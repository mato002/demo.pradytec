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
	if(!isset($_GET['vtp'])){ exit(); }
	$cond = (isset($_GET["des"])) ? base64_decode(str_replace(" ","+",trim($_GET['des']))):"";
	$vtp = explode(":",trim($_GET['vtp']));
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $dels=[];
	$pgs = (isset($_GET["pyd"])) ? "A5":"A4-P";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>$pgs]);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->SetAuthor("PradyTech");
	$mpdf->SetCreator("Prady MFI System");
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	# raw c2b Payments
	if($vtp[0]=="c2b"){
		$day = trim($_GET['dy']);
		$pbl = trim($_GET['pb']);
		$mpdf->AddPage('P');
		$mpdf->SetTitle(date("M d,Y",$day)." MPESA Payments");
		$mpdf->setFooter("<p style='text-align:center;'> ".date("M d,Y",$day)." MPESA Payments: Page {PAGENO}</p>");
		$title = date("M d,Y",$day)." MPESA Payments"; $ftp="I";
		$cond = ($pbl!="all") ? "AND `paybill`='$pbl'":"";
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='receiptlogo'");
		$bcs = ($res) ? $res[0]['value']:1; $dels[]=$logo; $dy=date("Ymd",$day); $no=$total=0;
		if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		file_put_contents("../vendor/tmp/$logo",base64_decode(getphoto($logo)));
		
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `date` LIKE '$dy%' AND NOT `code` LIKE 'WALLET%' AND NOT `code` LIKE 'CHECKOFF%' AND NOT `code` LIKE 'CASH%'
		AND NOT `code` LIKE 'OVERPAY%' AND NOT `code` LIKE 'MERGED%' AND NOT `code` LIKE 'WRITEOFF%' AND NOT `code` LIKE 'PREPAY%' $cond ORDER BY `date` ASC");
		foreach($qri as $row){
			$code=$row['code']; $amnt=fnum($row['amount']); $pb=$row['paybill']; $acc=prepare($row['account']); $fon=$row['phone']; $name=prepare($row['client']);
			$phone=(strlen($fon)>12) ? substr($fon,0,5)."***".substr($fon,-5):$fon; $state=($row['status']) ? "Approved":"Pending"; $total+=$row['amount']; $no++;
			$trs.= "<tr><td>$no</td><td>$pb</td><td>$code</td><td>$name</td><td>$phone</td><td>$acc</td><td>$amnt</td><td>$state</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='70'></p>
		<h3 style='color:#191970;text-align:center;'>$title <br>Ksh ".fnum($total)."</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td>No</td><td>Paybill</td>
			<td>Transaction</td><td>Name</td><td>Phone</td><td>Account</td><td>Amount</td><td>Approval</td></tr> $trs
		</table>";
	}
	
	# single receipt
	if($vtp[0]=="precpt"){
		$pid = trim($_GET['pyd']);
		$sql = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `payid`='$pid'");
		$row = $sql[0]; $code=$row['code']; $recno=prenum($row["receipt"]); $lid=$row["linked"];
		
		$mpdf->AddPage('P'); $ftp="I";
		$mpdf->SetTitle("Payment Receipt $recno");
		$mpdf->setFooter("<p style='text-align:center;'>Payment Receipt $recno: Page {PAGENO}</p>");
		
		$staff = ["System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qri = $db->query(2,"SELECT DISTINCT `payment` FROM `processed_payments$cid` WHERE `payid`='$pid'");
		foreach($qri as $row){ $modes[]=$row['payment']; }
	
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='receiptlogo'");
		$bcs = ($res) ? $res[0]['value']:1; $dels[]=$logo;
		if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		file_put_contents("../vendor/tmp/$logo",base64_decode(getphoto($logo)));
		$cname = ucwords(strtolower(clean($info["company"])));
		
		$tbal = array_sum(getLoanBals($lid)); $data=$ptrs="";
		$res = $db->query(2,"SELECT *,GROUP_CONCAT(payment) AS paymodes,GROUP_CONCAT(amount) AS amounts,SUM(amount) AS totpay FROM `processed_payments$cid` WHERE `payid`='$pid' 
		GROUP BY payid ORDER BY receipt ASC");
		foreach($res as $row){
			$pyid=$row['payid']; $idno=$row['idno']; $name=prepare(ucwords($row['client'])); $code=$row['code'];
			$pays=explode(",",$row['paymodes']); $amnts=explode(",",$row['amounts']); $rno=prenum($row['receipt']);
			$approved=$staff[$row['confirmed']]; $offname=$staff[$row['officer']]; 
			$tym = date("d-m-Y",$row['day']); $lis=""; $list=[];
					
			foreach($pays as $no=>$pay){
				$py=str_replace(" ","_",$pay); $tot=$amnts[$no];
				if(isset($list[$py])){ $list[$py]+=$tot; }
				else{ $list[$py]=$tot; }
			}
					
			foreach($list as $py=>$pay){
				$lis.="<tr><td>".str_replace("_"," ",$py)."</td><td>$code</td><td>".fnum($pay)."</td></tr>";
			}
			
			foreach($modes as $mode){ if(!array_key_exists(str_replace(" ","_",$mode),$list)){ $lis.="<tr><td>$mode</td><td></td><td></td></tr>"; }}
			$lis.= "<tr><td colspan='2' style='text-align:center'><b>TOTALS</b></td><td><b>".fnum(array_sum($list))."</b></td></tr>";
			$backimg = ($bcs) ? "background-image:url('../vendor/tmp/$logo');background-position:center 50px;background-repeat:no-repeat":"";
			
			$data.="<h2 style='color:#191970;text-align:center'>$cname</h2><hr>
			<div style='height:300px;width:100%;float:left'> <div style=\"padding:10px;$backimg\">
				<table cellpadding='4' style='width:100%;overflow: wrap;font-size:11px'>
				<tr><td>Client Name</td><td style='border-bottom:1px dotted #000;color:#191970'>$name</td>
				<td>Date</td><td style='border-bottom:1px dotted #000;color:#191970;'>$tym</td></tr>
				<tr><td>Client IDNO</td><td style='border-bottom:1px dotted #000;color:#191970'>$idno</td>
				<td colspan='2' style='text-align:center;font-size:20px;color:#ff4500;font-family:courier;font-weight:bold'>$rno</td></tr>
				<tr><td>Loan ID</td><td colspan='2' style='border-bottom:1px dotted #000;color:#191970'>$lid</td></tr>
				</table><table cellpadding='4' style='width:100%;overflow: wrap;border:1px solid #ccc; border-collapse:collapse;font-size:11px;margin-top:10px;
				background:rgba(255,255,255,0.4);' border='1'>
				<tr style='background:#f0f0f0;color:#191970'><td><b>Description</b></td><td><b>Transaction</b></td><td><b>Total</b></td></tr>$lis </table>
				<table cellpadding='4' style='width:100%;overflow: wrap;border:1px solid #ccc; border-collapse:collapse;font-size:11px;margin-top:5px' border='1'>
				<tr><td>Posted By <span style='color:#191970'>$approved</span></td><td>Loan Balance <span style='color:#191970'><b>".fnum($tbal)."</b></span></td></tr>
				<tr><td>Signature ..........................</td><td>Print Date: <span style='color:#191970'><b>".date("d-m-Y,H:i")."</b></span></td></tr>
				</table>
			</div></div>";
		}
	}
	
	# receipts
	if($vtp[0]=="receipt"){
		$day = trim($_GET['dy']);
		$mpdf->AddPage('P');
		$mpdf->SetTitle("Payment Receipts");
		$mpdf->setFooter("<p style='text-align:center;'> ".date("M d,Y",$day)." Payment Receipts: Page {PAGENO}</p>");
		$dtp = $vtp[1]; $state=($dtp==2) ? 1:0;
		$ftp = ($dtp) ? "D":"I";
		$title = date("M d,Y",$day)." Payment Receipts";
		
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qri = $db->query(2,"SELECT DISTINCT `payment` FROM `processed_payments$cid` WHERE `day`='$day'");
		foreach($qri as $row){
			$modes[]=$row['payment'];
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='receiptlogo'");
		$bcs = ($res) ? $res[0]['value']:1; $dels[]=$logo;
		if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		file_put_contents("../vendor/tmp/$logo",base64_decode(getphoto($logo)));
		
		$data = $ptrs=""; $payments=[];
		$res = $db->query(2,"SELECT *,GROUP_CONCAT(payment separator ',') AS paymodes,GROUP_CONCAT(amount separator ',') AS amounts,SUM(amount) AS totpay 
		FROM `processed_payments$cid` WHERE `day`='$day' AND `status`='$state' $cond GROUP BY payid ORDER BY receipt ASC ");
		foreach($res as $row){
			$pyid=$row['payid']; $idno=$row['idno']; $name=prepare(ucwords($row['client'])); $code=$row['code'];
			$pays=explode(",",$row['paymodes']); $amnts=explode(",",$row['amounts']); $rno=$row['receipt'];
			$approved=$staff[$row['confirmed']]; $offname=$staff[$row['officer']]; 
			$tym = date("d-m-Y",$row['day']); $lis=""; $list=[]; $payments[$row['payid']]=$rno;
					
			foreach($pays as $no=>$pay){
				$py=str_replace(" ","_",$pay); $tot=$amnts[$no];
				if(array_key_exists($py,$list)){ $list[$py]+=$tot; }
				else{ $list[$py]=$tot; }
			}
					
			foreach($list as $py=>$pay){
				$lis.="<tr><td>".str_replace("_"," ",$py)."</td><td>$code</td><td>".number_format($pay)."</td></tr>";
			}
			
			foreach($modes as $mode){ if(!array_key_exists(str_replace(" ","_",$mode),$list)){ $lis.="<tr><td>$mode</td><td></td><td></td></tr>"; } }
			$lis.="<tr><td colspan='2' style='text-align:center'><b>TOTALS</b></td><td><b>".number_format(array_sum($list))."</b></td></tr>";
			$backimg = ($bcs) ? "background-image:url('../vendor/tmp/$logo');background-position:center 60px;background-repeat:no-repeat":"";
			
			$data.="<div style='height:300px;width:50%;float:left'> <div style=\"padding:10px;$backimg\">
				<table cellpadding='4' style='width:100%;overflow: wrap;font-size:11px'>
				<tr><td>Client Name</td><td style='border-bottom:1px dotted #000;color:#191970'>$name</td>
				<td>Date</td><td style='border-bottom:1px dotted #000;color:#191970;'>$tym</td></tr>
				<tr><td>Client IDNO</td><td style='border-bottom:1px dotted #000;color:#191970'>$idno</td>
				<td colspan='2' style='text-align:center;font-size:20px;color:#ff4500;font-family:courier;font-weight:bold'>$rno</td></tr>
				<tr><td>Loan Officer</td><td colspan='2' style='border-bottom:1px dotted #000;color:#191970'>$offname</td></tr>
				</table>
				<table cellpadding='4' style='width:100%;overflow: wrap;border:1px solid #ccc; border-collapse:collapse;font-size:11px;margin-top:10px;
				background:rgba(255,255,255,0.4);' border='1'>
				<tr style='background:#f0f0f0;color:#191970'><td><b>Description</b></td><td><b>Transaction</b></td><td><b>Total</b></td></tr>$lis
				</table>
				<table cellpadding='4' style='width:100%;overflow: wrap;border:1px solid #ccc; border-collapse:collapse;font-size:11px;margin-top:5px' border='1'>
				<tr><td>Confirmed By <span style='color:#191970'>$approved</span></td><td>Posting Status</td></tr>
				<tr><td>Signature ...........................</td><td>Signature ................................</td></tr>
				</table>
			</div></div>";
			
		}
		
		if($dtp){
			$mpdf->WriteHTML($data);
			$mpdf->AddPage(); $no=0;
			
			foreach($payments as $pid=>$rno){
				$res = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
				$row = $res[0]; $code=$row['code']; $acc=$row['account']; $amnt=number_format($row['amount']);
				$fon=$row['phone']; $cname=prepare($row['client']); $no++;
				$ptrs.="<tr><td>$no</td><td>$cname</td><td>0$fon</td><td>$acc</td><td>$code</td><td>$amnt</td><td>$rno</td></tr>";
			}
			
			$data="<h3 style='text-align:center;color:#191970'>".date('M d, Y',$day)." Payment Sheet</h3>
			<table cellpadding='5' cellspacing='0' class='tbl'>
				<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td>
				<td>Contact</td><td>Account</td><td>Transaction</td><td>Amount</td><td>Receipt</td></tr> $ptrs
			</table>";
			
			$db->execute(2,"UPDATE `processed_payments$cid` SET `status`='1' WHERE `status`='0' AND `day`='$day' $cond");
		}
	}
	
	# payments report
	if($vtp[0]=="payrep"){
		$mpdf->AddPage('A4-L');
		$mon = trim($_GET['mn']);
		$pay = ucfirst(str_replace("_"," ",$vtp[1]));
		$pos = explode(":",trim($_GET['df']));
		
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$brans[0] = "Corporate"; $bcol="";
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($pos[0]=="s"){ $from =$staff[$pos[1]]; $bcol="<td>Branch</td>"; }
		else{ $from = ($res) ? $brans[$pos[1]]." branch":"Head Office"; }
		
		$title = ($pay=="All") ? date("M Y",$mon)." Payments for $from":date("M Y",$mon)." $pay Payments for $from";
		$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
		$mpdf->SetTitle($title);
		
		$trs=""; $no=$total=0; $ftp="I";
		$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' $cond GROUP BY payid ORDER BY time ASC");
		foreach($res as $row){
			$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $code=$row['code']; $rec=$row['receipt']; $lid=$row['linked'];
			$conf=$staff[$row['confirmed']]; $amnt=fnum($row['tamnt']); $tym=date("M-d,H:i",$row['time']); $total+=$row['tamnt']; $no++;
			$day = $db->query(2,"SELECT `disbursement` FROM `org$cid"."_loans` WHERE `loan`='$lid'")[0]['disbursement']; $disb=date("d-m-Y",$day);
			$bran = ($pos[0]=="s") ? "<td>".$brans[$row['branch']]."</td>":"";
			$trs.= "<tr valign='top'><td>$no</td><td>$tym</td><td>$code</td><td>$amnt</td><td>$disb</td><td>$name</td><td>$idno</td>$bran<td>$rec</td><td>$conf</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='70'></p>
		<h3 style='color:#191970;text-align:center;'>$title <br>Ksh ".fnum($total)."</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Date</td>
			<td>Transaction</td><td>Amount</td><td>Disbursement</td><td>Client</td><td>Id No</td>$bcol<td>Receipt</td><td>Approval</td></tr> $trs
		</table>";
		
	}
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(prepstr($title).'.pdf',$ftp);
	foreach($dels as $del){ unlink("../vendor/tmp/$del"); }
	exit;

?>