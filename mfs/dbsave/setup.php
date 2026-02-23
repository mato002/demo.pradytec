<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST["gasync"])){ exit(); }
	$sid = (isset($_POST["gasync"])) ? 0:substr(hexdec($_SESSION['myacc']),6);
	if($sid<1 && !isset($_POST["gasync"])){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	insertSqlite("photos","CREATE TABLE IF NOT EXISTS images (image TEXT UNIQUE NOT NULL,data BLOB)");
	
	# db async queries
	if(isset($_POST["gasync"])){
		$query = trim($_POST["gasync"]);
		$dbn = trim($_POST["dbn"]);
		$res = [];
		
		if(isset($_POST["token"])){
			$ref = trim($_POST["token"]); 
			$chk = fetchSqlite("tempdata","SELECT *FROM `tokens` WHERE `data`='$ref'"); 
			if(is_array($chk)){
				if($chk[0]['data']==$ref){
					$sql = $db->query($dbn,$query); $res=($sql) ? $sql:[];
				}
			}
		}
		echo json_encode($res,1);
	}
	
	# save interest vars
	if(isset($_POST['pvar'])){
		$name = trim($_POST['pvar']);
		$days = $_POST['pday'];
		$intr = $_POST['pchaj'];
		
		if($name=="none"){ echo "Error! Select range category first!"; }
		else{
			foreach($days as $key=>$val){ $data[$val]=$intr[$key]; }
			if($db->execute(1,"UPDATE `settings` SET `value`='".json_encode($data,1)."' WHERE `client`='$cid' AND `setting`='$name'")){
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# save high level approval
	if(isset($_POST['hlevels'])){
		$data = $_POST['hlevels'];
		$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
		
		foreach($data as $key=>$val){
			$tp = explode(".",$key)[0]; $lv = explode(".",$key)[1];
			$def[$tp][$lv]=$val;
		}
		
		$apps = json_encode($def,1);
		$qry = ($res) ? "UPDATE `approvals` SET `levels`='$apps' WHERE `client`='$cid' AND `type`='superlevels'":
		"INSERT INTO `approvals` VALUES(NULL,'$cid','superlevels','$apps')";
		if($db->execute(1,$qry)){
			savelog($sid,"Updated settings for High level approvals for management");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save loan limit settings
	if(isset($_POST['limstate'])){
		$state = trim($_POST['limstate']);
		$order = $_POST['order'];
		$cycles = $_POST['cycles'];
		
		foreach($order as $pid=>$oid){ $data[$pid]=array("cycles"=>$cycles[$pid],"order"=>$oid); }
		$res = $db->query(1,"SELECT *FROM `loan_limits` WHERE `client`='$cid'"); $des = json_encode($data,1);
		$query = ($res) ? "UPDATE `loan_limits` SET `setting`='$des',`status`='$state' WHERE `client`='$cid'":
		"INSERT INTO `loan_limits` VALUES(NULL,'$cid','$des','[]','$state')";
		
		if($db->execute(1,$query)){
			savelog($sid,"Updated Loan Limit settings");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save limit bypass
	if(isset($_POST['limbypass'])){
		$list = json_encode($_POST['limbypass']);
		$res = $db->query(1,"SELECT *FROM `loan_limits` WHERE `client`='$cid'");
		$query = ($res) ? "UPDATE `loan_limits` SET `bypass`='$list' WHERE `client`='$cid'":"INSERT INTO `loan_limits` VALUES(NULL,'$cid','[]','$list','0')";
		if($db->execute(1,$query)){
			savelog($sid,"Updated staff loan limit bypass settings");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later!"; }
	}
	
	# save system access restriction settings
	if(isset($_POST['systime'])){
		$sets = json_encode($_POST["systime"],1);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='systime' AND `client`='$cid'");
		$query = ($res) ? "UPDATE `settings` SET `value`='$sets' WHERE `setting`='systime' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','systime','$sets')";
		if($db->execute(1,$query)){
			savelog($sid,"Updated system access restriction settings");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save groups to require login OTP
	if(isset($_POST['otpreq'])){
		$grps = json_encode(explode(",",rtrim(trim($_POST['otpreq']),",")));
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='otpfor' AND `client`='$cid'");
		$query = ($res) ? "UPDATE `settings` SET `value`='$grps' WHERE `setting`='otpfor' AND `client`='$cid'":
		"INSERT INTO `settings` VALUES(NULL,'$cid','otpfor','$grps')";
		if($db->execute(1,$query)){
			savelog($sid,"Updated staff groups to require Login OTP");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save requisition application groups
	if(isset($_POST['savereqn'])){
		$grps = json_encode(explode(",",rtrim(trim($_POST['savereqn']),",")));
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='requisitionby' AND `client`='$cid'");
		$query = ($res) ? "UPDATE `settings` SET `value`='$grps' WHERE `setting`='requisitionby' AND `client`='$cid'":
		"INSERT INTO `settings` VALUES(NULL,'$cid','requisitionby','$grps')";
		if($db->execute(1,$query)){
			savelog($sid,"Updated staff groups to apply requisition");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save rank settings
	if(isset($_POST['perfset'])){
		foreach($_POST["perfset"] as $set=>$val){
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='$set' AND `client`='$cid'");
			$query = ($res) ? "UPDATE `settings` SET `value`='$val' WHERE `setting`='$set' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','$set','$val')";
			$db->execute(1,$query);
		}
		
		savelog($sid,"Updated performance control settings");
		echo "success";
	}
	
	# save KPIs
	if(isset($_POST['kpis'])){
		foreach($_POST['kpis'] as $key=>$one){
			if(!isset($one["rank"])){ $_POST["kpis"][$key]["rank"]=0; }
			if(!isset($one["target"])){ $_POST["kpis"][$key]["target"]=0; }
			if($key=="loanprods" && isset($_POST["lclust"])){ $_POST["kpis"][$key]["list"]=$_POST["lclust"]; }
		}
		
		$save = json_encode($_POST["kpis"],1);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='perfkpis' AND `client`='$cid'");
		$query = ($res) ? "UPDATE `settings` SET `value`='$save' WHERE `setting`='perfkpis' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','perfkpis','$save')";
		if($db->execute(1,$query)){
			savelog($sid,"Updated Targets & Ranking inclusion parameters");
			echo "success";
		}
		else{ echo "Failed to update! Try again later"; }
	}
	
	# save leave category
	if(isset($_POST['lcid'])){
		$lid = trim($_POST['lcid']);
		$cat = clean(strtolower($_POST['catname']));
		$satfro = trim($_POST['satfro']); $satto=trim($_POST['satto']);
		$sunfro = trim($_POST['sunfro']); $sunto=trim($_POST['sunto']);
		
		foreach(array("catname","lcid","satfro","satto","sunfro","sunto") as $post){
			unset($_POST[$post]); 
		}
		
		$sun=($sunfro && $sunto) ? "$sunfro-$sunto":"Off"; $_POST['saturday']="$satfro-$satto"; $_POST['sunday']=$sun;
		$data = json_encode($_POST,1); 
		
		$check = $db->query(1,"SELECT *FROM `leave_settings` WHERE `leave_type`='$cat' AND `client`='$cid' AND NOT `id`='$lid'");
		if($check){
			echo "Failed: Leave category already exists";
		}
		else{
			$query = ($lid) ? "UPDATE `leave_settings` SET `leave_type`='$cat',`setting`='$data' WHERE `id`='$lid'":
			"INSERT INTO `leave_settings` VALUES(NULL,'$cid','$cat','$data')";
			
			if($db->execute(1,$query)){
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment!"; }
		}
	}
	
	# save loan product/asset category
	if(isset($_POST['loanprod'])){
		$lid = trim($_POST['loanprod']);
		$cat = trim($_POST['prodcat']);
		$prod = clean(strtolower($_POST['prod']));
		$dur = clean($_POST['ldur']);
		$intv = clean($_POST['pdays']);
		$min = clean($_POST['minam']);
		$max = clean($_POST['maxam']);
		$intrtp = clean($_POST['intrtp']);
		$intdur = clean($_POST['intdur']);
		$offees = clean($_POST['offee']);
		$rollfee = clean($_POST['rollfee']);
		$penalty = clean($_POST['penalty']);
		$penam = clean($_POST['penamt']);
		$waive = intval($_POST['waiver']);
		$lnapp = clean($_POST['lnapp']);
		$instd = clean($_POST['instds']);
		$cskip = clean($_POST['cskip']);
		$cluster = ucwords(str_replace("_"," ",prepstr($_POST['prdclust'])));
		$charges = (isset($_POST['charges'])) ? $_POST['charges']:[];
		$desc = (isset($_POST['cdesc'])) ? $_POST['cdesc']:[];
		$fees = (isset($_POST['fees'])) ? $_POST['fees']:[]; $deducts=[];
		$int = clean($_POST['intr']);
		$intr = (isset($_POST['varint']) && !$int) ? trim($_POST['varint']):$int;
		$ptp = ($cat=="asset") ? "Asset category":"Loan product";
		
		foreach($desc as $key=>$des){
			if($charges[$key]!=15){
				$deducts[prepstr($des)] = ($charges[$key]==3) ? "$charges[$key]:$fees[$key]:".trim($_POST[$key]):"$charges[$key]:$fees[$key]";
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `product`='$prod' AND `category`='$cat' AND NOT `id`='$lid' AND `status`='0'");
		if($res){ echo "Failed: $ptp ".$_POST['loanprod']." is already in the system"; }
		elseif($intv>$dur){ echo "Error! Repayment duration must be less than payment Interval"; }
		elseif(fmod($dur,$intv)){ echo "Error! Loan duration of $dur days must be divisible by interval of $intv days"; }
		elseif($intdur>$dur){ echo "Error! Loan duration must be <= interest duration"; }
		elseif(fmod($dur,$intdur)){ echo "Error! Loan duration of $dur days must be divisible by interest duration"; }
		elseif($min>$max){ echo "Error! Minimum amount must be less than maximum amount"; }
		elseif(!$intr){ echo "Invalid total Interest $intr!"; }
		elseif($intr=="none"){ echo "Please select Interest range first!"; }
		elseif(count(explode("%",$intr))<2 && $intrtp=="RB"){ echo "Error! Interest for reducing balance should only be percentage"; }
		else{
			if($lid){ $pdf = json_decode($db->query(1,"SELECT `pdef` FROM `loan_products` WHERE `id`='$lid'")[0]["pdef"],1); }
			$new = ["intrtp"=>$intrtp,"offees"=>$offees,"rollfee"=>$rollfee,"allow_multiple"=>$lnapp,"repaywaiver"=>$waive,"showinst"=>$instd,"cluster"=>$cluster,"skip_checkoff"=>$cskip];
			foreach($new as $k=>$v){ $pdf[$k]=$v; }
			
			$def = json_encode($pdf,1); $payt=json_encode($deducts,1); $penal="$penalty:$penam"; 
			$qry = ($lid) ? "UPDATE `loan_products` SET `product`='$prod',`minamount`='$min',`maxamount`='$max',`duration`='$dur',`intervals`='$intv',
			`interest`='$intr',`interest_duration`='$intdur',`penalty`='$penal',`payterms`='$payt',`pdef`='$def' WHERE `id`='$lid'":
			"INSERT INTO `loan_products` VALUES(NULL,'$cid','$prod','$cat','$min','$max','$dur','$intv','$intdur','$intr','$penal','$payt','$def','0')";
			if($db->execute(1,$qry)){
				$txt = ($lid) ? "Updated":"Created new";
				savelog($sid,"$txt Loan product $prod");
				echo "success"; 
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# delete loan product
	if(isset($_POST['delprod'])){
		$pid = explode(":",trim($_POST['delprod'])); $id=$pid[1];
		$tbl = ($pid[0]=="client") ? "org$cid"."_loans":"staff_loans$cid";
		$tbl = ($pid[0]=="asset") ? "finassets$cid":$tbl;
		$pname = $db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='$pid'")[0]["product"];
		
		if($db->istable(2,$tbl)){
			$col = ($pid[0]=="asset") ? "asset_category":"loan_product";
			$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `$col`='$id'");
			if($res){
				$db->execute(1,"UPDATE `loan_products` SET `status`='1' WHERE `id`='$id'");
			}
			else{ $db->execute(1,"DELETE FROM `loan_products` WHERE `id`='$id'"); }
		}
		else{ $db->execute(1,"DELETE FROM `loan_products` WHERE `id`='$id'"); }
		
		savelog($sid,"Deleted loan product ".prepare($pname));
		echo "success";
	}
	
	#save prepay week
	if(isset($_POST['pfrom'])){
		$from=strtotime(trim($_POST['pfrom']));
		$to=strtotime(trim($_POST['pto']));
		$pid=trim($_POST['prepid']);
		$sid=trim($_POST['sid']);
		
		$res = $db->query(1,"SELECT *FROM `prepaydays` WHERE `fromdate`='$from' AND `todate`='$to' AND `client`='$cid' AND NOT `id`='$pid'");
		
		if($to<$from){ echo "Failed: To date must be greater than From day"; }
		elseif($res){ echo "Failed: Prepayment week already exists"; }
		else{
			$query=($pid) ? "UPDATE `prepaydays` SET `fromdate`='$from',`todate`='$to' WHERE `id`='$pid'":
			"INSERT INTO `prepaydays` VALUES(NULL,'$cid','$from','$to')";
			if($db->execute(1,$query)){
				echo "success";
				savelog($sid,"Created prepayment schedule from ".date("d-m-Y",$from)." to ".date("d-m-Y",$to));
			}
			else{ echo "Failed: Unkown Error occured!"; }
		}
	}
	
	#delete record
	if(isset($_POST['delrecord'])){
		$id=trim($_POST['delrecord']);
		$dtbl=explode(":",trim($_POST['dtbl']));
		$tbl = (count($dtbl)>1) ? $dtbl[1]:$dtbl[0];
		$dbn = (count($dtbl)>1) ? $dtbl[0]:1; 
		$pos = (count($dtbl)>1) ? 1:0;
		$dbn = (substr($dtbl[$pos],0,3)=="org" or in_array($tbl,["client_leads$cid"])) ? 2:$dbn;
		
		if($tbl == "overpayments$cid"){ $opay = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$id'")[0]; }
		if($tbl=="org$cid"."_loantemplates"){
			$res = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$id'")[0];
		}
		
		if($db->execute($dbn,"DELETE FROM `$tbl` WHERE `id`='$id'")){
			if($tbl == "overpayments$cid"){
				bookbal(DEF_ACCS['overpayment'],"-".$opay["amount"]); $pid=$opay["payid"];
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
				savelog($sid,"Deleted Overpayment transaction of Ksh ".$opay["amount"]." originated from parent transaction ".$qri[0]["code"]);
			}
			if($tbl == "org".$cid."_loantemplates"){
				$db->execute(2,"DELETE FROM `org".$cid."_prepayments` WHERE `template`='$id'");
				if($res["payment"]){
					if(substr($res["payment"],0,6)=="WALLET"){
						$get = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='".$res["client_idno"]."'");
						if($get){
							updateWallet($get[0]['id'],array_sum(json_decode($res["processing"],1)),"client","Payment #".$res["payment"]." Reversal",0);
							$db->execute(2,"DELETE FROM `org$cid"."_payments` WHERE `code`='".$res["payment"]."'");
						}
					}
				}
				if($res["status"]>200){
					$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='CLOAN$id'");
					if($chk){
						$rev = $chk[0]['reversal']; $tid=$chk[0]['transid'];
						$des = (in_array($rev,["default","auto"])) ? "default":json_decode($rev,1);
						reversetrans($tid,$des,$sid);
					}
				}
				savelog($sid,"Deleted Loan application for ".ucwords($res["client"])." of Ksh ".fnum($res["amount"]));
			}
			
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment"; }
	}
	
	# save app settings
	if(isset($_POST["awardst"])){
		$state = trim($_POST["awardst"]);
		$aplim = trim($_POST["applimit"]);
		$aptop = trim($_POST["apptop"]);
		$terms = htmlentities(htmlentities(trim($_POST['lterms'])),ENT_QUOTES);
		$set = json_encode($_POST["awards"],1);
		
		foreach(array("application_status"=>$state,"client_awarding"=>$set,"app_loanterms"=>$terms,"app_limit_from_previous"=>$aplim,"use_app_as_topup"=>$aptop) as $set=>$val){
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='$set'");
			$qry = ($chk) ? "UPDATE `settings` SET `value`='$val' WHERE `setting`='$set' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','$set','$val')";
			$db->execute(1,$qry);
		}
		
		echo "success";
		savelog($sid,"Updated client award settings");
	}
	
	# save constants
	if(isset($_POST["sconst"])){
		$const = json_encode($_POST["sconst"],1);
		$setts = $_POST["setts"];
		$setts["system_constants"]=$const;
		
		foreach($setts as $set=>$val){
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='$set'");
			$qry = ($chk) ? "UPDATE `settings` SET `value`='$val' WHERE `setting`='$set' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','$set','$val')";
			$db->execute(1,$qry);
		}
		
		echo "success";
		savelog($sid,"Updated client system constant settings");
	}
	
	# save approvals
	if(isset($_POST['approvals'])){
		$apps = $_POST['approvals']; 
		$type = trim($_POST['apptype']);
		$stid = trim($_POST['notify']);
		$data=[]; $error="";
		
		if($type=="leave"){
			foreach($apps as $grp=>$val){
				if(!$val){ $error = "Failed: You havent set approval 1 for ".ucwords(prepare($grp)); }
			}
			
			if($error){ echo $error; exit(); }
			$data = array(1=>$apps,2=>$_POST["appr2"]);
		}
		else{
			foreach($apps as $key=>$val){
				if(!in_array($val,$data)){ $data[$key+1]=$val; }
			}
		}
		
		$save = json_encode($data,1); 
		$arr = array("loantemplate"=>"Client Loan templates","leave"=>"Leave application","advance"=>"Advance application","requisition"=>"Requisition",
		"utilities"=>"Utility Expenses","stafftemplate"=>"Staff Loan templates","budget"=>"Budget reports");
		
		$check = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='$type'");
		$check2 = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='$type'");
		$query = ($check) ? "UPDATE `approvals` SET `levels`='$save' WHERE `client`='$cid' AND `type`='$type'":"INSERT INTO `approvals` VALUES(NULL,'$cid','$type','$save')";
		$query2 = ($check2) ? "UPDATE `approval_notify` SET `staff`='$stid' WHERE `client`='$cid' AND `approval`='$type'":
		"INSERT INTO `approval_notify` VALUES(NULL,'$cid','$type','$stid')";
		
		if($db->execute(1,$query)){
			$db->execute(1,$query2); $add=($type=="leave") ? "":"to ".implode(" -> ",$data);
			savelog($sid,"Updated $arr[$type] approval levels $add");
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment"; }
	}
	
	# save settings 
	if(isset($_POST['savesetting'])){
		$data = explode(":",trim($_POST['savesetting']));
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$data[0]."'");
		$val = $data[1]; $sett=$data[0]; $val=($sett=="autosms_time") ? $data[1].":".$data[2]:$val;
		if($data[0]=="login"){ $val=($res) ? $res[0]['value']:0; $val=($val) ? 0:1; }
		if($data[0]=="closing_loan_fees"){
			if($data[1]==null or $data[1]==""){ echo "Invalid Value!"; exit(); }
		}
		
		$query = ($res) ? "UPDATE `settings` SET `value`='$val' WHERE `client`='$cid' AND `setting`='$sett'":"INSERT INTO `settings` VALUES(NULL,'$cid','$sett','$val')";
		$db->execute(1,$query); 
		
		if($sett=="allow_staff_loans" && $val){
			if(!$db->istable(2,"staff_loans$cid")){
				$def = ["stid"=>"INT","staff"=>"CHAR","loan_product"=>"CHAR","phone"=>"INT","branch"=>"INT","amount"=>"INT","duration"=>"INT","disbursement"=>"INT","expiry"=>"INT",
				"penalty"=>"INT","paid"=>"INT","balance"=>"INT","loan"=>"CHAR","status"=>"INT","approvals"=>"TEXT","pref"=>"INT","payment"=>"CHAR","processing"=>"TEXT",
				"cuts"=>"TEXT","loantype"=>"CHAR","creator"=>"INT","time"=>"INT"];
				$db->createTbl(2,"staff_schedule$cid",["stid"=>"INT","loan"=>"CHAR","month"=>"INT","day"=>"INT","amount"=>"INT","interest"=>"INT","paid"=>"INT","balance"=>"INT",
				"breakdown"=>"TEXT","payments"=>"TEXT"]);
				$db->createTbl(2,"staff_loans$cid",$def);
			}
		}
		
		$types = array("login"=>"OTP Settings","officers"=>"Dedicated staff","checkoff"=>"Checkoff","loansegment"=>"Loan duration segmentaion","loanposting"=>"Disbursed Loans posting",
		"reschedule"=>"Loan Re-schedule","reschedulefees"=>"Reschedule Fees","rescheduledays"=>"Reschedule days","docprotection"=>"Document Protection",
		"autoapprovepays"=>"Payment automation","receiptlogo"=>"Logo inclusion on receipts","protectpass"=>"Document opening password","applyarrears"=>"Penalty application",
		"smsamount"=>"Installment reminder SMS","leavedays"=>"Leave days","probation"=>"New staff probation","advanceapplication"=>"Advance application",
		"applyrequisition"=>"Requisition application","addfloat"=>"Manually Topup float","installmentdate"=>"Installment reminder Date","loan_approval_sms"=>"Approvie notification",
		"updatepayroll"=>"Payroll update settings","clientdormancy"=>"Client dormancy duration","passreset"=>"Staff Pasword reset","loan_approval_otp"=>"Loan approval OTP reuirement",
		"template_phone_include"=>"Phone number editing in template creation","autoisntallment_sms"=>"Automatic installment reminder SMS","autosms_time"=>"Automatic SMS time",
		"loantype_option"=>"Selection of Loan Type on loantemplate","multidisburse"=>"Manually confirming Disbursed Loans","show_coll_rates"=>"Display collection rates",
		"allow_staff_loans"=>"Allow staff loan applications","intrcollectype"=>"Interest Collection type","closing_loan_fees"=>"Loan closure Fees",
		"guarantor_consent_phone"=>"Guarantor Consent OTP Phone","guarantor_consent_by"=>"Guarantor consent upon loan application","threshold_approver"=>"Loan special amounts approver");
		
		savelog($sid,"Updated $types[$sett] Settings");
		echo "Updated successfully";
	}
	
	# update advance settings
	if(isset($_POST['advancebypasslim'])){
		$data = $_POST; $setts=[];
		$data['advanceagreement']=clean($_POST['advanceagreement']);
		$data["charge_advance"]=trim($_POST["intrchaj"]).":".trim($_POST["intrval"]);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){ foreach($res as $row){ $setts[]=$row['setting']; }}
		
		unset($data["intrchaj"],$data["intrval"]);
		foreach($data as $key=>$val){
			$query = (in_array($key,$setts)) ? "UPDATE `settings` SET `value`='$val' WHERE `setting`='$key' AND `client`='$cid'":
			"INSERT INTO `settings` VALUES(NULL,'$cid','$key','$val')";
			$db->execute(1,$query);
		}
		
		savelog($sid,"Updated salary advance settings");
		echo "success";
	}
	
	# save reminder sms template
	if(isset($_POST['smsmssg'])){
		$sms = clean(ucfirst($_POST['smsmssg']));
		$ck = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND `name`='inst200'");
		$query = ($ck) ? "UPDATE `sms_templates` SET `message`='$sms' WHERE `client`='$cid' AND `name`='inst200'":
		"INSERT INTO `sms_templates` VALUES(NULL,'$cid','inst200','$sms')";
		echo ($db->execute(1,$query)) ? "Successfully updated":"Failed to complete the request at the moment";
	}
	
	# create/update group & permissions
	if(isset($_POST['userole'])){
		$gid = trim($_POST['userole']);
		$name = clean($_POST['gname']);
		$roles = (isset($_POST["roles"])) ? implode(",",$_POST['roles']):"6,9,22,25";
		
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' AND `sgroup`='$name' AND NOT `id`='$gid'");
		if($res or in_array(strtolower($name),["assistant","collection agent","ussd"])){ echo "Failed: Staff group already exist"; }
		else{
			$sql = $db->query(1,"SELECT *FROM `staff_groups` WHERE `id`='$gid'"); $from=($sql) ? $sql[0]['sgroup']:"";
			$query = ($gid) ? "UPDATE `staff_groups` SET `sgroup`='$name',`roles`='$roles' WHERE `id`='$gid'":"INSERT INTO `staff_groups` VALUES(NULL,'$cid','$name','$roles')";
			if($db->execute(1,$query)){
				if($gid){
					$db->execute(2,"UPDATE `org".$cid."_staff` SET `position`='$name',`roles`='$roles' WHERE `position`='$from'");
					$db->execute(1,"UPDATE `settings` SET `value`='$name' WHERE `setting`='officers' AND `client`='$cid' AND `value`='$from'");
					$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `status`='0' AND NOT `position`='assistant'");
					foreach($qri as $row){
						$cnf = json_decode($row["config"],1); 
						if(isset($cnf["mypost"])){
							if(isset($cnf["mypost"][$from])){
								$access = $cnf["mypost"][$from]; 
								if($name!=$from){ unset($cnf["mypost"][$from]); }
								$cnf["mypost"][$name]=$access; $jsn=json_encode($cnf,1); $rid=$row['id'];
								$db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='$jsn' WHERE `id`='$rid'");
							}
						}
					}
					
					$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid'");
					if($qry && $from!=$name){
						foreach($qry as $row){
							$apps=json_decode($row['levels'],1); $rid=$row['id']; $data=[];
							if($row["type"]=="leave"){
								foreach($apps as $no=>$one){
									if(in_array($from,$one)){
										foreach($one as $key=>$app){ $data[$no][$key]=($app==$from) ? $name:$app; }
									}
								}
								$db->execute(1,"UPDATE `approvals` SET `levels`='".json_encode($data,1)."' WHERE `id`='$rid'");
							}
							else{
								if(in_array($from,$apps)){
									foreach($apps as $key=>$app){ $data[$key]=($app==$from) ? $name:$app; }
									$db->execute(1,"UPDATE `approvals` SET `levels`='".json_encode($data,1)."' WHERE `id`='$rid'");
								}
							}
						}
					}
				}
				
				$txt = ($gid) ? "Updated":"Created";
				savelog($sid,"$txt staff group $name & permissions");
				echo "success";
			}
			else{ echo "Failed: Unkown error!"; }
		}
	}
	
	# save group Settings
	if(isset($_POST["cgrpid"])){
		$gid = trim($_POST["cgrpid"]);
		$sets = json_encode($_POST["grpset"],1); $error="";
		
		$mins = array("minmembers"=>[1,"Group members"]);
		foreach($mins as $col=>$val){
			if($_POST["grpset"][$col]<$val[0]){ $error = "Minimum ".$val[1]." is ".$val[0]; }
		}
		
		if($error){ echo "Error: $error"; exit(); }
		if($gid){
			$chk = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
			$def = json_decode($chk[0]["def"],1); $def["sets"]=json_decode($sets,1);
			if($db->execute(2,"UPDATE `cgroups$cid` SET `def`='".json_encode($def,1)."' WHERE `id`='$gid'")){
				savelog($sid,"Updated client loan group ".$chk[0]["group_name"]." settings");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='client_groupset'");
			$query = ($chk) ? "UPDATE `settings` SET `value`='$sets' WHERE `client`='$cid' AND `setting`='client_groupset'":
			"INSERT INTO `settings` VALUE(NULL,'$cid','client_groupset','$sets')";
			if($db->execute(1,$query)){
				savelog($sid,"Updated client loan group settings");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# save table
	if(isset($_POST['tablename'])){
		$dtb = explode(":",trim($_POST['tablename']));
		$def = json_decode(trim($_POST['deftbl']),1);
		$dtypes = array_merge(array_values($def),$_POST['datatypes']);
		$fnms = array_map("prepstr",$_POST['fnames']);
		$names = array_merge(array_keys($def),$fnms);
		$selecs = (isset($_POST['selects'])) ? $_POST['selects']:array();
		$linked = (isset($_POST['linkto'])) ? $_POST['linkto']:array();
		$replace = array("number"=>"INT","textarea"=>"TEXT");
		$reuse = (isset($_POST["reuse"])) ? $_POST['reuse']:[];
		$tbl = prepstr($dtb[0]); $dbn=$dtb[1]; $rec=$fields=[];
		
		foreach($names as $key=>$fname){
			if(!isset($fields[$fname])){
				$dtype = $dtypes[$key]; $fields[$fname]=$dtype; 
				$skey = array_search($fname,$fnms); $skey=($skey) ? $skey:$key;
				$dtp = (isset($replace[$dtype])) ? $replace[$dtype]:"CHAR"; $cfields[$fname]=$dtp; 
				$src[$fname]=(isset($selecs[$skey])) ? "select:".strtolower(clean($selecs[$skey])):"input";
				if(isset($linked[$skey])){ $src[$fname]="tbl:".$linked[$skey]; }
				if(isset($reuse[$skey])){ $rec[]=$fname; }
			}
		}
		
		$changes = [];
		foreach($_POST['prevtbl'] as $key=>$fld){
			if(array_key_exists($key,$fnms)){
				if($fld!=$fnms[$key]){ $changes[$fld]=$fnms[$key]; }
			}
		}
		
		$tfields = (count($changes)) ? $cfields+array("tchanged"=>$changes):$cfields;
		$flds = json_encode($fields,1); $dsrc=json_encode($src,1); $reuse=json_encode($rec,1);
		
		$check = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'");
		if($res=$db->createTbl($dbn,$tbl,$tfields)){
			if($tbl=="org".$cid."_loans"){
				$cols = $fields;
				$adds = ["comments"=>"textarea","loantype"=>"text","pref"=>"number","payment"=>"text","processing"=>"textarea","checkoff"=>"number","prepay"=>"number","cuts"=>"textarea"];
				$dels = array("balance","expiry","penalty","disbursement","paid","tid","clientdes");
				foreach($adds as $key=>$add){ $cols[$key]=$add; }
				$replace = array("number"=>"INT","textarea"=>"TEXT");
				foreach($cols as $fname=>$dtype){
					if(!in_array($fname,$dels)){
						$dtp = (isset($replace[$dtype])) ? $replace[$dtype]:"CHAR"; $nfields[$fname]=$dtp; 
					}
				}
				$db->createTbl(2,"org".$cid."_loantemplates",$nfields);
			}
				
			if($check){ $db->execute(1,"UPDATE `created_tables` SET `fields`='$flds',`datasrc`='$dsrc',`reuse`='$reuse' WHERE `client`='$cid' AND `name`='$tbl'"); }
			else{ $db->execute(1,"INSERT INTO `created_tables` VALUES(NULL,'$cid','$tbl','$flds','$dsrc','$reuse')"); }
			echo "success";
		}
		else{ echo "Failed to complete the process at the moment!".$res; }
	}
	
	# change logo
	if(isset($_FILES['logo'])){
		$sid = trim($_GET['usa']);
		$tmp = $_FILES['logo']['tmp_name'];
		$ext = @strtolower(array_pop(explode(".",strtolower($_FILES['logo']['name']))));
		$get = array("png","jpg","jpeg");
		
		if(@getimagesize($tmp)){
			if(in_array($ext,$get)){
				$newname = 'Logo-'.date('mdHis').'.'.$ext;
				$save = "../../docs/img/".$newname;
				$img = 'Temp-'.date('mdHis').'.'.$ext;
				$config = $update = mficlient();
				$update['logo']=$newname; $log="../../docs/img/$img";
				
				if(move_uploaded_file($tmp,$log)){
					crop($log,350,350,$save);
					cropSQ($log,248,"../../docs/img/prop.$ext");
					cropSQ($log,100,"../../docs/img/favicon.ico");
					$db->execute(1,"UPDATE `config` SET `settings`='".json_encode($update,1)."' WHERE `client`='$cid'");
					insertSqlite("photos","REPLACE INTO `images` VALUES('$newname','".base64_encode(file_get_contents($save))."')");
					unlink($save); unlink($log); 
					savelog($sid,"Updated company Logo"); 
					echo "success";
				}
				else{
					echo "Failed to upload Logo at the moment";
				}
			}
			else{ echo "Failed: Image extension $ext is not supported"; }
		}
		else{
			echo "File selected is not an Image! Please select a valid photo";
		}
	}
	
	# get table field
	if(isset($_POST['tblfields'])){
		$tbl=trim($_POST['tblfields']);
		$dbname = (substr($tbl,0,3)=="org" or $tbl=="disbursements$cid") ? 2:1;
		$dbname = (in_array($tbl,array("requisitions$cid","advances$cid"))) ? 3:$dbname;
		$fields = $db->tableFields($dbname,$tbl); 
		
		$lis="";
		foreach($fields as $fld){
			$skip = ($tbl=="org".$cid."_loantemplates") ? array("id","loan_product","branch","loan_officer","loan","status","time"):SKIP_FIELDS;
			$lis.=(!in_array($fld,$skip) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld'>".ucwords(str_replace("_"," ",$fld))."</option>":"";
		}
		
		echo $lis;
	}
	
	# update company info
	if(isset($_POST['updatefields'])){
		$fields = json_decode(trim($_POST['updatefields']));
		$data = mficlient();
		
		foreach($fields as $field){
			$val = ($field=="company") ? clean(strtoupper($_POST[$field])):clean(strtolower($_POST[$field]));
			$val = (in_array($field,array("address","senderid"))) ? str_replace(array("\r\n","\r","\n"),"~",clean($_POST[$field])):$val;
			$data[$field]=$val;
		}
	
		$save = json_encode($data,1);
		if($db->execute(1,"UPDATE `config` SET `settings`='$save' WHERE `client`='$cid'")){
			savelog($sid,"Updated company Info settings");
			echo "success";
		}
		else{
			echo "Failed to update info! Try again later";
		}
	}
	
	# delete temp photo
	if(isset($_POST['rempic'])){
		$pic = trim($_POST['rempic']);
		if(file_exists("../../docs/temps/$pic") && $pic){ unlink("../../docs/temps/$pic"); }
	}

	@ob_end_flush();
?>