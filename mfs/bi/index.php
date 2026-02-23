<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# home
	if(isset($_GET['view'])){
		echo "<div class='cardv' style='max-width:800px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Business Performance</h3><hr>	
				<div class='row'>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/portfolio.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-clipboard-data'></i>
						Portfolio Quality</h3><p style='margin-bottom:0px;font-size:14px'>Arrears analysis, Client analysis & Collection analysis</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/efficiency.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-journal-medical'></i>
						Efficiency & Productivity</h3><p style='margin-bottom:0px;font-size:14px'>Operating Expense,Cost per Borrower & Loan analysis</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/personnel.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-people'></i>
						Personnel Productivity</h3><p style='margin-bottom:0px;font-size:14px'>Staff Performance & Loan disbursements</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/financials.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-line-chart'></i>
						Financial Management</h3><p style='margin-bottom:0px;font-size:14px'>Cost of funding, Portfolio yield, Current & Debt ratios</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/profitability.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-palette2'></i>
						Profitability</h3><p style='margin-bottom:0px;font-size:14px'>Return on Equity & Return on Assets</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('bi/dormancy.php?view')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-alarm'></i>
						Dormancy Analysis</h3><p style='margin-bottom:0px;font-size:14px'>Clients falling to Dormancy & already in dormancy</p>
						</div>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed Business performance KPIs");
	}
	
	@ob_end_flush();
?>