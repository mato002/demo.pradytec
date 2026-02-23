<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if(!$sid){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	#prepayments
	if(isset($_GET['prepayments'])){
		$page=(isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$str=(isset($_GET['str'])) ? clean($_GET['str']):null;
		$bran=(isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$ofid=(isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		$ptbl = "org".$cid."_prepayments";
		
		$perpage=40;
		$lim=getLimit($page,$perpage);
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$cnf = json_decode($me["config"],1);
		
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND pt.branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? " AND pt.officer='".$me['branch']."'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"],"pt.branch"):$cond;
		$cond.= ($str) ? " AND (pt.client LIKE '%$str%' OR pt.idno LIKE '%$str%')":""; 
		$cond.= ($ofid) ? " AND pt.officer='$ofid'":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="lt.client_idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($qry){
			foreach($qry as $row){
				$prods[$row['id']]=prepare(ucwords($row['product']));
			}
		}
		
		$no=($perpage*$page)-$perpage; $trs=$offs=$brans=""; $offids=[]; $avs=$totals=0;
		if($db->istable(2,$ptbl)){
			$res = $db->query(2,"SELECT pt.id,pt.idno,pt.client,pt.branch,pt.officer,pt.amount,pt.product,lt.status,lt.amount AS lamnt,lt.duration
			FROM `$ptbl` AS pt INNER JOIN `org".$cid."_loantemplates` AS lt ON pt.template=lt.id WHERE pt.status='0' $cond $lim"); $avs=1;
			if($res){
				foreach($res as $row){
					$idno=$row['idno']; $name=ucwords(prepare($row['client'])); $bname=$bnames[$row['branch']]; $ofname=$staff[$row['officer']];
					$amnt=number_format($row['amount']); $prod=$prods[$row['product']]; $rid=$row['id']; $no++;
					$offids[$row['officer']]=$row['officer']; $loan=number_format($row['lamnt']); $dur=$row['duration'];
					$day=($row['status']>10) ? date("d-m-Y",$row['status']):"Pending"; 
						
					$del=(in_array("assign prepayment",$perms)) ? "<a href='javascript:void(0)' style='color:#ff4500;margin-left:10px' 
					onclick=\"delrecord('$rid','$ptbl','Delete Loan prepayment for ".str_replace("'","`",$name)."?')\">Delete</span>":"";
					$apprv=(in_array("assign prepayment",$perms) && $row['status']>10) ? "<a href='javascript:void(0)' onclick=\"approveprepay('$rid')\">Assign</span>":"";
					
					if($row['lamnt']){
						$trs.="<tr valign='top' id='rec$rid'><td>$no</td><td>$name<br>Idno: $idno</td><td>$bname</td><td>$loan</td><td>$prod<br>$dur Days</td>
						<td>$ofname</td><td>$day</td><td>$amnt</td><td>$apprv $del</td></tr>";
					}
					else{
						$db->insert(2,"DELETE FROM `$ptbl` WHERE `id`='$id'");
					}
				}
			}
		}
		
		if($access=="hq" or $access=="region"){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:150px' onchange=\"loadpage('payments.php?prepayments&bran='+this.value.trim())\">$brns</select>";
		}
		
		if($bran or $access=="branch"){
			$opts="<option value='0'>- Select Staff -</option>";
			foreach($offids as $off){
				$cnd=($off==$ofid) ? "selected":"";
				$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
			}
			
			$offs="<select style='width:150px;' onchange=\"loadpage('payments.php?prepayments&bran=$bran&ofid='+this.value)\">$opts</select>";
		}
		
		if($avs){
			$qri= $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` AS pt INNER JOIN `org".$cid."_loantemplates` AS lt ON pt.template=lt.id WHERE pt.status='0' $cond");
			$totals = ($qri) ? $qri[0]['total']:0;
		}
		
		$show=($str) ? "Search results for <u>".prepare($str)."</u>":"$brans $offs";
		$css = ($me['access_level']=="portfolio") ? "":"float:right";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px'>
			<div class='container' style='padding:5px;min-width:500px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Loan Prepayments</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-bordered' style='width:100%;font-size:15px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top;'>
							<input type='search' style='padding:4px;$css' placeholder='&#xf002; Search Client' 
							onkeyup=\"fsearch(event,'payments.php?prepayments&str='+cleanstr(this.value))\" 
							onsearch=\"loadpage('payments.php?prepayments&str='+cleanstr(this.value))\" value=\"$str\"> $show
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>
						<td colspan='2'>Client</td><td>Branch</td><td>Loan</td><td>Product</td><td>Officer</td><td>Disbursement</td>
						<td colspan='2'>Prepayment</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?prepayments&bran=$bran&ofid=$ofid&str=".str_replace(" ","+",$str))."
		</div>";
		
		savelog($sid,"Viewed prepayments");
	}
	
	//processed payments
	if(isset($_GET['processed'])){
		$view = trim($_GET['processed']);
		$day = ($view) ? strtotime($view):strtotime(date("Y-m-d"));
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$to = (isset($_GET['dto'])) ? trim($_GET['dto']):date("Y-m-d");
		$to = strtotime($to); $dto = ($day>$to) ? $day:$to;
		$ptp = (isset($_GET['paytp'])) ? clean($_GET['paytp']):null;
		$stid = (isset($_GET['ofid'])) ? clean($_GET['ofid']):0;
		$str = (isset($_GET['str'])) ? clean(strtoupper($_GET['str'])):null;
		$lnid = (isset($_GET['lid'])) ? clean($_GET['lid']):0;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$ptbl = "processed_payments$cid"; 
		$ldy = date("Y-m-d",$day); $ldto = date("Y-m-d",$dto);
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles'])); $perpage=40;
		$lim = getLimit($page,$perpage); $skip=[];
		$cnf = json_decode($me["config"],1);
		
		$stbl="org$cid"."_staff"; $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":" AND `branch`='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"]):$show;
		$fetch = ($dto) ? "`day` BETWEEN $day AND $dto":"`day`='$day'";
		$cond = ($str or $lnid) ? 1:$fetch;
		$cond.= ($me['access_level']=="portfolio") ? " AND `officer`='$sid'":$show;
		$cond.= ($str) ? " AND (`client` LIKE '%$str%' OR `idno` LIKE '%$str%' OR `code` LIKE '%$str%')":""; 
		$cond.= ($lnid) ? " AND `linked`='$lnid'":"";
		$load=$cond; $cond.= ($stid) ? " AND `officer`='$stid'":"";
		if($ptp){
			$cond.= ($ptp=="MPESA") ? " AND NOT `code` LIKE 'CHECKOFF%' AND NOT `code` LIKE 'CASH%'":" AND `code` LIKE '$ptp%'";
		}
		
		if($me["position"]=="collection agent"){ $cond.= " AND `officer`='$sid'"; }
		$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($sql){
			foreach($sql as $row){
				$pterms = json_decode($row["payterms"],1); $rid=$row['id'];
				foreach($pterms as $des=>$pay){
					if(in_array(explode(":",$pay)[0],[0,1,4,6])){ $skip[$des]=ucfirst($des); }
				}
			}
		}
		
		$no = ($perpage*$page)-$perpage; $trs=$offids=$brans=$name=""; $links=$lids=[]; $showbrk=1;
		if(defined("ONLY_SHOWPAYMENT_BREAKS")){
			$showbrk = (in_array($me["position"],ONLY_SHOWPAYMENT_BREAKS) or $sid==1) ? 1:0;
		}
		
		if($db->istable(2,$ptbl)){
			$res = $db->query(2,"SELECT *,GROUP_CONCAT(payment) AS paymodes,GROUP_CONCAT(amount) AS amounts,SUM(amount) AS totpay,MAX(id) AS rid FROM `$ptbl` 
			WHERE $cond GROUP BY payid ORDER BY id DESC $lim");
			if($res){
				foreach($res as $row){
					$pyid=$row['payid']; $idno=$row['idno']; $name=prepare(ucwords($row['client'])); $code=$row['code']; 
					$pays=explode(",",$row['paymodes']); $amnts=explode(",",$row['amounts']); $rid=$row['rid']; $loan=$row["linked"];
					$approved=$staff[$row['confirmed']]; $id=$row['id']; $amnt=number_format($row['totpay']); $lis=""; $list=[];
					$tym = ($str or ($dto!=$day) or $lnid) ? date("d-m-Y,H:i",$row['time']):date("h:i a",$row['time']); $skn=0;
					$tbl = (substr($loan,2,1)=="S") ? "staff_loans$cid":"org{$cid}_loans"; $col=(substr($loan,2,1)=="S") ? "stid":"client_idno";
					
					$lid = (isset($lids[$idno])) ? $lids[$idno]:0;
					if(!$lid){
						$lid = $db->query(2,"SELECT `loan` FROM `$tbl` WHERE `$col`='$idno' ORDER BY `disbursement` DESC LIMIT 1")[0]["loan"]; $lids[$idno]=$lid;
					}
					
					foreach($pays as $no=>$pay){
						$py=str_replace(" ","_",$pay); $tot=$amnts[$no]; $skn+=(in_array($py,$skip)) ? 1:0;
						if(isset($list[$py])){ $list[$py]+=$tot; }
						else{ $list[$py]=$tot; }
					}
					
					foreach($list as $py=>$pay){
						$lis.= "<li>".str_replace("_"," ",$py)." - ".fnum($pay)."</li>"; 
					}
					
					if(!isset($links[$loan])){
						$qry = $db->query(2,"SELECT id FROM `$ptbl` WHERE `linked`='$loan' ORDER BY `id` DESC LIMIT 1");
						$chk = $db->query(2,"SELECT SUM(balance+penalty) AS tbal FROM `$tbl` WHERE `loan`='$loan'");
						$rem = ($chk) ? intval($chk[0]["tbal"]):0; $mid=($qry && ($rem>0 or $lid==$loan)) ? $qry[0]['id']:0; $links[$loan]=$mid; 
					}
					
					$show = (in_array("reverse approved payments",$perms) && $rid==$mid && substr($code,0,8)!="WRITEOFF" && !$skn) ? "<br><a href='javascript:void(0)' 
					onclick=\"reversepay('$loan','$code','$ldy:$ldto:$bran:$stid')\"><i class='fa fa-refresh'></i> Reverse</a>":"";
					
					$idn = ($idno<5000) ? "":$idno; $sbr=($showbrk) ? "<td>$lis</td>":"";
					$trs.= "<tr valign='top' style='font-size:15px'><td>$code</td><td>$amnt</td>$sbr<td style='cursor:pointer' onclick=\"loadpage('clients.php?showclient=$idno')\">$name
					<br>$idn</td><td>$approved</td><td>$tym $show</td></tr>";
				}
			}
		}
		
		if($access=="hq" or $access=="region"){
			$brns="<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='width:150px;font-size:15px' onchange=\"loadpage('payments.php?processed=$ldy&dto=$ldto&bran='+this.value.trim())\">$brns</select>";
		}
		
		if(($bran or $access=="branch") && $access!="portfolio"){
			$brn = ($bran) ? $bran:$me['branch']; $offs="<option value='0'>-- Officer --</option>";
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `processed_payments$cid` WHERE $cond");
			if($res){
				foreach($res as $row){
					$id=$row['officer']; $cnd=($id==$stid) ? "selected":"";
					$offs.="<option value='$id' $cnd>".$staff[$id]."</option>";
				}
			}
			
			$offids = "<select style='width:150px;font-size:15px' onchange=\"loadpage('payments.php?processed=$ldy&dto=$ldto&bran=$bran&ofid='+this.value)\">$offs</select>";
		}
		
		$pays = array("MPESA"=>"Mpesa Paybill","CHECKOFF"=>"Checkoff Payments","CASH"=>"Cash Payments");
		$pys = "<option value=''>-- Pay Mode --</option>";
		foreach($pays as $py=>$des){
			$cnd = ($py==$ptp) ? "selected":"";
			$pys.="<option value='$py' $cnd>$des</option>";
		}
		
		$sql = $db->query(2,"SELECT COUNT(DISTINCT payid) AS total,SUM(amount) AS tpays FROM `$ptbl` WHERE $cond"); 
		$totals = ($sql) ? $sql[0]['total']:0; $tot = ($sql) ? $sql[0]['tpays']:0; $tpays=($tot) ? fnum($tot):0;
		$prnt = ($totals) ? "<button class='bts' onclick=\"printdoc('processed.php?src=".base64_encode($cond)."&fd=$day&td=$dto&pm=$ptp&br=$bran&stid=$stid&lid=$lnid','Processed')\" 
		style='padding:4px;float:right;line-height:normal'><i class='fa fa-print'></i> Print</button>":"";
		
		$res = $db->query(2,"SELECT MIN(day) AS mndy FROM `$ptbl`");
		$mn = ($res) ? $res[0]['mndy']:strtotime(date("Y-M-d")); $min=date("Y-m-d",$mn);
		
		if($dto==$day){ $title = "Processed pays for ".date('d-m-Y',$day); }
		else{ $title = "Processed pays from ".date('d-m-Y',$day)." to ".date('d-m-Y',$dto); }
		$title = ($str) ? "Search Results for $str":$title; $src=($name) ? "from $name":"";
		$title = ($lnid) ? "$staff[$stid] Collections $src":$title; $shbr=($showbrk) ? "<td>Payment Details</td>":"";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:20px;color:#191970'>$backbtn $title <span style='float:right;'>(Ksh $tpays)</span></h3><hr style='margin-bottom:0px'>
				<div style='overflow:auto;width:100%'>
					<table class='table-bordered' style='width:100%;font-size:14px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top'>
							<p>$prnt <input type='date' style='width:150px;padding:4px;border:1px solid #ccc;' id='dfro' min='$min' max='".date('Y-m-d')."'
							onchange=\"setprorange('dfro',this.value)\" value='".date('Y-m-d',$day)."'> To: 
							<input type='date' style='width:150px;padding:4px;border:1px solid #ccc;' id='dto' min='$min' max='".date('Y-m-d')."'
							onchange=\"setprorange('dto',this.value)\" value='".date('Y-m-d',$dto)."'></p>
							<p style='margin:0px'> <input type='search' style='float:right' placeholder='&#xf002; Search payment' 
							onkeyup=\"fsearch(event,'payments.php?processed&str='+cleanstr(this.value))\" 
							onsearch=\"loadpage('payments.php?processed&str='+cleanstr(this.value))\" value=\"$str\"> 
							$brans $offids <select style='width:140px;font-size:14px' 
							onchange=\"loadpage('payments.php?processed=$ldy&dto=$ldto&bran=$bran&ofid=$stid&paytp='+this.value)\">$pys</select></p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td>Transaction</td><td>Amount</td>$shbr<td>Client</td><td>Approval</td><td>Time</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?processed=$ldy&dto=$ldto&bran=$bran&ofid=$stid&paytp=$ptp&lid=$lnid&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed $title");
	}
	
	#pending manual payments
	if(isset($_GET['manual'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean(strtolower($_GET['str'])):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$ptbl = "org".$cid."_payments"; $portfolio=$pays=[];
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage=40; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		if($access=="portfolio"){
			$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan_officer`='$sid' AND `balance`>0");
			if($res){
				foreach($res as $row){
					$portfolio[$row['client_idno']]=$row['phone'];
				}
			}
		}
		
		insertSQLite("tempdata","CREATE TABLE IF NOT EXISTS mpays (pid TEXT UNIQUE NOT NULL,user INT NOT NULL,time INT NOT NULL)");
		$chk = fetchSqlite("tempdata","SELECT *FROM `mpays`");
		if(is_array($chk)){
			foreach($chk as $row){ $pays[$row['pid']]=$row['user']; }
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq" or $me["access_level"]=="region") ? "":" AND `paybill`='".getpaybill($me['branch'])."'";
		$cond= ($me['access_level']=="portfolio") ? " AND `paybill`='".getpaybill($me['branch'])."'":$show;
		$cond.= ($str) ? " AND (`client` LIKE '%$str%' OR `phone` LIKE '%$str%' OR `code` LIKE '%$str%' OR `account` LIKE '%$str%')":"";
		$states = array(1=>"Failed MPESA",2=>"Bank Payment",3=>"Cash Payment");
		
		$no=($perpage*$page)-$perpage; $trs=$brans="";
		if($db->istable(2,$ptbl)){
			$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `status` BETWEEN 1 AND 10 $cond ORDER BY date DESC $lim");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $name=prepare(ucwords($row['client'])); $dy=$row['date']; $payb=$row['paybill'];
					$phone=$row['phone']; $amnt=number_format($row['amount']); $desc=$states[$row['status']]; $code=$row['code'];
					$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
					$day=(date("d-m-Y",$ptm)==date("d-m-Y")) ? date("h:i a",$ptm):date("d-m-Y",$ptm); $frm="------";
					
					$act = (in_array("approve manual payments",$perms)) ? "<a href='javascript:void(0)' onclick=\"approvepay('$rid','1')\">Approve</a>":"";
					$act .= (in_array("delete client payment",$perms)) ? " | <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delpay('$rid')\">Delete</a>":"";
					if(isset($pays[$rid])){
						$frm = prepare(ucwords($db->query(2,"SELECT `name` FROM `org$cid"."_staff` WHERE `id`='$pays[$rid]'")[0]['name']));
					}
					
					if($access=="portfolio"){
						if(in_array($row['phone'],$portfolio) or isset($portfolio[$row['account']])){
							$trs.="<tr id='tr$rid'><td>$no</td><td>$name</td><td>$phone</td><td>$payb</td><td>$desc</td><td>$code</td><td>$amnt</td><td>$frm</td><td>$day</td><td>$act</td></tr>";
						}
					}
					else{
						$trs.="<tr id='tr$rid'><td>$no</td><td>$name</td><td>$phone</td><td>$payb</td><td>$desc</td><td>$code</td><td>$amnt</td><td>$frm</td><td>$day</td><td>$act</td></tr>";
					}
				}
			}
		}
		
		if($access=="hq" or $access=="region"){
			$brns="<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:160px' onchange=\"loadpage('payments.php?manual&bran='+this.value.trim())\">$brns</select>";
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` WHERE `status` BETWEEN 1 AND 10 $cond");
		$totals = ($sql) ? $sql[0]['total']:0;
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Pending Payments</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-bordered' style='width:100%;font-size:14px;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'>
							<input type='search' style='float:right' placeholder='&#xf002; Search payment' onkeyup=\"fsearch(event,'payments.php?manual&str='+cleanstr(this.value))\" 
							onsearch=\"loadpage('payments.php?manual&str='+cleanstr(this.value))\" value=\"$str\"> $brans
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td colspan='2'>Client</td><td>Phone</td><td>Paybill</td><td>Payment</td><td>Transaction</td><td>Amount</td><td>Creator</td><td colspan='2'>Date</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?manual&bran=$bran&str=".str_replace(" ","+",$str))."
		</div>";
		savelog($sid,"Accessed pending manual payments");
	}
	
	//incomig payments
	if(isset($_GET['payments'])){
		$view = trim($_GET['payments']);
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean(strtolower($_GET['str'])):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$ptbl = "org".$cid."_payments"; $portfolio =[];
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 40; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		if($access=="portfolio"){
			$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `loan_officer`='$sid' AND `balance`>0");
			if($res){
				foreach($res as $row){
					$portfolio[$row['client_idno']]=$row['phone'];
				}
			}
		}
		
		$vwc = (defined("USERS_VIEW_ALLPAYS")) ? USERS_VIEW_ALLPAYS:0;
		$states = array("Today","Previous"); $cond="";
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if(!$str){ $cond = ($view) ? "AND NOT `date` LIKE '".date('Ymd')."%'":"AND `date` LIKE '".date('Ymd')."%'"; }
		if(!$vwc){ $cond.= ($me['access_level']=="hq" or $me["access_level"]=="region") ? "":" AND `paybill`='".getpaybill($me['branch'])."'"; }
		$cond.= ($str) ? " AND (`client` LIKE '%$str%' OR `phone` LIKE '%$str%' OR `code` LIKE '%$str%' OR `account` LIKE '%$str%')":"";
		
		$no=($perpage*$page)-$perpage; $trs="";
		if($db->istable(2,$ptbl)){
			$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `status`='0' $cond ORDER BY date DESC $lim");
			if($res){
				foreach($res as $row){
					$no++; $rid=$row['id']; $name=prepare(ucwords($row['client'])); $dy=$row['date']; $payb=$row['paybill'];
					$phone=$row['phone']; $amnt=fnum($row['amount']); $acc=prepare($row['account']); $code=prepare($row['code']);
					$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
					$day=(date("d-m-Y",$ptm)==date("d-m-Y")) ? date("h:i a",$ptm):date("d-m-Y",$ptm); $sacc=prepstr($acc);
					
					$act = (in_array("confirm loan payment",$perms)) ? "<a href='javascript:void(0)' onclick=\"approvepay('$rid','0')\">Confirm</a>":"";
					$act .= (in_array("delete client payment",$perms)) ? " | <a href='javascript:void(0)' style='color:#ff4500' onclick=\"delpay('$rid')\">Delete</a>":"";
					$edit = (in_array("delete client payment",$perms)) ? "<i class='fa fa-pencil' style='cursor:pointer;color:#008fff;' onclick=\"editpay('$rid:account','$sacc')\"></i>":"";
					$edf = (in_array("delete client payment",$perms)) ? "<i class='fa fa-pencil' style='cursor:pointer;color:#008fff;' onclick=\"editpay('$rid:phone','$phone')\"></i>":"";
					
					if($access=="portfolio"){
						if(in_array($row['phone'],$portfolio) or array_key_exists($row['account'],$portfolio)){
							$trs.= "<tr id='tr$rid'><td>$no</td><td>$name</td><td id='fpy$rid'>$edf $phone</td><td>$payb</td><td id='pcc$rid'>$edit $acc</td><td>$code</td><td>$amnt</td>
							<td>$day</td><td>$act</td></tr>";
						}
					}
					else{
						$trs.= "<tr id='tr$rid'><td>$no</td><td>$name</td><td id='fpy$rid'>$edf $phone</td><td>$payb</td><td id='pcc$rid'>$edit $acc</td><td>$code</td><td>$amnt</td>
						<td>$day</td><td>$act</td></tr>";
					}
				}
			}
		}
		
		$grups=$brans="";
		foreach($states as $key=>$des){
			$cnd=($view==$key) ? "selected":"";
			$grups.="<option value='$key' $cnd>$des</option>";
		}
		
		if($access=="hq" or $access=="region"){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:160px' onchange=\"loadpage('payments.php?payments=$view&bran='+this.value.trim())\">$brns</select>";
		}
		
		$import = (in_array("approve manual payments",$perms)) ? "<button class='bts' style='float:right;font-size:15px;padding:4px;margin-left:7px' 
		onclick=\"popupload('payments.php?import')\"><i class='fa fa-level-up'></i> Import</button>":"";
		$pay = (in_array("make manual payments",$perms)) ? "<button class='bts' style='float:right;font-size:15px;padding:4px;' 
		onclick=\"popupload('payments.php?makepay')\"><i class='fa fa-plus'></i> Payment</button>":"";
		$bals = (in_array("view paybill balances",$perms)) ? "<button class='bts' style='float:right;font-size:15px;padding:4px;margin-right:10px' 
		onclick=\"popupload('payments.php?balances')\">Balances</button>":"";
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM(amount) AS tsum FROM `$ptbl` WHERE `status`='0' $cond");
		$totals = ($sql) ? intval($sql[0]['total']):0; $sum=($sql) ? fnum(intval($sql[0]['tsum'])):0;
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;'>
			<div class='container' style='padding:5px;max-width:1300px;padding-top:0px'>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped' style='width:100%;font-size:14px;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'>
							<h3 style='font-size:22px;color:#191970'>$backbtn Payments (Ksh $sum) $import $pay $bals</h3><hr>
							<input type='search' style='float:right' placeholder='&#xf002; Search payment' onkeyup=\"fsearch(event,'payments.php?payments=$view&str='+cleanstr(this.value))\" 
							onsearch=\"loadpage('payments.php?payments=$view&str='+cleanstr(this.value))\" value=\"$str\">
							<select style='width:150px' onchange=\"loadpage('payments.php?payments='+cleanstr(this.value))\">$grups</select> $brans
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td colspan='2'>Client</td><td>Phone</td><td>Paybill</td><td>Account</td><td>Transaction</td><td>Amount</td><td colspan='2'>Date</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?payments=$view&bran=$bran&str=".str_replace(" ","+",$str))."
		</div>";
		
		savelog($sid,"Accessed Unposted Payments"); 
	}
	
	# import payments
	if(isset($_GET['import'])){
		echo "<div style='padding:10px;max-width:350px;margin:0 auto;'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Import MPESA Payments</h3><br>
			<p style='font-size:14px'>Download Excel document from <u>safaricom Mpesa Org portal</u> for the payments done within specific duration
			then upload as it is. <b>DONT</b> Edit anything<p>
			<form method='post' id='pform' onsubmit='importpays(event)'>
				<table style='width:100%;margin-top:15px' cellpadding='5'>
					<tr><td>Select downloaded Excel File<br><input type='file' id='pxls' name='pxls' accept='.csv,.xls,.xlsx' required></td>
					<td><button class='btnn' style='float:right'>Upload</button></td></tr>
				</table>
			</form><br>
		</div><br>";
	}
	
	//merged payments
	if(isset($_GET['merged'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$perpage = 40; $lim=getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		$stbl = "org$cid"."_staff"; $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$trs=$brans="";
		if($db->istable(2,"mergedpayments$cid")){
			$res = $db->query(2,"SELECT confirmed,idno,GROUP_CONCAT(code) AS codes, GROUP_CONCAT(amount) AS amounts,GROUP_CONCAT(time) AS times FROM `mergedpayments$cid` 
			WHERE `status`='0' $cond GROUP BY idno ORDER BY id DESC");
			if($res){
				foreach($res as $row){
					$conf=$staff[$row['confirmed']]; $idno=$row['idno']; $codes=explode(",",$row['codes']); $amnts=explode(",",$row['amounts']); 
					$durs=explode(",",$row['times']); $amnt=0; $lis=""; 
					
					foreach($codes as $key=>$code){
						$pay=number_format($amnts[$key]); $amnt+=$amnts[$key];
						$lis.="<li>$code Ksh $pay merged by <b>$conf</b> on <b>".date("d-m-Y,H:i",$durs[$key])."</b></li>";
					}
					
					$name = (isset($staff[$idno])) ? $staff[$idno]:$db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['name'];
					$trs.= "<tr valign='top' id='$idno'><td>".prepare(ucwords($name))."<br>Idno: $idno</td><td>$lis</td><td>".number_format($amnt)."<br>
					<a href='javascript:void(0)' onclick=\"assignmerged('$idno')\">Assign</a></td></tr>";
				}
			}
		}
		
		if($access=="hq" or $access=="region"){
			$opts = "<option value='0'> Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `mergedpayments$cid` WHERE `status`=0 $add");
			if($res){
				$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
				foreach($qri as $row){
					$bnames[$row['id']]=prepare(ucwords($row['branch']));
				}
				
				foreach($res as $rw){
					$bid=$rw['branch']; $bname=$bnames[$bid]; $cnd=($bid==$bran) ? "selected":"";
					$opts.="<option value='$bid' $cnd>$bname</option>";
				}
			}
			
			$brans ="<select onchange=\"loadpage('payments.php?merged&bran='+this.value)\" style='width:180px'>$opts</select>";
		}
		
		$sql = $db->query(2,"SELECT COUNT(*) AS total FROM `mergedpayments$cid` AS mg WHERE `status`='0' $cond");
		$totals = ($sql) ? $sql[0]['total']:0;
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Merged Payments</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-bordered' style='width:100%;font-size:14px;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'> $brans </caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td>Client</td><td>Transactions</td><td>Total Merged</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?merged&bran=$bran")."
		</div>";
		savelog($sid,"Viewed Merged Payments");
	}
	
	# wallet topup
	if(isset($_GET["wtopup"])){
		$wid = trim($_GET["wtopup"]);
		$vtp = trim($_GET["wtp"]);
		$uid = trim($_GET["uid"]);
		$frm = (isset($_GET["dfro"])) ? trim($_GET["dfro"]):"mpesa";
		
		$ttls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings");
		$vbk = $ttls[$vtp]; $udf=explode(":",$uid); 
		
		$me = staffInfo($sid); $opts=$lis=$disb=$caac="";
		$perms = array_map("trim",getroles(explode(",",$me['roles'])));
		$types = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
		
		$qri = $db->query(2,"SELECT *FROM `".$types[$udf[1]]."` WHERE `id`='".$udf[0]."'"); 
		$fon = $qri[0]['contact']; $idno=$qri[0]['idno']; $bid=(isset($qri[0]["branch"])) ? $qri[0]['branch']:0;
		$accs = walletAccount($bid,$wid); $acc=$accs[$vtp];
		
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		if($qri){
			$sett = json_decode($qri[0]['value'],1); $caac=(isset($sett["Cash"])) ? $sett["Cash"]:0;
		}
		
		$list = array("mpesa"=>"Direct from MPESA");
		if(in_array("manage wallet deposits",$perms)){ $list["pndpays"]="From Suspense Account"; $list["assetbks"]="From Asset Accounts"; }
		if($caac && in_array("receive teller cash",$perms)){ $list["cash"]="Receive Cash"; }
		foreach($list as $tp=>$txt){
			$cnd = ($tp==$frm) ? "selected":"";
			$lis.= "<option value='$tp' $cnd>$txt</option>";
		}
		
		if($frm=="mpesa"){
			$cond = ($bid) ? "`id`='$bid'":"NOT `paybill`='0'";
			$payb = $db->query(1,"SELECT `paybill` FROM `branches` WHERE $cond")[0]['paybill'];
			$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
			$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
			$ckey = (isset($keys[$payb])) ? $keys[$payb]["ckey"]:""; $csec=(isset($keys[$payb])) ? $keys[$payb]["csec"]:""; 
			
			$data = "<p>Automatic topup via Paybill $payb using <b>Account No: $acc</b></p>";
			$data.= ($pkey) ? "<input type='hidden' name='pkey' value='$pkey'> <input type='hidden' name='accno' value='$acc'> 
			<input type='hidden' name='ckey' value='$ckey'> <input type='hidden' name='csec' value='$csec'> 
			<input type='hidden' name='paybno' value='$payb'><p>MPESA Phone number<br><input type='number' id='pfon' name='payfro' style='width:100%' value='0$fon' required></p>
			<p>Amount to Pay<br><input type='number' id='paym' style='width:100%' name='pamnt' required></p><br><p style='text-align:right'><button class='btnn'>Request</button></p>":"";
		}
		elseif($frm=="pndpays"){
			$spec = (defined("ALLOW_DEPOSIT_ALLPAYS")) ? ALLOW_DEPOSIT_ALLPAYS:[]; 
			$payall = (defined("DEPOSIT_ALLPAYS_GROUPS")) ? DEPOSIT_ALLPAYS_GROUPS:["super user","director"]; 
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `status`='0' ORDER BY `client` ASC");
			if($sql){
				foreach($sql as $row){
					$pid=$row['id']; $name=prepare(ucwords($row['client'])); $amnt=fnum($row['amount']); $acc=$row['account']; $code=$row["code"];
					if(in_array(strtolower($me["position"]),$payall)){ $opts.="<option value='$pid'>$name ($code Ksh $amnt)</option>"; }
					else{ $opts.= (in_array($acc,[$fon,$idno,"0$fon"]) or in_array($sid,$spec)) ? "<option value='$pid'>$name ($code Ksh $amnt)</option>":""; }
				}
			}
			
			$disb = ($opts) ? "":"disabled style='cursor:not-allowed'";
			$opts = ($opts) ? $opts:"<option value='0'>-- No matching Payments --</option>";
			$data = "<p>Select Unallocated Payment<br><select style='width:100%;font-size:15px' name='wlpay' required>$opts</select></p><br>
			<p style='text-align:right'><button class='btnn' $disb><i class='bi-arrow-return-right'></i> Confirm</button></p>";
		}
		else{
			if($frm!="cash"){
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`wing` LIKE '2,6%' OR `wing` LIKE '2,7%') ORDER BY `account` ASC");
				if($sql){
					foreach($sql as $row){
						$rid = $row['id']; $acc=prepare(ucfirst($row['account']));
						$opts.= (!in_array($rid,[14,15,22,23]) && $rid!=$caac) ? "<option value='$rid'>$acc</option>":"";
					}
				}
				$disb = ($opts) ? "":"disabled style='cursor:not-allowed'";
				$opts = ($opts) ? $opts:"<option value='0'>-- No Cash Accounts found --</option>";
			}
			
			$now = date("Y-m-d")."T".date("H:i");
			$dep = ($frm=="cash") ? "<input type='hidden' name='dpfrm' value='cash:$caac'>":
			"<p>Deposit from<br><select style='width:100%;font-size:15px' name='dpfrm' required>$opts</select></p>";
			$data = "$dep <p>Deposit Date<br><input type='datetime-local' style='width:100%' name='dpday' max='$now' value='$now' required></p>
			<p>Amount <span style='float:right'>Transaction Ref</span><br><input type='number' style='width:49%' name='tamnt' required>
			<input type='text' style='width:49%;float:right' name='dtrans' placeholder='Optional'></p>
			<p>Comments<br><input type='text' style='width:100%' name='dcom' placeholder='Optional'></p><br>
			<p style='text-align:right'><button class='btnn' $disb><i class='bi-wallet'></i> Deposit</button></p>";
		}
		
		echo "<div style='padding:10px;max-width:350px;margin:0 auto'>
			<h3 style='text-align:center;color:#191970;font-size:23px'>Deposit to $vbk Wallet</h3><br>
			<form method='post' id='wform' onsubmit=\"topupwallet(event,'$frm')\">
				<input type='hidden' name='cwid' value='$wid'> <input type='hidden' name='walletp' value='$vtp'>
				<p>Select Deposit Method<br><select style='width:100%;font-size:15px' onchange=\"popupload('payments.php?wtopup=$wid&wtp=$vtp&uid=$uid&dfro='+this.value)\" 
				name='tfrm' required>$lis</select></p> $data <br>
			</form>
		</div>";
	}
	
	# withdraw/transfer from wallet
	if(isset($_GET["wfrom"])){
		$wid = trim($_GET["wfrom"]);
		$vtp = trim($_GET["wtp"]);
		$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles']));
		
		$ttls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings");
		$wtp = ($vtp=="real") ? "balance":$vtp; $vbk=$ttls[$wtp]; $bal=round(walletBal($wid,$wtp));
		$disb = ($bal>0) ? "":"disabled style='cursor:not-allowed'"; 
		$opts=$caac=$scl=$lis=""; $setts=[];
		
		if($vtp=="real"){
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='wallet_accounts' OR `setting`='wallet_withdrawal_fees')");
			if($qri){
				foreach($qri as $row){ $setts[$row["setting"]]=json_decode($row['value'],1); } 
			}
			
			if(isset($setts["wallet_accounts"])){
				$sett = $setts["wallet_accounts"]; $caac=(isset($sett["Cash"])) ? $sett["Cash"]:0;
				$opts.= ($caac && in_array("transfer teller cash",$perms)) ? "<option value='cash:$caac'>Teller Cash Account</option>":"";
			}
			
			$reverse = (defined("REIMBURSE_WALLET_FUNDS")) ? REIMBURSE_WALLET_FUNDS:1;
			$opts.= "<option value='mpesa'>MPESA via B2C</option>";
			$opts.= ($reverse) ? "<option value='reimb'>Reimburse Client</option>":"";
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`wing` LIKE '2,6%' OR `wing` LIKE '2,7%') ORDER BY `account` ASC");
			if($sql){
				foreach($sql as $row){
					$rid = $row['id']; $acc=prepare(ucfirst($row['account']));
					$opts.= (!in_array($rid,[14,15,22,23]) && $rid!=$caac) ? "<option value='$rid'>$acc</option>":"";
				}
			}
			
			$now = date("Y-m-d")."T".date("H:i");
			$fees = (isset($setts["wallet_withdrawal_fees"])) ? $setts["wallet_withdrawal_fees"]:B2C_RATES;
			$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
			$clt = $chk[0]["client"]; $ctp=$chk[0]["type"]; $tbl=($ctp=="client") ? "org$cid"."_clients":"org$cid"."_staff";
			$fon = $db->query(2,"SELECT `contact` FROM `$tbl` WHERE `id`='$clt'")[0]["contact"];
			
			
			echo "<div style='padding:10px;max-width:350px;margin:0 auto'>
				<h3 style='text-align:center;color:#191970;font-size:23px'>Initial Wallet Withdrawal</h3><br>
				<form method='post' id='wfom' onsubmit=\"walletdeduct(event)\">
					<input type='hidden' name='dedwid' value='$wid'> 
					<p>Withdrawal Account<br><select style='width:100%;font-size:15px' onchange=\"chektype('$wid',this.value)\" name='dedfrm' required>$opts</select></p>
					<p>Receiver MPESA Number<br><input type='number' name='dedto' value='0$fon' style='width:100%' required></p>
					<p>Amount <span style='float:right'>Transaction Ref</span><br><input type='number' value='$bal' max='$bal' onkeyup=\"checkfee(this.value)\" style='width:49%' 
					name='tamnt' required> <input type='text' style='width:49%;float:right' name='dtrans' placeholder='Optional'></p>
					<p>Transaction Date<br><input type='datetime-local' style='width:100%' name='wtday' max='$now' value='$now' required></p>
					<p>Comments<br><input type='text' style='width:100%' name='dcom' placeholder='Optional'></p><br>
					<p style='text-align:right'><span style='float:left' id='fsp'></span><button class='btnn' $disb><i class='bi-box-arrow-right'></i> Process</button></p><br>
				</form>
			</div><script>
				var fees = ".json_encode($fees,1)."; checkfee($bal);
				function checkfee(val){
					var sum = parseInt(val),fee=0;
					if(sum>0){
						for(var i in fees){
							var range = i.split('-');
							if(sum>=parseInt(range[0]) && sum<=parseInt(range[1])){ fee=fees[i]; }
						}
						$('#fsp').html('<span style=\"padding:8px;background:#EEE8AA;color:#000080\">Charges: Ksh '+fee+'</span>');
					}
					else{ $('#fsp').html(''); }
				}
				
				function chektype(wid,vtp){
					if(vtp=='reimb'){ popupload('payments.php?reimbwallet=$wid'); }
				}
				
			</script>";
		}
		else{
			$tcl = (isset($_GET["tcl"])) ? trim($_GET["tcl"]):"";
			$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'"); $actp=$chk[0]['type']; $uid=$chk[0]['client'];
			$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
			if($actp=="investor"){ $def = array("investbal"=>"Investment Account"); }
			elseif($wtp=="savings"){ $def = array("balance"=>"Transactional Account"); }
			else{
				$def = ["investbal"=>"Investment Account","loan"=>"Loan Repayment","penalty"=>"Penalty & Loan Repayment"];
				if($wtp=="balance"){ $def["savings"]="Savings Account"; }
			}
			
			if(in_array("perform inter-wallet transfers",$perms) && $wtp=="balance"){ $def["client"]="Another $actp Account"; }
			if(in_array("manage wallet withdrawals",$perms) && $wtp=="balance"){ $def["reserve"]="Bad Debt Reserve"; }
			foreach($def as $key=>$txt){
				$cnd = ($key==$tcl) ? "selected":"";
				$opts.= "<option value='$key' $cnd>$txt</option>"; 
			}
			
			if($tcl=="client"){
				$sql = $db->query(2,"SELECT `idno`,`name` FROM `$tbls[$actp]` WHERE `status`<2 ORDER BY `name` ASC");
				foreach($sql as $row){
					$idno=$row['idno']; $name=prepare(ucwords($row['name']));
					$lis.= "<option value='$idno'>$name</option>";
				}
				$scl = "<p id='cinp'>".ucwords($actp)." Idno<br><datalist id='cls'>$lis</datalist><input type='text' list='cls' style='width:100%' name='clidno' required><p>";
			}
			elseif(in_array($tcl,["loan","penalty"])){
				$col = ($actp=="client") ? "client_idno":"stid"; $tbl=($col=="stid") ? "staff_loans$cid":"org$cid"."_loans";
				$idno = ($actp=="client") ? $db->query(2,"SELECT `idno` FROM `$tbls[$actp]` WHERE `id`='$uid'")[0]['idno']:$uid;
				$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `$col`='$idno' AND (balance+penalty)>0"); $lis="";
				if($qri){
					if(count($qri)>1){
						foreach($qri as $row){
							$lid = $row["loan"]; $prd=$row["loan_product"]; $sum=fnum($row["balance"]+$row["penalty"]); $tid=$row["tid"];
							$chk = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'"); $pname=ucfirst(prepare($chk[0]["product"])); 
							if($chk[0]["category"]=="asset"){
								$ltp = $db->query(2,"SELECT `loantype` FROM `org$cid"."_loantemplates` WHERE `id`='$tid'")[0]["loantype"];
								$fin = $db->query(2,"SELECT `asset_name` FROM `finassets$cid` WHERE `id`='".explode(":",$ltp)[2]."'");
								$pname = prepare(ucfirst($fin[0]["asset_name"]));
							}
							$lis.= "<option value='$lid'>$pname (Ksh $sum)</option>";
						}
						$scl = "<p>Select Loan<br><select style='width:100%' name='paylid'>$lis</select></p>";
					}
					else{ $scl = "<input type='hidden' name='paylid' value='".$qri[0]["loan"]."'>"; }
				}
			}
			
			echo "<div style='padding:10px;max-width:320px;margin:0 auto'>
				<h3 style='text-align:center;color:#191970;font-size:23px'>Transfer from $vbk Account</h3><br>
				<form method='post' id='wfom' onsubmit=\"transwallet(event)\">
					<input type='hidden' name='transwid' value='$wid'> <input type='hidden' name='transfro' value='$vtp'>
					<p>Transfer To<br><select style='width:100%' name='transto' id='tto' onchange='checkopt(this.value)'>$opts</select></p> $scl
					<p>Transfer Amount<br><input type='number' name='tamnt' value='$bal' max='$bal' style='width:100%' required></p><br>
					<p style='text-align:right'><button class='btnn' $disb>Tranfer</button></p><br>
				</form>
			</div>
			<script>
				function checkopt(val){
					if(val=='client' || val=='loan' || val=='penalty'){ popupload('payments.php?wfrom=$wid&wtp=$vtp&tcl='+val); }
					else{ $('#cinp').hide(); }
				}
			</script>";
		}
	}
	
	//reversals
	if(isset($_GET['reversals'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$ptbl = "org".$cid."_payments"; $perpage=20;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage);
		
		$stbl="org$cid"."_staff"; $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq" or $me["access_level"]=="region") ? "":" AND `paybill`='".getpaybill($me['branch'])."'";
		$cond = ($me['access_level']=="portfolio") ? " AND `paybill`='".getpaybill($me['branch'])."'":$show;
		$cond.= ($str) ? " AND (`client` LIKE '%$str%' OR `phone` LIKE '%$str%' OR `code` LIKE '%$str%' OR `account` LIKE '%$str%')":"";
		
		$no=($perpage*$page)-$perpage; $trs="";
		if($db->istable(2,$ptbl)){
			$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `status` LIKE 'REV-%' $cond ORDER BY date DESC $lim");
			if($res){
				foreach($res as $row){
					$no++; $name=prepare(ucwords($row['client'])); $dy=$row['date']; $rev=explode("-",$row['status']);
					$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
					$day=date("d-m-Y",$ptm); $phone=$row['phone']; $amnt=fnum($row['amount']); $acc=prepare($row['account']); $code=prepare($row['code']);
					if(is_numeric($rev[1])){ $revs=date("d.m.Y",$rev[1])." - $amnt"; $user=$staff[$rev[2]]; }
					else{
						$chk = $db->query(2,"SELECT *FROM `payouts$cid` WHERE `code`='$rev[1]'");
						if($chk){ $revs=date("d.m.Y",$chk[0]['time'])." - ".fnum($chk[0]['amount']); $user=$staff[$chk[0]['user']]; }
					}
					
					$trs.= "<tr><td>$no</td><td>$code</td><td>$name</td><td>$phone</td><td>$amnt</td><td>$acc</td><td>$day</td><td>$revs</td><td>$user</td></tr>";
				}
			}
		}
		
		$trs = ($trs) ? $trs:"<tr><td colspan='8'>No Reversal payments Found</td></tr>";
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM(amount) AS tsum FROM `$ptbl` WHERE `status` LIKE 'REV-%' $cond");
		$totals = ($sql) ? $sql[0]['total']:0; $tsum=($sql) ? $sql[0]["tsum"]:0;
		
		echo "<div class='cardv' style='max-width:1300px;min-height:300px;overflow:auto;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn C2B Reversals (Ksh ".fnum($tsum).")</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped' style='width:100%;font-size:14px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top'>
						<input type='search' placeholder='&#xf002; Search Payment' onsearch=\"loadpage('payments.php?reversals&str='+cleanstr(this.value))\"
						onkeyup=\"fsearch(event,'payments.php?reversals&str='+cleanstr(this.value))\" value=\"".prepare($str)."\"></caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td colspan='2'>Transaction</td><td>Client</td><td>Phone</td><td>Payment</td><td>Account</td><td>Date</td><td>Reversal</td><td>Approval</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?reversals&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed C2B Reversals");
	}
	
	//overpayments
	if(isset($_GET['overpays'])){
		$page = (isset($_GET['pg'])) ? clean($_GET['pg']):1;
		$str = (isset($_GET['str'])) ? intval($_GET['str']):null;
		$bran = (isset($_GET['bran'])) ? clean($_GET['bran']):0;
		$ptbl = "org".$cid."_payments"; $perpage=20;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage);
		$cnf = json_decode($me["config"],1);
		
		$stbl="org$cid"."_staff"; $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		$cond.= ($str) ? " AND `idno`='$str'":"";
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$no=($perpage*$page)-$perpage; $trs=$brans=""; $total=0;
		if($db->istable(2,"overpayments$cid")){
			$res = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `status`='0' $cond ORDER BY `id` DESC $lim");
			if($res){
				foreach($res as $row){
					$pid=$row['payid']; $amnt=number_format($row['amount']); $rid=$row['id']; $total+=$row['amount'];
					$day=date("d-m-Y,H:i",$row['time']); $idno=$row['idno']; $no++; $lis="";
					$tsum = $db->query(2,"SELECT `amount` FROM `$ptbl` WHERE `id`='$pid'")[0]['amount'];
					
					$sql = $db->query(2,"SELECT *,SUM(amount) AS pay FROM `processed_payments$cid` WHERE `payid`='$pid' GROUP BY payment");
					if($sql){
						foreach($sql as $rw){
							$pay=$rw['payment']; $code=$rw['code']; $pamnt=number_format($rw['pay']); 
							$client=prepare(ucwords($rw['client'])); $conf=$staff[$rw['confirmed']];
							$lis.= "<li>$pay - $pamnt</li>";
						}
					}
					
					$atp = sys_constants("overpayment"); $vtxt=($atp) ? "Transfer":"Assign";
					$asg = (in_array("assign overpayment",$perms)) ? "<a href='javascript:void(0)' onclick=\"assignoverpay('$rid','$atp')\">$vtxt</span>":"";
					$del = (in_array("delete client payment",$perms)) ? " | <a href='javascript:void(0)' onclick=\"deloverpay('$rid')\" style='color:#ff4500'>Delete</span><br>":"";
					$trs.= "<tr valign='top' id='rec$rid'><td>$no</td><td>$client<br>Idno: $idno</td><td>$amnt</td><td>$code</td><td>".number_format($tsum)."</td>
					<td>$lis</td><td>$day</td><td>$conf<br>$asg $del</td></tr>";
				}
			}
		}
		
		if($access=="hq" or $access=="region"){
			$opts = "<option value='0'> Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `overpayments$cid` WHERE `status`=0 $add");
			if($res){
				$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
				foreach($qri as $row){
					$bnames[$row['id']]=prepare(ucwords($row['branch']));
				}
				
				foreach($res as $rw){
					$bid=$rw['branch']; $cnd=($bid==$bran) ? "selected":"";
					$opts.= (isset($bnames[$bid])) ? "<option value='$bid' $cnd>".$bnames[$bid]."</option>":"";
				}
			}
			$brans = "<select onchange=\"loadpage('payments.php?overpays&bran='+this.value)\" style='width:160px;font-size:15px'>$opts</select>";
		}
		
		$trs = ($trs) ? $trs:"<tr><td colspan='8'>No Payments Found</td></tr>";
		$sql = $db->query(2,"SELECT COUNT(*) AS total,SUM(amount) AS tsum FROM `overpayments$cid` WHERE `status`='0' $cond");
		$totals = ($sql) ? $sql[0]['total']:0; $tsum=($sql) ? $sql[0]["tsum"]:0;
		if(!$bran && $access=="hq"){
			balance_book(DEF_ACCS['overpayment'],$tsum);
		}
		
		echo "<div class='cardv' style='max-width:1300px;min-height:300px;overflow:auto;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Overpayments (Ksh ".number_format($tsum).")</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped' style='width:100%;font-size:14px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top'>$brans 
						<input type='search' style='float:right' placeholder='&#xf002; Search Idno' onsearch=\"loadpage('payments.php?overpays&bran=$bran&str='+cleanstr(this.value))\"
						onkeyup=\"fsearch(event,'payments.php?overpays&bran=$bran&str='+cleanstr(this.value))\" value=\"$str\"></caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px' valign='top'>
						<td colspan='2'>Client</td><td>Overpay</td><td>Transaction</td><td>Amount</td><td>Description</td><td>Date</td><td>Approval</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?overpays&bran=$bran&str=".urlencode($str))."
		</div>";
		savelog($sid,"Viewed Client Overpayments");
	}
	
	#payin summary
	if(isset($_GET['payins'])){
		$mon=(isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$monend=date("j",monrange(date("m",$mon),date("Y",$mon))[1]);
		$first_day=date('w',mktime(0,0,0,date("m",$mon),1,date("Y",$mon)));
		$first_day=($first_day==0) ? 7:$first_day; $opts="";
		
		$branches = []; $btot=0;
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		foreach($qri as $row){
			$branches[$row['paybill']]=prepare(ucwords($row['branch'])); $btot++;
		}
		
		$weekdays=array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
		$thead="<tr style='text-align:center;color:#191970;font-weight:bold;background:#e6e6fa'>";
		foreach($weekdays as $wkdy){
			$thead.="<td><p style='margin:6px'>$wkdy</p></td>";
		}
		
		$thead.="</tr>"; $trs=($first_day>1) ? "<tr valign='top'><td colspan='".($first_day-1)."'></td>":"<tr valign='top'>";
		$me = staffInfo($sid); $access=$me['access_level'];
		$cond = ($access=="hq" or $access=="region") ? "":"AND `paybill`='".getpaybill($me['branch'])."'";
		
		for($i=1; $i<=$monend; $i++){
			$dy=($i<10) ? "0$i":$i; $day=strtotime(date("Y-M",$mon)."-".$dy); 
			$wkdy=date("D",$day); $pos=($i+$first_day-1)%7;
			$res = $db->query(2,"SELECT *FROM `payin$cid` WHERE `day`='$day' $cond");
			
			if($res){
				$tds=""; $tot=$no=0;
				foreach($res as $row){
					$pbl=$row['paybill']; $bpb=($btot>1 && count(array_keys($branches))>1) ? $branches[$pbl]:"Corporate $pbl"; 
					$payb = ($btot==1) ? $branches[$row['paybill']]:$bpb; $amnt=fnum($row['amount']); $tot+=$row['amount']; $no++;
					$vw = ($row["amount"]>0 && $btot>1) ? "<i class='bi-printer' onclick=\"printdoc('payments.php?vtp=c2b&dy=$day&pb=$pbl','tpays')\"
					style='float:right;cursor:pointer;color:#00008B;font-size:17px;margin-left:5px' title='Print Payments'></i>":"";
					$tds.="<tr><td>$payb</td><td style='text-align:right'>$amnt $vw</td></tr>";
				}
				
				$vwp = ($no>1 && $tot>0) ? "<i class='bi-printer' onclick=\"printdoc('payments.php?vtp=c2b&dy=$day&pb=all','tpays')\" 
				style='float:right;cursor:pointer;color:#00008B;font-size:17px;margin-left:5px'></i>":"";
				$tds.= ($no>1) ? "<tr><td colspan='2' style='text-align:right;font-weight:bold'>".fnum($tot)." $vwp</td></tr>":"";
				$trs.="<td><table cellpadding='5' style='width:100%;font-size:14px' class='table-bordered'>
					<tr style='background:#f0f0f0;text-align:center;color:#4682b4'><td colspan='2'><b>".date("d-m-Y",$day)."</b></td></tr> $tds
				</table></td>";
			} 
			else{ $trs.="<td></td>"; }
			$trs.= ($pos==0) ? "</tr><tr valign='top'>":"";
		}
		
		$trs.="</tr>";
		$thismon = strtotime(date("Y-M")); $tyr=date("Y");
		$opts = "<option value='$thismon'>".date("F Y")."</option>";
		$qry = $db->query(2,"SELECT DISTINCT `month` FROM `payin$cid` WHERE NOT `month`='$thismon' AND `year`='$yr' ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mn==$mon)? "selected":"";
				$opts.="<option value='$mn' $cnd>".date('F Y',$mn)."</option>";
			}
		}
		
		$yrs = "<option value='$tyr'>$tyr</option>";
		$qry = $db->query(2,"SELECT DISTINCT `year` FROM `payin$cid` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
		if($qry){
			foreach($qry as $row){
				$year=$row['year']; $cnd=($yr==$year)? "selected":"";
				$yrs.="<option value='$year' $cnd>$year</option>";
			}
		}
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;min-width:600px'>
			<div class='container' style='padding:7px;min-width:600px;max-width:1300px'>
			<h3 style='font-size:22px;color:#191970'>Daily Paybill Collection
			<select style='float:right;padding:4px;width:150px;' onchange=\"loadpage('payments.php?payins&year=$yr&mon='+this.value)\">$opts</select>
			<select style='float:right;padding:4px;width:80px;margin-right:10px' onchange=\"loadpage('payments.php?payins&year='+this.value)\">$yrs</select></h3>
			<table cellpadding='0' cellspacing='0' style='width:100%;font-size:15px'> $thead $trs </table>
			</div>
		</div>";
		
		savelog($sid,"View Daily paybill collection for ".date("F Y",$mon));
	}
	
	//make payment
	if(isset($_GET['makepay'])){
		$src = trim($_GET["makepay"]);
		$me = staffInfo($sid); 
		$access=$me['access_level'];
		$lis=$tin=$opts="";
		
		if($src=="invs"){
			
		}
		else{
			$cond = ($access=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$cond = ($access=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
			$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE NOT `status`='2' $cond");
			if($res){
				foreach($res as $row){
					$lis.= "<option value='".$row['idno']."'>".prepare(ucwords($row['name']))."</option>";
				}
			}
		}
		
		if($db->istable(2,"investors$cid") && $access=="hq"){
			$sql = $db->query(2,"SELECT *FROM `investors$cid` WHERE `type`='investor' ORDER BY `name` ASC");
			if($sql){
				if($src=="invs"){
					foreach($sql as $row){ $lis.= "<option value='".$row['idno']."'>".prepare(ucwords($row['name']))."</option>"; }
				}
				
				foreach(array("clients"=>"Clients Record","invs"=>"Investors Record") as $key=>$val){
					$cnd = ($src==$key) ? "selected":""; $opts.="<option value='$key' $cnd>$val</option>";
				}
				$tin = "<p>Select Source<br><select style='width:100%' name='psrc' onchange=\"popupload('payments.php?makepay='+this.value)\">$opts</select></p>";
			}
		}
		
		$dtx = ($src=="invs") ? "Investor":"Client";
		echo "<div style='max-width:300px;margin:0 auto;padding:10px'>
			<h3 style='font-size:23px;text-align:center'>Create Manual Payment</h3><br>
			<form method='post' id='pform' onsubmit=\"makepay(event)\">
				<datalist id='clients'>$lis</datalist> $tin
				<p>$dtx Idno<br><input type='text' list='clients' name='client' autocomplete='off' required></p>
				<p>Amount<br><input type='text' name='pamnt' onkeyup=\"valid('amnt',this.value)\" id='amnt' required></p>
				<p>Payment Source<br><select name='paytype' style='width:100%' id='paytype' onchange=\"checktp(this.value)\">
				<option value=''>-- Select --</option> <option value='mpesa'>Failed MPESA</option> <option value='cheque'>Bank Payment</option>
				<option value='cash'>Cash Payment</option></select></p>
				<p id='ptp'>Transaction Code/Cheque No<br><input type='text' name='pcode' style='text-transform:uppercase' required></p>
				<p>Payment Date & Time<br><input type='datetime-local' name='pday' max='".date('Y-m-d')."T".date('H:i')."' required></p><br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
		
		savelog($sid,"Accessed Manual Payment Form");
	}
	
	# confirm payment type
	if(isset($_GET['confpay'])){
		$pid = trim($_GET['confpay']);
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$ptp = (isset($_GET['paytp'])) ? clean($_GET['paytp']):"";
		$src = (isset($_GET['psrc'])) ? clean($_GET['psrc']):"client";
		$ptbl = "org".$cid."_payments"; $ols="";
		
		$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'");
		$row = $res[0]; $status=$row['status']; $amnt=$row['amount']; $idno=$macc=$row['account']; $pb=$row['paybill'];
		$phone=$row['phone']; $name=prepare(ucwords($row['client'])); $code=$row['code'];
		
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$access=$me['access_level']; $perms = getroles(explode(",",$me['roles']));
		$cond = ($access=="hq") ? 1:"`branch`='".$me['branch']."'";
		$cond = $def= ($access=="portfolio") ? "`loan_officer`='$sid'":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? setRegion($cnf["region"]):$cond;
		
		if($str){
			$list = array("client"=>["name","idno","contact"],"staff"=>["name","idno","contact"],"group"=>["group_name","group_id"]);
			foreach($list[$src] as $col){ $scol[]="`$col` LIKE '%$str%'"; }
			$cond.= " AND (".implode(" OR ",$scol).")";
		}
		else{ $cond.= (in_array($src,["client","staff"])) ? " AND (`idno`='$idno' OR `contact`='$phone')":" AND (`group_name` LIKE '%$idno%' OR `group_id`='$idno')"; }
		
		$sql = $db->query(2,"SELECT client FROM `org$cid"."_loantemplates` WHERE `payment`='$code' AND NOT `status`='9'");
		if($status or $sql){
			$txt = ($sql) ? "Payment is linked to Loan Application for ".prepare(ucwords($sql[0]['client'])):"Payment already approved";
			echo "<div style='max-width:350px;margin:0 auto'><br><br><p style='text-align:center;color:#ff4500;background:#FFE4C4;border:1px solid pink;padding:10px;'>
			<i class='bi-info-circle' style='font-size:30px'></i><br> $txt</p></div>";
		}
		else{
			$trs = $opts="";
			if($src=="group"){
				if($db->istable(2,"cgroups$cid")){
					$qry = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE ".str_replace("loan_officer","id",$cond));
					if($qry){
						$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
						foreach($sql as $row){ $brans[$row["id"]]=prepare(ucwords($row["branch"])); }
						foreach($qry as $row){
							$name=prepare(ucwords($row['group_name'])); $gid=$row['group_id']; $rid=$row['id']; $bname=$brans[$row["branch"]];
							$trs.= "<tr onclick=\"popupload('payments.php?groupay=$rid&pid=$pid')\"><td>$name</td><td>$gid</td><td>$bname</td></tr>";
						}
					}
				}
			}
			elseif($src=="staff"){
				if($db->istable(2,"staff_loans$cid")){
					$qry = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE $cond");
					if($qry){
						foreach($qry as $row){
							$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $uid=$row["id"]; $idno=$row['idno']; $nme=str_replace("'","`",$name);
							$trs.= "<tr onclick=\"confirmpay('$uid-staff','$pid','$amnt','$nme')\"><td>$name</td><td>0$fon</td><td>$idno</td></tr>";
						}
					}
				}
			}
			else{
				$qry = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE $cond");
				if($qry){
					foreach($qry as $row){
						$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $idno=$row['idno']; $nme=str_replace("'","`",$name);
						$trs.= "<tr onclick=\"confirmpay('$idno-client','$pid','$amnt','$nme')\"><td>$name</td><td>0$fon</td><td>$idno</td></tr>";
					}
				}
			}
			
			if($src=="group"){
				$tdh = "<td>Group Name</td><td>Group Id</td><td>Branch</td>";
				$pays = array("repay"=>"Loan Repayment","merge"=>"Merge Payment"); 
				foreach($pays as $key=>$pay){
					$cnd = ($ptp==$key) ? "selected":"";
					$opts.="<option value='$key' $cnd>$pay</option>";
				}
			}
			else{
				$tdh = "<td>Client</td><td>Phone</td><td>ID No</td>";
				$pays = array("repay"=>"Loan Repayment","penalty"=>"Penalty","merge"=>"Merge Payment","penrepay"=>"Penalty & Loan Repayment"); 
				foreach($pays as $key=>$pay){
					$cnd = ($ptp==$key) ? "selected":"";
					$opts.="<option value='$key' $cnd>$pay</option>";
				}
				
				if(in_array("delete client payment",$perms)){
					$cnd = ($ptp=="transfer") ? "selected":"";
					$opts.="<option value='transfer' $cnd>Transfer Payment</option>";
				}
				
				if(in_array("split client payment",$perms)){
					$cnd = ($ptp=="split") ? "selected":"";
					$opts.= "<option value='split' $cnd>Split Payment</option>";
				}
			}
			
			if($ptp=="transfer"){
				$brans = "<option value='0'>-- Transfer to --</option>";
				$res = $db->query(1,"SELECT *FROM `branches` WHERE `status`='0' AND `client`='$cid'");
				if($res){
					foreach($res as $row){
						$brans.=($row['paybill']!=$pb) ? "<option value='".$row['paybill']."'>".prepare(ucwords($row['branch']))."</option>":"";
					}
				}
				
				$top = "<select id='tto' style='width:200px;font-size:15px;cursor:pointer'>$brans</select><hr>";
				$data = "<p>Client Name<br><input type='text' id='cname' value=\"$name\" style='max-width:220px;width:100%'>
				<button class='btnn' style='float:right' onclick=\"transferpay('$pid')\">Transfer</button></p>";
			}
			elseif($ptp=="merge"){
				$lis=$top=""; $linked=[];
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `status`='0' AND NOT `id`='$pid' AND (`account`='$macc' OR `phone`='$phone') ORDER BY `client` ASC");
				if($sql){
					$chk = $db->query(2,"SELECT `payment` FROM `org$cid"."_loantemplates` WHERE (`status`<9 OR `loan`='0') AND NOT `payment`='0'");
					if($chk){
						foreach($chk as $roq){ $linked[$roq["payment"]]=$roq["payment"]; }
					}
					
					foreach($sql as $row){
						$cod=$row["code"]; $acc=prepare($row["account"]); $cname=ucfirst(prepare($row["client"])); $sum=fnum($row["amount"]);
						$lis.= (!in_array($cod,$linked)) ? "<option value='$cod'>$cname (A/C $acc - Ksh $sum)</option>":"";
					}
					
					$data = ($lis) ? "<form method='post' id='pfom' onsubmit=\"mergepays(event)\">
						<input type='hidden' name='mgpays[]' value='$code'> <textarea id='pops' style='display:none'>$lis</textarea>
						<table cellpadding='7' style='width:100%;font-size:15px;' class='ptbl'> 
						<caption style='text-align:right'><br><a href='javascript:void(0)' onclick=\"addrow('pays')\"><i class='fa fa-plus'></i> Add Row</a>
						<button class='btnn' style='padding:7px;min-width:60px;margin-left:20px'><i class='bi-bookmarks'></i> Merge</button></caption>
						<tr><td colspan='2'>Select payment transactions to merge with <b>$code</b></td></tr>
						<tr><td colspan='2'><select style='width:100%;' name='mgpays[]' required>$lis</select></td></tr></table>
					</form>":"<p style='color:#ff4500'>No matching payments from client found. Make sure the payments have same account No</p>";
				}
				else{ $data = "<p style='color:#ff4500'>No unapproved payments found</p>"; }
			}
			elseif($ptp=="split"){
				$lis=$top=""; $idnos=[];
				$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE $def AND `status`<2"); 
				if($sql){
					foreach($sql as $row){
						$idn=$row['idno']; $cname=prepare(ucwords($row['name'])); $idnos[]=$idn;
						$lis.= "<option value='$idn'>$cname</option>";
					}
				}
				
				if($lis){
					$dval = (in_array($idno,$idnos)) ? $idno:"";
					$data = "<form method='post' id='pfom' onsubmit=\"splitpay(event,'$pid')\">
						<input type='hidden' name='ptrans' value='$pid'>
						<table cellpadding='7' style='width:100%;font-size:15px' class='table-striped ptbl'> <datalist id='cls'>$lis</datalist>
						<caption style='text-align:right'><br><a href='javascript:void(0)' onclick=\"addrow('clients')\"><i class='fa fa-plus'></i> Add Row</a>
						<button class='btnn' style='padding:7px;min-width:60px;margin-left:20px'><i class='bi-plus-circle'></i> Assign</button></caption>
						<tr style='font-weight:bold;background:#E6E6FA;cursor:default;color:#191970'><td>Client Idno</td><td colspan='2'>Amount</td></tr>
						<tr><td><input type='text' style='width:100%;padding:3px 5px' name='clidn[]' required list='cls' value='$dval'></td>
						<td colspan='2' style='text-align:right;width:45%'><input type='text' name='clamt[]' style='width:100%;padding:3px 5px' 
						value='$amnt' required></td></tr></table>
					</form>";
				}
				else{  $data = "<p style='color:grey'>No clients found</p>"; }
			}
			else{
				$clist = array("client"=>"From Clients","staff"=>"From Employees");
				if($db->istable(2,"cgroups$cid")){ $clist["group"]="From Groups"; }
				foreach($clist as $key=>$val){
					$cnd = ($src==$key) ? "selected":"";
					$ols.= "<option value='$key' $cnd>$val</option>";
				}
				
				$top = "<select style='width:150px;padding:6px;font-size:15px' onchange=\"getpsrc('$pid',this.value)\">$ols</select> 
				<input type='search' style='font-size:15px;outline:none;float:right;padding:5px' placeholder='&#xf002; Search $src' value=\"".prepare($str)."\"
				onkeypress=\"searchpay(event,this.value,'$pid','$src')\">";
				
				$data = ($trs=="") ? "<hr><p style='color:grey'>No Matching $src Found</p>":"<table cellpadding='5' style='width:100%;font-size:15px' class='stbl'>
				<tr style='font-weight:bold;background:#E6E6FA;cursor:default;color:#191970'>$tdh</tr>$trs</table>";
			}
			
			$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
			$b2c_apps = ($qri) ? json_decode($qri[0]['platforms'],1):[]; $b2c_users=($qri) ? json_decode($qri[0]['users'],1):[];
			$isrv = (!in_array(substr($code,0,6),["WALLET","CHECKO","WRITEO","PREPAY"]) && substr($code,0,4)!="CASH") ? 1:0;
			$post = (in_array("post journal entry",$perms)) ? "&nbsp;<button class='bts' onclick=\"popupload('payments.php?postjrn=$pid')\">
			<i class='bi-journal-plus'></i> Post Journal</button>":"";
			$post.= (in_array($sid,$b2c_users) && in_array("web",$b2c_apps) && $isrv) ? "&nbsp;<button class='bts' onclick=\"popupload('payments.php?revpay=$pid')\">
			<i class='bi-arrow-counterclockwise'></i> Reverse</button>":"";
			
			echo "<div style='padding:10px;max-width:500px;margin:0 auto'>
				<p><b>Post Ksh ".fnum($amnt)." to Owner Account</b></p>
				<p>Payment Action<br><select style='width:180px;font-size:16px;padding:7px' id='cltp' onchange=\"checkpay('$ptp',this.value,'$pid')\">$opts</select> $post</p><hr>
				<p>$top</p> $data <br>
			</div>";
		}
	}
	
	# settle group Payment
	if(isset($_GET["groupay"])){
		$gid = trim($_GET["groupay"]);
		$pid = trim($_GET["pid"]);
		$act = (isset($_GET["act"])) ? trim($_GET["act"]):1;
		
		$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
		$qry = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
		$roq = $sql[0]; $gname=prepare(ucfirst($roq["group_name"])); $amnt=$qry[0]["amount"];
		$cond = ($act) ? "AND `status`='1'":""; $trs=$lis=$data=""; $ltd=0;
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `client_group`='$gid' $cond");
		if($qri){
			$qry = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
			if($qry){
				foreach($qry as $rw){ $prods[$rw["id"]]=prepare(ucfirst($rw["product"])); }
			}
			
			foreach($qri as $row){
				$name=prepare(ucwords($row["name"])); $uid=$row["id"]; $idno=$row["idno"]; $ths="<td>Savings</td><td>Loan charges</td>"; $ran=rand(100000,9999999);
				$tds = "<td><input type='number' onkeyup=\"calctot('$ran')\" style='width:100%;padding:0px;border:0px' class='$ran' name='savings[$uid]'></td>
				<td><input type='number' class='$ran' onkeyup=\"calctot('$ran')\" style='width:100%;padding:0px;border:0px' name='wallets[$uid]'></td>";
				
				$sql = $db->query(2,"SELECT `loan`,`loan_product` FROM `org$cid"."_loans` WHERE `client_idno`='$idno' AND (penalty+balance)>0");
				if($sql){
					foreach($sql as $rw){
						$lid = $rw["loan"]; $pname=$prods[$rw["loan_product"]]; $ths.="<td>$pname</td>";
						$tds.= "<td><input type='number' class='$ran' style='width:100%;padding:0px;border:0px' onkeyup=\"calctot('$ran')\" name='ploans[$uid-$lid]'></td>";
					}
				}
				
				$data.= "<h3 style='font-size:18px;color:#191970;padding:7px;background:#f0f0f0;cursor:pointer'><i class='bi-chevron-down'></i> $name
				<span style='float:right;font-weight:bold;font-size:15px;' id='hr$ran'></span></h3> 
				<div style='padding:2px 5px'><table cellpadding='5' style='width:100%' class='table-bordered'>
				<tr style='font-size:14px;background:#e6e6fa;color:#191970;font-weight:bold'>$ths</tr><tr>$tds</tr></table><br></div>";
			}
		}
		
		foreach(["All Clients","Active Clients"] as $key=>$val){
			$cnd = ($key==$act) ? "selected":"";
			$lis.= "<option value='$key' $cnd>$val</option>";
		}
		
		$sbt = ($data) ? "<br><p style='text-align:right;'><button class='btnn'>Proceed</button></p>":
		"<br><p style='color:#ff4500;padding:10px;background:#FFE4E1;text-align:center'><i class='bi-exclamation-circle'></i> No Clients Found</p>";
		
		echo "<div style='padding:10px;max-width:600px;margin:0 auto'>
			<p><b>Post Ksh ".fnum($amnt)." to $gname</b></p>
			<form method='post' id='gpfom' onsubmit=\"postgroupay(event,'$pid')\">
				<input type='hidden' name='groupay' value='$pid'>
				<p><select style='width:150px;font-size:15px;padding:5px;' onchange=\"popupload('payments.php?groupay=$gid&pid=$pid&act='+this.value)\">$lis</select></p>
				<div style='width:100%;overflow:auto;margin-top:15px;' id='dvs'>$data</div> $sbt
			</form>
		</div><script> $(function(){ $('#dvs').accordion({heightStyle: 'content'}); }); </script>";
	}
	
	# reverse C2B payment
	if(isset($_GET["revpay"])){
		$pid = trim($_GET["revpay"]);
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
		$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid'");
		$row = $sql[0]; $amnt=$row['amount']; $name=prepare($row['client']); 
		$cut = (defined("DEDUCT_REVERSAL")) ? DEDUCT_REVERSAL:0; $pb=$row['paybill'];
		
		if(isset($qri[0]["defs"])){ $pbls = json_decode($qri[0]["defs"],1); }
		else{ $pbl = (defined("B2C_DEF")) ? B2C_DEF["comdef"]["paybill"]:0; $pbls[$pbl]="None"; }
		
		if(!isset($pbls[$pb])){
			foreach(B2C_RATES as $lim=>$rate){
				$range = explode("-",$lim); $fee=$rate;
				if($amnt>=$range[0] && $amnt<=$range[1]){ $fee=$rate; break; }
			}
			$amnt-= ($fee>0) ? $fee:5;
		}
		
		$amin = ($cut) ? "<input type='hidden' name='revsum' value='$amnt'>":
		"<p>Reversal Amount<br><input type='number' name='revsum' style='width:100%' value='$amnt' max='$amnt' min='1' required></p>";
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;font-size:22px;color:#191970'>Reverse C2B Payment</h3><br>
			<form method='post' id='rform' onsubmit=\"revc2b(event,'$pid')\">
				<input type='hidden' name='revpay' value='$pid'> $amin
				<p>B2C Disbursement PIN<br><input type='password' name='pin' style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Reverse</button></p><br>
			</form>
		</div>";
	}
	
	# Reimburse wallet funds
	if(isset($_GET["reimbwallet"])){
		$wid = trim($_GET["reimbwallet"]);
		$cut = (defined("DEDUCT_REVERSAL")) ? DEDUCT_REVERSAL:0;
		$bal = walletBal($wid); $rem=fnum($cut);
		$ctx = ($cut) ? "<br><span style='color:#008080;font-size:14px'>Client will receive Less <b>Ksh $rem</b></span>":"";
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='text-align:center;font-size:22px;color:#191970'>Reverse Account Payment</h3><br>
			<form method='post' id='rform' onsubmit=\"revc2b(event,'wid$wid')\">
				<input type='hidden' name='revpay' value='wid$wid'> 
				<p>Reversal Amount<br><input type='number' name='revsum' style='width:100%' value='$bal' max='$bal' min='1' required> $ctx</p>
				<p>B2C Disbursement PIN<br><input type='password' name='pin' style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Reverse</button></p><br>
			</form>
		</div>";
	}
	
	# post journal transaction
	if(isset($_GET["postjrn"])){
		$pid = trim($_GET["postjrn"]); $opts="";
		$skip = array("Deffered Income","Clients Wallet","Surplus Account");
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE (`type`='liability' OR `type`='equity' OR `type`='asset' OR `type`='income') AND `tree`='0' ORDER BY `account` ASC");
		if($sql){
			foreach($sql as $row){
				$rid=$row['id']; $name=prepare(ucwords($row['account'])); $tp=$row['type'];
				$opts.= (!in_array($name,$skip)) ? "<option value='$rid:$tp'>$name</option>":"";
			}
		}
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='font-size:20px;text-align:center'><b>Post Payment to Accounts</b></h3><br>
			<form method='post' id='jform' onsubmit=\"postjrn(event,'$pid')\">
				<input type='hidden' name='jpid' value='$pid'>
				<p>Select Account<br><select style='width:100%' name='pacc' required>$opts</select></p>
				<p>Payment Description<br><input type='text' name='pdesc' style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Post journal</button></p>
			</form><br>
		</div>";
	}
	
	# view payments from payments report
	if(isset($_GET['viewpays'])){
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$vdy = (isset($_GET['dy'])) ? trim($_GET['dy']):0;
		$pays = trim($_GET['viewpays']);
		$pay = ucfirst(str_replace("_"," ",$pays));
		$mon = trim($_GET['mon']);
		$pos = explode(":",trim($_GET['pos']));
		
		$cond = ($vdy) ? "AND `day`='$vdy'":""; 
		$cond.= ($pos[0]=="s") ? " AND `officer`='$pos[1]'":" AND `branch`='$pos[1]'";
		if($pays!="all"){ $cond.= ($pays=="checkoff") ? " AND `code` LIKE 'CHECKOFF%'":" AND `payment`='$pay'"; }
		
		$perpage = 50;
		$lim = getLimit($page,$perpage); $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$brans[0] = "Corporate";
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$brans[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$no=($perpage*$page)-$perpage; $trs=$bcol="";
		$res = $db->query(2,"SELECT *,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' $cond GROUP BY payid ORDER BY time ASC $lim");
		if($res){
			foreach($res as $row){
				$name=prepare(ucwords($row['client'])); $idno=$row['idno']; $code=$row['code']; $rec=$row['receipt']; $lid=$row['linked'];
				$conf=$staff[$row['confirmed']]; $amnt=number_format($row['tamnt']); $tym=date("M-d,H:i",$row['time']); $no++;
				$day = $db->query(2,"SELECT `disbursement` FROM `org$cid"."_loans` WHERE `loan`='$lid'")[0]['disbursement']; $disb=date("d-m-Y",$day);
				$bran = ($pos[0]=="s") ? "<td>".$brans[$row['branch']]."</td>":"";
				$trs.= "<tr valign='top'><td>$no</td><td>$tym</td><td>$code</td><td>$amnt</td><td>$disb</td><td>$name</td><td>$idno</td>$bran<td>$rec</td><td>$conf</td></tr>";
			}
		}
		
		$url = base64_encode($cond); $fro=trim($_GET['pos']);
		$res = $db->query(2,"SELECT DISTINCT payid,COUNT(*) AS totals,SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' $cond");
		$totals = $res[0]['totals']; $total = $res[0]['tamnt']; 
		
		if($pos[0]=="s"){ $from =$staff[$pos[1]]; $bcol="<td>Branch</td>"; }
		else{ $from = ($res) ? $brans[$pos[1]]." branch":"Head Office"; }
		
		$prd = ($vdy) ? date("d-m-Y",$vdy):date("M Y",$mon);
		$title = ($pay=="All") ? "$prd Payments for $from":"$prd $pay Payments for $from";
		
		echo "<div class='cardv' style='max-width:1300px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped' style='width:100%;font-size:14px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top;font-weight:bold'>Ksh ".number_format($total)." ".
						genrepDiv("payments.php?vtp=payrep:$pays&des=$url&mn=$mon&df=$fro",'right')."</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>
						<td colspan='2'>Date</td><td>Transaction</td><td>Amount</td><td>Disbursement</td><td>Client</td><td>Id No</td>$bcol<td>Receipt</td><td>Approval</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"payments.php?viewpays=$pays&mon=$mon&pos=$fro&dy=$vdy")."
		</div>";
		savelog($sid,"Viewed $title");
	}
	
	# payment report
	if(isset($_GET['report'])){
		$view = trim($_GET['report']);
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$mon = ($view) ? $view:strtotime(date("Y-M"));
		$dec = strtotime("$yr-Dec-01");
		$mon = ($yr!=date("Y") && !$view) ? $dec:$mon;
		$thismon = strtotime(date("Y-M")); $ths=""; $pcko=0;
		
		$dels = array("id","paybill","branch","officer","month","year");
		$fields = $db->tableFields(2,"paysummary$cid");
		if(!in_array("branch",$fields)){
			$db->insert(2,"ALTER TABLE `paysummary$cid` ADD `branch` INT NOT NULL AFTER `paybill`");
			$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
			if($sql){
				foreach($sql as $row){
					$payb=$row['paybill']; $bid=$row['id'];
					$db->insert(2,"UPDATE `paysummary$cid` SET `branch`='$bid' WHERE `paybill`='$payb'");
				}
			}
		}
		
		foreach($fields as $col){
			if(!in_array($col,$dels)){
				$ths.="<td>".ucwords(str_replace("_"," ",$col))."</td>"; $cols[]=$col;
			}
		}
		
		$yrs = "<option value='".date("Y")."'>".date("Y")."</option>";
		$res = $db->query(2,"SELECT DISTINCT `year` FROM `paysummary$cid` WHERE NOT `year`='".date('Y')."' ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
				$yrs.="<option value='$year' $cnd>$year</option>";
			}
		}
		
		$nmon = ($yr==date("Y")) ? $thismon:$dec;
		$mons = ($yr==date("Y")) ? "<option value='$thismon'>".date("M Y")."</option>":"<option value='$dec'>".date("M Y",$dec)."</option>";
		$sql = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE NOT `month`='$nmon' AND `year`='$yr' ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":"";
				$mons.=($mn>0) ? "<option value='$mn' $cnd>".date("M Y",$mn)."</option>":"";
			}
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$me = staffInfo($sid); $access=$me['access_level']; $cnf=json_decode($me["config"],1);
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$bids=$data=$incomes=[]; $brans=$trs="";
		if($me["position"]=="collection agent"){
			$me["access_level"] = "portfolio";
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		if(in_array($access,["hq","region"])){
			$rg = (isset($cnf["region"])) ? $cnf["region"]:0;
			$brns = "<option value='0'>Corporate</option>"; $lod=($rgn) ? $rgn:$rg;
			$add = ($me["access_level"]=="region" && $lod) ? "AND ".setRegion($lod):"";
			$res = $db->query(2,"SELECT DISTINCT branch FROM `paysummary$cid` WHERE `month`='$mon' $add");
			if($res){
				foreach($res as $row){
					$br=$row['branch']; $cnd=($bran==$br) ? "selected":""; $bids[]=$br;
					$brns.="<option value='$br' $cnd>".$bnames[$br]."</option>";
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
					$brans = "<select style='width:150px;font-size:15px;' onchange=\"loadpage('payments.php?report=$view&year=$yr&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('payments.php?report=$view&year=$yr&reg=$rgn&bran='+this.value)\">$brns</select>";
		}
		
		$icols = array("interest","penalties","rollover_fees","offset_fees");
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$terms=json_decode($row['payterms'],1);
				foreach($terms as $pay=>$des){ $icols[$pay]=$pay; }
			}
		}
		
		if(in_array($me["access_level"],["hq","region"])){
			$ths = "<td>Branch</td>$ths";
			foreach($bids as $bid){
				$dat=[]; $income=0; $dcols=""; 
				$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `branch`='$bid' AND `month`='$mon'");
				foreach($qri as $row){
					foreach($cols as $col){
						if(array_key_exists($col,$dat)){
							if(array_key_exists($col,$row)){ $dat[$col]+=$row[$col]; }
						}
						else{
							if(array_key_exists($col,$row)){ $dat[$col]=$row[$col]; }
							else{ $dat[$col]=0; }
						}
					}
					foreach($icols as $col){ $income+=(isset($row[$col])) ? $row[$col]:0; $dcols.="`payment`='".ucfirst($col)."' OR "; }
				}
				
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
				$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `branch`='$bid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
				$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$bid]=$dat; $pcko+=$cko; $incomes[$bid]=array("total"=>$income,"checkoff"=>$cko);
			}
		}
		elseif($me['access_level']=="branch"){
			$mbr = $me['branch']; $ths = "<td>Staff</td>$ths";
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `paysummary$cid` WHERE `month`='$mon' AND `branch`='$mbr'");
			if($res){
				foreach($res as $row){
					$ofid=$row['officer']; $dat=[]; $income=0; $dcols="";
					$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `officer`='$ofid' AND `month`='$mon'");
					foreach($qri as $rw){
						foreach($cols as $col){
							if(array_key_exists($col,$dat)){
								if(array_key_exists($col,$rw)){ $dat[$col]+=$rw[$col]; }
							}
							else{
								if(array_key_exists($col,$rw)){ $dat[$col]=$rw[$col]; }
								else{ $dat[$col]=0; }
							}
						}
						foreach($icols as $col){ $income+=(isset($rw[$col])) ? $rw[$col]:0; $dcols.="`payment`='".ucfirst($col)."' OR "; }
					}
					
					$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
					$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `officer`='$ofid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
					$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$ofid]=$dat; $pcko+=$cko; $incomes[$ofid]=array("total"=>$income,"checkoff"=>$cko);
				}
			}
		}
		else{
			$ths = "<td>Staff</td>$ths"; $dcols=""; $dat=[]; $income=$pcko=0;
			$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `officer`='$sid' AND `month`='$mon'"); 
			if($qri){
				foreach($qri as $rw){
					foreach($cols as $col){
						if(array_key_exists($col,$dat)){
							if(array_key_exists($col,$rw)){ $dat[$col]+=$rw[$col]; }
						}
						else{
							if(array_key_exists($col,$rw)){ $dat[$col]=$rw[$col]; }
							else{ $dat[$col]=0; }
						}
					}
					foreach($icols as $col){ $income+=(isset($rw[$col])) ? $rw[$col]:0; $dcols.="`payment`='".ucfirst($col)."' OR "; }
				}
				
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
				$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `officer`='$sid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
				$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$sid]=$dat; $pcko+=$cko; $incomes[$sid]=array("total"=>$income,"checkoff"=>$cko);
			}
		}
		
		$totals=$itotal=$ckos=0; $trs=$tdk=""; $tcols=[]; 
		foreach($data as $pos=>$arr){
			$tname = (in_array($me["access_level"],["hq","region"])) ? $bnames[$pos]:$staff[$pos]; $total=array_sum($arr); 
			$totals+=$total; $tds=""; $go=(in_array($me["access_level"],["hq","region"])) ? "b:$pos":"s:$pos";
			foreach($cols as $col){
				if(array_key_exists($col,$tcols)){ $tcols[$col]+=$arr[$col]; }
				else{ $tcols[$col]=$arr[$col]; }
				$view = ($arr[$col]) ? "<a href='javascript:void(0)' onclick=\"loadpage('payments.php?viewpays=$col&pos=$go&mon=$mon')\">View</a>":"";
				$tds.="<td>".number_format($arr[$col])." $view</td>";
			}
			
			$cko = $incomes[$pos]['checkoff']; $ckos+=$cko; $itotal+=$incomes[$pos]['total']; 
			$tds.= ($pcko) ? "<td>".number_format($cko)."</td>":"";
			$trs.= "<tr><td>$tname</td>$tds<td>".number_format($incomes[$pos]['total'])."</td><td>".number_format($total)."</td></tr>";
		}
		
		foreach($cols as $col){
			$tdk.= (isset($tcols[$col])) ? "<td>".number_format($tcols[$col])."</td>":"<td>0</td>";
		}
		
		$ths.= ($ckos) ? "<td>Checkoff Income</td>":""; $tdk.= ($ckos) ? "<td>".number_format($ckos)."</td>":"";
		$trs.="<tr style='font-weight:bold;color:#191970'><td>Totals</td>$tdk<td>".number_format($itotal)."</td><td>".number_format($totals)."</td></tr>";
		$prnt = ($totals) ? genrepDiv("reports.php?vtp=paysum&br=$bran&mn=$mon&yr=$yr&reg=$rgn",'right'):"";
		
		echo "<div class='cardv' style='max-width:1250px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Payments Report</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-bordered' style='width:100%;font-size:15px;min-width:700px' cellpadding='5'>
						<caption style='caption-side:top'> $prnt
							<select onchange=\"loadpage('payments.php?report&year='+this.value)\" style='width:80px'>$yrs</select>
							<select onchange=\"loadpage('payments.php?year=$yr&report='+this.value)\" style='width:120px'>$mons</select> $brans
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>$ths<td>Total Income</td><td>Totals</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed Payments Report");
	}
	
	# payment receipts
	if(isset($_GET['receipts'])){
		$view = trim($_GET['receipts']);
		$yr = (isset($_GET['year'])) ? trim($_GET['year']):date("Y");
		$mon = ($view) ? $view:strtotime(date("Y-M"));
		$dec = strtotime("$yr-Dec-01");
		$mon = ($yr!=date("Y") && !$view) ? $dec:$mon;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$thismon = strtotime(date("Y-M"));
		
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		$access = $me['access_level']; $brans="";
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":"AND `branch`='".$me['branch']."'";
		$cond = ($me['access_level']=="hq") ? "":$cond;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$cond;
		
		if($me["position"]=="collection agent"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
			if($sql){
				foreach($sql as $row){ $ids[]="idno='".$row['client_idno']."'"; }
				$cond.= " AND (".implode(" OR ",$ids).")"; 
			}
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$yrs = "<option value='".date("Y")."'>".date("Y")."</option>";
		$res = $db->query(2,"SELECT DISTINCT `year` FROM `paysummary$cid` WHERE NOT `year`='".date('Y')."' ORDER BY `year` DESC");
		if($res){
			foreach($res as $row){
				$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
				$yrs.="<option value='$year' $cnd>$year</option>";
			}
		}
		
		$nmon = ($yr==date("Y")) ? $thismon:$dec;
		$mons = ($yr==date("Y")) ? "<option value='$thismon'>".date("M Y")."</option>":"<option value='$dec'>".date("M Y",$dec)."</option>";
		$sql = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE NOT `month`='$nmon' AND `year`='$yr' ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":"";
				$mons.=($mn>0) ? "<option value='$mn' $cnd>".date("F Y",$mn)."</option>":"";
			}
		}
		
		if($access=="hq" or $access=="region"){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT branch FROM `processed_payments$cid` WHERE `month`='$mon' $add");
			if($res){
				foreach($res as $row){
					$brn=$row['branch']; $cnd=($bran==$brn) ? "selected":"";
					$brns.="<option value='$brn' $cnd>".$bnames[$brn]."</option>";
				}
			}
			
			$brans = "<select style='width:150px' onchange=\"loadpage('payments.php?receipts=$view&year=$yr&bran='+this.value)\">$brns</select>";
		}
		
		$url = base64_encode($cond); $trs=""; $no=0;
		$res = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `month`='$mon' $cond GROUP BY day ORDER BY day DESC");
		if($res){
			foreach($res as $row){
				$day=date("d-m-Y",$row['day']); $dy=$row['day']; 
				$qri = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `day`='$dy' AND `status`='0' $cond GROUP BY payid");
				$qry = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `day`='$dy' $cond GROUP BY payid");
				$rem = ($qri) ? number_format(count($qri)):0; $all = ($qry) ? number_format(count($qry)):0; $no++;
				
				$add = ($rem) ? "<a href='javascript:void(0)' onclick=\"printdoc('payments.php?vtp=receipt:0&des=$url&dy=$dy','Receipts')\">
				<i class='fa fa-eye'></i> View</a> &nbsp; | &nbsp; <a href='javascript:void(0)' onclick=\"printdoc('payments.php?vtp=receipt:1&des=$url&dy=$dy','Receipts')\">
				<i class='fa fa-download'></i> Download</a>":"";
				
				$trs.="<tr><td>$no</td><td>$day</td><td>$all</td><td>$rem $add</td>
				<td><a href='javascript:void(0)' onclick=\"printdoc('payments.php?vtp=receipt:2&des=$url&dy=$dy','Receipts')\">
				<i class='fa fa-cloud-download'></i> Download All</a></td></tr>";
			}
		}
		
		echo "<div class='cardv' style='max-width:900px;min-height:400px;'>
			<div class='container' style='padding:5px;max-width:1300px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Payment Receipts</h3>
				<div style='overflow:auto;width:100%'>
					<table class='table-striped table-bordered' style='width:100%;font-size:15px;min-width:650px' cellpadding='5'>
						<caption style='caption-side:top'>
							<select onchange=\"loadpage('payments.php?receipts&year='+this.value)\" style='width:80px'>$yrs</select>
							<select onchange=\"loadpage('payments.php?year=$yr&receipts='+this.value)\" style='width:150px'>$mons</select> $brans
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px' valign='top'>
						<td colspan='2'>Date</td><td>Receipts</td><td>Unprinted</td><td>Action</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed Payment Receipts for ".date("F Y",$mon));
	}
	
	#validate payment
	if(isset($_GET['validatepay'])){
		$str = (isset($_GET['str'])) ? clean(strtoupper($_GET['str'])):null;
		$data = "<p style='padding:10px;background:#f8f0f8;color:#191970'>Search payment code or transaction to see its details</p>";
		
		if($str){
			if(substr($str,0,6)=="MERGED"){
				$trs=""; $tot=0;
				$sql = $db->query(2,"SELECT *FROM `mergedpayments$cid` WHERE `transaction`='$str'");
				if($sql){
					foreach($sql as $row){
						$code=$row['code']; $amnt=fnum($row['amount']); $day=date("d-m-Y, h:i a",$row['time']); $idno=$row['idno'];
						$trs.="<tr><td>$code</td><td>$amnt</td><td>$day</td></tr>"; $tot+=$row['amount']; $bid=$row["branch"];
					}
					
					if($bid){
						$name = $db->query(2,"SELECT `name` FROM `org".$cid."_clients` WHERE `idno`='$idno'")[0]["name"];
						$bene = "<tr><td>Beneficiary<td><td colspan='2'>".prepare(ucwords($name))."</td></tr><tr><td>ID No<td><td colspan='2'>$idno</td></tr>";
					}
					else{
						$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$idno'"); $bene="";
						if($qri){
							$name = prepare(ucwords($qri[0]["client"])); $trans=$qri[0]['code']; $trans.=" A/C: ".prepare($qri[0]["account"]);
							$bene = "<tr><td>Merged To<td><td colspan='2'>$name</td></tr><tr><td>Transaction<td><td colspan='2'>$trans</td></tr>";
						}
					}
					
					$data = "<table cellpadding='5' style='margin:0 auto'> $bene
						<tr><td>Total Merged<td><td colspan='2'>".fnum($tot)."</td></tr>
						<tr><td colspan='3'><br></td></tr>
						<tr style='font-weight:bold;background:#e6e6fa;color:#191970'><td>Transaction</td><td>Amount</td><td>Merge Date</td></tr>$trs
					</table><br>";
				}
				else{ $data="<p style='padding:10px;background:#f8f0f8;color:#191970'>No match results for your search <b>$str</b></p>"; }
			}
			elseif(substr($str,0,6)=="WALLET" && $db->istable(3,"translogs$cid")){
				$sql = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='$str'");
				if($sql){
					$row=$sql[0]; $by=$row['user']; $tym=date("d-m-Y, h:i a",$row['time']); $desc=json_decode($row['details'],1);
					$amnt=$desc["amount"]; $des=prepare($desc["desc"]); $dto=explode(":",$desc["user"]); 
					$actp=$dto[0]; $uid=$dto[1]; $buser=($by) ? ucwords(prepare(staffInfo($by)["name"])):"System";
					
					$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
					$qri = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `id`='$uid'"); 
					$name = prepare(ucwords($qri[0]['name'])); $idno=$qri[0]["idno"];
					
					$data = "<table cellpadding='7' style='margin:0 auto'>
						<tr><td style='text-align:right;font-weight:bold'>Transaction Date:</td><td>$tym</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>Beneficiary :</td><td>$name</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>ID Number :</td><td>$idno</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>Account Type :</td><td>".ucwords($actp)."</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>Amount :</td><td>".fnum($amnt)."</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>Description :</td><td>$des</td></tr>
						<tr><td style='text-align:right;font-weight:bold'>Done By :</td><td>$buser</td></tr>
					</table><br>";
				}
				else{ $data= "<p style='padding:10px;background:#f8f0f8;color:#191970'>No match results for your search <b>$str</b></p>"; }
			}
			else{
				$data=$trs="";
				$res = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$str'");
				if($res){
					foreach($res as $row){
						$code=$row['code']; $amnt=fnum($row['amount']); $payb=$row['paybill']; $acc=$row['account'];
						$cname=clean($row['client']); $dy=$row['date']; $sta=$row["status"];
						$ptm=strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
						$day=date("d-m-Y, h:i a",$ptm);
						
						if(strpos($str,"OVERPAY")!==false){
							$rid = trim(str_replace("OVERPAY","",$code));
							$sq = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `id`='$rid'");
							$overpay = fnum($sq[0]['amount']); $pid=$sq[0]['payid']; 
							$qr = $db->query(2,"SELECT `amount` FROM `org".$cid."_payments` WHERE `id`='$pid'"); 
							$amnt = fnum($qr[0]['amount']);
							
							$data = "<table cellpadding='7' style='margin:0 auto'>
								<tr><td style='text-align:right;font-weight:bold'>Transaction:</td><td>$code</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Initial Amount :</td><td>$amnt</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Overpay :</td><td>$overpay</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Paybill :</td><td>$payb</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Account :</td><td>$acc</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Client Name :</td><td>$cname</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Date Assigned :</td><td>$day</td></tr>
							</table><br>";
						}
						else{
							$data="<table cellpadding='7' style='margin:0 auto'>
								<tr><td style='text-align:right;font-weight:bold'>Transaction :</td><td>$code</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Amount :</td><td>$amnt</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Paybill :</td><td>$payb</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Account :</td><td>$acc</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Client Name :</td><td>$cname</td></tr>
								<tr><td style='text-align:right;font-weight:bold'>Payment Date :</td><td>$day</td></tr>
							</table><br>";
						}
					}
				}
				else{
					$data="<p style='padding:10px;background:#f8f0f8;color:#191970'>No match results for your search <b>$str</b></p>";
				}
			}
		}
		
		echo "<div class='cardv' style='max-width:800px;min-height:400px;overflow:auto;'>
			<div class='container' style='padding:7px;'>
				<div style='width:100%;overflow:auto'>
					<h3 style='font-size:22px;color:#191970'>$backbtn Validate Payment Transaction
					<input type='search' style='float:right;padding:5px;font-size:15px;' placeholder='&#xf002; Search payment' value='$str'
					onkeyup=\"fsearch(event,'payments.php?validatepay&str='+cleanstr(this.value))\" onsearch=\"loadpage('payments.php?validatepay&str='+cleanstr(this.value))\">
					</h3><hr> <div style='min-width:350px'>$data</div>
				</div>
			</div>
		</div>";
		savelog($sid,"Accessed Validate Payment Transaction Form");
	}
	
	# request payment push
	if(isset($_GET["reqpay"])){
		$accn = trim($_GET["reqpay"]);
		$payb = trim($_GET["payb"]);
		$pfon = trim($_GET["fon"]);
		
		$sql = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='passkeys'");
		$keys = ($sql) ? json_decode($sql[0]['value'],1):[]; $pkey=(isset($keys[$payb])) ? $keys[$payb]["key"]:""; 
		$ckey = (isset($keys[$payb])) ? $keys[$payb]["ckey"]:""; $csec=(isset($keys[$payb])) ? $keys[$payb]["csec"]:""; 
		
		echo "<div style='padding:10px;max-width:300px;margin:0 auto'>
			<h3 style='font-size:20px;text-align:center'><b>Initiate Payment Request</b></h3><br>
			<form method='post' id='pyfom' onsubmit=\"reqpay(event,'gfomd')\">
				<input type='hidden' name='pkey' value='$pkey'> <input type='hidden' name='accno' value='$accn'> 
				<input type='hidden' name='ckey' value='$ckey'> <input type='hidden' name='csec' value='$csec'> 
				<input type='hidden' name='paybno' value='$payb'><p>MPESA Phone number<br><input type='number' id='pfon' name='payfro' value='0$pfon' required></p>
				<p>Amount to Pay<br><input type='number' id='paym' name='pamnt' required></p><br><p style='text-align:right'><button class='btnn'>Request</button></p>
			</form><br>
		</div>";
	}
	
	//paybill balances
	if(isset($_GET['balances'])){
		$trs=""; $no=$np=0; $bnames=[]; 
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['paybill']]=prepare(ucwords($row['branch'])); $np++;
			}
		}
		
		if($db->istable(2,"paybill_balances$cid")){
			$sql = $db->query(2,"SELECT *FROM `paybill_balances$cid`");
			if($sql){
				foreach($sql as $row){
					$pb=$row['paybill']; $bal=number_format($row['current']); $no++;
					$bnm = isset($bnames[$pb]) ? $bnames[$pb]:"Head Office";
					$name = (count($bnames)==1 && $np>1) ? "Corporate":$bnm;
					$trs.="<tr><td>$no</td><td>$name</td><td>$pb</td><td>$bal</td></tr>";
				}
			}
		}
		
		echo "<div style='margin:0 auto;padding:10px;max-width:450px'>
			<h3 style='font-size:23px;text-align:center'>Current Paybill Balances</h3><br>
			<table cellpadding='7' style='width:100%;border:1px solid #dcdcdc;border-collapse:collapse' border='1'>
				<caption style='text-align:right'><button class='bts' style='padding:4px' onclick=\"popupload('payments.php?balances')\">
				<i class='fa fa-refresh'></i> Refresh</button></caption>
				<tr style='background:#f0f0f0;font-weight:bold'><td colspan='2'>Branch</td><td>Paybill</td><td>Balance</td></tr>$trs
			</table>
		</div>";
		savelog($sid,"Viewed Paybill Balances");
	}
	
	# select loan to Pay
	if(isset($_GET["picklid"])){
		$src = explode(":",trim($_GET["picklid"]));
		$ptp = explode("-",$src[0])[1]; $idno=explode("-",$src[0])[0]; 
		$tbl = ($ptp=="client") ? "org$cid"."_loans":"staff_loans$cid";
		$col = ($ptp=="client") ? "client_idno":"stid"; 
		$pid = $src[1]; $amnt=$src[2]; $type=$src[3]; $opts="";
		
		$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE `$col`='$idno' AND (balance+penalty)>0");
		foreach($sql as $row){
			$prd=$row["loan_product"]; $name=str_replace("'","",prepare(ucwords($row[$ptp]))); $bal=fnum($row["amount"]+$row["penalty"]); $lid=$row["loan"];
			$chk = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'"); $pname=ucfirst(prepare($chk[0]["product"])); $tid=$row["tid"];
			if($chk[0]["category"]=="asset"){
				$ltp = $db->query(2,"SELECT `loantype` FROM `org$cid"."_loantemplates` WHERE `id`='$tid'")[0]["loantype"];
				$fin = $db->query(2,"SELECT `asset_name` FROM `finassets$cid` WHERE `id`='".explode(":",$ltp)[2]."'");
				$pname = prepare(ucfirst($fin[0]["asset_name"]));
			}
			$opts.= "<option value='$lid'>$pname (Ksh $bal)</option>";
		}
		
		echo "<div style='margin:0 auto;padding:10px;max-width:300px'>
			<input type='hidden' id='cltp' value='$type'>
			<h3 style='font-size:23px;text-align:center'>Select Loan to Pay</h3><br>
			<p>Select Active Loan<br><select style='width:100%' id='lonid'>$opts</select></p>
			<p style='text-align:right'><button class='btnn' onclick=\"confirmpay('$idno-$ptp','$pid','$amnt','$name','lonid')\">Proceed</button></p><br>
		</div>";
	}
	
	# import errors
	if(isset($_GET['xlserrors'])){
		$data = (file_exists("dbsave/import_errors.dll")) ? json_decode(file_get_contents("dbsave/import_errors.dll"),1):[];
		$titles = array("exists"=>"Existing Payments","incomplete"=>"Incomplete Records");
		
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
	
	function setprorange(pos,val){
		var fro = (pos=="dfro") ? val:_("dfro").value;
		var to = (pos=="dto") ? val:_("dto").value;
		loadpage("payments.php?processed="+fro+"&dto="+to);
	}
	
	function checkpay(fro,val,pid){
		if(val=="transfer" || val=="split" || val=="merge"){
			popupload("payments.php?confpay="+pid+"&paytp="+val);
		}
		else{
			if(fro=="transfer" || fro=="split" || fro=="merge"){ popupload("payments.php?confpay="+pid+"&paytp="+val); }
		}
	}
	
	function addrow(src){
		var did = Date.now();
		if(src=="clients"){
			$(".ptbl").append("<tr id='"+did+"'><td><input type='text' style='width:100%;padding:3px 5px' name='clidn[]' list='cls' required></td><td style='text-align:right;width:35%'>"+
			"<input type='text' name='clamt[]' style='width:100%;padding:3px 5px' required></td><td><i class='bi-x-lg' style='font-size:23px;cursor:pointer;color:#ff4500'"+
			"onclick=\"$('#"+did+"').remove()\"></i></td></tr>");
		}
		else{
			var lis = $("#pops").val();
			$(".ptbl").append("<tr id='"+did+"'><td><select style='width:100%;' name='mgpays[]' required>"+lis+"</select></td>"+
			"<td style='width:30px'><i class='bi-x-lg' style='font-size:23px;cursor:pointer;color:#ff4500' onclick=\"$('#"+did+"').remove()\"></i></td></tr>");
		}
	}
	
	function calctot(id){
		var data = document.getElementsByClassName(id), sum=0;
		var fomat = new Intl.NumberFormat("en-US",{style:"currency",currency:"KES"});
		for(var i=0; i<data.length; i++){ sum+=Number(data[i].value); }
		$("#hr"+id).html(fomat.format(sum));
	}
	
	function importpays(e){
		e.preventDefault();
		var xls = _("pxls").files[0];
		if(xls!=null){
			if(confirm("Extract payments from selected File?")){
				var data=new FormData(_("pform"));
				data.append("file",xls);
				var x=new XMLHttpRequest(); progress("Processing...please wait");
				x.onreadystatechange=function(){
					if(x.status==200 && x.readyState==4){
						var res=x.responseText; progress(); 
						if(res.trim().split(":")[0]=="success"){
							var fx = res.trim().split(":")[1],all=res.trim().split(":")[2];
							if(fx=="all"){ toast(all+" Payments extracted and saved successfull!"); closepop(); loadpage("payments.php?payments"); }
							else if(fx=="none"){ alert("None of the payments Extracted! View errors found"); popupload("payments.php?xlserrors"); }
							else{ alert(fx+"/"+all+" records extracted! View errors found"); popupload("payments.php?xlserrors"); }
						}
						else{ alert(res.trim()); }
					}
				}
				x.open("post",path()+"dbsave/import_c2bpays.php",true);
				x.send(data);
			}
		}
		else{
			alert("Please select Excel File first");
		}
	}
	
	function topupwallet(e,src){
		e.preventDefault();
		var data = $("#wform").serialize();
		if(src=="mpesa"){ reqpay(e,data); }
		else{
			var txt = (src=="pndpays") ? "Pending Payments?":"Asset accounts?";
			var text = (src=="cash") ? "Confirm received Cash as Cashier?":"Confirm deposit from "+txt;
			if(confirm(text)){
				$.ajax({
					method:"post",url:path()+"dbsave/payments.php",data:data,
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						if(res.trim().split(":")[1]=="done"){ closepop(); window.location.reload(); }
						else{ closepop(); alert("Request Created! Waiting Approvals"); }
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function transwallet(e){
		e.preventDefault();
		var vtp = $("#tto").val(), list={loan:"Loan repayment",investbal:"Investment Wallet",balance:"Transactional Account",roi:"Return on Investment",client:"another client"};
		if(confirm("Sure to transfer funds to "+list[vtp]+"?")){
			var data = $("#wfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					if(res.trim().split(":")[1]=="wait"){ closepop(); alert("Request Created! Waiting Approvals"); }
					else{ closepop(); window.location.reload(); }
				}
				else{ alert(res); }
			});
		}
	}
	
	function mergepays(e){
		e.preventDefault();
		if(confirm("Confirm proceed to merge selected payments?")){
			var data = $("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					fetchpage("payments.php?payments"); 
					popupload("payments.php?confpay="+res.trim().split(":")[1]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function postjrn(e,pid){
		e.preventDefault();
		if(confirm("Confirm post payment to Accounts Journal?")){
			var data = $("#jform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); $("#tr"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function confirmpay(idno,pid,amnt,name,ln){
		if(confirm("Confirm Assign Ksh "+amnt+" payment to "+name+"?")){
			var desc = _("cltp").value.trim(),penalty=0; closepop();
			var lid = (ln=="lonid") ? $("#lonid").val():0;
			if(desc=="penrepay"){
				penalty = prompt("Enter penalty Amount",500);
			}
			
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{savepay:idno+":"+pid+":"+amnt+":"+lid,penamnt:penalty,paytp:desc},
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+pid).remove(); }
				else if(res.trim().split("~")[0]=="choose"){ popupload("payments.php?picklid="+res.trim().split("~")[1]); }
				else{ alert(res); popupload("payments.php?confpay="+pid); }
			});
		}
	}
	
	function transferpay(id){
		if(_("tto").value.trim()=="0"){ toast("Select branch to transfer to first!"); }
		else{
			var pb = _("tto").value.trim(), name = _("cname").value.trim();
			if(confirm("Transfer payment to paybill "+pb+"?")){
				$.ajax({
					method:"post",url:path()+"dbsave/payments.php",data:{transferpay:id,bto:pb,bcn:name},
					beforeSend:function(){progress("Transfering...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim().split(":")[0]=="success"){
						closepop(); toast("Tranfered successfully");
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function editpay(pid,val){
		var nacc = prompt("Enter Correct "+pid.split(":")[1]+" No",val);
		if(nacc){
			if(confirm("Update client payment "+pid.split(":")[1]+"?")){
				$.ajax({
					method:"post",url:path()+"dbsave/payments.php",data:{editpacc:pid,pacc:nacc},
					beforeSend:function(){ progress("Modifying...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						var id = (pid.split(":")[1]=="phone") ? "fpy":"pcc";
						$("#"+id+pid.split(":")[0]).html(nacc); toast("Success"); 
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function revc2b(e,pid){
		e.preventDefault();
		if(confirm("Sure to reverse payment back to Customer?")){
			var data = $("#rform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Reversing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); $("#tr"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function deloverpay(pid){
		if(confirm("Sure to delete client overpayment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{deloverpay:pid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#rec"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function reqpay(e,des){
		e.preventDefault();
		var fon = $("#pfon").val(), amnt=$("#paym").val();
		if(confirm("Request payment of Ksh "+amnt+" from "+fon+"?")){
			var data = (des=="gfomd") ? $("#pyfom").serialize():des;
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Requesting...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function walletdeduct(e){
		e.preventDefault();
		if(confirm("Proceed to Initiate wallet Withdrawal?")){
			var data = $("#wfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					closepop(); alert(res.trim().split(":")[1]); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function postgroupay(e,id){
		e.preventDefault();
		if(confirm("Split payment to selected clients?")){
			var data = $("#gpfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+id).remove(); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function splitpay(e,id){
		e.preventDefault();
		if(confirm("Split payment to selected clients?")){
			var data = $("#pfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+id).remove(); closepop(); }
				else{ alert(res); }
			});
		}
	}
	
	function approveprepay(id){
		if(confirm("Assign prepayment to Loan repayment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{getprepay:id},
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					$("#rec"+id).remove(); popupload("payments.php?confpay="+res.trim().split(":")[1]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function reversepay(idno,code,des){
		if(confirm("Reverse approved payment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{reversepay:code,revidno:idno},
				beforeSend:function(){progress("Reversing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					var load = des.split(":");
					loadpage("payments.php?processed="+load[0]+"&dto="+load[1]+"&bran="+load[2]+"&ofid="+load[3]);
				}
				else{ alert(res); }
			});
		}
	}
	
	function assignmerged(idno){
		if(confirm("Assigning this payment wont be reversed. Continue?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{assignmerged:idno},
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				var rs=res.trim().split(":");
				if(rs[0]=="success"){ popupload("payments.php?confpay="+rs[1]); $("#"+idno).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function assignoverpay(pid,vtp){
		var txt = (vtp==1) ? "Sure to transfer Overpayment to Client's Wallet?":"Assigning Payment won't be reversed. Proceed?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{assignpay:pid,avtp:vtp},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					$("#rec"+pid).remove(); toast("Success!"); 
					if(vtp==1){ popupload("payments.php?confpay="+res.trim().split(":")[1]); }
				}
				else{ alert(res); }
			});
		}
	}
	
	function searchpay(e,str,pid,src){
		var keycode = e.charCode? e.charCode : e.keyCode;
		if(keycode==13 && str.length>1){
			popupload("payments.php?confpay="+pid+"&str="+cleanstr(str)+"&paytp="+_("cltp").value+"&psrc="+src);
		}
	}
	
	function getpsrc(pid,src){
		popupload("payments.php?confpay="+pid+"&paytp="+_("cltp").value+"&psrc="+src);
	}
	
	function delpay(pid){
		if(confirm("Sure to permanently delete client payment?")){
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:{delpay:pid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){ $("#tr"+pid).remove(); }
				else{ alert(res); }
			});
		}
	}
	
	function approvepay(pid,type){
		if(type>0){
			if(confirm("Sure to confirm manual payment?")){
				$.ajax({
					method:"post",url:path()+"dbsave/payments.php",data:{approvemanual:pid},
					beforeSend:function(){progress("Processing...please wait");},
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						$("#tr"+pid).remove(); popupload("payments.php?confpay="+pid);
					}
					else{ alert(res); }
				});
			}
		}
		else{
			popupload("payments.php?confpay="+pid);
		}
	}
	
	function makepay(e){
		e.preventDefault();
		if(_("paytype").value.trim()==""){
			alert("Please select the reason for payment / Payment from!");
		}
		else{
			var data=$("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/payments.php",data:data,
				beforeSend:function(){progress("Processing...please wait");},
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Payment made successful waiting approval"); closepop();
				}
				else{ alert(res); }
			});
		}
	}
	
	function checktp(str){
		if(str=="cash"){ $("#ptp").html(""); }
		else{ $("#ptp").html("Transaction Code/Cheque No<br><input type='text' name='pcode' style='text-transform:uppercase' required>"); }
	}

</script>