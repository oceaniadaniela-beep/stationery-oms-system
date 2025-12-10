<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oms_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname") === TRUE) {
    echo "<p>✅ Database '$dbname' created or already exists.</p>";
} else {
    die("<p>❌ Error creating database: " . $conn->error . "</p>");
}

// Select the database
$conn->select_db($dbname);

// Create admin_users table
$table_sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100)
)";

if ($conn->query($table_sql) === TRUE) {
    echo "<p>✅ Table 'admin_users' created or already exists.</p>";
} else {
    die("<p>❌ Error creating table: " . $conn->error . "</p>");
}

// Insert default admin user
$admin_email = "daniela@gmail.com";
$admin_password = password_hash("daniela123", PASSWORD_DEFAULT);
$admin_name = "Administrator";

// Check if user exists
$check = $conn->prepare("SELECT * FROM admin_users WHERE email=?");
$check->bind_param("s", $admin_email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows == 0) {
    $insert = $conn->prepare("INSERT INTO admin_users (email, password, name) VALUES (?, ?, ?)");
    $insert->bind_param("sss", $admin_email, $admin_password, $admin_name);

    if ($insert->execute()) {
        echo "<p>✅ Default admin user added successfully!</p>";
    } else {
        echo "<p>❌ Error inserting admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ Admin user already exists — no changes made.</p>";
}

echo "<hr><p>🎉 Setup completed. You can now <a href='login.php'>Login Here</a>.</p>";

$conn->close();
?>
