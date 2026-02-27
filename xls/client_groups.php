<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$src = trim($_GET['src']);
	$grp = trim($_GET['gr']);
	$stid = trim($_GET['stid']);
	$bran = trim($_GET['br']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$ctbl = "org".$cid."_clients"; $stbl="org".$cid."_schedule"; $ltbl="org".$cid."_loans";
	
	$grups = array("Default","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
	$res = $db->query(2,"SELECT *FROM `client_groups$cid` GROUP BY gid ORDER BY `name` ASC");
	if($res){
		foreach($res as $row){
			$grups[$row['gid']]=prepare(ucfirst($row['name']));
		}
	}
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	$add = ($stid) ? "Staff ".$staff[$stid]:$bnames[$bran]." branch";
	$gname = ($grp<100) ? $grups[$grp]." Clients":$grups[$grp];
	$title = "$gname for $add";
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
		
		if($grp<100){
			if($grp==0){
				# default clients
				$tdy=strtotime(date("Y-M-d")); $trs=[]; $no=0;
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,ln.expiry FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd 
				ON ln.id=sd.loan WHERE ln.balance>0 $cond AND sd.balance>0 AND ln.expiry<$tdy GROUP BY sd.loan ORDER BY ln.expiry DESC,ln.client ASC $lim");
				
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $days=floor((time()-$row['expiry'])/86400);
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $idno=$row['client_idno']; $no++;
						$trs[]=array($no,$name,$fon,$idno,$ofname,$bal,$days);
					}
				}
				$thd = array("No","Name","Contact","Id Number","Loan Officer","Balance","Days");
			}
			else{
				# weekdays groups
				$trs=[]; $no=0;
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,from_unixtime(sd.day,'%W') AS dow FROM `$ltbl` AS ln 
				INNER JOIN `$stbl` AS sd ON ln.id=sd.loan WHERE ln.balance>0 $cond  GROUP BY sd.loan,dow HAVING dow LIKE '".$grups[$grp]."' ORDER BY ln.client ASC $lim");
				
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $idno=$row['client_idno']; 
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $no++;
						$trs[]=array($no,$name,$fon,$idno,$ofname,$bal);
					}
				}
				$thd = array("No","Name","Contact","Id Number","Loan Officer","Balance");
			}
		}
		else{
			# client groups 
			$trs=[]; $no=0;
			$res = $db->query(2,"SELECT ln.name,ln.contact,ln.idno,ln.loan_officer,ln.status,cg.unqid FROM `client_groups$cid` AS cg INNER JOIN `$ctbl` AS ln 
			ON ln.id=cg.client WHERE cg.gid='$grp' $cond ORDER BY ln.name ASC");
			
			if($res){
				foreach($res as $row){
					$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $idno=$row['idno']; 
					$ofname=$staff[$row['loan_officer']]; $st=$row['status']; $rid=$row['unqid']; $no++;
					$states=array(0=>"Dormant",1=>"Active",2=>"Suspended");
					$trs[]=array($no,$name,$fon,$idno,$ofname,$states[$st]);
				}
			}
			$thd = array("No","Name","Contact","Id Number","Loan Officer","Status");
		}
		
	$dat = array([null,$title,null,null],$thd); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title$by ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(6,20,12,12,20,10,13,18,15);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>