<?php
    
    ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
	
    require "functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
    
    if(isset($_POST['updateday'])){
		$today=strtotime(date("Y-M-d")); $ldy=strtotime("Yesterday");
		$ctbl="org".$cid."_clients"; $ltbl="org".$cid."_loans"; $stbl="org".$cid."_schedule"; 
		$mon = strtotime(date("Y-M",$ldy)); $updated[$ldy]=[]; $nxt=0; 
		$lmon = strtotime(date("Y-M",strtotime("-1 month")));
		
		foreach(array($ltbl,$stbl,$ctbl,"org".$cid."_loantemplates") as $tbl){
			if(!$db->istable(2,$tbl)){ $nxt=1; break; }
		}
		if($nxt){ exit(); }
		
		$btbl = "bi_analytics$cid"; $flds=$db->tableFields(2,$btbl); $cols=implode(",",$flds);
		if(!in_array("loanbook",$flds)){
			$cols = array_merge(array_slice($flds,0,3), ["loanbook"], array_slice($flds,3));
			$db->execute(2,"ALTER TABLE `bi_analytics$cid` ADD `loanbook` INT NOT NULL AFTER `staff`");
		}
		
		$res = $db->query(2,"SELECT *FROM `bi_analytics$cid` WHERE `month`='$mon'");
		if($res){
			foreach($res as $row){
				$updated[$row['day']][$row['staff']]=$row['staff'];
			}
		}
		
		#harmonize loans & clients
		if(count($updated[$ldy])<1){
			$res = $db->query(2,"SELECT COUNT(*) AS total FROM `$ctbl` WHERE `status`='1'"); $active=($res) ? $res[0]['total']:0;
			$lns = $db->query(2,"SELECT DISTINCT client_idno FROM `$ltbl` WHERE (balance+penalty)>0"); $loans=($lns) ? count($lns):0;
			if($active!=$loans){
				$db->insert(2,"UPDATE `$ctbl` SET `status`='0' WHERE `status`='1'");
				foreach($lns as $row){
					$db->insert(2,"UPDATE `$ctbl` SET `status`='1' WHERE `idno`='".$row['client_idno']."'");
				}
			}
		}
		
		#update bi analysis
		$mon=strtotime(date("Y-M",$ldy)); $ltpl="org".$cid."_loantemplates";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){ $mybran[$row['id']]=$row['branch']; }
			
		$res = $db->query(2,"SELECT MAX(day) AS mxd FROM `bi_analytics$cid` WHERE `month`='$mon'");
		$mxd = ($res[0]['mxd']) ? $res[0]['mxd']:monrange(date("m",$mon),date("Y",$mon))[0]; $from=$ldy-86400;
		if($mxd<$from){
			if(intval(date("d"))==2){ $updates = array(strtotime(date("Y-M"))); }
			else{
				for($i=intval(date("d",$mxd)); $i<=intval(date("d",$from)); $i++){
					$dy=(strlen($i)<2) ? "0$i":$i; $updates[]=strtotime(date('Y-M-',(($i-1)*86400)+$mxd).$dy); 
				}
				array_push($updates,$ldy);
			}
		}
		else{ $updates = array($ldy); }
		
		foreach($updates as $ldy){
			$mto = $ldy+86399;
			$qri = $db->query(2,"SELECT `loan_officer`,COUNT(*) AS sumup FROM `$ctbl` WHERE `loan_officer`>0 GROUP BY loan_officer HAVING sumup>0");
			if($qri){
				foreach($qri as $row){
					$uid=$row['loan_officer']; $tot=$row['sumup']; $bran=$mybran[$uid];
					$data = (isset($updated[$ldy])) ? $updated[$ldy]:[];
					
					if(!in_array($uid,$data)){
						$res = $db->query(2,"SELECT SUM(balance+penalty) as bal,COUNT(*) AS active FROM `$ltbl` WHERE `loan_officer`='$uid' AND (balance+penalty)>0");
						$sql = $db->query(2,"SELECT SUM((CASE WHEN (paid>(amount-interest)) THEN 0 ELSE (amount-paid-interest) END)) AS lbk FROM `$stbl` WHERE `officer`='$uid' AND `balance`>0");
						$bal =($res[0]['bal']) ? $res[0]['bal']:0; $active=($res) ? intval($res[0]['active']):0; $lbk=intval($sql[0]['lbk']); $dorm=$tot-$active;
						
						$qry = $db->query(2,"SELECT SUM(balance) AS arrears FROM `$stbl` WHERE `officer`='$uid' AND `balance`>0 AND `day`<'$mto'");
						$res = $db->query(2,"SELECT DISTINCT loan FROM `$stbl` WHERE `officer`='$uid' AND `balance`>0 AND `day`<'$mto'");
						$arr = ($qry) ? $qry[0]['arrears']:0; $def = ($res) ? count($res):0; $perf=$active-$def; $newc=$rept=$disb=$cko=0;
							
						$res = $db->query(2,"SELECT *FROM `$ltpl` WHERE `loan_officer`='$uid' AND `status` BETWEEN '$ldy' AND '$mto'");
						if($res){
							foreach($res as $row){
								if(substr($row['loantype'],0,5)!="topup"){
									$lntp = explode(":",$row['loantype']); $disb+=$row['amount']; $cko+=$row['checkoff'];
									$newc+= ($lntp[0]=="new") ? 1:0; $rept+=($lntp[0]=="repeat") ? 1:0; 
								}
							}
						}
							
						if($active>0 or $arr>0){
							$income = getIncome("officer",$uid,$mon); $prc=$all=0;
							$sqc = $db->query(2,"SELECT SUM(amount) AS coll FROM `processed_payments$cid` WHERE `officer`='$uid' AND `day`='$ldy'");
							$res = $db->query(2,"SELECT MIN(activation) AS mact FROM `bi_analytics$cid` WHERE `staff`='$uid'");
							$act = ($res) ? (int) $res[0]['mact']:$dorm; $actv = ($act>$dorm) ? (int) $act-$dorm:0; $coll =($sqc[0]['coll']) ? $sqc[0]['coll']:0;  
							$db->insert(2,"INSERT INTO `$btbl` ($cols) SELECT NULL,'$bran','$uid','$lbk','$active','$dorm','$actv','$bal','$arr','$perf','$rept',
							'$newc','$coll','$disb','$cko','$income','$ldy','$mon' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `$btbl` WHERE `staff`='$uid' AND `day`='$ldy')");
						}
					}
				}
			}
		}
    }

?>