	
	function validate(e){
		e.preventDefault();
		var data = $("#vform").serialize();
		$.ajax({
			method:"post",url:"validate.php",data:data,
			beforeSend:function(){ progress(" processing...please wait"); }
		}).fail(function(){
			alert("Failed: Check internet Connection"); progress();
		}).done(function(res){
			if(res.trim().split(":")[0]=="success"){
				progress("Success...redirecting");
				window.location.replace(res.trim().split(":")[1]);
			}
			else{
				progress();
				setTimeout(function(){
					if(res.trim()==""){ alert("Sorry! Your session has expired!"); window.location.reload(); }
					else{ alert(res); }
				},200);
			}
		});
	}
	
	function genreset(e){
		e.preventDefault();
		var data = $("#rform").serialize();
		$.ajax({
			method:"post",url:"validate.php",data:data,
			beforeSend:function(){ progress(" processing...please wait"); },
			complete:function(){ progress(); }
		}).fail(function(){
			alert("Failed to complete your request! Check Internet connection");
		}).done(function(res){
			if(res.trim().split("!")[0]=="Success"){
				setTimeout(function(){ alert(res); window.history.back(); },200); 
			}
			else{
				if(res.trim()==""){ progress("Retrying...please wait"); window.location.reload(); }
				else{
					setTimeout(function(){
						if(res.trim()==""){ alert("Sorry! Your session has expired!"); window.location.reload(); }
						else{ alert(res); }
					},200);
				}
			}
		});
	}
	
	function progress(txt){
		if(txt==null){ $(".progbtn").hide(); $(".btn1").show(); }
		else{
			$(".btn1").hide(); $(".progbtn").show();
			$(".progbtn").html("<img src='assets/img/waiting.gif'> "+txt);
		}
	}
		
	function changepass(e){
		e.preventDefault();
		if($("#pass1").val().trim()!=$("#pass2").val().trim()){
			alert("Password Mismatch! Retype matching Password!"); $("#pass2").val(""); $("#pass2").focus();
		}
		else{
			var data = $("#vform").serialize();
			$.ajax({
				method:"post",url:"validate.php",data:data,
				beforeSend:function(){ progress("Processing...please wait"); }
			}).fail(function(){
				alert("Failed: Check internet Connection"); progress();
			}).done(function(res){
				if(res.trim().split(":")[0]=="success"){
					progress("Success...redirecting");
					window.location.replace(res.trim().split(":")[1]);
				}
				else{
					progress();
					setTimeout(function(){
						if(res.trim()==""){ alert("Sorry! Your session has expired!"); window.location.reload(); }
						else{ alert(res); }
					},200);
				}
			});
		}
	}
	
		
	function login(e){
		e.preventDefault();
		var data = $("#logform").serialize();
		$.ajax({
			method:"post",url:"validate.php",data:data,
			beforeSend:function(){ progress("Processing...please wait"); }
		}).fail(function(){
			alert("Failed: Check internet Connection"); progress();
		}).done(function(res){
			if(res.trim().split(":")[0]=="success"){
				progress("Login successful...redirecting");
				window.location.replace(res.trim().split(":")[1]);
			}
			else{
				progress();
				setTimeout(function(){
					if(res.trim()==""){ alert("Sorry! Your session has expired!"); window.location.reload(); }
					else{ alert(res); }
				},200);
			}
		});
	}