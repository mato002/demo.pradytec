<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$cond = base64_decode(str_replace(" ","+",trim($_GET['src'])));
	$vtp = trim($_GET['v']);
	$mon = trim($_GET['mn']);
	$day = trim($_GET['dy']);
	$bran = trim($_GET['bran']);
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo'];
	
	$brans=[0=>"Head Office"];
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); 
	if($res){
		foreach($res as $row){ $brans[$row['id']]=prepare(ucwords($row['branch'])); }
	}
	
	$states = array("All templates","Disbursed Loans","Undisbursed Loans","Pended Loans","Declined Loans");
	$dur = ($day) ? date("M d,Y",$day):date("F Y",$mon);
	$title = $brans[$bran]." ".$states[$vtp]." for $dur";
	
	$ltbl = "org".$cid."_loantemplates";
	$fields = ($db->istable(2,$ltbl)) ? $db->tableFields(2,$ltbl):[];
	$exclude = array("id","loan","time","status","payment","client","client_idno","phone","duration","prepay","checkoff","approvals","creator","pref","comments",
	"processing","cuts","disbursed");
		
	$cfield = ($vtp==1) ? "status":"time";
	$cfield = ($vtp==2) ? "time":$cfield;
	
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='org".$cid."_loans'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){
					if(in_array($dtp,["image","docx","pdf"])){ $exclude[]=$col; }
				}
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $lprods[$row['id']]=prepare(ucwords($row['product'])); }
		}
	
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
		$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $dcd=count($levels);
		
		$no=0; $rows=[]; $ctd=0;
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY $cfield DESC");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $idno=$row['client_idno']; $tds=[]; $aptd="";
				if($dcd){
					$nx=0; $status = array(10=>date('d-m-Y,H:i',$row['status']));
					foreach($levels as $pos=>$user){
						$key=$pos-1; $nx+=$pos; 
						$status[$key]="Waiting ".prepare(ucfirst($user));
					}	
					$status[8]="Pending"; $status[9]="Declined";
				}
				else{ $status=array(0=>"Pending",10=>date('d-m-Y,H:i',$row['status'])); }
					
				foreach($fields as $col){
					if(!in_array($col,$exclude)){
						$val=($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
						$val=($col=="loan_officer") ? $staff[$row[$col]]:$val;
						$val=($col=="creator") ? $staff[$row[$col]]:$val;
						$val=($col=="loan_product") ? $lprods[$row[$col]]:$val;
						$val=($col=="amount") ? number_format($row[$col]):$val;
						$val=($col=="duration") ? $row[$col]." Days":$val;
						$tds[]=$val; if($no==1){ $ths[]=ucfirst(str_replace("_"," ",$col)); }
					}
				}
				
				$rid=$row['id']; $prepay=fnum($row['prepay']); $cko=fnum($row['checkoff']); $proc=fnum(array_sum(json_decode($row['processing'],1)));
				$cyc = $db->query(2,"SELECT `cycles` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['cycles'];
				$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $st=($row['status']>10) ? 10:$row['status'];
				$rows[]=array_merge(array($no,$name,$fon,$cyc,$cko,$prepay,$proc,$status[$st]),$tds);
			}
		}
		
	$head = array("No","Client","Contact","Cycles","Checkoff","Prepayment","Loan Processing","Disbursement"); $header=array_merge($head,$ths);
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$rows);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	foreach($ths as $th){ $others[]=15; }
	$widths = array_merge(array(6,20,10,10,10,16),$others);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>