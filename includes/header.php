<?php
/**
 * Common Header
 * Navigation menu and user info
 */

// Ensure user is authenticated
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}

// Load school year module for header display
require_once __DIR__ . '/schoolyear.php';

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Get active school year for header display (Requirements: 1.4)
$activeSchoolYear = getActiveSchoolYear();
$allSchoolYears = getAllSchoolYears();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(config('app_name', 'Attendance System')); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo config('app_url'); ?>/assets/images/icon.svg">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Theme System -->
    <?php require_once __DIR__ . '/theme.php'; ?>
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Light mode decorative background */
        .page-background {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 25%, #ede9fe 50%, #f5f3ff 75%, #faf5ff 100%);
            overflow: hidden;
        }
        
        .page-background::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.05) 40%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
        }
        
        .page-background::after {
            content: '';
            position: absolute;
            bottom: -10%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.12) 0%, rgba(167, 139, 250, 0.04) 40%, transparent 70%);
            border-radius: 50%;
            animation: float 25s ease-in-out infinite reverse;
        }
        
        .blob-accent {
            position: fixed;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(196, 181, 253, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
            animation: float 30s ease-in-out infinite;
        }
        
        .blob-accent-1 {
            top: 30%;
            left: 10%;
            animation-delay: -5s;
        }
        
        .blob-accent-2 {
            top: 60%;
            right: 15%;
            width: 250px;
            height: 250px;
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(20px, -20px) scale(1.05); }
            50% { transform: translate(-10px, 20px) scale(0.95); }
            75% { transform: translate(-20px, -10px) scale(1.02); }
        }
        
        /* Hide decorative background in dark mode */
        .dark-mode .page-background,
        .dark-mode .blob-accent {
            display: none;
        }
    </style>
</head>
<body class="theme-bg-primary" x-data="{ sidebarCollapsed: false, mobileSidebarOpen: false }">
    <!-- Decorative Background (Light Mode Only) -->
    <div class="page-background"></div>
    <div class="blob-accent blob-accent-1"></div>
    <div class="blob-accent blob-accent-2"></div>
    
    <div class="min-h-screen flex flex-col">
        <!-- Top Navigation Bar -->
        <nav class="theme-bg-card theme-border border-b fixed top-0 left-0 right-0 z-40 transition-all duration-300" 
             :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
            <div class="px-4 sm:px-6 lg:px-8 max-w-full">
                <div class="flex justify-end items-center h-16 max-w-full">
                    <!-- Mobile menu button (left side on mobile only) -->
                    <button @click="mobileSidebarOpen = !mobileSidebarOpen" class="md:hidden absolute left-4 p-2 rounded-lg theme-text-muted hover:bg-gray-50 dark-mode:hover:bg-gray-700 focus:outline-none transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    
                    <!-- Right Side -->
                    <div class="flex items-center gap-2">
                        <!-- School Year Selector (Requirements: 1.4) -->
                        <?php if ($activeSchoolYear || !empty($allSchoolYears)): ?>
                        <div class="relative" x-data="{ schoolYearOpen: false }">
                            <button @click="schoolYearOpen = !schoolYearOpen" 
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium theme-text-secondary hover:bg-gray-50 dark-mode:hover:bg-gray-700 transition-colors border border-gray-200 dark-mode:border-gray-600">
                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="hidden sm:inline"><?php echo $activeSchoolYear ? e($activeSchoolYear['name']) : 'No Active Year'; ?></span>
                                <svg class="w-3 h-3 theme-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <!-- School Year Dropdown -->
                            <div x-show="schoolYearOpen" @click.away="schoolYearOpen = false" x-transition x-cloak 
                                 class="absolute right-0 top-full mt-2 w-48 theme-bg-card rounded-xl shadow-xl py-2 z-[60] theme-border border">
                                <div class="px-4 py-2 theme-border border-b">
                                    <span class="text-xs font-semibold theme-text-muted uppercase tracking-wider">School Year</span>
                                </div>
                                <?php if (empty($allSchoolYears)): ?>
                                    <div class="px-4 py-3 text-sm theme-text-muted">No school years configured</div>
                                <?php else: ?>
                                    <?php foreach ($allSchoolYears as $sy): ?>
                                        <div class="px-4 py-2 text-sm flex items-center justify-between <?php echo $sy['is_active'] ? 'bg-violet-50 dark-mode:bg-violet-900/20' : 'hover:bg-gray-50 dark-mode:hover:bg-gray-700'; ?>">
                                            <span class="<?php echo $sy['is_active'] ? 'font-semibold text-violet-700 dark-mode:text-violet-300' : 'theme-text-secondary'; ?>">
                                                <?php echo e($sy['name']); ?>
                                            </span>
                                            <?php if ($sy['is_active']): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark-mode:bg-violet-800 dark-mode:text-violet-200">Active</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (hasRole('admin')): ?>
                                    <div class="theme-border border-t mt-2 pt-2">
                                        <a href="<?php echo config('app_url'); ?>/pages/school-years.php" 
                                           class="flex items-center gap-2 px-4 py-2 text-sm theme-text-secondary hover:bg-gray-50 dark-mode:hover:bg-gray-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            Manage School Years
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Theme Toggle -->
                        <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark-mode:hover:bg-gray-700 transition-colors" aria-label="Toggle theme">
                            <svg class="w-5 h-5 text-yellow-500 dark-only" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                            </svg>
                            <svg class="w-5 h-5 text-gray-700 light-only" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                            </svg>
                        </button>
                        
                        <!-- User Menu -->
                        <div class="flex items-center" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-3 hover:bg-gray-50 dark-mode:hover:bg-gray-700 px-3 py-2 rounded-xl focus:outline-none transition-colors">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                    <?php echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <div class="text-sm font-medium theme-text-primary"><?php echo e($currentUser['full_name'] ?? 'User'); ?></div>
                                    <div class="text-xs theme-text-muted"><?php echo e(ucfirst($currentUser['role'] ?? 'viewer')); ?></div>
                                </div>
                                <svg class="w-4 h-4 theme-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div x-show="open" @click.away="open = false" x-transition x-cloak class="absolute right-0 top-14 mt-2 w-56 theme-bg-card rounded-xl shadow-xl py-2 z-[60] theme-border border">
                                <div class="px-4 py-3 theme-border border-b">
                                    <div class="text-sm font-semibold theme-text-primary"><?php echo e($currentUser['full_name'] ?? 'User'); ?></div>
                                    <div class="text-xs theme-text-muted mt-0.5"><?php echo e(ucfirst($currentUser['role'] ?? 'viewer')); ?></div>
                                </div>
                                <a href="<?php echo config('app_url'); ?>/pages/logout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm theme-text-secondary hover:bg-gray-50 dark-mode:hover:bg-gray-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <div class="flex flex-1 w-full">
