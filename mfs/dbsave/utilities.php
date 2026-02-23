<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST["wb2c"])){ exit(); }
	$sid = (isset($_POST["wb2c"])) ? trim($_POST["wb2c"]):substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	require "../../xls/index.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# approve utility expense
	if(isset($_POST['appreq'])){
		$req = $_POST['appreq'];
		$otp = trim($_POST['otpv']);
		$rtbl = "utilities$cid";
		$ids = (isset($_POST["utilapp"])) ? $_POST["utilapp"]:[$_POST['reqnid']."-$req"];
	
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; $myname=prepare(ucwords($me['name']));
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='utilities'");
				$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $hlevs=($qry) ? json_decode($qry[0]['levels'],1):[]; 
				$dcd = count($levels); $done=$succ=0; $cles=[]; $error="";
				
				foreach($ids as $one){
					$rid = explode("-",$one)[0]; $nxt=explode("-",$one)[1]; $total=$cnd=$iswal=0;
					$sql = $db->query(3,"SELECT *FROM `$rtbl` WHERE `id`='$rid'"); $row=$sql[0];
					$apprv=$prev=($row['approvals']==null) ? []:json_decode($row['approvals'],1);
					$dto = json_decode($row['recipient'],1); $fee=(isset($dto["fee"])) ? $dto["fee"]:0; 
					foreach($dto["book"] as $bk=>$sum){ $iswal+=(!is_numeric($bk)) ? 1:0; }
					
					if(isset($_POST["qnty"])){
						foreach($_POST['qnty'] as $key=>$num){
							$price = (isset($_POST['price'][$key])) ? trim($_POST['price'][$key]):0; $qty=($num>0) ? $num:0; 
							$cost = ($price>0) ? $price:0; $total+=$cost*$qty; $cles[$key]=$qty;
							if(isset($prev[$key][$sid])){ $done=1; break; }
							$apprv[$key][$sid]=array($qty,$cost);
						}
					}
					else{
						$done = (isset($prev[0][$sid])) ? 1:0;
						$apprv[0][$sid]=array(1,intval($req[$rid])); $total+=$req[$rid]; $cles[]=1;
					}
					
					if($done){ continue; }
					if($total<1){
						$des = json_decode($_POST['tcls'],1);
						foreach($cles as $key=>$qn){ $cnd +=(in_array($key,$des) && $qn>0) ? 1:0; }
					}
					
					$cond = ($nxt==$dcd && $total>0) ? 1:0; $app=json_encode($apprv,1); $nst=$nxt+1;
					$sv1 = ($total>0) ? $nxt:15; $sv2=($cond) ? 200:$sv1; $sval=($cnd) ? 150:$sv2;
					$txt = ($sval==15) ? "Declined":"Approved"; $total+=($total>0) ? $fee:0;
					$mtx = ($iswal) ? "Withdrawal request":"Vendor payment"; $now=time();
					
					if($db->execute(3,"UPDATE `$rtbl` SET `status`='$sval',`approvals`='$app',`approved`='$total' WHERE `id`='$rid'")){
						$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'"); $succ++;
						if($cond && $sval>150){
							$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='utilities'");
							$notify = ($qry) ? $qry[0]['staff']:1;
							if($notify){
								$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $no=prenum($rid); $goto="accounts/b2capp.php?home";
								$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $cost=fnum($total-$fee);
								notify([$notify,$mto,$goto],"Hi $uto, $myname has approved $mtx no $no of KES $cost now waiting for disbursement");
							}
						}
						
						if(!$cond){
							if(isset($levels[$nst])){
								$cby = staffPost($row['staff']);
								$super = (isset($hlevs["utilities"])) ? $hlevs['utilities']:[]; $cont=0;
								$pos = $levels[$nst]; $grp=(isset($super[$pos]) && isset($cby[$pos])) ? $super[$pos]:$pos; 
								
								$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0'");
								if($res){
									foreach($res as $row){
										$cnf = json_decode($row["config"],1);
										$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
										if(isset($post[$grp])){
											if($post[$grp]=="hq"){
												$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$uid = $row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
											elseif($post[$grp]=="region" && isset($cnf["region"])){
												$chk = $db->query(1,"SELECT *FROM `regions` WHERE `id`='".$cnf["region"]."'");
												$brans = ($chk) ? json_decode($chk[0]["branches"],1):[];
												if(in_array($me['branch'],$brans)){
													$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
													$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
												}
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
										$no=prenum($rid); $cost=fnum($total-$fee); $goto="accounts/utilities.php?view";
										notify([$uid,$cont,$goto],"Hi $name, $myname has approved $mtx no $no of KES $cost now waiting for your approval");
									}
								}
							}
						}
						savelog($sid,"$txt $mtx number ".prenum($rid));
					}
					else{ $error = "Failed to approve utility no ".prenum($rid); break; }
				}
				
				if(!$succ){ echo ($done) ? "Failed: You already approved from previous stage! You can only approve once for integrity issues!":$error; }
				elseif($error){ echo $error; }
				else{ echo "success"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# decline utility expense
	if(isset($_POST['declinereqn'])){
		$rid = trim($_POST['declinereqn']);
		$otp = trim($_POST['otpv']);
		
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); exit(); }
			if($db->execute(3,"UPDATE `utilities$cid` SET `status`='15',`approved`='0' WHERE `id`='$rid'")){
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
				savelog($sid,"Declined vendor payment number ".prenum($rid));
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# save utility expense
	if(isset($_POST['formkeys'])){
		$rtbl = "utilities$cid";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$rid = intval($_POST['id']);
		$otp = trim($_POST['otpv']);
		$dto = intval($_POST['recipient']);
		$bks = $_POST['baccs'];
		$dmd = (isset($_POST["pmode"])) ? clean($_POST["pmode"]):"b2c";
		$nme = (isset($_POST["recname"])) ? clean($_POST["recname"]):"";
		$smsnotf = (isset($_POST["smnot"])) ? trim($_POST["smnot"]):0;
		
		$_POST['branch']=trim($_POST['rbran']);
		$me = staffInfo($sid); $myname=prepare(ucwords($me['name']));
		$upds=$fields=$vals=$validate=$error=""; $bksf=[]; $total=$iswal=$fee=$jn=0;
		$dls = array("b2c"=>"phone","b2b"=>"paybill","till"=>"tillno");
		
		foreach($_POST['item'] as $key=>$item){
			if(!$bks[$key]){ $error = "Error! Select Journal account for $item"; break; }
			$qty=trim($_POST['qnty'][$key]); $cat="cash"; $cost=trim($_POST['prices'][$key]); $total+=$qty*$cost; $bc=$bks[$key]; $jn++; 
			$data[] = array("item"=>clean($item),"type"=>$cat,"qty"=>$qty,"cost"=>$cost); $dc="$bc-$jn"; $iswb=(count(explode(":",$bc))>1) ? 1:0;
			if($iswb){ $dnt=explode(":",$bc); $dc=$dnt[0]."-$jn:".$dnt[1]; }
			$bksf[$dc] = (isset($bksf[$dc])) ? $bkfs[$dc]+=($qty*$cost):$qty*$cost;
			if($iswb){
				$bal = walletBal(explode(":",$bc)[1]); $iswal++;
				if($bksf[$dc]>$bal && !isset($_POST["isovd"])){ $error = "Error! Insufficient Account balance. Available balance is $bal"; }
			}
		}
		
		if($iswal){
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_withdrawal_fees'");
			$fees = ($chk) ? json_decode($chk[0]['value'],1):B2C_RATES; 
			if($fees){
				foreach($fees as $lim=>$rate){
					$range = explode("-",$lim); $fee=$rate;
					if($total>=$range[0] && $total<=$range[1]){ $fee=$rate; break; }
				}
			}
			if(($total+$fee)>$bal && !$error && !isset($_POST["isovd"])){ $error = "Insufficient balance to cater for Withdrawal fee of Ksh $fee"; }
		}
		
		$intr = (isset($_POST["isovd"])) ? trim($_POST["isovd"]):0;
		$reqam = $total; $total+=$fee; $_POST['cost']=$total;
		
		foreach($keys as $key){
			if(!in_array($key,array("id","item","qnty","prices"))){
				$val = ($key=="item_description") ?  json_encode($data,1):clean(strtolower($_POST[$key]));
				$val = ($key=="recipient") ? json_encode([$dls[$dmd]=>$dto,"name"=>"","book"=>$bksf,"tname"=>$nme,"fee"=>$fee,"mode"=>$dmd,"intr"=>$intr],1):$val;
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
		
		if($db->istable(3,"budgets$cid") && !$error){
			$tmon = strtotime(date("Y-M")); $bid=trim($_POST["branch"]);
			foreach($bks as $key=>$abk){
				if($abk){
					$sql = $db->query(3,"SELECT *FROM `budgets$cid` WHERE `book`='$abk' AND `month`='$tmon' AND `branch`='$bid'");
					if($sql){
						$qri = $db->query(3,"SELECT SUM(amount) AS tsum FROM `transactions$cid` WHERE `account`='$abk' AND `type`='debit' AND `branch`='$bid' AND `month`='$tmon'");
						$tbd = $sql[0]['amount']; $used=intval($qri[0]['tsum']); $brem=$tbd-$used; $cst=$data[$key]["cost"]*$data[$key]["qty"];
						if($brem<$cst){ $error = "Failed: Available budget for ".prepare($data[$key]["item"])." is ".number_format($brem); break; }
					}
				}
			}
		}
		
		if(strlen($dto)!=9 && $dmd=="b2c"){ echo "Failed: Invalid recipient MPESA number"; }
		elseif($error){ echo $error; }
		elseif($validate){ echo $validate; }
		elseif(!$res){ echo "Failed: Invalid OTP, try again"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		else{
			$ins = rtrim($vals,','); $order = rtrim($fields,','); $reqn=$rid; $mtx=($iswal) ? "Withdrawal request":"Vendor payment";
			$query = ($rid) ? "UPDATE `$rtbl` SET ".rtrim($upds,',')." WHERE `id`='$rid'":"INSERT INTO `$rtbl` ($order) VALUES($ins)";
			if($db->execute(3,$query)){
				if($rid==0 && !$smsnotf){
					$qry = $db->query(3,"SELECT *FROM `$rtbl` WHERE `staff`='$sid' ORDER BY `id` DESC LIMIT 1"); $reqn=$qry[0]['id'];
					$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='utilities'");
					if($app){
						$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
						$hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[]; $levels=json_decode($app[0]['levels'],1); 
						$super = (isset($hlevs['utilities'])) ? $hlevs['utilities']:[]; $cost=fnum($reqam); $cont=0;
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
								$goto="accounts/utilities.php?view"; $fname=ucwords(prepare($me['name']));
								notify([$uid,$cont,$goto],"Hi $name, $fname has created a new $mtx no ".prenum($reqn)." of KES $cost now waiting for your approval");
							}
						}
					}
					else{
						$db->execute(3,"UPDATE `$rtbl` SET `status`='200',`approvals`='[\"$sid\"]',`approved`='$total' WHERE `id`='$reqn'");
						$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='utilities'");
						$notify = ($qry) ? $qry[0]['staff']:1;
						if($notify){
							$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $no=prenum($reqn); 
							$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto="accounts/b2capp.php?home";
							notify([$notify,$mto,$goto],"Hi $uto, $myname has created $mtx no $no of KES $cost now waiting for disbursement");
						}
					}
				}
				
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
				$txt = ($rid) ? "Updated $mtx no $rid details":"Created new $mtx no ".intval($reqn); savelog($sid,$txt); 
				echo "success:$reqn";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# import data
	if(isset($_POST["importcols"])){
		$cols = json_decode(trim($_POST["importcols"]),1);
		$tmp = $_FILES['uxls']["tmp_name"];
		$doc = strtolower(trim($_FILES['uxls']['name']));
		$ext = @end(explode(".",$doc));
		$otp = trim($_POST["otpv"]);
		
		$me = staffInfo($sid); $myname=prepare(ucwords($me['name']));
		$dls = array("b2c"=>"phone","b2b"=>"paybill","till"=>"tillno");
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		
		if(!in_array($ext,array("csv","xls","xlsx"))){ echo "Failed: File formart $ext is not supported! Choose XLS,CSV & XLSX files"; }
		elseif(!$res){ echo "Failed: Invalid OTP, try again"; }
		elseif($res[0]['expiry']<time()){ echo "Failed: Your OTP expired on ".date("d-m-Y, h:i A",$res[0]['expiry']); }
		else{
			if(move_uploaded_file($tmp,"../../docs/temp.$ext")){
				$chk = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
				$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0'");
				foreach($chk as $row){ $brans[$row["id"]]=strtolower($row["branch"]); }
				foreach($qri as $row){ $accs[$row["id"]]=strtolower($row["account"]); }
				
				$data = openExcel("../../docs/temp.$ext"); $errors=[]; $jn=0;
				foreach(array_slice($data,1) as $key=>$one){
					if(count($one)>=count($cols)){
						$mname = trim($one[array_search("mpesa_name",$cols)]); $vals=[];
						if(!$mname){ continue; }
						foreach($cols as $k=>$col){
							$vals[$col]=clean($one[$k]);
							if($col=="cost"){
								$amnt = intval(str_replace(",","",$one[$k])); $vals[$col]=$amnt;
								if($amnt<=0){ $errors[]="Invalid amount $amnt for $mname"; break; }
							}
							
							if($col=="branch"){
								$bran = clean(strtolower(trim($one[$k]))); $bid=array_search($bran,$brans); $vals[$col]=$bid;
								if(!in_array($bran,$brans)){
									if(in_array($bran,["corporate","head officer"])){ $vals[$col]=0; }
									else{ $errors[]="Unrecognized branch ".prepare($bran)." for $mname"; break; }
								}
							}
							
							if($col=="recipient_mpesa_number"){
								$dto = intval($one[$k]); $cont=(substr($dto,0,3)==254) ? substr($dto,3):$dto; $vals[$col]=$cont;
								if(strlen($cont)!=9){ $errors[]="Invalid MPESA Number $cont for $mname"; break; }
							}
							
							if($col=="journal_account"){
								$jrnl = strtolower(clean($one[$k])); $acc=array_search($jrnl,$accs); $vals[$col]=$acc;
								if(!in_array($jrnl,$accs)){ $errors[]="Unrecognized Journal Acc ".$one[$k]." for $mname"; break; }
							}
							
						}
						
						if($errors){ break; }
						else{
							$acc = $vals["journal_account"]; $jn++; $dc="$acc-$jn"; $bks=[$dc=>$amnt]; 
							$items[] = array(["item"=>$vals["item_description"],"type"=>"cash","qty"=>1,"cost"=>$vals["cost"]]);  
							$vals["recipient"]=json_encode(["phone"=>$cont,"name"=>"","book"=>$bks,"tname"=>$mname,"fee"=>0,"mode"=>"b2c","intr"=>0],1); $others[]=$vals;
						}
					}
				}
				
				if($errors){ echo implode(", ",$errors); }
				else{
					foreach($others as $key=>$one){
						$mon = strtotime(date("Y-M")); $now=time();
						$ins = array("`id`"=>"NULL","`staff`"=>"'$sid'","`approvals`"=>"'[]'","`approved`"=>"'0'","`month`"=>"'$mon'","`time`"=>"'$now'");
						foreach($one as $col=>$val){
							if(!in_array($col,array("journal_account","recipient_mpesa_number","mpesa_name"))){
								$val = ($col=="item_description") ?  json_encode($items[$key],1):$val; $ins["`$col`"]="'$val'";
							}
						}
						$save[] = "(".implode(",",$ins).")"; $order="(".implode(",",array_keys($ins)).")";
					}
					
					if($db->execute(3,"INSERT INTO `utilities$cid` $order VALUES ".implode(",",$save))){
						$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'");
						echo "success";
					}
					else{ echo "Failed to save the data! Try again later"; }
				}
				
				unlink("../../docs/temp.$ext");
			}
			else{ echo "Failed to open file! Try again later"; }
		}
	}
	

	@ob_end_flush();
?>