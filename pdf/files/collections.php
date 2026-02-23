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
	$title = ($type=="apprep") ? "Mobile App Disbursements from ".date("d-m-Y",explode(":",$day)[0])." to ".date("d-m-Y",explode(":",$day)[1]):$title;
	$port = (in_array($type,["rates","dmtd","apprep"])) ? "L":"P";
	
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
		$total=$paid=$no=$bals=$tcum=0; $list=$lns=$arrs=$lids=[]; $fro=explode("-",$day)[0]; $dto=explode("-",$day)[1]; $tdy=strtotime(date("Y-M-d"));
		$qri = $db->query(2,"SELECT day,loan,SUM(amount) AS tsum,SUM(paid) AS tpay,SUM(balance) AS tbal FROM `$stbl` WHERE `day` BETWEEN $fro AND $dto $cond GROUP BY `loan`");
		if($qri){
			foreach($qri as $row){ $lid = $row["loan"]; $lns[]="'$lid'"; $list[$lid]=$row; }
		}
		
		if($lns){
			$sql = $db->query(2,"SELECT SUM(balance) AS tbal,`loan` FROM `$stbl` WHERE `loan` IN (".implode(",",$lns).") AND `day`<$tdy AND `balance`>0 GROUP BY `loan`");
			if($sql){
				foreach($sql as $row){ $arrs[$row["loan"]]=intval($row["tbal"]); }
			}
		
			$qry = $db->query(2,"SELECT `loan`,`client`,`phone`,`branch`,`client_idno`,`loan_officer` FROM `$ltbl` WHERE `loan` IN (".implode(",",$lns).")
			AND (balance>0 OR status>$fro) ORDER BY `client` ASC");
			foreach($qry as $rw){
				$lid = $rw["loan"]; $row=$list[$lid]; $name=prepare(ucwords($rw['client'])); $ofname=$staff[$rw['loan_officer']]; $fon=$rw['phone']; 
				$pay=$row["tpay"]; $amnt=$row["tsum"]; $bal=$row["tbal"]; $dy=$row["day"]; $bname=$bnames[$rw['branch']]; $total+=$amnt; $paid+=$pay; $bals+=$bal;
				$acum=(isset($arrs[$lid])) ? $arrs[$lid]:0; $tcum+=$acum; $no++;
				$trs.= "<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$ofname</td><td>$bname</td><td>".fnum($amnt)."</td>
				<td>".fnum($acum)."</td><td>".fnum($pay)."</td><td>".fnum($bal)."</td></tr>";
			}
			$trs.= "<tr style='background:#e6e6fa' class='trh'><td colspan='5'></td><td>".fnum($total)."</td><td>".fnum($tcum)."</td><td>".fnum($paid)."</td><td>".fnum($bals)."</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'>
			<td colspan='2'>Client</td><td>Contact</td><td>Portfolio</td><td>Branch</td><td>Collection</td><td>Arrears</td><td>Paid</td><td>Balance</td></tr> $trs
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
				$trs.= "<tr><td style='float:left'>$name</td><td>".fnum($pv)."</td><td>".fnum($tpv)."</td><td>".fnum($otc)."</td>
				<td>".fnum($oc)."</td><td>".fnum($dd8)."</td><td>".fnum($cg7)."</td><td>".fnum($arr)."</td><td>$otp%</td>
				<td>$ocp%</td><td>$gcp%</td></tr>";
			}
			
			$totp=round($totc/$tpf*100,2); $tocp=round($toc/$tpf*100,2); $tgcp=round($tgc/$tpf*100,2);
			$trs.= "<tr style='color:#191970;background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #fff' class='trh'>
			<td>Totals</td><td>".fnum($tln)."</td><td>".fnum($tpf)."</td><td>".fnum($totc)."</td><td>".fnum($toc)."</td>
			<td>".fnum($tdd8)."</td><td>".fnum($tcg7)."</td><td>".fnum($tarr)."</td><td>$totp%</td><td>$tocp%</td><td>$tgcp%</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='float:left'>$ttl</td><td>Disbursed Loan</td><td>Loan+Charges</td><td>OTC</td><td>OC</td>
			<td>DD7</td><td>CG7</td><td>Arrears</td><td>OTC%</td><td>OC%</td><td>GC%</td></tr> $trs
		</table>";
	}
	elseif($type=="apprep"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		$lis = explode(":",trim($_GET["lis"]));
		
		$ttl = ucwords(str_replace("_"," ",$cfield));
		$dfro = explode(":",$day)[0]; $dto = explode(":",$day)[1];
		$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=$tots=$ttag=0;
		
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		foreach($qri as $row){
			$id=$row['id']; $pdef[]="$ltbl.loan_product='$id'";
		}
		
		$fetch=$cond; $cond.= "AND (".implode(" OR ",$pdef).")"; $mfr=monrange(date("m",$dfro),date("Y",$dfro))[0];
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE 1 $fetch");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $tdy=strtotime(date("Y-M-d")); $ccol=str_replace("loan_officer","officer",$cfield);
				if(in_array($def,$lis)){
					$qri = $db->query(2,"SELECT SUM(apploans) AS ttag FROM `org$cid"."_targets` WHERE `month` BETWEEN $mfr AND $dto AND $ccol='$def'");
					$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' AND 
					`disbursement` BETWEEN $dfro AND $dto $cond");
					$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
					BETWEEN $dfro AND $dto AND sd.day<$tdy AND $cfield='$def' $cond"); 
					
					$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tot=$sq1[0]['tot']; 
					$tag=($qri) ? intval($qri[0]['ttag']):0; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd; $tots+=$tot; $ttag+=$tag;
					$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; $gc=($tpv) ? round($tpd/$tpv*100,2):0;
					$trs.= "<tr><td style='float:left'>$name</td><td>".fnum($tag)."</td><td>".fnum($pv)."</td><td>".fnum($tot)."</td><td>".fnum($tpv)."</td><td>".fnum($tpd)."</td>
					<td>".fnum($arr)."</td><td>$gc%</td></tr>";
				}
			}
			
			$tpc = ($tpf) ? round($tpds/$tpf*100,2):0;
			$trs.= "<tr style='color:#191970;background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);border-top:2px solid #ccc' class='trh'>
			<td>Totals</td><td>".fnum($ttag)."</td><td>".fnum($tln)."</td><td>".fnum($tots)."</td><td>".fnum($tpf)."</td><td>".fnum($tpds)."</td><td>".fnum($tarr)."</td><td>$tpc%</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='float:left'>$ttl</td><td>Target</td><td>Disbursement</td><td>Total Loans</td><td>Collections</td>
			<td>Paid</td><td>Arrears</td><td>GC%</td></tr> $trs
		</table>";
	}
	elseif($type=="dmtd"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		
		$ttl = ucwords(str_replace("_"," ",$cfield));
		$dfro = explode(":",$day)[0]; $dto = explode(":",$day)[1];
		$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=$tots=0;
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `disbursement` BETWEEN $dfro AND $dto $cond");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $tdy=strtotime(date("Y-M-d"));
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' AND `disbursement` BETWEEN $dfro AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
				BETWEEN $dfro AND $dto AND sd.day<$tdy AND $cfield='$def' $cond"); 
				
				$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd;
				$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; $gc=round($tpd/$tpv*100,2); $tot=$sq1[0]['tot']; $tots+=$tot;
				$trs.= "<tr><td style='float:left'>$name</td><td>".fnum($pv)."</td><td>".fnum($tot)."</td><td>".fnum($tpv)."</td><td>".fnum($tpd)."</td>
				<td>".fnum($arr)."</td><td>$gc%</td></tr>";
			}
			
			$trs.= "<tr style='background:linear-gradient(to bottom,#dcdcdc,#f0f0f0,#f8f8f0,#fff);' class='trh'>
			<td>Totals</td><td>".fnum($tln)."</td><td>".fnum($tots)."</td><td>".fnum($tpf)."</td><td>".fnum($tpds)."</td><td>".fnum($tarr)."</td>
			<td>".round($tpds/$tpf*100,2)."%</td></tr>";
		}
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;font-size:13px' class='trh'><td style='float:left'>$ttl</td><td>Disbursed Amount</td><td>Total Loans</td><td>Loan+Charges</td><td>Paid</td>
			<td>Arrears</td><td>GC%</td></tr> $trs
		</table>";
	}
	else{
		$tdy=strtotime("Today"); $total=$paid=$no=$tcum=0; $trs=""; $lns=$list=$arrs=$insts=[];
		$qri = $db->query(2,"SELECT day,loan,paid,amount FROM `$stbl` WHERE `day`='$day' AND `balance`>0 $cond GROUP BY `loan`");
		if($qri){
			foreach($qri as $row){ $lid = $row["loan"]; $lns[]="'$lid'"; $list[$lid]=$row; }
		}
		
		if($lns){
			$res = $db->query(2,"SELECT GROUP_CONCAT(day) AS days,`loan` FROM `$stbl` WHERE `loan` IN (".implode(",",$lns).") GROUP BY loan ORDER BY day ASC");
			foreach($res as $row){
				$dys = explode(",",$row['days']); sort($dys); $no=array_search($day,$dys)+1; $insts[$row["loan"]]="$no/".count($dys);
			}
			
			$sql = $db->query(2,"SELECT SUM(balance) AS tbal,`loan` FROM `org$cid"."_schedule` WHERE `loan` IN (".implode(",",$lns).") AND `day`<$tdy AND `balance`>0 GROUP BY `loan`");
			if($sql){
				foreach($sql as $row){ $arrs[$row["loan"]]=intval($row["tbal"]); }
			}
		
			$qry = $db->query(2,"SELECT `loan`,`client`,`phone`,`branch`,`client_idno`,`loan_officer` FROM `$ltbl` WHERE `loan` IN (".implode(",",$lns).") ORDER BY `client` ASC");
			foreach($qry as $rw){
				$lid = $rw["loan"]; $row=$list[$lid]; $name=prepare(ucwords($rw['client'])); $ofname=$staff[$rw['loan_officer']]; $fon=$rw['phone'];
				$pay=$row["paid"]; $amnt=$row["amount"]; $dy=$row["day"]; $bname=$bnames[$rw['branch']]; $total+=$amnt; $paid+=$pay; $no++; 
				$acum=(isset($arrs[$lid])) ? $arrs[$lid]:0; $tcum+=$acum; $inst=$insts[$lid];
				$trs.= "<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$ofname</td><td>$bname</td><td>$inst</td><td>".fnum($amnt)."</td><td>".fnum($acum)."</td><td>".fnum($pay)."</td></tr>";
			}
			$trs.= "<tr style='background:#e6e6fa;' class='trh'><td colspan='6'></td><td>".fnum($total)."</td><td>".fnum($tcum)."</td><td>".fnum($paid)."</td></tr>";
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