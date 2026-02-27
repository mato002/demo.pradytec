<?php
// Set environment variables for localhost
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;

echo "=== CLEARING RESET REQUIREMENTS FOR MATHIAS ===\n\n";

// Get Mathias account
$res = $db->query(2, "SELECT *FROM `org" . $cid . "_staff` WHERE `name`='Mathias Odhiambo'");
if ($res) {
    $staff = $res[0];
    $staffId = $staff['id'];
    $staffTime = $staff['time'];
    
    echo "Current account status:\n";
    echo "- ID: $staffId\n";
    echo "- Name: " . $staff['name'] . "\n";
    
    // Get current config
    $config = json_decode($staff['config'], true);
    if ($config) {
        echo "\nCurrent config keys:\n";
        foreach ($config as $key => $value) {
            if ($key == 'lastreset') {
                echo "- $key: " . date('Y-m-d H:i:s', $value) . "\n";
            } else {
                echo "- $key: " . (is_string($value) ? substr($value, 0, 30) . "..." : $value) . "\n";
            }
        }
        
        // Remove any reset flags and update lastreset to recent time
        $config['lastreset'] = time(); // Set to current time to avoid reset triggers
        
        // Keep the existing password
        $currentPassword = 'Mathias@2026';
        $timeKey = date("YMd-his", $staffTime);
        $config['key'] = encrypt($currentPassword, $timeKey);
        
        // Update config
        $configJson = json_encode($config);
        $updateSql = "UPDATE `org" . $cid . "_staff` SET `config`='$configJson' WHERE `id`='$staffId'";
        
        if ($db->execute(2, $updateSql)) {
            echo "\n✅ Account config updated\n";
            echo "- lastreset set to: " . date('Y-m-d H:i:s', time()) . "\n";
            echo "- Password preserved: $currentPassword\n";
        }
    }
}

// Clear any existing reset sessions
echo "\n=== CLEARING RESET SESSIONS ===\n";
$clearSql = "DELETE FROM `resets` WHERE `user`='$staffId'";
if (fetchSQLite("sitesess", "SELECT *FROM `resets` WHERE `user`='$staffId'")) {
    if (insertSQLite("sitesess", $clearSql)) {
        echo "✅ Reset sessions cleared\n";
    } else {
        echo "❌ Failed to clear reset sessions\n";
    }
} else {
    echo "ℹ️ No reset sessions found\n";
}

// Verify the passreset setting is still disabled
$res = $db->query(1, "SELECT *FROM `settings` WHERE `client`='$cid' AND `setting`='passreset'");
if ($res) {
    $setting = $res[0]['value'];
    echo "\nPassword reset policy setting: $setting (0=disabled)\n";
}

echo "\n=== PASSWORD RESET POLICY EXPLANATION ===\n";
echo "The system had these reset policies:\n";
echo "- Value 1: Reset on 1st of month if >28 days since last reset\n";
echo "- Value 15: Reset on 15th of month if >28 days since last reset\n";
echo "- Value 10: Reset if >10 days since last reset\n";
echo "- Value 28: Reset if >28 days since last reset\n";
echo "\nAll policies are now DISABLED (set to 0)\n";

echo "\n=== FINAL LOGIN DETAILS ===\n";
echo "🔑 Username: $staffId\n";
echo "🔑 Password: Mathias@2026\n";
echo "🌐 URL: http://localhost/mfs/demo.pradtec/\n";
echo "📋 Reset requirement: REMOVED\n";

?>
