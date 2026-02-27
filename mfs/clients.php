<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;

	# interactions
	if(isset($_GET['intcns'])){
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$stid = (isset($_GET['uid'])) ? trim($_GET['uid']):0;
		$fro = (isset($_GET["fdy"])) ? strtotime(trim($_GET["fdy"])):strtotime(date("Y-M"));
		$dtu = (isset($_GET["dto"])) ? strtotime(trim($_GET["dto"])):strtotime("Today");
		$dtu+=86399; $fdy=($dtu<$fro) ? $dtu:$fro; $dto = ($dtu<$fro) ? $fro:$dtu; 
		
		$me = staffInfo($sid); $access=$me['access_level']; 
		$perms = getroles(explode(",",$me['roles']));
		$cnf = json_decode($me["config"],1);
		$itbl = "interactions$cid"; $stbl="org$cid"."_staff"; $ctbl="org$cid"."_clients"; 
		
		if(!$db->istable(2,"interactions$cid")){
			$db->createTbl(2,"interactions$cid",["client"=>"INT","comment"=>"TEXT","source"=>"INT","time"=>"INT"]);
		}
		
		$perpage = 30; $totals=0;
		$lim = getLimit($page,$perpage);
		$data=[]; $staff=["System"]; $trs=$ths="";
		
		$exclude = array("id","time","client");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$itbl'");
		$def = array("id"=>"number","client"=>"number","comment"=>"textarea","source"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($ftc[$col]); }
			}
		}
		$fields = array_keys($ftc);
		
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); $user[$row['id']]=$row['branch']; }
		
		if($me['access_level']=="portfolio" && in_array("approve loan application",$perms)){ $me['access_level']="branch"; }
		$show = ($me['access_level']=="hq") ? 1:"cl.branch='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? setRegion($cnf["region"],"cl.branch"):$show;
		$load = ($me['access_level']=="portfolio") ? "cl.loan_officer='$sid'":$show;
		$load.= ($stid) ? " AND `source`='$stid'":"";
		$cond = ($str) ? "$load AND (cl.name LIKE '%$str%' OR cl.contact LIKE '%$str%' OR cl.idno LIKE '%$str%')":"it.time BETWEEN $fdy AND $dto AND $load";
		$colors = array("#008fff","#4682b4","#BA55D3","#9370DB","#3CB371","#C71585","#DA70D6","#663399","#008080","#FF6347","#8FBC8F","#2F4F4F","#008fff");
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="cl.idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$no=($perpage*$page)-$perpage; $fro=$no+1; $trs=$ths=$offs=$brns=$ulis="";
		$res = $db->query(2,"SELECT cl.name AS cname,cl.contact AS cont,cl.loan_officer,cl.status,it.* FROM `$itbl` AS it INNER JOIN `$ctbl` AS cl 
		ON it.client=cl.id WHERE $cond GROUP BY it.client ORDER BY cl.name ASC $lim"); 
		if($res){
			foreach($res as $row){
				$color=$colors[rand(0,12)]; $no++; $rid=$row['id']; $tds=""; 
				$states = array("<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>","<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",
				"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Blacklisted</span>");
				
				foreach($ftc as $col=>$dtp){
					if(!in_array($col,$exclude)){
						$val = prepare(ucfirst($row[$col])); $css="";
						if($col=="comment"){
							$isjs = (isJson($val)) ? 1:0;
							$val = ($isjs) ? json_decode($val,1)["comment"]:$val; $css=($isjs) ? "color:#DC143C":"";
						}
						$val = (strlen($val)>50) ? substr($val,0,50)." ...":$val;
						$val = ($col=="loan_officer" or $col=="source") ? $staff[$row[$col]]:$val;
						$val = ($dtp=="url") ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
						$tds.= "<td style='$css'>$val</td>"; $ths.=($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
					}
				}
				
				$name=prepare(ucwords($row['cname'])); $clid=$row['client']; $cont=$row['cont']; $ofname=$staff[$row['loan_officer']]; $cst=$states[$row['status']];
				$trs.="<tr onclick=\"loadpage('clients.php?vintrcn=$clid')\"><td>$no</td><td>$name<br>0$cont</td><td>$ofname</td>$tds<td>$cst</td></tr>";
			}
		}
		else{
			foreach($fields as $key=>$col){
				if(!in_array($col,$exclude)){
					$ths.="<td>".ucfirst(str_replace("_"," ",$col))."</td>";
				}
			}
		}
		
		$sql = $db->query(2,"SELECT DISTINCT it.source FROM `$itbl` AS it INNER JOIN `$ctbl` AS cl ON it.client=cl.id WHERE $cond");
		$totals = ($sql) ? count($sql):0; $frod=$fdy; $mdy=$dto; $tdy=date("Y-m-d"); $fdy=date("Y-m-d",$fdy); $dto=date("Y-m-d",$dto);
		
		if($me["access_level"]!="portfolio"){
			$opts="<option value='0'>-- Source --</option>"; $ids=[]; $cond2="";
			if($me["access_level"]!="hq"){
				$cnd = ($me["access_level"]=="branch") ? "`branch`='".$me["branch"]."'":setRegion($cnf["region"]);
				$sql = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE $cnd"); 
				foreach($sql as $row){ $ids[]="`source`='".$row["id"]."'"; }
				$cond2 = "AND (".implode(" OR",$ids).")";
			}
			
			$sql = $db->query(2,"SELECT DISTINCT `source` FROM `$itbl` WHERE `time` BETWEEN $frod AND $mdy $cond2");
			if($sql){
				foreach($sql as $row){
					$uid = $row['source']; $cnd=($uid==$stid) ? "selected":"";
					$opts.= "<option value='$uid' $cnd>".$staff[$uid]."</option>";
				}
				$ulis = "<select style='width:150px;' onchange=\"loadpage('clients.php?intcns&fdy=$fdy&dto=$dto&uid='+this.value)\">$opts</select>";
			}
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div style='padding:10px 5px;'>
				<h3 style='font-size:22px;color:#191970;margin-bottom:10px'>$backbtn Client Interactions</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped stbl' style='width:100%;min-width:500px;font-size:15px;' cellpadding='5'>
						<caption style='caption-side:top'>
							<input type='search' onkeyup=\"fsearch(event,'clients.php?intcns&str='+cleanstr(this.value))\" onsearch=\"loadpage('clients.php?intcns&str='+cleanstr(this.value))\" 
							value='".prepare($str)."'style='width:170px;padding:4px 6px;font-size:15px;float:right' placeholder='&#xf002; Search client'>
							<input type='date' style='width:150px;font-size:15px;padding:4px 6px' id='dfro' onchange='getrange()' value='$fdy' max='$tdy'> ~
							<input type='date' style='width:150px;font-size:15px;padding:4px 6px' id='dto' onchange='getrange()' value='$dto' max='$tdy'> $ulis
						</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px;cursor:default' valign='top'><td colspan='2'>Client</td>
						<td>Loan Officer</td> $ths<td>Client Status</td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"clients.php?intcns&str=".str_replace(" ","+",$str))."
		</div>
		<script>
			function getrange(){
				var fro = $('#dfro').val(), to=$('#dto').val();
				loadpage('clients.php?intcns&fdy='+fro+'&dto='+to);
			}
		</script>";
		savelog($sid,"Viewed client interactions");
	}
	
	# show client interaction
	if(isset($_GET['vintrcn'])){
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$uid = trim($_GET['vintrcn']);
		$ctbl = "org$cid"."_clients";
		$itbl = "interactions$cid";
		
		if(!$db->istable(2,$itbl)){
			$db->createTbl(2,$itbl,["client"=>"INT","comment"=>"TEXT","source"=>"INT","time"=>"INT"]);
		}
		
		$exclude = array("id","time","client","source","comment");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$itbl'");
		$def = array("id"=>"number","client"=>"number","comment"=>"textarea","source"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		$perpage = 30; $lim=getLimit($page,$perpage); 
		$col = (isset($_GET['fdt'])) ? "idno":"id"; $users=["System"];
		$cinfo = $db->query(2,"SELECT *FROM `$ctbl` WHERE `$col`='$uid'")[0]; 
		$clid = $cinfo['id']; $idno=$cinfo["idno"];
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($sql as $row){ $users[$row['id']]=prepare(ucwords($row['name'])); }
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$itbl'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){ 
					if($dtp=="image"){
						$res = $db->query(2,"SELECT $col,id FROM `$itbl` WHERE $cond ORDER BY `time` DESC $lim");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = (strlen($df)>1) ? "data:image/jpg;base64,".getphoto($df):"$path/assets/img/user.png";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;cursor:pointer;' onclick=\"popupload('media.php?doc=img:$df&tbl=$itbl:$col&fd=id:$id')\">";
							}
						}
					}
					if($dtp=="pdf" or $dtp=="docx"){
						$res = $db->query(2,"SELECT $col,id FROM `$itbl` WHERE $cond ORDER BY time DESC $lim");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;cursor:pointer' onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$itbl:$col&fd=id:$id')\">";
							}
						}
					}
				}
			}
		}
		
		$no=$start=($perpage*$page)-$perpage; $trs=$ths=$offs="";
		$res = $db->query(2,"SELECT *FROM `$itbl` WHERE `client`='$clid' ORDER BY time DESC $lim");
		if($res){
			foreach($res as $row){
				$rid=$row['id']; $tds=""; $no++;
				foreach($ftc as $col=>$dtp){
					if(!in_array($col,$exclude)){
						$val=prepare(ucfirst(nl2br($row[$col])));
						$val=(isset($docs[$col.$row['id']])) ? $docs[$col.$row['id']]:$val; 
						$val = ($dtp=="url") ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
						$tds.="<td style='$css'>$val</td>"; $ths.=($no==$start+1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
					}
				}
				
				$tym=date("d-m-Y, H:i",$row['time']); $com=nl2br(prepare($row['comment'])); $usa=$users[$row['source']];
				$isjs = (isJson($com)) ? 1:0; $dcom=($isjs) ? json_decode($com,1)["comment"]:$com; $css=($isjs) ? "color:#DC143C":"";
				$trs.="<tr valign='top'><td>$usa<br><span style='color:#008080;font-size:14px'>$tym</span></td>$tds<td style='$css'>$dcom</td></tr>";
			}
		}
		else{
			foreach($ftc as $col=>$dtp){
				if(!in_array($col,$exclude)){ $ths.="<td>".ucfirst(str_replace("_"," ",$col))."</td>"; }
			}
		}
	
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `$itbl` WHERE `client`='$clid' ORDER BY time DESC");
		$qri = $db->query(2,"SELECT COUNT(*) AS tot FROM `org{$cid}_loantemplates` WHERE `client_idno`='$idno' AND `status`<8 AND `pref`='8'");
		$chk = $db->query(2,"SELECT `comments`,`time`,`status` FROM `org{$cid}_loantemplates` WHERE `client_idno`='$idno' AND `status`>8 ORDER BY `time` DESC LIMIT 1");
		$totals = ($sql) ? intval($sql[0]['total']):0; $pnd=($qri) ? intval($qri[0]["tot"]):0; $sta=($chk) ? $chk[0]["status"]:0; $dct="";
		if($sta==9){
			$ctm = $chk[0]["time"]; $com=@prepare(array_pop(json_decode($chk[0]["comments"],1)));
			$dct = "<p style='color:#ff4500;font-size:15px'>Client Had a declined Application on ".date("M d,Y",$ctm)." : <i>$com</i></p>";
		}
		
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		$act = ($pnd && in_array("delete client",$perms)) ? "<button class='bts' style='float:right;padding:4px 8px;margin-right:7px' onclick=\"releasepend('$idno')\">
		<i class='bi-check2-circle'></i> Release Pended Loan</button>":"";
		
		echo "<div class='cardv' style='max-width:1100px;min-height:400px;overflow:auto'>
			<div style='padding:10px 5px;'>
				<h3 style='font-size:22px;color:#191970;margin-bottom:10px'>$backbtn <span onclick=\"loadpage('clients.php?vintrcn=$clid')\">".
				prepare(ucwords($cinfo['name']))."</span> Interactions <button class='bts' style='float:right;padding:4px 8px' onclick=\"popupload('clients.php?cintrn=$clid')\">
				<i class='fa fa-plus'></i> Create</button> $act</h3>
				<div style='width:100%;overflow:auto;'>$dct
    				<table class='table-striped' style='width:100%;min-width:500px;font-size:15px;' cellpadding='5'>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px;cursor:default' valign='top'><td>Source</td>$ths<td>Comment</td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"clients.php?vintrcn=$clid")."
		</div>";
		savelog($sid,"Viewed ".$cinfo['name']." interactions");
	}
	
	# show lead interactions
	if(isset($_GET['vcoms'])){
		$uid = trim($_GET['vcoms']);
		$cols = $db->tableFields(2,"client_leads$cid");
		if(!in_array("comments",$cols)){
			$db->execute(2,"ALTER TABLE `client_leads$cid` ADD `comments` LONGTEXT NOT NULL AFTER `others`");
		}
		
		$cinfo = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `id`='$uid'")[0];
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
		foreach($sql as $row){ $users[$row['id']]=prepare(ucwords($row['name'])); }
		$coms = ($cinfo["comments"]) ? json_decode($cinfo["comments"],1):[]; $trs="";
		
		foreach($coms as $src=>$com){
			$tym=date("d-m-Y, H:i",explode(":",$src)[1]); $com=str_replace("~nl~","<br>",prepare($com)); $usa=$users[explode(":",$src)[0]];
			$trs.= "<tr valign='top'><td>$usa<br><span style='color:#008080;font-size:14px'>$tym</span></td><td>$com</td></tr>";
		}
		
		echo "<div class='cardv' style='max-width:1100px;min-height:400px;overflow:auto'>
			<div style='padding:10px 5px;'>
				<h3 style='font-size:22px;color:#191970;margin-bottom:10px'>$backbtn <span onclick=\"loadpage('clients.php?vcoms=$uid')\">".
				prepare(ucwords($cinfo['name']))."</span> Interactions <button class='bts' style='float:right;padding:4px 8px' onclick=\"popupload('clients.php?leadint=$uid')\">
				<i class='fa fa-plus'></i> Create</button></h3>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped' style='width:100%;min-width:500px;font-size:15px;' cellpadding='5'>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px;cursor:default' valign='top'><td>Source</td><td>Comment</td></tr> $trs
    				</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed ".$cinfo['name']." lead interactions");
	}
	
	# create lead interaction
	if(isset($_GET["leadint"])){
		$uid = trim($_GET["leadint"]);
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>Create Lead Interaction</h3><br>
			<form method='post' id='lform' onsubmit=\"saveintrcn(event,'$uid')\">
				<input type='hidden' name='leadid' value='$uid'>
				<p>Comment/Message<br><textarea name='intrcom' class='mssg' required></textarea></p><br>
				<p style='text-align:right'><button class='btnn'>Create</button></p>
			</form><br>
		</div>";
	}
	
	# post client interaction
	if(isset($_GET['cintrn'])){
		$clid = trim($_GET['cintrn']);
		$itbl = "interactions$cid";
		
		$exclude = array("id","time","client","source");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$itbl'");
		$def = array("id"=>"number","client"=>"number","comment"=>"textarea","source"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; 
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		
		$defv = array("time"=>time(),"source"=>$sid,"client"=>$clid);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(array_key_exists($fld,$defv)) ? $defv[$fld]:""; 
		}
		
		$lis=$infs=""; $ftps=[];
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($dtype=="select"){
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
					$inp = (array_key_exists($dtype,$accept))? "file":$dtype; $add=($inp=="file") ? $accept[$dtype]:""; 
					$val = prepare(ucfirst($dvals[$field])); 
					if($inp=="file"){ $infs.="$field:"; $ftps[$field]=$dtype; }
					$lis.="<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" accept='$add' id='$field' name='$field' required></p>";
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>Post Client Interaction</h3><br>
			<form method='post' id='iform' onsubmit=\"postlead(event,'$clid','0')\">
				<input type='hidden' name='intrnkeys' value='".json_encode(array_keys($fields),1)."'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Post</button></p>
			</form><br>
		</div>";
	}
	
	# client groups
	if(isset($_GET['groups'])){
		$grp = ($_GET['groups']) ? trim($_GET['groups']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$stid = (isset($_GET['stid'])) ? trim($_GET['stid']):0;
		$ctbl = "org".$cid."_clients"; $stbl="org".$cid."_schedule"; $ltbl="org".$cid."_loans";
		
		$me = staffInfo($sid); $access=$me['access_level']; $data=[];
		$perms = getroles(explode(",",$me['roles'])); $perpage=30;
		$lim = getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		if(!$db->istable(2,"client_groups$cid")){
			$fields = json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='client_groups'")[0]['fields'],1);
			$db->createTbl(2,"client_groups$cid",$fields);
		}
		
		$grups = array("Default","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
		$res = $db->query(2,"SELECT *FROM `client_groups$cid` GROUP BY gid ORDER BY `name` ASC");
		if($res){
			foreach($res as $row){
				$grup = prepare(ucfirst($row['name'])); $gid=$row['gid'];
				$grups[$gid]=$grup;
			}
		}
		
		$grp = (array_key_exists($grp,$grups)) ? $grp:0;
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[0=>"Head Office"];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND ln.branch='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"ln.branch"):$show;
		$cond = ($me['access_level']=="portfolio") ? " AND ln.loan_officer='$sid'":$show;
		$cond.= ($stid) ? " AND ln.loan_officer='$stid'":"";
		
		if($grp<100){
			$cond.= ($str) ? " AND (ln.client LIKE '%$str%' OR ln.client_idno LIKE '%$str%' OR ln.phone LIKE '%$str%')":"";
			if($me["position"]=="collection agent"){
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
				if($sql){
					foreach($sql as $row){ $ids[]="ln.client_idno='".$row['client_idno']."'"; }
					$cond.= " AND (".implode(" OR ",$ids).")"; 
				}
			}
			
			if($grp==0){
				# default clients
				$title = $grups[$grp]; $tdh="<td>Balance</td><td>Days</td>"; $thv="stbl"; $tdy=strtotime(date("Y-M-d"));
				$qry = $db->query(2,"SELECT ln.client FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd ON ln.id=sd.loan 
				WHERE ln.balance>0 $cond AND sd.balance>0 AND ln.expiry<$tdy GROUP BY sd.loan ORDER BY ln.client ASC");
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,ln.expiry FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd 
				ON ln.id=sd.loan WHERE ln.balance>0 $cond AND sd.balance>0 AND ln.expiry<$tdy GROUP BY sd.loan ORDER BY ln.expiry DESC,ln.client ASC $lim");
				
				$totals = ($qry) ? count($qry):0; $trs=""; $no=($perpage*$page)-$perpage;
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $days=floor((time()-$row['expiry'])/86400);
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $idno=$row['client_idno']; $no++;
						$trs.="<tr onclick=\"loadpage('clients.php?showclient=$idno')\">
						<td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td><td>$bal</td><td>$days</td></tr>";
					}
				}
			}
			else{
				# weekdays groups
				$title = $grups[$grp]; $tdh="<td>Balance</td>"; $thv="stbl";
				$qry = $db->query(2,"SELECT ln.client,from_unixtime(sd.day,'%W') AS dow FROM `$ltbl` AS ln 
				INNER JOIN `$stbl` AS sd ON ln.id=sd.loan WHERE ln.balance>0 $cond  GROUP BY sd.loan,dow HAVING dow LIKE '".$grups[$grp]."'");
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,from_unixtime(sd.day,'%W') AS dow FROM `$ltbl` AS ln 
				INNER JOIN `$stbl` AS sd ON ln.id=sd.loan WHERE ln.balance>0 $cond  GROUP BY sd.loan,dow HAVING dow LIKE '".$grups[$grp]."' ORDER BY ln.client ASC $lim");
				
				$totals = ($qry) ? count($qry):0; $trs=""; $no=($perpage*$page)-$perpage;
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $idno=$row['client_idno']; 
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $no++;
						$trs.="<tr onclick=\"loadpage('clients.php?showclient=$idno')\">
						<td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td><td>$bal</td></tr>";
					}
				}
			}
		}
		else{
			# client groups 
			$title = $grups[$grp]; $tdh="<td>Status</td><td>Action</td>"; $thv=""; $ids=[];
			$cond.= ($str) ? " AND (ln.name LIKE '%$str%' OR ln.idno LIKE '%$str%' OR ln.contact LIKE '%$str%')":"";
			if($me["position"]=="collection agent"){
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
				if($sql){
					foreach($sql as $row){ $ids[]="ln.idno='".$row['client_idno']."'"; }
					$cond.= " AND (".implode(" OR ",$ids).")"; 
				}
			}
		
			$qry = $db->query(2,"SELECT COUNT(*) AS total FROM `client_groups$cid` AS cg INNER JOIN `$ctbl` AS ln ON ln.id=cg.client WHERE cg.gid='$grp' $cond");
			$res = $db->query(2,"SELECT ln.name,ln.contact,ln.idno,ln.loan_officer,ln.status,cg.unqid FROM `client_groups$cid` AS cg INNER JOIN `$ctbl` AS ln 
			ON ln.id=cg.client WHERE cg.gid='$grp' $cond ORDER BY ln.name ASC $lim");
			
			$totals = ($qry) ? $qry[0]['total']:0; $trs=""; $no=($perpage*$page)-$perpage;
			if($res){
				foreach($res as $row){
					$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $idno=$row['idno']; 
					$ofname=$staff[$row['loan_officer']]; $st=$row['status']; $rid=$row['unqid']; $no++;
					$states=array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",1=>"<span style='color:green;'>
					<i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Blacklisted</span>");
					
					$trs.="<tr id='$rid'><td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td>
					<td>".$states[$st]."</td><td><a href='javascript:void(0)' style='color:#ff4500' onclick=\"delcgroup('user','$rid')\">Remove</a></td></tr>";
				}
			}
		}
		
		$grps=$brns=$offs="";
		foreach($grups as $key=>$name){
			$cnd=($key==$grp) ? "selected":""; $add=($key<100) ? "Clients":"";
			$grps.="<option value='$key' $cnd>$name $add</option>";
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
			$brns = "<select style='width:160px' onchange=\"loadpage('clients.php?groups=$grp&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ctbl` WHERE $brn");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('clients.php?groups=$grp&bran=$bran&stid='+this.value.trim())\">$opts</select>";
			}
		}
		
		$prnt = ($totals) ? genrepDiv("client_groups.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&gr=$grp",'right'):"";
		$del = ($grp>10 && in_array("create client group",$perms)) ? "<button class='bts' style='padding:4px;float:right;font-size:14px;margin-left:5px' 
		onclick=\"delcgroup('group','$grp')\"><i class='fa fa-group'></i> Delete</button>":"";
		$edit = ($grp>10 && in_array("create client group",$perms)) ? "<button class='bts' style='padding:4px;float:right;font-size:14px;margin-left:5px' 
		onclick=\"editgroup('$grp')\"><i class='fa fa-pencil'></i> Edit</button>":"";
		$add = ($grp>10 && in_array("create client group",$perms)) ? "<button class='bts' style='padding:4px;font-size:14px;' 
		onclick=\"loadpage('clients.php?addtogrp=$grp')\"><i class='fa fa-user-plus'></i> Clients</button>":"";
		
		echo "<div class='cardv' style='max-width:1000px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Client Groups $del $edit
				<button class='bts' style='padding:4px;float:right;font-size:14px' onclick=\"loadpage('clients.php?addtogrp')\"><i class='fa fa-plus'></i> Group</button></h3>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered $thv' style='width:100%;font-size:15px;margin-top:15px;min-width:500px' cellpadding='5'>
						<caption style='caption-side:top'>
							<p><input type='search' onkeyup=\"fsearch(event,'clients.php?groups=$grp&str='+cleanstr(this.value))\"
							onsearch=\"loadpage('clients.php?groups=$grp&str='+cleanstr(this.value))\" value='".prepare($str)."'
							style='float:right;width:180px;padding:5px;font-size:15px' placeholder='&#xf002; Search'>
							<select style='width:160px' onchange=\"loadpage('clients.php?groups='+cleanstr(this.value))\">$grps</select> &nbsp; $add<p>
							<p style='margin:0px'> $brns $offs $prnt</p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px;' valign='top'>
						<td colspan='2'>Name</td><td>Contact</td><td>Id Number</td><td>Loan Officer</td>$tdh</tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"clients.php?groups=$grp&bran=$bran&stid=$stid&str=".str_replace(" ","+",$str))."
		</div>";
		
		savelog($sid,"Viewed client group $title");
		
	}
	
	#add clients to group
	if(isset($_GET['addtogrp'])){
		$grp = trim($_GET['addtogrp']);
		$me = staffInfo($sid); 
		$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_clients` WHERE `status`<2 $cond");
		$res = $db->query(2,"SELECT *FROM `client_groups$cid` WHERE `gid`='$grp'");
		$all = ($sql) ? $sql[0]['total']:0; $grp=($res) ? $grp:"";
		
		$gname = ($grp) ? "<input type='hidden' id='gid' value='$grp'><br>":
		"<p>Group Name<br><input type='text' id='gid' style='padding:5px;max-width:200px' required></p><hr>";
		$lis = ($all) ? "<li style='margin:5px 0px;color:#2f4f4f;list-style:none'>
		<input class='ccont' type='checkbox' name='clients[]' value='all' checked> &nbsp; All Clients ($all)</li>":"";
		$ttl = ($grp) ? "Add Clients to Group":"Create Client Group";
		
		echo "<div class='cardv' style='max-width:700px;padding:10px'>
			<h3 style='padding:10px 0px;color:#4682b4;font-size:23px'>$backbtn $ttl</h3> $gname
			<div class='row'>
				<div class='col-12 col-lg-6'> 
					<p><button class='bts' style='padding:4px 8px' onclick=\"popupload('clients.php?listconts=$grp')\">Select Contacts</button>
					<input type='search' onsearch='searchcl(this.value)' onkeyup='firesrc(event,this.value)' style='width:180px;float:right;padding:4px' 
					placeholder='&#xf002; Search Contact'></p>
				</div> 
				<div class='col-12 col-lg-6' style='text-align:right'>
					<button class='btnn' style='margin-left:10px;padding:5px' onclick=\"savegroup('$grp')\"><i class='fa fa-check'></i> Save</button>
				</div>
			</div><hr>
			<form method='post' id='gfom'> 
				<p><b>Selected Contacts <span class='tots'></span></b></p>
				<input type='hidden' name='savegrp' value='$grp'>
				<div style='min-height:150px;padding:5px 10px;' class='udiv'>$lis</div> 
			</form>
			<input type='hidden' id='grup' value='$grp'> <input type='hidden' id='totals' value='$all'>
		</div>";
		
	}
	
	# list clients to add to group
	if(isset($_GET['listconts'])){
		$grp = trim($_GET['listconts']);
		$me = staffInfo($sid); $lis="";
		$cnf = json_decode($me["config"],1);
		
		$cond = ($me['access_level']=="hq") ? "":"AND ct.branch='".$me['branch']."'";
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"ct.branch"):$cond;
		$cond = ($me['access_level']=="portfolio") ? "AND ct.loan_officer='$sid'":$cond;
		$ctbl = "org".$cid."_clients"; $cgtb = "client_groups$cid";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="ct.idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$qry= $db->query(2,"SELECT *FROM $ctbl ct WHERE ct.status<2 $cond AND NOT EXISTS (SELECT *FROM $cgtb cg WHERE cg.client=ct.id AND cg.gid='$grp') ORDER BY ct.name ASC LIMIT 20"); 
		if($qry){
			foreach($qry as $row){
				$rid=$row['id']; $name=prepare(ucwords($row['name']));
				$lis.="<li style='margin:5px 0px;color:#2f4f4f;list-style:none' id='$rid'>
				<input class='ccont' type='checkbox' name='clients[]' value='$rid'> &nbsp; $name</checkbox></li>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM $ctbl ct WHERE ct.status<2 $cond AND NOT EXISTS (SELECT *FROM $cgtb cg WHERE cg.client=ct.id AND cg.gid='$grp')");
		$all = ($sql) ? $sql[0]['total']:0; $rand=rand(123456,654321);
		
		$load=($all>20) ? "<p style='text-align:center' id='$rand'><span class='bts' onclick=\"getclients('$rand')\" style='cursor:pointer;'>
		Load More Clients</span></p>":"";
		
		echo ($lis) ? "<div style='padding:10px;max-width:400px;margin:0 auto;'>
			<h3 style='color:#191970;text-align:center;font-size:23px;padding-bottom:10px'>Select Clients</h3>
			<div style='max-height:390px;overflow:auto'>
				<div style='min-height:250px;padding:5px 10px;' class='cdiv'>$lis</div> <div class='pageload'> $load </div>
			</div><br> <input type='hidden' id='pageno' value='1'> 
			<p style='text-align:right'><a style='float:left' href='javascript:checkall()'>&#x2714; <span id='scont'>Select All</a> 
			<button class='bts' onclick=\"selectconts('0')\">Add Clients</button></p>
		</div>":
		"<div style='padding:10px;max-width:400px;margin:0 auto;'>
			<h3 style='color:#191970;text-align:center;font-size:23px;'>Select Clients</h3><br>
			<p style='padding:10px;background:#FFEFD5;color:#FF6347;border:1px solid pink;text-align:center'>No Clients Found</p><br>
		</div>";
		
	}
	
	# search clients to add to group
	if(isset($_GET['clientstr'])){
		$str = clean($_GET['clientstr']);
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1); $lis="";
		$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="`idno`='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$qry = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `status`<2 $cond AND (`name` LIKE '%$str%' OR `contact` LIKE '%$str%' OR `idno` LIKE '%$str%') ORDER BY `name` ASC"); 
		if($qry){
			foreach($qry as $row){
				$rid=$row['id']; $name=prepare(ucwords($row['name']));
				$lis.="<li style='margin:5px 0px;color:#2f4f4f;list-style:none' id='$rid'>
				<input class='ccont' type='checkbox' name='clients[]' value='$rid'> &nbsp; $name</checkbox></li>";
			}
		}
		
		echo ($lis!="") ? "<div style='padding:10px;max-width:400px;margin:0 auto;'>
			<h3 style='color:#191970;text-align:center;font-size:23px;padding-bottom:10px'>Search Results</h3>
			<div style='max-height:390px;overflow:auto'>
				<div style='min-height:150px;padding:5px 10px;' class='cdiv'>$lis</div>
			</div><br>
			<p style='text-align:right'><a style='float:left' href='javascript:checkall()'>&#x2714; <span id='scont'>Select All</a> 
			<button class='bts' onclick=\"selectconts('1')\">Add Clients</button></p>
		</div>":
		"<div style='padding:10px;max-width:400px;margin:0 auto;'>
			<h3 style='color:#191970;text-align:center;font-size:23px;'>Search Results</h3><br>
			<p style='padding:10px;background:#FFEFD5;color:#FF6347;border:1px solid pink;text-align:center'>
			No results found for <u>".prepare($str)."</u></p><br>
		</div>";
	}
	
	# fetch more clients to add to group
	if(isset($_POST['pageno'])){
		$page = trim($_POST['pageno'])+1;
		$grp = clean($_POST['grup']); $lis="";
		$lim=getLimit($page,20);
		
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$cond = ($me['access_level']=="hq") ? "":"AND ct.branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND ct.loan_officer='$sid'":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"ct.branch"):$cond;
		$ctbl = "org".$cid."_clients"; $cgtb = "client_groups$cid";
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM $ctbl ct WHERE ct.status<2 $cond AND NOT EXISTS (SELECT *FROM $cgtb cg WHERE cg.client=ct.id AND cg.gid='$grp')");
		$all = ceil($sql[0]['total']/20);
			
		$qry= $db->query(2,"SELECT *FROM $ctbl ct WHERE ct.status<2 $cond AND NOT EXISTS (SELECT *FROM $cgtb cg WHERE cg.client=ct.id AND cg.gid='$grp') ORDER BY ct.name ASC $lim"); 
		foreach($qry as $row){
			$rid=$row['id']; $name=prepare(ucwords($row['name']));
			$lis.="<li style='margin:5px 0px;color:#2f4f4f;list-style:none' id='$rid'>
			<input class='ccont' type='checkbox' name='clients[]' value='$rid'> &nbsp; $name</li>";
		}
			
		$rand=rand(123456,654321);
		$next=($page<$all) ? "<p style='text-align:center' id='$rand'><span class='bts' onclick=\"getclients('$rand')\" 
		style='cursor:pointer;'>Load More Clients</span></p>":"";
		echo "data~$page~$lis~$next";
		
		exit();
	}
	
	//transfer clients
	if(isset($_GET['transfer'])){
		$ofid=trim($_GET['transfer']);
		$opts=$opt2="<option value='0'>-- Select Loan Officer --</option>";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD' ORDER BY `name` ASC");
		foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
		
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		
		$sql=$db->query(2,"SELECT *,COUNT(*) AS total FROM `org".$cid."_loans` WHERE `balance`>0 $cond GROUP BY loan_officer");
		if($sql){
			foreach($sql as $row){
				$uid = $row['loan_officer']; $no=$row['total']; 
				$tot = ($no==1) ? "1 Client":"$no Clients"; $all[$uid]=$tot;
			}
			
			foreach($staff as $id=>$name){
				if(isset($all[$id])){
					$cond = ($ofid==$id) ? "selected":"";
					$opts.= "<option value='$id' $cond>$name (".$all[$id].")</option>";
				}
			}
		}
		
		if($ofid>0){
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'");
			$pos = ($res) ? $res[0]['value']:null;
			foreach($staff as $id=>$name){
				if($pos){ 
					$opt2.= (isset(staffPost($id)[$pos]) && $id!=$ofid) ? "<option value='$id'>$name</option>":"";
				}
				else{ $opt2.= ($id!=$ofid) ? "<option value='$id'>$name</option>":""; }
			}
		}
		
		echo "<div style='max-width:300px;margin:0 auto;padding:10px'><br>
			<h3 style='font-size:22px;text-align:center'>Transfer Active Clients</h3><br>
			<form method='post' id='tform' onsubmit='transferclients(event)'>
				<p>Officers with Active Clients<br>
				<select style='width:100%' id='tfro' onchange=\"popupload('clients.php?transfer='+this.value)\">$opts</select></p>
				<p>Transfer To<br><select id='tto' style='width:100%'>$opt2</select></p>
				<p style='padding-top:10px'><input type='checkbox' name='dormtoo' checked> &nbsp; Transfer plus Dormant Clients</checkbox></p><br>
				<p style='text-align:right'><button class='btnn'>Next</button></p><br>
			</form>
		</div>";
		
		savelog($sid,"Accessed Transfer Client Form");
	}
	
	//select clients to transfer from
	if(isset($_GET['transfrom'])){
		$from = trim($_GET['transfrom']);
		$to = trim($_GET['transto']);
		$dorm = trim($_GET['dorm']); $tot=0;
		
		$sql = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan_officer`='$from' AND `balance`>0");
		foreach($sql as $row){
			$clid=$row['client_idno']; $lid=$row['loan']; $name=prepare(ucwords($row['client'])); $tot++;
			$data[]="<input type='checkbox' name='tclients[]' value='$clid:$lid' checked> &nbsp; $name</checkbox>";
		}
		
		$all=array_chunk($data,3); $trs="";
		foreach($all as $one){
			$td1=$one[0]; $td2=(count($one)>1) ? $one[1]:""; $td3=(count($one)>2) ? $one[2]:"";
			$trs.="<tr valign='top'><td>$td1</td><td>$td2</td><td>$td3</td></tr>";
		}
		
		echo "<div style='padding:10px'><h3 style='font-size:22px;text-align:center'>Select Clients to Transfer</h3><br>
		<form method='post' id='tfom' onsubmit='savetransfered(event)'>
			<input type='hidden' name='tfro' value='$from'> <input type='hidden' name='tto' value='$to'> 
			<input type='hidden' name='tactive' value='$tot'> <input type='hidden' name='dormtoo' value='$dorm'> 
			<table cellpadding='5' style='width:100%'>$trs 
				<tr><td colspan='3' style='text-align:right'><br><button class='btnn'>Transfer</button></td></tr>
			</table>
		</form></div>";
	}
	
	# add/edit client
	if(isset($_GET['add'])){
		$stid = trim($_GET['add']);
		$cont = (isset($_GET['cdn'])) ? intval(ltrim(trim($_GET['cdn']),"254")):0;
		
		$ctbl = "org$cid"."_clients";
		$stbl = "org$cid"."_staff";
		$me = staffInfo($sid); $idno=0;
		$cnf = json_decode($me["config"],1);
		$perms = getroles(explode(",",$me['roles']));
		
		if(!$db->istable(2,"client_leads$cid")){
			$db->createTbl(2,"client_leads$cid",["branch"=>"INT","loan_officer"=>"INT","name"=>"CHAR","contact"=>"INT","idno"=>"INT","others"=>"TEXT","status"=>"INT","time"=>"INT"]);
		}
		
		$exclude = array("id","cdef","status","time","branch","creator","cycles");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","cycles"=>"number",
		"creator"=>"number","status"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; $fields["cdef"]="textarea";
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		
		$defv = array("cdef"=>"[]","status"=>0,"time"=>time(),"creator"=>$sid,"cycles"=>0);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'"); 
		$dedicated = ($res) ? $res[0]['value']:"all"; $bran=0;
		
		if($stid){
			$row = $db->query(2,"SELECT *FROM `$ctbl` WHERE `id`='$stid'");
			$dvals = array_map("prepare",$row[0]); $idno=$dvals['idno']; $bran=$dvals["branch"];
			if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
				foreach($fields as $col=>$dtp){ 
					if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($fields[$col]); }
				}
			}
		}
		else{
			if($cont){
				$sql = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `contact`='$cont'");
				if($sql){
					$row=$sql[0]; $data=array_merge(json_decode($row['others'],1),$row);
					foreach($data as $key=>$val){
						if(!in_array($key,["time","others","creator","id","status"])){ $dvals[$key]=$val; }
					}
				}
				else{ $dvals['contact']=$cont; }
			}
		}
		
		$lis=$infs=""; $ftps=[]; unset($fields['contact']);
		$cols = array_merge(["contact"=>"number"],$fields);
		
		foreach($cols as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname = ucwords(str_replace("_"," ",$field));
				if($field=="loan_officer"){
					if($me['access_level']=="portfolio" && $me["position"]==$dedicated){ $lis.= "<input type='hidden' name='$field' value='$sid'>"; }
					else{
						$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":"";
						$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
						$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
						$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `status`='0' AND NOT `position`='USSD' $cond"); $opts=""; $no=0;
						if($res){
							foreach($res as $row){
								if($dedicated=="all"){
									$uid=$row['id']; $cnd = ($uid==$dvals[$field]) ? "selected":""; $no++;
									$opts.="<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
								}
								else{
									if(isset(staffPost($row['id'])[$dedicated])){
										$uid=$row['id']; $cnd = ($uid==$dvals[$field]) ? "selected":""; $no++;
										$opts.="<option value='$uid' $cnd>".prepare(ucwords($row['name']))."</option>";
									}
								}
							}
						}
						
						if($no==1 && $res[0]['id']==$dvals[$field]){ $lis.="<input type='hidden' name='$field' value='".$res[0]['id']."'>"; }
						else{
							$opts = ($opts=="") ? "<option value='0'>No Staff found</option>":$opts;
							$lis.= "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
						}
					}
				}
				elseif($field=="client_group"){
					$pms = (in_array("manage loan group membership",$perms)) ? 1:0; $blist=[];
					if(!$pms or $stid){ $lis.= "<input type='hidden' name='$field' value='$dvals[$field]'>"; }
					else{
						if($bran){ $cond = "AND `branch`='$bran'"; }
						else{
							$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":"";
							$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
							$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
							if(in_array($me["access_level"],["hq","region"])){
								$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
								foreach($qri as $row){ $blist[$row["id"]]=prepare(ucfirst($row["branch"])); }
							}
						}
							
						if($dvals[$field]){
							$opts = ($pms) ? "<option value='0'>None</option>":"";
							$cond.= ($pms) ? "":" AND `id`='".$dvals[$field]."'";
						}
						else{ $opts = "<option value='0'>None</option>"; }
						
						if($db->istable(2,"cgroups$cid")){
							$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='client_groupset'");
							$gset = ($chk) ? json_decode($chk[0]["value"],1):[]; 
							$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `status`<2 $cond ORDER BY `group_name` ASC");
							if($sql){
								foreach($sql as $row){
									$id=$row['id']; $bid=$row["branch"]; $gdf=json_decode($row["def"],1); $gnm=prepare(ucfirst($row["group_name"]));
									$set = (isset($gdf["sets"])) ? $gdf["sets"]:$gset; $cnd=($id==$dvals[$field]) ? "selected":"";
									$gnm.= ($blist) ? " (".$blist[$bid].")":"";
									if($set){
										if($id==$dvals[$field]){ $opts.="<option value='$id' $cnd>$gnm</option>"; }
										else{
											$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$id'");
											if(intval($chk[0]["tot"])<=$set["maxmembers"]){ $opts.="<option value='$id' $cnd>$gnm</option>"; }
										}
									}
									else{ $opts.= "<option value='$id' $cnd>$gnm</option>"; }
								}
							}
						}
						$lis.= "<p>$fname<br><select name='$field' style='width:100%' required>$opts</select></p>";
					}
				}
				elseif($field=="contact"){
					$onchange = (!$stid) ? "onchange=\"popupload('clients.php?add=$stid&cdn='+this.value.trim())\"":"";
					$lis.= "<p>Client Contact<br><input type='number' style='width:100%' value='".$dvals[$field]."' name='$field' $onchange required></p>";
				}
				elseif($field=="branch"){
					$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'"); $opts="";
					foreach($res as $row){
						$bid=$row['id']; $cnd = ($bid==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$bid' $cnd>".prepare(ucwords($row['branch']))."</option>";
					}
					$opts=($opts=="") ? "<option value='0'>Head Office</option>":$opts;
					$lis.= "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
					}
					$lis.= "<p>$fname<br><select name='$field' style='width:100%' required>$opts</select></p>";
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
					$inp = (array_key_exists($dtype,$accept))? "file":$dtype; 
					$val = prepare(ucfirst($dvals[$field])); $add=($inp=="file") ? $accept[$dtype]:""; 
					if($inp=="file"){ $infs.="$field:"; $ftps[$field]=$dtype; }
					$lis.="<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" accept='$add' id='$field' name='$field' required></p>";
				}
			}
			else{ $lis.= "<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$title = ($stid) ? "Edit Client Info":"Add New Client"; 
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='sform' onsubmit=\"saveclient(event,'$idno','0')\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($cols),1)."'>
				<input type='hidden' name='id' value='$stid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
	}
	
	# create lead
	if(isset($_GET['createld'])){
		$col = trim($_GET['createld']);
		$ctbl = "org".$cid."_clients";
		$ltbl = "client_leads$cid";
		
		$me = staffInfo($sid); $access=$me['access_level']; $cnf=json_decode($me["config"],1);
		$exclude = array("id","cdef","status","time","branch","creator","cycles");
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","cycles"=>"number",
		"creator"=>"number","status"=>"number","time"=>"number");
		
		if(!$db->istable(2,$ltbl)){
			$db->createTbl(2,$ltbl,["branch"=>"INT","loan_officer"=>"INT","name"=>"CHAR","contact"=>"INT","idno"=>"INT","others"=>"TEXT","status"=>"INT","time"=>"INT"]);
		}
		
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $ftc['client_location']="text";
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[]; 
		$added = (isset($_SESSION['leadcols'])) ? json_decode(base64_decode($_SESSION['leadcols'])):[];
		if($col){ array_push($added,$col); $_SESSION['leadcols']=base64_encode(json_encode($added,1)); }
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		
		$fields = array_keys($ftc); $cols = array_diff($fields,$exclude);
		$needed = array_merge(array("name","contact","idno","client_location","loan_officer"),$added); 
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		
		$setts = []; $idno=0;
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		$dedicated = (isset($setts['officers'])) ? $setts['officers']:"all";
		
		$lis=$infs=$cls=""; $ftps=[];
		foreach($ftc as $field=>$dtype){
			if(in_array($field,$needed)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="loan_officer"){
					if($me['access_level']=="portfolio" && $me["position"]==$dedicated){ $lis.= "<input type='hidden' name='$field' value='$sid'>"; }
					else{
						$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":"";
						$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
						$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
						$res = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `status`='0' AND NOT `position`='USSD' $cond"); $opts=""; $no=0;
						if($res){
							foreach($res as $row){
								if($dedicated=="all"){
									$uid=$row['id']; $no++;
									$opts.="<option value='$uid'>".prepare(ucwords($row['name']))."</option>";
								}
								else{
									if(isset(staffPost($row['id'])[$dedicated])){
										$uid=$row['id']; $no++;
										$opts.="<option value='$uid'>".prepare(ucwords($row['name']))."</option>";
									}
								}
							}
						}
						
						if($no==1){ $lis.="<input type='hidden' name='$field' value='".$res[0]['id']."'>"; }
						else{
							$opts = ($opts=="") ? "<option value='0'>No Staff found</option>":$opts;
							$lis.= "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
						}
					}
				}
				elseif($field=="idno"){
					$lis.= "<p>Client Idno (Optional)<br><input type='number' value='0' style='width:100%' name='$field'></p>";
				}
				elseif($field=="branch"){
					$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'"); $opts="";
					foreach($res as $row){ 
						$opts.="<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>";
					}
					$opts=($opts=="") ? "<option value='0'>Head Office</option>":$opts;
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$opts.="<option value='$drop'>".prepare(ucwords($drop))."</option>";
					}
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $opts=""; 
					foreach($res as $row){
						$opts.="<option value='".prepare(ucfirst($row[$col]))."'></option>";
					}
					$ran = rand(12345678,87654321);
					$lis.="<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
				}
				elseif($dtype=="textarea"){
					$lis.="<p>$fname<br><textarea class='mssg' name='$field' required></textarea></p>";
				}
				else{
					$inp = (array_key_exists($dtype,$accept))? "file":$dtype; $add=($inp=="file") ? $accept[$dtype]:"";  
					if($inp=="file"){ $infs.="$field:"; $ftps[$field]=$dtype; }
					$lis.="<p>$fname<br><input type='$inp' style='width:100%' accept='$add' id='$field' name='$field' required></p>";
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='0'>"; }
		}
		
		foreach($cols as $one){
			if(!in_array($one,$needed)){ $cls.="<option value='$one'>".ucwords(str_replace("_"," ",$one))."</option>"; }
		}
		
		$addcol = ($cls) ? "<p>Add More Field<br><select style='width:75%;' id='cdl'>$cls</select> <span class='bts' onclick=\"popupload('clients.php?createld='+$('#cdl').val())\"
		style='padding:5px 10px;float:right;cursor:pointer'><i class='fa fa-plus'></i> Add</span></p>":"";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>Create Client Lead</h3><br>
			<form method='post' id='lform' onsubmit=\"savelead(event)\">
				<input type='hidden' name='leadkeys' value='".json_encode($fields,1)."'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'>$addcol $lis<br>
				<p style='text-align:right'><button class='btnn'>Create</button></p>
			</form>
		</div>";
	}
	
	# view client leads
	if(isset($_GET['vleads'])){
		$view = trim($_GET['vleads']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean(strtolower($_GET['str'])),"0"):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$fro = (isset($_GET['fdy'])) ? strtotime($_GET['fdy']):strtotime("2023-Jan");
		$dto = (isset($_GET['tdy'])) ? strtotime($_GET['tdy']):strtotime("Today");
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
	
		$vtp = ($view) ? $view:0; $perpage=30;
		$fdy = ($fro>$dto) ? $dto:$fro; 
		$fdy = ($str) ? strtotime("2022-Jan"):$fdy;
		$dtu = ($fro>$dto) ? $fro:$dto; $dtu+=86399;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage); $cnf=json_decode($me["config"],1);
		
		$cols = ["branch"=>"INT","loan_officer"=>"INT","name"=>"CHAR","contact"=>"INT","idno"=>"INT","others"=>"TEXT","status"=>"INT","time"=>"INT"];
		if(!$db->istable(2,"client_leads$cid")){ $db->createTbl(2,"client_leads$cid",$cols); }
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$load = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$sid'":$load;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		$cond.= ($vtp==2) ? "":" AND `status`='$vtp'";
		$cond.= ($stid) ? " AND `loan_officer`='$stid'":"";
		$cond.= ($str) ? " AND (`name` LIKE '%$str%' OR `contact` LIKE '%$str%' OR (`idno`='$str' AND NOT `idno`='0'))":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="`idno`='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
	
		$no=($perpage*$page)-$perpage; $fro=$no+1; $trs=$offs=$brns=$grups="";
		$res = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `time` BETWEEN $fdy AND $dtu $cond ORDER BY name,status ASC $lim");
		if($res){
			foreach($res as $row){
				$states = array("<span style='color:orange;'><i class='fa fa-circle'></i> Unboarded</span>","<span style='color:green;'><i class='fa fa-circle'></i> Boarded</span>");
				$bname=$brans[$row['branch']]; $name=prepare(ucwords($row['name'])); $rid=$row['id']; $cont=$row['contact']; $idno=$row['idno']; $no++;
				$others=json_decode($row['others'],1); $usa=$staff[$others['creator']]; $loc=prepare(ucwords($others['client_location'])); $kn=0; $go=1; $ols="";
				unset($others['client_location']); unset($others['creator']); $tym=date("d.m.Y, H:i",$row["time"]);
				if(is_array($others)){
					foreach($others as $key=>$val){
						if($val){ $ols.= "<li>".ucwords(str_replace("_"," ",$key)).": <i>".prepare(ucfirst($val))."</i></li>"; $kn++; }
					}
				}
				
				if($kn==0){ $ots="----"; }
				else{ $ots = ($kn>2) ? "<a href='javascript:void(0)' onclick=\"popupload('clients.php?lcols=$rid')\"><i class='bi-list-stars'></i> View</a>":$ols; }
				if($me['access_level']=="portfolio"){ $go=($row['loan_officer']==$sid) ? 1:0; }
				$coms = json_decode($row["comments"],1); $tcm=($coms) ? count($coms):0;
				
				$act = "<i class='bi-chat-right-text' style='color:#008080;cursor:pointer;font-size:20px' onclick=\"loadpage('clients.php?vcoms=$rid')\" title='Interactions'></i>";
				$act = (in_array("delete client",$perms) && $row['status']==0) ? "<i class='bi-trash' onclick=\"delrecord('$rid','client_leads$cid','Delete client Lead?')\" 
				style='color:#ff4500;cursor:pointer;font-size:20px;margin-left:15px' title='Delete Lead'></i>":"";
				$act.= (in_array("add client",$perms) && $row['status']==0 && $go) ? "<i class='fa fa-user-plus' onclick=\"popupload('clients.php?add&cdn=$cont')\" 
				style='cursor:pointer;font-size:20px;color:#008fff;margin-left:15px' title='Onboard Client'></i>":"";
				
				$trs.= "<tr valign='top' id='rec$rid'><td>$no</td><td>$name</td><td>$idno</td><td>0$cont</td><td>$bname</td><td>$loc</td><td>$ots</td><td>$usa</td><td>$tym</td>
				<td><a href='javascript:void(0)' onclick=\"loadpage('clients.php?vcoms=$rid')\">($tcm) Open</a></td><td>".$states[$row['status']]."</td><td>$act</td></tr>";
			}
		}
		
		$carr = array("Unboarded Leads","Boarded Leads","All Leads");
		foreach($carr as $key=>$des){
			$cnd=($vtp==$key) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>"; 
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('clients.php?vleads=$vtp&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px;' onchange=\"loadpage('clients.php?vleads=$vtp&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `client_leads$cid` WHERE $brn");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
				}
				
				$offs = "<select style='width:150px;font-size:15px' onchange=\"loadpage('clients.php?vleads=$vtp&reg=$rgn&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `client_leads$cid` WHERE `time` BETWEEN $fdy AND $dtu $cond"); $totals = ($sql) ? $sql[0]['total']:0;
		$title = ($str) ? "Search Results ":$carr[$vtp];
		$prnt = ($totals) ? genrepDiv("clientleads.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&v=$vtp&dur=$fdy:$dtu",'right'):"";
		$grps = "<select style='width:160px;font-size:15px;cursor:pointer' onchange=\"loadpage('clients.php?vleads='+cleanstr(this.value))\">$grups</select>";
		
		$sms  = ($totals && in_array("send sms",$perms)) ? "<button class='bts' onclick=\"popupload('clients.php?smsto=$fdy:$dtu&cn=".base64_encode($cond)."')\"
		style='float:right;margin-right:7px;padding:2px 4px'><i class='bi-envelope'></i> Send SMS</button>":"";
		$tdy = date("Y-m-d"); $dtfro=date("Y-m-d",$fdy); $dtto=date("Y-m-d",$dtu);
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;max-width:1380px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($totals).")</h3><hr style='margin-bottom:10px'>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped' style='width:100%;min-width:650px;font-size:15px;' cellpadding='5'>
    					<caption style='caption-side:top;padding:0px 0px 5px 0px'>
							<p style='margin-bottom:10px'>$grps $brns $offs <input type='search' style='float:right;width:150px;padding:4px;font-size:15px' value=\"".prepare($str)."\" placeholder='&#xf002; Search'
							onsearch=\"loadpage('clients.php?vleads=$vtp&str='+cleanstr(this.value))\" onkeyup=\"fsearch(event,'clients.php?vleads=$vtp&str='+cleanstr(this.value))\">
							<p style='margin-bottom:4px'><input type='date' value='$dtfro' id='dfro' style='font-size:15px;padding:4px;width:170px' onchange=\"setdrange()\" max='$tdy'>~ 
							<input type='date' value='$dtto' style='font-size:15px;padding:4px;width:170px' id='dto' onchange=\"setdrange()\" max='$tdy'>
							$prnt $sms</p>
						</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Name</td><td>Idno</td><td>Contact</td>
						<td>Branch</td><td>Client Location</td><td>Other Info</td><td>Creator</td><td>Date Created</td><td>Interactions</td><td colspan='2'>Status</td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"clients.php?vleads=$vtp&reg=$rgn&bran=$bran&staff=$stid&str=".str_replace(" ","+",$str))."
		</div>
		<script>
			function setdrange(id,val){
				var fro = $('#dfro').val().trim(), dto=$('#dto').val().trim();
				loadpage('clients.php?vleads=$vtp&reg=$rgn&bran=$bran&staff=$stid&fdy='+fro+'&tdy='+dto);
			}
		</script>";
		savelog($sid,"Viewed $title Record");
	}
	
	# send sms from client leads
	if(isset($_GET['smsto'])){
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$now = date("Y-m-d")."T".date("H:i");
		$src = explode(":",trim($_GET["smsto"]));
		$cnd = base64_decode(str_replace(" ","+",trim($_GET['cn'])));
		$sql = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `time` BETWEEN $src[0] AND $src[1] $cnd");
		foreach($sql as $row){ $conts[$row['contact']]=ucwords($row['name']); }
		
		$tot = (count($conts)==1) ? "1 Client":count($conts)." Clients";
		$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		
		$opts="<option value='0'> -- Select --</option>";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0' $cond");
		foreach($res as $row){
			$opts.="<option value='254".$row['contact']."'>".prepare(ucwords($row['name']))."</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:350px'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>Send SMS to $tot</h3><br>
			<form method='post' id='smfom' onsubmit=\"sendsms(event)\">
				<input type='hidden' name='csmto' value='".json_encode($conts,1)."'>
				<p>Message (Use acronym <b>CLIENT</b> to be replaced with client Name)<br>
				<textarea name='cmssg' class='mssg' style='min-height:140px' required></textarea></p>
				<p>Send SMS on<br><input type='datetime-local' style='width:100%' min='$now' value='$now' name='sendtm' required></p>
				<p>Send Sample SMS to<br><select name='addto' style='width:60%'>$opts</select>
				<button class='btnn' style='float:right'>Send</button></p><br>
			</form><br>
		</div>";
	}
	
	# view more fields from client leads
	if(isset($_GET['lcols'])){
		$rid = trim($_GET['lcols']);
		$sql = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `id`='$rid'");
		
		if(!$sql){ exit(); }
		$data = json_decode($sql[0]['others'],1); unset($data['creator']); unset($data['client_location']); $trs="";
		foreach($data as $col=>$val){
			if($val){ $trs.="<tr><td><b>".ucwords(str_replace("_"," ",$col))."</b></td><td>".prepare(ucfirst($val))."</td></tr>"; }
		}
		
		echo "<div style='max-width:400px;margin:0 auto;padding:10px'>
			<h3 style='text-align:center;font-size:23px;color:#191970'>".ucwords(prepare($sql[0]['name']))."</h3><br>
			<table style='width:100%' cellpadding='5' class='table-bordered'>$trs</table><br>
		</div>";
	}
	
	# manage clients
	if(isset($_GET['manage'])){
		$view = trim($_GET['manage']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean(strtolower($_GET['str'])),"0"):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
		$ctbl = "org".$cid."_clients";
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 30; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		$exclude = array("id","cdef","time","name","status","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		if($view=="" && !isset($_GET["fv"])){
			$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `status`='1'");
			$tot = ($chk) ? intval($chk[0]['tot']):0; $view=($tot) ? 1:$view;
		}
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		$fields = array_keys($ftc);
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans[]="None";
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$load = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "`loan_officer`='$sid'":$load;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? setRegion($cnf["region"]):$cond;
		$cond.= (!$str && ($view=="0" or $view>0)) ? " AND `status`='$view'":" AND `status`<8";
		$cond.= ($stid) ? " AND `loan_officer`='$stid'":"";
		if($str){
			if(in_array(substr($str,0,3),["t11","f12"]) && $db->istable(3,"wallets$cid")){
				$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='".substr($str,3)."'");
				if($chk){ $cond.=" AND `id`='".$chk[0]['client']."'"; }
				else{ $cond.="AND `id`='0'"; }
			}
			else{
				$skip = array_merge($exclude,array("branch","loan_officer","cycles")); $slis="";
				foreach($fields as $col){
					if(!in_array($col,$skip)){ $slis.="`$col` LIKE '$str%' OR "; }
					if($col=="name"){ $slis.="`$col` LIKE '%$str%' OR "; }
				}
				$cond.= " AND (".rtrim(trim($slis),"OR").")";
			}
		}
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="`idno`='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$qry = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='application_status'");
		$colors = array("#008fff","#4682b4","#BA55D3","#9370DB","#3CB371","#C71585","#DA70D6","#663399","#008080","#FF6347","#8FBC8F","#2F4F4F","#008fff");
		$no=($perpage*$page)-$perpage; $fro=$no+1; $trs=$ths=$offs=$brns=""; $points=($qry) ? $qry[0]['value']:0;
		$rem = array_diff(array_keys($ftc),$exclude);
		
		$cutf = (count($rem)>8) ? array_slice($rem,0,8):$rem; $cgrps[0]="None";
		$show = (isset($_COOKIE['tfds'])) ? json_decode($_COOKIE['tfds']):$cutf;
		if(in_array("client_group",$show)){
			if($db->istable(2,"cgroups$cid")){
				$sql = $db->query(2,"SELECT *FROM `cgroups$cid`");
				if($sql){
					foreach($sql as $row){ $cgrps[$row["id"]]=prepare(ucfirst($row["group_name"])); }
				}
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY name,status ASC $lim");
		if($res){
			foreach($res as $row){
				$color=$colors[rand(0,12)]; $no++; $rid=$row['id']; $tds=""; $prof=substr(ucfirst($row['name']),0,1); $idno=$row['idno']; 
				$states = array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",
				1=>"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Blacklisted</span>",
				3=>"<span style='color:#2F4F4F;'><i class='fa fa-circle'></i> UnApproved</span>",4=>"<span style='color:#1E90FF;'><i class='fa fa-circle'></i> OnSignup</span>");
				
				foreach($ftc as $col=>$dtp){
					if(in_array($col,$show)){
						$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
						$val = ($col=="loan_officer") ? $staff[$row[$col]]:$val;
						$val = ($col=="client_group") ? $cgrps[intval($row[$col])]:$val;
						$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
						$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
						$tds.= "<td>$val</td>"; $ths.=($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
					}
				}
				
				$name=prepare(ucwords($row['name'])); $cdf=json_decode($row['cdef'],1); $pnts=(isset($cdf["mpoints"])) ? $cdf["mpoints"]:0;
				$tds.= ($view>7) ? "":"<td>".$states[$row['status']]."</td>"; $tdm=($points) ? "<td style='text-align:center'>$pnts</td>":""; 
				$trs.="<tr onclick=\"loadpage('clients.php?showclient=$idno')\"><td>$no</td><td>$name</td>$tdm $tds</tr>";
			}
		}
		else{
			foreach($fields as $key=>$col){
				if(in_array($col,$show)){ $ths.="<td>".ucfirst(str_replace("_"," ",$col))."</td>"; }
			}
		}
		
		$grups = "<option value=''>All Clients</option>";
		$carr = (defined("CLIENT_STATES")) ? CLIENT_STATES:array("Dormant Clients","Active clients","Blacklisted Clients");
		foreach($carr as $key=>$des){
			$cnd=($view==$key && strlen($view)>0 && !$str) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('clients.php?fv&manage=$view&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px;' onchange=\"loadpage('clients.php?fv&manage=$view&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `$ctbl` WHERE $brn");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.= ($off) ? "<option value='$off' $cnd>".$staff[$off]."</option>":"";
				}
				
				$offs = "<select style='width:150px;font-size:15px' onchange=\"loadpage('clients.php?fv&manage=$view&reg=$rgn&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` WHERE $cond"); $totals = ($sql) ? $sql[0]['total']:0;
		$stx = ($str) ? "Search Results ":"Registered Clients";
		$title = ($view>7) ? "Client Leads":$stx; $title = ($view!=null && $view<7 && !$str) ? $carr[$view]:$title;
		$grps = ($view>7) ? "":"<select style='width:150px;font-size:15px;cursor:pointer' onchange=\"loadpage('clients.php?fv&manage='+cleanstr(this.value))\">$grups</select>";
		
		$ths.= ($view>7) ? "":"<td>Status</td>"; $thm=($points) ? "<td>M-Points</td>":"";
		$vcol = (count($rem)>8) ? "<i class='bi-sort-down-alt' style='float:right;margin-left:6px;cursor:pointer;font-size:32px;margin-top:-8px' title='Sort Columns'
		onclick=\"popupload('clients.php?sortcols')\"></i>":"";
		
		$prnt = ($totals) ? genrepDiv("clients.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&v=$view",'right'):"";
		$import = (in_array("add client",$perms) && $view<8) ? "<button class='bts' style='float:right;font-size:14px;padding:2px 5px;margin-left:7px' 
		onclick=\"popupload('clients.php?import')\"><i class='fa fa-level-up'></i> Import</button>":"";
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;max-width:1380px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($totals).")
				<input type='search' onkeyup=\"fsearch(event,'clients.php?manage=$view&str='+cleanstr(this.value))\" onsearch=\"loadpage('clients.php?fv&manage=$view&str='+cleanstr(this.value))\" 
				value='".prepare($str)."' style='float:right;width:180px;padding:5px;font-size:15px' placeholder='&#xf002; Search'></h3><hr>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped stbl' style='width:100%;min-width:650px;font-size:15px;' cellpadding='5'>
    					<caption style='caption-side:top;padding:0px 0px 5px 0px'>$grps $brns $offs $vcol $import $prnt</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Name</td>$thm $ths</tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"clients.php?fv&manage=$view&reg=$rgn&bran=$bran&staff=$stid&str=".urlencode($str))."
		</div>";
		
		savelog($sid,"Viewed $title Record");
	}
	
	# sort cols
	if(isset($_GET['sortcols'])){
		$ctbl = "org".$cid."_clients";
		$exclude = array("id","cdef","time","gender","name","status","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		
		$rem = array_diff(array_keys($ftc),$exclude);
		$cutf = (count($rem)>8) ? array_slice($rem,0,8):$rem; 
		$show = (isset($_COOKIE['tfds'])) ? json_decode($_COOKIE['tfds']):$cutf;
		
		foreach($rem as $col){
			$cnd = (in_array($col,$show)) ? "checked":"";
			$lis[] = "<li style='list-style:none;padding:5px 0px'><input type='checkbox' name='cols[]' value='$col' $cnd>&nbsp; ".ucwords(str_replace("_"," ",$col))."</li>";
		}
		
		if(count($lis)==1){ $trs = "<tr><td>".implode("",$lis)."</td></tr>"; }
		else{
			$chunk = array_chunk($lis,ceil(count($lis)/2));
			$trs = "<tr valign='top'><td>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>";
		}
		
		$data = array_chunk($lis,ceil(count($lis)/2));
		echo "<div style='padding:10px;margin:0 auto;max-width:500px'>
			<h3 style='font-size:23px;text-align:;'>Filter client Fields</h3><hr>
			<table cellpadding='7' style='width:100%'> $trs </table><hr>
			<p style='text-align:right'><button class='btnn' onclick='setcols()'>View Selected</button></p><br>
		</div>";
	}
	
	# import clients
	if(isset($_GET['import'])){
		$ctbl="org".$cid."_clients";
		$exclude = array("id","cdef","status","time","branch","cycles","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='".$cid."' AND `name`='$ctbl'");
		$fields = ($res) ? json_decode($res[0]['fields'],1):[]; $tds=$ths=""; $len=65;
	
		foreach($fields as $col=>$dtp){
			if(!in_array($col,$exclude) && !in_array($dtp,["image","pdf","doc"])){ $tds.="<td>".str_replace("_"," ",ucfirst($col))."</td>"; $len++; }
		}
		
		if(count($fields)){
			for($i=65; $i<$len; $i++){ $ths.="<td>".chr($i)."</td>"; }
		}
		
		echo "<div style='padding:10px;max-width:550px;margin:0 auto;min-width:500px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Import New Clients</h3><br>
			<p style='font-size:14px'>Your Excel file <b>MUST</b> be formatted as below. <b>Note:</b> For Loan Officer, 
			use their ID Numbers as in the system. If your file has date, format as <b>".date('d-m-Y')."</b><p> 
			<div style='width:100%;overflow:auto'>
				<table style='width:100%;font-size:13px;font-weight:bold;color:#4682b4' class='table-bordered' cellpadding='5'>
					<tr style='background:#f0f0f0;text-align:center;'>$ths</tr> <tr valign='top' style='color:#191970'>$tds</tr>
				</table>
			</div><br>
			<form method='post' id='cform' onsubmit='importclients(event)'>
				<table style='width:100%' cellpadding='5'>
					<tr><td>Select Your Filled Excel File<br><input type='file' id='cxls' name='cxls' accept='.csv,.xls,.xlsx' required></td>
					<td><button class='btnn' style='float:right'>Upload</button></td></tr>
				</table>
			</form><br>
		</div><br>";
	}
	
	// show client
	if(isset($_GET['showclient'])){
		$idno = trim($_GET['showclient']);
		$tbl = "org$cid"."_clients"; $sett=[];
		$vtp = (isset($_GET["vtp"])) ? trim($_GET["vtp"]):"lnhist";
		$exclude = array("id","cdef","time","gender","status","name");
		
		$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `idno`='$idno'"); $cinfo=($res) ? $res[0]:[];
		if(count($cinfo)<1){
			echo "<script> window.history.back(); </script>"; exit();
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='intrcollectype' OR `setting`='client_awarding')");
		if($qri){
			foreach($qri as $row){ $sett[$row['setting']]=prepare($row['value']); }
		}
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'");
		$def = array("id"=>"number","name"=>"text","contact"=>"number","idno"=>"number","cdef"=>"textarea","branch"=>"number","loan_officer"=>"text","status"=>"number","time"=>"number");
		$align = ($_GET['md']<600) ? "text-align:center":""; $mpnt=(isset($sett["client_awarding"])) ? $sett["client_awarding"]:0;
		$intrcol = (isset($sett["intrcollectype"])) ? $sett["intrcollectype"]:"Accrued";
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $doc="";
		if($mpnt){
			calc_mpoints($idno); 
			$cinfo["cdef"]=$db->query(2,"SELECT `cdef` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['cdef'];
		}
		
		$cdef = json_decode($cinfo["cdef"],1); $uid=$cinfo["id"];
		$mtx = (strpos($_SERVER["HTTP_HOST"],"msolar")!==false) ? "MSOLAR POINTS":"LOYALTY POINTS";
		foreach(["mpoints"=>$mtx,"savings"=>"ACC BALANCES (KSH)"] as $set=>$title){
			$val = (isset($cdef[$set])) ? $cdef[$set]:0; $cert=$whtl="";
			$icon = ($set=="savings") ? "":"<i class='bi-award'></i>";
			if($set=="mpoints"){
				$autod = (isset($cdef["autodisburse"])) ? $cdef["autodisburse"]:0; $bgcol="#663399";
				$cert = ($mpnt) ? "<i class='bi-list-stars' style='color:#F5DEB3;font-size:25px;cursor:pointer;float:right' onclick=\"popupload('clients.php?vpoints=$idno')\" title='View Details'></i>":"";
				$whtl = ($autod) ? "<span style='float:right;color:#98FB98' title='Whitelisted for autodisbursement'><i class='bi-star-fill'></i> <i class='bi-star-fill'></i></span>":"";
			}
			elseif($set=="savings"){
				$sqr = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'"); 
				$wbal = $val=($sqr) ? $sqr[0]['balance']+$sqr[0]["investbal"]+$sqr[0]["savings"]:0; $bgcol="#154eb7";
				$cert = "<button class='bts' onclick=\"loadpage('clients.php?wallet=$uid')\" style='color:blue;font-size:14px;float:right;border:0px;outline:none;padding:4px 8px;
				border-radius:30px;background:#B0E0E6'><i class='bi-box-arrow-up-right'></i> View</button>";
			}
			
			$doc.= "<div class='col-12 col-sm-auto col-md-auto col-lg-4 col-xl-auto mt-2 mb-2'>
				<div style='padding:7px;min-height:80px;min-width:190px;background:$bgcol;color:#7FFF00;font-family:signika negative'>
					<p style='color:#fff;font-size:13px;margin-bottom:10px;'>$title $whtl</p>
					<h3 style='font-size:23px;'>$icon ".fnum($val)." $cert</h3>
				</div>
			</div>";
		}
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			if(!is_dir("../docs/temps")){ mkdir("../docs/temps",0777,true); }
			foreach($ftc as $col=>$dtp){ 
				if($dtp=="image"){
					$df = $cinfo[$col]; unset($ftc[$col]); 
					if(strlen($df)>2){ $pic = getphoto($df); file_put_contents("../docs/temps/$df",base64_decode($pic)); $img="$path/docs/temps/$df"; }
					else{ $img="$path/docs/img/tempimg.png"; }
					$doc .= "<div class='col-auto col-sm-4 col-md-3 col-lg-2 col-xl-auto mt-2'>
					<div style='min-width:100px;text-align:center;padding:0px 5px'>
						<img src='$img' style='height:60px;cursor:pointer;max-width:100%' onload=\"rempic('$df')\" onclick=\"popupload('media.php?doc=img:$df&tbl=$tbl:$col&fd=idno:$idno')\"><br>
						<span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span>
					</div></div>";
				}
				if($dtp=="pdf" or $dtp=="docx"){
					$df = $cinfo[$col]; unset($ftc[$col]); 
					$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
					$doc .= "<div class='col-auto col-sm-auto col-md-auto col-lg-auto col-xl-auto mt-2'>
					<div style='min-width:100px;text-align:center;padding:0px 5px'>
						<img src='$img' style='height:68px;cursor:pointer' onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$tbl:$col&fd=idno:$idno')\"><br>
						<span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span>
					</div></div>";
				}
			}
		}
		
		$fields = array_keys($ftc); $prods=[];
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		foreach($res as $row){
			$brans[$row['id']]=prepare(ucwords($row['branch']));
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$prods[$row['id']]=prepare(ucwords($row['product'])); $pcats[$row["id"]]=$row["category"];
			}
		}
		
		$stbl="org$cid"."_staff"; $staff=[]; $lcl=0;
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$trs=$ltrs=""; $row=$cinfo;
		foreach($ftc as $col=>$dtp){
			if(!in_array($col,$exclude)){
				$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
				$val = ($col=="loan_officer") ? $staff[$row[$col]]:$val;
				$val = ($col=="creator") ? $staff[$row[$col]]:$val;
				$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
				$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
				if($col=="client_group"){
					if($row[$col]){
						$sql = $db->query(2,"SELECT `group_name` FROM `cgroups$cid` WHERE `id`='$val'");
						$val = ($sql) ? prepare(ucfirst($sql[0]["group_name"])):"None";
					}
					else{ $val="None"; }
				}
				
				$trs.= "<tr valign='top'><td style='color:#191970'><b>".ucfirst(str_replace("_"," ",$col))."</b></td><td>$val</td></tr>";
			}
		}
		
		$me = staffInfo($sid); $crid=$cinfo['id']; $tdy=strtotime(date("Y-M-d")); $vpn=0; 
		$perms = array_map("trim",getroles(explode(",",$me['roles']))); $acst=0; $lbtn="";
		$ltbl = "org$cid"."_loans"; $cst=$cinfo['status']; $istb=($db->istable(3,"translogs$cid")) ? 1:0;
		$rating = array("Unrated"=>["#dcdcdc","#191970"],"Good"=>["green","#fff"],"BLC"=>["#8B008B","#fff"],"BFC"=>["#DC143C","#fff"],"CFC"=>["#B8860B","#fff"]);
		
		if($vtp=="lnhist"){
			$page = (isset($_GET["pg"])) ? trim($_GET["pg"]):1; $lim=getLimit($page,10);
			$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `client_idno`='$idno' ORDER BY `balance` DESC,`disbursement` DESC $lim");
			if($res){
				foreach($res as $key=>$row){
					$cdes = @json_decode($row['clientdes'],1); $rate=$cdes['rating']; $agent=(isset($cdes["agent"])) ? $cdes["agent"]:0; $tm=$row['loan'];
					$qri = $db->query(2,"SELECT MAX(day) AS mdy,SUM((CASE WHEN (day<$tdy) THEN balance ELSE 0 END)) AS arrs FROM `org{$cid}_schedule` WHERE `loan`='$tm'");
					$mdy = $qri[0]["mdy"]; $arrs=intval($qri[0]["arrs"]); $lnsr="client";
					
					if($intrcol=="Cash" && $row["balance"]>0 && $mdy>time()){
						setInterest($tm); $row=$db->query(2,"SELECT *FROM `$ltbl` WHERE `loan`='$tm'")[0];
					}
					 
					$amnt=fnum($row['amount']); $dur=$row['duration']; $bal=fnum($row['balance']+$row['penalty']); $last=$mdy; $rcond=1;
					$day=date("d-m-Y",$row['disbursement']); $totpay=fnum($row['paid']); $penal=fnum($row['penalty']); $pid=$row['loan_product']; 
					$prod=(isset($prods[$pid])) ? $prods[$pid]:"----"; $tid=$row['tid']; $pen=$row['penalty']; $bl=$row['balance']+$pen; $avs=0;
					if($tid && isset($pcats[$pid])){
						$lnsr = $pcats[$pid];
						if($pcats[$pid]=="asset"){
							$chk = $db->query(2,"SELECT `loantype` FROM `org$cid"."_loantemplates` WHERE `id`='$tid'");
							if($chk){
								$lntp = explode(":",$chk[0]["loantype"]);
								$chk = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$lntp[2]'");
								$roq = $chk[0]; $qty=ceil($row["amount"]/$roq["asset_cost"]); $qt=($qty>1) ? "$qty ":"";
								$prod = $qt.prepare($roq["asset_name"]);
							}
						}
					}
					
					$edt = ($bl>0 && in_array("transfer clients",$perms)) ? "<i class='bi-pencil-square' style='cursor:pointer;color:#663399;' title='Update Collection Agent'
					onclick=\"popupload('loans.php?assign=$tm&agd=$agent')\"></i> ":"";
					$aname = ($agent) ? $edt.$staff[$agent]:"None"; $lnst=(isset($cdes["loanst"])) ? $cdes["loanst"]:0;
					
					if($bl>0 and time()<$last){ $cond="<span style='color:green'><i class='fa fa-circle'></i> Running</span>"; }
					elseif($bl>0 and time()>$last){ $cond="<span style='color:#FF1493'><i class='fa fa-circle'></i> Default</span>"; }
					else{ $cond="<span style='color:green'><i class='bi-check2-circle'></i> Completed<br>".date('d-m-Y',$row['status'])."</span>"; }
					if($lnst){ $cond="<span style='color:#FF1493'><i class='fa fa-lock'></i> Deactivated</span>"; }
					
					if($bl){
						$qry = $db->query(2,"SELECT *FROM `rescheduled$cid` WHERE `loan`='$tm'");
						if($qry){
							$rcond=0; $day.="<br><i style='font-size:13px;color:grey'>Rescheduled</i>";
						}
					}
					
					if($tid && $istb){ $avs = $db->query(3,"SELECT `ref` FROM `translogs$cid` WHERE `ref`='TID$tid'"); } 
					$arep = ($agent) ? "<a href='javascript:void(0)' onclick=\"loadpage('payments.php?processed&ofid=$agent&lid=$tm')\"><i class='bi-eye'></i> Collections</a>":"";
					$prnt = "<i class='bi-printer' style='color:#000080;cursor:pointer;font-size:20px' title='Payment Statement' onclick=\"printdoc('payreport.php?ln=$tm','pay')\"></i>";
					$prnt.= (in_array("generate loan statement",$perms) && $avs) ? " &nbsp; <i class='bi-journal-text' style='color:#0000CD;cursor:pointer;font-size:18px' 
					title='Generate Loan Statement' onclick=\"printdoc('reports.php?vtp=loanst&mn=$tm&yr=1','pay')\"></i>":"";
					$edipen = ((!$key or $bl>0) && in_array("edit client penalty",$perms)) ? "<a href='javascript:void(0)' onclick=\"editpenalty('$tm','$pen')\">Edit</a>":"";
					$asign = ($bl>0 && in_array("transfer clients",$perms) && !$agent) ? "<a href='javascript:void(0)' onclick=\"popupload('loans.php?assign=$tm')\">
					<i class='bi-broadcast'></i> Assign</a>":$arep; 
					
					$pent = ($key==(count($res)-1)) ? "<span class='penam'>$penal</span>":$penal; $ptd="";
					$more = ($tid) ? "<a href='javascript:void(0)' onclick=\"popupload('loans.php?loandes=$tm')\">Details</a>":"";
					if($mpnt && isset($cdef["lastcomp"])){
						$lids = $cdef["lastcomp"]["lids"]; 
						if(isset($lids[$tm])){
							$ptd = "<td>".$lids[$tm]['points']."<br><a href='javascript:void(0)' onclick=\"popupload('clients.php?vpoints=$idno&ln=$tm')\">View</a></td>"; $vpn++;
						}
					}
					
					$bgcl = $rating[$rate][0]; $color=$rating[$rate][1]; $pbal=($bal) ? fnum(getLoanBals($tm)["principal"]):0;
					$tag = "<span style='padding:3px 12px;font-size:14px;font-weight:bold;position:relative;margin-left:3px;background:$bgcl;color:$color'>
					<i class='bi-diamond-fill' style='color:#fff;position:absolute;margin-left:-20px;font-size:16px'></i> $rate</span>";
					$edit = ($bl>0) ? "<a href='javascript:void(0)' onclick=\"popupload('clients.php?loanact=$tm&set=$rcond&id=$idno&lnsr=$lnsr')\"><i class='bi-list-stars'></i> Action</a>":"";
					
					$ltrs.= "<tr valign='top'><td>$day<br>$tag</td><td>$amnt<br>$more</td><td>$prod<br>$dur days</td><td><span style='font-size:14px'>$aname</span><br>$asign</td><td>$pent $edipen</td>
					<td>".fnum($arrs)."</td><td>$pbal</td><td>$bal</td><td>$totpay <a href='javascript:void(0)' onclick=\"popupload('loans.php?payhistory=$tm')\">View</a><br> $prnt</td>$ptd<td>$cond<br>$edit</td></tr>";
				}
			}
			
			$qtt = $db->query(2,"SELECT COUNT(*) AS tot,SUM(balance+penalty) AS tbal FROM `$ltbl` WHERE `client_idno`='$idno'");
			$vth = ($vpn) ? "<td>Points</td>":""; $ltots=($qtt) ? intval($qtt[0]["tot"]):0; $acst=($qtt) ? intval($qtt[0]["tbal"]):0;
			
			$data = "<table cellpadding='5' style='border:1px solid #dcdcdc;border-collapse:collapse;width:100%;min-width:750px;font-size:14px;font-family:;' border='1'>
				<tr style='color:#191970;font-weight:bold;font-size:14px;background:#f8f8ff'><td>Date</td><td>Loan</td><td>Product</td>
				<td>Coll.Agent</td><td>Penalty</td><td>Arrears</td><td>Principal</td><td>Total Bal</td><td>Repayment</td>$vth<td>Status</td></tr> $ltrs
			</table>".getLimitDiv($page,10,$ltots,"clients.php?showclient=$idno&vtp=$vtp");
			
			if(($cst==0 && $acst>0) or ($cst==1 && $acst==0)){
				$nst = ($acst) ? 1:0;
				$db->execute(2,"UPDATE `org$cid"."_clients` SET `status`='$nst' WHERE `idno`='$idno'");
			}
		}
		elseif($vtp=="cdocs"){
			$clid = $cinfo['id']; $dtrs="";
			if(!$db->istable(2,"sysdocs$cid")){ $db->createTbl(2,"sysdocs$cid",["source"=>"CHAR","owner"=>"INT","name"=>"CHAR","def"=>"TEXT","status"=>"INT","time"=>"INT"]); }
			$qri = $db->query(2,"SELECT *FROM `sysdocs$cid` WHERE `source`='client' AND `owner`='$clid' ORDER BY `time` DESC");
			if($qri){
				foreach($qri as $row){
					$def=json_decode($row["def"],1); $dname=prepare(ucfirst($def["dname"])); $cdoc=$row["name"]; 
					$by=$def["from"]; $ext=explode(".",$cdoc)[1]; $rid=$row["id"];
					
					if(in_array($ext,["png","jpg","jpeg","gif"])){
						if(strlen($cdoc)>2){ $pic = getphoto($cdoc); file_put_contents("../docs/temps/$cdoc",base64_decode($pic)); $img="$path/docs/temps/$cdoc"; }
						else{ $img="$path/docs/img/tempimg.png"; }
						
						$dtrs .= "<div class='col-auto col-sm-4 col-md-3 col-lg-2 col-xl-auto mt-2 mb-2'>
						<div style='min-width:100px;text-align:center;padding:0px 5px'>
							<img src='$img' style='height:90px;cursor:pointer;max-width:100%' onload=\"rempic('$cdoc')\" 
							onclick=\"popupload('media.php?doc=img:$cdoc&tbl=sysdocs$cid:name&fd=id:$rid&nch')\"><br><span style='font-size:13px'>$dname</span>
						</div></div>";
					}
					if(in_array($ext,["pdf","docx","doc"])){
						$img = ($ext=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
						$dtrs .= "<div class='col-auto col-sm-auto col-md-auto col-lg-auto col-xl-auto mt-2 mb-2'>
						<div style='min-width:100px;text-align:center;padding:0px 5px;max-width:140px'>
							<img src='$img' style='height:90px;cursor:pointer' onclick=\"popupload('media.php?doc=$ext:$cdoc&tbl=sysdocs$cid:name&fd=id:$rid')\"><br>
							<span style='font-size:13px'>$dname</span>
						</div></div>";
					}
				}
			}
			
			$lbtn = "<button class='bts' style='float:right;padding:2px 5px;font-size:14px;margin-top:0px;background:#fff' onclick=\"popupload('clients.php?addmed=$clid&idno=$idno')\">
			<i class='bi-upload'></i> Upload</button>";
			$dtrs = ($dtrs) ? $dtrs:"<div class='col-12 mt-2'>No documents Found</div>";
			$data = "<div class='row' style='width:100%'>$dtrs</div>";
		}
		else{
			$data = "";
		}
		
		$cname = prepare(ucwords($cinfo['name'])); $nme=str_replace("'","`",$cname); $add=($cst>7) ? "&lead":""; $susp="";
		if($cst!=1){
			$susp = (in_array("suspend client",$perms) && $cst==0) ? "&nbsp; <button class='bts' style='font-size:14px;padding:3px' onclick=\"suspendclient('$idno','2')\">
			<i class='fa fa-gavel'></i> Blacklist</button>":"";
			$susp = (in_array("activate client",$perms) && $cst==2) ? "&nbsp; <button class='bts' style='font-size:14px;padding:3px' onclick=\"suspendclient('$idno','0')\">
			<i class='bi-download'></i> Activate</button>":$susp;
		}
		
		$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `$ltbl` WHERE `client_idno`='$idno'"); $tlns=intval($chk[0]["tot"]);
		$reset = (isset($cdef["ckey"])) ? "&nbsp; <button class='bts' style='padding:3px' onclick=\"resetpin('$idno')\"><i class='bi-arrow-clockwise'></i> Reset PIN</button>":"";
		$edit = (in_array("edit client",$perms)) ? "<button class='bts' style='padding:3px' onclick=\"popupload('clients.php?add=$crid$add')\"><i class='fa fa-pencil'></i> Edit Info</button> $reset":"";
		$trs.= ($edit or $susp) ? "<tr><td colspan='2' style='text-align:right'><hr><p style='margin-bottom:10px'>$edit $susp</p></td></tr>":"";
		$del = ($tlns<1  && in_array("delete client",$perms)) ? "<button class='bts' style='font-size:15px;margin-left:5px;padding:3px;color:#ff4500' 
		onclick=\"delclient('$idno')\"><i class='bi-person-x'></i> Delete</button>":"";
		
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $clid=$cinfo['id']; $hcs=($mxh) ? "margin-bottom:5px":"";
		$ref = "$del <button class='bts' style='font-size:15px;padding:3px;' onclick=\"loadpage('clients.php?vintrcn=$clid')\"><i class='bi-person-lines-fill'></i> Notes</button>";
		$titles = array("lnhist"=>"Loan History","cdocs"=>"Client Documents","reposs"=>"Reposessed Items"); $opts="";
		foreach($titles as $key=>$tx){
			$cnd = ($key==$vtp) ? "Selected":"";
			$opts.= "<option value='$key' $cnd>$tx</option>";
		}
		
		echo "<div class='container cardv' style='max-width:1400px;background:#fff;overflow:auto;padding:10px 0px'>
			<div class='row' style='margin:0px'>
				<div class='col-12 mb-1'>
					<h3 style='color:#191970;font-size:20px'>$backbtn $cname <span style='float:right'>$ref</span></h3><hr style='$hcs'> 
					<div class='row'>$doc</div>
				</div>
				<div class='col-12 col-md-12 col-lg-4 col-xl-3 mb-2'>
					<p style='padding:6px;background:#f0f0f0;color:#191970;font-weight:bold;text-align:center;margin-bottom:0px;border:1px solid #dcdcdc'>Client Details
					<i class='fa fa-refresh' style='float:right;cursor:pointer' onclick=\"loadpage('clients.php?showclient=$idno')\"></i></p>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<table cellpadding='6' style='width:100%;border:1px solid #dcdcdc;background:;border-top:0px;font-size:14px'>$trs</table>
					</div>
				</div>
				<div class='col-12 col-md-12 col-lg-8 col-xl-9'>
					<p style='padding:6px;background:#f0f0f0;color:#191970;font-weight:bold;text-align:center;margin-bottom:0px;border:1px solid #dcdcdc'>
					<select style='width:150px;float:left;padding:4px;font-size:15px' onchange=\"loadpage('clients.php?showclient=$idno&vtp='+this.value)\">$opts</Select> 
					$titles[$vtp] $lbtn</p> <div style='overflow:auto;width:100%;max-height:$mxh;'>$data</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed $cname account profile");
	}
	
	# loan action options
	if(isset($_GET["loanact"])){
		$lid = trim($_GET["loanact"]);
		$src = trim($_GET["lnsr"]);
		$set = trim($_GET["set"]);
		$me = staffInfo($sid); $idno=trim($_GET["id"]);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		
		$qry = $db->query(2,"SELECT `branch`,`phone`,`clientdes`,`tid` FROM `org$cid"."_loans` WHERE `loan`='$lid'")[0];
		$bid = $qry['branch']; $fon=$qry["phone"]; $des=@json_decode($qry["clientdes"],1); $tid=$qry["tid"];
		$cond = ($bid) ? "`id`='$bid'":"NOT `paybill`='0'"; $lnst=(isset($des["loanst"])) ? $des["loanst"]:0;
		$payb = $db->query(1,"SELECT `paybill` FROM `branches` WHERE $cond")[0]['paybill'];
		$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
		$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
		
		$css = "font-family:signika negative;color:#191970;text-align:center;font-size:14px;background:#f0f0f0;cursor:pointer;width:100%;padding:8px 10px;border:0px";
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='reschedule' AND `client`='$cid'");
		$rset = ($qri) ? $qri[0]['value']:1; $accno="PLN$lid"; $dtx=($lnst) ? "Activate Loan":"Deactivate Loan"; $tds=[]; $avs=0;
		if($tid && $db->istable(3,"translogs$cid")){ $avs = $db->query(3,"SELECT `ref` FROM `translogs$cid` WHERE `ref`='TID$tid'"); }
		
		if($pkey){ $tds[] = "<td><button style='$css;color:#4682b4' onclick=\"popupload('payments.php?reqpay=$accno&payb=$payb&fon=$fon')\"><i class='bi-cash-coin'></i> Make Payment</button></td>"; }
		if(in_array("rate client loan",$perms)){ $tds[] = "<td><button style='$css' onclick=\"popupload('clients.php?tagln=$lid')\"><i class='bi-bookmark-plus'></i> Tag Loan</button></td>"; }
		if(in_array("edit loan",$perms) && !$lnst){ $tds[] = "<td><button style='$css;color:blue' onclick=\"popupload('loans.php?editloan=$lid&src=$src')\"><i class='bi-pencil-square'></i> Edit Details</button></td>"; }
		if(in_array("update running loan amount",$perms) && !$lnst){ $tds[] = "<td><button style='$css;color:#008fff' onclick=\"popupload('loans.php?lncharge=$lid')\"><i class='bi-cash-coin'></i> Add Charges</button></td>"; }
		if(in_array("waive loan interest",$perms) && !$lnst){ $tds[] = "<td><button style='$css;color:green' onclick=\"waiveint('$lid')\"><i class='bi-calculator'></i> Waive Interest</button></td>"; }
		if(in_array("reschedule loan",$perms) && $rset && $set && !$lnst){ $tds[] = "<td><button style='$css' onclick=\"popupload('loans.php?reschedule=$lid')\"><i class='bi-calendar-week'></i> Reschedule</button></td>"; }
		if(in_array("offset loan",$perms) && !$lnst){ $tds[] = "<td><button style='$css' onclick=\"popupload('loans.php?offsetln=$lid')\"><i class='bi-command'></i> Offset Loan</button></td>"; }
		if(in_array("process loan topup",$perms) && !$lnst && $src!="asset"){ $tds[] = "<td><button style='$css' onclick=\"popupload('loans.php?topupln=$lid')\"><i class='bi-bag-plus'></i> Topup Loan</button></td>"; }
		if(in_array("close running loan",$perms)){ $tds[] = "<td><button style='$css' onclick=\"popupload('loans.php?closeln=$lid')\"><i class='bi-circle-square'></i> Close Loan</button></td>"; }
		if(in_array("rollover loan",$perms) && !$lnst){ $tds[] = "<td><button style='$css' onclick=\"popupload('loans.php?rolloverln=$lid')\"><i class='bi-calendar2-plus'></i> Roll-Over Loan</button></td>"; }
		if(in_array("deactivate loan",$perms)){ $tds[] = "<td><button style='$css;color:#008080' onclick=\"deactivateln('$idno','$lid','$lnst')\"><i class='bi-clock-history'></i> $dtx</button></td>"; }
		if(in_array("generate loan statement",$perms) && $avs){ $tds[] = "<td><button style='$css;color:#008080' onclick=\"printdoc('reports.php?vtp=loanst&mn=$lid&yr=1','pay')\">
		<i class='bi-list-stars'></i> Loan Statement</button></td>"; }
		if(in_array("write-off loan",$perms) && !$lnst){ $tds[] = "<td><button style='$css;color:purple' onclick=\"writeoff('$lid')\"><i class='bi-archive'></i> Write-Off Loan</button></td>"; }
		if(in_array("delete running loan",$perms) && !$lnst){ $tds[] = "<td><button style='$css;color:#ff4500' onclick=\"deleteloan('$lid','$idno')\"><i class='bi-trash'></i> Delete Loan</button></td>"; }
		
		if($tds){
			$all = array_chunk($tds,2); $trs="";
			foreach($all as $chunk){ $trs.= "<tr valign='top'>".implode("",$chunk)."</tr>"; }
		}
		else{
			$trs="<tr><td><p style='color:#ff4500;padding:10px;text-align:center;background:#FFE4C4'><i class='bi-info-circle' style='font-size:25px'></i><br> 
			You dont have Permission to update Loan details!</p></td></tr>"; 
		}
		
		echo "<div style='margin:0 auto;padding:20px;max-width:350px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Loan Action Options</h3><br>
			<table style='width:100%' cellpadding='7'>$trs</table><br>
		</div>";
	}
	
	# add media
	if(isset($_GET["addmed"])){
		$uid = trim($_GET["addmed"]);
		$idno = trim($_GET["idno"]);
		
		echo "<div style='max-width:350px;padding:10px;margin:0 auto'>
			<h3 style='text-align:center;font-size:23px'>Upload Client document</h3><br>
			<form method='post' id='dform' onsubmit=\"uploadmed(event,'$idno')\">
				<input type='hidden' name='clientid' value='$uid'>
				<p>Select PDF, Word & Images only<br><input type='file' id='cdoc' required></p>
				<p>Document Title/Name<br><input type='text' style='width:100%' name='dname' required></p><br>
				<p style='text-align:right'><button class='btnn'><i class='bi-upload'></i> Upload</button></p>
			</form><br>
		</div>";
	}
	
	# client wallet
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
		$me = staffInfo($sid); $lim=getLimit($page,20); $wid=createWallet("client",$uid);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
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
				$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='C$uid' ORDER BY `time` DESC");
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
			$sql = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk' AND `time` BETWEEN $dfro AND $dtu ORDER BY `id` DESC,`time` DESC $lim");
			if($sql){
				foreach($sql as $row){
					$revs = $row['reversal']; $rtp=(isJson($revs)) ? "rev":$revs; $tm=$row['time']; $nrv=0;
					if(isJson($revs)){ foreach(json_decode($revs,1) as $one){ if(isset($one["tbl"])){ $nrv+=($one["tbl"]=="sysoverpay") ? 1:0; }}}
					$dy=date("d-m-Y,H:i",$tm); $tid=$row['tid']; $appr=$staff[$row['approval']]; $amnt=fnum($row['amount']); $rid=$row['id']; $tdf=time()-$tm;
					$bal=fnum($row['afterbal']); $css="text-align:right"; $des=prepare(ucfirst($row['details'])); $sgn=($row["transaction"]=="debit") ? "+":"-"; 
					$rev = ($rtp!="norev" && in_array("reverse wallet transaction",$perms) && $row['status']==0 && $tdf<$revtm && !$nrv) ? "<a href='javascript:void(0)' 
					onclick=\"revtrans('$rid')\" style='cursor:pointer'><i class='bi-bootstrap-reboot'></i> Reverse</a>":""; $css1=($row['status']) ? "color:#B0C4DE":"";
					$desc=(strlen($des)>30) ? substr($des,0,30)."...<i title='View More' class='fa fa-plus' style='color:#008fff;cursor:pointer' onclick=\"setcontent('$rid','$des')\"></i>":$des;
					$trs.= "<tr valign='top' style='$css1'><td>$dy</td><td>$tid</td><td id='tr$rid'>$desc</td><td>$appr</td><td style='$css'>$sgn$amnt</td><td style='$css'><b>$bal</b></td><td>$rev</td></tr>";
				}
			}
		}
		
		$qri = $db->query(3,"SELECT COUNT(*) AS tot,MIN(time) AS mnt FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk'");
		$qry = $db->query(3,"SELECT SUM(amount) AS tsum FROM `walletrans$cid` WHERE `wallet`='$wid' AND `transaction`='credit' AND `book`='$vbk' AND 
		`status`='0' AND NOT `details` LIKE 'Loan Repayment%' AND NOT `details` LIKE '[Reversal]%'");
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $tots=intval($qri[0]['tot']); $twd=intval($qry[0]['tsum']); $tdy=date("Y-m-d");
		$mnt = ($qri) ? $qri[0]['mnt']:$tmon; $dfro=($dfro) ? $dfro:$mnt; $dftm=date("Y-m-d",$dfro); $dtm=date("Y-m-d",$dtu);
		$accno = walletAccount($bid,$wid,$vtp);
		
		$roibal = (in_array($vtp,["balance","savings"])) ? $twd:0; $switch=$overdiv="";
		$prnt = ($trs) ? "<button class='bts' style='float:right;padding:3px 6px' onclick=\"printdoc('reports.php?vtp=wallets&mn=$dfro:$dtu&yr=$wid&src=$vtp&vp=$vop','wallet')\">
		<i class='bi-printer'></i> Print</button>":"";
		
		$trs = ($trs) ? $trs:"<tr><td colspan='5'>No Transactions Found</td></tr>";
		$wht = (in_array($vtp,["balance","savings"])) ? "Withdrawals":"Invested Balance";
		if($vtp=="balance"){
			$overd = (defined("WALLET_OVERDRAFT")) ? WALLET_OVERDRAFT:0;
			if($overd){
				$chk = $db->query(3,"SELECT `def` FROM `wallets$cid` WHERE `id`='$wid'");
				$ovbal = ($wbal<0) ? str_replace("-","",$wbal):0; $wbal=($wbal>=0) ? $wbal:0;
				$wdef = ($chk[0]["def"]) ? json_decode($chk[0]["def"],1):[]; $olim=(isset($wdef["ovdlimit"])) ? $wdef["ovdlimit"]:0;
				$chjs = (isset($wdef["charges"])) ? $wdef["charges"]:0; $ovbal-=$chjs; $tobl=fnum($ovbal+$chjs);
				
				$obtn = (in_array("manage wallet overdraft limit",$perms)) ? "<button class='btnn' onclick=\"overdlim('$wid',$olim)\" 
				style='font-size:14px;padding:4px;margin-top:10px;background:#4169E1'><i class='fa fa-cogs'></i> Set Limit</button>":"";
				
				$overdiv = "<div style='color:#fff;background:#8B4513;padding:10px;border-radius:5px;font-Family:signika negative;margin-top:10px'>
					<table style='width:100%'><tr>
						<td><h3 style='font-size:22px'><span style='font-size:13px'>KES</span> $tobl<br><span style='font-size:13px;color:#ccc'>Acc Overdraft</span></h3></td>
						<td style='text-align:right'><span style='font-size:13px;color:#ccc'>Limit Balance</span><br><b style='font-family:arial'>".fnum($olim-$ovbal)."</b><br>$obtn</td>
					</tr></table>
				</div>";
			}
		}
		
		if(in_array($vtp,["balance","savings"])){
			$vtitle = "Transaction List";
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "popupload('payments.php?wfrom=$wid&wtp=$vtp&uid=$uid:client')":"toast('Permission Denied!')";
			$wbtn = (in_array("manage wallet withdrawals",$perms)) ? "<button class='btnn' onclick=\"popupload('payments.php?wfrom=$wid&wtp=real&uid=$uid:client')\" 
			style='background:#BA55D3;font-size:14px;padding:4px;margin-top:10px'><i class='bi-download'></i> Withdraw</button>":"";
		}
		else{
			$trans = (in_array("manage wallet withdrawals",$perms)) ? "transwallet('$wid','$wbal')":"toast('Permission Denied!')";
			$wbtn = (in_array("create investment",$perms)) ? "<button class='btnn' onclick=\"popupload('accounts/investors.php?invest=C$uid')\" 
			style='background:#20B2AA;font-size:14px;padding:4px;margin-top:10px'><i class='bi-bag-plus'></i> Invest</button>":""; $vopt="";
			foreach(array("Transactions","Investments") as $key=>$txt){
				$cnd = ($key==$vop) ? "selected":"";
				$vopt.= "<option value='$key' $cnd>$txt</selected>";
			}
			
			$vtitle = ($vop) ? "Investments List":"Transactions List";
			$switch = "<select style='width:130px;padding:4px;float:right;font-size:15px' onchange=\"loadpage('clients.php?wallet=$uid&vtp=$vtp&vop='+this.value)\">$vopt</select>";
		}
		
		foreach($ttls as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$txt Account</option>";
		}
		
		if($invtbl && $vtp=="investbal"){
			$sql = $db->query(3,"SELECT SUM(amount) AS tsum FROM `investments$cid` WHERE `client`='C$uid' AND `status`='0'");
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
						<select style='width:100%;background:transparent;border:0px;cursor:pointer'onchange=\"loadpage('clients.php?wallet=$uid&vtp='+this.value)\">$lis</select></div>
						<div style='color:#fff;background:#4682b4;padding:10px;border-radius:5px;font-Family:signika negative'>
							<table style='width:100%'><tr>
								<td><h3><span style='font-size:13px'>KES</span> ".fnum($wbal)."<br><span style='font-size:13px;color:#f0f0f0'>Available Balance</span></h3></td>
								<td style='text-align:right'><button class='btnn' style='font-size:14px;padding:4px' onclick=\"popupload('payments.php?wtopup=$wid&wtp=$vtp&uid=$uid:client')\">
								<i class='bi-plus-circle'></i> Deposit</button></td>
							</tr><tr><td colspan='2' style='color:#00FFFF;font-size:14px;'><hr style='margin-top:0px' color='#ccc'>AccNo: $accno
							<button class='btnn' onclick=\"$trans\" style='background:#20B2AA;font-size:14px;padding:4px;float:right'><i class='bi-arrow-left-right'></i> Transfer</button></td></tr></table>
						</div> $overdiv
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
						</table><br>".getLimitDiv($page,20,$tots,"clients.php?wallet=$uid&vtp=$vtp&fro=$dftm&dto=$dtm&vop=$vop")."
					</div>
				</div>
			</div><br>
		</div>
		<script>
			function setrange(){
				var fro = $('#fro').val(), dto=$('#dto').val(); 
				loadpage('clients.php?wallet=$uid&vtp=$vtp&fro='+fro+'&dto='+dto);
			}
			function setcontent(id,des){ $('#tr'+id).html(des); }
		</script>";
		savelog($sid,"Viewed $cname account wallet");
	}
	
	# view m-points
	if(isset($_GET["vpoints"])){
		$idno = trim($_GET["vpoints"]);
		$me = staffInfo($sid); $lis=$btx=$pam="";
		$lid = (isset($_GET["ln"])) ? trim($_GET['ln']):0;
		$perms = getroles(explode(",",$me['roles']));
		
		$sql = $db->query(2,"SELECT `name`,`cdef` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$sqr = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='client_awarding'");
		$name = prepare(ucwords($sql[0]['name'])); $cdef=json_decode($sql[0]['cdef'],1); $set=json_decode($sqr[0]['value'],1);
		$pnts = $cdef["mpoints"]; $def=$cdef["compresult"]; $auto=(isset($cdef["autodisburse"])) ? $cdef["autodisburse"]:0;
		if($lid){ $def=$cdef["lastcomp"]["lids"][$lid]; }
		$cpnt = ($pnts>0) ? $pnts:0; $lim=(isset($cdef["maxset"])) ? $cdef["maxset"]:0;
		
		$txt = ($auto) ? "<tr><td colspan='2' style='color:green'><b><i class='bi-check2-circle'></i> $name is whitelisted for automatic loan disbursement via App</b></td></tr>":"";
		if(in_array("manage client auto-disbursement",$perms)){
			$btx = ($auto) ? "<button class='btnn' onclick=\"autodisburse('$idno','0')\" style='background:#2f4f4f'><i class='bi-clock'></i> Disable Auto-Disburse</button>":
			"<button class='btnn' onclick=\"autodisburse('$idno','1')\"><i class='bi-check2-circle'></i> Enable Auto-Disburse</button>"; $pam=1;
		}
		
		$data = ($lid) ? "<h3 style='font-size:22px;color:#191970;text-align:center'>Loan #$lid Points</h3><br>
		<table cellpadding='5' style='width:100%' class='table-bordered'>
			<tr><td>Loan Amount Points</td><td style='text-align:right'><b>".fnum($def["amount"])."</b></td></tr>
			<tr><td>Timely Paid Installments</td><td style='text-align:right'><b>".fnum($def["timely"])."</b></td></tr>
			<tr><td>Lately Paid Installments</td><td style='text-align:right'><b>".fnum($def["lately"])."</b></td></tr>
			<tr><td>Loan Cycle Points</td><td style='text-align:right'><b>".fnum($def["cycle"])."</b></td></tr>
			<tr><td>Total Points</td><td style='text-align:right'><b>".fnum($def["points"])."</b></td></tr>
		</table><br>":"<h3 style='font-size:22px;color:#191970;text-align:center'>$name Awarded Points</h3><br>
		<table cellpadding='5' style='width:100%' class='table-bordered'> 
			$txt <tr><td colspan='2' style='text-align:right'>$btx</td></tr>
			<tr><td>Loan Amounts Taken</td><td style='text-align:right'><b>".fnum($def["amount"])."</b></td></tr>
			<tr><td>Timely Paid Installments</td><td style='text-align:right'><b>".fnum($def["timely"])."</b></td></tr>
			<tr><td>Lately Paid Installments</td><td style='text-align:right'><b>".fnum($def["lately"])."</b></td></tr>
			<tr><td>Loan Cycles Award</td><td style='text-align:right'><b>".fnum($def["cycles"])."</b></td></tr>
			<tr><td>Total Points</td><td style='text-align:right'><b>".fnum($pnts)."</b></td></tr>
			<tr style='background:#f8f8ff'><td>Loan Amount Qualified</td><td style='text-align:right'><b>".fnum($set["points_award"]*$cpnt)."</b></td></tr>
			<tr style='background:#f0f8ff'><td><b>App/USSD Loan Limit</b></td><td style='text-align:right'><b>".fnum(max(loanlimit($idno,"app")))."</b></td></tr>
			<tr><td colspan='2'><b>Restricted Set Amount</b><input type='number' value='$lim' style='float:right;width:90px;padding:3px' 
			onchange=\"setmaxlim($pam,'$idno','$lim',this.value)\" id='mxlim'></td></tr> 
		</table><br>";
		echo "<div style='padding:10px;margin:0 auto;max-width:380px'>$data</div>";
	}
	
	# tag loan
	if(isset($_GET['tagln'])){
		$ln = trim($_GET['tagln']); 
		$me = staffInfo($sid); $lis="";
		$perms = getroles(explode(",",$me['roles']));
		
		$list = array("Good"=>"Good paying client","BLC"=>"Bad Luck Client","BFC"=>"Bad Faith Client","CFC"=>"Control Failure");
		if($me['position']=="super user" or in_array("delete client",$perms)){ $list["Unrated"]="Untag Client"; }
		
		$cfs = array("BLC"=>["Sickness","Fire","Theft","Family emergency","Death"],"BFC"=>["Fraudster","Character issue","Relocation"],
		"CFC"=>["Staff Fraud","Negligence","Incompetence","System failure","Natural Disaster"]);
		foreach($list as $val=>$txt){
			$lis.= "<option value='$val'>$txt</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Tag/Rate Client Loan</h3><br>
			<form method='post' id='tgfom' onsubmit='tagloan(event)'>
				<input type='hidden' name='tgloan' value='$ln'>
				<p>Your Rating<br><select style='width:100%' id='ltp' name='ltag' onchange=\"tagoption(this.value)\">$lis</select></p>
				<p id='tagc'>Reason for your Choise<br><textarea name='tresn' class='mssg' required></textarea></p><br>
				<p style='text-align:right'><button class='btnn'>Tag Now</button></p>
			</form><br>
		</div> <script> var tags = ".json_encode($cfs,1)."; </script>";
	}
	
	# import errors
	if(isset($_GET['xlserrors'])){
		$data = (file_exists("dbsave/import_errors.dll")) ? json_decode(file_get_contents("dbsave/import_errors.dll"),1):[];
		$titles = array("phonefake"=>"Invalid Contacts","invalid"=>"Unknown Loan Officers ID Numbers","exists"=>"Existing Clients",
		"phones"=>"Existing Contacts","incomplete"=>"Incomplete Records");
		
		$lis = "";
		foreach($data as $key=>$idnos){
			$ttl = $titles[$key]; $all=count($idnos);
			$lis.="<h3 style='font-size:18px;background:#f0f0f0;color:#4682b4;padding:7px;margin-bottom:10px'>$ttl ($all)</h3>
			<p>".implode(", ",$idnos)."</p>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:450px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Found Errors</h3><br>$lis <br>
		</div>";
	}

?>
	
<script>
	
	function transwallet(wid,amt){
		if(amt==0){ toast("Insufficient Balance!"); }
		else{
			var amnt = prompt("Enter Amount to Transfer to Transactional Account",amt);
			if(amnt){
				if(confirm("Sure to transfer Ksh "+amnt+" to Transactional Account?")){
					$.ajax({
						method:"post",url:path()+"dbsave/payments.php",data:{transwid:wid,transfro:"investbal",transto:"balance",tamnt:amnt},
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
		}
	}
	
	function writeoff(lid){
		if(confirm("Request Principle write-off for loan?")){
			var resn = prompt("Enter the reason for Write-Off");
			if(resn){
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:{writeoff:lid,wresn:resn},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						closepop(); toast("Request submited, waiting for approvals"); 
					}
					else{ alert(res); }
				});
			}
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
	
	function releasepend(idno){
		if(confirm("Proceed to Release pended loan for approval?")){
			var resn = prompt("Type your comments","Proceed with approval");
			if(resn){
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:{pendrel:idno,icom:resn},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Released successfully!"); window.location.reload(); 
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function overdlim(wid,amnt){
		var lim = prompt("Set Account Overdraft Limit",amnt);
		if(lim){
			if(confirm("Proceed to update Overdraft limit from "+amnt+" to "+lim+"?")){
				$.ajax({
					method:"post",url:path()+"dbsave/clients.php",data:{setoverdlim:wid,limamnt:lim},
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
	}
	
	function setmaxlim(perm,idno,prev,val){
		if(perm!=1){ alert("Permission Denied!"); $("#mxlim").val(prev); }
		else{
			if(parseInt(val)<1 || val==""){ $("#mxlim").focus(); toast("Value should be >0"); }
			else{
				if(confirm("Proceed to update Restricted Loan limit from "+prev+" to "+val+"?")){
					$.ajax({
						method:"post",url:path()+"dbsave/clients.php",data:{setmaxlim:idno,sval:val},
						beforeSend:function(){ progress("Processing...please wait"); },
						complete:function(){progress();}
					}).fail(function(){
						toast("Failed: Check internet Connection");
					}).done(function(res){
						if(res.trim()=="success"){ closepop(); }
						else{ alert(res); }
					});
				}
				else{ $("#mxlim").val(prev); }
			}
		}
	}
	
	function checkall(){
		var cond=true;
		$(".cdiv").find("input[type=checkbox]:checked").each(function (){ cond=false; });
		var txt=(cond) ? "Unselect All":"Select All";
		$(".cdiv").find("input[type=checkbox]").each(function (){
			this.checked = cond;
		});
		$("#scont").html(txt);
	}
	
	function tagoption(val){
		if(val=="Good" || val=="Unrated"){ $("#tagc").html("Reason for your Choise<br><textarea name='tresn' class='mssg' required></textarea>"); }
		else{
			var opts ="";
			for(var i=0; i<tags[val].length; i++){ opts+= "<option value='"+tags[val][i]+"'>"+tags[val][i]+"</option>"; }
			$("#tagc").html("Select Reason<br><select style='width:100%' name='tresn' required>"+opts+"</select>");
		}
	}

	function firesrc(e,str){
		var keycode=e.charCode? e.charCode : e.keyCode;
		if(keycode==13){ searchcl(str); }
	}
	
	function setcols(){
		if(checkboxes("cols[]")){
			var arr = [], boxes = document.querySelectorAll('input[type=checkbox]:checked');
			for (var i = 0; i < boxes.length; i++) { arr.push(boxes[i].value); }
			var json = JSON.stringify(arr); 
			createcookie("tfds",json,1); closepop(); 
			setTimeout(function (){ window.location.reload(); },300);
		}
		else{ toast("Select atleast 1 Field"); }
	}
		
	function searchcl(str){
		if(str.length>1){
			popupload("clients.php?clientstr="+str.split(" ").join("+")); 
		}
	}
	
	function importclients(e){
		e.preventDefault();
		var xls = _("cxls").files[0];
		if(xls!=null){
			if(confirm("Extract Clients from selected File?")){
				var data=new FormData(_("cform"));
				data.append("file",xls);
				var x=new XMLHttpRequest(); progress("Processing...please wait");
				x.onreadystatechange=function(){
					if(x.status==200 && x.readyState==4){
						var res=x.responseText; progress(); 
						if(res.trim().split(":")[0]=="success"){
							var fx = res.trim().split(":")[1],all=res.trim().split(":")[2];
							if(fx=="all"){ toast(all+" Clients extracted and saved successfull!"); closepop(); loadpage("clients.php?manage"); }
							else if(fx=="none"){ alert("None of the clients Extracted! Correct errors found"); popupload("clients.php?xlserrors"); }
							else{ alert(fx+"/"+all+" records extracted! Correct errors found"); popupload("clients.php?xlserrors"); }
						}
						else{ alert(res.trim()); }
					}
				}
				x.open("post",path()+"dbsave/import_clients.php",true);
				x.send(data);
			}
		}
		else{
			alert("Please select Excel File first");
		}
	}
	
	function getclients(id){
		var pg=_("pageno").value.trim();
		$.ajax({
			method:"post",url:path()+"clients.php?sid=<?php echo $sid; ?>",data:{pageno:pg,grup:_("grup").value.trim()},
			beforeSend:function(){progress("Fetching...please wait");},
			complete:function(){progress();}
		}).fail(function(){
			toast("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim().split("~")[0]=="data"){
				$("#"+id).remove();
				_("pageno").value=res.trim().split("~")[1];
				$(".cdiv").append(res.trim().split("~")[2]);
				$(".pageload").html(res.trim().split("~")[3]);
			}
			else{
				toast(res);
			}
		});
	}
	
	function savegroup(dt){
		if(_("gid").value.trim()==""){ _("gid").focus(); toast("Type group name first"); }
		else{
			var tot=$(".udiv .ccont:checked").length; 
			if(tot==0){
				alert("No clients selected! Add atleast one Client");
			}
			else{
				tot = (_("totals").value==0) ? tot:_("totals").value;
				var txt=(tot==1) ? "1 Client":tot+" Clients";
				var ttl=(dt>0) ? "Add "+txt+" to client group?":"Create client group with "+txt+"?";
				
				if(confirm(ttl)){
					var data=$("#gfom").serialize(); data+="&gname="+encodeURIComponent(_("gid").value.trim());
					$.ajax({
						method:"post",url:path()+"dbsave/clients.php",data:data,
						beforeSend:function(){progress("Procesing...please wait");},
						complete:function(){progress(); }
					}).fail(function(){
						alert("Error while processing your request");
					}).done(function(res){
						if(res.trim().split(":")[0]=="success"){
							loadpage("clients.php?groups="+res.trim().split(":")[1]); 
						}
						else{ alert(res.trim()); }
					});
				}
			}
		}
	}
		
	function selectconts(val){
		var res = "",tot=0;
		$(".cdiv").find("input[type=checkbox]:checked").each(function (){
			res+="<li style='margin:5px 0px;color:#2f4f4f;list-style:none'>"+$("#"+this.value).html()+"</li>"; tot++;
		});
			
		if(res==""){ alert("No Client selected! Select at least one Client!"); }
		else{
			closepop(); 
			if(val>0 && _("totals").value==0){
				$(".udiv").append(res); var all=0;
				$(".udiv").find("input[type=checkbox]").each(function (){ all++; });
				$(".tots").html("("+all+")");
			}
			else{ 
				$(".udiv").html(res); $("#totals").val("0"); $(".tots").html("("+tot+")");
			}
				
			$(".udiv").find("input[type=checkbox]").each(function (){
				this.checked = true; 
			});
		}
	}
		
	function waiveint(lid){
		var intr = prompt("Enter Total Interest charged for the Loan");
		if(intr){
			if(confirm("Confirm change of Loan Interest to "+intr+"?")){
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:{waiveintr:lid,nintr:intr},
					beforeSend:function(){ closepop(); progress("Applying...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						alert("Updated successfully!"); window.location.reload();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function autodisburse(idno,val){
		var txt = (val==1) ? "Proceed to whitelist client for auto disbursement via App?":"Disable auto disbursement for client?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:{autodisburse:idno,autoval:val},
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ loadpage("clients.php?showclient="+idno); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function deactivateln(idno,lid,st){
		var txt = (st>0) ? "Proceed to Activate Loan?":"Sure to Deactivate the Loan?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{deactln:lid,lnst:st},
				beforeSend:function(){progress("Procesing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("clients.php?showclient="+idno); }
				else{ alert(res); }
			});
		}
	}
	
	function resetpin(idno){
		if(confirm("Proceed to reset Client App Login PIN?")){
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:{resetpin:idno},
				beforeSend:function(){progress("Resetting...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ loadpage("clients.php?showclient="+idno); }
				else{ alert(res); }
			});
		}
	}
	
	function delcgroup(tp,id){	
		var txt = (tp=="user") ? "Remove client from the group?":"Sure to delete client group?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:{delcgroup:id,deltp:tp},
				beforeSend:function(){progress("Removing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					if(tp=="user"){ $("#"+id).remove(); }
					else{ window.history.back(); toast("Removed successfully"); }
				}
				else{ alert(res); }
			});
		}
	}
	
	function editgroup(gid){
		var ng = prompt("Enter New Group Name");
		if(ng){
			if(confirm("Update client group name?")){
				$.ajax({
					method:"post",url:path()+"dbsave/clients.php",data:{editgroup:ng,pgid:gid},
					beforeSend:function(){progress("Updating...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Updated!"); loadpage("clients.php?groups="+gid); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function editpenalty(lid,val){
		var nw = prompt("Enter New Penalty amount",val);
		if(nw){
			if(confirm("Update client Penalty amount?")){
				$.ajax({
					method:"post",url:path()+"dbsave/loans.php",data:{cpenalty:nw,clid:lid},
					beforeSend:function(){progress("Updating...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){ toast("Updated!"); $(".penam").html(res.trim().split(":")[1]); }
					else{ alert(res); }
				});
			}
		}
	}
	
	function tagloan(e){
		e.preventDefault();
		if(confirm("Proceed to tag client Loan as "+$("#ltp").val()+"?")){
			var data = $("#tgfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); window.location.reload(); }
				else{ alert(res); }
			});
		}
	}
	
	function sendsms(e){
		e.preventDefault();
		if(confirm("Proceed to create SMS queue?")){
			var data = $("#smfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/sendsms.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); alert(res); }
				else{ alert(res); }
			});
		}
	}
	
	function savetransfered(e){
		e.preventDefault();
		if(confirm("Sure to Transfer checked Clients?")){
			var data=$("#tfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:data,
				beforeSend:function(){progress("Transfering...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res);
				if(res.trim()=="success"){ closepop(); toast("Transfer completed!"); }
				else{ alert(res); }
			});
		}
	}
	
	function transferclients(e){
		e.preventDefault();
		if(_("tfro").value==0){ alert("Please Select Loan Officer to Transfer Clients from"); }
		else if(_("tto").value==0){ alert("Please Select Officer to Transfer Clients To"); }
		else{
			var dorm = (checkboxes("dormtoo")) ? 1:0;
			popupload("clients.php?transfrom="+_("tfro").value+"&transto="+_("tto").value+"&dorm="+dorm);
		}
	}
	
	function suspendclient(idno,val){
		var txt = (val) ? "Suspend":"Activate";
		if(confirm("Sure to "+txt+" client?")){
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:{suspend:idno,sval:val},
				beforeSend:function(){ progress("Procesing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); 
				if(res.trim()=="success"){ loadpage("clients.php?showclient="+idno); }
			});
		}
	}
	
	function deleteloan(lid,idno){
		if(confirm("Sure to permanently delete running loan?")){
			$.ajax({
				method:"post",url:path()+"dbsave/loans.php",data:{deloan:lid},
				beforeSend:function(){ closepop(); progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); if(res.trim()=="success"){ loadpage("clients.php?showclient="+idno); }
			});
		}
	}
	
	function delclient(idno){
		if(confirm("Sure to permanently delete client?")){
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:{delclient:idno},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); if(res.trim()=="success"){ window.history.back(); }
			});
		}
	}
	
	function uploadmed(e,idno){
		e.preventDefault();
		if(confirm("Proceed to Upload selected document?")){
			var data = new FormData(_("dform"));
			data.append("cdoc",_("cdoc").files[0]);
			var xhr = new XMLHttpRequest();
			xhr.upload.addEventListener("progress",uploadprogress,false);
			xhr.addEventListener("load",uploaddone,false);
			xhr.addEventListener("error",uploaderror,false);
			xhr.addEventListener("abort",uploadabort,false);
			xhr.onload=function(){
				if(this.responseText.trim().split(":")[0]=="success"){
					toast("Success"); closepop(); fetchpage("clients.php?showclient="+idno+"&vtp=cdocs");
				}
				else{ alert(this.responseText); }
			}
			xhr.open("post",path()+"dbsave/clients.php",true);
			xhr.send(data);
		}
	}

	function saveclient(e,idno,vtp){
		e.preventDefault();
		if(confirm("Save client personal info?")){
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
					if(this.responseText.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); 
						if(idno>0){ loadpage("clients.php?showclient="+this.responseText.trim().split(":")[1]); }
						else{ loadpage("clients.php?manage="+vtp); }
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/clients.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#sform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/clients.php",data:data,
					beforeSend:function(){ progress("Procesing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); 
						if(idno>0){ loadpage("clients.php?showclient="+res.trim().split(":")[1]); }
						else{ loadpage("clients.php?manage="+vtp); }
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function savelead(e){
		e.preventDefault();
		if(confirm("Proceed to create client Lead?")){
			var tp = _("hasfiles").value.trim();
			if(tp.length>2){
				var data = new FormData(_("lform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					data.append(files[i],_(files[i]).files[0]);
				}
				
				var xhr=new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload=function(){
					if(this.responseText.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); loadpage("clients.php?vleads");
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/clients.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#lform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/clients.php",data:data,
					beforeSend:function(){ progress("Procesing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); loadpage("clients.php?vleads");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function postlead(e,id,ltp){
		e.preventDefault();
		if(confirm("Post Client Interaction?")){
			var tp = _("hasfiles").value.trim();
			if(tp.length>2){
				var data = new FormData(_("iform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					data.append(files[i],_(files[i]).files[0]);
				}
				
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload=function(){
					if(this.responseText.trim()=="success"){
						toast("Success"); closepop(); 
						if(ltp>0){ window.location.reload(); }
						else{ loadpage("clients.php?vintrcn="+id); }
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/clients.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#iform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/clients.php",data:data,
					beforeSend:function(){ progress("Posting...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success"); closepop(); 
						if(ltp>0){ window.location.reload(); }
						else{ loadpage("clients.php?vintrcn="+id); }
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function saveintrcn(e,uid){
		e.preventDefault();
		if(confirm("Post Lead interaction?")){
			var data = $("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/clients.php",data:data,
				beforeSend:function(){ progress("Posting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success"); closepop(); loadpage("clients.php?vcoms="+uid);
				}
				else{ alert(res); }
			});
		}
	}

</script>