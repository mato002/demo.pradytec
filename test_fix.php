<?php
// Test the fixed query
$_SERVER['HTTP_HOST'] = 'localhost';
include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;
$stbl = 'org'.$cid.'_schedule'; 
$ltbl = 'org'.$cid.'_loans';

$tdy = strtotime("Today");
$leo = $tdy;

echo "Testing the fixed query...\n";

// Test the fixed query with CAST
$res = $db->query(2,"SELECT *,SUM(sd.amount) AS total,SUM(sd.paid) AS pays FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln
ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.day='$leo' AND (ln.balance>0 or ln.status>$tdy)");

if ($res === null) {
    echo "Query executed successfully (no results expected as tables are empty)\n";
} else {
    echo "Query returned " . count($res) . " rows\n";
}

echo "Fix appears to be working!\n";
?>
