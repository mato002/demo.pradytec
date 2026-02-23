<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$cond = base64_decode(str_replace(" ","+",trim($_GET['des'])));
	$vtp = explode(":",trim($_GET['vtp']));
	$db = new DBO(); $cid=CLIENT_ID;
	$prot = protectDocs($by);
	$pass = ($prot) ? $prot['password']:null;
	
	# payments report
	if($vtp[0]=="payrep"){
		$mon = trim($_GET['mn']);
		$pay = ucfirst(str_replace("_"," ",$vtp[1]));
		$pos = explode(":",trim($_GET['df']));
		
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$brans[0] = "Corporate"; 
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($pos[0]=="s"){ $from =$staff[$pos[1]]; }
		else{ $from = ($res) ? $brans[$pos[1]]." branch":"Head Office"; }
		
		$title = ($pay=="All") ? date("M Y",$mon)." Payments for $from":date("M Y",$mon)." $pay Payments for $from";
		$data = array([null,$title,null,null],array("No","Date","Transaction","Amount","Disbursement","Client","Id Number","Branch","Receipt","Approval")); $no=0;
		$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' $cond GROUP BY payid ORDER BY time ASC");
		foreach($res as $row){
			$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $code=$row['code']; $receipt=number_format($row['receipt']);
			$conf=$staff[$row['confirmed']]; $amnt=number_format($row['tamnt']); $tym=date("M-d,H:i",$row['time']); $lid=$row['linked']; $no++;
			$day = $db->query(2,"SELECT `disbursement` FROM `org$cid"."_loans` WHERE `loan`='$lid'")[0]['disbursement']; $disb=date("d-m-Y",$day);
			$data[] = array($no,$tym,$code,$amnt,$disb,$name,$idno,$brans[$row['branch']],$receipt,$conf);
		}
		
		$widths = array(6,13,15,10,20,12,10,20); $fname=prepstr("$title ".date("His"));
		$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	
?>