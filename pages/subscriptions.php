<?php
/**
 * Subscriptions Management Page
 * Admin-only page to manage teacher premium subscriptions
 * Grouped by grade level for easy navigation
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';

// Require admin role
requireRole('admin');

$currentUser = getCurrentUser();
$activeSchoolYear = getActiveSchoolYear();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_premium') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $user = dbFetchOne("SELECT id, is_premium, full_name, role FROM users WHERE id = ?", [$userId]);
        if ($user && $user['role'] !== 'admin') {
            $newStatus = $user['is_premium'] ? 0 : 1;
            dbExecute("UPDATE users SET is_premium = ? WHERE id = ?", [$newStatus, $userId]);
            $statusText = $newStatus ? 'activated' : 'deactivated';
            setFlash('success', 'Premium ' . $statusText . ' for ' . $user['full_name']);
        }
        redirect(config('app_url') . '/pages/subscriptions.php' . ($_GET['grade'] ?? '' ? '?grade=' . urlencode($_GET['grade']) : ''));
    }
}

// Get filter
$search = sanitizeString($_GET['search'] ?? '');
$gradeFilter = sanitizeString($_GET['grade'] ?? '');
$statusFilter = sanitizeString($_GET['status'] ?? '');

// Get teachers with their advisory classes
$sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_premium, u.last_login,
               c.grade_level, c.section,
               (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) as student_count
        FROM users u
        LEFT JOIN classes c ON c.teacher_id = u.id AND c.is_active = 1" . 
        ($activeSchoolYear ? " AND c.school_year_id = " . (int)$activeSchoolYear['id'] : "") . "
        WHERE u.role IN ('teacher', 'operator') AND u.is_active = 1
        ORDER BY c.grade_level ASC, c.section ASC, u.full_name ASC";
$allTeachers = dbFetchAll($sql, []);

// Group by grade level
$teachersByGrade = [];
$noAdvisory = [];
foreach ($allTeachers as $teacher) {
    if ($search && stripos($teacher['full_name'], $search) === false && stripos($teacher['email'], $search) === false) {
        continue;
    }
    if ($statusFilter === 'premium' && !$teacher['is_premium']) continue;
    if ($statusFilter === 'free' && $teacher['is_premium']) continue;
    
    if ($teacher['grade_level']) {
        $grade = $teacher['grade_level'];
        if (!isset($teachersByGrade[$grade])) {
            $teachersByGrade[$grade] = [];
        }
        $teachersByGrade[$grade][] = $teacher;
    } else {
        $noAdvisory[] = $teacher;
    }
}

// Get unique grades for tabs
$grades = array_keys($teachersByGrade);
sort($grades);

// Stats
$totalCount = count($allTeachers);
$premiumCount = count(array_filter($allTeachers, fn($t) => $t['is_premium']));
$freeCount = $totalCount - $premiumCount;

$pageTitle = 'Subscriptions';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <?php echo displayFlash(); ?>
        
        <!-- Page Header -->
        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Subscriptions</h1>
            <p class="text-sm sm:text-base text-gray-500 mt-1">Manage teacher premium access for reports and exports</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalCount; ?></p>
                        <p class="text-sm text-gray-500">Total Teachers</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-amber-600"><?php echo $premiumCount; ?></p>
                        <p class="text-sm text-gray-500">Premium</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-600"><?php echo $freeCount; ?></p>
                        <p class="text-sm text-gray-500">Free</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?php echo e($search); ?>" 
                           placeholder="Search by name or email..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                </div>
                <select name="status" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    <option value="">All Status</option>
                    <option value="premium" <?php echo $statusFilter === 'premium' ? 'selected' : ''; ?>>Premium Only</option>
                    <option value="free" <?php echo $statusFilter === 'free' ? 'selected' : ''; ?>>Free Only</option>
                </select>
                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 transition-colors font-medium">
                    Filter
                </button>
                <?php if ($search || $statusFilter): ?>
                    <a href="<?php echo config('app_url'); ?>/pages/subscriptions.php" class="px-4 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors text-gray-600 text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Grade Level Tabs -->
        <?php if (!empty($grades) || !empty($noAdvisory)): ?>
        <div class="mb-6 overflow-x-auto">
            <div class="flex gap-2 pb-2">
                <a href="?<?php echo http_build_query(array_filter(['search' => $search, 'status' => $statusFilter])); ?>" 
                   class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-colors <?php echo !$gradeFilter ? 'bg-violet-600 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                    All Grades
                </a>
                <?php foreach ($grades as $grade): ?>
                    <a href="?<?php echo http_build_query(array_filter(['grade' => $grade, 'search' => $search, 'status' => $statusFilter])); ?>" 
                       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-colors <?php echo $gradeFilter === $grade ? 'bg-violet-600 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                        <?php echo e($grade); ?>
                        <span class="ml-1.5 px-1.5 py-0.5 rounded-md text-xs <?php echo $gradeFilter === $grade ? 'bg-violet-500' : 'bg-gray-100 text-gray-500'; ?>"><?php echo count($teachersByGrade[$grade]); ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($noAdvisory)): ?>
                    <a href="?<?php echo http_build_query(array_filter(['grade' => 'none', 'search' => $search, 'status' => $statusFilter])); ?>" 
                       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-colors <?php echo $gradeFilter === 'none' ? 'bg-violet-600 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                        No Advisory
                        <span class="ml-1.5 px-1.5 py-0.5 rounded-md text-xs <?php echo $gradeFilter === 'none' ? 'bg-violet-500' : 'bg-gray-100 text-gray-500'; ?>"><?php echo count($noAdvisory); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teachers List -->
        <?php 
        $displayGrades = [];
        if ($gradeFilter === 'none') {
            $displayGrades = ['No Advisory' => $noAdvisory];
        } elseif ($gradeFilter && isset($teachersByGrade[$gradeFilter])) {
            $displayGrades = [$gradeFilter => $teachersByGrade[$gradeFilter]];
        } else {
            $displayGrades = $teachersByGrade;
            if (!empty($noAdvisory)) {
                $displayGrades['No Advisory'] = $noAdvisory;
            }
        }
        ?>
        
        <?php if (empty($displayGrades)): ?>
            <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="mt-2 text-sm text-gray-500">No teachers found</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($displayGrades as $gradeName => $teachers): ?>
                    <?php if (empty($teachers)) continue; ?>
                    
                    <!-- Grade Section -->
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900"><?php echo e($gradeName); ?></h3>
                            </div>
                            <span class="text-sm text-gray-500"><?php echo count($teachers); ?> teacher<?php echo count($teachers) !== 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($teachers as $teacher): ?>
                                <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                    <!-- Avatar -->
                                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0 shadow-sm
                                        <?php echo $teacher['is_premium'] ? 'bg-gradient-to-br from-amber-400 to-orange-500' : 'bg-gradient-to-br from-gray-400 to-gray-500'; ?>">
                                        <?php echo strtoupper(substr($teacher['full_name'], 0, 2)); ?>
                                    </div>
                                    
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <span class="text-sm font-medium text-gray-900"><?php echo e($teacher['full_name']); ?></span>
                                            <?php if ($teacher['is_premium']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Premium
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-500">
                                            <?php if ($teacher['section']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-violet-50 text-violet-700 font-medium">
                                                    <?php echo e($teacher['section']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span><?php echo (int)$teacher['student_count']; ?> students</span>
                                            <?php if ($teacher['email']): ?>
                                                <span class="hidden sm:inline">•</span>
                                                <span class="hidden sm:inline truncate"><?php echo e($teacher['email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Toggle Button -->
                                        <?php if ($teacher['is_premium']): ?>
                                            <form method="POST" class="flex-shrink-0">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="toggle_premium">
                                                <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                                <button type="submit" 
                                                        onclick="return confirm('Deactivate premium for <?php echo e($teacher['full_name']); ?>?')"
                                                        class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                                    Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" 
                                                    onclick="openActivateModal(<?php echo $teacher['id']; ?>, '<?php echo e(addslashes($teacher['full_name'])); ?>', '<?php echo e($teacher['section'] ?? 'No Section'); ?>', '<?php echo e($teacher['grade_level'] ?? 'No Advisory'); ?>')"
                                                    class="px-4 py-2 text-sm font-medium rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 transition-colors shadow-sm flex-shrink-0">
                                                Activate Premium
                                            </button>
                                        <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium">About Premium Subscriptions</p>
                    <p class="mt-1">Premium teachers can export attendance reports to CSV, Excel, and PDF formats. Free accounts can view reports but cannot export them.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Activate Premium Modal -->
<div id="activateModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeActivateModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full mx-auto">
            <form method="POST" action="" id="activateForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="toggle_premium">
                <input type="hidden" name="user_id" id="activateUserId" value="">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <!-- Header -->
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Activate Premium</h3>
                            <p class="text-sm text-gray-500">Confirm subscription activation</p>
                        </div>
                    </div>
                    
                    <!-- Teacher Info -->
                    <div class="bg-gray-50 rounded-xl p-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-gray-400 to-gray-500 rounded-full flex items-center justify-center text-white font-semibold text-sm" id="activateAvatar">
                                --
                            </div>
                            <div>
                                <p class="font-medium text-gray-900" id="activateName">Teacher Name</p>
                                <p class="text-sm text-gray-500"><span id="activateGrade">Grade</span> • <span id="activateSection">Section</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-3">Premium features include:</p>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Export reports to CSV
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Export reports to Excel (.xlsx)
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Export reports to PDF
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmation Checkbox -->
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="confirmActivate" required
                                   class="mt-0.5 w-4 h-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500">
                            <span class="text-sm text-amber-800">
                                I confirm that this teacher has paid for the premium subscription and should have access to export features.
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeActivateModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="activateSubmitBtn" disabled
                            class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-sm font-medium rounded-xl hover:from-amber-600 hover:to-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Activate Premium
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openActivateModal(userId, name, section, grade) {
    document.getElementById('activateUserId').value = userId;
    document.getElementById('activateName').textContent = name;
    document.getElementById('activateSection').textContent = section;
    document.getElementById('activateGrade').textContent = grade;
    document.getElementById('activateAvatar').textContent = name.substring(0, 2).toUpperCase();
    document.getElementById('confirmActivate').checked = false;
    document.getElementById('activateSubmitBtn').disabled = true;
    document.getElementById('activateModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeActivateModal() {
    document.getElementById('activateModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Enable submit button only when checkbox is checked
document.getElementById('confirmActivate').addEventListener('change', function() {
    document.getElementById('activateSubmitBtn').disabled = !this.checked;
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeActivateModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
