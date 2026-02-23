<?php
	
	require "functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	insertSQLite("ussds","CREATE TABLE IF NOT EXISTS sessions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,sid TEXT,phone INT NOT NULL,access TEXT,cut TEXT,time INT NOT NULL)");
	
	function post_req($url,$data,$auth,$pbky){
		$http = explode(".",$_SERVER['HTTP_HOST']); unset($http[0]);
		$publ = openssl_pkey_get_public($pbky); $data["sip"]=BASE_URL;
		openssl_public_encrypt(json_encode($data,1), $post, $publ, OPENSSL_PKCS1_OAEP_PADDING);
		openssl_free_key($publ);
		
		$ch	= curl_init();
		curl_setopt($ch, CURLOPT_URL,"https://api.".implode(".",$http).$url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS,base64_encode($post));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Content-Type: application/json; charset=utf-8',
			'Cbs-Secret-Key: '.$auth
		));
		
		$res	= curl_exec($ch); 
		$error	= curl_error($ch);
		curl_close($ch);
		return ($error) ? $error:$res;
	}
	
	if(isset($_POST["sessionId"])){
        $sid	= trim($_POST["sessionId"]);
        $code	= trim($_POST["serviceCode"]);
        $phone	= ltrim(intval($_POST["phoneNumber"]),"254");
        $dtext	= trim($_POST["text"]);
		
		$sql = fetchSqlite("ussds","SELECT cut FROM `sessions` WHERE `sid`='$sid' AND `phone`='$phone' AND NOT `cut`='' ORDER BY `time` DESC LIMIT 1");
		$cut = ($sql && is_array($sql)) ? $sql[0]['cut']:""; $scut="";
		$text = ltrim(substr($dtext,strlen($cut)),"*");  
		
		$qri = $db->query(2,"SELECT *FROM `org$cid"."_staff` WHERE `position`='USSD' AND `status`='0'");
		if($qri){
			$def = json_decode($qri[0]["config"],1); $auth=$def["key"]; 
			$prky = base64_decode($def['prkey']); $pbky=base64_decode($def['pbkey']);
		}
		else{
			header('Content-type: text/plain');
			echo "END Ooops! This channel is temporarily not in use"; exit();
		}
        
        if($text==""){
			$sql = $db->query(2,"SELECT `name` FROM `org$cid"."_clients` WHERE `contact`='$phone'"); $name=($sql) ? explode(" ",prepare(ucwords($sql[0]['name'])))[0]:"";
			$options	= array("CON ".greet($name),"1. My Account Info","2. Check Loan details","3. Check Loan balance","4. Check Loan Limit","5. View installments","6. Request Loan");
            $response	= implode("\n",$options); $access="Menu";
        }
        elseif(in_array($text,["1","2","3","4","5","6"])){
            $response = "CON Enter your ID Number\n"; $access="Menu";
        }
		# fetch info
        elseif(in_array(explode("*",$text)[0],["1","2","3","4","5"])){
            $idno = trim(@end(explode("*",$text))); $pos=trim(explode("*",$text)[0]);
			$urls = array(1=>"fetch_client",2=>"loan_details",3=>"loan_balance",4=>"loan_limit",5=>"installment");
			$res  = post_req("/v1/".$urls[$pos],["client_idno"=>$idno],$auth,$pbky); $json=json_decode($res,1);
			
			$titles = ["","My Account Info","Loan Details","Current Loan Balance","My Loan Limit","Active Installments"]; 
			$data = (isset($json["enc_data"])) ? json_decode(ssl_decrypt($json['enc_data'],$prky),1):$json; $access=$titles[$pos];
			if(trim($data["response"])=="success"){
				$response = "END $titles[$pos]\n\n";
				foreach($data["data"] as $key=>$val){ $response.= ucfirst(str_replace("_"," ",$key))." : $val\n"; }
			}
			else{
				if($pos=="1"){ $response = "CON Not Found. Enter Registered IDNO\n"; }
				else{
					$options = array("1. My Account Info","2. Check Loan details","3. Check Loan balance","4. Check Loan Limit","5. View installments","6. Request Loan");
					$response = "CON ".$data["response"]."\n\n".implode("\n",$options); $scut=$dtext;
				}
			}
        }
		# request loan
        elseif(explode("*",$text)[0]=="6"){
            $part = explode("*",$text); $idno=trim($part[1]); $resp="";
			$reqs = (defined("USSD_LOAN_REQS")) ? USSD_LOAN_REQS:[]; $access="Loan Application";
			
			if(!isset($part[2])){ $response = "CON Enter Loan Amount\n"; }
			else{
				$post = ["client_idno"=>$idno,"loan_amount"=>trim($part[2])];
				foreach($reqs as $key=>$col){
					$nky = $key+3; $cname=ucwords(str_replace("_"," ",$col));
					if(!isset($part[$nky])){ $resp = "CON Enter $cname\n"; break; }
					else{ $post[$col]=trim($part[$nky]); }
				}
				
				if($resp){ $response = $resp; }
				else{
					$res  = post_req("/v1/apply_loan",$post,$auth,$pbky); $json=json_decode($res,1);
					$data = (isset($json["enc_data"])) ? json_decode(ssl_decrypt($json['enc_data'],$prky),1):$json;
					$response = (trim($data["response"])=="success") ? "END Loan application successful! We shall contact you shortly":"END ".$data["response"];
				}
			}
        }
		else{
			$options	= array("CON Invalid Option","1. My Account Info","2. Check Loan details","3. Check Loan balance","4. Check Loan Limit","5. View installments","6. Request Loan");
            $response	= implode("\n",$options); $scut=$dtext; $access="Wrong Menu";
		}
      
		insertSQLite("ussds","INSERT INTO `sessions` VALUES(NULL,'$sid','$phone','$access','$scut','".time()."')");
        header('Content-type: text/plain');
        echo $response;
    }


?>