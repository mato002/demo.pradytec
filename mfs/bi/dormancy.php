<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_GET['view'])){
		$vtp = trim($_GET['view']);
		$ftp = ($vtp) ? $vtp:0;
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$ofid = (isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		
		$tdy = strtotime(date("Y-M-d")); 
		$jana = strtotime(date("Y-M-d",$tdy-5));
		$nxtd = $tdy+(86400*30); $lstd = $jana-(86400*30);
		$ltbl = "org$cid"."_loans"; $ctbl = "org$cid"."_clients";
		
		$me = staffInfo($sid); $access = $me['access_level'];
		$bran = (!in_array($me['access_level'],["hq","region"])) ? $me['branch']:$bran;
		$ofid = ($me['access_level']=="portfolio") ? $sid:$ofid;
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		
		$perpage = 30; $lim=getLimit($page,$perpage); $cnf=json_decode($me["config"],1);
		$range = array(0=>[30,0],2=>[60,30],3=>[90,60],4=>[120,90],5=>[1000,120]);
		$fday = $jana-($range[$ftp][0]*86400); $dto=$jana-($range[$ftp][1]*86400);
		
		$cond = $fetch = ($ftp==1) ? "ln.expiry BETWEEN $tdy AND $nxtd AND ln.balance>0 AND ct.status='1'":"ln.expiry BETWEEN $fday AND $dto AND ct.status='0'";
		$cond.= ($bran) ? " AND ln.branch='$bran'":"";
		$cond.= ($access=="region" && isset($cnf["region"]) && !$bran) ? " AND ".setRegion($cnf["region"],"ln.branch"):"";
		$cond.= ($ofid) ? " AND ln.loan_officer='$ofid'":"";
		$order = ($ftp==1) ? "ASC":"DESC"; 
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staffs[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		$qrs = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($qrs){
			foreach($qrs as $row){
				$prods[$row['id']]=prepare(ucwords($row['product']));
			}
		}
		
		$no=($perpage*$page)-$perpage; $trs=$brans=$offs=""; $totals=0;
		if($db->istable(2,$ltbl)){
			$qri = $db->query(2,"SELECT ln.* FROM `$ltbl` AS ln INNER JOIN `$ctbl` AS ct ON ln.client_idno=ct.idno WHERE $cond ORDER BY ln.expiry $order $lim");
			if($qri){
				foreach($qri as $row){
					$name=prepare(ucwords($row['client'])); $prod=$prods[$row['loan_product']]; $fon=$row['phone']; $bname=$bnames[$row['branch']]; $no++;
					$ofname=$staffs[$row['loan_officer']]; $amnt=number_format($row['amount']); $dur=$row['duration']; $exp=$row['expiry']; 
					$bal=number_format($row['balance']); $dy=date("d-m-Y",$exp); $dys=($ftp==1) ? floor(($exp-$tdy)/86400):floor(($jana-$exp)/86400);
					
					$tdx = ($dys==1) ? "1 day":"$dys days"; $tdx.=($ftp==1) ? " to":" ago"; $tbal=($ftp==1) ? $bal:"Completed";
					$oftd = ($access=="portfolio") ? "":"<td>$ofname<br><span style='color:grey;font-size:14px'>$bname branch</span></td>";
					
					$trs.="<tr valign='top'><td>$no</td><td>$name<br><span style='color:grey;font-size:14px'>0$fon</span></td><td>$amnt</td>
					<td>$prod<br><span style='color:grey;font-size:14px'>$dur days</span></td>$oftd <td>$dy<br><span style='color:grey;font-size:14px'>$tdx</span></td>
					<td>$tbal</td></tr>";
				}
			}
			
			$qry = $db->query(2,"SELECT COUNT(*) AS total FROM `$ltbl` AS ln INNER JOIN `$ctbl` AS ct ON ln.client_idno=ct.idno WHERE $cond");
			$totals = ($qry) ? $qry[0]['total']:0;
		}
		
		if(in_array($access,["hq","region"])){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:140px;font-size:15px' onchange=\"loadpage('bi/dormancy.php?view=$ftp&bran='+this.value.trim())\">$brns</select>";
		}
		
		if($me['access_level']=="branch" or $bran){
			$brn = ($me['access_level']=="hq") ? 1:"ln.branch='".$me['branch']."'";
			$res = $db->query(2,"SELECT DISTINCT ln.loan_officer FROM `$ltbl` AS ln INNER JOIN `$ctbl` AS ct ON ln.client_idno=ct.idno WHERE $brn AND $fetch");
			if($res){
				$opts = "<option value='0'>-- Loan Officer --</option>";
				foreach($res as $row){
					$off=$row['loan_officer']; $cnd=($off==$ofid) ? "selected":"";
					$opts.="<option value='$off' $cnd>".$staffs[$off]."</option>";
				}
				
				$offs = "<select style='width:150px' onchange=\"loadpage('bi/dormancy.php?view=$ftp&bran=$bran&ofid='+this.value.trim())\">$opts</select>";
			}
		}
		
		$def = array("0-30 Days","Next 30 Days","31-60 Days","61-90 Days","91-120 Days","Above 120 Days"); $opts="";
		foreach($def as $key=>$val){
			$cnd=($ftp==$key) ? "selected":"";
			$opts.="<option value='$key' $cnd>$val</option>";
		}
		
		$prnt = ($totals) ? genrepDiv("dormancy.php?src=$ftp&br=$bran&stid=$ofid",'right'):"";
		$title = ($ftp==1) ? $def[$ftp]." to Dormant clients":$def[$ftp]." dormant clients"; 
		$tdh = ($access=="portfolio") ? "":"<td>Loan Officer</td>";
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:2px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title</h3>
				<div style='width:100%;overflow:auto'>
					<table class='table-striped table-bordered' style='width:100%;font-size:15px;margin-top:15px;min-width:600px' cellpadding='5'>
						<caption style='caption-side:top'>
							<p style='margin:0px'> <select style='width:140px' onchange=\"loadpage('bi/dormancy.php?view='+cleanstr(this.value))\">$opts</select>
							$brans $offs $prnt</p>
						</caption>
						<tr style='background:#e6e6fa;color:#191970;font-weight:bold;font-size:14px;' valign='top'>
						<td colspan='2'>Client</td><td>Loan</td><td>Product</td>$tdh<td>Maturity</td><td>Balance</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"bi/dormancy.php?view=$ftp&bran=$bran&ofid=$ofid")."
		</div>";
		
		savelog($sid,"Viewed $title");
	}

	@ob_end_flush();
?>