<?php
/**
 * Teacher Attendance Functions
 * Two-phase attendance model:
 * 1. Login = Intent (PENDING)
 * 2. First Student Scan = Presence Confirmation (CONFIRMED/LATE)
 * 
 * Lateness evaluated against Admin time rules, not relative timestamps.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schoolyear.php';
require_once __DIR__ . '/classes.php';
require_once __DIR__ . '/time-schedules.php';

/**
 * Record teacher time in (login) - Phase 1: Intent
 * Sets status to PENDING until presence is confirmed by student scan
 */
function recordTeacherTimeIn(int $teacherId, ?int $schoolYearId = null): bool {
    try {
        if (!$schoolYearId) {
            $activeYear = getActiveSchoolYear();
            $schoolYearId = $activeYear['id'] ?? null;
        }
        
        if (!$schoolYearId) return false;
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check if record exists for today
        $existing = dbFetchOne(
            "SELECT id, time_in FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?",
            [$teacherId, $today]
        );
        
        if ($existing) {
            // Already has time_in, don't overwrite
            if ($existing['time_in']) {
                return true;
            }
            // Update existing record
            dbExecute(
                "UPDATE teacher_attendance SET time_in = ?, attendance_status = 'pending', updated_at = NOW() WHERE id = ?",
                [$now, $existing['id']]
            );
        } else {
            // Create new record with PENDING status
            dbInsert(
                "INSERT INTO teacher_attendance (teacher_id, school_year_id, attendance_date, time_in, attendance_status) 
                 VALUES (?, ?, ?, ?, 'pending')",
                [$teacherId, $schoolYearId, $today, $now]
            );
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Record teacher time out (logout)
 */
function recordTeacherTimeOut(int $teacherId): bool {
    try {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        $result = dbExecute(
            "UPDATE teacher_attendance SET time_out = ?, updated_at = NOW() WHERE teacher_id = ? AND attendance_date = ?",
            [$now, $teacherId, $today]
        );
        
        return $result > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Record first student scan - Phase 2: Presence Confirmation
 * This finalizes the teacher's attendance and evaluates lateness against Admin time rules
 */
function recordFirstStudentScan(int $teacherId, string $scanTime): bool {
    try {
        $today = date('Y-m-d');
        
        // Get or create teacher attendance record
        $existing = dbFetchOne(
            "SELECT id, time_in, first_student_scan, attendance_status FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?",
            [$teacherId, $today]
        );
        
        // Only process if no first scan recorded yet
        if ($existing && $existing['first_student_scan']) {
            return true; // Already recorded
        }
        
        // Get active time schedule for evaluation
        $schedule = getActiveTimeSchedule();
        $timeRuleId = $schedule ? $schedule['id'] : null;
        
        if ($existing) {
            // Update with first student scan
            dbExecute(
                "UPDATE teacher_attendance SET first_student_scan = ?, time_rule_id = ?, updated_at = NOW() WHERE id = ?",
                [$scanTime, $timeRuleId, $existing['id']]
            );
            
            // Now evaluate and finalize attendance
            finalizeTeacherAttendance($teacherId, $today);
        } else {
            // Teacher hasn't logged in yet but student scanned - create record
            $activeYear = getActiveSchoolYear();
            if ($activeYear) {
                dbInsert(
                    "INSERT INTO teacher_attendance (teacher_id, school_year_id, attendance_date, first_student_scan, time_rule_id, attendance_status) 
                     VALUES (?, ?, ?, ?, ?, 'pending')",
                    [$teacherId, $activeYear['id'], $today, $scanTime, $timeRuleId]
                );
                finalizeTeacherAttendance($teacherId, $today);
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Finalize teacher attendance - Evaluate lateness against Admin time rules
 * Called after first student scan is recorded
 */
function finalizeTeacherAttendance(int $teacherId, string $date): void {
    try {
        $record = dbFetchOne(
            "SELECT ta.*, ts.time_in as rule_time_in, ts.late_threshold_minutes 
             FROM teacher_attendance ta
             LEFT JOIN time_schedules ts ON ta.time_rule_id = ts.id
             WHERE ta.teacher_id = ? AND ta.attendance_date = ?",
            [$teacherId, $date]
        );
        
        if (!$record) return;
        
        // Need first_student_scan to finalize
        if (!$record['first_student_scan']) {
            return;
        }
        
        // Get time rule - use locked rule or fetch active
        $ruleTimeIn = $record['rule_time_in'];
        $graceMinutes = $record['late_threshold_minutes'] ?? 0;
        
        if (!$ruleTimeIn) {
            // Fallback to active schedule if no locked rule
            $schedule = getActiveTimeSchedule();
            if ($schedule) {
                $ruleTimeIn = $schedule['time_in'];
                $graceMinutes = $schedule['late_threshold_minutes'] ?? 0;
                // Lock the rule
                dbExecute("UPDATE teacher_attendance SET time_rule_id = ? WHERE id = ?", [$schedule['id'], $record['id']]);
            }
        }
        
        if (!$ruleTimeIn) {
            // No time rule available - mark as confirmed without late evaluation
            dbExecute(
                "UPDATE teacher_attendance SET attendance_status = 'confirmed', late_status = NULL WHERE id = ?",
                [$record['id']]
            );
            return;
        }
        
        // Calculate cutoff time (time_in + grace period)
        $cutoffTime = strtotime($date . ' ' . $ruleTimeIn) + ($graceMinutes * 60);
        
        // Evaluate lateness based on BOTH teacher login AND first student scan
        $teacherTimeIn = $record['time_in'] ? strtotime($record['time_in']) : null;
        $firstScanTime = strtotime($record['first_student_scan']);
        
        // Decision Logic:
        // LATE if: teacher_time_in > cutoff OR first_student_scan > cutoff OR teacher never logged in
        $isLate = false;
        
        if ($teacherTimeIn === null) {
            // Teacher wasn't logged in when students arrived - LATE
            $isLate = true;
        } elseif ($teacherTimeIn > $cutoffTime) {
            // Teacher logged in after cutoff - LATE
            $isLate = true;
        } elseif ($firstScanTime > $cutoffTime) {
            // First student scanned after cutoff (class started late) - LATE
            $isLate = true;
        }
        
        $attendanceStatus = $isLate ? 'late' : 'confirmed';
        $lateStatus = $isLate ? 'late' : 'on_time';
        
        dbExecute(
            "UPDATE teacher_attendance SET attendance_status = ?, late_status = ? WHERE id = ?",
            [$attendanceStatus, $lateStatus, $record['id']]
        );
        
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Get teacher attendance records with filters
 */
function getTeacherAttendanceRecords(array $filters = []): array {
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['teacher_id'])) {
        $where[] = 'ta.teacher_id = ?';
        $params[] = $filters['teacher_id'];
    }
    
    if (!empty($filters['school_year_id'])) {
        $where[] = 'ta.school_year_id = ?';
        $params[] = $filters['school_year_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $where[] = 'ta.attendance_date >= ?';
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = 'ta.attendance_date <= ?';
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['attendance_status'])) {
        $where[] = 'ta.attendance_status = ?';
        $params[] = $filters['attendance_status'];
    }
    
    if (!empty($filters['late_status'])) {
        $where[] = 'ta.late_status = ?';
        $params[] = $filters['late_status'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT ta.*, 
                   u.full_name as teacher_name,
                   u.username,
                   sy.name as school_year_name,
                   ts.name as time_rule_name,
                   ts.time_in as rule_time_in,
                   ts.late_threshold_minutes
            FROM teacher_attendance ta
            INNER JOIN users u ON ta.teacher_id = u.id
            LEFT JOIN school_years sy ON ta.school_year_id = sy.id
            LEFT JOIN time_schedules ts ON ta.time_rule_id = ts.id
            WHERE {$whereClause}
            ORDER BY ta.attendance_date DESC, u.full_name ASC";
    
    return dbFetchAll($sql, $params);
}

/**
 * Get teacher attendance summary stats
 */
function getTeacherAttendanceSummary(int $teacherId, ?int $schoolYearId = null): array {
    $where = ['teacher_id = ?'];
    $params = [$teacherId];
    
    if ($schoolYearId) {
        $where[] = 'school_year_id = ?';
        $params[] = $schoolYearId;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stats = dbFetchOne(
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN attendance_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN attendance_status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN attendance_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN late_status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN late_status = 'late' THEN 1 ELSE 0 END) as late_status_count
         FROM teacher_attendance 
         WHERE {$whereClause}",
        $params
    );
    
    return $stats ?: [
        'total_days' => 0, 
        'confirmed_count' => 0, 
        'late_count' => 0, 
        'pending_count' => 0,
        'absent_count' => 0,
        'on_time_count' => 0,
        'late_status_count' => 0
    ];
}

/**
 * Get attendance time settings from active time schedule
 */
function getAttendanceSettings(): array {
    $schedule = getActiveTimeSchedule();
    
    if ($schedule) {
        return [
            'class_start_time' => date('H:i', strtotime($schedule['time_in'])),
            'late_threshold_minutes' => $schedule['late_threshold_minutes'],
            'class_end_time' => date('H:i', strtotime($schedule['time_out'])),
            'schedule_name' => $schedule['name']
        ];
    }
    
    // Default fallback
    return [
        'class_start_time' => '07:30',
        'late_threshold_minutes' => 15,
        'class_end_time' => '17:00',
        'schedule_name' => 'Default'
    ];
}

/**
 * Mark teachers as ABSENT who didn't login by end of day
 * Should be called by a cron job at end of school day
 */
function markAbsentTeachers(?int $schoolYearId = null): int {
    try {
        if (!$schoolYearId) {
            $activeYear = getActiveSchoolYear();
            $schoolYearId = $activeYear['id'] ?? null;
        }
        
        if (!$schoolYearId) return 0;
        
        $today = date('Y-m-d');
        
        // Get all active teachers
        $teachers = dbFetchAll(
            "SELECT id FROM users WHERE role = 'teacher' AND is_active = 1"
        );
        
        $markedCount = 0;
        
        foreach ($teachers as $teacher) {
            // Check if teacher has attendance record for today
            $existing = dbFetchOne(
                "SELECT id, time_in FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?",
                [$teacher['id'], $today]
            );
            
            if (!$existing) {
                // No record - mark as absent
                dbInsert(
                    "INSERT INTO teacher_attendance (teacher_id, school_year_id, attendance_date, attendance_status) 
                     VALUES (?, ?, ?, 'absent')",
                    [$teacher['id'], $schoolYearId, $today]
                );
                $markedCount++;
            } elseif (!$existing['time_in']) {
                // Record exists but no login - mark as absent
                dbExecute(
                    "UPDATE teacher_attendance SET attendance_status = 'absent' WHERE id = ?",
                    [$existing['id']]
                );
                $markedCount++;
            }
        }
        
        return $markedCount;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark teachers with login but no student scan as NO_SCAN
 * Should be called by a cron job after class hours
 */
function markNoScanTeachers(): int {
    try {
        $today = date('Y-m-d');
        
        // Update teachers who logged in but had no student scans
        $result = dbExecute(
            "UPDATE teacher_attendance 
             SET attendance_status = 'no_scan' 
             WHERE attendance_date = ? 
               AND time_in IS NOT NULL 
               AND first_student_scan IS NULL 
               AND attendance_status = 'pending'",
            [$today]
        );
        
        return $result;
    } catch (Exception $e) {
        return 0;
    }
}
