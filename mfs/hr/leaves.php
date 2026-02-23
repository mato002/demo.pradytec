<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# manage leaves
	if(isset($_GET['manage'])){
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y");
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		$ltbl="org".$cid."_leaves"; $stbl="org".$cid."_staff";
		$cnf = json_decode($me["config"],1);
		
		$exclude = array("id","staff","leave_end","leave_type","leave_start","reason","days","approvals","status","time","year");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = array("id"=>"number","staff"=>"number","leave_type"=>"text","reason"=>"textarea","days"=>"number","leave_start"=>"date","leave_end"=>"date",
		"approvals"=>"textarea","status"=>"number","time"=>"number","year"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $cols=array_keys($ftc);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $cdf=json_decode($row['config'],1);
			$post[$row['id']]=(isset($cdf["mypost"])) ? staffPost($row['id']):[$row['position']=>$row['access_level']];
		}
		
		$ppg = 30; $lim = getLimit($page,$ppg);
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		$cond = ($me['access_level']=="hq") ? "":"AND st.branch='".$me['branch']."'";
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"st.branch"):$cond;
		$cond = ($me['access_level']=="portfolio") ? "AND st.staff='$sid'":$cond;
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='leave'");
		$lvs = ($app) ? json_decode($app[0]['levels'],1):[]; 
		
		$trs=$ths=""; $no=($ppg*$page)-$ppg; $appr=[]; $fro=$no+1;
		if($db->istable(2,$ltbl)){
			$db->execute(2,"UPDATE `$ltbl` SET `status`='11' WHERE `status`<10 AND `year`='$yr' AND `leave_end`<".time());
			$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ltbl` AS lv INNER JOIN `$stbl` AS st ON lv.staff=st.id WHERE lv.year='$yr' $cond"); 
			$totals = ($res) ? $res[0]['total']:0;
			
			$res = $db->query(2,"SELECT lv.* FROM `$ltbl` AS lv INNER JOIN `$stbl` AS st ON lv.staff=st.id WHERE lv.year='$yr' $cond ORDER BY lv.time DESC $lim");
			if($res){
				foreach($res as $row){
					$css = "padding:5px;border-radius:4px;color:#fff"; $rid=$row['id']; $tds=$npos=""; $apprs="None"; $no++; $plev=0;
					$type=clean(ucwords($row['leave_type'])); $resn=nl2br(prepare(ucfirst($row['reason']))); $sname=$staff[$row['staff']]; 
					$start=date("M d",$row['leave_start']); $end=date("M d",$row['leave_end']); $dls=[];
					$dif = $row['leave_end']-$row["leave_start"]; $dur=($dif>86400) ? round($dif/86400)." Days":round($dif/3600)." Hours";
					
					$st=($row['status']>400) ? 20:$row['status']; $icon="<i class='fa fa-circle'></i>";
					$status = array(20=>"<span style='$css;color:green'>$icon Running</span>",11=>"<span style='$css;color:#8A2BE2'>$icon Unattended</span>",
					15=>"<span style='$css;color:#C71585'>$icon Declined</span>",400=>"<span style='$css;color:#20B2AA'>$icon Ended</span>");
					
					foreach($cols as $col){
						if(!in_array($col,$exclude)){
							$val=prepare(ucfirst($row[$col]));
							$tds.="<td>$val</td>"; $ths.=($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					if($row['approvals']){
						$des = json_decode($row['approvals'],1); $apprs="";
						if(is_array($des)){
							foreach($des as $by=>$comment){
								$names=explode(" ",$staff[$by]); $name=$names[0]; $name.=(count($names)>1) ? " ".$names[1]:""; 
								$apprs.= "<p style='margin:0px'>$name:</b> <i style='font-size:13px'>".nl2br(prepare(ucfirst($comment)))."</i></p>";
							}
						}
					}
					
					foreach($lvs as $key=>$one){
						foreach($post[$row['staff']] as $poz=>$ac){
							if(isset($one[$poz])){if($one[$poz]){ $dls[$key]=$one[$poz]; }}
						}
					}
					
					if(count($dls)){
						foreach($dls as $pos=>$user){
							$key=$pos-1; if($row['status']==$key){ $npos=$user; $appr[str_replace(" ","_",$user)]=$key; }
							$status[$key]="<i style='color:grey;font-size:13px'>Waiting ".prepare(ucfirst($user))."</i>";
						}
						
						$posts = array("super user",$npos);
						$plev = (in_array("approve leave",$perms) && $st<10 && in_array($me['position'],$posts)) ? 1:0;
					}
					else{ $status[0]="<i style='color:grey;font-size:13px'>Unapproved</i>"; }
					
					$state = ($plev) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/leaves.php?approve=$rid')\"><i class='fa fa-gavel'></i> Take Action</a>":$status[$st];
					$trs.= "<tr valign='top'><td>$no</td><td>$sname</td><td>$start - $end</td><td>$dur</td><td>$type</td><td>$resn</td><td>$apprs</td>$tds<td>$state</td></tr>";
				}
			}
		}
		
		$sett = (in_array("update leave settings",$perms)) ? "<button class='bts' onclick=\"loadpage('setup.php?leavesett')\" 
		style='padding:5px;font-size:14px;float:right;margin-left:7px'><i class='fa fa-wrench'></i> Settings</button>":"";
		
		$tyr = date("Y"); $yrs="<option value='$tyr'>$tyr</option>";
		if($db->istable(2,$ltbl)){
			$res = $db->query(2,"SELECT DISTINCT `year` FROM `$ltbl` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
			if($res){
				foreach($res as $row){
					$year=$row['year']; $cnd = ($year==$yr) ? "selected":"";
					$yrs.="<option value='$year' $cnd>$year</option>";
				}
			}
		}
		
		echo "<div class='cardv' style='max-width:1400px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;min-width:650px;max-width:1400px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $yr Staff Leaves $sett
				<select style='padding:5px;width:80px;float:right' onchange=\"loadpage('hr/leaves.php?manage&yr='+this.value)\">$yrs</select></h3>
				<table class='table-striped' style='width:100%;font-size:15px;margin-top:10px' cellpadding='7'>
					<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td colspan='2'>Employee</td><td>Leave Period</td><td>Duration</td>
					<td>Leave Type</td><td>Reason for Leave</td><td>Approvals</td>$ths<td>Status</td></tr> $trs
				</table><br>".getLimitDiv($page,$ppg,$totals,"hr/leaves.php?manage&yr=$yr")."
			</div>
		</div>";
		savelog($sid,"Viewed Staff Leave records for $yr");
	}
	
	# approve/decline leave
	if(isset($_GET['approve'])){
		$rid = trim($_GET['approve']);
		$res = $db->query(2,"SELECT *FROM `org".$cid."_leaves` WHERE `id`='$rid'");
		$row = $res[0]; $next=$row['status']+1;
		
		echo "<div style='padding:5px;margin:0 auto;max-width:350px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Approve/Decline Leave</h3><br>
			<form method='post' id='lform' onsubmit='approveleave(event)'>
				<input type='hidden' name='nval' value='$next'> <input type='hidden' name='lsid' value='$rid'> 
				<table style='width:100%;' cellpadding='10'>
					<tr><td><input type='radio' name='apprlv' value='1' checked> &nbsp; Approve</radio></td>
					<td><input type='radio' name='apprlv' value='0'> &nbsp; Decline</radio></td></tr>
					<tr><td colspan='2'>Comments<br><textarea class='mssg' name='comment' required></textarea></td></tr>
					<tr><td><br><div style='width:170px;border:1px solid #dcdcdc;height:35px'><input type='text' name='otpv' style='border:0px;padding:4px;width:80px' 
					placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
					<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
					<td style='text-align:right'><br><button class='btnn'>Confirm</button></td></tr>
				</table><br>
			</form><br>
		</div>";
	}
	
	# apply leave
	if(isset($_GET['apply'])){
		$lid = trim($_GET['apply']);
		$ltbl="org".$cid."_leaves";
		$exclude = array("id","staff","days","approvals","status","time","year");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = array("id"=>"number","staff"=>"number","leave_type"=>"text","reason"=>"textarea","days"=>"number","leave_start"=>"date","leave_end"=>"date",
		"approvals"=>"textarea","status"=>"number","time"=>"number","year"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$defv = array("year"=>date('Y'),"status"=>0,"time"=>time(),"staff"=>$sid,"days"=>0);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(array_key_exists($fld,$defv)) ? $defv[$fld]:""; 
		}
	
		if($lid){
			$row = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$lid'"); $dvals=$row[0];
		}
		
		$lis="";
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="leave_type"){
					$res = $db->query(1,"SELECT *FROM `leave_settings` WHERE `client`='$cid'");
					if($res){
						$opts = "";
						foreach($res as $row){
							$cnd = ($row['leave_type']==$dvals[$field]) ? "selected":"";
							$opts.="<option value='".$row['leave_type']."' $cnd>".prepare(ucfirst($row['leave_type']))."</option>";
						}
					}
					else{ $opts = "<option value='annual leave'>Anual Leave</option>"; }
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($field=="reason"){
					$lis.="<p>Reason For Leave<br><textarea name='reason' class='mssg' required>".prepare($dvals[$field])."</textarea></p>";
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
					if($field=="leave_end" or $field=="leave_start"){
						$add = ($dvals[$field]) ? "min='".date('Y-m-d',$dvals[$field])."'":"min='".date('Y-m-d')."'"; 
						$val = ($dvals[$field]) ? date("Y-m-d",$dvals[$field]):"";
					}
					else{ $val=prepare(ucfirst($dvals[$field])); $add=""; }
					
					$lis.="<p>$fname<br><input type='$dtype' style='width:100%' value=\"$val\" $add name='$field' required></p>";
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$title = ($lid) ? "Edit Leave Record":"Apply for Leave";
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='lform' onsubmit=\"saveleave(event)\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($fields),1)."'>
				<input type='hidden' name='id' value='$lid'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Apply</button></p>
			</form>
		</div>";
	}
	
	ob_end_flush();
?>

<script>
	
	function approveleave(e){
		e.preventDefault();
		if(confirm("Confirm Leave request?")){
			var data=$("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/leaves.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast(res); closepop(); loadpage("hr/leaves.php?manage"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveleave(e){
		e.preventDefault();
		if(confirm("Save Leave application?")){
			var data=$("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/leaves.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast(res); closepop(); loadpage("account.php?leaves"); }
				else{ alert(res); }
			});
		}
	}
	
</script>