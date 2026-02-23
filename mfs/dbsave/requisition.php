<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# approve requisition
	if(isset($_POST['appreq'])){
		$nxt = trim($_POST['appreq']);
		$rid = trim($_POST['reqnid']);
		$otp = trim($_POST['otpv']);
		$rtbl = "requisitions$cid"; 
	
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; $myname=prepare(ucwords($me['name']));
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='requisition'");
				$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $hlevs = ($qry) ? json_decode($qry[0]['levels'],1):[]; 
				$dcd=count($levels); $total=$no=$cnd=0; $cles=[];
		
				$sql = $db->query(3,"SELECT *FROM `$rtbl` WHERE `id`='$rid'"); $row=$sql[0];
				$apprv=$prev=($row['approvals']==null) ? []:json_decode($row['approvals'],1);
				
				foreach($_POST['qnty'] as $key=>$num){
					$price=(isset($_POST['price'][$key])) ? trim($_POST['price'][$key]):0; $qty=($num>0) ? $num:0; 
					$cost=($price>0) ? $price:0; $total+=$cost*$qty; $cles[$key]=$qty;
					if(count($prev)){
						if(isset($prev[$key][$sid])){ $no=1; break; }
					}
					$apprv[$key][$sid]=array($qty,$cost);
				}
				
				if($no){
					echo "Failed: You already approved requisition from previous stage! You can only approve once for integrity issues!"; exit();
				}
				
				if($total<1){
					$des = json_decode($_POST['tcls'],1);
					foreach($cles as $key=>$qn){
						$cnd +=(in_array($key,$des) && $qn>0) ? 1:0;
					}
				}
				
				$cond=($nxt==$dcd && $total>0) ? 1:0; $app=json_encode($apprv,1); $nst=$nxt+1;
				$sv1 = ($total>0) ? $nxt:15; $sv2=($cond) ? 200:$sv1; $sval=($cnd) ? 150:$sv2;
				$txt = ($sval==15) ? "Declined":"Approved";
				
				if($db->insert(3,"UPDATE `$rtbl` SET `status`='$sval',`approvals`='$app',`approved`='$total' WHERE `id`='$rid'")){
					$db->insert(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
					if($cond && $sval>150){
						$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='requisition'");
						$notify = ($qry) ? $qry[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $no=prenum($rid); $cost=number_format($total);
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="accounts/b2capp.php?home";
							notify([$notify,$mto,$goto],"Hi $uto, $myname has approved requisition no $no worth KES $cost now waiting for disbursement");
						}
					}
					if(!$cond){
						if(isset($levels[$nst])){
							$cby = staffPost($row['staff']);
							$super = (isset($hlevs["requisition"])) ? $hlevs['requisition']:[]; $cont=0;
							$pos = $levels[$nst]; $grp=(isset($super[$pos]) && isset($cby[$pos])) ? $super[$pos]:$pos; 
							
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$grp])){
										if($post[$grp]=="hq"){
											$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
											$uid=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
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
												$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$uid=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
										}
									}
								}
								if($cont){
									$no=prenum($rid); $cost=fnum($total); $goto="accounts/requisition.php?view";
									notify([$uid,$cont,$goto],"Hi $name, $myname has approved requisition no $no worth KES $cost now waiting for your approval");
								}
							}
						}
					}
					
					savelog($sid,"$txt requisition number ".prenum($rid));
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# decline requisition
	if(isset($_POST['declinereqn'])){
		$rid = trim($_POST['declinereqn']);
		$otp = trim($_POST['otpv']);
		
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); exit(); }
			if($db->insert(3,"UPDATE `requisitions$cid` SET `status`='15',`approved`='0' WHERE `id`='$rid'")){
				$db->insert(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
				savelog($sid,"Declined requisition number ".prenum($rid));
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# save requisition
	if(isset($_POST['formkeys'])){
		$rtbl = "requisitions$cid";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$rid = trim($_POST['id']);
		$otp = trim($_POST['otpv']);
		$ebk = trim($_POST["expbook"]);
		$_POST['branch']=trim($_POST['rbran']);
		$me = staffInfo($sid); $myname=prepare(ucwords($me['name']));
		$upds=$fields=$vals=$validate=""; $total=0;
		
		foreach($_POST['item'] as $key=>$item){
			$qty=trim($_POST['qnty'][$key]); $cat=trim($_POST['categ'][$key]); $cost=trim($_POST['prices'][$key]); $total+=$qty*$cost;
			$data[]=array("item"=>clean($item),"type"=>$cat,"qty"=>$qty,"cost"=>$cost);
		}
		
		$_POST['cost']=$brem=$total; $data["book"]=$ebk;
		foreach($keys as $key){
			if(!in_array($key,array("id","item","qnty","prices","categ"))){
				$val = ($key=="item_description") ?  json_encode($data,1):clean(strtolower($_POST[$key]));
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
		
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if($db->istable(3,"budgets$cid") && $ebk){
			$tmon = strtotime(date("Y-M")); $bid=trim($_POST["branch"]);
			$sql = $db->query(3,"SELECT *FROM `budgets$cid` WHERE `book`='$ebk' AND `month`='$tmon' AND `branch`='$bid'");
			if($sql){
				$qri = $db->query(3,"SELECT SUM(amount) AS tsum FROM `transactions$cid` WHERE `account`='$ebk' AND `type`='debit' AND `branch`='$bid' AND `month`='$tmon'");
				$tbd = $sql[0]['amount']; $used=intval($qri[0]['tsum']); $brem=$tbd-$used;
			}
		}
		
		if(!$ebk && !$rid){ echo "Error! Select Expense book first!"; }
		elseif($validate){ echo $validate; }
		elseif(!$res){ echo "Failed: Invalid OTP, try again"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		elseif($brem<$total){ echo "Failed: Available budget for account is ".number_format($brem); }
		else{
			$ins = rtrim($vals,','); $order = rtrim($fields,','); $reqn=$rid;
			$query = ($rid) ? "UPDATE `$rtbl` SET ".rtrim($upds,',')." WHERE `id`='$rid'":"INSERT INTO `$rtbl` ($order) VALUES($ins)";
			if($db->insert(3,$query)){
				if($rid==0){
					$qry = $db->query(3,"SELECT *FROM `$rtbl` WHERE `staff`='$sid' ORDER BY `id` DESC LIMIT 1"); $reqn=$qry[0]['id'];
					$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='requisition'");
					if($app){
						$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
						$hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[];  $levels = json_decode($app[0]['levels'],1); 
						$super = (isset($hlevs["requisition"])) ? $hlevs['requisition']:[]; $cont=0;
						$appr = (isset($super[$levels[1]]) && $levels[1]==$me['position']) ? $super[$levels[1]]:$levels[1];
						
						$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0'");
						if($res){
							foreach($res as $row){
								$cnf = json_decode($row["config"],1);
								$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
								if(isset($post[$appr])){
									if($post[$appr]=="hq"){
										$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
										$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
									}
									else{
										if($row['branch']==$me['branch']){
											$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact'];
											$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
										}
									}
								}
							}
							if($cont){
								$sname = ucwords(prepare($me['name'])); $goto="accounts/requisition.php?view";
								notify([$uid,$cont,$goto],"Hi $name, $sname has created a new requisition no ".prenum($reqn)." now waiting for your approval");
							}
						}
					}
					else{
						$db->execute(3,"UPDATE `$rtbl` SET `status`='200',`approvals`='[\"$sid\"]',`approved`='$total' WHERE `id`='$reqn'");
						$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='requisition'");
						$notify = ($qry) ? $qry[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $no=prenum($reqn); $cost=fnum($total);
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="accounts/b2capp.php?home";
							notify([$notify,$mto,$goto],"Hi $uto, $myname has created requisition no $no worth KES $cost now waiting for disbursement");
						}
					}
				}
				
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='".$me['contact']."'");
				$txt = ($rid) ? "Updated requisition details":"Created new requisition no ".intval($reqn); savelog($sid,$txt); 
				echo "success:$reqn";
			}
			else{
				echo "Failed to complete the request at the moment! Try again later";
			}
		}
	}

	@ob_end_flush();
?>