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
	$ftp = trim($_GET['src']);
	$ofid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
		$db = new DBO(); $cid = CLIENT_ID;
		$tdy = strtotime(date("Y-M-d")); 
		$jana = strtotime(date("Y-M-d",$tdy-5));
		$nxtd = $tdy+(86400*30); $lstd = $jana-(86400*30);
		$ltbl = "org$cid"."_loans"; $ctbl = "org$cid"."_clients";
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
		
		$me = staffInfo($by); $access = $me['access_level'];
		$bran = ($me['access_level']!="hq") ? $me['branch']:$bran;
		$ofid = ($me['access_level']=="portfolio") ? $by:$ofid;
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		
		$range = array(0=>[30,0],2=>[60,30],3=>[90,60],4=>[120,90],5=>[1000,120]);
		$fday = $jana-($range[$ftp][0]*86400); $dto=$jana-($range[$ftp][1]*86400);
	
		$cond = $fetch = ($ftp==1) ? "ln.expiry BETWEEN $tdy AND $nxtd AND ln.balance>0 AND ct.status='1'":"ln.expiry BETWEEN $fday AND $dto AND ct.status='0'";
		$cond.= ($bran) ? " AND ln.branch='$bran'":"";
		$cond.= ($ofid) ? " AND ln.loan_officer='$ofid'":"";
		$order = ($ftp==1) ? "ASC":"DESC";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staffs[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$qrs = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($qrs){
			foreach($qrs as $row){
				$prods[$row['id']]=prepare(ucwords($row['product']));
			}
		}
		
		$no=0; $trs=$brans=$offs="";
		if($db->istable(2,$ltbl)){
			$qri = $db->query(2,"SELECT ln.* FROM `$ltbl` AS ln INNER JOIN `$ctbl` AS ct ON ln.client_idno=ct.idno WHERE $cond ORDER BY ln.expiry $order");
			if($qri){
				foreach($qri as $row){
					$name=prepare(ucwords($row['client'])); $prod=$prods[$row['loan_product']]; $fon=$row['phone']; $bname=$bnames[$row['branch']]; $no++;
					$ofname=$staffs[$row['loan_officer']]; $amnt=number_format($row['amount']); $dur=$row['duration']; $exp=$row['expiry']; 
					$bal=number_format($row['balance']); $dy=date("d-m-Y",$exp); $dys=($ftp==1) ? floor(($exp-$tdy)/86400):floor(($jana-$exp)/86400);
					
					$tdx = ($dys==1) ? "1 day":"$dys days"; $tdx.=($ftp==1) ? " to":" ago"; $tbal=($ftp==1) ? $bal:"Completed";
					$oftd = ($access=="portfolio") ? "":"<td>$ofname<br><span style='color:grey;font-size:14px'>$bname branch</span></td>";
					
					$trs.="<tr valign='top'><td>$no</td><td>$name<br><span style='color:grey;font-size:14px'>0$fon</span></td><td>$amnt</td>
					<td>$prod<br><span style='color:grey;font-size:14px'>$dur days</span></td>$oftd <td>$dy<br><span style='color:grey;font-size:14px'>$tdx</span></td>
					<td>$tbal</td></tr>";
				}
			}
		}
		
		$def = array("0-30 Days","Next 30 Days","31-60 Days","61-90 Days","91-120 Days","Above 120 Days");
		$title = ($ftp==1) ? $def[$ftp]." to Dormant clients":$def[$ftp]." dormant clients"; 
		$title.=($bran) ? " from ".$bnames[$bran]:"";
		$tdh = ($access=="portfolio") ? "":"<td>Loan Officer</td>";
	
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
			.tbl td{font-size:13px;}
			.tbl tr:nth-child(odd){background:#f0f0f0;}
			.trh td{font-weight:bold;color:#191970;font-size:13px;}
		",1);
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staffs[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Loan</td><td>Product</td>$tdh<td>Maturity</td><td>Balance</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>