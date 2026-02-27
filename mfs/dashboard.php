<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	$path = ($_SERVER['HTTP_HOST']=="localhost") ? "/mfs":"";
	$licss = "padding:8px 0px;border-bottom:1px solid #dcdcdc;list-style:none;color:#191970";
	$cardcss = "width:45px;height:45px;border-radius:50%;font-size:30px;color:#fff;text-align:center;float:right";
	
	$me = staffInfo($sid); $access=$me['access_level']; $cnf=json_decode($me["config"],1);
	$lvs=round($me['leaves']/86400); $leaves=($lvs==1) ? "$lvs Leave Day":"$lvs Leave Days";
	$defacc = array("hq"=>"Corporate","region"=>"Regional","branch"=>"Branch","portfolio"=>"Portfolio");
	
	$sett["advanceapplication"]=1; $mfi=mficlient("syszn$cid");
	$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid'");
	foreach($sql as $row){ $sett[$row['setting']]=prepare($row['value']); }
	
	$apply = (1) ? "<a href='javascript:void(0)' onclick=\"popupload('hr/leaves.php?apply')\">Apply Leave</a>&nbsp":"";
	$apply.= ($sett["advanceapplication"] && in_array("accounting",$mfi)) ? " | &nbsp; <a href='javascript:void(0)' onclick=\"popupload('accounts/advance.php?apply')\">Request Advance</a>":"";
	
	$bnames = [0=>"Corporate"]; $totbrans=$totstaff=0; $actstf=[];
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			if($row["status"]==0){ $totbrans++; }
		}
	}
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE NOT `position`='USSD'"); $staffs[0]="System";
	foreach($res as $row){
		$id=$row['id']; $userbranch[$id]=$bnames[$row['branch']]; $staffs[$id]=prepare(ucwords($row['name'])); $def=json_decode($row['config'],1); 
		$phones[$id]=$row['contact']; $emails[$id]=$row['email']; $posts[$id]=ucwords(prepare($row['position'])); $sbr[$id]=$row["branch"];
		$icon = ($row['gender']=="male") ? "male.png":"fem.png"; $prof=(isset($def["profile"])) ? $def['profile']:"none";
		if($id==$sid){ $profiles[$id] = ($prof=="none") ? "$path/assets/img/$icon":"data:image/jpg;base64,".getphoto($prof); }
		if($row["status"]==0 && $row["position"]!="assistant"){ $totstaff++; $actstf[]="'$id'"; }
	}
	
	#collection progress
	$tdy = strtotime("Today"); $pay=$amnts=0;
	$leo = (defined("COLL_PROGRESS_DAY")) ? COLL_PROGRESS_DAY:$tdy; 
	$cond = ($me['access_level']=="hq") ? "":"AND sd.officer='$sid'";
	$cond = ($me['access_level']=="branch") ? "AND ln.branch='".$me['branch']."' GROUP BY sd.officer":$cond;
	$cond = ($me['access_level']=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"ln.branch"):$cond;
	$stbl = "org".$cid."_schedule"; $ltbl = "org".$cid."_loans"; $ctbl = "org".$cid."_clients";
	$istbl_loans = $db->istable(2,$ltbl); $istbl_clients=$db->istable(2,$ctbl);
	
	if($me["position"]=="collection agent"){
		$cond.= " AND JSON_EXTRACT(ln.clientdes,'$.agent')='$sid'";
	}
	
	$res = ($istbl_loans) ? $db->query(2,"SELECT *,SUM(sd.amount) AS total,SUM(sd.paid) AS pays FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln
	ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.day='$leo' AND (ln.balance>0 or ln.status>$tdy) $cond"):null;
	if($res){
		foreach($res as $row){ $pay+=$row['pays']; $amnts+=$row['total']; }
	}
	
	$width = ($pay>0) ? round(($pay*100)/$amnts,1).'%':"0%";
	$topup = (SMS_TOPUP) ? "<button class='bts' style='float:right;font-size:15px' onclick=\"popupload('bulksms.php?topup')\"><i class='bi-upload'></i> Topup</button>":"";
	
	$perms = getroles(explode(",",$me['roles']));
	$bulksms = (in_array("access bulksms",$perms)) ? "<hr style='margin-bottom:0px'>
	<div style='padding:5px'>
		<table cellpadding='5' cellspacing='7' style='width:100%'>
			<tr><td style='width:60px'><i class='fa fa-envelope-o' style='font-size:40px;color:#3498db;'></i></td>
			<td><h3 style='color:#2f4f4f;font-size:17px;margin-top:10px'>Bulk SMS Balance</h3>
			<h2 style='color:green;font-size:22px;margin:0px'> ".smsbalance()." $topup</h2></td></tr>
		</table>
	</div>":"<br>";
	
	# client analysis
	$cond = ($access=="branch") ? "AND ln.branch='".$me["branch"]."'":"";
	$cond = ($access=="portfolio") ? "AND ln.loan_officer='$sid'":$cond;
	$cond = ($access=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"ln.branch"):$cond; $cond2=$cond;
	if($me["position"]=="collection agent"){
		$cond.= " AND JSON_EXTRACT(ln.clientdes,'$.agent')=$sid";
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE JSON_EXTRACT(clientdes,'$.agent')=$sid");
		if($sql){
			foreach($sql as $row){ $ids[]="ln.idno='".$row['client_idno']."'"; }
			$cond2.= " AND (".implode(" OR ",$ids).")"; 
		}
	}
	
	$lnpie = [["Task"=>"Client Loans"]]; $prodpie=[["Task"=>"Loan Products"]]; $brnpie=[["Task"=>"Branch Loans"]]; $arrears=$arrln=$aprc=$tprc=$par=0;
	if($istbl_loans && in_array("view loans",$perms)){
		$fork = new DBAsync($sid);
		$qry = $db->query(2,"SELECT DISTINCT sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 AND ln.balance>0 AND sd.day<$tdy $cond");
		$fork->add(2,"SELECT SUM(sd.balance) AS arrears,SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ 
		FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 AND sd.day<$tdy $cond");
		$fork->add(2,"SELECT SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ 
		FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 $cond");
		$res = $fork->run(); 
		$arrears = (isset($res[0][0]['arrears'])) ? intval($res[0][0]['arrears']) : 0; 
		$aprc = (isset($res[0][0]['princ'])) ? intval($res[0][0]['princ']) : 0; 
		$arrln = ($qry) ? count($qry) : 0; 
		$tprc = (isset($res[1][0]['princ'])) ? intval($res[1][0]['princ']) : 0; 
		$par = ($tprc) ? round(($aprc/$tprc)*100,2) : 0;
		
		$qri = $db->query(2,"SELECT *,COUNT(id) AS total FROM `$ltbl` AS ln WHERE (balance+penalty)>0 $cond GROUP BY amount");
		if($qri){
			foreach($qri as $row){
				$lnpie[]=[$row['amount']." Loans"=>intval($row['total'])];
			}
		}
		
		$qri = $db->query(2,"SELECT `loan_product`,COUNT(id) AS total FROM `$ltbl` AS ln WHERE (balance+penalty)>0 $cond GROUP BY loan_product");
		if($qri){
			foreach($qri as $row){
				$chk = $db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='".$row['loan_product']."'");
				if($chk){
					$prodpie[]=[prepare(ucfirst($chk[0]["product"]))=>intval($row['total'])];
				}
			}
		}
		
		$sqr = $db->query(2,"SELECT DISTINCT `branch` FROM `$ltbl` WHERE balance>0"); $tbr=($sqr) ? count($sqr):0;
		$gcol = (($access=="hq" or $access=="region") && $tbr>1) ? "branch":"loan_officer";
		$vbr = ($gcol=="loan_officer" && $tbr==1) ? $sqr[0]['branch']:$me["branch"]; $cnd=($gcol=="loan_officer") ? "AND ln.branch='$vbr'":"";
		$qri = $db->query(2,"SELECT `$gcol`,SUM(balance) AS tsum FROM `$ltbl` AS ln WHERE balance>0 $cnd GROUP BY $gcol");
		if($qri){ 
			foreach($qri as $row){
				$name = ($gcol=="branch") ? $bnames[$row[$gcol]]:$staffs[$row[$gcol]];
				$brnpie[]=[$name=>intval($row['tsum'])];
			}
		}
	}
	
	$clpie = [["Task"=>"Client Cycles"]]; $activecl=$dormcl=$totcl=$penon=0;
	if($istbl_clients && in_array("view clients",$perms)){
		$qr1 = $db->query(2,"SELECT `status`,COUNT(id) AS tot FROM `$ctbl` AS ln WHERE `status`<2 $cond2 GROUP BY `status`");
		if($qr1){
			foreach($qr1 as $row){
				$totcl+=intval($row['tot']);
				if($row['status']==0){ $dormcl=intval($row['tot']); }
			}
		}
		
		$qri = $db->query(2,"SELECT cycles,COUNT(id) AS total FROM `$ctbl` AS ln WHERE `status`='1' $cond2 GROUP BY cycles");
		if($qri){
			foreach($qri as $row){
				$txt = ($row['cycles']==1) ? "1 Cycle":$row['cycles']." Cycles"; 
				$clpie[] = [$txt=>intval($row['total'])];
			}
		}
	}
	
	$lnjsn = json_encode($lnpie,1); $cljsn=json_encode($clpie,1); $prdjsn=json_encode($prodpie,1); $brnjsn=json_encode($brnpie,1);
	
	# loan analysis
	$tundisb=$totundsb=$mtd=$ytd=$tmtd=$tytd=$tpv=$gcol=$otc=$otcp=$gcp=$tolb=$tnlb=0; $graph=[]; $pfdata=$dgtitle="";
	if($db->istable(2,"org$cid"."_loantemplates") && $me["position"]!="collection agent"){
		$sq1 = $db->query(2,"SELECT COUNT(id) AS tot FROM `org$cid"."_loantemplates` WHERE `status`<9 $cond");
		$tundisb = ($sq1) ? 0 : 0; $totundsb = ($sq1) ? intval($sq1[0]['tot']) : 0;
	}
	
	if($istbl_loans){
		$jan1 = strtotime(date("Y")."-Jan"); $mon1=strtotime(date("Y-M")); $now=time(); 
		$sql = $db->query(2,"SELECT COUNT(ln.id) AS tot FROM `$ltbl` AS ln WHERE ln.penalty>0 AND ln.balance='0' $cond");
		$res2 = $db->query(2,"SELECT COUNT(ln.id) AS total FROM `$ltbl` AS ln WHERE (ln.balance+ln.penalty)>0 $cond");
		
		if($me["position"]=="collection agent"){
			$ljn = "INNER JOIN `org$cid"."_loans` AS ln ON lt.id=ln.tid";
			$sq1 = $db->query(2,"SELECT COUNT(lt.id) AS tot FROM `org$cid"."_loantemplates` AS lt $ljn WHERE lt.status BETWEEN $jan1 AND $now $cond");
			$sq2 = $db->query(2,"SELECT COUNT(lt.id) AS tot FROM `org$cid"."_loantemplates` AS lt $ljn WHERE lt.status BETWEEN $mon1 AND $now $cond");
		}
		else{
			$sq1 = $db->query(2,"SELECT COUNT(ln.id) AS tot FROM `org$cid"."_loantemplates` AS ln WHERE ln.status BETWEEN $jan1 AND $now $cond");
			$sq2 = $db->query(2,"SELECT COUNT(ln.id) AS tot FROM `org$cid"."_loantemplates` AS ln WHERE ln.status BETWEEN $mon1 AND $now $cond");
		}
		
		$sq3 = $db->query(2,"SELECT SUM(balance+penalty) AS tsum,COUNT(id) AS tot FROM `$ltbl` AS ln WHERE (balance+penalty)>0 $cond");
		$mtd = ($sq2) ? intval($sq2[0]['tot']):0; $tolb=($sq3) ? intval($sq3[0]['tsum']):0; $tmtd=($sq2) ? intval($sq2[0]['tot']):0; 
		$ytd = ($sq1) ? intval($sq1[0]['tot']):0; $tytd=($sq1) ? intval($sq1[0]['tot']):0; $tnlb=($sq3) ? intval($sq3[0]['tot']):0; 
		$penon = ($sql) ? intval($sql[0]["tot"]):0; $activecl=($res2) ? intval($res2[0]["total"]):0; $ad1=$ad2=""; $ats=0;
		
		# disbursement analysis
		$sqr = $db->query(1,"SELECT `id` FROM `loan_products` WHERE `client`='$cid' AND `category`='app'");
		if($sqr){
			foreach($sqr as $rw){ $id=$rw["id"]; $pids[]="`loan_product`='$id'"; }
			$ad1="AND NOT (".implode(" OR ",$pids).")"; $ad2="AND (".implode(" OR ",$pids).")";
		}
		
		$gtext = (defined("DISBURSEMENT_DESC")) ? DISBURSEMENT_DESC:["System","App"];
		$stack[] = array("Date",$gtext[0],$gtext[1],"Totals");
		for($i=$mon1; $i<=$tdy; $i+=86400){
			$dto = $i+86399; $dy=date("Y-m-d",$i); $port=["period"=>$dy]; $dsb2=0;
			$res1 = $db->query(2,"SELECT SUM(amount) AS tsum,COUNT(id) AS tot FROM `$ltbl` AS ln WHERE `disbursement` BETWEEN $i AND $dto $cond $ad1");
			if($ad2){
				$res2 = $db->query(2,"SELECT SUM(amount) AS tsum FROM `$ltbl` AS ln WHERE `disbursement` BETWEEN $i AND $dto $cond $ad2");
				$dsb2 = ($res2) ? intval($res2[0]['tsum']):0; $ats+=$dsb2;
			}
			
			$dsb1 = ($res1) ? intval($res1[0]['tsum']):0; $dtot1=($res1) ? intval($res1[0]['tot']):0;
			$port["Disbursement"]=$dsb1; $port["Loans"]=$dtot1; $graph[]=$port; $stack[]=array($dy,$dsb1,$dsb2,$dsb1+$dsb2); 
		}
	}
	
	if($ats){ $dgraph = "drawStack('dvdis',$('#dvdis').height(),'".date("F Y")." Disbursements',".json_encode($stack,1).",'Disbursement Amount','');"; }
	else{
		$dgtitle = "<h5 style='color:#2f4f4f;font-size:17px'>".date("F Y")." Disbursements</h5>";
		$grpjsn = json_encode($graph,1); $syms=json_encode(["Disbursement","Loans"],1);
		$dgraph = "drawLine('dvdis','period',$syms,$grpjsn,$syms)";
		if(defined("DISB_GRAPH")){
			$dgraph = (DISB_GRAPH=="bar") ? "drawBar('dvdis','period',$syms,$grpjsn,$syms)":$dgraph;
		}
	}
	
	# collection rate
	$tmon = strtotime(date("Y-M")); $lmon=strtotime(date("Y-M",$tmon-86400));
	if($db->istable(2,"processed_payments$cid") && $istbl_loans){
		$mon = strtotime(date("Y-M")); $now=time(); $fork = new DBAsync($sid); $monend=monrange(date("m",$lmon),date("Y",$lmon))[1];
		$sq1 = $db->query(2,"SELECT SUM(paid+balance) AS tsum,SUM(paid) AS tpd FROM `$ltbl` AS ln WHERE `disbursement` BETWEEN $mon AND $now $cond");
		$sq2 = $db->query(2,"SELECT SUM(paid+balance) AS tsum,SUM(paid) AS tpd FROM `$ltbl` AS ln WHERE `disbursement` BETWEEN $lmon AND $monend $cond");
		$tpv1 = intval($sq1[0]['tsum']); $tpv2=intval($sq2[0]['tsum']); $gcol=intval($sq2[0]['tpd']); $otc=intval($sq1[0]['tpd']); 
		$otcp = ($tpv1>0) ? round($otc/$tpv1*100,2):0; $gcp=($tpv2>0) ? round($gcol/$tpv2*100,2):0;
	}
	
	# performance
	if(in_array("analytics",$mfi)){
		$pdy = (isset($sett["perflastdy"])) ? $sett["perflastdy"]:1;
		if(!$db->istable(2,"targets$cid")){
			$tcols = ["branch"=>"INT","officer"=>"INT","month"=>"INT","year"=>"INT","results"=>"TEXT","data"=>"TEXT"];
			foreach(array_keys(KPI_LIST) as $col){ $tcols[$col]=(in_array($col,["loanprods","mtd"])) ? "TINYTEXT":"INT"; }
			$db->createTbl(2,"targets$cid",$tcols); 
		}
		
		$sql = $db->query(2,"SELECT MAX(month) AS mon FROM `targets$cid`"); $smd=(intval(date("d"))>1) ? 1:2;
		$mon = ($sql) ? $sql[0]['mon']:strtotime(date("Y-M")); $pmn=strtotime(date("Y-M",strtotime("-$smd month")));
		$pmon = ($pmn==strtotime(date("Y-M"))) ? strtotime(date("Y-M",$pmn-1)):$pmn; $tdy=strtotime(date("Y-M-d"));
		$frm = strtotime(date("Y-M")); $to=$tdy; $from=($pdy) ? $frm:monrange(date("m",$pmon),date("Y",$pmon))[1]; 
		$startday = date("M-d",$from); $enday=date("M-d",$to); $tmon=strtotime(date("Y-M"));
		$smon = (intval(date("d"))==1) ? strtotime(date("Y-M",$tmon-86400)):$tmon;
		$kpis = (isset($sett["perfkpis"])) ? json_decode($sett["perfkpis"],1):KPI_LIST;
		
		$data=$grouping=$sign=$record=$thead=$refined=$sums=$tgsum=[]; $dvs=0;
		$qri = $db->query(2,"SELECT *FROM `targets$cid` WHERE `month`='$smon' AND `officer` IN (".implode(",",$actstf).") GROUP BY `officer`");
		if($qri){
			foreach($qri as $row){
				foreach($row as $col=>$v){
					if(isset($kpis[$col])){
						if(isJson($v)){
							foreach(json_decode($v,1) as $k=>$vl){
								if(isset($tgsum[$k])){ $tgsum[$k]+=$vl; }else{ $tgsum[$k]=$vl; }
							}
						}
						else{
							if(isset($tgsum[$col])){ $tgsum[$col]+=intval($v); }else{ $tgsum[$col]=intval($v); }
						}
					}
				}
			}
			
			foreach($qri as $row){
				$stid=$row['officer']; $rest=json_decode($row["results"],1); $pdata=json_decode($row["data"],1); 
				$perc=$actuals=$targs=$ranks=$sign=$adds=[];
				
				foreach($rest as $col=>$val){
					if(is_array($val)){
						foreach($val as $k=>$v){ $actuals[$k]=$v; }
					}
					else{ $actuals[$col]=$val; }
				}
				
				foreach($kpis as $col=>$one){
					if($one["rank"]){
						if($col=="loanprods"){
							$dtgs = ($row[$col]) ? json_decode($row[$col],1):[];
							foreach($one["list"] as $dcol){
								$targs[$dcol]=(isset($dtgs[$dcol])) ? $dtgs[$dcol]:0; $ranks[$dcol]=$one["form"]; $sign[$dcol]="%";
								if(!isset($actuals[$dcol])){ $actuals[$dcol]=0; }
							}
						}
						else{
							$targs[$col]=$row[$col]; $ranks[$col]=$one["form"]; $sign[$col]="%";
							if(!isset($actuals[$col])){ $actuals[$col]=0; }
						}
					}
				}
				
				if(array_sum($tgsum)>0){
					if(array_sum($targs)<=0){ continue; }
				}
				
				foreach($ranks as $col=>$fom){
					$grouping[$col] = "DESC";
					if($fom=="value"){
						$perc[$col]=$actuals[$col]; $sign[$col]=""; $thead[$col]=[$enday];
						if(in_array($col,["arrears"])){ $grouping[$col]="ASC"; }
					}
					elseif($fom=="A/T"){ $perc[$col]=($targs[$col]) ? round($actuals[$col]/$targs[$col]*100,1):0; $thead[$col]=["Target",$enday]; }
					elseif($fom=="E-S/S"){
						if($pdata){
							$mdy = min(array_keys($pdata)); $max=max(array_keys($pdata));
							if(!isset($pdata[$max][$col])){
								$pdata = setTarget($stid,$mon,$col); $mdy=min(array_keys($pdata)); $max=max(array_keys($pdata));
							}
							if(!isset($pdata[$mdy][$col])){
								foreach($pdata as $one){
									if(isset($one[$col])){ $val=$one[$col]; break; }
								}
							}
							else{ $val = $pdata[$mdy][$col]; }
							
							$dval = ($val) ? round(($pdata[$max][$col]-$val)/$val*100,1):0; $thead[$col]=[$startday,$enday];
							$perc[$col] = ($dval>0) ? "-$dval":str_replace("-","",$dval);
						}
						else{ $perc[$col]=0; $thead[$col]=[$startday,$enday]; }
					}
					elseif($fom=="E-T/T"){
						if($pdata){
							$max = max(array_keys($pdata)); 
							if(!isset($pdata[$max][$col])){ $pdata=setTarget($stid,$mon,$col); $max=max(array_keys($pdata)); }
							$val = $pdata[$max][$col]; $tag=$targs[$col];
							$perc[$col] = ($tag) ? round(($val-$tag)/$tag*100,1):0; $thead[$col]=[$enday,"Target"];
						}
						else{ $perc[$col]=0; $thead[$col]=[$enday,"Target"]; }
					}
					elseif($fom=="S-E/S-T"){
						$thead[$col]=[$startday,"Target",$enday];
						if($pdata){
							$mdy = min(array_keys($pdata)); $max=max(array_keys($pdata));
							if(!isset($pdata[$max][$col])){
								$pdata = setTarget($stid,$mon,$col); $mdy=min(array_keys($pdata)); $max=max(array_keys($pdata));
							}
							if(!isset($pdata[$mdy][$col])){
								foreach($pdata as $one){
									if(isset($one[$col])){ $val=$one[$col]; break; }
								}
							}
							else{ $val = $pdata[$mdy][$col]; }
							
							$dv1 = $val-$pdata[$max][$col]; $dv2=$val-$targs[$col]; $adds[$col]=[$val,$pdata[$max][$col]];
							$perc[$col] = ($dv2) ? round($dv1/$dv2*100,1):0;
						}
						else{ $perc[$col]=0; $adds[$col]=[0,0]; }
					}
				}
				
				$sums[$stid] = round(array_sum($perc)/count($perc),1); 
				foreach($perc as $col=>$val){
					if(isset($adds[$col])){
						$perf[$col] = array($adds[$col][0],$targs[$col],$adds[$col][1],$val.$sign[$col]);
					}
					else{ $perf[$col] = array($targs[$col],$actuals[$col],$val.$sign[$col]); }
				}
				$record[$stid]=$perf; $data[$stid]=$perc;
			}
		}
		
		if(count($data)){
			foreach($data as $uid=>$one){
				foreach($one as $col=>$val){ $pull[$col][$uid]=$val; }
			}
			
			foreach($pull as $col=>$one){
				if(count($one)){
					$gtp = $grouping[$col];
					if($gtp=="DESC"){ arsort($one,SORT_NUMERIC); $refined[$col]=$one; }
					else{ asort($one,SORT_NUMERIC); $refined[$col]=$one; }
				}
			}
		}
		
		$ptotal=$positions=$numbers=$nums=[];
		foreach($refined as $col=>$one){
			$pos=0; 
			foreach($one as $stid=>$val){
				$pos++; $numbers[$pos]=$val;
				$no=(in_array($val,$numbers)) ? array_search($val,$numbers):$pos;
				if(isset($ptotal[$stid])){ $ptotal[$stid]+=$no; $positions[$stid][$col]=$no; $nums[$stid]+=$no; }
				else{ $ptotal[$stid]=$no; $positions[$stid][$col]=$no; $nums[$stid]=$no; }
			}
		}
		
		$cond =1; $no=0; $numbers=$fpoints=[]; $ptrs="";
		foreach($sign as $val){ 
			if($val!="%"){ $cond=0; break; }
		}
		
		# generate positions
		$tx1 = ($cond) ? "Average":"Points";
		$final = ($cond) ? $sums:$nums; $gbr=[];
		if($cond){ arsort($final); }else{ asort($final); }
		if($access=="region" && isset($cnf["region"])){
			$qri = $db->query(1,"SELECT *FROM `regions` WHERE `id`='".$cnf["region"]."'");
			$rbrn = ($qri) ? json_decode($qri[0]["branches"],1):[];
			foreach($rbrn as $bid){
				foreach($sbr as $u=>$br){
					if($br==$bid){ $gbr[]=$u; }
				}
			}
		}
		
		foreach($final as $stid=>$points){
			$name=(isset($staffs[$stid])) ? $staffs[$stid]:"Name"; $no++; $numbers[$no]=$points; 
			$pos = (in_array($points,$numbers)) ? array_search($points,$numbers):$no; $arrange[$stid]=$pos;
			$val = ($cond) ? "$points%":$points; $all=count($final); $fpoints[$stid]=$val;
			if($access=="branch"){ 
				$ptrs.= ($userbranch[$sid]==$userbranch[$stid] or $arrange[$stid]<=2) ? "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>":"";
			}
			elseif($access=="portfolio"){
				$ptrs.= ($stid==$sid or $arrange[$stid]<=2) ? "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>":"";
			}
			elseif($access=="region"){
				$ptrs.= (in_array($stid,$gbr) or $arrange[$stid]<=2) ? "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>":"";
			}
			else{ $ptrs.= "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>"; }
		}
		
		# get performance indicators
		$ths1=$ths2=$ltrs=""; $cols=$ranking=$titles=$prods=[];
		foreach($kpis as $col=>$one){
			if($one["rank"]){
				if($col=="loanprods"){
					foreach($one["list"] as $dcol){ $ranking[]=$dcol; $titles[$dcol]=prepare(ucwords($dcol)); $prods[]=$dcol; }
				}
				else{ $ranking[]=$col; $titles[$col]=prepare($one["title"]); }
			}
		}
	
		$defrank=$ranking; $jn=$np=0;
		foreach($defrank as $rank){
			$defhd = (in_array($rank,["performing","loanbook","arrears"])) ? array($startday,"Target",$enday):array("Target",$enday);
			$dcol = (in_array($rank,$prods) && !isset($kpis[$rank])) ? "loanprods":$rank; $jn++;
			$foml = $kpis[$dcol]["form"]; $sgn = (isset($sign[$rank])) ? $sign[$rank]:"";
			$stl = ($sgn=="%") ? "Score":"Actual"; $def=($foml=="value") ? array($enday):$defhd;
			$tarr = (isset($thead[$rank])) ? $thead[$rank]:$def; 
			$span = count($tarr)+2; $span+=(count($defrank)==$jn) ? 1:0; $no=0;
			foreach($tarr as $col){ $ths2.="<td>$col</td>"; $no++; }
			$ths1.= "<td colspan='$span'>$titles[$rank]</td>"; 
			$ths2.= "<td>$stl</td><td>Pos</td>"; $cols[$rank]=$no+1;
			$ths2.= (count($defrank)==$jn) ? "<td>Avg</td>":"";
		}
		
		foreach($record as $stid=>$des){ $tds=""; $np=0;
			foreach($defrank as $rank){
				for($i=0; $i<$cols[$rank]; $i++){
					$css = ($i==$cols[$rank]-1) ? "background:#f0f0f0":"";
					$tds.= "<td style='text-align:center;$css'>".$des[$rank][$i]."</td>"; 
				}
				
				$pos = (isset($positions[$stid][$rank])) ? $positions[$stid][$rank]:1;
				$tds.= "<td style='background:#dcdcdc;text-align:center'>$pos</td>"; $np++;
				$tds.= (count($defrank)==$np) ? "<td style='background:#ADD8E6;text-align:center'>$fpoints[$stid]</td>":""; 
			}
			
			if($access=="branch"){ 
				$ltrs.=($userbranch[$sid]==$userbranch[$stid] or $arrange[$stid]<=2) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":"";
			}
			elseif($access=="region"){
				$ltrs.=(in_array($stid,$gbr) or $arrange[$stid]<=2) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":"";
			}
			elseif($access=="portfolio"){
				$ltrs.=($stid==$sid or $arrange[$stid]<=2) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":"";
			}
			else{ $ltrs.=(isset($staffs[$stid])) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":""; }
		}
		
		$pfdata = "<div class='col-12 col-lg-6 col-xl-6 mb-3'>
			<div class='bg-white shadow-sm' style='max-height:450px;overflow:auto;min-height:300px;padding:10px'>
				<h5 style='color:#2f4f4f;font-size:17px'>".date("F Y",$mon)." Performance</h5>
				<table style='width:100%;font-size:15px;font-family:signika negative' cellpadding='5' class='table-striped'>
					<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-family:cambria'><td>Staff</td><td>$tx1</td><td>Position</td></tr> $ptrs
				</table>
			</div>
		</div>
		<div class='col-lg-12 col-md-12 col-sm-12' style='padding:10px'>
			<div class='bg-white shadow-sm' style='padding:8px;overflow:auto;width:100%'>
				<h3 style='color:#191970;font-size:20px'>Performance Indicators</h3>
				<div style='width:100%;overflow:auto'>
					<table class='table-bordered' style='width:100%;min-width:800px;font-size:14px;font-family:cambria' cellpadding='4'>
						<tr style='background:#4682b4;color:#fff;font-weight:bold;text-align:center;text-shadow:0px 1px 0px #000;font-size:13px'><td>Staff</td>$ths1</tr>
						<tr valign='top' style='background:#B0C4DE;color:#191970;font-weight:bold;text-align:center;font-size:12px'><td></td>$ths2</tr> $ltrs
					</table>
				</div>
			</div>
		</div>";
	}
	
	$dmtd=fnum($tmtd); $dytd=fnum($ytd); $sotc=fnum($otc); $sgcol=fnum($gcol); $smtd=fnum($mtd); $sarr=fnum($arrears); $spar=fnum($aprc)."/".fnum($tprc);
	if(defined("HIDE_USERS_DASHBOARD")){
		if(in_array($me["position"],HIDE_USERS_DASHBOARD)){ $dmtd=$dytd=$sotc=$sgcol=$smtd=$sarr=$spar="--^--"; }
	}
	
	$olbdv = ($access=="hq") ? "<h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>Current OLB</span><br> ".fnum($tolb)."<br>
	<span style='font-size:15px;color:#AFEEEE'><i class='fa fa-circle'></i> ".fnum($tnlb)." Loans</span></h3>":
	"<h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>Disbursement YTD</span><br> ".fnum($ytd)."<br>
	<span style='font-size:15px;color:#AFEEEE'><i class='fa fa-circle'></i> ".fnum($tytd)." Loans</span></h3>";
	
	$dsbdv = ($access=="hq") ? "<tr valign='top'><td style='width:50%;background:#136c68;color:#fff'><h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>
	Disbursement YTD</span><br> ".fnum($ytd)."<br><span style='font-size:15px;color:#AFEEEE'><i class='fa fa-circle'></i> ".fnum($tytd)." Loans</span></h3></td>
	<td><p>Undisbursed Loans ($totundsb)</p><hr><h2 style='color:#191970;font-size:18px;margin:0px'>Ksh ".fnum($tundisb)." <a href='javascript:void(0)' style='float:right;font-size:15px' 
	onclick=\"loadpage('loans.php?disbursements')\"><i class='bi-arrow-return-right'></i> View</a></h2></td></tr>":
	"<tr><td style='width:60px'><i class='bi-cash-coin' style='font-size:40px;color:#3498db;'></i></td>
	<td><h3 style='color:#2f4f4f;font-size:17px;margin-top:10px'>Undisbursed Loans ($totundsb)</h3>
	<h2 style='color:#191970;font-size:20px;margin:0px'>KES ".fnum($tundisb)." <button class='bts' style='float:right;font-size:15px' 
	onclick=\"loadpage('loans.php?disbursements')\"><i class='bi-arrow-return-right'></i> View</button></h2></td></tr>";
	
	echo "<div class='container-fluid' style='padding:0px'>
		<div class='row'>
			<div class='col-12 col-lg-6 col-xl-4 mb-3'>
				<div class='bg-white shadow-sm' style='border:1px solid #f0f0f0'>
					<table style='width:100%;font-family:signika negative' cellpadding='8'>
						<tr><td colspan='2' style='border-bottom:1px solid #f0f0f0;background:;'><h3 style='color:#4682b4;font-size:20px'>Welcome $staffs[$sid]</h3></td></tr>
						<tr valign='top'><td style='max-width:40%'><img src='$profiles[$sid]' style='max-height:150px;max-width:100%'></td>
						<td><li style='$licss'><i class='bi-award'></i> $posts[$sid]</li>
							<li style='$licss'><i class='bi-diagram-3-fill'></i> $userbranch[$sid] Branch</li>
							<li style='$licss'><i class='fa fa-bed'></i> $leaves</li>
							<li style='$licss'><i class='fa fa-eye'></i> ".$defacc[$access]." Access</li>
						</td></tr>
						<tr><td colspan='2' style='text-align:right;background:;'>$apply</td></tr>
					</table> $bulksms
				</div>
			</div>
			<div class='col-12 col-lg-6 col-xl-4 mb-3'>
				<table cellpadding='5' style='width:100%;font-family:signika negative'>
					<tr><td colspan='2'><div class='bg-white shadow-sm'>
						<table style='width:100%' cellpadding='10'>
							<tr><td><h5 style='color:grey;margin:0px'>".date('d-m-Y',$leo)." Collections</h5></td></tr>
							<tr><td><div class='progbar' style='float:;height:20px;min-width:250px;box-shadow:inset 1px 0px 5px #ccc;border-radius:15px;margin-bottom:7px'>
								<div style='width:$width;background:#3CB371;height:100%;border-radius:15px;font-size:12px;line-height:20px;color:#fff;
								text-align:center;text-shadow:0px 1px 1px #000'>$width</div>
							</div></td></tr>
						</table>
					</div></td></tr>
					<tr><td style='width:50%'><div class='shadow-sm' style='min-height:150px;background:#483D8B;padding:7px;color:#fff'>
						<h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>Disbursement MTD</span><br> 
						$smtd<br><span style='font-size:15px;color:#AFEEEE'><i class='fa fa-circle'></i> $dmtd Loans</span></h3> 
						<hr color='#f0f0f0' style='margin:5px 0px'> $olbdv
					</div></td>
					<td><div class='shadow-sm' style='min-height:150px;background:#4d5966;padding:7px;color:#fff'>
						<h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>".date("M Y")." OTC</span><br> 
						$otcp% <br><span style='font-size:15px;color:#AFEEEE'>KES $sotc</span></h3> <hr color='#f0f0f0' style='margin:5px 0px'>
						<h3 style='font-size:23px;text-align:right'><span style='color:#FFDDA9;font-size:14px'>".date("M Y",$lmon)." Collection</span><br> $gcp%<br>
						<span style='font-size:15px;color:#AFEEEE'>KES $sgcol</span></h3>
					</div></td></tr>
					<tr><td colspan='2'><div class='bg-white shadow-sm' style='padding:7px'>
						<table cellpadding='5' cellspacing='7' style='width:100%'>$dsbdv</table>
					</div></td></tr>
				</table>
			</div>
			<div class='col-12 col-lg-6 col-xl-4 mb-3'>
				<table cellpadding='5' style='width:100%;font-family:signika negative'>
					<tr><td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#4682b4;font-size:30px'>".fnum($totcl)."</h1><span style='color:grey;font-size:14px'>Total Clients</span></td>
							<td><div style='$cardcss;background:grey'><i class='bi-people'></i></div></td>
						</tr></table>
					</div></td>
					<td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#20B2AA;font-size:30px'>".fnum($activecl)."</h1><span style='color:grey;font-size:14px'>Active Clients</span></td>
							<td><div style='$cardcss;background:#778899'><i class='bi-people-fill'></i></div></td>
						</tr></table>
					</div></td></tr>
					<tr><td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#FFA07A;font-size:30px'>".fnum($dormcl)."</h1><span style='color:grey;font-size:14px'>Dormant clients</span></td>
							<td><div style='$cardcss;background:#BA55D3'><i class='bi-alarm'></i></div></td>
						</tr></table>
					</div></td>
					<td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:green;font-size:30px'>".fnum($activecl-($arrln+$penon))."</h1><span style='color:grey;font-size:14px'>Performing</span></td>
							<td><div style='$cardcss;background:#66CDAA'><i class='fa fa-balance-scale' style='font-size:25px'></i></div></td>
						</tr></table>
					</div></td></tr>
					<tr><td colspan='2'><div style='background:#DB7093;padding:5px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#fff;font-size:30px'>$sarr</h1>
							<span style='color:#f0f0f0;font-size:14px'>Loan Arrears (Ksh)</span></td>
							<td><div style='font-size:14px;float:right;color:#FFE4B5;font-weight:bold;font-family:cambria'>$par% PAR<br>
							<span style='font-size:13px'>$spar</span></div></td>
						</tr></table>
					</div></td></tr>
					<tr><td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#800080;font-size:30px'>".fnum($totbrans)."</h1><span style='color:grey;font-size:14px'>Active Branches</span></td>
							<td><div style='$cardcss;background:#800080'><i class='bi-diagram-3-fill'></i></div></td>
						</tr></table>
					</div></td>
					<td><div class='bg-white shadow-sm' style='min-height:90px'>
						<table style='width:100%' cellpadding='10'><tr>
							<td><h1 style='margin:0px;color:#663399;font-size:30px'>".fnum($totstaff)."</h1><span style='color:grey;font-size:14px'>Active Staff</span></td>
							<td><div style='$cardcss;background:#663399'><i class='bi-person-lines-fill' style='font-size:25px'></i></div></td>
						</tr></table>
					</div></td></tr>
				</table>
			</div>
			<div class='col-12 col-lg-6 col-xl-6 mb-3'>
				<div class='bg-white shadow-sm'>
					<div style='min-height:360px' id='dvln'></div>
					<div style='min-height:360px' id='dvcl'></div>
				</div>
				<script> drawPie('dvln','Client Loans Analysis',$lnjsn); drawPie('dvcl','Active Clients Loan Cycles',$cljsn); </script>
			</div>
			<div class='col-12 col-lg-6 col-xl-6 mb-3'>
				<div class='bg-white shadow-sm'>
					<div style='min-height:360px' id='dvprd'></div>
					<div style='min-height:360px' id='dvbr'></div>
				</div>
				<script> drawPie('dvprd','Loan Products Distribution',$prdjsn); drawPie('dvbr','OLB Distribution',$brnjsn); </script>
			</div>
			<div class='col-12 col-lg-6 col-xl-6 mb-3'>
				<div class='bg-white shadow-sm' style='max-height:450px;padding:10px'>
					$dgtitle <div style='min-height:360px' id='dvdis'></div>
					<script> $dgraph; </script>
				</div>
			</div> $pfdata
		</div>
	</div>";
	
	ob_end_flush();
?>