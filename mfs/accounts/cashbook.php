<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# post transaction
	if(isset($_GET['postrans'])){
		$tid = trim($_GET['postrans']); 
		$opts="<option value='0'>-- Select Account --</option>";
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $rid=$row['id'];
			$opts.=(!in_array($rid,array(12,15,17,21,25,23,14))) ? "<option value='$rid'>$acc</option>":"";
		}
		
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `id`='$tid'");
		if($res){
			$row=$res[0]; $bran=$row['branch']; $amnt=$row['amount']; $des=prepare(ucfirst($row['transaction']));
			$trans=$row['transid']; $day=date("Y-m-d",$row['time']); $tdy=date("Y-m-d");
			
			echo "<div style='padding:10px;margin:0 auto;max-width:340px'>
				<h3 style='text-align:center;color:#191970;font-size:22px'>Post Cashbook Transaction</h3><br>
				<input type='hidden' id='options' value=\"$opts\">
				<form method='post' id='pform' onsubmit=\"postrans(event,'$tid')\">
					<input type='hidden' name='pbran' value='$bran'> <input type='hidden' name='tamnt' value='$amnt'> 
					<table class='dtbl' style='width:100%' cellpadding='5'>
						<tr><td style='width:65%'>Debit Account <i class='fa fa-plus' title='Add Debit Account'
						style='font-size:19px;float:right;color:#008fff;cursor:pointer' onclick=\"addrow('debit')\"></i><br> 
						<select name='debs[]' style='width:100%;font-size:15px;color:#191970'>$opts</select></td><td>Amount<br><input type='number' name='damnt[]' 
						style='width:100%' value='$amnt' required></td></tr>
					</table>
					<input type='hidden' name='camnt[]' value='$amnt' required> <input type='hidden' name='creds[]' value='14' required>
					<p style='margin:10px 5px 15px 5px;'>Date <span style='float:right'>Ref No</span><br> 
					<input type='date' value='$day' style='width:55%;color:#191970;font-size:15px;' name='tday' max='$tdy' required>
					<input type='text' name='refno' value='$trans' style='float:right;width:43%;cursor:not-allowed' readonly></p>
					<p style='margin:15px 5px'>Transaction Details<br><input type='text' style='width:100%' name='transdes' value=\"$des\" required></p>
					<p style='margin:0px 5px'>Comments<br><input type='text' style='width:100%' name='tcomm'></p><br>
					<p style='text-align:right;'><button class='btnn'>Post</button></p>
				</form><br>
			</div>";
		}
		else{ echo "<p style='text-align:center;margin-top:50px;color:#ff4500'><b>Ooops! Transaction is not found!</b></p>"; }
	}
	
	# create transaction
	if(isset($_GET['addrec'])){
		$me = staffInfo($sid); $access=$me['access_level'];
		$mxd = date('Y-m-d'); $cnf=json_decode($me["config"],1);
		
		if(in_array($access,["hq","region"])){
			$brans = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			foreach($qri as $row){
				$brans.="<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>";
			}
			$bin = "<p>Branch<br><select name='tbran' id='bran' style='width:100%'>$brans</select></p>";
		}
		else{ $bin = "<input type='hidden' name='tbran' value='".$me['branch']."' id='bran'>"; }
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Record Transaction</h3><br>
			<form method='post' id='pform' onsubmit='savetrans(event)'> $bin
				<p>Transaction details<br><textarea class='mssg' style='height:80px' name='tdes' required></textarea></p> 
				<p>Amount<br><input type='number' name='tamnt' style='width:100%' required></p>
				<p>Date of Transaction<br><input type='date' name='tday' style='width:100%' max='$mxd' value='$mxd' required></p><br>
				<p style='text-align:right'><button class='btnn'>Post</button></p>
			</form><br>
		</div>";
	}
	
	# add float
	if(isset($_GET['addfloat'])){
		$me = staffInfo($sid); $access=$me['access_level']; 
		$cnf=json_decode($me["config"],1); $opts = "";
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`type`='asset' OR `type`='income') ORDER BY `account` ASC");
		foreach($res as $row){
			$acc=prepare(ucwords($row['account'])); $tid=$row['id'];
			$opts.=(!in_array($tid,array(12,14,15,17,21,23))) ? "<option value='$tid'>$acc</option>":"";
		}
		
		if(in_array($access,["hq","region"])){
			$brans = "<option value='0'>Head Office</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			foreach($qri as $row){
				$brans.="<option value='".$row['id']."'>".prepare(ucwords($row['branch']))."</option>";
			}
			$bin = "<p>Topup to branch<br><select name='fbran' id='bran' style='width:100%'>$brans</select></p>";
		}
		else{ $bin = "<input type='hidden' name='fbran' value='".$me['branch']."' id='bran'>"; }
		
		echo "<div style='padding:10px;margin:0 auto;max-width:300px'>
			<h3 style='text-align:center;color:#191970;font-size:22px'>Topup Account Float</h3><br>
			<form method='post' id='tform' onsubmit='savefloat(event)'>
				<p>Withdraw From<br><select name='facc' style='width:100%' required>$opts</select></p> $bin
				<p>Amount<br><input type='number' name='famnt' style='width:100%' required></p><br>
				<p style='text-align:right'><button class='btnn'>Topup</button></p>
			</form><br>
		</div>";
	}
	
	# view cashbook
	if(isset($_GET['view'])){
		$tmon = strtotime(date("Y-M"));
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):$tmon;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):null;
		$wk = (isset($_GET['wk'])) ? trim($_GET['wk']):0;
		$week = ($mon==$tmon && !isset($_GET['wk'])) ? date("W"):$wk;
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$cnf = json_decode($me["config"],1);
		
		$bnames = array(0=>"Corporate");
		$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
		$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
		foreach($qri as $row){
			$bnames[$row['id']]=prepare(ucwords($row['branch']));
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name'])); $mybran[$row['id']]=$row['branch'];
		}
		
		$mons = "<option value='$tmon'>".date("M Y")."</option>";
		$qry = $db->query(3,"SELECT DISTINCT `month` FROM `cashbook$cid` WHERE `month`<$tmon ORDER BY `month` DESC");
		if($qry){
			foreach($qry as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date("F Y",$mn)."</option>";
			}
		}
		
		$wks = "<option value='0'>All Weeks</option>"; $wavs=[]; $twk=date("W");
		$res = $db->query(3,"SELECT DISTINCT `week` FROM `cashbook$cid` WHERE `month`='$mon' ORDER BY week ASC");
		if($res){
			foreach($res as $row){
				$wk=$row['week']; $cnd=($wk==$week) ? "selected":""; $wkrange=weekrange($wk,date("Y",$mon)); $wavs[]=$wk;
				$wks.="<option value='$wk' $cnd>".date('M d',$wkrange[0])." - ".date('M d',$wkrange[1])."</option>";
			}
		}
		
		if($week==$twk && !in_array($twk,$wavs)){
			$wkrange=weekrange($twk,date("Y",$mon));
			$wks.="<option value='$twk' selected>".date('M d',$wkrange[0])." - ".date('M d',$wkrange[1])."</option>";
		}
		
		if($bran!=null){ $me['access_level']="branch"; $me['branch']=$bran; }
		$show = ($me['access_level']=="hq") ? "":"AND branch='".$me['branch']."'";
		$cond = ($me['access_level']=="portfolio") ? "AND `staff`='$sid'":$show;
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
		$cond.= ($week) ? " AND `week`='$week'":"";
		
		$res = $db->query(3,"SELECT SUM(opening) AS opened FROM `cashfloats$cid` WHERE `month`='$mon' $show");
		$obal = ($res) ? $res[0]['opened']:0; $brans=""; $all=1; $totals=0;
		
		if($week){
			$res1 = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `cashbook$cid` WHERE `month`='$mon' AND `week`<$week AND `type`='debit' $show");
			$res2 = $db->query(3,"SELECT SUM(amount) AS tamnt FROM `cashbook$cid` WHERE `month`='$mon' AND `week`<$week AND `type`='credit' $show");
			$added = ($res1) ? $res1[0]['tamnt']:0; $spent = ($res2) ? $res2[0]['tamnt']:0; $tfloat=($obal+$added)-$spent;
			
			$day = date("d-m-Y",weekrange($week,date("Y",$mon))[0]); $otx=($tfloat>=0) ? "Balance B/D":"Balance C/F";
			$trs = "<tr valign='top'><td>".str_replace("-","",number_format($tfloat))."</td><td>$day</td><td>$otx</td><td></td><td></td><td></td></tr>";
		}
		else{
			$day = date("d-m-Y",$mon); $otx=($obal>=0) ? "Balance B/D":"Balance C/F"; $tfloat=$obal;
			$trs = "<tr valign='top'><td>".str_replace("-","",number_format($obal))."</td><td>$day</td><td>$otx</td><td></td><td></td><td></td></tr>";
		}
		
		# floats
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `type`='debit' AND `month`='$mon' $cond ORDER BY `time` ASC");
		if($res){
			foreach($res as $row){
				$amnt=number_format($row['amount']); $day=date("d-m-Y",$row['time']); $trans=prepare(ucfirst($row['transaction']));
				$sno=prenum($row['id']); $usa=$staff[$row['cashier']]; $tfloat+=$row['amount']; $all++;
				$trs.= "<tr valign='top'><td>$amnt</td><td>$day</td><td>$trans</td><td>$sno</td><td style='text-align:right'>0</td><td>$usa</td></tr>";
			}
		}
		
		# expenditure
		$res = $db->query(3,"SELECT *FROM `cashbook$cid` WHERE `type`='credit' AND `month`='$mon' $cond ORDER BY `time` ASC");
		if($res){
			foreach($res as $row){
				$amnt=number_format($row['amount']); $day=date("d-m-Y",$row['time']); $trans=prepare(ucfirst($row['transaction']));
				$sno=prenum($row['id']); $usa=$staff[$row['cashier']]; $rid=$row['id']; $totals+=$row['amount']; $all++;
				
				$post = ($row['status']==0 && in_array("post journal entry",$perms)) ? "<a href='javascript:void(0)' id='p$rid' style='float:left'
				onclick=\"popupload('accounts/cashbook.php?postrans=$rid')\">Post</a>":"";
				$del = ($row['status']==0 && in_array("delete cashbook record",$perms)) ? "<a href='javascript:void(0)' id='d$rid' style='color:#ff4500;float:right' 
				onclick=\"delcrec('$rid')\">Delete</a>":"";
				
				$trs.= "<tr valign='top'><td style='text-align:right'>$post $amnt</td><td>$day</td><td>$trans</td><td>$sno</td>
				<td style='text-align:right'>$amnt</td><td>$usa $del</td></tr>";
			}
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value=''>Corporate</option>";
			foreach($bnames as $bid=>$bname){
				$cnd=($bran==$bid && $bran!=null) ? "selected":""; 
				$brn.="<option value='$bid' $cnd>$bname</option>";
			}
			$brans = "<select style='width:130px;font-size:15px' onchange=\"loadpage('accounts/cashbook.php?view&mon=$mon&wk=$week&bran='+this.value)\">$brn</select>";
		}
		else{ $bran=$me['branch']; }
		
		$rem=($all>14) ? 0:14-$all; $ramnt=$tfloat-$totals;
		$cf = ($ramnt<0) ? str_replace("-","",number_format($ramnt)):0; $bd = ($ramnt>=0) ? number_format($ramnt):0; 
		for($i=1; $i<=$rem; $i++){ $trs.="<tr><td><span style='color:#F8F8FF'>.</span></td><td></td><td></td><td></td><td></td><td></td></tr>"; }
		
		$css = "border-bottom:1px solid #F8F8FF;border-top:1px solid #F8F8FF";
		$trs.="<tr style='color:#191970;font-weight:bold;border-top:1px solid #ccc'><td>".number_format($tfloat)."</td>
		<td colspan='4' style='text-align:right;padding:4px 7px;'>".number_format($totals)."</td><td style='$css'></td></tr>
		<tr style='color:#191970;border-top:1px solid #ccc'><td style='padding:2px 7px;font-weight:bold'>$cf</td><td>Balance C/F</td><td colspan='4'></td></tr>
		<tr style='color:#191970;'><td style='padding:2px 7px;font-weight:bold'>$bd</td><td>Balance B/D</td><td colspan='4'></td></tr>";
		
		$last = date("d-m-Y",monrange(date("m",$mon),date("Y",$mon))[1]);
		$title = ($mon==$tmon) ? "For ".date("F Y",$mon)." Ending $last":"For ".date("F Y",$mon)." Ended $last";
		
		$print = ($trs) ? "<button class='btnn' style='float:right;padding:5px;min-width:50px;background:#DC143C;margin-left:5px'
		onclick=\"printdoc('cashbook.php?src=$mon&wk=$week&br=$bran','Cashbook')\"><i class='fa fa-print'></i> Print</button>":"";
		$create = (in_array("manage cashbook",$perms)) ? "<button class='bts' style='float:right;padding:3px;font-size:15px;'
		onclick=\"popupload('accounts/cashbook.php?addrec')\"><i class='fa fa-plus'></i> Record</button>":"";
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='addfloat'"); $sett = ($res) ? $res[0]['value']:1;
		$addfloat = ($sett && in_array("add cashbook float",$perms)) ? "<button class='btnn' style='float:right;padding:5px;min-width:50px;margin-left:5px'
		onclick=\"popupload('accounts/cashbook.php?addfloat')\"><i class='fa fa-plus'></i> Float</button>":"";
		
		echo "<div class='container cardv' style='max-width:1300px;overflow:auto;min-height:400px;padding:2px'>
			<h3 style='font-size:22px;text-align:center;color:#191970;margin-top:10px'><u>Petty Cashbook</u></h3>
			<h3 style='font-size:20px;color:#191970;text-align:center'>$title</h3>
			<div style='min-width:700px;padding:10px'>
				<table class='cashtbl'>
					<caption style='caption-side:top'>
						$print $addfloat $create 
						<select style='width:110px;font-size:15px' onchange=\"loadpage('accounts/cashbook.php?view&mon='+this.value)\">$mons</select>
						<select style='width:150px;font-size:15px' onchange=\"loadpage('accounts/cashbook.php?view&mon=$mon&wk='+this.value)\">$wks</select> $brans
					</caption>
					<tr><td style='width:130px'>Receipts</td><td style='width:95px'>Date</td><td>Details</td><td style='width:60px'>SN</td>
					<td style='width:90px'>Totals</td><td>Cashier</td></tr> $trs
				</table>
			</div>
		</div>";
		
		savelog($sid,"Accessed Petty Cashbook for ".date("F Y",$mon));
	}

	ob_end_flush();
