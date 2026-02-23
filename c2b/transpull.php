<?php
	date_default_timezone_set("Africa/Nairobi");
	// https://documenter.getpostman.com/view/1724456/SVtTy8sd?version=latest
	
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
		
		$res = (array_key_exists("access_token",json_decode($response,1))) ? json_decode($response,1)['access_token']:$response;
		return ($error) ? null:$res;
	}
	
	function registerUrl($token,$paybill,$phone,$callback){
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.safaricom.co.ke/pulltransactions/v1/register',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
			"ShortCode":"'.$paybill.'",
			"RequestType":"Pull",
			"NominatedNumber":"'.$phone.'",
			"CallBackURL":"'.$callback.'"
		}',
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'Accept-Encoding: application/json',
			'Authorization: Bearer '.$token
		  ),
		));

		$response = curl_exec($curl);
		$error = curl_errno($curl);
		curl_close($curl);
		return ($error) ? null:$response;
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
	
	$shotcodes = array(
		654280=>array("secret"=>"cvTsOYrY1v1pfPl3GAmLoLZBfUxlj85f","consumer"=>"xxNFZyCFNgKlQdgF"),
		677777=>array("secret"=>"UZIK4htHMrp3OriElgRusUUq4zfzQMW6","consumer"=>"917W7RVnjesUlm1W"),
		480701=>array("secret"=>"To7xkQRqVLwy8SFFQLKpCG5TzaGZdKEW","consumer"=>"fcLTTg5K79UFQUPb"),
		443340=>array("secret"=>"xJ3deYHzpDPuedpzuL7tdMti8nyUG0tW","consumer"=>"LAWf1gFMiUUJ0plw"),
		979706=>array("secret"=>"HLTsCJHTw0c8lglQV9HA9CjzGa9Fl5uK","consumer"=>"B2257K301ViWuHqz"),
		4072533=>array("secret"=>"yXElMKnuAmJl8SVvZ9oxjoYlyvBGyfTY","consumer"=>"x0MJUeoAEDndzLqd"),
		514484=>array("secret"=>"MCPm9o23jk5besbxRYLLz44Z3PiX0hk6","consumer"=>"8kgHppfZtJwYnDbI")
	);
	
	$payb =4072533;
	$token = getToken($shotcodes[$payb]['secret'],$shotcodes[$payb]['consumer']);
	
	if($token){
		$res = registerUrl($token,$payb,"254722295194","https://bulk.axecredits.com/pulled.php?payb=$payb");
		// $res = pullData($token,$payb,date("Y-m-d H:i:s",strtotime("Today")),date("Y-m-d H:i:s"));
		echo $res;
	}
	
	
?>