<?php
/**
 * Dashboard Page
 * Display attendance statistics and trends
 * 
 * Requirements: 7.1, 10.4 - Filter stats by teacher's classes for teacher role
 */

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';
require_once __DIR__ . '/../includes/reports.php';

// Require authentication
requireAuth();

// Get current user for role-based filtering
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'viewer';
$userId = $currentUser['id'] ?? null;
$isPrincipalUser = isPrincipal();

// Get active school year (Requirements: 7.1, 10.4)
$activeSchoolYear = getActiveSchoolYear();
$schoolYearId = $activeSchoolYear ? $activeSchoolYear['id'] : null;

// Initialize variables for teacher-specific filtering
$teacherClasses = [];
$classBreakdown = [];
$teacherPerformance = [];
$teacherSummary = [];
$teacherLoginActivity = [];
$teacherLoginTrend = [];

// Get today's statistics - filtered by teacher's classes if teacher role (Requirements: 7.1)
if (isTeacher() && $userId) {
    // Get teacher's classes for the active school year
    $teacherClasses = getTeacherClasses($userId, $schoolYearId);
    $classIds = array_column($teacherClasses, 'id');
    
    if (!empty($classIds)) {
        // Get stats filtered by teacher's classes
        $todayStats = getTodayAttendanceStatsForClasses($classIds, $schoolYearId);
        $recentAttendance = getRecentAttendanceForClasses($classIds, 1, 10, $schoolYearId);
    } else {
        // No classes assigned - show empty stats
        $todayStats = [
            'total_students' => 0,
            'present' => 0,
            'late' => 0,
            'percentage' => 0
        ];
        $recentAttendance = ['records' => [], 'total' => 0, 'total_pages' => 0];
    }
} else {
    // Admin/Principal/Operator/Viewer - show all stats
    $todayStats = getTodayAttendanceStats($schoolYearId);
    $recentAttendance = getRecentAttendance(1, 10, $schoolYearId);
    
    // Get class breakdown for admin and principal (Requirements: 10.4)
    if ((isAdmin() || $isPrincipalUser) && $schoolYearId) {
        $allClasses = getClassesBySchoolYear($schoolYearId);
        foreach ($allClasses as $class) {
            $classStats = getTodayAttendanceStatsForClasses([$class['id']], $schoolYearId);
            $classBreakdown[] = [
                'class_name' => $class['grade_level'] . ' - ' . $class['section'],
                'teacher_name' => $class['teacher_name'],
                'total_students' => $classStats['total_students'],
                'present' => $classStats['present'],
                'late' => $classStats['late'],
                'percentage' => $classStats['percentage']
            ];
        }
    }
    
    // Get teacher performance report for Admin and Principal
    if ((isAdmin() || $isPrincipalUser) && $schoolYearId) {
        $teacherPerformance = getTeacherPerformanceReport($schoolYearId);
        $teacherSummary = getTeacherAttendanceSummary($schoolYearId);
    }
    
    // Get teacher login activity for Admin and Principal
    if (isAdmin() || $isPrincipalUser) {
        $teacherLoginActivity = getTeacherLoginActivityToday();
        $teacherLoginTrend = getTeacherLoginTrend(7);
    }
}

// Calculate absent count for today
$absentToday = $todayStats['total_students'] - $todayStats['present'] - $todayStats['late'];

// Calculate percentages for donut chart
$presentPercent = $todayStats['total_students'] > 0 ? round(($todayStats['present'] / $todayStats['total_students']) * 100) : 0;
$absentPercent = 100 - $presentPercent;

