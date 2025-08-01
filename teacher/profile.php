<?php
require_once '../includes/auth.php';
if ($_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../database.php';

// Get teacher details
$teacher = $conn->query("
    SELECT t.*, u.username 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = {$_SESSION['user_id']}
")->fetch_assoc();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Verify current password
    $user = $conn->query("SELECT * FROM users WHERE id = {$_SESSION['user_id']}")->fetch_assoc();
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect";
    }
    
    // Validate username if changing
    if (!empty($new_username)) {
        if (strlen($new_username) < 4) {
            $errors[] = "Username must be at least 4 characters";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $new_username, $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Username already taken";
            }
        }
    }
    
    // Validate password if changing
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords don't match";
        }
    }
    
    // If no errors, update credentials
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update username if changed
            if (!empty($new_username)) {
                $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $new_username, $_SESSION['user_id']);
                $stmt->execute();
                $_SESSION['username'] = $new_username;
            }
            
            // Update password if changed
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                $stmt->execute();
                
                // Update password history (assuming password_history stores hashes separated by comma)
                $history = $user['password_history'] ? $user['password_history'] . ',' . $hashed_password : $hashed_password;
                $conn->query("UPDATE users SET password_history = '$history' WHERE id = {$_SESSION['user_id']}");
            }
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .profile-card {
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
        }
        .avatar {
            width: 100px;
            height: 100px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card profile-card">
            <div class="profile-header text-center">
                <div class="avatar">
                    <?= strtoupper(substr($teacher['name'], 0, 1)) ?>
                </div>
                <h2><?= htmlspecialchars($teacher['name']) ?></h2>
                <p class="mb-0">Teacher</p>
            </div>
            
            <div class="card-body p-4">
                <?php if ($success || isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['success'] ?? 'Profile updated successfully!' ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($teacher['username']) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_username" class="form-label">New Username (leave blank to keep current)</label>
                        <input type="text" class="form-control" id="new_username" name="new_username" 
                               placeholder="Enter new username" minlength="4">
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password*</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" 
                               required placeholder="Enter your current password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               placeholder="Enter new password (min 8 chars)" minlength="8">
                        <div class="form-text">Password must be at least 8 characters</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm new password">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                alert("New passwords don't match!");
                e.preventDefault();
            }
        });
    </script>
</body>
</html>