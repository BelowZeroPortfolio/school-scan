<?php
/**
 * Edit Class Page
 * Form to update existing class information
 * Admin and Teacher roles allowed (teachers can only edit their own classes)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/schoolyear.php';
require_once __DIR__ . '/../includes/classes.php';

// Require admin or teacher role
requireAnyRole(['admin', 'teacher']);

// Get current user info
$currentUser = getCurrentUser();
$isTeacher = isTeacher();
$teacherId = $currentUser['id'] ?? null;

// Get class ID from URL
$classId = (int)($_GET['id'] ?? 0);

if (!$classId) {
    setFlash('error', 'Invalid class ID.');
    redirect(config('app_url') . '/pages/classes.php');
}

// Get class details
$class = getClassById($classId);

if (!$class) {
    setFlash('error', 'Class not found.');
    redirect(config('app_url') . '/pages/classes.php');
}

// Check permission: Admin can edit any, Teacher can only edit their own
$canEdit = isAdmin() || ($isTeacher && $class['teacher_id'] == $teacherId);

if (!$canEdit) {
    setFlash('error', 'You do not have permission to edit this class.');
    redirect(config('app_url') . '/pages/classes.php');
}

$errors = [];
$formData = [
    'grade_level' => $class['grade_level'],
    'section' => $class['section'],
    'teacher_id' => $class['teacher_id'],
    'school_year_id' => $class['school_year_id']
];

// Handle form submission
if (isPost()) {
    verifyCsrf();
    
    $formData['grade_level'] = sanitizeString($_POST['grade_level'] ?? '');
    $formData['section'] = sanitizeString($_POST['section'] ?? '');
    
    // Teachers cannot change the teacher assignment
    if (isAdmin()) {
        $formData['teacher_id'] = (int)($_POST['teacher_id'] ?? 0);
    }
    
    // Validate required fields
    if (empty($formData['grade_level'])) {
        $errors[] = 'Grade level is required.';
    }
    if (empty($formData['section'])) {
        $errors[] = 'Section is required.';
    }
    if (isAdmin() && $formData['teacher_id'] <= 0) {
        $errors[] = 'Please select a teacher.';
    }
    
    if (empty($errors)) {
        // Build update query
        if (isAdmin()) {
            $updateSql = "UPDATE classes SET grade_level = ?, section = ?, teacher_id = ?, updated_at = NOW() WHERE id = ?";
            $params = [$formData['grade_level'], $formData['section'], $formData['teacher_id'], $classId];
        } else {
            $updateSql = "UPDATE classes SET grade_level = ?, section = ?, updated_at = NOW() WHERE id = ?";
            $params = [$formData['grade_level'], $formData['section'], $classId];
        }
        
        $result = dbExecute($updateSql, $params);
        
        if ($result !== false) {
            if (function_exists('logInfo')) {
                logInfo('Class updated', ['class_id' => $classId, 'grade_level' => $formData['grade_level'], 'section' => $formData['section']]);
            }
            
            setFlash('success', 'Class "' . $formData['grade_level'] . ' - ' . $formData['section'] . '" updated successfully.');
            redirect(config('app_url') . '/pages/classes.php?school_year_id=' . $class['school_year_id']);
        } else {
            $errors[] = 'Failed to update class. Please try again.';
        }
    }
}

// Get all teachers for dropdown (Admin only)
$teachers = isAdmin() ? getAllTeachers() : [];

// Get school year info
$schoolYear = null;
if ($class['school_year_id']) {
    $schoolYear = dbFetchOne("SELECT * FROM school_years WHERE id = ?", [$class['school_year_id']]);
}

$pageTitle = 'Edit Class';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <div class="mb-6 sm:mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="<?php echo config('app_url'); ?>/pages/classes.php" class="hover:text-violet-600">Classes</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span>Edit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Edit Class</h1>
            <p class="text-sm sm:text-base text-gray-500 mt-1">Update class information</p>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <?php if (!empty($errors)): ?>
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <?php foreach ($errors as $error): ?>
                            <p class="text-sm text-red-700"><?php echo e($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="max-w-2xl">
            <form method="POST" action="" class="bg-white rounded-xl border border-gray-100 p-6">
                <?php echo csrfField(); ?>
                
                <div class="space-y-6">
                    <!-- Current Class Info -->
                    <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-violet-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-violet-900">
                                    <?php echo e($class['grade_level'] . ' - ' . $class['section']); ?>
                                </p>
                                <p class="text-xs text-violet-600">
                                    <?php echo $schoolYear ? e($schoolYear['name']) : 'No school year'; ?>
                                    • <?php echo (int)$class['student_count']; ?> student<?php echo $class['student_count'] != 1 ? 's' : ''; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grade Level -->
                    <div>
                        <label for="grade_level" class="block text-sm font-medium text-gray-700 mb-1">
                            Grade Level <span class="text-red-500">*</span>
                        </label>
                        <select id="grade_level" name="grade_level" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
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
                    
                    <!-- Section -->
                    <div>
                        <label for="section" class="block text-sm font-medium text-gray-700 mb-1">
                            Section <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="section" name="section" required
                            value="<?php echo e($formData['section']); ?>"
                            placeholder="e.g., Section A, Einstein"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    </div>
                    
                    <?php if (isAdmin()): ?>
                    <!-- Teacher (Admin only) -->
                    <div>
                        <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Teacher <span class="text-red-500">*</span>
                        </label>
                        <select id="teacher_id" name="teacher_id" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $formData['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <!-- Teacher info for teachers (read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <div class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-700">
                            <?php echo e($class['teacher_name']); ?>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">You cannot change the teacher assignment.</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- School Year (read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                        <div class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-700">
                            <?php echo $schoolYear ? e($schoolYear['name']) : 'Not assigned'; ?>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">School year cannot be changed after class creation.</p>
                    </div>
                </div>
                
                <div class="mt-8 flex items-center justify-end gap-x-4 pt-6 border-t border-gray-100">
                    <a href="<?php echo config('app_url'); ?>/pages/classes.php<?php echo $class['school_year_id'] ? '?school_year_id=' . $class['school_year_id'] : ''; ?>" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                        Cancel
                    </a>
                    <button type="submit" 
                        class="inline-flex justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors">
                        Update Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
