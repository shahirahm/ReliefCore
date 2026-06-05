<?php
/**
 * Disaster Relief Camp & Volunteer Coordination System
 * Database Connection File (db_connect.php)
 * 
 * This file establishes a secure connection to the MySQL database
 * using PDO (PHP Data Objects). PDO is preferred over MySQLi as it
 * is object-oriented, supports multiple databases, and protects against
 * SQL injection through prepared statements.
 */

// Database Credentials
$host     = 'localhost';         // Database host (usually localhost)
$dbname   = 'disaster_relief_db'; // Name of our database
$username = 'root';              // Default MySQL username in XAMPP/WAMP
$password = '';                  // Default MySQL password (empty in XAMPP/WAMP)
$charset  = 'utf8mb4';           // Character set for supporting special symbols and characters

// Data Source Name (DSN) tells PDO which database driver and details to use
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Connection options for safety and ease of use
$options = [
    // 1. Throw exceptions if there are SQL or connection errors.
    // This allows us to handle errors in our try-catch block.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // 2. Fetch data as associative arrays by default (e.g., $row['full_name']).
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // 3. Disable emulation of prepared statements to use real prepared statements.
    // This prevents SQL injection attacks and is extremely secure.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Attempt to connect using the DSN, username, password, and configuration options
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Un-comment the line below if you want to verify connection success during setup.
    // echo "Connected successfully!"; 
} catch (PDOException $e) {
    // If connection fails, stop script execution and show a helpful error message.
    die("Database Connection Failed: " . $e->getMessage());
}
?>
