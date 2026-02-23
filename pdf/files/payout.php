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
	if(!isset($_GET['src'])){ exit(); }
	$fro = trim($_GET['src']);
	$dto = trim($_GET['dy']);
	$brn = (isset($_GET['br'])) ? trim($_GET['br']):0;
	
	$title = ($fro==$dto) ? "B2C Disbursements for ".date("d-m-Y",$dto):"B2C Disbursements from ".date("d-m-Y",$fro)." to ".date("d-m-Y",$dto);
	$period = ($fro==$dto) ? date("d-m-Y",$dto):date("d-m-Y",$fro)." to ".date("d-m-Y",$dto);
	
	$db = new DBO(); $cid=CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $staff[0]="System";
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`"); 
	foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
	
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
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	$trs=""; $totals=$no=0;
	$cond = ($brn) ? "AND `branch`='$brn'":"";
	$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `day` BETWEEN $fro AND $dto $cond ORDER BY `time` ASC");
	foreach($res as $row){
		$phone=$row['phone']; $amnt=number_format($row['amount']); $name=prepare($row['name']); $code=$row['code']; $usa=$staff[$row['user']];
		$tym=date("M d, h:i a",$row['time']); $no++; $totals+=$row['amount'];
		$trs.="<tr><td>$no</td><td>$tym</td><td>$usa</td><td>$phone</td><td>$name</td><td>$code</td><td>$amnt</td></tr>";
	}

	$bname = ($brn) ? prepare(ucwords($db->query(1,"SELECT `branch` FROM `branches` WHERE `id`='$brn'")[0]['branch'])):"Corporate";
	$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='70'></p>
	<h3 style='color:#191970;text-align:center;margin-bottom:0px'>MPESA Disbursement Report</h3><hr>
	<table style='width:100%;border-collapse:collapse;font-size:13px'><tr>
		<td><table cellpadding='3'>
			<tr><td style='text-align:right'>Branch: </td><td><b>$bname</b></td></tr>
			<tr><td style='text-align:right'>Period: </td><td><b>$period</b></td></tr>
			<tr><td style='text-align:right'>Printed By: </td><td><b>".$staff[$by]."</b></td></tr>
		</table></td>
		<td style='text-align:right'><table cellpadding='3'>
			<tr><td style='text-align:right'>Total Disbursements: </td><td style='text-align:left'><b>".number_format($totals)."</b></td></tr>
			<tr><td style='text-align:right'>Print Date: </td><td style='text-align:left'><b>".date("F d, Y - h:i a")."</b></td></tr>
			<tr><td style='text-align:right'>Total Records: </td><td style='text-align:left'><b>$no</b></td></tr>
		</table></td>
	</tr></table><hr>
	<h4 style='color:#191970;'>Payment Activity</h4>
	<table cellpadding='5' class='tbl' cellspacing='0'>
		<tr style='background:#E6E6FA;color:#191970'><td><b>No</b></td><td><b>Date</b></td><td><b>Initiator</b></td><td><b>Phone</b></td><td><b>Recipient</b></td>
		<td><b>Transaction</b></td><td style='text-align:right'><b>Amount</b></td></tr> $trs
	</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf',"I");
	exit;

?>