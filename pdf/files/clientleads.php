<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
	$vtp  = trim($_GET['v']);
	$bran = trim($_GET['br']);
	$stid = trim($_GET['stid']);
	$src  = trim($_GET['src']);
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
	
	$stbl="org$cid"."_staff"; $staff=[];
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
		.tbl td{font-size:13px;vertical-align:top;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $trs="";
	
	$res = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `time` BETWEEN $fdy AND $dtu $cond ORDER BY name,status ASC"); 
	if($res){
		foreach($res as $row){
			$states = array("<span style='color:orange;'><i class='fa fa-circle'></i> Unboarded</span>","<span style='color:green;'><i class='fa fa-circle'></i> Boarded</span>");
			$bname=$bnames[$row['branch']]; $name=prepare(ucwords($row['name'])); $rid=$row['id']; $cont=$row['contact']; $idno=$row['idno']; $no++;
			$others=json_decode($row['others'],1); $usa=$staff[$others['creator']]; $loc=prepare(ucwords($others['client_location'])); $kn=0; $go=1; $ols="";
			unset($others['client_location']); unset($others['creator']); 
			foreach($others as $key=>$val){
				if($val){ $ols.= "<li>".ucwords(str_replace("_"," ",$key)).": <i>".prepare(ucfirst($val))."</i></li>"; $kn++; }
			}
			$trs.= "<tr><td>$no</td><td>$name</td><td>$idno</td><td>0$cont</td><td>$bname</td><td>$loc</td><td>$ols</td><td>$usa</td><td>".$states[$row['status']]."</td></tr>";
		}
	}
	
	$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
	<h3 style='color:#191970;text-align:center;'>$title</h3>
	<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$sid]."</h4>
	<table cellpadding='5' cellspacing='0' class='tbl'>
		<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Name</td><td>Idno</td><td>Contact</td><td>Branch</td><td>Client Location</td>
		<td>Other Info</td><td>Creator</td><td>Status</td></tr> $trs
	</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(trim(str_replace(" ","_",$title)).'.pdf','I');
	exit;

?>