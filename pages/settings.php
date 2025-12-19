<?php
/**
 * Settings Page
 * System settings including school year management
 * Admin role required
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';

// Require admin role
requireRole('admin');

$errors = [];
$success = [];
$activeTab = sanitizeString($_GET['tab'] ?? 'school-year');

$formData = [
    'name' => '',
    'start_date' => '',
    'end_date' => ''
];

// Handle form submissions
if (isPost()) {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_school_year') {
        $formData['name'] = sanitizeString($_POST['name'] ?? '');
        $formData['start_date'] = sanitizeString($_POST['start_date'] ?? '');
        $formData['end_date'] = sanitizeString($_POST['end_date'] ?? '');
        
        if (empty($formData['name'])) {
            $errors[] = 'School year name is required.';
        } elseif (!validateSchoolYearFormat($formData['name'])) {
            $errors[] = 'School year must be in format YYYY-YYYY (e.g., 2024-2025).';
        }
        
        if (empty($errors)) {
            $result = createSchoolYear(
                $formData['name'],
                $formData['start_date'] ?: null,
                $formData['end_date'] ?: null
            );
            
            if ($result) {
                setFlash('success', 'School year "' . $formData['name'] . '" created successfully.');
                redirect(config('app_url') . '/pages/settings.php?tab=school-year');
            } else {
                $errors[] = 'Failed to create school year. It may already exist.';
            }
        }
        $activeTab = 'school-year';
    } elseif ($action === 'set_active') {
        $schoolYearId = (int)($_POST['school_year_id'] ?? 0);
        
        if ($schoolYearId <= 0) {
            $errors[] = 'Invalid school year selected.';
        } else {
            $result = setActiveSchoolYear($schoolYearId);
            
            if ($result) {
                $schoolYear = getSchoolYearById($schoolYearId);
                setFlash('success', 'School year "' . ($schoolYear['name'] ?? '') . '" is now active.');
                redirect(config('app_url') . '/pages/settings.php?tab=school-year');
            } else {
                $errors[] = 'Failed to set school year as active.';
            }
        }
        $activeTab = 'school-year';
    }
}

// Get data
$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();

$pageTitle = 'Settings';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Settings</h1>
            <p class="text-gray-500 mt-1">Manage system settings and configurations</p>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p class="font-medium"><?php echo e($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?tab=school-year" 
                       class="px-6 py-4 text-sm font-medium border-b-2 <?php echo $activeTab === 'school-year' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        School Year
                    </a>
                    <a href="?tab=general" 
                       class="px-6 py-4 text-sm font-medium border-b-2 <?php echo $activeTab === 'general' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        General
                    </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <?php if ($activeTab === 'school-year'): ?>
                    <!-- School Year Tab -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Create School Year Form -->
                        <div class="lg:col-span-1">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Create School Year</h3>
                                
                                <form method="POST" action="" class="space-y-4">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="create_school_year">
                                    
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                            School Year Name <span class="text-red-500">*</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            id="name" 
                                            name="name" 
                                            value="<?php echo e($formData['name']); ?>"
                                            placeholder="e.g., 2024-2025"
                                            pattern="\d{4}-\d{4}"
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                            required
                                        >
                                        <p class="mt-1 text-xs text-gray-500">Format: YYYY-YYYY</p>
                                    </div>
                                    
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                            Start Date
                                        </label>
                                        <input 
                                            type="date" 
                                            id="start_date" 
                                            name="start_date" 
                                            value="<?php echo e($formData['start_date']); ?>"
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                        >
                                    </div>
                                    
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                            End Date
                                        </label>
                                        <input 
                                            type="date" 
                                            id="end_date" 
                                            name="end_date" 
                                            value="<?php echo e($formData['end_date']); ?>"
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                        >
                                    </div>
                                    
                                    <button 
                                        type="submit" 
                                        class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm"
                                    >
                                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Create School Year
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Current Active School Year -->
                            <div class="mt-6 bg-violet-50 rounded-xl p-6 border border-violet-100">
                                <h4 class="text-sm font-medium text-violet-900 mb-2">Current Active School Year</h4>
                                <?php if ($activeSchoolYear): ?>
                                    <p class="text-2xl font-bold text-violet-600"><?php echo e($activeSchoolYear['name']); ?></p>
                                    <?php if ($activeSchoolYear['start_date'] && $activeSchoolYear['end_date']): ?>
                                        <p class="text-sm text-violet-700 mt-1">
                                            <?php echo formatDate($activeSchoolYear['start_date']); ?> - <?php echo formatDate($activeSchoolYear['end_date']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-amber-600 font-medium">No active school year set</p>
                                    <p class="text-sm text-gray-500 mt-1">Please set an active school year to enable attendance tracking.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- School Years List -->
                        <div class="lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">All School Years</h3>
                            
                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php if (empty($schoolYears)): ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-12 text-center">
                                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                    <p class="mt-2 text-sm text-gray-500">No school years found. Create your first school year!</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($schoolYears as $sy): ?>
                                                <tr class="hover:bg-gray-50/50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="text-sm font-medium text-gray-900"><?php echo e($sy['name']); ?></span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="text-sm text-gray-600">
                                                            <?php if ($sy['start_date'] && $sy['end_date']): ?>
                                                                <?php echo formatDate($sy['start_date']); ?> - <?php echo formatDate($sy['end_date']); ?>
                                                            <?php else: ?>
                                                                <span class="text-gray-400">Not set</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($sy['is_active']): ?>
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                </svg>
                                                                Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                                                Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                                        <?php if (!$sy['is_active']): ?>
                                                            <form method="POST" action="" class="inline-block">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="set_active">
                                                                <input type="hidden" name="school_year_id" value="<?php echo $sy['id']; ?>">
                                                                <button 
                                                                    type="submit" 
                                                                    class="inline-flex items-center px-3 py-1.5 bg-violet-50 text-violet-700 text-xs font-medium rounded-lg hover:bg-violet-100 transition-colors border border-violet-200"
                                                                    onclick="return confirm('Set <?php echo e($sy['name']); ?> as the active school year?')"
                                                                >
                                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                    Set Active
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-xs text-gray-400">Current</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-500">
                                Total: <?php echo count($schoolYears); ?> school year(s)
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($activeTab === 'general'): ?>
                    <!-- General Settings Tab -->
                    <div class="max-w-2xl">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">General Settings</h3>
                        
                        <div class="bg-gray-50 rounded-xl p-6">
                            <p class="text-gray-500 text-sm">General settings will be available in a future update.</p>
                            
                            <div class="mt-6 space-y-4">
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Application Name</p>
                                        <p class="text-xs text-gray-500">The name displayed in the header</p>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo e(config('app_name')); ?></span>
                                </div>
                                
                                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Application URL</p>
                                        <p class="text-xs text-gray-500">Base URL for the application</p>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo e(config('app_url')); ?></span>
                                </div>
                                
                                <div class="flex items-center justify-between py-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Environment</p>
                                        <p class="text-xs text-gray-500">Current running environment</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo config('environment') === 'production' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>">
                                        <?php echo e(ucfirst(config('environment') ?? 'development')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
