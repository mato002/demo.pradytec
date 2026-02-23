<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	
	# access
	if(isset($_GET['access'])){
		$show = trim($_GET['access']);
		$me = staffInfo($sid);
		$myperms = getroles(explode(",",$me['roles']));
		
		$setts = [];
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$setts[$row['setting']]=$row['value'];
			}
		}
		
		$otp = (array_key_exists("login",$setts)) ? $setts['login']:1;
		$dedicated = (array_key_exists("officers",$setts)) ? $setts['officers']:"all";
		$otpc = ($otp) ? "checked":"";
		
		# staff groups
		$no=0; $opts=$divs=""; $dcs="<option value='all'>All Staff</option>"; $perms=[];
		$res = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
		if($res){
			$show = ($show) ? $show:$res[0]['id'];
			foreach($res as $row){
				$no++; $groups[]=$row['sgroup']; $rid=$row['id'];
				$cnd=($rid==$show) ? "selected":""; $cnd1=($row['sgroup']==$dedicated) ? "selected":""; 
				$opts.= "<option value='$rid' $cnd>".prepare(ucwords($row['sgroup']))."</option>";
				$dcs.= "<option value='".$row['sgroup']."' $cnd1>".prepare(ucwords($row['sgroup']))."</option>";
				if($cnd=="selected"){ $perms=explode(",",$row['roles']); }
			}
		}
		
		# roles
		$res = $db->query(1,"SELECT DISTINCT `groups` FROM `useroles` ORDER BY `groups` DESC");
		foreach($res as $rw){
			$grp = $rw['groups']; $lis="";
			$qri = $db->query(1,"SELECT *FROM `useroles` WHERE `groups`='$grp' ORDER BY `role` ASC");
			foreach($qri as $row){
				$role=ucfirst($row['role']); $rid=$row['id']; $cnd = (in_array($rid,$perms)) ? "checked":"";
				$lis.="<li style='padding:3px 0px'><input type='checkbox' name='roles[]' onclick='return false;' value='$rid' $cnd> &nbsp; $role</checkbox></li>";
			}
			
			$divs.= "<div class='col-12 col-lg-4 col-md-6 mb-3'>
				<p style='font-weight:bold;color:#191970;background:#f0f0f0;padding:5px'>".ucwords($grp)." permissions</p>
				<ol style='list-style:none'> $lis </ol>
			</div>";
		}
		
		$tot = ($no==1) ? "1 Group":"$no Groups";
		$opts = ($opts) ? $opts:"<option value='0'>-- No staff groups --</option>";
		$add = (in_array("create staff group",$myperms)) ? "| <a href='javascript:void(0)' onclick=\"popupload('hr/staff.php?mkgrp')\"><i class='fa fa-plus'></i> Create Group</a>":""; 
		$sbtn = ($show && in_array("update permissions",$myperms)) ? "&nbsp;<button class='btnn' style='padding:5px 8px;font-family:signika negative' 
		onclick=\"popupload('hr/staff.php?mkgrp=$show&gperm')\"><i class='bi-pencil-square'></i> Edit Roles</button>":""; 
		
		$olis=$lis2=""; $trs=[];
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='passreset' AND `client`='$cid'"); $reset = ($res) ? $res[0]['value']:0;
		foreach(array(0=>"-- Switch Off --",1=>"Every Date 01",15=>"Every Date 15",10=>"After Every 10 Days",28=>"After every 28 Days") as $key=>$val){
			$cnd = ($key==$reset) ? "selected":"";
			$olis.="<option value='$key' $cnd>$val</option>";
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='otpfor' AND `client`='$cid'"); 
		$otps = ($res) ? json_decode($res[0]['value'],1):[];
		foreach($groups as $grup){
			$cnd=(in_array($grup,$otps)) ? "checked":""; $gname=prepare(ucwords($grup));
			$trs[]="<li style='padding:5px 0px;list-style:none'><input class='otps' type='checkbox' value='$grup' $cnd> &nbsp; $gname</checkbox></li>";
		}
		
		if(count($trs)>3){
			$all =array_chunk($trs,ceil(count($trs)/2));
			$lis1 = implode("",$all[0]);
			$lis2 = implode("",$all[1]);
		}
		else{ $lis1 = implode("",$trs); }
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn System Access Settings</h3><hr>
				<div class='row'>
					<div class='col-12 col-md-6 col-lg-4 mb-3'>
						<div style='border:1px solid #f0f0f0;padding:10px;background:#f8f8ff'>
							<p style='color:#191970;font-weight:bold;margin:0px'><i class='fa fa-key'></i> Login OTP</p>
							<p><input type='checkbox' $otpc onclick=\"savesetting('login:0')\"> &nbsp; Enable Login OTP for all staff</checkbox></p>
							<fieldset style='border:1px solid #ccc;padding:5px'>
								<legend style='font-size:17px;width:85%'> OR Only allow Verification For:</legend> 
								<table style='width:100%' cellpadding='5'>
									<tr><td>$lis1</td><td>$lis2</td></tr>
									<tr><td colspan='2' style='text-align:right'><button class='btnn' onclick=\"saveotpset()\"
									style='padding:4px;min-width:60px'>Save</button></td></tr>
								</table>
							</fieldset>
						</div>
					</div>
					<div class='col-12 col-md-6 col-lg-4 mb-3'>
						<div style='border:1px solid #f0f0f0;padding:10px;background:#f8f8ff'>
							<p style='color:#191970;font-weight:bold;margin:0px'><i class='fa fa-users'></i> Staff Groups & Security</p>
							<p>$tot created $add</p>
							<p style='margin:0px'>Require staff Password Reset<br><select style='max-width:200px;cursor:pointer;font-size:15px'
							onchange=\"savesetting('passreset:'+this.value.trim())\">$olis</select></p>
						</div>
					</div>
					<div class='col-12 col-md-6 col-lg-4 mb-3'>
						<div style='border:1px solid #f0f0f0;padding:10px;background:#f8f8ff'>
							<p style='color:#191970;font-weight:bold;margin:0px'><i class='fa fa-briefcase'></i> Dedicated Staff</p>
							<p style='margin:0px'>Set Staff group that work as Loan officers<br>
							<select style='width:200px' onchange=\"savesetting('officers:'+this.value)\">$dcs</select></p>
						</div>
					</div>
					<div class='col-12'><hr>
						<p style='color:#191970;font-weight:bold'><i class='fa fa-leaf'></i> System Access Levels</p>
						<p><select style='width:180px' onchange=\"loadpage('setup.php?access='+this.value.trim())\"> $opts</select> $sbtn</p>
						<input type='hidden' name='userole' value='$show'>
						<div class='row'> $divs </div>
					</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed system access settings");
	}
	
	# company info
	if(isset($_GET['info'])){
		$config = mficlient();
		$address = str_replace("~","\n",prepare($config['address'])); $logo=$config['logo'];
		$form1 = json_encode(array("company","contact","email","address"));
		$form2 = json_encode(array("appname","apikey","senderid"));
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Company Information</h3><hr>
				<div class='row'>
					<div class='col-12 col-lg-6 mb-3'>
						<div style='max-width:350px;margin:0 auto;'>
						<form method='post' id='cform' onsubmit=\"saveinfo(event,'cform')\">
							<input type='hidden' name='updatefields' value='$form1'> <input type='hidden' name='usa' value='$sid'>
							<p style='color:#191970'><b>Company Info</b></p>
							<p>Company Name<br><input type='text' style='width:100%' name='company' value=\"".prepare($config['company'])."\" required></p>
							<p>Main Contact Number<br><input type='text' style='width:100%' onkeyup=\"valid('cont',this.value)\" name='contact' id='cont'
							value=\"".prepare($config['contact'])."\" required></p>
							<p>Email Address<br><input type='email' style='width:100%' name='email' value=\"".prepare($config['email'])."\" required></p>
							<p>Physical Address<br><textarea name='address' class='mssg' required>$address</textarea></p>
							<p style='text-align:right'><button class='btnn'><i class='fa fa-refresh'></i> Save</button></p>
						</form>
						</div>
					</div>
					<div class='col-12 col-lg-6'>
						<div style='max-width:350px;margin:0 auto;'>
						<form method='post' id='smform' onsubmit=\"saveinfo(event,'smform')\">
							<input type='hidden' name='updatefields' value='$form2'> <input type='hidden' name='usa' value='$sid'>
							<p style='color:#191970'><b>Company Logo</b></p>
							<p><label for='logo' style='cursor:pointer'><img src='data:image/jpg;base64,".getphoto($logo)."' style='max-width:100%;max-height:180px'></label><br>
							<i style='font-size:13px;color:grey;'>** Click on Image to Update **</i></p><hr>
							<input type='file' id='logo' accept='image/*' style='display:none' onchange='changelogo()'>
							<p style='color:#191970'><b>SMS API Settings</b></p>
							<p>SMS App Name<br><input type='text' style='width:100%' name='appname' value=\"".prepare($config['appname'])."\" required></p>
							<p>SMS API Key<br><input type='text' style='width:100%' name='apikey' value=\"".prepare($config['apikey'])."\" required></p>
							<p>SMS Sender ID<br><input type='text' style='width:100%' name='senderid' value=\"".prepare($config['senderid'])."\" required></p>
							<p style='text-align:right'><button class='btnn'><i class='fa fa-save'></i> Save</button></p>
						</form>
						</div>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Viewed company Information settings");
	}
	
	# accounting settings
	if(isset($_GET['accounts'])){
		$setts = array("applyrequisition"=>1,"requisitionby"=>json_encode(["Manager"]),"addfloat"=>1,"updatepayroll"=>2,"advanceapplication"=>1,
		"recordpays"=>json_encode(["principal","interest","penalties"]));
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=prepare(trim($row['value'])); }
		}
		
		$app=$setts['applyrequisition']; $pby=json_decode($setts['requisitionby'],1); $float=$setts['addfloat']; $payupdate=$setts['updatepayroll'];
		$adv1 = ($setts['advanceapplication']==1) ? "checked":""; $adv2 = ($setts['advanceapplication']==0) ? "checked":"";
		$ad1 = ($app) ? "checked":""; $ad2 = (!$app) ? "checked":"";
		$adf1 = ($float) ? "checked":""; $adf2 = (!$float) ? "checked":"";
		$defs=$lis1=$lis2=$popts=""; $apps=[]; $not1=$not2=$not3=1;
		
		$sql = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND (`approval`='requisition' OR `approval`='utilities' OR `approval`='advance')");
		if($sql){
			foreach($sql as $row){
				if($row['approval']=="requisition"){ $not1=$row["staff"]; }
				if($row['approval']=="utilities"){ $not2=$row["staff"]; }
				if($row['approval']=="advance"){ $not3=$row["staff"]; }
			}
		}
		
		$usas1=$usas2=$usas3="<option value='0'>-- Notify None --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); 
			$cnd1=($rid==$not1) ? "selected":""; $cnd2=($rid==$not2) ? "selected":""; $cnd3=($rid==$not3) ? "selected":""; 
			$usas1.="<option value='$rid' $cnd1>$name</option>";
			$usas2.="<option value='$rid' $cnd2>$name</option>";
			$usas3.="<option value='$rid' $cnd3>$name</option>";
		}
		
		$qri = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
		if($qri){
			foreach($qri as $row){ $groups[]=$row['sgroup']; }
		}
		else{ $groups = array("manager","accountant"); }
		
		$stages = array(1=>"requisition",2=>"utilities",3=>"budget",4=>"advance");
		foreach($stages as $key=>$stage){
			$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='$stage'");
			$appr = ($res) ? json_decode($res[0]['levels'],1):array(1=>"manager");
			foreach($appr as $no=>$pos){
				$add = ($no==1) ? "<br><a href='javascript:void(0)' onclick=\"addapprow('tbl$key')\" style='float:right'>Add Row</a>":
				"<br><a href='javascript:void(0)' onclick=\"$('#trs$key$no').remove()\" style='float:right;color:#ff4500'>Remove</a>"; 
				$opts = "<option value='0'>-- None --</option>";
				foreach($groups as $grp){
					$cnd=($grp==$pos) ? "selected":"";
					$opts.="<option value='$grp' $cnd>".prepare(ucwords($grp))."</option>";
					$defs.=($no==1 && $key==1) ? "<option value='$grp'>".prepare(ucwords($grp))."</option>":"";
				}
				
				$apps[$key][] = "<tr valign='top' id='trs$key$no'><td>Approval $no</td><td><select name='approvals[]' style='width:100%'>$opts</select> $add</td></tr>";
			}
		}
		
		$lis=$pis=[]; $ptrs="";
		foreach($groups as $grup){
			$cnd=(in_array($grup,$pby)) ? "checked":""; $gname=prepare(ucwords($grup));
			$lis[]="<li style='padding:5px 0px;list-style:none'><input class='qns' type='checkbox' name='reqapply[]' value='$grup' $cnd> &nbsp; $gname</checkbox></li>";
		}
		
		if(count($lis)>3){
			$all =array_chunk($lis,ceil(count($lis)/2));
			$lis1 = implode("",$all[0]);
			$lis2 = implode("",$all[1]);
		}
		else{ $lis1 = implode("",$lis); }
		
		$pays = json_decode($setts['recordpays'],1); $no=0; 
		$pcols = $db->tableFields(2,"paysummary$cid"); $def=["principal","interest"];
		foreach($pcols as $col){
			if(!in_array($col,array("id","paybill",'officer',"penalties","month","year","branch"))){
				$del = (in_array($col,$def)) ? "":"<a href='javascript:void(0)' style='color:#ff4500' onclick=\"delpay('$col')\"><i class='fa fa-times'></i> Remove</a>";
				$tds = (in_array($col,$pays) or in_array($col,$def)) ? $del:"<a href='javascript:void(0)' onclick=\"popupload('accounts/index.php?accpays=$col')\">
				<i class='fa fa-plus'></i> Include</a>"; $no++;
				$ptrs.="<tr><td>$no. ".ucwords(str_replace("_"," ",$col))."</td><td>$tds</td></tr>";
			}
		}
		
		$parr = array("Only for Current Month","Upto previous month","Upto last 2 months","Upto last 3 months","Upto last 6 months","Whole period");
		foreach($parr as $key=>$one){
			$cnd = ($key==$payupdate) ? "selected":"";
			$popts.="<option value='$key' $cnd>$one</option>";
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<textarea id='defs' style='display:none'>$defs</textarea> 
				<input type='hidden' id='apps' value='".count($appr)."'>
				<h3 style='font-size:22px;'>$backbtn Accounts Settings 
				<button class='bts' style='float:right;font-size:13px;' onclick=\"loadpage('setup.php?reqstructure')\"><i class='fa fa-wrench'></i> Requisition Form</button></h3><hr>
				<div class='row'>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<table style='width:100%;max-width:350px;margin:0 auto' cellpadding='5' cellspacing='0'>
							<tr><td colspan='2'>Requisition Application</td></tr>
							<tr><td><input type='radio' name='check1' value='1' onchange=\"savesetting('applyrequisition:1')\" $ad1> &nbsp; Enabled</radio></td>
							<td><input type='radio' name='check1' value='0' onchange=\"savesetting('applyrequisition:0')\" $ad2> &nbsp; Disabled</radio></td></tr>
							<tr><td colspan='2'><hr>Topup Pettycash float manually</td></tr>
							<tr><td><input type='radio' name='check2' value='1' onchange=\"savesetting('addfloat:1')\" $adf1> &nbsp; Enabled</radio></td>
							<td><input type='radio' name='check2' value='0' onchange=\"savesetting('addfloat:0')\" $adf2> &nbsp; Disabled</radio></td></tr>
							<tr><td colspan='2'><hr>Requisition to be applied by</td></tr>
							<tr><td>$lis1</td><td>$lis2</td></tr>
							<tr><td colspan='2' style='text-align:right'><button onclick='savereqn()' class='btnn' style='min-width:60px;padding:4px;'>Update</button></td></tr>
							<tr><td colspan='2'><hr>
								<p style='font-weight:bold;margin-bottom:5px'>Requisition Approvals</p>
								<form method='post' id='rform' onsubmit=\"saveapproval(event,'rform','requisition','accounts')\">
									<input type='hidden' name='apptype' value='requisition'>
									<table class='tbl1' style='width:100%;max-width:350px;' cellpadding='5' cellspacing='0'>
										<caption>Notify staff below upon all approvals<br><select style='width:60%' name='notify'>$usas1</select>
										<button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>".implode("",$apps[1])."
									</table>
								</form>
							</td></tr>
							<tr><td colspan='2'><hr>
								<p style='font-weight:bold;margin-bottom:5px'>Advance Application</p>
								<form method='post' id='aform' onsubmit=\"saveapproval(event,'aform','Advance application','accounts')\">
									<input type='hidden' name='apptype' value='advance'>
									<table class='tbl4' style='width:100%;max-width:350px;' cellpadding='5' cellspacing='0'>
										<caption>Notify staff below upon all approvals<br><select style='width:60%' name='notify'>$usas3</select>
										<button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>
										<tr><td><input type='radio' name='check2' value='1' onchange=\"savesetting('advanceapplication:1')\" $adv1> &nbsp; Enabled</radio></td>
										<td><input type='radio' name='check2' value='0' onchange=\"savesetting('advanceapplication:0')\" $adv2> &nbsp; Disabled</radio></td></tr>
										".implode("",$apps[4])."
									</table>
								</form>
							</td></tr>
						</table><br>
					</div>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<p style='font-weight:bold;margin-bottom:5px'>Utility Expense Approvals</p>
						<form method='post' id='uform' onsubmit=\"saveapproval(event,'uform','utility expense')\">
							<input type='hidden' name='apptype' value='utilities'>
							<table class='tbl2' style='width:100%;max-width:350px;' cellpadding='5' cellspacing='0'>
								<caption>Notify staff below upon all approvals<br><select style='width:60%' name='notify'>$usas2</select>
								<button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>".implode("",$apps[2])."
							</table>
						</form><hr>
						<p style='font-weight:bold;margin-bottom:5px'>Budget Approvals</p>
						<form method='post' id='bform' onsubmit=\"saveapproval(event,'bform','budget')\">
							<input type='hidden' name='apptype' value='budget'> <input type='hidden' name='notify' value='0'>
							<table class='tbl2' style='width:100%;max-width:350px;' cellpadding='5' cellspacing='0'>
								<caption><button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>".implode("",$apps[3])."
							</table>
						</form><hr>
						<p>Allow Editing of Payroll:<br><select onchange=\"savesetting('updatepayroll:'+this.value)\" style='max-width:100%'>$popts</select></p>
						<p style='font-weight:bold;margin:0px'>Payments Inclusion in Accounts</p>
						<p style='color:grey;font-size:14px'>Select payments to include in Accounting books</p> 
						<table style='width:100%;max-width:350px;' cellpadding='3'> $ptrs </table>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed accounting settings"); 
	}
	
	# requisition structure
	if(isset($_GET['reqstructure'])){
		$db = new DBO();
		$rtbl = "requisitions$cid";
		$exclude = array("id","staff","branch","approvals","approved","month","status","time");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$rtbl'");
		$def = array("id"=>"number","staff"=>"number","branch"=>"number","item_description"=>"textarea","cost"=>"number","approvals"=>"textarea",
		"approved"=>"text","month"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.=(!in_array($type,array("image","docx","pdf"))) ? "<option value='$type'>$desc</option>":"";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$rtbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp=($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.="<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Requisition structure Setup</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$rtbl:3'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'>
						<caption style='caption-side:top'>Create requisition Form structure</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed requisition structure settings");
	}
	
	# loan settings
	if(isset($_GET['loansett'])){
		$data = array("checkoff"=>0,"loansegment"=>1,"reschedule"=>1,"reschedulefees"=>0,"rescheduledays"=>28,"smsamount"=>2,"applyarrears"=>1,"loan_approval_sms"=>1,
		"installmentdate"=>1,"clientdormancy"=>0,"loan_approval_otp"=>1,"loan_approval_otp"=>1,"loanposting"=>0,"template_phone_include"=>0,"autoisntallment_sms"=>0,
		"autosms_time"=>"04:00","loantype_option"=>1,"multidisburse"=>0,"allow_staff_loans"=>0,"intrcollectype"=>"Accrued","closing_loan_fees"=>0,"guarantor_consent_by"=>"",
		"guarantor_consent_phone"=>"","threshold_approver"=>"");
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $data[$row['setting']]=$row['value']; }
		}
		
		$sma1 = ($data['smsamount']==1) ? "checked":""; $sma2 = ($data['smsamount']==2) ? "checked":"";
		$suto1 = ($data['autoisntallment_sms']==0) ? "checked":""; $suto2 = ($data['autoisntallment_sms']==1) ? "checked":"";
		$resfee = $data['reschedulefees']; $instdy=$data['installmentdate'];  $rdays=$data['rescheduledays']; $smstm=$data["autosms_time"];
		$appr1=$appr2=$defs=$dpts=$cdms=$alis=$td1=$td2=$td3=$td4=$td5=$td6=$td7=$td8=$td9=$td10=$td11=$td12=$td13=$td14=$td0=$gcols=$lspec="";
	
		$qri = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
		if($qri){
			foreach($qri as $row){ $groups[]=$row['sgroup']; }
		}
		else{ $groups = array("manager","accountant"); }
		
		$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
		$appr = ($res) ? json_decode($res[0]['levels'],1):array(1=>"manager"); 
		$glis = "<option value='none'>No Callbacks</option>";
		$sops = "<option value='none'>None</option>"; $spamt=0;
		if($data["threshold_approver"]){
			$ldf=explode("%",$data["threshold_approver"]); $lspec=$ldf[0]; $spamt=$ldf[1];
		}
		
		foreach($appr as $no=>$pos){
			$add = ($no==1) ? "<br><a href='javascript:void(0)' onclick=\"addapprow('atbl')\" style='float:right'>Add Row</a>":
			"<br><a href='javascript:void(0)' onclick=\"$('#trs$no').remove()\" style='float:right;color:#ff4500'>Remove</a>";
			$opts = "<option value='0'>-- None --</option>";
			foreach($groups as $grp){
				$cnd=($grp==$pos) ? "selected":"";
				$opts.="<option value='$grp' $cnd>".prepare(ucwords($grp))."</option>";
				$defs.=($no==1) ? "<option value='$grp'>".prepare(ucwords($grp))."</option>":"";
			}
			
			$cnd = ($pos==$data["guarantor_consent_by"]) ? "selected":""; $cnd2=($pos==$lspec) ? "selected":"";
			$glis.= "<option value='$pos' $cnd>".prepare(ucwords($pos))."</option>";
			if($no>1){ $sops.="<option value='$pos' $cnd2>".prepare(ucwords($pos))."</option>"; }
			$appr1.= "<tr valign='top' id='trs$no'><td>Approval $no</td><td><select name='approvals[]' style='width:100%'>$opts</select> $add</td></tr>";
		}
		
		$sql = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
		$appr = ($sql) ? json_decode($sql[0]['levels'],1):array(1=>"manager");
		foreach($appr as $no=>$pos){
			$add = ($no==1) ? "<br><a href='javascript:void(0)' onclick=\"addapprow('etbl')\" style='float:right'>Add Row</a>":
			"<br><a href='javascript:void(0)' onclick=\"$('#strs$no').remove()\" style='float:right;color:#ff4500'>Remove</a>";
			$opts = "<option value='0'>-- None --</option>";
			foreach($groups as $grp){
				$cnd = ($grp==$pos) ? "selected":"";
				$opts.= "<option value='$grp' $cnd>".prepare(ucwords($grp))."</option>";
			}
			
			$appr2.="<tr valign='top' id='strs$no'><td style='width:50%'>Approval $no</td><td><select name='approvals[]' style='width:100%'>$opts</select> $add</td></tr>";
		}
		
		$def = "Dear CLIENT,\r\nKindly make your Installment of Ksh AMOUNT by DATE before 12 noon to avoid being a defaulter.\r\nThank you.";
		$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND `name`='inst200'");
		$sms = ($res) ? prepare($res[0]['message']):$def; $lnot=$snot=1; $cfee=$data["closing_loan_fees"];
		
		$sql = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid' AND (`approval`='loantemplate' OR `approval`='stafftemplate')");
		if($sql){
			foreach($sql as $row){
				if($row['approval']=="loantemplate"){ $lnot=$row['staff']; }
				if($row['approval']=="stafftemplate"){ $snot=$row['staff']; }
			}
		}
		
		$usas1 = "<option value='0'>-- Notify None --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); $cnd=($rid==$lnot) ? "selected":"";
			$usas1.="<option value='$rid' $cnd>$name</option>";
		}
		
		$usas2 = "<option value='0'>-- Notify None --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); $cnd=($rid==$snot) ? "selected":"";
			$usas2.="<option value='$rid' $cnd>$name</option>";
		}
		
		foreach(array("Day before","Exact date","Day after") as $key=>$dy){
			$cnd = ($key==$instdy) ? "selected":"";
			$dpts.="<option value='$key' $cnd>$dy</option>";
		}
		
		$cdms = "<option value='0'>-- No Period --</option>";
		for($i=2; $i<=12; $i++){
			$cnd = ($i==$data['clientdormancy']) ? "selected":"";
			$cdms.="<option value='$i' $cnd>$i Months</option>";
		}
		
		foreach(array("Accrued","Cash") as $val){
			$cnd = ($val==$data['intrcollectype']) ? "checked":"";
			$td0.= "<td><input type='radio' name='check1' value='$val' onchange=\"savesetting('intrcollectype:$val')\" $cnd> &nbsp; $val Basis</radio><hr style='margin-bottom:5px'></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['checkoff']) ? "checked":"";
			$td1.= "<td><input type='radio' name='check2' value='$key' onchange=\"savesetting('checkoff:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['loansegment']) ? "checked":"";
			$td2.= "<td><input type='radio' name='check3' value='$key' onchange=\"savesetting('loansegment:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['applyarrears']) ? "checked":"";
			$td3.= "<td><input type='radio' name='check4' value='$key' onchange=\"savesetting('applyarrears:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Switch off OTP","Require OTP") as $key=>$val){
			$cnd = ($key==$data['loan_approval_otp']) ? "checked":"";
			$td4.= "<td><input type='radio' name='check5' value='$key' onchange=\"savesetting('loan_approval_otp:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['loan_approval_sms']) ? "checked":"";
			$td5.= "<td><input type='radio' name='check6' value='$key' onchange=\"savesetting('loan_approval_sms:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['reschedule']) ? "checked":"";
			$td6.= "<td><input type='radio' name='check8' value='$key' onchange=\"savesetting('reschedule:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Manual posting","Automatic posting") as $key=>$val){
			$cnd = ($key==$data['loanposting']) ? "checked":"";
			$td7.= "<td><input type='radio' name='loanposting' value='$key' onchange=\"savesetting('loanposting:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disable Editing","Enable Editing") as $key=>$val){
			$cnd = ($key==$data['template_phone_include']) ? "checked":"";
			$td8.= "<td><input type='radio' name='template_phone_include' value='$key' onchange=\"savesetting('template_phone_include:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array(1=>"Full Installment",2=>"Installment Balance") as $key=>$val){
			$cnd = ($key==$data['smsamount']) ? "checked":"";
			$td9.= "<td><input type='radio' name='smsamount' value='$key' onchange=\"savesetting('smsamount:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Allowed") as $key=>$val){
			$cnd = ($key==$data['autoisntallment_sms']) ? "selected":"";
			$td10.= "<option value='$key' $cnd>$val</option>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['loantype_option']) ? "checked":"";
			$td11.= "<td><input type='radio' name='loantype_option' value='$key' onchange=\"savesetting('loantype_option:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Disabled","Enabled") as $key=>$val){
			$cnd = ($key==$data['multidisburse']) ? "checked":"";
			$td12.= "<td><input type='radio' name='multidisburse' value='$key' onchange=\"savesetting('multidisburse:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Hide","Show") as $key=>$val){
			$cnd = ($key==$data['show_coll_rates']) ? "checked":"";
			$td13.= "<td><input type='radio' name='show_coll_rates' value='$key' onchange=\"savesetting('show_coll_rates:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		foreach(array("Suspended","Allowed") as $key=>$val){
			$cnd = ($key==$data['allow_staff_loans']) ? "checked":"";
			$td14.= "<td><input type='radio' name='allow_staff_loans' value='$key' onchange=\"savesetting('allow_staff_loans:$key')\" $cnd> &nbsp; $val</radio><hr></td>";
		}
		
		$gcols = "<option value='0'>-- Select --</option>";
		$skip = ["id","cuts","time","pref","prepay","checkoff","client","comments","processing","loantype","payment","status","creator","approvals","disbursed","loan",
		"branch","loan_officer","loan_product","client_idno","amount","phone","duration",""];
		foreach($db->tableFields(2,"org$cid"."_loantemplates") as $col){
			if(!in_array($col,$skip)){
				$cnd = ($col==$data["guarantor_consent_phone"]) ? "selected":"";
				$gcols.= "<option value='$col' $cnd>".ucwords(str_replace("_"," ",$col))."</option>";
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<textarea id='defs' style='display:none'>$defs</textarea> 
				<input type='hidden' id='apps' value='".count($appr)."'>
				<h3 style='font-size:22px;'>$backbtn Loan Settings 
				<button class='bts' style='float:right' onclick=\"loadpage('setup.php?loanlimit')\"><i class='bi-bookmark-check'></i> Loan Limits</button></h3><hr>
				<div class='row'>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<form method='post' id='aform' onsubmit=\"saveapproval(event,'aform','Client Loan template','loansett')\">
							<input type='hidden' name='apptype' value='loantemplate'>
							<table class='atbl' style='width:100%;max-width:350px;margin:0 auto' cellpadding='5' cellspacing='0'>
								<caption>Notify staff below upon all approvals<br><select style='width:50%' name='notify'>$usas1</select>
								<button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>
								<tr><td colspan='2'>Interest Collection Type</td></tr> <tr>$td0</tr>
								<tr><td colspan='2'>Closing Loan Charges</td></tr> <tr><td colspan='2'><input type='text' value='$cfee' id='cfee'
								style='width:150px;padding:3px 5px' onkeyup=\"valid('cfee',this.value)\" onchange=\"savesetting('closing_loan_fees:'+this.value)\">
								<hr style='margin-bottom:5px'></td></tr>
								<tr><td colspan='2'>Allow Loan checkoff/Topup for Clients</td></tr> <tr>$td1</tr>
								<tr><td colspan='2'>Edit phone during template creation?</td></tr> <tr>$td8</tr>
								<tr><td colspan='2'>Segment Loan duration based on Interest duration?</td></tr> <tr>$td2</tr>
								<tr><td colspan='2'>Apply Penalty Fee for arrears Daily</td></tr> <tr>$td3</tr>
								<tr><td colspan='2'>Selection of Loan Type on loantemplate</td></tr> <tr>$td11</tr>
								<tr><td colspan='2'>Manually confirming Disbursed Loans</td></tr> <tr>$td12</tr>
								<tr><td colspan='2'>Show Collection Rates</td></tr> <tr>$td13</tr>
								<tr><td colspan='2'><p style='font-weight:bold;margin-bottom:6px'>Loan Template Approvals</p></td></tr> <tr>$td4</tr>
								<tr><td colspan='2'>Disbursed Loans posting type</td></tr> <tr>$td7</tr>
								<tr><td colspan='2'>Notify next approvie in the queue</td></tr> <tr>$td5</td></tr> $appr1
							</table><br>
						</form>
					</div>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<p style='font-weight:bold;margin-bottom:5px'>Threshhold Approver</p>
						<p>Set special loan approver for huge amounts as you stipulate</p>
						<table style='width:100%' cellpadding='5'><tr>
							<td style='width:30%'>Amounts >=<br><input type='number' style='width:100%;padding:4px' id='spam' value='$spamt'></td>
							<td style='width:45%'>Approval By<br><select id='spac' style='width:100%'>$sops</select></td>
							<td><br><button style='float:right;padding:5px;min-width:60px' onclick=\"setspecapprover()\" class='btnn'>Save</button></td>
						</tr></table><br>
						<p style='font-weight:bold;margin-bottom:5px'>Guarantor Consent</p>
						<p>Allow callbacks done to Guarantor upon Loan Application</p>
						<table style='width:100%' cellpadding='5'><tr>
							<td style='width:50%'>Approval By<br><select style='width:100%' onchange=\"savesetting('guarantor_consent_by:'+this.value)\">$glis</select></td>
							<td>Contact Field<br><select style='width:100%' onchange=\"savesetting('guarantor_consent_phone:'+this.value)\">$gcols</select></td>
						</tr></table><br>
						<p style='font-weight:bold;margin-bottom:5px'>Installment Reminder SMS</p>
						<form method='post' id='sform'> <p style='margin:0px'>
						<textarea name='smsmssg' class='mssg' style='max-width:350px;height:120px;font-size:14px' onchange='savesms()'>$sms</textarea></p>
						</form>
						<p style='margin-top:10px'>If DATE is included above, Pick:<br>
						<select style='width:150px;cursor:pointer' onchange=\"savesetting('installmentdate:'+this.value)\">$dpts</select></p>
						<table style='width:100%' cellpadding='5' cellspacing='0'>
							<tr><td colspan='2'>If AMOUNT is included, Pick:</td></tr> <tr>$td9</tr>
							<tr><td colspan='2'>Automatically send SMS Everyday at</td></tr> 
							<tr><td><select style='width:150px' onchange=\"savesetting('autoisntallment_sms:'+this.value)\">$td10</select><hr></td>
							<td><input type='time' onchange=\"savesetting('autosms_time:'+this.value)\" style='max-width:100%;padding:3px 7px' value='$smstm'><hr></td></tr>
						</table>
						<p style='font-weight:bold;margin-bottom:5px'>Loan Re-scheduling & Client Dormancy</p>
						<table style='width:100%' cellpadding='5' cellspacing='0'>
							<tr><td colspan='2'>Allow Loan Re-scheduling</td></tr> <tr>$td6</tr>
							<tr><td>Maximum Days<br><input type='number' value='$rdays' style='max-width:120px'
							onchange=\"savesetting('rescheduledays:'+this.value.trim())\"></td><td>Additional Fees<br>
							<input type='text' style='max-width:120px' value='$resfee' id='adf' onkeyup=\"valid('adf',this.value)\"
							onchange=\"savesetting('reschedulefees:'+this.value.trim())\"></td></tr>
							<tr><td colspan='2'><br>Treat a client as <b>New client</b> after dormancy period of:</td></tr>
							<tr><td colspan='2'><select style='width:150px' onchange=\"savesetting('clientdormancy:'+this.value)\">$cdms</select></td></tr>
						</table><hr>
						<p style='font-weight:bold;margin-bottom:5px'>Staff Loans</p>
						<form method='post' id='jform' onsubmit=\"saveapproval(event,'jform','Staff Loan template','loansett')\">
							<input type='hidden' name='apptype' value='stafftemplate'>
							<table class='etbl' style='width:100%;max-width:400px;margin:0 auto' cellpadding='5' cellspacing='0'>
								<caption>Notify staff below upon all approvals<br><select style='width:50%' name='notify'>$usas2</select>
								<button class='btnn' style='min-width:60px;padding:4px;float:right'>Update</button></caption>
								<tr><td colspan='2'>Allow Staff Loan Application</td></tr> <tr>$td14</tr>
								<tr><td colspan='2'>Staff Loan Approvals</td></tr> $appr2
							</table>
						</form>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed Loan settings form");
	}
	
	# loan limits
	if(isset($_GET['loanlimit'])){
		$res = $db->query(1,"SELECT *FROM `loan_limits` WHERE `client`='$cid'");
		$data = ($res) ? json_decode($res[0]['setting'],1):[]; $bypass = ($res) ? json_decode($res[0]['bypass'],1):[]; 
		$state = ($res) ? $res[0]['status']:0; $trs=$lis=$usas=""; $no=0;
		
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='client' AND `status`='0' ORDER BY `maxamount` ASC");
		if($qri){
			foreach($qri as $key=>$row){
				$prod=prepare(ucwords($row['product'])); $min=number_format($row['minamount']); 
				$max=number_format($row['maxamount']); $pid=$row['id']; $opts=""; $no++;
				$cycle = (array_key_exists($pid,$data)) ? $data[$pid]['cycles']:$key+1;
				
				for($i=1; $i<=count($qri); $i++){
					if(array_key_exists($pid,$data)){
						$cnd = ($i==$data[$pid]['order']) ? "selected":"";
						$opts.="<option value='$i' $cnd>Level $i</option>";
					}
					else{ $opts.="<option value='$i'>Level $i</option>"; }
				}
				
				$trs.="<tr valign='top'><td>$no</td><td>$prod<br>$min - $max</td><td><select name='order[$pid]' style='width:100px'>$opts</select></td>
				<td><input type='number' style='width:100px;padding:4px' name='cycles[$pid]' value='$cycle' required></td></tr>";
			}
		}
		
		foreach(array("Suspended","Active") as $key=>$one){
			$cnd = ($key==$state) ? "selected":"";
			$lis.="<option value='$key' $cnd>$one</option>";
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'");
		$add = ($qri) ? "AND NOT `position`='".$qri[0]['value']."'":"";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' AND NOT `position`='USSD' $add");
		foreach($res as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); $cnd=(in_array($rid,$bypass)) ? "checked":""; 
			$usas.="<tr><td style='width:30px'><input type='checkbox' name='limbypass[]' value='$rid' $cnd></td><td>$name</td></tr>";
		}
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='color:#191970;font-size:22px'>$backbtn Loan Limit Setup</h3>
				<div class='row'>
					<div class='col-12 col-lg-7 mt-3'>
						<div style='width:100%;overflow:auto'>
							<form method='post' id='limfom' onsubmit='savelimit(event)'>
								<table style='width:100%;min-width:500px' cellpadding='10' class='table-striped'>
									<tr style='font-weight:bold;color:#191970;background:#e6e6fa;font-size:15px'><td colspan='2'>Loan Product</td>
									<td>Graduation Level</td><td>Cycles</td></tr> 
									<tr><td colspan='2'></td><td><span style='font-size:14px;color:grey'>Level of Switching from one loan to next</span></td>
									<td style='font-size:14px;color:grey;'>No of Cycles that needs to be completed before Graduation</td></tr> $trs
									<tr><td colspan='2'>Status:<br> <select name='limstate' style='width:130px'>$lis</select></td>
									<td colspan='2' style='text-align:right'><br><button class='btnn' style='padding:5px'>Update</button></td></tr>
								</table>
							</form>
						</div>
					</div>
					<div class='col-12 col-lg-5 mt-3'>
						<p style='color:#191970;font-weight:bold'>Limit Bypass</p>
						<p style='color:grey'>Setup Users who will bypass this condition upon template creation if there is need for a special client</p>
						<form method='post' id='bform' onsubmit='savebypass(event)'>
							<table style='width:100%' cellpadding='4'> $usas 
							<tr><td colspan='2'><hr><button class='btnn' style='padding:5px;float:right'>Update</button></td></tr></table>
						</form>
					</div>
				</div>
			</div>
		</div>";
	}
	
	# system settings
	if(isset($_GET['gensett'])){
		$pdef = array("cycle_points"=>10,"loanamt_point"=>1000,"timely_pay"=>1,"points_award"=>50,"late_pay"=>0);
		$data = array("autoapprovepays"=>0,"docprotection"=>0,"receiptlogo"=>1,"leaveapplication"=>1,"protectpass"=>"@contact","client_awarding"=>json_encode($pdef,1),
		"application_status"=>0,"app_loanterms"=>"","app_limit_from_previous"=>0,"use_app_as_topup"=>0,"penalty_from_day"=>0,"allocate_arrears"=>0);
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $data[$row['setting']]=$row['value']; }
		}
		
		$ck1 = ($data['autoapprovepays']==1) ? "checked":""; $ck2 = ($data['autoapprovepays']==0) ? "checked":"";
		$ls1 = ($data['docprotection']==1) ? "checked":""; $ls2 = ($data['docprotection']==0) ? "checked":"";
		$sm1 = ($data['receiptlogo']==1) ? "checked":""; $sm2 = ($data['receiptlogo']==0) ? "checked":"";
		$lv1 = ($data['leaveapplication']==1) ? "checked":""; $lv2 = ($data['leaveapplication']==0) ? "checked":"";
		$protect=prepare($data['protectpass']); $award=json_decode($data["client_awarding"],1); $defs=$alis=$apls=$mls=$lis=$ops=""; 
		$latepy=(isset($data["late_pay"])) ? $data["late_pay"]:0; $terms=(isset($data["app_loanterms"])) ? prepare($data["app_loanterms"]):"";
		$systym = (isset($data["systime"])) ? json_decode($data["systime"],1):[];
		$sfro = (isset($systym["from"])) ? $systym["from"]:""; $sopts="";
		$sysac = (isset($systym["restrict"])) ? $systym["restrict"]:0;
		$supto = (isset($systym["upto"])) ? $systym["upto"]:"";
		
		foreach(array("Not Restricted","Restricted") as $key=>$txt){
			$cnd = ($key==$sysac) ? "selected":"";
			$sopts.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("Suspended","Active") as $key=>$txt){
			$cnd = ($key==$data["application_status"]) ? "selected":"";
			$alis.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("No","Yes") as $key=>$txt){
			$cnd = ($key==$data["app_limit_from_previous"]) ? "selected":"";
			$apls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("New Loans","Loan Topup") as $key=>$txt){
			$cnd = ($key==$data["use_app_as_topup"]) ? "selected":"";
			$mls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		$const = sys_constants();
		$list = array(
			"overpayment_to_wallet"=>"Direct overpayments to Transactional Account","payment_alert_sms"=>"Send Balance SMS upon payment approvals","wallet_smsnots"=>
			"Notify Holder Accounts upon wallet debits/credits","wallet_revtrans_time"=>"Disable Wallet tansactions reversal after how many days",
			"partial_disbursement"=>"Allow Loans Partial disbursements","min_offset_fees"=>"Minimum Offset fee amount","check_guarantors"=>"Notify upon Guarantor Loan Application Via",
			"no_guaranteeing_eachother"=>"Block Loan application From a Guarantor","template_notify_approver"=>"Notify approver upon loan Application creation",
			"penalty_from_day"=>"Days to waive penalty application when its due","allocate_arrears"=>"Automatically alocate Loan to Collection Agent",
			"enable_app_withdrawals"=>"Enable Account Withdrawals via Mobile App","c2b_one_acc"=>"System Paybill that is One account","operate_group_loans"=>"Operate with Group Loans",
			"non_performing_loan_days"=>"Arrears days to treat loan as Non Performing","wallets_disburse"=>"Allow Disbursement to holders wallet Accounts"
		);
		
		foreach(array("wallet_revtrans_time"=>3) as $key=>$v){
			if(!isset($const[$key])){ $const[$key]=$v; }
		}
		
		foreach($list as $key=>$txt){
			if($key=="allocate_arrears"){
				for($i=0; $i<=14; $i++){
					$cnd = ($i==$data["allocate_arrears"]) ? "selected":""; $dtx=($i) ? "$i Days Overdue":"Disabled";
					$ops.= "<option value='$i' $cnd>$dtx</option>";
				}
				$lis.= "<tr valign='top'><td>$txt</td><td style='text-align:right'><select style='width:110px;font-size:15px' name='setts[$key]'>$ops</select></td></tr>"; $ops="";
			}
			elseif($key=="penalty_from_day"){
				for($i=0; $i<=14; $i++){
					$cnd = ($i==$data["penalty_from_day"]) ? "selected":"";
					$ops.= "<option value='$i' $cnd>$i Days</option>";
				}
				$lis.= "<tr valign='top'><td>$txt</td><td style='text-align:right'><select style='width:100px;font-size:15px' name='setts[$key]'>$ops</select></td></tr>"; $ops="";
			}
			elseif(in_array($key,["c2b_one_acc","min_offset_fees","non_performing_loan_days","wallet_revtrans_time"])){
				$val = (isset($const[$key])) ? $const[$key]:0;
				$lis.= "<tr valign='top'><td>$txt</td><td style='text-align:right'><input type='number' style='width:100px;font-size:15px' 
				name='sconst[$key]' value='$val' required></td></tr>";
			}
			elseif($key=="check_guarantors"){
				$cols = $db->tableFields(2,"org$cid"."_loantemplates");
				$ops = "<option value='0'>Disabled</option>"; $val=(isset($const[$key])) ? $const[$key]:0;
				$skip = ["id","cuts","time","pref","prepay","checkoff","client","comments","processing","loantype","payment","status","creator","approvals","disbursed","loan",
				"branch","loan_officer","loan_product","client_idno","amount","phone","duration",""];
				foreach($cols as $col){
					if(!in_array($col,$skip)){
						$cnd = ($col==$val) ? "selected":"";
						$ops.= "<option value='$col' $cnd>".ucwords(str_replace("_"," ",$col))."</option>";
					}
				}
				$lis.= "<tr valign='top'><td>$txt</td><td style='text-align:right'><select style='width:110px;font-size:15px' name='sconst[$key]'>$ops</select></td></tr>"; $ops="";
			}
			else{
				foreach(array("No","Yes") as $k=>$v){
					$val=(isset($const[$key])) ? $const[$key]:0; $cnd=($k==$val) ? "selected":"";
					$ops.= "<option value='$k' $cnd>$v</option>";
				}
				$lis.= "<tr valign='top'><td>$txt</td><td style='text-align:right'><select style='width:100px;font-size:15px' name='sconst[$key]'>$ops</select></td></tr>"; $ops="";
			}
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<textarea id='defs' style='display:none'>$defs</textarea> 
				<h3 style='font-size:22px;'>$backbtn General System Settings</h3><hr>
				<div class='row'>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<table style='width:100%;max-width:500px;margin:0 auto' cellpadding='5' cellspacing='0'>
							<tr><td colspan='2'>Make client payments to be approved automatically</td></tr>
							<tr><td><input type='radio' name='check1' value='1' onchange=\"savesetting('autoapprovepays:1')\" $ck1> &nbsp; Enabled</radio></td>
							<td><input type='radio' name='check1' value='0' onchange=\"savesetting('autoapprovepays:0')\" $ck2> &nbsp; Disabled</radio></td></tr>
							<tr><td colspan='2'><hr>Generated Document Encryption (PDF & Excel)</td></tr>
							<tr><td><input type='radio' name='check2' value='1' onchange=\"savesetting('docprotection:1')\" $ls1> &nbsp; Enable Protection</radio></td>
							<td><input type='radio' name='check2' value='0' onchange=\"savesetting('docprotection:0')\" $ls2> &nbsp; Disable Protection</radio></td></tr>
							<tr><td colspan='2'><span style='font-size:13px'>Lock Password (Use @contact or @email to pick Logged-in user contact or email
							as password to open the document)</span><br><input type='text' style='max-width:200px;padding:4px' value=\"$protect\"
							onblur=\"savesetting('protectpass:'+this.value)\"></td></tr>
							<tr><td colspan='2'><hr>Include Company Logo on Receipts & Payroll</td></tr>
							<tr><td><input type='radio' name='check3' value='1' onchange=\"savesetting('receiptlogo:1')\" $sm1> &nbsp; Include Logo</radio></td>
							<td><input type='radio' name='check3' value='0' onchange=\"savesetting('receiptlogo:0')\" $sm2> &nbsp; Don't Include</radio></td></tr>
							<tr><td colspan='2' style='font-weight:bold'><hr>System Constants</td></tr>
							<tr><td colspan='2'><br>
								<form method='post' id='cvfom' onsubmit=\"saveconst(event)\">
									<table style='width:100%' cellpadding='5' class='table'>$lis</table>
									<p style='text-align:right'><button class='btnn' style='padding:5px'>Update</button></p>
								</form>
							</td></tr>
						</table><br>
					</div>
					<div class='col-12 col-md-6 col-lg-6 mb-3'>
						<div style='width:100%;overflow:auto'>
							<table style='width:100%;max-width:400px;' cellpadding='5' cellspacing='0'>
								<tr><td colspan='2' style='font-weight:bold'>System Access Hours</td></tr>
								<tr><td colspan='2'><div style='width:100%;overflow:auto'>
									<form method='post' id='stfom' onsubmit=\"setsystime(event)\"><table style='width:100%' cellpadding='4'>
									<tr valign='bottom'><td style='width:50%'>Restrict Working Hours<br><select style='padding:7px;width:100%' name='systime[restrict]'>$sopts</Select></td>
									<td>System Access Starts at<br><input type='time' name='systime[from]' value='$sfro' style='padding:4px;width:100%' required></td></tr>
									<tr><td>System Access Suspended at<br><input type='time' name='systime[upto]' value='$supto' style='padding:4px;width:100%' required></td>
									<td style='text-align:right'><br><button class='btnn'>Save</button></td></tr>
								</table></form></div></td></tr>
								<tr><td colspan='2' style='font-weight:bold'><hr>Higher Level Approvals</td></tr>
								<tr><td colspan='2' style='font-size:15px'><p>Setup approval for all those who approve advance, requisition and Leaves when they apply for the same.</p>
								<p style='text-align:right'><span class='bts' onclick=\"popupload('setup.php?hlapprove')\" style='cursor:pointer'>
								<i class='fa fa-cogs'></i> Setup Approvals</span></p></td></tr>
								<tr><td colspan='2' style='font-weight:bold'><hr>Client Loyalty Points & App Settings</td></tr>
								<tr><td colspan='2'><form method='post' id='aform' onsubmit=\"saveawarding(event)\">
									<table cellpadding='5' style='width:100%'>
									<tr><td>Points for Completed Loan Cycle</td><td style='width:90px'><input type='number' name='awards[cycle_points]' value='".$award["cycle_points"]."' 
									style='width:100%' required></td></tr><tr><td>How much amount borrowed transalates to 1 point Earning?</td><td style='width:90px'>
									<input type='number' name='awards[loanamt_point]' style='width:100%' value='".$award["loanamt_point"]."' required></td></tr>
									<tr><td>Points Earned for timely payment</td><td style='width:90px'><input type='number' name='awards[timely_pay]' value='".$award["timely_pay"]."'
									style='width:100%' required></td></tr> <tr><td>Points deducted for late payment</td><td style='width:90px'>
									<input type='number' name='awards[late_pay]' value='$latepy' style='width:100%' required></td></tr>
									<tr><td>Amount to borrow for each point earned</td><td style='width:90px'><input type='number' 
									name='awards[points_award]' value='".$award["points_award"]."' style='width:100%' required></td></tr>
									<tr><td>Compare App Limit from Previous Loan amount</td><td><select style='width:100%' name='applimit'>$apls</select></td></tr>
									<tr><td>Treat Mobile App Loans as</td><td><select style='width:110px;font-size:15px' name='apptop'>$mls</select></td></tr>
									<tr><td colspan='2'><hr><b>Loan Application Terms & Conditions</b></td></tr>
									<tr><td colspan='2' style='max-width:500px'><input type='hidden' id='lntms' value='$terms' name='lterms'>
									<trix-editor input='lntms' style='max-height:400px;overflow:auto' class='trix-content'></trix-editor></td></tr>
									<tr><td><br>Status: <select style='width:120px' name='awardst'>$alis</select></td>
									<td style='text-align:right'><br><button class='btnn' style='min-width:70px;padding:5px;'>Update</button></td></tr>
								</table></form></td></tr>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed general system settings form");
	}
	
	# high level approvals
	if(isset($_GET['hlapprove'])){
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		
		if(in_array("configure system",$perms)){
			$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
			$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND NOT `type`='loantemplate' AND NOT `type`='leave'");
			$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'");
			$def = ($qri) ? json_decode($qri[0]['levels'],1):[]; $grps=[]; $trs="";
			$offgp = ($qry) ? $qry[0]['value']:"";
		
			if($res){
				$sql = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
				if($sql){
					foreach($sql as $row){ $grps[]=$row['sgroup']; }
				}
				
				foreach($res as $key=>$row){
					$tp = $row['type']; $levels = json_decode($row['levels'],1); $tds = "";
					if($tp!="superlevels"){
						foreach($levels as $lv){
							$prev = (array_key_exists($tp,$def)) ? $def[$tp]:[]; $opts = ""; 
							foreach($grps as $grp){
								if($grp!=$lv && $grp!=$offgp){
									$val = (array_key_exists($lv,$prev)) ? $prev[$lv]:""; 
									$cnd = ($val==$grp) ? "selected":"";
									$opts.="<option value='$grp' $cnd>".prepare(ucfirst($grp))."</option>";
								}
							}
							
							$tds.="<tr><td>".prepare(ucwords($lv))." applies</td><td><select name='hlevels[$tp.$lv]' style='width:100%'>$opts</select></td></tr>";
						}
						
						$add = ($key==(count($res)-1)) ? "":"<tr><td colspan='2'><hr></td></tr>";
						$trs.= "<tr><td colspan='2'><b> Who will approve ".ucfirst($tp)." when </b></td><tr> $tds $add";
					}
				}
				
				$data = "<form method='post' id='hform' onsubmit='savehlevel(event)'>
					<table style='width:100%' cellpadding='5'> $trs
						<tr><td colspan='2' style='text-align:right'><br><button class='btnn'>Update</button></td></tr>
					</table><br>
				</form>";
			}
			else{
				$data = "<br><div style='color:#191970;background:#F0F8FF;border:1px solid lightblue;padding:10px;text-align:center'>
					<p>You havent set approval Levels in requisition, leave and advances! Set first</p>
				</div>";
			}
		}
		else{
			$data = "<br><div style='color:#191970;background:#F0F8FF;border:1px solid lightblue;padding:10px;text-align:center'>
				<p>You dont have permission to complete the request!</p>
			</div>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:350px'>
			<h3 style='color:#191970;text-align:center;font-size:23px'>Higher Level Approvals</h3><br> $data
		</div>";
	}
	
	# loan products
	if(isset($_GET['products'])){
		$show = intval($_GET['products']);
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		$def = array("client","app","staff","");
		$itps = array("FR"=>"Flat Rate","RB"=>"Reducing Balance");
		$lcat = $def[$show]; $trs=$opts=""; $no=0;
		
		if($show==3){
			$res = $db->query(1,"SELECT *FROM `prepaydays` WHERE `client`='$cid'");
			if($res){
				foreach($res as $row){
					$from=date("l M d, Y",$row['fromdate']); $to=date("l M d, Y",$row['todate']); $rid=$row['id']; $no++;
					$trs.="<tr id='rec$rid'><td>$no</td><td>$from</td><td>$to</td><td>
					<a href='javascript:void(0)' onclick=\"popupload('setup.php?prepayweek=$rid')\"><i class='fa fa-pencil'></i> Edit</a> |
					<a href='javascript:void(0)' style='color:#ff4500;' onclick=\"delrecord('$rid','1:prepaydays','Delete prepayment?')\">
					<i class='fa fa-trash-o'></i> Remove</a></td></tr>";
				}
			}
			
			$data = "<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Start Day</td><td>End Date</td><td>Action</td></tr>$trs";
		}
		else{
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='$lcat'");
			if($res){
				foreach($res as $row){
					$name=prepare(ucwords($row['product'])); $min=number_format($row['minamount']); $max=number_format($row['maxamount']); 
					$terms=json_decode($row['payterms'],1); $intr=(is_numeric($row['interest'])) ? number_format($row['interest']):$row['interest']; 
					$intv=$row['intervals']; $dur=$row['duration']/$intv; $pdef=json_decode($row['pdef'],1); $rid=$row['id']; $lis=""; $no++;
					if(substr($row['interest'],0,4)=="pvar"){
						$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$row['interest']."'")[0];
						$vars = json_decode($info['value'],1); $intr=$vars[min(array_keys($vars))]."-".$vars[max(array_keys($vars))];
					}
					
					if(isset($pdef["rollfee"])){ if($pdef["rollfee"]){ $terms["RollOver Fees"]="0:".$pdef["rollfee"]; }}
					if(isset($pdef["offees"])){ if($pdef["offees"]){ $terms["Offset Fees"]="0:".$pdef["offees"]; }}
					foreach($terms as $term=>$desc){
						$lis.="<li>".ucwords(str_replace("_"," ",$term))." - ".explode(":",$desc)[1]."</li>";
					}
					
					$lis = ($lis) ? $lis:"None"; $ptp=$row['category']; 
					$itp = (isset($pdef["intrtp"])) ? $itps[$pdef["intrtp"]]:"Flat Rate";
					$del = (in_array("delete loan product",$perms)) ? "<a href='javascript:void(0)' style='color:#ff4500' onclick=\"delprod('$ptp:$rid')\">Remove</a>":"";
					$add = (in_array("create loan product",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('setup.php?addprod=$rid&ptp=$ptp')\">Edit</a> |":"";
					$trs.="<tr valign='top' id='tr$rid'><td>$no</td><td>$name</td><td>$min - $max</td><td>$dur X $intv days</td><td>$intr<br>
					<span style='color:grey;font-size:13px'>$itp</span></td><td>$lis</td><td>$add $del</td></tr>";
				}
			}
			
			$data = "<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Product</td><td>Amount</td><td>Installments</td>
			<td>Interest</td><td>Pay Terms</td><td>Action</td></tr> $trs";
		}
		
		$def = array("Client System Products","Android App Products","Staff Loan Products","Prepayment Schedule");
		foreach($def as $key=>$txt){
			$cnd = ($key==$show) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$txt</option>";
		}
		
		$btx = ($show==3) ? "Prepay":"product";
		$onclick = ($show==3) ? "popupload('setup.php?prepayweek')":"popupload('setup.php?addprod&ptp=$lcat')";
		$add = (in_array("create loan product",$perms)) ? "<button class='bts' style='float:right;font-size:13px;padding:5px' onclick=\"$onclick\"><i class='fa fa-plus'></i> $btx</button>":"";
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $def[$show]</h3>
				<table class='table-striped table-borderd' style='width:100%;margin-top:15px' cellpadding='5'>
					<caption style='caption-side:top'>
						<select style='width:200px' onchange=\"loadpage('setup.php?products='+this.value.trim())\">$opts</select> $add
					</caption> $data 
				</table>
			</div>
		</div>";
		savelog($sid,"Accessed $def[$show] setup");
	}
	
	#add/edit prepay weeks
	if(isset($_GET['prepayweek'])){
		$id=trim($_GET['prepayweek']);
		$from=$to="";

		if($id>0){
			$res = $db->query(1,"SELECT *FROM `prepaydays` WHERE `id`='$id'");
			$row=$res[0]; $from=date("Y-m-d",$row['fromdate']); $to=date("Y-m-d",$row['todate']);
		}
		
		echo "<div style='margin:0 auto;padding:10px;max-width:300px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Prepayment Week</h3><br>
			<form method='post' onsubmit='saveprepay(event)' id='pfom'>
				<input type='hidden' name='prepid' value='$id'> <input type='hidden' name='sid' value='$sid'>
				<p>Start Date<br><input type='date' name='pfrom' style='width:100%' min='".date('Y-m-d')."' value='$from' required></p>
				<p>To Date<br><input type='date' name='pto' style='width:100%' min='".date('Y-m-d')."' value='$to' required></p><br>
				<p style='text-align:right;'><button class='btnn'>Save</button></p><br>
			</form>
		</div>";
	}
	
	# add/edit product
	if(isset($_GET['addprod'])){
		$rid = trim($_GET['addprod']);
		$ptp = trim($_GET['ptp']); 
		$prod=$min=$max=$intv=$dur=$intr=$penal=$intdur=$lis=$dis1=$trs=$tls=$als=$ils=$cls=""; $pdef=[];
		$dis2="none"; $ran=rand(123400,999999); $penamt=0; $itps=array("FR"=>"Flat Rate","RB"=>"Reducing Balance");
		$clist = array(0=>"New Clients only",6=>"Repeat Applications",1=>"@Loan application",2=>"Every Installment",3=>"@Certain Installment",4=>"Loan deduction",5=>"Added to Loan");
		
		if($rid){
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$rid'"); $row=$res[0]; 
			$prod=prepare(ucwords($row['product'])); $min=$row['minamount']; $max=$row['maxamount'];  $intdur=$row['interest_duration']; 
			$terms=json_decode($row['payterms'],1); $intr=$row['interest']; $intv=$row['intervals']; $dur=$row['duration'];
			$penalty=explode(":",$row['penalty']); $penal=$penalty[0]; $penamt=$penalty[1]; $pdef=json_decode($row['pdef'],1); $no=0; 
			$dis1=(substr($intr,0,4)=="pvar") ? "none":""; $dis2=(substr($intr,0,4)=="pvar") ? "block":"none";
				
			foreach($terms as $term=>$desc){
				$des = ucwords(str_replace("_"," ",$term)); $fee=explode(":",$desc)[1]; $ran=rand(123456,654321); $opts=$klis=""; $no++;
				foreach($clist as $val=>$txt){
					$cnd=($val==explode(":",$desc)[0]) ? "selected":"";
					if($val==3 && $cnd){
						$opts.= "<option value='$val' $cnd>@Installment ".explode(":",$desc)[2]."</option>";
						$klis.= "<input type='hidden' id='v$ran' name='$ran' value='".explode(":",$desc)[2]."'>";
					}
					else{ $opts.= "<option value='$val' $cnd>$txt</option>"; }
				}
				
				$trs .= "<tr valign='top' id='$ran'><td>Other Charges<br><select name='charges[$ran]' style='width:100%;font-size:15px;cursor:pointer' id='o$ran'
				onchange=\"ckcharge('$ran',this.value)\">$opts</select><span id='s$ran'></span> $klis</td>
				<td>Charge Description<br><input type='text' placeholder='E.g Processing Fee' style='width:100%' value='$des' name='cdesc[$ran]' required></td>
				<td>Fee Charged<br><input type='text' id='c$ran' onkeyup=\"valid('c$ran',this.value)\" style='width:100%' value='$fee' name='fees[$ran]' required><br>
				<a style='color:#ff4500' href=\"javascript:delfield('$ran')\">Remove</a></td></tr>";
			}
		}
		
		$text = ($rid) ? "Edit $ptp Loan Product":"Create $ptp Loan Product"; $opts="";
		$arr = array("none"=>"No Charges","daily"=>"Daily charge","installment"=>"Charged @Installment","loan"=>"For whole Loan","default"=>"Upon Defaulting Loan");
		foreach($arr as $key=>$des){
			$cnd=($key==$penal) ? "selected":"";
			$opts.="<option value='$key' $cnd>$des</option>";
		}
		
		$lis = "<option value='none'>-- Select Range --</option>";
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting` LIKE 'pvar%'");
		if($sql){
			foreach($sql as $row){
				$des=json_decode($row['value'],1); $vname=$row['setting']; $cnd=($vname==$intr) ? "selected":"";
				$lis.= "<option value='$vname' $cnd>".$des[min(array_keys($des))]."-".$des[max(array_keys($des))]."</option>";
			}
		}
		
		$itp = (isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR";
		$rolfee = (isset($pdef["rollfee"])) ? $pdef["rollfee"]:0;
		$offee = (isset($pdef["offees"])) ? $pdef["offees"]:0;
		$lnapp = (isset($pdef["allow_multiple"])) ? $pdef["allow_multiple"]:0;
		$waivd = (isset($pdef["repaywaiver"])) ? $pdef["repaywaiver"]:0;
		$clust = (isset($pdef["cluster"])) ? $pdef["cluster"]:"";
		$cskip = (isset($pdef["skip_checkoff"])) ? $pdef["skip_checkoff"]:0;
		$inst = (isset($pdef["showinst"])) ? $pdef["showinst"]:0;
		
		foreach($itps as $key=>$txt){
			$cnd = ($key==$itp) ? "selected":"";
			$tls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("No running Loans","Other running loans") as $key=>$txt){
			$cnd = ($key==$lnapp) ? "selected":"";
			$als.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("All Installments","Current Installment") as $key=>$txt){
			$cnd = ($key==$inst) ? "selected":"";
			$ils.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("No","Yes") as $key=>$txt){
			$cnd = ($key==$cskip) ? "selected":"";
			$cls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div style='padding:10px;max-width:550px;margin:0 auto;min-width:450px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>$text</h3><br>
			<form method='post' id='lform' onsubmit=\"saveprod(event,'$ptp')\">
				<input type='hidden' name='loanprod' value='$rid'><input type='hidden' name='prodcat' value='$ptp'>
				<table cellpadding='7' style='width:100%' class='ptbl'>
					<caption style='text-align:right'><a href='javascript:void(0)' onclick=\"addfield('$ptp')\"><i class='fa fa-plus'></i> Add Charges</a><hr></caption>
					<tr valign='bottom'><td>Product Name<br><input type='text' name='prod' style='width:100%' value=\"$prod\" required></td>
					<td>Loan Duration<br><input type='number' placeholder='Total Days' style='width:100%' name='ldur' value='$dur' required></td>
					<td>Payment Intervals<br><input type='number' placeholder='Installment Days' style='width:100%' name='pdays' value='$intv' required></td></tr>
					<tr><td>Total Interest <i class='bi-columns-gap' style='float:right;cursor:pointer' onclick=\"togprods()\" title='Set Interest range'></i><br>
					<input type='text' style='width:100%;display:$dis1' id='intr' name='intr' onkeyup=\"valid('intr',this.value)\" value='$intr' required>
					<span id='drops' style='display:$dis2'><select style='width:100%;font-size:15px' name='varint'>$lis</select><br><a href='javascript:void(0)' 
					onclick=\"popupload('setup.php?prodvars')\">Manage Ranges</a></span></td>
					<td>Interest Duration<br><input type='number' placeholder='Applicable Days' style='width:100%' name='intdur' value='$intdur' required></td>
					<td>Interest Type<br><select name='intrtp' style='width:100%'>$tls</select></td></tr>
					<tr valign='top'><td>Min Loan Amount<br><input type='number' style='width:100%' name='minam' value='$min' required></td>
					<td>Max Amount<br><input type='number' style='width:100%' name='maxam' value='$max' required></td>
					<td>Arrears Penalty<br><select name='penalty' style='width:100%'>$opts</select></td></tr>
					<tr valign='top'><td>Penalty Amount<br><input type='text' id='pen' onkeyup=\"valid('pen',this.value)\" style='width:100%' name='penamt' value='$penamt' required></td>
					<td>Rollover Fees<br><input type='text' id='roll' onkeyup=\"valid('roll',this.value)\" style='width:100%' name='rollfee' value='$rolfee' required></td>
					<td>Loan Offset Fees<br><input type='text' id='offee' onkeyup=\"valid('offee',this.value)\" style='width:100%' name='offee' value='$offee' required></td></tr> 
					<tr valign='bottom'><td>Repay Waiver days<br><input type='number' name='waiver' value='$waivd' style='width:100%' required></td>
					<td>Clients apply with<br><select style='width:100%;font-size:15px' name='lnapp'>$als</select></td><td>Installment display<br>
					<select style='width:100%;font-size:15px' name='instds'>$ils</select></td></tr> 
					<tr valign='bottom'><td>Excempt from Checkoffs<br><select style='width:100%;font-size:15px' name='cskip'>$cls</select></td>
					<td colspan='2'>Cluster Name<br><input type='text' name='prdclust' value=\"$clust\" style='width:100%;'></td></tr> $trs
				</table> <p style='text-align:right'> 
				<button class='btnn'><i class='bi-save'></i> Save</button></p>
			</form>
		</div>";
	}
	
	# variable interests
	if(isset($_GET['prodvars'])){
		$intr = trim($_GET['prodvars']);
		if($intr && $intr!="none"){
			$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='$intr'");
			if(!$chk){
				$intr="pvar".rand(1111,9999); $dys=json_encode([1=>"10%"],1);
				$db->insert(1,"INSERT INTO `settings` VALUES(NULL,'$cid','$intr','$dys')");
			}
		}
		
		$lis = "<option value='none'>-- Select --</option>"; $all=[]; $trs="";
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting` LIKE 'pvar%'");
		if($sql){
			foreach($sql as $row){
				$des=json_decode($row['value'],1); $vname=$row['setting']; $cnd=($vname==$intr) ? "selected":""; $all[$vname]=$des;
				$lis.= "<option value='$vname' $cnd>".$des[min(array_keys($des))]."-".$des[max(array_keys($des))]."</option>";
			}
		}
		
		if(isset($all[$intr])){
			foreach($all[$intr] as $dy=>$perc){
				$trs.= "<tr><td style='width:40%'><input type='number' style='width:100%' name='pday[]' value='$dy' required></td>
				<td><input type='text' id='d$dy' onkeyup=\"valid('d$dy',this.value)\" style='width:100%' name='pchaj[]' value='$perc' required></td></tr>";
			}
		}
		else{ $trs = "<tr><td style='width:40%'><input type='number' style='width:100%' name='pday[]' value='1' required></td>
		<td><input type='text' id='d1' onkeyup=\"valid('d1',this.value)\" style='width:100%' name='pchaj[]' value='10%' required></td></tr>"; }
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto;padding:10px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Interest Ranges</h3><br>
			<form method='post' id='pvform' onsubmit='savevars(event)'>
				<p>Interest Category <a href='javascript:void(0)' style='float:right' onclick=\"popupload('setup.php?prodvars=pvar')\"><i class='fa fa-plus'></i> Create</a>
				<br><select name='pvar' onchange=\"popupload('setup.php?prodvars='+this.value)\" style='width:100%'>$lis</select></p>
				<table cellpadding='5' style='width:100%' class='pvtbl'>
					<caption style='text-align:right'><a href='javascript:void(0)' onclick=\"addprow()\">Add Row</a></caption> 
					<tr style='font-weight:bold;color:#191970'><td>Day</td><td>Interest Charge</td></tr> $trs
				</table><br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
	}
	
	# leave form structure
	if(isset($_GET['leaves'])){
		$db = new DBO();
		$ltbl="org".$cid."_leaves";
		$exclude = array("id","days","time","year","status","approvals","comments","staff");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = array("id"=>"number","staff"=>"number","leave_type"=>"text","reason"=>"textarea","days"=>"number","leave_start"=>"date","leave_end"=>"date",
		"approvals"=>"textarea","comments"=>"textarea","status"=>"number","time"=>"number","year"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.=(!in_array($type,array("image","docx","pdf"))) ? "<option value='$type'>$desc</option>":"";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$ltbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp=($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.="<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles']));
		$sett = (in_array("update leave settings",$perms)) ? "<button class='bts' onclick=\"loadpage('setup.php?leavesett')\" 
		style='float:right;font-size:14px;padding:4px'><i class='fa fa-cog'></i> Settings</button>":"";
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Leave Form Setup $sett</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$ltbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'> $trs  </table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed staff leave form setup settings"); 
	}
	
	# leave approvals
	if(isset($_GET["leaveapp"])){
		$nots=["leave"=>1]; $defs=$lapprovals=$trs="";
		$sql = $db->query(1,"SELECT *FROM `approval_notify` WHERE `client`='$cid'");
		if($sql){
			foreach($sql as $row){ $nots[$row['approval']]=$row['staff']; }
		}
	
		$usas1 = "<option value='0'>-- Notify None --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name'])); $cnd1=($rid==$nots['leave']) ? "selected":""; 
			$usas1.="<option value='$rid' $cnd1>$name</option>"; 
		}
		
		$qri = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
		if($qri){
			$groups[] = "collection agent";
			foreach($qri as $row){ $groups[]=$row['sgroup']; }
		}
		else{ $groups=array("manager","accountant","collection agent"); }
		
		$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='leave'");
		$appr = ($res) ? json_decode($res[0]['levels'],1):[]; $d1=$d2="";
		foreach($groups as $grp){
			$lis1=$lis2="<option value='0'>-- Select --</option>";
			if(isset($appr[1])){ $d1 = (isset($appr[1][$grp])) ? $appr[1][$grp]:""; }
			if(isset($appr[2])){ $d2 = (isset($appr[2][$grp])) ? $appr[2][$grp]:""; }
			
			foreach($groups as $gr){ $cnd1=($gr==$d1 && $gr!=$grp) ? "selected":""; $lis1.=($gr!=$grp) ? "<option value='$gr' $cnd1>".ucwords($gr)."</option>":""; }
			foreach($groups as $gr){ $cnd2=($gr==$d2 && $gr!=$grp) ? "selected":""; $lis2.= ($gr!=$grp) ? "<option value='$gr' $cnd2>".ucwords($gr)."</option>":""; }
			
			$trs.= "<tr valign='top'><td>".ucwords($grp)."</td><td><Select style='width:150px' name='approvals[$grp]'>$lis1</Select></td>
			<td style='text-align:right'><Select style='width:150px' name='appr2[$grp]'>$lis2</Select></td></tr>";
		}
		
		echo "<div class='cardv' style='max-width:800px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:5px;min-width:500px'>
				<h3 style='font-size:22px;' onclick=\"loadpage('setup.php?leaveapp')\">$backbtn Leave Approval Settings</h3>
				<div style='width:100%;overflow:auto'>
					<form method='post' id='lfom' onsubmit=\"saveapproval(event,'lfom','Leave application','leaves')\">
						<input type='hidden' name='apptype' value='leave'>
						<table style='width:100%;min-width:500px;margin-top:10px' cellpadding='5'>
							<tr style='font-size:15px;color:#191970;background:#e6e6fa;font-weight:bold'><td>Staff Group Application</td><td>Approval 1</td>
							<td style='text-align:right'>Approval 2</td></tr> $trs <tr><td colspan='3'><hr></td></tr>
							<tr valign='bottom'><td colspan='2'>Notify Who upon all Approvals?<br><select style='width:200px' name='notify'>$usas1</select></td>
							<td style='text-align:right'><button class='btnn'><i class='bi-save'></i> Save</button></td></tr>
						</table>
					</form>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed leave approval Settings");
	}
	
	# leave settings
	if(isset($_GET['leavesett'])){
		$data = array("leavedays"=>24,"probation"=>2,"leaveapplication"=>1);
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $data[$row['setting']]=$row['value']; }
		}
		
		$days = $data['leavedays']; $prob=$data['probation'];  $state=$data['leaveapplication']; 
		$opts=$opts2=$trs=""; $no=0;
		
		for($i=1; $i<=6; $i++){
			$cnd=($i==$prob) ? "selected":""; $txt=($i==1) ? "1 Month":"$i Months";
			$opts.="<option value='$i' $cnd>$txt</option>";
		}
		
		for($i=0; $i<2; $i++){
			$cnd=($i==$state) ? "selected":""; $txt=($i==0) ? "Closed":"Opened";
			$opts2.="<option value='$i' $cnd>$txt</option>";
		}
		
		$res = $db->query(1,"SELECT *FROM `leave_settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$name=prepare(ucfirst($row['leave_type'])); $data=json_decode($row['setting'],1);
				$appto=$data['application']; $cond=$data['condition']; $reimb=$data['reimburse']; $susp=$data['suspension']; 
				$sat=$data['saturday']; $sun=$data['sunday'];
				
				$td1=($appto=="all") ? "All Staff":"Male Employees"; $td1=($appto=="female") ? "Female Employees":$td1; $no++;
				$td2=($cond==0) ? "<b>1++</b> Days":"<b>0++</b> Days"; $td3=($reimb) ? "Bonus allowed":"Reimburse"; 
				$td4=($susp) ? "Access Suspended":"Remain active"; $rid=$row['id'];
				
				$trs.="<tr id='rec$rid' valign='top'><td>$no</td><td>$name</td><td>$td1</td><td>$td2</td><td>Sat: <b>$sat</b><br>Sun: <b>$sun</b></td><td>$td3</td>
				<td>$td4</td><td><a href='javascript:void(0)' onclick=\"popupload('setup.php?leavecat=$rid')\">Edit</a> | 
				<a href='javascript:void(0)' onclick=\"delrecord('$rid','1:leave_settings','Delete leave record?')\" style='color:#ff4500'>Remove</a></td></tr>";
			}
		}
		
		echo "<div class='cardv' style='max-width:1100px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:5px;min-width:500px'>
				<h3 style='font-size:22px;'>$backbtn Leave Settings & Categories</h3><hr>
				<table style='' cellpadding='5'>
					<tr><td>Yearly leave Days<br><input type='number' onchange=\"savesetting('leavedays:'+this.value)\" value='$days' style='padding:5px;width:130px'></td>
					<td>New staff probation<br><select style='width:150px;padding:7px' onchange=\"savesetting('probation:'+this.value)\">$opts</select></td>
					<td>Application Status<br><select style='width:130px;padding:7px' onchange=\"savesetting('leaveapplication:'+this.value)\">$opts2</select></td></tr>
				</table>
				<p style='font-weight:bold;margin-top:10px'>Leave Categories
				<button class='bts' style='float:right;padding:4px;line-height:20px' onclick=\"popupload('setup.php?leavecat')\"><i class='fa fa-plus'></i> Create</button></p>
				<table class='table-striped' cellpadding='7' style='width:100%;font-size:15px'>
					<tr valign='top' style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px'><td colspan='2'>Category</td><td>Applicable to</td>
					<td>Restriction</td><td>Weekends</td><td>Reimburse Exta Days</td><td colspan='2'>Account Status</td></tr> $trs
				</table><br>
			</div>
		</div>";
		savelog($sid,"Accessed leave settings form");
	}
	
	# create/edit leave category
	if(isset($_GET['leavecat'])){
		$lid = trim($_GET['leavecat']);
		$name=$appto=$cond=$reimb=$susp=$opt1=$opt2=$opt3=$opt4=$sunfro=$sunto="";
		$satfro="09:00"; $satto="13:00";
		
		if($lid){
			$res = $db->query(1,"SELECT *FROM `leave_settings` WHERE `id`='$lid'");
			$row = $res[0]; $name=prepare(ucfirst($row['leave_type'])); $data=json_decode($row['setting'],1);
			$appto=$data['application']; $cond=$data['condition']; $reimb=$data['reimburse']; $susp=$data['suspension'];
			$sat=explode("-",$data['saturday']); $sun=($data['sunday']=="Off") ? [null,null]:explode("-",$data['sunday']);
			$satfro=$sat[0]; $satto=$sat[1]; $sunfro=$sun[0]; $sunto=$sun[1];
		}
		
		foreach(array("all"=>"All Employees","male"=>"Male staff only","female"=>"Female staff only") as $key=>$val){
			$cnd = ($key==$appto) ? "selected":"";
			$opt1.="<option value='$key' $cnd>$val</option>";
		}
		
		foreach(array("Prevent application","Allow application") as $key=>$val){
			$cnd = ($key==$cond) ? "selected":"";
			$opt2.="<option value='$key' $cnd>$val</option>";
		}
		
		foreach(array("Deduct from Accumulated days","Dont reimburse extra days") as $key=>$val){
			$cnd = ($key==$reimb) ? "selected":"";
			$opt3.="<option value='$key' $cnd>$val</option>";
		}
		
		foreach(array("Remain Active","Suspend system access") as $key=>$val){
			$cnd = ($key==$susp) ? "selected":"";
			$opt4.="<option value='$key' $cnd>$val</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Leave Category</h3><br>
			<form method='post' id='lform' onsubmit='savecats(event)'>
				<input type='hidden' name='lcid' value='$lid'>
				<p>Leave Category<br><input type='text' style='width:100%' value=\"$name\" name='catname' required></p>
				<p>Applicable To<br><select name='application' style='width:100%'>$opt1</select></p>
				<p>Saturday working Hrs <br> <input type='time' style='width:120px' name='satfro' value='$satfro' required> To: 
				<input type='time' style='width:120px;float:right' value='$satto' name='satto' required></p>
				<p>Sunday Hrs (Optional)<br> <input type='time' style='width:120px' name='sunfro' value='$sunfro'> To: 
				<input type='time' style='width:120px;float:right' name='sunto' value='$sunto'></p>
				<p>Allow Application even with 0 leave days<br><select name='condition' style='width:100%'>$opt2</select></p>
				<p>Reimburse Extra days spent<br><select name='reimburse' style='width:100%'>$opt3</select></p>
				<p>Allow System access while on Leave<br><select name='suspension' style='width:100%'>$opt4</select></p>
				<p style='text-align:right'><button class='btnn'>Save</button></p><br>
			</form>
		</div><br>";
	}
	
	# advance form structure
	if(isset($_GET['advance'])){
		$atbl = "advances$cid";
		$exclude = array("id","staff","approvals","approved","month","status","year","time","charges");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$atbl'");
		$def = array("id"=>"number","staff"=>"number","amount"=>"number","reason"=>"textarea","approvals"=>"textarea","approved"=>"text","charges"=>"text","month"=>"number",
		"year"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.=(!in_array($type,array("image","docx","pdf"))) ? "<option value='$type'>$desc</option>":"";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$atbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp=($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.="<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Salary advance Form Setup
				<button class='bts' style='float:right;font-size:13px;' onclick=\"popupload('setup.php?setadvance')\"><i class='fa fa-wrench'></i> Settings</button></h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$atbl:3'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'>
						<caption style='caption-side:top'>Create Form structure to capture Information for storage</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed Salary advance form setup settings"); 
	}
	
	# advance settings
	if(isset($_GET['setadvance'])){
		$agree = "I apply for the above mentioned salary advance and i authorize @employer to deduct the loan repayments from my salary for payroll month @current_month. 
		I understand that I am eligible for no more than two emergency payroll per calendar year that the amount requested shall not exceed @limit of my earnings to date for the current month. 
		I also agree that if I terminate employment prior to total repayment, I authorize the employer to deduct any unpaid advance amount from my salary owed to me at time of termination.";
		
		$setts = array("advanceapplication"=>1,"advancelimit"=>60,"advancefrom"=>10,"advanceto"=>28,"advancebypasslim"=>0,"advanceagreement"=>$agree,"charge_advance"=>"0:0");
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=prepare(trim($row['value'])); }
		}
		
		$app=$setts['advanceapplication']; $lim=$setts['advancelimit']; $from=$setts['advancefrom']; $to=$setts['advanceto']; $agree=$setts['advanceagreement'];
		$def = explode(":",$setts["charge_advance"]); $intcj=$def[0]; $cjam=$def[1];
		$ad1 = ($app) ? "checked":""; $ad2 = (!$app) ? "checked":""; $opts=$dfr=$dto=$ils="";
		
		foreach(array("Manage Strictly","Bypass restriction") as $key=>$val){
			$cnd = ($key==$setts['advancebypasslim']) ? "selected":"";
			$opts.="<option value='$key' $cnd>$val</option>";
		}
		
		for($i=1; $i<32; $i++){
			$cnd1=($i==$from) ? "selected":""; $cnd2=($i==$to) ? "selected":"";
			$dfr.="<option value='$i' $cnd1>Date $i</option>"; $dto.="<option value='$i' $cnd2>Date $i</option>";
		}
		
		foreach(array("No Charges","Deduct Interest") as $key=>$val){
			$cnd = ($key==$intcj) ? "selected":"";
			$ils.= "<option value='$key' $cnd>$val</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:350px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Advance Settings</h3><br>
			<form method='post' id='aform' onsubmit='saveadvance(event)'>
				<table style='width:100%' cellpadding='5'>
					<tr><td colspan='2'>Advance Application</td></tr>
					<tr><td><input type='radio' name='advanceapplication' value='1' $ad1> &nbsp; Enabled</radio></td>
					<td><input type='radio' name='advanceapplication' value='0' $ad2> &nbsp; Disabled</radio></td></tr>
					<tr><td colspan='2'><br>Limit from accumulated salary (%)</td></tr>
					<tr><td><input type='text' id='adam' onkeyup=\"valid('adam',this.value)\" value='$lim' style='width:80px' maxlength='3' name='advancelimit'></td>
					<td style='text-align:right'><select name='advancebypasslim'>$opts</select></td></tr>
					<tr><td colspan='2'><br>Applicable From date <span style='float:right'>To date</span></td></tr>
					<tr><td><select style='width:120px' name='advancefrom'>$dfr</select></td>
					<td style='text-align:right'><select style='width:120px' name='advanceto'>$dto</select></td></tr>
					<tr><td colspan='2'><br>Interest Charges <span style='float:right'>To date</span></td></tr>
					<tr><td><select style='width:130px;font-size:15px' name='intrchaj'>$ils</select></td>
					<td style='text-align:right'><input type='text' id='intr' onkeyup=\"valid('intr',this.value)\" style='width:120px' name='intrval' value='$cjam' required></td></tr>
					<tr><td colspan='2'><br>Staff consent agreement Text</td></tr>
					<tr><td colspan='2'><textarea class='mssg' style='height:150px;font-family:trebuchet ms;font-size:14px' name='advanceagreement' required>$agree</textarea></td></tr>
					<tr><td style='text-align:right' colspan='2'><br><button class='btnn'>Update</button></td></tr>
				</table>
			</form>
		</div><br>";
		
		savelog($sid,"Accessed salary advance settings");
	}
	
	# loan form structure
	if(isset($_GET['loans'])){
		$vtp = intval($_GET["loans"]);
		$ctbl = ($vtp) ? "staff_loans$cid":"org$cid"."_loans";
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		
		$def = ($vtp) ? ["id"=>"number","stid"=>"number","staff"=>"text","loan_product"=>"text","phone"=>"number","branch"=>"number","amount"=>"number","duration"=>"number",
		"disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number","loan"=>"text","status"=>"number","approvals"=>"textarea",
		"pref"=>"number","payment"=>"text","processing"=>"textarea","cuts"=>"textarea","creator"=>"number","time"=>"number"]:
		array("id"=>"number","client"=>"text","loan_product"=>"text","client_idno"=>"number","phone"=>"number","branch"=>"number","loan_officer"=>"number",
		"amount"=>"number","tid"=>"number","duration"=>"number","disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number",
		"loan"=>"text","status"=>"number","approvals"=>"textarea","disbursed"=>"textarea","clientdes"=>"textarea","creator"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$reuse = ($res) ? json_decode($res[0]['reuse'],1):[];
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$ctbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		$exclude = array("id","branch","status","time","loan","client","staff","balance","expiry","penalty","disbursement","paid","phone","tid","approvals","creator",
		"clientdes","stid","pref","cuts","payment","processing","disbursed");
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!isset($def[$field])) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (isset($def[$field])) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(isset($dsrc[$field])){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$cnd = (in_array($field,$reuse)) ? "checked":"";
				$rec = (!isset($def[$field])) ? "<input type='checkbox' name='reuse[$ran]' $cnd></checkbox>":"";
				$inp = ($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.= "<tr valign='bottom' id='$unq'><td>$rec</td><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%;padding:8px 5px' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		$title = ($vtp) ? "Staff Loan Form Setup":"Client Loan Form Setup";
		$btx = ($vtp) ? "Setup Client Loan Form":"Setup Staff Loan Form"; $nxt=($vtp) ? 0:1;
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='allow_staff_loans'"); $allow=($chk) ? $chk[0]['value']:0;
		$var = ($allow) ? "<p><button class='bts' type='reset' onclick=\"loadpage('setup.php?loans=$nxt')\"><i class='bi-tools'></i> $btx</button></p>":"";
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px;'>
				<h3 style='font-size:22px;'>$backbtn $title</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$ctbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='5' class='tblstr'>
						<caption style='caption-side:top'> $var 
						Design your Loan form to allow loan-template creation. Check on the left checkbox if you want the field to use 
						previously filled form data for existing applications</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick=\"addtblfield('template')\"><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed $title settings"); 
	}
	
	# clients structure
	if(isset($_GET['clients'])){
		$ctbl = "org$cid"."_clients";
		$exclude = array("id","cdef","branch","status","time","creator","cycles");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","cycles"=>"number",
		"creator"=>"number","status"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		if(sys_constants("operate_group_loans")){ $def["client_group"]="text"; }
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$ctbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp=($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.="<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Client Bio-data
				<button class='bts' style='float:right' onclick=\"loadpage('setup.php?leads')\"><i class='bi-journal-medical'></i> Interaction Form</button></h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$ctbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'>
						<caption style='caption-side:top'>Create Form structure to capture Information for storage</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed Client Bio-data settings");
	}
	
	# client interaction form
	if(isset($_GET['leads'])){
		$db = new DBO();
		$exclude = array("id","officer","source","time"); 
		$itbl = "interactions$cid";
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$itbl'");
		$def = array("id"=>"number","client"=>"number","comment"=>"textarea","source"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$itbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp = ($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				
				$trs.= "<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Client Interaction Form</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$itbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'>
						<caption style='caption-side:top'>Create Interaction Form structure to capture Information for storage</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed interaction form settings");
	}
	
	# BI analytics
	if(isset($_GET['analytics'])){
		$kpis = KPI_LIST; $data=$days=$pdys=$opts=$tcg=""; $tb4=5; $tb5=1; $clust=[];
		$forms = array("value"=>"Actual Value","A/T"=>"Actuals/Targets*100","S-E/S-T"=>"(Opening Bal-Closing Bal)/Opening Bal-Targets * 100",
		"E-S/S"=>"(End Arrears - Beginning Arrears)/Beginning Arrears * 100","E-T/T"=>"(End Arrears - Target Arrears)/Target Arrears * 100");
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='perfkpis' AND `client`='$cid'");
		$chk = $db->query(1,"SELECT `pdef` FROM `loan_products` WHERE `client`='$cid' AND JSON_CONTAINS_PATH(`pdef`,'one','$.cluster')"); 
		if($qri){ $sett = json_decode($qri[0]['value'],1); }
		else{
			foreach(array("newloans","repeats","performing","arrears","disbursement") as $one){ $sett[$one]=$kpis[$one]; }
		}
		
		foreach($kpis as $set=>$one){
			$desc=$one["desc"]; $form=$one["form"]; $df=$one["def"]; $lis=$cnd1=$cnd2=$trsg=""; $list=[];
			if(isset($sett[$set])){
				$cnd1 = ($sett[$set]["target"]) ? "checked":""; $list=(isset($sett[$set]["list"])) ? $sett[$set]["list"]:[];
				$cnd2 = ($sett[$set]["rank"]) ? "checked":""; $form=$sett[$set]["form"];
			}
			
			foreach($forms as $val=>$txt){
				$cnd = ($val==$form) ? "selected":"";
				if($set=="arrears"){ $lis.= "<option value='$val' $cnd>$txt</option>"; }
				else{ $lis.= (in_array($val,["value","A/T"])) ? "<option value='$val' $cnd>$txt</option>":""; }
			}
			
			if($set=="loanprods"){
				if(!$chk){ continue; }
				foreach($chk as $row){
					$def = json_decode($row["pdef"],1); $cl=$def["cluster"]; $chkd=(in_array($cl,$list)) ? "checked":"";
					if($cl){ $clust[$cl] = "<li style='list-style:none;padding:5px 0px'><input type='checkbox' name='lclust[]' value='$cl' $chkd> &nbsp; ".prepare(ucfirst($cl))."</li>"; } 
				}
				
				if($clust){
					$half = ceil(count($clust)/2); $chunk=array_chunk($clust,$half);
					$td1=implode("",$chunk[0]); $td2=(isset($chunk[1])) ? implode("",$chunk[1]):"";
					$trsg = "<tr><td colspan='2'><hr><b>Select Loan Product Clusters to Use</td></tr><tr valign='top'><td>$td1</td><td>$td2</td></tr>";
				}
				else{ continue; }
			}
			
			$data.= "<h3 style='font-size:18px;color:#191970;padding:7px;background:#f0f0f0;cursor:pointer'><i class='bi-chevron-down'></i> $desc</h3> 
			<div style='padding:2px 10px'>
				<input type='hidden' name='kpis[$set][def]' value='$df'><input type='hidden' name='kpis[$set][desc]' value='$desc'>
				<table cellpadding='5' style='width:100%'> <input type='hidden' name='kpis[$set][title]' value='".$one["title"]."'>
					<tr><td><input type='checkbox' name='kpis[$set][target]' value='1' $cnd1></checkbox> &nbsp; Include in Targets</td>
					<td style='width:50%'><input type='checkbox' name='kpis[$set][rank]' value='1' $cnd2></checkbox> &nbsp; Include in Ranking</td></tr>
					<tr><td>Performance Ranking Formular</td><td><select style='width:100%' name='kpis[$set][form]' style='padding:5px'>$lis</select></td></tr> $trsg
				</table>
			</div>";
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting` IN ('targetsb4','perflastdy','targetcollagents') AND `client`='$cid'");
		if($res){
			foreach($res as $row){
				if($row["setting"]=="targetsb4"){ $tb4=$row["value"]; }
				if($row["setting"]=="perflastdy"){ $tb5=$row["value"]; }
				if($row["setting"]=="targetcollagents"){ $tcg=$row["value"]; }
			}
		}
		
		for($i=2; $i<=25; $i++){
			$cnd = ($i==$tb4) ? "selected":"";
			$days.= "<option value='$i' $cnd>Day $i of Month</option>";
		}
		
		foreach(array("Last date of previous month","Date 1 of current month") as $pos=>$one){
			$cnd = ($pos==$tb5) ? "selected":"";
			$pdys.= "<option value='$pos' $cnd>$one</option>";
		}
		
		foreach(array("Dont Include","Include") as $pos=>$txt){
			$cnd = ($pos==$tcg) ? "selected":"";
			$opts.= "<option value='$pos' $cnd>$txt</option>";
		}
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Performance & Analytics Settings</h3><hr>
				<div class='row'>
					<div class='col-12 col-md-12 col-lg-7'>
						<form method='post' id='pafom' onsubmit=\"savekpis(event)\">
							<div id='dvs'>$data</div><br>
							<p style='text-align:right;'><button class='btnn' style='padding:5px'><i class='bi-save'></i> Save</button></p>
						</form>
					</div>
					<div class='col-12 col-md-12 col-lg-5'>
						<div style='max-width:350px;margin:0 auto'>
							<form method='post' id='pfom' onsubmit='savepsett(event)'>
								<p><b>Control Settings</b></p>
								<p>Set Targets for Collection Agents<br><select name='perfset[targetcollagents]' style='width:100%'>$opts</select></p>
								<p>Disable Change of Targets after<br><select name='perfset[targetsb4]' style='width:100%'>$days</select></p>
								<p>For Arrears & Performing, Compare current with:<br><select name='perfset[perflastdy]' style='width:100%;font-size:15px'>$pdys</select></p><br>
								<p style='text-align:right'><button class='btnn' style='padding:5px'><i class='bi-save'></i> Update</button></p>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div><script> $(function(){ $('#dvs').accordion({heightStyle: 'content'}); }); </script>";
		savelog($sid,"Accessed Business analytics settings");
	}
	
	# asset form Setup
	if(isset($_GET['finassetfom'])){
		$db = new DBO();
		$ctbl = "finassets$cid";
		$exclude = array("id","def","status","time","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","branch"=>"number","asset_name"=>"text","asset_description"=>"textarea","measurement_unit"=>"number","asset_cost"=>"number","asset_category"=>"number",
		"qty"=>"text","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; 
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.= ($tbl!=$ctbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!isset($def[$field])) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (isset($def[$field])) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(isset($dsrc[$field])){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp = ($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.= "<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%;padding:8px' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Assets Form</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$ctbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'> $trs </table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		savelog($sid,"Accessed asset finance form setup page");
	}
	
	# client group settings
	if(isset($_GET["setgrps"])){
		$gid = trim($_GET["setgrps"]);
		$set = (isset($_GET["noset"])) ? 0:1;
		$lock = (!$set) ? "disabled":"";
		$sett = groupSetting(); $lis="";
		
		if($gid){
			$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
			$def = json_decode($sql[0]["def"],1);
			if(isset($def["sets"])){ $sett=$def["sets"]; }
		}
		
		$list = array("collateral"=>"Member Savings Collateral Amount","minmembers"=>"Minimum Group Members","maxmembers"=>"Maximum Group Members","loan_notify"=>"Loan Notifications",
		"guarantors"=>"Loan Guarantors in Group","loan_apply"=>"Loan Application day");
		
		foreach($list as $key=>$txt){
			$val = (isset($sett[$key])) ? $sett[$key]:""; $opts="";
			
			if($key=="loan_notify"){
				foreach(["none"=>"None","leaders"=>"Leadership","all"=>"All Members"] as $ky=>$tvl){
					$cnd = ($ky==$val) ? "selected":"";
					$opts.= "<option value='$ky' $cnd>$tvl</option>";
				}
				$lis.= "<p>$txt<br><select style='width:100%' name='grpset[$key]' $lock required>$opts</select></p>";
			}
			elseif($key=="loan_apply"){
				foreach(["meetday"=>"Group Meeting Date","all"=>"Any Day (Open)"] as $ky=>$tvl){
					$cnd = ($ky==$val) ? "selected":"";
					$opts.= "<option value='$ky' $cnd>$tvl</option>";
				}
				$lis.= "<p>$txt<br><select style='width:100%' name='grpset[$key]' $lock required>$opts</select></p>";
			}
			elseif($key=="guarantors"){
				foreach(["none"=>"No Guarantorship","all"=>"All Members"] as $ky=>$tvl){
					$cnd = ($ky==$val) ? "selected":"";
					$opts.= "<option value='$ky' $cnd>$tvl</option>";
				}
				$lis.= "<p>$txt<br><select style='width:100%' name='grpset[$key]' $lock required>$opts</select></p>";
			}
			else{
				$add = (in_array($key,[])) ? "":"onkeyup=\"valid('$key',this.value)\"";
				$lis.= "<p>$txt<br><input type='text' style='width:100%' value='$val' $add id='$key' name='grpset[$key]' $lock required></p>";
			}
		}
		
		$btx = ($set) ? "<p style='text-align:right'><button class='btnn'><i class='bi-save'></i> Save</button></p>":
		"<p style='padding:10px;color:#ff4500;text-align:center;background:#FFDAB9'><i class='bi-exclamation-circle'></i> Update permission denied</p>";
		
		echo "<div style='padding:20px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;font-size:22px'>Group Settings</h3><br>
			<form method='post' id='gform' onsubmit='savegroupset(event)'>
				<input type='hidden' name='cgrpid' value='$gid'>$lis<br> $btx
			</form><br>
		</div>";
	}
	
	# group lending Setup
	if(isset($_GET['cgroups'])){
		$db = new DBO();
		$ctbl = "cgroups$cid";
		$exclude = array("id","def","status","time","branch","creator","group_id");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; 
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.= ($tbl!=$ctbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!isset($def[$field])) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (isset($def[$field])) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(isset($dsrc[$field])){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp = ($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.= "<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%;padding:8px' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Client Groups Form <button class='bts' style='float:right;' onclick=\"popupload('setup.php?setgrps')\">
				<i class='fa fa-wrench'></i> Group Settings</button></h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$ctbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'> $trs </table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		savelog($sid,"Accessed Group Lending setup page");
	}
	
	# staff structure
	if(isset($_GET['staff'])){
		$db = new DBO();
		$stbl="org".$cid."_staff";
		$exclude = array("id","branch","config","roles","access_level","position","status","time","leaves");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$stbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","email"=>"email","gender"=>"text","jobno"=>"text","branch"=>"number",
		"roles"=>"textarea","access_level"=>"text","config"=>"textarea","position"=>"text","entry_date"=>"date","leaves"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; $dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"images/*");
		$fields = (count($fields)==0) ? array("field_name"=>"text"):$fields;
		
		$trs=$temp="";
		foreach(INPUTS as $type=>$desc){
			$temp.="<option value='$type'>$desc</option>";
		}
		
		$tbls = "<option value='0'>-- Select Record --</option>";
		foreach(SYS_TABLES as $tbl=>$name){
			$tbls.=($tbl!=$stbl) ? "<option value='$tbl'>$name Record</option>":"";
		}
		
		$cols = $db->tableFields(2,$stbl);   
		foreach($fields as $col=>$dts){
			if(!in_array($col,$cols)){ unset($fields[$col]); }
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); 
				$unq=rand(12345678,87654321); $ran=rand(12345678,87654321); $opts=$src="";
				if(array_key_exists($field,$def)){ $opts="<option value='$dtype'>".INPUTS[$dtype]."</option>"; }
				else{
					foreach(INPUTS as $type=>$desc){
						$cnd = ($type==$dtype) ? "selected":"";
						$opts.="<option value='$type' $cnd>$desc</option>";
					}
				}
				
				$del = (!array_key_exists($field,$def)) ? "<i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' 
				title='Remove $fname' onclick=\"deltblfield('$unq')\">":"";
				$add = (array_key_exists($field,$def)) ? "readonly title='Non-editable' style='width:100%;cursor:not-allowed'":"style='width:100%'";
				if(array_key_exists($field,$dsrc)){
					$src = (explode(":",$dsrc[$field])[0]=="select") ? "<input type='text' style='width:100%;' placeholder='Source Data separate by comma' 
					name='selects[$ran]' value=\"".prepare(explode(":",$dsrc[$field])[1])."\" required>":"";
					if(explode(":",$dsrc[$field])[0]=="tbl"){
						$val=explode(".",explode(":",$dsrc[$field])[1]); $tbl=$val[0]; $tf=$val[1]; $store = (substr($tbl,0,3)=="org") ? 2:1;
						$src = "<select style='width:100%' name='linkto[$ran]'>";
						foreach($db->tableFields($store,$tbl) as $fld){
							$cnd = ($fld==$tf) ? "selected":""; $txt=ucwords(str_replace("_"," ",$fld)." "."(from ".SYS_TABLES[$tbl].")");
							$src.=(!in_array($fld,SKIP_FIELDS) && $tbl.$fld!="branchesclient") ? "<option value='$tbl.$fld' $cnd>$txt</option>":"";
						}
						$src.="</select>";
					}
				}
				
				$inp=($dtype=="textarea") ? "<textarea class='mssg' name='fnames[$ran]' $add required>$fname</textarea>":
				"<input type='text' name='fnames[$ran]' $add value=\"$fname\" required>";
				$trs.="<tr valign='bottom' id='$unq'><td style='width:45%'>$fname<br>$inp</td><td>Data type<br>
				<select style='width:100%' name='datatypes[$ran]' onchange=\"checkselect('$ran',this.value)\">$opts</select>
				<span id='$ran'>$src</span></td><td>$del</td></tr> <input type='hidden' name='prevtbl[$ran]' value='$field'>"; 
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn Staff Bio-data</h3><hr>
				<form method='post' id='tblform' onsubmit='savetblform(event)'>
					<input type='hidden' name='deftbl' value='".json_encode($def,1)."'> <input type='hidden' name='tablename' value='$stbl:2'>
					<textarea style='display:none' id='tmpfields'>$temp</textarea> <textarea style='display:none' id='tables'>$tbls</textarea>
					<table style='width:100%;max-width:600px;margin:0 auto' cellpadding='8' class='tblstr'>
						<caption style='caption-side:top'>Create Form structure to capture Information for storage</caption> $trs 
					</table><br>
					<p style='text-align:right;max-width:600px;margin:0 auto'> <input type='hidden' name='sid' value='$sid'>
					<span class='bts' style='padding:6px;cursor:pointer' onclick='addtblfield()'><i class='fa fa-plus'></i> Add Field</span>
					<button class='btnn' style='padding:5px;margin-left:10px'>Save</button></p>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed Staff Bio-data settings");
	}
	
	if(isset($_GET['view'])){
		$perms = getroles(explode(",",staffInfo($sid)['roles']));
		$lsett = (in_array("change loan settings",$perms)) ? "loadpage('setup.php?loansett')":"toast('You dont have permission to Configure Loans')";
		$advance = (in_array("update advance settings",$perms)) ? "loadpage('setup.php?advance')":"toast('You dont have permission to change advance settings')";
		$perf = (in_array("update KPI analytics",$perms)) ? "loadpage('setup.php?analytics')":"toast('You dont have permission to change analytics settings')";
		$leavapp = (in_array("update leave settings",$perms)) ? "loadpage('setup.php?leaveapp')":"toast('You dont have permission to Setup Leave Approvals')";
		
		echo "<div class='cardv' style='max-width:900px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;'>$backbtn System Setup</h3><hr>
				<div class='row'>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?info')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-institution'></i>
						Company Settings</h3><p style='margin-bottom:0px;font-size:14px'>Set Company name, Logo, address, Contacts & About us</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?staff')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-sitemap'></i>
						Staff Structure</h3><p style='margin-bottom:0px;font-size:14px'>Set Company staff hierachy & personal details form</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?access')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-key'></i>
						System Access & User roles</h3><p style='margin-bottom:0px;font-size:14px'>Set Login OTP & user access permissions in the system</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?clients')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-id-card-o'></i>
						Client Bio-data Setup</h3><p style='margin-bottom:0px;font-size:14px'>Design Client registration form details & structure</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?loans')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-book'></i>
						Loan form Setup</h3><p style='margin-bottom:0px;font-size:14px'>Design the structure and details to be captured in Loan Form</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?cgroups')\"><h3 style='font-size:17px;color:#191970'> <i class='bi-people'></i>
						Group Lending</h3><p style='margin-bottom:0px;font-size:14px'>Design client groups form & related group settings</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?products')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-briefcase'></i>
						Loans Products</h3><p style='margin-bottom:0px;font-size:14px'>Setup the Loan products,interest rates,duty fees & Prepayments</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?accounts')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-bar-chart'></i>
						Accounting</h3><p style='margin-bottom:0px;font-size:14px'>Design Requisition forms & Petty cashbook settings</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$advance\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-money'></i>
						Salary Advances</h3><p style='margin-bottom:0px;font-size:14px'>Design Salary advance Form & Application rules</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?leaves')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-bed'></i>
						Staff Leaves</h3><p style='margin-bottom:0px;font-size:14px'>Design Leave application Form,conditions & yearly leave days</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$leavapp\"><h3 style='font-size:17px;color:#191970'> <i class='bi-tools'></i>
						Leave Settings</h3><p style='margin-bottom:0px;font-size:14px'>Setup Staff Leaves approval workflow</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$perf\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-line-chart'></i>
						Staff Performance</h3><p style='margin-bottom:0px;font-size:14px'>Setup performance Indicators for the staff & BI Analysis</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"$lsett\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-bookmark-o'></i>
						Loan Settings</h3><p style='margin-bottom:0px;font-size:14px'>Configure Loan settings e.g Checkoffs & Reschedule</p>
						</div>
					</div>
					<div class='col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6 mt-3'>
						<div class='jdiv' onclick=\"loadpage('setup.php?gensett')\"><h3 style='font-size:17px;color:#191970'> <i class='fa fa-wrench'></i>
						General Settings</h3><p style='margin-bottom:0px;font-size:14px'>Set Payment automation,approval levels & Client royalty points</p>
						</div>
					</div>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed system setup settings page");
	}

?>

<script>
	
	function savesetting(sett){
		$.ajax({
			method:"post",url:path()+"dbsave/setup.php",data:{savesetting:sett},
			beforeSend:function(){ progress("Updating...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			toast(res);
		});
	}
	
	function saveperms(e){
		e.preventDefault();
		if(!checkboxes("roles[]")){ alert("No roles assigned! Please check atleast one Role"); }
		else{
			if(confirm("Update access permissions for staff group?")){
				var data=$("#pform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/setup.php",data:data,
					beforeSend:function(){ progress("Updating...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Successfuly updated"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function setspecapprover(){
		var amnt = $("#spam").val().trim(), app=$("#spac").val();
		if(amnt==""){ $("#spam").focus(); }
		else{ savesetting("threshold_approver:"+app+"%"+amnt); }
	}
	
	function setsystime(e){
		e.preventDefault();
		if(confirm("Update system access restriction settings?")){
			var data = $("#stfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Successfuly updated"); }
				else{ alert(res); }
			});
		}
	}
	
	function savegroupset(e){
		e.preventDefault();
		if(confirm("Update Group settings?")){
			var data = $("#gform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Successfuly updated"); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function savevars(e){
		e.preventDefault();
		if(confirm("Update interest range?")){
			var data=$("#pvform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Successfuly updated"); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function togprods(){
		if($("#drops").is(":visible")){ $("#drops").hide(); $("#intr").show(); $("#intr").val(""); }
		else{ $("#drops").show(); $("#intr").hide(); $("#intr").val(0); }
	}
	
	function addprow(){
		var did = Date.now();
		$(".pvtbl").append("<tr><td style='width:40%'><input type='number' style='width:100%' name='pday[]' value='1' required></td>"+
		"<td><input type='text' id='"+did+"' onkeyup=\"valid('"+did+"',this.value)\" style='width:100%' name='pchaj[]' value='10%' required></td></tr>");
	}
	
	function savepsett(e){
		e.preventDefault();
		if(confirm("Update Performance control settings?")){
			var data = $("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function savekpis(e){
		e.preventDefault();
		if(confirm("Update Performance analytics settings?")){
			var data = $("#pafom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveotpset(){
		var data = "otpreq="; 
		$(".otps:checked").each(function (){ data+=this.value+","; });
		if(confirm("Update staff groups to require OTP verifications?")){
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function savereqn(){
		if(!checkboxes("reqapply[]")){
			alert("Please select atleast one staff group");
		}
		else{
			var data = "savereqn="; 
			$(".qns:checked").each(function (){ data+=this.value+","; });
			if(confirm("Update staff groups to apply requisition?")){
				$.ajax({
					method:"post",url:path()+"dbsave/setup.php",data:data,
					beforeSend:function(){ progress("Updating...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Updated successfully!"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function savebypass(e){
		e.preventDefault();
		if(!checkboxes("limbypass[]")){ toast("No staff selected!"); }
		else{
			if(confirm("Update Loan limit settings?")){
				var data=$("#bform").serialize(); 
				$.ajax({
					method:"post",url:path()+"dbsave/setup.php",data:data,
					beforeSend:function(){ progress("Updating...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Updated successfully!"); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function saveconst(e){
		e.preventDefault();
		if(confirm("Update system constants settings?")){
			var data = $("#cvfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function savelimit(e){
		e.preventDefault();
		if(confirm("Update Loan limit settings?")){
			var data=$("#limfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function savehlevel(e){
		e.preventDefault();
		if(confirm("Update higher level approvals?")){
			var data=$("#hform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function saveanalytics(e){
		e.preventDefault();
		if(confirm("Update performance formulas settings?")){
			var data=$("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveawarding(e){
		e.preventDefault();
		if(confirm("This will not modify already awarded points, proceed to update?")){
			var data=$("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveadvance(e){
		e.preventDefault();
		if(confirm("Update advance settings?")){
			var data=$("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Updated successfully!"); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function savecats(e){
		e.preventDefault();
		if(confirm("Save Leave Category?")){
			var data=$("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Saving...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast(res); closepop(); loadpage("setup.php?leavesett"); }
				else{ alert(res); }
			});
		}
	}
	
	function saveprod(e,cat){
		e.preventDefault();
		var list = {client:0,app:1,staff:2};
		if(confirm("Save "+cat+" loan product?")){
			var data = $("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Saving...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast(res); closepop(); loadpage("setup.php?products="+list[cat]); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function saveapproval(e,fom,ttl,callback){
		e.preventDefault();
		if(confirm("Update "+ttl+" approvals?")){
			var data=$("#"+fom).serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){progress("Updating...please wait");},
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Success"); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function savesms(){
		if(confirm("Save changes to SMS Structure?")){
			var data = $("#sform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){progress("Saving...please wait");},
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res);
			});
		}
	}
	
	function saveprepay(e){
		e.preventDefault();
		if(confirm("Save prepayment days?")){
			var data=$("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){progress("Saving...please wait");},
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ toast("Success"); closepop(); loadpage("setup.php?products=prepay"); }
				else{ alert(res); }
			});
		}
	}
	
	function delfield(id){
		if(confirm("Sure to remove field row?")){
			$('#'+id).remove();
		}
	}
	
	function delpay(pay){
		if(confirm("Sure to remove payment from account books?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{delpay:pay},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); 
				if(res.trim()=="success"){ window.location.reload(); }
			});
		}
	}
	
	function delprod(id){
		if(confirm("Sure to delete loan product?")){
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:{delprod:id},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); if(res.trim()=="success"){ $("#tr"+id).remove(); }
			});
		}
	}
	
	function savetblform(e){
		e.preventDefault();
		if(confirm("Sure to update structure?")){
			var data=$("#tblform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); 
			});
		}
	}
	
	function addfield(cat){
		var did = Date.now(), opts=(cat=="client" || cat=="app") ? "<option value='0'>New Clients only</option>":""; 
		$(".ptbl").append("<tr id='"+did+"' valign='top'><td><select name='charges["+did+"]' style='width:100%;font-size:15px' id='o"+did+"' onchange=\"ckcharge('"+did+"',this.value)\">"+
		"<option value='1'>@Loan application</option>"+opts+"<option value='6'>Repeat applications</option><option value='2'>Every Installment</option><option value='3'>@Certain Installment</option>"+
		"<option value='4'>Loan deduction</option><option value='5'>Added to Loan</option></select><span id='s"+did+"'></span></td><td><input type='text' placeholder='Description'"+
		"style='width:100%' name='cdesc["+did+"]' required></td><td><input type='text' placeholder='Fee Charged' id='f"+did+"' onkeyup=\"valid('f"+did+"',this.value)\" "+
		"style='width:100%' name='fees["+did+"]' required><br><a href='javascript:void(0)'style='float:right;color:#ff4500' onclick=\"$('#"+did+"').remove()\">Remove</a></td></tr>");
	}
	
	function ckcharge(id,val){
		if(val==3){
			var ins = prompt("Enter the installment",1);
			if(parseInt(ins)>0){
				var inst = parseInt(ins),dval=$("#v"+id).val(); 
				if(dval==null){
					$("#s"+id).html("<br><i style='font-size:13px;color:#008080'>@ Installment "+inst+"</i> <input type='hidden' id='v"+id+"' name='"+id+"' value='"+inst+"'>");
				}
				else{ $("#s"+id).html("<br><i style='font-size:13px;color:#008080'>@ Installment "+inst+"</i>"); $("#v"+id).val(inst); }
			}
			else{ toast("Incorrect Value"); $("#o"+id).val("1"); }
		}
		else{ $("#s"+id).html(""); }
	}
	
	function addapprow(tbl){
		var did=Date.now(),nxt=parseInt(_("apps").value.trim())+1; _("apps").value=nxt;
		$("."+tbl).append("<tr valign='top' id='"+did+"'><td>Approval "+nxt+"</td><td><select name='approvals[]' style='width:100%'>"+_("defs").value+
		"</select> <br><a href='javascript:void(0)' onclick=\"$('#"+did+"').remove()\" style='float:right;color:#ff4500'>Remove</a></td></tr>");
	}
	
	function checkselect(pos,val){
		if(val=="select"){
			$("#"+pos).html("<input type='text' style='width:100%;' placeholder='Source Data separate by comma' name='selects["+pos+"]' required>");
		}
		else if(val=="link"){ $("#"+pos).html("<select style='width:100%' onchange=\"getblfields('"+pos+"',this.value)\">"+$("#tables").val()+"</select>"); }
		else{ $("#"+pos).html(""); }
	}
	
	function getblfields(pos,tbl){
		$.ajax({
			method:"post",url:path()+"dbsave/setup.php",data:{tblfields:tbl},
			beforeSend:function(){ progress("Fetching...please wait"); },
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed to load fetch field names");
		}).done(function(res){
			$("#"+pos).html("<select style='width:100%' name='linkto["+pos+"]'>"+res.trim()+"</select>");
		});
	}
	
	function addtblfield(tp){
		var opt = _("tmpfields").value;
		var did = Date.now(); var cid=Date.now()+500, adcl=(tp=="template") ? "<td><input type='checkbox' name='reuse["+cid+"]'></checkbox></td>":"";
		var inp = "<tr valign='bottom' id='"+did+"'>"+adcl+"<td>Field Name<br><input type='text' name='fnames["+cid+"]' placeholder='Type Field Name' required></td><td>Data type<br>"+
		"<select style='width:100%' name='datatypes["+cid+"]' onchange=\"checkselect('"+cid+"',this.value)\">"+opt+"</select><span id='"+cid+"'></span></td>"+
		"<td><i class='bi-x-lg' style='color:#DC143C;cursor:pointer;font-size:20px' title='Remove' onclick=\"deltblfield('"+did+"')\"></td></tr>";
		$(".tblstr").append(inp);
	}
	
	function deltblfield(id){
		if(confirm("Sure to remove Field?")){ $('#'+id).remove(); }
	}
	
	function changelogo(){
		var img = _("logo").files[0];
		if(img!=null){
			if(confirm("Change Logo with selected Photo?")){
				var formdata=new FormData();
				var xhr=new XMLHttpRequest();
				xhr.upload.addEventListener("progress",profprogress,false);
				xhr.addEventListener("load",profdone,false);
				xhr.addEventListener("error",proferror,false);
				xhr.addEventListener("abort",profabort,false);
				formdata.append("logo",img);
				xhr.onload=function(){
					if(this.responseText.trim()=="success"){
						toast("Updated successfull"); loadpage('setup.php?info');
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/setup.php?usa=<?php echo $sid; ?>",true);
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
	
	function saveinfo(e,fom){
		e.preventDefault();
		if(confirm("Sure to update info?")){
			var data=$("#"+fom).serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res);
			});
		}
	}
	
</script>