<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	
	# assign portfolio to agent
	if(isset($_GET["assign"])){
		$uid = trim($_GET["assign"]);
		$names=$colls=$lis=[]; $trs="";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD'"); 
		foreach($res as $row){
			$rid=$row["id"]; $names[$rid] = ucwords(prepare($row["name"]));
			if($rid==$uid){ $info=$row; }
			if(isset(staffPost($rid)["collection agent"])){
				$conf=json_decode($row["config"],1);
				$colls+= (isset($conf["collaccs"]) && $rid!=$uid) ? $conf["collaccs"]:[];
			}
		}
		
		$name = ucwords(prepare($info["name"])); $def=json_decode($info["config"],1); 
		$mine = (isset($def["collaccs"])) ? $def["collaccs"]:[]; $rno=(isset($def["collfro"])) ? $def["collfro"]:0;
		$sql = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `org$cid"."_loans` WHERE (balance+penalty)>0");
		if($sql){
			foreach($sql as $row){
				$lof=$row['loan_officer'];
				if(!in_array($lof,$colls)){
					$cnd = (in_array($lof,$mine)) ? "checked":"";
					$lis[] = "<li style='margin-top:5px;list-style:none'><input type='checkbox' value='$lof' name='offs[]' $cnd></checkbox>&nbsp; $names[$lof]</li>";
				}
			}
		}
		
		if($lis){
    		$half = ceil(count($lis)/2); $chunk=array_chunk($lis,$half); 
    		$trs = "<tr valign='top'><td style='width:50%'>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:400px'>
			<h3 style='font-size:23px;text-align:center;color:#191970'>$name Portfolios</h3><br>
			<form method='post' id='arform' onsubmit=\"saveagent(event)\">
				<input type='hidden' name='agentid' value='$uid'>
				<table cellpadding='10' style='width:100%'>
					<tr><td colspan='2'>Automatically Alocate Loan to Agent</td></tr>
					<tr><td>Enter Days in arrears</td><td><input type='number' name='arrno' value='$rno' style='width:100px' required></td></tr> $trs
				</table><hr>
				<p style='text-align:right'><button class='btnn'>Save</button></p><br>
			</form>
		</div>";
	}
	
	# manage assigned loans
	if(isset($_GET["portag"])){
		$rid = trim($_GET["portag"]); $ids=[];
		$sql = $db->query(2,"SELECT `client_idno` FROM `org".$cid."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$rid");
		if($sql){
			foreach($sql as $row){ $ids[]=$row['client_idno']; }
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:450px'>
			<h3 style='font-size:23px;text-align:center;color:#191970'>Agent Assigned Loans</h3><br>
			<form method='post' id='pform' onsubmit=\"saveport(event)\">
				<input type='hidden' name='portid' value='$rid'>
				<p>Assigned Client ID Numbers <b>*Separate by Comma(,)*</b></p><p><textarea class='mssg' name='ptids' 
				style='min-height:300px'>".implode(",",$ids)."</textarea></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p><br>
			</form>
		</div>";
	}
	
	# agents
	if(isset($_GET["agents"])){
		$uid = trim($_GET["agents"]);
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$day = (isset($_GET['dy'])) ? trim($_GET['dy']):0;
		$monrange = monrange(date('m',$mon),date("Y",$mon)); $dto=$monrange[1]+86399;
		
		$ltbl = "org".$cid."_loans"; $tmon=strtotime(date("Y-M"));
		$me = staffInfo($sid); $cnf=json_decode($me['config'],1); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$show;
		$cond = ($day) ? "AND `day`='$day'":"";
		
		$css = "style='text-align:center'"; $no=0; $trs=""; $rids=[];
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `status`='0' AND NOT `position`='USSD' $show");
		if($sql){
			foreach($sql as $rw){
				if(isset(staffPost($rw['id'])["collection agent"])){
					$rid=$rw['id']; $name=prepare(ucwords($rw['name'])); $def=json_decode($rw["config"],1); $rids[]="`officer`='$rid'"; $no++;
					$qri = $db->query(2,"SELECT COUNT(*) AS tot,SUM(balance+penalty) AS tsum FROM `$ltbl` WHERE JSON_EXTRACT(clientdes,'$.agent')=$rid $show");
					$qry = $db->query(2,"SELECT SUM(amount) AS tsum FROM `processed_payments$cid` WHERE `month`='$mon' AND `officer`='$rid' $show $cond");
					$ports = (isset($def["collaccs"])) ? $def["collaccs"]:[]; $frd=(isset($def["collfro"])) ? $def["collfro"]:"";
					$lnbk=number_format(intval($qri[0]['tsum'])); $tot=intval($qri[0]['tot']); $coll=number_format(intval($qry[0]['tsum'])); $lis=$bls=""; $brs=[];
					
					if($tot && !$ports){
						$chk1 = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ltbl` WHERE JSON_EXTRACT(clientdes,'$.agent')=$rid");
						if($chk1){
							foreach($chk1 as $row){ $ports[]=$row['loan_officer']; }
						}
					}
					
					foreach($ports as $pt){ $lis.= "<li>$staff[$pt]</li>"; $brs[$mybran[$pt]]=$bnames[$mybran[$pt]]; }
					foreach($brs as $bid=>$bn){ $bls.= "<li>$bn</li>"; }
					$lis = ($lis) ? $lis:"None"; $bls=($bls) ? $bls:"None";
					if($day && count($ports)>3){ $lis=$bls="<i>Hidden</i>"; }
					
					$tot.= (in_array("transfer clients",$perms)) ? " &nbsp;<a href='javascript:void(0)' onclick=\"popupload('collections.php?portag=$rid')\"><i class='fa fa-pencil'></i> Edit</a>":"";
					$edit = (in_array("transfer clients",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('collections.php?assign=$rid')\"><i class='bi-pencil'></i> Manage</a>":"N/A";
					$viewl = ($tot) ? "<a href='javascript:void(0)' style='color:#20B2AA' onclick=\"loadpage('loans.php?manage&vcol=$rid')\"><i class='bi-eye'></i> View Loans</a>":"";
					$viewc = ($coll) ? "<a href='javascript:void(0)' onclick=\"loadpage('payments.php?viewpays=all&pos=s:$rid&mon=$mon&dy=$day')\"><i class='bi-eye'></i> View</a>":"";
					$trs.= "<tr valign='top'><td>$no</td><td>$name</td><td>$bls</td><td>$lis</td><td $css>$lnbk</td><td $css>$tot<br>$viewl</td><td $css>$coll<br>$viewc</td><td>$edit</td></tr>";
				}
			}
		}
		
		$mns = "<option value='$tmon'>".date("M Y")."</option>"; $cond=($rids) ? "AND (".implode(" OR ",$rids).")":"";
		$sql = $db->query(2,"SELECT DISTINCT `month` FROM `processed_payments$cid` WHERE NOT `month`='$tmon' $cond ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":"";
				$mns.= "<option value='$mn' $cnd>".date("M Y",$mn)."</option>";
			}
		}
		
		$dys = "<option value='0'>-- Select Day --</option>";
		$qri = $db->query(2,"SELECT DISTINCT `day` FROM `processed_payments$cid` WHERE `month`='$mon' $cond ORDER BY `day` DESC");
		if($qri){
			foreach($qri as $row){
				$dy=$row['day']; $cnd=($dy==$day) ? "selected":"";
				$dys.= "<option value='$dy' $cnd>".date("d-m-Y",$dy)."</option>";
			}
		}
		
		$dtx = ($day) ? date("M-d",$day):date("M",$mon);
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Collection Agents Report</h3>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:;min-width:600px;' cellpadding='5'> 
						<caption style='caption-side:top'> 
							<select style='width:150px;font-size:15px' onchange=\"loadpage('collections.php?agents&mon='+this.value.trim())\">$mns</select>
							<select style='width:140px;font-size:15px' onchange=\"loadpage('collections.php?agents&mon=$mon&dy='+this.value.trim())\">$dys</select>
						</caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;' valign='top'><td colspan='2'>Agent Name</td><td>Branches</td>
						<td>Portfolios</td><td $css>Loanbook</td><td $css>Assigned Loans</td><td $css>$dtx Collections</td><td>Action</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed Collection Agents reports");
	}
	
	# collection rates
	if(isset($_GET['rates'])){
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$regn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$monrange = monrange(date('m',$mon),date("Y",$mon)); $dto=$monrange[1]+86399;
		
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$me = staffInfo($sid); $cnf=json_decode($me['config'],1); $access=$me['access_level'];
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($regn && !$bran){ $me['access_level']="region"; $cnf['region']=$regn; }
		$show = ($me['access_level']=="branch" or $bran) ? "AND branch='".$me['branch']."'":"";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"]):$show;
		$cond = ($me['access_level']=="portfolio") ? "AND loan_officer='$sid'":$show;
		$cfield = (in_array($me['access_level'],["hq","region"])) ? "branch":"loan_officer";
		$ttl = (in_array($me['access_level'],["hq","region"])) ? "Branch":"Loan Officer";
		$trs=$brans=$mns=$regs=""; $tln=$tpf=$totc=$toc=$tdd8=$tarr=$tgc=$tcg7=0;
		$cond2 = ($cond) ? str_replace("AND ","AND ln.",$cond):""; $cond2=str_replace("ln.(","(",$cond2);
		
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT(ln.clientdes,'$.agent')=$sid";
			$cond2.= " AND JSON_EXTRACT(ln.clientdes,'$.agent')=$sid";
		}
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` AS ln WHERE `disbursement` BETWEEN $mon AND $dto $cond");
		if($res){
			$fork = new DBAsync($sid);
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d"));  
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum FROM `$ltbl` AS ln WHERE `$cfield`='$def' AND `disbursement` BETWEEN $mon AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan WHERE sd.balance>0 AND ln.disbursement 
				BETWEEN $mon AND $dto AND sd.day<$today AND $cfield='$def' $cond");
				$fork->add(2,"SELECT SUM(pr.amount) AS tsum,SUM((CASE WHEN (pr.day<=ln.expiry) THEN pr.amount ELSE 0 END)) AS otc,SUM((CASE WHEN (pr.day BETWEEN ln.expiry+(1) 
				AND ln.expiry+(604800)) THEN pr.amount ELSE 0 END)) AS dd7,SUM((CASE WHEN (pr.day>ln.expiry+(604800)) THEN pr.amount ELSE 0 END)) AS cg7 FROM `$ltbl` AS ln STRAIGHT_JOIN 
				`processed_payments$cid` AS pr ON pr.linked=ln.loan WHERE ln.$cfield='$def' AND ln.disbursement BETWEEN $mon AND $dto AND NOT pr.payment='Penalties' $cond2");
				
				$name = (in_array($me['access_level'],["hq","region"])) ? $bnames[$def]:$staff[$def]; 
				$data[] = array("name"=>$name,"tln"=>$sq1[0]['tln'],"tpv"=>$sq1[0]['tsum'],"arr"=>$sq2[0]['tarr']);
			}
			
			$fres = $fork->run(); 
			foreach($fres as $key=>$one){
				$pv=$data[$key]["tln"]; $tpv=$data[$key]["tpv"]; $arr=$data[$key]["arr"]; $name=$data[$key]["name"]; $otc=$one[0]['otc']; $dd8=$one[0]['dd7']; $oc=$otc+$dd8;
				$tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $totc+=$otc; $toc+=$oc; $tdd8+=$dd8; $gc=$one[0]['tsum']; $cg7=$one[0]['cg7']; $tcg7+=$cg7; $tgc+=$gc;
				$otp=round($otc/$tpv*100,2); $ocp=round($oc/$tpv*100,2); $gcp=round($gc/$tpv*100,2);
				$trs.= "<tr><td style='float:left'>$name</td><td>".number_format($pv)."</td><td>".number_format($tpv)."</td><td>".number_format($otc)."</td>
				<td>".number_format($oc)."</td><td>".number_format($dd8)."</td><td>".number_format($cg7)."</td><td>".number_format($arr)."</td><td>$otp%</td>
				<td>$ocp%</td><td>$gcp%</td></tr>";
			}
			
			$totp=round($totc/$tpf*100,2); $tocp=round($toc/$tpf*100,2); $tgcp=round($tgc/$tpf*100,2);
			$trs.= "<tr style='color:#191970;font-weight:bold;background:linear-gradient(to top,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff'>
			<td>Totals</td><td>".number_format($tln)."</td><td>".number_format($tpf)."</td><td>".number_format($totc)."</td><td>".number_format($toc)."</td>
			<td>".number_format($tdd8)."</td><td>".number_format($tcg7)."</td><td>".number_format($tarr)."</td><td>$totp%</td><td>$tocp%</td><td>$tgcp%</td></tr>";
		}
		
		if($access=="hq" or $access=="region"){
			$brn = "<option value='0'>Corporate</option>";
			$cond2 = ($access=="region") ? "AND ".setRegion($cnf["region"]):""; $cond2=($regn) ? "AND ".setRegion($regn):$cond2;
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE `disbursement` BETWEEN $mon AND $dto $cond2");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brn.= "<option value='$rid' $cnd>".$bnames[$rid]."</option>";
				}
			}
			
			$chk = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid'"); 
			if($access=="hq" && $chk){
				$rops = "<option value='0'>-- Filter Region --</option>";
				foreach($chk as $row){
					$rid=$row['id']; $cnd=($regn==$rid) ? "selected":"";
					$rops.="<option value='$rid' $cnd>".prepare(ucfirst($row['name']))."</option>";
				}
				$regs = "<select style='width:150px;font-size:15px' onchange=\"loadpage('collections.php?rates&mon=$mon&reg='+this.value.trim())\">$rops</select>";
			}
			$brans = "<select style='width:150px;font-size:15px' onchange=\"loadpage('collections.php?rates&mon=$mon&reg=$regn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$tmon = strtotime(date("Y-M")); $mns = "<option value='$tmon'>".date("M Y")."</option>";
		$sql = $db->query(2,"SELECT DISTINCT `month` FROM `processed_payments$cid` WHERE NOT `month`='$tmon' ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd = ($mn==$mon) ? "selected":"";
				$mns.= "<option value='$mn' $cnd>".date("M Y",$mn)."</option>";
			}
		}
		
		$src = base64_encode($cond);
		$prnt = ($trs) ? genrepDiv("collections.php?src=$src&v=rates&dy=$mon&br=$bran&cf=$cfield&reg=$regn",'right'):"";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn ".date('M-Y',$mon)." Collection Rates</h3>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:center;min-width:600px;' cellpadding='5'> 
						<caption style='caption-side:top'> $prnt
							<select style='width:130px;font-size:15px' onchange=\"loadpage('collections.php?rates&mon='+this.value.trim())\">$mns</select> $regs $brans
						</caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td style='float:left'>$ttl</td>
						<td>Disbursed Loan</td><td>Loan+Charges</td><td>OTC</td><td>OC</td><td>DD7</td><td>CG7</td><td>Arrears</td><td>OTC%</td><td>OC%</td><td>GC%</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed ".date('M-Y',$mon)." Collection Rates");
	}
	
	# collection report
	if(isset($_GET['report'])){
		$tdy = strtotime(date("Y-M-d"));
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$frm = (isset($_GET['fro'])) ? strtotime(trim($_GET['fro'])):$tdy;
		$dtu = (isset($_GET['dto'])) ? strtotime(trim($_GET['dto'])):$tdy; $dtu+=86399;
		$fro = ($frm>$dtu) ? $dtu:$frm; $dto=($frm>$dtu) ? $frm:$dtu;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$fdy = date('Y-m-d',$fro); $fto=date('Y-m-d',$dto); 
		
		$me = staffInfo($sid); 
		$access = $me['access_level'];
		$stbl = "org".$cid."_schedule"; $ltbl="org".$cid."_loans";
		$cnf = json_decode($me["config"],1); $staff=$bnames=[]; 
		$mons=$days=$offs=$brans=$trs="";
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND `officer`='$sid'";
		if(in_array($me["access_level"],["branch","region"])){
			$rid = (isset($cnf["region"])) ? $cnf["region"]:0;
			if($rid && !$bran){
				$sql = $db->query(1,"SELECT `branches` FROM `regions` WHERE `id`='$rid'");
				foreach(json_decode($sql[0]["branches"],1) as $id){ $cnd[]="`branch`='$id'"; }
			}
			else{ $cnd[]="`branch`='".$me["branch"]."'"; }
			
			$sql = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE ".implode(" OR ",$cnd));
			if($sql){
				foreach($sql as $row){ $id=$row["id"]; $stl[]="`officer`='$id'"; }
				$show = "AND (".implode(" OR ",$stl).")";
			}
		}
		
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$cond.= ($stid) ? " AND `officer`='$stid'":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT `loan` FROM `$ltbl` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $lns[]="'".$row["loan"]."'"; }
				$cond.= " AND `loan` IN (".implode(",",$lns).")";
			}
		}
		
		$total=$paid=$no=$bals=$tcum=0; $list=$lns=$arrs=$lids=[];
		$qri = $db->query(2,"SELECT day,loan,SUM(amount) AS tsum,SUM(paid) AS tpay,SUM(balance) AS tbal FROM `$stbl` WHERE `day` BETWEEN $fro AND $dto $cond GROUP BY `loan`");
		if($qri){
			foreach($qri as $row){ $lid = $row["loan"]; $lns[]="'$lid'"; $list[$lid]=$row; }
		}
		
		if($lns){
			$sql = $db->query(2,"SELECT SUM(balance) AS tbal,`loan` FROM `$stbl` WHERE `loan` IN (".implode(",",$lns).") AND `day`<$tdy AND `balance`>0 GROUP BY `loan`");
			if($sql){
				foreach($sql as $row){ $arrs[$row["loan"]]=intval($row["tbal"]); }
			}
		
			$qry = $db->query(2,"SELECT `loan`,`client`,`phone`,`branch`,`client_idno`,`loan_officer` FROM `$ltbl` WHERE `loan` IN (".implode(",",$lns).")
			AND (balance>0 OR status>=$fro) ORDER BY `client` ASC");
			foreach($qry as $rw){
				$lid = $rw["loan"]; $row=$list[$lid]; $name=prepare(ucwords($rw['client'])); $ofname=$staff[$rw['loan_officer']]; $fon=$rw['phone']; $idno=$rw['client_idno'];
				$pay=$row["tpay"]; $amnt=$row["tsum"]; $bal=$row["tbal"]; $dy=$row["day"]; $bname=$bnames[$rw['branch']]; $total+=$amnt; $paid+=$pay; $bals+=$bal;
				$td = ($me['access_level']=="hq" or $me['access_level']=="region") ? "<td>$bname</td>":""; $acum=(isset($arrs[$lid])) ? $arrs[$lid]:0; $tcum+=$acum; $no++; 
				
				$trs.= "<tr><td>$no</td><td><span style='cursor:pointer' onclick=\"loadpage('clients.php?showclient=$idno')\">$name</span></td><td>0$fon</td>
				<td>$ofname</td>$td<td>".fnum($amnt)."</td><td>".fnum($acum)."</td><td>".fnum($pay)."</td><td>".fnum($bal)."</td></tr>";
			}
			
			$cols = ($me['access_level']=="hq" or $me['access_level']=="region") ? 5:4; 
			$trs.= "<tr style='font-weight:bold;color:#191970'><td colspan='$cols'></td><td>".fnum($total)."</td><td>".fnum($tcum)."</td><td>".fnum($paid)."</td><td>".fnum($bals)."</td></tr>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$opts = "<option value='0'>-- Portfolio --</option>"; $mb=($bran) ? $bran:$me['branch'];
			$res = $db->query(2,"SELECT DISTINCT st.officer FROM `$stbl` AS st INNER JOIN `$ltbl` AS ln ON ln.id=st.loan WHERE st.day BETWEEN $fro AND $dto AND ln.branch='$mb'");
			if($res){
				foreach($res as $row){
					$off=$row['officer']; $cnd=($stid==$off) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
			}
			$offs = "<select style='width:150px' onchange=\"loadpage('collections.php?report&bran=$bran&fro=$fdy&dto=$fto&staff='+this.value)\">$opts</select>";
		}
		
		if($access=="hq" or $access=="region"){
			$brns = "<option value='0'>Corporate</option>";
			$cond2 = ($me["access_level"]=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE `disbursement`<$dto $cond2");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brns.= "<option value='$rid' $cnd>".$bnames[$rid]."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('collections.php?report&fro=$fdy&dto=$fto&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('collections.php?report&fro=$fdy&dto=$fto&reg=$rgn&bran='+this.value.trim())\">$brns</select>";
		}
		
		$src = base64_encode($cond);
		$th = ($me['access_level']=="hq" or $me['access_level']=="region") ? "<td>Branch</td>":"";
		$prnt = ($trs) ? genrepDiv("collections.php?src=$src&v=rep&dy=$fro-$dto",'right'):"";
		$perc = ($total) ? round($paid/$total*100,1):0;
		$color = ($perc<25) ? "#C71585":"#3CB371"; 
		$color = ($perc>=25 && $perc<50) ? "#FFA500":$color;
		$color = ($perc==0) ? "#FF6347":$color;
		$mto = date("Y-m-d",strtotime("Today")+259200);
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;min-width:600px'>
			<div class='container' style='padding:7px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Collection Report
				<span class='btnn' style='float:right;text-align:center;padding:5px;font-size:15px;min-width:60px;cursor:default;background:$color'>$perc%</span></h3>
				<hr style='margin-bottom:5px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered' style='width:100%;font-size:14px;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'> $prnt
							<input type='date' style='width:130px;padding:4px 6px;' id='fro' value='$fdy' max='$mto' onchange=\"getrange(this.value)\"> ~
							<input type='date' style='width:130px;padding:4px 6px;' id='dto' value='$fto' max='$mto' onchange=\"getrange(this.value)\"> $brans $offs
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>
						<td colspan='2'>Client</td><td>Contact</td><td>Portfolio</td>$th<td>Collection</td><td>Arrears</td><td>Paid</td><td>Balance</td></tr> $trs
					</table>
				</div>
			</div>
		</div>
		<script>
			function getrange(val){
				var fro = $('#fro').val(), dto=$('#dto').val();
				loadpage('collections.php?report&fro='+fro+'&dto='+dto);
			}
		</script>";
		savelog($sid,"View Collection Report between $fdy to $fto");
	}
	
	# collection sheet
	if(isset($_GET['view'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$mon = (isset($_GET['mon'])) ? clean($_GET['mon']):strtotime(date("Y-M"));
		$day = (isset($_GET['day'])) ? clean($_GET['day']):strtotime(date("Y-M-d"));
		$vin = (isset($_GET['vin'])) ? trim($_GET['vin']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$cnf = json_decode($me["config"],1);
		$jana = strtotime("Yesterday");
		$stbl = "org".$cid."_schedule";
		$ltbl = "org".$cid."_loans";
		$staff=$bnames=[]; $mons=$days=$offs=$trs=$brans="";
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$res = $db->query(2,"SELECT DISTINCT `month` FROM `$stbl` WHERE `balance`>0 AND `day`>$jana ORDER BY `month` ASC");
		if($res){
			foreach($res as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":""; $monts[]=$mn;
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
			$mon=(isset($_GET['mon'])) ? $mon:min($monts);
		}
		else{ $mons = "<option value='0'> -- Month --</option>"; }
		
		$res = $db->query(2,"SELECT DISTINCT `day` FROM `$stbl` WHERE `balance`>0 AND `day`>$jana AND `month`='$mon' ORDER BY `day` ASC");
		if($res){
			foreach($res as $row){
				$dy=$row['day']; $cnd=($day==$dy) ? "selected":""; $dys[]=$dy;
				$days.="<option value='$dy' $cnd>".date('D d',$dy)."</option>";
			}
			$day=(isset($_GET['day'])) ? $day:min($dys);
		}
		else{ $days = "<option value='0'> -- Day --</option>"; }
		
		if($me['access_level']=="branch" or $bran){
			$opts = "<option value='0'>-- Portfolio --</option>"; $mb=($bran) ? $bran:$me['branch'];
			$res = $db->query(2,"SELECT DISTINCT st.officer FROM `$stbl` AS st INNER JOIN `org".$cid."_loans` AS ln ON ln.id=st.loan WHERE st.day='$day' 
			AND ln.balance>0 AND ln.branch='$mb'");
			if($res){
				foreach($res as $row){
					$off=$row['officer']; $cnd=($stid==$off) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
			}
			$offs = "<select style='width:150px' onchange=\"loadpage('collections.php?view&bran=$bran&day=$day&staff='+this.value)\">$opts</select>";
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND `officer`='$sid'"; $cnd=[];
		if(in_array($me["access_level"],["branch","region"])){
			$rid = (isset($cnf["region"])) ? $cnf["region"]:0;
			if($rid && !$bran){
				$sql = $db->query(1,"SELECT `branches` FROM `regions` WHERE `id`='$rid'");
				foreach(json_decode($sql[0]["branches"],1) as $id){ $cnd[]="`branch`='$id'"; }
			}
			else{ $cnd[]="`branch`='".$me["branch"]."'"; }
			
			$sql = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE ".implode(" OR ",$cnd));
			if($sql){
				foreach($sql as $row){ $id=$row["id"]; $stl[]="`officer`='$id'"; }
				$show = "AND (".implode(" OR ",$stl).")";
			}
		}
		
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$cond.= ($stid) ? " AND `officer`='$stid'":"";
		$cond.= ($vin) ? " AND JSON_UNQUOTE(JSON_EXTRACT(def,'$.inst'))=$vin":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT `loan` FROM `$ltbl` WHERE JSON_UNQUOTE(JSON_EXTRACT(clientdes,'$.agent'))=$sid");
			if($sql){
				foreach($sql as $row){ $lns[]="'".$row["loan"]."'"; }
				$cond.= " AND `loan` IN (".implode(",",$lns).")";
			}
		}
		
		$total=$paid=$no=$tcum=0; $phones=$lns=$list=$arrs=$insts=$mxn=[]; $tdy=strtotime("Today");
		$qri = $db->query(2,"SELECT day,loan,paid,amount FROM `$stbl` WHERE `day`='$day' AND `balance`>0 $cond GROUP BY `loan`");
		if($qri){
			foreach($qri as $row){ $lid = $row["loan"]; $lns[]="'$lid'"; $list[$lid]=$row; }
		}
		
		if($lns){
			$res = $db->query(2,"SELECT GROUP_CONCAT(day) AS days,`loan` FROM `$stbl` WHERE `loan` IN (".implode(",",$lns).") GROUP BY loan ORDER BY day ASC");
			foreach($res as $row){
				$dys = explode(",",$row['days']); sort($dys); $nj=array_search($day,$dys)+1; $insts[$row["loan"]]="$nj/".count($dys); $mxn[$nj]=$nj;
			}
			
			$sql = $db->query(2,"SELECT SUM(balance) AS tbal,`loan` FROM `org$cid"."_schedule` WHERE `loan` IN (".implode(",",$lns).") AND `day`<$tdy AND `balance`>0 GROUP BY `loan`");
			if($sql){
				foreach($sql as $row){ $arrs[$row["loan"]]=intval($row["tbal"]); }
			}
		
			$qry = $db->query(2,"SELECT `loan`,`client`,`phone`,`branch`,`client_idno`,`loan_officer` FROM `$ltbl` WHERE `loan` IN (".implode(",",$lns).") ORDER BY `client` ASC");
			foreach($qry as $rw){
				$lid = $rw["loan"]; $row=$list[$lid]; $name=prepare(ucwords($rw['client'])); $ofname=$staff[$rw['loan_officer']]; $fon=$rw['phone']; $idno=$rw['client_idno'];
				$pay=$row["paid"]; $amnt=$row["amount"]; $dy=$row["day"]; $bname=$bnames[$rw['branch']]; $total+=$amnt; $paid+=$pay; $no++; 
				$td = ($me['access_level']=="hq" or $me['access_level']=="region") ? "<td>$bname</td>":""; $inst=$insts[$lid];
				$acum=(isset($arrs[$lid])) ? $arrs[$lid]:0; $tcum+=$acum; $phones[]="254$fon";
				
				$trs.= "<tr><td>$no</td><td><span style='cursor:pointer' onclick=\"loadpage('clients.php?showclient=$idno')\">$name</span></td><td>0$fon</td>
				<td>$ofname</td>$td<td>$inst</td><td>".fnum($amnt)."</td><td>".fnum($acum)."</td><td>".fnum($pay)."</td></tr>";
			}
			
			$cols = ($me['access_level']=="hq" or $me['access_level']=="region") ? 6:5;
			$trs.= "<tr style='font-weight:bold;color:#191970'><td colspan='$cols'></td><td>".fnum($total)."</td><td>".fnum($tcum)."</td><td>".fnum($paid)."</td></tr>";
		}
		
		if(in_array($access,["hq","region"])){
			$rg = (isset($cnf["region"])) ? $cnf["region"]:0;
			$brns = "<option value='0'>Corporate</option>"; $lod=($rgn) ? $rgn:$rg;
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($lod,"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('collections.php?view&day=$day&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('collections.php?view&day=$day&reg=$rgn&bran='+this.value.trim())\">$brns</select>";
		}
		
		$src = base64_encode($cond); $tot=count($phones); $instop="";
		$th = ($me['access_level']=="hq") ? "<td>Branch</td>":"";
		$prnt = ($trs) ? genrepDiv("collections.php?src=$src&v=col&dy=$day",'right'):"";
		$sms = ($trs && in_array("send sms",$perms)) ? "<button class='bts' style='float:right;padding:3px' onclick=\"popupload('collections.php?fsms=$tot&day=$day&vin=$vin')\">Send SMS</button>":"";
		
		if($mxn){
			$nis = "<option value='0'>- Installment -</option>";
			for($i=1; $i<=max($mxn); $i++){
				$cnd = ($i==$vin) ? "selected":"";
				$nis.= "<option value='$i' $cnd>Installment $i</option>";
			}
			$instop = "<select style='width:130px' onchange=\"loadpage('collections.php?view&mon=$mon&day=$day&reg=$rgn&bran=$bran&vin='+this.value)\">$nis</select>";
		}
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;min-width:600px'>
			<div class='container' style='padding:7px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Collection sheet for ".date('d-m-Y',$day)." $sms</h3><hr style='margin-bottom:5px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered' style='width:100%;font-size:14px;' cellpadding='5'>
						<caption style='caption-side:top'> $prnt
							<select style='width:110px' onchange=\"loadpage('collections.php?view&mon='+cleanstr(this.value))\">$mons</select>
							<select style='width:110px' onchange=\"loadpage('collections.php?view&mon=$mon&day='+this.value.trim())\">$days</select>
							<textarea id='phones' style='display:none'>".json_encode($phones,1)."</textarea> $brans $offs $instop
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>
						<td colspan='2'>Client</td><td>Contact</td><td>Portfolio</td>$th<td>Installment</td><td>Amount</td><td>Accumulated</td><td>Paid</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"View Collection Sheet for ".date("d-m-Y",$day));
	}
	
	# structure sms
	if(isset($_GET['fsms'])){
		$tot = trim($_GET['fsms']); 
		$dy = trim($_GET['day']); 
		$me = staffInfo($sid); 
		
		$all=($tot==1) ? "1 Client":"$tot Clients";
		$def = "Dear CLIENT,\r\nKindly make your Installment of Ksh AMOUNT by DATE before 12 noon to avoid being a defaulter.\r\nThank you.";
		$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND `name`='inst200'");
		$mssg = ($res) ? prepare($res[0]['message']):$def; $len=countext($mssg);
		
		$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":$cond;
		
		$opts="<option value='0'> -- Select --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' $cond");
		foreach($res as $row){
			$opts.="<option value='254".$row['contact']."'>".prepare(ucwords($row['name']))."</option>";
		}
		
		echo "<div style='padding:10px;max-width:350px;margin:0 auto'>
			<h3 style='font-size:22px;text-align:center'>Send SMS to $all</h3><br>
			<p style='text-align:center;font-size:14px;color:#191970'>** Use keywords { CLIENT, AMOUNT, DATE, ARREARS, PENALTY, BALANCE & IDNO } to pick client firstname,
			installment Amount, Date, Arrears, Penalties, Loan balance & Client IDNO for Payment in your Message Composition **</p>
			<form method='post' id='sfom' onsubmit='smsclients(event)'>
				<input type='hidden' name='pday' value='$dy'>
				<p>Message Composition<br><textarea name='clmssg' class='mssg' onkeyup=\"countext('crem',this.value)\" 
				style='height:150px;font-size:15px;' readonly required>$mssg</textarea><br><span id='crem' style='float:right;color:#483D8B'>$len</span></p><br>
				<p>Send Sample SMS to<br><select name='addto' style='width:60%'>$opts</select>
				<button class='btnn' style='float:right'>Send</button></p><br>
			</form><br>
		</div>";
		
		savelog($sid,"Accessed Payment reminder SMS form");
	}
	
	
	ob_end_flush();
?>

<script>
			
	function smsclients(e){
		e.preventDefault();
		if(confirm("Send Reminder SMS to clients?")){
			var data=$("#sfom").serialize(); data+="&phones="+_("phones").value;
			$.ajax({
				method:"post",url:path()+"dbsave/sendsms.php",data:data,
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(" ")[0]=="Sent"){ alert(res); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function saveagent(e){
		e.preventDefault();
		if(confirm("Proceed to update agent portfolios?")){
			var data = $("#arform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("collections.php?agents"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveport(e){
		e.preventDefault();
		if(confirm("Proceed to update agent assigned Loans?")){
			var data = $("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("collections.php?agents"); }
				else{ alert(res); }
			});
		}
	}

</script>