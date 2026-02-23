<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$me = staffInfo($by);
	$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
	$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$by'":$cond;
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=$row['idno'];
	}
	
	$trs = [];
	$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `status`='0' $cond ORDER BY `name` ASC");
	if($res){
		foreach($res as $row){
			$trs[] = array(prepare(ucfirst($row['name'])),$row['idno'],null,null,null,null,null,0);
		}
	}
		
	$header = array("CLIENT NAME","IDNO","LOAN PRODUCT","LOAN AMOUNT","DURATION DAYS","DISBURSEMENT DATE","BALANCE","PENALTIES");
	$dat = array([null],$header); $data=array_merge($dat,$trs); $fname=prepstr("dormant clients ".date("His"));
	
	$widths = array(25,15,22,18,18,22,18,18,18,18,15);
	$res = genExcel($data,"A1",$widths,"docs/$fname.xlsx","Dormant Clients",null);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>