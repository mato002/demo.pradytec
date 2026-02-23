<?php
    
    ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
    
    include "functions.php";
	$db = new DBO();
    
    # system cron
	if(!isset($_GET['ucron'])){
	    $res = $db->query(1,"SELECT *FROM `clients` WHERE `status`='0'");
    	if($res){
        	foreach($res as $row){
        	    $url = $row['url'];
        		request("$url/c2b/automator.php",["autosave"=>1]);
        	}
    	}
	}
	
	# mail cron & backup
	if(isset($_GET['mcron'])){
	    $qri = $db->query(1,"SELECT *FROM `clients` WHERE `status`='0'");
	    foreach($qri as $roq){
	        $cid = $roq['id']; $loc=$roq['url'];
    		$emails=$subs=$messages=$files=[]; $hr=intval(date("H"));
    		
    		if(in_array($hr,[4,10,16,22]) && intval(date("i")<=10)){
    		    $sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='lastupdate'");
    		    $last = ($sql) ? $sql[0]['value']:0; $now=time();
    		    if(($now-$last)>3600){
    		        $query = ($last) ? "UPDATE `settings` SET `value`='$now' WHERE `setting`='lastupdate' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','lastupdate','$now')";
    		        $db->insert(1,$query); request("$url/core/backup.php?backup",["update"=>1]);
    		    }
    		}
            
            if($db->istable(2,"mailist$cid")){
        		$res = $db->query(2,"SELECT *FROM `mailist$cid` WHERE `status`='0' LIMIT 5");
        		if($res){
        			foreach($res as $row){
        				$email=$row['email']; $subject=prepare(ucfirst($row['subject'])); $file=$row['file']; $tym=$row['time'];
        				$mssg=request("$loc/core/syschron.php",["mailframe"=>nl2br(prepare($row['body']))]); $doc = ($file) ? "$loc$file":null;
        				$emails[]=$email; $subs[]=$subject; $messages[]=$mssg; $files[]=$doc;
        				$db->insert(2,"UPDATE `mailist$cid` SET `status`='1' WHERE `email`='$email' AND `time`='$tym'");
        			}
        			
        			$req = request("$loc/core/syschron.php",["sendmails"=>$emails,"subs"=>$subs,"mssgs"=>$messages,"docs"=>$files]);
        	    	$db->insert(2,"DELETE FROM `mailist$cid` WHERE `status`='1'");
        		}
            }
	    }
	}
	

?>