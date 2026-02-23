<?php
	
	define("KPI_LIST", array(
		"newloans"=>["def"=>"loanapp:new","desc"=>"New Loans Production","title"=>"New Loans","form"=>"A/T","rank"=>1,"target"=>1],
		"repeats"=>["def"=>"loanapp:repeat","desc"=>"Repeat Loans Production","title"=>"Repeat Loans","form"=>"A/T","rank"=>1,"target"=>1],
		"arrears"=>["def"=>"loans:arrears","desc"=>"Arrears Reduction","title"=>"Arrears","form"=>"S-E/S-T","rank"=>1,"target"=>1],
		"mtd"=>["def"=>"loans:mtd","desc"=>"Monthly Percentage Collections (MTD)","title"=>"Collection MTD","form"=>"A/T","rank"=>0,"target"=>0],
		"collections"=>["def"=>"report:collections","desc"=>"General Cummulative Collections","title"=>"General Collections","form"=>"A/T","rank"=>0,"target"=>0],
		"performing"=>["def"=>"loans:perf","desc"=>"Performing Loans","title"=>"Performing","form"=>"A/T","rank"=>0,"target"=>0],
		"disbursement"=>["def"=>"loanapp:disb","desc"=>"Gross Disbursement","title"=>"Gross Disbursement","form"=>"A/T","rank"=>1,"target"=>1],
		"loanbook"=>["def"=>"loans:book","desc"=>"Loanbook Value","title"=>"LoanBook","form"=>"A/T","rank"=>0,"target"=>0],
		"income"=>["def"=>"report:income","desc"=>"Income Generation","title"=>"Revenue","form"=>"A/T","rank"=>1,"target"=>1],
		"checkoff"=>["def"=>"loanapp:checkoff","desc"=>"Checkoff Amount","title"=>"Checkoff","form"=>"A/T","rank"=>0,"target"=>0],
		"groups"=>["def"=>"cgroups:new","desc"=>"Client Groups Formation","title"=>"New Groups","form"=>"A/T","rank"=>0,"target"=>0],
		"apploans"=>["def"=>"loanapp:app","desc"=>"USSD/App Loans","title"=>"USSD/App Loans","form"=>"A/T","rank"=>0,"target"=>0],
		"assetloans"=>["def"=>"loanapp:asset","desc"=>"Asset Financing Loans","title"=>"Asset Loans","form"=>"A/T","rank"=>0,"target"=>0],
		"loanprods"=>["def"=>"loanapp:prod","desc"=>"Product-Based Disbursements","title"=>"Product","form"=>"A/T","rank"=>0,"target"=>0]
	));

?>