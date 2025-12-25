<?php
/**
 * Edit Student Page
 * Form to update existing student information
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/barcode.php';

requireAnyRole(['admin', 'operator']);

$studentId = (int)($_GET['id'] ?? 0);

if (!$studentId) {
    setFlash('error', 'Invalid student ID.');
    redirect(config('app_url') . '/pages/students.php');
}

$sql = "SELECT * FROM students WHERE id = ?";
$student = dbFetchOne($sql, [$studentId]);

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect(config('app_url') . '/pages/students.php');
}

$errors = [];
$formData = $student;

if (isPost()) {
    verifyCsrf();
    
    // Format phone number
    $rawPhone = sanitizeString($_POST['parent_phone'] ?? '');
    if ($rawPhone) {
        $rawPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (strlen($rawPhone) === 10 && $rawPhone[0] === '9') {
            $rawPhone = '+63' . $rawPhone;
        } elseif (strlen($rawPhone) === 11 && substr($rawPhone, 0, 2) === '09') {
            $rawPhone = '+63' . substr($rawPhone, 1);
        } elseif (strlen($rawPhone) === 12 && substr($rawPhone, 0, 2) === '63') {
            $rawPhone = '+' . $rawPhone;
        } elseif (!str_starts_with($rawPhone, '+')) {
            $rawPhone = '+63' . $rawPhone;
        }
    }
    
    $formData = [
        'lrn' => sanitizeString($_POST['lrn'] ?? ''),
        'first_name' => sanitizeString($_POST['first_name'] ?? ''),
        'last_name' => sanitizeString($_POST['last_name'] ?? ''),
        'parent_name' => sanitizeString($_POST['parent_name'] ?? ''),
        'parent_phone' => $rawPhone,
        'parent_email' => sanitizeEmail($_POST['parent_email'] ?? ''),
        'address' => sanitizeString($_POST['address'] ?? ''),
        'date_of_birth' => sanitizeString($_POST['date_of_birth'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    
    $required = ['lrn', 'first_name', 'last_name', 'parent_name', 'parent_phone', 'address'];
    $missing = validateRequired($required, $formData);
    
    if (!empty($missing)) {
        $errors[] = 'Please fill in all required fields: ' . implode(', ', $missing);
    }
    
    if ($formData['lrn'] && !preg_match('/^\d{12}$/', $formData['lrn'])) {
        $errors['lrn'] = 'LRN must be exactly 12 digits.';
    }
    
    if ($formData['parent_email'] && !validateEmail($formData['parent_email'])) {
        $errors['parent_email'] = 'Please enter a valid email address.';
    }
    
    if (!$formData['parent_phone']) {
        $errors['parent_phone'] = 'Mobile number is required.';
    } elseif (!preg_match('/^\+63[0-9]{10}$/', $formData['parent_phone'])) {
        $errors['parent_phone'] = 'Please enter a valid Philippine mobile number.';
    }
    
    // Check for duplicate LRN (excluding current student)
    if (!isset($errors['lrn']) && $formData['lrn'] && $formData['lrn'] !== ($student['lrn'] ?? '')) {
        $checkSql = "SELECT id FROM students WHERE lrn = ? AND id != ?";
        $existing = dbFetchOne($checkSql, [$formData['lrn'], $studentId]);
        if ($existing) {
            $errors['lrn'] = 'A student with this LRN already exists.';
        }
    }
    
    if (empty($errors)) {
        try {
            dbBeginTransaction();
            
            $barcodePath = $student['barcode_path'];
            $regenerateBarcode = isset($_POST['regenerate_barcode']) || $formData['lrn'] !== ($student['lrn'] ?? '');
            if ($regenerateBarcode) {
                $barcodePath = regenerateStudentBarcode($formData['lrn'], $student['barcode_path']);
            }
            
            // Handle photo upload/removal (no file size limit)
            $photoPath = $student['photo_path'] ?? null;
            
            // Check if user wants to remove photo
            if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
                // Delete old photo file
                if ($photoPath && file_exists(__DIR__ . '/../' . $photoPath)) {
                    @unlink(__DIR__ . '/../' . $photoPath);
                }
                $photoPath = null;
            }
            
            // Handle new photo upload
            if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../storage/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileInfo = pathinfo($_FILES['student_photo']['name']);
                $extension = strtolower($fileInfo['extension'] ?? 'jpg');
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                if (in_array($extension, $allowedExtensions)) {
                    // Delete old photo if exists
                    if ($photoPath && file_exists(__DIR__ . '/../' . $photoPath)) {
                        @unlink(__DIR__ . '/../' . $photoPath);
                    }
                    
                    $newFilename = 'student_' . $studentId . '_' . time() . '.' . $extension;
                    $targetPath = $uploadDir . $newFilename;
                    
                    if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $targetPath)) {
                        $photoPath = 'storage/photos/' . $newFilename;
                    }
                }
            }
            
            // Note: class/section now managed via student_classes -> classes relationship
            $updateSql = "UPDATE students SET
                            student_id = ?, lrn = ?, first_name = ?, last_name = ?,
                            barcode_path = ?, photo_path = ?, parent_name = ?, parent_phone = ?, parent_email = ?,
                            address = ?, date_of_birth = ?, is_active = ?, updated_at = NOW()
                          WHERE id = ?";
            
            $params = [
                $formData['lrn'], $formData['lrn'], $formData['first_name'], $formData['last_name'],
                $barcodePath, $photoPath, $formData['parent_name'],
                $formData['parent_phone'], $formData['parent_email'], $formData['address'],
                $formData['date_of_birth'] ?: null, $formData['is_active'], $studentId
            ];
            
            dbExecute($updateSql, $params);
            dbCommit();
            
            if (function_exists('logInfo')) {
                logInfo('Student updated', ['student_id' => $studentId, 'lrn' => $formData['lrn']]);
            }
            
            setFlash('success', 'Student updated successfully!');
            redirect(config('app_url') . '/pages/student-view.php?id=' . $studentId);
            
        } catch (Exception $e) {
            dbRollback();
            // Log the actual error
            error_log('[STUDENT-EDIT] Error: ' . $e->getMessage());
            
            if (function_exists('logError')) {
                logError('Failed to update student: ' . $e->getMessage(), $formData);
            }
            $errors[] = 'Failed to update student: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Student';
$currentUser = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <div class="mb-6 sm:mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="<?php echo config('app_url'); ?>/pages/students.php" class="hover:text-violet-600">Students</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span>Edit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Edit Student</h1>
            <p class="text-sm sm:text-base text-gray-500 mt-1">Update student information</p>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <?php if (!empty($errors) && is_array($errors) && isset($errors[0])): ?>
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <p class="ml-3 text-sm text-red-700"><?php echo e($errors[0]); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-100 p-6">
            <?php echo csrfField(); ?>
            
            <div class="space-y-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Student Information</h3>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- LRN -->
                        <div class="sm:col-span-2">
                            <label for="lrn" class="block text-sm font-medium text-gray-700 mb-1">
                                LRN (Learner Reference Number) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="lrn" id="lrn" required maxlength="12" pattern="\d{12}"
                                value="<?php echo e($formData['lrn'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['lrn']) ? 'border-red-300' : ''; ?>"
                                placeholder="Enter 12-digit LRN">
                            <?php if (isset($errors['lrn'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['lrn']); ?></p>
                            <?php else: ?>
                                <p class="mt-1 text-xs text-gray-500">Changing this will regenerate the barcode</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name" id="first_name" required
                                value="<?php echo e($formData['first_name'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                        </div>
                        
                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="last_name" id="last_name" required
                                value="<?php echo e($formData['last_name'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                        </div>
                        
                        <!-- Date of Birth -->
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth"
                                value="<?php echo e($formData['date_of_birth'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                        </div>
                        
                        <!-- Student Photo -->
                        <div>
                            <label for="student_photo" class="block text-sm font-medium text-gray-700 mb-1">
                                Student Photo <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <div class="flex items-center gap-4">
                                <div id="photoPreview" class="w-20 h-24 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden">
                                    <?php if (!empty($student['photo_path']) && file_exists(__DIR__ . '/../' . $student['photo_path'])): ?>
                                        <img src="<?php echo config('app_url') . '/' . e($student['photo_path']); ?>" alt="Current Photo" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="student_photo" id="student_photo" accept="image/*"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                                    <p class="mt-1 text-xs text-gray-500">
                                        <?php if (!empty($student['photo_path'])): ?>
                                            Upload a new photo to replace the current one
                                        <?php else: ?>
                                            Upload a photo for the student ID card (JPG, PNG, WebP)
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($student['photo_path'])): ?>
                                        <label class="inline-flex items-center mt-2">
                                            <input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                                            <span class="ml-2 text-xs text-red-600">Remove current photo</span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Note: Class/Section is managed via Class Enrollment (see student-view.php) -->
                        
                        <!-- Status -->
                        <div>
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" value="1"
                                    <?php echo ($formData['is_active'] ?? 1) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                    Active student
                                </label>
                            </div>
                        </div>
                        
                        <!-- Regenerate Barcode -->
                        <div>
                            <div class="flex items-center">
                                <input type="checkbox" name="regenerate_barcode" id="regenerate_barcode" value="1"
                                    class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
                                <label for="regenerate_barcode" class="ml-2 block text-sm text-gray-900">
                                    Regenerate barcode (use if barcode won't scan)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Parent/Guardian Information</h3>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Parent Name -->
                        <div class="sm:col-span-2">
                            <label for="parent_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Parent/Guardian Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="parent_name" id="parent_name" required
                                value="<?php echo e($formData['parent_name'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['parent_name']) ? 'border-red-300' : ''; ?>">
                            <?php if (isset($errors['parent_name'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['parent_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Parent Phone -->
                        <div>
                            <label for="parent_phone" class="block text-sm font-medium text-gray-700 mb-1">
                                Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">+63</span>
                                <input type="tel" name="parent_phone" id="parent_phone" required
                                    value="<?php echo e(preg_replace('/^\+63/', '', $formData['parent_phone'] ?? '')); ?>"
                                    class="block w-full rounded-none rounded-r-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['parent_phone']) ? 'border-red-300' : ''; ?>"
                                    placeholder="917 123 4567"
                                    maxlength="10">
                            </div>
                            <?php if (isset($errors['parent_phone'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['parent_phone']); ?></p>
                            <?php else: ?>
                                <p class="mt-1 text-xs text-gray-500">Enter 10-digit mobile number (e.g., 9171234567)</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Parent Email -->
                        <div>
                            <label for="parent_email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <input type="email" name="parent_email" id="parent_email"
                                value="<?php echo e($formData['parent_email'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['parent_email']) ? 'border-red-300' : ''; ?>"
                                placeholder="parent@example.com">
                            <?php if (isset($errors['parent_email'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['parent_email']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Address -->
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                                Address <span class="text-red-500">*</span>
                            </label>
                            <textarea name="address" id="address" rows="3" required
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['address']) ? 'border-red-300' : ''; ?>"
                            ><?php echo e($formData['address'] ?? ''); ?></textarea>
                            <?php if (isset($errors['address'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['address']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 flex items-center justify-end gap-x-4 pt-6 border-t border-gray-100">
                <a href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $studentId; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
                <button type="submit" id="submitBtn" class="inline-flex justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Update Student
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    const validators = {
        lrn: {
            validate: (value) => /^\d{12}$/.test(value),
            message: 'LRN must be exactly 12 digits',
            required: true
        },
        first_name: {
            validate: (value) => value.trim().length >= 2,
            message: 'First name must be at least 2 characters',
            required: true
        },
        last_name: {
            validate: (value) => value.trim().length >= 2,
            message: 'Last name must be at least 2 characters',
            required: true
        },
        grade: {
            validate: (value) => ['7', '8', '9', '10', '11', '12'].includes(value),
            message: 'Please select a grade level',
            required: true
        },
        parent_name: {
            validate: (value) => value.trim().length >= 2,
            message: 'Parent/Guardian name must be at least 2 characters',
            required: true
        },
        parent_phone: {
            validate: (value) => /^9\d{9}$/.test(value.replace(/\s/g, '')),
            message: 'Enter valid 10-digit number starting with 9',
            required: true
        },
        parent_email: {
            validate: (value) => !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            message: 'Enter a valid email address',
            required: false
        },
        address: {
            validate: (value) => value.trim().length >= 10,
            message: 'Please enter a complete address',
            required: true
        }
    };
    
    function showError(input, message) {
        let errorEl = input.parentElement.querySelector('.validation-error');
        if (!errorEl) {
            errorEl = document.createElement('p');
            errorEl.className = 'validation-error mt-1 text-sm text-red-600';
            const container = input.closest('.flex') || input.parentElement;
            container.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        input.classList.add('border-red-300');
        input.classList.remove('border-green-300');
    }
    
    function clearError(input) {
        const container = input.closest('.flex')?.parentElement || input.parentElement;
        const errorEl = container.querySelector('.validation-error');
        if (errorEl) errorEl.remove();
        input.classList.remove('border-red-300');
    }
    
    function showSuccess(input) {
        clearError(input);
        input.classList.add('border-green-300');
    }
    
    function validateField(input) {
        const name = input.name;
        const value = input.value.trim();
        const validator = validators[name];
        
        if (!validator) return true;
        
        if (validator.required && !value) {
            showError(input, 'This field is required');
            return false;
        }
        
        if (value && !validator.validate(value)) {
            showError(input, validator.message);
            return false;
        }
        
        if (value) showSuccess(input);
        else clearError(input);
        return true;
    }
    
    Object.keys(validators).forEach(fieldName => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (input) {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('border-red-300')) validateField(input);
            });
        }
    });
    
    const lrnInput = document.getElementById('lrn');
    if (lrnInput) {
        lrnInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 12);
        });
    }
    
    const phoneInput = document.getElementById('parent_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    }
    
    // Photo preview
    const photoInput = document.getElementById('student_photo');
    const photoPreview = document.getElementById('photoPreview');
    const removePhotoCheckbox = document.querySelector('input[name="remove_photo"]');
    
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover">`;
                };
                reader.readAsDataURL(file);
                // Uncheck remove photo if selecting new photo
                if (removePhotoCheckbox) removePhotoCheckbox.checked = false;
            }
        });
    }
    
    // Handle remove photo checkbox
    if (removePhotoCheckbox && photoPreview) {
        removePhotoCheckbox.addEventListener('change', function() {
            if (this.checked) {
                photoPreview.innerHTML = `<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>`;
                // Clear file input
                if (photoInput) photoInput.value = '';
            }
        });
    }
    
    // Smooth scroll to element
    function smoothScrollTo(element) {
        // Get the parent container (the field wrapper div)
        const container = element.closest('.sm\\:col-span-2') || element.closest('div');
        const target = container || element;
        
        // Scroll the element into view with some space at top
        target.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center'
        });
    }
    
    // Show toast notification
    function showToast(message, type = 'error') {
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();
        
        const toast = document.createElement('div');
        toast.className = `toast-notification fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`;
        toast.innerHTML = `
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${type === 'error' 
                        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'}
                </svg>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
        });
        
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        let firstErrorField = null;
        
        Object.keys(validators).forEach(fieldName => {
            const input = form.querySelector(`[name="${fieldName}"]`);
            if (input && !validateField(input)) {
                isValid = false;
                if (!firstErrorField) firstErrorField = input;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showToast('Please fill in all required fields correctly');
            
            if (firstErrorField) {
                smoothScrollTo(firstErrorField);
                const container = firstErrorField.closest('.flex')?.parentElement || firstErrorField.parentElement;
                container.classList.add('animate-pulse');
                
                setTimeout(() => {
                    firstErrorField.focus();
                    container.classList.remove('animate-pulse');
                }, 600);
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
