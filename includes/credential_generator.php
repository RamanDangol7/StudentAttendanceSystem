<?php
class CredentialGenerator {
    // Existing student functions (unchanged)
    public static function generateStudentUsername($roll_number) {
        return 'stu_' . $roll_number;
    }

    public static function generatePassword() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        return substr(str_shuffle($chars), 0, 8);
    }

    // New unified credential generator
    public static function generateCredentials($conn, $id, $role, $name = '') {
        // Generate password
        $password = self::generatePassword();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate username based on role
        $username = ($role === 'teacher') ? 'tch_' . $id : 'stu_' . $id;
        
        return [
            'username' => $username,
            'password' => $password,
            'hashed_password' => $hashed_password,
            'display_name' => $name
        ];
    }
}