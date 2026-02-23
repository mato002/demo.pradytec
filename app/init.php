<?php
	require "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	# check client
	if(isset($_POST["fidno"])){
		$idno = clean($_POST["fidno"]);
		$seck = clean($_POST["seck"]);
		
		if($dy=decrypt($seck,$idno)){
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `position`='USSD' AND `gender`='app' AND `status`='0'");
			if(!$qri){
				$mail = "app@".$_SERVER['HTTP_HOST'];
				$keys = apikeys(); $ssl=genKeys(); $now=time();
				$prkey = base64_encode($ssl["private"]);
				$pbkey = base64_encode($ssl["public"]); $tdy=date("Y-m-d"); $jbno=getJobNo();
				$json  = json_encode(["key"=>$keys,"ips"=>$_SERVER['REMOTE_ADDR'],"prkey"=>$prkey,"pbkey"=>$pbkey,"payroll"=>["include"=>0,"paye"=>0]],1);
				$fon = $db->query(1,"SELECT `contact` FROM `clients` WHERE `id`='$cid'")[0]["contact"];
				
				$cols = ["id"=>"NULL","name"=>"Android-App","contact"=>ltrim($fon,"254"),"email"=>$mail,"config"=>$json,"position"=>"USSD","access_level"=>"hq","entry_date"=>$tdy,
				"idno"=>$now,"jobno"=>$jbno,"gender"=>"app","time"=>$now];
				foreach($db->tableFields(2,"org$cid"."_staff") as $col){
					if(!isset($cols[$col])){ $cols[$col]=0; }
				}
				
				foreach($cols as $col=>$val){ $order[]="`$col`"; $data[]="'$val'"; }
				$db->execute(2,"INSERT INTO `org$cid"."_staff` (".implode(",",$order).") VALUES(".implode(",",$data).")");
			}
			
			$qry = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `idno`='$idno' AND `status`='0' AND NOT `idno`='0'");
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno' AND `status`<2");
			if($sql or $qry){
				$qri = $db->query(1,"SELECT `settings` FROM `config` WHERE `client`='$cid'");
				$logo = json_decode($qri[0]['settings'],1)["logo"]; $edata=($qry) ? $qry[0]['jobno']:$idno;
				echo json_encode(["response"=>200,"logo"=>getphoto($logo),"logoext"=>explode(".",$logo)[1],"src"=>dechex($idno)."/".encrypt($edata,$idno)],1);
			}
			else{ echo json_encode(["response"=>404],1); }
		}
		else{ echo json_encode(["response"=>404],1); }
	}

?>