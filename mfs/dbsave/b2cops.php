<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	function getFee($amnt){
		foreach(B2C_RATES as $lim=>$rate){
			$range = explode("-",$lim); $fee=$rate;
			if($amnt>=$range[0] && $amnt<=$range[1]){ $fee=$rate; break; }
		}
		return $fee;
	}
	
	# reject template
	if(isset($_POST['rejectemp'])){
		$tid = trim($_POST['rejectemp']);
		$src = (isset($_POST["tsrc"])) ? trim($_POST['tsrc']):"client";
		$tbl = ($src=="client") ? "org".$cid."_loantemplates":"staff_loans$cid";
		
		if($db->execute(2,"UPDATE `$tbl` SET `status`='0',`approvals`='[]' WHERE `id`='$tid'")){
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment!"; }
	}
	
	# delete undisbursed
	if(isset($_POST['delreq'])){
		$rid = trim($_POST['delreq']);
		$chk = $db->query(2,"SELECT `source` FROM `b2c_trials$cid` WHERE `id`='$rid'");
		$def = ($chk) ? json_decode($chk[0]["source"],1):["src"=>"none"];
		
		if($db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `id`='$rid'")){
			if($def["src"]=="salaries"){ $db->execute(3,"DELETE FROM `paid_salaries$cid` WHERE `id`='".$def["id"]."'"); }
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment!"; }
	}
	
	# reject requisition
	if(isset($_POST['rejectreq'])){
		$rid = trim($_POST['rejectreq']);
		$json = json_encode([$sid=>"Rejected"],1);
		if($db->execute(3,"UPDATE `requisitions$cid` SET `status`='15',`approvals`='$json' WHERE `id`='$rid'")){
			savelog($sid,"Rejected requisition no $rid");
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment!"; }
	}
	
	# reject utility expense
	if(isset($_POST['rejectutil'])){
		$rid = trim($_POST['rejectutil']);
		$json = json_encode([$sid=>"Rejected"],1);
		if($db->execute(3,"UPDATE `utilities$cid` SET `status`='15',`approvals`='$json' WHERE `id`='$rid'")){
			savelog($sid,"Rejected utility expense no $rid");
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment!"; }
	}
	
	#send advance reject
	if(isset($_POST['rejres'])){
		$des = trim($_POST['rejres']);
		$fon = trim($_POST['rfon']);
		$rid = trim($_POST['rejid']);
		$json = json_encode([$sid=>clean($des)],1);
		
		if($db->execute(3,"UPDATE `advances$cid` SET `status`='15',`approvals`='$json' WHERE `id`='$rid'")){
			$me = ucwords(prepare($db->query(2,"SELECT `name` FROM `org".$cid."_staff` WHERE `id`='$sid'")[0]['name']));
			sendSMS($fon,"Hi,$me has rejected your salary advance request: $des");
			echo "success"; 
		}
		else{ echo (is_numeric($res)) ? "Failed: Poor Internet Connection":$res; }
	}
	
	# confirm sent requisition 
	if(isset($_POST['reqid'])){
		$rid = trim($_POST['reqid']);
		$acc = trim($_POST['reqacc']);
		$amnt = trim($_POST['reqamnt']);
		$tym = strtotime(trim($_POST['postm']));
		
		$qri = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `id`='$rid' AND `status`='200'");
		if($qri){
    		$yr = date("Y",$tym); $week=date("W",$tym); $mon=strtotime(date("Y-M",$tym)); $now=time();
    		$sql = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `id`='$rid'");
    		$bran = $sql[0]['branch']; $tid = getransid(); $rno=prenum($rid);
    		
    		$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'");  
    		$fid = ($res) ? $res[0]['id']:0; $desc = "Requisition $rno reimbursement"; $ds2="Reimbursement from requisition $rno";
    		
    		if(!$fid){
    			$db->execute(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$mon','0','0')");
    			$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'"); $fid = $qry[0]['id'];
    		}
    		
    		if($db->execute(3,"INSERT INTO `cashbook$cid` VALUES(NULL,'$bran','$sid','$tid','debit','$ds2','$amnt','$week','$mon','$yr','$now','0')")){
    			$db->execute(3,"UPDATE `requisitions$cid` SET `status`='$tym',`approved`='$amnt' WHERE `id`='$rid'");
				$rev = json_encode(array(["db"=>3,"tbl"=>"cashfloats$cid","col"=>"id","val"=>$fid,"update"=>"closing:-$amnt"],
				["db"=>3,"tbl"=>"cashbook$cid","col"=>"transid","val"=>$tid,"update"=>"delete:$tid"]));
				doublepost([$bran,$acc,$amnt,$desc,$tid,$sid],[$bran,14,$amnt,$ds2,$tid,$sid],'',$now,$rev);
    			cashbal($fid,"+$amnt"); savelog($sid,"Confirmed KES $amnt sent on ".date("M d,Y",$tym)." for requisition no $rid");
				echo "success";
    		}
    		else{ echo "Failed to complete the request at the moment"; }
		}
		else{ echo "Failed: Requisition is already confirmed"; }
	}
	
	# confirm sent advance 
	if(isset($_POST['advid'])){
		$rid = trim($_POST['advid']);
		$acc = trim($_POST['advacc']);
		$amnt = trim($_POST['adamnt']);
		$tym = trim($_POST['postm']);
		
		$qri = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid' AND `status`='200'");
		if($qri){
    		$row = $qri[0]; $now=time(); $mon=$row['month']; $stid=$row['staff']; $tid=getransid();
    		$qry = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `id`='$stid'"); $bran=$qry[0]['branch'];
			$name=ucwords($qry[0]['name']); $desc="Salary advance for $name in ".date("F Y",$mon); $chaj=0;
			if(isset($row['charges'])){ $chaj=($row["charges"]) ? array_sum(json_decode($row['charges'],1)):0; }
			
    		if($db->execute(3,"UPDATE `advances$cid` SET `status`='$tym' WHERE `id`='$rid'")){
    			$db->execute(3,"UPDATE `deductions$cid` SET `status`='$tym' WHERE `staff`='$stid' AND `amount`='$amnt' AND `status`='200'");
				doublepost([$bran,$acc,$amnt,$desc,$tid,$sid],[$bran,15,$amnt,$desc,$tid,$sid],'',$tym,"auto");
				if($chaj){ saveTrans_fees($tid,"Interest Charges from Salary Advance to $name",$chaj,$sid); }
				savelog($sid,"Confirmed KES $amnt was sent to $name on ".date("M d,Y",$tym));
				echo "success";
    		}
    		else{ echo "Failed to complete the request at the moment"; }
		}
		else{ echo "Failed: Advance is already confirmed"; }
	}
	
	# initiate disbursement
	if(isset($_POST['dfrom'])){
		$src = trim($_POST['dfrom']);
		$cnl = trim($_POST['distype']);
		$pbl = trim($_POST['pcnl']);
		$otp = trim($_POST['otpv']);
		$pin = clean($_POST['dpin']);
		$to = $_POST['disto'];
		$tdy = strtotime(date("Y-M-d"));
		
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; 
		$qri = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		$sql = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
		$list = array("advance"=>"Salary advances","requisition"=>"Requisitions","loantemplate"=>"Client Loan template","utility"=>"Vendor Payments","staffloans"=>"Staff Loan template");
		
		if(!is_numeric($pin)){ echo "Failed: Secret PIN must be numeric!"; }
		else{
			if($qri){
				if($qri[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$qri[0]['expiry']); }
				else{
					$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='b2c_lock_days'");
					$dys = ($chk) ? intval($chk[0]['value']):1; $tdy-=(86400*$dys); $sent=$now=$qrys=$modes=$accs=[]; $error="";
					if(!$db->istable(2,"b2c_trials$cid")){
						$db->createTbl(2,"b2c_trials$cid",["phone"=>"CHAR","name"=>"CHAR","amount"=>"INT","source"=>"TEXT","user"=>"INT","time"=>"INT","status"=>"INT"]); 
					}
					
					$db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`<$tdy"); $tym=time(); 
					$snt = $db->query(2,"SELECT *FROM `b2c_trials$cid` WHERE `time`>='$tdy'");  
					if(is_array($snt)){
						foreach($snt as $row){
							$def=json_decode($row['source'],1); $sent[$row['phone'].$def["id"]]=["tbl"=>$def["src"],"status"=>$row['status']]; 
						}
					}
					
					$damt = (isset($_POST["damnt"])) ? $_POST["damnt"]:[]; $damnt=$tmps=[]; $tsum=0;
					foreach($damt as $key=>$sum){
						$one=explode(":",$key); $id=$one[0]; $lim=explode("-",$one[1]);
						$dam=($lim[0]>$sum) ? $lim[0]:$sum; $dam=($lim[1]<$sum) ? $lim[1]:$dam;
						$damnt[$id]=($lim[0]<=$sum && $lim[1]>=$sum) ? $sum:$dam; 
					}
					
					foreach($to as $key=>$amnt){
						$dnf=explode(":",$key); $id=$dnf[1]; $cont=$dnf[0]; $dmd=(isset($dnf[3])) ? $dnf[3]:"b2c"; $dsrc=$src;
						$sto = ($dmd=="b2c") ? intval(ltrim($cont,"254")):$cont; $av=0; $ran=dechex(rand(10000000,999999999));
						if($src=="loantemplate"){ $dsrc = ($dnf[2]=="st") ? "staffloans":$src; }
						if(isset($sent[$sto.$id]) && $sent[$sto.$id]["tbl"]==$src){
							if($src=="loantemplate" && isset($damnt[$id])){ $av+=($sent[$sto.$id]["status"]<200) ? 1:0; }
							else{ $av++; }
						}
						
						if(!$av){
							$sam = (isset($damnt[$id])) ? $damnt[$id]:$amnt; $now[$dmd]["$cont-$ran"]=$sam;
							$des = json_encode(["src"=>$dsrc,"id"=>$id,"dtp"=>$dmd,"tmp"=>$ran,"channel"=>$cnl],1); $tsum+=$sam; $tmps[$ran]=$id;
							$qrys[] = "(NULL,'$sto','----','$sam','$des','$sid','$tym','0')";  
						}
					}
					
					foreach($now as $k=>$one){
						if(count($one)>5){ $error="Failed: You can only initiate upto 5 $k recipients at once!"; break; }
					}
					
					if(count($now)<1){ echo "Failed: Recipients had already been initiated before"; }
					elseif($error){ echo $error; }
					else{
						$nto = $sql[0]['notify']; $resp=$call=$sums=$restx=[];
						$db->execute(2,"INSERT INTO `b2c_trials$cid` VALUES ".implode(",",$qrys)); 
						foreach($now as $md=>$dto){
							if($md=="b2c"){ $res = send_money($dto,$pin,$sid,"b2c",$pbl); }
							else{ $sto=[];
								foreach($dto as $k=>$sum){
									if($src=="utility"){
										$id = $tmps[explode("-",$k)[1]]; $trans="Vendor Payment ".prenum($id);
										$qri = $db->query(3,"SELECT `recipient` FROM `utilities$cid` WHERE `id`='$id'");
										$accno = ($qri) ? json_decode($qri[0]['recipient'],1)["tname"]:"VP$id";
										$sto[$k] = array("amount"=>$sum,"type"=>$md,"account"=>$accno,"trans"=>$trans);
									}
								}
								$res = send_money($sto,$pin,$sid,"b2b",$pbl);
							}
							
							$call[$md] = explode("=>",$res)[0]; $resp[$md]=$res; $sums[$md]=[count($dto),array_sum($dto)];
							if(explode("=>",$res)[0]!="success"){ break; }
						}
						
						if(in_array("success",$call)){
							$dls = array("b2c"=>"B2C","b2b"=>"Paybill","till"=>"BuyGoods");
							$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon'");
							foreach($resp as $md=>$one){
								if($call[$md]=="success"){
									$tsum=$sums[$md][1]; $tot=$sums[$md][0]; $dxt=($tot==1) ? "1 recipient":"$tot recipients";
									$data = json_decode(explode("=>",$one)[1],1);
									
									if($nto && $nto!=$sid){
										$toinf = staffInfo($nto);
										if($toinf['contact']!=$fon){
											$myname = prepare(ucwords($me['name'])); $name=prepare(ucwords($toinf['name']));
											sendSMS($toinf['contact'],"Hi $name, $myname has initiated $dls[$md] disbursement of KES $tsum from $list[$src] to $dxt");
										}
									}
									
									$restx[] = "[$dls[$md]] ".$data['sent']."/$tot | ".$data['failed']." Failed";
									savelog($sid,"Initiated $dls[$md] disbursement of KES $tsum from $list[$src] to $dxt");
								}
							}
							
							echo "success:".implode(",",$restx);
						}
						else{
							$db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`='$tym'");
							echo implode("",$call);
						}
					}
				}
			}
			else{ echo "Failed: Invalid OTP, try again"; }
		}
	}
	
	# disburse salary
	if(isset($_POST['saloto'])){
		$sto = $_POST['saloto'];
		$pin = trim($_POST['dpin']);
		$otp = trim($_POST['otpv']);
		$mon = trim($_POST['smon']);
		$cnl = trim($_POST["paycnl"]);
		$pbl = (isset($_POST["pcnl"])) ? trim($_POST['pcnl']):0;
		
		$me = staffInfo($sid); $fon=$me['contact']; $done=$send=$saved=[]; $tym=time(); $tsum=0;
		$qri = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		$sql = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
		
		if(!is_numeric($pin)){ echo "Failed: Secret PIN must be numeric!"; }
		else{
			if($qri){
				if($qri[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$qri[0]['expiry']); }
				else{
					if(!in_array("payslip",$db->tableFields(3,"paid_salaries$cid"))){ $db->execute(3,"ALTER TABLE `paid_salaries$cid` ADD `payslip` INT NOT NULL AFTER `staff`"); }
					if(!$db->istable(2,"b2c_trials$cid")){
						$db->createTbl(2,"b2c_trials$cid",["phone"=>"CHAR","name"=>"CHAR","amount"=>"INT","source"=>"TEXT","user"=>"INT","time"=>"INT","status"=>"INT"]); 
					}
					
					if($cnl=="accs"){
						$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
						$posted = ($qri) ? json_decode($qri[0]["value"],1):[]; $payrac=(isset($posted[$mon])) ? $posted[$mon]:0;
						if(!$payrac){ echo "Failed: Post payroll to accounts first before you proceed"; exit(); }
					}
					
					$qry = $db->query(3,"SELECT *FROM `paid_salaries$cid` WHERE `month`='$mon'");
					if($qry){
						foreach($qry as $row){ $done[]=$row['payslip']; }
					}
					
					if(count($sto)){
						foreach($sto as $one){
							$def=explode(":",$one); $phone=$def[0]; $id=$def[1]; $phon=substr($phone,3);
							$chk = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `id`='$id'");
							if($chk){
								$row=$chk[0]; $amnt=$row["amount"]; $uid=$row['staff'];
								if(!in_array($id,$done)){
									$ran=dechex(rand(10000000,999999999)); $mod=($cnl=="accs") ? "ACCOUNTS":strtoupper($cnl);
									$db->execute(3,"INSERT INTO `paid_salaries$cid` VALUES(NULL,'$uid','$id','$amnt','$mod','$phon','$mon','0','$tym')");
									$rid = $db->query(3,"SELECT `id` FROM `paid_salaries$cid` WHERE `staff`='$uid' AND `payslip`='$id'")[0]['id'];
									$jsn = json_encode(["src"=>"salaries","id"=>$rid,"dtp"=>$cnl,"tmp"=>$ran],1); $saved[]=$rid;
									$qrys[] = "(NULL,'$phon','----','$amnt','$jsn','$sid','$tym','0')"; 
									if($cnl=="b2c"){ $send["$phone-$ran"]=$amnt; }
									elseif($cnl=="accs"){ $send["$id-".$_POST["srcacc"][$id]]=$amnt; }
									else{
										$pb=trim($_POST["payto"][$id]); $accno=trim($_POST["payacc"][$id]); $trans=date("F Y",$mon)." Salary Payment";
										$send["$pb-$ran"]=array("amount"=>$amnt,"type"=>$cnl,"account"=>$accno,"trans"=>$trans);
									}
								}
							}
						}
						
						if(count($send)<1){ echo "Failed: Recipients had already been initiated before"; }
						elseif(count($send)>5 && $cnl!="accs"){
							$db->execute(3,"DELETE FROM `paid_salaries$cid` WHERE `time`='$tym'");
							echo "Failed: You can only initiate upto 5 numbers at once!"; 
						}
						else{
							if($cnl=="accs"){
								$day = strtotime(trim($_POST["dday"]));
								$refs = $_POST["refs"];
								foreach($send as $k=>$sum){
									$df = explode("-",$k); $tid=getransid(); $pid=$df[0]; $tsum+=$sum;
									$uid = $db->query(3,"SELECT `staff` FROM `payslips$cid` WHERE `id`='$pid'")[0]["staff"];
									$staff = staffInfo($uid); $name=ucwords($staff["name"]); $bran=$staff["branch"]; $desc="$name Salary disbursement for ".date("F Y",$mon);
									doublepost([$bran,$df[1],$sum,$desc,$tid,$sid],[$bran,$payrac,$sum,$desc,$tid,$sid],$refs[$pid],$day,"default");
									$db->execute(3,"UPDATE `paid_salaries$cid` SET `status`='$tym' WHERE `payslip`='$pid'");
								}
								
								$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon'");
								savelog($sid,"Confirmed salary disbursement of KES $tsum to ".count($send)." employees from Asset accounts");
								echo "success";
							}
							else{
								$db->execute(2,"INSERT INTO `b2c_trials$cid` VALUES ".implode(",",$qrys));
								$res = send_money($send,$pin,$sid,$cnl,$pbl);
								if(explode("=>",$res)[0]=="success"){
									$data = json_decode(explode("=>",$res)[1],1); $nto=$sql[0]['notify'];
									$db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon'");
								
									if($nto && $nto!=$sid){
										$toinf = staffInfo($nto); $sum=fnum(array_sum($send));
										if($toinf['contact']!=$fon){
											$myname = prepare(ucwords($me['name'])); $name=prepare(ucwords($toinf['name']));
											sendSMS($toinf['contact'],"Hi $name, $myname has initiated salary disbursement of KES $sum to ".count($send)." employees via $cnl");
										}
										savelog($sid,"Initiated salary disbursement of KES $sum to ".count($send)." employees via $cnl");
									}
									echo "success:".$data['sent']."/".count($send).", ".$data['failed']." Failed";
								}
								else{
									$db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`='$tym'");
									foreach($saved as $id){ $db->execute(3,"DELETE FROM `paid_salaries$cid` WHERE `id`='$id'"); }
									echo $res;
								}
							}
						}
					}
					else{ echo "Failed: No staff selected!"; }
				}
			}
			else{ echo "Failed: Invalid OTP, try again"; }
		}
	}
	
	# generate XML 
	if(isset($_POST['genxml'])){
		$fons = json_decode(trim($_POST['genxml']),1);
		$fname = "Bulk_phone_validation$sid.xml";
		echo (genvalidxml($fons,"../../docs/$fname")) ? "success:$fname":"Failed to generate XML File";
	}
	
	# validate phones
	if(isset($_FILES['csverify'])){
		require "../../xls/index.php";
		$tmp = $_FILES['csverify']['tmp_name'];
		$name = strtolower($_FILES['csverify']['name']);
		$ext = @array_pop(explode(".",$name));
		
		if($ext!="csv"){ echo "Failed: Only CSV Files are allowed"; }
		else{
			if(move_uploaded_file($tmp,"../../docs/temp$sid.csv")){
				$data = openExcel("../../docs/temp$sid.csv");
				if(count($data)<9){ echo "Failed: Invalid CSV File!"; }
				else{
					$res=array_slice($data,9);
					foreach($res as $one){
						if(count($one)>5){
							$fon = ltrim($one[3],"254"); $name=clean($one[6]); 
							$db->execute(1,"INSERT IGNORE INTO `validations` VALUES('$cid','$fon','$name')");
						}
					}
					echo "success";
				}
				unlink("../../docs/temp$sid.csv"); 
			}
			else{ echo "Failed to upload the file"; }
		}
	}
	
	# check balance
	if(isset($_POST['checkbal'])){
		$payb = trim($_POST["payrt"]);
		$pin = trim($_POST['checkbal']);
		echo b2c_balance($pin,$sid,$payb);
	}
	
	if(isset($_POST['cksec'])){
		$payb = trim($_POST["payrt"]);
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]); $now=time();
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "http://pay.axecredits.com":"http://pay.".implode(".",$http);
		$res = request("$url/b2c/request.php",["getcpass"=>$sid,"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"payb"=>$payb]); 
		
		$sec = trim($_POST['cksec']);
		$cpin = clean($_POST['cpin']);
		$nval = clean($_POST['newkey']);
		
		if($sec=="pin" && strlen($nval)<6){ echo "Failed: Minimum PIN characters must be 6"; exit(); }
		if($cpass = decrypt($res,$cpin)){
			$update = ($sec=="pin") ? $cpass:$nval; $key=($sec=="pin") ? $nval:$cpin; $enc=dechex($now)."/".encrypt($key,date("MdY.His",$now));
			$req = request("$url/b2c/request.php",["changepass"=>encrypt($update,$key),"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"payb"=>$payb,"auto"=>$enc]);
			if(trim($req)=="success"){
				$txt = ($sec=="pin") ? "Mpesa disbursement secret PIN":"Mpesa disbursement password";
				savelog($sid,"Changed $txt");
			}
			echo $req;
		}
		else{ echo "Failed: Incorrect Secret PIN"; }
	}
	
	# upload b2c doc 
	if(isset($_FILES['b2cdoc'])){
		require "../../xls/index.php";
		$tmp = $_FILES['b2cdoc']['tmp_name'];
		$ext = @array_pop(explode(".",strtolower($_FILES['b2cdoc']['name'])));
		$doc = time().".$ext";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){ $mybran[$row['id']]=$row['branch']; $names[$row['id']]=ucwords($row['name']); }
		
		if(!in_array($ext,array("xls","xlsx"))){ echo "Failed: File formart $ext is not supported! Choose XLS & XLSX files"; }
		else{
			if(move_uploaded_file($tmp,$doc)){
				$data = openExcel($doc);
				$cols = $db->tableFields(2,"payouts$cid");
				if(!in_array("branch",$cols)){
					$db->execute(2,"ALTER TABLE `payouts$cid` ADD `user` INT NOT NULL AFTER `time`, ADD `branch` INT NOT NULL AFTER `user`");
				}
				
				foreach(array_slice($data,7) as $key=>$one){ 
					if(in_array(substr($one[3],0,16),["Business Payment","Business Buy Goo","Business Pay Bil"])){
						$paycode=$one[0]; $tym=strtotime($one[1]); $amnt=str_replace("-","",$one[6]); $to=explode("-",$one[10]); $dy=strtotime(date("Y-M-d",$tym)); 
						$name=clean(trim($to[1])); $mon=strtotime(date("Y-M",$tym)); $week=date("W",$tym); $fee=getFee($amnt); $phone=trim($to[0]); $yr=date("Y",$tym);
						$bid=$rid=$pst=0; $btid=intval($one[11]); $fon=(is_numeric($phone)) ? intval(ltrim($phone,"254")):$phone; $day=$one[1]; $src="";
						
						$sql = $db->query(2,"SELECT *FROM `b2c_trials$cid` WHERE `id`='$btid' AND `status`<2");
						if($sql){
							$def = json_decode($sql[0]['source'],1); $src=$def["src"]; $rid=$def["id"]; 
							$db->execute(2,"UPDATE `b2c_trials$cid` SET `name`='$name',`status`='200' WHERE `id`='$btid'");
							if(!is_numeric($phone)){
								$row=$sql[0]; $fon=$row['phone']; $phone="0$fon";
								$chk = $db->query(1,"SELECT `name` FROM `validations` WHERE `phone`='$fon'");
								$name = ($chk) ? $chk[0]['name']:$name;
							}
						}
						
						# requisitions
						if($src=="requisition"){
							$sql = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `id`='$rid' AND `status`='200'");
							if($sql){
								$row = $sql[0]; $tid=getransid(); $bran=$bid=$row['branch']; $rno=prenum($rid);
								$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'");  
								$fid = ($res) ? $res[0]['id']:0; $desc = "Requisition $rno reimbursement"; $ds2="Reimbursement from requisition $rno";
									
								if(!$fid){
									$db->execute(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$mon','0','0')");
									$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'"); $fid = $qry[0]['id'];
								}
								
								cashbal($fid,"+$amnt"); $pst=$tym;
								$db->execute(3,"INSERT INTO `cashbook$cid` VALUES(NULL,'$bran','$sid','$tid','debit','$ds2','$amnt','$week','$mon','$yr','$tym','0')");
								$db->execute(3,"UPDATE `requisitions$cid` SET `status`='$tym' WHERE `id`='$rid'");
								$rev = json_encode(array(["db"=>3,"tbl"=>"cashfloats$cid","col"=>"id","val"=>$fid,"update"=>"closing:-$amnt"],
								["db"=>3,"tbl"=>"cashbook$cid","col"=>"transid","val"=>$tid,"update"=>"delete:$tid"]));
								doublepost([$bran,16,$amnt,$desc,$tid,$sid],[$bran,14,$amnt,$ds2,$tid,$sid],'',$tym,$rev);
							}
						}
						
						# advances
						if($src=="advance"){
							$qri = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid' AND `status`='200'");
							if($qri){
								$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"status:0"]));
								$row = $qri[0]; $mont=$row['month']; $stid=$row['staff']; $bran=$bid=$mybran[$stid]; $tid=getransid(); $pst=$tym;
								$desc = "Salary advance for $names[$stid] in ".date("F Y",$mont); $dca=getrule("advances")['debit']; $cut=0;
								if(isset($row['charges'])){ $cut=($row["charges"]) ? array_sum(json_decode($row['charges'],1)):0; }
								$db->execute(3,"UPDATE `advances$cid` SET `status`='$tym' WHERE `id`='$rid'"); $tsum=$row['approved'];
								$db->execute(3,"UPDATE `deductions$cid` SET `status`='$tym' WHERE `staff`='$stid' AND `amount`='$tsum' AND `status`='200'");
								doublepost([$bran,16,$amnt,$desc,$tid,$sid],[$bran,$dca,$amnt,$desc,$tid,$sid],'',$tym,$rev);
								if($cut){ saveTrans_fees($paycode,"Interest Charges from Salary Advance to $names[$stid]",$cut,$sid); }
							}
						}
						
						# salaries
						if($src=="salaries"){
							$res = $db->query(3,"SELECT *FROM `paid_salaries$cid` WHERE `status`='0' AND `id`='$rid'");
							if($res){
								$db->execute(3,"UPDATE `paid_salaries$cid` SET `status`='$tym' WHERE `id`='$rid'");
								$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
								$posted = ($qri) ? json_decode($qri[0]["value"],1):[]; $bid=$mybran[$res[0]['staff']]; $mn=$res[0]["month"];
								if(isset($posted[$mn])){
									$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"status:0"]));
									$desc = "Salary disbursement to ".$names[$res[0]['staff']]." for the month of ".date("F Y",$mn); $tid=getransid(); $pst=$tym;
									doublepost([$bid,16,$amnt,$desc,$tid,$sid],[$bran,$posted[$mn],$amnt,$desc,$tid,$sid],'',$tym,$rev);
								}
							}
						}
						
						# utility expenses
						if($src=="utility"){
							$qri = $db->query(3,"SELECT *FROM `utilities$cid` WHERE `id`='$rid' AND `status`='200'");
							if($qri){
								$row = $qri[0]; $tid=getransid(); $bran=$bid=$row['branch']; $rno=prenum($rid); $pst=$tym; $no=0;
								$desc = json_decode($row['item_description'],1); $des=json_decode($row['recipient'],1); $des['name']=$name; $jsn=json_encode($des,1);
								$db->execute(3,"UPDATE `utilities$cid` SET `status`='$tym',`recipient`='$jsn' WHERE `id`='$rid' AND `status`='200'");
								$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"status:0"]),1);
								if(is_array($des['book'])){
									$charge = (isset($des["fee"])) ? $des["fee"]:0;
									foreach($des['book'] as $abk=>$sum){
										$book = explode(":",$abk); $dsc=$desc[$no]["item"]; $no++;
										doublepost([$bran,16,$sum,$dsc,$tid,$sid],[$bran,$book[0],$sum,$dsc,$tid,$sid],'',$tym,$rev); 
										if(count($book)>1){
											$qry = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='".$book[1]."'");
											if($qry){
												$uid=$qry[0]['client']; $actp=$qry[0]['type'];
												updateWallet($uid,"-$amnt",$actp,["desc"=>"$dsc Ref #$paycode","revs"=>array(["tbl"=>"disb","tid"=>"$tid:$paycode"])],$sid,time(),1,0);
											}
										}
									}
									
									if($charge>0){
										saveTrans_fees($paycode,"Transaction Charges from B2C disbursement to $name",$charge,$sid); $mtm=time()+2;
										if($uid){ updateWallet($uid,"-$charge",$actp,["desc"=>"Withdrawal #$paycode Transaction Charges","revs"=>array(["tbl"=>"norev"])],$sid,$mtm); }
									}
								}
								else{ doublepost([$bran,16,$amnt,$desc[0]["item"],$tid,$sid],[$bran,$des['book'],$amnt,$desc[0]["item"],$tid,$sid],'',$tym,$rev); }
							}
						}
						
						# client loans
						if($src=="loantemplate"){
							$chk = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `id`='$rid' AND `status`='8'");
							$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `status`='$tym' WHERE `id`='$rid' AND `status`='8'");
							$bid = ($chk) ? $chk[0]['branch']:0;
							if($chk){
								$row = $chk[0]; $cut=$row['checkoff']+$row['prepay']; $apam=$row['amount']; 
								$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; 
								$cut+= (isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; 
								$tds = ($dsb) ? $amnt:$amnt+$cut; $dsb[$tym]=$tds; $djsn=json_encode($dsb,1); $lntp=$row['loantype']; 
								$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `disbursed`='$djsn' WHERE `id`='$rid'");
								$pst = $tym; $des="$paycode - Loan of KES $amnt to $name"; $payb=B2C_DEF["comdef"]["paybill"]; 
								$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"status:0"]),1);
								doublepost([$bid,DEF_ACCS['mpesabulk'],$amnt,$des,"CLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"CLOAN$rid",$sid],'',$tym,$rev);
								if(explode(":",$lntp)[0]=="topup" or count($dsb)>1){
									$tsr = (count($dsb)>1) ? $rid:explode(":",$lntp)[1];
									$qri = $db->query(2,"SELECT `loan`,`balance` FROM `org$cid"."_loans` WHERE `tid`='$tsr'");
									if($qri){
										$jsn = ["desc"=>"Loan Topup to $name via MPESA B2C Channel $payb. Ref $paycode","type"=>"debit","amount"=>$amnt,"bal"=>$qri[0]['balance']+$amnt];
										logtrans($qri[0]['loan'],json_encode($jsn,1),0); 
									}
								}
								else{ logtrans("TID$rid","Disbursement to $name via MPESA B2C Channel $payb. Ref $paycode",0); }
							}
						}
						
						# staff loans
						if($src=="staffloans"){
							$chk = $db->query(2,"SELECT `branch`,`loantype` FROM `staff_loans$cid` WHERE `id`='$rid' AND `status`='8'");
							$db->execute(2,"UPDATE `staff_loans$cid` SET `status`='$tym' WHERE `id`='$rid' AND `status`='8'");
							$bid = ($chk) ? $chk[0]['branch']:0;
							if($chk){
								$pst = $tym; $des="$paycode - Loan of KES $amnt to $name"; $payb=B2C_DEF["comdef"]["paybill"]; $lntp=$chk[0]['loantype'];
								$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"status:0"]),1);
								doublepost([$bid,DEF_ACCS['mpesabulk'],$amnt,$des,"SLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"SLOAN$rid",$sid],'',$tym,$rev);
								if(explode(":",$lntp)[0]=="topup"){
									$qri = $db->query(2,"SELECT `loan`,`balance` FROM `staff_loans$cid` WHERE `id`='".explode(":",$lntp)[1]."'");
									if($qri){
										$jsn = ["desc"=>"Loan Topup to $name via MPESA B2C Channel $payb. Ref $paycode","type"=>"debit","amount"=>$amnt,"bal"=>$qri[0]['balance']+$amnt];
										logtrans($qri[0]['loan'],json_encode($jsn,1),0); 
									}
								}
								else{ logtrans("TID$rid","Disbursement to $name via MPESA B2C Channel $payb. Ref $paycode",0); }
							}
						}
						
						if($rid){
							$db->execute(2,"INSERT IGNORE INTO `payouts$cid` VALUES(NULL,'$phone','$name','$amnt','$paycode','$day','$dy','$mon','$pst','$fee','$tym','$tym','$sid','$bid')");
							if(is_numeric($fon)){ $db->execute(1,"INSERT IGNORE INTO `validations` VALUES('$cid','$fon','$name')"); }
							if($fee){
								$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$paycode,"update"=>"posted:0"])); $desc="Charges from Disbursement to $name";
								doublepost([$bid,DEF_ACCS['mpesabulk'],$fee,$desc,$tym,$sid],[$bid,DEF_ACCS['b2c_charges'],$fee,$desc,$tym,$sid],'',$tym,$rev);
							}
						}
					}
				}
				echo "success"; unlink($doc);
			}
			else{ echo "Failed to upload the document"; }
		}
	}
	
	ob_end_flush();
?>