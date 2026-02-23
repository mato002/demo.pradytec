<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# settings
	if(isset($_GET['settings'])){
		$def = array("NSSF"=>["0-500000"=>360],"NHIF"=>["0-500000"=>"2.75%"],"Housing Levy"=>["0-500000"=>"2%"],"NITA"=>["0-500000"=>"0"]);
		$sql = $db->query(1,"SELECT *FROM `paycuts` WHERE `client`='$cid' AND `deduction`='statutory'");
		$data = ($sql) ? json_decode($sql[0]['cut'],1):$def; $tbds="";
		if(!isset($data["NITA"])){ $data["NITA"]=["0-500000"=>"0"]; }
		
		foreach($data as $key=>$one){
			$name = ($key=="NHIF") ? "SHIF":prepare(ucfirst($key)); $trs=""; $no=0;
			$del = (!in_array($key,["NHIF","NSSF","Housing Levy","NITA"])) ? "<a href='javascript:void(0)' style='float:right;color:#ff4500;font-size:16px' onclick=\"delcut('$key')\">
			<i class='bi-node-minus'></i> Remove</a>":"";
			
			foreach($one as $range=>$amnt){
				$rn1 = explode("-",$range)[0]; $rn2=explode("-",$range)[1]; $ran=rand(123456,7654321); $no++;
				$rem = ($no>1) ? "<a href='javascript:void(0)' style='float:right;color:#ff4500' onclick=\"delrow('r$ran')\">Remove</a>":"";
				
				$trs.= "<tr id='r$ran' valign='top'><td><input type='number' name='mins[$key][]' style='width:100%;padding:5px' value='$rn1' required></td>
				<td style='width:32%'><input type='number' name='maxs[$key][]' style='width:100%;padding:5px' value='$rn2' required></td>
				<td style='width:32%'><input type='text' id='$ran' name='sums[$key][]' onkeyup=\"valnum('$ran',this.value)\" style='width:100%;padding:5px' value='$amnt' required> $rem</td></tr>";
			}
			
			$tbds.= "<h3 style='font-weight:bold;padding:8px;font-size:18px;background:#f0f0f0;margin-bottom:10px;color:#7B68EE'>
			<span style='cursor:pointer;'><i class='bi-list-stars'></i> $name</span> $del</h3>
			<div style='padding:5px;background:#f8f8ff;border:1px solid #f0f0f0;margin-bottom:10px;margin-top:-10px;overflow:auto'>
				<table style='width:100%;' cellpadding='5' class='".str_replace(" ","-",$name)."'>
					<caption style='margin:0px;padding:0px;text-align:right'>
						<a href='javascript:void(0)' onclick=\"addcutrow('$name')\"><i class='bi-plus-circle'></i> Range</a>
					</caption>
					<tr style='font-weight:bold;color:#191970'><td>Minimum (Ksh)</td><td>Maximum (Ksh)</td><td>Amount</td></tr>$trs
				</table>
			</div>";
		}
		
		echo "<div class='container cardv' style='overflow:auto;min-height:300px;max-width:900px;'>
		<h3 style='font-size:22px;color:#191970'>$backbtn Statutory Deductions</h3><hr>
			<form method='post' id='sform' onsubmit='savepaysett(event)'>
				<div class='cuts' style='padding:10px' id='cutsdv'> $tbds </div>
				<p style='text-align:right'><button class='btnn' style='margin-right:10px;background:#008080' type='reset' onclick=\"addcut()\">
				<i class='bi-plus-circle'></i> Add New</button><button class='btnn'><i class='bi-check2-circle'></i> Save</button></p>
			</form>
		</div><script> $('#cutsdv').accordion({heightStyle: 'content'}); </script>";
		savelog($sid,"Accessed Statutory Settings");
	}
	
	# deductions
	if(isset($_GET['deductions'])){
		$ftc = trim($_GET['deductions']);
		$vtp = ($ftc==null) ? 20:$ftc; 
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y"); $mtn=($yr<date("Y")) ? "Dec":"Jan";
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M")); 
		$mon = (date("Y")!=$yr && !isset($_GET['mon'])) ? strtotime("$yr-$mtn"):$mon;
		$tmon = strtotime(date("Y-M")); $tyr=date("Y");
		
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		$cond = ($vtp==20) ? "AND `status`>200":"AND `status`='$vtp'";
		if(!$db->istable(1,"standing_orders")){
			$db->createTbl(1,"standing_orders",["client"=>"INT","source"=>"CHAR","type"=>"CHAR","amount"=>"CHAR","details"=>"TEXT","status"=>"INT","time"=>"INT"]);
		}
		
		$yrs = "<option value='$tyr'>$tyr</option>";
		$res = $db->query(3,"SELECT DISTINCT `year` FROM `deductions$cid` WHERE NOT `year`='$tyr' AND `status`>10 ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
				$yrs.= "<option value='$year' $cnd>$year</option>";
			}
		}
		
		$mons = (date("Y")!=$yr) ? "":"<option value='$tmon'>".date('M Y')."</option>";
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `deductions$cid` WHERE `year`='$yr' AND NOT `month`='$tmon' AND `status`>10 ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":"";
				$mons.= "<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']]=array("status"=>$row['status'],"name"=>prepare(ucwords($row['name'])),"idno"=>$row['idno']);
			}
		}
		
		$trs=$opts=""; $no=0;
		$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `month`='$mon' $cond");
		if($res){
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
			$posted = ($qri) ? json_decode($qri[0]["value"],1):[];
			foreach($res as $row){
				$name=$names[$row['staff']]['name']; $cat=prepare($row['category']); $st=$row['status'];
				$amnt=fnum($row['amount']); $rid=$row['id']; $act=$tds=""; $no++;
				
				if(in_array("manage salary deductions",$perms)){
					if($st>200 && !isset($posted[$mon])){
						$act = "<a href='javascript:void(0)' onclick=\"popupload('accounts/payroll.php?deduct=$rid')\"><i class='fa fa-pencil'></i> Edit</a>
						&nbsp; | &nbsp; <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delrecord('$rid')\"><i class='fa fa-times'></i> Remove</a>";
					}
					else{
						$act = ($st==200) ? "<a href='javascript:void(0)' onclick=\"popupload('accounts/payroll.php?confdisb=$rid')\">
						<i class='fa fa-check'></i> Confirm</a>":"";
					}
				}
				else{ $act = "N/A"; }
				
				$day =  ($st==20) ? date("M d, h:i a",$row['status']):date("M d, h:i a",$row['time']);
				$trs.= "<tr id='tr$rid'><td style='width:30px'>$no</td><td>$name</td><td>$day</td><td>$cat</td><td>$amnt</td><td>$act</td></tr>";
			}
		}
		
		$defs = array(22=>"Standing Orders",20=>"Effected",200=>"Not Effected");
		foreach($defs as $key=>$one){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$one</option>";
		}
		
		echo "<div class='cardv' style='overflow:auto;min-height:300px;max-width:900px'>
		<h3 style='font-size:22px;color:#191970'>$backbtn ".date('F Y',$mon)." Payroll Deductions</h3>
			<table style='width:100%;font-size:15px;min-width:500px' class='table-striped' cellpadding='5'>
				<caption style='caption-side:top'> 
					<button style='float:right;padding:3px;font-size:14px' class='bts' onclick=\"popupload('accounts/payroll.php?deduct')\">
					<i class='fa fa-plus'></i> Record</button>
					<select style='width:80px;font-size:15px' onchange=\"loadpage('accounts/payroll.php?deductions&year='+this.value)\">$yrs</select>
					<select style='width:120px;font-size:15px' onchange=\"loadpage('accounts/payroll.php?deductions&year=$yr&mon='+this.value)\">$mons</select>
					<select style='width:150px;font-size:15px' onchange=\"loadpage('accounts/payroll.php?year=$yr&mon=$mon&deductions='+this.value)\">$opts</select>
				</caption>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA;cursor:default' valign='top'><td colspan='2'>Staff</td>
				<td>Date</td><td>Deduction</td><td colspan='2'>Amount</td></tr> $trs
			</table>
		</div>";
		savelog($sid,"Viewed $defs[$vtp] Payroll deductions for ".date("F Y",$mon));
	}
	
	# confirm disbursed advances
	if(isset($_GET['confdisb'])){
		$did = trim($_GET['confdisb']); $opts="";
		foreach(array(time()=>"Advance was disbursed",11=>"Failed to Disburse") as $key=>$val){
			$opts.="<option value='$key'>$val</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Confirm Deduction</h3><br>
			<form method='post' id='cform' onsubmit=\"confdisb(event,'$did')\">
				<input type='hidden' name='cdid' value='$did'>
				<p>Select Action<br><select name='distate' style='width:100%'>$opts</select></p>
				<p style='text-align:right'><button class='btnn'>Confirm</button></p><br>
			</form>
		</div>";
	}
	
	# deduct record
	if(isset($_GET['deduct'])){
		$rid = trim($_GET['deduct']);
		$dtp = (isset($_GET['dtp'])) ? trim($_GET['dtp']):"advance";
		$stid=$cat=$amnt=$deds=""; $mon=date("Y-m"); $loans=[];
		$opts = (in_array($dtp,["advance","loan"])) ? "":"<option value='0'>All Staff</option>";
		
		if($rid){
			$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `id`='$rid'");
			$stid = $res[0]['staff']; $cat=$res[0]['category']; $amnt=$res[0]['amount']; $mon=date("Y-m",$res[0]['month']);
			$ltp = (!isset($_GET['dtp']) && $cat!="Salary advance") ? "other":$dtp; $opts="";
			$dtp = (!isset($_GET['dtp']) && $cat=="Salary advance") ? "advance":$ltp; 
			$dtp = (!isset($_GET['dtp']) && trim(substr($cat,0,11))=="Staff Loan") ? "loan":$dtp; 
		}
		
		if($dtp=="loan"){
			$qri = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE (penalty+balance)>0");
			if($qri){
				foreach($qri as $row){ $loans[$row["stid"]]=[$row["loan"],$row["penalty"]+$row["balance"]]; }
			}
		}
		
		$cond = ($stid) ? "AND `id`='$stid'":"";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='15' $cond ORDER BY `name` ASC");
		foreach($res as $row){
			$def = json_decode($row['config'],1);
			if($dtp=="loan"){
				$id=$row['id']; $lca=(isset($def["lca"])) ? $def["lca"]:0; $name=prepare(ucwords($row['name']));
				if(isset($loans[$id])){ $lid=$loans[$id][0]; $opts.= "<option value='$id:$lid'>$name (Staff Loan ".fnum($loans[$id][1]).")</option>"; }
				if($lca){
					$idno = $db->query(2,"SELECT `idno` FROM `org$cid"."_clients` WHERE `id`='$lca'")[0]["idno"];
					$chk = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0");
					if($chk){
						foreach($chk as $rw){
							$lid = $rw["loan"]; $bal=$rw["balance"]+$rw["penalty"];
							$opts.= "<option value='$id:$lid'>$name (Client Loan ".fnum($bal).")</option>"; 
						}
					}
				}
			}
			else{
				if(!in_array($row["position"],["assistant","USSD"])){
					$pay = $def['payroll'];
					if($pay['include']){
						$id=$row['id']; $name=prepare(ucwords($row['name'])); $cnd=($id==$stid) ? "selected":"";
						if($stid){
							$opts.=($row['id']==$stid) ? "<option value='$id' $cnd>$name</option>":"";
						}
						else{ $opts.=($row['status']!=1) ? "<option value='$id' $cnd>$name</option>":""; }
					}
				}
			}
		}
		
		$list = ($db->istable(2,"staff_loans$cid")) ? array("advance"=>"Salary Advance","loan"=>"Staff Loan","other"=>"Other Deductions"):
		["advance"=>"Salary Advance","other"=>"Other Deductions"];
		
		foreach($list as $key=>$txt){
			$cnd = ($key==$dtp) ? "selected":"";
			$deds.= "<option value='$key' $cnd>$txt</option>";
		}
		
		$dtx = ($dtp=="advance") ? "Salary advance":"Staff Loan";
		$add = (in_array($dtp,["advance","loan"])) ? "<input type='hidden' name='dedtp' value='$dtx'>":
		"<p>Name of Deduction<br><input type='text' name='dedtp' value=\"$cat\" style='width:100%' required></p>";
		$dsb = (in_array($dtp,["advance","loan"])) ? "disabled":"";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Deduction Record</h3><br>
			<form method='post' id='dform' onsubmit=\"savededuct(event,'$rid')\">
				<input type='hidden' name='did' value='$rid'>
				<p>Deduction Type<br><select name='dedcat' style='width:100%' 
				onchange=\"popupload('accounts/payroll.php?deduct=$rid&dtp='+this.value)\">$deds</select></p> $add
				<p>Select Employee<br><select style='width:100%' name='staff' required>$opts</select></p>
				<p>Amount<br><input type='number' name='dedamnt' value='$amnt' style='width:100%' required></p>
				<p>Month<br><input type='month' style='width:100%;padding:5px;border:1px solid #ccc;outline:none' value='$mon' name='dedmon' required></p>
				<p><input type='checkbox' name='recur' $dsb> &nbsp; Recurring for Upcoming Months</p><br>
				<p style='text-align:right'><button class='btnn'>Save</button></p><br>
			</form>
		</div>";
	}
	
	# payslips
	if(isset($_GET['slips'])){
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):0;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$tmon = strtotime(date("Y-M")); $tyr=date("Y"); 
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		$yrs=$trs=$mons=""; $all=$mns=$done=[];
		
		$cols = $db->tableFields(3,"payslips$cid");
		if(!in_array("bankcode",$cols)){
			$db->insert(3,"ALTER TABLE `payslips$cid` ADD `bankcode` VARCHAR(255) NOT NULL AFTER `bank`, ADD `branch` VARCHAR(255) NOT NULL AFTER `bankcode`");
		}
		
		$res = $db->query(3,"SELECT DISTINCT `year` FROM `payslips$cid` ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":""; $all[]=$year;
				$yrs.= "<option value='$year' $cnd>$year</option>";
			}
			$yr = ($yr) ? $yr:max($all);
		}
		else{ $yrs = "<option value='$tyr'>$tyr</option>"; }
		
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `payslips$cid` WHERE `year`='$yr' ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":""; $mns[]=$mn;
				$mons.= "<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
			$mon = ($mon) ? $mon:max($mns);
		}
		else{ $mons = "<option value='$tmon'>".date('M Y')."</option>"; }
		
		if($db->istable(3,"paid_salaries$cid")){
			$sql = $db->query(3,"SELECT *FROM `paid_salaries$cid` WHERE `month`='$mon' AND `status`>200");
			if($sql){
				foreach($sql as $row){ $done[]=$row['staff']; }
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']]=prepare(ucwords($row['name']));
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='updatepayroll'");
		$upd = ($res) ? $res[0]['value']:2; $dif=date("m",$tmon)-date("m",$mon); $ups=0;
		
		if($upd==4){ $ups = ($dif<=6) ? 1:0; }
		elseif($upd==5){ $ups = 1; }
		else{ $ups = ($dif<=$upd) ? 1:0; }
		
		$res = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon' ORDER BY staff ASC"); $no=$uds=0;
		if($res){
			$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
			foreach($qri as $row){ $deds[$row['staff']]=$row['deductions']; $cuts[$row['staff']]=json_decode($row['cuts'],1); }
			foreach($res as $row){
				$stid=$row['staff']; $name=$names[$stid]; $cut=(is_array($cuts[$stid])) ? array_sum($cuts[$stid]):intval($cuts[$stid]);
				$bank=prepare(ucwords($row['bank'])); $bnkc=prepare($row['bankcode']); $acc=$row['account']; $chq=$row['cheque']; $no++;
				$amnt=fnum($row['amount']); $ded=fnum(intval($deds[$stid])+$cut); $pay=fnum($row['amount']+intval($deds[$stid])); $rid=$row['id']; 
				$brnc=prepare($row['branch']); $uds+=(in_array($stid,$done)) ? 0:1;
				
				$edit = (in_array("manage payroll",$perms) && $ups) ? "<i class='bi-pencil-square' title='Edit Record' onclick=\"popupload('accounts/payroll.php?updbank=$rid')\"
				style='font-size:19px;color:#191970;cursor:pointer;margin-right:10px'></i>":"";
				
				$trs.= "<tr><td>$no</td><td>$name</td><td>$bank</td><td>$bnkc</td><td>$brnc</td><td>$acc</td><td>$chq</td><td>$pay</td><td>$ded</td><td>$amnt</td>
				<td>$edit <i class='fa fa-envelope-o' style='font-size:20px;cursor:pointer;color:#008080;' title='Send email to $name' onclick=\"mailslip('$mon','$rid')\"></i>
				<i class='fa fa-print' style='font-size:20px;cursor:pointer;color:#008fff;margin-left:10px' title='Print Payslip' onclick=\"printslip('$mon','$rid')\"></i></td></tr>";
			}
		}
		
		$prnt = ($trs) ? genrepDiv("slips.php?mon=$mon",'right'):"";
		$send = "<button class='bts' style='float:right;font-size:14px;padding:2px;margin-left:7px' onclick=\"popupload('accounts/payroll.php?sendmail=$mon')\">
		<i class='fa fa-envelope'></i> Send Emails</button>";
		$disb = (in_array("disburse staff salary",$perms) && $uds) ? "<button class='bts' style='float:right;padding:2px;font-size:14px;margin-left:7px' 
		onclick=\"popupload('accounts/payroll.php?disbs=$mon')\"><i class='bi-cash-coin'></i> Disburse</button>":"";
			
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:1300px'>
		<h3 style='font-size:22px;color:#191970'>$backbtn ".date('F Y',$mon)." payslips</h3>
			<table style='width:100%;font-size:14px;min-width:700px' class='table-striped' cellpadding='5'>
				<caption style='caption-side:top'>$disb $send $prnt
					<select style='width:80px;font-size:14px' onchange=\"loadpage('accounts/payroll.php?slips&yr='+this.value)\">$yrs</select>
					<select style='width:120px;font-size:14px' onchange=\"loadpage('accounts/payroll.php?slips&yr=$yr&mon='+this.value)\">$mons</select>
				</caption>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA;cursor:default' valign='top'><td colspan='2'>Staff</td><td>Bank Name</td>
				<td>Bank Code</td><td>Branch Name/Code</td><td>Account No</td><td>Cheque No</td><td>Salary</td><td>Deductions</td><td colspan='2'>Net Pay</td></tr> $trs
			</table>
		</div>";
		
		savelog($sid,"Accessed Staff payslips for ".date("F Y",$mon));
	}
	
	# disburse salary
	if(isset($_GET['disbs'])){
		$mon = trim($_GET['disbs']); $done=[]; $trs=$opts=$als=""; $no=0;
		$mod = (isset($_GET["mode"])) ? trim($_GET["mode"]):"b2c";
		if(!$db->istable(3,"paid_salaries$cid")){
			$db->createTbl(3,"paid_salaries$cid",["staff"=>"INT","amount"=>"INT","method"=>"CHAR","account"=>"CHAR","month"=>"INT","status"=>"INT","time"=>"INT"]);
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']]=prepare(ucwords($row['name'])); $conts[$row['id']]=$row['contact'];
			}
		}
		
		$sql = $db->query(3,"SELECT *FROM `paid_salaries$cid` WHERE `month`='$mon' AND `status`>200");
		if($sql){
			foreach($sql as $row){ $done[]=$row['staff']; }
		}
		
		if($mod=="accs"){
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`wing` LIKE '2,6%' OR `wing` LIKE '2,7%') ORDER BY `account` ASC");
			if($sql){
				foreach($sql as $row){
					$rid = $row['id']; $acc=prepare(ucfirst($row['account']));
					$als.= (!in_array($rid,[14,15,22,23])) ? "<option value='$rid'>$acc</option>":"";
				}
			}
		}
		
		$res = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon'");
		foreach($res as $row){
			$stid = $row['staff']; $name=$names[$stid]; $acc=prepare($row["account"]);
			$fon = ($row["bank"]=="Safaricom MPESA" && $row["bankcode"]=="MPESAKE") ? intval($acc):$conts[$stid]; 
			$amnt = $row['amount']; $sum=fnum($amnt); $disb=(in_array($stid,$done)) ? 0:1; $id=$row['id']; $no++;
			$tdx = ($mod=="b2b") ? "<td><input type='number' style='padding:3px;font-size:15px;width:90px' name='payto[$id]'></td><td>
			<input type='text' style='padding:3px;font-size:15px;width:140px' name='payacc[$id]' value='$acc'></td>":"<td>0$fon</td>";
			$tdx = ($mod=="accs") ? "<td><select style='width:150px;font-size:15px;padding:4px' name='srcacc[$id]'>$als</select></td>
			<td><input type='text' name='refs[$id]' style='padding:2px 5px;font-size:15px;width:120px'></td>":$tdx;
			$trs.= ($amnt>0 && $disb) ? "<tr><td>$no. $name</td>$tdx<td>$sum</td><td><input type='checkbox' name='saloto[]' value='254$fon:$id'></td></tr>":"";
		}
		
		foreach(array("b2c"=>"MPESA B2C","b2b"=>"MPESA B2B","accs"=>"Asset Accounts") as $key=>$tx){
			$cnd = ($key==$mod) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$tx</option>";
		}
		
		$tdy = date("Y-m-d")."T".date("H:i",time()-1800); $now=date("Y-m-d")."T".date("H:i");
		$dtin = ($mod=="accs") ? "<input type='datetime-local' style='width:190px;padding:3px;font-size:15px' value='$tdy' max='$now' name='dday' required>":"";
		$txt = ($mod=="b2c") ? "Mpesa No":"Asset Account"; $atd=($mod=="accs") ? "<td>Transaction Ref</td>":"";
		$tcol = ($mod=="b2b") ? "<td>Bank Paybill</td><td>Account No</td>":"<td>$txt</td>$atd"; $csp=($mod=="b2c") ? 2:3;
		$trs.= ($trs) ? "<tr style='background:#fff'><td colspan='2'><br><div style='width:170px;border:1px solid #dcdcdc;height:35px'>
		<input type='text' name='otpv' style='border:0px;padding:4px;width:90px' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' 
		autocomplete='off' required><a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
		<td colspan='$csp' style='text-align:right'><br><button class='btnn' style='padding:6px'>Disburse</button></td></tr>":"<tr><td colspan='4'>No pending disbursements</td></tr>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:600px'>
			<h3 style='text-align:center;font-size:23px;color:#191970'>Disburse Staff Salary</h3><br>
			<form method='post' id='dsfom' onsubmit=\"disbsalo(event,'$mod')\">
				<input type='hidden' name='smon' value='$mon'>
				<div style='width:100%;overflow:auto'>
					<table style='width:100%;' class='table-striped' cellpadding='5'>
						<caption style='caption-side:top'> <select style='width:160px;font-size:15px;padding:6px' name='paycnl'
						onchange=\"popupload('accounts/payroll.php?disbs=$mon&mode='+this.value)\">$opts</select> $dtin</caption>
						<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td>Staff Name</td>$tcol<td colspan='2'>Salary</td></tr> $trs
					</table>
				</div>
			</form><br>
		</div>";
	}
	
	# update bank details
	if(isset($_GET['updbank'])){
		$rid = trim($_GET['updbank']);
		$res = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `id`='$rid'");
		$row = $res[0]; $bank=prepare($row['bank']); $bnkc=prepare($row['bankcode']); $brnc=prepare($row['branch']); $acc=$row['account']; $chq=$row['cheque'];
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;font-size:23px;color:#191970'>Update Bank Details</h3><br>
			<form method='post' id='bform' onsubmit='savebank(event)'>
				<input type='hidden' name='bpid' value='$rid'>
				<p>Bank Name<br><input type='text' style='width:100%' name='paydata[bank]' value=\"$bank\" required></p>
				<p>Bank Code<br><input type='text' style='width:100%' name='paydata[bankcode]' value=\"$bnkc\" required></p>
				<p>Branch Name/Code<br><input type='text' style='width:100%' name='paydata[branch]' value=\"$brnc\" required></p>
				<p>Account No<br><input type='number' style='width:100%' name='paydata[account]' value=\"$acc\" required></p>
				<p>Cheque No<br><input type='text' style='width:100%' name='paydata[cheque]' value=\"$chq\" required></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p><br>
			</form>
		</div>";
	}
	
	# payroll options
	if(isset($_GET['fetch'])){
		$cols = $db->tableFields(3,"payroll$cid");
		if(!in_array("helb",$cols)){
			$db->insert(3,"ALTER TABLE `payroll$cid` ADD `helb` VARCHAR(50) NOT NULL AFTER `nhif`, ADD `nita` VARCHAR(50) NOT NULL AFTER `helb`, ADD `pension` VARCHAR(50) NOT NULL AFTER `nita`");
			$db->insert(3,"UPDATE `payroll$cid` SET `helb`='0',`nita`='0',`pension`='0'");
		}
		
		$sql = $db->query(3,"SELECT MAX(month) AS mxm FROM `payroll$cid`");
		$qri = $db->query(3,"SELECT MAX(month) AS mxm FROM `payslips$cid`");
		$mxp = ($sql) ? intval($sql[0]['mxm']):0; $mxs=($qri) ? intval($qri[0]['mxm']):0;
		
		$tmon = strtotime(date("Y-M"));
		$vpr = ($mxp) ? "loadpage('accounts/payroll.php?viewp=$mxp')":"toast('No Payroll record Found')";
		$vps = ($mxs) ? "loadpage('accounts/payroll.php?slips&mon=$mxs')":"toast('No payslip record Found')";
		
		echo "<div class='cardv' style='max-width:800px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Employee Payroll</h3><hr>
				<div class='row'>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/payroll.php?manage=$tmon')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-calendar2-plus'></i>
						".date("F Y")." Payroll</h3><p style='margin-bottom:0px;font-size:14px'>Setup & Generate Current Month Payroll</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$vpr\"><h3 style='font-size:17px;color:#191970'> <i class='bi-calendar2-range'></i>
						Created Payrolls</h3><p style='margin-bottom:0px;font-size:14px'>View Prepared payroll & deductions for every month</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$vps\"><h3 style='font-size:17px;color:#191970'> <i class='bi-journal-album'></i>
						Payslips</h3><p style='margin-bottom:0px;font-size:14px'>View Payslips generated from Payroll Months</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/payroll.php?settings')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-basket2'></i>
						Statutory Deductions</h3><p style='margin-bottom:0px;font-size:14px'>Review and Configure Statutory deductions e.g. NSSF</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/payroll.php?deductions')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-wallet-fill'></i>
						Other Salary Deductions</h3><p style='margin-bottom:0px;font-size:14px'>Configure other salary deductions e.g. Welfare</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/payroll.php?pbonus')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-gift'></i>
						Bonuses & Allowances</h3><p style='margin-bottom:0px;font-size:14px'>Configure additional Payroll Income e.g. Incetives</p>
						</div>
					</div>
				</div>
			</div>
		</div>";
	}
	
	# bonuses & allowances
	if(isset($_GET['pbonus'])){
		$ftc = trim($_GET['pbonus']);
		$vtp = ($ftc==null) ? 20:$ftc; 
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y"); $mtn=($yr<date("Y")) ? "Dec":"Jan";
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M")); 
		$mon = (date("Y")!=$yr && !isset($_GET['mon'])) ? strtotime("$yr-$mtn"):$mon;
		$tmon = strtotime(date("Y-M")); $tyr=date("Y");
		
		if(!$db->istable(3,"bonuses$cid")){
			$db->createTbl(3,"bonuses$cid",["staff"=>"INT","month"=>"INT","bonus"=>"CHAR","allowance"=>"CHAR","details"=>"TEXT","year"=>"INT","status"=>"INT","time"=>"INT"]);
		}
		
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		$cond = ($vtp==20) ? "AND `status`='200'":"AND `status`='$vtp'";
		
		$yrs = "<option value='$tyr'>$tyr</option>";
		$res = $db->query(3,"SELECT DISTINCT `year` FROM `bonuses$cid` WHERE NOT `year`='$tyr' AND `status`>10 ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
				$yrs.= "<option value='$year' $cnd>$year</option>";
			}
		}
		
		$mons = (date("Y")!=$yr) ? "":"<option value='$tmon'>".date('M Y')."</option>";
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `bonuses$cid` WHERE `year`='$yr' AND NOT `month`='$tmon' AND `status`>10 ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":"";
				$mons.= "<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='15' ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']]=array("status"=>$row['status'],"name"=>prepare(ucwords($row['name'])),"idno"=>$row['idno']);
			}
		}
		
		$trs=$opts=""; $no=0;
		$res = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `month`='$mon' $cond");
		if($res){
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
			$posted = ($qri) ? json_decode($qri[0]["value"],1):[];
			foreach($res as $row){
				$name=$names[$row['staff']]['name']; $day=date("M d, h:i a",$row['time']); $des=json_decode($row["details"],1);
				$allow=fnum($row['allowance']); $bonus=fnum($row['bonus']); $rid=$row['id']; $act=$tds=$atds=$btds=$edpm=""; $no++;
				$tds = "<li><b>Bonuses:</b> $bonus</li><li><b>Allowances:</b> $allow</li>";
				
				if(in_array("manage salary bonus & allowances",$perms) && !isset($posted[$mon])){
					$act = "<a href='javascript:void(0)' style='color:#ff4500' onclick=\"delbonus('$rid','all')\"><i class='fa fa-times'></i> Remove</a>"; $edpm=1;
				}
				
				if(isset($des["bonus"])){
					foreach($des["bonus"] as $tc=>$sum){
						$edt1 = ($edpm) ? "<i class='fa fa-pencil' style='cursor:pointer;font-size:18px;color:#008fff' title='Edit ".ucwords(prepare($tc))."'
						onclick=\"popupload('accounts/payroll.php?addbon=$rid&df=bn~".urlencode($tc)."')\"></i>":"";
						$btds.=($tc!="taxable") ? "<li>$edt1 ".ucwords(prepare($tc))." - ".fnum(explode(":",$sum)[0])."</li>":""; 
					}
				}
				
				if(isset($des["allowance"])){
					foreach($des["allowance"] as $tc=>$sum){
						$edt1 = ($edpm) ? "<i class='fa fa-pencil' style='cursor:pointer;font-size:18px;color:#008fff' title='Edit ".ucwords(prepare($tc))."'
						onclick=\"popupload('accounts/payroll.php?addbon=$rid&df=all~".urlencode($tc)."')\"></i>":"";
						$atds.=($tc!="taxable") ? "<li>$edt1 ".ucwords(prepare($tc))." - ".fnum(explode(":",$sum)[0])."</li>":""; 
					}
				}
				
				$btds = ($btds) ? $btds:"<li>None</li>"; $atds=($atds) ? $atds:"<li>None</li>";
				$trs.= "<tr id='tr$rid' valign='top'><td style='width:30px'>$no</td><td>$name<br><span style='color:grey;font-size:14px'>$day</span></td>
				<td>$btds</td><td>$atds</td><td>$tds</td><td>$act</td></tr>";
			}
		}
		
		$add = (in_array("manage salary bonus & allowances",$perms)) ? "<button style='float:right;padding:3px;font-size:14px' class='bts' 
		onclick=\"popupload('accounts/payroll.php?addbon')\"><i class='fa fa-plus'></i> Bonus</button>":"";
					
		echo "<div class='cardv' style='overflow:auto;min-height:300px;max-width:1000px'>
		<h3 style='font-size:22px;color:#191970'>$backbtn ".date('M Y',$mon)." Bonus & Allowances</h3><hr style='margin:0px'>
			<div style='width:100%;overflow:auto'>
				<table style='width:100%;font-size:15px;min-width:500px' class='table-striped' cellpadding='5'>
					<caption style='caption-side:top'> $add
						<select style='width:90px;font-size:15px' onchange=\"loadpage('accounts/payroll.php?pbonus&year='+this.value)\">$yrs</select>
						<select style='width:120px;font-size:15px' onchange=\"loadpage('accounts/payroll.php?pbonus&year=$yr&mon='+this.value)\">$mons</select>
					</caption>
					<tr style='font-weight:bold;color:#191970;background:#E6E6FA;cursor:default' valign='top'><td colspan='2'>Staff</td>
					<td>Bonus</td><td>Allowance</td><td>Details</td><td></td></tr> $trs
				</table>
			</div>
		</div>";
		savelog($sid,"Viewed staff bonus & allowances for ".date("F Y",$mon));
	}
	
	# bonus record
	if(isset($_GET['addbon'])){
		$rid = trim($_GET['addbon']);
		$dtp = (isset($_GET["btp"])) ? trim($_GET["btp"]):"bonus";
		$stid=$cat=$amnt=$deds=$bname=$lis=$tax=""; $mon=date("Y-m");
		$opts = "<option value='0'>All Staff</option>";
		
		if($rid){
			$res = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `id`='$rid'");
			$def = explode("~",trim($_GET["df"])); $cat=$dtp=($def[0]=="bn") ? "bonus":"allowance";
			$rep = $def[1]; $stid=$res[0]['staff']; $mon=date("Y-m",$res[0]['month']); 
			$des = json_decode($res[0]['details'],1); $sum=explode(":",$des[$cat][$rep]); 
			$bname=ucwords(prepare($rep)); $amnt=$sum[0]; $tax=$sum[1]; $del=base64_encode(trim($_GET["df"]));
		}
		
		if($stid){
			$sin = "<input type='hidden' value='$stid' name='staff'><input type='hidden' name='prevd' value='".prepare($rep)."'>"; 
			$din = "<input type='hidden' value='$dtp' name='boncat'>";
			$minp = "<input type='hidden' value='$mon' name='bonmon'>";
		}
		else{
			$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='15' ORDER BY `name` ASC");
			foreach($res as $row){
				$cnf = json_decode($row['config'],1);
				if(!in_array($row["position"],["assistant","USSD"])){
					if($cnf['payroll']['include']){
						$id=$row['id']; $name=prepare(ucwords($row['name'])); $cnd = ($id==$stid) ? "selected":"";
						if($stid){
							$opts.=($row['id']==$stid) ? "<option value='$id' $cnd>$name</option>":"";
						}
						else{ $opts.=($row['status']!=1) ? "<option value='$id' $cnd>$name</option>":""; }
					}
				}
			}
			
			foreach(array("bonus"=>"Bonus","allowance"=>"Allowance") as $key=>$val){
				$cnd = ($dtp==$key) ? "selected":"";
				$deds.= "<option value='$key' $cnd>$val</option>";
			}
		
			$sin = "<p>Staff<br><select style='width:100%' name='staff'>$opts</select></p>";
			$din = "<p>Select Category<br><select name='boncat' style='width:100%' onchange=\"popupload('accounts/payroll.php?addbon=$rid&btp='+this.value)\">$deds</select></p>";
			$minp = "<p>Month<br><input type='month' style='width:100%;padding:5px;border:1px solid #ccc;outline:none' value='$mon' name='bonmon' required></p>
			<p><input type='checkbox' name='recur'> &nbsp; Recurring for Upcoming Months</p>";
		}
		
		foreach(array("Untaxed","Taxed") as $key=>$val){
			$cnd = ($tax==$key) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$val</option>"; 
		}
		
		$txt = ($dtp=="bonus") ? "Bonus":"Allowance";
		$btx = ($rid) ? "<button type='reset' class='btnn' style='background:#A52A2A;margin-right:10px' onclick=\"delbonus('$rid','$del')\">Remove</button>":"";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>$txt Record</h3><br>
			<form method='post' id='bform' onsubmit=\"savebonus(event,'$rid','$txt')\">
				<input type='hidden' name='bnid' value='$rid'> $din
				<p>Name of $txt<br><input type='text' name='boname' value=\"$bname\" style='width:100%' required></p>
				<p>Tax Type<br><select style='width:100%' name='taxtp'>$lis</select></p> $sin
				<p>Amount<br><input type='number' name='bonamt' value='$amnt' style='width:100%' required></p> $minp
				<br><p style='text-align:right'>$btx <button class='btnn'>Save</button></p><br>
			</form>
		</div>";
	}
	
	# view payroll
	if(isset($_GET["viewp"])){
		$mon = trim($_GET["viewp"]);
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y",$mon);
		$mon = (date("Y")!=$yr && !$mon) ? strtotime("$yr-Dec"):$mon;
		$tmon = strtotime(date("Y-M")); $tyr=date("Y");
		$pmon = strtotime(date("Y-M",strtotime("-1 month")));
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		$mon = (!$mon) ? $tmon:$mon;
		
		$yrs=$mons=""; $mns=$bonus=[];
		$res = $db->query(3,"SELECT DISTINCT `year` FROM `payroll$cid` ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
				$yrs.= "<option value='$year' $cnd>$year</option>";
			}
		}
		
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `payroll$cid` WHERE `year`='$yr' ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){ $mns[]=$row['month']; }
			foreach($mns as $mn){
				$cnd=($mon==$mn) ? "selected":"";
				$mons.= "<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='15' ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']]=array("status"=>$row['status'],"name"=>prepare(ucwords($row['name'])),"idno"=>$row['idno'],"pay"=>json_decode($row['config'],1)['payroll']);
			}
		}
		
		if($db->istable(3,"bonuses$cid")){
			$qri = $db->query(3,"SELECT * FROM `bonuses$cid` WHERE `month`='$mon' AND `status`='200'");
			if($qri){
				foreach($qri as $row){ $bonus[$row['staff']]=$row['bonus']; }
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='updatepayroll' OR `setting`='postedpayroll')");
		if($res){
			foreach($res as $row){ $sets[$row['setting']]=$row['value']; }
		}
		
		$upd = (isset($sets["updatepayroll"])) ? $sets["updatepayroll"]:2; 
		$posted = (isset($sets["postedpayroll"])) ? json_decode($sets["postedpayroll"],1):[]; 
		$dif = monsDiff(date("Y-m-d",$mon),date("Y-m-d")); $ups=0; $post="";
		
		if(!isset($posted[$mon])){
			$post = "<button class='bts' style='float:right;font-size:14px;padding:2px;' onclick=\"popupload('accounts/payroll.php?blkpost=$mon')\"><i class='bi-upload'></i> Bulk Post</button>";
			if($upd==4){ $ups = ($dif<=6) ? 1:0; }
			elseif($upd==5){ $ups = 1; }
			else{ $ups = ($dif<=$upd) ? 1:0; }
		}
		
		$thb=$nta=$pns=0; $dcuts=[];
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$thb+=$row["helb"]; $nta+=$row["nita"]; $pns+=$row["pension"]; $dct=json_decode($row["cuts"],1);
				foreach($dct as $dc=>$am){
					if(isset($dcuts[$dc])){ $dcuts[$dc]+=$am; }else{ $dcuts[$dc]=$am; }
				}
			}
		}
		
		$trs=$prnt=$gen=$ths=""; $no=$tot1=$tot2=$tot3=$tot4=$tot5=$tot6=$tot7=$tot8=$tot9=$tot10=$tota=$totb=$totc=$tot11=$kn=0; $dcts=[];
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$bpay=fnum($row['basicpay']); $allow=fnum($row['allowance']); $benefits=fnum($row['benefits']); 
				$nssf=fnum($row['nssf']); $grospay=fnum($row['grosspay']); $taxable=fnum($row['taxablepay']); 
				$paye=fnum($row['paye']);  $rid=$row['id']; $nhif=fnum($row['nhif']); $deduct=fnum($row['deductions']); 
				$netpay=fnum($row['netpay']); $stid=$row['staff']; $name=$names[$stid]['name']; 
				$cuts = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $kn++;
				foreach($dcuts as $one=>$tx){ if(!isset($cuts[$one])){ $cuts[$one]=0; }}
				$bnus=(isset($bonus[$stid])) ? $bonus[$stid]:0; $tot11+=$bnus;
				
				$tot1+=intval($row['basicpay']); $tot2+=intval($row['allowance']); $tot3+=intval($row['benefits']); 
				$tot4+=intval($row['grosspay']); $tot5+=intval($row['taxablepay']); $tota+=intval($row['helb']);
				$tot6+=intval($row['paye']); $tot7+=intval($row['nssf']); $tot8+=intval($row['nhif']); 
				$tot10+=intval($row['netpay']); $totb+=intval($row['nita']); $totc+=intval($row['pension']);
				$tds = ($thb) ? "<td>".fnum($row['helb'])."</td>":""; $tds.=($nta) ? "<td>".fnum($row['nita'])."</td>":""; 
				$tds.= ($pns) ? "<td>".fnum($row['pension'])."</td>":""; $tot9+=intval($row['deductions']); 
				foreach($cuts as $cut=>$sum){ $tds.="<td>".fnum($sum)."</td>"; }
				
				$onclick = (in_array("manage payroll",$perms) && $ups) ? "popupload('accounts/payroll.php?update=$rid')":"";
				$trs.="<tr style='text-align:right' onclick=\"$onclick\"><td style='text-align:left'>$kn. $name</td><td>$bpay</td><td>$allow</td>
				<td>$benefits</td><td>$bnus</td><td>$grospay</td><td>$taxable</td><td>$paye</td><td>$nssf</td><td>$nhif</td>$tds<td>$deduct</td><td>$netpay</td></tr>";
			}
			
			$ths = ($thb) ? "<td>HELB</td>":""; $ths.=($nta) ? "<td>NITA</td>":""; $ths.=($pns) ? "<td>Pension</td>":"";
			$tht = ($thb) ? "<td>".fnum($tota)."</td>":""; $tht.=($nta) ? "<td>".fnum($totb)."</td>":""; $tht.=($pns) ? "<td>".fnum($totc)."</td>":"";
			foreach($dcuts as $dct=>$txo){ $ths.="<td>".prepare(ucfirst($dct))."</td>"; $tht.="<td>".fnum($txo)."</td>"; }
		
			$trs.= "<tr style='text-align:right;cursor:default;font-weight:bold;background:linear-gradient(to top,#E6E6FA,#f0f0f0,#fff);color:#191970'>
			<td>Totals</td><td>".fnum($tot1)."</td><td>".fnum($tot2)."</td><td>".fnum($tot3)."</td><td>".fnum($tot11)."</td><td>".fnum($tot4)."</td>
			<td>".fnum($tot5)."</td><td>".fnum($tot6)."</td><td>".fnum($tot7)."</td><td>".fnum($tot8)."</td>$tht<td>".fnum($tot9)."</td><td>".fnum($tot10)."</td></tr>";
			
			$check = $db->query(3,"SELECT COUNT(*) AS total FROM `payslips$cid` WHERE `month`='$mon'"); $avs = ($check) ? $check[0]['total']:0;
			$prnt = genrepDiv("payroll.php?mon=$mon",'right');
			
			$gen = ($avs) ? "":"<button class='bts' style='float:right;font-size:14px;padding:2px;margin-left:7px' 
			onclick=\"loadpage('accounts/payroll.php?genslips=$mon')\"><i class='fa fa-book'></i> Gen Payslips</button>";
		}
		else{
			foreach($names as $one){
				$name=prepare(ucwords($one['name'])); $pyr=$one['pay'];
				if($one['status']!=1 && $pyr['include']){
					$onclick = (in_array("manage payroll",$perms) && $ups) ? "loadpage('accounts/payroll.php?manage=$mon')":""; $kn++;
					$trs.="<tr style='text-align:right' onclick=\"$onclick\"><td style='text-align:left'>$kn. $name</td>
					<td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td></tr>";
				}
			}
			
			$trs.= "<tr style='text-align:right;font-weight:bold;background:linear-gradient(to top,#E6E6FA,#f0f0f0,#fff);cursor:default;color:#191970'>
			<td>Totals</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td><td>0.00</td></tr>";
		}
		
		$update = (in_array("manage payroll",$perms) && $ups) ? "<button class='bts' style='float:right;font-size:14px;padding:2px 5px;margin-left:7px' 
		onclick=\"loadpage('accounts/payroll.php?manage=$mon')\"><i class='fa fa-refresh'></i> Update</button>":"";
		
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:1400px'>
			<h3 style='font-size:22px;color:#191970'>$backbtn ".date('F Y',$mon)." Payroll $post</h3>
			<table style='width:100%;font-size:14px;min-width:700px' class='table-striped btbl' cellpadding='5'>
				<caption style='caption-side:top'> $update $gen $prnt
					<select style='width:80px;font-size:14px' onchange=\"loadpage('accounts/payroll.php?viewp&yr='+this.value)\">$yrs</select>
					<select style='width:120px;font-size:14px' onchange=\"loadpage('accounts/payroll.php?yr=$yr&viewp='+this.value)\">$mons</select>
				</caption>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA;text-align:right;cursor:default' valign='top'><td style='text-align:left'>Staff</td>
				<td>Basic Pay</td><td>Allowances</td><td>Benefits</td><td>Bonus</td><td>Gross Pay</td><td>Taxable Pay</td><td>PAYE</td><td>NSSF</td><td>SHIF</td>$ths
				<td>Deductions</td><td>Net Pay</td></tr> $trs
			</table>
		</div>";
		savelog($sid,"Viewed Staff payroll for ".date("F Y",$mon));
	}
	
	# post payroll to accounts
	if(isset($_GET["blkpost"])){
		$mon = trim($_GET["blkpost"]); $list=[]; 
		$skip = array("id","staff","basicpay","allowance","benefits","grosspay","taxablepay","deductions","month","year");
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='payroll_accounts'");
		$hist = ($qri) ? json_decode($qri[0]["value"],1):[];
		
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		foreach($qri as $row){
			foreach($skip as $col){ unset($row[$col]); }
			foreach($row as $col=>$val){
				if(in_array($col,["cuts"])){ $val = json_decode($row[$col],1); }
				if(is_array($val)){
					foreach($val as $key=>$sum){
						if($sum>0){
							if(isset($list[$key])){ $list[$key]+=$sum; }
							else{ $list[$key]=$sum; }
						}
					}
				}
				else{
					if($val>0){
						if(isset($list[$col])){ $list[$col]+=$val; }
						else{ $list[$col]=$val; }
					}
				}
			}
		}
		
		$qri = $db->query(3,"SELECT `category`,SUM(amount) AS tsum FROM `deductions$cid` WHERE `month`='$mon' AND `status`>200 GROUP BY `category`"); 
		if($qri){
			foreach($qri as $row){
				$ded = $row["category"]; $sum=$row["tsum"]; 
				if($sum>0 && trim(substr($ded,0,11))!="Staff Loan"){
					if(isset($list[$ded])){ $list[$ded]+=$sum; }
					else{ $list[$ded]=$sum; }
				}
			}
		}
		
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`type`='expense' OR `type`='liability') ORDER BY `account` ASC");
		foreach($sql as $row){
			$rid = $row["id"]; $acc=prepare(ucfirst($row["account"])); $alis[$row["type"]][$rid]=$acc;
		}
		
		$lhist = (isset($hist["liabs"])) ? $hist["liabs"]:[];
		$ehist = (isset($hist["expes"])) ? $hist["expes"]:[]; $trs="";
		
		foreach($list as $cat=>$val){
			$lops=$eops="<option value='0'>-- Select Account --</option>"; $vhst=(isset($lhist[$cat])) ? $lhist[$cat]:0;
			$tname = ($cat=="netpay") ? "Staff Net Pay":prepare(ucwords($cat)); $tname=($cat=="nhif") ? "SHIF":$tname;
			foreach($alis["liability"] as $rid=>$acc){
				$cnd = ($rid==$vhst) ? "selected":""; $lops.="<option value='$rid' $cnd>$acc</option>";
			}
			
			$ehst = (isset($ehist[$cat])) ? $ehist[$cat]:0;
			foreach($alis["expense"] as $rid=>$acc){
				$cnd = ($rid==$ehst) ? "selected":""; $eops.="<option value='$rid' $cnd>$acc</option>";
			}
			
			$trs.= ($cat!="Salary advance") ? "<tr><td><b>$tname</b> (Ksh ".fnum($val).")</td><td style='width:33%'>
			<select style='width:100%;font-size:15px;padding:5px' name='liabacc[$cat]'>$lops</select></td><td style='width:33%'><select style='width:100%;font-size:15px;padding:5px' 
			name='expacc[$cat]'>$eops</select></td></tr>":"";
		}
		
		echo "<div style='margin:0 auto;padding:10px'>
			<h3 style='font-size:23px;text-align:center;color:#191970'>Post ".date("M Y",$mon)." Payroll to Accounts</h3>
			<form method='post' id='pform' onsubmit=\"postpayroll(event)\">
				<input type='hidden' name='postpay' value='$mon'> 
				<table cellpadding='5' style='font-size:15px;min-width:400px' class='table-striped'>
					<tr style='font-weight:bold;color:#191970;background:#e6e6fa'><td>Account</td><td>Payable Liability A/C</td><td>Expense Account</td></tr> $trs
				</table><br>
				<p style='padding:10px;font-size:14px;color:#ff4500;background:#FFE4E1;border:1px solid #ff4500'><b>NOTE:</b> Before you proceed to post make sure all deductions,
				Payroll, bonuses & allowances are okay since after posting Payroll will be locked from any updates!</p>
				<p style='text-align:right'><button class='btnn'><i class='bi-upload'></i> Confirm to Post</button></p>
			</form>
		</div>";
	}
	
	# update payroll for staff
	if(isset($_GET['update'])){
		$pid = trim($_GET['update']);
		if(!$db->istable(3,"bonuses$cid")){
			$db->createTbl(3,"bonuses$cid",["staff"=>"INT","month"=>"INT","bonus"=>"CHAR","allowance"=>"CHAR","details"=>"TEXT","year"=>"INT","status"=>"INT","time"=>"INT"]);
		}
		
		$res = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `id`='$pid'");
		$row = $res[0]; $stid=$rid=$row['staff']; $bpay=$row['basicpay']; $allow=$row['allowance']; 
		$bene=$row['benefits']; $mon=$row['month']; $helb=$row['helb']; $nita=$row['nita']; $pen=$row['pension'];
		
		$qri = $db->query(3,"SELECT SUM(amount) AS tsum FROM `deductions$cid` WHERE `staff`='$stid' AND `month`='$mon' AND `status`>200");
		$deduct = ($qri) ? intval($qri[0]['tsum']):$row['deductions']; $name=prepare(ucwords(staffInfo($stid)['name']));
		
		$qri = $db->query(3,"SELECT * FROM `bonuses$cid` WHERE `month`='$mon' AND `staff`='$stid' AND `status`='200'");
		$bonus = ($qri) ? intval($qri[0]['bonus']):0; $allow=($qri) ? intval($qri[0]['allowance']):0; 
		
		echo "<div style='max-width:300px;padding:10px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Update Payroll for $name</h3><br>
			<form method='post' id='spform' onsubmit=\"savepayroll(event,'spform','$mon')\">
				<input type='hidden' name='pmon' value='$mon'> 
				<p>Basic Pay<br><input type='number' name='basicpay[$rid]' value='$bpay' style='width:100%' required></p>
				<p>Allowances <span style='float:right'>Bonus</span><br><input type='number' value='$allow' disabled style='width:49%;cursor:not-allowed' required>
				<input type='number' disabled value='$bonus' style='width:49%;float:right;cursor:not-allowed' required></p>
				<p>HELB <span style='float:right'>NITA</span><br><input type='number' value='$helb' name='helb[$rid]' style='width:49%' required>
				<input type='number' name='nita[$rid]' value='$nita' style='width:49%;float:right' required></p>
				<p>Pension <span style='float:right'>Deductions</span><br><input type='number' value='$pen' name='pension[$rid]' style='width:49%' required>
				<input type='number' name='deducts[$rid]' value='$deduct' style='width:49%;float:right;cursor:not-allowed' disabled required></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p>
			</form><br>
		</div>";
	}
	
	# setup/update payroll
	if(isset($_GET['manage'])){
		$mon = trim($_GET['manage']);
		if(!$db->istable(3,"bonuses$cid")){
			$db->createTbl(3,"bonuses$cid",["staff"=>"INT","month"=>"INT","bonus"=>"CHAR","allowance"=>"CHAR","details"=>"TEXT","year"=>"INT","status"=>"INT","time"=>"INT"]);
		}
		
		$deducts=$pays=$bonuses=$allows=$prevs=[]; 
		$qri = $db->query(3,"SELECT *,SUM(amount) AS tamnt FROM `deductions$cid` WHERE `month`='$mon' AND `status`>200 GROUP BY `staff`");
		if($qri){
			foreach($qri as $row){ $deducts[$row['staff']]=$row['tamnt']; }
		}
		
		$qri = $db->query(3,"SELECT * FROM `bonuses$cid` WHERE `month`='$mon' AND `status`='200'");
		if($qri){
			foreach($qri as $row){
				$bonuses[$row['staff']]=$row['bonus']; $allows[$row['staff']]=$row['allowance']; 
			}
		}
		
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){ $pays[$row['staff']]=$row; }
		}
		else{
			$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`=(SELECT MAX(month) FROM `payroll$cid`)");
			if($qri){
				foreach($qri as $row){ $prevs[$row["staff"]]=$row; }
			}
		}
		
		$cond = (count($pays)) ? "":"AND NOT `status`='1'"; $trs=""; $no=0;
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='15' $cond ORDER BY `name` ASC");
		foreach($res as $row){
			if(count($pays)){
				if(isset($pays[$row['id']])){
					$name=prepare(ucwords($row['name'])); $rid=$row['id']; $bpay=$pays[$rid]['basicpay']; $no++;
					$deduct=(isset($deducts[$rid])) ? fnum($deducts[$rid]):0; $allow=(isset($allows[$rid])) ? fnum($allows[$rid]):0;
					$nita=$pays[$rid]['nita']; $helb=$pays[$rid]['helb']; $pens=$pays[$rid]['pension'];
					$bonus=(isset($bonuses[$rid])) ? fnum($bonuses[$rid]):0;
					
					$trs.= "<tr><td>$no. $name</td>
					<td style='width:12%'><input type='number' name='basicpay[$rid]' value='$bpay' style='width:100%;padding:4px' required></td>
					<td style='width:9%'><input type='number' name='helb[$rid]' value='$helb' style='width:100%;padding:4px' required></td>
					<td style='width:9%'><input type='number' name='pension[$rid]' value='$pens' style='width:100%;padding:4px' required></td>
					<td style='width:9%;text-align:center'>$allow</td><td style='width:9%;text-align:center'>$bonus</td>
					<td style='width:9%;text-align:center'>$deduct</td></tr>";
				}
			}
			else{
				if(!in_array(trim($row["position"]),["assistant","USSD","collection agent"])){
					$pay = json_decode($row['config'],1)['payroll']; $rid=$row['id']; 
					$bonus = (isset($bonuses[$rid])) ? fnum($bonuses[$rid]):0;
					$name = prepare(ucwords($row['name'])); $no+=($pay['include']) ? 1:0;
					$deduct = (isset($deducts[$rid])) ? fnum($deducts[$rid]):0; 
					$allow = (isset($allows[$rid])) ? fnum($allows[$rid]):0;
					$bpay = (isset($prevs[$rid])) ? $prevs[$rid]["basicpay"]:0;
					
					$trs.= ($pay['include']) ? "<tr><td>$no. $name</td>
					<td style='width:12%'><input type='number' name='basicpay[$rid]' value='$bpay' style='width:100%;padding:4px' required></td>
					<td style='width:9%'><input type='number' name='helb[$rid]' value='0' style='width:100%;padding:4px' required></td>
					<td style='width:9%'><input type='number' name='pension[$rid]' value='0' style='width:100%;padding:4px' required></td>
					<td style='width:9%;text-align:center'>$allow</td><td style='width:9%;text-align:center'>$bonus</td>
					<td style='width:9%;text-align:center'>$deduct</td></tr>":"";
				}
			}
		}
		
		$sql = $db->query(3,"SELECT MAX(month) AS mxm FROM `payroll$cid`"); $mxm=($sql) ? $sql[0]['mxm']:0;
		$add = ($mxm==$mon) ? "<button class='bts' style='float:right' onclick=\"popupload('accounts/payroll.php?payinc=$mon')\"><i class='fa fa-plus'></i> Add Staff</button>":"";
		
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:1100px'>
		<h3 style='font-size:22px;color:#191970;'>$backbtn ".date("M Y",$mon)." Payroll Setup $add</h3>
			<form method='post' id='prform' onsubmit=\"savepayroll(event,'prform','$mon')\">
				<input type='hidden' name='pmon' value='$mon'>
				<table style='width:100%;font-size:15px;min-width:600px;margin-top:15px' cellpadding='5'>
					<tr style='font-weight:bold;color:#191970;background:#E6E6FA;' valign='top'><td>Staff</td>
					<td>Basic Pay</td><td>HELB</td><td>Pension</td><td>Allowances</td><td>Bonus</td><td>Deductions</td></tr> $trs
					<tr><td colspan='8' style='text-align:right'><hr><button class='btnn'>Update</button></td></tr>
				</table>
			</form>
		</div>";
		savelog($sid,"Accessed payroll setup for ".date("F Y",$mon));
	}
	
	# add staff to payroll
	if(isset($_GET['payinc'])){
		$mon = trim($_GET['payinc']); $users=[];
		$sql = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE NOT `status`='15' ORDER BY `time` DESC");
		if($sql){ foreach($sql as $row){ $users[]=$row['staff']; }}
		
		$opts = "<option value='0'>-- Select --</option>";
		foreach($qri as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); $cnf=json_decode($row['config'],1); $inc=$cnf["payroll"]["include"];
			$opts.= (!in_array($rid,$users) && !in_array($row["position"],["assistant","USSD"]) && $inc) ? "<option value='$rid'>$name</option>":"";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>Include staff to Payroll</h3><br>
			<form method='post' id='uform' onsubmit=\"incstaff(event,'".date('M Y',$mon)."')\">
				<input type='hidden' name='incmon' value='$mon'>
				<p>Select Staff to Include<br><select id='stf' name='stid' style='width:100%;cursor:pointer'>$opts</select></p><br>
				<p style='text-align:right'><button class='btnn'>Include</button></p>
			</form>
		</div>";
	}
	
	# Generate payslips
	if(isset($_GET['genslips'])){
		$mon = trim($_GET['genslips']);
		$prevs=[]; $trs=""; $no=0;
		
		$cols = $db->tableFields(3,"payslips$cid");
		if(!in_array("bankcode",$cols)){
			$db->execute(3,"ALTER TABLE `payslips1` ADD `bankcode` VARCHAR(255) NOT NULL AFTER `bank`, ADD `branch` VARCHAR(255) NOT NULL AFTER `bankcode`");
		}
		
		$qri = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`=(SELECT MAX(month) FROM `payslips$cid`)");
		if($qri){
			foreach($qri as $row){ $prevs[$row['staff']]=$row; }
		}
		
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		foreach($sql as $row){ $bnames[$row["id"]]=prepare(ucwords($row["branch"])); }
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($qri as $row){
			if(!in_array($row["position"],["assistant","USSD"])){
				$names[$row['id']] = prepare(ucwords($row['name']));
				if(!isset($prevs[$row["id"]])){
					$prevs[$row["id"]] = array("bank"=>"Safaricom MPESA","account"=>"0".$row["contact"],"bankcode"=>"MPESAKE","branch"=>$bnames[$row["branch"]]);
				}
			}
		}
		
		$res = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		foreach($res as $row){
			$stid = $row['staff']; $name = $names[$stid]; $bank = (isset($prevs[$stid]['bank'])) ? prepare(ucwords($prevs[$stid]['bank'])):"";
			$acc = (isset($prevs[$stid]['account'])) ? $prevs[$stid]['account']:""; $npay=$row['netpay']; $no++;
			$bnkc = (isset($prevs[$stid]['bankcode'])) ? $prevs[$stid]['bankcode']:""; $dy=date("md").$stid;
			$brnc = (isset($prevs[$stid]['branch'])) ? $prevs[$stid]['branch']:"";
			
			$trs.="<tr><td>$no. $name</td> <input type='hidden' name='pays[$stid]' value='$npay'>
			<td style='width:15%'><input type='text' value='$bank' name='banks[$stid]' style='width:100%;padding:5px' required></td>
			<td style='width:15%'><input type='text' name='bnkcodes[$stid]' value='$bnkc' style='width:100%;padding:5px' required></td>
			<td style='width:15%'><input type='text' name='brncodes[$stid]' value='$brnc' style='width:100%;padding:5px' required></td>
			<td style='width:15%'><input type='text' name='accounts[$stid]' value='$acc' style='width:100%;padding:5px' required></td>
			<td style='width:15%'><input type='text' name='cheques[$stid]' style='width:100%;padding:5px' value='$dy' required></td></tr>";
		}
		
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:1200px'>
		<h3 style='font-size:22px;color:#191970;'>$backbtn ".date("F Y",$mon)." Payslips</h3>
			<form method='post' id='sfom' onsubmit=\"genslips(event)\">
				<input type='hidden' name='slipmon' value='$mon'>
				<table style='width:100%;font-size:15px;min-width:600px;margin-top:15px' cellpadding='5'>
					<tr style='font-weight:bold;color:#191970;background:#E6E6FA;' valign='top'><td>Staff</td><td>Bank Name</td>
					<td>Bank Code</td><td>Branch Code/Name</td><td>Account No</td><td>Cheque No</td></tr> $trs
					<tr><td colspan='6' style='text-align:right'><br><button class='btnn'>Generate</button></td></tr>
				</table><br>
			</form>
		</div>";
		
		savelog($sid,"Accessed Payslips setup for ".date("F Y",$mon));
	}
	
	# send emails
	if(isset($_GET['sendmail'])){
		$mon = trim($_GET['sendmail']);
		$res = $db->query(1,"SELECT *FROM `stamps` WHERE `client`='$cid' AND `month`='$mon'");
		$img = ($res) ? $res[0]['stamp']:"stamp.png";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:300px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Email Payslips for ".date('M Y',$mon)."</h3><br>
			<form method='post' id='mform' onsubmit=\"mailpayslips(event)\">
				<input type='hidden' name='mailmon' value='$mon'> <input type='hidden' name='stamp' id='stmp' value='$img'>
				<input type='file' id='stamp' style='display:none;' onchange=\"changestamp('$mon')\" accept='image/*'>
				<p style='text-align:center'><b>Confirm Payslip stamp</b><br> ** <i style='color:grey'>Click on the photo to update</i> **</p>
				<p style='text-align:center'> <label for='stamp'>
				<img id='simg' src='$path/docs/img/$img' style='max-width:100%;max-height:140px;cursor:pointer'></label></p><hr><br>
				<p><button class='bts' style='font-size:14px;padding:5px' type='reset' onclick=\"printdoc('payslip.php?src=$mon','Payslip')\">
				<i class='fa fa-folder-open'></i> Preview Payslip</button>
				<button class='btnn' style='float:right;padding:6px'><i class='fa fa-mail-forward'></i> Send Emails</button></p><br>
			</form><br>
		</div>";
	}
	
	ob_end_flush();
