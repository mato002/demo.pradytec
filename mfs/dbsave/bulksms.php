<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# fetch contacts from excel
	if(isset($_FILES['xls'])){
		$tmp = $_FILES['xls']['tmp_name'];
		$name = trim(strtolower($_FILES['xls']['name']));
		$ext = @array_pop(explode(".",$name));
		$mssg = $nms = trim($_POST['smssg']);
		$contf = trim($_POST['contfld']);
		$namef = trim($_POST['namefld']);
		$stime = strtotime(str_replace("T",",",trim($_POST['sendtm'])));
		
		if(!in_array($ext,array("xls","xlsx","csv"))){ echo "Failed: File format not supported!"; }
		elseif($mssg==null){ echo "Failed to retrieve your prepared message"; }
		else{
			require "../../xls/index.php";
			$file = time().".$ext";
			
			if(move_uploaded_file($tmp,$file)){
				$mssg = str_replace(array("\r\n","\n"),"~nl~",clean($mssg));
				$data = openExcel($file); $conts=[]; $cols=["A","B"]; $av=0;
				
				foreach($data as $row){
					if(strpos($mssg,"CLIENT")!==false){
						$cont = (array_key_exists($contf,$row)) ? str_replace("-","",$row[$contf]):0; 
						$name = (array_key_exists($namef,$row)) ? $row[$namef]:null;
						$sms = ($name) ? str_replace("CLIENT",explode(" ",trim($name))[0],$mssg):$mssg;
						if($cont && is_numeric($cont)){ $conts["254".ltrim($cont,"254")]=$sms; $av++; }
					}
					else{ if(array_key_exists($contf,$row)){ $conts[]=$row[$contf]; } }
				}
				
				if(count($conts)){
					if($stime<=time()){
						if($av){
							$all = count($conts); $to=json_encode($conts,1); $tym=time();
							$db->insert(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($nms)."','$to','multiple','$stime','$tym')");
							savelog($sid,"Added message\n { ".$nms." } to send Queue from Excel file to $all clients");
							echo "Success! Send Queue to $all clients created";
						}
						else{
							$res = sendSMS(implode(",",$conts),str_replace("~nl~","\r\n",$nms));
							if(substr($res,0,7)=="Sent to"){ savelog($sid,"Sent Message\n { ".$nms." } from Excel file to ".substr($res,7)); }
							echo $res;
						}
					}
					else{
						$smtp = ($av) ? "multiple":"similar"; $to=json_encode($conts,1); $all=count($conts); $tym=time();
						if($db->insert(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','".clean($nms)."','$to','$smtp','$stime','$tym')")){
							savelog($sid,"Created Message schedule { ".$nms." } to be sent at ".date("M d,Y h:i a",$stime)." to $all clients");
							echo "Success! Schedule created for $all clients";
						}
						else{ echo "Failed to create schedule at the moment"; }
					}
				}
				else{ echo "Failed: No contacts loaded from Excel Column $cols[$contf]. Please confirm your excel columns well"; }
				unlink($file);
			}
			else{ echo "Failed to upload document! Try again later"; }
		}
	}
	
	#save template
	if(isset($_POST['savetemp'])){
		$tid = trim($_POST['savetemp']);
		$name= clean(strtolower($_POST['tname']));
		$mssg= clean($_POST['tmssg']);
		$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `name`='$name' AND `client`='$cid' AND NOT `id`='$tid'");
		
		if($res){
			echo "Failed: Template ".prepare($name)." already exists";
		}
		else{
			if($tid){
				$db->insert(1,"UPDATE `sms_templates` SET `name`='$name',`message`='$mssg' WHERE `id`='$tid'");
				savelog($sid,"Updated message template $name");
			}
			else{
				$db->insert(1,"INSERT INTO `sms_templates` VALUES(NULL,'$cid','$name','$mssg')");
				savelog($sid,"Created message template $name");
			}
			echo "success";
		}
	}
	
	# save sms temporary
	if(isset($_POST['tmpmssg'])){
		$mssg = clean($_POST['tmpmssg']);
		insertSQLite("tempinfo","CREATE TABLE IF NOT EXISTS tempdata (user INTEGER PRIMARY KEY,data TEXT)");
		if(insertSQLite("tempinfo","REPLACE INTO `tempdata` VALUES('$sid','$mssg')")){
			echo "success";
		}
		else{ echo "Failed to save info"; }
	}
	
	# request payment push
	if(isset($_POST['rphone'])){
		$phone = ltrim(trim($_POST['rphone']),"0");
		$amnt = trim($_POST['mamnt']);
		
		if(!is_numeric($phone)){ echo "Error: phone number should be numeric"; }
		elseif(!is_numeric($amnt)){ echo "Error: Amount should be numeric"; }
		elseif(strlen($phone)!=9){ echo "Invalid phone number! It should have 9 numbers but ".strlen($phone)." provided"; }
		else{
			echo paysms("254$phone",$amnt);
		}
	}
	
	#delete schedule
	if(isset($_POST['delschd'])){
		$id=trim($_POST['delschd']);
		$db->insert(1,"DELETE FROM `sms_schedule` WHERE `id`='$id'");
		echo "success";
	}
	
	#delete template
	if(isset($_POST['deltmp'])){
		$id=trim($_POST['deltmp']);
		$db->insert(1,"DELETE FROM `sms_templates` WHERE `id`='$id'");
		echo "success";
	}
	
	ob_end_flush();
?>