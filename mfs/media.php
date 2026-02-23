<?php
	session_start();
	ob_start();
	if(!isset($_SESSION['myacc'])){ exit(); }
	$sid = substr(hexdec($_SESSION['myacc']),6);
	if($sid<1){ exit(); }
	
	include "../core/functions.php";
	$db = new DBO(); $cid = CLIENT_ID;
	
	# client media safes
	if(isset($_GET['safes'])){
		
	}
	
	# view attached media
	if(isset($_GET["vmed"])){
		$tbl = clean($_GET["vmed"]);
		$rid = clean($_GET["src"]); 
		$lis = "";
		
		if(!is_dir("../docs/temps")){ mkdir("../docs/temps",0777,true); }
		$res = $db->query(1,"SELECT *FROM `created_tables` WHERE `client`='$cid' AND `name`='$tbl'"); 
		if($res){ 
			$cols = json_decode($res[0]['fields'],1); $media=[]; 
			foreach($cols as $col=>$dtp){
				if(in_array($dtp,["image","pdf","docx"])){ $media[$col]=$dtp; }
			}
			
			$def = ["org$cid"."_loans"=>"org$cid"."_loantemplates"]; 
			$tbl = (isset($def[$tbl])) ? $def[$tbl]:$tbl;
			
			$qry = $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$rid'");
			if($qry){
				foreach($qry[0] as $col=>$df){
					if(isset($media[$col])){
						$dtp = $media[$col];
						$css = "font-size:18px;color:#191970;background:#f0f0f0;padding:8px;cursor:pointer;";
						
						if($dtp=="image"){
							if(strlen($df)>2){
								$pic = getphoto($df); file_put_contents("../docs/temps/$df",base64_decode($pic));
								$lis.= "<h3 style='$css'><i class='bi-image'></i> ".ucwords(str_replace("_"," ",$col))." <i class='bi-chevron-down' style='float:right'></i></h3> 
								<div style='text-align:center;border:1px dotted #ccc;margin:-10px 0px 10px 0px;border-top:none'>
								<p><img src='$path/docs/temps/$df' style='cursor:pointer;max-height:500px;max-width:100%;margin-top:3px' onload=\"rempic('$df')\"
								onclick=\"popupload('media.php?doc=img:$df&tbl=$tbl:$col&fd=id:$rid')\"></p></div>";
							}
							else{
								$lis.= "<h3 style='$css'><i class='bi-image'></i> ".ucwords(str_replace("_"," ",$col))." <i class='bi-caret-down-fill' style='float:right'></i></h3> 
								<div style='text-align:center;border:1px dotted #ccc;margin:-10px 0px 10px 0px;border-top:none'>
								<p><img src='$path/docs/img/tempimg.png' style='cursor:pointer;max-height:500px;max-width:100%;margin-top:3px'
								onclick=\"popupload('media.php?doc=img:$df&tbl=$tbl:$col&fd=id:$rid')\"></p></div>";
							}
						}
						if($dtp=="pdf" or $dtp=="docx"){
							$img = ($dtp=="pdf") ? "$path/assets/img/pdf.png":"$path/assets/img/docx.JPG";
							$lis.= "<h3 style='$css'><i class='bi-file-earmark-text-fill'></i> ".ucwords(str_replace("_"," ",$col))." 
							<i class='bi-caret-down-fill' style='float:right'></i></h3> 
							<div style='text-align:center;border:1px dotted #ccc;margin:-10px 0px 10px 0px;border-top:none'>
							<p><img src='$img' style='cursor:pointer;height:100px;margin-top:3px'
							onclick=\"popupload('media.php?doc=$dtp:$df&tbl=$tbl:$col&fd=id:$rid')\"></p></div>";
						}
					}
				}
			}
		}
		
		$data = ($lis) ? $lis:"<p style='border:1px solid pink;padding:10px;background:#FFF0F5;color:#B22222;text-align:center'>
		<i class='bi-exclamation-circle' style='font-size:44px'></i><br> No media Files Retrieved</p>";
		
		echo "<div style='padding:10px;max-width:450px;margin:0 auto'>
			<h3 style='color:#191970;text-align:center;font-size:23px'>Attached Media Files</h3><br>
			<div id='docs'> $data </div><br>
		</div>
		<script> $(function(){ $('#docs').accordion({heightStyle: 'content'}); }); </script>";
	}
	
	# upload media
	if(isset($_FILES['media'])){
		$tmp = $_FILES['media']['tmp_name'];
		$name = strtolower(trim($_FILES['media']['name']));
		$ext = @array_pop(explode(".",$name));
		$tbl = explode(":",trim($_POST['ctbl']));
		$col = explode(":",trim($_POST['dcol']));
		$dtp = explode(":",trim($_POST['fname']));
		
		if($dtp[0]=="img"){
			if(@getimagesize($tmp)){
				if(in_array($ext,array("jpg","jpeg","png","gif"))){
					$img = "IMG_".date("Ymd_His").".$ext";
					if(move_uploaded_file($tmp,"../docs/img/$img")){
						if(crop("../docs/img/$img",800,700,"../docs/img/$img")){
							if($db->insert(2,"UPDATE `$tbl[0]` SET `$tbl[1]`='$img' WHERE `$col[0]`='$col[1]'")){
								insertSqlite("photos","REPLACE INTO `images` VALUES('$img','".base64_encode(file_get_contents("../docs/img/$img"))."')"); 
								if($dtp[1]){ insertSqlite("photos","DELETE FROM `images` WHERE `image`='".$dtp[1]."'"); }
								unlink("../docs/img/$img");
								echo "success";
							}
							else{ echo "Failed to update photo! Try again later"; unlink("../docs/img/$img"); }
						}
						else{ echo "Failed to save photo"; }
					}
					else{ echo "Failed: Unknown Error occured"; }
				}
				else{ echo "Photo extention $ext is not supported"; }
			}
			else{ echo "File selected is not an Image"; }
		}
		else{
			if(in_array($ext,array("docx","pdf"))){
				$doc = "DOC_".date("Ymd_His").".$ext";
				if(move_uploaded_file($tmp,"../docs/$doc")){
					if($db->insert(2,"UPDATE `$tbl[0]` SET `$tbl[1]`='$doc' WHERE `$col[0]`='$col[1]'")){
						if($dtp[1]){ unlink("../docs/$dtp[1]"); }
						echo "success";
					}
					else{
						echo "Failed to update document! Try again later"; unlink("../docs/$img");
					}
				}
				else{ echo "Failed: Unknown Error occured"; }
			}
			else{ echo "Document extention $ext is not supported"; }
		}
	}
	
	# delete media
	if(isset($_POST['delrecord'])){
		$src = explode(":",trim($_POST['delrecord']));
		$tbl = $src[0]; $col=$src[1]; $upd=$src[2]; $val=$src[3];
		
		$res = $db->query(2,"SELECT $col FROM `$tbl` WHERE `$upd`='$val'"); $media = $res[0][$col];
		if($media){
			$file = (in_array(explode(".",$media)[1],array("docx","pdf","doc"))) ? "../docs/$media":"../docs/img/$media";
		}
		
		$qry = ($tbl=="sysdocs$cid") ? "DELETE FROM `$tbl` WHERE `id`='$val'":"UPDATE `$tbl` SET `$col`='0' WHERE `$upd`='$val'";
		if($db->execute(2,$qry)){
			if($media){
				if(file_exists($file)){ unlink($file); }
				else{ insertSqlite("photos","DELETE FROM `images` WHERE `image`='$media'"); }
			}
			echo "success";
		}
		else{ echo "Failed to complete the request! Try again later"; }
	}
	
	# view media
	if(isset($_GET['doc'])){
		$doc = explode(":",trim($_GET['doc']));
		$col = trim($_GET['fd']);
		$tbl = trim($_GET['tbl']);
		$frm = trim($_GET['doc']);
		$ctr = (isset($_GET["nch"])) ? 0:1;
		$pos = staffInfo($sid)["position"];
		$rid = rand(12345678,999999999); 
		$allow=0; $dtxt="";
		
		if(defined("SYS_MEDIA_CONTROL") && $ctr){
			$ctr = (in_array($pos,SYS_MEDIA_CONTROL)) ? 1:0;
		}
		
		if(explode(":",$tbl)[0]=="sysdocs$cid"){
			$id = explode(":",$col)[1];
			$sql = $db->query(2,"SELECT *FROM `sysdocs$cid` WHERE `id`='$id'");
			$def = json_decode($sql[0]["def"],1); $frm=$def["from"]; $tym=$sql[0]["time"]; $allow=($frm==$sid) ? 1:0;
			$dtxt = "<span style='font-size:14px;color:grey'>Uploaded by ".ucwords(staffInfo($frm)["name"])." on ".date("d-m-Y, H:i",$tym)."</span>";
		}
	
		if($doc[0]=="img"){
			$dtp = "image/*"; $ctr=(strlen($doc[1])>2) ? $ctr:1;
			$src = (strlen($doc[1])>1) ? "data:image/jpg;base64,".getphoto($doc[1]):"$path/assets/img/user.png";
			$img = "<img style='max-width:100%;max-height:550px' id='$rid' src='$src'>";
			$del = ($allow or (in_array($pos,["super user","C.E.O","CEO","Director"]) && $ctr)) ? "delmedia('$tbl:$col','Photo')":"toast('Permission denied!')";
			$upd = ($ctr or $allow) ? "<td><label for='dc$rid' style='cursor:pointer;margin:0px'><i class='bi-camera' style='font-size:30px;' title='Change Photo'></i></label></td>":"";
			
			$trs = "<tr>$upd <td><i class='bi-zoom-in' style='cursor:pointer;font-size:30px' title='Zoom Out' onclick=\"resizeimg('$rid','+')\"></i></td>
			<td><i class='bi-zoom-out' style='cursor:pointer;font-size:30px' title='Zoom In' onclick=\"resizeimg('$rid','-')\"></i></td>
			<td><i class='bi-arrow-repeat' style='cursor:pointer;font-size:30px' title='Rotate 90 Degrees' onclick=\"rotatepic('$tbl:$col','$rid')\"></i></td>
			<td><i class='bi-trash' style='cursor:pointer;font-size:30px;' title='Remove Photo' onclick=\"$del\"></i></td></tr>";
		}
		else{
			$url = "https://docs.google.com/viewer?url=http://$url/docs/$doc[1]&embedded=true";
			$open = ($doc[1]) ? "<a href='$url' target='_blank'>Open Document</a>":"";
			$img = ($doc[0]=="docx") ? "docx.JPG":"pdf.png"; $dtp = ".$doc[0]"; $ctr=(strlen($doc[1])>2) ? $ctr:1;
			$del = ($allow or (in_array($pos,["super user","C.E.O","CEO","Director"]) && $ctr)) ? "delmedia('$tbl:$col','Photo')":"toast('Permission denied!')";
			$img = "<figure style='margin:0px'><img style='height:100px' src='$path/assets/img/$img'><figcaption>$open</figcaption></figure>";
			$trs = ($ctr or $allow) ? "<tr><td><label class='bts' for='dc$rid' style='cursor:pointer;margin:0px'><i class='fa fa-file-text'></i> Change Doc</label></td>
			<td><button class='bts' onclick=\"delmedia('$tbl:$col','Document')\" style='color:#ff4500;margin:0px'><i class='fa fa-times'></i> Remove Doc</button></td></tr>":"";
		}
		
		echo "<div style='padding:10px;margin:0 auto;max-width:550px'>
			<div class='mediv' style='width:100%;text-align:center'><center>$img</center>$dtxt</div><br> 
			<input type='hidden' id='dtbl' value='$tbl'> <input type='hidden' id='dcol' value='$col'>
			<input type='file' accept='$dtp' style='display:none' id='dc$rid' onchange=\"changedoc('dc$rid','$frm')\">
			<table style='margin:0 auto;min-width:280px;text-align:center;border-color:#dcdcdc;background:#f8f8f0;' cellpadding='7' border='1'>$trs</table><br>
		</div><br>
		<script>
			var rdeg = 0;
			function rotatepic(pic,id){
				rdeg = (rdeg==360) ? 0:rdeg; rdeg+=Number(90);
				var width = $('#'+id).width(), hght=$('#'+id).height();
				$('#'+id).css({
					'-webkit-transform': 'rotate('+rdeg+'deg)',
					'-moz-transform': 'rotate('+rdeg+'deg)',
					'transform': 'rotate('+rdeg+'deg)',
					'margin-top':'20px'
				});
				if(width>hght){ $('.mediv').height(width); }
			}
			
			function resizeimg(img,tp){
				var width = $('#'+img).width(), height=$('#'+img).height(); 
				if(tp=='+'){
					if(width<550 && height<550){ $('#'+img).width(width*1.2).height(height*1.2); }
				}
				else{ if(width>250 && height>250){ $('#'+img).width(width*0.8).height(height*0.8); }}
			}
		</script>";
	}

	ob_end_flush();
?>