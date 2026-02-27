<?php
session_start();
ob_start();
if(!isset($_SESSION['myacc'])){ exit(); }
$sid = substr(hexdec($_SESSION['myacc']),6);

include "../core/functions.php";
$db = new DBO(); $cid = CLIENT_ID;

# Check if user has admin rights
$me = staffInfo($sid);
$perms = getroles(explode(",",$me['roles']));
if(!in_array("admin", $perms) && $me['access_level'] != "hq"){
    echo "<div style='padding:20px;text-align:center;color:red'>Access Denied! Admin rights required.</div>";
    exit();
}

# Save settings
if(isset($_POST['save_sms_config'])){
    $apikey = trim($_POST['apikey']);
    $appname = trim($_POST['appname']);
    $senderid = trim($_POST['senderid']);
    
    # Get current settings
    $res = $db->query(1, "SELECT *FROM `config` WHERE `client`='$cid'");
    if($res){
        $current = json_decode($res[0]['settings'], true);
        $current['apikey'] = $apikey;
        $current['appname'] = $appname;
        $current['senderid'] = $senderid;
        
        $new_settings = json_encode($current);
        $db->execute(1, "UPDATE `config` SET `settings`='$new_settings' WHERE `client`='$cid'");
        $success = "SMS API settings updated successfully!";
    }
}

# Load current settings
$res = $db->query(1, "SELECT *FROM `config` WHERE `client`='$cid'");
$settings = json_decode($res[0]['settings'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>SMS API Configuration</title>
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info-box { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h3 style="color: #191970; text-align: center;">SMS API Configuration</h3>
        <hr>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h5>Current API Endpoints:</h5>
            <ul>
                <li><strong>Send SMS:</strong> <?php echo SMS_SURL; ?></li>
                <li><strong>Check Balance:</strong> <?php echo SMS_BURL; ?></li>
                <li><strong>Payment/Top-up:</strong> <?php echo SMS_PURL; ?></li>
            </ul>
            <p><small>To change endpoints, edit <code>core/constants.php</code></small></p>
        </div>
        
        <form method="post">
            <div class="form-group">
                <label>API Key:</label>
                <input type="text" name="apikey" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['apikey']); ?>" 
                       placeholder="Enter your SMS API key">
                <small>Required for authentication with SMS provider</small>
            </div>
            
            <div class="form-group">
                <label>App Name:</label>
                <input type="text" name="appname" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['appname']); ?>" 
                       placeholder="Application name">
                <small>Your application identifier with the SMS provider</small>
            </div>
            
            <div class="form-group">
                <label>Sender ID:</label>
                <input type="text" name="senderid" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['senderid']); ?>" 
                       placeholder="Sender ID">
                <small>The name that appears as sender (max 11 characters)</small>
            </div>
            
            <div class="form-group">
                <button type="submit" name="save_sms_config" class="btn btn-primary">Save Settings</button>
                <a href="../mfs/bulksms.php" class="btn btn-secondary">Back to SMS</a>
            </div>
        </form>
        
        <div class="info-box" style="margin-top: 30px;">
            <h5>Test Connection:</h5>
            <p>After updating settings, you can test by checking your SMS balance in the main SMS module.</p>
            <p><strong>Current Balance:</strong> <?php echo smsbalance(); ?></p>
        </div>
    </div>
</body>
</html>
