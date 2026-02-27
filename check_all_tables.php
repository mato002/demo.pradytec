<?php
// Check all tables in all databases
try {
    // Connect to MySQL as root without password
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking all tables in MFS databases...\n\n";
    
    $databases = ['mfi_core', 'mfi_defined', 'mfi_accounts'];
    $totalTables = 0;
    
    foreach ($databases as $db) {
        echo "=== Database: $db ===\n";
        
        try {
            $pdo->exec("USE `$db`");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                echo "No tables found.\n";
            } else {
                echo "Tables found (" . count($tables) . "):\n";
                foreach ($tables as $table) {
                    echo "  - $table\n";
                    $totalTables++;
                }
            }
        } catch (PDOException $e) {
            echo "Error accessing database '$db': " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "=== Summary ===\n";
    echo "Total tables across all databases: $totalTables\n";
    
    if ($totalTables > 0) {
        echo "\nTables are created and should be visible in phpMyAdmin.\n";
        echo "If you don't see them in phpMyAdmin, try:\n";
        echo "1. Refresh the phpMyAdmin page\n";
        echo "2. Click on the database name on the left sidebar\n";
        echo "3. Check if you're looking at the correct database server\n";
    } else {
        echo "\nNo tables found. You need to run the installation script.\n";
    }
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
