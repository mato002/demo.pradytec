<?php
	session_start();
	include "../core/functions.php";
	
	if(isset($_SESSION['myacc'])){
		$sid = $_SESSION['myacc']; $ipv=$_SERVER['REMOTE_ADDR'];
		insertSQLite("sitesess","CREATE TABLE IF NOT EXISTS logins (user INTEGER PRIMARY KEY NOT NULL,device TEXT,ipv4 TEXT,lastime INTEGER)");
		insertSQLite("sitesess","REPLACE INTO `logins` VALUES('$sid','none','$ipv','0')");
		unset($_SESSION['myacc']); unset($_SESSION['logas']);
	}
	
	header("location:../");

?>