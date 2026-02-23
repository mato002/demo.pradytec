<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
    
	require "../core/functions.php";
	$db = new DBO(); $cid=CLIENT_ID;
	
	function getToken($key,$secret){
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			"Authorization:Basic ".base64_encode($key.':'.$secret)
		  ),
		));

		$response = curl_exec($curl);
		$error = curl_errno($curl);
		curl_close($curl);
		
		$arr = json_decode($response,1); $check=(is_array($arr)) ? $arr:[];
		$res = (array_key_exists("access_token",$check)) ? json_decode($response,1)['access_token']:$response;
		return ($error) ? null:$res;
	}
	
	function pullData($token,$paybill,$from,$to){
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.safaricom.co.ke/pulltransactions/v1/query',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
			"ShortCode":"'.$paybill.'",
			"StartDate":"'.$from.'",
			"EndDate":"'.$to.'",
			"OffSetValue":"0"
		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'Authorization: Bearer '.$token
		  ),
		));
		
		$response = curl_exec($curl);
		$error = curl_errno($curl);
		curl_close($curl);
		return ($error) ? null:$response;
	}

	if(isset($_POST['checkpays'])){
		$shotcodes = array();
		
		foreach($shotcodes as $payb=>$def){
			$token = getToken($def['secret'],$def['consumer']);
			if($token){
				$today = strtotime(date("Y-M-d")); $now=time(); $ptbl="org".$cid."_payments";
				$rec = (file_exists("../docs/c2b.dll")) ? json_decode(file_get_contents("../docs/c2b.dll"),1):array($payb=>$today);
				if(!array_key_exists($payb,$rec)){ $rec[$payb]=$today; }
				
				$from = date("Y-m-d H:i:s",$rec[$payb]); $to = date("Y-m-d H:i:s",$now);
				$res = json_decode(pullData($token,$payb,$from,$to),1);
				
				if($res['ResponseCode']==1000){
					foreach($res['Response'][0] as $one){ 
						$code=$one['transactionId']; $tym=strtotime(explode("+",str_replace("T",",",$one['trxDate']))[0]); $day=date("YmdHis",$tym);
						$fon=ltrim(ltrim($one['msisdn'],"254"),"0"); $type=$one['transactiontype']; $acc=clean($one['billreference']); 
						$mon=strtotime(date("Y-M",$tym)); $amnt=intval($one['amount']);
						
						if($type=="c2b-pay-bill-debit" && $amnt>0){
							$qri = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `contact`='$fon' OR `idno`='$acc'");
							if($qri){
								$cname = strtoupper($qri[0]['name']); $bid=$qri[0]['branch'];
								$qry = $db->query(1,"SELECT *FROM `branches` WHERE `id`='$bid'"); $pby=$qry[0]['paybill'];
							}
							else{ $cname=$one['msisdn']; $pby=$payb; }
							
							$sql = $db->query(2,"SELECT *FROM `$ptbl` WHERE `code`='$code'");
							if(!$sql){
								$db->insert(2,"INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','$code','$day','$amnt','$pby','$acc','0','$fon','$cname','0')");
								recordpay("$pby:$tym",$amnt,0);
							}
						}
					}
					
					$rec[$payb]=$now;
					file_put_contents("../docs/c2b.dll",json_encode($rec,1));
				}
			}
		}
	}
?>