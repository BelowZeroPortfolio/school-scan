<?php
/**
 * Time Schedules Management Page
 * Admin and Principal can create, edit, and manage time in/out schedules
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/time-schedules.php';

// Admin and Principal can manage schedules
requireAnyRole(['admin', 'principal']);

$currentUser = getCurrentUser();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $data = [
                'name' => sanitizeString($_POST['name'] ?? ''),
                'time_in' => $_POST['time_in'] ?? '',
                'time_out' => $_POST['time_out'] ?? '',
                'late_threshold_minutes' => (int)($_POST['late_threshold_minutes'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'effective_date' => $_POST['effective_date'] ?: null
            ];
            
            if (empty($data['name']) || empty($data['time_in']) || empty($data['time_out'])) {
                $error = 'Name, Time In, and Time Out are required.';
            } else {
                $result = createTimeSchedule($data, $currentUser['id']);
                if ($result) {
                    setFlash('success', 'Schedule "' . $data['name'] . '" created successfully.');
                    header('Location: ' . config('app_url') . '/pages/time-schedules.php');
                    exit;
                } else {
                    $error = 'Failed to create schedule. Please try again.';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['schedule_id'] ?? 0);
            $data = [
                'name' => sanitizeString($_POST['name'] ?? ''),
                'time_in' => $_POST['time_in'] ?? '',
                'time_out' => $_POST['time_out'] ?? '',
                'late_threshold_minutes' => (int)($_POST['late_threshold_minutes'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'effective_date' => $_POST['effective_date'] ?: null
            ];
            $reason = sanitizeString($_POST['change_reason'] ?? '');
            
            if (updateTimeSchedule($id, $data, $currentUser['id'], $reason)) {
                setFlash('success', 'Schedule updated successfully.');
                header('Location: ' . config('app_url') . '/pages/time-schedules.php');
                exit;
            } else {
                $error = 'Failed to update schedule.';
            }
        } elseif ($action === 'activate') {
            $id = (int)($_POST['schedule_id'] ?? 0);
            if (activateTimeSchedule($id, $currentUser['id'])) {
                setFlash('success', 'Schedule activated successfully.');
                header('Location: ' . config('app_url') . '/pages/time-schedules.php');
                exit;
            } else {
                $error = 'Failed to activate schedule.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['schedule_id'] ?? 0);
            if (deleteTimeSchedule($id, $currentUser['id'])) {
                setFlash('success', 'Schedule deleted successfully.');
                header('Location: ' . config('app_url') . '/pages/time-schedules.php');
                exit;
            } else {
                $error = 'Cannot delete active schedule. Activate another schedule first.';
            }
        }
    }
}

// Get all schedules and active schedule
$schedules = getAllTimeSchedules();
$activeSchedule = getActiveTimeSchedule();
$changeLogs = getScheduleChangeLogs(null, 20);

$pageTitle = 'Time Schedules';
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="mt-16 theme-bg-primary min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold theme-text-primary tracking-tight">Time Schedules</h1>
                    <p class="theme-text-secondary mt-1">Manage time in/out schedules for attendance</p>
                </div>
                <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Schedule
                </button>
            </div>
        </div>

        <?php echo displayFlash(); ?>
        
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <!-- Active Schedule Card -->
        <?php if ($activeSchedule): ?>
        <div class="theme-bg-card rounded-xl p-6 border-2 border-green-200 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold theme-text-primary flex items-center gap-2">
                    <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                    Active Schedule: <?php echo e($activeSchedule['name']); ?>
                </h3>
                <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">ACTIVE</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-violet-600"><?php echo formatScheduleTime($activeSchedule['time_in']); ?></div>
                    <div class="text-sm theme-text-secondary">Time In</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-violet-600"><?php echo formatScheduleTime($activeSchedule['time_out']); ?></div>
                    <div class="text-sm theme-text-secondary">Time Out</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-amber-600"><?php echo $activeSchedule['late_threshold_minutes']; ?> min</div>
                    <div class="text-sm theme-text-secondary">Grace Period</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600"><?php echo formatScheduleTime(date('H:i:s', strtotime($activeSchedule['time_in']) + ($activeSchedule['late_threshold_minutes'] * 60))); ?></div>
                    <div class="text-sm theme-text-secondary">Late After</div>
                </div>
            </div>
            <p class="text-xs theme-text-muted mt-4">
                * Students scanning after <?php echo formatScheduleTime(date('H:i:s', strtotime($activeSchedule['time_in']) + ($activeSchedule['late_threshold_minutes'] * 60))); ?> will be marked as LATE.
            </p>
        </div>
        <?php else: ?>
        <div class="theme-bg-card rounded-xl p-6 border border-amber-200 bg-amber-50 mb-6">
            <p class="text-amber-700 font-medium">No active schedule. Please create and activate a schedule.</p>
        </div>
        <?php endif; ?>

        <!-- All Schedules Table -->
        <div class="theme-bg-card rounded-xl border theme-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b theme-border">
                <h3 class="text-lg font-semibold theme-text-primary">All Schedules</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y theme-border">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Time In</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Time Out</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Grace Period</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Effective Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium theme-text-secondary uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y theme-border">
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center theme-text-secondary">
                                    No schedules found. Create one to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="font-medium theme-text-primary"><?php echo e($schedule['name']); ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-primary">
                                        <?php echo formatScheduleTime($schedule['time_in']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-primary">
                                        <?php echo formatScheduleTime($schedule['time_out']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-primary">
                                        <?php echo $schedule['late_threshold_minutes']; ?> minutes
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-secondary">
                                        <?php echo $schedule['effective_date'] ? date('M j, Y', strtotime($schedule['effective_date'])) : 'Immediate'; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($schedule['is_active']): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($schedule)); ?>)" class="text-violet-600 hover:text-violet-700 mr-2">Edit</button>
                                        <?php if (!$schedule['is_active']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" class="text-green-600 hover:text-green-700 mr-2">Activate</button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this schedule?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-700">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Change History -->
        <div class="theme-bg-card rounded-xl border theme-border overflow-hidden">
            <div class="px-6 py-4 border-b theme-border">
                <h3 class="text-lg font-semibold theme-text-primary">Change History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y theme-border">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Date/Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Schedule</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Changed By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium theme-text-secondary uppercase">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y theme-border">
                        <?php if (empty($changeLogs)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center theme-text-secondary">No change history yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($changeLogs as $log): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-secondary">
                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-primary">
                                        <?php echo e($log['schedule_name'] ?? 'Deleted'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php
                                        $actionColors = [
                                            'create' => 'bg-green-100 text-green-700',
                                            'update' => 'bg-blue-100 text-blue-700',
                                            'delete' => 'bg-red-100 text-red-700',
                                            'activate' => 'bg-violet-100 text-violet-700',
                                            'deactivate' => 'bg-gray-100 text-gray-700'
                                        ];
                                        $color = $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-700';
                                        ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $color; ?>">
                                            <?php echo ucfirst($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm theme-text-primary">
                                        <?php echo e($log['changed_by_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm theme-text-secondary">
                                        <?php if ($log['change_reason']): ?>
                                            <?php echo e($log['change_reason']); ?>
                                        <?php elseif ($log['old_values'] && $log['new_values']): ?>
                                            <?php 
                                            $old = json_decode($log['old_values'], true);
                                            $new = json_decode($log['new_values'], true);
                                            if (isset($old['time_in']) && isset($new['time_in']) && $old['time_in'] !== $new['time_in']) {
                                                echo 'Time In: ' . formatScheduleTime($old['time_in']) . ' → ' . formatScheduleTime($new['time_in']);
                                            }
                                            ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Create Schedule Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCreateModal()"></div>
        <div class="relative theme-bg-card rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full mx-auto">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="create">
                <div class="px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold theme-text-primary mb-4">Create New Schedule</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Schedule Name *</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500" placeholder="e.g., Regular Schedule">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium theme-text-secondary mb-1">Time In *</label>
                                <input type="time" name="time_in" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium theme-text-secondary mb-1">Time Out *</label>
                                <input type="time" name="time_out" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Grace Period (minutes)</label>
                            <input type="number" name="late_threshold_minutes" value="15" min="0" max="60" class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            <p class="text-xs theme-text-muted mt-1">Students arriving within this time after Time In won't be marked late.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Effective Date</label>
                            <input type="date" name="effective_date" class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            <p class="text-xs theme-text-muted mt-1">Leave empty for immediate effect.</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="create_is_active" class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                            <label for="create_is_active" class="ml-2 text-sm theme-text-primary">Set as active schedule</label>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 border theme-border rounded-xl theme-text-secondary hover:bg-gray-100 transition-colors text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 transition-colors text-sm font-medium">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
        <div class="relative theme-bg-card rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full mx-auto">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="px-6 pt-6 pb-4">
                    <h3 class="text-lg font-semibold theme-text-primary mb-4">Edit Schedule</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Schedule Name *</label>
                            <input type="text" name="name" id="edit_name" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium theme-text-secondary mb-1">Time In *</label>
                                <input type="time" name="time_in" id="edit_time_in" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium theme-text-secondary mb-1">Time Out *</label>
                                <input type="time" name="time_out" id="edit_time_out" required class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Grace Period (minutes)</label>
                            <input type="number" name="late_threshold_minutes" id="edit_late_threshold" min="0" max="60" class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Effective Date</label>
                            <input type="date" name="effective_date" id="edit_effective_date" class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_is_active" class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                            <label for="edit_is_active" class="ml-2 text-sm theme-text-primary">Set as active schedule</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium theme-text-secondary mb-1">Reason for Change</label>
                            <input type="text" name="change_reason" class="w-full px-4 py-2.5 border theme-border rounded-xl theme-bg-card theme-text-primary focus:ring-2 focus:ring-violet-500" placeholder="Optional: Why are you making this change?">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 border theme-border rounded-xl theme-text-secondary hover:bg-gray-100 transition-colors text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 transition-colors text-sm font-medium">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}
function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}
function openEditModal(schedule) {
    document.getElementById('edit_schedule_id').value = schedule.id;
    document.getElementById('edit_name').value = schedule.name;
    document.getElementById('edit_time_in').value = schedule.time_in;
    document.getElementById('edit_time_out').value = schedule.time_out;
    document.getElementById('edit_late_threshold').value = schedule.late_threshold_minutes;
    document.getElementById('edit_effective_date').value = schedule.effective_date || '';
    document.getElementById('edit_is_active').checked = schedule.is_active == 1;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
