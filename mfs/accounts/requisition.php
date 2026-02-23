<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# view requisitions
	if(isset($_GET['view'])){
		$ftc = trim($_GET['view']);
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles'])); 
		$cnf = json_decode($me["config"],1); $access=$me['access_level'];
		$rtbl = "requisitions$cid"; $tmon=strtotime(date("Y-M"));
		
		if(!$db->istable(3,$rtbl)){
			$def = array("staff"=>"INT","branch"=>"INT","item_description"=>"TEXT","cost"=>"INT","approvals"=>"TEXT","approved"=>"CHAR","month"=>"INT","status"=>"INT","time"=>"INT");
			$db->createTbl(3,$rtbl,$def);
		}
		
		if(!$mon){
			$res = $db->query(3,"SELECT MAX(month) AS mon FROM `$rtbl`");
			$mon = ($res) ? $res[0]['mon']:$tmon;
		}
		
		if(!$ftc){
			$fetch = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$sql = $db->query(3,"SELECT COUNT(*) AS total FROM `$rtbl` WHERE `status`<10 AND `month`='$mon' $fetch");
			$pnd = ($sql) ? intval($sql[0]['total']):0; $ftc=($pnd) ? 2:3;
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $pozts[$row['id']]=$row['position'];
		}
		
		$mons=$divs=$brns=$bin="";
		$res = $db->query(3,"SELECT DISTINCT `month` FROM `$rtbl` ORDER BY `month` DESC LIMIT 12");
		if($res){
			foreach($res as $row){
				$mn=$row['month']; $cnd = ($mn==$mon) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$brans = array("Corporate");
		$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$mons = ($mons) ? $mons:"<option value='$tmon'>".date('M Y')."</option>";
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='requisition'");
		$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
		$lvs=$ldef=($app) ? json_decode($app[0]['levels'],1):[]; $hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[]; 
		$super = (isset($hlevs['requisition'])) ? $hlevs['requisition']:[]; $dcd=count($lvs);
		$ownview = (defined("UTILITY_VIEW_OWN")) ? array_map("strtolower",UTILITY_VIEW_OWN):[];
		$mine = (in_array($access,["portfolio","collection agent"]) or in_array(strtolower($me["position"]),$ownview)) ? 1:0;
		
		if(in_array($access,["branch"])){ $bran=$me['branch']; }
		$states = array(2=>"`status`<10",3=>"`status`>100",4=>"`status`='15'");
		$cond = (isset($states[$ftc])) ? "AND ".$states[$ftc]:"";
		$cond.= ($mine) ? " AND `staff`='$sid'":"";
		$cond.= (($bran or $access=="branch") && !$mine) ? " AND `branch`='$bran'":"";
		$cond.= ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? " AND ".setRegion($cnf["region"]):"";
		
		$qri = $db->query(3,"SELECT *FROM `$rtbl` WHERE `month`='$mon' $cond ORDER BY id DESC");
		if($qri){
			foreach($qri as $row){
				$rid=$row['id']; $items=json_decode($row['item_description'],1); unset($items["book"]); $tot=count($items); $st=$row['status']; $tym=$row['time'];
				$cost=number_format($row['cost']); $apprv=number_format($row['approved']); $st=$row['status']; $nxt=$st+1; $no=0;
				$sts=($st>100) ? 20:15; $state=($st<10) ? 10:$sts; $sno=prenum($rid); 
				$show = ($state==20) ? $apprv:$cost;
				
				$from=explode(" ",$staff[$row['staff']])[0]; $bname=$brans[$row['branch']];
				$colors1 = array(10=>"#D8BFD8",15=>"#CD5C5C",20=>"#66CDAA");
				$colors2 = array(10=>"#663399",15=>"#fff",20=>"#fff");
				
				$trs=$npos=""; $add=($state==15) ? "border:2px solid #AFEEEE;":"";
				$all = ($tot==1) ? "1 Item":"$tot Items";
				$status = array(10=>"$all Pending",15=>"$all Declined",20=>"$all Approved");
				$btx = "<button class='bts' onclick=\"loadpage('accounts/requisition.php?reqid=$rid')\" 
				style='padding:3px;border-radius:5px;$add'><i class='fa fa-gift'></i> Open</button>";
				
				if(array_key_exists($pozts[$row['staff']],$super)){
					foreach($lvs as $key=>$one){
						if($one==$pozts[$row['staff']]){ $lvs[$key]=$super[$one]; }
					}
				}
									
				foreach($lvs as $key=>$one){
					$levels[$key]=$one;
				}
				
				if($dcd && $st<10){
					foreach($levels as $pos=>$user){
						$key=$pos-1; if($st==$key){ $npos=$user; $appr[str_replace(" ","_",$user)]=$key; }
						$status[10]="$all - <i>Waiting ".prepare(ucfirst($npos))."</i>";
					}
					
					$posts = array("super user",$npos);
					$btx = (in_array("approve requisitions",$perms) && in_array($me['position'],$posts)) ? 
					"<button class='bts' onclick=\"loadpage('accounts/requisition.php?reqid=$rid&approve=$nxt')\" style='padding:3px;border-radius:5px;'>
					<i class='fa fa-check'></i> Approve</button>":$btx;
				}
				
				foreach($items as $one){
					$item=prepare(ucfirst($one['item'])); $qty=$one['qty']; $cst=($one['type']=="cash") ? $one['cost']:"---"; $no++;
					$trs.="<tr style='font-size:11px'><td>$no</td><td>$item</td><td>$qty</td><td>$cst</td></tr>";
				}
				
				$lvs=$ldef;
				$divs.="<div class='col-12 col-sm-6 col-md-4 col-lg-3 mb-2 mt-3'>
					<div style='background:$colors1[$state];padding:5px;border-radius:5px;height:225px;font-family:cambria'>
						<div style='height:100px;background:#fff;border-top-right-radius:5px;border-top-left-radius:5px;overflow:hidden;padding:4px'>
							<p style='font-size:13px;color:#4682b4;margin-bottom:5px'>From $from for $bname</p>
							<table style='width:100%' class='table-bordered' style='color:#2f4f4f;font-size:12px' cellpadding='2'>
								<tr style='font-size:11px;font-weight:bold;color:#191970;background:#e6e6fa'><td colspan='2'>Item</td>
								<td>Qty</td><td>Cost</td></tr> $trs
							</table>
						</div>
						<div style='color:$colors2[$state]'>
							<p style='font-weight:bold;margin:7px 0px;border-bottom:1px solid $colors2[$state];padding-bottom:5px'><i class='fa fa-gift'></i> $sno 
							<span style='float:right;font-size:14px'>".date('d-m-Y, h:i a',$tym)."</span></p> <p style='font-size:14px'>$status[$state]</p>
							<p style='text-align:right'><span style='font-weight:bold;float:left'>KES $show</span> $btx</p>
						</div>
					</div>
				</div>";
			}
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='applyrequisition'");
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='requisitionby'");
		$set = ($qri) ? $qri[0]['value']:1; $sv=($qry) ? json_decode($qry[0]['value'],1):[$me['position']]; 
		$by = (in_array($me['position'],$sv) or $me['position']=="super user") ? 1:0;
		
		$opts="<option value='0'>-- Select --</option>";
		foreach(array(2=>"Pending",3=>"Approved",4=>"Declined") as $key=>$typ){
			$cnd=($key==$ftc) ? "selected":"";
			$opts.="<option value='$key' $cnd>$typ</option>";
		}
		
		if(in_array($access,["hq","region"])){
			foreach($brans as $bid=>$name){
				$cnd=($bran==$bid) ? "selected":"";
				$brns.="<option value='$bid' $cnd>$name</option>";
			}
			
			$bin = "<select style='padding:5px;width:140px;' onchange=\"loadpage('accounts/requisition.php?view=$ftc&mon=$mon&bran='+this.value)\">$brns</select>";
		}
		
		$apply = (in_array("create requisition",$perms) && $set && $by) ? "<button class='bts' onclick=\"loadpage('accounts/requisition.php?reqid')\" 
		style='padding:5px;font-size:14px;float:right;margin-left:7px'><i class='fa fa-plus'></i> Create</button>":"";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:10px 5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970;padding-bottom:10px'>$backbtn Requisitions $apply</h3>
				<p><select style='padding:5px;width:110px;' onchange=\"loadpage('accounts/requisition.php?view='+this.value)\">$opts</select>
				<select style='padding:5px;width:110px;' onchange=\"loadpage('accounts/requisition.php?view=$ftc&mon='+this.value)\">$mons</select>
				</p><hr>
				<div class='row'> $divs </div>
			</div>
		</div>";
		
		savelog($sid,"Viewed requisitions");
	}
	
	# create/edit requisition
	if(isset($_GET['reqid'])){
		$rid = trim($_GET['reqid']);
		$nxt = (isset($_GET['approve'])) ? trim($_GET['approve']):0;
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles'])); 
		$access=$me['access_level']; $rtbl="requisitions$cid";
		$cnf = json_decode($me["config"],1);
		
		$exclude = array("id","staff","branch","approvals","approved","month","status","time","item_description","cost");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$rtbl'");
		$def = array("id"=>"number","staff"=>"number","branch"=>"number","item_description"=>"textarea","cost"=>"number","approvals"=>"textarea",
		"approved"=>"text","month"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$defv = array("id"=>$rid,"month"=>strtotime(date('Y-M')),"status"=>0,"time"=>time(),"staff"=>$sid,"approved"=>0);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(array_key_exists($fld,$defv)) ? $defv[$fld]:""; 
		}
	
		if($rid){
			$row = $db->query(3,"SELECT *FROM `$rtbl` WHERE `id`='$rid'"); $dvals=$row[0];
		}
		
		$state = $dvals['status']; $lis=[]; $his=$trs=$rts="";
		$cond = ($nxt>0 && in_array("approve requisitions",$perms)) ? 1:0;
		$view = ($state==0 && $nxt==0) ? 0:1;
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); $add=($view) ? "disabled":"";
				if($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts=$drp="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":""; 
						if($cnd){ $drp=prepare(ucwords($drop)); }
						$opts.="<option value='$drop'>".prepare(ucwords($drop))."</option>";
					}
					$lis[]= ($view) ? "<p><i>$fname</i><br>$drp</p>":"<p>$fname<br><select name='$field' style='padding:7px' $add>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col]));
						$opts.="<option value='$val'></option>";
					}
					
					$ran = rand(12345678,87654321);
					$lis[] = ($view) ? "<p><i>$fname</i><br>".prepare($dvals[$field])."</p>":
					"<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='padding:5px' name='$field' list='$ran' autocomplete='off' value='".prepare($dvals[$field])."' $add required></p>";
				}
				elseif($dtype=="textarea"){
					$lis[] = ($view) ? "<p><i>$fname</i><br>".prepare($dvals[$field])."</p>":
					"<p>$fname<br><textarea class='mssg' name='$field' $add required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{ 
					$val = prepare(ucfirst($dvals[$field]));
					$lis[] = ($view) ? "<p><i>$fname</i><br>$val</p>":"<p>$fname<br><input type='$dtype' style='padding:5px' value=\"$val\" name='$field' $add required></p>";
				}
			}
			else{ $his.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		if(count($lis)){
			$half = ceil(count($lis)/2); $chunk = array_chunk($lis,$half);
			$trs=$rts=(count($lis)>1) ? "<tr valign='top'><td style='width:50%'>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>":
			"<tr valign='top'><td>".implode("",$lis)."</td></tr>";
		}
		
		if(in_array($access,["hq","region"])){
			$brns = "<option value='0'>Corporate</option>"; $mbr=($dvals['branch']) ? $dvals['branch']:$me['branch'];
			$lod = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $lod");
			if($res){
				foreach($res as $row){
					$bid=$row['id']; $cnd = ($bid==$mbr) ? "selected":"";
					$brns.="<option value='$bid' $cnd>".prepare(ucwords($row['branch']))." branch</option>";
				}
			}
			$brans = "<select style='width:180px;padding:5px' name='rbran'>$brns</select>";
		}
		else{ $brans = "<input type='hidden' name='rbran' value='".$me['branch']."'>"; }
		
		$twd = (count($lis)>1) ? "100%":""; $opts=$acc="";
		foreach(array("cash"=>"Cash Item","cashless"=>"Cashless Item") as $key=>$txt){
			$opts.="<option value='$key'>$txt</option>";
		}
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND `type`='expense' ORDER BY `account` ASC");
		foreach($qri as $row){ $accs[$row['id']]=prepare(ucfirst($row['account'])); }
		
		$otpr = ($view==0 or ($state<10 && $cond)) ? "<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' 
		style='border:0px;padding:4px;width:80px;outline:none' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
		<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>":"";
		
		if($view==0){
			if($rid){
				$desc = json_decode($dvals['item_description'],1); $acc=(isset($desc["book"])) ? $desc["book"]:0; unset($desc["book"]); $ltrs="";
				$btx = (in_array("edit requisitions",$perms)) ? "<tr><td>$otpr</td><td style='text-align:right;width:100px'><button class='btnn' 
				style='min-width:60px;padding:5px'><i class='fa fa-refresh'></i> Update</button></td></tr>":"";
				
				foreach($desc as $no=>$des){
					$item=prepare(ucfirst($des['item'])); $qty=$des['qty']; $cat=$des['type']; $cost=$des['cost']; 
					$wd = ($no) ? "80px":"100%"; $ran=rand(12345678,87654321); $opt="";
					$add = ($cat=="cashless") ? "readonly":""; $css=($cat=="cash") ? "outline:none":"outline:none;cursor:not-allowed";
					$del = ($no) ? "<i class='fa fa-times' style='font-size:20px;cursor:pointer;float:right;color:#ff4500' onclick=\"$('#$ran').remove()\" 
					title='Remove $item'></i>":"";
					
					foreach(array("cash"=>"Cash Item","cashless"=>"Cashless Item") as $key=>$txt){
						$cnd = ($key==$cat) ? "selected":"";
						$opt.="<option value='$key' $cnd>$txt</option>";
					}
		
					$ltrs.="<tr id='$ran'><td><input type='text' name='item[]' value=\"$item\" style='width:100%;border:0px;padding:2px;outline:none' required></td>
					<td style='width:120px'><select style='width:120px;border:0px;padding:2px;font-size:15px' onchange=\"getqnty('c$ran',this.value)\" name='categ[]'>$opt</select></td>
					<td style='width:80px'><input type='number' name='qnty[]' value='$qty' style='width:100%;border:0px;padding:2px;outline:none' required></td>
					<td style='width:110px'><input type='number' id='c$ran' value='$cost' name='prices[]' style='width:$wd;border:0px;padding:2px;$css' $add required> $del</td></tr>";
				}
			}
			else{
				$btx = (in_array("create requisition",$perms)) ? "<tr><td>$otpr</td><td style='text-align:right;width:100px'><button class='btnn' 
				style='min-width:60px;padding:5px'><i class='fa fa-gift'></i> Request</button></td></tr>":"";
				
				$ltrs = "<tr><td><input type='text' name='item[]' style='width:100%;border:0px;padding:2px;outline:none' required></td>
				<td style='width:120px'><select style='width:120px;border:0px;padding:2px;font-size:15px' onchange=\"getqnty('c1234',this.value)\" name='categ[]'>$opts</select></td>
				<td style='width:80px'><input type='number' name='qnty[]' style='width:100%;border:0px;padding:2px;outline:none' required></td>
				<td style='width:110px'><input type='number' id='c1234' name='prices[]' style='width:100%;border:0px;padding:2px;outline:none' required></td></tr>";
			}
			
			$bks = "<option value='0'>-- Expense Account --</option>";
			foreach($accs as $key=>$name){ 
				$cnd = ($key==$acc) ? "selected":""; $bks.= "<option value='$key' $cnd>$name</option>";
			}
			
			$data = "<form method='post' id='rfom' onsubmit=\"savereqn(event,'$rid')\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($fields),1)."'> $his
				<table style='width:$twd' cellpadding='5'>$trs</table>
				<table style='width:100%;font-size:15px' cellpadding='4' class='table-bordered mtbl'>
					<caption>$brans <select name='expbook' id='exbk' style='max-width:200px;width:100%;padding:5px 8px' required>$bks</select>
					<a href='javascript:void(0)' style='float:right' onclick='addrow()'><i class='fa fa-plus'></i> Add Row</a></caption>
					<tr style='color:#191970;color:#191970;font-weight:bold;background:#f0f0f0'><td>Item description</td>
					<td>Category</td><td>Qnty</td><td>Unit Cost</td></tr> $ltrs
				</table>
				<table style='width:100%'> $btx </table><br>
			</form>";
		}
		else{
			$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='requisition'");
			$app = ($qry) ? json_decode($qry[0]['levels'],1):[]; $all=count($app); $rem=0;
			$css = "style='background:#f8f8ff;color:#4682b4'"; $trh="<td $css><b>Request</b></td>";
			
			$desc = json_decode($dvals['item_description'],1); $acc=(isset($desc["book"])) ? $desc["book"]:0; unset($desc["book"]);
			$appr = json_decode($dvals['approvals'],1); $trs=""; $no=0; $cles=[];
			foreach($desc as $key=>$des){
				$item=prepare(ucfirst($des['item'])); $qty=$des['qty']; $cat=$des['type']; $cost=$des['cost']; $no++; $tqn=$tcst="";
				if($cat=="cashless"){ $cles[]=$key; }
				
				if($all){
					if(is_array($appr)){
						$arr = (isset($appr[$key])) ? $appr[$key]:[]; $rem = ($state>15) ? count($arr):$all; $prv=0;
						$val = ($cat=="cashless") ? "---":number_format($cost);
						$tqn = "<td>$qty</td>"; $tcst = "<td>$val</td>";
						
						for($i=0; $i<$rem; $i++){ 
							if(array_key_exists($i,array_keys($arr))){
								$svd = array_values($arr)[$i]; $tapp=$prv= $svd[0]*$svd[1]; $cost=($tapp) ? $svd[1]:0;
								$qvl = ($svd[0]) ? $svd[0]:"<i style='color:#DC143C'>Declined</i>";
								$val = ($cat=="cashless") ? "---":number_format($cost);
								$tqn .= "<td>$qvl</td>"; $tcst .= "<td>$val</td>";
							}
							else{
								$tapp = ($prv) ? $prv:0;
								$trx = ($state<10) ? "<i style='color:#9400D3'>pending</i>":"<i style='color:#DC143C'>Declined</i>";
								$in1 = (($i+1)==$nxt && $state<10) ? "<input type='number' name='qnty[$key]' value='$qty' style='width:55px;padding:0px 3px' required>":$trx;
								$in2 = (($i+1)==$nxt && $state<10) ? "<input type='number' name='price[$key]' value='$cost' style='width:75px;padding:0px 3px' required>":$trx;
								$qvl = ($i==$nxt) ? $qty:$in1;
								$cvl = ($i==$nxt) ? number_format($cost):$in2;
								$val = ($cat=="cashless") ? "---":$cvl; 
								$tqn .= "<td>$qvl</td>"; $tcst .= "<td>$val</td>";
							}
						}
					}
					else{
						$val = ($cat=="cashless") ? "---":number_format($cost);
						$tqn = "<td>$qty</td>"; $tcst = "<td>$val</td>"; $tapp = 0; 
						for($i=1; $i<=$all; $i++){
							$trx = ($state<10) ? "<i style='color:#9400D3'>pending</i>":"<i style='color:#DC143C'>Declined</i>";
							$in1 = ($i==$nxt && $state<10) ? "<input type='number' name='qnty[$key]' value='$qty' style='width:55px;padding:0px 3px' required>":$trx;
							$in2 = ($i==$nxt && $state<10) ? "<input type='number' name='price[$key]' value='$cost' style='width:75px;padding:0px 3px' required>":$trx;
							$val = ($cat=="cashless") ? "---":$in2;
							$tqn .= ($state>10 && $i>1) ? "<td>---</td>":"<td>$in1</td>"; 
							$tcst .= ($state>10 && $i>1) ? "<td>---</td>":"<td>$val</td>";
						}
					}
				}
				else{
					$val = ($cat=="cashless") ? "---":number_format($cost);
					$tqn = "<td>$qty</td><td>$qty</td>"; 
					$tcst = "<td>$val</td><td>$val</td>";
					$tapp = $cost*$qty; $col1=$col2=2;
				}
				$trs.= "<tr valign='top'><td style='width:35px'>$no</td><td>$item</td> $tqn $tcst <td style='text-align:right'><b>".number_format($tapp)."</b></td></tr>";
			}
			
			$all = ($state>10 && $rem>0) ? $rem:$all;
			if($all){
				$col1=$col2=$all+1;
				foreach($app as $key=>$one){
					$trh.="<td $css><b>".ucfirst($one)."</b></td>";
					if($key==$all){ break; }
				}
			}
			else{ $trh="<td $css><b>Request</b></td><td $css><b>Accepted</b></td>"; $col1=$col2=2; }
			
			$user = staffInfo($dvals['staff']); $from = prepare(ucwords($user['name'])); $cols=2+$col1+$col2;
			$btn = ($state<10 && $cond) ? "<button class='btnn' style='min-width:60px;padding:5px'><i class='fa fa-check'></i> Approve</button>":"";
			$btx = ($state<10 && $cond) ? "<button type='reset' onclick=\"cancelreq(event,'$rid')\" class='btnn' style='min-width:60px;padding:5px;background:#DC143C'>
			<i class='fa fa-times'></i> Decline</button>":"";
			
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `id`='".$dvals['branch']."'"); $bname=prepare(ucwords($qri[0]['branch']));
			$txt = ($cond) ? "<span style='font-size:14px'><b>Note:</b> To decline an Item, set quantity to 0</span>":"";
			
			$data = "<table style='width:$twd' cellpadding='5'>$rts</table>
			<p style='margin-bottom:5px'>From <b>$from</b>, <b>$bname</b> on <b>".date("M d,Y - h:i a",$dvals['time'])."</b></p>
			<form method='post' id='rfom' onsubmit='approvereqn(event)'>
				<input type='hidden' value='".json_encode($cles,1)."' name='tcls'> <input type='hidden' name='expbook' value='$acc'>
				<input type='hidden' value='$nxt' name='appreq'> <input type='hidden' value='$rid' name='reqnid'>
				<table style='width:100%;font-size:15px' cellpadding='4' class='table-bordered'>
					<tr style='color:#191970;color:#191970;font-weight:bold;background:#f0f0f0'><td colspan='2'>Item description</td>
					<td colspan='$col1' style='text-align:center'>Quantity Approvals</td><td colspan='$col2' style='text-align:center'>Cost Approvals</td>
					<td style='text-align:right'>Totals</td></tr>
					<tr style='font-size:13px'><td colspan='2'></td>$trh $trh<td></td> $trs
					<tr style='color:#191970;font-weight:bold;background:#f0f0f0'><td colspan='$cols'></td>
					<td style='text-align:right'>".number_format($dvals['approved'])."</td></tr>
				</table><br>
				<table style='width:100%'><tr><td>$txt</td><td>$otpr</td><td style='text-align:right;width:190px'>$btx $btn</td></tr></table>
			</form><br>";
		}
		
		$ttx = ($rid) ? prenum($rid):"Form";
		echo "<div class='cardv' style='max-width:1000px;min-height:400px;overflow:auto;padding:5px'>
			<h3 style='font-size:22px;text-align:center;color:#191970;padding-top:10px'><span style='float:left'>$backbtn</span>
			<u>Internal Requisition $ttx</u></h3><br>
			<div style='min-width:700px;padding:10px'>
				<input type='hidden' id='selecs' value=\"$opts'\"> $data
			</div>
		</div>";
	}

	ob_end_flush();
