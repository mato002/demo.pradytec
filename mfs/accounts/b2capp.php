<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
	$b2c_apps = ($qri) ? json_decode($qri[0]['platforms'],1):[];
	$b2c_users = ($qri) ? json_decode($qri[0]['users'],1):[];
	if(!in_array($sid,$b2c_users)){ exit(); }
	
	# base
	if(isset($_GET['home'])){
		if(trim($_GET['home'])){ sleep(trim($_GET['home'])); }
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]); 
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "http://pay.axecredits.com":"http://pay.".implode(".",$http); 
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles'])); $baldiv=$pbset="";
		
		$chk = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid' AND `status`='0'");
		if(isset($chk[0]["defs"])){
			$list = json_decode($chk[0]["routes"],1); $pbls=[];
			foreach($list as $md=>$one){
				foreach($one as $pb=>$k){ $pbls[$pb]=$pb; }
			}
			
			$pbl = (isset($_GET["pbl"])) ? trim($_GET["pbl"]):array_values($pbls)[0]; $opts="";
			if(count($pbls)>1){
				foreach($pbls as $pb){
					$cnd = ($pb==$pbl) ? "selected":"";
					$opts.= "<option value='$pb' $cnd>$pb</option>";
				}
			}
			
			$pbset = ($opts) ? "<select style='width:120px;float:right;font-size:15px;' onchange=\"loadpage('accounts/b2capp.php?home&pbl='+this.value)\">$opts</select>":"";
		}
		else{ $pbl = (defined("B2C_DEF")) ? B2C_DEF["comdef"]["paybill"]:"B2C"; }
		
		if(!$db->istable(3,"utilities$cid")){
			$def = array("staff"=>"INT","branch"=>"INT","item_description"=>"TEXT","cost"=>"INT","approvals"=>"TEXT","approved"=>"CHAR","recipient"=>"TEXT","month"=>"INT","status"=>"INT","time"=>"INT");
			$db->createTbl(3,"utilities$cid",$def);
		}
		
		if(in_array("view b2c balances",$perms)){
			$res = request("$url/b2c/request.php",["fetch"=>base64_encode("SELECT *FROM `balances` WHERE `paybill`='$pbl'"),"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"payb"=>$pbl]);
			$bals = (!is_numeric($res) && $res!="none") ? json_decode($res,1):null;
			$last = (is_array($bals)) ? $bals[0]['time']:0; $mbal = (is_array($bals)) ? fnum($bals[0]['working']):0; 
			$ubal = (is_array($bals)) ? fnum($bals[0]['utility']):0;
			
			$baldiv = "<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
				<div class='jdiv'><h3 style='font-size:17px;color:#191970'> <i class='bi-cash-coin'></i>
				Working KES $mbal | Utility KES $ubal</h3><p style='margin-bottom:0px;font-size:14px'>Paybill balances as at ".date("M d, h:i a",$last)
				." &nbsp; <a href='javascript:void(0)' onclick=\"checkbal('$pbl')\"><i class='fa fa-refresh'></i> Fetch New</a></p>
				</div>
			</div>";
		}
		
		$ckpass = ($sid==1) ? "&nbsp; | &nbsp <a href='javascript:void(0)' onclick=\"popupload('accounts/b2capp.php?security=pass&psrc=$pbl')\">
		<i class='fa fa-lock'></i> Change Password</a>":"";
		
		$config = (in_array("update b2c password",$perms)) ? "<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
		<div class='jdiv'><h3 style='font-size:17px;color:#191970'> <i class='bi-key'></i> Security</h3>
			<p style='margin-bottom:0px;'><a href='javascript:void(0)' onclick=\"popupload('accounts/b2capp.php?security=pin&psrc=$pbl')\">
			<i class='fa fa-key'></i> Change PIN</a> $ckpass</p></div>
		</div>":"";
		
		$qr1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_loantemplates` WHERE `status`='8' AND NOT `loantype` LIKE '%asset%'");
		$qr2 = $db->query(3,"SELECT COUNT(*) AS total FROM `requisitions$cid` WHERE `status`='200'");
		$qr3 = $db->query(3,"SELECT COUNT(*) AS total FROM `advances$cid` WHERE `status`='200'");
		$qr4 = $db->query(3,"SELECT COUNT(*) AS total FROM `utilities$cid` WHERE `status`='200'");
		$ltp = ($qr1) ? intval($qr1[0]['total']):0; $req=($qr2) ? $qr2[0]['total']:0; $adv=($qr3) ? $qr3[0]['total']:0; $uts=($qr4) ? $qr4[0]['total']:0;
		if($db->istable(2,"staff_loans$cid")){
			$qri = $db->query(2,"SELECT COUNT(*) AS total FROM `staff_loans$cid` WHERE `status`='8'");
			$ltp+= ($qri) ? intval($qri[0]['total']):0;
		}
		
		$click1 = ($ltp) ? "loadpage('accounts/b2capp.php?disburse=loantemplate&cnl=$pbl')":"toast('There are no pending approved templates!')";
		$click2 = ($req) ? "loadpage('accounts/b2capp.php?disburse=requisition&cnl=$pbl')":"toast('There are no pending requisitions!')";
		$click3 = ($adv) ? "loadpage('accounts/b2capp.php?disburse=advance&cnl=$pbl')":"toast('There are no pending advances!')";
		$click4 = ($uts) ? "loadpage('accounts/b2capp.php?disburse=utility&cnl=$pbl')":"toast('There are no pending utilities!')";
		
		$utdiv = (in_array("disburse utility bills",$perms)) ? "<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
		<div class='jdiv' onclick=\"$click4\"><h3 style='font-size:17px;color:#191970'> <i class='bi-shop'></i>
			Vendor Payments ($uts)</h3><p style='margin-bottom:0px;font-size:14px'>Disburse approved Vendor Payments</p>
			</div>
		</div>":"";
		
		echo "<div class='cardv' style='max-width:800px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<div class='row'>
					<div class='col-12'><h3 style='font-size:22px;'>MPESA $pbl Platform $pbset</h3><hr></div>
					$baldiv $config
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$click1\"><h3 style='font-size:17px;color:#191970'> <i class='bi-journal-text'></i>
						Loan Applications ($ltp)</h3><p style='margin-bottom:0px;font-size:14px'>Disburse approved Loan applications</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$click2\"><h3 style='font-size:17px;color:#191970'> <i class='bi-gift'></i>
						Requisitions ($req)</h3><p style='margin-bottom:0px;font-size:14px'>Disburse approved requisitions</p>
						</div>
					</div> $utdiv
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$click3\"><h3 style='font-size:17px;color:#191970'> <i class='bi-file-earmark-person'></i>
						Salary Advances ($adv)</h3><p style='margin-bottom:0px;font-size:14px'>Disburse approved salary advances</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/b2capp.php?requests')\"><h3 style='font-size:17px;color:#191970'> 
						<i class='bi-box-arrow-up-right'></i> Initiated Requests</h3><p style='margin-bottom:0px;font-size:14px'>Review MPESA payment requests</p>
						</div>
					</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed MPESA Disbursement Platform");
	}
	
	# requests
	if(isset($_GET['requests'])){
		$me = staffInfo($sid); 
		$perms = getroles(explode(",",$me['roles']));
		
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='b2c_lock_days'");
		$dys = ($chk) ? intval($chk[0]['value']):1; $tdy=strtotime(date("Y-M-d"));
		if(!$db->istable(2,"b2c_trials$cid")){
			$db->createTbl(2,"b2c_trials$cid",["phone"=>"CHAR","name"=>"CHAR","amount"=>"INT","source"=>"TEXT","user"=>"INT","time"=>"INT","status"=>"INT"]); 
		}
		
		$dy=$tdy-($dys*86400); $trs=""; $no=0;
		$list = array("advance"=>"Salary Advances","requisition"=>"Requisitions","loantemplate"=>"Client Loans","utility"=>"Vendor Payments","staffloans"=>"Staff Loans",
		"salaries"=>"Payroll","reversal"=>"C2B Reversal");
		$db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`<$dy");
		
		$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$sql = $db->query(2,"SELECT *FROM `b2c_trials$cid` WHERE `time`>=$dy ORDER BY `id` DESC,`time` DESC");
		if($sql){
			foreach($sql as $row){
				$fon=$row['phone']; $name=prepare($row['name']); $amnt=fnum($row['amount']); $rid=$row['id']; $no++;
				$def = json_decode($row['source'],1); $src=$list[$def["src"]]; $tym=date("M-d,h:i a",$row['time']); 
				$dtp = (isset($def["dtp"])) ? strtoupper($def["dtp"]):"B2C"; $usa=$staff[$row['user']]; $dif=time()-$row['time'];
				$state = ($row['status']==200) ? "<span style='color:green'>Success</span>":"<span style='color:purple'>Pending</span>";
				$del = (($me['position']=="super user" or in_array("delete client payment",$perms)) && $row['status']<200 && $dif>100) ? "<br><a href='javascript:void(0)' 
				onclick=\"delreq('$rid','$fon')\" style='color:#ff4500'>Delete</a>":"";
				$trs.= "<tr id='$rid'><td>$no</td><td>$rid</td><td>$fon</td><td>$dtp</td><td>$name</td><td>$amnt</td><td>$usa</td><td>$src</td><td>$tym</td><td>$state $del</td></tr>";
			}
		}
		else{ $db->execute(2,"TRUNCATE TABLE `b2c_trials$cid`"); }
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped' style='width:100%;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'>
							<h3 style='font-size:22px;margin-bottom:10px;color:#191970'>$backbtn Initiated MPESA Requests
							<button class='bts' style='float:right' onclick=\"popupload('accounts/b2capp.php?xlsup')\"><i class='bi-upload'></i> Upload CSV</button></h3>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Tid</td><td>Recipient</td><td>Type</td><td>Recipient Name</td><td>Amount</td>
						<td>Initiator</td><td>Source</td><td>Time</td><td>Status</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
	}
	
	# upload disbursed
	if(isset($_GET['xlsup'])){
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>Confirm Disbursement</h3><br>
			<form method='post' id='uform' onsubmit='uploadisb(event)'>
				<p>Upload Excel file downloaded from safaricom B2C Portal to extract missed records</p>
				<p><input type='file' id='upxls' style='width:100%' accept='.xls,.xlsx' required></p><br>
				<p style='text-align:right'><button class='btnn'>Upload</button></p><br>
			</form>
		</div>";
	}
	
	# initiate disbursement
	if(isset($_GET['disburse'])){
		$src = trim($_GET['disburse']); 
		$cnl = trim($_GET['cnl']); 
		$trs=$data=""; $no=0; $phones=$def=[];
		$me = staffInfo($sid);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){ $names[$row['id']]=prepare(ucwords($row['name'])); $conts[$row['id']]=$row['contact']; }
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		$walds = sys_constants("wallets_disburse"); $wac=($sql) ? json_decode($sql[0]["value"],1):[]; $isacc=(isset($wac["Withdrawals"])) ? 1:0;
		$disopt = ($walds && $isacc) ? "<select style='width:190px;font-size:15px;padding:7px;cursor:pointer' name='distype'><option value='mpesa'>Disburse via MPESA</option>
		<option value='wallet'>Fund Wallet Accounts</option></select>":"<input type='hidden' name='distype' value='mpesa'>";
		
		if($src=="loantemplate"){
			$qri = $db->query(1,"SELECT *FROM `validations`");
			if($qri){
				foreach($qri as $row){ $phones[$row['phone']]=prepare(ucwords($row['name'])); }
			}
		
			$title = "Disburse approved Loans"; $nts=0; $tls=[];
			$cond = ($me["access_level"]=="hq") ? "":"AND `branch`='".$me['branch']."'"; $defc=$cond;
			$cond = ($me["access_level"]=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
			$defc = ($me["access_level"]=="portfolio") ? "AND `stid`='$sid'":$cond;
			$part = sys_constants("partial_disbursement");
			
			$res = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `status`='8' AND NOT `loantype` LIKE '%asset%' $cond ORDER BY `pref` ASC");
			if($res){
				foreach($res as $pos=>$row){
					$name=ucwords(prepare($row['client'])); $cont=$row['phone']; $amnt=$row['amount']; $cko=$row['checkoff']; $prep=$row['prepay'];
					$deds = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $ded=array_sum($deds); $apps=json_decode($row['approvals'],1); 
					$by=$names[$row['creator']]; $id=$row['id']; $tcut=$cko+$prep+$ded; $net=$amnt-$tcut; $prf=$row['pref']+$pos; $lis=$cls=""; $no++;
					$vname = (isset($phones[$cont])) ? $phones[$cont]:"NONE"; $nme=str_replace("'",'',$name); $dy=date("d-m-Y, H:i",$row['pref']);
					if($vname=="NONE"){ $def[]="254$cont"; $nts+=1; }
					
					foreach($apps as $uid){ $lis.= "<li>".$names[$uid]."</li>"; }
					foreach($deds as $key=>$sum){ $cls.="<li>".ucwords(str_replace("_"," ",$key))."-".fnum($sum)."</li>"; }
					$cls.= ($cko) ? "<li>Checkoff-".fnum($cko)."</li>":"";
					$cls.= ($prep) ? "<li>Prepayment-".fnum($prep)."</li>":"";
					$cls = ($cls) ? $cls:"None"; 
					if($part){
						$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; 
						$dn = ($dsb) ? array_sum($dsb):$tcut; $rem=$amnt-$dn; $min=($dsb) ? 0:$tcut; $click="updateamnt('$id',parseInt($('#am$id').val().trim()))";
						$mds = "<input type='number' style='width:100px;padding:4px' value='$rem' id='am$id' min='$min' max='$rem' placeholder='$min-$rem' 
						name='damnt[$id:$min-$rem]' onchange=\"updatelist('$id',this.value)\" required>";
					}
					else{ $click="updateamnt('$id','$net')"; $mds=fnum($net); }
					
					$tls[$prf] = "<tr valign='top' id='tr$id'><td>$no</td><td>$name</td><td>0$cont</td><td>$amnt</td><td>$cls</td><td>$lis</td><td>$dy</td>
					<td>$vname<br><a href=\"javascript:void(0)\" onclick=\"rejectemp('$id','$nme','client')\">Reject</a></td>
					<td><input type='checkbox' name='disto[254$cont:$id:cl]' id='c$id' value='$net' onclick=\"$click\"> &nbsp; $mds</td></tr>";
				}
			}
			
			# staff loans
			if($db->istable(2,"staff_loans$cid")){
				$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `status`='8' $defc ORDER BY `pref` ASC");
				if($res){
					foreach($res as $pos=>$row){
						$name=ucwords(prepare($row['staff'])); $cont=$row['phone']; $amnt=$row['amount']; $prf=$row['pref']+$pos; $lis=$cls=""; $no++;
						$deds = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $ded=array_sum($deds); $apps=json_decode($row['approvals'],1); 
						$id=$row['id']; $net=$amnt-$ded; $vname=(isset($phones[$cont])) ? $phones[$cont]:"NONE"; $nme=str_replace("'",'',$name);
						if($vname=="NONE"){ $def[]="254$cont"; $nts+=1; }
						
						foreach($apps as $uid=>$k){ $lis.= "<li>".$names[$uid]."</li>"; }
						foreach($deds as $key=>$sum){ $cls.="<li>".ucwords(str_replace("_"," ",$key))."-".fnum($sum)."</li>"; }
						$cls = ($cls) ? $cls:"None"; $dy=date("d-m-Y, H:i",$row['pref']); $ran=rand(100000,9999999);
						
						$tls[$prf] = "<tr valign='top' id='tr$id'><td>$no</td><td>$name</td><td>0$cont</td><td>$amnt</td><td>$cls</td><td>$lis</td><td>$dy</td>
						<td>$vname<br><a href=\"javascript:void(0)\" onclick=\"rejectemp('$id','$nme','staff')\">Reject</a></td>
						<td><input type='checkbox' name='disto[254$cont:$id:st]' id='c$ran' value='$net' onclick=\"updateamnt('$ran','$net')\"> &nbsp; ".fnum($net)."</td></tr>";
					}
				}
			}
			
			ksort($tls); $trs = implode("",$tls); 
			$gen = ($nts) ? "<span class='bts' style='cursor:pointer' onclick=\"popupload('accounts/b2capp.php?verify')\"><i class='bi-journal-check'></i> Verify Phones</span>":"";
			$submit = ($trs) ? "<caption><hr>$gen <button class='btnn' style='float:right;margin-left:10px'><i class='bi-check2-circle'></i> Disburse</button>
			<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
			placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
			<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>
			<span style='float:right;margin-right:10px'>$disopt</span></caption>":"";
			
			$data = "<input type='hidden' id='fons' value='".json_encode($def,1)."'>
			<table style='width:100%;min-width:800px;font-size:15px' class='table-striped' cellpadding='7'> $submit
				<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Applicant</td><td>Phone</td><td>Loan</td><td>Deductions</td>
				<td>Approvals</td><td>Approval-Date</td><td>MPESA Name</td><td>Disbursement</td></tr> $trs
			</table>";
		}
		elseif($src=="requisition"){
			$title = "Disburse Requisitions"; $brans[0]="Head Office";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
			foreach($qri as $row){$brans[$row['id']]=prepare(ucwords($row['branch'])); }
			$cond = ($me["access_level"]=="hq") ? "":"AND `branch`='".$me['branch']."'";
			
			$res = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `status`='200' $cond ORDER BY `time` ASC");
			if($res){
				foreach($res as $row){
					$from=$names[$row['staff']]; $fon="254".ltrim($conts[$row['staff']],"0"); 
					$bran=$brans[$row['branch']]; $id=$row['id']; $pay=$row['approved']; $lis=$des=""; $items=[];
					$day=date("d-m-Y",$row['time'])."<br>".date("h:i a",$row['time']);
					$list = json_decode($row['item_description'],1); unset($list["book"]);
					
					foreach($list as $key=>$item){ $items[$key]=$item['item']; }
					foreach(array_keys(json_decode($row['approvals'],1)[0]) as $stid){
						$lis.=(isset($names[$stid])) ? "<li>".$names[$stid]."</li>":""; 
					}
						
					foreach(json_decode($row['approvals'],1) as $pos=>$itms){
						$tname = prepare(ucfirst($items[$pos])); $mkey=@array_pop(array_keys($itms)); $ds=$itms[$mkey]; $qty=$ds[0]; $prc=$ds[1];
						$des.=($prc) ? "<li>$tname @Ksh ".fnum($prc)."</li>":"";
					}
							
					$trs.="<tr valign='top' id='tr$id'><td>$day</td><td>$bran</td><td>$from</td><td>$fon</td><td>$des</td><td>$lis</td><td>Ksh ".fnum($pay)."<br>
					<a href=\"javascript:void(0)\" onclick=\"popupload('accounts/b2capp.php?confreq=$id')\">Confirm Sent</a></td>
					<td><input type='checkbox' name='disto[$fon:$id]' id='c$id' value='$pay' onclick=\"updateamnt('$id','$pay')\"></checkbox><br>
					<a href=\"javascript:void(0)\" onclick=\"rejectreq('$id')\">Reject</a></td></tr>";
				}
			}
			
			$submit = ($trs) ? "<caption><hr><button class='btnn' style='float:right;margin-left:10px'><i class='bi-check2-circle'></i> Disburse</button>
			<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
			placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
			<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>
			<span style='float:right;margin-right:10px'>$disopt</span></caption>":"";
			
			$data = "<table style='width:100%;min-width:700px;font-size:15px' class='table-striped' cellpadding='7'> $submit
				<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td>Date</td><td>Branch</td><td>From</td><td>Contact</td>
				<td>Items</td><td>Approvals</td><td colspan='2'>Amount</td></tr> $trs
			</table>";
		}
		elseif($src=="utility"){
			$title = "Disburse Vendor Payments"; $brans[0]="Head Office";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
			foreach($qri as $row){$brans[$row['id']]=prepare(ucwords($row['branch'])); }
			$cond = ($me["access_level"]=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$dls = array("b2c"=>"B2C","b2b"=>"Paybill","till"=>"BuyGoods");
			
			$res = $db->query(3,"SELECT *FROM `utilities$cid` WHERE `status`='200' $cond ORDER BY `time` ASC");
			if($res){
				foreach($res as $row){
					$from=$names[$row['staff']]; $recip=json_decode($row['recipient'],1); $dtp=(isset($recip["mode"])) ? $recip["mode"]:"b2c";
					$dtk=($dtp=="b2b") ? "paybill":"tillno"; $fon=($dtp=="b2c") ? "254".$recip['phone']:$recip[$dtk]; $dty=($dtp=="b2b") ? "AccNo: ":"";
					$bran=$brans[$row['branch']]; $id=$row['id']; $pay=$row['approved']; $lis=$des=""; $items=[]; $dmt=$dls[$dtp];
					$day=date("d-m-Y",$row['time'])."<br>".date("h:i a",$row['time']); $fee=(isset($recip["fee"])) ? $recip["fee"]:0;
					$dname=(isset($recip["tname"])) ? "$dty".prepare(ucwords($recip["tname"])):""; $pay-=$fee; $dto=($dtp=="b2b") ? "Paybill:$fon":$fon;
					$arr = json_decode($row['approvals'],1); $apps=($arr) ? array_keys($arr[0]):[]; $mname="----";
					
					$ftx = ($fee) ? "<br><i style='font-size:14px;color:#008080'>Fees: $fee</i>":""; 
					if($dtp=="b2c"){
						$chk = $db->query(1,"SELECT `name` FROM `validations` WHERE `phone`='".$recip['phone']."'");
						if($chk){ $mname = prepare(ucwords($chk[0]["name"])); }
					}
					
					foreach(json_decode($row['item_description'],1) as $key=>$item){ $items[$key]=$item['item']; }
					foreach($apps as $stid){
						$lis.= (isset($names[$stid])) ? "<li>".$names[$stid]."</li>":""; 
					}
						
					foreach(json_decode($row['approvals'],1) as $pos=>$itms){
						$tname = prepare(ucfirst($items[$pos])); $mkey=@array_pop(array_keys($itms)); $ds=$itms[$mkey]; $qty=$ds[0]; $prc=$ds[1];
						$des.= ($prc) ? "<li>$tname @Ksh ".fnum($prc)."</li>":"";
					}
					
					$trs.= "<tr valign='top' id='tr$id'><td>$day</td><td>$bran</td><td>$from</td><td>$dmt</td><td>$dto<br>$dname</td><td>$mname</td><td>$des</td>
					<td>$lis</td><td>Ksh ".fnum($pay)."$ftx</td><td><input type='checkbox' name='disto[$fon:$id:ut:$dtp]' id='c$id' value='$pay' onclick=\"updateamnt('$id','$pay')\"></checkbox><br>
					<a href=\"javascript:void(0)\" onclick=\"rejectutil('$id')\">Reject</a></td></tr>";
				}
			}
			
			$submit = ($trs) ? "<caption><hr><button class='btnn' style='float:right;margin-left:10px'><i class='bi-check2-circle'></i> Disburse</button>
			<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
			placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
			<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>
			<span style='float:right;margin-right:10px'>$disopt</span></caption>":"";
			
			$data = "<table style='width:100%;min-width:700px;font-size:15px' class='table-striped' cellpadding='7'> $submit
				<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td>Date</td><td>Branch</td><td>Request From</td><td>Channel</td><td>Recipient</td><td>MPESA Name</td>
				<td>Items</td><td>Approvals</td><td colspan='2'>Amount</td></tr> $trs
			</table>";
		}
		elseif($src=="advance"){
			$title = "Disburse Pending Advances"; $totals=0;
			$res = $db->query(3,"SELECT *FROM `advances$cid` WHERE `status`='200' ORDER BY `time` ASC");
			if($res){
				foreach($res as $row){
					$staff=$names[$row['staff']]; $fon="254".$conts[$row['staff']]; $amnt=fnum($row['amount']); $cut=0;
					if(isset($row["charges"])){ $cut=($row["charges"]) ? array_sum(json_decode($row['charges'],1)):0; }
					$des=nl2br(prepare(ucfirst($row['reason']))); $pay=$row['approved']-$cut; $id=$row['id']; 
					$day=date("d-m-Y @ H:i",$row['time']); $totals+=$pay; $cont=$conts[$row['staff']]; $lis="";
					
					foreach(json_decode($row['approvals'],1) as $key=>$rid){
						$lis.="<li>".$names[$key]."</li>";
					}
					
					$ctx = ($cut) ? "<i style='color:#008080;font-size:14px'>Deducted ".fnum($cut)."</i>":"";
					$trs.= "<tr valign='top' id='tr$id'><td>$day</td><td>$staff<br>$fon</td><td>KES $amnt</td><td>$des</td><td>KES ".fnum($pay)."<br>$ctx</td><td>$lis</td>
					<td><a href=\"javascript:void(0)\" onclick=\"popupload('accounts/b2capp.php?rejectadv=$id&rfon=$cont')\">Reject</a><br>
					<a href=\"javascript:void(0)\" onclick=\"popupload('accounts/b2capp.php?confadv=$id')\">Confirm Paid</a></td><td>
					<input type='checkbox' name='disto[$fon:$id]' id='c$id' value='$pay' onclick=\"updateamnt('$id','$pay')\"></checkbox></td></tr>";
				}
			}
			
			$submit = ($trs) ? "<caption><hr><button class='btnn' style='float:right;margin-left:10px'><i class='bi-check2-circle'></i> Disburse</button>
			<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
			placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
			<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>
			<span style='float:right;margin-right:10px'>$disopt</span></caption>":"";
			
			$data = "<table style='width:100%;min-width:700px;font-size:15px' class='table-striped' cellpadding='7'> $submit
				<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td>Date</td><td>Staff</td><td>Request</td>
				<td>Explanation</td><td>Accepted</td><td>Approvals</td><td colspan='2'>Action</td></tr> $trs
			</table>";
		}
		
		$show = ($data) ? "<form method='post' id='dform' onsubmit=\"disburse(event)\">
			<input type='hidden' name='dfrom' value='$src'> <input type='hidden' id='totam' value='0'> <input type='hidden' name='pcnl' value='$cnl'> $data
		</form>":"";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;'>$backbtn $title <span style='float:right;color:#9400D3;font-weight:bold;font-size:18px' id='sumt'></span></h3>
				<div style='width:100%;overflow:auto'> $show </div>
			</div>
		</div>";
	}
	
	# reject advance
	if(isset($_GET['rejectadv'])){
		$rid = trim($_GET['rejectadv']);
		$fon = trim($_GET['rfon']);
		
		echo "<div style='margin:0 auto;max-width:300px;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Reject Salary Advance</h3><br>
			<form method='post' id='aform' onsubmit=\"rejectadv(event,'$rid')\">
				<input type='hidden' name='rejid' value='$rid'> <input type='hidden' name='rfon' value='$fon'>
				<p>Reason for rejecting staff salary advance<br><textarea name='rejres' class='mssg' placeholder='Type reason' style='min-height:120px' required></textarea></p><br>
				<p style='text-align:right'><button class='btnn'>Confirm</button></p><br>
			</form>
		</div>";
	}
	
	# confirm requisition was sent
	if(isset($_GET['confreq'])){
		$rid = trim($_GET['confreq']);
		$now = date("Y-m-d")."T".date("H:i");
		
		$sql = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `id`='$rid'"); $amnt=$sql[0]['approved'];
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND NOT `type`='expense'"); $opts="";
		if($qri){
			foreach($qri as $row){
				$opts.="<option value='".$row['id']."'>".prepare(ucfirst($row['account']))."</option>";
			}
		}
		
		echo "<div style='margin:0 auto;max-width:300px;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Confirm Requisition ".prenum($rid)."</h3><br>
			<form method='post' id='rform' onsubmit=\"confreq(event,'$rid')\">
				<input type='hidden' name='reqid' value='$rid'>
				<p>KES ".number_format($amnt)." was sent from<br><select name='reqacc' style='width:100%' required>$opts</select></p>
				<p>Sent Date<br><input type='datetime-local' name='postm' style='width:100%' value='$now' max='$now' required></p>
				<p>Sent Amount<br><input type='number' name='reqamnt' style='width:100%' value='$amnt' required></p><br>
				<p style='text-align:right'><button class='btnn'>Confirm</button></p><br>
			</form>
		</div>";
	}
	
	# confirm advance was sent
	if(isset($_GET['confadv'])){
		$rid = trim($_GET['confadv']);
		$now = date("Y-m-d")."T".date("H:i");
		
		$sql = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid'"); $amnt=$sql[0]['approved'];
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND NOT `type`='expense'"); $opts="";
		if($qri){
			foreach($qri as $row){
				$opts.="<option value='".$row['id']."'>".prepare(ucfirst($row['account']))."</option>";
			}
		}
		
		echo "<div style='margin:0 auto;max-width:300px;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Confirm Sent Advance</h3><br>
			<form method='post' id='adform' onsubmit=\"confadv(event,'$rid')\">
				<input type='hidden' name='advid' value='$rid'> <input type='hidden' name='adamnt' value='$amnt'>
				<p>KES ".number_format($amnt)." was sent from<br><select name='advacc' style='width:100%' required>$opts</select></p>
				<p>Sent Date<br><input type='datetime-local' name='postm' style='width:100%' value='$now' max='$now' required></p><br>
				<p style='text-align:right'><button class='btnn'>Confirm</button></p><br>
			</form>
		</div>";
	}
	
	# verify phones
	if(isset($_GET['verify'])){
		echo "<div style='margin:0 auto;max-width:300px;text-align:center'>
			<h3 style='color:#191970;font-size:22px;'>Verify Phone Numbers</h3><br>
			<p><button class='bts' onclick='genvalidation()'><i class='fa fa-file-text'></i> Generate XML</button><br>
			Generate XML File & then upload to MPESA org portal under <b>My Functions => Bulk => Create bulk Task plan</b> then select <b>
			Bulk payment validation</b> under Bulk type.</p><hr>
			<p><b>Upload CSV File</b><br>After uploading the XML File go to <b>Bulk Task Plan History</b>, locate your template then view under operation
			then download CSV from <b>basic info</b> tab</p>
			<form method='post' id='cvfom' onsubmit='uploadcsv(event)'>
				<p><input type='file' name='csv' id='csv' accept='.csv' required></p>
				<p style='text-align:right'><button class='btnn'><i class='fa fa-cloud-upload'></i> Upload</button></p>
			</form><br>
		</div>";	
	}
	
	# change pin & pass
	if(isset($_GET['security'])){
		$tp = trim($_GET['security']);
		$src = trim($_GET['psrc']);
		$txt = ($tp=="pin") ? "Secret Pin":"Mpesa Password";
		$max = ($tp=="pin") ? 6:30;
		$lock = ($tp=="pin") ? "onkeyup=\"valid('nk',this.value)\"":"";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Update $txt</h3><br>
			<form method='post' id='sform' onsubmit='changesec(event)'>
				<input type='hidden' name='cksec' value='$tp'> <input type='hidden' name='payrt' value='$src'> 
				<p>Enter Current Secret PIN<br><input type='text' name='cpin' id='pin' onkeyup=\"valid('pin',this.value)\" maxlength='6' style='width:100%' required></p>
				<p>Enter New $txt<br><input type='text' name='newkey' style='width:100%;' id='nk' $lock maxlength='$max' required></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p><br>
			</form>
		</div>";
	}
	
	ob_end_flush();
?>

<script>
	
	function rejectreq(rid){
		if(confirm("Sure to reject requisition no "+rid+"?")){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{rejectreq:rid},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+rid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function rejectutil(rid){
		if(confirm("Sure to reject utility expense no "+rid+"?")){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{rejectutil:rid},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+rid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function delreq(rid,fon){
		if(confirm("Remove "+fon+" from undisbursed List?")){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{delreq:rid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#"+rid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function rejectemp(tid,name,src){
		if(confirm("Sure to reject template for "+name+"?")){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{rejectemp:tid,tsrc:src},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+tid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function genvalidation(){
		if(confirm("Generate XML document from unvalidated phones?")){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{genxml:_("fons").value.trim()},
				beforeSend:function(){ progress("Generating...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					var loc = (window.location.hostname=="localhost") ? "/mfi/docs/":"/docs/";
					toast("Generated successfully!"); closepop(); 
					downloadoc(loc,res.trim().split(":")[1]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function uploadisb(e){
		e.preventDefault();
		var csv = _("upxls").files[0];
		if(csv==null){ _("upxls").click(); }
		else{
			if(confirm("Upload selected File?")){
				var data = new FormData();
				data.append("b2cdoc",csv);
				progress("Processing...please wait");
				var xhr = new XMLHttpRequest();
				xhr.onload = function(){ 
					if(this.responseText.trim()=="success"){
						progress(); loadpage("accounts/b2capp.php?requests"); closepop(); 
					}
					else{ progress(); alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/b2cops.php",true);
				xhr.send(data);
			}
		}
	}
	
	function uploadcsv(e){
		e.preventDefault();
		var csv = _("csv").files[0];
		if(csv==null){ _("csv").click(); }
		else{
			if(confirm("Upload selected File?")){
				var data = new FormData();
				data.append("csverify",csv);
				progress("Processing...please wait");
				var xhr = new XMLHttpRequest();
				xhr.onload = function(){
					if(this.responseText.trim()=="success"){
						progress(); loadpage("accounts/b2capp.php?disburse=loantemplate"); closepop();
					}
					else{ progress(); alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/b2cops.php",true);
				xhr.send(data);
			}
		}
	}
	
	function downloadoc(path,doc){
		var link = document.createElement("a");
		link.href = path+doc;
		link.download = doc;
		link.click();
	}
	
	function disburse(e){
		e.preventDefault();
		var amnt = _("totam").value.trim();
		var nfom = new Intl.NumberFormat("en-US",{style:"currency",currency:"KES"});
		if(amnt==0){ toast("Failed: No recipients selected!"); }
		else{
			if(confirm("Initiate disbursement of "+nfom.format(amnt)+"?")){
				var pin = prompt("Enter 6 Digit Secret PIN","******");
				if(pin){
					var data = $("#dform").serialize(); data+="&dpin="+pin;
					$.ajax({
						method:"post",url:path()+"dbsave/b2cops.php",data:data,
						beforeSend:function(){ progress("Processing...please wait"); },
						complete:function(){ progress(); }
					}).fail(function(){
						toast("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim().split(":")[0]=="success"){
							toast("Request sent for "+res.trim().split(":")[1]); window.location.reload();
						}
						else{ alert(res); }
					});
				}
			}
		}
	}
	
	function rejectadv(e,id){
		e.preventDefault();
		if(confirm("Sure to reject staff salary advance request?")){
			var data = $("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Confirmed successfully"); closepop(); $("#tr"+id).remove();
				}
				else{ alert(res); }
			});
		}
	}
	
	function confreq(e,id){
		e.preventDefault();
		if(confirm("Confirm requisition was sent from selected account?")){
			var data = $("#rform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Confirmed successfully"); closepop(); $("#tr"+id).remove();
				}
				else{ alert(res); }
			});
		}
	}
	
	function confadv(e,id){
		e.preventDefault();
		if(confirm("Confirm salary advance was sent from selected account?")){
			var data = $("#adform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Confirmed successfully"); closepop(); $("#tr"+id).remove();
				}
				else{ alert(res); }
			});
		}
	}
	
	function changesec(e){
		e.preventDefault();
		if(confirm("Sure to update security credentials?")){
			var data = $("#sform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully"); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function checkbal(payb){
		var pin = prompt("Enter 6 Digit Secret PIN","******");
		if(pin){
			$.ajax({
				method:"post",url:path()+"dbsave/b2cops.php",data:{checkbal:pin,payrt:payb},
				beforeSend:function(){ progress("Requesting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success"); loadpage("accounts/b2capp.php?home=2&pbl="+payb);  
				}
				else{ alert(res); }
			});
		}
	}
	
	const disb = [];
	function updateamnt(id,amnt){
		var formatter = new Intl.NumberFormat("en-US",{style:"currency",currency:"KES"}); 
		if(disb.hasOwnProperty(id)){
			if(!$("#c"+id).is(":checked")){ delete disb[id]; }
			var tot = disb.reduce((a,b) => Number(a)+Number(b),0);
			$("#sumt").html(formatter.format(tot)); _("totam").value=tot;
		}
		else{
			disb[id]=amnt;
			var tot = disb.reduce((a,b) => Number(a)+Number(b),0);
			$("#sumt").html(formatter.format(tot)); _("totam").value=tot;
		}
	}
	
	function updatelist(id,amnt){
		var formatter = new Intl.NumberFormat("en-US",{style:"currency",currency:"KES"}); 
		if($("#c"+id).is(":checked")){
			disb[id]=(amnt>0) ? amnt:0;
			var tot = disb.reduce((a,b) => Number(a)+Number(b),0);
			$("#sumt").html(formatter.format(tot)); _("totam").value=tot;
		}
	}
	
</script>