<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(115);
    
	if(!isset($_GET['syschron'])){ exit(); }
	require "functions.php";
	
	$site = $_SERVER['HTTP_HOST'];
	request("https://$site/c2b/automator.php",["autosave"=>1]);
    
?>