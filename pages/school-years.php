<?php
/**
 * School Years Management Page
 * Admin role required
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/placement.php';

requireRole('admin');

$errors = [];
$currentUser = getCurrentUser();

// Handle form submissions
if (isPost()) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitizeString($_POST['name'] ?? '');
        $startDate = sanitizeString($_POST['start_date'] ?? '');
        $endDate = sanitizeString($_POST['end_date'] ?? '');
        
        if (empty($name)) {
            $errors[] = 'School year name is required.';
        } elseif (!validateSchoolYearFormat($name)) {
            $errors[] = 'Format must be YYYY-YYYY (e.g., 2024-2025).';
        } else {
            $result = createSchoolYear($name, $startDate ?: null, $endDate ?: null);
            if ($result) {
                setFlash('success', "School year \"$name\" created.");
                redirect('school-years.php');
            } else {
                $errors[] = 'Failed to create. May already exist.';
            }
        }
    }
    
    if ($action === 'set_active') {
        $id = (int)($_POST['school_year_id'] ?? 0);
        if ($id > 0 && setActiveSchoolYear($id)) {
            setFlash('success', 'School year activated.');
        } else {
            setFlash('error', 'Failed to activate.');
        }
        redirect('school-years.php');
    }
    
    if ($action === 'lock') {
        $id = (int)($_POST['school_year_id'] ?? 0);
        $result = lockEnrollment($id, $currentUser['id'] ?? 0);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('school-years.php');
    }
    
    if ($action === 'unlock') {
        $id = (int)($_POST['school_year_id'] ?? 0);
        $result = unlockEnrollment($id, $currentUser['id'] ?? 0);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('school-years.php');
    }
}

$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();
$pageTitle = 'School Years';
$csrfToken = generateCsrfToken();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">School Years</h1>
                    <p class="text-gray-500 mt-1">Manage academic years and enrollment periods</p>
                </div>
                <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New School Year
                </button>
            </div>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <div>
                    <?php foreach ($errors as $e): ?>
                    <p class="font-medium"><?php echo e($e); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- School Years Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrollment</th>
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
                                <p class="mt-2 text-sm text-gray-500">No school years found.</p>
                                <button onclick="openCreateModal()" class="mt-3 text-violet-600 hover:text-violet-700 text-sm font-medium">
                                    Create your first school year →
                                </button>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($schoolYears as $sy): 
                            $isLocked = (int)($sy['is_locked'] ?? 0) === 1;
                            $isActive = (bool)$sy['is_active'];
                        ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 <?php echo $isActive ? 'bg-violet-100' : 'bg-gray-100'; ?> rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 <?php echo $isActive ? 'text-violet-600' : 'text-gray-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($sy['name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php if ($sy['start_date'] && $sy['end_date']): ?>
                                    <?php echo formatDate($sy['start_date']); ?> — <?php echo formatDate($sy['end_date']); ?>
                                <?php elseif ($sy['start_date']): ?>
                                    From <?php echo formatDate($sy['start_date']); ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($isActive): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-violet-50 text-violet-700 border-violet-200">
                                    <span class="w-1.5 h-1.5 bg-violet-500 rounded-full mr-1.5 animate-pulse"></span>
                                    Active
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-gray-50 text-gray-600 border-gray-200">
                                    Inactive
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($isLocked): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-red-50 text-red-700 border-red-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Locked
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-green-50 text-green-700 border-green-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                    </svg>
                                    Open
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (!$isActive): ?>
                                    <form method="POST" class="inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="school_year_id" value="<?php echo $sy['id']; ?>">
                                        <button type="submit" onclick="return confirm('Set this as the active school year?')"
                                                class="inline-flex items-center px-3 py-1.5 bg-violet-50 text-violet-700 text-xs font-medium rounded-lg hover:bg-violet-100 transition-colors border border-violet-200">
                                            Set Active
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($isLocked): ?>
                                    <form method="POST" class="inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="school_year_id" value="<?php echo $sy['id']; ?>">
                                        <button type="submit" onclick="return confirm('Unlock enrollment?')"
                                                class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100 transition-colors border border-amber-200">
                                            Unlock
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="lock">
                                        <input type="hidden" name="school_year_id" value="<?php echo $sy['id']; ?>">
                                        <button type="submit" onclick="return confirm('Lock enrollment? No changes will be allowed.')"
                                                class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors border border-red-200">
                                            Lock
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                <div class="text-sm text-gray-600">
                    Total: <?php echo count($schoolYears); ?> school year<?php echo count($schoolYears) != 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Create Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCreateModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full mx-auto">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Create School Year</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">School Year Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" placeholder="2025-2026" pattern="\d{4}-\d{4}" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Format: YYYY-YYYY (e.g., 2025-2026)</p>
                        </div>
                        
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}
function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
