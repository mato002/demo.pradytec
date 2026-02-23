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
		.tbl td{font-size:13px;text-align:right;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;font-size:13px;color:#fff}
		.trl td{font-weight:bold;font-size:13px;color:#191970}
	",1);
		
		if($db->istable(3,"bonuses$cid")){
			$qri = $db->query(3,"SELECT * FROM `bonuses$cid` WHERE `month`='$mon' AND `status`='200'");
			if($qri){
				foreach($qri as $row){ $bonus[$row['staff']]=$row['bonus']; }
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` ORDER BY `name` ASC");
		foreach($res as $row){
			$names[$row['id']]=array("status"=>$row['status'],"name"=>prepare(ucwords($row['name'])),"idno"=>$row['idno']);
		}
		
		$thb=$nta=$pns=0; $dcuts=[];
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$thb+=$row["helb"]; $nta+=$row["nita"]; $pns+=$row["pension"]; $dct=json_decode($row["cuts"],1);
				foreach($dct as $dc=>$am){
					if(isset($dcuts[$dc])){ $dcuts[$dc]+=$am; }else{ $dcuts[$dc]=$am; }
				}
			}
		}
		
		$trs=$prnt=$gen=$ths=""; $no=$tot1=$tot2=$tot3=$tot4=$tot5=$tot6=$tot7=$tot8=$tot9=$tot10=$tota=$totb=$totc=$tot11=$kn=0; $dcts=[];
		$qri = $db->query(3,"SELECT *FROM `payroll$cid` WHERE `month`='$mon'");
		if($qri){
			foreach($qri as $row){
				$bpay=fnum($row['basicpay']); $allow=fnum($row['allowance']); $benefits=fnum($row['benefits']); 
				$nssf=fnum($row['nssf']); $grospay=fnum($row['grosspay']); $taxable=fnum($row['taxablepay']); 
				$paye=fnum($row['paye']);  $rid=$row['id']; $nhif=fnum($row['nhif']); $deduct=fnum($row['deductions']); 
				$netpay=fnum($row['netpay']); $stid=$row['staff']; $name=$names[$stid]['name']; 
				$cuts = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $kn++;
				foreach($dcuts as $one=>$tx){ if(!isset($cuts[$one])){ $cuts[$one]=0; }}
				$bnus=(isset($bonus[$stid])) ? $bonus[$stid]:0; $tot11+=$bnus;
				
				$tot1+=intval($row['basicpay']); $tot2+=intval($row['allowance']); $tot3+=intval($row['benefits']); 
				$tot4+=intval($row['grosspay']); $tot5+=intval($row['taxablepay']); $tota+=intval($row['helb']);
				$tot6+=intval($row['paye']); $tot7+=intval($row['nssf']); $tot8+=intval($row['nhif']); 
				$tot10+=intval($row['netpay']); $totb+=intval($row['nita']); $totc+=intval($row['pension']);
				$tds = ($thb) ? "<td>".fnum($row['helb'])."</td>":""; $tds.=($nta) ? "<td>".fnum($row['nita'])."</td>":""; 
				$tds.= ($pns) ? "<td>".fnum($row['pension'])."</td>":""; $tot9+=intval($row['deductions']); 
				foreach($cuts as $cut=>$sum){ $tds.="<td>".fnum($sum)."</td>"; }
				
				$trs.="<tr style='text-align:right'><td style='text-align:left'>$kn. $name</td><td>$bpay</td><td>$allow</td>
				<td>$benefits</td><td>$bnus</td><td>$grospay</td><td>$taxable</td><td>$paye</td><td>$nssf</td><td>$nhif</td>$tds<td>$deduct</td><td>$netpay</td></tr>";
			}
			
			$ths = ($thb) ? "<td>HELB</td>":""; $ths.=($nta) ? "<td>NITA</td>":""; $ths.=($pns) ? "<td>Pension</td>":"";
			$tht = ($thb) ? "<td>".fnum($tota)."</td>":""; $tht.=($nta) ? "<td>".fnum($totb)."</td>":""; $tht.=($pns) ? "<td>".fnum($totc)."</td>":"";
			foreach($dcuts as $dct=>$txo){ $ths.="<td>".prepare(ucfirst($dct))."</td>"; $tht.="<td>".fnum($txo)."</td>"; }
		
			$trs.= "<tr class='trl' style='text-align:right;cursor:default;background:linear-gradient(to bottom,#E6E6FA,#f0f0f0,#fff);'>
			<td>Totals</td><td>".fnum($tot1)."</td><td>".fnum($tot2)."</td><td>".fnum($tot3)."</td><td>".fnum($tot11)."</td><td>".fnum($tot4)."</td>
			<td>".fnum($tot5)."</td><td>".fnum($tot6)."</td><td>".fnum($tot7)."</td><td>".fnum($tot8)."</td>$tht<td>".fnum($tot9)."</td><td>".fnum($tot10)."</td></tr>";
		}
		
		$title = date("F Y",$mon)." Payroll";
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='80'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f;margin-bottom:5px'>Printed on ".date('M d,Y - h:i a')." by ".$names[$by]['name']."</h4>
		<table class='tbl' style='width:100%;font-size:14px' cellpadding='5' cellspacing='0'>
			<tr style='background:#4682b4;' class='trh'><td style='text-align:left'>Staff</td><td>Basic Pay</td><td>Allowances</td><td>Benefits</td><td>Bonus</td>
			<td>Gross Pay</td><td>Taxable Pay</td><td>PAYE</td><td>NSSF</td><td>SHIF</td>$ths<td>Deductions</td><td>Net Pay</td></tr> $trs
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