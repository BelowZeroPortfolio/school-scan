<?php
/**
 * System Logging Functions
 * Log system events to database
 */

require_once __DIR__ . '/db.php';

/**
 * Log message to database
 * 
 * @param string $level Log level (info, warning, error, critical)
 * @param string $message Log message
 * @param array $context Additional context data
 * @return bool True on success
 */
function logMessage($level, $message, $context = []) {
    try {
        // Get user ID if available
        $userId = null;
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        
        // Get client information
        $ipAddress = getClientIp();
        $userAgent = getUserAgent();
        
        // Convert context to JSON
        $contextJson = !empty($context) ? json_encode($context) : null;
        
        // Insert log entry
        $sql = "INSERT INTO system_logs (log_level, message, context, user_id, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        dbInsert($sql, [$level, $message, $contextJson, $userId, $ipAddress, $userAgent]);
        
        return true;
    } catch (Exception $e) {
        // Silently fail - logging errors shouldn't break the application
        error_log('Failed to log message: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log info message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool True on success
 */
function logInfo($message, $context = []) {
    return logMessage('info', $message, $context);
}

/**
 * Log warning message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool True on success
 */
function logWarning($message, $context = []) {
    return logMessage('warning', $message, $context);
}

/**
 * Log error message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool True on success
 */
function logError($message, $context = []) {
    return logMessage('error', $message, $context);
}

/**
 * Log critical message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool True on success
 */
function logCritical($message, $context = []) {
    return logMessage('critical', $message, $context);
}

/**
 * Get recent logs with optional filtering
 * 
 * @param int $limit Number of logs to retrieve
 * @param string $level Filter by log level (optional)
 * @return array Array of log entries
 */
function getRecentLogs($limit = 100, $level = null) {
    try {
        $sql = "SELECT l.*, u.username, u.full_name
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id";
        
        $params = [];
        
        if ($level) {
            $sql .= " WHERE l.log_level = ?";
            $params[] = $level;
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        error_log('Failed to get recent logs: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get logs by date range
 * 
 * @param string $startDate Start date (Y-m-d format)
 * @param string $endDate End date (Y-m-d format)
 * @param string $level Filter by log level (optional)
 * @return array Array of log entries
 */
function getLogsByDateRange($startDate, $endDate, $level = null) {
    try {
        $sql = "SELECT l.*, u.username, u.full_name
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE DATE(l.created_at) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        if ($level) {
            $sql .= " AND l.log_level = ?";
            $params[] = $level;
        }
        
        $sql .= " ORDER BY l.created_at DESC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        error_log('Failed to get logs by date range: ' . $e->getMessage());
        return [];
    }
}

/**
 * Clean old logs (should be called by cron)
 * 
 * @param int $daysToKeep Number of days to keep logs
 * @return int Number of logs deleted
 */
function cleanOldLogs($daysToKeep = 90) {
    try {
        $cutoffDate = date('Y-m-d', strtotime("-$daysToKeep days"));
        
        $sql = "DELETE FROM system_logs WHERE DATE(created_at) < ?";
        return dbExecute($sql, [$cutoffDate]);
    } catch (Exception $e) {
        error_log('Failed to clean old logs: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get log statistics for a date range
 * 
 * @param string $startDate Start date (Y-m-d format)
 * @param string $endDate End date (Y-m-d format)
 * @return array Statistics array with counts by level
 */
function getLogStats($startDate, $endDate) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN log_level = 'info' THEN 1 ELSE 0 END) as info,
                    SUM(CASE WHEN log_level = 'warning' THEN 1 ELSE 0 END) as warning,
                    SUM(CASE WHEN log_level = 'error' THEN 1 ELSE 0 END) as error,
                    SUM(CASE WHEN log_level = 'critical' THEN 1 ELSE 0 END) as critical
                FROM system_logs
                WHERE DATE(created_at) BETWEEN ? AND ?";
        
        $result = dbFetchOne($sql, [$startDate, $endDate]);
        
        return [
            'total' => (int) ($result['total'] ?? 0),
            'info' => (int) ($result['info'] ?? 0),
            'warning' => (int) ($result['warning'] ?? 0),
            'error' => (int) ($result['error'] ?? 0),
            'critical' => (int) ($result['critical'] ?? 0),
        ];
    } catch (Exception $e) {
        error_log('Failed to get log stats: ' . $e->getMessage());
        return [
            'total' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'critical' => 0,
        ];
    }
}
