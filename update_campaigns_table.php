<?php
require_once 'config/database.php';

try {
    $db = (new Database())->connect();
    
    // Add new columns to campaigns table
    $sql = "ALTER TABLE campaigns 
            ADD COLUMN IF NOT EXISTS success_count INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS failure_count INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS error_log JSON,
            MODIFY COLUMN status ENUM('sent', 'failed', 'scheduled', 'pending', 'partially_sent') NOT NULL DEFAULT 'pending'";
    
    $db->exec($sql);
    echo "Campaigns table updated successfully!";
    
} catch(PDOException $e) {
    echo "Error updating table: " . $e->getMessage();
}
?>