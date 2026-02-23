<?php
	session_start();
	ob_start();
	if(!isset($_POST['getnot'])){ echo "0:0:0:0:0"; exit(); }
	$sid = trim($_POST['getnot']);
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	$me = staffInfo($sid); 
	$access = $me['access_level'];
	$ptbl = "org".$cid."_payments";
	$ctbl = "org".$cid."_clients";
	$from = strtotime("2021-06-12"); $res=""; 
	$ftm = time()-(8400*2);
	
	$qri = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'");
	if(!$qri){ echo "0:0:0:0:0:0"; exit(); }
	if($qri[0]['status']>0){ echo "0:0:0:0:0:0"; exit(); }
	
	foreach(array($ctbl,"org".$cid."_loantemplates","org".$cid."_loans") as $tbl){
		if(!$db->istable(2,$tbl)){
			$sess = (isset($_SESSION['myacc']) && $me['status']==0) ? substr(hexdec($_SESSION['myacc']),6):0;
			$res = "0:0:0:$sess:0:0"; break; 
		}
	}
	
	if($res){ echo $res; exit(); }
	if(intval(date("His"))>=235950){
		session_destroy();
		echo "0:0:0:0:0:0"; exit();
	}
	
	$chk = $db->query(1,"SELECT `value` FROM `settings` WHERE `setting`='systime' AND `client`='$cid'");
	if($chk){
		$sets = json_decode($chk[0]["value"],1);
		if($sets["restrict"]){
			$fro = intval(str_replace(":","",$sets["from"])); $dto=intval(str_replace(":","",$sets["upto"]));
			if(intval(date("Hi"))>=$fro && intval(date("Hi"))<$dto){ }
			else{
				if(!in_array($sid,[1,2])){ echo "0:0:0:0:0:0"; exit(); }
			}
		}
	}
	
	if($access=="portfolio"){
		$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` AS ctb INNER JOIN `$ptbl` AS ptb ON (ptb.account=ctb.idno OR ptb.phone=ctb.contact) 
		WHERE ctb.loan_officer='$sid' AND ptb.status='0'");
		$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` AS ctb INNER JOIN `$ptbl` AS ptb ON (ptb.account=ctb.idno OR ptb.phone=ctb.contact) 
		WHERE ctb.loan_officer='$sid' AND ptb.status BETWEEN 1 AND 10");
		$res3 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_loantemplates` AS lt INNER JOIN $ctbl AS ctb ON ctb.idno=lt.client_idno
		WHERE lt.status>10 AND lt.loan='0' AND lt.time>$from AND lt.loan_officer='$sid'");
		$npays = ($res) ? $res[0]['total']:0; $upays = ($res2) ? $res2[0]['total']:0; $disb = ($res3) ? $res3[0]['total']:0;
	}
	elseif($access=="branch"){
		$bran = $me['branch']; $vwc=(defined("USERS_VIEW_ALLPAYS")) ? USERS_VIEW_ALLPAYS:0;
		$cond = ($vwc) ? "":"AND `paybill`='".getpaybill($bran)."'";
		$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` WHERE `status`='0' $cond");
		$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` WHERE `status` BETWEEN 1 AND 10 $cond");
		$res3 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_loantemplates` WHERE `status`>10 AND `loan`='0' AND `branch`='$bran' AND time>$from");
		$npays = ($res) ? $res[0]['total']:0; $upays = ($res2) ? $res2[0]['total']:0; $disb = ($res3) ? $res3[0]['total']:0;
	}
	else{
		$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` WHERE `status`='0'");
		$res2 = $db->query(2,"SELECT COUNT(*) AS total FROM `$ptbl` WHERE status BETWEEN 1 AND 10");
		$res3 = $db->query(2,"SELECT COUNT(*) AS total FROM `org".$cid."_loantemplates` WHERE `status`>10 AND `loan`='0' AND time>$from");
		$npays = ($res) ? $res[0]['total']:0; $upays = ($res2) ? $res2[0]['total']:0; $disb = ($res3) ? $res3[0]['total']:0;
	}
	
	insertSQLite("sitesess","CREATE TABLE IF NOT EXISTS logins (user INTEGER PRIMARY KEY NOT NULL,device TEXT,ipv4 TEXT,lastime INTEGER)");
	insertSQLite("sysnots","CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,user INTEGER,task TEXT,nav TEXT,status INTEGER,time INTEGER)");
	insertSQLite("sysnots","DELETE FROM `tasks` WHERE `time`<$ftm");
	
	$sess = (isset($_SESSION['myacc']) && $me['status']==0) ? intval(substr(hexdec($_SESSION['myacc']),6)):0; $nots=0;
	$qri = $db->query(2,"SELECT COUNT(*) AS total FROM `tickets$cid` WHERE (`receiver`='$sid'  OR `receiver`='0') AND (`reply`='0' OR `reply` LIKE '888%')");
	$tickets = ($qri) ? $qri[0]['total']:0; $skip=(defined("STOP_SQLITE")) ? STOP_SQLITE:[];
	
	if(!in_array(intval(date("H")),$skip)){
		$tym = ($sess>0) ? time():0; $browser = ($sess>0) ? getagent():"none";
		insertSQLite("sitesess","UPDATE `logins` SET `device`='$browser',`lastime`='$tym' WHERE `user`='$sid'");
		$chk = fetchSqlite("sysnots","SELECT COUNT(*) AS tot FROM `tasks` WHERE `user`='$sid' AND `status`='0'");
		$nots = ($chk) ? intval($chk[0]["tot"]):0;
	}
	
	echo "$npays:$upays:$disb:$sess:$tickets:$nots";
	exit();

?>