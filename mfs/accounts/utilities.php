<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# view utility expenses
	if(isset($_GET['view'])){
		$ftc = trim($_GET['view']);
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):"";
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles'])); 
		$cnf = json_decode($me["config"],1); $access=$me['access_level'];
		$rtbl = "utilities$cid"; $tmon=strtotime(date("Y-M"));
		
		if(!$db->istable(3,$rtbl)){
			$def = array("staff"=>"INT","branch"=>"INT","item_description"=>"TEXT","cost"=>"INT","approvals"=>"TEXT","approved"=>"CHAR","recipient"=>"TEXT","month"=>"INT","status"=>"INT","time"=>"INT");
			$db->createTbl(3,$rtbl,$def);
		}
		
		if(!$mon){
			$res = $db->query(3,"SELECT MAX(month) AS mon FROM `$rtbl`");
			$mon = ($res) ? $res[0]['mon']:$tmon;
		}
		
		if(!$ftc){
			$fetch = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$sql = $db->query(3,"SELECT COUNT(*) AS total FROM `$rtbl` WHERE `status`<10 AND `month`='$mon' $fetch");
			$pnd = ($sql) ? intval($sql[0]['total']):0; $ftc=($pnd) ? 2:3;
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $pozts[$row['id']]=$row['position'];
		}
		
		$mons=$divs=$brns=$bin="";
		$res = $db->query(3,"SELECT DISTINCT `month` FROM `$rtbl` ORDER BY `month` DESC LIMIT 12");
		if($res){
			foreach($res as $row){
				$mn=$row['month']; $cnd = ($mn==$mon) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$brans = array("Corporate");
		$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$mons = ($mons) ? $mons:"<option value='$tmon'>".date('M Y')."</option>";
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='utilities'");
		$qri = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='superlevels'");
		$lvs = $ldef=($app) ? json_decode($app[0]['levels'],1):[]; $hlevs = ($qri) ? json_decode($qri[0]['levels'],1):[]; 
		$super = (isset($hlevs['utilities'])) ? $hlevs['utilities']:[]; $dcd=count($lvs); $apn=[];
		$ownview = (defined("UTILITY_VIEW_OWN")) ? array_map("strtolower",UTILITY_VIEW_OWN):[];
		$mine = (in_array($access,["portfolio","collection agent"]) or in_array(strtolower($me["position"]),$ownview)) ? 1:0;
		
		if(in_array($access,["branch"])){ $bran=$me['branch']; }
		$states = array(2=>"`status`<10",3=>"`status`>100",4=>"`status`='15'");
		$cond = ($str) ? 1:"`month`='$mon'";
		$cond.= (isset($states[$ftc])) ? " AND ".$states[$ftc]:"";
		$cond.= ($mine) ? " AND `staff`='$sid'":"";
		$cond.= ($str) ? " AND (JSON_SEARCH(item_description, 'one', '%$str%', NULL, '$[*].item') IS NOT NULL OR JSON_EXTRACT(recipient,'$.name') LIKE '%$str%')":"";
		$cond.= (($bran or $access=="branch") && !$mine) ? " AND `branch`='$bran'":"";
		$cond.= ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? " AND ".setRegion($cnf["region"]):"";
		
		$qri = $db->query(3,"SELECT *FROM `$rtbl` WHERE $cond ORDER BY id DESC");
		if($qri){
			foreach($qri as $row){
				$rid = $row['id']; $items=json_decode($row['item_description'],1); 
				$items = (is_array($items)) ? $items:[]; $tot=count($items); $st=$row['status']; $tym=$row['time'];
				$cost = fnum($row['cost']); $apprv=fnum($row['approved']); $st=$row['status']; $nxt=$st+1; $no=0;
				$sts = ($st>100) ? 20:15; $state=($st<10) ? 10:$sts; $sno=prenum($rid); 
				$show = ($state==20) ? $apprv:$cost;
				
				$from = explode(" ",$staff[$row['staff']])[0]; $bname=$brans[$row['branch']];
				$colors1 = array(10=>"#D8BFD8",15=>"#CD5C5C",20=>"#66CDAA");
				$colors2 = array(10=>"#663399",15=>"#fff",20=>"#fff");
				
				$trs=$npos=""; $add=($state==15) ? "border:2px solid #AFEEEE;":"";
				$all = ($tot==1) ? "1 Item":"$tot Items";
				$status = array(10=>"$all Pending",15=>"$all Declined",20=>"$all Approved");
				$btx = "<button class='bts' onclick=\"loadpage('accounts/utilities.php?reqid=$rid')\" style='padding:3px;border-radius:5px;$add'><i class='fa fa-gift'></i> Open</button>";
				
				if(isset($super[$pozts[$row['staff']]])){
					foreach($lvs as $key=>$one){
						if($one==$pozts[$row['staff']]){ $lvs[$key]=$super[$one]; }
					}
				}
					
				foreach($lvs as $key=>$one){ $levels[$key]=$one; }
				if($dcd && $st<10){
					foreach($levels as $pos=>$user){
						$key=$pos-1; if($st==$key){ $npos=$user; $appr[str_replace(" ","_",$user)]=$key; }
						$status[10]="$all - <i>Waiting ".prepare(ucfirst($npos))."</i>";
					}
					
					$posts = array("super user",$npos);
					if(defined("REQ_SUPER_APPROVERS")){ $posts = array_merge($posts,REQ_SUPER_APPROVERS); }
					if(in_array("approve vendor payment",$perms) && in_array($me['position'],$posts)){
						$btx = "<button class='bts' onclick=\"loadpage('accounts/utilities.php?reqid=$rid&approve=$nxt')\" style='padding:3px;border-radius:5px;'>
						<i class='fa fa-check'></i> Approve</button>"; 
						if(count($items)==1){ $apn[]=$rid; }
					}
				}
				
				foreach($items as $one){
					$item = prepare(ucfirst($one['item'])); $qty=$one['qty']; $cst=($one['type']=="cash") ? $one['cost']:"---"; $no++;
					$trs.= "<tr style='font-size:11px'><td>$no</td><td>$item</td><td>$qty</td><td>$cst</td></tr>";
				}
				
				$lvs=$ldef;
				$divs.= "<div class='col-12 col-sm-6 col-md-4 col-lg-3 mb-2 mt-3'>
					<div style='background:$colors1[$state];padding:5px;border-radius:5px;height:225px;font-family:cambria'>
						<div style='height:100px;background:#fff;border-top-right-radius:5px;border-top-left-radius:5px;overflow:hidden;padding:4px'>
							<p style='font-size:13px;color:#4682b4;margin-bottom:5px'>From $from for $bname</p>
							<table style='width:100%' class='table-bordered' style='color:#2f4f4f;font-size:12px' cellpadding='2'>
								<tr style='font-size:11px;font-weight:bold;color:#191970;background:#e6e6fa'><td colspan='2'>Item</td><td>Qty</td><td>Cost</td></tr> $trs
							</table>
						</div>
						<div style='color:$colors2[$state]'>
							<p style='font-weight:bold;margin:7px 0px;border-bottom:1px solid $colors2[$state];padding-bottom:5px'><i class='fa fa-gift'></i> $sno 
							<span style='float:right;font-size:14px'>".date('d-m-Y, h:i a',$tym)."</span></p> <p style='font-size:14px'>$status[$state]</p>
							<p style='text-align:right'><span style='font-weight:bold;float:left'>KES $show</span> $btx</p>
						</div>
					</div>
				</div>";
			}
		}
		
		$opts = "<option value='0'>-- Select --</option>";
		foreach(array(2=>"Pending",3=>"Approved",4=>"Declined") as $key=>$typ){
			$cnd=($key==$ftc) ? "selected":"";
			$opts.="<option value='$key' $cnd>$typ</option>";
		}
		
		if(in_array($access,["hq","region"])){
			foreach($brans as $bid=>$name){
				$cnd=($bran==$bid) ? "selected":"";
				$brns.="<option value='$bid' $cnd>$name</option>";
			}
			$bin = "<select style='padding:5px;width:140px;' onchange=\"loadpage('accounts/utilities.php?view=$ftc&mon=$mon&bran='+this.value)\">$brns</select>";
		}
		
		$apply = (in_array("create vendor payment",$perms)) ? "<button class='bts' onclick=\"loadpage('accounts/utilities.php?reqid')\" 
		style='padding:5px;font-size:14px;float:right;margin-left:7px'><i class='fa fa-plus'></i> Create New</button><button class='bts' 
		onclick=\"popupload('accounts/utilities.php?upload')\" style='padding:5px;font-size:14px;float:right;margin-left:7px'><i class='fa fa-upload'></i> Bulk Upload</button>":"";
		
		$apply.= ($ftc==2 && count($apn)>1) ? " <button class='bts' style='float:right;padding:5px;font-size:14px;' onclick=\"popupload('accounts/utilities.php?bapprove=".implode("-",$apn)."')\">
		<i class='bi-list-check'></i> Bulk Approval</button>":"";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:10px 5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970;'>$backbtn Vendor/Utility Payments $apply</h3><hr>
				<p><select style='padding:5px;width:110px;' onchange=\"loadpage('accounts/utilities.php?view='+this.value)\">$opts</select>
				<select style='padding:5px;width:110px;' onchange=\"loadpage('accounts/utilities.php?view=$ftc&mon='+this.value)\">$mons</select>
				<input type='search' style='float:right;padding:4px 6px;width:170px;font-size:15px;' placeholder='&#xf002; Search' value=\"".prepare($str)."\"
				onsearch=\"loadpage('accounts/utilities.php?view=$ftc&str='+cleanstr(this.value))\">
				$bin</p><hr><div class='row'> $divs </div>
			</div>
		</div>";
		savelog($sid,"Viewed vendor payments");
	}
	
	# bulk Approval
	if(isset($_GET["bapprove"])){
		$ids = explode("-",trim($_GET["bapprove"])); $trs="";
		foreach($ids as $id){ $all[]="'$id'"; }
		
		$sql = $db->query(3,"SELECT *FROM `utilities$cid` WHERE `id` IN (".implode(",",$all).")");
		foreach($sql as $row){
			$id=$row["id"]; $sum=($row["approved"]) ? $row["approved"]:$row["cost"]; $sta=$row["status"]+1; $dto=json_decode($row["recipient"],1)["tname"]; $lis="";
			foreach(json_decode($row["item_description"],1) as $one){ $lis.= prepare($one["item"]); }
			$trs.= "<tr valign='top'><td>$lis</td><td>$dto</td><td><input type='number' name='appreq[$id]' value='$sum' style='padding:4px;width:100px' required></td>
			<td style='text-align:right'><input type='checkbox' name='utilapp[]' value='$id-$sta'></td></tr>";
		}
		
		echo "<div style='width:100%;padding:8px'>
			<h3 style='text-align:center;font-size:23px;'>Approve Utility Expenses</h3>
			<form method='post' id='utfom' onsubmit=\"bulkapprove(event)\">
				<table style='width:100%;font-size:15px' cellpadding='5' class='table-striped'> 
					<tr style='font-weight:bold;background:#e6e6fa'><td>Item Description</td><td>Recipient</td><td>Total Cost</td><td></td></tr> $trs
					<tr style='background:#fff'><td colspan='2'><br><div style='width:170px;border:1px solid #dcdcdc;height:35px;'><input type='text' name='otpv' 
					style='border:0px;padding:4px;width:80px;outline:none' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
					<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
					<td colspan='2'><br><button class='btnn' style='float:right'><i class='bi-check2-circle'></i> Proceed</button></td></tr>
				</table>
			</form><br>
		</div>";
	}
	
	# bulk upload
	if(isset($_GET["upload"])){
		$exclude = array("id","staff","status","time","approvals","approved","recipient","month");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='utilities$cid'");
		$fields = ($res) ? json_decode($res[0]['fields'],1):[]; $tds=$ths=""; $len=65;
		if(!$res){
			foreach($db->tableFields(3,"utilities$cid") as $col){ $fields[$col]="text"; }
		}
		
		foreach(["recipient_mpesa_number","mpesa_name","journal_account"] as $col){ $fields[$col]="text"; }
		foreach($fields as $col=>$dtp){
			if(!in_array($col,$exclude) && !in_array($dtp,["image","pdf","doc"])){
				$tds.= "<td>".str_replace("_"," ",ucfirst($col))."</td>"; $len++; $all[]=$col;
			}
		}
		
		if(count($fields)){
			for($i=65; $i<$len; $i++){ $ths.="<td>".chr($i)."</td>"; }
		}
		
		echo "<div style='padding:10px;max-width:550px;margin:0 auto;min-width:500px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Import Utility Payments</h3><br>
			<p style='font-size:14px'>Your Excel file <b>MUST</b> be formatted as below. <b>Note:</b> If your file has date, format it as <b>".date('d-m-Y')."</b><p> 
			<div style='width:100%;overflow:auto'>
				<table style='width:100%;font-size:13px;font-weight:bold;color:#4682b4' class='table-bordered' cellpadding='5'>
					<tr style='background:#f0f0f0;text-align:center;'>$ths</tr> <tr valign='top' style='color:#191970'>$tds</tr>
				</table>
			</div><br>
			<form method='post' id='ufom' onsubmit='importutil(event)'>
				<input type='hidden' name='importcols' value='".json_encode($all,1)."'>
				<table style='width:100%' cellpadding='5'>
					<tr><td colspan='2'>Select Your Filled Excel File<br><input type='file' id='cxls' name='cxls' accept='.csv,.xls,.xlsx' required><hr></td></tr>
					<tr><td><div style='width:170px;border:1px solid #dcdcdc;height:35px;'><input type='text' name='otpv' 
					style='border:0px;padding:4px;width:80px;outline:none' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
					<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
					<td><button class='btnn' style='float:right'><i class='fa fa-upload'></i> Upload</button></td></tr>
				</table>
			</form><br>
		</div><br>";
	}
	
	# create/edit utility expense
	if(isset($_GET['reqid'])){
		$rid = trim($_GET['reqid']);
		$dmd = (isset($_GET["dmd"])) ? trim($_GET["dmd"]):"b2c";
		$nxt = (isset($_GET['approve'])) ? trim($_GET['approve']):0;
		$me = staffInfo($sid); $perms = getroles(explode(",",$me['roles'])); 
		$access=$me['access_level']; $rtbl="utilities$cid";
		$cnf = json_decode($me["config"],1); $fee=0;
		
		$exclude = array("id","staff","branch","approvals","approved","month","status","time","item_description","cost","recipient");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$rtbl'");
		$def = array("id"=>"number","staff"=>"number","branch"=>"number","item_description"=>"textarea","cost"=>"number","approvals"=>"textarea",
		"approved"=>"text","recipient"=>"text","month"=>"number","status"=>"number","time"=>"number");
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$defv = array("id"=>$rid,"month"=>strtotime(date('Y-M')),"status"=>0,"time"=>time(),"staff"=>$sid,"approved"=>0);
		foreach(array_keys($fields) as $fld){
			$dvals[$fld]=(isset($defv[$fld])) ? $defv[$fld]:""; 
		}
	
		if($rid){
			$row = $db->query(3,"SELECT *FROM `$rtbl` WHERE `id`='$rid'"); $dvals=$row[0];
		}
		
		$state = $dvals['status']; $his=$trs=$rts=$dto=$dname=$fon=$pls=$payb=$accno=""; 
		$cond = ($nxt>0 && in_array("approve vendor payment",$perms)) ? 1:0;
		$view = ($state==0 && $nxt==0) ? 0:1; $acc=0;
		$bks = "<option value='0'>-- Select Account --</option>";
		$withdraw = (defined("WALLET_WITHDRAW_VIA_UTILITY")) ? WALLET_WITHDRAW_VIA_UTILITY:0;
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field)); $add=($view) ? "disabled":"";
				if($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts=$drp="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":""; 
						if($cnd){ $drp=prepare(ucwords($drop)); }
						$opts.="<option value='$drop'>".prepare(ucwords($drop))."</option>";
					}
					$lis[]= ($view) ? "<p><i>$fname</i><br>$drp</p>":"<p>$fname<br><select name='$field' style='padding:7px' $add>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col]));
						$opts.="<option value='$val'></option>";
					}
					
					$ran = rand(12345678,87654321);
					$lis[] = ($view) ? "<p><i>$fname</i><br>".prepare($dvals[$field])."</p>":
					"<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='padding:5px' name='$field' list='$ran' autocomplete='off' value='".prepare($dvals[$field])."' $add required></p>";
				}
				elseif($dtype=="textarea"){
					$lis[] = ($view) ? "<p><i>$fname</i><br>".prepare($dvals[$field])."</p>":
					"<p>$fname<br><textarea class='mssg' name='$field' $add required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{ 
					$val = prepare(ucfirst($dvals[$field]));
					$lis[] = ($view) ? "<p><i>$fname</i><br>$val</p>":"<p>$fname<br><input type='$dtype' style='padding:5px' value=\"$val\" name='$field' $add required></p>";
				}
			}
			else{ $his.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		$set = ($qri) ? json_decode($qri[0]['value'],1)["Transactional"]:"Clients Wallet"; $wcol=($qri) ? "id":"account";
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`type`='expense' OR `$wcol`='$set' OR `type`='liability') ORDER BY `account` ASC");
		foreach($qri as $row){
			if($row[$wcol]==$set && $withdraw){
				if($db->istable(3,"wallets$cid")){
					$sql = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `balance`>0"); 
					if($sql){
						$ids=$cnames=$lst=[];
						foreach($sql as $rw){
							if($rw["type"]=="client"){ $ids[$rw["id"]] = "`id`='".$rw['client']."'"; $lst[$rw['id']]=$rw['client']; }
							else{
								$qrc = $db->query(2,"SELECT `name` FROM `org$cid"."_staff` WHERE `status`='0' AND `id`='".$rw["client"]."'");
								if($qrc){ $accs[$row['id'].":".$rw["id"]]="Wallet - ".prepare(ucwords($qrc[0]["name"])); }
							}
						}
						
						if($ids){
							$qrc = $db->query(2,"SELECT `name`,`id` FROM `org$cid"."_clients` WHERE `status`<2 AND (".implode(" OR ",$ids).") ORDER BY `name` ASC");
							if($qrc){
								foreach($qrc as $rw){ $cnames[$rw['id']]=prepare(ucwords($rw["name"])); }
								foreach($lst as $id=>$uc){
									if(isset($cnames[$uc])){ $accs[$row['id'].":$id"]="Wallet - $cnames[$uc]"; }
								}
							}
						}
					}
				}
			}
			else{ $accs[$row['id']]=prepare(ucfirst($row['account'])); }
		}
		
		$dlis = array("b2c"=>"Mpesa B2C","b2b"=>"Paybill B2B","till"=>"BuyGoods (Till)");
		if($dvals['recipient']){
			$sto = json_decode($dvals["recipient"],1); $fee=(isset($sto["fee"])) ? $sto["fee"]:0; $acc=$sto['book'];
			$mode = (isset($sto["mode"])) ? $sto["mode"]:"b2c"; $dmd=(!isset($_GET["dmd"])) ? $mode:$dmd;
			if($mode=="b2c"){
				$dto = $fon="0".$sto["phone"]; $dname=(isset($sto["tname"])) ? prepare(ucwords($sto["tname"])):"";
				$dto.= ($sto['name']) ? " - ".prepare(ucwords($sto['name'])):""; $dto.=($dname && !$sto['name']) ? " - $dname":"";
			}
			else{
				$payb = (isset($sto['tillno'])) ? $sto["tillno"]:$sto["paybill"]; 
				$dto = ($mode=="b2b") ? "Paybill $payb":"TillNo $payb"; $dname=$accno=(isset($sto["tname"])) ? prepare(ucwords($sto["tname"])):""; 
				$sym = ($mode=="till") ? " - $dname":" Acc/No: $accno"; $dto.= ($sto['name']) ? " - ".prepare(ucwords($sto['name'])):""; 
				$dto.= ($dname && !$sto['name']) ? " $sym":"";
			}
		}
		
		if($view){ $lis[] = "<p><i>Recipient Details</i><br><b>$dto</b></p>";  }
		else{
			foreach($dlis as $key=>$val){
				$cnd = ($key==$dmd) ? "selected":"";
				$pls.= "<option value='$key' $cnd>$val</option>";
			}
			
			$lis[] = "<p>Payment Method<br><select style='width:150px' onchange=\"fetchpage('accounts/utilities.php?reqid=$rid&dmd='+this.value)\" name='pmode'>$pls</select></p>";
			if($dmd=="b2c"){
				$lis[] = "<p>Recipient Mpesa Number & Name<br><input type='number' name='recipient' style='max-width:150px;width:100%;padding:4px 6px' placeholder='Number' 
				value='$fon' required>&nbsp; <input type='text' name='recname' style='width:200px;padding:4px 6px' placeholder='Name' value='$dname' required></p>";
			}
			elseif($dmd=="b2b"){
				$lis[] = "<p>Paybill & Account Number<br><input type='number' name='recipient' style='max-width:150px;width:100%;padding:4px 6px' placeholder='Paybill No' 
				value='$payb' required>&nbsp; <input type='text' name='recname' style='width:180px;padding:4px 6px' placeholder='Acc Number' value='$accno' required></p>";
			}
			else{
				$lis[] = "<p>Till Number & Name<br><input type='number' name='recipient' style='max-width:150px;width:100%;padding:4px 6px' placeholder='Till Number' 
				value='$payb' required>&nbsp; <input type='text' name='recname' style='width:180px;padding:4px 6px' placeholder='Till Name' value='$dname' required></p>";
			}
		}
		
		if(count($lis)){
			$half = ceil(count($lis)/2); $chunk = array_chunk($lis,$half);
			$trs=$rts=(count($lis)>1) ? "<tr valign='top'><td style='width:60%'>".implode("",$chunk[0])."</td><td>".implode("",$chunk[1])."</td></tr>":
			"<tr valign='top'><td>".implode("",$lis)."</td></tr>";
		}
		
		if(in_array($access,["hq","region"])){
			$brns = "<option value='0'>Head Office</option>"; $mbr=($dvals['branch']) ? $dvals['branch']:$me['branch'];
			$lod = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $lod");
			if($res){
				foreach($res as $row){
					$bid=$row['id']; $cnd = ($bid==$mbr) ? "selected":"";
					$brns.="<option value='$bid' $cnd>".prepare(ucwords($row['branch']))." branch</option>";
				}
			}
			$brans = "<select style='width:180px;padding:5px' name='rbran'>$brns</select>";
		}
		else{ $brans = "<input type='hidden' name='rbran' value='".$me['branch']."'>"; }
		
		$twd = (count($lis)>1) ? "100%":""; 
		$otpr = ($view==0 or ($state<10 && $cond)) ? "<div style='width:170px;border:1px solid #dcdcdc;height:35px;float:right'><input type='text' name='otpv' 
		style='border:0px;padding:4px;width:80px;outline:none' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' autocomplete='off' required>
		<a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div>":"";
		
		if($view==0){
			if($rid){
				$desc = json_decode($dvals['item_description'],1); $desc=(is_array($desc)) ? $desc:[]; $ltrs="";
				$btx = (in_array("edit vendor payment",$perms)) ? "<tr><td>$otpr</td><td style='text-align:right;width:100px'><button class='btnn' 
				style='min-width:60px;padding:5px'><i class='fa fa-refresh'></i> Update</button></td></tr>":"";
				
				foreach($desc as $no=>$des){
					$item=prepare(ucfirst($des['item'])); $qty=$des['qty']; $cat=$des['type']; $cost=$des['cost']; 
					$wd = ($no) ? "80px":"100%"; $ran=rand(12345678,87654321); 
					$css = ($cat=="cash") ? "outline:none":"outline:none;cursor:not-allowed";
					$del = ($no) ? "<i class='bi-x-lg' style='font-size:18px;cursor:pointer;float:right;color:#ff4500' onclick=\"$('#$ran').remove()\" title='Remove $item'></i>":"";
					
					foreach($accs as $pos=>$name){
						if(is_array($acc)){
							$dnf = array_keys($acc); 
							$bac = (isset($dnf[$no])) ? explode("-",explode(":",$dnf[$no])[0])[0]:0;
						}
						else{ $bac=$acc; }
						$cnd = ($pos==$bac) ? "selected":""; $bks.= "<option value='$pos' $cnd>$name</option>";
					}
		
					$ltrs.= "<tr id='$ran'><td><input type='text' name='item[$no]' value=\"$item\" style='width:100%;border:0px;padding:2px;outline:none' required></td>
					<td style='width:180px'><input type='hidden' name='qnty[$no]' value='$qty'><select style='width:100%;border:0px;outline:none' name='baccs[$no]' required>$bks</select></td>
					<td style='width:110px'><input type='number' id='c$ran' value='$cost' name='prices[$no]' style='width:$wd;border:0px;padding:2px;$css' required> $del</td></tr>";
				}
			}
			else{
				$btx = (in_array("create vendor payment",$perms)) ? "<tr><td>$otpr</td><td style='text-align:right;width:100px'><button class='btnn' 
				style='min-width:60px;padding:5px'><i class='fa fa-gift'></i> Request</button></td></tr>":"";
				foreach($accs as $key=>$name){ $bks.= "<option value='$key'>$name</option>"; }
				
				$ltrs = "<tr><td><input type='text' name='item[0]' style='width:100%;border:0px;padding:2px;outline:none' required></td>
				<td style='width:180px'><input type='hidden' name='qnty[0]' value='1'><select style='width:100%;border:0px;font-size:15px' name='baccs[0]' required>$bks</select></td>
				<td style='width:100px'><input type='number' id='c1234' name='prices[0]' style='width:100%;border:0px;padding:2px;outline:none' required></td></tr>";
			}
			
			$data = "<form method='post' id='rfom' onsubmit=\"savereqn(event,'$rid')\">
				<input type='hidden' name='formkeys' value='".json_encode(array_keys($fields),1)."'> $his
				<table style='width:$twd' cellpadding='5'>$trs</table>
				<table style='width:100%;font-size:15px' cellpadding='4' class='table-bordered mtbl'>
					<caption>$brans <a href='javascript:void(0)' style='float:right' onclick='addrow()'><i class='fa fa-plus'></i> Add Row</a></caption>
					<tr style='color:#191970;color:#191970;font-weight:bold;background:#f0f0f0'><td>Item/Service description</td><td>Journal Account</td><td>Cost</td></tr> $ltrs
				</table>
				<table style='width:100%'> $btx </table><br><input type='hidden' id='bks' value=\"$bks\">
			</form>";
		}
		else{
			$qry = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='utilities'");
			$app = ($qry) ? json_decode($qry[0]['levels'],1):[]; $all=count($app); $rem=0;
			$desc = json_decode($dvals['item_description'],1); $desc=(is_array($desc)) ? $desc:[];
			$appr=json_decode($dvals['approvals'],1); $css="style='background:#f8f8ff;color:#4682b4'"; 
			
			$trs=$abk=""; $no=$jcl=0; $cles=[];
			foreach($desc as $key=>$des){
				$item=prepare(ucfirst($des['item'])); $qty=$des['qty']; 
				$cat=$des['type']; $cost=$des['cost']; $no++; $tqn=$tcst=$bks="";
				
				if($cat=="cashless"){ $cles[]=$key; }
				if($state>10){
					if(is_array($acc)){
						$dnf = array_keys($acc); $jcl++;
						$abk = (isset($dnf[$key])) ? $accs[explode("-",explode(":",$dnf[$key])[0])[0]]:"";
					}
					else{ $abk=$accs[$acc]; $jcl++; }
				}
				
				if($all){
					if(is_array($appr)){
						$arr = (array_key_exists($key,$appr)) ? $appr[$key]:[]; $rem = ($state>15) ? count($arr):$all; $prv=0;
						$val = ($cat=="cashless") ? "---":number_format($cost);
						$tqn = "<td>$qty</td>"; $tcst = "<td>$val</td>";
						
						for($i=0; $i<$rem; $i++){ 
							if(array_key_exists($i,array_keys($arr))){
								$svd = array_values($arr)[$i]; $tapp=$prv= $svd[0]*$svd[1]; $cost=($tapp) ? $svd[1]:0;
								$qvl = ($svd[0]) ? $svd[0]:"<i style='color:#DC143C'>Declined</i>";
								$val = ($cat=="cashless") ? "---":number_format($cost);
								$tqn .= "<td>$qvl</td>"; $tcst .= "<td>$val</td>";
							}
							else{
								$tapp = ($prv) ? $prv:0;
								$trx = ($state<10) ? "<i style='color:#9400D3'>pending</i>":"<i style='color:#DC143C'>Declined</i>";
								$in1 = (($i+1)==$nxt && $state<10) ? "<input type='number' name='qnty[$key]' value='$qty' style='width:55px;padding:0px 3px' required>":$trx;
								$in2 = (($i+1)==$nxt && $state<10) ? "<input type='number' name='price[$key]' value='$cost' style='width:75px;padding:0px 3px' required>":$trx;
								$qvl = ($i==$nxt) ? $qty:$in1;
								$cvl = ($i==$nxt) ? number_format($cost):$in2;
								$val = ($cat=="cashless") ? "---":$cvl; 
								$tqn .= "<td>$qvl</td>"; $tcst .= "<td>$val</td>";
							}
						}
					}
					else{
						$val = ($cat=="cashless") ? "---":number_format($cost);
						$tqn = "<td>$qty</td>"; $tcst = "<td>$val</td>"; $tapp = 0; 
						for($i=1; $i<=$all; $i++){
							$trx = ($state<10) ? "<i style='color:#9400D3'>pending</i>":"<i style='color:#DC143C'>Declined</i>";
							$in1 = ($i==$nxt && $state<10) ? "<input type='number' name='qnty[$key]' value='$qty' style='width:55px;padding:0px 3px' required>":$trx;
							$in2 = ($i==$nxt && $state<10) ? "<input type='number' name='price[$key]' value='$cost' style='width:75px;padding:0px 3px' required>":$trx;
							$val = ($cat=="cashless") ? "---":$in2;
							$tqn .= ($state>10 && $i>1) ? "<td>---</td>":"<td>$in1</td>"; 
							$tcst .= ($state>10 && $i>1) ? "<td>---</td>":"<td>$val</td>";
						}
					}
				}
				else{
					$val = ($cat=="cashless") ? "---":number_format($cost);
					$tqn = "<td>$qty</td><td>$qty</td>"; $tcst = "<td>$val</td><td>$val</td>";
					$tapp = $cost*$qty; $col1=$col2=2;
				}
				
				$tdx = ($abk or $jcl) ? "<td>$abk</td>":"";
				$trs.= "<tr valign='top'><td style='width:35px'>$no</td><td>$item</td>$tdx $tqn $tcst <td style='text-align:right'><b>".number_format($tapp)."</b></td></tr>";
			}
			
			$trh = "<td $css><b>Request</b></td>"; $dcn=($jcl) ? 3:2;
			$tdh = ($jcl) ? "<td $css><b>Journal Account</b></td>":""; 
			$all = ($state>10 && $rem>0) ? $rem:$all;
			if($all){
				$col1=$col2=$all+1;
				foreach($app as $key=>$one){
					$trh.="<td $css><b>".ucfirst($one)."</b></td>";
					if($key==$all){ break; }
				}
			}
			else{ $trh = "<td $css><b>Request</b></td><td $css><b>Accepted</b></td>"; $col1=$col2=2; }
			
			$user = staffInfo($dvals['staff']); $from=prepare(ucwords($user['name'])); $cols=2+$col1+$col2; $cols+=($jcl) ? 1:0;
			$btn = ($state<10 && $cond) ? "<button class='btnn' style='min-width:60px;padding:5px'><i class='fa fa-check'></i> Approve</button>":"";
			$btx = ($state<10 && $cond) ? "<button type='reset' onclick=\"cancelreq(event,'$rid')\" class='btnn' style='min-width:60px;padding:5px;background:#DC143C'>
			<i class='fa fa-times'></i> Decline</button>":"";
			
			$txt = ($cond) ? "<span style='font-size:14px'><b>Note:</b> To decline an Item, set quantity to 0</span>":""; $bname="Head Office";
			if($dvals['branch']){ $qri = $db->query(1,"SELECT *FROM `branches` WHERE `id`='".$dvals['branch']."'"); $bname=prepare(ucwords($qri[0]['branch']))." Branch"; }
			$charge = ($fee) ? "Charges: Ksh ".fnum($fee):""; $tsum=($dvals['approved']>0) ? $dvals['approved']-$fee:0;
			
			$data = "<table style='width:$twd' cellpadding='5'>$rts</table>
			<p style='margin-bottom:5px;font-size:15px'>From <b>$from</b>, <b>$bname</b> on <b>".date("M d,Y - h:i a",$dvals['time'])."</b></p>
			<form method='post' id='rfom' onsubmit='approvereqn(event)'>
				<input type='hidden' value='".json_encode($cles,1)."' name='tcls'>
				<input type='hidden' value='$nxt' name='appreq'> <input type='hidden' value='$rid' name='reqnid'>
				<table style='width:100%;font-size:15px' cellpadding='4' class='table-bordered'>
					<tr style='color:#191970;color:#191970;font-weight:bold;background:#f0f0f0'><td colspan='$dcn'>Item details</td>
					<td colspan='$col1' style='text-align:center'>Qty Approvals</td><td colspan='$col2' style='text-align:center'>Cost Approvals</td>
					<td style='text-align:right'>Totals</td></tr>
					<tr style='font-size:13px'><td colspan='2'></td>$tdh $trh $trh<td></td> $trs
					<tr style='color:#191970;font-weight:bold;background:#f0f0f0'><td colspan='$cols' style='text-align:right;font-size:14px'>$charge</td>
					<td style='text-align:right'>".fnum($tsum)."</td></tr>
				</table><br>
				<table style='width:100%'><tr><td>$txt</td><td>$otpr</td><td style='text-align:right;width:190px'>$btx $btn</td></tr></table>
			</form><br>";
		}
		
		$ttx = ($rid) ? prenum($rid):"Form";
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto;padding:5px'>
			<h3 style='font-size:22px;text-align:center;color:#191970;padding-top:10px'><span style='float:left'>$backbtn</span>
			<u>Vendor Payment $ttx</u></h3><br>
			<div style='min-width:700px;padding:10px'> $data </div>
		</div>";
	}

	ob_end_flush();
