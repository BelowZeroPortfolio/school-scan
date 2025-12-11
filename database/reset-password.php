<?php
/**
 * Password Reset Script
 * Use this script to reset a user's password
 * 
 * Usage: php database/reset-password.php
 * Or access via browser: http://localhost/school-scan/database/reset-password.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Configuration - Change these values
$username = 'admin';           // Username to reset
$newPassword = 'password';     // New password

echo "Password Reset Script\n";
echo "===================\n\n";

try {
    // Check if user exists
    $checkSql = "SELECT id, username, role, full_name FROM users WHERE username = ?";
    $user = dbFetchOne($checkSql, [$username]);
    
    if (!$user) {
        echo "✗ Error: User '$username' not found!\n";
        echo "\nAvailable users:\n";
        
        $allUsers = dbFetchAll("SELECT username, role FROM users WHERE is_active = 1");
        foreach ($allUsers as $u) {
            echo "  - {$u['username']} ({$u['role']})\n";
        }
        exit(1);
    }
    
    echo "User found:\n";
    echo "  Username: {$user['username']}\n";
    echo "  Role: {$user['role']}\n";
    echo "  Name: {$user['full_name']}\n\n";
    
    // Hash the new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateSql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
    dbExecute($updateSql, [$passwordHash, $user['id']]);
    
    echo "✓ Password reset successfully!\n\n";
    echo "New Credentials:\n";
    echo "================\n";
    echo "Username: $username\n";
    echo "Password: $newPassword\n";
    echo "\n";
    echo "Login URL: " . config('app_url') . "/pages/login.php\n";
    
} catch (Exception $e) {
    echo "✗ Error resetting password: " . $e->getMessage() . "\n";
    exit(1);
}
