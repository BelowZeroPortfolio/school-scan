<?php
/**
 * Students List Page
 * Display all students with pagination and search
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require authentication
requireAuth();

// Get search and pagination parameters
$search = sanitizeString($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query with search
$whereClause = '';
$params = [];

if ($search) {
    $whereClause = "WHERE (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR class LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM students " . $whereClause;
$countResult = dbFetchOne($countSql, $params);
$totalStudents = $countResult['total'];
$totalPages = ceil($totalStudents / $perPage);

// Get students for current page
$sql = "SELECT id, student_id, first_name, last_name, class, section, 
               parent_phone, parent_email, is_active, created_at
        FROM students 
        " . $whereClause . "
        ORDER BY last_name, first_name
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$students = dbFetchAll($sql, $params);

$pageTitle = 'Students';
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
                        <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">Students</h1>
                        <p class="text-gray-500 mt-1">Manage student records and information</p>
                    </div>
                    <?php if (hasAnyRole(['admin', 'operator'])): ?>
                        <a href="<?php echo config('app_url'); ?>/pages/student-add.php" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Student
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php echo displayFlash(); ?>
            
            <!-- Search Form -->
            <div class="mb-6">
                <form method="GET" action="" class="flex gap-3">
                    <div class="flex-1 relative">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search by ID, name, or class..." 
                            value="<?php echo e($search); ?>"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                        >
                        <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Search
                    </button>
                    <?php if ($search): ?>
                        <a href="<?php echo config('app_url'); ?>/pages/students.php" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Students Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">
                                            <?php echo $search ? 'No students found matching your search.' : 'No students found. Add your first student!'; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-gray-900"><?php echo e($student['student_id']); ?></span>
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
                                            <span class="text-sm text-gray-600"><?php echo e($student['class'] . ($student['section'] ? ' - ' . $student['section'] : '')); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($student['parent_phone']): ?>
                                                <div class="text-sm text-gray-600"><?php echo e($student['parent_phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($student['parent_email']): ?>
                                                <div class="text-xs text-gray-500"><?php echo e($student['parent_email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($student['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-green-50 text-green-700 border-green-200">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-red-50 text-red-700 border-red-200">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="<?php echo config('app_url'); ?>/pages/student-view.php?id=<?php echo $student['id']; ?>" class="text-violet-600 hover:text-violet-700 mr-3">View</a>
                                            <?php if (hasAnyRole(['admin', 'operator'])): ?>
                                                <a href="<?php echo config('app_url'); ?>/pages/student-edit.php?id=<?php echo $student['id']; ?>" class="text-gray-600 hover:text-gray-700">Edit</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination & Summary -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalStudents); ?> of <?php echo $totalStudents; ?> students
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="flex gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
