<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc']) && !isset($_POST['syspost'])){ exit(); }
	$sid = (isset($_POST['syspost'])) ? intval($_POST['syspost']):substr(hexdec($_SESSION['myacc']),6);
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
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
		foreach($sql as $row){
			$terms=json_decode($row['breakdown'],1); $id=$row['id'];
			foreach($terms as $py=>$am){ $pays[$py]=$py; }
			if($inst==$id or $inst==0){ $terms[$name]=$amnt; $data[$id]=json_encode($terms,1); $sums[$id]=$amnt; }
		}
		
		if(in_array($name,$pays)){ echo "Failed: Payment description is already set"; }
		elseif($amnt<1){ echo "Failed: Invalid amount"; }
		else{
			$tot = array_sum($sums); $txt=($inst) ? "All loan installments":"One Loan installment";
			if($db->execute(2,"UPDATE `org$cid"."_loans` SET `balance`=(balance+$tot) WHERE `loan`='$lid'")){
				foreach($data as $id=>$jsn){
					$db->execute(2,"UPDATE `org$cid"."_schedule` SET `balance`=(balance+$sums[$id]),`breakdown`='$jsn' WHERE `id`='$id'");
				}
				
				$pay = ucwords(str_replace("_"," ",$name)); $tbal=array_sum(getLoanBals($lid,0));
				logtrans($lid,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$tot,"bal"=>$tbal),1),0);
				savelog($sid,"Added Loan charge $pay of Ksh $amnt for Client ".$sql[0]['idno']." for $txt");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# roll-over loan
	if(isset($_POST["rolloverln"])){
		$lid = trim($_POST["rolloverln"]);
		$charges = explode(":",trim($_POST["lnfee"]));
		$ext = $charges[0]; $fee=$charges[1]; $intsum=$charges[2]; $dys=[];
		$others = explode(";",rtrim($charges[3],";")); $tdy=strtotime(date("Y-M-d"));
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$bals = getLoanBals($lid); $lamnt=$bals["principal"]; $prod=$qri[0]['loan_product']; $tbal=$qri[0]['balance']+$qri[0]['penalty'];
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'")[0];
		$tmon = strtotime(date("Y-M")); $rate=$lprod["interest"]; $pdef=json_decode($lprod["pdef"],1); 
		$intv=$lprod["intervals"]; $idno=$qri[0]['client_idno']; $ofid=$qri[0]['loan_officer']; $ldur=$qri[0]["duration"];
		
		$mnd = explode("-",$ext)[0]; $add=explode("-",$ext)[1];
		$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='intrcollectype' OR `setting`='payloan_from_day')");
		if($chk){
			foreach($chk as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$intrcol = (isset($setts["intrcollectype"])) ? $setts["intrcollectype"]:"Accrued";
		$dsd = $qri[0]['disbursement']; $pys["rollover_fees"]=$fee; $tsum=$intsum+$fee; $chjs["interest"]=$intsum;
		foreach($others as $one){
			$val = intval(explode("-",$one)[1]); 
			if($val>0){ $pys[explode("-",$one)[0]]=$val; $tsum+=$val; }
		}
		
		if($code=payFromWallet($uid,$tsum,"client","Loan #$lid roll-over charges",$sid,0,1)){
			$pen = (isset($pys["penalties"])) ? $pys["penalties"]:0;
			foreach($pys as $py=>$sum){ if($py!="penalties"){ $chjs[$py]=$sum; }}
			$pid = $db->query(2,"SELECT `id` FROM `org$cid"."_payments` WHERE `code`='$code'")[0]['id'];
			$bjs = json_encode($chjs,1); $pays=$payat=$adds=$days=$vsd=[]; $tpay=array_sum($chjs);
			$slis = array("(NULL,'$idno','$lid','$ofid','$tmon','$tdy','$tpay','$intsum','0','$tpay','$bjs','[]','[]')");
			
			foreach(json_decode($lprod['payterms'],1) as $des=>$pay){
				$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
				if(isset($pdef["limit"]["min"][$val[0]])){ $min=$pdef["limit"]["min"][$val[0]]; $amnt=($amnt<$min) ? $min:$amnt; }
				if($val[0]==2){ $pays[$des]=$amnt; }
				if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
				if($val[0]==5){ $adds[$des]=$amnt; }
			}
			
			if(substr($rate,0,4)=="pvar"){
				$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='$rate'")[0];
				$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
				$intrs = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
				foreach($vars as $key=>$perc){
					$dy = $mnd+($key*86400); $cyc=$ldur/$intv;
					$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
				}
			}
			else{ $intrs = (count(explode("%",$rate))>1) ? round($lamnt*explode("%",$rate)[0]/100):$rate; }
			
			if($pdef["intrtp"]=="FR"){
				$perin=$add; $totpay=$lamnt+($qri[0]['duration']/$lprod['interest_duration']*$intrs); $inst=ceil($totpay/$perin); $tprc=$lamnt; 
				$intr=round(($totpay-$lamnt)/$perin); $princ=$inst-$intr; $tintr=$totpay-$lamnt; $vint=(count($vsd)) ? "varying":$intr;
				$arr = array('principal'=>$princ,"interest"=>$vint); $pinst=ceil($lamnt/$perin);
				for($i=1; $i<=$perin; $i++){
					foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); }
					$dy=($i==1) ? $mnd:$mnd+(($i-1)*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $jps=$pays;
					$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $totpay+=$cut;
					if($intrcol=="Accrued"){ $totpay+=array_sum($pays); $jps=[]; $arr+=$pays; }
					$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); $inst=array_sum($brek);
					if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; $inst=array_sum($brek); }
					$sinst = ($inst>$totpay) ? $totpay:$inst; $totpay-=$sinst; $bjs=json_encode($brek,1); $days[$dy]=$intr;
					$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0; $tpay+=$svint;
					$sjs = ($jps) ? json_encode(["inst"=>$i,"pays"=>$pays],1):json_encode(["inst"=>$i],1);
					$slis[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
				}
			}
			else{
				$perin=$add; $pinst=ceil($lamnt/$perin); $arr=array('principal'=>$pinst,"interest"=>0);
				$calc = reducingBal($lamnt,explode("%",$rate)[0],$perin); 
				for($i=1; $i<=$perin; $i++){
					foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); }
					$dy=($i==1) ? $mnd:$mnd+(($i-1)*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
					$arr['principal']=$calc["principal"][$i]; $intr=$calc["interest"][$i]; $days[$dy]=$intr; $jps=$pays;
					if($intrcol=="Accrued"){ $totpay+=array_sum($pays); $jps=[]; $arr+=$pays; }
					$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); 
					$brek["interest"]=$intr; $inst=array_sum($brek); $sinst=$inst; $bjs=json_encode($brek,1); 
					$sjs = ($jps) ? json_encode(["inst"=>$i,"pays"=>$pays],1):json_encode(["inst"=>$i],1);
					$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0; $tpay+=$svint;
					$slis[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
				}
			}
			
			if($con = $db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN"); $tym=time();
				$con->query("SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid' FOR UPDATE");
				$con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' FOR UPDATE");
				$con->query("DELETE FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
				$con->query("INSERT INTO `org$cid"."_schedule` VALUES ".implode(",",$slis));
				$con->query("UPDATE `org$cid"."_loans` SET `balance`='$tpay',`penalty`='$pen' WHERE `loan`='$lid'");
				$con->commit(); $con->close();
				
				foreach($pys as $py=>$sum){
					$pay = ucwords(str_replace("_"," ",$py)); $tbal+=$sum;
					logtrans($lid,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$sum,"bal"=>$tbal),1),0);
				}
				
				$cut = ($pen>0) ? array(array(["penalties"=>$pen]),$tsum-$pen):$tsum;
				$res = makepay($idno,$pid,$cut,"client",$lid); recon_daily_pays($lid);
				if($intrcol=="Cash"){ setInterest($lid); }
				if($res && $pen>0){ $db->execute(2,"UPDATE `org$cid"."_loans` SET `penalty`='0' WHERE `loan`='$lid'"); }
				savelog($sid,"Applied Loan #$lid roll-over for ".ucwords($qri[0]["client"]));
				echo "success";
			}
			else{
				updateWallet($uid,$tsum,"client",["desc"=>"Loan #$lid roll-over charges Reversal","revs"=>array(["tbl"=>"norev"])],$sid);
				$db->execute(2,"DELETE FROM `org$cid"."_payments` WHERE `id`='$pid'");
				echo "Failed: System error!"; 
			}
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# offset loan
	if(isset($_POST["offsetln"])){
		$lid = trim($_POST["offsetln"]);
		$charges = explode(":",trim($_POST["lnfee"]));
		$fee = $charges[0]; $amnt=$charges[1]; $pen=$charges[2];
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
		foreach($sql as $row){ unset($row['payments']); $idno=$row['idno']; $ids[]=$row; }
		
		$bals = getLoanBals($lid); $princ=$bals["principal"]-$amnt; $prod=$qri[0]['loan_product'];
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'")[0];
		$tmon = strtotime(date("Y-M")); $rate=$lprod["interest"]; $pdef=json_decode($lprod["pdef"],1);
		$tbal = $qri[0]['balance']+$qri[0]['penalty'];
		
		$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='intrcollectype' OR `setting`='payloan_from_day')");
		if($chk){
			foreach($chk as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$pfrm = (isset($setts["payloan_from_day"])) ? round(86400*$setts["payloan_from_day"]):0;
		$intrcol = (isset($setts["intrcollectype"])) ? $setts["intrcollectype"]:"Accrued"; $chjs["principal"]=$amnt;
		$def = array("penalties"=>$pen,"offset_fees"=>$fee); $dsd=$qri[0]['disbursement']; $tsum=$amnt; 
		foreach($def as $py=>$sum){ if($sum>0){ $pays[$py]=$sum; $tsum+=$sum; }}
		
		if($code=payFromWallet($uid,$tsum,"client","Loan #$lid Offset withdrawal",$sid,0,1)){
			foreach($pays as $py=>$sum){ if($py!="penalties"){ $chjs[$py]=$sum; }}
			$pid = $db->query(2,"SELECT `id` FROM `org$cid"."_payments` WHERE `code`='$code'")[0]['id'];
			$bjs = json_encode($chjs,1); $ofid=$ids[0]['officer']; $tdy=strtotime(date("Y-M-d")); $tpay=array_sum($chjs);
			$db->execute(2,"INSERT INTO `org$cid"."_schedule` VALUES(NULL,'$idno','$lid','$ofid','$tmon','$tdy','$tpay','0','0','$tpay','$bjs','[]','[]')");
			
			if($pdef["intrtp"]=="FR"){
				$intrs=(count(explode("%",$rate))>1) ? round($princ*explode("%",$rate)[0]/100):$rate; $perin=count($ids);
				$totpay=$princ+($qri[0]['duration']/$lprod['interest_duration']*$intrs); $inst=ceil($totpay/$perin); 
				$intr=round(($totpay-$princ)/$perin); $tprc=$inst-$intr; $tpr=$princ;
				foreach($ids as $one){
					$brek=json_decode($one["breakdown"],1); $prc=($tprc>$tpr) ? $tpr:$tprc; $tpr-=$prc; $rid=$one["id"];
					$brek["principal"]=$prc; $brek["interest"]=$intr; $inst=array_sum($brek); $jsn=json_encode($brek,1); 
					$inst-=($intrcol=="Cash") ? $intr:0; $sint=($intrcol=="Cash") ? 0:$intr; $bal=$inst-$one["paid"]; $days[$one["day"]]=$intr; $tpay+=$bal;
					$db->execute(2,"UPDATE `org$cid"."_schedule` SET `amount`='$inst',`interest`='$sint',`balance`='$bal',`breakdown`='$jsn' WHERE `id`='$rid'");
				}
			}
			else{
				$calc = reducingBal($princ,explode("%",$rate)[0],count($ids));
				foreach($ids as $i=>$one){
					$brek=json_decode($one["breakdown"],1); $i++; $prc=$calc["principal"][$i]; $intr=$calc["interest"][$i]; 
					$brek["principal"]=$prc; $brek["interest"]=$intr; $inst=array_sum($brek); $jsn=json_encode($brek,1); $rid=$one["id"];
					$inst-=($intrcol=="Cash") ? $intr:0; $sint=($intrcol=="Cash") ? 0:$intr; $bal=$inst-$one["paid"]; $days[$one["day"]]=$intr; $tpay+=$bal;
					$db->execute(2,"UPDATE `org$cid"."_schedule` SET `amount`='$inst',`interest`='$sint',`balance`='$bal',`breakdown`='$jsn' WHERE `id`='$rid'");
				}
			}
			
			foreach($pays as $py=>$sum){
				$pay = ucwords(str_replace("_"," ",$py)); $tbal+=$sum;
				logtrans($lid,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$sum,"bal"=>$tbal),1),0);
			}
			
			foreach($bals as $py=>$sum){
				if($py!="principal" && $sum>0){
					$pay = ucwords(str_replace("_"," ",$py)); $tbal-=$sum;
					logtrans($lid,json_encode(array("desc"=>"$pay charges rollback","type"=>"credit","amount"=>$sum,"bal"=>$tbal),1),0);
				}
			}
			
			$db->execute(2,"UPDATE `org$cid"."_loans` SET `balance`='$tpay' WHERE `loan`='$lid'");
			$cut = ($pen) ? array(array(["penalties"=>$pen]),$amnt-$pen):$amnt;
			$res = makepay($idno,$pid,$cut,"client",$lid); recon_daily_pays($lid);
			if($res && $pen>0){ $db->execute(2,"UPDATE `org$cid"."_loans` SET `penalty`='0' WHERE `loan`='$lid'"); }
			if($intrcol=="Cash"){ setInterest($lid); }
			savelog($sid,"Applied Loan #$lid principal offset of Ksh $amnt for ".ucwords($qri[0]["client"]));
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# close Loan
	if(isset($_POST["closeln"])){
		$lid = trim($_POST["closeln"]);
		$fee = intval($_POST["lnfee"]);
		$sql = $db->query(2,"SELECT idno,SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
		$bal = $sql[0]['tbal']; $idno=$sql[0]['idno']; $tbal=$bal+$fee;
		
		$qri = $db->query(2,"SELECT `id`,`name` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0];
		$chk = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='".$qri['id']."' AND `type`='client'");
		$uid = $qri["id"]; $cname=ucwords($qri["name"]); $wid=$chk[0]['id'];
		
		if(walletBal($wid)<$tbal){ echo "Failed: Insufficient Balance to clear loan"; }
		else{
			if($code=payFromWallet($uid,$tbal,"client","Payment to Close Loan $lid",$sid,0,1)){
				$pid = $db->query(2,"SELECT `id` FROM `org$cid"."_payments` WHERE `code`='$code'")[0]['id'];
				logtrans($lid,json_encode(array("desc"=>"Penalties charges applied","type"=>"debit","amount"=>$fee,"bal"=>$tbal),1),0);
				makepay($idno,$pid,array(array(["penalties"=>$fee,"closeln"=>0]),$bal),"client",$lid);
				savelog($sid,"Closed Loan #$lid for $cname");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# deactivate Loan
	if(isset($_POST["deactln"])){
		$lid = trim($_POST["deactln"]);
		$val = intval($_POST["lnst"]);
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$des = json_decode($sql[0]['clientdes'],1); $arr=(is_array($des)) ? $des:[]; 
		$arr["loanst"]=($val) ? 0:1; $jsn=json_encode($arr,1); $cname=ucwords($sql[0]['client']);
		
		if($db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`='$jsn' WHERE `loan`='$lid'")){
			$txt = ($val) ? "Activated":"Deactivated";
			savelog($sid,"$txt Loan $lid for $cname");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# tag loan
	if(isset($_POST['tgloan'])){
		$lid = trim($_POST['tgloan']);
		$tag = trim($_POST['ltag']);
		$com = clean($_POST['tresn']);
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$des = json_decode($sql[0]['clientdes'],1); $des['rating']=$tag; $jds=json_encode($des,1); $idno=$sql[0]['client_idno'];
		$clid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id']; $tym=time();
		
		if($db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`='$jds' WHERE `loan`='$lid'")){
			$db->execute(2,"INSERT INTO `interactions$cid` VALUES(NULL,'$clid','Tagged Loan as $tag, Reason: $com','$sid','$tym')");
			savelog($sid,"Tagged ".$sql[0]['client']." loan as $tag, Reason: ($com)");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# assign loan to agent
	if(isset($_POST["assignln"])){
		$lid = trim($_POST["assignln"]);
		$uid = trim($_POST["agent"]);
		
		$sql = $db->query(2,"SELECT `clientdes`,`client` FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		if($sql){
			$des = json_decode($sql[0]["clientdes"],1); $des["agent"]=$uid; $jsn=json_encode($des,1);
			if($db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`='$jsn' WHERE `loan`='$lid'")){
				$cname = $db->query(2,"SELECT `name` FROM `org$cid"."_staff` WHERE `id`='$uid'")[0]['name'];
				savelog($sid,"Assigned client ".ucwords($sql[0]['client'])." to collection agent ".ucwords($cname));
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment!"; }
		}
		else{ echo "Failed: Loan not found!"; }
	}
	
	# delink loan from agent
	if(isset($_POST["delinkagent"])){
		$lid = trim($_POST["delinkagent"]);
		$sql = $db->query(2,"SELECT `clientdes`,`client` FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		if($sql){
			$des = json_decode($sql[0]["clientdes"],1); $uid=$des["agent"]; unset($des["agent"]); $jsn=json_encode($des,1);
			if($db->execute(2,"UPDATE `org$cid"."_loans` SET `clientdes`='$jsn' WHERE `loan`='$lid'")){
				$cname = $db->query(2,"SELECT `name` FROM `org$cid"."_staff` WHERE `id`='$uid'")[0]['name'];
				savelog($sid,"Delinked client ".ucwords($sql[0]['client'])." from collection agent ".ucwords($cname));
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment!"; }
		}
		else{ echo "Failed: Loan not found!"; }
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
				$sql = $con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' ORDER BY `day` ASC");
				while($row=$sql->fetch_assoc()){
					$brk = json_decode($row['breakdown'],1); $pays[$row['id']]=$brk; $idno=$row['idno']; 
					foreach(json_decode($row['payments'],1) as $pid=>$des){ $paid[]=explode(":",$pid)[0]; }
				}
				
				$qri = $con->query("SELECT *FROM `org$cid"."_payments` WHERE `status`='$lid'");
				if($qri->num_rows){
					while($row=$qri->fetch_assoc()){
						if(in_array($row['id'],$paid)){ reversepay("$idno:$lid",$row['code']); $revs[$row['id']]=$row['amount']; }
					}
				}
				
				$con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' ORDER BY `day` ASC FOR UPDATE");
				$nintr=$tint=intval($intr); $pint=ceil($nintr/count($pays));
				foreach($pays as $rid=>$one){
					$prc=$one['principal']; $intrs=($nintr>$pint) ? $pint:$nintr; $one['interest']=$intrs; 
					$inst=array_sum($one); $nbrk=json_encode($one,1); $tsum+=$inst; $nintr-=$pint;
					$qrys[]="UPDATE `org$cid"."_schedule` SET `interest`='$intrs',`amount`='$inst',`paid`='0',`balance`='$inst',`breakdown`='$nbrk',`payments`='[]' WHERE `id`='$rid'";
				}
				
				$con->query("SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid' FOR UPDATE");
				if($con->query("UPDATE `org$cid"."_loans` SET `balance`='$tsum',`paid`='0',`status`='0' WHERE `loan`='$lid'")){
					if($db->istable(2,"varying_interest$cid")){ $con->query("DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'"); }
					foreach($qrys as $qry){ $con->query($qry); } $con->commit(); $con->close();
					foreach($revs as $pid=>$amnt){ makepay($idno,$pid,$amnt); }
					savelog($sid,"Waived loan interest for client $idno to $tint");
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; $con->commit(); $con->close(); }
			}
			else{ echo "Failed due to system Error!"; }
		}
	}
	
	# reschedule loan 
	if(isset($_POST['maxto'])){
		$mto = trim($_POST['maxto']);
		$lid = trim($_POST['loan']);
		$fee=$intr = trim($_POST['fees']);
		$fro = strtotime(trim($_POST['rfrom']));
		$len = trim($_POST['dur']);
		$to = $fro+($len*86400); $tym=time();
		$from=date("d-m-Y",$fro); $dto=date("d-m-Y",$to);
		
		if($mto<$to){ echo "Failed: Maturity of loan will be on ".date("d-m-Y",$to)." exceeding Maximim set limit to ".date("d-m-Y",$mto); }
		else{
			$qry = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan`='$lid'"); $row=$qry[0];
			$bal=$row['balance']+$row['penalty']+$fee; $idno=$row['client_idno']; $ofid=$row['loan_officer']; $prod=$row['loan_product'];
			$res = $db->query(1,"SELECT intervals FROM `loan_products` WHERE `id`='".$qry[0]['loan_product']."'"); $dur=$len/$res[0]['intervals'];
			$qri = $db->query(2,"SELECT *FROM `org".$cid."_schedule` WHERE `loan`='$lid' AND `balance`>0");
			foreach($qri as $row){
				$rem=($row['balance']>$row['interest']) ? $row['interest']:$row['balance']; $intr+=$rem; $pys=$row['breakdown'];
			}
			
			$clid = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `idno`='$idno'")[0]['id'];
			$pays = json_decode($pys,1); $pers=round($intr/$dur); $princ=round(($bal-$intr)/$dur); $inst=$pers+$princ; $tbal=$inst*$dur;
			$rem = array_sum($pays)-$pays['interest']-$pays['principal']; $pays['interest']=$pers; $pays['principal']=$princ-$rem;
			
			$brek=json_encode($pays,1); $qrys=[]; 
			for($i=0; $i<$dur; $i++){
				$dy=$fro+($i*$res[0]['intervals']*86400); $mon=strtotime(date("Y-M",$dy)); $sjs=json_encode(["inst"=>$i+1],1);
				$qrys[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$inst','$pers','0','$inst','$brek','[]','$sjs')";
			}
			
			if($db->execute(2,"INSERT INTO `rescheduled$cid` VALUES(NULL,'$clid','$lid','$tbal','$fee','$intr','$len','$fro','$tym')")){
				$db->execute(2,"UPDATE `org".$cid."_loans` SET `balance`=(balance+$fee),`expiry`='$to',`penalty`='0' WHERE `loan`='$lid'");
				$db->execute(2,"DELETE FROM `org".$cid."_schedule` WHERE `loan`='$lid' AND `paid`='0'");
				$db->execute(2,"UPDATE `org".$cid."_schedule` SET `paid`=amount,`balance`='0' WHERE `loan`='$lid' AND `paid`>0 AND `balance`>0");
				$db->execute(2,"INSERT INTO `org".$cid."_schedule` VALUES ".implode(",",$qrys));
				if($db->istable(2,"daily_interests$cid")){
					$ncd = json_encode(daily_interest($lid,$prod),1); $now=time();
					$db->execute(2,"DELETE FROM `daily_interests$cid` WHERE `loan`='$lid'");
					$db->execute(2,"INSERT INTO `daily_interests$cid` VALUES(NULL,'$lid','$ncd','$now')");
				}
				
				setTarget($ofid,strtotime(date("Y-M")),["arrears","performing","loanbook","income"]);
				bookbal(DEF_ACCS['interest'],"+$fee"); bookbal(getrule('interest')['debit'],"+$fee:".strtotime(date("Y-M",$fro)));
				savelog($sid,"Rescheduled Loan for ".$qry[0]['client']." (".$qry[0]['client_idno'].") to pay KES $tbal from $from to $dto");
				echo "success";
			}
			else{ echo "Failed to reschedule Loan! Try again later"; }
		}
	}
	
	# approve loans
	if(isset($_POST['approveloans'])){
		$otp = trim($_POST['otpv']);
		$val = $_POST['nval'];
		$rids = $_POST['ploans']; 
		$otps = (isset($_POST["gotpv"])) ? $_POST["gotpv"]:[];
		$tgcl = (isset($_POST["tgfon"])) ? trim($_POST["tgfon"]):0;
	
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact']; 
		$myname = prepare(ucwords($me['name'])); $fons[]=$fon; $from=$val-1; $no=0; $nst=$val+1; 
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$com = (isset($_POST['appcom'])) ? clean($_POST['appcom']):"Ok"; 
				$tbl = "org".$cid."_loantemplates"; $cols=$db->tableFields(2,$tbl);
				if(!in_array("comments",$cols)){ $db->execute(2,"ALTER TABLE `$tbl` ADD `comments` TEXT NOT NULL AFTER `approvals`"); }
				$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
				$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='loan_approval_sms' OR `setting`='threshold_approver')");
				$levels = ($qri) ? json_decode($qri[0]['levels'],1):[]; $dcd=count($levels); $sets=$skip=[];
				if($sql){
					foreach($sql as $rw){ $sets[$rw["setting"]]=prepare($rw["value"]); }
				}
				
				$thapp = (isset($sets["threshold_approver"])) ? $sets["threshold_approver"]:"";
				$approvals=$states=$comms=$allst=$gcols=$save=$tots=$lids=$prfs=$ntos=[]; $total=0; $tym=time(); $error="";
				foreach($rids as $id){ $lids[]="'$id'"; }
				
				$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE `id` IN (".implode(",",$lids).")");
				if($sql){
					foreach($sql as $row){
						$id=$row['id']; $allst[$id]=$row['status']; $sum=$row['amount']; $tots[]=$sum; 
						$names[$id]=prepare(ucwords($row['client'])); $prfs[$id]=$row["pref"];
						if($tgcl){ $gcols[$id]=$row[$tgcl]; }
						if($thapp){
							$ldf=explode("%",$thapp); $pos=$ldf[0]; $mbp=$ldf[1];
							if(in_array($pos,$levels) && isset($levels[$nst])){
								if($levels[$nst]==$pos && $sum<intval($mbp)){ $skip[$id]=$nst; }
							}
						}
						
						if($row['status']==$from){
							$arr=($row['approvals']==null) ? []:json_decode($row['approvals'],1); 
							$comms[$id]=($row['comments']==null) ? []:json_decode($row['comments'],1);
							$approvals[$id]=$arr; $sums[$id]=($sum-$row['checkoff']-$row['prepay']);
						}
					}
				}
				
				foreach($rids as $rid){
					if(isset($allst[$rid])){
						$otpck = 1;
						if($gcols){
							$otp=(isset($otps[$rid])) ? $otps[$rid]:0; $cont=intval(ltrim($gcols[$rid],"254")); $fons[]=$cont;
							$chk = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$cont' AND `otp`='$otp'");
							if(!$chk){ $error="Incorrect Guarantor OTP for ".$names[$rid]; $otpck=0; break; }
							elseif($chk[0]['expiry']<time()){ $error="Failed: Guarantor OTP for $names[$rid] expired at ".date("d-m-Y, h:i A",$chk[0]['expiry']); $otpck=0; break; }
						}
						
						if($allst[$rid]<8 && $allst[$rid]<$val && $otpck){
							$new = (isset($approvals[$rid])) ? $approvals[$rid]:[]; $new[]=$sid; $total+=$sums[$rid]; 
							$ncom = (isset($comms[$rid])) ? $comms[$rid]:[]; $ncom[$sid]=$com; 
							$app = json_encode($new,1); $states[]=count($new); $jsn=json_encode($ncom,1); 
							$svl = (isset($skip[$rid])) ? $nst:$val; $sval=($svl==$dcd) ? 8:$svl; $no++;
							$save[] = "UPDATE `$tbl` SET `status`='$sval',`approvals`='$app',`comments`='$jsn',`pref`='$tym' WHERE `id`='$rid'";
						}
					}
				}
				
				if($error){ echo $error; exit(); }
				if($no){
					foreach($save as $qry){ $db->execute(2,$qry); }
					foreach($fons as $fon){ $db->execute(1,"UPDATE `otps` SET `expiry`='$tym' WHERE `phone`='$fon'"); }
					$appset = (isset($sets["loan_approval_sms"])) ? $sets["loan_approval_sms"]:1;
					$cond = (max($states)==$dcd) ? 1:0; $sum=fnum($total); $rno=$no-count($skip);
					
					if($cond){
						$pnd = (isset($_POST["pndst"])) ? trim($_POST["pndst"]):0;
						$fnd = ($pnd) ? "AND `pref`='8'":"AND NOT `pref`='8'";
						$db->execute(2,"UPDATE `$tbl` SET `status`='8',`pref`='$tym' WHERE `status`='$val' $fnd");
						if($appset){
							$qry = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND `approval`='loantemplate'");
							$notify = ($qry) ? $qry[0]['staff']:1;
							if($notify && $notify!=$sid){
								$nots = (defined("LOANDISBURSE_NOTIFY")) ? LOANDISBURSE_NOTIFY:[$notify];
								foreach($nots as $id){
									$nto = staffInfo($id); $uto=prepare(ucwords($nto['name'])); $all=($no==1) ? "1 Client":"$no Clients";
									$mto = (isset($nto["office_contact"])) ? $nto["office_contact"]:$nto['contact']; $goto = "loans.php?disbursements";
									notify([$id,$mto,$goto],"Hi $uto, Loan application of KES $sum for $all has been approved by $myname now waiting for disbursement");
								}
							}
						}
					}
					else{
						if(defined("LOAN_APPROVALS")){
							if(LOAN_APPROVALS && $tots){ $levels=loanApproval(max($tots),$nst); }
						}
						
						if(isset($levels[$nst]) && $rno>0 && $appset){
							$poz = $levels[$nst]; $pozt=(is_array($poz)) ? $poz:[$poz]; 
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0' AND NOT `id`='$sid'");
							if($res){
								foreach($pozt as $pos){
									$cont=$id=0;
									foreach($res as $row){
										$cnf = json_decode($row["config"],1);
										$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
										
										if(isset($post[$pos])){
											if($post[$pos]=="hq"){
												$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
												$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
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
													$cont=(isset($row["office_contact"])) ? $row["office_contact"]:$row['contact']; 
													$id=$row["id"]; $name=ucwords(prepare($row['name'])); break; 
												}
											}
										}
									}
									if($cont){
										$tot = ($rno==1) ? "1 Client":"$rno Clients"; $goto="loans.php?disbursements";
										notify([$id,$cont,$goto],"Dear $name, $myname has approved loan application for $tot now waiting your approval");
									}
								}
							}
						}
					}
					
					savelog($sid,"Approved Loan application for $no Clients");
					echo "success";
				}
				else{ echo "Failed: No client selected!"; }
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
	}
	
	# release Pended loan 
	if(isset($_POST["pendrel"])){
		$idno = trim($_POST["pendrel"]);
		$comt = clean($_POST["icom"]);
		
		$qri = $db->query(2,"SELECT *FROM `org{$cid}_loantemplates` WHERE `client_idno`='$idno' AND `status`<8 AND `pref`='8'");
		if($qri){
			$coms = json_decode($qri[0]["comments"],1); $uid=array_key_last($coms); $now=time(); $me=staffInfo($sid);
			if($db->execute(2,"UPDATE `org{$cid}_loantemplates` SET `pref`='$now' WHERE `client_idno`='$idno' AND `status`<8 AND `pref`='8'")){
				$clid = $db->query(2,"SELECT `id` FROM `org{$cid}_clients` WHERE `idno`='$idno'")[0]["id"];
				$db->execute(2,"INSERT INTO `interactions$cid` (`id`,`client`,`comment`,`source`,`time`) VALUES(NULL,'$clid','$comt','$sid','$now')");
				
				if($sid!=$uid){
					$user = staffInfo($uid); $cont=$user["contact"]; $name=prepare(ucwords($user["name"])); $cname=prepare(ucwords($qri[0]["client"]));
					notify([$uid,$cont,"loans.php?disbursements"],"Dear $name, ".prepare(ucwords($me["name"]))." has approved pended loan for $cname");
				}
				
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment"; }
		}
		else{ echo "Failed: The client has no pended application"; }
	}
	
	# pend loans
	if(isset($_POST['pendloans'])){
		$data = $_POST['ploans'];
		$otp = trim($_POST['otpv']);
		$com = (isset($_POST['appcom'])) ? clean($_POST['appcom']):"Ok";
		$me = staffInfo($sid); $fon=(isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		$tbl = "org".$cid."_loantemplates";
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$cols = $db->tableFields(2,$tbl);
				if(!in_array("comments",$cols)){ $db->execute(2,"ALTER TABLE `$tbl` ADD `comments` TEXT NOT NULL AFTER `approvals`"); }
			
				foreach($data as $rid){
					$svd = $db->query(2,"SELECT `comments` FROM `$tbl` WHERE `id`='$rid'")[0]['comments'];
					$pcom = ($svd==null) ? []:json_decode($svd,1); $pcom[$sid]=$com; $jsn=json_encode($pcom,1);
					$db->execute(2,"UPDATE `$tbl` SET `pref`='8',`comments`='$jsn' WHERE `id`='$rid'");
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
		$me = staffInfo($sid); $tbl="org".$cid."_loantemplates";
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				foreach($data as $rid){
					$jsn = json_encode([$sid=>$com],1);
					$db->execute(2,"UPDATE `$tbl` SET `status`='9',`approvals`='[]',`comments`='$jsn',`pref`='0' WHERE `id`='$rid'");
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
					$sql = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `id`='$rid'"); $no++;
					$row = $sql[0]; $cut=$row['checkoff']+$row['prepay']; $apam=$row['amount']; $bid=$row['branch'];
					$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $amnt=$damnt[$rid]; 
					$cut+= (isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; $tdsb=array_sum($dsb)+$amnt; $tdsb+=($dsb) ? 0:$cut;
					
					if($row["status"]==8 && $tdsb>$cut && $tdsb<=$apam){
						$tds=($dsb) ? $amnt:$amnt+$cut; $dsb[$day]=$tds; $djsn=json_encode($dsb,1); $lntp=$row["loantype"]; 
						$cname=ucwords($row['client']); $des="Loan for $cname"; $des.=($ref) ? " Ref #$ref":""; $tot+=$amnt; 
						$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `status`='$day',`disbursed`='$djsn' WHERE `id`='$rid'");
						$rev = json_encode(array(["db"=>2,"tbl"=>"org".$cid."_loantemplates","col"=>"id","val"=>$rid,"update"=>"status:8"]),1);
						doublepost([$bid,$acc,$amnt,$des,"CLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"CLOAN$rid",$sid],clean($ds),$day,$rev);
						$book.= ($ref) ? " Ref #$ref":""; $book.=($ds) ? " -".clean($ds):""; $fdes[$rid]=[$cname,$bid,$acc,$day];
						if(explode(":",$lntp)[0]=="topup" or count($dsb)>1){
							$src = (count($dsb)>1) ? $rid:explode(":",$lntp)[1];
							$chk = $db->query(2,"SELECT `loan`,`balance` FROM `org$cid"."_loans` WHERE `tid`='$src'");
							if($chk){
								logtrans($chk[0]['loan'],json_encode(["desc"=>"Loan Topup to $cname via $book","type"=>"debit","amount"=>$amnt,"bal"=>$chk[0]['balance']+$amnt],1),$sid); 
							}
						}
						else{ logtrans("TID$rid","Disbursement to $cname via $book",$sid); }
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
				
				if($tot>0){ savelog($sid,"Confirmed disbursement of $no worth Ksh ".number_format($tot)." Loans from $acname"); }
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
			$chk = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
			if($chk){
				$des = @json_decode($chk[0]['clientdes'],1); $lnst=(isset($des["loanst"])) ? $des["loanst"]:0;
				if($lnst){ echo "Failed: Penalty cant be updated for a Deactivated Loan"; exit(); }
				if($db->execute(2,"UPDATE `org".$cid."_loans` SET `penalty`='$amnt' WHERE `loan`='$lid'")){
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
					
					setTarget($chk[0]["loan_officer"],strtotime(date("Y-M")),["loanbook"]);
					savelog($sid,"Updated penalty amount for client ".$chk[0]["client"]." to $amnt");
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
		$ltbl = "org$cid"."_loantemplates"; $now=time();
		
		if(isset($_POST["syspost"])){
			if($con = $db->mysqlcon(1)){
				$con->autocommit(0); $con->query("BEGIN");
				$chk = $con->query("SELECT *FROM `tempque` WHERE `temp`='CLTMP$cid$tid' FOR UPDATE");
				if($chk){
					$state = $chk->fetch_assoc()["data"];
					if(intval($state)){ exit(); }
					else{ $con->query("UPDATE `tempque` SET `data`='$now' WHERE `temp`='CLTMP$cid$tid'"); }
				}
				$con->commit(); $con->close();
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$tid'"); $row=$res[0]; $lid=$row['loan']; $lst=$row['status']; $prod=$row['loan_product'];
		$day = ($pdy) ? $pdy:strtotime(date("Y-M-d",$lst)); $tym=($pdy) ? strtotime(trim($_POST['pday']).",".date("H:i")):$lst; $phone=$row['phone'];
		$expd = $day+($row['duration']*86400); $lamnt=$apam=$row['amount']; $pid=$row['loan_product']; $idno=$row['client_idno']; $cko=$row['checkoff']; $lntp=$row['loantype'];
		$pcode=$row['payment']; $ldur=$row['duration']; $deducted=json_decode($row['processing'],1); $ofid=$row['loan_officer']; $bran=$row['branch'];
		$desc = "Loan for ".ucwords($row['client']); $name=$row['client']; $deds=(isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $usa=$row['creator'];
		$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $istop=(count($dsb)>1) ? 1:0; $part=sys_constants("partial_disbursement");
		if($part){ $lamnt=(isset($dsb[$lst])) ? $dsb[$lst]:$lamnt; }
		
		if($lid && !$istop){ echo "success"; exit(); }
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$pid'");
		$paydes = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$pcode'");
		$pst = ($paydes) ? $paydes[0]['status']:0; 
		
		if(!$paydes && substr($pcode,0,6)=="WALLET"){
			$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
			$wid = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'")[0]['id'];
			if(walletBal($wid)>=array_sum($deducted)){
				$plt = array_map("ucwords",array_keys($deducted)); $sum=array_sum($deducted);
				if($pcode=payFromWallet($uid,$sum,"client","Payment for ".implode(" & ",$plt),$sid,0,1)){
					$db->execute(2,"UPDATE `$ltbl` SET `payment`='$pcode' WHERE `id`='$tid'");
					$paydes = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$pcode'");
				}
			}
		}
		
		if(!$lprod){ echo "Failed: Attached loan product is not found!"; exit(); }
		if(!$paydes && $pcode){ echo "Failed: Payment code $pcode is not found in payments"; exit(); }
		if($pst && $pcode && !$istop){ echo "Failed: Payment $pcode has already been approved"; exit(); }
		
		$vsd=$setts=$ladds=$cuts=$deps=[];
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='payloan_from_day' OR `setting`='intrcollectype' OR `setting`='use_app_as_topup')");
		if($chk){
			foreach($chk as $rw){ $setts[$rw['setting']]=prepare($rw["value"]); }
		}
		
		$intrcol = (isset($setts["intrcollectype"])) ? $setts["intrcollectype"]:"Accrued";
		$pfrm = (isset($setts["payloan_from_day"])) ? round(86400*$setts["payloan_from_day"]):0; 
		$pdef = json_decode($lprod[0]['pdef'],1); $intrtp=(isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR";
		$pamnt = ($paydes) ? $paydes[0]['amount']:0; $payid=($paydes) ? $paydes[0]['id']:0; $deduct=array_sum($deducted);
		$allowdup = (isset($pdef["allow_multiple"])) ? $pdef["allow_multiple"]:0; $prtp=($lntp=="new") ? "newloans":"repeats";
		$waivd = (isset($pdef["repaywaiver"])) ? $pdef["repaywaiver"]:0; $pfrm=($waivd) ? round(86400*$waivd):$pfrm;
		
		$record = [$prtp,"performing","disbursement","loanbook"];
		if($cko>0){ $record[]="checkoff"; }
		if($lprod[0]["category"]=="app"){ $record[]="apploans"; }
		if($lprod[0]["category"]=="asset"){ $record[]="assetloans"; }
		if(isset($pdef["cluster"])){ $record[]="loanprods-".$pdef["cluster"]; }
		
		$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals']; 
		foreach($payterms as $des=>$pay){
			$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
			if(isset($pdef["limit"]["min"][$val[0]])){ $min=$pdef["limit"]["min"][$val[0]]; $amnt=($amnt<$min) ? $min:$amnt; }
			if($val[0]==4 && !$istop){ $ladds[$des]=$amnt; $cuts[$des]=$amnt; }
			if(in_array($val[0],[2,3,5])){ $ladds[$des]=$amnt; }
			if($val[0]==7){ $deps[$des]=$amnt; }
		}
		
		if(!$istop && $apam>$lamnt){
			foreach($cuts as $des=>$am){ unset($ladds[$des]); }
			foreach($deds as $des=>$am){ $ladds[$des]=$am; }
			$cuts=$deds;
		}
		
		# loan topup
		if(explode(":",$lntp)[0]=="topup" or $istop){
			$src = ($istop) ? $tid:explode(":",$lntp)[1];
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `tid`='$src'");
			if(!$sql){
				$src = (isset(explode(":",$lntp)[1])) ? explode(":",$lntp)[1]:0;
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `tid`='$src'");
				if(!$sql){ echo "Failed: Primary Loan topped up from is not found!"; exit(); }
			}
			
			$roq = $sql[0]; $tsum=$lamnt+$roq['amount']; $lid=$roq["loan"]; 
			$ady = ($istop) ? 0:($ldur*86400); $expd=$roq["expiry"]+$ady; $std=($istop) ? $roq["disbursement"]:0;
			$ncd = loanschedule($tid,$std); $bals=getLoanBals($lid); $tbal=array_sum($bals)+$lamnt; $top=$bals["principal"]+$lamnt;
			$day = strtotime(date("Y-M-d",$roq["disbursement"])); $dsd=$day; 
			
			if($con = $db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN");
				$con->query("SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid' FOR UPDATE");
				$con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' FOR UPDATE");
				foreach($bals as $pay=>$bal){
					if($pay!="principal" && $bal>0){
						$ptx = ucwords(str_replace("_"," ",$pay)); $tbal-=$bal;
						logtrans($lid,json_encode(["desc"=>"$ptx charges rollback","type"=>"credit","amount"=>$bal,"bal"=>$tbal],1),$sid); 
					}
				}
				
				$tpay=$tint=0; $slis=$days=[];
				foreach($ncd["installment"] as $k=>$one){
					$dy=$one["day"]; $mon=strtotime(date("Y-M",$dy)); $svint=$one['install']; $brek=$one["pays"]; 
					$intr=($brek["interest"]=="varying") ? $one["nint"]:$brek["interest"]; $days[$dy]=$intr; 
					$intsv=($intrcol=="Accrued") ? $intr:0; $tpay+=$svint; $tint+=($intrcol=="Accrued") ? $intr:0; $bjs=json_encode($brek,1);
					$sjs = (isset($one["ipays"])) ? json_encode(["inst"=>$k+1,"pays"=>$one["ipays"]],1):json_encode(["inst"=>$k+1],1);
					$slis[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
				}
				
				if(substr($lprod[0]['interest'],0,4)=="pvar"){
					$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
					$vars = json_decode($info['value'],1);
					foreach($vars as $key=>$perc){
						$dy = $day+($key*86400); $cyc=count($days);
						$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($top*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
					}
				}
				
				if($con->query("UPDATE `org$cid"."_loans` SET `balance`='$tpay',`amount`='$tsum',`expiry`='$expd' WHERE `loan`='$lid'")){
					if(array_sum($dsb)<$apam){ $con->query("UPDATE `$ltbl` SET `status`='8',`loan`='0' WHERE `id`='$tid'"); } 
					else{ $con->query("UPDATE `$ltbl` SET `loan`='$lid' WHERE `id`='$tid'"); }
					$con->query("DELETE FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
					$con->query("INSERT INTO `org$cid"."_schedule` VALUES ".implode(",",$slis));
					$con->commit(); $con->close(); $now=time();
					
					if(count($vsd)){
						if(!$db->istable(2,"varying_interest$cid")){ $db->createTbl(2,"varying_interest$cid",["loan"=>"CHAR","schedule"=>"TEXT","time"=>"INT"]); }
						$db->execute(2,"DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'");
						$db->execute(2,"INSERT INTO `varying_interest$cid` VALUES(NULL,'$lid','".json_encode($vsd,1)."','$now')");
					}
					
					if(count($deds) && !$istop){
						$sum = array_sum($deds); $payb=getpaybill($bran); $mon=strtotime(date("Y-M")); $day=date("YmdHis");
						$db->execute(2,"INSERT INTO `org$cid"."_prepayments` VALUES(NULL,'$name','$idno','$bran','$ofid','$tid','$prod','$sum','$lid')");
						$rid = $db->query(2,"SELECT `id` FROM `org$cid"."_prepayments` WHERE `idno`='$idno' AND `status`='$lid'")[0]['id'];
						$db->execute(2,"INSERT IGNORE INTO `org".$cid."_payments` VALUES(NULL,'$mon','PREPAY$rid','$day','$sum','$payb','$idno','0','$phone','$name','0')");
						$pid = $db->query(2,"SELECT id FROM `org".$cid."_payments` WHERE `date`='$day' AND `code`='PREPAY$rid'")[0]['id'];
						savepays("$pid:$tym",$lid,array($deds)); bookbal(DEF_ACCS['loan_charges'],"+$sum");
					}
					
					if($tint>0){ bookbal(DEF_ACCS['interest'],"+$tint"); $ladds["interest"]=$tint; }
					if($paydes && !$istop){ savepays("$payid:$tym",$lid,array($deducted)); bookbal(DEF_ACCS['loan_charges'],"+$deduct"); }
					foreach($ladds as $py=>$sum){
						$pay = ucwords(str_replace("_"," ",$py)); $tbal+=$sum;
						logtrans($lid,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$sum,"bal"=>$tbal),1),0);
					}
					
					recon_daily_pays($lid);
					if($intrcol=="Cash"){ setInterest($lid); }
					setTarget($ofid,strtotime(date("Y-M",$dsd)),$record);
					savelog($sid,"Posted a loan topup for $name of KES ".fnum($lamnt)." to existing loan of ".fnum($tsum-$lamnt));
					echo "success";
				}
				else{ echo "Failed to post Loan! Try again later"; $con->commit(); $con->close(); }
			}
			else{ echo "Failed to complete the request! Try again later"; }
			exit();
		}
		
		$loan = getLoanId(); $expd+=$pfrm;
		if(substr($lprod[0]['interest'],0,4)=="pvar"){
			$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
			$vars = json_decode($info['value'],1);
			foreach($vars as $key=>$perc){
				$dy = $day+($key*86400); $cyc=$ldur/$intv; $dy+=$pfrm;
				$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
			}
		}
		
		$tblf = array_flip($db->tableFields(2,$ltbl)); $dsd=strtotime(date("Y-M-d",$day).",".date("H:i",$lst)); $rem=$pamnt-$deduct;
		$del = array("id","payment","processing","checkoff","prepay","approvals","pref","loantype","comments","cuts","disbursed");
		foreach($del as $one){ unset($tblf[$one]); }
		foreach($tblf as $col=>$k){ $records[$col]=$row[$col]; }
		
		$stbl = "org$cid"."_schedule";
		if(!$db->istable(2,$stbl)){
			$keys =json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='schedule'")[0]['fields'],1);
			if(!isset($keys["def"])){ $keys["def"]="TEXT"; }
			$db->createTbl(2,$stbl,$keys);
		}
		
		# schedule
		$tint=$tpay=$tbal=$tpen=$lid=0; $slis=[];
		$replace = array("loan"=>$loan,"disbursement"=>$dsd,"time"=>time(),"tid"=>$tid,"expiry"=>$expd,"penalty"=>0,"paid"=>0,"status"=>0,"creator"=>$sid,"amount"=>$lamnt);
		foreach(loanschedule($tid,$day)["installment"] as $k=>$one){
			$dy=$one["day"]; $mon=strtotime(date("Y-M",$dy)); $svint=$one['install']; $brek=$one["pays"]; 
			$intr=($brek["interest"]=="varying") ? $one["nint"]:$brek["interest"]; $days[$dy]=$intr; $tpay+=$svint; 
			$intsv=($intrcol=="Accrued") ? $intr:0; $tint+=($intrcol=="Accrued") ? $intr:0; $bjs=json_encode($brek,1);
			$sjs = (isset($one["ipays"])) ? json_encode(["inst"=>$k+1,"pays"=>$one["ipays"]],1):json_encode(["inst"=>$k+1],1);
			$slis[] = "(NULL,'$idno','$loan','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
		}
		
		$replace["balance"]=$tpay; $ctbl="org".$cid."_clients"; $ptbl="org".$cid."_payments"; $ins=$cols=""; $trem=0; 
		if(in_array("clientdes",$db->tableFields(2,"org$cid"."_loans"))){ $replace["clientdes"]=json_encode(["rating"=>"Unrated"],1); }
		foreach($replace as $col=>$val){ $records[$col]=$val; }
		foreach($records as $col=>$rec){ $cols.="`$col`,"; $ins.="'$rec',"; }
		$indata = rtrim($ins,","); $incols = rtrim($cols,","); $qrys=implode(",",$slis);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0"); 
		if($res){
			foreach($res as $rw){
				$pd=$rw["loan_product"]; $pen=$rw["penalty"]; $bl=$rw["balance"];
				$chk = $db->query(1,"SELECT `pdef` FROM `loan_products` WHERE `id`='$pd'");
				$def = json_decode($chk[0]["pdef"],1); $skp=(isset($def["skip_checkoff"])) ? $def["skip_checkoff"]:0;
				if(!$skp){ $tbal=$bl+$pen; $tpen=$pen; $lid=$rw["loan"]; break; }
			}
		}
		
		if($pst==0){
			if($cko){
				$avs = $db->query(2,"SELECT *FROM `$ptbl` WHERE `code`='CHECKOFF$tid'");
				if($avs==null){
					$qri = $db->query(2,"SELECT *FROM `checkoff_others$cid` WHERE `template`='$tid'"); $tbal-=$cko;
					if($qri){
						$give=$qri[0]['amount']; $cto=$qri[0]['receiver']; $diff=$cko-$give; $tbal+=$give;
						if($diff>0){ makepay($idno,"checkoff:$tym",$diff); }
						$mon = strtotime(date("Y-M",$tym)); $dy=date("YmdHis",$tym);
						$row = $db->query(2,"SELECT *FROM `$ctbl` WHERE `idno`='$cto'")[0];
						$cname = strtoupper($row['name']); $fon=$row['contact']; $payb=getpaybill($row['branch']);
						$db->execute(2,"INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','CHECKOFFTO$tid','$dy','$give','$payb','$cto','0','$fon','$cname','0')");
						$pid = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `date`='$dy' AND `code`='CHECKOFFTO$tid' ORDER BY `id` DESC")[0]['id'];
						makepay($cto,$pid,$give,"client",$qri[0]["loan"]);
					}
					else{
						$chk = ($db->istable(3,"translogs$cid")) ? $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='CKOF$tid'"):null; 
						if($chk){
							$def = json_decode($chk[0]["details"],1); $jn=0;
							if(array_sum($def)==$cko){
								foreach($def as $key=>$sum){
									$cd = ($jn) ? "CHECKOFF$tid.$jn":"CHECKOFF$tid"; $jn++;
									makepay($idno,"checkoff:$tym:$cd",$sum,"client",$key);
								}
							}
							else{ makepay($idno,"checkoff:$tym",$cko); }
						}
						else{ makepay($idno,"checkoff:$tym",$cko); }
					}
				}
			}
			
			if($rem>0 && $payid){
				if($tbal){
					$tbrm = ($tbal>$rem) ? $rem:$tbal;
					if($tpen){
						$db->execute(2,"UPDATE `org".$cid."_loans` SET `penalty`='0' WHERE `client_idno`='$idno'");
						if(($tbrm-$tpen)>0){ makepay($idno,$payid,array(array(["penalties"=>$tpen]),$tbrm-$tpen),"client",$lid,$tid); }
						else{ savepays($payid,$lid,array(["penalties"=>$tpen])); saveoverpayment($idno,$payid,$tbrm-$tpen); }
					}
					else{ makepay($idno,$payid,$rem,"client",$lid,$tid); }
					
					$qry = $db->query(2,"SELECT ov.amount,ov.id AS rid,cl.* FROM `overpayments$cid` AS ov INNER JOIN `$ctbl` AS cl ON ov.idno=cl.idno WHERE ov.payid='$payid'");
					if($qry){
						$row=$qry[0]; $rid=$row['rid']; $cname=strtoupper($row['name']); $fon=$row['contact']; $payb=getpaybill($row['branch']);
						$amnt=$row['amount']; $mon=strtotime(date("Y-M",$tym)); $dy=date("YmdHis",$tym);
						$db->execute(2,"INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','OVERPAY$rid','$dy','$amnt','$payb','$idno','0','$fon','$cname','0')");
						$payid = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `date`='$dy' AND `code`='OVERPAY$rid' ORDER BY `id` DESC")[0]['id'];
						$db->execute(2,"UPDATE `overpayments$cid` SET `status`='$tym' WHERE `id`='$rid'");
						if(($amnt-$deduct)>0){ saveoverpayment($idno,$payid,$amnt-$deduct); }
						bookbal(DEF_ACCS['overpayment'],"-$amnt");
					}
				}
				else{ 
					saveoverpayment($idno,$payid,$rem); 
					$db->execute(2,"UPDATE `org".$cid."_payments` SET `status`='$loan' WHERE `id`='$payid'");
				}
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE `idno`='$idno'");
		$res2 = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `tid`='$tid'"); $mtp=$haslon=0;
		$res3 = $db->query(2,"SELECT `loan_product` FROM `org".$cid."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0");
		if($res3){
			foreach($res3 as $rw){
				$chk = $db->query(1,"SELECT `pdef`,`category` FROM `loan_products` WHERE `id`='".$rw["loan_product"]."'");
				$def = json_decode($chk[0]["pdef"],1); $mtp+=(isset($def["skip_checkoff"])) ? intval($def["skip_checkoff"]):0;
				if($chk[0]["category"]=="asset" && $lprod[0]["category"]!="asset"){ $mtp++; }
			}
		}
		
		if($res3){ $haslon = ($mtp==count($res3)) ? 0:1; }
		$clst = $res[0]['status']; $state=($clst==1 && !$haslon) ? 0:$clst; $av=($res2) ? 1:0;
		$topup = (isset($setts["use_app_as_topup"])) ? $setts["use_app_as_topup"]:0; $post=staffInfo($usa)['position'];
		$appbypass = ($post=="USSD" && $topup) ? 1:0; $ldisb=$lamnt-array_sum($cuts)-$cko;
		$lsts = ($apam==$lamnt) ? $lst:8; $lid=($lsts==8) ? 0:$loan; $asln=(in_array("asset",explode(":",$lntp))) ? 1:0;
		
		if($av){ echo "success"; }
		elseif($state==2){ echo "Failed: ".prepare(ucfirst($res[0]['name']))." is suspended"; }
		elseif($state==1 && !$appbypass && !$allowdup){ echo "Failed: ".prepare(ucfirst($res[0]['name']))." has a running loan"; }
		elseif(!$qrys){ echo "Failed to post loan! Loan product is misconfigured"; }
		else{
			if($db->execute(2,"INSERT INTO `org".$cid."_loans` ($incols) VALUES ($indata)")){
				$db->execute(2,"INSERT INTO `$stbl` VALUES $qrys");
				$db->execute(2,"UPDATE `$ltbl` SET `loan`='$lid',`status`='$lsts' WHERE `id`='$tid'");
				$db->execute(2,"UPDATE `org".$cid."_clients` SET `status`='1',`cycles`=(cycles+1),`loan_officer`='$ofid' WHERE `idno`='$idno'");
				if(count($vsd)){
					if(!$db->istable(2,"varying_interest$cid")){ $db->createTbl(2,"varying_interest$cid",["loan"=>"CHAR","schedule"=>"TEXT","time"=>"INT"]); }
					$db->execute(2,"INSERT INTO `varying_interest$cid` VALUES(NULL,'$loan','".json_encode($vsd,1)."','$tym')");
				}
				
				if(count($deds)){
					$sum = array_sum($deds); $payb=getpaybill($bran); $mon=strtotime(date("Y-M")); $day=date("YmdHis");
					$db->execute(2,"INSERT INTO `org$cid"."_prepayments` VALUES(NULL,'$name','$idno','$bran','$ofid','$tid','$prod','$sum','$loan')");
					$rid = $db->query(2,"SELECT `id` FROM `org$cid"."_prepayments` WHERE `idno`='$idno' AND `status`='$loan'")[0]['id'];
					$db->execute(2,"INSERT IGNORE INTO `org".$cid."_payments` VALUES(NULL,'$mon','PREPAY$rid','$day','$sum','$payb','$idno','0','$phone','$name','0')");
					$pid = $db->query(2,"SELECT id FROM `org".$cid."_payments` WHERE `date`='$day' AND `code`='PREPAY$rid'")[0]['id'];
					savepays("$pid:$tym",$loan,array($deds)); bookbal(DEF_ACCS['loan_charges'],"+$sum");
				}
				
				if($tint>0){ setLoanBook(); $ladds["interest"]=$tint; }
				if($paydes){ savepays("$payid:$tym",$loan,array($deducted)); bookbal(DEF_ACCS['loan_charges'],"+$deduct"); }
				if($db->istable(3,"translogs$cid")){
					$chk = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='TID$tid'");
					if($chk){
						logtrans($loan,json_encode(array("desc"=>$chk[0]['details'],"type"=>"debit","amount"=>$ldisb,"bal"=>$ldisb),1),0);
						if($deps){ logtrans($loan,json_encode(array("desc"=>"Deposit amount Paid","type"=>"credit","amount"=>array_sum($deps),"bal"=>$ldisb-array_sum($deps)),1),0); }
						if($cko>0){ logtrans($loan,json_encode(array("desc"=>"Previous Loan Check-Off balance applied","type"=>"debit","amount"=>$cko,"bal"=>$ldisb+$cko),1),0); }
						foreach($ladds as $py=>$sum){
							$pay = ucwords(str_replace("_"," ",$py)); $ldisb+=$sum;
							logtrans($loan,json_encode(array("desc"=>"$pay charges applied","type"=>"debit","amount"=>$sum,"bal"=>$ldisb),1),0);
						}
					}
				}
				
				recon_daily_pays($loan);
				if($intrcol=="Cash"){ setInterest($loan); }
				$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='CLOAN$tid'");
				if(!$chk && !$asln){
					$rule = getrule("loans");
					doublepost([$bran,$rule['credit'],$lamnt,$desc,"CLOAN$tid",$sid],[$bran,$rule['debit'],$lamnt,$desc,"CLOAN$tid",$sid],'',time(),"auto");
				}
				
				setTarget($ofid,strtotime(date("Y-M",$dsd)),$record);
				$db->execute(1,"DELETE FROM `tempque` WHERE `temp`='CLTMP$cid$tid'"); 
				savelog($sid,"Created a loan for ".ucfirst($res[0]['name'])." of KES ".fnum($lamnt)." to be paid in $ldur days");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# save loan template
	if(isset($_POST['formkeys'])){
		$tid = trim($_POST['id']);
		$ltbl = "org$cid"."_loantemplates";
		$ptbl = "org$cid"."_payments";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$otp = (isset($_POST["otpv"])) ? trim($_POST["otpv"]):null;
		$idno = clean($_POST['client_idno']);
		$pid = clean($_POST['loan_product']);
		$lamnt = clean($_POST['amount']);
		$ldur = clean($_POST['duration']);
		$ltp = (isset($_POST['ltp'])) ? clean($_POST['ltp']):0;
		$pcode = (isset($_POST['payments'])) ? trim($_POST['payments']):0;
		$ckoto = (isset($_POST['ckoto'])) ? trim($_POST['ckoto']):0;
		$ckoam = (isset($_POST['ckoamnt']) && $ckoto) ? intval(trim($_POST['ckoamnt'])):0;
		$asid = (isset($_POST['asid'])) ? trim($_POST['asid']):0;
		$src = (isset($_POST["syspost"])) ? "app":"mfi";
		
		$adds = ["comments"=>"textarea","loantype"=>"text","pref"=>"number","payment"=>"text","processing"=>"textarea","checkoff"=>"number","prepay"=>"number","cuts"=>"textarea","disbursed"=>"textarea"];
		$dels = array("balance","expiry","penalty","disbursement","paid","tid","clientdes");
		foreach($adds as $key=>$add){ $keys[$key]=$add; }
		foreach($dels as $del){ unset($keys[$del]); }
		
		if(!$db->istable(2,$ltbl)){
			$replace = array("number"=>"INT","textarea"=>"TEXT");
			foreach($keys as $fname=>$dtype){
				$dtp = (isset($replace[$dtype])) ? $replace[$dtype]:"CHAR"; $cfields[$fname]=$dtp; 
			}
			$db->createTbl(2,$ltbl,$cfields);
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		foreach($res as $row){ $setts[$row['setting']]=prepare($row['value']); }
		
		$client = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$pid'");
		$ldisb = $db->query(2,"SELECT *FROM `$ltbl` WHERE `client_idno`='$idno' AND (`status`<9 OR (`loan`='0' AND NOT `status`='9')) AND NOT `id`='$tid'");
		$lpays = $db->query(2,"SELECT *FROM `$ltbl` WHERE `payment`='$pcode' AND NOT `id`='$tid' AND NOT `payment`='0' AND NOT `status`='9'");
		$paydes = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$pcode' AND NOT `code`='0'");
		$prepays = $db->query(1,"SELECT *FROM `prepaydays` WHERE `client`='$cid'"); 
		$pmst = ($paydes) ? $paydes[0]['status']:0; $cst=$client[0]['status'];
		$pdef = ($lprod) ? json_decode($lprod[0]["pdef"],1):[]; $lids=[];
		
		$cdm = (isset($setts['clientdormancy'])) ? $setts['clientdormancy']:0; 
		$smnot = sys_constants("template_notify_approver"); 
		$cond = (isset($setts['checkoff'])) ? $setts['checkoff']:0; 
		$ckocut = (isset($setts['checkoff_performing'])) ? $setts['checkoff_performing']:0; 
		$bypass_cko = (isset($setts['checkoff_bypass'])) ? json_decode($setts['checkoff_bypass'],1):[1]; 
		$today = strtotime('Today'); $limit=loanlimit($idno,$src); $due=$sum=$tbal=$lbal=0; 
		$topup = (isset($setts["use_app_as_topup"])) ? $setts["use_app_as_topup"]:0;
		$appbypass = (isset($_POST["syspost"]) && $otp && $topup) ? 1:0; 
		$allowdup = (isset($pdef["allow_multiple"])) ? $pdef["allow_multiple"]:0;
		
		$loans = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' ORDER BY `disbursement` DESC,`status` DESC");
		if($loans){
			if(!$db->istable(2,"penalties$cid")){ $db->createTbl(2,"penalties$cid",["loan"=>"INT","totals"=>"INT","paid"=>"INT","installments"=>"TEXT"]); }
			foreach($loans as $row){
				$lid = $row['loan']; $prod=$row["loan_product"];
				if($row["penalty"]>0){
					$qry = $db->query(2,"SELECT SUM(totals-paid) AS rem FROM `penalties$cid` WHERE `loan`='$lid'");
					$pbal = ($qry) ? intval($qry[0]['rem']):0; $row["penalty"]=$pbal;
					if($pbal!=$row["penalty"]){ $db->execute(2,"UPDATE `org$cid"."_loans` SET `penalty`='$pbal' WHERE `loan`='$lid'"); }
				}
				
				$bal=($row['balance']+$row['penalty']); $lbal+=$bal;
				if($bal>0){
					$chk = $db->query(1,"SELECT `pdef`,`category` FROM `loan_products` WHERE `id`='$prod'"); 
					$def = json_decode($chk[0]["pdef"],1); $skip=(isset($def["skip_checkoff"])) ? $def["skip_checkoff"]:0; 
					if(!$skip or $prod==$pid){ $tbal+=$bal; $lids[$lid]=$bal; }
					if(!$skip && $ckocut){
						$qri = $db->query(2,"SELECT SUM(balance) AS arr FROM `org".$cid."_schedule` WHERE `loan`='$lid' AND `day`<$today");
						$qry = $db->query(2,"SELECT SUM(balance) AS due FROM `org".$cid."_schedule` WHERE `loan`='$lid' AND `day`='$today'");
						$sum+= ($qri) ? intval($qri[0]['arr']):0; $due+=($qry) ? intval($qry[0]['due']):0; 
					}
					if($lprod){
						if($chk[0]["category"]!=$lprod["category"] && $chk[0]["category"]=="asset"){ $allowdup=1; }
					}
				}
			}
		}
		
		if($lbal<1 && $cst==1){
			$db->execute(2,"UPDATE `org$cid"."_clients` SET `status`='0' WHERE `idno`='$idno'"); $cst=0;
		}
		
		if(!is_null($otp)){
			$fon = trim($_POST["phone"]);
			$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
			if(!$res){ echo "Failed: Invalid OTP"; exit(); }
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); exit(); }
		}
		
		if(!$pid){ echo "Failed: No loan product selected"; }
		elseif(!$lprod){ echo "Failed: Loan product selected not found!"; }
		elseif(!$client){ echo "Failed: Client Id number $idno is not found in the system"; }
		elseif($cst && !$tid && !$cond && !$appbypass && !$allowdup){ echo "Failed: Client has a running Loan"; }
		elseif($client[0]['status']==2){ echo "Failed: Client is suspended"; }
		elseif($lamnt<$lprod[0]['minamount']){ echo "Minimum Loan amount for ".ucfirst(prepare($lprod[0]['product']))." is KES ".fnum($lprod[0]['minamount']); }
		elseif($lamnt>$lprod[0]['maxamount'] && !$asid){ echo "Maximum Loan amount for ".ucfirst(prepare($lprod[0]['product']))." is KES ".fnum($lprod[0]['maxamount']); }
		elseif($ldisb){ echo "Failed: The client has a pending loan disbursement"; }
		elseif($pcode && $lpays){ echo "Failed: Payment transaction $pcode is already in use"; }
		elseif($pcode && !$paydes && substr($pcode,0,7)!="WALLET:"){ echo "Failed: Payment transaction $pcode doesnt exist!"; }
		elseif($pcode && $pmst){ echo "Failed: Payment transaction $pcode is already Confirmed!"; }
		elseif($ckoto==$idno){ echo "Failed: Beneficiary account for checkoff cant be the same Client!"; }
		elseif(!isset($limit[$pid]) && !isset($_POST['bypasslim']) && !$asid){ echo ucwords(prepare($client[0]['name']))." does not qualify for this loan product"; }
		elseif(!isset($_POST['bypasslim']) && $lamnt>max($limit) && !$asid){ echo "Failed: Loan limit for ".ucwords(prepare($client[0]['name']))." is KES ".fnum(max($limit)); }
		else{
			if($ckoto){
				$chk = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `client_idno`='$ckoto' ORDER BY id DESC LIMIT 1");
				if($chk){
					$rbal = $chk[0]['balance']+$chk[0]["penalty"]; $bname=ucwords(prepare($chk[0]['client'])); $ckoln=$chk[0]["loan"];
					if($rbal){
						if($rbal<$ckoam){ echo "Failed: Checkoff amount to $bname is greater than Loan balance of KES ".fnum($rbal); exit(); }
					}
					else{ echo "Failed: Client $bname has no running Loan"; exit(); }
				}
				else{ echo "Failed: Beneficiary client $ckoto is not found in Loans"; exit(); }
			}
		
			if($cdm){
				$last = strtotime(date("Y-M-d",strtotime("-$cdm month"))); $last_loan=($loans) ? $loans[0]['status']:0;
				$cdms = ($last_loan<=$last && $last_loan>0) ? 1:0;
			}
			else{ $cdms = 0; }
			
			if($tid){ $ptmp = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$tid'")[0]; $lntp=$ptmp['loantype']; }
			else{
			    $lntp = ($ltp>0 or $cdms or !$loans) ? "new":"repeat"; $_POST['branch']=$client[0]['branch']; 
			    $lntp = (isset($_POST['ltp']) && $ltp==0) ? "repeat":$lntp;
			}
			
			$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals']; $req=0; $pays=$deds=$lis=[]; 
			foreach($payterms as $des=>$pay){
				$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
				if(isset($pdef["limit"]["min"][$val[0]])){ $min=$pdef["limit"]["min"][$val[0]]; $amnt=($amnt<$min) ? $min:$amnt; } 
				if($val[0]==0 && explode(":",$lntp)[0]=="new"){ $req+=$amnt; $pays[$des]=$amnt; $lis[]="Ksh $amnt ".ucwords(str_replace("_"," ",$des)); }
				if(in_array($val[0],[1,7])){ $req+=$amnt; $pays[$des]=$amnt; $lis[]="Ksh $amnt ".ucwords(str_replace("_"," ",$des)); }
				if($val[0]==6 && explode(":",$lntp)[0]=="repeat"){ $req+=$amnt; $pays[$des]=$amnt; $lis[]="Ksh $amnt ".ucwords(str_replace("_"," ",$des)); }
				if($val[0]==4){ $deds[$des]=$amnt; }
			}
			
			if(substr($lprod[0]['interest'],0,4)=="pvar"){
				$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod[0]['interest']."'")[0];
				$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
				$intr = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
			}
			else{ $intr = (count(explode("%",$lprod[0]['interest']))>1) ? round($lamnt*explode("%",$lprod[0]['interest'])[0]/100):$lprod[0]['interest']; }
			
			if(explode(":",$pcode)[0]=="WALLET" && !$tid){
				$wid = explode(":",$pcode)[1]; $pamnt=walletBal($wid);
			}
			else{ $pamnt = ($paydes) ? $paydes[0]['amount']:0; }
			
			$bal = ($req+$tbal)-$pamnt; $deduct=$cut=$cko=0; $dnots=[];
			$totpay = $lamnt+($ldur/$lprod[0]['interest_duration']*$intr); 
			
			if($prepays){
				foreach($prepays as $row){
					$from=$row['fromdate']; $to=$row['todate'];
					for($i=1; $i<=$ldur/$intv; $i++){
						$payday=$today+(86400*$i*$intv);
						$deduct+=($payday>=$from && $payday<=$to) ? 1:0;
					}
				}
				$cut=($deduct) ? ($totpay/($ldur/$intv))*$deduct:0;
			}
			
			$prtbl = "org$cid"."_prepayments";
			if(!$db->istable(2,$prtbl)){
				$ctbl =array("client"=>"CHAR","idno"=>"INT","branch"=>"INT","officer"=>"INT","template"=>"INT","product"=>"INT","amount"=>"INT","status"=>"INT");
				$db->createTbl(2,$prtbl,$ctbl);
			}
			
			if($bal>0 && !$appbypass && !$allowdup){ $cko = ($cond && $lids) ? $bal:0; }
			if($req>$pamnt){ echo "Failed: Loan fulfillment (".implode(", ",$lis).") totals to KES $req, but KES $pamnt provided"; }
			elseif($sum && !in_array($sid,$bypass_cko) && ($pamnt-$req)<$sum){ echo "Failed: Client is not eligible for Checkoff since has arrears"; }
			elseif($due && !in_array($sid,$bypass_cko) && ($pamnt-($req+$sum))<$due){ echo "Failed: Client is not eligible for Checkoff since has due installment"; }
			elseif($bal>0 && $cko==0 && !$appbypass && !$allowdup){ echo "Failed: Client has a loan balance of KES ".fnum($bal); }
			else{
				if(isset($client[0]["client_group"]) && !$tid){
					$gid = $client[0]["client_group"];
					if($gid){
						$gset = groupSetting($gid);
						if($gset){
							$chk = $db->query(2,"SELECT COUNT(*) AS tots FROM `org$cid"."_clients` WHERE `client_group`='$gid'");
							$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
							$row = $sql[0]; $mdy=$row["meeting_day"]; $coll=$gset["collateral"]; $dset=(isset($gset["loan_apply"])) ? $gset["loan_apply"]:"all"; 
							$minm = $gset["minmembers"]; $notif=$gset["loan_notify"]; $apd=($dset=="all") ? date("l"):$mdy; $uid=$client[0]['id']; $sbal=0;
							$colamt = (count(explode("%",$coll))>1) ? round($lamnt*explode("%",$coll)[0]/100):$coll; $gname=prepare(ucwords($row["group_name"]));
							
							if(intval($chk[0]["tots"])<$minm){ echo "Failed: Client group $gname has not met Minimum of $minm group members for it to operate"; exit(); }
							if($apd!=date("l")){ echo "Failed: Loan applications for $gname are allowed on $apd only"; exit(); }
							if($colamt>0){
								if($db->istable(3,"wallets$cid")){
									$chk = $db->query(3,"SELECT `savings` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
									$sbal = ($chk) ? intval($chk[0]["savings"]):0;
								}
								if($sbal<$colamt){ echo "Failed: Insufficient savings balance for Collateral amount of Ksh ".fnum($colamt)." required"; exit(); }
							}
							
							if(in_array($notif,["leaders","all"])){
								if($notif=="leaders"){
									foreach(array("group_leader","secretary","treasurer") as $one){
										if($row[$one] && $row[$one]!=$uid){
											$dnots[] = $db->query(2,"SELECT `contact` FROM `org$cid"."_clients` WHERE `id`='".$row[$one]."'")[0]['contact'];
										}
									}
								}
								else{
									$sql = $db->query(2,"SELECT `contact` FROM `org$cid"."_clients` WHERE `client_group`='$gid' AND NOT `id`='$uid'");
									if($sql){
										foreach($sql as $rw){ $dnots[]=$rw["contact"]; }
									}
								}
							}
						}
					}
				}
				
				$upds=$fields=$vals=""; $cname=$client[0]['name']; $json=json_encode($pays,1); $tcko=$cko+$ckoam; $dcts=json_encode($deds,1); $lntp.=($asid) ? ":asset:$asid":"";
				$replace=["comments"=>"[]","payment"=>$pcode,"processing"=>$json,"checkoff"=>$tcko,"prepay"=>$cut,"client"=>$cname,"approvals"=>"[]","loantype"=>$lntp,"pref"=>0,"cuts"=>$dcts,"disbursed"=>"[]"];
				if($tid){ $replace['approvals']=$ptmp['approvals']; $replace['comments']=$ptmp['comments']; $replace['pref']=$ptmp['pref']; }
				$cdef=json_decode($client[0]['cdef'],1); $autodisb=(isset($cdef["autodisburse"])) ? $cdef["autodisburse"]:0;
				
				foreach($keys as $key=>$dtype){
					if($key!="id" && !in_array($key,$files)){
						$pvl = (isset($_POST[$key])) ? $_POST[$key]:"";
						$val = (isset($replace[$key])) ? $replace[$key]:clean(strtolower($pvl));
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
					if(substr($pcode,0,6)=="WALLET" && !$tid){
						if($pcode=payFromWallet($client[0]["id"],$req,"client","Payment for ".implode(",",$lis),$sid,0,1)){
							$db->execute(2,"UPDATE `$ltbl` SET `payment`='$pcode' WHERE `client_idno`='$idno' AND `time`='".trim($_POST['time'])."'");
						}
					}
					
					if($cko && $lids && $db->istable(3,"translogs$cid")){
						$jsn = json_encode($lids,1); $tym=trim($_POST["time"]);
						$rid = ($tid) ? $tid:$db->query(2,"SELECT `id` FROM `$ltbl` WHERE `client_idno`='$idno' AND `time`='$tym'")[0]["id"];
						$chk = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='CKOF$rid'"); 
						$qry = ($chk) ? "UPDATE `translogs$cid` SET `details`='$jsn' WHERE `ref`='CKOF$rid'":
						"INSERT INTO `translogs$cid` VALUES(NULL,'$sid','CKOF$rid','$jsn','$tym')";
						$db->execute(3,$qry);
					}
					
					if($cut){
						$qri = $db->query(2,"SELECT *FROM `$prtbl` WHERE `template`='$tid'");
						if($qri){ $db->execute(2,"UPDATE `$prtbl` SET `amount`='$cut',`product`='$pid' WHERE `template`='$tid'"); }
						else{
							$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `payment`='$pcode' AND `client_idno`='$idno'"); 
							$rid=$res[0]['id']; $bran=trim($_POST['branch']); $ofid=trim($_POST['loan_officer']);
							$db->execute(2,"INSERT INTO `$prtbl` VALUES(NULL,'$cname','$idno','$bran','$ofid','$rid','$pid','$cut','0')");
						}
					}
					else{ 
						if($tid){ $db->execute(2,"DELETE FROM `$prtbl` WHERE `template`='$tid'"); }
					}
					
					if($ckoto){
						$qri = $db->query(2,"SELECT *FROM `checkoff_others$cid` WHERE `template`='$tid'");
						if($qri){ $db->execute(2,"UPDATE `checkoff_others$cid` SET `amount`='$ckoam',`receiver`='$ckoto',`loan`='$ckoln' WHERE `template`='$tid'"); }
						else{
							if(!in_array("loan",$db->tableFields(2,"checkoff_others$cid"))){
								$db->execute(2,"ALTER TABLE `checkoff_others$cid` ADD `loan` VARCHAR(50) NOT NULL AFTER `template`"); 
							}
							$rid = $db->query(2,"SELECT `id` FROM `$ltbl` WHERE `payment`='$pcode' AND `client_idno`='$idno'")[0]['id']; 
							$db->execute(2,"INSERT INTO `checkoff_others$cid` VALUES(NULL,'$idno','$ckoto','$rid','$ckoln','$ckoam')");
						}
					}
					else{ if($tid){ $db->execute(2,"DELETE FROM `checkoff_others$cid` WHERE `template`='$tid'"); }}
					
					if(!$tid && !$autodisb){
						$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
						$levels = ($qri) ? json_decode($qri[0]['levels'],1):[];
						if(isset($levels[1])){
							$pos = $levels[1]; $cont=0; $me=staffInfo($sid);
							$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0' AND NOT `id`='$sid'");
							if($res){
								foreach($res as $row){
									$cnf = json_decode($row["config"],1);
									$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
									if(isset($post[$pos])){
										if($post[$pos]=="hq"){ $id=$row["id"]; $cont=$row['contact']; $name=ucwords(prepare($row['name'])); break; }
										else{
											if($row['branch']==$me['branch']){ $id=$row["id"]; $cont=$row['contact']; $name = ucwords(prepare($row['name'])); break; }
										}
									}
								}
								if($cont){
									$goto = "loans.php?disbursements";
									notify([$id,$cont,$goto],"Hi $name, a new loan application of KES ".fnum($lamnt)." has been created now waiting for your approval",$smnot);
								}
							}
						}
					}
					
					if($dnots){
						$pname = prepare(ucfirst($lprod[0]["product"])); $pname.=(strpos($pname,"loan")!==false) ? "":" loan"; $now=time();
						$mssg = greet().", ".ucwords($client[0]["name"])." has applied for $pname of Ksh ".fnum($lamnt)." repaid in $ldur days.";
						$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','$mssg','".json_encode($dnots,1)."','similar','$now','$now')");
					}
					
					# auto disburse
					if($autodisb && !$tid && (isset($_SESSION["dsp"]) or isset($_POST["skey"]))){
						if(isset($_POST["skey"])){
							$chk = fetchSqlite("tempdata","SELECT *FROM `tokens` WHERE `data`='".trim($_POST["skey"])."'");
							$val = (isset($chk[0]["data"])) ? $chk[0]["data"]:0; $pass=($val==trim($_POST["skey"])) ? 1:0;
						}
						else{ $pass = decrypt($_SESSION["dsp"],$idno); }
						
						$tym=trim($_POST['time']); $usa=trim($_POST["creator"]); $com=json_encode([$usa=>"Auto Approved"],1); $app=json_encode([$usa],1); $sets=[];
						$row = $db->query(2,"SELECT *FROM `$ltbl` WHERE `client_idno`='$idno' AND `time`='$tym'")[0]; $rid=$row['id']; $prep=$row['prepay']; $fon=$row['phone'];
						$deds = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $ded=array_sum($deds); $cko=$row['checkoff']; $net=$lamnt-($cko+$prep+$ded);
						$db->execute(2,"UPDATE `$ltbl` SET `pref`='$tym',`status`='8',`comments`='$com',`approvals`='$app' WHERE `id`='$rid'");
						if($pass){
							$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='autodisb_notify'");
							if($chk){
								foreach($chk as $rw){ $sets[$rw['setting']]=$rw['value']; }
							}
							
							if($auto=getAutokey()){
								$dtm = hexdec(array_shift($auto));
								if($key=decrypt(implode("/",$auto),date("MdY.His",$dtm))){
									$sql = $db->query(2,"SELECT `source` FROM `b2c_trials$cid` WHERE `phone`='$fon' AND `status`<2 AND JSON_EXTRACT(source,'$.src')='loantemplate'");
									if(!$sql){
										$ran=dechex(rand(10000000,999999999)); $des=json_encode(["src"=>"loantemplate","id"=>$rid,"dtp"=>"b2c","tmp"=>$ran],1); $now=time(); 
										$db->execute(2,"INSERT INTO `b2c_trials$cid` VALUES(NULL,'$fon','----','$net','$des','$sid','$now','0')");
										$res = send_money(["254$fon-$ran"=>$net],$key,$sid);
										if(explode("=>",$res)[0]=="success"){
											$lof = staffInfo($row['loan_officer']); $bid=$lof["branch"];
											$sto = (isset($lof["office_contact"])) ? $lof["office_contact"]:$lof["contact"]; 
											$sto.= (isset($sets["autodisb_notify"])) ? ",".$sets["autodisb_notify"]:"";
											$nto = $db->query(1,"SELECT `notify` FROM `bulksettings` WHERE `client`='$cid'")[0]['notify'];
											if($nto){ $toinf=staffInfo($nto); $sto.=",".$toinf['contact']; }
											$bran = prepare(ucwords($db->query(1,"SELECT `branch` FROM `branches` WHERE `id`='$bid'")[0]['branch']));
											$comp = explode(" ",prepare(strtoupper($db->query(1,"SELECT `company` FROM `clients` WHERE `id`='$cid'")[0]['company'])))[0];
											$cname = prepare(ucwords($client[0]['name'])); $ofname=prepare(ucwords($lof['name']));
											$mssg = "$comp has disbursed KES $net to $cname from $bran ($ofname) via self application";
											sendSMS($sto,$mssg);
										}
										else{ $db->execute(2,"DELETE FROM `b2c_trials$cid` WHERE `time`='$now' AND `phone`='$fon'"); }
									}
								}	
							}
						}
					}
					
					$txt = ($tid) ? "Updated loan template for":"Created loan template for";
					savelog($sid,"$txt ".$client[0]['name']." of KES ".number_format($lamnt)); 
					echo "success";
				}
				else{ echo "Failed to complete the request at the moment! Try again later"; }
			}
		}
	}
	
	# create loan topup
	if(isset($_POST["topupln"])){
		$src = explode(":",trim($_POST["topupln"]));
		$ndy = intval($_POST["rdays"]);
		$sdy = strtotime($_POST["pday"]);
		$amnt = intval($_POST["topamnt"]);
		$pay = (isset($_POST["paycode"])) ? trim($_POST["paycode"]):0;
		$lid = $src[0]; $max=intval($src[1]); $now=time(); $tdy=strtotime(date("Y-M-d"));
		
		if($amnt<100){ echo "Failed: Minimum Topup amount is Ksh 100"; }
		elseif($amnt>$max){ echo "Failed: Maximum topup amount for client is Ksh ".fnum($max); }
		else{
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'"); 
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='intrcollectype'");
			$row = $sql[0]; $tid=$row['tid']; $pid=$row['loan_product']; $idno=$row['client_idno']; $intrcol=($chk) ? $chk[0]['value']:"Accrued";
			
			$chk = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `client_idno`='$idno' AND `status`<9");
			if($chk){ echo "Failed: There is a pending loan application!"; exit(); }
			
			$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$pid'");
			$terms = json_decode($qry[0]["payterms"],1); $pdef=json_decode($qry[0]["pdef"],1); $fees=$wded=0; $deds=$pays=$pls=[];
			foreach($terms as $des=>$py){
				$val = explode(":",$py); $sum=(count(explode("%",$val[1]))>1) ? round($amnt*explode("%",$val[1])[0]/100):$val[1];
				if(isset($pdef["limit"]["min"][$val[0]])){ $min=$pdef["limit"]["min"][$val[0]]; $sum=($sum<$min) ? $min:$sum; }
				if(in_array($val[0],[1,6])){ $fees+=$sum; $pays[$des]=$sum; $pls[]=ucwords(str_replace("_"," ",$des)); }
				if($val[0]==4){ $deds[$des]=$sum; }
			}
			
			$cname=prepare(ucwords($row['client'])); $pys=implode(" + ",$pls);  
			$bals=getLoanBals($lid,$tdy); $arr=array_sum($bals);
			
			if($pay){
				if(substr($pay,0,6)=="WALLET"){
					$wid = explode(":",$pay)[1]; $wbal=walletBal($wid); $wded=$fees;
					if($wbal<$fees){ echo "Failed: Insufficient wallet balance to pay for $pys!"; exit(); }
				}
				else{
					$chk1 = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$pay'"); 
					$chk2 = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `payment`='$pay'");
					if(!$chk1){ echo "Failed: Payment transaction $pay is not Found!"; exit(); }
					if($chk2){ echo "Failed: Payment transaction $pay is already linked to Loan template!"; exit(); }
					if($chk1[0]["status"]){ echo "Failed: Payment transaction $pay is already Assigned!"; exit(); }
					if($chk1[0]["amount"]<$fees){ echo "Failed: Insufficient funds to pay for $pys!"; exit(); }
				}
			}
			
			if($intrcol=="Cash"){
				if($arr<1){ $bals=getLoanBals($lid); unset($bals["principal"]); $arr=array_sum($bals); }
				if($arr>0){
					$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
					$wch = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `type`='client' AND `client`='$uid'");
					$wid = ($wch) ? $wch[0]['id']:0; $wbal=($wid) ? walletBal($wid):0; $pbal=$wbal-$wded;
					if($pbal<$arr){ echo "Failed: Client should clear Accrued dues of Ksh ".fnum($arr)." first!"; exit(); }
					if(!isset($bals["principal"])){ swap_principal($lid); }
					payFromWallet($uid,$arr,"client","Payment for accrued ".implode("+",array_keys($bals)),$sid,0);
				}
			}
			else{
				if($arr>0){ echo "Failed: Client should clear dues and arrears of Ksh ".fnum($arr)." first!"; exit(); }
			}
			
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$tid'");
			$replace = array("amount"=>$amnt,"loan"=>0,"status"=>0,"approvals"=>"[]","creator"=>$sid,"time"=>$now,"comments"=>"[]","loantype"=>"topup:$tid:$sdy","pref"=>"[]","disbursed"=>"[]",
			"payment"=>$pay,"processing"=>json_encode($pays,1),"checkoff"=>0,"checkoff"=>0,"prepay"=>0,"cuts"=>json_encode($deds,1),"duration"=>$ndy); $roq=$qri[0];
			foreach($replace as $col=>$val){ $roq[$col]=$val; }
			foreach($roq as $col=>$val){ $ins[]="`$col`"; $vals[]=($col=="id") ? "NULL":"'$val'"; }
			
			if($db->execute(2,"INSERT INTO `org$cid"."_loantemplates` (".implode(",",$ins).") VALUES (".implode(",",$vals).")")){
				if(substr($pay,0,6)=="WALLET"){
					$clid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
					if($code=payFromWallet($clid,$fees,"client","Payment for $pys",$sid,0,1)){
						$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `payment`='$code' WHERE `client_idno`='$idno' AND `time`='$now'");
					}
				}
				
				if(sys_constants("template_notify_approver")){
					$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
					$levels = ($qri) ? json_decode($qri[0]['levels'],1):[];
					if(isset($levels[1])){
						$pos = $levels[1]; $me=staffInfo($sid); $cont=0;
						$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' AND `status`='0'");
						if($res){
							foreach($res as $row){
								$cnf = json_decode($row["config"],1);
								$post = (isset($cnf["mypost"])) ? staffPost($row["id"]):[$row["position"]=>$row["access_level"]];
								if(isset($post[$pos])){
									if($post[$pos]=="hq"){ $id=$row["id"]; $cont=$row['contact']; $name=ucwords(prepare($row['name'])); break; }
									else{
										if($row['branch']==$me['branch']){ $id=$row["id"]; $cont=$row['contact']; $name=ucwords(prepare($row['name'])); break; }
									}
								}
							}
							if($cont){
								$goto = "loans.php?disbursements";
								notify([$id,$cont,$goto],"Hi $name, a loan topup application for $cname of KES ".fnum($amnt)." has been created now waiting for your approval");
							}
						}
					}
				}
				
				savelog($sid,"Created loan topup application for $cname");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# release asset loan
	if(isset($_POST["assetdisb"])){
		$rid = trim($_POST["assetdisb"]);
		$otp = trim($_POST["otpv"]);
		$day = strtotime($_POST["disbdy"]);
		$docs = explode(":",trim($_POST["hasdocs"]));
		
		$me = staffInfo($sid); $tbl="org".$cid."_loantemplates";
		$fon = (isset($me["office_contact"])) ? $me["office_contact"]:$me['contact'];
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if($res[0]['expiry']<time()){ echo "Failed: Your OTP expired at ".date("d-m-Y, h:i A",$res[0]['expiry']); }
			else{
				$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$rid'"); $save=[];
				$row = $qri[0]; $amnt=$row["amount"]; $aid=explode(":",$row["loantype"])[2]; $cname=ucwords($row["client"]);
				if($row["status"]>10){ echo "success"; exit(); }
				
				foreach($docs as $doc){
					$tmp = $_FILES[$doc]["tmp_name"]; 
					$ext = @array_pop(explode(".",strtolower($_FILES[$doc]['name']))); 
					$name = "$doc-".date("Ymd_His").".$ext"; $error="";
					
					if(!in_array($ext,["pdf","gif","png","jpg","jpeg"])){ $error="File extension $ext is not supported"; }
					else{
						if(move_uploaded_file($tmp,"../../docs/$name")){
							if(in_array($ext,["gif","png","jpg","jpeg"])){
								crop("../../docs/$name",1000,900,"../../docs/$name"); 
								insertSqlite("photos","REPLACE INTO `images` VALUES('$name','".base64_encode(file_get_contents("../../docs/$name"))."')");
								unlink("../../docs/$name"); $save[]=$name;
							}
						}
						else{ $error = "Failed to upload document $doc"; }
					}
				}
				
				if($error){ echo $error; }
				else{
					$sql = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$aid'"); 
					if($db->execute(2,"UPDATE `$tbl` SET `status`='$day',`disbursed`='".json_encode([$day=>$amnt],1)."' WHERE `id`='$rid'")){
						$row = $sql[0]; $qty=ceil($amnt/$row["asset_cost"]); $def=json_decode($row["def"],1); $def["given"][$rid]=$qty; $jsn=json_encode($def,1);
						$db->execute(2,"UPDATE `finassets$cid` SET `qty`=(qty-$qty),`def`='$jsn' WHERE `id`='$aid'");
						$name=$row["asset_name"]; logtrans("TID$rid","$qty $name released to $cname",$sid); 
						if($save){ logtrans("GVASSET$rid",json_encode($save,1),$sid); }
						savelog($sid,"Released asset loan $name to $cname");
						echo "success";
					}
					else{ echo "Failed to complere the request! Try again later"; }
				}
			}
		}
		else{ echo "Failed: Invalid OTP, try again"; }
		
	}
	
	# save edited loan
	if(isset($_POST['lprod'])){
		$lid = trim($_POST['lid']);
		$prod = trim($_POST['lprod']);
		$ldur = trim($_POST['ldur']);
		$lamnt = trim($_POST['lamnt']);
		$disb = strtotime(trim($_POST['disb']));
		$exds = (isset($_POST["excols"])) ? $_POST["excols"]:[];
		$ofid = trim($_POST['loff']);
		$ltbl = "org".$cid."_loans";
		$stbl = "org".$cid."_schedule";
		
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `id`='$prod'");
		$loans = $db->query(2,"SELECT *FROM `$ltbl` WHERE `loan`='$lid'"); $ptot=$loans[0]['balance']+$loans[0]['paid'];
		$pamnt = $loans[0]['amount']; $paid=$rem=$loans[0]['paid']; $prd=$loans[0]['loan_product']; $dur=$loans[0]['duration'];
		$ldis = strtotime(date("Y-M-d",$loans[0]['disbursement'])); $expd=$disb+($ldur*86400); $cname=ucwords($loans[0]["client"]); $setts=[];
		if(!$db->istable(2,"varying_interest$cid")){ $db->createTbl(2,"varying_interest$cid",["loan"=>"CHAR","schedule"=>"TEXT","time"=>"INT"]); }
		if(!$db->istable(2,"daily_interests$cid")){ $db->createTbl(2,"daily_interests$cid",["loan"=>"CHAR","schedule"=>"TEXT","time"=>"INT"]); }
		
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='payloan_from_day' OR `setting`='intrcollectype')");
		if($chk){
			foreach($chk as $row){ $setts[$row['setting']]=prepare($row['value']); }
		}
		
		$pfrm = (isset($setts["payloan_from_day"])) ? round(86400*$setts["payloan_from_day"]):0; $ext="";
		$intrcol = (isset($setts["intrcollectype"])) ? $setts["intrcollectype"]:"Accrued"; $tdy=strtotime(date("Y-M-d"));
		foreach($exds as $col=>$val){ $ext.= ",`$col`='".prepare($val)."'"; }
		
		if(!$prod){ echo "Failed: No loan product selected"; }
		elseif($lamnt<$lprod[0]['minamount']){ echo "Minimum Loan amount for the product is KES ".number_format($lprod[0]['minamount']); }
		elseif($lamnt>$lprod[0]['maxamount']){ echo "Maximum Loan amount for the product is KES ".number_format($lprod[0]['maxamount']); }
		else{
			$pdef = json_decode($lprod[0]['pdef'],1); $intrtp=(isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR";
			$waivd = (isset($pdef["repaywaiver"])) ? $pdef["repaywaiver"]:0; $pfrm=($waivd) ? round(86400*$waivd):$pfrm; $expd+=$pfrm;
			$start = $disb+$pfrm+($lprod[0]["intervals"]*86400);
			
			if($lamnt==$pamnt && $prod==$prd && $dur==$ldur){
				if($db->execute(2,"UPDATE `$ltbl` SET `disbursement`='$disb',`loan_officer`='$ofid',`expiry`='$expd'$ext WHERE `loan`='$lid'")){
					if($ldis!=$disb){
						$sql = $db->query(2,"SELECT *FROM `varying_interest$cid` WHERE `loan`='$lid'");
						if($sql){
							foreach(json_decode($sql[0]['schedule'],1) as $dy=>$perc){
								$ndy = ($ldis>$disb) ? $dy-($ldis-$disb):$dy+($disb-$ldis); $nds[$ndy]=$perc;
							}
							$db->execute(2,"UPDATE `varying_interest$cid` SET `schedule`='".json_encode($nds,1)."' WHERE `loan`='$lid'");
						}
						
						$change = $disb-$ldis;
						$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid'");
						foreach($res as $key=>$row){
							$day=$row['day']+$change; $mon=strtotime(date("Y-M",$day)); $rid=$row['id'];
							if($key==0){ $change+= ($start-$day); $day=$start; }
							$db->execute(2,"UPDATE `$stbl` SET `officer`='$ofid',`month`='$mon',`day`='$day' WHERE `id`='$rid'");
						}
						
						$qri = $db->query(2,"SELECT *FROM `daily_interests$cid` WHERE `loan`='$lid'");
						if($qri){
							$ncd = json_encode(daily_interest($lid,$prod),1);
							$db->execute(2,"UPDATE `daily_interests$cid` SET `schedule`='$ncd' WHERE `loan`='$lid'");
						}
					}
					else{ $db->execute(2,"UPDATE `$stbl` SET `officer`='$ofid' WHERE `loan`='$lid'"); }
					savelog($sid,"Edited loan $lid details for $cname");
					echo "success";
				}
				else{ echo "Failed: Unknown error occured"; }
			}
			else{
				$qrys=$pays=$pids=$codes=$payat=$vsd=$adds=$ladds=[]; 
				$db->execute(2,"DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'");
				$payterms = json_decode($lprod[0]['payterms'],1); $intv=$lprod[0]['intervals'];
				foreach($payterms as $des=>$pay){
					$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
					if(isset($pdef["limit"]["min"][$val[0]])){ $min=$pdef["limit"]["min"][$val[0]]; $amnt=($amnt<$min) ? $min:$amnt; }
					if($val[0]==2){ $pays[$des]=$amnt; $ladds[$des]=$amnt; }
					if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; $ladds[$des]=$amnt; }
					if($val[0]==4){ $ladds[$des]=$amnt; }
					if($val[0]==5){ $adds[$des]=$amnt; $ladds[$des]=$amnt; }
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
				if($intrtp=="FR"){
					$totpay=$tpay=$lamnt+($ldur/$lprod[0]['interest_duration']*$intrs); $inst=ceil($totpay/($ldur/$intv));
					$intr=round(($totpay-$lamnt)/($ldur/$intv)); $princ=$inst-$intr; $idno=$loans[0]['client_idno'];
				
					$vint = (count($vsd)) ? "varying":$intr; $pinst=ceil($lamnt/($ldur/$intv)); $tprc=$lamnt; $tintr=$totpay-$lamnt;
					$arr = array('principal'=>$princ,"interest"=>$vint); $perin=$ldur/$intv; $other=array_sum($pays); $tpy=0;
					for($i=1; $i<=$perin; $i++){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); $tpay+=round($sum/$perin); }
						$dy=$disb+$pfrm+($i*$intv*86400); $mon=strtotime(date("Y-M",$dy)); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
						$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $sjp=$pays;
						if($intrcol=="Accrued" or ($pays && $dy<time() && $intrcol=="Cash")){ $totpay+=$other; $arr+=$pays; $sjp=[]; }
						$brek = ($cut>0) ? array($payat[$i][0]=>$cut)+$arr:$arr; $totpay+=$cut+$other; $tpay+=$cut; $inst=array_sum($brek);
						if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; $inst=array_sum($brek); $cut=0; }
						$sinst = ($inst>$tpay) ? $tpay:$inst; $tpay-=$sinst; $bjs=json_encode($brek,1);
						$rem=($rem>0) ? $rem:0; $amnt=($rem>$sinst) ? $sinst:$rem; $bl=$sinst-$amnt; $rem-=$amnt; $days[$dy]=$intr;
						$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0; 
						$sjs = ($sjp) ? json_encode(["intr"=>$i,"pays"=>$pays],1):json_encode(["intr"=>$i],1); $tpy+=($paid>0 && count($codes)<1) ? $bl:$svint; 
						$qrys[] = ($paid>0 && count($codes)<1) ? "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','$amnt','$bl','$bjs','[]','$sjs')":
						"(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
					}
				}
				else{
					$perin=$ldur/$intv; $pinst=ceil($lamnt/$perin); $tint=$totpay=0; $idno=$loans[0]['client_idno'];
					$arr = array('principal'=>$pinst,"interest"=>0); $other=array_sum($pays); $tpy=0;
					$calc = reducingBal($lamnt,explode("%",$lprod[0]['interest'])[0],$perin);
					for($i=1; $i<=$perin; $i++){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); }
						$dy=$disb+$pfrm+($i*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
						$arr['principal']=$calc["principal"][$i]; $totpay+=$cut; $ot=0; $sjp=$pays; 
						if($intrcol=="Accrued" or ($pays && $dy<time() && $intrcol=="Cash")){ $ot=$other; $arr+=$pays; $sjp=[]; }
						$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); 
						$intr = $calc["interest"][$i]; $brek["interest"]=$intr; $inst=array_sum($brek); $bjs=json_encode($brek,1);
						$sinst = $inst; $totpay+=$sinst; $tint+=($intrcol=="Accrued") ? $intr:0; $totpay+=($ot) ? $other:0;
						$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0; $days[$dy]=$intr;
						$rem = ($rem>0) ? $rem:0; $amnt=($rem>$sinst) ? $sinst:$rem; $bl=$sinst-$amnt; $rem-=$amnt; 
						$sjs = ($sjp) ? json_encode(["intr"=>$i,"pays"=>$pays],1):json_encode(["intr"=>$i],1); $tpy+=($paid>0 && count($codes)<1) ? $bl:$svint;
						$qrys[] = ($paid>0 && count($codes)<1) ? "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','$amnt','$bl','$bjs','[]','$sjs')":
						"(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$sjs')";
					}
				}
			
				$expd = $disb+($ldur*86400); $ds=$lamnt-$pamnt; $expd+=$pfrm;
				foreach($codes as $code){ reversepay("$idno:$lid",$code,"client",1); usleep(200000); }
				if($db->execute(2,"UPDATE `$ltbl` SET `loan_product`='$prod',`loan_officer`='$ofid',`amount`='$lamnt',`balance`='$tpy',`duration`='$ldur',
				`disbursement`='$disb',`expiry`='$expd'$ext WHERE `loan`='$lid'")){
					if($ds!=0){
						setLoanBook(); $bk=($ds>0) ? "debit":"credit";
						$desc = ($ds>0) ? "Principal added upon loan update":"Principal deducted upon loan update";
						logtrans($lid,json_encode(["desc"=>$desc,"type"=>$bk,"amount"=>str_replace("-","",$ds),"bal"=>$tpy+$ds],1),$sid);
					}
					
					$fdy = strtotime(date("Y-M-d",$disb))+$pfrm; $fto=0; $stdy=[];
					foreach($days as $dy=>$sum){
						$fdy = ($fto) ? $fto:$fdy; $fdy+=86400; $diff=($dy-$fdy)/86400; $per=($diff) ? ceil($sum/$diff):$sum; 
						for($i=$fdy; $i<=$dy; $i+=86400){
							$int=($per>$sum) ? $sum:$per; $sum-=$int; $fto=$i; $dys[$i]=$int;
							if($dy<=$tdy && $intrcol=="Cash"){ if(isset($stdy[$dy])){ $stdy[$dy]+=$int; }else{ $stdy[$dy]=$int; }}
						} 
					}
					
					$db->execute(2,"DELETE FROM `$stbl` WHERE `loan`='$lid'");
					$db->execute(2,"INSERT INTO `$stbl` VALUES ".implode(",",$qrys));
					recon_daily_pays($lid);
					
					if($intrcol=="Cash"){
						if(array_sum($stdy)>0){ $ladds["interest"]=array_sum($stdy); }
						foreach($stdy as $dy=>$sum){
							$db->execute(2,"UPDATE `$stbl` SET `interest`='$sum',`amount`=(amount+$sum),`balance`=(balance+$sum) WHERE `loan`='$lid' AND `day`='$dy'"); 
							$db->execute(2,"UPDATE `$ltbl` SET `balance`=(balance+$sum) WHERE `loan`='$lid'");
						}
					}
					
					foreach($ladds as $pay=>$sum){
						logtrans($lid,json_encode(array("desc"=>str_replace("_"," ",$pay)." charges applied","type"=>"debit","amount"=>$sum,"bal"=>$lamnt+$sum),1),0);
					}
					
					if(count($vsd)){ $db->execute(2,"INSERT INTO `varying_interest$cid` VALUES(NULL,'$lid','".json_encode($vsd,1)."','".time()."')"); }
					foreach(array_reverse($codes,1) as $pid=>$code){ makepay($idno,$pid,$amnts[$pid],"client",$lid,0,0); usleep(200000); }
					setTarget($ofid,strtotime(date("Y-M",$disb)),["performing","disbursement","loanbook","arrears"]);
					savelog($sid,"Edited loan $lid details for $cname");
					echo "success";
				}
				else{ echo "Failed: Unknown error occured"; }
			}
		}
	}
	
	# request loan writeoff
	if(isset($_POST["writeoff"])){
		$lid = trim($_POST["writeoff"]);
		$res = clean($_POST["wresn"]);
		$bals = getLoanBals($lid); $prc=$bals["principal"];
		
		if($db->istable(2,"transtemps$cid")){
			$chk = $db->query(2,"SELECT *FROM `transtemps$cid` WHERE `status`<15 AND `type`='writeoff' AND JSON_EXTRACT(details,'$.loan')='$lid'");
			if($chk){ echo "Failed: There is a pending write-off request"; exit(); }
		}
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'"); $name=ucwords($sql[0]['client']);
		$def = ["loan"=>$lid,"amount"=>$prc,"desc"=>"$name Ksh ".fnum($prc)." Loan #$lid write-off - $res","title"=>"$name loan Write-Off","perm"=>"approve loan write-off"];
		if(initiateApproval("writeoff",$def,0,$sid)){
			savelog($sid,"Initiated Loan #$lid writeoff request for $name of Ksh $prc");
			echo "success"; 
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# delete loan
	if(isset($_POST['deloan'])){
		$lid = trim($_POST['deloan']);
		$sql = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan`='$lid'");
		$row = $sql[0]; $idno=$row['client_idno']; $lamnt=$row['amount']; $name=$row['client']; $paid=$row['paid'];
		$tbal=$row['balance']; $prod=$row['loan_product']; $dism=strtotime(date('Y-M',$row['disbursement'])); $ofid=$row["loan_officer"];
		$cko=$prep=$tid=0; $pcd=""; $tops=[];
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `loan`='$lid' ORDER BY `id` ASC");
		if($qri){
			foreach($qri as $roq){ $tops[$roq["id"]]=$roq["id"]; }
			$row = $qri[0]; $cko=$row['checkoff']; $prep=$row['prepay']; $pcd=$row['payment']; $cuts=(isJson($row["cuts"])) ? json_decode($row['cuts'],1):[]; 
			$tid = $row['id']; $prep+=array_sum($cuts); unset($tops[$tid]);
		}
		
		$pays=$codes=array(); $charges=0; 
		$qry = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `linked`='$lid' ORDER BY `payid` DESC"); 
		if($qry){
			foreach($qry as $row){
				$pay=str_replace(" ","_",strtolower($row['payment'])); $code=$row['code']; 
				$retn = ($code==$pcd && substr($pcd,0,6)=="WALLET") ? 1:0; $codes[]=$code;
				if(array_key_exists($pay,$pays)){ $pays[$pay]+=$row['amount']; }
				else{ $pays[$pay]=$row['amount']; }
				reversepay("$idno:$lid",$code,"client",$retn);
			}
		}
		
		if($db->execute(2,"DELETE FROM `org".$cid."_loans` WHERE `loan`='$lid'")){
			foreach($codes as $code){
				if(substr($code,0,7)=="OVERPAY"){
					$rid = trim(str_replace("OVERPAY","",$code));
					$res = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$rid'"); $pid=$res[0]['payid'];
					$qri = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
					if(!in_array($qri[0]['code'],$codes)){ reversepay("$idno:$lid",$qri[0]['code']); }
				}
			}
			
			if($cko){
				reversepay("$idno:$lid","CHECKOFF$tid");
				$db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `code`='CHECKOFF$tid'");
				$chk = $db->query(2,"SELECT `code` FROM `org$cid"."_payments` WHERE `code` LIKE 'CHECKOFF$tid.%'");
				if($chk){
					foreach($chk as $rw){
						$cd = $rw["code"]; reversepay("$idno:$lid",$cd);
						$db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `code`='$cd'");
					}
				}
				
				$qri = $db->query(2,"SELECT *FROM `checkoff_others$cid` WHERE `template`='$tid'");
				if($qri){
					reversepay("$idno:".$qri[0]["loan"],"CHECKOFFTO$tid");
					$db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `code`='CHECKOFFTO$tid'");
				}
			}
			if($prep){
				$prtbl = "org".$cid."_prepayments";
				$qri = $db->query(2,"SELECT *FROM `$prtbl` WHERE `template`='$tid'");
				if($qri['status']){
					$db->execute(2,"DELETE FROM `$prtbl` WHERE `template`='$tid'");
					$db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `code`='PREPAY$tid'");
				}
				else{ $db->execute(2,"DELETE FROM `$prtbl` WHERE `template`='$tid'"); }
			}
			
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
			foreach(json_decode($res[0]['payterms'],1) as $key=>$term){
				if(in_array(explode(":",$term)[0],[0,1,6])){ $charges+=(isset($pays[$key])) ? $pays[$key]:0; }
			}
			
			$sql = $db->query(2,"SELECT *FROM `rescheduled$cid` WHERE `loan`='$lid'");
			if($sql){
				$db->execute(2,"DELETE FROM `rescheduled$cid` WHERE `loan`='$lid'"); $intr=$sql[0]['fee'];
				bookbal(getrule('interest')['debit'],"-$intr:".strtotime(date("Y-M",$sql[0]['start'])));
			}
			
			bookbal(DEF_ACCS['loan_charges'],"-$charges:$dism"); setLoanBook(); reset_points($idno);
			$deltmp = (defined("DELETE_LOAN_PLUS_TEMP")) ? DELETE_LOAN_PLUS_TEMP:0;
			$db->execute(2,"DELETE FROM `org".$cid."_schedule` WHERE `loan`='$lid'");
			$db->execute(2,"DELETE FROM `processed_payments$cid` WHERE `linked`='$lid'");
			$db->execute(2,"UPDATE `org".$cid."_clients` SET `status`='0',`cycles`=(cycles-1) WHERE `idno`='$idno'");
			if($deltmp){ $db->execute(2,"DELETE FROM `org{$cid}_loantemplates` WHERE `loan`='$lid'"); }
			else{ $db->execute(2,"UPDATE `org{$cid}_loantemplates` SET `loan`='0' WHERE `loan`='$lid'"); }
			
			if($db->istable(2,"varying_interest$cid")){ $db->execute(2,"DELETE FROM `varying_interest$cid` WHERE `loan`='$lid'"); }
			if($db->istable(2,"daily_interests$cid")){ $db->execute(2,"DELETE FROM `daily_interests$cid` WHERE `loan`='$lid'"); }
			if($db->istable(3,"translogs$cid")){ $db->execute(3,"DELETE FROM `translogs$cid` WHERE `ref`='$lid'"); }
			foreach($tops as $id){ $db->execute(2,"DELETE FROM `org$cid"."_loantemplates` WHERE `id`='$id'"); }
			setTarget($ofid,$dism,["disbursement","loanbook","arrears","newloans","repeats"]);
			savelog($sid,"Deleted Loan record of KSH $lamnt for Client $name IDNO $idno");
			echo "success";
		}
		else{ echo "Failed to delete loan at the moment"; }
	}
	
	# fetch phone number
	if(isset($_POST['getphone'])){
		$idno = trim($_POST['getphone']);
		$tbl = "org".CLIENT_ID."_clients";
		$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `idno`='$idno'");
		$http = $_SERVER['HTTP_HOST'];
		
		if(!in_array($http,array("mfi.axecredits.com"))){
			$res2 = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `client_idno`='$idno' LIMIT 1");
			$no = ($res2) ? 1:0;
		}
		else{ $no = 0; }
		
		echo ($res) ? "success:".$res[0]['contact'].":$no":"Id number $idno is not found";
	}

	@ob_end_flush();
?>