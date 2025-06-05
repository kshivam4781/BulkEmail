<?php
require_once 'config/database.php';

try {
    $db = (new Database())->connect();
    
    // Read and execute the SQL file
    $sql = file_get_contents('sql/create_tables.sql');
    $db->exec($sql);
    
    echo "Tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?> 