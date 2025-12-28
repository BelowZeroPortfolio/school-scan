<?php
/**
 * Help Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pageTitle = 'Help';
$currentUser = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Help Center</h1>
            <p class="text-sm text-gray-500 mt-1">Get help with using the Attendance System</p>
        </div>
        
        <div class="space-y-6">
            <!-- Getting Started -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Getting Started</h2>
                <div class="space-y-3 text-sm text-gray-600">
                    <p>Welcome to the QR Attendance System. This system helps you track student attendance using QR code scanning.</p>
                    <ul class="list-disc list-inside space-y-2 ml-2">
                        <li>Add students with their LRN to generate unique QR codes</li>
                        <li>Print QR codes for student ID cards</li>
                        <li>Scan QR codes to record attendance</li>
                        <li>View reports and attendance history</li>
                    </ul>
                </div>
            </div>

            <!-- FAQ -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Frequently Asked Questions</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="font-medium text-gray-900">How do I add a new student?</h3>
                        <p class="text-sm text-gray-600 mt-1">Go to Students → Add New Student. Fill in the required information including the 12-digit LRN.</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">How do I scan attendance?</h3>
                        <p class="text-sm text-gray-600 mt-1">Use a QR scanner connected to your computer. The system will automatically record attendance when a valid QR code is scanned.</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">How do I export attendance reports?</h3>
                        <p class="text-sm text-gray-600 mt-1">Go to Reports, select the date range and filters, then click Export to download as CSV, Excel, or PDF.</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Need More Help?</h2>
                <p class="text-sm text-gray-600">Contact your system administrator for additional support.</p>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
