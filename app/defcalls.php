<?php
	
	$aptheme = (defined("APP_COLOR")) ? APP_COLOR:"#2f4f4f";
	function limitDiv($page,$perpage,$total,$jscall){
		$calc = $perpage * $page; 
		$start = $calc - $perpage;
		$next = $page+1;
		$prev = ($page>1) ? $page-1:1;
		$all = ceil($total/$perpage);
		$prevbtn=$btfirst=$bt1=$nextbtn=$btlast="";
		$css1 = ($page==1) ? "border:2px solid #ADD8E6;color:#ADD8E6":"";
		$css2 = ($page==$all) ? "border:2px solid #ADD8E6;color:#ADD8E6":"";
		
		if($page>1){
			$prevbtn = "<button class='pgbtn' onclick=\"window.location.href='$jscall&pg=$prev'\"><i class='fa fa-angle-left'></i> Prev</button>";
			$btfirst = ($page>2) ? "<button class='pgbtn' onclick=\"window.location.href='$jscall&pg=1'\"><i class='fa fa-angle-double-left'></i> 1</button>":"";
		}
		if(($all-$page)>1){
			$btlast = "<button class='pgbtn' onclick=\"window.location.href='$jscall&pg=$all'\"><i class='fa fa-angle-double-right'></i> $all</button>";
		}
		if($all>$page){
			$nextbtn = "<button class='pgbtn' onclick=\"window.location.href='$jscall&pg=$next'\"><i class='fa fa-angle-right'></i> Next</button>";
		}
		
		$bt1 = "<span style='color:#4682b4;'><b>$page</b> of <span style='color:grey'>$all</span></span>";
		$click = ($page>1) ? "onclick=\"window.location.href='$jscall&pg=$prev'\"":""; $add = ($page==1) ? "disabled":"";
		$click2 = ($all>$page) ? "onclick=\"window.location.href='$jscall&pg=$next'\"":""; $add2 = ($page==$all) ? "disabled":"";
		$prevbtn = "<button class='pgbtn' style='float:left;$css1' $click $add><i class='fa fa-angle-left'></i> Page $prev</button>";
		$nextbtn = "<button class='pgbtn' style='float:right;$css2' $click2 $add2>Page $next <i class='fa fa-angle-right'></i></button>";
		return ($all>1) ? "<div style='width:100%;height:60px;margin:0 auto;padding:15px 0px;text-align:center'>$prevbtn $bt1 $nextbtn</div>":"";
	}
	
	function passwdInput($name="password",$value="",$place="",$css="",$attr=""){
		return "<div style='border:1px solid #ccc;$css' class='pstb'>
		<table style='width:100%;margin:0px;background:transparent' cellpadding='0' cellspacing='0'><tr>
			<td><input type='password' placeholder='$place' style='width:100%;border:0px;outline:none;box-shadow:none' $attr onblur=\"outlinetbl('out')\" onfocus=\"outlinetbl('in')\" 
			id='psin' name='$name' value='$value' required></td><td style='width:35px;text-align:center'><span id='vpin' style='font-size:26px;cursor:pointer' onclick='togpassd()'>
			<i class='fa fa-eye' title='View Password'></i></span></td>
		</tr></table></div><br><script>
			function outlinetbl(vtp){
				if(vtp=='in'){ $('.pstb').css({\"border\":\"1px solid #4682b4\"}); }
				else{ $('.pstb').css({\"border\":\"1px solid #ccc\"}); }
			}
			
			function togpassd(inp){
				var x = document.getElementById('psin');
				if(x.type === 'password'){ $('#vpin').html('<i class=\"fa fa-eye-slash\" title=\"Hide Password\"></i>'); x.type='text'; } 
				else{ $('#vpin').html('<i class=\"fa fa-eye\" title=\"View Password\"></i>'); x.type='password'; }
			}
		</script>";
	}
	
	function loginType(){
		if(isset($_COOKIE["bid"])){
			$bid = decrypt($_COOKIE["bid"],"bkey");
			return explode(":",$bid)[0];
		}
		else{ return "None"; }
	}

?>