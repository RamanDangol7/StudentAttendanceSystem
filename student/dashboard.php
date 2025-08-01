<?php
require_once '../includes/auth.php';
if ($_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

require_once '../database.php';

// Get student info - using user_id to match students.user_id
$student_id = $_SESSION['user_id'];
$student = $conn->query("
    SELECT s.*, c.name AS class_name, sec.name AS section_name 
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

// Check if student exists
if (!$student) {
    die("Student record not found!");
}

// Current month stats with default values
$current_month = date('Y-m');
$monthly_stats = $conn->query("
    SELECT 
        COALESCE(SUM(status = 'present'), 0) AS present,
        COALESCE(SUM(status = 'absent'), 0) AS absent,
        COALESCE(COUNT(*), 0) AS total
    FROM attendance 
    WHERE student_id = {$student['id']}
    AND date LIKE '$current_month%'
")->fetch_assoc();

// Overall stats with default values
$overall_stats = $conn->query("
    SELECT 
        COALESCE(SUM(status = 'present'), 0) AS present,
        COALESCE(SUM(status = 'absent'), 0) AS absent,
        COALESCE(COUNT(*), 0) AS total
    FROM attendance 
    WHERE student_id = {$student['id']}
")->fetch_assoc();

// This week stats
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_stats = $conn->query("
    SELECT 
        COALESCE(SUM(status = 'present'), 0) AS present,
        COALESCE(SUM(status = 'absent'), 0) AS absent
    FROM attendance 
    WHERE student_id = {$student['id']}
    AND date >= '$week_start'
")->fetch_assoc();

// Recent attendance (last 30 days)
$recent_attendance = $conn->query("
    SELECT date, status 
    FROM attendance 
    WHERE student_id = {$student['id']} 
    ORDER BY date DESC 
    LIMIT 30
");

// Sample inspirational quotes
$quotes = [
    ["Education is the most powerful weapon which you can use to change the world.", "Nelson Mandela"],
    ["The beautiful thing about learning is that no one can take it away from you.", "B.B. King"],
    ["Success is the sum of small efforts, repeated day in and day out.", "Robert Collier"],
    ["Don't let what you cannot do interfere with what you can do.", "John Wooden"],
    ["The expert in anything was once a beginner.", "Helen Hayes"]
];
$random_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
        
        /* Student avatar */
        .student-avatar {
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
        
        /* Attendance styles */
        .attendance-badge {
            width: 20px;
            height: 20px;
            display: inline-block;
            border-radius: 50%;
            margin-right: 5px;
        }
        .present-badge {
            background-color: #28a745;
        }
        .absent-badge {
            background-color: #dc3545;
        }
        .progress {
            height: 25px;
        }
        .calendar-day {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
            border-radius: 50%;
            font-size: 0.8rem;
        }
        .present-day {
            background-color: #d4edda;
            color: #155724;
        }
        .absent-day {
            background-color: #f8d7da;
            color: #721c24;
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
                <div class="student-avatar">
                    <?= strtoupper(substr($student['name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="mb-3" style="font-weight: 700;">Welcome, <?= htmlspecialchars($student['name']) ?>!</h1>
                    <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 1.2rem;">
                        <i class="bi bi-stars"></i> Keep up the great work!
                    </p>
                    <div class="d-flex flex-wrap">
                        <span class="info-badge">
                            <i class="bi bi-journal-bookmark"></i> 
                            Class: <?= htmlspecialchars($student['class_name'] ?? 'Not assigned') ?>
                        </span>
                        <span class="info-badge">
                            <i class="bi bi-collection"></i> 
                            Section: <?= htmlspecialchars($student['section_name'] ?? 'Not assigned') ?>
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
                <i class="bi bi-person-circle"></i> My Dashboard
                <small class="text-muted">(Roll No: <?= htmlspecialchars($student['roll_number'] ?? 'N/A') ?>)</small>
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
            <div class="col-md-4 mb-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h5 class="text-success">Current Month</h5>
                        <h2><?= $monthly_stats['total'] > 0 ? round(($monthly_stats['present'] / $monthly_stats['total']) * 100) : 0 ?>%</h2>
                        <small><?= $monthly_stats['present'] ?> Present / <?= $monthly_stats['absent'] ?> Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h5 class="text-primary">Overall</h5>
                        <h2><?= $overall_stats['total'] > 0 ? round(($overall_stats['present'] / $overall_stats['total']) * 100) : 0 ?>%</h2>
                        <small><?= $overall_stats['present'] ?> Present / <?= $overall_stats['absent'] ?> Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="text-info">This Week</h5>
                        <h2><?= $week_stats['present'] ?> / <?= ($week_stats['present'] + $week_stats['absent']) ?></h2>
                        <small>Days attended</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Bars -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-graph-up"></i> Attendance Progress</h5>
                <div class="mb-3">
                    <label>Current Month (<?= date('F') ?>)</label>
                    <div class="progress">
                        <div class="progress-bar bg-success" 
                             style="width: <?= $monthly_stats['total'] > 0 ? ($monthly_stats['present'] / $monthly_stats['total']) * 100 : 0 ?>%">
                        </div>
                    </div>
                </div>
                <div>
                    <label>Overall Attendance</label>
                    <div class="progress">
                        <div class="progress-bar bg-primary" 
                             style="width: <?= $overall_stats['total'] > 0 ? ($overall_stats['present'] / $overall_stats['total']) * 100 : 0 ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Recent Attendance (Last 30 Days)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                <td><?= date('l', strtotime($record['date'])) ?></td>
                                <td>
                                    <span class="attendance-badge <?= $record['status'] === 'present' ? 'present-badge' : 'absent-badge' ?>"></span>
                                    <?= ucfirst($record['status']) ?>
                                </td>
                                <td><?= $record['status'] === 'present' ? 'On time' : 'Not marked' ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($recent_attendance->num_rows === 0): ?>
                            <tr>
                                <td colspan="4" class="text-center">No attendance records found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-width Bottom Section -->
    <div class="full-width-section bottom-section">
        <div class="main-container">
            <!-- Daily Inspiration Quote -->
            <div class="quote-section text-center">
                <p class="mb-1"><i class="bi bi-quote"></i> <?= $random_quote[0] ?> <i class="bi bi-quote"></i></p>
                <small>- <?= $random_quote[1] ?></small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>