<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$src = trim($_GET['src']);
	$mon = trim($_GET['mn']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = date('F Y',$mon)." Checkoff Loans for $from";
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$info = mficlient(); $logo=$info['logo'];
		
	$no=$totals=0; $trs=[];
	$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND `month`='$mon' 
	$cond GROUP BY code ORDER BY payid ASC");
	if($res){
		foreach($res as $row){
			$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $rc=$row['receipt']; $ofname=$staff[$row['officer']];
			$day=date("d-m-Y, H:i",$row['time']); $conf=$staff[$row['confirmed']]; $amnt=number_format($row['tamnt']); $totals+=$row['tamnt']; $no++;
				
			$trs[]=array($no,$name,$idno,$amnt,$ofname,$conf,$day,$rc);
		}
	}
		
	$header = array("No","Client","IDNO","Checkoff","Loan Officer","Confirmed By","Date","Receipt");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title$by ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(6,20,10,13,20,20,15,10);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>