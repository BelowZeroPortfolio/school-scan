<?php
/**
 * Report Generation Functions
 * Generate and filter attendance reports with statistics
 * 
 * Requirements: 7.3, 9.3, 10.3
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/schoolyear.php';
require_once __DIR__ . '/classes.php';

/**
 * Generate report data with filters
 * 
 * @param array $filters Filter criteria
 *   - start_date: Start date (Y-m-d)
 *   - end_date: End date (Y-m-d)
 *   - student_id: Specific student ID (optional)
 *   - status: Attendance status filter (optional)
 *   - school_year_id: School year ID filter (optional, defaults to active)
 *   - class_id: Class ID filter (optional)
 *   - teacher_id: Teacher ID filter - shows only students in teacher's classes (optional)
 * @return array Report data
 * 
 * Requirements: 7.3, 9.3, 10.3
 */
function generateReport($filters = []) {
    try {
        $params = [];
        $where = [];
        $joins = [];
        
        // Date range is required
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        
        $where[] = "a.attendance_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        
        // School year filter (Requirements: 9.3)
        $schoolYearId = $filters['school_year_id'] ?? null;
        if ($schoolYearId === null) {
            // Default to active school year
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        if ($schoolYearId) {
            $where[] = "a.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        // Optional filters
        if (!empty($filters['student_id'])) {
            $where[] = "s.id = ?";
            $params[] = $filters['student_id'];
        }
        
        // Class ID filter (new class-based model) - Requirements: 7.3, 10.3
        if (!empty($filters['class_id'])) {
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $where[] = "sc.class_id = ?";
            $params[] = $filters['class_id'];
        }
        // Teacher ID filter - shows only students in teacher's classes (Requirements: 7.3)
        elseif (!empty($filters['teacher_id'])) {
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $where[] = "c.teacher_id = ?";
            $params[] = $filters['teacher_id'];
            
            // Also filter by school year for teacher's classes
            if ($schoolYearId) {
                $where[] = "c.school_year_id = ?";
                $params[] = $schoolYearId;
            }
        }
        
        if (!empty($filters['status'])) {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }
        
        // Only active students
        $where[] = "s.is_active = 1";
        
        $whereClause = implode(" AND ", $where);
        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        
        // Build query with optional class info
        $classSelect = "";
        $classJoin = "";
        if (!empty($filters['class_id']) || !empty($filters['teacher_id'])) {
            $classSelect = ", c.grade_level AS class_grade, c.section AS class_section, 
                            t.full_name AS teacher_name, sy.name AS school_year_name";
            $classJoin = "LEFT JOIN users t ON c.teacher_id = t.id
                          LEFT JOIN school_years sy ON c.school_year_id = sy.id";
        }
        
        // Always join with classes to get class info
        if (empty($filters['class_id']) && empty($filters['teacher_id'])) {
            $joins[] = "LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "LEFT JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $classSelect = ", c.grade_level AS class_grade, c.section AS class_section, 
                            t.full_name AS teacher_name, sy.name AS school_year_name";
            $classJoin = "LEFT JOIN users t ON c.teacher_id = t.id
                          LEFT JOIN school_years sy ON c.school_year_id = sy.id";
            $joinClause = implode(" ", $joins);
        }
        
        $sql = "SELECT 
                    a.id,
                    a.attendance_date,
                    a.check_in_time,
                    a.check_out_time,
                    a.status,
                    a.school_year_id,
                    s.id as student_id,
                    s.student_id as student_number,
                    s.first_name,
                    s.last_name,
                    COALESCE(c.grade_level, '') AS class,
                    COALESCE(c.section, '') AS section,
                    u.full_name as recorded_by
                    $classSelect
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.id
                LEFT JOIN users u ON a.recorded_by = u.id
                $joinClause
                $classJoin
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
 *   - start_date: Start date
 *   - end_date: End date
 *   - class: Legacy class filter
 *   - section: Legacy section filter
 *   - school_year_id: School year ID filter
 *   - class_id: Class ID filter
 *   - teacher_id: Teacher ID filter
 * @return array Statistics array
 * 
 * Requirements: 7.3, 9.3, 10.3
 */
function calculateReportStats($data, $filters = []) {
    try {
        // Get school year info for stats
        $schoolYearId = $filters['school_year_id'] ?? null;
        $schoolYearName = null;
        
        if ($schoolYearId) {
            $schoolYear = getSchoolYearById($schoolYearId);
            $schoolYearName = $schoolYear['name'] ?? null;
        } else {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
                $schoolYearName = $activeSchoolYear['name'];
            }
        }
        
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
            ],
            'school_year_id' => $schoolYearId,
            'school_year_name' => $schoolYearName,
            'class_id' => $filters['class_id'] ?? null,
            'teacher_id' => $filters['teacher_id'] ?? null
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
        
        // Get total active students for context based on filters
        $totalParams = [];
        
        // If filtering by class_id or teacher_id, count students in those classes
        if (!empty($filters['class_id'])) {
            // Count students in specific class
            $totalStudentsSql = "SELECT COUNT(DISTINCT sc.student_id) as total 
                                 FROM student_classes sc
                                 JOIN students s ON sc.student_id = s.id
                                 WHERE sc.class_id = ? AND sc.is_active = 1 AND s.is_active = 1";
            $totalParams[] = $filters['class_id'];
            
            // Get class info for stats
            $classInfo = getClassById($filters['class_id']);
            if ($classInfo) {
                $stats['class_name'] = $classInfo['grade_level'] . ' - ' . $classInfo['section'];
                $stats['teacher_name'] = $classInfo['teacher_name'] ?? null;
            }
        } elseif (!empty($filters['teacher_id'])) {
            // Count students in teacher's classes for the school year
            $totalStudentsSql = "SELECT COUNT(DISTINCT sc.student_id) as total 
                                 FROM student_classes sc
                                 JOIN classes c ON sc.class_id = c.id
                                 JOIN students s ON sc.student_id = s.id
                                 WHERE c.teacher_id = ? AND c.is_active = 1 
                                       AND sc.is_active = 1 AND s.is_active = 1";
            $totalParams[] = $filters['teacher_id'];
            
            if ($schoolYearId) {
                $totalStudentsSql .= " AND c.school_year_id = ?";
                $totalParams[] = $schoolYearId;
            }
        } else {
            // Count students by class/section using classes table
            $totalWhere = ["s.is_active = 1"];
            $totalJoins = [];
            
            if (!empty($filters['class']) || !empty($filters['section'])) {
                $totalJoins[] = "JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
                $totalJoins[] = "JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
                $totalJoins[] = "JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1";
                
                if (!empty($filters['class'])) {
                    $totalWhere[] = "c.grade_level = ?";
                    $totalParams[] = $filters['class'];
                }
                
                if (!empty($filters['section'])) {
                    $totalWhere[] = "c.section = ?";
                    $totalParams[] = $filters['section'];
                }
            }
            
            $joinClause = !empty($totalJoins) ? implode(" ", $totalJoins) : "";
            $totalStudentsSql = "SELECT COUNT(DISTINCT s.id) as total FROM students s $joinClause WHERE " . implode(" AND ", $totalWhere);
        }
        
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
            'total_students' => 0,
            'school_year_id' => null,
            'school_year_name' => null
        ];
    }
}

/**
 * Get available classes (grade levels) for filtering
 * Now uses classes table instead of students table
 * 
 * @return array Array of unique grade levels
 */
function getAvailableClasses() {
    try {
        $sql = "SELECT DISTINCT c.grade_level AS class 
                FROM classes c
                JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1
                WHERE c.is_active = 1
                ORDER BY c.grade_level";
        
        $results = dbFetchAll($sql);
        return array_column($results, 'class');
    } catch (Exception $e) {
        logError('Failed to get available classes: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get available sections for a grade level
 * Now uses classes table instead of students table
 * 
 * @param string|null $gradeLevel Grade level (optional)
 * @return array Array of unique sections
 */
function getAvailableSections($gradeLevel = null) {
    try {
        $params = [];
        $where = ["c.is_active = 1"];
        
        // Only active school year
        $where[] = "sy.is_active = 1";
        
        if ($gradeLevel !== null) {
            $where[] = "c.grade_level = ?";
            $params[] = $gradeLevel;
        }
        
        $sql = "SELECT DISTINCT c.section 
                FROM classes c
                JOIN school_years sy ON c.school_year_id = sy.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY c.section";
        
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
 * @param array $filters Additional filters
 *   - class: Legacy class filter
 *   - section: Legacy section filter
 *   - school_year_id: School year ID filter
 *   - class_id: Class ID filter
 *   - teacher_id: Teacher ID filter
 * @return array Array of student summaries
 * 
 * Requirements: 7.3, 9.3, 10.3
 */
function getStudentAttendanceSummary($startDate, $endDate, $filters = []) {
    try {
        $params = [];
        $where = ["s.is_active = 1"];
        $joins = [];
        $groupBy = "s.id, s.student_id, s.first_name, s.last_name";
        $selectExtra = "";
        
        // School year filter
        $schoolYearId = $filters['school_year_id'] ?? null;
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        // Build attendance join with school year filter
        $attendanceCondition = "s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        
        if ($schoolYearId) {
            $attendanceCondition .= " AND a.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        // Class ID filter (new class-based model)
        if (!empty($filters['class_id'])) {
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $joins[] = "LEFT JOIN users t ON c.teacher_id = t.id";
            $where[] = "sc.class_id = ?";
            $params[] = $filters['class_id'];
            $selectExtra = ", c.grade_level AS class_grade, c.section AS class_section, t.full_name AS teacher_name";
            $groupBy .= ", c.grade_level, c.section, t.full_name";
        }
        // Teacher ID filter
        elseif (!empty($filters['teacher_id'])) {
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $joins[] = "LEFT JOIN users t ON c.teacher_id = t.id";
            $where[] = "c.teacher_id = ?";
            $params[] = $filters['teacher_id'];
            
            if ($schoolYearId) {
                $where[] = "c.school_year_id = ?";
                $params[] = $schoolYearId;
            }
            $selectExtra = ", c.grade_level AS class_grade, c.section AS class_section, t.full_name AS teacher_name";
            $groupBy .= ", c.grade_level, c.section, t.full_name";
        }
        // Class/section filters using classes table
        else {
            if (!empty($filters['class']) || !empty($filters['section'])) {
                $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
                $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
                $joins[] = "INNER JOIN school_years sy ON c.school_year_id = sy.id AND sy.is_active = 1";
                $joins[] = "LEFT JOIN users t ON c.teacher_id = t.id";
                $selectExtra = ", c.grade_level AS class_grade, c.section AS class_section, t.full_name AS teacher_name";
                $groupBy = "s.id, s.student_id, s.first_name, s.last_name, c.grade_level, c.section, t.full_name";
                
                if (!empty($filters['class'])) {
                    $where[] = "c.grade_level = ?";
                    $params[] = $filters['class'];
                }
                
                if (!empty($filters['section'])) {
                    $where[] = "c.section = ?";
                    $params[] = $filters['section'];
                }
            }
        }
        
        $whereClause = implode(" AND ", $where);
        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        
        $sql = "SELECT 
                    s.id,
                    s.student_id as student_number,
                    s.first_name,
                    s.last_name,
                    COALESCE(c.grade_level, '') AS class,
                    COALESCE(c.section, '') AS section
                    $selectExtra,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(a.id) as total_records,
                    ROUND((COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                           NULLIF(COUNT(a.id), 0)) * 100, 2) as attendance_percentage
                FROM students s
                $joinClause
                LEFT JOIN attendance a ON $attendanceCondition
                WHERE $whereClause
                GROUP BY $groupBy
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
 * @param array $filters Additional filters
 *   - school_year_id: School year ID filter
 *   - class_id: Class ID filter
 *   - teacher_id: Teacher ID filter
 * @return array Array of daily summaries
 * 
 * Requirements: 7.3, 9.3, 10.3
 */
function getDailyAttendanceSummary($startDate, $endDate, $filters = []) {
    try {
        $params = [$startDate, $endDate];
        $where = ["a.attendance_date BETWEEN ? AND ?"];
        $joins = [];
        
        // School year filter
        $schoolYearId = $filters['school_year_id'] ?? null;
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        if ($schoolYearId) {
            $where[] = "a.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        // Class ID filter
        if (!empty($filters['class_id'])) {
            $joins[] = "INNER JOIN students s ON a.student_id = s.id AND s.is_active = 1";
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $where[] = "sc.class_id = ?";
            $params[] = $filters['class_id'];
        }
        // Teacher ID filter
        elseif (!empty($filters['teacher_id'])) {
            $joins[] = "INNER JOIN students s ON a.student_id = s.id AND s.is_active = 1";
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $where[] = "c.teacher_id = ?";
            $params[] = $filters['teacher_id'];
            
            if ($schoolYearId) {
                $where[] = "c.school_year_id = ?";
                $params[] = $schoolYearId;
            }
        }
        
        $whereClause = implode(" AND ", $where);
        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        
        $sql = "SELECT 
                    a.attendance_date,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(*) as total_records
                FROM attendance a
                $joinClause
                WHERE $whereClause
                GROUP BY a.attendance_date
                ORDER BY a.attendance_date ASC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        logError('Failed to get daily attendance summary: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get school year name for report headers
 * 
 * @param int|null $schoolYearId School year ID (null = active)
 * @return string School year name or empty string
 */
function getReportSchoolYearName($schoolYearId = null) {
    if ($schoolYearId) {
        $schoolYear = getSchoolYearById($schoolYearId);
        return $schoolYear['name'] ?? '';
    }
    
    $activeSchoolYear = getActiveSchoolYear();
    return $activeSchoolYear['name'] ?? '';
}

/**
 * Build export filename with school year
 * 
 * @param string $baseFilename Base filename (e.g., 'attendance_report')
 * @param int|null $schoolYearId School year ID (null = active)
 * @return string Filename with school year included
 * 
 * Requirements: 8.3
 */
function buildExportFilename($baseFilename, $schoolYearId = null) {
    $schoolYearName = getReportSchoolYearName($schoolYearId);
    
    if ($schoolYearName) {
        // Replace dash with underscore for filename compatibility
        $schoolYearForFilename = str_replace('-', '_', $schoolYearName);
        return $baseFilename . '_SY' . $schoolYearForFilename;
    }
    
    return $baseFilename;
}


/**
 * Get class attendance comparison for charts
 * 
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @param int|null $schoolYearId School year ID filter
 * @param int|null $teacherId Teacher ID filter (for teachers to see only their classes)
 * @return array Array of class attendance data
 */
function getClassAttendanceComparison($startDate, $endDate, $schoolYearId = null, $teacherId = null) {
    try {
        $params = [$startDate, $endDate];
        $where = ["a.attendance_date BETWEEN ? AND ?"];
        
        // School year filter
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        if ($schoolYearId) {
            $where[] = "c.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        // Teacher filter
        if ($teacherId) {
            $where[] = "c.teacher_id = ?";
            $params[] = $teacherId;
        }
        
        $whereClause = implode(" AND ", $where);
        
        $sql = "SELECT 
                    c.id as class_id,
                    CONCAT(c.grade_level, ' - ', c.section) as class_name,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(a.id) as total_records,
                    ROUND((COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                           NULLIF(COUNT(a.id), 0)) * 100, 1) as attendance_rate
                FROM classes c
                INNER JOIN student_classes sc ON c.id = sc.class_id AND sc.is_active = 1
                INNER JOIN students s ON sc.student_id = s.id AND s.is_active = 1
                LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
                WHERE c.is_active = 1 AND $whereClause
                GROUP BY c.id, c.grade_level, c.section
                HAVING total_records > 0
                ORDER BY attendance_rate DESC";
        
        // Add date params again for the LEFT JOIN
        array_unshift($params, $endDate);
        array_unshift($params, $startDate);
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        logError('Failed to get class attendance comparison: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get weekly attendance trend for charts
 * 
 * @param int $weeks Number of weeks to include
 * @param int|null $schoolYearId School year ID filter
 * @param int|null $teacherId Teacher ID filter
 * @return array Array of weekly attendance data
 */
function getWeeklyAttendanceTrend($weeks = 8, $schoolYearId = null, $teacherId = null) {
    try {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$weeks} weeks"));
        
        $params = [$startDate, $endDate];
        $where = ["a.attendance_date BETWEEN ? AND ?"];
        $joins = [];
        
        // School year filter
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        if ($schoolYearId) {
            $where[] = "a.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        // Teacher filter
        if ($teacherId) {
            $joins[] = "INNER JOIN students s ON a.student_id = s.id AND s.is_active = 1";
            $joins[] = "INNER JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1";
            $joins[] = "INNER JOIN classes c ON sc.class_id = c.id AND c.is_active = 1";
            $where[] = "c.teacher_id = ?";
            $params[] = $teacherId;
        }
        
        $whereClause = implode(" AND ", $where);
        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        
        $sql = "SELECT 
                    YEARWEEK(a.attendance_date, 1) as year_week,
                    MIN(a.attendance_date) as week_start,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(*) as total_records,
                    ROUND((COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                           NULLIF(COUNT(*), 0)) * 100, 1) as attendance_rate
                FROM attendance a
                $joinClause
                WHERE $whereClause
                GROUP BY YEARWEEK(a.attendance_date, 1)
                ORDER BY year_week ASC";
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        logError('Failed to get weekly attendance trend: ' . $e->getMessage());
        return [];
    }
}


/**
 * Get teacher performance report for Principal dashboard
 * Shows attendance statistics per teacher
 * 
 * @param int|null $schoolYearId School year ID filter
 * @param string|null $startDate Start date (defaults to 30 days ago)
 * @param string|null $endDate End date (defaults to today)
 * @return array Array of teacher performance data
 */
function getTeacherPerformanceReport($schoolYearId = null, $startDate = null, $endDate = null) {
    try {
        // Default date range: last 30 days
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');
        
        // Get school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $params = [$startDate, $endDate];
        $schoolYearCondition = "";
        
        if ($schoolYearId) {
            $schoolYearCondition = "AND c.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        $sql = "SELECT 
                    u.id as teacher_id,
                    u.full_name as teacher_name,
                    u.email as teacher_email,
                    COUNT(DISTINCT c.id) as class_count,
                    COUNT(DISTINCT sc.student_id) as total_students,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(a.id) as total_records,
                    ROUND(
                        (COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / 
                        NULLIF(COUNT(a.id), 0)) * 100, 1
                    ) as attendance_rate,
                    GROUP_CONCAT(DISTINCT CONCAT(c.grade_level, '-', c.section) SEPARATOR ', ') as classes
                FROM users u
                INNER JOIN classes c ON u.id = c.teacher_id AND c.is_active = 1 $schoolYearCondition
                INNER JOIN student_classes sc ON c.id = sc.class_id AND sc.is_active = 1
                INNER JOIN students s ON sc.student_id = s.id AND s.is_active = 1
                LEFT JOIN attendance a ON s.id = a.student_id 
                    AND a.attendance_date BETWEEN ? AND ?
                    " . ($schoolYearId ? "AND a.school_year_id = ?" : "") . "
                WHERE u.role = 'teacher' AND u.is_active = 1
                GROUP BY u.id, u.full_name, u.email
                ORDER BY attendance_rate DESC, teacher_name ASC";
        
        if ($schoolYearId) {
            $params[] = $schoolYearId;
        }
        
        return dbFetchAll($sql, $params);
    } catch (Exception $e) {
        logError('Failed to get teacher performance report: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get teacher attendance summary (quick stats)
 * 
 * @param int|null $schoolYearId School year ID filter
 * @return array Summary statistics
 */
function getTeacherAttendanceSummary($schoolYearId = null) {
    try {
        // Get school year
        if ($schoolYearId === null) {
            $activeSchoolYear = getActiveSchoolYear();
            if ($activeSchoolYear) {
                $schoolYearId = $activeSchoolYear['id'];
            }
        }
        
        $params = [];
        $schoolYearCondition = "";
        
        if ($schoolYearId) {
            $schoolYearCondition = "AND c.school_year_id = ?";
            $params[] = $schoolYearId;
        }
        
        $sql = "SELECT 
                    COUNT(DISTINCT u.id) as total_teachers,
                    COUNT(DISTINCT c.id) as total_classes,
                    COUNT(DISTINCT sc.student_id) as total_students
                FROM users u
                INNER JOIN classes c ON u.id = c.teacher_id AND c.is_active = 1 $schoolYearCondition
                LEFT JOIN student_classes sc ON c.id = sc.class_id AND sc.is_active = 1
                LEFT JOIN students s ON sc.student_id = s.id AND s.is_active = 1
                WHERE u.role = 'teacher' AND u.is_active = 1";
        
        $result = dbFetchOne($sql, $params);
        
        return [
            'total_teachers' => $result['total_teachers'] ?? 0,
            'total_classes' => $result['total_classes'] ?? 0,
            'total_students' => $result['total_students'] ?? 0
        ];
    } catch (Exception $e) {
        logError('Failed to get teacher attendance summary: ' . $e->getMessage());
        return [
            'total_teachers' => 0,
            'total_classes' => 0,
            'total_students' => 0
        ];
    }
}


/**
 * Get teacher login activity for today
 * Shows which teachers have logged in today
 * 
 * @return array Array with active and inactive teachers
 */
function getTeacherLoginActivityToday() {
    try {
        $today = date('Y-m-d');
        
        // Get all active teachers with their login status for today
        $sql = "SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.last_login,
                    CASE 
                        WHEN DATE(u.last_login) = ? THEN 1 
                        ELSE 0 
                    END as logged_in_today,
                    TIME(u.last_login) as login_time
                FROM users u
                WHERE u.role = 'teacher' AND u.is_active = 1
                ORDER BY logged_in_today DESC, u.last_login DESC, u.full_name ASC";
        
        $teachers = dbFetchAll($sql, [$today]);
        
        $activeToday = [];
        $inactiveToday = [];
        
        foreach ($teachers as $teacher) {
            if ($teacher['logged_in_today']) {
                $activeToday[] = $teacher;
            } else {
                $inactiveToday[] = $teacher;
            }
        }
        
        return [
            'active_today' => $activeToday,
            'inactive_today' => $inactiveToday,
            'total_teachers' => count($teachers),
            'active_count' => count($activeToday),
            'inactive_count' => count($inactiveToday),
            'activity_rate' => count($teachers) > 0 ? round((count($activeToday) / count($teachers)) * 100, 1) : 0
        ];
    } catch (Exception $e) {
        logError('Failed to get teacher login activity: ' . $e->getMessage());
        return [
            'active_today' => [],
            'inactive_today' => [],
            'total_teachers' => 0,
            'active_count' => 0,
            'inactive_count' => 0,
            'activity_rate' => 0
        ];
    }
}

/**
 * Get teacher login activity for the past N days (for chart)
 * 
 * @param int $days Number of days to include
 * @return array Array of daily login counts
 */
function getTeacherLoginTrend($days = 7) {
    try {
        $result = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayName = date('D', strtotime($date));
            
            $sql = "SELECT COUNT(DISTINCT id) as count 
                    FROM users 
                    WHERE role = 'teacher' 
                    AND is_active = 1 
                    AND DATE(last_login) = ?";
            
            $row = dbFetchOne($sql, [$date]);
            
            $result[] = [
                'date' => $date,
                'day' => $dayName,
                'count' => $row['count'] ?? 0
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        logError('Failed to get teacher login trend: ' . $e->getMessage());
        return [];
    }
}
