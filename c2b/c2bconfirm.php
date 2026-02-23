<?php

    header("Content-Type:application/json");
	
    if (!isset($_GET["ref"])){
		echo "Technical error"; exit();
    }
    if ($_GET["ref"]!='Prd*20:25!'){
		echo "Invalid authorization"; exit();
    }
    if (!$request=file_get_contents('php://input')){
		echo "Invalid input"; exit();
    }
    
	require "../core/functions.php";
	$json = json_decode($request, true);
	$db = new DBO(); $cid = CLIENT_ID;
	$ptbl = "org".$cid."_payments";
	
	if(!isset($json["FirstName"])){ exit(); }
	$type=$json['TransactionType']; $code=$json['TransID']; $day=$json['TransTime']; $amnt=$json['TransAmount']; $mon=strtotime(date("Y-M"));
	$paybill=$json['BusinessShortCode']; $acc=clean($json['BillRefNumber']); $bal=$json['OrgAccountBalance']; $fon=ltrim($json['MSISDN'],"254");
	$oname = (isset($json['MiddleName'])) ? $json['MiddleName']:""; $oname.=(isset($json['LastName'])) ? " ".$json['LastName']:"";
	$client = clean($json['FirstName']." $oname");
		
	if($code && $amnt){
		$res = $db->query(2,"SELECT *FROM `$ptbl` WHERE `code`='$code'");
		if(!$res){
    		recordpay($paybill,$amnt,$bal); $desc="Payment from $client #$code";
    		$isone = sys_constants("c2b_one_acc");
    		if($isone){ updateB2C($amnt,$desc,$code,time()); }
        }
        
		if(!is_numeric($fon)){
		    $sql = $db->query(2,"SELECT *FROM `org".$cid."_clients` WHERE `idno`='$acc' OR `contact`='".ltrim($acc,"0")."'");
		    if($sql){ $fon=$sql[0]['contact']; $client=strtoupper($sql[0]['name']); }
		}
		
		$db->execute(2,"INSERT IGNORE INTO `$ptbl` VALUES(NULL,'$mon','$code','$day','$amnt','$paybill','$acc','$bal','$fon','$client','0')");
    	if($wres=isAccount($acc)){
    	    $desc = "Payment from $client #$code"; $atp=$wres["src"];
    	    if($con=$db->mysqlcon(2)){
    			$con->autocommit(0); $con->query("BEGIN");
    			$sqr = $con->query("SELECT *FROM `$ptbl` WHERE `code`='$code' AND `status`='0' FOR UPDATE");
    			if($sqr->num_rows>0){
        			if($res=updateWallet($wres["client"],"$amnt:".$wres["type"],$atp,["desc"=>$desc,"revs"=>["tbl"=>"norev"]],0,time(),1)){
        				$con->query("UPDATE `$ptbl` SET `status`='$res' WHERE `code`='$code'");
        			}
    			}
    			$con->commit(); $con->close();
    		}
    	}
		
		if(in_array(substr($acc,0,3),["PLN","SLN"])){
		    $pid = $db->query(2,"SELECT `id` FROM `$ptbl` WHERE `code`='$code'")[0]['id']; 
		    $lid = substr($acc,3); $tbl=(substr($acc,0,3)=="SLN") ? "staff_loans$cid":"org".$cid."_loans";
		    $col = (substr($acc,0,3)=="PLN") ? "client_idno":"stid"; $lntp=($col=="stid") ? "staff":"client";
		    $sql = $db->query(2,"SELECT `$col` FROM `$tbl` WHERE `loan`='$lid' AND (balance+penalty)>0");
		    if($sql){ makepay($sql[0][$col],$pid,$amnt,$lntp,$lid); }
		}
		
		if(substr($acc,0,4)=="BFSP"){ request("https://api.cbremits.com/b2c/getc2b.php",["paydata"=>$request]); }
        request("https://bulk.pradytec.com/api/getpayment.php",["paydata"=>$request]);
        request("https://mis.pradytec.com/api/c2bpays.php",["paydata"=>$request]);
        if(explode("-",$acc)[0]=="RFK"){ request("http://rafiki.limabtech.com/backend/getuserdet.php",["paydata"=>$request]); }
	}
	
    echo '{"ResultCode":0,"ResultDesc":"Confirmation received successfully"}';
 
?>