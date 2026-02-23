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
	$db = new DBO(); $cid=CLIENT_ID;
	
	$by = trim($_GET['pos']);
	$acc=trim($_GET['src']); 
	$bran=trim($_GET['br']);
	$fro=trim($_GET['lfro']);
	$to=trim($_GET['lto']);
	
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	$debs=$creds=0; $trs=$credits=$debits=[];
	
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
		.trx td{font-weight:bold;color:#191970;font-size:13px;}
		.tbl tr.fade td{color:#B0C4DE;}
	",1);
	
	$cond = ($bran) ? "AND `branch`='$bran'":"";
	if($bran){
		$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
		$bname = prepare(ucwords($qry[0]['branch']));
	}
	else{ $bname = "Head Office"; }
	
		$res = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
		foreach($res as $row){
			$name = prepare(ucwords($row['account'])); $id=$row['id']; $acnames[$id]=$name; $types[$id]=$row['type'];
		}
		
		# petty cash
		if(in_array($acc,array(0,14,6))){
			$mtm = $db->query(3,"SELECT MIN(time) AS mtm FROM `cashbook$cid` WHERE 1 $cond")[0]['mtm']; $xto+=86399;
			$qri = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `month` BETWEEN $fro AND $xto $cond");
			if($qri){
				foreach($qri as $row){
					if(isset($bals[$row['month']])){ $bals[$row['month']]+=$row['opening']; }
					else{ $bals[$row['month']]=$row['opening']; }
				}
			}
			
			$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `time` BETWEEN '$fro' AND '$xto' $cond ORDER BY `time` ASC");
			if($res){
				foreach($res as $row){
					$desc=prepare(ucfirst($row['transaction'])); $amnt=$row['amount']; $dy=date("d-m-Y",$row['time']); $name="Petty Cash";
					$type=$row['type']; $day=date("d-m-Y H:i",$row['time']); $tm=$row['time']; $tid=$row['transid']; $mon=strtotime(date("Y-M",$tm));
					$deb=($type=="debit") ? $amnt:null; $cred=($type=="credit") ? $amnt:null; $debs+=$deb; $creds+=$cred;
					$credit=($cred) ? fnum($cred):null; $debit=($deb) ? fnum($deb):null;
					
					if(!isset($bals[$mon])){ $bals[$mon]=0; }
					$tbal = ($type=="credit") ? $bals[$mon]-$amnt:$bals[$mon]+$amnt; $bals[$mon]=$tbal;
					
					$gdy = strtotime(date("Y-M-d",$row['time']));
					if(isset($credits[$gdy])){
						$credits[$gdy]+=($cred) ? $cred:0; $debits[$gdy]+=($deb) ? $deb:0; 
					}
					else{ $credits[$gdy]=($cred) ? $cred:0; $debits[$gdy]=($deb) ? $deb:0; }
					
					if(isset($trs[$tm])){
						$trs[$tm].= "<tr><td>$dy</td><td>$name</td><td>$desc</td><td></td><td>$day</td><td>$tid</td><td style='text-align:center'>$credit</td>
						<td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($tbal)."</td></tr>";
					}
					else{
						$trs[$tm]= "<tr><td>$dy</td><td>$name</td><td>$desc</td><td></td><td>$day</td><td>$tid</td><td style='text-align:center'>$credit</td>
						<td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($tbal)."</td></tr>";
					}
				}
			}
		}
		
		if($acc){
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acc'");
			$row = $sql[0]; $tree=$row['tree']; $wing=$row['wing']; $title=date("d-m-Y",$fro)." to ".date("d-m-Y",$to)." ".prepare(ucwords($row['account']))." Ledger";
			
			if($tree==0){ $ftc = "`account`='$acc'"; }
			else{
				$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing`='$acc' OR `wing` ='$wing,$acc'"); $ftc="(";
				foreach($qri as $key=>$row){
					$ftc.=($key==0) ? "`account`='".$row['id']."'":" OR `account`='".$row['id']."'"; 
				}
				$ftc.=" OR `account`='$acc')";
			}
			$cond.=" AND $ftc";
		}
		else{ $title = date("d-m-Y",$fro)." to ".date("d-m-Y",$to)." General Ledger"; }
		
		$res = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `day` BETWEEN '$fro' AND '$to' AND NOT `account`='14' $cond ORDER BY `day`,`time` ASC");
		if($res){
			$tmon=strtotime(date("Y-M")); $mfro=strtotime("-1 month",strtotime(date("Y-M",$fro))); $mto=$fro-3600; $bals=$mns=[];
			$mtm = $db->query(3,"SELECT MIN(day) AS mtm FROM `transactions$cid` WHERE NOT `account`='14' $cond")[0]['mtm']; 
			$qri = $db->query(3,"SELECT *FROM `monthly_balances$cid` WHERE `month` BETWEEN $mfro AND $to");
			if($qri){
				foreach($qri as $row){
					$rac=$row['account']; $mn=$row['month']; $bal=$row['openbal']; $mns[$mn][$rac]=$row['balance'];
					$bals[$mn][$rac]=(in_array($types[$rac],["income","expense"])) ? 0:$bal; 
				}
			}
			
			if(!isset($bals[$tmon]) && $to>=$tmon && $mns){ $bals[$tmon]=$mns[max(array_keys($mns))]; }
			foreach($bals as $mn=>$one){
				foreach($one as $acd=>$tot){
					if($mn<$fro && $fro<monrange(date("m",$mn),date("Y",$mn))[1] && !in_array($types[$acd],["income","expense"])){
						$sql = $db->query(3,"SELECT SUM(amount) AS tsum,`type` FROM `transactions$cid` WHERE `day` BETWEEN $mn AND $mto AND `account`='$acd' GROUP BY `type`");
						if($sql){
							foreach($sql as $rw){
								if($rw["type"]=="debit"){
									if($types[$acd]=="asset"){ $bals[$mn][$acd]+=$rw["tsum"]; }
									else{ $bals[$mn][$acd]-=$rw["tsum"]; }
								}
								if($rw["type"]=="credit"){
									if($types[$acd]=="asset"){ $bals[$mn][$acd]-=$rw["tsum"]; }
									else{ $bals[$mn][$acd]+=$rw["tsum"]; }
								}
							}
						}
					}
				}
			}
			
			foreach($res as $key=>$row){
				$def = (isJson($row['reversal'])) ? json_decode($row['reversal'],1):[];
				$desc=prepare(ucfirst($row['details'])); $ref=$row['refno']; $amnt=$row['amount']; $dy=date("d-m-Y",$row['day']); $rid=$row['account'];
				$type=$row['type']; $day=date("d-m-Y H:i",$row['time']); $deb=($type=="debit") ? $amnt:null; $cred=($type=="credit") ? $amnt:null;
				$debs+=$deb; $creds+=$cred; $tm=$row['time']; $name=$acnames[$rid]; $tid=$row['transid']; $mon=$row['month'];
				$credit=($cred) ? fnum($cred):null; $debit=($deb) ? fnum($deb):null; $css=$rbal="";
				
				if(isset($def['status']) && isset($def['time'])){
					$credit = (isset($def['accs']['credit'][$rid])) ? $def['accs']['credit'][$rid]:null;
					$debit = (isset($def['accs']['debit'][$rid])) ? $def['accs']['debit'][$rid]:null; $css="fade";
				}
				
				if(!isset($bals[$mon])){ $bals[$mon]=[]; }
				if(!isset($bals[$mon][$rid])){ $bals[$mon][$rid]=0; }
				
				if($key==0 && $acc){
					$bd = ($bals[$mon][$rid]>=0) ? "B/D":"B/F"; $csd="text-align:center";
					$trs[$tm] = "<tr style='font-weight:bold;color:#191970'><td></td><td></td><td></td><td></td><td style='font-size:13px'>".date('d-m-Y',$fro)."</td>
					<td>Balance $bd</td><td style='$csd'>0</td><td style='$csd'>0</td><td style='$csd'>".fnum($bals[$mon][$rid])."</td></tr>"; 
				}
				
				if(in_array($row['book'],["expense","asset"])){ $rbal=($type=="credit") ? $bals[$mon][$rid]-$amnt:$bals[$mon][$rid]+$amnt; $bals[$mon][$rid]=$rbal; }
				else{ $rbal=($type=="debit") ? $bals[$mon][$rid]-$amnt:$bals[$mon][$rid]+$amnt; $bals[$mon][$rid]=$rbal; }
				
				$gdy=strtotime(date("Y-M-d",$row['day']));
				if(isset($credits[$gdy])){
					$credits[$gdy]+=($cred) ? $cred:0; $debits[$gdy]+=($deb) ? $deb:0; 
				}
				else{ $credits[$gdy]=($cred) ? $cred:0; $debits[$gdy]=($deb) ? $deb:0; }
				
				if(isset($trs[$tm])){
					$trs[$tm].= "<tr class='$css'><td>$dy</td><td>$name</td><td>$desc</td><td>$ref</td><td>$day</td><td>$tid</td>
					<td style='text-align:center'>$credit</td><td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($rbal)."</td></tr>";
				}
				else{
					$trs[$tm] = "<tr class='$css'><td>$dy</td><td>$name</td><td>$desc</td><td>$ref</td><td>$day</td><td>$tid</td>
					<td style='text-align:center'>$credit</td><td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($rbal)."</td></tr>";
				}
			}
		}
		
		$trs = implode("",$trs)."<tr style='background:linear-gradient(to bottom,#dcdcdc,#f8f8f0);font-weight:bold;text-align:center' class='trx'>
		<td colspan='6'></td><td>".fnum($creds)."</td><td>".fnum($debs)."</td><td></td></tr>";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `id`='$by'");
		$sname = prepare(ucwords($res[0]['name']));
		
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f;margin-bottom:5px'>Printed on ".date('M d,Y - h:i a')." by $sname</h4>
		<table class='tbl' style='width:100%;font-size:14px' cellpadding='5' cellspacing='0'>
			<tr style='background:#4682b4;' class='trh'><td>TransDate</td><td>Account</td><td>Description</td><td>Refno</td><td>Date Created</td>
			<td>Trans ID</td><td style='text-align:center'>Credit</td><td style='text-align:center'>Debit</td><td style='text-align:center'>Balance</td></tr> $trs
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