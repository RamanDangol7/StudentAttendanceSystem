<?php
require_once __DIR__ . '/../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isLoggedIn() && $_SESSION['role'] === 'student';
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: ../login.php");
        exit();
    }
}

function requireStudent() {
    if (!isStudent()) {
        header("Location: ../login.php");
        exit();
    }
}

function loginUser($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.id, u.username, u.password, u.role, s.id as student_id 
                          FROM users u
                          LEFT JOIN students s ON u.id = s.user_id
                          WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'student') {
                $_SESSION['student_id'] = $user['student_id'];
            }
            
            return true;
        }
    }
    
    return false;
}

function logout() {
    $_SESSION = array();
    session_destroy();
}