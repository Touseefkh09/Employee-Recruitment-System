<?php
// Database Configuration
$host = 'localhost';
$dbname = 'recruitment_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    
    error_log("Database connection failed: " . $e->getMessage());
    die("Unable to connect to database. Please try again later or contact support.");
}
?>
