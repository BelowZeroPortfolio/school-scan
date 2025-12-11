<?php
/**
 * Terms of Service Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pageTitle = 'Terms of Service';
$currentUser = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Terms of Service</h1>
            <p class="text-sm text-gray-500 mt-1">Last updated: <?php echo date('F Y'); ?></p>
        </div>
        
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <div class="prose prose-sm max-w-none text-gray-600 space-y-6">
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Acceptance of Terms</h2>
                    <p>By accessing and using this Attendance System, you agree to comply with these terms of service.</p>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Authorized Use</h2>
                    <p>This system is intended for authorized school personnel only. Users must:</p>
                    <ul class="list-disc list-inside ml-2 space-y-1">
                        <li>Use the system only for legitimate attendance tracking purposes</li>
                        <li>Keep login credentials secure and confidential</li>
                        <li>Report any security concerns to administrators</li>
                    </ul>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">User Responsibilities</h2>
                    <ul class="list-disc list-inside ml-2 space-y-1">
                        <li>Ensure accuracy of data entered into the system</li>
                        <li>Protect student privacy and confidentiality</li>
                        <li>Use the system in accordance with school policies</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Prohibited Activities</h2>
                    <ul class="list-disc list-inside ml-2 space-y-1">
                        <li>Unauthorized access or sharing of credentials</li>
                        <li>Tampering with attendance records</li>
                        <li>Misuse of student information</li>
                    </ul>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Disclaimer</h2>
                    <p>The system is provided "as is" for educational institution use. The school is responsible for data backup and system maintenance.</p>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Changes to Terms</h2>
                    <p>These terms may be updated periodically. Continued use of the system constitutes acceptance of any changes.</p>
                </section>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
