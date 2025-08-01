<?php
require_once '../includes/auth.php';
require_once '../database.php';

if ($_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords don't match!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters!";
    } else {
        // Verify current password
        $user_id = $_SESSION['user_id'];
        $user = $conn->query("SELECT password FROM users WHERE id = $user_id")->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET 
                password = '$hashed_password',
                password_changed_at = NOW()
                WHERE id = $user_id");
            
            $success = "Password changed successfully!";
        } else {
            $error = "Current password is incorrect!";
        }
    }
}
?>

<!-- HTML Form -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5><i class="bi bi-shield-lock"></i> Change Password</h5>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key"></i> Change Password
            </button>
        </form>
    </div>
</div>