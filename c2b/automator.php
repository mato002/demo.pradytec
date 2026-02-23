<?php

	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(110);
	
	include "../core/functions.php";
	if(!isset($_POST['autosave'])){ exit(); }
	openurl("https://$url/core/mailchron.php?ckmails",["ckmails"=>1]);
	
	$db = new DBO(); $cid=CLIENT_ID;
	$ptbl = "org".$cid."_payments";
	$ltbl = "org".$cid."_loans"; 
	$lntb = "org$cid"."_loantemplates"; 
	$stbl = "org".$cid."_schedule";
	
	$sett['autoapprovepays']=0; $sett['loanposting']=0; $wltbl=($db->istable(3,"wallets$cid")) ? 1:0;
	$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting` IN ('autoapprovepays','autopay','loanposting','lastposted')");
	if($res){
		foreach($res as $row){ $sett[$row['setting']]=$row['value']; }
	}
	
	$lpst = (isset($sett['lastposted'])) ? $sett['lastposted']:0; $tmon=strtotime(date("Y-M"));
	$mxid = (isset($sett['autopay'])) ? $sett['autopay']:0; $pids=$lids=[];
	if($db->istable(2,"paysummary$cid")){
		$qri = $db->query(2,"SELECT `officer` FROM `paysummary$cid` WHERE `month`='$tmon' GROUP BY `officer` HAVING(COUNT(*))>1");
		if($qri){
			foreach($qri as $row){ reconpays($row["officer"],$tmon); }
		}
		
		$sql = $db->query(2,"SELECT DISTINCT `loan` FROM `$stbl` WHERE `balance`>0 AND `def`='[]' LIMIT 100");
		if($sql){
			foreach($sql as $rw){
				$qri = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='".$rw["loan"]."' ORDER BY `day` ASC");
				foreach($qri as $k=>$row){
					$id = $row["id"]; $no=$k+1; $def=json_decode($row["def"],1); $def["inst"]=$no; $jsn=json_encode($def,1);
					$db->execute(2,"UPDATE `$stbl` SET `def`='$jsn' WHERE `id`='$id'");
				}
			}
		}
	}
   
	if($sett['autoapprovepays']){
		if($db->istable(2,$ltbl)){
			$stftbl = ($db->istable(2,"staff_loans$cid")) ? 1:0;
			$qri = $db->query(2,"SELECT *FROM `$ptbl` AS pt WHERE `status`='0' AND `id`>$mxid AND NOT EXISTS(SELECT `payment` FROM `$lntb` AS ln WHERE pt.code=ln.payment) ORDER BY `id` ASC");
			if($qri){
				foreach($qri as $row){
					$pid=$row['id']; $amnt=intval($row['amount']); $acc=ltrim($row['account'],"0"); $lid=(substr($acc,0,3)=="PLN") ? substr($acc,3):"";
					$sql = $db->query(2,"SELECT `client_idno`,`penalty` FROM `$ltbl` WHERE (`client_idno`='$acc' OR `phone`='$acc' OR `loan`='$lid') AND (balance+penalty)>0");
					if($sql){
						$idno = $sql[0]['client_idno']; $penam=$sql[0]["penalty"]; $penfst=(defined("PAY_PENALTIES_FIRST")) ? PAY_PENALTIES_FIRST:0;
						$chk = $db->query(2,"SELECT COUNT(id) AS tot FROM `$ltbl` WHERE `client_idno`='$idno' AND (balance+penalty)>0");
						if(intval($chk[0]["tot"])==1){
							if($penfst && $penam>0){
								$penamt = ($penam>$amnt) ? $amnt:$penam;
								$res = request("https://$url/mfs/dbsave/payments.php",["savepay"=>"$idno-client:$pid:$amnt:$lid","penamnt"=>$penamt,"paytp"=>"penrepay","sysreq"=>genToken(0)]);
								if(trim($res)=="success"){ $pids[]=$pid; usleep(200000); }
							}
							else{ makepay($idno,$pid,$amnt,"client",$lid); $pids[]=$pid; usleep(200000); }
						}
					}
					elseif($wres=isAccount($acc)){
						$vtp = $wres["type"]; $desc="Payment from ".$row['client']." #".$row['code'];
						if($con=$db->mysqlcon(2)){
							$con->autocommit(0); $con->query("BEGIN");
							$sqr = $con->query("SELECT *FROM `$ptbl` WHERE `id`='$pid' AND `status`='0' FOR UPDATE");
							if($sqr->num_rows>0){
								if($res=updateWallet($wres["client"],"$amnt:$vtp",$wres["src"],["desc"=>$desc,"revs"=>["tbl"=>"norev"]],0,time(),1)){
									$con->query("UPDATE `$ptbl` SET `status`='$res' WHERE `id`='$pid'"); $pids[]=$pid;
								}
							}
							$con->commit(); $con->close();
						}
					}
					else{
						$sql = $db->query(2,"SELECT `client_idno` FROM `$ltbl` WHERE (`client_idno`='$acc' OR `phone`='$acc' OR `loan`='$lid') AND (balance+penalty)=0");
						if($sql){
							$idno = $sql[0]['client_idno']; $merge=(defined("AUTO_MERGE_PAYS")) ? AUTO_MERGE_PAYS:0;
							if($merge && $wltbl){
								$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]["id"];
								$wid = createWallet("client",$uid);
								if($wid){
									$res = request("https://$url/mfs/dbsave/payments.php",["wlpay"=>$pid,"cwid"=>$wid,"walletp"=>"balance","sysreq"=>genToken(0)]);
									if(explode(":",trim($res))[0]=="success"){ $pids[]=$pid; } usleep(200000);
								}
							}
						}
						else{
							if($stftbl){
								$qry = $db->query(2,"SELECT st.id FROM `org$cid"."_staff` AS st WHERE (`idno`='$acc' OR `contact`='$acc') AND EXISTS
								(SELECT `stid` FROM `staff_loans$cid` AS ln WHERE st.id=ln.stid AND (ln.balance+ln.penalty)>0)");
								if($qry){ makepay($qry[0]['id'],$pid,$amnt,"staff"); $pids[]=$pid; usleep(200000); }
							}
						}
					}
				}
				
				if(count($pids)){
					$mid = max($pids);
					$qry = ($mxid) ? "UPDATE `settings` SET `value`='$mid' WHERE `client`='$cid' AND `setting`='autopay'":"INSERT INTO `settings` VALUES(NULL,'$cid','autopay','$mid')";
					$db->execute(1,$qry);
				}
			}
			
			$allow = (defined("AUTO_REPAY_FROM_WALLET")) ? AUTO_REPAY_FROM_WALLET:1;
			$stym = (defined("WALLET_CHECK_TIME")) ? WALLET_CHECK_TIME:[20,40,0];
			if($wltbl && in_array(intval(date("i")),$stym) && $allow){
				$sql = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `balance`>0 AND (`type`='client' OR `type`='staff')");
				if($sql){
					$stbl = ($db->istable(2,"staff_schedule$cid")) ? 1:0;
					foreach($sql as $row){
						$wid=$row['id']; $uid=$row['client']; $utp=$row['type']; $now=time();
						if($utp=="client"){
							$chk = $db->query(2,"SELECT SUM(sd.balance) AS tbal,sd.loan FROM `org$cid"."_schedule` AS sd INNER JOIN `org$cid"."_clients` AS cl ON cl.idno=sd.idno WHERE 
							cl.id='$uid' AND sd.balance>0 AND sd.day<$now GROUP BY sd.loan");
							if($chk){
								$bal = walletBal($wid); $cut=($bal>$chk[0]["tbal"]) ? $chk[0]['tbal']:$bal; $lid=$chk[0]["loan"];
								if($bal>0){ payFromWallet($uid,$cut,$utp,["desc"=>"Loan $lid Repayment withdrawal","loan"=>$lid],0,1,1); }
							}
							else{
								$chk2 = $db->query(2,"SELECT ln.penalty FROM `org$cid"."_loans` AS ln INNER JOIN `org$cid"."_clients` AS cl ON cl.idno=ln.client_idno 
								WHERE cl.id='$uid' AND ln.penalty>0");
								if($chk2){
									$bal = walletBal($wid); $cut=($bal>$chk2[0]["penalty"]) ? $chk2[0]['penalty']:$bal;
									if($bal>0){ payFromWallet($uid,$cut,$utp,"Penalties claim withdrawal",0,1,1); }
								}
							}
						}
						else{
							if($stbl){
								$chk = $db->query(2,"SELECT SUM(balance) AS tbl,loan FROM `staff_schedule$cid` WHERE `stid`='$uid' AND `balance`>0 AND `day`<$now GROUP BY loan");
								if($chk){
									$bal = walletBal($wid); $cut=($bal>$chk[0]["tbal"]) ? $chk[0]['tbal']:$bal; $lid=$chk[0]["loan"];
									if($bal>0){ payFromWallet($uid,$cut,$utp,["desc"=>"Loan $lid Repayment withdrawal","loan"=>$lid],0,1,1); }
								}
								else{
									$chk2 = $db->query(2,"SELECT penalty FROM `staff_loans$cid` WHERE penalty>0 AND `stid`='$uid'");
									if($chk2){
										$bal = walletBal($wid); $cut=($bal>$chk2[0]["penalty"]) ? $chk2[0]['penalty']:$bal;
										if($bal>0){ payFromWallet($uid,$cut,$utp,"Penalties claim withdrawal",0,1,1); }
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	if($sett['loanposting']){
		if($db->istable(2,"org$cid"."_loantemplates")){
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `status`>200 AND `loan`<10 AND `status`>$lpst ORDER BY `status` ASC LIMIT 30");
			if($qri){
				foreach($qri as $row){
					$rid=$row['id']; $lids[]=$row['status']; $now=time();
					$db->execute(1,"INSERT IGNORE INTO `tempque` VALUES('$cid','CLTMP$cid$rid','0')");
					$res = request("https://$url/mfs/dbsave/loans.php",["postid"=>$rid,"syspost"=>0]); usleep(200000); 
				}
			}
		}
		
		if($db->istable(2,"staff_loans$cid")){
			$qri = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `status`>200 AND `loan`='0' AND `status`>$lpst ORDER BY `status` ASC LIMIT 30");
			if($qri){
				foreach($qri as $row){
					$rid=$row['id']; $lids[]=$row['status']; $now=time(); 
					$db->execute(1,"INSERT IGNORE INTO `tempque` VALUES('$cid','SLTMP$cid$rid','0')");
					$res = request("https://$url/mfs/dbsave/staff_loans.php",["postid"=>$rid,"syspost"=>0]); usleep(200000);
				}
			}
		}
		
		if(count($lids)){
			$mid = max($lids);
			$qry = ($lpst) ? "UPDATE `settings` SET `value`='$mid' WHERE `client`='$cid' AND `setting`='lastposted'":"INSERT INTO `settings` VALUES(NULL,'$cid','lastposted','$mid')";
			$db->execute(1,$qry);
		}
	}
	
	if($db->istable(3,"investments$cid") && intval(date("His"))>=1000){
		$hrf = intval(date("His",time()-130));
		$hrt = intval(date("His"));
		
		$chk = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE `gender`='app' AND `position`='USSD'");
		if($chk){
			$qri = $db->query(3,"SELECT *FROM `investments$cid` WHERE `status`>200 AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.balance'))>0 LIMIT 5");
			if($qri){
				foreach($qri as $rw){
					request("https://$url/mfs/dbsave/investors.php",["appreqd"=>$chk[0]["id"],"withdrawinv"=>$rw['id']]);
				}
			}
		}
		
		$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `status`='0' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.hour')) BETWEEN $hrf AND $hrt");
		if($sql){
			foreach($sql as $rw){
				$inv=$rw['id']; $def=json_decode($rw["details"],1); $hr=$def["hour"]; $pgm=0;
				if(strlen($hr)<6){ $hr=(strlen($hr)==5) ? "0$hr":"00$hr"; }
				$str = implode(":",str_split($hr,2)); $tym=strtotime(date("Y-M-d,$str")); 
				
				if($con=$db->mysqlcon(3)){
					$con->autocommit(0); $con->query("BEGIN");
					$qri = $con->query("SELECT *FROM `invreturns$cid` WHERE `investment`='$inv' AND `status`='0' LIMIT 1 FOR UPDATE");
					if($qri->num_rows){
						$row=$qri->fetch_assoc(); $exp=$row['maturity']; $pays=json_decode($row['payouts'],1);
						foreach(json_decode($row['schedule'],1) as $sdt=>$amnt){
							if(!isset($pays[$sdt]) && $sdt<=$tym){
								$pays[$sdt]=$amnt; $paid=$def["payouts"]; $paid+=$amnt; $def["payouts"]=roundnum($paid,2); 
								$jsnp=json_encode($pays,1); $jsnd=json_encode($def,1); $desc="Investment ".$def["pname"]." returns"; 
								$now=time(); $state=($exp==$sdt) ? $now:0; $stm=($rw['maturity']==$exp) ? $now:0; $rid=$row["id"];
								$con->query("UPDATE `invreturns$cid` SET `payouts`='$jsnp',`status`='$state' WHERE `id`='$rid'");
								$con->query("UPDATE `investments$cid` SET `details`='$jsnd',`status`='$stm' WHERE `id`='$inv'");
								logtrans("INVEST$inv",$desc,0); $pgm+=($state) ? array_sum($pays):0;
							}
							if($sdt>time()){ break; }
						}
					}
					$con->commit(); $con->close();
					if($pgm>0){ transInvPay($inv,$pgm); }
				}
			}
		}
	}
	
	if($db->istable(2,"b2c_trials$cid")){
		$last = time()-20;
		$sql = $db->query(2,"SELECT *FROM `b2c_trials$cid` WHERE `status`='0' AND `time`<=$last LIMIT 10");
		if($sql){
			foreach($sql as $row){
				$def = json_decode($row["source"],1); $pbl=$def["payb"]; 
				$auto = getAutokey($pbl); if(!$auto){ continue; }
				$dtm = hexdec(array_shift($auto)); $rid=$row['id'];
				if($key=decrypt(implode("/",$auto),date("MdY.His",$dtm))){
					if(isset($def["ocid"])){ check_trans($def["ocid"],$key,1,"ocid"); }
					else{
						if((time()-$row['time'])>300){ $db->execute(2,"UPDATE `b2c_trials$cid` SET `status`='1' WHERE `id`='$rid'"); }
					}
				}
			}
		}
	}
	
	request("https://$url/mfs/smschron.php",["checksd"=>1]);
	if(intval(date("Hi"))<330){
		if(!$db->istable(2,"targets$cid")){
			$tcols = ["branch"=>"INT","officer"=>"INT","month"=>"INT","year"=>"INT","results"=>"TEXT","data"=>"TEXT"];
			foreach(array_keys(KPI_LIST) as $col){ $tcols[$col]=(in_array($col,["loanprods","mtd"])) ? "TINYTEXT":"INT"; }
			$db->createTbl(2,"targets$cid",$tcols); 
		}
		
		$lm3 = strtotime(date("Y-M",time()-8035200)); $tmon=strtotime(date("Y-M")); $dy=strtotime(date("Y-M-d")); $targs=[]; $no=0;
		$sql = $db->query(2,"SELECT *FROM `targets$cid` WHERE `month`='$tmon'");
		if($sql){
			foreach($sql as $row){
				$off=$row["officer"]; $rid=$row["id"];
				if(isset($targs[$off])){ $db->execute(2,"DELETE FROM `targets$cid` WHERE `id`='$rid'"); }
				else{ $targs[$off]=json_decode($row["data"],1); }
			}
		}
		
		$qri = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `org$cid"."_loans` WHERE (balance+penalty)>0");
		if($qri){
			foreach($qri as $row){
				$uid=$row["loan_officer"]; $keys=array_keys(KPI_LIST);
				foreach($keys as $key){
					if(in_array($key,["loanprods","assetloans"])){ unset($keys[array_search($key,$keys)]); }
				}
				
				if(!isset($targs[$uid][$dy])){ setTarget($uid,$tmon,$keys,0,1); $no++; }
				else{
					foreach($keys as $key){
						if(!isset($targs[$uid][$dy][$key])){ setTarget($uid,$tmon,$keys,0,1); $no++; break; }
					}
				}
				if($no>=5){ break; }
			}
		}
	}

?>