?>

<script>
	
	function approvereqn(e){
		e.preventDefault();
		if(confirm("Continue to approve vendor payment?")){
			var data = $("#rfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/utilities.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success!"); loadpage("accounts/utilities.php?view");
				}
				else{ alert(res); }
			});
		}
	}
	
	function bulkapprove(e){
		e.preventDefault();
		if(!checkboxes("utilapp[]")){ toast("Select atleast one Item!"); }
		else{
			if(confirm("Continue to approve selected Items?")){
				var data = $("#utfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/utilities.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success!"); closepop(); fetchpage("accounts/utilities.php?view");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function importutil(e){
		e.preventDefault();
		var xls = _("cxls").files[0];
		if(xls!=null){
			if(confirm("Extract utility payments from selected File?")){
				var data = new FormData(_("ufom")); data.append("uxls",xls);
				var x = new XMLHttpRequest(); progress("Processing...please wait");
				x.onreadystatechange=function(){
					if(x.status==200 && x.readyState==4){
						var res=x.responseText; progress(); 
						if(res.trim().split(":")[0]=="success"){
							toast("Upload Successful!"); closepop(); loadpage("accounts/utilities.php?view=2"); 
						}
						else{ alert(res.trim()); }
					}
				}
				x.open("post",path()+"dbsave/utilities.php",true);
				x.send(data);
			}
		}
		else{ alert("Please select Excel File first"); }
	}
	
	function cancelreq(e,rid){
		e.preventDefault();
		var otp = _("otp").value.trim();
		
		if(otp==""){
			toast("Enter OTP code first!"); _("otp").focus();
		}
		else{
			if(confirm("Sure to decline vendor payment?")){
				$.ajax({
					method:"post",url:path()+"dbsave/utilities.php",data:{declinereqn:rid,otpv:otp},
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Success!"); loadpage("accounts/utilities.php?view");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function savereqn(e,rid){
		e.preventDefault();
		var btx = (rid>0) ? "update Vendor Payment?":"create new Vendor Payment?";
		if(confirm("Continue to "+btx)){
			var data=$("#rfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/utilities.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Success!"); loadpage("accounts/utilities.php?reqid="+res.trim().split(":")[1]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function addrow(){
		var did=Date.now(),lis=$("#selecs").val(), opts=$("#bks").val();
		$(".mtbl").append("<tr id='"+did+"'><td><input type='text' name='item["+did+"]' style='width:100%;border:0px;padding:2px;outline:none' required></td>"+
		"<td><input type='hidden' name='qnty["+did+"]' value='1'><select name='baccs["+did+"]' style='width:100%;border:0px;outline:none;font-size:15px'>"+opts+"</select></td>"+
		"<td style='width:110px'><input type='number' id='c"+did+"' name='prices["+did+"]' style='width:80px;border:0px;padding:2px;outline:none' required>"+
		"<i class='bi-x-lg' style='font-size:18px;cursor:pointer;float:right;color:#ff4500' onclick=\"$('#"+did+"').remove()\" title='Remove row'></i></td></tr>");
	}
	
	function getqnty(id,tp){
		if(tp=="cash"){
			$("#"+id).val(""); $("#"+id).prop("readonly",false); $("#"+id).css("cursor","text");
		}
		else{ 
			$("#"+id).val(0); $("#"+id).prop("readonly",true); $("#"+id).css("cursor","not-allowed");
		}
	}
	
</script>