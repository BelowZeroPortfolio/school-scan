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

// Require authentication
requireAuth();

// Check if user has permission to view reports
// Viewers can see reports, but only for their assigned classes
$currentUser = getCurrentUser();
$isAdmin = hasRole('admin');
$isOperator = hasRole('operator');

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    verifyCsrf();
    
    $exportFormat = $_POST['export_format'] ?? 'csv';
    $reportType = $_POST['report_type'] ?? 'detailed';
    
    // Build filters
    $filters = [
        'start_date' => $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
        'end_date' => $_POST['end_date'] ?? date('Y-m-d'),
        'class' => $_POST['class'] ?? null,
        'section' => $_POST['section'] ?? null,
        'status' => $_POST['status'] ?? null
    ];
    
    // Remove empty filters
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    try {
        if ($reportType === 'summary') {
            // Student summary report
            $data = getStudentAttendanceSummary(
                $filters['start_date'],
                $filters['end_date'],
                $filters
            );
            
            $filepath = false;
            $downloadName = 'student_summary_' . date('Y-m-d');
            
            switch ($exportFormat) {
                case 'csv':
                    $filepath = exportStudentSummaryCsv($data, 'student_summary');
                    $downloadName .= '.csv';
                    break;
                case 'pdf':
                    $filepath = exportStudentSummaryPdf($data, 'student_summary');
                    $downloadName .= '.pdf';
                    break;
                case 'excel':
                    $filepath = exportStudentSummaryExcel($data, 'student_summary');
                    $downloadName .= '.xlsx';
                    break;
            }
        } else {
            // Detailed attendance report
            $data = generateReport($filters);
            $stats = calculateReportStats($data, $filters);
            
            $filepath = false;
            $downloadName = 'attendance_report_' . date('Y-m-d');
            
            switch ($exportFormat) {
                case 'csv':
                    $filepath = exportToCsv($data, 'attendance_report');
                    $downloadName .= '.csv';
                    break;
                case 'pdf':
                    $filepath = exportToPdf($data, $stats, 'attendance_report');
                    $downloadName .= '.pdf';
                    break;
                case 'excel':
                    $filepath = exportToExcel($data, $stats, 'attendance_report');
                    $downloadName .= '.xlsx';
                    break;
            }
        }
        
        if ($filepath && file_exists($filepath)) {
            // Download the file
            if ($exportFormat === 'csv') {
                downloadCsv($filepath, $downloadName);
            } elseif ($exportFormat === 'pdf') {
                downloadPdf($filepath, $downloadName);
            } elseif ($exportFormat === 'excel') {
                downloadExcel($filepath, $downloadName);
            }
        } else {
            setFlash('error', 'Failed to generate export file');
        }
    } catch (Exception $e) {
        setFlash('error', 'Export failed: ' . $e->getMessage());
    }
}

// Get filter options
$availableClasses = getAvailableClasses();
$availableSections = getAvailableSections();

// Default filter values
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedClass = $_GET['class'] ?? '';
$selectedSection = $_GET['section'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

// Generate preview data if filters are applied
$previewData = [];
$previewStats = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preview'])) {
    $filters = [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'class' => $selectedClass ?: null,
        'section' => $selectedSection ?: null,
        'status' => $selectedStatus ?: null
    ];
    
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    $previewData = generateReport($filters);
    $previewStats = calculateReportStats($previewData, $filters);
}

