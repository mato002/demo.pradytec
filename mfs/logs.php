<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	if(isset($_GET['view'])){
		$md=trim($_GET['md']);
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):strtotime(date("Y-M"));
		$day = (isset($_GET['day'])) ? trim($_GET['day']):0;
		$str = (isset($_GET['str'])) ? clean($_GET['str']):null;
		$stid = (isset($_GET['staff'])) ? trim($_GET['staff']):null;
		$ord = (isset($_GET['ord'])) ? trim($_GET['ord']):"ASC";
		
		$tbl="org".$cid."_staff"; $staff=[0=>"System"];
		$res = $db->query(2,"SELECT *FROM `$tbl`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		foreach(array("months",$mon) as $tbl){
			$cols = ($tbl=="months") ? "month INTEGER UNIQUE NOT NULL":"staff INTEGER,activity TEXT, day INTEGER, time INTEGER";
			insertSQLite("syslogs","CREATE TABLE IF NOT EXISTS '$tbl' (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,$cols)");
		}
		
		$mons=$dys=""; $days=[];
		$res1 = fetchSQLite("syslogs","SELECT *FROM `months` ORDER BY `month` DESC");
		if(is_array($res1)){
			foreach($res1 as $row){
				$mn=$row['month']; $cnd=($mon==$mn) ? "selected":"";
				$mons.="<option value='$mn' $cnd>".date('M Y',$mn)."</option>";
			}
		}
		
		$res2 = fetchSQLite("syslogs","SELECT DISTINCT day FROM `$mon` ORDER BY `day` DESC");
		if(is_array($res2)){
			foreach($res2 as $row){
				$dy=$row['day']; $days[]=$dy; $cnd=($day==$dy) ? "selected":"";
				$dys.="<option value='$dy' $cnd>".date('M d',$dy)."</option>";
			}
		}
		
		$day=(count($days) && $day==0) ? max($days):$day;
		$lim=getLimit($page,40);
		$cond = ($str) ? "`activity` LIKE '%$str%'":"`day`='$day' ";
		$cond.= ($stid!=null) ? " AND `staff`='$stid'":"";
		
		$opts = "<option value=''>-- Filter Staff --</option>";
		$res3 = fetchSQLite("syslogs","SELECT DISTINCT staff FROM `$mon` WHERE `day`='$day' AND NOT `staff`=''");
		if(is_array($res3)){
			foreach($res3 as $row){
				$st=$row['staff']; $cnd=($stid==$st) ? "selected":"";
				$opts.="<option value='$st' $cnd>".$staff[$st]."</option>";
			}
		}
		
		$trs =$css="";
		$res = fetchSQLite("syslogs","SELECT *FROM `$mon` WHERE $cond ORDER BY `time` $ord $lim");
		if(is_array($res)){
			foreach($res as $row){
				$dy=date("d-m-Y, H:i",$row['time']); $name=$staff[$row['staff']]; $des=nl2br(prepare($row['activity']));
				$trs.=($md>599) ? "<tr valign='top'><td style='width:160px'>$dy</td><td>$name</td><td>$des</td></tr>":
				"<tr valign='top'><td><div style='color:#2f4f4f;padding:10px;background:#f8f8ff;border-radius:5px;margin-top:10px'>
					<p style='margin:0px;color:#8A2BE2;'>$name</p><p style='margin:0px'>$des</p>
					<p style='text-align:right;color:#20B2AA;margin:0px'>$dy</p>
				</div></td></tr>";
			}
		}
		
		if($md>599){
			$css = "style='text-align:right'";
			$data = "<table style='width:100%;' class='table-striped' cellpadding='5'>
				<tr style='color:#191970;background:#e6e6fa;font-weight:bold'><td>Date</td><td>Staff</td><td>Activity</td></tr> $trs
			</table>";
		}
		else{ $data = "<table cellpading='10' style='width:100%;max-width:450px;margin:0 auto'>$trs</table>"; }
		
		$res = fetchSQLite("syslogs","SELECT COUNT(*) AS total FROM `$mon` WHERE $cond");
		$totals = (is_array($res)) ? $res[0]['total']:0; $lis="";
		foreach(array("ASC"=>"Oldest","DESC"=>"Latest") as $key=>$txt){
			$cnd = ($key==$ord) ? "selected":""; $lis.="<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div class='cardv' style='max-width:1000px;min-height:300px'>
			<div class='container' style='padding:5px'>
				<h3 style='font-size:22px;color:#191970'>$backbtn System Access Logs</h3><hr>
				<div class='row'>
					<div class='col-12 col-lg-6 mt-2'>
						<select style='width:110px' onchange=\"loadpage('logs.php?view&mon='+this.value)\">$mons</select>
						<select style='width:90px' onchange=\"loadpage('logs.php?view&mon=$mon&day='+this.value)\">$dys</select>
						<select style='width:90px;padding:6px 4px;' onchange=\"loadpage('logs.php?view&mon=$mon&day=$day&ord='+this.value)\">$lis</select>
					</div>
					<div class='col-12 col-lg-6 mt-2' $css>
						<select style='width:140px;font-size:15px' onchange=\"loadpage('logs.php?view&mon=$mon&day=$day&ord=$ord&staff='+this.value)\">$opts</select>
						<input type='search' style='width:150px;padding:4px;font-size:15px' placeholder='&#xf002; Search' value=\"".prepare($str)."\"
						onsearch=\"loadpage('logs.php?view&mon=$mon&day=$day&ord=$ord&str='+cleanstr(this.value))\">
					</div>
				</div><br> $data
			</div><br>".getLimitDiv($page,40,$totals,"logs.php?view&mon=$mon&day=$day&staff=$stid&ord=$ord&str=".str_replace(" ","+",$str))."
		</div>";
		
	}
	
	ob_end_flush();
?>