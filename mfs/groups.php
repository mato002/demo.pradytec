<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	
	//show group
	if(isset($_GET['vgroup'])){
		$gid = trim($_GET['vgroup']);
		$tbl = "cgroups$cid"; $sett=[];
		$exclude = array("id","def","time","status","group_name"); 
	
		$res = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$gid'"); $cinfo=($res) ? $res[0]:[];
		if(count($cinfo)<1){
			echo "<script> window.history.back(); </script>"; exit();
		}
		
		$cdef = json_decode($cinfo["def"],1); $gset=groupSetting($gid); $me=staffInfo($sid);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		$maxet = (isset($gset["maxmembers"])) ? $gset["maxmembers"]:time();
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'");
		$qry = $db->query(2,"SELECT COUNT(*) AS tots FROM `org$cid"."_clients` WHERE `client_group`='$gid'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; $tcls=intval($qry[0]['tots']); $doc="";
		$gttl = (defined("SAVINGS_TERMS")) ? SAVINGS_TERMS["grp"]:"SAVINGS";
		
		foreach(["savings"=>"$gttl (KSH)","setting"=>"GROUP SETTINGS"] as $set=>$title){
			if($set=="savings"){
				$val = groupSavings($gid);
				$doc.= "<div class='col-12 col-sm-auto col-md-auto col-lg-4 col-xl-auto mt-2 mb-2'>
					<div style='padding:7px;min-height:84px;min-width:190px;background:#154eb7;color:#7FFF00;font-family:signika negative'>
						<p style='color:#fff;font-size:13px;margin-bottom:10px;'>$title</p>
						<h3 style='font-size:23px;'><i class='bi-wallet'></i> ".fnum($val)."</h3>
					</div>
				</div>";
			}
			elseif($set=="setting"){
				$maxm = (strlen($maxet)>8) ? "Unlimited":fnum($maxet);
				$clickset = (in_array("configure system",$perms)) ? "popupload('setup.php?setgrps=$gid')":"popupload('setup.php?setgrps=$gid&noset')";
				
				$doc.= "<div class='col-12 col-sm-auto col-md-auto col-lg-4 col-xl-auto mt-2 mb-2'>
					<div style='padding:7px;min-height:80px;min-width:190px;background:#f8f8f8;color:#191970;font-family:signika negative;border:1px solid #ccc'>
						<p style='color:#154eb7;font-size:13px;margin-bottom:3px;'><i class='fa fa-cog'></i> $title</p>
						<table style='width:100%;font-size:14px' cellpadding='3'><tr>
							<td style='font-family:helvetica;font-size:13px;'><b>$tcls</b> of <b>$maxm</b><br><span style='color:#2F4F4F'>Members</span></td>
							<td><button class='bts' onclick=\"$clickset\" style='color:#154eb7;font-size:14px;border:1px solid #154eb7;
							outline:none;padding:4px 8px;border-radius:30px;background:#F0F8FF;float:right'><i class='bi-box-arrow-up-right'></i> Set</button></td>
						</tr></table>
					</div>
				</div>";
			}
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
						<img src='$img' style='height:60px;cursor:pointer;max-width:100%' onload=\"rempic('$df')\" onclick=\"popupload('media.php?doc=img:$df&tbl=$tbl:$col&fd=id:$gid')\"><br>
						<span style='font-size:13px'>".str_replace("_"," ",ucfirst($col))."</span>
					</div></div>";
				}
				if($dtp=="pdf" or $dtp=="docx"){
					$df = $cinfo[$col]; unset($ftc[$col]); 
					$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
					$doc .= "<div class='col-auto col-sm-auto col-md-auto col-lg-auto col-xl-auto mt-2'>
					<div style='min-width:100px;text-align:center;padding:0px 5px'>
						<img src='$img' style='height:68px;cursor:pointer' onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$tbl:$col&fd=id:$gid')\"><br>
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
		
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		foreach($qri as $row){
			$prods[$row['id']]=prepare(ucwords($row['product']));
		}
		
		$stbl="org$cid"."_staff"; $staff[]="None"; $lcl=$no=0;
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$trs=$ltrs=""; $all[]="None"; $tdy=strtotime(date("Y-M-d")); $vpn=0; $tlns=0; $gst=$cinfo['status']; 
		$wtbl = ($db->istable(3,"wallets$cid")) ? 1:0; $leaders=array("group_leader","secretary","treasurer");
		$states = array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",
		1=>"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>",
		3=>"<span style='color:#2F4F4F;'><i class='fa fa-circle'></i> UnApproved</span>",4=>"<span style='color:#1E90FF;'><i class='fa fa-circle'></i> OnSignup</span>");
		
		$res = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `client_group`='$gid' ORDER BY `name` ASC");
		if($res){
			foreach($res as $key=>$row){
				$id=$row['id']; $name=prepare(ucwords($row["name"])); $idno=$row["idno"]; $wbal=$lnbal=0; $mtp="Member"; $cst=$row["status"];
				$chk1 = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (balance+penalty)>0 ORDER BY `disbursement` ASC");
				if($wtbl){
					$chk2 = $db->query(3,"SELECT `savings` FROM `wallets$cid` WHERE `client`='$id' AND `type`='client'");
					$wbal = ($chk2) ? $chk2[0]["savings"]:0;
				}
			
				$fon=$row["contact"]; $sbal=fnum($wbal); $all[$id]=$name; $tlns++; $no++; $tls="";
				foreach($leaders as $ld){ if($cinfo[$ld]==$id){ $mtp=ucwords(str_replace("_"," ",$ld)); break; }}
				if($chk1){
					foreach($chk1 as $rw){
						$pid=$rw["loan_product"]; $bal=fnum($rw["balance"]+$rw["penalty"]); $lid=$rw["loan"]; $expd=$rw["expiry"];
						$tcss = "text-align:center;border-bottom:1px solid #F2F2F2;";
						if($tdy>$expd){ $lntd = "<td style='padding:5px;font-size:13px;background:#D2691E;color:#fff;$tcss'>Overdue</td>"; }
						elseif($expd==$tdy){ $lntd = "<td style='padding:5px;font-size:13px;background:#556B2F;color:#fff;width:100px;$tcss'>Maturing Today</td>"; }
						elseif($expd==($tdy+86400)){ $lntd = "<td style='padding:5px;font-size:13px;background:#483D8B;color:#fff;width:100px;$tcss'>Maturing Tmorow</td>"; }
						else{
							$chk = $db->query(2,"SELECT `day` FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `balance`>0 AND `day`<=".($tdy+86400));
							if($chk){
								foreach($chk as $roq){
									if($roq["day"]<$tdy){ $lntd="<td style='padding:5px;font-size:13px;background:orange;color:#fff;$tcss'>In Arrears</td>"; break; }
									elseif($roq["day"]==$tdy){ $lntd="<td style='width:100px;padding:5px;font-size:13px;background:#008B8B;color:#fff;$tcss'>InDues Today</td>"; break; }
									else{ $lntd="<td style='width:90px;padding:5px;font-size:13px;background:#483D8B;color:#fff;$tcss'>InDues Tmorow</td>"; break; }
								}
							}
							else{ $lntd = "<td style='padding:5px;font-size:13px;background:green;color:#fff;$tcss'>Active</td>"; }
						}
						
						$tls.= (count($chk1)>1) ? "<tr style='background:transparent'><td><li>$prods[$pid]</li></td><td><b>$bal</b></td>$lntd</tr>":"<tr style='background:transparent'>
						<td><b>$bal</b></td>$lntd</tr>";
					}
					$lnbal = "<table style='font-size:13px;min-width:20px' cellpadding='3'>$tls</table>";
				}
				
				$view = (in_array("view clients",$perms)) ? "<a href='javascript:void(0)' onclick=\"loadpage('clients.php?wallet=$id&vtp=savings')\">View</a>":"";
				$act = (in_array("remove member from group",$perms)) ? "<i class='fa fa-retweet' title='Reshuffle $name' onclick=\"popupload('groups.php?reshuffle=$gid:$id')\"
				style='font-size:23px;cursor:pointer;color:#008080'></i> <i class='fa fa-user-times' title='Remove $name from Group' onclick=\"rmclient('$gid:$id')\"
				style='font-size:23px;cursor:pointer;color:#8B4513;margin-left:10px'></i>":"";
				
				$ltrs.= "<tr valign='top'><td>$no</td><td><i class='bi-box-arrow-up-right' style='cursor:pointer;color:#008fff;font-size:17px' title='View $name Acccount'
				onclick=\"loadpage('clients.php?showclient=$idno')\"></i> $name</td><td>0$fon</td><td>$states[$cst]</td><td>$mtp</td><td>$sbal $view</td><td>$lnbal</td><td>$act</td></tr>";
			}
		}
		
		$row = $cinfo;
		foreach($ftc as $col=>$dtp){
			if(!in_array($col,$exclude)){
				$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
				$val = (in_array($col,["loan_officer","creator"])) ? $staff[$row[$col]]:$val;
				$val = (in_array($col,["group_leader","treasurer","secretary"])) ? $all[$row[$col]]:$val;
				$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
				$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
				$trs.= "<tr valign='top'><td style='color:#191970'><b>".ucfirst(str_replace("_"," ",$col))."</b></td><td>$val</td></tr>";
			}
		}
		
		$cname = prepare(ucwords($cinfo['group_name'])); 
		$mxh = (trim($_GET['md'])>600) ? "500px":""; $hcs=($mxh) ? "margin-bottom:5px":"";
		$edit = (in_array("edit loan group",$perms)) ? "<button class='bts' style='padding:3px;font-size:15px' onclick=\"popupload('groups.php?create=$gid')\">
		<i class='fa fa-pencil'></i> Edit Info</button>":"";
		$del = ($tlns<1  && in_array("delete loan group",$perms)) ? "<button class='bts' style='font-size:15px;margin-left:5px;padding:3px;color:#ff4500' 
		onclick=\"delgroup('$gid')\"><i class='bi-person-x'></i> Delete</button>":"";
		$add = (in_array("manage loan group membership",$perms) && $tlns<$maxet) ? "<button class='bts' style='padding:2px 4px;float:right' onclick=\"popupload('groups.php?addcls=$gid')\">
		<i class='fa fa-user-plus'></i> Add Client</button>":"";
		
		$trs.= ($edit or $del) ? "<tr><td colspan='2' style='text-align:right'><hr><p style='margin-bottom:10px'>$edit $del</p></td></tr>":"";
		echo "<div class='container cardv' style='max-width:1400px;background:#fff;overflow:auto;padding:10px 0px'>
			<div class='row' style='margin:0px'>
				<div class='col-12 mb-1'>
					<h3 style='color:#191970;font-size:20px'>$backbtn $cname</h3><hr style='$hcs'> 
					<div class='row'>$doc</div>
				</div>
				<div class='col-12 col-md-12 col-lg-4 col-xl-3 mb-2'>
					<p style='padding:6px;background:#f0f0f0;color:#191970;font-weight:bold;text-align:center;margin-bottom:0px;border:1px solid #dcdcdc'>Group Details
					<i class='fa fa-refresh' style='float:right;cursor:pointer' onclick=\"loadpage('groups.php?vgroup=$gid')\"></i></p>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<table cellpadding='6' style='width:100%;border:1px solid #dcdcdc;background:;border-top:0px;font-size:14px'>$trs</table>
					</div>
				</div>
				<div class='col-12 col-md-12 col-lg-8 col-xl-9'>
					<p style='padding:6px;background:#f0f0f0;color:#191970;font-weight:bold;text-align:;margin-bottom:0px;border:1px solid #dcdcdc'>Group Members $add</p>
					<div style='overflow:auto;width:100%;max-height:$mxh;'>
						<table cellpadding='5' style='width:100%;min-width:750px;font-size:14px;' class='table-striped'>
							<tr style='color:#191970;font-weight:bold;font-size:14px;background:#e6e6fa;'><td colspan='2'>Client Name</td><td>Contact</td><td>Status</td><td>Position</td>
							<td>".ucwords(strtolower($gttl))."</td><td>Loan Balance</td><td></td></tr> $ltrs
						</table><br>
					</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed details for client group $cname");
	}
	
	# add clients to group
	if(isset($_GET["addcls"])){
		$gid = trim($_GET["addcls"]); $opts="";
		$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
		$row = $sql[0]; $gname=prepare(ucfirst($row["group_name"])); $bid=$row["branch"]; $lof=$row["loan_officer"];
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `branch`='$bid' AND `client_group`='0' AND `status`<2");
		if($qri){
			foreach($qri as $row){
				$idno=$row["idno"]; $name=prepare(ucwords($row["name"])); 
				$opts.= "<option value='$idno'>$name</option>";
			}
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:330px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Add Clients to $gname</h3><br>
			<form method='post' id='aform' onsubmit=\"addgclients(event,'$gid')\">
				<input type='hidden' name='gaddto' value='$gid'> <datalist id='ins'>$opts</datalist>
				<table cellpadding='5' style='width:100%;' class='tbl'>
					<caption style='text-align:right'><a href='javascript:void(0)' onclick='addrow()'><i class='fa fa-plus'></i> Add Row</a></caption>
					<tr><td colspan='2'>Client Idno<br><input type='text' list='ins' name='idnos[]' style='width:100%' autocomplete='off' required></td></tr>
				</table><hr>
				<p style='text-align:right'><button class='btnn'>Proceed</button></p><br>
			</form>
		</div>";
	}
	
	# reshuffle Client
	if(isset($_GET["reshuffle"])){
		$src = explode(":",trim($_GET["reshuffle"]));
		$gid = $src[0]; $uid=$src[1]; $opts="";
		
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$uid'");
		$gset = groupSetting(); $bid=$sql[0]['branch']; $cname=prepare(ucwords($sql[0]["name"]));
		
		$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `status`<2 AND `branch`='$bid' AND NOT `id`='$gid'");
		if($sql){
			foreach($sql as $row){
				$id=$row["id"]; $name=prepare(ucfirst($row["group_name"])); $gdf=json_decode($row["def"],1);
				$set = (isset($gdf["sets"])) ? $gdf["sets"]:$gset; 
				if($set){
					$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$id'");
					if(intval($chk[0]["tot"])<=$set["maxmembers"]){ $opts.="<option value='$id'>$name</option>"; }
				}
				else{ $opts.= "<option value='$id'>$name</option>"; }
			}
		}
		
		$act = (!$opts) ? "disabled style='cursor:not-allowed'":"onclick=\"shufflegrp('$gid:$uid')\"";
		$opts = ($opts) ? $opts:"<option value='0'>No Free Group found</option>";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Shuffle Client Group</h3><br>
			<p>Select Group to Move <b>$cname</b> to</p><p><select style='width:100%' id='mgid' style='width:100%'>$opts</Select><br>
			<span style='color:grey;font-size:14px'><b>Note:</b> Moving client will also shift Loan officer to the one managing the new group</span></p><br>
			<p style='text-align:right'><button class='btnn' $act>Shuffle</button></p><br>
		</div>";
	}
	
	# group loans
	if(isset($_GET["loans"])){
		$view = trim($_GET['loans']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean(strtolower($_GET['str'])),"0"):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 20; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$load = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "`loan_officer`='$sid'":$load;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? setRegion($cnf["region"]):$cond;
		$cond.= ($stid) ? " AND `loan_officer`='$stid'":"";
		$cond.= ($str) ? " AND (`group_name` LIKE '%$str%' OR `group_id`='$str')":"";
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff[]="None";
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$tdy = strtotime(date("Y-M-d"));
		$no = ($perpage*$page)-$perpage; $trs=$ths=$offs=$brns="";
		
		if($db->istable(2,"cgroups$cid")){
			$res = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE $cond ORDER BY group_name ASC $lim");
			if($res){
				foreach($res as $rw){
					$rid=$rw["id"]; $name=prepare(ucwords($rw["group_name"])); $gid=$rw["group_id"]; $mday=$rw["meeting_day"]; 
					$bname=$brans[$rw["branch"]]; $lof=$staff[$rw["loan_officer"]]; $tloan=$tbal=$tot=$tarr=0; $no++;
					
					$sql = $db->query(2,"SELECT SUM(ln.amount) AS tloan,SUM(ln.balance+ln.penalty) AS tbal,COUNT(*) AS tot,GROUP_CONCAT(ln.loan) AS lids FROM `org$cid"."_clients` 
					AS cl INNER JOIN `org$cid"."_loans` AS ln ON cl.idno=ln.client_idno WHERE cl.client_group='$rid' AND (ln.balance+ln.penalty)>0");
					if($sql){
						$row = $sql[0]; $tloan=fnum($row["tloan"]); $tbal=fnum($row["tbal"]); $tot=fnum($row["tot"]); $lids=explode(",",$row["lids"]); $ids=[];
						foreach($lids as $id){ $ids[]="'$id'"; }
						$chk = $db->query(2,"SELECT SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan` IN (".implode(",",$ids).") AND `balance`>0 AND `day`<$tdy");
						$tarr = ($chk) ? fnum(intval($chk[0]["tbal"])):0;
					}
					
					$trs.= "<tr onclick=\"loadpage('groups.php?vgroup=$rid')\"><td>$no</td><td>$name</td><td>$gid</td><td>$bname</td><td>$lof</td><td>$mday</td>
					<td>$tot</td><td>$tloan</td><td>$tbal</td><td>$tarr</td></tr>";
				}
			}
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
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('groups.php?loans=$view&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px;' onchange=\"loadpage('groups.php?loans=$view&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT `loan_officer` FROM `cgroups$cid` WHERE $brn");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$stid) ? "selected":"";
					$opts.= ($off) ? "<option value='$off' $cnd>".$staff[$off]."</option>":"";
				}
				
				$offs = "<select style='width:150px;font-size:15px' onchange=\"loadpage('groups.php?loans=$view&reg=$rgn&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `cgroups$cid` WHERE $cond"); 
		$title = ($str) ? "Search Results ":"Group Loans"; $totals=($sql) ? $sql[0]['total']:0;
		$prnt = ($totals) ? genrepDiv("groups.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&v=loans",'right'):"";
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;max-width:1380px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($totals).")</h3><hr>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped stbl' style='width:100%;min-width:650px;font-size:15px;' cellpadding='5'>
    					<caption style='caption-side:top;padding:0px 0px 5px 0px'>$brns $offs
						<input type='search' onkeyup=\"fsearch(event,'groups.php?loans=$view&str='+cleanstr(this.value))\" onsearch=\"loadpage('groups.php?loans=$view&str='+cleanstr(this.value))\" 
						value='".prepare($str)."' style='float:right;width:160px;padding:4px;font-size:15px' placeholder='&#xf002; Search'></caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Group Name</td><td>Group Id</td>
						<td>Branch</td><td>Loan Officer</td><td>Meeting Day</td><td>Active Loans</td><td>Disbursement</td><td>Loan Balances</td><td>Arrears</td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"groups.php?loans=$view&reg=$rgn&bran=$bran&staff=$stid&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed $title Record");
	}
	
	# manage groups
	if(isset($_GET['view'])){
		$view = trim($_GET['view']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean(strtolower($_GET['str'])),"0"):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$stid = (isset($_GET['staff'])) ? clean($_GET['staff']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
		$ctbl = "cgroups$cid";
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 20; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		$exclude = array("id","def","time","group_name","status","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		
		$fields = array_keys($ftc);
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff[]="None";
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
			$skip = array_merge($exclude,array("branch","loan_officer","group_leader","def")); $slis="";
			foreach($fields as $col){
				if(!in_array($col,$skip)){ $slis.="`$col` LIKE '$str%' OR "; }
				if($col=="group_name"){ $slis.="`$col` LIKE '%$str%' OR "; }
			}
			$cond.= " AND (".rtrim(trim($slis),"OR").")";
		}
		
		$no=($perpage*$page)-$perpage; $fro=$no+1; $trs=$ths=$offs=$brns="";
		$rem = array_diff(array_keys($ftc),$exclude);
		
		$cutf = (count($rem)>7) ? array_slice($rem,0,7):$rem;
		$show = (isset($_COOKIE['gcols'])) ? json_decode($_COOKIE['gcols']):$cutf;
		
		if($db->istable(2,$ctbl)){ groupState();
			$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY group_name,status ASC $lim");
			if($res){
				$proc = (in_array("client_group",$db->tableFields(2,"org$cid"."_clients"))) ? 1:0;
				foreach($res as $row){
					$no++; $rid=$row['id']; $tds="";
					$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$rid'");
					$states = array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",
					1=>"<span style='color:green;'><i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>");
					
					foreach($ftc as $col=>$dtp){
						if(in_array($col,$show)){
							$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
							$val = (in_array($col,["loan_officer","creator"])) ? $staff[$row[$col]]:$val;
							$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
							$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
							if(in_array($col,["group_leader","treasurer","secretary"])){
								$val = ($val) ? prepare(ucwords($db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `id`='$val'")[0]['name'])):"None";
							}
							
							$tds.= "<td>$val</td>"; $ths.=($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					$click = ($proc) ? "loadpage('groups.php?vgroup=$rid')":"toast('Error! Your client records structure lacks client group Column')";
					$name = prepare(ucwords($row['group_name'])); $tds.=($view>7) ? "":"<td>".$states[$row['status']]."</td>"; $tot=intval($chk[0]["tot"]);
					$trs.= "<tr onclick=\"$click\"><td>$no</td><td>$name</td><td>$tot</td>$tds</tr>";
				}
			}
		}
		
		if(!$ths){
			foreach($fields as $key=>$col){ 
				if(in_array($col,$show)){ $ths.= "<td>".ucfirst(str_replace("_"," ",$col))."</td>"; }
			}
		}
		
		$grups = "<option value=''>Filter Groups</option>";
		foreach(array("Dormant Groups","Active Groups","Suspended Groups") as $key=>$des){
			$cnd=($view==$key && strlen($view)>0 && !$str) ? "selected":"";
			$grups.= "<option value='$key' $cnd>$des</option>";
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
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('groups.php?view=$view&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px;' onchange=\"loadpage('groups.php?view=$view&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
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
				
				$offs = "<select style='width:150px;font-size:15px' onchange=\"loadpage('groups.php?view=$view&reg=$rgn&bran=$bran&staff='+this.value.trim())\">$opts</select>";
			}
		}
		
		$sql = ($db->istable(2,$ctbl)) ? $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` WHERE $cond"):""; 
		$title = ($str) ? "Search Results ":"Registered Groups"; $totals=($sql) ? $sql[0]['total']:0;
		$title = ($view!=null && $view<7 && !$str) ? $carr[$view]:$title;
		$grps = ($view>7) ? "":"<select style='width:150px;font-size:15px;cursor:pointer' onchange=\"loadpage('groups.php?view='+cleanstr(this.value))\">$grups</select>";
		
		$ths.= ($view>7) ? "":"<td>Status</td>";
		$vcol = (count($rem)>7) ? "<i class='bi-sort-down-alt' style='float:right;margin-left:6px;cursor:pointer;font-size:32px;margin-top:-8px' title='Sort Columns'
		onclick=\"popupload('groups.php?sortcols')\"></i>":"";
		$prnt = ($totals) ? genrepDiv("groups.php?src=".base64_encode($cond)."&br=$bran&stid=$stid&v=$view",'right'):"";
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;max-width:1380px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($totals).")
				<input type='search' onkeyup=\"fsearch(event,'groups.php?view=$view&str='+cleanstr(this.value))\" onsearch=\"loadpage('groups.php?view=$view&str='+cleanstr(this.value))\" 
				value='".prepare($str)."' style='float:right;width:180px;padding:5px;font-size:15px' placeholder='&#xf002; Search'></h3><hr>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped stbl' style='width:100%;min-width:650px;font-size:15px;' cellpadding='5'>
    					<caption style='caption-side:top;padding:0px 0px 5px 0px'>$grps $brns $offs $vcol $prnt</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Group Name</td><td>Members</td>$ths</tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"groups.php?view=$view&reg=$rgn&bran=$bran&staff=$stid&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed $title Record");
	}
	
	# sort cols
	if(isset($_GET['sortcols'])){
		$ctbl = "cgroups$cid";
		$exclude = array("id","def","time","group_name","status","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		
		$rem = array_diff(array_keys($ftc),$exclude);
		$cutf = (count($rem)>7) ? array_slice($rem,0,7):$rem; 
		$show = (isset($_COOKIE['gcols'])) ? json_decode($_COOKIE['gcols']):$cutf;
		
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
			<h3 style='font-size:23px;text-align:;'>Filter Group Fields</h3><hr>
			<table cellpadding='7' style='width:100%'> $trs </table><hr>
			<p style='text-align:right'><button class='btnn' onclick='setcols()'>View Selected</button></p><br>
		</div>";
	}
	
	# create group
	if(isset($_GET['create'])){
		$gid = intval($_GET['create']);
		$gtbl = "cgroups$cid";
		$me = staffInfo($sid); 
		$cnf = json_decode($me["config"],1);
		$perms = getroles(explode(",",$me['roles']));
		
		$exclude = array("id","def","status","time","branch","creator","group_id");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$gtbl'");
		$def = array("id"=>"number","group_id"=>"text","group_name"=>"text","group_leader"=>"number","treasurer"=>"number","secretary"=>"number","meeting_day"=>"text","branch"=>"number",
		"loan_officer"=>"number","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; 
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		$cproc = (in_array("client_group",$db->tableFields(2,"org$cid"."_clients"))) ? 1:0;
		
		$defv = array("def"=>"[]","status"=>0,"time"=>time(),"creator"=>$sid,"group_id"=>rand(123456,999999));
		foreach(array_keys($fields) as $fld){
			$dvals[$fld] = (isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='officers'"); 
		$dedicated = ($res) ? $res[0]['value']:"all";
		
		if($gid){
			$row = $db->query(2,"SELECT *FROM `$gtbl` WHERE `id`='$gid'"); $dvals=array_map("prepare",$row[0]);
			if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
				foreach($fields as $col=>$dtp){ 
					if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($fields[$col]); }
				}
			}
		}
		
		$lis=$infs=""; $ftps=[]; $cols=$fields;
		foreach($cols as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname = ucwords(str_replace("_"," ",$field));
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
				elseif(in_array($field,["group_leader","secretary","treasurer"])){
					$opts = "<option value='0'>-- Select --</option>";
					if($gid && $cproc){
						$lst = array("group_leader"=>$dvals["group_leader"],"secretary"=>$dvals["secretary"],"treasurer"=>$dvals["treasurer"]); unset($lst[$field]);
						$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `client_group`='$gid' ORDER BY `name` ASC");
						if($sql){
							foreach($sql as $row){
								$uid = $row["id"]; $name=prepare(ucwords($row["name"])); $cnd=($uid==$dvals[$field]) ? "selected":"";
								$opts.= (!in_array($uid,$lst)) ? "<option value='$uid' $cnd>$name</option>":"";
							}
						}
					}
					
					$lis.= (in_array("manage group leadership",$perms)) ? "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>":
					"<input type='hidden' name='$field' value='".$dvals[$field]."'>";
				}
				elseif($field=="meeting_day"){
					$lst = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"); $opts="";
					foreach($lst as $dy){
						$cnd = ($dy==$dvals[$field]) ? "selected":"";
						$opts.= "<option value='$dy' $cnd>$dy</option>";
					}
					$lis.= "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($field=="branch"){
					$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'"); $opts="";
					foreach($res as $row){
						$bid=$row['id']; $cnd = ($bid==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$bid' $cnd>".prepare(ucwords($row['branch']))."</option>";
					}
					$opts=($opts=="") ? "<option value='0'>Head Office</option>":$opts;
					$lis.="<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
					}
					$lis.= "<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$val' $cnd></option>";
					}
					$ran = rand(12345678,87654321);
					$lis.= "<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
				}
				elseif($dtype=="textarea"){
					$lis.= "<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{
					$inp = (array_key_exists($dtype,$accept))? "file":$dtype; 
					$val = prepare(ucfirst($dvals[$field])); $add=($inp=="file") ? $accept[$dtype]:""; 
					if($inp=="file"){ $infs.="$field:"; $ftps[$field]=$dtype; }
					$lis.= "<p>$fname<br><input type='$inp' style='width:100%' value=\"$val\" accept='$add' id='$field' name='$field' required></p>";
				}
			}
			else{ $lis.= "<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$title = ($gid) ? "Edit Client Group":"Create Client Group"; 
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='gform' onsubmit=\"savegoup(event,'$gid')\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($cols),1)."'>
				<input type='hidden' name='id' value='$gid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
	}


	@ob_end_flush();
?>
<script>
	
	function addgclients(e,gid){
		e.preventDefault();
		if(confirm("Add client(s) to group?")){
			var data = $("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/groups.php",data:data,
				beforeSend:function(){ progress("Adding...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Added Successfully!"); closepop(); fetchpage("groups.php?vgroup="+gid);
				}
				else{ alert(res); }
			});
		}
	}
	
	function rmclient(src){
		if(confirm("Sure to remove client from group?")){
			$.ajax({
				method:"post",url:path()+"dbsave/groups.php",data:{rmgclient:src},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Removed Successfully!"); fetchpage("groups.php?vgroup="+src.split(":")[0]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function delgroup(gid){
		if(confirm("Sure to delete client group?")){
			$.ajax({
				method:"post",url:path()+"dbsave/groups.php",data:{delgroup:gid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Removed Successfully!"); window.history.back();
				}
				else{ alert(res); }
			});
		}
	}
	
	function shufflegrp(src){
		var gto = $("#mgid").val().trim();
		if(confirm("Move client to selected group?")){
			$.ajax({
				method:"post",url:path()+"dbsave/groups.php",data:{movegrp:src,mvto:gto},
				beforeSend:function(){ progress("Monving...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Removed Successfully!"); closepop();
					fetchpage("groups.php?vgroup="+src.split(":")[0]);
				}
				else{ alert(res); }
			});
		}
	}

	function savegoup(e,gid){
		e.preventDefault();
		if(confirm("Proceed to save client group?")){
			var tp = _("hasfiles").value.trim();
			if(tp.length>2){
				var data=new FormData(_("gform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					data.append(files[i],_(files[i]).files[0]);
				}
				
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload=function(){
					if(this.responseText.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); 
						if(gid>0){ fetchpage("groups.php?vgroup="+gid); }
						else{ loadpage("groups.php?view"); }
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/groups.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#gform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/groups.php",data:data,
					beforeSend:function(){ progress("Procesing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); 
						if(gid>0){ fetchpage("groups.php?vgroup="+gid); }
						else{ loadpage("groups.php?view"); }
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function setcols(){
		if(checkboxes("cols[]")){
			var arr = [], boxes = document.querySelectorAll('input[type=checkbox]:checked');
			for (var i = 0; i < boxes.length; i++) { arr.push(boxes[i].value); }
			var json = JSON.stringify(arr); 
			createcookie("gcols",json,1); closepop(); 
			setTimeout(function (){ window.location.reload(); },300);
		}
		else{ toast("Select atleast 1 Field"); }
	}
	
	function addrow(){
		var id = Date.now();
		$(".tbl").append("<tr id='tr"+id+"'><td><input type='text' list='ins' name='idnos[]' autocomplete='off' style='width:100%' placeholder='Client Idno' required></td><td style='width:40px'>"+
		"<i class='bi-x-lg' style='color:#ff4500;cursor:pointer;font-size:23px' onclick=\"$('#tr"+id+"').remove()\"></i></td></tr>");
	}

</script>