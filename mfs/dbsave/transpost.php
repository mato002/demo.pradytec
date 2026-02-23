<?php
	session_start();
	ob_start();
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	$ref = trim($_POST["postreq"]); $proceed=0;
	$chk = fetchSqlite("tempdata","SELECT *FROM `tokens` WHERE `data`='$ref'"); 
	if(is_array($chk)){
		if($chk[0]['data']==$ref){ $proceed=1; }
	}
	
	if(!$proceed){ exit(); }
	$sid = $chk[0]['temp'];
	
	
	# post wallet topups
	if(isset($_POST["srctp"])){
		$src = trim($_POST["srctp"]);
		$rid = trim($_POST["reqid"]);
		$amnt = trim($_POST["psum"]);
		$trans = explode("-",trim($_POST["tid"]));
		$mon = strtotime(date("Y-M")); $dy=strtotime(date("Y-M-d")); 
		$tym = time(); $week=date("W",$tym); $yr=date("Y");
		$tid = $trans[0]; $abk=$trans[1];
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){ $mybran[$row['id']]=$row['branch']; $names[$row['id']]=$row['name']; }
		
		# requisitions
		if($src=="requisition"){
			$sql = $db->query(3,"SELECT *FROM `requisitions$cid` WHERE `id`='$rid'");
			$row = $sql[0]; $bran=$row['branch']; $rno=prenum($rid);
			$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'");  
			$fid = ($res) ? $res[0]['id']:0; $desc = "Requisition $rno reimbursement"; $ds2="Reimbursement from requisition $rno";
					
			if(!$fid){
				$db->execute(3,"INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$mon','0','0')");
				$qry = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$bran' AND `month`='$mon'"); 
				$fid = $qry[0]['id'];
			}
			
			cashbal($fid,"+$amnt"); 
			$db->execute(3,"INSERT INTO `cashbook$cid` VALUES(NULL,'$bran','$sid','$tid','debit','$ds2','$amnt','$week','$mon','$yr','$tym','0')");
			$rev = json_encode(array(["db"=>3,"tbl"=>"cashfloats$cid","col"=>"id","val"=>$fid,"update"=>"closing:-$amnt"],
			["db"=>3,"tbl"=>"cashbook$cid","col"=>"transid","val"=>$tid,"update"=>"delete:$tid"]));
			doublepost([$bran,$abk,$amnt,$desc,$tid,$sid],[$bran,14,$amnt,$ds2,$tid,$sid],'',$tym,$rev);
		}
		
		# advances
		if($src=="advance"){
			$qri = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid'");
			$row = $qri[0]; $mont=$row['month']; $stid=$row['staff']; $bran=$mybran[$stid]; $cut=0; 
			$desc = "Salary advance for $names[$stid] in ".date("F Y",$mont); $dca=getrule("advances")['debit']; 
			if(isset($row['charges'])){ $cut=($row["charges"]) ? array_sum(json_decode($row['charges'],1)):0; }
			doublepost([$bran,$abk,$amnt,$desc,$tid,$sid],[$bran,$dca,$amnt,$desc,$tid,$sid],'',$tym,[]);
			if($cut){ saveTrans_fees($tid,"Interest Charges from Salary Advance to $names[$stid]",$cut,$sid); }
		}
		
		# utility expenses
		if($src=="utility"){
    		$qri = $db->query(3,"SELECT *FROM `utilities$cid` WHERE `id`='$rid' AND `status`='200'");
    		if($qri){
				$row = $qri[0]; $bran=$row['branch']; $rno=prenum($rid); $uid=$row["staff"]; $no=0;
				$desc = json_decode($row['item_description'],1); $des=json_decode($row['recipient'],1); $des['name']=$names[$uid];
				$db->execute(3,"UPDATE `utilities$cid` SET `recipient`='".json_encode($des,1)."' WHERE `id`='$rid'");
				foreach($des['book'] as $bk=>$sum){
					$book = explode(":",$bk); $dsc=$desc[$no]["item"]; $no++;
					doublepost([$bran,$abk,$sum,$dsc,$tid,$sid],[$bran,explode("-",$book[0])[0],$sum,$dsc,$tid,$sid],'',$tym,"auto"); 
				}
    		}
		}
		
		# client loans
		if($src=="loantemplate"){
			$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `status`='$tym' WHERE `id`='$rid' AND `status`='8'");
			$chk = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `id`='$rid'");
			if($chk){
				$row = $chk[0]; $bid=$row["branch"]; $cut=$row['checkoff']+$row['prepay']; $name=ucwords($row["client"]);
				$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $des="Loan of KES $amnt to $name"; 
				$cut+= (isset($row['cuts'])) ? array_sum(json_decode($row['cuts'],1)):0; $lntp=$row['loantype'];
				$tds = ($dsb) ? $amnt:$amnt+$cut; $dsb[$tym]=$tds; $djsn=json_encode($dsb,1);  
				$db->execute(2,"UPDATE `org".$cid."_loantemplates` SET `disbursed`='$djsn' WHERE `id`='$rid'"); 
				doublepost([$bid,$abk,$amnt,$des,"CLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"CLOAN$rid",$sid],'',$tym,"auto");
				if(explode(":",$lntp)[0]=="topup" or count($dsb)>1){
					$tsr = (count($dsb)>1) ? $rid:explode(":",$lntp)[1];
					$qri = $db->query(2,"SELECT `loan`,`balance` FROM `org$cid"."_loans` WHERE `tid`='$tsr'");
					if($qri){
						$jsn = ["desc"=>"Loan Topup to $name via Transaction Account deposit. Ref $tid","type"=>"debit","amount"=>$amnt,"bal"=>$qri[0]['balance']+$amnt];
						logtrans($qri[0]['loan'],json_encode($jsn,1),0); 
					}
				}
				else{ logtrans("TID$rid","Disbursement to $name via Transaction Account deposit. Ref $tid",0); }
			}
		}
			
		# staff loans
		if($src=="staffloans"){
			$chk = $db->query(2,"SELECT `branch`,`loantype` FROM `staff_loans$cid` WHERE `id`='$rid' AND `status`='8'");
			$db->execute(2,"UPDATE `staff_loans$cid` SET `status`='$tym' WHERE `id`='$rid' AND `status`='8'");
			if($chk){
				$row=$chk[0]; $bid=$row['branch']; $name=ucwords($row["staff"]); $des="Loan of KES $amnt to $name"; $lntp=$row['loantype'];
				doublepost([$bid,$abk,$amnt,$des,"SLOAN$rid",$sid],[$bid,getrule("loans")['debit'],$amnt,$des,"SLOAN$rid",$sid],'',$tym,"auto");
				if(explode(":",$lntp)[0]=="topup"){
					$id = explode(":",$lntp)[1];
					$qri = $db->query(2,"SELECT `loan`,`balance` FROM `staff_loans$cid` WHERE `id`='$id'");
					if($qri){
						$jsn = ["desc"=>"Loan Topup to $name via Transaction Account deposit. Ref $tid","type"=>"debit","amount"=>$amnt,"bal"=>$qri[0]['balance']+$amnt];
						logtrans($qri[0]['loan'],json_encode($jsn,1),0); 
					}
				}
				else{ logtrans("SID$rid","Disbursement to $name via Transaction Account deposit. Ref $tid",0); }
			}
		}
	}

	
	@ob_end_flush();
?>