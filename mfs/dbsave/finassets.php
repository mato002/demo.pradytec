<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	
	# save asset
	if(isset($_POST['formkeys'])){
		$atbl = "finassets$cid";
		$aid = trim($_POST['id']);
		$gname = ucfirst(clean($_POST["asset_name"]));
		$keys = json_decode(trim($_POST['formkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$cat = $_POST["asset_category"]; $upds=$fields=$vals=$validate="";
		
		foreach($keys as $key){
			if(!in_array($key,["id","def"]) && !in_array($key,$files)){
				$val = ($key=="asset_name") ? $gname:clean(ucfirst($_POST[$key])); 
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',"; 
			}
		}
		
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		$prod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$cat'"); $cost=intval($_POST["asset_cost"]); $bid=trim($_POST["branch"]);
		$res1 = $db->query(2,"SELECT *FROM `$atbl` WHERE LCASE(asset_name)='".strtolower($gname)."' AND `asset_category`='$cat' AND NOT `id`='$aid' AND `branch`='$bid' AND NOT `status`='15'"); 
		
		if($res1){ echo "Failed: Asset $gname already exists"; }
		elseif(!$prod){ echo "Failed: Asset Category doesnt exist"; }
		elseif($cost<$prod[0]["minamount"]){ echo "Failed: Minimum Asset cost for ".prepare($prod[0]["product"])." is Ksh ".fnum($prod[0]["minamount"]); }
		elseif($cost>$prod[0]["maxamount"]){ echo "Failed: Maximum Asset cost for ".prepare($prod[0]["product"])." is Ksh ".fnum($prod[0]["maxamount"]); }
		elseif($validate){ echo $validate; }
		else{
			# save files if attached
			if(count($files)){
				$ftps = json_decode(trim($_POST['ftypes']),1); $error="";
				foreach($files as $key=>$name){
					$tmp = $_FILES[$name]['tmp_name'];
					$ext = @array_pop(explode(".",strtolower($_FILES[$name]['name'])));
					
					if($ftps[$name]=="image"){
						if(@getimagesize($tmp)){
							if(in_array($ext,array("jpg","jpeg","png","gif"))){
								$img = "IMG_".date("Ymd_His")."$key.$ext";
								if(move_uploaded_file($tmp,"../../docs/img/$img")){
									crop("../../docs/img/$img",1000,900,"../../docs/img/$img"); 
									$upds.="`$name`='$img',"; $fields.="`$name`,"; $vals.="'$img',"; 
									insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../../docs/img/$img"))."')");
									unlink("../../docs/img/$img");
								}
								else{ $error = "Failed to upload photo: Unknown Error occured"; break; }
							}
							else{ $error = "Photo format $ext is not supported"; break; }
						}
						else{ $error = "File selected is not an Image"; break; }
					}
					else{
						if(in_array($ext,array("pdf","docx"))){
							$fname = "DOC_".date("Ymd_His")."$key.$ext";
							if(move_uploaded_file($tmp,"../../docs/$fname")){
								$upds.="`$name`='$fname',"; $fields.="`$name`,"; $vals.="'$fname',";
							}
							else{ $error = "Failed to upload document: Unknown Error occured"; break; }
						}
						else{ $error = "File format $ext is not supported! Only PDF & .docx files are allowed"; break; }
					}
				}
				if($error){ echo $error; exit(); }
			}
			
			if(!$aid){ $vals.="'".$_POST["def"]."'"; $fields.="`def`"; }
			$ins = rtrim($vals,','); $order = rtrim($fields,','); 
			
			$query = ($aid) ? "UPDATE `$atbl` SET ".rtrim($upds,',')." WHERE `id`='$aid'":"INSERT INTO `$atbl` ($order) VALUES($ins)";
			if($db->execute(2,$query)){
				$txt = ($aid) ? "Updated loan Asset $gname details":"Created new loan Asset $gname";
				savelog($sid,$txt); echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# delete Asset
	if(isset($_POST["delitem"])){
		$rid = trim($_POST["delitem"]);
		$sql = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$rid'");
		$row = $sql[0]; $def=json_decode($row["def"],1); $gvn=(isset($def["given"])) ? $def["given"]:[];
		
		$query = ($gvn) ? "UPDATE `finassets$cid` SET `status`='15' WHERE `id`='$rid'":"DELETE FROM `finassets$cid` WHERE `id`='$rid'";
		if($db->execute(2,$query)){
			savelog($sid,"Deleted asset ".ucfirst($row["asset_name"]));
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment! Try again later"; }
	}

	# save unit
	if(isset($_POST['usym'])){
		$sym = strtoupper(prepstr(str_replace(" ","",trim($_POST['usym']))));
		$unit = clean(strtolower($_POST['unit']));
		$uid = trim($_POST['nid']); $data=[];
		
		$sql = $db->query(1,"SELECT *FROM `units` WHERE `client`='$cid' AND `unit`='$unit' AND NOT `id`='$uid'");
		if($sql){ echo "Failed: Unit ".prepare($unit)." already exists"; }
		else{
			if(isset($_POST["units"])){
				foreach($_POST['units'] as $key=>$one){
					$data[clean($one)] = array("symbol"=>strtoupper(prepstr(str_replace(" ","",trim($_POST['syms'][$key])))),"conv"=>$_POST['convs'][$key]);
				}
			}
			
			$jsn = json_encode($data,1);
			if($uid){
				if($db->execute(1,"UPDATE `units` SET `unit`='$unit',`symbol`='$sym',`measures`='$jsn' WHERE `id`='$uid'")){
					savelog($sid,"Uptated measurement unit $unit ($sym)");
					echo "success";
				}
				else{ echo "Failed to complete the request! Try again later"; }
			}
			else{
				if($db->execute(1,"INSERT INTO `units` VALUES(NULL,'$cid','$unit','$sym','$jsn')")){
					savelog($sid,"Created measurement unit $unit ($sym)");
					echo "success";
				}
				else{ "Failed to complete the request! Try again later"; }
			}
		}
	}
	
	
	@ob_end_flush();
?>