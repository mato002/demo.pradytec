<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# create reconciliation
	if(isset($_POST["racc"])){
		$acc = trim($_POST["racc"]);
		$mon = strtotime(trim($_POST["recmon"]));
		$bal = intval($_POST["rcbal"]);
		$name = clean($_POST["rcname"]); $now=time();
		$proj = json_encode(["closebal"=>$bal,"user"=>$sid,"checked"=>[]],1);
		
		$sql = $db->query(3,"SELECT *FROM `reconns$cid` WHERE `title`='$name'");
		if($sql){ echo "Failed: Report already exists"; }
		else{
			if($db->execute(3,"INSERT INTO `reconns$cid` VALUES(NULL,'$acc','$name','$mon','$proj','0','$now')")){
				$rid = $db->query(3,"SELECT `id` FROM `reconns$cid` WHERE `title`='$name' AND `time`='$now'")[0]['id'];
				savelog($sid,"Created reconciliation project $name"); $_SESSION["reconn"]=$rid;
				echo "success:$rid";
			}
			else{ echo "Failed to create project! Try again later"; }
		}
	}
	
	# reconcile transaction
	if(isset($_POST["confmatch"])){
		$def = explode(":",trim($_POST["confmatch"]));
		$rid = $def[0]; $id=$def[1];
		$sql = $db->query(3,"SELECT *FROM `reconns$cid` WHERE `id`='$rid'");
		$res = json_decode($sql[0]['report'],1); $res["checked"][]=$id; $jsn=json_encode($res,1);
		
		if($db->execute(3,"UPDATE `reconns$cid` SET `report`='$jsn' WHERE `id`='$rid'")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# update wallet acclounts
	if(isset($_POST["setwall"])){
		$accs = $_POST["setwall"]; 
		$json = json_encode($accs,1); $error="";
		foreach($accs as $key=>$val){
			if($val==0){ $error = "Failed: Select account for $key Account First!"; break; }
		}
		
		if($error){ echo $error; }
		else{
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
			$query = ($chk) ? "UPDATE `settings` SET `value`='$json' WHERE `client`='$cid' AND `setting`='wallet_accounts'":
			"INSERT INTO `settings` VALUES(NULL,'$cid','wallet_accounts','$json')";
			if($db->execute(1,$query)){
				savelog($sid,"Updated wallect accounts");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# save budget
	if(isset($_POST["bdgid"])){
		$bid = trim($_POST["bdgid"]);
		$acc = trim($_POST["bacc"]);
		$brn = trim($_POST["bbran"]);
		$mon = strtotime(trim($_POST["bmon"]));
		$sum = intval($_POST["budamt"]);
		
		$chk = $db->query(3,"SELECT *FROM `budgets$cid` WHERE `book`='$acc' AND `branch`='$brn' AND `month`='$mon' AND NOT `id`='$bid'");
		if($sum<0){ echo "Invalid amount! Shoud be >=0"; }
		elseif($chk){ echo "Failed: Budget record is already set!"; }
		else{
			$yr=date("Y",$mon); $tym=time(); $des=json_encode(["creator"=>$sid,"status"=>"0"],1);
			$query = ($bid) ? "UPDATE `budgets$cid` SET `book`='$acc',`branch`='$brn',`amount`='$sum' WHERE `id`='$bid'":
			"INSERT INTO `budgets$cid` VALUES(NULL,'$acc','$brn','$sum','$des','$mon','$yr','$tym')";
			if($db->execute(3,$query)){
				$aname = $db->query(3,"SELECT `account` FROM `accounts$cid` WHERE `id`='$acc'")[0]['account'];
				$bname = ($brn) ? $db->query(1,"SELECT `branch` FROM `branches` WHERE `id`='$brn'")[0]['branch']:"Head Office";
				$txt = ($bid) ? "Updated budget expense $aname for $bname":"Created budget expense $aname for $bname";
				savelog($sid,$txt); echo "success";
			}
			else{ echo "Failed to save the budget!"; }
		}
	}
	
	# approve/decline budget
	if(isset($_POST["budgetst"])){
		$rid = trim($_POST["budgetst"]);
		$val = trim($_POST["stval"]);
		$query = ($val) ? "UPDATE `budgets$cid` SET `details`=JSON_SET(details,'$.status',$sid) WHERE `id`='$rid'":"DELETE FROM `budgets$cid` WHERE `id`='$rid'";
		if($db->execute(3,$query)){
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# link paybill to bank
	if(isset($_POST['linkid'])){
		$pid = trim($_POST['linkid']);
		$bid = clean($_POST['lbank']);
		$cred = clean($_POST['cpayb']);
		$payb = clean($_POST['fpayb']);
		$fday = strtotime(trim($_POST['fday']));
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$bid'");
		$bname = $qri[0]['account']; $qrys=[]; $sum=0;
		
		if($bid==$cred){ echo "Failed: The bank cant be the same as the Paybill"; }
		else{
			$res = $db->query(2,"SELECT *FROM `payin$cid` WHERE `status`='0' AND `paybill`='$payb' AND `day`>=$fday");
			if($res){
				$sno = getransid(); 
				foreach($res as $row){
					$des = "Payins from paybill $payb for ".date("d-m-Y",$row['day']); $dy=$row['day']; $yr=$row['year']; 
					$amnt=$row['amount']; $ref="P".$row['id']; $mon=$row['month']; $now=strtotime(date("Y-m-d,H:i",$dy+86399)); 
					$sum+=$amnt; $rev=json_encode(array(["tbl"=>"payin$cid","db"=>2,"update"=>"status:0","col"=>"id","val"=>$row['id']]),1);
					$qrys[] = "(NULL,'$sno','0','asset','$cred','$amnt','debit','$des','$ref','','$sid','$rev','$mon','$dy','$now','$yr')"; $sno+=1;
				}
				$db->execute(1,"UPDATE `transcodes` SET `code`='$sno' WHERE `client`='$cid'");
			}
			
			if($pid){
				$res = $db->query(1,"SELECT *FROM `paybill_banks` WHERE `id`='$pid'");
				$row = $res[0]; $prev=$row['bank']; $pay=$row['paybill'];
				
				if($prev==$bid && $pay==$payb){ echo "Failed: No changes made!"; }
				else{
					if($db->execute(1,"UPDATE `paybill_banks` SET `paybill`='$payb',`bank`='$bid' WHERE `id`='$pid'")){
						$db->execute(3,"DELETE FROM `transactions$cid` WHERE `refno`='P$pay' AND `day`>=$fday");
						$db->execute(2,"UPDATE `payin$cid` SET `status`='0' WHERE `paybill`='$pay' AND `day`>=$fday");
						if(count($qrys)){
							$db->execute(2,"UPDATE `payin$cid` SET `status`='1' WHERE `status`='0' AND `paybill`='$payb'");
							foreach(array_chunk($qrys,150) as $chunk){
								$db->execute(3,"INSERT INTO `transactions$cid` VALUES ".implode(",",$chunk));
							}
						}
						
						if($sum){ bookbal($cred,"+$sum"); }
						savelog($sid,"Linked paybill $payb to $bname from Paybill $pay");
						echo "success";
					}
					else{ echo "Failed to Link the bank! Try again later"; }
				}
			}
			else{
				if($db->execute(1,"INSERT INTO `paybill_banks` VALUES(NULL,'$cid','$payb','$cred','$bid')")){
					$db->execute(2,"UPDATE `payin$cid` SET `status`='1' WHERE `status`='0' AND `paybill`='$payb' AND `day`>=$fday");
					foreach(array_chunk($qrys,150) as $chunk){
						$db->execute(3,"INSERT INTO `transactions$cid` VALUES ".implode(",",$chunk));
					}
					
					if($sum){ bookbal($cred,"+$sum"); }
					savelog($sid,"Linked paybill $payb to $bname");
					echo "success";
				}
				else{ echo "Failed to Link the bank! Try again later"; }
			}
		}
	}
	
	# post paybill transfer
	if(isset($_POST['postrans'])){
		$pid = trim($_POST['postrans']);
		$res = $db->query(2,"SELECT *FROM `paybill_transfers$cid` WHERE `id`='$pid'");
		$payb = $res[0]['paybill']; $amnt=$res[0]['amount']; $ptm=$res[0]['time']; $state=$res[0]['status']; $day=date("d-m-Y",$ptm);
		$mon=strtotime(date("Y-M",$ptm)); $dy=strtotime(date("Y-M-d",$ptm)); $yr=date("Y",$ptm);
		
		if($state){ echo "success"; exit(); }

		$qri = $db->query(1,"SELECT *FROM `paybill_banks` WHERE `paybill`='$payb' AND `client`='$cid'");
		$bid = $qri[0]['bank']; $acc=$qri[0]['linked']; $sno = getransid(); $now=time();
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$bid'"); $bname = $qri[0]['account'];
		
		$des1 = "Transfer payment from paybill $payb for $day"; $des2 = "Transfer payment to $bname for $day";
		$rev = json_encode(array(["tbl"=>"paybill_transfers$cid","db"=>2,"update"=>"status:0","col"=>"id","val"=>$pid]),1);
		$vals = ["(NULL,'$sno','0','asset','$bid','$amnt','debit','$des1','$payb','','$sid','$rev','$mon','$dy','$now','$yr')",
		"(NULL,'$sno','0','asset','$acc','$amnt','credit','$des2','$payb','','$sid','$rev','$mon','$dy','$now','$yr')"];
		
		if($db->execute(3,"INSERT INTO `transactions$cid` VALUES ".implode(",",$vals))){
			$db->execute(2,"UPDATE `paybill_transfers$cid` SET `status`='1' WHERE `id`='$pid'");
			bookbal($bid,"+$amnt"); bookbal($acc,"-$amnt");
			savelog($sid,"Transfered payment from paybill $payb to $bname for $day");
			echo "success";
		}
		else{ echo "Failed to post transaction! Try again later"; }
	}
	
	# save payroll settings
	if(isset($_POST['maxs'])){
		$maxs = $_POST['maxs'];
		$mins = $_POST['mins'];
		$sums = $_POST['sums'];
		$error = "";
		
		foreach($maxs as $name=>$one){
			foreach($one as $key=>$max){
				$min = $mins[$name][$key]; $rate=$sums[$name][$key]; 
				$data[clean(prepare($name))]["$min-$max"] = $rate;
				$mn=($key>0) ? $mins[$name][$key-1]:0; $mx=($key>0) ? $maxs[$name][$key-1]:0;
				
				if($min>=$max){ 
					$error = "Error! Minimum value $min must be less than maximum value $max for ".prepare($name); break; 
				}
				if($min<=$mx && $key>0){
					$error = "Error! Minimum value $min cannot be less or equal to Maximum of above row $mn-$mx for ".prepare($name); break; 
				}
			}
			if($error){ break; }
		}
		
		if($error){ echo $error; }
		else{
			$jsn = json_encode($data,1); 
			$chk1 = $db->query(1,"SELECT *FROM `paycuts` WHERE `client`='$cid' AND `deduction`='statutory'"); 
			$query = ($chk1) ? "UPDATE `paycuts` SET `cut`='$jsn' WHERE `client`='$cid' AND `deduction`='statutory'":"INSERT INTO `paycuts` VALUES(NULL,'$cid','statutory','$jsn')";
			if($db->execute(1,$query)){
				savelog($sid,"Updated statutory deductions settings");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# remove statutory deduction
	if(isset($_POST["delcut"])){
		$cut = trim($_POST["delcut"]);
		$sql = $db->query(1,"SELECT *FROM `paycuts` WHERE `client`='$cid' AND `deduction`='statutory'");
		
		$data = json_decode($sql[0]["cut"],1); unset($data[$cut]); $jsn=json_encode($data,1);
		if($db->execute(1,"UPDATE `paycuts` SET `cut`='$jsn' WHERE `client`='$cid' AND `deduction`='statutory'")){
			savelog($sid,"Removed $cut from statutory deductions");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save deduction record
	if(isset($_POST['dedmon'])){
		$mon = strtotime(trim($_POST['dedmon']));
		$did = trim($_POST['did']);
		$cat = clean($_POST['dedtp']);
		$dtp = clean($_POST['dedcat']);
		$amnt = clean($_POST['dedamnt']);
		$src = explode(":",trim($_POST['staff']));
		$yr = date("Y",$mon); $tmon=strtotime(date("Y-M")); 
		$rule = getrule("advances"); $tym=time(); $stid=$src[0];
		$deb = $rule['debit']; $cred=$rule['credit'];
		
		$usa = ($stid) ? staffInfo($stid):[];
		$bran = ($stid) ? $usa['branch']:0;
		$sname = ($stid) ? ucwords($usa['name']):"";
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
		$posted = ($qri) ? json_decode($qri[0]["value"],1):[]; $cat.=(isset($src[1])) ? " ".$src[1]:"";
		
		if(isset($posted[$mon])){ echo "Failed: ".date("F Y",$mon)." is locked after payroll deductions were posted to Journal accounts"; }
		elseif($amnt<1){ echo "Error: Amount should be greater than 0"; }
		elseif(strtolower(trim(substr($cat,0,11)))=="staff loan" && $dtp!="loan"){ echo "Error! Staff Loan must be selected from available list only"; }
		else{
			if($did){
				$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `id`='$did'");
				$pamnt=$res[0]['amount']; $pmon=$res[0]['month']; $pcat=$res[0]['category']; $ref=strtoupper(date("M",$pmon)).$did;
				
				if($db->execute(3,"UPDATE `deductions$cid` SET `amount`='$amnt',`category`='$cat',`month`='$mon',`year`='$yr' WHERE `id`='$did'")){
					if($pcat=="Salary advance" && $dtp=="other"){
						$db->execute(3,"DELETE FROM `transactions$cid` WHERE `refno`='$ref'");
						if($mon<$tmon){ bookbal($rule['debit'],"-$amnt:$mon",$bran); bookbal($rule['credit'],"+$amnt:$mon",$bran); }
						bookbal($rule['debit'],"-$amnt",$bran); bookbal($rule['credit'],"+$amnt",$bran); 
					}
					else{
						if($dtp=="advance"){
							if($pcat!=""){
								$desc = ucfirst($cat)." deduction for staff $sname for month of ".date("F Y",$mon); 
								$nref = strtoupper(date("M",$mon)).$did; $bran=$usa['branch'];
								doublepost([$bran,$cred,$amnt,$desc,$nref,$sid],[$bran,$deb,$amnt,$desc,$nref,$sid],'',$tym,"auto"); 
								if($mon<$tmon){ bookbal($deb,"+$amnt:$mon",$bran); bookbal($cred,"-$amnt:$mon",$bran); }
							}
							else{
								$nref = strtoupper(date("M",$mon)).$did;
								$db->execute(3,"UPDATE `transactions$cid` SET `amount`='$amnt',`refno`='$nref' WHERE `refno`='$ref'");
								
								if($mon!=$pmon){
									if($pmon<=$tmon){
										bookbal($deb,"-$pamnt:$pmon",$bran); bookbal($cred,"+$pamnt:$pmon",$bran); 
										if($pmon!=$tmon){ bookbal($cred,"+$pamnt",$bran); }
									}
									if($mon<=$tmon){
										bookbal($deb,"+$amnt:$mon",$bran); bookbal($cred,"-$amnt:$mon",$bran); 
										if($mon!=$tmon){ bookbal($cred,"-$pamnt",$bran); }
									}
								}
								else{
									if($pamnt!=$amnt && $mon<=$tmon){
										$upd1 = ($pamnt>$amnt) ? $amnt-$pamnt:"+".($amnt-$pamnt); $upd2 = ($pamnt>$amnt) ? "+".($pamnt-$amnt):$pamnt-$amnt; 
										bookbal($deb,"$upd1:$mon",$bran); bookbal($cred,"$upd2:$mon",$bran); 
										if($mon!=$tmon){ bookbal($cred,"$upd2",$bran); }
									}
								}
							}
						}
					}
					
					savelog($sid,"Updated $cat deductions of KES $amnt for $sname in month of ".date("F Y",$mon));
					echo "success:$mon";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
			else{
				if($dtp=="advance" && $stid==0){
					echo "Failed: Salary advance cant be applied to all staffs at once!";
				}
				else{
					if($stid){ $qrys[] = "(NULL,'$stid','$cat','$amnt','$mon','$yr','$tym','$tym')"; }
					else{
						$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='1' AND NOT `status`='15' ORDER BY `name` ASC");
						foreach($res as $row){
							if(!in_array($row["position"],["assistant","USSD"])){
								$pay = json_decode($row['config'],1)['payroll'];
								if($pay['include']){
									$qrys[] = "(NULL,'".$row['id']."','$cat','$amnt','$mon','$yr','$tym','$tym')";
								}
							}
						}
					}
					
					if($db->execute(3,"INSERT INTO `deductions$cid` VALUES ".implode(",",$qrys))){
						$sname = ($stid) ? "staff $sname":"all staffs";
						if($dtp=="advance"){
							$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `time`='$tym' AND `staff`='$stid'");
							$desc = ucfirst($cat)." deduction for $sname for month of ".date("F Y",$mon); 
							$tid = $res[0]['id']; $ref = strtoupper(date("M",$mon)).$tid; 
							doublepost([$bran,$cred,$amnt,$desc,$ref,$sid],[$bran,$deb,$amnt,$desc,$ref,$sid],'',$tym,"auto"); 
							if($mon<$tmon){ bookbal($deb,"+$amnt:$mon",$bran); bookbal($cred,"-$amnt:$mon",$bran); }
						}
						
						savelog($sid,"Created $cat deductions of KES $amnt for $sname in month of ".date("F Y",$mon));
						echo "success:$mon";
					}
					else{ echo "Failed to complete the request! Try again later"; }
				}
			}
		}
	}
	
	# delete deduction
	if(isset($_POST['deldeduct'])){
		$did = trim($_POST['deldeduct']);
		$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `id`='$did'");
		$amnt = $res[0]['amount']; $mon=$res[0]['month']; $stid=$res[0]['staff']; $cat=prepare($res[0]['category']);
		$rule=getrule("advances"); $ref=strtoupper(date("M",$mon)).$did; $tmon=strtotime(date("Y-M")); $usa=staffInfo($stid);
		
		if($db->execute(3,"DELETE FROM `deductions$cid` WHERE `id`='$did'")){
			if($cat=="Salary advance"){
				$db->execute(3,"DELETE FROM `transactions$cid` WHERE `refno`='$ref'");
				if($mon<$tmon){ bookbal($rule['debit'],"-$amnt:$mon",$usa['branch']); bookbal($rule['credit'],"+$amnt:$mon",$usa['branch']); }
				bookbal($rule['debit'],"-$amnt",$usa['branch']); bookbal($rule['credit'],"+$amnt",$usa['branch']); 
			}
			
			$sname = ucwords($usa['name']);
			savelog($sid,"Deleted $cat deductions of KES $amnt for $sname in month of ".date("F Y",$mon));
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# save asset
	if(isset($_POST['itemid'])){
		$tid = trim($_POST['itemid']);
		$bran = trim($_POST['tbran']);
		$dept = clean($_POST['dept']);
		$cat = clean($_POST['categ']);
		$subcat = (isset($_POST['subcat'])) ? clean($_POST['subcat']):0;
		$item = clean($_POST['itname']);
		$desc = clean($_POST['itdes']);
		$cost = clean($_POST['itcost']);
		$depr = clean($_POST['depr']);
		$recur = trim($_POST['recur']);
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$cat'"); 
		$categ = $res[0]['account']; $ref=($subcat) ? $subcat:$cat;
		
		if($tid){
			$qri = $db->query(3,"SELECT *FROM `assets$cid` WHERE `id`='$tid'"); $pbal = $qri[0]['cost']; $pref=$qri[0]['ref'];
			if($db->execute(3,"UPDATE `assets$cid` SET `branch`='$bran',`item`='$item',`category`='$categ',`details`='$desc',`office`='$dept',`cost`='$cost', 
			`ref`='$ref',`depreciation`='$depr',`cycle`='$recur' WHERE `id`='$tid'")){
				if($pref!=$ref){
					bookbal($pref,"-$pbal",$bran); bookbal($ref,"+$pbal",$bran); 
				}
				else{
					if($pbal!=$cost){
						$upd = ($pbal>$cost) ? $cost-$pbal:"+".($cost-$pbal); bookbal($ref,$upd,$bran); 
					}
				}
				
				savelog($sid,"Updated company asset item $item to $categ");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{
			if($db->execute(3,"INSERT INTO `assets$cid` VALUES(NULL,'$bran','$item','$categ','$desc','$dept','$cost','$ref','$depr','$recur')")){
				bookbal($ref,"+$cost",$bran); savelog($sid,"Added company asset item $item to $categ");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# delete paybill transfer
	if(isset($_POST['delpayb'])){
		$pid = trim($_POST['delpayb']);
		
		if($db->execute(2,"DELETE FROM `paybill_transfers$cid` WHERE `id`='$pid'")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# delete asset
	if(isset($_POST['delasset'])){
		$tid = trim($_POST['delasset']);
		$qri = $db->query(3,"SELECT *FROM `assets$cid` WHERE `id`='$tid'");
		$row = $qri[0]; $cost=$row['cost']; $ref=$row['ref']; $name=$row['item']; $cat=$row['category'];
		
		if($db->execute(3,"DELETE FROM `assets$cid` WHERE `id`='$tid'")){
			bookbal($ref,"-$cost",$row['branch']); savelog($sid,"Deleted asset $name from $cat");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# post transaction
	if(isset($_POST['transdes'])){
		$bran = trim($_POST['tbran']);
		$day = strtotime(trim($_POST['tday'])."T".date('H:i:s'));
		$mon = strtotime(date("Y-M",$day));
		$tmon = strtotime(date("Y-M"));
		$tym = $day; $wk=date("W",$tym); 
		$ref = trim($_POST['refno']);
		$des = clean($_POST['transdes']);
		$com = clean($_POST['tcomm']);
		$src = clean($_POST['tsrc']);
		$yr = date("Y",$tym); 
		$loans=$cdes=$dids=$cids=$bks=[];
		
		$creds = $_POST['creds']; $camnt = $_POST['camnt']; 
		$debs = $_POST['debs']; $damnt = $_POST['damnt']; 
		$clients = (isset($_POST['clients'])) ? $_POST['clients']:[];
		$lamnts = (isset($_POST['lamnts'])) ? $_POST['lamnts']:[];
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$accs[$row['id']]=prepare(ucfirst($row['account'])); $types[$row['id']]=$row['type'];
		}
		
		$av = array_unique(array_intersect($creds,$debs));
		foreach($debs as $key=>$deb){ $debits[$key]=$damnt[$key]; $dids[$key]=$deb; }
		foreach($creds as $key=>$cred){ $credits[$key]=$camnt[$key]; $cids[$key]=$cred; }
		foreach($clients as $key=>$idno){ $loans[$idno]=$lamnts[$key]; }
		
		foreach($loans as $idno=>$pay){
			$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0");
			if($res){
				$bal = $res[0]['penalty']+$res[0]['balance']; $cdes[$idno]=$res[0];
				if($pay>$bal){
					echo "Failed: Surplus allocation of funds for client $idno, has a balance of KES ".number_format($bal); exit();
				}
			}
			else{ echo "Failed: Client with Id number $idno has no running Loan!"; exit(); }
		}
		
		if(empty($av)){
			if(in_array(0,$dids)){ echo "Error! You havent selected one of the debits account"; }
			elseif(in_array(0,$cids) && count($loans)<1){ echo "Error! You havent selected one of the credits account"; }
			elseif(array_sum($debits)!=(array_sum($credits)+array_sum($loans))){ echo "Failed: Debit amounts is not equal to credit amounts!"; }
			else{
				$sno = getransid(); $dy=strtotime(date("Y-M-d",$tym)); $now=time(); $revl=[]; $rev="default";
				foreach($debits as $key=>$sum){
					$acc=$dids[$key]; $bk = $types[$acc]; $upd=(in_array($bk,array("asset","expense"))) ? "+$sum":"-$sum"; $books[$acc]=$upd; $bks[]=$bk;
					$qrys[] = "(NULL,'$sno','$bran','$bk','$acc','$sum','debit','$des','$ref','$com','$sid','$rev','$mon','$dy','$now','$yr')";
				}
				foreach($credits as $key=>$sum){
					if($cids[$key]>0){
						$acc=$cids[$key]; $bk = $types[$acc]; $upd=(in_array($bk,array("asset","expense"))) ? "-$sum":"+$sum"; $books[$acc]=$upd;
						$qrys[] = "(NULL,'$sno','$bran','$bk','$acc','$sum','credit','$des','$ref','$com','$sid','$rev','$mon','$dy','$now','$yr')";
					}
				}
				
				if(isset($books[14])){
					$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$tmon'"); $rid=($res) ? $res[0]['id']:0; 
					if(!$rid){
						$db->execute(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$tmon','0','0')");
						$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$tmon'"); $rid = $qry[0]['id'];
					}
					$revl[] = array("db"=>3,"tbl"=>"cashfloats$cid","col"=>"branch","val"=>$bran,"update"=>"closingbal:".$books[14]);
				}
				
				if($src){
					if(explode(":",$src)[0]=="b2c"){ $revl[]=array("db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>explode(":",$src)[1],"update"=>"status:0"); }
					else{ $revl[]=array("db"=>2,"tbl"=>"org$cid"."_payments","col"=>"code","val"=>explode(":",$src)[1],"update"=>"status:0"); }
				}
				
				if($db->execute(3,"INSERT INTO `transactions$cid` VALUES ".implode(",",$qrys))){
					if(count($loans)){
						foreach($loans as $idno=>$amnt){
							$row=$cdes[$idno]; $name=strtoupper($row['client']); $fon=$row['phone'];
							$dtm=date("YmdHis",$tym); $ptbl="org".$cid."_payments"; $lid=$row['loan'];
							
							$db->execute(2,"INSERT INTO `writtenoff_loans$cid` VALUES(NULL,'$idno','$lid','$amnt','$sid','$now')");
							$qry = $db->query(2,"SELECT *FROM `writtenoff_loans$cid` WHERE `time`='$now' AND `client`='$idno'");
							$wid = $qry[0]['id']; $code="WRITEOFF$wid"; $payb=getpaybill($row['branch']);
							$revl[] = array("db"=>2,"tbl"=>"org".$cid."_loans","col"=>$code,"val"=>$idno,"update"=>"revpay:$code");
							$revl[] = array("db"=>2,"tbl"=>"writtenoff_loans$cid","col"=>"id","val"=>$wid,"update"=>"delete:$wid");
							
							$db->execute(2,"INSERT INTO `$ptbl` VALUES(NULL,'$mon','$code','$dtm','$amnt','$payb','$idno','0','$fon','$name','0')");
							$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `date`='$dtm' AND `account`='$idno'");
							makepay($idno,$res[0]['id'],$amnt);
						}
					}
					
					if(count($revl)){ $db->execute(3,"UPDATE `transactions$cid` SET `reversal`='".json_encode($revl,1)."' WHERE `transid`='$sno'"); }
					foreach($books as $acc=>$upd){
						$save = ($mon<$tmon) ? "$upd:$mon":$upd; bookbal($acc,$save,$bran); 
						if($acc==14){ cashbal($rid,$upd); }
					}
					
					if($src){
						$def = explode(":",$src);
						if($def[0]=="b2c"){
							if($db->execute(2,"UPDATE `tickets$cid` SET `reply`='$now' WHERE `id`='".$def[2]."'")){
								$db->execute(2,"UPDATE `payouts$cid` SET `status`='$now' WHERE `code`='".$def[1]."'");
							}
						}
						else{ $db->execute(2,"UPDATE `org$cid"."_payments` SET `status`='$now' WHERE `code`='".$def[1]."'"); }
					}
					
					if(in_array("expense",$bks)){ setSurplus($mon); }
					savelog($sid,"Posted journal entry with transaction $sno of KES ".number_format(array_sum($debits)));
					echo "success:$sno";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
		else{
			foreach($av as $one){ $get[]=$accs[$one]; }
			$txt = (count($av)==1) ? "Account ".implode(",",$get)." is":"Accounts ".implode(",",$get)." are";
			echo "Failed: $txt available in both debit & credit accounts!";
		}
	}
	
	# reverse transactions
	if(isset($_POST['revtrans'])){
		$tid = trim($_POST['revtrans']);
		$res = $db->query(3,"SELECT `reversal` FROM `transactions$cid` WHERE `transid`='$tid' LIMIT 1");
		$rev = $res[0]['reversal']; $des=(in_array($rev,["default","auto"])) ? "default":json_decode($rev,1);
		
		if(reversetrans($tid,$des,$sid)){
			savelog($sid,"Reversed posted transaction $tid");
			echo "success";
		}
		else{ echo "Failed to create account! Try again later"; }
	}
	
	# create/edit account book
	if(isset($_POST['accname'])){
		$book = clean($_POST['accname']);
		$type = trim($_POST['actp']);
		$sub = trim($_POST['subacc']); 
		
		$wing=$level=0;
		if($sub){
			$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$sub'");
			$wing=($res[0]['wing']) ? $res[0]['wing'].",$sub":$sub; $level=$res[0]['level']+1;
		}
		
		if(isset($_POST['edit'])){
			$acc = trim($_POST['edit']);
			$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acc'"); 
			$name=prepare($res[0]['account']); $upd=@array_pop(explode(",",$res[0]['wing']));
			
			if($db->execute(3,"UPDATE `accounts$cid` SET `account`='$book',`wing`='$wing',`level`='$level' WHERE `id`='$acc'")){
				if($upd){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree-1) WHERE `id`='$upd'"); }
				if($sub){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree+1) WHERE `id`='$sub'"); }
				savelog($sid,"Updated book of account $name");
				echo "success";
			}
			else{ echo "Failed to update account! Try again later"; }
		}
		else{
			$bal = clean($_POST['cbal']);
			if($db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'$book','$type','$wing','$level','0','$bal')")){
				if($sub){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree+1) WHERE `id`='$sub'"); }
				savelog($sid,"Created book of account $book");
				echo "success";
			}
			else{ echo "Failed to create account! Try again later"; }
		}
	}
	
	# post payment to journal
	if(isset($_POST['jpid'])){
		$pid = trim($_POST['jpid']);
		$acc = trim($_POST['pacc']);
		$des = clean($_POST['pdesc']);
		
		$chk = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
		if(!$chk){ echo "Failed: Payment Not available!"; }
		elseif($chk[0]['status']!=0){ echo "Failed: Payment is already approved!"; }
		else{
			$tid = getransid(); $amnt=$chk[0]['amount']; $acid=explode(":",$acc)[0]; $bk=explode(":",$acc)[1]; $dy=$chk[0]['date'];
			$ptm = strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
			$code = $chk[0]['code']; $mon=strtotime(date("Y-M",$ptm)); $day=strtotime(date("Y-M-d",$ptm)); $tym=time(); $yr=date("Y",$ptm);
			$btp = ($bk=="asset") ? "debit":"credit";
			
			if($db->execute(2,"UPDATE `org$cid"."_payments` SET `status`='$tid' WHERE `id`='$pid'")){
				$revl = json_encode(array(["db"=>2,"tbl"=>"org$cid"."_payments","col"=>"id","val"=>$pid,"update"=>"status:0"]),1); bookbal($acid,"+$amnt:$mon");
				$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$tid','0','$bk','$acid','$amnt','$btp','$des','$code','','$sid','$revl','$mon','$day','$tym','$yr')");
				savelog($sid,"Created a journal transaction for payment $code");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# include payment to accounts
	if(isset($_POST['payacc'])){
		$book = clean($_POST['payacc']);
		$type = trim($_POST['actp']);
		$sub = trim($_POST['subacc']); 
		$mon = trim($_POST['pfrom']);
		$pay = trim($_POST['paydes']);
		
		$wing=$level=0;
		if($sub){
			$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$sub'");
			$wing=($res[0]['wing']) ? $res[0]['wing'].",$sub":$sub; $level=$res[0]['level']+1;
		}
		
		$res = $db->query(2,"SELECT SUM($pay) AS total FROM `paysummary$cid` WHERE `month`>=$mon");
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='recordpays' AND `client`='$cid'");
		$bal = ($res) ? $res[0]['total']:0; $pays = ($qry) ? json_decode($qry[0]['value'],1):[];
		
		if($db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'$book','$type','$wing','$level','0','$bal')")){
			if($sub){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree+1) WHERE `id`='$sub'"); }
			$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='$book' AND `balance`='$bal' ORDER BY `id` DESC LIMIT 1");
			$acid = $qri[0]['id']; $pays[$acid]=$pay; $save=json_encode($pays,1);
			
			$query = ($qry) ? "UPDATE `settings` SET `value`='$save' WHERE `setting`='recordpays' AND `client`='$cid'":
			"INSERT INTO `settings` VALUES(NULL,'$cid','recordpays','$save')";
			$db->execute(1,$query);
			
			savelog($sid,"Included ".ucwords(str_replace("_"," ",$pay))." payments to account books as $book");
			echo "success";
		}
		else{ echo "Failed to create account! Try again later"; }
	}
	
	# remove pay from accounts
	if(isset($_POST['delpay'])){
		$pay = trim($_POST['delpay']);
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='recordpays' AND `client`='$cid'");
		$pays = ($qry) ? json_decode($qry[0]['value'],1):[]; $acid=array_flip($pays)[$pay]; unset($pays[$acid]);
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acid'");
		$book = $res[0]['type']; $upd=@array_pop(explode(",",$res[0]['wing']));
		$type = (in_array($book,array("asset","expense"))) ? "credit":"debit";
		
		if($db->execute(1,"UPDATE `settings` SET `value`='".json_encode($pays,1)."' WHERE `setting`='recordpays' AND `client`='$cid'")){
			$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `account`='$acid' AND `type`='$type'");
			if(!$chk){
				if($upd){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree-1) WHERE `id`='$upd'"); }
				$db->execute(3,"DELETE FROM `accounts$cid` WHERE `id`='$acid'");
				$db->execute(3,"DELETE FROM `transactions$cid` WHERE `account`='$acid'");
				$db->execute(3,"DELETE FROM `monthly_balances$cid` WHERE `account`='$acid'");
			}
			
			savelog($sid,"Removed ".ucwords(str_replace("_"," ",$pay))." payments from account books");
			echo "success";
		}
	}
	
	# update accounting rules
	if(isset($_POST['rdb'])){
		$deb = trim($_POST['rdb']);
		$cred = trim($_POST['rcd']);
		$rule = trim($_POST['rule']);
		$type = trim($_POST['rname']);
		
		if($deb==$cred){ echo "Failed: Both accounts are similar"; }
		else{
			if($db->execute(3,"UPDATE `accounting_rules$cid` SET `credit`='$cred',`debit`='$deb' WHERE `id`='$rule'")){
				savelog($sid,"Changed accounting rule for $type");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# delete account
	if(isset($_POST['delacc'])){
		$acc = trim($_POST['delacc']);
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acc'"); 
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		$name = prepare($res[0]['account']); $upd=@array_pop(explode(",",$res[0]['wing'])); 
		$sett = ($qri) ? json_decode($qri[0]['value'],1):[];
		
		if(in_array($acc,$sett)){ echo "Failed: Account is linked to system wallet accounts!"; }
		else{
			if($db->execute(3,"DELETE FROM `accounts$cid` WHERE `id`='$acc'")){
				if($upd){ $db->execute(3,"UPDATE `accounts$cid` SET `tree`=(tree-1) WHERE `id`='$upd'"); }
				$db->execute(3,"DELETE FROM `transactions$cid` WHERE `account`='$acc'");
				$db->execute(3,"DELETE FROM `monthly_balances$cid` WHERE `account`='$acc'");
				savelog($sid,"Deleted $name from book of accounts");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# fetch clients with loans
	if(isset($_POST['getclients'])){
		$me = staffInfo($sid); $data=$lis="";
		$access = $me['access_level'];
		$cond = ($access=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond.= ($access=="portfolio") ? "AND `loan_officer`='$sid'":"";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE (balance+penalty)>0 $cond ORDER BY `client` ASC");
		if($res){
			foreach($res as $row){
				$name=ucwords(prepare($row['client'])); $idno=$row['client_idno'];
				$lis.="<option value='$idno'>$name</option>";
			}
			
			$data = "<datalist id='clients'>$lis</datalist>
			<input type='text' name='clients[]' list='clients' style='width:100%' placeholder='Client Idno' autocomplete='off' required>";
		}
		
		echo ($data) ? "data~$data":"No Loan record found!";
	}

	ob_end_flush();
?>