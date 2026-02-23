<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	
	# acc Balances 
	if(isset($_GET["accbals"])){
		$vtp = intval($_GET["accbals"]);
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		
		$me = staffInfo($sid); $perpage=25; $totals=$tsum=0;
		$perms = getroles(explode(",",$me['roles']));
		$lim = getLimit($page,$perpage);
		
		$titles = array("client","staff","investor"); $vta=$titles[$vtp];
		$tbls = array("org$cid"."_clients","org$cid"."_staff","investors$cid");
		$cond = ($str) ? "AND (`name` LIKE '$str%' OR `idno`='$str' OR `contact`='$str')":"";
		
		$no=($perpage*$page)-$perpage; $tbl=$tbls[$vtp]; $trs=$opts=""; $data=[];
		if($db->istable(2,$tbls[$vtp]) && $db->istable(3,"wallets$cid")){
			$sql = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `type`='$vta' AND (savings+balance+investbal)>0");
			if($sql){
				foreach($sql as $key=>$rw){
					if(!$str){ $tsum+=$rw["balance"]+$rw["investbal"]+$rw["savings"]; }
					$data[$rw["client"]] = array("wid"=>$rw["id"],"savings"=>$rw["savings"],"balance"=>$rw["balance"],"investbal"=>$rw["investbal"]); 
				}
				
				$qry = $db->query(2,"SELECT COUNT(*) AS tot FROM `$tbl` WHERE `id` IN (".implode(",",array_keys($data)).") $cond"); $totals=intval($qry[0]["tot"]);
				$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `id` IN (".implode(",",array_keys($data)).") $cond ORDER BY name ASC $lim");
				if($qri){
					foreach($qri as $row){
						$name=prepare(ucwords($row["name"])); $fon=$row["contact"]; $idno=$row["idno"]; $uid=$row["id"]; $def=$data[$uid]; $wid=$def["wid"]; $svb=fnum($def["savings"]);
						$tbal=fnum($def["balance"]); $ibal=fnum($def["investbal"]); $tval=$def["savings"]+$def["balance"]+$def["investbal"]; $st=$row["status"]; 
						$vaccs = array("client"=>"clients.php?showclient=$idno","staff"=>"hr/staff.php?vstid=$uid","investor"=>"accounts/investors.php?wallet=$uid");
						$wbals = array("client"=>"clients.php?wallet=$uid","staff"=>"hr/staff.php?wallet=$uid","investor"=>"accounts/investors.php?wallet=$uid");
						$vsb = ($svb) ? "<a href='javascript:void(0)' onclick=\"loadpage('$wbals[$vta]&vtp=savings')\">View</a>":"";
						$vbl = ($tbal) ? "<a href='javascript:void(0)' onclick=\"loadpage('$wbals[$vta]&vtp=balance')\">View</a>":"";
						$vibl = ($ibal) ? "<a href='javascript:void(0)' onclick=\"loadpage('$wbals[$vta]&vtp=investbal')\">View</a>":"";
						if($str){ $tsum+=$tval; }
						
						$states = array("client"=>["Dormant:orange","Active:green","Suspended:purple"],"staff"=>["Active:green","Suspended:purple","Inactive:orange"],
						"investor"=>["Inactive:purple","Active:green"]); $exd=explode(":",$states[$vta][$st]); $color=$exd[1]; $stx=$exd[0]; $no++;
						$state = "<span style='color:$color'><i class='fa fa-circle'></i> $stx</span>";
						
						$trs.= "<tr valign='top'><td>$no</td><td>$name <i class='bi-box-arrow-up-right' style='cursor:pointer;float:right;color:#008fff;font-size:17px'
						onclick=\"loadpage('$vaccs[$vta]')\" title='Open $name Account'></i></td><td>0$fon</td><td>$idno</td>
						<td>$svb $vsb</td><td>$tbal $vbl</td><td>$ibal $vibl</td><td>".fnum($tval)."</td><td>$state</td></tr>";
					}
				}
			}
		}
		
		foreach($titles as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$opts.= "<option value='$key' $cnd>".ucwords($txt)." Accounts</option>";
		}
		
		$actp = ucfirst($titles[$vtp]); $title="$actp Wallet Balances";
		$trs = ($trs) ? $trs:"<tr><td colspan='6'>No Balances record found</td></tr>";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div style='padding:10px 5px;'>
				<h3 style='font-size:22px;color:#191970;margin-bottom:10px'>$backbtn $title
				<span style='float:right;font-weight:bold;font-size:16px'>Ksh ".fnum($tsum)."</span></h3><hr style='margin-bottom:0px'>
				<div style='width:100%;overflow:auto;'>
    				<table class='table-striped' style='width:100%;min-width:500px;font-size:15px;' cellpadding='5'>
						<caption style='caption-side:top'>
							<select Style='width:170px;padding:6px;font-size:15px' onchange=\"loadpage('accounts/reports.php?accbals='+this.value)\">$opts</select>
							<input type='search' onkeyup=\"fsearch(event,'accounts/reports.php?accbals=$vtp&str='+cleanstr(this.value))\" 
							onsearch=\"loadpage('accounts/reports.php?accbals=$vtp&str='+cleanstr(this.value))\" 
							value=\"".prepare($str)."\" style='width:170px;padding:4px 6px;font-size:15px;float:right' placeholder='&#xf002; Search $actp'>
						</caption>
    					<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:15px;cursor:default' valign='top'><td colspan='2'>$actp Name</td>
						<td>Contact</td><td>Idno</td><td>Savings Acc</td><td>Transactional Acc</td><td>Investment Acc</td><td>Total Bal</td><td>Account Status</td></tr> $trs
    				</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"accounts/reports.php?accbals=$vtp&str=".str_replace(" ","+",$str))."
		</div>";
		savelog($sid,"Viewed $title");
	}
	
	# bank reconciliation
	if(isset($_GET["reconc"])){
		$acc = trim($_GET["reconc"]); $tmon=strtotime(date("Y-M")); 
		$acc = (isset($_SESSION["reconn"]) && !$acc) ? $_SESSION['reconn']:$acc;
		$mon = (isset($_GET["mon"])) ? trim($_GET["mon"]):$tmon; $opts=$capt=$trs="";
		if(!$db->istable(3,"reconns$cid")){ $db->createTbl(3,"reconns$cid",["account"=>"INT","title"=>"CHAR","month"=>"INT","report"=>"TEXT","status"=>"INT","time"=>"INT"]); }
		
		$chk = $db->query(3,"SELECT *FROM `reconns$cid` WHERE `month`='$mon' ORDER BY `status` ASC");
		if($chk){
			foreach($chk as $row){
				$rid = $row['id']; $title=prepare(ucfirst($row['title']));
				$cnd = ($rid==$acc) ? "selected":""; $accs[$rid]=$row;
				$opts.= "<option value='$rid' $cnd>$title</option>";
				if(!$acc){ $acc=$rid; }
			}
		}
		
		$mons = "<option value='$tmon'>".date("M Y")."</option>";
		$sql = $db->query(3,"SELECT DISTINCT `month` FROM `reconns$cid` WHERE NOT `month`='$tmon' ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":"";
				$mons.= "<option value='$mn' $cnd>".date("M Y",$mn)."</option>";
			}
		}
		
		if($acc){
			$row = $accs[$acc]; $acid=$row['account']; $mn=$row["month"]; $rep=json_decode($row['report'],1); 
			$sql = $db->query(3,"SELECT `account` FROM `accounts$cid` WHERE `id`='$acid'");
			$name = strtoupper(prepare($sql[0]['account'])." ".date("M-Y",$mn)); $uid=$rep["user"];
			$capt = "<caption style='caption-side:top;padding:0px'><p style='color:#191970;font-weight:bold'>$name 
			<span style='float:right;font-size:13px;color:#2f4f4f'>Created By: ".ucwords(staffInfo($uid)['name'])."</span></p></caption>";
			
			$pmon = strtotime("-1 month",$mn); $creds=$debs=0;
			$qry = $db->query(3,"SELECT `balance` FROM `monthly_balances$cid` WHERE `account`='$acid' AND `month`='$pmon'");
			$bal = ($qry) ? $qry[0]['balance']:0; $bclos=$rep["closebal"]; $rpl=(isset($rep["checked"])) ? $rep["checked"]:[];
			$trs = "<tr style='font-weight:bold'><td>".date("d-m-Y",$mn)."</td><td></td><td>Opening balance</td><td></td><td></td><td style='text-align:right'>".number_format($bal)."</td></tr>";
				
			$qri = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `account`='$acid' AND `month`='$mn' AND `amount`>0 ORDER BY `day`,`time` ASC");
			if($qri){
				foreach($qri as $row){
					$type=$row['type']; $tid=$row['transid']; $des=prepare($row['details']); $amnt=$row['amount']; $dy=date("d-m-Y",$row['day']);
					$dbr = ($type=="debit") ? number_format($amnt):""; $cbr=($type=="credit") ? number_format($amnt):""; $id=$row['id']; 
					$css = (in_array($id,$rpl)) ? "color:green;background:#c1f0c1":""; $debs+=($type=="debit") ? $amnt:0;
					$bal+= ($type=="debit") ? $amnt:0; $bal-=($type=="credit") ? $amnt:0; $creds+=($type=="credit") ? $amnt:0;
					$act = ($css) ? "":"<i class='bi-check2-circle' style='color:green;font-size:20px;cursor:pointer' onclick=\"confmatch('$id','$acc')\" title='Confirm Matching'></i>";
					$trs.= "<tr style='$css' id='tr$id'><td>$dy</td><td>$tid</td><td>$des</td><td>$dbr</td><td>$cbr</td><td style='text-align:right'>
					".number_format($bal)."</td><td id='trl$id'>$act</td></tr>";
				}
			}
			
			$drdf = ($debs>$creds) ? number_format($debs-$creds):"(".str_replace("-","",number_format($creds-$debs)).")";
			$bldf = ($bal>$bclos) ? number_format($bal-$bclos):"(".str_replace("-","",number_format($bal-$bclos)).")";
			$css2 = "border-bottom:3px solid #2f4f4f;border-top:3px solid #2f4f4f";
			
			$trs.= "<tr style='color:#191970;font-weight:bold'><td colspan='3'></td><td style='$css2'>".number_format($debs)."</td>
			<td style='$css2'>".number_format($creds)."</td><td style='$css2;text-align:right'>".number_format($bal)."</td><td></tr>";
			$trs.= "<tr style='color:#fff;font-weight:bold;background:#4682b4'><td colspan='3'></td><td><br>$drdf</td><td></td>
			<td style='text-align:right'>".number_format($bclos)."<br>$bldf</td><td></tr>";
		}
		
		$opts = ($opts) ? $opts:"<option value='0'>-- Select Project --</option>";
		$trs = ($trs) ? $trs:"<tr><td colspan='5'>No Transactions Found</td></tr>";
		
		echo "<div class='container cardv' style='max-width:1200px;min-height:300px;overflow:auto'>
			<h3 style='color:#191970;font-size:22px;'>$backbtn Book Reconciliation</h3><hr>
			<p><select style='width:110px;font-size:15px' onchange=\"loadpage('accounts/reports.php?reconc&mon='+this.value)\">$mons</select>
			<select style='width:150px;font-size:15px' onchange=\"loadpage('accounts/reports.php?mon=$mon&reconc='+this.value)\">$opts</select>
			<button class='bts' style='padding:3px 6px;float:right' onclick=\"popupload('accounts/reports.php?reconadd')\"><i class='fa fa-plus'></i> Project</button></p>
			<div style='width:100%;overflow:auto'>
				<table cellpadding='4' style='width:100%;font-size:15px;min-width:700px' class='table-striped'> $capt
					<tr style='background:#2f4f4f;color:#fff;font-weight:bold'><td>Date</td><td>Transaction</td><td>Details</td><td>Debit</td><td>Credit</td>
					<td style='text-align:right'>Balance</td><td></td></tr> $trs
				</table>
			</div>
		</div>";
		savelog($sid,"Accessed accounts reconciliation");
	}
	
	# create Reconciliation Project
	if(isset($_GET["reconadd"])){
		$tmon = date("Y-m"); $opts="";
		$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='asset' AND `tree`='0'"); 
		if($sql){
			foreach($sql as $row){
				$rid=$row['id']; $name=prepare(ucwords($row['account']));
				$opts.= (!in_array($rid,[14,21]) && $name!="Accounts Reconciliation") ? "<option value='$rid'>$name</option>":"";
			}
		}
		
		echo "<div style='max-width:320px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Create Reconciliation Project</h3><br>
			<form method='post' id='rfom' onsubmit='savereconn(event)'>
				<p>Select Account to Reconcile<br><select name='racc' style='width:100%' required>$opts</select></p>
				<p>Month to Reconcile<br><input type='month' style='width:100%;padding:5px;border:1px solid #ccc;outline:none' name='recmon' value='$tmon' max='$tmon' required></p>
				<p>External book Closing Balance<br><input type='number' style='width:100%' name='rcbal' required></p>
				<p>Project Name<br><input type='text' style='width:100%' name='rcname' required></p><br>
				<p style='text-align:right'><button class='btnn'><i class='fa fa-plus'></i> Create</button></p>
			</form>
		</div>";
	}
	
	# manage reports
	if(isset($_GET['manage'])){
		$vtp = (isset($_GET['report'])) ? trim($_GET['report']):"income";
		$yr = (isset($_GET['yr'])) ? trim($_GET['yr']):date("Y");
		$tyr = date("Y"); $tmon=strtotime(date("Y-M")); $opts="";
		
		$reps = array("income"=>"Income Report","balsheet"=>"Balance Sheet","trialbal"=>"Trial Balance");
		foreach($reps as $key=>$view){
			$cnd = ($vtp==$key) ? "selected":"";
			$opts.="<option value='$key' $cnd>$view</option>";
		}
		
		#income statement
		if($vtp=="income"){
			$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
			$brn = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
			$mtl = (defined("INCOME_MONTH")) ? INCOME_MONTH:"default";
			$mon = (!isset($_GET['mon']) && (($mtl=="current") or ($mon==0 && intval(date("m"))>5))) ? strtotime(date("Y-M")):$mon;
			$mon = (!isset($_GET["mon"]) && (($mtl=="all") or $yr!=date("Y"))) ? 0:$mon;
			$mon = (!$mon && !isset($_GET["mon"]) && $mtl=="current" && $yr<date("Y")) ? strtotime("$yr-Dec"):$mon;
			
			$title = ($mon) ? date("F Y",$mon):$yr;
			$title = (isset($_GET['mon']) && !$mon) ? $yr:$title;
			$cond = ($mon) ? "AND `month`='$mon'":"";
			$lds = ($brn) ? "&brn=$brn":"";
			
			$mons = "<option value='0'>All Months</option>"; $mns=[];
			$qri = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE `year`='$yr' ORDER BY `month` DESC");
			if($qri){ foreach($qri as $row){ $mns[]=$row['month']; }}
			
			if($yr==date("Y") && !in_array($tmon,$mns)){ $mns[]=$tmon; } rsort($mns);
			foreach($mns as $mn){
				$cnd=($mon==$mn) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date("M Y",$mn)."</option>";
			}
			
			$yrs = "<option value='$tyr'>$tyr</option>";
			$res = $db->query(2,"SELECT DISTINCT `year` FROM `paysummary$cid` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
			if($res){
				foreach($res as $row){
					$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
					$yrs.="<option value='$year' $cnd>$year</option>";
				}
			}
			
			$brans = "<option value='0'>-- Corporate --</option>"; $pnames=[];
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `paysummary$cid` WHERE `year`='$yr' $cond");
			if($res){
				foreach($res as $row){
					$bran=$row['branch']; $cnd=($brn==$bran) ? "selected":"";
					$qri = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'")[0]; 
					$brans.="<option value='$bran' $cnd>".prepare(ucfirst($qri['branch']))."</option>";
				}
			}
			
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
			
			$add2 = ($brn) ? "AND `branch`='$brn'":""; $net=$mls=$xpacs=[]; $trs="";
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
			
			$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='expense' AND `wing`='0'");
			foreach($res as $row){
				$acc=ucwords(prepare($row['account'])); $rid=$row['id']; $ids=[];
				$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing` LIKE '$rid,%' OR `wing`='$rid'");
				if($qri){
					foreach($qri as $rw){ $ids[]=$rw['id']; }
				}
				$xpacs[$acc] = $ids;
			}
			
			$ths=$etrs=""; $xaccs=$totexp=[];
			$css = "text-align:right;border-top:2px solid #2f4f4f;border-bottom:2px solid #2f4f4f";
			$trs.= "<tr style='color:#191970;font-weight:bold;background:#fff'><td>Total Income</td>"; 
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
					$plus = (count($ids)) ? "<i class='fa fa-plus' style='color:#008fff;cursor:pointer' title='View Accounts'
					onclick=\"popupload('accounts/reports.php?viewexpsubs=".implode(":",array_keys($ids))."&mon=$mn&yr=$yr&br=$brn')\"></i>":"";
					$etrs.="<td style='text-align:right'>".number_format(array_sum($ids))." $plus</td>"; 
				}
				$etrs.= "</tr>";
			}
			
			$etrs.= "<tr style='color:#191970;font-weight:bold;background:#fff'><td>Total Expenses</td>"; 
			$gtrs = "<tr style='color:#191970;font-weight:bold;background:#fff'><td>Gross Income</td>"; 
			$itrs = "<tr style='color:#191970;font-weight:bold;background:#fff'><td>Income Tax</td>"; 
			$ntrs = "<tr style='color:#fff;font-weight:bold;background:#2f4f4f'><td>Net Profit (Loss)</td>"; 
			foreach($totexp as $mn=>$sum){
				$etrs.= "<td style='$css'>".number_format($sum)."</td>"; 
				$gtrs.= "<td style='text-align:right;'>".number_format($totinc[$mn]-$sum)."</td>"; 
				$itrs.= "<td style='text-align:right;'>".number_format(0)."</td>"; 
				$ntrs.= "<td style='text-align:right;'>".number_format($totinc[$mn]-$sum)."</td>"; 
			}
			$trs.= "</tr>"; $etrs.="</tr>"; $gtrs.="</tr>"; $itrs.="</tr>"; $ntrs.="</tr>";
			
			$mxw = (count($incomes)<=4) ? "950px":"1400px";
			$mxw = (count($incomes)>4 && count($incomes)<8) ? "1100px":$mxw;
			$mxd = (count($incomes)<=4) ? "700px":"1400px";
			$mnw = ($mon) ? "":"450px"; $tcl=count($incomes)+1;
			
			$selcss = "padding:4px;border:1px solid #dcdcdc;margin-left:5px;font-size:15px;float:right;background:#F8F8FF;cursor:pointer;color:#4682b4;margin-top:10px";
			$addins = "<select style='$selcss;width:100px' onchange=\"loadpage('accounts/reports.php?manage&report=$vtp&yr=$yr&mon='+this.value)\">$mons</select>
			<select style='$selcss;width:70px;' onchange=\"loadpage('accounts/reports.php?manage&report=$vtp&yr='+this.value)\">$yrs</select>";
			
			$data = "<div style='margin:0 auto;overflow:auto;max-width:$mxd'>
				<table style='width:100%;margin:0 auto;font-size:15px;min-width:$mnw;' cellpadding='4' class='table-striped'>
					<caption style='caption-side:top;padding:;'>
						<h2 style='color:#191970;font-weight:bold;font-size:20px;'>Income Report for $title
						<select style='width:150px;padding:5px;font-size:15px;cursor:pointer;color:#4682b4;float:right' 
						onchange=\"loadpage('accounts/reports.php?manage&report=$vtp&yr=$yr&mon=$mon&bran='+this.value)\">$brans</select></h2>
					</caption>
					<tr style='background:#e6e6fa;font-weight:bold;color:#191970'><td style='min-width:100px'>Income Account</td>$ths</tr> $trs
					<tr style='background:#fff'><td colspan='$tcl'><h2 style='color:#191970;font-size:16px;margin-top:10px;font-weight:bold'>EXPENSES</h2></td></tr> 
					$etrs $gtrs $itrs $ntrs
				</table>
			</div>";
			
			savelog($sid,"Viewed Income statement for $title");
		}
		
		#Trial Balance
		if($vtp=="trialbal"){
			$monstart = ($yr==$tyr) ? intval(date("m")):12;
			$yrs = "<option value='$tyr'>$tyr</option>"; $mxw="1400px";
			$res = $db->query(3,"SELECT DISTINCT `year` FROM `monthly_balances$cid` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
			if($res){
				foreach($res as $row){
					$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
					$yrs.="<option value='$year' $cnd>$year</option>";
				}
			}
			
			$selcss = "padding:4px;border:1px solid #dcdcdc;margin-left:5px;font-size:15px;float:right;background:#F8F8FF;cursor:pointer;color:#4682b4;margin-top:10px";
			$addins = "<select style='$selcss;width:70px;' onchange=\"loadpage('accounts/reports.php?manage&report=$vtp&yr='+this.value)\">$yrs</select>";
			
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
				$dbt = (isset($debs[$mon])) ? number_format(array_sum($debs[$mon])):0; $ths1.= "<td colspan='2' style='text-align:center'>$mls[$mn] $yr</td>"; 
				$ths2.= "<td>Debits</td><td>Credits</td>"; $thd.= "<td>$dbt</td><td style='text-align:right'>$crd</td>";
			}
			
			$data = "<div style='max-width:1400px;margin:0 auto;overflow:auto'>
				<h2 style='color:#191970;font-weight:bold;font-size:21px;'>$yr Trial Balance <button class='bts' style='font-size:14px;float:right;padding:4px' 
				onclick=\"genreport('reports.php?vtp=trbl&yr=$yr','xls')\"><i class='fa fa-file-excel-o'></i> Gen Excel</button></h2>
				<div style='width:100%;overflow:auto'>
					<table style='width:100%;font-size:14px;min-width:700px' cellpadding='5' class='table-striped table-bordered'>
						<tr style='background:#2f4f4f;font-weight:bold;color:#fff'><td>Code</td><td style='min-width:100px'>Account</td>$ths1</tr> 
						<tr style='background:#f0f0f0;font-weight:bold;font-size:13px'><td colspan='2'></td>$ths2</tr> $trs
						<tr style='background:#e6e6fa;font-weight:bold;font-size:13px;color:#191970'><td colspan='2'>Totals</td>$thd</tr>
					</table>
				</div><br>
			</div>";
			
			savelog($sid,"Viewed Trial Balance for $yr");
		}
		
		# balance sheet
		if($vtp=="balsheet"){
			$monstart = ($yr==$tyr) ? intval(date("m")):12;
			$yrs = "<option value='$tyr'>$tyr</option>";
			$res = $db->query(3,"SELECT DISTINCT `year` FROM `monthly_balances$cid` WHERE NOT `year`='$tyr' ORDER BY `year` DESC");
			if($res){
				foreach($res as $row){
					$year=$row['year']; $cnd=($year==$yr) ? "selected":"";
					$yrs.="<option value='$year' $cnd>$year</option>";
				}
			}
			
			$selcss = "padding:4px;border:1px solid #dcdcdc;margin-left:5px;font-size:15px;float:right;background:#F8F8FF;cursor:pointer;color:#4682b4;margin-top:10px";
			$addins = "<select style='$selcss;width:80px;' onchange=\"loadpage('accounts/reports.php?manage&report=$vtp&yr='+this.value)\">$yrs</select>";
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
				$trs.= "<tr style='color:#191970;font-weight:bold'><td colspan='$cls'><i>".strtoupper($txt)."</i></td></tr>";
				
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `level`='0' AND `type`='$col'");
				foreach($sql as $rw){
					$name=prepare(ucfirst($rw["account"])); $rid=$rw['id']; $bal=0;
					if($rw["tree"]){
						$trs.= "<tr style='background:#e6e6fa;font-weight:bold;color:#191970'><td>$name</td>"; 
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
									$trs.= "<tr><td style='padding-left:40px;$css'><i class='bi-circle-fill' style='font-size:12px;color:grey'></i> $name3</td>";
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
								$trs.= "<tr><td style='padding-left:20px;$css'><i class='bi-circle' style='font-size:12px;color:grey'></i> $name2</td>";
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
						$trs.= "<tr style='font-weight:bold'><td style='border-top:2px solid #ccc;'>Total $name</td>";
						foreach($tot1 as $mn=>$tot){ $trs.="<td style='text-align:center;border-top:2px solid #ccc;'>".number_format($tot)."</td>"; }
						$trs.= "</tr><tr><td colspan='$cls'></td></tr>";
					}
					else{
						$trs.= "<tr><td style='$css'><i class='bi-circle' style='font-size:12px;color:grey'></i> $name</td>"; 
						for($i=$monstart; $i>=1; $i--){
							$mn = strtotime("$yr-".$mls[$i]);
							if(isset($bals[$rid])){ $bal=(isset($bals[$rid][$mn])) ? $bals[$rid][$mn]:0; }else{ $bal=0; }
							if(isset($tots[$mn])){ $tots[$mn]+=$bal; }else{ $tots[$mn]=$bal; }
							$trs.= "<td style='text-align:center;$css'>".number_format($bal)."</td>"; 
						}
						$trs.= "</tr>";
					}
				}
				
				$csl = ($col!="asset") ? "border-top:2px solid #2f4f4f;border-bottom:2px solid #2f4f4f;":""; $bld="font-weight:bold;";
				$trs.= ($col=="asset") ? "<tr style='color:#fff;background:#4682b4;$bld'><td>Total $txt</td>":"<tr style='color:#191970;$bld'><td>Total $txt</td>"; 
				foreach($tots as $mn=>$tot){
					$trs.="<td style='text-align:center;$csl'>".number_format($tot)."</td>"; 
					if($col!="asset"){ if(isset($totlbs[$mn])){ $totlbs[$mn]+=$tot; }else{ $totlbs[$mn]=$tot; }}
				}
				$trs.= "</tr><tr><td colspan='$cls'></td></tr>";
			}
			
			$trs.= "<tr style='color:#fff;background:#4682b4;font-weight:bold'><td>Total Equity & Liabilities</td>";
			for($mn=$monstart; $mn>=1; $mn--){
				$mon = strtotime("$yr-".$mls[$mn]); $ths.="<td style='text-align:center'>$mls[$mn] $yr</td>"; 
				$trs.="<td style='text-align:center;'>".number_format($totlbs[$mon])."</td>"; 
			}
			
			$trs.= "</tr>";
			$mxw = (count($totlbs)<=4) ? "950px":"1400px";
			$mxw = (count($totlbs)>4 && count($totlbs)<8) ? "1100px":$mxw;
			$mxd = (count($totlbs)<=4) ? "700px":"1400px";
			
			$data = "<div style='max-width:$mxd;margin:0 auto;overflow:auto'>
				<h2 style='color:#191970;font-weight:bold;font-size:21px;'>$yr Balance Sheet</h2>
				<div style='width:100%;overflow:auto'>
					<table style='width:100%;font-size:14px;min-width:700px;' cellpadding='5' class=''>
						<tr style='background:#2f4f4f;font-weight:bold;color:#fff;font-size:13px'><td style='min-width:100px'>Account</td>$ths</tr> $trs
					</table>
				</div>
			</div>";
			savelog($sid,"Viewed $yr Balance Sheet");
		}
		
		$btxt = ($vtp=="income") ? "<i class='fa fa-cloud-upload'></i> Export":"<i class='bi-printer'></i> Print";
		$print = ($vtp=="income") ? "popupload('accounts/reports.php?incexp=$mon:$yr$lds')":"printdoc('reports.php?vtp=$vtp&mn=$mon&yr=$yr$lds','report')";
		
		echo "<div class='container cardv' style='max-width:$mxw;min-height:300px;overflow:auto'>
			<div class='row' style='margin:0px;'>
				<div class='col-12 col-md-6 col-lg-6 mt-3'><h3 style='color:#191970;font-size:22px;'>$backbtn Accruals & Reports</h3></div>
				<div class='col-12 col-md-6 col-lg-6'>  
					<button class='bts' style='font-size:14px;float:right;margin-left:5px;padding:2px;margin-top:10px;' onclick=\"$print\">$btxt</button>$addins
					<select style='float:right;background:#F8F8FF;border:1px solid #dcdcdc;padding:4px;cursor:pointer;width:140px;color:#4682b4;font-size:15px;margin-top:10px' 
					onchange=\"loadpage('accounts/reports.php?manage&report='+this.value)\">$opts</select>
				</div>
			</div><hr> $data
		</div>";
	}
	
	# income export options
	if(isset($_GET["incexp"])){
		$def = explode(":",trim($_GET["incexp"]));
		$brn = (isset($_GET["brn"])) ? trim($_GET["brn"]):"";
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='font-size:23px;color:#191970;text-align:center'>Income Export Options</h3><br>
			<p>Presentation Style<br><select style='width:100%' id='dst'><option value='1'>With detailed expenses</option><option value='0'>Compressed expenses</option></select></p><br>
			<p><button class='btnn' style='font-family:signika negative' onclick=\"incomexport('xls')\"><i class='bi-file-earmark-excel'></i> Generate Excel</button>
			<button class='btnn' style='font-family:signika negative;float:right;background:#D2691E' onclick=\"incomexport('pdf')\"><i class='bi-file-pdf'></i> Generate PDF</button></p><br>
		</div>
		<script>
			function incomexport(tp){
				genreport('reports.php?vtp=income&mn=$def[0]&yr=$def[1]&br=$brn&pst='+$('#dst').val(),tp); closepop();
			}
		</script>";
	}
	
	#balance sheet settings
	if(isset($_GET['setbalsheet'])){
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='balsheetaccs'");
		$accs = ($qri) ? json_decode($qri[0]['value'],1):[]; $trs="";
		
		$bls = array(["fixed asset","current asset","other asset"],["current liability","long term liability"],["equity"]);
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE (`type`='asset' OR `type`='liability' OR `type`='equity') AND `id`>5");
		foreach($res as $row){
			$acc=prepare(ucfirst($row['account'])); $rid=$row['id']; $cnd=(array_key_exists($rid,$accs)) ? "checked":"";
			$tps = array("asset","liability","equity"); $tp=array_flip($tps)[$row['type']]; $sel=$opts="";
			
			foreach($bls[$tp] as $bl){
				if(array_key_exists($rid,$accs)){ $sel=($bl==$accs[$rid]) ? "selected":""; }
				$opts.="<option value='$bl' $sel>".ucwords($bl)."</option>";
			}
			
			$trs.="<tr><td><input type='checkbox' name='bsacc[]' value='$rid' $cnd> &nbsp; $acc</checkbox></td>
			<td><select name='bstype[$rid]' style='padding:4px;width:150px;float:right'>$opts</select></td></tr>";
		}
		
		echo "<div style='max-width:500px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Balance Sheet Accounts</h3><br>
			<form method='post' id='bfom' onsubmit='savebalset(event)'>
			<table cellpadding='4' style='width:min-width:60%;margin:0 auto'>
				<tr style='font-weight:bold'><td>Account</td><td>Balancesheet Account</td></tr> $trs
				<tr><td colspan='2' style='text-align:right'><br><button class='btnn'>Save</button></td></tr>
			</table><br>
			</form>
		</div>";
		
		savelog($sid,"Accessed Balance Sheet Accounts form");
	}
	
	#view expense sub accounts from income statement
	if(isset($_GET['viewexpsubs'])){
		$ids = explode(":",trim($_GET['viewexpsubs']));
		$mon = trim($_GET['mon']);
		$brn = trim($_GET['br']);
		$yr = trim($_GET['yr']);
		
		$cond = ($mon) ? "AND `month`='$mon'":"";
		$cond.= ($brn) ? " AND `branch`='$brn'":"";
		$title = ($mon) ? date("F Y",$mon):$yr;
		
		$accounts=[]; $trs="";
		$res = $db->query(3,"SELECT *FROM `accounts$cid`");
		foreach($res as $row){
			$accounts[$row['id']]=prepare(ucfirst($row['account']));
		}
		
		foreach($ids as $id){
			$qry = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `transactions$cid` WHERE `year`='$yr' AND `type`='debit' AND `account`='$id' $cond");
			$amnt = ($qry) ? $qry[0]['tamnt']:0;
			$trs.="<tr><td>".$accounts[$id]."</td><td style='text-align:right'>".number_format($amnt)."</td></tr>";
		}
		
		echo "<h3 style='color:#191970;font-size:22px;text-align:center'>$title</h3>
		<table style='width:100%;max-width:500px;margin:0 auto' class='table-bordered table-striped' cellpadding='5'>
			<tr style='font-weight:bold;color:#191970'><td>Account</td><td>Amount</td></tr>$trs
		</table>";
	}
	
	ob_end_flush();
?>

<script>
	
	function savebalset(e){
		e.preventDefault();
		if(!checkboxes("bsacc[]")){ toast("You have to select atleast 1 account!"); }
		else{
			if(confirm("Update balance sheet accounts?")){
				var data=$("#bfom").serialize();
				$.ajax({
					method:"post",url:path()+"dbsave/accounting.php",data:data,
					beforeSend:function(){ progress("Processing...please wait"); },
					complete:function(){progress();}
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						toast("Updated successfully!"); closepop(); window.location.reload(); 
					}
					else{ alert(res); }
				});
			}
		}
	}
	
	function confmatch(id,proj){
		if(confirm("Confirm Transaction exists on both statements?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{confmatch:proj+":"+id},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					$("#tr"+id).css({"background":"#c1f0c1","color":"green"}); $("#trl"+id).html("");
				}
				else{ toast(res); }
			});
		}
	}
	
	function savereconn(e){
		e.preventDefault();
		if(confirm("Proceed to create Reconciliation Project?")){
			var data = $("#rfom").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					toast("Created successfully!"); closepop(); 
					loadpage("accounts/reports.php?reconc="+res.trim().split(":")[1]); 
				}
				else{ alert(res); }
			});
		}
	}

</script>