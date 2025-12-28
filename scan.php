<?php
/**
 * QR Code Scanning Page
 * Teachers/Operators scan student QR codes for attendance
 * Optimized for GOOJPRT 2D QR Scanner (hardware)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/attendance.php';

// Require authentication - teachers, operators, or admins can scan
requireAnyRole(['admin', 'operator', 'teacher']);

$currentUser = getCurrentUser();
$scanResult = null;

// Get scan mode from URL parameter (arrival or dismissal)
$scanMode = isset($_GET['mode']) && $_GET['mode'] === 'dismissal' ? 'dismissal' : 'arrival';

// Handle barcode scan submission with POST-Redirect-GET pattern
if (isPost()) {
    verifyCsrf();
    
    $barcode = sanitizeString($_POST['barcode'] ?? '');
    $mode = sanitizeString($_POST['mode'] ?? 'arrival');
    
    if (!empty($barcode)) {
        $result = processBarcodeScan($barcode, $mode);
        $_SESSION['scan_result'] = $result;
        $_SESSION['scan_mode'] = $mode;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Retrieve scan result from session
if (isset($_SESSION['scan_result'])) {
    $scanResult = $_SESSION['scan_result'];
    unset($_SESSION['scan_result']);
}
if (isset($_SESSION['scan_mode'])) {
    $scanMode = $_SESSION['scan_mode'];
    unset($_SESSION['scan_mode']);
}

// Mode display settings
$modeConfig = [
    'arrival' => [
        'title' => 'Time In',
        'subtitle' => 'Record student arrival',
        'color' => 'emerald',
        'gradient' => 'from-emerald-500 to-green-600',
        'icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1'
    ],
    'dismissal' => [
        'title' => 'Time Out',
        'subtitle' => 'Record student dismissal',
        'color' => 'orange',
        'gradient' => 'from-orange-500 to-amber-600',
        'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'
    ]
];
$currentMode = $modeConfig[$scanMode];

// Determine result type for better messaging
$resultType = null;
if ($scanResult) {
    if ($scanResult['success']) {
        $resultType = 'success';
    } elseif (isset($scanResult['error']['code'])) {
        $code = $scanResult['error']['code'];
        if ($code === 'ALREADY_RECORDED' || $code === 'DUPLICATE_SCAN') {
            $resultType = 'already_recorded';
        } elseif ($code === 'STUDENT_NOT_FOUND') {
            $resultType = 'not_found';
        } else {
            $resultType = 'error';
        }
    } else {
        $resultType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Attendance - <?php echo e(config('app_name')); ?></title>
    
    <link rel="icon" type="image/png" href="<?php echo config('app_url'); ?>/assets/images/lex.png">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php require_once __DIR__ . '/includes/theme.php'; ?>
    
    <style>
        [x-cloak] { display: none !important; }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .pulse-ring { animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite; }
    </style>
</head>
<body class="theme-bg-primary min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="theme-bg-card backdrop-blur-md theme-border border-b sticky top-0 z-20 shadow-sm">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br <?php echo $currentMode['gradient']; ?> rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $currentMode['icon']; ?>"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold theme-text-primary"><?php echo e($currentMode['title']); ?></h1>
                            <p class="text-xs theme-text-muted"><?php echo e($currentMode['subtitle']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="themeToggle" class="p-2 rounded-lg theme-bg-secondary hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" aria-label="Toggle theme">
                            <svg class="w-5 h-5 text-yellow-500 dark-only" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                            </svg>
                            <svg class="w-5 h-5 text-gray-700 light-only" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                            </svg>
                        </button>
                        <a href="<?php echo config('app_url'); ?>/pages/dashboard.php" class="text-sm font-medium theme-text-secondary hover:text-violet-600 px-4 py-2 rounded-lg theme-bg-secondary hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            ← Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-8">
            <div class="max-w-2xl mx-auto">

                <!-- Scan Result Messages -->
                <?php if ($scanResult && $resultType): ?>
                    <?php if ($resultType === 'success'): ?>
                        <!-- Success Message -->
                        <div id="scanResultMessage" class="bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-300 dark:border-emerald-700 rounded-2xl p-6 mb-8 shadow-lg" role="alert">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xl font-bold text-emerald-800 dark:text-emerald-200"><?php echo e($scanResult['message']); ?></p>
                                    <p class="text-sm text-emerald-700 dark:text-emerald-300 mt-1">
                                        <?php echo e($scanResult['student']['first_name'] . ' ' . $scanResult['student']['last_name']); ?>
                                        <span class="mx-1">•</span>
                                        LRN: <?php echo e($scanResult['student']['lrn'] ?? $scanResult['student']['student_id']); ?>
                                    </p>
                                    <?php if (isset($scanResult['notification']['sms'])): ?>
                                        <?php $sms = $scanResult['notification']['sms']; ?>
                                        <?php if ($sms['success']): ?>
                                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-2 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        SMS sent to parent
                                    </p>
                                        <?php elseif ($sms['attempted'] && !$sms['success']): ?>
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-2 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        SMS failed: <?php echo e($sms['error'] ?? 'Unknown error'); ?>
                                    </p>
                                        <?php elseif (!$sms['attempted']): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <?php echo e($sms['error'] ?? 'SMS not sent'); ?>
                                    </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    
                    <?php elseif ($resultType === 'already_recorded'): ?>
                        <!-- Already Recorded Message (Info, not error) -->
                        <div id="scanResultMessage" class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-300 dark:border-blue-700 rounded-2xl p-6 mb-8 shadow-lg" role="alert">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xl font-bold text-blue-800 dark:text-blue-200">Already Recorded</p>
                                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1"><?php echo e($scanResult['error']['message']); ?></p>
                                </div>
                            </div>
                        </div>
                    
                    <?php elseif ($resultType === 'not_found'): ?>
                        <!-- Student Not Found -->
                        <div id="scanResultMessage" class="bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-300 dark:border-amber-700 rounded-2xl p-6 mb-8 shadow-lg" role="alert">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-amber-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xl font-bold text-amber-800 dark:text-amber-200">Student Not Found</p>
                                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">The scanned QR code doesn't match any student record.</p>
                                </div>
                            </div>
                        </div>
                    
                    <?php else: ?>
                        <!-- Generic Error -->
                        <div id="scanResultMessage" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700 rounded-2xl p-6 mb-8 shadow-lg" role="alert">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xl font-bold text-red-800 dark:text-red-200">Scan Error</p>
                                    <p class="text-sm text-red-700 dark:text-red-300 mt-1"><?php echo e($scanResult['error']['message'] ?? 'An error occurred'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <script>
                        setTimeout(() => {
                            const msg = document.getElementById('scanResultMessage');
                            if (msg) {
                                msg.style.opacity = '0';
                                msg.style.transform = 'translateY(-10px)';
                                msg.style.transition = 'all 0.3s ease';
                                setTimeout(() => msg.remove(), 300);
                            }
                        }, 4000);
                    </script>
                <?php endif; ?>

                <!-- Mode Switcher -->
                <div class="flex justify-center mb-8">
                    <div class="inline-flex rounded-xl p-1 theme-bg-card shadow-lg border theme-border">
                        <a href="?mode=arrival" 
                           class="px-6 py-3 rounded-lg text-sm font-semibold transition-all <?php echo $scanMode === 'arrival' ? 'bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-md' : 'theme-text-secondary hover:bg-gray-100 dark:hover:bg-gray-700'; ?>">
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/>
                                </svg>
                                Time In
                            </span>
                        </a>
                        <a href="?mode=dismissal" 
                           class="px-6 py-3 rounded-lg text-sm font-semibold transition-all <?php echo $scanMode === 'dismissal' ? 'bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-md' : 'theme-text-secondary hover:bg-gray-100 dark:hover:bg-gray-700'; ?>">
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                                </svg>
                                Time Out
                            </span>
                        </a>
                    </div>
                </div>

                <!-- Scanner Card -->
                <div class="theme-bg-card rounded-3xl shadow-2xl p-8 theme-border border">
                    <!-- Scanner Icon with Pulse -->
                    <div class="flex justify-center mb-6">
                        <div class="relative">
                            <div class="absolute inset-0 bg-<?php echo $currentMode['color']; ?>-500/30 rounded-full pulse-ring"></div>
                            <div class="relative w-24 h-24 bg-gradient-to-br <?php echo $currentMode['gradient']; ?> rounded-full flex items-center justify-center shadow-xl">
                                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-2xl font-bold theme-text-primary text-center mb-2">Ready to Scan</h2>
                    <p class="text-center theme-text-muted mb-8">Use your QR scanner to record attendance</p>
                    
                    <form method="POST" action="" id="scanForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="mode" value="<?php echo e($scanMode); ?>">
                        
                        <div class="mb-6">
                            <input 
                                type="text" 
                                id="barcode" 
                                name="barcode" 
                                class="w-full px-6 py-5 theme-bg-secondary border-2 border-<?php echo $currentMode['color']; ?>-400 dark:border-<?php echo $currentMode['color']; ?>-600 rounded-2xl focus:ring-4 focus:ring-<?php echo $currentMode['color']; ?>-500/30 focus:border-<?php echo $currentMode['color']; ?>-500 text-2xl font-mono theme-text-primary placeholder-gray-400 text-center transition-all"
                                placeholder="Scan QR code here..."
                                autofocus
                                autocomplete="off"
                            >
                        </div>
                        
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="w-full bg-gradient-to-r <?php echo $currentMode['gradient']; ?> text-white px-6 py-4 rounded-xl hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-<?php echo $currentMode['color']; ?>-500/30 font-semibold text-lg transition-all disabled:opacity-50"
                        >
                            Record <?php echo $scanMode === 'arrival' ? 'Arrival' : 'Dismissal'; ?>
                        </button>
                    </form>
                    
                    <p class="mt-6 text-sm theme-text-muted text-center">
                        Scanner auto-submits when QR code is detected
                    </p>
                </div>

                <!-- Quick Tips -->
                <div class="mt-8 theme-bg-card rounded-2xl p-6 theme-border border">
                    <h3 class="text-sm font-semibold theme-text-primary mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Quick Tips
                    </h3>
                    <ul class="space-y-2 text-sm theme-text-muted">
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>
                            Point scanner at student's ID QR code
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>
                            Wait for confirmation message
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>
                            Switch between Time In/Out using buttons above
                        </li>
                    </ul>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="theme-bg-card theme-border border-t py-4 mt-auto">
            <div class="max-w-4xl mx-auto px-4">
                <p class="text-center text-xs theme-text-muted">
                    Logged in as <?php echo e($currentUser['full_name']); ?> • <?php echo date('F j, Y'); ?>
                </p>
            </div>
        </footer>
    </div>

    <script>
        (function() {
            const barcodeInput = document.getElementById('barcode');
            const scanForm = document.getElementById('scanForm');
            const submitBtn = document.getElementById('submitBtn');
            
            let lastInputTime = 0;
            let scanBuffer = '';
            let submitTimeout = null;
            let isProcessing = false;
            let lastSubmitTime = 0;
            
            // Configuration optimized for GOOJPRT 2D QR Scanner
            const MIN_BARCODE_LENGTH = 6;
            const SCANNER_SUBMIT_DELAY = 100; // ms to wait after last character before submitting
            const DEBOUNCE_TIME = 1500; // Prevent double scans within this time
            const SCAN_COMPLETE_CHARS = ['\r', '\n', '\t']; // Characters that indicate scan complete
            
            // Focus input on page load
            setTimeout(() => barcodeInput.focus(), 100);
            
            // Re-focus when clicking anywhere on page
            document.addEventListener('click', function(e) {
                if (!e.target.closest('button') && !e.target.closest('a') && !isProcessing) {
                    barcodeInput.focus();
                }
            });
            
            // Re-focus when window gains focus
            window.addEventListener('focus', function() {
                if (!isProcessing) {
                    setTimeout(() => barcodeInput.focus(), 50);
                }
            });
            
            // Auto-reset after showing result
            <?php if ($scanResult): ?>
            setTimeout(() => {
                barcodeInput.value = '';
                scanBuffer = '';
                barcodeInput.focus();
                isProcessing = false;
            }, 300);
            <?php endif; ?>
            
            // Clean barcode input - remove special characters that scanner might add
            function cleanBarcode(value) {
                // Remove carriage return, newline, tab, and trim whitespace
                // Also remove any non-printable characters
                return value.replace(/[\r\n\t\x00-\x1F\x7F]/g, '').trim();
            }
            
            // Submit the form
            function submitScan() {
                const barcode = cleanBarcode(barcodeInput.value);
                const now = Date.now();
                
                // Prevent double submission
                if (now - lastSubmitTime < DEBOUNCE_TIME) {
                    console.log('Debounced - too soon after last scan');
                    return;
                }
                
                if (barcode.length >= MIN_BARCODE_LENGTH && !isProcessing) {
                    isProcessing = true;
                    lastSubmitTime = now;
                    submitBtn.disabled = true;
                    barcodeInput.value = barcode; // Set cleaned value
                    barcodeInput.readOnly = true; // Prevent further input
                    
                    // Visual feedback
                    barcodeInput.classList.add('bg-green-100', 'dark:bg-green-900/30');
                    submitBtn.innerHTML = '<span class="flex items-center justify-center gap-2"><svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...</span>';
                    
                    console.log('Submitting barcode:', barcode);
                    
                    // Submit form
                    scanForm.submit();
                }
            }
            
            // Hardware scanner detection - GOOJPRT sends characters very fast then Enter/Tab
            barcodeInput.addEventListener('input', function(e) {
                if (isProcessing) return;
                
                const currentTime = Date.now();
                const currentValue = e.target.value;
                
                // Clear any pending submit
                if (submitTimeout) {
                    clearTimeout(submitTimeout);
                    submitTimeout = null;
                }
                
                lastInputTime = currentTime;
                
                // Check if we have enough characters for a valid barcode
                const cleanedValue = cleanBarcode(currentValue);
                if (cleanedValue.length >= MIN_BARCODE_LENGTH) {
                    // Wait a short time for scanner to finish sending all characters
                    submitTimeout = setTimeout(() => {
                        submitScan();
                    }, SCANNER_SUBMIT_DELAY);
                }
            });
            
            // Handle Enter key (GOOJPRT usually sends Enter at end of scan)
            barcodeInput.addEventListener('keydown', function(e) {
                if (isProcessing) return;
                
                // Enter, Tab, or carriage return - common scanner terminators
                if (e.key === 'Enter' || e.key === 'Tab' || e.keyCode === 13 || e.keyCode === 9) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Clear any pending timeout
                    if (submitTimeout) {
                        clearTimeout(submitTimeout);
                        submitTimeout = null;
                    }
                    
                    const barcode = cleanBarcode(barcodeInput.value);
                    if (barcode.length >= MIN_BARCODE_LENGTH) {
                        submitScan();
                    } else if (barcode.length > 0) {
                        // Show error for short barcode
                        barcodeInput.classList.add('border-red-500', 'shake');
                        setTimeout(() => {
                            barcodeInput.classList.remove('border-red-500', 'shake');
                        }, 500);
                    }
                    return false;
                }
            });
            
            // Also capture keypress for Enter (backup)
            barcodeInput.addEventListener('keypress', function(e) {
                if (isProcessing) return;
                
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (submitTimeout) {
                        clearTimeout(submitTimeout);
                        submitTimeout = null;
                    }
                    
                    const barcode = cleanBarcode(barcodeInput.value);
                    if (barcode.length >= MIN_BARCODE_LENGTH) {
                        submitScan();
                    }
                    return false;
                }
            });
            
            // Reset on focus
            barcodeInput.addEventListener('focus', function() {
                if (!isProcessing) {
                    scanBuffer = '';
                    lastInputTime = 0;
                    barcodeInput.readOnly = false;
                    barcodeInput.select(); // Select all text for easy replacement
                }
            });
            
            // Form submit validation
            scanForm.addEventListener('submit', function(e) {
                const barcode = cleanBarcode(barcodeInput.value);
                
                if (barcode.length < MIN_BARCODE_LENGTH) {
                    e.preventDefault();
                    barcodeInput.focus();
                    barcodeInput.classList.add('border-red-500');
                    setTimeout(() => {
                        barcodeInput.classList.remove('border-red-500');
                    }, 500);
                    return false;
                }
                
                isProcessing = true;
            });
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Debug: Log scanner input (remove in production)
            console.log('GOOJPRT Scanner Ready - Focus on input field');
        })();
    </script>
    
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .shake { animation: shake 0.3s ease-in-out; }
    </style>
</body>
</html>