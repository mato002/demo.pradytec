<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$stid=$suid=substr(hexdec($_SESSION['myacc']),6);
	if(!$stid){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	function getpaye($amnt,$emp="normal"){
		$db = new DBO();
		$qri = $db->query(1,"SELECT *FROM `taxation` WHERE `type`='paye'");
		$rates = json_decode($qri[0]['taxes'],1);
		
		if($amnt<=$rates['minimum']){ $res=0; }
		elseif($amnt>$rates['minimum'] && $amnt<=$rates['part1']){
			$res = ($emp=="consultant") ? round($amnt*0.05):round(($amnt-$rates['minimum'])*$rates['taxrate1']/100);
		}
		else{
			$part1 = round($rates['limit_charge']*$rates['taxrate1']/100);
			$part2 = round(($amnt-$rates['part1'])*$rates['taxrate2']/100);
			$res = ($emp=="consultant") ? round($amnt*0.05):$part1+$part2;
		}
		return round($res);
	}
	
	function getcut($type,$amnt,$emp="normal"){
		$db = new DBO(); $cid=CLIENT_ID; $rate=0;
		if($emp=="consultant"){ return 0; }
		else{
			$sql = $db->query(1,"SELECT *FROM `paycuts` WHERE `client`='$cid' AND `deduction`='statutory'");
			$def = ($sql) ? json_decode($sql[0]["cut"],1):["NHIF"=>["0-500000"=>"2.75%"],"NSSF"=>["0-500000"=>"6%"]]; 
			if(!isset($def[$type])){ $def[$type]=["0-5000000"=>0]; }
			foreach($def[$type] as $range=>$charge){
				if($amnt>=intval(explode("-",$range)[0]) && $amnt<=intval(explode("-",$range)[1])){
					$rate = (count(explode("%",$charge))>1) ? round(explode("%",$charge)[0]/100*$amnt):round($charge); break;
				}
			}
			
			if($rate<300 && $type=="NHIF"){ $rate=300; }
			return $rate;
		}
	}
	
	function getItem($arr,$item){
		$res = 0;
		foreach($arr as $key=>$val){
			if(strpos(strtolower($key),strtolower($item))!==false){ $res=$val; break; }
		}
		return $res;
	}
	
	if(isset($_POST['basicpay'])){
		$basics = $_POST['basicpay'];
		$helbs = $_POST['helb'];
		$pensn = $_POST['pension'];
		$mon = trim($_POST['pmon']); 
		$yr=date("Y",$mon); $no=0;
		$saved=$bonus=$allows=$taxes=$deducts=[]; 
		
		$check = $db->query(3,"SELECT `staff` FROM `payroll$cid` WHERE `month`='$mon'");
		if($check){
			foreach($check as $row){ $saved[]=$row['staff']; }
		}
		
		$sql = $db->query(1,"SELECT *FROM `paycuts` WHERE `client`='$cid' AND `deduction`='statutory'");
		$def = ($sql) ? json_decode($sql[0]["cut"],1):["NHIF"=>["0-500000"=>"2.75%"],"NSSF"=>["0-500000"=>"6%"],"NITA"=>["0-500000"=>0]];
		unset($def["NHIF"],$def["NSSF"],$def["NITA"]); $now=time();
		
		$res = $db->query(1,"SELECT *FROM `taxation` WHERE `type`='paye'");
		$tax = json_decode($res[0]['taxes'],1); $minallow=$tax['taxable_allowances'];
		
		$req = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND NOT `position`='USSD' AND NOT `status`='15'");
		foreach($req as $row){
			$pay = json_decode($row['config'],1)['payroll']; $nh=(isset($pay["nhif"])) ? $pay["nhif"]:1; $nhs[$row["id"]]=$nh;
			$defs[$row['id']]=$pay['paye']; $emps[$row["id"]]=(isset($pay["etype"])) ? $pay["etype"]:"normal";
		}
		
		$qri = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$des = json_decode($row["details"],1); $taxb=(isset($des['allowance']["taxable"])) ? $des['allowance']["taxable"]:0;
				$taxb+= (isset($des['bonus']["taxable"])) ? $des['bonus']["taxable"]:0; $taxes[$row['staff']]=$taxb;
				$bonus[$row['staff']]=$row['bonus']; $allows[$row['staff']]=$row['allowance']; 
			}
		}
		
		$qri = $db->query(3,"SELECT *,SUM(amount) AS tamnt FROM `deductions$cid` WHERE `month`='$mon' AND `status`>200 GROUP BY `staff`");
		if($qri){
			foreach($qri as $row){ $deducts[$row['staff']]=$row['tamnt']; }
		}
	
		foreach(array_keys($basics) as $sid){
			$bpay=$basics[$sid]; $allow=(isset($allows[$sid])) ? $allows[$sid]:0; $taxb=(isset($taxes[$sid])) ? $taxes[$sid]:0; $bene=0;
			$bnus=(isset($bonus[$sid])) ? intval($bonus[$sid]):0; $deduct=(isset($deducts[$sid])) ? $deducts[$sid]:0; $gross=$bpay+$taxb+$bene;
			$pens=$pensn[$sid]; $helb=$helbs[$sid]; $nita=(isset($_POST["nita"])) ? $_POST["nita"][$sid]:getcut("NITA",$gross); 
			$etp = $emps[$sid]; $nssf=getcut("NSSF",$gross,$etp); 
			
			if(!$defs[$sid]){
				$nita=0; $netpay=($gross+$allow+$bnus)-($deduct+$pens+$helb+$nita+$taxb);
				if(in_array($sid,$saved)){
					$db->execute(3,"UPDATE `payroll$cid` SET `basicpay`='$bpay',`allowance`='$allow',`benefits`='$bene',`grosspay`='$gross',`taxablepay`='0',`helb`='$helb',`nita`='$nita',
					`pension`='$pens',`paye`='0',`nssf`='0',`nhif`='0',`deductions`='$deduct',`cuts`='[]',`netpay`='$netpay' WHERE `staff`='$sid' AND `month`='$mon'"); $no++;
					$db->execute(3,"UPDATE `payslips$cid` SET `amount`='$netpay' WHERE `staff`='$sid' AND `month`='$mon'");
				}
				else{
					$db->execute(3,"INSERT INTO `payroll$cid` VALUES(NULL,'$sid','$bpay','$allow','$bene','$gross','0','0','0','0','$helb','$nita','$pens','$deduct','[]','$netpay','$mon','$yr')"); $no++;
				}
			}
			else{
				if($bpay>0){
					$addn_minus_tax = (($bene+$taxb)>$minallow) ? 0:$bene+$taxb; $cuts=[];
					$addn_with_tax = (($bene+$taxb)>$minallow) ? $bene+$taxb:0; 
					foreach($def as $cut=>$one){ $dct=getcut($cut,$gross,$etp); if($dct){ $cuts[$cut]=$dct; }}
					$hsng = getItem($cuts,"Housing"); $nhif=($nhs[$sid]) ? getcut("NHIF",$bpay+$addn_with_tax,$etp):0; 
					$hsng = ($hsng>0 && $hsng<300) ? 300:$hsng; $hsng=($etp=="consultant") ? 0:$hsng; $tval=$hsng+$nhif; 
					$taxable=($bpay-($nssf+$tval))+$addn_with_tax; $paye=getpaye($taxable,$etp); 
					// $paye = ($paye>0) ? $paye-round($tval*0.15):0; $paye=($paye>0) ? $paye:0; 
					$netpay = ($taxable+$addn_minus_tax)-($paye+$deduct+$pens+$helb+$nita+(array_sum($cuts)-$hsng)); 
					$netpay+=(($bnus+$allow)-$taxb); $jct=json_encode($cuts,1);
					
					if(in_array($sid,$saved)){
						$db->execute(3,"UPDATE `payroll$cid` SET `basicpay`='$bpay',`allowance`='$allow',`benefits`='$bene',`grosspay`='$gross',`taxablepay`='$taxable',`helb`='$helb',
						`paye`='$paye',`nssf`='$nssf',`nhif`='$nhif',`nita`='$nita',`pension`='$pens',`deductions`='$deduct',`cuts`='$jct',`netpay`='$netpay' WHERE `staff`='$sid' AND `month`='$mon'"); 
						$db->execute(3,"UPDATE `payslips$cid` SET `amount`='$netpay' WHERE `staff`='$sid' AND `month`='$mon'"); $no++;
					}
					else{
						$db->execute(3,"INSERT INTO `payroll$cid` VALUES(NULL,'$sid','$bpay','$allow','$bene','$gross','$taxable','$paye','$nssf','$nhif','$helb','$nita','$pens','$deduct',
						'$jct','$netpay','$mon','$yr')"); $no++;
					}
				}
			}
		}
		
		if($no){
			$tx = (count($saved)) ? "Updated":"Created"; $tot=($no==1) ? "1 Staff":"$no Staffs";
			$txt = "$tx payroll of $tot for month of ".date("F Y",$mon); savelog($stid,$txt);
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# include staff to payroll
	if(isset($_POST['incmon'])){
		$mon = trim($_POST['incmon']);
		$uid = trim($_POST['stid']);
		$yr = date("Y",$mon); 
		
		$cols = $db->tableFields(3,"payroll$cid"); $ins=[];
		$def = array("id"=>"NULL","staff"=>$uid,"month"=>$mon,"year"=>$yr,"cuts"=>"[]");
		foreach($cols as $col){
			if(isset($def[$col])){ $ins[]=($col=="id") ? "NULL":"'$def[$col]'"; }else{ $ins[]="'0'"; }
		}
		
		if($db->execute(3,"INSERT INTO `payroll$cid` VALUES (".implode(",",$ins).")")){
			$chk = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon'");
			if($chk){
				$bnk=$cd=$brn=$acc="";
				$sql = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `staff`='$uid' AND NOT `bank`=''");
				if($sql){ $row=$sql[0]; $bnk=$row['bank']; $cd=$row['bankcode']; $brn=$row['branch']; $acc=$row['account']; }
				$db->execute(3,"INSERT INTO `payslips$cid` VALUES(NULL,'$uid','$bnk','$cd','$brn','$acc','0','0','$mon','$yr')"); 
			}
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# confirm deduction
	if(isset($_POST['distate'])){
		$did = trim($_POST['cdid']);
		$act = trim($_POST['distate']);
		
		if($db->execute(3,"UPDATE `deductions$cid` SET `status`='$act' WHERE `id`='$did'")){
			$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `id`='$did'"); $uid=$res[0]['staff']; $mon=$res[0]['month'];
			$db->execute(3,"UPDATE `advances$cid` SET `status`='$act' WHERE `status`='200' AND `month`='$mon' AND `staff`='$uid'");
			if($act>200){
				$user=staffInfo($uid); $bran=$user['branch']; $amnt=$res[0]['amount']; $tid = getransid();
				$desc = "Salary advance for ".ucwords($user['name'])." in ".date("F Y",$mon);
				doublepost([$bran,16,$amnt,$desc,$tid,$stid],[$bran,14,$amnt,$desc,$tid,$stid],'',$act,"auto");
			}
			
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# post payroll to accounts
	if(isset($_POST["postpay"])){
		$mon = trim($_POST["postpay"]);
		$liabs = $_POST["liabacc"];
		$expes = $_POST["expacc"]; 
		$deds=$sets=[]; $error="";
		
		foreach($liabs as $cat=>$acc){
			if($acc==0){ $error = "Error: You havent selected Liability account for ".ucwords(prepare($cat)); break; }
			if($expes[$cat]==0){ $error = "Error: You havent selected Expense account for ".ucwords(prepare($cat)); break; }
		}
		
		if($error){ echo $error; }
		else{
			$sql = $db->query(2,"SELECT `id`,`name`,`branch`,`config` FROM `org$cid"."_staff`");
			foreach($sql as $row){ $dnf=json_decode($row["config"],1); $user[$row["id"]]=$row; $lcas[$row["id"]]=(isset($dnf["lca"])) ? $dnf["lca"]:0; }
			
			$qri = $db->query(3,"SELECT `category`,`staff`,SUM(amount) AS tsum FROM `deductions$cid` WHERE `month`='$mon' AND `status`>200 GROUP BY `staff`,`category`"); 
			if($qri){
				foreach($qri as $row){
					$ded = $row["category"]; $uid=$row["staff"]; $sum=$row["tsum"]; 
					if($sum>0){ $deds[$uid][$ded]=$sum; }
				}
			}
			
			$tym = monrange(date("m",$mon),date("Y",$mon))[1]; $advrule=getrule("advances"); $tym+=rand(10000,90000);
			$skip = array("id","staff","basicpay","allowance","benefits","grosspay","taxablepay","deductions","month","year");
			
			$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
			foreach($qri as $row){
				$uid = $row["staff"]; $list=[];
				foreach($skip as $col){ unset($row[$col]); }
				foreach($row as $col=>$val){
					if(isJson($val)){
						foreach(json_decode($val,1) as $key=>$vl){
							if($vl>0){ $list[$key]=$vl; }
						}
					}
					else{
						if($val>0){ $list[$col]=$val; }
					}
				}
				
				$all = (isset($deds[$uid])) ? $list+$deds[$uid]:$list; $tid=getransid();
				foreach($all as $col=>$amnt){
					if($col=="Salary advance"){
						$cred = $advrule["debit"]; $deb=$expes["netpay"]; $bran=$user[$uid]["branch"]; 
						$desc = "Salary Advance for $name for the month of ".date("F Y",$mon); $name=ucwords($user[$uid]["name"]); 
						doublepost([$bran,$cred,$amnt,$desc,$tid,$suid],[$bran,$deb,$amnt,$desc,$tid,$suid],'',$tym,"default");
					}
					elseif(trim(substr($col,0,11))=="Staff Loan"){
						$lid = trim(substr($col,11)); $wid=$uid;
						if($lid){ $ltp = (substr($lid,2,1)=="C") ? "client":"staff"; $wid=($ltp=="client") ? $lcas[$uid]:$uid; }
						else{
							$sql = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `stid`='$uid' AND `balance`>0");
							$lid = ($sql) ? $sql[0]["loan"]:0; $ltp="staff"; 
						}
						
						$name = ucwords($user[$uid]["name"]); $desc="$name Loan deduction from ".date("F Y",$mon)." Salary"; $bran=$user[$uid]["branch"]; 
						$acc = $liabs["netpay"]; doublepost([$bran,$acc,$amnt,$desc,$tid,$suid],[$bran,$expes["netpay"],$amnt,$desc,$tid,$suid],'',$tym,"auto");
						if($res=updateWallet($wid,$amnt,$ltp,["desc"=>$desc,"revs"=>array(["tbl"=>"trans","tid"=>"smtid"],["tbl"=>"trans","tid"=>$tid])],$suid,time(),1)){
							$day = strtotime(date("Y-M-d")); $mn=strtotime(date("Y-M")); $yr=date("Y"); bookbal($acc,"-$amnt:$mn",$bran); $now=time();
							$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$res','$bran','liability','$acc','$amnt','debit','$desc','$tid','','$suid','auto','$mn','$day','$now','$yr')");
							if($lid){ payFromWallet($wid,$amnt,$ltp,"Loan #$lid repayment",$suid,0,1); }
							logtrans($res,$desc,$suid);
						}
					}
					else{
						$cred = $liabs[$col]; $deb=$expes[$col]; $bran=$user[$uid]["branch"]; $name=ucwords($user[$uid]["name"]); 
						$ptp = ($col=="netpay") ? "Salary Payment":ucwords($col)." Contribution"; $desc="$name $ptp for the month of ".date("F Y",$mon);
						doublepost([$bran,$cred,$amnt,$desc,$tid,$suid],[$bran,$deb,$amnt,$desc,$tid,$suid],'',$tym,"default");
					}
				}
			}
			
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='postedpayroll' OR `setting`='payroll_accounts')");
			if($qri){
				foreach($qri as $row){ $sets[$row["setting"]]=json_decode($row["value"],1); }
			}
			
			$save = (isset($sets["postedpayroll"])) ? $sets["postedpayroll"]:[]; $save[$mon]=$liabs["netpay"]; 
			$hist = json_encode(["liabs"=>$liabs,"expes"=>$expes],1); $jsn=json_encode($save,1);
			foreach(["postedpayroll"=>$jsn,"payroll_accounts"=>$hist] as $set=>$val){
				$query = (isset($sets[$set])) ? "UPDATE `settings` SET `value`='$val' WHERE `client`='$cid' AND `setting`='$set'":"INSERT INTO `settings` VALUES(NULL,'$cid','$set','$val')";
				$db->execute(1,$query); 
			}
			
			savelog($suid,"Posted payroll deductions for ".date("F Y",$mon)." to respective accounts");
			echo "success";
		}
	}
	
	# save allowances/bonus record
	if(isset($_POST['bonmon'])){
		$mon = strtotime(trim($_POST['bonmon']));
		$did = trim($_POST['bnid']);
		$cat = clean($_POST['boncat']);
		$tax = clean($_POST['taxtp']);
		$name = clean(strtolower($_POST['boname']));
		$amnt = intval($_POST['bonamt']);
		$stid = trim($_POST['staff']);
		$yr = date("Y",$mon); $tym=time();
		$tmon = strtotime(date("Y-M")); 
		
		$usa = ($stid) ? staffInfo($stid):[];
		$bran = ($stid) ? $usa['branch']:0;
		$sname = ($stid) ? ucwords($usa['name']):"";
		$upds=$ins=[];
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='postedpayroll'");
		$posted = ($qri) ? json_decode($qri[0]["value"],1):[];
		
		if(isset($posted[$mon])){ echo "Failed: ".date("F Y",$mon)." is locked after payroll was posted to Journal accounts"; }
		elseif($amnt<1){ echo "Error: Amount should be greater than 0"; }
		else{
			if($did){
				$sql = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `id`='$did'");
				$row = $sql[0]; $prv=clean($_POST["prevd"]); $des=json_decode($row['details'],1);
				unset($des[$cat][$prv]); $des[$cat][$name]="$amnt:$tax"; $sum=$taxb=0;
				foreach($des[$cat] as $tp=>$one){
					if($tp!="taxable"){ $am=explode(":",$one)[0]; $sum+=$am; $taxb+=(explode(":",$one)[1]) ? $am:0; }
				}
				
				$des[$cat]["taxable"]=$taxb; $jsn=json_encode($des,1);
				if($db->execute(3,"UPDATE `bonuses$cid` SET `details`='$jsn',`$cat`='$sum' WHERE `id`='$did'")){
					savelog($suid,"Updated $cat of KES $amnt for $sname in month of ".date("F Y",$mon));
					echo "success:$mon";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
			else{
				$cond = ($stid) ? "AND `id`='$stid'":"";
				$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='1' AND NOT `status`='15' $cond ORDER BY `name` ASC");
				foreach($res as $row){
					$pay = json_decode($row['config'],1)['payroll'];
					if(!in_array($row["position"],["assistant","USSD"]) && $pay['include']){
						$chk = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `staff`='".$row['id']."' AND `month`='$mon'");
						$des = ($chk) ? json_decode($chk[0]['details'],1):[]; $des[$cat][$name]="$amnt:$tax"; $sum=$taxb=0;
						foreach($des[$cat] as $tp=>$one){
							if($tp!="taxable"){ $am=explode(":",$one)[0]; $sum+=$am; $taxb+=(explode(":",$one)[1]) ? $am:0; }
						}
						
						$des[$cat]["taxable"]=$taxb; $uid=$row['id']; $jsn=json_encode($des,1); $rid=($chk) ? $chk[0]['id']:0;
						if($rid){ $upds[]="UPDATE `bonuses$cid` SET `details`='$jsn',`$cat`='$sum' WHERE `id`='$rid'"; }
						else{
							$bon=($cat=="bonus") ? $sum:0; $allow=($cat=="allowance") ? $sum:0; 
							$ins[]="(NULL,'$uid','$mon','$bon','$allow','$jsn','$yr','200','$tym')";
						}
					}
				}
				
				foreach($upds as $qry){ $db->execute(3,$qry); }
				foreach(array_chunk($ins,50) as $chunk){
					$db->execute(3,"INSERT INTO `bonuses$cid` VALUES ".implode(",",$chunk));
				}
				
				$sname = ($stid) ? "staff $sname":"all staffs";
				savelog($suid,"Created $cat of KES $amnt for $sname in month of ".date("F Y",$mon));
				echo "success:$mon";
			}
		}
	}
	
	# delete bonus/allowance
	if(isset($_POST["delbonus"])){
		$bid = trim($_POST["delbonus"]);
		$cat = trim($_POST["addn"]);
		
		if($cat=="all"){
			if($db->execute(3,"DELETE FROM `bonuses$cid` WHERE `id`='$bid'")){
				echo "success";
			}
			else{ echo "Failed to complete the request"; }
		}
		else{
			$def = explode("~",base64_decode($cat)); $cat=$dtp=($def[0]=="bn") ? "bonus":"allowance"; 
			$sql = $db->query(3,"SELECT *FROM `bonuses$cid` WHERE `id`='$bid'");
			$row = $sql[0]; $des=json_decode($row['details'],1); $rep=$def[1]; unset($des[$cat][$rep]); $sum=$taxb=0;
			foreach($des[$cat] as $tp=>$one){
				if($tp!="taxable"){ $am=explode(":",$one)[0]; $sum+=$am; $taxb+=(explode(":",$one)[1]) ? $am:0; }
			}
			
			$des[$cat]["taxable"]=$taxb; $jsn=json_encode($des,1);
			if($db->execute(3,"UPDATE `bonuses$cid` SET `details`='$jsn',`$cat`='$sum' WHERE `id`='$bid'")){
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	ob_end_flush();
?>