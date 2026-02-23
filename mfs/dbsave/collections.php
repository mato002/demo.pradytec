<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
	$type = trim($_GET['v']);
	$day = trim($_GET['dy']);
	$src = trim($_GET['src']);
	
	$db = new DBO(); $cid=CLIENT_ID; $by=$sid;
	$tp = ($type=="rep") ? "Report":"Sheet";
	$rtl = ($type=="rep") ? date("d-m-Y",explode("-",$day)[0])." to ".date("d-m-Y",explode("-",$day)[1])." Collection Report":
	"Collection Sheet for ".date("d-m-Y",explode(":",$day)[0]);
	
	$title = ($type=="rates") ? "Collection Rates for ".date("F-Y",$day):$rtl;
	$title = ($type=="dmtd") ? "Disbursements from ".date("d-m-Y",explode(":",$day)[0])." to ".date("d-m-Y",explode(":",$day)[1]):$title;
	$port = ($type=="rates" or $type=="dmtd") ? "L":"P";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>"A4-$port"]);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage($port);
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;font-size:14px}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$stbl = "org".$cid."_schedule"; $ltbl = "org".$cid."_loans";
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1]; $bnames=[]; $trs=$ths="";
	
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
	
	if($type=="rep"){
		$total=$paid=$no=$bals=0; $list=[]; $fro=explode("-",$day)[0]; $dto=explode("-",$day)[1]; $tdy=strtotime(date("Y-M-d"));
		$res = $db->query(2,"SELECT sd.officer,sd.amount,sd.paid,sd.loan,sd.balance,ln.client,ln.phone,ln.branch,ln.client_idno,sd.day FROM `$stbl` AS sd 
		INNER JOIN $ltbl AS ln ON ln.loan=sd.loan WHERE sd.day BETWEEN $fro AND $dto AND (ln.balance>0 or ln.status>$fro) $cond ORDER BY ln.client,sd.balance ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $ofname=$staff[$row['officer']]; $fon=$row['phone']; $amnt=$row['amount']; $bal=$row['balance'];
				$pay=$row['paid']; $idno=$row['client_idno']; $bname=$bnames[$row['branch']]; $total+=$amnt; $paid+=$pay; $bals+=$bal;
				
				if(isset($list[$idno])){ $list[$idno]['sum']+=$amnt; $list[$idno]["paid"]+=$pay; $list[$idno]["bal"]+=$bal; }
				else{ $list[$idno] = array("day"=>$row['day'],"name"=>$name,"phone"=>"0$fon","portf"=>$ofname,"bran"=>$bname,"sum"=>$amnt,"paid"=>$pay,"bal"=>$bal); }
			}
			
			foreach($list as $one){
				$name=$one["name"]; $fon=$one["phone"]; $port=$one["portf"]; $sum=number_format($one["sum"]); $pay=number_format($one["paid"]); $no++; 
				$trs.= "<tr><td>$no</td><td>$name</td><td>$fon</td><td>$port</td><td>".$one["bran"]."</td><td>$sum</td><td>$pay</td><td>".number_format($one["bal"])."</td></tr>";
			}
			
			$trs.= "<tr style='font-weight:bold'><td colspan='5'></td><td><b>".number_format($total)."</b></td><td><b>".number_format($paid)."</b></td>
			<td><b>".number_format($bals)."</b></td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'>
			<td colspan='2'>Client</td><td>Contact</td><td>Portfolio</td><td>Branch</td><td>Collection</td><td>Paid</td><td>Balance</td></tr> $trs
		</table>";
	}
	elseif($type=="rates"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		
		$ttl = ucwords(str_replace("_"," ",$cfield)); $mon=$day;
		$monrange = monrange(date('m',$mon),date("Y",$mon)); $dto=$monrange[1]+86399;
		$trs=$brans=$mns=""; $tln=$tpf=$totc=$toc=$tdd8=$tarr=$tgc=$tcg7=0;
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `disbursement` BETWEEN $mon AND $dto $cond");
		if($res){
			$fork = new DBAsync();
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d")); $cond2=($cond) ? str_replace("AND ","AND ln.",$cond):"";
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum FROM `$ltbl` WHERE `$cfield`='$def' AND `disbursement` BETWEEN $mon AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
				BETWEEN $mon AND $dto AND sd.day<$today AND $cfield='$def' $cond");
				$fork->add(2,"SELECT SUM(pr.amount) AS tsum,SUM((CASE WHEN (pr.day<=ln.expiry) THEN pr.amount ELSE 0 END)) AS otc,SUM((CASE WHEN (pr.day BETWEEN ln.expiry+(1) 
				AND ln.expiry+(604800)) THEN pr.amount ELSE 0 END)) AS dd7,SUM((CASE WHEN (pr.day>ln.expiry+(604800)) THEN pr.amount ELSE 0 END)) AS cg7 FROM `$ltbl` AS ln STRAIGHT_JOIN 
				`processed_payments$cid` AS pr ON pr.linked=ln.loan WHERE ln.$cfield='$def' AND ln.disbursement BETWEEN $mon AND $dto AND NOT pr.payment='Penalties' $cond2");
				
				$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; 
				$data[] = array("name"=>$name,"tln"=>$sq1[0]['tln'],"tpv"=>$sq1[0]['tsum'],"arr"=>$sq2[0]['tarr']);
			}
			
			$fres = $fork->run();
			foreach($fres as $key=>$one){
				$pv=$data[$key]["tln"]; $tpv=$data[$key]["tpv"]; $arr=$data[$key]["arr"]; $name=$data[$key]["name"]; $otc=$one[0]['otc']; $dd8=$one[0]['dd7']; $oc=$otc+$dd8;
				$tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $totc+=$otc; $toc+=$oc; $tdd8+=$dd8; $gc=$one[0]['tsum']; $cg7=$one[0]['cg7']; $tcg7+=$cg7; $tgc+=$gc;
				$otp=round($otc/$tpv*100,2); $ocp=round($oc/$tpv*100,2); $gcp=round($gc/$tpv*100,2);
				$trs.= "<tr><td style='float:left'>$name</td><td>".number_format($pv)."</td><td>".number_format($tpv)."</td><td>".number_format($otc)."</td>
				<td>".number_format($oc)."</td><td>".number_format($dd8)."</td><td>".number_format($cg7)."</td><td>".number_format($arr)."</td><td>$otp%</td>
				<td>$ocp%</td><td>$gcp%</td></tr>";
			}
			
			$totp=round($totc/$tpf*100,2); $tocp=round($toc/$tpf*100,2); $tgcp=round($tgc/$tpf*100,2);
			$trs.= "<tr style='color:#191970;background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff' class='trh'>
			<td>Totals</td><td>".number_format($tln)."</td><td>".number_format($tpf)."</td><td>".number_format($totc)."</td><td>".number_format($toc)."</td>
			<td>".number_format($tdd8)."</td><td>".number_format($tcg7)."</td><td>".number_format($tarr)."</td><td>$totp%</td><td>$tocp%</td><td>$tgcp%</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='float:left'>$ttl</td><td>Disbursed Loan</td><td>Loan+Charges</td><td>OTC</td><td>OC</td>
			<td>DD7</td><td>CG7</td><td>Arrears</td><td>OTC%</td><td>OC%</td><td>GC%</td></tr> $trs
		</table>";
	}
	elseif($type=="dmtd"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		
		$ttl = ucwords(str_replace("_"," ",$cfield));
		$dfro = explode(":",$day)[0]; $dto = explode(":",$day)[1];
		$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=0;
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `disbursement` BETWEEN $dfro AND $dto $cond");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $tdy=strtotime(date("Y-M-d"));
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd FROM `$ltbl` WHERE `$cfield`='$def' AND `disbursement` BETWEEN $dfro AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
				BETWEEN $dfro AND $dto AND sd.day<$tdy AND $cfield='$def' $cond"); 
				
				$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd;
				$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; $gc=round($tpd/$tpv*100,2); 
				$trs.= "<tr><td style='float:left'>$name</td><td>".number_format($pv)."</td><td>".number_format($tpv)."</td><td>".number_format($tpd)."</td>
				<td>".number_format($arr)."</td><td>$gc%</td></tr>";
			}
			
			$trs.= "<tr style='background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);' class='trh'>
			<td>Totals</td><td>".number_format($tln)."</td><td>".number_format($tpf)."</td><td>".number_format($tpds)."</td><td>".number_format($tarr)."</td>
			<td>".round($tpds/$tpf*100,2)."%</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='float:left'>$ttl</td><td>Disbursed Loan</td><td>Loan+Charges</td><td>Paid</td>
			<td>Arrears</td><td>GC%</td></tr> $trs
		</table>";
	}
	else{
		$tdy=strtotime("Today"); $total=$paid=$no=$tcum=0; $trs="";
		$res = $db->query(2,"SELECT sd.officer,sd.amount,sd.paid,sd.loan,sd.day,ln.client,ln.phone,ln.branch FROM `$stbl` AS sd 
		INNER JOIN $ltbl AS ln ON ln.loan=sd.loan WHERE sd.day='$day' AND sd.balance>0 $cond ORDER BY ln.client,sd.balance ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $ofname=$staff[$row['officer']]; $amnt=fnum($row['amount']); $fon=$row['phone'];
				$pay=fnum($row['paid']); $bname=$bnames[$row['branch']]; $total+=$row['amount']; $paid+=$row['paid']; $lid=$row['loan']; $no++;
				$qri = $db->query(2,"SELECT SUM(balance) AS tsm FROM `$stbl` WHERE `loan`='$lid' AND `day`<$tdy");
				$acum = ($qri) ? intval($qri[0]['tsm']):0; $tcum+=$acum; $inst=getInstall($lid,$row['day']); 
				$trs.= "<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$ofname</td><td>$bname</td><td>$inst</td><td>$amnt</td><td>".fnum($acum)."</td><td>$pay</td></tr>";
			}
			$trs.="<tr style='color:#191970'><td colspan='6'></td><td><b>".fnum($total)."</b></td><td><b>".fnum($tcum)."</b></td><td><b>".fnum($paid)."</b></td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Contact</td><td>Portfolio</td><td>Branch</td><td>Installment</td><td>Amount</td>
			<td>Accumulated</td><td>Paid</td></tr> $trs
		</table>";
	}
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>