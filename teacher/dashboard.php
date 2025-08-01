<?php
require_once '../includes/auth.php';
if ($_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../database.php';

// Get teacher details and assigned classes/sections
$teacher_info = $conn->query("
    SELECT t.name, t.email, 
           GROUP_CONCAT(DISTINCT c.id) AS class_ids,
           GROUP_CONCAT(DISTINCT s.id) AS section_ids,
           GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS classes,
           GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS sections
    FROM teachers t
    LEFT JOIN teacher_classes tc ON t.user_id = tc.teacher_id
    LEFT JOIN classes c ON tc.class_id = c.id
    LEFT JOIN sections s ON tc.section_id = s.id
    WHERE t.user_id = {$_SESSION['user_id']}
")->fetch_assoc();

// Handle date selection
$selected_date = $_GET['date'] ?? date('Y-m-d');
$max_date = date('Y-m-d');

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['attendance'] as $student_id => $status) {
        $student_id = intval($student_id);
        $status = $status === 'present' ? 'present' : 'absent';
        
        $conn->query("REPLACE INTO attendance (student_id, date, status) 
                     VALUES ($student_id, '$selected_date', '$status')");
    }
    
    $_SESSION['success'] = "Attendance saved successfully!";
    header("Location: dashboard.php?date=$selected_date");
    exit();
}

// Fetch existing attendance for this teacher's students
$existing_attendance = [];
$attendance_query = "
    SELECT a.student_id, a.status 
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN teacher_classes tc ON s.class_id = tc.class_id AND s.section_id = tc.section_id
    WHERE a.date = '$selected_date'
    AND tc.teacher_id = {$_SESSION['user_id']}";

if (!empty($teacher_info['class_ids'])) {
    $attendance_query = "
        SELECT student_id, status 
        FROM attendance 
        WHERE date = '$selected_date'
        AND student_id IN (
            SELECT id FROM students 
            WHERE class_id IN ({$teacher_info['class_ids']}) 
            AND section_id IN ({$teacher_info['section_ids']})
        )";
}

$result = $conn->query($attendance_query);
while ($row = $result->fetch_assoc()) {
    $existing_attendance[$row['student_id']] = $row['status'];
}

// Fetch only students assigned to this teacher's classes/sections
$students_query = "
    SELECT s.* 
    FROM students s
    JOIN teacher_classes tc ON s.class_id = tc.class_id AND s.section_id = tc.section_id
    WHERE tc.teacher_id = {$_SESSION['user_id']}
    ORDER BY s.name";

if (!empty($teacher_info['class_ids'])) {
    $students_query = "
        SELECT * FROM students 
        WHERE class_id IN ({$teacher_info['class_ids']}) 
        AND section_id IN ({$teacher_info['section_ids']})
        ORDER BY name";
}

$students = $conn->query($students_query);

// Get stats (only for assigned students)
$stats_query = "
    SELECT 
        SUM(a.status = 'present') AS present,
        SUM(a.status = 'absent') AS absent
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN teacher_classes tc ON s.class_id = tc.class_id AND s.section_id = tc.section_id
    WHERE a.date = '$selected_date'
    AND tc.teacher_id = {$_SESSION['user_id']}";

if (!empty($teacher_info['class_ids'])) {
    $stats_query = "
        SELECT 
            SUM(status = 'present') AS present,
            SUM(status = 'absent') AS absent
        FROM attendance 
        WHERE date = '$selected_date'
        AND student_id IN (
            SELECT id FROM students 
            WHERE class_id IN ({$teacher_info['class_ids']}) 
            AND section_id IN ({$teacher_info['section_ids']})
        )";
}

$stats = $conn->query($stats_query)->fetch_assoc();
$present = $stats['present'] ?? 0;
$absent = $stats['absent'] ?? 0;
$total_attendance = $present + $absent;

// Sample inspirational quotes
$quotes = [
    ["Education is the most powerful weapon which you can use to change the world.", "Nelson Mandela"],
    ["The beautiful thing about learning is that no one can take it away from you.", "B.B. King"],
    ["Teaching is the greatest act of optimism.", "Colleen Wilcox"],
    ["Education is not preparation for life; education is life itself.", "John Dewey"]
];
$random_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Full-width sections */
        .full-width-section {
            width: 100vw;
            position: relative;
            left: 50%;
            right: 50%;
            margin-left: -50vw;
            margin-right: -50vw;
        }
        
        /* Greeting section */
        .greeting-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        /* Bottom section */
        .bottom-section {
            background-color: #f8f9fa;
            padding: 3rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e9ecef;
        }
        
        /* Main content container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        /* Teacher avatar */
        .teacher-avatar {
            width: 100px;
            height: 100px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-right: 25px;
            font-weight: bold;
        }
        
        /* Info badges */
        .info-badge {
            background-color: rgba(255,255,255,0.2);
            border-radius: 25px;
            padding: 8px 20px;
            margin-right: 12px;
            margin-bottom: 12px;
            display: inline-flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        /* Quick links */
        .quick-links {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        /* Attendance styles */
        .btn-attendance {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            color: #495057 !important;
        }
        .btn-present.active {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
        }
        .btn-absent.active {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        .attendance-card {
            transition: all 0.3s;
            margin-bottom: 25px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        /* Quote section */
        .quote-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Full-width Greeting Section -->
    <div class="full-width-section greeting-section">
        <div class="main-container">
            <div class="d-flex align-items-center">
                <div class="teacher-avatar">
                    <?= strtoupper(substr($teacher_info['name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="mb-3" style="font-weight: 700;">Welcome back, <?= htmlspecialchars($teacher_info['name']) ?>!</h1>
                    <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 1.2rem;">
                        <i class="bi bi-stars"></i> Ready to make today's learning experience amazing!
                    </p>
                    <div class="d-flex flex-wrap">
                        <span class="info-badge">
                            <i class="bi bi-journal-bookmark"></i> 
                            Classes: <?= htmlspecialchars($teacher_info['classes'] ?? 'Not assigned') ?>
                        </span>
                        <span class="info-badge">
                            <i class="bi bi-collection"></i> 
                            Sections: <?= htmlspecialchars($teacher_info['sections'] ?? 'Not assigned') ?>
                        </span>
                        <span class="info-badge">
                            <i class="bi bi-calendar-check"></i> 
                            Today: <?= date('l, F j, Y') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Dashboard Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0" style="font-weight: 600;">
                <i class="bi bi-clipboard-check"></i> Attendance Management
                <small class="text-muted">(<?= htmlspecialchars($teacher_info['classes']) ?>)</small>
            </h3>
            <a href="../logout.php" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5><i class="bi bi-check-circle"></i> Present</h5>
                        <h2><?= $present ?></h2>
                        <small><?= $students->num_rows ?> total students</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5><i class="bi bi-x-circle"></i> Absent</h5>
                        <h2><?= $absent ?></h2>
                        <small>
                            <?php if ($total_attendance > 0): ?>
                                <?= round(($present/$total_attendance)*100) ?>% attendance
                            <?php else: ?>
                                No attendance taken
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Selection -->
        <div class="card mb-4 attendance-card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-calendar"></i> Select Attendance Date
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <input type="date" id="attendanceDate" name="date" class="form-control" 
                               max="<?= $max_date ?>" value="<?= $selected_date ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Load Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Box -->
        <div class="card mb-4 attendance-card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-search"></i> Search Students
            </div>
            <div class="card-body">
                <input type="text" id="studentSearch" placeholder="Type student name..." class="form-control">
            </div>
        </div>

        <!-- Attendance Form -->
        <form method="POST">
            <div class="card attendance-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <span>
                        <i class="bi bi-list-check"></i> 
                        Attendance for <?= date('F j, Y', strtotime($selected_date)) ?>
                        (<?= htmlspecialchars($teacher_info['classes']) ?>)
                    </span>
                    <button type="submit" class="btn btn-sm btn-light">
                        <i class="bi bi-save"></i> Save Attendance
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($students->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Roll Number</th>
                                        <th>Class/Section</th>
                                        <th class="status-header">Attendance Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): 
                                        $class_section = $conn->query("
                                            SELECT c.name AS class_name, s.name AS section_name 
                                            FROM classes c, sections s 
                                            WHERE c.id = {$student['class_id']} 
                                            AND s.id = {$student['section_id']}
                                        ")->fetch_assoc();
                                    ?>
                                    <tr class="student-row">
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <td><?= $student['roll_number'] ?></td>
                                        <td><?= $class_section['class_name'] ?? '' ?>/<?= $class_section['section_name'] ?? '' ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="attendance[<?= $student['id'] ?>]" 
                                                       id="present_<?= $student['id'] ?>" 
                                                       value="present" 
                                                       autocomplete="off"
                                                       <?= isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] === 'present' ? 'checked' : '' ?>>
                                                <label class="btn btn-sm btn-attendance btn-present <?= (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] === 'present') ? 'active' : '' ?>" for="present_<?= $student['id'] ?>">
                                                    <i class="bi bi-check-circle"></i> Present
                                                </label>

                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="attendance[<?= $student['id'] ?>]" 
                                                       id="absent_<?= $student['id'] ?>" 
                                                       value="absent" 
                                                       autocomplete="off"
                                                       <?= isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] === 'absent' ? 'checked' : '' ?>>
                                                <label class="btn btn-sm btn-attendance btn-absent <?= (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] === 'absent') ? 'active' : '' ?>" for="absent_<?= $student['id'] ?>">
                                                    <i class="bi bi-x-circle"></i> Absent
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No students assigned to your classes/sections.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Full-width Bottom Section -->
    <div class="full-width-section bottom-section">
        <div class="main-container">
            <div class="quick-links">
                <h4 class="mb-4 text-center"><i class="bi bi-lightning"></i> Quick Actions</h4>
                <div class="row">
                    <div class="col-md-3">
                        <a href="manage_attendance.php" class="text-decoration-none">
                            <div class="quick-link-item">
                                <div class="quick-link-icon">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <h5>Attendance</h5>
                                <p class="text-muted small">View all records</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="quick-link-item">
                                <div class="quick-link-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <h5>Reports</h5>
                                <p class="text-muted small">Generate insights</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="resources.php" class="text-decoration-none">
                            <div class="quick-link-item">
                                <div class="quick-link-icon">
                                    <i class="bi bi-folder"></i>
                                </div>
                                <h5>Resources</h5>
                                <p class="text-muted small">Teaching materials</p>
                            </div>
                        </a>
                    </div>
                   <div class="col-md-3">
    <a href="profile.php" class="text-decoration-none">
        <div class="quick-link-item">
            <div class="quick-link-icon">
                <i class="bi bi-person"></i>
            </div>
            <h5>Profile</h5>
            <p class="text-muted small">Update credentials</p>
        </div>
    </a>
</div>
                </div>
            </div>

            <!-- Daily Inspiration Quote -->
            <div class="quote-section text-center mt-4">
                <p class="mb-1"><i class="bi bi-quote"></i> <?= $random_quote[0] ?> <i class="bi bi-quote"></i></p>
                <small>- <?= $random_quote[1] ?></small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Student search functionality
        document.getElementById('studentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                row.style.display = name.includes(searchTerm) ? '' : 'none';
            });
        });

        // Auto-select present if no selection exists
        document.querySelectorAll('.btn-group').forEach(group => {
            if (!group.querySelector('input:checked')) {
                group.querySelector('input[value="present"]').checked = true;
                group.querySelector('.btn-present').classList.add('active');
            }
        });
        
        // Add active class when buttons are clicked
        document.querySelectorAll('.btn-check').forEach(radio => {
            radio.addEventListener('change', function() {
                const label = document.querySelector(`label[for="${this.id}"]`);
                label.classList.toggle('active', this.checked);
                
                // Toggle sibling button's active state
                const siblingLabel = label.parentNode.querySelector(`label:not([for="${this.id}"])`);
                siblingLabel.classList.remove('active');
            });
        });
    </script>
</body>
</html>