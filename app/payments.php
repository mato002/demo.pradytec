<?php
	session_start();
	ob_start();
	
	require "../core/functions.php";
	require "defcalls.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	$qri = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE `position`='USSD' AND `gender`='app' AND `status`='0'");
	$config = mficlient(); $co=$config['company']; $mcont=$config["contact"]; $mail=$config["email"];
	$company =(strlen($co)>25) ? substr($co,0,25):$co; 
	$sid = ($_SERVER["HTTP_HOST"]=="localhost") ? 0:$qri[0]['id'];
	
	$path = ($_SERVER['HTTP_HOST']=="localhost") ? "/mfs":"";
	$loc  = explode("/",$_SERVER['SCRIPT_NAME']); array_pop($loc);
	$url  = implode("/",$loc); $logtp=loginType(); $data="";
	
	if(!in_array($logtp,["staff","client"])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
	if($logtp=="staff" && !isset($_SESSION["use4"])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
	
	if(isset($_GET["view"])){
		$gurl = str_replace(" ","+",trim($_GET["view"]));
		$page = (isset($_GET['pg'])) ? intval($_GET['pg']):1;
		if(isset($_COOKIE["app4"])){ $_SESSION["use4"]=$_COOKIE["app4"]; }
		if(isset($_SESSION["clapp"])){ $idno=$_SESSION["clapp"]; }
		elseif(isset($_COOKIE["myapp"])){ $idno=hexdec($_COOKIE["myapp"]); $_SESSION["clapp"]=$idno; }
		else{
			$src = explode("/",$gurl);
			$key = hexdec(array_shift($src));
			$idno = decrypt(implode("/",$src),$key);
		}
		
		if($idno){
			if(!isset($_SESSION["clapp"])){ $_SESSION["clapp"]=$idno; }
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			if(!$sql){ echo "<script> window.location.replace('index.php?home=$gurl'); </script>"; exit(); }
			
			$cdef = json_decode($sql[0]['cdef'],1);
			if(!isset($_COOKIE["myapp"]) or (!isset($cdef["ckey"]) && !isset($_SESSION["use4"]))){ 
				echo "<script> window.location.replace('index.php?home=$gurl'); </script>"; exit();
			}
			
			$lim = getLimit($page,20); $ltrs=$cond=""; $no=0; $fon=$sql[0]['contact']; $client=prepare(ucwords($sql[0]['name']));
			$qri = $db->query(2,"SELECT `loan` FROM `org$cid"."_loans` WHERE `client_idno`='$idno'");
			if($qri){
				foreach($qri as $row){
					$lid=$row['loan']; $cond.="OR `status`='$lid' ";
				}
			}
			
			$qry = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `account`='$idno' OR `account`='$fon' $cond ORDER BY `date` DESC $lim");
			if($qry){
				foreach($qry as $row){
					$code=$row['code']; $amnt=fnum($row['amount'],2); $dy=$row['date']; $st=$row['status']; $no++;
					$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
					$state = (!$st) ? "<span style='font-size:13px;color:grey'><i class='bi-alarm'></i> Pending</span>":
					"<span style='font-size:13px;color:green'><i class='bi-check2-circle'></i> Confirmed</span>";
					$css = ($no<count($qry)) ? "style='border-bottom:1px solid #dcdcdc'":"";
					$click = ($st) ? "onclick=\"window.location.href='payments.php?trans=$code'\"":"";
					$ltrs.= "<tr $click $css><td>$code<br><span style='font-size:14px;color:grey'>".date("M d,Y h:i a",$ptm)."</span></td>
					<td style='text-align:right'>KES $amnt<br>$state</td></tr>";
				}
			}
			
			$qri = $db->query(2,"SELECT COUNT(*) AS tot,SUM(amount) AS tsum FROM `org$cid"."_payments` WHERE `account`='$idno' OR `account`='$fon' $cond");
			$totals = intval($qri[0]['tot']); $tsum=fnum(intval($qri[0]['tsum']));
			
			$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
				<tr style='height:40px;'><td>
					<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; MY PAYMENTS</h3>
				</td></tr>
				<tr valign='top'><td>
					<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
						<div style='background:$aptheme;color:#fff;border-radius:10px;padding:5px;'>
							<table cellpadding='7' style='width:100%;font-family:signika negative'><tr>
								<td><h3><span style='font-size:14px'>KES</span> $tsum<br><span style='color:#E6E6FA;font-size:13px'>TOTAL PAID</span></td>
								<td style='text-align:right'><button class='btnn' onclick=\"window.location.href='?makepay'\"><i class='bi-plus-circle'></i> Payment</button></td>
							</tr></table>
						</div><br>
						<h4 style='font-size:20px;color:#4682b4;padding:8px;background:#f0f0f0'>All Transactions</h4>
						<table cellpadding='7' style='width:100%;margin-bottom:10px'> $ltrs </table>".limitDiv($page,20,$totals,"payments.php?view")."
					</div>
				</td></tr>
			</table>";
			savelog($sid,"$client viewed payment transactions");
		}
	}
	
	# view wallet
	if(isset($_GET["wallet"])){
		$ref = trim($_GET["wallet"]);
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$page = (isset($_GET['pg'])) ? intval($_GET['pg']):1;
		$lim = getLimit($page,20); $exw=explode(":",$ref); $wid=$exw[0]; 
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
		$uid = $qri[0]['client']; $wtp=$exw[1]; $totals=$no=0; $ltrs=""; 
		$tps = array(1=>"Transactional",2=>"Savings",3=>"Investment");
		$click1=$click2=$click3=""; $btcl=$btc2=$btc3="btab";
		$sttl = (defined("SAVINGS_TERMS")) ? SAVINGS_TERMS["acc"]:"Savings";
		
		if($wtp==1){
			$click2 = "window.location.replace('?wallet=$wid:2')"; $click3="window.location.replace('?wallet=$wid:3')";
			$wtx = "TRANSACTION ACCOUNT"; $tbal=fnum(round($qri[0]['balance'])); $abal=walletBal($wid); 
			$wbal = fnum(round($abal)); $btcl.=" active";
			if($abal<0){ $wbal = "(".str_replace("-","",$wbal).")"; }
			
			$tds = "<td style='text-align:center'><h3 style='font-size:20px;font-weight:bold;color:#7FFF00'><span style='font-size:13px'>Ksh</span> $wbal<br><span style='color:#E6E6FA;
			font-size:13px'>Available Balance</span><br><br><button class='btnn' style='font-size:15px;padding:6px;width:80px;background:#325d81;color:#00FFFF;font-weight:bold'
			onclick=\"window.location.href='payments.php?wtrans=$ref'\"><i class='bi-arrow-left-right'></i> Transfer</button></td>
			<td style='text-align:center'><h3 style='font-size:20px;font-weight:bold'><span style='font-size:13px'>
			Ksh</span> $tbal<br><span style='color:#E6E6FA;font-size:13px'>Actual Balance</span><br><br><button class='btnn' style='font-size:15px;padding:6px;width:80px;
			background:#325d81;color:#FFD700;font-weight:bold' onclick=\"window.location.href='payments.php?wwithdraw=$ref'\"><i class='bi-download'></i> Withdraw</button></td>";
			
			$ovd = (defined("WALLET_OVERDRAFT")) ? WALLET_OVERDRAFT:0;
			if($ovd){
				$wdef = ($qri[0]["def"]) ? json_decode($qri[0]["def"],1):[];
				$olim = (isset($wdef["ovdlimit"])) ? $wdef["ovdlimit"]:0; 
				$intr = (isset($wdef["charges"])) ? $wdef["charges"]:0; $olim+= ($abal<0) ? $abal+$intr:0; $ovb=fnum($olim);
				$cash = ($olim>0) ? "<button class='btnn' style='font-size:15px;padding:6px;width:80px;background:#325d81;color:#FFD700;font-weight:bold'
				onclick=\"window.location.href='payments.php?wwithdraw=$ref&addr=ovd'\"><i class='bi-download'></i> Withdraw</button>":"";
				$tds.= "<td style='text-align:center'><h3 style='font-size:20px;font-weight:bold;color:#7FFF00'><span style='font-size:13px'>Ksh</span> $ovb<br><span style='color:#E6E6FA;
				font-size:13px'>Available Overdraft</span><br><br>$cash</td>";
			}
		}
		elseif($wtp==2){
			$click1 = "window.location.replace('?wallet=$wid:1')"; $click3="window.location.replace('?wallet=$wid:3')";
			$wtx = strtoupper("$sttl ACCOUNT"); $tbal=fnum(round($qri[0]['savings'])); $btc2.=" active";
			$tds = "<td><h3 style='font-size:22px;font-weight:bold;color:#7FFF00'><span style='font-size:15px;'>Ksh</span> $tbal<br><span style='color:#E6E6FA;
			font-size:14px'>Savings Balance</span></td><td style='text-align:right'><button class='btnn' style='font-size:15px;padding:6px;background:#325d81;color:#00FFFF;font-weight:bold'
			onclick=\"window.location.href='payments.php?wtrans=$ref'\"><i class='bi-arrow-left-right'></i> Transfer</button></td>";
		}
		else{
			$click1 = "window.location.replace('?wallet=$wid:1')"; $click2="window.location.replace('?wallet=$wid:2')";
			$wtx = "INVESTMENT ACCOUNT"; $ibal=fnum($qri[0]['investbal']); $invs=0; $btc3.=" active";
			if($db->istable(3,"investments$cid")){
				$sql = $db->query(3,"SELECT SUM(amount) AS tsum FROM `investments$cid` WHERE `client`='C$uid' AND `status`='0'");
				$invs = ($sql) ? fnum(intval($sql[0]['tsum'])):0;
			}
			
			$tds = "<td style='text-align:center'><h3 style='font-size:20px;font-weight:bold;color:#7FFF00'><span style='font-size:13px'>Ksh</span> $ibal<br><span style='color:#E6E6FA;
			font-size:13px'>Available Balance</span><br><br><button class='btnn' style='font-size:15px;padding:6px;width:80px;background:#325d81;color:#00FFFF;font-weight:bold'
			onclick=\"window.location.href='payments.php?wtrans=$ref'\"><i class='bi-arrow-left-right'></i> Transfer</button></td><td style='text-align:center'>
			<h3 style='font-size:20px;font-weight:bold;color:#7FFF00'><span style='font-size:13px'>Ksh</span> $invs<br><span style='color:#E6E6FA;font-size:13px'>Investments</span><br><br>
			<button class='btnn' style='font-size:15px;padding:6px;width:80px;background:#325d81;font-weight:bold' onclick=\"window.location.href='payments.php?vinvest=$uid:$wid'\">
			<i class='bi-list-stars'></i> View</button></td>";
		}
		
		if($db->istable(3,"walletrans$cid")){
			$vbk = $tps[$wtp]; $no=0;
			$qry = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk' ORDER BY `id` DESC,`time` DESC $lim");
			if($qry){
				foreach($qry as $row){
					$dy=date("d-m-Y, H:i",$row['time']); $no++; $css=($no<count($qry)) ? "border-bottom:1px solid #dcdcdc;":""; 
					$css.=($row['status']) ? "color:#B0C4DE":""; $des=prepare(ucfirst($row['details'])); $amnt=fnum($row['amount']);
					$sgn=($row["transaction"]=="debit") ? "+":"-"; $color=($sgn=="-") ? "#ff4500":"green";
					$ltrs.= "<tr style='$css'><td><p style='font-weight:bold;margin-bottom:8px'>$dy <span style='float:right;color:$color'>$sgn$amnt</span></p>
					<p style='margin:0px'>$des</p></td></tr>";
				}
			}
			
			$sql = $db->query(3,"SELECT COUNT(*) AS tot FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk'");
			$totals = ($sql) ? intval($sql[0]['tot']):0;
		}
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
		$client = prepare(ucwords($qri[0]['name']));
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; $wtx</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
					<table style='width:100%;background:$aptheme;' cellpadding='0' cellspacing='0'><tr>
						<td style='width:33%;'><button class='$btcl' onclick=\"$click1\"><i class='bi-wallet'></i><br>Transaction<br>Account</button></td>
						<td style='width:33%;'><button class='$btc2' onclick=\"$click2\"><i class='bi-briefcase'></i><br>$sttl<br>Account</button></td>
						<td style='width:33%;'><button class='$btc3' onclick=\"$click3\"><i class='bi-graph-up'></i><br>Investment<br>Account</button></td>
					</tr></table>
					<div style='background:$aptheme;color:#fff;border-radius:10px;border-top-right-radius:0px;border-top-left-radius:0px;padding:15px 5px 5px 5px;'>
						<table cellpadding='7' style='width:100%;font-family:signika negative'><tr valign='top'>$tds</tr></table>
					</div><br>
					<h4 style='font-size:20px;color:#4682b4;padding:8px;background:#f0f0f0'>Transaction History</h4>
					<table cellpadding='7' style='width:100%;margin-bottom:10px'> $ltrs </table>".limitDiv($page,20,$totals,"payments.php?wallet=$ref")."
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed ".strtolower($wtx));
	}
	
	# transfer from wallet
	if(isset($_GET["wtrans"])){
		$ref = trim($_GET["wtrans"]);
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$tps = array(1=>"balance",2=>"savings",3=>"investbal");
		$idno = $_SESSION["clapp"]; $exw=explode(":",$ref); $wid=$exw[0]; $wtp=$exw[1]; $vtp=$tps[$wtp];
		$sql = $db->query(2,"SELECT `branch`,`name`,`contact`,`id` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$client=prepare(ucwords($sql[0]['name'])); $fon=$sql[0]['contact']; $bal=round(walletBal($wid,$vtp)); $opts="";
		$def = ($vtp=="balance") ? ["loan"=>"Loan Repayment","investbal"=>"Investment Account","savings"=>"Savings Account"]:["balance"=>"Transactional Account"];
		foreach($def as $key=>$val){ $opts.="<option value='$key'>$val</option>"; }
		
		$error = ($bal<10) ? "Insufficient Account Balance":"";
		if($logtp=="staff"){
			if(isset($_COOKIE["bid"])){
				$bid = explode(":",decrypt($_COOKIE["bid"],"bkey"))[1]; 
				$qri = $db->query(2,"SELECT `config` FROM `org$cid"."_staff` WHERE `jobno`='$bid'");
				$cnf = json_decode($qri[0]["config"],1); $lca=(isset($cnf["lca"])) ? $cnf["lca"]:0;
				if($lca!=$sql[0]['id']){ $error = "Permission Denied! You are not the owner"; }
			}
			else{ $error = "Permission Denied! You are not the owner"; }
		}
		
		$sbtn = ($error) ? "<p style='color:#ff4500;background:#FFDAB9;padding:10px;text-align:center'>
		<i class='bi-exclamation-octagon'></i> $error</p>":"<p style='text-align:right'><button class='sbtn'>Tranfer Funds</button></p>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:20px;text-align:center'><b>Initiate Account Transfer</b></h3><br>
					<form method='post' id='tform' onsubmit=\"transwallet(event,'$bal')\"> 
						<input type='hidden' name='transwid' value='$wid'> <input type='hidden' name='transfro' value='$vtp'>
						<p>Transfer To<br><select style='width:100%' name='transto' id='tto' onchange='checkopt(this.value)'>$opts</select></p>
						<p>Transfer Amount<br><input type='number' name='tamnt' id='tamnt' value='$bal' max='$bal' style='width:100%' required></p>
						<p>Comments<br><input type='text' name='wcomm' style='width:100%'></p>
						<div style='width:100%;border:1px solid #ccc;padding:4px;line-height:;margin-top:20px'><input type='number' name='otpv' 
						style='border:0px;padding:4px;width:150px;' placeholder='OTP Code' id='otp' autocomplete='off' required><a href='javascript:void(0)' 
						onclick=\"requestotp('$fon','".str_replace("'","",$client)."')\" style='float:right;margin-top:5px'><i class='fa fa-refresh'></i> Request</a></div><br>
						<br>$sbtn<br>
					</form>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed wallet transfer form");
	}
	
	# topup wallet
	if(isset($_GET["walletop"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"]; $tym=time(); $opts="";
		$sql = $db->query(2,"SELECT `branch`,`name`,`contact`,`id` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$client=prepare(ucwords($sql[0]['name'])); $bid=$sql[0]['branch']; $fon=$sql[0]['contact']; $uid=$sql[0]['id'];
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
		if(!$qri){
			$db->execute(3,"INSERT INTO `wallets$cid` VALUES(NULL,'$uid','client','0','0','0','$tym')");
			$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
		}
		
		$cond = ($bid) ? "`id`='$bid'":"NOT `paybill`='0'"; $wid=$qri[0]['id']; 
		$payb = $db->query(1,"SELECT `paybill` FROM `branches` WHERE $cond")[0]['paybill'];
		$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
		$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
		$ckey = (isset($keys[$payb])) ? $keys[$payb]["ckey"]:""; $csec=(isset($keys[$payb])) ? $keys[$payb]["csec"]:""; 
		$accs = walletAccount($bid,$wid); $accno=$accs["balance"];
		
		foreach(["balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings"] as $key=>$val){
			$opts.= "<option value='".$accs[$key]."'>$val Account</option>";
		}
		
		$form = ($pkey) ? "<br><input type='hidden' name='paybno' value='$payb'>
		<p>Topup Account<br><select style='width:100%' name='accno'>$opts</select></p>
		<p>MPESA Phone number<br><input type='number' name='phone' value='0$fon' id='fon'></p>
		<p>Topup Amount<br><input type='number' name='amnt' id='amnt'></p><br><p><button class='sbtn'>Initiate Payment</button></p>":
		"<div style='background:#f0f0f0;padding:10px;'><ul><li>Go to MPESA Toolkit</li><li>Select Pay Bill</li><li>Enter Paybill No <b>$payb</b></li>
		<li>Enter Amount & PIN</li><li>ACC No: $accno</li></ul></div>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:22px;text-align:center'><b>Initiate Payment Request</b></h3><br>
					<form method='post' id='pform' onsubmit='makepay(event)'>
						<input type='hidden' name='pkey' value='$pkey'><input type='hidden' name='ckey' value='$ckey'> <input type='hidden' name='csec' value='$csec'> $form
					</form>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client wallet account topup form");
	}
	
	# wallet Withdraw
	if(isset($_GET["wwithdraw"])){
		$ref = trim($_GET["wwithdraw"]);
		$src = (isset($_GET["addr"])) ? trim($_GET["addr"]):"bal";
		$cnl = (isset($_GET["cnl"])) ? trim($_GET["cnl"]):"b2c";
		
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"]; $exw=explode(":",$ref); $wid=$exw[0]; $wtp=$exw[1]; $vtp=($wtp==1) ? "balance":"investbal";
		$sql = $db->query(2,"SELECT `branch`,`name`,`contact`,`id` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$client = prepare(ucwords($sql[0]['name'])); $fon=$sql[0]['contact']; $bal=round(walletBal($wid,$vtp));
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_withdrawal_fees'");
		$fees = ($qri) ? json_decode($qri[0]['value'],1):B2C_RATES;
		$maxet = (defined("MAX_APP_WITHDRAWAL")) ? MAX_APP_WITHDRAWAL:1000;
		
		$chk = $db->query(3,"SELECT `def` FROM `wallets$cid` WHERE `id`='$wid'");
		$wdef = ($chk[0]["def"]) ? json_decode($chk[0]["def"],1):[]; $intr=(isset($wdef["charges"])) ? $wdef["charges"]:0;
		$obal = (isset($wdef["ovdlimit"])) ? $wdef["ovdlimit"]:0; $obal+=($bal<=0) ? $bal+$intr:0; 
		$tbal = ($bal>0) ? $bal+$obal:$obal; $sbal=($bal>0) ? $bal:$tbal;
		
		$error = ($tbal<10) ? "Insufficient Account Balance":"";
		if($logtp=="staff"){
			if(isset($_COOKIE["bid"])){
				$bid = explode(":",decrypt($_COOKIE["bid"],"bkey"))[1]; 
				$qri = $db->query(2,"SELECT `config` FROM `org$cid"."_staff` WHERE `jobno`='$bid'");
				$cnf = json_decode($qri[0]["config"],1); $lca=(isset($cnf["lca"])) ? $cnf["lca"]:0;
				if($lca!=$sql[0]['id']){ $error = "Permission Denied! You are not the owner"; }
			}
			else{ $error = "Permission Denied! You are not the owner"; }
		}
		
		$dlis = array("b2c"=>"To MPESA","b2b"=>"To Paybill","till"=>"BuyGoods (Till)"); $opts=""; $no=0;
		foreach($dlis as $md=>$des){
			$cnd = ($md==$cnl) ? "selected":"";
			if($md=="b2c"){ $opts.="<option value='$md' $cnd>$des</option>"; $no++; }
			else{
				if(defined("B2B_DEF")){ $opts.="<option value='$md' $cnd>$des</option>"; $no++; }
			}
		}
		
		$mode = ($no>1) ? "<p>Withdrawal Channel<br><Select name='channel' style='width:100%' onchange=\"window.location.replace('?wwithdraw=$ref&cnl='+this.value)\">$opts</select></p>":"";
		if($cnl=="b2c"){ $mode.= "<p>MPESA Phone number<br><input type='number' name='disbto' value='0$fon' id='fon'></p>"; }
		else{
			$mode.= ($cnl=="b2b") ? "<p>Paybill No<br><input type='number' name='disbto' style='width:100%' required></p>
			<p>Account No<br><input type='text' name='accno' style='width:100%' required></p>":"<p>Till No<br><input type='number' name='disbto' style='width:100%' required></p>
			<p>Till Name<br><input type='text' name='rname' style='width:100%' required></p>";
		}
		
		$error = (!sys_constants("enable_app_withdrawals")) ? "Withdrawals are disabled at the moment":$error;
		$sbtn = ($error) ? "<p style='color:#ff4500;background:#FFDAB9;padding:10px;text-align:center'>
		<i class='bi-exclamation-octagon'></i> $error</p>":"<p style='text-align:right'><button class='sbtn'><i class='bi-download'></i> Withdraw</button></p>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:20px;text-align:center'><b>Withdraw From Account</b></h3><br>
					<form method='post' id='wform' onsubmit=\"withdraw(event)\"> 
						<input type='hidden' name='walwid' value='$wid'> $mode
						<p>Withdrawal Amount<br><input type='number' name='tamnt' id='amnt' value='$sbal' max='$tbal'  onkeyup=\"checkfee(this.value)\" style='width:100%' required>
						<br><span style='color:grey;font-size:13px'>Instant withdrawal apply for amounts upto Ksh <b>".fnum($maxet)."</b></span></p>
						<p>Comments/Description<br><input type='text' name='wcomm' style='width:100%'></p>
						<div style='width:100%;border:1px solid #ccc;padding:4px;line-height:;margin-top:20px'><input type='number' name='otpv' 
						style='border:0px;padding:4px;width:150px;' placeholder='OTP Code' id='otp' autocomplete='off' required><a href='javascript:void(0)' 
						onclick=\"requestotp('$fon','".str_replace("'","",$client)."')\" style='float:right;margin-top:5px'><i class='fa fa-refresh'></i> Request</a></div><br>
						<p id='fsp' style='text-align:right'></p><br>$sbtn<br>
					</form>
				</div>
			</td></tr>
		</table>
		<script>
			var fees = ".json_encode($fees,1)."; checkfee($sbal);
			function checkfee(val){
				var sum = parseInt(val),fee=0;
				if(sum>0){
					for(var i in fees){
						var range = i.split('-');
						if(sum>=parseInt(range[0]) && sum<=parseInt(range[1])){ fee=fees[i]; }
					}
					$('#fsp').html('<span style=\"padding:8px;background:#EEE8AA;color:#000080\">Withdrawal Fee: Ksh '+fee+'</span>');
				}
				else{ $('#fsp').html(''); }
			}
		</script>";
		savelog($sid,"$client accessed wallet Withdrawal form");
	}
	
	# View Investments
	if(isset($_GET["vinvest"])){
		$ref = trim($_GET["vinvest"]);
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$page = (isset($_GET['pg'])) ? intval($_GET['pg']):1;
		$lim = getLimit($page,10); $exw=explode(":",$ref); $wid=$exw[1]; $uid=$exw[0];
		$totals=$no=$invs=$pays=0; $ltrs=""; $mine=0;
		
		if($logtp=="staff"){
			if(isset($_COOKIE["bid"])){
				$idno = $_SESSION["clapp"];
				$qry = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
				$bid = explode(":",decrypt($_COOKIE["bid"],"bkey"))[1]; $usa=($qry) ? $qry[0]['id']:0;
				$qri = $db->query(2,"SELECT `config` FROM `org$cid"."_staff` WHERE `jobno`='$bid'");
				$cnf = json_decode($qri[0]["config"],1); $lca=(isset($cnf["lca"])) ? $cnf["lca"]:0;
				if($lca==$usa){ $mine=1; }
			}
		}
		else{ $mine=1; }
		
		if($db->istable(3,"investments$cid")){
			$sql = $db->query(3,"SELECT SUM(amount) AS tsum FROM `investments$cid` WHERE `client`='C$uid' AND `status`='0'");
			$qri = $db->query(3,"SELECT COUNT(*) AS tot,SUM(JSON_EXTRACT(details,'$.withdrawal')) AS tpy FROM `investments$cid` WHERE `client`='C$uid'");
			$invs = ($sql) ? fnum(intval($sql[0]['tsum'])):0; $totals=($qri) ? intval($qri[0]['tot']):0; $pays=($qri) ? fnum(intval($qri[0]['tpy'])):0;
			
			$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='C$uid' ORDER BY `time` DESC $lim");
			if($sql){
				foreach($sql as $row){
					$dy=date("d-m-Y,H:i",$row['time']); $def=json_decode($row['details'],1); $amnt=fnum($row['amount']); $pack=prepare($def["pname"]);
					$dur=$row['period']; $rid=$row['id']; $ran=rand(12345678,87654321); $st=($row['status']>200) ? 200:$row['status']; $term="";
					$css = "font-size:14px;color:#fff;padding:4px;font-family:signika negative"; $bal=fnum($def["balance"]); $pid=$row['package'];
					
					if($st==0){
						$tdy = intval(date("d")); $tmn=intval(date("m")); $wopen=0;
						$qri = $db->query(1,"SELECT *FROM `invest_packages` WHERE `id`='$pid'");
						$prod = json_decode($qri[0]['periods'],1); $wdys=explode("-",$prod["wdays"]); $wmon=$prod["wmon"];
						if($wmon==0){
							if($exp<=time() && $tdy>=$wdys[0] && $tdy<=$wdys[1]){ $wopen=1; }
						}
						else{
							if($tmn==$wmon && $tdy>=$wdys[0] && $tdy<=$wdys[1]){ $wopen=1; }
						}
						
						if($mine && $wopen){
							$term = "<br><a href='?terminv=$rid:$uid' style='float:right;background:purple;color:#fff;padding:3px 6px'><i class='bi-download'></i> Terminate</a>";
						}
					}
					
					$states = array(0=>"<span id='$ran' style='font-weight:bold'><script> Timer('".date("M d, Y H:i:s",$row['maturity'])."','$ran'); </script></span>",
					200=>"<span style='background:#20B2AA;$css'>Matured ".date("d-m-Y",$row['status'])."</span>",15=>"<span style='background:orange;$css'>Terminated</span>");
					
					$ltrs.= "<tr style='border-bottom:1px solid #dcdcdc;'><td><p style='font-weight:bold;margin-bottom:8px'>$dy <span style='float:right;color:#191970'>Ksh $amnt</span></p>
					<p style='margin-bottom:7px'>$pack Investment for $dur days</p><p style='margin-bottom:7px'>$states[$st] <span style='float:right;color:green;font-weight:bold'>
					Bal Ksh $bal</span>$term</p></td></tr>";
				}
			}
		}
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
		$client = prepare(ucwords($qri[0]['name'])); 
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; INVESTMENTS</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
					<div style='background:$aptheme;color:#fff;border-radius:10px;padding:5px;'>
						<table cellpadding='7' style='width:100%;font-family:signika negative'><tr>
						<td style='text-align:center'><h3 style='font-size:20px;font-weight:bold'>$invs<br><span style='color:#E6E6FA;
						font-size:13px'>Investments</span></td><td style='text-align:center'><h3 style='font-size:20px;font-weight:bold'>$pays<br>
						<span style='color:#E6E6FA;font-size:13px'>Payouts</span></td>
						<td style='text-align:right'><button class='btnn' style='background:#325d81;padding:5px;width:45px' 
						onclick=\"window.location.href='payments.php?investnow=$uid'\"><i class='bi-bag-plus'></i><br> Invest</a></button></td>
						</tr></table>
					</div><br>
					<h4 style='font-size:20px;color:#4682b4;padding:8px;background:#f0f0f0'>Investment History</h4>
					<table cellpadding='7' style='width:100%;margin-bottom:10px'> $ltrs </table>".limitDiv($page,10,$totals,"payments.php?vinvest=$ref")."
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client viewed Investments");
	}
	
	# terminate investment
	if(isset($_GET["terminv"])){
		$def = explode(":",trim($_GET["terminv"]));
		$inv = $def[0]; $uid=$def[1];
		
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$sql = $db->query(2,"SELECT `name`,`contact` FROM `org$cid"."_clients` WHERE `id`='$uid'");
		$qri = $db->query(3,"SELECT *FROM `investments$cid` WHERE `id`='$inv'");
		$client=prepare(ucwords($sql[0]['name'])); $fon=$sql[0]['contact']; $amnt=fnum($qri[0]['amount']);
		$cnf = json_decode($qri[0]['details'],1); $iname=prepare($cnf["pname"]); $day=date("M d,Y h:i a",$qri[0]['maturity']);
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:21px;text-align:center'><b>Terminate Investment</b></h3><br>
					<form method='post' id='iform' onsubmit='terminvest(event)'>
						<input type='hidden' name='terminv' value='$inv:$uid'>
						<p>Confirm to Terminate $iname Investment of <b>Ksh $amnt</b> maturing on <b>$day</b></p>
						<div style='width:100%;border:1px solid #ccc;padding:4px;line-height:;margin-top:20px'><input type='number' name='appotp' 
						style='border:0px;padding:4px;width:150px;' placeholder='OTP Code' id='otp' autocomplete='off' required><a href='javascript:void(0)' 
						onclick=\"requestotp('$fon','".str_replace("'","",$client)."')\" style='float:right;margin-top:5px'><i class='fa fa-refresh'></i> Request</a></div><br>
						<br><p style='text-align:right'><button class='sbtn'>Terminate Now</button></p>
					</form>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed $iname investment termination form");
	}
	
	# make investment
	if(isset($_GET["investnow"])){
		$uid = trim($_GET["investnow"]);
		$pid = (isset($_GET["pid"])) ? trim($_GET["pid"]):0;
		$range=$opts=$lis=$itp=""; 
		
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
		$sql = $db->query(2,"SELECT `branch`,`name`,`contact` FROM `org$cid"."_clients` WHERE `id`='$uid'");
		$client=prepare(ucwords($sql[0]['name'])); $fon=$sql[0]['contact']; $bal=($qri) ? round(walletBal($qri[0]['id'],"investbal")):0;
		
		if(!$db->istable(1,"invest_packages")){
			$db->createTbl(1,"invest_packages",["client"=>"INT","name"=>"CHAR","amount"=>"CHAR","periods"=>"TEXT","intervals"=>"INT","status"=>"INT","time"=>"INT"]); 
		}
		
		$lis = "<option value='0'>-- Select --</option>";
		$sql = $db->query(1,"SELECT *FROM `invest_packages` WHERE `client`='$cid' AND `status`='0'");
		if($sql){
			foreach($sql as $row){
				$name=prepare(ucwords($row['name'])); $rid=$row['id']; $prd=json_decode($row['periods'],1);
				$cnd = ($rid==$pid) ? "selected":"";
				$lis.= "<option value='$rid' $cnd>$name</option>";
				
				if($pid==$rid){
					$range=$row['amount']; $dys=$prd['days']; $dur=$prd['duration']; $dy=array_keys($dys)[0]; $all=$dur/$dy; $itp=$row['type'];
					for($i=1; $i<=$all; $i++){
						$tot=round($i*$dy); $cnd=($dur==$tot) ? "selected":"";
						$opts.="<option value='$tot' $cnd>$tot Days</option>"; 
					}
				}
			}
		}
		
		$title = "Create Investment"; $min=time()+(86400*7);
		$opts = ($opts) ? $opts:"<option value='0'>-- Select --</option>";
		$din = "<p>Investment Duration<br><Select style='width:100%' name='dur'>$opts</Select></p>";
			
		if($itp=="Cummulative" && $db->istable(3,"investments$cid")){
			$chk = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='C$uid' AND `status`='0' AND `package`='$pid' AND `maturity`>$min");
			if($chk){
				$title = "Topup Investment"; $din="<input type='hidden' name='dur' value='0'>";
			}
		}
		
		$minv = ($range) ? explode("-",$range)[0]:500;
		$error = ($bal<$minv) ? "Minimum Investment amount is Ksh ".fnum($minv):"";
		if($logtp=="staff"){
			if(isset($_COOKIE["bid"])){
				$bid = explode(":",decrypt($_COOKIE["bid"],"bkey"))[1]; 
				$qri = $db->query(2,"SELECT `config` FROM `org$cid"."_staff` WHERE `jobno`='$bid'");
				$cnf = json_decode($qri[0]["config"],1); $lca=(isset($cnf["lca"])) ? $cnf["lca"]:0;
				if($lca!=$uid){ $error = "Permission Denied! You are not the owner"; }
			}
			else{ $error = "Permission Denied! You are not the owner"; }
		}
		
		$sbtn = ($error) ? "<p style='color:#ff4500;background:#FFDAB9;padding:10px;text-align:center'>
		<i class='bi-exclamation-octagon'></i> $error</p>":"<p style='text-align:right'><button class='sbtn'>Invest Now</button></p>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:20px;text-align:center'><b>$title</b></h3><br>
					<form method='post' id='iform' onsubmit=\"investnow(event)\"> 
						<input type='hidden' name='ivsrc' value='C$uid'> <input type='hidden' name='appreqd' value='$sid'>
						<p>Select Investment Package<br><select style='width:100%' name='pack' onchange=\"window.location.replace('payments.php?investnow=$uid&pid='+this.value)\" required>$lis</Select></p>
						<p>Investment Amount <span style='float:right;color:#2f4f4f;font-size:13px;font-weight:bold'>Balance Ksh ".fnum($bal)."</span><br>
						<input type='number' placeholder='$range' style='width:100%' name='ivamnt' id='amnt' required></p>$din
						<div style='width:100%;border:1px solid #ccc;padding:4px;line-height:;margin-top:20px'><input type='number' name='appotp' 
						style='border:0px;padding:4px;width:150px;' placeholder='OTP Code' id='otp' autocomplete='off' required><a href='javascript:void(0)' 
						onclick=\"requestotp('$fon','".str_replace("'","",$client)."')\" style='float:right;margin-top:5px'><i class='fa fa-refresh'></i> Request</a></div><br>
						<p id='fsp' style='text-align:right'></p><br>$sbtn<br>
					</form>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed investment form");
	}
	
	# make payment
	if(isset($_GET["makepay"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"];
		$sql = $db->query(2,"SELECT `branch`,`name`,`contact` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$client=prepare(ucwords($sql[0]['name'])); $bid=$sql[0]['branch']; $fon=$sql[0]['contact']; $cond=($bid) ? "`id`='$bid'":"NOT `paybill`='0'";
		
		$payb = $db->query(1,"SELECT `paybill` FROM `branches` WHERE $cond")[0]['paybill'];
		$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
		$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
		$ckey = (isset($keys[$payb])) ? $keys[$payb]["ckey"]:""; $csec=(isset($keys[$payb])) ? $keys[$payb]["csec"]:""; 
		$prods=$lns=$sums=[]; $opts="";
		
		$qri = $db->query(2,"SELECT `loan`,`loan_product`,`amount` FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0");
		if($qri){
			$sql = $db->query(1,"SELECT `id`,`product` FROM `loan_products`");
			if($sql){
				foreach($sql as $row){ $prods[$row["id"]]=prepare(ucfirst($row['product'])); }
			}
			foreach($qri as $row){ $lns[$row["loan"]]=$row["loan_product"]; $sums[$row["loan"]]=$row["amount"]; }
		}
		
		if(count($lns)>1){
			foreach($lns as $lid=>$pid){ $opts.="<option value='PLN$lid'>$prods[$pid] (Ksh ".fnum($sums[$lid]).")</option>"; }
			$pls = "<p>Select Loan to Pay<br><select style='width:100%' name='accno'>$opts</select></p>";
		}
		else{
			$accno = ($lns) ? "PLN".array_keys($lns)[0]:$idno;
			$pls = "<input type='hidden' name='accno' value='$accno'>";
		}
		
		$form = ($pkey) ? "<br><input type='hidden' name='paybno' value='$payb'><p>MPESA Phone number<br><input type='number' name='phone' value='0$fon' id='fon'></p>
		$pls<p>Amount to Pay<br><input type='number' name='amnt' id='amnt'></p><br><p><button class='sbtn'>Request Payment</button></p>":
		"<div style='background:#f0f0f0;padding:10px;'><ul><li>Go to MPESA Toolkit</li><li>Select Pay Bill</li><li>Enter Paybill No <b>$payb</b></li>
		<li>Enter Amount & PIN</li><li>ACC No: $idno</li></ul></div>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr><td>
				<div style='padding:10px;max-width:300px;font-family:signika negative;margin:0 auto'>
					<h3 style='font-size:22px;text-align:center'><b>Loan Payment Request</b></h3><br>
					<form method='post' id='pform' onsubmit='makepay(event)'>
						<input type='hidden' name='pkey' value='$pkey'> <input type='hidden' name='ckey' value='$ckey'> <input type='hidden' name='csec' value='$csec'> $form
					</form>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed make payment form");
	}
	
	# transaction details
	if(isset($_GET["trans"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$code = clean($_GET["trans"]); 
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='".$_SESSION["clapp"]."'");
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `code`='$code'");
		$client = prepare(ucwords($sql[0]['name'])); $amnt=$qri[0]['amount']; $acc=prepare($qri[0]['account']); $dy=$qri[0]['date'];
		$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
		$name=prepare($qri[0]['client']); $payb=$qri[0]['paybill']; $day=date("M d, h:i a",$ptm); $trs=$dat=$pdes=""; $tot=$no=0;
		
		if(strpos($code,"MERGED")!==false){
			$sql = $db->query(2,"SELECT *FROM `mergedpayments$cid` WHERE `transaction`='$code'");
			if($sql){
				foreach($sql as $row){
					$cde=$row['code']; $amnt=number_format($row['amount']); $day=date("M d,Y - h:i a",$row['time']); $no++; 
					$trs.= "<tr style='border-bottom:1px solid #dcdcdc'><td>$cde</td><td style='text-align:right'>$amnt</td></tr>"; $tot+=$row['amount'];
				}
		
				$dat = "<table cellpadding='5' style='width:100%'>
					<tr style='font-weight:bold;background:#e6e6fa;color:#191970;font-family:georgia'><td>Transaction</td><td style='text-align:right'>Amount</td></tr>$trs
					<tr><td colspan='2' style='text-align:right'><b>KES ".number_format($tot)."</b></td></tr>
				</table>";
			}
		}
		else{
			if(strpos($code,"OVERPAY")!==false){
				$rid = trim(str_replace("OVERPAY","",$code));
				$sq = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$rid'");
				$overpay = number_format($sq[0]['amount']); $pid=$sq[0]['payid']; 
				$qr = $db->query(2,"SELECT `amount`,`code` FROM `org".$cid."_payments` WHERE `id`='$pid'"); 
				$amnt = number_format($qr[0]['amount']); $pcd=$qr[0]['code'];
			
				$dat = "<table cellpadding='7' style='width:100%'>
					<tr style='border-bottom:1px solid #dcdcdc' valign='top'><td>Initial Amount</td><td style='text-align:right'>KES $amnt<br><span style='color:grey'>$pcd</span></td></tr>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Overpayment</td><td style='text-align:right'>KES $overpay</td></tr>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Paybill</td><td style='text-align:right'>$payb</td></tr>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Account</td><td style='text-align:right'>$acc</td></tr>
					<tr><td>Date Assigned</td><td style='text-align:right'>$day</td></tr>
				</table>";
			}
			else{
				$dat = "<table cellpadding='7' style='width:100%'>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Amount</td><td style='text-align:right'>KES ".number_format($amnt)."</td></tr>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Paybill</td><td style='text-align:right'>$payb</td></tr>
					<tr style='border-bottom:1px solid #dcdcdc'><td>Account</td><td style='text-align:right'>$acc</td></tr>
					<tr style='border-bottom:1px solid #dcdcdc' valign='top'><td>Paid By</td><td style='text-align:right'>$name</td></tr>
					<tr><td>Date</td><td style='text-align:right'>$day</td></tr>
				</table>";
			}
		}
	    
		$sql = $db->query(2,"SELECT GROUP_CONCAT(payment) AS pays,GROUP_CONCAT(amount) AS tsums FROM `processed_payments$cid` WHERE `code`='$code' GROUP BY payid");
		if($sql){
    		$pays = explode(",",$sql[0]["pays"]); $sums=explode(",",$sql[0]["tsums"]); $trs=[]; $lis=""; $no=0;
    		foreach($pays as $key=>$pay){
    			if(isset($trs[$pay])){ $trs[$pay]+=$sums[$key]; }
    			else{ $trs[$pay]=$sums[$key]; }
    		}
    		
    		foreach($trs as $pay=>$sum){
    			$no++; $css = ($no<count($trs)) ? "style='border-bottom:1px solid #dcdcdc'":""; 
    			$lis.= "<tr $css><td>$pay</td><td style='text-align:right'>KES ".number_format($sum)."</td></tr>";
    		}
    		
    		$pdes = "<h3 style='color:#191970;font-size:20px'>Assignment Details</h3>
			<div style='background:#F0F8FF;padding:7px;border:1px dotted #ADD8E6'>
				<table cellpadding='7' style='width:100%'>$lis</table>
			</div><br>";
		}
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:18px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; $code</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative;'>
					<div style='background:#F0F8FF;padding:7px;border:1px dotted #ADD8E6'>$dat</div><br> $pdes
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client viewed payment $code details");
	}

	if(!$data){ echo "<script> window.location.replace('index.php?logout'); </script>"; exit(); }
	ob_end_flush();
?>

	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="robots" content="index,follow"/>
	<title><?php echo $client; ?></title>
	<link rel="shortcut icon" href="<?php echo $path; ?>/docs/img/favicon.ico">
	<script src="<?php echo $path; ?>/assets/js/jquery.js?234"></script>
	<script src='https://code.jquery.com/ui/1.10.0/jquery-ui.js'></script>
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/bootstrap4.css">
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/app.css?<?php echo rand(1234,4321); ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link href="https://fonts.googleapis.com/css?family=Signika Negative&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Acme&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
	<script src="<?php echo $path; ?>/assets/js/bootstrap4.js"></script>
	<script src="<?php echo $path; ?>/assets/js/bootstrap.bundle.js"></script>
	<script src="<?php echo $path; ?>/assets/js/app.js?<?php echo rand(123456,654321); ?>"></script>
	</head>
	<body>
		<?php echo $data; ?>	
	</body>
	</html>
	
	<script>
		
		function transwallet(e,bal){
			e.preventDefault();
			if($("#tamnt").val().trim()==""){ error("Amount is required!"); $("#amnt").focus(); }
			else if($("#tamnt").val().trim()==""){ error("Amount is required!"); $("#amnt").focus(); }
			else if(parseInt($("#tamnt").val().trim())>bal){ error("Insufficient Balance!"); $("#amnt").focus(); }
			else{
				var data = $("#tform").serialize();
				$.ajax({
					method:"post",url:"post.php",data:data,
					beforeSend:function(){ progress("Requesting","Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						success("Success"); window.history.back();
					}
					else{ error(res); }
				});
			}
		}
		
		function terminvest(e){
			e.preventDefault();
			var data = $("#iform").serialize();
			$.ajax({
				method:"post",url:"post.php",data:data,
				beforeSend:function(){ progress("Terminating","Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					success("Success"); window.history.back();
				}
				else{ error(res); }
			});
		}
		
		function makepay(e){
			e.preventDefault();
			if($("#fon").val().length<9){ error("Invalid phone number!"); $("#fon").focus(); }
			else if($("#amnt").val().trim()==""){ error("Amount is required!"); $("#amnt").focus(); }
			else{
				var data = $("#pform").serialize();
				$.ajax({
					method:"post",url:"post.php",data:data,
					beforeSend:function(){ progress("Requesting","Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						success("Success"); window.history.back();
					}
					else{ error(res); }
				});
			}
		}
		
		function investnow(e){
			e.preventDefault();
			if($("#amnt").val().trim()==""){ error("Amount is required!"); $("#amnt").focus(); }
			else{
				var data = $("#iform").serialize();
				$.ajax({
					method:"post",url:"../mfs/dbsave/investors.php",data:data,
					beforeSend:function(){ progress("Processing","Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						success("Investment made successfully"); window.history.back();
					}
					else{ error(res); }
				});
			}
		}
		
		function withdraw(e){
			e.preventDefault();
			if($("#amnt").val().trim()==""){ error("Amount is required!"); $("#amnt").focus(); }
			else{
				var data = $("#wform").serialize();
				$.ajax({
					method:"post",url:"post.php",data:data,
					beforeSend:function(){ progress("Requesting","Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						success("Success"); window.history.back();
					}
					else{ error(res); }
				});
			}
		}
		
	</script>