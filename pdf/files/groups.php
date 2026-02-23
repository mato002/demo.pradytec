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
		
		$ctbl = "cgroups$cid";
		$exclude = array("id","def","time","group_name","status","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def;
	
		$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $docs=$dels=[];
		if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){ 
					if($dtp=="image"){
						$res = $db->query(2,"SELECT $col,id FROM `$ctbl` WHERE $cond");
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
						$res = $db->query(2,"SELECT $col,id FROM `$ctbl` WHERE $cond");
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
		
		$fields = array_keys($ftc); $brans=$bnames;
		$stbl = "org".$cid."_staff"; $staff[]="None";
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$no=0; $fro=$no+1; $trs=$ths=$offs=$brns="";
		$show = array_diff(array_keys($ftc),$exclude);
		
		if($db->istable(2,$ctbl)){ 
			$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY group_name,status ASC");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $tds="";
					$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$rid'");
					$states = array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",
					1=>"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>");
					
					foreach($ftc as $col=>$dtp){
						if(in_array($col,$show)){
							$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
							$val = (in_array($col,["loan_officer","creator"])) ? $staff[$row[$col]]:$val;
							$val = (isset($docs[$col.$rid])) ? $docs[$col.$rid]:$val;
							$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
							$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
							if(in_array($col,["group_leader","treasurer","secretary"])){
								$val = ($val) ? prepare(ucwords($db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `id`='$val'")[0]['name'])):"None";
							}
							
							$tds.= "<td>$val</td>"; $ths.=($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					$name = prepare(ucwords($row['group_name'])); $tds.="<td>".$states[$row['status']]."</td>"; $tot=intval($chk[0]["tot"]);
					$trs.= "<tr><td>$no</td><td>$name</td><td>$tot</td>$tds</tr>";
				}
			}
			else{
				foreach($fields as $key=>$col){
					if(in_array($col,$show)){ $ths.= "<td>".ucfirst(str_replace("_"," ",$col))."</td>"; }
				}
			}
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Group Name</td><td>Members</td>$ths<td>Status</td></tr> $trs
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