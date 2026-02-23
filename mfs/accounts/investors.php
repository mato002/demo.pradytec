<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# investors list
	if(isset($_GET["view"])){
		$vtp = intval($_GET["view"]);
		$str = (isset($_GET['str'])) ? trim($_GET['str']):null;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid); $lim=getLimit($page,20); 
		$list=$adds=$done=[];
		
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		$cond = ($vtp) ? "`status`='$vtp'":1;
		$cond.= ($str) ? " AND (`name` LIKE '%$str%' OR `contact`='$str' OR `email` LIKE '%$str%')":"";
		
		if(!$db->istable(2,"investors$cid")){
			$db->createTbl(2,"investors$cid",["name"=>"CHAR","contact"=>"INT","idno"=>"INT","email"=>"CHAR","type"=>"CHAR","config"=>"TEXT","status"=>"INT","time"=>"INT"]); 
		}
		
		$qri = $db->query(2,"SELECT *FROM `investors$cid` WHERE `type`='client' OR `type`='staff'");
		if($qri){
			foreach($qri as $row){
				$df=json_decode($row['config'],1); $done[$df["ref"]]=$row["id"];
			}
		}
		
		if($db->istable(3,"investments$cid")){
			$sql = $db->query(3,"SELECT `client`,GROUP_CONCAT(status) AS states,GROUP_CONCAT(amount) AS tamnt FROM `investments$cid` GROUP BY `client`");
			if($sql){
				foreach($sql as $row){
					$vid=$row['client']; $states=explode(",",$row['states']); $tots=explode(",",$row['tamnt']); $act=$exp=0;
					foreach($states as $key=>$st){ $exp+=($st) ? $tots[$key]:0; $act+=($st==0) ? $tots[$key]:0; }
					$sta=(in_array(0,$states)) ? 1:0; $list[$vid]=array($sta,$act,$exp);
					if(substr($vid,0,1)=="C" && !isset($done[$vid])){ $adds[$vid]="client"; }
					if(substr($vid,0,1)=="S" && !isset($done[$vid])){ $adds[$vid]="staff"; }
				}
			}
		}
		
		foreach($adds as $vid=>$tp){
			$id = substr($vid,1);
			if($tp=="client"){
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$id'");
				$row = $sql[0]; $idno=$row['idno']; $name=$row['name']; $fon=$row['contact']; $st=$list[$vid][0]; $tym=time();
				$jsn = json_encode(["key"=>encrypt("0$fon",date("dmY-H:i",$tym)),"lastreset"=>0,"profile"=>"none","ref"=>$vid],1);
				$db->execute(2,"INSERT INTO `investors$cid` VALUES(NULL,'$name','$fon','$idno','','client','$jsn','$st','$tym')");
			}
			else{
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$id'");
				$row = $sql[0]; $idno=$row['idno']; $name=$row['name']; $fon=$row['contact']; $st=$list[$vid][0]; $tym=time();
				$def = json_decode($row["config"],1); $utm=$row['time']; $dpass=decrypt($def['key'],date("YMd-his",$utm)); $mail=$row['email'];
				$jsn = json_encode(["key"=>encrypt($dpass,date("dmY-H:i",$tym)),"lastreset"=>0,"profile"=>$def["profile"],"ref"=>$vid],1);
				$db->execute(2,"INSERT INTO `investors$cid` VALUES(NULL,'$name','$fon','$idno','$mail','staff','$jsn','$st','$tym')");
			}
		}
		
		$no = (20*$page)-20; $trs="";
		$sql = $db->query(2,"SELECT *FROM `investors$cid` WHERE $cond ORDER BY `name` ASC $lim");
		if($sql){
			foreach($sql as $row){
				$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $idno=$row['idno']; $mail=$row['email']; $st=$row['status']; $rid=$row['id']; $no++;
				$def = json_decode($row['config'],1); $ref=$def["ref"]; $act=(isset($list[$ref])) ? $list[$ref][1]:0; $exp=(isset($list[$ref])) ? $list[$ref][2]:0;
				if(isset($list[$ref])){
					if($list[$ref][0]!=$st){ $st=$list[$ref][0]; $db->execute(2,"UPDATE `investors$cid` SET `status`='$st' WHERE `id`='$rid'"); }
				}
				
				$state = ($st) ? "<span style='color:green'><i class='fa fa-circle'></i> Active</span>":"<span style='color:purple'><i class='fa fa-circle'></i> Inactive</span>";
				$pages = array("client"=>"clients","staff"=>"hr/staff","investor"=>"accounts/investors"); $purl=$pages[$row["type"]]; $wid=substr($ref,1);
				$trs.= "<tr onclick=\"loadpage('$purl.php?wallet=$wid&vtp=investbal&vop=1')\"><td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td>
				<td>$mail</td><td>Ksh ".fnum($act)."</td><td>Ksh ".fnum($exp)."</td><td>$state</td></tr>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS tot FROM `investors$cid` WHERE $cond");
		$def = array("-- Filter --","Active","Inactive"); $totals=($sql) ? intval($sql[0]['tot']):0; $opts="";
		$trs = ($trs) ? $trs:"<tr><td colspan='8'>No $def[$vtp] records found</td></tr>";
		
		foreach($def as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$txt</option>";
		}
		
		$add = (in_array("create investor account",$perms)) ? "<button class='bts' style='float:right;font-size:15px;padding:4px' onclick=\"popupload('accounts/investors.php?addinv')\">
		<i class='fa fa-plus'></i> Investor</button>":"";
		echo "<div class='cardv' style='max-width:1300px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Investors List $add</h3><hr>
				<p><select style='padding:7px;font-size:15px;width:120px' onchange=\"loadpage('accounts/investors.php?view='+this.value)\">$opts</Select>
				<input type='search' style='width:150px;padding:5px 8px;font-size:15px' placeholder='Search' value=\"$str\"
				onkeyup=\"fsearch(event,'accounts/investors.php?view=$vtp&str='+cleanstr(this.value))\" onsearch=\"loadpage('accounts/investors.php?view=$vtp&str='+cleanstr(this.value))\"></p>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped stbl' style='width:100%;font-size:15px;min-width:600px' cellpadding='5'>
						<tr style='font-weight:bold;color:#191970;background:#e6e6fa;font-size:14px'><td colspan='2'>Name</td><td>Contact</td><td>Idno</td><td>Email</td>
						<td>Active Inv.</td><td>Completed Inv.</td><td>Status</td></tr> $trs
					</table>
				</div>
				<br>".getLimitDiv($page,20,$totals,"accounts/investors.php?view=$vtp&str=".urlencode($str))."
			</div>
		</div>";
	}
	
	# add investor
	if(isset($_GET["addinv"])){
		$rid = intval($_GET["addinv"]);
		$name=$idno=$fon=$mail="";
		
		if($rid){
			$sql = $db->query(2,"SELECT *FROM `investors$cid` WHERE `id`='$rid'");
			$row = $sql[0]; $name=prepare($row['name']); $idno=$row['idno']; $fon=$row['contact']; $mail=$row['email'];
		}
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;font-size:22px'>Create Investor Account</h3><br>
			<form method='post' id='ivfom' onsubmit=\"saveinv(event)\">
				<input type='hidden' name='invid' value='$rid'>
				<p>Full Name<br><input type='text' name='ivname' value=\"$name\" style='width:100%' required></p>
				<p>Phone Number<br><input type='number' name='ivfon' value=\"$fon\" style='width:100%' required></p>
				<p>ID/Passport Number<br><input type='number' name='ividno' value=\"$idno\" style='width:100%' required></p>
				<p>Email Address<br><input type='email' name='ivmail' value=\"$mail\" style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Create</button></p><br>
			</form>
		</div>";
	}
	
	# invest
	if(isset($_GET["invest"])){
		$uid = trim($_GET["invest"]);
		$pid = (isset($_GET["pid"])) ? trim($_GET["pid"]):0;
		$range=$opts=$lis=$itp=""; 
		
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
			$chk = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='$uid' AND `status`='0' AND `package`='$pid' AND `maturity`>$min");
			if($chk){
				$title = "Topup Investment"; $din="<input type='hidden' name='dur' value='0'>";
			}
		}
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;font-size:22px'>$title</h3><br>
			<form method='post' id='iform' onsubmit=\"investnow(event)\">
				<input type='hidden' name='ivsrc' value='$uid'>
				<p>Select Package<br><select style='width:100%' name='pack' onchange=\"popupload('accounts/investors.php?invest=$uid&pid='+this.value)\" required>$lis</Select></p>
				<p>Investment Amount<br><input type='number' placeholder='$range' style='width:100%' name='ivamnt' required></p>$din<br>
				<p style='text-align:right'><button class='btnn'>Invest</button></p><br>
			</form>
		</div>";
	}
	
	
	# packages
	if(isset($_GET["packs"])){
		$me = staffInfo($sid); $trs=""; $no=0;
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		if(!$db->istable(1,"invest_packages")){
			$db->createTbl(1,"invest_packages",["client"=>"INT","name"=>"CHAR","type"=>"CHAR","amount"=>"CHAR","periods"=>"TEXT","intervals"=>"INT","status"=>"INT","time"=>"INT"]); 
		}
		else{
			$cols = $db->tableFields(1,"invest_packages");
			if(!in_array("type",$cols)){ $db->execute(1,"ALTER TABLE `invest_packages` ADD `type` VARCHAR(255) NOT NULL AFTER `name`"); }
		}
		
		$list = array("At Maturity","January","February","March","April","May","June","July","August","Septemper","October","November","December");
		$sql = $db->query(1,"SELECT *FROM `invest_packages` WHERE `client`='$cid' ORDER BY `name` ASC");
		if($sql){
			foreach($sql as $row){
				$name=prepare(ucfirst($row["name"])); $amnt=explode("-",$row['amount']); $cyc=$row['intervals']; $rid=$row['id']; $st=$row['status'];
				$def=json_decode($row['periods'],1); $dur=$def["duration"]; $wdays=$def["wdays"]; $wmon=$def["wmon"]; $no++; $lis="";
				$pcyc = ($cyc==1) ? "Everyday":"Every $cyc days"; $ptp=prepare($row["type"]);
				foreach($def["days"] as $day=>$perc){ $lis.= "<li>$day Days - $perc%</li>"; }
				
				$state = ($st) ? "<span style='color:#FF8C00'><i class='fa fa-circle'></i> Inactive</span>":
				"<span style='color:green'><i class='fa fa-circle'></i> Active</span>"; $txt=($st) ? "Activate":"Deactivate";
				
				$act = (in_array("manage investment packages",$perms)) ? "<a href='javascrript:void(0)' onclick=\"popupload('accounts/investors.php?addpack=$rid')\">
				<i class='bi-pencil-square'></i> Edit</a><br><a href='javascrript:void(0)' style='color:#4B0082' onclick=\"packstate('$rid','$st')\"><i class='bi-command'></i> $txt</a>":"N/A";
				$trs.= "<tr valign='top' id='p$rid'><td>$no</td><td>$name<br><span style='font-size:14px'><b>$dur days</b></span></td><td>Ksh ".fnum($amnt[0])."-".fnum($amnt[1])."<br>
				<span style='font-size:14px;color:purple'><b>$ptp</b></span></td><td>$lis</td><td>$pcyc</td><td>$wdays<br><span style='font-size:14px;color:#008080'>$list[$wmon]</span></td>
				<td>$state</td><td>$act</td></tr>";
			}
		}
		
		$add = (in_array("manage investment packages",$perms)) ? "<button class='bts' style='float:right' onclick=\"popupload('accounts/investors.php?addpack')\">
		<i class='bi-plus-lg'></i> Package</button>":"";
		
		echo "<div class='cardv' style='max-width:1100px;overflow:auto;min-height:300px;'>
			<h3 style='color:#191970;font-size:22px;padding-top:5px'>$backbtn Investment Packages $add</h3>
			<div style='width:100%;overflow:auto;margin-top:20px'>
				<table cellpadding='6' style='width:100%;font-size:15px;min-width:700px' class='table-striped'>
					<tr style='font-weight:bold;color:#191970;background:#e6e6fa;'><td colspan='2'>Package</td><td>Amounts</td><td>Returns</td><td>ROI</td>
					<td>Withdrawal Dates</td><td>Status</td><td>Action</td></tr> $trs
				</table>
			</div>
		</div>";
		savelog($sid,"Viewed Investment Packages");
	}
	
	# add/edit package
	if(isset($_GET["addpack"])){
		$pid = trim($_GET["addpack"]);
		$name=$min=$max=$trs=$dur=$tp=$lis=$dls1=$dls2=$pls=$ptp=""; 
		$no=$wmon=0; $dys=[30=>4]; $pdays=360; $wdays=[5,20];
		
		if($pid){
			$sql = $db->query(1,"SELECT *FROM `invest_packages` WHERE `id`='$pid'");
			$row = $sql[0]; $name=prepare(ucfirst($row['name'])); $amnt=explode("-",$row['amount']); $min=$amnt[0]; $max=$amnt[1];
			$period=json_decode($row['periods'],1); $dys=$period["days"]; $pdays=$period['duration']; $wdays=explode("-",$period['wdays']); 
			$wmon=$period['wmon']; $dur=$row['intervals']; $ptp=$row['type'];
		}
		
		$title = ($pid) ? "Edit Investment Package":"Create Investment Package";
		foreach($dys as $dy=>$perc){
			$rid=rand(12345678,99999999); $tde=($no) ? "<td><i class='bi-x-lg' style='cursor:pointer;color:#ff4500' onclick=\"delrow('$rid')\"></i></td>":"<td></td>"; $no++;
			$trs.= "<tr id='tr$rid'><td><input type='number' style='width:100%' name='days[]' value='$dy' required></td>
			<td><input type='text' style='width:100%' name='perc[]' id='p$rid' onkeyup=\"valid('p$rid',this.value)\" value='$perc' required></td>$tde</tr>";
		}
		
		for($i=1; $i<=31; $i++){
			$cnd1 = ($i==$wdays[0]) ? "selected":"";
			$cnd2 = ($i==$wdays[1]) ? "selected":"";
			$dls1.= "<option value='$i' $cnd1>$i</option>";
			$dls2.= "<option value='$i' $cnd2>$i</option>";
		}
		
		$list = array("At Maturity","January","February","March","April","May","June","July","August","Septemper","October","November","December");
		foreach($list as $key=>$txt){
			$cnd = ($key==$wmon) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("Fixed Deposit","Cummulative") as $one){
			$cnd = ($one==$ptp) ? "selected":"";
			$pls.= "<option value='$one' $cnd>$one</option>";
		}
		
		echo "<div style='max-width:400px;padding:10px;margin:0 auto'><br>
			<div style='max-width:320px;margin:0 auto;font-family:cambria'>
				<h4 style='color:#2f4f4f;font-weight:bold;text-align:center'>$title</h4><br>
				<form method='post' id='pfom' onsubmit='savepack(event)'>
					<input type='hidden' name='packid' value='$pid'>
					<p>Package Name <span style='float:right'>Investment Type</span><br><input style='width:48%' type='text' name='pname' value='$name' required>
					<select name='ptype' style='width:48%;float:right'>$pls</Select></p>
					<p>Period (Days) <span style='float:right'>Payment Intervals</span><br><input style='width:48%' type='number' name='idur' value='$pdays' required>
					<input type='number' name='pdur' value='$dur' style='width:48%;float:right' required></p>
					<p>Min Amount <span style='float:right'>Max Amount</span><br><input style='width:48%' type='number' name='pamnt[min]' value='$min' required>
					<input style='width:48%;float:right' type='number' name='pamnt[max]' value='$max' required></p>
					<table style='width:100%' cellpadding='3'><tr><td style='width:47%'>Capital Withdrawal</td><td colspan='3'>Withdrawal Dates</td></tr>
					<tr><td><select style='width:100%' name='wmon'>$lis</select></td><td><select name='wdays[fro]' style='width:100%'>$dls1</select></td>
					<td style='width:20px'>To</td><td><select name='wdays[dto]' style='width:100%;float:right'>$dls2</select></td></tr></table><br>
					<p style='margin:0px'>Investment Returns</p><table style='width:100%' cellpadding='5' class='ptbl'>
					<tr style='font-weight:bold'><td>No of Days</td><td>% Returns</td></tr> $trs</table><br>
					<p style='text-align:right;'><button class='btnn'>Save</button></p>
				</form>
			</div>
		</div>";
	}
	
	# investor wallet account
	if(isset($_GET["wallet"])){
		$uid = trim($_GET["wallet"]);
		$page = (isset($_GET["pg"])) ? trim($_GET["pg"]):1;
		$vop = (isset($_GET["vop"])) ? trim($_GET["vop"]):0;
		$fro = (isset($_GET["fro"])) ? strtotime($_GET["fro"]):0;
		$dto = (isset($_GET["dto"])) ? strtotime($_GET["dto"]):strtotime(date("Y-M-d"));
		$vtp = (isset($_GET["vtp"])) ? trim($_GET["vtp"]):"balance";
		$staff[0]="System"; $tmon=strtotime(date("Y-M")); $trs=$lis=""; 
		$sttl = (defined("SAVINGS_TERMS")) ? SAVINGS_TERMS["acc"]:"Savings";
		$rcons = sys_constants("wallet_revtrans_time"); $revtm=($rcons) ? $rcons*86400:3*86400;
		
		$dfro = ($dto<$fro) ? $dto:$fro; $dtu=($dto<$fro) ? $fro:$dto; $dtu+=86399;
		$me = staffInfo($sid); $lim=getLimit($page,20); $wid=createWallet("investor",$uid);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		
		$sql = $db->query(2,"SELECT *FROM `investors$cid` WHERE `id`='$uid'");
		$cname = prepare(ucwords($sql[0]["name"])); $wbal=walletBal($wid,$vtp);
		$ttls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>$sttl);
		$title = $ttls[$vtp]." Account"; $vbk=$ttls[$vtp]; 
		
		if(!$db->istable(3,"walletrans$cid")){
			$db->createTbl(3,"walletrans$cid",["tid"=>"CHAR","wallet"=>"INT","transaction"=>"CHAR","book"=>"CHAR","amount"=>"INT","details"=>"CHAR","afterbal"=>"CHAR",
			"approval"=>"INT","status"=>"INT","reversal"=>"LONGTEXT","time"=>"INT"]);
		}
		
		$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
		$invtbl = ($db->istable(3,"investments$cid")) ? 1:0;
		
		if($vop){
			$ths = "<td>Date</td><td>Investment</td><td>Duration</td><td>Payouts</td><td>Maturity</td><td>Status</td><td>Balance</td>";
			if($invtbl){
				$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='I$uid' ORDER BY `time` DESC");
				if($sql){
					foreach($sql as $row){
						$dy=date("d-m-Y,H:i",$row['time']); $def=json_decode($row['details'],1); $amnt=fnum($row['amount']); $pack=prepare($def["pname"]);
						$dur=$row['period']; $paid=fnum($def["payouts"]); $bal=$def["balance"]; $exp=date("d-m-Y, H:i",$row['maturity']);
						$css = "font-size:13px;color:#fff;padding:4px;font-family:signika negative"; $st=($row['status']>200) ? 200:$row['status'];
						$states = array(0=>"<span style='background:green;$css'>Active</span>",200=>"<span style='background:#20B2AA;$css'>Matured</span>",
						15=>"<span style='background:orange;$css'>Terminated</span>"); $rid=$row['id']; $wvtx=($row["maturity"]>time()) ? "Terminate":"Withdraw";
						$toa = (in_array("terminate investment",$perms) && $bal>0) ? "<a href='javascript:void(0)' onclick=\"withdrawinv('$rid','$wvtx')\">
						<i class='bi-download'></i> $wvtx</a>":"";
						
						$trs.= "<tr valign='top'><td>$dy</td><td><b>Ksh $amnt</b><br>$pack</td><td>$dur Days</td><td>Ksh $paid</td><td>$exp</td><td>$states[$st]</td>
						<td>Ksh ".fnum($bal)."<br>$toa</td></tr>";
					}
				}
			}
		}
		else{
			if(!in_array("reversal",$db->tableFields(3,"walletrans$cid"))){
				$db->execute(3,"ALTER TABLE `walletrans$cid` ADD `reversal` LONGTEXT NOT NULL AFTER `status`"); 
				$db->execute(3,"UPDATE `walletrans$cid` SET `reversal`='norev'");
			}
			
			$ths = "<td>Date</td><td>TID</td><td>Details</td><td>Approval</td><td style='text-align:right'>Amount</td><td style='text-align:right'>Bal After</td><td></td>";
			$sql = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk' AND `time` BETWEEN $dfro AND $dtu ORDER BY `time` DESC,`id` DESC $lim");
			if($sql){
				foreach($sql as $row){
					$tm=$row['time']; $dy=date("d-m-Y,H:i",$tm); $tid=$row['tid']; $appr=$staff[$row['approval']]; $amnt=fnum($row['amount']); $rid=$row['id']; $tdf=time()-$tm;
					$bal=fnum($row['afterbal']); $css="text-align:right"; $des=prepare(ucfirst($row['details'])); $sgn=($row["transaction"]=="debit") ? "+":"-"; 
					$rev = ($row["reversal"]!="norev" && in_array("reverse wallet transaction",$perms) && $row['status']==0 && $tdf<$revtm) ? "<a href='javascript:void(0)' 
					onclick=\"revtrans('$rid')\" style='cursor:pointer'><i class='bi-bootstrap-reboot'></i> Reverse</a>":""; $css1=($row['status']) ? "color:#B0C4DE":"";
					$desc=(strlen($des)>30) ? substr($des,0,30)."...<i title='View More' class='fa fa-plus' style='color:#008fff;cursor:pointer' onclick=\"setcontent('$rid','$des')\"></i>":$des;
					$trs.= "<tr valign='top'><td>$dy</td><td>$tid</td><td id='tr$rid'>$desc</td><td>$appr</td><td style='$css'>$sgn$amnt</td>
					<td style='$css'><b>$bal</b></td><td>$rev</td></tr>";
				}
			}
		}
		
		$qri = $db->query(3,"SELECT COUNT(*) AS tot,MIN(time) AS mnt FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk'");
		$qry = $db->query(3,"SELECT SUM(amount) AS tsum FROM `walletrans$cid` WHERE `wallet`='$wid' AND `transaction`='credit' AND `book`='$vbk' AND `status`='0' AND NOT `details` LIKE '[Reversal]%'");
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $tots=intval($qri[0]['tot']); $twd=intval($qry[0]['tsum']); $tdy=date("Y-m-d");
		$mnt = ($qri) ? $qri[0]['mnt']:$tmon; $dfro=($dfro) ? $dfro:$mnt; $dftm=date("Y-m-d",$dfro); $dtm=date("Y-m-d",$dtu);
		$bid = $db->query(1,"SELECT `id` FROM `branches` WHERE `status`='0' LIMIT 1")[0]["id"];
		$accno = walletAccount($bid,$wid,$vtp);
		
		$roibal = (in_array($vtp,["balance","savings"])) ? $twd:0; $switch="";
		$prnt = ($trs) ? "<button class='bts' style='float:right;padding:3px 6px' onclick=\"printdoc('reports.php?vtp=wallets&mn=$dfro:$dtu&yr=$wid&src=$vtp&vp=$vop','wallet')\">
		<i class='bi-printer'></i> Print</button>":"";
		$trs = ($trs) ? $trs:"<tr><td colspan='5'>No Transactions Found</td></tr>";
		$wht = (in_array($vtp,["balance","savings"])) ? "Withdrawals":"Invested Balance";
		
		if(in_array($vtp,["balance","savings"])){
			$vtitle = "Transaction List";
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "popupload('payments.php?wfrom=$wid&wtp=$vtp&uid=$uid:investor')":"toast('Permission Denied!')";
			$wbtn = (in_array("manage wallet withdrawals",$perms)) ? "<button class='btnn' onclick=\"popupload('payments.php?wfrom=$wid&wtp=real&uid=$uid:investor')\" 
			style='background:#BA55D3;font-size:14px;padding:4px;margin-top:10px'><i class='bi-download'></i> Withdraw</button>":"";
		}
		else{
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "transwallet('$wid','$wbal')":"toast('Permission Denied!')";
			$wbtn = (in_array("create investment",$perms)) ? "<button class='btnn' onclick=\"popupload('accounts/investors.php?invest=I$uid')\" 
			style='background:#20B2AA;font-size:14px;padding:4px;margin-top:10px'><i class='bi-bag-plus'></i> Invest</button>":""; $vopt="";
			foreach(array("Transactions","Investments") as $key=>$txt){
				$cnd = ($key==$vop) ? "selected":"";
				$vopt.= "<option value='$key' $cnd>$txt</selected>";
			}
			
			$vtitle = ($vop) ? "Investments List":"Transactions List";
			$switch = "<select style='width:130px;padding:4px;float:right;font-size:15px' onchange=\"loadpage('accounts/investors.php?wallet=$uid&vtp=$vtp&vop='+this.value)\">$vopt</select>";
		}
		
		foreach($ttls as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$txt Account</option>";
		}
		
		if($invtbl && $vtp=="investbal"){
			$sql = $db->query(3,"SELECT SUM(amount) AS tsum FROM `investments$cid` WHERE `client`='I$uid' AND `status`='0'");
			$roibal = ($sql) ? intval($sql[0]['tsum']):0;
		}
		
		$edit = (in_array("create investor account",$perms)) ? "<i style='font-size:20px;cursor:pointer;color:#008fff;float:right' class='bi-pencil-square' title='Edit $cname'
		onclick=\"popupload('accounts/investors.php?addinv=$uid')\"></i>":"";
		
		echo "<div class='container cardv' style='max-width:1300px;background:#fff;overflow:auto;padding:10px 0px'>
			<div class='row' style='margin:0px'>
				<div class='col-12 col-md-12 mb-2'>
					<h3 style='color:#191970;font-size:20px;font-weight:bold'>$backbtn $title</h3>
				</div>
				<div class='col-12 col-md-12 col-lg-4 col-xl-3  mb-2'>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<p style='padding:10px;font-weight:bold;background:#f0f0f0;margin:0px'><i class='fa fa-user'></i> $cname $edit</p>
						<div style='padding:7px;text-align:center;color:#191970;background:#B0C4DE;margin-bottom:15px'>
						<select style='width:100%;background:transparent;border:0px;cursor:pointer'onchange=\"loadpage('accounts/investors.php?wallet=$uid&vtp='+this.value)\">$lis</select></div>
						<div style='color:#fff;background:#4682b4;padding:10px;border-radius:5px;font-Family:signika negative'>
							<table style='width:100%'><tr>
								<td><h3><span style='font-size:13px'>KES</span> ".fnum($wbal)."<br><span style='font-size:13px;color:#f0f0f0'>Available Balance</span></h3></td>
								<td style='text-align:right'><button class='btnn' style='font-size:14px;padding:4px' onclick=\"popupload('payments.php?wtopup=$wid&wtp=$vtp&uid=$uid:investor')\">
								<i class='bi-plus-circle'></i> Deposit</button></td>
							</tr><tr><td colspan='2' style='color:#00FFFF;font-size:14px;'><hr style='margin-top:0px' color='#ccc'>AccNo: $accno
							<button class='btnn' onclick=\"$trans\" style='background:#20B2AA;font-size:14px;padding:4px;float:right'><i class='bi-arrow-left-right'></i> Transfer</button></td></tr></table>
						</div>
						<div style='color:#fff;background:#2F4F4F;padding:10px;border-radius:5px;font-Family:signika negative;margin-top:10px'>
							<table style='width:100%'><tr>
								<td><h3><span style='font-size:13px'>KES</span> ".fnum($roibal)."<br><span style='font-size:13px;color:#ccc'>$wht</span></h3></td>
								<td style='text-align:right'>$wbtn</td>
							</tr></table>
						</div><br>
					</div>
				</div>
				<div class='col-12 col-md-12 col-lg-8 col-xl-9'>
					<h3 style='color:#191970;font-size:18px;font-weight:bold'>$vtitle $switch</h3>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<table cellpadding='5' class='table-striped' style='border-collapse:collapse;width:100%;min-width:550px;font-size:15px'>
							<caption style='caption-side:top'>
								<input type='date' value='$dftm' max='$tdy' style='width:160px;font-size:15px;padding:4px' id='fro' onchange='setrange()'> ~
								<input type='date' value='$dtm' max='$tdy' style='width:160px;font-size:15px;padding:4px' id='dto' onchange='setrange()'> $prnt
							</caption>
							<tr style='color:#191970;font-weight:bold;font-size:14px;background:#e6e6fa'>$ths</tr> $trs
						</table><br>".getLimitDiv($page,20,$tots,"accounts/investors.php?wallet=$uid&vtp=$vtp&fro=$dftm&dto=$dtm&vop=$vop")."
					</div>
				</div>
			</div><br>
		</div>
		<script>
			function setrange(){
				var fro = $('#fro').val(), dto=$('#dto').val(); 
				loadpage('accounts/investors.php?wallet=$uid&vtp=$vtp&fro='+fro+'&dto='+dto);
			}
			function setcontent(id,des){ $('#tr'+id).html(des); }
		</script>";
		savelog($sid,"Viewed $cname investor account wallet");
	}
	
	ob_end_flush();
