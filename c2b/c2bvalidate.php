<?php 

	header("Content-Type:application/json"); 
	
	if(!isset($_GET["ref"])){
		echo "Technical error"; exit();
	}
	
	if($_GET["ref"]!='Prd*20:25!'){
		echo "Invalid authorization"; exit();
	}

	echo '{"ResultCode":0, "ResultDesc":"Success", "ThirdPartyTransID": 0}';
	
?>