<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# topup loan
	if(isset($_GET["topupln"])){
		$lid = trim($_GET["topupln"]);
		$me = staffInfo($sid); $pays=[]; $ops=$lis=$opts=""; $pst=0;
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$row = $sql[0]; $pid=$row['loan_product']; $amnt=$row['amount']; 
		$cname = ucwords(prepare($row['client'])); $idno=$row['client_idno'];
		$assignpays = (defined("TEMPLATE_ASSIGN_PAYS")) ? TEMPLATE_ASSIGN_PAYS:[1];
		
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$pid'")[0];
		$lim = $qri["maxamount"]-$amnt; $payterms=json_decode($qri["payterms"],1); $dur=$qri["duration"]; $intv=$qri["intervals"];
		foreach($payterms as $key=>$val){
			if(explode(":",$val)[0]==1){ $pays[]=ucwords(str_replace("_"," ",$key)); }
			if(explode(":",$val)[0]==6){ $pays[]=ucwords(str_replace("_"," ",$key)); }
		}
		
		if(count($pays)){
			$crow = $db->query(2,"SELECT `id`,`contact` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0];
			if($db->istable(3,"wallets$cid")){
				$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='".$crow['id']."' AND `type`='client' AND `balance`>0");
				if($chk){
					$wid = $chk[0]['id']; $bal=walletBal($wid); $pst=1;
					$ops = "<option value='WALLET:$wid' select>Transaction A/C (Ksh $bal)</option>";
				}
			}
			
			$cond = ($me['access_level']=="hq") ? "":"AND `paybill`='".getpaybill($me['branch'])."'";
			$res = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `status`='0' $cond");
			if($res){
				foreach($res as $row){
					$name = prepare(ucwords(strtolower($row['client'])));
					$code = $row['code']; $acc=$row['account']; $phon=ltrim(ltrim($row['phone'],"0"),"254"); $amnt=fnum($row['amount']);
					$cnd = (!$pst && ($idno==$acc or $phon==$crow["contact"] or $acc==$crow["contact"])) ? "selected":"";
					$ops.= ($cnd or in_array($sid,$assignpays) or in_array("all",$assignpays)) ? "<option value='$code' $cnd>$name - $amnt</option>":"";
				}
			}
			$lis = "<p>Payment for ".implode("+",$pays)."<br><select style='width:100%' name='paycode' required>$ops</select></p>";
		}
		
		$opts = "<option value='0'>None</option>"; 
		for($i=1; $i<=($dur/$intv); $i++){
			$nd = $i*$intv; $opts.="<option value='$nd'>$nd Days</option>";
		}
		
		$tdy = strtotime(date("Y-M-d")); $pls=""; $tdyx=date("Y-m-d");
		$qri = $db->query(2,"SELECT MIN(day) AS mdy,MAX(day) AS mxd FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `day`>$tdy");
		if($qri){
			$mdy = date("Y-m-d",$qri[0]['mdy']); $mxd=date("Y-m-d",$qri[0]['mxd']); 
			$mxd = ($qri[0]['mxd']<($tdy+($intv*86400))) ? date("Y-m-d",$tdy+($intv*86400)):$mxd;
			$sbt = "<p style='text-align:right'><button class='btnn'>Create Application</button></p>";
		}
		else{
			$mdy=$mxd=date("Y-m-d");
			$sbt = "<p style='color:#ff4500;padding:8px;text-align:center;background:#FFE4C4'>Topup Disabled for an Overdue loan</p>"; 
		}
		
		echo "<div style='margin:0 auto;padding:10px;max-width:320px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Topup Loan #$lid</h3><br>
			<form method='post' id='tfom' onsubmit=\"topuploan(event,'$lim')\">
				<input type='hidden' name='topupln' value='$lid:$lim'><p><b>$cname</b></p>
				<p>Topup Amount<br><input type='number' id='tamnt' name='topamnt' style='width:100%' placeholder='Max Limit $lim' required></p>
				<p>Additional Repayment Days<br><select style='width:100%;' name='rdays'>$opts</select></p>
				<p>Start Paying from<br><input type='date' style='width:100%' name='pday' min='$tdyx' value='$mdy' max='$mxd'></p>$lis<br>$sbt<br>
			</form>
		</div>";
	}
	
	# close loan
	if(isset($_GET["closeln"])){
		$lid = trim($_GET["closeln"]);
		$sql = $db->query(2,"SELECT SUM(interest) AS intr,SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0");
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='closing_loan_fees'");
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		
		$intr = $sql[0]['intr']; $bal=$sql[0]['tbal']; $pen=$qri[0]['penalty']; $amnt=$qri[0]['amount']; $exp=$qri[0]['expiry']; $prc=$bal-$intr; 
		$paid = $qri[0]['paid']; $disb=$qri[0]['disbursement']; $pid=$qri[0]['loan_product']; $fee=($qry) ? $qry[0]['value']:0; $idno=$qri[0]['client_idno'];
		$pname = $db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='$pid'")[0]['product'];
		$charge = (count(explode("%",$fee))>1) ? round($prc*explode("%",$fee)[0]/100):$fee; $wbal=0; $tchj=$charge+$pen;
		
		if($db->istable(3,"wallets$cid")){
			$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
			$chk = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
			if($chk){ $wbal=walletBal($chk[0]['id']); }
		}
		
		$cname = prepare(ucwords($qri[0]['client']));
		$btx = ($wbal>=$charge+$bal+$pen) ? "<button class='btnn' style='width:100%' onclick=\"closeloan('$lid','$tchj')\"><i class='bi-clipboard-check'></i> Close Loan</button>":
		"<p style='padding:10px;text-align:center;color:#ff4500;background:#FFE4B5;border:1px solid pink'><i class='bi-exclamation-circle'></i> Insufficient Balance to clear Balances</p>";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:450px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Close Loan #$lid</h3><br>
			<table style='width:100%;font-size:15px' cellpadding='7'>
				<tr style='background:#f0f0f0'><td colspan='2'><b>$cname</b></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($amnt)."</b><br><span style='color:grey;font-size:14px'>".ucwords(prepare($pname))." Loan</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$disb)."</b><br><span style='color:grey;font-size:14px'>Disbursement</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($paid)."</b><br><span style='color:grey;font-size:14px'>Total Paid</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$exp)."</b><br><span style='color:grey;font-size:14px'>Loan Maturity</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($bal)."</b><br><span style='color:grey;font-size:14px'>Total Balance</span></td>
				<td style='text-align:right'><b>Ksh ".number_format($pen)."</b><br><span style='color:grey;font-size:14px'>Penalties</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($prc)."</b><br><span style='color:grey;font-size:14px'>Principal Bal</span></td>
				<td style='text-align:right'><b>Ksh ".fnum($intr)."</b><br><span style='color:grey;font-size:14px'>Accrued Interest</span></td></tr>
				<tr><td colspan='2' style='text-align:right;font-weight:bold;color:#191970'>Closing Fees : Ksh ".fnum($charge)."</td></tr>
				<tr><td colspan='2' style='text-align:right;font-weight:bold;color:#191970'>Total ToPay : Ksh ".fnum($charge+$bal+$pen)."</td></tr>
				<tr><td colspan='2' style='text-align:right;font-weight:bold;color:#191970'>Account Balance : Ksh ".fnum($wbal)."</td></tr>
				<tr><td colspan='2' style='text-align:right'>$btx</td></tr>
			</table><br>
		</div>";
	}
	
	# rollover Loan
	if(isset($_GET["rolloverln"])){
		$lid = trim($_GET["rolloverln"]);
		$mns = (isset($_GET["mon"])) ? intval($_GET["mon"]):0;
		$std = (isset($_GET["std"])) ? strtotime(trim($_GET["std"])):0;
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'"); 
		
		$bal=$qri[0]['balance']; $pen=$qri[0]['penalty']; $lamnt=$qri[0]['amount']; $exp=$qri[0]['expiry']; $ldur=$qri[0]['duration'];
		$paid = $qri[0]['paid']; $disb=$qri[0]['disbursement']; $pid=$qri[0]['loan_product']; $idno=$qri[0]['client_idno'];
		$tdy=strtotime(date("Y-M-d")); $bals=getLoanBals($lid); $intr=$bals["interest"]; $tprc=$bals["principal"];  
		
		$prod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$pid'")[0];
		$pdef = json_decode($prod["pdef"],1); $fee=(isset($pdef["rollfee"])) ? $pdef["rollfee"]:0; 
		$charge = (count(explode("%",$fee))>1) ? round($tprc*explode("%",$fee)[0]/100):$fee; $wbal=0;
		$intrtp = (isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR"; $maxn=($ldur/$prod['intervals'])*4; $prbal=$tprc; 
		
		if($db->istable(3,"wallets$cid")){
			$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
			$chk = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
			if($chk){ $wbal=walletBal($chk[0]['id']); }
		}
		
		$pays=$payat=$days=$adds=[]; $arrs["principal"]=$arrs["interest"]=0; $trs=""; $no=0;
		foreach(json_decode($prod["payterms"],1) as $des=>$pay){
			$val = explode(":",$pay); 
			$amnt = (count(explode("%",$val[1]))>1) ? round($prbal*explode("%",$val[1])[0]/100):$val[1];
			if($val[0]==2){ $pays[$des]=$amnt; }
			if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
			if($val[0]==5){ $adds[$des]=$amnt; }
		}
		
		$qry = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' ORDER BY `day` ASC");
		if($qry){
			foreach($qry as $row){
				$brek = json_decode($row['breakdown'],1); $dyls[]=$row['day'];
				$maxn-= (isset($brek["principal"]) && isset($brek["interest"])) ? 1:0;
				if($row['balance']>0 && isset($brek["principal"])){
					if($row['day']>=$tdy){ $days[]=$row['day']; }
					else{
						foreach($brek as $pay=>$sum){
							if($sum!="varying"){
								$val=($pay=="interest") ? $row['interest']:$sum;
								foreach(json_decode($row['payments'],1) as $one){ $val-=(isset($one[$pay])) ? $one[$pay]:0; }
								if(isset($arrs[$pay])){ $arrs[$pay]+=$val; }else{ $arrs[$pay]=$val; }
							}
						}
					}
				}
			}
			
			$intv=$prod["intervals"]; $nds=$skip=$stm=[]; $mnx=min($dyls);
			$frm = ($mnx<$tdy) ? $mnx+($intv*86400):$mnx; $frm=($frm<$tdy) ? $tdy:$frm;
			if($mns>0){
				foreach($days as $key=>$dy){
				    if(($dy-($tdy+($intv*86400)))<($intv*86400*0.25)){
				        if(($dy+(86400*$intv))>$tdy){ $skip[]=$dy+(86400*$intv*$mns); }
				    }
				    else{
						if($dy>=$tdy){ $nds[]=$dy; }
					}
				}
				foreach($skip as $dy){
				    if($dy>=($tdy+($mns*$intv))){ $stm[]=$dy; }
				}
				$days=$stm+$nds;
			}
			
			$mval = ($mns>$maxn) ? $maxn:$mns; $mls="<option value='0'>- Select -</option>";
			for($i=0; $i<$mval; $i++){ $days[]=$frm+($i*86400*$intv); } sort($days); 
			for($i=1; $i<=$maxn; $i++){ $cnd=($i==$mns) ? "selected":""; $mls.="<option value='$i' $cnd>".round($intv*$i)."</option>"; }
			if($std && $days){
				for($i=0; $i<count($days); $i++){ $dxd=$std+($i*$intv*86400); if($dxd>=$tdy){ $dayz[]=$dxd; }}
				$days = $dayz;
			}
		
			if($intrtp=="FR"){
				if(count($days)){
					$perin = count($days); $inc=$prod['interest']; $intrs=(count(explode("%",$inc))>1) ? round($prbal*explode("%",$inc)[0]/100):$inc;
					if(substr($intrs,0,4)=="pvar"){
						$chk = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='$intrs'");
						$set = json_decode($chk[0]["value"],1); $prc=$set[max(array_keys($set))];
						$intrs = (count(explode("%",$prc))>1) ? round($prbal*explode("%",$prc)[0]/100):$prc;
					}
					
					$totpay=$tpay=$prbal+($ldur/$prod['interest_duration']*$intrs); $inst=ceil($totpay/$perin); $int=round(($totpay-$prbal)/$perin); 
					$princ=$inst-$int; $arr=array('principal'=>$princ,"interest"=>$int)+$pays; $tpr=$prbal; $other=array_sum($pays);
					foreach($days as $i=>$dy){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $tpay+=round($sum/$perin); }
						$prc = ($princ>$tpr) ? $tpr:$princ; $arr['principal']=$prc; $tpr-=$prc; $tpay+=$other;
						$i++; $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $day=date("d-m-Y",$dy); $tpay+=$cut; 
						$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $inst=array_sum($brek); $sinst=($inst>$tpay) ? $tpay:$inst; $tpay-=$sinst;
						$trs.= "<tr><td style='text-align:left'>$day</td><td>".fnum($prc)."</td><td>".fnum($int)."</td><td>".fnum($sinst)."</td></tr>";
					}
				}
			}
			else{
				if(count($days)){
					$perin = count($days); $pinst=ceil($prbal/$perin); $arr=array('principal'=>$pinst,"interest"=>0)+$pays;
					$calc = reducingBal($prbal,explode("%",$prod['interest'])[0],$perin);
					foreach($days as $i=>$dy){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); }
						$i++; $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $prc=$calc["principal"][$i]; $arr['principal']=$prc; $day=date("d-m-Y",$dy);
						$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $int=$calc["interest"][$i]; $brek["interest"]=$int; $sinst=array_sum($brek);  
						$trs.= "<tr><td style='text-align:left'>$day</td><td>".fnum($prc)."</td><td>".fnum($int)."</td><td>".fnum($sinst)."</td></tr>";
					}
				}
			}
		}
		
		$cname = prepare(ucwords($qri[0]['client'])); $pname=prepare(ucwords($prod["product"])); $arl="penalties-$pen;";
		$arp=$arrs["principal"]; $ari=$arrs["interest"]; unset($arrs["principal"]); unset($arrs["interest"]); $mnd=($days) ? min($days):$tdy;
		$tarr=array_sum($arrs); $tchj=$charge+$pen+$tarr+$intr; $stdy=($std) ? date("Y-m-d",$std):date("Y-m-d",$mnd);
		foreach($arrs as $py=>$sum){ $arl.="$py-$sum;"; }
		
		$error = "padding:10px;text-align:center;color:#ff4500;background:#FFE4B5;border:1px solid pink"; $lds=($days) ? min($days)."-".count($days):"$tdy-0";
		$btx = ($wbal>=$tchj) ? "<button class='btnn' style='width:100%' onclick=\"rolloverln('$lid','$lds:$charge:$intr:$arl')\"><i class='bi-clipboard-check'></i> RollOver Loan</button>":
		"<p style='$error'><i class='bi-exclamation-circle'></i> Insufficient Balance to Roll-Over Loan</p>"; $sbtx=($mns>0) ? $btx:""; 
		
		$dnt = ($days) ? "<span style='float:right'>Pay From</span>":""; $mxd=$mnd+(86400*$intv); $mnv=$mnd-(86400*$intv*0.25);
		$din = ($days) ? "<input type='date' style='width:110px;float:right;font-size:14px;padding:4px' value='$stdy' min='".date("Y-m-d",$mnv)."' max='".date("Y-m-d",$mxd)."'
		onchange=\"popupload('loans.php?rolloverln=$lid&mon=$mns&std='+this.value.trim())\">":"";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:500px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>RollOver Loan #$lid</h3><br>
			<table style='width:100%;font-size:15px' cellpadding='7'>
				<tr style='background:#f0f0f0'><td colspan='2'><b>$cname</b></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($lamnt)."</b><br><span style='color:grey;font-size:14px'>$pname Loan</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$disb)."</b><br><span style='color:grey;font-size:14px'>Disbursement</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($paid)."</b><br><span style='color:grey;font-size:14px'>Total Paid</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$exp)."</b><br><span style='color:grey;font-size:14px'>Loan Maturity</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($bal)."</b><br><span style='color:grey;font-size:14px'>Total Balance</span></td>
				<td style='text-align:right'><b>Ksh ".fnum($pen)."</b><br><span style='color:grey;font-size:14px'>Penalties</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($tprc)."</b><br><span style='color:grey;font-size:14px'>Principal Balance</span></td>
				<td style='text-align:right'><b>Ksh ".fnum($intr)."</b><br><span style='color:grey;font-size:14px'>Accrued Interest</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".fnum($arp)."</b><br><span style='color:grey;font-size:14px'>Principal Arrears</span></td>
				<td style='text-align:right'><b>Ksh ".fnum($ari)."</b><br><span style='color:grey;font-size:14px'>Interest Arrears</span></td></tr>
				<tr><td>Additional Days $dnt<br><select style='width:100px;padding:6px' onchange=\"compute(this.value)\">$mls</select> $din</td>
				<td style='text-align:right;font-weight:bold;color:#191970'>RollOver Fees : Ksh ".fnum($charge)."<br>
				Total ToPay : Ksh ".fnum($tchj)."</td></tr><tr><td colspan='2'><b>Payment schedule</b><br>
					<table style='width:100%;font-size:14px;text-align:center' cellpadding='5'> 
						<tr style='background:#f0f0f0;color:#191970;font-weight:bold'><td style='text-align:left'>Date</td><td>Principal</td><td>Interest</td>
						<td>Installment</td></tr> $trs
					</table><br>
				</td></tr><tr><td colspan='2' style='text-align:right'>$btx</td></tr>
			</table><br>
		</div>
		<script>
			function compute(mon){
				popupload('loans.php?rolloverln=$lid&mon='+mon);
			}
		</script>";
	}
	
	# offset Loan
	if(isset($_GET["offsetln"])){
		$lid = trim($_GET["offsetln"]);
		$ofam = (isset($_GET["ofam"])) ? intval($_GET["ofam"]):0;
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `loan`='$lid'"); 
		
		$bal=$qri[0]['balance']; $pen=$qri[0]['penalty']; $lamnt=$qri[0]['amount']; $exp=$qri[0]['expiry']; $ldur=$qri[0]['duration'];
		$paid = $qri[0]['paid']; $disb=$qri[0]['disbursement']; $pid=$qri[0]['loan_product']; $idno=$qri[0]['client_idno'];
		$tdy=strtotime(date("Y-M-d")); $bals=getLoanBals($lid); $intr=$bals["interest"]; $tprc=$bals["principal"];
		
		$prod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$pid'")[0];
		$pdef = json_decode($prod["pdef"],1); $fee=(isset($pdef["offees"])) ? $pdef["offees"]:0; 
		$charge = (count(explode("%",$fee))>1) ? round($ofam*explode("%",$fee)[0]/100):$fee; $wbal=0;
		$intrtp = (isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR"; $prbal=$tprc-$ofam; $mnf=sys_constants("min_offset_fees");
		$cdef = ($mnf) ? $mnf:$charge; $charge=($cdef>$charge) ? $cdef:$charge;
		
		if($db->istable(3,"wallets$cid")){
			$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id'];
			$chk = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
			if($chk){ $wbal=walletBal($chk[0]['id']); }
		}
		
		$pays=$payat=$days=$adds=[]; $arrs["principal"]=$arrs["interest"]=0; $trs="";
		foreach(json_decode($prod["payterms"],1) as $des=>$pay){
			$val = explode(":",$pay); 
			$amnt = (count(explode("%",$val[1]))>1) ? round($prbal*explode("%",$val[1])[0]/100):$val[1];
			if($val[0]==2){ $pays[$des]=$amnt; }
			if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
			if($val[0]==5){ $adds[$des]=$amnt; }
		}
		
		$qry = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0 ORDER BY `day` ASC");
		if($qry){
			foreach($qry as $row){ 
				if($row['day']>=$tdy){ $days[]=$row['day']; }
				else{
					$brek = json_decode($row['breakdown'],1);
					foreach($brek as $pay=>$sum){
						if($sum!="varying"){
							$val=($pay=="interest") ? $row['interest']:$sum;
							foreach(json_decode($row['payments'],1) as $one){ $val-=(isset($one[$pay])) ? $one[$pay]:0; }
							if(isset($arrs[$pay])){ $arrs[$pay]+=$val; }else{ $arrs[$pay]=$val; }
						}
					}
				}
			}
			
			if($intrtp=="FR"){
				if(count($days)){
					$perin = count($days); $inc=$prod['interest']; $intrs=(count(explode("%",$inc))>1) ? round($prbal*explode("%",$inc)[0]/100):$inc;
					$totpay=$tpay=$prbal+($ldur/$prod['interest_duration']*$intrs); $inst=ceil($totpay/$perin); $int=round(($totpay-$prbal)/$perin); 
					$princ=$inst-$int; $arr=array('principal'=>$princ,"interest"=>$int)+$pays; $tpr=$prbal; $other=array_sum($pays);
					foreach($days as $i=>$dy){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $tpay+=round($sum/$perin); }
						$prc = ($princ>$tpr) ? $tpr:$princ; $arr['principal']=$prc; $tpr-=$prc; $tpay+=$other;
						$i++; $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $day=date("d-m-Y",$dy); $tpay+=$cut; 
						$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $inst=array_sum($brek); $sinst=($inst>$tpay) ? $tpay:$inst; $tpay-=$sinst;
						$trs.= "<tr><td style='text-align:left'>$day</td><td>".number_format($prc)."</td><td>".number_format($int)."</td><td>".number_format($sinst)."</td></tr>";
					}
				}
			}
			else{
				if(count($days)){
					$perin = count($days); $pinst=ceil($prbal/$perin); $arr=array('principal'=>$pinst,"interest"=>0)+$pays;
					$calc = reducingBal($prbal,explode("%",$prod['interest'])[0],$perin);
					foreach($days as $i=>$dy){
						foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); }
						$i++; $cut=(isset($payat[$i])) ? $payat[$i][1]:0; $prc=$calc["principal"][$i]; $arr['principal']=$prc; $day=date("d-m-Y",$dy);
						$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $int=$calc["interest"][$i]; $brek["interest"]=$int; $sinst=array_sum($brek);  
						$trs.= "<tr><td style='text-align:left'>$day</td><td>".number_format($prc)."</td><td>".number_format($int)."</td><td>".number_format($sinst)."</td></tr>";
					}
				}
			}
		}
		
		$cname = prepare(ucwords($qri[0]['client'])); $pname=prepare(ucwords($prod["product"]));
		$arp=$arrs["principal"]; $tarr=array_sum($arrs); $ari=$arrs["interest"]; $tchj=$charge+$ofam+$pen;
		if($wbal>0 && $tarr>0){
			$cut = ($tarr>$wbal) ? $wbal:$tarr;
			if(payFromWallet($uid,$cut,"client","Loan #$lid Repayment withdrawal",0)){
				echo "<script> loadpage('loans.php?offsetln=$lid'); </script>"; exit();
			}
		}
		
		$error = "padding:10px;text-align:center;color:#ff4500;background:#FFE4B5;border:1px solid pink";
		$btx = ($wbal>=$tchj) ? "<button class='btnn' style='width:100%' onclick=\"offsetloan('$lid','$charge:$ofam:$pen')\"><i class='bi-clipboard-check'></i> Offset Loan</button>":
		"<p style='$error'><i class='bi-exclamation-circle'></i> Insufficient Balance to Offset Loan</p>";
		$btx = ($ofam>$tprc) ? "<p style='$error'><i class='bi-exclamation-circle'></i> Offset Amount exceeds Principal Balance</p>":$btx;
		$sbtx = ($ofam>0) ? $btx:"";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:500px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Offset Loan #$lid</h3><br>
			<table style='width:100%;font-size:15px' cellpadding='7'>
				<tr style='background:#f0f0f0'><td colspan='2'><b>$cname</b></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($lamnt)."</b><br><span style='color:grey;font-size:14px'>$pname Loan</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$disb)."</b><br><span style='color:grey;font-size:14px'>Disbursement</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($paid)."</b><br><span style='color:grey;font-size:14px'>Total Paid</span></td>
				<td style='text-align:right'><b>".date('d-m-Y, H:i',$exp)."</b><br><span style='color:grey;font-size:14px'>Loan Maturity</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($bal)."</b><br><span style='color:grey;font-size:14px'>Total Balance</span></td>
				<td style='text-align:right'><b>Ksh ".number_format($pen)."</b><br><span style='color:grey;font-size:14px'>Penalties</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($tprc)."</b><br><span style='color:grey;font-size:14px'>Principal Balance</span></td>
				<td style='text-align:right'><b>Ksh ".number_format($intr)."</b><br><span style='color:grey;font-size:14px'>Accrued Interest</span></td></tr>
				<tr style='border-bottom:1px solid #dcdcdc'><td><b>Ksh ".number_format($arp)."</b><br><span style='color:grey;font-size:14px'>Principal Arrears</span></td>
				<td style='text-align:right'><b>Ksh ".number_format($ari)."</b><br><span style='color:grey;font-size:14px'>Interest Arrears</span></td></tr>
				<tr><td>Offset Amount<br><input type='number' value='$ofam' style='width:100px;padding:4px' id='ofam'> <button class='btnn' style='padding:5px'
				onclick='compute()'>Compute</button></td><td style='text-align:right;font-weight:bold;color:#191970'>Offset Fees : Ksh ".number_format($charge)."<br>
				Total ToPay : Ksh ".number_format($tchj)."</td></tr><tr><td colspan='2'><b>Payment schedule</b><br>
					<table style='width:100%;font-size:14px;text-align:center' cellpadding='5'>
						<tr style='background:#f0f0f0;color:#191970;font-weight:bold'><td style='text-align:left'>Date</td><td>Principal</td><td>Interest</td>
						<td>Installment</td></tr> $trs
					</table><br>
				</td></tr><tr><td colspan='2' style='text-align:right'>$sbtx</td></tr>
			</table><br>
		</div>
		<script>
			function compute(){
				var amt=$('#ofam').val().trim();
				popupload('loans.php?offsetln=$lid&ofam='+amt);
			}
		</script>";
	}
	
	# mobile app loans
	if(isset($_GET['apprep'])){
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$fro = (isset($_GET['fro'])) ? intval(strtotime(trim($_GET['fro']))):strtotime(date("Y-M"));
		$dtu = (isset($_GET['dto'])) ? intval(strtotime(trim($_GET['dto']))):strtotime(date("Y-M-d"));
		$fro = ($fro) ? $fro:strtotime(date("Y-M")); $dtu=($dtu) ? $dtu:strtotime(date("Y-M-d"));
		$dfro = ($dtu<$fro) ? $dtu:$fro; $dto=($fro>$dtu) ? $fro:$dtu; $dto+=86399;
		
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles'])); 
		$cnf = json_decode($me["config"],1); $active=[];
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
			if($row['status']==0){ $active[]=$row['id']; }
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND loan_officer='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
		$cfield = (in_array($me['access_level'],["hq","region"])) ? "branch":"loan_officer";
		$ttl = (in_array($me['access_level'],["hq","region"])) ? "Branch":"Loan Officer";
		$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=$tots=$ttag=0; $tags=$pids=[];
		
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT(clientdes,'$.agent')=$sid";
		}
		
		$sqi = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='targetsb4' AND `client`='$cid'");
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		foreach($qri as $row){
			$id=$row['id']; $pdef[]="'$id'";
		}
		
		$mfr = monrange(date("m",$dfro),date("Y",$dfro))[0];
		if($db->istable(2,"org$cid"."_targets")){
			$cols = $db->tableFields(2,"org$cid"."_targets"); 
			if(!in_array("apploans",$cols)){ $db->execute(2,"ALTER TABLE `org$cid"."_targets` ADD `apploans` INT NOT NULL AFTER `loanbook`"); }
			if($cfield=="loan_officer"){
				$qri = $db->query(2,"SELECT DISTINCT `officer` FROM `org$cid"."_targets` WHERE `month` BETWEEN $mfr AND $dto AND `apploans`>0");
				if($qri){
					foreach($qri as $row){ $active[]=$row["officer"]; }
				}
			}
		}
		
		$fetch=$cond; $cond.= "AND $ltbl.loan_product IN (".implode(",",$pdef).")"; $sday=($sqi) ? $sqi[0]['value']:5; 
		$selec = ($cfield=="branch") ? "`branch`":"`branch`,`loan_officer`"; $lis=[];
		$res = $db->query(2,"SELECT $selec FROM `$ltbl` WHERE 1 $fetch GROUP BY $cfield");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d")); $ccol=str_replace("loan_officer","officer",$cfield); $set=""; $ok=1;
				if($cfield=="loan_officer"){ $ok=(in_array($def,$active) && $mybran[$def]==$rw["branch"]) ? 1:0; }
				if($ok){
					$qri = $db->query(2,"SELECT SUM(apploans) AS ttag FROM `org$cid"."_targets` WHERE `month` BETWEEN $mfr AND $dto AND $ccol='$def'");
					$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' 
					AND `disbursement` BETWEEN $dfro AND $dto $cond");
					$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
					BETWEEN $dfro AND $dto AND sd.day<$today AND $cfield='$def' $cond"); 
					
					$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tot=$sq1[0]['tot']; $lis[]=$def;
					$tag=($qri) ? intval($qri[0]['ttag']):0; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd; $tots+=$tot; $ttag+=$tag;
					$name = (in_array($me['access_level'],["hq","region"])) ? $bnames[$def]:$staff[$def]; $gc=($tpv) ? round($tpd/$tpv*100,2):0; 
					if($cfield=="loan_officer" && $sday>=intval(date("d")) && $mfr==strtotime(date("Y-M"))){
						if($tag){
							$set = (in_array("edit targets",$perms)) ? "<i class='bi-pencil-square' style='cursor:pointer;font-size:17px;color:#008fff' title='Update Target for $name'
							onclick=\"changetarget('apploans','$def','$tag')\"></i>":"";
						}
						else{
							$set = (in_array("set targets",$perms)) ? "<i class='fa fa-plus' style='cursor:pointer;font-size:17px;color:#008fff' title='Set Target for $name'
							onclick=\"changetarget('apploans','$def','$tag')\"></i>":"";
						}
					}
					
					$trs.= "<tr><td style='float:left'>$name</td><td>".fnum($tag)." $set</td><td>".fnum($pv)."</td><td>".fnum($tot)."</td><td>".fnum($tpv)."</td><td>".fnum($tpd)."</td>
					<td>".fnum($arr)."</td><td>$gc%</td></tr>";
				}
			}
			
			$tpc = ($tpf) ? round($tpds/$tpf*100,2):0;
			$trs.= "<tr style='color:#191970;font-weight:bold;background:linear-gradient(to top,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff'>
			<td>Totals</td><td>".fnum($ttag)."</td><td>".fnum($tln)."</td><td>".fnum($tots)."</td><td>".fnum($tpf)."</td><td>".fnum($tpds)."</td><td>".fnum($tarr)."</td><td>$tpc%</td></tr>";
		}
		
		$frd=date("Y-m-d",$dfro); $dtu=date("Y-m-d",$dto);
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE 1 $fetch $add");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brn.= "<option value='$rid' $cnd>".$bnames[$rid]."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?apprep&fro=$frd&dto=$dtu&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('loans.php?apprep&fro=$frd&dto=$dtu&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$sql = $db->query(2,"SELECT MIN(disbursement) AS mnd,MAX(disbursement) AS mxd FROM `$ltbl` WHERE 1 $cond");
		$mnd = ($sql) ? $sql[0]['mnd']:strtotime("Yesterday"); $mxd=($sql) ? $sql[0]['mxd']:strtotime("Today");
		
		$src = base64_encode($fetch); $mdy=date("Y-m-d",$mnd); $mxdy=date("Y-m-d",$mxd);
		$prnt = ($trs) ? genrepDiv("collections.php?src=$src&v=apprep&dy=$fro:$dto&br=$bran&cf=$cfield&lis=".implode(":",$lis),'right'):"";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Mobile Application Loans</h3>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:center;min-width:600px;' cellpadding='5'> 
						<caption style='caption-side:top'> $prnt
							<input type='date' style='font-size:14px;width:120px;padding:4px 5px' id='mindy' onchange=\"setdays()\" value='$frd' min='$mdy' max='$mxdy'> To
							<input type='date' style='font-size:14px;width:120px;padding:4px 5px' id='maxdy' onchange=\"setdays()\" value='$dtu' min='$mdy' max='$mxdy'> $brans
						</caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td style='float:left'>$ttl</td>
						<td>Target</td><td>Disbursement</td><td>Total Loans</td><td>Collections</td><td>Paid</td><td>Arrears</td><td>GC%</td></tr> $trs
					</table>
				</div>
			</div>
		</div>
		<script>
			function setdays(){
				loadpage('loans.php?apprep&fro='+$('#mindy').val()+'&dto='+$('#maxdy').val());
			}
		</script>";
		savelog($sid,"Viewed $frd to $dtu Mobile App loans report");
	}
	
	# mtd
	if(isset($_GET['dmtd'])){
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$fro = (isset($_GET['fro'])) ? intval(strtotime(trim($_GET['fro']))):strtotime(date("Y-M"));
		$dtu = (isset($_GET['dto'])) ? intval(strtotime(trim($_GET['dto']))):strtotime(date("Y-M-d"));
		$fro = ($fro) ? $fro:strtotime(date("Y-M")); $dtu=($dtu) ? $dtu:strtotime(date("Y-M-d"));
		$dfro = ($dtu<$fro) ? $dtu:$fro; $dto=($fro>$dtu) ? $fro:$dtu; $dto+=86399;
		
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$me = staffInfo($sid); $access=$me['access_level'];
		$cnf = json_decode($me["config"],1);
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND loan_officer='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
		$cfield = (in_array($me['access_level'],["hq","region"])) ? "branch":"loan_officer";
		$ttl = (in_array($me['access_level'],["hq","region"])) ? "Branch":"Loan Officer";
		$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=$tots=0; $showsum=1;
		if(defined("HIDE_USERS_DASHBOARD")){
			$showsum = (in_array($me["position"],HIDE_USERS_DASHBOARD)) ? 0:1;
		}
		
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT(clientdes,'$.agent')=$sid";
		}
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `disbursement` BETWEEN $dfro AND $dto $cond");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d")); 
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' AND `disbursement` BETWEEN $dfro AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
				BETWEEN $dfro AND $dto AND sd.day<$today AND $cfield='$def' $cond"); 
				
				$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tot=$sq1[0]['tot']; 
				$tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd; $tots+=$tot; $spv=($showsum) ? fnum($pv):"--"; $spvt=($showsum) ? fnum($tpv):"--";
				$name = (in_array($me['access_level'],["hq","region"])) ? $bnames[$def]:$staff[$def]; $gc=round($tpd/$tpv*100,2); $stpd=($showsum) ? fnum($tpd):"--"; 
				$trs.= "<tr><td style='float:left'>$name</td><td>$spv</td><td>".fnum($tot)."</td><td>$spvt</td><td>$stpd</td>
				<td>".fnum($arr)."</td><td>$gc%</td></tr>";
			}
			
			$td1 = ($showsum) ? fnum($tln):"--"; $td2=($showsum) ? fnum($tpf):"--"; $td3=($showsum) ? fnum($tpds):"--";
			$trs.= "<tr style='color:#191970;font-weight:bold;background:linear-gradient(to top,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff'>
			<td>Totals</td><td>$td1</td><td>".fnum($tots)."</td><td>$td2</td><td>$td3</td><td>".fnum($tarr)."</td>
			<td>".round($tpds/$tpf*100,2)."%</td></tr>";
		}
		
		$frd=date("Y-m-d",$dfro); $dtu=date("Y-m-d",$dto);
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE `disbursement` BETWEEN $dfro AND $dto $add");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brn.= "<option value='$rid' $cnd>".$bnames[$rid]."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?dmtd&fro=$frd&dto=$dtu&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('loans.php?dmtd&fro=$frd&dto=$dtu&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$sql = $db->query(2,"SELECT MIN(disbursement) AS mnd,MAX(disbursement) AS mxd FROM `$ltbl` WHERE 1 $cond");
		$mnd = ($sql) ? $sql[0]['mnd']:strtotime("Yesterday"); $mxd=($sql) ? $sql[0]['mxd']:strtotime("Today");
		
		$src = base64_encode($cond); $mdy=date("Y-m-d",$mnd); $mxdy=date("Y-m-d",$mxd);
		$prnt = ($trs) ? genrepDiv("collections.php?src=$src&v=dmtd&dy=$fro:$dto&br=$bran&cf=$cfield",'right'):"";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Progressive Disbursements</h3>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:center;min-width:600px;' cellpadding='5'> 
						<caption style='caption-side:top'> $prnt
							<input type='date' style='font-size:14px;width:120px;padding:4px 5px' id='mindy' onchange=\"setdays()\" value='$frd' min='$mdy' max='$mxdy'> To
							<input type='date' style='font-size:14px;width:120px;padding:4px 5px' id='maxdy' onchange=\"setdays()\" value='$dtu' min='$mdy' max='$mxdy'> $brans
						</caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td style='float:left'>$ttl</td>
						<td>Disbursed Amount</td><td>Total Loans</td><td>Loan+Charges</td><td>Paid</td><td>Arrears</td><td>GC%</td></tr> $trs
					</table>
				</div>
			</div>
		</div>
		<script>
			function setdays(){
				loadpage('loans.php?dmtd&fro='+$('#mindy').val()+'&dto='+$('#maxdy').val());
			}
		</script>";
		savelog($sid,"Viewed $frd to $dtu disbursement MTD");
	}
	
	# loan arrears
	if(isset($_GET['arrears'])){
		$arr = trim($_GET['arrears']);
		$tdy = strtotime(date("Y-M-d"));
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$fto = (isset($_GET['fto'])) ? trim($_GET['fto']):0;
		$vtp = (isset($_GET['follow'])) ? trim($_GET['follow']):null;
		$from = ($arr) ? strtotime($arr):strtotime(date("Y-M-d",$tdy-(86400*7)));
		$to = ($fto) ? strtotime($fto):$tdy-1;
		$to = ($from>$to) ? $from:$to;
		$view = (isset($_GET['fetch'])) ? trim($_GET['fetch']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['stid'])) ? clean($_GET['stid']):0;
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$qri = $db->query(2,"SELECT * FROM `org".$cid."_clients`");
		if($qri){
			foreach($qri as $row){ $cycles[$row['idno']]=$row['cycles']; }
		}
		
		$perpage = 40;
		$lim = getLimit($page,$perpage); $sfrm=0;
		$me = staffInfo($sid); $access=$me['access_level'];
		$cnf = json_decode($me["config"],1);
		
		if($vtp){
			$sql = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='penalty_from_day' AND `client`='$cid'");
			$sfrm = ($sql) ? $sql[0]['value']*86400:0; $from-=$sfrm; $to-=$sfrm;
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":" AND ln.branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? " AND sd.officer='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"],"ln.branch"):$cond;
		$cond.= ($view==1) ? " AND ln.expiry<=$to":"";
		$cond.= ($view==2) ? " AND ln.expiry>$to":"";
		$cond.= ($stid) ? " AND sd.officer='$stid'":"";
		
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT(ln.clientdes,'$.agent')=$sid";
		}
		
		$no=($perpage*$page)-$perpage; $trs=$offs=$brns=""; $total=$all=$sum=0; $isport=($access=="portfolio") ? 1:0;
		$res = $db->query(2,"SELECT ln.branch,sd.idno,sd.officer,MIN(sd.day) AS dy,sd.loan,ln.balance,ln.client,ln.phone,ln.amount AS tloan,ln.expiry,SUM(sd.balance) AS tbal,ln.disbursement 
		FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan WHERE sd.balance>0 AND sd.day BETWEEN '$from' AND '$to' $cond GROUP BY sd.loan ORDER BY sd.day,sd.balance DESC $lim");
		if($res){
			foreach($res as $row){
				$idno=$row['idno']; $ofid=$row['officer']; $day=$row['dy']; $tbal=$row['balance']; $bal=fnum($row['tbal']);
				$client=prepare(ucwords($row['client'])); $fon=$row['phone']; $loan=fnum($row['tloan']); $lid=$row['loan'];
				$qri = $db->query(2,"SELECT SUM(balance) AS tbal FROM `$stbl` WHERE `loan`='$lid' AND `day`<$tdy");
				$arrs = $qri[0]['tbal']; $inst=getInstall($lid,$day); $sum+=$arrs; $total+=$row['tbal']; $all+=$row['balance']; $no++;
				$tdys = floor((strtotime(date("Y-M-d"))-$day)/86400); $cycle=$cycles[$idno]; $dsd=date("d-m-Y",$row["disbursement"]);
				$tad = (!$bran && !$isport) ? "<td>".$brans[$row['branch']]."</td>":""; 
				$tad.= (!$stid && !$isport) ? "<td>$staff[$ofid]</td>":"";
				
				$trs.= "<tr onclick=\"loadpage('clients.php?showclient=$idno')\" style='cursor:pointer'><td>$no</td><td>$client</td><td>0$fon</td>$tad<td>$loan</td><td>$dsd</td><td>$cycle</td>
				<td>$bal</td><td>".fnum($arrs)."</td><td>$inst</td><td>".date("d-m-Y",$day)."</td><td>$tdys</td><td>".fnum($tbal)."</td></tr>";
			}
			
			$csp=6; $csp+=(!$bran && !$isport) ? 1:0; $csp+=(!$stid && !$isport) ? 1:0; 
			$trs.= "<tr style='font-weight:bold;color:#191970'><td colspan='$csp' style='text-align:right'>Totals</td>
			<td>".fnum($total)."</td><td>".fnum($sum)."</td><td></td><td></td><td></td><td>".fnum($all)."</td></tr>";
		}
		
		$grups = "<option value='0'>-- Filter Loans --</option>";
		$array = array(1=>"Overdue Loans",2=>"Running Loans");
		foreach($array as $key=>$des){
			$cnd=($view==$key && strlen($view)>0) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?arrears=$arr&fto=$fto&follow=$vtp&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:140px;font-size:15px' onchange=\"loadpage('loans.php?arrears=$arr&fto=$fto&follow=$vtp&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `$stbl` WHERE day BETWEEN '$from' AND '$to' AND `balance`>0");
			if($res){
				$opts = "<option value='0'>-- Portfolio --</option>";
				foreach($res as $row){
					if($me['access_level']=="branch"){
						if($me['branch']==$mybran[$row['officer']]){
							$off=$row['officer']; $cnd=($off==$stid) ? "selected":"";
							$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
						}
					}
					else{
						$off=$row['officer']; $cnd=($off==$stid) ? "selected":"";
						$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
					}
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('loans.php?arrears=$arr&fto=$fto&bran=$bran&follow=$vtp&stid='+this.value.trim())\">$opts</select>";
			}
		}
		
		$src = base64_encode($cond);
		$sql = $db->query(2,"SELECT DISTINCT sd.loan,COUNT(*) AS total FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan 
		WHERE sd.balance>0 AND sd.day BETWEEN '$from' AND '$to' $cond "); $totals = ($sql) ? $sql[0]['total']:0;
		$prnt = ($totals) ? genrepDiv("arrears.php?src=$src&br=$bran&stid=$stid&ftc=$from:$to&v=$view",'right'):"";
		
		$tarr = array("","Overdue","Running"); $dtl=($vtp) ? ceil($sfrm/86400)." Days++ Arrears":"Loan Arrears";
		$title = $tarr[$view]." $dtl from ".date("d-m-Y",$from)." to ".date("d-m-Y",$to);
		
		$res = $db->query(2,"SELECT MIN(day) AS mnday,MAX(day) AS mxday FROM `$stbl` WHERE `balance`>0 AND `day`<".strtotime(date("Y-M-d")));
		$mindy = ($res) ? $res[0]['mnday']:strtotime(date("Y-M-d"))-172800; $maxdy = ($res) ? $res[0]['mxday']:strtotime(date("Y-M-d"))-1;
		$min1 = date("Y-m-d",$mindy-$sfrm); $max1=date("Y-m-d",$maxdy-(86400+$sfrm)); $min2=date("Y-m-d",($mindy+86400)-$sfrm); $max2=date("Y-m-d",$maxdy-$sfrm);
		
		$perms = getroles(explode(",",$me['roles'])); 
		$tadd = (!$bran && !$isport) ? "<td>Branch</td>":""; $tadd.=(!$stid && !$isport) ? "<td>Loan Officer</td>":"";
		$sms = ($trs && in_array("send sms",$perms)) ? "<button class='bts' style='float:right;padding:2px' 
		onclick=\"popupload('loans.php?sendsms=$from&sto=$to&src=$src')\"><i class='fa fa-envelope-o'></i> Send SMS</button>":"";
		
		echo "<div class='container cardv' style='max-width:1300px;min-height:400px;overflow:auto'>
			<div style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-hover' style='width:100%;font-size:15px;min-width:700px;' cellpadding='5'>
						<caption style='caption-side:top'>
							<p>Select Period<br> $sms
								<input type='date' style='width:160px;padding:4px' id='arr1' onchange=\"setarrange('$vtp','arr',this.value)\" value='".date('Y-m-d',$from)."' min='$min1' max='$max1'>
								To: <input type='date' style='width:160px;padding:4px' id='fto1' onchange=\"setarrange('$vtp','fto',this.value)\" value='".date('Y-m-d',$to)."' min='$min2' max='$max2'>
							</p> <p style='margin:0px'>
							<select style='width:150px' onchange=\"loadpage('loans.php?arrears=$arr&fto=$fto&follow=$vtp&fetch='+cleanstr(this.value))\">$grups</select>
							$brns $offs $prnt</p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Client</td><td>Contact</td>$tadd
						<td>Loan</td><td>Disbursement</td><td>Cycles</td><td>P.Arrears</td><td>Accumulated</td><td>Installment</td><td>Fall Date</td><td>Days</td><td>T.Bal</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"loans.php?arrears=$arr&reg=$rgn&bran=$bran&stid=$stid&fto=$fto&fetch=$view&follow=$vtp")."
		</div>";
		savelog($sid,"Viewed $title");
	}
	
	# send sms
	if(isset($_GET['sendsms'])){
		$from = trim($_GET['sendsms']);
		$sto = trim($_GET['sto']);
		$src = str_replace(" ","+",trim($_GET['src']));
		$me = staffInfo($sid); 
		
		$cond = ($src) ? base64_decode($src):"";
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$tdy = strtotime(date("Y-M-d")); $all=$lis=[];
		
		$qri = $db->query(2,"SELECT sd.idno,sd.officer,MIN(sd.day) AS dy,sd.loan,ln.balance,ln.client,ln.phone,ln.amount AS tloan,ln.expiry,SUM(sd.balance) AS tbal FROM `$stbl` AS sd
		INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan WHERE sd.balance>0 AND sd.day BETWEEN '$from' AND '$sto' $cond GROUP BY sd.loan ORDER BY sd.day,sd.balance");
		foreach($qri as $row){
			$fon="254".$row['phone']; $amnt=$row['tbal']; $lid=$row['loan']; $name=$row['client'];
			if(array_key_exists($fon,$lis)){ $lis[$fon]["amount"]+=$amnt; }
			else{
				$res = $db->query(2,"SELECT MIN(day) AS mdy,SUM(balance) AS tbal FROM `$stbl` WHERE `loan`='$lid' AND `day`<$tdy AND `balance`>0");
				$lis[$fon]=array("amount"=>$amnt,"from"=>$from,"name"=>$name,"idno"=>$row['idno']); 
				$all[$fon]=array("amount"=>$res[0]['tbal'],"from"=>$res[0]['mdy'],"name"=>$name,"idno"=>$row['idno']);
			}
		}
		
		$tot = (count($lis)==1) ? "1 Client":count($lis)." Clients";
		$js1 = json_encode($lis); $js2 = json_encode($all); $fdy=date("d-m-Y",$from); $tdy=date("d-m-Y",$sto);
		
		insertSQLite("tempinfo","CREATE TABLE IF NOT EXISTS sitecache (cache TEXT NOT NULL UNIQUE,data TEXT)");
		$qri = fetchSQLite("tempinfo","SELECT *FROM `sitecache` WHERE `cache`='arrearsms'");
		$sms = (count($qri)) ? prepare($qri[0]['data']):
		"Dear CLIENT,\rKindly pay your arrears that have accumulated to ARREARS from STARTDAY using A/C IDNO to avoid your securities being auctioned. Thank you.";
		
		$ftc = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$ftc = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":$ftc;
		
		$opts="<option value='0'> -- Select --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' $ftc");
		foreach($res as $row){
			$opts.="<option value='254".$row['contact']."'>".prepare(ucwords($row['name']))."</option>";
		}
		
		echo "<div style='margin:0 auto;max-width:380px;padding:10px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Send SMS to $tot</h3><br>
			<form method='post' id='sform' onsubmit=\"smsclients(event)\">
				<input type='hidden' name='smlim' value='$js1'> <input type='hidden' name='small' value='$js2'>
				<p style='color:#2f4f4f;font-size:14px'>Use words <b>CLIENT, IDNO, ARREARS & STARTDAY</b> to pick client name,idno,arrears amount & fall date respectively</p>
				<p>Type Message<br><textarea name='rmsg' class='mssg' style='height:140px;font-size:15px' onkeyup=\"countext('tlen',this.value)\" required>$sms</textarea>
				<br><span id='tlen' style='color:#191970'>".strlen($sms)." chars, 1 Mssg</span></p>
				<p>Arrears to Pick <span style='float:right'>Send Sample to</span><br>
				<select name='rmsto' style='width:48%;font-size:15px;cursor:pointer'><option value='smlim'>Period Arrears</option>
				<option value='small'>Whole Arrears</option></select> <select name='addto' style='width:49%;cursor:pointer;float:right'>$opts</select></p><br>
				<p style='text-align:right'><button class='btnn'>Send</button></p>
			</form><br>
		</div>";
	}
	
	# checkoff loans
	if(isset($_GET['checkoff'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$rgn = (isset($_GET['reg'])) ? clean($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$me = staffInfo($sid); $access=$me['access_level'];
		$perpage = 50; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		$stbl = "org".CLIENT_ID."_staff"; $staff[0]="System";
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		$cond.= ($stid) ? " AND `officer`='$stid'":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$no=($perpage*$page)-$perpage; $trs=$offs=$brans=$brns="";
		$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND `month`='$mon' $cond GROUP BY code ORDER BY payid ASC $lim");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $rc=$row['receipt']; $ofname=$staff[$row['officer']];
				$day=date("d-m-Y, H:i",$row['time']); $conf=$staff[$row['confirmed']]; $amnt=number_format($row['tamnt']); $no++;
				
				$trs.="<tr><td>$no</td><td>$name</td><td>$idno</td><td>$amnt</td><td>$ofname</td><td>$conf</td><td>$day</td><td>$rc</td></tr>";
			}
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?checkoff&mon=$mon&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('loans.php?checkoff&mon=$mon&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `processed_payments$cid` WHERE $brn AND `month`='$mon'");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('loans.php?checkoff&mon=$mon&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$tmon = strtotime(date("Y-M"));
		$mons = "<option value='$tmon'>".date("M Y")."</option>";
		$res = $db->query(2,"SELECT DISTINCT `month` FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND NOT `month`='$tmon' $cond ORDER BY month DESC");
		if($res){
			foreach($res as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$sql = $db->query(2,"SELECT DISTINCT code FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND `month`='$mon' $cond");
		$qri = $db->query(2,"SELECT DISTINCT code,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND `month`='$mon' $cond");
		$totals = ($sql) ? count($sql):0; $tamnt = ($qri) ? $qri[0]['tamnt']:0;
		$prnt = ($totals) ? genrepDiv("checkoff.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&mn=$mon",'right'):"";
		
		echo "<div class='container cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div style='padding:7px;min-width:500px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Checkoff Loans (".number_format($tamnt).")</h3>
				<table class='table-striped table-bordered' style='width:100%;font-size:15px;margin-top:15px' cellpadding='5'>
					<caption style='caption-side:top'>
						<select style='width:130px' onchange=\"loadpage('loans.php?checkoff&mon='+cleanstr(this.value))\">$mons</select>
						$brns $offs $prnt
					</caption>
					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px;' valign='top'>
					<td colspan='2'>Client</td><td>Idno</td><td>Checkoff</td><td>Loan Officer</td><td>Confirmed By</td><td>Date</td><td>Receipt</td></tr> $trs
				</table>
			</div>".getLimitDiv($page,$perpage,$totals,"loans.php?checkoff&reg=$rgn&bran=$bran&staff=$stid&mon=$mon")."
		</div>";
		savelog($sid,"Viewed Checkoff Payments");
	}
	
	# loan history
	if(isset($_GET['payhistory'])){
		$tm=trim($_GET['payhistory']); 
		$start=$tbal=$tpd=$tsum=$tint=$tprc=0; $trs=$tht=""; $ths=[]; 
		
		if($db->istable(2,"rescheduled$cid")){
			$res = $db->query(2,"SELECT *FROM `rescheduled$cid` WHERE `loan`='$tm'");
			$start = ($res) ? $res[0]['start']:0;
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_schedule` WHERE `loan`='$tm' ORDER BY `day` ASC");
		foreach($res as $row){
			$day=date("d-m-Y",$row['day']); $intr=$row['interest']; $paid=fnum($row['paid']); $bal=$row['balance']; $tpd+=$row['paid'];
			$dy=$row['day']; $today=strtotime("Today"); $id=$row['id']; $pays=json_decode($row['payments'],1); $amnt=$row['amount']; 
			$pd = (count($pays)) ? explode(":",array_reverse(array_keys($pays))[0])[1]:0; $brek=json_decode($row['breakdown'],1); 
			$bint = (isset($brek["interest"])) ? intval($brek["interest"]):0; $tds="";
			if($intr==0 && $bint>0){ $bal+=$brek["interest"]; $amnt+=$brek["interest"]; $intr=$brek["interest"]; }
			$tprc+=(isset($brek["principal"])) ? $brek["principal"]:0; $tint+=$intr;
			
			$prc = (isset($brek["principal"])) ? fnum($brek["principal"]):0; $intrst=fnum($intr); $tsum+=$amnt; $sum=fnum($amnt); $tbal+=$bal;
			$prc.= ($intr==0 && !isset($brek["interest"])) ? " <span style='background:#4682b4;font-size:12px;padding:3px 4px;color:#fff'>Offset</span>":"";
			$intrst.= (!isset($brek["principal"]) && $intr>0) ? " <span style='background:#006400;font-size:12px;padding:3px 4px;color:#fff'>RollOver</span>":"";
			
			$pday = ($pd>0) ? date("d-m-Y",$pd)." <i class='fa fa-plus' onclick=\"popupload('loans.php?showpays=$id:$tm')\" style='cursor:pointer;color:blue;'
			title='View Payments'></i>":"----"; $color="#000";
			
			if($today>$dy){
				if($pd>0){ $color=(strtotime(date("Y-M-d",$pd))>$dy) ? "#FF00FF":"#000"; }
				else{ $color=($row['balance']>0) ? "#FF00FF":"#000"; }
			}
			
			// unset($brek["principal"],$brek["interest"]);
			// foreach($brek as $py=>$amt){ $tds.="<td>".fnum($amt)."</td>"; $ths[$py]=$py; }
			
			$trs.= ($start==$row['day']) ? "<tr style='font-weight:bold;background:#f8f8f0;color:#191970;font-size:14px'><td colspan='6'>Rescheduled</td></tr>":"";
			$trs.= "<tr style='color:$color'><td>$day</td><td>$sum</td><td>$prc</td><td>$intrst</td>$tds<td>$paid</td><td>$pday</td><td>".fnum($bal)."</td></tr>";
		}
		
		$trs.= "<tr style='color:#191970;font-weight:bold'><td>Totals</td><td>".fnum($tsum)."</td><td>".fnum($tprc)."</td><td>".fnum($tint)."</td>
		<td>".fnum($tpd)."</td><td>----</td><td>".fnum($tbal)."</td></tr>";
		// foreach($ths as $col){ $tht.="<td>".ucwords(str_replace("_"," ",$col))."</td>"; }
		
		echo "<div style='max-width:100%;overflow:auto;padding:15px 5px'>
			<p style='font-weight:bold'>Loan $tm Installments</p>
			<table cellpadding='5' style='width:100%;min-width:460px;font-size:15px' class='table-bordered'>
				<tr style='font-weight:bold;background:#f0f0f0;color:#191970;font-size:14px'><td>Schedule</td><td>Amount</td><td>Principal</td><td>Interest</td>$tht
				<td>Paid</td><td>Date</td><td>Balance</td></tr> $trs
			</table>
		</div>";
	}
	
	# post loan
	if(isset($_GET['postloan'])){
		$tid = trim($_GET['postloan']);
		$ltbl = "org$cid"."_loantemplates";
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$tid'");
		$row = $res[0]; $day=date("Y-m-d",$row['status']); $dur=$row['duration']; $mx=date("Y-m-d",time()+86400);
		$amnt=fnum($row['amount']); $name=prepare(ucwords($row['client'])); $sum=$row['amount'];
		
		$chk = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `tid`='$tid' AND `amount`='$sum'");
		$btx = ($chk) ? "<p style='color:#ff4500;font-weight:bold'>Sorry, The Loan is already posted!</p>":"<p style='text-align:right'><button class='btnn'>Post</button></p>";
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Post Disbursed Loan</h3><br>
			<form method='post' id='pform' onsubmit=\"postloan(event,'$tid')\">
				<input type='hidden' name='postid' value='$tid'>
				<p>Post Loan for $name of KES $amnt for $dur days</p>
				<p>Disbursement Date<br><input type='date' name='pday' style='width:100%' value='$day' max='$mx' required></p><br> $btx
			</form><br>
		</div>";
	}
	
	# add loan Charges
	if(isset($_GET["lncharge"])){
		$lid = trim($_GET["lncharge"]);
		$opts = "<option value='0'>All Installments</option>"; $no=0;
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid'");
		if($sql){
			foreach($sql as $row){
				$id=$row['id']; $bal=$row['balance']; $no++;
				$opts.= ($bal>0) ? "<option value='$id'>Installment $no</option>":"";
			}
		}
		
		$btx = ($no) ? "<p style='text-align:right'><button class='btnn'>Charge</button></p>":"<p style='color:#ff4500;font-weight:bold'>Sorry, All installments are paid!</p>";
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Post Loan Charges</h3><br>
			<form method='post' id='cfom' onsubmit=\"chargeloan(event)\">
				<input type='hidden' name='lncharge' value='$lid'>
				<p>Payment Description(Max 20 Chars)<br><input style='width:100%' type='text' name='chargenm' maxlength='20' required></p>
				<p>Charge Amount<br><input style='width:100%' type='number' name='chajamt' required></p>
				<p>Charge on<br><select style='width:100%' name='instp' required>$opts</select><br><span style='font-size:13px'>
				<b>Note:</b> For all installments, same input amount will be added for each of them.</span></p><br> $btx
			</form><br>
		</div>";
	}
	
	# payment history for schedule
	if(isset($_GET['showpays'])){
		$data = trim($_GET['showpays']); 
		$id=explode(":",$data)[0]; $loan=explode(":",$data)[1]; $trs="";
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_schedule` WHERE `id`='$id'");
		$pays = json_decode($res[0]['payments'],1); $list=[];
		foreach($pays as $pay=>$des){
			$ded=fnum(array_sum($des)); $day=date("d-m-Y,H:i",explode(":",$pay)[1]); $pid=explode(":",$pay)[0];
			$rs = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
			$prnt = (in_array("view payment receipts",$perms) && !in_array($pid,$list)) ? "<i class='bi-printer' onclick=\"printdoc('payments.php?vtp=precpt&pyd=$pid','Receipt')\"
			style='font-size:18px;cursor:pointer;color:#008fff;' title='Print Receipt'></i>":"";
			$rw = $rs[0]; $code=$rw['code']; $acc=prepare($rw['account']); $amnt=fnum($rw['amount']);
			$trs.= "<tr><td>$day</td><td>$prnt $code</td><td>$acc</td><td>$amnt</td><td>$ded</td></tr>";
		}
		
		echo "<div style='max-width:100%;overflow:auto;padding:15px 5px'>
			<button class='bts' style='font-size:14px;padding:3px' onclick=\"popupload('loans.php?payhistory=$loan')\"><i class='fa fa-arrow-left'></i> Back</button>
			<table cellpadding='5' style='width:100%;font-size:15px;margin-top:10px;min-width:450px' class='table-striped table-bordered'>
			<tr style='font-weight:bold;background:#f0f0f0;color:#191970;font-size:14px'><td>Date</td><td>Transaction</td><td>Account</td><td>Amount</td><td>Deducted</td></tr>$trs
			</table>
		</div>";
	}
	
	# disbursements
	if(isset($_GET['disbursements'])){
		$view = trim($_GET['disbursements']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$day = (isset($_GET['day'])) ? clean($_GET['day']):0;
		$rgn = (isset($_GET['reg'])) ? clean($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$fap = (isset($_GET['fap'])) ? clean($_GET['fap']):"n";
		
		$ltbl = "org$cid"."_loantemplates";
		$fields = ($db->istable(2,$ltbl)) ? $db->tableFields(2,$ltbl):[]; $docs=[];
		$exclude = ["id","loan","time","status","payment","client","client_idno","phone","duration","prepay","checkoff","approvals","creator","branch","pref","loantype","comments","cuts","disbursed"];
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles'])); $perpage=30;
		$lim = getLimit($page,$perpage); $cnf=json_decode($me["config"],1);
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($view==null){
			$check = $db->query(2,"SELECT *FROM `$ltbl` WHERE `status`<9");
			$view = ($check) ? 2:1;
		}
		
		if($view==1){
			unset($exclude[array_search("branch",$exclude)]); $exclude[]="processing"; 
			$hidecols = (defined("HIDE_TEMPLATE_COLS")) ? HIDE_TEMPLATE_COLS:[];
			foreach($hidecols as $col){ $exclude[]=$col; }
		}
		
		$states = array("All templates","Disbursed Loans","Undisbursed Loans","Pended Loans","Declined Loans");
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		if($me['access_level']=="portfolio" && in_array("approve loan application",$perms)){ $me['access_level']="branch"; }
		$show = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "`loan_officer`='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? setRegion($cnf["region"]):$cond; $defc=$cond;
		$cond.= ($stid) ? " AND `loan_officer`='$stid'":"";
		$cond.= ($str) ? " AND (`client` LIKE '%$str%' OR `client_idno`='$str' OR `phone` LIKE '$str%' OR `payment`='$str')":"";
		$defc.= ($str) ? " AND (`client` LIKE '%$str%' OR `client_idno`='$str' OR `phone` LIKE '$str%' OR `payment`='$str')":"";
		
		$cfield = ($view==1) ? "status":"time";
		$cfield = ($view>=2) ? "time":$cfield;
		$defc .= ($view==1) ? " AND `status`>10":"";
		$defc .= ($view==3) ? " AND `pref`='8'":"";
		$defc .= ($view==4) ? " AND `status`='9'":"";
		
		$qri = $db->query(2,"SELECT from_unixtime($cfield,'%Y-%b') AS mon FROM `$ltbl` WHERE $defc GROUP BY mon");
		$res = ($qri) ? $qri:array(["mon"=>date("Y-M")]); $mons=$days=$media=[];
		foreach($res as $row){
			$mons[] = strtotime($row['mon']);
		}
		
		rsort($mons); $mon = ($mon) ? $mon:max($mons); $mns=$dys=$appftr="";
		foreach($mons as $mn){
			$cnd=($mn==$mon) ? "selected":"";
			$mns.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
		}
		
		$trange = monrange(date("m",$mon),date('Y',$mon)); $m1=$trange[0]; $m2=$trange[1]; $m2+=86399;
		$res = $db->query(2,"SELECT from_unixtime($cfield,'%Y-%b-%d') AS day FROM `$ltbl` WHERE $defc AND $cfield BETWEEN $m1 AND $m2 GROUP BY day");
		if($res){
			foreach($res as $row){ $days[] = strtotime($row['day']); }
		}
		
		rsort($days); $dys = "<option value='0'>-- Day --</option>";
		foreach($days as $dy){
			$cnd = ($dy==$day) ? "selected":"";
			$dys.="<option value='$dy' $cnd>".date('d-m-Y',$dy)."</option>";
		}
		
		if($view>=2){
			$tmon = ($mon) ? monrange(date("m",$mon),date('Y',$mon)):[]; $tto=($mon) ? $tmon[1]:0; $tto+=86399;
			$load = ($mon) ? "AND `time` BETWEEN ".$tmon[0]." AND $tto":"";
			$cond.= ($view==4) ? " AND `status`='9' $load":" AND `status`<9 $load"; 
			$cond.= ($view==3) ? " AND `pref`='8'":" AND NOT `pref`='8'";
			$cond.= ($fap!="n") ? " AND `status`='$fap'":"";
			if(in_array($view,[2,3]) && $sid==1){
				$order = "CASE WHEN `pref`<10 THEN time ELSE `pref` END ASC";
			}
			else{ $order = "$cfield DESC"; }
		}
		else{
			$cfield = ($view) ? "status":"time"; $order="$cfield DESC";
			$fro = ($day) ? $day:$mon; $fro=(isset($_GET['nlt'])) ? strtotime("-12 Month"):$fro;
			$dto = ($day) ? $day+86399:monrange(date("m",$mon),date('Y',$mon))[1]+86399; 
			$cond.= " AND `$cfield` BETWEEN $fro AND $dto";
			$cond.= (isset($_GET['nlt'])) ? " AND `loan`='0'":"";
		}
		
		$lntb = "org".$cid."_loans";
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$lntb'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			foreach($cols as $col=>$dtp){
				if(in_array($dtp,["image","pdf","docx"])){ $media[]=$col; }
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $lprods[$row['id']]=prepare(ucwords($row['product'])); }
		}
		
		$sett["loanposting"]=0; $sett["lastposted"]=0; $sett["multidisburse"]=0; $assets=[];
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='loanposting' OR `setting`='lastposted' OR `setting`='multidisburse')");
		if($sql){
			foreach($sql as $row){ $sett[$row['setting']]=prepare($row['value']); }
		}
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
		$sqp = $db->query(1,"SELECT `users` FROM `bulksettings` WHERE `status`='0' AND `client`='$cid'");
		$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $dcd=count($levels); $supers=["super user","C.E.O","Director"]; 
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		$disu = ($sqp) ? json_decode($sqp[0]["users"],1):[];
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="`client_idno`='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		if($view==2){
			$lps = "<option value='n'>-- Filter Approval --</option>";
			foreach($levels as $key=>$lev){
				$key-=1; $cnd=($key==$fap) ? "selected":"";
				$lps.= "<option value='$key' $cnd>Waiting ".prepare(ucfirst($lev))."</option>";
			}
			$appftr = "<select style='width:160px;font-size:15px;' onchange=\"loadpage('loans.php?disbursements=$view&bran=$bran&staff=$stid&mon=$mon&day=$day&fap='+this.value)\">$lps</select>";
		}
		
		if($db->istable(2,"finassets$cid")){
			$sql = $db->query(2,"SELECT *FROM `finassets$cid`");
			if($sql){
				foreach($sql as $row){ $assets[$row["id"]]=[prepare($row["asset_name"]),$row["asset_cost"]]; }
			}
		}
		
		$dc = $db->query(2,"SELECT SUM(prepay+checkoff) AS deds FROM `$ltbl` WHERE $cond");
		$deds = ($dc) ? $dc[0]['deds']:0; $ctd=0; 
		
		$no=$start=($perpage*$page)-$perpage; $trs=$ths=$offs=""; $dis=0; $appr=[];
		if($db->istable(2,$ltbl)){
			$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY $order $lim");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $idno=$row['client_idno']; $tds=$aptd=$cltd=""; $upd="----"; $no++; $st=$row['status'];
					$cuts = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $cuts=(is_array($cuts)) ? $cuts:[]; $lntp=$row['loantype'];
					$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $istop=(count($dsb)>1) ? 1:0;
					$istpp = (explode(":",$lntp)[0]=="topup") ? "topup":""; $ndr=($istpp) ? "Extra Days":"Days";
					if($view==1){
						$cyc = $db->query(2,"SELECT `cycles` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['cycles'];
						$cltd = "<td>".fnum($cyc)."</td>";
					}
					
					if(defined("LOAN_APPROVALS")){
						if(LOAN_APPROVALS){ $levels = loanApproval($row["amount"]); $dcd=count($levels); }
					}
					
					if(in_array("asset",explode(":",$lntp))){
						$pid = explode(":",$lntp)[2]; $qty=ceil($row["amount"]/$assets[$pid][1]); $qt=($qty>1) ? "$qty ":"";
						$pname = $qt.$assets[$pid][0]." (".$lprods[$row["loan_product"]].")"; $asprod=1;
					}
					else{ $pname = $lprods[$row["loan_product"]]; $asprod=0; }
					
					if($dcd){
						$dis+=($st==8 && !$asprod) ? 1:0; $plev=0; $npos=[];
						$status = array(10=>"<span style='color:green;'>".date('d.m.Y,H:i',$st)."</span>");
						foreach($levels as $pos=>$user){
							$stx = ($row['pref']==8) ? "Pended at":"Waiting"; $key=$pos-1; 
							$psn = (is_array($user)) ? implode(" / ",array_map("ucfirst",$user)):prepare(ucfirst($user));
							$status[$key]="<i style='color:grey;font-size:13px'>$stx $psn</i>";
							if($st==$key){
								$npos = (is_array($user)) ? $user:[$user]; 
								foreach($npos as $p){ $appr[str_replace(" ","_",$p)]=$key; } 
							}
						}
						
						$stz = ($istop or $istpp) ? fnum(array_sum($dsb))." / ".fnum($row['amount']):"Pending";
						$status[8] = "<i style='color:#9932CC;'>$stz</i>"; $status[9]="<i style='color:#ff4500;'>Declined</i>";
						$plev = ($st!=8 && (in_array($me['position'],$supers) or in_array($sid,$disu) or in_array($me['position'],$npos) or $st==0)) ? 1:0;
					}
					else{
						$dis+= ($st<9 && !$asprod) ? 1:0; $plev=1; $stz=($istop or $istpp) ? fnum(array_sum($dsb))." / ".fnum($row['amount']):"Pending";
						$status = array(0=>"<i style='color:#9932CC;'>$stz</i>",10=>"<span style='color:green;'>".date('d.m.Y,H:i',$st)."</span>");
					}
					
					$top = ($istpp) ? "<span style='padding:3px;font-size:13px;color:#fff;background:purple;font-family:arial;'>Topup</span>":"";
					$scd = ($st<9 && !$istop) ? "<a href='javascript:void(0)' onclick=\"popupload('loans.php?lnscd=$rid')\">Schedule</a>":"";
					foreach($fields as $col){
						if(!in_array($col,$exclude) && !in_array($col,$media)){
							$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst(str_replace("/","/ ",nl2br($row[$col]))));
							$val = ($col=="loan_officer") ? $staff[$row[$col]]:$val;
							$val = ($col=="creator") ? $staff[$row[$col]]:$val;
							$val = ($col=="loan_product") ? "$pname $top<br>$scd":$val;
							$val = (isset($docs[$col.$row['id']])) ? $docs[$col.$row['id']]:$val; 
							$val = (filter_var($val,FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
							$val = ($col=="amount") ? "- KES ".fnum($row[$col])."<br>- ".$row['duration']." $ndr":$val;
							if($col=="processing"){ 
								$chj = array_sum(json_decode($row['processing'],1)); $lis="";
								foreach(json_decode($row['processing'],1) as $pay=>$amnt){ $lis.= ucwords(str_replace("_"," ",$pay))."($amnt)\n"; }
								foreach($cuts as $pay=>$amnt){ $lis.= ucwords(str_replace("_"," ",$pay))."($amnt)\n"; $chj+=$amnt; }
								$pyc = ($row['payment']) ? $row['payment']."<br>":""; $col="Charges";
								$val = ($lis) ? "<span title='$lis'>$pyc KES ".fnum($chj)."</span>":"None"; 
							}
							$tds.="<td>$val</td>"; $ths.=($no==$start+1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					$tds.= (count($media)) ? "<td>".count($media)." Files<br><a href='javascript:void(0)' onclick=\"popupload('media.php?vmed=$lntb&src=$rid')\">
					<i class='bi-eye'></i> View</a></td>":"";
					
					$prepay=fnum($row['prepay']); $cko=fnum($row['checkoff']); 
					$tds.= ($deds) ? "<td>Checkoff($cko)<br>Prepayment($prepay)</td>":"";
					$autopost = ($sett['loanposting'] && $st>$sett['lastposted']) ? 1:0;
					
					$bt1 = ($st>10 && !$row['loan'] && in_array("post disbursed loan",$perms) && !$autopost) ? 
					"<br><a href='javascript:void(0)' onclick=\"popupload('loans.php?postloan=$rid')\">Post Loan</a>":"<br>";
					$bt1 = ($st<9 && in_array("create loan template",$perms) && $plev && !$istpp) ? "<br><a href='javascript:void(0)' 
					onclick=\"popupload('loans.php?template=$rid')\">Edit</a>":$bt1;
					
					if($st>200 && !$row['loan']){
						$bt1.= (!$autopost && in_array("delete loan template",$perms) && $plev && !$istop) ? " | <a href='javascript:void(0)' style='color:#ff4500' 
						onclick=\"delrecord('$rid','$ltbl','Delete Loan template?')\">Delete</a>":"";
					}
					else{
						$sgn = ($bt1!="<br>") ? " | ":"";
						$bt1.= ($row['loan']<10 && $st<9 && in_array("delete loan template",$perms) && $plev && !$istop) ? 
						"$sgn<a href='javascript:void(0)' style='color:#ff4500' onclick=\"delrecord('$rid','$ltbl','Delete Loan template?')\">Delete</a>":"";
					}
					
					$act = ($row["status"]==8 && $asprod && in_array("issue approved loan assets",$perms)) ? "<button class='btnn' onclick=\"popupload('loans.php?relprod=$rid')\"
					style='padding:4px;min-width:60px;background:#008fff'>Release</button>":$bt1;
					
					if(in_array("comments",$fields)){
						$arr = ($row['comments']==null) ? []:json_decode($row['comments'],1); 
						foreach($arr as $usa=>$com){
							$msg = ($com) ? prepare($com):"Ok";
							$aptd.="<li style='list-style:none'><b>".explode(" ",$staff[$usa])[0].":</b> <i>$msg</i>";
						}
						$tds.= ($aptd) ? "<td>$aptd</td>":"<td>None</td>"; $ctd+=1;
					}
					
					$onclick = ($view==3 or $view==4) ? "onclick=\"loadpage('clients.php?vintrcn=$idno&fdt')\"":"";
					$click = ($view!=3 && $view!=4 && $st<10) ? "onclick=\"loadpage('clients.php?showclient=$idno')\"":"";
					$name = prepare(ucwords($row['client'])); $fon=$row['phone']; $sta=($st>10) ? 10:$st; $css=($click) ? "cursor:pointer":""; $dy=date("d.m.Y, h:i a",$row['time']);
					$trs.= "<tr valign='top' id='rec$rid' $onclick><td>$no</td><td>$dy</td><td><span style='$css' $click>$name</span><br>0$fon</td>$cltd $tds<td>".$status[$sta]." 
					<span id='td$rid'>$act</span></td></tr>";
				}
			}
			else{ $ths = "<td>Loan product</td><td>Branch</td><td>Loan Officer</td><td>Amount</td><td>Loan Processing</td>"; }
		}
		else{ $ths = "<td>Loan product</td><td>Branch</td><td>Loan Officer</td><td>Amount</td><td>Loan Processing</td>"; }
		
		$ths.= (count($media)) ? "<td>Attached Media</td>":"";
		$ths.= ($deds) ? "<td>Deductions</td>":""; 
		$ths.= ($ctd) ? "<td>Approvals</td>":""; 
		
		$grups=$brans="";
		foreach($states as $key=>$des){
			$cnd=($view==$key) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?disbursements=$view&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:140px;font-size:15px;cursor:pointer' onchange=\"loadpage('loans.php?disbursements=$view&reg=$rgn&bran='+this.value.trim())\">$brns</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ltbl` WHERE $cond");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px;cursor:pointer;font-size:15px' onchange=\"loadpage('loans.php?disbursements=$view&reg=$rgn&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM(amount) AS tsum FROM `$ltbl` WHERE $cond");
		$totals = ($sql) ? intval($sql[0]['total']):0; $tsum=($sql) ? number_format(intval($sql[0]['tsum'])):0; 
		$pnd = ($view==3) ? 1:0; $nxp=0;
		
		$disb = ($dis && in_array("disburse loans",$perms) && $sett["multidisburse"]) ? "<button class='bts' style='padding:4px;float:right;font-size:13px;margin-left:5px' 
		onclick=\"popupload('loans.php?confdisb=$dcd')\">Confirm Disbursed</button>":"";
		$print = ($totals) ? genrepDiv("disbursement.php?src=".base64_encode($cond)."&v=$view&bran=$bran&mn=$mon&dy=$day",'right'):"";
		
		$cnd =(isset($appr[str_replace(" ","_",$me['position'])]) or (count($appr)>0 && in_array($me['position'],$supers))) ? 1:0;
		if($cnd){ $nxp = (isset($appr[str_replace(" ","_",$me['position'])])) ? $appr[str_replace(" ","_",$me['position'])]:min($appr); }
		$apprv = ($cnd && in_array("approve loan application",$perms)) ? "<button class='bts' style='padding:4px;float:right;font-size:13px;' 
		onclick=\"popupload('loans.php?approve=$nxp&pnd=$pnd')\">Approve Loans</button>":"";
		
		$adcl = ($view==3 or $view==4) ? "stbl":""; $cltd=($view==1) ? "<td>Cycles</td>":"";
		$title = (isset($_GET['nlt'])) ? "Unposted Disbursed Loans":$states[$view];
		
		echo "<div class='container cardv' style='max-width:1400px;min-height:400px;overflow:auto;'>
			<div style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title ($tsum)</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered $adcl' style='width:100%;font-size:14px;min-width:700px' cellpadding='5'>
						<caption style='caption-side:top'><p> $disb $apprv
							<select style='width:150px;font-size:15px;cursor:pointer' onchange=\"loadpage('loans.php?disbursements='+this.value.trim().split(' ').join('+'))\">
							$grups</select> $brans $offs</p>
							<p style='margin:0px'> $print <select style='width:110px;cursor:pointer' onchange=\"loadpage('loans.php?disbursements=$view&bran=$bran&staff=$stid&mon='+this.value.trim())\">$mns</select>
							<select style='width:110px;cursor:pointer' onchange=\"loadpage('loans.php?disbursements=$view&bran=$bran&staff=$stid&mon=$mon&day='+this.value.trim())\">$dys</select> $appftr
							<input type='search' style='width:160px;padding:4px 5px;font-size:15px' placeholder='Search in ".date('M Y',$mon)."' value=\"".prepare($str)."\"
							onkeyup=\"fsearch(event,'loans.php?disbursements=0&str='+cleanstr(this.value))\" onsearch=\"loadpage('loans.php?disbursements=0&str='+cleanstr(this.value))\"></p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px' valign='top'>
						<td colspan='2'>Application</td><td>Client</td>$cltd $ths<td>Disbursement</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"loans.php?disbursements=$view&reg=$rgn&bran=$bran&staff=$stid&mon=$mon&day=$day&fap=$fap&str=".urlencode(prepare($str)))."
		</div>";
		savelog($sid,"Viewed $title"); 
	}
	
	# template loan schedule
	if(isset($_GET["lnscd"])){
		$tid = trim($_GET["lnscd"]); 
		$scd = loanschedule($tid); $idno=$scd["idno"]; $dep=fnum($scd["deposit"]); 
		$prod = $scd["product"]; $trs=$ths=$trl=""; $tot=0; $cols=$all=[];
		
		foreach($scd["installment"] as $one){
			if(isset($one["ipays"])){ $one["pays"]+=$one["ipays"]; }
			foreach($one["pays"] as $py=>$sum){ $cols[$py]=ucwords(str_replace("_"," ",$py)); }
		}
		
		foreach($scd["installment"] as $one){
			if(isset($one["ipays"])){ $one["pays"]+=$one["ipays"]; }
			$day=date("d-m-Y",$one["day"]); $pays=$one["pays"]; $inst=array_sum($pays); $tds=""; $tot+=$inst;
			foreach($cols as $col=>$ctx){
				$amnt=(isset($pays[$col])) ? $pays[$col]:0; $tds.="<td style='text-align:center'>".fnum($amnt)."</td>";
				if(isset($all[$col])){ $all[$col]+=$amnt; }else{ $all[$col]=$amnt; }
			}
			$trs.= "<tr valign='top'><td>$day</td>$tds<td style='text-align:center'>".fnum($inst)."</td></tr>";
		}
		
		$sql = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		foreach($cols as $col=>$ctx){
			$ths.="<td style='text-align:center'>$ctx</td>"; $trl.="<td style='text-align:center'>".fnum($all[$col])."</td>"; 
		}
		
		$trs.= "<tr style='font-weight:bold;background:#f0f0f0'><td></td>$trl<td style='text-align:center'>".fnum($tot)."</td></tr>";
		$colsp = ($dep) ? 3:2; $deptd=($dep) ? "<td>Deposit<br><b>Ksh $dep</b></td>":"";
		
		echo "<div style='padding:10px;min-width:430px'>
			<h3 style='text-align:center;font-size:23px;text-align:center'>".ucwords(prepare($sql[0]['name']))." Loan Schedule</h3>
			<table cellpadding='3' style='width:100%;margin-top:15px;margin-bottom:10px'>
				<tr><td colspan='$colsp' style='font-weight:bold;color:#191970'>$prod</td></tr>
				<tr><td>Principal<br><b>Ksh ".fnum($scd["loan"])."</b></td>$deptd<td style='text-align:right'><button class='bts' style='padding:2px 6px;' 
				onclick=\"printdoc('reports.php?vtp=loanscd&mn=$tid&yr=1','schedule')\"><i class='bi-printer'></i> Print</button></td></tr>
			</table>
			<table cellpadding='5' style='width:100%;font-size:15px;'>
				<tr valign='top' style='font-weight:bold;background:#e6e6fa;color:#191970'><td>Date</td>$ths<td style='text-align:center'>Installment</td></tr> $trs
			</table><br>
		</div>";
	}
	
	# release asset Loan
	if(isset($_GET["relprod"])){
		$rid = trim($_GET["relprod"]);
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$rid'");
		$row = $sql[0]; $cname=prepare(ucwords($row["client"])); $lntp=explode(":",$row["loantype"]); $aid=$lntp[2];
		$qri = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$aid'"); 
		$item = prepare(ucfirst($qri[0]['asset_name'])); $cost=$qri[0]['asset_cost']; 
		$qty = ceil($row["amount"]/$cost); $now=date("Y-m-d")."T".date("H:i");
		
		echo "<div style='padding:10px;max-width:350px;margin:0 auto'>
			<h3 style='text-align:center;font-size:23px;text-align:center'>Release Asset Loan</h3><br>
			<form method='post' id='afom' onsubmit=\"giveasset(event)\">
				<input type='hidden' name='assetdisb' value='$rid'> <input type='hidden' id='hasdocs' name='hasdocs' value='dnote'>
				<p>Confirm Realease of <b>$qty $item</b> for Ksh <b>".fnum($row["amount"])."</b> to $cname</p>
				<p>Acknowledgement or Delivery Note Photo<br><input type='file' style='width:100%' id='dnote' accept='image/*' required></p>
				<p>Release Date & Time<br><input type='datetime-local' style='width:100%' name='disbdy' max='$now' required></p>
				<table cellpadding='5' style='width:100%'><tr>
					<td>Authenticate<br><div style='width:170px;border:1px solid #dcdcdc;height:35px'>
					<input type='text' name='otpv' style='border:0px;padding:4px;width:90px' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' 
					autocomplete='off' required><a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
					<td style='text-align:right'><br><button class='btnn' style='padding:6px'>Release</button></td>
				</tr></table><br>
			</form><br>
		</div>";
	}
	
	# approve loans
	if(isset($_GET['approve'])){
		$state = trim($_GET['approve']); 
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$pnd = (isset($_GET['pnd'])) ? trim($_GET["pnd"]):0;
		
		$me = staffInfo($sid); $access=$me['access_level']; $pos=$me['position'];
		$lvs = ($pos=="assistant") ? json_decode($me['config'],1)['position']:$pos;
		$cnf = json_decode($me["config"],1); $sets=[]; $tcols=3;
		$gotp=$grcol=$amr=$lap=$brans=$tps=$opts=$lis=""; 
		
		$poz = (isset($cnf["mypost"])) ? $cnf["mypost"]:[$me["position"]=>$me["access_level"]];
		if(isset($_GET["amr"])){
		    $dnf=explode("-",$state); $lap=$state; $state=$dnf[0]-1; $amr="BETWEEN ".$dnf[1]." AND ".$dnf[2];
		}
		
		$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
		$levels = ($res) ? json_decode($res[0]['levels'],1):[]; 
		if(defined("LOAN_APPROVALS")){
			if(LOAN_APPROVALS){ $levels[$state+1]=$pos; }
		}
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='guarantor_consent_phone' OR `setting`='guarantor_consent_by' 
		OR `setting`='loan_approval_otp' OR `setting`='threshold_approver')");
		if($sql){
			foreach($sql as $row){ $sets[$row['setting']]=prepare($row['value']); }
		}
		
		$level = (in_array($lvs,$levels)) ? array_search($lvs,$levels):0; 
		if(in_array(strtolower($pos),["ceo","c.e.o","director","super user"])){
			foreach($levels as $key=>$lv){
				$val=$key-1; $cnd=($state==$val) ? "selected":"";
				$opts.="<option value='$val' $cnd>".ucfirst(prepare($lv))."</option>";
			}
			$tps = "Approve as: <select style='padding:4px;width:120px;font-size:15px;margin-top:7px' onchange=\"popupload('loans.php?pnd=$pnd&approve='+this.value)\">$opts</select>";
		}
		else{ $state=$level-1; }
	
		$thapp = (isset($sets["threshold_approver"])) ? $sets["threshold_approver"]:"";
		$tmpby = (isset($sets["guarantor_consent_by"])) ? $sets["guarantor_consent_by"]:"none";
		$tgfon = (isset($sets["guarantor_consent_phone"])) ? $sets["guarantor_consent_phone"]:0;
		if($tmpby!="none" && $tgfon && in_array($tmpby,$levels)){
			$spos = array_search($tmpby,$levels); $spos-=1;
			if($state==$spos){ $tcols=4; $grcol=$tgfon; }
		}
		
		if(in_array($me["access_level"],["hq","region"])){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $key=>$row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.= "<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='padding:4px;width:140px;font-size:15px;margin-top:7px' onchange=\"popupload('loans.php?approve=$state&pnd=$pnd&bran='+this.value)\">$brns</select>";
		}
		else{ $bran = $me['branch']; }
		
		$ltbl = "org".$cid."_loantemplates"; $trs=""; $next=$state+1;
		$cond = ($bran) ? "AND `branch`='$bran'":""; $posts=[]; $mxran=0;
		$cond = (!$bran && $me["access_level"]=="region") ? "AND ".setRegion($cnf["region"],"branch"):$cond;
		$cond.= ($state==0 && count($poz)==1) ? " AND NOT `creator`='$sid'":"";
		
		if(defined("LOAN_APPROVALS")){
			if(LOAN_APPROVALS){
			    foreach(LOAN_APPROVALS as $key=>$one){
			        foreach($one as $p=>$d){
				        if(in_array($levels[$level],$d)){ $posts[]="$p-$key"; }
				    }
			    }
			    
			    if($amr){ $cond.=" AND `amount` $amr"; }
			    else{
    				foreach(LOAN_APPROVALS as $key=>$one){
    					if(isset($one[$state+1])){
    						if(in_array($pos,$one[$state+1])){
    							$dx = explode("-",$key); $fro=$dx[0]; $mto=$dx[1]; $mxran=$mto;
    							$cond.= " AND `amount` BETWEEN $fro AND $mto"; break; 
    						}
    					}
    				}
			    }
			}
		}
		
		if($thapp && !$mxran){
			$ldf=explode("%",$thapp); $appr=$ldf[0]; $sam=$ldf[1];
			if(in_array($appr,$levels)){
				$spos = array_search($appr,$levels); $spos-=1;
				if($state==$spos){ $cond.=" AND `amount`>=$sam"; }
			}
		}
		
		if(!$tps && $posts){
		    foreach($posts as $one){
		        $cnd = ($one==$lap) ? "selected":""; 
		        $opts.= "<option value='$one' $cnd>Approver ".$one[0]." (".explode("-",$one)[1]."-".explode("-",$one)[2].")</option>";
		    }
		    $tps = "Approval: <select style='padding:4px;width:150px;font-size:15px;margin-top:7px' onchange=\"popupload('loans.php?amr=1&pnd=$pnd&approve='+this.value)\">$opts</select>";
		}
		
		$cond.= ($pnd) ? " AND `pref`='8'":" AND NOT `pref`='8'";
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `status`='$state' $cond ORDER BY `pref` ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $id=$row['id']; $amnt=fnum($row['amount']); $idno=$row["client_idno"]; $gotp=$dct="";
				if($tcols==4){
					$nme = str_replace("'","",$name); $cont=intval(ltrim($row[$tgfon],"254"));
					$gotp = "<td><div style='width:150px;border:1px solid #dcdcdc;height:33px'>
					<input type='text' name='gotpv[$id]' style='border:0px;padding:4px;width:70px;font-size:15px' placeholder='OTP' maxlength='6' onkeyup=\"valid('otp$id',this.value)\" 
					id='otp$id' autocomplete='off'><a href='javascript:void(0)' onclick=\"requestgotp('$cont:$nme')\"><i class='fa fa-refresh'></i> Request</a></div></td>";
				}
				
				$chk = $db->query(2,"SELECT `comments`,`time`,`status` FROM `$ltbl` WHERE `client_idno`='$idno' AND `status`>8 AND NOT `id`='$id' ORDER BY `time` DESC LIMIT 1");
				if($chk){
					if($chk[0]["status"]==9){
						$ctm = $chk[0]["time"]; $com=@prepare(array_pop(json_decode($chk[0]["comments"],1)));
						$dct = "<br><i style='color:#ff4500;font-size:15px'>Had a declined Application on ".date("M d,Y",$ctm)." : $com</i>";
					}
				}
				
				$trs.= "<tr valign='top'><td><input type='checkbox' name='ploans[]' value='$id'></checkbox></td><td>$name $dct</td><td style='text-align:right'>$amnt</td>$gotp</tr>";
			}
		}
		
		$arr = ($state) ? array("Approve","Decline","Pend"):array("Approve","Pend");
		foreach($arr as $li){ $lis.="<option value='$li'>$li</option>"; }
		$set = (isset($sets["loan_approval_otp"])) ? $sets["loan_approval_otp"]:1; 
		$adcl = ($tcols==4) ? "<td style='text-align:right'>Guarantor OTP</td>":""; $tcl=$tcols-1;
		
		if($set){
			$btx = ($trs) ? "<tr style='background:#fff;' valign='bottom'><td colspan='$tcl'><div style='width:170px;border:1px solid #dcdcdc;height:35px'>
			<input type='text' name='otpv' style='border:0px;padding:4px;width:90px' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' 
			autocomplete='off' required><a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
			<td style='width:120px'><select style='width:100%;padding:7px;cursor:pointer;font-size:15px' id='aop'>$lis</select></td></tr>
			<tr style='background:#fff'><td colspan='$tcols' style='text-align:right'><hr><button class='btnn'>Proceed</button></td></tr>":"";
		}
		else{
			$otp=rand(100000,999999); $exp=time()+3600; $fon=$me['contact'];
			$db->execute(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')");
			$btx = ($trs) ? "<tr style='background:#fff;' valign='bottom'><td colspan='$tcl'><input type='hidden' name='otpv' value='$otp'>
			<select style='width:140px;padding:6px;cursor:pointer;font-size:15px' id='aop'>$lis</select></td>
			<td><button class='btnn' style='float:right;padding:6px'>Proceed</button></td></tr>":"";
		}
		
		$trs.= ($trs) ? "<tr><td colspan='$tcols'>Your Comment (Max 100 Chars)<br><input type='text' style='width:100%' value='Ok' maxlength='100' name='appcom'></td></tr>":"";
		echo "<div style='padding:5px;margin:0 auto;max-width:520px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Approve Loans</h3>
			<form method='post' id='aform' onsubmit='approveloans(event)'>
				<input type='hidden' name='nval' value='$next'> <input type='hidden' name='tgfon' value='$grcol'>
				<table style='width:100%;' class='table-striped' cellpadding='7'>
					<caption style='caption-side:top'> $tps $brans</caption>
					<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td colspan='2'>Client</td><td style='text-align:right'>Loan</td> $adcl</tr> $trs $btx
				</table><br><input type='hidden' name='pndst' value='$pnd'>
			</form><br>
		</div> <script> clearInterval(tmt); var tmt=setTimeout(function(){closepop();},300000); </script>";
	}
	
	# confirm disbursed loans
	if(isset($_GET['confdisb'])){
		$state = trim($_GET['confdisb']);
		$ltbl = "org".$cid."_loantemplates"; $trs=$lis="";
		$part = sys_constants("partial_disbursement");
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `status`='8' AND NOT `loantype` LIKE '%asset%' ORDER BY pref ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $id=$row['id']; $cut=$row['checkoff']+$row['prepay']; $mx=date("Y-m-d")."T".date("H:i");
				$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $cut+=(isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; 
				$dn = ($dsb) ? array_sum($dsb):$cut; $amnt=$row['amount']-$dn; $min=($dsb) ? 0:$cut+50;
				$minp = ($part) ? "<input type='number' style='width:100px;padding:5px' value='$amnt' min='$min' max='$amnt' name='damnt[$id]' required>":
				"Ksh ".fnum($amnt)."<input type='hidden' value='$amnt' name='damnt[$id]'>";
				$trs.= "<tr valign='top'><td><input type='checkbox' name='dids[]' value='$id'></checkbox></td><td style='width:30%'>$name<br><span id='tdf$id'></span></td>
				<td>$minp<br><span id='tdl$id'></span></td><td style='width:33%'><input type='datetime-local' style='width:200px;padding:4px;background:#fff' name='days[$id]' max='$mx'><br>
				<span id='tdr$id'><a href='javascript:void(0)' onclick=\"addrefs('$id','$part')\" style='float:right'><i class='fa fa-plus'></i> Add Payment Ref</a></span></td></tr>";
			}
		}
		
		$lis = "<option value='0'>-- Select Account --</option>";
		$btx = ($trs) ? "<p style='text-align:right;'><button class='btnn'>Confirm</button></p>":"";
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`wing` LIKE '2,6%' OR `wing` LIKE '2,7%') ORDER BY `account` ASC");
		if($sql){
			foreach($sql as $row){
				$rid = $row['id']; $acc=prepare(ucfirst($row['account']));
				$lis.= (!in_array($rid,[14,15,22,23])) ? "<option value='$rid'>$acc</option>":"";
			}
		}
		
		echo "<div style='padding:10px'> 
			<h3 style='color:#191970;text-align:center;font-size:22px'>Confirm Disbursed Loans</h3>
			<form method='post' id='dform' onsubmit='confirmdisb(event)'>
				<table style='width:100%;min-width:430px' class='table-striped' cellpadding='7'>
					<caption style='caption-side:top'>
						<p style='margin:0px'>Disbursed From<br><select style='width:220px;font-size:15px' name='disbfrom' required>$lis</select></p>
					</caption>
					<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td colspan='2'>Client</td><td>Amount</td><td>Disbursed Date</td></tr> $trs
				</table><br> $btx
			</form><br>
		</div>
		<script>
			function addrefs(id,pt){
				$('#tdf'+id).html(\"<input type='text' name='trans[\"+id+\"]' placeholder='Payment Ref' style='width:100%;font-size:15px;padding:4px;min-width:120px;margin-top:12px' required>\");
				$('#tdl'+id).html(\"<input type='text' id='ty\"+id+\"' name='fees[\"+id+\"]' placeholder='Charges Incurred' style='width:100%;font-size:15px;padding:4px;'>\");
				$('#tdr'+id).html(\"<input type='text' name='desc[\"+id+\"]' placeholder='Payment Description' style='width:100%;padding:4px;font-size:15px' required>\");
				if(pt<1){ $('#ty'+id).css({'margin-top':'12px'}); }
			}
		</script>";
	}
	
	# loan template
	if(isset($_GET['template'])){
		$tid = intval($_GET['template']);
		$prod = (isset($_GET['prod'])) ? trim($_GET['prod']):0;
		$idno = (isset($_GET['cid'])) ? trim($_GET['cid']):0;
		$asid = (isset($_GET['asid'])) ? trim($_GET['asid']):0;
		$pcost = (isset($_GET['qty'])) ? intval($_GET['qty'])*intval($_GET['cost']):0;
		
		$ltbl = "org$cid"."_loans"; $stbl="org$cid"."_staff";
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1); $clid=$bid=0;
		$perms = getroles(explode(",",$me['roles'])); $lrow=[]; $lsrc="client";
		
		$exclude = array("id","status","time","loan","client","balance","expiry","penalty","disbursement","paid","tid","approvals","clientdes","branch","creator","pref","loantype","disbursed");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = array("id"=>"number","client"=>"text","loan_product"=>"text","client_idno"=>"number","phone"=>"number","branch"=>"number","loan_officer"=>"number",
		"amount"=>"number","tid"=>"number","duration"=>"number","disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number",
		"loan"=>"text","status"=>"number","approvals"=>"textarea","disbursed"=>"textarea","clientdes"=>"textarea","creator"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$reuse = ($res) ? json_decode($res[0]['reuse'],1):[];
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[]; 
		$skipcols = (defined("SKIP_ADDED_LOAN_COLS")) ? SKIP_ADDED_LOAN_COLS:[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		$dels = array("balance","expiry","penalty","disbursement","paid","clientdes");
		foreach($dels as $del){ unset($fields[$del]); }
		
		if(!in_array("cuts",$db->tableFields(2,"org$cid"."_loantemplates"))){
			$db->execute(2,"ALTER TABLE `org$cid"."_loantemplates` ADD `cuts` LONGTEXT NOT NULL AFTER `prepay`");
			$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `cuts`='[]'");
		}
		
		$defv = array("status"=>0,"time"=>time(),"loan"=>0,"penalty"=>0,"tid"=>0,"creator"=>$sid,"disbursed"=>"[]"); 
		foreach(array_keys($fields) as $fld){
			$dvals[$fld] = (isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		$setts = ["loansegment"=>1]; $setts['template_phone_include']=0; $pcode=$fon="";
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$dedicated = (isset($setts['officers'])) ? $setts['officers']:"all";
		$lis=$min=$max=$infs=$ckoamnt=$ckoto=$bypass_lim=$ofid=""; $isnew=1; $done=$crow=[];
		
		if($tid){
			$row = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$tid'"); 
			$dvals = array_map("prepare",$row[0]); $dvals['tid']=$tid; $idno=$dvals['client_idno'];  
			$prod = ($prod) ? $prod:$dvals['loan_product']; $pcode=$dvals['payment']; $pcost=$row[0]['amount'];
			$lsrc = $db->query(1,"SELECT `category` FROM `loan_products` WHERE `id`='$prod'")[0]['category'];
			if($lsrc=="asset"){ $asid = explode(":",$row[0]["loantype"])[2]; }
			
			if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
				foreach($fields as $col=>$dtp){ 
					if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($fields[$col]); }
				}
			}
		}
		
		if($idno){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			if($sql){
				$crow=$sql[0]; $fon=$crow['contact']; $ofid=$crow['loan_officer']; $clid=$crow['id']; 
				$cdm = (isset($setts['clientdormancy'])) ? $setts['clientdormancy']:0; $bid=$crow["branch"];
				$qri = $db->query(2,"SELECT *FROM `$ltbl` WHERE `client_idno`='$idno' ORDER BY `disbursement` DESC LIMIT 1"); $lrow=($qri) ? $qri[0]:[];
				$isnew = ($qri) ? 0:1; $lastloan=($qri) ? $qri[0]["status"]:0; $lastloan=($qri && !$lastloan) ? time():$lastloan;
				if($cdm){
					$last = strtotime(date("Y-M-d",strtotime("-$cdm month")));
					$isnew = ($lastloan<=$last && $lastloan>0) ? 1:$isnew;
				}
				
				if(!$tid && count($reuse) && $qri){
					foreach($reuse as $col){
						if(isset($qri[0][$col])){
							if($qri[0][$col] or in_array($col,$skipcols)){ $done[$col]=$qri[0][$col]; }
						}
					}
				}
			}
			else{ echo ($asid) ? "<script> alert('Unknow client Idno $idno'); loadpage('finassets.php?issue=$asid'); </script>":""; $idno=0; }
		}
		
		$durs=$payterms=$ftps=$pays=[];
		if($prod){
			$dvals['loan_product']=$prod; $lsrc=($asid) ? "asset":$lsrc;
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod' AND `category`='$lsrc'");
			$payterms = json_decode($res[0]['payterms'],1); $min=$res[0]['minamount']; $max=$res[0]['maxamount'];
			if($setts['loansegment']){
				$arr = $res[0]['duration']/$res[0]['interest_duration']; $intv=$res[0]['intervals'];
				for($i=1; $i<=$arr; $i++){
					$durs[]=$i*$res[0]['interest_duration'];
				}
			}
			else{ 
				$durs[]=$res[0]['duration'];
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_limits` WHERE `client`='$cid'"); 
		if($res){
			if($res[0]['status']){
				$bypass_lim = (in_array($sid,json_decode($res[0]['bypass'],1))) ? "<span style='float:left'>
				<input type='checkbox' name='bypasslim' value='1'> &nbsp; Bypass Loan Limit</checkbox></span>":"";
			}
		}
		
		unset($fields["client_idno"]); $gtx="";
		$cols = array_merge(["client_idno"=>"number"],$fields);
		$assignpays = (defined("TEMPLATE_ASSIGN_PAYS")) ? TEMPLATE_ASSIGN_PAYS:[1];
		$defcols = (defined("TEMPLATE_DEF_COLS")) ? TEMPLATE_DEF_COLS:[];
		$specprods = (defined("APP_SPECIAL_PRODUCTS")) ? APP_SPECIAL_PRODUCTS:[];
		$exemptbrn = (defined("EXCEMPT_COGUARANTEE")) ? EXCEMPT_COGUARANTEE:[];
		if($asid){
			$skip = array("loan_product"=>$prod,"client_idno"=>$idno,"phone"=>$fon,"amount"=>$pcost);
			foreach($skip as $col=>$val){ $exclude[]=$col; $dvals[$col]=$val; }
		}
		
		foreach($cols as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="loan_officer"){
					$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":"";
					$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
					$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond; $opts=""; $avs=[];
					$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `status`='0' AND NOT `position`='USSD' $cond ORDER BY `name` ASC"); 
					if($res){
						foreach($res as $row){
							if($dedicated=="all"){
								$uid=$row['id']; $avs[]=$uid; $cnd = ($uid==$dvals[$field] or $uid==$ofid) ? "selected":"";
								$opts.= "<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
							}
							else{
								if(isset(staffPost($row['id'])[$dedicated])){
									$uid=$row['id']; $avs[]=$uid; $cnd = ($uid==$dvals[$field] or $uid==$ofid) ? "selected":"";
									$opts.= "<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
								}
							}
						}
					}
					
					$opts = ($opts=="") ? "<option value='0'>No Staff found</option>":$opts;
					$lis.= (count($avs)>1) ? "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>":"<input type='hidden' name='$field' value='".$avs[0]."'>";
				}
				elseif($field=="client_idno"){
					$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":""; $opts=$gtx=$tcss="";
					$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
					$res = $db->query(2,"SELECT *FROM `org".CLIENT_ID."_clients` WHERE `status`='0' $cond"); 
					if($res){
						foreach($res as $row){
							$opts.= "<option value='".$row['idno']."'>".prepare(ucfirst($row['name']))."</option>";
						}
					}
					
					$ran = rand(1234567,7654321); $val=($idno) ? $idno:$dvals[$field]; $lock=($dvals[$field]) ? "readonly":""; 
					$css = ($lock) ? "cursor:not-allowed":""; $chk_guarantor=sys_constants("check_guarantors"); $dct="";
					if($val){
						$chk = $db->query(2,"SELECT `comments`,`time`,`status` FROM `org{$cid}_loantemplates` WHERE `client_idno`='$val' AND `status`>8 AND NOT `id`='$tid' ORDER BY `time` DESC LIMIT 1");
						if($chk){
							if($chk[0]["status"]==9){
								$ctm = $chk[0]["time"]; $com=@prepare(array_pop(json_decode($chk[0]["comments"],1)));
								$dct = "The client had a declined Application on ".date("M d,Y",$ctm)." : $com<br>";
							}
						}
						
						if($chk_guarantor){
							$chk = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `$chk_guarantor`='$fon' OR `$chk_guarantor`='$val' ORDER BY `time` DESC");
							if($chk){
								$lch = $db->query(2,"SELECT SUM(balance+penalty) AS tbal FROM `org$cid"."_loans` WHERE `tid`='".$chk[0]['id']."'");
								if($lch[0]["tbal"]>0){
									$gtx = "Guaranteed Loan of Ksh ".fnum($chk[0]['amount'])." for ".prepare(ucwords($chk[0]['client'])); $tcss="margin:0px";
								}
							}
						}
					}
					
					$notice = ($gtx or $dct) ? "<div style='color:#ff4500;border:1px solid pink;background:#FFEBCD;padding:7px;font-family:signika negative;margin-bottom:10px'>$dct $gtx</div>":"";
					$lis.="<p style='$tcss'>Client Id Number<br> <datalist id='$ran'>$opts</datalist><input type='text' name='$field' value='$val' style='width:100%;$css' 
					list='$ran' $lock onchange=\"popupload('loans.php?template=$tid&cid='+this.value)\"></p> $notice";
				}
				elseif($field=="phone"){
					$fon = ($fon) ? $fon:$dvals[$field];
					$lis.= ($setts['template_phone_include']) ? "<p>Phone Number<br><input type='number' id='cphone' style='width:100%' value=\"$fon\" name='$field' required></p>":
					"<input type='hidden' name='phone' id='cphone' value='$fon'>";
				}
				elseif($field=="loan_product"){
					$limit = ($idno) ? loanlimit($idno):[];
					$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='$lsrc'");
					$opts = "<option value='0'>-- Loan Product --</option>";
					foreach($res as $row){
						$id=$row['id']; $cnd=($id==$dvals[$field]) ? "selected":"";
						if(isset($specprods[$id])){
							$val = (isset($crow[$specprods[$id]])) ? $crow[$specprods[$id]]:"";
							if(strtolower($val)=="yes" && $isnew){
								$opts.= (isset($limit[$id])) ? "<option value='$id' $cnd>".prepare(ucfirst($row['product']))."</option>":"";
							}
						}
						else{
							if(strlen($bypass_lim)){ $opts.= "<option value='$id' $cnd>".prepare(ucfirst($row['product']))."</option>"; }
							else{ $opts.= (isset($limit[$id])) ? "<option value='$id' $cnd>".prepare(ucfirst($row['product']))."</option>":""; }
						}
					}
					
					$onch = ($idno) ? "popupload('loans.php?template=$tid&cid=$idno&prod='+this.value)":"toast('Enter Client Idno first!');$('#prd').val('0')";
					$lis.= "<p>Loan Product<br><select name='$field' id='prd' style='width:100%' onchange=\"$onch\">$opts</select></p>";
				}
				elseif($field=="amount"){
					$val = $dvals[$field];
					$lis.= "<p>Loan Amount<br><input type='number' style='width:100%' value=\"$val\" min='$min' max='$max' name='$field' placeholder='$min - $max' required></p>";
				}
				elseif($field=="duration"){
					$skipdys = (defined("SKIP_LOAN_DURS")) ? SKIP_LOAN_DURS:[]; $opts="";
					foreach($durs as $dur){
						$cnd = ($dur==$dvals[$field]) ? "selected":"";
						$opts.= (!in_array($dur,$skipdys)) ? "<option value='$dur' $cnd>$dur days</option>":"";
					}
					
					$opts = ($opts) ? $opts:"<option value='0'> -- Select --</option>";
					$lis.="<p>Loan Duration<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); asort($drops); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
					}
					
					if(isset($defcols[$field])){
						$lis.= (in_array($prod,$defcols[$field])) ? "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>":
						"<input type='hidden' name='$field' value='N/A'>";
					}
					else{
						$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
						"<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
					}
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname=(substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $ran=rand(12345678,87654321); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$val' $cnd></option>";
					}
					
					if(isset($defcols[$field])){
						$lis.= (in_array($prod,$defcols[$field])) ? "<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
						<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>":"<input type='hidden' name='$field' value='N/A'>";
					}
					else{
						$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
						"<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
						<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
					}
				}
				elseif($dtype=="textarea"){
					if(isset($defcols[$field])){
						$lis.= (in_array($prod,$defcols[$field])) ? "<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>":
						"<input type='hidden' name='$field' value='N/A'>";
					}
					else{
						$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
						"<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
					}
				}
				else{
					$inp = (isset($accept[$dtype])) ? "file":$dtype; 
					$val = prepare(ucfirst($dvals[$field])); $add=($inp=="file") ? $accept[$dtype]:""; 
					$fid = ($field=="phone") ? "id='cphone'":"id='$field'";
					if($inp=="file" && !isset($done[$field])){ $infs.="$field:"; $ftps[$field]=$dtype; }
					if(isset($defcols[$field])){
						$lis.= (in_array($prod,$defcols[$field])) ? "<p>$fname<br><input type='$inp' $fid style='width:100%' value=\"$val\" accept='$add' name='$field' required></p>":
						"<input type='hidden' name='$field' value='N/A'>";
					}
					else{
						if(!$val && !in_array($field,["amount"])){ $val=(isset($lrow[$field])) ? $lrow[$field]:""; }
						$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>": 
						"<p>$fname<br><input type='$inp' $fid style='width:100%' value=\"$val\" accept='$add' name='$field' required></p>";
					}
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		if($prod){
			foreach($payterms as $key=>$val){
				if(explode(":",$val)[0]==0 && $isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==1){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==6 && !$isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==7 && !$isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
			}
		}
		
		$stpl = (isset($setts['loantype_option'])) ? $setts['loantype_option']:1;
		$title = ($tid) ? "Edit Loan Application":"Create Loan Application";
		
		if(count($pays)){
			$ops=""; $pst=0;
			if($db->istable(3,"wallets$cid") && !$tid){
				$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$clid' AND `type`='client' AND `balance`>0");
				if($chk){
					$wid = $chk[0]['id']; $bal=walletBal($wid); 
					if($bal>0){ $ops = "<option value='WALLET:$wid' selected>Transaction A/C (Ksh $bal)</option>"; $pst=1; }
				}
			}
			
			$vwc = (defined("USERS_VIEW_ALLPAYS")) ? USERS_VIEW_ALLPAYS:0; $req=($tid or !$stpl) ? "required":"";
			$cond = ($me['access_level']=="hq" or $vwc) ? "":"AND `paybill`='".getpaybill($me['branch'])."'";
			$lis.= "<p>Payment for ".implode("+",$pays)."<br><select style='width:100%' name='payments' $req>";
			$res = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `status`='0' $cond");
			if($res){
				foreach($res as $row){
					$name = prepare(ucwords(strtolower($row['client'])));
					$code = $row['code']; $acc=$row['account']; $phon=ltrim(ltrim($row['phone'],"0"),"254"); 
					$cnd = ($code==$pcode or $idno==$acc or $phon==$fon or $acc==$fon) ? "selected":""; $amnt=fnum($row['amount']);
					$ops.= ($cnd or in_array($sid,$assignpays) or in_array("all",$assignpays)) ? "<option value='$code' $cnd>$name - $amnt</option>":"";
				}
			}
			$lis.= "$ops</select></p>";
		}
		 
		$tpl = ($tid or !$stpl) ? "":"<p id='ltp'>Type of Loan (Optional)<br><select name='ltp' style='width:100%'><option value='0'>-- Select --</option>
		<option value='1'>New Loan</option><option value='0'>Repeat Loan</option></select></p>";
		
		if($tid){
			$qri = $db->query(2,"SELECT *FROM `checkoff_others$cid` WHERE `template`='$tid'");
			$ckoamnt = ($qri) ? $qri[0]['amount']:""; $ckoto = ($qri) ? $qri[0]['receiver']:"";
		}
		
		$alowed = (isset($setts['checkoff'])) ? $setts['checkoff']:0; 
		$gto = (defined("TRANSFER_CHECKOFF")) ? TRANSFER_CHECKOFF:0;
		$cut = (in_array("assign client checkoff",$perms) && $alowed && $gto) ? "<p>Deduct Check Off to another Client (Optional)<br>
		<input type='number' style='width:60%' name='ckoto' placeholder='Beneficiary Idno' value='$ckoto'> 
		<input type='number' style='width:38%;float:right' placeholder='Amount' name='ckoamnt' value='$ckoamnt'>":"";
		
		$noapp = sys_constants("no_guaranteeing_eachother");
		$btx = ($gtx && $noapp && !in_array($bid,$exemptbrn)) ? "<span style='color:#ff4500;background:#FFEBCD;padding:10px'>Application Declined!</span>":
		"$bypass_lim <button class='btnn'>Save</button>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:350px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='tform' onsubmit=\"savetemplate(event)\">
				<input type='hidden' name='formkeys' value='".json_encode($cols,1)."'> <input type='hidden' name='asid' value='$asid'>
				<input type='hidden' name='id' value='$tid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'>
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis $cut $tpl<br>	
				<p style='text-align:right'>$btx</p>
			</form>
		</div>";
	}
	
	# view loans
	if(isset($_GET['manage'])){
		$view = trim($_GET['manage']);
		$vtp = ($view) ? $view:0;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean($_GET['str']),"0"):null;
		$fro = (isset($_GET['fro'])) ? strtotime($_GET['fro']):0;
		$dtu = (isset($_GET['dto'])) ? strtotime($_GET['dto']):0;
		$stid = (isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$prod = (isset($_GET['prd'])) ? trim($_GET['prd']):0;
		$vcol = (isset($_GET['vcol'])) ? trim($_GET['vcol']):0;
		$lcyc = (isset($_GET['cyc'])) ? trim($_GET['cyc']):0;
		$rate = (isset($_GET['rtg'])) ? trim($_GET['rtg']):"";
		$odb = (isset($_GET['odb'])) ? trim($_GET['odb']):"";
		$tdy = strtotime('Today'); 
		
		$ltbl = "org$cid"."_loans"; $cols=$db->tableFields(2,$ltbl);
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 30; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1); 
		$npd = sys_constants("non_performing_loan_days");
		$tdo = ($npd) ? $npd*86400:180*86400; $npl=time()-$tdo;
		
		if(!in_array("clientdes",$cols)){
			$cds = json_encode(["rating"=>"Unrated"],1);
			$db->execute(2,"ALTER TABLE `$ltbl` ADD `clientdes` LONGTEXT NOT NULL AFTER `approvals`");
			$db->execute(2,"UPDATE `$ltbl` SET `clientdes`='$cds'");
		}
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[0=>"Head Office"];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org$cid"."_staff"; $staff=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
	
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":" AND $ltbl.branch='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"$ltbl.branch"):$show;
		
		$ftc = ($vtp==2) ? "AND `expiry`<$tdy":""; $ftc=($vtp==5) ? "AND `expiry`<$npl":$ftc; 
		$cond = $defc = ($vtp==1) ? "($ltbl.balance+penalty)='0'":"($ltbl.balance+penalty)>0 $ftc";
		$cond = (in_array($vtp,[3,4,6])) ? 1:$cond; 
		$cond.= ($me['access_level']=="portfolio") ? " AND $ltbl.loan_officer='$sid'":$show;
		$cond.= ($stid) ? " AND $ltbl.loan_officer='$stid'":"";
		$cond.= ($str) ? " AND ($ltbl.client LIKE '%$str%' OR `phone` LIKE '%$str%' OR `client_idno` LIKE '%$str%')":"";
		$cond.= ($rate) ? " AND JSON_EXTRACT(clientdes,'$.rating')='$rate'":"";
		if($prod){
			if(is_numeric($prod)){ $cond.=" AND `loan_product`='$prod'"; }
			else{
				foreach(explode("-",$prod) as $pid){ $pds[]= "`loan_product`='$pid'"; }
				$cond.= " AND (".implode(" OR ",$pds).")";
			}
		}
		
		$fdy=$dto=0; $loths=$others="";
		if(defined("LOAN_FILTERS")){
			foreach(LOAN_FILTERS as $col){
				if(in_array($col,$cols)){
					$qrc = $db->query(2,"SELECT DISTINCT `$col` FROM `$ltbl` WHERE $cond AND NOT `$col`='' AND NOT `$col`='0' ORDER BY `$col` ASC");
					if($qrc){
						$lst = "<option value=''>-- ".ucwords(str_replace("_"," ",$col))."</option>";
						$pval = (isset($_GET[$col])) ? urldecode(trim($_GET[$col])):"";
						foreach($qrc as $rw){
							$val=prepare($rw[$col]); $cnd=($val==$pval) ? "selected":"";
							$lst.= "<option value='$val' $cnd>".ucfirst($val)."</option>";
						}
						
						$others.= "<select style='font-size:14px;width:130px;padding:7px' 
						onchange=\"loadpage('loans.php?manage=$vtp&bran=$bran&prd=$prod&vcol=$vcol&rtg=$rate&cyc=$lcyc&reg=$rgn&ofid=$stid&$col='+cleanstr(this.value))\">$lst</select> ";
					}
					
					if(isset($_GET[$col])){
						$cond.= (clean($_GET[$col])) ? " AND `$col`='".clean($_GET[$col])."'":"";
						$loths.= (clean($_GET[$col])) ? "&$col=".urlencode(trim($_GET[$col])):"";
					}
				}
			}
		}
		
		if($fro or $dtu){
			$fdy=($fro>$dtu) ? $dtu:$fro; $dto=($fro>$fro) ? $fro:$dtu;
			$cond.= " AND `disbursement` BETWEEN $fdy AND $dto";
		}
		
		if(in_array($vtp,array(3,4))){
			$comp = array(
				3=>["`rescheduled$cid` AS rs ON $ltbl.loan=rs.loan","$ltbl.*,rs.start,rs.fee,rs.duration","<td>Rescheduled</td><td>Duration</td><td>Fees</td>"],
				4=>["`writtenoff_loans$cid` AS wf ON $ltbl.loan=wf.loan","$ltbl.*,wf.amount AS wamnt,wf.time AS wtym","<td>Writeoff</td><td>Date</td>"]
			);
			
			$join = "INNER JOIN ".$comp[$vtp][0]; $chk = $comp[$vtp][1]; $tdh = $comp[$vtp][2];
		}
		else{
			$join=""; $chk="$ltbl.*"; $tdh="<td>Disbursement</td>"; 
			if($lcyc){
				$join = "INNER JOIN `org$cid"."_clients` AS cl ON $ltbl.client_idno=cl.idno";
				$cond.= " AND cl.cycles='$lcyc'";
			}
		}
		
		if($me["position"]=="collection agent" or $vcol){
			$uid = ($vcol) ? $vcol:$sid;
			$cond.= " AND JSON_EXTRACT($ltbl.clientdes,'$.agent')=$uid";
		}
		
		$show_curr_bal = []; $order="`balance` DESC";
		$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND (`category`='client' OR `category`='app')");
		if($sql){
			foreach($sql as $row){
				$pdef = json_decode($row["pdef"],1); $sint=(isset($pdef["showinst"])) ? $pdef["showinst"]:0;
				if($sint){ $show_curr_bal[]=$row["id"]; }
			}
		}
		
		if($odb){
			$oxt = explode(":",$odb); $ocl=$oxt[0];
			$order = ($oxt[1]=="a") ? "`$ocl` ASC":"`$ocl` DESC";
		}
	
		$no=($perpage*$page)-$perpage; $trs=$offs=$brans="";
		if($db->istable(2,$ltbl)){
			$res = $db->query(2,"SELECT $chk FROM `$ltbl` $join WHERE $cond ORDER BY $order $lim");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $lid=$row['loan']; $pid=$row['loan_product']; $expd=$row['expiry'];
					$amnt=fnum($row['amount']); $bal=intval($row['balance']+$row['penalty']); $paid=intval($row['paid']); $dsb=$kj=$row['disbursement'];
					if(in_array($pid,$show_curr_bal)){
						$sqr = $db->query(2,"SELECT SUM(paid) AS tpd,SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `day`<=$tdy");
						$roq = $sqr[0]; $bal=intval($roq['tbal']+$row['penalty']); $paid=intval($roq['tpd']);
					}
					
					$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $ofname=$staff[$row['loan_officer']]; $idno=$row['client_idno'];
					$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$dsb); $exp=date("d-m-Y",$row[$st]); 
					$tpy=fnum($bal+$paid); $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100;  
					
					$color = ($tdy<=$expd) ? "green":"#DC143C"; $color=($vtp==1) ? "#7B68EE":$color; 
					$perc = ($vtp==1) ? "":"<td style='color:#9400D3;font-size:13px;'><b>$prc%</b></td>";
					
					$trh=($vtp==3) ? "<td>".date('d-m-Y',$row['start'])."</td><td>".$row['duration']." days</td><td>".fnum($row['fee'])."</td>":"<td>$disb</td>";
					$trh =($vtp==4) ? "<td>".fnum($row['wamnt'])."</td><td>".date('d-m-Y',$row['wtym'])."</td>":$trh; $lntd="";
					if(in_array($vtp,[0,2,3,5])){
						$tcss = "text-align:center;border-bottom:1px solid #F2F2F2;";
						if($tdy>$expd){ $lntd = "<td style='padding:5px;font-size:13px;background:#D2691E;color:#fff;$tcss'>Overdue</td>"; }
						elseif($expd==$tdy){ $lntd = "<td style='padding:5px;font-size:13px;background:#556B2F;color:#fff;width:100px;$tcss'>Maturing Today</td>"; }
						elseif($expd==($tdy+86400)){ $lntd = "<td style='padding:5px;font-size:13px;background:#483D8B;color:#fff;width:100px;$tcss'>Maturing Tmorow</td>"; }
						else{
							$chk = $db->query(2,"SELECT `day` FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0 AND `day`<=".($tdy+86400)." LIMIT 1");
							if($chk){
								foreach($chk as $rw){
									if($rw["day"]<$tdy){ $lntd = "<td style='padding:5px;font-size:13px;background:orange;color:#fff;$tcss'>In Arrears</td>"; break; }
									elseif($rw["day"]==$tdy){ $lntd = "<td style='width:100px;padding:5px;font-size:13px;background:#008B8B;color:#fff;$tcss'>InDues Today</td>"; break; }
									else{ $lntd = "<td style='width:90px;padding:5px;font-size:13px;background:#483D8B;color:#fff;$tcss'>InDues Tmorow</td>"; break; }
								}
							}
							else{ $lntd = "<td style='padding:5px;font-size:13px;background:green;color:#fff;$tcss'>Active</td>"; }
						}
					}
					
					$trs.= "<tr onclick=\"loadpage('clients.php?showclient=$idno')\" valign='top'><td>$no</td><td>$name</td><td>0$fon</td><td>$ofname</td>$trh<td>$amnt</td><td>$tpy</td>
					<td>".fnum($paid)."</td>$perc<td>".fnum($bal)."</td>$lntd<td style='color:$color'><i class='fa fa-circle'></i> $exp</td></tr>";
				}
			}
		}
		
		$prods = "<option value='0'>-- Loan Product --</option>"; $clusts=[];
		$res = $db->query(2,"SELECT DISTINCT `loan_product` FROM `$ltbl` WHERE $defc");
		if($res){
			$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category` IN ('client','app','asset')");
			foreach($qry as $row){
				$terms = json_decode($row["pdef"],1); $pnames[$row['id']]=prepare(ucfirst($row['product']));
				if(isset($terms["cluster"])){
					if(isset($clusts[$terms["cluster"]])){ $clusts[$terms["cluster"]].="-".$row["id"]; }
					else{ $clusts[$terms["cluster"]]=$row["id"]; }
				}
			}
			
			foreach($res as $row){
				$prd=$row['loan_product']; $cnd=($prd==$prod) ? "selected":"";
				$prods.= (isset($pnames[$prd])) ? "<option value='$prd' $cnd>".$pnames[$prd]."</option>":"";
			}
			if($clusts){
				ksort($clusts);
				foreach($clusts as $grp=>$all){
					if(count(explode("-",$all))>1){
						$cnd = ($all==$prod) ? "selected":"";
						$prods.= "<option value='$all' $cnd>".prepare($grp)."</option>";
					}
				}
			}
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('loans.php?manage=$vtp&prd=$prod&vcol=$vcol&rtg=$rate&cyc=$lcyc&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			$brans.= "<select style='width:140px;font-size:15px' onchange=\"loadpage('loans.php?manage=$vtp&prd=$prod&vcol=$vcol&rtg=$rate&cyc=$lcyc&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($vtp==1) ? "(balance+penalty)='0'":"(balance+penalty)>0 $ftc";
			$brn.= ($me['access_level']=="hq") ? "":" AND `branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ltbl` WHERE $brn");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('loans.php?manage=$vtp&bran=$bran&prd=$prod&vcol=$vcol&rtg=$rate&cyc=$lcyc&reg=$rgn&ofid='+this.value.trim())\">$opts</select>";
			}
		}
		
		$src = base64_encode($cond);
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM($ltbl.amount) AS tdisb,SUM(paid) AS tpy,SUM(balance+penalty) AS tbal FROM `$ltbl` $join WHERE $cond"); 
		$totals = ($sql) ? intval($sql[0]['total']):0; $disb=intval($sql[0]['tdisb']); $tpy=intval($sql[0]['tpy']); $tbal=intval($sql[0]['tbal']);
		$pbtn = ($totals) ? genrepDiv("loans.php?src=$src&v=$vtp&br=$bran&mn=$fdy&dy=$dto&prd=$prod&rtg=$rate&cyc=$lcyc&lof=$stid&odb=$odb",'right'):"";
		$stl = ($view==1) ? "Completed":"Maturity"; $prctd=($vtp==1) ? "":"<td>Percent</td>";
		foreach($show_curr_bal as $pd){
			$sqr = $db->query(2,"SELECT SUM(sd.balance) AS tbal FROM `org$cid"."_schedule` AS sd INNER JOIN `$ltbl` ON sd.loan=$ltbl.loan $join WHERE $cond AND `loan_product`='$pd' AND sd.day>$tdy");
			$tbal-= ($sqr) ? intval($sqr[0]['tbal']):0;
		}
		
		if($trs){
			$tcol = ($vtp==3) ? 7:5; $tcol=($vtp==4) ? 6:$tcol; $tdg = (in_array($vtp,[0,2,3,5])) ? "<td></td>":"";
			$tpr = ($tbal) ? round($tpy/($tbal+$tpy)*100,2):100; $perc=($vtp==1) ? "":"<td>$tpr%</td>";
			$trs.= "<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px'><td colspan='$tcol' style='text-align:right'>Totals</td>
			<td>".fnum($disb)."</td><td>".fnum($tpy+$tbal)."</td><td>".fnum($tpy)."</td>$perc<td>".fnum($tbal)."</td><td></td>$tdg</tr>";
		}
		
		$titles = array("Current Loans","Completed Loans","Overdue Loans","Rescheduled Loans","WrittenOff Loans","Non Performing","All Loans"); $opts="";
		foreach($titles as $key=>$ttl){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$ttl</option>";
		}
		
		$lis = "<option value=''>-- Rating --</option>";
		foreach(array("Unrated"=>"Untagged","Good"=>"Good Payer","BLC"=>"Bad Luck","BFC"=>"Bad Faith","CFC"=>"Control Failure") as $key=>$one){
			$cnd = ($key==$rate) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$one</option>";
		}
		
		$cls = "<option value='0'>Cycles</option>";
		$sql = $db->query(2,"SELECT MAX(cycles) AS mxd FROM `org$cid"."_clients` WHERE `status`='1'");
		if($sql){
			$mxd=$sql[0]['mxd']; 
			for($i=1; $i<=$mxd; $i++){
				$cnd = ($i==$lcyc) ? "selected":"";
				$cls.= "<option value='$i' $cnd>$i</option>";
			}
		}
		
		$import = (in_array("post disbursed loan",$perms)) ? "<button class='bts' style='font-size:14px;padding:5px;float:right' 
		onclick=\"popupload('loans.php?import')\"><i class='fa fa-level-up'></i> Import</button>":"";
		$fdt = ($fdy) ? date("Y-m-d",$fdy)."T".date("H:i",$fdy):""; $tfd=($dto) ? date("Y-m-d",$dto)."T23:59":"";
		$title = ($fdy) ? date("d-m-Y",$fdy)." to ".date("d-m-Y",$dto)." $titles[$vtp]":$titles[$vtp]; 
		
		$ots = ($loths) ? base64_encode($loths):"None"; $odby="";
		$lnth = (in_array($vtp,[0,2,3,5])) ? "<td>Status</td>":"";
		if($trs){
			$ods = "<option value='0'>-- Order By --</option>";
			$list = array("balance:a"=>"Balance Asc","balance:d"=>"Balance Desc","amount:a"=>"Amount Asc","amount:d"=>"Amount Desc","disbursement:a"=>"Disbursement Asc",
			"disbursement:d"=>"Disbursement Desc","expiry:a"=>"Maturity Asc","expiry:d"=>"Maturity Desc");
			foreach($list as $k=>$t){
				$cnd = ($k==$odb) ? "selected":"";
				$ods.= "<option value='$k' $cnd>$t</option>";
			}
			
			$odby = "<select onchange=\"loadpage('loans.php?manage=$vtp&reg=$rgn&bran=$bran&ofid=$stid&prd=$prod&vcol=$vcol&fro=$fdt&dto=$tfd&rtg=$rate&cyc=$lcyc$loths&odb='+this.value)\"
			style='width:130px;padding:7px;font-size:15px' >$ods</select>";
		}
		
		echo "<div class='container cardv' style='max-width:1400px;min-height:400px;overflow:auto;'>
			<div style='padding:7px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title $import</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped stbl' style='width:100%;min-width:900px;font-size:15px' cellpadding='5'>
						<caption style='caption-side:top'> 
							<p><input type='search' style='float:right;width:150px;padding:4px;font-size:15px;'  onkeyup=\"fsearch(event,'loans.php?manage=$vtp&vcol=$vcol&str='+cleanstr(this.value))\"
							onsearch=\"loadpage('loans.php?manage=$vtp&vcol=$vcol&str='+cleanstr(this.value))\" value='".prepare($str)."' placeholder='&#xf002; Search client'>
							<select style='width:130px;padding:5px;font-size:15px;' onchange=\"loadpage('loans.php?vcol=$vcol&manage='+this.value)\">$opts</select>
							<select style='width:150px;padding:5px;font-size:15px;' onchange=\"loadpage('loans.php?manage=$vtp&vcol=$vcol&prd='+this.value)\">$prods</select>
							<select style='width:110px;padding:5px;font-size:15px;' onchange=\"loadpage('loans.php?manage=$vtp&prd=$prod&vcol=$vcol&rtg='+this.value)\">$lis</select>
							<select style='width:80px;padding:5px;font-size:15px;' onchange=\"loadpage('loans.php?manage=$vtp&prd=$prod&vcol=$vcol&rtg=$rate&cyc='+this.value)\">$cls</select></p>
							<p style='margin:0px'>$pbtn $brans $offs $others <span style='padding:8px;color:#191970;font-family:arial;border:1px solid #ccc;font-size:15px;cursor:pointer;' 
							onclick=\"popupload('loans.php?setflt=$vtp~$rgn~$bran~$stid~$prod~$vcol~$fdt~$tfd~$rate~$lcyc~$ots')\"><i class='bi-list-stars'></i> Filter Dates</span> $odby</p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-size:15px;font-weight:bold'><td colspan='2'>Client</td>
						<td>Contact</td><td>Loan Officer</td>$tdh<td>Loan</td><td>To-Pay</td><td>Paid</td>$prctd<td>Balance</td>$lnth<td>$stl</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"loans.php?manage=$vtp&reg=$rgn&bran=$bran&ofid=$stid&prd=$prod&vcol=$vcol&fro=$fdt&dto=$tfd&rtg=$rate&cyc=$lcyc$loths&odb=$odb&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed $title");
	}
	
	# set dates Filter
	if(isset($_GET["setflt"])){
		$def = explode("~",trim($_GET["setflt"]));
		$sql = $db->query(2,"SELECT MIN(disbursement) AS mnd FROM `org$cid"."_loans` WHERE `disbursement`>0");
		$tdy = date("Y-m-d")."T23:59"; $fro=$def[6]; $to=$def[7]; $otc=$def[10]; 
		$min = ($sql) ? date("Y-m-d",$sql[0]['mnd'])."T00:00":"2019-01-01T00:00";
		$fro = ($fro) ? $fro:date("Y-m")."-01T00:00"; $dto=($to) ? $to:$tdy;
		$dtc = ($otc=="None") ? "":base64_decode($otc);
		
		echo "<div style='max-width:300px;margin:0 auto;'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Set Disbursement Range</h3><br>
			<p>Disbursement From<br><input type='datetime-local' style='width:100%' id='fdy' value='$fro' min='$min' max='$tdy'></p>
			<p>Disbursement To<br><input type='datetime-local' style='width:100%' id='fto' value='$dto' min='$min' max='$tdy'></p><br>
			<p style='text-align:right'><button class='btnn' onclick=\"setdt()\">Apply</button></p><br>
		</div>
		<script>
			function setdt(){
				var fro=$('#fdy').val().trim(), dto=$('#fto').val().trim();
				if(fro==''){ $('#fdy').focus(); }
				else if(dto==''){ $('#fto').focus(); }
				else{ closepop(); loadpage('loans.php?manage=$def[0]&reg=$def[1]&bran=$def[2]&ofid=$def[3]&prd=$def[4]&vcol=$def[5]&rtg=$def[8]$dtc&fro='+fro+'&dto='+dto); }
			}
		</script>";
	}
	
	# assign loan to agent
	if(isset($_GET['assign'])){
		$lid = trim($_GET["assign"]);
		$agd = (isset($_GET["agd"])) ? trim($_GET["agd"]):0;
		$qri = $db->query(2,"SELECT `clientdes` FROM `org$cid"."_loans` WHERE `loan`='$lid'");
		$jsn = json_decode($qri[0]['clientdes'],1); $prv=(isset($jsn["agent"]) && !$agd) ? $jsn["agent"]:$agd; $lis="";
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE NOT `position`='USSD' AND `status`='0'");
		if($sql){
			foreach($sql as $row){
				if(isset(staffPost($row['id'])["collection agent"])){
					$rid=$row['id']; $cnd=($prv==$rid) ? "selected":"";
					$lis.= "<option value='$rid' $cnd>".prepare(ucwords($row['name']))."</option>";
				}
			}
		}
		
		$opts = ($lis) ? $lis:"<option value='0'>-- Select Agent --</option>";
		$remv = ($prv) ? "<button type='reset' class='btnn' style='background:#A52A2A' onclick=\"delinkagent('$lid')\">Delink</button> &nbsp":"";
		$btx = ($lis) ? "<p style='text-align:right'>$remv <button class='btnn'>Assign</button></p>":"<p style='color:#ff4500;padding:8px;border:1px solid pink;background:#FFF0F5'>
		There are no Collection Agents in the system, Add one from Company staff Tab</p>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:320px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Assign Loan to Collection Agent</h3><br>
			<form method='post' id='asfom' onsubmit='assignloan(event)'>
				<input type='hidden' name='assignln' value='$lid'>
				<p>Select Collection Agent<br><select style='width:100%' name='agent'>$opts</select></p><br> $btx
			</form><br>
		</div>";
	}
	
	# loan details
	if(isset($_GET['loandes'])){
		$lid = trim($_GET['loandes']);
		$ltbl = "org".$cid."_loans";
		$fields = $db->tableFields(2,$ltbl);
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`"); $names[0]="System";
		foreach($qri as $row){
			$names[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `loan`='$lid'");
		$row = $res[0]; $amnt=number_format($row['amount']); $day=date("d-m-Y, h:i a",$row['time']); $exp=date("d-m-Y, h:i a",$row['expiry']);
		$disb=date("d-m-Y, h:i a",$row['disbursement']); $finished=($row['status']) ? date("d-m-Y, h:i a",$row['status']):"----";
		$ofid=$row['loan_officer']; $pby=$names[$row['creator']]; $tid=$row['tid']; $trs=$lis=$dls=""; $no=0;
		
		$sql = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `id`='$tid'");
		$apps = ($sql) ? $sql[0]['approvals']:0; $appr = ($apps) ? json_decode($apps,1):[]; 
		$tby = ($sql) ? $names[$sql[0]['creator']]."<br>".date("d-m-Y, h:i a",$sql[0]['time']):"----";
		foreach($appr as $one){ $lis.="<li>$names[$one]</li>"; }
		$lis = ($lis) ? $lis:"----"; $dls=$disb;
		
		if($sql){
			$roq=$sql[0]; $cut=$roq['checkoff']+$roq['prepay']; $cut+=(isset($roq['cuts'])) ? array_sum(json_decode($roq['cuts'],1)):0;
			$dsb = (isJson($roq["disbursed"])) ? json_decode($roq["disbursed"],1):[]; 
			if($dsb){ $dls="";
				foreach($dsb as $dy=>$sum){
					$df = ($no) ? 0:$cut; $no++;
					$dls.= "<li>".date("d-m-Y,H:i",$dy)." - ".fnum($sum-$df)."</li>"; 
				}
			}
		}
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `loantype` LIKE 'topup:$tid%'");
		if($qri){
			foreach($qri as $roq){
				$cut=$roq['checkoff']+$roq['prepay']; $cut+=(isset($roq['cuts'])) ? array_sum(json_decode($roq['cuts'],1)):0; 
				$dsb = (isJson($roq["disbursed"])) ? json_decode($roq["disbursed"],1):[]; $jn=0;
				if($dsb){
					foreach($dsb as $dy=>$sum){
						$df = ($jn) ? 0:$cut; $jn++;
						$dls.= "<li>".date("d-m-Y,H:i",$dy)." - ".fnum($sum-$df)."</li>"; 
					}
				}
			}
		}
		
		$exclude = array("id","client","loan_product","client_idno","phone","branch","loan_officer","amount","tid","duration","disbursement","expiry","comments",
		"penalty","paid","balance","loan","status","time","approvals","creator","clientdes");
		$qri = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$inputs = ($qri) ? json_decode($qri[0]['fields'],1):[];
	
		foreach($fields as $col){
			if(!in_array($col,$exclude)){
				$cname = str_replace("_"," ",ucwords($col)); 
				$dtp = (isset($inputs[$col])) ? $inputs[$col]:""; $df=$row[$col];
				$val = ($dtp=="url") ? "<a href='".$row[$col]."' target='_blank'>View</a>":prepare(ucfirst($row[$col]));
				
				if(in_array($dtp,array("image","pdf","docx"))){
					if($dtp=="pdf" or $dtp=="docx"){
						$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
						$url = "https://docs.google.com/viewer?url=http://$url/docs/$df&embedded=true";
						$val = ($df) ? "<a href='$url'><img src='$img' style='height:40px;cursor:pointer'></a>":"----";
					}
					else{ 
						$val = ($dtp=="image" && $df) ? "<img src='data:image/jpg;base64,".getphoto($df)."' style='height:60px;cursor:pointer'
						onclick=\"popupload('media.php?doc=img:$df&tbl=$ltbl:$col&fd=loan:$lid&nch')\">":"----"; 
					}
				}
				
				$trs.= "<tr><td style='color:#191970;font-weight:bold;font-size:14px'>$cname</td><td>$val</td></tr>";
			}
		}
		
		$sql = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='GVASSET$tid'");
		$def = ($sql) ? json_decode($sql[0]["details"],1):[]; $pos=0;
		foreach($def as $one){
			$ext = explode(".",$one)[1]; $pos++;
			if(in_array($ext,["pdf"])){
				$img = ($ext=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
				$url = "https://docs.google.com/viewer?url=http://$url/docs/$one&embedded=true";
				$val = "<a href='$url'><img src='$img' style='height:40px;cursor:pointer'></a>";
			}
			else{
				$val = "<img src='data:image/jpg;base64,".getphoto($one)."' style='height:60px;cursor:pointer'
				onclick=\"popupload('media.php?doc=img:$one&tbl=translogs$cid:ref-$one&fd=loan:$lid&nch')\">"; 
			}
			$trs.= "<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Delivery Note $pos</td><td>$val</td></tr>";
		}
		
		echo "<div style='padding:10px;max-width:500px;margin:0 auto;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Loan Details</h3><br>
			<div style='overflow:auto;'>
				<table class='table-striped' style='margin:0 auto;font-size:15px;min-width:300px' cellpadding='5'>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Loan Amount</td><td>$amnt</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Disbursement</td><td>$dls</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Posted By</td><td>$pby<br>$day</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Template Creation</td><td>$tby</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Approvals</td><td>$lis</td></tr>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Loan Maturity</td><td>$exp</td></tr>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Clearance</td><td>$finished</td></tr> 
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Loan Officer</td><td>$names[$ofid]</td></tr> $trs
				</table><br> 	
			</div>
		</div>";
	}
	
	# import loans
	if(isset($_GET['import'])){
		echo "<div style='padding:10px;max-width:550px;margin:0 auto;min-width:500px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Import Active Loans</h3><br>
			<p style='font-size:14px'>Your Excel file must be formatted as below. <b>Note:</b> Disbursement date <b>must</b> be formatted as <b>dd-mm-YY.</b>
			&nbsp; Example ".date("d-m-Y").". Client & Staff Id numbers <b>must</b> be in the system.<p> 
			<p><img src='$path/assets/img/loans.JPG' style='width:100%'></p>
			<p style='text-align:right'><button class='bts' onclick=\"genreport('dormants.php','xls')\"><i class='fa fa-download'></i> Download Dormant Clients</button></p>
			<form method='post' id='lform' onsubmit='uploadloans(event)'>
				<table style='width:100%' cellpadding='5'>
					<tr><td>Select Your Filled Excel File<br><input type='file' id='lxls' name='lxls' accept='.csv,.xls,.xlsx' required></td>
					<td><button class='btnn' style='float:right'>Upload</button></td></tr>
				</table>
			</form><br>
		</div><br>";
	}
	
	# reschedule loan
	if(isset($_GET['reschedule'])){
		$lid = trim($_GET['reschedule']); $setts=[]; $opts="";
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting` IN ('reschedulefees','rescheduledays')");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$sql = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan`='$lid'"); $row = $sql[0];
		$bal=$row['balance']+$row['penalty']; $name=prepare(ucwords($row['client'])); $prod=$row['loan_product'];
		
		$qri = $db->query(2,"SELECT day FROM `org".$cid."_schedule` WHERE `loan`='$lid' AND `balance`>0 LIMIT 1");
		$fees = (isset($setts['reschedulefees'])) ? $setts['reschedulefees']:0;
		$max = (isset($setts['rescheduledays'])) ? $setts['rescheduledays']:84;
		$mxto = strtotime("Today")+(86400*$max); $min=$qri[0]['day'];
		
		$res = $db->query(1,"SELECT `intervals` FROM `loan_products` WHERE `id`='$prod'");
		$extra = (strpos($fees,"%")!==false) ? round($bal*(str_replace("%","",$fees)/100)):$fees;
		$wks = ($max) ? ceil($max/$res[0]['intervals']):0; $tbal=$bal+$extra;
		
		for($i=1; $i<=$wks; $i++){
			$val = $i*$res[0]['intervals'];
			$opts.="<option value='$val'>$val Days</option>";
		}
		
		$btx = ($mxto>time()) ? "<p style='text-align:right'><button class='btnn'>Reschedule</button></p>":
		"<p style='color:#C71585;'><b>Attention!</b> Maximum allowed time for repayment for this Loan is Over!</p>";
		
		echo "<div style='max-width:300px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Reschedule Loan</h3><br>
			<form method='post' id='rform' onsubmit='rescheduleloan(event)'>
				<input type='hidden' name='loan' value='$lid'> 
				<input type='hidden' name='maxto' value='$mxto'> <input type='hidden' name='fees' value='$extra'>
				<p>Reschedule Loan for <b>$name</b> to pay <b>KES ".fnum($tbal)."</b> latest <b>".date('M d, Y',$mxto)."</b> on additional fees of <b>KES ".fnum($extra)."</b>.</p>
				<p>Reschedule From<br><input type='date' style='width:100%' name='rfrom' min='".date('Y-m-d',$min)."' max='".date('Y-m-d',$mxto)."' required>
				<p>Select Duration<br><select name='dur' style='width:100%' required>$opts</select></p><br> $btx
			</form><br>
		</div>";
	}
	
	# edit loan
	if(isset($_GET['editloan'])){
		$lid = trim($_GET['editloan']);
		$src = trim($_GET["src"]);
		$prd = (isset($_GET['prd'])) ? trim($_GET['prd']):0;
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan`='$lid'");
		$row = $res[0]; $roq=$row; $ofid=$row['loan_officer']; $amnt=$row['amount']; $dur=$row['duration']; $client=prepare(ucwords($row['client']));
		$disb = date("Y-m-d",$row['disbursement']); $prod=($prd) ? $prd:$row['loan_product']; $kesho=date("Y-m-d",time()+86400);
		$idno = $row['client_idno']; 
		
		$setts = ["loansegment"=>1];
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		$dedicated = (array_key_exists("officers",$setts)) ? $setts['officers']:"all";
		
		$opts=$offs=$prods=$adds="";
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
		$payterms = json_decode($res[0]['payterms'],1); $min=$res[0]['minamount']; $max=$res[0]['maxamount'];
		if($setts['loansegment']){
			$arr = $res[0]['duration']/$res[0]['interest_duration']; $intv=$res[0]['intervals'];
			for($i=1; $i<=$arr; $i++){
				$pr=$i*$res[0]['interest_duration'];
				$cnd = ($dur==$pr) ? "selected":"";
				$opts.="<option value='$pr' $cnd>$pr days</option>";
			}
		}
		else{ 
			$pr = $res[0]['duration'];
			$opts="<option value='$pr' $cnd>$pr days</option>";
		}
		
		$ftc = ($src=="asset") ? "AND `id`='$prod'":"";
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='$src' $ftc");
		foreach($res as $row){
			$cnd = ($row['id']==$prod) ? "selected":"";
			$prods.="<option value='".$row['id']."' $cnd>".prepare(ucfirst($row['product']))."</option>";
		}
		
		$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":"";
		$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' AND NOT `position`='USSD' $cond");
		if($res){
			foreach($res as $row){
				if($dedicated=="all"){
					$uid=$row['id']; $cnd = ($uid==$ofid) ? "selected":"";
					$offs.= "<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
				}
				else{
					if(isset(staffPost($row['id'])[$dedicated])){
						$uid=$row['id']; $cnd = ($uid==$ofid) ? "selected":"";
						$offs.= "<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
					}
				}
			}
		}
		else{ $offs ="<option value='0'>No Staff found</option>"; }
		
		$lock = (in_array("update running loan amount",$perms) && $src!="asset") ? "":"readonly style='cursor:not-allowed;background:#f8f8f0'";
		if(defined("LOAN_FILTERS")){
			$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='org$cid"."_loans'");
			$def = array("id"=>"number","client"=>"text","loan_product"=>"text","client_idno"=>"number","phone"=>"number","branch"=>"number","loan_officer"=>"number",
			"amount"=>"number","tid"=>"number","duration"=>"number","disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number",
			"loan"=>"text","status"=>"number","approvals"=>"textarea","disbursed"=>"textarea","clientdes"=>"textarea","creator"=>"number","time"=>"number");
			$cols = ($res) ? json_decode($res[0]['fields'],1):$def; $dsrc=($res) ? json_decode($res[0]['datasrc'],1):[];
			$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
			$temp = (defined("TEMPLATE_DEF_COLS")) ? TEMPLATE_DEF_COLS:[]; 
			
			foreach(LOAN_FILTERS as $grp){
				$fname=ucwords(str_replace("_"," ",$grp)); $dtype=$cols[$grp];
				if(isset($temp[$grp])){
					if(in_array($prod,$temp[$grp])){
						if($dtype=="select"){
							$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$grp],","))[1])); asort($drops); $lis="";
							foreach($drops as $drop){
								$cnd = ($drop==$roq[$grp]) ? "selected":"";
								$lis.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
							}
							$adds.= "<p>$fname<br><select name='excols[$grp]' style='width:100%'>$lis</select></p>";
						}
						elseif($dtype=="link"){
							$tsrc = explode(".",explode(":",$dsrc[$grp])[1]); $tbl=$tsrc[0]; $col=$tsrc[1]; $dbname=(substr($tbl,0,3)=="org") ? 2:1;
							$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $ran=rand(12345678,87654321); $lis=""; 
							foreach($res as $row){
								$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$roq[$grp]) ? "selected":"";
								$lis.="<option value='$val' $cnd></option>";
							}
							
							$adds.="<p>$fname<br> <datalist id='$ran'>$lis</datalist> <input type='hidden' name='dlinks[$grp]' value='$tbl.$col'>
							<input type='text' style='width:100%' name='excols[$grp]' list='$ran' autocomplete='off' required></p>";
						}
						elseif($dtype=="textarea"){
							$adds.= "<p>$fname<br><textarea class='mssg' name='excols[$grp]' required>".prepare(ucfirst($roq[$grp]))."</textarea></p>";
						}
						else{
							$inp = (isset($accept[$dtype])) ? "file":$dtype; $val=prepare(ucfirst($roq[$grp])); 
							$adds.= "<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" name='excols[$grp]' required></p>";
						}
					}
				}
				else{
					if($dtype=="select"){
						$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$grp],","))[1])); asort($drops); $lis="";
						foreach($drops as $drop){
							$cnd = ($drop==$roq[$grp]) ? "selected":"";
							$lis.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
						}
						$adds.= "<p>$fname<br><select name='excols[$grp]' style='width:100%'>$lis</select></p>";
					}
					elseif($dtype=="link"){
						$tsrc = explode(".",explode(":",$dsrc[$grp])[1]); $tbl=$tsrc[0]; $col=$tsrc[1]; $dbname=(substr($tbl,0,3)=="org") ? 2:1;
						$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $ran=rand(12345678,87654321); $lis=""; 
						foreach($res as $row){
							$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$roq[$grp]) ? "selected":"";
							$lis.="<option value='$val' $cnd></option>";
						}
						
						$adds.="<p>$fname<br> <datalist id='$ran'>$lis</datalist> <input type='hidden' name='dlinks[$grp]' value='$tbl.$col'>
						<input type='text' style='width:100%' name='excols[$grp]' list='$ran' autocomplete='off' required></p>";
					}
					elseif($dtype=="textarea"){
						$adds.= "<p>$fname<br><textarea class='mssg' name='excols[$grp]' required>".prepare(ucfirst($roq[$grp]))."</textarea></p>";
					}
					else{
						$inp = (isset($accept[$dtype])) ? "file":$dtype; $val=prepare(ucfirst($roq[$grp])); 
						$adds.= "<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" name='excols[$grp]' required></p>";
					}
				}
			}
		}
		
		echo "<div style='max-width:300px;padding:10px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Edit running Loan</h3><br>
			<form method='post' id='lform' onsubmit=\"saveloan(event,'$idno')\">
				<input type='hidden' name='lid' value='$lid'>
				<p>Loan Product<br><select name='lprod' onchange=\"popupload('loans.php?editloan=$lid&src=$src&prd='+this.value)\" style='width:100%'>$prods</select></p>
				<p>Duration<br><select name='ldur' style='width:100%'>$opts</select></p>
				<p>Loan Amount<br><input type='number' name='lamnt' value='$amnt' $lock required></p>
				<p>Disbursement Date<br><input style='color:#2f4f4f;width:100%' type='date' value='$disb' max='$kesho' name='disb' required></p>
				<p>Loan Officer<br><select name='loff' style='width:100%'>$offs</select></p>$adds<br>
				<p style='text-align:right'><button class='btnn'>Update</button></p><br>
			</form><br>
		</div>";
	}
	
	# daily Disbursements
	if(isset($_GET["dailyds"])){
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$smon = ($yr==date("Y")) ? strtotime(date("Y-M")):strtotime("$yr-Dec");
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):$smon;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$prod = (isset($_GET['prod'])) ? trim($_GET['prod']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
		$monend = date("j",monrange(date("m",$mon),date("Y",$mon))[1]);
		$first_day = date('w',mktime(0,0,0,date("m",$mon),1,date("Y",$mon)));
		$first_day = ($first_day==0) ? 7:$first_day; $opts=$brns="";
		$ltbl = "org$cid"."_loantemplates"; $brans=$staff=[]; 
		
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		foreach($qri as $row){
			$brans[$row['id']] = prepare(ucwords($row['branch']));
		}
		
		$weekdays = array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
		$thead = "<tr style='text-align:center;color:#191970;font-weight:bold;background:#e6e6fa'>";
		foreach($weekdays as $wkdy){
			$thead.= "<td><p style='margin:6px'>$wkdy</p></td>";
		}
		
		$thead.= "</tr>"; $trs=($first_day>1) ? "<tr valign='top'><td colspan='".($first_day-1)."'></td>":"<tr valign='top'>";
		$me = staffInfo($sid); $access=$me['access_level']; $cnf=json_decode($me["config"],1);
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$load = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "`loan_officer`='$sid'":$load;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? setRegion($cnf["region"]):$cond;
		$grpb = ($me["access_level"]=="branch") ? "loan_officer":"branch"; $defc=$cond;
		if($prod){
			if(is_numeric($prod)){ $cond.=" AND `loan_product`='$prod'"; }
			else{
				foreach(explode("-",$prod) as $pid){ $pds[]= "`loan_product`='$pid'"; }
				$cond.= " AND (".implode(" OR ",$pds).")";
			}
		}
		
		if($grpb=="loan_officer"){
			$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
			foreach($res as $row){
				$staff[$row['id']]=prepare(ucwords($row['name']));
			}
		}
		
		for($i=1; $i<=$monend; $i++){
			$dy = ($i<10) ? "0$i":$i; $day=strtotime(date("Y-M",$mon)."-".$dy); 
			$wkdy = date("D",$day); $pos=($i+$first_day-1)%7; $dto=$day+86399;
			$res = $db->query(2,"SELECT SUM(amount) AS tsum,`$grpb` FROM `$ltbl` WHERE $cond AND `status` BETWEEN $day AND $dto GROUP BY $grpb");
			
			if($res){
				$tds=""; $tot=$no=0;
				foreach($res as $row){
					$name = ($grpb=="branch") ? $brans[$row[$grpb]]:$staff[$row[$grpb]]; $amnt=fnum($row["tsum"]); $no++; $tot+=$row["tsum"];
					$tds.= "<tr><td>$name</td><td style='text-align:right'>$amnt</td></tr>";
				}
				
				$tds.= ($no>1) ? "<tr><td colspan='2' style='text-align:right;font-weight:bold'>".fnum($tot)."</td></tr>":"";
				$trs.= "<td><table cellpadding='5' style='width:100%;font-size:14px' class='table-bordered'>
					<tr style='background:#f0f0f0;text-align:center;color:#4682b4' valign='top'><td colspan='2'><b>".date("d-m-Y",$day)."</b></td></tr> $tds
				</table></td>";
			}
			else{ $trs.="<td></td>"; }
			$trs.= ($pos==0) ? "</tr><tr valign='top'>":"";
		}
		
		$trs.="</tr>"; $tmon = strtotime(date("Y-M")); $tyr=date("Y"); $tsf=strtotime("$yr-Jan");
		$qri = $db->query(2,"SELECT from_unixtime(status,'%Y-%b') AS mon FROM `$ltbl` WHERE $cond AND `status`>$tsf GROUP BY mon");
		$res = ($qri) ? $qri:array(["mon"=>date("Y-M")]); $mons=[];
		foreach($res as $row){ $mons[] = strtotime($row['mon']); }
		
		$opts = "<option value='$smon'>".date("M Y",$smon)."</option>"; rsort($mons);
		foreach($mons as $mn){
			$cnd = ($mn==$mon) ? "selected":"";
			$opts.= ($mn!=$smon) ? "<option value='$mn' $cnd>".date('M Y',$mn)."</option>":"";
		}
		
		$yrs = "<option value='$tyr'>$tyr</option>";
		for($i=2024; $i<$tyr; $i++){
			$cnd = ($yr==$i)? "selected":"";
			$yrs.= "<option value='$i' $cnd>$i</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brns = "<select style='width:130px;font-size:15px;padding:5px' onchange=\"loadpage('loans.php?dailyds&year=$yr&mon=$mon&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			$brns.= "<select style='width:150px;font-size:15px;padding:5px' onchange=\"loadpage('loans.php?dailyds&year=$yr&mon=$mon&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$pls = "<option value='0'>-- Loan Product --</option>"; $clusts=[];
		$res = $db->query(2,"SELECT DISTINCT `loan_product` FROM `$ltbl` WHERE $defc");
		if($res){
			$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND (`category`='client' OR `category`='app' OR `category`='asset')");
			foreach($qry as $row){
				$terms = json_decode($row["pdef"],1); $pnames[$row['id']]=prepare(ucfirst($row['product']));
				if(isset($terms["cluster"])){
					if(isset($clusts[$terms["cluster"]])){ $clusts[$terms["cluster"]].="-".$row["id"]; }
					else{ $clusts[$terms["cluster"]]=$row["id"]; }
				}
			}
			
			foreach($res as $row){
				$prd=$row['loan_product']; $cnd=($prd==$prod) ? "selected":"";
				$pls.= "<option value='$prd' $cnd>".$pnames[$prd]."</option>";
			}
			
			if($clusts){
				ksort($clusts);
				foreach($clusts as $grp=>$all){
					if(count(explode("-",$all))>1){
						$cnd = ($all==$prod) ? "selected":"";
						$pls.= "<option value='$all' $cnd>".prepare($grp)."</option>";
					}
				}
			}
		}
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:7px;max-width:1400px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Daily Disbursements<h3><hr style='margin:0px'>
				<div style='width:100%;overflow:auto'>
					<table cellpadding='0' cellspacing='0' style='width:100%;font-size:15px;min-width:700px'>
						<caption style='caption-side:top'>
							<select style='padding:5px;width:70px;margin-right:5px;font-size:15px' onchange=\"loadpage('loans.php?dailyds&year='+this.value)\">$yrs</select>
							<select style='padding:5px;width:120px;font-size:15px' onchange=\"loadpage('loans.php?dailyds&year=$yr&mon='+this.value)\">$opts</select> $brns
							<select style='width:150px;font-size:15px;padding:5px' onchange=\"loadpage('loans.php?dailyds&year=$yr&mon=$mon&reg=$rgn&bran=$bran&prod='+this.value)\">$pls</select>
						</caption> $thead $trs 
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"View Daily disbursements report for ".date("F Y",$mon));
	}
	
	# import errors
	if(isset($_GET['xlserrors'])){
		$data = (file_exists("dbsave/import_errors.dll")) ? json_decode(file_get_contents("dbsave/import_errors.dll"),1):[];
		$titles = array("prodfake"=>"Invalid Loan products","invalid"=>"Unknown Client Id Numbers","running"=>"Running Loans",
		"amounts"=>"Invalid Balances","incomplete"=>"Incomplete Records");
		
		$lis = "";
		foreach($data as $key=>$idnos){
			$ttl = $titles[$key]; $all=count($idnos);
			$lis.="<h3 style='font-size:18px;background:#f0f0f0;color:#4682b4;padding:7px;margin-bottom:10px'>$ttl ($all)</h3>
			<p>".implode(", ",$idnos)."</p>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:450px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Found Errors</h3><br>$lis <br>
		</div>";
	}

?>

<script>
	
	function setarrange(vtp,pos,val){
		var fro = (pos=="arr") ? val:_("arr1").value;
		var to = (pos=="fto") ? val:_("fto1").value;
		loadpage("loans.php?arrears="+fro+"&fto="+to+"&follow="+vtp);
	}
	
	function uploadloans(e){
		e.preventDefault();
		var xls = _("lxls").files[0];
		if(xls!=null){
			if(confirm("Extract Loans from selected File?")){
				var data=new FormData(_("lform"));
				data.append("file",xls);
				var x=new XMLHttpRequest(); progress("Processing...please wait");
				x.onreadystatechange=function(){
					if(x.status==200 && x.readyState==4){
						var res=x.responseText; progress(); 
						if(res.trim().split(":")[0]=="success"){
							var fx = res.trim().split(":")[1],all=res.trim().split(":")[2];
							if(fx=="all"){ toast(all+" Loans extracted and saved successfull!"); closepop(); loadpage("loans.php?manage"); }
							else if(fx=="none"){ alert("None of the Loans Extracted! Correct errors found"); popupload("loans.php?xlserrors"); }
							else{ alert(fx+"/"+all+" records extracted! Correct errors found"); popupload("loans.php?xlserrors"); }
						}
						else{ alert(res.trim()); }
					}
				}
				x.open("post",path()+"dbsave/import_loans.php",true);
				x.send(data);
			}
		}
		else{
			alert("Please select Excel File first");
		}
	}
	
	function requestgotp(fon){
		$.ajax({
			method:"post",url:path()+"dbsave/sendsms.php",data:{requestotp:0,cotp:fon},
			beforeSend:function(){progress("Requesting...please wait");},
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			toast(res);
		});
	}
	
	function smsclients(e){
		e.preventDefault();
		if(confirm("Send Arrears SMS to clients?")){
			var data=$("#sform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/sendsms.php",data:data,
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(" ")[0]=="Sent"){ alert(res); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function closeloan(lid,fee){
		if(confirm("Sure to close current Loan?")){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{closeln:lid,lnfee:fee},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function delinkagent(lid){
		if(confirm("Delink collection agent from client?")){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{delinkagent:lid},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function changetarget(typ,stid,val){
		var nval = prompt("Set Staff Target for Mobile App Disbursements",val);
		if(nval){
			if(confirm("Update Disbursement targets for the staff?")){
				$.ajax({
					method:"post",url:path()+"dbsave/targets.php",data:{setarget:typ,tval:nval,stid:stid},
					beforeSend:function(){ progress("Updating...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Success"); window.location.reload(); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function rolloverln(lid,fee){
		if(confirm("Proceed to Roll-Over the Loan?")){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{rolloverln:lid,lnfee:fee},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function offsetloan(lid,fee){
		if(confirm("Confirm offset Loan?")){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{offsetln:lid,lnfee:fee},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function chargeloan(e){
		e.preventDefault();
		if(confirm("Proceed to add loan charges?")){
			var data = $("#cfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function assignloan(e){
		e.preventDefault();
		if(confirm("Proceed to assign loan to selected agent?")){
			var data = $("#asfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){ progress("Assigning...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function approveloans(e){
		e.preventDefault();
		if(!checkboxes("ploans[]")){ alert("Please check atleast one client to approve"); }
		else{
			var vtp = $("#aop").val(), adds={Approve:"approveloans",Decline:"decline",Pend:"pendloans"}
			if(confirm("Proceed to "+vtp+" selected Loans?")){
				var data = $("#aform").serialize(); data+="&"+adds[vtp]+"=1";
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed to approve loan! Check your internet connection");
				}).done(function(res){
					if(res.trim()=="success"){
						loadpage("loans.php?disbursements"); closepop();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function topuploan(e,max){
		e.preventDefault();
		if(parseInt($("#tamnt").val().trim())>parseInt(max)){ toast("Error! Maximum topup available for client is "+max); }
		else{
			if(confirm("Proceed to create topup Application?")){
				var data = $("#tfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:data,
					beforeSend:function(){ progress("Creating...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						closepop(); toast("Created successfull! Waiting for Approvals"); 
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function confirmdisb(e){
		e.preventDefault();
		if(!checkboxes("dids[]")){ alert("Please check atleast one client whose loan is disbursed"); }
		else{
			if(confirm("Confirm that Loans for selected clients are disbursed?")){
				var data = $("#dform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed to confirm Loans! Check your internet connection");
				}).done(function(res){
					if(res.trim()=="success"){
						loadpage("loans.php?disbursements=1"); closepop();
					}
					else{ toast(res); }
				});
			}
		}
	}
	
	function rescheduleloan(e){
		e.preventDefault();
		if(confirm("Sure to reschedule client Loan?")){
			var data = $("#rform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					closepop(); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveloan(e,idno){
		e.preventDefault();
		if(confirm("Update Client Loan?")){
			var data = $("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					loadpage("clients.php?showclient="+idno); closepop();
				}
				else{ toast(res); }
			});
		}
	}
	
	function postloan(e,tid){
		e.preventDefault();
		if(confirm("Sure to post Loan?")){
			var data = $("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){ progress("Posting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to post loan! Check your internet connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					$("#td"+tid).html(""); closepop();
				}
				else{ toast(res); }
			});
		}
	}
	
	function getphone(idno){
		if(idno.trim().length>2){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{getphone:idno},
				beforeSend:function(){ progress("Fetching phone number..."); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to fetch client phone number");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					_("cphone").value=res.trim().split(":")[1];
					if(res.trim().split(":")[2]>0){ $("#ltp").hide(); }
					else{ $("#ltp").show(); }
				}
				else{ toast(res); }
			});
		}
	}
	
	function giveasset(e){
		e.preventDefault();
		if(confirm("Proceed to Create Asset Loan?")){
			var tp = _("hasdocs").value.trim(),error="";
			if(tp.length>2){
				var data = new FormData(_("afom")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					if(_(files[i]).files[0].size>20971520){ error="File "+_(files[i]).files[0].name+" should be less than 20 MB"; break; }
					else{ data.append(files[i],_(files[i]).files[0]); }
				}
				
				if(error!=""){ alert(error); }
				else{
					var xhr=new XMLHttpRequest();
					xhr.upload.addEventListener("progress",uploadprogress,false);
					xhr.addEventListener("load",uploaddone,false);
					xhr.addEventListener("error",uploaderror,false);
					xhr.addEventListener("abort",uploadabort,false);
					xhr.onload=function(){
						if(this.responseText.trim()=="success"){
							toast("Success"); closepop(); loadpage("loans.php?disbursements");
						}
						else{ alert(this.responseText); }
					}
					xhr.open("post",path()+"dbsave/loans.php",true);
					xhr.send(data);
				}
			}
			else{
				var data=$("#tform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:data,
					beforeSend:function(){ progress("Saving...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast(res); closepop(); loadpage("loans.php?disbursements"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function savetemplate(e){
		e.preventDefault();
		if(confirm("Save loan template?")){
			var tp = _("hasfiles").value.trim(),error="";
			if(tp.length>2){
				var data = new FormData(_("tform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					if(_(files[i]).files[0].size>20971520){ error="File "+_(files[i]).files[0].name+" should be less than 20 MB"; break; }
					else{ data.append(files[i],_(files[i]).files[0]); }
				}
				
				if(error!=""){ alert(error); }
				else{
					var xhr=new XMLHttpRequest();
					xhr.upload.addEventListener("progress",uploadprogress,false);
					xhr.addEventListener("load",uploaddone,false);
					xhr.addEventListener("error",uploaderror,false);
					xhr.addEventListener("abort",uploadabort,false);
					xhr.onload=function(){
						if(this.responseText.trim()=="success"){
							toast("Success"); closepop(); loadpage("loans.php?disbursements");
						}
						else{ alert(this.responseText); }
					}
					xhr.open("post",path()+"dbsave/loans.php",true);
					xhr.send(data);
				}
			}
			else{
				var data=$("#tform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:data,
					beforeSend:function(){ progress("Saving...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast(res); closepop(); loadpage("loans.php?disbursements"); }
					else{ alert(res); }
				});
			}
		}
	}

</script>