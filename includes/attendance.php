<?php
/**
 * Attendance Management Functions
 * Handle attendance recording, querying, and statistics
 * 
 * Requirements: 7.1, 7.2, 7.4, 9.1, 9.2
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/schoolyear.php';
require_once __DIR__ . '/classes.php';

/**
 * Find student by barcode value (LRN)
 * Includes current class info from enrollment
 * 
 * @param string $barcode Barcode value (LRN or student_id for backwards compatibility)
 * @return array|null Student data or null if not found
 */
function findStudentByBarcode($barcode) {
    try {
        // Search by LRN first (primary), then fall back to student_id for backwards compatibility
        // Join with student_classes and classes to get current class info
        $sql = "SELECT s.id, s.student_id, s.lrn, s.first_name, s.last_name,
                       s.parent_name, s.parent_phone, s.parent_email, s.is_active,
                       c.grade_level AS class, c.section
                FROM students s
                LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1
                LEFT JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                WHERE (s.lrn = ? OR s.student_id = ?) AND s.is_active = 1
                LIMIT 1";
        
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
 * Associates attendance with the active school year
 * 
 * @param int $studentId Student ID
 * @param string $status Attendance status (present, late, absent)
 * @return bool True on success, false on failure
 * 
 * Requirements: 7.4
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
        
        // Get active school year ID
        $schoolYearId = null;
        $activeSchoolYear = getActiveSchoolYear();
        if ($activeSchoolYear) {
            $schoolYearId = $activeSchoolYear['id'];
        }
        
        // Insert attendance record with school_year_id
        $sql = "INSERT INTO attendance (student_id, attendance_date, check_in_time, status, recorded_by, school_year_id)
                VALUES (?, CURDATE(), NOW(), ?, ?, ?)";
        
        dbInsert($sql, [$studentId, $status, $recordedBy, $schoolYearId]);
        
        // Log successful attendance
        if (function_exists('logInfo')) {
            logInfo('Attendance recorded', [
                'student_id' => $studentId,
                'status' => $status,
                'recorded_by' => $recordedBy,
                'school_year_id' => $schoolYearId
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
 * Optionally filter by school year, class, or teacher
 * 
 * @param string $date Date in Y-m-d format
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param int|null $classId Class ID to filter by (optional)
 * @param int|null $teacherId Teacher ID to filter by their classes (optional)
 * @return array Array of attendance records
 * 
 * Requirements: 7.1, 7.2, 9.1, 9.2
 */
function getAttendanceByDate($date, ?int $schoolYearId = null, ?int $classId = null, ?int $teacherId = null) {
    try {
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $params = [$date];
        $whereConditions = ['a.attendance_date = ?'];
        
        // Add school year filter
        if ($schoolYearId !== null) {
            $whereConditions[] = 'a.school_year_id = ?';
            $params[] = $schoolYearId;
        }
        
        // Build JOIN clause based on filters
        $joinClause = "INNER JOIN students s ON a.student_id = s.id
                       LEFT JOIN users u ON a.recorded_by = u.id";
        
        // Add class/teacher filtering via student_classes
        if ($classId !== null || $teacherId !== null) {
            $joinClause .= " LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                             LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            
            if ($schoolYearId !== null) {
                $joinClause .= " AND c.school_year_id = " . (int)$schoolYearId;
            }
            
            if ($classId !== null) {
                $whereConditions[] = 'c.id = ?';
                $params[] = $classId;
            }
            
            if ($teacherId !== null) {
                $whereConditions[] = 'c.teacher_id = ?';
                $params[] = $teacherId;
            }
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT DISTINCT a.*, 
                       s.student_id AS student_code, s.first_name, s.last_name,
                       COALESCE(c.grade_level, '') AS class, COALESCE(c.section, '') AS section,
                       u.full_name as recorded_by_name,
                       sy.name AS school_year_name
                FROM attendance a
                {$joinClause}
                LEFT JOIN school_years sy ON a.school_year_id = sy.id
                WHERE {$whereClause}
                ORDER BY a.check_in_time DESC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get attendance by date: ' . $e->getMessage(), [
                'date' => $date,
                'school_year_id' => $schoolYearId
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
 * Optionally filter by school year, class, or teacher
 * 
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param int|null $classId Class ID to filter by (optional)
 * @param int|null $teacherId Teacher ID to filter by their classes (optional)
 * @return array Statistics array with counts and percentages
 * 
 * Requirements: 7.1, 7.2, 9.1, 9.2
 */
function getAttendanceStats($startDate, $endDate, ?int $schoolYearId = null, ?int $classId = null, ?int $teacherId = null) {
    try {
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        // Get total students based on filters
        $totalParams = [];
        $totalWhereConditions = ['s.is_active = 1'];
        $totalJoinClause = '';
        
        if ($classId !== null || $teacherId !== null || $schoolYearId !== null) {
            $totalJoinClause = "JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                                JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            
            if ($schoolYearId !== null) {
                $totalWhereConditions[] = 'c.school_year_id = ?';
                $totalParams[] = $schoolYearId;
            }
            
            if ($classId !== null) {
                $totalWhereConditions[] = 'c.id = ?';
                $totalParams[] = $classId;
            }
            
            if ($teacherId !== null) {
                $totalWhereConditions[] = 'c.teacher_id = ?';
                $totalParams[] = $teacherId;
            }
        }
        
        $totalWhereClause = implode(' AND ', $totalWhereConditions);
        $totalSql = "SELECT COUNT(DISTINCT s.id) as total FROM students s {$totalJoinClause} WHERE {$totalWhereClause}";
        $totalResult = dbFetchOne($totalSql, $totalParams);
        $totalStudents = $totalResult['total'] ?? 0;
        
        // Get attendance counts by status with filters
        $params = [$startDate, $endDate];
        $whereConditions = ['a.attendance_date BETWEEN ? AND ?'];
        $joinClause = '';
        
        if ($schoolYearId !== null) {
            $whereConditions[] = 'a.school_year_id = ?';
            $params[] = $schoolYearId;
        }
        
        if ($classId !== null || $teacherId !== null) {
            $joinClause = "JOIN students s ON a.student_id = s.id
                           JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                           JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            
            if ($schoolYearId !== null) {
                $joinClause .= " AND c.school_year_id = " . (int)$schoolYearId;
            }
            
            if ($classId !== null) {
                $whereConditions[] = 'c.id = ?';
                $params[] = $classId;
            }
            
            if ($teacherId !== null) {
                $whereConditions[] = 'c.teacher_id = ?';
                $params[] = $teacherId;
            }
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT a.status, COUNT(DISTINCT a.id) as count
                FROM attendance a
                {$joinClause}
                WHERE {$whereClause}
                GROUP BY a.status";
        
        $results = dbFetchAll($sql, $params);
        
        $stats = [
            'total_students' => $totalStudents,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'percentage' => 0,
            'school_year_id' => $schoolYearId,
            'class_id' => $classId,
            'teacher_id' => $teacherId
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
                'end_date' => $endDate,
                'school_year_id' => $schoolYearId
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
 * Optionally filter by school year, class, or teacher
 * 
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param int|null $classId Class ID to filter by (optional)
 * @param int|null $teacherId Teacher ID to filter by their classes (optional)
 * @return array Statistics for current day
 * 
 * Requirements: 7.1
 */
function getTodayAttendanceStats(?int $schoolYearId = null, ?int $classId = null, ?int $teacherId = null) {
    $today = date('Y-m-d');
    return getAttendanceStats($today, $today, $schoolYearId, $classId, $teacherId);
}

/**
 * Get recent attendance records with pagination
 * Optionally filter by school year, class, or teacher
 * 
 * @param int $page Page number (1-based)
 * @param int $perPage Records per page
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param int|null $classId Class ID to filter by (optional)
 * @param int|null $teacherId Teacher ID to filter by their classes (optional)
 * @return array Array with 'records' and 'total' keys
 * 
 * Requirements: 7.2, 9.1, 9.2
 */
function getRecentAttendance($page = 1, $perPage = 50, ?int $schoolYearId = null, ?int $classId = null, ?int $teacherId = null) {
    try {
        $offset = ($page - 1) * $perPage;
        
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $params = [];
        $whereConditions = [];
        
        // Always join with classes to get class info
        $joinClause = "INNER JOIN students s ON a.student_id = s.id
                       LEFT JOIN users u ON a.recorded_by = u.id
                       LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                       LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
        
        // Add school year filter
        if ($schoolYearId !== null) {
            $whereConditions[] = 'a.school_year_id = ?';
            $params[] = $schoolYearId;
            // Also filter classes by school year
            $joinClause .= " AND c.school_year_id = " . (int)$schoolYearId;
        }
        
        // Add class/teacher filtering
        if ($classId !== null) {
            $whereConditions[] = 'c.id = ?';
            $params[] = $classId;
        }
        
        if ($teacherId !== null) {
            $whereConditions[] = 'c.teacher_id = ?';
            $params[] = $teacherId;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count with filters
        $countSql = "SELECT COUNT(DISTINCT a.id) as total FROM attendance a {$joinClause} {$whereClause}";
        $countResult = dbFetchOne($countSql, $params);
        $total = $countResult['total'] ?? 0;
        
        // Get records with pagination
        $params[] = $perPage;
        $params[] = $offset;
        
        $sql = "SELECT DISTINCT a.*, 
                       s.student_id AS student_code, s.first_name, s.last_name,
                       COALESCE(c.grade_level, '') AS class, COALESCE(c.section, '') AS section,
                       u.full_name as recorded_by_name,
                       sy.name AS school_year_name
                FROM attendance a
                {$joinClause}
                LEFT JOIN school_years sy ON a.school_year_id = sy.id
                {$whereClause}
                ORDER BY a.attendance_date DESC, a.check_in_time DESC
                LIMIT ? OFFSET ?";
        
        $records = dbFetchAll($sql, $params);
        
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'school_year_id' => $schoolYearId,
            'class_id' => $classId,
            'teacher_id' => $teacherId
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
 * Check if student already has check-out time recorded for today
 * 
 * @param int $studentId Student ID
 * @return bool True if already checked out today
 */
function hasCheckoutToday($studentId) {
    try {
        $sql = "SELECT COUNT(*) as count 
                FROM attendance 
                WHERE student_id = ? AND attendance_date = CURDATE() AND check_out_time IS NOT NULL";
        
        $result = dbFetchOne($sql, [$studentId]);
        return ($result['count'] ?? 0) > 0;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to check checkout: ' . $e->getMessage(), [
                'student_id' => $studentId
            ]);
        }
        return false;
    }
}

/**
 * Record check-out time for student (dismissal)
 * 
 * @param int $studentId Student ID
 * @return bool True on success, false on failure
 */
function recordCheckout($studentId) {
    try {
        // Check if already checked out today
        if (hasCheckoutToday($studentId)) {
            return false;
        }
        
        // Check if there's an attendance record for today (must have arrival first)
        if (!hasAttendanceToday($studentId)) {
            return false;
        }
        
        // Update attendance record with check_out_time
        $sql = "UPDATE attendance 
                SET check_out_time = NOW() 
                WHERE student_id = ? AND attendance_date = CURDATE() AND check_out_time IS NULL";
        
        $affected = dbExecute($sql, [$studentId]);
        
        if ($affected > 0) {
            if (function_exists('logInfo')) {
                logInfo('Checkout recorded', [
                    'student_id' => $studentId
                ]);
            }
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to record checkout: ' . $e->getMessage(), [
                'student_id' => $studentId
            ]);
        }
        return false;
    }
}

/**
 * Process barcode scan and record attendance
 * Returns result array with success status and message
 * 
 * @param string $barcode Scanned barcode value
 * @param string $mode Scan mode: 'arrival' for time-in, 'dismissal' for time-out
 * @return array Result array
 */
function processBarcodeScan($barcode, $mode = 'arrival') {
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
    
    // Handle based on mode
    if ($mode === 'dismissal') {
        // Dismissal mode - record check-out time
        
        // Check if student has arrived today
        if (!hasAttendanceToday($student['id'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'NO_ARRIVAL',
                    'message' => $student['first_name'] . ' ' . $student['last_name'] . ' has no arrival record today. Please scan arrival first.'
                ]
            ];
        }
        
        // Check if already checked out
        if (hasCheckoutToday($student['id'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_CHECKOUT',
                    'message' => 'Dismissal already recorded for ' . $student['first_name'] . ' ' . $student['last_name'] . ' today.'
                ]
            ];
        }
        
        // Record checkout
        $recorded = recordCheckout($student['id']);
        
        if (!$recorded) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'RECORD_FAILED',
                    'message' => 'Failed to record dismissal. Please try again.'
                ]
            ];
        }
        
        // Send dismissal notification if function exists
        $notificationResult = null;
        if (function_exists('sendDismissalNotificationWithStatus')) {
            $notificationResult = sendDismissalNotificationWithStatus($student);
        }
        
        return [
            'success' => true,
            'student' => $student,
            'message' => 'Dismissal recorded for ' . $student['first_name'] . ' ' . $student['last_name'],
            'mode' => 'dismissal',
            'notification' => $notificationResult
        ];
    } else {
        // Arrival mode - record check-in time (default behavior)
        
        // Check if already scanned today
        if (hasAttendanceToday($student['id'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_SCAN',
                    'message' => 'Arrival already recorded for ' . $student['first_name'] . ' ' . $student['last_name'] . ' today.'
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
                    'message' => 'Failed to record arrival. Please try again.'
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
            'message' => 'Arrival recorded for ' . $student['first_name'] . ' ' . $student['last_name'],
            'mode' => 'arrival',
            'notification' => $notificationResult
        ];
    }
}

/**
 * Get attendance records for a specific school year
 * Optionally filter by class or teacher
 * 
 * @param int $schoolYearId School year ID
 * @param int|null $classId Class ID to filter by (optional)
 * @param int|null $teacherId Teacher ID to filter by their classes (optional)
 * @param string|null $startDate Start date filter (optional)
 * @param string|null $endDate End date filter (optional)
 * @return array Array of attendance records
 * 
 * Requirements: 9.1, 9.2
 */
function getAttendanceBySchoolYear(int $schoolYearId, ?int $classId = null, ?int $teacherId = null, ?string $startDate = null, ?string $endDate = null): array {
    try {
        $params = [$schoolYearId];
        $whereConditions = ['a.school_year_id = ?'];
        
        // Add date range filter
        if ($startDate !== null) {
            $whereConditions[] = 'a.attendance_date >= ?';
            $params[] = $startDate;
        }
        
        if ($endDate !== null) {
            $whereConditions[] = 'a.attendance_date <= ?';
            $params[] = $endDate;
        }
        
        // Build JOIN clause - always include classes for class info
        $joinClause = "INNER JOIN students s ON a.student_id = s.id
                       LEFT JOIN users u ON a.recorded_by = u.id
                       LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                       LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1 AND c.school_year_id = " . (int)$schoolYearId;
        
        // Add class/teacher filtering
        if ($classId !== null) {
            $whereConditions[] = 'c.id = ?';
            $params[] = $classId;
        }
        
        if ($teacherId !== null) {
            $whereConditions[] = 'c.teacher_id = ?';
            $params[] = $teacherId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT DISTINCT a.*, 
                       s.student_id AS student_code, s.lrn, s.first_name, s.last_name,
                       COALESCE(c.grade_level, '') AS class, COALESCE(c.section, '') AS section,
                       u.full_name as recorded_by_name,
                       sy.name AS school_year_name
                FROM attendance a
                {$joinClause}
                LEFT JOIN school_years sy ON a.school_year_id = sy.id
                WHERE {$whereClause}
                ORDER BY a.attendance_date DESC, a.check_in_time DESC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get attendance by school year: ' . $e->getMessage(), [
                'school_year_id' => $schoolYearId
            ]);
        }
        return [];
    }
}

/**
 * Get attendance records for a teacher's students
 * Filters by teacher's classes for the specified school year
 * 
 * @param int $teacherId Teacher user ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param string|null $startDate Start date filter (optional)
 * @param string|null $endDate End date filter (optional)
 * @return array Array of attendance records
 * 
 * Requirements: 7.1, 7.2
 */
function getTeacherAttendance(int $teacherId, ?int $schoolYearId = null, ?string $startDate = null, ?string $endDate = null): array {
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    return getAttendanceBySchoolYear($schoolYearId, null, $teacherId, $startDate, $endDate);
}

/**
 * Get attendance statistics for a teacher's students
 * 
 * @param int $teacherId Teacher user ID
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array Statistics array with counts and percentages
 * 
 * Requirements: 7.1
 */
function getTeacherAttendanceStats(int $teacherId, string $startDate, string $endDate, ?int $schoolYearId = null): array {
    return getAttendanceStats($startDate, $endDate, $schoolYearId, null, $teacherId);
}

/**
 * Get student attendance filtered by school year
 * 
 * @param int $studentId Student ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @param string|null $startDate Start date in Y-m-d format (optional)
 * @param string|null $endDate End date in Y-m-d format (optional)
 * @return array Array of attendance records
 * 
 * Requirements: 9.1, 9.2
 */
function getStudentAttendanceBySchoolYear(int $studentId, ?int $schoolYearId = null, ?string $startDate = null, ?string $endDate = null): array {
    try {
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $params = [$studentId];
        $whereConditions = ['a.student_id = ?'];
        
        if ($schoolYearId !== null) {
            $whereConditions[] = 'a.school_year_id = ?';
            $params[] = $schoolYearId;
        }
        
        if ($startDate !== null) {
            $whereConditions[] = 'a.attendance_date >= ?';
            $params[] = $startDate;
        }
        
        if ($endDate !== null) {
            $whereConditions[] = 'a.attendance_date <= ?';
            $params[] = $endDate;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT a.*, 
                       u.full_name as recorded_by_name,
                       sy.name AS school_year_name
                FROM attendance a
                LEFT JOIN users u ON a.recorded_by = u.id
                LEFT JOIN school_years sy ON a.school_year_id = sy.id
                WHERE {$whereClause}
                ORDER BY a.attendance_date DESC, a.check_in_time DESC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get student attendance by school year: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'school_year_id' => $schoolYearId
            ]);
        }
        return [];
    }
}


/**
 * Get today's attendance statistics for specific class IDs
 * Used for teacher dashboard to show stats for their classes only
 * 
 * @param array $classIds Array of class IDs
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array Statistics for current day
 * 
 * Requirements: 7.1
 */
function getTodayAttendanceStatsForClasses(array $classIds, ?int $schoolYearId = null): array {
    if (empty($classIds)) {
        return [
            'total_students' => 0,
            'present' => 0,
            'late' => 0,
            'percentage' => 0
        ];
    }
    
    try {
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $today = date('Y-m-d');
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        
        // Get total students in these classes
        $totalParams = $classIds;
        $totalSql = "SELECT COUNT(DISTINCT s.id) as total 
                     FROM students s
                     JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                     WHERE sc.class_id IN ({$placeholders}) AND s.is_active = 1";
        
        $totalResult = dbFetchOne($totalSql, $totalParams);
        $totalStudents = $totalResult['total'] ?? 0;
        
        // Get attendance counts by status for today
        $attendanceParams = array_merge([$today], $classIds);
        if ($schoolYearId !== null) {
            $attendanceParams[] = $schoolYearId;
        }
        
        $schoolYearCondition = $schoolYearId !== null ? 'AND a.school_year_id = ?' : '';
        
        $sql = "SELECT a.status, COUNT(DISTINCT a.id) as count
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                WHERE a.attendance_date = ? 
                  AND sc.class_id IN ({$placeholders})
                  {$schoolYearCondition}
                GROUP BY a.status";
        
        $results = dbFetchAll($sql, $attendanceParams);
        
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
            logError('Failed to get attendance stats for classes: ' . $e->getMessage(), [
                'class_ids' => $classIds
            ]);
        }
        return [
            'total_students' => 0,
            'present' => 0,
            'late' => 0,
            'percentage' => 0
        ];
    }
}

/**
 * Get recent attendance records for specific class IDs
 * Used for teacher dashboard to show recent attendance for their classes only
 * 
 * @param array $classIds Array of class IDs
 * @param int $page Page number (1-based)
 * @param int $perPage Records per page
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array Array with 'records' and 'total' keys
 * 
 * Requirements: 7.1, 7.2
 */
function getRecentAttendanceForClasses(array $classIds, int $page = 1, int $perPage = 50, ?int $schoolYearId = null): array {
    if (empty($classIds)) {
        return [
            'records' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0
        ];
    }
    
    try {
        $offset = ($page - 1) * $perPage;
        
        // If no school year specified, use active school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        
        // Build params for count query
        $countParams = $classIds;
        $schoolYearCondition = '';
        if ($schoolYearId !== null) {
            $schoolYearCondition = 'AND a.school_year_id = ?';
            $countParams[] = $schoolYearId;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(DISTINCT a.id) as total 
                     FROM attendance a
                     JOIN students s ON a.student_id = s.id
                     JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                     WHERE sc.class_id IN ({$placeholders}) {$schoolYearCondition}";
        
        $countResult = dbFetchOne($countSql, $countParams);
        $total = $countResult['total'] ?? 0;
        
        // Build params for records query
        $recordParams = $classIds;
        if ($schoolYearId !== null) {
            $recordParams[] = $schoolYearId;
        }
        $recordParams[] = $perPage;
        $recordParams[] = $offset;
        
        // Get records with pagination
        $sql = "SELECT DISTINCT a.*, 
                       s.student_id AS student_code, s.first_name, s.last_name,
                       c.grade_level AS class, c.section,
                       u.full_name as recorded_by_name,
                       sy.name AS school_year_name
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
                JOIN classes c ON sc.class_id = c.id
                LEFT JOIN users u ON a.recorded_by = u.id
                LEFT JOIN school_years sy ON a.school_year_id = sy.id
                WHERE sc.class_id IN ({$placeholders}) {$schoolYearCondition}
                ORDER BY a.attendance_date DESC, a.check_in_time DESC
                LIMIT ? OFFSET ?";
        
        $records = dbFetchAll($sql, $recordParams);
        
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Failed to get recent attendance for classes: ' . $e->getMessage(), [
                'class_ids' => $classIds
            ]);
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
