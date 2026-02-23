<?php
/**
 * Install tables for MFS demo – run once when databases exist but have no tables.
 * Open in browser: http://localhost/mfs/demo.pradtec/install_tables.php
 *
 * Creates core tables and inserts minimal data so you can log in.
 * Default login: username "admin" (or use contact/email), password = today's date like Feb23@2026
 */

error_reporting(E_ALL);
require __DIR__ . "/core/functions.php";

$db = new DBO();
$cid = CLIENT_ID;

// Prevent running if tables already exist
$hasStaff = $db->istable(2, "org" . $cid . "_staff");
$hasConfig = $db->istable(1, "config");
if ($hasStaff && $hasConfig) {
    die("<p><strong>Tables already exist.</strong> Delete tables first in phpMyAdmin if you want to re-run install.</p>");
}

$done = [];
$err  = [];

function run($db, $dbNum, $sql, &$done, &$err) {
    $res = $db->execute($dbNum, $sql);
    if ($res) { $done[] = $sql; return true; }
    $err[] = $sql; return false;
}

// ---------- Database 1: mfi_core ----------
$core = [
    "CREATE TABLE IF NOT EXISTS `config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `settings` mediumtext NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `client` (`client`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `setting` varchar(128) NOT NULL,
        `value` mediumtext,
        PRIMARY KEY (`id`),
        KEY `client_setting` (`client`,`setting`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `clients` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `contact` varchar(64) NOT NULL,
        `url` varchar(255) DEFAULT NULL,
        `status` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `default_tables` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(64) NOT NULL,
        `fields` mediumtext NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `created_tables` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `name` varchar(64) NOT NULL,
        `fields` mediumtext,
        `datasrc` text,
        `reuse` text,
        PRIMARY KEY (`id`),
        KEY `client_name` (`client`,`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `staff_groups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `sgroup` varchar(64) NOT NULL,
        `roles` text NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `useroles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role` varchar(128) NOT NULL,
        `groups` varchar(64) NOT NULL,
        `cluster` varchar(32) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `bulksettings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `defs` mediumtext,
        `routes` mediumtext,
        `platforms` mediumtext,
        `users` mediumtext,
        `levels` mediumtext,
        `notify` int(11) DEFAULT NULL,
        `status` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($core as $q) { run($db, 1, $q, $done, $err); }

// ---------- Database 2: mfi_defined ----------
$defined = [
    "CREATE TABLE IF NOT EXISTS `branches` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `branch` varchar(255) NOT NULL,
        `paybill` varchar(32) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_staff` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `contact` varchar(64) NOT NULL DEFAULT '',
        `idno` varchar(64) NOT NULL DEFAULT '',
        `email` varchar(255) NOT NULL DEFAULT '',
        `gender` varchar(32) NOT NULL DEFAULT 'male',
        `jobno` varchar(32) NOT NULL DEFAULT '',
        `branch` varchar(32) NOT NULL DEFAULT '0',
        `roles` text NOT NULL,
        `access_level` varchar(32) NOT NULL DEFAULT 'hq',
        `config` mediumtext NOT NULL,
        `position` varchar(64) NOT NULL DEFAULT '',
        `entry_date` date DEFAULT NULL,
        `leaves` int(11) NOT NULL DEFAULT 0,
        `status` int(11) NOT NULL DEFAULT 0,
        `time` int(11) NOT NULL DEFAULT 0,
        `login_username` varchar(128) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_clients` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `contact` varchar(64) NOT NULL DEFAULT '',
        `idno` varchar(64) NOT NULL DEFAULT '',
        `cdef` mediumtext,
        `branch` varchar(32) NOT NULL DEFAULT '0',
        `loan_officer` varchar(32) DEFAULT NULL,
        `cycles` int(11) NOT NULL DEFAULT 0,
        `creator` int(11) DEFAULT NULL,
        `status` int(11) NOT NULL DEFAULT 0,
        `time` int(11) NOT NULL DEFAULT 0,
        `client_group` varchar(32) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_loans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` varchar(64) NOT NULL,
        `loan_product` varchar(64) DEFAULT NULL,
        `client_idno` varchar(64) DEFAULT NULL,
        `phone` varchar(64) DEFAULT NULL,
        `branch` varchar(32) DEFAULT NULL,
        `loan_officer` int(11) DEFAULT NULL,
        `amount` decimal(18,2) NOT NULL DEFAULT 0,
        `tid` varchar(64) DEFAULT NULL,
        `duration` int(11) NOT NULL DEFAULT 0,
        `disbursement` int(11) NOT NULL DEFAULT 0,
        `expiry` int(11) NOT NULL DEFAULT 0,
        `penalty` decimal(18,2) NOT NULL DEFAULT 0,
        `paid` decimal(18,2) NOT NULL DEFAULT 0,
        `balance` decimal(18,2) NOT NULL DEFAULT 0,
        `status` int(11) NOT NULL DEFAULT 0,
        `time` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `loan` varchar(64) NOT NULL,
        `amount` decimal(18,2) NOT NULL DEFAULT 0,
        `client` varchar(64) DEFAULT NULL,
        `time` int(11) NOT NULL DEFAULT 0,
        `status` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_loantemplates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `client` int(11) NOT NULL,
        `status` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `disbursements{$cid}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `loan` varchar(64) NOT NULL,
        `amount` decimal(18,2) NOT NULL DEFAULT 0,
        `time` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `org{$cid}_targets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `branch` int(11) NOT NULL,
        `officer` int(11) NOT NULL,
        `month` int(11) NOT NULL,
        `year` int(11) NOT NULL,
        `results` text,
        `data` text,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($defined as $q) { run($db, 2, $q, $done, $err); }

// ---------- Database 3: mfi_accounts ----------
$accounts = [
    "CREATE TABLE IF NOT EXISTS `accounts{$cid}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `account` varchar(255) NOT NULL,
        `type` varchar(64) NOT NULL,
        `balance` varchar(64) NOT NULL DEFAULT '0',
        `level` varchar(32) NOT NULL DEFAULT '0',
        `status` int(11) NOT NULL DEFAULT 0,
        `def` text,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `wallets{$cid}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client` int(11) NOT NULL,
        `type` varchar(32) NOT NULL,
        `balance` varchar(64) NOT NULL DEFAULT '0',
        `status` int(11) NOT NULL DEFAULT 0,
        `time` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `walletrans{$cid}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tid` varchar(64) NOT NULL,
        `wallet` int(11) NOT NULL,
        `transaction` varchar(64) NOT NULL,
        `amount` int(11) NOT NULL DEFAULT 0,
        `details` varchar(255) DEFAULT NULL,
        `time` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($accounts as $q) { run($db, 3, $q, $done, $err); }

// ---------- Seed data ----------
$clientsRow = $db->query(1, "SELECT id FROM `clients` WHERE `id`='$cid'");
if (!$clientsRow) {
    run($db, 1, "INSERT INTO `clients` (`id`,`company`,`email`,`contact`,`url`,`status`) VALUES ($cid,'Demo MFI','admin@demo.local','254700000000','',0)", $done, $err);
}

$configRow = $db->query(1, "SELECT id FROM `config` WHERE `client`='$cid'");
if (!$configRow) {
    $settings = [
        'company' => 'Demo MFI',
        'email'   => 'admin@demo.local',
        'contact' => '254700000000',
        'address' => 'Demo Address',
        'apikey'  => '',
        'appname' => '',
        'senderid'=> '',
        'logo'    => 'none',
        'smds'    => encrypt(json_encode(['mfs']), 'syszn' . $cid),
    ];
    $setJson = addslashes(json_encode($settings, JSON_UNESCAPED_UNICODE));
    run($db, 1, "INSERT INTO `config` (`client`,`settings`) VALUES ($cid,'$setJson')", $done, $err);
}

run($db, 1, "INSERT IGNORE INTO `settings` (`client`,`setting`,`value`) VALUES ($cid,'createtables','1')", $done, $err);

$userolesRow = $db->query(1, "SELECT id FROM `useroles` LIMIT 1");
if (!$userolesRow) {
    run($db, 1, "INSERT INTO `useroles` (`role`,`groups`,`cluster`) VALUES ('View Dashboard','Management','mfs'),('Manage Staff','HR','mfs'),('Manage Clients','Operations','mfs'),('Manage Loans','Operations','mfs'),('Manage Payments','Operations','mfs')", $done, $err);
}

$branchRow = $db->query(2, "SELECT id FROM `branches` LIMIT 1");
if (!$branchRow) {
    run($db, 2, "INSERT INTO `branches` (`branch`,`paybill`) VALUES ('Head Office','')", $done, $err);
}

$staffRow = $db->query(2, "SELECT id FROM `org{$cid}_staff` LIMIT 1");
if (!$staffRow) {
    $tym = time();
    $pass = encrypt(date("Md@Y", $tym), date("YMd-his", $tym));
    $cnf = json_encode(['key' => $pass, 'region' => 0, 'lastreset' => 0, 'profile' => 'none', 'payroll' => ['include' => 0, 'paye' => 0]], JSON_UNESCAPED_UNICODE);
    $cnf = addslashes($cnf);
    $roles = $db->query(1, "SELECT id FROM `useroles`");
    $roleList = $roles ? implode(",", array_column($roles, 'id')) : "1,2,3,4,5";
    run($db, 2, "INSERT INTO `org{$cid}_staff` (`name`,`contact`,`idno`,`email`,`gender`,`jobno`,`branch`,`roles`,`access_level`,`config`,`position`,`entry_date`,`leaves`,`status`,`time`,`login_username`) VALUES ('admin','254700000000','1238','admin@demo.local','male','ADMIN001','1','$roleList','hq','$cnf','super user','" . date('Y-m-d') . "',0,0,$tym,'admin')", $done, $err);
}

$accRow = $db->query(3, "SELECT id FROM `accounts{$cid}` LIMIT 1");
if (!$accRow) {
    run($db, 3, "INSERT INTO `accounts{$cid}` (`account`,`type`,`balance`,`level`,`status`,`def`) VALUES ('Clients Wallet','liability','0','1','0','0')", $done, $err);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Tables</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 24px; background: #f5f5f5; }
        h1 { color: #074E8F; }
        .ok { color: #0a0; }
        .fail { color: #c00; }
        pre { background: #eee; padding: 10px; overflow-x: auto; font-size: 12px; }
        .login { background: #fff; padding: 16px; border: 1px solid #ddd; margin-top: 16px; max-width: 420px; }
    </style>
</head>
<body>
    <h1>Install tables</h1>
    <p>Created/ran: <strong><?php echo count($done); ?></strong> statements.</p>
    <?php if (count($err)): ?>
        <p class="fail">Errors (<?php echo count($err); ?>):</p>
        <pre><?php echo htmlspecialchars(implode("\n", array_slice($err, 0, 10))); ?></pre>
    <?php endif; ?>
    <div class="login">
        <h2>Login</h2>
        <p>Use the app login page. Default user:</p>
        <ul>
            <li><strong>Username:</strong> <code>admin</code> (or <code>admin@demo.local</code> or contact number)</li>
            <li><strong>Password:</strong> today’s date like <code><?php echo date("Md@Y"); ?></code> (e.g. Feb23@2026)</li>
        </ul>
        <p>App URL: <a href="validate.php">validate.php</a> or your MFS login page.</p>
    </div>
</body>
</html>
