<?php
/**
 * View Student Page
 * Display student details with barcode
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require authentication
requireAuth();

// Get student ID from query string
$studentId = (int)($_GET['id'] ?? 0);

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
                            <dt class="text-sm font-medium text-gray-500">Class</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo e($student['class'] . ($student['section'] ? ' - Section ' . $student['section'] : '')); ?></dd>
                        </div>
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
            </div>
            
            <!-- Sidebar Cards -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Barcode Card -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Student Barcode</h3>
                    </div>
                    <div class="p-6">
                        <?php if ($student['barcode_path']): ?>
                            <div class="text-center">
                                <img src="<?php echo config('app_url'); ?>/<?php echo e($student['barcode_path']); ?>" 
                                    alt="Student Barcode" 
                                    class="mx-auto mb-4 border border-gray-200 p-3 rounded-lg bg-white"
                                    style="max-width: 100%;">
                                <p class="text-sm text-gray-500 mb-4">Scan this barcode to record attendance</p>
                                <a href="<?php echo config('app_url'); ?>/<?php echo e($student['barcode_path']); ?>" 
                                    download="barcode_<?php echo e($student['lrn'] ?? $student['student_id']); ?>.png"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download Barcode
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">No barcode available</p>
                            </div>
                        <?php endif; ?>
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
                            <a href="<?php echo config('app_url'); ?>/pages/scan.php?student_id=<?php echo $student['student_id']; ?>" 
                                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                Record Attendance
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ID Card Modal -->
<div id="idCardModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeIDCardModal()"></div>
        
        <div class="relative bg-white rounded-xl shadow-xl transform transition-all sm:max-w-4xl sm:w-full mx-auto">
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
                <div id="idCardContainer" class="flex flex-col sm:flex-row gap-8 justify-center items-center">
                    <!-- Front of ID Card (Portrait) - QR Code -->
                    <div id="idCardFront" class="id-card-front relative" style="width: 300px; height: 480px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #CE1126 100%); border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: hidden;">
                        <!-- Overlay effect -->
                        <div class="absolute inset-0" style="background: linear-gradient(45deg, transparent 30%, rgba(206, 17, 38, 0.1) 50%, transparent 70%), linear-gradient(-45deg, transparent 30%, rgba(0, 56, 168, 0.1) 50%, transparent 70%); pointer-events: none;"></div>
                        
                        <!-- Content -->
                        <div class="relative h-full flex flex-col items-center p-6 text-white">
                            <!-- Role Badge -->
                            <div class="px-6 py-1.5 rounded-full mb-4" style="background: #CE1126;">
                                <span class="text-xs font-bold tracking-widest text-white">STUDENT</span>
                            </div>
                            
                            <!-- Photo circle with red ring -->
                            <div class="w-24 h-24 rounded-full flex items-center justify-center" style="border: 4px solid #CE1126; background: rgba(206, 17, 38, 0.2);">
                                <svg class="w-12 h-12" style="color: #CE1126;" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            
                            <!-- Student Name -->
                            <h4 class="mt-4 text-xl font-bold text-center" style="color: #FCD116;"><?php echo e(strtoupper($student['first_name'] . ' ' . $student['last_name'])); ?></h4>
                            
                            <!-- QR Code Box -->
                            <div class="mt-5 rounded-2xl p-5 flex flex-col items-center shadow-lg" style="background: #ffffff; border: 3px solid #e5e5e5;">
                                <div id="qrcode-front" style="width: 150px; height: 150px; background: #ffffff; padding: 5px;"></div>
                                <p class="mt-3 text-sm font-mono font-bold tracking-wider" style="color: #333333;"><?php echo e($student['lrn'] ?? $student['student_id']); ?></p>
                            </div>
                            
                            <!-- Bottom spacer and ID info -->
                            <div class="mt-auto text-center pt-2">
                                <p class="text-xs" style="color: rgba(255,255,255,0.7);">SCAN QR CODE FOR ATTENDANCE</p>
                                <p class="text-sm font-bold mt-1" style="color: #FCD116;">LRN: <?php echo e($student['lrn'] ?? $student['student_id']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Back of ID Card (Portrait) -->
                    <div id="idCardBack" class="id-card-back relative" style="width: 300px; height: 480px; background: linear-gradient(135deg, #CE1126 0%, #1a1a1a 50%, #0038A8 100%); border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: hidden;">
                        <!-- Content -->
                        <div class="relative h-full flex flex-col p-6 text-white">
                            <!-- Emergency Header -->
                            <div class="text-center mb-6">
                                <h4 class="text-lg font-bold" style="color: #FCD116;">IN CASE OF EMERGENCY</h4>
                                <p class="text-sm mt-1" style="color: rgba(255,255,255,0.8);">Kindly notify</p>
                            </div>
                            
                            <!-- Parent/Guardian Info Box - No Labels -->
                            <div class="w-full rounded-xl p-5 flex-1" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                                <div class="mb-4 text-center">
                                    <span class="text-lg font-bold text-white"><?php echo e($student['parent_name'] ?: '—'); ?></span>
                                </div>
                                <div class="mb-4 text-center">
                                    <span class="text-xl font-bold" style="color: #FCD116;"><?php echo e($student['parent_phone'] ?: '—'); ?></span>
                                </div>
                                <div class="mb-4 text-center">
                                    <span class="text-sm text-white break-all"><?php echo e($student['parent_email'] ?: '—'); ?></span>
                                </div>
                                <div class="text-center">
                                    <span class="text-sm text-white leading-tight"><?php echo e($student['address'] ?: '—'); ?></span>
                                </div>
                            </div>
                            
                            <!-- Barcode at bottom -->
                            <div class="mt-4">
                                <div class="bg-white rounded-lg p-3 flex flex-col items-center">
                                    <?php if ($student['barcode_path']): ?>
                                    <img src="<?php echo config('app_url'); ?>/<?php echo e($student['barcode_path']); ?>" 
                                        alt="Barcode" class="h-12 w-auto">
                                    <?php else: ?>
                                    <div class="h-12 w-40 bg-gray-100 flex items-center justify-center text-xs text-gray-400">No barcode</div>
                                    <?php endif; ?>
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
    
    // Generate QR code for front of ID card
    const qrContainer = document.getElementById('qrcode-front');
    qrContainer.innerHTML = ''; // Clear previous
    
    new QRCode(qrContainer, {
        text: '<?php echo e($student['lrn'] ?? $student['student_id']); ?>',
        width: 150,
        height: 150,
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
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
