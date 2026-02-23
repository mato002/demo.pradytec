<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# loan summary
	if(isset($_GET['loansummary'])){
		$stid = (isset($_GET['stid'])) ? trim($_GET['stid']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$me = staffInfo($sid); $access=$me['access_level'];
		$cnf = json_decode($me["config"],1);
		
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
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":" AND branch='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"]):$show;
		$cond = ($me['access_level']=="portfolio") ? " AND loan_officer='$sid'":$show;
		$cond.= ($stid) ? " AND loan_officer='$stid'":""; $cond2=$cond;
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT($ltbl.clientdes,'$.agent')=$sid";
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="`idno`='".$row['client_idno']."'"; }
				$cond2.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$cls=$actv=$perfs=$arrears=$rloans=$princs=$intrs=$no=$tpen=0; $trs=$brans=$offs="";
		$cfield = ($me['access_level']=="hq" or $me['access_level']=="region") ? "branch":"loan_officer";
		$ttl = ($me['access_level']=="hq" or $me['access_level']=="region") ? "Branch":"Loan Officer";
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `balance`>0 $cond");
		if($res){
			$fork = new DBAsync($sid);
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d"));
				$res1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_clients` WHERE `$cfield`='$def' $cond2");
				$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `$ltbl` WHERE `$cfield`='$def' AND (balance+penalty)>0 $cond");
				$res3 = $db->query(2,"SELECT DISTINCT sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND ($ltbl.balance+$ltbl.penalty)>0 AND sd.day<$today AND $cfield='$def' $cond");
				$res4 = $db->query(2,"SELECT SUM(sd.balance) AS arrears FROM `$ltbl` INNER JOIN `$stbl` AS sd ON $ltbl.loan=sd.loan WHERE $cfield='$def' AND $ltbl.balance>0 AND sd.balance>0 AND sd.day<$today $cond");
				$fork->add(2,"SELECT SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN (sd.amount-sd.paid) ELSE sd.interest END)) AS intr,SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 
				ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND `$cfield`='$def' $cond");
				$res6 = $db->query(2,"SELECT SUM(penalty) AS tpen FROM `$ltbl` WHERE $cfield='$def' $cond");
				$res7 = $db->query(2,"SELECT COUNT(id) AS tot FROM `$ltbl` WHERE $cfield='$def' AND `penalty`>0 AND `balance`='0' $cond");
				
				$tcl = $res1[0]['total']; $active=$res2[0]['total']; $arrln=($res3) ? count($res3):0; $arrs=($res4) ? $res4[0]['arrears']:0; 
				$pen = ($res6) ? $res6[0]['tpen']:0; $penon=($res7) ? intval($res7[0]["tot"]):0; $perf=$active-($arrln+$penon);
				$name = (in_array($me['access_level'],["hq","region"])) ? $bnames[$def]:$staff[$def]; 
				$data[] = array("name"=>$name,"tcl"=>$tcl,"actv"=>$active,"arrln"=>$arrln,"perf"=>$perf,"arrs"=>$arrs,"pen"=>$pen,"gen"=>"$cfield:$def");
			}
			
			foreach($fork->run() as $key=>$one){
				$intr=$one[0]['intr']; $princ=$one[0]['princ']; $tcl=$data[$key]["tcl"]; $active=$data[$key]["actv"]; $arrln=$data[$key]["arrln"];
				$perf=$data[$key]["perf"]; $arrs=$data[$key]["arrs"]; $pen=$data[$key]["pen"]; $name=$data[$key]["name"]; $gen=$data[$key]["gen"]; $no++;
				$cls+=$tcl; $actv+=$active; $perfs+=$perf; $arrears+=$arrs; $rloans+=$arrln; $princs+=$princ; $intrs+=$intr; $tpen+=$pen;
				
				$vw1 = ($perf>0) ? "<a href='javascript:void(0)' onclick=\"loadpage('branches.php?vrec=perf&def=$gen')\">View</a>":"";
				$vw2 = ($arrs>0) ? "<a href='javascript:void(0)' onclick=\"loadpage('branches.php?vrec=arrs&def=$gen')\">View</a>":"";
				
				$trs.= "<tr><td style='float:left'>$name</td><td>".fnum($tcl)."</td><td>".fnum($active)."</td><td>".fnum($princ)."</td><td>".fnum($intr)."</td><td>".fnum($pen)."</td>
				<td>".fnum($arrln)."</td><td>".fnum($perf)." $vw1</td><td>".fnum($arrs)." $vw2</td></tr>";
			}
			
			$trs.= "<tr style='color:#191970;font-weight:bold;background:linear-gradient(to top,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff'>
			<td>Totals</td><td>".fnum($cls)."</td><td>".fnum($actv)."</td><td>".fnum($princs)."</td><td>".fnum($intrs)."</td><td>".fnum($tpen)."</td><td>".fnum($rloans)."</td>
			<td>".fnum($perfs)."</td><td>".fnum($arrears)."</td></tr>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$cond2 = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE `balance`>0 $cond2");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".$bnames[$rid]."</option>";
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
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('branches.php?loansummary&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('branches.php?loansummary&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$src = base64_encode($cond); $pos=($brans) ? "right":"";
		$prnt = ($trs) ? genrepDiv("portfolio.php?src=$src&br=$bran&cf=$cfield",$pos):"";
		
		echo "<div class='cardv' style='max-width:1250px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1250px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Loan Summary</h3>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:center;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'>$prnt $brans </caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td style='float:left'>$ttl</td>
						<td>Total Clients</td><td>Active Clients</td><td>Active Principal</td><td>Active Interest</td><td>Unpaid Penalties</td><td>Loans in Arrears</td>
						<td>Performing Loans</td><td>Total Arrears</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed Branch Loan Summary");
	}
	
	# view summary record
	if(isset($_GET["vrec"])){
		$vtp = trim($_GET["vrec"]);
		$def = explode(":",trim($_GET["def"]));
		$col = $def[0]; $val=$def[1];
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$tdy = strtotime(date("Y-M-d")); 
		
		$dbn = ($col=="branch") ? 1:2; $list=[]; $trs=$data=""; $no=$tsum=0;
		$from = ($col=="branch") ? "SELECT `branch` AS name FROM `branches` WHERE `id`='$val'":"SELECT `name` FROM `org$cid"."_staff` WHERE `id`='$val'";
		$fsrc = prepare(ucwords($db->query($dbn,$from)[0]['name']));
		
		if($vtp=="perf"){
			$res1 = $db->query(2,"SELECT DISTINCT sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.loan=sd.loan WHERE sd.balance>0 AND ln.balance>0 AND sd.day<$tdy AND ln.$col='$val'");
			if($res1){
				foreach($res1 as $row){ $list[]=$row['loan']; }
			}
			
			$sql = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$col`='$val' AND balance>0 ORDER BY `client` ASC");
			foreach($sql as $row){
				if(!in_array($row['loan'],$list)){
					$name = prepare(ucwords($row['client'])); $pen=$row['penalty']; $disb=date("d-m-Y",$row['disbursement']); $amnt=fnum($row['amount']);
					$exp = date("d-m-Y",$row['expiry']); $bal=$row['balance']; $fon=$row['phone']; $no++; $tsum++;
					$trs.= "<tr><td>$no</td><td>$name</td><td>$fon</td><td>$amnt</td><td>$disb</td><td>".fnum($pen)."</td><td>".fnum($pen+$bal)."</td><td>$exp</td></tr>";
				}
			}
			
			$data = "<table cellpadding='5' style='width:100%' class='table-striped'>
				<tr style='color:#191970;background:#e6e6fa;font-weight:bold'><td colspan='2'>Client</td><td>Contact</td><td>Loan</td><td>Disbursement</td><td>Penalty</td>
				<td>T.Balance</td><td>Maturity</td></tr> $trs
			</table><br>";
		}
		else{
			$res = $db->query(2,"SELECT SUM(sd.balance) AS abal,ln.* FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan WHERE ln.$col='$val' AND ln.balance>0 
			AND sd.balance>0 AND sd.day<$tdy GROUP BY sd.loan ORDER BY ln.client");
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $arr=$row['abal']; $disb=date("d-m-Y",$row['disbursement']); $amnt=fnum($row['amount']);
				$exp=date("d-m-Y",$row['expiry']); $bal=$row['balance']; $fon=$row['phone']; $pen=$row['penalty']; $tsum+=$arr; $no++;
				$trs.= "<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$amnt</td><td>$disb</td><td>".fnum($arr)."</td><td>".fnum($pen+$bal)."</td><td>$exp</td></tr>";
			}
			
			$data = "<table cellpadding='5' style='width:100%' class='table-striped'>
				<tr style='color:#191970;background:#e6e6fa;font-weight:bold'><td colspan='2'>Client</td><td>Contact</td><td>Loan</td><td>Disbursement</td><td>Arrears</td>
				<td>T.Balance</td><td>Maturity</td></tr> $trs
			</table><br>";
		}
		
		$title = ($vtp=="perf") ? "$fsrc Performing Loans":"$fsrc Arrears";
		echo "<div class='container cardv' style='max-width:1100px;min-height:400px;overflow:auto'>
			<div style='padding:5px;max-width:1100px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($tsum).")</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>$data</div>
			</div>
		</div>";
	}
	
	# view branches
	if(isset($_GET['manage'])){
		$trs=""; $no=0;
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' ORDER BY `branch` ASC");
		if($res){
			foreach($res as $row){
				$bname=prepare(ucwords($row['branch'])); $payb=$row['paybill']; $no++; $id=$row['id'];
				$res1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".CLIENT_ID."_staff` WHERE `status`='0' AND `branch`='$id'"); 
				$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".CLIENT_ID."_clients` WHERE `branch`='$id'"); 
				$nst=($res1) ? fnum($res1[0]['total']):0; $ncl=($res2) ? fnum($res2[0]['total']):0;
				
				$txt=($row['status']) ? "Activate":"Suspend"; $val=($row['status']) ? 0:1;
				$state = ($row['status']) ? "<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>":
				"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>";
				
				$edit = (in_array("edit branch",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('branches.php?add=$id')\"><i class='fa fa-pencil'></i> Edit</a>":""; 
				$susp = (in_array("suspend branch",$perms)) ? "| <a href='javascript:void(0)' style='color:#6A5ACD' onclick=\"suspend('$id','$val')\"><i class='fa fa-gavel'></i> $txt</a>":"";
				
				$trs.= "<tr><td>$no</td><td>$bname</td><td>$payb</td><td>$nst</td><td>$ncl</td><td>$state</td><td>$edit $susp</td></tr>";
			}
		}
		
		$trs = ($trs) ? $trs:"<tr><td colspan='6'>No branches Found</td></tr>";
		echo "<div class='cardv' style='max-width:800px;min-height:300px;padding:15px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>Company Branches </h3>
			<table class='table-striped' style='width:100%;min-width:600px' cellpadding='7'>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA'><td colspan='2'>Branch</td><td>Paybill</td><td>Staff</td><td>Clients</td>
				<td>Status</td><td></td></tr> $trs
			</table>
		</div>";
	}
	
	# view regions
	if(isset($_GET['regions'])){
		$trs=""; $no=0;
		$me = staffInfo($sid); 
		$perms = getroles(explode(",",$me['roles']));
		
		$res = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['name'])); $locs=json_decode($row['branches'],1); $no++; $id=$row['id']; $nst=$ncl=0; $lis="";
				foreach($locs as $loc){
					$bname = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$loc'")[0]['branch'];
					$res1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org$cid"."_staff` WHERE `status`='0' AND `branch`='$loc'"); 
					$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `org$cid"."_clients` WHERE `branch`='$loc'"); 
					$nst+=($res1) ? $res1[0]['total']:0; $ncl+=($res2) ? $res2[0]['total']:0;
					$lis.="<li>".prepare(ucwords($bname))."</li>";
				}
				
				$edit = (in_array("create region",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('branches.php?cregn=$id')\"><i class='fa fa-pencil'></i> Edit</a>":""; 
				$del = (in_array("delete region",$perms)) ? "| <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delregion('$id')\"><i class='fa fa-trash'></i> Delete</a>":"";
				$trs.= "<tr valign='top'><td>$no</td><td>$name</td><td>$lis</td><td>$nst</td><td>$ncl</td><td>$edit $del</td></tr>";
			}
		}
		
		$trs = ($trs) ? $trs:"<tr><td colspan='6'>No regions Found</td></tr>";
		echo "<div class='cardv' style='max-width:900px;min-height:300px;padding:15px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>Company Regions </h3>
			<table class='table-striped' style='width:100%;min-width:600px' cellpadding='7'>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA'><td colspan='2'>Name</td><td>Branches</td><td>Staff</td>
				<td>Clients</td><td></td></tr> $trs
			</table>
		</div>";
	}
	
	#add/edit region
	if(isset($_GET['cregn'])){
		$rid=trim($_GET['cregn']);
		$bran=""; $brans=$lis=[];
		$title = "Create Region";
		
		if($rid){
			$sql=$db->query(1,"SELECT *FROM `regions` WHERE `id`='$rid'");
			$row=$sql[0]; $bran=prepare(ucfirst($row['name'])); $brans=json_decode($row['branches'],1); $title="Edit $bran";
		}
		
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qri){
			foreach($qri as $row){
				$bid=$row['id']; $cnd=(in_array($bid,$brans)) ? "checked":""; $name=prepare(ucfirst($row['branch']));
				$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='rbrans[]' value='$bid' $cnd></checkbox> &nbsp; $name</li>";
			}
		}
		else{ $lis[] = "<li>No branches Found</li>"; }
		
		$half = ceil(count($lis)/2); $chunk = array_chunk($lis,$half);
		$trs = (count($chunk)>1) ? "<tr valign='top'><td>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>":"<tr><td>".implode("",$chunk[0])."</td></tr>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:320px;font-family:cambria'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>$title</h3><br>
			<form method='post' id='rfom' onsubmit='saveregion(event)'>
				<input type='hidden' name='regid' value='$rid'>
				<p>Region Name<br><input type='text' name='rname' style='width:100%' value='$bran' required></p>
				<p style='margin-bottom:0px'>Select Branches</p><table style='width:100%;background:#f8f8f0;border:1px dotted #ccc' cellpadding='10'>$trs</table><br>
				<p style='text-align:right'><button class='btnn' style='width:80px'><i class='fa fa-plus'></i> Save</button></p><br>
			</form>
		</div>";
	}
	
	#add/edit branch
	if(isset($_GET['add'])){
		$bid=trim($_GET['add']);
		$bran=$pin=""; $payb=0;
		$title="Add Branch";
		
		if($bid){
			$sql=$db->query(1,"SELECT *FROM `branches` WHERE `id`='$bid'");
			$row=$sql[0]; $bran=prepare(ucfirst($row['branch'])); $payb=$row['paybill']; $title="Edit $bran";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px;font-family:cambria'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>$title</h3><br>
			<form method='post' id='bfom' onsubmit='savebranch(event)'>
				<input type='hidden' name='bid' value='$bid'>
				<p>Branch Name<br><input type='text' name='bname' style='width:100%' value='$bran' required></p>
				<p>Paybill or Till<br><input type='number' style='width:100%' name='bpay' value='$payb' required></p><br>
				<p style='text-align:right'><button class='btnn' style='width:80px'><i class='fa fa-plus'></i> Save</button></p><br>
			</form>
		</div>";
	}
	
	ob_end_flush();
?>
	<script>
		
		function suspend(id,val){
			var txt = (val) ? "Suspend":"Activate";
			if(confirm("Sure to "+txt+" branch?")){
				$.ajax({
					method:"post",url:path()+"dbsave/branch.php",data:{suspend:id+":"+val},
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Success"); loadpage("branches.php?manage"); }
					else{ alert(res); }
				});
			}
		}
		
		function delregion(id){
			if(confirm("Sure to remove region?")){
				$.ajax({
					method:"post",url:path()+"dbsave/branch.php",data:{delregion:id},
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success"); loadpage("branches.php?regions");
					}
					else{ alert(res); }
				});
			}
		}
	
		function saveregion(e){
			e.preventDefault();
			if(!checkboxes("rbrans[]")){ toast("No branch selected!"); }
			else{
				var data = $("#rfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/branch.php",data:data,
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success"); closepop(); loadpage("branches.php?regions");
					}
					else{ alert(res); }
				});
			}
		}
		
		function savebranch(e){
			e.preventDefault();
			var data = $("#bfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/branch.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success"); closepop(); loadpage("branches.php?manage"); 
				}
				else{ alert(res); }
			});
		}
		
	</script>