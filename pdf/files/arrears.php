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
	$ftc = explode(":",trim($_GET['ftc']));
	$src = trim($_GET['src']);
	$vtp = trim($_GET['v']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$from = $ftc[0]; $to=$ftc[1];
	$to = ($from>$to) ? $from:$to;
	$tarr = array("","Overdue","Running");
	
	$bnames = array(0=>"Corporate");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$add = ($stid) ? $staff[$stid]:$bnames[$bran];
	$title = "$add ".$tarr[$vtp]." Loan Arrears from ".date("M-d-Y",$from)." to ".date("M-d-Y",$to);
	$tdy = strtotime(date("Y-M-d"));
	
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
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		$info = mficlient(); $logo=$info['logo']; 
	
		$qri = $db->query(2,"SELECT * FROM `org".$cid."_clients`");
		if($qri){
			foreach($qri as $row){ $cycles[$row['idno']]=$row['cycles']; }
		}
		
		$no=0; $trs=""; $total=$all=$sum=0;
		$res = $db->query(2,"SELECT ln.branch,sd.idno,sd.officer,MIN(sd.day) AS dy,sd.loan,ln.balance,ln.client,ln.phone,ln.amount AS tloan,ln.expiry,SUM(sd.balance) AS tbal,ln.disbursement 
		FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan WHERE sd.balance>0 AND sd.day BETWEEN '$from' AND '$to' $cond GROUP BY sd.loan ORDER BY sd.day,sd.balance DESC");
		if($res){
			foreach($res as $row){
				$idno=$row['idno']; $ofid=$row['officer']; $day=$row['dy']; $tbal=$row['balance']; $bal=fnum($row['tbal']);
				$client=prepare(ucwords($row['client'])); $fon=$row['phone']; $loan=fnum($row['tloan']); $lid=$row['loan'];
				$qri = $db->query(2,"SELECT SUM(balance) AS tbal FROM `$stbl` WHERE `loan`='$lid' AND `day`<$tdy");
				$arrs = $qri[0]['tbal']; $inst=getInstall($lid,$day); $sum+=$arrs; $total+=$row['tbal']; $all+=$row['balance']; $no++;
				$tdys = floor((strtotime(date("Y-M-d"))-$day)/86400); $cycle=$cycles[$idno]; $dsd=date("d-m-Y",$row["disbursement"]);
				$tad = (!$bran) ? "<td>".$bnames[$row['branch']]."</td>":""; $tad.=(!$stid) ? "<td>$staff[$ofid]</td>":"";
				
				$trs.= "<tr><td>$no</td><td>$client</td><td>0$fon</td>$tad<td>$loan</td><td>$dsd</td><td>$cycle</td><td>$bal</td><td>".fnum($arrs)."</td>
				<td>$inst</td><td>".date("d-m-Y",$day)."</td><td>$tdys</td><td>".fnum($tbal)."</td></tr>";
			}
			
			$csp=6; $csp+=(!$bran) ? 1:0; $csp+=(!$stid) ? 1:0; 
			$trs.= "<tr class='trh'><td colspan='$csp' style='text-align:center'>Totals</td>
			<td>".fnum($total)."</td><td>".fnum($sum)."</td><td colspan='3'></td><td style='text-align:right'>".fnum($all)."</td></tr>";
		}
		
		$tadd = (!$bran) ? "<td>Branch</td>":""; $tadd.=(!$stid) ? "<td>Loan Officer</td>":"";
		$data = "<p style='text-align:center'><img src='data:image/".explode(".",$logo)[1].";base64,".getphoto($logo)."' height='80'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3><h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Contact</td>$tadd
			<td>Loan</td><td>Disbursement</td><td>Cycles</td><td>P.Arrears</td><td>Accumulated</td><td>Installment</td><td>Fall Date</td><td>Days</td><td>T.Bal</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",trim($title)).'.pdf','I');
	exit;

?>