<?php
/**
 * Generate ID Cards Page
 * Bulk generation of student ID cards with filtering
 * Admin/Operator: Full access
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/classes.php';
require_once __DIR__ . '/../includes/schoolyear.php';

// Require authentication and admin/operator role only
requireAnyRole(['admin', 'operator']);

$currentUser = getCurrentUser();
$isTeacher = isTeacher();
$activeSchoolYear = getActiveSchoolYear();

// Get school settings (logo and name) from database
$schoolSettings = [];
$settingsSql = "SELECT setting_key, setting_value FROM school_settings WHERE setting_key IN ('school_name', 'school_logo')";
$settingsResult = dbFetchAll($settingsSql);
foreach ($settingsResult as $setting) {
    $schoolSettings[$setting['setting_key']] = $setting['setting_value'];
}
$schoolName = $schoolSettings['school_name'] ?? config('school_name', 'School Name');
$schoolLogo = $schoolSettings['school_logo'] ?? '';
$schoolLogoUrl = $schoolLogo ? config('app_url') . '/' . $schoolLogo : '';

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
    $gradesSql = "SELECT DISTINCT c.grade_level AS class FROM classes c JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1 WHERE c.is_active = 1 ORDER BY c.grade_level";
    $grades = dbFetchAll($gradesSql);
    if ($grade) {
        $sectionsSql = "SELECT DISTINCT c.section FROM classes c JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1 WHERE c.is_active = 1 AND c.grade_level = ? ORDER BY c.section";
        $sections = dbFetchAll($sectionsSql, [$grade]);
    } else {
        $sectionsSql = "SELECT DISTINCT c.section FROM classes c JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1 WHERE c.is_active = 1 ORDER BY c.section";
        $sections = dbFetchAll($sectionsSql);
    }
    $gradeSectionsMap = [];
    foreach ($grades as $g) {
        $sectionsForGrade = dbFetchAll("SELECT DISTINCT c.section FROM classes c JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1 WHERE c.is_active = 1 AND c.grade_level = ? ORDER BY c.section", [$g['class']]);
        $gradeSectionsMap[$g['class']] = array_column($sectionsForGrade, 'section');
    }
}

// Build query based on filters
$students = [];
if ($isTeacher) {
    if (!empty($teacherStudentIds) && ($classId || $search)) {
        $whereConditions = ['s.is_active = 1', 's.id IN (' . implode(',', array_map('intval', $teacherStudentIds)) . ')'];
        $params = [];
        if ($classId) {
            $whereConditions[] = "sc.class_id = ?";
            $params[] = $classId;
        }
        if ($search) {
            $whereConditions[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        $sql = "SELECT DISTINCT s.id, s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level AS class, c.section, s.barcode_path, s.photo_path, s.parent_name, s.parent_phone, s.parent_email, s.address FROM students s JOIN student_classes sc ON s.id = sc.student_id JOIN classes c ON sc.class_id = c.id WHERE " . implode(' AND ', $whereConditions) . " AND sc.is_active = 1 ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
        $students = dbFetchAll($sql, $params);
    }
} else {
    if ($grade || $section || $search) {
        $whereConditions = ['s.is_active = 1', 'sc.is_active = 1', 'c.is_active = 1'];
        $params = [];
        if ($grade) { $whereConditions[] = "c.grade_level = ?"; $params[] = $grade; }
        if ($section) { $whereConditions[] = "c.section = ?"; $params[] = $section; }
        if ($search) {
            $whereConditions[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        $sql = "SELECT DISTINCT s.id, s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level AS class, c.section, s.barcode_path, s.photo_path, s.parent_name, s.parent_phone, s.parent_email, s.address FROM students s JOIN student_classes sc ON s.id = sc.student_id JOIN classes c ON sc.class_id = c.id JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1 WHERE " . implode(' AND ', $whereConditions) . " ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
        $students = dbFetchAll($sql, $params);
    }
}
$pageTitle = 'Generate ID Cards';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full" :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Generate ID Cards</h1>
                    <p class="text-gray-500 mt-1">Select students to generate ID cards in bulk</p>
                </div>
                <button type="button" id="generateBtn" onclick="generateSelectedIDs()" disabled class="inline-flex items-center px-4 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
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
                <div class="md:col-span-2">
                    <label for="class_id" class="block text-sm font-medium text-gray-600 mb-1">Select Class</label>
                    <select name="class_id" id="class_id" onchange="debounceFilter()" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                        <option value="">Select a class...</option>
                        <?php foreach ($teacherClasses as $tc): ?>
                        <option value="<?php echo $tc['id']; ?>" <?php echo $classId == $tc['id'] ? 'selected' : ''; ?>><?php echo e($tc['grade_level'] . ' - ' . $tc['section']); ?> (<?php echo $tc['student_count']; ?> students)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div>
                    <label for="grade" class="block text-sm font-medium text-gray-600 mb-1">Grade Level</label>
                    <select name="grade" id="grade" onchange="onGradeChange()" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                        <option value="">All Grades</option>
                        <?php foreach ($grades as $g): ?>
                        <option value="<?php echo e($g['class']); ?>" <?php echo $grade === $g['class'] ? 'selected' : ''; ?>><?php echo e($g['class']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="section" class="block text-sm font-medium text-gray-600 mb-1">Section</label>
                    <select name="section" id="section" onchange="debounceFilter()" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?php echo e($s['section']); ?>" <?php echo $section === $s['section'] ? 'selected' : ''; ?>><?php echo e($s['section']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-600 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo e($search); ?>" placeholder="Name, LRN, or ID..." oninput="debounceFilter()" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">Apply Filter</button>
                    <a href="<?php echo config('app_url'); ?>/pages/generate-id.php" class="px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Clear</a>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <?php if (empty($students) && ($grade || $section || $search)): ?>
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <p class="mt-2 text-sm text-gray-500">No students found matching your filters.</p>
            </div>
            <?php elseif (empty($students)): ?>
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
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
            <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
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
                                <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" data-student="<?php echo htmlspecialchars(json_encode($student), ENT_QUOTES, 'UTF-8'); ?>" onchange="updateSelectedCount()" class="student-checkbox h-4 w-4 text-violet-600 focus:ring-violet-500 border-gray-300 rounded">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-sm"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></div>
                                    <div class="ml-3"><p class="text-sm font-medium text-gray-900"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></p></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><span class="text-sm font-mono text-gray-600"><?php echo e($student['lrn'] ?? $student['student_id']); ?></span></td>
                            <td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-gray-600"><?php echo e($student['class'] . ($student['section'] ? ' - ' . $student['section'] : '')); ?></span></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($student['barcode_path']): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Ready</span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">No barcode</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"><a href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $student['id']; ?>" class="text-violet-600 hover:text-violet-700">View</a></td>
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
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print All
                    </button>
                    <button onclick="closeBulkModal()" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div id="bulkIdContainer" class="p-6 overflow-auto max-h-[75vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="cardsGrid"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
let debounceTimer = null;
const gradeSectionsMap = <?php echo json_encode($gradeSectionsMap); ?>;
const currentSection = '<?php echo e($section); ?>';
const schoolName = '<?php echo e($schoolName); ?>';
const schoolYear = '<?php echo $activeSchoolYear ? e($activeSchoolYear['name']) : date('Y') . '-' . (date('Y') + 1); ?>';
const schoolLogoUrl = '<?php echo e($schoolLogoUrl); ?>';
const appUrl = '<?php echo config('app_url'); ?>';

function updateSections() {
    const gradeSelect = document.getElementById('grade');
    const sectionSelect = document.getElementById('section');
    if (!gradeSelect || !sectionSelect) return;
    const selectedGrade = gradeSelect.value;
    sectionSelect.innerHTML = '<option value="">All Sections</option>';
    if (selectedGrade && gradeSectionsMap[selectedGrade]) {
        gradeSectionsMap[selectedGrade].forEach(section => {
            const option = document.createElement('option');
            option.value = section;
            option.textContent = section;
            if (section === currentSection) option.selected = true;
            sectionSelect.appendChild(option);
        });
    } else {
        const allSections = [...new Set(Object.values(gradeSectionsMap).flat())].sort();
        allSections.forEach(section => {
            const option = document.createElement('option');
            option.value = section;
            option.textContent = section;
            if (section === currentSection) option.selected = true;
            sectionSelect.appendChild(option);
        });
    }
}

function onGradeChange() {
    updateSections();
    document.getElementById('section').value = '';
    debounceFilter();
}

function debounceFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { document.getElementById('filterForm').submit(); }, 500);
}

document.addEventListener('DOMContentLoaded', updateSections);

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('generateBtn').disabled = count === 0;
    const allCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
        selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
    }
}

function generateSelectedIDs() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    if (checkboxes.length === 0) return;
    const cardsGrid = document.getElementById('cardsGrid');
    cardsGrid.innerHTML = '';
    document.getElementById('modalCount').textContent = checkboxes.length;
    document.getElementById('bulkIdModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    checkboxes.forEach((cb, index) => {
        const student = JSON.parse(cb.dataset.student);
        const cardHtml = createIDCardHTML(student, index);
        cardsGrid.insertAdjacentHTML('beforeend', cardHtml);
        setTimeout(() => {
            const qrContainer = document.getElementById('qr-' + index);
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: student.lrn || student.student_id,
                    width: 85,
                    height: 85,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }, 100);
    });
}

function createIDCardHTML(student, index) {
    const name = (student.first_name + ' ' + student.last_name).toUpperCase();
    const lrn = student.lrn || student.student_id;
    const gradeSection = student.class + (student.section ? ' - ' + student.section : '');
    const parentName = student.parent_name || '—';
    const parentPhone = student.parent_phone || '—';
    const parentEmail = student.parent_email || '—';
    const address = student.address || '—';
    
    // Student photo - use uploaded photo or placeholder
    const studentPhotoUrl = student.photo_path ? appUrl + '/' + student.photo_path : '';
    const studentPhotoHtml = studentPhotoUrl 
        ? `<img src="${studentPhotoUrl}" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">`
        : `<div style="width: 100%; height: 100%; background: #1a2a3a; display: flex; align-items: center; justify-content: center;"><svg style="width: 40px; height: 40px; color: #3a4a5a;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;
    
    // School logo - use uploaded logo or placeholder
    const schoolLogoHtml = schoolLogoUrl 
        ? `<img src="${schoolLogoUrl}" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">`
        : `<div style="width: 100%; height: 100%; background: linear-gradient(180deg, #4a8ac4 0%, #2a5a8a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #F4D35E;"><span style="font-size: 7px; color: white; font-weight: bold;">LOGO</span></div>`;

    // CR80 Portrait: 204px x 324px - Dark Navy Blue Theme
    return `
        <div class="id-card-wrapper col-span-1">
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <!-- FRONT SIDE -->
                <div style="width: 204px; height: 324px; background: #0D1B2A; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.4); display: flex; flex-direction: column; font-family: 'Segoe UI', Arial, sans-serif;">
                    <!-- Header -->
                    <div style="background: #0D1B2A; padding: 12px 10px 8px; text-align: center;">
                        <h3 style="font-size: 13px; font-weight: bold; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.25; margin: 0;">${schoolName}</h3>
                    </div>
                    
                    <!-- Gradient Accent Line: Red → Navy → Yellow -->
                    <div style="height: 4px; background: linear-gradient(to right, #D62828 0%, #D62828 30%, #0D1B2A 50%, #F4D35E 70%, #F4D35E 100%);"></div>
                    
                    <!-- Photo and Logo Section -->
                    <div style="padding: 10px 12px; display: flex; justify-content: space-between; align-items: flex-start; background: #0D1B2A;">
                        <!-- Student Photo with white border -->
                        <div style="width: 78px; height: 98px; border: 3px solid #FFFFFF; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            ${studentPhotoHtml}
                        </div>
                        
                        <!-- Logo and School Year -->
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 58px; height: 58px; border-radius: 50%; overflow: hidden; background: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                                ${schoolLogoHtml}
                            </div>
                            <div style="text-align: center; margin-top: 8px;">
                                <p style="font-size: 8px; color: rgba(255,255,255,0.7); margin: 0;">S.Y.</p>
                                <p style="font-size: 11px; font-weight: bold; color: #FFFFFF; margin: 0;">${schoolYear}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Info Section - White Background -->
                    <div style="flex: 1; background: #FFFFFF; padding: 10px 12px; text-align: center;">
                        <!-- LRN -->
                        <div style="margin-bottom: 6px;">
                            <p style="font-size: 8px; color: #333333; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 2px 0;">LRN</p>
                            <p style="font-size: 15px; font-weight: bold; color: #0D1B2A; font-family: 'Courier New', monospace; margin: 0;">${lrn}</p>
                        </div>
                        
                        <!-- Student Name -->
                        <div style="margin-bottom: 6px;">
                            <p style="font-size: 8px; color: #333333; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 2px 0;">Student Name</p>
                            <p style="font-size: 12px; font-weight: bold; color: #0D1B2A; margin: 0;">${name}</p>
                        </div>
                        
                        <!-- Grade & Section -->
                        <div>
                            <p style="font-size: 8px; color: #333333; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 2px 0;">Grade & Section</p>
                            <p style="font-size: 13px; font-weight: bold; color: #0D1B2A; margin: 0;">${gradeSection}</p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #0D1B2A; padding: 8px 10px; text-align: center;">
                        <p style="font-size: 8px; font-weight: bold; color: #FFFFFF; text-transform: uppercase; letter-spacing: 1.5px; margin: 0;">Student Identification Card</p>
                    </div>
                </div>
                
                <!-- BACK SIDE -->
                <div style="width: 204px; height: 324px; background: #0D1B2A; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.4); display: flex; flex-direction: column; font-family: 'Segoe UI', Arial, sans-serif;">
                    <!-- Header -->
                    <div style="background: #0D1B2A; padding: 12px 10px 8px; text-align: center;">
                        <h3 style="font-size: 14px; font-weight: bold; color: #FFFFFF; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Emergency Contact</h3>
                    </div>
                    
                    <!-- Gradient Accent Line: Red → Navy → Yellow -->
                    <div style="height: 4px; background: linear-gradient(to right, #D62828 0%, #D62828 30%, #0D1B2A 50%, #F4D35E 70%, #F4D35E 100%);"></div>
                    
                    <!-- Main Content - White Background -->
                    <div style="flex: 1; background: #FFFFFF; margin: 8px; border-radius: 8px; display: flex; flex-direction: column; align-items: center; padding: 10px;">
                        <!-- QR Code -->
                        <div style="background: #f5f5f5; border-radius: 8px; padding: 8px; margin-bottom: 6px; border: 2px solid #0D1B2A;">
                            <div id="qr-${index}" style="width: 85px; height: 85px;"></div>
                        </div>
                        <p style="font-size: 9px; color: #333333; margin: 0 0 8px 0;">Scan to verify student</p>
                        
                        <!-- In Case of Emergency Section -->
                        <div style="width: 100%; text-align: center;">
                            <div style="border-bottom: 1px solid #0D1B2A; padding-bottom: 2px; margin-bottom: 4px;">
                                <p style="font-size: 8px; font-weight: bold; color: #0D1B2A; text-transform: uppercase; letter-spacing: 0.3px; margin: 0;">In Case of Emergency</p>
                            </div>
                            
                            <!-- Guardian -->
                            <div style="margin-bottom: 3px;">
                                <p style="font-size: 6px; color: #333333; text-transform: uppercase; margin: 0;">Guardian</p>
                                <p style="font-size: 9px; font-weight: bold; color: #0D1B2A; margin: 0;">${parentName}</p>
                            </div>
                            
                            <!-- Contact Number -->
                            <div style="margin-bottom: 3px;">
                                <p style="font-size: 6px; color: #333333; text-transform: uppercase; margin: 0;">Contact Number</p>
                                <p style="font-size: 10px; font-weight: bold; color: #D62828; margin: 0;">${parentPhone}</p>
                            </div>
                            
                            <!-- Email -->
                            <div style="margin-bottom: 3px;">
                                <p style="font-size: 6px; color: #333333; text-transform: uppercase; margin: 0;">Email</p>
                                <p style="font-size: 7px; color: #333333; margin: 0; word-break: break-all;">${parentEmail}</p>
                            </div>
                            
                            <!-- Address -->
                            <div>
                                <p style="font-size: 7px; color: #333333; margin: 0; line-height: 1.2;">${address}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #0D1B2A; padding: 8px 10px; text-align: center;">
                        <p style="font-size: 7px; color: #FFFFFF; margin: 0;">If found, please return to school administration.</p>
                    </div>
                </div>
            </div>
            <p class="text-center text-xs text-gray-500 mt-2">${student.first_name} ${student.last_name}</p>
        </div>
    `;
}

function closeBulkModal() {
    document.getElementById('bulkIdModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function printAllCards() {
    const container = document.getElementById('cardsGrid');
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student ID Cards</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                @media print {
                    @page { size: A4; margin: 8mm; }
                    body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
                    .no-print { display: none !important; }
                }
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; }
                .print-grid { 
                    display: flex; 
                    flex-wrap: wrap; 
                    justify-content: flex-start;
                    gap: 8mm;
                    padding: 5mm;
                }
                .id-card-wrapper { 
                    page-break-inside: avoid; 
                    break-inside: avoid;
                    margin-bottom: 5mm;
                }
                .id-card-wrapper > div:first-child {
                    display: flex;
                    flex-direction: row;
                    gap: 5mm;
                }
                .id-card-wrapper p.text-center { display: none; }
            </style>
        </head>
        <body>
            <div class="print-grid">${container.innerHTML}</div>
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBulkModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
