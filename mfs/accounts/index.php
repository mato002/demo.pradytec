<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	#paybill transfers
	if(isset($_GET['paybills'])){
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,50); $linked=[];
		
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qri){
			foreach($qri as $row){
				$bnames[$row['paybill']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT COUNT(*) AS total FROM `paybill_transfers$cid` WHERE `status`='0'");
		$totals = ($res) ? $res[0]['total']:0; $trs =""; $no=(50*$page)-50;
		
		$qry = $db->query(1,"SELECT *FROM `paybill_banks` WHERE `client`='$cid'");
		if($qry){
			foreach($qry as $row){ $linked[]=$row['paybill']; }
		}
	
		$res = $db->query(2,"SELECT *FROM `paybill_transfers$cid` WHERE `status`='0' $lim");
		if($res){
			foreach($res as $row){
				$payb=$row['paybill']; $amnt=number_format($row['amount']); $day=date("M d,Y - h:i a",$row['time']); 
				$rid=$row['id']; $post=$del="----"; $no++; 
				
				if(in_array("post journal entry",$perms)){
					$post = (in_array($payb,$linked)) ? "<a href='javascript:void(0)' onclick=\"savetransfer('$rid')\">Post Transaction</a>":"<i>Unlinked</i>";
					$del = "<a href='javascript:void(0)' style='color:#DC143C' onclick=\"delpayb('$rid')\">Delete</a>";
				}
				
				$trs.="<tr id='tr$rid'><td>$no</td><td>$payb</td><td>".$bnames[$payb]."</td><td>$day</td><td>$amnt</td><td>$post</td><td>$del</td></tr>";
			}
		}
		
		$payset = (in_array("create chart of account",$perms)) ? "<button class='bts' style='padding:4px;float:right' onclick=\"loadpage('accounts/index.php?paybsett')\">
		<i class='fa fa-cog'></i> Settings</button>":"";
		
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:900px'>
		<h3 style='font-size:22px;color:#191970'>$backbtn Paybill Withdrawals $payset</h3>
			<table style='width:100%;font-size:15px;min-width:500px' class='table-bordered table-striped' cellpadding='5'>
				<caption style='caption-side:top'> <b>Note:</b> Some records may not be accurate since this relies on precision 
				tracking of the paybill balance once a payment reflects in the system</caption>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA;'><td colspan='2'>Paybill</td><td>Branch</td><td>Date</td>
				<td>Amount</td><td colspan='2'>Action</td></tr> $trs
			</table><br>".getLimitDiv($page,50,$totals,"accounts/index.php?paybills")."
		</div>";
		
		savelog($sid,"Accessed Paybill Money Transfers form");
	}
	
	# paybill settings
	if(isset($_GET['paybsett'])){
		$trs =""; $no=0;
		$qri = $db->query(3,"SELECT *FROM `accounts$cid`");
		foreach($qri as $row){ $accs[$row['id']]=prepare($row['account']); }
		
		$res = $db->query(1,"SELECT pb.*,br.branch FROM `paybill_banks` AS pb INNER JOIN `branches` AS br ON pb.paybill=br.paybill WHERE pb.client='$cid'");
		if($res){
			foreach($res as $row){
				$payb=$row['paybill']; $pid=$row['linked']; $bid=$row['bank']; $rid=$row['id']; $bname=prepare(ucwords($row['branch'])); $no++;
				$trs.="<tr><td>$no</td><td>$payb</td><td>$bname</td><td>$accs[$bid]</td><td>
				<a href='javascript:void(0)' onclick=\"popupload('accounts/index.php?addpayb=$rid')\"><i class='fa fa-pencil'></i> Edit</a></td></tr>";
			}
		}
		
		echo "<div class='cardv' style='overflow:auto;min-height:200px;max-width:700px'>
			<h3 style='font-size:22px;color:#191970'>$backbtn Paybill Settings</h3><hr>
			<p style='color:grey;font-size:15px'>Setup paybill with their corresponding Banks where they are withdrawned to. <b>Note:</b> you must 
			have added the paybill to Charts of Account as an Asset</p>
			<table style='width:100%;font-size:15px;min-width:500px' class='table-bordered table-striped' cellpadding='5'>
				<caption style='caption-side:top;'>
					<button class='bts' style='padding:4px;float:right' onclick=\"popupload('accounts/index.php?addpayb=0')\"><i class='bi-bag-plus'></i> Paybill</button>
					<h3 style='color:#191970;font-size:20px'>Current Setups</h3>
				</caption>
				<tr style='font-weight:bold;font-size:15px;background:#e6e6fa;color:#191970'><td colspan='2'>Paybill</td><td>Branch</td>
				<td colspan='2'>Bank Name</td></tr> $trs
			</table><br>
		</div>";
		
		savelog($sid,"Accessed Paybill Setup Settings");
	}
	
	# manage paybill setup
	if(isset($_GET['addpayb'])){
		$pid = trim($_GET['addpayb']); 
		$prev1=$prev2=[]; $lis1=$lis2=$opts=$pay="";
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='asset' AND `level`>0 AND `id`>35");
		foreach($qri as $row){ $accs[$row['id']]=prepare($row['account']); }
		
		$qry = $db->query(1,"SELECT *FROM `paybill_banks` WHERE `client`='$cid'");
		if($qry){
			foreach($qry as $row){ $prev1[]=$row['linked']; $prev2[]=$row['bank']; $prev3[]=$row['paybill']; }
		}
		
		if($pid){
			$res = $db->query(1,"SELECT *FROM `paybill_banks` WHERE `id`='$pid'");
			$row = $res[0]; $payb=$row['linked']; $bid=$row['bank']; $pay=$row['paybill']; $prev3=[];
			$lis1 = "<option value='$payb'>$accs[$payb]</option>";
			foreach($accs as $id=>$name){
				$cnd = ($id==$bid) ? "selected":"";
				$lis2.=(!in_array($id,$prev1)) ? "<option value='$id' $cnd>$name</option>":"";
			}
		}
		else{
			foreach($accs as $id=>$name){
				$lis1.=(!in_array($id,$prev1) && !in_array($id,$prev2)) ? "<option value='$id'>$name</option>":"";
				$lis2.=(!in_array($id,$prev1)) ? "<option value='$id'>$name</option>":"";
			}
		}
		
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		foreach($sql as $row){
			$pb=$row['paybill']; $cnd=($pb==$pay) ? "selected":"";
			$opts.=(!in_array($pb,$prev3)) ? "<option value='$pb'>$pb - ".ucwords(prepare($row['branch']))."</option>":"";
		}
		
		$mxto = date("Y-m-d"); $fd=date("Y-m-d",time()-86400);
		echo "<div style='margin:0 auto;max-width:300px;padding:10px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Paybill-Bank Linking</h3><br>
			<form method='post' id='bform' onsubmit='savebank(event)'>
				<input type='hidden' name='linkid' value='$pid'>
				<p>Select Paybill account<br><select style='width:100%;cursor:pointer' name='cpayb' required>$lis1</select></p>
				<p>Linked Bank<br><select style='width:100%;cursor:pointer' name='lbank' required>$lis2</select></p> 
				<p>Direct payins from<br><select name='fpayb' style='width:100%' required>$opts</select></p>
				<p>Update previous records from<br><input type='date' name='fday' style='width:100%' value='$fd' max='$mxto' required></p><br>
				<p style='text-align:right'><button class='btnn'><i class='bi-arrow-left-right'></i> Link</button></p>
			</form><br>
		</div>";
	}
	
	# post entry
	if(isset($_GET['postentry'])){
		$ref = trim($_GET['postentry']);
		$tdy = date("Y-m-d"); $amnt=$trans=$pyc=""; $bid=0;
		$me = staffInfo($sid); $access=$me['access_level'];
		$ran = (isset($_GET['src'])) ? trim($_GET['src']):"";
		
		$opts = $lis = "<option value='0'>-- Select Account --</option>";
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $rid=$row['id']; $accs[$rid]=$acc;
			$opts.=(!in_array($rid,array(12,21,23))) ? "<option value='$rid'>$acc</option>":"";
		}
		
		if($ref){
			if($ref=="disb"){
				$pid = trim($_GET['ref']); $ran="seen$pid";
				$qri = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `id`='$pid'");
				if($qri){
					$row=$qri[0]; $ref=$row["code"]; $amnt=$row['amount']; $tdy=date("Y-m-d",$row['time']); $bid=$row['branch'];
					$lis = "<option value='16'>".$accs[16]."</option>"; $trans=prepare($row['name'])." - $ref"; $pyc="b2c:$ref:0";
				}
			}
			elseif(explode(":",$ref)[0]=="c2b"){
				$pid = explode(":",$ref)[1]; $payb=explode(":",$ref)[2]; $ran=$pid;
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
				if($qri){
					$row=$qri[0]; $dy=$row['date']; $ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
					$ref=$row["code"]; $amnt=$row['amount']; $tdy=date("Y-m-d",$ptm); $trans=prepare($row['client'])." - $ref"; $pyc="c2b:$ref:0"; 
				}
				
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account` LIKE '%$payb%'");
				if($sql){ $pacc=$sql[0]['id']; }
				else{
					$db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'Mpesa Paybills','asset','2','1','1','0')");
					$mid = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='Mpesa Paybills'")[0]['id'];
					$db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'Paybill-$payb','asset','2,$mid','2','0','0')");
					$pacc = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='Paybill-$payb'")[0]['id'];
					$accs[$pacc] = "Paybill-$payb";
				}
				
				foreach($accs as $rid=>$acc){
					$cnd = ($rid==$pacc) ? "selected":"";
					$lis.=(!in_array($rid,array(12,21,23))) ? "<option value='$rid' $cnd>$acc</option>":"";
				}
			}
			else{
				$chk = $db->query(2,"SELECT `message` FROM `tickets$cid` WHERE `id`='$ref'");
				if($chk){
					$mssg=prepare($chk[0]['message']); $pyd=$ref;
					$ref = (strpos($mssg,"transaction")!==false) ? explode(" ",substr($mssg,strpos($mssg,"transaction")))[1]:$ref;
					$pyc = ($ref!=$pyd) ? "b2c:$ref:$pyd":$ref;
				}
				
				$qri = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `code`='$ref'");
				$amnt = ($qri) ? $qri[0]['amount']:""; $tdy=($qri) ? date("Y-m-d",$qri[0]['time']):$tdy; $trans=($qri) ? prepare($qri[0]['name'])." - $ref":"";
				foreach($accs as $rid=>$acc){
					$cnd = ($rid==16) ? "selected":"";
					$lis.=(!in_array($rid,array(12,21,23))) ? "<option value='$rid' $cnd>$acc</option>":"";
				}
			}
		}
		else{ $lis = $opts; }
		
		if($access=="hq"){
			$brans = "<option value='0'>Corporate (HQ)</option>";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			foreach($qri as $row){
				$cnd = ($row['id']==$bid) ? "selected":"";
				$brans.="<option value='".$row['id']."' $cnd>".prepare(ucwords($row['branch']))."</option>";
			}
			$bin = "<p style='margin:10px 5px'>Branch<br><select name='tbran' id='bran' style='width:100%'>$brans</select></p>";
		}
		else{ $bin = "<input type='hidden' name='tbran' value='".$me['branch']."' id='bran'>"; }
		
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Post Journal Transaction</h3><br>
			<input type='hidden' id='options' value=\"$opts\">
			<form method='post' id='pform' onsubmit=\"savejournal(event,'$ran')\"> 
				<input type='hidden' name='tsrc' value=\"$pyc\"> $bin
				<table class='dtbl' style='width:100%' cellpadding='5'>
					<tr><td style='width:65%'>Debit Account <i class='fa fa-plus' title='Add Debit Account'
					style='font-size:19px;float:right;color:#008fff;cursor:pointer' onclick=\"addrow('debit')\"></i><br> 
					<select name='debs[]' style='width:100%;font-size:15px;color:#191970'>$opts</select></td><td>Amount<br><input type='number' name='damnt[]' 
					style='width:100%' value='$amnt' required></td></tr>
				</table>
				<table class='ctbl' style='width:100%;margin-top:7px' cellpadding='5'>
					<tr><td style='width:65%'>Credit Account <i class='fa fa-plus' title='Add Credit Account'
					style='font-size:19px;float:right;color:#008fff;cursor:pointer' onclick=\"addrow('credit')\"></i><br> 
					<select name='creds[]' style='width:100%;font-size:15px;color:#191970'>$lis</select></td><td>Amount<br><input type='number' name='camnt[]' 
					style='width:100%' value='$amnt' required></td></tr>
				</table>
				<p style='margin:10px 5px 15px 5px;'>Date <span style='float:right'>Ref No</span><br> 
				<input type='date' value='$tdy' style='width:55%;color:#191970;font-size:15px;' name='tday' max='$tdy' required>
				<input type='text' name='refno' style='float:right;width:43%;' value='$ref'></p>
				<p style='margin:15px 5px'>Transaction Details<br><input type='text' style='width:100%' name='transdes' value=\"$trans\" required></p>
				<p style='margin:0px 5px'>Comments<br><input type='text' style='width:100%' name='tcomm'></p><br>
				<p style='text-align:right;'><button class='btnn'>Post</button></p>
			</form><br>
		</div>";
	}
	
	# chart of accounts
	if(isset($_GET['charts'])){
		$dat=$opts=$trs=$lis=""; 
		$me = staffInfo($sid); $no=$jn=0; $all=[];
		$perms=getroles(explode(",",$me['roles']));
	
		$accs=array("asset","liability","equity","income","expense");
		foreach($accs as $acc){
			$qry=$db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='$acc' AND `wing`='0'"); $res1=($qry) ? $qry:[]; $wings="";
			foreach($res1 as $row){
				$name=($acc=="asset") ? prepare(strtoupper($row['account'])):prepare(ucwords($row['account'])); 
				$wid=$row['id']; $all[$wid]=prepare(ucwords($row['account']));
				$qri=$db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='$acc' AND `level`='1' AND `wing`='$wid'"); 
				$res2=($qri) ? $qri:[]; $lis1="";
				foreach($res2 as $raw){
					$name2=prepare(ucwords($raw['account'])); $wid1=$wid.",".$raw['id']; $lis2=""; $all[$raw['id']]=prepare(ucwords($raw['account']));
					$qr=$db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='$acc' AND `wing`='$wid1'"); $res3=($qr) ? $qr:[];
					foreach($res3 as $roq){
						$name3=prepare(ucwords($roq['account'])); $all[$roq['id']]=prepare(ucwords($roq['account']));
						$lis2.="<li style='line-height:22px;color:#008fff;list-style:none;font-family:ebrima;'><i class='fa fa-circle'></i> $name3</li>";
					}
					if($lis2==""){ $lis1.="<li style='line-height:22px;font-family:ebrima;color:#008fff;list-style:none'><i class='fa fa-circle'></i> $name2</li>"; }
					else{ $lis1.="<li style='line-height:22px;color:#3CB371;list-style:none'><span class='libox'>$name2</span> <ul class='nested'>$lis2</ul></li>"; }
				}
				if($lis1=="" && $acc!="asset"){
					$wings.="<li style='line-height:22px;color:#008fff;list-style:none'><i class='fa fa-circle'></i> $name</li>";
				}
				else{ 
					$wings.="<li style='line-height:22px;color:#191970;list-style:none'><span class='libox'>$name</span> 
					<ul class='nested'>$lis1</ul></li>"; 
				}
			}
			
			$show="<ul class='nested' style='margin-bottom:0px'>$wings</ul>";
			$dat.="<li style='list-style:none'><span class='libox' style='color:#4682b4;font-size:18px'>".strtoupper($acc)."</span>$show</li>";
		}
		
		if(in_array("create chart of account",$perms)){
			$edit = "<button class='bts' style='font-size:14px;' onclick=\"popupload('accounts/index.php?macc=1')\"><i class='fa fa-pencil'></i> Edit Acc</button>";
			$add = "<button class='bts' style='font-size:14px;' onclick=\"popupload('accounts/index.php?macc')\"><i class='fa fa-plus'></i> Create</button>";
		}
		
		$del = (in_array("delete chart of account",$perms)) ? "<button class='bts' style='font-size:14px;' onclick=\"popupload('accounts/index.php?rmacc')\">
		<i class='fa fa-times'></i> Remove</button>":"";
		
		$res = $db->query(3,"SELECT *FROM `accounting_rules$cid`");
		foreach($res as $row){
			$name=prepare(ucwords($row['rule'])); $rid=$row['id']; $no++;
			$deb=($rid==4) ? $all[$row['credit']]:$all[$row['debit']]; $cred=($rid==4) ? $all[$row['debit']]:$all[$row['credit']];
			$edt = ($row['id']!=4 && in_array("update accounting rule",$perms)) ? "<i class='fa fa-pencil' style='color:#008fff;cursor:pointer;font-size:17px;float:right'
			onclick=\"popupload('accounts/index.php?crule=$rid')\"></i>":"";
			$trs.= "<tr valign='top'><td>$no</td><td>$name $edt</td> <td>$deb</td><td>$cred</td></tr>";
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		if($qri){ $sett = json_decode($qri[0]['value'],1); }
		else{
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='Clients Wallet'");
			if($sql){ $dca=$sql[0]['id']; }
			else{
				$db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'Clients Wallet','liability','4','1','0','0')");
				$dca = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='Clients Wallet'")[0]['id'];
				$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='reserved_accounts'");
				$skip = ($qri) ? json_decode($qri[0]['value'],1):[]; $skip[]=$dca; $jsn = json_encode($skip,1);
				if($qri){ $db->execute(1,"UPDATE `settings` SET `value`='$jsn' WHERE `setting`='reserved_accounts'"); }
				else{ $db->execute(1,"INSERT INTO `settings` VALUES(NULL,'$cid','reserved_accounts','$jsn')"); }
			}
			$sett["Investment"]=$sett["Transactional"]=$sett["Savings"]=$dca; $sett["Investors"]=$sett["Withdrawals"]=0;
		}
		
		if(!isset($sett["Cash"])){ $sett["Cash"]=0; }
		if(!isset($sett["Savings"])){ $sett["Savings"]=0; }
		if(!isset($sett["Withdrawals"])){ $sett["Withdrawals"]=0; }
		
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE (`type`='liability' OR `type`='asset') AND `tree`='0' ORDER BY `account` ASC");
		foreach($sql as $row){
			if($row['type']=="liability"){ $libs[$row["id"]]=prepare(ucfirst($row['account'])); }
			if($row['type']=="asset"){ $alib[$row["id"]]=prepare(ucfirst($row['account'])); }
		}
		
		foreach($sett as $key=>$acc){
			$ops="<option value='0'>-- Select --</option>"; $arr=(in_array($key,["Cash","Withdrawals"])) ? $alib:$libs; $jn++;
			$txt=($key=="Investors") ? "Investors ROI":$key; $txt=($key=="Withdrawals") ? "$key Suspense":$txt; 
			foreach($arr as $id=>$name){ $cnd=($id==$acc) ? "selected":""; $ops.="<option value='$id' $cnd>$name</option>"; }
			$lis.= "<tr><td>$jn. $txt Account</td><td style='text-align:right'><select name='setwall[$key]' style='width:150px;padding:3px;font-size:15px'>$ops</select></td></tr>";
		}
		
		$disb = (in_array("update accounting rule",$perms)) ? "style='padding:3px 6px'":"disabled style='cursor:not-allowed;padding:3px 6px'";
		echo "<div class='container cardv' style='max-width:1100px;'>
			<div class='row' style='overflow:auto'>
				<div class='col-12'>
					<h3 style='font-size:22px;color:#191970'>$backbtn Chart of Accounts</h3><hr>
				</div>
				<div class='col-12 col-lg-6 mb-3' style='overflow:auto'>
					<div style='padding:5px 20px'>
						<h4 style='text-align:right'> $add $edit $del</h4><hr> $dat
					</div>
					<div style='padding:5px 20px'>
						<h3 style='color:#191970;font-size:22px;margin-top:10px'>Set Wallet Accounts</h3>
							<form method='post' id='wform' onsubmit='setwallets(event)'>
							<table style='width:100%' class='table-striped' cellpadding='5'>
							<caption style='text-align:right'><button class='btnn' $disb>Update</button></caption> $lis</table>
						</form>
					</div>
				</div>
				<div class='col-12 col-lg-6 mb-3' style='overflow:auto'>
					<div style='padding:5px;max-width:100%'>
						<h3 style='color:#191970;font-size:20px;'>Accounting Rules</h3>
						<table class='table-striped' style='width:100%;font-size:15px' cellpadding='5'>
							<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Rule</td><td>Debit</td><td>Credit</td></tr> $trs
						</table>
					</div>
				</div>
			</div>
		</div>
		
		<script>
			var toggler=document.getElementsByClassName('libox');
			for (var i = 0; i <toggler.length; i++){
			  toggler[i].addEventListener('click', function(){
				this.parentElement.querySelector('.nested').classList.toggle('liactive');
				this.classList.toggle('check-box');
			  });
			}
		</script>";
		savelog($sid,"Viewed chart of accounts");
	}
	
	# delete account
	if(isset($_GET['rmacc'])){
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='reserved_accounts'");
		$opts = "<option value='0'>-- Select --</option>"; $skip=($sql) ? json_decode($sql[0]['value'],1):[];
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND `id`>35 ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $tid=$row['id'];
			$opts.= (!in_array($tid,$skip)) ? "<option value='$tid'>$acc</option>":"";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Remove Book of Account</h3><br>
			<p><b>Note:</b> You can only delete account without a sub-account under it & the ones you created after system setup.</p>
			<p>Select Account<br><select style='width:100%' id='dacc'>$opts</select></p><br>
			<p style='text-align:right'><button class='btnn' onclick='delbook()' style='background:#DC143C'><i class='fa fa-times'></i> Remove</button></p>
		</div><br>";
	}
	
	# create/edit account chart
	if(isset($_GET['macc'])){
		$tid = trim($_GET['macc']);
		$ctp = (isset($_GET['ctp'])) ? trim($_GET['ctp']):"asset";
		
		$opt1 = "<option value='0'>-- None --</option>"; $opt2=$opts=""; $accs=$lis=$levels=[];
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='$ctp' ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucfirst($row['account'])); $acid=$row['id']; $tree=$row['tree']; 
			$cnd = ($tid==$acid) ? "selected":""; $accs[$acid]=$acc; $levels[$acid]=$row['level']; $wings[$acid]=$row['wing'];
			if($tree>0 or ($tree==0 && $row['level']<2)){ $lis[$acid]=$acc; }
			$opt2.="<option value='$acid' $cnd>$acc</option>";
		}
		
		foreach(array("asset","liability","equity","income","expense") as $one){
			$cnd = ($ctp==$one) ? "selected":"";
			$opts.="<option value='$one' $cnd>".strtoupper($one)."</option>";
		}
		
		if($tid && count($accs)>0){ $tid=(array_key_exists($tid,$accs)) ? $tid:array_keys($accs)[0]; }
		$name = (array_key_exists($tid,$accs)) ? $accs[$tid]:"";
		$title = ($tid) ? "Edit Book of Account":"Create Book of Account"; 
		$wing = ($tid) ? @array_pop(explode(",",$wings[$tid])):0; 
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='reserved_accounts'");
		$skip = ($sql) ? json_decode($sql[0]['value'],1):[];
		
		foreach($lis as $key=>$acc){
			if(!in_array($key,$skip)){
				$cnd = ($key==$wing) ? "selected":"";
				if($tid){ 
					$opt1.=($levels[$tid]>0 && $tid!=$key) ? "<option value='$key' $cnd>$acc</option>":"";
				}
				else{ $opt1.="<option value='$key' $cnd>$acc</option>"; }
			}
		}
		
		$dval = ($tid) ? "<p>Select Account to Edit<br><select name='edit' style='width:100%'
		onchange=\"popupload('accounts/index.php?ctp=$ctp&macc='+this.value)\">$opt2</select></p>":"";
		$bal = ($tid) ? "":"<p>Current account balance<br><input type='number' name='cbal' style='width:100%' value='0' required></p>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>$title</h3><br>
			<form method='post' id='cform' onsubmit=\"savebook(event,'$tid')\">
				<p>Account Book<br><select style='width:100%' name='actp' onchange=\"popupload('accounts/index.php?macc=$tid&ctp='+this.value)\">
				$opts</select></p> $dval
				<p>Account Name<br><input type='text' style='width:100%' value='$name' name='accname' required></p>
				<p>Sub-Account Wing<br><select name='subacc' id='subt' style='width:100%'>$opt1</select></p> $bal
				<br><p style='text-align:right'><button class='btnn'>Save</button></p>
			</form>
		</div><br>";
	}
	
	# pays inclusion
	if(isset($_GET['accpays'])){
		$pay = trim($_GET['accpays']);
		$ctp = (isset($_GET['ctp'])) ? trim($_GET['ctp']):"income";
		$tmon = date('Y-m');$lis=$opts="";
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='$ctp' AND `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$acc = prepare(ucfirst($row['account'])); $acid=$row['id']; 
			$lis.= "<option value='$acid'>$acc</option>";
		}
		
		foreach(array("asset","liability","income") as $one){
			$cnd = ($ctp==$one) ? "selected":"";
			$opts.= "<option value='$one' $cnd>".strtoupper($one)."</option>";
		}
	
		$res = $db->query(2,"SELECT MIN(month) AS mon FROM `paysummary$cid`");
		$min = ($res) ? date("Y-m",$res[0]['mon']):$tmon;
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Record Payment to Journals</h3><br>
			<form method='post' id='pform' onsubmit=\"savepayacc(event)\">
				<input type='hidden' name='paydes' value='$pay'>
				<p>Account Book<br><select style='width:100%' name='actp' onchange=\"popupload('accounts/index.php?accpays=$pay&ctp='+this.value)\">$opts</select></p>
				<p>Select Account for <b>".ucwords(str_replace("_"," ",$pay))."</b><br><select name='payacc' style='width:100%'>$lis</select></p> 
				<p>Compound balance from<br><input type='month' name='pfrom' style='width:100%;padding:5px;border:1px solid #dcdcdc' min='$min' max='$tmon' value='$min' required></p>
				<br><p style='text-align:right'><button class='btnn'>Include</button></p>
			</form>
		</div><br>";
	}
	
	# change accounting rules
	if(isset($_GET['crule'])){
		$rid = trim($_GET['crule']);
		$sql = $db->query(3,"SELECT *FROM `accounting_rules$cid` WHERE `id`='$rid'");
		$row = $sql[0]; $deb=$row['debit']; $cred=$row['credit']; $rule=prepare(ucwords($row['rule']));
		
		$creds=$debs="";
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $acid=$row['id'];
			$cnd1=($acid==$deb) ? "selected":""; $cnd2=($acid==$cred) ? "selected":"";
			if($rid==2){
				$debs.=($acid==$deb) ? "<option value='$acid' $cnd1>$acc</option>":""; $creds.="<option value='$acid' $cnd2>$acc</option>";
			}
			elseif($rid==3){
				$debs.="<option value='$acid' $cnd1>$acc</option>"; $creds.=($cred==$acid) ? "<option value='$acid' $cnd2>$acc</option>":"";
			}
			else{
				$debs.="<option value='$acid' $cnd1>$acc</option>"; $creds.="<option value='$acid' $cnd2>$acc</option>";
			}
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Change Accounting Rule</h3><br>
			<p>Change Rule for $rule</p>
			<form method='post' id='rform' onsubmit='saverule(event)'>
				<input type='hidden' name='rule' value='$rid'> <input type='hidden' name='rname' value='$rule'>
				<p>Debit Account<br><select name='rdb' style='width:100%'>$debs</select></p>
				<p>Credit Account<br><select name='rcd' style='width:100%'>$creds</select></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p>
			</form><br>
		</div>";
	}
	
	//expenses & posted entris
	if(isset($_GET['ventries'])){
		$vtp = trim($_GET['ventries']);
		$tmon = strtotime(date("Y-M"));
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):null;
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):$tmon;
		$mon = (date("Y")!=$yr && !isset($_GET['mon'])) ? 0:$mon;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$day = (isset($_GET['day'])) ? trim($_GET['day']):0;
		
		$trs=$brans=""; $perpage=50;
		$me = staffInfo($sid); $access = $me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage); 
		
		$bnames = array(0=>"Head Office");
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$staff[0]="System";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
		foreach($res as $row){
			$accs[$row['id']]=prepare(ucfirst($row['account'])); 
		}
		
		$mons = ($yr==date("Y")) ? "<option value='$tmon'>".date("M Y")."</option>":""; $mns=[];
		$res = $db->query(3,"SELECT DISTINCT `month` FROM `transactions$cid` WHERE `month`<$tmon AND `year`='$yr' ORDER BY `month` DESC");
		if($res){
			foreach($res as $row){
				if(date("Y",$row['month'])==$yr){
					$cnd = ($row['month']==$mon) ? "selected":""; $mns[]=$row['month'];
					$mons.="<option value='".$row['month']."' $cnd>".date("M Y",$row['month'])."</option>";
				}
			}
			$mon = ($mon) ? $mon:max($mns);
		}
		else{ $mon = ($mon) ? $mon:$tmon; }
		
		if($bran!=null){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND `branch`='".$me['branch']."'";
		$cond = ($str) ? 1:"`year`='$yr'";
		$cond.= ($vtp=="all") ? "":" AND `book`='expense'";
		$cond.= ($me['access_level']=="portfolio") ? " AND `staff`='$sid'":$show;
		$cond.= ($str) ? " AND (`id`='$str' OR `transid` LIKE '%$str%' OR `refno` LIKE '%$str%' OR `details` LIKE '%$str%')":" AND `month`='$mon'";
		$cond.= ($day && $str==null) ? " AND `day`='$day'":"";
		
		$res = $db->query(3,"SELECT *,GROUP_CONCAT(type separator ',') AS types,GROUP_CONCAT(amount separator ',') AS amounts,
		GROUP_CONCAT(account separator ',') AS accs,SUM(amount) AS totpay FROM `transactions$cid` WHERE $cond GROUP BY transid ORDER BY id DESC $lim");
		if($res){
			foreach($res as $row){
				$tid=$row['transid']; $by=$staff[$row['user']]; $amnts=explode(",",$row['amounts']); $tps=explode(",",$row['types']);
				$list=explode(",",$row['accs']); $ref=$row['refno']; $tym=date('M d, H:i',$row['time']); $dy=date("d-m-Y",$row['day']); 
				$amnt=(count($tps)>1) ? fnum($row['totpay']/2):fnum($row['totpay']); $lis1=$lis2=$css=$rvs=""; $revs=[];
				
				if(isJson($row['reversal'])){
					$def = json_decode($row['reversal'],1); 
					if(isset($def['status']) && isset($def['time'])){
						$rvs="<i>Reversed ".date('d-m-y',$def['time'])."</i><br>By ".$staff[$def['user']];
						if(isset($def['status'])){
							$crd = (isset($def['accs']['credit'])) ? array_sum($def['accs']['credit']):0;
							$amnt=(isset($accs["accs"]["debit"])) ? array_sum($def['accs']['debit']):$crd; 
							$rby=$staff[$def['user']]; $rdy=date("d-m-Y,H:i",$def['time']); $css="color:#B0C4DE"; 
							$revs['debit']=(isset($def['accs']['debit'])) ? $def['accs']['debit']:0; 
							$revs['credit']=(isset($def['accs']['credit'])) ? $def['accs']['credit']:0;
						}
					}
				}
				
				foreach($tps as $no=>$tp){
					if($tp=="credit"){
						$acc=$list[$no]; $tsum=(count($revs)) ? $revs['credit'][$acc]:$amnts[$no];
						$lis1.="<li type='i'> ".$accs[$acc]." - KES ".fnum($tsum)."</li>"; 
					}
					else{
						$acc=$list[$no]; $tsum=(count($revs)) ? $revs['debit'][$acc]:$amnts[$no];
						$lis2.="<li type='i'> ".$accs[$acc]." - KES ".fnum($tsum)."</li>";
					}
				}
				
				$onclick = (count($revs)) ? "":"onclick=\"popupload('accounts/index.php?viewentry=$tid')\"";
				$trs.="<tr valign='top' id='$tid' style='$css' $onclick><td>$by<br><span style='color:grey'>$tym</span></td><td>$dy</td><td>$amnt</td><td>$lis1</td>
				<td>$lis2</td><td>$ref</td><td>$tid</td><td>$rvs</td</tr>";
			}
		}
		
		$yrs = "<option value='".date('Y')."'>".date("Y")."</option>";
		$res = $db->query(3,"SELECT DISTINCT `year` FROM `transactions$cid` WHERE `year`<".date("Y")." ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$cnd = ($row['year']==$yr) ? "selected":"";
				$yrs.="<option value='".$row['year']."' $cnd>".$row['year']."</option>";
			}
		}
		
		$days = "<option value='0'>-- Day --</option>";
		$res = $db->query(3,"SELECT DISTINCT `day` FROM `transactions$cid` WHERE `month`='$mon' ORDER BY `day` DESC");
		if($res){
			foreach($res as $row){
				$cnd = ($row['day']==$day) ? "selected":"";
				$days.="<option value='".$row['day']."' $cnd>".date("d-m-Y",$row['day'])."</option>";
			}
		}
		
		if($access=="hq"){
			$brn = "<option value=''>Corporate</option>";
			foreach($bnames as $bid=>$bname){
				$cnd=($bran==$bid && $bran!=null) ? "selected":""; 
				$brn.="<option value='$bid' $cnd>$bname</option>";
			}
			
			$brans = "<select style='width:130px;font-size:15px' 
			onchange=\"loadpage('accounts/index.php?ventries=$vtp&year=$yr&mon=$mon&day=$day&bran='+this.value)\">$brn</select>";
		}
		
		$sql = $db->query(3,"SELECT COUNT(DISTINCT transid) AS total,SUM(amount) AS tamnt FROM `transactions$cid` WHERE $cond"); 
		$totals = ($sql) ? $sql[0]['total']:0; $sum = ($sql) ? number_format($sql[0]['tamnt']):0;
		$tsum = ($vtp=="expense") ? "<span style='float:right;'>KES $sum</span>":"";
		
		$title = ($day) ? date('d-m-Y',$day):date('M Y',$mon);
		$title.= ($vtp=="all") ? " Posted Entries":"Expenses";
		
		echo "<div class='container cardv' style='max-width:1300px;min-height:400px;overflow:auto'>
			<div style='padding:7px;min-width:800px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title $tsum</h3><hr style='margin:0px'>
				<table class='table-striped btbl' style='width:100%;font-size:14px;' cellpadding='5'>
					<caption style='caption-side:top'>
						<input type='search' style='padding:5px;float:right;width:170px' placeholder='&#xf002; Search Transaction' 
						onkeyup=\"fsearch(event,'accounts/index.php?ventries=$vtp&str='+cleanstr(this.value))\" 
						onsearch=\"loadpage('accounts/index.php?ventries=$vtp&str='+cleanstr(this.value))\" value=\"$str\">
						<select style='width:80px' onchange=\"loadpage('accounts/index.php?ventries=$vtp&year='+this.value)\">$yrs</select>
						<select style='width:120px' onchange=\"loadpage('accounts/index.php?ventries=$vtp&year=$yr&mon='+this.value)\">$mons</select>
						<select style='width:120px' onchange=\"loadpage('accounts/index.php?ventries=$vtp&year=$yr&mon=$mon&day='+this.value)\">$days</select> $brans
					</caption>
					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px;cursor:default' valign='top'><td style='min-width:120px'>Posted By</td>
					<td style='width:90px'>Trans Date</td><td>Amount</td><td>Credit</td><td>Debit</td><td>Refno</td><td colspan='2'>Trans Id</td></tr> $trs
				</table>
			</div>".getLimitDiv($page,$perpage,$totals,"accounts/index.php?ventries=$vtp&year=$yr&mon=$mon&bran=$bran&day=$day&str=".str_replace(" ","+",$str))."
		</div>";
		
		$bname = ($bran==null) ? "Corporate":$bnames[$bran];
		savelog($sid,"Viewed $title for $bname");
	}
	
	#view posted entry
	if(isset($_GET['viewentry'])){
		$tid = trim($_GET['viewentry']); $trs="";
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid`");
		foreach($qri as $row){
			$accs[$row['id']]=prepare(ucwords($row['account']));
		}
		
		$res = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `transid`='$tid'");
		foreach($res as $row){
			$acc=$row['account']; $amnt=number_format($row['amount']); $ref=$row['refno']; $book=strtoupper($row['book']); 
			$type=$row['type']; $id=$row['id']; $deb=($type=="debit") ? $amnt:null; $cred=($type=="credit") ? $amnt:null; 
			$day=date("M d, Y",$row['day']); $desc=ucfirst(prepare($row['details'])); $comm=prepare(ucfirst($row['comments']));
			$bran=$row['branch']; $rev=$row['reversal'];
			$trs.="<tr><td>$id</td><td>$book</td><td>$accs[$acc]</td><td>$deb</td><td>$cred</td></tr>";
		}
		
		$desc = ($desc) ? $desc:"None"; $comm = ($comm) ? $comm:"None";
		$reverse = (in_array("delete journal entry",$perms) && $rev!="auto") ? "<button style='float:right;font-size:14px' class='bts' 
		onclick=\"delentry('$tid')\"><i class='fa fa-refresh'></i> Reverse</button>":"";
		
		if($bran){
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
			$bname = prepare(ucwords($res[0]['branch']))." branch";
		}
		else{ $bname = "Head Office"; }
		
		echo "<div style='padding:10px'>
			<h3 style='color:#191970;font-size:22px'>Transaction $tid $reverse</h3><hr>
			<p style='font-weight:bold'>$bname <span style='float:right'>Date: $day</span></p>
			<table style='width:100%;font-size:15px' class='table-striped' cellpadding='6'>
				<tr style='font-weight:bold;color:#191970'><td>EntryID</td><td>Type</td><td>Account</td><td>Debit</td><td>Credit</td></tr>$trs
			</table><br>
			<table style='width:100%;color:#2f4f4f' cellpadding='5'>
				<tr style='font-weight:bold;color:#191970;'><td>Description</td><td>Comments</td></tr> 
				<tr valign='top'><td>$desc</td><td>$comm</td></tr>
			</table><br>
		</div>";
	}
	
	# expense summary
	if(isset($_GET['expesummary'])){
		$mon=(isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$monend=date("j",monrange(date("m",$mon),date("Y",$mon))[1]);
		$first_day=date('w',mktime(0,0,0,date("m",$mon),1,date("Y",$mon)));
		$first_day=($first_day==0) ? 7:$first_day; $opts="";
		
		$brans = array("Head Office");
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		foreach($qri as $row){
			$brans[$row['id']]=prepare(ucwords($row['branch']));
		}
		
		$weekdays=array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
		$thead="<tr style='text-align:center;color:#191970;font-weight:bold;background:#e6e6fa'>";
		foreach($weekdays as $wkdy){
			$thead.="<td><p style='margin:6px'>$wkdy</p></td>";
		}
		
		$thead.="</tr>"; $trs=($first_day>1) ? "<td colspan='".($first_day-1)."'></td>":"";
		
		for($i=1; $i<=$monend; $i++){
			$dy=($i<10) ? "0$i":$i; $day=strtotime(date("Y-M",$mon)."-".$dy); 
			$wkdy=date("D",$day); $pos=($i+$first_day-1)%7;
			$res = $db->query(3,"SELECT *,SUM(amount) AS tamnt FROM `transactions$cid` WHERE `day`='$day' AND `book`='expense' AND `type`='debit' GROUP BY branch");
			
			if($res){
				$tds=""; $tot=0; $avs=[];
				foreach($res as $row){
					$bid=$row['branch']; $amnt=number_format($row['tamnt']); $avs[]=$bid; $tot+=$row['tamnt'];
					$tds.="<tr><td>$brans[$bid]</td><td style='text-align:right'>$amnt</td></tr>";
				}
				
				foreach($brans as $bid=>$name){
					$tds.=(!in_array($bid,$avs)) ? "<tr><td>$name</td><td style='text-align:right'>0</td></tr>":"";
				}
				
				$tds.="<tr><td colspan='2' style='text-align:right;font-weight:bold'>".number_format($tot)."</td></tr>";
				$trs.="<td><table cellpadding='5' style='width:100%;font-size:14px' class='table-bordered'>
					<tr style='background:#f0f0f0;text-align:center;color:#4682b4'><td colspan='2'><b>".date("d-m-Y",$day)."</b></td></tr> $tds
				</table></td>";
			}
			else{
				$trs.="<td></td>";
			}
			
			$trs.=($pos==0) ? "</tr><tr valign='top'>":"";
		}
		$trs.="</tr>";
		
		$thismon = strtotime(date("Y-M")); $tyr=date("Y");
		$opts = "<option value='$thismon'>".date("F Y")."</option>";
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `transactions$cid` WHERE NOT `month`='$thismon' AND `book`='expense' AND `year`='$yr' ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mn==$mon)? "selected":"";
				$opts.="<option value='$mn' $cnd>".date('F Y',$mn)."</option>";
			}
		}
		
		$yrs = "<option value='$tyr'>$tyr</option>";
		$qry = $db->query(3,"SELECT DISTINCT `year` FROM `transactions$cid` WHERE NOT `year`='$tyr' AND `book`='expense' ORDER BY `year` DESC");
		if($qry){
			foreach($qry as $row){
				$year=$row['year']; $cnd=($yr==$year)? "selected":"";
				$yrs.="<option value='$year' $cnd>$year</option>";
			}
		}
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;min-width:600px'>
			<div class='container' style='padding:7px;min-width:600px'>
			<h3 style='font-size:22px;color:#191970'>Daily Company Expenses
			<select style='float:right;padding:4px;width:150px;' onchange=\"loadpage('accounts/index.php?expesummary&year=$yr&mon='+this.value)\">$opts</select>
			<select style='float:right;padding:4px;width:80px;margin-right:10px' onchange=\"loadpage('accounts/index.php?expesummary&year='+this.value)\">$yrs</select></h3>
				<table cellpadding='0' cellspacing='0' style='width:100%;font-size:15px'> $thead $trs </table>
			</div>
		</div>";
		
		savelog($sid,"Viewed Daily company Expenses for ".date("F Y",$mon));
	}
	
	#expected cashflow
	if(isset($_GET['cashflow'])){
		$tmon = strtotime(date("Y-M"));
		$mon=(isset($_GET['mon'])) ? trim($_GET['mon']):$tmon;
		$week=(isset($_GET['wk'])) ? trim($_GET['wk']):0;
		$day=(isset($_GET['day'])) ? trim($_GET['day']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['stid'])) ? clean($_GET['stid']):0;
		$today = strtotime(date("Y-M-d"));
		$monstart=monrange(date("m",$mon),date("Y",$mon))[0];
		$from = ($monstart>$today) ? $monstart:$today;
		$thiswk=date("W"); $trs=$oftd=$brans="";
		
		if($week>0){
			$start=($thiswk==$week) ? $today:weekrange($week,date("Y",$mon))[0]; $wkend=weekrange($week,date("Y",$mon))[1];
			$cond = ($day) ? "AND sd.day='$day'":"AND sd.day BETWEEN $start AND $wkend";
		}
		else{ $cond = ($day>0) ? "AND sd.day='$day'":"AND sd.day BETWEEN $from AND ".monrange(date("m",$mon),date("Y",$mon))[1]; }
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$ltbl = "org".$cid."_loans"; $stbl = "org".$cid."_schedule";
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND ln.branch='".$me['branch']."'";
		$cond.= ($me['access_level']=="portfolio") ? " AND sd.officer='$sid'":$show;
		$cond.=($stid) ? " AND sd.officer='$stid'":"";
		
		$bnames=["Head Office"];
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$mons = "<option value='$tmon'>".date('M Y')."</option>";
		$sql = $db->query(2,"SELECT DISTINCT `month` FROM `$stbl` WHERE `month`>$tmon ORDER BY `month` ASC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$mdy = ($monstart>$today) ? $monstart:$today; $wks=array();
		$days = "<option value='0'>-- Day --</option>"; 
		$res = $db->query(2,"SELECT DISTINCT `day` FROM `$stbl` WHERE `month`='$mon' AND `day`>=$mdy ORDER BY `day` ASC");
		if($res){
			foreach($res as $row){
				$dy=$row['day']; $cnd=($dy==$day) ? "selected":"";
				$days.="<option value='$dy' $cnd>".date('d-m-Y',$dy)."</option>";
				$wks[date("W",$dy)]=weekrange(date("W",$dy),date("Y",$dy));
			}
		}
		
		$weeks="<option value='0'>-- Select week --</option>";
		foreach($wks as $wkno=>$wk){
			$cnd=($wkno==$week) ? "selected":"";
			$weeks.="<option value='$wkno' $cnd>".date('M d',$wk[0])." - ".date('M d',$wk[1])."</option>";
		}
		
		if($bran or $access=="branch"){
			$offs = "<option value='0'>-- Select Staff --</option>";
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `$stbl` WHERE `day`>=$today");
			if($res){
				foreach($res as $row){
					$ro=$row['officer']; $cnd=($ro==$stid) ? "selected":"";
					$offs.=($me['branch']==$mybran[$ro] or $bran==$mybran[$ro]) ? "<option value='$ro' $cnd>$staff[$ro]</option>":"";
				}
			}
		}
		
		$res = $db->query(2,"SELECT SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN (sd.amount-sd.paid) ELSE sd.interest END)) AS intr,
		SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ FROM `$stbl` AS sd
		INNER JOIN `$ltbl` AS ln ON ln.loan=sd.loan WHERE sd.balance>0 $cond");
		
		$res1 = $db->query(2,"SELECT ln.loan FROM `$ltbl` AS ln INNER JOIN $stbl AS sd ON ln.loan=sd.loan WHERE sd.balance>0 $cond GROUP BY ln.loan");
		$princ = ($res) ? $res[0]['princ']:0; $intr = ($res) ? $res[0]['intr']:0; 
		$tloans = ($res1) ? number_format(count($res1)):0; $totals=number_format($princ+$intr);
		
		if($access=="hq"){
			$brn="<option value='0'>Head Office</option>";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='width:100%' onchange=\"loadpage('accounts/index.php?cashflow&mon=$mon&wk=$week&day=$day&bran='+this.value)\">$brn</select>";
			if($bran){
				$oftd = "<tr><td colspan='2'><select onchange=\"loadpage('accounts/index.php?cashflow&mon=$mon&wk=$week&day=$day&bran=$bran&stid='+this.value)\"
				style='width:100%'>$offs</select></td></tr>";
			}
		}
		else{
			if($access=="branch"){
				$bran = $me['branch'];
				$brans = "<select onchange=\"loadpage('accounts/index.php?cashflow&mon=$mon&wk=$week&day=$day&bran=$bran&stid='+this.value)\"
				style='width:100%'>$offs</select>";
			}
		}
		
		echo "<div class='container cardv' style='max-width:1000px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>".date('M Y',$mon)." Expected Cashflow</h3><hr>	
			<div class='row' style='margin:0px'>
				<div class='col-12 col-md-6 col-lg-6 mt-3'>
					<table style='max-width:100%' cellpadding='4'>
						<tr><td><select style='width:120px' onchange=\"loadpage('accounts/index.php?cashflow&mon='+this.value)\">$mons</td>
						<td><select style='width:100%' onchange=\"loadpage('accounts/index.php?cashflow&mon=$mon&wk='+this.value)\">$weeks</td></tr>
						<tr><td><select style='width:120px' onchange=\"loadpage('accounts/index.php?cashflow&mon=$mon&wk=$week&day='+this.value)\">$days</td>
						<td>$brans</td></tr> $oftd
					</table>
				</div>
				<div class='col-12 col-md-6 col-lg-6 mt-3'>
					<p style='color:#4682b4;font-weight:bold;margin-bottom:6px'>Loan Summary</p>
					<table style='width:100%' cellpadding='7' class='table-striped'>
						<tr><td>Total Loans</td><td style='text-align:right'><b>$tloans</b></td></tr>
						<tr><td>Loan Principal</td><td style='text-align:right'><b>".number_format($princ)."</b> KES</td></tr>
						<tr><td>Loan Interest</td><td style='text-align:right'><b>".number_format($intr)."</b> KES</td></tr>
						<tr style='font-weight:bold;color:#4682b4'><td></td><td style='text-align:right;border-bottom:1px solid #dcdcdc'>$totals KES</td></tr>
					</table>
				</div>
			</div><br>
		</div>";
		
		savelog($sid,"Viewed Expected Cashflow for ".date("F Y",$mon));
	}

	//accounting books
	if(isset($_GET['books'])){
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles'])); $cnd="";
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='reserved_accounts'");
		$skip = ($sql) ? json_decode($sql[0]['value'],1):[]; $avs=($sql) ? 1:0; $tmon=strtotime(date("Y-M")); $ads=[];
		
		$reserve = array("Surplus Account"=>["equity","0","0"],"Accounts Reconciliation"=>["asset","2","1"],"Clients Wallet"=>["liability","4","1"]);
		foreach($reserve as $acc=>$des){
			$chk = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='$acc'");
			if(!$chk){
				$db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'$acc','".$des[0]."','".$des[1]."','".$des[2]."','0','0')");
				$rid = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='$acc'")[0]['id']; $skip[]=$rid; $ads[]=$rid;
				if($acc=="Surplus Account"){
					$qrt = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE NOT `month`='$tmon' ORDER BY `month` ASC");
					if($qrt){
						foreach($qrt as $row){ setSurplus($row['month']); }
					}
				}
			}
		}
		
		if($ads){
			$jsn = json_encode($skip,1);
			if($avs){ $db->execute(1,"UPDATE `settings` SET `value`='$jsn' WHERE `setting`='reserved_accounts'"); }
			else{ $db->execute(1,"INSERT INTO `settings` VALUES(NULL,'$cid','reserved_accounts','$jsn')"); }
		}
		
		setSurplus($tmon); 
		$entry = (in_array("post journal entry",$perms)) ? "popupload('accounts/index.php?postentry')":"toast('You dont have permission to post Journal Entries')";
		$budget = (in_array("view budgets",$perms)) ? "loadpage('accounts/budget.php?manage')":"toast('You dont have permission to view budgets')";
		$recon = (in_array("post journal entry",$perms)) ? "loadpage('accounts/reports.php?reconc')":"toast('You dont have permission to reconcile accounts')";
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>Books of Account</h3><hr>
				<div class='row'>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$entry\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-plus'></i>
						Create Journal Entry</h3><p style='margin-bottom:0px;font-size:14px'>Manual Post an Entry to Journal Record</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/index.php?ventries=all')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-search'></i>
						View posted Entries</h3><p style='margin-bottom:0px;font-size:14px'>Retrieve and Manage posted entries</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/index.php?charts')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-sitemap'></i>
						Chart of Accounts & Rules</h3><p style='margin-bottom:0px;font-size:14px'>List of Accounts used by the Company & accounting rules</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/reports.php?manage')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-bar-chart'></i>
						Acrruals & Reports</h3><p style='margin-bottom:0px;font-size:14px'>Income statement, Trial Balance & Balance sheet reports</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/index.php?ventries=expense')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-money'></i>
						Company Expenses</h3><p style='margin-bottom:0px;font-size:14px'>View and manage Company Expenses</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/payroll.php?fetch')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-leaf'></i>
						Employee Payroll</h3><p style='margin-bottom:0px;font-size:14px'>Salary dues, payroll and Payslips</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"popupload('accounts/ledger.php?getledger')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-list'></i>
						General Ledger</h3><p style='margin-bottom:0px;font-size:14px'>Description for the accounts activities</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$budget\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-sliders'></i>
						Budget Reports</h3><p style='margin-bottom:0px;font-size:14px'>Budget analysis for branches & Estimates</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('accounts/assets.php?manage')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-book'></i>
						Company Assets</h3><p style='margin-bottom:0px;font-size:14px'>Register and manage company assets</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$recon\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-retweet'></i>
						Accounts Reconciliation</h3><p style='margin-bottom:0px;font-size:14px'>Reconcile Operating Financial accounts</p>
						</div>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Viewed Books of account");
	}
	
	ob_end_flush();
?>

<script>

	function addrow(tp){
		var did=Date.now(),opts=_("options").value.trim(); 
		if(tp=="debit"){
			$(".dtbl").append("<tr id='"+did+"'><td><select name='debs[]' style='width:100%;font-size:15px;color:#191970'>"+opts+"</select></td>"+
			"<td><input type='number' name='damnt[]' style='width:80%' value='0' required><i class='fa fa-times' onclick=\"$('#"+did+"').remove()\""+
			"style='color:#ff4500;font-size:17px;float:right;cursor:pointer;' title='Remove row'></i></td></tr>");
		}
		else{
			$(".ctbl").append("<tr id='"+did+"'><td><select name='creds[]' style='width:100%;font-size:15px;color:#191970'>"+opts+"</select></td>"+
			"<td><input type='number' name='camnt[]' style='width:80%' value='0' required><i class='fa fa-times' onclick=\"$('#"+did+"').remove()\""+
			"style='color:#ff4500;font-size:17px;float:right;cursor:pointer;' title='Remove row'></i></td></tr>");
		}
	}
	
	function getclients(){
		$.ajax({
			method:"post",url:path()+"dbsave/accounting.php",data:{getclients:1},
			beforeSend:function(){ progress("Fetching...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim().split("~")[0]=="data"){
				var did = Date.now(),opts = res.trim().split("~")[1]; 
				$(".ctbl").append("<tr id='"+did+"'><td>"+opts+"</td><td><input type='number' name='lamnts[]' style='width:80%' value='0' required>"+
				"<i class='fa fa-times' onclick=\"$('#"+did+"').remove()\"style='color:#ff4500;font-size:17px;float:right;cursor:pointer;'"+
				"title='Remove row'></i></td></tr>");
			}
			else{ toast(res); }
		});
	}
	
	function delbook(){
		if(_("dacc").value.trim()==0){ toast("Error! No account selected!"); }
		else{
			if(confirm("Sure to remove book of account?")){
				$.ajax({
					method:"post",url:path()+"dbsave/accounting.php",data:{delacc:_("dacc").value.trim()},
					beforeSend:function(){ progress("Removing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ closepop(); loadpage("accounts/index.php?charts"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function savebank(e){
		e.preventDefault();
		if(confirm("Link selected paybill to Bank?")){
			var data=$("#bform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Linking...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Linked successfully!"); closepop(); loadpage("accounts/index.php?paybsett");
				}
				else{ alert(res); }
			});
		}
	}
	
	function setwallets(e){
		e.preventDefault();
		if(confirm("Proceed to update Wallet accounts?")){
			var data = $("#wform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function savejournal(e,dv){
		e.preventDefault();
		if(confirm("Continue to post Journal Entry?")){
			var data=$("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Posting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Posted successfully!"); popupload("accounts/index.php?viewentry="+res.trim().split(":")[1]); 
					if(dv.length>2){
						$("#div"+dv).hide(); $("#tr"+dv).remove(); $("#"+dv).html("<i style='color:grey;'>Seen</i>"); 
					}
				}
				else{ alert(res); }
			});
		}
	}
	
	function savepayacc(e){
		e.preventDefault();
		if(confirm("Sure to include payment in account books?")){
			var data=$("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
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
	
	function savebook(e,tid){
		e.preventDefault();
		var txt = (tid>0) ? "Update book of Account?":"Create new Book of account?";
		if(confirm("Sure to "+txt)){
			var data=$("#cform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("accounts/index.php?charts"); }
				else{ alert(res); }
			});
		}
	}
	
	function savetransfer(pid){
		if(confirm("Post paybill Transaction?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{postrans:pid},
				beforeSend:function(){ progress("Posting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function delpayb(pid){
		if(confirm("Sure to delete record?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{delpayb:pid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function delentry(tid){
		if(confirm("Sure to reverse transaction "+tid+"?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{revtrans:tid},
				beforeSend:function(){ progress("Reversing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); $("#"+tid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function saverule(e){
		e.preventDefault();
		if(confirm("Sure to update accounting rule?")){
			var data=$("#rform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("accounts/index.php?charts"); }
				else{ alert(res); }
			});
		}
	}
	
</script>