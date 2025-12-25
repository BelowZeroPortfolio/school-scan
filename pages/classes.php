<?php
/**
 * Class Management Page
 * Display, create, and manage classes
 * Admin: Full access | Principal: View-only | Teacher: Own classes
 * 
 * Requirements: 3.1, 3.2, 3.3
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require admin, principal, or teacher role
requireAnyRole(['admin', 'principal', 'teacher']);

$errors = [];
$formData = [
    'grade_level' => '',
    'section' => '',
    'teacher_id' => '',
    'school_year_id' => ''
];

// Get current user info
$currentUser = getCurrentUser();
$isTeacher = isTeacher();
$isPrincipalUser = isPrincipal();
$teacherId = $currentUser['id'] ?? null;

// Get filter parameters
$filterSchoolYearId = isset($_GET['school_year_id']) ? (int)$_GET['school_year_id'] : null;

// Handle form submissions (Admin can manage all, Teachers can manage their own, Principal is view-only)
if (isPost() && !$isPrincipalUser) {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Create new class - Admin or Teacher
        $formData['grade_level'] = sanitizeString($_POST['grade_level'] ?? '');
        $formData['section'] = sanitizeString($_POST['section'] ?? '');
        $formData['school_year_id'] = (int)($_POST['school_year_id'] ?? 0);
        
        // Teachers can only create classes for themselves
        if ($isTeacher) {
            $formData['teacher_id'] = $teacherId;
        } else {
            $formData['teacher_id'] = (int)($_POST['teacher_id'] ?? 0);
        }
        
        // Validate required fields
        if (empty($formData['grade_level'])) {
            $errors[] = 'Grade level is required.';
        }
        if (empty($formData['section'])) {
            $errors[] = 'Section is required.';
        }
        if ($formData['teacher_id'] <= 0) {
            $errors[] = 'Please select a teacher.';
        }
        if ($formData['school_year_id'] <= 0) {
            $errors[] = 'Please select a school year.';
        }

        if (empty($errors)) {
            $result = createClass(
                $formData['grade_level'],
                $formData['section'],
                $formData['teacher_id'],
                $formData['school_year_id']
            );
            
            if ($result) {
                setFlash('success', 'Class "' . $formData['grade_level'] . ' - ' . $formData['section'] . '" created successfully.');
                redirect(config('app_url') . '/pages/classes.php?school_year_id=' . $formData['school_year_id']);
            } else {
                $errors[] = 'Failed to create class. It may already exist or the teacher is invalid.';
            }
        }
    } elseif ($action === 'delete') {
        // Deactivate class - Admin can delete any, Teacher can only delete their own
        $classId = (int)($_POST['class_id'] ?? 0);
        
        if ($classId <= 0) {
            $errors[] = 'Invalid class selected.';
        } else {
            $class = getClassById($classId);
            if ($class) {
                // Check permission: Admin can delete any, Teacher can only delete their own
                $canDelete = isAdmin() || ($isTeacher && $class['teacher_id'] == $teacherId);
                
                if (!$canDelete) {
                    $errors[] = 'You do not have permission to delete this class.';
                } else {
                    $result = deactivateClass($classId);
                    
                    if ($result) {
                        setFlash('success', 'Class "' . $class['grade_level'] . ' - ' . $class['section'] . '" has been deactivated.');
                        redirect(config('app_url') . '/pages/classes.php' . ($filterSchoolYearId ? '?school_year_id=' . $filterSchoolYearId : ''));
                    } else {
                        $errors[] = 'Failed to deactivate class.';
                    }
                }
            } else {
                $errors[] = 'Class not found.';
            }
        }
    } elseif ($action === 'update') {
        // Update class - Admin can update any, Teacher can only update their own
        $classId = (int)($_POST['edit_class_id'] ?? 0);
        $newGradeLevel = sanitizeString($_POST['edit_grade_level'] ?? '');
        $newSection = sanitizeString($_POST['edit_section'] ?? '');
        
        if ($classId <= 0) {
            $errors[] = 'Invalid class selected.';
        } elseif (empty($newGradeLevel) || empty($newSection)) {
            $errors[] = 'Grade level and section are required.';
        } else {
            $class = getClassById($classId);
            if ($class) {
                // Check permission: Admin can update any, Teacher can only update their own
                $canUpdate = isAdmin() || ($isTeacher && $class['teacher_id'] == $teacherId);
                
                if (!$canUpdate) {
                    $errors[] = 'You do not have permission to edit this class.';
                } else {
                    // Update the class
                    $updateSql = "UPDATE classes SET grade_level = ?, section = ?, updated_at = NOW() WHERE id = ?";
                    $result = dbExecute($updateSql, [$newGradeLevel, $newSection, $classId]);
                    
                    if ($result) {
                        setFlash('success', 'Class updated to "' . $newGradeLevel . ' - ' . $newSection . '" successfully.');
                        redirect(config('app_url') . '/pages/classes.php' . ($filterSchoolYearId ? '?school_year_id=' . $filterSchoolYearId : ''));
                    } else {
                        $errors[] = 'Failed to update class.';
                    }
                }
            } else {
                $errors[] = 'Class not found.';
            }
        }
    }
}

// Get all school years for filter dropdown
$schoolYears = getAllSchoolYears();
$activeSchoolYear = getActiveSchoolYear();

// Determine which school year to display
$selectedSchoolYearId = $filterSchoolYearId;
if (!$selectedSchoolYearId && $activeSchoolYear) {
    $selectedSchoolYearId = $activeSchoolYear['id'];
}

// Get classes for selected school year
// Teachers only see their own classes
if ($isTeacher) {
    $classes = $selectedSchoolYearId ? getTeacherClasses($teacherId, $selectedSchoolYearId) : [];
} else {
    $classes = $selectedSchoolYearId ? getClassesBySchoolYear($selectedSchoolYearId) : [];
}

// Get all teachers for dropdown (Admin only)
$teachers = isAdmin() ? getAllTeachers() : [];

// Get teachers without advisory for the selected school year (for the notice - Admin only)
$teachersWithoutAdvisory = ($selectedSchoolYearId && isAdmin()) ? getTeachersWithoutAdvisory($selectedSchoolYearId) : [];

// Set default school year for form
if (empty($formData['school_year_id']) && $selectedSchoolYearId) {
    $formData['school_year_id'] = $selectedSchoolYearId;
}

// For teachers, pre-fill their ID
if ($isTeacher) {
    $formData['teacher_id'] = $teacherId;
}

$pageTitle = $isTeacher ? 'My Class' : 'Classes';
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
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Classes</h1>
                    <p class="text-gray-500 mt-1">Manage classes by grade, section, and teacher</p>
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

        <!-- No Active School Year Warning -->
        <?php if (empty($schoolYears)): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium">No school years found.</p>
                        <p class="text-sm mt-1">Please <a href="<?php echo config('app_url'); ?>/pages/school-years.php" class="underline hover:text-amber-800">create a school year</a> first before adding classes.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Teachers Without Advisory Notice -->
        <?php if (!empty($teachersWithoutAdvisory) && isAdmin()): ?>
            <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 mb-6" x-data="{ expanded: false }">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 bg-violet-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-violet-900">
                                <?php echo count($teachersWithoutAdvisory); ?> teacher<?php echo count($teachersWithoutAdvisory) != 1 ? 's' : ''; ?> without advisory class
                            </p>
                            <p class="text-xs text-violet-600">Assign them to a class to complete setup</p>
                        </div>
                    </div>
                    <button @click="expanded = !expanded" class="text-violet-600 hover:text-violet-800 text-sm font-medium flex items-center">
                        <span x-text="expanded ? 'Hide' : 'Show'">Show</span>
                        <svg class="w-4 h-4 ml-1 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
                <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-violet-200">
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($teachersWithoutAdvisory as $teacher): ?>
                            <div class="inline-flex items-center px-3 py-1.5 bg-white rounded-lg border border-violet-200 text-sm">
                                <div class="w-6 h-6 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white text-xs font-medium mr-2">
                                    <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                                </div>
                                <span class="text-gray-700"><?php echo e($teacher['full_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-violet-600 mt-3">
                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Use the form to create a class and assign one of these teachers as adviser.
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Create Class Form - Admin and Teachers (not Principal) -->
            <?php if (!empty($schoolYears) && !$isPrincipalUser): ?>
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Create Class</h2>
                    
                    <?php if (isAdmin() && empty($teachers)): ?>
                        <div class="bg-amber-50 border border-amber-200 text-amber-700 p-4 rounded-lg">
                            <p class="text-sm">No teachers found. Please create a user with the "teacher" role first.</p>
                        </div>
                    <?php else: ?>
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div>
                            <label for="grade_level" class="block text-sm font-medium text-gray-700 mb-1">
                                Grade Level <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="grade_level" 
                                name="grade_level" 
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                required
                            >
                                <option value="">Select Grade Level</option>
                                <?php 
                                $gradeLevels = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
                                foreach ($gradeLevels as $grade): 
                                ?>
                                    <option value="<?php echo e($grade); ?>" <?php echo $formData['grade_level'] === $grade ? 'selected' : ''; ?>>
                                        <?php echo e($grade); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="section" class="block text-sm font-medium text-gray-700 mb-1">
                                Section <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="section" 
                                name="section" 
                                value="<?php echo e($formData['section']); ?>"
                                placeholder="e.g., Section A, Einstein"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                required
                            >
                        </div>
                        
                        <?php if (isAdmin()): ?>
                        <div>
                            <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Teacher <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="teacher_id" 
                                name="teacher_id" 
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                required
                            >
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo $formData['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <!-- Teachers are auto-assigned to their own class -->
                        <input type="hidden" name="teacher_id" value="<?php echo $teacherId; ?>">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-sm text-blue-700">
                                <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                This class will be assigned to you automatically.
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label for="school_year_id" class="block text-sm font-medium text-gray-700 mb-1">
                                School Year <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="school_year_id" 
                                name="school_year_id" 
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                required
                            >
                                <option value="">Select School Year</option>
                                <?php foreach ($schoolYears as $sy): ?>
                                    <option value="<?php echo $sy['id']; ?>" <?php echo $formData['school_year_id'] == $sy['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button 
                            type="submit" 
                            class="w-full px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm"
                        >
                            <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create Class
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Classes List -->
            <div class="<?php echo (!empty($schoolYears) && !$isPrincipalUser) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <h2 class="text-lg font-semibold text-gray-900">All Classes</h2>
                            
                            <!-- School Year Filter -->
                            <?php if (!empty($schoolYears)): ?>
                            <form method="GET" action="" class="flex items-center gap-2">
                                <label for="filter_school_year" class="text-sm text-gray-600">School Year:</label>
                                <select 
                                    id="filter_school_year" 
                                    name="school_year_id" 
                                    onchange="this.form.submit()"
                                    class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                                >
                                    <?php foreach ($schoolYears as $sy): ?>
                                        <option value="<?php echo $sy['id']; ?>" <?php echo $selectedSchoolYearId == $sy['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($sy['name']); ?><?php echo $sy['is_active'] ? ' (Active)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-500">
                                                <?php if (empty($schoolYears)): ?>
                                                    No school years found. Create a school year first.
                                                <?php elseif (empty($teachers)): ?>
                                                    No teachers found. <a href="<?php echo config('app_url'); ?>/pages/users.php" class="text-violet-600 hover:underline">Create a teacher account</a> first.
                                                <?php else: ?>
                                                    No classes found for this school year.
                                                    <?php if (isAdmin()): ?>
                                                        <?php if (!empty($teachersWithoutAdvisory)): ?>
                                                            <br><span class="text-violet-600"><?php echo count($teachersWithoutAdvisory); ?> teacher<?php echo count($teachersWithoutAdvisory) != 1 ? 's are' : ' is'; ?> waiting to be assigned!</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $class): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 bg-violet-100 rounded-lg flex items-center justify-center">
                                                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                        </svg>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo e($class['grade_level']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo e($class['section']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo e($class['teacher_name'] ?? 'No teacher assigned'); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo e($class['teacher_email'] ?? ''); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $class['student_count'] > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-gray-50 text-gray-600 border border-gray-200'; ?>">
                                                    <?php echo (int)$class['student_count']; ?> student<?php echo $class['student_count'] != 1 ? 's' : ''; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-600"><?php echo e($class['school_year_name']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a 
                                                        href="<?php echo config('app_url'); ?>/pages/class-students.php?id=<?php echo $class['id']; ?>" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-violet-50 text-violet-700 text-xs font-medium rounded-lg hover:bg-violet-100 transition-colors border border-violet-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                                        </svg>
                                                        Students
                                                    </a>
                                                    <?php 
                                                    // Admin can delete any class, Teacher can only delete their own, Principal is view-only
                                                    $canDeleteClass = !$isPrincipalUser && (isAdmin() || ($isTeacher && isset($class['teacher_id']) && $class['teacher_id'] == $teacherId));
                                                    if ($canDeleteClass): 
                                                    ?>
                                                    <a 
                                                        href="<?php echo config('app_url'); ?>/pages/class-edit.php?id=<?php echo $class['id']; ?>"
                                                        class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100 transition-colors border border-amber-200"
                                                    >
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="" class="inline-block">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                        <button 
                                                            type="submit" 
                                                            class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors border border-red-200"
                                                            onclick="return confirm('Are you sure you want to deactivate this class?')"
                                                        >
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                            Delete
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
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Total: <?php echo count($classes); ?> class<?php echo count($classes) != 1 ? 'es' : ''; ?></span>
                            <?php 
                            $totalStudents = array_sum(array_column($classes, 'student_count'));
                            ?>
                            <span>Total Students: <strong class="text-violet-600"><?php echo $totalStudents; ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
