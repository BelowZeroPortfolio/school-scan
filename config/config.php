<?php
/**
 * Main Configuration File
 * Direct configuration for localhost
 */

// Load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Configuration array
$config = [
    // Database Configuration
    'db_host' => 'localhost',
    'db_name' => 'school',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    
    // Application Settings
    'app_name' => 'Barcode Attendance System',
    'app_url' => 'http://localhost/school-scan',
    'app_env' => 'development',
    'debug' => true,
    'school_name' => 'Your School Name', // Update this with your school name
    
    // Security Settings
    'session_timeout' => 7200, // 2 hours in seconds
    'csrf_token_name' => 'csrf_token',
    'check_session_ip' => false, // Disable IP checking for local development
    
    // File Paths
    'barcode_path' => __DIR__ . '/../storage/barcodes/',
    'export_path' => __DIR__ . '/../storage/exports/',
    'upload_max_size' => 5242880, // 5MB in bytes
    
    // SMS Mobile API Settings
    'smsmobileapi_key' => '7c56b6caf31371598f7267012bf0d85378654f19b8e65edf',
    
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
    
    // reCAPTCHA Settings
    'recaptcha_site_key' => '6LfS7icsAAAAAJqNSS0TWy-RPDkF1H38NZuooxmz',
    'recaptcha_secret_key' => '6LfS7icsAAAAAN4D741KC8_tZrlzjkc7ygiZa5eA',
];

// Make config globally accessible
$GLOBALS['config'] = $config;

/**
 * Get configuration value
 * 
 * @param string $key Configuration key (supports dot notation)
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function config($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS['config'];
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

return $config;
