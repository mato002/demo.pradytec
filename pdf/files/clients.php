<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid=$by=substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("pcre.backtrack_limit",10000000);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	$vtp = ($vtp==null) ? 3:$vtp;
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = (defined("CLIENT_STATES")) ? CLIENT_STATES:array("Dormant Clients","Active clients","Suspended Clients");
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl="org".CLIENT_ID."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = $titles[$vtp]." from $from";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
		
		$ctbl = "org".$cid."_clients";
		$exclude = array("id","password","time","gender","name","status","creator","cdef");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"text","branch"=>"number",
		"loan_officer"=>"text","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $fields = array_keys($ftc);
	
		$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $docs=$dels=[];
		if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='org".$cid."_clients'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){ 
					if($dtp=="image"){
						$res = $db->query(2,"SELECT $col,id FROM `org".$cid."_clients` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; 
								if($df){ file_put_contents("../vendor/tmp/$df",base64_decode(getphoto($df))); $dels[]=$df; }
								$img = ($df) ? "../vendor/tmp/$df":"$path/assets/img/user.png";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;'>";
							}
						}
					}
					if($dtp=="pdf" or $dtp=="docx"){
						$res = $db->query(2,"SELECT $col,id FROM `org".$cid."_clients` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;'>";
							}
						}
					}
				}
			}
		}
		
		$no=0; $trs=$ths="";
		$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY name,status ASC");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $tds="";
				$states = array(0=>"<span style='color:orange;'>Dormant</span>",1=>"<span style='color:green;'>Active</span>",2=>"<span style='color:#9932CC;'>Suspended</span>",
				3=>"<span style='color:#2F4F4F;'>UnApproved</span>",4=>"<span style='color:#1E90FF;'>OnSignup</span>");
				
				foreach($fields as $key=>$col){
					if(!in_array($col,$exclude)){
						$val=($col=="branch") ? $bnames[$row[$col]]:prepare(ucfirst($row[$col]));
						$val=($col=="loan_officer") ? $staff[$row[$col]]:$val;
						$val=(array_key_exists($col.$row['id'],$docs)) ? $docs[$col.$row['id']]:$val;
						$tds.="<td>$val</td>"; $ths.=($no==1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
					}
				}
				
				$name=prepare(ucwords($row['name'])); $idno=$row['idno'];
				$trs.="<tr><td>$no</td><td>$name</td>$tds<td>".$states[$row['status']]."</td></tr>";
			}
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Name</td>$ths<td>Status</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	foreach($dels as $del){ unlink("../vendor/tmp/$del"); }
	exit;

?>