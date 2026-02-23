<?php
	// Use same cookie path for session so login AJAX (validate.php) receives the session
	$basePath = str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? ''));
	if ($basePath !== '' && $basePath !== '/') {
		session_set_cookie_params(['path' => $basePath . '/', 'lifetime' => 0]);
	}
	session_start();
	ob_start();
	require "core/functions.php";
	$config = mficlient();
	
	if(isset($_SESSION['myacc'])){ unset($_SESSION['myacc']); }
	if(!isset($_SESSION['mvbr'])){ $_SESSION['mvbr']=time(); }
	$company = ucwords(strtolower(isset($config['company'])?$config['company']:'Login'));
	$domain = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs":$_SERVER['HTTP_HOST'];
	
	$needInstall = ($company==='Setup' || (isset($config['company']) && $config['company']==='Setup'));
	if($needInstall && !isset($_GET['install'])){
		header('Location: install_tables.php');
		exit;
	}
	
	checktables(); updatesys();
	if(is_dir("assets/css")){ @file_put_contents("assets/css/theme.css",":root { --pcolor: ".COLORS[0]."; --scolor: ".COLORS[1]."; --opcolor:".THEME['opacity']." }"); }
	
	if(isset($_GET['reqotp'])){
		$title = "Account Verify";
		$uid = trim($_GET['reqotp']);
		
		$data = "<form method='post' id='vform' onsubmit='validate(event)'>
			<input type='hidden' name='otpid' value='$uid'>
			<p style='margin-top:16px'>Enter the OTP sent to Phone number linked to the account to proceed</p>
			<table style='width:100%' cellpadding='8'>
				<tr><td style='width:30px'><i class='fa fa-key' style='font-size:22px;'></i></td>
				<td><p><input type='number' placeholder='Enter OTP' name='code' autocomplete='off' autofocus required></p></td></tr>
				<tr><td colspan='2' style='padding:20px 0px'><button type='submit' class='btn1'><i class='fa fa-check'></i> Validate</button>
				<div class='progbtn'>Processing...please wait</div></td></tr>
			</table>
		</form>";
	}
	elseif(isset($_GET['reset'])){
		$title = "Password Reset";
		$utm = hexdec(trim($_GET['reset']));
		
		if(strlen($utm)>10){
			$exp = substr($utm,0,10);
			$data = (time()<$exp) ? "<form method='post' id='vform' onsubmit='changepass(event)'>
				<input type='hidden' name='vtim' value='".trim($_GET['reset'])."'>
				<p style='text-align:center'>Update your account password to proceed</p>
				<table style='width:100%' cellpadding='8'>
					<tr><td style='width:30px'><i class='fa fa-key' style='font-size:22px;'></i></td>
					<td><p><input type='password' placeholder='New Password' name='vpass' id='pass1' autocomplete='off' autofocus required></p></td></tr>
					<tr><td style='width:30px'><i class='fa fa-clone' style='font-size:22px;'></i></td>
					<td><p><input type='password' placeholder='Re-type Password' id='pass2' autocomplete='off' required></p></td></tr>
					<tr><td colspan='2' style='padding:20px 0px'><button type='submit' class='btn1'><i class='fa fa-refresh'></i> Update</button>
					<div class='progbtn'>Processing...please wait</div></td></tr>
				</table>
			</form>":"<br><p style='color:#DC143C;padding:10px;border:1px solid pink;background:#FFF0F5;text-align:center'>
			<i class='fa fa-exclamation-triangle' style='font-size:40px'></i><br> Sorry! The Link Expired on ".date("F d, h:i a",$exp)."</p><br>";
		}
		else{
			$data = "<br><p style='color:#DC143C;padding:10px;border:1px solid pink;background:#FFF0F5;text-align:center'>
			<i class='fa fa-exclamation-triangle' style='font-size:40px'></i><br> Invalid Reset Link. Try again </p><br>"; 
		}
	}
	elseif(isset($_GET['forgot'])){
		$title = "Password Recovery";
		$data = "<form method='post' id='rform' onsubmit='genreset(event)'>
			<table style='width:100%' cellpadding='8'>
				<tr><td colspan='2'><p>Enter your email address linked to the account</p></td></tr>
				<tr><td style='width:30px'><i class='fa fa-envelope-o' style='font-size:22px'></i></td>
				<td><p><input type='text' name='forgot' autocomplete='off' placeholder='Your Email address' autofocus required></p></td></tr>
				<tr><td colspan='2' style='padding:20px 0px'><button type='submit' class='btn1'><i class='fa fa-check'></i> Confirm</button>
				<div class='progbtn'>Processing...please wait</div></td></tr>
			</table>
		</form>";
	}
	else{
		$title = "Account Login";
		$reset = (RESET_PASS) ? "<p style='text-align:right;'><a href='?forgot'>Forgot Password?</a></p>":"";
		
		$data = "<form method='post' id='logform' onsubmit=\"login(event)\">
			<table style='width:100%' cellpadding='8'>
				<tr><td style='width:30px'><i class='fa fa-user-o' style='font-size:21px;'></i></td>
				<td><p><input type='text' placeholder='Username or Email address' name='usern' autocomplete='off' autofocus required></p></td></tr>
				<tr><td><i class='fa fa-lock' style='font-size:25px;'></i></td>
				<td><p><input type='password' placeholder='Password' name='passw' required></p></td></tr>
				<tr><td colspan='2' style='padding:20px 0px'><button type='submit' class='btn1'><i class='fa fa-caret-right'></i> Login</button>
				<div class='progbtn'>Processing...please wait</div></td></tr>
			</table>
		</form> $reset";
	}
	
	ob_end_flush();

