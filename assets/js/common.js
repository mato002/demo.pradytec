	
	$(window).on('keydown',function(){
		if(event.keyCode==123){
			return false;
		}
		else if(event.ctrlKey && event.shiftKey && event.keyCode==73){
			return false;
		}
		else if(event.ctrlKey && event.keyCode==73){
			return false;
		}
		else if(event.ctrlKey && event.keyCode==85){
			return false;
		}
	});
	$(document).on("contextmenu",function(){
		return false;
	});