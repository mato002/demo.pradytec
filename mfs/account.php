<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	$stbl = "org".$cid."_staff";
	
	# notification tasks
	if(isset($_GET["tasks"])){
		$qri = fetchSqlite("sysnots","SELECT *FROM `tasks` WHERE `user`='$sid' AND `status`='0'");
		$cond = ($qri) ? "AND `status`='0'":""; 
		$tdy=strtotime(date("Y-M-d")); $data=[]; $all=0; $trs="";
	
		$img = "<img src='$path/assets/img/not.png' style='height:60px;border-radius:50%;border-top-right-radius:0px;border:1px solid #f0f0f0'>";
		$res = fetchSqlite("sysnots","SELECT *FROM `tasks` WHERE `user`='$sid' $cond ORDER BY time DESC LIMIT 40");
		if($res){
			foreach($res as $row){
				$mssg = nl2br(prepare(ucfirst($row['task']))); $tym=date("M d, h:i a",$row['time']); $rid=$row['id']; $nav=prepare($row["nav"]);
				$notbtn = ($cond) ? "<i class='fa fa-circle' style='margin-left:-20px;color:#3CB371;font-size:23px'></i>":"";
				$act = ($nav) ? "<button class='btnn' style='float:right;margin-left:20px;padding:4px 8px;border-radius:40px;font-size:14px;background:#663399' onclick=\"loadpage('$nav')\">
				<i class='bi-arrow-right'></i> Proceed</button>":"";
				
				$trs.= "<div style='background:#f5f5f5;border-radius:10px;border-top-left-radius:32px;word-wrap:break-word;'>
					<table style='width:100%;word-wrap:break-word;overflow:ellipsis' cellpadding='0'><tr valign='top'>
						<td style='width:70px;text-align:center;'><div style='width:70px;height:70px;background:#e6e6fa;border-radius:50%;border-top-right-radius:0px;color:#4682b4;
						font-weight:bold;font-family:signika negative;line-height:65px;'>$img</div></td>
						<td><h3 style='font-size:18px;color:#4682b4;padding:7px 10px;background:#e6e6fa;font-family:signika negative'>$notbtn &nbsp; $tym</h3>
						<p style='color:#191970;word-wrap:break-word;'>$mssg $act</p></td>
					</tr></table>
				</div><br>";
			} 
		}
		
		$trs = ($trs) ? $trs:"<div style='background:#f5f5f5;border-radius:10px;border-top-left-radius:32px;word-wrap:break-word;'>
		<table style='width:100%' cellpadding='0'><tr valign='top'>
			<td style='width:70px;text-align:center;'><div style='width:70px;height:70px;background:#e6e6fa;border-radius:50%;border-top-right-radius:0px;color:#4682b4;
			font-weight:bold;font-family:signika negative;line-height:65px;'>$img</div></td>
			<td><h3 style='font-size:18px;color:#4682b4;padding:7px 10px;background:#e6e6fa;font-family:signika negative'>&nbsp; ".date('F d,Y - h:i a')."</h3> 
			<p style='color:#191970'>You have no notifications</p></td>
			</tr></table>
		</div><br>";
	
		$now=time(); $from=$now-86400;
		insertSqlite("sysnots","UPDATE `tasks` SET `status`='$now' WHERE `user`='$sid' AND `status`='0'");
		insertSqlite("sysnots","DELETE FROM `tasks` WHERE `status`<$from AND NOT `status`='0'");
		
		$title = ($cond) ? "Recent Notifications":"Previous Notifications";
		echo "<div class='container cardv' style='max-width:900px;padding:15px;min-height:400px'>
			<h3 style='color:#4682b4;font-size:20px;'>$backbtn $title</h3><hr>
			<div style='color:#2f4f4f;font-family:helvetica;margin-top:30px;overflow:auto;width:100%'> $trs </div>
		</div>";
	}
	
	# approval requests
	if(isset($_GET["appreqs"])){
		$vtp = intval($_GET["appreqs"]);
		$str = (isset($_GET['str'])) ? trim($_GET['str']):null;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid); $lim=getLimit($page,20);
		
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		$cond = ($vtp==1) ? "`status`>200":"`status`='$vtp'";
		$cond.= ($me["access_level"]=="branch") ? " AND `branch`='".$me["branch"]."'":"";
		$cond.= ($str) ? " AND (JSON_EXTRACT(details,'$.desc') LIKE '%$str%' OR JSON_EXTRACT(details,'$.title') LIKE '%$str%')":"";
		
		if(!$db->istable(2,"transtemps$cid")){
			$db->createTbl(2,"transtemps$cid",["type"=>"CHAR","user"=>"INT","branch"=>"INT","details"=>"TEXT","status"=>"INT","time"=>"INT"]); 
		}
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff`"); $dvs=""; $fon=0;
		foreach($sql as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
		
		$sql = $db->query(2,"SELECT *FROM `transtemps$cid` WHERE $cond ORDER BY `time` DESC $lim");
		if($sql){
			foreach($sql as $row){
				$des=json_decode($row['details'],1); $user=$staff[$row['user']]; $tym=date("d-m-Y, h:i a",$row['time']);
				$title=prepare($des["title"]); $desc=prepare($des["desc"]); $rid=$row['id'];
				if($row["status"]==0){
					$css = ($sid==$row["user"]) ? "cursor:not-allowed":"";
					$dsd1 = ($sid==$row["user"]) ? "disabled":"onclick=\"approvereq('$rid','15','$sid')\"";
					$dsd2 = ($sid==$row["user"]) ? "disabled":"onclick=\"approvereq('$rid','200','$sid')\""; 
					$appr = "<button class='btnn mt-2' style='padding:4px;background:brown;min-width:60px;$css' $dsd1><i class='bi-scissors'></i> Reject</button>
					&nbsp; <button class='btnn mt-2' style='padding:4px;min-width:60px;$css' $dsd2><i class='bi-check2-circle'></i> Approve</button>";
				}
				else{
					$by=$staff[$des["approval"]]; $adt=date("d-m-Y",$row["status"]);
					$appr = ($row['status']==15) ? "<span style='padding:5px;font-size:14px;color:#fff;background:brown'>Rejected By $by on $adt</span>":
					"<span style='padding:5px;font-size:14px;color:#fff;background:#228B22'><i class='bi-check2-circle'></i> Approved By $by on $adt</span>";
				}
				
				$dvs.= (in_array(trim($des["perm"]),$perms)) ? "<div class='col-12 col-sm-6 col-md-6 col-lg-4 mt-2 mb-2' id='div$rid'>
					<div style='margin:0 auto;padding:8px;border:1px solid #ccc'>
						<table style='width:100%' cellpadding='4'>
							<tr><td colspan='2' style='font-weight:bold;color:#191970'>$title</td></tr>
							<tr><td colspan='2'><p>$desc</p></td></tr><tr style='font-size:14px;font-weight:bold;background:#f0f0f0' valign='top'>
							<td style='color:purple'>From $user</td><td style='text-align:right'>$tym</td></tr>
							<tr><td colspan='2' style='text-align:right;font-family:signika negative'>$appr</td></tr>
						</table>
					</div>
				</div>":"";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS tot FROM `transtemps$cid` WHERE $cond");
		$def = array("Pending","Approved","Rejected"); $totals=($sql) ? intval($sql[0]['tot']):0; $opts="";
		$dvs = ($dvs) ? $dvs:"<div class='col-12'><p>No $def[$vtp] requests found</p></div>";
		foreach($def as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Approval Requests</h3>
				<p style='margin-top:15px'><select style='padding:7px;font-size:15px;width:120px' onchange=\"loadpage('account.php?appreqs='+this.value)\">$opts</Select>
				<input type='search' style='width:150px;padding:5px 8px;font-size:15px' placeholder='Search' value=\"$str\"
				onkeyup=\"fsearch(event,'account.php?appreqs=$vtp&str='+cleanstr(this.value))\" onsearch=\"loadpage('account.php?appreqs=$vtp&str='+cleanstr(this.value))\"></p><hr>
				<div class='row'>$dvs</div><br>".getLimitDiv($page,20,$totals,"account.php?appreqs=$vtp&str=".urlencode($str))."
			</div>
		</div>";
	}
	
	# teller operations
	if(isset($_GET["tellers"])){
		$me = staffInfo($sid); $access=$me["access_level"];
		$perms = getroles(explode(",",$me['roles']));
		$l30 = time()-(86400*30); $trs=""; $no=0;
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($sql as $row){ $staff[$row['id']]=array(prepare(ucwords($row['name'])),$row['contact'],$row['branch']); }
		
		if($db->istable(3,"cashiers$cid")){
			$sql = $db->query(3,"SELECT *FROM `cashiers$cid` ORDER BY `balance` DESC");
			if($sql){
				$trstb = ($db->istable(3,"translogs$cid")) ? 1:0;
				foreach($sql as $row){
					$bal=$row['balance']; $uid=$row['user']; $name=$staff[$uid][0]; $frm="CASH$uid-"; 
					$tot=$col=0; $no++; $coll=$fund=""; $part=strlen($frm)+1;
					
					if($trstb){
						$chk = $db->query(3,"SELECT COUNT(*) AS tot,SUM(SUBSTRING(ref,$part)) AS tsum FROM `translogs$cid` WHERE `ref` LIKE '$frm%' AND `time`>$l30");
						if($chk){ $col=intval($chk[0]['tsum']); $tot=intval($chk[0]['tot']); }
					}
					
					$view = ($tot) ? "<a href='javascript:void(0)' onclick=\"loadpage('account.php?teltrans=$uid')\">View</a>":"";
					if(in_array("reconcile teller cash",$perms)){
						$coll = ($bal>0) ? "<button class='btnn' style='padding:3px;background:#2f4f4f' onclick=\"collectfunds('$uid','$bal')\"><i class='bi-node-minus'></i> Collect</button>":"";
						$fund = "<button class='btnn' style='padding:3px;background:green' onclick=\"popupload('account.php?fundcash=$uid')\"><i class='bi-node-plus'></i> Fund</button>";
					}
					
					$trs.= "<tr valign='top'><td>$no</td><td>$name</td><td>".fnum($tot)." $view</td><td>Ksh ".fnum($col)."</td><td>Ksh ".fnum($bal)."</td>
					<td style='text-align:right'>$coll $fund</td></tr>";
				}
			}
		}
		
		$abtn = (in_array("reconcile teller cash",$perms)) ? "<button class='bts' style='padding:4px;float:right;font-size:15px;' onclick=\"popupload('account.php?fundcash')\">
		<i class='bi-node-plus'></i> Fund Cashier</button>":"";
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Teller Cashiers $abtn</h3>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;margin-top:10px;min-width:500px' cellpadding='7'>
						<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td colspan='2'>Cashier</td><td>Transactions</td><td>Collection</td>
						<td>Balance</td><td></td></tr> $trs
					</table><br>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed Teller cashiers");
	}
	
	# fund wallet
	if(isset($_GET["fundcash"])){
		$uid = intval($_GET["fundcash"]); $opts = "";
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE (`status`='0' OR `id`='$uid')");
		foreach($sql as $row){
			if($row["position"]!="USSD"){
				$rid=$row['id']; $name=prepare(ucwords($row['name']));
				if($uid==$rid){ $opts.= "<option value='$rid' selected>$name</option>"; }
				else{
					$perms=array_map("trim",getroles(explode(",",$row['roles'])));
					if(in_array("receive teller cash",$perms) or in_array("transfer teller cash",$perms)){ $opts.= "<option value='$rid'>$name</option>"; }
				}
			}
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='color:#191970;text-align:center;font-size:23px'>Fund Cashier Account</h3><br>
			<form method='post' id='ffom' onsubmit='fundcash(event)'>
				<p>Select Cashier<br><select style='width:100%' name='cashier' required>$opts</Select><br>
				<span style='color:grey;font-size:13px;font-family:ebrima'><b>Note:</b> Only users with permissions to receive or Transfer cash are listed</span></p>
				<p>Amount to Fund<br><input type='number' name='casham' style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Fund</button></p><br>
			</form>
		</div>";
	}
	
	# teller Transactions
	if(isset($_GET["teltrans"])){
		$uid = trim($_GET["teltrans"]);
		$page = (isset($_GET["pg"])) ? trim($_GET["pg"]):1;
		$l30 = time()-(86400*30); $trs=""; $no=0;
		$lim = getLimit($page,30); $frm="CASH$uid-";
		
		$qri = $db->query(3,"SELECT *FROM `translogs$cid` WHERE (`ref` LIKE '$frm%' OR `ref` LIKE 'GCASH$uid-%') AND `time`>$l30 $lim");
		if($qri){
			foreach($qri as $row){
				$amnt=fnum(intval(explode("-",$row['ref'])[1])); $desc=prepare($row['details']); 
				$css = (substr($row['ref'],0,5)=="GCASH") ? "color:#4682b4":""; $tym=date("d-m-Y, h:i a",$row['time']);
				$trs.= "<tr valign='top' style='$css'><td>$tym</td><td>$desc</td><td>$amnt</td></tr>";
			}
		}
		
		$qry = $db->query(2,"SELECT `name` FROM `org$cid"."_staff` WHERE `id`='$uid'")[0];
		$sql = $db->query(3,"SELECT COUNT(*) AS tot FROM `translogs$cid` WHERE (`ref` LIKE '$frm%' OR `ref` LIKE 'GCASH$uid-%') AND `time`>$l30");
		$totals = ($sql) ? $sql[0]['tot']:0; $name=ucwords(prepare($qry["name"]));
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $name Cash Transactions</h3>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;margin-top:10px;min-width:450px' cellpadding='7'>
						<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td>Date</td><td>Details</td><td>Amount</td></tr> $trs
					</table>
				</div>".getLimitDiv($page,30,$totals,"account.php?teltrans=$uid")."
			</div>
		</div>";
		savelog($sid,"Viewed $name Cash Transactions");
	}
	
	# advances
	if(isset($_GET['advances'])){
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y");
		$me = staffInfo($sid); $atbl="advances$cid"; $tmon=strtotime(date("Y-M"));
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='advance'");
		$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
		$lvs = ($app) ? json_decode($app[0]['levels'],1):[]; $hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[]; 
		$super = (array_key_exists("advance",$hlevs)) ? $hlevs['advance']:[]; $dcd=count($lvs);
		
		$trs=""; $appr=[];
		if($db->istable(3,$atbl)){
			$db->insert(3,"UPDATE `$atbl` SET `status`='15' WHERE `status`<10 AND `year`='$yr' AND NOT `month`='$tmon'");
			$res = $db->query(3,"SELECT *FROM `$atbl` WHERE `staff`='$sid' AND `year`='$yr' ORDER BY time DESC");
			if($res){
				foreach($res as $row){
					$css = "padding:4px;border-radius:4px;color:#fff";
					$amnt=number_format($row['amount']); $resn=nl2br(prepare(ucfirst($row['reason'])));
					$day=date("M d, H:i",$row['time']); $approved=number_format($row['approved']); $rid=$row['id'];
					
					$st=($row['status']>100) ? 20:$row['status']; $icon="<i class='fa fa-circle'></i>";
					$status = array(20=>"<span style='$css;color:green'>$icon Approved</span>",11=>"<span style='$css;color:#8A2BE2'>$icon Unattended</span>",
					15=>"<span style='$css;color:#C71585'>$icon Declined</span>");
					
					foreach($lvs as $key=>$one){
						if(array_key_exists($one,$super) && in_array($me['position'],$lvs)){
							$levels[$key] = ($one==$me['position'] && $row['staff']==$sid) ? $super[$one]:$one;
						}
						else{ $levels[$key]=$one; }
					}
					
					if($dcd){
						foreach($levels as $pos=>$user){
							$status[$pos-1]="<i style='color:grey;font-size:13px'>Waiting ".prepare(ucfirst($user))."</i>";
						}
					}
					
					$edit = ($st==0) ? "<a href='javascript:void()' onclick=\"popupload('accounts/advance.php?apply=$rid')\"><i class='fa fa-pencil'></i> Edit</a>":"";
					$trs.="<tr valign='top'><td>$day</td><td>$amnt</td><td>$resn</td><td>KES $approved</td><td>$status[$st]</td><td>$edit</td></tr>";
				}
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='advanceapplication'"); $app = ($res) ? $res[0]['value']:1;
		$apply = ($app) ? "<button class='bts' onclick=\"popupload('accounts/advance.php?apply')\" style='padding:5px;font-size:14px;float:right'>Apply</button>":"";
		
		echo "<div class='cardv' style='max-width:1000px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;min-width:500px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $yr Salary Advances $apply</h3>
				<table class='table-striped' style='width:100%;font-size:15px;margin-top:10px' cellpadding='7'>
					<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td>Date</td><td>Request</td><td>Reason for Advance</td>
					<td>Accepted</td><td colspan='2'>Status</td></tr> $trs
				</table><br>
			</div>
		</div>";
		savelog($sid,"Viewed personal salary advance record");
	}
	
	# leaves
	if(isset($_GET['leaves'])){
		$lid = trim($_GET['leaves']);
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y");
		$me = staffInfo($sid); $ltbl="org".$cid."_leaves"; 
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='leave'");
		$lvs = ($app) ? json_decode($app[0]['levels'],1):[]; $dls=[];
		foreach($lvs as $key=>$one){
			if(isset($one[$me["position"]])){if($one[$me["position"]]){ $dls[$key]=$one[$me["position"]]; }}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $pozts[$row['id']]=$row['position'];
		}
		
		$dcd=count($dls); $trs=""; $appr=[];
		if($db->istable(2,$ltbl)){
			$cond = ($lid) ? "`id`='$lid'":"`staff`='$sid' AND `year`='$yr'";
			$db->execute(2,"UPDATE `$ltbl` SET `status`='15' WHERE `status`<10 AND `year`='$yr' AND `leave_end`<".time());
			$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY time DESC");
			if($res){
				foreach($res as $row){
					$css = "padding:4px;border-radius:4px;color:#fff";
					$type=clean(ucwords($row['leave_type'])); $resn=nl2br(prepare(ucfirst($row['reason']))); $days=$row['days']; $apps="None";
					$start=date("M d",$row['leave_start']); $end=date("M d",$row['leave_end']); $st=$row['status']; $rid=$row['id'];
					
					$st=($row['status']>400) ? 20:$row['status']; $icon="<i class='fa fa-circle'></i>";
					$status = array(20=>"<span style='$css;color:green'>$icon Running</span>",11=>"<span style='$css;color:#8A2BE2'>$icon Unattended</span>",
					15=>"<span style='$css;color:#C71585'>$icon Declined</span>",400=>"<span style='$css;color:#20B2AA'>$icon Ended</span>");
					
					if($dcd){
						foreach($dls as $pos=>$user){ $status[$pos-1]="<i style='color:grey;font-size:13px'>Waiting ".prepare(ucfirst($user))."</i>"; }
					}
					
					if($row['approvals']){
						$des = json_decode($row['approvals'],1); $apps="";
						if(is_array($des)){
							foreach($des as $by=>$comment){
								$names=explode(" ",$staff[$by]); $name=$names[0]; $name.=(count($names)>1) ? " ".$names[1]:""; 
								$apps.= "<p style='margin:0px'>$name:</b> <i style='font-size:13px'>".nl2br(prepare(ucfirst($comment)))."</i></p>";
							}
						}
					}
					
					$edit = ($st==0) ? "<a href='javascript:void()' onclick=\"popupload('hr/leaves.php?apply=$rid')\"><i class='fa fa-pencil'></i> Edit</a>":"";
					$trs.="<tr valign='top'><td>$start</td><td>$end</td><td>$type</td><td>$resn</td><td>$apps</td><td>$status[$st]</td><td>$edit</td></tr>";
				}
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='leaveapplication'");
		$app = ($res) ? $res[0]['value']:1;
		$apply = ($app) ? "<button class='bts' onclick=\"popupload('hr/leaves.php?apply')\" style='padding:5px;font-size:14px;float:right'>Apply Leave</button>":"";
		$title = ($lid) ? "Leave details":"My $yr Leaves $apply";
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title</h3>
				<div style='overflow:auto;width:100%;'>
					<table class='table-striped' style='width:100%;font-size:15px;margin-top:10px;min-width:600px' cellpadding='7'>
						<tr style='background:#e6e6fa;color:#191970;font-size:14px;font-weight:bold'><td>Leave Start</td><td>Leave End</td><td>Leave Type</td>
						<td>Reason for Leave</td><td>Approvals</td><td colspan='2'>Status</td></tr> $trs
					</table><br>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Viewed personal staff Leave record");
	}
	
	# account
	if(isset($_GET['myacc'])){
		$sql = $db->query(2,"SELECT *FROM `$stbl` WHERE `id`='$sid'");
		$row = $sql[0]; $name=prepare(ucwords($row['name'])); $email=$row['email']; $cont=$row['contact'];
		$def = json_decode($row['config'],1); $pass=decrypt($def['key'],date("YMd-his",$row['time'])); $prof=$def['profile']; 
		$icon = ($row['gender']=="male") ? "male.png":"fem.png";
		$profile = ($prof=="none") ? "$path/assets/img/$icon":"data:image/jpg;base64,".getphoto($prof);
		$exclude = array("email","contact","status","time","roles","config");
		
		$keys=array("email","contact","config"); 
		$inputs = "<input type='hidden' name='sid' value='$sid'> <input type='hidden' name='hasfiles' value=''>";
		foreach($row as $key=>$val){
			if(!in_array($key,$exclude)){
				$val = ($key=="leaves") ? round($val/86400):$val;
				$inputs.="<input type='hidden' value=\"$val\" name='$key'>"; $keys[]=$key;
			}
		}
		
		$posts = (isset($def["mypost"])) ? staffPost($sid):[$row["position"]=>$row["access_level"]];
		$inputs.= "<input type='hidden' name='formkeys' value='".json_encode($keys,1)."'>";
		$switch = (count($posts)>1) ? "<p><button class='btnn' style='width:100%;padding:5px;background:#2f4f4f' onclick=\"popupload('account.php?setsess')\">
		<i class='fa fa-refresh'></i> Switch Account</button></p>":"";
		
		echo "<div>
			<div style='max-width:300px;margin:0 auto'><br>
				<center><label for='prof' style='cursor:pointer'><img src='$profile' height='100'></label><br>
				<i style='font-size:12px;color:grey'>** Click on Image to Update **</i></center><br>
				<form method='post' id='ufom' onsubmit='saveuser(event)'>
					<input type='hidden' name='config' value='".json_encode($def,1)."'>
					$inputs <input type='file' id='prof' style='display:none' accept='image/*' onchange='changeprof(event)'>
					<p>E-mail<br><input style='width:100%' type='email' name='email' value='$email' required></p>
					<p>Contact<br><input style='width:100%' type='number' name='contact' value='0$cont' required></p>
					<p>Password<br><input style='width:85%' type='password' name='upass' id='upass' value='$pass' required>
					<span id='vpass' style='float:right;font-size:26px;cursor:pointer' onclick='togpass()'>
					<i class='fa fa-eye' title='View Password'></i></span></p><br>
					<p style='text-align:right'> <button class='bts' style='float:left;' onclick='logout()' type='reset'><i class='fa fa-power-off'></i> Logout</button>
					<button class='btnn'><i class='fa fa-refresh'></i> Update</button></p>$switch<br>
				</form>
			</div><br>
		</div>";
		
		savelog($sid,"Viewed personal account details");
	}
	
	# set assistant account
	if(isset($_GET['setmine'])){
		$acc = trim($_GET['setmine']);
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'");
		$exc = ($sql) ? $sql[0]['value']:""; $opts=$grps="";
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		if($res){
			foreach($res as $row){
				$opts.="<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>";
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$grp = $row['sgroup']; $gid=$row['id'];
				$grps.=($grp!=$exc) ? "<option value='$gid'>".prepare(ucfirst($grp))."</option>":"";
			}
		}
		
		echo "<div style='max-width:300px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Set Assistant Account</h3><br>
			<form method='post' id='aform' onsubmit='saveassistant(event)'>
				<input type='hidden' name='asid' value='$acc'>
				<p>Which account will you use in this session?<br><select name='assacc' style='width:100%;cursor:pointer' required>$grps</select></p>
				<p>From which branch?<br><select name='asbrn' style='width:100%;cursor:pointer' required>$opts</select></p><br>
				<p style='text-align:right'><button class='btnn'>Proceed</button></p>
			</form>
		</div><br>";
	}
	
	# set session account for multi-account user
	if(isset($_GET["setsess"])){
		$post = staffPost($sid); $lis="";
		$curr = staffInfo($sid)["position"];
		$list = array("hq"=>"Corporate","region"=>"Regional","branch"=>"Branch","portfolio"=>"Your Portfolio");
		
		foreach($post as $grp=>$acc){
			$cnd = ($grp==$curr) ? "checked":"";
			$lis.= "<tr onclick=\"setsess('$grp:$acc')\"><td><input type='radio' name='access' value='$grp' $cnd></td><td style='cursor:pointer'>
			<h3 style='font-size:19px;color:#191970;margin-bottom:5px'>".prepare(ucwords($grp))."</h3>
			<p style='color:grey;margin:0px;font-family:helvetica;font-size:14px'>$list[$acc] Records access Level</p></td></tr>";
		}
		
		echo "<div style='max-width:300px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>Choose Session Account</h3><br>
			<table style='width:100%' cellpadding='7' class='table'>$lis</table>
		</div><br>";
	}
	
	ob_end_flush();
?>

<script>
	
	function changeprof(){
		var img = _("prof").files[0];
		if(img!=null){
			if(confirm("Upload selected Photo?")){
				var formdata=new FormData();
				var xhr=new XMLHttpRequest();
				xhr.upload.addEventListener("progress",profprogress,false);
				xhr.addEventListener("load",profdone,false);
				xhr.addEventListener("error",proferror,false);
				xhr.addEventListener("abort",profabort,false);
				formdata.append("staff","<?php echo $sid; ?>");
				formdata.append("profile",img);
				xhr.onload=function(){
					if(this.responseText.trim()=="success"){
						toast("Changed successfull"); window.location.reload();
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/account.php",true);
				xhr.send(formdata);
			}
		}
	}
		
	function profprogress(event){
		var percent=(event.loaded / event.total) * 100;
		progress("Uploading photo "+Math.round(percent)+"%");
		if(percent==100){
			progress("Cropping...please wait");
		}
	}
		
	function profdone(event){ progress(); }
		
	function proferror(event){
		toast("Upload failed"); progress();
	}
		
	function profabort(event){
		toast("Upload aborted"); progress();
	}
	
	function collectfunds(uid,bal){
		var amnt = prompt("Enter amount to collect",bal);
		if(parseInt(amnt)>bal){ toast("Error: Collection is greater than available balance!"); }
		else{
			if(confirm("Confirm collected Ksh "+amnt+" from Cashier?")){
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:{colfunds:uid,camnt:amnt},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						closepop(); toast("Success!"); loadpage("account.php?tellers");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function approvereq(rid,val,fon){
		var txt = (val==15) ? "Sure to Cancel the request?":"Proceed to approve Request?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/sendsms.php",data:{requestotp:fon},
				beforeSend:function(){progress("Requesting OTP...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().substring(0,7)=="Sent to"){
					setTimeout(function(){
						var otp = prompt("Enter Approval OTP to proceed");
						if(otp){ postreq(rid,val,otp); }
					},500);
				}
				else{ toast(res); }
			});
		}
	}
	
	function postreq(rid,val,otp){
		$.ajax({
			method:"post",url:path()+"dbsave/account.php",data:{approvereq:rid,sval:val,votp:otp},
			beforeSend:function(){ progress("Processing...please wait"); },
			complete:function(){ progress(); }
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim()=="success"){
				toast("Success"); $("#div"+rid).remove();
			}
			else{ alert(res); }
		});
	}
	
	function fundcash(e){
		e.preventDefault();
		if(confirm("Fund Selected Cashier?")){
			var data = $("#ffom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveassistant(e){
		e.preventDefault();
		var data = $("#aform").serialize();
		$.ajax({
			method:"post",url:path()+"dbsave/account.php",data:data,
			beforeSend:function(){ progress("Setting...please wait"); },
			complete:function(){ progress(); }
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim()=="success"){
				closepop(); window.location.reload();
			}
			else{ alert(res); }
		});
	}
	
	function setsess(acc){
		if(confirm("Proceed to set current session as "+acc.split(":")[0]+"?")){
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:{setsesn:acc},
				beforeSend:function(){ progress("Setting...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function saveuser(e){
		e.preventDefault();
		if(confirm("Continue to update your account?")){
			var data=$("#ufom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); window.location.reload();
				}
				toast(res);
			});
		}
	}

</script>