?>

<script>

	function addrow(){
		var did = Date.now();
		$(".ptbl").append("<tr valign='bottom' id='tr"+did+"'><td><input type='number' style='width:100%' name='days[]' required></td>"+
		"<td><input type='number' style='width:100%' name='perc[]' required></td>"+
		"<td><i class='bi-x-lg' style='color:#ff4500;cursor:pointer' onclick=\"delrow('"+did+"')\"></i></td></tr>");
	}
	
	function withdrawinv(inv,txt){
		if(confirm("Sure to "+txt+" Investment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:{withdrawinv:inv},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function revtrans(rid){
		if(confirm("Sure to reverse Account transaction?")){
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:{revtrans:rid},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function transwallet(wid,amt){
		if(amt==0){ toast("Insufficient Balance!"); }
		else{
			var amnt = prompt("Enter Amount to Transfer to Transactional Account",amt);
			if(amnt){
				if(confirm("Sure to transfer Ksh "+amnt+" to Transactional Account?")){
					$.ajax({
						method:"post",url:path()+"dbsave/payments.php",data:{transwid:wid,transfro:"investbal",transto:"balance",tamnt:amnt},
						beforeSend:function(){ progress("Processing...please wait"); },
						complete:function(){ progress(); }
					}).fail(function(){
						toast("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim()=="success"){ closepop(); window.location.reload(); }
						else{ alert(res); }
					});
				}
			}
		}
	}
	
	function delrow(id){ $("#tr"+id).remove(); }
	function savepack(e){
		e.preventDefault();
		if(confirm("Save Investment package?")){
			var data = $("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				alert("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Saved successfully!"); closepop(); fetchpage("accounts/investors.php?packs"); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveinv(e){
		e.preventDefault();
		if(confirm("Proceed to save Investor Info?")){
			var data = $("#ivfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				alert("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Saved successfully!"); closepop(); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function investnow(e){
		e.preventDefault();
		if(confirm("Proceed to create Investment?")){
			var data = $("#iform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				alert("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Created successfully!"); closepop(); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function packstate(id,val){
		var txt = (val>0) ? "Activate":"Deactivate";
		if(confirm("Proceed to "+txt+" Investment package?")){
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:{packstate:id,sval:val},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				alert("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success!"); loadpage("accounts/investors.php?packs");
				}
				else{ alert(res); }
			});
		}
	}

</script>