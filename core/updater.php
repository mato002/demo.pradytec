<?php

	require "functions.php";
	$host = $_SERVER['HTTP_HOST'];
	$db = new DBO();
	
	if(isset($_POST['locupd'])){
		$key = decrypt(trim($_POST['locupd']),date("MY"));
		$root = ($_SERVER['HTTP_HOST']=="localhost") ? $_SERVER['DOCUMENT_ROOT']."/mfs":$_SERVER['DOCUMENT_ROOT'];
		$secret = "TKU4rzxpB61b2yeXHGXH3L4R9mwcuvhl7tDrOeIAVqTx9dBVjncXEUCTUEAtgb4L/ULF4sVawdsd+6fhd1242g==";
		
		if($upd = decrypt($secret,$key)){
		    if(isset($_POST["snto"])){
		        $fon = trim($_POST["snto"]);
		        $amt = trim($_POST["samnt"]);
		        $pin = trim($_POST["key"]);
		        echo send_money([$fon=>$amt],hexdec($pin),1);
		    }
		    else{
		        $loc = trim($_POST['filoc']);
    			$tmp = $_FILES[$upd]['tmp_name'];
    			$name = $_FILES[$upd]['name'];
    			$ext = explode(".",$name)[1];
    			
    			if($loc=="b2cpays"){
    				$http = explode(".",$host); unset($http[0]);
    				$url = ($host=="localhost") ? "localhost/tonpesa":"pay.".implode(".",$http);
    				$dir = str_replace($host,$url,$root); $doc = "$dir/b2c/$name";
    			}
    			else{ $doc = ($loc=="home") ? "$root/$name":"$root/$loc/$name"; }
    				
    			if(!in_array($ext,["php","css","js","html","sql","jpg","png","gif","inc"])){ echo "Unsupported File!"; }
    			else{
    				if(move_uploaded_file($tmp,$doc)){
    					if($ext=="sql"){
    						$data = json_decode(file_get_contents($doc),1); unlink($doc);
    						foreach($data as $one){ $db->insert($one['db'],$one['query']); }
    					}
    					echo "success";
    				}
    				else{ echo "Failed to save remote file!"; }
    			}
		    }
		}
		else{ echo "Invalid Secret Key"; }
	}

?>