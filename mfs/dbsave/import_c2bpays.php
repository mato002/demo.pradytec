<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	require "../../core/functions.php";
	require "../../xls/index.php";
	$db = new DBO(); $cid=CLIENT_ID;
	insertSQLite("mpays","CREATE TABLE IF NOT EXISTS dpays (code TEXT UNIQUE NOT NULL,details TEXT NOT NULL,user INT NOT NULL,time INT NOT NULL)");
	
	if(isset($_FILES['pxls'])){
		$tmp = $_FILES['pxls']['tmp_name'];
		$doc = strtolower(trim($_FILES['pxls']['name']));
		$ext = @end(explode(".",$doc));
		$pbl = explode("_",$doc)[1];
		$chk = $db->query(1,"SELECT COUNT(*) AS tot FROM `branches` WHERE `paybill`='$pbl'");
		
		if(!in_array($ext,array("csv","xls","xlsx"))){ echo "Failed: File formart $ext is not supported! Choose XLS,CSV & XLSX files"; }
		elseif(!intval($chk[0]["tot"])){ echo "Failed: Payments source paybill $pbl is not recognized by the system"; }
		else{
			if(move_uploaded_file($tmp,"../../docs/temp.$ext")){
				$found=$incomplete=$codes=$errors=[];
				$ptbl = "org".$cid."_payments"; $no=0;
				$wltbl = ($db->istable(3,"wallets$cid")) ? 1:0;
				$isone = sys_constants("c2b_one_acc");
				
				$res = $db->query(2,"SELECT code,status FROM `$ptbl` WHERE `date`>".date("YmdHis",time()-1296000));
				if($res){
					foreach($res as $row){ $codes[$row['code']]=$row['status']; }
				}
		
				$data = openExcel("../../docs/temp.$ext");
				foreach(array_slice($data,7) as $key=>$one){
					if(count($one)>=13){
						$amnt=intval(str_replace(",","",$one[5])); $acc=clean($one[12]); $payb=$data[1][1];
						if($amnt>0 && $acc){
							$code = $one[0]; $dp1=explode("-",explode(" ",$one[1])[0]); 
							$dy = $dp1[2].$dp1[1].$dp1[0].str_replace(":","",explode(" ",$one[1])[1]); 
							$from = explode("-",$one[10]); $phone=ltrim(trim($from[0]),"254"); $name=clean(trim($from[1]));
							$ptm = strtotime(str_replace(" ",",",$one[1])); $mon=strtotime(date("Y-M",$ptm));
							
							if(!isset($codes[$code])){
								$sqe = fetchSqlite("mpays","SELECT *FROM `dpays` WHERE `code`='$code'");
								$qri = $db->query(2,"SELECT *FROM `$ptbl` WHERE `code`='$code'");
								if(!$sqe && !$qri){
									if($db->execute(2,"INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','$code','$dy','$amnt','$payb','$acc','0','$phone','$name','0')")){
										recordpay("$payb:$ptm",$amnt,0); $desc="Payment from $name #$code"; $no++;
										if($isone){ updateB2C($amnt,$desc,$code,$ptm); }
										$prt = strtoupper(substr($acc,0,3)); $part=substr($acc,3);
										
										if(in_array($prt,["T11","F12","SV3","PLN","SLN"]) or (in_array(substr($acc,3,2),["01","02","03"]) && strlen($acc)>=11)){
											$pid = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `code`='$code'")[0]["id"];
											
											if(in_array($prt,["PLN","SLN"])){
												$tbl = ($prt=="SLN") ? "staff_loans$cid":"org$cid"."_loans"; 
												$col = ($prt=="SLN") ? "stid":"client_idno"; $ltp=($col=="stid") ? "staff":"client";
												$sql = $db->query(2,"SELECT `$col` FROM `$tbl` WHERE `loan`='$part' AND (balance+penalty)>0");
												if($sql){ makepay($sql[0][$col],$pid,$amnt,$ltp,$part); usleep(200000); }
											}
											elseif($wltbl){
												$wid = (in_array($prt,["T11","F12","SV3"])) ? substr($acc,0,3):intval(substr($acc,5));
												$chk = $db->query(3,"SELECT *FROM `wallets$cid` WHERE `id`='$wid'");
												if($chk){
													$tps = array("T11"=>"balance","F12"=>"investbal","SV3"=>"savings");
													$wds = array("01"=>"balance","02"=>"investbal","03"=>"savings");
													$wtp = (isset($tps[$prt])) ? $tps[$prt]:$wds[substr($acc,3,2)];
													$uid = $chk[0]['client']; $atp=$chk[0]['type'];
													
													if($con=$db->mysqlcon(2)){
														$con->autocommit(0); $con->query("BEGIN");
														$sqr = $con->query("SELECT *FROM `$ptbl` WHERE `id`='$pid' AND `status`='0' FOR UPDATE");
														if($sqr->num_rows>0){
															if($res=updateWallet($uid,"$amnt:$wtp",$atp,["desc"=>$desc,"revs"=>["tbl"=>"norev"]],0)){
																$con->query("UPDATE `$ptbl` SET `status`='$res' WHERE `id`='$pid'");
															}
														}
														$con->commit(); $con->close();
													}
												}
											}
										}
									}
								}
							}
							else{
								if($codes[$code]==1){ $db->execute(2,"UPDATE `$ptbl` SET `status`='0',`amount`='$amnt' WHERE `code`='$code'"); $no++; }
								else{ $found[]="$code($amnt)"; }
							}
						}
					}
					else{ $incomplete[]="Row ".($key+1); }
				}
				
				if(count($found)){ $errors['exists']=$found; }
				if(count($incomplete)){ $errors['incomplete']=$incomplete; }
				if(count($errors)){ file_put_contents("import_errors.dll",json_encode($errors,1)); }
				
				$all = count($data)-6;
				$cond = ($no==$all or (count($errors)==0)) ? "all":$no; $cond = ($no==0) ? "none":$cond;
				echo "success:$cond:$all";
			}
			else{
				echo "Failed to open file! Try again later";
			}
		}
	}
	
	ob_end_flush();
?>