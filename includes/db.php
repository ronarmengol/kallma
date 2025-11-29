<?php
$servername = "localhost";
$username = "root";
$password = "12345"; // Default XAMPP password is empty
$dbname = "kallma";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
