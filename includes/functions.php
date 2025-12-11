<?php
/**
 * Validation and Sanitization Helper Functions
 * Common utility functions for input handling and output
 */

/**
 * Sanitize string input
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitizeString($input) {
    return trim(strip_tags($input));
}

/**
 * Sanitize email address
 * 
 * @param string $email Email address
 * @return string Sanitized email
 */
function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Validate email format
 * 
 * @param string $email Email address
 * @return bool True if valid
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic validation)
 * 
 * @param string $phone Phone number
 * @return bool True if valid
 */
function validatePhone($phone) {
    // Remove common formatting characters
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    // Check if it has at least 10 digits
    return strlen($cleaned) >= 10;
}

/**
 * Validate required fields in data array
 * 
 * @param array $fields Required field names
 * @param array $data Data array to validate
 * @return array Empty if valid, or array of missing fields
 */
function validateRequired($fields, $data) {
    $missing = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    
    return $missing;
}

/**
 * Escape output for HTML (prevent XSS)
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 * @return void
 */
function setFlash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message from session
 * 
 * @return array|null Flash message array or null
 */
function getFlash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    return null;
}

/**
 * Display flash message HTML with auto-dismiss
 * 
 * @return string HTML for flash message
 */
function displayFlash() {
    $flash = getFlash();
    
    if (!$flash) {
        return '';
    }
    
    $colors = [
        'success' => 'bg-green-50 border-green-500 text-green-700',
        'error' => 'bg-red-50 border-red-500 text-red-700',
        'warning' => 'bg-yellow-50 border-yellow-500 text-yellow-700',
        'info' => 'bg-blue-50 border-blue-500 text-blue-700',
    ];
    
    $icons = [
        'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        'error' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
        'info' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ];
    
    $colorClass = $colors[$flash['type']] ?? $colors['info'];
    $icon = $icons[$flash['type']] ?? $icons['info'];
    
    return sprintf(
        '<div id="flash-message" class="border-l-4 p-4 mb-4 rounded-r-lg %s transition-all duration-500" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">%s</svg>
                <p class="font-medium">%s</p>
            </div>
        </div>
        <script>
            setTimeout(function() {
                var flash = document.getElementById("flash-message");
                if (flash) {
                    flash.style.opacity = "0";
                    flash.style.transform = "translateY(-10px)";
                    setTimeout(function() { flash.remove(); }, 500);
                }
            }, 3000);
        </script>',
        $colorClass,
        $icon,
        e($flash['message'])
    );
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Format string (optional)
 * @return string Formatted date
 */
function formatDate($date, $format = null) {
    if (!$date) {
        return '';
    }
    
    $format = $format ?: config('display_date_format', 'M d, Y');
    
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format datetime for display
 * 
 * @param string $datetime Datetime string
 * @param string $format Format string (optional)
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = null) {
    if (!$datetime) {
        return '';
    }
    
    $format = $format ?: config('display_datetime_format', 'M d, Y h:i A');
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Generate pagination HTML
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string Pagination HTML
 */
function pagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="flex justify-center mt-4">';
    $html .= '<nav class="inline-flex rounded-md shadow">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= sprintf(
            '<a href="%s?page=%d" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>',
            $baseUrl,
            $currentPage - 1
        );
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === $currentPage) {
            $html .= sprintf(
                '<span class="px-3 py-2 bg-violet-600 border border-violet-600 text-sm font-medium text-white">%d</span>',
                $i
            );
        } else {
            $html .= sprintf(
                '<a href="%s?page=%d" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">%d</a>',
                $baseUrl,
                $i,
                $i
            );
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= sprintf(
            '<a href="%s?page=%d" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>',
            $baseUrl,
            $currentPage + 1
        );
    }
    
    $html .= '</nav>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Check if request is POST
 * 
 * @return bool True if POST request
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 * 
 * @return bool True if GET request
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Get user agent string
 * 
 * @return string User agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}