?>

<script>
	
	function approvereqn(e){
		e.preventDefault();
		if(confirm("Continue to approve the requisition?")){
			var data=$("#rfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/requisition.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success!"); loadpage("accounts/requisition.php?view");
				}
				else{ alert(res); }
			});
		}
	}
	
	function cancelreq(e,rid){
		e.preventDefault();
		var otp = _("otp").value.trim();
		
		if(otp==""){
			toast("Enter OTP code first!"); _("otp").focus();
		}
		else{
			if(confirm("Sure to decline the requisition?")){
				$.ajax({
					method:"post",url:path()+"dbsave/requisition.php",data:{declinereqn:rid,otpv:otp},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success!"); loadpage("accounts/requisition.php?view");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function savereqn(e,rid){
		e.preventDefault();
		var btx = (rid>0) ? "update requisition?":"create new requisition?";
		if(confirm("Continue to "+btx)){
			var data=$("#rfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/requisition.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Success!"); loadpage("accounts/requisition.php?reqid="+res.trim().split(":")[1]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function addrow(){
		var did=Date.now(),lis=$("#selecs").val();
		$(".mtbl").append("<tr id='"+did+"'><td><input type='text' name='item[]' style='width:100%;border:0px;padding:2px;outline:none' required></td>"+
		"<td style='width:120px'><select style='width:120px;border:0px;padding:2px;font-size:15px' onchange=\"getqnty('c"+did+"',this.value)\" name='categ[]'>"+lis+"</select></td>"+
		"<td style='width:80px'><input type='number' name='qnty[]' style='width:100%;border:0px;padding:2px;outline:none' required></td>"+
		"<td style='width:110px'><input type='number' id='c"+did+"' name='prices[]' style='width:80px;border:0px;padding:2px;outline:none' required>"+
		"<i class='fa fa-times' style='font-size:20px;cursor:pointer;float:right;color:#ff4500' onclick=\"$('#"+did+"').remove()\" title='Remove row'></i></td></tr>");
	}
	
	function getqnty(id,tp){
		if(tp=="cash"){
			$("#"+id).val(""); $("#"+id).prop("readonly",false); $("#"+id).css("cursor","text");
		}
		else{ 
			$("#"+id).val(0); $("#"+id).prop("readonly",true); $("#"+id).css("cursor","not-allowed");
		}
	}
	
</script>