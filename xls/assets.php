<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$src = trim($_GET['src']);
	$db = new DBO(); $cid = CLIENT_ID;
	
	$cond = ($src==null) ? 1:"`branch`='$ftc'";
	if($src){
		$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
		$bname = prepare(ucwords($qry[0]['branch']));
	}
	else{ $bname = ($src==null) ? "All":"Head Office"; }
		
		$trs=[]; $no=$sum=0;
		$res = $db->query(3,"SELECT *FROM `assets$cid` WHERE $cond ORDER BY `item` ASC");
		if($res){
			foreach($res as $row){
				$item=prepare(ucwords($row['item'])); $cat=prepare($row['category']); $desc=prepare(ucfirst($row['details'])); 
				$recur = date('M d',strtotime($row['cycle'])); $cost=number_format($row['cost']); $rid=$row['id']; $no++;
				$dept=prepare(ucfirst($row['office'])); $depr=$row['depreciation']."% every $recur"; $sum+=$row['cost'];
				$trs[] = array($item,$cat,$desc,$dept,$cost,$depr);
			}
		}
	
	$title = "$bname Company Assets";
	$header = array("Item","Category","Description","Department/office","Cost","Depreciation");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title$by ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(25,25,30,25,15,15);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>