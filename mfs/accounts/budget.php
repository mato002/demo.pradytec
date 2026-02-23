<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# view budget
	if(isset($_GET["manage"])){
		$yr = ($_GET['manage']) ? trim($_GET["manage"]):date("Y");
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$mon = (isset($_GET["mon"])) ? trim($_GET["mon"]):strtotime(date("Y-M"));
		$acc = (isset($_GET["acc"])) ? trim($_GET["acc"]):0;
		$bid = (isset($_GET["brn"])) ? trim($_GET["brn"]):null;
		if(isset($_GET["vw"])){ $_SESSION["vbbr"]=trim($_GET["vw"]); }
		
		$me = staffInfo($sid); 
		$perms = getroles(explode(",",$me['roles'])); 
		if(!$db->istable(3,"budgets$cid")){
			$db->createTbl(3,"budgets$cid",array("book"=>"INT","branch"=>"INT","amount"=>"INT","details"=>"TEXT","month"=>"INT","year"=>"INT","time"=>"INT"));
		}
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE (`type`='expense' OR `type`='liability')");
		foreach($qri as $row){ $accs[$row['id']]=prepare(ucfirst($row['account'])); }
		
		$brans = array("Head Office");
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($sql){
			foreach($sql as $row){ $brans[$row['id']]=prepare(ucfirst($row['branch'])); }
		}
		
		$bid = ($me["access_level"]=="hq") ? $bid:$me["branch"];
		$cond = ($mon) ? "`month`='$mon'":"`year`='$yr'";
		$cond.= ($bid!=null) ? " AND `branch`='$bid'":"";
		$cond.= ($acc) ? " AND `book`='$acc'":""; 
		
		$lead = array("super user","Director","CEO","C.E.O");
		$lis=[]; $trs=""; $totals=$tspent=0; $tmon=strtotime(date("Y-M"));
		$sql = $db->query(3,"SELECT *FROM `budgets$cid` WHERE $cond ORDER BY `month` ASC");
		if($sql){
			foreach($sql as $row){
				$bk=$row['book']; $mn=date("M-Y",$row['month']); $amnt=$row['amount']; $bran=$brans[$row["branch"]]; 
				$def=json_decode($row['details'],1); $st=$def["status"]; $br=$row["branch"]; $mnn=$row['month'];
				$qri = ($bk==14) ? $db->query(3,"SELECT SUM(amount) AS tsum FROM `cashbook$cid` WHERE `month`='$mnn' AND `branch`='$br' AND `type`='credit'"):
				$db->query(3,"SELECT SUM(amount) AS tsum FROM `transactions$cid` WHERE `account`='$bk' AND `type`='debit' AND `month`='$mnn' AND `branch`='$br'");
				$spent = intval($qri[0]['tsum']); $book=$accs[$bk]; $lis[$mn][$book][$bran] = array("tsum"=>$amnt,"spent"=>$spent,"rid"=>$row["id"],"st"=>$st);
			}
		}
		
		if(count($lis)){
			$sbr = (isset($_SESSION["vbbr"])) ? $_SESSION["vbbr"]:1;
			$vbr = ($sbr) ? "<i class='bi-chevron-double-up' style='font-size:18px;float:right;cursor:pointer;color:#4682b4' title='Hide Multiple View' onclick=\"setview('0')\"></i>":
			"<i class='bi-list-stars' style='font-size:18px;float:right;cursor:pointer;color:#4682b4' title='Show Multiple View' onclick=\"setview('1')\"></i>";
			
			if($mon){
				$ths = "<td>Expense Account</td><td>Branch $vbr</td><td>Budget</td><td>Spent</td><td>Balance</td><td>Var</td>";
				foreach($lis as $mn=>$des){
					foreach($des as $li=>$one){
						$spn = count($one); $tds=$tls=""; $no=$tsum=$tsp=0;
						foreach($one as $br=>$def){
							$sum = $def["tsum"]; $used=$def["spent"]; $rid=$def['rid']; $perc=($sum>0) ? round(($sum-$used)/$sum*100,2):0; 
							$tsum+=$sum; $tsp+=$used; $act="$perc%"; $no++;
							$edit = (in_array("edit budget",$perms) && $mon>=$tmon) ? "<i class='bi-pencil-square' onclick=\"popupload('accounts/budget.php?create=$rid')\"
							style='cursor:pointer;font-size:18px;color:#008fff;float:right' title='Edit Budget'></i>":"";
							if(!$def["st"]){
								$act = (in_array("approve budget",$perms) or in_array($me["position"],$lead)) ? "<a href='javascript:void(0)' onclick=\"budgetst('$rid','1')\">Approve</a>
								| <a href='javascript:void(0)' style='color:#ff4500' onclick=\"budgetst('$rid','0')\">Delete</a>":"<i style='color:purple'>Unapproved</i>";
							}
							
							if($no==1){ $tds="<td>$br</td><td>$edit ".fnum($sum)."</td><td>".fnum($used)."</td><td>".fnum($sum-$used)."</td><td>$act</td>"; }
							else{ $tls.="<tr><td>$br</td><td>$edit ".fnum($sum)."</td><td>".fnum($used)."</td><td>".fnum($sum-$used)."</td><td>$act</td></tr>"; }
						}
						
						if($spn>1){
							$tpc = round(($tsum-$tsp)/$tsum*100,2); 
							if($sbr){
								$tls.= ($spn>1) ? "<tr style='font-weight:bold'><td>Totals</td><td>".fnum($tsum)."</td><td>".fnum($tsp)."</td>
								<td>".fnum($tsum-$tsp)."</td><td>$tpc%</td></tr>":""; $spn++; 
							}
							else{
								$tds = "<td>$spn branches</td><td>".fnum($tsum)."</td><td>".fnum($tsp)."</td>
								<td>".fnum($tsum-$tsp)."</td><td>$tpc%</td>"; $spn=1; $tls=""; 
							}
						}
						$trs.= "<tr valign='top'><td rowspan='$spn'>$li</td>$tds</tr>$tls"; $totals+=$tsum; $tspent+=$tsp;
					}
				}
				
				$trs.= "<tr style='background:#e6e6fa;font-weight:bold;color:#191970'><td colspan='2'></td><td>".fnum($totals)."</td>
				<td>".fnum($tspent)."</td><td>".fnum($totals-$tspent)."</td><td>".fnum(round(($totals-$tspent)/$totals*100,2))."%</td></tr>";
			}
			else{
				$ths = "<td>Expense Account</td><td>Month</td><td>Branch $vbr</td><td>Budget</td><td>Spent</td><td>Balance</td><td>Var</td>";
				foreach($lis as $mn=>$des){
					foreach($des as $li=>$one){
						$spn = count($one); $tds=$tls=""; $no=$tsum=$tsp=0;
						foreach($one as $br=>$def){
							$sum = $def["tsum"]; $used=$def["spent"]; $rid=$def['rid']; $perc=($sum>0) ? round(($sum-$used)/$sum*100,2):0; $tsum+=$sum; $tsp+=$used; $act="$perc%"; $no++;
							$edit = (in_array("edit budget",$perms) && strtotime($mn)>=$tmon) ? "<i class='bi-pencil-square' onclick=\"popupload('accounts/budget.php?create=$rid')\"
							style='cursor:pointer;font-size:18px;color:#008fff;float:right' title='Edit Budget'></i>":"";
							if(!$def["st"]){
								$act = (in_array("approve budget",$perms) or in_array($me["position"],$lead)) ? "<a href='javascript:void(0)' onclick=\"budgetst('$rid','1')\">Approve</a>
								| <a href='javascript:void(0)' style='color:#ff4500' onclick=\"budgetst('$rid','0')\">Delete</a>":"<i style='color:purple'>Unapproved</i>";
							}
							
							if($no==1){ $tds="<td>$br</td><td>$edit ".fnum($sum)."</td><td>".fnum($used)."</td><td>".fnum($sum-$used)."</td><td>$act</td>"; }
							else{ $tls.="<tr><td>$br</td><td>$edit ".fnum($sum)."</td><td>".fnum($used)."</td><td>".fnum($sum-$used)."</td><td>$act</td></tr>"; }
						}
						
						if($spn>1){
							$tpc = round(($tsum-$tsp)/$tsum*100,2); 
							if($sbr){
								$tls.= ($spn>1) ? "<tr style='font-weight:bold'><td>Totals</td><td>".fnum($tsum)."</td><td>".fnum($tsp)."</td>
								<td>".fnum($tsum-$tsp)."</td><td>$tpc%</td></tr>":""; $spn++; 
							}
							else{
								$tds = "<td>$spn branches</td><td>".fnum($tsum)."</td><td>".fnum($tsp)."</td>
								<td>".fnum($tsum-$tsp)."</td><td>$tpc%</td>"; $spn=1; $tls=""; 
							}
						}
						$trs.= "<tr valign='top'><td rowspan='$spn'>$li</td><td rowspan='$spn'>$mn</td>$tds</tr>$tls"; $totals+=$tsum; $tspent+=$tsp;
					}
				}
				
				$trs.= "<tr style='background:#e6e6fa;font-weight:bold;color:#191970'><td colspan='3'></td><td>".fnum($totals)."</td>
				<td>".fnum($tspent)."</td><td>".fnum($totals-$tspent)."</td><td>".fnum(round(($totals-$tspent)/$totals*100,2))."%</td></tr>";
			}
		}
		else{
			$ths = ($mon) ? "<td>Expense Account</td><td>Branch</td><td>Budget</td><td>Spent</td><td>Balance</td><td>Var</td>":
			"<td>Expense Account</td><td>Month</td><td>Branch</td><td>Budget</td><td>Spent</td><td>Balance</td><td>Var</td>";
		}
		
		$li1 = "<option value='".date("Y")."'>".date("Y")."</option>";
		$li2 = "<option value='0'>-- Month --</option>";
		$li3 = "<option value=''>-- Expense Book --</option>";
		$li4 = "<option value=''>-- Branch --</option>";
		
		foreach(array("year","month","branch","book") as $col){
			$fetch = ($col=="year") ? "NOT `year`='$yr'":"`year`='$yr'";
			$fetch.= ($me["access_level"]!="hq") ? " AND `branch`='$bid'":"";
			$sql = $db->query(3,"SELECT DISTINCT `$col` FROM `budgets$cid` WHERE $fetch");
			if($sql){
				foreach($sql as $row){
					if($col=="year"){ $val=$row[$col]; $cnd=($val==$yr) ? "selected":""; $li1.="<option value='$val' $cnd>$val</option>"; }
					if($col=="month"){ $val=$row[$col]; $cnd=($val==$mon) ? "selected":""; $li2.="<option value='$val' $cnd>".date("M-Y",$val)."</option>"; }
					if($col=="book"){ $val=$row[$col]; $cnd=($val==$acc) ? "selected":""; $li3.="<option value='$val' $cnd>$accs[$val]</option>"; }
					if($col=="branch"){ $val=$row[$col]; $cnd=($val==$bid) ? "selected":""; $li4.="<option value='$val' $cnd>$brans[$val]</option>"; }
				}
			}
		}
		
		$title = ($mon) ? date("M Y",$mon):$yr;
		$add = (in_array("create budget",$perms)) ? "<button class='bts' style='font-size:15px;float:right' onclick=\"popupload('accounts/budget.php?create')\">
		<i class='fa fa-plus'></i> Create</button>":"";
		
		echo "<div class='container cardv' style='overflow:auto;min-height:300px;max-width:1200px;'>
		<h3 style='font-size:22px;color:#191970'>$backbtn $title Budget $add</h3><hr style='margin-bottom:0px'>
			<div style='width:100%;overflow:auto'>
				<table style='width:100%;min-width:600px' cellpadding='5' class='table-bordered'>
					<caption style='caption-side:top'>
						<select style='width:80px' onchange=\"loadpage('accounts/budget.php?manage='+this.value)\">$li1</select>
						<select style='width:120px' onchange=\"loadpage('accounts/budget.php?manage=$yr&acc=$acc&mon='+this.value)\">$li2</select>
						<select style='width:180px' onchange=\"loadpage('accounts/budget.php?manage=$yr&mon=$mon&brn=$bid&acc='+this.value)\">$li3</select>
						<select style='width:150px' onchange=\"loadpage('accounts/budget.php?manage=$yr&mon=$mon&acc=$acc&brn='+this.value)\">$li4</select>
					</caption>
					<tr style='background:#e6e6fa;color:#191970;font-weight:bold'>$ths</tr> $trs
				</table>
			</div>
		</div><script>
			function setview(v){ loadpage('accounts/budget.php?manage=$yr&mon=$mon&acc=$acc&brn=$bid&vw='+v); }
		</script>";
		savelog($sid,"Viewed $title budget report");
	}
	
	# create budget
	if(isset($_GET["create"])){
		$bid = trim($_GET["create"]); 
		$acc=$lis=$amnt=$brn=""; $mon=date("Y-m");
		$me = staffInfo($sid);
		
		$cond = ($me["access_level"]=="hq") ? "":"AND `id`='".$me["branch"]."'";
		$monv = "<p>Target Month<br><input type='month' style='width:100%;padding:6px;border:1px solid #ccc;outline:none' name='bmon' value='$mon' required></p>";
		
		if($bid){
			$sql = $db->query(3,"SELECT *FROM `budgets$cid` WHERE `id`='$bid'");
			$row = $sql[0]; $acc=$row['book']; $brn=$row['branch']; $mon=date("Y-m",$row['month']); $amnt=$row['amount'];
			$monv = "<input type='hidden' name='bmon' value='$mon'>";
		}
		
		$qri = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `tree`='0' AND (`type`='expense' OR `type`='liability') ORDER BY `account` ASC");
		foreach($qri as $row){
			$rid=$row['id'];  $cnd=($rid==$acc) ? "selected":"";
			$lis.= "<option value='$rid' $cnd>".prepare(ucfirst($row['account']))."</option>";
		}
		
		$opts = "<option value='0'>Head Office</button>";
		$sql = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $cond ORDER BY `branch` ASC");
		if($sql){
			foreach($sql as $row){
				$rid=$row['id'];  $cnd=($rid==$brn) ? "selected":"";
				$opts.= "<option value='$rid' $cnd>".prepare(ucfirst($row['branch']))."</option>";
			}
		}
		
		$title = ($bid) ? "Edit Budget":"Create Budget";
		echo "<div style='margin:0 auto;padding:10px;max-width:330px'>
			<h3 style='font-size:22px;color:#191970;text-align:center'>$title</h3><br>
			<form method='post' id='bform' onsubmit=\"savebudget(event)\">
				<input type='hidden' name='bdgid' value='$bid'>
				<p>Expense Account<br><select style='width:100%' name='bacc' required>$lis</select></p>
				<p>Target Branch<br><select style='width:100%' name='bbran' required>$opts</select></p> $monv
				<p>Target Amount<br><input type='number' style='width:100%' name='budamt' value='$amnt' required></p><br>
				<p style='text-align:right'><button class='btnn'>Save</button></p>
			</form><br>
		</div>";
	}
	
	
	ob_end_flush();
?>

<script>
	
	function budgetst(rid,st){
		var txt = (st==1) ? "Proceed to approve the budget?":"Sure to delete the budget?";
		if(confirm(txt)){
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:{budgetst:rid,stval:st},
				beforeSend:function(){ progress("Processing...please wait"); },
				complete:function(){ progress(); }
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Success!"); window.location.reload();
				}
				else{ alert(res); }
			});
		}
	}
	
	function savebudget(e){
		e.preventDefault();
		if(confirm("Save budget details?")){
			var data = $("#bform").serialize();
			$.ajax({
				method:"post",url:path()+"dbsave/accounting.php",data:data,
				beforeSend:function(){ progress("Saving...please wait"); },
				complete:function(){progress();}
			}).fail(function(){
				toast("Failed: Check internet Connection");
			}).done(function(res){
				if(res.trim()=="success"){
					toast("Saved successfully!"); closepop(); loadpage("accounts/budget.php?manage");
				}
				else{ alert(res); }
			});
		}
	}
	
</script>