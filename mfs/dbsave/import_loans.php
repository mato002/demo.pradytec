<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	require "../../xls/index.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_FILES['lxls'])){
		$tmp = $_FILES['lxls']['tmp_name'];
		$ext = @array_pop(explode(".",strtolower(trim($_FILES['lxls']['name']))));
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['idno']]=$row['id'];
		}
		
		if(!in_array($ext,array("csv","xls","xlsx"))){ echo "Failed: File formart $ext is not supported! Choose XLS,CSV & XLSX files"; }
		else{
			if(move_uploaded_file($tmp,"../../docs/temp.$ext")){
				$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='intrcollectype'");
				$intrcol = ($chk) ? $chk[0]["value"]:"Accrued"; $saved=$loans=$errors=$offs=[]; $no=0;
				$delpd = (defined("UPLOAD_LOANS_SKIP_PAID")) ? UPLOAD_LOANS_SKIP_PAID:0;
				
				$res = $db->query(2,"SELECT *FROM `org".$cid."_clients`");
				if($res){
					foreach($res as $row){
						$clients[$row['idno']]=$row['contact']; $brans[$row['idno']]=$row['branch']; $names[$row['idno']]=$row['name']; $offs[$row['idno']]=$row["loan_officer"];
					}
				}
				else{ echo "No clients found in the system!"; exit(); }
				
				$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
				if($res){
					foreach($res as $row){
						$prods[$row['id']]=prepare(strtolower($row['product'])); $plist[$row["id"]]=$row;
					}
				}
				else{ echo "No loan products found! Add atleast one product!"; exit(); }
				
				$res = $db->query(2,"SELECT *FROM `org".$cid."_loans` WHERE `balance`>0");
				if($res){
					foreach($res as $row){
						$loans[$row['client_idno']]=$row['loan_product'];
					}
				}
		
				$data = openExcel("../../docs/temp.$ext");
				foreach($data as $akey=>$one){
					if(count($one)>=7){
						$idno=trim($one[1]); $prod=trim($one[2]); $lamnt=intval(str_replace(",","",$one[3])); $ldur=intval($one[4]); $edy=trim($one[5]); 
						$bal=intval($one[6]); $pid=array_search(strtolower($prod),$prods); $pen=(isset($one[7])) ? intval($one[7]):0;
						
						if(!$ldur){ $errors["wrongdur"][]="Invalid Loan duration"; continue; }
						if(isset($clients[$idno])){
							$day = (is_numeric($edy)) ? exceldate($edy):$edy; $ofid=$offs[$idno]; $dy=$dsd=strtotime($day);
							if(!$dy){
								$div = explode("-",str_replace("/","-",$day)); $p1=$div[0]; $p2=$div[1]; $p3 = (count($div)>2) ? $div[2]:date("Y"); 
								$d1=(strlen($p1)==1) ? "0$p1":$p1; $d2=(strlen($p2)==1) ? "0$p2":$p2; $d3=(strlen($p3)==2) ? "20$p3":$p3;
								$d3 =($d3<(date("Y")-6)) ? date("Y"):$d3; $dy=$dsd=strtotime("$d3-$d2-$d1"); 
							}
							
							if($pid){
								$tym = $dsd+rand(24000,40000); $name=$names[$idno]; $fon=$clients[$idno];
								$loan=$lid=getLoanId(); $lprod=$plist[$pid]; $pdef=json_decode($lprod["pdef"],1); $intv=$lprod["intervals"];
								$pfrm = (isset($pdef["repaywaiver"])) ? round(86400*$pdef["repaywaiver"]):0; $pays=$payat=$adds=$vsd=$qrys=[];
								$intrtp = (isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR"; $dy+=$pfrm; $disb=$dy; $exp=$dy+($ldur*86400);   

								if(substr($lprod['interest'],0,4)=="pvar"){
									$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod['interest']."'")[0];
									$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
									$intrs = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
									foreach($vars as $key=>$perc){
										$dy = $disb+($key*86400); $cyc=$ldur/$intv;
										$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
									}
								}
								else{ $intrs = (count(explode("%",$lprod['interest']))>1) ? round($lamnt*explode("%",$lprod['interest'])[0]/100):$lprod['interest']; }
								
								foreach(json_decode($lprod["payterms"],1) as $des=>$pay){
									$val = explode(":",$pay); $amnt=(count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
									if($val[0]==2){ $pays[$des]=$amnt; }
									if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
									// if($val[0]==4){ $adds[$des]=$amnt; }
									// if($val[0]==5){ $adds[$des]=$amnt; }
								}
								
								# schedule
								if($intrtp=="FR"){
									$totpay=$tpay=$lamnt+($ldur/$lprod['interest_duration']*$intrs); $inst=ceil($totpay/($ldur/$intv));
									$intr=round(($totpay-$lamnt)/($ldur/$intv)); $princ=$inst-$intr; $tprc=$lamnt; $tintr=$totpay-$lamnt;
									$vint = (count($vsd)) ? "varying":$intr; $pinst=ceil($lamnt/($ldur/$intv)); $other=array_sum($pays);
									$arr = array('principal'=>$princ,"interest"=>$vint)+$pays; $perin=$ldur/$intv; $tpy=0;
									
									for($i=1; $i<=$perin; $i++){
										foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); $tpay+=round($sum/$perin); }
										$dy=$disb+($i*$intv*86400); $mon=strtotime(date("Y-M",$dy)); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
										$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $tpay+=$other;
										$brek = ($cut>0) ? array($payat[$i][0]=>$cut)+$arr:$arr; $totpay+=$cut+$other; $tpay+=$cut; $inst=array_sum($brek);
										if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; $inst=array_sum($brek); $cut=0; }
										$sinst = ($inst>$tpay) ? $tpay:$inst; $tpay-=$sinst; $bjs=json_encode($brek,1); $ijs=json_encode(["intr"=>$i],1);
										$svint = ($intrcol=="Accrued" or $dy<time()) ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued" or $dy<time()) ? $intr:0; 
										$qrys[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$ijs')"; $tpy+=$svint;
									}
								}
								else{
									$perin=$ldur/$intv; $pinst=ceil($lamnt/$perin); $tint=$pmo=$totpay=$tpy=0;
									$arr = array('principal'=>$pinst,"interest"=>0)+$pays; $other=array_sum($pays);
									$calc = reducingBal($lamnt,explode("%",$lprod['interest'])[0],$perin);
									
									for($i=1; $i<=$perin; $i++){
										foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); }
										$dy=$disb+($i*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0; 
										$arr['principal']=$calc["principal"][$i]; $pmo+=$cut+$other; $totpay+=$cut;
										$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); 
										$intr = $calc["interest"][$i]; $brek["interest"]=$intr; $inst=array_sum($brek); $bjs=json_encode($brek,1);
										$sinst = $inst; $totpay+=$sinst; $tint+=($intrcol=="Accrued") ? $intr:0; $totpay+=$other; $ijs=json_encode(["intr"=>$i],1);
										$svint = ($intrcol=="Accrued" or $dy<time()) ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued" or $dy<time()) ? $intr:0;
										$qrys[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','0','$svint','$bjs','[]','$ijs')"; $tpy+=$svint;
									}
								}
								
								$bal = ($bal>$tpy) ? $tpy:$bal; $paid=$rem=$tpy-$bal; $cols=$ins=[]; $pdam=($delpd) ? 0:$paid;
								$replace = array("loan"=>$loan,"disbursement"=>$dsd,"time"=>$tym,"expiry"=>$exp,"penalty"=>$pen,"paid"=>$pdam,"balance"=>$bal,
								"status"=>0,"client"=>$name,"loan_product"=>$pid,"client_idno"=>$idno,"phone"=>$fon,"branch"=>$brans[$idno],"loan_officer"=>$ofid,
								"amount"=>$lamnt,"duration"=>$ldur,"clientdes"=>json_encode(["rating"=>"Unrated"],1));
								foreach($replace as $col=>$rec){ $cols[]="`$col`"; $ins[]="'$rec'"; }
								$indata = implode(",",$ins); $incols=implode(",",$cols); 
								
								if($db->execute(2,"INSERT INTO `org".$cid."_loans` ($incols) VALUES ($indata)")){
									$db->execute(2,"INSERT INTO `org".$cid."_schedule` VALUES ".implode(",",$qrys)); $no++;
									$db->execute(2,"UPDATE `org".$cid."_clients` SET `status`='1',`cycles`=(cycles+1) WHERE `idno`='$idno'");
									if($exp>time()){
										recon_daily_pays($lid); 
										if($intrcol=="Cash"){ setInterest($lid); }
									}
									
									if($paid>0 && $rem>0){
										$sql = $db->query(2,"SELECT *FROM `org".$cid."_schedule` WHERE `loan`='$lid' ORDER BY `day` ASC");
										foreach($sql as $row){
											$rid=$row["id"]; $bal=$row["balance"]; $val=($rem>$bal) ? $bal:$rem; $rem-=$val; $tpd=$row["paid"]+$val; $rbal=$bal-$val;
											if($rbal<=0 && $delpd){ $db->execute(2,"DELETE FROM `org".$cid."_schedule` WHERE `id`='$rid'"); }
											else{ $db->execute(2,"UPDATE `org".$cid."_schedule` SET `balance`='$rbal',`paid`='$tpd' WHERE `id`='$rid'"); }
											if($rem<=0){ break; }
										}
									}
								}
							}
							else{ $errors['prodfake'][]=$prod; }
						}
						else{ $errors['invalid'][]=$idno; }
					}
					else{ $errors['incomplete'][]="Row ".($akey+1); }
				}
				
				if(count($errors)){ file_put_contents("import_errors.dll",json_encode($errors,1)); }
				$all=count($data); $cond=($no==$all) ? "all":$no; $cond=($no==0) ? "none":$cond;
				if($no){ savelog($sid,"Imported $no/$all client loans"); }
				echo "success:$cond:$all";
			}
			else{ echo "Failed to open file! Try again later"; }
		}
	}
	
	ob_end_flush();
?>