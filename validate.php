<?php
	// Match cookie path used by index.php so session is available for login
	$basePath = str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? ''));
	if ($basePath !== '' && $basePath !== '/') {
		session_set_cookie_params(['path' => $basePath . '/', 'lifetime' => 0]);
	}
	session_start();
	include "core/functions.php";
	// Allow login POST to proceed even if session wasn't carried over (e.g. cookie path)
	if (!isset($_SESSION['mvbr'])) {
		if (isset($_POST['usern'])) {
			$_SESSION['mvbr'] = time();
		} else {
			echo "Please reload the login page and try again.";
			exit();
		}
	}
	insertSQLite("sitesess","CREATE TABLE IF NOT EXISTS logins (user INTEGER PRIMARY KEY NOT NULL,device TEXT,ipv4 TEXT,lastime INTEGER)");
	insertSQLite("sitesess","CREATE TABLE IF NOT EXISTS resets (user INTEGER PRIMARY KEY NOT NULL,skey TEXT)");
	
	$cid = CLIENT_ID;
	$ltbl = "org".$cid."_staff";
	$ipv4 = (isset($_SERVER[""])) ? :$_SERVER['REMOTE_ADDR'];
	$db = new DBO(); 
	
	if(isset($_POST['usern'])){
		$user = clean(strtolower($_POST['usern']));
		$pass = trim($_POST['passw']);
		$code = rand(123456,654321);
		$exp = time()+600; $setts=[]; $cond="";
		
		if(in_array("login_username",$db->tableFields(2,$ltbl))){
			$cond = "OR `login_username`='$user'";
		}
		
		$qri = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'");
		$data = $db->query(2,"SELECT *FROM `$ltbl` WHERE `name`='$user' OR `email`='$user' $cond");
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		if($qri[0]['status']>0){ echo "Sorry! Your system has been Locked, contact the developer for more Info"; exit(); }
		$otpreq = (isset($setts['login'])) ? $setts['login']:1;
		$otpfor = (isset($setts['otpfor'])) ? json_decode($setts['otpfor'],1):[];
		$resetp = (isset($setts['passreset'])) ? $setts['passreset']:0;
		$systym = (isset($setts["systime"])) ? json_decode($setts["systime"],1):[];
		$otpc = (defined("OTP_CHANNEL")) ? OTP_CHANNEL:"sms";
		
		if($systym){
			if($systym["restrict"]){
				$fro = intval(str_replace(":","",$systym["from"])); $dto=intval(str_replace(":","",$systym["upto"]));
				if(intval(date("Hi"))>=$fro && intval(date("Hi"))<$dto){ }
				else{
					if($data){
						if(!in_array($data[0]["id"],[1,2])){ echo "Oops! The system is restricted from access at this time"; exit(); }
					}
					else{ echo "Oops! System access time is suspended due to Off-hours"; exit(); }
				}
			}
		}
		
		if($data){
			if(count($data)>1){
				$status = $data[0]['status']; 
				foreach($data as $row){
					if($row['status']==0){
						$name=prepare(ucwords($row['name'])); $id=$row['id']; $utm=$row['time']; $def=json_decode($data[0]['config'],1);
						$dbpass=decrypt($def['key'],date("YMd-his",$utm)); $status=$row['status']; $post=$row['position']; $mail=$row["email"]; $fon=$row['contact'];
					}
				}
			}
			else{
				$row=$data[0]; $name=prepare(ucwords($row['name'])); $id=$row['id']; $utm=$row['time']; $def=json_decode($row['config'],1);
				$dbpass=decrypt($def['key'],date("YMd-his",$utm)); $status=$row['status']; $post=$row['position']; $mail=$row["email"]; $fon=$row['contact'];
			}
			
			if($dbpass==$pass){
				if($status==0){
					$res = fetchSQLite("sitesess","SELECT *FROM `logins` WHERE `user`='$id'"); $now=time(); $last=0;
					if(is_array($res)){
						$last = (count($res)) ? $res[0]['lastime']:0;
						$pdev = (count($res)) ? prepare($res[0]['device']):0;
					}
					
					if(($now-$last)<50 && $id>1){
						echo "Sorry! Another session from $pdev is running! Logout first";
					}
					else{
						if($otpreq or in_array($post,$otpfor)){
							$db->execute(1,"REPLACE INTO `otps` VALUES('$fon','$code','$exp')");
							if($otpc=="email"){
								mailto([$mail],["Login OTP"],[frame_email("<h4>".greet($name).",</h4><p>Use code <b>$code</b> as your Login OTP before ".date("h:i a",$exp)."</p>")],[null]);
							}
							else{ sendSMS($fon,"Hi $name, Use code $code as your Login OTP before ".date("h:i a",$exp)); }
							echo "success:?reqotp=".dechex(rand(123456,654321).$id);
						}
						else{
							insertSQLite("sitesess","REPLACE INTO `logins` VALUES('$id','".getagent()."','$ipv4','$now')");
							$mid=dechex(rand(123456,654321).$id); $_SESSION['myacc']=$mid; setcookie("mssn",$mid); $reset=0; $dur=time()+1800; 
							if($post=="assistant"){ $db->execute(2,"UPDATE `$ltbl` SET `roles`='18' WHERE `id`='$id'"); }
							if($resetp){
								$avs = (isset($def['lastreset'])) ? $def['lastreset']:0; $mtm=time()-$avs;
								$reset = ($resetp==1 && intval(date("d"))==1 && $mtm>2419200) ? 1:$reset;
								$reset = ($resetp==15 && intval(date("d"))==15 && $mtm>2419200) ? 1:$reset;
								$reset = ($resetp==10 && $mtm>=864000) ? 1:$reset;
								$reset = ($resetp==28 && $mtm>=2419200) ? 1:$reset;
							}
							
							if($reset){
								insertSQLite("sitesess","REPLACE INTO `resets` VALUES('$id','".dechex($dur.$id)."')");
								echo "success:?reset=".dechex($dur.$id);
							}
							else{ 
								savelog($id,"Logged Into System"); unset($_SESSION['mvbr']);
								echo 'success:mfs/';
							}
						}
					}
				}
				else{
					$states = array(1=>"Your account has been suspended. Contact your employer Inquiry",2=>"Your account is Inactive at the moment! Contact your employer Inquiry");
					echo (isset($states[$status])) ? $states[$status]:"Your account doesnt exist!";
				}
			}
			else{ echo "Login Failed: Incorrect Password"; }
		}
		else{ echo "Login Failed: Incorrect Details"; }
	}
	
	#forgot password
	if(isset($_POST['forgot'])){
		$acc = clean(strip_tags(strtolower($_POST['forgot'])));
		$dur = time()+1800;
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `email`='$acc'");
		if($res){
			if($res[0]['status']==1){ echo "Failed: Your account has been suspended. Contact your employer for more info"; }
			else{
				$email=$res[0]['email']; $name=prepare(ucwords($res[0]['name'])); $id=$res[0]['id']; $rid=dechex($dur.$id);
				$link = shortenUrl("https://".BASE_URL."/?reset=$rid"); $now=time();
				
				$mssg = frame_email("<h4 style='color:#008fff;margin:0px'>".greet($name).",</h4> <p>Proceed to <a href='$link'>Reset Password</a> if you requested for Password change</p>
				<p>The Link is active till ".date("h:i a",$dur)."</p>");
				
				if(mailto([$email],['Password Recovery'],[$mssg],[null])){
					insertSQLite("sitesess","REPLACE INTO `resets` VALUES('$id','$rid')");
					echo "Success! Password reset Link has been sent to $email";
				}
				else{ echo "Failed to complete the request! Try again Later"; }
			}
		}
		else{ echo "Failed: ".prepare($acc)." is not found in the system"; }
	}
	
	# reset password
	if(isset($_POST['vpass'])){
		$pass = trim($_POST['vpass']);
		$src = clean($_POST['vtim']);
		$acc = substr(hexdec($src),10);
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$acc'");
		if($res){
			if(checkpass(trim($_POST['vpass']))){
				$def = json_decode($res[0]['config'],1); $def['lastreset']=time(); $mtm=date("YMd-his",$res[0]['time']);
				$old = decrypt($def['key'],$mtm); $def['key']=encrypt($pass,$mtm); $save=json_encode($def,1);
				$qry = fetchSQLite("sitesess","SELECT *FROM `resets` WHERE `user`='$acc'"); 
				
				if(!isset($qry[0]['skey'])){ echo "Failed! your session doesnt exist!"; }
				elseif($qry[0]['skey']!=$src){ echo "Failed: Invalid session ID for password reset!"; }
				elseif($old==$pass){ echo "Failed: Kindly Use a new password from the former one"; }
				else{
					if($db->execute(2,"UPDATE `$ltbl` SET `config`='$save' WHERE `id`='$acc'")){
						if(!isset($_SESSION["myacc"]) && isset($_COOKIE["mssn"])){ $_SESSION["myacc"]=$_COOKIE["mssn"]; }
						if(isset($_SESSION['myacc'])){
							insertSQLite("sitesess","REPLACE INTO `logins` VALUES('$acc','".getagent()."','$ipv4','".time()."')");
							savelog($acc,"Logged Into System from IP address $ipv4"); unset($_SESSION['mvbr']); 
						}
						echo (isset($_SESSION['myacc'])) ? "success:mfs/":"success:?hm";
					}
					else{ echo "Unknown error has occured! Try again later"; }
				}
			}
			else{
				echo "Password must be at least 8 characters in length and must contain at least one number, one uppercase letter, one lower case letter and one special character.";
			}
		}
		else{ echo "Failed: The Link has no associated account!"; }
	}
	
	# otp verification
	if(isset($_POST['otpid'])){
		$id = substr(hexdec(trim($_POST['otpid'])),6);
		$otp = clean($_POST['code']); 
		$user = staffInfo($id); 
		$fon = $user['contact'];
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='passreset'");
		$resetp = ($sql) ? $sql[0]['value']:0; $def=json_decode($user['config'],1); $reset=0;
		
		$res = $db->query(1,"SELECT *FROM `otps` WHERE `phone`='$fon' AND `otp`='$otp'");
		if($res){
			if(time()>$res[0]['expiry']){ echo "Failed: Your OTP expired at ".date("M d, h:i a",$res[0]['expiry']); }
			else{
				if($resetp){
					$avs = (isset($def['lastreset'])) ? $def['lastreset']:0; $mtm=time()-$avs;
					$reset = ($resetp==1 && intval(date("d"))==1 && $mtm>2419200) ? 1:$reset;
					$reset = ($resetp==15 && intval(date("d"))==15 && $mtm>2419200) ? 1:$reset;
					$reset = ($resetp==10 && $mtm>=864000) ? 1:$reset;
					$reset = ($resetp==28 && $mtm>=2419200) ? 1:$reset;
				}
				
				$mid=dechex(rand(123456,654321).$id); $_SESSION['myacc']=$mid; setcookie("mssn",$mid); $dur=time()+1800;
				if($user['position']=="assistant"){ $db->execute(2,"UPDATE `$ltbl` SET `roles`='18' WHERE `id`='$id'"); }
				if($reset){
					insertSQLite("sitesess","REPLACE INTO `resets` VALUES('$id','".dechex($dur.$id)."')");
					echo "success:?reset=".dechex($dur.$id); 
				}
				else{
					savelog($id,"Logged Into Account"); unset($_SESSION['mvbr']);
					echo "success:mfs/";
				}
			}
		}
		else{ echo "Invalid OTP Code, try again"; }
	}

?>