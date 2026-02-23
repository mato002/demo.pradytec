<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	if(isset($_GET['ref'])){
		$ref = trim($_GET['ref']); $divs=""; $times=[];
		$qri = $db->query(2,"SELECT *FROM `org".$cid."_staff`");
		foreach($qri as $row){
			$names[$row['id']]=prepare(ucwords($row['name']));
		}
		
		$sub = prepare($db->query(2,"SELECT `subject` FROM `tickets$cid` WHERE `ref`='$ref'")[0]['subject']);
		$qri = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `ref`='$ref'"); 
		foreach($qri as $row){
			$msg=prepare($row['message']); $mssg=explode("~>",ucfirst($msg)); $tym=date("d-m-Y, H:i",$row['time']); $from=$row['from']; $rid=$row['id'];
			$css = "clear:both;max-width:80%;padding:7px;border-radius:5px;margin-top:10px"; $cto=($from==$sid) ? $row['receiver']:$from; 
			$img = (substr(prepare($row['message']),0,5)=="sPIC:") ? explode(":",$mssg[0])[1]:null; $times[]=$row['time']; $reply=$repchat="";
			$chat = ($img) ? "<img src='data:image/jpg;base64,".getphoto($img)."' style='max-width:100%;'><br>".nl2br($mssg[1]):nl2br($mssg[0]);
			$code = ($sub=="B2C Inquiry" && strpos($msg,"transaction")!==false) ? explode(" ",substr($msg,strpos($msg,"transaction")))[1]:"";
			$isrep = (substr($row['reply'],0,2)==88) ? 1:0; $ran=rand(12345678,87654321);
			
			if($sub=="B2C Inquiry"){
				$reply = ($row['reply']<300) ? "<button class='btnn' style='padding:3px;float:left;margin-right:20px;min-width:60px;background:#6495ed'
				onclick=\"replyto('$code','$rid')\"><i class='fa fa-mail-forward'></i> Reply</button>":"";
				$reply = ($isrep && substr($row['reply'],0,3)==888) ? "<button class='btnn' id='$ran' style='padding:3px;float:left;margin-right:20px;min-width:60px;'
				onclick=\"popupload('accounts/index.php?postentry=".substr($row['reply'],3)."&ran=$ran')\"><i class='fa fa-upload'></i> Post</button>":$reply;
			}
			
			if($isrep){
				$sql = $db->query(2,"SELECT *FROM `tickets$cid` WHERE `id`='".substr($row['reply'],3)."'");
				$repchat = ($sql) ? "<p style='background:#f0f0f0;color:#191970;border:1px solid #ccc;padding:7px;margin-bottom:5px'><i>".prepare(substr($sql[0]['message'],0,80))."...</i></p>":"";
			}
			
			$divs.= ($sid==$from) ? "<div style='$css;float:right;background:#E0FFFF;border:1px solid #ADD8E6;color:#4682b4'> $repchat
			<p style='margin-bottom:7px'>$chat</p> <p style='color:#9370DB;text-align:right;font-size:14px;margin:0px;font-family:signika negative'>$tym</p>
			</div>":"<div style='$css;float:left;background:#fff;border:1px solid #B0C4DE;color:#191970'>
				<p style='color:#4169E1;margin-bottom:7px;font-family:signika negative'>$names[$cto]</p> $repchat
				<p style='margin-bottom:7px'>$chat</p><p style='color:#9370DB;text-align:right;font-size:14px;margin:0px;font-family:signika negative'>$reply $tym</p>
			</div>";
		}
	}
	else{ exit(); }
	
	$maxtm = (count($times)) ? max($times):time(); $src="$cto:$ref";
	$db->insert(2,"UPDATE `tickets$cid` SET `reply`='200' WHERE `ref`='$ref' AND `receiver`='$sid' AND `time`<=$maxtm AND `reply`<=200");
	
	ob_end_flush();
	
?>

