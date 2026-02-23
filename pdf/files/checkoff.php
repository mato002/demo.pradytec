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
	$src = trim($_GET['src']);
	$mon = trim($_GET['mn']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	
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
	
	$from = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = date('F Y',$mon)." Checkoff Loans for $from";
	
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
		
		$no=$totals=0; $trs="";
		$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `code` LIKE 'CHECKOFF%' AND `month`='$mon' 
		$cond GROUP BY code ORDER BY payid ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $rc=$row['receipt']; $ofname=$staff[$row['officer']];
				$day=date("d-m-Y, H:i",$row['time']); $conf=$staff[$row['confirmed']]; $amnt=number_format($row['tamnt']); $no++;
				
				$trs.="<tr><td>$no</td><td>$name</td><td>$idno</td><td>$amnt</td><td>$ofname</td><td>$conf</td><td>$day</td><td>$rc</td></tr>";
			}
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Idno</td><td>Checkoff</td><td>Loan Officer</td><td>Confirmed By</td>
			<td>Date</td><td>Receipt</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>