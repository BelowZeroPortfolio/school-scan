    <?php
/**
 * Login Page
 * User authentication interface
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = null;

// Handle login form submission
if (isPost()) {
    verifyCsrf();
    
    $username = sanitizeString($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    
    // Validate required fields
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (empty($recaptchaResponse)) {
        $error = 'Please complete the reCAPTCHA verification.';
    } else {
        // Verify reCAPTCHA
        $recaptchaSecret = config('recaptcha_secret_key');
        $recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
        
        $recaptchaData = [
            'secret' => $recaptchaSecret,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($recaptchaData)
            ]
        ];
        
        $context = stream_context_create($options);
        $recaptchaResult = file_get_contents($recaptchaUrl, false, $context);
        $recaptchaJson = json_decode($recaptchaResult, true);
        
        if (!$recaptchaJson['success']) {
            $error = 'reCAPTCHA verification failed. Please try again.';
        } else {
            // Attempt login
            if (login($username, $password)) {
                // Redirect to appropriate dashboard based on role
                redirectToDashboard();
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e(config('app_name', 'Attendance System')); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo config('app_url'); ?>/assets/images/lex.png">
    <!-- Theme System -->
    <?php require_once __DIR__ . '/../includes/theme.php'; ?>
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="theme-bg-primary min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="theme-bg-secondary backdrop-blur-sm theme-border border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <a href="<?php echo config('app_url'); ?>/" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold theme-text-primary">Barcode Attendance</h1>
                            <p class="text-xs theme-text-muted">Smart Attendance System</p>
                        </div>
                    </a>
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
                        <a href="<?php echo config('app_url'); ?>/" class="text-sm font-medium theme-text-secondary hover:text-violet-600 dark-mode:hover:text-violet-400 px-4 py-2 rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700 transition-colors">
                            ← Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
            <div class="max-w-md w-full">
                <!-- Login Card -->
                <div class="theme-bg-card rounded-2xl shadow-xl p-8 theme-border border">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-gradient-to-br from-violet-500 to-violet-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold theme-text-primary mb-2">Admin Login</h2>
                        <p class="text-sm theme-text-muted">Sign in to access the admin portal</p>
                    </div>
                    
            <!-- Login Form -->
            <form method="POST" action="" class="space-y-6">
                <?php echo csrfField(); ?>
                
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
                        
                        <!-- Username Field -->
                        <div>
                            <label for="username" class="block text-sm font-medium theme-text-secondary mb-2">
                                Username
                            </label>
                            <input 
                                id="username" 
                                name="username" 
                                type="text" 
                                required 
                                class="w-full px-4 py-3 border-2 theme-border rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-text-primary placeholder-gray-400 theme-bg-card" 
                                placeholder="Enter your username"
                                value="<?php echo e($_POST['username'] ?? ''); ?>"
                                autofocus
                            >
                        </div>
                        
                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium theme-text-secondary mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <input 
                                    id="password" 
                                    name="password" 
                                    type="password" 
                                    required 
                                    class="w-full px-4 py-3 pr-12 border-2 theme-border rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-text-primary placeholder-gray-400 theme-bg-card" 
                                    placeholder="Enter your password"
                                >
                                <button 
                                    type="button" 
                                    id="togglePassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 theme-text-muted hover:text-violet-600 focus:outline-none"
                                    onclick="togglePasswordVisibility()"
                                >
                                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg id="eyeOffIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Show Password Checkbox & Forgot Password -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    id="showPassword"
                                    class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500 cursor-pointer"
                                    onchange="togglePasswordCheckbox()"
                                >
                                <span class="ml-2 text-sm theme-text-secondary">Show password</span>
                            </label>
                            <a href="<?php echo config('app_url'); ?>/pages/forgot-password.php" class="text-sm font-medium text-violet-600 hover:text-violet-700 transition-colors">
                                Forgot password?
                            </a>
                        </div>
                        
                        <!-- reCAPTCHA -->
                        <div class="flex justify-center">
                            <div class="g-recaptcha" data-sitekey="<?php echo e(config('recaptcha_site_key')); ?>"></div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full bg-gradient-to-r from-violet-600 to-violet-700 text-white px-6 py-3 rounded-xl hover:from-violet-700 hover:to-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- Help Text -->
                <div class="mt-6 text-center">
                    <p class="text-sm theme-text-muted">
                        Need help? Contact your system administrator
                    </p>
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

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');
            const showPasswordCheckbox = document.getElementById('showPassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
                showPasswordCheckbox.checked = true;
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
                showPasswordCheckbox.checked = false;
            }
        }

        function togglePasswordCheckbox() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');
            const showPasswordCheckbox = document.getElementById('showPassword');
            
            if (showPasswordCheckbox.checked) {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
