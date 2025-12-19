<?php
/**
 * Students List Page
 * Display all students with pagination and search
 * 
 * Requirements: 6.1, 10.1 - Filter by teacher's classes for teacher role
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require authentication
requireAuth();

// Get current user info
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'viewer';
$userId = $currentUser['id'] ?? null;

// Get active school year and all school years for filter
$activeSchoolYear = getActiveSchoolYear();
$allSchoolYears = getAllSchoolYears();

// Get filter parameters
$search = sanitizeString($_GET['search'] ?? '');
$selectedSchoolYearId = isset($_GET['school_year']) && $_GET['school_year'] !== '' ? (int)$_GET['school_year'] : ($activeSchoolYear ? $activeSchoolYear['id'] : null);
$selectedClassId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get available classes for filter dropdown
$availableClasses = [];
if (isTeacher() && $userId) {
    // Teachers see only their classes (Requirements: 6.1)
    $availableClasses = getTeacherClasses($userId, $selectedSchoolYearId);
} else {
    // Admin/Operator see all classes for selected school year
    $availableClasses = getClassesBySchoolYear($selectedSchoolYearId);
}

// Build query based on role and filters
$students = [];
$totalStudents = 0;

if (isTeacher() && $userId) {
    // Teacher: Show only students in their classes (Requirements: 6.1)
    $teacherStudents = getTeacherStudents($userId, $selectedSchoolYearId);
    
    // Apply search filter
    if ($search) {
        $searchLower = strtolower($search);
        $teacherStudents = array_filter($teacherStudents, function($s) use ($searchLower) {
            return strpos(strtolower($s['student_code']), $searchLower) !== false ||
                   strpos(strtolower($s['first_name']), $searchLower) !== false ||
                   strpos(strtolower($s['last_name']), $searchLower) !== false ||
                   strpos(strtolower($s['lrn'] ?? ''), $searchLower) !== false;
        });
    }
    
    // Apply class filter
    if ($selectedClassId) {
        $teacherStudents = array_filter($teacherStudents, function($s) use ($selectedClassId) {
            return $s['class_id'] == $selectedClassId;
        });
    }
    
    $totalStudents = count($teacherStudents);
    $students = array_slice(array_values($teacherStudents), $offset, $perPage);
} else {
    // Admin/Operator: Show all students with optional filters (Requirements: 10.1)
    $whereConditions = ['s.is_active = 1'];
    $params = [];
    $joinClause = '';
    
    // Add school year and class filtering via student_classes
    if ($selectedSchoolYearId || $selectedClassId) {
        $joinClause = "LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                       LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1
                       LEFT JOIN users t ON c.teacher_id = t.id";
        
        if ($selectedSchoolYearId) {
            $whereConditions[] = 'c.school_year_id = ?';
            $params[] = $selectedSchoolYearId;
        }
        
        if ($selectedClassId) {
            $whereConditions[] = 'c.id = ?';
            $params[] = $selectedClassId;
        }
    }
    
    // Add search filter
    if ($search) {
        $searchParam = '%' . $search . '%';
        $whereConditions[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(DISTINCT s.id) as total FROM students s {$joinClause} {$whereClause}";
    $countResult = dbFetchOne($countSql, $params);
    $totalStudents = $countResult['total'];
    
    // Get students for current page with class info
    $params[] = $perPage;
    $params[] = $offset;
    
    if ($selectedSchoolYearId || $selectedClassId) {
        $sql = "SELECT DISTINCT s.id, s.student_id, s.lrn, s.first_name, s.last_name,
                       s.parent_phone, s.parent_email, s.is_active, s.created_at,
                       c.id AS class_id, c.grade_level, c.section AS class_section,
                       t.full_name AS teacher_name
                FROM students s
                {$joinClause}
                {$whereClause}
                ORDER BY s.last_name, s.first_name
                LIMIT ? OFFSET ?";
    } else {
        // Join with classes to get current class info
        $sql = "SELECT s.id, s.student_id, s.lrn, s.first_name, s.last_name,
                       s.parent_phone, s.parent_email, s.is_active, s.created_at,
                       c.id AS class_id, c.grade_level, c.section AS class_section,
                       t.full_name AS teacher_name
                FROM students s
                LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1
                LEFT JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                LEFT JOIN users t ON c.teacher_id = t.id
                {$whereClause}
                ORDER BY s.last_name, s.first_name
                LIMIT ? OFFSET ?";
    }
    
    $students = dbFetchAll($sql, $params);
}

$totalPages = ceil($totalStudents / $perPage);
$pageTitle = 'Students';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Students</h1>
                        <p class="text-gray-500 mt-1">Manage student records and information</p>
                    </div>
                    <?php if (hasAnyRole(['admin', 'operator', 'teacher'])): ?>
                        <a href="<?php echo config('app_url'); ?>/pages/student-add.php" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Student
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php echo displayFlash(); ?>
            
            <!-- Filters Section -->
            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100 mb-6">
                <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="search" 
                                id="search"
                                placeholder="Search by ID, name, or LRN..." 
                                value="<?php echo e($search); ?>"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- School Year Filter -->
                    <div>
                        <label for="school_year" class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                        <select name="school_year" id="school_year" 
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                            <option value="">All School Years</option>
                            <?php foreach ($allSchoolYears as $sy): ?>
                                <option value="<?php echo $sy['id']; ?>" <?php echo $selectedSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Active)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Class Filter -->
                    <div>
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select name="class_id" id="class_id" 
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                            <option value="">All Classes</option>
                            <?php foreach ($availableClasses as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($class['grade_level'] . ' - ' . $class['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                            Filter
                        </button>
                        <a href="<?php echo config('app_url'); ?>/pages/students.php" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Students Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">
                                            <?php echo $search ? 'No students found matching your search.' : 'No students found. Add your first student!'; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    // Get class display info - use new class system if available
                                    $classDisplay = '';
                                    $teacherDisplay = '';
                                    if (isset($student['grade_level']) && $student['grade_level']) {
                                        $classDisplay = $student['grade_level'] . ' - ' . ($student['class_section'] ?? '');
                                        $teacherDisplay = $student['teacher_name'] ?? '';
                                    } else {
                                        $classDisplay = $student['class'] . ($student['section'] ? ' - ' . $student['section'] : '');
                                    }
                                    ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <span class="text-sm font-medium text-gray-900"><?php echo e($student['student_id'] ?? $student['student_code'] ?? ''); ?></span>
                                                <?php if (!empty($student['lrn'])): ?>
                                                    <div class="text-xs text-gray-500">LRN: <?php echo e($student['lrn']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600"><?php echo e($classDisplay); ?></span>
                                        </td>
                                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600"><?php echo $teacherDisplay ? e($teacherDisplay) : '<span class="text-gray-400">—</span>'; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($student['parent_phone'])): ?>
                                                <div class="text-sm text-gray-600"><?php echo e($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($student['parent_email'])): ?>
                                                <div class="text-xs text-gray-500"><?php echo e($student['parent_email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($student['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-green-50 text-green-700 border-green-200">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-red-50 text-red-700 border-red-200">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $student['id']; ?>" class="text-violet-600 hover:text-violet-700 mr-3">View</a>
                                            <?php if (hasAnyRole(['admin', 'operator', 'teacher'])): ?>
                                                <a href="<?php echo config('app_url'); ?>/pages/student-edit.php?id=<?php echo $student['id']; ?>" class="text-gray-600 hover:text-gray-700">Edit</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination & Summary -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalStudents); ?> of <?php echo $totalStudents; ?> students
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <?php
                        // Build query string for pagination links
                        $queryParams = [];
                        if ($search) $queryParams['search'] = $search;
                        if ($selectedSchoolYearId) $queryParams['school_year'] = $selectedSchoolYearId;
                        if ($selectedClassId) $queryParams['class_id'] = $selectedClassId;
                        $queryString = http_build_query($queryParams);
                        ?>
                        <div class="flex gap-2">
                            <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $queryString ? '&' . $queryString : ''; ?>" 
                                   class="px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
