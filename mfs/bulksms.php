<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if(!$sid){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# sms logs
	if(isset($_GET['logs'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$frm = (isset($_GET['fro'])) ? trim($_GET['fro']):date("Y-m-d");
		$dtu = (isset($_GET['dto'])) ? trim($_GET['dto']):date("Y-m-d");
		$ord = (isset($_GET['ord'])) ? trim($_GET['ord']):"ASC";
		$str = (isset($_GET['str'])) ? clean($_GET['str']):"";
		if($str){ $dtu=date("Y-m-d"); $frm=date("Y-m-d",time()-(86400*30)); }
		$fro = strtotime($frm); $dto=strtotime($dtu);  
		$fro = ($dto<$fro) ? $dto:$fro; $dto=($fro>$dto) ? $fro:$dto; $dto+=86399;
		
		$lim = explode(",",trim(str_replace("LIMIT","",getLimit($page,20))));
		$res = smslogs(["logfrom"=>$fro,"logto"=>$dto,"limit"=>[$lim[0],$lim[1]],"orderby"=>$ord,"search"=>$str]);
		$data = ($res['response']=="success") ? $res['data']:[];
		$tdy = date('Y-m-d'); $trs=""; $tcost=$tots=0;
	
		foreach($data as $row){
			$day=$row['time']; $mssg=nl2br(prepare($row['message'])); $snid=$row['senderId']; $tots=explode("/",$row['count'])[1];
			$st=$row['status']; $cost=explode("/",$row['cost']); $fon=$row['recipient']; $len=strlen(strip_tags($mssg));
			$to = (substr($fon,0,3)==254) ? $fon:"254".ltrim($fon,"0"); $tcost=$cost[1];
			
			if(strpos(strtolower($mssg),"use code")!==false){
			    $def = explode(" ",$mssg);
			    foreach($def as $key=>$word){
			        if(is_numeric($word)){ $def[$key]="******"; }
			    }
			    $mssg = implode(" ",$def);
			}
			
			$tot = ($len<161) ? "1 Message ($len characters)":ceil($len/160)." Messages ($len characters)";
			$css = "style='margin:0px;padding:8px 0px;border-bottom:1px solid #dcdcdc'";
			$state = ($st==200) ? "<span style='background:green;padding:3px;color:#fff;border-radius:3px'>Success</span>":
			"<span style='background:#4682b4;padding:3px;color:#fffborder-radius:3px'>Sent</span>";
			
			$trs.= ($_GET['md']<601) ? "<tr><td colspan='6'><p $css>$day</p> <div style='padding:10px 0px 0px 0px;border-bottom:1px solid #dcdcdc'>
			$mssg <p style='padding:5px;background:#dcdcdc;color:#191970;margin:10px 0px 0px 0px'>$tot</p></div>
			<p $css>From: $snid</p><p $css>To: +$to</p><p $css>KES $cost[0]</p><p style='margin-top:10px'>$state</p></td></tr>":
			"<tr><td>$day</td><td>$mssg<p style='padding:3px;background:#dcdcdc;color:#191970;margin:10px 0px 0px 0px'>$tot</p></td>
			<td>$snid</td><td>+$to</td><td>KES $cost[0]</td><td>$state</td></tr>";
		}
		
		$trs = ($trs) ? $trs:"<tr colspan='6'><td>".prepare($res['response'])."</td></tr>";
		$dat = ($_GET['md']<601) ? $trs:"<tr style='background:#e6e6fa;font-weight:bold;color:#191970'><td>Date</td><td>Message</td><td>From</td><td>To</td>
		<td>Cost</td><td>Status</td></tr>$trs"; $lis="";
		
		foreach(array("ASC"=>"Oldest","DESC"=>"Newest") as $key=>$txt){
			$cnd = ($key==$ord) ? "selected":""; $lis.="<option value='$key' $cnd>$txt</option>";
		}
		
		$frd = date("Y-m-d",$fro); $dtd=date("Y-m-d",$dto); $vstr=prepare($str); $vst=urlencode($vstr);
		echo "<div class='container cardv' style='max-width:1400px;min-height:400px;overflow:auto;'>
			<div style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn SMS Logs <b style='float:right;font-size:17px'>$tots (KES $tcost)</b></h3><hr style='margin:0px'>
				<div style='width:100%;overflow:auto'>
					<table style='width:100%;font-size:14px;' cellpadding='7' class='table-striped'> 
						<caption style='caption-side:top;color:#191970;'>
							<input type='date' value='$frd' max='$tdy' id='fro' style='width:130px' onchange=\"setday()\">
							<input type='date' value='$dtd' max='$tdy' id='dto' style='width:130px' onchange=\"setday()\">
							<select style='width:90px;padding:6px 4px;float:right' onchange=\"loadpage('bulksms.php?logs&fro=$frd&dto=$dtd&str=$vst&ord='+this.value)\">$lis</select>
							<input type='search' style='float:right;width:150px;font-size:15px;padding:4px 6px;margin-right:5px;' placeholder='&#xf002; Search' value=\"$vstr\"
							onsearch=\"loadpage('bulksms.php?logs&fro=$frd&dto=$dtd&str='+cleanstr(this.value))\">
						</caption> $dat
					</table>
				</div>
			</div>".getLimitDiv($page,25,$tots,"bulksms.php?logs&fro=$frd&dto=$dtd&ord=$ord&str=$vst")."
		</div>
		<script>
			function setday(){
				var fro = $('#fro').val().trim(), dto=$('#dto').val().trim();
				loadpage('bulksms.php?logs&fro='+fro+'&dto='+dto);
			}
		</script>";
		savelog($sid,"Viewed SMS Logs from $frm to $dtu"); 
	}
	
	# select clients to send sms to
	if(isset($_GET['getclients'])){
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$stid = (isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		$reg = (isset($_GET['reg'])) ? trim($_GET["reg"]):0; 
		$ctbl = "org".$cid."_clients";
		
		$res = fetchSQLite("tempinfo","SELECT *FROM `tempdata` WHERE `user`='$sid'"); 
		$mssg = ($res) ? prepare($res[0]['data']):"";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
	
		$me = staffInfo($sid); $access=$me['access_level']; 
		$perms = getroles(explode(",",$me['roles'])); $cnf=json_decode($me["config"],1);
		$titles = array(0=>"Dormant Clients",1=>"Active Clients",2=>"Suspended Clients");
		if($access=="region" && isset($cnf["region"])){ $reg=$cnf["region"]; }
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$show = ($reg) ? setRegion($reg):$show;
		$cond = ($me['access_level']=="portfolio") ? "`loan_officer`='$sid'":$show;
		$cond = ($stid) ? "`loan_officer`='$stid'":$cond;
		
		$offs=""; $lis=[]; $all=$no=0;
		if($str){
			$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond AND (`name` LIKE '%$str%' OR `contact` LIKE '%$str%' OR `idno` LIKE '%$str%')");
			if($res){
				foreach($res as $row){
					$fon=$row['contact']; $name=prepare(ucwords($row['name']));
					$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' checked name='sendto[]' value='$fon'> &nbsp; $name</checkbox></li>";
				}
			}
		}
		else{
			$bcs = base64_encode($cond); $data=$defs=[];
			$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` WHERE $cond"); $all=($res) ? $res[0]['total']:0;
			if($all){ 
				$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='all=$bcs' checked> 
				&nbsp; All Clients ($all)</checkbox></li>";
			}
			
			$sql = $db->query(2,"SELECT status,COUNT(*) AS total FROM `$ctbl` WHERE $cond GROUP BY status");
			if($sql){
				foreach($sql as $row){
					$st=$row['status']; $tot=$row['total'];
					$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='status=$st:$bcs'> &nbsp; ".$titles[$st]." ($tot)</checkbox></li>";
				}
			}
			
			$show = ($me['access_level']=="hq") ? "":"AND ln.branch='".$me['branch']."'";
			$show = ($reg) ? "AND ".setRegion($reg,"ln.branch"):$show;
			$cond1 = ($me['access_level']=="portfolio") ? "AND ln.loan_officer='$sid'":$show;
			$cond1 = ($stid) ? "AND ln.loan_officer='$stid'":$cond1;
			
			# weekdays groups
			$res = $db->query(2,"SELECT ln.phone,from_unixtime(sd.day,'%W') AS dow FROM `org".$cid."_loans` AS ln INNER JOIN `org".$cid."_schedule` AS sd ON ln.loan=sd.loan 
			WHERE ln.balance>0 $cond1  GROUP BY sd.loan,dow");
			if($res){
				foreach($res as $row){
					$day = $row['dow']; $phone=$row['phone'];
					$data[$day][$phone]=$phone;
				}
			}
			
			foreach($data as $day=>$fons){
				$json = json_encode(array_values($fons),1); $tot=count($fons); 
				$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='json=$json'> &nbsp; $day Clients ($tot)</checkbox></li>";
			}
			
			# default clients
			$res = $db->query(2,"SELECT ln.phone FROM `org".$cid."_loans` AS ln INNER JOIN `org".$cid."_schedule` AS sd ON ln.loan=sd.loan 
			WHERE ln.balance>0 $cond1 AND sd.balance>0 AND ln.expiry<".strtotime(date("Y-M-d"))." GROUP BY sd.loan");
			if($res){
				foreach($res as $row){ $defs[]=$row['phone']; }
				$json=json_encode($defs,1); $tot=count($defs);
				$lis[] = "<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='json=$json'> &nbsp; Default Clients ($tot)</checkbox></li>";
			}
			
			# client groups
			if($db->istable(2,"client_groups$cid")){
				$res = $db->query(2,"SELECT *,COUNT(*) AS total FROM `client_groups$cid` GROUP BY gid ORDER BY `name` ASC");
				if($res){
					foreach($res as $row){
						$grup = prepare(ucfirst($row['name'])); $tot=$row['total']; $gid=$row['gid'];
						$lis[]="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='group=$gid'> &nbsp; $grup ($tot)</checkbox></li>";
					}
				}
			}
			
		}
		
		if(in_array($access,["hq","region"])){
			$brans=""; $rls="<option value='0'>All Regions</option>";
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid'");
				if($sql){
					foreach($sql as $row){
						$id=$row["id"]; $cnd=($id==$reg) ? "selected":"";
						$rls.= "<option value='$id' $cnd>".prepare(ucfirst($row["name"]))."</option>";
					}
					$brans = "<select style='width:150px;margin-right:7px' onchange=\"loadpage('bulksms.php?getclients&reg='+this.value.trim())\">$rls</select>";
				}
			}
			
			$cond2 = ($reg) ? "AND ".setRegion($reg,"id"):"";
			$brn = "<option value='0'>All Branches</option>"; 
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $cond2");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans.= "<select style='width:140px' onchange=\"loadpage('bulksms.php?getclients&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($access=="hq") ? $bran:$me['branch'];
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ctbl` WHERE `branch`='$brn'");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('bulksms.php?getclients&bran=$bran&ofid='+this.value.trim())\">$opts</select>";
			}
		}
		
		if(count($lis)>3){
			$all = count($lis); $now=date("Y-m-d")."T".date("H:i");
			$parts = array_chunk($lis,ceil($all/2)+1);
			$list1 = implode("",$parts[0]); $list2 = implode("",$parts[1]);
			$scd = (in_array("create sms schedule",$perms)) ? "<p>Send SMS on<br><input type='datetime-local' style='padding:4px;width:100%' 
			name='sendtm' value='$now' min='$now' required></p>":"<input type='hidden' name='sendtm' value='$now'>";
			
			$data = "<div class='col-12 col-lg-6'><div style='margin:0 auto;max-width:280px'> $list1 </div></div>
			<div class='col-12 col-lg-6'> <div style='margin:0 auto;max-width:280px'> $list2 <br>$scd
			<p style='text-align:right;'> <button class='btnn'>Send</button></p></div></div>";
		}
		else{
			$trs = (count($lis)) ? implode("",$lis):"No Clients Found"; $now=date("Y-m-d")."T".date("H:i");
			$scd = (in_array("create sms schedule",$perms)) ? "<tr><td>Send SMS on<br><input type='datetime-local' style='padding:4px;width:100%' name='sendtm' 
			value='$now' min='$now' required></td></tr>":"<input type='hidden' name='sendtm' value='$now'>";
			$btx = (count($lis)) ? "$scd <tr><td><p style='text-align:right'> <button class='btnn'>Send</button></p></td></tr>":"";
			$data = "<table style='min-width:250px;margin:0 auto' cellpadding='10'><tr><td>$trs</td></tr> $btx</table>";
		}
		
		echo "<div class='cardv' style='max-width:800px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:7px;min-width:400px'>
				<h3 style='font-size:22px;color:#191970'>Select clients to send SMS</h3>
				<p style='margin-top:20px'>$brans $offs 
				<input type='search' onkeyup=\"fsearch(event,'bulksms.php?getclients&str='+cleanstr(this.value))\"
				onsearch=\"loadpage('bulksms.php?getclients&str='+cleanstr(this.value))\" value='".prepare($str)."'
				style='float:right;width:170px;padding:4px;font-size:16px;' placeholder='&#xf002; Search client'></p><hr>
				<form method='post' id='sfom' onsubmit='smsclients(event)'>
					<input type='hidden' value='sendto' name='smto'> <input type='hidden' name='spos' value='$str'>
					<textarea name='smssg' style='display:none'>$mssg</textarea>
					<div class='row'> $data </div>
				</form>
			</div>
		</div>";
	}
	
	# send sms to staff or from excel
	if(isset($_GET['smrecips'])){
		$res = fetchSQLite("tempinfo","SELECT *FROM `tempdata` WHERE `user`='$sid'"); 
		$mssg = ($res) ? prepare($res[0]['data']):"";
		$to = trim($_GET['sto']); $now=date("Y-m-d")."T".date("H:i");
		
		if($to=="xls"){
			echo "<div style='max-width:300px;padding:10px;margin:0 auto;font-family:cambria'><br>
				<h4 style='color:#191970;font-weight:bold'>Load Contacts from Excel File</h4><br>
				<form method='post' id='xlfom' onsubmit='uploadxls(event)'>
					<textarea name='smssg' style='display:none'>$mssg</textarea>
					<p style='color:#191970;font-size:14px'>** NOTE: Your Excel File must have maximum of 2 Columns Starting from A for Contact & name(optional) **</p>
					<p>Select Document<br><input type='file' name='xls' id='xls' accept='.csv,.xlsx,.xls' required></p>
					<p>Contact Field <span style='float:right'>Name Field</span><br>
					<select style='width:45%' name='contfld'><option value='0'>Column A</option><option value='1'>Column B</option></select>
					<select style='width:45%;float:right' name='namefld'><option value='10'>-- None --</option><option value='0'>Column A</option>
					<option value='1'>Column B</option></select></p>
					<p>Send SMS on<br><input type='datetime-local' style='width:100%' name='sendtm' value='$now' min='$now' required></p><br>
					<p style='text-align:right'><button class='btnn'>Upload</button></p><br>
				</form>
			</div>";
		}
		else{
			$me = staffInfo($sid); $lis=[];
			$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' $cond");
			foreach($res as $row){
				$cont=$row['contact']; $name=prepare(ucwords($row['name']));
				$lis[]="<li style='margin:5px 0px;list-style:none'><input type='checkbox' name='staff[]' value='$cont' checked> &nbsp; $name</li>";
			}
			
			$all = array_chunk($lis,ceil(count($lis)/2)); 
			$td1 = implode("",$all[0]); $td2 = implode("",$all[1]);
			$trs = "<tr valign='top'><td>$td1</td><td>$td2</td></tr>";
			
			echo "<div style='max-width:500px;padding:10px;margin:0 auto;font-family:cambria'><br>
				<h4 style='color:#191970;font-weight:bold'>Send SMS to Employees</h4><br>
				<form method='post' id='sform' onsubmit='smstaff(event)'>
					<textarea name='smssg' style='display:none'>$mssg</textarea> <input type='hidden' name='smto' value='staff'>
					<table style='width:100%' cellpadding='5'> $trs
						<tr valign='bottom'><td style='width:50%'>Send SMS on<br>
						<input type='datetime-local' style='width:95%' name='sendtm' value='$now' min='$now' required></td>
						<td style='text-align:center'><br><button class='btnn'>Send</button></td></tr>
					</table><br>
				</form><br>
			</div>";
		}
	}
	
	# send sms form
	if(isset($_GET['sendsms'])){
		$to=trim($_GET['sendsms']);
		$tid=(isset($_GET['template'])) ? trim($_GET['template']):0; 
		$text=""; $opts="<option value='0'>-- Select Template --</option> ";
		
		if($tid){
			$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `id`='$tid'");
			$text=prepare($res[0]['message']);
		}
		
		$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND NOT `name`='inst200'");
		if($res){
			foreach($res as $row){
				$tmid=$row['id']; $tname=prepare(ucwords($row['name'])); $cnd=($tmid==$tid) ? "selected":"";
				$opts.="<option value='$tmid' $cnd>$tname</option>";
			}
		}
		
		$chars=strlen(strip_tags(stripslashes($text)));
		$len=($chars<161) ? "$chars chars, 1 Mssg":"$chars chars, ".ceil($chars/160)." Mssgs";
		$btn=($to) ? "<i class='fa fa-mail-forward'></i> Send SMS":"<i class='fa fa-arrow-right'></i> Next";
		
		echo "<div style='padding:10px'><br>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Prepare SMS to Send</h3><br>
			<div style='margin:0 auto;max-width:350px'>
				<form method='post' id='sform' onsubmit='gonext(event)'>
					<p>Type Message (Use keywords <b>CLIENT/STAFF, IDNO, LOAN_BALANCE, ARREARS, LOAN_AMOUNT</b> to pick Client {First Name, Idno, Loan balance, Arrears,
					Loan amount} or Staff Name respectively)<br>
					<textarea class='mssg' name='tmpmssg' id='tsms' style='height:130px' onkeyup=\"countext('tsm',this.value)\" maxlength='630' required>$text</textarea>
					<br><span style='color:#191970;float:right' id='tsm'>$len</span></p><br>
					<p>Load from Template <span style='float:right'>Send To</span><br>
					<select style='width:60%' onchange=\"popupload('bulksms.php?sendsms=$to&template='+this.value)\">$opts</select>
					<select style='width:39%;float:right' id='sto'><option value='clients'>Clients</option><option value='staff'>Employees</option>
					<option value='xls'>Excel File</option></select></p><br>
					<p style='text-align:right'><button class='btnn'>$btn</button></p><br>
				</form>
			</div>
		</div>";
		
		savelog($sid,"Accessed Send SMS Form");
	}
	
	# manage schedule
	if(isset($_GET['schedule'])){
		$data = "";
		$perms = getroles(explode(",",staffInfo($sid)['roles']));
		$qri = $db->query(1,"SELECT *FROM `sms_schedule` WHERE `client`='$cid' ORDER BY `time` DESC");
		if($qri){
			foreach($qri as $row){
				$created=date("M d, H:i",$row['time']); $day=date("d-m-Y, h:i a",$row['schedule']); $mssg=nl2br(prepare($row['message']));
				$len=strlen(strip_tags(stripslashes($mssg))); $id=$row['id']; $json=json_decode($row['contacts'],1); 
				$cont=($row['type']=="similar") ? $json[0]:array_keys($json)[0]; $to=count($json); $conts=($to==1) ? $cont:"$to Contacts"; 
				$tot=($len<161) ? "1 Message ($len characters)":ceil($len/160)." Messages ($len characters)";
				$del = (in_array("delete sms schedule",$perms)) ? "<a href='javascript:void(0)' onclick=\"delschd('$id')\" 
				style='color:#C71585;font-weight:bold;float:right'><i class='fa fa-trash-o'></i> Delete</a>":"";
				
				$data.="<div class='col-12 col-sm-12 col-md-6 col-lg-4 mt-4' style='font-family:helvetica;font-size:15px' id='dv$id'>
					<p style='padding:5px;color:#4682b4;background:#f0f0f0;margin-bottom:5px'><i class='fa fa-calendar'></i> $day</p>
					<p>$mssg</p><p style='padding:5px;background:#FFE4E1;color:#191970;margin:10px 0px 0px 0px'>$tot</p>
					<p style='padding-top:10px;margin:0px;color:#2f4f4f'><b>To: $conts</b> ($created) $del</p>
				</div>";
			}
		}
		
		$data=($data) ? $data:"<div class='col-12' style='line-height:40px'>No Scheduled Messages Found</div>";
		
		echo "<div class='cardv' style='max-width:1200px;padding:15px;min-height:250px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px'>$backbtn SMS Schedule</h3><hr style='margin-bottom:0px'>
			<div class='row'> $data </div><br>
		</div>";
	}
	
	# message templates
	if(isset($_GET['templates'])){
		$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `client`='$cid' AND NOT `name`='inst200' ORDER BY `name` ASC"); 
		$perms = getroles(explode(",",staffInfo($sid)['roles']));
		$data="";
		
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['name'])); $mssg=nl2br(prepare(ucfirst($row['message']))); 
				$tid=$row['id']; $slen=countext($mssg);
				$del = (in_array("delete sms template",$perms)) ? "<i class='fa fa-trash-o' style='float:right;color:#ff4500;font-size:23px;cursor:pointer;margin-left:20px' 
				onclick=\"deltemp('$tid')\" title='Delete $name'></i>":"";
				
				$data.="<div class='col-12 col-lg-6 mb-3'>
					<table cellpadding='5' id='tb$tid' style='background:linear-gradient(to bottom,#fff,#fff,#f0f0f8);border:1px solid #f0f0f8;width:100%' cellspacing='0'>
					<tr><td style='width:40px'><i class='fa fa-bullhorn' style='font-size:23px;color:#2f4f4f'></i></td><td><h4 style='color:#008fff'>$name</h4></td></tr>
					<tr><td colspan='2' style='color:#2f4f4f'>$mssg</td></tr> <tr><td colspan='2'><i>($slen)</i> $del
					<i class='fa fa-pencil' style='float:right;color:#008fff;font-size:23px;cursor:pointer;' onclick=\"popupload('bulksms.php?createtemp=$tid')\" title='Edit $name'></i>
					</td></tr></table>
				</div>";
			}
		}
		
		$data=($data=="") ? "<table cellpadding='10'><tr><td style='color:grey'>No Templates found</td></tr></table>":$data;
		
		echo "<div class='cardv' style='max-width:1200px;padding:15px;min-height:300px'>
			<h3 style='color:#191970;font-size:22px'>$backbtn Message Templates</h4><hr> 
			<div class='row'> $data </div>
		</div>";
		
		savelog($sid,"View Message Templates");
	}
	
	#add/edit template
	if(isset($_GET['createtemp'])){
		$val=trim($_GET['createtemp']); 
		$name=$mssg=""; $txt="Create";
		
		if($val>0){
			$res = $db->query(1,"SELECT *FROM `sms_templates` WHERE `id`='$val'"); $txt="Update";
			$row = $res[0]; $name=prepare(ucwords($row['name'])); $mssg=prepare(ucfirst($row['message'])); 
		}
		
		echo "<h3 style='color:#191970;font-size:23px;text-align:center'> $txt Message Template</h3>
		<div style='padding:10px;max-width:350px;margin:0 auto'><br>
			<form method='post' id='tfom' onsubmit=\"savetemplate(event)\"> 
				<input type='hidden' name='savetemp' value='$val'>
				<p>Template Name<br><input type='text' name='tname' value=\"$name\" style='width:100%' autofocus required></p>
				<p>Template Message (Use keyword <b>CLIENT</b> to pick first Name of Client)<br>
				<textarea class='mssg' onkeyup=\"countext('mtot',this.value)\" name='tmssg' style='height:150px' maxlength='630' required>$mssg</textarea></p>
				<p style='text-align:right'><span id='mtot' style='float:left'></span> <button class='btnn'>$txt</button></p><br>
			</form>
		</div><br>";
	}
	
	# search client
	if(isset($_POST['searchc'])){
		$str = clean($_POST['searchc']); 
		$typ = clean($_POST['type']); 
		$lis=""; $no=0;
		
		$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE  (`name` LIKE '%$str%' OR `contact` LIKE '%$str%' OR `idno`='$str')");
		if($sql){
			foreach($sql as $row){
				$cont=$row[$typ]; $nme=prepare(ucwords($row['name'])); $name=prepare(ucwords($row['name'])." (0".$row['contact'].")"); $no++;
				$lis.="<li style='padding:5px 0px;list-style:none'><input type='checkbox' name='sendto[]' value='$cont' checked> &nbsp; $name</checkbox></li>";
			}
			
			echo "data~<br><b>Results found ($no)</b> <button class='btnn' style='float:right;padding:5px'>Send</button>~$lis";
		}
		else{
			echo "No results found for your search $str";
		}
		exit();
	}
	
	# topup sms
	if(isset($_GET['topup'])){
		echo "<div style='margin:0 auto;max-width:300px;padding:10px;font-family:cambria'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Topup SMS Wallet</h3><br>
			<form method='post' id='pform' onsubmit='requestpay(event)'>
				<p>Enter MPESA Phone Number<br><input type='number' style='width:100%' name='rphone' required></p>
				<p>Amount to topup<br><input type='number' style='width:100%' name='mamnt' required></p><br>
				<p style='text-align:right;'><button class='btnn'>Pay Now</button></p><br>
			</form>
		</div>";
	}

	ob_end_flush();
