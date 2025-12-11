<?php
/**
 * System Logs Page
 * View system logs (admin only)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

// Require authentication and admin role
requireAuth();
requireRole('admin');

// Get filter parameters
$level = $_GET['level'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$limit = (int) ($_GET['limit'] ?? 100);

// Get logs
if (isset($_GET['date_range'])) {
    $logs = getLogsByDateRange($startDate, $endDate, $level);
} else {
    $logs = getRecentLogs($limit, $level);
}

// Get log statistics
$stats = getLogStats($startDate, $endDate);

$pageTitle = 'System Logs';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
          :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
            <!-- Page Header -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight"><?php echo e($pageTitle); ?></h1>
                <p class="text-sm sm:text-base text-gray-500 mt-1">Monitor system events and errors</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <div class="text-sm font-medium text-gray-600">Total Logs</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo e($stats['total']); ?></div>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <div class="text-sm font-medium text-gray-600">Info</div>
                    <div class="text-2xl font-bold text-blue-600 mt-1"><?php echo e($stats['info']); ?></div>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <div class="text-sm font-medium text-gray-600">Warnings</div>
                    <div class="text-2xl font-bold text-yellow-600 mt-1"><?php echo e($stats['warning']); ?></div>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <div class="text-sm font-medium text-gray-600">Errors</div>
                    <div class="text-2xl font-bold text-red-600 mt-1"><?php echo e($stats['error']); ?></div>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100">
                    <div class="text-sm font-medium text-gray-600">Critical</div>
                    <div class="text-2xl font-bold text-violet-600 mt-1"><?php echo e($stats['critical']); ?></div>
                </div>
            </div>
            
            <!-- Filter Form -->
            <div class="bg-white rounded-xl p-6 border border-gray-100 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filter Logs</h2>
                
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Log Level -->
                        <div>
                            <label for="level" class="block text-sm font-medium text-gray-700 mb-1">Log Level</label>
                            <select id="level" name="level" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                                <option value="">All Levels</option>
                                <option value="info" <?php echo $level === 'info' ? 'selected' : ''; ?>>Info</option>
                                <option value="warning" <?php echo $level === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                <option value="error" <?php echo $level === 'error' ? 'selected' : ''; ?>>Error</option>
                                <option value="critical" <?php echo $level === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <!-- Start Date -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo e($startDate); ?>" 
                                   class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                        </div>
                        
                        <!-- End Date -->
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo e($endDate); ?>" 
                                   class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                        </div>
                        
                        <!-- Limit -->
                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700 mb-1">Limit</label>
                            <select id="limit" name="limit" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                                <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                                <option value="250" <?php echo $limit === 250 ? 'selected' : ''; ?>>250</option>
                                <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" name="date_range" value="1" 
                                class="px-6 py-2 bg-violet-600 text-white rounded-xl hover:bg-violet-700 focus:ring-4 focus:ring-violet-300 transition-all font-medium">
                            Apply Filters
                        </button>
                        <a href="?" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 transition-all font-medium">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Logs Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">Log Entries</h2>
                    <p class="text-sm text-gray-500 mt-1">Showing <?php echo count($logs); ?> log entries</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            $levelColors = [
                                                'info' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'warning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                                'error' => 'bg-red-50 text-red-700 border-red-200',
                                                'critical' => 'bg-violet-50 text-violet-700 border-violet-200'
                                            ];
                                            echo $levelColors[$log['log_level']] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                            ?> border">
                                            <?php echo e(strtoupper($log['log_level'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-md">
                                            <?php echo e($log['message']); ?>
                                            <?php if (!empty($log['context'])): ?>
                                                <details class="mt-1">
                                                    <summary class="text-xs text-violet-600 cursor-pointer hover:text-violet-800">View Context</summary>
                                                    <pre class="mt-2 text-xs bg-gray-50 p-2 rounded-lg overflow-x-auto"><?php echo e(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)); ?></pre>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if (!empty($log['username'])): ?>
                                            <div class="font-medium"><?php echo e($log['full_name']); ?></div>
                                            <div class="text-gray-500"><?php echo e($log['username']); ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-400">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo e($log['ip_address'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No log entries found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
