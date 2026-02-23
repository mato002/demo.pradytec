<?php
	
	include "../core/functions.php";
	require "index.php";
	
	if(!isset($_POST['genxls'])){ exit(); }
	$by = trim($_POST['genxls']);
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$fro = trim($_GET['mn']);
	$dto = trim($_GET['dy']);
	$bran = trim($_GET['br']);
	$prd = trim($_GET['prd']);
	$off = trim($_GET['lof']);
	$odb = trim($_GET['odb']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Current Loans","Completed Loans","Overdue Loans","Rescheduled Loans","WrittenOff Loans","Non Performing Loans","All Loans");
	
	$ttl = $titles[$vtp];
	if($prd){
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'");
		$ttl = prepare(ucfirst($qri[0]['product']))." $titles[$vtp]";
	}
	
	$bnames = array(0=>"Head Office");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl="org".CLIENT_ID."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
		
	$stl = ($off) ? $staff[$off]:$bnames[$bran]; $lof=($off) ? $staff[$off]:"All";
	$dur = ($fro) ? date("d-m-Y,H:i",$fro)." to ".date("d-m-Y,H:i",$dto):"";
	$title = "$stl $dur $ttl";
	
	$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1;
	$ltbl = "org".$cid."_loans";
	$info = mficlient(); $logo=$info['logo']; 
	
	if(in_array($vtp,array(3,4))){
		$comp = array(
			3=>["`rescheduled$cid` AS rs ON $ltbl.loan=rs.loan","$ltbl.*,rs.start,rs.fee,rs.duration"],
			4=>["`writtenoff_loans$cid` AS wf ON $ltbl.loan=wf.loan","$ltbl.*,wf.amount AS wamnt,wf.time AS wtym"]
		);
			
		$join = "INNER JOIN ".$comp[$vtp][0]; $chk = $comp[$vtp][1];
	}
	else{ $join=""; $chk="$ltbl.*"; }
		
	$no=0; $trs=[]; $tdy=strtotime(date("Y-M-d")); $show_curr_bal=[]; $order="`balance` DESC";
	$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND (`category`='client' OR `category`='app' OR `category`='asset')");
	if($sql){
		foreach($sql as $row){
			$pdef = json_decode($row["pdef"],1); $prods[$row["id"]]=prepare(ucfirst($row["product"]));
			$sint = (isset($pdef["showinst"])) ? $pdef["showinst"]:0;
			if($sint){ $show_curr_bal[]=$row["id"]; }
		}
	}
	
	if($odb){
		$oxt = explode(":",$odb); $ocl=$oxt[0];
		$order = ($oxt[1]=="a") ? "`$ocl` ASC":"`$ocl` DESC";
	}
	
	$res = $db->query(2,"SELECT $chk FROM `$ltbl` $join WHERE $cond ORDER BY $order");
	if($res){
		foreach($res as $row){
			$no++; $rid=$row['id']; $lid=$row['loan']; $pid=$row['loan_product']; $expd=$row['expiry']; $prod=$prods[$pid];
			$amnt=$row['amount']; $bal=$row['balance']+$row['penalty']; $paid=$row['paid']; $dsb=$row['disbursement'];
			if(in_array($pid,$show_curr_bal)){
				$sqr = $db->query(2,"SELECT SUM(paid) AS tpd,SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `day`<=$tdy");
				$roq = $sqr[0]; $bal=intval($roq['tbal']+$row['penalty']); $paid=intval($roq['tpd']);
			}
				
			$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $ofname=$staff[$row['loan_officer']]; $idno=$row['client_idno'];
			$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$dsb); $exp=date("d-m-Y",$row[$st]); 
			$tpy=$bal+$paid; $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100; $perc=($vtp==1) ? "100%":"$prc%"; $lntd="";
			
			if(in_array($vtp,[0,2,3,5])){
				if($tdy>$expd){ $lntd = "Overdue"; }
				elseif($expd==$tdy){ $lntd = "Maturing Today"; }
				elseif($expd==($tdy+86400)){ $lntd = "Maturing Tmorow"; }
				else{ $lntd = "Active"; }
			}
			
			if($vtp==3){ $trs[] = array($no,$name,$fon,$ofname,$prod,date("d-m-Y",$row['start']),$row['duration']." days",$row['fee'],$amnt,$tpy,$paid,$perc,$bal,$lntd,$exp); }
			elseif($vtp==4){ $trs[] = array($no,$name,$fon,$ofname,$prod,$row['wamnt'],date('d-m-Y',$row['wtym']),$amnt,$tpy,$paid,$perc,$bal,$lntd,$exp); }
			else{ $trs[] = array($no,$name,$fon,$ofname,$prod,$disb,$amnt,$tpy,$paid,$perc,$bal,$lntd,$exp); }
		}
	}
		
	$stl = ($vtp==1) ? "Completed":"Maturity"; $stl = ($vtp==4) ? "Writeoff":$stl;
	$header = ($vtp==3) ? array("No","Client","Contact","Portfolio","Product","Rescheduled","Duration","Fees","Loan","ToPay","Paid","Percentage","Balance","Status",$stl):
	array("No","Client","Contact","Portfolio","Product","Disbursement","Loan","ToPay","Paid","Percentage","Balance","Status",$stl);
	$header = ($vtp==4) ? array("No","Client","Contact","Portfolio","Product","Writeoff","Date","Loan","ToPay","Paid","Percentage","Balance","Status",$stl):$header;
	
	$dat = array([null,$title,null,null],$header); $data = array_merge($dat,$trs);
	$prot = protectDocs($by); $fname=prepstr("$title ".date("His"));
	$pass = ($prot) ? $prot['password']:null;
	
	$widths = array(6,20,12,20,18,10,10,15,15,15,15,15,15,15);
	$res = genExcel($data,"A2",$widths,"docs/$fname.xlsx",$title,$pass);
	echo ($res==1) ? "success:docs/$fname.xlsx":"Failed to generate Excel File";

?>