<?php
/**
 * School Years Management Page
 * Display, create, and manage school years
 * Admin role required
 * 
 * Requirements: 1.1, 1.2, 1.3
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
$formData = [
    'name' => '',
    'start_date' => '',
    'end_date' => ''
];

// Handle form submissions
if (isPost()) {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Create new school year
        $formData['name'] = sanitizeString($_POST['name'] ?? '');
        $formData['start_date'] = sanitizeString($_POST['start_date'] ?? '');
        $formData['end_date'] = sanitizeString($_POST['end_date'] ?? '');
        
        // Validate required fields
        if (empty($formData['name'])) {
            $errors[] = 'School year name is required.';
        } elseif (!validateSchoolYearFormat($formData['name'])) {
            $errors[] = 'School year must be in format YYYY-YYYY (e.g., 2024-2025) where the second year is one more than the first.';
        }
        
        if (empty($errors)) {
            $result = createSchoolYear(
                $formData['name'],
                $formData['start_date'] ?: null,
                $formData['end_date'] ?: null
            );
            
            if ($result) {
                setFlash('success', 'School year "' . $formData['name'] . '" created successfully.');
                redirect(config('app_url') . '/pages/school-years.php');
            } else {
                $errors[] = 'Failed to create school year. It may already exist.';
            }
        }
    } elseif ($action === 'set_active') {
        // Set school year as active
        $schoolYearId = (int)($_POST['school_year_id'] ?? 0);
        
        if ($schoolYearId <= 0) {
            $errors[] = 'Invalid school year selected.';
        } else {
            $result = setActiveSchoolYear($schoolYearId);
            
            if ($result) {
                $schoolYear = getSchoolYearById($schoolYearId);
                setFlash('success', 'School year "' . ($schoolYear['name'] ?? '') . '" is now active.');
                redirect(config('app_url') . '/pages/school-years.php');
            } else {
                $errors[] = 'Failed to set school year as active.';
            }
        }
    }
}

// Get all school years
$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();

$pageTitle = 'School Years';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">School Years</h1>
                    <p class="text-gray-500 mt-1">Manage academic school years</p>
                </div>
            </div>
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
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Create School Year Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Create School Year</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
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
                            <p class="mt-1 text-xs text-gray-500">Format: YYYY-YYYY (e.g., 2024-2025)</p>
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
            </div>

            
            <!-- School Years List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-900">All School Years</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($schoolYears)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
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
                                                    <?php elseif ($sy['start_date']): ?>
                                                        From <?php echo formatDate($sy['start_date']); ?>
                                                    <?php elseif ($sy['end_date']): ?>
                                                        Until <?php echo formatDate($sy['end_date']); ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">Not set</span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($sy['is_active']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-green-50 text-green-700 border-green-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-gray-50 text-gray-600 border-gray-200">
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600"><?php echo formatDate($sy['created_at']); ?></span>
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
                    
                    <!-- Summary -->
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Total: <?php echo count($schoolYears); ?> school year(s)</span>
                            <?php if ($activeSchoolYear): ?>
                                <span>Active: <strong class="text-violet-600"><?php echo e($activeSchoolYear['name']); ?></strong></span>
                            <?php else: ?>
                                <span class="text-amber-600">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    No active school year set
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
