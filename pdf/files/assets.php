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
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo'];
	
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
		.trh td{font-weight:bold;color:#fff;font-size:13px;}
	",1);
	
		$cond = ($src==null) ? 1:"`branch`='$ftc'";
		if($src){
			$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
			$bname = prepare(ucwords($qry[0]['branch']));
		}
		else{ $bname = ($src==null) ? "All":"Head Office"; }
	
		$trs=""; $no=$sum=0;
		$res = $db->query(3,"SELECT *FROM `assets$cid` WHERE $cond ORDER BY `item` ASC");
		if($res){
			foreach($res as $row){
				$item=prepare(ucwords($row['item'])); $cat=prepare($row['category']); $desc=prepare(ucfirst($row['details'])); 
				$recur = date('M d',strtotime($row['cycle'])); $cost=number_format($row['cost']); $rid=$row['id']; $no++;
				$dept=prepare(ucfirst($row['office'])); $depr=$row['depreciation']."% every $recur"; $sum+=$row['cost'];
				$trs.="<tr valign='top'><td>$no</td><td>$item</td><td>$cat</td><td>$desc</td><td>$dept</td><td>$cost</td><td>$depr</td></tr>";
			}
		}
		
		$title = "$bname Company Assets";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `id`='$by'");
		$sname = prepare(ucwords($res[0]['name']));
		
		$data = "<p style='text-align:center'><img src='data:image/".explode(".",$logo)[1].";base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title (".number_format($sum).")</h3>
		<h4 style='color:#2f4f4f;margin-bottom:5px'>Printed on ".date('M d,Y - h:i a')." by $sname</h4>
		<table class='tbl' style='width:100%;font-size:14px' cellpadding='5' cellspacing='0'>
			<tr style='background:#4682b4;' class='trh'><td colspan='2'>Item</td>
			<td>Category</td><td>Description</td><td>Department/office</td><td>Cost</td><td>Depreciation</td></tr> $trs
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