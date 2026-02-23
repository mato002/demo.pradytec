
	function devid(){ return window.NativeJavascriptInterface.showDevice(); }
    function alerts(title,txt){ window.NativeJavascriptInterface.myAlertDialog(title,txt); } 
    function toast(txt){ window.NativeJavascriptInterface.showToast(txt); } 
    function error(txt){ window.NativeJavascriptInterface.showToastFancyErrorToast(txt); } 
    function success(txt){ window.NativeJavascriptInterface.showToastFancySuccessToast(txt); } 
    function cleanstr(str){ return str.trim().split(" ").join('+'); }
	
	function valid(id,v){
		var exp = /^[0-9.%]+$/;
		if(!v.match(exp)){ document.getElementById(id).value=v.slice(0,-1); }
	}
	
	function progress(title,text){
        if(title==null){ window.NativeJavascriptInterface.dismissProgressDialog(); }
        else{ window.NativeJavascriptInterface.showProgressDialog(title,text); }
    }
	
	function logout(str){
		window.NativeJavascriptInterface.clearDataCache(str);
	}
	
	function requestotp(fon,name){
		$.ajax({
			method:"post",url:"post.php",data:{reqotp:fon,reqname:name},
			beforeSend:function(){ progress("Requesting","Processing...please wait"); },
			complete:function(){ progress(); },timeout:90000
		}).fail(function(){
			error("Failed: Check internet Connection");
		}).done(function(res){
			if(res.trim()=="success"){ success("Success!"); }
			else{ alerts(res); }
		});
	}
    
    function setCookie(cname, cvalue, dur=30){
		var d = new Date();
		d.setTime(d.getTime() + (dur*24*60*60*1000));
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
	}
	
	function getcookie(name){
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++){
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	}
	
	function Timer(day,div){
		var countDownDate = new Date(day).getTime();
		setInterval(function() {
			var now = new Date().getTime();
			var timeleft = countDownDate - now;
			
			var days = Math.floor(timeleft / (1000 * 60 * 60 * 24));
			var hours = Math.floor((timeleft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			var minutes = Math.floor((timeleft % (1000 * 60 * 60)) / (1000 * 60));
			var seconds = Math.floor((timeleft % (1000 * 60)) / 1000);
			
			$("#"+div).html("<span style='color:#DC143C'>"+days+"d</span><span style='color:#008fff'> "+hours+"h</span>"+
			"<span style='color:#9400D3'> "+minutes+"m</span><span style='color:#FF00FF'> "+seconds+"s</span>");
			
			if (timeleft < 0) {
				clearInterval(this); $("#"+div).html("--:--");
			}
		},1000);
	}