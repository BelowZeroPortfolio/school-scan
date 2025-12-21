<?php
/**
 * Production Environment Configuration
 */

return [
    // Database Configuration
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'attendance_prod',
    'db_user' => getenv('DB_USER') ?: 'attendance_user',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_charset' => 'utf8mb4',
    
    // Application Settings
    'app_name' => 'Lexite Attendance System',
    'app_url' => getenv('APP_URL') ?: 'https://attendance.example.com',
    'app_env' => 'production',
    'debug' => false,
    
    // Security Settings
    'session_lifetime' => 3600, // 1 hour in seconds
    'csrf_token_name' => 'csrf_token',
    
    // File Paths
    'barcode_path' => __DIR__ . '/../storage/barcodes/',
    'export_path' => __DIR__ . '/../storage/exports/',
    'upload_max_size' => 5242880, // 5MB in bytes
    
    // Notification Settings (Production - use environment variables)
    'mail' => [
        'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('MAIL_PORT') ?: 587,
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'from_email' => getenv('MAIL_FROM') ?: 'noreply@attendance.com',
        'from_name' => 'Attendance System',
    ],
    
    'twilio' => [
        'account_sid' => getenv('TWILIO_SID') ?: '',
        'auth_token' => getenv('TWILIO_TOKEN') ?: '',
        'from_number' => getenv('TWILIO_FROM') ?: '',
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
