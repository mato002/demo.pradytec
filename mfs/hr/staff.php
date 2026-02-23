<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	function getPosts($post){
		$db = new DBO(); $cid=CLIENT_ID; $posts=[];
		$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($res as $row){
			$id=$row['id']; $cnf=json_decode($row["config"],1);
			if(isset($cnf["mypost"])){
				foreach($cnf["mypost"] as $pos=>$val){
					if($pos==$post && $row["position"]!=$post){ $posts[]="`id`='$id'"; }
				}
			}
		}
		return ($posts) ? "AND (`position`='$post' OR ".implode(" OR ",$posts).")":"AND `position`='$post'";
	}
	
	# workplan
	if(isset($_GET["workplan"])){
		$uid = trim($_GET["workplan"]);
		$day = (isset($_GET["day"])) ? trim($_GET["day"]):date("Y-m-d");
		$title = ($uid==$sid) ? "My Workplan":"Staff Workplans";
		
		if(!$db->istable(2,"workplans$cid")){
			$db->createTbl(2,"workplans$cid",["staff"=>"INT","branch"=>"INT","day"=>"CHAR","target"=>"TEXT","locations"=>"TEXT","achieved"=>"TEXT","clients"=>"TEXT","comments"=>"TEXT","time"=>"INT"]);
		}
		
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		$tmr = date("Y-m-d",time()+86399); $usa=($uid) ? $uid:$sid;
		$cnf = json_decode($me["config"],1); $dy=strtotime($day);
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$show;
		$cond = ($me['access_level']=="portfolio" or $uid) ? " AND `staff`='$usa'":$show;
		$titles = array("repeats"=>"Re-Appraisal","collect"=>"Collection","new"=>"Onboarding","leads"=>"Prospect");
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$data=$trs=""; $all=[];
		$sql = $db->query(2,"SELECT *FROM `workplans$cid` WHERE `day`='$dy' $cond");
		if($sql){
			foreach($sql as $row){
				$stid=$row["staff"]; $name=$staff[$stid]; $targ=json_decode($row["target"],1); $locs=json_decode($row["locations"],1); 
				$clist=json_decode($row["clients"],1); $done=json_decode($row["achieved"],1); $comms=json_decode($row["comments"],1); $all[]=$row["staff"]; 
				$dy=date("h:i a",$row["time"]); $updt=(isset($done["time"])) ? "Updated <b>".date("h:i a",$done["time"])."</b>":""; $trs=$comdt="";
				
				foreach($comms as $src=>$com){
					$usa=explode(":",$src)[0]; $tym=date("d-m-Y,H:i",explode(":",$src)[1]); 
					$css=($usa==$sid) ? "background:#F0F8FF;float:right":"background:#f8f8ff;float:left"; $mssg=prepare(str_replace("~nl~","<br>",$com));
					$comdt.= "<div style='$css;clear:both;margin-top:10px;border:1px solid #ADD8E6;padding:5px;font-family:arial;font-size:14px;max-width:80%'>
						<p style='font-weight:bold;color:#8A2BE2;margin-bottom:8px'>$staff[$usa] <span style='float:right;margin-left:20px'>$tym</span></p><p style='margin:0px'>$mssg</p>
					</div>";
				}
				
				foreach($targ as $col=>$des){
					$tcl = fnum($des["clients"]); $res=(isset($done[$col])) ? $done[$col]["clients"]:0; $rid=$row["id"]; 
					$per = ($tcl>0) ? round(($res/$tcl)*100,2):0; $lis=$cls="";
					foreach($locs[$col] as $loc){ $lis.="<li>".prepare(ucfirst($loc))."</li>"; }
					if(isset($clist[$col])){
						foreach($clist[$col] as $cname){ $cls.="<li>".prepare(ucfirst($cname))."</li>"; }
					}
					
					$edt = ($sid==$row["staff"] && (time()-$row["time"])<3600) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?addplan=$rid')\">
					<i class='bi-pencil-square'></i> Update</a>":"";
					$upd = ($sid==$row["staff"] && (time()-$row["time"])<64000) ? "<a onclick=\"popupload('hr/staff.php?planres=$rid:$col:clients&user=$stid&dy=$day')\" 
					href='javascript:void(0)'><i class='bi-pencil-square' style='font-size:20px' title='Update Achievement'></i></a>":"";
					
					$cls = ($cls) ? $cls:"None";
					$trs.= "<tr valign='top'><td style='font-weight:bold;font-size:14px'>$titles[$col] Clients<br>$edt</td><td>Set at <b>$dy</b><br>$updt</td><td>$tcl</td>
					<td>$lis</td><td>$cls</td><td>".fnum($res)." $upd <span style='color:#fff;background:#8B008B;padding:2px 4px;font-size:13px;float:right'>$per%</span></td></tr>"; 
				}
				
				$comdt = ($comdt) ? $comdt:"No posted comments"; 
				$addcom = (in_array("post staff interaction",$perms) or $sid==$row["staff"]) ? "<a onclick=\"popupload('hr/staff.php?plancom=$rid&user=$stid&dy=$day')\" 
				style='float:right;font-weight:normal' href='javascript:void(0)'><i class='bi-plus-circle'></i> Post Comment</a>":"";
				
				$data.= "<h3 style='font-weight:bold;padding:8px;font-size:16px;background:#f0f0f0;margin-bottom:10px;color:#191970'>
				<span style='cursor:pointer;'><i class='bi-person'></i> $name</span> <i class='bi-chevron-double-down' style='float:right;cursor:pointer'></i></h3>
				<div style='padding:5px;background:;margin-bottom:10px;margin-top:-10px;overflow:auto'>
					<table style='width:100%;min-width:550px;background:#fff;font-size:15px' cellpadding='5' class='table-bordered'>
						<caption style='color:#111'><p style='font-weight:bold;background:#e6e6fa;padding:4px 6px;margin-bottom:7px'>Comments $addcom</p> 
						<div style='max-height:500px;overflow:auto'>$comdt</div></caption>
						<tr style='font-weight:bold;color:#191970;background:#e6e6fa'><td>Visitation</td><td style='width:160px'>Time</td><td>Target</td><td>Locations</td>
						<td>Clients Visited</td><td>Achieved</td></tr>$trs
					</table>
				</div>";
			}
		}
		else{
			foreach($titles as $col=>$txt){ $trs.= "<tr valign='top'><td style='font-weight:bold;font-size:14px'>$txt Clients</td><td>0</td><td>None</td><td>None</td><td>0</td></tr>"; }
			$data = "<h3 style='font-weight:bold;padding:8px;font-size:18px;background:#f0f0f0;margin-bottom:10px;color:#7B68EE'>
			<span style='cursor:pointer;'><i class='bi-person'></i> $staff[$sid]</span><i class='bi-chevron-double-down' style='float:right;cursor:pointer'></i></h3>
			<div style='padding:5px;background:;margin-bottom:10px;margin-top:-10px;overflow:auto'>
				<table style='width:100%;min-width:500px;background:#fff' cellpadding='5' class='table-bordered'>
					<caption style='color:#111'><p style='font-weight:bold;background:#e6e6fa;padding:4px 6px;margin-bottom:7px'>Comments</p><p>No Comments</p></caption>
					<tr style='font-weight:bold;color:#191970;background:#e6e6fa'><td>Visitation</td><td>Target</td><td>Locations</td><td>Clients Visited</td><td>Achieved</td></tr>$trs
				</table>
			</div>";
		}
	
		$add = (!in_array($sid,$all)) ? "<button style='float:right;padding:4px 8px' onclick=\"popupload('hr/staff.php?addplan')\" class='bts'><i class='fa fa-plus'></i> Create</button>":"";
		echo "<div class='cardv' style='max-width:1200px;min-height:300px;padding:15px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>$backbtn $title</h3><hr style='margin-bottom:10px;'>
			<p>$add <input type='date' style='width:180px;padding:5px;font-size:15px' max='$tmr' value='$day' onchange=\"loadpage('hr/staff.php?workplan=$uid&day='+this.value)\"></p>
			<div style='width:100%;overflow:auto' id='plandv'>$data</div>
		</div> <script> $('#plandv').accordion({heightStyle: 'content'}); </script>";
	}
	
	# post plan results
	if(isset($_GET["planres"])){
		$rid = trim($_GET["planres"]);
		$uid = trim($_GET["user"]);
		$day = trim($_GET["dy"]);
		
		echo "<div style='max-width:400px;margin:0 auto;padding:10px'>
			<h3 style='font-size:20px;text-align:center'>Post Workplan Achievements</h3><br>
			<form method='post' id='prfom' onsubmit=\"saveplanres(event,'$uid','$day')\">
				<input type='hidden' name='setachieved' value='$rid'>
				<p>Name of Clients Visited (Separate by comma)<br><textarea name='pclients' class='mssg' required></textarea><br>
				<span style='font-size:13px;color:grey'>Type <b>None</b> For no clients visited</span></p><br>
				<p style='text-align:right'><button class='btnn'><i class='bi-upload'></i> Post</button></p>
			</form><br>
		</div>";
	}
	
	# post plan comment
	if(isset($_GET["plancom"])){
		$rid = trim($_GET["plancom"]);
		$uid = trim($_GET["user"]);
		$day = trim($_GET["dy"]);
		
		echo "<div style='max-width:400px;margin:0 auto;padding:10px'>
			<h3 style='font-size:20px;text-align:center'>Post Workplan Comment</h3><br>
			<form method='post' id='cmfom' onsubmit=\"savepcomm(event,'$uid','$day')\">
				<input type='hidden' name='pcomid' value='$rid'>
				<p>Comment<br><textarea name='pcom' class='mssg' required></textarea></p><br>
				<p style='text-align:right'><button class='btnn'><i class='bi-upload'></i> Post</button></p>
			</form><br>
		</div>";
	}
	
	# create plan
	if(isset($_GET["addplan"])){
		$pid = trim($_GET["addplan"]);
		$dy = date("Y-m-d"); $max=date("Y-m-d",time()+86399); 
		$titles = array("repeats"=>"Re-Appraisal","collect"=>"Collection","new"=>"Onboarding","leads"=>"Prospect");
		$targ=$locs=[]; $trs="";
		
		if($pid){
			$sql = $db->query(2,"SELECT *FROM `workplans$cid` WHERE `id`='$pid'");
			$row = $sql[0]; $targ=json_decode($row["target"],1); $locs=json_decode($row["locations"],1); $dy=date("Y-m-d",$row["day"]);
		}
		
		foreach($titles as $col=>$txt){
			$tcl = (isset($targ[$col])) ? $targ[$col]["clients"]:"";
			$lis = (isset($locs[$col])) ? implode(", ",$locs[$col]):"";
			$trs.= "<tr valign='top'><td colspan='2' style='background:#f0f0f0'><b>$txt Clients</b></td></tr>
			<tr valign='top'><td>No of Clients<br><input type='number' min='0' name='plan[$col][clients]' value='$tcl' style='width:100%' required></td>
			<td style='width:65%'>Visiting Locations<br><textarea class='mssg' placeholder='Separate with comma(,)' name='plan[$col][locs]' required>$lis</textarea></td></tr>";
		}
		
		echo "<div style='max-width:450px;margin:0 auto;padding:10px'>
			<h3 style='font-size:21px;text-align:center'>Daily Workplan Setup</h3><br>
			<form method='post' id='plfom' onsubmit=\"saveplan(event,'$sid')\">
				<input type='hidden' name='planid' value='$pid'>
				<table style='width:100%' cellpadding='5'> $trs </table><br>
				<p>Plan Date<br><input type='date' style='width:180px' value='$dy' max='$max' name='plandate' id='pday' required>
				<button class='btnn' style='float:right'><i class='bi-save'></i> Save</button></p>
			</form><br>
		</div>";
	}
	
	# staff groups
	if(isset($_GET['groups'])){
		$trs=""; $no=0;
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' ORDER BY `sgroup` ASC");
		if($res){
			foreach($res as $row){
				$gname=prepare(ucfirst($row['sgroup'])); $grup=$row['sgroup']; $no++; $id=$row['id'];
				$res1 = $db->query(2,"SELECT COUNT(*) AS total FROM `org$cid"."_staff` WHERE `status`='0' ".getPosts($grup)); 
				$nst = ($res1) ? fnum($res1[0]['total']):0; $nc=count(explode(",",$row['roles'])); $nme=str_replace("'","`",$gname);
				$edit = (in_array("edit staff groups",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?mkgrp=$id')\"><i class='fa fa-pencil'></i> Edit</a>":"N/A";
				$access = (in_array("re-assign permissions",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?mkgrp=$id&gperm')\">$nc Roles</a>":"$nc Roles";
				$trs.= "<tr><td>$no</td><td>$gname</td><td>$nst</td><td>$access</td><td>$edit</td></tr>";
			}
		}
		
		echo "<div class='cardv' style='max-width:700px;min-height:300px;padding:15px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>Staff Groups
			<button style='font-size:14px;float:right' onclick=\"popupload('hr/staff.php?mkgrp')\" class='bts'><i class='fa fa-plus'></i> Group</button></h3><br>
			<table class='table-striped' style='width:100%;min-width:300px' cellpadding='7'>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA;font-size:14px'><td colspan='2'>Group</td><td>Total Staff</td><td>Permissions</td>
				<td>Action</td></tr> $trs
			</table>
		</div>";
		savelog($sid,"Viewed Staff Groups");
	}
	
	# add/edit group
	if(isset($_GET['mkgrp'])){
		$gid = trim($_GET['mkgrp']);
		$pms = (isset($_GET['gperm'])) ? 1:0;
		$mfi = mficlient("syszn$cid");
		
		$sql = $db->query(1,"SELECT *FROM `staff_groups` WHERE `id`='$gid'");
		$roles = ($sql) ? explode(",",$sql[0]['roles']):[]; $name=($sql) ? prepare($sql[0]['sgroup']):"";
		$title = ($gid) ? "Edit $name Group":"Create Staff Group";
		
		$res = $db->query(1,"SELECT DISTINCT `groups` FROM `useroles` ORDER BY `groups` ASC"); 
		foreach($res as $rw){
			$sql = $db->query(1,"SELECT *FROM `useroles` WHERE `groups`='".$rw['groups']."'");
			foreach($sql as $row){
				if(in_array($row["cluster"],$mfi)){
					$cond = (in_array($row['id'],$roles)) ? "checked":""; $perm=ucfirst($row['role']); $val=$row['id'];
					$trs[]="<li style='padding:5px;list-style:none'><input type='checkbox' name='roles[]' value='$val' $cond> &nbsp; $perm</checkbox></li>";
				}
			}
		}
		
		$half = ceil(count($trs)/2); $chunk = array_chunk($trs,$half); $tp = ($pms) ? 2:0; $tp=($gid && !$pms) ? 1:$tp;
		$lis = "<tr valign='top'><td>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>";
		$gname = ($pms) ? "<input type='hidden' name='gname' value='$name'>":"<p>Group Name<br><input type='text' name='gname' value=\"$name\" style='max-width:100%' required></p>";
		
		echo "<div style='max-width:500px;padding:10px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>$title</h3>
			<form method='post' id='pfom' onsubmit=\"savegroles(event,'$tp')\">
				<input type='hidden' name='userole' value='$gid'>
				<table style='width:100%' cellpadding='5'> 
					<caption style='caption-side:top'>$gname</caption> $lis 
					<tr><td></td><td style='text-align:center'><br><button class='btnn'>Update</button></td></tr>
				</table><br>
			</form>
		</div>";
	}
	
	# staff permissions
	if(isset($_GET['uroles'])){
		$uid = trim($_GET['uroles']);
		$des = staffInfo($uid); $mfi=mficlient("syszn$cid");
		$roles = explode(",",$des['roles']);
		
		$res = $db->query(1,"SELECT DISTINCT `groups` FROM `useroles` ORDER BY `groups` ASC");  
		foreach($res as $rw){
			$sql = $db->query(1,"SELECT *FROM `useroles` WHERE `groups`='".$rw['groups']."'");
			foreach($sql as $row){
				if(in_array($row["cluster"],$mfi)){
					$cond = (in_array($row['id'],$roles)) ? "checked":""; $perm=ucfirst($row['role']); $val=$row['id'];
					$trs[]= "<li style='padding:5px;list-style:none'><input type='checkbox' name='uroles[]' value='$val' $cond> &nbsp; $perm</checkbox></li>";
				}
			}
		}
		
		$half = ceil(count($trs)/2); $chunk=array_chunk($trs,$half);
		$lis = "<tr valign='top'><td>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>";
		
		echo "<div style='max-width:500px;padding:10px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Staff Access Permissions</h3><br>
			<form method='post' id='pform' onsubmit='saveperms(event)'>
				<input type='hidden' name='usar' value='$uid'>
				<table style='width:100%' cellpadding='5'> $lis 
					<tr><td></td><td style='text-align:center'><br><button class='btnn'>Update</button></td></tr>
				</table><br>
			</form>
		</div>";
	}
	
	# staff action
	if(isset($_GET['uaction'])){
		$uid = trim($_GET['uaction']);
		$user = staffInfo($uid); $me=staffInfo($sid);
		$perms = getroles(explode(",",$me['roles'])); 
		$cnf = json_decode($user['config'],1); $trs=$hr="";
		$pass = decrypt($cnf['key'],date("YMd-his",$user['time']));
		
		if(in_array("manage payroll",$perms) && !in_array($user['position'],["assistant","collection agent","USSD"])){
			$des = $cnf['payroll']; $emp=(isset($des["etype"])) ? $des["etype"]:"normal"; $hr="<hr>";
			$py1 = ($des['include']) ? "checked":""; $py2 = ($des['include']==0) ? "checked":"";
			$pe1 = ($des['paye']) ? "checked":""; $pe2 = ($des['paye']==0) ? "checked":"";
			$ep1 = ($emp=="consultant") ? "checked":""; $ep2=($emp=="normal") ? "checked":"";
		
			$trs = "<tr><td colspan='2'>Payroll Inclusion</td></tr>
			<tr><td><input type='radio' $py1 name='payroll' value='1' onchange=\"payroll('$uid','include:1')\"> &nbsp; Include</radio></td>
			<td><input type='radio' $py2 name='payroll' value='0' onchange=\"payroll('$uid','include:0')\"> &nbsp; Exclude</radio></td></tr>
			<tr><td colspan='2'><hr>Statutory Deductions</td></tr>
			<tr><td><input type='radio' $pe1 name='paye' value='1' onchange=\"payroll('$uid','paye:1')\"> &nbsp; Deduct Statutory</radio></td>
			<td><input type='radio' $pe2 name='paye' value='0' onchange=\"payroll('$uid','paye:0')\"> &nbsp; Retain Statutory</radio></td></tr>
			<tr><td colspan='2'><hr>Employment Category</td></tr>
			<tr><td><input type='radio' $ep1 name='emptype' value='consultant' onchange=\"payroll('$uid','etype:consultant')\"> &nbsp; Consultant</radio></td>
			<td><input type='radio' $ep2 name='emptype' value='normal' onchange=\"payroll('$uid','etype:normal')\"> &nbsp; Non Consultant</radio></td></tr>";
		}
		
		if(in_array("modify staff password",$perms)){
			$trs .= "<tr><td colspan='2'>$hr<p>New Staff Password<br><input type='password' value='$pass' id='pass' style='width:100%'></p>
			<p>Confirm Password<br><input type='password' id='pass2' style='width:100%'></p>
			<p><button class='btnn' style='padding:6px;float:right;margin-top:17px' onclick=\"changepass('$uid')\">Update</button></p></td></tr>";
		}
		
		echo "<div style='max-width:350px;padding:10px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Staff Actions</h3><br>
			<table style='width:100%' cellpadding='5'> $trs</table><br>
		</div><br>";
	}
	
	# add/edit staff
	if(isset($_GET['add'])){
		$stid = trim($_GET['add']);
		$stbl="org".$cid."_staff";
		$me = staffInfo($sid); 
		$access=$me['access_level']; 
		$tym=time(); $lis=$infs="";
		
		$exclude = array("id","config","status","time","roles","leaves");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$stbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","email"=>"email","gender"=>"text","jobno"=>"text","branch"=>"number","roles"=>"textarea",
		"access_level"=>"text","config"=>"text","position"=>"text","entry_date"=>"date","leaves"=>"number","status"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; $fields['config']="text";
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[]; 
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		$cnf = array("key"=>encrypt(date("M")."@".rand(1234,5555),date("YMd-his",$tym)),"region"=>0,"lastreset"=>0,"profile"=>"none","payroll"=>["include"=>1,"paye"=>1]);
		
		$defv = array("config"=>json_encode($cnf,1),"status"=>0,"time"=>$tym);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(isset($defv[$fld])) ? $defv[$fld]:""; 
		}
	
		if($stid){
			$row = $db->query(2,"SELECT *FROM `$stbl` WHERE `id`='$stid'"); $dvals=$row[0]; $dvals['otp']=0;
			if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
				foreach($fields as $col=>$dtp){ 
					if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($fields[$col]); }
				}
			}
		}
		
		$cols = $db->tableFields(2,$stbl); $ftps=[];
		$mreg=json_decode($dvals['config'],1)['region'];  
		foreach($fields as $col=>$dts){
			if(!in_array($col,$cols)){ unset($fields[$col]); }
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname = ucwords(str_replace("_"," ",$field));
				if($field=="access_level"){
					$disp = ($mreg) ? "block":"none"; $opts=$rls="";
					$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid'"); 
					if($sql){
						$arr = array("hq"=>"Head Office","region"=>"Regional Level","branch"=>"Branch Level","portfolio"=>"Portfolio Level");
						foreach($sql as $row){
							$rid=$row['id']; $cnd = ($rid==$mreg) ? "selected":"";
							$rls.="<option value='$rid' $cnd>".prepare(ucwords($row['name']))."</option>";
						}
					}
					else{ $arr = array("hq"=>"Head Office","branch"=>"Branch Level","portfolio"=>"Portfolio Level"); }
					
					foreach($arr as $key=>$val){
						$cnd = ($key==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$key' $cnd>$val</option>";
					}
			
					$lis.= "<p>System Access Level<br><select name='$field' style='width:100%' onchange=\"setregion(this.value)\">$opts</select></p>
					<p style='display:$disp;' id='rinp'>Operational Region<br><select style='width:100%' name='region'>$rls</select></p>";
				}
				elseif($field=="jobno"){
					$jbno = (!$stid or !$dvals[$field]) ? getJobno():$dvals[$field];
					$lvd = ($dvals['leaves']) ? round($dvals['leaves']/86400):0;
					$lis.="<p>Job Number <span style='float:right'>Leave Days</span><br> <input type='text' name='jobno' value='$jbno' style='width:50%' required>
					<input type='text' name='leaves' value='$lvd' style='width:40%;float:right' required></p>";
				}
				elseif($field=="branch"){
					$mnf = json_decode($me["config"],1);
					$cond = ($me['access_level']=="hq") ? "":"AND `id`='".$me['branch']."'";
					$cond = ($me["access_level"]=="region" && isset($mnf["region"])) ? "AND ".setRegion($mnf["region"],"id"):$cond;
					$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' $cond AND `status`='0'"); $opts="";
					if($res){
						foreach($res as $row){
							$bid=$row['id']; $cnd = ($bid==$dvals[$field]) ? "selected":"";
							$opts.="<option value='$bid' $cnd>".prepare(ucwords($row['branch']))."</option>";
						}
					}
					
					$opts=($opts=="") ? "<option value='0'>Head Office</option>":$opts;
					$lis.="<p>Hosted Branch<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($field=="gender"){
					$opts ="";
					foreach(array("male","female") as $opt){
						$cnd = ($opt==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$opt' $cnd>".ucwords($opt)."</option>";
					}
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($field=="entry_date"){
					$leo = date('Y-m-d'); $val=($dvals[$field]) ? $dvals['entry_date']:$leo;
					$lis.="<p>Entry Date<br><input type='date' style='width:100%' name='$field' max='$leo' value='$val' required></p>";
				}
				elseif($field=="position"){
					$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
					if($dvals[$field]=="super user"){ $opts = "<option value='super user'>Super User</option>"; }
					else{
						$cnd = ($dvals[$field]=="collection agent") ? "selected":"";
						$opts = "<option value='assistant'>Assistant</option><option value='collection agent' $cnd>Collection Agent</option>";
						foreach($res as $row){
							$cnd = ($row['sgroup']==$dvals[$field]) ? "selected":"";
							$opts.="<option value='".$row['sgroup']."' $cnd>".prepare(ucfirst($row['sgroup']))."</option>";
						}
					}
					
					$lis.="<p>Staff Group<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
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
					$inp=(array_key_exists($dtype,$accept))? "file":$dtype; $add=($inp=="file") ? $accept[$dtype]:""; 
					$val=prepare(ucfirst($dvals[$field]));
					if($inp=="file"){ $infs.="$field:"; $ftps[$field]=$dtype; }
					$lis.="<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" accept='$add' id='$field' name='$field' required></p>";
				}
			}
			else{ $lis.=($field!="leaves") ? "<input type='hidden' name='$field' value='$dvals[$field]'>":""; }
		}
		
		$title = ($stid) ? "Edit Staff Info":"Add New Staff";
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='sform' onsubmit=\"savestaff(event)\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($fields),1)."'>
				<input type='hidden' name='id' value='$stid'> <input type='hidden' name='sid' value='$sid'>
				<input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form>
		</div>";
	}
	
	# manage staff
	if(isset($_GET['manage'])){
		$vtp = trim($_GET['manage']);
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean(strtolower($_GET['str'])):null;
		$grp = (isset($_GET['grp'])) ? clean($_GET['grp']):"";
		$bran = (isset($_GET['bran'])) ? clean(strtolower($_GET['bran'])):0;
		
		$stbl="org".$cid."_staff";
		$lim = getLimit($page,20);
		$me = staffInfo($sid); 
		$cnf = json_decode($me["config"],1); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		
		$exclude = array("id","config","time","contact","gender","name","status","roles");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$stbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","email"=>"email","gender"=>"text","jobno"=>"text","branch"=>"number","roles"=>"textarea",
		"access_level"=>"text","config"=>"text","position"=>"text","entry_date"=>"date","leaves"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $fields = array_keys($ftc);
		
		$brans=$docs=[];
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); 
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$show;
		$cond = ($me['access_level']=="portfolio") ? "NOT `position`='USSD' AND `id`='$sid'":"NOT `position`='USSD' $show";
		$cond.= ($vtp) ? " AND `status`='$vtp'":" AND `status`='0'";
		$cond.= ($grp) ? " ".getPosts($grp):"";
		$cond.= ($str) ? " AND (`name` LIKE '%$str%' OR `contact` LIKE '%$str%' OR `email` LIKE '%$str%')":"";
		$colors = array("#008fff","#4682b4","#BA55D3","#9370DB","#3CB371","#C71585","#DA70D6","#663399","#008080","#FF6347","#8FBC8F","#2F4F4F","#008fff");
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='org".$cid."_staff'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){ 
					if($dtp=="image"){
						$res = $db->query(2,"SELECT $col,id FROM `org".$cid."_staff` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = ($df) ? "data:image/jpg;base64,".getphoto($df):"$path/assets/img/user.png";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;cursor:pointer;' onclick=\"popupload('media.php?doc=img:$df&tbl=$stbl:$col&fd=id:$id')\">";
							}
						}
					}
					if($dtp=="pdf" or $dtp=="docx"){
						$res = $db->query(2,"SELECT $col,id FROM `org".$cid."_staff` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;cursor:pointer' onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$stbl:$col&fd=id:$id')\">";
							}
						}
					}
				}
			}
		}
		
		$cols = $db->tableFields(2,$stbl); $no=1; $trs=$ths=$brns=$opts="";
		foreach($fields as $col){
			if(!in_array($col,$cols)){ unset($fields[array_search($col,$fields)]); }
		}
	
		$res = $db->query(2,"SELECT *FROM `$stbl` WHERE $cond ORDER BY name,status ASC $lim");
		if($res){
			foreach($res as $row){
				$color=$colors[rand(0,12)]; $no++; $def=json_decode($row['config'],1); $pic=$def['profile']; $rid=$row['id']; $name=prepare(ucwords($row['name'])); $tds=$pls="";
				$prof = ($pic=="none") ? substr(ucfirst($row['name']),0,1):"<img src='data:image/jpg;base64,".getphoto($pic)."' style='height:40px;max-width:40px;border-radius:50%'>";
				$states = array(0=>"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",1=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>",
				2=>"<span style='color:purple;'><i class='fa fa-circle'></i> Inactive</span>");
				
				foreach($fields as $key=>$col){
					if(!in_array($col,$exclude)){
						$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
						$val = ($col=="email") ? $row['email']."<br>".$cont="0".$row['contact']:$val; $roles=count(explode(",",$row[$col]));
						$val = ($col=="leaves") ? round($row['leaves']/86400)." days":$val;
						$val = (isset($docs[$col.$row['id']])) ? $docs[$col.$row['id']]:$val;
						$th = ($col=="email") ? "Contact Info":ucfirst(str_replace("_"," ",$col));
						if($col!="position"){ $tds.= "<td>$val</td>"; $ths.=($no==2) ? "<td>$th</td>":""; }
					}
				}
				
				$cnf = json_decode($row['config'],1); $post=(isset($cnf["mypost"])) ? staffPost($rid):[$row["position"]=>$row["access_level"]];
				foreach($post as $key=>$val){ $pls.="<li>".prepare(ucwords($key))."</li>"; }
				
				$click = (in_array("view staff info",$perms)) ? "loadpage('hr/staff.php?vstid=$rid')":"toast('Unauthorised access!')";
				$trs.= "<tr style='cursor:pointer' valign='top' onclick=\"$click\"><td><div style='height:40px;width:40px;background:$color;font-weight:bold;
				text-align:center;line-height:35px;border-radius:50%;color:#fff;font-size:23px;'>$prof</div></td><td>$name<br>".$states[$row['status']]."</td><td>$pls</td>$tds</tr>";
			}
		}
		else{
			foreach($fields as $key=>$col){
				if(!in_array($col,$exclude)){
					$th =($col=="email") ? "Contact Info":ucfirst(str_replace("_"," ",$col));
					$ths.="<td>$th</td>";
				}
			}
		}
		
		$grups = "<option value=''>-- Staff Group --</option>";
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' ORDER BY `sgroup` ASC");
		if($res){
			$res[]=array("sgroup"=>"assistant"); $res[]=array("sgroup"=>"collection agent");
			foreach($res as $row){
				$val=prepare($row['sgroup']); $cnd=($grp==$val) ? "selected":"";
				$grups.="<option value='$val' $cnd>".ucwords($val)."</option>";
			}
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$cond2 = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $cond2");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$grpi = str_replace(" ","+",$grp);
			$brns = "<select style='width:150px' onchange=\"loadpage('hr/staff.php?manage=$vtp&grp=$grpi&bran='+this.value.trim())\">$brn</select>";
		}
		
		foreach(array("Active","Suspended","Inactive") as $key=>$one){
			$cnd = ($vtp==$key) ? "selected":"";
			$opts.="<option value='$key' $cnd>$one</option>";
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS tsum FROM `$stbl` WHERE $cond");
		$totals = intval($sql[0]['tsum']);
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Company Employees</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-hover' style='width:100%;font-size:14px;min-width:800px' cellpadding='5'>
						<caption style='caption-side:top'>
							<input type='search' onsearch=\"loadpage('hr/staff.php?manage&str='+this.value.trim().split(' ').join('+'))\" value='".prepare($str)."'
							style='float:right;width:180px;padding:4px;font-size:15px' placeholder='&#xf002; Search'>
							<select style='width:120px' onchange=\"loadpage('hr/staff.php?manage='+this.value)\">$opts</select>
							<select style='width:160px' onchange=\"loadpage('hr/staff.php?manage=$vtp&grp='+cleanstr(this.value))\">$grups</select> $brns
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;' valign='top'><td colspan='2'>Name</td><td>Position</td>$ths</tr> $trs
					</table>
				</div>".getLimitDiv($page,20,$totals,"hr/staff.php?manage=$vtp&grp=".urlencode($grp)."&str=".urlencode($str))."
			</div>
		</div>";
		
		savelog($sid,"Viewed Company Employees");
	}
	
	# staff account
	if(isset($_GET['vstid'])){
		$uid = trim($_GET['vstid']);
		$tbl = "org$cid"."_staff";
		$me = staffInfo($sid); $yr=date('Y');
		$vtp = (isset($_GET["vtp"])) ? trim($_GET["vtp"]):"";
		$tyr = (isset($_GET["yr"])) ? trim($_GET["yr"]):$yr;
		$perms = getroles(explode(",",$me['roles'])); 
		$cnf = json_decode($me["config"],1);
		$isstb = ($db->istable(2,"staff_loans$cid")) ? 1:0;
		$wid = createWallet("staff",$uid);
		
		$exclude = array("id","config","time","status","name");
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		foreach($res as $row){
			$brans[$row['id']]=prepare(ucwords($row['branch']));
		}
		
		if(!$db->istable(2,"staff_notes$cid")){
			$db->createTbl(2,"staff_notes$cid",["sender"=>"INT","staff"=>"INT","subject"=>"CHAR","notes"=>"TEXT","repto"=>"TEXT","status"=>"INT","time"=>"INT"]);
		}
		
		if(!$vtp){
			$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `staff_notes$cid` WHERE `staff`='$sid' AND `status`='0'");
			if($chk){
				if($chk[0]['tot']){ $vtp="nts"; }
			}
			if(!$vtp){
				if($isstb){
					$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `staff_loans$cid` WHERE `stid`='$uid'");
					if($chk[0]['tot']){ $vtp="lns"; }
				}
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","email"=>"email","gender"=>"text","jobno"=>"text","branch"=>"number","roles"=>"text",
		"access_level"=>"text","config"=>"textarea","position"=>"text","entry_date"=>"date","leaves"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $row=staffInfo($uid); $cnf=json_decode($row['config'],1);
		$align = ($_GET['md']<600) ? "text-align:center":""; $upost=(isset($cnf["mypost"])) ? staffPost($uid):[$row["position"]=>$row["access_level"]];
		
		$icon = ($row['gender']=="male") ? "male.png":"fem.png";
		$prof = ($cnf['profile']=="none") ? "$path/assets/img/$icon":"data:image/jpg;base64,".getphoto($cnf['profile']);
		$sqr = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='staff'"); 
		$wbal = ($sqr) ? $sqr[0]['balance']+$sqr[0]["investbal"]+$sqr[0]["savings"]:0;
				
		$doc = "<div class='col-12 col-sm-6 col-md-6 col-xl-2 col-lg-4 mt-2 mb-2'>
		<div style='padding:7px;min-height:80px;background:#154eb7;color:#7FFF00;font-family:signika negative'>
			<p style='color:#fff;font-size:13px;margin-bottom:10px;'>ACC BALANCES (KSH)</p>
			<h3 style='font-size:23px'>".fnum($wbal)." <button class='bts' onclick=\"loadpage('hr/staff.php?wallet=$uid')\" style='color:blue;font-size:14px;float:right;border:0px;
			outline:none;padding:4px 8px;border-radius:30px;background:#B0E0E6'><i class='bi-box-arrow-up-right'></i> View</button></h3>
		</div></div><div class='col-6 col-sm-4 col-md-3 col-lg-2 mt-2' style='$align;'>
		<img src='$prof' style='height:60px;cursor:pointer;max-width:100%'><br><span style='font-size:13px'>Profile</span></div>";
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){
				if($dtp=="image"){
					unset($ftc[$col]); 
					$res = $db->query(2,"SELECT $col FROM `$tbl` WHERE `id`='$uid'"); $df=$res[0][$col];
					$img = ($res[0][$col]) ? "data:image/jpg;base64,".getphoto($df):"$path/assets/img/user.png";
					$doc .= "<div class='col-6 col-sm-4 col-md-3 col-lg-2 mt-2' style='$align;'>
					<img src='$img' style='height:60px;cursor:pointer;max-width:100%' onclick=\"popupload('media.php?doc=img:$df&tbl=$tbl:$col&fd=id:$uid')\"><br>
					<span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span></div>";
				}
				if($dtp=="pdf" or $dtp=="docx"){
					unset($ftc[$col]); 
					$res = $db->query(2,"SELECT $col FROM `org".$cid."_clients` WHERE `id`='$uid'"); $df=$res[0][$col];
					$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
					$doc .= "<div class='col-6 col-sm-4 col-md-3 col-lg-2 mt-2' style='$align;'>
					<img src='$img' style='height:68px;cursor:pointer' onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$tbl:$col&fd=id:$uid')\"><br>
					<span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span></div>";
				}
			}
		}
		
		$cname=prepare(ucwords($row['name'])); $cst=$row['status']; $post=$row['position']; $ltrs=[]; $susp=$pint=$ldiv="";
		$zns = (isset($cnf["zones"])) ? $cnf["zones"]:[]; $ztx=(count($zns)==1) ? "1 Branch":count($zns)." Branches";
		$zones = (in_array("edit staff",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?colz=$uid')\">$ztx</a>":$ztx;
		$trs = (isset($upost["collection agent"])) ? "<tr valign='top'><td style='color:#191970'><b>Assigned Zones</b></td><td>$zones<td></tr>":"";
		
		foreach($ftc as $col=>$dtp){
			if(!in_array($col,$exclude)){
				$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
				$val = ($col=="leaves") ? round($row['leaves']/86400)." days":$val;
				$val = ($col=="email") ? implode(" @",explode("@",$row['email'])):$val; $ped="";
				$val = (strlen(prepare($row[$col]))>45) ? substr(prepare($row[$col]),0,45)."...<a href=\"javascript:$('#$col').html('$val')\">View</a>":$val;
				if($col=="roles"){
					$roles = count(explode(",",$row['roles']));
					$val = (in_array("re-assign permissions",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?uroles=$uid')\">$roles View</a>":"$roles Roles";
				}
				
				$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
				if($col=="position"){
					$post = (isset($cnf["mypost"])) ? staffPost($uid):[$row["position"]=>$row['access_level']]; $val="";
					if(count($post)>1){ foreach($post as $k=>$v){ $val.="<li>".ucwords($k)."</li>"; }}else{ $val=ucwords($row['position']); }
					$ped = (in_array("edit staff",$perms)) ? "<br><a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?setpos=$uid')\"><i class='fa fa-pencil'></i> Update</a>":"";
				}
				
				$trs.= "<tr valign='top'><td style='color:#191970'><b>".ucfirst(str_replace("_"," ",$col))."</b> $ped</td><td id='$col'>$val</td></tr>";
			}
		}
		
		if($vtp=="lvp"){
			$title = "$tyr Leaves & Payslips";
			if($sid==$uid or in_array("view staff leaves",$perms)){
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_leaves` WHERE `staff`='$uid' AND `status`>=400 AND `year`='$tyr' ORDER BY `time` DESC");
				if($qri){
					foreach($qri as $roq){
						$type=clean(ucwords($roq['leave_type'])); $resn=nl2br(prepare(ucfirst($roq['reason']))); $rid=$roq['id'];
						$start=date("M d",$roq['leave_start']); $end=date("M d",$roq['leave_end']); $tym=$row['time'];
						
						$st=($roq['status']>400) ? 20:$roq['status']; $icon="<i class='fa fa-circle'></i>";
						$status = array(20=>"<span style='color:green'>$icon Running</span>",11=>"<span style='color:#8A2BE2'>$icon Unattended</span>",
						15=>"<span style='color:#C71585'>$icon Declined</span>",400=>"<span style='color:#20B2AA'>$icon Ended</span>");
						
						$ltrs[$tym] = "<tr valign='top'><td>$start - $end</td><td>$type</td><td>$resn</td><td>$status[$st]</td><td><a href='javascript:void(0)'
						onclick=\"loadpage('account.php?leaves=$rid')\"><i class='bi-eye'></i> View</a></td></tr>";
					}
				}
			}
			
			if($sid==$uid or in_array("manage payroll",$perms)){
				$qri = $db->query(3,"SELECT *FROM `payslips$cid` WHERE `year`='$tyr' AND `staff`='$uid' ORDER BY `month` DESC");
				if($qri){
					foreach($qri as $rw){
						$bnk=prepare(ucfirst($rw['bank'])); $bran=prepare(ucfirst($rw['branch'])); $acc=$rw['account']; $chq=$rw['cheque']; $rid=$rw['id'];
						$amnt=number_format($rw['amount']); $mon=$rw['month']; $dy=date("M d",$mon); $bnk.=($bran) ? ", $bran Branch":"";
						$ltrs[$mon] = "<tr valign='top'><td>$dy</td><td>$dy Salary<br>CHQ No: $chq</td><td>$bnk<br>Acc: $acc</td><td>KES $amnt</td><td>
						<i class='bi-printer' style='font-size:20px;cursor:pointer;color:#008fff;' title='Print Payslip' onclick=\"printslip('$mon','$rid')\"></i></td></tr>";
					}
				}
			}
			
			if(count($ltrs)){
				krsort($ltrs); $ptrs = implode("",$ltrs);
			}
			else{ $ptrs = "<tr><td colspan='4' style='text-align:center'>No records found</td></tr>"; }
			
			$tbdata = "<table cellpadding='5' style='border:1px solid #dcdcdc;min-width:700px;border-collapse:collapse;width:100%;font-size:15px' border='1'>
				<tr style='color:#191970;font-weight:bold;font-size:14px;background:#f8f8ff'><td>Date</td><td>Type</td><td>Details</td><td colspan='2'>Info</td></tr> $ptrs
			</table><br>";
		}
		elseif($vtp=="nts"){
			$page = (isset($_GET["pg"])) ? trim($_GET["pg"]):1; $lim=getLimit($page,15);
			$title = "Staff Interactions"; $mst=strtotime("$tyr-Jan"); $mto=strtotime("$tyr-Dec-31,23:59"); $ptrs="";
			$pint = ($uid==$sid or in_array("post staff interaction",$perms)) ? "<button class='bts' style='float:right;padding:3px 6px' onclick=\"popupload('hr/staff.php?pint=$uid&rep=0')\">
			<i class='bi-bookmark-plus'></i> Notes</button>":"";
			
			$sql = $db->query(2,"SELECT *FROM `staff_notes$cid` WHERE `staff`='$uid' AND `time` BETWEEN $mst AND $mto ORDER BY `time` DESC $lim");
			if($sql){
				$staff[0] = array("System","none");
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
				foreach($qri as $row){
					$cnd = json_decode($row['config'],1); $prof=(isset($cnd["profile"])) ? $cnd['profile']:"none";
					$staff[$row['id']]=[prepare(ucwords($row['name'])),$prof];
				}
				
				foreach($sql as $row){
					$tym=date("M-d, H:i",$row['time']); $frm=$staff[$row['sender']][0]; $pic=$staff[$row['sender']][1]; $subj=prepare($row['subject']); 
					$mssg=nl2br(prepare($row["notes"])); $rep=nl2br(prepare($row["repto"])); $sta=$row['status']; $rid=$row['id']; 
					$reply = ($rep) ? "<tr><td colspan='2'><div style='border:1px solid #dcdcdc;border-left:7px solid purple;background:#fff;padding:5px;font-size:13px;
					color:#191970;border-radius:7px;max-height:80px;overflow:hidden'>$rep</div></td></tr>":"";
					$css = ($row['sender']==$row["staff"]) ? "float:right;background:#F0FFFF;":"background:#f8f8ff;"; $css.=($_GET["md"]>800) ? "max-width:85%;":"";
					$img = ($pic=="none") ? "<span style='font-size:30px'>".substr(ucfirst($frm),0,1)."</span>":
					"<img src='data:image/jpg;base64,".getphoto($pic)."' style='height:60px;border-radius:50%;border-top-right-radius:0px;'>";
					$prof = "<div style='width:60px;height:60px;background:#e6e6fa;border-radius:50%;color:#4682b4;font-weight:bold;line-height:55px;margin:0 auto'>$img</div>";
					
					$ptrs.= "<br><div style='font-Family:signika negative;border:1px dashed #008B8B;clear:both;$css'>
						<table cellpadding='5' style='width:100%;margin-top:-10px;font-size:15px'>
							<tr valign='top'><td><span style='padding:5px;background:#008B8B;color:#fff;font-size:14px'><i class='bi-bookmark'></i> $subj</span></td>
							<td style='color:#008B8B;text-align:right;padding-top:15px'>$tym</td></tr> $reply
							<tr valign='top'><td style='width:130px;text-align:center'>$prof<span style='font-size:13px'>$frm</span></td><td><p style='margin-bottom:5px'>$mssg</p>
							<p style='text-align:right;padding-right:10px;margin:0px'><i class='bi-reply-all' style='font-size:30px;color:#008080;cursor:pointer'
							onclick=\"popupload('hr/staff.php?pint=$uid&rep=$rid')\" title='Reply to $frm'></i></p></td></tr>
						</table>
					</div>";
				}
			}
			
			$qri = $db->query(2,"SELECT COUNT(*) AS tot FROM `staff_notes$cid` WHERE `staff`='$uid' AND `time` BETWEEN $mst AND $mto");
			$tbdata = "$ptrs"; $tots=($qri) ? intval($qri[0]['tot']):0;
			$ldiv = "<br>".getLimitDiv($page,15,$tots,"hr/staff.php?vstid=$uid&vtp=$vtp&yr=$tyr");
		}
		elseif($vtp=="lns"){
			$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`"); $names[0]="System";
			foreach($qri as $row){
				$names[$row['id']]=prepare(ucwords($row['name']));
			}
			
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='staff'");
			foreach($res as $row){
				$prods[$row['id']]=prepare(ucwords($row['product']));
			}
			
			$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
			$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $ltd=0; $done=$pend=[]; $ptrs=$ltrs="";
			$istb = ($db->istable(3,"translogs$cid")) ? 1:0; $appst=0;
			
			if($sid==$uid or in_array("view staff loans",$perms) && $isstb){
				$qri = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `stid`='$uid' ORDER BY `disbursement` ASC");
				if($qri){
					foreach($qri as $key=>$row){
						$amnt=fnum($row['amount']); $dur=$row['duration']; $bal=fnum($row['balance']+$row['penalty']); $lid=$row['loan']; $avs=0;
						$day = ($lid) ? date("d-m-Y",$row['disbursement']):date("d-m-Y",$row['time']); $rid=$row['id']; $done[]=$row['disbursement'];
						$sql = $db->query(2,"SELECT `day` FROM `staff_schedule$cid` WHERE `loan`='$lid' ORDER BY `day` DESC LIMIT 1"); 
						$last=($sql) ? $sql[0]['day']:0; $prod=$prods[$row['loan_product']]; $pen=$row['penalty']; $bl=$row['balance']+$pen;
						
						if($lid){
							if($bl>0 and time()<$last){ $stac="<span style='color:green'><i class='fa fa-circle'></i> Running</span>"; $appst++; }
							elseif($bl>0 and time()>$last){ $stac="<span style='color:#FF1493'><i class='fa fa-circle'></i> Default</span>"; $appst++; }
							else{ $stac = "<span style='color:green'><i class='bi-check2-circle'></i> Completed<br>".date('d-m-Y',$row['status'])."</span>"; }
						
							$totpay=fnum($row['paid']); $penal=fnum($row['penalty']);
							$prnt = "<i class='bi-printer' style='color:#000080;cursor:pointer;font-size:20px' title='Print Statement' onclick=\"printdoc('payreport.php?ln=$lid&stf','pay')\"></i>";
							$edipen = ($key==(count($qri)-1) && in_array("edit staffloan penalty",$perms)) ? "<a href='javascript:void(0)' onclick=\"editpenalty('$lid','$pen','$uid')\">Edit</a>":"";
							
							$pent = ($key==(count($qri)-1)) ? "<span class='penam'>$penal</span>":$penal;
							$more = "<a href='javascript:void(0)' onclick=\"popupload('hr/loans.php?loandes=$lid')\">Details</a>";
							if($istb){ $avs = $db->query(3,"SELECT `ref` FROM `translogs$cid` WHERE `ref`='SLN$rid'"); } 
							$prnt.= (in_array("generate loan statement",$perms) && $avs) ? " &nbsp; <i class='bi-journal-text' style='color:#0000CD;cursor:pointer;font-size:18px' 
							title='Generate Loan Statement' onclick=\"printdoc('reports.php?vtp=loanst&mn=$lid&yr=stf','pay')\"></i>":"";
							$act = ($bl>0) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/loans.php?loanact=$lid&id=$uid')\"><i class='bi-list-stars'></i> Action</a>":"";
							
							$ltrs.= "<tr valign='top'><td>$day</td><td>$amnt<br>$more</td><td>$prod<br>$dur days</td><td>$pent $edipen</td><td>$bal</td><td>$totpay 
							<a href='javascript:void(0)' onclick=\"popupload('hr/loans.php?payhist=$lid')\">View</a><br>$prnt</td><td>$stac<br>$act</td></tr>";
						}
						else{
							if(count($levels)){
								$status[8]="<i style='color:#9932CC;'>Pending</i>"; $status[9]="<i style='color:#ff4500;'>Declined</i>";
								foreach($levels as $pos=>$user){
									$stx = ($row['pref']==8) ? "Pended at":"Waiting"; $key=$pos-1;
									$status[$key]="<i style='color:grey;font-size:13px'>$stx ".prepare(ucfirst($user))."</i>";
								}
							}
							else{ $status[0]="<i style='color:#9932CC;'>Pending</i>"; }
						
							$appr =($row['approvals']) ? json_decode($row['approvals'],1):[]; $lis="";
							foreach($appr as $one){ $lis.="<li>$names[$one]</li>"; }
							$lis = ($lis) ? $lis:"----"; $st=$row['status']; $sts=$status[$st]; $pend[]=$row['time'];
							
							$edit = ($st<1 && $uid==$sid) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/loans.php?apply=$rid')\"><i class='fa fa-pencil'></i> Edit</a>":"";
							$del = ($st<1 && $uid==$sid) ? "<a href='javascript:void(0)' style='color:#ff4500;' onclick=\"deleteloan('$rid','$uid')\"><i class='bi-trash'></i> Delete</a>":"";
							$act = (strlen($edit)) ? "<td>$edit | $del</td>":""; $ltd+=(strlen($act)) ? 1:0; $appst++;
							$ptrs.= "<tr valign='top'><td>$day</td><td>$amnt</td><td>$prod<br>$dur days</td><td>$lis</td><td>$sts</td>$act</tr>";
						}
					}
				}
			}
			
			$title = "Staff Loan History"; $be4=$after="";
			$dtrs = ($ltrs) ? $ltrs:"<tr><td colspan='8' style='text-align:center'>No records found</td></tr>";
			$dsbl = ($sid==$uid) ? "":"disabled"; $dsb=($sid==$uid) ? "":"cursor:not-allowed";
			
			if($ptrs){
				$act = ($ltd) ? "<td>Action</td>":""; $title=(max($pend)>max($done)) ? "Pending Applications":"Failed Applications";
				$pds = "<table cellpadding='5' style='border:1px solid #dcdcdc;border-collapse:collapse;width:100%;min-width:700px;font-size:15px' border='1'>
					<tr><td colspan='8' style='color:#191970;background:#e6e6fa;font-weight:bold'>$title</td></tr>
					<tr style='color:#191970;font-weight:bold;font-size:14px;background:#f8f8ff'><td>Date</td><td>Loan</td><td>Product</td><td>Approvals</td>
					<td>Status</td>$act</tr> $ptrs
				</table><br>";
				if(max($pend)>max($done)){ $be4=$pds; }
				if(max($pend)<max($done)){ $after=$pds; }
			}
			
			$pint = ($uid==$sid && $appst==0) ? "<button class='bts' style='float:right;padding:3px 6px' onclick=\"popupload('hr/loans.php?apply')\">
			<i class='bi-clipboard-plus'></i> Apply Loan</button>":"";
			
			$tbdata = "$be4 <table cellpadding='5' style='border:1px solid #dcdcdc;border-collapse:collapse;width:100%;min-width:700px;font-size:15px' border='1'>
				<tr style='color:#191970;font-weight:bold;font-size:14px;background:#f8f8ff'><td>Date</td><td>Loan</td><td>Product</td><td>Penalty</td>
				<td>T.Balance</td><td>Repayment</td><td>Status</td></tr> $dtrs
			</table><br> $after";
		}
		else{
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='officers' AND `client`='$cid'");
			$usa = staffInfo($uid); $cnd=json_decode($usa["config"],1); $loff=($chk) ? $chk[0]['value']:$usa["position"];
			$cond = ($usa["access_level"]=="branch") ? "AND `branch`='".$usa["branch"]."'":"";
			$cond = ($usa["access_level"]=="portfolio" or $usa["position"]==$loff) ? "AND `staff`='$uid'":$cond;
			$cond = ($usa["access_level"]=="region") ? "AND ".setRegion($cnd["region"]):$cond;
		
			$list = array("newloans"=>"New Loans","repeats"=>"Repeat Loans","performing"=>"Performing","arrears"=>"Arrears","loanbook"=>"Loan Book","disbursement"=>"Gross disbursement",
			"clientelle"=>"Client Activation","income"=>"Revenue","checkoff"=>"Check Off/Topup");
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='analyticskpis' AND `client`='$cid'");
			$sett = ($res) ? json_decode($res[0]['value'],1):array("newloans","repeats","performing","arrears","loanbook","disbursement");
			$mst = strtotime("$tyr-Jan"); $mto=strtotime("$tyr-Dec"); $ltrs=$ths1=$ths2=""; $title="$tyr Performance";
			
			$qri = $db->query(2,"SELECT DISTINCT `month` FROM `bi_analytics$cid` WHERE `month` BETWEEN $mst AND $mto $cond ORDER BY `month` ASC");
			if($qri){
				foreach($qri as $rw){
					$mn=$rw["month"]; $lbk=$actv=$dorm=$port=$arr=$perf=$rept=$nln=$col=$disb=$cko=$rpt=$nlt=$pft=$arrt=$dist=$cat=$ckot=$inct=$obt=$cla=$inc=0;
					$mxd = $db->query(2,"SELECT MAX(day) AS mxd FROM `bi_analytics$cid` WHERE `month`='$mn' $cond")[0]['mxd'];
					$sql = $db->query(2,"SELECT *FROM `bi_analytics$cid` WHERE `month`='$mn' $cond");
					foreach($sql as $row){
						$rept+=$row['repeats']; $nln+=$row['newloans']; $col+=$row['collection']; $disb+=$row['disbursed']; $cko+=$row['checkoff']; $cla+=$row['activation'];
						if($row["day"]==$mxd){
							$lbk+=$row['loanbook']; $actv+=$row['active']; $dorm+=$row['dormant']; $port+=$row['portfolio']; $arr+=$row['arrears']; 
							$perf+=$row['performing']; $inc+=getIncome("officer",$row["staff"],$mn);
						}
					}
					
					$qry = $db->query(2,"SELECT * FROM `org$cid"."_targets` WHERE `month`='$mn' ".str_replace("`staff`","`officer`",$cond));
					if($qry){
						foreach($qry as $row){
							$rpt+=$row['repeats']; $nlt+=$row['newloans']; $pft+=$row['performing']; $arrt+=$row['arrears']; $dist+=$row['disbursement']; 
							$cat+=$row['clientelle']; $ckot+=$row['checkoff']; $inct+=$row['income']; $obt+=$row['loanbook'];
						}
					}
					
					$data = array("newloans"=>[$nlt,$nln],"repeats"=>[$rpt,$rept],"performing"=>[$pft,$perf],"arrears"=>[$arrt,$arr],"clientelle"=>[$cat,$cla],
					"income"=>[$inct,$inc],"checkoff"=>[$ckot,$cko],"loanbook"=>[$obt,$lbk],"disbursement"=>[$dist,$disb]); $tds="";
					foreach($sett as $one){
						if(isset($data[$one])){
							$perc = ($data[$one][0]) ? round($data[$one][1]/$data[$one][0]*100,1):0; $val2=fnum($data[$one][1]);
							$upd =($one!="arrears") ? "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span>":"";
							$tds.= "<td>".fnum($data[$one][0])."</td><td>$val2 $upd</td>";
						}
					}
					
					$ltrs.= "<tr style='font-size:14px'><td>".date("M-Y",$mn)."</td>$tds</tr>";
				}
			}
			
			foreach($sett as $col){ $ths1.= "<td colspan='2'>$list[$col]</td>"; $ths2.="<td>Target</td><td>Actual</td>"; }
			$tbdata = "<table cellpadding='5' style='border:1px solid #dcdcdc;min-width:700px;border-collapse:collapse;width:100%;font-size:15px' border='1'>
				<tr style='background:#4682b4;color:#fff;font-weight:bold;font-size:13px;' valign='top'><td>Month</td>$ths1</tr>
				<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td></td>$ths2</tr> $ltrs
			</table><br>";
		}
		
		if(in_array("suspend staff",$perms) && $post!="super user"){
			$txt = ($cst!=1) ? "Suspend":"Activate"; $val=($cst==1) ? 0:1;
			$susp = "<button class='bts' style='font-size:14px;padding:3px;' onclick=\"staffop('$val','$uid','1')\"><i class='fa fa-gavel'></i> $txt</button>";
		}
		
		$act = "<button class='bts' style='font-size:14px;padding:3px 5px;float:;margin-top:10px' onclick=\"popupload('hr/staff.php?uaction=$uid')\">
		<i class='bi-list-stars'></i> Options</button>";
		$sms = (in_array("send sms",$perms)) ? "<button class='bts' style='font-size:14px;padding:3px 5px;margin-left:5px' onclick=\"popupload('hr/staff.php?comm=$uid&tp=sms')\">
		<i class='fa fa-envelope-o'></i> SMS</button> <button class='bts' style='font-size:14px;padding:3px 5px;margin-left:5px' onclick=\"popupload('hr/staff.php?comm=$uid&tp=mail')\">
		<i class='fa fa-envelope-o'></i> Email</button>":"";
		$trs.= (in_array("edit staff",$perms)) ? "<tr><td colspan='2' style='text-align:right'><hr><p style='margin-bottom:10px'><button class='bts' 
		style='padding:3px 5px' onclick=\"popupload('hr/staff.php?add=$uid')\"><i class='fa fa-pencil'></i> Update</button>  $sms</p></td></tr>":"";
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $vls="";
		
		foreach(array("pfr"=>"Performance","nts"=>"Interactions","lns"=>"Staff Loans","lvp"=>"Leaves & Payroll") as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$vls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		$tls = "<option value='$yr'>$yr</option>"; $lst=strtotime("2021-Dec");
		$qri = $db->query(2,"SELECT from_unixtime(month,'%Y') AS yr FROM `bi_analytics$cid` WHERE `month`>$lst GROUP BY yr ORDER BY `month` DESC");
		if($qri){
			foreach($qri as $row){
				$val=$row['yr']; $cnd=($val==$tyr) ? "selected":"";
				if($val!=$yr){ $tls.= "<option value='$val' $cnd>$val</option>"; }
			}
		}
		
		echo "<div class='container cardv' style='max-width:1300px;background:#fff;overflow:auto;padding:10px 0px'>
			<div class='row' style='margin:0px'> 
				<div class='col-12 mb-1'><h3 style='margin:0px;text-align:right'>$backbtn 
				<span style='font-size:18px;font-weight:bold;float:left;color:#191970'>$cname</span> $act $susp</h3><hr style='margin:5px 0px'>
				<div class='row'>$doc</div></div>
				<div class='col-12 col-md-12 col-lg-4 col-xl-3 mb-2'>
					<table cellpadding='6' style='width:100%;border:1px solid #dcdcdc;font-size:14px'>$trs</table>
				</div>
				<div class='col-12 col-md-12 col-lg-8 col-xl-9'>
					<p style='margin-bottom:5px'><select style='width:140px;font-size:15px;' onchange=\"loadpage('hr/staff.php?vstid=$uid&vtp='+this.value)\">$vls</select>
					<select style='width:70px;font-size:15px;' onchange=\"loadpage('hr/staff.php?vstid=$uid&vtp=$vtp&yr='+this.value)\">$tls</select> $pint</p>
					<p style='padding:6px;background:#f0f0f0;color:#191970;font-weight:bold;text-align:center;margin-bottom:0px;border:1px solid #dcdcdc'>$title</p>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>$tbdata</div> $ldiv
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed staff $cname account Details");
	}
	
	# staff Positions
	if(isset($_GET["setpos"])){
		$uid = trim($_GET["setpos"]); 
		$post = staffPost($uid); $trs="";
		
		$qri = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='officers'");
		$sql = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid' ORDER BY `sgroup` ASC"); 
		$sql[] = array("sgroup"=>"collection agent","id"=>0); $ofs=($qri) ? $qri[0]['value']:"";
		foreach($sql as $row){
			$grp=$row["sgroup"]; $cnd=(isset($post[$grp])) ? "checked":""; $id=$row['id']; $pls="";
			$def = ["hq"=>"Corporate Access","region"=>"Regional Access","branch"=>"Branch Access","portfolio"=>"Portfolio Access"];
			if($ofs==$grp or $grp=="collection agent"){ unset($def["hq"],$def["region"]); }
			foreach($def as $ps=>$txt){
				$dst=($cnd) ? $post[$grp]:""; $cnd2=($dst==$ps) ? "selected":"";
				$pls.= "<option value='$ps' $cnd2>$txt</option>";
			}
			
			$trs.= "<tr><td><input type='checkbox' name='spost[]' value='$grp%$id' $cnd> &nbsp; ".prepare(ucwords($grp))."</checkbox></td>
			<td style='width:50%'><select style='width:100%;font-size:15px;padding:5px 7px' name='slevs[$id]'>$pls</select></td></tr>";
		}
		
		echo "<div style='margin:0 auto;padding:10px;max-width:400px'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>Manage Staff Positions</h3><br>
			<form method='post' id='pform' onsubmit=\"setpost(event)\">
				<input type='hidden' name='stuid' value='$uid'> 
				<table style='width:100%' cellpadding='5'>$trs</table><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p><br>
			</form>
		</div>";
	}
	
	# post Notes
	if(isset($_GET["pint"])){
		$uid = trim($_GET["pint"]);
		$rep = trim($_GET["rep"]); $opts="";
		$def = array("Performance","PTP","Collection","Arrears","Production","Follow-Up");
		foreach($def as $sub){ $opts.= "<option value='$sub'>$sub</option>"; }
		$title = ($rep) ? "Reply to Interaction":"Create Interaction";
		$subj = ($rep) ? "":"<p>Subject to Address<br><select style='width:100%' name='nsubj'>$opts</select></p>";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:350px'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>$title</h3><br>
			<form method='post' id='iform' onsubmit=\"postnotes(event,'$uid')\">
				<input type='hidden' name='nuid' value='$uid'> <input type='hidden' name='repto' value='$rep'> $subj
				<p>Message Note<br><textarea class='mssg' name='snote' style='min-height:150px' required></textarea></p>
				<p style='text-align:right'><button class='btnn'>Post</button></p><br>
			</form>
		</div>";
	}
	
	# staff wallet
	if(isset($_GET["wallet"])){
		$uid = trim($_GET["wallet"]);
		$page = (isset($_GET["pg"])) ? trim($_GET["pg"]):1;
		$vop = (isset($_GET["vop"])) ? trim($_GET["vop"]):0;
		$fro = (isset($_GET["fro"])) ? strtotime($_GET["fro"]):0;
		$dto = (isset($_GET["dto"])) ? strtotime($_GET["dto"]):strtotime(date("Y-M-d"));
		$vtp = (isset($_GET["vtp"])) ? trim($_GET["vtp"]):"balance";
		$staff[0]="System"; $tmon=strtotime(date("Y-M")); $trs=$lis=""; 
		$sttl = (defined("SAVINGS_TERMS")) ? SAVINGS_TERMS["acc"]:"Savings";
		$rcons = sys_constants("wallet_revtrans_time"); $revtm=($rcons) ? $rcons*86400:3*86400;
		
		$dfro = ($dto<$fro) ? $dto:$fro; $dtu=($dto<$fro) ? $fro:$dto; $dtu+=86399;
		$me = staffInfo($sid); $lim=getLimit($page,20); $wid=createWallet("staff",$uid);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$uid'");
		$cname = prepare(ucwords($sql[0]["name"])); $bid=$sql[0]["branch"]; $wbal=walletBal($wid,$vtp);
		$ttls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>$sttl);
		$title = $ttls[$vtp]." Account"; $vbk=$ttls[$vtp];
		
		if(!$db->istable(3,"walletrans$cid")){
			$db->createTbl(3,"walletrans$cid",["tid"=>"CHAR","wallet"=>"INT","transaction"=>"CHAR","book"=>"CHAR","amount"=>"INT","details"=>"CHAR","afterbal"=>"CHAR",
			"approval"=>"INT","status"=>"INT","reversal"=>"LONGTEXT","time"=>"INT"]);
		}
		
		$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
		$invtbl = ($db->istable(3,"investments$cid")) ? 1:0;
		
		if($vop){
			$ths = "<td>Date</td><td>Investment</td><td>Duration</td><td>Payouts</td><td>Maturity</td><td>Status</td><td>Balance</td>";
			if($invtbl){
				$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='S$uid' ORDER BY `time` DESC");
				if($sql){
					foreach($sql as $row){
						$dy=date("d-m-Y,H:i",$row['time']); $def=json_decode($row['details'],1); $amnt=fnum($row['amount']); $pack=prepare($def["pname"]);
						$dur=$row['period']; $paid=fnum($def["payouts"]); $bal=$def["balance"]; $exp=date("d-m-Y, H:i",$row['maturity']);
						$css = "font-size:13px;color:#fff;padding:4px;font-family:signika negative"; $st=($row['status']>200) ? 200:$row['status'];
						$states = array(0=>"<span style='background:green;$css'>Active</span>",200=>"<span style='background:#20B2AA;$css'>Matured</span>",
						15=>"<span style='background:orange;$css'>Terminated</span>"); $rid=$row['id']; $wvtx=($row["maturity"]>time()) ? "Terminate":"Withdraw";
						$toa = (in_array("terminate investment",$perms) && $bal>0) ? "<a href='javascript:void(0)' onclick=\"withdrawinv('$rid','$wvtx')\">
						<i class='bi-download'></i> $wvtx</a>":"";
						
						$trs.= "<tr valign='top'><td>$dy</td><td><b>Ksh $amnt</b><br>$pack</td><td>$dur Days</td><td>Ksh $paid</td><td>$exp</td><td>$states[$st]</td>
						<td>Ksh ".fnum($bal)."<br>$toa</td></tr>";
					}
				}
			}
		}
		else{
			$ths = "<td>Date</td><td>TID</td><td>Details</td><td>Approval</td><td style='text-align:right'>Amount</td><td style='text-align:right'>Bal After</td><td></td>";
			$sql = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk' AND `time` BETWEEN $dfro AND $dtu ORDER BY `time` DESC,`id` DESC $lim");
			if($sql){
				foreach($sql as $row){
					$revs = $row['reversal']; $rtp=(isJson($revs)) ? "rev":$revs; $tm=$row['time']; $nrv=0;
					if(isJson($revs)){ foreach(json_decode($revs,1) as $one){ $nrv+=($one["tbl"]=="sysoverpay") ? 1:0; }}
					$dy=date("d-m-Y,H:i",$tm); $tid=$row['tid']; $appr=$staff[$row['approval']]; $amnt=fnum($row['amount']); $rid=$row['id']; $tdf=time()-$tm;
					$bal=fnum($row['afterbal']); $css="text-align:right"; $des=prepare(ucfirst($row['details'])); $sgn=($row["transaction"]=="debit") ? "+":"-"; 
					$rev = ($rtp!="norev" && in_array("reverse wallet transaction",$perms) && $row['status']==0 && $tdf<$revtm && !$nrv) ? "<a href='javascript:void(0)' 
					onclick=\"revtrans('$rid')\" style='cursor:pointer'><i class='bi-bootstrap-reboot'></i> Reverse</a>":""; $css1=($row['status']) ? "color:#B0C4DE":"";
					$desc=(strlen($des)>30) ? substr($des,0,30)."...<i title='View More' class='fa fa-plus' style='color:#008fff;cursor:pointer' onclick=\"setcontent('$rid','$des')\"></i>":$des;
					$trs.= "<tr valign='top' style='$css1'><td>$dy</td><td>$tid</td><td id='tr$rid'>$desc</td><td>$appr</td><td style='$css'>$sgn$amnt</td>
					<td style='$css'><b>$bal</b></td><td>$rev</td></tr>";
				}
			}
		}
		
		$qri = $db->query(3,"SELECT COUNT(*) AS tot,MIN(time) AS mnt FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk'");
		$qry = $db->query(3,"SELECT SUM(amount) AS tsum FROM `walletrans$cid` WHERE `wallet`='$wid' AND `transaction`='credit' AND `book`='$vbk' AND 
		`status`='0' AND NOT `details` LIKE 'Loan Repayment%' AND NOT `details` LIKE '[Reversal]%'");
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $tots=intval($qri[0]['tot']); $twd=intval($qry[0]['tsum']); $tdy=date("Y-m-d");
		$mnt = ($qri) ? $qri[0]['mnt']:$tmon; $dfro=($dfro) ? $dfro:$mnt; $dftm=date("Y-m-d",$dfro); $dtm=date("Y-m-d",$dtu);
		$accno = walletAccount($bid,$wid,$vtp);
		
		$roibal = (in_array($vtp,["balance","savings"])) ? $twd:0; $switch="";
		$prnt = ($trs) ? "<button class='bts' style='float:right;padding:3px 6px' onclick=\"printdoc('reports.php?vtp=wallets&mn=$dfro:$dtu&yr=$wid&src=$vtp&vp=$vop','wallet')\">
		<i class='bi-printer'></i> Print</button>":"";
		$trs = ($trs) ? $trs:"<tr><td colspan='5'>No Transactions Found</td></tr>";
		$wht = (in_array($vtp,["balance","savings"])) ? "Withdrawals":"Invested Balance";
		
		if(in_array($vtp,["balance","savings"])){
			$vtitle = "Transaction List";
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "popupload('payments.php?wfrom=$wid&wtp=$vtp&uid=$uid:staff')":"toast('Permission Denied!')";
			$wbtn = (in_array("manage wallet withdrawals",$perms)) ? "<button class='btnn' onclick=\"popupload('payments.php?wfrom=$wid&wtp=real&uid=$uid:staff')\" 
			style='background:#BA55D3;font-size:14px;padding:4px;margin-top:10px'><i class='bi-download'></i> Withdraw</button>":"";
		}
		else{
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "transwallet('$wid','$wbal')":"toast('Permission Denied!')";
			$wbtn = (in_array("create investment",$perms)) ? "<button class='btnn' onclick=\"popupload('accounts/investors.php?invest=S$uid')\" 
			style='background:#20B2AA;font-size:14px;padding:4px;margin-top:10px'><i class='bi-bag-plus'></i> Invest</button>":""; $vopt="";
			foreach(array("Transactions","Investments") as $key=>$txt){
				$cnd = ($key==$vop) ? "selected":"";
				$vopt.= "<option value='$key' $cnd>$txt</selected>";
			}
			
			$vtitle = ($vop) ? "Investments List":"Transactions List";
			$switch = "<select style='width:130px;padding:4px;float:right;font-size:15px' onchange=\"loadpage('hr/staff.php?wallet=$uid&vtp=$vtp&vop='+this.value)\">$vopt</select>";
		}
		
		foreach($ttls as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$txt Account</option>";
		}
		
		if($invtbl && $vtp=="investbal"){
			$sql = $db->query(3,"SELECT SUM(amount) AS tsum FROM `investments$cid` WHERE `client`='S$uid' AND `status`='0'");
			$roibal = ($sql) ? intval($sql[0]['tsum']):0;
		}
		
		echo "<div class='container cardv' style='max-width:1300px;background:#fff;overflow:auto;padding:10px 0px'>
			<div class='row' style='margin:0px'>
				<div class='col-12 col-md-12 mb-2'>
					<h3 style='color:#191970;font-size:20px;font-weight:bold'>$backbtn $title</h3>
				</div>
				<div class='col-12 col-md-12 col-lg-4 col-xl-3  mb-2'>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<p style='padding:10px;font-weight:bold;background:#f0f0f0;margin:0px'><i class='fa fa-user'></i> $cname</p>
						<div style='padding:7px;text-align:center;color:#191970;background:#B0C4DE;margin-bottom:15px'>
						<select style='width:100%;background:transparent;border:0px;cursor:pointer'onchange=\"loadpage('hr/staff.php?wallet=$uid&vtp='+this.value)\">$lis</select></div>
						<div style='color:#fff;background:#4682b4;padding:10px;border-radius:5px;font-Family:signika negative'>
							<table style='width:100%'><tr>
								<td><h3><span style='font-size:13px'>KES</span> ".fnum($wbal)."<br><span style='font-size:13px;color:#f0f0f0'>Available Balance</span></h3></td>
								<td style='text-align:right'><button class='btnn' style='font-size:14px;padding:4px' onclick=\"popupload('payments.php?wtopup=$wid&wtp=$vtp&uid=$uid:staff')\">
								<i class='bi-plus-circle'></i> Deposit</button></td>
							</tr><tr><td colspan='2' style='color:#00FFFF;font-size:14px;'><hr style='margin-top:0px' color='#ccc'>AccNo: $accno
							<button class='btnn' onclick=\"$trans\" style='background:#20B2AA;font-size:14px;padding:4px;float:right'><i class='bi-arrow-left-right'></i> Transfer</button></td></tr></table>
						</div>
						<div style='color:#fff;background:#2F4F4F;padding:10px;border-radius:5px;font-Family:signika negative;margin-top:10px'>
							<table style='width:100%'><tr>
								<td><h3><span style='font-size:13px'>KES</span> ".fnum($roibal)."<br><span style='font-size:13px;color:#ccc'>$wht</span></h3></td>
								<td style='text-align:right'>$wbtn</td>
							</tr></table>
						</div><br>
					</div>
				</div>
				<div class='col-12 col-md-12 col-lg-8 col-xl-9'>
					<h3 style='color:#191970;font-size:18px;font-weight:bold'>$vtitle $switch</h3>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<table cellpadding='5' class='table-striped' style='border-collapse:collapse;width:100%;min-width:550px;font-size:15px'>
							<caption style='caption-side:top'>
								<input type='date' value='$dftm' max='$tdy' style='width:160px;font-size:15px;padding:4px' id='fro' onchange='setrange()'> ~
								<input type='date' value='$dtm' max='$tdy' style='width:160px;font-size:15px;padding:4px' id='dto' onchange='setrange()'> $prnt
							</caption>
							<tr style='color:#191970;font-weight:bold;font-size:14px;background:#e6e6fa'>$ths</tr> $trs
						</table><br>".getLimitDiv($page,20,$tots,"hr/staff.php?wallet=$uid&vtp=$vtp&fro=$dftm&dto=$dtm&vop=$vop")."
					</div>
				</div>
			</div><br>
		</div>
		<script>
			function setrange(){
				var fro = $('#fro').val(), dto=$('#dto').val(); 
				loadpage('hr/staff.php?wallet=$uid&vtp=$vtp&fro='+fro+'&dto='+dto);
			}
			function setcontent(id,des){ $('#tr'+id).html(des); }
		</script>";
		savelog($sid,"Viewed $cname account wallet");
	}
	
	# set collection zones for collection agent
	if(isset($_GET["colz"])){
		$uid = trim($_GET['colz']); $usa = staffInfo($uid); $lis=[];
		$cnf = json_decode($usa['config'],1); $name=prepare(ucwords($usa['name'])); $zns=(isset($cnf["zones"])) ? $cnf["zones"]:[]; 
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		foreach($sql as $row){
			$rid=$row['id']; $cnd=(in_array($rid,$zns)) ? "checked":""; $bnm=prepare(ucfirst($row['branch']));
			$lis[] = "<li style='list-style:none;padding-top:5px'><input type='checkbox' name='zones[]' value='$rid' $cnd>&nbsp; $bnm</checkbox>";
		}
		
		$hlf = ceil(count($lis)/2); $arr=array_chunk($lis,$hlf);
		$td1 = implode("",$arr[0]); $td2=(isset($arr[1])) ? implode("",$arr[1]):"";
		
		echo "<div style='max-width:400px;padding:10px;margin:0 auto'><br>
			<div style='max-width:350px;margin:0 auto;font-family:cambria'>
				<h4 style='color:#2f4f4f;font-weight:bold;text-align:center'>$name Operation Zones</h4><br>
				<form method='post' id='zfom' onsubmit=\"savezones(event,'$uid')\">
					<input type='hidden' name='colz' value='$uid'>
					<table style='width:100%;background:#f0f0f0' cellpadding='7'>
						<tr valign='top'><td>$td1</td><td>$td2</td></tr>
					</table><br>
					<p style='text-align:right'><button class='btnn' style='padding:6px;'>Save</button></p><br>
				</form>
			</div>
		</div>";
	}
	
	# send email/password
	if(isset($_GET['comm'])){
		$uid = trim($_GET['comm']);
		$dtp = trim($_GET['tp']);
		$user = staffInfo($uid);
		
		$name = prepare(ucwords($user['name'])); 
		$sto = ($dtp=="sms") ? $user['contact']:$user['email'];
		$title = ($dtp=="sms") ? "SMS $name":"Email $name";
		
		$data = ($dtp=="mail") ? "<p>Email Subject<br><input type='text' style='width:100%' name='subject' required></p>
		<p>Type Message<br><textarea class='mssg' name='emssg' style='height:150px' autofocus></textarea></p>
		<p>Attach Document (Optional)<br><input type='file' accept='.pdf,.docx,.doc,.xls,.xlsx,.csv' id='emdoc'></p><br>":
		"<p>Type Message<br><textarea class='mssg' name='emssg' style='height:150px' autofocus></textarea></p><br>";
		
		echo "<div style='max-width:400px;padding:10px;margin:0 auto'><br>
			<div style='max-width:350px;margin:0 auto;font-family:cambria'>
				<h4 style='color:#2f4f4f;font-weight:bold'>$title</h4><br>
				<form method='post' id='cform' onsubmit=\"sendcom(event,'$dtp','".str_replace("'","",$title)."')\">
					<input type='hidden' name='sendto' value='$sto'> $data 
					<p style='text-align:right'><button class='btnn' style='padding:6px;'>Send</button></p><br>
				</form>
			</div>
		</div>";
	}
	
	@ob_end_flush();
?>
	
<script>
	
	function savepcomm(e,uid,dy){
		e.preventDefault();
		if(confirm("Proceed to post Workplan comment?")){
			var data = $("#cmfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); loadpage("hr/staff.php?workplan="+uid+"&day="+dy);
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveplanres(e,uid,dy){
		e.preventDefault();
		if(confirm("Proceed to Update Workplan achievement?")){
			var data = $("#prfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); fetchpage("hr/staff.php?workplan="+uid+"&day="+dy);
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveplan(e,uid){
		e.preventDefault();
		if(confirm("Sure to save Workplan?")){
			var data = $("#plfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); fetchpage("hr/staff.php?workplan="+uid+"&day="+$("#pday").val());
				}
				else{ alert(res); }
			});
		}
	}
	
	function withdrawinv(inv,txt){
		if(confirm("Sure to "+txt+" Investment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/investors.php",data:{withdrawinv:inv},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function setpost(e){
		e.preventDefault();
		if(confirm("Assign selected Position(s) to Employee?")){
			var data = $("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function revtrans(rid){
		if(confirm("Sure to reverse wallet transaction?")){
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:{revtrans:rid},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function transwallet(wid,amt){
		if(amt==0){ toast("Insufficient Balance!"); }
		else{
			var amnt = prompt("Enter Amount to Transfer to Transactional Account",amt);
			if(amnt){
				if(confirm("Sure to transfer Ksh "+amnt+" to Transactional Account?")){
					$.ajax({
						method:"post",url:path()+"dbsave/payments.php",data:{transwid:wid,transfro:"investbal",transto:"balance",tamnt:amnt},
						beforeSend:function(){ progress("Processing...please wait"); },
						complete:function(){ progress(); }
					}).fail(function(){
						toast("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim()=="success"){ closepop(); window.location.reload(); }
						else{ alert(res); }
					});
				}
			}
		}
	}
	
	function staffop(val,uid,perm){
		if(perm==1){
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:{suspstid:uid,susval:val},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast(res); loadpage("hr/staff.php?vstid="+uid); closepop(); }
				else{ alert(res); }
			});
		}
		else{
			var txt = (perm==2) ? "Sorry! You can't perform operation on Assistant account":"You dont have permissions to complete the operation";
			alert(txt);
		}
	}
	
	function printslip(mon,pid){
		$.ajax({
			method:"post",url:path()+"dbsave/payslip.php",data:{mailmon:mon,payid:pid,noemail:1},
			beforeSend:function(){ progress("Preparing...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim().split(":")[0]=="success"){ 
				var loc = (window.location.hostname=="localhost") ? "/mfs/":"/";
				window.open(loc+"pdf/files/payslips/"+res.trim().split(":")[1]);
			}
			else{ alert(res); }
		});
	}
	
	function setregion(val){
		if(val=="region"){ $("#rinp").show(); }
		else{ $("#rinp").hide(); }
	}
	
	function payroll(uid,vtp){
		$.ajax({
			method:"post",url:path()+"dbsave/account.php",data:{accpay:uid,payval:vtp},
			beforeSend:function(){ progress("Processing...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim()=="success"){ toast("Updated successfully"); }
			else{ alert(res); }
		});
	}
	
	function changepass(uid){
		var p1 = $("#pass").val().trim(), p2 = $("#pass2").val().trim();
		if(p1.length<8){ toast("Password length too little!"); $("#pass").focus(); }
		else if(p1!=p2){ toast("Passwords unmatch!"); $("#pass2").val(""); $("#pass2").focus(); }
		else{
			if(confirm("Update staff password?")){
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:{changepass:uid,passw:p1},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Changed successfully"); closepop(); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function savezones(e,uid){
		e.preventDefault();
		if(!checkboxes("zones[]")){ toast("No branch selected!"); }
		else{
			if(confirm("Proceed to set operation zones?")){
				var data = $("#zfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ loadpage('hr/staff.php?vstid='+uid); closepop(); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function postnotes(e,uid){
		e.preventDefault();
		if(confirm("Proceed to post Notes?")){
			var data = $("#iform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/account.php",data:data,
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); loadpage("hr/staff.php?vstid="+uid+"&vtp=nts"); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function sendcom(e,tp,ttl){
		e.preventDefault();
		if(confirm("Proceed to "+ttl+"?")){
			var doc = (tp=="mail") ? _("emdoc").files[0]:null;
			if(doc!=null){
				var data = new FormData(_("cform"));
				data.append("attdoc",doc);
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload = function(){
					if(this.responseText.trim()=="success"){ toast("Sent successfully!"); closepop(); }
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/account.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#cform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:data,
					beforeSend:function(){ progress("Sending...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Sent successfully!"); closepop(); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function savegroles(e,id){
		e.preventDefault();
		if(checkboxes('roles[]')){
			var txt = (id==1) ? "Update staff group & permissions?":"Create staff group?"; 
			var stx = (id==2) ? "Update group permissions?":txt;
			if(confirm(stx)){
				var data = $("#pfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/setup.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast(res); closepop(); window.location.reload();
					}
					else{ alert(res); }
				});
			}
		}
		else{ alert("No roles selected for the Staff"); }
	}
	
	function saveperms(e){
		e.preventDefault();
		if(checkboxes('uroles[]')){
			if(confirm("Update staff permissions?")){
				var data=$("#pform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast(res); closepop(); window.location.reload();}
					else{ alert(res); }
				});
			}
		}
		else{ alert("No roles selected for the Staff"); }
	}
	
	function savestaff(e){
		e.preventDefault();
		if(confirm("Save staff personal info?")){
			var tp = _("hasfiles").value.trim();
			if(tp.length>2){
				var data=new FormData(_("sform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					data.append(files[i],_(files[i]).files[0]);
				}
				
				var xhr=new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload=function(){
					if(this.responseText.trim()=="success"){
						toast("Success"); closepop(); loadpage("hr/staff.php?manage");
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/account.php",true);
				xhr.send(data);
			}
			else{
				var data=$("#sform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/account.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast(res); closepop(); window.location.reload(); }
					else{ alert(res); }
				});
			}
		}
	}

</script>