?>
	
	<!DOCTYPE HTML>
	<html>
	<head>
		<title><?php echo "$company | $title"; ?></title>
		<meta charset="utf-8">
		<meta name="viewport"content="width=device-width,initial-scale=1.0,user-scalable=no,user-scalable=0">
		<meta name="theme-color" content="<?php echo THEME['bgcolor']; ?>"/>
		
		<meta property="og:description" content="<?php ucwords(strip_tags($config['address'])); ?>"/>
		<meta property="og:url" content="https://<?php echo $domain; ?>"/>
		<meta property="og:title" content="<?php echo $company; ?> | Login"/>
		<meta property="og:image" itemprop="image" content="https://<?php echo $domain; ?>/docs/img/prop.jpg"/>
		<meta property="og:image:type" content="image/jpg"/>
		<meta property="og:image:width" content="248"/>
		<meta property="og:image:height" content="248"/>
		<meta property="og:type" content="website"/>

		<link rel="shortcut icon" href="docs/img/favicon.ico">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link href="https://fonts.googleapis.com/css?family=Signika Negative&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="assets/css/bootstrap4.css">
		<link rel="stylesheet" href="assets/css/login.css?<?php echo rand(12345,54321); ?>">
		<script src="assets/js/jquery.js"></script> <script src="assets/js/common.js"></script>
		<script src="assets/js/login.js?<?php echo rand(12345,54321); ?>"></script>
		<style>body,html{background:<?php echo THEME['bgcolor']; ?>;height:100%;width:100%;font-size:16px;}</style>
	</head>

	<body>
		<?php
			if(THEME['type']==1){
				echo "<br><div class='main1'>
					<center><img src='data:image/jpg;base64,".getphoto($config['logo'])."' style='max-height:100px;max-width:100%'>
						<h3 style='color:var(--pcolor);padding-top:10px;font-size:23px'>$title</h3>
					</center>
					<div class='contents1'> $data <hr>
						<p style='text-align:center;color:#191970;margin:0px'>Copyright &copy; $company ".date('Y')."</p>
					</div>
				</div>";
			}
			elseif(THEME['type']==2){
				echo "<table style='height:100%;width:100%;font-family:signika negative'>
					<tr><td>
						<div class='container'>
							<div class='row'>
								<div class='col-12 col-md-6 col-lg-5 mb-4'>
									<center><img src='data:image/jpg;base64,".getphoto($config['logo'])."' class='logo'></center>
								</div>
								<div class='col-12 col-md-6 col-lg-7'>
									<div class='main2'>
										<h3 style='color:var(--scolor);font-size:23px;text-align:center'>$title</h3><br>
										<div class='contents2'>$data</div>
									</div>
								</div>
							</div>
						</div>
					</td></tr>
					<tr style='height:60px;border-top:1px solid #2f4f4f'><td style='text-align:center;color:#dcdcdc;'>Copyright &copy; $company ".date('Y')."</td></tr>
				</table>";
			}
		
		?>
	</body>
	</html>