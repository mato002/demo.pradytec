<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
	
    require "functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_POST['ckupdate'])){
		$tdy = strtotime(date("Y-M-d"));
		$tmon = strtotime(date("Y-M"));
		$lmon = strtotime(date("Y-M",strtotime("last month")));
		$now = time();
		
		if(intval(date("Hi"))>=6 && intval(date("Hi"))<=10){
			$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs":$_SERVER['HTTP_HOST'];
			$http = ($_SERVER['HTTP_HOST']=="localhost") ? "http://":"https://";
			request($http.$url."/core/chron1200.php",["updateday"=>1]);
		}
		
		# check failed queries
		insertSQLite("tempinfo","CREATE TABLE IF NOT EXISTS fallback (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,db INTEGER,locks TEXT,query TEXT)");
		if(is_array($res=fetchSQLite("tempinfo","SELECT *FROM `fallback`"))){
			foreach($res as $row){
				$dbn=$row['db']; $rid=$row['id']; $lock=$row['locks']; $qry=base64_decode($row['query']);
				if($lock=="none"){
					if($db->execute($dbn,$qry)){ insertSQLite("tempinfo","DELETE FROM `fallback` WHERE `id`='$rid'"); }
				}
				else{
					if($con = $db->mysqlcon($dbn)){
						$parts = explode(":",$lock); $tbl=$parts[0]; $col=$parts[1]; $val=$parts[2];
						$con->autocommit(FALSE);
						$con->query("BEGIN");
						$con->query("SELECT * FROM `$tbl` WHERE `$col`='$val' FOR UPDATE");
						$con->query($query);
						$con->commit(); $con->close(); 
						insertSQLite("tempinfo","DELETE FROM `fallback` WHERE `id`='$rid'");
					}
				}
			}
		}
		
		# check leaves
		if($db->istable(2,"org".$cid."_leaves")){
			$db->execute(2,"UPDATE `org".$cid."_leaves` SET `status`='11' WHERE `status`<10 AND `leave_end`<$now");
			$res = $db->query(2,"SELECT *FROM `org".$cid."_leaves` WHERE `status`>400 AND `leave_end`<$now");
			if($res){
				foreach($res as $row){
					$stid=$row['staff']; $rid=$row['id'];
					$db->execute(2,"UPDATE `org".$cid."_leaves` SET `status`='400' WHERE `status`<$now AND `id`='$rid'");
					$db->execute(2,"UPDATE `org".$cid."_staff` SET `status`='0' WHERE `id`='$stid' AND `status`='2'");
				}
			}
		}
		
		# monthly updates
		if(intval(date("d"))==1){
			$sett=$queries=[]; $cond=0;
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'"); 
			if($qri){ foreach($qri as $row){ $sett[$row['setting']]=prepare($row['value']); } }
			$mfro = (isset($sett['lastmonth'])) ? $sett['lastmonth']:0;
			
			if($mfro<$lmon){
				# leaves
				if(intval(date("dm"))==101){
					$db->execute(2,"UPDATE `org".$cid."_staff` SET `leaves`='0'");
				}
				else{
					$lvdys = (isset($sett['leavedays'])) ? $sett['leavedays']:24;
					$prob = (isset($sett['probation'])) ? $sett['probation']:2;
					$add = round($lvdys*86400/12);
					
					$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `status`='1'");
					foreach($res as $row){
						$day=strtotime($row['entry_date']); $fro=strtotime(date("Y-M-d",strtotime("-$prob month"))); $stid=$row['id'];
						if($day<$fro){
							$db->execute(2,"UPDATE `org".$cid."_staff` SET `leaves`=(leaves+$add) WHERE `id`='$stid'");
						}
					}
				}
				
				# petty cash
				$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `month`='$lmon'");
				if($res){
					foreach($res as $row){
						$close=$row['closing']; $bid=$row['branch'];
						$db->execute(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bid','$tmon','$close','$close')");
					}
				}
				
				# account balances
				$cols = $db->tableFields(3,"monthly_balances$cid");
				if(!in_array("openbal",$cols)){ $db->execute(3,"ALTER TABLE `monthly_balances$cid` ADD `openbal` INT NOT NULL AFTER `account`"); }
				$qry = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' OR NOT `balance`='0'");
				foreach($qry as $row){
					$rid=$row['id']; $bal=$row['balance']; backdate($rid,$bal,$lmon);
				}
				
				$db->execute(3,"UPDATE `accounts$cid` SET `balance`='0' WHERE `type`='expense' OR `type`='income'"); 
				foreach(array(15) as $acc){ 
					if($acc==15){
						$res = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `deductions$cid` WHERE `month`='$tmon' AND `status`>200");
						$bal = ($res) ? $res[0]['tamnt']:0;
					}
					else{ $bal=0; }
					$db->execute(3,"UPDATE `accounts$cid` SET `balance`='$bal' WHERE `id`='$acc'"); 
				}
				
				# loanbook
				if($db->istable(2,"org$cid"."_loans")){
					if(!$db->istable(2,"loanbooks$cid")){
						$db->createTbl(2,"loanbooks$cid",["branch"=>"INT","officer"=>"INT","principle"=>"INT","charges"=>"INT","penalties"=>"INT","month"=>"INT"]);
					}
					
					$qri = $db->query(2,"SELECT `branch`,`loan_officer`,SUM((CASE WHEN (amount>paid) THEN amount-paid ELSE paid-amount END)) AS prc,SUM(balance) AS tbal,
					SUM(penalty) AS tpen FROM `org$cid"."_loans` WHERE `balance`>0 GROUP BY `loan_officer`");
					if($qri){
						foreach($qri as $row){
							$bid=$row['branch']; $lof=$row['loan_officer']; $prc=$row['prc']; $pen=$row['tpen']; $chj=$row['tbal']-$prc; 
							$db->execute(2,"INSERT INTO `loanbooks$cid` VALUES(NULL,'$bid','$lof','$prc','$chj','$pen','$lmon')");
						}
					}
				}
				
				$cond=1;
			}
			
			$query = ($mfro) ? "UPDATE `settings` SET `value`='$lmon' WHERE `setting`='lastmonth' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','lastmonth','$lmon')";
			if($cond){ 
				$db->execute(1,$query); 
				$db->execute(3,"UPDATE `advances$cid` SET `status`='11' WHERE `status`<10 AND `month`<$tmon");
				$db->execute(3,"UPDATE `deductions$cid` SET `status`='11' WHERE `status`<10 AND `month`<$tmon");
			}
		}
	}
	
	if(isset($_POST['mailframe'])){
		$data = trim($_POST['mailframe']);
		echo frame_email($data);
	}
	
	if(isset($_POST['sendmails'])){
		echo mailto($_POST['sendmails'],$_POST['subs'],$_POST['mssgs'],$_POST['docs']);
	}

?>