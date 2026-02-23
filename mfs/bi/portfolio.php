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
	
	if(isset($_GET['view'])){
		$vtp = trim($_GET['view']);
		$ftc = ($vtp) ? $vtp:"par";
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$bran = (isset($_GET['bran'])) ? trim($_GET['bran']):0;
		$ofid = (isset($_GET['ofid'])) ? trim($_GET['ofid']):0;
		
		$me = staffInfo($sid); $access = $me['access_level'];
		$bran = (!in_array($me['access_level'],["hq","region"])) ? $me['branch']:$bran;
		$ofid = ($me['access_level']=="portfolio") ? $sid:$ofid;
		if($bran){ $me['access_level']="branch"; $me['branch']=$bran; }
		
		$staff=["System"]; $offs=$brans=$data=$lis=""; $offids=[];
		$leo = strtotime(date("Y-M-d")); $jana = strtotime(date("Y-M-d",$leo-100)); $tmon=strtotime(date("Y-M"));
		$ltbl = "org$cid"."_loans"; $stbl = "org$cid"."_schedule"; $cnf=json_decode($me["config"],1);
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$qr= $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid'");
		if($qr){
			foreach($qr as $row){
				$bnames[$row['id']]=prepare(ucwords($row['branch']));
			}
		}
		
		if(in_array($access,["hq","region"])){
			$brns = "<option value='0'>Corporate</option>";
			$add = ($access=="region" && isset($cnf["region"])) ? " AND ".setRegion($cnf["region"],"id"):"";
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `client`='$cid' AND `status`='0' $add");
			if($res){
				foreach($res as $row){
					$rid=$row['id']; $cnd=($bran==$rid) ? "selected":"";
					$brns.="<option value='$rid' $cnd>".prepare(ucwords($row['branch']))."</option>";
				}
			}
			$brans = "<select style='width:140px;font-size:15px' onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran='+this.value.trim())\">$brns</select>";
		}
		
		# PAR 
		if($ftc=="par"){
			$title = "Arrears Analysis";
			$days = ["1-7","8-14","15-30","30-60","61-90","91-180","181-6000"];
			$cond = ($bran) ? "AND ln.branch='$bran'":"";
			$cond = ($access=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"],"ln.branch"):$cond;
			$cond.= ($ofid) ? " AND ln.loan_officer='$ofid'":"";
			
			$trs=""; $tdt=0; $grp="branch";
			foreach($days as $one){
				$fro=strtotime(date("Y-m-d",$leo-(86400*explode("-",$one)[1]))); $loans=[];
				$to=strtotime(date("Y-m-d",$leo-(86400*explode("-",$one)[0])));
				
				$qri = $db->query(2,"SELECT SUM(ln.balance) AS tbal,SUM((CASE WHEN (sd.day<$leo) THEN sd.balance ELSE 0 END)) AS pbal FROM `$ltbl` AS ln 
				INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan WHERE sd.day BETWEEN $fro AND $to AND sd.balance>0 AND ln.balance>0 $cond");
				$qry = $db->query(2,"SELECT ln.* FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan WHERE sd.day 
				BETWEEN $fro AND $to AND sd.balance>0 AND ln.balance>0 $cond GROUP BY sd.loan");
				
				if($qri[0]['pbal']){
					foreach($qry as $row){
						$offids[$row['loan_officer']]=$row['loan_officer'];
						if($row['expiry']<$leo && !in_array($row['loan'],$loans)){ $loans[$row['loan']]=$row['balance']; }
					}
					
					$all=count($qry); $tbal=$qri[0]['tbal']; $def=count($loans); $run=$all-$def; $arrs=number_format($qri[0]['pbal']); 
					$sum=number_format($tbal); $par=($tbal) ? round(($qri[0]['pbal']/$tbal)*100,2):0;
					$color=($par>3) ? "#ff4500":"orange"; $color=($par<1) ? "green":$color; $tdt+=$qri[0]['pbal'];
				
					$trs.="<tr style='color:$color'><td>$one Days</td><td>$all <a href='javascript:void(0)' style='color:#008fff'
					onclick=\"loadpage('bi/portfolio.php?vclns=$one&bran=$bran&ofid=$ofid')\">View</a></td><td>$run</td><td>$def</td><td>$arrs</td>
					<td>$sum</td><td>$par%</td></tr>";
				}
			}
			
			if($trs){
				$res = $db->query(2,"SELECT SUM(ln.balance) AS tbal FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan 
				WHERE ln.balance>0 AND sd.balance>0 AND sd.day<$leo $cond GROUP BY sd.loan"); 
				$sum = array_sum(array_column($res, 'tbal')); $pval=number_format($sum); $par=($sum) ? round(($tdt/$sum)*100,2):0;
				
				$trs.= "<tr style='color:#191970;background:#e6e6fa;font-weight:bold'><td colspan='4' style='text-align:center'>Totals</td>
				<td>".number_format($tdt)."</td><td>$pval</td><td>$par%</td></tr>";
			}
			
			$arrears=$all=[];
			$fdy = (isset($_GET['fdy'])) ? strtotime(trim($_GET['fdy'])):$jana;
			$fto = (isset($_GET['fto'])) ? strtotime(trim($_GET['fto'])):$jana;
			$fto = ($fdy>$fto) ? $fdy:$fto;
			$grp = ($bran) ? "day,staff":"day,branch";
			$grp = ($ofid) ? "day,staff":$grp;
			
			$cond2 = ($bran) ? "AND `branch`='$bran'":"";
			$cond2.= ($ofid) ? " AND `staff`='$ofid'":"";
			
			$res = $db->query(2,"SELECT *,SUM(arrears) AS deni FROM `bi_analytics$cid` WHERE `day` BETWEEN $fdy AND $fto $cond2 GROUP BY $grp HAVING deni>0");
			if($res){
				foreach($res as $row){
					$arr=$row['deni']; $day=$row['day']; 
					$port=(!$bran) ? $bnames[$row['branch']]:$staff[$row['staff']]; $all[$port]=$port;
					if(array_key_exists($day,$arrears)){ $arrears[$day][$port]=($arr) ? $arr:0; }
					else{ $arrears[$day][$port]=($arr) ? $arr:0; }
				}
			}
			
			$graph1=$symbols=[];
			foreach($arrears as $day=>$one){
				$dy=date("Y-m-d",$day); $arr=["period"=>$dy];
				foreach($all as $name){
					if(!array_key_exists($name,$one)){ $one[$name]=0; }
				}
				
				foreach($one as $key=>$val){ $arr[$key]=$val;  $symbols[]=$key; }
				$graph1[]=$arr;
			}
			
			$js1=json_encode($graph1); $js2=json_encode(array_unique($symbols));
			echo (count($arrears)>1) ? "<script>drawLine('dvd','period',$js2,$js1,$js2);</script>":"<script>drawBar('dvd','period',$js2,$js1,$js2);</script>";
			
			$data ="<div style='min-width:600px'><hr style='margin:0px'>
			<table style='min-width:500px;width:100%;' cellpadding='5' class='table-striped'>
				<caption style='caption-side:top;color:#191970;font-size:18px'>Portfolio at Risk</caption>
				<tr style='color:#191970;background:#e6e6fa;font-weight:bold;font-size:15px'><td>Days Range</td><td>Loans</td><td>Running</td><td>Overdue</td>
				<td>Arrears</td><td>Portfolio</td><td>PAR</td></tr> $trs
			</table><br>
			<h4 style='font-size:18px;'>Portfolio Arrears Trend</h4>
			<div style='min-width:500px'>
				<p><input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fdy)."' id='fdy'
				onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+this.value)\"> To: 
				<input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fto)."'
				onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+_('fdy').value+'&fto='+this.value)\"></p><hr>
				<div id='dvd' style='height:360px'></div>
			</div></div>";
		}
		
		# client analysis
		if($ftc=="client"){
			$title = "Client Analysis";
			$cond = ($bran) ? "AND branch='$bran'":"";
			$cond = ($access=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond;
			$cond.= ($ofid) ? " AND loan_officer='$ofid'":"";
			
			$fdy = (isset($_GET['fdy'])) ? strtotime(trim($_GET['fdy'])):$jana;
			$fto = (isset($_GET['fto'])) ? strtotime(trim($_GET['fto'])):$jana;
			$fto = ($fdy>$fto) ? $fdy:$fto;
			$grp = ($bran) ? "day,staff":"day,branch";
			$grp = ($ofid) ? "day,staff":$grp;
			
			$cond2 = ($bran) ? "AND `branch`='$bran'":"";
			$cond2 = ($access=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond2;
			$cond2.= ($ofid) ? " AND `staff`='$ofid'":"";
			$graph1=$graph2=$symbols=$dorms=$actives=$all=[];
			
			$res = $db->query(2,"SELECT *,SUM(dormant) AS dorm,SUM(active) AS actv FROM `bi_analytics$cid` WHERE `day` BETWEEN $fdy AND $fto $cond2 GROUP BY $grp");
			if($res){
				foreach($res as $row){
					$dorm=$row['dorm']; $actv=$row['actv']; $day=$row['day']; $offids[$row['staff']]=$row['staff'];
					$port=(!$bran) ? $bnames[$row['branch']]:$staff[$row['staff']]; $all[$port]=$port;
					if(array_key_exists($day,$dorms)){
						$dorms[$day][$port]=($dorm) ? $dorm:0; $actives[$day][$port]=($actv) ? $actv:0; 
					}
					else{
						$dorms[$day][$port]=($dorm) ? $dorm:0; $actives[$day][$port]=($actv) ? $actv:0; 
					}
				}
			}
			
			foreach($dorms as $day=>$one){
				$dy=date("Y-m-d",$day); $dorm=$active=["period"=>$dy];
				foreach($all as $name){
					if(!array_key_exists($name,$one)){ $one[$name]=0; $actives[$day][$name]=0; }
				}
				
				foreach($one as $key=>$val){
					$dorm[$key]=$val; $active[$key]=$actives[$day][$key]; $symbols[]=$key; 
				}
				$graph1[]=$dorm; $graph2[]=$active;
			}
			
			$pie = [["Task"=>"Client Loans"]]; 
			$qri = $db->query(2,"SELECT *,COUNT(*) AS total FROM `$ltbl` WHERE `balance`>0 $cond GROUP BY amount");
			if($qri){
				foreach($qri as $row){
					$pie[]=[$row['amount']." Loans"=>intval($row['total'])];
				}
			}
			
			$pie2 = [["Task"=>"Client Cycles"]];
			$qri = $db->query(2,"SELECT cycles,COUNT(*) AS total FROM `org".$cid."_clients` WHERE `status`='1' $cond GROUP BY cycles");
			if($qri){
				foreach($qri as $row){
					$txt = ($row['cycles']==1) ? "1 Cycle":$row['cycles']." Cycles";
					$pie2[]=[$txt=>intval($row['total'])];
				}
			}
				
			$js1=json_encode($graph1); $js2=json_encode($graph2); $js3=json_encode(array_unique($symbols));
			$js4=json_encode($pie); $js5=json_encode($pie2);
				
			echo (count($actives)>3) ? "<script>drawLine('dva','period',$js3,$js2,$js3); drawLine('dvd','period',$js3,$js1,$js3); 
			drawPie('dvc','Client Loans Analysis',$js4); drawPie('dvy','Active Clients Loan Cycles',$js5);</script>":
			"<script>drawBar('dva','period',$js3,$js2,$js3); drawBar('dvd','period',$js3,$js1,$js3); 
			drawPie('dvc','Client Loans Analysis',$js4); drawPie('dvy','Active Clients Loan Cycles',$js5);</script>";
				
			$data = "<hr><div class='row' style='margin:0px;padding:0px'>
				<div class='col-sm-12 col-md-12 col-lg-6'><div id='dvc' style='height:360px'></div></div>
				<div class='col-sm-12 col-md-12 col-lg-6'><div id='dvy' style='height:360px'></div></div>
				<div class='col-12'>
					<p><input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fdy)."' id='fdy'
					onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+this.value)\"> To: 
					<input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fto)."'
					onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+_('fdy').value+'&fto='+this.value)\"></p><hr>
				</div>
				<div class='col-sm-12 col-md-12 col-lg-6'>
					<h4 style='font-size:18px;text-align:center'>Active Clients</h4><div id='dva' style='height:360px'></div>
				</div>
				<div class='col-sm-12 col-md-12 col-lg-6'>
					<h4 style='font-size:18px;text-align:center'>Dormant Clients from Active ROS</h4><div id='dvd' style='height:360px'></div>
				</div>
			</div>";
		}
		
		# collection analysis
		if($ftc=="collect"){
			$title = "Collection Analysis";
			$fdy = (isset($_GET['fdy'])) ? strtotime(trim($_GET['fdy'])):$jana;
			$fto = (isset($_GET['fto'])) ? strtotime(trim($_GET['fto'])):$jana;
			$fto = ($fdy>$fto) ? $fdy:$fto;
			$grp = ($bran) ? "day,staff":"day,branch";
			$grp = ($ofid) ? "day,staff":$grp;
			
			$cond2 = ($bran) ? "AND `branch`='$bran'":"";
			$cond2 = ($access=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"]):$cond2;
			$cond2.= ($ofid) ? " AND `staff`='$ofid'":"";
			$graph1=$graph2=$symbols=$colls=$ports=$all=[];
			
			$res = $db->query(2,"SELECT *,SUM(collection) AS coll,SUM(portfolio) AS port FROM `bi_analytics$cid` WHERE `day` BETWEEN $fdy AND $fto $cond2 GROUP BY $grp");
			if($res){
				foreach($res as $row){
					$coll=$row['coll']; $port=$row['port']; $day=$row['day']; $offids[$row['staff']]=$row['staff'];
					$name=(!$bran) ? $bnames[$row['branch']]:$staff[$row['staff']]; $all[$name]=$name;
					if(array_key_exists($day,$colls)){
						$colls[$day][$name]=($coll) ? $coll:0; $ports[$day][$name]=($port) ? $port:0; 
					}
					else{
						$colls[$day][$name]=($coll) ? $coll:0; $ports[$day][$name]=($port) ? $port:0; 
					}
				}
			}
			
			foreach($colls as $day=>$one){
				$dy=date("Y-m-d",$day); $coll=$port=["period"=>$dy];
				foreach($all as $name){
					if(!array_key_exists($name,$one)){ $one[$name]=0; $ports[$day][$name]=0; }
				}
				
				foreach($one as $key=>$val){
					$coll[$key]=$val; $port[$key]=$ports[$day][$key]; $symbols[]=$key; 
				}
				$graph1[]=$coll; $graph2[]=$port;
			}
			
			$js1=json_encode($graph1); $js2=json_encode($graph2); $js3=json_encode(array_unique($symbols)); 
			echo (count($colls)>3) ? "<script>drawLine('dvp','period',$js3,$js1,$js3); drawLine('dvc','period',$js3,$js2,$js3);</script>":
			"<script>drawBar('dvp','period',$js3,$js1,$js3); drawBar('dvc','period',$js3,$js2,$js3);</script>";
				
			$data = "<hr><div class='row' style='margin:0px;padding:0px'>
				<div class='col-12'>
					<p><input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fdy)."' id='fdy'
					onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+this.value)\"> To: 
					<input type='date' style='width:150px;padding:3px;font-size:15px' max='".date('Y-m-d',$jana)."' value='".date('Y-m-d',$fto)."'
					onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid=$ofid&fdy='+_('fdy').value+'&fto='+this.value)\"></p><hr>
				</div>
				<div class='col-sm-12 col-md-12 col-lg-6'>
					<h4 style='font-size:18px;text-align:center'>Effected Collection</h4><div id='dvp' style='height:360px'></div>
				</div>
				<div class='col-sm-12 col-md-12 col-lg-6'>
					<h4 style='font-size:18px;text-align:center'>Gross Portfolio</h4><div id='dvc' style='height:360px'></div>
				</div>
			</div>";
		}
		
		if(($bran or $access=="branch") && $access!="portfolio"){
			$opts="<option value='0'>- Select Staff -</option>";
			foreach($offids as $off){
				$cnd=($off==$ofid) ? "selected":"";
				$opts.="<option value='$off' $cnd>".$staff[$off]."</option>";
			}
			
			$offs = "<select style='width:150px;font-size:15px;' onchange=\"loadpage('bi/portfolio.php?view=$vtp&bran=$bran&ofid='+this.value)\">$opts</select>";
		}
		
		foreach(array("par"=>"Arrears Analysis","client"=>"Client Analysis","collect"=>"Collection Analysis") as $key=>$txt){
			$cnd = ($key==$vtp) ? "selected":"";
			$lis.="<option value='$key' $cnd>$txt</option>";
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;'>
				<h3 style='font-size:22px;color:#191970'>$backbtn $title</h3>
				<div style='width:100%;overflow:auto;margin-top:20px'>
					<p><select style='width:160px;font-size:15px' onchange=\"loadpage('bi/portfolio.php?view='+this.value)\">$lis</select> 
					$brans $offs</p> $data
				</div>
			</div>
		</div>";
		
		savelog($sid,"Accessed $title");
	}
	
	# view arrears
	if(isset($_GET['vclns'])){
		$dur = trim($_GET['vclns']);
		$bran = trim($_GET['bran']);
		$ofid = trim($_GET['ofid']);
		$leo = strtotime(date("Y-M-d")); 
		$page = (isset($_GET['pg'])) ? trim($_GET['pg']):1;
		$me = staffInfo($sid); $cnf=json_decode($me["config"],1);
		
		$fro=strtotime(date("Y-m-d",$leo-(86400*explode("-",$dur)[1]))); 
		$to=strtotime(date("Y-m-d",$leo-(86400*explode("-",$dur)[0])));
		$cond = ($bran) ? "AND ln.branch='$bran'":"";
		$cond = ($me["access_level"]=="region" && isset($cnf["region"]) && !$bran) ? "AND ".setRegion($cnf["region"],"ln.branch"):$cond;
		$cond.= ($ofid) ? " AND ln.loan_officer='$ofid'":"";
		
		$trs=$period=""; 
		$ltbl = "org$cid"."_loans"; $stbl = "org$cid"."_schedule"; $ctbl = "org$cid"."_clients";
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($res as $row){
			$staff[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$perpage = 40;
		$lim = getLimit($page,$perpage);
		$qri = $db->query(2,"SELECT *FROM `$ltbl` AS ln INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan WHERE sd.day BETWEEN $fro AND $to 
		AND sd.balance>0 AND ln.balance>0 $cond GROUP BY client_idno");
		$totals = count($qri);
		
		$no = ($perpage*$page)-$perpage;
		$qry = $db->query(2,"SELECT ln.*,cl.cycles,SUM((CASE WHEN (sd.day<=$to) THEN sd.balance ELSE 0 END)) AS arrs FROM `$ltbl` AS ln 
		INNER JOIN `$stbl` AS sd ON ln.loan=sd.loan INNER JOIN `$ctbl` AS cl ON ln.client_idno=cl.idno WHERE sd.day BETWEEN $fro AND $to AND sd.balance>0 
		AND ln.balance>0 $cond GROUP BY client_idno $lim");
		foreach($qry as $row){
			$name=prepare(ucwords($row['client'])); $cont=$row['phone']; $off=$staff[$row['loan_officer']]; $loan=number_format($row['amount']); 
			$exp=date("d-m-Y",$row['expiry']); $tbal=number_format($row['balance']); $arrs=number_format($row['arrs']); $cycs=$row['cycles']; $no++;
			
			$color = ($row['expiry']<$leo) ? "#DC143C":"#483D8B";
			$trs.="<tr style='color:$color'><td>$no</td><td>$name</td><td>0$cont</td><td>$cycs</td><td>$off</td><td>$loan</td><td>$exp</td>
			<td>$arrs</td><td>$tbal</td></tr>";
		}
		
		if($bran){
			$res = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bran'");
			$bname = prepare(ucwords($res[0]['branch'])); $period=" for $bname";
		}
		
		echo "<div class='cardv' style='max-width:1200px;min-height:400px;overflow:auto'>
			<div class='container' style='padding:7px;'>
				<h3 style='font-size:20px;color:#191970'>$backbtn $dur Days Arrears Analysis $period</h3>
				<div style='width:100%;overflow:auto;margin-top:20px'>
					<table style='min-width:600px;width:100%;font-size:15px' cellpadding='5' class='table-striped'>
						<tr style='color:#191970;font-size:15px;background:#e6e6fa;font-weight:bold' valign='top'><td colspan='2'>Client</td><td>Contact</td>
						<td>Cycles</td><td>Loan Officer</td><td>Loan</td><td>Maturity</td><td>P.Arrears</td><td>T.Balance</td></tr> $trs
					</table>
				</div>
			</div>".getLimitDiv($page,$perpage,$totals,"bi/portfolio.php?vclns=$dur&bran=$bran&ofid=$ofid")."
		</div>";
		
		savelog($sid,"$dur Days Arrears Analysis $period");
	}

	@ob_end_flush();
?>