<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	$atbl = "finassets$cid";
	
	# manage assets
	if(isset($_GET['view'])){
		$view = trim($_GET['view']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$str = (isset($_GET['str'])) ? ltrim(clean(strtolower($_GET['str'])),"0"):null;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
		$ctbl = $atbl;
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 20; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1); $media=[];
		
		$exclude = array("id","def","time","asset_name","status","creator","qty","measurement_unit");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ctbl'");
		$def = array("id"=>"number","branch"=>"number","asset_name"=>"text","asset_description"=>"textarea","measurement_unit"=>"number","asset_cost"=>"number","asset_category"=>"number",
		"qty"=>"text","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){
				if(in_array($dtp,["image","pdf","docx"])){ $exclude[]=$col; $media[]=$col; }
			}
		}
		
		$fields = array_keys($ftc); $units=$cats=[];
		$stbl = "org".$cid."_staff"; $staff[]="None";
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($db->istable(1,"units")){
			$res = $db->query(1,"SELECT *FROM `units`");
			if($res){
				foreach($res as $row){ $units[$row['id']]=$row["symbol"]; }
			}
		}
		
		$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='asset'");
		if($sql){
			foreach($sql as $row){ $cats[$row['id']] = prepare(ucfirst($row['product'])); }
		}
		
		$brans[] = "Corporate";
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qri){
			foreach($qri as $row){ $brans[$row['id']] = prepare(ucfirst($row['branch'])); }
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$load = ($me['access_level']=="hq") ? 1:"(`branch`='".$me['branch']."' OR `branch`='0')";
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? setRegion($cnf["region"]):$load;
		$cond.= (!$str && $view) ? " AND `status`='$view'":" AND NOT `status`='15'";
		if($str){
			$skip = array_merge($exclude,array("def")); $slis="";
			foreach($fields as $col){
				if(!in_array($col,$skip)){ $slis.="`$col` LIKE '$str%' OR "; }
				if($col=="asset_name"){ $slis.="`$col` LIKE '%$str%' OR "; }
			}
			$cond.= " AND (".rtrim(trim($slis),"OR").")";
		}
		
		$no=($perpage*$page)-$perpage; $fro=$no+1; $trs=$ths=$offs=$brns=$act="";
		$rem = array_diff($fields,$exclude); $iss=0;
		
		$cutf = (count($rem)>7) ? array_slice($rem,0,7):$rem;
		$show = (isset($_COOKIE['acols'])) ? json_decode($_COOKIE['acols']):$cutf;
		
		if($db->istable(2,$ctbl)){
			$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE $cond ORDER BY asset_name,status ASC $lim");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $pid=$row["asset_category"]; $tds="";
					$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `org$cid"."_loans` WHERE `loan_product`='$pid'");
					$states = array("<span style='color:orange;'><i class='fa fa-circle'></i> Inactive</span>","<span style='color:green;'><i class='fa fa-circle'></i> Active</span>");
					
					foreach($ftc as $col=>$dtp){
						if(in_array($col,$show)){
							$val = ($col=="asset_cost") ? fnum($row[$col]):prepare(ucfirst($row[$col]));
							$val = ($col=="asset_category") ? $cats[$row[$col]]:$val;
							$val = ($col=="branch") ? $brans[$row[$col]]:$val;
							$val = (in_array($col,["creator"])) ? $staff[$row[$col]]:$val;
							$val = (strlen($row[$col])>45) ? substr($row[$col],0,45)."...<a href=\"javascript:alert('$val')\">View</a>":$val;
							$val = ($dtp=="url" or filter_var($row[$col],FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
							$tds.= "<td>$val</td>"; $ths.= ($no==$fro) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					$tds.= (count($media)) ? "<td>".count($media)." Files<br><a href='javascript:void(0)' onclick=\"popupload('media.php?vmed=$ctbl&src=$rid')\">
					<i class='bi-eye'></i> View</a></td>":""; $ed=0; $act="";
					if($row["status"]){
						$tds.= (in_array("process asset loan",$perms)) ? "<td><button class='btnn' style='padding:4px;min-width:60px' onclick=\"popupload('finassets.php?issue=$rid')\">
						<i class='bi-bag-plus'></i> Issue</button></td>":""; $iss++;
					}
					
					if(in_array("add loan asset",$perms)){
						$act = "<a href='javascript:void(0)' onclick=\"popupload('finassets.php?create=$rid')\"><i class='fa fa-pencil'></i> Edit</a>"; $ed++;
					}
					if(in_array("delete loan asset",$perms)){
						$brk = ($ed) ? "<br>":"";
						$act.= "$brk <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delasset('$rid')\"><i class='fa fa-times'></i> Remove</a>";
					}
					
					$name = prepare(ucwords($row['asset_name'])); $tot=intval($chk[0]["tot"]); $ms=$units[$row["measurement_unit"]]; $css=($act) ? "min-width:80px":"";
					$trs.= "<tr valign='top' id='tr$rid'><td>$no.</td><td style='min-width:100px'>$name<br>".$states[$row['status']]."</td><td>$tot $ms</td>
					<td>".fnum($row["qty"])." $ms</td>$tds<td style='$css'>$act</td></tr>";
				}
			}
		}
		
		if(!$ths){
			foreach($fields as $key=>$col){ 
				if(in_array($col,$show)){ $ths.= "<td>".ucfirst(str_replace("_"," ",$col))."</td>"; }
			}
		}
		
		$grups = "<option value=''>-- Filter Assets --</option>";
		foreach(array("Inactive Products","Active Products") as $key=>$des){
			$cnd = ($view==$key && strlen($view)>0 && !$str) ? "selected":"";
			$grups.= "<option value='$key' $cnd>$des</option>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.= "<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
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
					$brns = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('finassets.php?view=$view&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brns.= "<select style='width:150px;font-size:15px;' onchange=\"loadpage('finassets.php?view=$view&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$sql = ($db->istable(2,$atbl)) ? $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` WHERE $cond"):""; 
		$title = ($str) ? "Search Results ":"Asset List"; $totals=($sql) ? $sql[0]['total']:0;
		$carr = array("Inactive Items","Active Items"); $title=($view!=null && !$str) ? $carr[$view]:$title;
		$grps = ($view>7) ? "":"<select style='width:150px;font-size:15px;cursor:pointer' onchange=\"loadpage('finassets.php?view='+cleanstr(this.value))\">$grups</select>";
		
		$ths.= (count($media)) ? "<td>Attachments</td>":""; $ths.=($iss) ? "<td></td>":"";
		$vcol = (count($rem)>7) ? "<i class='bi-sort-down-alt' style='float:right;margin-left:6px;cursor:pointer;font-size:32px;margin-top:-8px' title='Sort Columns'
		onclick=\"popupload('finassets.php?sortcols')\"></i>":"";
		
		$prnt = ($totals) ? genrepDiv("finassets.php?src=".base64_encode($cond)."&v=$view",'right'):"";
		$setfom = (in_array("configure system",$perms)) ? "<button style='padding:4px;float:right' class='bts' onclick=\"loadpage('setup.php?finassetfom')\">
		<i class='fa fa-wrench'></i> Setup Form</button>":"";
		
		echo "<div class='cardv' style='max-width:1400px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;max-width:1380px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title (".fnum($totals).") $setfom</h3><hr>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped' style='width:100%;min-width:750px;font-size:14px;' cellpadding='5'>
    					<caption style='caption-side:top;padding:0px 0px 5px 0px'> $prnt
						<input type='search' onkeyup=\"fsearch(event,'finassets.php?view=$view&str='+cleanstr(this.value))\" onsearch=\"loadpage('finassets.php?view=$view&str='+cleanstr(this.value))\" 
						value='".prepare($str)."' style='float:right;width:150px;padding:4px;font-size:14px;margin-right:7px' placeholder='&#xf002; Search'> $grps $brns $vcol</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td colspan='2'>Asset Name</td><td>Given Out</td>
						<td>Available Qty</td>$ths<td></td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"finassets.php?view=$view&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed $title Record");
	}
	
	# sort cols
	if(isset($_GET['sortcols'])){
		$exclude = array("id","def","time","asset_name","status","creator","qty","measurement_unit");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$atbl'");
		$def = array("id"=>"number","branch"=>"number","asset_name"=>"text","asset_description"=>"textarea","measurement_unit"=>"number","asset_cost"=>"number","asset_category"=>"number",
		"qty"=>"text","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		$ftc = ($res) ? json_decode($res[0]['fields'],1):$def; 
		
		if(in_array("image",$ftc) or in_array("pdf",$ftc) or in_array("docx",$ftc)){
			foreach($ftc as $col=>$dtp){ 
				if(in_array($dtp,["image","docx","pdf"])){ unset($ftc[$col]); }
			}
		}
		
		$rem = array_diff(array_keys($ftc),$exclude);
		$cutf = (count($rem)>7) ? array_slice($rem,0,7):$rem; 
		$show = (isset($_COOKIE['acols'])) ? json_decode($_COOKIE['acols']):$cutf;
		
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
			<h3 style='font-size:23px;text-align:;'>Filter Asset Fields</h3><hr>
			<table cellpadding='7' style='width:100%'> $trs </table><hr>
			<p style='text-align:right'><button class='btnn' onclick='setcols()'>View Selected</button></p><br>
		</div>";
	}
	
	# issue Asset
	if(isset($_GET["issue"])){
		$rid = trim($_GET["issue"]);
		$me = staffInfo($sid);
		$sql = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$rid'");
		$row = $sql[0]; $name=prepare($row["asset_name"]); $amnt=$row["asset_cost"]; $qty=$row["qty"]; $pid=$row["asset_category"];
		
		$cond = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":""; $opts="";
		$cond = ($me['access_level']=="branch") ? "AND `branch`='".$me['branch']."'":$cond;
		$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE NOT `status`='2' $cond"); 
		if($res){
			foreach($res as $row){
				$opts.= "<option value='".$row['idno']."'>".prepare(ucfirst($row['name']))."</option>";
			}
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:23px;text-align:center;'>Issue Asset on Loan</h3><br>
			<form method='post' id='ifom' onsubmit=\"setasset(event)\">
				<datalist id='lis'>$opts</datalist> <input type='hidden' name='asid' value='$rid'>
				<input type='hidden' name='prod' value='$pid'> <input type='hidden' name='cost' value='$amnt'>
				<p>Select Client to Issue to <b>$name</b> of Ksh <b>".fnum($amnt)."</b> each</p>
				<p>Client Idno<br><input type='text' style='width:100%' name='cid' list='lis' required></p>
				<p>Item Qty to Issue<br><input type='number' name='qty' style='width:100%' min='1' max='$qty' placeholder='$qty Available' required></p><br>
				<p style='text-align:right'><button class='btnn'>Proceed</button></p>
			</form><br>
		</div>";
	}
	
	# add/edit asset
	if(isset($_GET["create"])){
		$aid = intval($_GET['create']);
		$me = staffInfo($sid); 
		$cnf = json_decode($me["config"],1);
		$perms = getroles(explode(",",$me['roles']));
		
		$exclude = array("id","def","time","creator");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$atbl'");
		$def = array("id"=>"number","branch"=>"number","asset_name"=>"text","asset_description"=>"textarea","measurement_unit"=>"number","asset_cost"=>"number","asset_category"=>"number",
		"qty"=>"text","def"=>"textarea","creator"=>"number","status"=>"number","time"=>"number");
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def; 
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		
		$defv = array("def"=>"[]","time"=>time(),"creator"=>$sid);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld] = (isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		if($aid){
			$row = $db->query(2,"SELECT *FROM `$atbl` WHERE `id`='$aid'"); $dvals=array_map("prepare",$row[0]);
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
				if($field=="asset_category"){
					$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='asset' ORDER BY `product` ASC"); $opts="";
					foreach($res as $row){
						$pid=$row['id']; $cnd=($pid==$dvals[$field]) ? "selected":"";
						$opts.= "<option value='$pid' $cnd>".prepare(ucwords($row['product']))."</option>";
					}
					$lis.= "<p>$fname<br><select name='$field' style='width:100%' required>$opts</select></p>";
				}
				elseif($field=="measurement_unit"){
					$res = $db->query(1,"SELECT *FROM `units` WHERE `client`='$cid' ORDER BY `unit` ASC"); $opts="";
					foreach($res as $row){
						$rid=$row['id']; $cnd=($rid==$dvals[$field]) ? "selected":"";
						$opts.= "<option value='$rid' $cnd>".prepare(ucfirst($row['unit']))." (".$row["symbol"].")</option>";
					}
					$lis.= "<p>$fname<br><select name='$field' style='width:100%' required>$opts</select></p>";
				}
				elseif($field=="branch"){
					$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
					$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond; $tot=0;
					$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $cond ORDER BY `branch` ASC"); $opts="";
					foreach($res as $row){
						$rid=$row['id']; $cnd=($rid==$dvals[$field]) ? "selected":""; $tot++;
						$opts.= "<option value='$rid' $cnd>".prepare(ucfirst($row['branch']))."</option>";
					}
					
					$lis.= ($tot>1) ? "<p>$fname<br><select name='$field' style='width:100%' required><option value='0'>All Branches</option>$opts</select></p>":
					"<input type='hidden' name='branch' value='$rid'>";
				}
				elseif($field=="qty"){
					$lis.= "<p>Available Qty<br><input type='text' id='aqty' onkeyup=\"valid('aqty',this.value)\" value='$dvals[$field]' name='$field' style='width:100%' required></p>";
				}
				elseif($field=="status"){
					$drops = array(1=>"Active for Use",0=>"Inactive to Use"); $opts="";
					foreach($drops as $drop=>$txt){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.= "<option value='$drop' $cnd>$txt</option>";
					}
					$lis.= "<p>Asset Status<br><select name='$field' style='width:100%'>$opts</select></p>";
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
		
		$title = ($aid) ? "Update Asset":"Add New Asset"; 
		echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='aform' onsubmit=\"saveasset(event,'$aid')\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($cols),1)."'>
				<input type='hidden' name='id' value='$aid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'> 
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
	}
	
	# asset categories
	if(isset($_GET['cats'])){
		$me = staffInfo($sid);
		$perms = getroles(explode(",",$me['roles']));
		$itps = array("FR"=>"Flat Rate","RB"=>"Reducing Balance");
		$lcat = "asset"; $trs=$opts=""; $no=0;
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='$lcat'");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['product'])); $min=fnum($row['minamount']); $max=fnum($row['maxamount']); 
				$terms=json_decode($row['payterms'],1); $intr=(is_numeric($row['interest'])) ? fnum($row['interest']):$row['interest']; 
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
				
				$lis = ($lis) ? $lis:"None"; $ptp=$row['category']; $intdur=$row["interest_duration"];
				$itp = (isset($pdef["intrtp"])) ? $itps[$pdef["intrtp"]]:"Flat Rate";
				$del = (in_array("manage asset categories",$perms)) ? "<a href='javascript:void(0)' style='color:#ff4500' onclick=\"delprod('$ptp:$rid')\">Remove</a>":"";
				$add = (in_array("manage asset categories",$perms)) ? "<a href='javascript:void(0)' onclick=\"popupload('finassets.php?addcat=$rid')\">Edit</a> |":"";
				$trs.="<tr valign='top' id='tr$rid'><td>$no</td><td>$name</td><td>$min - $max</td><td>$dur X $intv days</td><td>$intdur days @$intr<br>
				<span style='color:grey;font-size:14px'>$itp</span></td><td>$lis</td><td>$add $del</td></tr>";
			}
		}
			
		$data = "<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td colspan='2'>Category</td><td>Funding</td><td>Installments</td>
		<td>Interest Charges</td><td>Pay Terms</td><td>Action</td></tr> $trs";
		
		$add = (in_array("manage asset categories",$perms)) ? "<button class='bts' style='float:right;font-size:13px;padding:5px' onclick=\"popupload('finassets.php?addcat')\">
		<i class='fa fa-plus'></i> Create</button>":"";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Asset Categories $add</h3>
				<table class='table-striped table-borderd' style='width:100%;margin-top:15px' cellpadding='5'> $data </table>
			</div>
		</div>";
		savelog($sid,"Viewed Asset categories");
	}
	
	# add/edit categories
	if(isset($_GET['addcat'])){
		$rid = trim($_GET['addcat']); $ptp="asset"; 
		$prod=$min=$max=$intv=$dur=$intr=$penal=$intdur=$lis=$dis1=$trs=$tls=$als=$ils=""; $pdef=[];
		$dis2="none"; $ran=rand(123400,999999); $penamt=0; $itps=array("FR"=>"Flat Rate","RB"=>"Reducing Balance");
		$clist = array(0=>"New Clients only",6=>"Repeat Clients",1=>"@Loan application",2=>"Every Installment",3=>"@Certain Installment",4=>"Loan deduction",
		5=>"Added to Loan",7=>"Asset Deposit");
		
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
		
		$text = ($rid) ? "Edit Asset Category":"Create Asset Category"; $opts="";
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
		$inst = (isset($pdef["showinst"])) ? $pdef["showinst"]:0;
		
		foreach($itps as $key=>$txt){
			$cnd = ($key==$itp) ? "selected":"";
			$tls.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("No running Loans","Other running loans") as $key=>$txt){
			$cnd = ($key==$lnapp) ? "selected":"";
			$als.= "<option value='$key' $cnd>$txt</option>";
		}
		
		foreach(array("All Installements","Current Installement") as $key=>$txt){
			$cnd = ($key==$inst) ? "selected":"";
			$ils.= "<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div style='padding:10px;margin:0 auto;min-width:450px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>$text</h3><br>
			<form method='post' id='lform' onsubmit=\"saveprod(event,'$ptp')\">
				<input type='hidden' name='loanprod' value='$rid'><input type='hidden' name='prodcat' value='$ptp'>
				<table cellpadding='7' style='width:100%;min-width:500px' class='ptbl'>
					<caption style='text-align:right'><a href='javascript:void(0)' onclick=\"addfield('$ptp')\"><i class='fa fa-plus'></i> Add Charges</a><hr></caption>
					<tr valign='bottom'><td>Category Name<br><input type='text' name='prod' style='width:100%' value=\"$prod\" required></td>
					<td>Repayment Duration<br><input type='number' placeholder='Total Days' style='width:100%' name='ldur' value='$dur' required></td>
					<td>Payment Intervals<br><input type='number' placeholder='Installment Days' style='width:100%' name='pdays' value='$intv' required></td></tr>
					<tr><td>Interest Charges<i class='bi-columns-gap' style='float:right;cursor:pointer' onclick=\"togprods()\" title='Set Interest range'></i><br>
					<input type='text' style='width:100%;display:$dis1' id='intr' name='intr' placeholder='E.g 20%' onkeyup=\"valid('intr',this.value)\" value='$intr' required>
					<span id='drops' style='display:$dis2'><select style='width:100%;font-size:15px' name='varint'>$lis</select><br><a href='javascript:void(0)' 
					onclick=\"popupload('setup.php?prodvars')\">Manage Ranges</a></span></td>
					<td>Interest Charges Covers<br><input type='number' placeholder='How many Days' style='width:100%' name='intdur' value='$intdur' required></td>
					<td>Interest Type<br><select name='intrtp' style='width:100%'>$tls</select></td></tr>
					<tr valign='top'><td>Min Asset Amount<br><input type='number' style='width:100%' name='minam' value='$min' required></td>
					<td>Max Asset Amount<br><input type='number' style='width:100%' name='maxam' value='$max' required></td>
					<td>Arrears Penalty<br><select name='penalty' style='width:100%'>$opts</select></td></tr>
					<tr valign='top'><td>Penalty Amount<br><input type='text' id='pen' onkeyup=\"valid('pen',this.value)\" style='width:100%' name='penamt' value='$penamt' required></td>
					<td>Rollover Fees<br><input type='text' id='roll' onkeyup=\"valid('roll',this.value)\" style='width:100%' name='rollfee' value='$rolfee' required></td>
					<td>Loan Offset Fees<br><input type='text' id='offee' onkeyup=\"valid('offee',this.value)\" style='width:100%' name='offee' value='$offee' required></td></tr> 
					<tr valign='bottom'><td>Repay Waiver days<br><input type='number' name='waiver' value='$waivd' style='width:100%' required></td>
					<td>Clients apply with<br><select style='width:100%;font-size:15px' name='lnapp'>$als</select></td><td>Installement display<br>
					<select style='width:100%;font-size:15px' name='instds'>$ils</select></td></tr> $trs
				</table> <p style='text-align:right'><button class='btnn'>Save</button></p>
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
	
	# measurement units
	if(isset($_GET["units"])){
		$trs=""; $no=0;
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		if(!$db->istable(1,"units")){ $db->createTbl(1,"units",["client"=>"INT","unit"=>"CHAR","symbol"=>"CHAR","measures"=>"TEXT"]); }
		
		$res = $db->query(1,"SELECT *FROM `units` WHERE `client`='$cid' ORDER BY `unit` ASC");
		if($res){
			$istb = ($db->istable(2,$atbl)) ? 1:0;
			foreach($res as $row){
				$unit = prepare(ucfirst($row['unit'])); $sym=prepare($row['symbol']); $des=json_decode($row['measures'],1); $rid=$row['id']; $lis=""; $no++;
				foreach($des as $key=>$one){
					$lis.= ($one["conv"]<1) ? "<li>1 $sym = ".fnum(1/$one['conv'])." ".ucfirst($key)." (".prepare(ucfirst($one['symbol'])).")</li>":
					"<li>1 $sym = ".fnum($one['conv'])." ".ucfirst($key)." (".prepare(ucfirst($one['symbol'])).")</li>";
				}
				
				$act = (in_array("manage units of measurement",$perms)) ? "<a href=\"javascript:void(0)\" onclick=\"popupload('finassets.php?addunit=$rid')\">
				<i class='bi-pencil-square'></i> Edit</a>":""; $lis=($lis) ? $lis:"None";
				$trs.= "<tr valign='top'><td>$no</td><td>$unit</td><td>$sym</td><td>$lis</td><td>$act</td></tr>";
			}
		}
		
		$trs = ($trs) ? $trs:"<tr><td colspan='5'>No Units of Measurement Found</td></tr>";
		$add = (in_array("manage units of measurement",$perms)) ? "<button class='bts' style='float:right' onclick=\"popupload('finassets.php?addunit')\">
		<i class='fa fa-plus'></i> Create</button>":"";
		
		echo "<div class='cardv' style='max-width:800px;min-height:300px;padding:15px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px;margin-bottom:15px'>$backbtn Units of Measurement $add</h3>
			<table class='table-striped' style='width:100%;min-width:600px' cellpadding='7'>
				<tr style='font-weight:bold;color:#191970;background:#E6E6FA'><td colspan='2'>Unit</td><td>Symbol</td><td>Breakdown</td><td></td></tr> $trs
			</table>
		</div>";
		savelog($sid,"Viewed Measurement units");
	}
	
	# create units
	if(isset($_GET['addunit'])){
		$rid = trim($_GET['addunit']);
		$unit=$trs=$symb=$esym=$opts=""; $no=0; $ms=[];
		$title = ($rid) ? "Edit":"Create";
		
		if($rid){
			$sql = $db->query(1,"SELECT *FROM `units` WHERE `id`='$rid'");
			$row = $sql[0]; $unit=prepare($row['unit']); $ms=json_decode($row['measures'],1); $symb=$row['symbol'];
		}
		
		foreach($ms as $key=>$one){
			$sym=$one['symbol']; $conv=$one['conv']; $ran=rand(100000,99999999); $no++;
			if($conv!=1){
				$del = ($no>1) ? "<a href=\"javascript:void(0)\" onclick=\"$('#r$ran').remove()\" style='color:#ff4500;float:right'>Remove</a>":"";
				$trs.= "<tr style='font-size:14px' valign='top' id='r$ran'><td>Breakdown Unit<br><input type='text' name='units[]' style='width:100%' value='$key' required></td>
				<td>Symbol<br><input type='text' name='syms[]' style='width:100%' value='$sym' required></td>
				<td>Conversion<br><input type='text' id='$ran' name='convs[]' onkeyup=\"valid('$ran',this.value)\" value='$conv' style='width:100%' required> $del</td></tr>";
			}
		}
		
		echo "<div style='max-width:350px;padding:10px;margin:0 auto' class='fps'>
			<h3 style='font-size:23px;text-align:center'>$title Measurement Unit</h3><br>
			<form method='post' id='uform' onsubmit='saveunit(event)'>
				<input type='hidden' name='nid' value='$rid'> 
				<table cellpadding='5' style='width:100%' class='itbl'>
					<tr><td colspan='3'>Unit Name<br><input type='text' name='unit' value=\"$unit\" style='width:100%' placeholder='Kilogram' required></td></tr>
					<tr valign='bottom'><td colspan='2'>Unit Symbol<br><input style='width:100%' type='text' value='$symb' name='usym' placeholder='Kg' required></td>
					<td style='text-align:right'><br><a href=\"javascript:void(0)\" onclick=\"addurow()\"><i class='bi-plus-lg'></i> SubUnit</a></td></tr>
					<tr><td colspan='3'>You can add a sub-unit for above unit breakdown.<br> E.g. 1gm = 0.001Kg, 1mg = 0.000001 Kg</td></tr> $trs
				</table><hr>
				<p style='text-align:right;margin-top:10px'><button class='btnn'><i class='bi-save'></i> Create</button></p>
			</form><br>
		</div>";
	}
	
	@ob_end_flush();
?>

<script>
	
	function saveasset(e,aid){
		e.preventDefault();
		if(confirm("Proceed to save Loan asset?")){
			var tp = _("hasfiles").value.trim(), name=$("#asset_name").val().trim();
			if(tp.length>2){
				var data = new FormData(_("aform")),files=tp.split(":");
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
						fetchpage("finassets.php?view&str="+cleanstr(name));
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/finassets.php",true);
				xhr.send(data);
			}
			else{
				var data = $("#aform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/finassets.php",data:data,
					beforeSend:function(){ progress("Procesing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Success"); closepop(); 
						fetchpage("finassets.php?view&str="+cleanstr(name));
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function setasset(e){
		e.preventDefault();
		var data = $("#ifom").serialize();
		if(confirm("Proceed to process loan for selected client?")){
			popupload("loans.php?template&"+data);
		}
	}
	
	function saveprod(e,cat){
		e.preventDefault();
		if(confirm("Save asset Category?")){
			var data = $("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/setup.php",data:data,
				beforeSend:function(){ progress("Saving...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast(res); closepop(); loadpage("finassets.php?cats"); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function delasset(id){
		if(confirm("Sure to delete the Asset?")){
			$.ajax({
				method:"post",url:path()+"dbsave/finassets.php",data:{delitem:id},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				toast(res); if(res.trim()=="success"){ $("#tr"+id).remove(); }
			});
		}
	}
	
	function delprod(id){
		if(confirm("Sure to delete Asset Category?")){
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
	
	function saveunit(e){
		e.preventDefault();
		if(confirm("Save unit of measurement?")){
			var data = $("#uform").serialize();
			$.ajax({
				method:"post",url:path()+"/dbsave/finassets.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				alert("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); loadpage("finassets.php?units"); }
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
				complete:function(){ progress(); }
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
	
	function setcols(){
		if(checkboxes("cols[]")){
			var arr = [], boxes = document.querySelectorAll('input[type=checkbox]:checked');
			for (var i = 0; i < boxes.length; i++) { arr.push(boxes[i].value); }
			var json = JSON.stringify(arr); 
			createcookie("acols",json,1); closepop(); 
			setTimeout(function (){ window.location.reload(); },300);
		}
		else{ toast("Select atleast 1 Field"); }
	}
	
	function addfield(cat){
		var did = Date.now(), opts = "<option value='0'>New Clients only</option>"; 
		$(".ptbl").append("<tr id='"+did+"' valign='top'><td><select name='charges["+did+"]' style='width:100%;font-size:15px' id='o"+did+"' onchange=\"ckcharge('"+did+"',this.value)\">"+
		"<option value='1'>@Loan application</option>"+opts+"<option value='6'>Repeat applications</option><option value='2'>Every Installment</option><option value='3'>@Certain Installment</option>"+
		"<option value='4'>Loan deduction</option><option value='5'>Added to Loan</option><option value='7'>Asset Deposit</option></select><span id='s"+did+"'></span></td><td><input type='text'"+
		"placeholder='Description' style='width:100%' name='cdesc["+did+"]' required></td><td><input type='text' placeholder='Fee Charged' id='f"+did+"' onkeyup=\"valid('f"+did+"',this.value)\" "+
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
	
	function addprow(){
		var did = Date.now();
		$(".pvtbl").append("<tr><td style='width:40%'><input type='number' style='width:100%' name='pday[]' value='1' required></td>"+
		"<td><input type='text' id='"+did+"' onkeyup=\"valid('"+did+"',this.value)\" style='width:100%' name='pchaj[]' value='10%' required></td></tr>");
	}
		
	function addurow(){
		var did = Date.now();
		$(".itbl").append("<tr valign='top' id='"+did+"' style='font-size:14px'><td style='width:38%'>Unit Name<br>"+
		"<input type='text' name='units[]' placeholder='E.g Grams' style='width:100%' required></td>"+
		"<td style='width:30%'>Symbol<br><input type='text' name='syms[]' placeholder='GM' style='width:100%' required></td>"+
		"<td>Conversion<br><input type='text' id='i"+did+"' placeholder='0.001' name='convs[]' onkeyup=\"valid('i"+did+"',this.value)\" style='width:100%' required><br>"+
		"<a href='javascript:void(0)' onclick=\"$('#"+did+"').remove()\" style='float:right;color:#ff4500'><i class='fa fa-times'></i> Remove</a></td></tr>");
	}

</script>