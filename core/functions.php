<?php

	ini_set("error_log","errors.log");
	ini_set('mysql.connect_timeout', 600);
    ini_set('default_socket_timeout', 600);
	date_default_timezone_set("Africa/Nairobi");
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
	require "constants.php";
	require "kpis.inc"; 
	
	function updatesys(){
		$db = new DBO(); $cid=CLIENT_ID;
		$today = strtotime(date("Y-M-d")); $ldy = strtotime("Yesterday");
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs":$_SERVER['HTTP_HOST'];
		
		if($db->istable(2,"bi_analytics$cid")){
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='lastarrears'");
			$http = ($_SERVER['HTTP_HOST']=="localhost") ? "http://":"https://";
			$last = ($res) ? $res[0]['value']:0;
			if($last<$ldy){
				$http = ($_SERVER['HTTP_HOST']=="localhost") ? "http://":"https://";
				request($http.$url."/core/chron1200.php",["updateday"=>1]);
			}
		}
	}
	
	function mycountry(){
		$url = ($_SERVER["HTTP_HOST"]=="localhost") ? "http://ip-api.com/json":'http://ip-api.com/php/'.$_SERVER['REMOTE_ADDR'];
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'GET');
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$result = curl_exec($ch); 
		$error = curl_error($ch);
		curl_close($ch);
		if($error){ $res = []; }
		else{ $res = ($_SERVER["HTTP_HOST"]=="localhost") ? json_decode($result,1):@unserialize($result); }
		return $res;
	}
	
	function genToken($sid){
		$token = dechex(time()+100);
		insertSQLite("tempdata","CREATE TABLE IF NOT EXISTS tokens (temp TEXT UNIQUE NOT NULL,data TEXT)");
		insertSQLite("tempdata","REPLACE INTO `tokens` VALUES('$sid','$token')");
		return $token;
	}
	
	function setRegion($reg,$col="branch"){
		$db = new DBO(); $cid=CLIENT_ID; $def=[];
		$dcol = (count(explode(".",$col))>1) ? $col:"`$col`"; 
		if($reg){
			$sql = $db->query(1,"SELECT `branches` FROM `regions` WHERE `id`='$reg'");
			foreach(json_decode($sql[0]['branches'],1) as $bid){ $def[]="'$bid'"; }
		}
		return ($def) ? "$dcol IN (".implode(",",$def).")":"$dcol='0'";
	}
	
	function syshost(){
		$db = new DBO(); $cid=CLIENT_ID;
		$sql = $db->query(1,"SELECT `url` FROM `clients` WHERE `id`='$cid'");
		return ($sql) ? str_replace(["http://","https://"],"",$sql[0]['url']):$_SERVER["HTTP_HOST"];
	}
	
	function send_money($to,$pin,$sid,$api="b2c",$payb=0){
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]);
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs/pays":"pay.".implode(".",$http)."/b2c";
		$res = request("http://$url/request.php",["api"=>$api,"sendto"=>$to,"pin"=>$pin,"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"route"=>$payb]);
		return (is_numeric($res)) ? "Failed: Poor internet connection":$res;
	}
	
	function b2c_balance($pin,$sid,$payb=0){
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]);
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs/pays":"pay.".implode(".",$http)."/b2c";
		$res = request("http://$url/request.php",["api"=>"balance","pin"=>$pin,"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"route"=>$payb]);
		return (is_numeric($res)) ? "Failed: Poor internet connection":$res;
	}
	
	function c2b_reverse($code,$amnt,$pin,$sid,$payb=0){
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]);
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs/pays":"pay.".implode(".",$http)."/b2c";
		$res = request("http://$url/request.php",["api"=>"reversal","code"=>$code,"sum"=>$amnt,"pin"=>$pin,"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"route"=>$payb]);
		return (is_numeric($res)) ? "Failed: Poor internet connection":$res;
	}
	
	function check_trans($code,$pin,$sid,$trans="transid",$payb=0){
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]);
		$url = ($_SERVER['HTTP_HOST']=="localhost") ? "localhost/mfs/pays":"pay.".implode(".",$http)."/b2c";
		$res = request("http://$url/request.php",["api"=>"transref","trans"=>$code,"pin"=>$pin,"floc"=>syshost(),"sid"=>$sid,"reft"=>genToken($sid),"type"=>$trans,"route"=>$payb]);
		return (is_numeric($res)) ? "Failed: Poor internet connection":$res;
	}
	
	function getphoto($img,$tmp=null){
	    $loc = str_replace(array("\core","/core"),"",__DIR__);
		$tbl = ($tmp) ? "temps":"images";
		insertSQLite("photos","CREATE TABLE IF NOT EXISTS $tbl (image TEXT UNIQUE NOT NULL,data BLOB)");
		$res = fetchSQLite("photos","SELECT *FROM `$tbl` WHERE `image`='$img'");
		$pic = base64_encode(file_get_contents("$loc/docs/img/tempimg.png"));
		if(is_array($res)){ $pic = (isset($res[0]["data"])) ? $res[0]['data']:$pic; }
		if($tmp){
			$sql = fetchSQLite("photos","SELECT *FROM `images` WHERE `image`='$img'");
			$dimg = (is_array($sql)) ? $sql[0]['data']:$pic;
			return [$pic,$dimg];
		}
		else{ return $pic; }
	}
	
	function getAutokey($pbl=0){
		$db = new DBO(); $cid=CLIENT_ID;
		$qri = $db->query(1,"SELECT *FROM `bulksettings` WHERE `client`='$cid' AND `status`='0'");
		if(isset($qri[0]["defs"])){
			$def = json_decode($qri[0]["defs"],1);
			if(!$pbl){ $pbl = array_keys($def)[0]; }
			return (isset($def[$pbl]["auto"])) ? explode("/",$def[$pbl]["auto"]):null;
		}
		else{
			$qri = $db->query(1,"SELECT `value` FROM `settings` WHERE `setting`='autokey' AND `client`='$cid'");
			return ($qri) ? explode("/",$qri[0]["value"]):null;
		}
	}
	
	function getJobNo(){
		$db = new DBO(); $cid=CLIENT_ID;
		$com = strtoupper(substr(explode(" ",mficlient()['company'])[0],0,5));
		$qri = $db->query(2,"SELECT MIN(jobno) AS mjb FROM `org".$cid."_staff`");
		$mjb = ($qri) ? $qri[0]['mjb']:0; $com = ($mjb) ? preg_replace('/[^a-zA-Z]/', '', $mjb):$com;
		$res = $db->query(2,"SELECT MAX(CAST(REPLACE(REPLACE(jobno, '$com', ''), '', '') AS FLOAT)) AS mxjb FROM `org".$cid."_staff`");
		$jno = ($res) ? $res[0]['mxjb']:$com."000"; $jno=($jno) ? $com.$jno:$com."000";
		$nums = intval(preg_replace('/[^0-9]/', '', $jno)); $com = preg_replace('/[^a-zA-Z]/', '', $jno);
		return $com.prenum($nums+1);
	}
	
	function api_reply($resp_code,$data,$message){
		echo json_encode(["status"=>$resp_code,"response"=>$message,"data"=>$data],1);
	}
	
	function getPost(){
		$res = array_reverse(explode("/",ltrim($_SERVER['REQUEST_URI'],"/"))); @array_pop($res);
		return $res;
	}
	
	function genKeys(){
		$config = array(
			"digest_alg" => "sha512",
			"private_key_bits" => 4096,
			"private_key_type" => OPENSSL_KEYTYPE_RSA,
		);
		
		$res = openssl_pkey_new($config);
		openssl_pkey_export($res, $privKey);
		$pubKey = openssl_pkey_get_details($res)['key'];
		return ["private"=>$privKey,"public"=>$pubKey];
	}
	
	function apikeys(){
		for($i=0; $i<5; $i++){ $text[]=strtoupper(chr(rand(65,90)).chr(rand(65,90)).rand(0,9).chr(rand(65,90)).chr(rand(65,90))); }
		return implode("-",$text);
	}
	
	function api_send_txn($url,$enc,$encrypt=1){
	    $data = ($encrypt) ? json_encode(["enc_data"=>base64_encode($enc)],1):$enc;
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Content-Type: application/json; charset=utf-8'
		));
		
		$res	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		return ($error) ? $error:$res;
	}
	
	function ssl_decrypt($text,$key){
		$private = openssl_pkey_get_private($key,'');
		if($private){
			$encdata = base64_decode($text);
			openssl_private_decrypt($encdata, $data, $private, OPENSSL_PKCS1_OAEP_PADDING);
			openssl_free_key($private);
			return $data;
		}
		else{ return null; }
	}
	
	function genvalidxml($phones,$fname){
		foreach($phones as $no=>$fon){
			$no++;
			$vals[]="\r\t<!--Bulk Payment $no-->\r\t<Customer>\r\t\t<Identifier IdentifierType=\"MSISDN\" IdentifierValue=\"$fon\"></Identifier>\r\t\t<Amount Value=\"1\"></Amount>\r\t</Customer>"; 
		}	
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$data = "$xml\r<BulkPaymentValidationRequest>".implode("",$vals)."\r</BulkPaymentValidationRequest>";
		file_put_contents($fname,$data);
		return 1;
	}
	
	function fnum($val){
		$all = explode(".",$val);
		return (count($all)>1) ? number_format(intval($all[0])).".$all[1]":number_format(intval($all[0]));
	}
	
	function checktables(){
		$db = new DBO(); $cid=CLIENT_ID;
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='createtables'");
		$cond = ($res) ? $res[0]['value']:0; $created=[];
		
		if(!$cond){
			$qri = $db->query(1,"SELECT *FROM `default_tables`");
			$middle = array("schedule","payments","prepayments","targets","staff");
			foreach($qri as $row){
				$name = $row['name']; $tbl = (in_array($name,$middle)) ? "org".$cid."_".$name:"$name$cid"; $fields=json_decode($row['fields'],1);
				$dbs3 = array(13,17,18,19,22,23,24,26,27,28); $dbn=(in_array($row['id'],$dbs3)) ? 3:2;
				
				if(!$db->istable($dbn,$tbl)){
					$db->createTbl($dbn,$tbl,$fields); $created[]=array($dbn,$tbl);
				}
			}
			
			$cols = array("accounts$cid"=>array(["asset",0,0,4],["asset",0,0,6],["asset",0,0,3],["liability",0,0,2],["liability",0,0,2],["asset",2,1,3],
			["asset",2,1,2],["liability",4,1,0],["equity",0,0,0],["equity",0,0,0],["equity",0,0,0],["income",0,0,0],["income",0,0,0],["asset","2,6",2,0],
			["asset","2,6",2,0],["asset","2,7",2,0],["liability",4,1,0],["liability",5,1,0],["liability",5,1,0],["asset",2,1,1],["asset","2,20",2,0],
			["asset","2,7",2,0],["asset","2,6",2,0],["expense",0,0,1],["expense",24,1,1],["expense","24,25",2,0],["liability",4,1,0],["asset",1,1,0],
			["asset",1,1,0],["asset",1,1,0],["asset",1,1,0],["asset",3,1,0],["asset",3,1,0],["asset",3,1,0]),
			"accounting_rules$cid"=>array([15,16],[21,16],[22,17],[12,23]));
			
			$data = array(
				"accounts$cid"=>array("fixed assets","current assets","intangible assets","current liabilities","long-term liabilities","cash at hand",
				"cash at bank","income taxes payable","stated capital","capital surplus","retained earnings","interest income","finance charge income",
				"petty cash","employee advances","Mpesa Bulk Utility","loan overpayments","deffered income","Bank loans payable","loan portfolio",
				"loanbook","prepaid loans","accounts receivable","Administration Expenses","Transaction Cost","Mpesa bulk charges","Bad Debt Reserve",
				"Furniture & Fittings","Computer & Accessories","Office Equipments","Vehicles","Goodwill","Brand Recognition","intellectual property"),
				"accounting_rules$cid"=>array("salary advances","loan ledger","loan overpayments","loan interests")
			);
			
			foreach($created as $tds){
				$tbl=$tds[1]; $dbn=$tds[0];
				if(array_key_exists($tbl,$data)){
					foreach($data[$tbl] as $key=>$one){
						if($tbl=="accounts$cid"){
							$v1=$cols[$tbl][$key][0]; $v2=$cols[$tbl][$key][1]; $v3=$cols[$tbl][$key][2]; $v4=$cols[$tbl][$key][3];
							$vals="(NULL,'$one','$v1','$v2','$v3','$v4','0')"; 
						}
						else{ $vals = "(NULL,'$one','".$cols[$tbl][$key][0]."','".$cols[$tbl][$key][1]."')"; }
						
						$db->insert($dbn,"INSERT INTO `$tbl` VALUES $vals");
					}
				}
				if($tbl=="org".$cid."_staff"){
					$res = $db->query(1,"SELECT *FROM `clients` WHERE `id`='$cid'"); $row=$res[0]; $pos="super user";
					$email=$row['email']; $cont=ltrim($row['contact'],"254"); $tym=time(); $pass=encrypt(date("Md@Y",$tym),date("YMd-his",$tym)); $dy=date("Y-m-d"); 
					$cnf = json_encode(array("key"=>$pass,"region"=>0,"lastreset"=>0,"profile"=>"none","payroll"=>["include"=>0,"paye"=>0]),1); 
					
					$qri = $db->query(1,"SELECT COUNT(id) AS total FROM `useroles`"); $all=$qri[0]['total']; 
					for($n=1; $n<=$all; $n++){ $roles[]=$n; }
					$def = array("9,14,16,17,18,22,24,25,27,28,29,30,32,59","9,14,16,17,18,22,24,25,27,28,29,30,32,33,59,60,71",
					"9,14,16,17,18,22,24,25,27,28,29,30,32,33,59,60,71,39,40,41,42,43,44,45,46,47,48,49,50,61,62,84,85");
					
					$db->insert(2,"INSERT INTO `$tbl` VALUES(NULL,'admin','$cont','1238','$email','male','0','0','".implode(",",$roles)."','hq','$cnf','$pos','$dy','0','0','$tym')");
					foreach(array("Loan officer","Team leader","Accountant") as $key=>$grp){ $db->insert(1,"INSERT INTO `staff_groups` VALUES(NULL,'$cid','$grp','$def[$key]')"); }
				}
			}
		}
	}
	
	function getIncome($col,$val,$mon){
		$db = new DBO(); 
		$cid = CLIENT_ID; $incomes=[];
		$cols = array("interest","penalties","rollover_fees","offset_fees");
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
		if($res){
			foreach($res as $row){
				$terms = json_decode($row['payterms'],1); 
				foreach($terms as $pay=>$des){
					if(!in_array(explode(":",$des)[0],[7])){ $cols[$pay]=$pay; }
				}
			}
		}
		
		$cond = ($col) ? "AND `$col`='$val'":"";
		$sql = $db->query(2,"SELECT *FROM `paysummary$cid` WHERE `month`='$mon' $cond");
		if($sql){
			foreach($sql as $row){
				foreach($cols as $col){
					if(isset($row[$col])){
						if(isset($incomes[$col])){ $incomes[$col]+=$row[$col]; }
						else{ $incomes[$col]=$row[$col]; }
					}
				}
			}
		}
		
		return (count($incomes)) ? array_sum($incomes):0;
	}
	
	function setSurplus($mon){
		$db = new DBO(); $cid=CLIENT_ID; $tmon=strtotime(date("Y-M"));
		$chk = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='Surplus Account'");
		if($chk){
			$res = $db->query(3,"SELECT SUM(amount) AS tsum FROM `transactions$cid` WHERE `book`='expense' AND `month`='$mon'");
			$inc = getIncome("","",$mon); $exp=($res) ? $res[0]['tsum']:0; $yr=date("Y",$mon); $rem=$inc-$exp; $rid=$chk[0]['id'];
			
			$sql = $db->query(3,"SELECT SUM(amount) AS tsum,`type` FROM `transactions$cid` WHERE `account`='$rid' AND `month`='$mon' GROUP BY `type`");
			if($sql){
				foreach($sql as $row){
					$rem-=($row['type']=="debit") ? $row['tsum']:0; $rem+=($row['type']=="credit") ? $row['tsum']:0;
				}
			}
			if($mon<$tmon){ backdate($rid,$rem,$mon,["addto"]); }
			else{ balance_book($rid,$rem); }
		}
	}
	
	function initiateApproval($type,$def,$bid=0,$sid=0){
		$db = new DBO(); $cid=CLIENT_ID;
		$jsn = json_encode($def,1); $now=time();
		$host = ($_SERVER["HTTP_HOST"]=="localhost") ? "localhost":explode(".",$_SERVER["HTTP_HOST"])[1];
		$cond = ($bid) ? "AND `branch`='$bid'":""; $cond.=(!in_array($host,["zafrinasolutions"])) ? "AND `id`>1":""; 
		
		if(!$db->istable(2,"transtemps$cid")){ $db->createTbl(2,"transtemps$cid",["type"=>"CHAR","user"=>"INT","branch"=>"INT","details"=>"TEXT","status"=>"INT","time"=>"INT"]); }
		if($db->execute(2,"INSERT INTO `transtemps$cid` VALUES(NULL,'$type','$sid','$bid','$jsn','0','$now')")){
			$rid = $db->query(2,"SELECT `id` FROM `transtemps$cid` WHERE `user`='$sid' AND `time`='$now'")[0]["id"];
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `status`='0' $cond");
			if($sql){
				foreach($sql as $row){
					if($row["id"]!=$sid){
						$post = staffPost($row['id'],1); $av=0;
						if(is_array($post)){
							foreach($post as $gr=>$one){
								$perms = array_map("trim",getroles(explode(",",explode(":",$one)[1])));
								$av+= (in_array($def["perm"],$perms)) ? 1:0;
							}
						}
						
						if($av){
							$mssg = greet(explode(" ",ucwords($row['name']))[0]).", You have a pending ".$def["title"]." approval request. 
							Kindly Login to the system to Approve under My Account > Approval Requests"; $dto=json_encode([$row['contact']],1);
							$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','$mssg','$dto','similar','$now','$now')");
						}
					}
				}
			}
			
			return $rid;
		}
		else{ return 0; }
	}
	
	function loanApproval($amnt,$pos=null){
		foreach(LOAN_APPROVALS as $key=>$one){
			$range=explode("-",$key);
			if($amnt>=$range[0] && $amnt<=$range[1]){
				return ($pos) ? $one[$pos]:$one; break;
			}
		}
	}
	
	function mergeFix($fro,$to){
		$db = new DBO(); $cid=CLIENT_ID; 
		$user = staffInfo($fro); $usa=staffInfo($to); $cnf=json_decode($usa["config"],1); 
		$cnf["mypost"][$usa["position"]]=$usa["access_level"]; $cnf["mypost"][$user["position"]]=$user["access_level"]; $jsn=json_encode($cnf,1);
		$def2 = array("interactions$cid"=>"source","bi_analytics$cid"=>"staff","loanbooks$cid"=>"officer","mergedpayments$cid"=>"officer","org".$cid."_clients"=>"loan_officer",
		"org$cid"."_loans"=>"loan_officer","org$cid"."_loantemplates"=>"loan_officer","org$cid"."_prepayments"=>"officer","org$cid"."_schedule"=>"officer","org$cid"."_targets"=>"officer",
		"overpayments$cid"=>"officer","paysummary$cid"=>"officer","processed_payments$cid"=>"officer");
		$def3 = array("advances$cid"=>"staff","cashbook$cid"=>"cashier","deductions$cid"=>"staff","paid_salaries$cid"=>"staff","payroll$cid"=>"staff","payslips$cid"=>"staff",
		"requisitions$cid"=>"staff","transactions$cid"=>"user","translogs$cid"=>"user","utilities$cid"=>"staff");
		
		foreach($def2 as $tbl=>$col){
			if($db->istable(2,$tbl)){ $db->execute(2,"UPDATE `$tbl` SET `$col`='$to' WHERE `$col`='$fro'"); }
		}
		foreach($def3 as $tbl=>$col){
			if($db->istable(3,$tbl)){ $db->execute(3,"UPDATE `$tbl` SET `$col`='$to' WHERE `$col`='$fro'"); }
		}
		
		$db->execute(2,"UPDATE `org$cid"."_staff` SET `config`='$jsn' WHERE `id`='$to'");
		$db->execute(2,"UPDATE `org$cid"."_staff` SET `status`='15' WHERE `id`='$fro'");
	}
	
	function staffPost($uid,$perms=null){
		$db = new DBO(); $cid=CLIENT_ID;
		$sql = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$uid'");
		$row = $sql[0]; $pos=$row["position"]; $cnf=json_decode($row['config'],1);
		$arr = (isset($cnf["mypost"])) ? $cnf["mypost"]:[$pos=>$row['access_level']]; $data=[];
		if($perms){
			$qri = $db->query(1,"SELECT *FROM `staff_groups` WHERE `client`='$cid'");
			foreach($qri as $row){ $grups[$row['sgroup']]=$row["roles"]; }
			foreach($arr as $grp=>$acc){
				if($grp=="super user"){
					$chk = $db->query(1,"SELECT *FROM `useroles`");
					foreach($chk as $rw){ $roles[]=$rw['id']; }
					$data[$grp]="$acc:".implode(",",$roles);
				}
				elseif($grp=="collection agent"){ $data[$grp]="$acc:9,22,24,25,29,112"; }
				elseif($grp=="USSD"){ $data[$grp]="$acc:9"; }
				else{
					if(isset($grups[$grp])){ $data[$grp]="$acc:".$grups[$grp]; }
				}
			}
			return $data;
		}
		else{ return $arr; }
	}
	
	function fund_wallet($bid){
		$db = new DBO(); $cid=CLIENT_ID;
		$sql = $db->query(2,"SELECT *FROM `b2c_trials$cid` WHERE `id`='$bid'");
		if($sql){
			$row = $sql[0]; $amnt=$row["amount"]; $usa=$row["user"]; $now=time(); $qrys=[];
			$def = json_decode($row["source"],1); $src=$def["src"]; $rid=$def['id']; $err=$actp="";
			
			if($src=="utility"){
				$qri = $db->query(3,"SELECT *FROM `utilities$cid` WHERE `id`='$rid'");
				if(!$qri){ $err="Vendor payment cannot be traced"; }
				elseif($qri[0]["status"]>200){ $err = "Vendor payment is already settled"; }
				else{
					foreach(json_decode($qri[0]["item_description"],1) as $one){ $des[]=$one["item"]; }
					$desc = implode(", ",$des); $uid=$qri[0]["staff"]; $actp="staff"; $revs=array(["tbl"=>"utility","tid"=>$rid]);
					$qrys[] = array("db"=>3,"query"=>"UPDATE `utilities$cid` SET `status`='$now' WHERE `id`='$rid'");
				}
			}
			
			if($src=="advance"){
				$qri = $db->query(3,"SELECT *FROM `advances$cid` WHERE `id`='$rid'");
				if(!$qri){ $err="Salary advance record cannot be traced"; }
				elseif($qri[0]["status"]>200){ $err = "Salary advance is already paid"; }
				else{
					$desc = "Salary advance"; $uid=$qri[0]["staff"]; $actp="staff"; $revs=array(["tbl"=>"advance","tid"=>$rid]);
					$qrys[] = array("db"=>3,"query"=>"UPDATE `advances$cid` SET `status`='$now' WHERE `id`='$rid'"); $tsum=$qri[0]['approved'];
					$qrys[] = array("db"=>3,"query"=>"UPDATE `deductions$cid` SET `status`='$now' WHERE `staff`='$uid' AND `amount`='$tsum' AND `status`='200'");
				}
			}
			
			if($src=="loantemplate"){
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$rid'");
				if(!$qri){ $err="Client Loan record cannot be traced"; }
				elseif($qri[0]["status"]>200){ $err = "Client Loan is already disbursed"; }
				else{
					$idno = $qri[0]["client_idno"]; $pid=$qri[0]["loan_product"]; $actp="client"; $revs=array(["tbl"=>"loantemp","tid"=>$rid]);
					$uid = $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]["id"];
					$pname = $db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='$pid'")[0]["product"];
					$desc = (strpos($pname,"loan")!==false) ? ucwords($pname)." disbursement":ucwords($pname)." Loan disbursement"; 
				}
			}
			
			if($src=="staffloans"){
				$qri = $db->query(2,"SELECT *FROM `staff_loans$cid` WHERE `id`='$rid'");
				if(!$qri){ $err="Staff Loan record cannot be traced"; }
				elseif($qri[0]["status"]>200){ $err = "Staff Loan is already disbursed"; }
				else{
					$pid=$qri[0]["loan_product"]; $uid=$qri[0]["stid"]; $actp="staff"; $revs=array(["tbl"=>"staffloan","tid"=>$rid]);
					$pname = $db->query(1,"SELECT `product` FROM `loan_products` WHERE `id`='$pid'")[0]["product"];
					$desc = (strpos($pname,"loan")!==false) ? ucwords($pname)." disbursement":ucwords($pname)." Loan disbursement"; 
				}
			}
			
			if($err){ return array("error"=>$err); }
			elseif(!$actp){ return array("error"=>"Record cannot be traced"); }
			else{
				$tbl = ($actp=="staff") ? "org$cid"."_staff":"org$cid"."_clients";
				$name = ucwords($db->query(2,"SELECT `name` FROM `$tbl` WHERE `id`='$uid'")[0]["name"]);
				
				if($res=updateWallet($uid,$amnt,$actp,["desc"=>$desc,"revs"=>$revs],$usa,$now,1,8899)){
					$url = $db->query(1,"SELECT `url` FROM `clients` WHERE `id`='$cid'")[0]["url"];
					$db->execute(2,"UPDATE `b2c_trials$cid` SET `status`='200',`name`='$name' WHERE `id`='$bid'");
					foreach($qrys as $one){ $db->execute($one["db"],$one["query"]); }
					request("$url/mfs/dbsave/transpost.php",["postreq"=>genToken($usa),"srctp"=>$src,"reqid"=>$rid,"psum"=>$amnt,"tid"=>$res]);
				}
				else{ return array("error"=>"Failed to fund $name Transactional Account"); }
			}
		}
		else{ return array("error"=>"Funding record doesnt exist"); }
	}
	
	function walletAccs(){
		$db = new DBO(); $cid=CLIENT_ID;
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
		if($qri){ $sett = json_decode($qri[0]['value'],1); }
		else{
			$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='Clients Wallet'");
			if($sql){ $dca=$sql[0]['id']; }
			else{
				$db->execute(3,"INSERT INTO `accounts$cid` VALUES(NULL,'Clients Wallet','liability','4','1','0','0')");
				$dca = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='Clients Wallet'")[0]['id'];
				reserved_accounts($dca);
			}
			$sett["Investment"]=$sett["Transactional"]=$sett["Savings"]=$dca;
		}
		return $sett; 
	}
	
	function createWallet($src,$id){
		$db = new DBO(); $cid=CLIENT_ID;
		if(!$db->istable(3,"wallets$cid")){
			$db->createTbl(3,"wallets$cid",["client"=>"INT","type"=>"CHAR","investbal"=>"CHAR","savings"=>"CHAR","balance"=>"CHAR","def"=>"TEXT","status"=>"INT","time"=>"INT"]); 
		}
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN"); $now=time();
			$sql = $con->query("SELECT *FROM `wallets$cid` WHERE `client`='$id' AND `type`='$src' FOR UPDATE");
			if($sql->num_rows){ $wid = $sql->fetch_assoc()["id"]; }
			else{
				$con->query("INSERT INTO `wallets$cid` VALUES(NULL,'$id','$src','0','0','0','[]','0','$now')");
				$chk = $con->query("SELECT *FROM `wallets$cid` WHERE `client`='$id' AND `type`='$src' FOR UPDATE");
				$wid = $chk->fetch_assoc()["id"];
			}
			$con->commit(); $con->close();
			return $wid;
		}
		else{ return 0; }
	}
	
	function escrowallet($wid,$mval,$acc,$des,$sid,$mon){
		$db = new DBO(); $cid=CLIENT_ID;
		if(!$db->istable(1,"escrowallets")){
			$db->execute(1,"CREATE TABLE `escrowallets`(`wid` int(11) NOT NULL UNIQUE, `client` int(11) NOT NULL, `balance` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1"); 
		}
		
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN"); $cut=0;
			$sql = $con->query("SELECT *FROM `escrowallets` WHERE `client`='$cid' AND `wid`='$wid' FOR UPDATE");
			if($sql->num_rows>0){
				$row = $sql->fetch_assoc(); $rem=$row["balance"]; $bal=$rem+$mval; $cut=($bal>=0) ? $mval:$rem;
				$query = ($bal>0) ? "UPDATE `escrowallets` SET `balance`='$bal' WHERE `client`='$cid' AND `wid`='$wid'":"DELETE FROM `escrowallets` WHERE `client`='$cid' AND `wid`='$wid'";
				$con->query($query);
			}
			else{
				if($mval>0){ $con->query("INSERT INTO `escrowallets` VALUES('$wid','$cid','$mval')"); $cut=$mval; }
			}
			
			$con->commit(); $con->close();
			if($cut!=0){
				bookbal($acc,"$cut:$mon"); $tid=getransid(); $ttp=($mval>0) ? "debit":"credit";
				$sum = str_replace("-","",$cut); $day=strtotime(date("Y-M")); $tym=time(); $yr=date("Y");
				$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$tid','0','asset','$acc','$sum','$ttp','$des','WID$wid','','$sid','auto','$mon','$day','$tym','$yr')");
			}
		}
	}
	
	function updateWallet($uid,$amnt,$src,$desc,$sid,$now=0,$sms=0,$svm=0){
		$db = new DBO(); $cid=CLIENT_ID;
		$col = (count(explode(":",$amnt))>1) ? explode(":",$amnt)[1]:"balance"; $tym=($now>1) ? $now:time();
		$day = strtotime(date("Y-M-d",$tym)); $mon=strtotime(date("Y-M",$tym)); $yr=date("Y",$tym);
		$bks = array("balance"=>"Transactional","savings"=>"Savings","investbal"=>"Investment");
		$bk = $bks[$col]; $mval=explode(":",$amnt)[0]; $ttp=($mval>0) ? "debit":"credit"; $trtp=($mval>0) ? "credit":"debit";
		
		if(!$db->istable(3,"walletrans$cid")){
			$db->createTbl(3,"walletrans$cid",["tid"=>"CHAR","wallet"=>"INT","transaction"=>"CHAR","book"=>"CHAR","amount"=>"INT","details"=>"CHAR","afterbal"=>"CHAR",
			"approval"=>"INT","status"=>"INT","reversal"=>"LONGTEXT","time"=>"INT"]);
		}
		
		$wid = createWallet($src,$uid); $con=$db->mysqlcon(3);
		if($con && $wid){
			$con->autocommit(0); $con->query("BEGIN"); 
			$res = $con->query("SELECT * FROM `wallets$cid` WHERE `id`='$wid' FOR UPDATE");
			$row = $res->fetch_assoc(); $bal=roundnum($row[$col],2); $wdef=($row["def"]) ? json_decode($row["def"],1):[];
			$sum = str_replace("-","",$mval); $bal+=$mval; $sett=walletAccs(); $tid=$return=getransid(); $uad="";
			$des = (is_array($desc)) ? $desc["desc"]:$desc; $rev=(is_array($desc)) ? $desc["revs"]:[]; $nrv=$fee=0;
			
			if(isset($wdef["charges"]) && $col=="balance" && $mval>0){
				$chj = $wdef["charges"];
				if($chj>0){
					$cjr = ($mval>$chj) ? 0:$chj-$mval; $fee=$chj-$cjr; $uad=",`def`=JSON_SET(def,'$.charges','$cjr')";
				}
			}
			
			$con->query("UPDATE `wallets$cid` SET `$col`='".roundnum($bal,2)."'$uad WHERE `id`='$wid'");
			$con->commit(); $con->close(); $acc=$sett[$bk]; $notf=sys_constants("wallet_smsnots");
			if($fee>0){ saveTrans_fees($tid,"Overdraft Interest Charges Paid ($des)",$fee,$sid,"Overdraft Interest"); }
			
			if(isset($rev["tbl"])){ $nrv=($rev["tbl"]=="norev") ? 1:0; }
			if($svm){
				if($svm==8899){ escrowallet($wid,$mval,$sett["Withdrawals"],$des,$sid,$mon); $rev[]=["tbl"=>"trans","tid"=>$tid]; $return="$tid-$acc"; }
				else{
					bookbal($acc,"$mval:$mon"); if(!$nrv){ $rev[]=["tbl"=>"trans","tid"=>$tid]; }
					$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$tid','0','liability','$acc','$sum','$trtp','$des','WID$wid','','$sid','auto','$mon','$day','$tym','$yr')");
				}
			}
			
			if(!$nrv){ foreach($rev as $one){ $nrv+=($one["tbl"]=="norev") ? 1:0; }}
			$revs = (!$nrv && substr($des,0,24)!="Reimbursement Withdrawal") ? json_encode($rev,1):"norev";
			if(!in_array("reversal",$db->tableFields(3,"walletrans$cid"))){
				$db->execute(3,"ALTER TABLE `walletrans$cid` ADD `reversal` LONGTEXT NOT NULL AFTER `status`"); 
				$db->execute(3,"UPDATE `walletrans$cid` SET `reversal`='norev'");
			}
			
			$db->execute(3,"INSERT INTO `walletrans$cid` VALUES(NULL,'$tid','$wid','$ttp','$bk','$sum','$des','$bal','$sid','0','$revs','$tym')");
			if($mval<0){
				$wac = (isset($sett["Withdrawals"])) ? $sett["Withdrawals"]:0;
				if($wac){ escrowallet($wid,$mval,$wac,$des,$sid,$mon); }
			}
			
			if($sms && $notf && substr($des,0,16)!="Overpayment from"){
				$tbls = ["client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid"];
				$sql = $db->query(2,"SELECT `contact`,`name` FROM `$tbls[$src]` WHERE `id`='$uid'")[0];
				$fon = $sql["contact"]; $name=ucwords($sql["name"]); $wwt=($trtp=="credit") ? "Debited to your $bk Account from":"Credited from $bk Account for";
				$mssg = greet(explode(" ",$name)[0]).", Ksh ".fnum($sum)." has been $wwt $des. Acc balance is ".fnum($bal); $now=time();
				if($trtp=="credit" && $col=="investbal"){
					$mssg = greet(explode(" ",$name)[0]).", Your Investment account has been funded by Ksh ".fnum($sum)." from $des. Funds are available for Investment, Login to the App to Invest";
				}
				$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','$mssg','".json_encode([$fon],1)."','similar','$now','$now')");
			}
			
			return $return;
		}
		else{ return 0; }
	}
	
	function walletBal($wid,$col="balance"){
		$db = new DBO(); $cid=CLIENT_ID; $bal=0; $sett=[];
		if($col=="balance"){
			$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='wallet_accounts'");
			if($qri){ $sett = json_decode($qri[0]['value'],1); }
			else{
				$sql = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='Clients Wallet'");
				if($sql){ $sett["Transactional"]=$sql[0]['id']; }
			}
		}
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN");
			if($db->istable(3,"wallets$cid")){
				$sql = $con->query("SELECT `$col` FROM `wallets$cid` WHERE `id`='$wid'");
				if($sql->num_rows){
					$bal = roundnum($sql->fetch_assoc()[$col],2);
					if(isset($sett["Transactional"])){
						$acc = $sett["Transactional"]; $last=strtotime("2024-Jun");
						$qri = $con->query("SELECT *FROM `utilities$cid` WHERE `status`<=200 AND `time`>$last AND NOT `status`='15'");
						if($qri->num_rows){
							while($row=$qri->fetch_assoc()){
								$book = json_decode($row['recipient'],1)["book"];
								foreach($book as $key=>$amnt){
									$def = explode(":",$key);
									if(count($def)>1){
										if($wid==$def[1]){ $bal-=($row['status']==200) ? $row['approved']:$row['cost']; }
									}
								}
							}
						}
					}
				}
			}
			$con->commit(); $con->close(); 
		}
		return $bal;
	}
	
	function setCash($sid,$amnt,$desc){
		$db = new DBO(); $cid = CLIENT_ID;
		if(!$db->istable(3,"cashiers$cid")){ $db->createTbl(3,"cashiers$cid",["user"=>"INT","collections"=>"CHAR","balance"=>"CHAR"]); }
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN");
			$res = $con->query("SELECT * FROM `cashiers$cid` WHERE `user`='$sid' FOR UPDATE"); 
			$num = $res->num_rows; $row=($num) ? $res->fetch_assoc():["collections"=>0,"balance"=>0];
			$col = $row["collections"]; $col+=($amnt>0) ? $amnt:0; $bal=$row['balance']; $bal+=$amnt;
			$query = ($num) ? "UPDATE `cashiers$cid` SET `collections`='$col',`balance`='$bal' WHERE `user`='$sid'":"INSERT INTO `cashiers$cid` VALUES(NULL,'$sid','$col','$bal')";
			$con->query($query); $con->commit(); $con->close(); 
			$code = ($amnt>0) ? "CASH$sid-$amnt":"GCASH$sid-".str_replace("-","",$amnt);
			logtrans($code,$desc,$sid);
			return 1;
		}
		else{ return 0; }
	}
	
	function payFromWallet($uid,$amnt,$actp,$des,$sid=0,$pay=1,$sms=0){
		$db = new DBO(); $cid=CLIENT_ID; $tym=time(); $day=date("YmdHis"); 
		$desc = (is_array($des)) ? $des["desc"]:$des; $lid=(is_array($des)) ? $des["loan"]:0;
		$code="WALLET".uniqueId(); $mon=strtotime(date("Y-M")); $rev[]=["tbl"=>"pays","tid"=>$code];
		if($res=updateWallet($uid,"-$amnt:balance",$actp,["desc"=>"$desc #$code","revs"=>$rev],$sid,$tym,$sms)){
			$tbls = array("client"=>"org$cid"."_clients","staff"=>"org$cid"."_staff","investor"=>"investors$cid");
			$sql = $db->query(2,"SELECT *FROM `$tbls[$actp]` WHERE `id`='$uid'"); 
			$bid = $sql[0]['branch']; $name=ucwords($sql[0]['name']); $fon=$sql[0]['contact']; $payb=getpaybill($bid); $idno=$sql[0]['idno'];
			$db->execute(2,"INSERT IGNORE INTO `org$cid"."_payments` VALUES(NULL,'$mon','$code','$day','$amnt','$payb','$idno','0','$fon','$name','0')");
			$pid = $db->query(2,"SELECT `id` FROM `org$cid"."_payments` WHERE `code`='$code'")[0]['id'];
			logtrans($code,json_encode(["desc"=>$desc,"amount"=>$amnt,"user"=>"$actp:$uid"],1),$sid);
			if($pay){
				if(trim($desc)=="Penalties claim withdrawal"){
					$ltbl = ($actp=="client") ? "org$cid"."_loans":"staff_loans$cid";
					$cond = ($actp=="client") ? "AND `client_idno`='$idno'":"AND `stid`='$uid'";
					$sdr = $db->query(2,"SELECT `loan` FROM `$ltbl` WHERE `penalty`>0 $cond"); 
					if($sdr){
						$lid = $sdr[0]['loan'];
						$db->execute(2,"UPDATE `$ltbl` SET `penalty`=(penalty-$amnt) WHERE `loan`='$lid'");
						savepays($pid,$lid,array(["penalties"=>$amnt]),$actp); $tbal=array_sum(getLoanBals($lid,0,$actp));
						logtrans($lid,json_encode(array("desc"=>"Penalties charges paid","type"=>"credit","amount"=>$amnt,"bal"=>$tbal),1),0);
					}
				}
				else{
					$acc = ($actp=="staff") ? $uid:$idno;
					makepay($acc,$pid,$amnt,$actp,$lid); 
				}
			}
			return $code;
		}
		else{ return 0; }
	}
	
	function logtrans($ref,$des,$sid){
		$db = new DBO(); $cid=CLIENT_ID; 
		if(!$db->istable(3,"translogs$cid")){ $db->createTbl(3,"translogs$cid",["user"=>"INT","ref"=>"CHAR","details"=>"TEXT","time"=>"INT"]); }
		$desc = (isJson($des)) ? $des:clean($des); $tym=time();
		$db->execute(3,"INSERT INTO `translogs$cid` VALUES(NULL,'$sid','$ref','$desc','$tym')");
	}
	
	function adjustInt($pay,$sum){
		$db = new DBO(); $cid=CLIENT_ID;
		$int = (defined("INTRATE")) ? INTRATE:0;
		if($int && strtolower($pay)=="interest"){
			return ($sum>0) ? round($int*$sum/26):0;
		}
		else{ return $sum; }
	}
	
	function setInterest($lid){
		$db = new DBO(); $cid=CLIENT_ID;
		$tdy = strtotime(date("Y-M-d"));
		$src = (substr($lid,2,1)=="C") ? "client":"staff";
		$stbl = ($src=="client") ? "org".$cid."_schedule":"staff_schedule$cid"; 
		$ltbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid"; 
		
		if($db->istable(2,"daily_interests$cid")){
			if($con = $db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN"); $data=$adds=$asum=[];
				$sql = $con->query("SELECT *FROM `daily_interests$cid` WHERE `loan`='$lid'");
				if($sql->num_rows){
					$def = json_decode($sql->fetch_assoc()['schedule'],1); 
					$qri = $con->query("SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `balance`>0 FOR UPDATE");
					while($row = $qri->fetch_assoc()){
						$day=$row['day']; $rid=$row['id']; $intr=$row['interest']; $brk=json_decode($row['breakdown'],1); 
						$tot=($day<=$tdy && isset($brk["interest"])) ? $brk["interest"]:0; $sdef=json_decode($row["def"],1);
						if($day<=$tdy && isset($sdef["pays"])){
							foreach($sdef["pays"] as $py=>$sum){
								if(!isset($brk[$py])){ $brk[$py]=$sum; $adds[$py][$rid]=$sum; }
							}
						}
						
						if($day>$tdy && isset($def[$day])){
							foreach($def[$day] as $dy=>$sum){
								if($dy<=$day && $dy<=$tdy){ $tot+=$sum; }
								if($dy>=$tdy){ break; }
							}
						}
						
						if(isset($def[$day])){ $tot=(isset($def[$day]["set"])) ? $def[$day]["set"]:$tot; }
						if($intr<$tot){ $data[$rid]=$tot-$intr; }
						if($day>$tdy){ break; }
					}
					
					if($data or $adds){
						foreach($adds as $py=>$all){
							foreach($all as $rid=>$amnt){
								if(isset($asum[$py])){ $asum[$py]+=$amnt; }else{ $asum[$py]=$amnt; }
								$con->query("UPDATE `$stbl` SET `balance`=(balance+$amnt),`amount`=(amount+$amnt) WHERE `id`='$rid'"); 
							}
						}
						
						foreach($data as $rid=>$amnt){
							$con->query("UPDATE `$stbl` SET `interest`=(interest+$amnt),`balance`=(balance+$amnt),`amount`=(amount+$amnt) WHERE `id`='$rid'");
						}
						
						$chk = $con->query("SELECT *FROM `$ltbl` WHERE `loan`='$lid' FOR UPDATE"); 
						$sum = array_sum($data)+array_sum($asum); $roq=$chk->fetch_assoc(); $tbal=$roq["balance"]+$sum;
						$con->query("UPDATE `$ltbl` SET `balance`='$tbal' WHERE `loan`='$lid'"); $tbal+=$roq["penalty"];
					}
				}
				
				$con->commit(); $con->close(); 
				if($data){
					$tbal-= array_sum($asum);
					logtrans($lid,json_encode(array("desc"=>"Interest charges applied","type"=>"debit","amount"=>array_sum($data),"bal"=>$tbal),1),0); 
				}
				foreach($asum as $py=>$sum){
					logtrans($lid,json_encode(array("desc"=>"$py charges applied","type"=>"debit","amount"=>$sum,"bal"=>$tbal+$sum),1),0); 
				}
			}
		}
	}
	
	function daily_interest($lid,$prod){
		$db = new DBO(); $cid=CLIENT_ID;
		$tdy = strtotime(date("Y-M-d")); $days=[];
		$src = (substr($lid,2,1)=="S") ? "staff":"client";
		$stbl = ($src=="client") ? "org".$cid."_schedule":"staff_schedule$cid";
		
		$intv = $db->query(1,"SELECT `intervals` FROM `loan_products` WHERE `id`='$prod'")[0]['intervals'];
		$sql = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `balance`>0");
		foreach($sql as $row){
			$brk = json_decode($row["breakdown"],1);
			if(isset($brk["interest"])){
				$int = $brk["interest"]; $dy=$row['day']; $frm=$dy-(86400*$intv); $frm+=86400;
				$intr = ($int=="varying") ? $row["interest"]:$int; $per=ceil($intr/$intv); $sum=$intr;
				for($i=$frm; $i<=$dy; $i+=86400){
					$val=($sum>$per) ? $per:$sum;
					$days[$dy][$i]=$val; $sum-=$val;
				}
			}
		}
		return $days;
	}
	
	function getLoanBals($lid=0,$dy=0,$tbl="client"){
		$db = new DBO(); $cid=CLIENT_ID;
		$data = array("interest"=>0,"principal"=>0,"penalties"=>0);
		$stbl = ($tbl=="client") ? "org".$cid."_schedule":"staff_schedule$cid"; 
		$ltbl = ($tbl=="client") ? "org".$cid."_loans":"staff_loans$cid"; 
		$cond=$ftc=($lid) ? "AND `loan`='$lid'":""; $cond.=($dy) ? " AND `day`<=$dy":"";
		
		if($db->istable(2,$stbl)){
			if($con=$db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN");
				$qri = $con->query("SELECT SUM(penalty) AS tpen FROM `$ltbl` WHERE `penalty`>0 $ftc");
				$sql = $con->query("SELECT *FROM `$stbl` WHERE `balance`>0 $cond FOR UPDATE");
				if($sql){
					while($row=$sql->fetch_assoc()){
						$brek=json_decode($row['breakdown'],1); $pays=json_decode($row['payments'],1);
						foreach($brek as $pay=>$sum){
							$val=($pay=="interest") ? $row['interest']:$sum;
							foreach($pays as $one){ $val-=(isset($one[$pay])) ? $one[$pay]:0; }
							if(isset($data[$pay])){ $data[$pay]+=$val; }else{ $data[$pay]=$val; }
						}
					}
				}
				
				$data["penalties"]=($qri->num_rows) ? intval($qri->fetch_assoc()["tpen"]):0;
				$con->commit(); $con->close();
			}
		}
		return $data;
	}
	
	function swap_principal($lid){
		$db = new DBO(); $cid=CLIENT_ID;
		$src = (substr($lid,2,1)=="S") ? "staff":"client";
		$stbl = ($src=="client") ? "org".$cid."_schedule":"staff_schedule$cid";
		
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `interest`>0 FOR UPDATE");
			if($sql){
				while($row=$sql->fetch_assoc()){
					$brek=json_decode($row['breakdown'],1); $prc=$brek["principal"]; $rid=$row['id'];
					unset($brek["principal"]); $brek["principal"]=$prc; $jsn=json_encode($brek,1);
					$con->query("UPDATE `$stbl` SET `breakdown`='$jsn' WHERE `id`='$rid'");
				}
			}
			$con->commit(); $con->close();
		}
	}
	
	function uniqueId(){
		$db = new DBO(); $cid = CLIENT_ID; 
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0);
			$con->query("BEGIN");
			$res = $con->query("SELECT * FROM `settings` WHERE `client`='$cid' AND `setting`='uniqueid' FOR UPDATE"); 
			$rct = ($res->num_rows) ? $res->fetch_assoc()['value']:1; $nxt=$rct+1;
			$query = ($rct>1) ? "UPDATE `settings` SET `value`='$nxt' WHERE `client`='$cid' AND `setting`='uniqueid'":"INSERT INTO `settings` VALUES(NULL,'$cid','uniqueid','$nxt')";
			$con->query($query); $con->commit(); $con->close(); 
			return $rct;
		}
		else{ return rand(12345678,87654321); }
	}
	
	function doublepost($credit,$debit,$com,$tym,$reverse,$srn=0){
		$db = new DBO(); $cid=CLIENT_ID; $sno=($srn) ? $srn:getransid(); $all=array($credit,$debit); $res=0;
		$mon = strtotime(date("Y-M",$tym)); $day=strtotime(date("Y-M-d",$tym)); $yr=date("Y",$tym); $tym=time();
	
		for($i=0; $i<2; $i++){
			$tid=$all[$i][1]; $amnt=$all[$i][2]; $tp=($i) ? "debit":"credit"; 
			$sql = $db->query(3,"SELECT `type` FROM `accounts$cid` WHERE `id`='$tid'"); $bk=$sql[0]['type'];
			if(in_array($bk,["asset","expense"])){ $update = ($i) ? "+$amnt:$mon":"-$amnt:$mon"; }
			else{ $update = ($i) ? "-$amnt:$mon":"+$amnt:$mon"; }
			if(bookbal($tid,$update,$all[$i][0])){
				$v1=$all[$i][0]; $v2=$all[$i][3]; $v3=$all[$i][4]; $v4=$all[$i][5]; $res=1;
				$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$sno','$v1','$bk','$tid','$amnt','$tp','$v2','$v3','$com','$v4','$reverse','$mon','$day','$tym','$yr')");
			}
		}
		return $res;
	}
	
	function reversetrans($tid,$rev,$sid=0){
		$db = new DBO(); $cid=CLIENT_ID; $bks=[];
		$res = $db->query(3,"SELECT *FROM `transactions$cid` WHERE `transid`='$tid'");
		if($res){
			foreach($res as $row){
				$tp=$row['type']; $amnt=$row['amount']; $book=$row['book']; $bks[]=$book;
				$acc=$row['account']; $accs[$tp][$acc]=$amnt; $mon=$row['month']; 
				if(in_array($book,array("expense","asset"))){
					$upd = ($tp=="credit") ? "+$amnt":"-$amnt";
					if($acc==14){
						if(is_array($rev)){
							if($rev[0]['update']!="status:0"){ bookbal($acc,"$upd:$mon"); }
						}
						else{ bookbal($acc,"$upd:$mon"); }
					}
					else{ bookbal($acc,"$upd:$mon"); }
				}
				else{
					$upd = ($tp=="credit") ? "-$amnt:$mon":"+$amnt:$mon"; 
					bookbal($acc,$upd);
				}
			}
		}
		
		$jsn = json_encode(["status"=>1,"time"=>time(),"user"=>$sid,"accs"=>$accs],1);
		$db->execute(3,"UPDATE `transactions$cid` SET `reversal`='$jsn',`amount`='0' WHERE `transid`='$tid'");
		if(is_array($rev)){
			foreach($rev as $one){
				if(isset($one["update"])){
					$col=$one['col']; $val=$one['val']; $fld=explode(":",$one['update'])[0]; $upd=explode(":",$one['update'])[1];
					if($fld=="delete"){ $db->execute($one['db'],"DELETE FROM `".$one['tbl']."` WHERE `$col`='$val'"); }
					elseif($fld=="closingbal"){
						$tmon=strtotime(date("Y-M")); $update=(substr($upd,0,1)=="+") ? "-".substr($upd,1):"+".substr($upd,1);
						$res = $db->query(3,"SELECT *FROM `cashfloats$cid` WHERE `branch`='$val' AND `month`='$tmon'");
						if($res){ cashbal($res[0]['id'],$update); }
					}
					elseif($fld=="revpay"){
						if(reversepay($val,$col)){ $db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `code`='$col'"); }
					}
					else{
						$change = (in_array(substr($upd,0,1),array("+","-"))) ? "`$fld`=($fld$upd)":"`$fld`='$upd'";
						$db->execute($one['db'],"UPDATE `".$one['tbl']."` SET $change WHERE `$col`='$val'"); 
					}
				}
			}
		}
		
		if(in_array("expense",$bks)){ setSurplus($mon); }
		return 1;
	}
	
	function saveTrans_fees($trans,$desc,$sum,$sid,$acc="Transaction Charges Income"){
		$db = new DBO(); $cid=CLIENT_ID;
		$mon = strtotime(date("Y-M")); $day=strtotime(date("Y-M-d")); 
		$chk = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='$acc'");
		if(!$chk){ $fid = reserved_accounts(null,["name"=>$acc,"type"=>"income","wing"=>"0"]); }
		else{ $fid=$chk[0]['id']; } bookbal($fid,"+$sum"); $tid=getransid(); $yr=date("Y"); $tym=time();
		$db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$tid','0','income','$fid','$sum','credit','$desc','$trans','','$sid','auto','$mon','$day','$tym','$yr')");
	}
	
	function isAccount($str){
		$db = new DBO(); $cid=CLIENT_ID; $res=[];
		if(in_array(substr(trim($str),0,3),["T11","F12","SV3"])){
			$wid = substr(trim($str),3); $wds=array("T11"=>"balance","F12"=>"investbal","SV3"=>"savings");
			$wck = ($db->istable(3,"wallets$cid")) ? $db->query(3,"SELECT `client`,`type` FROM `wallets$cid` WHERE `id`='$wid'"):null;
			if($wck){ $res = ["wid"=>$wid,"type"=>$wds[substr(trim($str),0,3)],"client"=>$wck[0]["client"],"src"=>$wck[0]["type"]]; }
		}
		else{
			$acc = preg_replace('/\D/','',trim($str)); 
			if(strlen($acc)==11){
				$wds = array("01"=>"balance","02"=>"investbal","03"=>"savings");
				$p1 = intval(substr($acc,0,3)); $p2=substr($acc,3,2); $p3=intval(substr($acc,5));
				if(in_array($p2,["01","02","03"])){
					$brn = $db->query(1,"SELECT `branch` FROM `branches` WHERE `id`='$p1'");
					if($brn){
						$wck = ($db->istable(3,"wallets$cid")) ? $db->query(3,"SELECT `client`,`type` FROM `wallets$cid` WHERE `id`='$p3'"):null;
						if($wck){ $res = ["wid"=>$p3,"type"=>$wds[$p2],"client"=>$wck[0]["client"],"src"=>$wck[0]["type"]]; }
					}
				}
			}
		}
		
		return $res;
	}
	
	function reserved_accounts($acc=null,$add=[]){
		$db = new DBO(); $cid=CLIENT_ID;
		if($add){
			$chk = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `account`='".$add["name"]."'");
			if(!$chk){
				$name=$add["name"]; $atp=$add["type"]; $wing=$add["wing"]; $lv=(!$wing) ? 0:count(explode(",",$wing));
				$db->execute(3,"INSERT `accounts$cid` VALUES(NULL,'$name','$atp','$wing','$lv','0','0')");
				$acc = $db->query(3,"SELECT `id` FROM `accounts$cid` WHERE `account`='$name'")[0]['id'];
			}
			else{ $acc=$chk[0]['id']; }
		}
		
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN");
			$qri = $con->query("SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='reserved_accounts' FOR UPDATE");
			$num = $qri->num_rows; $sett=($num>0) ? json_decode($qri->fetch_assoc()["value"],1):[];
			if($acc){
				if(!in_array($acc,$sett)){
					$sett[]=$acc; $jsn=json_encode($sett,1);
					if($num){ $con->query("UPDATE `settings` SET `value`='$jsn' WHERE `setting`='reserved_accounts'"); }
					else{ $con->query("INSERT INTO `settings` VALUES(NULL,'$cid','reserved_accounts','$jsn')"); }
				}
			}
			
			$con->commit(); $con->close();
			return ($add) ? $acc:$sett;
		}
		else{ return []; }
	}
	
	function isJson($str){
		return (is_array(json_decode($str,1))) ? 1:0;
	}
	
	function loanlimit($idno,$src="mfs"){
		$db = new DBO(); $cid=CLIENT_ID;
		$prods=$loans=$found=$def=$order=$cycles=$nos=[];
		
		if($src=="app"){
			calc_mpoints($idno); $list=$sets=[]; $no=0;
			$sqr = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='application_status' OR `setting`='client_awarding' OR `setting`='app_limit_from_previous')");
			if($sqr){
				foreach($sqr as $row){ $sets[$row['setting']]=($row['setting']=="client_awarding") ? json_decode($row['value'],1):$row['value']; }
			}
			
			$lim = (isset($sets["app_limit_from_previous"])) ? $sets["app_limit_from_previous"]:0;
			$award = (isset($sets["application_status"])) ? $sets["application_status"]:0; $mxln=0; $pap=$prevs=[];
			$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='app' AND `status`='0' ORDER BY `maxamount` ASC");
			if($qri){ foreach($qri as $row){ $id=$row['id']; $prods[$id]=$row['maxamount']; $mins[$id]=$row['minamount']; $pap[]=$id; }}
			if($lim){
				$qry = $db->query(2,"SELECT `loan_product`,`amount`,`disbursement` FROM `org".$cid."_loans` WHERE `client_idno`='$idno'");
				if($qry){
					foreach($qry as $rw){
						$prevs[$rw['disbursement']]=$rw['amount'];
					}
					
					$mxln = ($prevs) ? $prevs[max(array_keys($prevs))]:0;
					if($mxln && defined("CAPP_LIMIT")){ $mxln=$mxln*CAPP_LIMIT; }
				}
			}
			
			$sql = $db->query(2,"SELECT `cdef` FROM `org$cid"."_clients` WHERE `idno`='$idno'");
			$cdef = json_decode($sql[0]['cdef'],1); $pnts=(isset($cdef["mpoints"])) ? $cdef["mpoints"]:1; 
			
			if($award){
				$sum = ($pnts>0) ? round($pnts*$sets["client_awarding"]["points_award"]):0; 
				foreach($prods as $pid=>$mx){
					$max = ($mxln>$mx) ? $mxln:$mx;
					if($max>=$sum && $mins[$pid]<=$sum){ $amnt=($sum>$max) ? $max:$sum; $amnt=($amnt<$mxln) ? $mxln:$amnt; $list[$pid]=$amnt; }
				}
				
				$val = ($mxln>$sum) ? $mxln:$sum;
				$mid = ($prods) ? array_keys($prods)[0]:1;
				$lims = ($list) ? $list:[$mid=>$val];
			}
			else{ $lims = ($prods) ? $prods:[1=>100]; }
			if(isset($cdef["maxset"])){ foreach($lims as $id=>$tot){ $lims[$id]=$cdef["maxset"]; }}
			return $lims;
		}
		else{
			$qri = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid' AND `category`='client' AND `status`='0' ORDER BY `maxamount` ASC");
			if($qri){ foreach($qri as $row){ $prods[$row['id']]=$row['maxamount']; }}
		
			$res = $db->query(1,"SELECT *FROM `loan_limits` WHERE `client`='$cid'");
			if($res){
				$setts = json_decode($res[0]['setting'],1);
				if($res[0]['status']==0){
					return $prods; exit(); 
				}
				
				$sql = $db->query(2,"SELECT loan_product,COUNT(id) AS total FROM `org".$cid."_loans` WHERE `client_idno`='$idno' GROUP BY loan_product");
				if($sql){
					foreach($sql as $row){
						if(isset($prods[$row['loan_product']])){ $found[$row['loan_product']]=$row['total']; }
					}
				}
				
				foreach($setts as $pid=>$one){
					$order[$pid]=$one['order']; $cycles[$pid]=$one['cycles'];
				}
				
				asort($order,SORT_NUMERIC); 
				foreach($order as $pid=>$pos){
					if($pos==1 or isset($found[$pid])){ $loans[]=$pid; }
				}
				
				foreach(array_unique($loans) as $pid){ 
					$pos = $order[$pid]; $sum=array_count_values($order)[$pos]; $no = (isset($found[$pid])) ? $found[$pid]:0;
					if($no>=$cycles[$pid]){
						if(in_array($pos+1,$order)){
							$nxt =array_search($pos+1,$order); $def[$nxt]=$prods[$nxt]; $num=array_count_values($order)[$pos+1];
							if($num>1){
								foreach($order as $key=>$val){
									if($val==$pos+1 && $nxt!=$key){ 
										$def[$key]=$prods[$key]; $pos2=$order[$key]; $num2=array_count_values($order)[$pos2];
										if($num2>1){
											foreach($order as $key2=>$val2){
												if($val2==$pos2 && $key!=$key2){ $def[$key2]=$prods[$key2]; }
											}
										}
									}
								}
							}
						}
					}
					
					if($sum>1){
						foreach($order as $key=>$val){
							if($val==$pos && $pid!=$key){
								if(isset($prods[$key])){ $def[$key]=$prods[$key]; }
							}
						}
					}
							
					if(!isset($def[$pid])){
						if(isset($prods[$pid])){ $def[$pid]=$prods[$pid]; }
					}
				}
				
				return $def;
			}
			else{ return $prods; }
		}
	}
	
	function calc_mpoints($idno){
		$db = new DBO(); $cid=CLIENT_ID;
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sqr = $db->query(1,"SELECT `value` FROM `settings` WHERE `client`='$cid' AND `setting`='client_awarding'");
			if($sqr){
				$sql = $con->query("SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno' FOR UPDATE");
				if($sql->num_rows){
					$roq = $sql->fetch_assoc(); $cdef=($roq["cdef"]) ? json_decode($roq['cdef'],1):[]; 
					$pnts = (isset($cdef["mpoints"])) ? $cdef["mpoints"]:0; $sett=json_decode($sqr[0]['value'],1);
					$last = (isset($cdef["lastcomp"])) ? $cdef["lastcomp"]:[]; $lrw=(isset($last["lastrow"])) ? $last["lastrow"]:0; 
					$lids = (isset($last["lids"])) ? $last["lids"]:[]; $tdy=strtotime(date("Y-M-d")); $tcl=0;
					
					$qri = $con->query("SELECT `loan`,`amount`,`status` FROM `org$cid"."_loans` WHERE `client_idno`='$idno'");
					while($rw=$qri->fetch_assoc()){
						$lid=$rw['loan']; $amnt=$rw['amount']; $no=$rm=0;
						if(!isset($lids[$lid])){
							$lnpt = ($sett["loanamt_point"]>0) ? round($amnt/$sett["loanamt_point"]):0;
							$cyc = ($rw["status"]) ? $sett["cycle_points"]:0; $tot=$cyc+$lnpt; $tcl+=1;
							$lids[$lid]=array("amount"=>$lnpt,"cycle"=>$cyc,"timely"=>0,"lately"=>0,"points"=>$tot); 
						}
						else{
							if($rw['status'] && $lids[$lid]["cycle"]==0){
								$lids[$lid]["cycle"]=$sett["cycle_points"]; $lids[$lid]["points"]+=$sett["cycle_points"]; $tcl++;
							}
						}
						
						$qry = $con->query("SELECT *FROM `org$cid"."_schedule` WHERE `loan`='$lid' AND `id`>$lrw AND (`balance`='0' OR `day`<$tdy) ORDER BY `day` ASC");
						if($qry->num_rows){
							while($row=$qry->fetch_assoc()){
								$day=$row['day']; $lrw=$row["id"]; $def=json_decode($row["payments"],1); $pays=[];
								foreach($def as $dy=>$one){ $pays[]=explode(":",$dy)[1]; }
								$mxv = (count($pays)) ? max($pays):time();
								if(($day+86399)>=$mxv){ $no++; }
								else{ $rm++; }
							}
							
							$tml=$no*$sett["timely_pay"]; $ltl=$rm*$sett["late_pay"]; $dif=($ltl>=0) ? $tml-$ltl:$tml+$ltl; $tcl+=1;
							$lids[$lid]["timely"]+=$tml; $lids[$lid]["lately"]+=$ltl; $lids[$lid]["points"]+=$dif;
						}
					}
					
					if($tcl>0){
						foreach(array("cycles","timely","amount","lately") as $one){ $cres[$one]=0; }
						$last["lastrow"]=$lrw; $last["lids"]=$lids; $cdef["mpoints"]=0;
						foreach($lids as $one){
							if(isset($one["amount"])){
								$cres["amount"]+=$one["amount"]; $cres["timely"]+=$one["timely"]; $cres["cycles"]+=$one["cycle"];
								$cres["lately"]+=$one["lately"]; $cdef["mpoints"]+=$one["points"];
							}
						}
						
						$cdef["lastcomp"]=$last; $cdef["compresult"]=$cres; $jsn=json_encode($cdef,1);
						$con->query("UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'");
					}
				}
			}
			$con->commit(); $con->close();
		}
	}
	
	function reset_points($idno){
		$db = new DBO(); $cid=CLIENT_ID;
		if($con = $db->mysqlcon(2)){
			$con->autocommit(0); $con->query("BEGIN");
			$sql = $con->query("SELECT *FROM `org$cid"."_clients` WHERE `idno`='$idno' FOR UPDATE");
			if($sql->num_rows){
				$roq = $sql->fetch_assoc(); $cdef=($roq["cdef"]) ? json_decode($roq['cdef'],1):[];
				unset($cdef["lastcomp"],$cdef["compresult"]); $cdef["mpoints"]=0; $jsn=json_encode($cdef,1);
				$con->query("UPDATE `org$cid"."_clients` SET `cdef`='$jsn' WHERE `idno`='$idno'");
			}
			$con->commit(); $con->close();
			calc_mpoints($idno);
		}
	}
	
	function setTarget($stid,$mon,$ref,$target=0,$pass=0){
		$db = new DBO(); $cid=CLIENT_ID; $tbl="targets$cid";
		if(!$db->istable(2,$tbl)){
			$db->createTbl(2,$tbl,["branch"=>"INT","officer"=>"INT","month"=>"INT","year"=>"INT","results"=>"TEXT","data"=>"TEXT","newloans"=>"INT","repeats"=>"INT","arrears"=>"INT"]); 
		}
		
		$refd = (is_array($ref)) ? $ref:[$ref]; $rets=[];
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='perfkpis'");
		$kpis = ($qry) ? json_decode($qry[0]["value"],1):KPI_LIST;
		
		foreach($refd as $dcol){
			$col = explode("-",$dcol)[0]; $cdf=(isset($kpis[$col]["target"])) ? $kpis[$col]["target"]:0;
			$cols = $db->tableFields(2,$tbl); $dtx=(in_array($col,["loanprods","mtd"])) ? "TINYTEXT":"INT";
			if(!in_array($col,$cols)){ $db->execute(2,"ALTER TABLE `$tbl` ADD `$col` $dtx NOT NULL"); }
			if(!$cdf && !$pass){ continue; }
			
			if($con = $db->mysqlcon(2)){
				$con->autocommit(0); $con->query("BEGIN"); 
				$sql = $con->query("SELECT *FROM `$tbl` WHERE `officer`='$stid' AND `month`='$mon' FOR UPDATE");
				if($sql->num_rows){
					$roq = $sql->fetch_assoc(); $id=$roq["id"]; $rest=json_decode($roq["results"],1); $targ=$target;
					if($target){
						if($col=="loanprods"){ $def=json_decode($roq[$col],1); $def[explode("-",$dcol)[1]]=$target; $targ=json_encode($def,1); }
						$con->query("UPDATE `$tbl` SET `$col`='$targ' WHERE `id`='$id'"); 
					}
					else{
						$dval = getTarget($stid,$mon,$dcol); $drc=json_decode($roq["data"],1);
						foreach($dval["recs"] as $dy=>$val){ $drc[$dy][$dcol]=$val; }
						if($col=="loanprods"){ $rest[$col][explode("-",$dcol)[1]]=$dval["value"]; }else{ $rest[$col]=$dval["value"]; }
						$rjs = json_encode($rest,1); $djs=json_encode($drc,1); $rets=$drc;
						$con->query("UPDATE `$tbl` SET `results`='$rjs',`data`='$djs' WHERE `id`='$id'");
					}
				}
				else{
					$res=$recs="[]"; $targ=$target;
					if(!$target){
						$dval = getTarget($stid,$mon,$dcol); $data=($col=="loanprods") ? [explode("-",$dcol)[1]=>$dval["value"]]:$dval["value"]; 
						foreach($dval["recs"] as $dy=>$val){ $drc[$dy][$dcol]=$val; }
						$res = json_encode([$col=>$data],1); $recs=json_encode($drc,1);
					}
					
					if($col=="loanprods"){ $targ = json_encode([explode("-",$dcol)[1]=>$target],1); }
					$def = array("id"=>"NULL","branch"=>staffInfo($stid)["branch"],"officer"=>$stid,"month"=>$mon,"year"=>date("Y",$mon),"results"=>$res,"data"=>$recs,$col=>$targ);
					foreach($cols as $dc){ $vl=($dc=="loanprods") ? "[]":0; $val=(isset($def[$dc])) ? $def[$dc]:$vl; $ords[]="`$dc`"; $ins[]="'$val'"; }
					$con->query("INSERT INTO `$tbl` (".implode(",",$ords).") VALUES (".implode(",",$ins).")"); $rets=json_decode($recs,1);
				}
				
				$con->commit(); $con->close();
			}
		}
		return $rets;
	}
	
	function getTarget($stid,$mon,$dcol){
		$db = new DBO(); $cid=CLIENT_ID;
		$dfc = explode("-",$dcol); $col=$dfc[0];
		$def = explode(":",KPI_LIST[$col]["def"]); 
		$range = monrange(date("m",$mon),date("Y",$mon)); $v1=$def[1];
		$tdy = strtotime("Today"); $mxd=($range[1]>$tdy) ? $tdy:$range[1]; $mto=$mxd+86399; $data=[];
		
		if($def[0]=="loanapp"){
			$list = array("new"=>"`loantype`='$v1'","repeat"=>"(`loantype` LIKE '$v1%' OR `loantype` LIKE 'topup%')","checkoff"=>"`checkoff`>0","disb"=>"`amount`>0");
			if(in_array($v1,["app","asset","prod"])){
				$fetch = ($v1=="prod" && isset($dfc[1])) ? "JSON_EXTRACT(`pdef`,'$.cluster')='".$dfc[1]."'":"`category`='$v1'";
				$chk = $db->query(1,"SELECT *FROM `loan_products` WHERE $fetch");
				if($chk){
					foreach($chk as $rw){ $prods[]="'".$rw["id"]."'"; }
					$cond = "`loan_product` IN (".implode(",",$prods).")";
				}
				else{ $cond = "`loan_product`='0'"; }
			}
			else{ $cond = $list[$v1]; }
			
			$sel = (in_array($v1,["new","repeat"])) ? "COUNT(id)":"SUM(amount)"; 
			$sel = ($v1=="checkoff") ? "SUM(checkoff)":$sel; $recs=[]; $tsum=0;
			for($i=$mon; $i<=$mxd; $i+=86400){
				$qri = $db->query(2,"SELECT $sel AS tot FROM `org$cid"."_loantemplates` WHERE `status` BETWEEN $i AND ".($i+86399)." AND `loan_officer`='$stid' AND $cond");
				$tot = ($qri) ? intval($qri[0]["tot"]):0; $recs[$i]=$tot; $tsum+=$tot;
			}
			$data = array("recs"=>$recs,"value"=>$tsum);
		}
		elseif($def[0]=="report"){
			if($v1=="income"){ $inc=getIncome("officer",$stid,$mon); $data=array("recs"=>[$mxd=>$inc],"value"=>$inc); }
			if($v1=="collections"){
				$sql = $db->query(2,"SELECT SUM(amount) AS tsum FROM `processed_payments$cid` WHERE `month`='$mon' AND `officer`='$stid'");
				$sum = ($sql) ? intval($sql[0]["tsum"]):0; $data=array("recs"=>[$mxd=>$sum],"value"=>$sum);
			}
		}
		elseif($def[0]=="cgroups"){
			if($v1=="new" && $db->istable(2,"cgroups$cid")){
				$tsum=0; $recs=[];
				for($i=$mon; $i<=$mxd; $i+=86400){
					$qri = $db->query(2,"SELECT COUNT(id) AS tot FROM `cgroups$cid` WHERE `time` BETWEEN $i AND ".($i+86399)." AND `loan_officer`='$stid'");
					$tot = ($qri) ? intval($qri[0]["tot"]):0; $recs[$i]=$tot; $tsum+=$tot;
				}
				$data = array("recs"=>$recs,"value"=>$tsum);
			}
			else{ $data = array("recs"=>[$mxd=>0],"value"=>0); }
		}
		elseif($def[0]=="loans"){
			if($v1=="arrears"){
				$sql = $db->query(2,"SELECT SUM(balance) AS tbal FROM `org$cid"."_schedule` WHERE `day`<$tdy AND `balance`>0 AND `officer`='$stid'");
				$sum = ($sql) ? intval($sql[0]["tbal"]):0; $data=array("recs"=>[$mxd=>$sum],"value"=>$sum);
			}
			elseif($v1=="perf"){
				$qr1 = $db->query(2,"SELECT DISTINCT `loan` FROM `org$cid"."_schedule` WHERE `officer`='$stid' AND `balance`>0");
				$qr2 = $db->query(2,"SELECT DISTINCT `loan` FROM `org$cid"."_schedule` WHERE `officer`='$stid' AND `balance`>0 AND `day`<$tdy");
				$all = ($qr1) ? count($qr1):0; $exc=($qr2) ? count($qr2):0; $data=array("recs"=>[$mxd=>$all-$exc],"value"=>$all-$exc);
			}
			elseif($v1=="book"){
				$sql = $db->query(2,"SELECT SUM(balance+penalty) AS tbal FROM `org$cid"."_loans` WHERE `loan_officer`='$stid' AND (balance+penalty)>0");
				$sum = ($sql) ? intval($sql[0]["tbal"]):0; $data=array("recs"=>[$mxd=>$sum],"value"=>$sum);
			}
			elseif($v1=="mtd"){
				$sql = $db->query(2,"SELECT SUM(paid+balance) AS tsum,SUM(paid) AS tpd FROM `org$cid"."_loans` WHERE `disbursement` BETWEEN $mon AND $mto AND `loan_officer`='$stid'");
				$sum = ($sql) ? intval($sql[0]["tsum"]):0; $paid=($sql) ? intval($sql[0]["tpd"]):0; $perc=($sum) ? roundnum($paid/$sum*100,2):0;
				$data = array("recs"=>[$mxd=>$perc],"value"=>$perc);
			}
		}
		
		return $data;
	}
	
	function recon_daily_pays($lid){
		$db = new DBO(); $cid=CLIENT_ID;
		$src = (substr($lid,2,1)=="S") ? "staff":"client";
		$tbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid";
		$sql = $db->query(2,"SELECT `loan_product` FROM `$tbl` WHERE `loan`='$lid'");
		if(!$db->istable(2,"daily_interests$cid")){ $db->createTbl(2,"daily_interests$cid",["loan"=>"CHAR","schedule"=>"TEXT","time"=>"INT"]); }
		$prod = $sql[0]['loan_product']; $scd=json_encode(daily_interest($lid,$prod),1);
		$qri = $db->query(2,"SELECT *FROM `daily_interests$cid` WHERE `loan`='$lid'");
		$query = ($qri) ? "UPDATE `daily_interests$cid` SET `schedule`='$scd' WHERE `loan`='$lid'":"INSERT INTO `daily_interests$cid` VALUES(NULL,'$lid','$scd','".time()."')";
		$db->execute(2,$query);
	}
	
	function reschedule($lid){
		$db = new DBO(); $cid=CLIENT_ID;
		$src = (substr($lid,2,1)=="S") ? "staff":"client";
		$tbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid";
		$tbl2 = ($src=="client") ? "org$cid"."_schedule":"staff_schedule$cid";
		
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='intrcollectype'");
		$qri = $db->query(2,"SELECT *FROM `$tbl` WHERE `loan`='$lid'");
		if($qri){
			$row = $qri[0]; $intrcol=($chk) ? $chk[0]['value']:"Accrued"; $idno=(isset($row['client_idno'])) ? $row['client_idno']:$row['stid'];
			$plan = loanschedule($lid,$row['disbursement'],$tbl); $ofid=$row['loan_officer']; $tbal=$row["balance"]; $paid=$row['paid']; 
			$slis=$skip=$pids=[]; $totpay=$tbal+$paid; $tpay=0;
			
			$sql = $db->query(1,"SELECT *FROM `loan_products` WHERE `client`='$cid'");
			if($sql){
				foreach($sql as $row){
					$pterms = json_decode($row["payterms"],1); $rid=$row['id'];
					foreach($pterms as $des=>$pay){
						if(in_array(explode(":",$pay)[0],[0,1,4,6])){ $skip[$des]=ucfirst($des); }
					}
				}
			}
			
			$qry = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `linked`='$lid'");
			if($qry){
				foreach($qry as $row){
					$py=str_replace(" ","_",$row['payment']); $pid=$row['payid']; $amt=$row['amount'];
					if(!in_array($py,$skip)){ reversepay($idno,$row["code"],$src,$lid); $paid-=$amt; $pids[$pid]=$amt; }
				}
			}
			
			if($db->execute(2,"DELETE FROM `$tbl2` WHERE `loan`='$lid'")){
				if($totpay>$plan["totpay"] && $paid>0){ $diff=$totpay-$plan["totpay"]; $paid-=$diff; }
				$pay = ($paid>0) ? $paid:0; $bal=$plan["totpay"]-$pay; $tpbal=$pay;
				foreach($plan["installment"] as $one){
					$dy=$one["day"]; $brek=$one["pays"]; $mon=strtotime(date("Y-M",$dy));
					$intr=($brek["interest"]=="varying") ? $one["nint"]:$brek["interest"]; 
					$intsv=($intrcol=="Accrued") ? $intr:0; $bjs=json_encode($brek,1); $svint=$one['install']; 
					$pdam=($pay>$svint) ? $svint:$pay; $pbl=$svint-$pdam; $pay-=$pdam; 
					$slis[] = "(NULL,'$idno','$lid','$ofid','$mon','$dy','$svint','$intsv','$pdam','$pbl','$bjs','[]')";
				}
				 
				$db->execute(2,"INSERT INTO `$tbl2` VALUES ".implode(",",$slis));
				$db->execute(2,"UPDATE `$tbl` SET `paid`='$tpbal',`balance`='$bal' WHERE `loan`='$lid'");
				foreach($pids as $pid=>$amnt){ makepay($idno,$pid,$amnt,$src); }
				recon_daily_pays($lid);
				return 1;
			}
		}
		else{ return 0; }
	}
	
	function reducingBal($amnt,$intr,$dur){
		$pint = round($intr/100,4);
		$anfv = round((1-pow((1+$pint),-$dur))/$pint,2);
		$inst = round($amnt/$anfv,2); $tsum=round($inst*$dur); 
		$tint = $tsum-$amnt; $pays=$closebal=[]; $tot=0;
		for($i=1; $i<=$dur; $i++){
			$rem=($i==1) ? $amnt:$closebal[$i]; $int=round($rem*$pint); $prc=round($inst-$int); $tot+=$prc;
			$closebal[$i+1]=($rem+$int)-$inst; $pays["principal"][$i]=$prc; $pays["interest"][$i]=$int; 
		}
		
		if(($amnt-$tot)!=0){
			$diff=$amnt-$tot; $per=($diff>0) ? ceil($diff/$dur):round($diff/$dur);
			foreach($pays["principal"] as $key=>$val){
				if($diff>0){ $prc=($diff>$per) ? $per:$diff; $pays["principal"][$key]=$prc+$val; $diff-=$prc; }
				else{ $prc=($diff<$per) ? $per:$diff; $pays["principal"][$key]=$prc+$val; $diff-=$prc; }
			}
		}
		
		return $pays;
	}
	
	function monsDiff($fro,$dto){
		$d1 = new DateTime($fro);
		$d2 = new DateTime($dto);
		$mns = $d2->diff($d1); 
		return (($mns->y)*12)+($mns->m); 
	}
	
	function roundnum($val,$pos=0){
		$val = ($pos) ? str_replace(",","",number_format($val,$pos)):round($val);
		if(count(explode(".",$val))>1){
			$val = (intval(explode(".",$val)[1])) ? explode(".",$val)[0].".".rtrim(explode(".",$val)[1],"0"):intval(explode(".",$val)[0]);
		}
		return $val;
	}
	
	function transInvPay($inv,$sum){
		$db = new DBO(); $cid=CLIENT_ID;
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN");
			$qri = $con->query("SELECT *FROM `investments$cid` WHERE `id`='$inv' FOR UPDATE");
			$row = $qri->fetch_assoc(); $desc=json_decode($row["details"],1); $tpy=(isset($desc["withdrawal"])) ? $desc["withdrawal"]:0; 
			$uid = substr($row["client"],1); $tp=substr($row["client"],0,1); $tps=array("I"=>"investor","C"=>"client","S"=>"staff"); 
			if($res=updateWallet($uid,$sum,$tps[$tp],["desc"=>$desc["pname"]." Investment Returns Payout","revs"=>["tbl"=>"norev"]],0,time(),1)){
				$tpy+=$sum; $desc["withdrawal"]=roundnum($tpy,2); $jsn=json_encode($desc,1);
				$con->query("UPDATE `investments$cid` SET `details`='$jsn' WHERE `id`='$inv'");
			}
			$con->commit(); $con->close();
		}
		else{ transInvPay($inv,$sum); }
	}
	
	function bookbal($book,$total,$bran=0){
		$db = new DBO(); $cid = CLIENT_ID; 
		$cmon = strtotime(date("Y-M")); $amnt=explode(":",$total)[0]; 
		$tmon = (count(explode(":",$total))>1) ? explode(":",$total)[1]:$cmon;
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN");
			if($tmon==$cmon){
				$sql = $con->query("SELECT * FROM `accounts$cid` WHERE `id`='$book' FOR UPDATE");
				$chk = $con->query("SELECT `id` FROM `monthly_balances$cid` WHERE `account`='$book'");
				if($chk->num_rows<1){ $con->query("INSERT INTO `monthly_balances$cid` VALUES(NULL,'$book','0','0','$cmon','".date("Y",$cmon)."')"); }
				$row = $sql->fetch_assoc(); $bal=intval($row['balance']); $bal+=intval(str_replace("+","",explode(":",$total)[0]));
				$con->query("UPDATE `monthly_balances$cid` SET `balance`='$bal' WHERE `account`='$book' AND `month`='$cmon'");
				$res = $con->query("UPDATE `accounts$cid` SET `balance`='$bal' WHERE `id`='$book'");
				$con->commit(); $con->close();
				return $res;
			}
			else{
				$res = $con->query("SELECT * FROM `monthly_balances$cid` WHERE `account`='$book' AND `month`='$tmon' FOR UPDATE");
				$pbal = ($res->num_rows) ? $res->fetch_assoc()["balance"]:0; $nbal=$pbal+intval(str_replace("+","",$amnt));
				$con->commit(); $con->close();
				return backdate($book,$nbal,$tmon,[$amnt,$bran]);
			}
		}
		else{ bookbal($book,$total,$bran); }
	}
	
	function backdate($book,$bal,$bmon,$def=[]){
		$db = new DBO(); $cid=CLIENT_ID;
		$actp = $db->query(3,"SELECT `type` FROM `accounts$cid` WHERE `id`='$book'")[0]['type']; 
		
		if($con = $db->mysqlcon(3)){
			$cmon = strtotime(date("Y-M")); 
			$tdto = (in_array($actp,["expense","income"]) && $book!=14) ? $bmon:$cmon;
			$mxto = monsDiff(date("Y-m-d",$bmon),date("Y-m-d",$tdto)); 
			
			for($i=0; $i<=$mxto; $i++){
				$con->autocommit(0); $con->query("BEGIN"); $crd=$dbt=0;
				$mon = strtotime("+$i month",$bmon); $nxtm=strtotime("+1 month",$mon); 
				if(!in_array($actp,["expense","income"])){ getBookBal($book,$mon); }
				
				if($i){
					$sql = $con->query("SELECT SUM(amount) AS tsum,`type` FROM `transactions$cid` WHERE `account`='$book' AND `month`='$mon' AND `amount`>0 GROUP BY `type`");
					while($row=$sql->fetch_assoc()){
						if($row['type']=="credit"){ $crd+=intval($row['tsum']); }
						if($row['type']=="debit"){ $dbt+=intval($row['tsum']); }
					}
					
					$sum = ($actp=="asset") ? intval($dbt-$crd):intval($crd-$dbt); $lmn=strtotime(date("Y-M-01",$mon-10)); $yr=date("Y",$mon);
    		        $qri = $con->query("SELECT *FROM `monthly_balances$cid` WHERE `account`='$book' AND `month`='$lmn' FOR UPDATE");
    		        $qry = $con->query("SELECT *FROM `monthly_balances$cid` WHERE `account`='$book' AND `month`='$mon' FOR UPDATE");
					$obal = ($qri->num_rows) ? $qri->fetch_assoc()["balance"]:0; $sum+=intval($obal);
					$save = ($qry->num_rows) ? "UPDATE `monthly_balances$cid` SET `openbal`='$obal',`balance`='$sum' WHERE `account`='$book' AND `month`='$mon'":
					"INSERT INTO `monthly_balances$cid` VALUES(NULL,'$book','$obal','$sum','$mon','$yr')"; $con->query($save);
    		        if($mon==$cmon){ $con->query("UPDATE `accounts$cid` SET `balance`='$sum' WHERE `id`='$book'"); }
				}
				else{
					$chk = $con->query("SELECT *FROM `monthly_balances$cid` WHERE `month`='$mon' AND `account`='$book' FOR UPDATE");
					if($chk->num_rows){ $con->query("UPDATE `monthly_balances$cid` SET `balance`='$bal' WHERE `account`='$book' AND `month`='$mon'"); }
					else{ $con->query("INSERT INTO `monthly_balances$cid` VALUES(NULL,'$book','0','$bal','$mon','".date("Y",$mon)."')"); }
				}
				
				if($book==14 && $def){
					$amnt = intval(str_replace("+","",$def[0])); $bran=$def[1]; $cbal=$amnt;
					$chk = $con->query("SELECT *FROM `cashfloats$cid` WHERE `month`='$mon' AND `branch`='$bran' FOR UPDATE");
					if(!$chk->num_rows){ $con->query("INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$mon','0','$amnt')"); }
					else{
						$roq = $chk->fetch_assoc(); $cbal=$roq['closing']+$amnt;
						$con->query("UPDATE `cashfloats$cid` SET `closing`='$cbal' WHERE `month`='$mon' AND `branch`='$bran'");
					}
					
					if($nxtm<=$cmon){
						$qri = $con->query("SELECT *FROM `cashfloats$cid` WHERE `month`='$nxtm' AND `branch`='$bran'");
						if($qri){ $con->query("UPDATE `cashfloats$cid` SET `opening`='$cbal' WHERE `month`='$nxtm' AND `branch`='$bran'"); }
						else{ $con->query("INSERT INTO `cashfloats$cid` VALUES(NULL,'$bran','$nxtm','$cbal','0')"); }
					}
				}
				$con->commit(); $con->autocommit(1);
			}
			$con->close(); return 1;
		}
		else{ backdate($book,$bal,$bmon,$def); }
	}
	
	function getBookBal($book,$mon=""){
		$db = new DBO(); $cid=CLIENT_ID;
		$tmon = strtotime(date("Y-M"));
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(0); $con->query("BEGIN"); $mon=($mon) ? $mon:$tmon;
			$res = $con->query("SELECT *FROM `monthly_balances$cid` WHERE `month`='$mon' AND `account`='$book' FOR UPDATE");
			if($res->num_rows){ $row=$res->fetch_assoc(); $bal=$row["balance"]; $open=$row['openbal']; }
			else{
				$pmon = strtotime("-1 month",$mon); 
				$qry = $con->query("SELECT `balance` FROM `monthly_balances$cid` WHERE `account`='$book' AND `month`='$pmon' FOR UPDATE");
				$open = ($qry->num_rows) ? $qry->fetch_assoc()["balance"]:0; $bal=0; $yr=date("Y",$mon);
				$con->query("INSERT INTO `monthly_balances$cid` VALUES(NULL,'$book','$open','0','$mon','$yr')");
			}
			
			if($mon==$tmon){
				$res = $con->query("SELECT * FROM `accounts$cid` WHERE `id`='$book' FOR UPDATE"); $bal=$res->fetch_assoc()["balance"];
				$con->query("UPDATE `monthly_balances$cid` SET `balance`='$bal' WHERE `account`='$book' AND `month`='$mon'");
			}
			
			$con->commit(); $con->close();
			return [$open,$bal];
		}
		else{ return []; }
	}
	
	function cashbal($id,$amnt){
		$db = new DBO(); $cid = CLIENT_ID; 
		$upd = (substr($amnt,0,1)=="+") ? "closing+".substr($amnt,1):"closing-".substr($amnt,1);
		$query = "UPDATE `cashfloats$cid` SET `closing`=($upd) WHERE `id`='$id'";
		
		if($con = $db->mysqlcon(3)){
			$con->autocommit(FALSE);
			$con->query("BEGIN");
			$con->query("SELECT * FROM `cashfloats$cid` WHERE `id`='$id' FOR UPDATE");
			$con->query($query);
			$con->commit(); $con->close(); 
		}
		else{
			insertSQLite("ftrans","CREATE TABLE IF NOT EXISTS fallback (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,db INTEGER,locks TEXT,query TEXT)");
			insertSQLite("ftrans","INSERT INTO `fallback` VALUES(NULL,'3','cashfloats$cid:id:$id','".base64_encode($query)."')");
		}
		return 1;
	}
	
	function standing_order($src,$mon,$type=null){
		$db = new DBO(); $cid=CLIENT_ID; 
		if(!$db->istable(1,"standing_orders")){
			$db->createTbl(1,"standing_orders",["client"=>"INT","source"=>"CHAR","type"=>"CHAR","amount"=>"CHAR","details"=>"TEXT","status"=>"INT","time"=>"INT"]);
		}
		
		$res = $db->query(2,"SELECT *FROM `org".$cid."_staff` WHERE `status`<2 ORDER BY `name` ASC");
		foreach($res as $row){
			if(!in_array($row["position"],["collection agent","assistant","USSD"])){
				$pay = json_decode($row['config'],1)['payroll'];
				if($pay['include']){
					$id=$row['id']; $name=prepare(ucwords($row['name'])); $cnd = ($id==$stid) ? "selected":"";
					if($stid){
						$opts.=($row['id']==$stid) ? "<option value='$id' $cnd>$name</option>":"";
					}
					else{ $opts.=($row['status']!=1) ? "<option value='$id' $cnd>$name</option>":""; }
				}
			}
		}
		
		$sql = $db->query(1,"SELECT *FROM `standing_orders` WHERE `client`='$cid' AND `source`='$src' AND (`month`='$mon' OR `month`='all') AND `status`='0'");
		if($sql){
			foreach($sql as $row){
				$tp=$row['type']; $amnt=$row['amount']; $def=json_decode($row['details'],1); 
				if($tp=="credit"){
					if($src=="payroll"){
						
					}
				}
				else{
					
				}
			}
		}
	}
	
	function getrule($rule){
		$db = new DBO(); $cid=CLIENT_ID;
		$def = array("interest"=>"loan interests","loans"=>"loan ledger","overpays"=>"loan overpayments","advances"=>"salary advances");
		$res = $db->query(3,"SELECT debit,credit FROM `accounting_rules$cid` WHERE `rule`='$def[$rule]'");
		return $res[0];
	}
	
	function loanbook($mon,$bran=0,$uid=0,$cum=0){
		$db = new DBO(); $cid=CLIENT_ID;
		$range = monrange(date("m",$mon),date("Y",$mon));
		$fro = $range[0]; $dto=$range[1]; $lids=[]; $dto+=86399; $tsum=$tcj=0;
		$cond = ($cum) ? "`disbursement`<=$dto":"`disbursement` BETWEEN $fro AND $dto";
		$ftc = ($cum) ? "`time`<=$dto":"`month`='$mon'";
		$cond.= ($bran) ? " AND `branch`='$bran'":"";
		$cond.= ($uid) ? " AND `loan_officer`='$uid'":"";
		
		$qri = $db->query(2,"SELECT `loan`,`loan_product`,`amount`,((paid+balance)-amount) AS tcj FROM `org$cid"."_loans` WHERE $cond");
		if($qri){
			foreach($qri as $row){ $lid=$row["loan"]; $prd=$row["loan_product"]; $tsum+=$row["amount"]; $tcj+=$row["tcj"]; $lids[]="'$lid'"; $prods[$prd]="'$prd'"; }
			$sql = $db->query(1,"SELECT `payterms` FROM `loan_products` WHERE `id` IN (".implode(",",$prods).")");
			foreach($sql as $row){
				foreach(json_decode($row["payterms"],1) as $pay=>$cut){
					if(in_array(explode(":",$cut)[0],[0,1,6,7])){ $skip[$pay]="'".ucfirst(str_replace("_"," ",$pay))."'"; }
				}
			}
			
			$skip[]="'Principal'"; $lns=implode(",",$lids); $nots=implode(",",$skip);
			$qr1 = $db->query(2,"SELECT SUM(amount) AS tsum FROM `processed_payments$cid` WHERE `payment`='Principal' AND `linked` IN ($lns) AND $ftc");
			$qr2 = $db->query(2,"SELECT SUM(amount) AS tsum FROM `processed_payments$cid` WHERE NOT `payment` IN ($nots) AND `linked` IN ($lns) AND $ftc");
			$prcs = $tsum-intval($qr1[0]["tsum"]); $recvs=$tcj-intval($qr2[0]["tsum"]);
			return ["book"=>$prcs,"receivable"=>$recvs];
		}
		else{ return ["book"=>0,"receivable"=>0]; }
	}
	
	function walletAccount($bid,$wid,$wtp="all"){
		$tps = array("balance"=>"01","investbal"=>"02","savings"=>"03");
		if($wtp=="all"){
			foreach($tps as $k=>$v){
				$part = intval($v."000000"); $part+=$wid; $all[$k]=prenum($bid)."0$part";
			}
			return $all;
		}
		else{
			$part = intval($tps[$wtp]."000000"); $part+=$wid;
			return prenum($bid)."0$part"; 
		}
	}
	
	function loanschedule($tid,$dy=0,$tbl="temps"){
		$db = new DBO(); $cid=CLIENT_ID;
		if($tbl=="temps"){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_loantemplates` WHERE `id`='$tid'");
			$row = $sql[0]; $pid=$row['loan_product']; $ldur=$row['duration']; $lamnt=$apam=$row["amount"]; $rst=$row["status"];
			$dsb = (isJson($row["disbursed"])) ? json_decode($row["disbursed"],1):[]; $lntp=$row["loantype"]; $idno=$row["client_idno"];
		}
		else{
			$sql = $db->query(2,"SELECT *FROM `$tbl` WHERE `loan`='$tid'");
			$row = $sql[0]; $pid=$row['loan_product']; $ldur=$row['duration']; $lamnt=$apam=$row["amount"]; 
			$idno = (isset($row['client_idno'])) ? $row["client_idno"]:""; $lntp="repeat"; $rst=0; $dsb=[];
		}
		
		$partial = sys_constants("partial_disbursement"); $istop=(count($dsb)>1 && explode(":",$lntp)[0]!="topup") ? 1:0; 
		if($partial && $rst>200){ $lamnt=$apam=($partial && isset($dsb[$rst])) ? $dsb[$rst]:$lamnt; }
		$lprod = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$pid'")[0];
		$intv=$lprod['intervals']; $cat=$lprod["category"]; $dur=$top=$topay=0; $days=$vsd=[];
		
		if(explode(":",$lntp)[0]=="topup" or $istop){
			$src = ($istop) ? $tid:explode(":",$lntp)[1]; 
			$dy = (!$dy && isset(explode(":",$lntp)[2])) ? explode(":",$lntp)[2]-(86400*$intv):$dy;
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_loans` WHERE `tid`='$src'");
			$qry = $db->query(2,"SELECT *FROM `org$cid"."_schedule` WHERE `loan`='".$qri[0]['loan']."' AND `balance`>0");
			foreach($qry as $rw){ $days[]=$rw["day"]; }
			$dy = (!$dy && $days) ? min($days)-(86400*$intv):$dy;
			$top = getLoanBals($qri[0]["loan"])["principal"];
			$dur = ($istop) ? 0:count($days); 
		}
		
		$chk = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting`='payloan_from_day' OR `setting`='intrcollectype')");
		if($chk){
			foreach($chk as $rw){ $setts[$rw['setting']]=prepare($rw["value"]); }
		}
		
		$intrcol = (isset($setts["intrcollectype"])) ? $setts["intrcollectype"]:"Accrued"; $lamnt+=$top;
		$pfrm = (isset($setts["payloan_from_day"])) ? round(86400*$setts["payloan_from_day"]):0;
		$pdef = json_decode($lprod['pdef'],1); $intrtp=(isset($pdef["intrtp"])) ? $pdef["intrtp"]:"FR";
		$pname = prepare(ucwords($lprod["product"])); $day=($dy) ? $dy:strtotime(date("Y-M-d")); $pname.=($days) ? " Topup":"";
		$waivd = (isset($pdef["repaywaiver"])) ? $pdef["repaywaiver"]:0; $pfrm=($waivd) ? round(86400*$waivd):$pfrm;
		if($cat=="asset"){
			$aid = explode(":",$lntp)[2]; 
			$chk = $db->query(2,"SELECT *FROM `finassets$cid` WHERE `id`='$aid'")[0]; $asname=prepare($chk["asset_name"]); $cost=$chk["asset_cost"];
			$qty = ceil($lamnt/$cost); $qt=($qty>1) ? "$qty ":""; $pname="$qt$asname ($pname)";
		}
		
		$payterms = json_decode($lprod['payterms'],1); $pays=$payat=$adds=$deds=[]; $ldur+=($dur*$intv);
		foreach($payterms as $des=>$pay){
			$val = explode(":",$pay);
			$amnt = (count(explode("%",$val[1]))>1) ? round($lamnt*explode("%",$val[1])[0]/100):$val[1];
			if($val[0]==2){ $pays[$des]=$amnt; }
			if($val[0]==3){ $payat[$val[2]]=[$des,$amnt]; }
			if($val[0]==5){ $adds[$des]=$amnt; }
			if($val[0]==7){ $deds[$des]=$amnt; }
		}
		
		$lamnt-= array_sum($deds);
		if(substr($lprod['interest'],0,4)=="pvar"){
			$info = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='".$lprod['interest']."'")[0];
			$vars = json_decode($info['value'],1); $max = max(array_keys($vars));
			$intrs = (count(explode("%",$vars[$max]))>1) ? round($lamnt*explode("%",$vars[$max])[0]/100):$vars[$max];
			foreach($vars as $key=>$perc){
				$dy = $day+($key*86400); $cyc=$ldur/$intv; $dy+=$pfrm;
				$vsd[$dy] = (count(explode("%",$perc))>1) ? round(($lamnt*explode("%",$perc)[0]/100)/$cyc):round($perc/$cyc);
			}
		}
		else{ $intrs = (count(explode("%",$lprod['interest']))>1) ? round($lamnt*explode("%",$lprod['interest'])[0]/100):$lprod['interest']; }
		
		if($intrtp=="FR"){
			$totpay = $lamnt+($ldur/$lprod['interest_duration']*$intrs); $inst=ceil($totpay/($ldur/$intv)); 
			$intr=round(($totpay-$lamnt)/($ldur/$intv)); $princ=$inst-$intr; 
			
			$vint = (count($vsd)) ? "varying":$intr; $tint=0; $slis=[]; $perin=$ldur/$intv; $pinst=ceil($lamnt/$perin);
			$arr = array('principal'=>$princ,"interest"=>$vint); $tprc=$lamnt; $tintr=$totpay-$lamnt; $other=array_sum($pays);
			for($i=1; $i<=$perin; $i++){
				foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); }
				$dy=$day+$pfrm+($i*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0;
				$prc = ($pinst>$tprc) ? $tprc:$pinst; $arr['principal']=$prc; $tprc-=$prc; $jps=$pays;
				if($intrcol=="Accrued"){ $totpay+=$other; $arr+=$pays; $jps=[]; }
				$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); $totpay+=$cut;
				if(is_numeric($vint)){ $intr = ($vint>$tintr or $i==$perin) ? $tintr:$vint; $brek["interest"]=$intr; $tintr-=$intr; }
				$inst = ($brek["interest"]=="varying") ? array_sum($brek)+$intr:array_sum($brek); 
				$sinst = ($inst>$totpay) ? $totpay:$inst; $totpay-=$sinst; $tint+=($intrcol=="Accrued") ? $intr:0;
				$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0;
				$dat = array("day"=>$dy,"install"=>$svint,"pays"=>$brek,"nint"=>$intr);
				if($jps){ $dat["ipays"]=$pays; }
				$slis[]=$dat; $topay+=$svint;
			}
		}
		else{
			$perin=$ldur/$intv; $pinst=ceil($lamnt/$perin); $tint=$totpay=$tpay=0; $slis=[]; 
			$arr = array('principal'=>$pinst,"interest"=>0); $other=array_sum($pays);
			$calc = reducingBal($lamnt,explode("%",$lprod['interest'])[0],$perin);
			for($i=1; $i<=$perin; $i++){
				foreach($adds as $py=>$sum){ $arr[$py]=round($sum/$perin); $totpay+=round($sum/$perin); }
				$dy=$day+$pfrm+($i*$intv*86400); $cut=(isset($payat[$i])) ? $payat[$i][1]:0;
				$arr['principal']=$calc["principal"][$i]; $totpay+=$cut; $jps=$pays;
				if($intrcol=="Accrued"){ $totpay+=$other; $arr+=$pays; $jps=[]; }
				$brek = ($cut) ? array($payat[$i][0]=>$cut)+$arr:$arr; $mon=strtotime(date("Y-M",$dy)); 
				$intr = $calc["interest"][$i]; $brek["interest"]=$intr; $inst=array_sum($brek);
				$sinst = $inst; $totpay+=$sinst; $tint+=($intrcol=="Accrued") ? $intr:0; 
				$svint = ($intrcol=="Accrued") ? $sinst:$sinst-$intr; $intsv=($intrcol=="Accrued") ? $intr:0;
				$dat = array("day"=>$dy,"install"=>$svint,"pays"=>$brek,"nint"=>$intr);
				if($jps){ $dat["ipays"]=$pays; }
				$slis[]=$dat; $topay+=$svint;
			}
		}
		
		return array("installment"=>$slis,"product"=>$pname,"idno"=>$idno,"loan"=>$apam,"topup"=>$top,"totpay"=>$topay,"deposit"=>array_sum($deds));
	}
	
	function balance_book($acc,$bal,$mon=""){
		$db = new DBO(); $cid=CLIENT_ID; $tbl="accounts$cid"; $col="id";
		if($mon && $mon<strtotime(date("Y-M"))){ $tbl="monthly_balances$cid"; $col="account"; }
		$res = $db->query(3,"SELECT balance FROM `$tbl` WHERE `$col`='$acc'");
		if($res){
			if($res[0]['balance']!=$bal){
				if($con = $db->mysqlcon(3)){
					$con->autocommit(0);
					$con->query("BEGIN");
					$con->query("SELECT * FROM `$tbl` WHERE `$col`='$acc' FOR UPDATE");
					$con->query("UPDATE `$tbl` SET `balance`='$bal' WHERE `$col`='$acc'");
					$con->commit(); $con->close();
				}
			}
		}
	}
	
	function mficlient($get=null){
		$db = new DBO();
		$row = null;
		if($con=$db->mysqlcon(1)){
			$sql = @mysqli_query($con,"SELECT *FROM `config` WHERE `client`='".CLIENT_ID."'");
			if($sql && $r=mysqli_fetch_assoc($sql)){ $row = $r; }
			mysqli_close($con);
		}
		else{
			try{
				$con = $db->pdocon(1);
				if($con && !($con instanceof Exception)){
					$stmt = $con->prepare("SELECT *FROM `config` WHERE `client`=?");
					$stmt->execute(array(CLIENT_ID));
					$r = $stmt->fetch(PDO::FETCH_ASSOC);
					if($r){ $row = $r; }
				}
				$con = null;
			} catch(Exception $e){ }
		}
		if(!$row || empty($row['settings'])){
			return array('company'=>'Setup','email'=>'','contact'=>'','address'=>'','logo'=>'none','apikey'=>'','appname'=>'','senderid'=>'','smds'=>'');
		}
		$res = json_decode(prepare($row['settings']),1);
		if(!is_array($res)){ $res = array('company'=>'Setup','email'=>'','contact'=>'','address'=>'','logo'=>'none','apikey'=>'','appname'=>'','senderid'=>'','smds'=>''); }
		if($get){
			$smds = isset($res["smds"]) ? $res["smds"] : "";
			return ($smds !== "") ? json_decode(decrypt($smds,$get),1) : array();
		}
		return $res;
	}
	
	function generatePass(){
		$special = array("@","!","#","%","&","*","?"); $pass = "";
		for($i=1; $i<4; $i++){
			$part1 = rand(65,90); $part2 = rand(97,122); $part3 = rand(1,100);
			$pass.=chr($part1).chr($part2).$part3;
		}
		return $pass.$special[rand(0,6)];
	}
	
	function checkpass($pass){
		$number = preg_match('@[0-9]@', $pass);
		$uppercase = preg_match('@[A-Z]@', $pass);
		$lowercase = preg_match('@[a-z]@', $pass);
		$specialChars = preg_match('@[^\w]@', $pass);
		
		return (strlen($pass)<8 || !$number || !$uppercase || !$lowercase || !$specialChars) ? 0:1;
	}
	
	function genreceipt(){
		$db = new DBO(); $cid = CLIENT_ID; 
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0);
			$con->query("BEGIN");
			$res = $con->query("SELECT * FROM `settings` WHERE `client`='$cid' AND `setting`='receipt' FOR UPDATE"); 
			$rct = ($res->num_rows) ? $res->fetch_assoc()['value']:1; $nxt=$rct+1;
			$query = ($rct>1) ? "UPDATE `settings` SET `value`='$nxt' WHERE `client`='$cid' AND `setting`='receipt'":
			"INSERT INTO `settings` VALUES(NULL,'$cid','receipt','$nxt')";
			$con->query($query); $con->commit(); $con->close(); 
			return $rct;
		}
		else{ return rand(12345678,87654321); }
	}
	
	function getLoanId($type="C"){
		$db = new DBO(); $cid=CLIENT_ID;
		$yr = substr(date("Y"),2); $yr+=($yr>46) ? 19:44;
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN");
			$res = $con->query("SELECT * FROM `settings` WHERE `client`='$cid' AND `setting`='loanid' FOR UPDATE"); 
			$rct = ($res->num_rows) ? $res->fetch_assoc()['value']:1; $nxt=$rct+1;
			$query = ($rct>1) ? "UPDATE `settings` SET `value`='$nxt' WHERE `setting`='loanid' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','loanid','$nxt')";
			$con->query($query); $con->commit(); $con->close(); 
			return chr($yr).chr(date("n")+64).$type.prenum($rct);
		}
		else{ return chr($yr).chr(date("n")+64).$type.date("dmHis"); }
	}
	
	function getGroupId($type="C"){
		$db = new DBO(); $cid=CLIENT_ID;
		$yr = substr(date("Y"),2); $yr+=($yr>46) ? 19:44;
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN");
			$res = $con->query("SELECT * FROM `settings` WHERE `client`='$cid' AND `setting`='groupid' FOR UPDATE"); 
			$rct = ($res->num_rows) ? $res->fetch_assoc()['value']:1; $nxt=$rct+1;
			$query = ($rct>1) ? "UPDATE `settings` SET `value`='$nxt' WHERE `setting`='groupid' AND `client`='$cid'":"INSERT INTO `settings` VALUES(NULL,'$cid','groupid','$nxt')";
			$con->query($query); $con->commit(); $con->close(); 
			return chr($yr).$type.prenum($rct);
		}
		else{ return chr($yr).$type.date("dmHis"); }
	}
	
	function getransid(){
		$db = new DBO(); $cid = CLIENT_ID; 
		$today = strtotime(date("Y-M-d"));
		if($con = $db->mysqlcon(1)){
			$con->autocommit(0); $con->query("BEGIN");
			$res = $con->query("SELECT * FROM `transcodes` WHERE `client`='$cid' FOR UPDATE"); 
			$num = $res->num_rows; $row=$res->fetch_assoc(); $day=($num) ? $row['day']:0;
			$code = ($today==$day) ? $row['code']:date("Ymd")."0001"; $nxt=$code+1;
			$query = ($num) ? "UPDATE `transcodes` SET `code`='$nxt',`day`='$today' WHERE `client`='$cid'":"INSERT INTO `transcodes` VALUES(NULL,'$cid','$today','$nxt')";
			$con->query($query); $con->commit(); $con->close(); 
			return $code;
		}
		else{ return date("Ymd").rand(5000,9999); }
	}
	
	function genstamp($day=0){
		$com = mficlient(); $date=($day) ? $day:date("M d Y");
		$name = strtoupper(prepare($com["company"]));
		$cont = $com["contact"]; $mail=$com["email"]; $addr=str_replace("~"," ",prepare($com["address"]));
		return "<div style='width:200px;border:3px solid #31309B;min-height:110px;padding:5px;color:#31309B;text-align:center'>
			<p style='margin:0px;font-size:13px'><b>$name</b></p>
			<p style='font-size:11px;font-weight:bold;margin-bottom:5px'>$addr</p>
			<p style='color:#ff4500;font-weight:bold;margin-bottom:7px;font-size:14px'>$date</p>
			<p style='font-size:13px;font-weight:bold;margin:0px'>&#9733; Call: +$cont &#9733;</p>
		</div>";
	}
	
	function chatref($chat,$sub){
		$arr = explode(" ",$sub); $part=substr($arr[0],0,1).substr($arr[1],0,1);
		return strtoupper($part.dechex(min($chat).max($chat).max($chat).min($chat)));
	}
	
	function saveoverpayment($idno,$pid,$amnt,$src="client"){
		if($amnt>0){
			$db = new DBO(); $cid=CLIENT_ID;
			$qri = $db->query(2,"SELECT *FROM `org$cid"."_payments` WHERE `id`='$pid'");
			$code = $qri[0]['code']; $rev[]=["tbl"=>"sysoverpay","tid"=>$code]; $tym=time(); 
			
			if($src=="client"){
				$res = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `idno`='$idno'");
				$ofid = $res[0]['loan_officer']; $bran=$res[0]['branch']; $uid=$res[0]['id'];
				if(sys_constants("overpayment_to_wallet")){
					$req = updateWallet($uid,$amnt,"client",["desc"=>"Overpayment from transaction #$code","revs"=>$rev],0);
					$tid = $db->query(3,"SELECT `id` FROM `walletrans$cid` WHERE `tid`='$req'")[0]['id'];
					logtrans("OVP-$code",json_encode(array("desc"=>"Overpayment $code","tid"=>$tid,"amount"=>$amnt),1),0);
				}
				else{
					$db->execute(2,"INSERT INTO `overpayments$cid` VALUES(NULL,'$idno','$ofid','$bran','$pid','$amnt','$tym','0')");
					bookbal(DEF_ACCS['overpayment'],"+$amnt");
				}
			}
			else{
				$sql = $db->query(2,"SELECT `id` FROM `org$cid"."_staff` WHERE `idno`='$idno'"); 
				$req = updateWallet($sql[0]['id'],$amnt,"staff",["desc"=>"Overpayment from transaction #$code","revs"=>$rev],0);
				$tid = $db->query(3,"SELECT `id` FROM `walletrans$cid` WHERE `tid`='$req'")[0]['id'];
				logtrans("OVP-$code",json_encode(array("desc"=>"Overpayment $code","tid"=>$tid,"amount"=>$amnt),1),0);
			}
		}
	}
	
	function sys_constants($src=null){
		$db = new DBO(); $cid=CLIENT_ID;
		$sql = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='system_constants'");
		$data = ($sql) ? json_decode($sql[0]['value'],1):[];
		if($src){ return (isset($data[$src])) ? $data[$src]:""; }
		else{ return $data; }
	}
	
	function groupSavings($gid){
		$db = new DBO(); $cid=CLIENT_ID; $bal=0;
		if($db->istable(3,"wallets$cid")){
			$sql = $db->query(2,"SELECT *FROM `org$cid"."_clients` WHERE `client_group`='$gid'");
			if($sql){
				foreach($sql as $row){
					$chk = $db->query(3,"SELECT `savings` FROM `wallets$cid` WHERE `type`='client' AND `client`='".$row['id']."'");
					$bal+= ($chk) ? $chk[0]["savings"]:0;
				}
			}
		}
		return $bal;
	}
	
	function groupState($gid=null){
		$db = new DBO(); $cid=CLIENT_ID;
		$gdef = groupSetting();
		
		if($gid){
			$sql = $db->query(2,"SELECT COUNT(id) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$gid' AND `status`='1'");
			$qri = $db->query(2,"SELECT `def`,`status` FROM `cgroups$cid` WHERE `id`='$gid'");
			$row = $qri[0]; $sta=$row["status"]; $def=json_decode($row["def"],1); 
			$cdf = (isset($def["sets"])) ? $def["sets"]:$gdef; 
			$min = (isset($cdf["minmembers"])) ? $cdf["minmembers"]:1;
			$act = ($sql) ? intval($sql[0]["tot"]):0; $state=($act>=$min) ? 1:0;
			if($sta!=$state){ $db->execute(2,"UPDATE `cgroups$cid` SET `status`='$state' WHERE `id`='$gid'"); }
		}
		else{
			$sql = $db->query(2,"SELECT *FROM `cgroups$cid` WHERE `status`<2");
			if($sql){
				foreach($sql as $row){
					$rid = $row['id']; $sta=$row["status"]; $def=json_decode($row["def"],1); 
					$cdf = (isset($def["sets"])) ? $def["sets"]:$gdef; $min=(isset($cdf["minmembers"])) ? $cdf["minmembers"]:1;
					$sql = $db->query(2,"SELECT COUNT(id) AS tot FROM `org$cid"."_clients` WHERE `client_group`='$rid' AND `status`='1'");
					$act = ($sql) ? intval($sql[0]["tot"]):0; $state=($act>=$min) ? 1:0; 
					if($sta!=$state){ $db->execute(2,"UPDATE `cgroups$cid` SET `status`='$state' WHERE `id`='$rid'"); }
				}
			}
		}
	}
	
	function reconpays($uid,$mon){
		$db = new DBO(); $cid=CLIENT_ID;
		$chk = $db->query(2,"SELECT COUNT(*) AS tot FROM `paysummary$cid` WHERE `month`='$mon' AND `officer`='$uid'"); $tot=intval($chk[0]["tot"]);
		if($tot>1){ $db->execute(2,"DELETE FROM `paysummary$cid` WHERE `month`='$mon' AND `officer`='$uid'"); $tot=0; }
		$qri = $db->query(2,"SELECT `payment`,`branch`,SUM(amount) AS tsum FROM `processed_payments$cid` WHERE `month`='$mon' AND `officer`='$uid' GROUP BY `payment`");
		if($qri){
			$cols = array("`id`","`paybill`","`branch`","`officer`","`month`","`year`");
			foreach($qri as $row){
				$pay=prepstr($row["payment"]); $sum=$row["tsum"]; $bid=$row["branch"];
				$upds[]="`$pay`='$sum'"; $ins[]="'$sum'"; $cols[]="`$pay`";
			}
			
			if($tot){ $db->execute(2,"UPDATE `paysummary$cid` SET ".implode(",",$upds)." WHERE `month`='$mon' AND `officer`='$uid'"); }
			else{
				$save = array_merge(["NULL","'".getpaybill($bid)."'","'$bid'","'$uid'","'$mon'","'".date("Y",$mon)."'"],$ins);
				$db->execute(2,"INSERT INTO `paysummary$cid` (".implode(",",$cols).") VALUES(".implode(",",$save).")");
			}
		}
	}
	
	function groupSetting($gid=null){
		$db = new DBO(); $cid=CLIENT_ID;
		$qri = $db->query(1,"SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='client_groupset'");
		$gdef = ($qri) ? json_decode($qri[0]["value"],1):[];
		
		if($gid){
			$qri = $db->query(2,"SELECT `def` FROM `cgroups$cid` WHERE `id`='$gid'");
			$def = json_decode($qri[0]["def"],1); $cdf=(isset($def["sets"])) ? $def["sets"]:$gdef; 
			return $cdf;
		}
		else{ return $gdef; }
	}
	
	function makepay($idno,$pid,$pay,$src="client",$lid=0,$tid=0,$sms=1){
		$db = new DBO(); $cid=CLIENT_ID; 
		$payterms = (is_array($pay)) ? $pay[0]:[];
		$amnt = (is_array($pay)) ? $pay[1]:$pay;
		$stbl = ($src=="client") ? "org".$cid."_schedule":"staff_schedule$cid"; 
		$ltbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid"; 
		$lcol = ($src=="client") ? "client_idno":"stid"; $code=$ckf=$cln=""; $result=0; 
		$tmtb = ($src=="client") ? "org$cid"."_loantemplates":"staff_loans$cid";
		if(isset($payterms["closeln"])){ unset($payterms["closeln"]); $cln=1; }
		
		foreach(array("processed_payments","payouts","overpayments") as $name){
			if(!$db->istable(2,$name.$cid)){
				$fields = json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='$name'")[0]['fields'],1);
				$db->createTbl(2,$name.$cid,$fields);
			}
		}
		
		if(explode(":",$pid)[0]=="checkoff"){
			$sql = $db->query(2,"SELECT *FROM `org".$cid."_loantemplates` WHERE `client_idno`='$idno' ORDER BY `time` DESC LIMIT 1");
			$fon = $sql[0]['phone']; $bran=$sql[0]['branch']; $name=strtoupper($sql[0]['client']); $cnd=explode(":",$pid); $ctm=$cnd[1];
			$mon = strtotime(date("Y-M",$ctm)); $dy=date("YmdHis",$ctm); $payb=getpaybill($bran); $code=(isset($cnd[2])) ? $cnd[2]:"CHECKOFF".$sql[0]['id'];
			$ckf = "(NULL,'$mon','$code','$dy','$amnt','$payb','$idno','0','$fon','$name','0')";
		}
		
		if($con = $db->mysqlcon(2)){
			$tmp = ($lid=="temp") ? 1:0;
			$cond = ($lid && !$tmp) ? "AND `loan`='$lid'":"";
			$con->autocommit(0); $con->query("BEGIN");
			$qri = $con->query("SELECT *FROM `$ltbl` WHERE `$lcol`='$idno' AND (balance+penalty)>0 $cond ORDER BY `time` ASC LIMIT 1 FOR UPDATE");
			if($qri->num_rows<1){ $con->commit(); $con->close(); return 0; exit(); }
			if($ckf){
				$db->execute(2,"INSERT INTO `org".$cid."_payments` VALUES $ckf");
				$pid = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$code' AND `date`='$dy'")[0]['id'];
			}
			
			$row=$qri->fetch_assoc(); $fon=$row['phone']; $bal=$row['balance']; $tamnt=$row['amount']; $lid=$row['loan']; $penalty=$row['penalty'];
			$pyd=($bal>$amnt) ? $amnt:$bal; $tbal=$bal-$pyd; $tpaid=$row['paid']+$pyd; $cname=$row[$src]; $bran=$row["branch"]; $tym=time(); 
		
			$res = $con->query("SELECT *FROM `org".$cid."_payments` WHERE `id`='$pid' FOR UPDATE"); 
			if($pid<1 or $res->num_rows<1){
				if($code){ $con->query("DELETE FROM `org".$cid."_payments` WHERE `code`='$code'"); }
				$con->commit(); $con->close(); 
				return 0; exit(); 
			}
			else{ $prow = $res->fetch_assoc(); $code=$prow["code"]; }
			
			$chk = $con->query("SELECT *FROM `$tmtb` WHERE `payment`='$code' AND NOT `id`='$tid'");
			$avs = $con->query("SELECT *FROM `processed_payments$cid` WHERE `code`='$code'"); $dy=$prow['date']; $payb=$prow["paybill"];
			$pdy = strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":")); 
			$sta = ($tbal<1) ? $pdy:0; $vars=[];
			if($prow['status'] or $avs->num_rows or ($chk->num_rows>0 && !$tmp)){
				$con->commit(); $con->close(); return 1; exit();
			}
			
			if($db->istable(2,"varying_interest$cid")){
				$sql = $db->query(2,"SELECT *FROM `varying_interest$cid` WHERE `loan`='$lid'");
				$vars = ($sql) ? json_decode($sql[0]['schedule'],1):[];
			}
			
			$result = $con->query("UPDATE `org".$cid."_payments` SET `status`='$lid' WHERE `id`='$pid'");
			$con->query("UPDATE `$ltbl` SET `paid`='$tpaid',`balance`='$tbal',`status`='$sta' WHERE `loan`='$lid'");
			$con->commit(); $con->close();
			
			if($result){
				$trem = $amnt-($bal+$penalty); $ovp=0;
				if($trem>=0){
					$ck = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$lcol`='$idno' AND (balance+penalty)>0");
					if(!$ck && $src=="client"){ $db->execute(2,"UPDATE `org".$cid."_clients` SET `status`='0' WHERE `idno`='$idno'"); }
					if($trem>0){ 
						$ptm=0; foreach($payterms as $arr){ $ptm+=(!isset($arr["penalties"])) ? array_sum($arr):0; }
						if(($trem-$ptm)>0){ $ovp=$trem-$ptm; saveoverpayment($idno,$pid,$ovp,$src); }
					}
				}
				
				if(($amnt-$bal)>0 && $penalty>0){
					$penal = ($trem>0) ? $penalty:$amnt-$bal; $payterms[]=array("penalties"=>$penal); $penalty-=$penal;
					$db->execute(2,"UPDATE `$ltbl` SET `penalty`=(penalty-$penal) WHERE `loan`='$lid'");
				}
				
				$pamnt=$pdamt=$amnt; $schedule=$rpays=$pscd=[]; 
				$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `balance`>0 ORDER BY `day` ASC");
				if($res){
					foreach($res as $row){
						if($pamnt<1){ break; }
						$rid=$row['id']; $hist=json_decode($row['payments'],1); $paid=$row['paid']; $history=$terms=[]; 
						foreach($hist as $one){
							foreach($one as $key=>$val){
								if(isset($history[$key])){ $history[$key]+=$val; }
								else{ $history[$key]=$val; }
							}
						}
						
						$diff = $paid-array_sum($history); $brek=json_decode($row['breakdown'],1); 
						$intr = (isset($brek["interest"])) ?  $brek["interest"]:0;
						if($row['interest']==0 && $intr>0){ unset($brek["interest"]); }
						if($row["interest"]>0 && $intr!=$row["interest"] && $intr!="varying"){ $brek["interest"]=$row['interest']; }
						foreach($brek as $key=>$def){
							if($def=="varying"){
								$mxd = max(array_keys($vars));
								if($mxd<=$pdy){ $amnt=$vars[$mxd]; $brek[$key]=$amnt; }
								else{
									foreach($vars as $dy=>$perc){
										if($pdy<=($dy+86399)){ $amnt=$perc; $brek[$key]=$amnt; break; }
									}
								}
							}
							else{ $amnt=$def; }
							
							$total = (isset($history[$key])) ? $history[$key]:0; $rem=$amnt-$total;
							if($diff>0 && $rem>0){
								$cut = ($rem>$diff) ? $diff:$rem; $rem-=$cut; $diff-=$cut;
							}
							$val = ($rem>$pamnt) ? $pamnt:$rem; $terms[$key]=$val; $pamnt-=$val; 
							if($pamnt<1){ break; }
						}
						
						$payterms[]=$terms; $hist["$pid:$pdy"]=$terms; $schedule[$rid]=$hist; $rpays[$rid]=$paid+array_sum($terms); $pscd[$rid]=$brek;
					}
				}
				
				foreach($schedule as $rid=>$one){
					$hist=json_encode($one,1); $paid=$rpays[$rid]; $psd=$pscd[$rid]; $intr=(isset($psd["interest"])) ? $psd['interest']:0;
					if(is_numeric($intr)){
						$tpay=array_sum($psd); $bjsn=json_encode($psd,1);
						$db->execute(2,"UPDATE `$stbl` SET `amount`='$tpay',`interest`='$intr',`paid`='$paid',`balance`=(amount-$paid),`breakdown`='$bjsn',`payments`='$hist' WHERE `id`='$rid'");
					}
					else{ $db->execute(2,"UPDATE `$stbl` SET `paid`='$paid',`balance`=(amount-$paid),`payments`='$hist' WHERE `id`='$rid'"); }
				}
				
				savepays($pid,$lid,$payterms,$src); $now=time();
				$sql = $db->query(2,"SELECT SUM(balance) AS tbl,SUM(paid) AS tpd FROM `$stbl` WHERE `loan`='$lid'"); 
				$tbal = intval($sql[0]['tbl']); $tpd=intval($sql[0]['tpd']); $state=($tbal>0) ? 0:$pdy; $result=$lid; 
				$add = ($state && $src=="client") ? ",`clientdes`=JSON_REMOVE(clientdes,'$.agent')":""; $diff=$tpaid-$tpd;
				$db->execute(2,"UPDATE `$ltbl` SET `balance`='$tbal',`paid`='$tpd',`status`='$state'$add WHERE `loan`='$lid'");
				
				$bam = $tbal+$pdamt+$penalty; $desc="paybill $payb. Ref $code"; 
				$desc = ($cln) ? "Loan Closure facility. Ref $code":$desc; $desc=(substr($code,0,6)=="WALLET") ? "Transactional Account. Ref $code":$desc;
				foreach($payterms as $one){
					if(isset($one["rollover_fees"])){ $desc="Rollover facility. Ref $code"; }
					if(isset($one["offset_fees"])){ $desc="Offset facility. Ref $code"; }
					foreach($one as $py=>$sum){
						$pay=ucwords(str_replace("_"," ",$py)); $bam-=$sum;
						logtrans($lid,json_encode(array("desc"=>"$pay paid from $desc","type"=>"credit","amount"=>$sum,"bal"=>$bam),1),0);
					}
				}
				
				if(sys_constants("payment_alert_sms") && $sms){
					$snto = (defined("PAYMENT_SMS_BRANS")) ? PAYMENT_SMS_BRANS:[]; $allow=1;
					if($snto){ $allow = (in_array($bran,$snto)) ? 1:0; }
					if(!in_array(substr($code,0,8),["WRITEOFF"]) && $allow){
						$btx = ($tbal+$penalty>0) ? "Your Loan Balance is Ksh ".fnum($tbal+$penalty):"You have now cleared your Loan";
						$mssg = greet(ucwords($cname)).", Your payment of Ksh ".fnum($pdamt)." has been received. $btx. Thank You";
						$db->execute(1,"INSERT INTO `sms_schedule` VALUES(NULL,'$cid','$mssg','".json_encode([$fon],1)."','similar','$now','$now')"); 
					}
				}
				
				if($ovp>0){
					if($tbal>0 or $tpaid<$tpd){
						if($src=="client"){ $db->execute(2,"DELETE FROM `overpayments$cid` WHERE `payid`='$pid'"); }
						else{ savings($idno,$pid,"rmv",0); }
						if($tbal==0 && ($tpd-$tpaid)<$ovp){ saveoverpayment($idno,$pid,$ovp-($tpd-$tpaid),$src); }
					}
					else{
						if($diff>0){ $db->execute(2,"UPDATE `overpayments$cid` SET `amount`=(amount+$diff) WHERE `payid`='$pid'"); }
					}
				}
				else{
					if(($tpaid-$tpd)>0 && $state>10){ saveoverpayment($idno,$pid,$tpaid-$tpd,$src); }
				}
			}
		}
		
		return $result;
	}
	
	function getpaybill($bid){
		$db = new DBO();
		return $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bid'")[0]['paybill'];
	}
	
	function protectDocs($user){
		$db = new DBO(); $cid=CLIENT_ID;
		$return = null;
		
		$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='docprotection' AND `client`='$cid'");
		$cond = ($res) ? $res[0]['value']:0;
		if($cond){
			$res = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='protectpass' AND `client`='$cid'");
			$pass = ($res) ? $res[0]['value']:"@contact";
			$arr = array("@contact","@email"); $return = array("password"=>prepare($pass));
			if(in_array($pass,$arr)){
				$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$user'");
				$val = ($pass=="@contact") ? "0".$qri[0]['contact']:$qri[0][str_replace("@","",$pass)];
				$return = array("password"=>prepare($val));
			}
		}
		return $return;
	}
	
	function getInstall($loan,$day){
		$db = new DBO(); $cid = CLIENT_ID;
		$res = $db->query(2,"SELECT *,GROUP_CONCAT(day separator ',') AS days FROM `org".$cid."_schedule` WHERE `loan`='$loan' GROUP BY loan ORDER BY day ASC");
		$days = explode(",",$res[0]['days']); sort($days); $no=array_search($day,$days)+1;
		return "$no/".count($days);
	}
	
	function savepays($pay,$loan,$desc,$src="client"){
		$db = new DBO(); $cid=CLIENT_ID;
		$ptbl = "org$cid"."_payments"; 
		$sid = (isset($_COOKIE['mssn'])) ? substr(hexdec($_COOKIE['mssn']),6):0;
		$tym = (count(explode(":",$pay))>1) ? explode(":",$pay)[1]:0;
		$pid = explode(":",$pay)[0]; 
		
		foreach(array("processed_payments","payouts","overpayments") as $name){
			if(!$db->istable(2,$name.$cid)){
				$fields = json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='$name'")[0]['fields'],1);
				$db->createTbl(2,$name.$cid,$fields);
			}
		}
		
		$ltbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid";
		$sql = $db->query(2,"SELECT *FROM `$ltbl` WHERE `loan`='$loan'");
		$chk = $db->query(2,"SELECT *FROM `$ptbl` WHERE `id`='$pid'");
		if(!$sql or !$chk){ return 0; exit(); }
		if($src=="client"){
			$row=$sql[0]; $fon=$row['phone']; $idno=$row['client_idno']; $prod=$row['loan_product']; $cname=$row['client']; 
			$bid=$row['branch']; $des=json_decode($row['clientdes'],1); $offid=(isset($des["agent"])) ? $des['agent']:$row['loan_officer'];
		}
		else{ $row=$sql[0]; $fon=$row['phone']; $idno=$row['stid']; $prod=$row['loan_product']; $cname=$row['staff']; $bid=$row['branch']; $offid=$idno; }
		
		$exclude = array("interest","penalties");
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
		$arr = ($res) ? json_decode($res[0]['payterms'],1):[];  
		foreach($arr as $key=>$term){
			if(explode(":",$term)[0]!=2){ $exclude[]=$key; }
		}
		
		$row=$chk[0]; $code=$row['code']; $paybill=$row['paybill']; $dy=$row['date']; $tamnt=$row['amount']; $from=$row['client']; $pst=$row['status'];
		$ptm=($tym) ? $tym:strtotime(substr($dy,0,4)."-".rtrim(chunk_split(substr($dy,4,4),2,"-"),"-").", ".rtrim(chunk_split(substr($dy,8,6),2,":"),":"));
		$day=strtotime(date("Y-M-d",$ptm)); $mon=strtotime(date("Y-M",$ptm)); 
		$rc = genreceipt(); $data=[]; $intr=$prcs=$total=$penal=0;
		
		foreach($desc as $one){
			foreach($one as $pay=>$amnt){
				if($amnt>0){
					$pdes = ucfirst(str_replace("_"," ",$pay)); $total+=$amnt;
					$prcs+=(!in_array($pay,$exclude)) ? $amnt:0; 
					$intr+=($pdes=="Interest") ? $amnt:0; $penal+=($pdes=="Penalties") ? $amnt:0; $prtb="processed_payments$cid";
					$db->insert(2,"INSERT INTO `$prtb` VALUES(NULL,'$cname','$idno','$pdes','$pid','$code','$bid','$amnt','$offid','$sid','$rc','$loan','$mon','$day','$ptm','0')");
					if(isset($data[$pay])){ $data[$pay]+=$amnt; }
					else{ $data[$pay]=$amnt; }
				}
			}
		}
		
		$save = $data; $pays=""; 
		if($pst=="0"){ $db->insert(2,"UPDATE `$ptbl` SET `status`='$loan' WHERE `id`='$pid'"); }
		if(substr($code,0,8)=="CHECKOFF"){ $save["checkoff"]=$tamnt; }
		updatepays('add',$mon,$paybill,$offid,$save,[$code,time(),$bid,"Payment from ".ucwords($cname)." KES $tamnt, Ref $code"]); 
		foreach($data as $pay=>$amnt){
			$pays.=ucwords(str_replace("_"," ",$pay))." ($amnt),";
		}
		
		if($src=="client"){ setTarget($offid,$mon,["arrears","performing","loanbook","income","mtd","collections"]); calc_mpoints($idno); }
		if($intr>0 or $prcs>0){ setLoanBook(); }
		if($intr){ bookbal(getrule("interest")["debit"],"+$intr:$mon"); }
		if($penal){
			$db->execute(2,"UPDATE `penalties$cid` SET `paid`=(paid+$penal) WHERE `loan`='$loan'");
			bookbal(DEF_ACCS['loan_charges'],"+$penal:$mon");
		}
		
		savelog($sid,"Confirmed KES $tamnt from $from to ".rtrim($pays,","));
		return 1;
	}
	
	function setLoanBook(){
		$cdt=getLoanBals(0,0,"client"); $sdt=getLoanBals(0,0,"staff");
		balance_book(DEF_ACCS['loanbook'],$cdt["principal"]+$sdt["principal"]); 
		balance_book(DEF_ACCS['interest'],$cdt["interest"]+$sdt["interest"]);
	}
	
	function updatepays($type,$mon,$payb,$ofid,$pays,$trans){
		$db = new DBO(); $cid=CLIENT_ID;
		$stbl = "paysummary$cid"; 
		
		$qry = $db->query(1,"SELECT *FROM `settings` WHERE `setting`='recordpays' AND `client`='$cid'");
		$rec = ($qry) ? json_decode($qry[0]['value'],1):[];
		
		if(!$db->istable(2,$stbl)){
			$fields = json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='paysummary'")[0]['fields'],1);
			$db->createTbl(2,$stbl,$fields);
		}
		
		$tf1 = $db->tableFields(2,$stbl); $diff = array_diff(array_keys($pays),$tf1);
		if(!in_array("branch",$tf1)){ array_push($diff,"branch"); }
		foreach($diff as $field){
			if(!is_numeric($field)){
				$add = ($field=="branch") ? "AFTER `paybill`":"";
				$db->insert(2,"ALTER TABLE `$stbl` ADD `$field` INT NOT NULL $add");
			}
		}
		
		$yr=date("Y",$mon); $qry=""; $qris=[];
		$bid = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `id`='$ofid'")[0]['branch'];
		$qri= "'$payb','$bid','$ofid','$mon','$yr',"; $cols="`paybill`,`branch`,`officer`,`month`,`year`,";
		foreach($pays as $field=>$pay){
			$qry.=($type=="add") ? "`$field`=($field+$pay),":"`$field`=($field-$pay),"; 
			$qri.="'$pay',"; $cols.="`$field`,";
			if(in_array($field,$rec)){
				$upd = ($type=="add") ? "(balance+$pay)":"(balance-$pay)"; 
				$sid = (isset($_COOKIE['mssn'])) ? substr(hexdec($_COOKIE['mssn']),6):0;
				$acc = array_flip($rec)[$field]; $bid=$trans[2]; $des=$trans[3]; $code=$trans[0]; $tym=$trans[1]; $day=strtotime(date("Y-M-d",$tym));
				$sq = $db->query(3,"SELECT *FROM `accounts$cid` WHERE `id`='$acc'");
				$bk = $sq[0]['type']; $ptp = (in_array($bk,array("asset","expense"))) ? "debit":"credit";
				$qris[]="UPDATE `accounts$cid` SET `balance`=$upd WHERE `id`='$acc'";
				$qris[]=($type=="add") ? "INSERT INTO `transactions$cid` VALUES(NULL,'".getransid()."','$bid','$bk','$acc','$pay','$ptp','$des','$code',
				'','$sid','auto','$mon','$day','$tym','$yr')":"DELETE FROM `transactions$cid` WHERE `refno`='$code'";
			}
		}
		
		$con = $db->mysqlcon(2);
		$con->autocommit(0); $con->query("BEGIN");
		$check = $con->query("SELECT `officer` FROM `$stbl` WHERE `month`='$mon' AND `officer`='$ofid' FOR UPDATE");
		if($check->num_rows>0 or $type=="minus"){
			if($qry){ $con->query("UPDATE `$stbl` SET ".rtrim($qry,",")." WHERE `month`='$mon' AND `officer`='$ofid'"); }
		}
		else{
			$cols = rtrim($cols,","); $vals = rtrim($qri,",");
			$con->query("INSERT INTO `$stbl` ($cols) SELECT $vals FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `$stbl` WHERE `month`='$mon' AND `officer`='$ofid')");
		}
		$con->commit(); $con->close();
		foreach($qris as $qr){ $db->insert(3,$qr); }
	}
	
	function reversepay($frm,$code,$src="client",$retain=0){
		$db = new DBO(); $cid = CLIENT_ID; 
		$fdf = explode(":",$frm); $idno=$fdf[0];
		$ltbl = ($src=="client") ? "org".$cid."_loans":"staff_loans$cid";
		$stbl = ($src=="client") ? "org".$cid."_schedule":"staff_schedule$cid";
		$col = ($src=="client") ? "client_idno":"stid";
		$cond = (isset($fdf[1])) ? "AND `loan`='".$fdf[1]."'":"";
		
		$row = $db->query(2,"SELECT *FROM `$ltbl` WHERE `$col`='$idno' $cond ORDER BY `time` DESC LIMIT 1"); 
		$res = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$code'"); 
		if(!$res){ return 0; exit(); }
		$amnt=$tamnt=$res[0]['amount']; $pid=$res[0]['id']; $lid=$row[0]['loan']; $prod=$row[0]['loan_product']; $state=$res[0]['status'];
		
		if(!$state){ return 1; exit(); }
		$exclude = array("interest","penalties"); $added=[];
		$res = $db->query(1,"SELECT *FROM `loan_products` WHERE `id`='$prod'");
		foreach(json_decode($res[0]['payterms'],1) as $key=>$term){
			if(!in_array(explode(":",$term)[0],[2,3,5])){ $exclude[]=$key; }
			if(in_array(explode(":",$term)[0],[2,3,5])){ $added[]=$key; }
		}
		
		$schedule=$payterms=[]; $penal=$prcs=$pnts=0; $lis="";
		$qri = $db->query(2,"SELECT *FROM `processed_payments$cid` WHERE `payid`='$pid'");
		if(!$qri){ return 1; exit(); }
		foreach($qri as $row){
			$mon=$row['month']; $day=$row['day']; $bran=$row['branch']; $ofid=$row['officer']; $pay=str_replace(" ","_",strtolower($row['payment']));
			$prcs+=(!in_array($pay,$exclude)) ? $row['amount']:0;
			$penal+=($row['payment']=="Penalties") ? $row['amount']:0; $lis.="$pay,";
			if(isset($payterms[$pay])){ $payterms[$pay]+=$row['amount']; }
			else{ $payterms[$pay]=$row['amount']; }
		}
			
		if(rtrim($lis,",")!="penalties"){
			if($src=="client"){ $db->execute(2,"UPDATE `org".$cid."_clients` SET `status`='1' WHERE `idno`='$idno'"); }
			$res = $db->query(2,"SELECT *FROM `$stbl` WHERE `loan`='$lid' AND `paid`>0 ORDER BY `day` DESC"); $amnt-=$penal; 
			if($res){
				foreach($res as $row){
					if($amnt<1){ break; }
					$rid=$row['id']; $hist=json_decode($row['payments'],1);
					foreach($hist as $des=>$one){
						foreach($one as $key=>$pay){
							$cut = ($amnt>$pay) ? $pay:$amnt; $amnt-=$cut;
							if($amnt<1){ break; }
						}
						if(explode(":",$des)[0]==$pid){ unset($hist[$des]); }
					}
					$schedule[$rid]=$hist;
				}
			}
		}
		
		foreach($schedule as $rid=>$arr){
			$hist=json_encode($arr,1); $paid=0; foreach($arr as $one){ $paid+=array_sum($one); }
			$db->execute(2,"UPDATE `$stbl` SET `paid`='$paid',`balance`=(amount-$paid),`payments`='$hist' WHERE `id`='$rid'");
		}
		
		$qry = $db->query(2,"SELECT *,SUM(paid) AS pd,SUM(balance) AS bal FROM `$stbl` WHERE `loan`='$lid'");
		$tpay = $qry[0]['pd']; $tbal=$qry[0]['bal']; $bam=array_sum(getLoanBals($lid,0,$src)); $bam-=$tamnt;
		
		if($prcs){
			setLoanBook(); $bam+=$prcs;
			logtrans($lid,json_encode(array("desc"=>"Principal payment #$code Reversal","type"=>"debit","amount"=>$prcs,"bal"=>$bam),1),0);
		}
		
		if($penal){
			$db->execute(2,"UPDATE `penalties$cid` SET `paid`=(paid-$penal) WHERE `loan`='$lid'");
			bookbal(DEF_ACCS['loan_charges'],"-$penal:$mon");
		}
		
		foreach($added as $py){
			if(isset($payterms[$py])){
				$pay = ucwords(str_replace("_"," ",$py)); $bam+=$payterms[$py];
				logtrans($lid,json_encode(array("desc"=>"$pay payment #$code Reversal","type"=>"debit","amount"=>$payterms[$py],"bal"=>$bam),1),0);
			}
		}
		
		if(substr($code,0,8)=="CHECKOFF"){ $payterms["checkoff"]=$tamnt; }
		updatepays('minus',$mon,getpaybill($bran),$ofid,$payterms,[$code,$day,$bran,"None"]); 
		if(isset($payterms["interest"])){
			if($prcs<=0){ setLoanBook(); }
			$val=$payterms['interest']; bookbal(getrule('interest')['debit'],"-$val".":$mon"); $bam+=$val;
			logtrans($lid,json_encode(array("desc"=>"Payment #$code Reversal","type"=>"debit","amount"=>$val,"bal"=>$bam),1),0);
		}
		
		if($db->istable(3,"translogs$cid")){
			$chk = $db->query(3,"SELECT *FROM `translogs$cid` WHERE `ref`='OVP-$code'");
			if($chk){
				$host = $_SERVER['HTTP_HOST']; $def=json_decode($chk[0]['details'],1);
				$sid = (isset($_SESSION["myacc"])) ? substr(hexdec($_SESSION['myacc']),6):0;
				$url = ($host=="localhost") ? "http://localhost/mfs":"https://$host"; 
				request("$url/mfs/dbsave/account.php",["sysreq"=>genToken($sid),"revtrans"=>$def["tid"]]);
			}
		}
		
		if($db->istable(2,"overpayments$cid")){
			$ov = $db->query(2,"SELECT *FROM `overpayments$cid` WHERE `payid`='$pid'");
			if($ov){
				$db->execute(2,"DELETE FROM `overpayments$cid` WHERE `payid`='$pid'");
				if($ov[0]['status']>0){
					$ovcode = "OVERPAY".$ov[0]['id'];
					$qri = $db->query(2,"SELECT *FROM `org".$cid."_payments` WHERE `code`='$ovcode'");
					$st = $qri[0]['status']; $opid=$qri[0]['id'];
					if($st>10){
						$res = $db->query(2,"SELECT *FROM `mergedpayments$cid` WHERE `code`='$ovcode'");
						if($res){
							$ded=$res[0]['amount']; $mcode=$res[0]['transaction'];
							$db->execute(2,"DELETE FROM `mergedpayments$cid` WHERE `code`='$ovcode'");
							$db->execute(2,"UPDATE `org".$cid."_payments` SET `amount`=(amount-$ded) WHERE `code`='$mcode'");
						}
					}
					$db->execute(2,"DELETE FROM `org".$cid."_payments` WHERE `id`='$opid' OR `amount`<1");
				}
				bookbal(DEF_ACCS['overpayment'],"-".$ov[0]['amount']);
			} 
		}
		
		if(substr($code,0,6)=="WALLET" && !$retain){
			$uid = ($src=="client") ? $db->query(2,"SELECT `id` FROM `org$cid"."_clients` WHERE `idno`='$idno'")[0]['id']:$idno;
			updateWallet($uid,$tamnt,$src,["desc"=>"Payment #$code Reversal","revs"=>array(["tbl"=>"norev","tid"=>$code])],0,time(),0,0);
			$db->execute(2,"DELETE FROM `org$cid"."_payments` WHERE `code`='$code'");
		}
		else{ $db->execute(2,"UPDATE `org".$cid."_payments` SET `status`='0' WHERE `id`='$pid'"); }
	
		$db->execute(2,"UPDATE `$ltbl` SET `penalty`=(penalty+$penal),`paid`='$tpay',`balance`='$tbal' WHERE `loan`='$lid'");
		$res = $db->execute(2,"DELETE FROM `processed_payments$cid` WHERE `payid`='$pid'");
		if($src=="client"){ setTarget($ofid,$mon,["arrears","performing","loanbook","income","mtd","collections"]); }
		return $res;
	}
	
	
	class DBAsync{
		private $_handles = array();
		private $_mh      = array();
		
		function __construct($sid){
			$this->_mh = curl_multi_init();
			$this->token = genToken($sid);
		}
		
		function add($dbn,$sql){
			$host = $_SERVER["HTTP_HOST"];
			$url = ($host=="localhost") ? "http://localhost/mfs":"https://$host";
			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, "$url/mfs/dbsave/setup.php");
			curl_setopt($ch, CURLOPT_POST,1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query(["gasync"=>$sql,"dbn"=>$dbn,"token"=>$this->token]));
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
			curl_multi_add_handle($this->_mh, $ch);
			$this->_handles[] = $ch;
			return $this;
		}
		
		function run(){
			$running=null;
			do{
				curl_multi_exec($this->_mh, $running);
			} while ($running > 0);
			
			for($i=0; $i < count($this->_handles); $i++){
				$out = curl_multi_getcontent($this->_handles[$i]);
				$data[$i] = json_decode($out,1); 
				curl_multi_remove_handle($this->_mh, $this->_handles[$i]);
			}
			curl_multi_close($this->_mh);
			return $data;
		}
	}
	
	class DBO{
		
		public function pdocon($db){
			$store = DATABASES[$db];
			try{
				$con = new PDO("mysql:host=".DB_HOST.";dbname=".$store['name'], $store['user'], $store['pass']);
				$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				return $con;
			}
			catch(Exception $e){
				return $e;
			}
		}
		
		public function mysqlcon($db){
			$store = DATABASES[$db];
			$con = @mysqli_connect(DB_HOST,$store['user'],$store['pass'],$store['name']);
			return (mysqli_connect_errno()) ? null:$con;
		}
		
		function istable($db,$name){
			$dbname=DATABASES[$db]['name'];
			$check = $this->query($db,"SELECT * FROM INFORMATION_SCHEMA.TABLES where TABLE_NAME = '$name' AND TABLE_SCHEMA = '$dbname'");
			return ($check) ? 1:0;
		}
		
		public function createTbl($db,$name,$fields){
			$tbl = prepstr($name); $dbname=DATABASES[$db]['name'];
			$check = $this->query($db,"SELECT * FROM INFORMATION_SCHEMA.TABLES where TABLE_NAME = '$tbl' AND TABLE_SCHEMA = '$dbname'");
			$changed = (isset($fields["tchanged"])) ? @array_pop($fields):[];
			
			$keys = ""; $data=[];
			if($check){
				$res = $this->query($db,"SELECT *FROM `$tbl`");
				$data = ($res) ? $res:[];
				$this->insert($db,"DROP TABLE $tbl");
			}
			
			foreach($fields as $fld=>$type){
				if($fld!="id"){
					$ftyp = explode(".",$type); $ext=(count($ftyp)>1) ? 1:null; $col=($ext) ? "varchar(255)":"tinytext";
					$fkey = preg_replace('/[^A-Za-z0-9\-_]/', '', strtolower(str_replace(" ","_",trim($fld))));
					$add = ($ftyp[0]=="INT") ? "int(11) NOT NULL":"$col NOT NULL";
					$add = ($ftyp[0]=="TEXT") ? "mediumtext NOT NULL":$add;
					$add.= ($ext) ? " UNIQUE":"";
					$keys.="`$fkey` $add,";
				}
			}
		
			$res = $this->insert($db,"
				CREATE TABLE `$tbl`(
					`id` int(11) NOT NULL AUTO_INCREMENT, $keys PRIMARY KEY (`id`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1
			");
			
			if($res){
				$cols = array_keys($fields); $qrys=[]; $fds="";
				foreach($data as $pos=>$row){
					foreach($cols as $col){
						$key = (in_array($col,$changed)) ? array_search($col,$changed):$col;
						$vals[]=(array_key_exists($key,$row)) ? "'".$row[$key]."'":"'0'";
						if($pos==0){ $fds.="`$col`,"; }
					}
					$qrys[]="(".implode(",",$vals).")"; $vals=[];
				}
				
				if(count($qrys)){
					foreach(array_chunk($qrys,150) as $chunk){
						$this->insert($db,"INSERT INTO `$tbl` (".rtrim($fds,",").") VALUES ".implode(",",$chunk));
					}
				}
			}
			
			return $res;
		}
		
		public function insert($db,$query){
			$store = DATABASES[$db];
			
			if($con = mysqli_connect(DB_HOST,$store['user'],$store['pass'],$store['name'])){
				$res = mysqli_query($con, $query); $error=mysqli_error($con); mysqli_close($con);
				if($error){ file_put_contents("mysql_errors.txt","$error=$query\r\n",FILE_APPEND); }
				return ($res) ? 1:0;
			}
			else{
				$con = new PDO("mysql:host=".DB_HOST.";dbname=".$store['name'], $store['user'], $store['pass']);
				$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				try{
					$stmt = $con->prepare($query);
					$res = $stmt->execute(); $con = null;
					return ($res) ? 1:0;
				}
				catch(PDOException $err){
					insertSQLite("tempinfo","CREATE TABLE IF NOT EXISTS fallback (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,db INTEGER,locks TEXT,query TEXT)");
					insertSQLite("tempinfo","INSERT INTO `fallback` VALUES(NULL,'$db','none','".base64_encode($query)."')");
				}
			}
		}
		
		public function execute($db,$query){
			return $this->insert($db,$query);
		}
		
		public function query($db,$query){
			$store = DATABASES[$db];
			
			if($con = mysqli_connect(DB_HOST,$store['user'],$store['pass'],$store['name'])){
				if(strpos($query,"from_unixtime")!==false){ mysqli_query($con, "SET time_zone = '+03:00'"); }
				$sql = mysqli_query($con, $query); $res=[];
				while($row=mysqli_fetch_assoc($sql)){ $res[] = $row; }
				$error=mysqli_error($con); mysqli_close($con);
				if($error){ file_put_contents("mysql_errors.txt","$error=$query\r\n",FILE_APPEND); }
				return (count($res)) ? $res:null;
			}
			else{
				$con = new PDO("mysql:host=".DB_HOST.";dbname=".$store['name'], $store['user'], $store['pass']);
				$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				if(strpos($query,"from_unixtime")!==false){ $stm = $con->prepare("SET time_zone = '+03:00'"); $stm->execute(); }
				$stmt = $con->prepare($query); $stmt->execute(); $data=[];
				while($row=$stmt->fetch(PDO::FETCH_ASSOC)){ $data[]=$row; }
				$con = null;
				return (count($data)) ? $data:null;
			}
		}
		
		function tableFields($db,$table){
			$res = $this->query($db,"DESC $table");
			foreach($res as $one){ $cols[]=$one['Field']; }
			return $cols;
		}
	}
	
	function cropSQ($tmp,$size,$save){
		$ext = @array_pop(explode(".",$tmp));
		list($width,$height)=getimagesize($tmp);
		
		if($ext=="png"){
			$newname=imagecreatefrompng($tmp);
		}
		if($ext=='jpg' || $ext=='jpeg'){
			$newname=imagecreatefromjpeg($tmp);
		}
		if($ext=="gif"){
			$newname=imagecreatefromgif($tmp);
		}
				
		if($width > $height){
			$y = 0;
			$x = ($width - $height) / 2;
			$smallestSide = $height;
		}
		else{
			$x = 0;
			$y = ($height - $width) / 2;
			$smallestSide = $width;
		}
					
		$tmp_image=imagecreatetruecolor($size,$size); $res=0;
		$transparent = imagecolorallocatealpha($tmp_image, 0,0,0,127);
		imagealphablending( $tmp_image, false);
		imagesavealpha($tmp_image, true);
		imagefill($tmp_image, 0, 0, $transparent);
		imagecopyresampled($tmp_image, $newname, 0, 0, $x, $y, $size, $size, $smallestSide, $smallestSide);
		if(imagejpeg($tmp_image,$save,80)){ $res = 1; }
		imagedestroy($tmp_image);
		imagedestroy($newname);
		return $res;
	}
	
	function crop($tmp,$cropwidth,$maxheight,$name){
		$ext=array_reverse(explode(".",$tmp))[0];
		list($width,$height)=getimagesize($tmp);
		if($ext=="jpeg" or $ext=="jpg"){ $newname=imagecreatefromjpeg($tmp); }
		if($ext=="png"){ $newname=imagecreatefrompng($tmp); }
		if($ext=="gif"){ $newname=imagecreatefromgif($tmp); }
		
		if($width > $height and $width>$cropwidth){
			$new_width=$cropwidth;
			$new_height=($height/$width)*$cropwidth;
		}
		else if($width<$height and $height>$maxheight){
			$new_height=$maxheight;
			$new_width=($width/$height)*$maxheight;
		}
		else if($width==$height and $width>$cropwidth){
			$new_width=$cropwidth;
			$new_height=$cropwidth;
		}
		else{
			$new_width=$width;
			$new_height=$height;
		}
		
		$tmp_image = imagecreatetruecolor($new_width,$new_height);
		$transparent = imagecolorallocatealpha($tmp_image, 0,0,0,127);
		imagealphablending( $tmp_image, false);
		imagesavealpha($tmp_image, true);
		imagefill($tmp_image, 0, 0, $transparent);
		imagecopyresampled($tmp_image,$newname,0,0,0,0,$new_width,$new_height,$width,$height);
		if($ext=="png"){ imagepng($tmp_image,$name,8); }
		if($ext=="jpg" or $ext=="jpeg"){ imagejpeg($tmp_image,$name,80); }
		imagedestroy($tmp_image); imagedestroy($newname);
		return 1;
	}
	
	function getroles($roles){
		$db = new DBO(); $mfi=mficlient("syszn".CLIENT_ID);
		$res = $db->query(1,"SELECT *FROM `useroles`"); $data=[];
		foreach($res as $row){
			if(in_array($row['id'],$roles) && in_array($row["cluster"],$mfi)){ $data[]=trim($row['role']); }
		}
		return $data;
	}
	
	function collAgentRoles(){
		return (defined("COLL_AGENT_ROLES")) ? COLL_AGENT_ROLES:"9,22,24,25,29,28,112";
	}
	
	function staffInfo($sid){
		$db = new DBO(); $tbl="org".CLIENT_ID."_staff";
		return $db->query(2,"SELECT *FROM `$tbl` WHERE `id`='$sid'")[0];
	}
	
	function clean($data){
		return htmlentities(htmlentities(strip_tags(trim($data))),ENT_QUOTES);
	}
	
	function prepstr($str){
		return preg_replace('/[^A-Za-z0-9\-_]/', '', strtolower(str_replace(" ","_",trim($str))));
	}
	
	function prepare($data){
		return html_entity_decode(html_entity_decode(stripslashes(stripslashes($data)),ENT_QUOTES));
	}
	
	function weekrange($week, $year) {
	  $date_string = $year . 'W' . sprintf('%02d', $week);
	  $return[0] = strtotime($date_string);
	  $return[1] = strtotime($date_string . '7');
	  return $return;
	}
	
	function genrepDiv($callback,$pos){
		$float = ($pos) ? "float:$pos":"";
		$div = "<select style='border:0px;background:#f0f0f0;width:120px;padding:4px;cursor:pointer;color:#4682b4;$float;font-size:14px;border:1px solid #dcdcdc' 
		onchange=\"genreport('$callback',this.value)\"><option value=''>-- Generate --</option><option value='pdf'>PDF Printout</option>
		<option value='xls'>Excel File</option></select>";
		return $div;
	}
	
	function getIP(){
		return $_SERVER["REMOTE_ADDR"];
	}
	
	function shortenUrl($url){
		// $url = urlencode($url);
		// $json = file_get_contents("https://cutt.ly/api/api.php?key=e39b5ebae235b4b48ad84b101b4ddfb0b33a3&short=$url");
		// $res = json_decode($json,1); 
		// return ($res['url']['status']==7) ? $res['url']['shortLink']:urldecode($url);
		return $url;
	}
	
	function mailto($email,$subj,$mssg,$doc){
		$from = mficlient();
		$cname = ucwords(strtolower($from['company'])); $client = json_encode(array("email"=>$from['email'],"name"=>$cname),1);
		$body = array("client"=>$client,"emails"=>json_encode($email,1),"subjects"=>json_encode($subj,1),"messages"=>json_encode($mssg,1),"docs"=>json_encode($doc,1));
		return request(MAIL_URL,$body);
	}
	
	function countext($txt){
		$len=strlen(strip_tags($txt));
		if($len<161){ return "$len chars, 1 Mssg"; }
		else{
			$rem=ceil($len/160);
			return "$len chars, $rem Mssgs";
		}
	}
	
	function upload($url,$filenames){
		$files=$temps= array();
		foreach ($filenames as $file){
			$val=rand(123456,654321); $temps[$val]=@array_pop(explode("/",$file));
			$files[$val] = file_get_contents($file);
		}
		
		$fields=array("gnames"=>json_encode($temps));
		$url_data = http_build_query($fields);
		$boundary = uniqid();
		$delimiter = '-------------' . $boundary;
		$post_data = build_data_files($boundary, $fields, $files);
		
		$ch = curl_init();
		curl_setopt_array($ch, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => 1,
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POST => 1,
		  CURLOPT_POSTFIELDS => $post_data,
		  CURLOPT_SSL_VERIFYHOST=>0,
		  CURLOPT_HTTPHEADER => array(
			"Content-Type: multipart/form-data; boundary=" . $delimiter,
			"Content-Length: " . strlen($post_data)
		),));

		$result=curl_exec($ch); 
		$err=curl_errno($ch);
		curl_close($ch);
		if($err !=0){$result=$err;}
		return $result;
	}
	
	function build_data_files($boundary, $fields, $files){
		$data = ''; $eol = "\r\n";
		$delimiter = '-------------' . $boundary;
		foreach ($fields as $name => $content) {
			$data .= "--" . $delimiter . $eol
			. 'Content-Disposition: form-data; name="' . $name . "\"".$eol.$eol
			. $content . $eol;
		}
		foreach ($files as $name => $content) {
			$data .= "--" . $delimiter . $eol
				. 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $name . '"' . $eol
				. 'Content-Transfer-Encoding: binary'.$eol;
			$data .= $eol;
			$data .= $content . $eol;
		}
		$data .= "--" . $delimiter . "--".$eol;
		return $data;
	}
	
	function setTask($sid,$desc,$nav){
		try{
			$path = str_replace(array("\core","/core"),"",__DIR__); $tym=time();
			$db = new PDO("sqlite:$path/sqlite/sysnots");
			$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			$db->exec("CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,user INTEGER,task TEXT,nav TEXT,status INTEGER,time INTEGER)");
			$db->exec("INSERT INTO `tasks` VALUES(NULL,'$sid','".clean($desc)."','$nav','0','$tym')");
			$db = null;
		}
		catch(PDOException $e){ return $e->getMessage(); }
	}
	
	function notify($nto,$mssg,$sms=1){
		$not = (defined("NOTIFY_USERS")) ? NOTIFY_USERS:$sms;
		$nav = (isset($nto[2])) ? $nto[2]:""; setTask($nto[0],$mssg,$nav);
		if($not && $not!="system"){ sendSMS($nto[1],$mssg); }
	}
	
	function savelog($sid,$desc){
		$skip = (defined("STOP_SQLITE")) ? STOP_SQLITE:[];
		if(!in_array(intval(date("H")),$skip) && !in_array($sid,[1,5])){
			try{
				$path = str_replace(array("\core","/core"),"",__DIR__);
				$mon=strtotime(date("Y-M")); $dy=strtotime(date("Y-M-d")); $tym=time();
				$db = new PDO("sqlite:$path/sqlite/syslogs");
				$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
				foreach(array("months",$mon) as $tbl){
					$cols = ($tbl=="months") ? "month INTEGER UNIQUE NOT NULL":"staff INTEGER,activity TEXT, day INTEGER, time INTEGER";
					$db->exec("CREATE TABLE IF NOT EXISTS '$tbl' (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,$cols)");
				}
				
				$db->exec("INSERT INTO `$mon` VALUES(NULL,'$sid','".clean($desc)."','$dy','$tym')");
				$db->exec("INSERT or IGNORE INTO months VALUES(NULL,'$mon')");
				$db = null;
			}
			catch(PDOException $e){ return $e->getMessage(); }
		}
	}
	
	function insertSQLite($dbname,$query){
		try{
			$path = str_replace(array("\core","/core"),"",__DIR__);
			$db = new PDO("sqlite:$path/sqlite/$dbname");
			$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			$res = $db->exec($query);
			if($res && defined("SQLITE_SHARE") && $dbname!="sitesess"){
				$dbn = new DBO(); $site=$_SERVER["HTTP_HOST"];
				$dbn->execute(4,"INSERT INTO `sqlite_def` VALUES(NULL,'$dbname','$site','".base64_encode($query)."','0')");
			}
			$db = null; return ($res) ? 1:0;
		}
		catch(PDOException $e){ return $e->getMessage(); }
	}
	
	function fetchSQLite($dbname,$query){
		try{
			$path = str_replace(array("\core","/core"),"",__DIR__);
			$db = new PDO("sqlite:$path/sqlite/$dbname");
			$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			$stmt = $db->query($query); $data=[];
			while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
				$data[]=$row;
			}
			
			$db = null; return $data;
		}
		catch(PDOException $e){ return $e->getMessage(); }
	}
	
	function updateB2C($amnt,$desc,$ref,$tym,$payb=0){
		$db = new DBO(); $cid=CLIENT_ID;
		$list = (defined("PAYBILL_ACCS")) ? PAYBILL_ACCS:[]; $acc=(isset($list[$payb])) ? $list[$payb]:16;
		$mon = strtotime(date("Y-M",$tym)); $day=strtotime(date("Y-M-d",$tym)); $yr=date("Y",$tym); 
		$tid = getransid(); $trans=($amnt>0) ? "debit":"credit"; $sum=str_replace("-","",$amnt); $bam=($amnt>0) ? "+$amnt":$amnt;
		if($db->execute(3,"INSERT INTO `transactions$cid` VALUES(NULL,'$tid','0','asset','$acc','$sum','$trans','$desc','$ref','','0','default','$mon','$day','$tym','$yr')")){
			bookbal(16,"$bam:$mon"); return 1;
		}
		else{ return 0; }
	}
	
	function recordpay($payb,$amnt,$bal){
		$db = new DBO(); $cid=CLIENT_ID;
		$ptbl = "paybill_balances$cid";
		$path = str_replace(array("\core","/core"),"",__DIR__).'/docs';
		$isone = sys_constants("c2b_one_acc");
		
		if(count(explode(":",$payb))>1){
			$tym = explode(":",$payb)[1]; $paybill=explode(":",$payb)[0]; $uptm=time();
			$mon = strtotime(date("Y-M",$tym)); $day = strtotime(date("Y-m-d",$tym)); $yr = date("Y",$tym);
		}
		else{
			$day = strtotime(date("Y-m-d")); $paybill=$payb;
			$mon = strtotime(date("Y-M")); $yr=date("Y"); $tym=$uptm=time();
		}
		
		$rec = (file_exists("$path/lastpay.dll")) ? file_get_contents("$path/lastpay.dll"):$uptm;
		foreach(array("paybill_balances","payin") as $tbl){
			if(!$db->istable(2,$tbl.$cid)){
				$fields = json_decode($db->query(1,"SELECT *FROM `default_tables` WHERE `name`='$tbl'")[0]['fields'],1);
				$db->createTbl(2,$tbl.$cid,$fields);
			}
		}
		
		if($rec<$tym){
			$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `paybill`='$paybill'");
			if($res){
				$row=$res[0]; $cbal=$row['current']; $pval=$row['previous'];
				if($bal>0){
					$db->execute(2,"UPDATE `$ptbl` SET `current`='$bal',`previous`='$pval' WHERE `paybill`='$paybill'");
				}
			}
			else{ $db->execute(2,"INSERT INTO `$ptbl` VALUES(NULL,'$paybill','$amnt','$bal')"); }
		}
		
		$state = ($isone) ? time():0;
		$sql = $db->query(2,"SELECT *FROM `payin$cid` WHERE `day`='$day' AND `paybill`='$paybill'");
		if($sql){
			$db->execute(2,"UPDATE `payin$cid` SET `amount`=(amount+$amnt) WHERE `day`='$day' AND `paybill`='$paybill'");
			if($day<strtotime("Today") && !$isone){
				$db->execute(3,"UPDATE `transactions$cid` SET `amount`=(amount+$amnt) WHERE `refno`='P".$sql[0]['id']."'");
			}
		}
		else{ $db->execute(2,"INSERT INTO `payin$cid` VALUES(NULL,'$paybill','$mon','$day','$yr','$amnt','$state')"); }
		file_put_contents("$path/lastpay.dll",$uptm);
	}
	
	function sendSMS($to,$mssg){
		$config = mficlient();
		$data = json_encode(
			["recipients"=>$to,"message"=>$mssg,"apikey"=>$config["apikey"],"appname"=>$config["appname"],"senderId"=>$config["senderid"]]
		);
		
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,SMS_SURL);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Accept: application/json',
			'Cache-Control: no-cache',
			'Content-Type: application/json; charset=utf-8'
		));
		
		$res	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		
		if($error){ $result = $error; }
		else{
			$res = json_decode($res,1);
			$result = ($res['response']=="success") ? "Sent to ".$res['data']['sendto']." Cost ".$res['data']['cost']:$res['response'];
		}
		return $result;
	}
	
	function smslogs($data=[]){
		$config = mficlient();
		$data['apikey']=$config["apikey"]; $data['appname']=$config["appname"];
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,str_replace("balance","logs",SMS_BURL));
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data,1));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Accept: application/json',
			'Cache-Control: no-cache',
			'Content-Type: application/json; charset=utf-8'
		));
		
		$result	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		return ($error) ? array("response"=>$error):json_decode($result,1);
	}
	
	function smsbalance(){
		$config = mficlient();
		$data=json_encode(["apikey"=>$config["apikey"],"appname"=>$config["appname"]]);
		
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,SMS_BURL);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Accept: application/json',
			'Cache-Control: no-cache',
			'Content-Type: application/json; charset=utf-8'
		));
		
		$result	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		
		return ($error) ? "NULL":"KES ".json_decode($result,1)['data']['balance'];
	}
	
	function paysms($phone,$amnt){
		$config = mficlient();
		$data=json_encode(["phone"=>$phone,"amount"=>$amnt,"apikey"=>$config["apikey"],"appname"=>$config["appname"]]);
		
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,SMS_PURL);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Accept: application/json',
			'Cache-Control: no-cache',
			'Content-Type: application/json; charset=utf-8'
		));
		
		$result	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		
		return ($error) ? $error:json_decode($result,1)['response'];
	}
	
	function monrange($mon,$yr){
		$dim = cal_days_in_month(CAL_GREGORIAN, $mon, $yr); 
		$sttm = mktime(0,0,0,$mon,1,$yr); $endtm=mktime(0,0,0,$mon,$dim,$yr);
		return array($sttm,$endtm); 
	}
	
	function encrypt($plaintext, $password){
		$method = "AES-256-CBC";
		$key = hash('sha256', $password, true);
		$iv = openssl_random_pseudo_bytes(16);
		$ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);
		$hash = hash_hmac('sha256', $ciphertext.$iv, $key, true);
		return base64_encode($iv.$hash.$ciphertext);
	}

	function decrypt($encrypted, $password){
		$ivHashCiphertext = base64_decode($encrypted);
		$method = "AES-256-CBC";
		$iv = substr($ivHashCiphertext, 0, 16);
		$hash = substr($ivHashCiphertext, 16, 32);
		$ciphertext = substr($ivHashCiphertext, 48);
		$key = hash('sha256', $password, true);
		if (!hash_equals(hash_hmac('sha256', $ciphertext . $iv, $key, true), $hash)) return null;
		return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
	}
	
	function getLimitDiv($page,$perpage,$total,$jscall){
		$calc = $perpage * $page; 
		$start = $calc - $perpage;
		$next=$page+1;
		$prev=($page>1) ? $page-1:1;
		$all=ceil($total/$perpage);
		$prevbtn=($page>1) ? "<button class='bts' style='float:left;font-size:15px;padding:3px' onclick=\"loadpage('$jscall&pg=$prev')\">
		<i class='fa fa-chevron-left'></i> Previous</button>":"";
		$nextbtn=($all>$page) ? "<button class='bts' style='float:right;font-size:15px;padding:3px' onclick=\"loadpage('$jscall&pg=$next')\">
		<i class='fa fa-chevron-right'></i> Next</button>":"";
		$opts="";
		for($i=1; $i<=$all; $i++){
			$cond=($i==$page) ? "selected":"";
			$opts.="<option value='$i' $cond>$i</option>";
		}
		
		$div=($all>1) ? "<div style='width:250px;height:50px;margin:0 auto;padding:10px'>
		<center>$prevbtn <select style='padding:5px;width:60px' onchange=\"loadpage('$jscall&pg='+this.value)\">$opts</select> $nextbtn</center>
		</div>":"";
		
		return $div;
	}
	
	function getLimit($page,$perpage){
		$calc = $perpage * $page; 
		$start = $calc - $perpage;
		return "LIMIT $start,$perpage";
	}
	
	function getagent(){
		$agent = $_SERVER["HTTP_USER_AGENT"]; $device="Another device";
		if(preg_match('/MSIE (\d+\.\d+);/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "Windows Explorer browser on windows":"Windows Explorer on Mobile";
		}elseif(preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "Chrome browser on windows":"Chrome on Mobile phone";
		}elseif(preg_match('/Edge\/\d+/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "MS Edge browser on windows":"MS Edge on Mobile phone";
		}elseif(preg_match('/Firefox[\/\s](\d+\.\d+)/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "Firefox browser on windows":"Mobile phone firefox browser";
		}elseif(preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "Opera browser on windows":"Mobile phone opera browser";
		}elseif(preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)){
			$device = (strpos($agent,"Windows")!==false) ? "Safari browser on windows":"Safari browser on Mobile";
		}
		
		return $device;
	}
	
	function request($url,$data){
		$str=http_build_query($data);
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$str);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		$result=curl_exec($ch); 
		$err=curl_error($ch);
		curl_close($ch);
		return ($err) ? $err:$result;
	}
	
	function openurl($url,$data){
		$str=http_build_query($data);
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$str);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($ch,CURLOPT_TIMEOUT,2);
		curl_exec($ch); 
		curl_close($ch);
	}
	
	function greet($name=null){
		date_default_timezone_set("Africa/Nairobi");
		$hr = date("H");
		if($hr<12){$greet="Good Morning";}
		if($hr>11 && $hr<16){$greet="Good Afternoon";}
		if($hr>15){$greet="Good Evening";}
		return ($name) ? "$greet $name":$greet;
	}
	
	function delfiles($dr){
		if(is_dir($dr)){
			$dir = opendir($dr);
			while(false !== ( $file = readdir($dir)) ) {
				if (( $file != '.' ) && ( $file != '..' )) {
					$full = $dr.'/' . $file;
					if ( is_dir($full) ) {
						rmdir($full);
					}
					else {
						unlink($full);
					}
				}
			}
			closedir($dir);
		}
	}
	
	function prenum($val){
		$num = ($val<10) ? "00$val":"0$val";
		$num = ($val>100) ? $val:$num;
		return $num;
	}
	
	function getfiles($folder){
		$items=array();
		if($handle=opendir($folder)){
			while(false !==($file=readdir($handle))){
				if(($file !=".") and ($file !="..")){
					if(count(explode(".",$file))>1){
						$items[]=$file;
					}
				}
			}
			closedir($handle);
			return $items;
		}
	}
	
	function zipDir($rootPath,$excludes){
		$dir = @array_pop(explode("/",str_replace("\\","/",$rootPath)));
		$zip = new ZipArchive();
		$zip->open("$dir.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($rootPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file){
			if(!$file->isDir() && !in_array($file,$excludes)){
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($rootPath) + 1);
				$zip->addFile($filePath, $relativePath);
			}
		}

		$zip->close();
	}
	
	function addtoZip($name,$files){
		if(!file_exists($name)){file_put_contents($name,"");}
		$zip = new ZipArchive;
		if ($zip->open($name) === TRUE) {
			foreach($files as $file){
				$fname = (count(explode("/",$file))>1) ? explode("/",$file)[1]:$file;
				$src = (strpos($fname,"home")!==false) ? $file:getcwd()."/$file";
				$zip->addFile($src, @array_pop(explode("/",$file)));
			}
			$zip->close(); $err=true;
		}
		else { $err= false; }
		return $err;
	}
	
	function openZip($file,$folder){
		if($folder!=null and !is_dir($folder)){mkdir($folder,0777);}
		$zip = new ZipArchive;
		$res = $zip->open($file);
		if ($res === TRUE) {
		  $zip->extractTo(getcwd()."/$folder/");
		  $zip->close(); $err='success';
		} else { $err='Failed to Extract File'; }
		return $err;
	}
	
	function zipDel($zipfile,$files){
		$zip = new ZipArchive;
		if($zip->open($zipfile) === TRUE){
			foreach($files as $file){
				$zip->deleteName($file);
			}
		  $zip->close();
		}
	}

	function frame_email($body){
		$config = mficlient();
		$img = $config['logo']; $logo=getphoto($img); $url=$_SERVER["HTTP_HOST"];
		file_put_contents(str_replace(array("\core","/core"),"",__DIR__)."/docs/img/$img",base64_decode($logo));
		
		$data = "<html><meta chartset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'>
		<head></head><body>
		<div style='max-width:550px;font-family:ebrima;margin:0 auto;background:#f8f8f0;min-height:300px'>
			<div style='padding:4px;background:;color:#fff;min-height:100px'>
				<center><br><img src='https://$url/docs/img/$img' style='max-width:100%;max-height:90px'></center>
			</div>
			<div style='padding:15px 10px;background:#fff;font-family:ebrima;line-height:21px;color:#181F3B;margin:12px;border:1px solid #f0f0f0'>
				$body
			</div>
			<div style='padding:15px 10px;background:#fff;font-family:ebrima;line-height:21px;color:#191970;margin:12px;border:1px solid #f0f0f0'>
				<p style='margin:0px;text-align:center'>".$config['contact']." | ".$config['email']."</p>
				<p style='margin:0px;text-align:center'>".str_replace("~","<br>",prepare($config['address']))."</p>
			</div>
			<div style='border-top:1px solid #f0f0f0;text-align:center;color:#778899;padding:10px;background:#fff'>
				<p>Copyright &copy; ".prepare($config['company'])."<br> All rights reserved.</p>
			</div>
		</div></body></html>";
		
		return $data;
	}

?>