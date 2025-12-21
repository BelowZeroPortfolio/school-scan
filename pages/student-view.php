<?php
/**
 * View Student Page
 * Display student details with barcode
 * 
 * Requirements: 4.4, 6.3, 9.4 - Show enrollment history, current class/teacher, filter attendance by school year
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require authentication
requireAuth();

// Get current user info
$currentUser = getCurrentUser();
$userId = $currentUser['id'] ?? null;

// Get student ID from query string
$studentId = (int)($_GET['id'] ?? $_POST['student_id'] ?? 0);

if (!$studentId) {
    setFlash('error', 'Invalid student ID.');
    redirect(config('app_url') . '/pages/students.php');
}

// Fetch student data
$sql = "SELECT * FROM students WHERE id = ?";
$student = dbFetchOne($sql, [$studentId]);

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect(config('app_url') . '/pages/students.php');
}

// Check teacher access (Requirements: 6.3, 6.4)
if (isTeacher() && $userId) {
    if (!canTeacherAccessStudent($userId, $studentId)) {
        setFlash('error', 'You do not have permission to access this student.');
        redirect(config('app_url') . '/pages/students.php');
    }
}

// Get active school year
$activeSchoolYear = getActiveSchoolYear();
$schoolYearId = $activeSchoolYear ? $activeSchoolYear['id'] : null;

// Get current class for the student (Requirements: 6.3)
$currentClass = getStudentClass($studentId, $schoolYearId);

// Handle POST actions for enrollment management
if (isPost() && hasAnyRole(['admin', 'operator'])) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    // Handle SMS toggle
    if ($action === 'toggle_sms') {
        $smsEnabled = isset($_POST['sms_enabled']) ? 1 : 0;
        $updateSql = "UPDATE students SET sms_enabled = ? WHERE id = ?";
        dbExecute($updateSql, [$smsEnabled, $studentId]);
        
        // Refresh student data
        $student = dbFetchOne("SELECT * FROM students WHERE id = ?", [$studentId]);
        
        $statusText = $smsEnabled ? 'enabled' : 'disabled';
        setFlash('success', "SMS notifications {$statusText} for this student.");
        redirect(config('app_url') . '/pages/student-view.php?id=' . $studentId);
    }
    
    if ($action === 'update_status' && $currentClass) {
        $newStatus = sanitizeString($_POST['status'] ?? '');
        $reason = sanitizeString($_POST['reason'] ?? '');
        
        $result = updateEnrollmentStatus($studentId, $currentClass['id'], $newStatus, $userId, $reason);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(config('app_url') . '/pages/student-view.php?id=' . $studentId);
    }
    
    if ($action === 'transfer_class' && $currentClass) {
        $toClassId = (int)($_POST['to_class_id'] ?? 0);
        $reason = sanitizeString($_POST['reason'] ?? '');
        
        if ($toClassId > 0) {
            $result = transferStudentToClass($studentId, $currentClass['id'], $toClassId, $userId, $reason);
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
        } else {
            setFlash('error', 'Please select a class to transfer to.');
        }
        redirect(config('app_url') . '/pages/student-view.php?id=' . $studentId);
    }
}

// Refresh current class after potential changes
$currentClass = getStudentClass($studentId, $schoolYearId);

// Get available classes for transfer (same school year, different from current)
$availableClassesForTransfer = [];
if ($currentClass && $schoolYearId) {
    $allClasses = getClassesBySchoolYear($schoolYearId);
    foreach ($allClasses as $class) {
        if ($class['id'] != $currentClass['id']) {
            $availableClassesForTransfer[] = $class;
        }
    }
}

// Get enrollment history across all school years (Requirements: 4.4, 9.4)
$enrollmentHistory = getStudentEnrollmentHistory($studentId);

$pageTitle = 'View Student';
$currentUser = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <?php echo displayFlash(); ?>
        
        <!-- Page Header -->
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <a href="<?php echo config('app_url'); ?>/pages/students.php" class="hover:text-violet-600">Students</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span>View</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">
                    <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>
                </h1>
                <p class="text-sm sm:text-base text-gray-500 mt-1">Student ID: <?php echo e($student['student_id']); ?></p>
            </div>
            <div class="flex gap-3">
                <a href="<?php echo config('app_url'); ?>/pages/students.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to List
                </a>
                <?php if (hasAnyRole(['admin', 'operator'])): ?>
                    <a href="<?php echo config('app_url'); ?>/pages/student-edit.php?id=<?php echo $student['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Student
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Student Information Card -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Student Information</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Student ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo e($student['student_id']); ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">LRN</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono"><?php echo $student['lrn'] ? e($student['lrn']) : '<span class="text-gray-400">Not provided</span>'; ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">Date of Birth</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo $student['date_of_birth'] ? formatDate($student['date_of_birth']) : 'Not provided'; ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Current Class</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php if ($currentClass): ?>
                                    <span class="font-medium"><?php echo e($currentClass['grade_level'] . ' - ' . $currentClass['section']); ?></span>
                                    <span class="text-gray-500 ml-2">(<?php echo e($currentClass['school_year_name']); ?>)</span>
                                <?php else: ?>
                                    <span class="text-amber-600">Not enrolled in any class</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if ($currentClass): ?>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">Teacher</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo e($currentClass['teacher_name']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 sm:mt-0 sm:col-span-2">
                                <?php if ($student['is_active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Active</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">Inactive</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo formatDateTime($student['created_at']); ?></dd>
                        </div>
                    </div>
                </div>

                <!-- Parent Information Card -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Parent/Guardian Information</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo $student['parent_name'] ? e($student['parent_name']) : '<span class="text-gray-400">Not provided</span>'; ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo $student['parent_phone'] ? e($student['parent_phone']) : '<span class="text-gray-400">Not provided</span>'; ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo $student['parent_email'] ? e($student['parent_email']) : '<span class="text-gray-400">Not provided</span>'; ?></dd>
                        </div>
                        <div class="px-6 py-4 sm:grid sm:grid-cols-3 sm:gap-4 bg-gray-50/30">
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo $student['address'] ? nl2br(e($student['address'])) : '<span class="text-gray-400">Not provided</span>'; ?></dd>
                        </div>
                    </div>
                </div>
                
                <!-- Enrollment History Card (Requirements: 4.4, 9.4) -->
                <?php if (!empty($enrollmentHistory)): ?>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Enrollment History</h3>
                        <p class="text-sm text-gray-500 mt-1">Class assignments across school years</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php foreach ($enrollmentHistory as $enrollment): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors <?php echo $enrollment['is_current_year'] ? 'bg-violet-50/30' : ''; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-gray-900"><?php echo e($enrollment['school_year_name']); ?></span>
                                            <?php if ($enrollment['is_current_year']): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700">Current</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-900"><?php echo e($enrollment['grade_level'] . ' - ' . $enrollment['section']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600"><?php echo e($enrollment['teacher_name']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-500"><?php echo formatDate($enrollment['enrolled_at']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($enrollment['enrollment_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar Cards -->
            <div class="lg:col-span-1 space-y-6">
                <!-- QR Code Card -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Student QR Code</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-center">
                            <div id="student-qrcode" class="mx-auto mb-4 border border-gray-200 p-4 rounded-lg inline-block" style="background-color: #ffffff;"></div>
                            <p class="text-sm text-gray-500 mb-4">Scan this QR code to record attendance</p>
                            <button type="button" onclick="downloadQRCode()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download QR Code
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <button type="button" onclick="generateIDCard()" 
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                            Generate ID Card
                        </button>
                        <a href="<?php echo config('app_url'); ?>/pages/attendance-history.php?student_id=<?php echo $student['id']; ?>" 
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            View Attendance History
                        </a>
                        <?php if (hasAnyRole(['admin', 'operator'])): ?>
                            <a href="<?php echo config('app_url'); ?>/scan.php" 
                                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                Record Attendance
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- SMS Notifications Card (Admin/Operator only) -->
                <?php if (hasAnyRole(['admin', 'operator'])): ?>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">SMS Notifications</h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="toggle_sms">
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg <?php echo !empty($student['sms_enabled']) ? 'bg-green-100' : 'bg-gray-100'; ?> flex items-center justify-center">
                                        <svg class="w-5 h-5 <?php echo !empty($student['sms_enabled']) ? 'text-green-600' : 'text-gray-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Send SMS to Parent</p>
                                        <p class="text-xs text-gray-500"><?php echo !empty($student['sms_enabled']) ? 'Paid - Active' : 'Not subscribed'; ?></p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="sms_enabled" class="sr-only peer" <?php echo !empty($student['sms_enabled']) ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                </label>
                            </div>
                            
                            <p class="mt-3 text-xs text-gray-500">
                                <?php if (!empty($student['sms_enabled'])): ?>
                                    <span class="text-green-600">✓</span> Parent will receive SMS when attendance is scanned.
                                <?php else: ?>
                                    <span class="text-gray-400">○</span> Enable to send SMS notifications to parent.
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Enrollment Management Card (Admin/Operator only) -->
                <?php if (hasAnyRole(['admin', 'operator']) && $currentClass): ?>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Enrollment Management</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <!-- Transfer to Another Class -->
                        <button type="button" onclick="openTransferModal()"
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-blue-300 rounded-lg shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Transfer to Another Class
                        </button>
                        
                        <!-- Mark as Withdrawn -->
                        <button type="button" onclick="openStatusModal('withdrawn', 'Withdraw Student')"
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-amber-300 rounded-lg shadow-sm text-sm font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Mark as Withdrawn
                        </button>
                        
                        <!-- Mark as Dropped -->
                        <button type="button" onclick="openStatusModal('dropped', 'Mark as Dropped')"
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-red-300 rounded-lg shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Mark as Dropped
                        </button>
                        
                        <!-- Transferred Out -->
                        <button type="button" onclick="openStatusModal('transferred_out', 'Mark as Transferred Out')"
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            Transferred to Another School
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- ID Card Modal -->
<div id="idCardModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeIDCardModal()"></div>
        
        <div class="relative bg-white rounded-xl shadow-xl transform transition-all sm:max-w-3xl sm:w-full mx-auto">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Student ID Card</h3>
                <div class="flex items-center gap-2">
                    <button onclick="printIDCard()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
                    <button onclick="downloadIDCard()" class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-lg text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </button>
                    <button onclick="closeIDCardModal()" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6 overflow-auto max-h-[80vh]">
                <div id="idCardContainer" class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <!-- Front of ID Card -->
                    <div id="idCardFront" style="width: 204px; height: 324px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #CE1126 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <!-- Header with School Name -->
                        <div style="background: rgba(206,17,38,0.9); padding: 10px 12px; text-align: center;">
                            <h3 style="font-size: 11px; font-weight: bold; color: #FCD116; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;"><?php echo e(config('school_name', 'School Name')); ?></h3>
                        </div>
                        
                        <div style="padding: 12px; display: flex; flex-direction: column; height: calc(100% - 42px);">
                            <!-- Photo and Logo Row -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <!-- Student Photo -->
                                <div style="width: 80px; height: 90px; border: 2px solid #CE1126; border-radius: 6px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 40px; height: 40px; color: #CE1126;" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                </div>
                                <!-- School Logo -->
                                <div style="width: 70px; height: 70px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 9px; color: rgba(255,255,255,0.6); text-align: center;">LOGO</span>
                                </div>
                            </div>
                            
                            <!-- Student Info -->
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                <!-- ID Number -->
                                <div style="margin-bottom: 8px;">
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin: 0 0 2px 0;">ID No.</p>
                                    <p style="font-size: 13px; font-weight: bold; color: #FCD116; font-family: monospace; margin: 0;"><?php echo e($student['lrn'] ?? $student['student_id']); ?></p>
                                </div>
                                
                                <!-- Student Name -->
                                <div style="margin-bottom: 8px;">
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin: 0 0 2px 0;">Student Name</p>
                                    <p style="font-size: 12px; font-weight: bold; color: white; margin: 0;"><?php echo e(strtoupper($student['first_name'] . ' ' . $student['last_name'])); ?></p>
                                </div>
                                
                                <!-- Grade & Section -->
                                <div>
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin: 0 0 2px 0;">Grade & Section</p>
                                    <p style="font-size: 12px; font-weight: 600; color: #FCD116; margin: 0;"><?php echo $currentClass ? e($currentClass['grade_level'] . ' - ' . $currentClass['section']) : 'Not Enrolled'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Back of ID Card -->
                    <div id="idCardBack" style="width: 204px; height: 324px; background: linear-gradient(135deg, #CE1126 0%, #1a1a1a 50%, #0038A8 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <div style="padding: 16px; display: flex; flex-direction: column; height: 100%; align-items: center;">
                            <!-- QR Code -->
                            <div style="background: white; border-radius: 8px; padding: 10px; margin-bottom: 8px;">
                                <div id="qrcode-back" style="width: 100px; height: 100px;"></div>
                            </div>
                            
                            <!-- School Year -->
                            <div style="text-align: center; margin-bottom: 16px;">
                                <p style="font-size: 10px; color: rgba(255,255,255,0.7); margin: 0;">S.Y.</p>
                                <p style="font-size: 12px; font-weight: bold; color: #FCD116; margin: 0;"><?php echo $activeSchoolYear ? e($activeSchoolYear['name']) : date('Y') . '-' . (date('Y') + 1); ?></p>
                            </div>
                            
                            <!-- Contact Person Section -->
                            <div style="width: 100%; flex: 1; background: rgba(255,255,255,0.1); border-radius: 8px; padding: 10px;">
                                <div style="text-align: center; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #FCD116;">
                                    <p style="font-size: 10px; font-weight: bold; color: #FCD116; text-transform: uppercase; margin: 0;">Contact Person</p>
                                </div>
                                
                                <!-- Contact Info Lines -->
                                <div style="padding: 0 4px;">
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 10px; font-weight: 600; color: white; margin: 0;"><?php echo e($student['parent_name'] ?: '—'); ?></p>
                                    </div>
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 10px; color: rgba(255,255,255,0.9); margin: 0;"><?php echo e($student['parent_phone'] ?: '—'); ?></p>
                                    </div>
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 9px; color: rgba(255,255,255,0.9); word-break: break-all; margin: 0;"><?php echo e($student['parent_email'] ?: '—'); ?></p>
                                    </div>
                                    <div style="border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 9px; color: rgba(255,255,255,0.9); margin: 0;"><?php echo e($student['address'] ?: '—'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// ID Card Functions
function generateIDCard() {
    document.getElementById('idCardModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Generate QR code for back of ID card
    const qrContainerBack = document.getElementById('qrcode-back');
    qrContainerBack.innerHTML = ''; // Clear previous
    
    new QRCode(qrContainerBack, {
        text: '<?php echo e($student['lrn'] ?? $student['student_id']); ?>',
        width: 100,
        height: 100,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

function closeIDCardModal() {
    document.getElementById('idCardModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function printIDCard() {
    const container = document.getElementById('idCardContainer');
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student ID Card - <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></title>
            <script src="https://cdn.tailwindcss.com"><\/script>
            <style>
                @media print {
                    @page { size: portrait; margin: 0.5in; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
                body { font-family: system-ui, -apple-system, sans-serif; }
            </style>
        </head>
        <body class="p-8 bg-white">
            <div class="flex flex-row gap-8 justify-center items-start">
                ${container.innerHTML}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

async function downloadIDCard() {
    const container = document.getElementById('idCardContainer');
    
    try {
        const canvas = await html2canvas(container, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false
        });
        
        const link = document.createElement('a');
        link.download = 'ID_Card_<?php echo e($student['lrn'] ?? $student['student_id']); ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        console.error('Download failed:', error);
        alert('Download failed. Please use the Print option instead.');
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeIDCardModal();
        closeStatusModal();
        closeTransferModal();
    }
});

// Enrollment Status Modal Functions
function openStatusModal(status, title) {
    document.getElementById('statusModalTitle').textContent = title;
    document.getElementById('statusInput').value = status;
    document.getElementById('statusModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Transfer Modal Functions
function openTransferModal() {
    document.getElementById('transferModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.add('hidden');
    document.body.style.overflow = '';
}
</script>

<!-- Status Change Modal -->
<div id="statusModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeStatusModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full mx-auto">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <input type="hidden" name="status" id="statusInput" value="">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 id="statusModalTitle" class="text-lg font-semibold text-gray-900 mb-4">Update Status</h3>
                    
                    <div class="mb-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <textarea name="reason" id="reason" rows="3" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            placeholder="Enter reason for this status change..."></textarea>
                    </div>
                    
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p class="text-sm text-amber-700">
                            <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            This action will remove the student from their current class.
                        </p>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeStatusModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transfer Class Modal -->
<div id="transferModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeTransferModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full mx-auto">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="transfer_class">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Transfer to Another Class</h3>
                    
                    <?php if ($currentClass): ?>
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Current Class:</p>
                        <p class="font-medium text-gray-900"><?php echo e($currentClass['grade_level'] . ' - ' . $currentClass['section']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label for="to_class_id" class="block text-sm font-medium text-gray-700 mb-1">Transfer To <span class="text-red-500">*</span></label>
                        <select name="to_class_id" id="to_class_id" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                            <option value="">— Select a class —</option>
                            <?php foreach ($availableClassesForTransfer as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo e($class['grade_level'] . ' - ' . $class['section']); ?>
                                    (<?php echo e($class['teacher_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="transfer_reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
                        <textarea name="reason" id="transfer_reason" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            placeholder="Enter reason for transfer..."></textarea>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeTransferModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Transfer Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Generate QR code on page load
    document.addEventListener('DOMContentLoaded', function() {
        const qrContainer = document.getElementById('student-qrcode');
        if (qrContainer) {
            new QRCode(qrContainer, {
                text: '<?php echo e($student['lrn'] ?? $student['student_id']); ?>',
                width: 150,
                height: 150,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
    
    // Download QR code as PNG
    function downloadQRCode() {
        const qrContainer = document.getElementById('student-qrcode');
        const canvas = qrContainer.querySelector('canvas');
        if (canvas) {
            const link = document.createElement('a');
            link.download = 'qrcode_<?php echo e($student['lrn'] ?? $student['student_id']); ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
