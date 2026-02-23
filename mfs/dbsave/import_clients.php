<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	require "../../xls/index.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_FILES['cxls'])){
		$tmp = $_FILES['cxls']['tmp_name'];
		$ext = @array_pop(explode(".",strtolower(trim($_FILES['cxls']['name']))));
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['idno']]=$row['id'];
		}
		
		if(!in_array($ext,array("csv","xls","xlsx"))){ echo "Failed: File formart $ext is not supported! Choose XLS,CSV & XLSX files"; }
		else{
			if(move_uploaded_file($tmp,"../../docs/temp.$ext")){
				$invalid=$fonfake=$saved=$clients=$found=$errors=$phones=$incomplete=[]; 
				$no=0; $ctbl="org".$cid."_clients";
				
				$res = $db->query(2,"SELECT *FROM `$ctbl`");
				if($res){
					foreach($res as $row){ $clients[$row['contact']]=$row['idno']; }
				}
				
				$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
				foreach($res as $row){
					$staff[$row['idno']]=$row['id']; $brans[$row['idno']]=$row['branch'];
				}
				
				$exclude = array("id","cdef","status","time","branch","cycles","creator");
				$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='".$cid."' AND `name`='$ctbl'");
				$cols = ($res) ? json_decode($res[0]['fields'],1):[];
				foreach($cols as $col=>$dtp){
					if(in_array($dtp,["image","pdf","doc"])){ $exclude[]=$col; }
				}
		
				foreach($exclude as $col){ unset($cols[$col]); }
				$fields = array_keys($cols);
				$idpos = array_search("idno",$fields);
				$fpos = array_search("contact",$fields); 
				$lpos = array_search("loan_officer",$fields);
		
				$data = openExcel("../../docs/temp.$ext");
				foreach($data as $akey=>$one){
					if(count($one)>=count($fields) && !in_array($one[$idpos],$saved)){
						$idno=$one[$idpos]; $fon=intval(ltrim($one[$fpos],"254")); $ofid=$one[$lpos];
						
						if(!in_array($idno,$clients)){
							if(!isset($clients[$fon]) && !isset($saved[$fon])){
								if(isset($staff[$ofid])){
									if(strlen($fon)==9){
										$vals=$cols="";
										$replace = array("cdef"=>"[]","status"=>0,"time"=>time(),"branch"=>$brans[$ofid],"creator"=>$sid,"cycles"=>0);
										foreach($replace as $key=>$val){ $cols.="`$key`,"; $vals.="'$val',"; }
										
										foreach($fields as $pos=>$col){
											$val = ($col=="loan_officer") ? $staff[$one[$pos]]:clean(strtolower($one[$pos]));
											if(strpos($col,"date")!==false){ 
												$val=(is_numeric($one[$pos])) ? exceldate($one[$pos]):$one[$pos];
											}
											$cols.="`$col`,"; $vals.="'$val',";
										}
										
										$indata = rtrim($vals,","); $incols = rtrim($cols,","); 
										if($db->execute(2,"INSERT INTO `$ctbl` ($incols) VALUES ($indata)")){
											$no++; $saved[$fon]=$idno;
										}
									}
									else{ $fonfake[]=$fon; }
								}
								else{ $invalid[]=$ofid; }
							}
							else{ $phones[]=$fon; }
						}
						else{ $found[]=$idno; }
					}
					else{ $incomplete[]="Row ".($akey+1); }
				}
				
				if(count($incomplete)){ $errors['incomplete']=$incomplete; }
				if(count($fonfake)){ $errors['phonefake']=$fonfake; }
				if(count($invalid)){ $errors['invalid']=$invalid; }
				if(count($found)){ $errors['exists']=$found; }
				if(count($phones)){ $errors['phones']=$phones; }
				if(count($errors)){ file_put_contents("import_errors.dll",json_encode($errors,1)); }
				
				$all=count($data); $cond = ($no==$all) ? "all":$no; $cond = ($no==0) ? "none":$cond;
				if($no){ savelog($sid,"Imported $no/$all new clients"); }
				echo "success:$cond:$all";
			}
			else{ echo "Failed to open file! Try again later"; }
		}
	}
	
	ob_end_flush();
?>