?>

<script>
	
	function addrow(tp){
		var did=Date.now(),opts=_("options").value.trim(); 
		if(tp=="debit"){
			$(".dtbl").append("<tr id='"+did+"'><td><select name='debs[]' style='width:100%;font-size:15px;color:#191970'>"+opts+"</select></td>"+
			"<td><input type='number' name='damnt[]' style='width:80%' value='0' required><i class='fa fa-times' onclick=\"$('#"+did+"').remove()\""+
			"style='color:#ff4500;font-size:17px;float:right;cursor:pointer;' title='Remove row'></i></td></tr>");
		}
		else{
			$(".ctbl").append("<tr id='"+did+"'><td><select name='creds[]' style='width:100%;font-size:15px;color:#191970'>"+opts+"</select></td>"+
			"<td><input type='number' name='camnt[]' style='width:80%' value='0' required><i class='fa fa-times' onclick=\"$('#"+did+"').remove()\""+
			"style='color:#ff4500;font-size:17px;float:right;cursor:pointer;' title='Remove row'></i></td></tr>");
		}
	}
	
	function delcrec(rid){
		if(confirm("Sure to delete cashbook transaction?")){
			$.ajax({
				method:"post",url:path()+"dbsave/cashbook.php",data:{delrecord:rid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Removed successfully"); window.location.reload(); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function postrans(e,tid){
		e.preventDefault();
		if(confirm("Post cashbook transaction?")){
			var data=$("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/cashbook.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); $("#p"+tid).html(""); $("#d"+tid).html(""); toast("Posted Successfully!");
				}
				else{ alert(res); }
			});
		}
	}
	
	function savetrans(e){
		e.preventDefault();
		if(confirm("Create cashbook transaction?")){
			var data=$("#pform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/cashbook.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); loadpage("accounts/cashbook.php?view&bran="+_("bran").value.trim()); 
				}
				else{ alert(res); }
			});
		}
	}
	
	function savefloat(e){
		e.preventDefault();
		if(confirm("Continue to topup branch float?")){
			var data=$("#tform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/cashbook.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					closepop(); loadpage("accounts/cashbook.php?view&bran="+_("bran").value.trim()); 
				}
				else{ alert(res); }
			});
		}
	}
	
</script>