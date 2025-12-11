<?php
/**
 * Authentication and Authorization Functions
 * Handle user login, logout, session management, and role-based access control
 */

// Ensure required files are loaded
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt login with credentials
 * 
 * @param string $username Username
 * @param string $password Password (plain text)
 * @return bool True on success, false on failure
 */
function login($username, $password) {
    try {
        // Fetch user by username
        $sql = "SELECT id, username, password_hash, role, full_name, email, is_active 
                FROM users 
                WHERE username = ? AND is_active = 1";
        
        $user = dbFetchOne($sql, [$username]);
        
        // Check if user exists and password is correct
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Log failed login attempt
            if (function_exists('logWarning')) {
                logWarning('Failed login attempt', [
                    'username' => $username,
                    'ip' => getClientIp()
                ]);
            }
            return false;
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Update last login timestamp
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        dbExecute($updateSql, [$user['id']]);
        
        // Create session record in database
        $sessionSql = "INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) 
                       VALUES (?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE last_activity = NOW()";
        dbExecute($sessionSql, [
            session_id(),
            $user['id'],
            getClientIp(),
            getUserAgent()
        ]);
        
        // Log successful login
        if (function_exists('logInfo')) {
            logInfo('User logged in', [
                'user_id' => $user['id'],
                'username' => $username
            ]);
        }
        
        // Regenerate CSRF token after login
        if (function_exists('regenerateCsrfToken')) {
            regenerateCsrfToken();
        }
        
        return true;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Login error: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Logout current user
 * 
 * @return void
 */
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log logout event
    if (isset($_SESSION['user_id']) && function_exists('logInfo')) {
        logInfo('User logged out', [
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Remove session from database
    try {
        $sql = "DELETE FROM sessions WHERE id = ?";
        dbExecute($sql, [session_id()]);
    } catch (Exception $e) {
        // Silently fail - session cleanup is not critical
    }
    
    // Clear session data
    $_SESSION = [];
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if logged in
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in flag is set
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Check session timeout (default 2 hours)
    $timeout = config('session_timeout', 7200);
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        logout();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Get current user data
 * 
 * @return array|null User data array or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
    ];
}

/**
 * Check if user has specific role
 * 
 * @param string $role Role to check (admin, operator, viewer)
 * @return bool True if user has role
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['role'] ?? null;
    
    // Admin has access to everything
    if ($userRole === 'admin') {
        return true;
    }
    
    // Check specific role
    return $userRole === $role;
}

/**
 * Check if user has any of the specified roles
 * 
 * @param array $roles Array of roles to check
 * @return bool True if user has any of the roles
 */
function hasAnyRole($roles) {
    foreach ($roles as $role) {
        if (hasRole($role)) {
            return true;
        }
    }
    return false;
}

/**
 * Require authentication (redirect if not logged in)
 * 
 * @param string $redirectUrl URL to redirect to (default: login.php)
 * @return void
 */
function requireAuth($redirectUrl = null) {
    if (!isLoggedIn()) {
        $redirectUrl = $redirectUrl ?: config('app_url') . '/pages/login.php';
        setFlash('error', 'Please log in to access this page.');
        redirect($redirectUrl);
    }
}

/**
 * Require specific role (redirect if insufficient permissions)
 * 
 * @param string $role Required role
 * @param string $redirectUrl URL to redirect to (default: dashboard)
 * @return void
 */
function requireRole($role, $redirectUrl = null) {
    requireAuth();
    
    if (!hasRole($role)) {
        $redirectUrl = $redirectUrl ?: config('app_url') . '/pages/dashboard.php';
        setFlash('error', 'You do not have permission to access this page.');
        
        // Log unauthorized access attempt
        if (function_exists('logWarning')) {
            logWarning('Unauthorized access attempt', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'required_role' => $role,
                'user_role' => $_SESSION['role'] ?? null,
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
        
        redirect($redirectUrl);
    }
}

/**
 * Require any of the specified roles
 * 
 * @param array $roles Array of acceptable roles
 * @param string $redirectUrl URL to redirect to (default: dashboard)
 * @return void
 */
function requireAnyRole($roles, $redirectUrl = null) {
    requireAuth();
    
    if (!hasAnyRole($roles)) {
        $redirectUrl = $redirectUrl ?: config('app_url') . '/pages/dashboard.php';
        setFlash('error', 'You do not have permission to access this page.');
        redirect($redirectUrl);
    }
}

/**
 * Validate session integrity
 * Checks for session hijacking attempts
 * 
 * @return bool True if session is valid
 */
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Check if session exists in database
    try {
        $sql = "SELECT user_id, ip_address FROM sessions WHERE id = ?";
        $session = dbFetchOne($sql, [session_id()]);
        
        if (!$session) {
            logout();
            return false;
        }
        
        // Optionally check IP address (can be disabled if users have dynamic IPs)
        if (config('check_session_ip', false)) {
            if ($session['ip_address'] !== getClientIp()) {
                logout();
                return false;
            }
        }
        
        // Update session activity in database
        $updateSql = "UPDATE sessions SET last_activity = NOW() WHERE id = ?";
        dbExecute($updateSql, [session_id()]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Clean up expired sessions (should be called by cron)
 * 
 * @return int Number of sessions cleaned
 */
function cleanExpiredSessions() {
    $timeout = config('session_timeout', 7200);
    $expiredTime = date('Y-m-d H:i:s', time() - $timeout);
    
    try {
        $sql = "DELETE FROM sessions WHERE last_activity < ?";
        return dbExecute($sql, [$expiredTime]);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to clean expired sessions: ' . $e->getMessage());
        }
        return 0;
    }
}
