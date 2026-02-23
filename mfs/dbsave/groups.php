<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	insertSqlite("photos","CREATE TABLE IF NOT EXISTS images (image TEXT UNIQUE NOT NULL,data BLOB)");

	if(isset($_POST['formkeys'])){
		$ctbl = "cgroups$cid";
		$gid = trim($_POST['id']);
		$gname = ucfirst(clean($_POST["group_name"]));
		$keys = json_decode(trim($_POST['formkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$lof = (isset($_POST['loan_officer'])) ? trim($_POST['loan_officer']):0;
		$_POST['branch']=($lof) ? staffInfo($lof)['branch']:0;
		$bid = $_POST["branch"]; $upds=$fields=$vals=$validate="";
		
		foreach($keys as $key){
			if(!in_array($key,["id","def","group_id"]) && !in_array($key,$files)){
				$val = ($key=="group_name") ? $gname:clean(ucfirst($_POST[$key])); 
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',"; 
			}
		}
		
		$res1 = $db->query(2,"SELECT *FROM `$ctbl` WHERE LCASE(group_name)='".strtolower($gname)."' AND `branch`='$bid' AND NOT `id`='$gid'"); 
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		if($res1){ echo "Failed: Group $gname already exists"; }
		elseif($lof==0){ echo "Failed: No Loan officer selected"; }
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
									crop("../../docs/img/$img",800,700,"../../docs/img/$img"); 
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
			
			if(!$gid){
				$fields.="`def`,`group_id`"; $vals.="'[]','".getGroupId()."'"; 
			}
			
			$ins = rtrim($vals,','); $order = rtrim($fields,','); 
			$query = ($gid) ? "UPDATE `$ctbl` SET ".rtrim($upds,',')." WHERE `id`='$gid'":"INSERT INTO `$ctbl` ($order) VALUES($ins)";
			if($db->execute(2,$query)){
				if($gid && in_array("client_group",$db->tableFields(2,"org$cid"."_clients"))){
					$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `client_group`='$gid' AND NOT `loan_officer`='$lof'");
					if($sql){
						foreach($sql as $row){
							$idno = $row['idno'];
							$db->execute(2,"UPDATE `org$cid"."_clients` SET `loan_officer`='$lof',`branch`='$bid' WHERE `idno`='$idno'");
							$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `loan_officer`='$lof',`branch`='$bid' WHERE `client_idno`='$idno' AND `status`<8");
							$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE (balance+penalty)>0 AND `client_idno`='$idno'");
							if($qri){
								foreach($qri as $rw){
									$lid = $rw["loan"];
									$db->execute(2,"UPDATE `org$cid"."_loans` SET `loan_officer`='$lof',`branch`='$bid' WHERE `loan`='$lid'");
									$db->execute(2,"UPDATE `org$cid"."_schedule` SET `officer`='$lof' WHERE `loan`='$lid'");
								}
							}
						}
					}
					groupState($gid);
				}
				
				setTarget($lof,strtotime(date("Y-M")),["groups"]);
				$txt = ($gid) ? "Updated loan group $gname details":"Created new loan group $gname";
				savelog($sid,$txt); echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# add clients to group
	if(isset($_POST["gaddto"])){
		$gid = trim($_POST["gaddto"]);
		$dto = $_POST["idnos"]; $error=""; $qrys=[];
		
		$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
		$bid = $sql[0]["branch"]; $lof=$sql[0]["loan_officer"]; $gname=ucfirst($sql[0]["group_name"]);
		
		foreach($dto as $idno){
			$chk = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			if($chk){
				if($chk[0]["status"]<2){
					$names[] = ucwords($chk[0]['name']);
					$qrys[] = "UPDATE `org$cid"."_clients` SET `client_group`='$gid',`loan_officer`='$lof',`branch`='$bid' WHERE `idno`='$idno'";
					$qrys[] = "UPDATE `org$cid"."_loantemplates` SET `loan_officer`='$lof',`branch`='$bid' WHERE `client_idno`='$idno' AND `status`<8";
					$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE (balance+penalty)>0 AND `client_idno`='$idno'");
					if($qri){
						foreach($qri as $rw){
							$lid = $rw["loan"];
							$qrys[] = "UPDATE `org$cid"."_loans` SET `loan_officer`='$lof',`branch`='$bid' WHERE `loan`='$lid'";
							$qrys[] = "UPDATE `org$cid"."_schedule` SET `officer`='$lof' WHERE `loan`='$lid'";
						}
					}
				}
				else{ $error = ucwords($chk[0]["name"])." is suspended"; break; }
			}
			else{ $error = "Client Idno $idno is not found in the system"; break; }
		}
		
		if($error && !$qrys){ echo $error; }
		else{
			foreach($qrys as $qry){ $db->execute(2,$qry); }
			savelog($sid,"Added clients ".implode(",",$names)." to $gname");
			groupState($gid); echo "success";
		}
	}
	
	# move client to another group
	if(isset($_POST["movegrp"])){
		$src = explode(":",trim($_POST["movegrp"]));
		$gto = trim($_POST["mvto"]);
		$gid = $src[0]; $rid=$src[1];
		
		if($db->execute(2,"UPDATE `org$cid"."_clients` SET `client_group`='$gto' WHERE `id`='$rid'")){
			$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gto'"); $lof=$sql[0]["loan_officer"];
			$chk1 = $db->query(2,"SELECT `name`,`loan_officer`,`idno` FROM `org$cid"."_clients` WHERE `id`='$rid'");
			$chk2 = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'"); $row=$chk2[0];
			foreach(array("group_leader","secretary","treasurer") as $one){
				if($row[$one]==$rid){ $db->execute(2,"UPDATE `cgroups$cid` SET `$one`='0' WHERE `id`='$gid'"); }
			}
			
			if($lof!=$chk1[0]["loan_officer"]){
				$idno = $chk1[0]['idno']; $bid=$row["branch"];
				$db->execute(2,"UPDATE `org$cid"."_clients` SET `loan_officer`='$lof',`branch`='$bid' WHERE `idno`='$idno'");
				$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `loan_officer`='$lof',`branch`='$bid' WHERE `client_idno`='$idno' AND `status`<8");
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE (balance+penalty)>0 AND `client_idno`='$idno'");
				if($qri){
					foreach($qri as $rw){
						$lid = $rw["loan"];
						$db->execute(2,"UPDATE `org$cid"."_loans` SET `loan_officer`='$lof',`branch`='$bid' WHERE `loan`='$lid'");
						$db->execute(2,"UPDATE `org$cid"."_schedule` SET `officer`='$lof' WHERE `loan`='$lid'");
					}
				}
			}
			
			groupState($gid); groupState($gto); $cname=ucwords($chk1[0]["name"]); 
			savelog($sid,"Moved client $cname from group ".ucfirst($row["group_name"])." to ".ucfirst($sql[0]['group_name']));
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# remove client from group
	if(isset($_POST["rmgclient"])){
		$src = explode(":",trim($_POST["rmgclient"]));
		$gid = $src[0]; $rid=$src[1];
		
		if($db->execute(2,"UPDATE `org$cid"."_clients` SET `client_group`='0' WHERE `id`='$rid'")){
			$chk1 = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `id`='$rid'");
			$chk2 = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'"); $row=$chk2[0];
			foreach(array("group_leader","secretary","treasurer") as $one){
				if($row[$one]==$rid){ $db->execute(2,"UPDATE `cgroups$cid` SET `$one`='0' WHERE `id`='$gid'"); }
			}
			
			groupState($gid); $cname=ucwords($chk1[0]["name"]);  $gname=ucwords($row["group_name"]); 
			savelog($sid,"Removed client $cname from group $gname");
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# delete client group
	if(isset($_POST["delgroup"])){
		$gid = trim($_POST["delgroup"]);
		$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$gid'");
		
		if($db->execute(2,"DELETE FROM `cgroups$cid` WHERE `id`='$gid'")){
			setTarget($sql[0]["loan_officer"],strtotime(date("Y-M",$sql[0]["time"])),["groups"]);
			savelog($sid,"Deleted client group ".$sql[0]["group_name"]);
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}

	@ob_end_flush();
?>