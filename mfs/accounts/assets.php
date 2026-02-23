<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# view assets
	if(isset($_GET['manage'])){
		$ftc = trim($_GET['manage']);
		$cond = ($ftc==null) ? 1:"`branch`='$ftc'";
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		
		$bnames = array(0=>"Head Office");
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($access=="hq"){
			$opts = "<option value=''>Corporate</option>";
			foreach($bnames as $bid=>$name){
				$cnd=($bid==$ftc && $ftc!=null) ? "selected":"";
				$opts.="<option value='$bid' $cnd>$name</option>";
			}
		}
		else{ $opts = "<option value='".$me['branch']."'>".$bnames[$me['branch']]."</option>"; }
		
		$trs=""; $no=$sum=0;
		$res = $db->query(3,"SELECT *FROM `assets$cid` WHERE $cond ORDER BY `item` ASC");
		if($res){
			foreach($res as $row){
				$item=prepare(ucwords($row['item'])); $cat=prepare($row['category']); $desc=prepare(ucfirst($row['details'])); 
				$recur = date('M d',strtotime($row['cycle'])); $cost=number_format($row['cost']); $rid=$row['id']; $no++;
				$dept=prepare(ucfirst($row['office'])); $depr=$row['depreciation']."% every $recur"; $sum+=$row['cost'];
				
				$trs.="<tr valign='top' onclick=\"popupload('accounts/assets.php?addasset=$rid')\" id='tr$rid'><td>$no</td>
				<td>$item</td><td>$cat</td><td>$desc</td><td>$dept</td><td>$cost</td><td>$depr</td></tr>";
			}
		}
		
		$css = ($trs) ? "margin-right:8px":"";
		$add = (in_array("manage company assets",$perms)) ? "<button class='bts' style='float:right;padding:2px;font-size:14px;$css' 
		onclick=\"popupload('accounts/assets.php?addasset')\"><i class='fa fa-plus'></i> Add New</button>":"";
		$prnt = ($trs) ? genrepDiv("assets.php?src=$ftc",'right'):"";
		$tsum = ($sum) ? "<span style='float:right;font-size:18px;font-weight:bold'>KES ".number_format($sum)."</span>":"";
		
		echo "<div class='cardv' style='max-width:1100px;min-height:300px;overflow:auto'>
			<div class='container' style='padding:5px;overflow:auto'>
				<h3 style='font-size:22px;color:#191970' onclick=\"loadpage('accounts/assets.php?manage')\">$backbtn Company Assets $tsum</h3>
				<table class='table-bordered table-striped btbl' style='width:100%;font-size:15px;margin-top:15px' cellpadding='5'>
					<caption style='caption-side:top'> $prnt $add
						<select style='padding:5px;width:170px;cursor:pointer' onchange=\"loadpage('accounts/assets.php?manage='+this.value)\">$opts</select>
					</caption>
					<tr style='background:#e6e6fa;color:#191970;cursor:default;font-weight:bold;font-size:14px'><td colspan='2'>Item</td>
					<td>Category</td><td>Description</td><td>Department/office</td><td>Cost</td><td>Depreciation</td></tr> $trs
				</table><br>
			</div>
		</div>";
		
		$bname = ($ftc==null) ? "Corporate":$bnames[$ftc];
		savelog($sid,"Viewed Company assets for $bname");
	}
	
	# add assets
	if(isset($_GET['addasset'])){
		$rid = trim($_GET['addasset']);
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$name=$bran=$cat=$dept=$desc=$cost=$ref=$acc=$cats=$opts=$pds=$depr=$del="";
		$title = "Add Company Asset"; $recur=date("Y-m-d"); 
		
		if($rid){
			$res = $db->query(3,"SELECT *FROM `assets$cid` WHERE `id`='$rid'");
			$row = $res[0]; $name=prepare(ucfirst($row['item'])); $bran=$row['branch']; $dept=prepare(ucfirst($row['office']));
			$desc = prepare(ucfirst($row['details'])); $cost=$row['cost']; $cat=$acc=$row['category']; $ref=$row['ref'];
			$title = $name; $depr=$row['depreciation']; $recur=$row['cycle'];
			$del = (in_array("manage company assets",$perms)) ? "<p style='text-align:right'><a href='javascript:void(0)' style='color:#DC143C' 
			onclick=\"delasset('$rid')\"><i class='fa fa-times'></i> Remove Item</a></p>":"";
		}
		
		if($access=="hq"){
			$brans = "<option value='0'>Head Office</option>";
			$qri = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			foreach($qri as $row){
				$cnd = ($bran==$row['id']) ? "selected":"";
				$brans.="<option value='".$row['id']."' $cnd>".prepare(ucwords($row['branch']))."</option>";
			}
			$bin = "<p>Branch<br><select name='tbran' id='bran' style='width:100%'>$brans</select></p>";
		}
		else{ $bin = "<input type='hidden' name='tbran' value='".$me['branch']."' id='bran'>"; }
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing`='1' ORDER BY `account` ASC");
		foreach($res as $key=>$row){
			$cnd=($cat==$row['id']) ? "selected":"";
			$cats.="<option value='".$row['id']."' $cnd>".prepare(ucwords($row['account']))."</option>";
			if(!$acc && $key==0){ $acc=$row['id']; }
		}
		
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing`='1,$acc'");
		if($res){
			foreach($res as $row){
				$id=$row['id']; $cnd=($id==$ref) ? "selected":"";
				$opts.="<option value='$id' $cnd>".prepare(ucwords($row['account']))."</option>";
			}
			$pds = "Select Account<br><select name='subcat' style='width:100%'>$opts</select>";
		}
		
		$btx = ($rid) ? "<i class='fa fa-refresh'></i> Update":"<i class='fa fa-plus'></i> Save";
		$submit = (in_array("manage company assets",$perms)) ? "<button class='btnn'>$btx</button>":"";
		
		echo "<div style='margin:0 auto;padding:10px;max-width:330px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>$title</h3><br> $del
			<form method='post' id='aform' onsubmit=\"saveasset(event,'$rid')\">
				<input type='hidden' name='itemid' value='$rid'> $bin
				<p>Department/office<br><input type='text' name='dept' style='width:100%' value=\"$dept\" required></p> 
				<p>Item Category<br><select style='width:100%' onchange=\"getsubs(this.value)\" name='categ'>$cats</select></p>
				<p id='subcat'>$pds</p>
				<p>Item Name<br><input type='text' name='itname' style='width:100%' value=\"$name\" required></p>
				<p>Item Description<br><input type='text' name='itdes' style='width:100%' value=\"$desc\" required></p>								
				<p>Item Cost <span style='float:right'>Depreciation (% p.a)</span><br>
				<input type='number' name='itcost' style='width:48%' value=\"$cost\" required>
				<input type='text' name='depr' id='depr' onkeyup=\"valid('depr',this.value)\" style='width:48%;float:right;' value=\"$depr\" required></p>
				<p>Always Depreciate on<br><input type='date' value='$recur' max='".date('Y-m-d')."' name='recur' style='width:100%' required></p><br>
				<p style='text-align:right'>$submit</p><br>
			</form>
		</div>";
	}
	
	# get subcategs
	if(isset($_POST['getsubs'])){
		$acc = trim($_POST['getsubs']); $opts="";
		$res = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `wing`='1,$acc'");
		if($res){
			foreach($res as $row){
				$opts.="<option value='".$row['id']."'>".prepare(ucwords($row['account']))."</option>";
			}
			echo "data ~ Select Account<br><select name='subcat' style='width:100%'>$opts</select>";
		}
		exit();
	}
	
	ob_end_flush();
?>

<script>
	
	function saveasset(e,rid){
		e.preventDefault();
		var txt = (rid>0) ? "update":"add";
		if(confirm("Sure to "+txt+" company asset?")){
			var data=$("#aform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("success!"); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function delasset(tid){
		if(confirm("Sure to remove company asset?")){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{delasset:tid},
				beforeSend:function(){ progress("Removing...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Removed!"); closepop(); $("#tr"+tid).remove();
				}
				else{ alert(res); }
			});
		}
	}
	
	function getsubs(val){
		$.ajax({
			method:"post",url:path()+"accounts/assets.php?sid=<?php echo $sid; ?>",data:{getsubs:val}
		}).done(function(res){
			if(res.split("~")[0].trim()=="data"){
				$("#subcat").html(res.trim().split("~")[1]);
			}
		});
	}

</script>