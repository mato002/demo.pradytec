<?php
// Create databases script
try {
    // Connect to MySQL as root without password
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL successfully\n";
    
    // Create the three databases
    $databases = ['mfi_core', 'mfi_defined', 'mfi_accounts'];
    
    foreach ($databases as $db) {
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
            echo "Database '$db' created or already exists\n";
        } catch (PDOException $e) {
            echo "Error creating database '$db': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nDatabase setup completed!\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "Please make sure MySQL is running and accessible with root user and no password\n";
}
?>
