<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$acc=trim($_GET['src']); 
	$bran=trim($_GET['br']);
	$fro=trim($_GET['lfro']);
	$to=trim($_GET['lto']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$debs=$creds=0; $trs=$credits=$debits=[];
	
	$cond = ($bran) ? "AND `branch`='$bran'":"";
	if($bran){
		$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
		$bname = prepare(ucwords($qry[0]['branch']));
	}
	else{ $bname = "Head Office"; }
	
		$res = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
		foreach($res as $row){
			$name = prepare(ucwords($row['account'])); $id=$row['id']; $accs[$id]=$row['balance']; $types[$id]=$row['type']; $acnames[$id]=$name;
		}
		
		# petty cash
		if(in_array($acc,array(0,14,6))){
			$mtm = $db->query(3,"SELECT MIN(time) AS mtm FROM `cashbook$cid` WHERE 1 $cond")[0]['mtm']; $xto=$to+86399;
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
					$credit=($cred) ? $cred:null; $debit=($deb) ? $deb:null;
					
					if(!isset($bals[$mon])){ $bals[$mon]=0; }
					$tbal = ($type=="credit") ? $bals[$mon]-$amnt:$bals[$mon]+$amnt; $bals[$mon]=$tbal;
					
					$gdy = strtotime(date("Y-M-d",$row['time']));
					if(isset($credits[$gdy])){
						$credits[$gdy]+=($cred) ? $cred:0; $debits[$gdy]+=($deb) ? $deb:0; 
					}
					else{ $credits[$gdy]=($cred) ? $cred:0; $debits[$gdy]=($deb) ? $deb:0; }
					
					if(isset($trs[$tm])){
						for($i=1; $i<500; $i++){ $ntm=$tm+$i;
							if(!isset($trs[$ntm])){
								$trs[$ntm] = array($day,$name,$desc,null,$dy,"$tid\n",$credit,$debit,$tbal); break;
							}
						}
					}
					else{ $trs[$tm] = array($day,$name,$desc,null,$dy,"$tid\n",$credit,$debit,$tbal); }
				}
			}
		}
		
		if($acc){
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acc'");
			$row = $sql[0]; $tree=$row['tree']; $wing=$row['wing']; $title=prepare(ucwords($row['account']))." Ledger";
			
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
		else{ $title = "General Ledger"; }
		
		$res = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `day` BETWEEN '$fro' AND '$to' AND NOT `account`='14' AND `amount`>0 $cond ORDER BY `day`,`time` ASC");
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
				$debs+=$deb; $creds+=$cred; $tm=$row['time']; $name=$acnames[$rid]; $tid=$row['transid']; $mon=strtotime(date("Y-M",$tm));
				$credit=($cred) ? $cred:null; $debit=($deb) ? $deb:null; $css=$rbal="";
				
				if(isset($def['status']) && isset($def['time'])){
					$credit = (isset($def['accs']['credit'][$rid])) ? $def['accs']['credit'][$rid]:null;
					$debit = (isset($def['accs']['debit'][$rid])) ? $def['accs']['debit'][$rid]:null; $css="color:#B0C4DE;cursor:default";
				}
				
				if(!isset($bals[$mon])){ $bals[$mon]=[]; }
				if(!isset($bals[$mon][$rid])){ $bals[$mon][$rid]=0; }
				
				if($key==0 && $acc){
					$bd = ($bals[$mon][$rid]>=0) ? "B/D":"B/F"; 
					$trs[$tm] = array(null,null,null,null,date('d-m-Y',$fro),"Balance $bd",0,0,$bals[$mon][$rid]); 
				}
				
				if(in_array($row['book'],["expense","asset"])){ $rbal=($type=="credit") ? $bals[$mon][$rid]-$amnt:$bals[$mon][$rid]+$amnt; $bals[$mon][$rid]=$rbal; }
				else{ $rbal=($type=="debit") ? $bals[$mon][$rid]-$amnt:$bals[$mon][$rid]+$amnt; $bals[$mon][$rid]=$rbal; }
				
				$gdy=strtotime(date("Y-M-d",$row['day']));
				if(array_key_exists($gdy,$credits)){
					$credits[$gdy]+=($cred) ? $cred:0; $debits[$gdy]+=($deb) ? $deb:0; 
				}
				else{ $credits[$gdy]=($cred) ? $cred:0; $debits[$gdy]=($deb) ? $deb:0; }
				
				if(isset($trs[$tm])){
					for($i=1; $i<500; $i++){ $ntm=$tm+$i;
						if(!isset($trs[$ntm])){
							$trs[$ntm] = array($day,$name,$desc,"$ref\n",$dy,"$tid\n",$credit,$debit,$rbal); break;
						}
					}
				}
				else{ $trs[$tm] = array($day,$name,$desc,"$ref\n",$dy,"$tid\n",$credit,$debit,$rbal); }
			}
		}
		
		ksort($trs);
		$trs[time()]=array(null,null,null,null,null,null,$creds,$debs,null);
	
		$header = array("Date","Account","Description","Refno","Transaction","Trans ID","Credit","Debit","Balance");
		$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
		$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
		$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(20,20,30,15,15,15,15,15,15,15,15);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>