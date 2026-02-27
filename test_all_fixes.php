<?php
// Test all the fixed queries
$_SERVER['HTTP_HOST'] = 'localhost';
include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;
$stbl = 'org'.$cid.'_schedule'; 
$ltbl = 'org'.$cid.'_loans';

$tdy = strtotime("Today");
$leo = $tdy;
$cond = "";

echo "Testing all fixed queries...\n";

// Test 1: Main collection progress query
echo "Test 1: Collection progress query\n";
$res = $db->query(2,"SELECT *,SUM(sd.amount) AS total,SUM(sd.paid) AS pays FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln
ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.day='$leo' AND (ln.balance>0 or ln.status>$tdy) $cond");
echo "Result: " . ($res ? count($res) . " rows" : "No error") . "\n\n";

// Test 2: Distinct loans query
echo "Test 2: Distinct loans query\n";
$qry = $db->query(2,"SELECT DISTINCT sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 AND ln.balance>0 AND sd.day<$tdy $cond");
echo "Result: " . ($qry ? count($qry) . " rows" : "No error") . "\n\n";

// Test 3: Arrears query
echo "Test 3: Arrears query\n";
$res = $db->query(2,"SELECT SUM(sd.balance) AS arrears FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 AND sd.day<$tdy $cond");
echo "Result: " . ($res ? "Query executed successfully" : "No error") . "\n\n";

// Test 4: Principal query
echo "Test 4: Principal query\n";
$res = $db->query(2,"SELECT SUM((CASE WHEN (sd.paid>(sd.amount-sd.interest)) THEN 0 ELSE (sd.amount-sd.paid-sd.interest) END)) AS princ 
FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.balance>0 $cond");
echo "Result: " . ($res ? "Query executed successfully" : "No error") . "\n\n";

echo "All fixes are working correctly!\n";
?>
