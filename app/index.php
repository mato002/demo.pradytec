<?php
	session_start();
	ob_start();
	
	require "../core/functions.php";
	require "defcalls.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	$chk = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'");
	$qri = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE `position`='USSD' AND `gender`='app' AND `status`='0'");
	$config = mficlient(); $co=$config['company']; $mcont=$config["contact"]; $mail=$config["email"]; $state=$chk[0]["status"];
	$company =(strlen($co)>25) ? substr($co,0,25):$co; $logo=getphoto($config["logo"]);
	$sid = ($_SERVER["HTTP_HOST"]=="localhost") ? 0:$qri[0]['id'];
	
	$path = ($_SERVER['HTTP_HOST']=="localhost") ? "/mfs":"";
	$loc  = explode("/",$_SERVER['SCRIPT_NAME']); array_pop($loc);
	$url  = implode("/",$loc); $bgcolor="#fff"; $data=""; $rid=0; 
	$aptheme = (defined("APP_COLOR")) ? APP_COLOR:"#2f4f4f";
	
	
	# home
	if(isset($_GET["home"])){
		$get = str_replace(" ","+",trim($_GET["home"]));
		if(isset($_SESSION["clapp"])){ $idno=$_SESSION["clapp"]; }
		elseif(isset($_COOKIE["myapp"])){ $idno=hexdec($_COOKIE["myapp"]); $_SESSION["clapp"]=$idno; }
		else{
			$src = explode("/",$get);
			$key = hexdec(array_shift($src));
			$idno = decrypt(implode("/",$src),$key);
		}
		
		if($idno){
			if(!isset($_COOKIE["bid"])){
				$logtp = (!is_numeric($idno) or isset($_SESSION["use4"])) ? "staff":"client";
				setcookie("bid",encrypt("$logtp:$idno","bkey"),time()+(360*86400),"/");
			}
			else{
				$bid = decrypt($_COOKIE["bid"],"bkey");
				if($bid){
					$def = explode(":",$bid); $logtp=$def[0]; $idno=$def[1];
					if(!$idno){
						$src = explode("/",$get); $key=hexdec(array_shift($src)); $idno=decrypt(implode("/",$src),$key);
					}
				}
				else{ echo "<script> window.location.replace('index.php?logout'); </script>"; exit(); }
			}
	
			if($logtp=="staff"){
				if(isset($_SESSION["use4"])){ $idno=$_SESSION["use4"]; $_SESSION["clapp"]=$idno; $_COOKIE["myapp"]=dechex($idno); }
				else{
					$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `jobno`='$idno'");
					if($sql){
						$qri = $db->query(2,"SELECT `idno` FROM `org$cid"."_clients` WHERE `status`<2 LIMIT 1");
						if($qri){ $_SESSION["clapp"]=$idno; $idno=$qri[0]['idno']; $rid=$sql[0]['id']; }
						else{ echo "<script> window.location.replace('index.php?logout'); </script>"; exit(); }
					}
					else{ echo "<script> window.location.replace('index.php?logout'); </script>"; exit(); }
				}
			}
			
			if(!isset($_SESSION["clapp"])){ $_SESSION["clapp"]=$idno; }
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			if(!$sql or $state){ echo "<script> window.location.replace('index.php?logout'); </script>"; exit(); }
			
			$qrs = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='installmentdate'");
			$idy = ($qrs) ? $qrs[0]['value']:1; $diff=array(-1,0,1)[$idy]; $diff*=86400; $tdy=strtotime(date("Y-M-d")); $tdy+=$diff;  
			$cdef = json_decode($sql[0]['cdef'],1); $fon=$sql[0]['contact']; $client=prepare(ucwords($sql[0]['name'])); 
			$mpoints = (isset($cdef["mpoints"])) ? $cdef["mpoints"]:0; $ltrs=$lis=""; $no=0;
			
			# login
			if($logtp=="client" && (!isset($cdef["ckey"]) or !isset($_COOKIE["myapp"]) or $state)){
				if(isset($cdef["ckey"])){
					$otp = rand(123456,654321); $exp=time()+600;
					$db->execute(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')");
					$login = "<p>Enter Login PIN<br><input type='hidden' name='otpv' id='otp' value='$otp'></p>".passwdInput("lpin","","4 Numbers").""; 
				}
				else{
					$login = "<p>Set Login PIN</p>".passwdInput("lpin","","4 Numbers")."
					<div style='width:100%;border:1px solid #ccc;padding:4px;'><input type='number' name='otpv' 
					style='border:0px;padding:4px;width:150px;' placeholder='OTP Code' id='otp' autocomplete='off' required><a href='javascript:void(0)' 
					onclick=\"requestotp('$fon','".str_replace("'","",$client)."')\" style='float:right;margin-top:5px'><i class='fa fa-refresh'></i> Request</a></div>
					<span style='color:grey;font-size:13px'>Dial *456*9*5*5# if you dont get OTP</span><br>";
				}
				
				$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
					<tr><td>
						<div style='max-height:100%;width:100%;overflow:auto;font-family:signika negative'>
							<div style='padding:10px;margin:0 auto;max-width:300px'>
								<p style='text-align:center'><img src='data:image/jpg;base64,$logo' style='max-width:100%;max-height:150px'></p>
								<p style='font-family:acme'>".greet(explode(" ",$client)[0])."</p>
								<form method='post' id='lform' onsubmit=\"login(event,'client')\">
									<input type='hidden' name='cidno' value='$idno'>$login<br><p><button class='sbtn'><i class='bi-box-arrow-in-right'></i> Proceed</button></p>
									<p><button class='sbtn' style='background:#8B0000' type='reset' onclick=\"window.location.href='?logout'\"><i class='bi-box-arrow-right'></i> Logout</button></p>
								</form>
							</div>
						</div>
					</td></tr>
				</table>";
			}
			elseif($logtp=="staff" && (!isset($_SESSION["use4"]) or $state)){
				$me = staffInfo($rid); $sname=prepare(ucwords($me['name'])); 
				$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
				$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$show;
				$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$rid'":$show;
		
				$qri = $db->query(2,"SELECT `idno`,`name` FROM `org$cid"."_clients` WHERE `status`<2 $cond ORDER BY `name` ASC");
				foreach($qri as $row){
					$cnd = ($idno==$row['idno']) ? "selected":"";
					$lis.= "<option value='".$row['idno']."' $cnd>".prepare(ucwords($row["name"]))."</option>";
				}
				
				$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
					<tr><td>
						<div style='max-height:100%;width:100%;overflow:auto;font-family:signika negative'>
							<div style='padding:10px;margin:0 auto;max-width:300px'>
								<p style='text-align:center'><img src='data:image/jpg;base64,$logo' style='max-width:100%;max-height:150px'></p>
								<p style='font-family:acme'>".greet(explode(" ",$sname)[0])."</p>
								<form method='post' id='sform' onsubmit=\"login(event,'staff')\">
									<input type='hidden' name='stfid' value='$rid'>
									<p>Client Account to Use<br><select style='width:100%' name='clacc' required>$lis</select></p>
									<span>Your Login Password</span><br>".passwdInput("spass")."
									<br><p><button class='sbtn'><i class='bi-box-arrow-in-right'></i> Proceed</button></p>
									<p><button class='sbtn' style='background:#8B0000' type='reset' onclick=\"window.location.href='?logout'\"><i class='bi-box-arrow-right'></i> Logout</button></p>
								</form>
							</div>
						</div>
					</td></tr>
				</table>";
			}
			else{
				$tbal=$pen=$paid=$lamnt=0; $disb=time(); $lids=[]; $lid="N";
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (penalty+balance)>0");
				if($qri){
					foreach($qri as $row){
						$disb=$row["disbursement"]; $lid=$row["loan"]; $lids[]="`status`='$lid'";
						$tbal+=$row["balance"]+$row["penalty"]; $lamnt+=$row["amount"]; $pen+=$row["penalty"]; $paid+=$row["paid"];
					}
				}
				
				$qry = $db->query(2,"SELECT SUM(balance) AS arrs FROM `org$cid"."_schedule` WHERE `idno`='$idno' AND `balance`>0 AND `day`<$tdy");
				$fon=$sql[0]['contact']; $cyc=$sql[0]['cycles']; $bgcolor="#F8F8FF"; $sts=($lids) ? "OR ".implode(" OR ",$lids):"";
				$arreas=intval($qry[0]['arrs']); $lst=($qri) ? $qri[0]["status"]:0;
				
				$qry = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `account`='$idno' OR `account`='$fon' $sts ORDER BY `date` DESC LIMIT 4");
				if($qry){
					foreach($qry as $row){
						$code=$row['code']; $amnt=fnum($row['amount'],2); $dy=$row['date']; $st=$row['status']; $no++;
						$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
						$state = ($st=="0") ? "<span style='font-size:13px;color:grey'><i class='bi-alarm'></i> Pending</span>":
						"<span style='font-size:13px;color:green'><i class='bi-check2-circle'></i> Confirmed</span>";
						$css = ($no<count($qry)) ? "style='border-bottom:1px solid #dcdcdc'":"";
						$ltrs.= "<tr $css><td>$code<br><span style='font-size:14px;color:grey'>".date("M d,Y h:i a",$ptm)."</span></td><td style='text-align:right'>KES $amnt<br>$state</td></tr>";
					}
				}
				
				$ltrs = ($ltrs) ? $ltrs:"<tr><td style='color:grey'><i class='bi-info-circle'></i> No transactions Found</td></tr>";
				$fro=time()-(30*86400); $dto=time(); $trs="";
				$res = smslogs(["logfrom"=>$fro,"logto"=>$dto,"limit"=>[0,5],"orderby"=>"DESC","strphone"=>$fon]);
				$data = ($res['response']=="success") ? $res['data']:[]; $no=0;
				foreach($data as $row){
					$day=$row['time']; $mssg=nl2br(prepare($row['message'])); $snid=$row['senderId']; $no++;
					$st=$row['status']; $cost=explode("/",$row['cost']); $len=strlen(strip_tags($mssg));
					
					if(strpos(strtolower($mssg),"use code")!==false){
						$def = explode(" ",$mssg);
						foreach($def as $key=>$word){
							if(is_numeric($word)){ $def[$key]="******"; }
						}
						$mssg = implode(" ",$def);
					}
					
					$tot = ($len<161) ? "1 Message ($len characters)":ceil($len/160)." Messages ($len characters)";
					$css = ($no<count($data)) ? "style='margin:0px;padding:8px 0px;border-bottom:1px solid #dcdcdc'":"";
					$state = ($st==200) ? "<span style='background:green;padding:3px;color:#fff;border-radius:3px;float:right;font-size:13px'>Success</span>":
					"<span style='background:#4682b4;padding:3px;color:#fff;border-radius:3px;float:right;font-size:13px'>Sent</span>";
					
					$trs.= "<tr $css><td><p style='font-weight:bold;color:#191970;margin:10px 0px 0px 0px;'>$day</p> <div style='padding:10px 0px 0px 0px;border-bottom:1px solid #dcdcdc'>
					$mssg <p style='padding:5px;background:#dcdcdc;color:#191970;margin:10px 0px 0px 0px'>$tot</p></div>
					<p style='margin-top:10px'>From: $snid $state</p></td></tr>";
				}
				
				$trs = ($trs) ? $trs:"<tr><td style='color:grey'><i class='bi-info-circle'></i> No System SMS Found</td></tr>";
				$iclick = ($lid=="N") ? "toast('You have no Loans')":"window.location.href='loans.php?insts=$lid'"; 
				
				if(!$db->istable(3,"wallets$cid")){
					$db->createTbl(3,"wallets$cid",["client"=>"INT","type"=>"CHAR","investbal"=>"CHAR","savings"=>"CHAR","balance"=>"CHAR","status"=>"INT","time"=>"INT"]); 
				}
				
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'"); $uid=$sql[0]['id'];
				$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
				if(!$qri){
					$db->execute(3,"INSERT INTO `wallets$cid` VALUES(NULL,'$uid','client','0','0','0','0','".time()."')"); 
					$qri = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
				}
				
				$row = $qri[0]; $wid=$row['id']; 
				$wbal = roundnum($row["investbal"]+$row["balance"]+$row["savings"]);
				
				$myloan = ($lamnt) ? "<table cellpadding='5' style='width:100%;margin-top:10px'>
					<tr><td colspan='3'><h3 style='color:#4682b4;margin:0px;font-size:22px'>KES ".fnum($lamnt)."</h3>
					<p style='color:grey;font-size:14px'>".date("d-m-Y, h:i A",$disb)."</p><hr></td></tr>
					<tr><td style='border-right:1px solid #dcdcdc'><b>".fnum($paid)."</b><br><span style='font-size:13px;color:grey'>PAID</span></td>
					<td style='border-right:1px solid #dcdcdc'><b>".fnum($pen)."</b><br><span style='font-size:13px;color:grey'>PENALTIES</span></td>
					<td><b>".fnum($tbal)."</b><br><span style='font-size:13px;color:grey'>BALANCE</span></td></tr>
				</table>":"<br><h3 style='color:#4682b4'>KES 0.00</h3>";
				
				$data = "<div class=''>
					<div style='background:$aptheme;height:280px;color:#fff;padding:20px 15px'>
						<h3 style='font-size:18px;font-weight:;font-family:acme'><i class='bi-house-door'></i> &nbsp; $company
						<i class='bi-box-arrow-right' onclick=\"window.location.href='?logout'\" style='float:right;font-size:25px;color:#F0E68C'></i></h3>
						<p style='font-family:acme;margin-top:18px;color:#F0E68C'>".greet(", ".explode(" ",strtoupper($client))[0])."</p>
						<div style='height:125px;background:#0C2845;padding:0px 5px'>
							<table cellpadding='8' style='width:100%;font-family:Signika negative'><tr>
								<td style='width:70%;color:#fff;'>
									<h3 style='font-size:22px;font-weight:bold;color:#7FFF00'>Ksh ".fnum($wbal)."</h3>
									<p style='color:#ccc;font-size:15px;margin-bottom:10px'>Account Balances</p>
									<p><span style='padding:5px 8px;background:#325d81' onclick=\"window.location.href='payments.php?wallet=$wid:1'\">
									<i class='bi-wallet2'></i> View Account</span></p>
								</td>
								<td style='color:#fff;text-align:right'>
									<button class='btnn' style='background:#325d81;padding:5px;min-width:60px;font-size:15px;color:#7FFF00' onclick=\"window.location.href='payments.php?walletop'\">
									<i class='bi-plus-square'></i><br> Topup</a></button>
								</td>
							</tr></table>
						</div>
					</div>
					<div style='padding:20px 15PX;background:transparent;max-width:550px;margin:0 auto;margin-top:-70px;'>
						<div style='padding:10px;background:#fff;margin:0 auto;box-shadow:0px 1px 3px #ccc;min-height:100px;font-family:signika negative;text-align:center'>
							<h3 style='font-size:18px;'>My Running Loans</h3> $myloan
						</div>
						<div class='row mt-2'>
							<div class='col-12 mt-1 mb-1'>
								<div style='padding:5px;background:#fff;box-shadow:0px 1px 3px #ccc'>
									<table cellpadding='5' style='width:100%;font-family:signika negative;text-align:center'><tr>
										<td><b style='color:#DC143C'>".fnum($arreas)."</b><br><span style='color:grey;font-size:13px;'>ARREARS</span></td>
										<td><b>".fnum(max(loanlimit($idno,"app")))."</b><br><span style='color:grey;font-size:13px;'>LOAN LIMIT</span></td>
										<td><b>".fnum($mpoints)."</b><br><span style='color:grey;font-size:13px;'>M-POINTS</span></td>
									</tr></table>
								</div>
							</div>
							<div class='col-6 mb-3 mt-2'>
								<div class='hcard' onclick=\"window.location.href='?account'\">
									<p style='margin:0px'><i class='bi-person-circle' style='font-size:30px;color:grey'></i></p>
									<p style='margin:0px;padding-bottom:10px;font-weight:bold;font-size:13px;border-bottom:1px solid #ccc'>My Account</p>
									<p style='margin:0px;color:grey;font-size:13px;padding-top:5px;font-family:arial;'>Registered Account information</p>
								</div>
							</div>
							<div class='col-6 mb-3 mt-2'>
								<div class='hcard' onclick=\"window.location.href='loans.php?view'\">
									<p style='margin:0px'><i class='bi-cart4' style='font-size:30px;color:grey'></i></p>
									<p style='margin:0px;padding-bottom:10px;font-weight:bold;font-size:13px;border-bottom:1px solid #ccc'>My Loans</p>
									<p style='margin:0px;color:grey;font-size:13px;padding-top:5px;font-family:arial'>View both active & Completed Loans</p>
								</div>
							</div>
							<div class='col-6 mb-3'>
								<div class='hcard' onclick=\"$iclick\">
									<p style='margin:0px'><i class='bi-calendar-week' style='font-size:30px;color:grey'></i></p>
									<p style='margin:0px;padding-bottom:10px;font-weight:bold;font-size:13px;border-bottom:1px solid #ccc'>Installments</p>
									<p style='margin:0px;color:grey;font-size:13px;padding-top:5px;font-family:arial'>Current Loan Repayment schedule</p>
								</div>
							</div>
							<div class='col-6 mb-3'>
								<div class='hcard' onclick=\"window.location.href='payments.php?view'\">
									<p style='margin:0px'><i class='bi-cash-coin' style='font-size:30px;color:grey'></i></p>
									<p style='margin:0px;padding-bottom:10px;font-weight:bold;font-size:13px;border-bottom:1px solid #ccc'>Payments</p>
									<p style='margin:0px;color:grey;font-size:13px;padding-top:5px;font-family:arial;'>View Loan Associated payments</p>
								</div>
							</div>
							<div class='col-12 mb-3'>
								<div class='cardv' style='font-family:signika negative'>
									<h4 style='font-size:18px;color:#191970;'>Recent Loan Transactions 
									<i class='bi-box-arrow-up-right' style='float:right' onclick=\"window.location.href='payments.php?view=$get'\"></i></h4><hr style='margin-top:0px'>
									<table cellpadding='7' style='width:100%'> $ltrs </table>
								</div>
							</div>
							<div class='col-12 mb-3'>
								<div class='cardv' style='font-family:signika negative'>
									<h4 style='font-size:18px;color:#191970;'>Recent System SMS</h4><hr style='margin-top:0px'>
									<table cellpadding='7' style='width:100%'> $trs </table>
								</div>
							</div>
							<div class='col-12'>
								<div class='cardv' style='font-family:signika negative;text-align:center'>
									<h4 style='color:#191970;font-size:20px'>Contact us</h4>
									<table style='width:250px;margin:0 auto'><tr>
										<td><i class='bi-telephone-plus' onclick=\"location.href='tel:$mcont'\" style='font-size:35px;color:#4682b4'></i><br>
										<span style='color:grey;font-size:13px'>Phone</span></td>
										<td><i class='bi-envelope' onclick=\"location.href='mailto:$mail'\" style='font-size:35px;color:#9370DB'></i><br>
										<span style='color:grey;font-size:13px'> Email</span></td>
										<td><i class='bi-chat-left-text' onclick=\"location.href='chat.php'\" style='font-size:35px;color:#4B0082'></i><br>
										<span style='color:grey;font-size:13px'> Chat</span></td>
									</tr></table>
								</div>
							</div>
						</div>
					</div>
				</div>";
				savelog($sid,"$client accessed application homepage");
			}
		}
	}
	
	# account
	if(isset($_GET["account"])){
		if(!isset($_SESSION["clapp"])){
			if(!isset($_COOKIE['myapp'])){ echo "<script>window.location.replace('?home'); </script>"; }
			$_SESSION["clapp"]=hexdec($_COOKIE['myapp']);
		}
		
		$idno = $_SESSION["clapp"];
		$tbl = "org$cid"."_clients";
		$exclude = array("id","cdef","time","gender","status","name","creator");
		$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `idno`='$idno'"); $cinfo=($res) ? $res[0]:[];
		if(count($cinfo)<1){
			echo "<script> window.history.back(); </script>"; exit();
		}
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $doc="";
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if($dtp=="image"){
					$res = $db->query(2,"SELECT $col FROM `org".$cid."_clients` WHERE `idno`='$idno'"); $df=$res[0][$col]; unset($ftc[$col]); 
					if(strlen($res[0][$col])>2){ $pic = getphoto($df); $img="data:image/".explode(".",$df)[1].";base64,$pic"; }
					else{ $img = "$path/docs/img/tempimg.png"; }
					$doc .= "<div class='col-6 col-sm-4 col-md-3 col-lg-2 mt-2' style='text-align:center;'>
					<img src='$img' style='height:100px;cursor:pointer;max-width:100%'><br><span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span></div>";
				}
			}
		}
		
		$fields = array_keys($ftc);
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		foreach($res as $row){
			$brans[$row['id']]=prepare(ucwords($row['branch']));
		}
		
		$stbl="org$cid"."_staff"; $staff=[]; $lcl=0;
		$res = $db->query(2,"SELECT `id`,`name`,`contact` FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $fons[$row['id']]=$row['contact'];
		}
		
		$row=$cinfo; $client=prepare(ucwords($row['name'])); $trs=""; $no=0;
		foreach($ftc as $col=>$dtp){
			if(!in_array($col,$exclude)){
				$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
				$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
				$val = ($col=="loan_officer") ? $staff[$row[$col]]." &nbsp; <a href='tel:".$fons[$row[$col]]."'><i style='font-size:22px' class='bi-telephone-plus'></i></a>":$val;
				$val = (strlen($row[$col])>50) ? substr($row[$col],0,50)."...<a href=\"javascript:alerts('$val')\">View</a>":$val;
				$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
				$txt = ($col=="cycles") ? "Loan Cycles":ucfirst(str_replace("_"," ",$col));
				$trs.= "<tr valign='top' style='border-bottom:1px solid #dcdcdc'><td><b>$txt</b></td><td style='text-align:right'>$val</td></tr>";
			}
		}
		
		$data = "<table style='width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0;' cellspacing='0' cellpadding='15'>
			<tr style='height:40px;'><td>
				<h3 style='font-size:18px;font-family:acme;color:#191970;margin:0px'><i class='bi-arrow-left' onclick=\"window.history.back()\"></i> &nbsp; MY ACCOUNT</h3>
			</td></tr>
			<tr valign='top'><td>
				<div style='height:100%;width:100%;overflow:auto;font-family:signika negative;'>
					<div class='row' style='margin:0px'>
						<div class='col-12 mb-4'>
							<div class='row'>$doc</div>
						</div>
						<div class='col-12 col-md-12 col-lg-3 mb-2'>
							<p style='padding:6px;background:#f0f0f0;text-align:center;border:1px solid #dcdcdc'><b>$client</b></p>
							<div style='overflow:auto;width:100%;max-height:500px'>
								<table cellpadding='8' style='width:100%;border-top:0px;font-size:15px'>$trs</table>
							</div>
						</div>
					</div>
				</div>
			</td></tr>
		</table>";
		savelog($sid,"$client viewed personal account info");
	}
	
	# logout
	if(isset($_GET["logout"])){
		unset($_SESSION["clapp"]); unset($_SESSION["use4"]); setcookie('myapp',null,time()-3600,"/"); setcookie('bid',null,time()-3600,"/");
		sleep(1); echo "<script> window.NativeJavascriptInterface.clearDataCache('me'); </script>"; exit();
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
	<body style="background:<?php echo $bgcolor; ?>">
		<?php echo $data; ?>	
	</body>
	</html>
	
	<script>
	
		function login(e,ltp){
			e.preventDefault();
			if(ltp=="client"){
				var pin = $("#psin").val().trim(), otp=$("#otp").val().trim();
				if(pin.length!=4){ error("PIN Must be 4 numbers only!"); $("#psin").focus(); }
				else if(otp.length!=6){ error("OTP Must be 6 numbers"); $("#otp").focus(); }
				else{
					var data = $("#lform").serialize();
					$.ajax({
						method:"post",url:"post.php",data:data,
						beforeSend:function(){ progress("Verification","Processing...please wait"); },
						complete:function(){ progress(); }
					}).fail(function(){
						error("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim()=="success"){
							success("Success"); window.location.reload();
						}
						else{ alerts("Response",res); }
					});
				}
			}
			else{
				var data = $("#sform").serialize();
				$.ajax({
					method:"post",url:"post.php",data:data,
					beforeSend:function(){ progress("Verification","Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					error("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						success("Success"); window.location.reload();
					}
					else{ alerts("Response",res); }
				});
			}
		}
		
	</script>