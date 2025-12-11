<?php
/**
 * Logout Page
 * Handles user logout and session cleanup
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Perform logout
logout();

// Redirect to login page
setFlash('success', 'You have been logged out successfully.');
redirect(config('app_url') . '/pages/login.php');
