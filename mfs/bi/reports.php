<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	
	# loan sizes
	if(isset($_GET["lsizes"])){
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		
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
			$trs.= "<tr><td style='float:left'>$rname</td><td>".fnum($tot)."</td><td>".fnum($tbal)."</td><td>$perc%</td><td>".fnum($arrt)."</td><td>".fnum($arrs)."</td></tr>";
		}
		
		$trs.= "<tr style='background:#e6e6fa;color:#191970;font-weight:bold'><td></td><td>".fnum($tots)."</td><td>".fnum($tbals)."</td><td>100%</td>
		<td>".fnum($tns)."</td><td>".fnum($tarr)."</td></tr>";
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):"";
			$res = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE `amount`>0 $add");
			if($res){
				foreach($res as $row){
					$rid=$row['branch']; $cnd=($bran==$rid) ? "selected":"";
					$brn.= "<option value='$rid' $cnd>".$bnames[$rid]."</option>";
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
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('bi/reports.php?lsizes&reg='+this.value)\">$rgs</select>&nbsp;";
				}
			}
			$brans.= "<select style='width:150px;font-size:15px' onchange=\"loadpage('bi/reports.php?lsizes&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$prnt = ($trs) ? genrepDiv("reports.php?vtp=lsizes&br=$bran&rgn=$rgn",'right'):"";
		echo "<div class='cardv' style='max-width:1200px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;max-width:1200px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Loan Size Distribution</h3><hr>
				<div style='overflow:auto'>
					<table class='table-striped' style='width:100%;font-size:15px;text-align:center;min-width:500px;' cellpadding='5'> 
						<caption style='caption-side:top;padding-top:0px'> $prnt $brans</caption>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:14px;' valign='top'><td style='float:left'>Loan Size</td>
						<td>No. of Accounts</td><td>Loan Balances</td><td>Composition</td><td>Non-Performing Accounts</td><td>Non-Performing Amounts</td></tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed Loan size Distributions");
	}


	ob_end_flush();
?>