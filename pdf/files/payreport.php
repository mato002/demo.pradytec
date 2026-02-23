<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid=$by=substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['ln'])){ exit(); }
	$lid = trim($_GET['ln']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $fon=$info['contact']; $mail=$info['email'];
	$cname = strtoupper(prepare($info['company'])); $addr=prepare(str_replace("~","<br>",$info["address"]));
	
	if(isset($_GET["stf"])){
		$sql = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `loan`='$lid'"); $row=$sql[0];
		$name=prepare(ucwords($row['staff'])); $expd=date("d-m-Y",$row['expiry']); $disb=date("d-m-Y",$row['disbursement']); 
		$bran=$row['branch']; $dur=$row['duration']; $bal=number_format($row['balance']+$row['penalty']); $loan=number_format($row['amount']); $pen=$row['penalty'];
		$title = "$name Loan Statement"; $utl="Employee Name"; $idno=staffInfo($row["stid"])["idno"];
	}
	else{
		$sql = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan`='$lid'"); $row=$sql[0];
		$name=prepare(ucwords($row['client'])); $expd=date("d-m-Y",$row['expiry']); $idno=$row['client_idno']; $disb=date("d-m-Y",$row['disbursement']); 
		$bran=$row['branch']; $dur=$row['duration']; $bal=number_format($row['balance']+$row['penalty']); $loan=number_format($row['amount']); $pen=$row['penalty'];
		$title = "$name Loan Statement"; $utl="Client Name";
	}
	
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
	$bname = prepare(ucwords($res[0]['branch']));
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('P');
	$mpdf->SetAuthor("PradyTech");
	$mpdf->SetCreator("Prady MFI System");
	$mpdf->SetTitle($title);
	$mpdf->SetWatermarkImage("data:image/$ext;base64,".getphoto($logo));
	$mpdf->showWatermarkImage = true;
	$mpdf->setFooter("<p style='text-align:center;'> $title: Page {PAGENO} of {nb}</p>");
	
	$mpdf->WriteHTML("
	    *{margin:0px;}
	    .tbl{border-collapse:collapse;font-size:12px;width:100%;}
	    .tbl tr:nth-child(odd){background:#f0f0f0;}
	    .tbr td {vertical-align:top}
	",1);
	
	$trs=""; $totals=0;
	$res = $db->query(2,"SELECT *,GROUP_CONCAT(payment) AS pays,GROUP_CONCAT(amount) AS pams FROM `processed_payments$cid` WHERE `linked`='$lid' GROUP BY `payid`");
	foreach($res as $rw){
		$pid=$rw['payid']; $pays=explode(",",$rw['pays']); $sums=explode(",",$rw['pams']); $lis=""; $all=[];
		$qry = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'"); $row=$qry[0]; $dy=$row['date'];
		$tym=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
		$amnt=number_format($row['amount']); $code=$row['code']; $paybil=$row['paybill']; $acc=$row['account']; $day=date("d-m-Y, H:i",$tym); $totals+=$row['amount'];
	    foreach($pays as $key=>$li){ 
	        if(isset($all[$li])){ $all[$li]+=$sums[$key]; }
	        else{ $all[$li]=$sums[$key]; }
	    }
	    
	    foreach($all as $key=>$amt){ $lis.="<li>".prepare($key)." - $amt</li>"; }
		$trs.="<tr class='tbr'><td>$day</td><td>$code</td><td>$paybil</td><td>$acc</td><td>$amnt</td><td>$lis</td></tr>"; 
	}
	
	$qri = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `linked`='$lid' AND `payment`='Penalties'");
	$paid = ($qri) ? $qri[0]['tamnt']:0; $paidpen=number_format($paid); $tpen=number_format($paid+$pen);
	
	$data = "<h2 style='color:#191970;text-align:center;margin-bottom:0px;'>$cname</h2>
	<p style='text-align:center;color:#191970;margin-top:0px'><b>$addr</b><br>$mail | $fon</p>
	<h3 style='color:#191970;text-align:center;margin-bottom:0px'>Loan Account Statement</h3><hr>
	<table style='width:100%;border-collapse:collapse;font-size:13px'><tr>
		<td><table cellpadding='3'>
			<tr><td style='text-align:right'>Branch: </td><td><b>$bname</b></td></tr>
			<tr><td style='text-align:right'>$utl: </td><td><b>$name</b></td></tr>
			<tr><td style='text-align:right'>ID Number: </td><td><b>$idno</b></td></tr>
			<tr><td style='text-align:right'>Penalty Charged: </td><td><b>$tpen</b></td></tr>
			<tr><td style='text-align:right'>Print Date: </td><td><b>".date("F d, Y - h:i a")."</b></td></tr>
		</table></td>
		<td style='text-align:right'><table cellpadding='3'>
			<tr><td style='text-align:right'>Loan Amount: </td><td style='text-align:left'><b>$loan</b></td></tr>
			<tr><td style='text-align:right'>Disbursement: </td><td style='text-align:left'><b>$disb</b></td></tr>
			<tr><td style='text-align:right'>Duration: </td><td style='text-align:left'><b>$dur days</b></td></tr>
			<tr><td style='text-align:right'>Paid Penalty: </td><td style='text-align:left'><b>$paidpen</b></td></tr>
			<tr><td style='text-align:right'>Loan Balance: </td><td style='text-align:left'><b>$bal</b></td></tr>
		</table></td>
	</tr></table><hr>
	<h4 style='color:#191970;'>Payment History</h4>
	<table cellpadding='5' class='tbl'>
		<tr style='background:#E6E6FA;color:#191970'><td><b>Date</b></td><td><b>Transaction</b></td><td><b>Paybill</b></td><td><b>Account</b></td><td><b>Amount</b></td>
		<td><b>Breakdown</b></td></tr>$trs<tr><td colspan='4'><b>Totals</b></td><td><b>".number_format($totals)."</b></td><td></td></tr>
	</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(prepstr($title).".pdf",'I');
	if(file_exists("errors.log")){ unlink("errors.log"); }
	exit;

?>