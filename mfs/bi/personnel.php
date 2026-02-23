<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	
	include "../../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	echo "<script>
	
		function drawLine(elem,xd,yd,data,lbs){
			Morris.Line({
			  element: elem,
			  data: data,
			  xkey: xd,
			  ykeys: yd,
			  labels: lbs,
			  xLabelAngle: 70,
			  lineColors: ['#663399','#2E8B57','#4169E1','#C71585'],
			  resize: true
			});
		}
		
		function drawBar(elem,xd,yd,data,lbs){
			Morris.Bar({
			  element: elem,
			  data: data,
			  xkey: xd,
			  ykeys: yd,
			  labels: lbs,
			  xLabelAngle: 70,
			  barColors: ['#663399','#2E8B57','#4169E1','#C71585'],
			  resize: true
			});
		}
		
		function drawPie(div,title,vals){
			var arr=JSON.parse(JSON.stringify(vals).split('{').join('[').split('}').join(']').split(':').join(','));
			google.charts.load('current', {'packages':['corechart']});
			google.charts.setOnLoadCallback(function(){
				var data = google.visualization.arrayToDataTable(arr);
				var options = {'title':title, 'width':'98%', 'height':'98%',is3D: true};
				var chart = new google.visualization.PieChart(document.getElementById(div));
				chart.draw(data, options);
			});
		}
		
	</script>";
	
	# view
	if(isset($_GET['view'])){
		$vtp = trim($_GET['view']);
		$ftc = ($vtp) ? $vtp:"personnel";
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$ofid = (isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		
		$me = staffInfo($sid); $access = $me['access_level'];
		$bran = ($me['access_level']!="hq") ? $me['branch']:$bran;
		$ofid = ($me['access_level']=="portfolio") ? $sid:$ofid;
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		
		$staff=[0=>"System"]; $offs=$brans=$data=$lis=""; $offids=[];
		$leo = strtotime(date("Y-M-d")); $jana = strtotime(date("Y-M-d",$leo-100)); $tmon=strtotime(date("Y-M"));
		$ltbl = "org$cid"."_loans"; $stbl = "org$cid"."_schedule";
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staffs[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if($access=="hq"){
			$brns="<option value='0'>Head Office</option>";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0'");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			
			$brans = "<select style='width:140px;font-size:15px' onchange=\"loadpage('bi/personnel.php?view=$vtp&bran='+this.value.trim())\">$brns</select>";
		}
		
		
		# performance
		if($ftc=="personnel"){
			$fdy = (isset($_GET['fdy'])) ? strtotime(trim($_GET['fdy'])):strtotime(date("Y-M"));
			$fto = (isset($_GET['fto'])) ? strtotime(trim($_GET['fto'])):$jana;
			$fto = ($fdy>$fto) ? $fdy:$fto;
			$grp = ($bran) ? "day,staff":"day,branch";
			$grp = ($ofid) ? "day,staff":$grp;
			
			$smd = (intval(date("d",$fdy))>1) ? 1:2;
			$mon = strtotime(date("Y-M",$fdy));
			$title = date("d-m-Y",$fdy)." to ".date("d-m-Y",$fto)." Performance";
			
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='perflastdy' AND `client`='$cid'"); 
			$pdy = ($qri) ? $qri[0]['value']:1; $pmon = strtotime(date("Y-M",strtotime("-$smd month")));
			$from = ($pdy) ? strtotime(date("Y-M",$fdy)):monrange(date("m",$pmon),date("Y",$pmon))[1];
			$startday = date("M-d",$from); $enday = date("M-d",$fto);
			
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='rankparams' AND `client`='$cid'");
			$ranking = ($res) ? json_decode($res[0]['value'],1):array("newloans","repeats","performing","arrears","clientelle");
			$def = array("newloans"=>"A/T*100","repeats"=>"A/T*100","performing"=>"A/T*100","clientelle"=>"S-E/E*100","arrears"=>"S-E/S-T*100",
			"income"=>"value","checkoff"=>"value");
			
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='analyticsformulas' AND `client`='$cid'");
			$formulas = ($res) ? json_decode($res[0]['value'],1):$def; $formulas["olb"]="value";
			$col=($_SERVER['HTTP_HOST']=="mfi.axecredits.com" or $_SERVER['HTTP_HOST']=="bulk.axecredits.com") ? "performing":"active";
			
			$data=$grouping=$sign=$record=$thead=$refined=$sums=[]; 
			$qri = $db->query(2,"SELECT DISTINCT `staff` FROM `bi_analytics$cid` WHERE `month`='$mon' AND `day`<=$fto AND `$col`>0");
			if($qri){
				foreach($qri as $rw){
					$stid=$rw['staff']; $perc=[]; $cv1=$cv2=0;
					$sql=$db->query(2,"SELECT *FROM `bi_analytics$cid` WHERE `staff`='$stid' AND `day`='$from'");
					$acs=($sql) ? $sql[0]['performing']:0; $dcs=($sql) ? $sql[0]['dormant']:0; $arrs=($sql) ? $sql[0]['arrears']:0; 
					
					$sqr=$db->query(2,"SELECT *FROM `bi_analytics$cid` WHERE `staff`='$stid' AND `day`='$fto'");
					$ace=($sqr) ? $sqr[0]['performing']:0; $dce=($sqr) ? $sqr[0]['dormant']:0; $arre=($sqr) ? trim($sqr[0]['arrears']):0;
					$porte=($sqr) ? trim($sqr[0]['portfolio']):0; $income=($sqr) ? $sqr[0]['income']:0;
					
					$res=$db->query(2,"SELECT *,SUM(repeats) AS rept,SUM(newloans) AS nloan,SUM(activation) AS cactv,SUM(checkoff) AS cko 
					FROM `bi_analytics$cid` WHERE `month`='$mon' AND `staff`='$stid'"); $rept=($res) ? $res[0]['rept']:0; 
					$newloans=($res) ? $res[0]['nloan']:0; $catv=($res) ? $res[0]['cactv']:0;  $checkoff=($res) ? $res[0]['cko']:0; 
					
					$qry=$db->query(2,"SELECT *FROM `org".$cid."_targets` WHERE `month`='$mon' AND `officer`='$stid'"); $cko=($qry) ? $qry[0]['checkoff']:0; 
					$nltag=($qry) ? $qry[0]['newloans']:0; $reptag=($qry) ? $qry[0]['repeats']:0;  $ptar=($qry) ? $qry[0]['performing']:0; 
					$rtar=($qry) ? $qry[0]['arrears']:0; $catvag=($qry) ? $qry[0]['clientelle']:0;  $inct=($qry) ? $qry[0]['income']:0; 
					$actuals = array("newloans"=>$newloans,"olb"=>$porte,"repeats"=>$rept,"performing"=>$ace,"arrears"=>$arre,"clientelle"=>$catv,
					"income"=>$income,"checkoff"=>$checkoff);
					
					foreach($ranking as $rank){
						$formula = $formulas[$rank]; $grouping[$rank]="DESC"; $sign[$rank]="%";
						if($formula=="value"){
							$perc[$rank]=$actuals[$rank]; $sign[$rank]=""; $thead[$rank]=[$enday];
							if($rank=="clientelle" or $rank=="arrears"){ $grouping[$rank]="ASC"; }
						}
						else{
							if($rank=="newloans"){ $perc[$rank]=($nltag) ? round($newloans/$nltag*100,1):0; $thead[$rank]=["Target",$enday]; }
							if($rank=="checkoff"){ $perc[$rank]=($cko) ? round($checkoff/$cko*100,1):0; $thead[$rank]=["Target",$enday]; }
							if($rank=="repeats"){ $perc[$rank]=($reptag) ? round($rept/$reptag*100,1):0; $thead[$rank]=["Target",$enday]; }
							if($rank=="income"){ $perc[$rank]=($inct) ? round($income/$inct*100,1):0; $thead[$rank]=["Target",$enday]; }
							if($rank=="performing"){
								if($formula=="A/T*100"){ $perc[$rank]=($ptar) ? round($ace/$ptar*100,1):0; $pfv=$ptar; $thead[$rank]=[$startday,"Target",$enday]; }
								if($formula=="A-S"){ $perc[$rank]=$ace-$acs; $sign[$rank]=""; $pfv=$acs; $thead[$rank]=[$startday,$enday]; }
								if($formula=="A-S/S*100"){ $perc[$rank]=round(($ace-$acs)/$acs*100,1); $pfv=$acs; $thead[$rank]=[$startday,$enday]; }
							}
							if($rank=="clientelle"){
								if($formula=="A/T*100"){ $perc[$rank]=($catvag) ? round($catv/$catvag*100,1):0; $cv1=$catvag; $cv2=$catv; $thead[$rank]=[$startday,"Target",$enday]; }
								if($formula=="S-E/S*100"){ $perc[$rank]=round(($dcs-$dce)/$dcs*100,1); $cv1=$dcs; $cv2=$dce; $thead[$rank]=[$startday,$enday]; }
							}
							if($rank=="arrears"){
								if($formula=="A-S/S*100"){ $perc[$rank]=round(($arre-$arrs)/$arrs*100,1); $avs=$arrs; $thead[$rank]=[$startday,$enday]; }
								if($formula=="A-T/T*100"){ $perc[$rank]=($rtar) ? round(($arre-$rtar)/$rtar*100,1):0; $avs=$rtar; $thead[$rank]=[$startday,"Target",$enday]; }
								if($formula=="S-E/S-T*100"){ $perc[$rank]=($arrs) ? round(($arrs-$arre)/($arrs-$rtar)*100,1):0; $avs=$rtar; $thead[$rank]=[$startday,"Target",$enday]; }
							}
						}
					}
					
					$data[$stid]=$perc;
					$dt1=$dt2=$dt3=$dt4=$dt5=$dt6=$dt7=$dt8=0; $dv1=$dv2=$dv3=$dv4=$dv5=$dv6=$dv7=$dv8=0; $no=0;
					
					if(array_key_exists("newloans",$perc)){ $dt1 = $perc['newloans'].$sign['newloans']; $dv1=$perc['newloans']; $no++; }
					if(array_key_exists("arrears",$perc)){ $dt2 = $perc['arrears'].$sign['arrears']; $dv2=$perc['arrears']; $no++; }
					if(array_key_exists("checkoff",$perc)){ $dt8 = $perc['checkoff'].$sign['checkoff']; $dv8=$perc['checkoff']; $no++; }
					if(array_key_exists("clientelle",$perc)){ $dt3 = $perc['clientelle'].$sign['clientelle']; $dv3=$perc['clientelle']; $no++; }
					if(array_key_exists("repeats",$perc)){ $dt4 = $perc['repeats'].$sign['repeats']; $dv4=$perc['repeats']; $no++; }
					if(array_key_exists("income",$perc)){ $dt5 = $perc['income'].$sign['income']; $dv5=$perc['income']; $no++; }
					if(array_key_exists("performing",$perc)){ $dt6 = $perc['performing'].$sign['performing']; $dv6=$perc['performing']; $no++; }
					if(array_key_exists("olb",$perc)){ $dt7 = $perc['olb'].$sign['olb']; $dv7=$perc['olb']; $no++; }
					
					$sums[$stid] = round(($dv1+$dv2+$dv3+$dv4+$dv5+$dv6+$dv7+$dv8)/$no,1);
					$record[$stid] = array("newloans"=>[$nltag,$newloans,$dt1],"arrears"=>[$arrs,$avs,$arre,$dt2],"clientelle"=>[$dcs,$cv1,$cv2,$dt3],
					"repeats"=>[$reptag,$rept,$dt4],"income"=>[$inct,$income,$dt5],"performing"=>[$acs,$pfv,$ace,$dt6],"olb"=>[$porte,$porte,$dv7],
					"checkoff"=>[$cko,$checkoff,$dt8]);
				}
			}
			
			$perc1=$perc2=$perc3=$perc4=$perc5=$perc6=$perc7=$perc8=[];
			foreach($data as $uid=>$one){
				if(array_key_exists("newloans",$one)){ $perc1[$uid]=$one['newloans']; } 
				if(array_key_exists("arrears",$one)){ $perc2[$uid]=$one['arrears']; } 
				if(array_key_exists("checkoff",$one)){ $perc8[$uid]=$one['checkoff']; } 
				if(array_key_exists("clientelle",$one)){ $perc3[$uid]=$one['clientelle']; } 
				if(array_key_exists("repeats",$one)){ $perc4[$uid]=$one['repeats']; } 
				if(array_key_exists("performing",$one)){ $perc5[$uid]=$one['performing']; } 		 
				if(array_key_exists("income",$one)){ $perc6[$uid]=$one['income']; } 		 
				if(array_key_exists("olb",$one)){ $perc7[$uid]=$one['olb']; } 		 
			}
			
			if(count($data)){
				$pull = array("newloans"=>$perc1,"arrears"=>$perc2,"clientelle"=>$perc3,"repeats"=>$perc4,"performing"=>$perc5,"income"=>$perc6,"olb"=>$perc7,"checkoff"=>$perc8);
				foreach($pull as $col=>$one){
					if(count($one)){
						$gtp = $grouping[$col];
						if($gtp=="DESC"){ arsort($one,SORT_NUMERIC); $refined[$col]=$one; }
						else{ asort($one,SORT_NUMERIC); $refined[$col]=$one; }
					}
				}
			}
			
			$ptotal=$positions=$numbers=$nums=[];
			foreach($refined as $col=>$one){
				$pos=0; 
				foreach($one as $stid=>$val){
					$pos++; $numbers[$pos]=$val;
					$no=(in_array($val,$numbers)) ? array_search($val,$numbers):$pos;
					if(array_key_exists($stid,$ptotal)){ $ptotal[$stid]+=$no; $positions[$stid][$col]=$no; $nums[$stid]+=$no; }
					else{ $ptotal[$stid]=$no; $positions[$stid][$col]=$no; $nums[$stid]=$no; }
				}
			}
			
			$cond =1; $no=0; $numbers=$fpoints=[]; $ptrs="";
			foreach($sign as $val){ 
				if($val!="%"){ $cond=0; break; }
			}
			
			# generate positions
			$tx1 = ($cond) ? "Average":"Points";
			$final = ($cond) ? $sums:$nums;
			if($cond){ arsort($final); }else{ asort($final); }
			
			foreach($final as $stid=>$points){
				$name=$staffs[$stid]; $no++; $numbers[$no]=$points; 
				$pos = (in_array($points,$numbers)) ? array_search($points,$numbers):$no; $arrange[$stid]=$pos;
				$val = ($cond) ? "$points%":$points; $all=count($final); $fpoints[$stid]=$val;
				if($access=="branch"){ 
					$ptrs.=($userbranch[$sid]==$userbranch[$stid] or $arrange[$stid]<=2) ? "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>":"";
				}
				elseif($access=="portfolio"){
					$ptrs.=($stid==$sid or $arrange[$stid]<=2) ? "<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>":"";
				}
				else{ $ptrs.="<tr><td>$name</td><td>$val</td><td>$pos of $all</td></tr>"; }
			}
			
			# get performance indicators
			$ths1=$ths2=$ltrs=""; $cols=[];
			$defrank = $ranking; $last=@array_pop($ranking); 
			$titles = array("newloans"=>"Production New","repeats"=>"Production Repeat","performing"=>"Performing Loans","arrears"=>"P.A.R","income"=>"Revenue",
			"clientelle"=>"Clientele Activation","olb"=>"0.L.B","checkoff"=>"Check Off/Topup");
			
			foreach($defrank as $rank){
				$defhd = (in_array($rank,array("newloans","repeats","income","checkoff"))) ? array("Target",$enday):array($startday,"Target",$enday);
				$foml = $formulas[$rank]; $stl=($sign[$rank]=="%") ? "Score":"Actual"; $def=($foml=="value") ? array($enday):$defhd;
				$tarr = (array_key_exists($rank,$thead)) ? $thead[$rank]:$def; 
				$span=count($tarr)+2; $span+=($rank==$last) ? 1:0; $no=0;
				foreach($tarr as $col){ $ths2.="<td>$col</td>"; $no++; }
				$ths1.="<td colspan='$span'>$titles[$rank]</td>"; $cols[$rank]=$no;
				$ths2.="<td>$stl</td><td>Pos</td>";
				$ths2.=($rank==$last) ? "<td>Avg</td>":"";
			}
			
			foreach($record as $stid=>$des){
				$tds=""; 
				foreach($defrank as $rank){
					$one = $des[$rank]; $all=count($one); $rem=$all-$cols[$rank];
					for($i=1; $i<=$rem-1; $i++){ array_shift($one); }
					foreach($one as $no=>$td){
						$css = ($no==(count($one)-1)) ? "background:#f0f0f0":"";
						$tds.="<td style='text-align:center;$css'>$td</td>"; 
					}
					
					$tds.="<td style='background:#dcdcdc;text-align:center'>".$positions[$stid][$rank]."</td>";
					$tds.=($rank==$last) ? "<td style='background:#ADD8E6;text-align:center'>$fpoints[$stid]</td>":"";
				}
				
				if($access=="branch"){ 
					$ltrs.=($userbranch[$sid]==$userbranch[$stid] or $arrange[$stid]<=2) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":"";
				}
				elseif($access=="portfolio"){
					$ltrs.=($stid==$sid or $arrange[$stid]<=2) ? "<tr><td>$staffs[$stid]</td> $tds </tr>":"";
				}
				else{ $ltrs.="<tr><td>$staffs[$stid]</td> $tds </tr>"; }
			}
			
			$data = "<hr><div class='row' style='margin:0px;padding:0px'>
				<div class='col-12'>
					<p><input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fdy)."' id='fdy'
					onchange=\"loadpage('bi/personnel.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+this.value)\"> To: 
					<input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fto)."'
					onchange=\"loadpage('bi/personnel.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+_('fdy').value+'&fto='+this.value)\"></p><hr>
				</div>
				<div class='col-sm-12 col-md-6 col-lg-6 mt-3'>
					<div class='bg-white' style='min-height:150px'>
						<h3 style='color:#191970;font-size:20px'>Staff Performance</h3>
						<table style='width:100%;font-size:15px' cellpadding='5' class='table-striped'>
							<tr style='background:#B0C4DE;color:#191970;font-weight:bold'><td>Staff</td><td>$tx1</td><td>Position</td></tr> $ptrs
						</table>
					</div>
				</div>
				<div class='col-sm-12 col-md-6 col-lg-6 mt-3'>
					<div class='bg-white' style='min-height:150px'>
						<h3 style='color:#191970;font-size:20px'>Branch Performance</h3>
						<table style='width:100%;font-size:15px' cellpadding='5' class='table-striped'>
							<tr style='background:#B0C4DE;color:#191970;font-weight:bold'><td>Branch</td><td>$tx1</td><td>Position</td></tr>
						</table>
					</div>
				</div>
				<div class='col-lg-12 col-md-12 col-sm-12' style='padding:10px'>
					<div class='bg-white' style='overflow:auto;width:100%'>
						<h3 style='color:#191970;font-size:20px'>Performance Indicators</h3>
						<table class='table-bordered' style='width:100%;min-width:800px;font-size:14px' cellpadding='4'>
							<tr style='background:#4682b4;color:#fff;font-weight:bold;text-align:center;text-shadow:0px 1px 0px #000;font-size:13px'>
							<td>Staff</td>$ths1</tr>
							<tr valign='top' style='background:#B0C4DE;color:#191970;font-weight:bold;text-align:center;font-size:12px'><td></td>$ths2</tr> $ltrs
						</table>
					</div>
				</div>
			</div>";
		}
		
		if(($bran or $access=="branch") && $access!="portfolio"){
			$opts="<option value='0'>- Select Staff -</option>";
			foreach($offids as $off){
				$cnd=($off==$ofid) ? "selected":"";
				$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
			}
			
			$offs = "<select style='width:150px;font-size:15px;' onchange=\"loadpage('bi/personnel.php?view=$vtp&bran=$bran&ofid='+this.value)\">$opts</select>";
		}
		
		foreach(array("personnel"=>"Staff Performance","loans"=>"Disbursement analysis") as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$lis.="<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970' onclick=\"loadpage('bi/personnel.php?view=$vtp')\">$backbtn $title</h3>
				<div style='width:100%;overflow:auto;margin-top:20px'>
					<p><select style='width:160px;font-size:15px' onchange=\"loadpage('bi/personnel.php?view='+this.value)\">$lis</select> 
					$brans $offs</p> $data
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed $title");
	}
	
	@ob_end_flush();
?>