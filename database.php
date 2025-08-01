<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "attendance_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    error_log("Database connected successfully");
}
?>