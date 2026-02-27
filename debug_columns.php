<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;
$stbl = 'org'.$cid.'_schedule'; 
$ltbl = 'org'.$cid.'_loans';

echo 'Schedule table columns:' . PHP_EOL;
$res = $db->query(2, "DESCRIBE `$stbl`");
if ($res) {
    foreach ($res as $row) {
        echo '- ' . $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
    }
}

echo PHP_EOL . 'Loans table columns:' . PHP_EOL;
$res = $db->query(2, "DESCRIBE `$ltbl`");
if ($res) {
    foreach ($res as $row) {
        echo '- ' . $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
    }
}
?>
