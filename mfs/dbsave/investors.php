<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST["appreqd"])){ exit(); }
	$sid = (isset($_POST["appreqd"])) ? trim($_POST["appreqd"]):substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	
	# save investor account
	if(isset($_POST["invid"])){
		$rid = trim($_POST["invid"]);
		$name = clean(strtolower($_POST["ivname"]));
		$mail = clean(strtolower($_POST["ivmail"]));
		$idno = intval($_POST["ividno"]);
		$fon = ltrim(intval($_POST["ivfon"]),"254");
		$now = time();
		
		$chk1 = $db->query(2,"SELECT *FROM `investors$cid` WHERE `contact`='$fon' AND NOT `id`='$rid'");
		$chk2 = $db->query(2,"SELECT *FROM `investors$cid` WHERE `idno`='$fon' AND NOT `id`='$rid'");
		$chk3 = $db->query(2,"SELECT *FROM `investors$cid` WHERE `email`='$mail' AND NOT `id`='$rid'");
		
		if(count(explode(" ",$name))<2){ echo "Failed: Provide more than one name!"; }
		elseif(strlen($fon)!=9){ echo "Error: Invalid phone number"; }
		elseif(strlen($idno)<6){ echo "Error: Invalid ID number"; }
		elseif(!filter_var($mail,FILTER_VALIDATE_EMAIL)){ echo "Error: Invalid Email"; }
		elseif($chk1){ echo "Failed: Phone number $fon is already in the system"; }
		elseif($chk2){ echo "Failed: ID number $idno is already in the system"; }
		elseif($chk3){ echo "Failed: Email address $mail is already in the system"; }
		else{
			$qry = ($rid) ? "UPDATE `investors$cid` SET `name`='$name',`contact`='$fon',`email`='$mail',`idno`='$idno' WHERE `id`='$rid'":
			"INSERT INTO `investors$cid` VALUES(NULL,'$name','$fon','$idno','$mail','investor','[]','0','$now')";
			if($db->execute(2,$qry)){
				if(!$rid){
					$vid = $db->query(2,"SELECT `id` FROM `investors$cid` WHERE `idno`='$idno' AND `time`='$now'")[0]['id'];
					$jsn = json_encode(["key"=>encrypt("0$fon",date("dmY-H:i",$now)),"lastreset"=>0,"profile"=>"none","ref"=>"I$vid"],1);
					$db->execute(2,"UPDATE `investors$cid` SET `config`='$jsn' WHERE `id`='$vid'");
				}
				
				$desc = ($rid) ? "Updated investor $name info":"Created investor account $name";
				savelog($sid,$desc);
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# terminate Investment
	if(isset($_POST["withdrawinv"])){
		$inv = trim($_POST["withdrawinv"]);
		$types = array("C"=>"client","S"=>"staff","I"=>"investor");
		$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `investments$cid` WHERE `id`='$inv' FOR UPDATE");
			$row = $sql->fetch_assoc(); $def=json_decode($row['details'],1); $user=$row['client']; $bal=$def["balance"];
			$uid = substr($user,1); $actp=$types[substr($user,0,1)]; $pid=$row['package']; $exp=$row["maturity"]; 
			$tdy = intval(date("d")); $tmn=intval(date("m")); $acc=$def["book"]; $state=($exp>time()) ? 15:time();
			$twd = (isset($def["withdrawal"])) ? $def["withdrawal"]:0; $rem=$def["payouts"]-$twd;
			
			$sql = $db->query(1,"SELECT *FROM `invest_packages` WHERE `id`='$pid'");
			$prod = json_decode($sql[0]['periods'],1); $name=$sql[0]['name']; $wdys=explode("-",$prod["wdays"]); $wmon=$prod["wmon"];
			
			if($wmon==0){
				if(($exp>time() or $tdy<$wdys[0] or $tdy>$wdys[1]) && $sid>1){ echo "Failed: Withdrawal not allowed"; exit(); }
			}
			else{
				if(($tmn!=$wmon or $tdy<$wdys[0] or $tdy>$wdys[1]) && $sid>1 && $exp>time()){ echo "Failed: Withdrawal not allowed"; exit(); }
			}
			
			if($bal<=0){ echo "success"; exit(); }
			if($res=updateWallet($uid,"+$bal:investbal",$actp,"$name Investment withdrawal",$sid,time(),1)){
				$def["balance"]=0; $def["payouts"]+=$bal; $jsn=json_encode($def,1); $tym=time(); 
				$con->query("UPDATE `investments$cid` SET `status`='$state',`details`='$jsn' WHERE `id`='$inv'");
				$con->query("UPDATE `invreturns$cid` SET `status`='$tym' WHERE `investment`='$inv' AND `status`='0'");
				$con->commit(); $con->close(); bookbal($acc,"-$bal"); $mon=strtotime(date("Y-M")); $yr=date("Y");
				$cname = $db->query(2,"SELECT `name` FROM `$tbls[$actp]` WHERE `id`='$uid'")[0]['name'];
				$des = "$name Capital withdrawal from $cname Investments"; $day=strtotime(date("Y-M-d"));  
				$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','liability','$acc','$bal','debit','$des','$inv','','$sid','auto','$mon','$day','$tym','$yr')");
				if($rem>0){ transInvPay($inv,$rem); }
				logtrans($res,$des,$sid); savelog($sid,"Initiated $des");
				echo "success";
			}
			else{ echo "Failed to complete request! Try again later"; $con->commit(); $con->close(); }
		}
		else{ echo "Failed: System error! Try again later"; }
	}
	
	# create Investment
	if(isset($_POST["ivsrc"])){
		$src = trim($_POST["ivsrc"]);
		$pid = trim($_POST["pack"]);
		$dur = trim($_POST["dur"]);
		$amnt = intval($_POST["ivamnt"]);
		
		if(!$db->istable(3,"investments$cid")){
			$db->createTbl(3,"investments$cid",["tid"=>"CHAR","client"=>"CHAR","package"=>"INT","amount"=>"CHAR","period"=>"INT","returns"=>"CHAR","bonus"=>"CHAR","details"=>"TEXT",
			"started"=>"INT","maturity"=>"INT","status"=>"INT","time"=>"INT"]); 
			$db->createTbl(3,"invreturns$cid",["client"=>"CHAR","investment"=>"INT","amount"=>"CHAR","maturity"=>"INT","schedule"=>"TEXT","payouts"=>"TEXT","status"=>"INT"]);
		}
		
		if(!$db->istable(2,"investors$cid")){
			$db->createTbl(2,"investors$cid",["name"=>"CHAR","contact"=>"INT","idno"=>"INT","email"=>"CHAR","type"=>"CHAR","config"=>"TEXT","status"=>"INT","time"=>"INT"]); 
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		$sql = $db->query(1,"SELECT *FROM `invest_packages` WHERE `id`='$pid'");
		$row = $sql[0]; $range=explode("-",$row["amount"]); $pname=$row['name']; $intv=$row['intervals']; $ptp=$row['type'];
		$prod = json_decode($row['periods'],1); $dys=$prod['days']; $dy=array_keys($dys)[0]; $perc=$dys[$dy];
		$types = array("C"=>"client","S"=>"staff","I"=>"investor");
		
		if(in_array(substr($src,0,1),["C","S","I"])){
			$actp = $types[substr($src,0,1)]; $uid=substr($src,1); 
			$tbl = ($actp=="client") ? "org$cid"."_clients":"org$cid"."_staff"; $tbl=($actp=="investor") ? "investors$cid":$tbl;
			$wid = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='$actp'")[0]['id'];
			$qry = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$uid'")[0]; $cname=ucwords($qry['name']);
			$bal = walletBal($wid,"investbal");
			if(substr($src,0,1)!="I"){
				$chk = $db->query(2,"SELECT *FROM `investors$cid` WHERE JSON_EXTRACT(config,'$.ref')='$src'");
				if(!$chk){
					if($actp=="client"){
						$row = $qry; $idno=$row['idno']; $cname=$row['name']; $fon=$row['contact']; $tym=time();
						$jsn = json_encode(["key"=>encrypt("0$fon",date("dmY-H:i",$tym)),"lastreset"=>0,"profile"=>"none","ref"=>$src],1);
						$db->execute(2,"INSERT INTO `investors$cid` VALUES(NULL,'$cname','$fon','$idno','','client','$jsn','0','$tym')");
					}
					else{
						$row = $qry; $idno=$row['idno']; $cname=$row['name']; $fon=$row['contact']; $tym=time();
						$def = json_decode($row["config"],1); $utm=$row['time']; $dpass=decrypt($def['key'],date("YMd-his",$utm)); $mail=$row['email'];
						$jsn = json_encode(["key"=>encrypt($dpass,date("dmY-H:i",$tym)),"lastreset"=>0,"profile"=>$def["profile"],"ref"=>$src],1);
						$db->execute(2,"INSERT INTO `investors$cid` VALUES(NULL,'$cname','$fon','$idno','$mail','staff','$jsn','0','$tym')");
					}
				}
			}
		}
		else{ echo "Failed! Client Not Found!"; exit(); }
		
		if(isset($_POST["appotp"])){
			$otp = trim($_POST["appotp"]);
			$fon = $db->query(2,"SELECT `contact` FROM `org$cid"."_clients` WHERE `id`='".substr($src,1)."'")[0]['contact'];
			$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
			if(!$res){ echo "Failed: Invalid OTP Code"; exit(); }
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); exit(); }
		}
		
		if($amnt<$range[0]){ echo "Failed: Minimum Investment is Ksh ".fnum($range[0]); }
		elseif($amnt>$range[1]){ echo "Failed: Maximum Investment is Ksh ".fnum($range[1]); }
		elseif($bal<$amnt){ echo "Failed: Insufficient account balance"; }
		elseif(!$qri){ echo "Sorry! Investors ROI Account has not been set up. Go to books of account > chart of accounts to set First!"; }
		else{
			$acc=json_decode($qri[0]['value'],1)["Investors"]; $now=time(); $now+=(intval(date("His"))<1000) ? 600:0; $min=($now-60)+(86400*7); $tint=0;
			$qry = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='$src' AND `status`='0' AND `package`='$pid' AND `maturity`>$min");
			if($ptp=="Cummulative" && $qry){
				if($con=$db->mysqlcon(3)){
					$con->autocommit(0); $con->query("BEGIN");
					$sql = $con->query("SELECT *FROM `investments$cid` WHERE `client`='$src' AND `status`='0' FOR UPDATE");
					$row = $sql->fetch_assoc(); $vid=$row['id']; $pamnt=$row["amount"]; $exp=$row["maturity"]; $rets=$row['returns']; 
					$det = json_decode($row["details"],1); $det["balance"]+=$amnt; $djs = json_encode($det,1); $tsum=$pamnt+$amnt;
					
					if($res=updateWallet($uid,"-$amnt:investbal",$actp,"$pname Investment Topup",$sid,$now,1)){
						$sqr = $con->query("SELECT *FROM `invreturns$cid` WHERE `investment`='$vid' AND `status`='0' FOR UPDATE");
						while($row=$sqr->fetch_assoc()){
							$scd = json_decode($row["schedule"],1); $pays=json_decode($row["payouts"],1); $tot=count($scd);
							$rem = $tot-count($pays); $per=$perc*($rem/$tot); $intr=roundnum($amnt*$per/100,2); $at=roundnum($intr/$rem,2); 
							foreach($scd as $dy=>$sum){
								if(!isset($pays[$dy])){ $scd[$dy]=roundnum($at+$sum,2); }
							}
							
							$rid = $row['id']; $amt=$row['amount']+$intr; $jsn=json_encode($scd,1); $rets+=$intr;
							$con->query("UPDATE `invreturns$cid` SET `schedule`='$jsn',`amount`='$amt' WHERE `id`='$rid'");
						}
						
						$con->query("UPDATE `investments$cid` SET `amount`='$tsum',`returns`='$rets',`details`='$djs' WHERE `id`='$vid'");
						$con->commit(); $con->close(); bookbal($acc,"+$amnt"); 
						$des = "$pname Investment Topup"; $day=strtotime(date("Y-M-d")); $mon=strtotime(date("Y-M")); $yr=date("Y"); 
						$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','liability','$acc','$amnt','credit','$des','$vid','','$sid','auto','$mon','$day','$now','$yr')");
						logtrans("INVEST$vid",$des,$sid); savelog($sid,"Created $des");
						echo "success";
					}
					else{ echo "Failed to Initiate Investment topup! Try again later"; }
				}
				else{ echo "System error! Try again later"; }
			}
			else{
				for($i=1; $i<=($dur/$dy); $i++){
					$intr=round($amnt*$perc/100); $per=roundnum($intr/($dy/$intv),2); $exp=$now+(86400*$i*$dy); $start=$exp-(86400*$dy); $tint+=$intr; $div=[];
					for($k=1; $k<=($dy/$intv); $k++){ $nxt=$start+($k*$intv*86400); $div[$nxt]=$per; }
					$qrys[] = "(NULL,'$src','$now','$intr','$exp','".json_encode($div,1)."','[]','0')";
				}
				
				if($res=updateWallet($uid,"-$amnt:investbal",$actp,"$pname Investment for $dur days",$sid,$now,1)){
					$expd=$now+(86400*$dur); $des="$pname Investment for $dur days from $cname Investment Account";
					$day=strtotime(date("Y-M-d")); $mon=strtotime(date("Y-M")); $yr=date("Y"); 
					$desc=json_encode(["pname"=>$pname,"hour"=>intval(date("His",$now)),"balance"=>$amnt,"payouts"=>0,"book"=>$acc],1); 
					
					if($db->execute(3,"INSERT INTO `investments$cid` VALUES(NULL,'$res','$src','$pid','$amnt','$dur','$tint','0','$desc','$now','$expd','0','$now')")){
						$ivid = $db->query(3,"SELECT `id` FROM `investments$cid` WHERE `time`='$now' AND `tid`='$res'")[0]['id'];
						$db->execute(3,"INSERT INTO `invreturns$cid` VALUES ".implode(",",$qrys)); bookbal($acc,"+$amnt");
						$db->execute(3,"UPDATE `invreturns$cid` SET `investment`='$ivid' WHERE `investment`='$now' AND `client`='$src'");
						$db->execute(2,"UPDATE `investors$cid` SET `status`='1' WHERE JSON_EXTRACT(config,'$.ref')='$src'");
						$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','liability','$acc','$amnt','credit','$des','$ivid','','$sid','auto','$mon','$day','$now','$yr')");
						logtrans("INVEST$ivid",$des,$sid); savelog($sid,"Created $des");
						echo "success";
					}
					else{
						updateWallet($uid,"$amnt:investbal",$actp,"$pname Investment Reversal",$sid,$now,1);
						echo "Failed to create Investment! Try again later";
					}
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
	}
	
	# save package
	if(isset($_POST["packid"])){
		$pid = trim($_POST["packid"]);
		$name = ucwords(clean(strtolower($_POST["pname"])));
		$amnt = array_map("intval",$_POST["pamnt"]); 
		$wmon = trim($_POST["wmon"]); 
		$pdur = intval($_POST["pdur"]); 
		$idur = intval($_POST["idur"]); 
		$ptp = trim($_POST["ptype"]); 
		$wdys = $_POST["wdays"]; 
		$error="";
		
		$chk = $db->query(1,"SELECT *FROM `invest_packages` WHERE `name`='$name' AND `client`='$cid' AND NOT `id`='$pid'");
		foreach($_POST["days"] as $key=>$dy){
			if(fmod($idur,$dy)){ $error="Investment return days $dy should be divisible by Investment period $idur"; break; }
			elseif(fmod($dy,$pdur)){ $error="Investment return days $dy should be divisible by payment intervals $pdur"; break; }
			else{ $data[$dy]=$_POST["perc"][$key]; }
		}
		
		if($amnt["min"]<100){ echo "Error: Minimum amount should be >= 100"; }
		elseif($amnt["max"]<$amnt["min"]){ echo "Error: Maximum amount should be >= Minimum amount"; }
		elseif($wdys["fro"]>$wdys["dto"]){ echo "Error: withdrawal end date should be >= start date"; }
		elseif($pdur<1){ echo "Invalid payment intervals! It should be greater than 0"; }
		elseif($error){ echo $error; }
		elseif($chk){ echo "Failed: Package already exists!"; }
		else{
			$jsn = json_encode(["days"=>$data,"duration"=>$idur,"wdays"=>implode("-",$wdys),"wmon"=>$wmon],1); $tym=time(); $range=implode("-",$amnt);
			$qry = ($pid) ? "UPDATE `invest_packages` SET `name`='$name',`type`='$ptp',`amount`='$range',`periods`='$jsn',`intervals`='$pdur' WHERE `id`='$pid'":
			"INSERT INTO `invest_packages` VALUES(NULL,'$cid','$name','$ptp','$range','$jsn','$pdur','0','$tym')";
			if($db->execute(1,$qry)){
				$txt = ($pid) ? "Updated":"Created";
				savelog($sid,"$txt Investment package $name Info");
				echo "success"; 
			}
			else{ echo "Failed to save package! Try again Later"; }
		}
	}
	
	# activate/deactivate package
	if(isset($_POST['packstate'])){
		$pid = trim($_POST['packstate']);
		$val = (trim($_POST['sval'])) ? 0:1;
		$txt = ($val) ? "Deactivated":"Activated";
		
		$name = $db->query(1,"SELECT `name` FROM `invest_packages` WHERE `id`='$pid'")[0]['name'];
		if($db->execute(1,"UPDATE `invest_packages` SET `status`='$val' WHERE `id`='$pid'")){
			savelog($sid,"$txt Investment package $name");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}

	@ob_end_flush();
?>