<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	$vtp = ($vtp==null) ? 3:$vtp;
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Dormant Clients","Active clients","Suspended Clients","All Clients");
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl = "org".CLIENT_ID."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = $titles[$vtp]." from $from";
	
	$ctbl = "org".$cid."_clients";
	$exclude = array("id","time","gender","name","status","creator","cdef","idno","contact","cycles");
	$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
	$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","password"=>"password","branch"=>"number","loan_officer"=>"text","status"=>"number","time"=>"number");
	$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $fields = array_keys($ftc);
	
	foreach($ftc as $col=>$dtp){
		if(in_array($dtp,["image","pdf","docx","doc"])){ $exclude[]=$col; }
	}
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
	$info = mficlient(); $logo=$info['logo'];
	
	$fetch = ($stid) ? "AND `loan_officer`='$stid'":"";
	$fetch.= ($bran) ? " AND `branch`='$bran'":""; $loans=[];
	$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE (balance+penalty)>0 $fetch");
	if($sql){
		foreach($sql as $row){ $loans[$row['client_idno']]=[$row["amount"],$row["balance"]+$row["penalty"]]; }
	}
		
	$no=0; $rows=$ths=[];
	$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY name,status ASC");
	if($res){
		foreach($res as $row){
			$no++; $rid=$row['id']; $tds=[];
			$states=array(0=>"Dormant",1=>"Active",2=>"Suspended",3=>"Unapproved",4=>"OnSignup");
				
			foreach($fields as $key=>$col){
				if(!in_array($col,$exclude)){
					$val=($col=="branch") ? $bnames[$row[$col]]:prepare(ucfirst($row[$col]));
					$val=($col=="loan_officer") ? $staff[$row[$col]]:$val;
					$tds[]=$val; if($no==1){ $ths[]=ucfirst(str_replace("_"," ",$col)); }
				}
			}
				
			$name=prepare(ucwords($row['name'])); array_push($tds,$states[$row['status']]); $idno=$row['idno']; $cont=$row['contact'];
			$lbal=(isset($loans[$idno])) ? $loans[$idno][0]:0; $olb=(isset($loans[$idno])) ? $loans[$idno][1]:0;
			$rows[] = array_merge(array($no,$name,$cont,$idno,$lbal,$olb,$row['cycles']),$tds);
		}
	}
	
	array_push($ths,"Status"); $header = array_merge(array("No","Client","Contact","Id Number","Active Loan","OLB","Loan Cycles"),$ths);
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$rows);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	foreach($ths as $th){ $others[]=17; }
	$widths = array_merge(array(6,20),$others);
	$res = genExcel($data,"A1",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>