<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST['sysauto'])){ exit(); }
	if(isset($_POST['sysauto'])){ $sid=0; }
	else{
		$sid = substr(hexdec($_SESSION['myacc']),6);
		if($sid<1){ exit(); }
	}
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# send sms from leads
	if(isset($_POST['csmto'])){
		$stime = strtotime(str_replace("T",",",trim($_POST['sendtm'])));
		$addto = trim($_POST['addto']);
		$smto = json_decode(trim($_POST['csmto']),1);
		$sms  = trim($_POST['cmssg']); $av=0;
		
		$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($sms)); 
		if(strpos($mssg,"CLIENT")!==false){
			foreach($smto as $cont=>$name){
				$phones["254$cont"] = str_replace("CLIENT",explode(" ",prepare(ucwords($name)))[0],$mssg); $av++;
			}
		}
		
		if($addto){
			if($av){ $phones[$addto]=$phones[array_keys($phones)[0]]; }
			else{ $smto[$addto]="Admin"; }
		}
	
		if($stime<=time()){
			if($av){
				$all=count($phones); $to=json_encode(array_map("clean",$phones),1); $tym=time();
				$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($sms)."','$to','multiple','$stime','$tym')");
				savelog($sid,"Added message\n { ".$sms." } to send Queue for $all Clients");
				echo "Success! Send Queue to $all clients created";
			}
			else{
				$res = sendSMS(implode(",",array_keys($smto)),$sms);
				if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$sms." } to ".substr($res,7)); }
				echo $res;
			}
		}
		else{
			$sto = ($av) ? $phones:array_keys($smto);
			$smtp = ($av) ? "multiple":"similar"; $to=json_encode(array_map("clean",$sto),1); $all=count($sto); $tym=time();
			if($db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($sms)."','$to','$smtp','$stime','$tym')")){
				savelog($sid,"Created Message schedule { ".$sms." } to be sent at ".date("M d,Y h:i a",$stime)." to $all Clients");
				echo "Success! Schedule created for $all Clients";
			}
			else{ echo "Failed to create schedule at the moment"; }
		}
	}
	
	# send/schedule sms to staff/clients
	if(isset($_POST['smssg'])){
		$smto = trim($_POST['smto']);
		$recip = $_POST[$smto];
		$mssg = $smt=trim($_POST['smssg']); $av=0;
		$stime = strtotime(str_replace("T",",",trim($_POST['sendtm'])));
		$conts=$sms=$dels=[]; $tdy=strtotime(date("Y-M-d"));
		
		if($mssg==null){ echo "Failed to retrieve prepared message! Try again later"; exit(); }
		
		if($smto=="staff"){
			$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($mssg)); 
			if(strpos($mssg,"STAFF")!==false){
				foreach($recip as $cont){
					$sql = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `contact`='$cont'"); $av++;
					$conts["254$cont"] = str_replace("STAFF",explode(" ",prepare(ucwords($sql[0]['name'])))[0],$mssg);
				}
			}
				
			if($stime<=time()){
				if($av){
					$all=count($conts); $to=json_encode(array_map("clean",$conts),1); $tym=time();
					$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','multiple','$stime','$tym')");
					savelog($sid,"Added message\n { ".$smt." } to send Queue for $all staff");
					echo "Success! Send Queue to $all clients created";
				}
				else{
					$res = sendSMS(implode(",",$recip),$smt);
					if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$mssg." } to ".substr($res,7)); }
					echo $res;
				}
			}
			else{
				$conts = ($av) ? $conts:$recip;
				$smtp = ($av) ? "multiple":"similar"; $to=json_encode(array_map("clean",$conts),1); $all=count($conts); $tym=time();
				if($db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','$smtp','$stime','$tym')")){
					savelog($sid,"Created Message schedule { ".$smt." } to be sent at ".date("M d,Y h:i a",$stime)." to $all Staff");
					echo "Success! Schedule created for $all Staff";
				}
				else{ echo "Failed to create schedule at the moment"; }
			}
		}
		else{
			$conts = []; $ctbl = "org".$cid."_clients";
			$qri = $db->query(2,"SELECT *FROM `$ctbl`");
			foreach($qri as $row){ $def[$row['contact']]=$row; }
			
			if(!$_POST['spos']){
				foreach($recip as $one){
					$from=explode("=",$one); $tp=$from[0]; $val=$from[1];
					if($tp=="all"){
						$res = $db->query(2,"SELECT contact FROM `org".$cid."_clients` WHERE ".base64_decode($val));
						foreach($res as $row){ $conts[]=$row['contact']; }
						break;
					}
					else{
						if($tp=="json"){ $conts = array_merge($conts,json_decode($val,1)); }
						if($tp=="status"){
							$sep = explode(":",$val); $state=$sep[0]; $cond=base64_decode($sep[1]);
							$res = $db->query(2,"SELECT contact FROM `$ctbl` WHERE $cond AND `status`='$state'");
							foreach($res as $row){ $conts[]=$row['contact']; }
						}
						if($tp=="group"){
							$res = $db->query(2,"SELECT cl.contact FROM `client_groups$cid` AS cg INNER JOIN `$ctbl` AS cl ON cg.client=cl.id WHERE cg.gid='$val'");
							foreach($res as $row){ $conts[]=$row['contact']; }
						}
					}
				}
			}
			else{ $conts = $recip; }
			
			$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($mssg)); $idnos=[];
			if(strpos($mssg,"CLIENT")!==false){
				foreach($conts as $cont){
					$sms[$cont] = str_replace("CLIENT",explode(" ",prepare(ucwords($def[$cont]['name'])))[0],$mssg); $av++;
				}
			}
			
			if(strpos($mssg,"IDNO")!==false){
				foreach($conts as $cont){
					$dsms = (isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$sms[$cont] = str_replace("IDNO",$def[$cont]['idno'],$dsms); $av++;
				}
			}
			
			if(strpos($mssg,"PENALTY")!==false or strpos($mssg,"LOAN_BALANCE")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg; $av++;
					$res = $db->query(2,"SELECT penalty,balance,client_idno FROM `org".$cid."_loans` WHERE `phone`='$fon' ORDER BY `disbursement` DESC LIMIT 1");
					$nms = str_replace("PENALTY",fnum($res[0]['penalty']),$src); $tbal=$res[0]['penalty']+$res[0]["balance"];
					$sms[$cont] = str_replace("LOAN_BALANCE",fnum($tbal),$nms); $idnos[$cont]=$res[0]['client_idno'];
					if($tbal<=0){ $dels[]=$cont; }
				}
			}
			if(strpos($mssg,"LOAN_AMOUNT")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg; $av++;
					$res = $db->query(2,"SELECT `amount` FROM `org".$cid."_loans` WHERE `phone`='$fon' ORDER BY `disbursement` DESC LIMIT 1");
					$sms[$cont] = str_replace("LOAN_AMOUNT",fnum($res[0]['amount']),$src);
					if(!isset($idnos[$cont])){ $idnos[$cont] = $res[0]['idno']; }
					if($res[0]['amount']<=0){ $dels[]=$cont; }
				}
			}
			if(strpos($mssg,"ARREARS")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg; $av++;
					if(!isset($idnos[$cont])){
						$res = $db->query(2,"SELECT `idno` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); $idno=$res[0]['idno'];
					}
					else{ $idno = $idnos[$cont]; }
					
					$sql = $db->query(2,"SELECT SUM(balance) AS tbal FROM `org".$cid."_schedule` WHERE `idno`='$idno' AND `day`<$tdy AND `balance`>0");
					$sms[$cont] = str_replace("ARREARS",fnum($sql[0]['tbal']),$src);
				}
			}
			
			foreach($dels as $fon){ unset($sms[$fon]); }
			if($stime<=time()){
				if($av){
					$all=count($sms); $to=json_encode(array_map("clean",$sms),1); $tym=time();
					$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','multiple','$stime','$tym')");
					savelog($sid,"Added message\n { ".$smt." } to send Queue for $all Clients");
					echo "Success! Send Queue to $all clients created";
				}
				else{
					$res = sendSMS(implode(",",array_unique($conts)),$smt);
					if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$mssg." } to ".substr($res,7)); }
					echo $res;
				}
			}
			else{
				$sto = ($av) ? $sms:$conts;
				$smtp = ($av) ? "multiple":"similar"; $to=json_encode(array_map("clean",$sto),1); $all=count($sto); $tym=time();
				if($db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','$smtp','$stime','$tym')")){
					savelog($sid,"Created Message schedule { ".$smt." } to be sent at ".date("M d,Y h:i a",$stime)." to $all Clients");
					echo "Success! Schedule created for $all Clients";
				}
				else{ echo "Failed to create schedule at the moment"; }
			}
		}
	}
	
	# request OTP
	if(isset($_POST['requestotp'])){
		$uid = trim($_POST['requestotp']);
		$otp = rand(123456,654321); $exp=time()+600; $mail="";
		$mode = (defined("OTP_CHANNEL")) ? OTP_CHANNEL:"sms";
		
		if(isset($_POST["cotp"])){ $def=explode(":",trim($_POST["cotp"])); $fon=$def[0]; $name=$def[1]; }
		else{ $me = staffInfo($uid); $mail=$me["email"]; $fon=(isset($me["office_contact"])) ? $me['office_contact']:$me['contact']; $name=prepare(ucwords($me['name'])); }
	
		if(strlen($fon)<9){ echo "Invalid phone number!"; }
		else{
			if($mode=="email" && $mail){
				$res = mailto([$mail],["System OTP"],[frame_email("<h4>Dear $name,</h4><p>Use code <b>$otp</b> as your system OTP before ".date('h:i A',$exp)."</p>")],[null]);
				if($res==1){ $db->execute(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')"); }
				echo ($res==1) ? "OTP Sent Successfully":"Failed to send the OTP via Email";
			}
			else{
				$res = sendSMS($fon,"Dear $name, Use code $otp as your system OTP before ".date('h:i A',$exp)); 
				if(substr($res,0,7)=="Sent to"){ $db->execute(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')"); }
				echo $res;
			}
		}
	}
	
	# send payment reminder SMS
	if(isset($_POST['pday'])){
		$day = trim($_POST['pday']);
		$add = trim($_POST['addto']);
		$mssg = $smt = trim($_POST['clmssg']);
		$conts = json_decode(trim($_POST['phones']),1);
		$specs = (isset($_POST["spec"])) ? $_POST["spec"]:[];
		
		$setts = array("smsamount"=>2,"installmentdate"=>1);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$field = ($setts['smsamount']==1) ? "sd.amount":"sd.balance";
		$idays = array($day-86400,$day,$day+86400); $tdy=strtotime("Today"); 
		$smday=date("d-m-Y",$idays[$setts['installmentdate']]); $sms=$idnos=$dels=$cids=$brans=$loffs=[]; 
		$mssg = (strpos($mssg,"DATE")!==false) ? str_replace("DATE",$smday,$mssg):$mssg; $avs=0; 
		foreach(array("CLIENT","PENALTY","AMOUNT","IDNO","ARREARS","TRANSACC","LOAN_OFFICER","PAYBILL","BALANCE") as $key){
			$avs+=(strpos($mssg,$key)!==false) ? 1:0;
		}
		
		if($avs){
			$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($mssg));
			if(strpos($mssg,"CLIENT")!==false){
				foreach($conts as $cont){
					$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `contact`='".ltrim($cont,"254")."'");
					$sms[$cont] = str_replace("CLIENT",explode(" ",prepare(ucwords($res[0]['name'])))[0],$mssg); 
					$idnos[$cont]=$res[0]['idno']; $cids[$cont]=$res[0]['id']; $loffs[$cont]=$res[0]['loan_officer']; $brans[$cont]=$res[0]['branch'];
				}
			}
			if(strpos($mssg,"PENALTY")!==false or strpos($mssg,"BALANCE")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$res = $db->query(2,"SELECT penalty,balance,client_idno FROM `org".$cid."_loans` WHERE `phone`='$fon' AND (balance+penalty)>0");
					if($res){
						$nms = str_replace("PENALTY",fnum($res[0]['penalty']),$src); $tbal=$res[0]['penalty']+$res[0]["balance"];
						$sms[$cont] = str_replace("BALANCE",fnum($tbal),$nms);
						$idnos[$cont] = $res[0]['client_idno'];
					}
					else{ $dels[$cont]=$cont; }
				}
			}
			if(strpos($mssg,"AMOUNT")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$res = $db->query(2,"SELECT $field AS amnt,sd.idno FROM `org".$cid."_loans` AS ln INNER JOIN `org".$cid."_schedule` AS sd 
					ON sd.loan=ln.loan WHERE ln.phone='$fon' AND sd.day='$day' AND sd.balance>0 AND ln.status='0'");
					if($res){
						$sms[$cont] = str_replace("AMOUNT",fnum($res[0]['amnt']),$src);
						if(!isset($idnos[$cont])){ $idnos[$cont] = $res[0]['idno']; }
					}
					else{
						if(isset($specs[$cont])){
							$sms[$cont] = str_replace("AMOUNT",fnum($specs[$cont]),$src); $msr=$sms[$cont]; $nxd=strtotime($smday)+86400;
							$sms[$cont] = str_replace($smday,date("d-m-Y",$nxd),$msr); 
						}
						else{ $dels[$cont]=$cont; }
					}
				}
			}
			if(strpos($mssg,"IDNO")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					if(!isset($idnos[$cont])){
						$res = $db->query(2,"SELECT `idno`,`id`,`loan_officer`,`branch` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); 
						$idno = $res[0]['idno']; $cids[$cont]=$res[0]['id']; $loffs[$cont]=$res[0]['loan_officer']; $brans[$cont]=$res[0]['branch'];
					}
					else{ $idno = $idnos[$cont]; }
					$sms[$cont] = str_replace("IDNO",$idno,$src);
				}
			}
			if(strpos($mssg,"ARREARS")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					if(!isset($idnos[$cont])){
						$res = $db->query(2,"SELECT `idno`,`id`,`loan_officer`,`branch` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); 
						$idno = $res[0]['idno']; $cids[$idno]=$res[0]['id']; $loffs[$cont]=$res[0]['loan_officer']; $brans[$cont]=$res[0]['branch'];
					}
					else{ $idno = $idnos[$cont]; }
					
					$sql = $db->query(2,"SELECT SUM(balance) AS tbal FROM `org".$cid."_schedule` WHERE `idno`='$idno' AND `day`<$tdy AND `balance`>0");
					$sms[$cont] = str_replace("ARREARS",fnum(intval($sql[0]['tbal'])),$src);
				}
			}
			if(strpos($mssg,"TRANSACC")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					if(!isset($idnos[$cont])){
						$res = $db->query(2,"SELECT `id`,`loan_officer`,`branch` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); 
						$rid = $res[0]['id']; $loffs[$cont]=$res[0]['loan_officer']; $brans[$cont]=$res[0]['branch'];
					}
					else{
						if(isset($cids[$cont])){ $rid=$cids[$cont]; }
						else{
							$res = $db->query(2,"SELECT `id`,`loan_officer`,`branch` FROM `org".$cid."_clients` WHERE `idno`='".$idnos[$cont]."'"); 
							$rid = $res[0]['id']; $loffs[$cont]=$res[0]['loan_officer']; $brans[$cont]=$res[0]['branch'];
						}
					}
					
					$wid = createWallet("client",$rid);
					$sms[$cont] = str_replace("TRANSACC",walletAccount($brans[$cont],$wid,"balance"),$src);
				}
			}
			if(strpos($mssg,"LOAN_OFFICER")!==false){
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					if(isset($loffs[$cont])){ $rid=$loffs[$cont]; }
					else{ $res = $db->query(2,"SELECT `loan_officer` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); $rid=$res[0]['loan_officer']; }
					$phone=staffInfo($rid)["contact"]; $sms[$cont]=str_replace("LOAN_OFFICER","0$phone",$src);
				}
			}
			if(strpos($mssg,"PAYBILL")!==false){
				$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
				foreach($qri as $row){ $bids[$row['id']]=$row['paybill']; }
				foreach($conts as $cont){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					if(isset($brans[$cont])){ $bid=$brans[$cont]; }
					else{ $res = $db->query(2,"SELECT `branch` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); $bid=$res[0]['branch']; }
					$payb = (isset($bids[$bid])) ? $bids[$bid]:array_values($bids)[0]; $sms[$cont]=str_replace("PAYBILL",$payb,$src);
				}
			}
			
			$scd = (isset($_POST['sysauto'])) ? trim($_POST['sysauto']):time();
			foreach($dels as $fon){ unset($sms[$fon]); }
			if(count($sms)){
				if($add){ $sms[$add]=array_values($sms)[0]; } $all=count($sms); $to=json_encode(array_map("clean",$sms),1); $tym=time();
				$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','multiple','$scd','$tym')");
				savelog($sid,"Added message\n { ".$smt." } to send Queue for $all clients");
			}
			echo "Success! Send Queue to $all clients created";
		}
		else{
			if($add){ array_push($conts,$add); }
			$res = sendSMS(implode(",",$conts),$mssg);
			if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$smt." } to ".substr($res,7)); }
			echo $res;
		}
		
	}
	
	# send arrears SMS
	if(isset($_POST['rmsto'])){
		$vtp = trim($_POST['rmsto']);
		$mssg = $smt = trim($_POST['rmsg']);
		$adto = trim($_POST['addto']);
		$data = json_decode(trim($_POST[$vtp]),1);
		insertSQLite("tempinfo","REPLACE INTO `sitecache` VALUES('arrearsms','$mssg')");
		
		if(strpos($mssg,"CLIENT")!==false or strpos($mssg,"ARREARS")!==false or strpos($mssg,"STARTDAY")!==false){
			$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($mssg));
			if(strpos($mssg,"CLIENT")!==false){
				foreach($data as $cont=>$desc){
					$sms[$cont] = str_replace("CLIENT",explode(" ",prepare(ucwords($desc['name'])))[0],$mssg); 
				}
			}
			if(strpos($mssg,"ARREARS")!==false){
				foreach($data as $cont=>$desc){
					$src = (isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$sms[$cont] = str_replace("ARREARS",number_format($desc['amount']),$src);
				}
			}
			if(strpos($mssg,"STARTDAY")!==false){
				foreach($data as $cont=>$desc){
					$src = (isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$sms[$cont] = str_replace("STARTDAY",date("d-m-Y",$desc['from']),$src);
				}
			}
			if(strpos($mssg,"IDNO")!==false){
				foreach($data as $cont=>$desc){
					$src = (isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$sms[$cont] = str_replace("IDNO",$desc['idno'],$src);
				}
			}
			if(strpos($mssg,"PAYBILL")!==false){
				$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
				foreach($qri as $row){ $bids[$row['id']]=$row['paybill']; }
				foreach($data as $cont=>$desc){
					$fon = ltrim($cont,"254"); $src=(isset($sms[$cont])) ? $sms[$cont]:$mssg;
					$res = $db->query(2,"SELECT `branch` FROM `org".$cid."_clients` WHERE `contact`='$fon'"); $bid=$res[0]['branch']; 
					$payb = (isset($bids[$bid])) ? $bids[$bid]:array_values($bids)[0]; $sms[$cont]=str_replace("PAYBILL",$payb,$src);
				}
			}
			
			if($adto){ $sms[$adto]=array_values($sms)[0]; } $all=count($sms); $to=json_encode(array_map("clean",$sms),1); $tym=time();
			$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($smt)."','$to','multiple','$tym','$tym')");
			savelog($sid,"Added message\n { ".$smt." } to send Queue for $all clients");
			echo "Success! Send Queue to $all clients created";
		}
		else{
			if($adto){ array_push($data,$adto); }
			$res = sendSMS(implode(",",$data),$mssg);
			if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$smt." } to ".substr($res,7)); }
			echo $res;
		}
	}
	
	@ob_end_flush();
?>