<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# show ledger
	if(isset($_GET['showledger'])){
		$acc = trim($_GET['showledger']); 
		$bran = trim($_GET['bran']);
		$sta = strtotime(trim($_GET['lfro']));
		$dto = strtotime(trim($_GET['lto']));
		$fro = ($dto<$sta) ? $dto:$sta;
		$to = ($dto>$sta) ? $dto:$sta;
		$debs=$creds=0; $mtm=$fro;
		$trs=$credits=$debits=$bals=$mns=[];
		
		$cond = ($bran) ? "AND `branch`='$bran'":"";
		$url = "accounts/ledger.php?bran=$bran&lfro=".trim($_GET['lfro'])."&lto=".trim($_GET['lto']);
		$show = (isset($_GET['display'])) ? trim($_GET['display']):"table";
		
		if($bran){
			$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
			$bname = prepare(ucwords($qry[0]['branch']));
		}
		else{ $bname = "Head Office"; }
		
		$lis = "<option value='0'> All accounts </option>"; 
		$res = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
		foreach($res as $row){
			$name = prepare(ucwords($row['account'])); $id=$row['id']; $accs[$id]=$row['balance']; $types[$id]=$row['type'];
			$cnd = ($id==$acc) ? "selected":""; $acnames[$id]=$name;
			$lis.=($id>5) ? "<option value='$id' $cnd>$name</option>":"";
		}
		
		# petty cash
		if(in_array($acc,array(0,14,6))){
			$xto=$to; $xto+=86399;
			$mtm = $db->query(3,"SELECT MIN(time) AS mtm FROM `cashbook$cid` WHERE 1 $cond")[0]['mtm']; 
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
					$type=$row['type']; $day=date("d-m-Y H:i",$row['time']); $tm=$row['time']; $tid=$row['transid']; $mon=$row['month'];
					$deb=($type=="debit") ? $amnt:null; $cred=($type=="credit") ? $amnt:null; $debs+=$deb; $creds+=$cred;
					$credit=($cred) ? number_format($cred):null; $debit=($deb) ? number_format($deb):null;
					
					if(!isset($bals[$mon])){ $bals[$mon]=0; }
					$tbal = ($type=="credit") ? $bals[$mon]-$amnt:$bals[$mon]+$amnt; $bals[$mon]=$tbal;
					
					$gdy = strtotime(date("Y-M-d",$row['time']));
					if(array_key_exists($gdy,$credits)){
						$credits[$gdy]+=($cred) ? $cred:0; $debits[$gdy]+=($deb) ? $deb:0; 
					}
					else{ $credits[$gdy]=($cred) ? $cred:0; $debits[$gdy]=($deb) ? $deb:0; }
					
					if(array_key_exists($tm,$trs)){
						$trs[$tm].="<tr><td>$day</td><td>$name</td><td>$desc</td><td></td><td>$dy</td><td>$tid</td><td style='text-align:center'>$credit</td>
						<td style='text-align:center'>$debit</td><td style='text-align:center'>".number_format($tbal)."</td></tr>";
					}
					else{
						$trs[$tm]="<tr><td>$dy</td><td>$name</td><td>$desc</td><td></td><td>$day</td><td>$tid</td><td style='text-align:center'>$credit</td>
						<td style='text-align:center'>$debit</td><td style='text-align:center'>".number_format($tbal)."</td></tr>";
					}
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
		
		$res = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `day` BETWEEN '$fro' AND '$to' AND NOT `account`='14' $cond ORDER BY `day`,`time` ASC");
		if($res){
			$load = ($acc) ? "AND `account`='$acc'":""; $mto=$fro-3600; $bals=[];
			$tmon = strtotime(date("Y-M")); $mfro=strtotime("-1 month",strtotime(date("Y-M",$fro))); 
			$chk = $db->query(3,"SELECT MIN(day) AS mtm,MAX(day) AS mxd FROM `transactions$cid` WHERE NOT `account`='14' $cond")[0]; 
			$qri = $db->query(3,"SELECT *FROM `monthly_balances$cid` WHERE `month` BETWEEN $mfro AND $to $load");
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
					$debit = (isset($def['accs']['debit'][$rid])) ? $def['accs']['debit'][$rid]:null; $css="color:#B0C4DE;cursor:default";
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
					$trs[$tm].= "<tr style='$css'><td>$dy</td><td>$name</td><td>$desc</td><td>$ref</td><td>$day</td><td>$tid</td>
					<td style='text-align:center'>$credit</td><td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($rbal)."</td></tr>";
				}
				else{
					$trs[$tm] = "<tr style='$css'><td>$dy</td><td>$name</td><td>$desc</td><td>$ref</td><td>$day</td><td>$tid</td>
					<td style='text-align:center'>$credit</td><td style='text-align:center'>$debit</td><td style='text-align:center'>".fnum($rbal)."</td></tr>";
				}
			}
		}
		
		$trs = implode("",$trs)."<tr style='background:linear-gradient(to top,#dcdcdc,#f8f8f0);font-weight:bold;text-align:center'>
		<td colspan='6'></td><td>".fnum($creds)."</td><td>".fnum($debs)."</td><td></td></tr>";
		
		$css="float:right;padding:4px;font-size:14px;cursor:pointer"; $opts="";
		foreach(array("table","graph") as $one){
			$cnd = ($one==$show) ? "selected":"";
			$opts.="<option value='$one' $cnd>".ucfirst($one)."</option>";
		}
		
		if($show=="table"){
			$prnt = ($trs) ? genrepDiv("ledger.php?src=$acc&br=$bran&lfro=$fro&lto=$to",'right'):"";
			$disp = "<table class='table-striped' style='width:100%;font-size:14px' cellpadding='7'>
				<tr style='background:#4682b4;color:#fff;font-weight:bold'><td>TransDate</td><td>Account</td><td>Description</td><td>Refno</td><td>Date Created</td>
				<td>Trans ID</td><td style='text-align:center'>Credit</td><td style='text-align:center'>Debit</td><td style='text-align:center'>Balance</td></tr>$trs
			</table><br>";
		}
		else{
			$graph=[]; $prnt="";
			foreach($debits as $day=>$deb){
				$dy=date("Y-m-d",$day); $cred=$credits[$day];
				$graph[]=["period"=>$dy,"debits"=>$deb,"credits"=>$cred];
			}
			
			$data = json_encode($graph);
			$disp="<div id='graph' style='height: 400px;'></div>
			<script>
				drawLine('graph','period',['debits','credits'],$data,['Debits','Credits']);
				function drawLine(elem,xd,yd,data,lbs){
					Morris.Line({
					  element: elem,
					  data: data,
					  xkey: xd,
					  ykeys: yd,
					  labels: lbs,
					  xLabelAngle: 70,
					  lineColors: ['#8A2BE2','#2E8B57'],
					  resize: true
					});
				}
				function printjs(){
					xepOnline.Formatter.Format('graph',{render:'download', srctype:'svg'});
				}
			</script>";
		}
		
		$tdy = date("Y-m-d"); $fdt=date("Y-m-d",$fro); $tdt=date("Y-m-d",$to); $mnd=date("Y-m-d",$chk["mtm"]); $max=date("Y-m-d",$chk["mxd"]);
		echo "<div class='cardv' style='max-width:1300px;overflow:auto'>
			<div style='min-width:750px'>
				<table style='width:100%' cellpadding='10'>
					<tr><td colspan='3'><h3 style='color:#191970;text-align:center;font-size:22px'><span style='float:left'>$backbtn</span> <u>$title</u></td></tr>
					<tr style='font-weight:bold;color:#191970'><td>$bname</td><td><input type='date' style='width:130px;padding:3px;font-size:14px' max='$tdy' min='$mnd' id='lfro'
					value='$fdt' onchange=\"setdate('lfro',this.value)\"> : <input type='date' id='lto' style='width:130px;padding:3px;font-size:14px' max='$tdy' min='$mnd' value='$tdt' 
					onchange=\"setdate('lto',this.value)\"></td><td>$prnt 
					<select style='width:140px;$css;margin-right:5px;' onchange=\"loadpage('$url&display=$show&showledger='+this.value)\">$lis</select>
					<select style='width:70px;margin-right:5px;$css' onchange=\"loadpage('$url&showledger=$acc&display='+this.value)\">$opts</select></td></tr>
				</table> $disp
			</div>
		</div>
		<script>
			function setdate(id,val){
				var ods = (id=='lfro') ? $('#lto').val():$('#lfro').val(), oid=(id=='lfro') ? 'lto':'lfro';
				loadpage('accounts/ledger.php?showledger=$acc&bran=$bran&'+id+'='+val+'&'+oid+'='+ods);
			}
		</script>";
		
		savelog($sid,"Viewed $title from ".date("M d,Y", $fro)." to ".date('M d,Y',$to));
	}
	
	# fetch ledger accounts
	if(isset($_GET['getledger'])){
		$tdy = date("Y-m-d");
		$me = staffInfo($sid); $access=$me['access_level'];
		$res = $db->query(3,"SELECT MIN(day) AS mdy,MAX(day) AS mxd FROM `transactions$cid`");
		$min = ($res) ? date("Y-m-d",$res[0]['mdy']):$tdy;
		$max = ($res) ? date("Y-m-d",$res[0]['mxd']):$tdy;
		
		$opts = "<option value='0'> Run all accounts </option>"; 
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`>5 ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $id=$row['id'];
			$opts.="<option value='$id'>$acc</option>";
		}
		
		$brans = ($access=="hq") ? "<option value='0'>Head Office</option>":"";
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		foreach($qri as $row){
			if($access=="hq"){ $brans.="<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>"; }
			else{
				$brans.=($me['branch']==$row['id']) ? "<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>":"";
			}
		}
		
		echo "<h3 style='color:#191970;text-align:center;font-size:22px'>Ledger Accounts</h3><br>
		<div style='padding:10px;margin:0 auto;max-width:300px'>
			<form method='post' id='lfom' onsubmit='runledger(event)'>
				<p>Select Ledger Account to Run<br><select style='width:100%' name='showledger'>$opts</select></p>
				<p>Branch<br><select style='width:100%' name='bran'>$brans</select></p>
				<p>Report From<br><input style='width:100%' type='date' name='lfro' min='$min' max='$max' required></p>
				<p>Report To<br><input style='width:100%' type='date' name='lto' min='$min' max='$max' required></p><br>
				<p style='text-align:right'><button class='btnn'>Run</button></p>
			</form><br>
		</div>";
		savelog($sid,"Accessed Ledger accounts");
	}

?>

<script>
	
	function runledger(e){
		e.preventDefault();
		var data = $("#lfom").serialize();
		loadpage("accounts/ledger.php?"+data); closepop();
	}

</script>