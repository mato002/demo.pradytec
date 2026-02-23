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
	$mon = trim($_GET['src']);
	$week = trim($_GET['wk']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	
	$bname = ($bran==null) ? "Corporate":"Head Office";
	if($bran){
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
		$bname = prepare(ucwords($sql[0]['branch']))." branch";
	}
	
	$title = $bname." Petty Cashbook for ".date("F Y",$mon); $tmon=strtotime(date("Y-M"));
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
	$mpdf->SetAuthor("PradyTech");
	$mpdf->SetCreator("Prady MFI System");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.cashtbl{ border: 1px solid grey; border-collapse: collapse; width:100%; font-size:14px;background:#F8F8FF; }
		.trh td{ font-weight:bold;border:1px solid #9370DB;padding:5px;color:#fff;font-size:15px; }
		.cashtbl td { border-left: 1px solid grey; padding:2px 7px; font-size:13px; }
		.ltr td{color:#191970;border-top:1px solid grey;}
	",1);
		
		$show = $cond = ($bran==null) ? "":"AND branch='$bran'";
		$cond.= ($week) ? " AND `week`='$week'":"";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$res = $db->query(3,"SELECT SUM(opening) AS opened FROM `cashfloats$cid` WHERE `month`='$mon' $show");
		$obal = ($res) ? $res[0]['opened']:0; $brans=""; $all=1; $totals=0;
		
		if($week){
			$res1 = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `cashbook$cid` WHERE `month`='$mon' AND `week`<$week AND `type`='debit' $show");
			$res2 = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `cashbook$cid` WHERE `month`='$mon' AND `week`<$week AND `type`='credit' $show");
			$added = ($res1) ? $res1[0]['tamnt']:0; $spent = ($res2) ? $res2[0]['tamnt']:0; $tfloat=($obal+$added)-$spent;
			
			$day = date("d-m-Y",weekrange($week,date("Y",$mon))[0]); $otx=($tfloat>=0) ? "Balance B/D":"Balance C/F";
			$trs = "<tr valign='top'><td>".str_replace("-","",number_format($tfloat))."</td><td>$day</td><td>$otx</td><td></td><td></td><td></td></tr>";
			$mttl = "From $day to ".date("d-m-Y",weekrange($week,date("Y",$mon))[1]);
		}
		else{
			$day = date("d-m-Y",$mon); $otx=($obal>=0) ? "Balance B/D":"Balance C/F"; $tfloat=$obal;
			$trs = "<tr valign='top'><td>".str_replace("-","",number_format($obal))."</td><td>$day</td><td>$otx</td><td></td><td></td><td></td></tr>";
			$last = date("d-m-Y",monrange(date("m",$mon),date("Y",$mon))[1]);
			$mttl = ($mon==$tmon) ? "For ".date("F Y",$mon)." Ending $last":"For ".date("F Y",$mon)." Ended $last";
		}
		
		# floats
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `type`='debit' AND `month`='$mon' $cond ORDER BY `time` ASC");
		if($res){
			foreach($res as $row){
				$amnt=number_format($row['amount']); $day=date("d-m-Y",$row['time']); $trans=prepare(ucfirst($row['transaction']));
				$sno=prenum($row['id']); $usa=$staff[$row['cashier']]; $tfloat+=$row['amount']; $all++;
				$trs.= "<tr valign='top'><td>$amnt</td><td>$day</td><td>$trans</td><td>$sno</td><td style='text-align:right'>0</td><td>$usa</td></tr>";
			}
		}
		
		# expenditure
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `type`='credit' AND `month`='$mon' $cond ORDER BY `time` ASC");
		if($res){
			foreach($res as $row){
				$amnt=number_format($row['amount']); $day=date("d-m-Y",$row['time']); $trans=prepare(ucfirst($row['transaction']));
				$sno=prenum($row['id']); $usa=$staff[$row['cashier']]; $rid=$row['id']; $totals+=$row['amount']; $all++;
				
				$trs.= "<tr valign='top'><td style='text-align:right'>$amnt</td><td>$day</td><td>$trans</td><td>$sno</td>
				<td style='text-align:right'>$amnt</td><td>$usa</td></tr>";
			}
		}
		
		$rem=($all>14) ? 0:14-$all; $ramnt=$tfloat-$totals;
		$cf = ($ramnt<0) ? str_replace("-","",number_format($ramnt)):0; $bd = ($ramnt>=0) ? number_format($ramnt):0; 
		for($i=1; $i<=$rem; $i++){ $trs.="<tr><td><span style='color:#F8F8FF'>.</span></td><td></td><td></td><td></td><td></td><td></td></tr>"; }
		
		$trs.="<tr><td style='border:1px solid grey'><b>".number_format($tfloat)."</b></td>
		<td colspan='4' style='text-align:right;padding:4px 7px;border:1px solid grey;'><b>".number_format($totals)."</b></td><td></td></tr>
		<tr style='color:#191970;'><td style='padding:2px 7px;font-weight:bold;border-top:1px solid grey'>$cf</td><td>Balance C/F</td><td colspan='3'></td></tr>
		<tr style='color:#191970;'><td style='padding:4px 7px;font-weight:bold'><b>$bd</b></td><td>Balance B/D</td><td colspan='4'></td></tr>";
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='font-size:20px;text-align:center;color:#191970;font-family:courier'><u>$bname Petty Cashbook</u></h3>
		<h3 style='color:#191970;text-align:center;margin:0px'>$mttl</h3>
		<h4 style='color:#2f4f4f;margin:0px'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table class='cashtbl'>
			<tr class='trh' style='background:#9370DB;'><td style='width:130px'>Receipts</td><td style='width:95px'>Date</td><td>Details</td>
			<td style='width:60px'>SN</td><td style='width:90px'>Totals</td><td>Cashier</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf',"I");
	exit;

?>