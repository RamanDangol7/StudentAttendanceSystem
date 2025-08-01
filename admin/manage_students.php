<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../includes/student_account.php';

// Clear credential session if not coming from add_student
if (!isset($_POST['add_student']) && (!isset($_GET['show_credentials']) || isset($_POST['delete_id']))) {
    unset($_SESSION['new_student_credentials']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../database.php';
    
    // Add new student
    if (isset($_POST['add_student'])) {
        $name = trim($conn->real_escape_string($_POST['name']));
        $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
        
        if (empty($name)) {
            $_SESSION['error'] = "Name is required!";
            header("Location: manage_students.php");
            exit;
        }

        try {
            $conn->query("INSERT INTO students (name, class_id, section_id) VALUES ('$name', $class_id, $section_id)");
            $student_id = $conn->insert_id;
            
            $credentials = StudentAccount::createWithCredentials($conn, $student_id);
            
            $_SESSION['new_student_credentials'] = [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'roll_number' => $credentials['roll_number'],
                'displayed' => false
            ];
            
            $_SESSION['success'] = "Student added successfully!";
            header("Location: manage_students.php?show_credentials=1");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header("Location: manage_students.php");
            exit();
        }
    }
    
    // Delete student
    if (isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $conn->query("DELETE FROM attendance WHERE student_id = $id");
        $conn->query("DELETE FROM students WHERE id = $id");

        // Trigger renumbering
        $conn->query("SET @counter = 0");
        $conn->query("UPDATE students SET order_index = (@counter:=@counter+1) ORDER BY name");
        $conn->query("UPDATE students SET roll_number = LPAD(order_index, 3, '0')");
        
        $_SESSION['success'] = "Student deleted successfully";
        header("Location: manage_students.php");
        exit();
    }
}

// Search and Pagination
require_once __DIR__ . '/../database.php';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch classes and sections for dropdown
$classes = $conn->query("SELECT * FROM classes ORDER BY LENGTH(name), name");
$sections = $conn->query("SELECT * FROM sections ORDER BY name");

// Base query
$sql = "SELECT s.*, u.username, c.name AS class_name, sec.name AS section_name 
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id ";
$count_sql = "SELECT COUNT(*) FROM students ";

// Add search filter
if (!empty($search)) {
    $filter = "WHERE s.name LIKE '%$search%' OR s.roll_number LIKE '%$search%' ";
    $sql .= $filter;
    $count_sql .= $filter;
}

// Complete queries
$sql .= "ORDER BY s.name LIMIT $per_page OFFSET $offset";
$students = $conn->query($sql);
$total_students = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_students / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .has-credentials { color: #28a745; }
        #searchResults { display: none; }
        .dashboard-btn { margin-right: 15px; }
        #credentialsModal .form-control[readonly] { background-color: #fff; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Back to Dashboard Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="dashboard.php" class="btn btn-outline-primary dashboard-btn">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h2 class="mb-0"><i class="bi bi-people-fill"></i> Manage Students</h2>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-search"></i> Student Management</span>
                <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#addStudentForm">
                    <i class="bi bi-plus-lg"></i> Add New
                </button>
            </div>
            <div class="card-body">
                <!-- Live Search Form -->
                <div class="mb-4">
                    <div class="input-group">
                        <input type="text" id="liveSearch" class="form-control" 
                               placeholder="Start typing to search..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="button" id="searchBtn">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="manage_students.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                    <div id="searchResults" class="mt-2"></div>
                </div>

                <!-- Add Student Form -->
                <div class="collapse" id="addStudentForm">
                    <form method="POST" class="border p-3 rounded bg-light">
                        <h5><i class="bi bi-person-plus"></i> Add New Student</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php while ($class = $classes->fetch_assoc()): ?>
                                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select name="section_id" class="form-select" required>
                                    <option value="">Select Section</option>
                                    <?php while ($section = $sections->fetch_assoc()): ?>
                                        <option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="add_student" class="btn btn-success w-100">
                                    <i class="bi bi-check-lg"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Students List -->
        <div class="card" id="studentsTable">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul"></i> Student Records</span>
                <span class="badge bg-light text-dark">
                    <?= $total_students ?> student(s) found
                </span>
            </div>
            <div class="card-body">
                <?php if ($students->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Roll Number</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Account</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= $row['roll_number'] ?></td>
                                    <td><?= htmlspecialchars($row['class_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['section_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($row['username']): ?>
                                            <span class="has-credentials"><i class="bi bi-check-circle-fill"></i> Active</span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit_student.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" style="display:inline" 
                                              onsubmit="return confirm('Delete this student?');">
                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No students found. 
                        <?php if (!empty($search)): ?>
                            Try a different search term.
                        <?php else: ?>
                            Add your first student using the form above.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Credentials Modal -->
    <div class="modal fade" id="credentialsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Student Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Roll Number:</label>
                        <input type="text" class="form-control" id="dispRollNumber" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username:</label>
                        <input type="text" class="form-control" id="dispUsername" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password:</label>
                        <input type="text" class="form-control" id="dispPassword" readonly>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Save these credentials - they cannot be retrieved later!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="copyCreds()">
                        <i class="bi bi-clipboard"></i> Copy All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Live Search Functionality
    document.getElementById('liveSearch').addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            fetchSearchResults(query);
        } else {
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('studentsTable').style.display = '';
        }
    });

    document.getElementById('searchBtn').addEventListener('click', function() {
        const query = document.getElementById('liveSearch').value.trim();
        if (query) {
            fetchSearchResults(query);
        }
    });

    function fetchSearchResults(query) {
        fetch(`search_students.php?query=${encodeURIComponent(query)}`)
            .then(response => response.text())
            .then(data => {
                const resultsDiv = document.getElementById('searchResults');
                resultsDiv.innerHTML = data;
                resultsDiv.style.display = 'block';
                document.getElementById('studentsTable').style.display = 'none';
                
                // Reattach event listeners to new delete buttons
                document.querySelectorAll('#searchResults form[method="POST"]').forEach(form => {
                    form.onsubmit = function(e) {
                        if (!confirm('Delete this student?')) {
                            e.preventDefault();
                        }
                    };
                });
            });
    }

    // Credentials Modal
    <?php if (isset($_GET['show_credentials']) && isset($_SESSION['new_student_credentials']) && !$_SESSION['new_student_credentials']['displayed']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = new bootstrap.Modal(document.getElementById('credentialsModal'));
            document.getElementById('dispRollNumber').value = '<?= $_SESSION['new_student_credentials']['roll_number'] ?>';
            document.getElementById('dispUsername').value = '<?= $_SESSION['new_student_credentials']['username'] ?>';
            document.getElementById('dispPassword').value = '<?= $_SESSION['new_student_credentials']['password'] ?>';
            modal.show();
            
            // Mark as displayed
            <?php $_SESSION['new_student_credentials']['displayed'] = true; ?>
            
            // Clean URL
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('show_credentials');
                window.history.replaceState({}, '', url);
            }
        });

        function copyCreds() {
            const text = `Roll Number: ${document.getElementById('dispRollNumber').value}
Username: ${document.getElementById('dispUsername').value}
Password: ${document.getElementById('dispPassword').value}`;
            
            navigator.clipboard.writeText(text).then(function() {
                alert('Credentials copied to clipboard!');
            });
        }
    <?php endif; ?>

    // Auto-expand add form if error exists
    <?php if (isset($_POST['add_student']) && (isset($_SESSION['error']) || isset($_SESSION['new_student_credentials']))): ?>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Collapse(document.getElementById('addStudentForm')).show();
        });
    <?php endif; ?>
    </script>
</body>
</html>