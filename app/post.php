<?php
    session_start();
    ob_start();
    
	require "../core/functions.php";
	if(!isset($_SESSION["clapp"])){ echo "Invalid session!"; exit(); }
	$cid = CLIENT_ID; $db=new DBO();
	$qri = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE `position`='USSD' AND `gender`='app' AND `status`='0'");
	$sid = ($_SERVER["HTTP_HOST"]=="localhost") ? 0:$qri[0]['id'];
	insertSqlite("app","CREATE TABLE IF NOT EXISTS withdrawals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,trans TEXT,def TEXT,time INTEGER)");
	
	# terminate Investment
	if(isset($_POST["terminv"])){
		$def = explode(":",trim($_POST["terminv"]));
		$otp = trim($_POST["appotp"]);
		$inv = $def[0]; $uid=$def[1];
		
		$fon = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'")[0]['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if(!$res){ echo "Failed: Invalid OTP Code"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		else{
			$dto = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://$url":"https://$url";
			echo request("$dto/mfs/dbsave/investors.php",["appreqd"=>$sid,"withdrawinv"=>$inv]);
		}
	}
	
	# transfer from wallet
	if(isset($_POST["transwid"])){
		$wid = trim($_POST["transwid"]);
		$wtp = trim($_POST["transfro"]);
		$sum = intval($_POST["tamnt"]);
		$tto = trim($_POST["transto"]);
		$com = clean($_POST["wcomm"]);
		$otp = trim($_POST["otpv"]);
		
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		$actp=$qri[0]['type']; $uid=$qri[0]['client']; $bal=walletBal($wid,$wtp);
		$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		$fon = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'")[0]['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if($sum<1){ echo "Error! Amount should be greater than 0"; }
		elseif($bal<$sum){ echo "Failed: Insufficient balance to complete request!"; }
		elseif(!$res){ echo "Failed: Invalid OTP Code"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		else{
			$sql = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `id`='$uid'");
			$dtls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings"); 
			$cname = ucwords($sql[0]['name']); $desc="Transfer to $dtls[$tto] Account"; $desc.=($com) ? " - $com":"";
			
			if(in_array($tto,["balance","investbal","savings"])){
				if($res=updateWallet($uid,"-$sum:$wtp",$actp,["desc"=>$desc,"revs"=>array(["tbl"=>"norev"])],$sid,time(),1)){
					updateWallet($uid,"$sum:$tto",$actp,["desc"=>"Transfer #$res from $dtls[$wtp] Account","revs"=>array(["tbl"=>"norev"])],$sid);
					logtrans($res,"Transfer of Ksh $sum from $cname $dtls[$wtp] Account to $dtls[$tto] Account",$sid);
					savelog($sid,"$cname Transfered Ksh $sum from $dtls[$wtp] Account to $dtls[$tto] Account");
					echo "success";
				}
				else{ echo "Failed to complete request! Try again later!"; }
			}
			else{
				if($actp=="client"){
					$chk = $db->query(2,"SELECT SUM(balance+penalty) AS tbal FROM `org$cid"."_loans` WHERE `client_idno`='".$sql[0]['idno']."'");
					$tbal = ($chk) ? intval($chk[0]["tbal"]):0;
					if($tbal<=0){ echo "Failed: Client has no running Loan!"; exit(); }
				}
				else{
					if($db->istable(2,"staff_loans$cid")){
						$chk = $db->query(2,"SELECT SUM(balance+penalty) AS tbal FROM `staff_loans$cid` WHERE `stid`='$uid'");
						$tbal = ($chk) ? intval($chk[0]["tbal"]):0;
						if($tbal<=0){ echo "Failed: Staff has no running Loan!"; exit(); }
					}
				}
				
				if($code=payFromWallet($uid,$sum,$actp,"Loan Repayment withdrawal",$sid,1,1)){
					savelog($sid,"$cname Made a loan payment of Ksh $sum from Transactional Account. Ref $code");
					echo "success"; 
				}
				else{ echo "Failed to complete request! Try again later!"; }
			}
		}
	}
	
	# wallet withdrawal
	if(isset($_POST["walwid"])){
		$wid = trim($_POST["walwid"]);
		$wto = intval($_POST["disbto"]);
		$sum = intval($_POST["tamnt"]);
		$otp = trim($_POST["otpv"]);
		$com = clean($_POST["wcomm"]);
		$cnl = (isset($_POST["channel"])) ? trim($_POST["channel"]):"b2c";
		$accno = (isset($_POST["accno"])) ? trim($_POST["accno"]):"";
		
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		$wdef = ($qri[0]["def"]) ? json_decode($qri[0]["def"],1):[];
		$obal = (isset($wdef["ovdlimit"])) ? $wdef["ovdlimit"]:0;
		$intr = (isset($wdef["charges"])) ? $wdef["charges"]:0;
		$actp = $qri[0]['type']; $uid=$qri[0]['client']; $bal=$wbal=walletBal($wid); $bal+=$obal+$intr;
		$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		$fon = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'")[0]['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		$dto = (substr($wto,0,3)=="254" && strlen($wto)>10) ? substr($wto,3):$wto;
		
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_withdrawal_fees'");
		$fees = ($chk) ? json_decode($chk[0]['value'],1):B2C_RATES; $fee=$acid=0; $now=time();
		if($fees){
			foreach($fees as $lim=>$rate){
				$range = explode("-",$lim); $fee=$rate;
				if($sum>=$range[0] && $sum<=$range[1]){ $fee=$rate; break; }
			}
		}
		
		if($sum<50){ echo "Error! Minimum withdrawal Amount is Ksh 50"; }
		elseif($bal<($sum+$fee)){ echo "Failed: Insufficient balance to complete request!"; }
		elseif(!$res){ echo "Failed: Invalid OTP Code"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		elseif(strlen($dto)!=9 && $cnl=="b2c"){ echo "Invalid Withdrawal MPESA Number"; }
		else{
			$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='utilities$cid'");
			$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
			if($qry){ $acid = json_decode($qry[0]['value'],1)["Transactional"]; }
			else{
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='Clients Wallet'");
				if($sql){ $acid=$sql[0]['id']; }
			}
			
			if(!$acid){ echo "Failed: Withdrawals are temporarily disabled!"; }
			else{
				$maxet = (defined("MAX_APP_WITHDRAWAL")) ? MAX_APP_WITHDRAWAL:1000; $pto=$dto;
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
				$name = ucwords($qri[0]['name']); $otp=rand(123456,654321); $exp=time()+60; 
				$me = staffInfo($sid); $mfon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
				$dest = ($cnl=="b2c") ? "MPESA B2C to 0$pto":"MPESA B2B to Paybill $pto A/C $accno";
				$item = "$name Transactional Account Withdrawal via $dest"; $item.=($com) ? " - $com":"";
				$rname = (isset($_POST["rname"])) ? trim($_POST["rname"]):prepare($name); $tsum=$fee+$sum; $rate=0;
				
				$db->execute(1,"REPLACE INTO `otps` VALUES('$mfon','$otp','$exp')"); $notf=($sum<=$maxet) ? 1:0;
				$cols = ($res) ? array_keys(json_decode($res[0]['fields'],1)):["id","staff","branch","item_description","cost","approvals","approved","recipient","month","status","time"]; 
				$post = array("formkeys"=>json_encode($cols,1),"id"=>0,"staff"=>$sid,"recipient"=>$pto,"month"=>strtotime(date("Y-M")),"time"=>$now,"recname"=>$rname,"status"=>0,
				"rbran"=>0,"item"=>[$item],"qnty"=>[1],"baccs"=>["$acid:$wid"],"prices"=>[$sum],"otpv"=>$otp,"wb2c"=>$sid,"approvals"=>"[]","approved"=>0,"smnot"=>$notf,"pmode"=>$cnl);
				if(($wbal-$tsum)<0){
					$ovd = WALLET_OVERDRAFT; $cut=($wbal>0) ? $tsum-$wbal:$tsum; 
					$rate = (count(explode("%",$ovd))>1) ? round($cut*floatval(explode("%",$ovd)[0])/100):$ovd; $post["isovd"]=$rate; 
				}
				
				$mode = ($cnl=="b2c") ? "B2C":"B2B";
				$dto = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://$url":"https://$url";
				$req = request("$dto/mfs/dbsave/utilities.php",$post);
				if(explode(":",trim($req))[0]=="success"){
					$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'");
					if($sum<=$maxet){
						$app = '[{"'.$sid.'":["1","'.$sum.'"]}]'; 
						$rid = $db->query(3,"SELECT `id` FROM `utilities$cid` WHERE `time`='$now' AND `cost`='$tsum'")[0]['id'];
						$db->execute(3,"UPDATE `utilities$cid` SET `status`='200',`approvals`='$app',`approved`='$tsum' WHERE `id`='$rid'");
						
						if($auto=getAutokey()){
							$dtm = hexdec(array_shift($auto));
							if($key=decrypt(implode("/",$auto),date("MdY.His",$dtm))){
								$sql = $db->query(2,"SELECT `source` FROM `b2c_trials$cid` WHERE `phone`='$pto' AND `status`<2 AND JSON_EXTRACT(source,'$.src')='utility' AND
								JSON_EXTRACT(source,'$.wid')=$wid");
								if($sql){ echo "Sorry! There is a pending withdrawal Transaction. Please wait for its completion"; }
								else{
									$ran = dechex(rand(10000000,999999999)); 
									$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'"); 
									$des = json_encode(["src"=>"utility","id"=>$rid,"dtp"=>$cnl,"tmp"=>$ran,"route"=>"$name~$fon~$cnl","wid"=>$wid,"intr"=>$rate],1); 
									$db->execute(2,"INSERT INTO `b2c_trials$cid` VALUES(NULL,'$pto','----','$sum','$des','$sid','$now','0')");
									
									if($cnl=="b2c"){ $res = send_money(["254$pto-$ran"=>$sum],$key,$sid); }
									else{
										$sto["$pto-$ran"] = array("amount"=>$sum,"type"=>$cnl,"account"=>$accno,"trans"=>"Withdrawal ".prenum($rid));
										$res = send_money($sto,$key,$sid,"b2b");
									}
									
									if(explode("=>",$res)[0]=="success"){
										$nto=$qri[0]['notify'];
										if($nto){
											sendSMS(staffInfo($nto)["contact"],"$name has Initiated Withdrawal of Ksh ".fnum($sum)." from Transactional A/C via $mode");
										}
									}
									else{ $db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`='$now' AND `phone`='$pto' AND `amount`='$sum'"); }
								}
							}	
						}
					}
						
					savelog($sid,"$name Initiated Withdrawal of Ksh ".fnum($sum)." from Transactional A/C via $mode"); 
					echo "success";
				}
				else{ echo "Failed to Initiate Withdrawal: $req"; }
			}
		}
	}

	# request stk push
	if(isset($_POST["pkey"])){
		$key = trim($_POST["pkey"]);
		$fon = ltrim(intval($_POST["phone"]),"254");
		$acc = trim($_POST["accno"]);
		$payb = trim($_POST["paybno"]);
		$amnt = intval($_POST["amnt"]);
		$ckey = trim($_POST["ckey"]);
		$csec = trim($_POST["csec"]);
		
		if(strlen($fon)!=9){ echo "Invalid phone number!"; }
		elseif($amnt<5){ echo "Error: Amount should be >= Ksh 5"; }
		else{
			$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]); 
			$url = ($_SERVER['HTTP_HOST']=="localhost") ? "http://pay.pradytec.com":"http://pay.".implode(".",$http);
			$def = array("pkey"=>$key,"payb"=>$payb,"phone"=>"254$fon","amnt"=>$amnt,"pacc"=>$acc,"params"=>["ConsumerKey"=>$ckey,"ConsumerSecret"=>$csec]);
			$res = request("$url/b2c/c2b.php",["stkpush"=>$def,"floc"=>syshost()]);
			echo $res;
		}
	}
	
	# staff login
	if(isset($_POST["stfid"])){
		$uid = trim($_POST["stfid"]);
		$acc = trim($_POST["clacc"]);
		$pass = trim($_POST["spass"]);
		$rem = (isset($_COOKIE["logtrials"])) ? $_COOKIE["logtrials"]:0;
		
		$chk = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'");
		if($chk[0]["status"]){ echo "Failed: We are temporarily out of service"; exit(); }
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$uid'");
		if($sql){
			if($rem>=4){ echo "Failed: Your account has been blocked after $rem failed attempts"; }
			else{
				$def = json_decode($sql[0]["config"],1); $exp=time()+(86400*7);
				if(decrypt($def["key"],date("YMd-his",$sql[0]['time']))==$pass){
					if($sql[0]['status']){ echo "Your account has been suspended!"; }
					else{
						setcookie("myapp",dechex($acc),$exp,"/"); $_SESSION["use4"]=$acc;
						setcookie("bid",encrypt("staff:".$sql[0]["jobno"],"bkey"),time()+(360*86400),"/");
						setcookie("logtrials",3,0,"/");
						echo "success";
					}
				}
				else{
					$rem+=1; setcookie("logtrials",$rem,time()+(86400*2),"/"); $no=3-$rem;
					echo "Failed: Incorrect Password. You have $no trials remaining"; 
				}
			}
		}
		else{ echo "Failed: Staff account doesnt exist!"; }
	}
	
	#client login
	if(isset($_POST["cidno"])){
		$idno = trim($_POST["cidno"]);
		$otp = clean($_POST["otpv"]);
		$pin = clean($_POST["lpin"]);
		$chk = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'");
		if($chk[0]["status"]){ echo "Failed: We are temporarily out of service"; exit(); }
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$cdef = json_decode($sql[0]['cdef'],1); $fon=$sql[0]['contact']; $now=time();
		$rem = (isset($cdef["logtrials"])) ? $cdef["logtrials"]:0; $exp=$now+(86400*7);
		
		if($rem>=3){ echo "Failed: Your account has been blocked after $rem failed attempts. Contact your Officer for guidance"; }
		else{
			$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
			if($res){
				if($res[0]['expiry']<$now){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
				else{
					if(isset($cdef["ckey"])){
						if(decrypt($cdef["ckey"],implode(":",str_split($idno,2)))==$pin){
							$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'");
							if($rem){
								unset($cdef["logtrials"]); $jsn=json_encode($cdef,1);
								$db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'");
							}
							
							setcookie("bid",encrypt("client:$idno","bkey"),time()+(360*86400),"/");
							setcookie("myapp",dechex($idno),$exp,"/");
							echo "success";
						}
						else{
							$rem+=1; $cdef["logtrials"]=$rem; $jsn=json_encode($cdef,1); $no=3-$rem;
							$db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'");
							echo "Failed: Incorrect PIN. You have $no trials remaining"; 
						}
					}
					else{
						$cdef["ckey"]=encrypt($pin,implode(":",str_split($idno,2))); $jsn=json_encode($cdef,1);
						if($db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'")){
							$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'");
							setcookie("bid",encrypt("client:$idno","bkey"),time()+(360*86400),"/");
							setcookie("myapp",dechex($idno),$exp,"/"); 
							echo "success";
						}
						else{ echo "Failed to complete the request! Try again later"; }
					}
				}
			}
			else{ echo "Failed: Invalid OTP, try again"; }
		}
	}
	
	# request otp
	if(isset($_POST["reqotp"])){
		$fon = trim($_POST["reqotp"]);
		$name = trim($_POST["reqname"]);
		$nos = (isset($_SESSION["otps"])) ? $_SESSION["otps"]:0;
		$otp = rand(123456,654321); $exp=time()+600;
		
		if($nos>5){ echo "Failed: You have exhausted request trials!"; }
		else{
			$res = sendSMS($fon,"Hi $name, Use code $otp as your system OTP before ".date('h:i A',$exp));
			if(substr($res,0,7)=="Sent to"){
				$db->insert(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')"); 
				echo "success";
			}
			else{ echo "Failed to request OTP at the moment! Try again later"; }
		}
	}
    
    ob_end_flush();
?>