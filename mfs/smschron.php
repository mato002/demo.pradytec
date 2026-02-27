<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(110);
	
	include "../core/functions.php";
	if(!isset($_POST['checksd'])){ exit(); }
	$db = new DBO(); $cid = CLIENT_ID;
	
	request("https://$url/core/syschron.php",["ckupdate"=>1]);
	
	# installment reminder
	if(intval(date("Hi"))<14 && intval(date("Hi"))>=8){
		$setts = array("autoisntallment_sms"=>0,"installmentdate"=>1); 
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$set = (isset($setts["autoisntallment_sms"])) ? $setts["autoisntallment_sms"]:0; 
		$hr  = (isset($setts["autosms_time"])) ? $setts['autosms_time']:"04:00";
		$intrcol = (isset($sett["intrcollectype"])) ? $sett["intrcollectype"]:"Accrued";
		$scd = strtotime(date("Y-M-d").",$hr"); $tdy=strtotime(date("Y-M-d")); $idays=array($tdy+86400,$tdy,$tdy-86400);
		
		if($set){
			$chk = $db->query(1,"SELECT *FROM `sms_schedule` WHERE `client`='$cid' AND `schedule`='$scd' AND `type`='multiple'");
			if(!$chk){
				$def = "Dear CLIENT,\r\nKindly make your Installment of Ksh AMOUNT by DATE before 12 using A/C IDNO.\r\nThank you.";
				$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND `name`='inst200'");
				$mssg = ($res) ? prepare($res[0]['message']):$def; $day=$idays[$setts["installmentdate"]];
				
				$spec = (defined("SPECIAL_COLLECTION_PRODS")) ? SPECIAL_COLLECTION_PRODS:[]; $specs=[];
				if($spec){
					foreach($spec as $pid){
						$qri = $db->query(2,"SELECT ln.phone,sd.balance FROM `org$cid"."_loans` AS ln INNER JOIN `org$cid"."_schedule` AS sd ON ln.id=sd.loan WHERE sd.balance>0 
					AND ln.loan_product='$pid' AND sd.day='".$idays[0]."'");
						if($qri){
							foreach($qri as $row){ $specs[$row['phone']]=$row["balance"]; $conts[$row["phone"]]="254".$row['phone']; }
						}
					}
				}
				
				$qri = $db->query(2,"SELECT ln.phone,ln.loan FROM `org$cid"."_schedule` AS sd INNER JOIN `org$cid"."_loans` AS ln ON ln.id=sd.loan WHERE sd.balance>0 AND sd.day='$day'");
				if($qri){
					foreach($qri as $row){
						if($intrcol=="Cash"){ setInterest($row["loan"]); }
						$conts[$row["phone"]]="254".$row['phone'];
					}
					request("https://$url/mfs/dbsave/sendsms.php",["sysauto"=>$scd,"pday"=>$day,"phones"=>json_encode($conts,1),"clmssg"=>$mssg,"addto"=>0,"spec"=>$specs]);
				}
			}
		}
	}

	$tym = time();
	$qri = $db->query(1,"SELECT *FROM `sms_schedule` WHERE `client`='$cid' AND `schedule`<=$tym");
	if($qri){
		foreach($qri as $row){
			$conts=json_decode($row['contacts'],1); $rid=$row['id'];
			if($row['type']=="multiple"){
				if($con = $db->mysqlcon(1)){
					$con->autocommit(0); $con->query("BEGIN"); $sent=[];
					$sql = $con->query("SELECT *FROM `sms_queues` WHERE `sid`='$rid' FOR UPDATE");
					if($sql->num_rows){
						while($one=$sql->fetch_assoc()){ unset($conts[$one['phone']]); }
					}
					
					foreach(array_slice($conts,0,60,1) as $cont=>$mssg){
						$res = sendSMS($cont,str_replace("~nl~","\r\n",prepare($mssg)));
						if(substr(trim($res),0,7)=="Sent to"){
							$con->query("INSERT IGNORE INTO `sms_queues` VALUES('$cont','$rid')"); $sent[]=$cont; unset($conts[$cont]);
						}
					}
					
					if($sent){
						if($conts){ $con->query("UPDATE `sms_schedule` SET `contacts`='".json_encode($conts,1)."' WHERE `id`='$rid'"); }
						else{
							$con->query("DELETE FROM `sms_schedule` WHERE `id`='$rid'"); 
							$con->query("DELETE FROM `sms_queues` WHERE `sid`='$rid'");
						}
					}

					$con->commit(); $con->close();
				}
			}
			else{
				$res = sendSMS(implode(",",$conts),str_replace("~nl~","\r\n",prepare($row['message'])));
				if(substr($res,0,7)=="Sent to"){
					$db->execute(1,"DELETE FROM `sms_schedule` WHERE `id`='$rid'"); 
				}
			}
		}
	}

?>