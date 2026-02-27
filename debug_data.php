<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;
$stbl = 'org'.$cid.'_schedule'; 
$ltbl = 'org'.$cid.'_loans';

echo 'Sample data from schedule table:' . PHP_EOL;
$res = $db->query(2, "SELECT * FROM `$stbl` LIMIT 5");
if ($res) {
    foreach ($res as $row) {
        echo 'ID: ' . $row['id'] . ', Loan: ' . $row['loan'] . ', Officer: ' . $row['officer'] . PHP_EOL;
    }
}

echo PHP_EOL . 'Sample data from loans table:' . PHP_EOL;
$res = $db->query(2, "SELECT id, client, amount FROM `$ltbl` LIMIT 5");
if ($res) {
    foreach ($res as $row) {
        echo 'ID: ' . $row['id'] . ', Client: ' . $row['client'] . ', Amount: ' . $row['amount'] . PHP_EOL;
    }
}
?>
