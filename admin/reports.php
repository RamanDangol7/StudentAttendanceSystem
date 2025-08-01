<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../database.php';

// Fetch all classes and sections for dropdowns - CHANGED TO USE sort_order
$classes = $conn->query("SELECT * FROM classes ORDER BY sort_order, name");
$sections = $conn->query("SELECT * FROM sections ORDER BY name");

// Filter parameters
$class_id = $_GET['class_id'] ?? null;
$section_id = $_GET['section_id'] ?? null;
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');      // Default: today

// Build the base query
$query = "
    SELECT s.name, s.roll_number, c.name AS class_name, sec.name AS section_name, a.date, a.status 
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE a.date BETWEEN '$start_date' AND '$end_date'
";

// Add class filter if selected
if (!empty($class_id)) {
    $query .= " AND s.class_id = " . intval($class_id);
}

// Add section filter if selected
if (!empty($section_id)) {
    $query .= " AND s.section_id = " . intval($section_id);
}

$query .= " ORDER BY a.date DESC, s.name";

$report = $conn->query($query);

// Calculate stats with same filters
$stats_query = "
    SELECT 
        COUNT(*) AS total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.date BETWEEN '$start_date' AND '$end_date'
";

if (!empty($class_id)) {
    $stats_query .= " AND s.class_id = " . intval($class_id);
}

if (!empty($section_id)) {
    $stats_query .= " AND s.section_id = " . intval($section_id);
}

$stats = $conn->query($stats_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .attendance-card {
            transition: transform 0.2s;
        }
        .attendance-card:hover {
            transform: translateY(-5px);
        }
        .present-badge {
            background-color: #28a745;
        }
        .absent-badge {
            background-color: #dc3545;
        }
        .dashboard-btn {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Back to Dashboard Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="dashboard.php" class="btn btn-outline-primary dashboard-btn">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h2 class="mb-0">
                <i class="bi bi-clipboard-data"></i> Attendance Reports
            </h2>
            <button class="btn btn-primary" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel"></i> Export
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card attendance-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">TOTAL RECORDS</h6>
                                <h2 class="mb-0"><?php echo $stats['total_records']; ?></h2>
                            </div>
                            <i class="bi bi-journal-text display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card attendance-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">PRESENT</h6>
                                <h2 class="mb-0"><?php echo $stats['present_count']; ?></h2>
                            </div>
                            <i class="bi bi-check-circle display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card attendance-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">ABSENT</h6>
                                <h2 class="mb-0"><?php echo $stats['absent_count']; ?></h2>
                            </div>
                            <i class="bi bi-x-circle display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <i class="bi bi-funnel"></i> Filters
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select">
                            <option value="">All Classes</option>
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?= $class['id'] ?>" <?= ($class_id == $class['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select">
                            <option value="">All Sections</option>
                            <?php while ($section = $sections->fetch_assoc()): ?>
                                <option value="<?= $section['id'] ?>" <?= ($section_id == $section['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?= $start_date ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?= $end_date ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Apply Filters
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table"></i> Attendance Records</span>
                <span class="badge bg-primary">
                    <?= date('F j, Y', strtotime($start_date)) ?> - <?= date('F j, Y', strtotime($end_date)) ?>
                    <?php if ($class_id || $section_id): ?>
                        (Filtered)
                    <?php endif; ?>
                </span>
            </div>
            <div class="card-body">
                <?php if ($report->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table id="reportTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Roll No.</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th>Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $report->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= $row['roll_number'] ?></td>
                                    <td><?= htmlspecialchars($row['class_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['section_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?= ($row['status'] === 'present') ? 'present-badge' : 'absent-badge' ?>">
                                            <i class="bi <?= ($row['status'] === 'present') ? 'bi-check-circle' : 'bi-x-circle' ?>"></i>
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('l', strtotime($row['date'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No attendance records found for the selected filters.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#reportTable').DataTable({
                dom: '<"top"lf>rt<"bottom"ip>',
                order: [[0, 'desc']],
                responsive: true,
                columnDefs: [
                    { responsivePriority: 1, targets: 0 }, // Date
                    { responsivePriority: 2, targets: 2 }, // Roll No
                    { responsivePriority: 3, targets: 5 }  // Status
                ]
            });
        });

        function exportToExcel() {
            const table = document.getElementById('reportTable');
            const workbook = XLSX.utils.table_to_book(table);
            XLSX.writeFile(workbook, `Attendance_Report_${new Date().toISOString().slice(0,10)}.xlsx`);
        }
    </script>
</body>
</html>