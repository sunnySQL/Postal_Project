<?php
require_once __DIR__ . '/app_config.php';

// Database connection parameters — copy this file to db_connect.php and fill in your credentials.
$host     = 'localhost';
$username = 'your_db_username';
$password = 'your_db_password';
$database = 'postal';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    if (!empty($app_config['debug'])) {
        die('Connection failed: ' . $conn->connect_error);
    }
    error_log('Database connection failed: ' . $conn->connect_error);
    die('Unable to connect to the database. Please try again later.');
}
