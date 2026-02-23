<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$mon = trim($_GET['mon']);
	$db = new DBO(); $cid = CLIENT_ID;
	
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` ORDER BY `name` ASC");
		foreach($res as $row){
			$names[$row['id']]=array("status"=>$row['status'],"name"=>prepare(ucwords($row['name'])),"idno"=>$row['idno']);
		}
		
		$trs=[]; $no=$tot1=$tot2=$tot3=$tot4=$tot5=$tot6=$tot7=$tot8=$tot9=$tot10=0;
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$bpay=number_format($row['basicpay']); $allow=number_format($row['allowance']); $benefits=number_format($row['benefits']); 
				$nssf=number_format($row['nssf']); $grospay=number_format($row['grosspay']); $taxable=number_format($row['taxablepay']); 
				$paye=number_format($row['paye']);  $rid=$row['id']; $nhif=number_format($row['nhif']); $deduct=number_format($row['deductions']); 
				$netpay=number_format($row['netpay']); $stid=$row['staff']; $name=$names[$stid]['name']; $no++;
				
				$tot1+=$row['basicpay']; $tot2+=$row['allowance']; $tot3+=$row['benefits']; $tot4+=$row['grosspay']; $tot5+=$row['taxablepay']; 
				$tot6+=$row['paye']; $tot7+=$row['nssf']; $tot8+=$row['nhif']; $tot9+=$row['deductions']; $tot10+=$row['netpay']; 
				
				$trs[] = array($name,$bpay,$allow,$benefits,$grospay,$taxable,$paye,$nssf,$nhif,$row["helb"],$row["nita"],$row["pension"],$deduct,$netpay);
			}
			
			$qri = $db->query(3,"SELECT SUM(helb) AS thb,SUM(nita) AS nta,SUM(pension) AS pns FROM `payroll$cid` WHERE `month`='$mon'");
			$thb = ($qri) ? intval($qri[0]['thb']):0; $nta=($qri) ? intval($qri[0]['nta']):0; $pns=($qri) ? intval($qri[0]['pns']):0;
		
			$trs[] = array("Totals",number_format($tot1),number_format($tot2),number_format($tot3),number_format($tot4),number_format($tot5),number_format($tot6),
			number_format($tot7),number_format($tot8),number_format($thb),number_format($nta),number_format($pns),number_format($tot9),number_format($tot10));
		}
		
	$title = date("F Y",$mon)." Payroll";
	$header = array("Staff","Basic Pay","Allowances","Benefits","Gross Pay","Taxable Pay","PAYE","NSSF","NHIF","HELB","NITA","Pension","Deductions","Net Pay");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(25,15,15,15,16,16,16,16,16,16,16,16,16,20);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>