<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	insertSqlite("photos","CREATE TABLE IF NOT EXISTS images (image TEXT UNIQUE NOT NULL,data BLOB)");
	
	# upload image 
	if(isset($_FILES['photo'])){
		$to = trim($_POST['cto']);
		$ref = trim($_POST['ref']);
		$mssg = clean($_POST['mssg']);
		$tmp = $_FILES['photo']['tmp_name'];
		$ext = @strtolower(array_pop(explode(".",strtolower($_FILES['photo']['name']))));
		$get = array("png","jpg","jpeg","gif"); $tym = time();
		
		if(@getimagesize($tmp)){
			if(in_array($ext,$get)){
				$img = "Chat_".date("Ymd_His").".$ext";
				$sub = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `ref`='$ref'")[0]['subject'];
				
				if(move_uploaded_file($tmp,"../../docs/img/$img")){
					if($db->insert(2,"INSERT INTO `tickets$cid` VALUES(NULL,'$sid','$to','$sub','sPIC:$img~>$mssg','$ref','$tym','0')")){
						$db->insert(2,"UPDATE `tickets$cid` SET `reply`='$tym' WHERE `ref`='$ref' AND `receiver`='$sid' AND `reply`<=200");
						crop("../../docs/img/$img",1000,600,"../../docs/img/$img");
						insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../../docs/img/$img"))."')");
						unlink("../../docs/img/$img");
						echo "success";
					}
					else{ echo "Failed to save photo"; unlink("../../docs/img/$img"); }
				}
				else{ echo "Failed: Unknown Error occured"; }
			}
			else{ echo "Failed: Image extension $ext is not supported"; }
		}
		else{ echo "Invalid Image! Please select a valid photo"; }
	}
	
	# save chat
	if(isset($_POST['cto']) && !isset($_FILES['photo'])){
		$to = trim($_POST['cto']);
		$ref = trim($_POST['ref']);
		$rep = trim($_POST['repto']);
		$mssg = clean($_POST['mssg']);
		
		$rst = ($rep) ? "888$rep":time(); $tym=time();
		$sub = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `ref`='$ref'")[0]['subject'];
		
		if($db->insert(2,"INSERT INTO `tickets$cid` VALUES(NULL,'$sid','$to','$sub','$mssg','$ref','$tym','$rst')")){
			if($rep){ $db->insert(2,"UPDATE `tickets$cid` SET `reply`='$tym' WHERE `id`='$rep'"); }
			else{ $db->insert(2,"UPDATE `tickets$cid` SET `reply`='$tym' WHERE `ref`='$ref' AND `receiver`='$sid' AND `reply`<=200"); }
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# create new ticket
	if(isset($_POST['tsub'])){
		$sub = clean($_POST['tsub']);
		$to = clean($_POST['tto']);
		$mssg = clean($_POST['tmssg']); 
		$ref = chatref([$sid,$to],prepare($sub));
		$tym = time();
		
		if($db->insert(2,"INSERT INTO `tickets$cid` VALUES(NULL,'$sid','$to','$sub','$mssg','$ref','$tym','0')")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}


	@ob_end_flush();
?>