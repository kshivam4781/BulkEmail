<?php
require_once 'config/database.php';

try {
    $db = (new Database())->connect();
    
    // Create contactlists table
    $db->exec("CREATE TABLE IF NOT EXISTS contactlists (
        ListID INT AUTO_INCREMENT PRIMARY KEY,
        UserID INT NOT NULL,
        ListName VARCHAR(255) NOT NULL,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (UserID) REFERENCES users(userId) ON DELETE CASCADE
    )");
    
    // Create contacts table
    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        ContactID INT AUTO_INCREMENT PRIMARY KEY,
        ListID INT NOT NULL,
        FullName VARCHAR(255),
        Email VARCHAR(255) NOT NULL,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ListID) REFERENCES contactlists(ListID) ON DELETE CASCADE
    )");
    
    // Create email_tracking table
    $db->exec("CREATE TABLE IF NOT EXISTS email_tracking (
        TrackingID INT AUTO_INCREMENT PRIMARY KEY,
        CampaignID INT NOT NULL,
        RecipientEmail VARCHAR(255) NOT NULL,
        FirstOpenedAt TIMESTAMP NULL,
        LastOpenedAt TIMESTAMP NULL,
        OpenCount INT DEFAULT 0,
        UserAgent TEXT,
        IPAddress VARCHAR(45),
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (CampaignID) REFERENCES campaigns(CampaignID) ON DELETE CASCADE
    )");
    
    echo "Tables created successfully!";
    
} catch(PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?> 