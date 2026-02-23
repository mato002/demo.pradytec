<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$mon = trim($_GET['mn']);
	$day = trim($_GET['dy']);
	$bran = trim($_GET['br']);
	$prd = trim($_GET['prd']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Current Staff Loans","Completed Staff Loans","Overdue Staff Loans");
	
	$ttl = $titles[$vtp];
	if($prd){
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'");
		$ttl = prepare(ucfirst($qri[0]['product']))." $titles[$vtp]";
	}
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
		
	$dur = ($mon && !$day) ? date("F Y",$mon):"All";
	$dur = ($day) ? date("M d, Y",$day):$dur;
	$title = $dur." $ttl from ".$bnames[$bran];
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1; $ltbl="staff_loans$cid";
	$info = mficlient(); $logo=$info['logo']; $bnames=[];
		
	$stbl="org$cid"."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$no=0; $trs=[];
	$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY balance DESC");
	if($res){
		foreach($res as $row){
			$no++; $rid=$row['id']; $amnt=number_format($row['amount']); $bal=$row['balance']+$row['penalty']; $paid=$row['paid']; $lid=$row['loan'];
			$name=prepare(ucwords($row['staff'])); $fon=$row['phone']; 
			$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$row['disbursement']); $exp=date("d-m-Y",$row[$st]); 
			$tpy=number_format($bal+$paid); $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100;
		
			$qri = $db->query(2,"SELECT SUM(amount) AS tamnt,MAX(time) AS mtm FROM `processed_payments$cid` WHERE `linked`='$lid' AND 
			`time`=(SELECT MAX(time) FROM `processed_payments$cid` WHERE `linked`='$lid') GROUP BY payid");
			$last = ($qri && $paid>0) ? intval($qri[0]['tamnt']):0; $perc = ($vtp==1) ? "100%":"$prc%";
			
			$trs[] = array($no,$name,$fon,$disb,$amnt,$tpy,number_format($paid),$last,$perc,$bal,$exp);
		}
	}
	
	$stl = ($vtp==1) ? "Completed":"Maturity"; 
	$header = array("No","Client","Contact","Disbursement","Loan","ToPay","Paid","LastPaid","Percentage","Balance",$stl);
	
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(6,20,12,20,18,10,10,15,15,15,15,15,15,15);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>