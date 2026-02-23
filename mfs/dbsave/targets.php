<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# update targets
	if(isset($_POST['setarget'])){
		$col = trim($_POST['setarget']);
		$val = intval($_POST['tval']);
		$ttl = trim($_POST['title']);
		$stid = trim($_POST['stid']);
		$mon = strtotime(date("Y-M"));
		$name = staffInfo($stid)["name"];
		
		if($val<0){ echo "Error! Targets should be >=0"; }
		else{
			setTarget($stid,$mon,$col,$val);
			savelog($sid,"Updated $ttl Targets for $name to $val for month of ".date("F Y",$mon));
			echo "success";
		}
	}

	ob_end_flush();
?>