<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# save charges
	if(isset($_POST['postcharge'])){
		$day = trim($_POST['postcharge']);
		$amnt = trim($_POST['damnt']);
		$desc = "B2C Transaction charges for ".date("d-m-Y",$day); 
		$tym = strtotime(date("Y-M-d,",$day).date("H:i")); $now=time();
		$rev = json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"posted","val"=>$now,"update"=>"posted:0"]));
		
		if(doublepost([0,DEF_ACCS['mpesabulk'],$amnt,$desc,$tym,$sid],[0,DEF_ACCS['b2c_charges'],$amnt,$desc,$tym,$sid],'',$tym,$rev)){
			$db->insert(2,"UPDATE `payouts$cid` SET `posted`='$now' WHERE `day`='$day'");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later!"; }
		exit();
	}
	
	# save payment type
	if(isset($_POST['paytp'])){
		$ptp = trim($_POST['paytp']);
		$pid = trim($_POST['pid']);
		$com = clean($_POST['comment']);
		
		if($ptp=="inquire"){
			$to = trim($_POST['inquire']);
			$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `id`='$pid'"); 
			$row = $res[0]; $code=$row['code']; $amnt=$row['amount']; $day=date("M d, Y - h:i a",$row['time']); $name=$row['name']; $tym=time();
			$mssg = "Kindly explain the MPESA payout of KES $amnt with transaction $code ON $day to $name for accounting purposes";
			$ref = chatref([$sid,$to],"B2C Inquiry");
			
			if($db->insert(2,"INSERT INTO `tickets$cid` VALUES(NULL,'$sid','$to','B2C Inquiry','$mssg','$ref','$tym','0')")){
				$db->insert(2,"UPDATE `payouts$cid` SET `status`='2' WHERE `id`='$pid'");
				echo "success";
			}
			else{ echo "Failed to complete the request"; }
		}
		elseif($ptp=="loan"){
			$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `id`='$pid'"); 
			$row=$res[0]; $code=$row['code']; $name=$row['name']; $amnt=$row['amount']; $fon=$row['phone']; $ltm=$row['time'];
			$tym=time(); $cont=ltrim(ltrim($fon,"254"),"0"); $ltbl="org".$cid."_loantemplates";
			$des="$code - Loan for $name KES $amnt";
			
			$qri = $db->query(2,"SELECT *FROM `$ltbl` WHERE `phone`='$cont' AND `status`='$ltm'");
			if($qri){
				$bran = $qri[0]['branch']; $tid=$qri[0]['id'];
				if($db->insert(2,"UPDATE `payouts$cid` SET `status`='$tym' WHERE `id`='$pid'")){
					$chk = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `refno`='$tid'");
					if(!$chk){
						doublepost([$bran,DEF_ACCS['mpesabulk'],$amnt,$des,$tid,$sid],[$bran,getrule("loans")['debit'],$amnt,$des,$tid,$sid],'',$ltm,"auto");
					}
					
					savelog($sid,"Recorded loan Ledger $des of Ksh $amnt");
					echo "success";
				}
				else{ echo "Failed: Something went wrong!"; } 
			}
			else{ echo "Failed: ".prepare($name)." is not found in disbursement template for ".date('d-m-Y',$ltm); }
		}
		elseif($ptp=="advance"){
			if($db->istable(3,"advances$cid")){
				$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `id`='$pid'"); 
				$row=$res[0]; $code=$row['code']; $name=$row['name']; $amnt=$row['amount']; $fon=$row['phone']; $ltm=$row['time'];
				$tym=time(); $cont=ltrim(ltrim($fon,"254"),"0"); $des="$code - Salary Advance for $name"; 
				$rev=json_encode(array(["db"=>2,"tbl"=>"payouts$cid","col"=>"code","val"=>$code,"update"=>"status:0"]));
				
				$qry = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `contact`='$cont'");
				if($qry){
					$uid = $qry[0]['id'];
					$qri = $db->query(3,"SELECT *FROM `advances$cid` WHERE `staff`='$uid' AND (`status`='200' OR `status`='400')");
					if($qri){
						$bran = staffInfo($uid)['branch'];
						if($db->insert(2,"UPDATE `payouts$cid` SET `status`='$tym' WHERE `id`='$pid'")){
							doublepost([$bran,DEF_ACCS['mpesabulk'],$amnt,$des,$code,$sid],[$bran,getrule("advances")['debit'],$amnt,$des,$code,$sid],'',$ltm,$rev);
							$db->insert(3,"UPDATE `advances$cid` SET `status`='$tym' WHERE `staff`='$uid' AND (`status`='200' OR `status`='400')");
							$db->insert(3,"UPDATE `deductions$cid` SET `status`='$tym' WHERE `staff`='$uid' AND `status`='0' AND `category`='Salary advance'");
							savelog($sid,"Recorded advance Ledger ($des) of Ksh $amnt");
							echo "success";
						}
						else{ echo "Failed: Something went wrong!"; } 
					}
					else{ echo "Failed: ".prepare($name)." has no pending advance request"; }
				}
				else{ echo "Disbursement contact $fon is not found from staffs"; }
			}
			else{ echo "Failed: No advance records found!"; }
		}
		else{
			if($db->insert(2,"UPDATE `payouts$cid` SET `status`='3' WHERE `id`='$pid'")){
				echo "success";
			}
			else{ echo "Failed to complete the request"; }
		}
		exit();
	}
	
	#view disbursement
	if(isset($_GET['view'])){
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):"";
		$fro = (isset($_GET['dfro'])) ? trim($_GET['dfro']):date("Y-m-d",strtotime(date("Y-M")));
		$to = (isset($_GET['dto'])) ? trim($_GET['dto']):date("Y-m-d");
		$fd = strtotime($fro); $td=strtotime($to);
		$fdy = ($td<$fd) ? $td:$fd; $tdy=($td<$fd) ? $fd:$td;
		$dfro = date("Y-m-d",$fdy); $dto=date("Y-m-d",$tdy);
		
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		$thismon = strtotime(date("Y-M"));
		$lim = getLimit($page,40); $staff[0]="----";
		$bran = ($me['access_level']=="hq") ? 0:$me['branch'];
		$fetch = ($str) ? "(`phone` LIKE '$str%' OR `name` LIKE '%$str%' OR `code`='$str')":"`day` BETWEEN $fdy AND $tdy";
		$fetch.= ($bran) ? " AND `branch`='$bran'":"";
		$fetch.= ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? " AND ".setRegion($cnf["region"]):"";
		
		$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$trs=""; $no=(40*$page)-40; $cdys=[]; 
		$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE $fetch ORDER BY `id` DESC,`time` DESC $lim");
		if($res){
			foreach($res as $row){
				$phone=$row['phone']; $amnt=number_format($row['amount']); $name=prepare($row['name']); $code=$row['code']; $tm=$row['time'];
				$tym=date("M d, h:i a",$tm); $no++; $status=$row['status']; $id=$row['id']; $fee=$row['fee']; $state=""; $usa=$staff[$row['user']];
				if($row['posted']==0){ $cdys[$row['day']]=$row['day']; }
				
				if($status<3){
					$btx = ($status==2) ? "Post Inquiry":"Confirm";
					$click = (in_array("reconcile disbursements",$perms)) ? "popupload('accounts/disbursement.php?payout=$id')":"toast('Permission Denied!')";
					$state = "<a href='javascript:void(0)' onclick=\"$click\">$btx</a>";
				}
				else{ $state = "<i style='color:grey;'>Verified</i>"; }
				$trs.="<tr id='tr$id'><td>$no</td><td>$tym</td><td>$phone</td><td>$name</td><td>$code</td><td>$amnt</td><td>$usa</td><td>$fee</td><td id='seen$id'>$state</td></tr>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS totals,SUM(amount) AS totpay FROM `payouts$cid` WHERE $fetch");
		$qri = $db->query(2,"SELECT MIN(day) AS mdy,MAX(day) AS mxd FROM `payouts$cid`");
		$totals = ($sql) ? number_format($sql[0]['totpay']):0; $tots=($sql) ? $sql[0]['totals']:0;
		$mnd = ($qri[0]['mdy']) ? date("Y-m-d",$qri[0]['mdy']):date("Y-m-d");
		$mxd = ($qri[0]['mxd']) ? date("Y-m-d",$qri[0]['mxd']):date("Y-m-d");
		
		$prnt = ($trs) ? "<button style='float:right;padding:3px;font-size:14px;margin-left:5px' class='bts' onclick=\"printdoc('payout.php?src=$fdy&dy=$tdy&br=$bran&str=','Payouts')\">
		<i class='fa fa-print'></i> Print</button>":"";
		$post = (count($cdys)) ? "<button class='bts' style='font-size:14px;padding:3px;float:right;' onclick=\"popupload('accounts/disbursement.php?postcharge')\">
		<i class='fa fa-level-up'></i> Post Charges</button>":"";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;'>
			<h3 style='font-size:23px;padding-bottom:10px;color:#191970;'>$backbtn MPESA Disbursements
			<span style='float:right;font-weight:bold;color:#191970;font-size:18px'>Ksh $totals</span></h3><hr style='margin:0px'>
			<div style='width:100%;overflow:auto'>
				<table class='table-striped' cellpadding='7' style='width:100%;font-size:14px;min-width:700px;margin:0px'>
					<caption style='caption-side:top'> $prnt $post
						<input type='search' style='width:160px;font-size:15px;padding:3px 7PX;float:right' placeholder='&#xf002; Search payment' value=\"".prepare($str)."\"
						onsearch=\"loadpage('accounts/disbursement.php?view&str='+cleanstr(this.value))\">
						<input type='date' style='width:150px' id='dfro' onchange=\"getday()\" value='$dfro' min='$mnd' max='$mxd'> ~
						<input type='date' style='width:150px' id='dto' onchange=\"getday()\" value='$dto' min='$mnd' max='$mxd'> 
					</caption>
					<tr style='font-weight:bold;color:#191970;background:#E6E6FA;'><td colspan='2'>Date</td><td>Phone</td><td>Name</td><td>Transaction</td>
					<td>Amount</td><td>Initiator</td><td>Charges</td><td>Status</td></tr> $trs
				</table><br>".getLimitDiv($page,40,$tots,"accounts/disbursement.php?view&dfro=$dfro&dto=$dto&str=".urlencode($str))."
			</div>
		</div>
		<script>
			function getday(){
				loadpage('accounts/disbursement.php?view&dfro='+$('#dfro').val()+'&dto='+$('#dto').val());
			}
		</script>";
		
		savelog($sid,"Viewed MPESA Disbursements from $dfro to $dto");
	}
	
	# post charges
	if(isset($_GET['postcharge'])){
		$lis = "";
		$sql = $db->query(2,"SELECT DISTINCT day FROM `payouts$cid` WHERE `posted`='0' ORDER BY day ASC");
		if($sql){
			foreach($sql as $row){
				$dy=$row['day']; 
				$res = $db->query(2,"SELECT SUM(fee) as tfee FROM `payouts$cid` WHERE `day`='$dy' AND `posted`='0'");
				$day = date("M d, Y",$dy); $amnt=$res[0]['tfee'];
				$lis.="<tr id='$dy'><td>$day</td><td>Ksh ".number_format($amnt)."</td><td><a href='javascript:void(0)' onclick=\"postcharges('$dy','$amnt','$day')\">
				<i class='fa fa-level-up'></i> Post</button></td></tr>";
			}
		}
		
		echo "<div style='padding:15px;max-width:350px;margin:0 auto'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Post Bulk Charges</h3><br>
			<table style='width:100%' cellpadding='8' class='table-striped'>
				<tr style='font-weight:bold;color:#191970;background:#e6e6fa'><td>Date</td><td>Charges</td><td>Action</td></tr> $lis
			</table><br>
		</div>";
	}
	
	# confirm payment
	if(isset($_GET['payout'])){
		$pid = trim($_GET['payout']);
		$res = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `id`='$pid'");
		$row = $res[0]; $state=$row['status']; $opts=$users="";
		$list = array("requisition"=>"Requisition","loan"=>"Client Loan","expense"=>"Company Expense","advance"=>"Salary Advance","inquire"=>"Request Explanation");
		
		foreach($list as $key=>$one){
			$opts.=($state==2 && $key=="inquire") ? "":"<option value='$key'>$one</option>";
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$users.="<option value='".$row['id']."'>".prepare(ucwords($row['name']))."</option>";
		}
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Confirm Payout</h3><br>
			<form method='post' id='pform' onsubmit=\"confirmpay(event,'$pid')\">
				<input type='hidden' name='pid' value='$pid'>
				<p>Select Type of Payment<br><select name='paytp' id='stp' onchange=\"checkpaytp(this.value)\" style='width:100%'>$opts</select></p>
				<p id='users' style='display:none'>Send Inquiry To<br><select name='inquire' style='width:100%'>$users</select></p>
				<p>Comment<br><textarea name='comment' class='mssg'></textarea></p>
				<p style='text-align:right'><button class='btnn'>Post</button></p><br>
			</form>
		</div><br>";
	}

	ob_end_flush();
?>

<script>
	
	function postcharges(day,amnt,dy){
		if(confirm("Post charges for "+dy+"?")){
			$.ajax({
				method:"post",url:path()+"accounts/disbursement.php?sid=<?php echo $sid; ?>",data:{postcharge:day,damnt:amnt},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#"+day).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function confirmpay(e,pid){
		e.preventDefault();
		if(_("stp").value=="expense"){ popupload("accounts/index.php?postentry=disb&ref="+pid); }
		else{
			var txt = (_("stp").value=="inquire") ? "Create a ticket for payment inquiry?":"Sure to confirm payment?";
			if(confirm(txt)){
				var data = $("#pform").serialize();
				$.ajax({
					method:"post",url:path()+"accounts/disbursement.php?sid=<?php echo $sid; ?>",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ closepop(); $("#seen"+pid).html("<i style='color:grey;'>Verified</i>"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function checkpaytp(val){
		if(val=="inquire"){ $("#users").show(); }
		else{ $("#users").hide(); }
	}

</script>