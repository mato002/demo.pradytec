<?php
// Set environment variables for localhost
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;

echo "=== DISABLING PASSWORD RESET REQUIREMENT ===\n\n";

// Check current password reset setting
$res = $db->query(1, "SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='passreset'");
if ($res) {
    $current = $res[0]['value'];
    echo "Current password reset setting: $current\n";
} else {
    echo "No password reset setting found, will create one\n";
}

// Disable password reset requirement
if ($res) {
    $updateSql = "UPDATE `settings` SET `value`='0' WHERE `client`='$cid' AND `setting`='passreset'";
    if ($db->execute(1, $updateSql)) {
        echo "✅ Password reset requirement disabled\n";
    } else {
        echo "❌ Failed to update setting\n";
    }
} else {
    // Create the setting if it doesn't exist
    $insertSql = "INSERT INTO `settings` (`client`, `setting`, `value`) VALUES ('$cid', 'passreset', '0')";
    if ($db->execute(1, $insertSql)) {
        echo "✅ Password reset requirement created and disabled\n";
    } else {
        echo "❌ Failed to create setting\n";
    }
}

// Verify the change
$res = $db->query(1, "SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='passreset'");
if ($res) {
    $newValue = $res[0]['value'];
    echo "\nNew password reset setting: $newValue\n";
    echo "0 = Disabled, 1 = Enabled\n";
}

// Also check for any other password-related settings
echo "\n=== OTHER PASSWORD SETTINGS ===\n";
$res = $db->query(1, "SELECT *FROM `settings` WHERE `client`='$cid' AND (`setting` LIKE '%pass%' OR `setting` LIKE '%reset%')");
if ($res) {
    foreach ($res as $row) {
        echo "- " . $row['setting'] . ": " . $row['value'] . "\n";
    }
} else {
    echo "No other password/reset settings found\n";
}

echo "\n=== EFFECT ===\n";
echo "Users will no longer be forced to change password on first login\n";
echo "New staff accounts can use any valid password without reset requirement\n";

?>
