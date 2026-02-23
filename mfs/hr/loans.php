<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	
	# loan action options
	if(isset($_GET["loanact"])){
		$lid = trim($_GET["loanact"]);
		$uid = trim($_GET["id"]);
		$me = staffInfo($sid); $idno=trim($_GET["id"]);
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		
		$qry = $db->query(2,"SELECT `branch`,`phone`,`id` FROM `staff_loans$cid` WHERE `loan`='$lid'")[0];
		$bid = $qry['branch']; $fon=$qry["phone"]; $tid=$qry["id"]; $lnst=0;
		$cond = ($bid) ? "`id`='$bid'":"NOT `paybill`='0'"; $accno="SLN$lid"; $tds=[]; $avs=0;
		$payb = $db->query(1,"SELECT `paybill` FROM `branches` WHERE $cond")[0]['paybill'];
		$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
		$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
		
		$css = "font-family:signika negative;color:#191970;text-align:center;font-size:14px;background:#f0f0f0;cursor:pointer;width:100%;padding:8px 10px;border:0px";
		if($tid && $db->istable(3,"translogs$cid")){ $avs = $db->query(3,"SELECT `ref` FROM `translogs$cid` WHERE `ref`='TIDS$tid'"); }
		
		if($pkey){ $tds[] = "<td><button style='$css;color:#4682b4' onclick=\"popupload('payments.php?reqpay=$accno&payb=$payb&fon=$fon')\"><i class='bi-cash-coin'></i> Make Payment</button></td>"; }
		if(in_array("edit staff loan",$perms)){ $tds[] = "<td><button style='$css;color:blue' onclick=\"popupload('hr/loans.php?editloan=$lid')\"><i class='bi-pencil-square'></i> Edit Details</button></td>"; }
		if(in_array("update running loan amount",$perms)){ $tds[] = "<td><button style='$css;color:#008fff' onclick=\"popupload('hr/loans.php?lncharge=$lid')\"><i class='bi-cash-coin'></i> Add Charges</button></td>"; }
		if(in_array("waive loan interest",$perms)){ $tds[] = "<td><button style='$css;color:green' onclick=\"waiveint('$lid')\"><i class='bi-calculator'></i> Waive Interest</button></td>"; }
		if((in_array("generate loan statement",$perms) or $sid==$uid) && $avs){ $tds[] = "<td><button style='$css;color:#008080' onclick=\"printdoc('reports.php?vtp=loanst&mn=$lid&yr=1&ltp=stf','pay')\">
		<i class='bi-list-stars'></i> Loan Statement</button></td>"; }
		if(in_array("delete staff loan",$perms)){ $tds[] = "<td><button style='$css;color:#ff4500' onclick=\"deleteloan('$lid','$idno')\"><i class='bi-trash'></i> Delete Loan</button></td>"; }
		
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
	
	# view loans
	if(isset($_GET['manage'])){
		$view = trim($_GET['manage']);
		$vtp = ($view) ? $view:0;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? ltrim(clean($_GET['str']),"0"):null;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$day = (isset($_GET['day'])) ? trim($_GET['day']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$prod = (isset($_GET['prd'])) ? trim($_GET['prd']):0;
		$tdy = strtotime('Today'); $mons=[];
		
		$ltbl = "staff_loans$cid"; $perpage=30;
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage);
		
		$bnames = [0=>"Corporate"];
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); 
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org$cid"."_staff"; $staff[0]="System";
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
	
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND `branch`='".$me['branch']."'";
		$ftc = ($vtp==2) ? "AND `expiry`<$tdy":"";
		$cond = $defc = ($vtp==1) ? "(balance+penalty)='0'":"(balance+penalty)>0 $ftc";
		$cond = (in_array($vtp,[3])) ? "NOT `loan`='0'":"NOT `loan`='0' AND $cond";
		$cond.= ($me['access_level']=="portfolio") ? " AND `stid`='$sid'":$show;
		$cond.= ($str) ? " AND ($ltbl.staff LIKE '%$str%' OR `phone` LIKE '%$str%')":"";
		
		$qri = $db->query(2,"SELECT from_unixtime(disbursement,'%Y-%b') AS mon FROM `$ltbl` WHERE $defc AND `disbursement`>0 AND NOT `loan`='0' GROUP BY mon");
		if($qri){
			foreach($qri as $row){ $mons[] = strtotime($row['mon']); }
		}
		
		$monrange = monrange(date('m'),date("Y"));
		$from = (count($mons)) ? min($mons):$monrange[0]; $to = (count($mons)) ? max($mons):$monrange[1]+86399; 
		$mto = ($mon) ? monrange(date('m',$mon),date("Y",$mon))[1]+86399:time();
		$fro = ($day) ? $day:$mon; $dto = ($day) ? $day+86399:$mto;
		$cond.= " AND `disbursement` BETWEEN $fro AND $dto";
		$cond.= ($prod) ? " AND `loan_product`='$prod'":"";
	
		$no=($perpage*$page)-$perpage; $trs=$offs=$brans="";
		if($db->istable(2,$ltbl)){
			$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY balance DESC $lim");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $amnt=number_format($row['amount']); $bal=$row['balance']+$row['penalty']; $paid=$row['paid'];
					$name=prepare(ucwords($row['staff'])); $fon=$row['phone']; $uid=$row['stid']; $lid=$row['loan'];
					$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$row['disbursement']); $exp=date("d-m-Y",$row[$st]); 
					$tpy=number_format($bal+$paid); $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100;
					
					$qri = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `linked`='$lid' GROUP BY payid ORDER BY `time` DESC LIMIT 1");
					$last = ($qri && $paid>0) ? intval($qri[0]['tamnt']):0; 
					
					$color = ($tdy<=$row['expiry']) ? "green":"#DC143C"; $color=($vtp==1) ? "#7B68EE":$color; 
					$perc = ($vtp==1) ? "":"<td style='color:#9400D3;font-size:13px;'><b>$prc%</b></td>";
					
					$trs.= "<tr onclick=\"loadpage('hr/staff.php?vstid=$uid&vtp=lns')\" valign='top'><td>$no</td><td>$name</td><td>0$fon</td><td>$disb</td><td>$amnt</td><td>$tpy</td>
					<td>".number_format($paid)."</td><td>$last</td>$perc<td>".number_format($bal)."</td><td style='color:$color'><i class='fa fa-circle'></i> $exp</td></tr>";
				}
			}
		}
		
		$mns ="<option value='0'>-- Month --</option>"; arsort($mons);
		foreach($mons as $mn){
			$cnd = ($mn==$mon) ? "selected":"";
			$mns.= "<option value='$mn' $cnd>".date('F Y',$mn)."</option>";
		}
		
		$prods = "<option value='0'>-- Loan Product --</option>";
		$res = $db->query(2,"SELECT DISTINCT `loan_product` FROM `$ltbl` WHERE $defc");
		if($res){
			$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='staff'");
			foreach($qry as $row){
				$pnames[$row['id']]=prepare(ucfirst($row['product']));
			}
			
			foreach($res as $row){
				$prd=$row['loan_product']; $cnd=($prd==$prod) ? "selected":"";
				$prods.= "<option value='$prd' $cnd>".$pnames[$prd]."</option>";
			}
		}
		
		if($access=="hq"){
			$brn = "<option value='0'>Corporate</option>";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='width:150px' onchange=\"loadpage('hr/loans.php?manage=$vtp&prd=$prod&bran='+this.value.trim())\">$brn</select>";
		}
		
		$src = base64_encode($cond);
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM($ltbl.amount) AS tdisb,SUM(paid) AS tpy,SUM(balance) AS tbal FROM `$ltbl` WHERE $cond"); 
		$totals = ($sql) ? intval($sql[0]['total']):0; $disb=intval($sql[0]['tdisb']); $tpy=intval($sql[0]['tpy']); $tbal=intval($sql[0]['tbal']);
		$pbtn = ($totals) ? genrepDiv("sloans.php?src=$src&v=$vtp&br=$bran&mn=$mon&dy=$day&prd=$prod",'right'):"";
		$stl = ($view==1) ? "Completed":"Maturity"; $prctd=($vtp==1) ? "":"<td>Percent</td>";
		
		if($trs){
			$tpr=($tbal) ? round($tpy/($tbal+$tpy)*100,2):100; $perc=($vtp==1) ? "":"<td>$tpr%</td>";
			$trs.="<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px'><td colspan='4' style='text-align:right'>Totals</td>
			<td>".number_format($disb)."</td><td>".number_format($tpy+$tbal)."</td><td>".number_format($tpy)."</td><td></td>$perc<td>".number_format($tbal)."</td><td></td></tr>";
		}
		
		$titles = array("Current Staff Loans","Completed Staff Loans","Overdue Staff Loans","All Staff Loans"); $opts="";
		foreach($titles as $key=>$ttl){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>$ttl</option>";
		}
		
		echo "<div class='container cardv' style='max-width:1300px;min-height:400px;overflow:auto;'>
			<div style='padding:7px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $titles[$vtp]</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped stbl' style='width:100%;min-width:800px' cellpadding='5'>
						<caption style='caption-side:top'> 
							<p><input type='search' style='float:right;width:160px;padding:4px;font-size:15px;'  onkeyup=\"fsearch(event,'hr/loans.php?manage=$vtp&str='+cleanstr(this.value))\"
							onsearch=\"loadpage('hr/loans.php?manage=$vtp&str='+cleanstr(this.value))\" value='".prepare($str)."' placeholder='&#xf002; Search Staff'>
							<select style='width:150px;padding:5px;font-size:15px;' onchange=\"loadpage('hr/loans.php?manage='+this.value)\">$opts</select>
							<select style='width:160px;padding:5px;font-size:15px;' onchange=\"loadpage('hr/loans.php?manage=$vtp&prd='+this.value)\">$prods</select></p>
							<p style='margin:0px'>$pbtn $brans
							<select style='width:160px;' onchange=\"loadpage('hr/loans.php?manage=$vtp&bran=$bran&prd=$prod&mon='+this.value.trim())\">$mns</select></p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-size:15px;font-weight:bold'><td colspan='2'>Staff Name</td>
						<td>Contact</td><td>Disbursement</td><td>Loan</td><td>To-Pay</td><td>Paid</td><td>LastPaid</td>$prctd<td>Balance</td><td>$stl</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"hr/loans.php?manage=$vtp&bran=$bran&prd=$prod&mon=$mon&day=$day&str=".str_replace(" ","+",$str))."
		</div>";
		savelog($sid,"Viewed Staff Loans");
	}
	
	# create template
	if(isset($_GET["apply"])){
		$tid = trim($_GET['apply']);
		$prod = (isset($_GET['prod'])) ? trim($_GET['prod']):0;
		$ltbl = "staff_loans$cid";
		$me = staffInfo($sid);
		
		$exclude = array("id","status","time","loan","staff","balance","expiry","penalty","disbursement","paid","stid","approvals","branch","creator","pref",
		"cuts","branch","processing","payment","loantype");
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$ltbl'");
		$def = ["id"=>"number","stid"=>"number","staff"=>"text","loan_product"=>"text","phone"=>"number","branch"=>"number","amount"=>"number","duration"=>"number",
		"disbursement"=>"number","expiry"=>"number","penalty"=>"number","paid"=>"number","balance"=>"number","loan"=>"text","status"=>"number","approvals"=>"textarea",
		"pref"=>"number","payment"=>"text","processing"=>"textarea","cuts"=>"textarea","loantype"=>"text","creator"=>"number","time"=>"number"];
		
		$fields = ($res) ? json_decode($res[0]['fields'],1):$def;
		$reuse = ($res) ? json_decode($res[0]['reuse'],1):[];
		$dsrc = ($res) ? json_decode($res[0]['datasrc'],1):[];
		
		$accept = array("docx"=>".docx","pdf"=>".pdf","image"=>"image/*");
		$dels = array("balance","expiry","penalty","disbursement","paid");
		foreach($dels as $del){ unset($fields[$del]); }
		
		$defv = array("status"=>0,"time"=>time(),"loan"=>0,"penalty"=>0,"stid"=>$sid,"creator"=>0,"branch"=>$me["branch"],"phone"=>$me["contact"],"staff"=>$me["name"]); 
		foreach(array_keys($fields) as $fld){
			$dvals[$fld] = (isset($defv[$fld])) ? $defv[$fld]:""; 
		}
		
		$setts = ["loansegment"=>1]; $setts['template_phone_include']=0; $pcode="";
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$lis=$min=$max=$infs=$ckoamnt=$ckoto=$bypass_lim=$ofid=""; $done=[];
		if($tid){
			$row = $db->query(2,"SELECT *FROM `$ltbl` WHERE `id`='$tid'"); 
			$dvals=$row[0]; $dvals['tid']=$tid; $pcode=$dvals['payment']; $prod=($prod) ? $prod:$dvals['loan_product'];
			
			if(in_array("image",$fields) or in_array("pdf",$fields) or in_array("docx",$fields)){
				foreach($fields as $col=>$dtp){ 
					if($dtp=="image" or $dtp=="docx" or $dtp=="pdf"){ unset($fields[$col]); }
				}
			}
		}
		
		$durs=$payterms=$ftps=$pays=[];
		if($prod){
			$dvals['loan_product']=$prod; 
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod' AND `category`='staff'");
			$payterms = json_decode($res[0]['payterms'],1); $min=$res[0]['minamount']; $max=$res[0]['maxamount'];
			if($setts['loansegment']){
				$arr = $res[0]['duration']/$res[0]['interest_duration']; $intv=$res[0]['intervals'];
				for($i=1; $i<=$arr; $i++){
					$durs[]=$i*$res[0]['interest_duration'];
				}
			}
			else{ $durs[]=$res[0]['duration']; }
		}
		
		foreach($fields as $field=>$dtype){
			if(!in_array($field,$exclude)){
				$fname=ucwords(str_replace("_"," ",$field));
				if($field=="phone"){
					$fon = $dvals[$field];
					$lis.= ($setts['template_phone_include']) ? "<p>Phone Number<br><input type='number' id='cphone' style='width:100%' value=\"$fon\" name='$field' required></p>":
					"<input type='hidden' name='phone' id='cphone' value='$fon'>";
				}
				elseif($field=="loan_product"){
					$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='staff'");
					$opts = "<option value='0'>-- Loan Product --</option>";
					foreach($res as $row){
						$cnd = ($row['id']==$dvals[$field]) ? "selected":"";
						$opts.= "<option value='".$row['id']."' $cnd>".prepare(ucfirst($row['product']))."</option>";
					}
					$lis.= "<p>Loan Product<br><select name='$field' id='prd' style='width:100%' onchange=\"popupload('hr/loans.php?apply=$tid&prod='+this.value)\">$opts</select></p>";
				}
				elseif($field=="duration"){
					$opts="";
					foreach($durs as $dur){
						$cnd = ($dur==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$dur' $cnd>$dur days</option>";
					}
					
					$opts = ($opts) ? $opts:"<option value='0'> -- Select --</option>";
					$lis.="<p>Repayment Duration<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="select"){
					$drops = array_map("trim",explode(",",explode(":",rtrim($dsrc[$field],","))[1])); $opts="";
					foreach($drops as $drop){
						$cnd = ($drop==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$drop' $cnd>".prepare(ucwords($drop))."</option>";
					}
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":"<p>$fname<br><select name='$field' style='width:100%'>$opts</select></p>";
				}
				elseif($dtype=="link"){
					$src = explode(".",explode(":",$dsrc[$field])[1]); $tbl=$src[0]; $col=$src[1]; $dbname=(substr($tbl,0,3)=="org") ? 2:1;
					$res = $db->query($dbname,"SELECT $col FROM `$tbl` ORDER BY `$col` ASC"); $ran=rand(12345678,87654321); $opts=""; 
					foreach($res as $row){
						$val=prepare(ucfirst($row[$col])); $cnd=($row[$col]==$dvals[$field]) ? "selected":"";
						$opts.="<option value='$val' $cnd></option>";
					}
					
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
					"<p>$fname<br> <datalist id='$ran'>$opts</datalist> <input type='hidden' name='dlinks[$field]' value='$tbl.$col'>
					<input type='text' style='width:100%' name='$field' list='$ran' autocomplete='off' required></p>";
				}
				elseif($dtype=="textarea"){
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
					"<p>$fname<br><textarea class='mssg' name='$field' required>".prepare(ucfirst($dvals[$field]))."</textarea></p>";
				}
				else{
					$inp=(array_key_exists($dtype,$accept))? "file":$dtype; $add=($inp=="file") ? $accept[$dtype]:""; 
					$val=prepare(ucfirst($dvals[$field])); $place=($field=="amount") ? "placeholder='$min - $max'":"";
					$fid = ($field=="phone") ? "id='cphone'":"id='$field'";
					if($inp=="file" && !isset($done[$field])){ $infs.="$field:"; $ftps[$field]=$dtype; }
					
					$lis.= (isset($done[$field])) ? "<input type='hidden' name='$field' value='$done[$field]'>":
					"<p>$fname<br><input type='$inp' $fid style='width:100%' value=\"$val\" accept='$add' name='$field' $place required></p>";
				}
			}
			else{ $lis.="<input type='hidden' name='$field' value='$dvals[$field]'>"; }
		}
		
		if($prod){
			foreach($payterms as $key=>$val){
				if(explode(":",$val)[0]==0 && $isnew){ $pays[]=ucwords(str_replace("_"," ",$key)); }
				if(explode(":",$val)[0]==1){ $pays[]=ucwords(str_replace("_"," ",$key)); }
			}
		}
		
		if(count($pays)){
			$cond = ($me['access_level']=="hq") ? "":"AND `paybill`='".getpaybill($me['branch'])."'";
			$lis.="<p>Payment for ".implode("+",$pays)."<br><select style='width:100%' name='payments'>";
			$res = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `status`='0' $cond");
			if($res){
				foreach($res as $row){
					$code=$row['code']; $acc=$row['account']; $phon=ltrim(ltrim($row['phone'],"0"),"254"); $amnt=number_format($row['amount']);
					$cnd = ($code==$pcode or $idno==$acc or $phon==$fon or $acc==$fon) ? "selected":"";
					$lis.="<option value='$code' $cnd>".prepare(ucwords(strtolower($row['client'])))." - $amnt</option>";
				}
			}
			$lis.= "</select></p>";
		}
		
		$title = ($tid) ? "Edit Loan Application":"Apply for Staff Loan"; 
		echo "<div style='padding:10px;margin:0 auto;max-width:320px'>
			<h3 style='color:#191970;font-size:23px;text-align:center'>$title</h3><br>
			<form method='post' id='tform' onsubmit=\"savetemplate(event)\">
				<input type='hidden' name='formkeys' value='".json_encode($fields,1)."'>
				<input type='hidden' name='id' value='$tid'> <input type='hidden' name='ftypes' value='".json_encode($ftps,1)."'>
				<input type='hidden' id='hasfiles' name='hasfiles' value='".rtrim($infs,":")."'> $lis<br>	
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form>
		</div>";
	}
	
	# view loan applications
	if(isset($_GET["apps"])){
		$view = trim($_GET['apps']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$day = (isset($_GET['day'])) ? clean($_GET['day']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		
		$ltbl = "staff_loans$cid";
		$fields = ($db->istable(2,$ltbl)) ? $db->tableFields(2,$ltbl):[]; $docs=[];
		$exclude = ["id","loan","time","status","payment","staff","stid","phone","duration","disbursement","approvals","creator","branch","pref","cuts","expiry","penalty","paid","balance","loantype"];
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles'])); $perpage=30;
		$lim = getLimit($page,$perpage);
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $brans=[];
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$stbl = "org".$cid."_staff"; $staff=$media=[];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($view==null){
			$check = $db->query(2,"SELECT *FROM `$ltbl` WHERE `status`<9 AND `disbursement`='0'");
			$view = ($check) ? 2:1;
		}
		
		$states = array("All Applications","Disbursed Applications","Undisbursed Applications","Pended Applications","Declined Applications");
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = $defc =($me['access_level']=="portfolio") ? "`stid`='$sid'":$show;
		$cond.= ($str) ? " AND (`staff` LIKE '%$str%' OR `phone` LIKE '$str%')":"";
		$defc.= ($str) ? " AND (`staff` LIKE '%$str%' OR `phone` LIKE '$str%')":"";
		
		$cfield = ($view==1) ? "disbursement":"time";
		$cfield = ($view>=2) ? "time":$cfield;
		$defc .= ($view==1) ? " AND `disbursement`>0":"";
		$defc .= ($view==3) ? " AND `pref`='8'":"";
		$defc .= ($view==4) ? " AND `status`='9'":"";
		
		$qri = $db->query(2,"SELECT from_unixtime($cfield,'%Y-%b') AS mon FROM `$ltbl` WHERE $defc GROUP BY mon");
		$res = ($qri) ? $qri:array(["mon"=>date("Y-M")]); $mons=$days=[];
		foreach($res as $row){
			$mons[] = strtotime($row['mon']);
		}

		rsort($mons); $mon = ($mon) ? $mon:max($mons); $mns=$dys="";
		foreach($mons as $mn){
			$cnd=($mn==$mon) ? "selected":"";
			$mns.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
		}
		
		$trange = monrange(date("m",$mon),date('Y',$mon)); $m1=$trange[0]; $m2=$trange[1]; $m2+=86399;
		$res = $db->query(2,"SELECT from_unixtime($cfield,'%Y-%b-%d') AS day FROM `$ltbl` WHERE $defc AND $cfield BETWEEN $m1 AND $m2 GROUP BY day");
		if($res){
			foreach($res as $row){ $days[] = strtotime($row['day']); }
		}
		
		rsort($days); $dys = "<option value='0'>-- Day --</option>";
		foreach($days as $dy){
			$cnd = ($dy==$day) ? "selected":"";
			$dys.="<option value='$dy' $cnd>".date('d-m-Y',$dy)."</option>";
		}
		
		if($view>=2){
			$tmon = ($mon) ? monrange(date("m",$mon),date('Y',$mon)):[]; $tto=($mon) ? $tmon[1]:0; $tto+=86399;
			$load = ($mon) ? "AND `time` BETWEEN ".$tmon[0]." AND $tto":"";
			$cond.= ($view==4) ? " AND `status`='9' $load":" AND `status`<9 AND `disbursement`='0' $load"; 
			$cond.= ($view==3) ? " AND `pref`='8' AND `disbursement`='0'":" AND NOT `pref`='8' AND `disbursement`='0'";
			if(in_array($view,[2,3]) && $sid==1){
				$order = "CASE WHEN `pref`<10 THEN time ELSE `pref` END ASC";
			}
			else{ $order = "$cfield DESC"; }
		}
		else{
			$cfield = ($view) ? "disbursement":"time"; $order="$cfield DESC";
			$fro = ($day) ? $day:$mon; $dto = ($day) ? $day+86399:monrange(date("m",$mon),date('Y',$mon))[1]+86399; 
			$cond.= ($view==1) ? " AND `$cfield` BETWEEN $fro AND $dto":" AND `$cfield` BETWEEN $fro AND $dto";
			$cond.= (isset($_GET['nlt'])) ? " AND `loan`='0'":"";
		}
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='staff_loans$cid'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			foreach($cols as $col=>$dtp){
				if(in_array($dtp,["image","pdf","docx"])){ $media[]=$col; }
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='staff'");
		if($res){
			foreach($res as $row){ $lprods[$row['id']]=prepare(ucwords($row['product'])); }
		}
		
		$sett["loanposting"]=0; $sett["lastposted"]=0; $sett["multidisburse"]=0;
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='loanposting' OR `setting`='lastposted' OR `setting`='multidisburse')");
		if($sql){
			foreach($sql as $row){ $sett[$row['setting']]=prepare($row['value']); }
		}
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
		$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $dcd=count($levels); $supers = ["super user","C.E.O","Director"]; 
		if($me['position']=="assistant"){ $me['position']=json_decode($me['config'],1)['position']; }
		
		$no=$start=($perpage*$page)-$perpage; $trs=$ths=$offs=""; $dis=$ctd=0; $appr=[];
		if($db->istable(2,$ltbl)){
			$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY $order $lim");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $disb=$row['disbursement']; $tds=$aptd=""; $upd="----"; $no++; 
					$cuts = (isset($row['cuts'])) ? json_decode($row['cuts'],1):[]; $cuts=(is_array($cuts)) ? $cuts:[];
					if($dcd){
						$dis+=($row['status']==8) ? 1:0; $plev=0; $npos="";
						$status = array(10=>"<span style='color:green;'>".date('d.m.Y,H:i',$disb)."</span>");
						foreach($levels as $pos=>$user){
							$stx = ($row['pref']==8) ? "Pended at":"Waiting"; $key=$pos-1; 
							if($row['status']==$key && !$disb){ $npos=$user; $appr[str_replace(" ","_",$user)]=$key; }
							$status[$key]="<i style='color:grey;font-size:13px'>$stx ".prepare(ucfirst($user))."</i>";
						}
						
						$status[8]="<i style='color:#9932CC;'>Pending</i>"; $status[9]="<i style='color:#ff4500;'>Declined</i>";
						$plev=($row['status']!=8 && !$disb && (in_array($me['position'],$supers) or $me['position']==$npos or $row['status']==0)) ? 1:0;
					}
					else{
						$dis+=($row['status']<9 && $disb) ? 1:0; $plev=1; 
						$status=array(0=>"<i style='color:#9932CC;'>Pending</i>",10=>"<span style='color:green;'>".date('d.m.Y,H:i',$disb)."</span>");
					}
					
					foreach($fields as $col){
						if(!in_array($col,$exclude) && !in_array($col,$media)){
							$val = ($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst(str_replace("/","/ ",nl2br($row[$col]))));
							$val = ($col=="creator") ? $staff[$row[$col]]:$val;
							$val = ($col=="loan_product") ? "- ".$lprods[$row[$col]]."<br>- ".$row['duration']." days":$val;
							$val = (isset($docs[$col.$row['id']])) ? $docs[$col.$row['id']]:$val; 
							$val = (filter_var($val,FILTER_VALIDATE_URL)) ? "<a href='".prepare($row[$col])."' target='_blank'><i class='fa fa-link'></i> View</a>":$val;
							$val = ($col=="amount") ? "KES ".number_format($row[$col]):$val;
							if($col=="processing"){ 
								$chj = array_sum(json_decode($row['processing'],1)); $lis="";
								foreach(json_decode($row['processing'],1) as $pay=>$amnt){ $lis.= ucwords(str_replace("_"," ",$pay))."($amnt)\n"; }
								foreach($cuts as $pay=>$amnt){ $lis.= ucwords(str_replace("_"," ",$pay))."($amnt)\n"; $chj+=$amnt; }
								$pyc = ($row['payment']) ? $row['payment']."<br>":""; $col="Charges";
								$val = ($lis) ? "<span title='$lis'>$pyc KES $chj</span>":"None"; 
							}
							$tds.= "<td>$val</td>"; $ths.=($no==$start+1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
						}
					}
					
					$tds.= (count($media)) ? "<td>".count($media)." Files<br><a href='javascript:void(0)' onclick=\"popupload('media.php?vmed=$ltbl&src=$rid')\">
					<i class='bi-eye'></i> View</a></td>":"";
					
					$autopost = ($sett['loanposting'] && $row['status']>$sett['lastposted']) ? 1:0;
					$bt1 = ($row['status']>10 && $row['loan']==0 && in_array("post disbursed loan",$perms) && !$autopost) ? 
					"<br><a href='javascript:void(0)' onclick=\"popupload('hr/loans.php?postloan=$rid')\">Post Loan</a>":"<br>";
					$bt1 = ($row['status']<9 && in_array("edit staff loan",$perms) && $plev) ? "<br><a href='javascript:void(0)' 
					onclick=\"popupload('hr/loans.php?apply=$rid')\">Edit</a>":$bt1;
					
					if($row['status']>200 && $row['loan']<10){
						$bt1.= (!$autopost && in_array("delete staff loan",$perms) && $plev) ? " | <a href='javascript:void(0)' style='color:#ff4500' 
						onclick=\"delrecord('$rid','$ltbl','Delete Loan application?')\">Delete</a>":"";
					}
					else{
						$bt1.= ($row['loan']<10 && $row['status']<9 && in_array("delete staff loan",$perms) && $plev) ? 
						" | <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delrecord('$rid','$ltbl','Delete Loan application?')\">Delete</a>":"";
					}
					
					if(in_array("approvals",$fields)){
						$arr = ($row['approvals']==null) ? []:json_decode($row['approvals'],1); 
						foreach($arr as $usa=>$com){
							$msg = ($com) ? prepare($com):"Ok";
							$aptd.="<li style='list-style:none'><b>".explode(" ",$staff[$usa])[0].":</b> <i>$msg</i>";
						}
						$tds.= ($aptd) ? "<td>$aptd</td>":"<td>None</td>"; $ctd+=1;
					}
					
					$name=prepare(ucwords($row['staff'])); $fon=$row['phone']; $st=($row['status']==0 && $disb) ? 10:$row['status'];
					$trs.= "<tr valign='top' id='rec$rid'><td>$no</td><td>$name<br>0$fon</td>$tds<td>".$status[$st]." <span id='td$rid'>$bt1</span></td></tr>";
				}
			}
			else{
				foreach($fields as $col){
					if(!in_array($col,$exclude)){
						$ths.="<td>".ucfirst(str_replace("_"," ",$col))."</td>";
					}
				}
			}
		}
		else{ $ths = "<td>Loan product</td><td>Branch</td><td>Amount</td><td>Loan Processing</td>"; }
		$ths.= (count($media)) ? "<td>Attached Media</td>":"";
		$ths.= ($ctd) ? "<td>Approvals</td>":""; 
		
		$grups=$brans="";
		foreach($states as $key=>$des){
			$cnd=($view==$key) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if($access=="hq"){
			$brns = "<option value='0'>Corporate</option>";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:140px;font-size:15px;cursor:pointer' onchange=\"loadpage('hr/loans.php?apps=$view&bran='+this.value.trim())\">$brns</select>";
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM(amount) AS tsum FROM `$ltbl` WHERE $cond");
		$totals = ($sql) ? intval($sql[0]['total']):0; $tsum=($sql) ? number_format(intval($sql[0]['tsum'])):0; 
		
		$disb = ($dis && in_array("disburse loans",$perms) && $sett["multidisburse"]) ? "<button class='bts' style='padding:4px;float:right;font-size:13px;margin-left:5px' 
		onclick=\"popupload('hr/loans.php?confdisb=$dcd')\">Confirm Disbursed</button>":"";
		
		$cnd =(isset($appr[str_replace(" ","_",$me['position'])]) or (count($appr)>0 && in_array($me['position'],$supers))) ? 1:0;
		$apprv = ($cnd && in_array("approve staff loan",$perms)) ? "<button class='bts' style='padding:4px;float:right;font-size:13px;' 
		onclick=\"popupload('hr/loans.php?approve=".min($appr)."')\">Approve Loans</button>":"";
		
		$adcl = ($view==3 or $view==4) ? "stbl":"";
		$title = (isset($_GET['nlt'])) ? "Unposted Disbursed Loans":$states[$view];
		
		echo "<div class='container cardv' style='max-width:1400px;min-height:400px;overflow:auto;'>
			<div style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title ($tsum)</h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered $adcl' style='width:100%;font-size:14px;min-width:700px' cellpadding='5'>
						<caption style='caption-side:top'><p> $disb $apprv
							<select style='width:150px;font-size:15px;cursor:pointer' onchange=\"loadpage('hr/loans.php?apps='+this.value.trim().split(' ').join('+'))\">
							$grups</select> $brans</p>
							<p style='margin:0px'> <select style='width:110px;cursor:pointer' onchange=\"loadpage('hr/loans.php?apps=$view&bran=$bran&mon='+this.value.trim())\">$mns</select>
							<select style='width:120px;cursor:pointer' onchange=\"loadpage('hr/loans.php?apps=$view&bran=$bran&mon=$mon&day='+this.value.trim())\">$dys</select>
							<input type='search' style='width:170px;padding:4px 5px;font-size:15px' placeholder='Search in ".date('M Y',$mon)."' value=\"".prepare($str)."\"
							onkeyup=\"fsearch(event,'hr/loans.php?apps=0&str='+cleanstr(this.value))\" onsearch=\"loadpage('hr/loans.php?apps=0&str='+cleanstr(this.value))\"></p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:13px' valign='top'>
						<td colspan='2'>Staff Name</td>$ths<td>Disbursement</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"hr/loans.php?apps=$view&bran=$bran&mon=$mon&day=$day&str=".str_replace(" ","+",prepare($str)))."
		</div>
		<script>
			setTimeout(function(){
				$.each($('img'), function(){
					if($(this).attr('data-src')){
						var source = $(this).data('src');
						$(this).attr('src', source); $(this).removeAttr('data-src');
					}
				});
			},2000);
		</script>";
		savelog($sid,"Viewed $title");
	}
	
	# post loan
	if(isset($_GET['postloan'])){
		$tid = trim($_GET['postloan']);
		$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `id`='$tid'");
		$row = $res[0]; $day=date("Y-m-d",$row['status']); $dur=$row['duration']; $mx=date("Y-m-d",time()+86400);
		$amnt=number_format($row['amount']); $name=prepare(ucwords($row['staff'])); $lid=$row['loan'];
		
		$btx = ($lid) ? "<p style='color:#ff4500;font-weight:bold'>Sorry, Loan already posted!</p>":"<p style='text-align:right'><button class='btnn'>Post</button></p>";
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Post Disbursed Loan</h3><br>
			<form method='post' id='pform' onsubmit=\"postloan(event,'$tid')\">
				<input type='hidden' name='postid' value='$tid'>
				<p>Post Staff Loan for <b>$name</b> of KES <b>$amnt</b> for $dur days</p>
				<p>Disbursement Date<br><input type='date' name='pday' style='width:100%' value='$day' max='$mx' required></p><br> $btx
			</form><br>
		</div>";
	}
	
	# add loan Charges
	if(isset($_GET["lncharge"])){
		$lid = trim($_GET["lncharge"]);
		$opts = "<option value='0'>All Installments</option>"; $no=0;
		$sql = $db->query(2,"SELECT *FROM `staff_schedule$cid` WHERE `loan`='$lid'");
		if($sql){
			foreach($sql as $row){
				$id=$row['id']; $bal=$row['balance']; $no++;
				$opts.= ($bal>0) ? "<option value='$id'>Installment $no</option>":"";
			}
		}
		
		$btx = ($no) ? "<p style='text-align:right'><button class='btnn'>Charge</button></p>":"<p style='color:#ff4500;font-weight:bold'>Sorry, All installments are paid!</p>";
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Post Loan Charges</h3><br>
			<form method='post' id='cfom' onsubmit=\"chargeloan(event)\">
				<input type='hidden' name='lncharge' value='$lid'>
				<p>Payment Description(Max 20 Chars)<br><input style='width:100%' type='text' name='chargenm' maxlength='20' required></p>
				<p>Charge Amount<br><input style='width:100%' type='number' name='chajamt' required></p>
				<p>Charge on<br><select style='width:100%' name='instp' required>$opts</select><br><span style='font-size:13px'>
				<b>Note:</b> For all installments, same input amount will be added for each of them.</span></p><br> $btx
			</form><br>
		</div>";
	}
	
	# loan history
	if(isset($_GET['payhist'])){
		$tm=trim($_GET['payhist']); 
		$start=$tbal=$tpd=$tsum=$tint=$tprc=0; $trs=""; 
		
		$res = $db->query(2,"SELECT *FROM `staff_schedule$cid` WHERE `loan`='$tm' ORDER BY `day` ASC");
		foreach($res as $row){
			$day=date("d-m-Y",$row['day']); $intr=$row['interest']; $paid=fnum($row['paid']); $bal=$row['balance']; $tpd+=$row['paid'];
			$dy=$row['day']; $today=strtotime("Today"); $id=$row['id']; $pays=json_decode($row['payments'],1); $amnt=$row['amount']; 
			$pd = (count($pays)) ? explode(":",array_reverse(array_keys($pays))[0])[1]:0; $brek=json_decode($row['breakdown'],1); 
			$bint = (isset($brek["interest"])) ? intval($brek["interest"]):0;
			if($intr==0 && $bint>0){ $bal+=$brek["interest"]; $amnt+=$brek["interest"]; $intr=$brek["interest"]; }
			$tprc+=(isset($brek["principal"])) ? $brek["principal"]:0; $tint+=$intr;
			
			$prc = (isset($brek["principal"])) ? fnum($brek["principal"]):0; $intrst=fnum($intr); $tsum+=$amnt; $sum=fnum($amnt); $tbal+=$bal;
			$prc.= ($intr==0 && !isset($brek["interest"])) ? " <span style='background:#4682b4;font-size:12px;padding:3px 4px;color:#fff'>Offset</span>":"";
			$intrst.= (!isset($brek["principal"]) && $intr>0) ? " <span style='background:#006400;font-size:12px;padding:3px 4px;color:#fff'>RollOver</span>":"";
			
			$pday = ($pd>0) ? date("d-m-Y",$pd)." <i class='fa fa-plus' onclick=\"popupload('hr/loans.php?showpays=$id:$tm')\" style='cursor:pointer;color:blue;'
			title='View Payments'></i>":"----"; $color="#000";
			
			if($today>$dy){
				if($pd>0){ $color=(strtotime(date("Y-M-d",$pd))>$dy) ? "#FF00FF":"#000"; }
				else{ $color=($row['balance']>0) ? "#FF00FF":"#000"; }
			}
			
			$trs.= ($start==$row['day']) ? "<tr style='font-weight:bold;background:#f8f8f0;color:#191970;font-size:14px'><td colspan='6'>Rescheduled</td></tr>":"";
			$trs.= "<tr style='color:$color'><td>$day</td><td>$sum</td><td>$prc</td><td>$intrst</td><td>$paid</td><td>$pday</td><td>".fnum($bal)."</td></tr>";
		}
		
		$trs.= "<tr style='color:#191970;font-weight:bold'><td>Totals</td><td>".fnum($tsum)."</td><td>".fnum($tprc)."</td><td>".fnum($tint)."</td>
		<td>".fnum($tpd)."</td><td>----</td><td>".fnum($tbal)."</td></tr>";
		
		echo "<div style='max-width:100%;overflow:auto;padding:15px 5px'>
			<p style='font-weight:bold'>Loan $tm Installments</p>
			<table cellpadding='5' style='width:100%;min-width:460px;font-size:15px' class='table-bordered'>
				<tr style='font-weight:bold;background:#f0f0f0;color:#191970;font-size:14px'><td>Schedule</td><td>Amount</td><td>Principal</td><td>Interest</td>
				<td>Paid</td><td>Date</td><td>Balance</td></tr> $trs
			</table>
		</div>";
	}
	
	# approve loans
	if(isset($_GET['approve'])){
		$state = trim($_GET['approve']); $brans=$tps=$opts=$lis="";
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$me = staffInfo($sid); $access=$me['access_level']; $pos=$me['position'];
		$lvs = ($pos=="assistant") ? json_decode($me['config'],1)['position']:$pos;
		
		$res = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='stafftemplate'");
		$levels = ($res) ? json_decode($res[0]['levels'],1):[]; 
		$level = (in_array($lvs,$levels)) ? array_search($lvs,$levels):0;
		
		if(in_array($pos,["C.E.O","Director","super user"])){
			foreach($levels as $key=>$lv){
				$val=$key-1; $cnd=($state==$val) ? "selected":"";
				$opts.="<option value='$val' $cnd>".ucfirst(prepare($lv))."</option>";
			}
			$tps = "Approve as: <select style='padding:4px;width:120px;font-size:15px;margin-top:7px' onchange=\"popupload('hr/loans.php?approve='+this.value)\">$opts</select>";
		}
		else{ $state=$level-1; }
		
		if($access=="hq"){
			$brns = "<option value='0'>Head Office</option>";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='padding:4px;width:140px;font-size:15px;margin-top:7px' onchange=\"popupload('hr/loans.php?approve=$state&bran='+this.value)\">$brns</select>";
		}
		else{ $bran = $me['branch']; }
		
		$trs=""; $next=$state+1;
		$cond = ($bran) ? "AND `branch`='$bran'":"";
		$cond.= ($state==0) ? " AND NOT `creator`='$sid'":"";
		$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `status`='$state' AND (`loan`='0' OR `loan`='') $cond ORDER BY `pref` ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['staff'])); $id=$row['id']; $amnt=number_format($row['amount']); $mx=date("Y-m-d")."T".date("H:i");
				$trs.= "<tr valign='top'><td><input type='checkbox' name='ploans[]' value='$id'></checkbox></td><td>$name</td><td style='text-align:right'>$amnt</td></tr>";
			}
		}
		
		$arr = ($state) ? array("Approve","Decline","Pend"):array("Approve","Pend");
		foreach($arr as $li){ $lis.="<option value='$li'>$li</option>"; }
		
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='loan_approval_otp' AND `client`='$cid'"); $set = ($sql) ? $sql[0]['value']:1;
		if($set){
			$btx = ($trs) ? "<tr style='background:#fff;' valign='bottom'><td colspan='2'><div style='width:170px;border:1px solid #dcdcdc;height:35px'>
			<input type='text' name='otpv' style='border:0px;padding:4px;width:90px' placeholder='OTP Code' maxlength='6' onkeyup=\"valid('otp',this.value)\" id='otp' 
			autocomplete='off' required><a href='javascript:void(0)' onclick=\"requestotp('$sid')\"><i class='fa fa-refresh'></i> Request</a></div></td>
			<td style='width:120px'><select style='width:100%;padding:7px;cursor:pointer;font-size:15px' id='aop'>$lis</select></td></tr>
			<tr style='background:#fff'><td colspan='3' style='text-align:right'><hr><button class='btnn'>Proceed</button></td></tr>":"";
		}
		else{
			$otp=rand(100000,999999); $exp=time()+3600; $fon=$me['contact'];
			$db->insert(1,"REPLACE INTO `otps` VALUES('$fon','$otp','$exp')");
			$btx = ($trs) ? "<tr style='background:#fff;' valign='bottom'><td colspan='2'><input type='hidden' name='otpv' value='$otp'>
			<select style='width:140px;padding:6px;cursor:pointer;font-size:15px' id='aop'>$lis</select></td>
			<td><button class='btnn' style='float:right;padding:6px'>Proceed</button></td></tr>":"";
		}
		
		$trs.= ($trs) ? "<tr><td colspan='3'>Your Comment (Max 30 Chars)<br><input type='text' style='width:100%' value='Ok' maxlength='30' name='appcom'></td></tr>":"";
		echo "<div style='padding:5px;margin:0 auto;max-width:450px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Approve Loans</h3>
			<form method='post' id='aform' onsubmit='approveloans(event)'>
				<input type='hidden' name='nval' value='$next'>
				<table style='width:100%;' class='table-striped' cellpadding='7'>
					<caption style='caption-side:top'> $tps $brans</caption>
					<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td colspan='2'>Staff</td><td style='text-align:right'>Loan</td></tr> $trs $btx
				</table><br>
			</form><br>
		</div> <script> clearInterval(tmt); var tmt=setTimeout(function(){closepop();},300000); </script>";
	}
	
	# confirm disbursed loans
	if(isset($_GET['confdisb'])){
		$state = trim($_GET['confdisb']); $trs="";
		$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `status`='8' ORDER BY `time` ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['staff'])); $id=$row['id'];
				$cut=(isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0;
				$amnt=fnum($row['amount']-$cut); $mx = date("Y-m-d")."T".date("H:i");
				$trs.= "<tr valign='top'><td><input type='checkbox' name='dids[]' value='$id'></checkbox></td><td>$name</td><td>$amnt</td>
				<td><input type='datetime-local' style='width:100%;padding:4px;max-width:220px' name='days[$id]' max='$mx'></td></tr>";
			}
		}
		
		$btx = ($trs) ? "<p style='text-align:right;'><button class='btnn'>Confirm</button></p>":"";
		echo "<div style='padding:10px'>
			<h3 style='color:#191970;text-align:center;font-size:22px'>Confirm Disbursed Applications</h3>
			<form method='post' id='dform' onsubmit='confirmdisb(event)'>
				<table style='width:100%;' class='table-striped' cellpadding='7'>
					<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td colspan='2'>Employee</td><td>Amount</td><td>Disbursement</td></tr> $trs
				</table><br> $btx
			</form><br>
		</div>";
	}
	# confirm disbursed loans
	if(isset($_GET['confdisb'])){
		$state = trim($_GET['confdisb']);
		$ltbl = "staff_loans$cid"; $trs=$lis="";
		$part = 0;
		
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE `status`='8' ORDER BY pref ASC");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $id=$row['id']; $cut=0; $mx=date("Y-m-d")."T".date("H:i");
				$dsb=[]; $cut+=(isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; 
				$dn=($dsb) ? array_sum($dsb):$cut; $amnt=$row['amount']-$dn; $min=($dsb) ? 0:$cut+50;
				$minp = ($part) ? "<input type='number' style='width:100px;padding:5px' value='$amnt' min='$min' max='$amnt' name='damnt[$id]' required>":
				"Ksh ".fnum($amnt)."<input type='hidden' value='$amnt' name='damnt[$id]'>";
				$trs.= "<tr valign='top'><td><input type='checkbox' name='dids[]' value='$id'></checkbox></td><td style='width:30%'>$name<br><span id='tdf$id'></span></td>
				<td>$minp<br><span id='tdl$id'></span></td><td style='width:33%'><input type='datetime-local' style='width:200px;padding:4px;background:#fff' name='days[$id]' max='$mx'><br>
				<span id='tdr$id'><a href='javascript:void(0)' onclick=\"addrefs('$id','$part')\" style='float:right'><i class='fa fa-plus'></i> Add Payment Ref</a></span></td></tr>";
			}
		}
		
		$lis = "<option value='0'>-- Select Account --</option>";
		$btx = ($trs) ? "<p style='text-align:right;'><button class='btnn'>Confirm</button></p>":"";
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`wing` LIKE '2,6%' OR `wing` LIKE '2,7%') ORDER BY `account` ASC");
		if($sql){
			foreach($sql as $row){
				$rid = $row['id']; $acc=prepare(ucfirst($row['account']));
				$lis.= (!in_array($rid,[14,15,22,23])) ? "<option value='$rid'>$acc</option>":"";
			}
		}
		
		echo "<div style='padding:10px'> 
			<h3 style='color:#191970;text-align:center;font-size:22px'>Confirm Disbursed Loans</h3>
			<form method='post' id='dform' onsubmit='confirmdisb(event)'>
				<table style='width:100%;min-width:430px' class='table-striped' cellpadding='7'>
					<caption style='caption-side:top'>
						<p style='margin:0px'>Disbursed From<br><select style='width:220px;font-size:15px' name='disbfrom' required>$lis</select></p>
					</caption>
					<tr style='color:#191970;font-weight:bold;background:#e6e6fa'><td colspan='2'>Client</td><td>Amount</td><td>Disbursed Date</td></tr> $trs
				</table><br> $btx
			</form><br>
		</div>
		<script>
			function addrefs(id,pt){
				$('#tdf'+id).html(\"<input type='text' name='trans[\"+id+\"]' placeholder='Payment Ref' style='width:100%;font-size:15px;padding:4px;min-width:120px;margin-top:12px' required>\");
				$('#tdl'+id).html(\"<input type='text' id='ty\"+id+\"' name='fees[\"+id+\"]' placeholder='Charges Incurred' style='width:100%;font-size:15px;padding:4px;'>\");
				$('#tdr'+id).html(\"<input type='text' name='desc[\"+id+\"]' placeholder='Payment Description' style='width:100%;padding:4px;font-size:15px' required>\");
				if(pt<1){ $('#ty'+id).css({'margin-top':'12px'}); }
			}
		</script>";
	}
	
	# payment history for schedule
	if(isset($_GET['showpays'])){
		$data = trim($_GET['showpays']); 
		$id = explode(":",$data)[0]; $loan=explode(":",$data)[1]; $trs="";
		
		$res = $db->query(2,"SELECT *FROM `staff_schedule$cid` WHERE `id`='$id'");
		foreach(json_decode($res[0]['payments'],1) as $pay=>$des){
			$ded = fnum(array_sum($des)); $day=date("d-m-Y,H:i",explode(":",$pay)[1]); $pid=explode(":",$pay)[0];
			$rs = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid'");
			$rw = $rs[0]; $code=$rw['code']; $acc=prepare($rw['account']); $amnt=fnum($rw['amount']);
			$trs.="<tr><td>$day</td><td>$code</td><td>$acc</td><td>$amnt</td><td>$ded</td></tr>";
		}
		
		echo "<div style='max-width:100%;overflow:auto;padding:15px 5px'>
			<button class='bts' style='font-size:14px;padding:3px' onclick=\"popupload('hr/loans.php?payhist=$loan')\"><i class='fa fa-arrow-left'></i> Back</button>
			<table cellpadding='5' style='width:100%;font-size:15px;margin-top:10px;min-width:450px' class='table-striped table-bordered'>
			<tr style='font-weight:bold;background:#f0f0f0;color:#191970;font-size:14px'><td>Date</td><td>Transaction</td><td>Account</td><td>Amount</td>
			<td>Deducted</td></tr>$trs
			</table>
		</div>";
	}
	
	# edit loan
	if(isset($_GET['editloan'])){
		$lid = trim($_GET['editloan']);
		$prd = (isset($_GET['prd'])) ? trim($_GET['prd']):0;
		$me = staffInfo($sid);
		
		$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `loan`='$lid'");
		$row = $res[0]; $amnt=$row['amount']; $dur=$row['duration']; $staff=prepare(ucwords($row['staff'])); $uid=$row['stid'];
		$disb=date("Y-m-d",$row['disbursement']); $prod=($prd) ? $prd:$row['loan_product']; $kesho=date("Y-m-d",time()+86400);
		
		$setts = ["loansegment"=>1];
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row['setting']]=$row['value']; }
		}
		
		$opts=$offs=$prods="";
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
		$payterms = json_decode($res[0]['payterms'],1); $min=$res[0]['minamount']; $max=$res[0]['maxamount'];
		if($setts['loansegment']){
			$arr = $res[0]['duration']/$res[0]['interest_duration']; $intv=$res[0]['intervals'];
			for($i=1; $i<=$arr; $i++){
				$pr=$i*$res[0]['interest_duration'];
				$cnd = ($dur==$pr) ? "selected":"";
				$opts.="<option value='$pr' $cnd>$pr days</option>";
			}
		}
		else{ 
			$pr=$res[0]['duration'];
			$opts="<option value='$pr' $cnd>$pr days</option>";
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `status`='0' AND `category`='staff'");
		foreach($res as $row){
			$cnd = ($row['id']==$prod) ? "selected":"";
			$prods.= "<option value='".$row['id']."' $cnd>".prepare(ucfirst($row['product']))."</option>";
		}
		
		echo "<div style='max-width:300px;padding:10px;margin:0 auto'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>Edit staff running Loan</h3><br>
			<form method='post' id='lform' onsubmit=\"saveloan(event,'$uid')\">
				<input type='hidden' name='lid' value='$lid'>
				<p>Loan Product<br><select name='lprod' onchange=\"popupload('hr/loans.php?editloan=$lid&prd='+this.value)\" style='width:100%'>$prods</select></p>
				<p>Duration<br><select name='ldur' style='width:100%'>$opts</select></p>
				<p>Loan Amount<br><input type='number' name='lamnt' value='$amnt' required></p>
				<p>Disbursement Date<br><input style='color:#2f4f4f;width:100%' type='date' value='$disb' max='$kesho' name='disb' required></p><br>
				<p style='text-align:right'><button class='btnn'>Update</button></p>
			</form><br>
		</div>";
	}
	
	# loan details
	if(isset($_GET['loandes'])){
		$lid = trim($_GET['loandes']);
		$fields = $db->tableFields(2,"staff_loans$cid");
		
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`"); $names[0]="System";
		foreach($qri as $row){
			$names[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$res = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `loan`='$lid'");
		$row = $res[0]; $amnt=number_format($row['amount']); $day=date("d-m-Y, h:i a",$row['time']); $exp=date("d-m-Y, h:i a",$row['expiry']);
		$disb=date("d-m-Y, h:i a",$row['disbursement']); $finished=($row['status']>200) ? date("d-m-Y, h:i a",$row['status']):"----";
		$pby=$names[$row['creator']]; $apps=$row['approvals']; $appr=($apps) ? json_decode($apps,1):[];  $trs=$lis="";
		$tby=date("d-m-Y, h:i a",$row['time']);
		
		foreach($appr as $key=>$com){ $lis.="<li>$names[$key]: <i>$com</i></li>"; }
		$lis = ($lis) ? $lis:"----";
		
		$exclude = array("id","staff","loan_product","phone","branch","amount","stid","duration","disbursement","expiry","penalty","paid","balance","loan","status","pref",
		"time","approvals","creator","processing","payment");
		$qri = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='staff_loans$cid'");
		$inputs = ($qri) ? json_decode($qri[0]['fields'],1):[];
	
		foreach($fields as $col){
			if(!in_array($col,$exclude)){
				$cname = str_replace("_"," ",ucwords($col)); 
				$dtp = (isset($inputs[$col])) ? $inputs[$col]:""; $df=$row[$col]; $opts="";
				$val = ($dtp=="url") ? "<a href='".$row[$col]."' target='_blank'>View</a>":prepare(ucfirst($row[$col]));
				if($col=="cuts"){
					foreach(json_decode($row[$col],1) as $pay=>$sum){
						$opts.= "<li>".ucwords(str_replace("_"," ",$pay))." - $sum</li>";
					}
					
					if($opts){ $val=$opts; $cname="Deductions";}
					else{ $val=$cname=""; }
				}
				
				if(in_array($dtp,array("image","pdf","docx"))){
					if($dtp=="pdf" or $dtp=="docx"){
						$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
						$url = "https://docs.google.com/viewer?url=http://$url/docs/$df&embedded=true";
						$val = ($df) ? "<a href='$url'><img src='$img' style='height:40px;cursor:pointer'></a>":"----";
					}
					else{
						$val = ($dtp=="image" && $df) ? "<img src='data:image/jpg;base64,".getphoto($df)."' style='height:60px;cursor:pointer' 
						onclick=\"popupload('media.php?doc=img:$df&tbl=staff_loans$cid:$col&fd=loan:$lid&nch')\">":"----"; 
					}
				}
				
				$trs.= ($cname) ? "<tr><td style='color:#191970;font-weight:bold;font-size:14px'>$cname</td><td>$val</td></tr>":"";
			}
		}
		
		echo "<div style='padding:10px;max-width:500px;margin:0 auto;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Loan Details</h3><br>
			<div style='overflow:auto;'>
				<table class='table-striped' style='margin:0 auto;font-size:15px;min-width:300px' cellpadding='5'>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Loan Amount</td><td>$amnt</td></tr>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Disbursement</td><td>$disb</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Issuerer</td><td>$pby<br>$day</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Application</td><td>$tby</td></tr>
					<tr valign='top'><td style='color:#191970;font-weight:bold;font-size:14px'>Approvals</td><td>$lis</td></tr>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Loan Maturity</td><td>$exp</td></tr>
					<tr><td style='color:#191970;font-weight:bold;font-size:14px'>Clearance</td><td>$finished</td></tr> $trs
				</table><br> 	
			</div><br>
		</div>";
	}

	@ob_end_flush();
?>

<script>
	
	function postloan(e,tid){
		e.preventDefault();
		if(confirm("Proceed to post staff Loan?")){
			var data = $("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/staff_loans.php",data:data,
				beforeSend:function(){ progress("Posting...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to post loan! Check your internet connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					$("#td"+tid).html(""); closepop();
				}
				else{ toast(res); }
			});
		}
	}
	
	function waiveint(lid){
		var intr = prompt("Enter Total Interest charged for the Loan");
		if(intr){
			if(confirm("Confirm change of Loan Interest to "+intr+"?")){
				$.ajax({
					method:"post",url:path()+"dbsave/staff_loans.php",data:{waiveintr:lid,nintr:intr},
					beforeSend:function(){ progress("Applying...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						alert("Updated successfully!"); closepop(); window.location.reload();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function chargeloan(e){
		e.preventDefault();
		if(confirm("Proceed to add loan charges?")){
			var data = $("#cfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/staff_loans.php",data:data,
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
	
	function approveloans(e){
		e.preventDefault();
		if(!checkboxes("ploans[]")){ alert("Please check atleast one staff to approve"); }
		else{
			var vtp = $("#aop").val(), adds={Approve:"approveloans",Decline:"decline",Pend:"pendloans"}
			if(confirm("Proceed to "+vtp+" selected Loans?")){
				var data = $("#aform").serialize(); data+="&"+adds[vtp]+"=1";
				$.ajax({
					method:"post",url:path()+"dbsave/staff_loans.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed to approve loan! Check your internet connection");
				}).done(function(res){
					if(res.trim()=="success"){
						loadpage("hr/loans.php?apps"); closepop();
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function deleteloan(lid,uid){
		if(confirm("Sure to permanently delete loan?")){
			$.ajax({
				method:"post",url:path()+"dbsave/staff_loans.php",data:{deloan:lid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					loadpage("hr/staff.php?vtp=lns&vstid="+uid); toast("Deleted Successfully");
				}
				else{ alert(res); }
			});
		}
	}
	
	function confirmdisb(e){
		e.preventDefault();
		if(!checkboxes("dids[]")){ alert("Please check atleast one staff whose loan is disbursed"); }
		else{
			if(confirm("Confirm that Loans for selected staff are disbursed?")){
				var data = $("#dform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/staff_loans.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed to confirm Loans! Check your internet connection");
				}).done(function(res){
					if(res.trim()=="success"){
						loadpage("hr/loans.php?apps=1"); closepop();
					}
					else{ toast(res); }
				});
			}
		}
	}
	
	function editpenalty(lid,val,uid){
		var nw = prompt("Enter New Penalty amount",val);
		if(nw){
			if(confirm("Update staff loan Penalty amount?")){
				$.ajax({
					method:"post",url:path()+"dbsave/staff_loans.php",data:{cpenalty:nw,clid:lid},
					beforeSend:function(){progress("Updating...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						toast("Updated!"); loadpage("hr/staff.php?vtp=lns&vstid="+uid); 
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function saveloan(e,uid){
		e.preventDefault();
		if(confirm("Update Loan details?")){
			var data = $("#lform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/staff_loans.php",data:data,
				beforeSend:function(){ progress("Updating...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed to complete the request! Check your internet connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					loadpage("hr/staff.php?vtp=lns&vstid="+uid); closepop();
				}
				else{ toast(res); }
			});
		}
	}
	
	function savetemplate(e){
		e.preventDefault();
		if(confirm("Save loan application template?")){
			var tp = _("hasfiles").value.trim();
			if(tp.length>2){
				var data=new FormData(_("tform")),files=tp.split(":");
				for(var i=0; i<files.length; i++){
					data.append(files[i],_(files[i]).files[0]);
				}
				
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener("progress",uploadprogress,false);
				xhr.addEventListener("load",uploaddone,false);
				xhr.addEventListener("error",uploaderror,false);
				xhr.addEventListener("abort",uploadabort,false);
				xhr.onload = function(){
					if(this.responseText.trim()=="success"){
						toast("Success"); closepop(); window.location.reload();
					}
					else{ alert(this.responseText); }
				}
				xhr.open("post",path()+"dbsave/loans.php",true);
				xhr.send(data);
			}
			else{
				var data=$("#tform").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/staff_loans.php",data:data,
					beforeSend:function(){ progress("Saving...please wait"); },
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