<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

try {
    $db = (new Database())->connect();
    echo "<h2>Database Connection Test</h2>";
    echo "✅ Database connection successful<br><br>";

    // Check users table structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Users Table Structure:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    // Check if there are any users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<br>Number of users in database: " . $count . "<br>";

    if ($count > 0) {
        echo "<h3>Sample User Data (first user):</h3>";
        $stmt = $db->query("SELECT userId, name, email, passwordHash FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    }

} catch(PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?> 