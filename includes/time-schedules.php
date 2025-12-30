<?php
/**
 * Time Schedule Management Functions
 * Handle dynamic time in/out schedules for attendance
 */

require_once __DIR__ . '/db.php';

/**
 * Get the currently active time schedule
 * @return array|null Active schedule or null if none
 */
function getActiveTimeSchedule(): ?array {
    try {
        $today = date('Y-m-d');
        
        // Get active schedule that's effective today or has no effective date
        $sql = "SELECT * FROM time_schedules 
                WHERE is_active = 1 
                AND (effective_date IS NULL OR effective_date <= ?)
                ORDER BY effective_date DESC
                LIMIT 1";
        
        return dbFetchOne($sql, [$today]);
    } catch (Exception $e) {
        // Table might not exist yet - return null gracefully
        return null;
    }
}

/**
 * Get all time schedules
 * @return array List of all schedules
 */
function getAllTimeSchedules(): array {
    try {
        return dbFetchAll("SELECT ts.*, u.full_name as created_by_name 
                           FROM time_schedules ts
                           LEFT JOIN users u ON ts.created_by = u.id
                           ORDER BY ts.is_active DESC, ts.created_at DESC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get a single time schedule by ID
 * @param int $id Schedule ID
 * @return array|null Schedule data or null
 */
function getTimeScheduleById(int $id): ?array {
    try {
        return dbFetchOne("SELECT * FROM time_schedules WHERE id = ?", [$id]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Create a new time schedule
 * @param array $data Schedule data
 * @param int $userId User creating the schedule
 * @return int|false New schedule ID or false on failure
 */
function createTimeSchedule(array $data, int $userId) {
    try {
        $sql = "INSERT INTO time_schedules (name, time_in, time_out, late_threshold_minutes, is_active, effective_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $scheduleId = dbInsert($sql, [
            $data['name'],
            $data['time_in'],
            $data['time_out'],
            $data['late_threshold_minutes'] ?? 0,
            $data['is_active'] ?? 0,
            $data['effective_date'] ?: null,
            $userId
        ]);
        
        // If this schedule is active, deactivate others
        if ($data['is_active'] ?? false) {
            deactivateOtherSchedules($scheduleId);
        }
        
        // Log the creation
        logScheduleChange($scheduleId, 'create', $userId, null, $data);
        
        return $scheduleId;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to create time schedule: ' . $e->getMessage());
        }
        return false;
    }
}


/**
 * Update an existing time schedule
 * @param int $id Schedule ID
 * @param array $data Updated data
 * @param int $userId User making the update
 * @param string|null $reason Reason for change
 * @return bool Success status
 */
function updateTimeSchedule(int $id, array $data, int $userId, ?string $reason = null): bool {
    try {
        // Get old values for logging
        $oldSchedule = getTimeScheduleById($id);
        if (!$oldSchedule) {
            return false;
        }
        
        $sql = "UPDATE time_schedules 
                SET name = ?, time_in = ?, time_out = ?, late_threshold_minutes = ?, 
                    is_active = ?, effective_date = ?, updated_at = NOW()
                WHERE id = ?";
        
        dbExecute($sql, [
            $data['name'],
            $data['time_in'],
            $data['time_out'],
            $data['late_threshold_minutes'] ?? 0,
            $data['is_active'] ?? 0,
            $data['effective_date'] ?: null,
            $id
        ]);
        
        // If this schedule is now active, deactivate others
        if ($data['is_active'] ?? false) {
            deactivateOtherSchedules($id);
        }
        
        // Log the update
        logScheduleChange($id, 'update', $userId, $oldSchedule, $data, $reason);
        
        return true;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to update time schedule: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Delete a time schedule
 * @param int $id Schedule ID
 * @param int $userId User deleting
 * @return bool Success status
 */
function deleteTimeSchedule(int $id, int $userId): bool {
    try {
        $oldSchedule = getTimeScheduleById($id);
        if (!$oldSchedule) {
            return false;
        }
        
        // Don't allow deleting the only active schedule
        if ($oldSchedule['is_active']) {
            return false;
        }
        
        dbExecute("DELETE FROM time_schedules WHERE id = ?", [$id]);
        
        // Log the deletion
        logScheduleChange(null, 'delete', $userId, $oldSchedule, null);
        
        return true;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to delete time schedule: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Activate a specific schedule (deactivates all others)
 * @param int $id Schedule ID to activate
 * @param int $userId User making the change
 * @return bool Success status
 */
function activateTimeSchedule(int $id, int $userId): bool {
    try {
        $schedule = getTimeScheduleById($id);
        if (!$schedule) {
            return false;
        }
        
        // Deactivate all schedules first
        dbExecute("UPDATE time_schedules SET is_active = 0");
        
        // Activate the selected one
        dbExecute("UPDATE time_schedules SET is_active = 1 WHERE id = ?", [$id]);
        
        // Log the activation
        logScheduleChange($id, 'activate', $userId, ['is_active' => 0], ['is_active' => 1]);
        
        return true;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to activate time schedule: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Deactivate other schedules when one is activated
 * @param int $exceptId Schedule ID to keep active
 */
function deactivateOtherSchedules(int $exceptId): void {
    dbExecute("UPDATE time_schedules SET is_active = 0 WHERE id != ?", [$exceptId]);
}

/**
 * Log a schedule change for audit trail
 */
function logScheduleChange(?int $scheduleId, string $action, int $userId, ?array $oldValues, ?array $newValues, ?string $reason = null): void {
    try {
        $sql = "INSERT INTO time_schedule_logs (schedule_id, action, changed_by, old_values, new_values, change_reason)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        dbInsert($sql, [
            $scheduleId,
            $action,
            $userId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $reason
        ]);
    } catch (Exception $e) {
        // Silently fail - logging shouldn't break main functionality
    }
}

/**
 * Get schedule change history
 * @param int|null $scheduleId Filter by schedule ID (null for all)
 * @param int $limit Number of records
 * @return array Change logs
 */
function getScheduleChangeLogs(?int $scheduleId = null, int $limit = 50): array {
    try {
        $where = '1=1';
        $params = [];
        
        if ($scheduleId !== null) {
            $where = 'tsl.schedule_id = ?';
            $params[] = $scheduleId;
        }
        
        $params[] = $limit;
        
        $sql = "SELECT tsl.*, u.full_name as changed_by_name, ts.name as schedule_name
                FROM time_schedule_logs tsl
                LEFT JOIN users u ON tsl.changed_by = u.id
                LEFT JOIN time_schedules ts ON tsl.schedule_id = ts.id
                WHERE {$where}
                ORDER BY tsl.created_at DESC
                LIMIT ?";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Calculate if a time is late based on active schedule
 * @param string $checkTime Time to check (H:i:s or timestamp)
 * @return array ['is_late' => bool, 'schedule' => array, 'minutes_late' => int]
 */
function calculateLateStatus(string $checkTime): array {
    $schedule = getActiveTimeSchedule();
    
    if (!$schedule) {
        return ['is_late' => false, 'schedule' => null, 'minutes_late' => 0];
    }
    
    // Parse the check time - ensure we're working with just the time portion
    $checkTimeOnly = date('H:i:s', strtotime($checkTime));
    
    // Get schedule time_in (stored as TIME in database, e.g., "07:30:00")
    $scheduleTimeIn = $schedule['time_in'];
    
    // Convert to today's timestamps for comparison
    $today = date('Y-m-d');
    $checkTimestamp = strtotime($today . ' ' . $checkTimeOnly);
    $timeInTimestamp = strtotime($today . ' ' . $scheduleTimeIn);
    
    // Calculate the grace period cutoff (time_in + grace minutes)
    $graceMinutes = (int)($schedule['late_threshold_minutes'] ?? 0);
    $graceTimestamp = $timeInTimestamp + ($graceMinutes * 60);
    
    // Student is late if they scan AFTER the grace period ends
    $isLate = $checkTimestamp > $graceTimestamp;
    
    // Calculate how many minutes late (only if actually late)
    $minutesLate = 0;
    if ($isLate) {
        $minutesLate = (int)round(($checkTimestamp - $graceTimestamp) / 60);
    }
    
    return [
        'is_late' => $isLate,
        'schedule' => $schedule,
        'minutes_late' => $minutesLate,
        'time_in' => $scheduleTimeIn,
        'grace_period' => $graceMinutes,
        'check_time' => $checkTimeOnly,
        'cutoff_time' => date('H:i:s', $graceTimestamp)
    ];
}

/**
 * Get formatted time for display
 * @param string $time Time string
 * @return string Formatted time (e.g., "7:30 AM")
 */
function formatScheduleTime(string $time): string {
    return date('g:i A', strtotime($time));
}
