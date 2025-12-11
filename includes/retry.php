<?php
/**
 * Retry System Functions
 * Handle failed operation retries with exponential backoff
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

/**
 * Add operation to retry queue
 * 
 * @param string $operation Operation type (e.g., 'notification', 'export')
 * @param array $data Operation data
 * @param int $maxRetries Maximum retry attempts
 * @return int Queue ID
 */
function addToRetryQueue($operation, $data, $maxRetries = 3) {
    try {
        $dataJson = json_encode($data);
        $nextRetry = calculateNextRetry(0);
        
        $sql = "INSERT INTO retry_queue 
                (operation_type, operation_data, retry_count, max_retries, next_retry_at, status)
                VALUES (?, ?, 0, ?, ?, 'pending')";
        
        $queueId = dbInsert($sql, [$operation, $dataJson, $maxRetries, $nextRetry]);
        
        logInfo('Operation added to retry queue', [
            'queue_id' => $queueId,
            'operation' => $operation
        ]);
        
        return $queueId;
    } catch (Exception $e) {
        logError('Failed to add to retry queue: ' . $e->getMessage(), [
            'operation' => $operation,
            'data' => $data
        ]);
        return 0;
    }
}

/**
 * Calculate next retry time with exponential backoff
 * 
 * @param int $retryCount Current retry count
 * @return string Next retry timestamp (Y-m-d H:i:s format)
 */
function calculateNextRetry($retryCount) {
    // Exponential backoff: 5 minutes, 15 minutes, 45 minutes
    $delays = [
        0 => 5,      // First retry after 5 minutes
        1 => 15,     // Second retry after 15 minutes
        2 => 45,     // Third retry after 45 minutes
        3 => 120     // Fourth retry after 2 hours
    ];
    
    $delayMinutes = $delays[$retryCount] ?? 120;
    
    return date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
}

/**
 * Get operations ready for retry
 * 
 * @return array Array of retry queue items
 */
