<?php
/**
 * Users Management Page
 * Admin-only page to manage system users (including teachers)
 */

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to allow redirects after form processing
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/classes.php';
require_once __DIR__ . '/../includes/schoolyear.php';

// Require admin role
requireRole('admin');

// Get active school year for advisory info
$activeSchoolYear = getActiveSchoolYear();

$currentUser = getCurrentUser();
$error = '';
$success = '';

// Handle form submissions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            // Create new user
            $username = sanitizeString($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $fullName = sanitizeString($_POST['full_name'] ?? '');
            $email = sanitizeString($_POST['email'] ?? '');
            $role = sanitizeString($_POST['role'] ?? 'viewer');
            
            // Validation
            if (empty($username) || empty($password) || empty($fullName)) {
                $error = 'Username, password, and full name are required.';
            } elseif (strlen($username) < 3) {
                $error = 'Username must be at least 3 characters.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (!in_array($role, ['admin', 'principal', 'teacher'])) {
                $error = 'Invalid role selected.';
            } else {
                // Check if username exists
                $existingUser = dbFetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                if ($existingUser) {
                    $error = 'Username already exists.';
                } else {
                    // Create user
                    try {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (username, password_hash, role, full_name, email, is_active) VALUES (?, ?, ?, ?, ?, 1)";
                        $userId = dbInsert($sql, [$username, $passwordHash, $role, $fullName, $email]);
                        
                        if ($userId > 0) {
                            setFlash('success', 'User "' . $fullName . '" created successfully.');
                            header('Location: ' . config('app_url') . '/pages/users.php');
                            ob_end_flush();
                            exit;
                        } else {
                            // Double-check if user was actually created
                            $createdUser = dbFetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                            if ($createdUser) {
                                setFlash('success', 'User "' . $fullName . '" created successfully.');
                                header('Location: ' . config('app_url') . '/pages/users.php');
                                ob_end_flush();
                                exit;
                            }
                            $error = 'Failed to create user. Please try again.';
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            // Toggle user active status
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId === $currentUser['id']) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $user = dbFetchOne("SELECT id, is_active, full_name FROM users WHERE id = ?", [$userId]);
                if ($user) {
                    $newStatus = $user['is_active'] ? 0 : 1;
                    dbExecute("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $userId]);
                    $statusText = $newStatus ? 'activated' : 'deactivated';
                    setFlash('success', 'User "' . $user['full_name'] . '" has been ' . $statusText . '.');
                    header('Location: ' . config('app_url') . '/pages/users.php');
                    ob_end_flush();
                    exit;
                }
            }
        } elseif ($action === 'reset_password') {
            // Reset user password
            $userId = (int)($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            
            if (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                dbExecute("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
                setFlash('success', 'Password has been reset successfully.');
                header('Location: ' . config('app_url') . '/pages/users.php');
                ob_end_flush();
                exit;
            }
        }
    }
}

// Get filter parameters
$search = sanitizeString($_GET['search'] ?? '');
$roleFilter = sanitizeString($_GET['role'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = ['1=1'];
$params = [];

if ($search) {
    $searchParam = '%' . $search . '%';
    $whereConditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($roleFilter) {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countSql = "SELECT COUNT(*) as total FROM users WHERE {$whereClause}";
$countResult = dbFetchOne($countSql, $params);
$totalUsers = $countResult['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get users with advisory info for teachers
$params[] = $perPage;
$params[] = $offset;
$sql = "SELECT id, username, full_name, email, role, is_active, created_at, last_login 
        FROM users 
        WHERE {$whereClause}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$users = dbFetchAll($sql, $params);

// Fetch advisory info for teachers
$teacherAdvisoryMap = [];
if ($activeSchoolYear) {
    foreach ($users as $user) {
        if ($user['role'] === 'teacher') {
            $advisory = getTeacherAdvisoryClass($user['id'], $activeSchoolYear['id']);
            $teacherAdvisoryMap[$user['id']] = $advisory;
        }
    }
}

$pageTitle = 'User Management';
$csrfToken = generateCsrfToken();
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
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">User Management</h1>
                    <p class="text-gray-500 mt-1">Manage system users and their roles</p>
                </div>
                <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add User
                </button>
            </div>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters Section -->
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100 mb-6">
            <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <label for="filter_search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" name="search" id="filter_search" placeholder="Search by username, name, or email..." 
                               value="<?php echo e($search); ?>"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                
                <!-- Role Filter -->
                <div>
                    <label for="filter_role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" id="filter_role" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="principal" <?php echo $roleFilter === 'principal' ? 'selected' : ''; ?>>Principal</option>
                        <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                </div>
                
                <!-- Buttons -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Filter
                    </button>
                    <a href="<?php echo config('app_url'); ?>/pages/users.php" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advisory Class</th>
                            <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">No users found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $roleColors = [
                                    'admin' => 'bg-red-50 text-red-700 border-red-200',
                                    'principal' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'teacher' => 'bg-violet-50 text-violet-700 border-violet-200'
                                ];
                                $roleColor = $roleColors[$user['role']] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                $advisory = $teacherAdvisoryMap[$user['id']] ?? null;
                                ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo e($user['full_name']); ?></p>
                                                <p class="text-xs text-gray-500">@<?php echo e($user['username']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?php echo $roleColor; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['role'] === 'teacher'): ?>
                                            <?php if ($advisory): ?>
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-lg flex items-center justify-center mr-2">
                                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900"><?php echo e($advisory['grade_level'] . ' - ' . $advisory['section']); ?></p>
                                                        <p class="text-xs text-gray-500"><?php echo (int)$advisory['student_count']; ?> student<?php echo $advisory['student_count'] != 1 ? 's' : ''; ?></p>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    No advisory
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $user['email'] ? e($user['email']) : '<span class="text-gray-400">—</span>'; ?>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : '<span class="text-gray-400">Never</span>'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['is_active']): ?>
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
                                        <button onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo e($user['full_name']); ?>')" 
                                                class="text-violet-600 hover:text-violet-700 mr-3">Reset Password</button>
                                        <?php if ($user['id'] !== $currentUser['id']): ?>
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="<?php echo $user['is_active'] ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700'; ?>"
                                                        onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                                                    <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                </div>
                <?php if ($totalPages > 1): ?>
                    <?php
                    $queryParams = [];
                    if ($search) $queryParams['search'] = $search;
                    if ($roleFilter) $queryParams['role'] = $roleFilter;
                    $queryString = http_build_query($queryParams);
                    ?>
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $queryString ? '&' . $queryString : ''; ?>" 
                               class="px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</main>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCreateModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full mx-auto">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New User</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="create_full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="full_name" id="create_full_name" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="create_username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                            <input type="text" name="username" id="create_username" required minlength="3"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="create_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="create_email"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="create_role" class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select name="role" id="create_role" required
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                <option value="teacher">Teacher</option>
                                <option value="principal">Principal</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="create_password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <input type="password" name="password" id="create_password" required minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="create_confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="create_confirm_password" required minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeResetModal()"></div>
        
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full mx-auto">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id" value="">
                
                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Reset Password</h3>
                    <p class="text-sm text-gray-500 mb-4">Reset password for <span class="font-medium" id="reset_user_name"></span></p>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeResetModal()" class="px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createUserModal').classList.add('hidden');
}

function openResetModal(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}

function closeResetModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateModal();
        closeResetModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