$pageTitle = 'Attendance Reports';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
          :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            <!-- Page Header -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight"><?php echo e($pageTitle); ?></h1>
                <p class="text-sm sm:text-base text-gray-500 mt-1">Generate and export attendance reports with custom filters</p>
            </div>
            
            <!-- Flash Messages -->
            <?php if ($flash = getFlash()): ?>
                <div class="mb-6 p-4 rounded-xl border-l-4 <?php echo $flash['type'] === 'error' ? 'bg-red-50 border-red-500 text-red-800' : 'bg-green-50 border-green-500 text-green-800'; ?>">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Form -->
            <div class="bg-white rounded-xl p-6 border border-gray-100 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Report Filters</h2>
                
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Date Range -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo e($startDate); ?>" 
                                   class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                        </div>
                        
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo e($endDate); ?>" 
                                   class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                        </div>
                        
                        <!-- Class Filter -->
                        <div>
                            <label for="class" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select id="class" name="class" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                                <option value="">All Classes</option>
                                <?php foreach ($availableClasses as $class): ?>
                                    <option value="<?php echo e($class); ?>" <?php echo $selectedClass === $class ? 'selected' : ''; ?>>
                                        <?php echo e($class); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Section Filter -->
                        <div>
                            <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select id="section" name="section" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                                <option value="">All Sections</option>
                                <?php foreach ($availableSections as $section): ?>
                                    <option value="<?php echo e($section); ?>" <?php echo $selectedSection === $section ? 'selected' : ''; ?>>
                                        <?php echo e($section); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                                <option value="">All Statuses</option>
                                <option value="present" <?php echo $selectedStatus === 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $selectedStatus === 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $selectedStatus === 'absent' ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" name="preview" value="1" 
                                class="px-6 py-2 bg-violet-600 text-white rounded-xl hover:bg-violet-700 focus:ring-4 focus:ring-violet-300 transition-all font-medium">
                            Preview Report
                        </button>
                        <a href="?" class="px-6 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all font-medium">
                            Reset Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Summary -->
            <?php if (!empty($previewStats)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-6 border border-gray-100">
                        <div class="text-sm font-medium text-gray-600">Total Records</div>
                        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo e($previewStats['total_records']); ?></div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100">
                        <div class="text-sm font-medium text-gray-600">Present</div>
                        <div class="text-2xl font-bold text-green-600 mt-1"><?php echo e($previewStats['present']); ?></div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100">
                        <div class="text-sm font-medium text-gray-600">Late</div>
                        <div class="text-2xl font-bold text-orange-600 mt-1"><?php echo e($previewStats['late']); ?></div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100">
                        <div class="text-sm font-medium text-gray-600">Absent</div>
                        <div class="text-2xl font-bold text-red-600 mt-1"><?php echo e($previewStats['absent']); ?></div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100">
                        <div class="text-sm font-medium text-gray-600">Attendance %</div>
                        <div class="text-2xl font-bold text-violet-600 mt-1"><?php echo e($previewStats['attendance_percentage']); ?>%</div>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="bg-white rounded-xl p-6 border border-gray-100 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Export Report</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="export">
                        <input type="hidden" name="start_date" value="<?php echo e($startDate); ?>">
                        <input type="hidden" name="end_date" value="<?php echo e($endDate); ?>">
                        <input type="hidden" name="class" value="<?php echo e($selectedClass); ?>">
                        <input type="hidden" name="section" value="<?php echo e($selectedSection); ?>">
                        <input type="hidden" name="status" value="<?php echo e($selectedStatus); ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                                <select id="report_type" name="report_type" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                                    <option value="detailed">Detailed Attendance Records</option>
                                    <option value="summary">Student Summary</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="export_format" class="block text-sm font-medium text-gray-700 mb-1">Export Format</label>
                                <select id="export_format" name="export_format" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors theme-bg-card theme-text-primary">
                                    <option value="csv">CSV (Excel Compatible)</option>
                                    <option value="excel">Excel (.xlsx)</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="px-6 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 focus:ring-4 focus:ring-orange-300 transition-all font-medium">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download Report
                        </button>
                    </form>
                </div>
                
                <!-- Preview Table -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-900">Report Preview</h2>
                        <p class="text-sm text-gray-500 mt-1">Showing first 50 records</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php 
                                $displayData = array_slice($previewData, 0, 50);
                                foreach ($displayData as $record): 
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e($record['attendance_date']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo e($record['first_name'] . ' ' . $record['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo e($record['student_number']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e($record['class'] . ' ' . $record['section']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e(date('H:i', strtotime($record['check_in_time']))); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                echo $record['status'] === 'present' ? 'bg-green-100 text-green-800' : 
                                                     ($record['status'] === 'late' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'); 
                                                ?>">
                                                <?php echo e(ucfirst($record['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($displayData)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            No records found for the selected filters
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