function getRetryableOperations() {
    try {
        $sql = "SELECT * FROM retry_queue
                WHERE status = 'pending'
                  AND retry_count < max_retries
                  AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                ORDER BY created_at ASC
                LIMIT 50";
        
        return dbFetchAll($sql);
    } catch (Exception $e) {
        logError('Failed to get retryable operations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark retry as processing
 * 
 * @param int $queueId Queue ID
 * @return bool True on success
 */
function markRetryProcessing($queueId) {
    try {
        $sql = "UPDATE retry_queue 
                SET status = 'processing'
                WHERE id = ?";
        
        return dbExecute($sql, [$queueId]) > 0;
    } catch (Exception $e) {
        logError('Failed to mark retry as processing: ' . $e->getMessage(), [
            'queue_id' => $queueId
        ]);
        return false;
    }
}

/**
 * Mark retry as completed
 * 
 * @param int $queueId Queue ID
 * @return bool True on success
 */
function markRetryCompleted($queueId) {
    try {
        $sql = "UPDATE retry_queue 
                SET status = 'completed', error_message = NULL
                WHERE id = ?";
        
        $result = dbExecute($sql, [$queueId]) > 0;
        
        if ($result) {
            logInfo('Retry completed successfully', ['queue_id' => $queueId]);
        }
        
        return $result;
    } catch (Exception $e) {
        logError('Failed to mark retry as completed: ' . $e->getMessage(), [
            'queue_id' => $queueId
        ]);
        return false;
    }
}

/**
 * Mark retry as failed and schedule next attempt
 * 
 * @param int $queueId Queue ID
 * @param string $error Error message
 * @return bool True on success
 */
function markRetryFailed($queueId, $error) {
    try {
        // Get current retry count
        $sql = "SELECT retry_count, max_retries FROM retry_queue WHERE id = ?";
        $item = dbFetchOne($sql, [$queueId]);
        
        if (!$item) {
            return false;
        }
        
        $newRetryCount = $item['retry_count'] + 1;
        $nextRetry = calculateNextRetry($newRetryCount);
        
        // Check if max retries reached
        if ($newRetryCount >= $item['max_retries']) {
            $updateSql = "UPDATE retry_queue 
                         SET status = 'failed', 
                             retry_count = ?,
                             error_message = ?,
                             next_retry_at = NULL
                         WHERE id = ?";
            
            dbExecute($updateSql, [$newRetryCount, $error, $queueId]);
            
            logCritical('Retry permanently failed after max attempts', [
                'queue_id' => $queueId,
                'retry_count' => $newRetryCount,
                'error' => $error
            ]);
            
            // TODO: Notify administrators
            
            return true;
        }
        
        // Schedule next retry
        $updateSql = "UPDATE retry_queue 
                     SET status = 'pending',
                         retry_count = ?,
                         error_message = ?,
                         next_retry_at = ?
                     WHERE id = ?";
        
        $result = dbExecute($updateSql, [$newRetryCount, $error, $nextRetry, $queueId]) > 0;
        
        if ($result) {
            logWarning('Retry failed, scheduled for next attempt', [
                'queue_id' => $queueId,
                'retry_count' => $newRetryCount,
                'next_retry' => $nextRetry,
                'error' => $error
            ]);
        }
        
        return $result;
    } catch (Exception $e) {
        logError('Failed to mark retry as failed: ' . $e->getMessage(), [
            'queue_id' => $queueId
        ]);
        return false;
    }
}

/**
 * Process retry queue
 * Called by cron job to retry failed operations
 * 
 * @return array Statistics about processed retries
 */
function processRetryQueue() {
    $stats = [
        'processed' => 0,
        'succeeded' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    try {
        logInfo('Starting retry queue processing');
        
        $operations = getRetryableOperations();
        
        foreach ($operations as $operation) {
            $stats['processed']++;
            
            // Mark as processing
            markRetryProcessing($operation['id']);
            
            // Decode operation data
            $data = json_decode($operation['operation_data'], true);
            
            if (!$data) {
                markRetryFailed($operation['id'], 'Invalid operation data');
                $stats['failed']++;
                continue;
            }
            
            // Process based on operation type
            $success = false;
            $error = null;
            
            try {
                switch ($operation['operation_type']) {
                    case 'notification':
                        $success = processNotificationRetry($data);
                        $error = $success ? null : 'Notification send failed';
                        break;
                        
                    case 'export':
                        $success = processExportRetry($data);
                        $error = $success ? null : 'Export generation failed';
                        break;
                        
                    default:
                        $error = 'Unknown operation type: ' . $operation['operation_type'];
                        break;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            
            // Update status
            if ($success) {
                markRetryCompleted($operation['id']);
                $stats['succeeded']++;
            } else {
                markRetryFailed($operation['id'], $error);
                $stats['failed']++;
                $stats['errors'][] = [
                    'queue_id' => $operation['id'],
                    'operation' => $operation['operation_type'],
                    'error' => $error
                ];
            }
        }
        
        logInfo('Retry queue processing completed', $stats);
        
        return $stats;
    } catch (Exception $e) {
        logError('Retry queue processing failed: ' . $e->getMessage());
        return $stats;
    }
}

/**
 * Process notification retry
 * 
 * @param array $data Notification data
 * @return bool True on success
 */
function processNotificationRetry($data) {
    if (!isset($data['notification_id'])) {
        return false;
    }
    
    // Use existing retry function from notifications.php
    if (function_exists('retryNotification')) {
        return retryNotification($data['notification_id']);
    }
    
    return false;
}

/**
 * Process export retry
 * 
 * @param array $data Export data
 * @return bool True on success
 */
function processExportRetry($data) {
    // Placeholder for export retry logic
    // Would regenerate the export based on saved parameters
    return false;
}

/**
 * Get retry queue statistics
 * 
 * @return array Statistics array
 */
function getRetryQueueStats() {
    try {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM retry_queue
                GROUP BY status";
        
        $results = dbFetchAll($sql);
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }
        
        return $stats;
    } catch (Exception $e) {
        logError('Failed to get retry queue stats: ' . $e->getMessage());
        return [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        ];
    }
}

/**
 * Clean up old completed retry queue items
 * 
 * @param int $days Delete items older than this many days
 * @return int Number of deleted items
 */
function cleanupRetryQueue($days = 30) {
    try {
        $sql = "DELETE FROM retry_queue 
                WHERE status IN ('completed', 'failed')
                  AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $deleted = dbExecute($sql, [$days]);
        
        if ($deleted > 0) {
            logInfo('Cleaned up retry queue', ['deleted' => $deleted]);
        }
        
        return $deleted;
    } catch (Exception $e) {
        logError('Failed to cleanup retry queue: ' . $e->getMessage());
        return 0;
    }
}
