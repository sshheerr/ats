<?php
// Database configuration
$host = 'localhost'; // Database host (default for WAMP)
$username = 'root';  // Database username (default for WAMP)
$password = '';      // Database password (default for WAMP is empty)
$database = '';      // Leave empty for now, the database is selected dynamically

// Create a connection
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Display a success message (remove in production)
echo "Connected successfully to MySQL server.<br>";
?>
