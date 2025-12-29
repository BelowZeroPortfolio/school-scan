<?php
/**
 * Authentication and Authorization Functions
 * Handle user login, logout, session management, and role-based access control
 */

// Ensure required files are loaded
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started and headers haven't been sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
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
        $sql = "SELECT id, username, password_hash, role, full_name, email, is_active, is_premium 
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
        $_SESSION['is_premium'] = $user['is_premium'] ?? 0;
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
        
        // Record teacher time in for attendance monitoring
        if ($user['role'] === 'teacher') {
            require_once __DIR__ . '/teacher-attendance.php';
            recordTeacherTimeIn($user['id']);
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
    
    // Record teacher time out before clearing session
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
        require_once __DIR__ . '/teacher-attendance.php';
        recordTeacherTimeOut($_SESSION['user_id']);
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
        'is_premium' => $_SESSION['is_premium'] ?? 0,
    ];
}

/**
 * Check if user has specific role
 * 
 * @param string $role Role to check (admin, principal, teacher)
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
    
    // Principal has access to most things except admin-only features
    if ($userRole === 'principal') {
        // Principal can access teacher-level features
        if (in_array($role, ['teacher', 'principal'])) {
            return true;
        }
    }
    
    // Check specific role
    return $userRole === $role;
}

/**
 * Check if current user is a teacher
 * 
 * @return bool True if user has teacher role
 */
function isTeacher() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return ($_SESSION['role'] ?? null) === 'teacher';
}

/**
 * Check if current user is an admin
 * 
 * @return bool True if user has admin role
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return ($_SESSION['role'] ?? null) === 'admin';
}

/**
 * Check if current user is a principal
 * 
 * @return bool True if user has principal role
 */
function isPrincipal() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return ($_SESSION['role'] ?? null) === 'principal';
}

/**
 * Check if current user is admin or principal (school leadership)
 * 
 * @return bool True if user has admin or principal role
 */
function isSchoolLeadership() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array($_SESSION['role'] ?? null, ['admin', 'principal']);
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

/**
 * Get the appropriate dashboard URL based on user role
 * Teachers get redirected to a teacher-specific dashboard view
 * 
 * @return string Dashboard URL
 */
function getDashboardUrl() {
    $baseUrl = config('app_url') . '/pages/dashboard.php';
    
    // Teachers get a filtered dashboard showing only their students
    if (isTeacher()) {
        return $baseUrl . '?view=teacher';
    }
    
    return $baseUrl;
}

/**
 * Redirect user to appropriate dashboard based on role
 * 
 * @return void
 */
function redirectToDashboard() {
    redirect(getDashboardUrl());
}

/**
 * Get current user's teacher ID if they are a teacher
 * 
 * @return int|null Teacher's user ID or null if not a teacher
 */
function getTeacherId() {
    if (!isTeacher()) {
        return null;
    }
    
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if current user has premium access
 * Admins and Principals always have premium access
 * 
 * @return bool True if user has premium access
 */
function isPremium() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admins and Principals always have premium access
    if (isAdmin() || isPrincipal()) {
        return true;
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return false;
    }
    
    // Check database for premium status
    $sql = "SELECT is_premium, premium_expires_at FROM users WHERE id = ? AND is_active = 1";
    $user = dbFetchOne($sql, [$userId]);
    
    if (!$user || !$user['is_premium']) {
        return false;
    }
    
    // Check if premium has expired
    if ($user['premium_expires_at'] !== null) {
        $expiresAt = strtotime($user['premium_expires_at']);
        if ($expiresAt < time()) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if a specific user has premium access
 * 
 * @param int $userId User ID to check
 * @return bool True if user has premium access
 */
function isUserPremium(int $userId): bool {
    $sql = "SELECT role, is_premium, premium_expires_at FROM users WHERE id = ? AND is_active = 1";
    $user = dbFetchOne($sql, [$userId]);
    
    if (!$user) {
        return false;
    }
    
    // Admins always have premium access
    if ($user['role'] === 'admin') {
        return true;
    }
    
    if (!$user['is_premium']) {
        return false;
    }
    
    // Check if premium has expired
    if ($user['premium_expires_at'] !== null) {
        $expiresAt = strtotime($user['premium_expires_at']);
        if ($expiresAt < time()) {
            return false;
        }
    }
    
    return true;
}

/**
 * Require premium access (redirect if not premium)
 * 
 * @param string $redirectUrl URL to redirect to (default: dashboard)
 * @return void
 */
function requirePremium($redirectUrl = null) {
    requireAuth();
    
    if (!isPremium()) {
        $redirectUrl = $redirectUrl ?: config('app_url') . '/pages/dashboard.php';
        setFlash('error', 'This feature requires a premium subscription. Please contact the administrator.');
        redirect($redirectUrl);
    }
}
