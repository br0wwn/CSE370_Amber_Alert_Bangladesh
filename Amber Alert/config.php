<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amber_alert";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Base URL for the application (change this to your public URL in production)
$base_url = "http://localhost/Amber Alert";
?> 