<?php
/**
 * Attendance Management Functions
 * Handle attendance recording, querying, and statistics
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Find student by barcode value (LRN)
 * 
 * @param string $barcode Barcode value (LRN or student_id for backwards compatibility)
 * @return array|null Student data or null if not found
 */
function findStudentByBarcode($barcode) {
    try {
        // Search by LRN first (primary), then fall back to student_id for backwards compatibility
        $sql = "SELECT id, student_id, lrn, first_name, last_name, class, section, 
                       parent_name, parent_phone, parent_email, is_active
                FROM students 
                WHERE (lrn = ? OR student_id = ?) AND is_active = 1";
        
        return dbFetchOne($sql, [$barcode, $barcode]);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to find student by barcode: ' . $e->getMessage(), [
                'barcode' => $barcode
            ]);
        }
        return null;
    }
}

/**
 * Check if student already has attendance record for today
 * 
 * @param int $studentId Student ID
 * @return bool True if already scanned today
 */
function hasAttendanceToday($studentId) {
    try {
        $sql = "SELECT COUNT(*) as count 
                FROM attendance 
                WHERE student_id = ? AND attendance_date = CURDATE()";
        
        $result = dbFetchOne($sql, [$studentId]);
        return ($result['count'] ?? 0) > 0;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to check attendance: ' . $e->getMessage(), [
                'student_id' => $studentId
            ]);
        }
        return false;
    }
}

/**
 * Record attendance for student
 * 
 * @param int $studentId Student ID
 * @param string $status Attendance status (present, late, absent)
 * @return bool True on success, false on failure
 */
function recordAttendance($studentId, $status = 'present') {
    try {
        // Check if already scanned today
        if (hasAttendanceToday($studentId)) {
            return false;
        }
        
        // Get current user ID if available
        $recordedBy = null;
        if (function_exists('getCurrentUser')) {
            $user = getCurrentUser();
            $recordedBy = $user['id'] ?? null;
        }
        
        // Insert attendance record
        $sql = "INSERT INTO attendance (student_id, attendance_date, check_in_time, status, recorded_by)
                VALUES (?, CURDATE(), NOW(), ?, ?)";
        
        dbInsert($sql, [$studentId, $status, $recordedBy]);
        
        // Log successful attendance
        if (function_exists('logInfo')) {
            logInfo('Attendance recorded', [
                'student_id' => $studentId,
                'status' => $status,
                'recorded_by' => $recordedBy
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to record attendance: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'status' => $status
            ]);
        }
        return false;
    }
}

/**
 * Get attendance records by date
 * 
 * @param string $date Date in Y-m-d format
 * @return array Array of attendance records
 */
function getAttendanceByDate($date) {
    try {
        $sql = "SELECT a.*, 
                       s.student_id, s.first_name, s.last_name, s.class, s.section,
                       u.full_name as recorded_by_name
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.id
                LEFT JOIN users u ON a.recorded_by = u.id
                WHERE a.attendance_date = ?
                ORDER BY a.check_in_time DESC";
        
        return dbFetchAll($sql, [$date]);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get attendance by date: ' . $e->getMessage(), [
                'date' => $date
            ]);
        }
        return [];
    }
}

/**
 * Get attendance records for student in date range
 * 
 * @param int $studentId Student ID
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @return array Array of attendance records
 */
function getStudentAttendance($studentId, $startDate, $endDate) {
    try {
        $sql = "SELECT a.*, u.full_name as recorded_by_name
                FROM attendance a
                LEFT JOIN users u ON a.recorded_by = u.id
                WHERE a.student_id = ? 
                  AND a.attendance_date BETWEEN ? AND ?
                ORDER BY a.attendance_date DESC, a.check_in_time DESC";
        
        return dbFetchAll($sql, [$studentId, $startDate, $endDate]);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get student attendance: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }
        return [];
    }
}

/**
 * Get attendance statistics for date range
 * 
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @return array Statistics array with counts and percentages
 */
function getAttendanceStats($startDate, $endDate) {
    try {
        // Get total active students
        $totalSql = "SELECT COUNT(*) as total FROM students WHERE is_active = 1";
        $totalResult = dbFetchOne($totalSql);
        $totalStudents = $totalResult['total'] ?? 0;
        
        // Get attendance counts by status
        $sql = "SELECT status, COUNT(*) as count
                FROM attendance
                WHERE attendance_date BETWEEN ? AND ?
                GROUP BY status";
        
        $results = dbFetchAll($sql, [$startDate, $endDate]);
        
        $stats = [
            'total_students' => $totalStudents,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'percentage' => 0
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        
        // Calculate attendance percentage
        $totalAttendance = $stats['present'] + $stats['late'];
        if ($totalStudents > 0) {
            $stats['percentage'] = round(($totalAttendance / $totalStudents) * 100, 2);
        }
        
        return $stats;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get attendance stats: ' . $e->getMessage(), [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }
        return [
            'total_students' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'percentage' => 0
        ];
    }
}

/**
 * Get today's attendance statistics
 * 
 * @return array Statistics for current day
 */
function getTodayAttendanceStats() {
    $today = date('Y-m-d');
    return getAttendanceStats($today, $today);
}

/**
 * Get recent attendance records with pagination
 * 
 * @param int $page Page number (1-based)
 * @param int $perPage Records per page
 * @return array Array with 'records' and 'total' keys
 */
function getRecentAttendance($page = 1, $perPage = 50) {
    try {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM attendance";
        $countResult = dbFetchOne($countSql);
        $total = $countResult['total'] ?? 0;
        
        // Get records
        $sql = "SELECT a.*, 
                       s.student_id, s.first_name, s.last_name, s.class, s.section,
                       u.full_name as recorded_by_name
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.id
                LEFT JOIN users u ON a.recorded_by = u.id
                ORDER BY a.attendance_date DESC, a.check_in_time DESC
                LIMIT ? OFFSET ?";
        
        $records = dbFetchAll($sql, [$perPage, $offset]);
        
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get recent attendance: ' . $e->getMessage());
        }
        return [
            'records' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0
        ];
    }
}

/**
 * Process barcode scan and record attendance
 * Returns result array with success status and message
 * 
 * @param string $barcode Scanned barcode value
 * @return array Result array
 */
function processBarcodeScan($barcode) {
    // Find student by barcode
    $student = findStudentByBarcode($barcode);
    
    if (!$student) {
        return [
            'success' => false,
            'error' => [
                'code' => 'STUDENT_NOT_FOUND',
                'message' => 'Student not found. Please check the barcode.'
            ]
        ];
    }
    
    // Check if already scanned today
    if (hasAttendanceToday($student['id'])) {
        return [
            'success' => false,
            'error' => [
                'code' => 'DUPLICATE_SCAN',
                'message' => 'Attendance already recorded for ' . $student['first_name'] . ' ' . $student['last_name'] . ' today.'
            ]
        ];
    }
    
    // Record attendance
    $recorded = recordAttendance($student['id'], 'present');
    
    if (!$recorded) {
        return [
            'success' => false,
            'error' => [
                'code' => 'RECORD_FAILED',
                'message' => 'Failed to record attendance. Please try again.'
            ]
        ];
    }
    
    // Send notification if function exists
    $notificationResult = null;
    if (function_exists('sendAttendanceNotification')) {
        $notificationResult = sendAttendanceNotificationWithStatus($student);
    }
    
    return [
        'success' => true,
        'student' => $student,
        'message' => 'Attendance recorded for ' . $student['first_name'] . ' ' . $student['last_name'],
        'notification' => $notificationResult
    ];
}
