<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$ftc = explode(":",trim($_GET['ftc']));
	$src = trim($_GET['src']);
	$vtp = trim($_GET['v']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$from = $ftc[0]; $to=$ftc[1];
	$to = ($from>$to) ? $from:$to;
	$tarr = array("","Overdue","Running");
	
	$bnames = array(0=>"Corporate");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$add = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = "$add ".$tarr[$vtp]." Loan Arrears from ".date("M-d-Y",$from)." to ".date("M-d-Y",$to);
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		
	$no=0; $trs=[]; $total=$all=0;
	$res = $db->query(2,"SELECT ln.branch,sd.idno,sd.officer,MIN(sd.day) AS dy,sd.loan,ln.balance,ln.client,ln.phone,ln.amount AS tloan,ln.expiry,SUM(sd.balance) AS tbal,ln.disbursement 
	FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.loan=sd.loan WHERE sd.balance>0 AND sd.day BETWEEN '$from' AND '$to' $cond GROUP BY sd.loan ORDER BY sd.day,sd.balance DESC");
	if($res){
		foreach($res as $row){
			$idno=$row['idno']; $ofid=$row['officer']; $day=$row['dy']; $tbal=$row['balance']; $bal=$row['tbal'];
			$client=prepare(ucwords($row['client'])); $fon=$row['phone']; $loan=$row['tloan']; $dsd=date("d-m-Y",$row["disbursement"]);
			$qri = $db->query(2,"SELECT DISTINCT loan,COUNT(*) AS total FROM `$ltbl` WHERE `client_idno`='$idno'");
			$cycle = $qri[0]['total']; $inst=getInstall($row['loan'],$day); $total+=$row['tbal']; $all+=$row['balance']; $no++;
			$tdys = floor((strtotime(date("Y-M-d"))-$day)/86400); $brn=$bnames[$row['branch']]; $lof=$staff[$ofid];
			
			$trs[]=array($client,$fon,$brn,$lof,$loan,$dsd,$cycle,$bal,$inst,date("d-m-Y",$day),$tdys,$tbal);
		}
			
		$trs[] = array(null,null,null,null,null,null,null,$total,null,null,null,$all);
	}
		
	$header = array("Client","Contact","Branch","Loan Officer","Loan","Disbursement","Cycles","Arrears","Installment","Fall Date","Days","Tot Bal");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$add loan arrears$by ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(20,12,20,20,10,8,10,13,18,8,15);
	$res = genExcel($data,"A1",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>