<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# view targets
	if(isset($_GET['view'])){
		$mon = (isset($_GET['mon'])) ? trim($_GET['mon']):0;
		$rgn = (isset($_GET['reg'])) ? trim($_GET['reg']):0;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$tmon = strtotime(date("Y-M")); $tdy=strtotime(date("Y-M-d"));
		
		$me = staffInfo($sid); $access=$me['access_level'];
		$perms = getroles(explode(",",$me['roles']));
		$cnf = json_decode($me["config"],1);
		$tbl = "targets$cid"; $stbl="org".$cid."_staff";
		
		if(!$db->istable(2,$tbl)){ $db->createTbl(2,$tbl,["branch"=>"INT","officer"=>"INT","month"=>"INT","year"=>"INT","results"=>"TEXT","data"=>"TEXT"]); }
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting` IN ('perfkpis','officers','targetsb4','targetcollagents') AND `client`='$cid'");
		if($res){
			foreach($res as $row){ $setts[$row["setting"]]=prepare($row["value"]); }
		}
		
		$kpis = (isset($setts["perfkpis"])) ? json_decode($setts["perfkpis"],1):KPI_LIST;
		$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'"); $bnames[]="Corporate";
		if($res){
			foreach($res as $row){ $bnames[$row['id']]=prepare(ucwords($row['branch'])); }
		}
		
		$post = (isset($setts["officers"])) ? $setts["officers"]:"loan officer"; $mons="";
		$targcol = (isset($setts["targetcollagents"])) ? $setts["targetcollagents"]:0; $posts=$months=[];
		$lofg = (defined("WORK_AS_OFFICERS")) ? WORK_AS_OFFICERS:[];
		
		$res = $db->query(2,"SELECT *FROM `$stbl`");
		foreach($res as $row){
			$id=$row['id']; $staff[$id]=prepare(ucwords($row['name'])); $mybran[$id]=$row['branch']; $def=json_decode($row["config"],1);
			if(in_array($row["position"],$lofg)){ $posts[$id]="'$id'"; }
			if($targcol && $row["position"]=="collection agent"){ $posts[$id]="'$id'"; }
			if(isset($def["mypost"])){
				foreach($def["mypost"] as $pos=>$val){
					if($pos==$post && $row["position"]!=$post && !isset($posts[$id])){ $posts[$id]="'$id'"; }
					if(in_array($pos,$lofg) && $row["position"]!=$post && !isset($posts[$id])){ $posts[$id]="'$id'"; }
					if($targcol && $pos=="collection agent" && !isset($posts[$id])){ $posts[$id]="'$id'"; }
				}
			}
		}
		
		$mon = ($mon) ? $mon:$tmon;
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		if($rgn && !$bran){ $me['access_level']="region"; $cnf['region']=$rgn; }
		$show = ($me['access_level']=="hq") ? "":"AND `branch`='".$me['branch']."'";
		$show = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"]):$show;
		$cond = ($me['access_level']=="portfolio") ? "AND `officer`='$sid'":$show;
		$ftch = ($me['access_level']=="portfolio") ? "AND `id`='$sid'":$show;
		
		$res = $db->query(2,"SELECT DISTINCT `month` FROM `$tbl` ORDER BY `month` DESC LIMIT 12");
		if($res){
			foreach($res as $row){
				$mn=$row['month']; $cnd=($mn==$mon) ? "selected":""; $months[]=$mn;
				$mons.="<option value='$mn' $cnd>".date('F Y',$mn)."</option>";
			}
		}
		else{ $mons = "<option value='$tmon'>".date('F Y')."</option>"; }
		
		$cfield = (in_array($me["access_level"],["hq","region"])) ? "branch":"id";
		$title = ($me['access_level']=="hq") ? "Branch":"Loan Officer";
		if($posts){
			$ftch.= " AND (`position`='$post' OR `id` IN (".implode(",",$posts)."))";
		}
		else{
			foreach(array("C.E.O","CEO","ceo","Director","director","USSD") as $pot){ $notin[]="'$pot'"; }
			$ftch.= ($post=="all") ? "AND NOT `position` IN (".implode(",",$notin).")":" AND `position`='$post'"; 
		}
		
		$sday = (isset($setts["targetsb4"])) ? $setts["targetsb4"]:5;
		$mxd = monrange(date("m"),date("Y"))[0]; $mxd=($mxd>$tdy) ? $tdy:$mxd;
		
		$cols = $db->tableFields(2,$tbl); $sums=[]; $trs=$brans=$tdh=""; $no=0;
		$res = $db->query(2,"SELECT DISTINCT `$cfield` FROM `$stbl` WHERE NOT `status` IN ('1','15') AND `id`>1 $ftch");
		if($res){
			foreach($res as $pos=>$rw){
				$def = $rw[$cfield]; $col=($cfield=="branch") ? "branch":"officer"; $tds=""; $all=[];
				
				$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE `$col`='$def' AND `month`='$mon'");
				if($sql){
					foreach($sql as $row){
						$done = json_decode($row["results"],1);
						foreach($kpis as $key=>$one){
							if(!in_array($key,$cols)){
								$dtx = (in_array($key,["loanprods","mtd"])) ? "TINYTEXT":"INT"; $row[$key]=0;
								$db->execute(2,"ALTER TABLE `$tbl` ADD `$key` $dtx NOT NULL"); $cols[]=$key; 
							}
							
							if($one["target"]){
								$dval = (isset($done[$key])) ? $done[$key]:0;
								if($key=="loanprods"){
									foreach($one["list"] as $dcol){
										$ttl = prepare($dcol)." Loan Products"; $cdf=json_decode($row[$key],1);
										$pam = (isset($cdf[$dcol])) ? "edit targets":"set targets"; $icon=($pam=="edit targets") ? "fa-pencil":"fa-plus";
										$val = (isset($dval[$dcol])) ? $dval[$dcol]:0; $targ=(isset($cdf[$dcol])) ? $cdf[$dcol]:0; $perc=($targ>0) ? round($val/$targ*100,1):0;
										if(isset($sums[$key][$dcol])){ $sums[$key][$dcol]["targ"]+=$targ; $sums[$key][$dcol]["val"]+=$val; }
										else{ $sums[$key][$dcol]=array("targ"=>$targ,"val"=>$val); }
										
										if($cfield=="branch"){
											if(isset($all[$key][$dcol])){ $all[$key][$dcol]["targ"]+=$targ; $all[$key][$dcol]["val"]+=$val; }
											else{ $all[$key][$dcol]=array("targ"=>$targ,"val"=>$val); }
										}
										else{
											$edit = (in_array($pam,$perms) && $cfield=="id" && $mon==$tmon && intval(date("d"))<=$sday) ? "<i class='fa $icon' 
											style='cursor:pointer;color:#008fff;font-size:17px' onclick=\"changetarget('$key-$dcol','$def','$targ','$ttl')\"></i>":"";
											$tds.= "<td>".fnum($targ)." $edit</td><td>".fnum($val)." <span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span></td>";
										}
									}
								}
								else{
									$targ = $row[$key]; $ttl=$one["title"]; $perc=($targ>0) ? round($dval/$targ*100,1):0;
									if(isset($sums[$key])){ $sums[$key]["targ"]+=$targ; $sums[$key]["val"]+=$dval; }
									else{ $sums[$key]=array("targ"=>$targ,"val"=>$dval); }
										
									if($cfield=="branch"){
										if(isset($all[$key])){ $all[$key]["targ"]+=$targ; $all[$key]["val"]+=$dval; }
										else{ $all[$key]=array("targ"=>$targ,"val"=>$dval); }
									}
									else{
										$pam = ($targ>0) ? "edit targets":"set targets"; $icon=($pam=="edit targets") ? "fa-pencil":"fa-plus";
										$upd = (!in_array($key,["arrears"])) ? "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span>":"";
										$edit = (in_array($pam,$perms) && $cfield=="id" && $mon==$tmon && intval(date("d"))<=$sday) ? "<i class='fa $icon' 
										style='cursor:pointer;color:#008fff;font-size:17px' onclick=\"changetarget('$key','$def','$targ','$ttl')\"></i>":"";
										$tds.= "<td>".fnum($targ)." $edit</td><td>".fnum($dval)." $upd</td>";
									}
								}
							}
						}
					}
				}
				else{
					foreach($kpis as $key=>$one){
						if(!in_array($key,$cols)){
							$dtx = ($key=="loanprods") ? "TINYTEXT":"INT"; 
							$db->execute(2,"ALTER TABLE `$tbl` ADD `$key` $dtx NOT NULL"); $cols[]=$key; 
						}
						
						if($one["target"]){
							if($key=="loanprods"){
								foreach($one["list"] as $dcol){
									$ttl = prepare($dcol)." Loan Products";
									if(!isset($sums[$key][$dcol])){ $sums[$key][$dcol]=array("targ"=>0,"val"=>0); }
									$upd = "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>0%</span>"; 
									$edit = (in_array("set targets",$perms) && $cfield=="id" && $mon==$tmon && intval(date("d"))<=$sday) ? 
									"<i class='fa fa-plus' style='cursor:pointer;color:#008fff;font-size:17px' onclick=\"changetarget('$key-$dcol','$def','0','$ttl')\"></i>":"";
									$tds.= "<td>0 $edit</td><td>0 $upd</td>"; $sums[$key][$dcol]=array("targ"=>0,"val"=>0);
								}
							}
							else{
								if(!isset($sums[$key])){ $sums[$key]=array("targ"=>0,"val"=>0); }
								$upd = (!in_array($key,["arrears"])) ? "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>0%</span>":"";
								$edit = (in_array("set targets",$perms) && $cfield=="id" && $mon==$tmon && intval(date("d"))<=$sday) ? 
								"<i class='fa fa-plus' style='cursor:pointer;color:#008fff;font-size:17px' onclick=\"changetarget('$key','$def','0','".$one["title"]."')\"></i>":"";
								$tds.= "<td>0 $edit</td><td>0 $upd</td>"; 
							}
						}
					}
				}
				
				$name = ($cfield=="branch") ? $bnames[$def]:$staff[$def];
				if($all){
					foreach($all as $dk=>$arr){
						if($dk=="loanprods"){
							foreach($arr as $co=>$v){
								$perc=($v["targ"]>0) ? round($v["val"]/$v["targ"]*100,1):0;
								$tds.= "<td>".fnum($v["targ"])."</td><td>".fnum($v["val"])." <span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span></td>";
							}
						}
						else{
							$perc=($arr["targ"]>0) ? round($arr["val"]/$arr["targ"]*100,1):0;
							$upd = (!in_array($dk,["arrears"])) ? "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span>":"";
							$tds.= "<td>".fnum($arr["targ"])."</td><td>".fnum($arr["val"])." $upd</td>";
						}
					}
				}
				
				$trs.= "<tr><td style='text-align:left'>$name</td>$tds</tr>";
			}
			
			foreach($sums as $col=>$arr){
				if($col=="loanprods"){
					foreach($arr as $co=>$v){
						$perc=($v["targ"]>0) ? round($v["val"]/$v["targ"]*100,1):0;
						$tdh.= "<td>".fnum($v["targ"])."</td><td>".fnum($v["val"])." <span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span></td>";
					}
				}
				else{
					$perc=($arr["targ"]>0) ? round($arr["val"]/$arr["targ"]*100,1):0;
					$upd = (!in_array($col,["arrears"])) ? "<span style='color:#FF00FF;font-size:12px;float:right;font-weight:bold'>$perc%</span>":"";
					$tdh.= "<td>".fnum($arr["targ"])."</td><td>".fnum($arr["val"])." $upd</td>";
				}
			}
			
			$trs.= "<tr style='font-weight:bold;background:#f0f0f0'><td style='text-align:left;'>Totals</td>$tdh</tr>";
		}
		
		if(in_array($access,["hq","region"])){
			$brn = "<option value='0'>Corporate</option>";
			$add = ($me["access_level"]=="region" && isset($cnf["region"])) ? "AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brn.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			if($access=="hq"){
				$sql = $db->query(1,"SELECT *FROM `regions` WHERE `client`='$cid' ORDER BY `name` ASC");
				if($sql){
					$rgs = "<option value='0'>-- Region --</option>";
					foreach($sql as $row){
						$id=$row["id"]; $cnd = ($id==$rgn) ? "selected":"";
						$rgs.= "<option value='$id' $cnd>".prepare(ucwords($row["name"]))."</option>";
					}
					$brans = "<select style='width:130px;font-size:15px;' onchange=\"loadpage('bi/targets.php?view&mon=$mon&reg='+this.value.trim())\">$rgs</select>&nbsp;";
				}
			}
			
			$brans.= "<select style='width:140px;font-size:15px' onchange=\"loadpage('bi/targets.php?view&mon=$mon&reg=$rgn&bran='+this.value.trim())\">$brn</select>";
		}
		
		$ths=$thd=""; 
		foreach($kpis as $key=>$one){
			if($one["target"]){
				if($key=="loanprods"){
					foreach($one["list"] as $pd){ $ths.="<td colspan='2'>".ucfirst(prepare($pd))."</td>"; $thd.="<td>Target</td><td>Actual</td>"; }
				}
				else{ $ths.="<td colspan='2'>".prepare($one["title"])."</td>"; $thd.="<td>Target</td><td>Actual</td>"; }
			}
		}
		
		$text = (in_array($me["access_level"],["hq","region"]) && $mon=$tmon) ? "<p style='font-size:14px'><b>Note:</b> Targets can only be 
		set at branch level between ".date('F')." 2 & ".date("F ")."$sday</p>":"";
		echo "<div class='container cardv' style='max-width:1450px;min-height:300px;overflow:auto'>
			<div style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn Targets & Actuals</h3><hr> $text
				<div style='width:100%;overflow:auto'>
					<table class='table-bordered' style='width:100%;font-size:14px;text-align:center;min-width:650px;' cellpadding='5'> 
						<caption style='caption-side:top;padding-top:0px'>
							<select style='width:160px' onchange=\"loadpage('bi/targets.php?view&mon='+this.value.trim())\">$mons</select> $brans
						</caption>
						<tr style='background:#4682b4;color:#fff;font-weight:bold;font-size:13px;' valign='top'><td>$title</td>$ths</tr>
						<tr style='background:#B0C4DE;color:#191970;font-weight:bold;font-size:13px;' valign='top'><td></td>$thd</tr> $trs
					</table>
				</div>
			</div>
		</div>";
		savelog($sid,"Viewed Targets for ".date("M Y",$mon));
	}

	@ob_end_flush();
?>

<script>
	
	function changetarget(typ,stid,val,ttl){
		var nval = prompt("Set Staff Target for "+ttl,val);
		if(nval){
			if(confirm("Update "+ttl+" targets for the staff?")){
				$.ajax({
					method:"post",url:path()+"dbsave/targets.php",data:{setarget:typ,tval:nval,stid:stid,title:ttl},
					beforeSend:function(){ progress("Updating...please wait"); },
					complete:function(){ progress(); }
				}).fail(function(){
					toast("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){ toast("Success"); window.location.reload(); }
					else{ alert(res); }
				});
			}
		}
	}

</script>
