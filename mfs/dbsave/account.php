<?php
	session_start();
	ob_start();
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	insertSqlite("photos","CREATE TABLE IF NOT EXISTS images (image TEXT UNIQUE NOT NULL,data BLOB)");
	
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
		if($sid<1){ exit(); }
	}
	
	
	# save workplan
	if(isset($_POST["plandate"])){
		$pid = trim($_POST["planid"]);
		$day = strtotime($_POST["plandate"]);
		$plan = $_POST["plan"]; $data=[];
		$sql = $db->query(2,"SELECT *FROM `workplans$cid` WHERE `staff`='$sid' AND `day`='$day'");
		$pid = ($sql) ? $sql[0]["id"]:$pid;
		
		foreach($plan as $col=>$one){
			$data[$col]["clients"]=$one["clients"]; $loc=str_replace(["\r","\n"],",",$one["locs"]);
			$locs[$col]=array_filter(array_map("clean",explode(",",$loc)), fn($value) => !is_null($value) && $value !== '');
		}
		
		$bid = staffInfo($sid)["branch"]; $tjs=json_encode($data,1); $ljs=json_encode($locs,1); $tym=time(); 
		$query = ($pid) ? "UPDATE `workplans$cid` SET `day`='$day',`target`='$tjs',`locations`='$ljs' WHERE `id`='$pid'":
		"INSERT INTO `workplans$cid` VALUES(NULL,'$sid','$bid','$day','$tjs','$ljs','[]','[]','[]','$tym')";
		
		if($db->execute(2,$query)){
			$txt = ($pid) ? "Updated":"Created";
			savelog($sid,"$txt workplan for ".date("d-m-Y",$day));
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save workflow comment
	if(isset($_POST["pcomid"])){
		$pid = trim($_POST["pcomid"]);
		$com = str_replace(["\r\n"],"~nl~",clean($_POST["pcom"]));
		
		$sql = $db->query(2,"SELECT *FROM `workplans$cid` WHERE `id`='$pid'");
		$comms = json_decode($sql[0]["comments"],1); $comms["$sid:".time()]=$com; $cjs=json_encode($comms,1);
		if($db->execute(2,"UPDATE `workplans$cid` SET `comments`='$cjs' WHERE `id`='$pid'")){
			savelog($sid,"Posted comments to ".staffInfo($sql[0]["staff"])["name"]." workplan for ".date("d-m-Y",$sql[0]["day"]));
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# update worfplan achieved
	if(isset($_POST["setachieved"])){
		$cls = str_replace(["\r\n","\n"],",",clean($_POST["pclients"]));
		$clients = array_filter(explode(",",$cls), fn($value) => !is_null($value) && $value !== '');
		$src = explode(":",trim($_POST["setachieved"]));
		$pid = $src[0]; $col=$src[1]; $tp=$src[2];
		$clients = (in_array("none",array_map("strtolower",$clients))) ? []:$clients;
		
		$sql = $db->query(2,"SELECT *FROM `workplans$cid` WHERE `id`='$pid'");
		$res = json_decode($sql[0]["achieved"],1); $res[$col][$tp]=count($clients); $res["time"]=time(); $rjs=json_encode($res,1);
		$clist = json_decode($sql[0]["clients"],1); $clist[$col]=$clients; $cjs=json_encode($clist,1);
		
		if($db->execute(2,"UPDATE `workplans$cid` SET `achieved`='$rjs',`clients`='$cjs' WHERE `id`='$pid'")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# post notes
	if(isset($_POST["snote"])){
		$uid = trim($_POST["nuid"]);
		$rep = trim($_POST["repto"]);
		$mssg = clean($_POST["snote"]);
		$tym = time(); $note=$subj="";
		$state = ($uid==$sid) ? 200:0;
	
		if($rep){
			$sql = $db->query(2,"SELECT `notes`,`subject` FROM `staff_notes$cid` WHERE `id`='$rep'");
			$note = $sql[0]['notes']; $subj=$sql[0]['subject']; 
		}
		else{ $subj=trim($_POST["nsubj"]); }
		
		if($db->execute(2,"INSERT INTO `staff_notes$cid` VALUES(NULL,'$sid','$uid','$subj','$mssg','$note','$state','$tym')")){
			if($rep && $uid==$sid){ $db->execute(2,"UPDATE `staff_notes$cid` SET `status`='200' WHERE `id`='$rep'"); }
			echo 'success';
		}
		else{ echo "Failed to complete request! Try again later"; }
	}
	
	# reverse wallet transaction
	if(isset($_POST["revtrans"])){
		$rid = trim($_POST["revtrans"]);
		$sql = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `id`='$rid' AND `status`='0'");
		if($sql){
			$row=$sql[0]; $data=json_decode($row['reversal'],1); $act=$row['transaction']; $amnt=$row['amount'];
			$wid=$row['wallet']; $bk=$row['book']; $tid=$row['tid']; $des=$row['details']; $now=time();
			$bks = array("Transactional"=>"balance","Savings"=>"savings","Investment"=>"investbal");
			$wtp = $bks[$bk]; $tids[]=$tid; $err=""; $fee=0; $qrys=$revs=$cashset=$trans=$skip=$pyrev=[]; 
			$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
			$uid = $qri[0]['client']; $actp=$qri[0]['type']; 
			
			if($act=="debit"){
				if(walletBal($wid,$wtp)<$amnt && !isset($_POST["sysreq"])){ echo "Insufficient account balance to effect reversal"; }
				else{
					foreach($data as $one){
						if($one["tbl"]=="trans"){ $revs[]=$one["tid"]; }
						if($one["tbl"]=="cashacc"){ $revs[]=$tid; $cashset[]=$one['tid']; }
						if($one["tbl"]=="waltrans"){ $trans[]=$one['tid']; }
						if($one["tbl"]=="loantemp"){ $qrys[]=array("db"=>2,"query"=>"UPDATE `org$cid"."_loantemplates` SET `status`='8' WHERE `id`='".$one["tid"]."'"); }
						if($one["tbl"]=="staffloan"){ $qrys[]=array("db"=>2,"query"=>"UPDATE `staff_loans$cid` SET `status`='8' WHERE `id`='".$one["tid"]."'"); }
						if(in_array($one["tbl"],["utility","advance","requisition"])){ $qrys[]=array("db"=>3,"query"=>"DELETE FROM `utilities$cid` WHERE `id`='".$one["tid"]."'"); }
						if(in_array($one["tbl"],["pays","overpay"])){ $qrys[]=array("db"=>2,"query"=>"UPDATE `org$cid"."_payments` SET `status`='0' WHERE `code`='".$one["tid"]."'"); }
					}
					
					if(updateWallet($uid,"-$amnt:$wtp",$actp,["desc"=>"[Reversal] $des","revs"=>array(["tbl"=>"norev"])],$sid,time(),1)){
						$db->execute(3,"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$tid'");
						foreach($revs as $one){ reversetrans($one,"default",$sid); }
						foreach($cashset as $one){ setCash($one,"-$amnt","[Reversal] $des"); }
						foreach($qrys as $one){ $db->execute($one["db"],$one["query"]); }
						foreach($trans as $one){
							$def=explode(":",$one); $ref=trim(str_replace("#","",explode(" ",$des)[1]));
							$chk = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `tid`='$ref'");
							updateWallet($def[0],"$amnt:balance",$def[1],["desc"=>"[Reversal] ".$chk[0]['details'],"revs"=>array(["tbl"=>"norev"])],$sid,time(),1); 
							$db->execute(3,"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$ref'");
						}
						
						savelog($sid,"Reversed Account Transaction $tid");
						echo "success";
					}
					else{ echo "Failed to complete the Request! Try again later"; }
				}
			}
			else{
				$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
				if($sql){
					foreach($sql as $row){
						$pterms = json_decode($row["payterms"],1); 
						foreach($pterms as $py=>$pay){
							if(in_array(explode(":",$pay)[0],[0,1,4,6])){ $skip[$py]=ucfirst($py); }
						}
					}
				}
		
				foreach($data as $one){
					if($one["tbl"]=="trans"){ $revs[$one["tid"]]="default"; }
					if($one["tbl"]=="cashacc"){ 
						$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='$tid'"); 
						$revs[$chk[0]['transid']]="default"; $revs[$tid]="default"; $cashset[]=$one['tid'];
						$sqt = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `details` LIKE '%Transaction #$tid Charges'");
						if($sqt){
							$id=$sqt[0]['tid']; $revs[$id]="default"; $fee=$sqt[0]['amount'];
							$qrys[]=array("db"=>3,"query"=>"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$id'");
						}
					}
					if($one["tbl"]=="waltrans"){
						$def = explode(":",$one['tid']); $tp=$def[1]; $to=$def[0];
						$wid = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$to' AND `type`='$tp'")[0]['id'];
						if(walletBal($wid,"balance")<$amnt){ $err="Failed: Insufficient balance from recipient wallet"; break; }
						else{ $trans[]=$one['tid']; }
					}
					if($one["tbl"]=="disb"){
						$def=explode(":",$one["tid"]); $rid=$def[0]; $ref=$def[1];
						$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='$ref'");
						$sqr = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `transid`='$rid'");
						$sqt = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `details` LIKE 'Withdrawal #$ref%'");
						$revs[$chk[0]['transid']]=$chk[0]['reversal']; $revs[$rid]=json_decode($sqr[0]['reversal'],1); 
						if($sqt){
							$id=$sqt[0]['tid']; $revs[$id]="default"; $fee=$sqt[0]['amount'];
							$qrys[]=array("db"=>3,"query"=>"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$id'");
						}
					}
					if($one["tbl"]=="withdraw"){
						$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='$tid'"); $revs[$chk[0]['transid']]="default";
						$sqt = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `details` LIKE '%Transaction #$tid Charges'");
						if($sqt){
							$id=$sqt[0]['tid']; $revs[$id]="default"; $amnt+=$sqt[0]['amount'];
							$qrys[]=array("db"=>3,"query"=>"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$id'");
						}
					}
					if($one["tbl"]=="pays"){
						$code = $one["tid"];
						$chk = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$code'");
						if(!$chk){ $err="Failed: Payment Transaction $code doesnt exist"; break; }
						else{
							if($chk[0]['status']){
								$chk2 = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `code`='$code'"); $av=0;
								if($chk2){
									foreach($chk2 as $row){
										$idno = $row['idno'];
										if(in_array(str_replace(" ","_",$row["payment"]),$skip)){ $av++; break; }
									}
									if($av){ $err="Failed: Loan charge fees cannot be reversed"; break; }
									else{ $pyrev[$code]=$idno; }
								}
								else{ $qrys[]=array("db"=>2,"query"=>"DELETE FROM `org$cid"."_payments` WHERE `code`='$code'"); }
							}
							else{
								$chk2 = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `payment`='$code'");
								if($chk2){ $err="Failed: Payment $code is linked to Loan application"; break; }
								else{ $qrys[]=array("db"=>2,"query"=>"DELETE FROM `org$cid"."_payments` WHERE `code`='$code'"); }
							}
						}
					}
				}
				
				$sum = $amnt+$fee;
				if($err){ echo $err; }
				else{
					if(updateWallet($uid,"$sum:$wtp",$actp,["desc"=>"[Reversal] $des","revs"=>array(["tbl"=>"norev"])],$sid,time(),1)){
						$db->execute(3,"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='$tid'");
						foreach($revs as $id=>$rvs){ reversetrans($id,$rvs,$sid); }
						foreach($cashset as $one){ setCash($one,$amnt,"[Reversal] $des"); }
						foreach($qrys as $one){ $db->execute($one["db"],$one["query"]); }
						foreach($trans as $one){
							$def=explode(":",$one); 
							$chk = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `details` LIKE 'Transfer #$tid%'");
							updateWallet($def[0],"-$amnt:balance",$def[1],["desc"=>"[Reversal] ".$chk[0]['details'],"revs"=>array(["tbl"=>"norev"])],$sid,time(),1); 
							$db->execute(3,"UPDATE `walletrans$cid` SET `status`='$now' WHERE `tid`='".$chk[0]['tid']."'");
						}
						foreach($pyrev as $code=>$idno){
							reversepay($idno,$code,$actp,1);
							$db->execute(2,"DELETE FROM `org$cid"."_payments` WHERE `code`='$code'");
						}
						
						savelog($sid,"Reversed Account Transaction $tid");
						echo "success";
					}
					else{ echo "Failed to complete the Request! Try again later"; }
				}
			}
		}
		else{ echo "Failed: Either Transaction doesnt exist or it has been reversed!"; }
	}
	
	# approve initiated request
	if(isset($_POST["approvereq"])){
		$rid = trim($_POST["approvereq"]);
		$val = trim($_POST["sval"]);
		$otp = trim($_POST["votp"]);
		
		$me = staffInfo($sid); $tym=time();
		$fon = (isset($me["office_contact"])) ? $me['office_contact']:$me['contact']; 
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$qri = $db->query(2,"SELECT *FROM `transtemps$cid` WHERE `id`='$rid'");
				$row = $qri[0]; $desc=json_decode($qri[0]['details'],1); $ttl=$desc["title"]; $vtp=$row['type'];
				$desc["approval"]=$sid; $jsn=json_encode($desc,1); $des=$desc["desc"]; 
				
				if($val==15){
					if($db->execute(2,"UPDATE `transtemps$cid` SET `status`='15',`details`='$jsn' WHERE `id`='$rid'")){
						savelog($sid,"Rejected $ttl Request");
						echo "success";
					}
					else{ echo "Failed to complete the request! Try again later"; }
				}
				elseif($row["status"]>200){ echo "success"; }
				else{
					if($vtp=="topups"){
						$wallet=explode(":",$desc["wallet"]); $dep=$desc["deposit"]; $tym=$desc["time"];
						if($res=updateWallet($wallet[1],$dep,$wallet[0],["desc"=>$des,"revs"=>array(["tbl"=>"trans","tid"=>"smtid"])],$sid,$tym)){
							$sum=intval(explode(":",$dep)[0]); $wid=$wallet[2]; $acc=$desc["acc"]; $com=$desc["comment"];
							$day = strtotime(date("Y-M-d",$tym)); $mon=strtotime(date("Y-M",$tym)); $yr=date("Y",$tym); bookbal($acc,"$sum:$mon");
							$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','0','asset','$acc','$sum','debit','$ttl','WID$wid','$com','$sid','auto','$mon','$day','$tym','$yr')");
							$db->execute(2,"UPDATE `transtemps$cid` SET `status`='$tym',`details`='$jsn' WHERE `id`='$rid'");
							$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon' AND `otp`='$otp'");
							logtrans($res,$des,$sid); savelog($sid,"Approved $ttl");
							echo "success";
						}
						else{ echo "Failed to complete the request! Try again later"; }
					}
					elseif($vtp=="wtransfer"){
						$wallet=explode(":",$desc["wallet"]); $dep=$desc["transfer"]; $toid=$desc["acc"]; $uid=$wallet[1]; $actp=$wallet[0]; 
						$tid = ($toid=="reserve") ? getransid():"$toid:$actp"; $dtbl=($toid=="reserve") ? "trans":"waltrans"; $cname=$desc["comment"]; 
						if($res=updateWallet($uid,"-$dep",$actp,["desc"=>$des,"revs"=>array(["tbl"=>$dtbl,"tid"=>$tid])],$sid,$tym)){
							if($toid=="reserve"){
								$wacc = walletAccs(); $amnt=explode(":",$dep)[0];
								doublepost([0,27,$amnt,$des,$res,$sid],[0,$wacc["Transactional"],$amnt,$des,$res,$sid],$ttl,$tym,"auto",$tid);
							}
							else{ updateWallet($toid,$dep,$actp,["desc"=>"Transfer #$res from $cname Transactional Wallet","revs"=>array(["tbl"=>"waltrans","tid"=>"$uid:$actp"])],$sid,$tym,1); }
							
							$db->execute(2,"UPDATE `transtemps$cid` SET `status`='$tym',`details`='$jsn' WHERE `id`='$rid'");
							$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon' AND `otp`='$otp'");
							logtrans($res,$des,$sid); savelog($sid,$des);
							echo "success";
						}
						else{ echo "Failed to complete request! Try again later!"; }
					}
					elseif($vtp=="writeoff"){
						$lid=$desc["loan"]; $amnt=$desc["amount"]; $tbal=0; $deb=27; $crd=23;
						if($con = $db->mysqlcon(2)){
							$con->autocommit(0); $con->query("BEGIN");
							$sql = $con->query("SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid' FOR UPDATE");
							$qri = $con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0 FOR UPDATE");
							if($qri->num_rows){
								$roq = $sql->fetch_assoc();
								while($row=$qri->fetch_assoc()){
									$id=$row['id']; $paid=$row['paid']; $def=json_decode($row["breakdown"],1);
									$bal = ($def["principal"]<=$paid) ? 0:$def["principal"]-$paid; $amt=$row['paid']+$bal;
									foreach($def as $pay=>$sum){
										if($pay!="principal"){ unset($def[$pay]); }
									}
									
									$bjs = json_encode($def,1); $tbal+=$bal;
									$con->query("UPDATE `org$cid"."_schedule` SET `balance`='$bal',`interest`='0',`amount`='$amt',`breakdown`='$bjs' WHERE `id`='$id'");
								}
								
								$con->query("UPDATE `org$cid"."_loans` SET `balance`='$tbal' WHERE `loan`='$lid'");
								$con->commit(); $con->close(); $dtm=date("YmdHis",$tym); $idno=$roq["client_idno"];
								$cname=$roq["client"]; $fon=$roq["phone"]; $bran=$roq["branch"]; $mon=strtotime(date("Y-M"));
								
								$db->execute(2,"INSERT INTO `writtenoff_loans$cid` VALUES(NULL,'$idno','$lid','$amnt','$sid','$tym')");
								$qry = $db->query(2,"SELECT *FROM `writtenoff_loans$cid` WHERE `time`='$tym' AND `loan`='$lid'");
								$wid = $qry[0]['id']; $code="WRITEOFF$wid"; $payb=getpaybill($bran); $tid=getransid();
								$revl[] = array("db"=>2,"tbl"=>"org".$cid."_loans","col"=>$code,"val"=>$idno,"update"=>"revpay:$code");
								$revl[] = array("db"=>2,"tbl"=>"writtenoff_loans$cid","col"=>"id","val"=>$wid,"update"=>"delete:$wid");
								$db->execute(2,"INSERT INTO `org$cid"."_payments` VALUES(NULL,'$mon','$code','$dtm','$amnt','$payb','$idno','0','$fon','$cname','0')");
								$res = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `date`='$dtm' AND `account`='$idno'");
								
								doublepost([$bran,23,$amnt,$des,$tid,$sid],[$bran,27,$amnt,$des,$tid,$sid],$ttl,$tym,json_encode($revl,1));
								makepay($idno,$res[0]['id'],$amnt); logtrans($code,$des,$sid); savelog($sid,"Approved $ttl");
								$db->execute(2,"UPDATE `transtemps$cid` SET `status`='$tym',`details`='$jsn' WHERE `id`='$rid'");
								$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon' AND `otp`='$otp'");
								echo "success";
							}
							else{ echo "Failed: The loan doesnt exist!"; $con->commit(); $con->close(); }
						}
						else{ echo "Failed: System error!"; }
					}
				}
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# fund cashier
	if(isset($_POST["casham"])){
		$amnt = intval($_POST["casham"]);
		$cuid = trim($_POST["cashier"]);
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		$sett = ($qri) ? json_decode($qri[0]['value'],1):[]; $acc=(isset($sett["Cash"])) ? $sett["Cash"]:0;
		if($acc){
			$accname = prepare(ucfirst($db->query(3,"SELECT `account` FROM `accounts$cid` WHERE `id`='$acc'")[0]['account']));
		}
		
		if($amnt<10){ echo "Failed: Minimum Funding is Ksh 10"; }
		elseif(!isset($sett["Cash"])){ echo "Failed: Cash account has not been configured in Chart of accounts!"; }
		elseif(getBookBal($acc)[1]<$amnt){ echo "Failed: Insufficient funds from $accname Treasury"; }
		else{
			if(setCash($cuid,$amnt,"Funded Cash by ".ucwords(staffInfo($sid)["name"])." from $accname")){
				savelog($sid,"Funded ".ucwords(staffInfo($cuid)["name"])." Teller Cash account with Ksh $amnt from $accname");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# collect cash from cashier
	if(isset($_POST["colfunds"])){
		$uid = trim($_POST["colfunds"]);
		$amnt = intval($_POST["camnt"]);
		
		if($amnt<=0){ echo "Error! Invalid Amount"; }
		else{
			if(setCash($uid,"-$amnt","Transfered Cash collection to ".ucwords(staffInfo($sid)["name"]))){
				savelog($sid,"Collected Ksh $amnt Teller cash from ".ucwords(staffInfo($uid)["name"]));
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# mail/SMS staff
	if(isset($_POST['sendto'])){
		$to = trim($_POST['sendto']);
		$mssg = trim($_POST['emssg']); $doc=null;
		$subj = (isset($_POST['subject'])) ? trim($_POST['subject']):"";
		
		if(isset($_FILES["attdoc"])){
			$tmp = $_FILES["attdoc"]['tmp_name'];
			$loc = ($_SERVER['HTTP_HOST']=="localhost") ? "http://$url":"https://$url";
			$ext = @array_pop(explode(".",strtolower($_FILES['attdoc']['name'])));
			if(in_array($ext,["png","jpg","jpeg","gif"])){
				if(@getimagesize($tmp)){
					$img = "IMG_".date("Ymd_His")."$key.$ext";
					if(move_uploaded_file($tmp,"../../docs/img/$img")){
						crop("../../docs/img/$img",1500,1300,"../../docs/img/$img"); 
						$doc = "$loc/docs/img/$img";
					}
					else{ echo "Failed to upload photo: Unknown Error occured"; exit(); }
				}
				else{ echo "File selected is not an Image"; exit(); }
			}
			else{
				if(in_array($ext,array("pdf","docx","doc","xls","xlsx","csv"))){
					$fname = "DOC_".date("Ymd_His").".$ext";
					if(move_uploaded_file($tmp,"../../docs/$fname")){ $doc="$loc/docs/$fname"; }
					else{ echo "Failed to upload document: Unknown Error occured"; exit(); }
				}
				else{ echo "File format $ext is not supported! Only PDF & .docx files are allowed"; exit(); }
			}
		}
		
		if($subj or $doc){
			$res = mailto([$to],[$subj],[frame_email(nl2br($mssg))],[$doc]);
			echo ($res) ? "success":"Failed to send email at the moment!";
		}
		else{
			$res = sendSMS($to,$mssg); 
			echo (substr($res,0,4)=="Sent") ? "success":$res;
		}
	}
	
	# set collection zones
	if(isset($_POST["colz"])){
		$uid = trim($_POST["colz"]);
		$user = staffInfo($uid);
		$conf = json_decode($user["config"],1);
		$conf["zones"]=$_POST["zones"]; $jsn=json_encode($conf,1);
		
		if($db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='$jsn' WHERE `id`='$uid'")){
			savelog($sid,"Updated operation zones for agent ".ucwords($user["name"]));
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# set staff positions
	if(isset($_POST["stuid"])){
		$uid = trim($_POST["stuid"]);
		$post = (isset($_POST["spost"])) ? $_POST["spost"]:[];
		$accs = $_POST["slevs"]; $data=[];
		$usa = staffInfo($uid); $cnf=json_decode($usa["config"],1);
		$posts = (isset($cnf["mypost"])) ? staffPost($sid):[$row["position"]=>$row["access_level"]];
		
		foreach($post as $val){ $grp=explode("%",$val); $data[$grp[0]]=$accs[$grp[1]]; }
		if(count($data)<1 && !isset($posts["super user"])){ echo "Failed: No Job group selected"; }
		else{ 
			$pos=array_keys($data)[0]; $acl=$data[$pos]; 
			if($usa["position"]=="super user"){
				$chk = $db->query(1,"SELECT `id` FROM `useroles`");
				foreach($chk as $rw){ $rols[]=$rw['id']; }
				$pos="super user"; $acl="hq"; $role=implode(",",$rols); $data[$pos]="hq";
			}
			elseif($pos=="collection agent"){ $role=collAgentRoles(); }
			else{ $role = $db->query(1,"SELECT `roles` FROM `staff_groups` WHERE `sgroup`='$pos' AND `client`='$cid'")[0]['roles']; }
			if(count($data)>1){ $cnf["mypost"]=$data; }
			else{ unset($cnf["mypost"]);  }
			
			if($db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='".json_encode($cnf,1)."',`position`='$pos',`roles`='$role',`access_level`='$acl' WHERE `id`='$uid'")){
				savelog($sid,"Updated staff position for ".ucwords($usa["name"]));
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# update account payroll options
	if(isset($_POST['accpay'])){
		$uid = trim($_POST['accpay']);
		$vtp = explode(":",trim($_POST['payval']));
		$def = json_decode(staffInfo($uid)['config'],1); $prev=$def['payroll'];
		$prev[$vtp[0]]=$vtp[1]; $def['payroll']=$prev; $json=json_encode($def,1);
		
		if($db->execute(2,"UPDATE `org".$cid."_staff` SET `config`='$json' WHERE `id`='$uid'")){
			echo "success";
		}
		else{ echo "Failed: Try again later!"; }
	}
	
	# set assistant account upon login
	if(isset($_POST['assacc'])){
		$gid = trim($_POST['assacc']);
		$bran = trim($_POST['asbrn']);
		$acc = trim($_POST['asid']);
		
		$usa = staffInfo($acc); $def=json_decode($usa['config'],1); $cname=ucwords($usa['name']);
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' AND `id`='$gid'");
		$roles = $res[0]['roles']; $def['position']=$res[0]['sgroup']; $jsn=json_encode($def,1);
		
		if($db->execute(2,"UPDATE `org".$cid."_staff` SET `roles`='$roles',`branch`='$bran',`config`='$jsn' WHERE `id`='$acc'")){
			savelog($sid,"Set $cname account to access system as ".$res[0]['sgroup']);
			echo "success";
		}
		else{ echo "Failed to set the account! Try again later"; }
	}
	
	# set session account
	if(isset($_POST["setsesn"])){
		$sess = explode(":",trim($_POST["setsesn"]));
		$post = $sess[0]; $access=$sess[1];
		if($post=="super user"){
			$chk = $db->query(1,"SELECT *FROM `useroles`");
			foreach($chk as $row){ $rols[]=$row['id']; }
			$roles = implode(",",$rols);
		}
		elseif($post=="collection agent"){ $roles=collAgentRoles(); }
		else{ $roles = $db->query(1,"SELECT `roles` FROM `staff_groups` WHERE `sgroup`='$post' AND `client`='$cid'")[0]['roles']; }
		
		if($db->execute(2,"UPDATE `org$cid"."_staff` SET `position`='$post',`roles`='$roles',`access_level`='$access' WHERE `id`='$sid'")){
			savelog($sid,"Switched session account to $post");
			$_SESSION["logas"]=$post;
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# update permissions
	if(isset($_POST['uroles'])){
		$uid = trim($_POST['usar']);
		$user = staffInfo($uid);
		$roles = implode(",",$_POST['uroles']);
		
		if($user['position']=="assistant"){
			$res = fetchSQLite("tempinfo","SELECT *FROM `logins` WHERE `user`='$uid'"); $last=0;
			if(is_array($res)){
				$last = (count($res)) ? $res[0]['lastime']:0;
			}
				
			if((time()-$last)>50){
				$ldy = ($last) ? "The last time of Login was ".date("M d, h:i a",$last):"The account has never Logged in";
				echo "Failed: You can only update assistant permissions while has logged in. $ldy"; exit();
			}
		}
		
		if($db->execute(2,"UPDATE `org".$cid."_staff` SET `roles`='$roles' WHERE `id`='$uid'")){
			savelog($sid,"Updated access permissions for staff ".$user['name']);
			echo "success";
		}
		else{ echo "Failed to update permissions! Try again later"; }
	}
	
	# change password
	if(isset($_POST['changepass'])){
		$uid = trim($_POST['changepass']);
		$pass = trim($_POST['passw']);
		$user = staffInfo($uid); $def=json_decode($user['config'],1);
		$def['key'] = encrypt($pass,date("YMd-his",$user['time']));
		$def['lastreset']=time(); $json=json_encode($def,1);
		
		if(checkpass(trim($_POST['passw']))){
			if($db->execute(2,"UPDATE `org".$cid."_staff` SET `config`='$json' WHERE `id`='$uid'")){
				savelog($sid,"Changed password for staff ".$user['name']);
				echo "success";
			}
			else{ echo "Failed: Try again later"; }
		}
		else{ echo "Password must be at least 8 characters in length and must contain at least one number, one uppercase letter, one lower case letter and one special character."; }
	}
	
	# suspend/unsuspend staff
	if(isset($_POST['suspstid'])){
		$uid = trim($_POST['suspstid']);
		$val = trim($_POST['susval']);
		$user = staffInfo($uid);
		
		if($sid==$uid){ echo "Error! You cannot suspend yourself"; }
		elseif($uid==1){ echo "Failed: You cannot suspend a super user account"; }
		else{
			if($db->execute(2,"UPDATE `org".$cid."_staff` SET `status`='$val' WHERE `id`='$uid'")){
				foreach(staffPost($sid) as $post=>$all){
					if($post=="collection agent" && $val){
						$db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`=JSON_REMOVE(clientdes,'$.agent') WHERE JSON_EXTRACT(clientdes,'$.agent')=$uid");
						$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$uid");
						if($sql){
							foreach($sql as $row){
								$lid=$row['loan']; $des=json_decode($row["clientdes"],1); unset($des["agent"]); 
								$db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`='".json_encode($des,1)."' WHERE `loan`='$lid'");
							}
						}
					}
				}
			
				$txt = ($val) ? "Suspended":"Activated"; 
				savelog($sid,"$txt Staff ".$user["name"]);
				echo "success";
			}
			else{ echo "Failed: Try again later"; }
		}
	}
	
	# assign portfolios to agent
	if(isset($_POST["agentid"])){
		$uid = trim($_POST["agentid"]);
		$arr = trim($_POST["arrno"]);
		$offs = (isset($_POST["offs"])) ? $_POST["offs"]:[];
		$info = staffInfo($uid); $def=json_decode($info["config"],1);
		
		if(count($offs)){
			$def["collaccs"]=$offs; $def["collfro"]=$arr; $jsn=json_encode($def,1); $tdy=strtotime("Today"); $tdy-=round($arr*86400);
			if($db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='$jsn' WHERE `id`='$uid'")){
				$db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`=JSON_REMOVE(clientdes,'$.agent') WHERE JSON_EXTRACT(clientdes,'$.agent')=$uid");
				foreach($offs as $off){
					$sql = $db->query(2,"SELECT `loan` FROM `org$cid"."_schedule` WHERE `officer`='$off' AND `balance`>0 AND `day`<$tdy GROUP BY `loan`");
					if($sql){ 
						foreach($sql as $row){
							$db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`=JSON_INSERT(clientdes,'$.agent',$uid) WHERE `loan`='".$row['loan']."'");
						}
					}
				}
				
				savelog($sid,"Updated portfolios for collection agent ".$info["name"]);
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{
			unset($def["collaccs"],$def["collfro"]); $jsn=json_encode($def,1);
			if($db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='$jsn' WHERE `id`='$uid'")){
				$db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`=JSON_REMOVE(clientdes,'$.agent') WHERE JSON_EXTRACT(clientdes,'$.agent')=$uid");
				savelog($sid,"Removed all loans from collection agent ".$info["name"]);
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# assign loans to agent
	if(isset($_POST["portid"])){
		$uid = trim($_POST["portid"]);
		$ids = trim($_POST["ptids"]);
		$ltbl = "org$cid"."_loans";
		
		if($db->execute(2,"UPDATE `$ltbl` SET `clientdes`=JSON_REMOVE(clientdes,'$.agent') WHERE JSON_EXTRACT(clientdes,'$.agent')=$uid")){
			if($ids){
				foreach(explode(",",rtrim($ids,",")) as $idno){ 
					if($idno){ $db->execute(2,"UPDATE `$ltbl` SET `clientdes`=JSON_INSERT(clientdes,'$.agent',$uid) WHERE `client_idno`='".trim($idno)."' AND (balance+penalty)>0"); }
				}
			}
			
			savelog($sid,"Updated assigned loans for agent ".staffInfo($uid)["name"]);
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save staff
	if(isset($_POST['formkeys'])){
		$stbl = "org".$cid."_staff";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$uid = trim($_POST['id']);
		$sid = trim($_POST['sid']);
		$idno = trim($_POST['idno']);
		$bran = trim($_POST['branch']);
		$email = clean(strtolower($_POST['email']));
		$cont = ltrim(clean($_POST['contact']),"254");
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$usa = ($uid) ? staffInfo($uid):[]; $upds=$fields=$vals=$validate=$pbrn="";
		
		foreach($keys as $key){
			if($key!="id" && !in_array($key,$files)){
				$val = ($key=="config") ? trim($_POST['config']):clean(strtolower($_POST[$key]));
				$val = ($key=="contact") ? ltrim($cont,"0"):$val;
				$val = ($key=="jobno") ? strtoupper($_POST['jobno']):$val;
				$val = ($key=="position") ? trim($_POST['position']):$val;
				$val = ($key=="leaves" && trim($_POST['leaves'])>0) ? trim($_POST['leaves'])*86400:$val;
				if($key=="config"){
					$def = json_decode(trim($_POST['config']),1); 
					if(isset($_POST['upass'])){ $def['key']=encrypt(trim($_POST['upass']),date("YMd-his",$usa['time'])); }
					if($uid){
						$mpoz = (isset($def["mypost"])) ? $def["mypost"]:[];
						if($usa["position"]!=trim($_POST['position']) && !isset($mpoz[trim($_POST['position'])])){ unset($def["mypost"]); }
					}
					$val=$_POST["config"]=json_encode($def,1);
				}
				if($key=="roles"){
					$pos = $_POST['position'];
					if($pos=="super user"){
						$res = $db->query(1,"SELECT id FROM `useroles`");
						foreach($res as $row){ $roles[]=$row['id']; }
						$roles = implode(",",$roles);
					}
					elseif($pos=="assistant"){ $roles = 18; }
					elseif($pos=="collection agent"){ $roles = collAgentRoles(); }
					else{
						if($uid){
							$pbrn = $usa['branch'];
							if($usa['position']==$pos){ $roles = $usa['roles']; }
							else{ $roles = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' AND `sgroup`='$pos'")[0]['roles']; }
						}
						else{
							$roles = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' AND `sgroup`='$pos'")[0]['roles'];
						}
					}
					$val = $roles;
				}
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
			}
		}
		
		$res1 = $db->query(2,"SELECT *FROM `$stbl` WHERE `email`='$email' AND NOT `id`='$uid'"); 
		$res2 = $db->query(2,"SELECT *FROM `$stbl` WHERE `contact`='$cont' AND NOT `id`='$uid'"); 
		$res3 = $db->query(2,"SELECT *FROM `$stbl` WHERE `idno`='$idno' AND NOT `id`='$uid'"); 
		
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		if(count(explode(" ",clean($_POST['name'])))<2 && $uid>1){ echo "Please provide more than one Name"; }
		elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){ echo "Invalid email address! Enter a valid email"; }
		elseif(!is_numeric($cont)){ echo "Failed: Contact must be numeric"; }
		elseif(strlen(ltrim($cont,"0"))!=9){ echo "Invalid Contact! It has ".strlen(ltrim($cont,"0"))." numbers"; }
		elseif($res2){ echo "Failed: Contact $cont is already in use"; }
		elseif($res3){ echo "Failed: Id number $idno is already in use"; }
		elseif($res1){ echo "Failed: Email address $email is already in the system"; }
		elseif($validate){ echo $validate; }
		elseif(isset($_POST['upass']) && !checkpass(trim($_POST['upass']))){
			echo "Password must be at least 8 characters in length and must contain at least one number, one uppercase letter, one lower case letter and one special character.";
		}
		else{
			if(isset($_POST["login_username"])){
				$user = clean(strtolower($_POST["login_username"]));
				if(strlen(prepare($user))<6){ echo "Error! Username is too short! Must be atleast 6 characters"; exit(); }
				$chk = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `login_username`='$user' AND NOT `id`='$uid'");
				if($chk){ echo "Error! Login username exists! Try using another username"; exit(); }
			}
			
			# save files if attached
			if(count($files)){
				$ftps = json_decode(trim($_POST['ftypes']),1);
				foreach($files as $key=>$name){
					$tmp = $_FILES[$name]['tmp_name'];
					$ext = @array_pop(explode(".",strtolower($_FILES[$name]['name'])));
					
					if($ftps[$name]=="image"){
						if(@getimagesize($tmp)){
							if(in_array($ext,array("jpg","jpeg","png","gif"))){
								$img = "IMG_".date("Ymd_His")."$key.$ext";
								if(move_uploaded_file($tmp,"../../docs/img/$img")){
									crop("../../docs/img/$img",1000,700,"../../docs/img/$img");
									$upds.="`$name`='$img',"; $fields.="`$name`,"; $vals.="'$img',";
									insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../../docs/img/$img"))."')");
									unlink("../../docs/img/$img");
								}
								else{ echo "Failed to upload photo: Unknown Error occured"; exit(); }
							}
							else{ echo "Photo format $ext is not supported"; exit(); }
						}
						else{ echo "File selected is not an Image"; exit(); }
					}
					else{
						if(in_array($ext,array("pdf","docx"))){
							$fname = "DOC_".date("Ymd_His").".$ext";
							if(move_uploaded_file($tmp,"../../docs/$fname")){
								$upds.="`$name`='$fname',"; $fields.="`$name`,"; $vals.="'$fname',";
							}
							else{ echo "Failed to upload document: Unknown Error occured"; exit(); }
						}
						else{ echo "File format $ext is not supported! Only PDF & .docx files are allowed"; exit(); }
					}
				}
			}
			
			$ins = rtrim($vals,','); $order = rtrim($fields,','); $tmon=strtotime(date("Y-M"));
			$query = ($uid) ? "UPDATE `$stbl` SET ".rtrim($upds,',')." WHERE `id`='$uid'":"INSERT INTO `$stbl` ($order) VALUES($ins)"; 
			if($db->execute(2,$query)){
				if(trim($_POST['access_level'])=="region"){
					$def=json_decode($_POST['config'],1); $def['region']=trim($_POST['region']); $jsn=json_encode($def,1);
					$db->execute(2,"UPDATE `$stbl` SET `config`='$jsn' WHERE `idno`='$idno'");
				}
			
				if($uid){
					if($bran!=$pbrn && $pbrn){
						$db->execute(2,"UPDATE `org$cid"."_clients` SET `branch`='$bran' WHERE `loan_officer`='$uid' AND `status`='1'");
						$db->execute(2,"UPDATE `org$cid"."_loans` SET `branch`='$bran' WHERE `loan_officer`='$uid' AND `status`='0'");
						$db->execute(2,"UPDATE `paysummary$cid` SET `branch`='$bran' WHERE `officer`='$uid' AND `month`='$tmon'");
						$db->execute(2,"UPDATE `processed_payments$cid` SET `branch`='$bran' WHERE `officer`='$uid' AND `month`='$tmon'");
						if($db->istable(2,"targets$cid")){ $db->execute(2,"UPDATE `targets$cid` SET `branch`='$bran' WHERE `officer`='$uid' AND `month`='$tmon'"); }
					}
				}
				else{
					$name = ucwords(strtolower($_POST['name'])); $url=BASE_URL; $pass=decrypt(json_decode($_POST['config'],1)['key'],date("YMd-his",$_POST['time']));
					sendSMS($cont,"Hi $name, Your account has been created successfull. Login at https://$url using username $name and password $pass.");
				}
				
				$txt = ($uid) ? "Updated account details for":"Created new account for staff";
				savelog($sid,"$txt ".ucwords($_POST['name'])); 
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# change profile
	if(isset($_FILES['profile'])){
		$stbl = "org".$cid."_staff";
		$stid = trim($_POST['staff']);
		$tmp = $_FILES['profile']['tmp_name'];
		$ext = @strtolower(array_pop(explode(".",strtolower($_FILES['profile']['name']))));
		$get = array("png","jpg","jpeg","gif");
		
		$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `id`='$stid'");
		$def = json_decode($res[0]['config'],1); $prof=$def['profile'];
		
		if(@getimagesize($tmp)){
			if(in_array($ext,$get)){
				$img = "Prof_".date("Ymd_His").".$ext";
				$def['profile']=$img; $cnf=json_encode($def,1);
				
				if(move_uploaded_file($tmp,"../../docs/img/$img")){
					if(cropSQ("../../docs/img/$img",300,"../../docs/img/$img")){
						if($db->execute(2,"UPDATE `$stbl` SET `config`='$cnf' WHERE `id`='$stid'")){
							insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../../docs/img/$img"))."')");
							unlink("../../docs/img/$img");
							if($prof!="none"){ insertSqlite("photos","DELETE FROM `images` WHERE `image`='$prof'"); }
							savelog($stid,"Changed profile picture");
							echo "success";
						}
						else{
							echo "Failed to update photo! Try again later"; unlink("../../docs/img/$img");
						}
					}
					else{ echo "Failed to save photo"; }
				}
				else{ echo "Failed: Unknown Error occured"; }
			}
			else{ echo "Failed: Image extension $ext is not supported"; }
		}
		else{ echo "Invalid Image! Please select a valid photo"; }
	}

?>