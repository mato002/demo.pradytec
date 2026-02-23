<?php
	session_start();
	ob_start();
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	if(isset($_POST["sysreq"])){
		$ref = trim($_POST["sysreq"]); 
		$chk = fetchSqlite("tempdata","SELECT *FROM `tokens` WHERE `data`='$ref'"); 
		if(is_array($chk)){
			if($chk[0]['data']==$ref){ $sid=$chk[0]['temp']; }else{ exit(); }
		}
		else{ exit(); }
	}
	else{
		if(!isset($_SESSION['myacc'])){ exit(); }
		$sid = substr(hexdec($_SESSION['myacc']),6);
		if(!$sid){ exit(); }
	}
	
	# initiate payment Reversal
	if(isset($_POST["revpay"])){
		$pin = trim($_POST["pin"]);
		$pid = trim($_POST["revpay"]);
		$amnt = intval($_POST["revsum"]);
		
		if(strlen($pin)<6){ echo "Failed: Invalid PIN"; }
		else{
			if($con = $db->mysqlcon(2)){
				if(substr($pid,0,3)=="wid"){
					$wid = substr($pid,3); $bal=walletBal($wid);
					if($amnt>$bal){ echo "Failed: Reversal amount is greater than Account balance"; $con->close(); exit(); }
					$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
					$uid = $chk[0]['client']; $actp=$chk[0]['type']; $desc="Reimbursement Withdrawal";
					$code = payFromWallet($uid,$amnt,$actp,$desc,$sid,0,0);
					$pid = $db->query(2,"SELECT `id` FROM `org".$cid."_payments` WHERE `code`='$code'")[0]['id'];
				}
				
				$con->autocommit(0); $con->query("BEGIN");
				$sql = $con->query("SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid' FOR UPDATE");
				if($sql->num_rows<1){ echo "Failed: The payment doesnt exist!"; }
				else{
					$row = $sql->fetch_assoc(); $st=$row['status']; $code=$row['code']; $now=time(); 
					$amnt-= (defined("DEDUCT_REVERSAL")) ? DEDUCT_REVERSAL:0; $payb=$row["paybill"];
					
					if($st){ echo "Failed: Payment is already approved"; }
					elseif($amnt<1){ echo "Invalid Reversal amount"; }
					elseif($amnt>$row["amount"]){ echo "Failed: Reversal amount is greater than actual amount"; }
					else{
						if($amnt<$row["amount"] or in_array(substr($code,0,6),["OVERPA","MERGED","WALLET"])){
							$fon = ltrim($row['phone'],"254"); $acc=$row['account'];
							if(!is_numeric($fon)){
								$qri = $db->query(2,"SELECT `contact` FROM `org$cid"."_clients` WHERE `idno`='$acc' OR `contact`='$acc'");
								if(!$qri){
									$qri = $db->query(2,"SELECT `contact` FROM `org$cid"."_staff` WHERE `idno`='$acc' OR `contact`='$acc'");
									if(!$qri){ echo "Failed: Recipient phone number cannot be retrieved"; exit(); } 
								}
								else{ $fon = $qri[0]['contact']; }
							}
							
							$fon = intval($fon);
							$qry = $db->query(2,"SELECT `source` FROM `b2c_trials$cid` WHERE `phone`='$fon' AND `status`='0' AND JSON_EXTRACT(source,'$.src')='reversal'");
							if($qry){ echo "Failed: There is a pending request of the same"; }
							else{
								$ran=dechex(rand(10000000,999999999)); $des=json_encode(["src"=>"reversal","id"=>$pid,"dtp"=>"b2c","tmp"=>$ran],1); $now=time();
								$db->execute(2,"INSERT INTO `b2c_trials$cid` VALUES(NULL,'$fon','----','$amnt','$des','$sid','$now','0')");
								$res = send_money(["254$fon-$ran"=>$amnt],$pin,$sid);
								if(explode("=>",$res)[0]=="success"){
									$con->query("UPDATE `org".$cid."_payments` SET `status`='$now' WHERE `id`='$pid'");
									echo "success";
								}
								else{
									$db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`='$now' AND `phone`='$fon'"); 
									echo $res;
								}
							}
						}
						else{
							$res = c2b_reverse($code,$amnt,$pin,$sid,$payb);
							if(trim($res)=="success"){
								$con->query("UPDATE `org".$cid."_payments` SET `status`='$now' WHERE `id`='$pid'");
								echo "success";
							}
							else{ echo $res; }
						}
					}
				}
				$con->commit(); $con->close();
			}
			else{ echo "Failed: System Error!"; }
		}
	}
	
	# request stk push
	if(isset($_POST["pkey"])){
		$key = trim($_POST["pkey"]);
		$fon = ltrim(intval($_POST["payfro"]),"254");
		$acc = trim($_POST["accno"]);
		$payb = trim($_POST["paybno"]);
		$amnt = intval($_POST["pamnt"]);
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
	
	#approve prepayment
	if(isset($_POST['getprepay'])){
		$pid = trim($_POST['getprepay']);
		$mon = strtotime(date("Y-M"));
		$day = date("YmdHis");
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `org".$cid."_prepayments` WHERE `id`='$pid' FOR UPDATE");
			$row = $sql->fetch_assoc(); $tid=$row['template']; $amnt=$row['amount']; $idno=$row['idno']; 
			$payb = getpaybill($row['branch']);
			
			if($row['status']>0){ echo "success:0"; }
			else{
				$qry = $con->query("SELECT *FROM `org".$cid."_loantemplates` WHERE `id`='$tid'");
				$row = $qry->fetch_assoc(); $dtm=$row['status']; $fon=$row['phone']; $name=$row['client']; $code="PREPAY$pid";
				
				if($db->execute(2,"INSERT INTO `org".$cid."_payments` VALUES(NULL,'$mon','$code','$day','$amnt','$payb','$idno','0','$fon','$name','0')")){
					$rid = $db->query(2,"SELECT id FROM `org".$cid."_payments` WHERE `date`='$day' AND `code`='$code'")[0]['id'];
					$con->query("UPDATE `org".$cid."_prepayments` SET `status`='$rid' WHERE `id`='$pid'"); 
					$con->commit(); $con->autocommit(1);
					savelog($sid,"Approved prepayment of Ksh $amnt for $name ($idno)");
					echo "success:$rid";
				}
				else{ echo "Failed to transfer payment at the moment"; }
			}
			$con->close();
		}
		else{ echo "System error! Try again later!"; }
	}
	
	# group payment
	if(isset($_POST['groupay'])){
		$pid = trim($_POST['groupay']);
		$savings = $_POST['savings'];
		$wallets = $_POST['wallets'];
		$loans = (isset($_POST['ploans'])) ? $_POST['ploans']:[];
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid' FOR UPDATE"); 
			$row = $sql->fetch_assoc(); $amnt=$row['amount']; $trans=$row['code']; $mon=$row["month"]; $day=$row["date"]; $tsum=0;
			
			foreach($savings as $uid=>$sum){if(intval($sum)>0){ $ids[$uid]["savings"]=$sum; $tsum+=$sum; }}
			foreach($wallets as $uid=>$sum){if(intval($sum)>0){ $ids[$uid]["balance"]=$sum; $tsum+=$sum; }}
			foreach($loans as $id=>$sum){if(intval($sum)>0){ $ids[explode("-",$id)[0]][explode("-",$id)[1]]=$sum; $tsum+=$sum; }}
			
			if($row['status']){ echo "Failed: Payment already approved!"; }
			elseif($tsum<$amnt){ echo "Error! Assigned amounts is less than actual payment"; }
			elseif($tsum>$amnt){ echo "Error! Assigned amounts is greater than actual payment"; }
			else{
				$pst = "SPL".time(); $pos=0; $done=[];
				if($con->query("UPDATE `org$cid"."_payments` SET `status`='$pst' WHERE `id`='$pid'")){
					$con->commit(); $con->autocommit(1);
					foreach($ids as $uid=>$def){
						$chk = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
						$row = $chk[0]; $payb=getpaybill($row["branch"]); $name=ucwords($row["name"]); $tot=0;
						foreach($def as $src=>$sum){
							$code="SPL$pid"."P$pos"; $pos+=1; $idno=$row['idno']; $fon=$row["contact"];
							$db->execute(2,"INSERT INTO `org".$cid."_payments` VALUES(NULL,'$mon','$code','$day','$sum','$payb','$idno','0','$fon','$name','0')");
							$rid = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$code' AND `phone`='$fon'")[0]['id'];
							if(in_array($src,["savings","balance"])){
								$desc="Paybill payment #$code assigned to $name from parent transaction #$trans"; 
								if($res=updateWallet($uid,"$sum:$src","client",["desc"=>$desc,"revs"=>array(["tbl"=>"pays","tid"=>$code])],$sid,time(),1)){
									$db->execute(2,"UPDATE `org$cid"."_payments` SET `status`='$res' WHERE `id`='$rid'"); $tot+=$sum;
								}
							}
							else{ makepay($idno,$rid,$sum,"client",$src); $tot+=$sum; }
						}
						$done[] = "$name(Kes ".fnum($tot).")";
					}
					
					savelog($sid,"Assigned Group payment $trans of KES $amnt to ".implode(", ",$done));
					echo "success";
				}
				else{ echo "Failed to complete the request!"; }
			}
			$con->close();
		}
		else{ echo "System error! Try again later!"; }
	}
	
	# split payment
	if(isset($_POST['ptrans'])){
		$pid = trim($_POST['ptrans']);
		$dto = $_POST['clidn'];
		$samnt = $_POST['clamt'];
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid' FOR UPDATE"); 
			$row = $sql->fetch_assoc(); $amnt=$row['amount']; $trans=$row['code']; 
			$mon = $row["month"]; $day=$row["date"]; $error=""; $bals=[];
			foreach($dto as $key=>$idno){
				$chk = $con->query("SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (penalty+balance)>0");
				if($chk->num_rows<1){ $error = "Client idno $idno has no running Loan"; break; }
				else{
					$roq=$chk->fetch_assoc(); $give[$idno]=$samnt[$key];
					$bals[$idno]=[$roq['balance'],$roq['penalty'],$roq['loan']];  
					$user[$idno]=[ucwords($roq['client']),$roq['phone'],$roq['branch']];
				}
			}
			
			if($row['status']){ echo "Failed: Payment already approved!"; }
			elseif(array_sum($samnt)<$amnt){ echo "Error! Assigned amounts is less than system payment"; }
			elseif(array_sum($samnt)>$amnt){ echo "Error! Assigned amounts is greater than system payment"; }
			elseif($error){ echo "Failed: $error"; }
			else{
				$pst = "SPL".time(); $pos=0;
				if($con->query("UPDATE `org$cid"."_payments` SET `status`='$pst' WHERE `id`='$pid'")){
					$con->commit(); $con->autocommit(1);
					foreach($give as $idno=>$sum){
						if($sum>0){
							$code="SPL$pid"."P$pos"; $loan=$bals[$idno]; $pos+=1; $payb=getpaybill($user[$idno][2]); $fon=$user[$idno][1]; $name=$user[$idno][0];
							$db->execute(2,"INSERT INTO `org".$cid."_payments` VALUES(NULL,'$mon','$code','$day','$sum','$payb','$idno','0','$fon','$name','0')");
							$rid = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$code' AND `phone`='$fon'")[0]['id'];
							if($loan[0]>0){ makepay($idno,$rid,$sum,"client",$loan[2]); $done[$idno]="$name(Kes $sum)"; }
							else{
								$lid=$loan[2]; $pen=$loan[1]; $rem=($sum>$pen) ? 0:$pen-$sum; $bal=$sum-$pen;
								if($db->execute(2,"UPDATE `org".$cid."_loans` SET `penalty`='$rem' WHERE `loan`='$lid'")){
									if($bal>0){
										savepays($rid,$lid,array(["penalties"=>$pen])); saveoverpayment($idno,$rid,$bal); 
									}
									else{ savepays($rid,$lid,array(["penalties"=>$sum])); }
								}
							}
						}
					} 
					
					savelog($sid,"Splitted payment $trans of KES $amnt to ".implode(", ",$done));
					echo "success";
				}
				else{ echo "Failed to complete the request!"; }
			}
			$con->close();
		}
		else{ echo "System error! Try again later!"; }
	}
	
	//assign merged payments
	if(isset($_POST['assignmerged'])){
		$idno = trim($_POST['assignmerged']);
		$mon = strtotime(date("Y-M"));
		$day = date("YmdHis"); $tym=time();
		$ptbl = "org".$cid."_payments";
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN"); $amnt=0;
			$res = $con->query("SELECT *FROM `mergedpayments$cid` WHERE `idno`='$idno' AND `status`='0' ORDER BY `id` ASC FOR UPDATE");
			if($res->num_rows){
				while($rw=$res->fetch_assoc()){ $amnt+=$rw['amount']; $rid=$rw['id']; }
				$qry = $con->query("SELECT *FROM `org".$cid."_clients` WHERE `idno`='$idno'");
				$row = $qry->fetch_assoc(); $cname=strtoupper($row['name']); $fon=$row['contact']; $payb=getpaybill($row['branch']);
				
				if($con->query("INSERT INTO `$ptbl` VALUES(NULL,'$mon','MERGED$rid','$day','$amnt','$payb','$idno','0','$fon','$cname','0')")){
					$con->query("UPDATE `mergedpayments$cid` SET `status`='$tym',`transaction`='MERGED$rid' WHERE `idno`='$idno' AND `status`='0'"); 
					$con->commit(); $con->autocommit(1); $con->close();
					$qri = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `date`='$day' AND `account`='$idno' AND `amount`='$amnt' ORDER BY `id` DESC");
					savelog($sid,"Assigned payment of Ksh $amnt to $cname from merged payments");
					echo "success:".$qri[0]['id'];
				}
				else{ echo "Failed to complete payment,try again later"; $con->close(); }
			}
			else{ echo "Payment is already assigned"; $con->close(); }
		}
		else{ echo "System error! Try again later!"; }
	}
	
	# merge payments
	if(isset($_POST["mgpays"])){
		$pays = array_unique($_POST["mgpays"]);
		$mtbl = "mergedpayments$cid";
		$ptbl = "org".$cid."_payments";
		
		if(count($pays)==1){ echo "Error: You cannot merge single payment"; exit(); }
		if(!$db->istable(2,$mtbl)){
			$db->createTbl(2,$mtbl,json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='mergedpayments'")[0]['fields'],1));
		}
	
		if($con = $db->mysqlcon(2)){
			$error=""; $trans=rand(100000000,999999999);
			$con->autocommit(0); $con->query("BEGIN"); 
			foreach($pays as $pos=>$code){
				$chk = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `payment`='$code' AND NOT `status`='9'");
				if($chk){ $error = "Failed: Payment transaction $code is linked to loan application for ".ucwords(prepare($chk[0]["client"])); break; }
				$res = $con->query("SELECT *FROM `$ptbl` WHERE `code`='$code' FOR UPDATE");
				if($res->num_rows){
					$row = $res->fetch_assoc(); $sta=$row["status"]; $codes[]=$code;
					if($pos==0){ $def=$row; } 
					if($sta){ $error = "Error: Payment transaction $code is already approved"; break; }
					else{
						$pid = $row["id"]; $tym=time(); $id=$def["id"]; $amnt=$row["amount"];
						$qrys[] = "INSERT INTO `$mtbl` VALUES(NULL,'$id','$sid','0','$code','$amnt','$pid','$trans','$sid','$tym','0')";
						$qrys[] = "UPDATE `org".$cid."_payments` SET `status`='MGD-$tym' WHERE `code`='$code'";
					}
				}
				else{ $error = "Error: Payment transaction $code is not found"; break; }
			}
			
			if($error){
				$con->commit(); $con->autocommit(1); $con->close();
				echo $error; 
			}
			else{
				foreach($qrys as $qry){ $con->query($qry); }
				$con->commit(); $amnt=0; $mon=strtotime(date("Y-M")); $day=date("YmdHis"); $tym=time();
				
				$res = $con->query("SELECT *FROM `mergedpayments$cid` WHERE `transaction`='$trans' AND `status`='0' ORDER BY `id` ASC FOR UPDATE");
				while($rw=$res->fetch_assoc()){ $amnt+=$rw['amount']; $rid=$rw['id']; }
				$cname = $def['client']; $fon=$def['phone']; $payb=$def["paybill"]; $acc=$def["account"];
				
				if($con->query("INSERT INTO `$ptbl` VALUES(NULL,'$mon','MERGED$rid','$day','$amnt','$payb','$acc','0','$fon','$cname','0')")){
					$con->query("UPDATE `$mtbl` SET `status`='$tym',`transaction`='MERGED$rid' WHERE `transaction`='$trans' AND `status`='0'"); 
					$con->commit(); $con->autocommit(1); $con->close();
					$qri = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `date`='$day' AND `account`='$acc' AND `amount`='$amnt' ORDER BY `id` DESC");
					savelog($sid,"Merged payment transactions (".implode(",",$codes).") to MERGED$rid");
					echo "success:".$qri[0]['id'];
				}
				else{ echo "Failed to complete payment,try again later"; $con->close(); }
			}
		}
		else{ echo "System error! Try again later!"; }
	}
	
	//assign overpayments
	if(isset($_POST['assignpay'])){
		$rid = trim($_POST['assignpay']);
		$atp = (isset($_POST["avtp"])) ? trim($_POST['avtp']):0;
		$mon = strtotime(date("Y-M"));
		$day = date("YmdHis"); $tym=time();
		$ptbl = "org".$cid."_payments";
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$qri = $con->query("SELECT *FROM `overpayments$cid` WHERE `id`='$rid' AND `status`='0' FOR UPDATE");
			if($qri->num_rows){
				$rw=$qri->fetch_assoc(); $idno=$rw['idno']; $amnt=$rw['amount'];
				$qry = $con->query("SELECT *FROM `org".$cid."_clients` WHERE `idno`='$idno'");
				if($qry->num_rows<1){ echo "Failed: Client Idno $idno is not found in the system"; exit(); }
				
				$row=$qry->fetch_assoc(); $cname=strtoupper($row['name']); $fon=$row['contact']; $payb=getpaybill($row['branch']);
				$desc = "Overpayment from $cname ref OVERPAY$rid";
				
				if($con->query("INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','OVERPAY$rid','$day','$amnt','$payb','$idno','0','$fon','$cname','0')")){
					$con->query("UPDATE `overpayments$cid` SET `status`='$tym' WHERE `id`='$rid'"); 
					$con->commit(); $con->autocommit(1);
					
					if($atp){
						if($res=updateWallet($row["id"],$amnt,"client",["desc"=>$desc,"revs"=>array(["tbl"=>"overpay","tid"=>"OVERPAY$rid"])],$sid,$tym,1)){
							$con->query("UPDATE `$ptbl` SET `status`='$res' WHERE `date`='$day' AND `code`='OVERPAY$rid'");
							bookbal(DEF_ACCS['overpayment'],"-$amnt");
							savelog($sid,"Assigned payment of Ksh $amnt to $cname wallet from overpayments");
							echo "success";
						}
						else{
							$con->query("DELETE FROM `$ptbl` WHERE `date`='$day' AND `code`='OVERPAY$rid'");
							$con->query("UPDATE `overpayments$cid` SET `status`='0' WHERE `id`='$rid'");
							echo "Failed to complete the request! Try again later";
						}
					}
					else{
						$qri = $con->query("SELECT `id` FROM `$ptbl` WHERE `date`='$day' AND `account`='$idno' AND `amount`='$amnt' ORDER BY `id` DESC");
						$pid = $qri->fetch_assoc()['id']; bookbal(DEF_ACCS['overpayment'],"-$amnt");
						savelog($sid,"Assigned payment of Ksh $amnt to $cname from overpayments");
						echo "success:$pid";
					}
				}
				else{ echo "Failed to complete payment,try again later"; }
			}
			else{ echo "success"; } 
			$con->close();
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# topup wallet from payments 
	if(isset($_POST["wlpay"])){
		$wid = trim($_POST["cwid"]);
		$wtp = trim($_POST["walletp"]);
		$pid = trim($_POST["wlpay"]);
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid' FOR UPDATE");
			if($sql->num_rows){
				$row=$sql->fetch_assoc(); $st=$row['status']; $amnt=$row['amount']; $code=$row['code']; $name=$row['client']; 
				$actp=$qri[0]['type']; $uid=$qri[0]['client']; $desc="Paybill payment #$code From $name"; 
				$chk = $con->query("SELECT *FROM `org".$cid."_loantemplates` WHERE `payment`='$code' AND NOT `status`='9'");
				
				if($st){ echo "Failed: Payment is already approved!"; }
				elseif($chk->num_rows){ echo "Failed: Payment is already linked to loan template"; }
				else{
					if($res=updateWallet($uid,"$amnt:$wtp",$actp,["desc"=>$desc,"revs"=>array(["tbl"=>"pays","tid"=>$code])],$sid,time(),1)){
						$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
						$qry = $con->query("SELECT `name` FROM `$tbls[$actp]` WHERE `id`='$uid'");
						$con->query("UPDATE `org$cid"."_payments` SET `status`='$res' WHERE `id`='$pid'");
						savelog($sid,"Transfered $desc to ".$qry->fetch_assoc()["name"]." account");
						echo "success:done";
					}
					else{ echo "Failed to complete the request! Try again later"; }
				}
			}
			else{ echo "Failed: Payment doesnt exist!"; }
			$con->commit(); $con->close();
		}
		else{ echo "Failed to initiate the process! Try again later"; }
	}
	
	# topup wallet from asset accounts
	if(isset($_POST["dpfrm"])){
		$acc = trim($_POST["dpfrm"]);
		$tym = strtotime(trim($_POST["dpday"]));
		$ref = clean($_POST["dtrans"]);
		$com = clean($_POST["dcom"]);
		$wid = trim($_POST["cwid"]);
		$sum = intval($_POST["tamnt"]);
		$wtp = trim($_POST["walletp"]);
		
		if(!$acc){ echo "Failed: No asset account selected!"; }
		elseif(!$tym){ echo "Error: Invalid Date"; }
		elseif($sum<1){ echo "Error! Amount should be greater than 0"; }
		else{
			$iscash = (explode(":",$acc)[0]=="cash") ? 1:0; 
			$acc = ($iscash) ? explode(":",$acc)[1]:$acc;
			$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
			$qry = $db->query(3,"SELECT `account` FROM `accounts$cid` WHERE `id`='$acc'");
			
			$actp = $qri[0]['type']; $uid=$qri[0]['client']; $desc=$rep="Deposit from ".$qry[0]['account'];
			$desc.= ($ref) ? " #$ref":""; $desc.=($com) ? " - $com":""; 
			$types = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings"); 
			$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
			$qry = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `id`='$uid'"); $name=ucwords($qry[0]['name']);
			
			if($iscash){
				$rev[] = array("tbl"=>"cashacc","tid"=>$sid);
				if($res=updateWallet($uid,"$sum:$wtp",$actp,["desc"=>$desc,"revs"=>$rev],$sid,$tym,1)){
					$des = str_replace($rep,"Deposit to $types[$wtp] account",$desc);
					$day = strtotime(date("Y-M-d",$tym)); $mon=strtotime(date("Y-M",$tym)); $yr=date("Y",$tym); bookbal($acc,"$sum:$mon");
					$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','asset','$acc','$sum','debit','$des','WID$wid','$com','$sid','auto','$mon','$day','$tym','$yr')");
					setCash($sid,$sum,"$desc to $name $types[$wtp] account");
					logtrans($res,"$desc to $name $types[$wtp] account",$sid);
					savelog($sid,"Created $desc to $name $types[$wtp] account");
					echo "success:done";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
			else{
				$auto = (defined("AUTOSETTLE_TOPUPS")) ? AUTOSETTLE_TOPUPS:0;
				$def = ["wallet"=>"$actp:$uid:$wid","deposit"=>"$sum:$wtp","desc"=>"Ksh $sum $desc","acc"=>$acc,"comment"=>$com,"title"=>"$name $types[$wtp] Account deposit",
				"perm"=>"approve wallet deposits","time"=>$tym]; 
				if($rid=initiateApproval("topups",$def,0,$sid)){
					if($auto){
						$me = staffInfo($sid); $otp=rand(123456,999999); $exp=time()+300;
						$mfon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
						$db->execute(1,"REPLACE INTO `otps` VALUES('$mfon','$otp','$exp')");
						$dto = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://$url":"https://$url";
						$req = request("$dto/mfs/dbsave/account.php",["approvereq"=>$rid,"sval"=>200,"votp"=>$otp,"sysreq"=>genToken($sid)]);
					}
					
					savelog($sid,"Initiated $desc to $name $types[$wtp] Account");
					echo "success:wait"; 
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
	}
	
	# wallet withdraw
	if(isset($_POST["dedwid"])){
		$wid = trim($_POST["dedwid"]);
		$acc = trim($_POST["dedfrm"]);
		$ref = clean($_POST["dtrans"]);
		$com = clean($_POST["dcom"]);
		$sum = intval($_POST["tamnt"]);
		$tym = strtotime(trim($_POST["wtday"]));
		$cnl = (isset($_POST["channel"])) ? trim($_POST["channel"]):"b2c";
		
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		$actp=$qri[0]['type']; $uid=$qri[0]['client']; $bal=walletBal($wid);
		$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_withdrawal_fees'");
		$tbl = $tbls[$actp]; $acid=$fee=0; $fees=($chk) ? json_decode($chk[0]['value'],1):[];
		if($fees){
			foreach($fees as $lim=>$rate){
				$range = explode("-",$lim); $fee=$rate;
				if($sum>=$range[0] && $sum<=$range[1]){ $fee=$rate; break; }
			}
		}
		
		if($sum<1){ echo "Error! Amount should be greater than 0"; }
		elseif(!$tym){ echo "Error: Invalid Date"; }
		elseif($bal<($sum+$fee)){ echo "Failed: Insufficient amount to complete request!"; }
		else{
			if($acc=="mpesa"){
				$fon = intval($_POST["dedto"]);
				$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='utilities$cid'");
				$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
				if($qry){ $acid = json_decode($qry[0]['value'],1)["Transactional"]; }
				else{
					$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='Clients Wallet'");
					if($sql){ $acid=$sql[0]['id']; }
				}
				
				if(!$acid){ echo "Failed: Transactional account has not been set!"; }
				elseif(strlen($fon)!=9){ echo "Error: Invalid MPESA phone number"; }
				else{
					$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$uid'");
					$name = ucwords($qri[0]['name']); $otp=rand(123456,654321); $exp=time()+60; $now=time();
					$me = staffInfo($sid); $mfon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
					$db->execute(1,"REPLACE INTO `otps` VALUES('$mfon','$otp','$exp')");
					
					$cols = ($res) ? array_keys(json_decode($res[0]['fields'],1)):["id","staff","branch","item_description","cost","approvals","approved","recipient","month","status","time"]; 
					$post = array("formkeys"=>json_encode($cols,1),"id"=>0,"staff"=>$sid,"recipient"=>"0$fon","month"=>strtotime(date("Y-M")),"time"=>$now,"recname"=>$name,"status"=>0,
					"rbran"=>0,"item"=>["$name Wallet Withdrawal"],"qnty"=>[1],"baccs"=>["$acid:$wid"],"prices"=>[$sum],"otpv"=>$otp,"wb2c"=>$sid,"approvals"=>"[]","approved"=>0);
					
					$dto = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://$url":"https://$url";
					$req = request("$dto/mfs/dbsave/utilities.php",$post); 
					if(explode(":",trim($req))[0]=="success"){
						$allowed = (defined("ALLOW_DIRECT_WALLET_B2C")) ? ALLOW_DIRECT_WALLET_B2C:0;
						if($allowed){
							$tsum=$fee+$sum; $app='[{"'.$sid.'":["1","'.$sum.'"]}]'; $mode=($cnl=="b2c") ? "B2C":"B2B";
							$rid = $db->query(3,"SELECT `id` FROM `utilities$cid` WHERE `time`='$now' AND `cost`='$tsum'")[0]['id'];
							$db->execute(3,"UPDATE `utilities$cid` SET `status`='200',`approvals`='$app',`approved`='$tsum' WHERE `id`='$rid'");
							
							if($auto=getAutokey()){
								$dtm = hexdec(array_shift($auto)); $pto=$fon;
								if($key=decrypt(implode("/",$auto),date("MdY.His",$dtm))){
									$sql = $db->query(2,"SELECT `source` FROM `b2c_trials$cid` WHERE `phone`='$pto' AND `status`<2 AND JSON_EXTRACT(source,'$.src')='utility' AND
									JSON_EXTRACT(source,'$.wid')=$wid");
									if($sql){ echo "Sorry! There is a pending withdrawal Transaction. Please wait for its completion"; }
									else{
										$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'"); $ran=dechex(rand(10000000,999999999));
										$des = json_encode(["src"=>"utility","id"=>$rid,"dtp"=>$cnl,"tmp"=>$ran,"route"=>"$name~$fon~$cnl","wid"=>$wid],1); 
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
						else{
							savelog($sid,"Initiated B2C Withdrawal transaction of Ksh $sum for $name"); 
							echo "success:Vendor payment Created now waiting for approvals!";
						}
					}
					else{ echo $req; }
				}
			}
			else{
				$iscash = (explode(":",$acc)[0]=="cash") ? 1:0; 
				$acc = ($iscash) ? explode(":",$acc)[1]:$acc; 
				$qry = $db->query(3,"SELECT `account`,`balance` FROM `accounts$cid` WHERE `id`='$acc'");
				$desc = $rep="Withdrawal via ".$qry[0]['account']; $desc.=($ref) ? " #$ref":""; $desc.=($com) ? " - $com":"";
				$rev[] = ($iscash) ? ["tbl"=>"cashacc","tid"=>$sid]:["tbl"=>"withdraw","tid"=>"smtid"];
				$allow = (defined("ASSET_ACCOUNTS_OVERDRAFT")) ? ASSET_ACCOUNTS_OVERDRAFT:0;
				
				if($qry[0]['balance']<($sum+$fee) && !$allow){ echo "Failed: Insufficient account balance"; }
				else{
					if($res=updateWallet($uid,"-$sum:balance",$actp,["desc"=>$desc,"revs"=>$rev],$sid,$tym,1)){
						$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$uid'"); sleep(1);
						$name = ucwords($qri[0]['name']); bookbal($acc,"-$sum");
						if($fee>0){
							saveTrans_fees($res,"$desc to $name Transaction Charges ",$fee,$sid); 
							updateWallet($uid,"-$fee:balance",$actp,["desc"=>"$desc Transaction #$res Charges","revs"=>array(["tbl"=>"norev"])],$sid);
						}
						
						$des = str_replace($rep,"Withdrawal via Transactional account",$desc); 
						$day = strtotime(date("Y-M-d",$tym)); $mon=strtotime(date("Y-M",$tym)); $yr=date("Y",$tym); 
						$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','asset','$acc','$sum','credit','$des','WID$wid','$com','$sid','auto','$mon','$day','$tym','$yr')");
						if($iscash){ setCash($sid,"-$sum","$desc from $name"); }
						logtrans($res,"$desc from $name Transactional account",$sid);
						savelog($sid,"Created $des from $name Transactional account");
						echo "success:Withdrawal transaction successful";
					}
					else{ echo "Failed to complete the request! Try again later"; }
				}
			}
		}
	}
	
	# transfer from wallet
	if(isset($_POST["transwid"])){
		$wid = trim($_POST["transwid"]);
		$wtp = trim($_POST["transfro"]);
		$sum = intval($_POST["tamnt"]);
		$tto = trim($_POST["transto"]);
		
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		$actp=$qri[0]['type']; $uid=$qri[0]['client']; $bal=walletBal($wid,$wtp);
		$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		
		if($sum<1){ echo "Error! Amount should be greater than 0"; }
		elseif($bal<$sum){ echo "Failed: Insufficient amount to complete request!"; }
		else{
			$sql = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `id`='$uid'");
			$dtls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings"); 
			$cname = ucwords($sql[0]['name']);
			
			if(in_array($tto,["balance","investbal","savings"])){
				if($res=updateWallet($uid,"-$sum:$wtp",$actp,["desc"=>"Transfer to $dtls[$tto] Account","revs"=>array(["tbl"=>"norev"])],$sid,time(),1)){
					updateWallet($uid,"$sum:$tto",$actp,["desc"=>"Transfer #$res from $dtls[$wtp] Account","revs"=>array(["tbl"=>"norev"])],$sid);
					logtrans($res,"Transfer of Ksh $sum from $cname $dtls[$wtp] account to $dtls[$tto] Account",$sid);
					savelog($sid,"Transfered Ksh $sum from $cname $dtls[$wtp] account to $dtls[$tto] account");
					echo "success";
				}
				else{ echo "Failed to complete request! Try again later!"; }
			}
			elseif($tto=="client"){
				$idno = trim($_POST["clidno"]);
				$chk = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `idno`='$idno'");
				
				if(!$chk){ echo "Failed: $actp account with idno $idno is not found!"; }
				elseif($chk[0]['id']==$uid){ echo "Failed: You cannot transfer to same account"; }
				else{
					$auto = (defined("AUTOSETTLE_TOPUPS")) ? AUTOSETTLE_TOPUPS:0;
					$toname=ucwords($chk[0]['name']); $toid=$chk[0]['id']; $tym=time();
					$def = ["wallet"=>"$actp:$uid:$wid","transfer"=>"$sum:$wtp","desc"=>"Transfer of Ksh $sum from $cname $dtls[$wtp] account to $toname Transactional account","acc"=>$toid,
					"title"=>"Ksh $sum Transfer from $cname to $toname","perm"=>"approve wallet deposits","comment"=>$cname];
					if($rid=initiateApproval("wtransfer",$def,0,$sid)){
						if($auto){
							$me = staffInfo($sid); $otp=rand(123456,999999); $exp=time()+300;
							$mfon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
							$db->execute(1,"REPLACE INTO `otps` VALUES('$mfon','$otp','$exp')");
							$dto = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://$url":"https://$url";
							$req = request("$dto/mfs/dbsave/account.php",["approvereq"=>$rid,"sval"=>200,"votp"=>$otp,"sysreq"=>genToken($sid)]);
						}
					
						savelog($sid,"Initiated Transfer of Ksh $sum from $cname $dtls[$wtp] account to $toname Transactional account");
						echo "success:wait"; 
					}
					else{ echo "Failed to complete the request! Try again later"; }
				}
			}
			elseif($tto=="reserve"){
				$def = ["wallet"=>"$actp:$uid:$wid","transfer"=>"$sum:$wtp","desc"=>"Transfer of Ksh $sum from $cname $dtls[$wtp] account to System Bad debt Reserve","acc"=>"reserve",
				"title"=>"Ksh $sum Transfer from $cname to Bad Debt Reserve","perm"=>"approve wallet deposits","comment"=>$cname];
				if(initiateApproval("wtransfer",$def,0,$sid)){
					savelog($sid,"Initiated Transfer of Ksh $sum from $cname $dtls[$wtp] account to Bad debt Reserve");
					echo "success:wait"; 
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
			else{
				$lid = trim($_POST["paylid"]); 
				$lcol = ($tto=="loan") ? "balance":"penalty"; 
				$ptx = ($tto=="loan") ? "running Loan":"Unpaid penalties";
				$desc = ($tto=="loan") ? "Loan $lid Repayment":"Penalties claim withdrawal";
				
				if($actp=="client"){
					$chk = $db->query(2,"SELECT SUM($lcol) AS tbal FROM `org$cid"."_loans` WHERE `client_idno`='".$sql[0]['idno']."'");
					$tbal = ($chk) ? intval($chk[0]["tbal"]):0; 
					if($tbal<=0){ echo "Failed: Client has no $ptx!"; exit(); }
				}
				else{
					if($db->istable(2,"staff_loans$cid")){
						$chk = $db->query(2,"SELECT SUM($lcol) AS tbal FROM `staff_loans$cid` WHERE `stid`='$uid'");
						$tbal = ($chk) ? intval($chk[0]["tbal"]):0;
						if($tbal<=0){ echo "Failed: Staff has no $ptx!"; exit(); }
					}
				}
				
				if($code=payFromWallet($uid,$sum,$actp,["desc"=>$desc,"loan"=>$lid],$sid,1,1)){
					savelog($sid,"Made a loan payment of Ksh $sum from $cname account. Ref $code");
					echo "success"; 
				}
				else{ echo "Failed to complete request! Try again later!"; }
			}
		}
	}
	
	# edit payment details
	if(isset($_POST["editpacc"])){
		$val = clean($_POST["pacc"]);
		$def = explode(":",trim($_POST["editpacc"]));
		$pid = $def[0]; $col=$def[1];
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
		if(!$sql){ echo "Failed: Payment doesnt exist!"; }
		elseif($sql[0]['status']){ echo "Failed: payment is already approved"; }
		else{
			if($db->execute(2,"UPDATE `org$cid"."_payments` SET `$col`='$val' WHERE `id`='$pid'")){
				$code=$sql[0]['code']; $old=$sql[0][$col]; 
				savelog($sid,"Edited payment $col for Transaction $code from $old to $val");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	//approve payment
	if(isset($_POST['savepay'])){
		$src = explode(":",trim($_POST['savepay']));
		$clid = explode("-",$src[0]); $pid=$src[1]; $amnt=$src[2];
		$idno = $clid[0]; $actp=$ptp=$clid[1]; $loan=$src[3];
		$type = trim($_POST['paytp']); 
		$ptbl = "org".$cid."_payments";
		$tym=time(); $prem=$tpen=$avs=0;
		
		$row = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'")[0]; $status=$row['status']; $code=$row['code']; $dy=$row['date'];
		$tdy = strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-")); $isemp=($actp=="staff") ? 1:0; 
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `payment`='$code' AND NOT `status`='9'");
		if($actp=="staff"){
			$qry = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `payment`='$code'"); $avs=($qry) ? 1:0;
		}
		
		$ltbl = ($isemp) ? "staff_loans$cid":"org".$cid."_loans"; 
		$stbl = ($isemp) ? "staff_schedule$cid":"org".$cid."_schedule"; 
		$lcol = ($isemp) ? "stid":"client_idno"; $stx=($isemp) ? "Staff":"Client";
		
		if($status>10 or !$pid){ echo "success"; }
		elseif($qri or $avs){ echo "Failed: Payment transaction $code is already linked to Loan template"; }
		else{
			$cond = ($loan) ? "AND `loan`='$loan'":""; $scol=($isemp) ? "stid":"idno";
		    if(in_array($type,["penrepay","repay","penalty"])){
				if(!$loan){
					$sql = $db->query(2,"SELECT COUNT(*) AS tot FROM `$ltbl` WHERE (balance+penalty)>0 AND `$lcol`='$idno'");
					if(intval($sql[0]["tot"])>1){ echo "choose~$idno-$ptp:$pid:$amnt:$type"; exit(); }
				}
				
				$qry = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='upfront_penalties' AND `client`='$cid'"); $cut=($qry) ? $qry[0]['value']:0;
				if(!$db->istable(2,"penalties$cid")){ $db->createTbl(2,"penalties$cid",["loan"=>"CHAR","totals"=>"INT","paid"=>"INT","installments"=>"CHAR"]); }
		        
				if($cut!=0){
					$sql = $db->query(2,"SELECT `loan`,SUM(balance) AS tamnt FROM `$stbl` WHERE `$scol`='$idno' AND `balance`>0 AND `day`<$tdy");
					if($sql){
						$qry = $db->query(2,"SELECT *FROM `penalties$cid` WHERE `loan`='".$sql[0]['loan']."'");
						$lid = $sql[0]['loan']; $due=intval($sql[0]['tamnt']); $pb4=($qry) ? $qry[0]['paid']:0; 
						$pcut = (count(explode("%",$cut))>1) ? round($due*explode("%",$cut)[0]/100):$cut;
						if($due>0 && $pb4<$pcut){
							$prem = $pcut-$pb4; $type=($amnt>$prem) ? "penrepay":"penalty"; $pen=($amnt<$prem) ? $amnt:$prem; $_POST['penamnt']=$pen; $tpen=$pen+$pb4;
							$db->execute(2,"UPDATE `$ltbl` SET `penalty`='$prem' WHERE `loan`='$lid'");
							if(!$qry){ $db->execute(2,"INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$pcut','0','[\"all\"]')"); }
							logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$pen,"bal"=>$prem),1),0);
						}
					}
				}
		    }
		   
			if($type=="penalty"){
				$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$lcol`='$idno' $cond ORDER BY `id` DESC LIMIT 1");
				if(!$res){ echo "Failed: $stx has no loan history"; }
				else{
					$row = $res[0]; $penam=$row['penalty']; $lid=$row['loan']; $cname=($isemp) ? $row["staff"]:$row['client']; $tbal=$row['balance'];
					$paid = ($amnt>$penam) ? $penam:$amnt; $rem=$penam-$paid; $bal=$amnt-$paid;
					
					if($penam==0){ echo "$stx ".prepare($cname)." has no Unpaid penalty"; }
					else{
						if($db->execute(2,"UPDATE `$ltbl` SET `penalty`='$rem' WHERE `loan`='$lid'")){
							if($bal){
								if($tbal){ makepay($idno,$pid,array(array(["penalties"=>$paid]),$bal),$ptp,$lid); }
								else{
									savepays($pid,$lid,array(["penalties"=>$paid]),$ptp); saveoverpayment($idno,$pid,$bal,$ptp); 
									logtrans($lid,json_encode(array("desc"=>"Penalties charges paid","type"=>"credit","amount"=>$paid,"bal"=>0),1),0);
								}
							}
							else{
								savepays($pid,$lid,array(["penalties"=>$paid]),$ptp);
								logtrans($lid,json_encode(array("desc"=>"Penalties charges paid","type"=>"credit","amount"=>$paid,"bal"=>($tbal+$penam)-$paid),1),0);
							}
							echo "success";
						}
						else{ echo "Failed to make payment. Try again later"; }
					}
				}
			}
			
			if($type=="repay"){
				$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$lcol`='$idno' AND (balance+penalty)>0 $cond");
				if(!$res){ echo "Failed: Selected $stx has no running loan"; }
				else{
					if(makepay($idno,$pid,$amnt,$ptp,$res[0]["loan"])){
						savelog($sid,"Made a Loan repayment of Ksh $amnt to ".$res[0][$ptp]);
						echo "success"; 
					}
					else{ echo "Failed to complete payment"; }
				}
			}
			
			if($type=="penrepay"){
				$penalty = intval($_POST['penamnt']);
				if($penalty<1){ echo "Penalty amount should be greater than 0"; }
				elseif($penalty>$amnt){ echo "Error! Penalty is greater than paid amount"; }
				else{
					$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$lcol`='$idno' AND (balance+penalty)>0 $cond ORDER BY `id` DESC LIMIT 1");
					if(!$res){ echo "Failed: Selected $stx has no running loan"; }
					else{
						$row = $res[0]; $penam=$row['penalty']; $tbal=$row['balance']+$penam; $lid=$row['loan']; 
						$deduct = ($penam>$penalty) ? $penalty:$penam; $rem=$amnt-$deduct;
						
						$db->execute(2,"UPDATE `$ltbl` SET `penalty`=(penalty-$penalty) WHERE `client_idno`='$idno' AND `balance`>0");
						if($rem){ makepay($idno,$pid,array(array(["penalties"=>$deduct]),$rem),$ptp,$lid); }
						else{
							savepays($pid,$lid,array(["penalties"=>$deduct]),$ptp); 
							logtrans($lid,json_encode(array("desc"=>"Penalties charges paid","type"=>"credit","amount"=>$penalty,"bal"=>$tbal-$penalty),1),0);
						}
						echo "success";
					}
				}
			}
		}
	}
	
	# reverse payment
	if(isset($_POST['reversepay'])){
		$code = trim($_POST['reversepay']);
		$loan = trim($_POST['revidno']); 
		$ptp = (substr($loan,2,1)=="S") ? "staff":"client";
		$col = ($ptp=="client") ? "client_idno":"stid";
		$tbl = ($ptp=="client") ? "org$cid"."_loans":"staff_loans$cid";
		
		$idno = $db->query(2,"SELECT `$col` FROM `$tbl` WHERE `loan`='$loan'")[0][$col];
		if(reversepay("$idno:$loan",$code,$ptp)){ reset_points($idno); echo "success"; }
		else{ echo "Failed to reverse payment at the moment! Try again later"; }
	}
	
	# transfer payment
	if(isset($_POST['transferpay'])){
		$pid = trim($_POST['transferpay']);
		$name = clean(strtoupper($_POST['bcn']));
		$payb = clean($_POST['bto']);
		$ptbl = "org".$cid."_payments";
		
		$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'"); $dy=$res[0]['date']; $amnt=$res[0]['amount']; $pbf=$res[0]['paybill'];
		$ptm = strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
		$day = strtotime(date("Y-M-d",$ptm));
		
		if($db->execute(2,"UPDATE `$ptbl` SET `paybill`='$payb',`client`='$name' WHERE `id`='$pid'")){
			$db->execute(2,"UPDATE `payin$cid` SET `amount`=(amount-$amnt) WHERE `paybill`='$pbf' AND `day`='$day'");
			$db->execute(2,"UPDATE `payin$cid` SET `amount`=(amount+$amnt) WHERE `paybill`='$payb' AND `day`='$day'");
			echo "success";
		}
		else{ echo "Failed to transfer payment at the moment! Try again later"; }
	}

	//make manual payments
	if(isset($_POST['paytype'])){
		$idno=trim($_POST['client']);
		$amnt=trim($_POST['pamnt']);
		$pcode = (isset($_POST['pcode'])) ? strtoupper(clean($_POST['pcode'])):null;
		$type=trim($_POST['paytype']);
		$dte=strtotime(str_replace("T",",",trim($_POST['pday'])));
		$mon=strtotime(date("Y-M",$dte));
		$day=date("YmdHis",$dte); $tm=time(); 
		$ptbl = "org".$cid."_payments";
		$code = ($pcode) ? $pcode:rand(12345678,87654321);
		$src = (isset($_POST["psrc"])) ? trim($_POST["psrc"]):"client";
		$tbl = ($src=="invs") ? "investors$cid":"org".$cid."_clients";
		$dxt = ($src=="invs") ? "Investor":"Client";
		
		$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `idno`='$idno'");
		$pay = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$code'");
		
		if(!$res){ echo "$dxt ID Number $idno is not found in the system"; }
		elseif($pay){ echo "Failed: Payment Transaction $code already exists"; }
		else{
			$row=$res[0]; $fon=$row['contact']; $name=strtoupper($row['name']);
			$states = array("mpesa"=>1,"cheque"=>2,"cash"=>3); $state=$states[$type]; 
			$payb = (isset($row["branch"])) ? getpaybill($row['branch']):$db->query(1,"SELECT `paybill` FROM `branches` WHERE `status`='0'")[0]['paybill']; 
			
			if($db->execute(2,"INSERT INTO `$ptbl` VALUES(NULL,'$mon','$code','$day','$amnt','$payb','$idno','0','$fon','$name','$state')")){
				$res = $db->query(2,"SELECT id FROM `$ptbl` WHERE `code`='$code'"); $pid=$res[0]['id'];
				if(!$pcode){
					$db->execute(2,"UPDATE `$ptbl` SET `code`='CASH$pid' WHERE `code`='$code'");
				}
				
				insertSQLite("tempdata","CREATE TABLE IF NOT EXISTS mpays (pid TEXT UNIQUE NOT NULL,user INT NOT NULL,time INT NOT NULL)");
				insertSQLite("tempdata","REPLACE INTO `mpays` VALUES('$pid','$sid','$tm')");
				savelog($sid,"Made manual payment of Ksh ".number_format($amnt)." to $name");
				echo "success";
			}
			else{ echo "Failed to complete payment,try again later"; }
		}
	}
	
	#approve manual payments
	if(isset($_POST['approvemanual'])){
		$pid = trim($_POST['approvemanual']);
		$ptbl = "org".$cid."_payments";
		$sql = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'");
		$row = $sql[0]; $status=$row['status']; $amnt=$row['amount']; $payb=$row['paybill']; $code=$row['code']; $dy=$row['date'];
		$ptm = strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
		
		insertSQLite("tempdata","CREATE TABLE IF NOT EXISTS mpays (pid TEXT UNIQUE NOT NULL,user INT NOT NULL,time INT NOT NULL)");
		insertSQLite("mpays","CREATE TABLE IF NOT EXISTS dpays (code TEXT UNIQUE NOT NULL,details TEXT NOT NULL,user INT NOT NULL,time INT NOT NULL)");
		$chk = fetchSqlite("tempdata","SELECT *FROM `mpays` WHERE `pid`='$pid'"); $frm=($chk) ? $chk[0]['user']:0;
		$frm = ($_SERVER["HTTP_HOST"]=="sys.mwangacredit.co.ke") ? 1:$frm;
		$allow = (defined("SUPER_USERS")) ? SUPER_USERS:[];
		
		if($frm==$sid && ($sid>1 && !in_array($sid,$allow))){ echo "Failed: You are not allowed to create and approve the same payment!"; }
		else{
			if($db->execute(2,"UPDATE `$ptbl` SET `status`='0' WHERE `id`='$pid'")){
				$chk = fetchSqlite("mpays","SELECT *FROM `dpays` WHERE `code`='$code'");
				insertSQLite("tempdata","DELETE FROM `mpays` WHERE `pid`='$pid'");
				if($status==1 && !$chk){ recordpay("$payb:$ptm",$amnt,0); }
				savelog($sid,"Approved Manual payment of Ksh $amnt with Code $code");
				echo "success";
			}
			else{ echo "Failed to complete request at the moment"; }
		}
	}
	
	# delete payment
	if(isset($_POST['delpay'])){
		$pid = trim($_POST['delpay']);
		$ptbl = "org".$cid."_payments";
		$sql = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'");
		$row = $sql[0]; $status=$row['status']; $amnt=number_format($row['amount']); $name=$row['client'];
		$code=$row['code']; $tp=($status==0) ? "Client":"Manual"; $jsn=json_encode($row,1);
		
		insertSQLite("mpays","CREATE TABLE IF NOT EXISTS dpays (code TEXT UNIQUE NOT NULL,details TEXT NOT NULL,user INT NOT NULL,time INT NOT NULL)");
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `payment`='$code' AND NOT `status`='9'");
		
		if($qri){ echo "Failed: Payment transaction $code is already linked to Loan template"; }
		else{
			if($db->execute(2,"DELETE FROM `$ptbl` WHERE `id`='$pid' AND `status`<10")){
				if(substr($code,0,6)=="WALLET"){
					$chk = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='$code'");
					if($chk){
						$def = json_encode($chk[0]["details"],1); $des=$def["desc"]; $user=explode(":",$def["user"]);
						updateWallet($user[1],$def["amount"],$user[0],["desc"=>"$des Reversal","revs"=>array(["tbl"=>"norev"])],0);
					}
				}
				
				if($tp=="Client"){ insertSQLite("mpays","REPLACE INTO `dpays` VALUES('$code','$jsn','$sid','".time()."')"); }
				savelog($sid,"Deleted $tp payment of KES $amnt ($code) from $name");
				echo "success";
			}
			else{ echo "Failed to remove payment at the moment"; }
		}
	}
	
	# delete overpayment
	if(isset($_POST['deloverpay'])){
		$rid = trim($_POST['deloverpay']);
		$sql = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$rid'");
		$pid = $sql[0]['payid']; $amnt=$sql[0]['amount'];
		
		if($db->execute(2,"DELETE FROM `overpayments$cid` WHERE `id`='$rid'")){
			$qri = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `payid`='$pid'");
			if($qri){
				$name = $qri[0]['name']; $code = $qri[0]['code'];
				savelog($sid,"Deleted overpayment of KES $amnt from $name Ref $code");
			}
			
			bookbal(DEF_ACCS['overpayment'],"-$amnt");
			echo "success";
		}
		else{ echo "Failed to remove payment at the moment"; }
	}
	
	@ob_end_flush();
?>