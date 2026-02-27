<?php
	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
	// When project is in demo.pradtec subfolder, use that path so login and redirects work
	$scriptDir = str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? ''));
	$isDemoPradtec = (strpos($scriptDir, 'demo.pradtec') !== false);
	$isLocalhost = ($_SERVER['HTTP_HOST']=="localhost" || $_SERVER['HTTP_HOST']=="127.0.0.1:8000" || $_SERVER['SERVER_NAME']=="localhost" || $_SERVER['SERVER_NAME']=="127.0.0.1");
	$url = $isLocalhost ? ($isDemoPradtec ? "localhost/demo.pradtec" : "localhost/mfs") : $_SERVER['HTTP_HOST'];
	$path = $isLocalhost ? ($isDemoPradtec ? "/demo.pradtec" : "/mfs") : "";
	$backbtn = "<i class='bi-arrow-left-circle' style='font-size:25px;cursor:pointer;float:left;margin-right:12px' onclick='window.history.back()'></i>";
	
	$db_names = $isLocalhost ? array(1=>'mfi_core',2=>'mfi_defined',3=>'mfi_accounts'):array(1=>'emptyjou_mficore',2=>'emptyjou_mfidefined','emptyjou_mfiaccounts');
    $usernames = $isLocalhost ? array(1=>'root',2=>'root',3=>'root'):array(1=>'emptyjou_coreuser',2=>'emptyjou_defuser',3=>'emptyjou_accsuser');
    $passwords = $isLocalhost ? array(1=>'',2=>'',3=>''):array(1=>'@coredb.g1',2=>'@defined.g2',3=>'@accounts.g3');
	
	define("CLIENT_ID",4);
	define("DB_HOST","localhost");
	define("COLORS",["#074E8F","#074E8F"]);
	define("THEME",["type"=>1,"bgcolor"=>"#f8f8f0","opacity"=>"#fff"]);
	define("RESET_PASS",1);
	
	define("DATABASES",array(
		1=>array("user"=>$usernames[1],"pass"=>$passwords[1],"name"=>$db_names[1]),
		2=>array("user"=>$usernames[2],"pass"=>$passwords[2],"name"=>$db_names[2]),
		3=>array("user"=>$usernames[3],"pass"=>$passwords[3],"name"=>$db_names[3])
	));
	define("INPUTS",array(
		"number"=>"Number Value","text"=>"Alphanumeric text","email"=>"Email input","password"=>"Password","time"=>"Time input","month"=>"Month Input",
		"date"=>"Date Input","textarea"=>"Long Text","docx"=>"Word document","image"=>"Image File","pdf"=>"PDF Document","url"=>"Link or URL",
		"select"=>"Select List/Dropdown","link"=>"Data from Record"
	));
	define("SYS_TABLES",array(
		"org".CLIENT_ID."_staff"=>"Staff","org".CLIENT_ID."_clients"=>"Clients","org".CLIENT_ID."_loans"=>"Loans","org".CLIENT_ID."_payments"=>"Payments",
		"branches"=>"Branches","org".CLIENT_ID."_loantemplates"=>"Loan Templates","disbursements".CLIENT_ID=>"Disbursements","org".CLIENT_ID."_targets"=>"Targets",
		"requisitions".CLIENT_ID=>"Requisitions","advances".CLIENT_ID=>"Advances"
	));
	define("SKIP_FIELDS",array(
		"id","time","status","profile","otp","access_level","roles","tid","loan","loan_product"
	));
	define("DEF_ACCS",array(
		"interest"=>23,"b2c_charges"=>26,"portfolio"=>20,"loan_charges"=>13,"def_income"=>18,"overpayment"=>17,"loanbook"=>21,"mpesabulk"=>16,"advance"=>15
	));
	
	define("B2C_DEF",array(
		"params"=>['ConsumerKey' => 'jorKpV98x4kQaz5KUpL1eHgkiHg47ibA','ConsumerSecret' => 'xzzw8UedH1EqnvIF'],
		"comdef"=>["paybill"=>918885,"user"=>"PRADY OPERATIONS","curl"=>"https://pay.pradytec.com"]
	));
	
	define("B2C_RATES",array("0-100"=>0,"101-1500"=>5,"1501-5000"=>9,"5001-20000"=>11,"20001-150000"=>13));
	define("BASE_URL", $url);
	define("BACKUP_URL", "http://dev.limabtech.com/backups/getbackup.php?src=$url");
	define("SMS_SURL", "http://bulk.pradytec.com/api/sms/send");
	define("MULTIPLESMS_URL", "http://bulk.pradytec.com/api/multiple.php");
	define("SMS_PURL", "http://bulk.pradytec.com/api/sms/payment");
	define("SMS_BURL", "http://bulk.pradytec.com/api/sms/balance");
	define("MAIL_URL", "http://dev.pradytec.com/email/mfs.php");
	define("SMS_TOPUP",SMS_PURL);
	

?>