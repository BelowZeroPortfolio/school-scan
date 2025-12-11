<?php
/**
 * Landing Page
 * Public-facing page with options for students and admin
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: ' . config('app_url') . '/pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(config('app_name', 'Attendance System')); ?></title>
    
    
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Theme System -->
    <?php require_once __DIR__ . '/includes/theme.php'; ?>
</head>
<body class="theme-bg-primary min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="theme-bg-secondary backdrop-blur-md theme-border border-b sticky top-0 z-20 shadow-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold theme-text-primary">Barcode Attendance</h1>
                            <p class="text-xs theme-text-muted">Smart Attendance System</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Theme Toggle -->
                        <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-700/50 dark-mode:hover:bg-gray-700 light-only:hover:bg-gray-100 transition-colors" aria-label="Toggle theme">
                            <svg class="w-5 h-5 text-yellow-400 dark-only" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                            </svg>
                            <svg class="w-5 h-5 text-gray-700 light-only" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                            </svg>
                        </button>
                        <a href="<?php echo config('app_url'); ?>/pages/login.php" class="text-sm font-medium theme-text-secondary hover:text-violet-600 dark-mode:hover:text-violet-400 px-4 py-2 rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700 transition-colors">
                            Admin Login →
                        </a>
                    </div>
                </div>
            </div>
        </header>


        <!-- Hero Section -->
        <main class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
            <div class="max-w-6xl w-full">
                <div class="text-center mb-12">
                    <h2 class="text-4xl sm:text-5xl lg:text-6xl font-bold theme-text-primary mb-4">
                        Welcome to
                        <span class="bg-gradient-to-r from-violet-400 to-blue-400 bg-clip-text text-transparent">
                            Attendance System
                        </span>
                    </h2>
                    <p class="text-lg sm:text-xl theme-text-secondary max-w-2xl mx-auto">
                        Quick and easy attendance tracking with barcode scanning technology
                    </p>
                </div>

                <!-- Action Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                    <!-- Student Scan Card -->
                    <a href="<?php echo config('app_url'); ?>/scan.php" class="group">
                        <div class="theme-bg-card backdrop-blur-sm rounded-2xl p-8 shadow-2xl theme-border border hover:shadow-violet-500/20 hover:border-violet-500/50 transition-all duration-300 transform hover:-translate-y-1">
                            <div class="w-16 h-16 bg-gradient-to-br from-violet-500 to-violet-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold theme-text-primary mb-3">Student Check-In</h3>
                            <p class="theme-text-secondary mb-4">
                                Scan your student ID barcode to record your attendance
                            </p>
                            <div class="flex items-center text-violet-400 font-semibold group-hover:gap-2 transition-all">
                                <span>Scan Now</span>
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </div>
                        </div>
                    </a>

                    <!-- Admin Portal Card -->
                    <a href="<?php echo config('app_url'); ?>/pages/login.php" class="group">
                        <div class="theme-bg-card backdrop-blur-sm rounded-2xl p-8 shadow-2xl theme-border border hover:shadow-blue-500/20 hover:border-blue-500/50 transition-all duration-300 transform hover:-translate-y-1">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold theme-text-primary mb-3">Admin Portal</h3>
                            <p class="theme-text-secondary mb-4">
                                Manage students, view reports, and track attendance records
                            </p>
                            <div class="flex items-center text-blue-400 font-semibold group-hover:gap-2 transition-all">
                                <span>Login</span>
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Features -->
                <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-8 max-w-4xl mx-auto">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold theme-text-primary mb-2">Fast & Easy</h4>
                        <p class="text-sm theme-text-muted">Quick barcode scanning for instant attendance</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold theme-text-primary mb-2">Secure</h4>
                        <p class="text-sm theme-text-muted">Protected data with role-based access</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold theme-text-primary mb-2">Real-time Reports</h4>
                        <p class="text-sm theme-text-muted">Track attendance with detailed analytics</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="theme-bg-secondary theme-border border-t py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm theme-text-muted">
                    © <?php echo date('Y'); ?> Barcode Attendance System. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
