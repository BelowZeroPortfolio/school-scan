<?php
/**
 * Forgot Password Page
 * Password reset request interface
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(config('app_url') . '/pages/dashboard.php');
}

$success = null;
$error = null;

// Handle password reset request
if (isPost()) {
    verifyCsrf();
    
    $username = sanitizeString($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = 'Please enter your username.';
    } else {
        // In a real application, you would:
        // 1. Check if username exists
        // 2. Generate a password reset token
        // 3. Send email with reset link
        // For now, we'll just show a success message
        $success = 'If an account exists with this username, password reset instructions have been sent to the associated email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo e(config('app_name', 'Attendance System')); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gradient-to-br from-violet-50 via-white to-violet-50 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white/80 backdrop-blur-sm border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <a href="<?php echo config('app_url'); ?>/" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">QR Attendance</h1>
                            <p class="text-xs text-gray-500">Smart Attendance System</p>
                        </div>
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/login.php" class="text-sm font-medium text-gray-600 hover:text-gray-900 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                        ← Back to Login
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
            <div class="max-w-md w-full">
                <!-- Forgot Password Card -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Forgot Password?</h2>
                        <p class="text-sm text-gray-600">Enter your username and we'll help you reset your password</p>
                    </div>
                    
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="mb-6 bg-green-50 border-2 border-green-200 rounded-xl p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-green-800"><?php echo e($success); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-50 border-2 border-red-200 rounded-xl p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800"><?php echo e($error); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$success): ?>
                    <!-- Reset Form -->
                    <form method="POST" action="" class="space-y-6">
                        <?php echo csrfField(); ?>
                        
                        <!-- Username Field -->
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                Username
                            </label>
                            <input 
                                id="username" 
                                name="username" 
                                type="text" 
                                required 
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 placeholder-gray-400" 
                                placeholder="Enter your username"
                                value="<?php echo e($_POST['username'] ?? ''); ?>"
                                autofocus
                            >
                            <p class="mt-2 text-xs text-gray-500">
                                Enter the username associated with your account
                            </p>
                        </div>
                        
                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-xl hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Send Reset Instructions
                        </button>
                    </form>
                    <?php else: ?>
                    <!-- Back to Login Button -->
                    <a 
                        href="<?php echo config('app_url'); ?>/pages/login.php"
                        class="block w-full bg-gradient-to-r from-violet-600 to-violet-700 text-white px-6 py-3 rounded-xl hover:from-violet-700 hover:to-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 font-semibold shadow-lg transition-all text-center"
                    >
                        Back to Login
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Help Text -->
                <div class="mt-6 text-center space-y-2">
                    <p class="text-sm text-gray-600">
                        Remember your password? 
                        <a href="<?php echo config('app_url'); ?>/pages/login.php" class="font-medium text-violet-600 hover:text-violet-700">
                            Sign in
                        </a>
                    </p>
                    <p class="text-sm text-gray-600">
                        Need help? Contact your system administrator
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-100 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    © <?php echo date('Y'); ?> QR Attendance System. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
