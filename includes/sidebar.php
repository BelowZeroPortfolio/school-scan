<?php
/**
 * Navigation Sidebar
 * Role-based menu items - Collapsible
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'viewer';
?>

<!-- Desktop Sidebar -->
<aside class="theme-bg-card hidden md:block theme-border border-r fixed left-0 top-0 h-screen overflow-y-auto z-50 transition-all duration-300"
       :class="sidebarCollapsed ? 'w-20' : 'w-64'">
    <!-- Logo Section -->
    <div class="px-6 py-5 theme-border border-b flex items-center" :class="sidebarCollapsed ? 'justify-center px-4' : 'justify-between px-6'">
        <!-- Logo (only when expanded) -->
        <a x-show="!sidebarCollapsed" x-transition href="<?php echo config('app_url'); ?>/pages/dashboard.php" class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                </svg>
            </div>
            <div class="overflow-hidden">
                <span class="text-sm font-semibold theme-text-primary block leading-tight whitespace-nowrap">Barcode</span>
                <span class="text-xs theme-text-muted block leading-tight whitespace-nowrap">Attendance</span>
            </div>
        </a>
        
        <!-- Burger Icon (always visible) -->
        <button @click="sidebarCollapsed = !sidebarCollapsed" class="p-2 rounded-lg theme-text-muted hover:bg-gray-50 dark-mode:hover:bg-gray-700 focus:outline-none transition-colors flex-shrink-0">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>
    
    <nav class="py-6 px-4">
        <!-- Dashboard -->
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/dashboard.php" 
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'dashboard.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'dashboard.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Dashboard</span>
                <!-- Tooltip -->
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    Dashboard
                </div>
            </a>
        </div>

        <!-- Students -->
        <?php if (in_array($userRole, ['admin', 'operator'])): ?>
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/students.php"
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'students.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'students.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Students</span>
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    Students
                </div>
            </a>
        </div>
        
        <!-- Generate ID Cards -->
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/generate-id.php"
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'generate-id.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'generate-id.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Generate ID Cards</span>
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    Generate ID Cards
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Attendance -->
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php"
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'attendance-history.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'attendance-history.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Attendance</span>
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    Attendance
                </div>
            </a>
        </div>

        <!-- Reports -->
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/reports.php"
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'reports.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'reports.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Reports</span>
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    Reports
                </div>
            </a>
        </div>

        <!-- System Logs -->
        <?php if ($userRole === 'admin'): ?>
        <div class="mb-1" x-data="{ tooltip: false }">
            <a href="<?php echo config('app_url'); ?>/pages/logs.php"
               @mouseenter="tooltip = sidebarCollapsed" 
               @mouseleave="tooltip = false"
               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'logs.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'logs.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">System Logs</span>
                <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
                    System Logs
                </div>
            </a>
        </div>
        <?php endif; ?>
    </nav>
</aside>

<!-- Mobile Sidebar -->
<div x-show="mobileSidebarOpen" 
     @click.away="mobileSidebarOpen = false"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 md:hidden">
    <div class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>
    <aside x-show="mobileSidebarOpen"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 w-64 theme-bg-card shadow-xl z-50 transform">
        <div class="flex items-center justify-between h-16 px-6 theme-border border-b">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-sm font-semibold theme-text-primary block leading-tight">Barcode</span>
                    <span class="text-xs theme-text-muted block leading-tight">Attendance</span>
                </div>
            </div>
            <button @click="mobileSidebarOpen = false" class="p-2 rounded-lg hover:bg-gray-100 dark-mode:hover:bg-gray-700">
                <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <nav class="py-4 px-4">
            <div class="space-y-1">
                <a href="<?php echo config('app_url'); ?>/pages/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'dashboard.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
                <?php if (in_array($userRole, ['admin', 'operator'])): ?>
                <a href="<?php echo config('app_url'); ?>/pages/students.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'students.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Students
                </a>
                <a href="<?php echo config('app_url'); ?>/pages/generate-id.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'generate-id.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    Generate ID Cards
                </a>
                <?php endif; ?>
                <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'attendance-history.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Attendance
                </a>
                <a href="<?php echo config('app_url'); ?>/pages/reports.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'reports.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports
                </a>
                <?php if ($userRole === 'admin'): ?>
                <a href="<?php echo config('app_url'); ?>/pages/logs.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'logs.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    System Logs
                </a>
                <?php endif; ?>
            </div>
        </nav>
    </aside>
</div>
