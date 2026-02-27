<?php
	session_start();
	ob_start();
	include "../core/functions.php";
	$cid = CLIENT_ID;
	
	if(isset($_SESSION['myacc'])){
		$sid = substr(hexdec($_SESSION['myacc']),6);
		$db = new DBO(); $stbl = "org".$cid."_staff";
		
		$row = $db->query(2,"SELECT *FROM `$stbl` WHERE `id`='$sid'")[0];
		$user = prepare(ucwords($row['name'])); $cnf=json_decode($row['config'],1); $idno=$row['idno']; 
		$perms = explode(",",$row['roles']); $prof=$cnf['profile']; $gen=$row['gender']; 
		$name = (strlen($user)>20) ? substr($user,0,20):$user; $post=$row['position'];
		$uset = ($row['roles']==18 && $post=="assistant") ? $sid:0;
		$gset = (!$uset && !isset($_SESSION["logas"]) && count(staffPost($sid))>1) ? $sid:0;
		
		$config = mficlient(); $co=$config['company']; 
		$mfi = json_decode(decrypt($config["smds"],"syszn$cid"),1);
		$company =(strlen($co)>20) ? substr($co,0,20):$co;
		
		$gif = base64_encode(file_get_contents("../assets/img/loading.gif"));
		// Use path from constants (so demo.pradtec subfolder works); do not override
		if(!isset($path) || $path===''){ $path = ($_SERVER['HTTP_HOST']=="localhost") ? "/mfs":""; }
		$loc  = explode("/",$_SERVER['SCRIPT_NAME']); array_pop($loc);
		$url  = implode("/",$loc);
		
		$icon = ($gen=="male") ? "male.png":"fem.png";
		$profile = ($prof=="none") ? "$path/assets/img/$icon":"data:image/jpg;base64,".getphoto($prof);
		$data = $db->query(1,"SELECT *FROM `useroles`"); $roles=[];
		foreach($data as $row){
			if(in_array($row['id'],$perms) && in_array($row["cluster"],$mfi)){ $roles[]=prepare($row['role']); }
		}
		
		$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
		$b2c_apps = ($qri) ? json_decode($qri[0]['platforms'],1):[]; 
		$b2c_users = ($qri) ? json_decode($qri[0]['users'],1):[];
		$hidebrn = (defined("HIDE_BRANCHES_TAB")) ? HIDE_BRANCHES_TAB:0;
		$loantab = (defined("LOAN_TEXTS")) ? LOAN_TEXTS["tab"]:"LoanBook";
		$newloan = (defined("LOAN_TEXTS")) ? LOAN_TEXTS["new"]:"Create Application";
		$vtemp = (defined("LOAN_TEXTS")) ? LOAN_TEXTS["temps"]:"Loan Applications";
		
		$qri = $db->query(1,"SELECT COUNT(*) AS tot FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		$sets = array("arrears_handlers","show_coll_rates","allow_staff_loans","checkoff");
		foreach($sets as $set){ $sgt[]="`setting`='$set'"; }
		$ahandlers=$setts=[]; $collrate=$stfloans=$docko=0; $apploans=($qri) ? intval($qri[0]["tot"]):0;
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (".implode(" OR ",$sgt).")");
		if($sql){
			foreach($sql as $row){ $setts[$row['setting']]=prepare($row['value']); }
			$collrate = (isset($setts['show_coll_rates'])) ? $setts['show_coll_rates']:0;
			$stfloans = (isset($setts['allow_staff_loans'])) ? $setts['allow_staff_loans']:0;
			$docko = (isset($setts['checkoff'])) ? $setts['checkoff']:0;
			if(isset($setts['arrears_handlers'])){
				$ahandlers[]=json_decode($sql[0]['value'],1); array_push($ahandlers,"super user");
			}
		}
		array_push($ahandlers,"super user");
	?>
	
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="theme-color" content="<?php echo COLORS[0]; ?>">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="robots" content="noindex, nofollow"/>
	<title><?php echo ucwords(strtolower($config['company'])); ?> | Account</title>
	<link rel="shortcut icon" href="<?php echo $path; ?>/docs/img/favicon.ico">
	<script src="<?php echo $path; ?>/assets/js/jquery.js?234"></script>
	<script src='https://code.jquery.com/ui/1.10.0/jquery-ui.js'></script>
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/bootstrap4.css">
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/trix.css">
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/client.css?<?php echo rand(1234,4321); ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link href="https://fonts.googleapis.com/css?family=Signika+Negative&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Acme&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
	<link rel="stylesheet" href="<?php echo $path; ?>/assets/css/morris.css">
	<script src="<?php echo $path; ?>/assets/js/bootstrap4.js"></script>
	<script src="<?php echo $path; ?>/assets/js/bootstrap.bundle.js"></script>
	<script src="<?php echo $path; ?>/assets/js/raphael.js"></script>
	<script src="<?php echo $path; ?>/assets/js/morris.js"></script>
	<script src='<?php echo $path; ?>/assets/js/googlechart.js'></script>
	<script src="<?php echo $path; ?>/assets/js/graphs.js?2024"></script>
	<script src="<?php echo $path; ?>/assets/js/trix.js"></script>
	<script>var MFS_BASE_PATH = "<?php echo isset($path) ? addslashes($path) : ''; ?>";</script>
	</head>
	<body>
	
		<input type="hidden" value="" id="temp"> <input type="hidden" value="" id="tempimg">
		<div id="progdv"><img src="data:image/gif;base64,<?php echo $gif; ?>" style='width:80px'></div>
		<div id="toast"><div id="notif"></div></div>
		<div id="progr"><div id="progt"></div></div>
		<div class="wrap"></div><div class="overlay"></div>
		
		<div class="popup" style="font-family:cambria">
			<h3 style="padding:10px;font-size:22px;color:#191970">
			<i class="bi-x-lg" style="float:right;color:#ff4500;cursor:pointer" title="Close" onclick="closepop()"></i></h3>
			<div class="popdiv" style="padding:10px"></div>
		</div>
		
		<table style="width:100%;height:100%;max-height:100%;top:0;right:0;left:0;bottom:0" cellspacing="0" cellpadding="0">
		<tr valign="top">
			<td class="aside">
				<div style="height:150px;background-image:url('<?php echo "$path/assets/img/panell.jpg"; ?>');background-size:cover;font-family:Signika Negative">
					<div style="padding:10px 20px">
						<div> <img src="<?php echo "data:image/jpg;base64,".getphoto($config['logo']); ?>" style="max-width:150px;max-height:70px"></div>
						<p style="color:#fff;font-size:17px;text-shadow:0px 1px 1px #000;padding-top:10px">
						<?php echo ucwords($name); ?><br><span style="font-size:13px"><i class="fa fa-circle"style="color:#00FF7F"></i> Online</span></p>
					</div>
				</div>
			
				<div class="lower-aside" style="overflow:auto;background:#2f4f4f;color:rgba(255,255,255,0.7);min-height:60%">
					<div style="padding:10px;" id="accordion">
						<li class="l1" onclick="window.location.replace('<?php echo $url; ?>/')"><i class="fa fa-dashboard"></i> Dashboard</li>
						<?php 
						
							if(in_array("view staffs",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#seven" aria-expanded="true" aria-controls="seven">
								<i class="fa fa-id-card-o"></i> Employees <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="seven" class="collapse" data-parent="#accordion">';
									echo (in_array("add staff",$roles)) ? '<li class="l2" onclick="popupload(\'hr/staff.php?add\')">Add Employee</li>':"";
									echo (in_array("view staff leaves",$roles)) ? '<li class="l2" onclick="loadpage(\'hr/leaves.php?manage\')">Staff Leaves</li>':"";
									echo (in_array("view staff groups",$roles)) ? '<li class="l2" onclick="loadpage(\'hr/staff.php?groups\')">Staff Groups</li>':"";
									echo (in_array("view loans",$roles)) ? '<li class="l2" onclick="loadpage(\'branches.php?loansummary\')">Staff Portfolios</li>':"";
									echo (in_array("view staff loans",$roles) && $stfloans) ? '<li class="l2" onclick="loadpage(\'hr/loans.php?apps\')">Loan Applications</li>':"";
									echo (in_array("view staff loans",$roles) && $stfloans) ? '<li class="l2" onclick="loadpage(\'hr/loans.php?manage\')">Staff Loans</li>':"";
									echo '<li class="l2" onclick="loadpage(\'hr/staff.php?workplan\')">Daily Workplan</li>
									<li class="l2" onclick="loadpage(\'hr/staff.php?manage\')">View Employees</li>
								</ol>';
							}
							
							if(in_array("access financials",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop6" aria-expanded="true" aria-controls="drop6">
								<i class="bi-cash-coin"></i> Financial <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop6" class="collapse" data-parent="#accordion">';
									echo (in_array($sid,$b2c_users) && in_array("web",$b2c_apps)) ? '<li class="l2" onclick="loadpage(\'accounts/b2capp.php?home\')">Mpesa Platform</li>':"";
									echo (in_array("view disbursements",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/disbursement.php?view\')">Mpesa Payouts</li>':"";
									echo (in_array("manage wallet deposits",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/reports.php?accbals\')">Account Balances</li>':"";
									echo (in_array("receive teller cash",$roles) or in_array("transfer teller cash",$roles) or in_array("reconcile teller cash",$roles)) ? 
									'<li class="l2" onclick="loadpage(\'account.php?tellers\')">Teller Operations</li>':"";
									echo (in_array("view investment packages",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/investors.php?packs\')">Investment Packages</li>':"";
									echo (in_array("view investors",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/investors.php?view\')">Investors List</li>':"";
									echo (in_array("view investment reports",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/investors.php?reports\')">Investors Reports</li>':"";
									echo '
								</ol>';
							}
							
							if(in_array("access banking",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop7" aria-expanded="true" aria-controls="drop7">
								<i class="bi-bank"></i> Banking <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop7" class="collapse" data-parent="#accordion">';
									echo (in_array("view bank accounts",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/banking.php?view\')">Bank Accounts</li>':"";
									echo (in_array("initiate bank transaction",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/banking.php?initiate\')">Initiate Transaction</li>':"";
									echo (in_array("post bank transaction",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/banking.php?postrans\')">Post Transaction</li>':"";
									echo (in_array("view bank transactions",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/banking.php?deposits\')">Bank Deposits</li>
									<li class="l2" onclick="loadpage(\'accounts/banking.php?withdraws\')">Bank Withdrawals</li>':"";
									echo '
								</ol>';
							}
							
							if(in_array("access accounting",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop1" aria-expanded="true" aria-controls="drop1">
								<i class="fa fa-bar-chart"></i> Accounting <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop1" class="collapse" data-parent="#accordion">';
									echo (in_array("view requisitions",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/requisition.php?view\')">Requisitions</li>':"";
									echo (in_array("view utility payments",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/utilities.php?view\')">Utility Payments</li>':"";
									echo (in_array("view cashbook",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/cashbook.php?view\')">Petty Cashbook</li>':"";
									echo (in_array("view accounting books",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/index.php?books\')">Books of Account</li>':"";
									echo (in_array("view salary advances",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/advance.php?manage\')">Salary Advances</li>':"";
									echo (in_array("view expenses",$roles)) ? '<li class="l2" onclick="loadpage(\'accounts/index.php?expesummary\')">Expense Summary</li>':"";
									echo '<li class="l2" onclick="loadpage(\'accounts/index.php?cashflow\')">Cashflow</li>
								</ol>';
							}
							
							if(in_array("view branches/regions",$roles) && !$hidebrn){
								echo '<li class="l1" data-toggle="collapse" data-target="#Two" aria-expanded="true" aria-controls="Two">
								<i class="fa fa-sitemap"></i> Branches & Regions <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="Two" class="collapse" data-parent="#accordion">';
									echo (in_array("add branch",$roles)) ? '<li class="l2" onclick="popupload(\'branches.php?add\')">Add Branch</li>':"";
									echo (in_array("create region",$roles)) ? '<li class="l2" onclick="popupload(\'branches.php?cregn\')">Create Region</li>':"";
									echo '<li class="l2" onclick="loadpage(\'branches.php?loansummary\')">Loan Summary</li>
									<li class="l2" onclick="loadpage(\'branches.php?regions\')">View Regions</li>
									<li class="l2" onclick="loadpage(\'branches.php?manage\')">View Branches</li>
								</ol>';
							}
							
							if(in_array("access business analytics",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop5" aria-expanded="true" aria-controls="drop5">
								<i class="fa fa-line-chart"></i> Business Analytics <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop5" class="collapse" data-parent="#accordion">
									<li class="l2" onclick="loadpage(\'bi/reports.php?lsizes\')">Loan Sizes</li>';
									echo (in_array("view targets",$roles)) ? '<li class="l2" onclick="loadpage(\'bi/targets.php?view\')">Targets & Accruals</li>':"";
									echo '<li class="l2" onclick="loadpage(\'bi/index.php?view\')">Business Performance</li>
								</ol>';
							}
						
							if(in_array("view clients",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#One" aria-expanded="true" aria-controls="One">
								<i class="bi-people-fill"></i> Clients <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="One" class="collapse" data-parent="#accordion">';
									echo (in_array("add client",$roles)) ? '<li class="l2" onclick="popupload(\'clients.php?add\')">Add Client</li>':"";
									echo (in_array("create client lead",$roles)) ? '<li class="l2" onclick="popupload(\'clients.php?createld\')">Create a Lead</li>':"";
									echo (in_array("transfer clients",$roles)) ? '<li class="l2" onclick="popupload(\'clients.php?transfer\')">Transfer Clients</li>':"";
									echo (in_array("view client groups",$roles)) ? '<li class="l2" onclick="loadpage(\'clients.php?groups\')">Default Groups</li>':"";
									echo '<li class="l2" onclick="loadpage(\'clients.php?intcns\')">Interactions</li> 
									<li class="l2" onclick="loadpage(\'clients.php?vleads\')">Client Leads</li>
									<li class="l2" onclick="loadpage(\'clients.php?manage\')">View Clients</li>
								</ol>';
							}
							
							if(in_array("access asset financing",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop9" aria-expanded="true" aria-controls="drop9">
								<i class="bi-cart4"></i> Asset Financing <i class="fa fa-sort-down" style="float:right"></i></li>
								<ol id="drop9" class="collapse" data-parent="#accordion">';
									echo (in_array("view units of measurement",$roles)) ? '<li class="l2" onclick="loadpage(\'finassets.php?units\')">Measurement Units</li>':"";
									echo (in_array("view asset categories",$roles)) ? '<li class="l2" onclick="loadpage(\'finassets.php?cats\')">Asset Categories</li>':"";
									echo (in_array("add loan asset",$roles)) ? '<li class="l2" onclick="popupload(\'finassets.php?create\')">Add Asset/Stock</li>':"";
									echo (in_array("view loan assets",$roles)) ? '<li class="l2" onclick="loadpage(\'finassets.php?view\')">Asset List/Stock</li>':"";
								echo '</ol>';
							}
							
							if(in_array("access group lending",$roles) && sys_constants("operate_group_loans")){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop8" aria-expanded="true" aria-controls="drop8">
								<i class="bi-people"></i> Group Lending <i class="fa fa-sort-down" style="float:right"></i></li>
								<ol id="drop8" class="collapse" data-parent="#accordion">';
									echo (in_array("create loan group",$roles)) ? '<li class="l2" onclick="popupload(\'groups.php?create\')">Create Group</li>':"";
									echo '<li class="l2" onclick="loadpage(\'groups.php?loans\')">Group Loans</li>
									<li class="l2" onclick="loadpage(\'groups.php?view\')">View Groups</li>
								</ol>';
							}
							
							$novloans = (defined("NO_LOAN_VIEW")) ? NO_LOAN_VIEW:[];
							$arrgive = (defined("ALLOCATE_ARREARS")) ? ALLOCATE_ARREARS:0;
							
							if(in_array("view loans",$roles) && !in_array($post,$novloans)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop2" aria-expanded="true" aria-controls="drop2">
								<i class="fa fa-book"></i> '.$loantab.' <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop2" class="collapse" data-parent="#accordion">';
									echo (in_array("create loan template",$roles)) ? '<li class="l2" onclick="popupload(\'loans.php?template\')">'.$newloan.'</li>':"";
									echo (in_array("view loan disbursements",$roles)) ? '<li class="l2" onclick="loadpage(\'loans.php?disbursements\')">'.$vtemp.'</li>
									<li class="l2" onclick="loadpage(\'loans.php?dmtd\')">Collection MTD</li><li class="l2" onclick="loadpage(\'loans.php?dailyds\')">Disbursements</li>':"";
									echo (in_array("access collection sheet",$roles)) ? '<li class="l2" onclick="loadpage(\'collections.php?view\')">Collection sheet</li>':"";
									echo (in_array("access collection report",$roles)) ? '<li class="l2" onclick="loadpage(\'collections.php?report\')">Collection Reports</li>':"";
									echo (in_array("access collection report",$roles) && $collrate) ? '<li class="l2" onclick="loadpage(\'collections.php?rates\')">Collection Rates</li>':"";
									echo (in_array("view collection agents",$roles)) ? '<li class="l2" onclick="loadpage(\'collections.php?agents\')">Collection Agents</li>':"";
									echo (in_array("view loan arrears",$roles)) ? '<li class="l2" onclick="loadpage(\'loans.php?arrears\')">Loan Arrears</li>':"";
									echo ($apploans) ? '<li class="l2" onclick="loadpage(\'loans.php?apprep\')">App Loans Report</li>':"";
									echo (in_array("view checkoff loans",$roles) && $docko) ? '<li class="l2" onclick="loadpage(\'loans.php?checkoff\')">Checkoff Loans</li>':"";
									echo (in_array($post,$ahandlers)) ? '<li class="l2" onclick="loadpage(\'loans.php?arrears&follow=1\')">Arrears Followup</li>':"";
									echo (in_array($post,$ahandlers) && in_array("add staff",$roles) && $arrgive) ? '<li class="l2" onclick="popupload(\'collections.php?ddasgs\')">DD\'s Assignment</li>':"";
									echo '<li class="l2" onclick="loadpage(\'loans.php?manage\')">View Loans</li>
								</ol>';
							}
							
							if(in_array("access bulksms",$roles)){
								echo '<li class="l1" data-toggle="collapse" data-target="#drop4" aria-expanded="true" aria-controls="drop4">
								<i class="fa fa-envelope-o"></i> Bulk SMS <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop4" class="collapse" data-parent="#accordion">';
									echo (in_array("send sms",$roles)) ? '<li class="l2" onclick="popupload(\'bulksms.php?sendsms\')">Send/Schedule SMS</li>':"";
									echo (in_array("create sms template",$roles)) ? '<li class="l2" onclick="popupload(\'bulksms.php?createtemp\')">Create SMS Template</li>':"";
									echo (in_array("view sms templates",$roles)) ? '<li class="l2" onclick="loadpage(\'bulksms.php?templates\')">SMS Templates</li>':"";
									echo (in_array("view sms logs",$roles)) ? '<li class="l2" onclick="loadpage(\'bulksms.php?logs\')">SMS Logs</li>':"";
									echo (SMS_TOPUP) ? '<li class="l2" onclick="popupload(\'bulksms.php?topup\')">Topup Wallet</li>':"";
									echo '<li class="l2" onclick="loadpage(\'bulksms.php?schedule\')">SMS Schedules</li>';
									// Add admin config link for HQ users with admin rights
									if(in_array("admin", $perms) || $me['access_level'] == "hq"){
										echo '<li class="l2" onclick="loadpage(\'admin/sms_config.php\')" style="color:#ff6b35;">⚙️ API Settings</li>';
									}
								echo '</ol>';
							}
							
							if(in_array("view loan payments",$roles)){
								$noview = (defined("VIEW_PROCESSED_PAYSONLY")) ? VIEW_PROCESSED_PAYSONLY:[];
								echo '<li class="l1" data-toggle="collapse" data-target="#drop3" aria-expanded="true" aria-controls="drop3">
								<i class="fa fa-money"></i> Payments <i class="fa fa-sort-down"style="float:right"></i></li>
								<ol id="drop3" class="collapse" data-parent="#accordion">';
									echo (in_array($post,$noview)) ? '<li class="l2" onclick="loadpage(\'payments.php?processed\')">Processed Payments</li>':
									'<li class="l2" onclick="loadpage(\'payments.php?payments\')">Unposted Payments</li> 
									<li class="l2" onclick="loadpage(\'payments.php?processed\')">Processed Payments</li>
									<li class="l2" onclick="loadpage(\'payments.php?prepayments\')">Prepayments</li>
									<li class="l2" onclick="loadpage(\'payments.php?overpays\')">Overpayments</li>';
									echo (in_array("merge payment",$roles)) ? '<li class="l2" onclick="loadpage(\'payments.php?merged\')">Merged Payments</li>':"";
									echo (in_array($sid,$b2c_users) && in_array("web",$b2c_apps) && !in_array($post,$noview)) ? '<li class="l2" 
									onclick="loadpage(\'payments.php?reversals\')">C2B Reversals</li>':"";
									echo (in_array("view payment receipts",$roles)) ? '<li class="l2" onclick="loadpage(\'payments.php?receipts\')">Receipts</li>':"";
									echo (in_array("view payments report",$roles)) ? '<li class="l2" onclick="loadpage(\'payments.php?payins\')">Payin Summary</li>':"";
									echo (in_array("view payments report",$roles)) ? '<li class="l2" onclick="loadpage(\'payments.php?report\')">Payments Report</li>':"";
									echo '<li class="l2" onclick="loadpage(\'payments.php?validatepay\')">Validate Payment</li>
								</ol>';
							}
						
							echo '<li class="l1" data-toggle="collapse" data-target="#Three" aria-expanded="true" aria-controls="Three">
							<i class="bi-person-lines-fill"></i> My Account <i class="fa fa-sort-down"style="float:right"></i></li>
							<ol id="Three" class="collapse" data-parent="#accordion">
								<li class="l2" onclick="loadpage(\'hr/staff.php?vstid='.$sid.'\')">View Details</li>
								<li class="l2" onclick="loadpage(\'hr/staff.php?workplan='.$sid.'\')">My Workplan</li>';
								echo (in_array("accounting",$mfi)) ? '<li class="l2" onclick="loadpage(\'account.php?advances\')">Salary Advance</li>':"";
								echo ($stfloans) ? '<li class="l2" onclick="loadpage(\'hr/staff.php?vstid='.$sid.'&vtp=lns\')">My Staff Loans</li>':"";
								echo (in_array("approve loan write-off",$roles) or in_array("approve wallet deposits",$roles)) ? 
								'<li class="l2" onclick="loadpage(\'account.php?appreqs\')">Approval Requests</li>':"";
								echo '<li class="l2" onclick="popupload(\'account.php?myacc\')">Update Details</li>
							</ol>';
							
							echo '<li class="l1" data-toggle="collapse" data-target="#Four" aria-expanded="true" aria-controls="Four">
							<i class="fa fa-wrench"></i> System & Help <i class="fa fa-sort-down"style="float:right"></i></li>
							<ol id="Four" class="collapse" data-parent="#accordion">
								<li class="l2" onclick="popupload(\'tickets.php?create\')">Create a Ticket</li>
								<li class="l2" onclick="loadpage(\'tickets.php?view\')">Raised Tickets</li>';
								echo (in_array("configure system",$roles)) ? "<li class='l2' onclick=\"loadpage('setup.php?view')\">System Setup</li>":"";
								echo (in_array("view access logs",$roles)) ? "<li class='l2' onclick=\"loadpage('logs.php?view')\">Access Logs</li>":"";
							echo '</ol>';
							
							echo '<li class="l1" onclick="logout()"><i class="fa fa-power-off"></i> Logout</li>';
						
						?>
					</div>
				</div>
				
			</td>
			
			<td class="section">
				<div style="height:70px;border-bottom:1px solid #f0f0f0;background:#fff;">
					<img src="<?php echo $profile; ?>" class="proficon" onclick="popupload('account.php?myacc')" title='My Account'>
					<?php
					
						if(in_array("view loan payments",$roles)){
							echo '<span class="navitem" onclick="loadpage(\'payments.php?payments\')" title="New Payments"><i class="fa fa-money"></i>
							<span class="not1"></span></span><span class="navitem" onclick="loadpage(\'payments.php?manual\')" title="Uncofirmed Payments">
							<i class="fa fa-clone" style="font-size:20px"></i><span class="not2"></span></span>';
						}
						
						if(in_array("view loan disbursements",$roles)){
							echo '<span class="navitem dsbico" onclick="loadpage(\'loans.php?disbursements=1&nlt\')" title="Disbursements">
							<i class="fa fa-calendar-check-o" style="font-size:21px"></i><span class="not3"></span></span>';
						}
					?>
					
					<span class="navitem snot" onclick="loadpage('account.php?tasks')" title='System Notifications'><i class="fa fa-bell-o"></i><span class="not5"></span></span>
					<span class="navitem" onclick="loadpage('tickets.php?view')" title='Incoming Tickets'><i class="fa fa-comments-o"></i><span class="not4"></span></span>
					<h2 style="font-size:19px;font-weight:;color:#4682b4;line-height:67px;padding-left:10px;font-family:Acme">
					<i class="fa fa-bars" id="mbar" onclick="navbar('show')"></i> <span class="orgname"><?php echo $company; ?></span></h2>
				</div>
				<div class="loadarea" style="overflow:auto;height:70%;position:absolute;width:100%">
					<div class="mainactivity">
						
					</div>
				</div>
			</td>
		</tr></table>
	</body>
	</html>
	
	<script>
	
		divide(); $( window ).resize(divide);
		function divide(){
			$(".lower-aside").css("height",($(window).height()-150)+"px"); 
			$(".loadarea").css("height",($(window).height()-71)+"px"); 
		}
		
		(function(){
			var url = window.location.pathname.split("/"),purl="<?php echo $url; ?>",len=purl.split("/").length;
			if(url.length>len+(+1)){
				var rem = url.slice(len),pos=rem.length-1,param=(rem[pos].length>1) ? atob(rem[pos]):"";
				if(rem[0]!="menu"){
					var page=rem.slice(0,pos).join("/")+".php"+param,urp = (page.split("?").length>1) ? "&md=":"?md=";
					$("#progdv").fadeIn(); $(".loadarea").animate({ scrollTop:0 },500);
					$(".mainactivity").load(path()+page+urp+$(window).width()+"&sid=<?php echo $sid; ?>",function(response,status,xhr){
						$("#progdv").fadeOut(); if(status=="error"){toast("Failed: Check Internet connection");}
					});
				}
			}
			else{
				var idno = "<?php echo $idno; ?>",psn = "<?php echo $uset; ?>",grp="<?php echo $gset; ?>";
				if(idno==1238){ popupload('hr/staff.php?add=<?php echo $sid; ?>'); }
				if(psn>0){ popupload('account.php?setmine='+psn); }
				if(psn<1 && grp>0){ popupload('account.php?setsess='+grp); }
				
				$(".mainactivity").html("<p style='line-height:100px;text-align:center;color:#4682b4;font-weight:bold;font-size:14px'>Loading Dashboard..."+
				" <img src='<?php echo $path; ?>/assets/img/waiting.gif'></p>");
				$(".mainactivity").load(path()+"dashboard.php?md="+$(window).width()+"&sid=<?php echo $sid; ?>",function(response,status,xhr){
					if(status=="error"){
						$(".mainactivity").html("<p style='line-height:100px;text-align:center;color:#ff4500;font-weight:bold;font-size:14px'>Network Error!</p>");
					}
				});
			}
		}());
		
		function printdoc(url,pos){
			var loc = "/demo.pradtec/";
			window.open(loc+"pdf/files/"+url+"&pos=<?php echo $sid; ?>",pos);
		}
		
		function createcookie(name,value,days=90){
			var expires = "";
			if (days){
				var date = new Date();
				date.setTime(date.getTime() + (days*24*60*60*1000));
				expires = "; expires=" + date.toUTCString();
			}
			document.cookie = name + "=" + (value || "") + expires + "; path=/";
		}
		
		function genreport(url,val){
			if(val=="pdf"){ printdoc(url,"Printout"); }
			if(val=="xls"){
				var loc = "/demo.pradtec/";
				$.ajax({
					method:"post",url:loc+"xls/"+url,data:{genxls:"<?php echo $sid; ?>"},
					beforeSend:function(){progress("Generating...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						window.location.href=loc+"xls/"+res.trim().split(":")[1];
					}
					else{ alert(res); }
				});
			}
		}
		
		function changedoc(id,name){
			var doc = _(id).files[0];
			if(doc!=null){
				if(confirm("Upload selected File for replacement?")){
					var data=new FormData();
					data.append("media",doc); data.append("ctbl",_("dtbl").value);
					data.append("dcol",_("dcol").value); data.append("fname",name);
					var xhr=new XMLHttpRequest();
					xhr.upload.addEventListener("progress",uploadprogress,false);
					xhr.addEventListener("load",uploaddone,false);
					xhr.addEventListener("error",uploaderror,false);
					xhr.addEventListener("abort",uploadabort,false);
					xhr.onload=function(){
						if(this.responseText.trim()=="success"){
							window.location.reload(); closepop();
						}
						else{ alert(this.responseText); _(id).value=null; }
					}
					xhr.open("post",path()+"media.php?sid=<?php echo $sid; ?>",true);
					xhr.send(data);
				}
				else{ _(id).value=null; }
			}
		}
		
		function uploadprogress(event){
			var percent=(event.loaded / event.total) * 100;
			progress("Uploading "+Math.round(percent)+"%");
			if(percent==100){
				progress("Processing...please wait");
			}
		}
		
		function uploaddone(event){ progress(); }
		function uploaderror(event){ toast("Upload failed"); progress(); }
		function uploadabort(event){ toast("Upload aborted"); progress(); }
		
		function setCookie(cname, cvalue){
			var d = new Date();
			d.setTime(d.getTime() + (30*24*60*60*1000));
			var expires = "expires="+ d.toUTCString();
			document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
		}
		
		function rempic(pic){
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:{rempic:pic}
			});
		}
		
		function creategroup(callback){
			var grp = prompt("Type Staff Group");
			if(grp){
				if(confirm("Create staff group "+grp+"?")){
					$.ajax({
						method:"post",url:path()+"dbsave/setup.php",data:{savegrup:grp},
						beforeSend:function(){progress("Creating...please wait");},
						complete:function(){progress();}
					}).fail(function(){
						toast("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim()=="success"){
							toast("Success"); loadpage(callback+"="+grp.toLowerCase().split(' ').join("+")); 
						}
						else{ alert(res); }
					});
				}
			}
		}
		
		function requestotp(fon){
			$.ajax({
				method:"post",url:path()+"dbsave/sendsms.php",data:{requestotp:fon},
				beforeSend:function(){progress("Requesting...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res);
			});
		}
		
		function delmedia(del,tp){
			if(confirm("Sure to remove the "+tp+"?")){
				$.ajax({
					method:"post",url:path()+"media.php?sid=<?php echo $sid; ?>",data:{delrecord:del},
					beforeSend:function(){progress("Removing...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					toast(res); 
					if(res.trim()=="success"){ closepop(); window.location.reload(); }
				});
			}
		}
		
		function delrecord(id,tbl,tt){
			if(confirm(tt)){
				$.ajax({
					method:"post",url:path()+"dbsave/setup.php",data:{delrecord:id,dtbl:tbl},
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					toast(res); if(res.trim()=="success"){ $('#rec'+id).remove(); }
				});
			}
		}
		
		function checkboxes(item){
			var radios=document.getElementsByName(item);
			var formValid = false; var i = 0;
			while (!formValid && i < radios.length) {
				if (radios[i].checked) formValid = true; i++;        
			}
			if (!formValid){return false; }
			else{return true; }
		}
		
		function closepop(){
			$(".popup").fadeOut(); $(".previewimg").fadeOut(); $(".overlay").fadeOut();
		}
		
		function fsearch(e,callback){
			var keycode=e.charCode? e.charCode : e.keyCode,agent = navigator.userAgent;
			if(keycode==13 && (agent.search("Firefox")>-1 || agent.search("MSIE")>-1)){
				loadpage(callback);
			}
		}
		
		function cleanstr(str){ return encodeURIComponent(str.trim()); }
		function logout(){
			if(confirm("Sure to Logout?")){
				closepop(); window.location.replace(path()+"logout.php");
			}
		}
		
		function path(){
			var base = (typeof MFS_BASE_PATH !== "undefined") ? MFS_BASE_PATH : "/demo.pradtec";
			return base ? (base + "/mfs/") : "/demo.pradtec/mfs/";
		}
		
		function popupload(url){
			if($(window).width()<601){navbar("hide");}
			$(".popdiv").html("<br><br><center><img src='<?php echo $path; ?>/assets/img/loader.gif'></center>");
			$(".popup").fadeIn(); $(".overlay").fadeIn(); 
			$(".popdiv").load(path()+url+"&sid=<?php echo $sid; ?>");
		}
		
		var check = setInterval(checknotification,4000);
		function checknotification(){
			var dt = new Date(); 
			var hr = dt.getHours(), min=dt.getMinutes(), sec=dt.getSeconds(), chk=parseInt(hr+""+min+""+sec);
			var go = (typeof MFS_BASE_PATH !== "undefined" && MFS_BASE_PATH) ? MFS_BASE_PATH : "/demo.pradtec";
			
			if(chk>=235945){ clearInterval(check); alert("Your session has expired,login again"); window.location.replace(go); }
			else{
				if(navigator.onLine==true){
					$.ajax({
						method:"post",url:path()+"fetchnotes.php",data:{getnot:"<?php echo $sid;?>"}
					}).done(function(res){
						var data = res.trim().split(":");
						var pay = data[0],upay=data[1],disb=data[2],sess=data[3],tick=data[4],nots=data[5]; 
						var ntv = (nots>99) ? "99+":nots;
						if(pay>0){ $(".not1").html("<sup class='sup'>"+pay+"</sup>"); }
						if(upay>0){ $(".not2").html("<sup class='sup'>"+upay+"</sup>"); }
						if(disb>0){ $(".not3").html("<sup class='sup'>"+disb+"</sup>"); }
						if(tick>0){ $(".not4").html("<sup class='sup'>"+tick+"</sup>"); }
						if(nots>0){ $(".not5").html("<sup class='sup'>"+ntv+"</sup>"); }
						if(pay==0){ $(".not1").html(""); }
						if(upay==0){ $(".not2").html(""); }
						if(disb==0){ $(".not3").html(""); }
						if(tick==0){ $(".not4").html(""); }
						if(nots==0){ $(".not5").html(""); }
						if(sess==0){ clearInterval(check); alert("Your session has expired,login again"); window.location.replace(go); }
					});
				}
			}
		}

		var idleTime = 0;
		$(document).ready(function (){
			var idleInterval = setInterval(timerIncrement, 60000);
			$(this).mousemove(function (e){ idleTime = 0; });
			$(this).keypress(function (e){ idleTime = 0; });
		});
		
		function timerIncrement(){
			if(localStorage.getItem("ubr")!="dev"){
				idleTime++;
				var go = (typeof MFS_BASE_PATH !== "undefined" && MFS_BASE_PATH) ? MFS_BASE_PATH : "/demo.pradtec";
				if(idleTime>29){ window.location.replace(go); }
				if(idleTime==29){ toast("You have been Idle for 29 Minutes, the system will Logout in the next 1 Minute"); }
			}
		}
		
		function navbar(tp){
			if(tp=="show"){
				if(window.location.pathname=="<?php echo $url; ?>/"){
					history.pushState(null, null, "<?php echo $url; ?>/menu/");
				}
				var ha=$(window).height()-150; $(".lower-aside").css("height",ha+"px");
				$(".wrap").fadeIn(); $(".aside").show(); $(".aside").animate({width:"90%"});
			}
			else{
				$(".wrap").fadeOut(); $(".aside").animate({width:"0px"});
				setTimeout(function(){$(".aside").hide();},300);
			}
		}
		
		$(".wrap").click(function(){
			if($(".aside").width()){ navbar("hide"); }
		});
		
		function loadpage(page){
			if($(window).width()<601){ navbar("hide"); }
			$("#progdv").fadeIn(); $(".loadarea").animate({ scrollTop:0 },500);
			
			var urp = (page.split("?").length>1) ? "&md=":"?md=";
			$(".mainactivity").load(path()+page+urp+$(window).width()+"&sid=<?php echo $sid; ?>",function(response,status,xhr){
				$("#progdv").fadeOut(); if(status=="error"){ toast("Failed: "+response); }
			});
			
			var pos=page.split(".php"),last=(pos.length>1) ? "/"+btoa(pos[1]):"";
			history.pushState(null, null, "<?php echo $url; ?>/"+pos[0]+last);
		}
		
		function fetchpage(page){
			if($(window).width()<601){ navbar("hide"); }
			$("#progdv").fadeIn(); $(".loadarea").animate({ scrollTop:0 },500);
			var urp = (page.split("?").length>1) ? "&md=":"?md=";
			$(".mainactivity").load(path()+page+urp+$(window).width(),function(response,status,xhr){
				$("#progdv").fadeOut(); if(status=="error"){toast("Failed: Check Internet connection");}
			});
		}
		
		window.onpopstate = function(event){
			var url = window.location.pathname.split("/"),purl="<?php echo $url; ?>",len=purl.split("/").length; 
			if(url.length>len+(+1)){
				var rem = url.slice(len),pos=rem.length-1,param=(rem[pos].length>1) ? atob(rem[pos]):"",page=rem.slice(0,pos).join("/")+".php"+param; 
				if(rem[0]=="menu"){ navbar("hide"); } 
				else{ 
					$("#progdv").fadeIn(); $(".loadarea").animate({ scrollTop:0 },500);
					var urp = (page.split("?").length>1) ? "&md=":"?md=";
					$(".mainactivity").load(path()+page+urp+$(window).width()+"&sid=<?php echo $sid; ?>",function(response,status,xhr){
						$("#progdv").fadeOut(); if(status=="error"){toast("Failed: Check Internet connection");}
					});
				}
			}
			else{ window.location=window.location; }
		}
		
		function countext(id,len){
			var n=len.length;
			if(n<161){_(id).innerHTML=n+" chars, 1 Mssg";}
			else{
				var nw=Math.ceil(n/160);
				_(id).innerHTML=n+" chars, "+nw+" Mssgs";
			}
		}
		
		function valid(id,v){
			var exp = /^[0-9.%]+$/; 
			if(!v.match(exp)){ _(id).value=v.slice(0,-1);}
		}
		
		function toast(v){
			rest(); $("#toast").fadeIn(); _("notif").innerHTML=v;
			tmo=setTimeout(function(){
				$("#toast").fadeOut();
			},5000);
		}
		
		var tmo;
		function rest(){
			clearTimeout(tmo);
		}
		
		function progress(v){
			if(v){
				$("#progr").fadeIn(); _("progt").innerHTML=v;
			}
			else{
				$("#progr").fadeOut(); _("progt").innerHTML="";
			}
		}
		
		function togpass(v){
			var x = document.getElementById("upass");
			if (x.type === "password") {
				_("vpass").innerHTML = "<i class='fa fa-eye-slash' title='Hide Password'></i>"; x.type = "text";
			} else {
				_("vpass").innerHTML = "<i class='fa fa-eye' title='View Password'></i>"; x.type = "password";
			}
		}
		
		function _(el){
			return document.getElementById(el);
		}
		
	</script>
	
	<?php	
	}
	else{
		$root = "/demo.pradtec";
		echo "<script>window.location.replace('$root')</script>";
	}
	
	ob_end_flush();
?>