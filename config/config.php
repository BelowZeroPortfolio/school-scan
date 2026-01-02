<?php
/**
 * Main Configuration File
 * Direct configuration for localhost
 */

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Load .env file if exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!getenv($name)) {
                putenv("$name=$value");
            }
        }
    }
}

// Configuration array
$config = [
    // Database Configuration
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'school',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_charset' => 'utf8mb4',
    
    // Application Settings
    'app_name' => 'Lexite Attendance System',
    'app_url' => getenv('APP_URL') ?: 'http://school-scan.local',
    'app_env' => getenv('APP_ENV') ?: 'development',
    'debug' => true,
    'school_name' => 'SAGAY NATIONAL HIGH SCHOOL', // Update this with your school name
    
    // Security Settings
    'session_timeout' => 7200, // 2 hours in seconds
    'csrf_token_name' => 'csrf_token',
    'check_session_ip' => false, // Disable IP checking for local development
    
    // File Paths
    'barcode_path' => __DIR__ . '/../storage/barcodes/',
    'export_path' => __DIR__ . '/../storage/exports/',
    'upload_max_size' => 5242880, // 5MB in bytes
    
    // Semaphore SMS API Settings (Philippines)
    'semaphore_api_key' => getenv('SEMAPHORE_API_KEY') ?: '',
    'semaphore_sender' => getenv('SEMAPHORE_SENDER') ?: 'SEMAPHORE',
    
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
    'recaptcha_site_key' => getenv('RECAPTCHA_SITE_KEY') ?: '',
    'recaptcha_secret_key' => getenv('RECAPTCHA_SECRET_KEY') ?: '',
];

// Make config globally accessible (only if not already set)
if (!isset($GLOBALS['config'])) {
    $GLOBALS['config'] = $config;
}

/**
 * Get configuration value
 * 
 * @param string $key Configuration key (supports dot notation)
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
if (!function_exists('config')) {
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
}

return $config;
