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
	$vtp = trim($_GET['v']);
	$src = trim($_GET['src']);
	$fro = trim($_GET['mn']);
	$dto = trim($_GET['dy']);
	$bran = trim($_GET['br']);
	$prd = trim($_GET['prd']);
	$off = trim($_GET['lof']);
	$odb = trim($_GET['odb']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$titles = array("Current Loans","Completed Loans","Overdue Loans","Rescheduled Loans","Written Off Loans","Non Performing Loans","All Loans");
	$ttl = $titles[$vtp];
	
	$bnames = array(0=>"Corporate");
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
	if($res){
		foreach($res as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
	}
	
	$stbl = "org".CLIENT_ID."_staff"; $staff=[];
	$res = $db->query(2,"SELECT *FROM `$stbl`");
	foreach($res as $row){
		$staff[$row['id']]=prepare(ucwords($row['name']));
	}
	
	if($prd){
		$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prd'");
		$ttl = prepare(ucfirst($qri[0]['product']))." $titles[$vtp]";
	}
	
	$stl = ($off) ? $staff[$off]:$bnames[$bran]; $lof=($off) ? $staff[$off]:"All";
	$dur = ($fro) ? date("d-m-Y,H:i",$fro)." to ".date("d-m-Y,H:i",$dto):"";
	$title = "$stl $dur $ttl";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
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
	
		$cond = ($src) ? base64_decode(str_replace(" ","+",$src)):1; $ltbl="org".$cid."_loans";
		$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
		
		if(in_array($vtp,array(3,4))){
			$comp = array(
				3=>["`rescheduled$cid` AS rs ON $ltbl.loan=rs.loan","$ltbl.*,rs.start,rs.fee,rs.duration","<td>Rescheduled</td><td>Duration</td><td>Fees</td>"],
				4=>["`writtenoff_loans$cid` AS wf ON $ltbl.loan=wf.loan","$ltbl.*,wf.amount AS wamnt,wf.time AS wtym","<td>Writeoff</td><td>Date</td>"]
			);
			
			$join = "INNER JOIN ".$comp[$vtp][0]; $chk = $comp[$vtp][1]; $tdh = $comp[$vtp][2];
		}
		else{ $join=""; $chk="$ltbl.*"; $tdh="<td>Disbursement</td>"; }
		
		$show_curr_bal = []; $order="`balance` DESC";
		$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND (`category`='client' OR `category`='app' OR `category`='asset')");
		if($sql){
			foreach($sql as $row){
				$pdef = json_decode($row["pdef"],1); $prods[$row["id"]]=prepare(ucfirst($row["product"]));
				$sint = (isset($pdef["showinst"])) ? $pdef["showinst"]:0;
				if($sint){ $show_curr_bal[]=$row["id"]; }
			}
		}
		
		$tdy = strtotime("Today"); $no=$tbal=$tdsb=$tpaid=0; $trs=""; $dsbs=[]; 
		if($odb){
			$oxt = explode(":",$odb); $ocl=$oxt[0];
			$order = ($oxt[1]=="a") ? "`$ocl` ASC":"`$ocl` DESC";
		}
	
		$res = $db->query(2,"SELECT $chk FROM `$ltbl` $join WHERE $cond ORDER BY $order");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $lid=$row['loan']; $pid=$row['loan_product']; $expd=$row['expiry']; $prod=$prods[$pid];
				$amnt=fnum($row['amount']); $bal=$row['balance']+$row['penalty']; $paid=$row['paid']; $dsb=$kj=$row['disbursement'];
				if(in_array($pid,$show_curr_bal)){
					$sqr = $db->query(2,"SELECT SUM(paid) AS tpd,SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `day`<=$tdy");
					$roq = $sqr[0]; $bal=intval($roq['tbal']+$row['penalty']); $paid=intval($roq['tpd']);
				}
				
				$name=prepare(ucwords($row['client'])); $fon=$row['phone']; $ofname=$staff[$row['loan_officer']]; $idno=$row['client_idno'];
				$st=($vtp==1) ? "status":"expiry"; $disb=date("d-m-Y",$dsb); $exp=date("d-m-Y",$row[$st]); $dsbs[]=$dsb; $tpaid+=$paid;
				$tpy=fnum($bal+$paid); $prc=($bal>0) ? round($paid/($bal+$paid)*100,2):100; $tbal+=$bal; $tdsb+=$row['amount'];
					
				$color = ($tdy<=$expd) ? "green":"#DC143C"; $color=($vtp==1) ? "#7B68EE":$color; 
				$perc = ($vtp==1) ? "":"<td style='color:#9400D3;font-size:13px;'><b>$prc%</b></td>";
					
				$trh=($vtp==3) ? "<td>".date('d-m-Y',$row['start'])."</td><td>".$row['duration']." days</td><td>".fnum($row['fee'])."</td>":"<td>$disb</td>";
				$trh =($vtp==4) ? "<td>".fnum($row['wamnt'])."</td><td>".date('d-m-Y',$row['wtym'])."</td>":$trh; $lntd="";
				if(in_array($vtp,[0,2,3,5])){
					$tcss = "text-align:center;border-bottom:1px solid #F2F2F2;";
					if($tdy>$expd){ $lntd = "<td style='padding:5px;font-size:13px;background:#D2691E;color:#fff;$tcss'>Overdue</td>"; }
					elseif($expd==$tdy){ $lntd = "<td style='padding:5px;font-size:13px;background:#556B2F;color:#fff;width:100px;$tcss'>Maturing Today</td>"; }
					elseif($expd==($tdy+86400)){ $lntd = "<td style='padding:5px;font-size:13px;background:#483D8B;color:#fff;width:100px;$tcss'>Maturing Tmorow</td>"; }
					else{ $lntd = "<td style='padding:5px;font-size:13px;background:green;color:#fff;$tcss'>Active</td>"; }
				}
					
				$trs.= "<tr valign='top'><td>$no</td><td>$name</td><td>0$fon</td><td>$ofname</td><td>$prod</td>$trh<td>$amnt</td><td>$tpy</td>
				<td>".fnum($paid)."</td>$perc<td>".fnum($bal)."</td>$lntd<td style='color:$color'><i class='fa fa-circle'></i> $exp</td></tr>";
			}
		}
		
		if($trs){
			$tcol = ($vtp==3) ? 6:4; $tcol=($vtp==4) ? 5:$tcol; $tcol+=1; $tpr=($tbal) ? round($tpaid/($tbal+$tpaid)*100,2):100; $perc=($vtp==1) ? "":"<td>$tpr%</td>";
			$trs.= "<tr class='trh' style='background:#e6e6fa;'><td colspan='$tcol'></td><td>Totals</td><td>".fnum($tdsb)."</td><td>".fnum($tpaid+$tbal)."</td>
			<td>".fnum($tpaid)."</td>$perc<td>".fnum($tbal)."</td><td></td><td></td></tr>";
		}
		
		$stl = ($vtp==1) ? "Completed":"Maturity"; 
		$prctd = ($vtp==1) ? "":"<td>Percent</td>";
		$fro = ($fro) ? $fro:min($dsbs); $dto=($dto) ? $dto:max($dsbs);
		
		$data = "<p style='text-align:center'></p>
		<table cellpadding='5' style='width:100%'><tr>
			<td style='width:100px'><img src='data:image/$ext;base64,".getphoto($logo)."' height='70'></td>
			<td style='line-height:25px'><h2 style='color:#191970;'>$titles[$vtp]</h2><p><b>Disbursement From:</b> ".date("d-m-Y, h:i a",$fro)."</p>
			<p><b>Disbursement To:</b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".date("d-m-Y, h:i a",$dto)."</p></td>
			<td style='text-align:right;line-height:25px'>
				<p><b>Branch:</b> $bnames[$bran]</p><p><b>Loan Officer:</b> $lof</p>
			</td>
		</tr></table><hr>
		<h4 style='color:#2f4f4f;margin-top:0px'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td><td>Contact</td><td>Credit Officer</td><td>Product</td>$tdh<td>Amount</td>
			<td>To-Pay</td><td>Paid</td>$prctd<td>Balance</td><td>Status</td><td>$stl</td></tr> $trs
		</table>";
	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf','I');
	exit;

?>