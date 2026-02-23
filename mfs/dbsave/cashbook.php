<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	
	# post transaction
	if(isset($_POST['pbran'])){
		$bran = trim($_POST['pbran']);
		$amnt = trim($_POST['tamnt']);
		$day = strtotime(trim($_POST['tday']).",".date('H:i:s'));
		$mon = strtotime(date("Y-M",$day));
		$tmon = strtotime(date("Y-M"));
		$tym = $day; $wk=date("W",$tym); $yr=date("Y",$tym);
		$ref = trim($_POST['refno']);
		$des = clean($_POST['transdes']);
		$com = clean($_POST['tcomm']);
		$creds = $_POST['creds']; $camnt = $_POST['camnt']; 
		$debs = $_POST['debs']; $damnt = $_POST['damnt']; 
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$accs[$row['id']]=prepare(ucfirst($row['account'])); $types[$row['id']]=$row['type'];
		}
		
		$av = array_unique(array_intersect($creds,$debs));
		foreach($debs as $key=>$deb){ $debits[$deb]=$damnt[$key]; }
		foreach($creds as $key=>$cred){ $credits[$cred]=$camnt[$key]; }
		
		if(empty($av)){
			if(array_key_exists(0,$debits)){ echo "Error! You havent selected one of the debits account"; }
			elseif(array_key_exists(0,$credits)){ echo "Error! You havent selected one of the credits account"; }
			elseif(array_sum($debits)!=$amnt){ echo "Failed: Debit accounts amount don't sum up to original amount of KES ".number_format($amnt); }
			elseif(array_sum($credits)!=$amnt){ echo "Failed: Credit accounts amount don't sum up to original amount of KES ".number_format($amnt); }
			else{
				$sno = getransid(); $dy=strtotime(date("Y-M-d",$tym)); $now=time();
				$rev = json_encode(array(["db"=>3,"tbl"=>"cashbook$cid","col"=>"transid","val"=>$ref,"update"=>"status:0"]));
				
				foreach($debits as $acc=>$sum){
					$bk = $types[$acc]; $upd=(in_array($bk,array("asset","expense"))) ? "+$sum":"-$sum"; $books[$acc]=$upd;
					$qrys[] = "(NULL,'$sno','$bran','$bk','$acc','$sum','debit','$des','$ref','$com','$sid','$rev','$mon','$dy','$now','$yr')";
				}
				foreach($credits as $acc=>$sum){
					$bk = $types[$acc]; $upd=(in_array($bk,array("asset","expense"))) ? "-$sum":"+$sum"; $books[$acc]=$upd;
					$qrys[] = "(NULL,'$sno','$bran','$bk','$acc','$sum','credit','$des','$ref','$com','$sid','$rev','$mon','$dy','$now','$yr')";
				}
				
				if($db->insert(3,"INSERT INTO `transactions$cid` VALUES ".implode(",",$qrys))){
					$db->insert(3,"UPDATE `cashbook$cid` SET `status`='$now' WHERE `transid`='$ref'");
					foreach($books as $acc=>$upd){
						if($acc!=14){
							$save = ($mon<$tmon && in_array($types[$acc],array("expense","income"))) ? "$upd:$mon":$upd;
							bookbal($acc,$save);
						}
					}
					
					savelog($sid,"Posted cashbook transaction $ref of KES $amnt");
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
		}
		else{
			foreach($av as $one){ $get[]=$accs[$one]; }
			$txt = (count($av)==1) ? "Account ".implode(",",$get)." is":"Accounts ".implode(",",$get)."";
			echo "Failed: $txt  available in both debit & credit accounts!";
		}
	}
	
	# delete transaction
	if(isset($_POST['delrecord'])){
		$tid = trim($_POST['delrecord']);
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `id`='$tid'");
		
		if($res){
			$des = $res[0]['transaction']; $amnt=$res[0]['amount']; $mon=strtotime(date("Y-M")); $bran=$res[0]['branch'];
			$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'");  
			
			if($db->insert(3,"DELETE FROM `cashbook$cid` WHERE `id`='$tid'")){
				cashbal($res[0]['id'],"+$amnt"); bookbal(14,"+$amnt"); 
				savelog($sid,"Deleted cashbook record {".$des."} of KES $amnt");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
		else{ echo "success"; }
	}
	
	# save cashbook transaction
	if(isset($_POST['tbran'])){
		$bran = trim($_POST['tbran']);
		$desc = clean($_POST['tdes']);
		$amnt = trim($_POST['tamnt']);
		$day = strtotime(trim($_POST['tday']).",".date('H:i:s'));
		$mon = strtotime(date("Y-M"),$day);
		$tym = $day; $wk=date("W",$tym); $yr=date("Y",$tym);
		$tmon = strtotime(date("Y-M"));
		
		if(!is_numeric($amnt)){ echo "Failed: Amount should be numeric!"; }
		elseif($amnt<1){ echo "Failed: Amount must be greater than 0"; }
		else{
			if($bran){
				$sql = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
				$bname = ucwords($sql[0]['branch']);
			}
			else{ $bname="Head Office"; }
			
			$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$tmon'");  
			$rid=($res) ? $res[0]['id']:0; $tid = getransid(); 
			if(!$rid){
				$db->insert(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$tmon','0','0')");
				$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$tmon'"); $rid = $qry[0]['id'];
			}
			
			if($db->insert(3,"INSERT INTO `cashbook$cid` VALUES(NULL,'$bran','$sid','$tid','credit','$desc','$amnt','$wk','$mon','$yr','$tym','0')")){
				cashbal($rid,"-$amnt"); bookbal(14,"-$amnt"); 
				savelog($sid,"Created cashbook record {".$desc."} of KES $amnt for $bname branch");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	# totup float
	if(isset($_POST['famnt'])){
		$amnt = clean($_POST['famnt']);
		$bran = clean($_POST['fbran']);
		$from = clean($_POST['facc']);
		
		if(!is_numeric($amnt)){ echo "Failed: Amount should be numeric!"; }
		elseif($amnt<1){ echo "Failed: Amount must be greater than 0"; }
		else{
			if($bran){
				$sql = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
				$bname = ucwords($sql[0]['branch']);
			}
			else{ $bname="Head Office"; }
			
			$facc = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$from'")[0]['account'];
			$tid = getransid(); $mon=strtotime(date("Y-M")); $wk=date("W"); $yr=date("Y"); $tym=time();
			$desc = "Cashfloat for $bname branch petty cashbook"; $ds2="Cashfloat from $facc account";
			
			$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'");  $rid=($res) ? $res[0]['id']:0;
			if(!$rid){
				$db->insert(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$mon','0','0')");
				$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'"); $rid = $qry[0]['id'];
			}
			
			if($db->insert(3,"INSERT INTO `cashbook$cid` VALUES(NULL,'$bran','$sid','$tid','debit','$ds2','$amnt','$wk','$mon','$yr','$tym','0')")){
				cashbal($rid,"+$amnt");
				$rev = json_encode(array(["db"=>3,"tbl"=>"cashfloats$cid","col"=>"id","val"=>$rid,"update"=>"closing:-$amnt"],
				["db"=>3,"tbl"=>"cashbook$cid","col"=>"transid","val"=>$tid,"update"=>"delete:$tid"]));
				doublepost([$bran,$from,$amnt,$desc,$tid,$sid],[$bran,14,$amnt,$ds2,$tid,$sid],'',time(),$rev);
				
				savelog($sid,"Toped up petty cashbook float for $bname with KES $amnt from $facc account");
				echo "success";
			}
			else{ echo "Failed to complete the request! Try again later"; }
		}
	}
	
	ob_end_flush();
?>