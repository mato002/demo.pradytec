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
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$mon = trim($_GET['mn']);
	$day = trim($_GET['dy']);
	$bran = trim($_GET['br']);
	$prd = trim($_GET['prd']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Current Staff Loans","Completed Staff Loans","Overdue Staff Loans");
	$ttl = $titles[$vtp];
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	if($prd){
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'");
		$ttl = prepare(ucfirst($qri[0]['product']))." $titles[$vtp]";
	}
	
	$dur = ($mon && !$day) ? date("F Y",$mon):"All";
	$dur = ($day) ? date("M d, Y",$day):$dur;
	$title = $dur." $ttl from ".$bnames[$bran];
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
		$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1; $ltbl = "staff_loans$cid";
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $bnames=[];
		
		$stbl = "org$cid"."_staff"; $staff=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$no=0; $trs=""; $tdy=strtotime("Today");
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY balance DESC");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $amnt=number_format($row['amount']); $bal=$row['balance']+$row['penalty']; $paid=$row['paid'];
				$name=prepare(ucwords($row['staff'])); $fon=$row['phone']; $uid=$row['stid']; $lid=$row['loan'];
				$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$row['disbursement']); $exp=date("d-m-Y",$row[$st]); 
				$tpy=number_format($bal+$paid); $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100;
					
				$qri = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `linked`='$lid' GROUP BY payid ORDER BY `time` DESC LIMIT 1");
				$last = ($qri && $paid>0) ? intval($qri[0]['tamnt']):0; 
					
				$color = ($tdy<=$row['expiry']) ? "green":"#DC143C"; $color=($vtp==1) ? "#7B68EE":$color; 
				$perc = ($vtp==1) ? "":"<td style='color:#9400D3;font-size:13px;'><b>$prc%</b></td>";
					
				$trs.= "<tr valign='top'><td>$no</td><td>$name</td><td>0$fon</td><td>$disb</td><td>$amnt</td><td>$tpy</td>
				<td>".number_format($paid)."</td><td>$last</td>$perc<td>".number_format($bal)."</td><td style='color:$color'>$exp</td></tr>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM($ltbl.amount) AS tdisb,SUM(paid) AS tpy,SUM(balance) AS tbal FROM `$ltbl` WHERE $cond"); 
		$disb = intval($sql[0]['tdisb']); $tpy=intval($sql[0]['tpy']); $tbal=intval($sql[0]['tbal']);
		
		if($trs){
			$tpr=($tbal) ? round($tpy/($tbal+$tpy)*100,2):100; $perc=($vtp==1) ? "":"<td>$tpr%</td>";
			$trs.="<tr class='trh'><td colspan='3'></td><td>Totals</td><td>".number_format($disb)."</td><td>".number_format($tpy+$tbal)."</td>
			<td>".number_format($tpy)."</td><td></td>$perc<td>".number_format($tbal)."</td><td></td></tr>";
		}
		
		$stl = ($vtp==1) ? "Completed":"Maturity"; 
		$prctd = ($vtp==1) ? "":"<td>Percent</td>";
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='70'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Contact</td><td>Disbursement</td><td>Loan</td>
			<td>To-Pay</td><td>Paid</td><td>LastPaid</td>$prctd<td>Balance</td><td>$stl</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>