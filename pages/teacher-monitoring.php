<?php
/**
 * Teacher Attendance Monitoring Page
 * Optimized for 50+ teachers with pagination and search
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/teacher-attendance.php';

requireAnyRole(['admin', 'principal']);

$currentUser = getCurrentUser();
$activeSchoolYear = getActiveSchoolYear();
$schoolYears = dbFetchAll("SELECT id, name FROM school_years ORDER BY name DESC");
$teachers = getAllTeachers();
$attendanceSettings = getAttendanceSettings();

// Calculate cutoff time
$cutoffTime = strtotime(date('Y-m-d') . ' ' . $attendanceSettings['class_start_time']) + ($attendanceSettings['late_threshold_minutes'] * 60);
$cutoffFormatted = date('g:i A', $cutoffTime);

// Pagination settings
$perPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Filter parameters
$filterTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$filterSchoolYearId = isset($_GET['school_year_id']) ? (int)$_GET['school_year_id'] : ($activeSchoolYear['id'] ?? null);
$filterStartDate = $_GET['start_date'] ?? date('Y-m-d'); // Default to today
$filterEndDate = $_GET['end_date'] ?? date('Y-m-d');
$filterAttendanceStatus = $_GET['attendance_status'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');

// Build filters
$filters = [
    'school_year_id' => $filterSchoolYearId,
    'start_date' => $filterStartDate,
    'end_date' => $filterEndDate
];
if ($filterTeacherId) $filters['teacher_id'] = $filterTeacherId;
if ($filterAttendanceStatus) $filters['attendance_status'] = $filterAttendanceStatus;

// Get all records for stats (before pagination)
$allRecords = getTeacherAttendanceRecords($filters);

// Apply search filter
if ($filterSearch) {
    $allRecords = array_filter($allRecords, function($r) use ($filterSearch) {
        return stripos($r['teacher_name'], $filterSearch) !== false || 
               stripos($r['username'], $filterSearch) !== false;
    });
    $allRecords = array_values($allRecords);
}

// Calculate stats from filtered records
$totalRecords = count($allRecords);
$confirmedCount = count(array_filter($allRecords, fn($r) => $r['attendance_status'] === 'confirmed'));
$lateCount = count(array_filter($allRecords, fn($r) => $r['attendance_status'] === 'late'));
$pendingCount = count(array_filter($allRecords, fn($r) => $r['attendance_status'] === 'pending'));
$absentCount = count(array_filter($allRecords, fn($r) => $r['attendance_status'] === 'absent'));

// Pagination
$totalPages = ceil($totalRecords / $perPage);
$currentPage = min($currentPage, max(1, $totalPages));
$offset = ($currentPage - 1) * $perPage;
$attendanceRecords = array_slice($allRecords, $offset, $perPage);

// Build query string for pagination links
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);

$pageTitle = 'Teacher Monitoring';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-full overflow-hidden">
        
        <!-- Compact Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Teacher Monitoring</h1>
                <p class="text-xs text-gray-500">
                    <?php echo date('l, F j, Y'); ?> • 
                    Late after <span class="font-medium text-red-600"><?php echo $cutoffFormatted; ?></span>
                </p>
            </div>
            
            <!-- Quick Date Shortcuts -->
            <div class="flex items-center gap-2">
                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>&school_year_id=<?php echo $filterSchoolYearId; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-lg <?php echo $filterStartDate === date('Y-m-d') && $filterEndDate === date('Y-m-d') ? 'bg-violet-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                    Today
                </a>
                <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&school_year_id=<?php echo $filterSchoolYearId; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-lg <?php echo $filterStartDate === date('Y-m-d', strtotime('-7 days')) ? 'bg-violet-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                    Last 7 Days
                </a>
                <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>&school_year_id=<?php echo $filterSchoolYearId; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-lg <?php echo $filterStartDate === date('Y-m-01') ? 'bg-violet-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                    This Month
                </a>
            </div>
        </div>

        <!-- Stats Row - Compact -->
        <div class="grid grid-cols-4 gap-2 mb-4">
            <a href="?<?php echo $queryString; ?>&attendance_status=confirmed" class="bg-white rounded-lg p-3 border border-gray-100 hover:border-green-300 transition-colors <?php echo $filterAttendanceStatus === 'confirmed' ? 'ring-2 ring-green-500' : ''; ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">On Time</span>
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                </div>
                <p class="text-xl font-bold text-green-600 mt-1"><?php echo $confirmedCount; ?></p>
            </a>
            <a href="?<?php echo $queryString; ?>&attendance_status=late" class="bg-white rounded-lg p-3 border border-gray-100 hover:border-red-300 transition-colors <?php echo $filterAttendanceStatus === 'late' ? 'ring-2 ring-red-500' : ''; ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">Late</span>
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                </div>
                <p class="text-xl font-bold text-red-600 mt-1"><?php echo $lateCount; ?></p>
            </a>
            <a href="?<?php echo $queryString; ?>&attendance_status=pending" class="bg-white rounded-lg p-3 border border-gray-100 hover:border-amber-300 transition-colors <?php echo $filterAttendanceStatus === 'pending' ? 'ring-2 ring-amber-500' : ''; ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">Pending</span>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </div>
                <p class="text-xl font-bold text-amber-500 mt-1"><?php echo $pendingCount; ?></p>
            </a>
            <a href="?<?php echo $queryString; ?>&attendance_status=absent" class="bg-white rounded-lg p-3 border border-gray-100 hover:border-gray-300 transition-colors <?php echo $filterAttendanceStatus === 'absent' ? 'ring-2 ring-gray-500' : ''; ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">Absent</span>
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                </div>
                <p class="text-xl font-bold text-gray-500 mt-1"><?php echo $absentCount; ?></p>
            </a>
        </div>

        <!-- Search & Filters Bar -->
        <div class="bg-white rounded-xl border border-gray-100 mb-4">
            <form method="GET" class="p-3">
                <div class="flex flex-wrap items-end gap-2">
                    <!-- Search -->
                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" name="search" value="<?php echo e($filterSearch); ?>" placeholder="Search teacher name..." 
                                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                        </div>
                    </div>
                    
                    <!-- Teacher Dropdown -->
                    <div class="w-40">
                        <select name="teacher_id" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-violet-500">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo $filterTeacherId == $teacher['id'] ? 'selected' : ''; ?>><?php echo e($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="flex items-center gap-1">
                        <input type="date" name="start_date" value="<?php echo e($filterStartDate); ?>" class="px-2 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-violet-500 w-32">
                        <span class="text-gray-400">→</span>
                        <input type="date" name="end_date" value="<?php echo e($filterEndDate); ?>" class="px-2 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-violet-500 w-32">
                    </div>
                    
                    <!-- Hidden fields -->
                    <input type="hidden" name="school_year_id" value="<?php echo $filterSchoolYearId; ?>">
                    <?php if ($filterAttendanceStatus): ?>
                    <input type="hidden" name="attendance_status" value="<?php echo e($filterAttendanceStatus); ?>">
                    <?php endif; ?>
                    
                    <!-- Buttons -->
                    <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700">
                        Search
                    </button>
                    <a href="?" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <!-- Table Header with Pagination Info -->
            <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <p class="text-xs text-gray-500">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> records
                </p>
                <?php if ($totalPages > 1): ?>
                <div class="flex items-center gap-1">
                    <?php if ($currentPage > 1): ?>
                    <a href="?<?php echo $queryString; ?>&page=<?php echo $currentPage - 1; ?>" class="p-1 rounded hover:bg-gray-200">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <?php endif; ?>
                    <span class="text-xs text-gray-600 px-2">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?php echo $queryString; ?>&page=<?php echo $currentPage + 1; ?>" class="p-1 rounded hover:bg-gray-200">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase w-48">Teacher</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase w-24">Date</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">Login</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">1st Scan</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">Logout</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($attendanceRecords)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                <p>No records found</p>
                                <p class="text-xs mt-1">Try adjusting your filters</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($attendanceRecords as $record): 
                            $loginTime = $record['time_in'] ? strtotime($record['time_in']) : null;
                            $scanTime = $record['first_student_scan'] ? strtotime($record['first_student_scan']) : null;
                            $recordDate = $record['attendance_date'];
                            $recordCutoff = strtotime($recordDate . ' ' . $attendanceSettings['class_start_time']) + ($attendanceSettings['late_threshold_minutes'] * 60);
                        ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 bg-gradient-to-br from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
                                        <?php echo strtoupper(substr($record['teacher_name'], 0, 1)); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo e($record['teacher_name']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                <?php echo date('M j', strtotime($recordDate)); ?>
                                <span class="text-gray-400 block"><?php echo date('D', strtotime($recordDate)); ?></span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <?php if ($loginTime): ?>
                                <span class="text-xs <?php echo $loginTime > $recordCutoff ? 'text-red-600 font-medium' : 'text-gray-700'; ?>">
                                    <?php echo date('g:i A', $loginTime); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <?php if ($scanTime): ?>
                                <span class="text-xs <?php echo $scanTime > $recordCutoff ? 'text-red-600 font-medium' : 'text-gray-700'; ?>">
                                    <?php echo date('g:i A', $scanTime); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-center text-xs text-gray-600">
                                <?php echo $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : '<span class="text-gray-300">—</span>'; ?>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <?php
                                $statusConfig = [
                                    'confirmed' => ['label' => 'On Time', 'class' => 'bg-green-100 text-green-700'],
                                    'late' => ['label' => 'Late', 'class' => 'bg-red-100 text-red-700'],
                                    'pending' => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-700'],
                                    'no_scan' => ['label' => 'No Scan', 'class' => 'bg-gray-100 text-gray-600'],
                                    'absent' => ['label' => 'Absent', 'class' => 'bg-gray-200 text-gray-700']
                                ];
                                $status = $record['attendance_status'] ?? 'pending';
                                $cfg = $statusConfig[$status] ?? $statusConfig['pending'];
                                ?>
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium <?php echo $cfg['class']; ?>">
                                    <?php echo $cfg['label']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bottom Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    <?php echo $totalRecords; ?> total records
                </p>
                <div class="flex items-center gap-1">
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>
                    
                    <?php if ($currentPage > 1): ?>
                    <a href="?<?php echo $queryString; ?>&page=1" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded">First</a>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?<?php echo $queryString; ?>&page=<?php echo $i; ?>" 
                       class="px-2.5 py-1 text-xs rounded <?php echo $i === $currentPage ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?php echo $queryString; ?>&page=<?php echo $totalPages; ?>" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded">Last</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Compact Legend -->
        <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-400">
            <span>✓ On Time = Before <?php echo $cutoffFormatted; ?></span>
            <span>⚠ Late = After <?php echo $cutoffFormatted; ?></span>
            <span>◷ Pending = Awaiting scan</span>
            <span>✗ Absent = No login</span>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
