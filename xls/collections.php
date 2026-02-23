<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$type = trim($_GET['v']);
	$day = trim($_GET['dy']);
	$src = trim($_GET['src']);
	
	$db = new DBO(); $cid=CLIENT_ID;
	$prot = protectDocs($by);
	$pass = ($prot) ? $prot['password']:null;
	$tp = ($type=="rep") ? "Report":"Sheet";
	$rtl = ($type=="rep") ? date("d-m-Y",explode("-",$day)[0])." to ".date("d-m-Y",explode("-",$day)[1])." Collection Report":
	"Collection Sheet for ".date("d-m-Y",explode(":",$day)[0]);
	
	$title = ($type=="rates") ? "Collection Rates for ".date("F-Y",$day):$rtl;
	$title = ($type=="dmtd") ? "Disbursements from ".date("d-m-Y",explode(":",$day)[0])." to ".date("d-m-Y",explode(":",$day)[1]):$title;
	$title = ($type=="apprep") ? "Mobile App Disbursements from ".date("d-m-Y",explode(":",$day)[0])." to ".date("d-m-Y",explode(":",$day)[1]):$title;
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
	$stbl = "org".$cid."_schedule"; $ltbl = "org".$cid."_loans";
	$info = mficlient(); $logo=$info['logo']; $bnames=[];
	
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
				$trs[] = array($name,$fon,$ofname,$bname,$amnt,$acum,$pay,$bal);
			}
			$trs[] = array(null,null,null,null,fnum($total),fnum($tcum),fnum($paid),fnum($bals));
		}
		
		$head = array([null,$title,null,null],array("Client","Contact","Portfolio","Branch","Collection","Arrears","Paid","Balance"));
		$data = array_merge($head,$trs); $fname=prepstr("$title ".date("His"));
		$widths = array(20,10,20,18,16,16,10);
		$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	elseif($type=="rates"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		
		$ttl = ucwords(str_replace("_"," ",$cfield)); $mon=$day;
		$monrange = monrange(date('m',$mon),date("Y",$mon)); $dto=$monrange[1]+86399;
		$tln=$tpf=$totc=$toc=$tdd8=$tarr=$tgc=$tcg7=0; $trs=[];
		
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
				$trs[] = array($name,fnum($pv),fnum($tpv),fnum($otc),fnum($oc),fnum($dd8),fnum($cg7),
				fnum($arr),"$otp%","$ocp%","$gcp%");
			}
			
			$totp=round($totc/$tpf*100,2); $tocp=round($toc/$tpf*100,2); $tgcp=round($tgc/$tpf*100,2);
			$trs[] = array("Totals",fnum($tln),fnum($tpf),fnum($totc),fnum($toc),fnum($tdd8),fnum($tcg7),
			fnum($tarr),"$totp%","$tocp%","$tgcp%");
		}
		
		$head = array([null,$title,null,null],array($ttl,"Disbursed Loan","Loan+Charges","OTC","OC","DD7","CG7","Arrears","OTC%","OC%","GC%"));
		$widths = array(25,10,10,10,10,10,10,10,10,10,10,10,10,10); $fname=prepstr("$title ".date("His"));
		$res = genExcel(array_merge($head,$trs),"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	elseif($type=="apprep"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		$lis = explode(":",trim($_GET["lis"]));
		
		$ttl = ucwords(str_replace("_"," ",$cfield));
		$dfro = explode(":",$day)[0]; $dto = explode(":",$day)[1];
		$tln=$tpf=$tarr=$tpds=0; $trs=[];
		
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		foreach($qri as $row){
			$id=$row['id']; $pdef[]="$ltbl.loan_product='$id'";
		}
		
		$fetch=$cond; $cond.= "AND (".implode(" OR ",$pdef).")"; $mfr=monrange(date("m",$dfro),date("Y",$dfro))[0];
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE 1 $fetch");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d")); $ccol=str_replace("loan_officer","officer",$cfield);
				if(in_array($def,$lis)){
					$qri = $db->query(2,"SELECT SUM(apploans) AS ttag FROM `org$cid"."_targets` WHERE `month` BETWEEN $mfr AND $dto AND $ccol='$def'");
					$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' AND 
					`disbursement` BETWEEN $dfro AND $dto $cond");
					$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
					BETWEEN $dfro AND $dto AND sd.day<$today AND $cfield='$def' $cond"); 
					
					$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tot=$sq1[0]['tot']; 
					$tag=($qri) ? intval($qri[0]['ttag']):0; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd; $tots+=$tot; $ttag+=$tag;
					$name = (in_array($me['access_level'],["hq","region"])) ? $bnames[$def]:$staff[$def]; $gc=($tpv) ? round($tpd/$tpv*100,2):0;
					$trs[] = array($name,$tag,$pv,$tot,$tpv,$tpd,$arr,"$gc%");
				}
			}
			$trs[] = array("Totals",$ttag,$tln,$tots,$tpf,$tpds,$tarr,round($tpds/$tpf*100,2)."%");
		}
		
		$head = array([null,$title,null,null],array($ttl,"Target","Disbursements","Total Loans","Loan+Charges","Paid","Arrears","GC%"));
		$widths = array(25,15,15,15,15,15,15,15,15,15,15); $fname=prepstr("$title ".date("His"));
		$res = genExcel(array_merge($head,$trs),"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	elseif($type=="dmtd"){
		$ltbl = "org".$cid."_loans"; 
		$stbl = "org".$cid."_schedule";
		$bran = trim($_GET['br']);
		$cfield = trim($_GET['cf']);
		
		$ttl = ucwords(str_replace("_"," ",$cfield));
		$dfro = explode(":",$day)[0]; $dto = explode(":",$day)[1];
		$tln=$tpf=$tarr=$tpds=$tots=0; $trs=[];
		
		$res = $db->query(2,"SELECT DISTINCT $cfield FROM `$ltbl` WHERE `disbursement` BETWEEN $dfro AND $dto $cond");
		if($res){
			foreach($res as $rw){
				$def = $rw[$cfield]; $today=strtotime(date("Y-M-d")); 
				$sq1 = $db->query(2,"SELECT SUM(amount) AS tln,SUM(paid+balance) AS tsum,SUM(paid) AS tpd,COUNT(*) AS tot FROM `$ltbl` WHERE `$cfield`='$def' AND `disbursement` BETWEEN $dfro AND $dto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.disbursement 
				BETWEEN $dfro AND $dto AND sd.day<$today AND $cfield='$def' $cond"); 
				
				$pv=$sq1[0]['tln']; $tpv=$sq1[0]['tsum']; $arr=$sq2[0]['tarr']; $tpd=$sq1[0]['tpd']; $tln+=$pv; $tpf+=$tpv; $tarr+=$arr; $tpds+=$tpd;
				$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def]; $gc=round($tpd/$tpv*100,2); $tot=$sq1[0]['tot']; $tots+=$tot;
				$trs[] = array($name,fnum($pv),fnum($tot),fnum($tpv),fnum($tpd),fnum($arr),"$gc%");
			}
			$trs[] = array("Totals",fnum($tln),fnum($tots),fnum($tpf),fnum($tpds),fnum($tarr),round($tpds/$tpf*100,2)."%");
		}
		
		$head = array([null,$title,null,null],array($ttl,"Disbursed Amount","Total Loans","Loan+Charges","Paid","Arrears","GC%"));
		$widths = array(25,15,15,15,15,15,15,15,15,15); $fname=prepstr("$title ".date("His"));
		$res = genExcel(array_merge($head,$trs),"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	else{
		$tdy=strtotime("Today"); $total=$paid=$no=$tcum=0; $trs=$lns=$list=$arrs=$insts=[];
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
				$trs[] = array($name,$fon,$ofname,$bname,$inst,$amnt,$acum,$pay);
			}
			$trs[] = array(null,null,null,null,null,$total,$tcum,$paid);
		}
		
		$head = array([null,$title,null,null],array("Client","Contact","Portfolio","Branch","Installment","Amount","Accumulated","Paid"));
		$data = array_merge($head,$trs); $fname=prepstr("$title ".date("His"));
		$widths = array(20,15,20,20,18,16,18,10);
		$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	
?>