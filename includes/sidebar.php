<?php
/**
 * Navigation Sidebar
 * Role-based menu items - Collapsible with grouped sections
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
        <a x-show="!sidebarCollapsed" x-transition href="<?php echo config('app_url'); ?>/pages/dashboard.php" class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden">
                <img src="<?php echo config('app_url'); ?>/assets/images/lex.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div class="overflow-hidden">
                <span class="text-sm font-semibold theme-text-primary block leading-tight whitespace-nowrap">Lexite</span>
                <span class="text-xs theme-text-muted block leading-tight whitespace-nowrap">Attendance</span>
            </div>
        </a>
        
        <button @click="sidebarCollapsed = !sidebarCollapsed" class="p-2 rounded-lg theme-text-muted hover:bg-gray-50 dark-mode:hover:bg-gray-700 focus:outline-none transition-colors flex-shrink-0">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>
    
    <nav class="py-4 px-3">
        <!-- MAIN Section -->
        <div class="mb-4">
            <div x-show="!sidebarCollapsed" class="px-3 mb-2">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Main</span>
            </div>
            <div x-show="sidebarCollapsed" class="border-b border-gray-100 mx-2 mb-3"></div>
            
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
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Dashboard</div>
                </a>
            </div>
            
            <!-- Scan Attendance -->
            <?php if (in_array($userRole, ['admin', 'operator', 'teacher'])): ?>
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/scan.php" 
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'scan.php' ? 'bg-green-50 text-green-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'scan.php' ? 'text-green-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Scan Attendance</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Scan Attendance</div>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- STUDENT MANAGEMENT Section -->
        <?php if (in_array($userRole, ['admin', 'operator', 'teacher'])): ?>
        <div class="mb-4">
            <div x-show="!sidebarCollapsed" class="px-3 mb-2">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Student Management</span>
            </div>
            <div x-show="sidebarCollapsed" class="border-b border-gray-100 mx-2 mb-3"></div>
            
            <!-- Students -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <?php $studentsLabel = $userRole === 'teacher' ? 'My Students' : 'Students'; ?>
                <a href="<?php echo config('app_url'); ?>/pages/students.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'students.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'students.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap"><?php echo $studentsLabel; ?></span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50"><?php echo $studentsLabel; ?></div>
                </a>
            </div>

            <!-- Classes -->
            <?php if (in_array($userRole, ['admin', 'teacher'])): ?>
            <div class="mb-1" x-data="{ tooltip: false }">
                <?php $classesLabel = $userRole === 'teacher' ? 'My Class' : 'Classes'; ?>
                <a href="<?php echo config('app_url'); ?>/pages/classes.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo in_array($currentPage, ['classes.php', 'class-students.php']) ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo in_array($currentPage, ['classes.php', 'class-students.php']) ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap"><?php echo $classesLabel; ?></span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50"><?php echo $classesLabel; ?></div>
                </a>
            </div>
            <?php endif; ?>
            
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
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Generate ID Cards</div>
                </a>
            </div>
            
        </div>
        <?php endif; ?>

        <!-- ATTENDANCE & REPORTS Section -->
        <div class="mb-4">
            <div x-show="!sidebarCollapsed" class="px-3 mb-2">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Attendance & Reports</span>
            </div>
            <div x-show="sidebarCollapsed" class="border-b border-gray-100 mx-2 mb-3"></div>
            
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
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Attendance</div>
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
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Reports</div>
                </a>
            </div>
        </div>

        <!-- ADMINISTRATION Section (Admin only) -->
        <?php if ($userRole === 'admin'): ?>
        <div class="mb-4">
            <div x-show="!sidebarCollapsed" class="px-3 mb-2">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Administration</span>
            </div>
            <div x-show="sidebarCollapsed" class="border-b border-gray-100 mx-2 mb-3"></div>
            
            <!-- Users -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/pages/users.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'users.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'users.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Users</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Users</div>
                </a>
            </div>

            <!-- School Years -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/pages/school-years.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'school-years.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'school-years.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">School Years</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">School Years</div>
                </a>
            </div>

            <!-- Student Placement (Requirement 1.1 - Admin only) -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/pages/student-placement.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'student-placement.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'student-placement.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Student Placement</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Student Placement</div>
                </a>
            </div>

            <!-- System Logs -->
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
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">System Logs</div>
                </a>
            </div>
            
            <!-- Subscriptions -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/pages/subscriptions.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'subscriptions.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'subscriptions.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Subscriptions</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Subscriptions</div>
                </a>
            </div>
            
            <!-- Settings -->
            <div class="mb-1" x-data="{ tooltip: false }">
                <a href="<?php echo config('app_url'); ?>/pages/settings.php"
                   @mouseenter="tooltip = sidebarCollapsed" 
                   @mouseleave="tooltip = false"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all relative <?php echo $currentPage === 'settings.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>"
                   :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0 <?php echo $currentPage === 'settings.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">Settings</span>
                    <div x-show="tooltip" x-transition class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">Settings</div>
                </a>
            </div>
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
           class="fixed inset-y-0 left-0 w-64 theme-bg-card shadow-xl z-50 transform overflow-y-auto">
        <div class="flex items-center justify-between h-16 px-6 theme-border border-b">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center overflow-hidden">
                    <img src="<?php echo config('app_url'); ?>/assets/images/lex.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <span class="text-sm font-semibold theme-text-primary block leading-tight">Lexite</span>
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
            <!-- MAIN Section -->
            <div class="mb-4">
                <div class="px-3 mb-2">
                    <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Main</span>
                </div>
                <a href="<?php echo config('app_url'); ?>/pages/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'dashboard.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <svg class="w-5 h-5 <?php echo $currentPage === 'dashboard.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
            </div>

            <!-- STUDENT MANAGEMENT Section -->
            <?php if (in_array($userRole, ['admin', 'operator', 'teacher'])): ?>
            <div class="mb-4">
                <div class="px-3 mb-2">
                    <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Student Management</span>
                </div>
                <div class="space-y-1">
                    <a href="<?php echo config('app_url'); ?>/pages/students.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'students.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'students.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <?php echo $userRole === 'teacher' ? 'My Students' : 'Students'; ?>
                    </a>
                    <?php if (in_array($userRole, ['admin', 'teacher'])): ?>
                    <a href="<?php echo config('app_url'); ?>/pages/classes.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo in_array($currentPage, ['classes.php', 'class-students.php']) ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo in_array($currentPage, ['classes.php', 'class-students.php']) ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <?php echo $userRole === 'teacher' ? 'My Class' : 'Classes'; ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo config('app_url'); ?>/pages/generate-id.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'generate-id.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'generate-id.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                        Generate ID Cards
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- ATTENDANCE & REPORTS Section -->
            <div class="mb-4">
                <div class="px-3 mb-2">
                    <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Attendance & Reports</span>
                </div>
                <div class="space-y-1">
                    <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'attendance-history.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'attendance-history.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Attendance
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/reports.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'reports.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'reports.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Reports
                    </a>
                </div>
            </div>

            <!-- ADMINISTRATION Section (Admin only) -->
            <?php if ($userRole === 'admin'): ?>
            <div class="mb-4">
                <div class="px-3 mb-2">
                    <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Administration</span>
                </div>
                <div class="space-y-1">
                    <a href="<?php echo config('app_url'); ?>/pages/users.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'users.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'users.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Users
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/school-years.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'school-years.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'school-years.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        School Years
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/student-placement.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'student-placement.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'student-placement.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Student Placement
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/logs.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'logs.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'logs.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        System Logs
                    </a>
                    <a href="<?php echo config('app_url'); ?>/pages/settings.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl <?php echo $currentPage === 'settings.php' ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <svg class="w-5 h-5 <?php echo $currentPage === 'settings.php' ? 'text-violet-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </nav>
    </aside>
</div>
