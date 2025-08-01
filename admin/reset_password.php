<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database.php';

if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    header("Location: ../index.php");
    exit();
}

// Get user type and ID
$user_type = $_GET['user_type'] ?? ''; // 'student' or 'teacher'
$user_id = intval($_GET['user_id'] ?? 0);

// Validate user type
if (!in_array($user_type, ['student', 'teacher'])) {
    $_SESSION['error'] = "Invalid user type";
    header("Location: manage_$user_type" . "s.php");
    exit();
}

// Verify user exists
$user = $conn->query("SELECT * FROM users WHERE id = $user_id AND role = '$user_type'")->fetch_assoc();
if (!$user) {
    $_SESSION['error'] = ucfirst($user_type) . " not found";
    header("Location: manage_$user_type" . "s.php");
    exit();
}

// Generate temp password
$temp_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
$hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

// Update password and force reset
$conn->query("UPDATE users SET 
    password = '$hashed_password',
    password_changed_at = NULL,
    force_password_change = 1
    WHERE id = $user_id");

// Redirect back with success message
$_SESSION['success'] = "Password reset for " . ucfirst($user_type) . "! Temporary password: $temp_password";
header("Location: manage_$user_type" . "s.php");
exit();