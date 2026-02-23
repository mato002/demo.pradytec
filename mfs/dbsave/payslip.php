<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# update payslips
	if(isset($_POST['bpid'])){
		$pid = trim($_POST['bpid']);
		$des = $_POST['paydata']; $upds="";
		foreach($des as $col=>$val){ $upds.="`$col`='".clean($val)."',"; }
		
		if($db->insert(3,"UPDATE `payslips$cid` SET ".rtrim($upds,",")." WHERE `id`='$pid'")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again Later"; }
	}
	
	# mail/generate payslips
	if(isset($_POST['mailmon'])){
		$mon = trim($_POST['mailmon']);
		$mail = (isset($_POST['noemail'])) ? 0:1;
		
		if(isset($_POST['payid'])){
			$res = $db->query(1,"SELECT *FROM `stamps` WHERE `client`='$cid' AND `month`='$mon'");
			$stmp = ($res) ? $res[0]['stamp']:mficlient()['logo']; $pid = trim($_POST['payid']);
		}
		else{ $stmp = trim($_POST['stamp']); $pid=0; }
		
		$pts = "pdf/files/payslip.php?src=$sid";
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "http://localhost/mfs/$pts":"https://".$_SERVER['HTTP_HOST']."/$pts";
		$res = request($url,["paymonth"=>$mon,"pid"=>$pid,"stamp"=>$stmp,"sendmail"=>$mail]);
		echo (explode(":",$res)[0]=="success") ? $res:"Failed to complete the request! Try again Later";
	}
	
	# change stamp
	if(isset($_FILES['stamp'])){
		$tmp = $_FILES["stamp"]['tmp_name'];
		$mon = trim($_POST['stampmon']);
		$ext = strtolower(@array_pop(explode(".",$_FILES['stamp']['name'])));
		
		if(@getimagesize($tmp)){
			if(in_array($ext,array("png","jpg","gif","jpeg"))){
				$newname = "Stamp_".date("Ymd_his").".$ext";
				$res = $db->query(1,"SELECT *FROM `stamps` WHERE `client`='$cid' AND `month`='$mon'");
				$prev = ($res) ? $res[0]['stamp']:"";
				
				if(move_uploaded_file($tmp,"../../docs/img/$newname")){
					crop("../../docs/img/$newname",250,200,"../../docs/img/$newname");
					$query = ($prev) ? "UPDATE `stamps` SET `stamp`='$newname' WHERE `client`='$cid' AND `month`='$mon'":
					"INSERT INTO `stamps` VALUES(NULL,'$cid','$newname','$mon')";
					
					if($db->insert(1,$query)){
						if($prev){ unlink("../../docs/img/$prev"); }
						savelog($sid,"Updated Payslip stamp for ".date("F Y",$mon));
						echo "success:$newname";
					}
					else{
						unlink("../../docs/img/$newname");
						echo "Failed to update stamp! Try again later"; 
					}
				}
				else{ echo "Failed to upload photo! Try again later"; }
			}
			else{ echo "Failed: Image extension $ext is not supported"; }
		}
		else{ echo "Failed: File selected is not a valid photo"; }
	}
	
	# generate payslips
	if(isset($_POST['slipmon'])){
		$mon = trim($_POST['slipmon']);
		$pays = $_POST['pays'];
		$banks = $_POST['banks'];
		$codes = $_POST['bnkcodes'];
		$brans = $_POST['brncodes'];
		$accs = $_POST['accounts'];
		$cheques = $_POST['cheques'];
		$yr = date("Y",$mon); $qrys=[];
		
		foreach($pays as $stid=>$amnt){
			$qrys[]="(NULL,'$stid','".clean($banks[$stid])."','".clean($codes[$stid])."','".clean($brans[$stid])."','$accs[$stid]','$cheques[$stid]','$amnt','$mon','$yr')";
		}
		
		$cols = $db->tableFields(3,"payslips$cid");
		if(!in_array("bankcode",$cols)){
			$db->insert(3,"ALTER TABLE `payslips$cid` ADD `bankcode` VARCHAR(255) NOT NULL AFTER `bank`, ADD `branch` VARCHAR(255) NOT NULL AFTER `bankcode`");
		}
		
		if(count($qrys)){
			if($db->insert(3,"INSERT INTO `payslips$cid` VALUES ".implode(",",$qrys))){
				savelog($sid,"Generated staff payslips for ".date("F Y",$mon));
				echo "success";
			}
			else{ echo "Failed to generate payslips! Trya again later"; }
		}
		else{ echo "Failed: No payroll record found for ".date("F Y",$mon); }
	}
	
	ob_end_flush();
?>