<?php
/**
 * Barcode Scanning Page
 * Interface for scanning student barcodes and recording attendance
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../includes/notifications.php';

// Require authentication
requireAuth();
requireAnyRole(['admin', 'operator']);

$pageTitle = 'Scan Attendance';
$scanResult = null;

// Handle barcode scan submission
if (isPost()) {
    verifyCsrf();
    
    $barcode = sanitizeString($_POST['barcode'] ?? '');
    
    if (empty($barcode)) {
        $scanResult = [
            'success' => false,
            'error' => [
                'code' => 'EMPTY_BARCODE',
                'message' => 'Please scan or enter a barcode.'
            ]
        ];
    } else {
        $scanResult = processBarcodeScan($barcode);
    }
}

// Get today's statistics
$todayStats = getTodayAttendanceStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - Attendance System</title>
    <link href="/assets/css/app.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-violet-600 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold">Attendance System</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="/pages/dashboard.php" class="hover:text-violet-200">Dashboard</a>
                        <a href="/pages/students.php" class="hover:text-violet-200">Students</a>
                        <a href="/pages/scan.php" class="hover:text-violet-200 font-semibold">Scan</a>
                        <a href="/pages/attendance-history.php" class="hover:text-violet-200">History</a>
                        <a href="/pages/logout.php" class="hover:text-violet-200">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Today's Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600">Total Students</div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo e($todayStats['total_students']); ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600">Present</div>
                    <div class="text-3xl font-bold text-green-600"><?php echo e($todayStats['present']); ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600">Late</div>
                    <div class="text-3xl font-bold text-orange-500"><?php echo e($todayStats['late']); ?></div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600">Attendance Rate</div>
                    <div class="text-3xl font-bold text-violet-600"><?php echo e($todayStats['percentage']); ?>%</div>
                </div>
            </div>

            <!-- Scan Result Message -->
            <?php if ($scanResult): ?>
                <?php if ($scanResult['success']): ?>
                    <div class="bg-violet-100 border-l-4 border-violet-600 p-6 mb-6 rounded-lg shadow-lg" role="alert">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-violet-600 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-xl font-bold text-violet-900"><?php echo e($scanResult['message']); ?></p>
                                <p class="text-sm text-violet-700 mt-1">
                                    Student ID: <?php echo e($scanResult['student']['student_id']); ?> | 
                                    Class: <?php echo e($scanResult['student']['class'] ?? 'N/A'); ?> <?php echo e($scanResult['student']['section'] ?? ''); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-orange-100 border-l-4 border-orange-500 p-6 mb-6 rounded-lg shadow-lg" role="alert">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-orange-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-xl font-bold text-orange-900"><?php echo e($scanResult['error']['message']); ?></p>
                                <p class="text-sm text-orange-700 mt-1">Error Code: <?php echo e($scanResult['error']['code']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Scanning Interface -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Hardware Scanner Input -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Hardware Scanner</h2>
                    <p class="text-gray-600 mb-6">Scan student barcode using your barcode scanner device.</p>
                    
                    <form method="POST" action="" id="scanForm">
                        <?php echo csrfField(); ?>
                        
                        <div class="mb-6">
                            <label for="barcode" class="block text-sm font-medium text-gray-700 mb-2">
                                Barcode Input
                            </label>
                            <input 
                                type="text" 
                                id="barcode" 
                                name="barcode" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent text-lg"
                                placeholder="Scan barcode here..."
                                autofocus
                                autocomplete="off"
                            >
                            <p class="mt-2 text-sm text-gray-500">
                                Focus this field and scan the barcode. It will submit automatically.
                            </p>
                        </div>
                        
                        <button 
                            type="submit" 
                            class="w-full bg-violet-600 text-white px-6 py-3 rounded-lg hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 font-semibold text-lg"
                        >
                            Submit Manually
                        </button>
                    </form>
                </div>

                <!-- Camera Scanner -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Camera Scanner</h2>
                    <p class="text-gray-600 mb-6">Use your device camera to scan barcodes.</p>
                    
                    <div id="camera-scanner" x-data="cameraScanner()">
                        <div x-show="!scanning" class="text-center">
                            <button 
                                @click="startScanning()"
                                class="w-full bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 font-semibold text-lg"
                            >
                                Start Camera
                            </button>
                        </div>
                        
                        <div x-show="scanning">
                            <div id="camera-preview" class="relative bg-black rounded-lg overflow-hidden mb-4" style="height: 300px;">
                                <!-- Camera feed will be inserted here by QuaggaJS -->
                            </div>
                            
                            <button 
                                @click="stopScanning()"
                                class="w-full bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-semibold"
                            >
                                Stop Camera
                            </button>
                            
                            <p class="mt-4 text-sm text-gray-600 text-center">
                                Position the barcode within the camera view
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Scans Today -->
            <div class="mt-8 bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Today's Attendance</h2>
                
                <?php
                $todayAttendance = getAttendanceByDate(date('Y-m-d'));
                ?>
                
                <?php if (empty($todayAttendance)): ?>
                    <p class="text-gray-600">No attendance records for today yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($todayAttendance as $record): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e(formatDateTime($record['check_in_time'], 'h:i A')); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include scanner JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script src="/assets/js/scanner.js"></script>
</body>
</html>
