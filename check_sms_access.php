<?php
// Set environment variables for localhost
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

include 'core/functions.php';
$db = new DBO(); 
$cid = CLIENT_ID;

echo "=== CHECKING MATHIAS SMS ACCESS ===\n\n";

// Get Mathias account
$res = $db->query(2, "SELECT *FROM `org" . $cid . "_staff` WHERE `name`='Mathias Odhiambo'");
if ($res) {
    $staff = $res[0];
    $staffId = $staff['id'];
    $roles = explode(",", $staff['roles']);
    $accessLevel = $staff['access_level'];
    
    echo "User Details:\n";
    echo "- ID: $staffId\n";
    echo "- Name: " . $staff['name'] . "\n";
    echo "- Access Level: $accessLevel\n";
    echo "- Total Roles: " . count($roles) . "\n\n";
    
    // Check SMS-related permissions
    echo "SMS Permissions Check:\n";
    $smsPermissions = [
        'access bulksms' => in_array("access bulksms", $roles),
        'send sms' => in_array("send sms", $roles),
        'create sms template' => in_array("create sms template", $roles),
        'view sms templates' => in_array("view sms templates", $roles),
        'view sms logs' => in_array("view sms logs", $roles),
        'create sms schedule' => in_array("create sms schedule", $roles)
    ];
    
    foreach ($smsPermissions as $perm => $has) {
        echo "- $perm: " . ($has ? "✅ YES" : "❌ NO") . "\n";
    }
    
    // Check admin permissions
    echo "\nAdmin Permissions Check:\n";
    $adminPerms = [
        'admin' => in_array("admin", $roles),
        'hq access' => ($accessLevel == "hq"),
        'can see API settings' => (in_array("admin", $roles) || $accessLevel == "hq")
    ];
    
    foreach ($adminPerms as $perm => $has) {
        echo "- $perm: " . ($has ? "✅ YES" : "❌ NO") . "\n";
    }
    
    echo "\n=== HOW TO ACCESS SMS FEATURES ===\n";
    echo "1. Look in the LEFT SIDEBAR menu\n";
    echo "2. Find 'Bulk SMS' section (with envelope icon)\n";
    echo "3. Click to expand the menu\n";
    echo "4. You should see these options:\n";
    
    if ($smsPermissions['send sms']) echo "   - Send/Schedule SMS\n";
    if ($smsPermissions['create sms template']) echo "   - Create SMS Template\n";
    if ($smsPermissions['view sms templates']) echo "   - SMS Templates\n";
    if ($smsPermissions['view sms logs']) echo "   - SMS Logs\n";
    if (defined('SMS_TOPUP') && SMS_TOPUP) echo "   - Topup Wallet\n";
    echo "   - SMS Schedules\n";
    
    if ($adminPerms['can see API settings']) {
        echo "   - ⚙️ API Settings (in orange color)\n";
    }
    
    echo "\n=== API SETTINGS LOCATION ===\n";
    echo "File: mfs/admin/sms_config.php\n";
    echo "URL: /mfs/admin/sms_config.php\n";
    echo "Access: Click '⚙️ API Settings' in Bulk SMS menu\n";
    
} else {
    echo "❌ Mathias account not found\n";
}

echo "\n=== IF YOU DON'T SEE THE MENU ===\n";
echo "1. Try refreshing the page (F5)\n";
echo "2. Check if you're logged in as the right user\n";
echo "3. Look for 'Bulk SMS' in the left sidebar\n";
echo "4. If still not visible, try accessing directly:\n";
echo "   http://localhost/mfs/demo.pradtec/mfs/admin/sms_config.php\n";

?>
