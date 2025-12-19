<?php
/**
 * Move Students Page
 * Bulk move students to new classes for a new school year
 * Admin role required
 * 
 * Requirements: 5.1, 5.2, 5.3
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require admin role
requireRole('admin');

$errors = [];
$moveResults = null;

// Get all school years
$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();

// Get filter parameters
$sourceSchoolYearId = isset($_GET['source_year']) ? (int)$_GET['source_year'] : null;
$targetSchoolYearId = isset($_GET['target_year']) ? (int)$_GET['target_year'] : null;

// Default source to active school year if not set
if (!$sourceSchoolYearId && $activeSchoolYear) {
    $sourceSchoolYearId = $activeSchoolYear['id'];
}

// Handle form submission for bulk move
if (isPost()) {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'move') {
        $targetClassId = (int)($_POST['target_class_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        
        if (empty($studentIds)) {
            $errors[] = 'Please select at least one student to move.';
        } elseif ($targetClassId <= 0) {
            $errors[] = 'Please select a target class.';
        } else {
            // Build assignments array
            $assignments = [];
            foreach ($studentIds as $studentId) {
                $assignments[(int)$studentId] = $targetClassId;
            }
            
            // Get current user for enrolled_by
            $currentUser = getCurrentUser();
            
            // Perform bulk move
            $moveResults = moveStudentsToClasses($assignments, $currentUser['id']);
            
            if ($moveResults['success_count'] > 0) {
                setFlash('success', $moveResults['success_count'] . ' student(s) moved successfully.');
            }
            
            if ($moveResults['failed_count'] > 0 && $moveResults['success_count'] === 0) {
                $errors[] = 'Failed to move students. ' . ($moveResults['failed'][0]['reason'] ?? 'Unknown error.');
            }
        }
    }
}

// Get classes for source school year (to display students grouped by class)
$sourceClasses = $sourceSchoolYearId ? getClassesBySchoolYear($sourceSchoolYearId) : [];

// Get classes for target school year (for dropdown)
$targetClasses = $targetSchoolYearId ? getClassesBySchoolYear($targetSchoolYearId) : [];

// Build students grouped by class for source year
$studentsGroupedByClass = [];
foreach ($sourceClasses as $class) {
    $students = getClassStudents($class['id']);
    if (!empty($students)) {
        $studentsGroupedByClass[$class['id']] = [
            'class' => $class,
            'students' => $students
        ];
    }
}

// Get students not enrolled in any class for source year
$unenrolledStudents = [];
if ($sourceSchoolYearId) {
    $sql = "SELECT s.id, s.student_id AS student_code, s.lrn, s.first_name, s.last_name
            FROM students s
            WHERE s.is_active = 1
            AND s.id NOT IN (
                SELECT sc.student_id 
                FROM student_classes sc
                JOIN classes c ON sc.class_id = c.id
                WHERE c.school_year_id = ? AND sc.is_active = 1
            )
            ORDER BY s.last_name, s.first_name";
    $unenrolledStudents = dbFetchAll($sql, [$sourceSchoolYearId]);
}

$pageTitle = 'Move Students';
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
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Move Students</h1>
                    <p class="text-gray-500 mt-1">Bulk move students to new classes for a new school year</p>
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

        <!-- Move Results -->
        <?php if ($moveResults && !empty($moveResults['failed'])): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium mb-2">Some students could not be moved:</p>
                        <ul class="list-disc list-inside text-sm">
                            <?php foreach ($moveResults['failed'] as $failed): ?>
                                <li>
                                    <?php if (isset($failed['student_name'])): ?>
                                        <?php echo e($failed['student_name']); ?>: 
                                    <?php endif; ?>
                                    <?php echo e($failed['reason']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- No School Years Warning -->
        <?php if (empty($schoolYears)): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium">No school years found.</p>
                        <p class="text-sm mt-1">Please <a href="<?php echo config('app_url'); ?>/pages/school-years.php" class="underline hover:text-amber-800">create school years</a> first.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>

        <!-- School Year Selection -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Select School Years</h2>
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="source_year" class="block text-sm font-medium text-gray-700 mb-1">
                        Source School Year (Current)
                    </label>
                    <select 
                        id="source_year" 
                        name="source_year" 
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    >
                        <option value="">Select source year...</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['id']; ?>" <?php echo $sourceSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                                <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Active)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="target_year" class="block text-sm font-medium text-gray-700 mb-1">
                        Target School Year (New)
                    </label>
                    <select 
                        id="target_year" 
                        name="target_year" 
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    >
                        <option value="">Select target year...</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <?php if ($sy['id'] != $sourceSchoolYearId): ?>
                            <option value="<?php echo $sy['id']; ?>" <?php echo $targetSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                                <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Active)' : ''; ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button 
                        type="submit" 
                        class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm"
                    >
                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if ($sourceSchoolYearId && $targetSchoolYearId): ?>
            <?php if (empty($targetClasses)): ?>
                <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-r-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="font-medium">No classes found for the target school year.</p>
                            <p class="text-sm mt-1">Please <a href="<?php echo config('app_url'); ?>/pages/classes.php" class="underline hover:text-amber-800">create classes</a> for the target school year first.</p>
                        </div>
                    </div>
                </div>
            <?php elseif (empty($studentsGroupedByClass) && empty($unenrolledStudents)): ?>
                <div class="bg-gray-50 border border-gray-200 text-gray-600 p-8 rounded-xl text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <p class="text-lg font-medium">No students found for the source school year.</p>
                    <p class="text-sm mt-1">There are no students enrolled in classes for this school year.</p>
                </div>
            <?php else: ?>

        <!-- Move Form -->
        <form method="POST" action="?source_year=<?php echo $sourceSchoolYearId; ?>&target_year=<?php echo $targetSchoolYearId; ?>" 
              x-data="{ 
                  selectedStudents: [], 
                  targetClass: '',
                  selectAll(classId) {
                      const checkboxes = document.querySelectorAll('.student-checkbox-' + classId);
                      const allChecked = Array.from(checkboxes).every(cb => this.selectedStudents.includes(cb.value));
                      checkboxes.forEach(cb => {
                          if (allChecked) {
                              this.selectedStudents = this.selectedStudents.filter(id => id !== cb.value);
                          } else if (!this.selectedStudents.includes(cb.value)) {
                              this.selectedStudents.push(cb.value);
                          }
                      });
                  },
                  isClassSelected(classId) {
                      const checkboxes = document.querySelectorAll('.student-checkbox-' + classId);
                      return checkboxes.length > 0 && Array.from(checkboxes).every(cb => this.selectedStudents.includes(cb.value));
                  }
              }">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="move">
            
            <!-- Target Class Selection (Sticky) -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6 sticky top-20 z-10 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1">
                        <label for="target_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Move Selected Students To:
                        </label>
                        <select 
                            id="target_class_id" 
                            name="target_class_id" 
                            x-model="targetClass"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            required
                        >
                            <option value="">Select target class...</option>
                            <?php foreach ($targetClasses as $tc): ?>
                                <option value="<?php echo $tc['id']; ?>">
                                    <?php echo e($tc['grade_level'] . ' - ' . $tc['section']); ?> 
                                    (<?php echo e($tc['teacher_name']); ?>) 
                                    [<?php echo (int)$tc['student_count']; ?> students]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-sm text-gray-600" x-text="selectedStudents.length + ' student(s) selected'"></span>
                        <button 
                            type="submit" 
                            class="px-6 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="selectedStudents.length === 0 || !targetClass"
                        >
                            <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                            Move Students
                        </button>
                    </div>
                </div>
            </div>

            <!-- Students Grouped by Class -->
            <div class="space-y-6">
                <?php foreach ($studentsGroupedByClass as $classId => $data): ?>
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 bg-violet-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            <?php echo e($data['class']['grade_level'] . ' - ' . $data['class']['section']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            Teacher: <?php echo e($data['class']['teacher_name']); ?> • 
                                            <?php echo count($data['students']); ?> student(s)
                                        </p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="selectAll(<?php echo $classId; ?>)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                                    :class="isClassSelected(<?php echo $classId; ?>) ? 'bg-violet-100 text-violet-700 border border-violet-200' : 'bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200'"
                                >
                                    <span x-text="isClassSelected(<?php echo $classId; ?>) ? 'Deselect All' : 'Select All'"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50/30">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                            <span class="sr-only">Select</span>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php foreach ($data['students'] as $student): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input 
                                                    type="checkbox" 
                                                    name="student_ids[]" 
                                                    value="<?php echo $student['id']; ?>"
                                                    x-model="selectedStudents"
                                                    class="student-checkbox-<?php echo $classId; ?> h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded"
                                                >
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo e($student['last_name'] . ', ' . $student['first_name']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">
                                                    <?php echo $student['lrn'] ? e($student['lrn']) : '<span class="text-gray-400">Not set</span>'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600"><?php echo e($student['student_code']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Unenrolled Students -->
                <?php if (!empty($unenrolledStudents)): ?>
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-amber-50/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Not Enrolled</h3>
                                        <p class="text-sm text-gray-500">
                                            Students not enrolled in any class for this school year • 
                                            <?php echo count($unenrolledStudents); ?> student(s)
                                        </p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="selectAll(0)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                                    :class="isClassSelected(0) ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200'"
                                >
                                    <span x-text="isClassSelected(0) ? 'Deselect All' : 'Select All'"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50/30">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                            <span class="sr-only">Select</span>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php foreach ($unenrolledStudents as $student): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input 
                                                    type="checkbox" 
                                                    name="student_ids[]" 
                                                    value="<?php echo $student['id']; ?>"
                                                    x-model="selectedStudents"
                                                    class="student-checkbox-0 h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded"
                                                >
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo e($student['last_name'] . ', ' . $student['first_name']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">
                                                    <?php echo $student['lrn'] ? e($student['lrn']) : '<span class="text-gray-400">Not set</span>'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600"><?php echo e($student['student_code']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>
            <?php endif; ?>
        <?php elseif ($sourceSchoolYearId && !$targetSchoolYearId): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="font-medium">Please select a target school year to move students to.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
