<?php
/**
 * Privacy Policy Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pageTitle = 'Privacy Policy';
$currentUser = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Privacy Policy</h1>
            <p class="text-sm text-gray-500 mt-1">Last updated: <?php echo date('F Y'); ?></p>
        </div>
        
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <div class="prose prose-sm max-w-none text-gray-600 space-y-6">
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Information We Collect</h2>
                    <p>We collect student information necessary for attendance tracking:</p>
                    <ul class="list-disc list-inside ml-2 space-y-1">
                        <li>Student name and LRN</li>
                        <li>Grade level and section</li>
                        <li>Parent/Guardian contact information</li>
                        <li>Attendance records</li>
                    </ul>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">How We Use Information</h2>
                    <p>Information is used solely for:</p>
                    <ul class="list-disc list-inside ml-2 space-y-1">
                        <li>Recording and tracking student attendance</li>
                        <li>Generating attendance reports</li>
                        <li>Notifying parents/guardians when applicable</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Data Security</h2>
                    <p>We implement appropriate security measures to protect student data, including secure database storage and access controls.</p>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Data Retention</h2>
                    <p>Attendance records are retained for the duration required by school policies and applicable regulations.</p>
                </section>
                
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Contact</h2>
                    <p>For privacy concerns, contact your school administrator.</p>
                </section>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