<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="theme-color" content="#4682b4">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="robots" content="index,follow"/>
	<title>Ticket <?php echo "$ref | $sub"; ?></title>
	<link rel="shortcut icon" href="../docs/img/favicon.ico">
	<script src="../assets/js/jquery.js"></script>
	<link rel="stylesheet" href="../assets/css/bootstrap4.css">
	<link rel="stylesheet" href="../assets/css/client.css?<?php echo rand(1234,4321); ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link href="https://fonts.googleapis.com/css?family=Signika Negative&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
	<script src="../assets/js/bootstrap4.js"></script>
	<script src="../assets/js/bootstrap.bundle.js"></script>
	</head>
	<body>
		
		<div class="popup">
			<div style="text-align:center;padding:10px">
				<table style="width:100%" cellpadding="8">
					<tr><td colspan="2"><img id="previmg" style="max-width:100%;max-height:400px"></td></tr>
					<tr><td><br><button class="btnn" style="padding:5px;background:#DC143C;" onclick="closepop()"><i class="bi-x-lg"></i> Cancel</button></td>
					<td><br><button class="btnn" style="padding:5px;" onclick="uploadpic()"><i class="bi-upload"></i> Upload</button></td></tr>
				</table><br>
			</div>
		</div>
		
		<div class="popup mdv"><p style="text-align:right;padding:10px 10px 0px 0px;margin:0px"><i class="bi-x-lg" style="color:#ff4500;cursor:pointer" onclick="closepop()"></i></p>
		<div style="text-align:center;padding:10px;font-family:cambria;text-align:left" class="popin"></div></div>
		
		<div id="progr"><div id="progt"></div></div> <div class="overlay"></div>
		<div class="wrap">
			<div class="prog" style="line-height:50px;background:#fff;border-radius:5px;top:15%;left:5%;right:5%;position:fixed;margin:0 auto;max-width:300px;
			font-family:signika negative;color:#4682b4;text-align:center">Posting...</div>
		</div> 
		
		<table style="width:100%;height:100%;max-width:500px;margin:0 auto" cellpadding="10">
			<tr><td style="height:60px;font-family:signika negative;background:#fff;box-shadow:0px 2px 3px #dcdcdc">
				<h3 style="color:#008fff;padding:0px 20px;font-size:20px;"><?php echo "$sub #$ref"; ?></h3>
			</td></tr>
			<tr style="overflow:auto;"><td>
				<div style="overflow:auto;height:100%;" id="activity">
					<input type='hidden' id='maxtm' value='<?php echo $maxtm; ?>'>
					<?php echo $divs; ?>
				</div>
			</td></tr>
			<tr><td style="height:90px;background:#fff;border-top:1px solid #f0f0f0;">
				<form method="post" id="cform" onsubmit="sendmssg(event,'<?php echo $sub; ?>')">
					<input type='hidden' name="repto" id='repto' value="">
					<input type="file" name="pic" id="pic" accept="image/*" style="display:none" onchange="getpic(event)">
					<input type="hidden" name="cto" value="<?php echo $cto; ?>"> <input type="hidden" name="ref" value="<?php echo $ref; ?>">
					<table style="width:100%;"><tr valign="bottom">
						<td><textarea name="mssg" style="height:80px;border:0px;padding:1px;font-family:helvetica;resize:none;outline:none;width:100%" 
						placeholder="Type Message" id="mssg" onkeyup="scrolbottom()" autofocus required></textarea></td>
						<td style="width:70px;text-align:right"> 
						<label for="pic"><i class="fa fa-camera" style="font-size:32px;cursor:pointer;color:#008080;margin-bottom:7px"></i></label><br>
						<button class="btnn" style="min-width:50px;padding:5px"><i class="fa fa-send"></i> Send</button></td>
					</tr></table>
				</form>
			</td></tr>
		</table>
	</body>
	</html>
	
	<script>
		
		function uploadpic(){
			var img = _("pic").files[0];
			var formdata=new FormData(_("cform"));
			var xhr=new XMLHttpRequest();
			xhr.upload.addEventListener("progress",picprogress,false);
			xhr.addEventListener("load",picdone,false);
			xhr.addEventListener("error",picerror,false);
			xhr.addEventListener("abort",picabort,false);
			formdata.append("photo",img);
			xhr.onload=function(){
				if(this.responseText.trim()=="success"){
					loadmssg(); $(".wrap").fadeOut(); document.getElementById("cform").reset();
				}
				else{ alert(this.responseText); }
			}
			xhr.open("post","dbsave/tickets.php",true);
			xhr.send(formdata);
		}
		
		function picprogress(event){
			closepop(); $(".wrap").fadeIn();
			var percent=(event.loaded / event.total) * 100; 
			$(".prog").html("Uploading photo "+Math.round(percent)+"%");
			if(percent==100){
				$(".prog").html("Cropping...please wait");
			}
		}
		
		function picdone(event){ $(".wrap").fadeOut(); }
		function replyto(code,rid){
			$("#repto").val(rid); $("#mssg").focus(); $("#mssg").val("Transaction "+code+"\r\n"); 
		}	
			
		function picerror(event){
			alert("Upload failed"); $(".wrap").fadeOut();
		}
			
		function picabort(event){
			alert("Upload aborted"); $(".wrap").fadeOut();
		}
		
		function getpic(e){
			var img = _("pic").files[0], ftp = img.type.toLowerCase(), output = _("previmg");
			if(img){
				if(ftp=="image/png" || ftp=="image/jpg" || ftp=="image/jpeg" || ftp=="image/gif"){
					$(".overlay").fadeIn(); $(".popup").fadeIn(); 
					output.src = URL.createObjectURL(e.target.files[0]);
					output.onload = function(){
					  URL.revokeObjectURL(output.src);
					}
				}
				else{ alert("Error! File format not supported!"); }
			}
		}
		
		function closepop(){
			URL.revokeObjectURL(_("previmg").src);
			$(".overlay").fadeOut(); $(".popup").fadeOut();
		}
		
		function popupload(url){
			$(".popup.mdv").fadeIn(); $(".overlay").fadeIn();
			$(".popin").html("<p style='line-height:100px;text-align:center;color:#4682b4;font-weight:bold'>Loading...please wait</p>");
			$(".popin").load(url);
		}
		
		function scrolbottom(){
			var div=_("activity");
			$("#activity").animate({
				scrollTop:div.scrollHeight-div.clientHeight
			},500);
		}
		
		scrolbottom(); setInterval(checkmssg,5000);
		function _(e){ return document.getElementById(e); }
		
		function sendmssg(e,sub){
			e.preventDefault();
			var code = (sub=="B2C Inquiry") ? $("#repto").val().trim():1;
			if(code<1){ alert("You need to click on reply button for individual transaction you want to reply"); }
			else{
				var data = $("#cform").serialize();
				$.ajax({
					method:"post",url:"dbsave/tickets.php",data:data,
					beforeSend:function(){ $(".wrap").fadeIn(); $(".prog").html("<img src='../assets/img/waiting.gif'> Posting...please wait"); },
					complete:function(){ $(".wrap").fadeOut(); }
				}).fail(function(){
					alert("Failed: Check internet Connection");
				}).done(function(res){
					if(res.trim()=="success"){
						loadmssg(); $(".wrap").fadeOut(); document.getElementById("cform").reset();
						document.getElementById("mssg").focus();
					}
					else{ alert(res); }
				});
			}
		}
		
		function checkmssg(){
			var mx = parseInt(_("maxtm").value.trim());
			$.ajax({
				method:"post",url:"tickets.php?sid=<?php echo $sid; ?>",data:{maxtm:"<?php echo $src; ?>"}
			}).done(function(res){
				if(parseInt(res.trim())>mx){ loadmssg(); } 
			});
		}
		
		function loadmssg(){
			$("#activity").load("tickets.php?fetchchats=<?php echo $src; ?>&sid=<?php echo $sid; ?>"); 
			setTimeout(scrolbottom,200); 
		}
	
	</script>
	