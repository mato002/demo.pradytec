<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by=$sid=trim($_POST['genxls']);
	$vtp = trim($_GET['vtp']);
	
	$db = new DBO(); $cid=CLIENT_ID;
	$prot = protectDocs($by);
	$pass = ($prot) ? $prot['password']:null;
	
	
	# loan sizes
	if($vtp=="lsizes"){
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$rgn = (isset($_GET['rgn'])) ? trim($_GET['rgn']):0;
		
		$ltbl = "org{$cid}_loans"; $stbl = "org{$cid}_schedule";
		$me = staffInfo($sid); $access=$me['access_level'];
		$cnf = json_decode($me["config"],1);
		
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames=[];
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch'])); 
			}
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND loan_officer='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
		$trs=[]; $tln=$tpf=$tarr=$tpds=$tots=0; 
		
		if($me["position"]=="collection agent"){
			$cond.= " AND JSON_EXTRACT(clientdes,'$.agent')=$sid";
		}
			
		$ranges = (defined("LOAN_SIZE_RANGE")) ? LOAN_SIZE_RANGE:array("0-1000","1001-5000","5001-10000","10001-20000","20001-50000","50001-100000","100001-inf");
		$qri = $db->query(2,"SELECT SUM(balance) AS tbal FROM `$ltbl` WHERE `balance`>0 $cond");
		$tsum = intval($qri[0]["tbal"]); $today=strtotime(date("Y-M-d")); $tbals=$tots=$tns=$tarr=0;
		
		foreach($ranges as $range){
			$mfr = explode("-",$range)[0]; $mtu=explode("-",$range)[1]; $mto=($mtu=="inf") ? time():$mtu; $arrt=$arrs=0;
			$rname = ($mfr==0) ? " Below ".fnum($mto):fnum($mfr)." - ".fnum($mto); $rname=($mtu=="inf") ? "Above ".fnum($mfr-1):$rname;
			
			$sq1 = $db->query(2,"SELECT SUM(balance) AS tbal,COUNT(*) AS tot FROM `$ltbl` WHERE `balance`>0 AND `amount` BETWEEN $mfr AND $mto $cond");
			$sq2 = $db->query(2,"SELECT SUM(sd.balance) AS tarr,COUNT(DISTINCT sd.loan) AS tot FROM `$stbl` AS sd INNER JOIN `$ltbl` ON $ltbl.loan=sd.loan WHERE sd.balance>0 AND $ltbl.amount 
			BETWEEN $mfr AND $mto AND sd.day<$today $cond GROUP BY sd.loan"); 
			if($sq2){
				foreach($sq2 as $row){ $arrt+=$row["tot"]; $arrs+=$row["tarr"]; }
			}
			
			$tbal = intval($sq1[0]["tbal"]); $tot=intval($sq1[0]["tot"]); $perc=roundnum(($tbal/$tsum)*100,2); $tbals+=$tbal; $tots+=$tot; $tns+=$arrt; $tarr+=$arrs;
			$trs[] = array($rname,fnum($tot),fnum($tbal),"$perc%",fnum($arrt),fnum($arrs));
		}
		
		$trs[] = array(null,fnum($tots),fnum($tbals),"100%",fnum($tns),fnum($tarr));
		$title = "Loan Size Distribution"; $fname=prepstr("$title ".date("His"));
		$dat = array([null,$title,null,null,null,null,null],array("Loan Size","No. of Accounts","Loan Balances","Composition","Non-Performing Accounts","Non-Performing Amounts")); 
		$res = genExcel(array_merge($dat,$trs),"A2",array(40,20,20,20,20,20,20,20),"docs/$fname.xlsx",$title,null,8);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	
	# trial balance
	if($vtp=="trbl"){
		$yr = trim($_GET["yr"]); $tmon=strtotime(date("Y-M"));
		$monstart = ($yr==date("Y")) ? intval(date("m")):12;
		$right = array("liability","equity"); $saved=$creds=$debs=$trs=$thd=$ths1=$ths2=$lds=[]; 
		$mls = array("","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
		$qri = $db->query(3,"SELECT *FROM `monthly_balances$cid` WHERE `year`='$yr'");
		if($qri){
			foreach($qri as $row){ $saved[$row['account']][$row["month"]]=$row["balance"]; }
		}
			
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` ORDER BY `account` ASC");
		foreach($qri as $row){
			if($row["tree"]==0 or isset($saved[$row['id']])){ $accs[$row["id"]]=$row; }
		}
			
		foreach($accs as $rid=>$row){
			$cbal=intval($row['balance']); $name=prepare(ucfirst($row["account"])); $type=$row['type']; $tds=[];
			for($mn=$monstart; $mn>=1; $mn--){
				$mon = strtotime("$yr-".$mls[$mn]); $creds[$mon][$rid]=$debs[$mon][$rid]=0; $rgt=$lft="";
				if($mon==$tmon){
					if(in_array($type,$right)){ $rgt=$cbal; $creds[$mon][$rid]+=$cbal;  }
					else{ $lft=$cbal; $debs[$mon][$rid]+=$cbal; }
					$tds[] = array($lft,$rgt);
				}
				else{
					if(isset($saved[$rid])){
						if(isset($saved[$rid][$mon])){
							if(in_array($type,$right)){ $val=$saved[$rid][$mon]; $rgt=$val; $creds[$mon][$rid]+=$val; }
							else{ $val=$saved[$rid][$mon]; $lft=$val; $debs[$mon][$rid]+=$val; } 
							$tds[] = array($lft,$rgt);
						}
						else{ $tds[] = array("--","--"); }
					}
					else{ $tds[] = array("--","--"); }
				}
			}
			
			$drow = [prenum($rid)."\r\n",$name];
			foreach($tds as $one){
				foreach($one as $arr){ $drow[]=$arr; }
			}
			$trs[] = $drow;
		}
		
		for($mn=$monstart; $mn>=1; $mn--){
			$mon = strtotime("$yr-".$mls[$mn]); $crd=(isset($creds[$mon])) ? array_sum($creds[$mon]):0; 
			$dbt = (isset($debs[$mon])) ? array_sum($debs[$mon]):0; 
			$ths1[] = array("$mls[$mn] $yr",null); $ths2[]=array("Debits","Credits"); $thd[]=array($dbt,$crd);
		}
		
		$hed1 = array("Code","Account"); $hed2=$last=[null,null];
		foreach($ths1 as $one){ foreach($one as $arr){ $hed1[]=$arr; }}
		foreach($ths2 as $one){ foreach($one as $arr){ $hed2[]=$arr; }}
		foreach($thd as $one){ foreach($one as $arr){ $last[]=$arr; }}
		
		$title = "$yr Trial balance"; $fname=prepstr("$title ".date("His")); $tot=count($hed1)+2;
		$dat = array([null,$title,null,null,null,null,null],$hed1); $def=array_merge($dat,[$hed2],$trs,[$last]);
		$widths = array(7,30,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10);
		$res = genExcel($def,"A2",$widths,"docs/$fname.xlsx",$title,null,$tot);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	
	# payments report
	if($vtp=="paysum"){
		$mon = trim($_GET['mn']);
		$bran = trim($_GET['br']);
		$dels = array("id","paybill","branch","officer","month","year");
		$fields = $db->tableFields(2,"paysummary$cid"); $ths=[];
			
		foreach($fields as $col){
			if(!in_array($col,$dels)){
				$ths[]=ucwords(str_replace("_"," ",$col)); $cols[]=$col;
			}
		}
			
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
			
		$me = staffInfo($sid); 
		$access = $me['access_level'];
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		$bids=$data=$incomes=[]; $pcko=0;
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){ $staff[$row['id']]=prepare(ucwords($row['name'])); }
	
		$icols = array("interest","penalties");
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$terms=json_decode($row['payterms'],1);
				foreach($terms as $pay=>$des){ $icols[$pay]=$pay; }
			}
		}
			
		if($access=="hq"){
			$res = $db->query(2,"SELECT DISTINCT branch FROM `paysummary$cid` WHERE `month`='$mon'");
			if($res){
				foreach($res as $row){ $bids[]=$row['branch']; }
			}
		}
			
		if($me['access_level']=="hq"){
			$ths = array_merge(["Branch"],$ths);
			foreach($bids as $bid){
				$dat=[]; $income=0; $dcols=""; 
				$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `branch`='$bid' AND `month`='$mon'");
				foreach($qri as $row){
					foreach($cols as $col){
						if(array_key_exists($col,$dat)){
							if(array_key_exists($col,$row)){ $dat[$col]+=$row[$col]; }
						}
						else{
							if(array_key_exists($col,$row)){ $dat[$col]=$row[$col]; }
							else{ $dat[$col]=0; }
						}
					}
					foreach($icols as $col){ $income+=(isset($row[$col])) ? $row[$col]:0; $dcols.="`payment`='".ucfirst($col)."' OR "; }
				}
			
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
				$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `branch`='$bid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
				$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$bid]=$dat; $pcko+=$cko; $incomes[$bid]=array("total"=>$income,"checkoff"=>$cko);
			}
		}
		elseif($me['access_level']=="branch"){
			$mbr = $me['branch']; $ths = array_merge(["Staff"],$ths);
			$res = $db->query(2,"SELECT DISTINCT `officer` FROM `paysummary$cid` WHERE `month`='$mon' AND `branch`='$mbr'");
			if($res){
				foreach($res as $row){
					$ofid=$row['officer']; $dat=[]; $income=0; $dcols="";
					$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `officer`='$ofid' AND `month`='$mon'");
					foreach($qri as $rw){
						foreach($cols as $col){
							if(array_key_exists($col,$dat)){
								if(array_key_exists($col,$rw)){ $dat[$col]+=$rw[$col]; }
							}
							else{
								if(array_key_exists($col,$rw)){ $dat[$col]=$rw[$col]; }
								else{ $dat[$col]=0; }
							}
						}
						foreach($icols as $col){ $income+=$rw[$col]; $dcols.="`payment`='".ucfirst($col)."' OR "; }
					}
					
					$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
					$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `officer`='$ofid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
					$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$ofid]=$dat; $pcko+=$cko; $incomes[$ofid]=array("total"=>$income,"checkoff"=>$cko);
				}
			}
		}
		else{
			$ths = array_merge(["Staff"],$ths); $dcols=""; $dat=[]; $income=$pcko=0;
			$qri = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `officer`='$sid' AND `month`='$mon'"); 
			if($qri){
				foreach($qri as $rw){
					foreach($cols as $col){
						if(isset($dat[$col])){
							if(isset($rw[$col])){ $dat[$col]+=$rw[$col]; }
						}
						else{
							if(isset($rw[$col])){ $dat[$col]=$rw[$col]; }
							else{ $dat[$col]=0; }
						}
					}
					foreach($icols as $col){ $income+=$rw[$col]; $dcols.="`payment`='".ucfirst($col)."' OR "; }
				}
					
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
				$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `officer`='$sid' AND `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def");
				$cko = ($qry) ? $qry[0]['tamnt']:0; $data[$sid]=$dat; $pcko+=$cko; $incomes[$sid]=array("total"=>$income,"checkoff"=>$cko);
			}
		}
			
		$totals=$itotal=$ckos=0; $trs=$tdk=$tcols=[]; 
		foreach($data as $pos=>$arr){
			$tname = ($me['access_level']=="hq") ? $bnames[$pos]:$staff[$pos]; $total=array_sum($arr); 
			$totals+=$total; $tds=[]; $go=($me['access_level']=="hq") ? "b:$pos":"s:$pos";
			foreach($cols as $col){
				if(array_key_exists($col,$tcols)){ $tcols[$col]+=$arr[$col]; }
				else{ $tcols[$col]=$arr[$col]; }
				$tds[]=$arr[$col];
			}
				
			$cko = $incomes[$pos]['checkoff']; $ckos+=$cko; $itotal+=$incomes[$pos]['total']; 
			if($pcko){ array_push($tds,$cko); }
			$trs[] = array_merge(array($tname),$tds,array($incomes[$pos]['total'],$total));
		}
			
		foreach($cols as $col){
			$tdk[]= (isset($tcols[$col])) ? $tcols[$col]:0;
		}
		
		if($ckos){ array_push($ths,"Checkoff Income"); array_push($tdk,$ckos); }
		$trs[] = array_merge(array("Totals"),$tdk,array($itotal,$totals));
		$add = ($bran or $me['access_level']=="portfolio") ? $bnames[$me['branch']]:"Corporate";
		$title = date("M Y",$mon)." $add Payments Report"; $fname=prepstr("$title ".date("His"));
		
		$dat = array([null,$title,null,null],array_merge($ths,["Total Income","Totals"])); $def = array_merge($dat,$trs);
		$widths = array(20,15,15,15,25,15,15,15,15,15,15,15,15,15,15);
		$res = genExcel($def,"A2",$widths,"docs/$fname.xlsx",$title,null);
		echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";
	}
	
	#income statement
	if($vtp=="income"){
		$mon = trim($_GET['mn']);
		$yr = trim($_GET['yr']);
		$brn = (isset($_GET['brn'])) ? trim($_GET['brn']):0;
		$pst = (isset($_GET['pst'])) ? trim($_GET['pst']):0;
		$title = ($mon) ? date("F Y",$mon):$yr;
		$title = (isset($_GET['mon']) && !$mon) ? $yr:$title;
		$cond = ($mon) ? "AND `month`='$mon'":""; $mns=[];
			
		$qri = $db->query(2,"SELECT DISTINCT `month` FROM `paysummary$cid` WHERE `year`='$yr' ORDER BY `month` DESC");
		if($qri){ foreach($qri as $row){ $mns[]=$row['month']; }}
		
		$incomes=$trans=[]; $cols=array("interest","penalties"); $dcols="";
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$terms=json_decode($row['payterms'],1);
				foreach($terms as $pay=>$des){ $cols[$pay]=$pay; }
			}
		}
			
		$add1 = ($brn) ? "AND `branch`='$brn'":""; $lmons=$icols=[];
		$sql = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `year`='$yr' $cond $add1 ORDER BY `month` DESC");
		if($sql){
			foreach($sql as $row){
				$mn=$row["month"]; $lmons[$mn]=$mn;
				if(!isset($incomes[$mn])){ $incomes[$mn]=[]; }
				foreach($cols as $col){
					if(isset($row[$col]) or isset($incomes[$mn][$col])){
    					$dcols.="`payment`='".ucfirst($col)."' OR "; 
    					if(isset($incomes[$mn][$col])){ $incomes[$mn][$col]+=$row[$col]; $icols[$col]=$col; }
    					else{ $incomes[$mn][$col]=$row[$col]; $icols[$col]=$col; }
					}
				}
			}
			
			if($mon){
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":"";
				$qry = $db->query(2,"SELECT SUM(amount) AS tamnt FROM `processed_payments$cid` WHERE `month`='$mon' AND `code` LIKE 'CHECKOFF%' $def $add1");
				$cko = ($qry) ? $qry[0]['tamnt']:0; if($cko){ $incomes[$mon]["Checkoff Income"]=$cko; }
			}
			else{
				$def = ($dcols) ? "AND (".trim(rtrim($dcols,"OR ")).")":""; $yrst=strtotime("$yr-Jan"); $yrto=strtotime("$yr-Dec"); $adds=[];
				$qri = $db->query(2,"SELECT SUM(amount) AS tamnt,`month` FROM `processed_payments$cid` WHERE `month` BETWEEN $yrst AND $yrto AND `code` LIKE 'CHECKOFF%' $def $add1 GROUP BY month");
				if($qri){
					foreach($qri as $row){
						if($row["month"]){ $incomes[$row["month"]]["Checkoff Income"]=$row["tamnt"]; $adds[]=$row['month']; }
					}
				}
				
				if($adds){
					foreach($lmons as $mn){
						if(!in_array($mn,$adds)){ $incomes[$mn]["Checkoff Income"]=0; }
					}
				}
			}
		}
		else{ $incomes[$tmon] = array("Loan Interest"=>0,"Other Income"=>0); $icols=["Loan Interest","Other Income"]; }
		
		$add2 = ($brn) ? "AND `branch`='$brn'":""; $net=$mls=$xpacs=$accs=[]; $trs=[];
		$sqr = $db->query(3,"SELECT SUM(amount) AS tsum,account,month FROM `transactions$cid` WHERE `year`='$yr' AND `book`='expense' AND `type`='debit' AND `amount`>0 $cond $add2 GROUP BY `account`,`month`");
		if($sqr){
			foreach($sqr as $row){
				$mn=$row['month']; $amnt=$row['tsum']; $acc=$row['account'];
				if(!isset($incomes[$mn])){
					foreach($icols as $col){ $incomes[$mn][$col]=0; }
				}
				if(isset($trans[$mn])){
					if(isset($trans[$mn][$acc])){ $trans[$mn][$acc]+=$amnt; }
					else{ $trans[$mn][$acc]=$amnt; }
				}
				else{ $trans[$mn][$acc]=$amnt; }
				if(isset($accs[$acc])){ $accs[$acc]+=$amnt; }
				else{  $accs[$acc]=$amnt; }
			}
		}
		
		krsort($incomes);
		foreach($incomes as $mn=>$one){
			$totinc[$mn]=0;
			foreach($one as $col=>$tot){
				$acc = ($col=="interest") ? "Loan Interest":ucwords(str_replace("_"," ",$col)); 
				$net[$acc][$mn]=$tot; $totinc[$mn]+=($col=="Checkoff Income") ? 0:$tot;
			}
		}
		
		foreach($net as $acc=>$one){
			foreach($one as $mn=>$tot){ $tds[]=number_format($tot); }
			$trs[] =array_merge(array($acc),$tds);
		}
			
		$cond2 = (!$pst) ? "AND `wing`='0'":"AND `tree`='0'";
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `type`='expense' $cond2");
		foreach($res as $row){
			$acc=ucwords(prepare($row['account'])); $rid=$row['id']; $ids=[];
			if($pst){
				if(isset($accs[$rid])){ $xpacs[$acc]=[$rid]; }
			}
			else{
				$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing` LIKE '$rid,%' OR `wing`='$rid'");
				if($qri){
					foreach($qri as $rw){ $ids[]=$rw['id']; }
				}
				$xpacs[$acc] = $ids;
			}
		}
		
		$ths=$etrs=$xaccs=$totexp=$tts=[];
		foreach($totinc as $mn=>$sum){
			$tts[] = number_format($sum); $ths[]=date("M-Y",$mn); $totexp[$mn]=0;
			foreach($xpacs as $acc=>$one){ $fnd=[];
				foreach($one as $id){
					if(isset($trans[$mn][$id])){ $fnd[$id]=$trans[$mn][$id]; }
				}
				$xaccs[$acc][$mn]=$fnd; $totexp[$mn]+=array_sum($fnd);
			}
		}
		
		foreach($xaccs as $acc=>$one){ $tds=[];
			foreach($one as $mn=>$ids){ $tds[]=number_format(array_sum($ids)); }
			$etrs[] = array_merge([$acc],$tds);
		}
		
		$tte=$gte=$ite=$nte=[]; 
		foreach($totexp as $mn=>$sum){
			$tte[]=number_format($sum); $gte[]=number_format($totinc[$mn]-$sum);
			$ite[]=number_format(0); $nte[]=number_format($totinc[$mn]-$sum);
		}
		
		$trs[] = array_merge(["Total Income"],$tts);
		$etrs[] = array_merge(["Total Expenses"],$tte);
		$gtrs[] = array_merge(["Gross Income"],$gte);
		$itrs[] = array_merge(["Income Tax"],$ite);
		$ntrs[] = array_merge(["Net Profit (Loss)"],$nte);
		
		if($brn){
			$bname = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$brn'")[0]['branch'];
			$mtitle = prepare(ucwords($bname))." Income report for $title";
		}
		else{ $mtitle = "Income Report for $title"; }
		
		$dat = array([null,$mtitle,null,null],[null,"REVENUE",null],array_merge(["Account"],$ths)); $def=array_merge($dat,$trs,array([null,null,null]));
		$def[] = [null,"EXPENSES",null]; $final = array_merge($def,$etrs,array([null,null,null]));
		$res = genExcel($final,"A2",array(18,30,25),"docs/$mtitle.xlsx",$mtitle,null);
		echo ($res==1) ? "success:docs/$mtitle.xlsx":"Failed to generate Excel File";
	}
	
?>