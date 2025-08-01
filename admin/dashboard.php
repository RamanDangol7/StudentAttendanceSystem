<?php
// Start session and check auth
require_once '../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Attendance System</a>
            <div class="navbar-nav">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_teachers.php">Manage Teachers</a>
                <a class="nav-link" href="manage_students.php">Manage Students</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        
        <!-- Quick Stats Cards -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Students</h5>
                        <?php
                        require_once '../database.php';
                        $result = $conn->query("SELECT COUNT(*) FROM students");
                        echo "<p class='display-4'>".$result->fetch_row()[0]."</p>";
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Today's Attendance</h5>
                        <?php
                        $today = date('Y-m-d');
                        $result = $conn->query("SELECT COUNT(*) FROM attendance WHERE date='$today'");
                        echo "<p class='display-4'>".$result->fetch_row()[0]."</p>";
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Pending Tasks</h5>
                        <p class="display-4">3</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>