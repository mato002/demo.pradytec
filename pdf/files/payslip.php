<?php
	session_start();
	ob_start();
	
	if(isset($_POST['paymonth'])){
		$sid=$by=intval($_GET['src']);
		if($sid<1){ exit(); }
	}
	else{
		if(!isset($_SESSION['myacc'])){ exit(); }
		$sid=$by=substr(hexdec($_SESSION['myacc']),6);
		if($sid<1){ exit(); }
	}
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	require_once __DIR__ . '/../vendor/autoload.php';
	
	if(!isset($_GET['src'])){ exit(); }
	$pid = (isset($_POST['pid'])) ? trim($_POST['pid']):"view";
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo = $info['logo']; $ext=explode(".",$logo)[1];
	$addr = nl2br(prepare(str_replace("~","<br>",$info['address'])));
	$cname = ucwords(prepare($info['company'])); $cemail=$info['email']; $cont=$info['contact'];
	
	if($pid!="view"){
		$mon = trim($_POST['paymonth']); 
		$sendmail = trim($_POST['sendmail']); 
		$img = trim($_POST['stamp']);
		$stamp = "<img src='../../docs/img/$img' style='height:80px'>";
		$cond = ($pid) ? "AND `id`='$pid'":""; $defs=$bonuses=$blist=[]; $result="success";
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($qri as $row){ $staff[$row['id']]=$row; }
		
		$res = $db->query(3,"SELECT *,COUNT(*) AS total FROM `deductions$cid` WHERE `month`='$mon' GROUP BY `category`");
		if($res){
			foreach($res as $row){ $defs[$row['total']]=prepare(ucwords($row['category'])); }
		}
		
		if($db->istable(3,"bonuses$cid")){
			$qri = $db->query(3,"SELECT * FROM `bonuses$cid` WHERE `month`='$mon' AND `status`='200'");
			if($qri){
				foreach($qri as $row){ $bonuses[$row['staff']]=$row['bonus']; $blist[$row['staff']]=json_decode($row['details'],1); }
			}
		}
		
		$qry = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `month`='$mon' $cond");
		foreach($qry as $rw){
			$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-P']);
			$mpdf->SetDisplayMode('fullpage');
			$mpdf->mirrorMargins = 1;
			$mpdf->defaultPageNumStyle = '1';
			$mpdf->setHeader();
			$mpdf->AddPage('P');
			$mpdf->SetAuthor($cname);
			$mpdf->SetCreator("Prady MFI System");
			$mpdf->SetTitle(prepare(ucwords($staff[$rw['staff']]['name']))." ".date("F Y",$mon)." Payslip");
			$mpdf->SetWatermarkImage("data:image/$ext;base64,".getphoto($logo));
			$mpdf->showWatermarkImage = true;
	
			$stid=$rw['staff']; $bank=prepare(ucwords($rw['bank'])); $bran=prepare(ucwords($rw['branch'])); $acc=$rw['account']; 
			$cheque=$rw['cheque']; $amnt=fnum($rw['amount'],2); $pid=$rw['id']; $empno=$staff[$stid]['jobno'];
			$name=prepare(ucwords($staff[$stid]['name'])); $actp=ucwords($staff[$stid]['position']); $email=$staff[$stid]['email'];
			
			$res = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `staff`='$stid' AND `month`='$mon'"); 
			$row = $res[0]; $nssf=fnum($row['nssf'],2); $bpay=fnum($row['basicpay'],2); $helb=$row['helb']; $cuts=json_decode($row['cuts'],1);
			$allow=fnum($row['allowance'],2); $benefits=fnum($row['benefits'],2); $pens=$row['pension']; 
			$grospay=fnum($row['grosspay'],2); $taxable=fnum($row['taxablepay'],2); $paye=fnum($row['paye'],2);
			$nhif=fnum($row['nhif'],2); $deduct=fnum($row['deductions'],2); $netpay=fnum($row['netpay'],2); $nita=$row['nita'];
			$period=date("F Y",$row['month']); $bonus=(isset($bonuses[$stid])) ? fnum($bonuses[$stid]):0;
			$tots=fnum($row['nssf']+$row['paye']+$row['nhif']+$row['deductions']+$row['helb']+$row['nita']+$row['pension']+array_sum($cuts),2);
			
			$fmt = new NumberFormatter("en",NumberFormatter::SPELLOUT);
			$numwords = ucfirst($fmt->format($row['netpay']))." only"; $dls="";
			
			foreach($cuts as $tp=>$sum){ $dls.= "<li>".prepare($tp)." : ".fnum($sum,2)."</li>"; }
			$dls.= ($helb>0) ? "<li>HELB : ".fnum($helb,2)."</li>":""; $dls.=($nita>0) ? "<li>NITA : ".fnum($nita,2)."</li>":""; 
			$dls.= ($pens>0) ? "<li>Pension : ".fnum($pens,2)."</li>":"";
			$ths = ($dls) ? "<tr><td colspan='2'></td><td>Statutory Deductions</td><td style='text-align:right'><b>$dls</b></td></tr>":"";
			
			if(count($defs)){
				krsort($defs); $key=array_keys($defs)[0]; $ded = $defs[$key]; $ded1=prepare(ucfirst($ded)); $d1amnt="0.00";
				foreach($defs as $cat){
					$qri = $db->query(3,"SELECT *,SUM(amount) AS tamnt FROM `deductions$cid` WHERE `staff`='$stid' AND `month`='$mon' AND `category`='$cat'");
					if($qri){
						$tamnt=intval($qri[0]['tamnt']); $ded1=prepare(ucfirst($cat)); $d1amnt=fnum($tamnt,2); 
						$deduct = fnum($row['deductions']-$tamnt,2); break;
					}
				}
			}
			else{
				if($row['deductions']){
					$sql = $db->query(3,"SELECT *,SUM(amount) AS tamnt FROM `deductions$cid` WHERE `month`='$mon' AND `staff`='$stid' GROUP BY category");
					if($sql){
						foreach($sql as $roq){
							$lis[intval($roq['tamnt'])]=prepare(ucwords($roq['category']));
						}
						
						krsort($lis); $key=array_keys($lis)[0]; $ded1=prepare(ucfirst($lis[$key]));
						$d1amnt=fnum($key,2); $deduct = fnum($row['deductions']-$key,2);
					}
					else{ $ded1 = "Salary Advances"; $d1amnt="0.00"; }
				}
				else{ $ded1 = "Salary Advances"; $d1amnt="0.00"; }
			}
		
			$data = "<div style='width:100%;height:100%;border:1px solid #191970;font-family:helvetica'>
				<table cellpadding='5' style='width:100%;font-family:helvetica'><tr>
					<td style='width:380px'><img src='data:image/$ext;base64,".getphoto($logo)."' style='margin:15px;height:100px'></td>
					<td><p style='color:#2f4f4f'>$cname<br>$addr<br>$cont | $cemail</p></td>
				</tr><tr><td colspan='2' style='text-align:center;'><h3 style='color:#3CB371;font-family:courier'>Salary Slip ".prenum($pid)."</h3></td></tr>
				</table>
			
				<div style='padding:10px;'>
					<table cellpadding='4' style='width:420px;font-family:courier;color:#2f4f4f'>
						<tr><td><h3>Employee Name:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$name</h3></td></tr>
						<tr><td><h3>Job No:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$empno</h3></td></tr>
						<tr><td><h3>Designation:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$actp</h3></td></tr>
						<tr><td><h3>Period:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$period</h3></td></tr>
					</table><br><br>
					<table style='width:100%;border:1px solid #2f4f4f;border-collapse:collapse' cellpadding='5' cellspacing='0' border='1'>
						<tr style='background:#f0f0f0;'><td colspan='2'><h3 style='color:#191970'>Earnings</h3></td><td colspan='2'><h3 style='color:#191970'>Deductions</h3></td></tr>
						<tr><td>Basic Salary</td><td style='text-align:right'><b>$bpay</b></td><td>NSSF</td><td style='text-align:right'><b>$nssf</b></td></tr>
						<tr><td>Allowances</td><td style='text-align:right'><b>$allow</b></td><td>SHIF</td><td style='text-align:right'><b>$nhif</b></td></tr>
						<tr><td>Bonus</td><td style='text-align:right'><b>$bonus</b></td><td>PAYE</td><td style='text-align:right'><b>$paye</b></td></tr>
						<tr><td colspan='2'></td><td>$ded1</td><td style='text-align:right'><b>$d1amnt</b></td></tr> $ths
						<tr><td colspan='2'></td><td>Other Deductions</td><td style='text-align:right'><b>$deduct</b></td></tr>
						<tr><td><b>Gross Pay</b></td></td><td style='text-align:right'><b>$grospay</b></td><td><b>Total Deductions</b></td>
						<td style='text-align:right'><b>$tots</b></td></tr><tr><td colspan='4' style='border:1px solid #fff'></td></tr>
						<tr style='background:#f0f0f0;'><td colspan='3'><h3 style='color:#191970'>Net Salary</h3></td>
						<td style='text-align:right'><h3 style='color:#191970'>$netpay</h3></td></tr>
					</table><br>
					<table style='width:100%' cellpadding='7'>
						<tr><td colspan='4'><h3 style='color:#191970'>Ksh $numwords</h3></td></tr>
						<tr><td>Cheque No</td><td style='border-bottom:1px dotted #191970;color:#191970'>$cheque</td>
						<td>Bank Name</td><td style='border-bottom:1px dotted #191970;color:#191970'>$bank, $bran</td></tr>
						<tr><td>Account No</td><td style='border-bottom:1px dotted #191970;color:#191970'>$acc</td>
						<td>Date</td><td style='border-bottom:1px dotted #191970;color:#191970'>".date('F d, Y')."</td></tr>
					</table><br><hr>
					<table style='width:100%;' cellpadding='8'>
						<tr><td style='width:350px'><h4>Employee Signature</td><td><h4>Human Resource Manager</td></tr>
						<tr><td>..............................................................</td><td>$stamp</td></tr>
					</table>
				</div>
			</div>";
			
			$mpdf->WriteHTML($data); $mont = date('F Y',$mon);
			$save = "<h3 style='color:#008fff'>".greet($name).",</h3><p>The attached is your Salary Payslip for the month of $mont</p>";
			$mssg = htmlentities(htmlentities($save,ENT_QUOTES));
			
			if(!is_dir("payslips/")){mkdir("payslips/",0777,true);} 
			$fname = str_replace(" ","_",prepstr($name)).'-'.date('F_Y',$mon).'_Payslip_'.prenum($pid).'.pdf';
			$mpdf->Output("payslips/$fname",'F'); $tym = time()+$pid;
			
			if($sendmail){
				$db->execute(2,"INSERT INTO `mailist$cid` VALUES(NULL,'$email','$mont Salary Payslip','$mssg','/pdf/files/payslips/$fname','0','$tym')");
			}
			else{ $result = "success:$fname"; }
		}
		
		echo $result;
		exit;
	}
	else{
		$mon = trim($_GET['src']);
		$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-P']);
		$mpdf->SetDisplayMode('fullpage');
		$mpdf->mirrorMargins = 1;
		$mpdf->defaultPageNumStyle = '1';
		$mpdf->setHeader();
		$mpdf->AddPage('P');
		$mpdf->SetAuthor($cname);
		$mpdf->SetCreator("Prady MFI System");
		$mpdf->SetTitle(date("F Y",$mon)." Payslip");
		$mpdf->SetWatermarkImage("data:image/$ext;base64,".getphoto($logo));
		$mpdf->showWatermarkImage = true;
		
		$res = $db->query(1,"SELECT *FROM `stamps` WHERE `client`='$cid' AND `month`='$mon'");
		$img = ($res) ? $res[0]['stamp']:"stamp.png";
		$stamp = "<img src='../../docs/img/$img' style='height:80px'>";
			
		$name = $cname; $actp = "Credit Company"; $period = date("F Y",$mon); $date=date("F d,Y"); $empno = "EMP000";
		$bpay=$allow=$benefits=$nssf=$grospay=$taxable=$paye=$nhif=$netpay=$tots="0.00"; $netpay=fnum(9855,2);
		$cheque=90000; $bank="My Bank"; $acc=1234567; $deduct=300;
		
		$f = new NumberFormatter("en",NumberFormatter::SPELLOUT);
		$numwords = ucfirst($f->format(9855))." only";
		
		$data.="<div style='width:100%;height:100%;border:1px solid #191970;font-family:helvetica;'>
			<table cellpadding='5' style='width:100%;font-family:helvetica'><tr>
				<td style='width:380px'><img src='data:image/$ext;base64,".getphoto($logo)."' style='margin:15px;height:100px'></td>
				<td><p style='color:#2f4f4f'>$cname<br>$addr<br>$cont | $cemail</p></td>
			</tr><tr><td colspan='2' style='text-align:center;'><h3 style='color:#3CB371;font-family:courier'>Salary Slip ".prenum(1)."</h3></td></tr>
			</table>
			
			<div style='padding:10px;'>
				<table cellpadding='4' style='width:420px;font-family:courier;color:#2f4f4f'>
					<tr><td><h3>Employee Name:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$name</h3></td></tr>
					<tr><td><h3>Job No:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$empno</h3></td></tr>
					<tr><td><h3>Designation:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$actp</h3></td></tr>
					<tr><td><h3>Period:</h3></td><td style='border-bottom:1px dotted #191970;color:#191970'><h3>$period</h3></td></tr>
				</table><br><br>
				<table style='width:100%;border:1px solid #2f4f4f;border-collapse:collapse' cellpadding='5' cellspacing='0' border='1'>
					<tr style='background:#f0f0f0;'><td colspan='2'><h3 style='color:#191970'>Earnings</h3></td><td colspan='2'><h3 style='color:#191970'>Deductions</h3></td></tr>
					<tr><td>Basic Salary</td><td style='text-align:right'><b>$bpay</b></td><td>NSSF</td><td style='text-align:right'><b>$nssf</b></td></tr>
					<tr><td>Allowance</td><td style='text-align:right'><b>$allow</b></td><td>SHIF</td><td style='text-align:right'><b>$nhif</b></td></tr>
					<tr><td>Non Cash Benefits</td><td style='text-align:right'><b>$benefits</b></td><td>PAYE</td><td style='text-align:right'><b>$paye</b></td></tr>
					<tr><td colspan='2'></td><td>Salary Advance</td><td style='text-align:right'><b>0.00</b></td></tr>
					<tr><td colspan='2'></td><td>Other Deductions</td><td style='text-align:right'><b>$deduct</b></td></tr>
					<tr><td><b>Gross Pay</b></td></td><td style='text-align:right'><b>$grospay</b></td><td><b>Total Deductions</b></td>
					<td style='text-align:right'><b>$tots</b></td></tr><tr><td colspan='4' style='border:1px solid #fff'></td></tr>
					<tr style='background:#f0f0f0;'><td colspan='3'><h3 style='color:#191970'>Net Salary</h3></td>
					<td style='text-align:right'><h3 style='color:#191970'>$netpay</h3></td></tr>
				</table><br>
				<table style='width:100%' cellpadding='7'>
					<tr><td colspan='4'><h3 style='color:#191970'>Ksh $numwords</h3></td></tr>
					<tr><td>Cheque No</td><td style='border-bottom:1px dotted #191970;color:#191970'>$cheque</td>
					<td>Name of Bank</td><td style='border-bottom:1px dotted #191970;color:#191970'>$bank</td></tr>
					<tr><td>Account No</td><td style='border-bottom:1px dotted #191970;color:#191970'>$acc</td>
					<td>Date</td><td style='border-bottom:1px dotted #191970;color:#191970'>$date</td></tr>
				</table><br><hr>
				<table style='width:100%;' cellpadding='8'>
					<tr><td style='width:350px'><h4>Employee Signature</td><td><h4>Human Resource Manager</td></tr>
					<tr><td>..............................................................</td><td>$stamp</td></tr>
				</table>
			</div>
		</div>";
	
		$mpdf->WriteHTML($data);
		$mpdf->Output("Payslip_Template.pdf",'I');
		exit;
	}

?>