?>

<script>
	
	function delrow(id){
		if(confirm("Sure to remove the row?")){
			$("#"+id).remove();
		}
	}
	
	function setire(tp){
		$("#nssf").val(tiers[tp]);
	}
	
	function valnum(id,val){
		var exp=/^[0-9.%]+$/;
		if(!val.match(exp)){ _(id).value=val.slice(0,-1); return false;}
		else{ return true; }
	}
	
	function addcut(){
		var name = prompt("Enter deduction name");
		if(name){
			var did = Date.now(), tbcl=name.split(" ").join("-");
			$(".cuts").append("<div id='"+did+"'><h3 style='font-weight:bold;padding:8px;font-size:18px;background:#f0f0f0;margin-bottom:10px;color:#7B68EE'>"+
			"<span style='cursor:pointer;'><i class='bi-list-stars'></i> "+name+"</span>"+
			"<a href='javascript:void(0)' style='float:right;color:#ff4500;font-size:16px' onclick=\"$('#"+did+"').remove()\"><i class='fa fa-minus-circle'></i> Remove</a></h3>"+
			"<div class='tblcut' style='padding:5px;background:#f8f8ff;border:1px solid #f0f0f0;margin-bottom:10px;margin-top:-10px;overflow:auto'>"+
				"<table style='width:100%;' cellpadding='5' class='"+tbcl+"'>"+
				"<caption style='margin:0px;padding:0px;text-align:right'><a href='javascript:void(0)' onclick=\"addcutrow('"+name+"')\"><i class='bi-plus-circle'></i> Range</a></caption>"+
				"<tr style='font-weight:bold;color:#191970'><td>Minimum (Ksh)</td><td>Maximum (Ksh)</td><td>Amount</td></tr>"+
				"<tr><td><input type='number' name='mins["+name+"][]' style='width:100%' required></td><td style='width:32%'><input type='number' name='maxs["+name+"][]'"+ 
				"style='width:100%' required></td><td style='width:32%'><input type='text' id='a"+did+"' name='sums["+name+"][]' onkeyup=\"valnum('a"+did+"',this.value)\""+
				"style='width:100%' required></td></tr></table>"+
			"</div></div>");
		}
	}
	
	function addcutrow(tbl){
		var did = Date.now();
		$("."+tbl.split(" ").join("-")).append("<tr id='"+did+"' valign='top'><td><input type='number' name='mins["+tbl+"][]' style='width:100%' required>"+
		"</td><td style='width:32%'><input type='number' name='maxs["+tbl+"][]' style='width:100%' required></td>"+
		"<td><input type='text' id='a"+did+"' name='sums["+tbl+"][]' onkeyup=\"valnum('a"+did+"',this.value)\" style='width:100%' required>"+
		"<br><a href='javascript:void(0)' onclick=\"delrow('"+did+"')\" style='float:right;color:#ff4500'>Remove</a></td></tr>");
	}
	
	function changestamp(mon){
		var img = _("stamp").files[0];
		if(img!=null){
			if(confirm("Upload selected Photo as stamp?")){
				var formdata=new FormData();
				var xhr=new XMLHttpRequest();
				xhr.upload.addEventListener("progress",imgprogress,false);
				xhr.addEventListener("load",imgdone,false);
				xhr.addEventListener("error",imgerror,false);
				xhr.addEventListener("abort",imgabort,false);
				formdata.append("stampmon",mon);
				formdata.append("stamp",img);
				xhr.onload=function(){
					if(this.responseText.trim().split(":")[0]=="success"){
						toast("Changed successfull"); var new_img = this.responseText.trim().split(":")[1];
						$("#simg").attr("src","<?php echo $path; ?>/docs/img/"+new_img); $("#stmp").val(new_img);
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/payslip.php",true);
				xhr.send(formdata);
			}
		}
	}
	
	function imgprogress(event){
		var percent=(event.loaded / event.total) * 100;
		progress("Uploading photo "+Math.round(percent)+"%");
		if(percent==100){
			progress("Cropping...please wait");
		}
	}
		
	function imgdone(event){ progress(); }
		
	function imgerror(event){
		toast("Upload failed"); progress();
	}
		
	function imgabort(event){
		toast("Upload aborted"); progress();
	}
	
	function mailpayslips(e){
		e.preventDefault();
		var img = _("stmp").value.trim();
		if(img=="stamp.png"){ alert("Failed: Upload a stamp Image First"); }
		else{
			if(confirm("Sure to mail paysilps to staff?")){
				var data=$("#mform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/payslip.php",data:data,
					beforeSend:function(){ progress("Sending...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Emails added to send queue successfully"); closepop();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function disbsalo(e,mode){
		e.preventDefault();
		if(!checkboxes("saloto[]")){ toast("No staff Selected!"); }
		else{
			if(confirm("Disburse salaries to selected staff?")){
				var pin = (mode=="accs") ? "123":prompt("Enter B2C Secret Pin","*****");
				var data = $("#dsfom").serialize(); data+="&dpin="+pin;
				$.ajax({
					method:"post",url:path()+"dbsave/b2cops.php",data:data,
					beforeSend:function(){ progress("Sending...please wait"); },
					complete:function(){progress();} 
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Request sent for "+res.trim().split(":")[1]); closepop();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function incstaff(e,mon){
		e.preventDefault();
		if($("#stf").val().trim()==0){ toast("No staff Selected!"); }
		else{
			if(confirm("Include selected staff to "+mon+" payroll?")){
				var data = $("#uform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/payroll.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Added successfully"); closepop(); window.location.reload();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function postpayroll(e){
		e.preventDefault();
		if(confirm("Sure to post payroll accounts to Journals?")){
			var data = $("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payroll.php",data:data,
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); toast("Success"); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function delbonus(bid,cat){
		if(confirm("Sure to remove bonus/Allowance record?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payroll.php",data:{delbonus:bid,addn:cat},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); toast("Success"); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function delcut(cut){
		if(confirm("Sure to remove deduction?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{delcut:cut},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ loadpage("accounts/payroll.php?settings"); }
				else{ alert(res); }
			});
		}
	}
	
	function confdisb(e,rid){
		e.preventDefault();
		if(confirm("Confirm staff deduction status?")){
			var data = $("#cform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payroll.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Confirmed successfully"); closepop(); $("#tr"+rid).remove();
				}
				else{ alert(res); }
			});
		}
	}
	
	function printslip(mon,pid){
		$.ajax({
			method:"post",url:path()+"dbsave/payslip.php",data:{mailmon:mon,payid:pid,noemail:1},
			beforeSend:function(){ progress("Preparing...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim().split(":")[0]=="success"){
				var loc = (window.location.hostname=="localhost") ? "/mfi/":"/";
				window.open(loc+"pdf/files/payslips/"+res.trim().split(":")[1]);
			}
			else{ alert(res); }
		});
	}
	
	function mailslip(mon,pid){
		if(confirm("Send payslip via email to staff?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payslip.php",data:{mailmon:mon,payid:pid},
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Sent successfully");
				}
				else{ alert(res); }
			});
		}
	}
	
	function savebank(e){
		e.preventDefault();
		if(confirm("Update bank details for staff?")){
			var data=$("#bform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payslip.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Updated successfully"); closepop(); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function genslips(e){
		e.preventDefault();
		if(confirm("Generate staff payslips from payroll?")){
			var data=$("#sfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payslip.php",data:data,
				beforeSend:function(){ progress("Generating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Generated successfully"); window.history.back();
				}
				else{ alert(res); }
			});
		}
	}
	
	function savepayroll(e,fom,mon){
		e.preventDefault();
		if(confirm("Update staff payroll?")){
			var data = $("#"+fom).serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payroll.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Updated successfully"); closepop(); loadpage("accounts/payroll.php?viewp="+mon);
				}
				else{ alert(res); }
			});
		}
	}
	
	function savepaysett(e){
		e.preventDefault();
		if(confirm("Update payroll settings?")){
			var data=$("#sform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Updated successfully"); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function savebonus(e,rid,tp){
		e.preventDefault();
		var txt = (rid>0) ? "Proceed to update "+tp+" record for staff?":"Create "+tp+" record for staff?";
		if(confirm(txt)){
			var data = $("#bform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payroll.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Success!"); closepop(); loadpage("accounts/payroll.php?pbonus&mon="+res.trim().split(":")[1]); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function savededuct(e,rid){
		e.preventDefault();
		var txt = (rid>0) ? "Sure to update deduction record for staff?":"Create deduction record for staff?";
		if(confirm(txt)){
			var data=$("#dform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Success!"); closepop(); loadpage("accounts/payroll.php?deductions&mon="+res.trim().split(":")[1]); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function delrecord(did){
		if(confirm("Sure to delete record?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{deldeduct:did},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+did).remove(); }
				else{ alert(res); }
			});
		}
	}
	
</script>