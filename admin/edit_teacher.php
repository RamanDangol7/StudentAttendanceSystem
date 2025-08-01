<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: manage_teachers.php");
    exit();
}

$teacher_id = intval($_GET['id']);

// Fetch teacher details
$teacher = $conn->query("
    SELECT t.*, u.username, u.password_history as temp_password 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = $teacher_id
")->fetch_assoc();

if (!$teacher) {
    $_SESSION['error'] = "Teacher not found!";
    header("Location: manage_teachers.php");
    exit();
}

// Fetch assigned classes
$assigned_classes = $conn->query("
    SELECT CONCAT(class_id, '-', section_id) as class_section 
    FROM teacher_classes 
    WHERE teacher_id = {$teacher['user_id']}
")->fetch_all(MYSQLI_ASSOC);

$assigned = array_column($assigned_classes, 'class_section');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($conn->real_escape_string($_POST['name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    
    try {
        $conn->begin_transaction();
        
        // Update teacher details
        $conn->query("UPDATE teachers SET name = '$name', email = '$email' WHERE id = $teacher_id");
        
        // Update class assignments
        $conn->query("DELETE FROM teacher_classes WHERE teacher_id = {$teacher['user_id']}");
        
        if (isset($_POST['classes'])) {
            foreach ($_POST['classes'] as $class_section) {
                list($class_id, $section_id) = explode('-', $class_section);
                $conn->query("INSERT INTO teacher_classes (teacher_id, class_id, section_id) VALUES (
                    {$teacher['user_id']},
                    $class_id,
                    $section_id
                )");
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Teacher updated successfully!";
        header("Location: manage_teachers.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating teacher: " . $e->getMessage();
        header("Location: edit_teacher.php?id=$teacher_id");
        exit();
    }
}

// Fetch all classes and sections
$classes = $conn->query("SELECT * FROM classes ORDER BY sort_order");
$sections = $conn->query("SELECT * FROM sections ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .password-display {
            font-size: 0.8rem;
            color: #dc3545;
            background: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="manage_teachers.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Teachers
            </a>
            <h2 class="mb-0"><i class="bi bi-person-badge"></i> Edit Teacher</h2>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Teacher Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($teacher['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($teacher['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($teacher['username']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Initial Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       value="<?= htmlspecialchars($teacher['temp_password']) ?>" readonly>
                                <a href="reset_password.php?user_id=<?= $teacher['user_id'] ?>" 
                                   class="btn btn-outline-secondary" 
                                   onclick="return confirm('Generate new temporary password?')">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Class Assignments
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php while ($class = $classes->fetch_assoc()): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-header">
                                        <?= htmlspecialchars($class['name']) ?>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $sections_result = $conn->query("SELECT * FROM sections ORDER BY name");
                                        while ($section = $sections_result->fetch_assoc()): 
                                            $value = $class['id'] . '-' . $section['id'];
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="classes[]" value="<?= $value ?>" 
                                                       id="class<?= $value ?>"
                                                       <?= in_array($value, $assigned) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="class<?= $value ?>">
                                                    <?= htmlspecialchars($section['name']) ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
                <a href="manage_teachers.php" class="btn btn-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>