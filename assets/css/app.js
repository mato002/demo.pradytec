
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