<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid=$by=substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['vtp'])){ exit(); }
	$vtp = trim($_GET['vtp']);
	$mon = trim($_GET['mn']);
	$yr = trim($_GET['yr']);
	$db = new DBO(); $cid=CLIENT_ID;
	$port = "L"; $tyr=date("Y"); 
	$tmon = strtotime(date("Y-M"));
		
		# loan sizes
		if($vtp=="lsizes"){
			$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
			$rgn = (isset($_GET['rgn'])) ? trim($_GET['rgn']):0;
			
			$ltbl = "org{$cid}_loans"; $stbl = "org{$cid}_schedule";
			$me = staffInfo($sid); $access=$me['access_level'];
			$cnf = json_decode($me["config"],1);
			
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
			if($res){
				foreach($res as $row){
					$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
				}
			}
			
			$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
			foreach($res as $row){
				$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
			}
			
			if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
			if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
			$show = ($me['access_level']=="hq") ? "":"AND branch='".$me['branch']."'";
			$cond = ($me['access_level']=="portfolio") ? "AND loan_officer='$sid'":$show;
			$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
			$trs=$brans=$mns=""; $tln=$tpf=$tarr=$tpds=$tots=0; 
			
			if($me["position"]=="collection agent"){
				$cond.= " AND JSON_EXTRACT(clientdes,'$.agent')=$sid";
			}
			
			$ranges = (defined("LOAN_SIZE_RANGE")) ? LOAN_SIZE_RANGE:array("0-1000","1001-5000","5001-10000","10001-20000","20001-50000","50001-100000","100001-inf");
			$qri = $db->query(2,"SELECT SUM(balance) AS tbal FROM `$ltbl` WHERE `balance`>0 $cond");
			$tsum = intval($qri[0]["tbal"]); $today=strtotime(date("Y-M-d")); $tbals=$tots=$tns=$tarr=0;
			
			foreach($ranges as $range){
				$mfr = explode("-",$range)[0]; $mtu=explode("-",$range)[1]; $mto=($mtu=="inf") ? time():$mtu; $arrt=$arrs=0;
				$rname = ($mfr==0) ? " Below ".fnum($mto):fnum($mfr)." - ".fnum($mto); $rname=($mtu=="inf") ? "Above ".fnum($mfr-1):$rname;
				
				$sq1 = $db->query(2,"SELECT SUM(balance) AS tbal,COUNT(*) AS tot FROM `$ltbl` WHERE `balance`>0 AND `amount` BETWEEN $mfr AND $mto $cond");
				$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr,COUNT(DISTINCT sd.loan) AS tot FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.amount 
				BETWEEN $mfr AND $mto AND sd.day<$today $cond GROUP BY sd.loan"); 
				if($sq2){
					foreach($sq2 as $row){ $arrt+=$row["tot"]; $arrs+=$row["tarr"]; }
				}
				
				$tbal = intval($sq1[0]["tbal"]); $tot=intval($sq1[0]["tot"]); $perc=roundnum(($tbal/$tsum)*100,2); $tbals+=$tbal; $tots+=$tot; $tns+=$arrt; $tarr+=$arrs;
				$trs.= "<tr><td style='text-align:left'>$rname</td><td>".fnum($tot)."</td><td>".fnum($tbal)."</td><td>$perc%</td><td>".fnum($arrt)."</td><td>".fnum($arrs)."</td></tr>";
			}
			
			$mtitle = "Loan Size Distribution";
			$trs.= "<tr class='trh'><td></td><td>".fnum($tots)."</td><td>".fnum($tbals)."</td><td>100%</td><td>".fnum($tns)."</td><td>".fnum($tarr)."</td></tr>";
			
			$data = "<table style='width:100%;text-align:center' cellpadding='4' cellspacing='0' class='tbl'>
				<tr style='font-weight:bold;color:#191970;background:#e6e6fa;font-size:14px' class='trh'><td style='text-align:left'>Loan Size</td>
				<td>No. of Accounts</td><td>Loan Balances</td><td>Composition</td><td>Non-Performing Accounts</td><td>Non-Performing Amounts</td></tr> $trs
			</table>";
		}
		
		# loan schedule
		if($vtp=="loanscd"){
			$scd = loanschedule($mon); $idno=$acc=$scd["idno"];
			$prod = $scd["product"]; $trs=$ths=$trl=""; $tot=0; $cols=$all=[];
			foreach($scd["installment"] as $one){
				foreach($one["pays"] as $py=>$sum){ $cols[$py]=ucwords(str_replace("_"," ",$py)); }
			}
			
			foreach($scd["installment"] as $one){
				$day=date("d-m-Y",$one["day"]); $pays=$one["pays"]; $inst=array_sum($pays); $tds=""; $tot+=$inst;
				foreach($cols as $col=>$ctx){
					$amnt=(isset($pays[$col])) ? $pays[$col]:0; $tds.="<td style='text-align:center'>".fnum($amnt)."</td>";
					if(isset($all[$col])){ $all[$col]+=$amnt; }else{ $all[$col]=$amnt; }
				}
				$trs.= "<tr valign='top'><td>$day</td>$tds<td style='text-align:center'>".fnum($inst)."</td></tr>";
			}
			
			$sql = $db->query(2,"SELECT `name`,`branch`,`id` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			foreach($cols as $col=>$ctx){
				$ths.= "<td style='text-align:center'>$ctx</td>"; 
				$trl.= "<td style='text-align:center'><b>".fnum($all[$col])."</b></td>"; 
			}
			
			$trs.= "<tr style='background:#ccc'><td></td>$trl<td style='text-align:center'><b>".fnum($tot)."</b></td></tr>";
			$trx = ($scd["topup"]>0) ? "<tr><td>Initial Principal Balance</td><td><b>: &nbsp; Ksh ".fnum($scd["topup"])."</b></td></tr>":"";
			$mtitle = ucwords(prepare($sql[0]['name']))." Loan Schedule"; $port="P"; $bid=$sql[0]['branch']; $payb=getpaybill($bid); $uid=$sql[0]['id'];
			$ltx = ($scd["topup"]>0) ? "Topup Amount":"Loan Amount"; $usewal=(defined("USE_WALLET_SYSTEM")) ? USE_WALLET_SYSTEM:0;
			if($usewal && $db->istable(3,"wallets$cid")){
				$qri = $db->query(3,"SELECT `id` FROM `wallets$cid` WHERE `client`='$uid' AND `type`='client'");
				$acc = ($qri) ? walletAccount($bid,$qri[0]["id"],"balance"):$idno;
			}
			
			$dtr = ($scd["deposit"]) ? "<tr><td>Deposit Amount</td><td><b>: &nbsp; Ksh ".fnum($scd["deposit"])."</b></td></tr>":"";
			$data = "<table style='width:100%;' cellpadding='4' cellspacing='0' class='tbl'>
				<tr class='trx'><td>Date</td>$ths<td style='text-align:center'>Installment</td></tr> $trs
				</table><br><table cellpadding='2'>
				<tr><td>Loan Product</td><td><b>: &nbsp; $prod</b></td></tr> $trx
				<tr><td>$ltx</td><td><b>: &nbsp; Ksh ".fnum($scd["loan"])."</b></td></tr> $dtr
				<tr><td>Loan Account</td><td><b>: &nbsp; PLN$mon</b></td></tr>
				<tr><td>Loan Maturity</td><td><b>: &nbsp; $day</b></td></tr>
				<tr><td>Paybill Number</td><td><b>: &nbsp; $payb, A/C No: $acc</b></td></tr>
			</table>";
		}
		
		# loan statement
		if($vtp=="loanst"){
			$src = (isset($_GET["ltp"])) ? trim($_GET["ltp"]):"client";
			$tbl = ($src=="client") ? "org$cid"."_loans":"staff_loans$cid";
			$col = ($src=="client") ? "client":"staff";
			
			$qry = $db->query(2,"SELECT `$col`,`loan_product`,`tid`,`amount`,SUM(balance+penalty) AS tbal FROM `$tbl` WHERE `loan`='$mon'")[0];
			$cname = prepare(ucwords($qry[$col])); $pid=$qry['loan_product']; $tbal=$qry['tbal']; $tid=$qry["tid"];
			$pname = ucwords(prepare($db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='$pid'")[0]['product']));
			$lntp = $db->query(2,"SELECT `loantype` FROM `org$cid"."_loantemplates` WHERE `id`='$tid'")[0]['loantype'];
			if(in_array("asset",explode(":",$lntp))){
				$def = explode(":",$lntp);
				$chk = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$def[2]'");
				$roq = $chk[0]; $qty=ceil($qry["amount"]/$roq["asset_cost"]); $qt=($qty>1) ? "$qty ":"";
				$pname = $qt.prepare($roq["asset_name"]);
			}
			
			$mtitle = "$cname - $pname Loan #$mon Statement"; $port="P"; $trs=$sim=""; $no=0;
			$sql = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='$mon'");
			foreach($sql as $row){
				$def = json_decode($row["details"],1); $des=prepare($def["desc"]); $tp=$def["type"]; $amnt=$def["amount"];
				$tym = $row['time']; $bal=fnum($def["bal"]); $dbt=($tp=="debit") ? $amnt:0; $crd=($tp=="credit") ? $amnt:0;
				if($des.$tp!=$sim){ $no++; }
				$dat[$no][]=array($tym,$des,$dbt,$crd,$bal); $sim=$des.$tp;
			}
			
			foreach($dat as $one){
				if(count($one)>1){
					$crd=$dbt=0; $tms=[];
					foreach($one as $ds){ $tms[]=$ds[0]; $des=$ds[1]; $dbt+=$ds[2]; $crd+=$ds[3]; $bal=$ds[4]; }
					$mnt=min($tms); $mxt=max($tms); $dy1=date("d-m-Y, H:i",$mnt); $dy2=date("d-m-Y, H:i",$mxt);
					$debit = ($dbt) ? fnum($dbt):""; $credit=($crd) ? fnum($crd):"";
					$trs.= "<tr valign='top'><td>$dy1<br>$dy2</td><td>$des</td><td>$debit</td><td>$credit</td><td>$bal</td></tr>";
				}
				else{
					$dy = date("d-m-Y, H:i",$one[0][0]); $des=$one[0][1]; $bal=$one[0][4];
					$dbt = ($one[0][2]) ? fnum($one[0][2]):""; $crd=($one[0][3]) ? fnum($one[0][3]):""; 
					$trs.= "<tr><td>$dy</td><td>$des</td><td>$dbt</td><td>$crd</td><td>$bal</td></tr>";
				}
			}
			
			$trs.= "<tr><td colspan='3' style='text-align:right;'><b>BALANCE AS AT ".date('d-m-Y, h:i A')."</b></td><td></td><td><b>".fnum($tbal)."</b></td></tr>";
			$data = "<table style='width:100%;' cellpadding='4' cellspacing='0' class='tbl'>
				<tr class='trx'><td>Date</td><td>Description</td><td>Debit</td><td>Credit</td><td>Balance</td></tr> $trs
			</table>";
		}
		
		# client wallet
		if($vtp=="wallets"){
			$vop = trim($_GET["vp"]);
			$wid = $yr; $port="P"; $trs=""; $staff[0]="System";
			$fro = explode(":",$mon)[0]; $dtu=explode(":",$mon)[1];
			$ttls = array("balance"=>"Transactional","investbal"=>"Investment","savings"=>"Savings");
			$vbk = $ttls[trim($_GET["src"])];
			
			$res = $db->query(2,"SELECT *FROM `org$cid"."_staff`");
			foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
			$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
			
			$sql = $db->query(3,"SELECT `client`,`type` FROM `wallets$cid` WHERE `id`='$wid'"); 
			$actp = $sql[0]['type']; $uid=$sql[0]['client']; $cuid=strtoupper(substr($actp,0,1).$uid);
			$cname = $db->query(2,"SELECT `name` FROM `$tbls[$actp]` WHERE `id`='$uid'")[0]['name'];
			$mtitle = ucwords($cname)." $vbk Account";
			
			if($vop){
				$ths = "<td>Date</td><td>Investment</td><td>Duration</td><td>Payouts</td><td>Maturity</td><td>Status</td><td>Balance</td>";
				$sql = $db->query(3,"SELECT *FROM `investments$cid` WHERE `client`='$cuid' ORDER BY `time` DESC");
				if($sql){
					foreach($sql as $row){
						$dy=date("d-m-Y,H:i",$row['time']); $def=json_decode($row['details'],1); $amnt=fnum($row['amount']); $pack=prepare($def["pname"]);
						$dur=$row['period']; $paid=fnum($def["payouts"]); $bal=$def["balance"]; $exp=date("d-m-Y, H:i",$row['maturity']); 
						$css = "font-size:13px;color:#fff;padding:4px;font-family:signika negative"; $st=($row['status']>200) ? 200:$row['status'];
						$states = array(0=>"<span style='background:green;$css'>Active</span>",200=>"<span style='background:#20B2AA;$css'>Matured</span>",
						15=>"<span style='background:orange;$css'>Terminated</span>"); $rid=$row['id']; 
						
						$trs.= "<tr><td>$dy</td><td><b>Ksh $amnt</b><br>$pack</td><td>$dur Days</td><td>Ksh $paid</td><td>$exp</td><td>$states[$st]</td>
						<td>Ksh ".fnum($bal)."</td></tr>";
					}
				}
			}
			else{
				$ths = "<td>Date</td><td>Transaction</td><td>Details</td><td>Approval</td><td style='text-align:right'>Amount</td><td style='text-align:right'>Bal After</td>";
				$sql = $db->query(3,"SELECT *FROM `walletrans$cid` WHERE `wallet`='$wid' AND `book`='$vbk' AND `time` BETWEEN $fro AND $dtu ORDER BY `id` DESC,`time` DESC");
				if($sql){
					foreach($sql as $row){
						$dy=date("d-m-Y,H:i",$row['time']); $tid=$row['tid']; $appr=$staff[$row['approval']]; $amnt=number_format($row['amount']); $rid=$row['id'];
						$bal=number_format($row['afterbal']); $css="text-align:right"; $des=prepare(ucfirst($row['details'])); 
						$sgn=($row["transaction"]=="debit") ? "+":"-"; $cls=($row['status']) ? "fade":"";
						$trs.= "<tr class='$cls'><td>$dy</td><td>$tid</td><td>$des</td><td>$appr</td><td style='$css'>$sgn$amnt</td><td style='$css'><b>$bal</b></td></tr>";
					}
				}
			}
			
			$data = "<table style='width:100%;' cellpadding='4' cellspacing='0' class='tbl'><tr class='trx'>$ths</tr> $trs </table>";
		}
	
		#income statement
		if($vtp=="income"){
			$pst = (isset($_GET['pst'])) ? trim($_GET['pst']):0;
			$brn = (isset($_GET['br'])) ? trim($_GET['br']):0;
			$title = ($mon) ? date("F Y",$mon):$yr;
			$title = (isset($_GET['mon']) && !$mon) ? $yr:$title;
			$cond = ($mon) ? "AND `month`='$mon'":""; $mns=[];
			
			$qri = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE `year`='$yr' ORDER BY `month` DESC");
			if($qri){ foreach($qri as $row){ $mns[]=$row['month']; }}
			
			$incomes=$trans=[]; $cols=array("interest","penalties"); $dcols="";
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
			if($res){
				foreach($res as $row){
					$terms=json_decode($row['payterms'],1);
					foreach($terms as $pay=>$des){ $cols[$pay]=$pay; }
				}
			}
			
			$add1 = ($brn) ? "AND `branch`='$brn'":""; $lmons=$icols=[];
			$sql = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `year`='$yr' $cond $add1 ORDER BY `month` DESC");
			if($sql){
				foreach($sql as $row){
					$mn=$row["month"]; $lmons[$mn]=$mn;
					if(!isset($incomes[$mn])){ $incomes[$mn]=[]; }
					foreach($cols as $col){
					    if(isset($row[$col]) or isset($incomes[$mn][$col])){
    						$dcols.="`payment`='".ucfirst($col)."' OR "; 
    						if(isset($incomes[$mn][$col])){ $incomes[$mn][$col]+=$row[$col]; $icols[$col]=$col; }
    						else{ $incomes[$mn][$col]=$row[$col]; $icols[$col]=$col; }
					    }
					}
				}
				
				if($mon){
					$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
					$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def $add1");
					$cko = ($qry) ? $qry[0]['tamnt']:0; if($cko){ $incomes[$mon]["Checkoff Income"]=$cko; }
				}
				else{
					$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":""; $yrst=strtotime("$yr-Jan"); $yrto=strtotime("$yr-Dec"); $adds=[];
					$qri = $db->query(2,"SELECT SUM(amount) AS tamnt,`month` FROM `processed_payments$cid` WHERE `month` BETWEEN $yrst AND $yrto AND `code` LIKE 'CHECKOFF%' $def $add1 GROUP BY month");
					if($qri){
						foreach($qri as $row){
							if($row["month"]){ $incomes[$row["month"]]["Checkoff Income"]=$row["tamnt"]; $adds[]=$row['month']; }
						}
					}
					
					if($adds){
						foreach($lmons as $mn){
							if(!in_array($mn,$adds)){ $incomes[$mn]["Checkoff Income"]=0; }
						}
					}
				}
			}
			else{ $incomes[$tmon] = array("Loan Interest"=>0,"Other Income"=>0); $icols=["Loan Interest","Other Income"]; }
			
			$add2 = ($brn) ? "AND `branch`='$brn'":""; $net=$mls=$xpacs=$accs=[]; $trs="";
			$sqr = $db->query(3,"SELECT SUM(amount) AS tsum,account,month FROM `transactions$cid` WHERE `year`='$yr' AND `book`='expense' AND `type`='debit' AND `amount`>0 $cond $add2 GROUP BY `account`,`month`");
			if($sqr){
				foreach($sqr as $row){
					$mn=$row['month']; $amnt=$row['tsum']; $acc=$row['account'];
					if(!isset($incomes[$mn])){
						foreach($icols as $col){ $incomes[$mn][$col]=0; }
					}
					if(isset($trans[$mn])){
						if(isset($trans[$mn][$acc])){ $trans[$mn][$acc]+=$amnt; }
						else{ $trans[$mn][$acc]=$amnt; }
					}
					else{ $trans[$mn][$acc]=$amnt; }
					if(isset($accs[$acc])){ $accs[$acc]+=$amnt; }
					else{  $accs[$acc]=$amnt; }
				}
			}
			
			krsort($incomes);
			foreach($incomes as $mn=>$one){
				$totinc[$mn]=0;
				foreach($one as $col=>$tot){
					$acc = ($col=="interest") ? "Loan Interest":ucwords(str_replace("_"," ",$col)); 
					$net[$acc][$mn]=$tot; $totinc[$mn]+=($col=="Checkoff Income") ? 0:$tot;
				}
			}
			
			foreach($net as $acc=>$one){ 
				$trs.= "<tr><td>$acc</td>";
				foreach($one as $mn=>$tot){ $trs.="<td style='text-align:right'>".number_format($tot)."</td>"; }
				$trs.= "</tr>";
			}
			
			$cond2 = (!$pst) ? "AND `wing`='0'":"AND `tree`='0'";
			$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='expense' $cond2");
			foreach($res as $row){
				$acc=ucwords(prepare($row['account'])); $rid=$row['id']; $ids=[];
				if($pst){
					if(isset($accs[$rid])){ $xpacs[$acc]=[$rid]; }
				}
				else{
					$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing` LIKE '$rid,%' OR `wing`='$rid'");
					if($qri){
						foreach($qri as $rw){ $ids[]=$rw['id']; }
					}
					$xpacs[$acc] = $ids;
				}
			}
			
			$ths=$etrs=""; $xaccs=$totexp=[];
			$css = "text-align:right;border-top:2px solid #2f4f4f;border-bottom:2px solid #2f4f4f";
			$trs.= "<tr class='trt'><td>Total Income</td>"; 
			foreach($totinc as $mn=>$sum){
				$trs.= "<td style='$css'>".number_format($sum)."</td>"; $totexp[$mn]=0;
				$ths.= "<td style='text-align:right'>".date("M-Y",$mn)."</td>";
				foreach($xpacs as $acc=>$one){ $fnd=[];
					foreach($one as $id){
						if(isset($trans[$mn][$id])){ $fnd[$id]=$trans[$mn][$id]; }
					}
					$xaccs[$acc][$mn]=$fnd; $totexp[$mn]+=array_sum($fnd);
				}
			}
			
			foreach($xaccs as $acc=>$one){
				$etrs.= "<tr><td>$acc</td>";
				foreach($one as $mn=>$ids){
					$etrs.="<td style='text-align:right'>".number_format(array_sum($ids))."</td>"; 
				}
				$etrs.= "</tr>";
			}
			
			$etrs.= "<tr class='trt'><td>Total Expenses</td>"; 
			$gtrs = "<tr class='trt'><td>Gross Income</td>"; 
			$itrs = "<tr class='trt'><td>Income Tax</td>"; 
			$ntrs = "<tr class='trx'><td>Net Profit (Loss)</td>"; 
			foreach($totexp as $mn=>$sum){
				$etrs.= "<td style='$css'>".number_format($sum)."</td>"; 
				$gtrs.= "<td style='text-align:right;'>".number_format($totinc[$mn]-$sum)."</td>"; 
				$itrs.= "<td style='text-align:right;'>".number_format(0)."</td>"; 
				$ntrs.= "<td style='text-align:right;'>".number_format($totinc[$mn]-$sum)."</td>"; 
			}
			
			$trs.= "</tr>"; $etrs.="</tr>"; $gtrs.="</tr>"; $itrs.="</tr>"; $ntrs.="</tr>";
			$tcl = count($incomes)+1; $port=(count($incomes)>6) ? "L":"P";
			$mtitle = "Income Report for $title";
			
			$data = "<table style='width:100%;' cellpadding='4' cellspacing='0' class='tbl'>
				<tr class='trx'><td style='min-width:100px'>Income Account</td>$ths</tr> $trs
				<tr style='background:#fff'><td colspan='$tcl'><br><h2 style='color:#191970;font-size:16px;font-weight:bold'>EXPENSES</h2></td></tr> 
				$etrs $gtrs $itrs $ntrs
			</table>";
		}
		
		#Trial Balance
		if($vtp=="trialbal"){
			$monstart = ($yr==$tyr) ? intval(date("m")):12;
			$right=array("liability","equity"); $saved=$creds=$debs=[]; $trs=$thd=$ths1=$ths2=$lds=""; 
			$mls = array("","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
			$qri = $db->query(3,"SELECT *FROM `monthly_balances$cid` WHERE `year`='$yr'");
			if($qri){
				foreach($qri as $row){ $saved[$row['account']][$row["month"]]=$row["balance"]; }
			}
			
			$qri = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
			foreach($qri as $row){
				if($row["tree"]==0 or isset($saved[$row['id']])){ $accs[$row["id"]]=$row; }
			}
			
			foreach($accs as $rid=>$row){
				$cbal=intval($row['balance']); $name=prepare(ucfirst($row["account"])); $type=$row['type']; 
				$css="style='text-align:right;background:#F5F5DC;'"; $tds="";
				
				for($mn=$monstart; $mn>=1; $mn--){
					$mon = strtotime("$yr-".$mls[$mn]); $creds[$mon][$rid]=$debs[$mon][$rid]=0; $rgt=$lft="";
					if($mon==$tmon){
						if(in_array($type,$right)){ $rgt=number_format($cbal); $creds[$mon][$rid]+=$cbal;  }
						else{ $lft=number_format($cbal); $debs[$mon][$rid]+=$cbal; }
						$tds.= "<td>$lft</td><td $css>$rgt</td>";
					}
					else{
						if(isset($saved[$rid])){
							if(isset($saved[$rid][$mon])){
								if(in_array($type,$right)){ $val=$saved[$rid][$mon]; $rgt=number_format($val); $creds[$mon][$rid]+=$val; }
								else{ $val=$saved[$rid][$mon]; $lft=number_format($val); $debs[$mon][$rid]+=$val; } 
								$tds.= "<td>$lft</td><td $css>$rgt</td>";
							}
							else{ $tds.= "<td>--</td><td $css>--</td>"; }
						}
						else{ $tds.= "<td>--</td><td $css>--</td>"; }
					}
				}
				$trs.= "<tr><td>".prenum($rid)."</td><td>$name</td>$tds</tr>";
			}
			
			for($mn=$monstart; $mn>=1; $mn--){
				$mon = strtotime("$yr-".$mls[$mn]); $crd=(isset($creds[$mon])) ? number_format(array_sum($creds[$mon])):0; 
				$dbt = (isset($debs[$mon])) ? number_format(array_sum($debs[$mon])):0; $ths1.= "<td colspan='2' style='text-align:center;color:#fff'><b>$mls[$mn] $yr</b></td>"; 
				$ths2.= "<td>Debits</td><td>Credits</td>"; $thd.= "<td>$dbt</td><td style='text-align:right'>$crd</td>";
			}
			
			$mtitle = "$yr Trial Balance";
			$data = "<table style='width:100%;font-size:15px;' cellpadding='5' cellspacing='0' class='tbl'>
				<tr style='background:#2f4f4f'><td style='color:#fff'>Code</td><td style='min-width:100px;color:#fff'><b>Account</b></td>$ths1</tr> 
				<tr style='background:#f0f0f0;font-size:13px' class='trt'><td colspan='2'></td>$ths2</tr> $trs
				<tr style='background:#e6e6fa;font-size:13px;' class='trx'><td colspan='2'>Totals</td>$thd</tr>
			</table>";
		}
		
		# balance sheet
		if($vtp=="balsheet"){
			$monstart = ($yr==$tyr) ? intval(date("m")):12;
			$mls = array("","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
			$trs=$ths=$lds=""; $mxw="1200px"; $totlbs=[];
	
			$res = $db->query(3,"SELECT *FROM `accounts$cid`");
			foreach($res as $row){
				$id=$row['id']; $names[$id]=ucwords(prepare($row['account'])); $bals[$id][$tmon]=intval($row['balance']); 
				$trees[$id]=$row['tree']; $wings[$id]=$row['wing'];
			}
			
			$qry = $db->query(3,"SELECT *FROM `monthly_balances$cid` WHERE `year`='$yr'");
			if($qry){
				foreach($qry as $row){
					$bals[$row['account']][$row['month']]=intval($row['balance']);
				}
			}
			
			foreach(array("asset"=>"Assets","liability"=>"Liabilities","equity"=>"Equity") as $col=>$txt){
				$css = "border-right:1px solid #ccc"; $tots=[]; $cls=$monstart+1;
				$trs.= "<tr class='trt'><td colspan='$cls'><i>".strtoupper($txt)."</i></td></tr>";
				
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `level`='0' AND `type`='$col'");
				foreach($sql as $rw){
					$name=prepare(ucfirst($rw["account"])); $rid=$rw['id']; $bal=0;
					if($rw["tree"]){
						$trs.= "<tr style='background:#e6e6fa;color:#191970'><td><b>$name</b></td>"; 
						for($mn=$monstart; $mn>=1; $mn--){ $trs.= "<td></td>"; } $trs.="</tr>"; $tot1=[];
						$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `level`>0 AND `type`='$col' AND `wing`='$rid'");
						foreach($qri as $row){
							$id=$row['id']; $name2=prepare(ucfirst($row["account"]));
							if($row['tree']){
								$trs.= "<tr><td style='padding-left:20px;$css'><b>$name2</b></td>";
								for($mn=$monstart; $mn>=1; $mn--){ $trs.= "<td style='$css'></td>"; } $trs.="</tr>";
								$qry = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `level`>0 AND `type`='$col' AND `wing` LIKE '$rid,$id%'");
								foreach($qry as $roq){
									$name3=prepare(ucfirst($roq["account"])); $idr=$roq['id'];
									$trs.= "<tr><td style='padding-left:40px;$css'>$name3</td>";
									for($i=$monstart; $i>=1; $i--){
										$mn = strtotime("$yr-".$mls[$i]);
										if(isset($bals[$idr])){ $bal=(isset($bals[$idr][$mn])) ? $bals[$idr][$mn]:0; }else{ $bal=0; }
										$trs.= "<td style='text-align:center;$css'>".number_format($bal)."</td>"; 
										if(isset($tots[$mn])){ $tots[$mn]+=$bal; }else{ $tots[$mn]=$bal; }
										if(isset($tot1[$mn])){ $tot1[$mn]+=$bal; }else{ $tot1[$mn]=$bal; }
									}
									$trs.= "</tr>";
								}
							}
							else{
								$trs.= "<tr><td style='padding-left:20px;$css'>$name2</td>";
								for($i=$monstart; $i>=1; $i--){
									$mn = strtotime("$yr-".$mls[$i]);
									if(isset($bals[$id])){ $bal=(isset($bals[$id][$mn])) ? $bals[$id][$mn]:0; }else{ $bal=0; } 
									if(isset($tots[$mn])){ $tots[$mn]+=$bal; }else{ $tots[$mn]=$bal; }
									if(isset($tot1[$mn])){ $tot1[$mn]+=$bal; }else{ $tot1[$mn]=$bal; }
									$trs.= "<td style='text-align:center;$css'>".number_format($bal)."</td>";
								}
								$trs.= "</tr>";
							}
						}
						$trs.= "<tr><td style='border-top:2px solid #ccc;'><b>Total $name</b></td>";
						foreach($tot1 as $mn=>$tot){ $trs.="<td style='text-align:center;border-top:2px solid #ccc;'><b>".number_format($tot)."</b></td>"; }
						$trs.= "</tr><tr style='background:#fff'><td colspan='$cls'></td></tr>";
					}
					else{
						$trs.= "<tr><td style='$css'>$name</td>"; 
						for($i=$monstart; $i>=1; $i--){
							$mn = strtotime("$yr-".$mls[$i]);
							if(isset($bals[$rid])){ $bal=(isset($bals[$rid][$mn])) ? $bals[$rid][$mn]:0; }else{ $bal=0; }
							if(isset($tots[$mn])){ $tots[$mn]+=$bal; }else{ $tots[$mn]=$bal; }
							$trs.= "<td style='text-align:center;$css'>".number_format($bal)."</td>"; 
						}
						$trs.= "</tr>";
					}
				}
				
				$csl = ($col!="asset") ? "border-top:2px solid #2f4f4f;border-bottom:2px solid #2f4f4f;":""; 
				$trs.= ($col=="asset") ? "<tr class='trx'><td>Total $txt</td>":"<tr class='trt'><td>Total $txt</td>"; 
				foreach($tots as $mn=>$tot){
					$trs.="<td style='text-align:center;$csl'>".number_format($tot)."</td>"; 
					if($col!="asset"){ if(isset($totlbs[$mn])){ $totlbs[$mn]+=$tot; }else{ $totlbs[$mn]=$tot; }}
				}
				$trs.= "</tr><tr style='background:#fff'><td colspan='$cls'></td></tr>";
			}
			
			$trs.= "<tr class='trx'><td>Total Equity & Liabilities</td>";
			for($mn=$monstart; $mn>=1; $mn--){
				$mon = strtotime("$yr-".$mls[$mn]); $ths.="<td style='text-align:center;color:#fff'><b>$mls[$mn] $yr</b></td>"; 
				$trs.="<td style='text-align:center;'>".number_format($totlbs[$mon])."</td>"; 
			}
			
			$trs.= "</tr>";
			$port = (count($totlbs)>6) ? "L":"P";
			$mtitle = "$yr Balance Sheet";
			
			$data = "<table style='width:100%;font-size:14px' cellpadding='5' cellspacing='0'>
				<tr style='background:#2f4f4f;font-size:13px'><td style='min-width:100px;color:#fff'><b>Account</b></td>$ths</tr> $trs
			</table>";
		}
		
		# payments summary
		if($vtp=="paysum"){
			$bran = trim($_GET['br']);
			$dels = array("id","paybill","branch","officer","month","year");
			$fields = $db->tableFields(2,"paysummary$cid"); $ths="";
			
			foreach($fields as $col){
				if(!in_array($col,$dels)){
					$ths.="<td>".ucwords(str_replace("_"," ",$col))."</td>"; $cols[]=$col;
				}
			}
			
			$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($qr){
				foreach($qr as $row){
					$bnames[$row['id']]=prepare(ucwords($row['branch']));
				}
			}
			
			$me = staffInfo($sid); 
			$access = $me['access_level']; $pcko=0;
			if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
			$bids=$data=$incomes=[]; $brans=$trs="";
			
			$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
			foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
			
			$icols = array("interest","penalties");
			$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
			if($res){
				foreach($res as $row){
					$terms=json_decode($row['payterms'],1);
					foreach($terms as $pay=>$des){ $icols[$pay]=$pay; }
				}
			}
			
			if($access=="hq"){
				$res = $db->query(2,"SELECT DISTINCT branch FROM `paysummary$cid` WHERE `month`='$mon'");
				if($res){
					foreach($res as $row){ $bids[]=$row['branch']; }
				}
			}
			
			if($me['access_level']=="hq"){
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
							foreach($icols as $col){ $income+=$rw[$col]; $dcols.="`payment`='".ucfirst($col)."' OR "; }
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
							if(isset($dat[$col])){
								if(isset($rw[$col])){ $dat[$col]+=$rw[$col]; }
							}
							else{
								if(isset($rw[$col])){ $dat[$col]=$rw[$col]; }
								else{ $dat[$col]=0; }
							}
						}
						foreach($icols as $col){ $income+=$rw[$col]; $dcols.="`payment`='".ucfirst($col)."' OR "; }
					}
					
					$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
					$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `officer`='$sid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
					$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$sid]=$dat; $pcko+=$cko; $incomes[$sid]=array("total"=>$income,"checkoff"=>$cko);
				}
			}
			
			$totals=$itotal=$ckos=0; $trs=$tdk=""; $tcols=[]; 
			foreach($data as $pos=>$arr){
				$tname = ($me['access_level']=="hq") ? $bnames[$pos]:$staff[$pos]; $total=array_sum($arr); 
				$totals+=$total; $tds=""; $go=($me['access_level']=="hq") ? "b:$pos":"s:$pos";
				foreach($cols as $col){
					if(array_key_exists($col,$tcols)){ $tcols[$col]+=$arr[$col]; }
					else{ $tcols[$col]=$arr[$col]; }
					$tds.="<td>".number_format($arr[$col])."</td>";
				}
				
				$cko = $incomes[$pos]['checkoff']; $ckos+=$cko; $itotal+=$incomes[$pos]['total']; 
				$tds.= ($pcko) ? "<td>".number_format($cko)."</td>":"";
				$trs.= "<tr><td>$tname</td>$tds<td>".number_format($incomes[$pos]['total'])."</td><td>".number_format($total)."</td></tr>";
			}
			
			foreach($cols as $col){
				$tdk.= (isset($tcols[$col])) ? "<td>".number_format($tcols[$col])."</td>":"<td>0</td>";
			}
			
			$ths.= ($ckos) ? "<td>Checkoff Income</td>":""; $tdk.= ($ckos) ? "<td>".number_format($ckos)."</td>":"";
			$trs.= "<tr class='trh'><td>Totals</td>$tdk<td>".number_format($itotal)."</td><td>".number_format($totals)."</td></tr>";
			$add = ($bran or $me['access_level']=="portfolio") ? $bnames[$me['branch']]:"Corporate";
			$mtitle = date("M Y",$mon)." $add Payments Report"; $port="L";
			
			$data = "<table class='tbl' style='width:100%;font-size:15px;' cellspacing='0' cellpadding='5'>
				<tr class='trh'>$ths<td>Total Income</td><td>Totals</td></tr> $trs
			</table>";
		}
	
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>"A4-$port"]);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage($port);
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($mtitle);
	$mpdf->SetWatermarkImage("data:image/$ext;base64,".getphoto($logo));
	$mpdf->showWatermarkImage = true;
	$mpdf->setFooter('<p style="text-align:center"> '.$mtitle.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:arial;}
		.tbl td{font-size:14px;color:#000;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;background:#e6e6fa}
		.trx td{font-weight:bold;color:#fff;font-size:13px;background:#4682b4}
		.trt td{font-weight:bold;color:#191970;background:#fff;}
		.tbl .fade td{color:#B0C4DE;}
	",1);
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML("<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='60'></p>
	<h3 style='color:#191970;text-align:center;'>$mtitle</h3><h4 style='color:#2f4f4f'>Generated on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4> $data");
	$mpdf->Output(str_replace(" ","_",$mtitle).'.pdf','I');
	exit;

?>