<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# create ticket
	if(isset($_GET['create'])){
		$ftc = trim($_GET['create']);
		$me = staffInfo($sid); $lis=$opts="";
		
		if($sid==1){
// 			echo send_money([254706719045=>3000],202312,1); 
            // require "../xls/index.php";
            // $data = openExcel("1tmtb.xlsx");
            // foreach($data as $k=>$one){
            //     if($k){
            //         $tmtb[trim($one[2])]=$one[1]; $tm=explode("-",$one[1]); $df=strtotime("2025-May-02,".trim($tm[1]))-strtotime("2025-May-02,".trim($tm[0]));
            //         $durs[trim($one[2])]=round($df/3600,2); $dys[trim($one[2])]=$one[0];
            //     }
            // }
           
            // $docs = array("WK4 JAN.xlsx","WK2 JAN.xlsx","WK3 JAN.xlsx","WK4 DEC.xlsx","WK1 FEB.xlsx");
            // foreach($docs as $doc){ 
            //     $coses=$dets=$days=$trs=$wds=[];
            //     foreach(openExcel("ds/$doc") as $k=>$one){ if(isset($one[5]) && $k && count($one)>=6){ $coses[$one[2]][$one[5]]=$one[3]; }}
            //     foreach($coses as $cos=>$all){
            //         if(!$dets){
            //             $dets = array("Course","Session","Day","Time"); $wds=array(20,15,18,18);
            //             foreach($coses as $c=>$a){
            //                 foreach($a as $k=>$v){
            //                     if(!in_array($k,$dets)){ $dets[]=$k; $days[]=$k; $wds[]=18; }
            //                 } 
            //             }
            //         }
                    
            //         $cos = trim($cos);
            //         $trs[$cos] = (isset($durs[$cos])) ? array($cos,$durs[$cos],$dys[$cos],$tmtb[$cos]):array($cos,"None","None","None");
            //         foreach($days as $dy){
            //             $trs[$cos][]=(isset($all[$dy])) ? "Taught":"";
            //         }
            //     }
                
            //     $dat = array([null,null],$dets); $data=array_merge($dat,$trs);
            //     $res = genExcel($data,"A1",$wds,"ds/gen-$doc","",null);
            // }
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`='0'");
		foreach($res as $row){
			$lis.=($sid!=$row['id']) ? "<option value='".$row['id']."'>".prepare(ucwords($row['name']))."</option>":"";
		}
		
		$list = array("Payments Support","Loans Support","Clientelle Support","Bank Withdrawal","Bank Deposit","Technical Support","System Support");
		foreach($list as $li){
			$opts.="<option value='$li'>$li</option>";
		}
		
		echo "<div style='max-width:380px;margin:0 auto;padding:10px'>
			<h3 style='color:#191970;font-size:22px;text-align:center'>Create a Ticket</h3><br>
			<form method='post' id='tform' onsubmit=\"sendticket(event)\">
				<p>Ticket subject<br><select style='width:100%;cursor:pointer' name='tsub'>$opts</select></p>
				<p>Message or Inquiry<br><textarea class='mssg' style='min-height:130px;' name='tmssg' required></textarea></p>
				<p>Send To<br><select style='width:100%;cursor:pointer' name='tto'>$lis</select></p><br>
				<p style='text-align:right'><button class='btnn'>Send</button></p><br>
			</form>
		</div>";
	}
	
	# view 
	if(isset($_GET['view'])){
		$md = trim($_GET['md']); $trs="";
		$px = ($_GET['md']>500) ? "8px":"4px";
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$lim = getLimit($page,15);
	
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$cnf = json_decode($row['config'],1); $prof=(isset($cnf["profile"])) ? $cnf['profile']:"none";
			$staff[$row['id']]=[prepare(ucwords($row['name'])),$prof];
		}
		
		$res = $db->query(2,"SELECT `ref`,MAX(time) AS tm FROM `tickets$cid` WHERE (`from`='$sid' OR `receiver`='$sid') GROUP BY `ref` ORDER BY tm DESC $lim");
		if($res){
			foreach($res as $rw){
				$ref = $rw['ref']; $ran=rand(12345678,87654321);
				$sql = $db->query(2,"SELECT COUNT(*) AS tot FROM `tickets$cid` WHERE `ref`='$ref'");
				$qri = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `ref`='$ref' ORDER BY `time` DESC LIMIT 1");
				
				$tot = $sql[0]['tot']; $row=$qri[0]; $subj=prepare($row['subject']); $fro=$row['from']; $mssg=explode("~>",ucfirst(prepare($row['message'])));
				$tym = date("d-m-Y, h:i a",$row['time']); $repl=$row['reply']; $rid=$row['id']; $sum = ($tot==1) ? "":"$tot Messages"; 
				$usa = ($fro==$sid) ? $row['receiver']:$fro; $pref=($fro==$sid) ? "To":"From"; $name=$staff[$usa][0]; $pic=$staff[$usa][1];
				$img = (substr(prepare($row['message']),0,5)=="sPIC:") ? explode(":",$mssg[0])[1]:null; 
				$chat = ($img) ? "<img src='data:image/jpg;base64,".getphoto($img)."' style='max-width:100%;max-height:200px'><br>".nl2br($mssg[1]):nl2br($mssg[0]);
				
				$prof = ($pic=="none") ? "<span style='font-size:30px'>".substr(ucfirst($name),0,1)."</span>":"<img src='data:image/jpg;base64,".getphoto($pic)."' 
				style='height:70px;border-radius:50%;border-top-right-radius:0px;'>";
				$topr = ($md>500) ? "<span style='float:right;font-size:15px'>$tym</span>":"<span style='float:right;font-size:14px'>($tot)</span>";
				$btnl = ($md>500) ? "<span style='color:grey;font-size:14px'>$sum</span>":"<span style='font-size:15px;color:#4682b4'>$tym</span>";
				
				$no = (!$repl && $fro!=$sid) ? $db->query(2,"SELECT COUNT(*) AS tot FROM `tickets$cid` WHERE `ref`='$ref' AND (`reply`='0' OR `reply` LIKE '888%') AND `receiver`='$sid'")[0]['tot']:0;
				$notbtn = ($no) ? "<button style='border-radius:50%;height:30px;width:30px;background:#FF6347;font-size:13px;margin-left:-35px;
				border:1px solid #FF6347;color:#fff' id='$ran'>$no</button>":"";
				$btx = ($tot>1) ? "<i class='fa fa-folder-open'></i> Open":"<i class='fa fa-reply'></i> Reply";
				
				$trs.= "<div style='background:#f5f5f5;border-radius:10px;border-top-left-radius:32px;' id='div$ran'>
					<table style='width:100%' cellpadding='0'><tr valign='top'>
						<td style='width:70px;text-align:center;'><div style='width:70px;height:70px;background:#e6e6fa;border-radius:50%;border-top-right-radius:0px;color:#4682b4;
						font-weight:bold;font-family:signika negative;line-height:65px;'>$prof</div></td>
						<td><h3 style='font-size:18px;color:#4682b4;padding:7px 10px;background:#e6e6fa;font-family:signika negative'>$notbtn &nbsp; $pref $name $topr</h3>
						<p style='padding:5px;margin:0px'><b>$subj</b><br>$chat</p><p style='padding-top:10px;'>$btnl 
						<button class='btnn' style='padding:4px;float:right;margin:0px 10px 10px 0px' onclick=\"openchat('$ref','$ran')\">$btx</button></p></td>
					</tr></table>
				</div><br>";
			}
		}
		
		$sql = $db->query(2,"SELECT DISTINCT `ref` FROM `tickets$cid` WHERE (`from`='$sid' OR `receiver`='$sid')");
		$totals = ($sql) ? count($sql):0;
		
		echo "<div class='cardv' style='max-width:850px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:$px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Support Tickets</h3><hr><br> $trs
			</div>".getLimitDiv($page,15,$totals,"tickets.php?view")."
		</div>";
	}
	
	# fetch chats
	if(isset($_GET['fetchchats'])){
		$src = explode(":",trim($_GET['fetchchats']));
		$ref = $src[1]; $to=$src[0]; $divs=""; $times=[];
		$fname = prepare(ucwords($db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `id`='$to'")[0]['name']));
		
		$qri = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `ref`='$ref'");
		foreach($qri as $row){
			$msg=prepare($row['message']); $mssg=explode("~>",ucfirst($msg)); $tym=date("d-m-Y, H:i",$row['time']); $from=$row['from']; $rid=$row['id'];
			$css = "clear:both;max-width:80%;padding:7px;border-radius:5px;margin-top:10px"; $cto=($from==$sid) ? $row['receiver']:$from; $sub=$row['subject'];
			$img = (substr(prepare($row['message']),0,5)=="sPIC:") ? explode(":",$mssg[0])[1]:null; $times[]=$row['time']; $reply=$repchat="";
			$chat = ($img) ? "<img src='data:image/jpg;base64,".getphoto($img)."' style='max-width:100%;'><br>".nl2br($mssg[1]):nl2br($mssg[0]);
			$code = ($sub=="B2C Inquiry" && strpos($msg,"transaction")!==false) ? explode(" ",substr($msg,strpos($msg,"transaction")))[1]:"";
			$isrep = (substr($row['reply'],0,2)==88) ? 1:0; $ran=rand(12345678,87654321);
			
			if($sub=="B2C Inquiry"){
				$reply = ($row['reply']<300) ? "<button class='btnn' style='padding:3px;float:left;margin-right:20px;min-width:60px;background:#6495ed'
				onclick=\"replyto('$code','$rid')\"><i class='fa fa-mail-forward'></i> Reply</button>":"";
				$reply = ($isrep && substr($row['reply'],0,3)==888) ? "<button class='btnn' id='$ran' style='padding:3px;float:left;margin-right:20px;min-width:60px;'
				onclick=\"popupload('accounts/index.php?postentry=".substr($row['reply'],3)."&ran=$ran')\"><i class='fa fa-upload'></i> Post</button>":$reply;
			}
			
			if($isrep){
				$sql = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `id`='".substr($row['reply'],3)."'");
				$repchat = ($sql) ? "<p style='background:#f0f0f0;color:#191970;border:1px solid #ccc;padding:7px;margin-bottom:5px'><i>".prepare(substr($sql[0]['message'],0,80))."...</i></p>":"";
			}
			
			$divs.= ($sid==$from) ? "<div style='$css;float:right;background:#E0FFFF;border:1px solid #ADD8E6;color:#4682b4'> $repchat
			<p style='margin-bottom:7px'>$chat</p> <p style='color:#9370DB;text-align:right;font-size:14px;margin:0px;font-family:signika negative'>$tym</p>
			</div>":"<div style='$css;float:left;background:#fff;border:1px solid #B0C4DE;color:#191970'>
				<p style='color:#4169E1;margin-bottom:7px;font-family:signika negative'>$fname</p> $repchat
				<p style='margin-bottom:7px'>$chat</p><p style='color:#9370DB;text-align:right;font-size:14px;margin:0px;font-family:signika negative'>$reply $tym</p>
			</div>";
		}
		
		$maxtm = (count($times)) ? max($times):time();
		$db->insert(2,"UPDATE `tickets$cid` SET `reply`='200' WHERE `ref`='$ref' AND `receiver`='$sid' AND `time`<=$maxtm AND `reply`<=200");
		echo "$divs <input type='hidden' id='maxtm' value='$maxtm'>";
		exit();
	}
	
	# check new chats
	if(isset($_POST['maxtm'])){
		$ref = explode(":",trim($_POST['maxtm']))[1];
		$res = $db->query(2,"SELECT MAX(time) AS mxtm FROM `tickets$cid` WHERE `ref`='$ref' ");
		echo ($res) ? $res[0]['mxtm']:0; exit();
	}
	
	ob_end_flush();
?>

<script>

	function openchat(ref,btn){
		if($(window).width()<500){
			window.open(path()+"chat.php?src=<?php echo $sid; ?>&ref="+ref, "chats"); $("#"+btn).hide();
		}
		else{
			window.open(path()+"chat.php?src=<?php echo $sid; ?>&ref="+ref, "chats", "toolbar=no,scrollbars=yes,resizable=no,width=500,height=650"); $("#"+btn).hide();
		}
	}
	
	function sendticket(e){
		e.preventDefault();
		if(confirm("Create new ticket to selected staff?")){
			var data=$("#tform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/tickets.php",data:data,
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); toast("Sent successfully!");
				}
				else{ alert(res); }
			});
		}
	}
	
	function savereply(e,id){
		e.preventDefault();
		if(confirm("Confirm to send your task reply?")){
			var data=$("#rform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/tickets.php",data:data,
				beforeSend:function(){ progress("Sending...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); $("#div"+id).hide(); toast("Sent successfully!");
				}
				else{ alert(res); }
			});
		}
	}
	
</script>