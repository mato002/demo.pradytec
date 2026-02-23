<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# approve leave
	if(isset($_POST['apprlv'])){
		$val = trim($_POST['apprlv']);
		$lid = trim($_POST['lsid']);
		$nxt = trim($_POST['nval']);
		$otp = trim($_POST['otpv']);
		$com = clean($_POST['comment']);
		$save = ($val) ? $nxt:15;
		$txt = ($save==15) ? "Declined":"Approved";
		
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; $myname=prepare(ucwords($me['name']));
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='leave'");
				$sql = $db->query(2,"SELECT *FROM `org".$cid."_leaves` WHERE `id`='$lid'"); $row=$sql[0]; 
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $uid=$row['staff']; $user=staffInfo($uid); $dls=[];
				if($user['position']=="assistant"){ $user['position']=json_decode($user['config'],1)['position']; }
				foreach($levels as $key=>$one){
					if(isset($one[$user["position"]])){if($one[$user["position"]]){ $dls[$key]=$one[$user["position"]]; }}
				}
		
				$dcd=count($dls); $apprv = ($row['approvals']==null) ? []:json_decode($row['approvals'],1); $nst=$nxt+1;
				$lvtp=$row['leave_type']; $days=$row['days']; $usa=prepare(ucwords($user['name']));
				$apprv[$sid]=$com; $cond=(count($apprv)==$dcd) ? 1:0; $app=json_encode($apprv,1);
				$sval = ($save!=15 && $cond) ? time():$save;
				$dur = date("M d,Y",$row['leave_start'])." to ".date("M d,Y",$row['leave_end']);
				if($row['status']>=15){ echo "success"; exit(); }
				
				if($db->execute(2,"UPDATE `org".$cid."_leaves` SET `status`='$sval',`approvals`='$app' WHERE `id`='$lid'")){
					$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
					if($cond or $save==15){ sendSMS($user['contact'],"Hi $usa, your leave application has been $txt by $myname"); }
					if($cond && $save!=15){
						$qry = $db->query(1,"SELECT *FROM `leave_settings` WHERE `leave_type`='$lvtp' AND `client`='$cid'");	
						if($qry){
							$sett = json_decode($qry[0]['setting'],1); $reimb=$sett['reimburse']; $susp=$sett['suspension'];
							$rem = ($user['leaves']<$days && $reimb) ? "'0'":"(leaves-$days)";
							$rem.= ($susp) ? ",`status`='2'":"";
						}
						else{ $rem="(leaves-$days)"; }
						
						$db->execute(2,"UPDATE `org".$cid."_staff` SET `leaves`=$rem WHERE `id`='$uid'");
						$qri = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='leave'");
						$notify = ($qri) ? $qri[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $mto=(isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact'];
							notify([$notify,$mto,"hr/leaves.php?manage"],"Hi $uto, staff $usa applied for leave from $dur, $myname has confirmed now");
						}
					}
					
					if(!$cond){
						if(isset($dls[$nst])){
							$grp = $dls[$nst]; $cont=0;
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD' AND NOT `position`='assistant' AND `status`='0'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$grp])){
										if($post[$grp]=="hq"){
											$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
											$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
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
											if($row['branch']==$me['branch']){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break;
											}
										}
									}
								}
								if($cont){ notify([$uid,$cont,"hr/leaves.php?manage"],"Hi $name, $myname has approved leave for staff $usa now waiting your approval"); }
							}
						}
					}
					
					savelog($sid,"$txt Staff Leave for ".$user['name']);
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# save leave application
	if(isset($_POST['formkeys'])){
		$ltbl = "org".$cid."_leaves";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$lid = trim($_POST['id']);
		$lvtp = trim($_POST['leave_type']);
		$from = strtotime(trim($_POST['leave_start']));
		$to = strtotime(trim($_POST['leave_end']));
		$to+=($to==$from) ? 64800:0;
		$to = ($from>$to) ? $from:$to;
		$dhrs = ($to==$from) ? 43200:$to-$from;
		$me = staffInfo($sid);
		$upds=$fields=$vals=$validate="";
		
		foreach($keys as $key){
			if(!in_array($key,array("id","days","leave_start","leave_end"))){
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
		
		$res1 = $db->query(2,"SELECT *FROM `$ltbl` WHERE `staff`='$sid' AND `status`<10 AND NOT `id`='$lid'");
		$res2 = $db->query(1,"SELECT *FROM `leave_settings` WHERE `client`='$cid' AND `leave_type`='$lvtp'");
		if($res2){
			$data = json_decode($res2[0]['setting'],1); $sat=$data['saturday'];  $sun=$data['sunday'];
			$appto=$data['application']; $cond=$data['condition']; $reimb=$data['reimburse']; $susp=$data['suspension']; $apply=1;
			if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
			if($appto!="all"){ $apply = ($me['gender']==$appto) ? 1:0; }
			
			for($i=$from; $i<=$to; $i+=86400){
				if(date("D",$i)=="Sat"){
					$diff = strtotime("2021-01-01,".explode("-",$sat)[1])-strtotime("2021-01-01,".explode("-",$sat)[0]); $dhrs-=$diff;
				}
				if(date("D",$i)=="Sun"){
					if($sun=="Off"){ $dhrs-=43200; }
					else{ $diff = strtotime("2021-01-01,".explode("-",$sun)[1])-strtotime("2021-01-01,".explode("-",$sun)[0]); $dhrs-=$diff; }
				}
			}
			
			if(!$cond){ $cond=($me['leaves']<$dhrs) ? 0:1; }
		}
		else{
			$cond=($me['leaves']<$dhrs) ? 0:1; $susp=0; $reimb=$apply=1;
			for($i=$from; $i<=$to; $i+=86400){
				if(date("D",$i)=="Sat"){ $dhrs-=14400; } 
				if(date("D",$i)=="Sun"){ $dhrs-=43200; } 
			}
		}
		
		$mlv = floor($me['leaves']/86400); $days=intval($dhrs/86400);
		$ltx = ($mlv==1) ? "1 Leave day":"$mlv leave days"; $dys=($days==1) ? "1 day":"$days days";
		
		if($res1){ echo "Failed: You have a pending leave application"; }
		elseif($validate){ echo $validate; }
		elseif(!$cond){ echo "Failed: You have $ltx remaining but your application has $dys"; }
		elseif(!$apply){ echo "Failed: You are not allowed to apply this kind of Leave"; }
		else{
			foreach(array("days"=>$dhrs,"leave_start"=>$from,"leave_end"=>$to) as $key=>$val){
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
			}
			
			if(!$db->istable(2,$ltbl)){
				$def = array("staff"=>"INT","leave_type"=>"CHAR","reason"=>"TEXT","days"=>"INT","leave_start"=>"CHAR","leave_end"=>"CHAR",
				"approvals"=>"TEXT","status"=>"INT","time"=>"INT","year"=>"INT");
				$db->createTbl(2,$ltbl,$def);
			}
			
			$ins = rtrim($vals,','); $order = rtrim($fields,',');
			$query = ($lid) ? "UPDATE `$ltbl` SET ".rtrim($upds,',')." WHERE `id`='$lid'":"INSERT INTO `$ltbl` ($order) VALUES($ins)";
			if($db->execute(2,$query)){
				if($lid==0){
					$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='leave'");
					$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $dls=[];
					foreach($levels as $key=>$one){
						if(isset($one[$me["position"]])){if($one[$me["position"]]){ $dls[$key]=$one[$me["position"]]; }}
					}
				
					if($dls){
						$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD' AND NOT `position`='assistant' AND `status`='0'");
						if($res){
							foreach($res as $row){
								$cnf = json_decode($row["config"],1);
								$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
								if(isset($post[$dls[1]])){
									if($post[$dls[1]]=="hq"){
										$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
										$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
									}
									elseif($post[$dls[1]]=="region"){
										if(isset($cnf["region"])){
											$chk = $db->query(1,"SELECT *FROM `regions` WHERE `id`='".$cnf["region"]."'");
											if(in_array($me["branch"],json_decode($chk[0]['branches'],1))){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
										}
									}
									else{
										if($row['branch']==$me['branch']){
											$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
											$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
										}
									}
								}
							}
							if($cont){
								$sname = ucwords(prepare($me['name'])); $goto="hr/leaves.php?manage";
								notify([$uid,$cont,$goto],"Hi $name, Staff $sname has applied for ".ucwords(prepare($lvtp))." for $dys now waiting for your approval");
							}
						}
					}
					else{
						$qry = $db->query(3,"SELECT *FROM `$ltbl` WHERE `staff`='$sid' ORDER BY `id` DESC LIMIT 1"); $lid=$qry[0]['id'];
						$dur = date("M d,Y",$qry[0]['leave_start'])." to ".date("M d,Y",$qry[0]['leave_end']);
						$db->execute(2,"UPDATE `org".$cid."_leaves` SET `status`='".time()."',`approvals`='[\"$sid\"]' WHERE `id`='$lid'");
						
						$qry = $db->query(1,"SELECT *FROM `leave_settings` WHERE `leave_type`='$lvtp' AND `client`='$cid'");	
						if($qry){
							$sett = json_decode($qry[0]['setting'],1); $reimb=$sett['reimburse']; $susp=$sett['suspension'];
							$rem = ($user['leaves']<$days && $reimb) ? "'0'":"(leaves-$days)";
							$rem.= ($susp) ? ",`status`='2'":"";
						}
						else{ $rem="(leaves-$days)"; }
						
						$db->execute(2,"UPDATE `org".$cid."_staff` SET `leaves`=$rem WHERE `id`='$sid'");
						$qri = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='leave'");
						$notify = ($qri) ? $qri[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $sname = ucwords(prepare($me['name'])); 
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="hr/leaves.php?manage";
							notify([$notify,$mto,$goto],"Hi $uto, staff $sname applied for leave from $dur, the leave has started running");
						}
					}
				}
				
				$txt = ($lid) ? "Updated leave application details":"Applied for $lvtp leave for $dys"; savelog($sid,$txt); 
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	ob_end_flush();
?>