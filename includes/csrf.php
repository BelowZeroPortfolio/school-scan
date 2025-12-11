<?php
/**
 * CSRF Protection Functions
 * Generate and validate CSRF tokens
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token for session
 * 
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerate CSRF token (call after login)
 * 
 * @return string New CSRF token
 */
function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * 
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get HTML input field for forms
 * 
 * @return string HTML input field
 */
function csrfField() {
    $token = generateCsrfToken();
    $tokenName = config('csrf_token_name', 'csrf_token');
    return sprintf('<input type="hidden" name="%s" value="%s">', 
        htmlspecialchars($tokenName), 
        htmlspecialchars($token)
    );
}

/**
 * Verify CSRF token or die with error
 * 
 * @return void
 */
function verifyCsrf() {
    $tokenName = config('csrf_token_name', 'csrf_token');
    $token = $_POST[$tokenName] ?? '';
    
    if (!validateCsrfToken($token)) {
        // Log CSRF failure
        if (function_exists('logWarning')) {
            logWarning('CSRF token validation failed', [
                'ip' => getClientIp(),
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
        
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}

/**
 * Check CSRF token and return boolean
 * 
 * @return bool True if valid
 */
function checkCsrf() {
    $tokenName = config('csrf_token_name', 'csrf_token');
    $token = $_POST[$tokenName] ?? '';
    
    return validateCsrfToken($token);
}