?>
	
	<script>
		
		function smsclients(e){
			e.preventDefault();
			if(!checkboxes("sendto[]")){ alert("No recipients selected!"); }
			else{
				if(confirm("Send SMS to selected Clients?")){
					var data = $("#sfom").serialize();
					$.ajax({
						method:"post",url:path()+"dbsave/sendsms.php",data:data,
						beforeSend:function(){ progress("Sending...please wait"); },
						complete:function(){progress();}
					}).fail(function(){
						toast("Failed to send SMS! Check your internet connection");
					}).done(function(res){
						if(res.trim().split(" ")[0]=="Sent" || res.trim().split("!")[0]=="Success"){ _("sfom").reset(); }
						alert(res);
					});
				}
			}
		}
		
		function smstaff(e){
			e.preventDefault();
			if(!checkboxes("staff[]")){ alert("Please check atleast one Staff to send SMS to"); }
			else{
				if(confirm("Send SMS to selected Employees?")){
					var data = $("#sform").serialize();
					$.ajax({
						method:"post",url:path()+"dbsave/sendsms.php",data:data,
						beforeSend:function(){ progress("Sending...please wait"); },
						complete:function(){progress();}
					}).fail(function(){
						toast("Failed to send SMS! Check your internet connection");
					}).done(function(res){
						if(res.trim().split(" ")[0]=="Sent" || res.trim().split("!")[0]=="Success"){ closepop(); }
						alert(res);
					});
				}
			}
		}
	
		function savetemplate(e){
			e.preventDefault();
			if(confirm("Save message Template?")){
				var data=$("#tfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/bulksms.php",data:data,
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					alert("Error while processing your request");
				}).done(function(res){
					if(res.trim()=="success"){ loadpage("bulksms.php?templates"); closepop(); }
					else{ alert(res.trim()); }
				});
			}
		}
		
		function uploadxls(e){
			e.preventDefault();
			var csv=_("xls").files[0];
			if(csv!=null){
				if(confirm("Upload selected document?")){
					var data=new FormData(_("xlfom"));
					data.append("xls",csv);
					var x=new XMLHttpRequest(); progress("Loading Document...wait");
					x.onreadystatechange=function(){
						if(x.status==200 && x.readyState==4){
							var res=x.responseText;
							if(res.trim().split(" ")[0]=="Sent" || res.trim().split("!")[0]=="Success"){ closepop(); }
							progress(); alert(res.trim());
						}
					}
					x.open("post",path()+"dbsave/bulksms.php",true);
					x.send(data);
				}
			}
		}
		
		function delschd(id){
			if(confirm("Sure to delete Schedule?")){
				$.ajax({
					method:"post",url:path()+"dbsave/bulksms.php",data:{delschd:id},
					beforeSend:function(){ progress("Removing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Deleted successful"); $("#dv"+id).remove();
					}
					else{ alert(res); }
				});
			}
		}
		
		function deltemp(id){
			if(confirm("Sure to delete template?")){
				$.ajax({
					method:"post",url:path()+"dbsave/bulksms.php",data:{deltmp:id},
					beforeSend:function(){ progress("Deleting...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					alert("Error while processing your request");
				}).done(function(res){
					if(res.trim()=="success"){
						$("#tb"+id).hide();
					}
					else{ alert(res); }
				});
			}
		}
		
		function requestpay(e){
			e.preventDefault();
			var data = $('#pform').serialize();
			$.ajax({
				method:'post',url:path()+'dbsave/bulksms.php',data:data,
				beforeSend:function(){progress('Requesting...please wait');},
				complete:function(){progress();}
			}).fail(function(){
				toast('Failed: Check internet Connection');
			}).done(function(res){
				alert(res); if(res.trim()=="success"){ closepop(); }
			});
		}
		
		function sendsms(e){
			e.preventDefault();
			if(!checkboxes("sendto[]")){ alert("No recipients selected!"); }
			else{
				if(confirm("Send SMS to selected recipients?")){
					var data=$("#smfom").serialize();
					$.ajax({
						method:"post",url:path()+"dbsave/bulksms.php",data:data,
						beforeSend:function(){ progress("Sending...please wait"); },
						complete:function(){progress(); }
					}).fail(function(){
						alert("Error while processing your request");
					}).done(function(res){
						alert(res);
					});
				}
			}
		}
		
		function searchc(str){
			if(str.length>1){
				$.ajax({
					method:"post",url:path()+"dbsave/bulksms.php",data:{searchc:str,type:"contact"},
					beforeSend:function(){progress("Searching...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					alert("Failed to add contact! Check your internet connection");
				}).done(function(res){
					if(res.trim().split("~")[0]=="data"){
						$(".ttl").html(res.trim().split("~")[1]); $(".searchres").html(res.trim().split("~")[2]);
					}
					else{ alert(res.trim()); }
				});
			}
			else{ $(".searchres").html(""); $(".ttl").html("No results found"); }
		}
		
		function gonext(e){
			e.preventDefault();
			var data = $("#sform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/bulksms.php",data:data,
				beforeSend:function(){progress("Initializing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				alert("Failed to add contact! Check your internet connection");
			}).done(function(res){
				if(res.trim()=="success"){
					if(_("sto").value=="clients"){ loadpage("bulksms.php?getclients"); closepop(); }
					else{ popupload("bulksms.php?smrecips&sto="+_("sto").value); }
				}
				else{ toast("Failed to initialize the process! Try again"); }
			});
		}
		
	</script>