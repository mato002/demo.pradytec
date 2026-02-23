<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Dormant Groups","Active Groups","Suspended Groups");
	
	$bnames = array(0=>"Corporate");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl="org$cid"."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = ($vtp) ? trim("$from $titles[$vtp]"):trim("$from Client Groups");
	
	$ctbl = "cgroups$cid";
	$exclude = array("id","def","time","group_name","status","creator");
	$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
	$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
	"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
	$ftc = ($res) ? json_decode($res[0]['fields'],1):$def;
	
	foreach($ftc as $col=>$dtp){
		if(in_array($dtp,["image","pdf","docx","doc"])){ $exclude[]=$col; }
	}
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
	$info = mficlient(); $logo=$info['logo'];
	
	$fields = array_keys($ftc); $brans=$bnames;
	$stbl = "org".$cid."_staff"; $staff[]="None";
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$no=0; $fro=$no+1; $trs=$ths=[]; $offs=$brns="";
	$show = array_diff(array_keys($ftc),$exclude);
	
	if($db->istable(2,$ctbl)){ 
		$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY group_name,status ASC");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $tds=[];
				$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$rid'");
				$states = array(0=>"Dormant",1=>"Active",2=>"Suspended");
				
				foreach($ftc as $col=>$dtp){
					if(in_array($col,$show)){
						$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
						$val = (in_array($col,["loan_officer","creator"])) ? $staff[$row[$col]]:$val;
						$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
						$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
						if(in_array($col,["group_leader","treasurer","secretary"])){
							$val = ($val) ? prepare(ucwords($db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `id`='$val'")[0]['name'])):"None";
						}
						
						if($no==$fro){ $ths[]=ucfirst(str_replace("_"," ",$col)); } $tds[]=$val; 
					}
				}
					
				$name = prepare(ucwords($row['group_name'])); $tds[]=$states[$row['status']]; $tot=intval($chk[0]["tot"]);
				$trs[] = array_merge(array($name,$tot),$tds);
			}
		}
		else{
			foreach($fields as $key=>$col){
				if(in_array($col,$show)){ $ths[]= ucfirst(str_replace("_"," ",$col)); }
			}
		}
	}
	
	array_push($ths,"Status"); $header = array_merge(array("Group name","Members"),$ths);
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	foreach($ths as $th){ $others[]=17; }
	$widths = array_merge(array(25,15),$others);
	$res = genExcel($data,"A1",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>