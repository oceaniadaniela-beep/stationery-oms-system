<?php
// db_connect.php
// Central database connection file for OMS project

$host = "localhost";      // XAMPP default host
$user = "root";           // Default MySQL username
$pass = "";               // Default MySQL password (leave empty unless you set one)
$dbname = "oms_db";       // Your database name

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Optional: set charset for consistent encoding
$conn->set_charset("utf8mb4");
?>
