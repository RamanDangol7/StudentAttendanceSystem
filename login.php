<?php
session_start();
require 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Basic validation
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: index.php");
        exit();
    }

    try {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ? AND role = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Plaintext comparison (temporary)
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'teacher':
                        header("Location: teacher/dashboard.php");
                        break;
                    case 'student':
                        header("Location: student/dashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            }
        }
        
        $_SESSION['error'] = "Invalid credentials!";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "System error. Please try again.";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}