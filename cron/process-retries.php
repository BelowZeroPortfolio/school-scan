<?php
/**
 * Retry Queue Processor
 * Cron script to process failed operations in retry queue
 * 
 * Schedule this script to run every 5-10 minutes:
 * (crontab) 5-10 minutes interval: php /path/to/attendance-system/cron/process-retries.php
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Set working directory to project root
chdir(dirname(__DIR__));

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/retry.php';
require_once __DIR__ . '/../includes/notifications.php';

// Log script start
echo "[" . date('Y-m-d H:i:s') . "] Starting retry queue processor\n";
logInfo('Retry queue processor started');

try {
    // Process retry queue
    $stats = processRetryQueue();
    
    // Output results
    echo "[" . date('Y-m-d H:i:s') . "] Retry queue processing completed\n";
    echo "  Processed: " . $stats['processed'] . "\n";
    echo "  Succeeded: " . $stats['succeeded'] . "\n";
    echo "  Failed: " . $stats['failed'] . "\n";
    
    if (!empty($stats['errors'])) {
        echo "  Errors:\n";
        foreach ($stats['errors'] as $error) {
            echo "    - Queue ID " . $error['queue_id'] . ": " . $error['error'] . "\n";
        }
    }
    
    // Clean up old completed items (older than 30 days)
    $cleaned = cleanupRetryQueue(30);
    if ($cleaned > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaned up $cleaned old retry queue items\n";
    }
    
    // Clean up old logs (older than 90 days)
    if (function_exists('deleteOldLogs')) {
        $deletedLogs = deleteOldLogs(90);
        if ($deletedLogs > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Cleaned up $deletedLogs old log entries\n";
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Retry queue processor finished successfully\n";
    exit(0);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    logError('Retry queue processor failed: ' . $e->getMessage());
    exit(1);
}
