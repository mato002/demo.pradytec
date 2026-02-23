<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	#save/update region 
	if(isset($_POST['regid'])){
		$id = trim($_POST['regid']);
		$name = clean($_POST['rname']);
		$brans = json_encode($_POST['rbrans'],1);
		
		$chk1=$db->query(1,"SELECT *FROM `regions` WHERE `name`='$name' AND `client`='$cid' AND NOT `id`='$id'");
		if($chk1){
			echo "Failed: Region ".prepare(ucwords($name))." is already in the system";
		}
		else{
			$sql = ($id) ? "UPDATE `regions` SET `name`='$name',`branches`='$brans' WHERE `id`='$id'":"INSERT INTO `regions` VALUES(NULL,'$cid','$name','$brans')";
			if($db->insert(1,$sql)){
				$txt = ($id) ? "Updated region to $name":"Created region $name"; savelog($sid,$txt); 
				echo "success";
			}
			else{ echo "Failed to complete your request at the moment, try again later"; }
		}
	}
	
	# delete branch
	if(isset($_POST['delregion'])){
		$rid = trim($_POST['delregion']);
		$rname = $db->query(1,"SELECT *FROM `regions` WHERE `id`='$rid'")[0]['name'];
		
		if($db->insert(1,"DELETE FROM `regions` WHERE `id`='$rid'")){
			savelog($sid,"Deleted $rname region");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	#save/update branch 
	if(isset($_POST['bid'])){
		$id=trim($_POST['bid']);
		$name=clean(strtolower($_POST['bname']));
		$payb=clean($_POST['bpay']);
		
		$chk1=$db->query(1,"SELECT *FROM `branches` WHERE `branch`='$name' AND `client`='$cid' AND NOT `id`='$id'");
		if($chk1){
			echo "Failed: Branch ".prepare(ucwords($name))." is already in the system";
		}
		else{
			$sql=($id) ? "UPDATE `branches` SET `branch`='$name',`paybill`='$payb' WHERE `id`='$id'":
			"INSERT INTO `branches` VALUES(NULL,'$cid','$name','$payb','0')";
			if($db->insert(1,$sql)){
				$txt = ($id) ? "Updated branch to $name":"Created branch $name"; savelog($sid,$txt); 
				echo "success";
			}
			else{
				echo "Failed to complete your request at the moment, try again later";
			}
		}
	}
	
	#suspend/activate branch
	if(isset($_POST['suspend'])){
		$res=explode(":",trim($_POST['suspend']));
		$id=$res[0]; $val=$res[1];
		
		if($db->insert(1,"UPDATE `branches` SET `status`='$val' WHERE `id`='$id'")){
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$id'"); $bname=$res[0]['branch'];
			$txt = ($val) ? "Suspended $bname branch":"Activated $bname branch"; savelog($sid,$txt); 
			echo "success";
		}
		else{
			echo "Failed to complete request, try again later";
		}
	}
	
	ob_end_flush();
?>