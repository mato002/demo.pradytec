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
	$cond = base64_decode(str_replace(" ","+",trim($_GET['src'])));
	$vtp = trim($_GET['v']);
	$mon = trim($_GET['mn']);
	$day = trim($_GET['dy']);
	$bran = trim($_GET['bran']);
	
	$db = new DBO(); $cid = CLIENT_ID;
	$info = mficlient(); $logo=$info['logo']; $ext=explode(".",$logo)[1];
	
	$brans=[0=>"Head Office"];
	$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); 
	if($res){
		foreach($res as $row){ $brans[$row['id']]=prepare(ucwords($row['branch'])); }
	}
	
	$states = array("All templates","Disbursed Loans","Undisbursed Loans","Pended Loans","Declined Loans");
	$dur = ($day) ? date("M d,Y",$day):date("F Y",$mon);
	$title = $brans[$bran]." ".$states[$vtp]." for $dur";
	
	require_once __DIR__ . '/../vendor/autoload.php';
	$mpdf = new \Mpdf\Mpdf(['mode'=>'c','format'=>'A4-L']);
	$mpdf->SetDisplayMode('fullpage');
	$mpdf->mirrorMargins = 1;
	$mpdf->defaultPageNumStyle = '1';
	$mpdf->setHeader();
	$mpdf->AddPage('L');
	$mpdf->SetAuthor("PradyTech");
	$mpdf->SetCreator("Prady MFI System");
	$mpdf->SetTitle("Loan Disbursements");
	$mpdf->setFooter('<p style="text-align:center"> '.$title.' : Page {PAGENO}</p>');
	$mpdf->WriteHTML("
		*{margin:0px;}
		.tbl{width:100%;font-size:15px;font-family:cambria;}
		.tbl td{font-size:13px;}
		.tbl tr:nth-child(odd){background:#f0f0f0;}
		.trh td{font-weight:bold;color:#191970;font-size:13px;}
	",1);
	
	$ltbl = "org".$cid."_loantemplates";
	$fields = ($db->istable(2,$ltbl)) ? $db->tableFields(2,$ltbl):[];
	$exclude = ["id","loan","time","status","payment","client","client_idno","phone","duration","prepay","checkoff","approvals","creator","branch","pref","loantype","comments","cuts","disbursed"];
	
	if($vtp==1){
		unset($exclude[array_search("branch",$exclude)]); $exclude[]="processing"; 
		$hidecols = (defined("HIDE_TEMPLATE_COLS")) ? HIDE_TEMPLATE_COLS:[];
		foreach($hidecols as $col){ $exclude[]=$col; }
	}
		
	$cfield = ($vtp==1) ? "status":"time";
	$cfield = ($vtp==2) ? "time":$cfield; $docs=$dels=$skip=[];
	if(!is_dir("../vendor/tmp")){ mkdir("../vendor/tmp",0777,true); }
		
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='org".$cid."_loans'"); 
		if($res){
			$cols = json_decode($res[0]['fields'],1);
			if(in_array("image",$cols) or in_array("pdf",$cols) or in_array("docx",$cols)){
				foreach($cols as $col=>$dtp){ 
					if($dtp=="image"){
						$res = $db->query(2,"SELECT $col,id FROM `$ltbl` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; 
								if($df){ file_put_contents("../vendor/tmp/$df",base64_decode(getphoto($df))); $dels[]=$df; }
								$img = ($df) ? "../vendor/tmp/$df":"$path/assets/img/user.png";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;'>";
							}
						}
					}
					if($dtp=="pdf" or $dtp=="docx"){
						$res = $db->query(2,"SELECT $col,id FROM `$ltbl` WHERE $cond");
						if($res){
							foreach($res as $row){
								$df=$row[$col]; $id=$row['id']; $img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
								$docs[$col.$row['id']] = "<img src='$img' style='height:50px;'>"; $skip[$col.$row["id"]]=$col;
							}
						}
					}
				}
			}
		}
		
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){ $lprods[$row['id']]=prepare(ucwords($row['product'])); }
		}
	
		$staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$app = $db->query(1,"SELECT *FROM `approvals` WHERE `client`='$cid' AND `type`='loantemplate'");
		$levels = ($app) ? json_decode($app[0]['levels'],1):[]; $dcd=count($levels);
		
		$dc = $db->query(2,"SELECT SUM(prepay+checkoff) AS deds FROM `$ltbl` WHERE $cond");
		$deds = ($dc) ? $dc[0]['deds']:0; $ctd=0;
		
		$no=0; $trs="";
		$res = $db->query(2,"SELECT *FROM `$ltbl` WHERE $cond ORDER BY $cfield DESC");
		if($res){
			foreach($res as $row){
				$no++; $rid=$row['id']; $idno=$row["client_idno"]; $tds=$aptd=$cltd="";
				if($dcd){
					$dis+=($row['status']==$dcd && $row['status']<9) ? 1:0; $nx=0;
					$status = array(10=>"<span style='color:green;'>".date('d.m.Y,H:i',$row['status'])."</span>");
					foreach($levels as $pos=>$user){
						$key=$pos-1; $nx+=$pos; 
						$status[$key]="<i style='color:grey;font-size:13px'>Waiting ".prepare(ucfirst($user))."</i>";
					}
						
					$status[8]="<i style='color:#9932CC;'>Pending</i>"; $status[9]="<i style='color:#ff4500;'>Declined</i>";
				}
				else{ $status=array(0=>"<i style='color:#9932CC;'>Pending</i>",10=>"<span style='color:green;'>".date('d.m.Y,H:i',$row['status'])."</span>"); }
				
				if($vtp==1){
					$cyc = $db->query(2,"SELECT `cycles` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['cycles'];
					$cltd = "<td>".fnum($cyc)."</td>";
				}
					
				foreach($fields as $col){
					if(!in_array($col,$exclude) && !in_array($col,$skip)){
						$val=($col=="branch") ? $brans[$row[$col]]:prepare(ucfirst($row[$col]));
						$val=($col=="loan_officer") ? $staff[$row[$col]]:$val;
						$val=($col=="creator") ? $staff[$row[$col]]:$val;
						$val=($col=="loan_product") ? $lprods[$row[$col]]:$val;
						$val=(isset($docs[$col.$row['id']])) ? $docs[$col.$row['id']]:$val;
						$val=($col=="amount") ? "- KES ".fnum($row[$col])."<br>- For ".$row['duration']." days":$val;
						if($col=="processing"){ $lis="";
							foreach(json_decode($row['processing'],1) as $pay=>$amnt){
								$lis.="<li>".ucwords(str_replace("_"," ",$pay))."($amnt)</li>";
							}
							$val=$lis; $col="Charges";
						}
						$tds.="<td>$val</td>"; $ths.=($no==1) ? "<td>".ucfirst(str_replace("_"," ",$col))."</td>":"";
					}
				}
				
				if(in_array("comments",$fields)){
					$arr = ($row['comments']==null) ? []:json_decode($row['comments'],1);
					foreach($arr as $usa=>$com){
						$msg = ($com) ? prepare($com):"Ok";
						$aptd.="<li style='list-style:none'><b>".explode(" ",$staff[$usa])[0].":</b> <i>$msg</i>";
					}
					$tds.= ($aptd) ? "<td>$aptd</td>":"<td>None</td>"; $ctd+=1;
				}
					
				$rid=$row['id']; $prepay=fnum($row['prepay']); $cko=fnum($row['checkoff']); $name=prepare(ucwords($row['client'])); $fon=$row['phone']; 
				$tds.= ($deds) ? "<td>Checkoff($cko)<br>Prepayment($prepay)</td>":""; $st=($row['status']>10) ? 10:$row['status'];
				$trs.= "<tr valign='top'><td>$no</td><td>$name<br>0$fon</td>$cltd $tds<td>".$status[$st]."</td></tr>";
			}
		}
		
		$cltd = ($vtp==1) ? "<td>Cycles</td>":"";
		$ths.= ($ctd) ? "<td>Approvals</td>":""; $ths.=($deds) ? "<td>Deductions</td>":""; 
		$data = "<p style='text-align:center'><img src='data:image/$ext;base64,".getphoto($logo)."' height='80'></p>
		<h3 style='color:#191970;text-align:center;'>$title</h3>
		<h4 style='color:#2f4f4f'>Printed on ".date('M d,Y - h:i a')." by ".$staff[$by]."</h4>
		<table cellpadding='5' cellspacing='0' class='tbl'>
			<tr style='background:#e6e6fa;' class='trh'><td colspan='2'>Client</td>$cltd $ths<td>Disbursement</td></tr> $trs
		</table>";

	
	if($res = protectDocs($by)){
		$pass = $res['password'];
		$mpdf->SetProtection(array('copy','print'), $pass, "mfimpdf.21");
	}
	
	$mpdf->WriteHTML($data);
	$mpdf->Output(str_replace(" ","_",$title).'.pdf',"I");
	foreach($dels as $del){ unlink("../vendor/tmp/$del"); }
	exit;

?>