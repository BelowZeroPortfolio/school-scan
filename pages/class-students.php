<?php
/**
 * Class Students Management Page
 * Display students in a class, enroll/remove students
 * Admin and teacher (own classes) access
 * 
 * Requirements: 4.1, 4.4, 6.2
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require authentication
requireAuth();

$errors = [];
$classId = (int)($_GET['id'] ?? 0);

// Validate class ID
if ($classId <= 0) {
    setFlash('error', 'Invalid class selected.');
    redirect(config('app_url') . '/pages/classes.php');
}

// Get class details
$class = getClassById($classId);
if (!$class) {
    setFlash('error', 'Class not found.');
    redirect(config('app_url') . '/pages/classes.php');
}

// Check access permissions
// Admin can access all classes, teachers can only access their own classes
$currentUser = getCurrentUser();
$isAdmin = isAdmin();
$isTeacher = isTeacher();

if (!$isAdmin) {
    if ($isTeacher) {
        // Check if this is the teacher's class
        if ($class['teacher_id'] != $currentUser['id']) {
            setFlash('error', 'You do not have permission to access this class.');
            redirect(config('app_url') . '/pages/dashboard.php');
        }
    } else {
        // Other roles cannot access this page
        setFlash('error', 'You do not have permission to access this page.');
        redirect(config('app_url') . '/pages/dashboard.php');
    }
}

// Handle form submissions
if (isPost()) {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enroll') {
        // Enroll student in class
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        if ($studentId <= 0) {
            $errors[] = 'Please select a student to enroll.';
        } else {
            $result = assignStudentToClass($studentId, $classId, $currentUser['id']);
            
            if ($result) {
                setFlash('success', 'Student enrolled successfully.');
                redirect(config('app_url') . '/pages/class-students.php?id=' . $classId);
            } else {
                $errors[] = 'Failed to enroll student. The student may already be enrolled in a class for this school year.';
            }
        }
    } elseif ($action === 'bulk_enroll') {
        // Bulk enroll multiple students
        $studentIds = $_POST['student_ids'] ?? [];
        
        if (empty($studentIds)) {
            $errors[] = 'Please select at least one student to enroll.';
        } else {
            $successCount = 0;
            $failCount = 0;
            
            foreach ($studentIds as $studentId) {
                $studentId = (int)$studentId;
                if ($studentId > 0) {
                    $result = assignStudentToClass($studentId, $classId, $currentUser['id']);
                    if ($result) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $message = $successCount . ' student(s) enrolled successfully.';
                if ($failCount > 0) {
                    $message .= ' ' . $failCount . ' student(s) could not be enrolled.';
                }
                setFlash('success', $message);
            } else {
                setFlash('error', 'Failed to enroll students. They may already be enrolled in classes for this school year.');
            }
            redirect(config('app_url') . '/pages/class-students.php?id=' . $classId);
        }
    } elseif ($action === 'remove') {
        // Remove student from class
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        if ($studentId <= 0) {
            $errors[] = 'Invalid student selected.';
        } else {
            $result = removeStudentFromClass($studentId, $classId);
            
            if ($result) {
                setFlash('success', 'Student removed from class successfully.');
                redirect(config('app_url') . '/pages/class-students.php?id=' . $classId);
            } else {
                $errors[] = 'Failed to remove student from class.';
            }
        }
    }
}

// Get students in this class
$classStudents = getClassStudents($classId);

// Get available students (not enrolled in any class for this school year)
$availableStudents = [];
if ($isAdmin || $isTeacher) {
    // Get all active students not enrolled in any class for this school year
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
    $availableStudents = dbFetchAll($sql, [$class['school_year_id']]);
}

$pageTitle = $class['grade_level'] . ' - ' . $class['section'] . ' Students';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="<?php echo config('app_url'); ?>/pages/classes.php" class="hover:text-violet-600 transition-colors">Classes</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-gray-700"><?php echo e($class['grade_level'] . ' - ' . $class['section']); ?></span>
            </div>
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight"><?php echo e($class['grade_level'] . ' - ' . $class['section']); ?></h1>
                    <p class="text-gray-500 mt-1">
                        Teacher: <?php echo e($class['teacher_name']); ?> • 
                        School Year: <?php echo e($class['school_year_name']); ?>
                    </p>
                </div>
                <a href="<?php echo config('app_url'); ?>/pages/classes.php?school_year_id=<?php echo $class['school_year_id']; ?>" 
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Classes
                </a>
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
            <!-- Enroll Student Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Enroll Students</h2>
                    
                    <?php if (empty($availableStudents)): ?>
                        <div class="bg-gray-50 border border-gray-200 text-gray-600 p-4 rounded-lg">
                            <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <p class="text-sm text-center">All students are already enrolled in classes for this school year.</p>
                        </div>
                    <?php else: ?>
                    
                    <!-- Tab Navigation -->
                    <div class="flex border-b border-gray-200 mb-4">
                        <button type="button" id="singleTab" onclick="showTab('single')" class="px-4 py-2 text-sm font-medium text-violet-600 border-b-2 border-violet-600">
                            Single
                        </button>
                        <button type="button" id="bulkTab" onclick="showTab('bulk')" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                            Bulk Add
                        </button>
                    </div>
                    
                    <!-- Single Enroll Form -->
                    <div id="singleForm">
                        <form method="POST" action="" class="space-y-4">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="enroll">
                            
                            <div>
                                <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Select Student <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="student_id" 
                                    name="student_id" 
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                    required
                                >
                                    <option value="">Choose a student...</option>
                                    <?php foreach ($availableStudents as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo e($student['last_name'] . ', ' . $student['first_name']); ?>
                                            <?php if ($student['lrn']): ?> (LRN: <?php echo e($student['lrn']); ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500"><?php echo count($availableStudents); ?> student(s) available</p>
                            </div>
                            
                            <button 
                                type="submit" 
                                class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm"
                            >
                                <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                                Enroll Student
                            </button>
                        </form>
                    </div>
                    
                    <!-- Bulk Enroll Form -->
                    <div id="bulkForm" style="display: none;">
                        <form method="POST" action="" class="space-y-4" id="bulkEnrollForm">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="bulk_enroll">
                            
                            <!-- Search Filter -->
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input 
                                    type="text" 
                                    id="studentSearch" 
                                    placeholder="Search by name or LRN..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                >
                            </div>
                            
                            <!-- Selection Controls -->
                            <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <button type="button" onclick="selectAllVisible()" class="text-xs font-medium text-violet-600 hover:text-violet-800 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Select All
                                    </button>
                                    <button type="button" onclick="deselectAllStudents()" class="text-xs font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Clear
                                    </button>
                                </div>
                                <span class="text-xs text-gray-500" id="visibleCount"><?php echo count($availableStudents); ?> shown</span>
                            </div>
                            
                            <!-- Student List -->
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <div class="max-h-72 overflow-y-auto" id="studentListContainer">
                                    <?php foreach ($availableStudents as $index => $student): ?>
                                        <label class="student-item flex items-center px-4 py-3 hover:bg-violet-50 cursor-pointer transition-colors <?php echo $index > 0 ? 'border-t border-gray-100' : ''; ?>" 
                                               data-name="<?php echo strtolower(e($student['last_name'] . ' ' . $student['first_name'])); ?>"
                                               data-lrn="<?php echo strtolower(e($student['lrn'] ?? '')); ?>">
                                            <input 
                                                type="checkbox" 
                                                name="student_ids[]" 
                                                value="<?php echo $student['id']; ?>"
                                                class="bulk-student-checkbox w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500"
                                            >
                                            <div class="ml-3 flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?php echo e($student['last_name'] . ', ' . $student['first_name']); ?>
                                                </p>
                                                <?php if ($student['lrn']): ?>
                                                    <p class="text-xs text-gray-500">LRN: <?php echo e($student['lrn']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-2 flex-shrink-0 selected-badge hidden">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700">
                                                    ✓
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- No Results Message -->
                                <div id="noResults" class="hidden px-4 py-8 text-center">
                                    <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">No students match your search</p>
                                </div>
                            </div>
                            
                            <!-- Selection Summary & Submit -->
                            <div class="bg-violet-50 rounded-xl p-4 border border-violet-100">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-medium text-violet-900">Selected Students</span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold bg-violet-600 text-white" id="selectedCount">0</span>
                                </div>
                                <button 
                                    type="submit" 
                                    id="enrollBtn"
                                    disabled
                                    class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span id="enrollBtnText">Select students to enroll</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                        function showTab(tab) {
                            var singleForm = document.getElementById('singleForm');
                            var bulkForm = document.getElementById('bulkForm');
                            var singleTab = document.getElementById('singleTab');
                            var bulkTab = document.getElementById('bulkTab');
                            
                            if (tab === 'single') {
                                singleForm.style.display = 'block';
                                bulkForm.style.display = 'none';
                                singleTab.className = 'px-4 py-2 text-sm font-medium text-violet-600 border-b-2 border-violet-600';
                                bulkTab.className = 'px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700';
                            } else {
                                singleForm.style.display = 'none';
                                bulkForm.style.display = 'block';
                                singleTab.className = 'px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700';
                                bulkTab.className = 'px-4 py-2 text-sm font-medium text-violet-600 border-b-2 border-violet-600';
                            }
                        }
                        
                        function selectAllVisible() {
                            var items = document.querySelectorAll('.student-item:not([style*="display: none"]) .bulk-student-checkbox');
                            items.forEach(function(cb) { cb.checked = true; });
                            updateSelectedCount();
                            updateBadges();
                        }
                        
                        function deselectAllStudents() {
                            var checkboxes = document.querySelectorAll('.bulk-student-checkbox');
                            checkboxes.forEach(function(cb) { cb.checked = false; });
                            updateSelectedCount();
                            updateBadges();
                        }
                        
                        function updateSelectedCount() {
                            var checkboxes = document.querySelectorAll('.bulk-student-checkbox:checked');
                            var count = checkboxes.length;
                            document.getElementById('selectedCount').textContent = count;
                            
                            var btn = document.getElementById('enrollBtn');
                            var btnText = document.getElementById('enrollBtnText');
                            
                            if (count > 0) {
                                btn.disabled = false;
                                btnText.textContent = 'Enroll ' + count + ' Student' + (count > 1 ? 's' : '');
                            } else {
                                btn.disabled = true;
                                btnText.textContent = 'Select students to enroll';
                            }
                        }
                        
                        function updateBadges() {
                            document.querySelectorAll('.student-item').forEach(function(item) {
                                var checkbox = item.querySelector('.bulk-student-checkbox');
                                var badge = item.querySelector('.selected-badge');
                                if (checkbox.checked) {
                                    badge.classList.remove('hidden');
                                    item.classList.add('bg-violet-50');
                                } else {
                                    badge.classList.add('hidden');
                                    item.classList.remove('bg-violet-50');
                                }
                            });
                        }
                        
                        function filterStudents() {
                            var search = document.getElementById('studentSearch').value.toLowerCase().trim();
                            var items = document.querySelectorAll('.student-item');
                            var visibleCount = 0;
                            
                            items.forEach(function(item) {
                                var name = item.getAttribute('data-name');
                                var lrn = item.getAttribute('data-lrn');
                                
                                if (name.includes(search) || lrn.includes(search)) {
                                    item.style.display = '';
                                    visibleCount++;
                                } else {
                                    item.style.display = 'none';
                                }
                            });
                            
                            document.getElementById('visibleCount').textContent = visibleCount + ' shown';
                            document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);
                            document.getElementById('studentListContainer').classList.toggle('hidden', visibleCount === 0);
                        }
                        
                        // Add event listeners
                        document.addEventListener('DOMContentLoaded', function() {
                            var checkboxes = document.querySelectorAll('.bulk-student-checkbox');
                            checkboxes.forEach(function(cb) {
                                cb.addEventListener('change', function() {
                                    updateSelectedCount();
                                    updateBadges();
                                });
                            });
                            
                            document.getElementById('studentSearch').addEventListener('input', filterStudents);
                        });
                    </script>
                    <?php endif; ?>
                </div>
                
                <!-- Class Summary Card -->
                <div class="bg-white rounded-xl border border-gray-100 p-6 mt-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Class Summary</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Grade Level</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo e($class['grade_level']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Section</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo e($class['section']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Teacher</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo e($class['teacher_name']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">School Year</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo e($class['school_year_name']); ?></span>
                        </div>
                        <div class="pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Students</span>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-violet-50 text-violet-700 border border-violet-200">
                                    <?php echo count($classStudents); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-900">Enrolled Students</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($classStudents)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-500">No students enrolled in this class yet.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classStudents as $student): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo e($student['last_name'] . ', ' . $student['first_name']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500">ID: <?php echo e($student['student_code']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">
                                                    <?php echo $student['lrn'] ? e($student['lrn']) : '<span class="text-gray-400">Not set</span>'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600"><?php echo formatDate($student['enrolled_at']); ?></div>
                                                <?php if ($student['enrolled_by_name']): ?>
                                                    <div class="text-xs text-gray-500">by <?php echo e($student['enrolled_by_name']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a 
                                                        href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $student['id']; ?>" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-gray-50 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-100 transition-colors border border-gray-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        View
                                                    </a>
                                                    <form method="POST" action="" class="inline-block">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <button 
                                                            type="submit" 
                                                            class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors border border-red-200"
                                                            onclick="return confirm('Remove <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?> from this class?')"
                                                        >
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"/>
                                                            </svg>
                                                            Remove
                                                        </button>
                                                    </form>
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
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Total: <?php echo count($classStudents); ?> student<?php echo count($classStudents) != 1 ? 's' : ''; ?> enrolled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
