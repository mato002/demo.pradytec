<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST['syspost'])){ exit(); }
	$sid = (isset($_POST['syspost'])) ? 0:substr(hexdec($_SESSION['myacc']),6);
	if($sid<1 && !isset($_POST['syspost'])){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	insertSqlite("photos","CREATE TABLE IF NOT EXISTS images (image TEXT UNIQUE NOT NULL,data BLOB)");
	
	# add loan charges
	if(isset($_POST["lncharge"])){
		$lid = trim($_POST["lncharge"]);
		$name = prepstr($_POST["chargenm"]);
		$amnt = intval($_POST["chajamt"]);
		$inst = intval($_POST["instp"]);
		$data=$sums=[];
		
		$sql = $db->query(2,"SELECT *FROM `staff_schedule$cid` WHERE `loan`='$lid' AND `balance`>0");
		foreach($sql as $row){
			$terms=json_decode($row['breakdown'],1); $id=$row['id'];
			foreach($terms as $py=>$am){ $pays[$py]=$py; }
			if($inst==$id or $inst==0){ $terms[$name]=$amnt; $data[$id]=json_encode($terms,1); $sums[$id]=$amnt; }
		}
		
		if(in_array($name,$pays)){ echo "Failed: Payment description is already set"; }
		elseif($amnt<1){ echo "Failed: Invalid amount"; }
		else{
			$tot = array_sum($sums); $txt=($inst) ? "All loan installments":"One Loan installment";
			if($db->execute(2,"UPDATE `staff_loans$cid` SET `balance`=(balance+$tot) WHERE `loan`='$lid'")){
				foreach($data as $id=>$jsn){
					$db->execute(2,"UPDATE `staff_schedule$cid` SET `balance`=(balance+$sums[$id]),`breakdown`='$jsn' WHERE `id`='$id'");
				}
				
				$pay = ucwords(str_replace("_"," ",$name)); $tbal=array_sum(getLoanBals($lid,0,"staff"));
				logtrans($lid,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$tot,"bal"=>$tbal),1),0);
				savelog($sid,"Added Loan charge $pay of Ksh $amnt for Client ".$sql[0]['idno']." for $txt");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# approve loans
	if(isset($_POST['approveloans'])){
		$otp = trim($_POST['otpv']);
		$val = $_POST['nval'];
		$rids = $_POST['ploans']; 
		$from = $val-1; $no=0; $nst=$val+1; 
		
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; $myname=prepare(ucwords($me['name']));
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$com = (isset($_POST['appcom'])) ? clean($_POST['appcom']):"Ok"; $tbl="staff_loans$cid"; 
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $dcd=count($levels); 
				$approvals=$comms=$allst=[]; $total=0; $tym=time();
				
				$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE `status`<10 AND `loan`='0'");
				if($sql){
					foreach($sql as $row){
						$allst[$row['id']]=$row['status'];
						if($row['status']==$from){
							$arr=($row['approvals']==null) ? []:json_decode($row['approvals'],1); 
							$approvals[$row['id']]=$arr; $sums[$row['id']]=$row['amount'];
						}
					}
				}
				
				foreach($rids as $rid){
					if(isset($allst[$rid])){
						if($allst[$rid]<8 && $allst[$rid]<$val){
							$new = (isset($approvals[$rid])) ? $approvals[$rid]:[]; $total+=$sums[$rid]; $new[$sid]=$com; $app=json_encode($new,1); $no++;
							$db->execute(2,"UPDATE `$tbl` SET `status`='$val',`approvals`='$app',`pref`='$tym' WHERE `id`='$rid'"); 
						}
					}
				}
				
				if($no){
					$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='loan_approval_sms'");
					$cond = ($val==$dcd) ? 1:0; $sum=fnum($total); $appset=($sql) ? $sql[0]['value']:1; $now=time();
					$db->execute(1,"UPDATE `otps` SET `expiry`='$now' WHERE `phone`='$fon'");
					if($cond){
						$db->execute(2,"UPDATE `$tbl` SET `status`='8',`pref`='$tym' WHERE `status`='$val' AND `loan`='0'");
						if($appset){
							$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='stafftemplate'");
							$notify = ($qry) ? $qry[0]['staff']:1;
							if($notify){
								$nto = staffInfo($notify); $uto=prepare(ucwords($nto['name'])); $all=($no==1) ? "1 Staff":"$no Staffs";
								$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact'];
								sendSMS($mto,"Hi $uto, Staff Loan of KES $sum for $all has been approved by $myname now waiting for disbursement");
							}
						}
					}
					else{
						if(isset($levels[$nst]) && $no && $appset){
							$pos = $levels[$nst]; $cont=0;
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND NOT `position`='USSD' AND `status`='0'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$pos])){
										if($post[$pos]=="hq"){
											$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; $name=ucwords(prepare($row['name'])); break; 
										}
										elseif($post[$pos]=="region" && isset($cnf["region"])){
											$chk = $db->query(1,"SELECT *FROM `regions` WHERE `id`='".$cnf["region"]."'");
											$brans = ($chk) ? json_decode($chk[0]["branches"],1):[];
											if(in_array($me['branch'],$brans)){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
											}
										}
										else{
											if($row['branch']==$me['branch']){
												$cont = (isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; $name=ucwords(prepare($row['name'])); break; 
											}
										}
									}
								}
								if($cont){
									$sum = ($no==1) ? "1 Employee":"$no Employees";
									sendSMS($cont,"Hi $name, $myname has approved a staff loan for $sum now waiting your approval");
								}
							}
						}
					}
					
					savelog($sid,"Approved staff Loan application for $no staffs");
					echo "success";
				}
				else{ echo "Failed: No staff selected!"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# pend loans
	if(isset($_POST['pendloans'])){
		$data = $_POST['ploans'];
		$otp = trim($_POST['otpv']);
		$com = (isset($_POST['appcom'])) ? clean($_POST['appcom']):"Ok";
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$tbl = "staff_loans$cid";
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				foreach($data as $rid){
					$svd = $db->query(2,"SELECT `approvals` FROM `$tbl` WHERE `id`='$rid'")[0]['approvals'];
					$pcom = ($svd==null) ? []:json_decode($svd,1); $pcom[$sid]=$com; $jsn=json_encode($pcom,1);
					$db->execute(2,"UPDATE `$tbl` SET `pref`='8',`approvals`='$jsn' WHERE `id`='$rid'");
				}
				
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
				savelog($sid,"Pended ".count($data)." Loans from template");
				echo "success";
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# decline loans
	if(isset($_POST['decline'])){
		$data = $_POST['ploans'];
		$otp = trim($_POST['otpv']);
		$com = (isset($_POST['appcom'])) ? clean($_POST['appcom']):"Ok";
		$me = staffInfo($sid); $tbl="staff_loans$cid";
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				foreach($data as $rid){
					$jsn = json_encode([$sid=>$com],1);
					$db->execute(2,"UPDATE `$tbl` SET `status`='9',`approvals`='$jsn',`pref`='0' WHERE `id`='$rid'");
				}
				
				$db->execute(1,"UPDATE `otps` SET `expiry`='".time()."' WHERE `phone`='$fon'");
				savelog($sid,"Declined ".count($data)." Loans from template");
				echo "success";
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# confirm disbursed
	if(isset($_POST['disbfrom'])){
		$acc = trim($_POST["disbfrom"]);
		$desc = (isset($_POST["desc"])) ? $_POST['desc']:[];
		$fees = (isset($_POST["fees"])) ? $_POST['fees']:[];
		$tref = (isset($_POST["trans"])) ? $_POST['trans']:[];
		$days = $_POST["days"]; $damnt=$_POST["damnt"]; $tot=$no=0; $fdes=[];
	
		if(!$acc){ echo "Failed: No credit account selected!"; }
		else{
			$qri = $db->query(3,"SELECT `account` FROM `accounts$cid` WHERE `id`='$acc'");
			$acname = ucwords($qri[0]['account']); $book=$acname;
			foreach($_POST['dids'] as $rid){
				$day = (isset($days[$rid])) ? strtotime(str_replace("T",",",$days[$rid])):0;
				$ref = (isset($tref[$rid])) ? $tref[$rid]:""; $ds=(isset($desc[$rid])) ? $desc[$rid]:"";
				if($day){
					$sql = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `id`='$rid'"); $no++;
					$row = $sql[0]; $cut=0; $apam=$row['amount']; $bid=$row['branch']; $dsb=[]; $amnt=$damnt[$rid]; 
					$cut+= (isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; $tdsb=array_sum($dsb)+$amnt;
					
					if($row["status"]==8 && $tdsb>$cut && $tdsb<=$apam){
						$tds=($dsb) ? $amnt:$amnt+$cut; $lntp=$row["loantype"]; 
						$cname=ucwords($row['client']); $des="Loan for $cname"; $des.=($ref) ? " Ref #$ref":""; $tot+=$amnt; 
						$db->execute(2,"UPDATE `staff_loans$cid` SET `status`='$day' WHERE `id`='$rid'");
						$rev = json_encode(array(["db"=>2,"tbl"=>"staff_loans$cid","col"=>"id","val"=>$rid,"update"=>"status:8"]),1);
						doublepost([$bid,$acc,$amnt,$des,"SLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"SLOAN$rid",$sid],clean($ds),$day,$rev);
						$book.= ($ref) ? " Ref #$ref":""; $book.=($ds) ? " -".clean($ds):""; $fdes[$rid]=[$cname,$bid,$acc,$day];
						logtrans("TIDS$rid","Disbursement to $cname via $book",$sid);
					}
				}
			}
			
			if($no){
				foreach($fees as $id=>$sum){
					if(intval($sum) && isset($fdes[$id])){
						$desc = "Charges from Disbursement to ".$fdes[$id][0]; $acc=$fdes[$id][2]; $fee=intval($sum); $tym=$fdes[$id][3];
						doublepost([$fdes[$id][1],$acc,$fee,$desc,$tym,$sid],[$bid,DEF_ACCS['b2c_charges'],$fee,$desc,$tym,$sid],'',$tym,"default");
					}
				}
				
				if($tot>0){ savelog($sid,"Confirmed staff loan disbursement of $no worth Ksh ".fnum($tot)." from $acname"); }
				echo "success";
			}
			else{ echo "Failed: You havent selected any disbursement dates!"; }
		}
	}
	
	# update penalty
	if(isset($_POST['cpenalty'])){
		$amnt = intval($_POST['cpenalty']);
		$lid = trim($_POST['clid']);
		
		if($amnt>=0){
			$chk = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `loan`='$lid'");
			if($chk){
				if($db->execute(2,"UPDATE `staff_loans$cid` SET `penalty`='$amnt' WHERE `loan`='$lid'")){
					if($sid){
						if($db->istable(2,"penalties$cid")){
							$db->execute(2,"DELETE FROM `penalties$cid` WHERE `loan`='$lid'");
							if($amnt>0){ $db->execute(2,"INSERT INTO `penalties$cid` VALUES(NULL,'$lid','$amnt','0','".json_encode(["all"],1)."')"); }
						}
					}
					
					$tbal=$chk[0]['balance']+$chk[0]['penalty']; $pen=$amnt-$chk[0]['penalty'];
					if($pen>0){ logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$pen,"bal"=>$tbal+$pen),1),$sid); }
					else{
						$val = str_replace("-","",$pen); 
						logtrans($lid,json_encode(array("desc"=>"Penalties charges waived","type"=>"credit","amount"=>$val,"bal"=>$tbal-$val),1),$sid); 
					}
					
					savelog($sid,"Updated penalty amount for staff ".$chk[0]["staff"]." to $amnt");
					echo "success:".number_format($amnt);
				}
				else{ echo "Failed: Try again later"; }
			}
			else{ echo "Failed: Loan details not found!"; }
		}
		else{ echo "Failed: Invalid penalty amount"; }
	}
	
	# post loan
	if(isset($_POST['postid'])){
		$tid = trim($_POST['postid']);
		$pdy = (isset($_POST['pday'])) ? strtotime(trim($_POST['pday'])):0;
		$ltbl = "staff_loans$cid";
		$loan = getLoanId("S");
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$tid'"); $row = $res[0]; $lid=$row['loan']; $lst=$row['status']; $prod=$row['loan_product'];
		$day = ($pdy) ? $pdy:strtotime(date("Y-M-d",$lst)); $tym = ($pdy) ? strtotime(trim($_POST['pday']).",".date("H:i")):$lst; $phone=$row['phone'];
		$expd =$day+($row['duration']*86400); $lamnt=$row['amount']; $pid=$row['loan_product']; $uid=$row['stid']; $bran=$row['branch'];
		$pcode=$row['payment']; $ldur=$row['duration']; $deducted=json_decode($row['processing'],1); 
		$desc = "Loan for ".ucwords($row['staff']); $name=$row['staff']; $deds=(isset($row['cuts'])) ? json_decode($row['cuts'],1):[];
		
		if($lid){ echo "success"; exit(); }
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$pid'");
		$paydes = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$pcode'");
		$pst = ($paydes) ? $paydes[0]['status']:0;
		
		if(!$lprod){ echo "Failed: Attached loan product is not found!"; exit(); }
		if(!$paydes && $pcode){ echo "Failed: Payment code $pcode is not found in payments"; exit(); }
		if($pst>10 && $pcode){ echo "Failed: Payment $pcode has already been approved"; exit(); }
		
		$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals']; $pays=$payat=$vsd=[];
		foreach($payterms as $des=>$pay){
			$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
			if($val[0]==2){ $pays[$des]=$amnt; }
			if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
		}
		
		if(substr($lprod[0]['interest'],0,4)=="pvar"){
			$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
			$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
			$intrs = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
			foreach($vars as $key=>$perc){
				$dy = $day+($key*86400); $cyc=$ldur/$intv; 
				$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
			}
		}
		else{ $intrs = (count(explode("%",$lprod[0]['interest']))>1) ? round($lamnt*explode("%",$lprod[0]['interest'])[0]/100):$lprod[0]['interest']; }
		
		$pamnt = ($paydes) ? $paydes[0]['amount']:0; $payid=($paydes) ? $paydes[0]['id']:0;
		$totpay = $lamnt+($ldur/$lprod[0]['interest_duration']*$intrs); $inst=ceil($totpay/($ldur/$intv));
		$intr=round(($totpay-$lamnt)/($ldur/$intv)); $princ=$inst-$intr; $deduct=array_sum($deducted); $rem=$pamnt-$deduct;
		$dsd=strtotime(date("Y-M-d",$day).",".date("H:i",$lst)); $tloan=$totpay;
		
		# schedule
		$vint = (count($vsd)) ? "varying":$intr; $tint=$pmo=0; $slis=[]; $pinst=ceil($lamnt/($ldur/$intv)); $perin=$ldur/$intv;
		$arr = array('principal'=>$princ,"interest"=>$vint)+$pays; $tprc=$lamnt; $tintr=$totpay-$lamnt; $other=array_sum($pays);
		for($i=1; $i<=$perin; $i++){
			$dy=$day+($i*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $totpay+=$other;
			$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $pmo+=$cut+$other; $totpay+=$cut;
			$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); 
			if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; $inst=array_sum($brek); $cut=0; }
			$sinst = ($inst>$totpay) ? $totpay:$inst; $sinst+=$cut; $totpay-=$sinst; $tint+=$intr; $bjs=json_encode($brek,1);
			$slis[] = "(NULL,'$uid','$loan','$mon','$dy','$sinst','$intr','0','$sinst','$bjs','[]')";
		}
		
		$ins=$cols=""; $trem=0; $qrys=implode(",",$slis); $tloan+=$pmo;
		$res = $db->query(2,"SELECT loan,SUM(penalty) AS tpen,SUM(balance+penalty) AS tbal FROM `$ltbl` WHERE `stid`='$uid'");
		$tbal = ($res) ? $res[0]['tbal']:0; $tpen=($res) ? $res[0]['tpen']:0; $lid=($res) ? $res[0]['loan']:0;
		$ctbl = "org".$cid."_staff"; $ptbl = "org".$cid."_payments";
		
		if($pst==0){
			if($rem>0 && $payid){
				if($tbal){
					if($tpen){
						$db->execute(2,"UPDATE `$ltbl` SET `penalty`='0' WHERE `stid`='$uid'");
						if(($tbal-$tpen)>0){ makepay($uid,$payid,array(array(["penalties"=>$tpen]),$tbal-$tpen),"staff"); }
						else{ savepays($payid,$lid,array(["penalties"=>$tpen]),"staff"); saveoverpayment($uid,$payid,$pamnt-$tpen,"staff"); }
					}
					else{ makepay($uid,$payid,$tbal,"staff"); }
				}
				else{ 
					saveoverpayment($uid,$payid,$rem,"staff"); 
					$db->execute(2,"UPDATE `org".$cid."_payments` SET `status`='$loan' WHERE `id`='$payid'");
				}
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE `id`='$uid'");
		$chk = $db->query(2,"SELECT *FROM `$ltbl` WHERE `stid`='$uid' AND (balance+penalty)>0");
		$name = $res[0]['name']; $idno=$res[0]['idno'];
		
		if($res[0]['status']==1){ echo "Failed: ".prepare(ucwords($name))." is suspended"; }
		elseif($chk){ echo "Failed: ".prepare(ucwords($name))." has a running loan"; }
		else{
			if($db->execute(2,"UPDATE `$ltbl` SET `loan`='$loan',`status`='0',`disbursement`='$dsd',`expiry`='$expd',`balance`='$tloan',`creator`='$sid' WHERE `id`='$tid'")){
				$db->execute(2,"INSERT INTO `staff_schedule$cid` VALUES $qrys");
				if(count($vsd)){
					if(!$db->istable(2,"varying_interest$cid")){ $db->createTbl(2,"varying_interest$cid",["loan"=>"INT","schedule"=>"TEXT","time"=>"INT"]); }
					$db->execute(2,"INSERT INTO `varying_interest$cid` VALUES(NULL,'$loan','".json_encode($vsd,1)."','$tym')");
				}
				
				if(count($deds)){
					if(!$db->istable(2,"org$cid"."_prepayments")){
						$tbl =array("client"=>"CHAR","idno"=>"INT","branch"=>"INT","officer"=>"INT","template"=>"INT","product"=>"INT","amount"=>"INT","status"=>"INT");
						$db->createTbl(2,"org$cid"."_prepayments",$tbl);
					}
			
					$sum = array_sum($deds); $payb=getpaybill($bran); $mon=strtotime(date("Y-M")); $day=date("YmdHis");
					$db->execute(2,"INSERT INTO `org$cid"."_prepayments` VALUES(NULL,'$name','$idno','$bran','$uid','$tid','$prod','$sum','$tid')");
					$rid = $db->query(2,"SELECT `id` FROM `org$cid"."_prepayments` WHERE `idno`='$idno' AND `status`='$tid'")[0]['id'];
					$db->execute(2,"INSERT INTO `org".$cid."_payments` VALUES(NULL,'$mon','PREPAY$rid','$day','$sum','$payb','$uid','0','$phone','$name','0')");
					$pid = $db->query(2,"SELECT id FROM `org".$cid."_payments` WHERE `date`='$day' AND `code`='PREPAY$rid'")[0]['id'];
					savepays("$pid:$tym",$loan,array($deds),"staff"); bookbal(DEF_ACCS['loan_charges'],"+$sum");
				}
				
				bookbal(DEF_ACCS['interest'],"+$tint");
				if($paydes){
					savepays("$payid:$tym",$loan,array($deducted),"staff"); bookbal(DEF_ACCS['loan_charges'],"+$deduct");
				} 
				
				$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='$tid'");
				if(!$chk){
					$rule = getrule("loans");
					doublepost([$bran,$rule['credit'],$lamnt,$desc,$tid,$sid],[$bran,$rule['debit'],$lamnt,$desc,$tid,$sid],'',time(),"auto");
				}
				
				savelog($sid,"Created a staf loan for $name of KES ".number_format($lamnt)." to be paid in $ldur days");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# waive interest
	if(isset($_POST['waiveintr'])){
		$lid = trim($_POST['waiveintr']);
		$intr = trim($_POST['nintr']);
		$paid=$revs=[]; $tsum=0;
		
		if(!is_numeric($intr)){ echo "Error! Invalid amount! Try again"; }
		elseif($intr<0){ echo "Error! Amount should be >=0"; }
		else{
			if($con = $db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN");
				$sql = $con->query("SELECT *FROM `staff_schedule$cid` WHERE `loan`='$lid' ORDER BY `day` ASC");
				while($row=$sql->fetch_assoc()){
					$brk = json_decode($row['breakdown'],1); $pays[$row['id']]=$brk; $uid=$row['stid']; 
					foreach(json_decode($row['payments'],1) as $pid=>$des){ $paid[]=explode(":",$pid)[0]; }
				}
				
				$qri = $con->query("SELECT *FROM `org$cid"."_payments` WHERE `status`='$lid'");
				if($qri->num_rows){
					while($row=$qri->fetch_assoc()){
						if(in_array($row['id'],$paid)){ reversepay("$uid:$lid",$row['code'],"staff"); $revs[$row['id']]=$row['amount']; }
					}
				}
				
				$con->query("SELECT *FROM `staff_schedule$cid` WHERE `loan`='$lid' ORDER BY `day` ASC FOR UPDATE");
				$nintr=$tint=intval($intr); $pint=ceil($nintr/count($pays));
				foreach($pays as $rid=>$one){
					$prc=$one['principal']; $intrs=($nintr>$pint) ? $pint:$nintr; $one['interest']=$intrs; 
					$inst=array_sum($one); $nbrk=json_encode($one,1); $tsum+=$inst; $nintr-=$pint;
					$qrys[]="UPDATE `staff_schedule$cid` SET `interest`='$intrs',`amount`='$inst',`paid`='0',`balance`='$inst',`breakdown`='$nbrk',`payments`='[]' WHERE `id`='$rid'";
				}
				
				$con->query("SELECT *FROM `staff_loans$cid` WHERE `loan`='$lid' FOR UPDATE");
				if($con->query("UPDATE `staff_loans$cid` SET `balance`='$tsum',`paid`='0',`status`='0' WHERE `loan`='$lid'")){
					if($db->istable(2,"varying_interest$cid")){ $con->query("DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'"); }
					foreach($qrys as $qry){ $con->query($qry); } $con->commit(); $con->close();
					foreach($revs as $pid=>$amnt){ makepay($uid,$pid,$amnt,"staff",$lid); }
					savelog($sid,"Waived loan interest for staff ".ucwords(staffInfo($uid)["name"])." to $tint");
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; $con->commit(); $con->close(); }
			}
			else{ echo "Failed due to system Error!"; }
		}
	}
	
	# save loan application
	if(isset($_POST['formkeys'])){
		$keys = json_decode(trim($_POST['formkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$tid = trim($_POST['id']);
		$pid = clean($_POST['loan_product']);
		$lamnt = clean($_POST['amount']);
		$ldur = clean($_POST['duration']);
		$ltp = (isset($_POST['ltp'])) ? clean($_POST['ltp']):0;
		$pcode = (isset($_POST['payments'])) ? trim($_POST['payments']):0;
		$ckoto = (isset($_POST['ckoto'])) ? trim($_POST['ckoto']):0;
		$ckoam = (isset($_POST['ckoamnt']) && $ckoto) ? intval(trim($_POST['ckoamnt'])):0;
		$ltbl = "staff_loans$cid"; $ptbl="org$cid"."_payments";
		
		$adds = ["pref"=>"number","payment"=>"text","processing"=>"textarea","cuts"=>"textarea"];
		foreach($adds as $key=>$add){ $keys[$key]=$add; }
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		foreach($res as $row){
			$setts[$row['setting']]=prepare($row['value']);
		}
		
		$client = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$sid'");
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$pid'");
		$ldisb = $db->query(2,"SELECT *FROM `$ltbl` WHERE `stid`='$sid' AND `loan`='0' AND `status`<9 AND NOT `id`='$tid'");
		$lpays = $db->query(2,"SELECT *FROM `$ltbl` WHERE `payment`='$pcode' AND NOT `id`='$tid' AND NOT `payment`='0' AND NOT `status`='9'");
		$loans = $db->query(2,"SELECT *FROM `$ltbl` WHERE `stid`='$sid' ORDER BY `time` DESC LIMIT 1");
		$paydes = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$pcode' AND NOT `code`='0'");
		
		$smnot = (isset($setts['template_notify_approver'])) ? $setts['template_notify_approver']:0; 
		$today = strtotime('Today'); $due=$sum=$tbal=0; $cst=$client[0]['status'];
		$pdef = ($lprod) ? json_decode($lprod[0]["pdef"],1):[];
		$allowdup = (isset($pdef["allow_multiple"])) ? $pdef["allow_multiple"]:0;
		
		if($loans){
			$lid=$loans[0]['loan']; $tbal=$loans[0]['balance']+$loans[0]['penalty'];
		}
		
		if(!$pid){ echo "Failed: No loan product selected"; }
		elseif(!$lprod){ echo "Failed: Loan product selected not found!"; }
		elseif($tbal>0 && !$allowdup){ echo "Failed: You have running Loan"; }
		elseif($cst==1){ echo "Failed: You are suspended"; }
		elseif($lamnt<$lprod[0]['minamount']){ echo "Minimum Loan amount for ".ucfirst(prepare($lprod[0]['product']))." is KES ".fnum($lprod[0]['minamount']); }
		elseif($lamnt>$lprod[0]['maxamount']){ echo "Maximum Loan amount for ".ucfirst(prepare($lprod[0]['product']))." is KES ".fnum($lprod[0]['maxamount']); }
		elseif($ldisb){ echo "Failed: You have a pending loan disbursement"; }
		elseif($pcode && $lpays){ echo "Failed: Payment transaction $pcode is already in use"; }
		else{
			$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `$ltbl` WHERE NOT `status`='9' AND `stid`='$sid' AND NOT `id`='$tid'");
			$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals']; $lis=""; $req=0; 
			$avs = ($chk) ? intval($chk[0]['tot']):0; $lntp=($avs) ? "repeat":"new"; $pays=$deds=[]; 
			
			if($loans){
				if(!$db->istable(2,"penalties$cid")){ $db->createTbl(2,"penalties$cid",["loan"=>"INT","totals"=>"INT","paid"=>"INT","installments"=>"TEXT"]); }
				$qry = $db->query(2,"SELECT SUM(totals-paid) AS rem FROM `penalties$cid` WHERE `loan`='".$loans[0]['loan']."'");
				$pbal = ($qry) ? intval($qry[0]['rem']):0; 
				if($pbal!=$loans[0]['penalty']){
					$db->execute(2,"UPDATE `$ltbl` SET `penalty`='$pbal' WHERE `loan`='".$loans[0]['loan']."'"); $loans[0]['penalty']=$pbal;
				}
			}
			
			foreach($payterms as $des=>$pay){
				$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
				if($val[0]==0 && $lntp=="new"){ $req+=$amnt; $pays[$des]=$amnt; $lis.=ucwords(str_replace("_"," ",$des))." = KES $amnt,"; }
				if($val[0]==1){ $req+=$amnt; $pays[$des]=$amnt; $lis.=ucwords(str_replace("_"," ",$des))." = KES $amnt,"; }
				if($val[0]==4){ $deds[$des]=$amnt; }
			}
			
			if(substr($lprod[0]['interest'],0,4)=="pvar"){
				$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
				$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
				$intr = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
			}
			else{ $intr = (count(explode("%",$lprod[0]['interest']))>1) ? round($lamnt*explode("%",$lprod[0]['interest'])[0]/100):$lprod[0]['interest']; }
			
			$pamnt = ($paydes) ? $paydes[0]['amount']:0; $tbal=($loans) ? $loans[0]['balance']+$loans[0]['penalty']:0; 
			$bal = ($req+$tbal)-$pamnt; $deduct=$cut=0; $totpay = $lamnt+($ldur/$lprod[0]['interest_duration']*$intr);
			
			if($req>$pamnt){ echo "Failed: Loan fulfillment (".rtrim($lis,",").") totals to KES $req, but KES $pamnt provided"; }
			elseif($bal>0){ echo "Failed: You have a loan balance of KES ".number_format($bal); }
			else{
				$upds=$fields=$vals=""; $cname=$client[0]['name']; $json=json_encode($pays,1); $dcts=json_encode($deds,1); $updskip=[];
				$replace=["payment"=>$pcode,"processing"=>$json,"client"=>$cname,"approvals"=>"[]","pref"=>0,"cuts"=>$dcts,"loantype"=>$lntp];
				if($tid){ $updskip=["approvals","pref"]; }
				
				foreach($keys as $key=>$dtype){
					if($key!="id" && !in_array($key,$files) && !in_array($key,$updskip)){
						$val = (isset($replace[$key])) ? $replace[$key]:clean(strtolower($_POST[$key]));
						$val = ($key=="phone") ? ltrim(trim($_POST['phone']),"254"):$val;
						$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
					}
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
										crop("../../docs/img/$img",800,700,"../../docs/img/$img");
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
								$fname = "DOC_".date("Ymd_His")."$key.$ext";
								if(move_uploaded_file($tmp,"../../docs/$fname")){
									$upds.="`$name`='$fname',"; $fields.="`$name`,"; $vals.="'$fname',";
								}
								else{ echo "Failed to upload document: Unknown Error occured"; exit(); }
							}
							else{ echo "File format $ext is not supported! Only PDF & .docx files are allowed"; exit(); }
						}
					}
				}
				
				$ins = rtrim($vals,','); $order = rtrim($fields,',');
				$query = ($tid) ? "UPDATE `$ltbl` SET ".rtrim($upds,',')." WHERE `id`='$tid'":"INSERT INTO `$ltbl` ($order) VALUES($ins)";
				if($db->execute(2,$query)){
					if(!$tid){
						$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
						$levels = ($qri) ? json_decode($qri[0]['levels'],1):[];
						if(isset($levels[1])){
							$pos = $levels[1]; $cont=0;
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$pos])){
										if($post[$pos]=="hq"){ $cont=$row['contact']; $name = ucwords(prepare($row['name'])); break; }
										else{
											if($row['branch']==$me['branch']){ $cont=$row['contact']; $name = ucwords(prepare($row['name'])); break; }
										}
									}
								}
								if($cont){
									sendSMS($cont,"Hi $name,".$client[0]["name"]." has applied for Loan of Ksh ".number_format($lamnt)." now waiting for your approval");
								}
							}
						}
					}
					
					$txt = ($tid) ? "Updated staff loan application for":"Created staff loan application for";
					savelog($sid,"$txt ".$client[0]['name']." of KES ".number_format($lamnt)); 
					echo "success";
				}
				else{ echo "Failed to complete the request at the moment! Try again later"; }
			}
		}
	}
	
	# save edited loan
	if(isset($_POST['lprod'])){
		$lid = trim($_POST['lid']);
		$prod = trim($_POST['lprod']);
		$ldur = trim($_POST['ldur']);
		$lamnt = trim($_POST['lamnt']);
		$disb = strtotime(trim($_POST['disb']));
		$ltbl = "staff_loans$cid";
		$stbl = "staff_schedule$cid";
		
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$prod'");
		$loans = $db->query(2,"SELECT *FROM `$ltbl` WHERE `loan`='$lid'"); $ptot=$loans[0]['balance']+$loans[0]['paid'];
		$pamnt = $loans[0]['amount']; $paid=$rem=$loans[0]['paid']; $prd=$loans[0]['loan_product']; $dur=$loans[0]['duration'];
		$ldis = strtotime(date("Y-M-d",$loans[0]['disbursement'])); $expd = $disb+($ldur*86400); $uid=$loans[0]['stid']; 
		if(!$db->istable(2,"varying_interest$cid")){ $db->createTbl(2,"varying_interest$cid",["loan"=>"INT","schedule"=>"TEXT","time"=>"INT"]); }
		
		if(!$prod){ echo "Failed: No loan product selected"; }
		elseif($lamnt<$lprod[0]['minamount']){ echo "Minimum Loan amount for the product is KES ".number_format($lprod[0]['minamount']); }
		elseif($lamnt>$lprod[0]['maxamount']){ echo "Maximum Loan amount for the product is KES ".number_format($lprod[0]['maxamount']); }
		else{
			if($lamnt==$pamnt && $prod==$prd && $dur==$ldur){
				if($db->execute(2,"UPDATE `$ltbl` SET `disbursement`='$disb',`expiry`='$expd' WHERE `loan`='$lid'")){
					if($ldis!=$disb){
						$sql = $db->execute(2,"SELECT *FROM `varying_interest$cid` WHERE `loan`='$lid'");
						if($sql){
							foreach(json_decode($sql[0]['schedule'],1) as $dy=>$perc){
								$ndy = ($ldis>$disb) ? $dy-($ldis-$disb):$dy+($disb-$ldis); $nds[$ndy]=$perc;
							}
							$db->execute(2,"UPDATE `varying_interest$cid` SET `schedule`='".json_encode($nds,1)."' WHERE `loan`='$lid'");
						}
						
						$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid'");
						foreach($res as $row){
							$change=$disb-$ldis; $day=$row['day']+$change; $mon=strtotime(date("Y-M",$day)); $rid=$row['id'];
							$db->execute(2,"UPDATE `$stbl` SET `month`='$mon',`day`='$day' WHERE `id`='$rid'");
						}
					}
					echo "success";
				}
				else{
					echo "Failed: Unknown error occured";
				}
			}
			else{
				$qrys=""; $pays=$pids=$codes=$payat=$vsd=[]; 
				$db->execute(2,"DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'");
				$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals'];
				foreach($payterms as $des=>$pay){
					$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
					if($val[0]==2){ $pays[$des]=$amnt; }
					if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
				}
				
				if(substr($lprod[0]['interest'],0,4)=="pvar"){
					$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
					$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
					$intrs = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
					foreach($vars as $key=>$perc){
						$dy = $disb+($key*86400); $dy+=$pfrm; $cyc=$ldur/$intv;
						$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
					}
				}
				else{ $intrs = (count(explode("%",$lprod[0]['interest']))>1) ? round($lamnt*explode("%",$lprod[0]['interest'])[0]/100):$lprod[0]['interest']; }
		
				$totpay=$tpay=$lamnt+($ldur/$lprod[0]['interest_duration']*$intrs); $inst=ceil($totpay/($ldur/$intv));
				$intr=round(($totpay-$lamnt)/($ldur/$intv)); $princ=$inst-$intr; 
				
				if($paid){
					$qri = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `paid`>0 ORDER BY id DESC");
					foreach($qri as $row){
						foreach(array_reverse(json_decode($row['payments'],1),1) as $key=>$ds){
							$pids[explode(":",$key)[0]]=explode(":",$key)[0];
						}
					}
				}
				
				foreach($pids as $pid){
					$res = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
					$codes[$pid]=$res[0]['code']; $amnts[$pid]=$res[0]['amount'];
				}
			
				# schedule 
				$vint = (count($vsd)) ? "varying":$intr; $pinst=ceil($lamnt/($ldur/$intv)); $tprc=$lamnt; $tintr=$totpay-$lamnt;
				$arr = array('principal'=>$princ,"interest"=>$vint)+$pays; $perin=$ldur/$intv; $other=array_sum($pays);
				for($i=1; $i<=$perin; $i++){
					$dy=$disb+$pfrm+($i*$intv*86400); $mon=strtotime(date("Y-M",$dy)); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
					$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $tpay+=$other;
					$brek = ($cut>0) ? array($payat[$i][0]=>$cut)+$arr:$arr; $totpay+=$cut+$other; $tpay+=$cut;
					if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; $inst=array_sum($brek); $cut=0; }
					$sinst = ($inst>$tpay) ? $tpay:$inst; $sinst+=$cut; $tpay-=$sinst; $bjs=json_encode($brek,1);
					$rem=($rem) ? $rem:0; $amnt=($rem>$sinst) ? $sinst:$rem; $bl=$sinst-$amnt; $rem-=$amnt;
					$qrys.=($paid>0 && count($codes)<1) ? "(NULL,'$uid','$lid','$mon','$dy','$sinst','$intr','$amnt','$bl','$bjs','[]'),":
					"(NULL,'$uid','$lid','$mon','$dy','$sinst','$intr','0','$sinst','$bjs','[]'),";
				}
			
				$expd = $disb+($ldur*86400); $ds=$lamnt-$pamnt; $expd+=$pfrm;
				$ded = ($ds>0) ? "+$ds":$ds;
				
				foreach($codes as $code){ reversepay("$uid:$lid",$code,"staff"); usleep(200000); }
				if($db->execute(2,"UPDATE `$ltbl` SET `loan_product`='$prod',`amount`='$lamnt',`balance`='$totpay',`duration`='$ldur',`disbursement`='$disb',`expiry`='$expd' WHERE `loan`='$lid'")){
					if($ds!=0){ bookbal(DEF_ACCS['loanbook'],$ded); }
					$db->execute(2,"DELETE FROM `$stbl` WHERE `loan`='$lid'");
					$db->execute(2,"INSERT INTO `$stbl` VALUES ".rtrim($qrys,","));
					if(count($vsd)){ $db->execute(2,"INSERT INTO `varying_interest$cid` VALUES(NULL,'$lid','".json_encode($vsd,1)."','".time()."')"); }
					foreach(array_reverse($codes,1) as $pid=>$code){ makepay($uid,$pid,$amnts[$pid],"staff",$lid); usleep(200000); }
					echo "success";
				}
				else{ echo "Failed: Unknown error occured"; }
			}
		}
	}
	
	# delete loan
	if(isset($_POST['deloan'])){
		$tid = trim($_POST['deloan']);
		$sql = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `id`='$tid'");
		$row = $sql[0]; $uid=$row['stid']; $lamnt=$row['amount']; $name=$row['staff']; $paid=$row['paid']; $lid=$row['loan'];
		$tbal=$row['balance']; $prod=$row['loan_product']; $dism=strtotime(date('Y-M',$row['disbursement']));
		
		if($db->execute(2,"DELETE FROM `staff_loans$cid` WHERE `id`='$tid'")){
			if($lid){
				$qry = $db->query(2,"SELECT SUM(interest) AS intr FROM `staff_schedule$cid` WHERE `loan`='$lid'");
				$pays=$codes=array(); $charges=0; $tintr=$qry[0]['intr'];
				$qry = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `linked`='$lid' ORDER BY `payid` DESC"); 
				if($qry){
					foreach($qry as $row){
						$pay=str_replace(" ","_",strtolower($row['payment'])); $codes[]=$row['code'];
						if(array_key_exists($pay,$pays)){ $pays[$pay]+=$row['amount']; }
						else{ $pays[$pay]=$row['amount']; }
						reversepay("$uid:$lid",$row['code'],"staff");
					}
				}
			
				foreach($codes as $code){
					if(substr($code,0,7)=="OVERPAY"){
						$rid = trim(str_replace("OVERPAY","",$code));
						$res = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$rid'"); $pid=$res[0]['payid'];
						$qri = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
						if(!in_array($qri[0]['code'],$codes)){ reversepay("$uid:$lid",$qri[0]['code'],"staff"); }
					}
				}
				
				$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
				foreach(json_decode($res[0]['payterms'],1) as $key=>$term){
					if(explode(":",$term)[0]<2){ $charges+=(isset($pays[$key])) ? $pays[$key]:0; }
				}
				
				$db->execute(2,"DELETE FROM `staff_schedule$cid` WHERE `loan`='$lid'");
				$db->execute(2,"DELETE FROM `processed_payments$cid` WHERE `linked`='$lid'");
				$db->execute(3,"DELETE FROM `transactions$cid` WHERE `refno`='$tid'");
				if($db->istable(2,"varying_interest$cid")){ $db->execute(2,"DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'"); }
				bookbal(DEF_ACCS['loan_charges'],"-$charges:$dism"); bookbal(DEF_ACCS['interest'],"-$tintr"); 
				bookbal(DEF_ACCS['loanbook'],"-$lamnt"); bookbal(getrule("loans")['credit'],"+$lamnt");
			}
			
			$txt = ($lid) ? "Deleted staff Loan record of Ksh $lamnt for staff $name":"Deleted staff $name loan application of Ksh $lamnt";
			savelog($sid,$txt); echo "success";
		}
		else{ echo "Failed to delete loan at the moment"; }
	}
	
	ob_end_flush();
?>