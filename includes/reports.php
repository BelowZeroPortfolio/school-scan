<?php
/**
 * Report Generation Functions
 * Generate and filter attendance reports with statistics
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';

/**
 * Generate report data with filters
 * 
 * @param array $filters Filter criteria
 *   - start_date: Start date (Y-m-d)
 *   - end_date: End date (Y-m-d)
 *   - student_id: Specific student ID (optional)
 *   - class: Class filter (optional)
 *   - section: Section filter (optional)
 *   - status: Attendance status filter (optional)
 * @return array Report data
 */
function generateReport($filters = []) {
    try {
        $params = [];
        $where = [];
        
        // Date range is required
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        
        $where[] = "a.attendance_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        
        // Optional filters
        if (!empty($filters['student_id'])) {
            $where[] = "s.id = ?";
            $params[] = $filters['student_id'];
        }
        
        if (!empty($filters['class'])) {
            $where[] = "s.class = ?";
            $params[] = $filters['class'];
        }
        
        if (!empty($filters['section'])) {
            $where[] = "s.section = ?";
            $params[] = $filters['section'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }
        
        // Only active students
        $where[] = "s.is_active = 1";
        
        $whereClause = implode(" AND ", $where);
        
        $sql = "SELECT 
                    a.id,
                    a.attendance_date,
                    a.check_in_time,
                    a.status,
                    s.id as student_id,
                    s.student_id as student_number,
                    s.first_name,
                    s.last_name,
                    s.class,
                    s.section,
                    u.full_name as recorded_by
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.id
                LEFT JOIN users u ON a.recorded_by = u.id
                WHERE $whereClause
                ORDER BY a.attendance_date DESC, s.last_name ASC, s.first_name ASC";
        
        $records = dbFetchAll($sql, $params);
        
        logInfo('Report generated', [
            'filters' => $filters,
            'record_count' => count($records)
        ]);
        
        return $records;
    } catch (Exception $e) {
        logError('Failed to generate report: ' . $e->getMessage(), [
            'filters' => $filters
        ]);
        return [];
    }
}

/**
 * Calculate report statistics
 * 
 * @param array $data Report data from generateReport()
 * @param array $filters Original filters used
 * @return array Statistics array
 */
function calculateReportStats($data, $filters = []) {
    try {
        $stats = [
            'total_records' => count($data),
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'unique_students' => 0,
            'unique_dates' => 0,
            'attendance_percentage' => 0,
            'date_range' => [
                'start' => $filters['start_date'] ?? null,
                'end' => $filters['end_date'] ?? null
            ]
        ];
        
        if (empty($data)) {
            return $stats;
        }
        
        $uniqueStudents = [];
        $uniqueDates = [];
        
        foreach ($data as $record) {
            // Count by status
            $status = $record['status'];
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            
            // Track unique students and dates
            $uniqueStudents[$record['student_id']] = true;
            $uniqueDates[$record['attendance_date']] = true;
        }
        
        $stats['unique_students'] = count($uniqueStudents);
        $stats['unique_dates'] = count($uniqueDates);
        
        // Calculate attendance percentage
        $totalAttendance = $stats['present'] + $stats['late'];
        if ($stats['total_records'] > 0) {
            $stats['attendance_percentage'] = round(($totalAttendance / $stats['total_records']) * 100, 2);
        }
        
        // Get total active students for context
        $totalStudentsSql = "SELECT COUNT(*) as total FROM students WHERE is_active = 1";
        
        // Apply class/section filters if present
        $totalParams = [];
        $totalWhere = ["is_active = 1"];
        
        if (!empty($filters['class'])) {
            $totalWhere[] = "class = ?";
            $totalParams[] = $filters['class'];
        }
        
        if (!empty($filters['section'])) {
            $totalWhere[] = "section = ?";
            $totalParams[] = $filters['section'];
        }
        
        $totalStudentsSql = "SELECT COUNT(*) as total FROM students WHERE " . implode(" AND ", $totalWhere);
        $totalResult = dbFetchOne($totalStudentsSql, $totalParams);
        $stats['total_students'] = $totalResult['total'] ?? 0;
        
        return $stats;
    } catch (Exception $e) {
        logError('Failed to calculate report stats: ' . $e->getMessage());
        return [
            'total_records' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'unique_students' => 0,
            'unique_dates' => 0,
            'attendance_percentage' => 0,
            'total_students' => 0
        ];
    }
}

/**
 * Get available classes for filtering
 * 
 * @return array Array of unique classes
 */
function getAvailableClasses() {
    try {
        $sql = "SELECT DISTINCT class 
                FROM students 
                WHERE is_active = 1 AND class IS NOT NULL
                ORDER BY class";
        
        $results = dbFetchAll($sql);
        return array_column($results, 'class');
    } catch (Exception $e) {
        logError('Failed to get available classes: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get available sections for a class
 * 
 * @param string|null $class Class name (optional)
 * @return array Array of unique sections
 */
function getAvailableSections($class = null) {
    try {
        $params = [];
        $where = "is_active = 1 AND section IS NOT NULL";
        
        if ($class !== null) {
            $where .= " AND class = ?";
            $params[] = $class;
        }
        
        $sql = "SELECT DISTINCT section 
                FROM students 
                WHERE $where
                ORDER BY section";
        
        $results = dbFetchAll($sql, $params);
        return array_column($results, 'section');
    } catch (Exception $e) {
        logError('Failed to get available sections: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get student attendance summary for date range
 * 
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @param array $filters Additional filters (class, section)
 * @return array Array of student summaries
 */
function getStudentAttendanceSummary($startDate, $endDate, $filters = []) {
    try {
        $params = [$startDate, $endDate];
        $where = ["s.is_active = 1"];
        
        if (!empty($filters['class'])) {
            $where[] = "s.class = ?";
            $params[] = $filters['class'];
        }
        
        if (!empty($filters['section'])) {
            $where[] = "s.section = ?";
            $params[] = $filters['section'];
        }
        
        $whereClause = implode(" AND ", $where);
        
        $sql = "SELECT 
                    s.id,
                    s.student_id as student_number,
                    s.first_name,
                    s.last_name,
                    s.class,
                    s.section,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(a.id) as total_records,
                    ROUND((COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                           NULLIF(COUNT(a.id), 0)) * 100, 2) as attendance_percentage
                FROM students s
                LEFT JOIN attendance a ON s.id = a.student_id 
                    AND a.attendance_date BETWEEN ? AND ?
                WHERE $whereClause
                GROUP BY s.id, s.student_id, s.first_name, s.last_name, s.class, s.section
                ORDER BY s.last_name ASC, s.first_name ASC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        logError('Failed to get student attendance summary: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get daily attendance summary for date range
 * 
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return array Array of daily summaries
 */
function getDailyAttendanceSummary($startDate, $endDate) {
    try {
        $sql = "SELECT 
                    attendance_date,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                    COUNT(*) as total_records
                FROM attendance
                WHERE attendance_date BETWEEN ? AND ?
                GROUP BY attendance_date
                ORDER BY attendance_date ASC";
        
        return dbFetchAll($sql, [$startDate, $endDate]);
    } catch (Exception $e) {
        logError('Failed to get daily attendance summary: ' . $e->getMessage());
        return [];
    }
}
