<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid=$by=substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
	$dfro = trim($_GET['fd']);
	$dto = trim($_GET['td']);
	$type = trim($_GET['pm']);
	$bran = trim($_GET['br']);
	$stid = trim($_GET['stid']);
	$src = trim($_GET['src']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$pays = array("MPESA"=>"Mpesa Paybill Payments","CHECKOFF"=>"Checkoff Payments","CASH"=>"Cash Payments");
	
	$staff = array(0=>"System");
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$ptp = ($type) ? $type:"MPESA";
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = $pays[$ptp]." for $from from ".date("d-m-Y",$dfro)." to ".date("d-m-Y",$dto);
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-P']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('P');
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
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
		
		$no=0; $trs="";
		$res = $db->query(2,"SELECT *,GROUP_CONCAT(payment separator ',') AS paymodes,GROUP_CONCAT(amount separator ',') AS amounts,
		SUM(amount) AS totpay,MAX(id) AS rid FROM `processed_payments$cid` WHERE $cond GROUP BY payid ORDER BY id DESC");
		if($res){
			foreach($res as $row){
				$pyid=$row['payid']; $idno=$row['idno']; $name=prepare(ucwords($row['client'])); $code=$row['code'];
				$pays=explode(",",$row['paymodes']); $amnts=explode(",",$row['amounts']); $rid=$row['rid'];
				$approved=$staff[$row['confirmed']]; $id=$row['id']; $amnt=number_format($row['totpay']); $lis=""; $list=[];
				$tym = ($str or ($dto!=$day)) ? date("d-m-Y,H:i",$row['time']):date("h:i a",$row['time']); $no++;
					
				foreach($pays as $k=>$pay){
					$py=str_replace(" ","_",$pay); $tot=$amnts[$k];
					if(array_key_exists($py,$list)){ $list[$py]+=$tot; }
					else{ $list[$py]=$tot; }
				}
					
				foreach($list as $py=>$pay){
					$lis.="<li>".str_replace("_"," ",$py)." - ".number_format($pay)."</li>"; 
				}
				
				$trs.="<tr valign='top' style='font-size:15px'><td>$no</td><td>$code</td><td>$amnt</td><td>$lis</td><td>$name<br>$idno</td><td>$approved</td><td>$tym</td></tr>";
			}
		}
	
	$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='80'></p>
	<h3 style='color:#191970;text-align:center;'>$title</h3>
	<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
	<table cellpadding='5' cellspacing='0' class='tbl'>
		<tr style='background:#e6e6fa;' class='trh'>
		<td colspan='2'>Code</td><td>Amount</td><td>Payment Details</td><td>Client</td><td>Approved By</td><td>Date</td></tr> $trs
	</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>