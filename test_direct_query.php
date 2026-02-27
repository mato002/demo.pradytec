<?php
// Clear any potential caches and test the query directly
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clear opcode cache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
}

$_SERVER['HTTP_HOST'] = 'localhost';
include 'core/functions.php';

echo "Testing direct query execution...\n";

$db = new DBO(); 
$cid = CLIENT_ID;
$stbl = 'org'.$cid.'_schedule'; 
$ltbl = 'org'.$cid.'_loans';

$tdy = strtotime("Today");
$leo = $tdy;
$cond = "";

// Test the exact query from dashboard line 57-58
$query = "SELECT *,SUM(sd.amount) AS total,SUM(sd.paid) AS pays FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln
ON ln.id=CAST(sd.loan AS UNSIGNED) WHERE sd.day='$leo' AND (ln.balance>0 or ln.status>$tdy) $cond";

echo "Executing query: " . $query . "\n";

try {
    $res = $db->query(2, $query);
    if ($res === null) {
        echo "Query executed successfully (null result expected for empty tables)\n";
    } else {
        echo "Query returned " . count($res) . " rows\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
?>
