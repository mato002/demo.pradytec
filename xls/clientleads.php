<?php

	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$vtp  = trim($_GET['v']);
	$bran = trim($_GET['br']);
	$stid = trim($_GET['stid']);
	$src = trim($_GET['src']);
	$dur = explode(":",trim($_GET['dur']));
	$fdy = $dur[0]; $dtu=$dur[1];
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Unboarded Leads","Boarded Leads","All Leads");
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl = "org$cid"."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}

	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = $titles[$vtp]." from $from"; $trs=[];
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
	
	$res = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `time` BETWEEN $fdy AND $dtu $cond ORDER BY name,status ASC"); 
	if($res){
		foreach($res as $row){
			$states = array("Unboarded","Boarded");
			$bname=$bnames[$row['branch']]; $name=prepare(ucwords($row['name'])); $rid=$row['id']; $cont=$row['contact']; $idno=$row['idno']; 
			$others=json_decode($row['others'],1); $usa=$staff[$others['creator']]; $loc=prepare(ucwords($others['client_location'])); $ols="";
			unset($others['client_location']); unset($others['creator']); 
			foreach($others as $key=>$val){
				if($val){ $ols.= ucwords(str_replace("_"," ",$key))." : ".prepare(ucfirst($val))."; "; }
			}
			$trs[] = array($name,$idno,$cont,$bname,$loc,$ols,$usa,$states[$row['status']]);
		}
	}
	
	$header = array("Name","Idno","Contact","Branch","Client Location","Other Info","Creator","Status");
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $pass=($prot) ? $prot['password']:null;
	
	$fname=prepstr("$title ".date("His"));
	$res = genExcel($data,"A2",array(20,12,12,15,20,25,20,15),"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>