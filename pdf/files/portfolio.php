<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
	$src = trim($_GET['src']);
	$bid = trim($_GET['br']);
	
	$db = new DBO(); 
	$cid = CLIENT_ID; $by=$sid;
	
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$cfield = trim($_GET['cf']);
	$title = (isset($bnames[$bid])) ? $bnames[$bid]." Loan Summary":"Corporte Loan Summary";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-P']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage("L");
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;font-size:14px}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$stbl = "org".$cid."_schedule"; $ltbl = "org".$cid."_loans";
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$cls=$actv=$perfs=$arrears=$rloans=$princs=$intrs=$no=$tpen=0; $trs=$brans=$offs="";
	$ttl = ($cfield=="branch") ? "Branch Name":"Loan Officer";

	$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `balance`>0 $cond");
	foreach($res as $rw){
		$def = $rw[$cfield]; $today=strtotime(date("Y-M-d"));
		$res1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_clients` WHERE `$cfield`='$def' $cond");
		$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `$ltbl` WHERE `$cfield`='$def' AND `balance`>0 $cond");
		$res3 = $db->query(2,"SELECT DISTINCT sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan 
		WHERE sd.balance>0 AND $ltbl.balance>0 AND sd.day<$today AND $cfield='$def' $cond");
		$res4 = $db->query(2,"SELECT SUM(sd.balance) AS arrears,SUM($ltbl.penalty) AS tpen FROM `$ltbl` INNER JOIN `$stbl` AS sd ON $ltbl.loan=sd.loan 
		WHERE $cfield='$def' AND $ltbl.balance>0 AND sd.balance>0 AND sd.day<$today $cond");
		$res5 = $db->query(2,"SELECT SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN (sd.amount-sd.paid) ELSE sd.interest END)) AS intr,
		SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ FROM `$stbl` AS sd
		INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND `$cfield`='$def' $cond");
			
		$clients = $res1[0]['total']; $active=$res2[0]['total']; $arrln=($res3) ? count($res3):0; $perf=$active-$arrln;
		$arrs=($res4) ? $res4[0]['arrears']:0; $pen=($res4) ? $res4[0]['tpen']:0; $intr=$res5[0]['intr']; $princ=$res5[0]['princ'];
		$cls+=$clients; $actv+=$active; $perfs+=$perf; $arrears+=$arrs; $rloans+=$arrln; $princs+=$princ; $intrs+=$intr; $tpen+=$pen;
				
		$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; $no++;
		$trs.= "<tr><td style='float:left'>$name</td><td>".number_format($clients)."</td><td>".number_format($active)."</td>
		<td>".number_format($princ)."</td><td>".number_format($intr)."</td><td>".number_format($pen)."</td><td>".number_format($arrln)."</td>
		<td>".number_format($perf)."</td><td>".number_format($arrs)."</td></tr>";
	}
	
	$trs.= "<tr style='background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff' class='trh'>
	<td>Totals</td><td>".number_format($cls)."</td><td>".number_format($actv)."</td>
	<td>".number_format($princs)."</td><td>".number_format($intrs)."</td><td>".number_format($tpen)."</td><td>".number_format($rloans)."</td>
	<td>".number_format($perfs)."</td><td>".number_format($arrears)."</td></tr>";
	
	$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
	<h3 style='color:#191970;text-align:center;'>$title</h3>
	<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
	<table cellpadding='5' cellspacing='0' class='tbl'>
		<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='width:20%'>$ttl</td><td>Total Clients</td><td>Active Clients</td><td>Active Principal</td>
		<td>Active Interest</td><td>Unpaid Penalties</td><td>Loans-in-Arrears</td><td>Performing Loans</td><td>Total Arrears</td></tr> $trs
	</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace([" ","/"],"_",$title).'.pdf','I');
	exit;

?>