<?php
/**
 * Student Placement Page
 * Step-by-step process for moving students between school years
 * Admin role only
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/placement.php';

requireRole('admin');

// Handle file download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = __DIR__ . '/../storage/exports/' . $filename;
    if (file_exists($filepath) && strpos($filename, 'placement_preview_') === 0) {
        downloadPlacementPreview($filepath, $filename);
        exit;
    }
    setFlash('error', 'Export file not found.');
}

$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();
$currentUserId = getCurrentUser()['id'] ?? 0;

// Get parameters
$sourceSchoolYearId = (int)($_GET['source_year'] ?? 0);
$targetSchoolYearId = (int)($_GET['target_year'] ?? 0);
$filterGrade = sanitizeString($_GET['grade'] ?? '');
$filterSection = sanitizeString($_GET['section'] ?? '');

// Set defaults
if (!$sourceSchoolYearId && count($schoolYears) > 1) {
    $sourceSchoolYearId = $schoolYears[1]['id'] ?? 0;
}
if (!$targetSchoolYearId && $activeSchoolYear) {
    $targetSchoolYearId = $activeSchoolYear['id'];
}

initPlacementSession($sourceSchoolYearId ?: null, $targetSchoolYearId ?: null);

// Handle POST actions
if (isPost()) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_assign') {
        $studentIds = $_POST['students'] ?? [];
        $targetClassId = (int)($_POST['target_class'] ?? 0);
        if (!empty($studentIds) && $targetClassId > 0) {
            $result = bulkAssignStudents($studentIds, $targetClassId, $currentUserId);
            if ($result['assigned_count'] > 0) {
                setFlash('success', "Assigned {$result['assigned_count']} student(s) to class.");
            }
            if ($result['skipped_count'] > 0) {
                setFlash('warning', "Skipped {$result['skipped_count']} student(s).");
            }
        } else {
            setFlash('error', 'Please select students and a target class.');
        }
        redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId&grade=" . urlencode($filterGrade) . "&section=" . urlencode($filterSection));
    }
    
    if ($action === 'remove_placement') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        if ($studentId > 0) {
            removePendingPlacement($studentId, $classId);
            setFlash('success', 'Placement removed.');
        }
        redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId&grade=" . urlencode($filterGrade) . "&section=" . urlencode($filterSection));
    }
    
    if ($action === 'undo') {
        $result = undoLastAction();
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId&grade=" . urlencode($filterGrade) . "&section=" . urlencode($filterSection));
    }
    
    if ($action === 'save_placements') {
        $result = savePlacements([], $currentUserId);
        if ($result['success']) {
            setFlash('success', "Saved {$result['created_count']} enrollment(s) successfully!");
        } else {
            setFlash('error', $result['error'] ?? 'Save failed.');
        }
        redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId");
    }
    
    if ($action === 'export') {
        $result = exportPlacementPreview($sourceSchoolYearId, $targetSchoolYearId);
        if ($result['success']) {
            redirect("student-placement.php?download=" . urlencode($result['filename']));
        } else {
            setFlash('error', $result['error'] ?? 'Export failed.');
            redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId");
        }
    }
    
    if ($action === 'lock') {
        $result = lockEnrollment($targetSchoolYearId, $currentUserId);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("student-placement.php?source_year=$sourceSchoolYearId&target_year=$targetSchoolYearId");
    }
}

// Validation
$sameYearError = ($sourceSchoolYearId > 0 && $targetSchoolYearId > 0 && $sourceSchoolYearId === $targetSchoolYearId);
$targetLocked = ($targetSchoolYearId > 0) ? isEnrollmentLocked($targetSchoolYearId) : false;

// Get data
$eligibleStudents = [];
$allStudents = [];
$filterOptions = ['grade_levels' => [], 'sections' => []];
$targetClasses = [];
$placementStats = ['total_eligible' => 0, 'placed' => 0, 'pending' => 0, 'conflicts' => 0, 'unassigned' => 0, 'progress_percentage' => 0];

if ($sourceSchoolYearId > 0 && $targetSchoolYearId > 0 && !$sameYearError) {
    $allStudents = getEligibleStudentsWithSuggestions($sourceSchoolYearId, $targetSchoolYearId);
    $filterOptions = getFilterOptions($allStudents);
    
    // Get sections for selected grade
    $sectionsForGrade = [];
    if ($filterGrade) {
        foreach ($allStudents as $s) {
            if ($s['source_grade_level'] === $filterGrade && !in_array($s['source_section'], $sectionsForGrade)) {
                $sectionsForGrade[] = $s['source_section'];
            }
        }
        sort($sectionsForGrade);
    }
    
    $eligibleStudents = filterStudents($allStudents, $filterGrade ?: null, $filterSection ?: null);
    $targetClasses = getAvailableTargetClasses($targetSchoolYearId);
    $placementStats = getPlacementStats($sourceSchoolYearId, $targetSchoolYearId);
}

$pendingPlacements = getPendingPlacements();
$undoStackSize = getUndoStackSize();
$pageTitle = 'Student Placement';
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
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Student Placement</h1>
                    <p class="text-gray-500 mt-1">Promote students from one school year to the next</p>
                </div>
            </div>
        </div>
        
        <?php echo displayFlash(); ?>

        <?php if ($targetLocked): ?>
        <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-r-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div>
                    <p class="font-medium">Enrollment Locked</p>
                    <p class="text-sm mt-1">The target school year is locked. No changes can be made.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 1: Select School Years -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-8 w-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-violet-600 font-semibold text-sm">1</span>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Select School Years</h2>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From (Source Year)</label>
                    <select name="source_year" onchange="this.form.submit()" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <option value="">-- Select Source Year --</option>
                        <?php foreach ($schoolYears as $sy): ?>
                        <option value="<?php echo $sy['id']; ?>" <?php echo $sourceSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                            <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Current)' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To (Target Year)</label>
                    <select name="target_year" onchange="this.form.submit()" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <option value="">-- Select Target Year --</option>
                        <?php foreach ($schoolYears as $sy): ?>
                        <option value="<?php echo $sy['id']; ?>" <?php echo $targetSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                            <?php echo e($sy['name']); ?><?php echo ((int)($sy['is_locked'] ?? 0) === 1) ? ' 🔒' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filterGrade): ?><input type="hidden" name="grade" value="<?php echo e($filterGrade); ?>"><?php endif; ?>
                <?php if ($filterSection): ?><input type="hidden" name="section" value="<?php echo e($filterSection); ?>"><?php endif; ?>
            </form>
            
            <?php if ($sameYearError): ?>
            <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-700 text-sm">Source and target years cannot be the same.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($sourceSchoolYearId > 0 && $targetSchoolYearId > 0 && !$sameYearError && !$targetLocked): ?>
        
        <!-- Step 2: Assign Students -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3">
                        <span class="text-violet-600 font-semibold text-sm">2</span>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Assign Students</h2>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Grade</label>
                        <select id="gradeFilter" onchange="updateFilters()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">All Grades</option>
                            <?php foreach ($filterOptions['grade_levels'] as $g): ?>
                            <option value="<?php echo e($g); ?>" <?php echo $filterGrade === $g ? 'selected' : ''; ?>><?php echo e($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Section</label>
                        <select id="sectionFilter" onchange="updateFilters()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" <?php echo empty($filterGrade) ? 'disabled' : ''; ?>>
                            <option value="">All Sections</option>
                            <?php if (!empty($sectionsForGrade)): ?>
                            <?php foreach ($sectionsForGrade as $sec): ?>
                            <option value="<?php echo e($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo e($sec); ?></option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2 flex items-end">
                        <a href="?source_year=<?php echo $sourceSchoolYearId; ?>&target_year=<?php echo $targetSchoolYearId; ?>" 
                           class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            Clear Filters
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $placementStats['total_eligible']; ?></div>
                    <div class="text-sm text-gray-500">Total</div>
                </div>
                <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                    <div class="text-2xl font-bold text-green-600"><?php echo $placementStats['placed']; ?></div>
                    <div class="text-sm text-green-700">Saved</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $placementStats['pending']; ?></div>
                    <div class="text-sm text-blue-700">Pending</div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-gray-500"><?php echo $placementStats['unassigned']; ?></div>
                    <div class="text-sm text-gray-500">Remaining</div>
                </div>
            </div>

            <?php if (empty($targetClasses)): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-amber-700">No classes found in target year. <a href="classes.php" class="underline font-medium hover:text-amber-800">Create classes first</a>.</p>
            </div>
            <?php elseif (empty($eligibleStudents)): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-green-700 font-medium">All students have been assigned!</p>
                </div>
            </div>
            <?php else: ?>
            
            <form method="POST" id="assignForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="bulk_assign">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Student List -->
                    <div class="lg:col-span-2 border border-gray-200 rounded-xl overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700"><?php echo count($eligibleStudents); ?> student<?php echo count($eligibleStudents) != 1 ? 's' : ''; ?></span>
                            <div class="flex gap-2">
                                <button type="button" onclick="selectAllStudents()" class="text-xs text-violet-600 hover:text-violet-700 font-medium">Select All</button>
                                <span class="text-gray-300">|</span>
                                <button type="button" onclick="clearAllStudents()" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Clear</button>
                            </div>
                        </div>
                        <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                            <?php foreach ($eligibleStudents as $s): 
                                $pending = $pendingPlacements[$s['id']] ?? null;
                                $pendingClass = '';
                                if ($pending) {
                                    foreach ($targetClasses as $tc) {
                                        if ($tc['id'] == $pending) { $pendingClass = $tc['grade_level'].' - '.$tc['section']; break; }
                                    }
                                }
                            ?>
                            <label class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $pending ? 'bg-blue-50/50 cursor-default' : 'cursor-pointer'; ?>" 
                                   <?php echo !$pending ? 'onclick="event.target.tagName !== \'BUTTON\' && event.target.tagName !== \'INPUT\' || updateCount()"' : ''; ?>>
                                <input type="checkbox" name="students[]" value="<?php echo $s['id']; ?>" <?php echo $pending ? 'disabled' : ''; ?> 
                                       class="student-cb rounded border-gray-300 text-violet-600 focus:ring-violet-500 mr-3 <?php echo $pending ? '' : 'cursor-pointer'; ?>">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900"><?php echo e($s['last_name'].', '.$s['first_name']); ?></div>
                                    <div class="text-xs text-gray-500">From: <?php echo e($s['source_grade_level'].' - '.$s['source_section']); ?></div>
                                </div>
                                <?php if ($pending): ?>
                                <div class="flex items-center" onclick="event.stopPropagation()">
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-lg mr-2">→ <?php echo e($pendingClass); ?></span>
                                    <form method="POST" class="inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="remove_placement">
                                        <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="class_id" value="<?php echo $pending; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1" title="Remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-gray-400">→ <?php echo e($s['suggested_grade']); ?></span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Assignment Panel -->
                    <div class="bg-violet-50 border border-violet-200 rounded-xl p-5">
                        <h3 class="font-semibold text-violet-900 mb-4">Assign Selected</h3>
                        <div class="mb-4">
                            <div class="text-3xl font-bold text-violet-700" id="selectedCount">0</div>
                            <div class="text-sm text-violet-600">students selected</div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-violet-800 mb-1">Target Class</label>
                            <select name="target_class" required class="w-full px-4 py-2.5 border border-violet-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($targetClasses as $c): 
                                    $cap = checkClassCapacity($c['id']);
                                    $isFull = $cap['current_enrollment'] >= $cap['max_capacity'];
                                ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $isFull ? 'disabled' : ''; ?>>
                                    <?php echo e($c['grade_level'].' - '.$c['section']); ?> (<?php echo $cap['current_enrollment']; ?>/<?php echo $cap['max_capacity']; ?>)<?php echo $isFull ? ' - Full' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                            <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Assign to Class
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($placementStats['pending'] > 0 || $placementStats['placed'] > 0): ?>
        <!-- Step 3: Review & Save -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-8 w-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-violet-600 font-semibold text-sm">3</span>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Review & Save</h2>
            </div>
            
            <!-- Progress -->
            <div class="mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600">Progress</span>
                    <span class="font-medium text-gray-900"><?php echo $placementStats['progress_percentage']; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-violet-600 h-2.5 rounded-full transition-all duration-300" style="width: <?php echo min($placementStats['progress_percentage'], 100); ?>%"></div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex flex-wrap gap-3">
                <?php if ($undoStackSize > 0): ?>
                <form method="POST" class="inline">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="undo">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Undo (<?php echo $undoStackSize; ?>)
                    </button>
                </form>
                <?php endif; ?>
                
                <form method="POST" class="inline">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="export">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </button>
                </form>
                
                <?php if ($placementStats['pending'] > 0 && !$targetLocked): ?>
                <form method="POST" class="inline" onsubmit="return confirm('Save all pending placements to database?')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="save_placements">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save All (<?php echo $placementStats['pending']; ?>)
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if ($placementStats['pending'] === 0 && $placementStats['placed'] > 0 && !$targetLocked): ?>
                <form method="POST" class="inline" onsubmit="return confirm('Lock enrollment? This prevents further changes.')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-amber-500 text-white text-sm font-medium rounded-xl hover:bg-amber-600 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Lock Enrollment
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
// Section data for dynamic filtering
const sectionsByGrade = <?php echo json_encode(
    array_reduce($allStudents, function($carry, $s) {
        $grade = $s['source_grade_level'];
        $section = $s['source_section'];
        if (!isset($carry[$grade])) $carry[$grade] = [];
        if (!in_array($section, $carry[$grade])) $carry[$grade][] = $section;
        return $carry;
    }, [])
); ?>;

function updateFilters() {
    const grade = document.getElementById('gradeFilter').value;
    const sectionSelect = document.getElementById('sectionFilter');
    
    // Update section dropdown based on grade
    sectionSelect.innerHTML = '<option value="">All Sections</option>';
    
    if (grade && sectionsByGrade[grade]) {
        sectionsByGrade[grade].sort().forEach(section => {
            const opt = document.createElement('option');
            opt.value = section;
            opt.textContent = section;
            sectionSelect.appendChild(opt);
        });
        sectionSelect.disabled = false;
    } else {
        sectionSelect.disabled = true;
    }
    
    // Build URL and redirect
    const section = sectionSelect.value;
    let url = '?source_year=<?php echo $sourceSchoolYearId; ?>&target_year=<?php echo $targetSchoolYearId; ?>';
    if (grade) url += '&grade=' + encodeURIComponent(grade);
    if (section) url += '&section=' + encodeURIComponent(section);
    
    window.location.href = url;
}

function selectAllStudents() {
    document.querySelectorAll('.student-cb:not(:disabled)').forEach(cb => cb.checked = true);
    updateCount();
}

function clearAllStudents() {
    document.querySelectorAll('.student-cb').forEach(cb => cb.checked = false);
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.student-cb:checked').length;
    const countEl = document.getElementById('selectedCount');
    if (countEl) countEl.textContent = count;
}

// Update count on checkbox change
document.querySelectorAll('.student-cb').forEach(cb => {
    cb.addEventListener('change', updateCount);
});

// Initialize count on page load
document.addEventListener('DOMContentLoaded', updateCount);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
