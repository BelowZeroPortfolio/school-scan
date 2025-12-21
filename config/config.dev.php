<?php
/**
 * Development Environment Configuration
 */

return [
    // Database Configuration
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'school',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_charset' => 'utf8mb4',
    
    // Application Settings
    'app_name' => 'Lexite Attendance System',
    'app_url' => 'http://localhost/attendance-system',
    'app_env' => 'development',
    'debug' => true,
    
    // Security Settings
    'session_lifetime' => 7200, // 2 hours in seconds
    'csrf_token_name' => 'csrf_token',
    
    // File Paths
    'barcode_path' => __DIR__ . '/../storage/barcodes/',
    'export_path' => __DIR__ . '/../storage/exports/',
    'upload_max_size' => 5242880, // 5MB in bytes
    
    // Notification Settings (Development - use test credentials)
    'mail' => [
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => '',
        'password' => '',
        'from_email' => 'noreply@attendance.local',
        'from_name' => 'Attendance System',
    ],
    
    'twilio' => [
        'account_sid' => '',
        'auth_token' => '',
        'from_number' => '',
    ],
    
    // Retry Settings
    'retry' => [
        'max_attempts' => 3,
        'initial_delay' => 60, // seconds
        'max_delay' => 3600, // 1 hour
    ],
    
    // Pagination
    'per_page' => 20,
    
    // Date/Time Format
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d H:i:s',
    'display_date_format' => 'M d, Y',
    'display_datetime_format' => 'M d, Y h:i A',
];
