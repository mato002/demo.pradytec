<?php

	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(115);
	
    require "functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_GET['ckmails'])){
		$loc = ($_SERVER['HTTP_HOST']=="localhost") ? "http://$url":"https://$url";
		$emails=$subs=$messages=$files=[];

		$res = $db->query(2,"SELECT *FROM `mailist$cid` WHERE `status`='0' LIMIT 5");
		if($res){
			foreach($res as $row){
				$email=$row['email']; $subject=prepare(ucfirst($row['subject'])); $file=$row['file']; $tym=$row['time'];
				$mssg=frame_email(nl2br(prepare($row['body']))); $doc = ($file) ? "$loc$file":null;
				$emails[]=$email; $subs[]=$subject; $messages[]=$mssg; $files[]=$doc;
				$db->insert(2,"UPDATE `mailist$cid` SET `status`='1' WHERE `email`='$email' AND `time`='$tym'");
			}
			
			echo mailto($emails,$subs,$messages,$files);
	    	$db->insert(2,"DELETE FROM `mailist$cid` WHERE `status`='1'");
		}
	}

?>