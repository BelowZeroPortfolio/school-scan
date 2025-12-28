<?php
/**
 * Landing Page
 * Public-facing marketing page for the QR Attendance System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn()) {
    header('Location: ' . config('app_url') . '/pages/dashboard.php');
    exit;
}

$appName = config('app_name', 'QR Attendance System');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($appName); ?> - Smart School Attendance</title>
    <link rel="icon" type="image/png" href="<?php echo config('app_url'); ?>/assets/images/lex.png">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php require_once __DIR__ . '/includes/theme.php'; ?>
    <style>
        [x-cloak] { display: none !important; }
        .gradient-text {
            background: linear-gradient(135deg, #8B5CF6 0%, #3B82F6 50%, #8B5CF6 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient 3s ease infinite;
        }
        @keyframes gradient { 0% { background-position: 0% center; } 50% { background-position: 100% center; } 100% { background-position: 0% center; } }
        .float-animation { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-20px); } }
    </style>
</head>
<body class="theme-bg-primary" x-data="{ mobileMenu: false }">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 theme-bg-secondary/80 backdrop-blur-lg theme-border border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="#" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden">
                    <img src="<?php echo config('app_url'); ?>/assets/images/lex.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <span class="hidden sm:block text-lg font-bold theme-text-primary">QR Attendance</span>
            </a>
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm font-medium theme-text-secondary hover:text-violet-600">Features</a>
                <a href="#how-it-works" class="text-sm font-medium theme-text-secondary hover:text-violet-600">How It Works</a>
                <a href="#benefits" class="text-sm font-medium theme-text-secondary hover:text-violet-600">Benefits</a>
                <a href="#contact" class="text-sm font-medium theme-text-secondary hover:text-violet-600">Contact</a>
            </div>
            <div class="flex items-center gap-3">
                <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark-mode:hover:bg-gray-700">
                    <svg class="w-5 h-5 text-yellow-500 dark-only" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                    <svg class="w-5 h-5 text-gray-700 light-only" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>
                <a href="<?php echo config('app_url'); ?>/pages/login.php" class="hidden sm:inline-flex px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700">Sign In</a>
                <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark-mode:hover:bg-gray-700">
                    <svg class="w-6 h-6 theme-text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <div x-show="mobileMenu" x-cloak class="md:hidden theme-bg-card border-t theme-border px-4 py-4 space-y-2">
        <a href="#features" @click="mobileMenu=false" class="block px-4 py-2 text-sm theme-text-secondary rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700">Features</a>
        <a href="#how-it-works" @click="mobileMenu=false" class="block px-4 py-2 text-sm theme-text-secondary rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700">How It Works</a>
        <a href="#benefits" @click="mobileMenu=false" class="block px-4 py-2 text-sm theme-text-secondary rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700">Benefits</a>
        <a href="#contact" @click="mobileMenu=false" class="block px-4 py-2 text-sm theme-text-secondary rounded-lg hover:bg-gray-50 dark-mode:hover:bg-gray-700">Contact</a>
        <a href="<?php echo config('app_url'); ?>/pages/login.php" class="block px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg text-center">Sign In</a>
    </div>
</nav>

<!-- Hero Section -->
<section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-violet-100 dark-mode:bg-violet-900/30 rounded-full mb-6">
                    <span class="w-2 h-2 bg-violet-500 rounded-full animate-pulse"></span>
                    <span class="text-sm font-medium text-violet-700 dark-mode:text-violet-300">Smart Attendance Solution</span>
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold theme-text-primary mb-6 leading-tight">
                    Modern School <span class="gradient-text">Attendance</span> Made Simple
                </h1>
                <p class="text-lg theme-text-secondary mb-8 max-w-xl mx-auto lg:mx-0">
                    Streamline your school's attendance tracking with our QR code-based system. Fast scanning, real-time notifications, and comprehensive reports.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                    <a href="<?php echo config('app_url'); ?>/pages/login.php" class="inline-flex items-center justify-center px-8 py-4 bg-violet-600 text-white font-semibold rounded-xl hover:bg-violet-700 shadow-lg shadow-violet-500/30">
                        Get Started <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="#features" class="inline-flex items-center justify-center px-8 py-4 bg-white dark-mode:bg-gray-800 theme-text-primary font-semibold rounded-xl border border-gray-200 dark-mode:border-gray-700 hover:border-violet-300">Learn More</a>
                </div>
                <div class="grid grid-cols-3 gap-6 mt-12 pt-8 border-t theme-border">
                    <div><div class="text-3xl font-bold text-violet-600">99%</div><div class="text-sm theme-text-muted">Accuracy</div></div>
                    <div><div class="text-3xl font-bold text-violet-600">&lt;1s</div><div class="text-sm theme-text-muted">Scan Time</div></div>
                    <div><div class="text-3xl font-bold text-violet-600">24/7</div><div class="text-sm theme-text-muted">Available</div></div>
                </div>
            </div>
            <div class="relative hidden lg:block float-animation">
                <div class="theme-bg-card rounded-3xl shadow-2xl p-8 border theme-border">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-violet-500 to-violet-600 rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        </div>
                        <div><div class="text-lg font-bold theme-text-primary">Scan Complete</div><div class="text-sm theme-text-muted">Student checked in</div></div>
                        <div class="ml-auto w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark-mode:bg-gray-800 rounded-xl"><span class="text-sm theme-text-muted">Student</span><span class="text-sm font-medium theme-text-primary">Juan Dela Cruz</span></div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark-mode:bg-gray-800 rounded-xl"><span class="text-sm theme-text-muted">Time</span><span class="text-sm font-medium text-green-600">7:45 AM</span></div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark-mode:bg-gray-800 rounded-xl"><span class="text-sm theme-text-muted">Status</span><span class="text-sm font-medium text-green-600">On Time ✓</span></div>
                    </div>
                </div>
                <div class="absolute -top-4 -right-4 bg-green-500 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">SMS Sent ✓</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-20 px-4 sm:px-6 lg:px-8 theme-bg-secondary">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold theme-text-primary mb-4">Powerful Features</h2>
            <p class="text-lg theme-text-secondary max-w-2xl mx-auto">Everything you need to manage student attendance efficiently</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-violet-100 dark-mode:bg-violet-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">QR Code Scanning</h3>
                <p class="theme-text-secondary">Fast and accurate QR code scanning using hardware scanners or device cameras.</p>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-green-100 dark-mode:bg-green-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">SMS Notifications</h3>
                <p class="theme-text-secondary">Automatic SMS alerts to parents when students arrive or leave school.</p>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-blue-100 dark-mode:bg-blue-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">Reports & Analytics</h3>
                <p class="theme-text-secondary">Comprehensive attendance reports with CSV, PDF, and Excel export options.</p>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-amber-100 dark-mode:bg-amber-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">Student Management</h3>
                <p class="theme-text-secondary">Complete student database with class assignments and ID card generation.</p>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-red-100 dark-mode:bg-red-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">Role-Based Access</h3>
                <p class="theme-text-secondary">Secure access control with Admin, Teacher, Operator, and Viewer roles.</p>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border hover:shadow-xl transition-all">
                <div class="w-14 h-14 bg-indigo-100 dark-mode:bg-indigo-900/30 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">School Year Management</h3>
                <p class="theme-text-secondary">Manage multiple school years with student placement and promotion tools.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-20 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold theme-text-primary mb-4">How It Works</h2>
            <p class="text-lg theme-text-secondary max-w-2xl mx-auto">Simple three-step process for effortless attendance tracking</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="relative text-center">
                <div class="w-20 h-20 bg-violet-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg shadow-violet-500/30">1</div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">Register Students</h3>
                <p class="theme-text-secondary">Add students to the system. Each student gets a unique QR code ID automatically.</p>
                <div class="hidden md:block absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-violet-500 to-transparent"></div>
            </div>
            <div class="relative text-center">
                <div class="w-20 h-20 bg-violet-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg shadow-violet-500/30">2</div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">Scan Attendance</h3>
                <p class="theme-text-secondary">Teachers scan student QR codes during arrival and dismissal. System records time automatically.</p>
                <div class="hidden md:block absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-violet-500 to-transparent"></div>
            </div>
            <div class="text-center">
                <div class="w-20 h-20 bg-violet-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg shadow-violet-500/30">3</div>
                <h3 class="text-xl font-bold theme-text-primary mb-3">View Reports</h3>
                <p class="theme-text-secondary">Access detailed attendance reports, export data, and track patterns over time.</p>
            </div>
        </div>
    </div>
</section>

<!-- Benefits Section -->
<section id="benefits" class="py-20 px-4 sm:px-6 lg:px-8 theme-bg-secondary">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl sm:text-4xl font-bold theme-text-primary mb-6">Why Choose Our System?</h2>
                <p class="text-lg theme-text-secondary mb-8">Our QR attendance system is designed specifically for schools, making attendance tracking effortless.</p>
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-green-100 dark-mode:bg-green-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div><h4 class="font-semibold theme-text-primary mb-1">Save Time</h4><p class="text-sm theme-text-secondary">Reduce manual attendance taking from minutes to seconds.</p></div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-green-100 dark-mode:bg-green-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div><h4 class="font-semibold theme-text-primary mb-1">Improve Accuracy</h4><p class="text-sm theme-text-secondary">Eliminate human errors with automated QR code-based recording.</p></div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-green-100 dark-mode:bg-green-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div><h4 class="font-semibold theme-text-primary mb-1">Keep Parents Informed</h4><p class="text-sm theme-text-secondary">Automatic SMS notifications when their child arrives or leaves.</p></div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-green-100 dark-mode:bg-green-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div><h4 class="font-semibold theme-text-primary mb-1">Easy to Use</h4><p class="text-sm theme-text-secondary">Intuitive interface requires minimal training. Get started in minutes.</p></div>
                    </div>
                </div>
            </div>
            <div class="theme-bg-card rounded-3xl p-8 border theme-border shadow-xl">
                <h3 class="text-2xl font-bold theme-text-primary mb-8 text-center">System Capabilities</h3>
                <div class="grid grid-cols-2 gap-6">
                    <div class="text-center p-6 bg-violet-50 dark-mode:bg-violet-900/20 rounded-2xl"><div class="text-4xl font-bold text-violet-600 mb-2">∞</div><div class="text-sm theme-text-muted">Unlimited Students</div></div>
                    <div class="text-center p-6 bg-green-50 dark-mode:bg-green-900/20 rounded-2xl"><div class="text-4xl font-bold text-green-600 mb-2">4</div><div class="text-sm theme-text-muted">User Roles</div></div>
                    <div class="text-center p-6 bg-blue-50 dark-mode:bg-blue-900/20 rounded-2xl"><div class="text-4xl font-bold text-blue-600 mb-2">3</div><div class="text-sm theme-text-muted">Export Formats</div></div>
                    <div class="text-center p-6 bg-amber-50 dark-mode:bg-amber-900/20 rounded-2xl"><div class="text-4xl font-bold text-amber-600 mb-2">24/7</div><div class="text-sm theme-text-muted">System Access</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-violet-600 to-violet-800">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-white mb-6">Ready to Modernize Your Attendance System?</h2>
        <p class="text-lg text-violet-100 mb-8 max-w-2xl mx-auto">Join schools that have already simplified their attendance tracking. Get started today.</p>
        <a href="<?php echo config('app_url'); ?>/pages/login.php" class="inline-flex items-center px-8 py-4 bg-white text-violet-700 font-semibold rounded-xl hover:bg-violet-50 shadow-lg">
            Get Started Now <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="py-20 px-4 sm:px-6 lg:px-8 theme-bg-secondary">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <div>
                <h2 class="text-3xl sm:text-4xl font-bold theme-text-primary mb-6">Get In Touch</h2>
                <p class="text-lg theme-text-secondary mb-8">Have questions about our attendance system? We're here to help.</p>
                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-violet-100 dark-mode:bg-violet-900/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div><div class="text-sm theme-text-muted">Email</div><div class="font-medium theme-text-primary">support@example.com</div></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-violet-100 dark-mode:bg-violet-900/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div><div class="text-sm theme-text-muted">Phone</div><div class="font-medium theme-text-primary">+63 912 345 6789</div></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-violet-100 dark-mode:bg-violet-900/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div><div class="text-sm theme-text-muted">Location</div><div class="font-medium theme-text-primary">Philippines</div></div>
                    </div>
                </div>
            </div>
            <div class="theme-bg-card rounded-2xl p-8 border theme-border">
                <h3 class="text-xl font-bold theme-text-primary mb-6">Send us a Message</h3>
                <form class="space-y-4">
                    <div><label class="block text-sm font-medium theme-text-secondary mb-1">Name</label><input type="text" class="w-full px-4 py-3 border border-gray-200 dark-mode:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 theme-bg-primary theme-text-primary" placeholder="Your name"></div>
                    <div><label class="block text-sm font-medium theme-text-secondary mb-1">Email</label><input type="email" class="w-full px-4 py-3 border border-gray-200 dark-mode:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 theme-bg-primary theme-text-primary" placeholder="your@email.com"></div>
                    <div><label class="block text-sm font-medium theme-text-secondary mb-1">Message</label><textarea rows="4" class="w-full px-4 py-3 border border-gray-200 dark-mode:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 theme-bg-primary theme-text-primary resize-none" placeholder="How can we help?"></textarea></div>
                    <button type="submit" class="w-full px-6 py-3 bg-violet-600 text-white font-semibold rounded-xl hover:bg-violet-700">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-12 px-4 sm:px-6 lg:px-8 theme-bg-card border-t theme-border">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden"><img src="<?php echo config('app_url'); ?>/assets/images/lex.png" alt="Logo" class="w-full h-full object-contain"></div>
                <span class="font-bold theme-text-primary">QR Attendance</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="#features" class="text-sm theme-text-secondary hover:text-violet-600">Features</a>
                <a href="#how-it-works" class="text-sm theme-text-secondary hover:text-violet-600">How It Works</a>
                <a href="#benefits" class="text-sm theme-text-secondary hover:text-violet-600">Benefits</a>
                <a href="#contact" class="text-sm theme-text-secondary hover:text-violet-600">Contact</a>
            </div>
            <p class="text-sm theme-text-muted">© <?php echo date('Y'); ?> All rights reserved.</p>
        </div>
    </div>
</footer>
</body>
</html>
