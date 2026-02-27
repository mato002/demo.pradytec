<?php
// Set environment variables for localhost
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;
$ltbl = 'org'.$cid.'_loans';
$stbl = 'org'.$cid.'_schedule';

echo "Testing the problematic query...\n";

// Test the exact query from dashboard
$tdy = strtotime("Today");
$leo = $tdy;
$cond = "";

$query = "SELECT *,SUM(sd.amount) AS total,SUM(sd.paid) AS pays FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln
ON ln.id=sd.loan WHERE sd.day='$leo' AND (ln.balance>0 or ln.status>$tdy) $cond";

echo "Query: $query\n\n";

try {
    $res = $db->query(2, $query);
    echo "Query executed successfully!\n";
    if ($res) {
        echo "Results: " . count($res) . " rows\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Also test a simpler version
echo "\nTesting simpler join...\n";
$simple_query = "SELECT ln.id, sd.loan FROM `$stbl` AS sd INNER JOIN `$ltbl` AS ln ON ln.id=sd.loan LIMIT 5";
echo "Query: $simple_query\n";

try {
    $res = $db->query(2, $simple_query);
    echo "Simple query executed successfully!\n";
    if ($res) {
        echo "Results: " . count($res) . " rows\n";
        foreach ($res as $row) {
            echo "Loan ID: " . $row['id'] . " -> Schedule loan: " . $row['loan'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
