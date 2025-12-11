<?php
/**
 * Create Admin User Script
 * Run this script once to create the initial admin user
 * 
 * Usage: php database/create-admin.php
 * Or access via browser: http://localhost/school-scan/database/create-admin.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Admin credentials
$username = 'admin';
$password = 'password';
$fullName = 'System Administrator';
$email = 'admin@attendance.local';

try {
    // Check if admin already exists
    $checkSql = "SELECT id FROM users WHERE username = ?";
    $existing = dbFetchOne($checkSql, [$username]);
    
    if ($existing) {
        echo "Admin user already exists!\n";
        echo "Username: $username\n";
        echo "You can update the password if needed.\n";
        exit;
    }
    
    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $sql = "INSERT INTO users (username, password_hash, role, full_name, email, is_active) 
            VALUES (?, ?, 'admin', ?, ?, TRUE)";
    
    $userId = dbInsert($sql, [$username, $passwordHash, $fullName, $email]);
    
    echo "✓ Admin user created successfully!\n\n";
    echo "Login Credentials:\n";
    echo "==================\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
    echo "\n";
    echo "Please change the password after first login!\n";
    echo "\n";
    echo "Login URL: " . config('app_url') . "/pages/login.php\n";
    
} catch (Exception $e) {
    echo "✗ Error creating admin user: " . $e->getMessage() . "\n";
    exit(1);
}
