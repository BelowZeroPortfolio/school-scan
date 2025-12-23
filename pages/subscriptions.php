<?php
/**
 * Subscriptions Management Page
 * Admin-only page to manage teacher premium subscriptions and student SMS subscriptions
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

// Get active tab (teachers or students)
$activeTab = $_GET['tab'] ?? 'teachers';

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
        redirect(config('app_url') . '/pages/subscriptions.php?tab=teachers' . ($_GET['grade'] ?? '' ? '&grade=' . urlencode($_GET['grade']) : ''));
    }
    
    if ($action === 'toggle_student_sms') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student = dbFetchOne("SELECT id, sms_enabled, first_name, last_name FROM students WHERE id = ?", [$studentId]);
        if ($student) {
            $newStatus = $student['sms_enabled'] ? 0 : 1;
            dbExecute("UPDATE students SET sms_enabled = ? WHERE id = ?", [$newStatus, $studentId]);
            $statusText = $newStatus ? 'enabled' : 'disabled';
            setFlash('success', 'SMS notifications ' . $statusText . ' for ' . $student['first_name'] . ' ' . $student['last_name']);
        }
        $redirectUrl = config('app_url') . '/pages/subscriptions.php?tab=students';
        if (!empty($_GET['class_id'])) $redirectUrl .= '&class_id=' . (int)$_GET['class_id'];
        if (!empty($_GET['sms_status'])) $redirectUrl .= '&sms_status=' . urlencode($_GET['sms_status']);
        redirect($redirectUrl);
    }
    
    if ($action === 'bulk_sms_toggle') {
        $studentIds = $_POST['student_ids'] ?? [];
        $bulkAction = $_POST['bulk_action'] ?? '';
        
        if (!empty($studentIds) && in_array($bulkAction, ['enable', 'disable'])) {
            $newStatus = $bulkAction === 'enable' ? 1 : 0;
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $params = array_merge([$newStatus], array_map('intval', $studentIds));
            dbExecute("UPDATE students SET sms_enabled = ? WHERE id IN ($placeholders)", $params);
            $statusText = $bulkAction === 'enable' ? 'enabled' : 'disabled';
            setFlash('success', 'SMS notifications ' . $statusText . ' for ' . count($studentIds) . ' student(s)');
        }
        $redirectUrl = config('app_url') . '/pages/subscriptions.php?tab=students';
        if (!empty($_GET['class_id'])) $redirectUrl .= '&class_id=' . (int)$_GET['class_id'];
        redirect($redirectUrl);
    }
    
    if ($action === 'bulk_premium_toggle') {
        $userIds = $_POST['user_ids'] ?? [];
        $bulkAction = $_POST['bulk_action'] ?? '';
        
        if (!empty($userIds) && in_array($bulkAction, ['activate', 'deactivate'])) {
            $newStatus = $bulkAction === 'activate' ? 1 : 0;
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $params = array_merge([$newStatus], array_map('intval', $userIds));
            dbExecute("UPDATE users SET is_premium = ? WHERE id IN ($placeholders) AND role != 'admin'", $params);
            $statusText = $bulkAction === 'activate' ? 'activated' : 'deactivated';
            setFlash('success', 'Premium ' . $statusText . ' for ' . count($userIds) . ' teacher(s)');
        }
        $redirectUrl = config('app_url') . '/pages/subscriptions.php?tab=teachers';
        if (!empty($_GET['grade'])) $redirectUrl .= '&grade=' . urlencode($_GET['grade']);
        redirect($redirectUrl);
    }
}

// Get filter
$search = sanitizeString($_GET['search'] ?? '');
$gradeFilter = sanitizeString($_GET['grade'] ?? '');
$statusFilter = sanitizeString($_GET['status'] ?? '');

// ============ TEACHER SUBSCRIPTIONS DATA ============
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
    if ($search && $activeTab === 'teachers' && stripos($teacher['full_name'], $search) === false && stripos($teacher['email'], $search) === false) {
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

// Teacher Stats
$totalTeacherCount = count($allTeachers);
$premiumCount = count(array_filter($allTeachers, fn($t) => $t['is_premium']));
$freeCount = $totalTeacherCount - $premiumCount;

// ============ STUDENT SMS SUBSCRIPTIONS DATA ============
$studentSearch = sanitizeString($_GET['student_search'] ?? '');
$classIdFilter = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$smsStatusFilter = sanitizeString($_GET['sms_status'] ?? '');

// Get all classes for filter dropdown
$classesForFilter = [];
if ($activeSchoolYear) {
    $classesForFilter = dbFetchAll(
        "SELECT c.id, c.grade_level, c.section, u.full_name as teacher_name
         FROM classes c
         LEFT JOIN users u ON c.teacher_id = u.id
         WHERE c.is_active = 1 AND c.school_year_id = ?
         ORDER BY c.grade_level ASC, c.section ASC",
        [$activeSchoolYear['id']]
    );
}

// Get students with SMS subscription status
$studentSql = "SELECT s.id, s.student_id, s.lrn, s.first_name, s.last_name, s.parent_name, s.parent_phone, s.sms_enabled,
                      c.grade_level, c.section, c.id as class_id
               FROM students s
               LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
               LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1" .
               ($activeSchoolYear ? " AND c.school_year_id = " . (int)$activeSchoolYear['id'] : "") . "
               WHERE s.is_active = 1";
$studentParams = [];

if ($classIdFilter) {
    $studentSql .= " AND c.id = ?";
    $studentParams[] = $classIdFilter;
}

if ($studentSearch) {
    $studentSql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.lrn LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    $searchParam = '%' . $studentSearch . '%';
    $studentParams = array_merge($studentParams, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($smsStatusFilter === 'enabled') {
    $studentSql .= " AND s.sms_enabled = 1";
} elseif ($smsStatusFilter === 'disabled') {
    $studentSql .= " AND s.sms_enabled = 0";
}

$studentSql .= " ORDER BY c.grade_level ASC, c.section ASC, s.last_name ASC, s.first_name ASC";
$allStudents = dbFetchAll($studentSql, $studentParams);

// Group students by class
$studentsByClass = [];
$noClass = [];
foreach ($allStudents as $student) {
    if ($student['class_id']) {
        $classKey = $student['grade_level'] . ' - ' . $student['section'];
        if (!isset($studentsByClass[$classKey])) {
            $studentsByClass[$classKey] = [];
        }
        $studentsByClass[$classKey][] = $student;
    } else {
        $noClass[] = $student;
    }
}

// Student SMS Stats
$totalStudentCount = count($allStudents);
$smsEnabledCount = count(array_filter($allStudents, fn($s) => $s['sms_enabled']));
$smsDisabledCount = $totalStudentCount - $smsEnabledCount;

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
            <p class="text-sm sm:text-base text-gray-500 mt-1">Manage teacher premium access and student SMS notifications</p>
        </div>

        <!-- Main Tabs: Teachers vs Students -->
        <div class="mb-6 border-b border-gray-200">
            <nav class="flex gap-4" aria-label="Tabs">
                <a href="?tab=teachers" 
                   class="px-4 py-3 text-sm font-medium border-b-2 transition-colors <?php echo $activeTab === 'teachers' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Teacher Export Access
                </a>
                <a href="?tab=students" 
                   class="px-4 py-3 text-sm font-medium border-b-2 transition-colors <?php echo $activeTab === 'students' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    Student SMS Notifications
                </a>
            </nav>
        </div>

        <?php if ($activeTab === 'teachers'): ?>
        <!-- ============ TEACHER SUBSCRIPTIONS TAB ============ -->

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
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalTeacherCount; ?></p>
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
                <input type="hidden" name="tab" value="teachers">
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?php echo e($search); ?>" 
                           placeholder="Search by name or email..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                </div>
                <select name="grade" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo e($grade); ?>" <?php echo $gradeFilter === $grade ? 'selected' : ''; ?>>
                            <?php echo e($grade); ?> (<?php echo count($teachersByGrade[$grade]); ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if (!empty($noAdvisory)): ?>
                        <option value="none" <?php echo $gradeFilter === 'none' ? 'selected' : ''; ?>>No Advisory (<?php echo count($noAdvisory); ?>)</option>
                    <?php endif; ?>
                </select>
                <select name="status" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    <option value="">All Status</option>
                    <option value="premium" <?php echo $statusFilter === 'premium' ? 'selected' : ''; ?>>Premium Only</option>
                    <option value="free" <?php echo $statusFilter === 'free' ? 'selected' : ''; ?>>Free Only</option>
                </select>
                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 transition-colors font-medium">
                    Filter
                </button>
                <?php if ($search || $statusFilter || $gradeFilter): ?>
                    <a href="<?php echo config('app_url'); ?>/pages/subscriptions.php?tab=teachers" class="px-4 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors text-gray-600 text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Bulk Actions -->
        <form method="POST" id="bulkPremiumForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="bulk_premium_toggle">
            
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="selectAllTeachers" class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                    <label for="selectAllTeachers" class="text-sm text-gray-600">Select All</label>
                </div>
                <div class="flex-1"></div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500" id="selectedTeacherCount">0 selected</span>
                    <button type="submit" name="bulk_action" value="activate" 
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            id="bulkActivateBtn" disabled>
                        Activate Premium
                    </button>
                    <button type="submit" name="bulk_action" value="deactivate"
                            class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            id="bulkDeactivateBtn" disabled>
                        Deactivate
                    </button>
                </div>
            </div>

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
                                    <!-- Checkbox -->
                                    <input type="checkbox" name="user_ids[]" value="<?php echo $teacher['id']; ?>" 
                                           class="teacher-checkbox w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                                    
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
                                            <button type="button" 
                                                    onclick="deactivateSingle(<?php echo $teacher['id']; ?>, '<?php echo e(addslashes($teacher['full_name'])); ?>')"
                                                    class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors flex-shrink-0">
                                                Deactivate
                                            </button>
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
        </form>

        <!-- Teacher Bulk Actions Script -->
        <script>
        // Select All Teachers functionality
        document.getElementById('selectAllTeachers').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.teacher-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateTeacherSelectedCount();
        });

        // Individual checkbox change
        document.querySelectorAll('.teacher-checkbox').forEach(cb => {
            cb.addEventListener('change', updateTeacherSelectedCount);
        });

        function updateTeacherSelectedCount() {
            const checked = document.querySelectorAll('.teacher-checkbox:checked').length;
            document.getElementById('selectedTeacherCount').textContent = checked + ' selected';
            document.getElementById('bulkActivateBtn').disabled = checked === 0;
            document.getElementById('bulkDeactivateBtn').disabled = checked === 0;
            
            // Update select all checkbox state
            const total = document.querySelectorAll('.teacher-checkbox').length;
            const selectAll = document.getElementById('selectAllTeachers');
            selectAll.checked = checked === total && total > 0;
            selectAll.indeterminate = checked > 0 && checked < total;
        }

        // Confirm bulk actions
        document.getElementById('bulkPremiumForm').addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.teacher-checkbox:checked').length;
            const action = e.submitter.value;
            const actionText = action === 'activate' ? 'activate premium for' : 'deactivate premium for';
            if (!confirm(`Are you sure you want to ${actionText} ${checked} teacher(s)?`)) {
                e.preventDefault();
            }
        });

        // Single deactivate function
        function deactivateSingle(userId, name) {
            if (confirm('Deactivate premium for ' + name + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="toggle_premium">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>

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

        <?php else: ?>
        <!-- ============ STUDENT SMS SUBSCRIPTIONS TAB ============ -->

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalStudentCount; ?></p>
                        <p class="text-sm text-gray-500">Total Students</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-green-600"><?php echo $smsEnabledCount; ?></p>
                        <p class="text-sm text-gray-500">SMS Enabled</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-600"><?php echo $smsDisabledCount; ?></p>
                        <p class="text-sm text-gray-500">SMS Disabled</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="tab" value="students">
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="student_search" value="<?php echo e($studentSearch); ?>" 
                           placeholder="Search by name, student ID, or LRN..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                </div>
                <select name="class_id" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    <option value="">All Classes</option>
                    <?php foreach ($classesForFilter as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $classIdFilter === $class['id'] ? 'selected' : ''; ?>>
                            <?php echo e($class['grade_level'] . ' - ' . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sms_status" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    <option value="">All Status</option>
                    <option value="enabled" <?php echo $smsStatusFilter === 'enabled' ? 'selected' : ''; ?>>SMS Enabled</option>
                    <option value="disabled" <?php echo $smsStatusFilter === 'disabled' ? 'selected' : ''; ?>>SMS Disabled</option>
                </select>
                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 transition-colors font-medium">
                    Filter
                </button>
                <?php if ($studentSearch || $classIdFilter || $smsStatusFilter): ?>
                    <a href="<?php echo config('app_url'); ?>/pages/subscriptions.php?tab=students" class="px-4 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors text-gray-600 text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Bulk Actions -->
        <form method="POST" id="bulkSmsForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="bulk_sms_toggle">
            
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="selectAll" class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                    <label for="selectAll" class="text-sm text-gray-600">Select All</label>
                </div>
                <div class="flex-1"></div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500" id="selectedCount">0 selected</span>
                    <button type="submit" name="bulk_action" value="enable" 
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-green-600 text-white hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            id="bulkEnableBtn" disabled>
                        Enable SMS
                    </button>
                    <button type="submit" name="bulk_action" value="disable"
                            class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            id="bulkDisableBtn" disabled>
                        Disable SMS
                    </button>
                </div>
            </div>

            <!-- Students List -->
            <?php if (empty($allStudents)): ?>
                <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">No students found</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($studentsByClass as $className => $students): ?>
                        <?php if (empty($students)) continue; ?>
                        
                        <!-- Class Section -->
                        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo e($className); ?></h3>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo count($students); ?> student<?php echo count($students) !== 1 ? 's' : ''; ?></span>
                            </div>
                            
                            <div class="divide-y divide-gray-100">
                                <?php foreach ($students as $student): ?>
                                    <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                        <!-- Checkbox -->
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                               class="student-checkbox w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                                        
                                        <!-- Avatar -->
                                        <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0 shadow-sm
                                            <?php echo $student['sms_enabled'] ? 'bg-gradient-to-br from-green-400 to-emerald-500' : 'bg-gradient-to-br from-gray-400 to-gray-500'; ?>">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <span class="text-sm font-medium text-gray-900"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                <?php if ($student['sms_enabled']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        SMS On
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                        SMS Off
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                                <span><?php echo e($student['student_id']); ?></span>
                                                <?php if ($student['parent_phone']): ?>
                                                    <span>•</span>
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                        </svg>
                                                        <?php echo e($student['parent_phone']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span>•</span>
                                                    <span class="text-amber-600">No phone number</span>
                                                <?php endif; ?>
                                                <?php if ($student['parent_name']): ?>
                                                    <span class="hidden sm:inline">•</span>
                                                    <span class="hidden sm:inline"><?php echo e($student['parent_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Toggle Button -->
                                        <form method="POST" class="flex-shrink-0">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="toggle_student_sms">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <?php if ($student['sms_enabled']): ?>
                                                <button type="submit" 
                                                        onclick="return confirm('Disable SMS notifications for <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>?')"
                                                        class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" 
                                                        class="px-4 py-2 text-sm font-medium rounded-xl bg-green-600 text-white hover:bg-green-700 transition-colors">
                                                    Enable SMS
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($noClass)): ?>
                        <!-- No Class Section -->
                        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">No Class Assigned</h3>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo count($noClass); ?> student<?php echo count($noClass) !== 1 ? 's' : ''; ?></span>
                            </div>
                            
                            <div class="divide-y divide-gray-100">
                                <?php foreach ($noClass as $student): ?>
                                    <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                        <!-- Checkbox -->
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                               class="student-checkbox w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                                        
                                        <!-- Avatar -->
                                        <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0 shadow-sm
                                            <?php echo $student['sms_enabled'] ? 'bg-gradient-to-br from-green-400 to-emerald-500' : 'bg-gradient-to-br from-gray-400 to-gray-500'; ?>">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <span class="text-sm font-medium text-gray-900"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                <?php if ($student['sms_enabled']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        SMS On
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                        SMS Off
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                                <span><?php echo e($student['student_id']); ?></span>
                                                <?php if ($student['parent_phone']): ?>
                                                    <span>•</span>
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                        </svg>
                                                        <?php echo e($student['parent_phone']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span>•</span>
                                                    <span class="text-amber-600">No phone number</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Toggle Button -->
                                        <form method="POST" class="flex-shrink-0">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="toggle_student_sms">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <?php if ($student['sms_enabled']): ?>
                                                <button type="submit" 
                                                        onclick="return confirm('Disable SMS notifications for <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>?')"
                                                        class="px-4 py-2 text-sm font-medium rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" 
                                                        class="px-4 py-2 text-sm font-medium rounded-xl bg-green-600 text-white hover:bg-green-700 transition-colors">
                                                    Enable SMS
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium">About SMS Notifications</p>
                    <p class="mt-1">When SMS is enabled for a student, their parent/guardian will receive text notifications when attendance is recorded. Make sure the parent's phone number is correctly entered in the student profile.</p>
                </div>
            </div>
        </div>

        <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // Individual checkbox change
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.student-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = checked + ' selected';
            document.getElementById('bulkEnableBtn').disabled = checked === 0;
            document.getElementById('bulkDisableBtn').disabled = checked === 0;
            
            // Update select all checkbox state
            const total = document.querySelectorAll('.student-checkbox').length;
            const selectAll = document.getElementById('selectAll');
            selectAll.checked = checked === total && total > 0;
            selectAll.indeterminate = checked > 0 && checked < total;
        }

        // Confirm bulk actions
        document.getElementById('bulkSmsForm').addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.student-checkbox:checked').length;
            const action = e.submitter.value;
            const actionText = action === 'enable' ? 'enable' : 'disable';
            if (!confirm(`Are you sure you want to ${actionText} SMS notifications for ${checked} student(s)?`)) {
                e.preventDefault();
            }
        });
        </script>

        <?php endif; ?>
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
