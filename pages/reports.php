<?php
/**
 * Reports Page
 * Generate and export attendance reports
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/reports.php';
require_once __DIR__ . '/../includes/export-csv.php';
require_once __DIR__ . '/../includes/export-pdf.php';
require_once __DIR__ . '/../includes/export-excel.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = $currentUser['id'] ?? null;
$isAdmin = hasRole('admin');
$hasPremiumAccess = isPremium();
$showPremiumUpgrade = isTeacher() && !$hasPremiumAccess;

// Get school years and classes
$activeSchoolYear = getActiveSchoolYear();
$allSchoolYears = getAllSchoolYears();

$availableClassesForFilter = [];
$availableTeachers = [];
if (isTeacher() && $userId) {
    $availableClassesForFilter = getTeacherClasses($userId, $activeSchoolYear ? $activeSchoolYear['id'] : null);
} else {
    $availableClassesForFilter = getClassesBySchoolYear($activeSchoolYear ? $activeSchoolYear['id'] : null);
    $availableTeachers = getAllTeachers();
}

// Handle export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    verifyCsrf();
    if (isTeacher() && !isPremium()) {
        setFlash('error', 'Export requires premium subscription.');
        redirect(config('app_url') . '/pages/reports.php');
    }
    
    $exportFormat = $_POST['export_format'] ?? 'csv';
    $reportType = $_POST['report_type'] ?? 'detailed';
    
    $filters = [
        'start_date' => $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
        'end_date' => $_POST['end_date'] ?? date('Y-m-d'),
        'status' => $_POST['status'] ?? null,
        'school_year_id' => isset($_POST['school_year']) && $_POST['school_year'] !== '' ? (int)$_POST['school_year'] : null,
        'class_id' => isset($_POST['class_id']) && $_POST['class_id'] !== '' ? (int)$_POST['class_id'] : null,
        'teacher_id' => isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? (int)$_POST['teacher_id'] : null
    ];
    
    if (isTeacher() && $userId) {
        $filters['teacher_id'] = $userId;
    }
    $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
    
    $schoolYearName = '';
    if (isset($filters['school_year_id'])) {
        $sy = getSchoolYearById($filters['school_year_id']);
        if ($sy) $schoolYearName = '_' . str_replace('-', '_', $sy['name']);
    } elseif ($activeSchoolYear) {
        $schoolYearName = '_' . str_replace('-', '_', $activeSchoolYear['name']);
    }
    
    try {
        if ($reportType === 'summary') {
            $data = getStudentAttendanceSummary($filters['start_date'], $filters['end_date'], $filters);
            $downloadName = 'student_summary' . $schoolYearName . '_' . date('Y-m-d');
            switch ($exportFormat) {
                case 'csv': $filepath = exportStudentSummaryCsv($data, 'student_summary' . $schoolYearName); $downloadName .= '.csv'; break;
                case 'pdf': $filepath = exportStudentSummaryPdf($data, 'student_summary' . $schoolYearName); $downloadName .= '.pdf'; break;
                case 'excel': $filepath = exportStudentSummaryExcel($data, 'student_summary' . $schoolYearName); $downloadName .= '.xlsx'; break;
            }
        } else {
            $data = generateReport($filters);
            $stats = calculateReportStats($data, $filters);
            $downloadName = 'attendance_report' . $schoolYearName . '_' . date('Y-m-d');
            switch ($exportFormat) {
                case 'csv': $filepath = exportToCsv($data, 'attendance_report' . $schoolYearName); $downloadName .= '.csv'; break;
                case 'pdf': $filepath = exportToPdf($data, $stats, 'attendance_report' . $schoolYearName); $downloadName .= '.pdf'; break;
                case 'excel': $filepath = exportToExcel($data, $stats, 'attendance_report' . $schoolYearName); $downloadName .= '.xlsx'; break;
            }
        }
        
        if ($filepath && file_exists($filepath)) {
            if ($exportFormat === 'csv') downloadCsv($filepath, $downloadName);
            elseif ($exportFormat === 'pdf') downloadPdf($filepath, $downloadName);
            elseif ($exportFormat === 'excel') downloadExcel($filepath, $downloadName);
        } else {
            setFlash('error', 'Failed to generate export file');
        }
    } catch (Exception $e) {
        setFlash('error', 'Export failed: ' . $e->getMessage());
    }
}

// Filter values
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedStatus = $_GET['status'] ?? '';
$selectedSchoolYearId = isTeacher() ? ($activeSchoolYear ? $activeSchoolYear['id'] : null) : (isset($_GET['school_year']) && $_GET['school_year'] !== '' ? (int)$_GET['school_year'] : ($activeSchoolYear ? $activeSchoolYear['id'] : null));
$selectedClassId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$selectedTeacherId = isset($_GET['teacher_id']) && $_GET['teacher_id'] !== '' ? (int)$_GET['teacher_id'] : null;
$teacherIdFilter = (isTeacher() && $userId) ? $userId : null;

// Build filters
$filters = array_filter([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'status' => $selectedStatus ?: null,
    'school_year_id' => $selectedSchoolYearId,
    'class_id' => $selectedClassId,
    'teacher_id' => $teacherIdFilter ?: $selectedTeacherId
], fn($v) => $v !== null && $v !== '');

$previewData = generateReport($filters);
$previewStats = calculateReportStats($previewData, $filters);

// Chart data
$overviewData = generateReport(['start_date' => $startDate, 'end_date' => $endDate, 'school_year_id' => $selectedSchoolYearId, 'class_id' => $selectedClassId, 'teacher_id' => $teacherIdFilter ?: $selectedTeacherId]);
$overviewStats = calculateReportStats($overviewData, $filters);
$weeklyTrend = getWeeklyAttendanceTrend(8, $selectedSchoolYearId, $teacherIdFilter ?: $selectedTeacherId);
$classComparison = getClassAttendanceComparison($startDate, $endDate, $selectedSchoolYearId, $teacherIdFilter ?: $selectedTeacherId);

$pageTitle = 'Reports';
$csrfToken = generateCsrfToken();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full overflow-x-hidden"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-full overflow-hidden">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Attendance Reports</h1>
            <p class="text-sm text-gray-500 mt-1">Analytics and export tools</p>
        </div>
        
        <?php if ($flash = getFlash()): ?>
        <div class="mb-4 p-3 rounded-lg <?php echo $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-xl p-4 border border-gray-100">
                <p class="text-xs font-medium text-gray-500">Total Records</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($previewStats['total_records'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100">
                <p class="text-xs font-medium text-gray-500">Present</p>
                <p class="text-xl sm:text-2xl font-bold text-green-600 mt-1"><?php echo number_format($previewStats['present'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100">
                <p class="text-xs font-medium text-gray-500">Late</p>
                <p class="text-xl sm:text-2xl font-bold text-amber-500 mt-1"><?php echo number_format($previewStats['late'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100">
                <p class="text-xs font-medium text-gray-500">Absent</p>
                <p class="text-xl sm:text-2xl font-bold text-red-500 mt-1"><?php echo number_format($previewStats['absent'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100 col-span-2 sm:col-span-1">
                <p class="text-xs font-medium text-gray-500">Attendance Rate</p>
                <p class="text-xl sm:text-2xl font-bold text-violet-600 mt-1"><?php echo $previewStats['attendance_percentage'] ?? 0; ?>%</p>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <!-- Doughnut Chart -->
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Distribution</h3>
                <div class="relative h-48">
                    <canvas id="attendanceOverviewChart"></canvas>
                </div>
                <div class="flex justify-center gap-4 mt-4 text-xs">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Present</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Late</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Absent</span>
                </div>
            </div>
            
            <!-- Line Chart -->
            <div class="bg-white rounded-xl p-5 border border-gray-100 lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Weekly Trend</h3>
                <div class="relative h-48">
                    <canvas id="weeklyTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters & Export -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <!-- Filters -->
            <div class="lg:col-span-2 bg-white rounded-xl p-5 border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Filters</h3>
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo e($startDate); ?>" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo e($endDate); ?>" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                        </div>
                        <?php if (!isTeacher()): ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">School Year</label>
                            <select name="school_year" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                                <option value="">All Years</option>
                                <?php foreach ($allSchoolYears as $sy): ?>
                                <option value="<?php echo $sy['id']; ?>" <?php echo $selectedSchoolYearId == $sy['id'] ? 'selected' : ''; ?>><?php echo e($sy['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Class</label>
                            <select name="class_id" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                                <option value="">All Classes</option>
                                <?php foreach ($availableClassesForFilter as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>><?php echo e($class['grade_level'] . ' - ' . $class['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!isTeacher() && !empty($availableTeachers)): ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Teacher</label>
                            <select name="teacher_id" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                                <option value="">All Teachers</option>
                                <?php foreach ($availableTeachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $selectedTeacherId == $teacher['id'] ? 'selected' : ''; ?>><?php echo e($teacher['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900 focus:ring-1 focus:ring-violet-500">
                                <option value="">All</option>
                                <option value="present" <?php echo $selectedStatus === 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $selectedStatus === 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $selectedStatus === 'absent' ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">Apply</button>
                        <a href="?" class="px-4 py-2 border border-gray-100 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Export -->
            <div class="bg-white rounded-xl p-5 border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Export</h3>
                <?php if ($showPremiumUpgrade): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="text-xs text-amber-700">Export requires premium subscription.</p>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="export">
                    <input type="hidden" name="start_date" value="<?php echo e($startDate); ?>">
                    <input type="hidden" name="end_date" value="<?php echo e($endDate); ?>">
                    <input type="hidden" name="status" value="<?php echo e($selectedStatus); ?>">
                    <input type="hidden" name="school_year" value="<?php echo e($selectedSchoolYearId); ?>">
                    <input type="hidden" name="class_id" value="<?php echo e($selectedClassId); ?>">
                    <input type="hidden" name="teacher_id" value="<?php echo e($selectedTeacherId); ?>">
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Report Type</label>
                            <select name="report_type" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900" <?php echo $showPremiumUpgrade ? 'disabled' : ''; ?>>
                                <option value="detailed">Detailed Records</option>
                                <option value="summary">Student Summary</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Format</label>
                            <select name="export_format" class="w-full px-3 py-2 text-sm border border-gray-100 rounded-lg bg-white text-gray-900" <?php echo $showPremiumUpgrade ? 'disabled' : ''; ?>>
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors flex items-center justify-center gap-2 <?php echo $showPremiumUpgrade ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $showPremiumUpgrade ? 'disabled' : ''; ?>>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Download
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($classComparison)): ?>
        <!-- Class Comparison -->
        <div class="bg-white rounded-xl p-5 border border-gray-100 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Class Comparison</h3>
            <div class="relative h-64">
                <canvas id="classComparisonChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Records</h3>
                    <p class="text-xs text-gray-500">Showing <?php echo min(50, count($previewData)); ?> of <?php echo count($previewData); ?></p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y border-gray-100">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time Out</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y border-gray-100">
                        <?php $displayData = array_slice($previewData, 0, 50); ?>
                        <?php if (empty($displayData)): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">No records found</td></tr>
                        <?php else: ?>
                        <?php foreach ($displayData as $record): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-sm font-medium text-gray-900"><?php echo e($record['first_name'] . ' ' . $record['last_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($record['student_number'] ?? $record['student_id'] ?? ''); ?></p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo e(($record['class'] ?? 'N/A') . ($record['section'] ? ' - ' . $record['section'] : '')); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo e(date('M j, Y', strtotime($record['attendance_date']))); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo !empty($record['check_in_time']) ? date('g:i A', strtotime($record['check_in_time'])) : '—'; ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo !empty($record['check_out_time']) ? date('g:i A', strtotime($record['check_out_time'])) : '—'; ?></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $record['status'] === 'present' ? 'bg-green-100 text-green-700' : ($record['status'] === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo ucfirst($record['status']); ?>
                                </span>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.classList.contains('dark-mode');
const textColor = isDark ? '#9ca3af' : '#6b7280';
const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';

Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;

// Doughnut Chart
const overviewCtx = document.getElementById('attendanceOverviewChart');
if (overviewCtx) {
    new Chart(overviewCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [<?php echo $overviewStats['present'] ?? 0; ?>, <?php echo $overviewStats['late'] ?? 0; ?>, <?php echo $overviewStats['absent'] ?? 0; ?>],
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });
}

// Line Chart
const trendCtx = document.getElementById('weeklyTrendChart');
const weeklyData = <?php echo json_encode($weeklyTrend); ?>;
if (trendCtx && weeklyData.length > 0) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: weeklyData.map(w => new Date(w.week_start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
            datasets: [{
                label: 'Rate %',
                data: weeklyData.map(w => w.attendance_rate || 0),
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: gridColor } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

// Bar Chart
const classCtx = document.getElementById('classComparisonChart');
const classData = <?php echo json_encode($classComparison); ?>;
if (classCtx && classData.length > 0) {
    new Chart(classCtx, {
        type: 'bar',
        data: {
            labels: classData.map(c => c.class_name),
            datasets: [{
                label: 'Rate %',
                data: classData.map(c => c.attendance_rate || 0),
                backgroundColor: classData.map(c => c.attendance_rate >= 90 ? '#10B981' : c.attendance_rate >= 75 ? '#F59E0B' : '#EF4444'),
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: gridColor } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
