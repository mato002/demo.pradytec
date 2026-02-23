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
		$ctbl = "org".CLIENT_ID."_clients";
		$keys = json_decode(trim($_POST['formkeys']),1);
		$uid = trim($_POST['id']);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$idno = clean(strtolower($_POST['idno']));
		$cont = intval(ltrim(clean($_POST['contact']),"254"));
		$upds=$fields=$vals=$validate=""; $data=[];
		$lof = (isset($_POST['loan_officer'])) ? trim($_POST['loan_officer']):0;
		$_POST['branch']=($lof) ? staffInfo($lof)['branch']:0;
		$allow_dups = (defined("ALLOW_DUPLICATE_PHONES")) ? ALLOW_DUPLICATE_PHONES:0;
		
		foreach($keys as $key){
			if(!in_array($key,["id","cdef"]) && !in_array($key,$files)){
				$val = (isset($_POST[$key])) ? clean(strtolower($_POST[$key])):""; $data[$key]=$val;
				$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
			}
		}
		
		$res1 = $db->query(2,"SELECT *FROM `$ctbl` WHERE `idno`='$idno' AND NOT `id`='$uid'"); 
		$res2 = $db->query(2,"SELECT *FROM `$ctbl` WHERE `contact`='$cont' AND NOT `id`='$uid'"); 
		
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		if(count(explode(" ",clean($_POST['name'])))<2){ echo "Please provide more than one Name"; }
		elseif(!is_numeric($cont)){ echo "Failed: Contact must be numeric"; }
		elseif(strlen($cont)!=9){ echo "Invalid Contact! It has ".strlen($cont)." numbers"; }
		elseif($res1){ echo "Failed: Id number $idno is already in the system"; }
		elseif($res2 && !$allow_dups){ echo "Failed: Contact $cont is already in use"; }
		elseif($lof==0){ echo "Failed: No Loan officer selected"; }
		elseif($validate){ echo $validate; }
		else{
			$others=$updatekeys=[];
			if($uid){
				$res = $db->query(2,"SELECT *FROM `$ctbl` WHERE `id`='$uid'"); 
				$row = $res[0]; $ofid=$row['loan_officer']; $bran=$row['branch']; $did=$row['idno']; $name=$row['name']; $fon=$row['contact'];
				$gid = (isset($row["client_group"])) ? $row["client_group"]:0;
				
				$others = array(
					"org".$cid."_loans"=>array("client_idno"=>$idno,"client"=>$data['name'],"branch"=>$data['branch'],"loan_officer"=>$data['loan_officer'],"phone"=>$data['contact']),
					"org".$cid."_loantemplates"=>array("client_idno"=>$idno,"client"=>$data['name'],"branch"=>$data['branch'],"loan_officer"=>$data['loan_officer']),
					"org".$cid."_prepayments"=>array("idno"=>$idno,"client"=>$data['name'],"branch"=>$data['branch'],"officer"=>$data['loan_officer']),
					"processed_payments$cid"=>array("idno"=>$idno,"client"=>$data['name'],"branch"=>$data['branch'],"officer"=>$data['loan_officer']),
					"mergedpayments$cid"=>array("idno"=>$idno,"branch"=>$data['branch'],"officer"=>$data['loan_officer']),"client_leads$cid"=>array("idno"=>$idno),
					"writtenoff_loans$cid"=>array("client"=>$idno),"investors$cid"=>array("idno"=>$idno,"name"=>$data['name'],"contact"=>$data['contact']),
					"overpayments$cid"=>array("idno"=>$idno,"branch"=>$data['branch'],"officer"=>$data['loan_officer']),
					"org".$cid."_schedule"=>array("idno"=>$idno,"officer"=>$data['loan_officer'])
				);
				
				$updatekeys = array(
					"org".$cid."_loans"=>array("client_idno"=>$row['idno']),"org".$cid."_schedule"=>array("idno"=>$row['idno']),
					"org".$cid."_loantemplates"=>array("client_idno"=>$row['idno']),"mergedpayments$cid"=>array("idno"=>$row['idno']),
					"overpayments$cid"=>array("idno"=>$row['idno']),"processed_payments$cid"=>array("idno"=>$row['idno']),"client_leads$cid"=>array("idno"=>$row['idno']),
					"writtenoff_loans$cid"=>array("client"=>$row['idno']),"org".$cid."_prepayments"=>array("idno"=>$row['idno']),"investors$cid"=>array("idno"=>$row['idno'])
				);
			}
			else{ $upds.="`cdef`='[]',"; $fields.="`cdef`,"; $vals.="'[]',";  }
			
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
									crop("../../docs/img/$img",1500,1200,"../../docs/img/$img"); 
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
			
			$ins = rtrim($vals,','); $order = rtrim($fields,','); $idno=$data['idno'];
			$query = ($uid) ? "UPDATE `$ctbl` SET ".rtrim($upds,',')." WHERE `id`='$uid'":"INSERT INTO `$ctbl` ($order) VALUES($ins)";
			if($db->execute(2,$query)){
				if(count($others)){
					$me = staffInfo($sid); $perms=getroles(explode(",",$me['roles'])); 
					if($idno!=$did or $data['branch']!=$bran or $lof!=$ofid or $data['name']!=$name or $cont!=$fon){
						foreach($others as $tbl=>$des){
							if($db->istable(2,$tbl)){
								$hd=$updatekeys[$tbl]; $cond=array_keys($hd); $fld=$cond[0]; $ups=""; 
								foreach($des as $key=>$val){ $ups.="`$key`='$val',"; }
								$db->execute(2,"UPDATE `$tbl` SET ".rtrim($ups,",")." WHERE `$fld`='".$hd[$fld]."'");
								if($tbl=="org$cid"."_loantemplates" && in_array("delete client",$perms) && $cont!=$fon){
									$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `phone`='$cont' WHERE `client_idno`='$idno' AND `loan`='0'");
								}
							}
						}
					}
					
					if(isset($data["client_group"])){
						if($data["client_group"]!=$gid && $data["client_group"]){
							$nid = $data["client_group"]; groupState($nid);
							$chk = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `id`='$nid'");
							if($chk[0]["loan_officer"]!=$data["loan_officer"]){
								$lof = $chk[0]["loan_officer"];
								$db->execute(2,"UPDATE `org$cid"."_clients` SET `loan_officer`='$lof' WHERE `idno`='$idno'");
								$db->execute(2,"UPDATE `org$cid"."_loantemplates` SET `loan_officer`='$lof' WHERE `client_idno`='$idno' AND `status`<8");
								$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE (balance+penalty)>0 AND `client_idno`='$idno'");
								if($qri){
									foreach($qri as $rw){
										$lid = $rw["loan"];
										$db->execute(2,"UPDATE `org$cid"."_loans` SET `loan_officer`='$lof' WHERE `loan`='$lid'");
										$db->execute(2,"UPDATE `org$cid"."_schedule` SET `officer`='$lof' WHERE `loan`='$lid'");
									}
								}
							}
						}
					}
				}
				
				if($db->istable(2,"client_leads$cid") && !$uid){ $db->execute(2,"UPDATE `client_leads$cid` SET `status`='1' WHERE `contact`='$cont' OR `idno`='$idno'"); }
				$txt = ($uid) ? "Updated client account details for":"Created new client account";
				savelog($sid,"$txt ".ucwords($_POST['name'])); 
				echo "success:$idno";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# upload media
	if(isset($_POST["clientid"])){
		$uid = trim($_POST["clientid"]);
		$dname = clean($_POST["dname"]);
		$tmp = $_FILES["cdoc"]["tmp_name"];
		$ext = @array_pop(explode(".",strtolower($_FILES["cdoc"]['name'])));
		$djs = json_encode(["from"=>$sid,"dname"=>$dname],1); $now=time();
		
		if(in_array($ext,["pdf","doc","docx","png","jpg","jpeg","gif"])){
			$cname = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `id`='$uid'")[0]["name"];
			if(in_array($ext,["png","jpg","jpeg","gif"])){
				if(@getimagesize($tmp)){
					$img = "IMG_".date("Ymd_His").".$ext";
					if(move_uploaded_file($tmp,"../../docs/img/$img")){
						if($db->execute(2,"INSERT INTO `sysdocs$cid` VALUES(NULL,'client','$uid','$img','$djs','0','$now')")){
							crop("../../docs/img/$img",2000,1500,"../../docs/img/$img"); 
							insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../../docs/img/$img"))."')");
							unlink("../../docs/img/$img");
							savelog($sid,"Uploaded client document $dname to ".ucwords($cname));
							echo "success";
						}
						else{ echo "Failed to save the document!"; unlink("../../docs/img/$img"); }
					}
					else{ echo "Failed to upload photo: Unknown Error occured"; }	
				}
				else{ echo "File selected is not an Image"; }
			}
			else{
				$fname = "DOC_".date("Ymd_His").".$ext";
				if(move_uploaded_file($tmp,"../../docs/$fname")){
					if($db->execute(2,"INSERT INTO `sysdocs$cid` VALUES(NULL,'client','$uid','$fname','$djs','0','$now')")){
						savelog($sid,"Uploaded client document $dname to ".ucwords($cname));
						echo "success";
					}
					else{ echo "Failed to save the document!"; unlink("../../docs/$fname"); }
				}
				else{ echo "Failed to upload document: Unknown Error occured"; }
			}
		}
		else{ echo "Failed: File type $ext is not supported"; }
	}
	
	# set overdraft LIMIT
	if(isset($_POST["setoverdlim"])){
		$wid = trim($_POST["setoverdlim"]);
		$amnt = intval($_POST["limamnt"]);
		
		$sql = $db->query(3,"SELECT `def` FROM `wallets$cid` WHERE `id`='$wid'");
		$def = ($sql[0]["def"]) ? json_decode($sql[0]["def"],1):[]; $def["ovdlimit"]=$amnt; $ojs=json_encode($def,1);
		
		if($db->execute(3,"UPDATE `wallets$cid` SET `def`='$ojs' WHERE `id`='$wid'")){
			savelog($sid,"Adjusted Account T11$wid overdraft limit to ".fnum($amnt));
			echo "success";
		}
		else{ echo "Failed to complete request at the moment!"; }
	}
	
	# reset app PIN
	if(isset($_POST["resetpin"])){
		$idno = trim($_POST["resetpin"]);
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$cdef = json_decode($sql[0]['cdef'],1); unset($cdef["ckey"],$cdef["logtrials"]); $jsn=json_encode($cdef,1);
		
		if($db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'")){
			savelog($sid,"Reset app login PIN for ".ucwords($sql[0]['name']));
			echo "success";
		}
		else{ echo "Failed to complete request at the moment!"; }
	}
	
	# set max loan limit
	if(isset($_POST["setmaxlim"])){
		$idno = trim($_POST["setmaxlim"]);
		$val = trim($_POST["sval"]);
		$qri = $db->query(1,"SELECT MAX(maxamount) AS mxl FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		$qry = $db->query(2,"SELECT `name`,`cdef` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
		$lim = ($qri) ? $qri[0]['mxl']:0; $cdef=json_decode($qry[0]['cdef'],1); $denied=0; $maxet=$val;
		
		if(defined("CAPP_LIMIT")){
			$sql = $db->query(2,"SELECT `amount` FROM `org$cid"."_loans` WHERE `client_idno`='$idno' ORDER BY `disbursement` DESC");
			if($sql){ $maxet=round($sql[0]["amount"]*CAPP_LIMIT); }
		}
		
		if(defined("UPWARD_LIMIT_SET")){
			$me = staffInfo($sid); $maxs=(isset($cdef["maxset"])) ? $cdef["maxset"]:max(loanlimit($idno,"app"));
			$denied = ($val>$maxs && !in_array($me["position"],array_merge(UPWARD_LIMIT_SET,["super user","Director"]))) ? 1:0;
		}
		
		if($lim<$val){ echo "Failed: Maximum loan limit for App products is $lim"; }
		elseif($val>$maxet){ echo "Failed: Maximum loan amount qualified by client is ".fnum($maxet); }
		elseif($denied){ echo "Failed: You are not allowed to alter Loan limit upwards"; }
		else{
			$cname=ucwords($qry[0]['name']); $cdef["maxset"]=$val; $jsn=json_encode($cdef,1);
			if($db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'")){
				savelog($sid,"Set maximum loan limit for $cname to $val");
				echo "success";
			}
			else{ echo "Failed to complete request at the moment!"; }
		}
	}
	
	# enable/disable autodisburse
	if(isset($_POST["autodisburse"])){
		$idno = trim($_POST["autodisburse"]);
		$val = trim($_POST["autoval"]);
		$client = $db->query(2,"SELECT `name`,`cdef` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0];
		$cdef = json_decode($client["cdef"],1); $cdef["autodisburse"]=$val; $jsn=json_encode($cdef,1);
		
		if($db->execute(2,"UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'")){
			$name=$client['name']; $txt=($val) ? "Enabled":"Disabled";
			savelog($sid,"$txt auto disbursement for $name");
			echo "success";
		}
		else{ echo "Failed to complete request at the moment!"; }
	}
	
	# save client lead
	if(isset($_POST['leadkeys'])){
		$ltbl = "client_leads$cid";
		$keys = json_decode(trim($_POST['leadkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$idno = clean(strtolower($_POST['idno']));
		$cont = intval(ltrim(clean($_POST['contact']),"254"));
		
		$_POST['branch']=(trim($_POST['loan_officer'])) ? staffInfo(trim($_POST['loan_officer']))['branch']:0;
		$_POST['creator']=$sid; $_POST['time']=time(); 
		
		$upds=$fields=$vals=$validate=""; $data=$post=[];
		$save = array("name","branch","loan_officer","contact","idno","status","time");
		$skip = array("id","cdef","time","branch","cycles");
		
		foreach($keys as $key){
			if($key!="id" && !in_array($key,$files)){
				if(in_array($key,$save)){
					$val = clean($_POST[$key]); $data[$key]=$val;
					$upds.="`$key`='$val',"; $fields.="`$key`,"; $vals.="'$val',";
				}
				else{
					if(!in_array($key,$skip)){ $post[$key]=clean($_POST[$key]); }
				}
			}
		}
		
		if(isset($_POST['dlinks'])){
			foreach($_POST['dlinks'] as $fld=>$from){
				$src=explode(".",$from); $tbl=$src[0]; $col=$src[1]; $dbname = (substr($tbl,0,3)=="org") ? 2:1;
				$res = $db->query($dbname,"SELECT $col FROM `$tbl` WHERE `$col`='".clean($_POST[$fld])."'");
				if(!$res){ $validate="$col ".$_POST[$fld]." is not found in the system"; break; }
			}
		}
		
		$chk1 = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno' AND NOT `idno`='0'");
		$chk2 = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `contact`='$cont'");
		$chk3 = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `idno`='$idno' AND NOT `idno`='0'");
		$chk4 = $db->query(2,"SELECT *FROM `client_leads$cid` WHERE `contact`='$cont'");
	
		if(count(explode(" ",clean($_POST['name'])))<2){ echo "Please provide more than one Name"; }
		elseif(!is_numeric($cont)){ echo "Failed: Contact must be numeric"; }
		elseif(strlen($cont)!=9){ echo "Invalid Contact! It has ".strlen($cont)." numbers"; }
		elseif($chk1){ echo "Failed: Id number $idno is already in clients record"; }
		elseif($chk2){ echo "Failed: Contact $cont is already in clients record"; }
		elseif($chk3){ echo "Failed: Id number $idno already exists"; }
		elseif($chk4){ echo "Failed: Contact $cont already exists"; }
		elseif(trim($_POST['loan_officer'])==0){ echo "Failed: No Loan officer selected"; }
		elseif($validate){ echo $validate; }
		else{
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
									crop("../../docs/img/$img",350,300,"../../docs/img/$img"); 
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
			
			$vals.="'".json_encode($post,1)."'"; $fields.="`others`";
			if($db->execute(2,"INSERT INTO `$ltbl` ($fields) VALUES($vals)")){
				savelog($sid,"Created client lead for ".$_POST['name']); 
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# save client interaction
	if(isset($_POST['intrnkeys'])){
		$itbl = "interactions$cid";
		$keys = json_decode(trim($_POST['intrnkeys']),1);
		$files = (trim($_POST['hasfiles'])) ? explode(":",trim($_POST['hasfiles'])):[];
		$upds=$fields=$vals=$validate=""; $data=[];
		
		foreach($keys as $key){
			if($key!="id" && !in_array($key,$files)){
				$val = clean($_POST[$key]); $data[$key]=$val;
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
		
		if($validate){ echo $validate; }
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
									crop("../../docs/img/$img",350,300,"../../docs/img/$img"); 
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
			
			$clid=trim($_POST['client']); $ins = rtrim($vals,','); $order = rtrim($fields,',');
			if($db->execute(2,"INSERT INTO `$itbl` ($order) VALUES($ins)")){
				$cname = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `id`='$clid'")[0]['name'];
				savelog($sid,"Posted interaction for client $cname"); 
				echo "success";
			}
			else{ echo "Failed to complete the request at the moment! Try again later"; }
		}
	}
	
	# save lead interaction
	if(isset($_POST["intrcom"])){
		$uid = trim($_POST["leadid"]);
		$com = str_replace(array("\r\n","\r","\n"),"~nl~",clean($_POST["intrcom"]));
		$sql = $db->query(2,"SELECT `comments`,`name` FROM `client_leads$cid` WHERE `id`='$uid'");
		
		$coms = ($sql[0]["comments"]) ? json_decode($sql[0]["comments"],1):[]; $coms["$sid:".time()]=$com; $jsn=json_encode($coms,1);
		if($db->execute(2,"UPDATE `client_leads$cid` SET `comments`='$jsn' WHERE `id`='$uid'")){
			savelog($sid,"Posted interaction for client ".$sql[0]['name']); 
			echo "success";
		}
		else{ echo "Failed to complete the request at the moment! Try again later"; }
	}
	
	// transfer Clients
	if(isset($_POST['tfro'])){
		$fro = trim($_POST['tfro']);
		$to = trim($_POST['tto']);
		$total = trim($_POST['tactive']);
		$dorm = trim($_POST['dormtoo']);
		$clients = (isset($_POST['tclients'])) ? $_POST['tclients']:0; 
		$mon = strtotime(date("Y-M")); $lmon=strtotime(date("Y-M-d",$mon-60)); $idnos=[];
		$monend = monrange(date("m",$mon),date("Y",$mon))[1]+86399;
		$sto = staffInfo($to); $nbran=$sto['branch']; 
		
		if($clients==0){ echo "Please Select atleast one Client"; }
		else{
			foreach($clients as $client){
				$idno=explode(":",$client)[0]; $lid=explode(":",$client)[1]; $idnos[]=$idno;
				$db->execute(2,"UPDATE `org".$cid."_loans` SET `loan_officer`='$to',`branch`='$nbran' WHERE `loan_officer`='$fro' AND `client_idno`='$idno'");
				$db->execute(2,"UPDATE `org".$cid."_schedule` SET `officer`='$to' WHERE `loan`='$lid'");
				$db->execute(2,"UPDATE `org".$cid."_clients` SET `loan_officer`='$to',`branch`='$nbran' WHERE `idno`='$idno'");
			}
			
			if($dorm){ $db->execute(2,"UPDATE `org".$cid."_clients` SET `loan_officer`='$to',`branch`='$nbran' WHERE `loan_officer`='$fro' AND `status`='0'"); }
			if($total!=count($clients)){
				$qry = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `loan_officer`='$fro' AND `time` BETWEEN '$mon' AND '$monend'");
				if($qry){
					foreach($qry as $row){
						$idno=$row['client_idno']; $dy=strtotime(date("Y-M-d",$row['status'])); $rid=$row['id'];
						if(in_array($idno,$idnos)){ $db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `loan_officer`='$to',`branch`='$nbran' WHERE `id`='$rid'"); }
					}
				}
				
				$keys = array_keys(KPI_LIST);
				setTarget($to,$mon,$keys,0,1); setTarget($fro,$mon,$keys,0,1);
			}
			else{
				$db->execute(2,"DELETE FROM `targets$cid` WHERE `officer`='$to' AND `month`='$mon'");
				$db->execute(2,"UPDATE `targets$cid` SET `officer`='$to',`branch`='$nbran' WHERE `officer`='$fro' AND `month`='$mon'");
				$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `loan_officer`='$to',`branch`='$nbran' WHERE `loan_officer`='$fro' AND `time` BETWEEN '$mon' AND '$monend'");
				$db->execute(2,"UPDATE `paysummary$cid` SET `officer`='$to',`branch`='$nbran' WHERE `officer`='$fro' AND `month`='$mon'");
				setTarget($to,$mon,array_keys(KPI_LIST),0,1);
			}
			
			savelog($sid,"Transfered ".count($clients)." clients from Loan officer ".ucwords(staffInfo($fro)['name'])." to ".ucwords($sto['name']));
			echo "success";
		}
	}
	
	# client groups
	if(isset($_POST['savegrp'])){
		$prev = trim($_POST['savegrp']);
		$grp = clean(ucfirst(urldecode($_POST['gname'])));
		$cids = array_unique($_POST['clients']);
		$gid = ($prev) ? $prev:rand(12345678,87654321);
		$qrys = [];
		
		if($prev){
			$res = $db->query(2,"SELECT name FROM `client_groups$cid` WHERE `gid`='$gid' LIMIT 1");
			$grp = $res[0]['name'];
		}
		
		if(in_array("all",$cids)){
			$me = staffInfo($sid); 
			$cond = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
			$cond = ($me['access_level']=="portfolio") ? "AND `loan_officer`='$sid'":$cond;
			
			$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `status`<2 $cond");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $qrys[]="(NULL,'$rid','$grp','$gid','$gid$rid')";
				}
			}
		}
		else{
			foreach($cids as $rid){ $qrys[]="(NULL,'$rid','$grp','$gid','$gid$rid')"; }
		}
		
		if(count($qrys)){
			foreach(array_chunk($qrys,200) as $one){
				$db->execute(2,"INSERT IGNORE INTO `client_groups$cid` VALUES ".implode(",",$qrys));
			}
			
			$all=count($qrys); $txt = ($gid) ? "Added $all clients to client group $grp":"Created client group $grp with $all clients";
			savelog($sid,$txt); echo "success:$gid";
		}
		else{ echo "Failed: No clients found!"; }
	}
	
	# update client group
	if(isset($_POST['editgroup'])){
		$val = clean(ucfirst($_POST['editgroup']));
		$gid = trim($_POST['pgid']);
		
		if($db->execute(2,"UPDATE `client_groups$cid` SET `name`='$val' WHERE `gid`='$gid'")){
			savelog($sid,"Updated client group to $val");
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# delete client group
	if(isset($_POST['delcgroup'])){
		$val = trim($_POST['delcgroup']);
		$typ = trim($_POST['deltp']);
		$cond = ($typ=="user") ? "`unqid`='$val'":"`gid`='$val'";
		
		if($db->execute(2,"DELETE FROM `client_groups$cid` WHERE $cond")){
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# suspend/unsuspend client
	if(isset($_POST['suspend'])){
		$idno = trim($_POST['suspend']);
		$val = trim($_POST['sval']);
		$ctbl = "org".CLIENT_ID."_clients";
		
		if($db->execute(2,"UPDATE `$ctbl` SET `status`='$val' WHERE `idno`='$idno'")){
			$txt = ($val==2) ? "Suspended":"Activated";
			savelog($sid,"$txt client with Id number $idno");
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	# delete client
	if(isset($_POST['delclient'])){
		$idno = trim($_POST['delclient']);
		$ctbl = "org$cid"."_clients";
		
		if($db->execute(2,"DELETE FROM `$ctbl` WHERE `idno`='$idno'")){
			if($db->istable(2,"client_leads$cid")){ $db->execute(2,"DELETE FROM `client_leads$cid` WHERE `idno`='$idno'"); }
			savelog($sid,"Deleted client with Id number $idno");
			echo "success";
		}
		else{ echo "Failed to complete the request!"; }
	}
	
	ob_end_flush();
?>