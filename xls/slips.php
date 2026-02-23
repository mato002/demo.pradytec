<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$mon = trim($_GET['mon']);
	$db = new DBO(); $cid = CLIENT_ID;
	
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' ORDER BY `name` ASC");
		foreach($res as $row){
			$names[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$no=$tns=$tds=$tpy=0; $trs=[];
		$res = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon' ORDER BY staff ASC"); 
		if($res){
			$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
			foreach($qri as $row){ $deds[$row['staff']]=$row['deductions']; }
			foreach($res as $row){
				$name=$names[$row['staff']]; $bank=prepare(ucwords($row['bank'])); $acc=$row['account']; $chq=$row['cheque']; $rid=$row['id']; $no++;
				$amnt=number_format($row['amount']); $ded=number_format($deds[$row['staff']]); $pay=number_format($row['amount']+$deds[$row['staff']]);
				$bnkc=prepare($row['bankcode']); $brnc=prepare($row['branch']); $tds+=$deds[$row['staff']]; $tns+=$row['amount']; $tpy+=$row['amount']+$deds[$row['staff']];
				$trs[]= array($name,$bank,$bnkc,$brnc,$acc,$chq,$pay,$ded,$amnt);
			}
		}
		
	$title = date("F Y",$mon)." Payslips";
	$header = array("Staff","Bank Name","Bank Code","Branch Code/Name","Account No","Cheque No","Salary","Deductions","Net Pay");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(25,20,20,20,20,15,16,16,16,16);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>