<?php
/**
 * Attendance History Page
 * View and filter attendance records
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';

// Require authentication
requireAuth();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Attendance History';

// Get filter parameters
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterStudent = $_GET['student'] ?? '';
$filterSearch = sanitizeString($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;

// Get attendance records
if ($filterSearch) {
    // Search by name, student ID, or LRN
    $searchParam = '%' . $filterSearch . '%';
    $sql = "SELECT a.*, 
                   s.student_id, s.lrn, s.first_name, s.last_name, s.class, s.section,
                   u.full_name as recorded_by_name
            FROM attendance a
            INNER JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON a.recorded_by = u.id
            WHERE (s.student_id LIKE ? OR s.lrn LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)
            " . ($filterDate ? "AND a.attendance_date = ?" : "") . "
            ORDER BY a.attendance_date DESC, a.check_in_time DESC
            LIMIT 100";
    
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    if ($filterDate) {
        $params[] = $filterDate;
    }
    
    $records = dbFetchAll($sql, $params);
    $totalRecords = count($records);
    $totalPages = 1;
} elseif ($filterStudent) {
    // Filter by specific student
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $sql = "SELECT a.*, 
                   s.student_id, s.first_name, s.last_name, s.class, s.section,
                   u.full_name as recorded_by_name
            FROM attendance a
            INNER JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON a.recorded_by = u.id
            WHERE s.student_id = ? AND a.attendance_date BETWEEN ? AND ?
            ORDER BY a.attendance_date DESC, a.check_in_time DESC";
    
    $records = dbFetchAll($sql, [$filterStudent, $startDate, $endDate]);
    $totalRecords = count($records);
    $totalPages = 1;
} elseif ($filterDate) {
    // Filter by date
    $records = getAttendanceByDate($filterDate);
    $totalRecords = count($records);
    $totalPages = 1;
} else {
    // Get all recent records with pagination
    $result = getRecentAttendance($page, $perPage);
    $records = $result['records'];
    $totalRecords = $result['total'];
    $totalPages = $result['total_pages'];
}

// Get all students for filter dropdown
$students = dbFetchAll("SELECT student_id, first_name, last_name FROM students WHERE is_active = 1 ORDER BY last_name, first_name");
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
          :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Attendance History</h1>
                <p class="text-sm sm:text-base text-gray-500 mt-1">View and filter attendance records</p>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl p-6 border border-gray-100 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                
                <form method="GET" action="" id="filterForm" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                            Search
                        </label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            value="<?php echo e($filterSearch); ?>"
                            placeholder="Name, ID, or LRN..."
                            oninput="debounceFilter()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary"
                        >
                    </div>
                    
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                            Date
                        </label>
                        <input 
                            type="date" 
                            id="date" 
                            name="date" 
                            value="<?php echo e($filterDate); ?>"
                            onchange="debounceFilter()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary"
                        >
                    </div>
                    
                    <div>
                        <label for="student" class="block text-sm font-medium text-gray-700 mb-2">
                            Student
                        </label>
                        <select 
                            id="student" 
                            name="student"
                            onchange="debounceFilter()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary"
                        >
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option 
                                    value="<?php echo e($student['student_id']); ?>"
                                    <?php echo $filterStudent === $student['student_id'] ? 'selected' : ''; ?>
                                >
                                    <?php echo e($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($filterStudent): ?>
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Start Date
                            </label>
                            <input 
                                type="date" 
                                id="start_date" 
                                name="start_date" 
                                value="<?php echo e($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'))); ?>"
                                onchange="debounceFilter()"
                                class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary"
                            >
                        </div>
                        
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                                End Date
                            </label>
                            <input 
                                type="date" 
                                id="end_date" 
                                name="end_date" 
                                value="<?php echo e($_GET['end_date'] ?? date('Y-m-d')); ?>"
                                onchange="debounceFilter()"
                                class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary"
                            >
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-end gap-2">
                        <button 
                            type="submit"
                            class="flex-1 bg-violet-600 text-white px-4 py-2 rounded-xl hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 font-medium transition-all"
                        >
                            Apply
                        </button>
                        <a 
                            href="<?php echo config('app_url'); ?>/pages/attendance-history.php" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-all"
                        >
                            Clear
                        </a>
                    </div>
                </form>
                
                <script>
                    let debounceTimer = null;
                    function debounceFilter() {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                            document.getElementById('filterForm').submit();
                        }, 500);
                    }
                </script>
                

            </div>

            <!-- Results Summary -->
            <div class="bg-white rounded-xl p-4 border border-gray-100 mb-6">
                <p class="text-gray-700">
                    Showing <span class="font-semibold"><?php echo e(count($records)); ?></span> 
                    <?php if ($totalRecords > count($records)): ?>
                        of <span class="font-semibold"><?php echo e($totalRecords); ?></span>
                    <?php endif; ?>
                    attendance record<?php echo count($records) !== 1 ? 's' : ''; ?>
                    <?php if ($filterSearch): ?>
                        matching "<span class="font-semibold"><?php echo e($filterSearch); ?></span>"
                    <?php endif; ?>
                    <?php if ($filterDate): ?>
                        for <span class="font-semibold"><?php echo e(formatDate($filterDate)); ?></span>
                    <?php endif; ?>
                    <?php if ($filterStudent): ?>
                        for student <span class="font-semibold"><?php echo e($filterStudent); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Attendance Records Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <?php if (empty($records)): ?>
                    <div class="p-8 text-center text-gray-600">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg">No attendance records found</p>
                        <p class="text-sm mt-2">Try adjusting your filters or scan some barcodes</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php foreach ($records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e(formatDate($record['attendance_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e(formatDateTime($record['check_in_time'], 'h:i A')); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo e($record['student_id']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e($record['first_name'] . ' ' . $record['last_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e($record['class'] ?? 'N/A'); ?> <?php echo e($record['section'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusColors = [
                                                'present' => 'bg-green-100 text-green-800',
                                                'late' => 'bg-orange-100 text-orange-800',
                                                'absent' => 'bg-red-100 text-red-800'
                                            ];
                                            $colorClass = $statusColors[$record['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $colorClass; ?>">
                                                <?php echo e(ucfirst($record['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo e($record['recorded_by_name'] ?? 'System'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1 && !$filterStudent && !$filterDate): ?>
                        <div class="bg-white px-4 py-3 border-t border-gray-100 sm:px-6">
                            <div class="flex gap-2 justify-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" 
                                       class="px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
