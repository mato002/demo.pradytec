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
	if(!isset($_GET['mon'])){ exit(); }
	$mon = trim($_GET['mon']); 
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
	$mpdf->SetAuthor("PradyTech");
	$mpdf->SetCreator("Prady MFI System");
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;font-size:13px;color:#fff}
		.trl td{font-weight:bold;font-size:13px;color:#191970}
	",1);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='assistant' ORDER BY `name` ASC");
		foreach($res as $row){
			$names[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$no=$tds=$tpy=$tns=0; $trs="";
		$res = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon' ORDER BY staff ASC"); 
		if($res){
			$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
			foreach($qri as $row){ $deds[$row['staff']]=$row['deductions']+$row['nita']+$row['helb']+$row['pension']; }
			foreach($res as $row){
				$name=$names[$row['staff']]; $bank=prepare(ucwords($row['bank'])); $acc=$row['account']; $chq=$row['cheque']; $rid=$row['id']; $no++;
				$amnt=number_format($row['amount']); $ded=number_format($deds[$row['staff']]); $pay=number_format($row['amount']+$deds[$row['staff']]); 
				$bnkc=prepare($row['bankcode']); $brnc=prepare($row['branch']); $tds+=$deds[$row['staff']]; $tns+=$row['amount']; $tpy+=$row['amount']+$deds[$row['staff']];
				$trs.="<tr><td>$no</td><td>$name</td><td>$bank</td><td>$bnkc</td><td>$brnc</td><td>$acc</td><td>$chq</td><td>$pay</td><td>$ded</td><td>$amnt</td></tr>";
			}
		}
		
		$title = date("F Y",$mon)." Payslips";
		$trs.= "<tr style='color:#191970'><td colspan='8' style='text-align:right'><b>".number_format($tpy)."</b></td><td><b>".number_format($tds)."</b></td>
		<td><b>".number_format($tns)."</b></td></tr>";
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f;margin-bottom:5px'>Printed on ".date('M d,Y - h:i a')." by ".$names[$by]."</h4>
		<table class='tbl' style='width:100%;font-size:14px' cellpadding='5' cellspacing='0'>
			<tr style='background:#4682b4;' class='trh'><td colspan='2'>Staff</td><td>Bank Name</td><td style='width:100px'>Bank Code</td><td style='width:150px'>
			Branch Name/Code</td><td>Account No</td><td>Cheque No</td><td>Salary</td><td>Deductions</td><td>Net Pay</td></tr> $trs
		</table><br>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf',"I");
	exit;

?>