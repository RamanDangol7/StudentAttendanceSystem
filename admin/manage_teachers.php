<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credential_generator.php';
require_once __DIR__ . '/../database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new teacher
    if (isset($_POST['add_teacher'])) {
        $name = trim($conn->real_escape_string($_POST['name']));
        $email = trim($conn->real_escape_string($_POST['email']));
        
        if (empty($name) || empty($email)) {
            $_SESSION['error'] = "Name and email are required!";
            header("Location: manage_teachers.php");
            exit;
        }

        try {
            $conn->begin_transaction();
            error_log("Starting teacher creation for: $name");

            // 1. Generate credentials
            $password = CredentialGenerator::generatePassword();
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // 2. Create FINAL username directly (skip temp step)
            $user_id = null;
            $username = 'tch_' . time() . '_' . bin2hex(random_bytes(2));
            
            // 3. Create user account
            $role = 'teacher';
            $user_sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($user_sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            
            if (!$stmt->execute()) {
                throw new Exception("User creation failed: " . $stmt->error);
            }
            
            $user_id = $conn->insert_id;
            error_log("Created user ID: $user_id");
            
            // 4. Create teacher record
            $teacher_sql = "INSERT INTO teachers (user_id, name, email) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($teacher_sql);
            $stmt->bind_param("iss", $user_id, $name, $email);
            
            if (!$stmt->execute()) {
                throw new Exception("Teacher record creation failed: " . $stmt->error);
            }
            
            // 5. Assign classes/sections
            if (!empty($_POST['classes'])) {
                foreach ($_POST['classes'] as $class_section) {
                    list($class_id, $section_id) = explode('-', $class_section);
                    $assign_sql = "INSERT INTO teacher_classes (teacher_id, class_id, section_id) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($assign_sql);
                    $stmt->bind_param("iii", $user_id, $class_id, $section_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Class assignment failed: " . $stmt->error);
                    }
                }
            }
            
            $conn->commit();
            error_log("Transaction committed successfully");
            
            // Store credentials
            $_SESSION['new_teacher_credentials'] = [
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'displayed' => false
            ];
            
            $_SESSION['success'] = "Teacher added successfully!";
            header("Location: manage_teachers.php?show_credentials=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("ERROR: " . $e->getMessage());
            $_SESSION['error'] = "Error creating teacher. Please try again.";
            header("Location: manage_teachers.php");
            exit();
        }
    } 
    
    // Delete teacher
    if (isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $user_id = $conn->query("SELECT user_id FROM teachers WHERE id = $id")->fetch_row()[0];
        
        $conn->query("DELETE FROM teacher_classes WHERE teacher_id = $user_id");
        $conn->query("DELETE FROM teachers WHERE id = $id");
        $conn->query("DELETE FROM users WHERE id = $user_id");
        
        $_SESSION['success'] = "Teacher deleted successfully!";
        header("Location: manage_teachers.php");
        exit();
    }
}

// Fetch all teachers with their credentials
$teachers = $conn->query("
    SELECT t.id, t.name, t.email, u.id as user_id, u.username, u.password_history as temp_password,
           GROUP_CONCAT(CONCAT(c.name, ' (', s.name, ')') SEPARATOR ', ') AS assigned_classes
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN teacher_classes tc ON tc.teacher_id = u.id
    LEFT JOIN classes c ON tc.class_id = c.id
    LEFT JOIN sections s ON tc.section_id = s.id
    GROUP BY t.id
");

// Fetch all classes and sections
$classes = $conn->query("SELECT * FROM classes ORDER BY sort_order");
$sections = $conn->query("SELECT * FROM sections ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers</title>
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
        .username-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .assigned-classes {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h2 class="mb-0"><i class="bi bi-person-badge"></i> Manage Teachers</h2>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Add New Teacher
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="add_teacher" class="btn btn-success w-100">
                                <i class="bi bi-check-lg"></i> Add Teacher
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5><i class="bi bi-book"></i> Assign Classes/Sections</h5>
                        <div class="row">
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <div class="col-md-3">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <?= htmlspecialchars($class['name']) ?>
                                        </div>
                                        <div class="card-body">
                                            <?php 
                                            $sections_result = $conn->query("SELECT * FROM sections ORDER BY name");
                                            while ($section = $sections_result->fetch_assoc()): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="classes[]" 
                                                           value="<?= $class['id'] ?>-<?= $section['id'] ?>" 
                                                           id="class<?= $class['id'] ?>_section<?= $section['id'] ?>">
                                                    <label class="form-check-label" for="class<?= $class['id'] ?>_section<?= $section['id'] ?>">
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
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-ul"></i> Teacher Records
            </div>
            <div class="card-body">
                <?php if ($teachers->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Assigned Classes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $teacher['id'] ?></td>
                                        <td><?= htmlspecialchars($teacher['name']) ?></td>
                                        <td><?= htmlspecialchars($teacher['email']) ?></td>
                                        <td>
                                            <div class="username-container">
                                                <span><?= htmlspecialchars($teacher['username']) ?></span>
                                                <?php if (!empty($teacher['temp_password'])): ?>
                                                    <span class="password-display" title="Initial password">
                                                        <?= htmlspecialchars($teacher['temp_password']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="assigned-classes">
                                                <?= $teacher['assigned_classes'] ?: 'Not assigned' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="edit_teacher.php?id=<?= $teacher['id'] ?>" 
                                                   class="btn btn-sm btn-warning me-2" title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="reset_password.php?user_id=<?= $teacher['user_id'] ?>" 
                                                   class="btn btn-sm btn-secondary me-2" 
                                                   title="Reset Password"
                                                   onclick="return confirm('Generate new temporary password?')">
                                                   <i class="bi bi-arrow-repeat"></i>
                                                </a>
                                                <form method="POST" onsubmit="return confirm('Delete this teacher?');">
                                                    <input type="hidden" name="delete_id" value="<?= $teacher['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No teachers found. Add your first teacher using the form above.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Credentials Modal -->
    <?php if (isset($_GET['show_credentials']) && isset($_SESSION['new_teacher_credentials']) && !$_SESSION['new_teacher_credentials']['displayed']): ?>

        
    <div class="modal fade show" id="credentialsModal" tabindex="-1" style="display: block; padding-right: 15px;" aria-modal="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Teacher Credentials</h5>
                    <button type="button" class="btn-close" onclick="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name:</label>
                        <input type="text" class="form-control" value="<?= $_SESSION['new_teacher_credentials']['name'] ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email:</label>
                        <input type="text" class="form-control" value="<?= $_SESSION['new_teacher_credentials']['email'] ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username:</label>
                        <input type="text" class="form-control" value="<?= $_SESSION['new_teacher_credentials']['username'] ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Temporary Password:</label>
                        <input type="text" class="form-control" value="<?= $_SESSION['new_teacher_credentials']['password'] ?>" readonly>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Save these credentials - they cannot be retrieved later!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                    <button type="button" class="btn btn-primary" onclick="copyCredentials()">
                        <i class="bi bi-clipboard"></i> Copy All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function closeModal() {
        window.location.href = 'manage_teachers.php';
    }

    function copyCredentials() {
        const text = `TEACHER CREDENTIALS\n\nName: <?= $_SESSION['new_teacher_credentials']['name'] ?>\nEmail: <?= $_SESSION['new_teacher_credentials']['email'] ?>\nUsername: <?= $_SESSION['new_teacher_credentials']['username'] ?>\nPassword: <?= $_SESSION['new_teacher_credentials']['password'] ?>`;
        navigator.clipboard.writeText(text).then(() => {
            alert('Credentials copied to clipboard!');
        });
    }
    </script>
    <?php 
    $_SESSION['new_teacher_credentials']['displayed'] = true;
    endif; 
    ?>

   <?php 

if (isset($_SESSION['new_teacher_credentials']['displayed'])) {
    unset($_SESSION['new_teacher_credentials']);
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>