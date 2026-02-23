<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# approve advance
	if(isset($_POST['apprlv'])){
		$val = trim($_POST['apprlv']);
		$aid = trim($_POST['asid']);
		$nxt = trim($_POST['nval']);
		$otp = trim($_POST['otpv']);
		$accepted = trim($_POST['accepted']);
		$com = clean($_POST['comment']);
		$save = ($val) ? $nxt:15;
		$txt = ($save==15) ? "Declined":"Approved";
		
		$me = staffInfo($sid); $myname=prepare(ucwords($me['name']));
		$cols = $db->tableFields(3,"advances$cid"); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; 
		if(!in_array("charges",$cols)){ $db->execute(3,"ALTER TABLE `advances$cid` ADD `charges` VARCHAR(255) NOT NULL AFTER `approved`"); }
			
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			elseif(!is_numeric($accepted) && $save!=15){ echo "Failed: Approved amount must be numeric!"; }
			elseif($accepted<500 && $save!=15){ echo "Failed: Minimum amount allowed is KES 500"; }
			else{
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='advance'");
				$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $hlevs=($qry) ? json_decode($qry[0]['levels'],1):[]; $dcd=count($levels);
		
				$sql = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$aid'"); $row=$sql[0];
				$apprv = ($row['approvals']==null) ? []:json_decode($row['approvals'],1); $uid=$row['staff'];
				$amnt=$row['amount']; $user = staffInfo($uid); $usa=prepare(ucwords($user['name'])); $nst=$nxt+1;
				$apprv[$sid]=$com; $cond=(count($apprv)==$dcd) ? 1:0; $app=json_encode($apprv,1);
				$sval = ($save!=15 && $cond) ? 200:$save; $txt.=($sval==200) ? " KES $accepted":"";
				if($row['status']>=15){ echo "success"; exit(); }
				
				if($db->execute(3,"UPDATE `advances$cid` SET `status`='$sval',`approvals`='$app',`approved`='$accepted' WHERE `id`='$aid'")){
					$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
					if($cond or $save==15){ sendSMS($user['contact'],"Hi $usa, your advance request of KES $amnt has been $txt by $myname"); }
					if($cond && $save!=15){
						if(!$db->istable(3,"deductions$cid")){
							$keys =json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='deductions'")[0]['fields'],1);
							$db->createTbl(3,"deductions$cid",$keys);
						}
						
						$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='charge_advance'");
						$mon = strtotime(date("Y-M")); $yr=date("Y"); $tym=time(); $set=($qry) ? explode(":",$qry[0]['value']):[0,0];
						if($set[0]){
							$cut=(count(explode("%",$set[1]))>1) ? round($accepted*explode("%",$set[1])[0]/100):intval($set[1]); 
							$db->execute(3,"UPDATE `advances$cid` SET `charges`='".json_encode(["interest"=>$cut],1)."' WHERE `id`='$aid'");
						}
						
						$db->execute(3,"INSERT INTO `deductions$cid` VALUES(NULL,'$uid','Salary advance','$accepted','$mon','$yr','200','$tym')");
						$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='advance'");
						$notify = ($qry) ? $qry[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); 
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="accounts/b2capp.php?home";
							notify([$notify,$mto,$goto],"Hi $uto, staff $usa applied for salary advance of KES $amnt,$myname $txt now waiting for disbursement");
						}
					}
					if(!$cond){
						if(isset($levels[$nst])){
							$cby = staffPost($row["staff"]);
							$super = (isset($hlevs['advance'])) ? $hlevs['advance']:[]; $cont=0;
							$pos = $levels[$nst]; $grp=(isset($super[$pos]) && isset($cby[$pos])) ? $super[$pos]:$pos; 
							
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD' AND NOT `position`='assistant' AND `status`='0'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$grp])){
										if($post[$grp]=="hq"){
											$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
											$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
										}
										elseif($post[$grp]=="region" && isset($cnf["region"])){
											$chk = $db->query(1,"SELECT *FROM `regions` WHERE `id`='".$cnf["region"]."'");
											$brans = ($chk) ? json_decode($chk[0]["branches"],1):[];
											if(in_array($me['branch'],$brans)){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
										}
										else{
											if($row["branch"]==$me['branch']){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
										}
									}
								}
								if($cont){
									$goto = "accounts/advance.php?manage";
									notify([$id,$cont,$goto],"Hi $name, $myname has approved salary advance for staff $usa now waiting your approval");
								}
							}
						}
					}
					
					savelog($sid,"$txt Staff advance request for ".$user['name']);
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# save advance
	if(isset($_POST['reason'])){
		$atbl = "advances$cid";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$aid = trim($_POST['id']);
		$otp = trim($_POST['otpv']);
		$amnt = trim($_POST['amount']);
		$me = staffInfo($sid);
		$upds=$fields=$vals=$validate="";
		
		foreach($keys as $key){
			if(!in_array($key,array("id"))){
				$val = clean(strtolower($_POST[$key]));
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
			}
		}
		
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		if(!$db->istable(3,$atbl)){
			$def = array("staff"=>"INT","amount"=>"INT","reason"=>"TEXT","approvals"=>"TEXT","approved"=>"CHAR","charges"=>"CHAR",
			"month"=>"INT","year"=>"INT","status"=>"INT","time"=>"INT");
			$db->createTbl(3,$atbl,$def);
		}
		else{
			$cols = $db->tableFields(3,"advances$cid");
			if(!in_array("charges",$cols)){ $db->execute(3,"ALTER TABLE `advances$cid` ADD `charges` VARCHAR(255) NOT NULL AFTER `approved`"); }
		}
		
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$res1 = $db->query(3,"SELECT *FROM `$atbl` WHERE `staff`='$sid' AND `status`<10 AND NOT `id`='$aid'");
		$res2 = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if(!is_numeric($amnt)){ echo "Error! Amount should be numeric!"; }
		elseif($res1){ echo "Failed: You have a pending advance application"; }
		elseif($amnt<500){ echo "Failed: Minimum amount for application is KES 500"; }
		elseif($validate){ echo $validate; }
		elseif(!$res2){ echo "Failed: Invalid OTP, try again"; }
		elseif($res2[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res2[0]['expiry']); }
		else{
			$ins = rtrim($vals,','); $order = rtrim($fields,',');
			$query = ($aid) ? "UPDATE `$atbl` SET ".rtrim($upds,',')." WHERE `id`='$aid'":"INSERT INTO `$atbl` ($order) VALUES($ins)";
			if($db->execute(3,$query)){
				if($aid==0){
					$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='advance'");
					if($app){
						$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
						$hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[];  $levels = json_decode($app[0]['levels'],1); 
						$super = (isset($hlevs["advance"])) ? $hlevs['advance']:[]; $cont=0;
						$appr = (isset($super[$levels[1]]) && $levels[1]==$me['position']) ? $super[$levels[1]]:$levels[1];
						
						$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND NOT `position`='USSD' AND `status`='0'");
						if($res){
							foreach($res as $row){
								$cnf = json_decode($row["config"],1);
								$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
								if(isset($post[$appr])){
									if($post[$appr]=="hq"){
										$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; $id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
									}
									else{
										if($row['branch']==$me['branch']){
											$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; $id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
										}
									}
								}
							}
							if($cont){
								$sname = ucwords(prepare($me['name'])); $goto="accounts/advance.php?manage";
								notify([$id,$cont,$goto],"Hi $name, Staff $sname has applied for salary advance of KES ".fnum($amnt)." now waiting for your approval");
							}
						}
					}
					else{
						$qry = $db->query(3,"SELECT *FROM `$atbl` WHERE `staff`='$sid' ORDER BY `id` DESC LIMIT 1"); $aid=$qry[0]['id'];
						$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='charge_advance'");
						$set = ($qri) ? explode(":",$qri[0]['value']):[0,0]; $chj="[]";
						if($set[0]){
							$cut = (count(explode("%",$set[1]))>1) ? round($amnt*explode("%",$set[1])[0]/100):intval($set[1]); 
							$chj = json_encode(["interest"=>$cut],1);
						}
						
						$db->execute(3,"UPDATE `advances$cid` SET `status`='200',`approvals`='[\"$sid\"]',`approved`='$amnt',`charges`='$chj' WHERE `id`='$aid'");
						if(!$db->istable(3,"deductions$cid")){
							$keys =json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='deductions'")[0]['fields'],1);
							$db->createTbl(3,"deductions$cid",$keys);
						}
						
						$mon = strtotime(date("Y-M")); $yr=date("Y"); $tym=time();
						$db->execute(3,"INSERT INTO `deductions$cid` VALUES(NULL,'$sid','Salary advance','$amnt','$mon','$yr','200','$tym')");
						
						$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='advance'");
						$notify = ($qry) ? $qry[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $sname = ucwords(prepare($me['name']));
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="accounts/b2capp.php?home";
							notify([$notify,$mto,$goto],"Hi $uto, staff $sname applied for salary advance of KES $amnt, now waiting for disbursement");
						}
					}
				}
				
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='".$me['contact']."'");
				$txt = ($aid) ? "Updated advance application details":"Applied for salary advance of KES $amnt"; savelog($sid,$txt); 
				echo "success";
			}
			else{
				echo "Failed to complete the request at the moment! Try again later";
			}
		}
	}
	
	ob_end_flush();
?>