// Page title
$pageTitle = 'Dashboard';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            
            <!-- Page Header -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Dashboard</h1>
                <p class="text-sm sm:text-base text-gray-500 mt-1">Welcome back, <?php echo e($currentUser['full_name']); ?></p>
            </div>
            
            <?php echo displayFlash(); ?>
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Total Students -->
                <div class="bg-white rounded-xl p-6 border border-gray-100 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Students</p>
                            <p class="text-3xl font-semibold text-gray-900"><?php echo number_format($todayStats['total_students']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Present Today -->
                <div class="bg-white rounded-xl p-6 border border-gray-100 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Present Today</p>
                            <p class="text-3xl font-semibold text-gray-900"><?php echo number_format($todayStats['present']); ?></p>
                            <p class="text-xs text-green-600 mt-1 font-medium">↑ <?php echo $presentPercent; ?>%</p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Absent Today -->
                <div class="bg-white rounded-xl p-6 border border-gray-100 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Absent Today</p>
                            <p class="text-3xl font-semibold text-gray-900"><?php echo number_format($absentToday); ?></p>
                            <p class="text-xs text-red-600 mt-1 font-medium">↓ <?php echo $absentPercent; ?>%</p>
                        </div>
                        <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Rate -->
                <div class="bg-gradient-to-br from-violet-600 to-violet-700 rounded-xl p-6 text-white hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-violet-100 mb-1">Attendance Rate</p>
                            <p class="text-3xl font-semibold"><?php echo number_format($todayStats['percentage'], 1); ?>%</p>
                            <p class="text-xs text-violet-200 mt-1">Overall performance</p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Attendance Distribution -->
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Attendance Distribution</h3>
                    <div class="flex items-center justify-center">
                        <div style="position: relative; width: 200px; height: 200px;">
                            <canvas id="donutChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-3xl font-bold text-gray-900"><?php echo $presentPercent; ?>%</span>
                                <span class="text-xs text-gray-500 mt-1">Present</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-center gap-6 mt-6">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-violet-600"></div>
                            <span class="text-sm text-gray-600">Present</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-gray-200"></div>
                            <span class="text-sm text-gray-600">Absent</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="lg:col-span-2 bg-white rounded-xl p-4 sm:p-6 border border-gray-100">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <a href="<?php echo config('app_url'); ?>/pages/students.php" class="group p-4 border border-gray-200 rounded-xl hover:border-blue-600 hover:bg-blue-50 transition-all duration-200">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-blue-600 transition-colors">
                                <svg class="w-5 h-5 text-blue-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-1">Manage Students</h4>
                            <p class="text-xs text-gray-500">View and edit students</p>
                        </a>
                        
                        <a href="<?php echo config('app_url'); ?>/pages/reports.php" class="group p-4 border border-gray-200 rounded-xl hover:border-green-600 hover:bg-green-50 transition-all duration-200">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-green-600 transition-colors">
                                <svg class="w-5 h-5 text-green-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-1">Generate Reports</h4>
                            <p class="text-xs text-gray-500">Export attendance data</p>
                        </a>
                        
                        <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php" class="group p-4 border border-gray-200 rounded-xl hover:border-amber-600 hover:bg-amber-50 transition-all duration-200">
                            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-amber-600 transition-colors">
                                <svg class="w-5 h-5 text-amber-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-1">View History</h4>
                            <p class="text-xs text-gray-500">Past attendance records</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Class Breakdown for Admin and Principal (Requirements: 10.4) -->
            <?php if ((isAdmin() || $isPrincipalUser) && !empty($classBreakdown)): ?>
            <div class="bg-white rounded-xl border border-gray-100 mb-6 sm:mb-8">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">Class Breakdown</h3>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">Today's attendance by class<?php echo $activeSchoolYear ? ' - ' . e($activeSchoolYear['name']) : ''; ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($classBreakdown as $classData): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900"><?php echo e($classData['class_name']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                        <span class="text-sm text-gray-600"><?php echo e($classData['teacher_name']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="text-sm text-gray-900"><?php echo number_format($classData['total_students']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700"><?php echo number_format($classData['present']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700"><?php echo number_format($classData['late']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="text-sm font-medium <?php echo $classData['percentage'] >= 80 ? 'text-green-600' : ($classData['percentage'] >= 60 ? 'text-amber-600' : 'text-red-600'); ?>">
                                            <?php echo number_format($classData['percentage'], 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Teacher Activity Today for Admin and Principal -->
            <?php if ((isAdmin() || $isPrincipalUser) && !empty($teacherLoginActivity)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Teacher Activity Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl p-4 sm:p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Teacher Login Activity</h3>
                            <p class="text-xs sm:text-sm text-gray-500 mt-1">Last 7 days login trend</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span>
                                <?php echo $teacherLoginActivity['active_count']; ?> Active Today
                            </span>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="teacherLoginChart"></canvas>
                    </div>
                </div>
                
                <!-- Today's Activity Summary -->
                <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Today's Activity</h3>
                    <div class="text-center mb-4">
                        <div class="relative inline-flex items-center justify-center">
                            <svg class="w-32 h-32 transform -rotate-90">
                                <circle cx="64" cy="64" r="56" stroke="#E5E7EB" stroke-width="12" fill="none"/>
                                <circle cx="64" cy="64" r="56" stroke="#8B5CF6" stroke-width="12" fill="none"
                                        stroke-dasharray="<?php echo 2 * 3.14159 * 56; ?>"
                                        stroke-dashoffset="<?php echo 2 * 3.14159 * 56 * (1 - $teacherLoginActivity['activity_rate'] / 100); ?>"
                                        stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-2xl font-bold text-gray-900"><?php echo $teacherLoginActivity['activity_rate']; ?>%</span>
                                <span class="text-xs text-gray-500">Active</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-green-50 rounded-lg p-3 text-center">
                            <p class="text-2xl font-bold text-green-700"><?php echo $teacherLoginActivity['active_count']; ?></p>
                            <p class="text-xs text-green-600">Logged In</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-2xl font-bold text-gray-700"><?php echo $teacherLoginActivity['inactive_count']; ?></p>
                            <p class="text-xs text-gray-600">Not Yet</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 text-center mt-3"><?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <!-- Active Teachers List -->
            <?php if (!empty($teacherLoginActivity['active_today']) || !empty($teacherLoginActivity['inactive_today'])): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Active Teachers -->
                <div class="bg-white rounded-xl border border-gray-100">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <h4 class="font-semibold text-gray-900">Active Teachers Today</h4>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($teacherLoginActivity['active_today']); ?> teachers</span>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if (empty($teacherLoginActivity['active_today'])): ?>
                            <div class="px-4 py-8 text-center">
                                <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">No teachers logged in yet today</p>
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($teacherLoginActivity['active_today'] as $teacher): ?>
                                    <li class="px-4 sm:px-6 py-3 flex items-center justify-between hover:bg-gray-50/50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                                <?php echo strtoupper(substr($teacher['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?php echo e($teacher['full_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo e($teacher['email'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-green-600 font-medium"><?php echo date('g:i A', strtotime($teacher['last_login'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Inactive Teachers -->
                <div class="bg-white rounded-xl border border-gray-100">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                            <h4 class="font-semibold text-gray-900">Not Logged In Today</h4>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($teacherLoginActivity['inactive_today']); ?> teachers</span>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if (empty($teacherLoginActivity['inactive_today'])): ?>
                            <div class="px-4 py-8 text-center">
                                <svg class="mx-auto h-10 w-10 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-sm text-green-600">All teachers have logged in today!</p>
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($teacherLoginActivity['inactive_today'] as $teacher): ?>
                                    <li class="px-4 sm:px-6 py-3 flex items-center justify-between hover:bg-gray-50/50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 font-semibold text-xs">
                                                <?php echo strtoupper(substr($teacher['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?php echo e($teacher['full_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo e($teacher['email'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-400">
                                            <?php echo $teacher['last_login'] ? 'Last: ' . date('M j', strtotime($teacher['last_login'])) : 'Never'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Teacher Performance Report for Admin and Principal -->
            <?php if ((isAdmin() || $isPrincipalUser) && !empty($teacherPerformance)): ?>
            <div class="bg-white rounded-xl border border-gray-100 mb-6 sm:mb-8">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Teacher Performance Report</h3>
                            <p class="text-xs sm:text-sm text-gray-500 mt-1">Attendance rates by teacher (Last 30 days)<?php echo $activeSchoolYear ? ' - ' . e($activeSchoolYear['name']) : ''; ?></p>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo number_format($teacherSummary['total_teachers']); ?></p>
                                    <p class="text-xs text-gray-500">Teachers</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo number_format($teacherSummary['total_classes']); ?></p>
                                    <p class="text-xs text-gray-500">Classes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classes</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($teacherPerformance as $teacher): ?>
                                <?php 
                                $rate = $teacher['attendance_rate'] ?? 0;
                                $rateColor = $rate >= 90 ? 'text-green-600' : ($rate >= 75 ? 'text-amber-600' : 'text-red-600');
                                $rateBg = $rate >= 90 ? 'bg-green-50' : ($rate >= 75 ? 'bg-amber-50' : 'bg-red-50');
                                ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-9 h-9 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                                <?php echo strtoupper(substr($teacher['teacher_name'], 0, 2)); ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo e($teacher['teacher_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo e($teacher['teacher_email'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3">
                                        <div class="max-w-xs">
                                            <p class="text-sm text-gray-600 truncate" title="<?php echo e($teacher['classes']); ?>"><?php echo e($teacher['classes']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo number_format($teacher['class_count']); ?> class<?php echo $teacher['class_count'] != 1 ? 'es' : ''; ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($teacher['total_students']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700"><?php echo number_format($teacher['present_count']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700"><?php echo number_format($teacher['late_count']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700"><?php echo number_format($teacher['absent_count']); ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="w-16 h-2 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full <?php echo $rateBg; ?>" style="width: <?php echo min($rate, 100); ?>%"></div>
                                            </div>
                                            <span class="text-sm font-semibold <?php echo $rateColor; ?>"><?php echo number_format($rate, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    <p class="text-xs text-gray-500">
                        <span class="inline-flex items-center mr-4"><span class="w-2 h-2 rounded-full bg-green-500 mr-1"></span> ≥90% Excellent</span>
                        <span class="inline-flex items-center mr-4"><span class="w-2 h-2 rounded-full bg-amber-500 mr-1"></span> 75-89% Good</span>
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-1"></span> &lt;75% Needs Attention</span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Teacher's Classes Summary (Requirements: 7.1) -->
            <?php if (isTeacher() && !empty($teacherClasses)): ?>
            <div class="bg-white rounded-xl border border-gray-100 mb-6 sm:mb-8">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">My Classes</h3>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1"><?php echo $activeSchoolYear ? e($activeSchoolYear['name']) : 'No active school year'; ?></p>
                </div>
                <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($teacherClasses as $class): ?>
                        <a href="<?php echo config('app_url'); ?>/pages/class-students.php?id=<?php echo $class['id']; ?>" 
                           class="block p-4 border border-gray-200 rounded-xl hover:border-violet-300 hover:bg-violet-50/50 transition-all">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-900"><?php echo e($class['grade_level'] . ' - ' . $class['section']); ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                                    <?php echo number_format($class['student_count']); ?> students
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">Click to view students</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Attendance Table -->
            <div class="bg-white rounded-xl border border-gray-100">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Recent Attendance</h3>
                            <p class="text-xs sm:text-sm text-gray-500 mt-1">Latest attendance records</p>
                        </div>
                        <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php" class="text-xs sm:text-sm font-medium text-violet-600 hover:text-violet-700">
                            View all →
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="hidden sm:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="hidden lg:table-cell px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($recentAttendance['records'])): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">No attendance records found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAttendance['records'] as $record): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-xs sm:text-sm">
                                                    <?php echo strtoupper(substr($record['first_name'], 0, 1) . substr($record['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="ml-2 sm:ml-3">
                                                    <p class="text-xs sm:text-sm font-medium text-gray-900"><?php echo e($record['first_name'] . ' ' . $record['last_name']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="hidden sm:table-cell px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600"><?php echo e($record['student_id']); ?></span>
                                        </td>
                                        <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600"><?php echo e($record['class'] . ($record['section'] ? '-' . $record['section'] : '')); ?></span>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <span class="text-xs sm:text-sm text-gray-600"><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></span>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <?php
                                            $statusConfig = [
                                                'present' => ['bg' => 'bg-green-50', 'text' => 'text-green-700', 'border' => 'border-green-200'],
                                                'late' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200'],
                                                'absent' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200']
                                            ];
                                            $config = $statusConfig[$record['status']] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200'];
                                            ?>
                                            <span class="inline-flex items-center px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-full text-xs font-medium border <?php echo $config['bg'] . ' ' . $config['text'] . ' ' . $config['border']; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="text-gray-400 hover:text-violet-600 transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Donut Chart with minimalist design
    const donutCanvas = document.getElementById('donutChart');
    if (donutCanvas) {
        const donutCtx = donutCanvas.getContext('2d');
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [
                        <?php echo $todayStats['present']; ?>,
                        <?php echo $absentToday; ?>
                    ],
                    backgroundColor: [
                        '#8B5CF6',
                        '#E5E7EB'
                    ],
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1F2937',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 12
                        }
                    }
                }
            }
        });
    }
    
    <?php if ((isAdmin() || $isPrincipalUser) && !empty($teacherLoginTrend)): ?>
    // Teacher Login Activity Chart for Admin and Principal
    const teacherLoginCanvas = document.getElementById('teacherLoginChart');
    if (teacherLoginCanvas) {
        const teacherLoginCtx = teacherLoginCanvas.getContext('2d');
        new Chart(teacherLoginCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($d) { return "'" . $d['day'] . "'"; }, $teacherLoginTrend)); ?>],
                datasets: [{
                    label: 'Teachers Logged In',
                    data: [<?php echo implode(',', array_column($teacherLoginTrend, 'count')); ?>],
                    backgroundColor: function(context) {
                        const index = context.dataIndex;
                        const count = context.dataset.data.length;
                        // Highlight today (last bar)
                        return index === count - 1 ? '#8B5CF6' : '#C4B5FD';
                    },
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1F2937',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            title: function(context) {
                                const dates = [<?php echo implode(',', array_map(function($d) { return "'" . date('M j', strtotime($d['date'])) . "'"; }, $teacherLoginTrend)); ?>];
                                return dates[context[0].dataIndex];
                            },
                            label: function(context) {
                                return context.raw + ' teacher' + (context.raw !== 1 ? 's' : '') + ' logged in';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { size: 11 },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: '#F3F4F6'
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 },
                            color: '#9CA3AF'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
