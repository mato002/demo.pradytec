<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
    
    require "functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	if(isset($_POST['updateday'])){
		$today=$tdy=strtotime(date("Y-M-d")); $ldy=strtotime("Yesterday");
		$ltbl="org".$cid."_loans"; $stbl="org".$cid."_schedule"; $nxt=$dfro=0;
		foreach(array($ltbl,$stbl,"org".$cid."_clients","org".$cid."_loantemplates") as $tbl){
			if(!$db->istable(2,$tbl)){ $nxt=1; break; }
		}
		if($nxt){ exit(); }
		
		if($con=$db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='lastarrears'");
			if($sql->num_rows>0){
				$dfro=$sql->fetch_assoc()['value'];
				$con->query("UPDATE `settings` SET `value`='$ldy' WHERE `client`='$cid' AND `setting`='lastarrears'");
			}
			else{ $con->query("INSERT INTO `settings` VALUES(NULL,'$cid','lastarrears','$ldy')"); }
			$con->commit(); $con->close(); 
		}
		else{ exit(); }
		
		$sett=$defs=$arrears=$daily=$pens=$ins=$brans=$loans=$intds=[];
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'"); 
		foreach($qri as $row){ $sett[$row['setting']]=prepare($row['value']); } 
		$apply = (isset($sett['applyarrears'])) ? $sett['applyarrears']:1;
		$allocate = (isset($sett['allocate_arrears'])) ? $sett['allocate_arrears']:0;
		$consts = (isset($sett['system_constants'])) ? json_decode($sett['system_constants'],1):[];
		$pbcol = (isset($sett['paybcollection'])) ? $sett['paybcollection']:0;
		
		$alertarrs = (isset($sett['alert_new_arrears'])) ? explode(",",$sett['alert_new_arrears']):[];
		if($alertarrs){
			if(!$db->istable(2,"staff_notes$cid")){
				$db->createTbl(2,"staff_notes$cid",["sender"=>"INT","staff"=>"INT","subject"=>"CHAR","notes"=>"TEXT","repto"=>"TEXT","status"=>"INT","time"=>"INT"]);
			}
		}
		
		# update interest for cash-basis
		if(isset($sett["intrcollectype"]) && $dfro<$ldy){
			if($sett["intrcollectype"]=="Cash"){
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `day`>=$tdy AND `balance`>0 AND JSON_EXTRACT(breakdown,'$.interest')>0");
				if($sql){
					foreach($sql as $row){
						$lid=$row['loan']; $dy=$row['day'];
						if(!in_array($lid,$intds)){
							$chk = $db->query(2,"SELECT *FROM `daily_interests$cid` WHERE `loan`='$lid'");
							if($chk){
								$def = json_decode($chk[0]['schedule'],1); 
								if(isset($def[$dy])){
									if(isset($def[$dy][$tdy])){ setInterest($lid); $intds[]=$lid; }
								}
							}
						}
					}
				}
			}
		}
		
		# apply arrears
		if($apply){
			if($dfro<$ldy){
				$sfrm = (isset($sett['penalty_from_day'])) ? $sett['penalty_from_day']*86400:0; $dfx=$dfro-$sfrm;
				if(!$db->istable(2,"penalties$cid")){ $db->createTbl(2,"penalties$cid",["loan"=>"CHAR","totals"=>"INT","paid"=>"INT","installments"=>"TEXT"]); }
				else{ $db->execute(2,"DELETE FROM `penalties$cid` WHERE `totals`<=0"); }
				
				$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
				if($qry){
					foreach($qry as $row){
						$defs[$row['id']]=[$row['penalty'],$row["intervals"]]; 
						if(explode(":",$row['penalty'])[0]=="daily"){ $id=$row["id"]; $daily[$id]="'$id'"; }
					}
				}
				
				if($daily){
					$ftc = (defined("APPLY_ARREARS_FROM")) ? "AND ln.disbursement>=".strtotime(APPLY_ARREARS_FROM):"";
					$qri = $db->query(2,"SELECT sc.*,MIN(sc.day) AS mdy,SUM(sc.balance) AS arr,ln.loan_product,ln.clientdes,ln.balance AS tbal FROM $stbl AS sc INNER JOIN $ltbl AS ln 
					ON sc.loan=ln.loan WHERE sc.balance>0 AND (sc.day+$sfrm)<=$ldy AND ln.loan_product IN (".implode(",",$daily).") $ftc GROUP BY sc.loan");
					if($qri){
						foreach($qri as $row){
							$prod = $row['loan_product']; $lid=$row['loan']; $bal=$row['arr']; $idno=$row['idno']; $stid=$row['officer'];
							$chk = $db->query(2,"SELECT SUM(amount) AS tsum FROM `org$cid"."_payments` WHERE `account`='$idno' AND `status`='0'");
							$charge = explode(":",$defs[$prod][0]); $tp=$charge[0]; $val=$charge[1]; $day=$row['mdy'];
							$paid = ($chk) ? intval($chk[0]['tsum']):0; $des=@json_decode($row["clientdes"],1); $intv=$defs[$prod][1];
							$sum = ($tp=="default") ? $row['tbal']:$bal; $lnst=(isset($des["loanst"])) ? $des["loanst"]:0; $tbal=$row['tbal'];
							$cost = (count(explode("%",$val))>1) ? round($sum*explode("%",$val)[0]/100):$val; $lis=[];
							
							if($bal>$paid && !$lnst && $day<$today){
								if($con = $db->mysqlcon(2)){
									$con->autocommit(0); $con->query("BEGIN");
									$con->query("SELECT *FROM `$ltbl` WHERE `loan`='$lid' FOR UPDATE");
									$sql = $con->query("SELECT *FROM `penalties$cid` WHERE `loan`='$lid' FOR UPDATE");
									$num = $sql->num_rows; $roq=$sql->fetch_assoc(); $opn=($num) ? $roq["totals"]:0; $pen=$cost; 
									if($cost>0){
										$pyd = ($num) ? $roq["paid"]:0; $opn=($opn<$pyd) ? $pyd:$opn; $jsn=json_encode(["all"],1); $tpn=$opn+$pen; 
										$query = ($num) ? "UPDATE `penalties$cid` SET `totals`='$tpn',`installments`='$jsn' WHERE `loan`='$lid'":
										"INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$pen','0','$jsn')"; $con->query($query); $rem=$tpn-$pyd;
										$con->query("UPDATE `$ltbl` SET `penalty`='$rem' WHERE `loan`='$lid'");
										logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$pen,"bal"=>$tbal+$rem),1),0);
									}
									$con->commit(); $con->close();
								}
							}
						}
					}
				}
				
				$cond = ($sfrm) ? "AND (sc.day+$sfrm) BETWEEN $dfx AND $ldy":"AND sc.day BETWEEN $dfro AND $ldy";
				$cond.= (defined("APPLY_ARREARS_FROM")) ? " AND ln.disbursement>=".strtotime(APPLY_ARREARS_FROM):"";
				$res = $db->query(2,"SELECT sc.*,ln.loan_product,ln.expiry,ln.clientdes,ln.balance AS tbal FROM $stbl AS sc INNER JOIN $ltbl AS ln ON sc.loan=ln.loan WHERE sc.balance>0 $cond");
				if($res){
					foreach($res as $row){
						if(isset($daily[$row["loan_product"]])){ continue; }
						$prod=$row['loan_product']; $lid=$row['loan']; $bal=$row['balance']; $idno=$row['idno']; $stid=$row['officer'];
						$chk = $db->query(2,"SELECT SUM(amount) AS tsum FROM `org$cid"."_payments` WHERE `account`='$idno' AND `status`='0'");
						$expd=$row['expiry']; $charge=explode(":",$defs[$prod][0]); $tp=$charge[0]; $val=$charge[1]; $day=$row['day'];
						$paid=($chk) ? intval($chk[0]['tsum']):0; $des=@json_decode($row["clientdes"],1); $intv=$defs[$prod][1];
						$sum = ($tp=="default") ? $row['tbal']:$bal; $lnst=(isset($des["loanst"])) ? $des["loanst"]:0; $tbal=$row['tbal'];
						$cost = (count(explode("%",$val))>1) ? round($sum*explode("%",$val)[0]/100):$val; $lis=[];
						
						if($bal>$paid && !$lnst && $day<$today){
							if($con = $db->mysqlcon(2)){
								$con->autocommit(0); $con->query("BEGIN");
								$con->query("SELECT *FROM `$ltbl` WHERE `loan`='$lid' FOR UPDATE");
								$sql = $con->query("SELECT *FROM `penalties$cid` WHERE `loan`='$lid' FOR UPDATE");
								$num = $sql->num_rows; $roq=$sql->fetch_assoc(); $pen=($num) ? $roq["totals"]:0; 
								$pyd = ($num) ? $roq["paid"]:0; $pen=($pen<$pyd) ? $pyd:$pen;
					
								if($tp=="installment"){
									$prv=($num) ? json_decode($roq['installments'],1):[];
									if(!in_array($day,$prv) && $cost>0){
										$pen+=$cost; $prv[]=$day; $jsn=json_encode($prv,1); $rem=$pen-$pyd;
										$query = ($num) ? "UPDATE `penalties$cid` SET `totals`='$pen',`installments`='$jsn' WHERE `loan`='$lid'":
										"INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$pen','0','$jsn')"; $con->query($query);
										$con->query("UPDATE `$ltbl` SET `penalty`='$rem' WHERE `loan`='$lid'");
										logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$cost,"bal"=>$tbal+$rem),1),0);
									}
								}
								elseif($tp=="default"){
									if(!$num && $expd<$today && $cost>0){
										$pen=$cost; $jsn=json_encode(["all"],1); $rem=$pen-$pyd;
										$con->query("INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$pen','0','$jsn')");
										$con->query("UPDATE `$ltbl` SET `penalty`='$rem' WHERE `loan`='$lid'");
										logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$cost,"bal"=>$tbal+$rem),1),0);
									}
								}
								else{
									if(!$num && $cost>0){
										$pen=$cost; $jsn=json_encode(["all"],1); $rem=$pen-$pyd;
										$con->query("INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$pen','0','$jsn')");
										$con->query("UPDATE `$ltbl` SET `penalty`='$rem' WHERE `loan`='$lid'");
										logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$cost,"bal"=>$tbal+$rem),1),0);
									}
								}
								$con->commit(); $con->close();
							}
						}
					}
				}
			}
		}
	}

?>