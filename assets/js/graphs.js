	
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
	
	function drawStack(div,dvh,mtitle,jdata,ytitle,xtitle){
		function drawView(){
			var data = google.visualization.arrayToDataTable(jdata);
			new google.visualization.ColumnChart(document.getElementById(div)).
			draw(data,{title:mtitle, width:$("#".div).width(), height:dvh,vAxis: {title: ytitle}, isStacked: true, hAxis: {title: xtitle}});
		}
		
		google.load("visualization", "1", {packages:["corechart"]});
		google.setOnLoadCallback(drawView);
	}