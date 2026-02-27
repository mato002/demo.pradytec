<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid=$by=substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	set_time_limit(600);
	ini_set("memory_limit",-1);
	ini_set("display_errors",0);
	
	include "../../core/functions.php";
	if(!isset($_GET['src'])){ exit(); }
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
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-P']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('P');
	$mpdf->SetAuthor("Prady MFI System");
	$mpdf->SetCreator("PradyTech");
	$mpdf->SetTitle($title);
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
		$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):"";
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
		
		if($grp<100){
			if($grp==0){
				# default clients
				$tdh="<td>Balance</td><td>Days</td>"; $tdy=strtotime(date("Y-M-d")); $trs=""; $no=0;
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,ln.expiry FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd 
				ON ln.id=sd.loan WHERE ln.balance>0 $cond AND sd.balance>0 AND ln.expiry<$tdy GROUP BY sd.loan ORDER BY ln.expiry DESC,ln.client ASC $lim");
				
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $days=floor((time()-$row['expiry'])/86400);
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $idno=$row['client_idno']; $no++;
						$trs.="<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td><td>$bal</td><td>$days</td></tr>";
					}
				}
			}
			else{
				# weekdays groups
				$tdh="<td>Balance</td>"; $trs=""; $no=0;
				$res = $db->query(2,"SELECT ln.client,ln.client_idno,ln.phone,ln.loan_officer,ln.balance,from_unixtime(sd.day,'%W') AS dow FROM `$ltbl` AS ln 
				INNER JOIN `$stbl` AS sd ON ln.id=sd.loan WHERE ln.balance>0 $cond  GROUP BY sd.loan,dow HAVING dow LIKE '".$grups[$grp]."' ORDER BY ln.client ASC $lim");
				
				if($res){
					foreach($res as $row){
						$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $idno=$row['client_idno']; 
						$ofname=$staff[$row['loan_officer']]; $bal=number_format($row['balance']); $no++;
						$trs.="<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td><td>$bal</td></tr>";
					}
				}
			}
		}
		else{
			# client groups 
			$tdh="<td>Status</td>"; $trs=""; $no=0;
			$res = $db->query(2,"SELECT ln.name,ln.contact,ln.idno,ln.loan_officer,ln.status,cg.unqid FROM `client_groups$cid` AS cg INNER JOIN `$ctbl` AS ln 
			ON ln.id=cg.client WHERE cg.gid='$grp' $cond ORDER BY ln.name ASC");
			
			if($res){
				foreach($res as $row){
					$name=prepare(ucwords($row['name'])); $fon=$row['contact']; $idno=$row['idno']; 
					$ofname=$staff[$row['loan_officer']]; $st=$row['status']; $rid=$row['unqid']; $no++;
					$states=array(0=>"<span style='color:orange;'><i class='fa fa-circle'></i> Dormant</span>",1=>"<span style='color:green;'>
					<i class='fa fa-circle'></i> Active</span>",2=>"<span style='color:#9932CC;'><i class='fa fa-circle'></i> Suspended</span>");
					
					$trs.="<tr><td>$no</td><td>$name</td><td>0$fon</td><td>$idno</td><td>$ofname</td><td>".$states[$st]."</td></tr>";
				}
			}
		}
		
		$data = "<p style='text-align:center'><img src='data:image/jpg;base64,".getphoto($logo)."' height='60'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Name</td><td>Contact</td><td>Id Number</td><td>Loan Officer</td>$tdh</tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>