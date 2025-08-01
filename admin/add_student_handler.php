<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credential_generator.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$name = trim($_POST['name']);
$class_id = (int)$_POST['class_id'];
$section_id = (int)$_POST['section_id'];

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

try {
    $conn->begin_transaction();

    // Generate roll number
    $roll_number = str_pad($conn->query("SELECT IFNULL(MAX(id), 0) + 1 FROM students")->fetch_row()[0], 3, '0', STR_PAD_LEFT);
    
    // Generate credentials
    $username = CredentialGenerator::generateStudentUsername($roll_number);
    $password = CredentialGenerator::generatePassword();
    
    // Create user account
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'student')");
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt->bind_param("ss", $username, $hashed_password);
    $stmt->execute();
    $user_id = $conn->insert_id;

    // Create student record
    $stmt = $conn->prepare("INSERT INTO students (name, class_id, section_id, user_id, roll_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiis", $name, $class_id, $section_id, $user_id, $roll_number);
    $stmt->execute();

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'name' => $name,
        'roll_number' => $roll_number,
        'username' => $username,
        'password' => $password
    ]);
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => str_contains($e->getMessage(), 'foreign key') 
            ? "Invalid class or section selected" 
            : "Database error: " . $e->getMessage()
    ]);
}