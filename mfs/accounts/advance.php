<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# manage advances
	if(isset($_GET['manage'])){
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y");
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		$atbl="advances$cid"; $tmon=strtotime(date("Y-M"));
		
		$exclude = array("id","staff","approvals","approved","month","status","year","time","reason","amount","charges");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$atbl'");
		$def = array("id"=>"number","staff"=>"number","amount"=>"number","reason"=>"textarea","approvals"=>"textarea","approved"=>"text","charges"=>"text","month"=>"number",
		"year"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $fields = array_keys($ftc);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $pozts[$row['id']]=$row['position'];
		}
		
		# distinct month
		$mons="<option value='$tmon'>".date('M Y')."</option>"; $mns=[$tmon];
		if($db->istable(3,$atbl)){
			$res = $db->query(3,"SELECT DISTINCT `month` FROM `$atbl` WHERE NOT `month`='$tmon' AND `year`='$yr' ORDER BY `month` DESC");
			if($res){
				foreach($res as $row){
					$mn=$row['month']; $cnd = ($mn==$mon) ? "selected":""; $mns[]=$mn;
					$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
				}
			}
		}
		
		$lim = getLimit($page,30); $mon = ($mon) ? $mon:$mns[0];
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='advance'");
		$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
		$lvs=$ldef=($app) ? json_decode($app[0]['levels'],1):[]; $hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[]; 
		$super = (array_key_exists("advance",$hlevs)) ? $hlevs['advance']:[]; $dcd=count($lvs);
		
		$trs=$ths=""; $no=(30*$page)-30; $appr=[]; $totals=0;
		if($db->istable(3,$atbl)){
			$res = $db->query(3,"SELECT COUNT(*) AS total FROM `$atbl` WHERE `month`='$mon'"); $totals = ($res) ? $res[0]['total']:0;
			$db->insert(3,"UPDATE `$atbl` SET `status`='15' WHERE `status`<10 AND `year`='$yr' AND NOT `month`='$tmon'");
			
			$res = $db->query(3,"SELECT *FROM `$atbl` WHERE `month`='$mon' ORDER BY time DESC $lim");
			if($res){
				foreach($res as $row){
					$css = "padding:5px;border-radius:4px;color:#fff"; $tds=$npos=""; $apprs="None"; $no++; $plev=0;
					$resn=nl2br(prepare(ucfirst($row['reason']))); $apprvd=number_format($row['approved']); $sname=$staff[$row['staff']]; $rid=$row['id'];
					$day=date("M d, H:i",$row['time']); $amnt=number_format($row['amount']);
					
					$st=($row['status']>200) ? 20:$row['status']; $icon="<i class='fa fa-circle'></i>";
					$status = array(20=>"<span style='$css;color:green'>$icon Disbursed</span>",11=>"<span style='$css;color:#8A2BE2'>$icon Unattended</span>",
					15=>"<span style='$css;color:#C71585'>$icon Declined</span>",200=>"<span style='$css;color:#2f4f4f'>$icon Pending</span>");
					
					foreach($fields as $col){
						if(!in_array($col,$exclude)){
							$val=prepare(ucfirst($row[$col]));
							$tds.="<td>$val</td>"; $ths.=($no==1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					if($row['approvals']){
						$des = json_decode($row['approvals'],1); $apprs="";
						foreach($des as $by=>$comment){
							$names=explode(" ",$staff[$by]); $name=$names[0]; $name.=(count($names)>1) ? " ".$names[1]:"";  
							$apprs.= "<p style='margin:0px'><b>$name:</b> <i style='font-size:13px'>".nl2br(prepare(ucfirst($comment)))."</i></p>";
						}
					}
					
					if(array_key_exists($pozts[$row['staff']],$super)){
					    foreach($lvs as $key=>$one){
					        if($one==$pozts[$row['staff']]){ $lvs[$key]=$super[$one]; }
					    }
					}
					
					foreach($lvs as $key=>$one){
						$levels[$key]=$one;
					}
					
					if($dcd){
						foreach($levels as $pos=>$user){
							$key=$pos-1; if($row['status']==$key){ $npos=$user; $appr[str_replace(" ","_",$user)]=$key; }
							$status[$key]="<i style='color:grey;font-size:13px'>Waiting ".prepare(ucfirst($user))."</i>";
						}
						
						$posts = array("super user",$npos);
						$plev = (in_array("approve salary advance",$perms) && $st<10 && in_array($me['position'],$posts)) ? 1:0;
					}
					
					$state = ($plev) ? "<a href='javascript:void(0)' onclick=\"popupload('accounts/advance.php?approve=$rid')\">
					<i class='fa fa-check'></i> Take Action</a>":$status[$st]; $lvs=$ldef;
					$trs.= "<tr valign='top'><td>$no</td><td>$sname</td><td>$day</td><td>KES $amnt</td><td>$resn</td><td>$apprs</td>$tds<td>KES $apprvd</td><td>$state</td></tr>";
				}
			}
		}
		
		$sett = (in_array("update advance settings",$perms)) ? "<button class='bts' onclick=\"popupload('setup.php?setadvance')\" 
		style='padding:5px;font-size:14px;float:right;margin-left:7px'><i class='fa fa-wrench'></i> Settings</button>":"";
		
		$tyr = date("Y"); $yrs="<option value='$tyr'>$tyr</option>";
		if($db->istable(3,$atbl)){
			$res = $db->query(3,"SELECT DISTINCT `year` FROM `$atbl` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
			if($res){
				foreach($res as $row){
					$year=$row['year']; $cnd = ($year==$yr) ? "selected":"";
					$yrs.="<option value='$year' $cnd>$year</option>";
				}
			}
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;min-width:650px'>
				<h3 style='font-size:19px;color:#191970'>$backbtn ".date('M Y',$mon)." Salary Advances $sett
				<select style='padding:5px;width:110px;float:right' onchange=\"loadpage('accounts/advance.php?manage&yr=$yr&mon='+this.value)\">$mons</select>
				<select style='padding:5px;width:70px;float:right;margin-right:5px' onchange=\"loadpage('accounts/advance.php?manage&yr='+this.value)\">$yrs</select></h3>
				<table class='table-striped' style='width:100%;font-size:15px;margin-top:13px' cellpadding='7'>
					<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td colspan='2'>Staff</td><td>Date</td>
					<td>Request</td><td>Reason for Request</td><td>Approvals</td>$ths<td>Approved</td><td>Status</td></tr> $trs
				</table><br>".getLimitDiv($page,30,$totals,"accounts/advance.php?manage&yr=$yr&mon=$mon")."
			</div>
		</div>";
		
		savelog($sid,"Viewed Salary advance records for $yr");
	}
	
	# approve/decline advance
	if(isset($_GET['approve'])){
		$rid = trim($_GET['approve']);
		$cond = (isset($_GET['req'])) ? trim($_GET['req']):0;
		$pay = (isset($_GET['pay'])) ? trim($_GET['pay']):0;
		$res = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid'");
		$row = $res[0]; $next=$row['status']+1; $stid=$res[0]['staff']; $amnt=$row['amount'];
		$user = staffInfo($stid);
		
		$setts = array("advancelimit"=>60,"advancebypasslim"=>0);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		$perc = $setts['advancelimit']/100; $bypass=$setts['advancebypasslim'];
		
		if($db->istable(3,"payroll$cid") && !$pay){
			$mons=["","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
			$lmon=(date("m")==1) ? 12:date("m")-1; $yr=(date("m")==1) ? date("Y")-1:date("Y");
			$dim=date("d",monrange($lmon,$yr)[1]); $mon=strtotime("$yr-".$mons[$lmon]);
			
			$res = $db->query(3,"SELECT SUM(deductions+netpay) AS salary FROM `payroll$cid` WHERE `month`='$mon' AND `staff`='$stid'");
			$salary = ($res) ? $res[0]['salary']:0; $pay = ($salary) ? round($salary/$dim):0; 
		}
		
		$legit=($pay) ? $pay*date("d")*$perc:0; $deds=0; $tmon=strtotime(date("Y-M"));
		if($db->istable(3,"deductions$cid")){
			$res = $db->query(3,"SELECT *FROM `deductions$cid` WHERE `staff`='$stid' AND `month`='$tmon' AND `status`>'200'");
			if($res){
				foreach($res as $row){ $legit-=$row['amount']; $deds+=$row['amount']; }
			}
		}
		
		$pdes=($deds) ? "<br>Current Deductions KES ".fnum($deds):""; $accept=($legit>$amnt) ? $amnt:$legit; $opts=""; 
		$list = ($bypass<1 && $amnt>$legit) ? array(0=>"Decline Request"):array(1=>"Approve Request",0=>"Decline Request");
		$lock = (count($list)>1) ? "value='$accept' style='width:120px;'":"value='0' style='width:120px;cursor:not-allowed' readonly";
		
		foreach($list as $key=>$txt){
			$cnd = ($legit<=$amnt && $key==0) ? "selected":""; 
			$opts.="<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div style='padding:5px;margin:0 auto;max-width:350px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Approve/Decline Advance</h3><br>
			<p><b>".prepare(ucwords($user['name']))."</b>, $pdes</p>
			<table style='width:100%;text-align:center;font-size:15px;margin-bottom:10px' cellpadding='5' class='table-bordered'>
			<tr style='font-weight:bold;color:#191970;background:#f8f8f0'><td>Requested</td><td>Qualified</td></tr>
			<tr><td>KES ".fnum($amnt)."</td><td>KES ".fnum($legit)."</td></tr></table>
			<p>Average Daily Pay<br><input type='number' id='dpy' value='$pay' style='width:150px;padding:4px;' 
			onchange=\"popupload('accounts/advance.php?approve=$rid&pay='+cleanstr(this.value))\"></p>
			
			<form method='post' id='aform' onsubmit='approveadvance(event)'>
				<input type='hidden' name='nval' value='$next'> <input type='hidden' name='asid' value='$rid'> 
				<table style='width:100%;' cellpadding='10'>
					<tr><td>Action<br><select name='apprlv' style='width:100%'>$opts</select></td>
					<td>Approved Amount<br><input type='number' name='accepted' $lock required></td></tr>
					<tr><td colspan='2'>Comments<br><textarea class='mssg' name='comment' required></textarea></td></tr>
					<tr><td><div style='width:170px;border:1px solid #dcdcdc;height:35px'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
					placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
					<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
					<td style='text-align:right'><button class='btnn'>Confirm</button></td></tr>
				</table><br>
			</form><br>
		</div>";
	}
	
	# apply advance
	if(isset($_GET['apply'])){
		$aid = trim($_GET['apply']);
		$atbl="advances$cid";
		$exclude = array("id","staff","approvals","approved","month","status","year","time","charges");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$atbl'");
		$def = array("id"=>"number","staff"=>"number","amount"=>"number","reason"=>"textarea","approvals"=>"textarea","approved"=>"text","charges"=>"text","month"=>"number",
		"year"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$defv = array("month"=>strtotime(date('Y-M')),"status"=>0,"time"=>time(),"staff"=>$sid,"approved"=>0,"year"=>date("Y"),"charges"=>"[]");
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(array_key_exists($fld,$defv)) ? $defv[$fld]:""; 
		}
	
		if($aid){
			$row = $db->query(3,"SELECT *FROM `$atbl` WHERE `id`='$aid'"); $dvals=$row[0];
		}
		
		$lis="";
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="amount"){
					$lis.="<p>Requesting Amount<br><input type='number' style='width:100%' value='$dvals[$field]' name='$field' required></p>";
				}
				elseif($field=="reason"){
					$lis.="<p>Reason For Advance<br><textarea name='reason' class='mssg' required>".prepare($dvals[$field])."</textarea></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop'>".prepare(ucwords($drop))."</option>";
					}
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$val' $cnd></option>";
					}
					$ran = rand(12345678,87654321);
					$lis.="<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
				}
				elseif($dtype=="textarea"){
					$lis.="<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{ 
					$val=prepare(ucfirst($dvals[$field]));
					$lis.="<p>$fname<br><input type='$dtype' style='width:100%' value=\"$val\" name='$field' required></p>";
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$agree = "I apply for the above mentioned salary advance and i authorize @employer to deduct the loan repayments from my salary for payroll month @current_month. 
		I understand that I am eligible for no more than two emergency payroll per calendar year that the amount requested shall not exceed @limit of my earnings to date for the current month. 
		I also agree that if I terminate employment prior to total repayment, I authorize the employer to deduct any unpaid advance amount from my salary owed to me at time of termination.";
		
		$setts = array("advanceapplication"=>1,"advancelimit"=>60,"advancefrom"=>10,"advanceto"=>28,"advancebypasslim"=>0,"advanceagreement"=>$agree);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=prepare(trim($row['value'])); }
		}
		
		$title = ($aid) ? "Edit Advance Request":"Apply salary Advance";
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='advanceagreement' AND `client`='$cid'");
		$txt = nl2br(prepare(ucfirst($setts['advanceagreement']))); $comp=ucwords(strtolower(mficlient()['company']));
		$accept = str_replace(array("@employer","@current_month","@limit"),array("<b>$comp</b>","<b>".date("F Y")."</b>","<b>".$setts['advancelimit']."%</b>"),$txt);
		$from = date('F ').$setts['advancefrom']; $to=$setts['advanceto']; $monend=date("d",monrange(date("m"),date("Y"))[1]);
		$dto = ($monend>$to) ? date('F ').$to:date('F ').$monend;
		
		$cond = ((date("d")>=$setts['advancefrom'] && date("d")<=$setts['advanceto']) or $aid) ? "<table style='width:100%' cellpadding='5'>
		<tr><td><div style='width:170px;border:1px solid #dcdcdc;height:35px;'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
		placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
		<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
		<td style='text-align:right'><button class='btnn'>Apply</button></td></tr></table>":
		"<p style='color:#ff4500;border:1px solid pink;background:#FFEBCD;padding:10px'><b>Att:</b> Advance Application is only allowed between <b>$from & $dto</b></p>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>$title</h3><br>
			<form method='post' id='aform' onsubmit=\"saveadvance(event)\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($fields),1)."'>
				<input type='hidden' name='id' value='$aid'> $lis <p style='color:#2f4f4f'>$accept</p>
				<p><input type='checkbox' name='accept'> &nbsp; I accept Terms & Conditions</p> $cond <br>
			</form><br>
		</div>";
	}
	
	ob_end_flush();
?>

<script>
	
	function approveadvance(e){
		e.preventDefault();
		if(confirm("Confirm advance request?")){
			var data=$("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/advance.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast(res); closepop(); loadpage("accounts/advance.php?manage"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveadvance(e){
		e.preventDefault();
		if(!checkboxes("accept")){ alert("You have to agree to Terms & Conditions First!"); }
		else{
			if(confirm("Sure to send advance application?")){
				var data=$("#aform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/advance.php",data:data,
					beforeSend:function(){ progress("Sending...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast(res); closepop(); loadpage("account.php?advances"); }
					else{ alert(res); }
				});
			}
		}
	}
	
</script>