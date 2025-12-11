<?php
/**
 * Add Student Page
 * Form to create new student with barcode generation
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/barcode.php';

// Require authentication and appropriate role
requireAnyRole(['admin', 'operator']);

$errors = [];
$formData = [];

// Handle form submission
if (isPost()) {
    verifyCsrf();
    
    // Format phone number - add +63 prefix if not present
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
    
    // Get and sanitize form data
    $formData = [
        'lrn' => sanitizeString($_POST['lrn'] ?? ''),
        'first_name' => sanitizeString($_POST['first_name'] ?? ''),
        'last_name' => sanitizeString($_POST['last_name'] ?? ''),
        'grade' => sanitizeString($_POST['grade'] ?? ''),
        'section' => sanitizeString($_POST['section'] ?? ''),
        'parent_name' => sanitizeString($_POST['parent_name'] ?? ''),
        'parent_phone' => $rawPhone,
        'parent_email' => sanitizeEmail($_POST['parent_email'] ?? ''),
        'address' => sanitizeString($_POST['address'] ?? ''),
        'date_of_birth' => sanitizeString($_POST['date_of_birth'] ?? ''),
    ];

    // Validate required fields
    $required = ['lrn', 'first_name', 'last_name', 'grade', 'parent_name', 'parent_phone', 'address'];
    $missing = validateRequired($required, $formData);
    
    if (!empty($missing)) {
        $errors[] = 'Please fill in all required fields: ' . implode(', ', $missing);
    }
    
    // Validate LRN format (12 digits)
    if ($formData['lrn'] && !preg_match('/^\d{12}$/', $formData['lrn'])) {
        $errors['lrn'] = 'LRN must be exactly 12 digits.';
    }
    
    // Validate grade
    if ($formData['grade'] && !in_array($formData['grade'], ['7', '8', '9', '10', '11', '12'])) {
        $errors['grade'] = 'Please select a valid grade level.';
    }
    
    // Validate email if provided
    if ($formData['parent_email'] && !validateEmail($formData['parent_email'])) {
        $errors['parent_email'] = 'Please enter a valid email address.';
    }
    
    // Validate phone format (required, +63 followed by 10 digits)
    if (!$formData['parent_phone']) {
        $errors['parent_phone'] = 'Mobile number is required.';
    } elseif (!preg_match('/^\+63[0-9]{10}$/', $formData['parent_phone'])) {
        $errors['parent_phone'] = 'Please enter a valid Philippine mobile number.';
    }
    
    // Check for duplicate LRN
    if (!isset($errors['lrn']) && $formData['lrn']) {
        $checkSql = "SELECT id FROM students WHERE lrn = ?";
        $existing = dbFetchOne($checkSql, [$formData['lrn']]);
        
        if ($existing) {
            $errors['lrn'] = 'A student with this LRN already exists.';
        }
    }
    
    // If no errors, create student
    if (empty($errors)) {
        try {
            dbBeginTransaction();
            
            // Generate barcode using LRN
            $barcodePath = generateStudentBarcode($formData['lrn']);
            
            // Insert student record (student_id = lrn for compatibility)
            $sql = "INSERT INTO students (
                        student_id, lrn, first_name, last_name, class, section,
                        barcode_path, parent_name, parent_phone, parent_email,
                        address, date_of_birth, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            
            $params = [
                $formData['lrn'], // Use LRN as student_id for compatibility
                $formData['lrn'],
                $formData['first_name'],
                $formData['last_name'],
                'Grade ' . $formData['grade'],
                $formData['section'],
                $barcodePath,
                $formData['parent_name'],
                $formData['parent_phone'],
                $formData['parent_email'],
                $formData['address'],
                $formData['date_of_birth'] ?: null,
            ];
            
            $studentId = dbInsert($sql, $params);
            dbCommit();
            
            if (function_exists('logInfo')) {
                logInfo('Student created', ['student_id' => $studentId, 'lrn' => $formData['lrn']]);
            }
            
            setFlash('success', 'Student added successfully!');
            redirect(config('app_url') . '/pages/student-view.php?id=' . $studentId);
            
        } catch (Exception $e) {
            dbRollback();
            if (function_exists('logError')) {
                logError('Failed to create student: ' . $e->getMessage(), $formData);
            }
            // Show actual error in development mode
            if (config('debug', false)) {
                $errors[] = 'Error: ' . $e->getMessage();
            } else {
                $errors[] = 'Failed to create student. Please try again.';
            }
        }
    }
}

$pageTitle = 'Add Student';
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
                <span>Add New</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Add New Student</h1>
            <p class="text-sm sm:text-base text-gray-500 mt-1">Create a new student record with barcode generation</p>
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

        <form method="POST" action="" class="bg-white rounded-xl border border-gray-100 p-6">
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
                                <p class="mt-1 text-xs text-gray-500">This will be used for barcode generation and student identification</p>
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
                        
                        <!-- Grade -->
                        <div>
                            <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">
                                Grade Level <span class="text-red-500">*</span>
                            </label>
                            <select name="grade" id="grade" required
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 <?php echo isset($errors['grade']) ? 'border-red-300' : ''; ?>">
                                <option value="">Select Grade</option>
                                <?php for ($g = 7; $g <= 12; $g++): ?>
                                    <option value="<?php echo $g; ?>" <?php echo ($formData['grade'] ?? '') == $g ? 'selected' : ''; ?>>
                                        Grade <?php echo $g; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <?php if (isset($errors['grade'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo e($errors['grade']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Section -->
                        <div>
                            <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <input type="text" name="section" id="section"
                                value="<?php echo e($formData['section'] ?? ''); ?>"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                                placeholder="e.g., Einstein, Newton">
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
                <a href="<?php echo config('app_url'); ?>/pages/students.php" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
                <button type="submit" id="submitBtn" class="inline-flex justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Add Student
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validation rules
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
    
    // Create/update error message element
    function showError(input, message) {
        let errorEl = input.parentElement.querySelector('.validation-error');
        if (!errorEl) {
            errorEl = document.createElement('p');
            errorEl.className = 'validation-error mt-1 text-sm text-red-600';
            // Handle phone input group
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
    
    // Validate single field
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
        
        if (value) {
            showSuccess(input);
        } else {
            clearError(input);
        }
        return true;
    }
    
    // Add real-time validation
    Object.keys(validators).forEach(fieldName => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (input) {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('border-red-300')) {
                    validateField(input);
                }
            });
        }
    });
    
    // LRN: only allow digits
    const lrnInput = document.getElementById('lrn');
    if (lrnInput) {
        lrnInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 12);
        });
    }
    
    // Phone: only allow digits
    const phoneInput = document.getElementById('parent_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
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
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
        });
        
        // Auto remove
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    // Form submission validation
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
            
            // Show toast notification
            showToast('Please fill in all required fields correctly');
            
            // Smooth scroll to first error with highlight effect
            if (firstErrorField) {
                smoothScrollTo(firstErrorField);
                
                // Add pulse animation
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
