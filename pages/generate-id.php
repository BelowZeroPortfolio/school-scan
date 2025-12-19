<?php
/**
 * Generate ID Cards Page
 * Bulk generation of student ID cards with filtering
 * Teachers can only see students from their classes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/classes.php';
require_once __DIR__ . '/../includes/schoolyear.php';

// Require authentication and admin/operator/teacher role
requireAnyRole(['admin', 'operator', 'teacher']);

$currentUser = getCurrentUser();
$isTeacher = isTeacher();
$activeSchoolYear = getActiveSchoolYear();

// Get filter parameters
$grade = sanitizeString($_GET['grade'] ?? '');
$section = sanitizeString($_GET['section'] ?? '');
$search = sanitizeString($_GET['search'] ?? '');
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// For teachers, get their classes and students
$teacherClasses = [];
$teacherStudentIds = [];

if ($isTeacher && $activeSchoolYear) {
    $teacherClasses = getTeacherClasses($currentUser['id'], $activeSchoolYear['id']);
    
    // Get all student IDs from teacher's classes
    foreach ($teacherClasses as $tc) {
        $classStudents = getClassStudents($tc['id']);
        foreach ($classStudents as $cs) {
            $teacherStudentIds[] = $cs['id'];
        }
    }
}

// Get unique grades for filters (teacher sees only their classes)
if ($isTeacher) {
    $grades = [];
    $gradeSectionsMap = [];
    foreach ($teacherClasses as $tc) {
        $gradeKey = $tc['grade_level'];
        if (!isset($gradeSectionsMap[$gradeKey])) {
            $grades[] = ['class' => $gradeKey];
            $gradeSectionsMap[$gradeKey] = [];
        }
        $gradeSectionsMap[$gradeKey][] = $tc['section'];
    }
    
    // Get sections for selected grade
    if ($grade && isset($gradeSectionsMap[$grade])) {
        $sections = array_map(function($s) { return ['section' => $s]; }, $gradeSectionsMap[$grade]);
    } else {
        $allSections = [];
        foreach ($gradeSectionsMap as $secs) {
            $allSections = array_merge($allSections, $secs);
        }
        $sections = array_map(function($s) { return ['section' => $s]; }, array_unique($allSections));
    }
} else {
    // Admin/Operator - show all grades/sections from classes table
    $gradesSql = "SELECT DISTINCT c.grade_level AS class 
                  FROM classes c 
                  JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                  WHERE c.is_active = 1 
                  ORDER BY c.grade_level";
    $grades = dbFetchAll($gradesSql);
    
    if ($grade) {
        $sectionsSql = "SELECT DISTINCT c.section 
                        FROM classes c 
                        JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                        WHERE c.is_active = 1 AND c.grade_level = ? 
                        ORDER BY c.section";
        $sections = dbFetchAll($sectionsSql, [$grade]);
    } else {
        $sectionsSql = "SELECT DISTINCT c.section 
                        FROM classes c 
                        JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                        WHERE c.is_active = 1 
                        ORDER BY c.section";
        $sections = dbFetchAll($sectionsSql);
    }
    
    // Build grade-to-sections mapping for JavaScript
    $gradeSectionsMap = [];
    foreach ($grades as $g) {
        $sectionsForGrade = dbFetchAll(
            "SELECT DISTINCT c.section 
             FROM classes c 
             JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
             WHERE c.is_active = 1 AND c.grade_level = ? 
             ORDER BY c.section",
            [$g['class']]
        );
        $gradeSectionsMap[$g['class']] = array_column($sectionsForGrade, 'section');
    }
}

// Build query based on filters
$students = [];

if ($isTeacher) {
    // Teacher: only show students from their classes
    if (!empty($teacherStudentIds) && ($classId || $search)) {
        $whereConditions = ['s.is_active = 1', 's.id IN (' . implode(',', array_map('intval', $teacherStudentIds)) . ')'];
        $params = [];
        
        if ($classId) {
            // Filter by specific class
            $whereConditions[] = "sc.class_id = ?";
            $params[] = $classId;
        }
        
        if ($search) {
            $whereConditions[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $sql = "SELECT DISTINCT s.id, s.student_id, s.lrn, s.first_name, s.last_name, 
                       c.grade_level AS class, c.section, s.barcode_path, 
                       s.parent_name, s.parent_phone, s.parent_email, s.address
                FROM students s
                JOIN student_classes sc ON s.id = sc.student_id
                JOIN classes c ON sc.class_id = c.id
                WHERE " . implode(' AND ', $whereConditions) . " AND sc.is_active = 1
                ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
        
        $students = dbFetchAll($sql, $params);
    }
} else {
    // Admin/Operator: show all students with filters (using classes table)
    if ($grade || $section || $search) {
        $whereConditions = ['s.is_active = 1', 'sc.is_active = 1', 'c.is_active = 1'];
        $params = [];
        
        if ($grade) {
            $whereConditions[] = "c.grade_level = ?";
            $params[] = $grade;
        }
        if ($section) {
            $whereConditions[] = "c.section = ?";
            $params[] = $section;
        }
        if ($search) {
            $whereConditions[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $sql = "SELECT DISTINCT s.id, s.student_id, s.lrn, s.first_name, s.last_name, 
                       c.grade_level AS class, c.section, s.barcode_path, 
                       s.parent_name, s.parent_phone, s.parent_email, s.address
                FROM students s
                JOIN student_classes sc ON s.id = sc.student_id
                JOIN classes c ON sc.class_id = c.id
                JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
        
        $students = dbFetchAll($sql, $params);
    }
}

$pageTitle = 'Generate ID Cards';
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
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Generate ID Cards</h1>
                    <p class="text-gray-500 mt-1">Select students to generate ID cards in bulk</p>
                </div>
                <button type="button" id="generateBtn" onclick="generateSelectedIDs()" disabled
                    class="inline-flex items-center px-4 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    Generate Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter Students</h3>
            <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php if ($isTeacher): ?>
                    <!-- Teacher: Class Filter -->
                    <div class="md:col-span-2">
                        <label for="class_id" class="block text-sm font-medium text-gray-600 mb-1">Select Class</label>
                        <select name="class_id" id="class_id" onchange="debounceFilter()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                            <option value="">Select a class...</option>
                            <?php foreach ($teacherClasses as $tc): ?>
                                <option value="<?php echo $tc['id']; ?>" <?php echo $classId == $tc['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($tc['grade_level'] . ' - ' . $tc['section']); ?>
                                    (<?php echo $tc['student_count']; ?> students)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <!-- Admin/Operator: Grade Filter -->
                    <div>
                        <label for="grade" class="block text-sm font-medium text-gray-600 mb-1">Grade Level</label>
                        <select name="grade" id="grade" onchange="onGradeChange()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                            <option value="">All Grades</option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?php echo e($g['class']); ?>" <?php echo $grade === $g['class'] ? 'selected' : ''; ?>>
                                    <?php echo e($g['class']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Section Filter -->
                    <div>
                        <label for="section" class="block text-sm font-medium text-gray-600 mb-1">Section</label>
                        <select name="section" id="section" onchange="debounceFilter()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?php echo e($s['section']); ?>" <?php echo $section === $s['section'] ? 'selected' : ''; ?>>
                                    <?php echo e($s['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-600 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo e($search); ?>"
                        placeholder="Name, LRN, or ID..."
                        oninput="debounceFilter()"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                </div>
                
                <!-- Actions -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">
                        Apply Filter
                    </button>
                    <a href="<?php echo config('app_url'); ?>/pages/generate-id.php" class="px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Students Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <?php if (empty($students) && ($grade || $section || $search)): ?>
                <!-- No results -->
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">No students found matching your filters.</p>
                </div>
            <?php elseif (empty($students)): ?>
                <!-- Initial state -->
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">Select filters to view students</h3>
                    <?php if ($isTeacher): ?>
                        <?php if (empty($teacherClasses)): ?>
                            <p class="mt-2 text-sm text-gray-500">You don't have any classes assigned yet. Please contact an administrator.</p>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-gray-500">Select one of your classes above to display students for ID card generation.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-2 text-sm text-gray-500">Choose a grade level or section above to display students for ID card generation.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Table Header -->
                <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()"
                            class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
                        <label for="selectAll" class="text-sm font-medium text-gray-700">Select All</label>
                    </div>
                    <span class="text-sm text-gray-500"><?php echo count($students); ?> students found</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barcode</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>"
                                            data-student='<?php echo json_encode($student); ?>'
                                            onchange="updateSelectedCount()"
                                            class="student-checkbox h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-mono text-gray-600"><?php echo e($student['lrn'] ?? $student['student_id']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600"><?php echo e($student['class'] . ($student['section'] ? ' - ' . $student['section'] : '')); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($student['barcode_path']): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                Ready
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                No barcode
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $student['id']; ?>" 
                                            class="text-violet-600 hover:text-violet-700">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Bulk ID Card Modal -->
<div id="bulkIdModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBulkModal()"></div>
        
        <div class="relative bg-white rounded-xl shadow-xl transform transition-all sm:max-w-6xl sm:w-full mx-auto max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Generated ID Cards (<span id="modalCount">0</span>)</h3>
                <div class="flex items-center gap-2">
                    <button onclick="printAllCards()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print All
                    </button>
                    <button onclick="closeBulkModal()" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div id="bulkIdContainer" class="p-6 overflow-auto max-h-[75vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="cardsGrid">
                    <!-- Cards will be inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    let debounceTimer = null;
    
    // Grade to sections mapping
    const gradeSectionsMap = <?php echo json_encode($gradeSectionsMap); ?>;
    const currentSection = '<?php echo e($section); ?>';
    
    // Update sections dropdown based on selected grade
    function updateSections() {
        const gradeSelect = document.getElementById('grade');
        const sectionSelect = document.getElementById('section');
        const selectedGrade = gradeSelect.value;
        
        // Clear current options
        sectionSelect.innerHTML = '<option value="">All Sections</option>';
        
        if (selectedGrade && gradeSectionsMap[selectedGrade]) {
            // Add sections for selected grade
            gradeSectionsMap[selectedGrade].forEach(section => {
                const option = document.createElement('option');
                option.value = section;
                option.textContent = section;
                if (section === currentSection) {
                    option.selected = true;
                }
                sectionSelect.appendChild(option);
            });
        } else {
            // Add all sections when no grade selected
            const allSections = [...new Set(Object.values(gradeSectionsMap).flat())].sort();
            allSections.forEach(section => {
                const option = document.createElement('option');
                option.value = section;
                option.textContent = section;
                if (section === currentSection) {
                    option.selected = true;
                }
                sectionSelect.appendChild(option);
            });
        }
    }
    
    // Handle grade change
    function onGradeChange() {
        updateSections();
        // Reset section when grade changes
        document.getElementById('section').value = '';
        debounceFilter();
    }
    
    // Debounce filter submission
    function debounceFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    }
    
    // Initialize sections on page load
    document.addEventListener('DOMContentLoaded', updateSections);
    
    // Toggle select all checkboxes
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateSelectedCount();
    }
    
    // Update selected count
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('generateBtn').disabled = count === 0;
        
        // Update select all state
        const allCheckboxes = document.querySelectorAll('.student-checkbox');
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
            selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
        }
    }
    
    // Generate selected IDs
    function generateSelectedIDs() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        const cardsGrid = document.getElementById('cardsGrid');
        cardsGrid.innerHTML = '';
        
        document.getElementById('modalCount').textContent = checkboxes.length;
        document.getElementById('bulkIdModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Generate cards for each selected student
        checkboxes.forEach((cb, index) => {
            const student = JSON.parse(cb.dataset.student);
            const cardHtml = createIDCardHTML(student, index);
            cardsGrid.insertAdjacentHTML('beforeend', cardHtml);
            
            // Generate QR code after card is added
            setTimeout(() => {
                const qrContainer = document.getElementById('qr-' + index);
                if (qrContainer) {
                    new QRCode(qrContainer, {
                        text: student.lrn || student.student_id,
                        width: 100,
                        height: 100,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            }, 100);
        });
    }
    
    // Create ID card HTML - Front and Back
    function createIDCardHTML(student, index) {
        const name = (student.first_name + ' ' + student.last_name).toUpperCase();
        const lrn = student.lrn || student.student_id;
        const gradeSection = student.class + (student.section ? ' - ' + student.section : '');
        const parentName = student.parent_name || '—';
        const parentPhone = student.parent_phone || '—';
        const parentEmail = student.parent_email || '—';
        const address = student.address || '—';
        const schoolName = '<?php echo e(config('school_name', 'School Name')); ?>';
        const schoolYear = '<?php echo $activeSchoolYear ? e($activeSchoolYear['name']) : date('Y') . '-' . (date('Y') + 1); ?>';
        
        return `
            <div class="id-card-wrapper col-span-1">
                <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                    <!-- Front -->
                    <div style="width: 204px; height: 324px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #CE1126 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <!-- Header with School Name -->
                        <div style="background: rgba(206,17,38,0.9); padding: 10px 12px; text-align: center;">
                            <h3 style="font-size: 11px; font-weight: bold; color: #FCD116; text-transform: uppercase; letter-spacing: 0.5px;">${schoolName}</h3>
                        </div>
                        
                        <div style="padding: 12px; display: flex; flex-direction: column; height: calc(100% - 42px);">
                            <!-- Photo and Logo Row -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <!-- Student Photo -->
                                <div style="width: 80px; height: 90px; border: 2px solid #CE1126; border-radius: 6px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 40px; height: 40px; color: #CE1126;" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                </div>
                                <!-- School Logo -->
                                <div style="width: 70px; height: 70px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 9px; color: rgba(255,255,255,0.6); text-align: center;">LOGO</span>
                                </div>
                            </div>
                            
                            <!-- Student Info -->
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                <!-- ID Number -->
                                <div style="margin-bottom: 8px;">
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin-bottom: 2px;">ID No.</p>
                                    <p style="font-size: 13px; font-weight: bold; color: #FCD116; font-family: monospace;">${lrn}</p>
                                </div>
                                
                                <!-- Student Name -->
                                <div style="margin-bottom: 8px;">
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin-bottom: 2px;">Student Name</p>
                                    <p style="font-size: 12px; font-weight: bold; color: white;">${name}</p>
                                </div>
                                
                                <!-- Grade & Section -->
                                <div>
                                    <p style="font-size: 9px; color: rgba(255,255,255,0.7); margin-bottom: 2px;">Grade & Section</p>
                                    <p style="font-size: 12px; font-weight: 600; color: #FCD116;">${gradeSection}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Back -->
                    <div style="width: 204px; height: 324px; background: linear-gradient(135deg, #CE1126 0%, #1a1a1a 50%, #0038A8 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <div style="padding: 16px; display: flex; flex-direction: column; height: 100%; align-items: center;">
                            <!-- QR Code -->
                            <div style="background: white; border-radius: 8px; padding: 10px; margin-bottom: 8px;">
                                <div id="qr-${index}" style="width: 100px; height: 100px;"></div>
                            </div>
                            
                            <!-- School Year -->
                            <div style="text-align: center; margin-bottom: 16px;">
                                <p style="font-size: 10px; color: rgba(255,255,255,0.7);">S.Y.</p>
                                <p style="font-size: 12px; font-weight: bold; color: #FCD116;">${schoolYear}</p>
                            </div>
                            
                            <!-- Contact Person Section -->
                            <div style="width: 100%; flex: 1; background: rgba(255,255,255,0.1); border-radius: 8px; padding: 10px;">
                                <div style="text-align: center; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #FCD116;">
                                    <p style="font-size: 10px; font-weight: bold; color: #FCD116; text-transform: uppercase;">Contact Person</p>
                                </div>
                                
                                <!-- Contact Info Lines -->
                                <div style="padding: 0 4px;">
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 10px; font-weight: 600; color: white;">${parentName}</p>
                                    </div>
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 10px; color: rgba(255,255,255,0.9);">${parentPhone}</p>
                                    </div>
                                    <div style="margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 9px; color: rgba(255,255,255,0.9); word-break: break-all;">${parentEmail}</p>
                                    </div>
                                    <div style="border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 4px;">
                                        <p style="font-size: 9px; color: rgba(255,255,255,0.9);">${address}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-center text-xs text-gray-500 mt-2">${student.first_name} ${student.last_name}</p>
            </div>
        `;
    }
    
    // Close modal
    function closeBulkModal() {
        document.getElementById('bulkIdModal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    
    // Print all cards
    function printAllCards() {
        const container = document.getElementById('cardsGrid');
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Student ID Cards</title>
                <style>
                    @media print {
                        @page { size: A4; margin: 10mm; }
                        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    }
                    body { font-family: Arial, sans-serif; }
                    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                    .id-card-wrapper { page-break-inside: avoid; }
                </style>
            </head>
            <body>
                <div class="grid">${container.innerHTML}</div>
            </body>
            </html>
        `);
        printWindow.document.close();
        setTimeout(() => printWindow.print(), 500);
    }
    
    // Close on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBulkModal();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
