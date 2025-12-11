<?php
/**
 * Public Barcode Scanning Page
 * Students can scan their barcodes without authentication
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/attendance.php';

// Start session for CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scanResult = null;

// Handle barcode scan submission with POST-Redirect-GET pattern
if (isPost()) {
    verifyCsrf();
    
    $barcode = sanitizeString($_POST['barcode'] ?? '');
    
    // Only process if barcode is not empty
    if (!empty($barcode)) {
        $result = processBarcodeScan($barcode);
        
        // Store result in session and redirect to prevent resubmission
        $_SESSION['scan_result'] = $result;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Retrieve scan result from session (if any)
if (isset($_SESSION['scan_result'])) {
    $scanResult = $_SESSION['scan_result'];
    unset($_SESSION['scan_result']); // Clear after reading
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Attendance - <?php echo e(config('app_name')); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo config('app_url'); ?>/assets/images/icon.svg">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Theme System -->
    <?php require_once __DIR__ . '/includes/theme.php'; ?>
    
    <style>
        [x-cloak] { display: none !important; }
        #reader {
            width: 100%;
            min-height: 280px;
            border-radius: 12px;
            overflow: hidden;
        }
        #reader video {
            width: 100% !important;
            border-radius: 8px;
        }
        #reader__scan_region {
            border-radius: 8px;
            overflow: hidden;
        }
        #reader__scan_region > br {
            display: none;
        }
        #reader__dashboard {
            padding: 10px !important;
        }
        #reader__dashboard_section_csr span {
            font-size: 12px !important;
        }
    </style>
</head>
<body class="theme-bg-primary min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="theme-bg-secondary backdrop-blur-md theme-border border-b sticky top-0 z-20 shadow-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <a href="<?php echo config('app_url'); ?>/" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold theme-text-primary">Student Check-In</h1>
                            <p class="text-xs theme-text-muted">Scan your barcode</p>
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
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-8">
            <div class="max-w-4xl mx-auto">

                <!-- Scan Result Message -->
                <?php if ($scanResult): ?>
                    <?php if ($scanResult['success']): ?>
                        <div id="scanResultMessage" class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 mb-8 shadow-lg transition-all duration-500" role="alert">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-2xl font-bold text-green-900 mb-1"><?php echo e($scanResult['message']); ?></p>
                                    <p class="text-sm text-green-700">
                                        <?php echo e($scanResult['student']['first_name'] . ' ' . $scanResult['student']['last_name']); ?> | 
                                        LRN: <?php echo e($scanResult['student']['lrn'] ?? $scanResult['student']['student_id']); ?>
                                        <?php if (!empty($scanResult['student']['class'])): ?>
                                            | <?php echo e($scanResult['student']['class']); ?><?php echo !empty($scanResult['student']['section']) ? '-' . e($scanResult['student']['section']) : ''; ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (isset($scanResult['notification']['sms'])): ?>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <?php $sms = $scanResult['notification']['sms']; ?>
                                        <?php if ($sms['attempted']): ?>
                                            <?php if ($sms['success']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-200 text-green-800 rounded-full">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    SMS Sent to <?php echo e($sms['recipient']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-200 text-red-800 rounded-full" title="<?php echo e($sms['error'] ?? ''); ?>">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                    SMS Failed: <?php echo e($sms['error'] ?? 'Unknown error'); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-200 text-gray-600 rounded-full" title="<?php echo e($sms['error'] ?? 'No phone'); ?>">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                                                <?php echo e($sms['error'] ?? 'No SMS sent'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Countdown bar -->
                            <div class="mt-4 h-1 bg-green-200 rounded-full overflow-hidden">
                                <div id="countdownBar" class="h-full bg-green-500 transition-all duration-100" style="width: 100%"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="scanResultMessage" class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 mb-8 shadow-lg transition-all duration-500" role="alert">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-2xl font-bold text-red-900 mb-1"><?php echo e($scanResult['error']['message']); ?></p>
                                    <p class="text-sm text-red-700">Please try again</p>
                                </div>
                            </div>
                            <!-- Countdown bar -->
                            <div class="mt-4 h-1 bg-red-200 rounded-full overflow-hidden">
                                <div id="countdownBar" class="h-full bg-red-500 transition-all duration-100" style="width: 100%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <script>
                        // Auto-hide message after 5 seconds with countdown
                        (function() {
                            const msg = document.getElementById('scanResultMessage');
                            const bar = document.getElementById('countdownBar');
                            const duration = 5000;
                            const interval = 50;
                            let elapsed = 0;
                            
                            const countdown = setInterval(() => {
                                elapsed += interval;
                                const remaining = Math.max(0, 100 - (elapsed / duration * 100));
                                bar.style.width = remaining + '%';
                                
                                if (elapsed >= duration) {
                                    clearInterval(countdown);
                                    msg.style.opacity = '0';
                                    msg.style.transform = 'translateY(-20px)';
                                    setTimeout(() => msg.remove(), 500);
                                }
                            }, interval);
                            
                            // Also log to console for debugging
                            <?php if ($scanResult['success'] && isset($scanResult['notification'])): ?>
                            console.log('📱 Notification Status:', <?php echo json_encode($scanResult['notification']); ?>);
                            <?php endif; ?>
                        })();
                    </script>
                <?php endif; ?>

                <!-- Scanning Interface -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Hardware Scanner Input -->
                    <div class="theme-bg-card backdrop-blur-sm rounded-2xl shadow-2xl p-8 theme-border border">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 bg-violet-500/20 rounded-xl flex items-center justify-center border border-violet-500/30">
                                <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold theme-text-primary">Barcode Scanner</h2>
                                <p class="text-sm theme-text-muted">Use hardware scanner</p>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="scanForm" x-data="{ scanning: false }">
                            <?php echo csrfField(); ?>
                            
                            <div class="mb-6">
                                <label for="barcode" class="block text-sm font-medium theme-text-secondary mb-3">
                                    Scan Your Student ID (LRN)
                                </label>
                                
                                <!-- Ready indicator -->
                                <div class="mb-3 flex items-center gap-2 text-sm">
                                    <span class="relative flex h-3 w-3">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                    </span>
                                    <span class="text-green-600 font-medium">Ready to scan</span>
                                </div>
                                
                                <input 
                                    type="text" 
                                    id="barcode" 
                                    name="barcode" 
                                    class="w-full px-4 py-5 bg-gray-900/50 dark-mode:bg-gray-900/50 light-only:bg-gray-50 border-2 border-violet-500/50 rounded-xl focus:ring-4 focus:ring-violet-500/30 focus:border-violet-500 text-xl font-mono theme-text-primary placeholder-gray-500 text-center transition-all"
                                    placeholder="Scan barcode here..."
                                    autofocus
                                    autocomplete="off"
                                >
                                <p class="mt-3 text-sm theme-text-muted flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Auto-submits when barcode is detected
                                </p>
                            </div>
                            
                            <!-- Status indicator -->
                            <div id="scanStatus" class="hidden mb-4 p-3 rounded-xl text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Processing scan...</span>
                                </div>
                            </div>
                            
                            <button 
                                type="submit" 
                                id="submitBtn"
                                class="w-full bg-gradient-to-r from-violet-600 to-violet-700 text-white px-6 py-4 rounded-xl hover:from-violet-700 hover:to-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 font-semibold text-lg shadow-lg transition-all"
                            >
                                Submit Attendance
                            </button>
                        </form>
                    </div>


                    <!-- Camera Scanner -->
                    <div class="theme-bg-card backdrop-blur-sm rounded-2xl shadow-2xl p-8 theme-border border">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center border border-blue-500/30">
                                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold theme-text-primary">Camera Scanner</h2>
                                <p class="text-sm theme-text-muted">Use device camera</p>
                            </div>
                        </div>
                        
                        <div id="camera-scanner" x-data="{ scanning: false }" x-init="<?php if (!$scanResult): ?>setTimeout(() => { scanning = true; $nextTick(() => startCamera()); }, 500)<?php endif; ?>">
                            <div x-show="!scanning" class="text-center py-8">
                                <div class="w-20 h-20 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-500/30">
                                    <svg class="w-10 h-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <button 
                                    @click="scanning = true; $nextTick(() => startCamera())"
                                    class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-xl hover:from-blue-600 hover:to-blue-700 hover:shadow-blue-500/50 focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold text-lg shadow-lg transition-all"
                                >
                                    Start Camera Scanner
                                </button>
                                <p class="mt-4 text-sm theme-text-muted">
                                    Fast barcode detection with camera
                                </p>
                            </div>
                            
                            <div x-show="scanning" x-cloak>
                                <div id="reader" class="mb-4"></div>
                                
                                <button 
                                    @click="scanning = false; stopCamera()"
                                    class="w-full bg-red-500 text-white px-6 py-4 rounded-xl hover:bg-red-600 hover:shadow-red-500/50 focus:outline-none focus:ring-2 focus:ring-red-500 font-semibold shadow-lg transition-all"
                                >
                                    Stop Camera
                                </button>
                                
                                <p class="mt-4 text-sm theme-text-muted text-center flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 animate-pulse text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="10" r="8"/>
                                    </svg>
                                    Point camera at barcode - auto-detects instantly
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="mt-8 theme-bg-card backdrop-blur-sm theme-border border rounded-2xl p-6">
                    <h3 class="text-lg font-semibold theme-text-primary mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        How to Check In
                    </h3>
                    <ul class="space-y-2 text-sm theme-text-secondary">
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-violet-400">1.</span>
                            <span>Click on the barcode input field or start the camera scanner</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-violet-400">2.</span>
                            <span>Scan your student ID barcode using a scanner or camera</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-violet-400">3.</span>
                            <span>Wait for confirmation message to appear</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-violet-400">4.</span>
                            <span>You're done! Your attendance has been recorded</span>
                        </li>
                    </ul>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="theme-bg-secondary theme-border border-t py-6 mt-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm theme-text-muted">
                    Need help? Contact your administrator
                </p>
            </div>
        </footer>
    </div>

    <!-- Html5-QRCode - Much faster barcode scanner -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function() {
            const barcodeInput = document.getElementById('barcode');
            const scanForm = document.getElementById('scanForm');
            const scanStatus = document.getElementById('scanStatus');
            const submitBtn = document.getElementById('submitBtn');
            
            let html5QrCode = null;
            let lastInputTime = 0;
            let submitTimeout = null;
            const MIN_BARCODE_LENGTH = 6;
            
            // Focus input on page load
            barcodeInput.focus();
            
            // Re-focus when clicking anywhere on the page (except buttons)
            document.addEventListener('click', function(e) {
                if (!e.target.closest('button') && !e.target.closest('a') && !e.target.closest('#reader')) {
                    barcodeInput.focus();
                }
            });
            
            // Auto-reset after showing result
            <?php if ($scanResult): ?>
            setTimeout(function() {
                barcodeInput.value = '';
                barcodeInput.focus();
            }, 3000);
            <?php endif; ?>
            
            // Hardware scanner detection
            barcodeInput.addEventListener('input', function(e) {
                const currentTime = Date.now();
                const currentValue = e.target.value.trim();
                
                if (submitTimeout) clearTimeout(submitTimeout);
                
                lastInputTime = currentTime;
                
                if (currentValue.length >= MIN_BARCODE_LENGTH) {
                    submitTimeout = setTimeout(function() {
                        if (barcodeInput.value.trim().length >= MIN_BARCODE_LENGTH) {
                            showProcessing();
                            scanForm.submit();
                        }
                    }, 300);
                }
            });
            
            // Enter key handler
            barcodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = barcodeInput.value.trim();
                    if (value.length >= MIN_BARCODE_LENGTH) {
                        showProcessing();
                        scanForm.submit();
                    }
                }
            });
            
            // Form validation
            scanForm.addEventListener('submit', function(e) {
                const value = barcodeInput.value.trim();
                if (value.length < MIN_BARCODE_LENGTH) {
                    e.preventDefault();
                    barcodeInput.focus();
                    barcodeInput.classList.add('border-amber-500');
                    setTimeout(() => barcodeInput.classList.remove('border-amber-500'), 2000);
                    return false;
                }
                showProcessing();
            });
            
            function showProcessing() {
                scanStatus.classList.remove('hidden');
                scanStatus.classList.add('bg-violet-100', 'text-violet-700');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50');
            }
            
            // Camera scanner using Html5-QRCode
            window.startCamera = function() {
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    html5QrCode = new Html5Qrcode("reader", {
                        formatsToSupport: [
                            Html5QrcodeSupportedFormats.QR_CODE,
                            Html5QrcodeSupportedFormats.CODE_128,
                            Html5QrcodeSupportedFormats.CODE_39,
                            Html5QrcodeSupportedFormats.EAN_13,
                            Html5QrcodeSupportedFormats.EAN_8,
                            Html5QrcodeSupportedFormats.ITF,
                            Html5QrcodeSupportedFormats.CODABAR
                        ]
                    });
                    
                    const config = {
                        fps: 10,
                        qrbox: { width: 300, height: 150 },
                        aspectRatio: 1.5
                    };
                    
                    html5QrCode.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText) => {
                            console.log("SUCCESS! Barcode:", decodedText);
                            
                            // Vibrate on success
                            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                            
                            // Stop camera and submit
                            html5QrCode.stop().then(() => {
                                barcodeInput.value = decodedText;
                                showProcessing();
                                scanForm.submit();
                            }).catch(() => {
                                barcodeInput.value = decodedText;
                                showProcessing();
                                scanForm.submit();
                            });
                        },
                        (errorMessage) => {
                            // Silent - no barcode detected yet
                        }
                    ).catch(err => {
                        console.error("Camera error:", err);
                        alert("Could not start camera: " + err);
                    });
                }, 100);
            };

            window.stopCamera = function() {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                        html5QrCode = null;
                    }).catch(err => {
                        console.log("Stop error:", err);
                        html5QrCode = null;
                    });
                }
            };
        })();
    </script>
</body>
</html>
