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
	
	# view loans
	if(isset($_GET["view"])){
		$gurl = str_replace(" ","+",trim($_GET["view"]));
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
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
			
			$lim = getLimit($page,20); $fon=$sql[0]['contact']; $client=prepare(ucwords($sql[0]['name'])); $no=0; $ltrs=""; $sets=[];
			$cdef = json_decode($sql[0]['cdef'],1); $autodsb=(isset($cdef["autodisburse"])) ? $cdef["autodisburse"]:0;
			if(!isset($_COOKIE["myapp"]) or (!isset($cdef["ckey"]) && !isset($_SESSION["use4"]))){
				echo "<script> window.location.replace('index.php?home=$gurl'); </script>"; exit();
			}
			
			$qry = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `client_idno`='$idno' AND `status`<=8");
			if($qry){
				$row=$qry[0]; $amnt=fnum($row['amount']); $dys=$row['duration']; $day=date("M d,Y h:i a",$row["time"]); $st=$row['status'];
				$state = ($st==8) ? "<span style='font-size:13px;color:green'><i class='bi-check2-all'></i> Pending Disbursement</span>":
				"<span style='font-size:13px;color:#008080'><i class='bi-alarm'></i> Pending Approvals</span>";
				$ltrs.= "<tr style='border-bottom:1px solid #dcdcdc'><td>KES $amnt<br><span style='font-size:14px;color:grey'>$day</span></td>
				<td style='text-align:right'>For $dys days<br>$state</td></tr>";
			}
			
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' ORDER BY `disbursement` DESC $lim");
			if($qri){
				foreach($qri as $row){
					$lid=$row['loan']; $amnt=fnum($row['amount']); $tsum=fnum($row['paid']+$row['balance']); $st=$row['status']; $no++;
					$state = ($st) ? "<span style='font-size:13px;color:green'><i class='bi-check2-circle'></i> Cleared ".date("d-m-Y",$st)."</span>":
					"<span style='font-size:13px;color:#008080'><i class='bi-alarm'></i> Running</span>";
					$css = ($no<count($qri)) ? "style='border-bottom:1px solid #dcdcdc'":""; $disb=date("M d,Y h:i a",$row["disbursement"]);
					
					$ltrs.= "<tr $css onclick=\"window.location.href='?insts=$lid'\"><td>KES $amnt<br><span style='font-size:14px;color:grey'>$disb</span></td>
					<td style='text-align:right'>To-Pay KES $tsum<br>$state</td></tr>";
				}
			}
			
			$qrs = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='checkoff' OR `setting`='use_app_as_topup')");
			if($qrs){
				foreach($qrs as $ro){ $sets[$ro['setting']]=prepare($ro['value']); }
			}
			
			$qri = $db->query(2,"SELECT COUNT(*) AS tot,SUM(amount) AS tsum,SUM(balance+penalty) AS tbal FROM `org$cid"."_loans` WHERE `client_idno`='$idno'");
			$totals = intval($qri[0]['tot']); $tsum=fnum(intval($qri[0]['tsum'])); $cko=(isset($sets["checkoff"])) ? $sets["checkoff"]:0; $nxt=$no+1;
			$click = (intval($qri[0]['tbal'])<=0 or $cko) ? "window.location.href='?apply&nln=$nxt'":"error('Sorry! Clear your running loan first!')";
			$click = ($qry) ? "error('You have a pending application')":$click; $topup=(isset($sets["use_app_as_topup"])) ? $sets["use_app_as_topup"]:0;
			$click = ($topup && !$autodsb) ? "alerts('Application Error','You are not whitelisted for online application')":$click;
			
			$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
				<tr style='height:40px;'><td>
					<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; MY LOANS</h3>
				</td></tr>
				<tr valign='top'><td>
					<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
						<div style='background:$aptheme;color:#fff;border-radius:10px;padding:5px;'>
							<table cellpadding='7' style='width:100%;font-family:signika negative'><tr>
								<td><h3><span style='font-size:14px'>KES</span> $tsum<br><span style='color:#E6E6FA;font-size:13px'>TOTAL BORROWED</span></td>
								<td style='text-align:right'><button class='btnn' onclick=\"$click\"><i class='bi-plus-circle'></i> Request</button></td>
							</tr></table>
						</div><br>
						<h4 style='font-size:20px;color:#4682b4;padding:8px;background:#f0f0f0'>Loan History</h4>
						<table cellpadding='7' style='width:100%;margin-bottom:10px'> $ltrs </table>".limitDiv($page,20,$totals,"loans.php?view")."
					</div>
				</td></tr>
			</table>";
			savelog($sid,"$client viewed loan history");
		}
	}
	
	# apply loan
	if(isset($_GET["apply"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script>window.location.replace('index.php?home'); </script>"; }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"];
		$_SESSION["dsp"]=encrypt(date("F-d-Y"),$idno);
		$nxt = trim($_GET['apply']); $tid=0;
		$prod = (isset($_GET['prod'])) ? trim($_GET['prod']):0;
		$ltbl = "org$cid"."_loans"; $stbl="org$cid"."_staff";
		
		$exclude = array("id","status","time","loan","client","balance","expiry","penalty","disbursement","paid","tid","approvals","clientdes","branch","creator","pref","loantype","disbursed");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = array("id"=>"number","client"=>"text","loan_product"=>"text","client_idno"=>"number","phone"=>"number","branch"=>"number","loan_officer"=>"number",
		"amount"=>"number","tid"=>"number","duration"=>"number","disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number",
		"loan"=>"text","status"=>"number","approvals"=>"textarea","clientdes"=>"textarea","creator"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$reuse = ($res) ? json_decode($res[0]['reuse'],1):[];
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		$dels = array("balance","expiry","penalty","disbursement","paid","clientdes");
		foreach($dels as $del){ unset($fields[$del]); }
		
		if(!in_array("cuts",$db->tableFields(2,"org$cid"."_loantemplates"))){
			$db->execute(2,"ALTER TABLE `org$cid"."_loantemplates` ADD `cuts` LONGTEXT NOT NULL AFTER `prepay`");
			$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `cuts`='[]'");
		}
		
		$defv = array("status"=>0,"time"=>time(),"loan"=>0,"penalty"=>0,"tid"=>0,"creator"=>$sid); 
		foreach(array_keys($fields) as $fld){
			$dvals[$fld] = (isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		$setts = ["loansegment"=>1]; $pcode=$fon="";
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='loansegment' OR `setting`='clientdormancy')");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$specprods = (defined("APP_SPECIAL_PRODUCTS")) ? APP_SPECIAL_PRODUCTS:[];
		$lis=$min=$max=$infs=$ckoamnt=$ckoto=$bypass_lim=$ofid=""; $isnew=1; $done=$docs=[];
		if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
			foreach($fields as $col=>$dtp){ 
				if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ $reuse[]=$col; $docs[$col]=$dtp; }
			}
		}
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		if($sql){
			$crow = $sql[0]; $fon=$crow['contact']; $bid=$crow['branch']; $ofid=$crow['loan_officer']; $clid=$crow["id"];
			$cdm = (isset($setts['clientdormancy'])) ? $setts['clientdormancy']:0; $client=clean(ucwords($crow['name']));
			$qri = $db->query(2,"SELECT *FROM `$ltbl` WHERE `client_idno`='$idno' ORDER BY `time` DESC LIMIT 1"); 
			$isnew = ($qri) ? 0:1; $lastloan=($qri) ? $qri[0]["status"]:0; $lastloan=($qri && !$lastloan) ? time():$lastloan;
			if($cdm){
				$last = strtotime(date("Y-M-d",strtotime("-$cdm month")));
				$isnew = ($lastloan<=$last && $lastloan>0) ? 1:$isnew;
			}
				
			if(!$tid && count($reuse) && $qri){
				foreach($reuse as $col){
					if(isset($qri[0][$col])){
						if($qri[0][$col]){
							if(isset($docs[$col])){
								$doc=$qri[0][$col]; $ext=explode(".",$doc)[1]; $file="$col$idno$nxt.$ext"; $done[$col]=$file;
								if($docs[$col]=="image"){ insertSqlite("photos","INSERT OR IGNORE INTO `images` VALUES('$file','".getphoto($doc)."')"); }
								else{ copy("../docs/$doc","../docs/$file"); }
							}
							else{ $done[$col]=$qri[0][$col]; }
						}
					}
				}
			}
		}
		else{ echo "<script> window.location.replace('index.php?home'); </script>"; exit(); } 
		
		$durs=$payterms=$ftps=$pays=[];
		if($prod){
			$dvals['loan_product']=$prod; 
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod' AND `category`='app'");
			$payterms = json_decode($res[0]['payterms'],1); $min=$res[0]['minamount']; $max=$res[0]['maxamount'];
			if($setts['loansegment']){
				$arr = $res[0]['duration']/$res[0]['interest_duration']; $intv=$res[0]['intervals'];
				for($i=1; $i<=$arr; $i++){
					$durs[]=$i*$res[0]['interest_duration'];
				}
			}
			else{ $durs[]=$res[0]['duration']; }
		}
		
		$cols = $fields; $error="";
		foreach($cols as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="loan_officer"){ $lis.= "<input type='hidden' name='loan_officer' value='$ofid'>"; }
				elseif($field=="branch"){ $lis.= "<input type='hidden' name='branch' value='$bid'>"; }
				elseif($field=="client_idno"){ $lis.= "<input type='hidden' name='client_idno' value='$idno'>"; }
				elseif($field=="phone"){ $lis.= "<input type='hidden' name='phone' id='cphone' value='$fon'>"; }
				elseif($field=="loan_product"){
					$limit = ($idno) ? loanlimit($idno,"app"):[]; 
					$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='app'");
					$opts = "<option value='0'>-- Loan Product --</option>";
					if($res){
						foreach($res as $row){
							$id=$row['id']; $cnd=($id==$dvals[$field]) ? "selected":"";
							if(isset($specprods[$id])){
								$val = (isset($crow[$specprods[$id]])) ? $crow[$specprods[$id]]:"";
								if(strtolower($val)=="yes"){
									$opts.= (isset($limit[$id])) ? "<option value='$id' $cnd>".prepare(ucfirst($row['product']))."</option>":"";
								}
							}
							else{ $opts.= (isset($limit[$id])) ? "<option value='$id' $cnd>".prepare(ucfirst($row['product']))."</option>":""; }
						}
					}
					
					$lis.= "<p>Loan Product<br><select name='$field' id='prd' style='width:100%' 
					onchange=\"window.location.replace('?apply=$nxt&cid=$idno&prod='+this.value)\">$opts</select></p>";
				}
				elseif($field=="duration"){
					$opts="";
					foreach($durs as $dur){
						$cnd = ($dur==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$dur' $cnd>$dur days</option>";
					}
					
					$opts = ($opts) ? $opts:"<option value='0'> -- Select --</option>";
					$lis.="<p>Repayment Duration<br><select name='$field' id='dur' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
					}
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":"<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname=(substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $ran=rand(12345678,87654321); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$val' $cnd></option>";
					}
					
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
					"<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
				}
				elseif($dtype=="textarea"){
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
					"<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{
					$inp = (array_key_exists($dtype,$accept))? "file":$dtype; $add=($inp=="file") ? $accept[$dtype]:""; 
					$val = prepare(ucfirst($dvals[$field])); $place=($field=="amount") ? "placeholder='$min - $max'":"";
					$fid = ($field=="phone") ? "id='cphone'":"id='$field'"; $skip_added=(defined("SKIP_ADDED_LOAN_COLS")) ? SKIP_ADDED_LOAN_COLS:[];
					if($inp=="file" && !isset($done[$field]) && !in_array($field,$skip_added)){ $infs.="$field:"; $ftps[$field]=$dtype; $error.="$fname,"; }
					else{
						$cval = (isset($done[$field])) ? $done[$field]:"0";
						$lis.= (isset($done[$field]) or in_array($field,$skip_added)) ? "<input type='hidden' name='$field' value='$cval'>":
						"<p>$fname<br><input type='$inp' $fid style='width:100%' value=\"$val\" accept='$add' name='$field' $place required></p>";
					}
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$error = ($error) ? "Kindly submit (".rtrim($error,",").") documents for approval":"";
		if($prod){
			foreach($payterms as $key=>$val){
				if(explode(":",$val)[0]==0 && $isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==1){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==6 && !$isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
			}
		}
		
		if(count($pays) && !$error){
			$ops=""; $pst=0;
			if($db->istable(3,"wallets$cid") && !$tid){
				$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$clid' AND `type`='client' AND `balance`>0");
				if($chk){
					$wid = $chk[0]['id']; $bal=walletBal($wid); $pst=$bal;
					$ops = ($bal>0) ? "<input type='hidden' name='payments' value='WALLET:$wid'>":"";
				}
			}
			
			$res = $db->query(2,"SELECT `code`,`amount` FROM `org$cid"."_payments` WHERE (`account`='$idno' OR `account`='$fon') AND `status`='0'");
			if($res){ $lis.= ($pst>=$res[0]['amount']) ? "<input type='hidden' name='payments' value='".$res[0]['code']."'>":$ops; }
			else{
				if($ops){ $lis.=$ops; }
				else{ $error = "Payment for ".implode("+",$pays)." is missing, pay now before you proceed"; }
			}
		}
		
		$nme = str_replace("'","",$client); 
		$error = ($logtp=="staff") ? "You cannot apply loan on behalf of client":$error;
		$submit = ($error) ? "<p style='color:#ff4500;background:#FFDAB9;padding:10px;'><i class='bi-exclamation-octagon'></i> $error</p>":
		"<div style='width:100%;border:1px solid #ccc;padding:4px;line-height:;margin-top:20px'><input type='number' name='otpv' id='otp' style='border:0px;padding:4px;width:150px' 
		placeholder='OTP Code' autocomplete='off' required><a href='javascript:void(0)' onclick=\"requestotp('$fon','$nme')\" style='float:right;margin-top:5px'>
		<i class='fa fa-refresh'></i> Request</a></div><span style='color:grey;font-size:13px'>Dial *456*9*5*5# if you dont get OTP</span><br><br>
		<p><input type='checkbox' class='terms' style='width:30px'></checkbox> &nbsp; I accept <a href='loans.php?terms'>Terms and Conditions</a> for this Loan</p><br>
		<p><button class='sbtn' id='abtn'>Apply Now</button></p>";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; LOAN APPLICATION</h3>
			</td></tr>
			<tr><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
					<div style='padding:10px;margin:0 auto;max-width:320px'>
						<form method='post' id='tform' onsubmit=\"savetemplate(event)\">
							<input type='hidden' name='formkeys' value='".json_encode($cols,1)."'> <input type='hidden' name='syspost' value='$sid'>
							<input type='hidden' name='id' value='$tid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'>
							<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis
							<p>$submit</p>
						</form>
					</div>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client accessed loan application form");
	}
	
	# terms & conditions
	if(isset($_GET["terms"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script>window.location.replace('index.php?home'); </script>"; }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"];
		$sql = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$qri = $db->query(1,"SELECT `value` FROM `settings` WHERE `setting`='app_loanterms' AND `client`='$cid'");
		$client = prepare(ucwords($sql[0]['name'])); $terms=($qri) ? nl2br(prepare($qri[0]['value'])):"";
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; TERMS & CONDITIONS</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>$terms</div>
			</td></tr>
		</table>";
		savelog($sid,"$client viewed loan terms & conditions");
	}
	
	# installments
	if(isset($_GET["insts"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script>window.location.replace('index.php?home'); </script>"; }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"];
		$lid = trim($_GET["insts"]);
		$sql = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$client=prepare(ucwords($sql[0]['name'])); $today=strtotime("Today"); $trs=""; $no=$tbal=$tsum=$start=0; 
		$qrs = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='installmentdate'");
		$idy = ($qrs) ? $qrs[0]['value']:1; $diff=array(-1,0,1)[$idy]; $diff*=86400;
		
		if($db->istable(2,"rescheduled$cid")){
			$res = $db->query(2,"SELECT *FROM `rescheduled$cid` WHERE `loan`='$lid'");
			$start = ($res) ? $res[0]['start']:0;
		}
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid'");
		foreach($qri as $row){
			$day=date("d-m-Y",$row['day']+$diff); $amnt=number_format($row['amount']); $paid=number_format($row['paid']); $bal=number_format($row['balance']); $no++;
			$dy=$row['day']+$diff; $pays=json_decode($row['payments'],1); $pdy=(count($pays)) ? explode(":",array_reverse(array_keys($pays))[0])[1]:0; $color="#000";
			if($today>$dy){
				if($pdy>0){ $color=(strtotime(date("Y-M-d",$pdy))>$dy) ? "#DC143C":"#000"; }
				else{ $color = ($row['balance']>0) ? "#DC143C":"#000"; }
			}
			
			$css = ($no<count($qri)) ? "border-bottom:1px solid #dcdcdc":""; $tbal+=$row['balance']; $tsum+=$row['amount']; $pday=($pdy) ? date("d-m-Y",$pdy):"----";
			$trs.= ($start==$row['day']) ? "<tr style='background:#f0f0f0;color:#4682b4;font-size:14px'><td colspan='2'>Rescheduled</td></tr>":"";
			$trs.= "<tr style='color:$color;$css'><td>KES $amnt<br><span style='font-size:13px;color:grey'>$day</span></td><td style='text-align:center'>Paid $paid<br>
			<span style='font-size:13px;color:grey'>$pday</span></td><td style='text-align:right'>KES $bal<br><span style='font-size:13px;color:grey'>BALANCE</span></td></tr>";
		}
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:20px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; INSTALLMENTS</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative'>
					<div style='background:$aptheme;color:#fff;border-radius:10px;padding:5px;'>
						<table cellpadding='7' style='width:100%;font-family:signika negative'><tr>
							<td><h3><span style='font-size:14px'>KES</span> ".number_format($tsum)."<br><span style='color:#E6E6FA;font-size:13px'>TOTALS TO-PAY</span></td>
							<td style='text-align:right'><h3><span style='font-size:14px'>KES</span> ".number_format($tbal)."<br>
							<span style='color:#E6E6FA;font-size:13px'>TOTAL BALANCE</span></td>
						</tr></table>
					</div><br>
					<h4 style='font-size:20px;color:#4682b4;padding:8px;background:#f0f0f0'>Loan Installments</h4>
					<table cellpadding='7' style='width:100%;margin-bottom:10px'> $trs </table>
				</div>
			</td></tr>
		</table>";
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
		
		function savetemplate(e){
			e.preventDefault();
			var prd = $("#prd").val().trim(),dur=$("#dur").val().trim(),otp=$("#otp").val().trim();
			
			if(prd==0){ alerts("Error! No Loan product selected"); }
			else if(dur==0){ alerts("Error! Select Repayment Duration first"); }
			else if(otp.length!=6){ error("Invalid OTP"); }
			else if($(".terms:checked").length==0){ error("Accept our Terms & conditions first before you proceed!"); }
			else{
				var data = $("#tform").serialize(); 
				$.ajax({
					method:"post",url:"../mfs/dbsave/loans.php",data:data,
					beforeSend:function(){ $("#abtn").hide(); progress("Requesting","Processing...please wait"); },
					complete:function(){ progress(); $("#abtn").show(); },timeout:150000
				}).fail(function(){
					error("Failed: Check internet Connection"); $("#abtn").show();
				}).done(function(res){
					if(res.trim()=="success"){
						success("Success"); window.history.back();
					}
					else{ alerts("Response",res); }
				});
			}
		}
		
	</script>