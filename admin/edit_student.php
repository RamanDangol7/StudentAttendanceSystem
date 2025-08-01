<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../database.php';

// Clear any lingering modal sessions
unset($_SESSION['credential_modal']);

$id = intval($_GET['id']);
$student = $conn->query("SELECT * FROM students WHERE id = $id")->fetch_assoc();

if (!$student) {
    $_SESSION['error'] = "Student not found";
    header("Location: manage_students.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $roll_number = $conn->real_escape_string($_POST['roll_number']);
    
    $conn->query("UPDATE students SET name = '$name', roll_number = '$roll_number' WHERE id = $id");
    $_SESSION['success'] = "Student updated successfully!";
    header("Location: manage_students.php");
    exit();
}

// Safely get user info if user_id exists
$user_info = ['username' => null, 'password_changed_at' => null];
if (!empty($student['user_id'])) {
    $user_result = $conn->query("
        SELECT username, password_changed_at 
        FROM users 
        WHERE id = {$student['user_id']}
    ");
    if ($user_result) {
        $user_info = $user_result->fetch_assoc() ?? $user_info;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .password-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> Edit Student</h2>
            <a href="manage_students.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Students
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($student['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_number" class="form-control" 
                                   value="<?= htmlspecialchars($student['roll_number']) ?>" required>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update
                                </button>
                                <a href="manage_students.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Password Reset Section -->
                <div class="mt-5">
                    <h5><i class="bi bi-key"></i> Password Management</h5>
                    <hr>
                    <div class="password-info mb-3">
                        <div>Username: <strong><?= htmlspecialchars($user_info['username'] ?? 'N/A') ?></strong></div>
                        <div>Last changed: <strong>
                            <?= !empty($user_info['password_changed_at']) 
                                ? date('M j, Y H:i', strtotime($user_info['password_changed_at'])) 
                                : 'Never' ?>
                        </strong></div>
                    </div>
                    <?php if (!empty($student['user_id'])): ?>
                        <a href="reset_password.php?student_id=<?= $student['id'] ?>" 
                           class="btn btn-warning"
                           onclick="return confirm('This will generate a new temporary password. Continue?')">
                           <i class="bi bi-arrow-repeat"></i> Reset Password
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            <i class="bi bi-exclamation-triangle"></i> No account exists
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>