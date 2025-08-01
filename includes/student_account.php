<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/credential_generator.php';

class StudentAccount {
    public static function createWithCredentials($conn, $studentId) {
        // Get student data
        $stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        
        if (!$student) {
            throw new Exception("Student not found");
        }

        // Generate unique username
        $baseUsername = "stu_" . strtolower(preg_replace('/[^a-z]/', '', $student['name']));
        $username = $baseUsername;
        $counter = 1;
        
        // Check if username exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        
        while (true) {
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) break;
            $username = $baseUsername . $counter;
            $counter++;
        }

        // Generate and hash password with validation
        $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // ===== CRITICAL ADDITION 1 =====
        // Validate hash was generated properly
        if ($hashedPassword === false) {
            throw new Exception("Password hash generation failed");
        }
        
        // ===== CRITICAL ADDITION 2 =====
        // Verify the hash before storing
        if (!password_verify($password, $hashedPassword)) {
            throw new Exception("Hash verification failed immediately after generation");
        }

        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Regenerate all roll numbers
            $students = $conn->query("SELECT id, name FROM students ORDER BY name");
            $rollNumber = 1;
            
            while ($studentRow = $students->fetch_assoc()) {
                $newRoll = str_pad($rollNumber, 3, '0', STR_PAD_LEFT);
                $conn->query("UPDATE students SET roll_number = '$newRoll', order_index = $rollNumber WHERE id = {$studentRow['id']}");
                
                if ($studentRow['id'] == $studentId) {
                    // Create user with hashed password
                    $userStmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'student')");
                    $userStmt->bind_param("ss", $username, $hashedPassword);
                    $userStmt->execute();
                    
                    // ===== CRITICAL ADDITION 3 =====
                    // Verify the stored hash
                    $storedHash = $conn->query("SELECT password FROM users WHERE username = '$username'")->fetch_row()[0];
                    if (strlen($storedHash) < 60) {
                        throw new Exception("Password hash was truncated during storage");
                    }
                    
                    $conn->query("UPDATE students SET user_id = LAST_INSERT_ID() WHERE id = $studentId");
                    
                    $conn->commit();
                    return [
                        'username' => $username,
                        'password' => $password, // Return plaintext only for display
                        'roll_number' => $newRoll
                    ];
                }
                $rollNumber++;
            }
            throw new Exception("Student numbering failed");
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}