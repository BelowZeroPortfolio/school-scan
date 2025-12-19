<?php
/**
 * Class Management Functions
 * Handle class CRUD operations, student enrollment, and teacher access control
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 5.4, 6.1, 6.4
 */

// Ensure required files are loaded
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schoolyear.php';

/**
 * Create a new class
 * 
 * @param string $gradeLevel Grade level (e.g., "Grade 7")
 * @param string $section Section name (e.g., "Section A")
 * @param int $teacherId Teacher user ID
 * @param int $schoolYearId School year ID
 * @return int|false New class ID or false on failure
 * 
 * Requirements: 3.1, 3.2, 3.4
 */
function createClass(string $gradeLevel, string $section, int $teacherId, int $schoolYearId): int|false
{
    // Validate required fields
    if (empty(trim($gradeLevel)) || empty(trim($section))) {
        return false;
    }
    
    if ($teacherId <= 0 || $schoolYearId <= 0) {
        return false;
    }
    
    // Verify teacher exists and has teacher role
    $teacherSql = "SELECT id, role FROM users WHERE id = ? AND is_active = 1";
    $teacher = dbFetchOne($teacherSql, [$teacherId]);
    
    if (!$teacher || $teacher['role'] !== 'teacher') {
        return false;
    }
    
    // Verify school year exists
    $schoolYearSql = "SELECT id FROM school_years WHERE id = ?";
    $schoolYear = dbFetchOne($schoolYearSql, [$schoolYearId]);
    
    if (!$schoolYear) {
        return false;
    }
    
    // Check for duplicate class (same grade, section, school year)
    $duplicateSql = "SELECT id FROM classes WHERE grade_level = ? AND section = ? AND school_year_id = ?";
    $existing = dbFetchOne($duplicateSql, [trim($gradeLevel), trim($section), $schoolYearId]);
    
    if ($existing) {
        return false;
    }

    
    try {
        $sql = "INSERT INTO classes (grade_level, section, teacher_id, school_year_id, is_active) 
                VALUES (?, ?, ?, ?, 1)";
        return dbInsert($sql, [trim($gradeLevel), trim($section), $teacherId, $schoolYearId]);
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to create class: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Get all classes for a school year
 * 
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array List of classes with teacher info
 * 
 * Requirements: 3.3
 */
function getClassesBySchoolYear(?int $schoolYearId = null): array
{
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT c.id, c.grade_level, c.section, c.teacher_id, c.school_year_id, 
                   c.is_active, c.created_at, c.updated_at,
                   u.full_name AS teacher_name, u.email AS teacher_email,
                   sy.name AS school_year_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS student_count
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE c.school_year_id = ? AND c.is_active = 1
            ORDER BY c.grade_level, c.section";
    
    return dbFetchAll($sql, [$schoolYearId]);
}

/**
 * Get classes taught by a teacher
 * 
 * @param int $teacherId Teacher user ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array List of classes
 * 
 * Requirements: 3.3
 */
function getTeacherClasses(int $teacherId, ?int $schoolYearId = null): array
{
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT c.id, c.grade_level, c.section, c.teacher_id, c.school_year_id, 
                   c.is_active, c.created_at, c.updated_at,
                   sy.name AS school_year_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS student_count
            FROM classes c
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE c.teacher_id = ? AND c.school_year_id = ? AND c.is_active = 1
            ORDER BY c.grade_level, c.section";
    
    return dbFetchAll($sql, [$teacherId, $schoolYearId]);
}

/**
 * Get a single class by ID with teacher info
 * 
 * @param int $classId Class ID
 * @return array|null Class record with teacher info or null if not found
 * 
 * Requirements: 3.3
 */
function getClassById(int $classId): ?array
{
    $sql = "SELECT c.id, c.grade_level, c.section, c.teacher_id, c.school_year_id, 
                   c.is_active, c.created_at, c.updated_at,
                   u.full_name AS teacher_name, u.email AS teacher_email,
                   sy.name AS school_year_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS student_count
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE c.id = ?";
    
    return dbFetchOne($sql, [$classId]);
}


/**
 * Assign a student to a class
 * Prevents duplicate enrollment in the same school year
 * Handles re-enrollment of previously removed students
 * 
 * @param int $studentId Student ID
 * @param int $classId Class ID
 * @param int|null $enrolledBy User ID who enrolled the student (optional)
 * @return int|false Assignment ID or false on failure
 * 
 * Requirements: 4.1, 4.2
 */
function assignStudentToClass(int $studentId, int $classId, ?int $enrolledBy = null): int|false
{
    if ($studentId <= 0 || $classId <= 0) {
        return false;
    }
    
    // Verify student exists
    $studentSql = "SELECT id FROM students WHERE id = ? AND is_active = 1";
    $student = dbFetchOne($studentSql, [$studentId]);
    
    if (!$student) {
        return false;
    }
    
    // Get class info to check school year
    $class = getClassById($classId);
    if (!$class || !$class['is_active']) {
        return false;
    }
    
    // Check if student is already enrolled in a class for this school year (active enrollment)
    $existingEnrollmentSql = "SELECT sc.id, c.grade_level, c.section 
                              FROM student_classes sc
                              JOIN classes c ON sc.class_id = c.id
                              WHERE sc.student_id = ? AND c.school_year_id = ? AND sc.is_active = 1";
    $existingEnrollment = dbFetchOne($existingEnrollmentSql, [$studentId, $class['school_year_id']]);
    
    if ($existingEnrollment) {
        // Student already enrolled in a class for this school year
        return false;
    }
    
    try {
        // Check if there's an inactive enrollment for this exact student+class combination
        $inactiveEnrollmentSql = "SELECT id FROM student_classes WHERE student_id = ? AND class_id = ? AND is_active = 0";
        $inactiveEnrollment = dbFetchOne($inactiveEnrollmentSql, [$studentId, $classId]);
        
        if ($inactiveEnrollment) {
            // Re-activate the existing enrollment
            $updateSql = "UPDATE student_classes SET is_active = 1, enrolled_by = ?, enrolled_at = CURRENT_TIMESTAMP WHERE id = ?";
            $affected = dbExecute($updateSql, [$enrolledBy, $inactiveEnrollment['id']]);
            return $affected > 0 ? $inactiveEnrollment['id'] : false;
        }
        
        // Create new enrollment
        $sql = "INSERT INTO student_classes (student_id, class_id, enrolled_by, is_active) 
                VALUES (?, ?, ?, 1)";
        return dbInsert($sql, [$studentId, $classId, $enrolledBy]);
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to assign student to class: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Get all students in a class
 * 
 * @param int $classId Class ID
 * @return array List of students with enrollment info
 * 
 * Requirements: 4.4
 */
function getClassStudents(int $classId): array
{
    $sql = "SELECT s.id, s.student_id AS student_code, s.lrn, s.first_name, s.last_name, 
                   s.date_of_birth, s.parent_name, s.parent_phone, s.parent_email, s.is_active,
                   sc.id AS enrollment_id, sc.enrolled_at, sc.is_active AS enrollment_active,
                   u.full_name AS enrolled_by_name
            FROM students s
            JOIN student_classes sc ON s.id = sc.student_id
            LEFT JOIN users u ON sc.enrolled_by = u.id
            WHERE sc.class_id = ? AND sc.is_active = 1 AND s.is_active = 1
            ORDER BY s.last_name, s.first_name";
    
    return dbFetchAll($sql, [$classId]);
}

/**
 * Get student's class for a specific school year
 * 
 * @param int $studentId Student ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array|null Class record with teacher info or null if not enrolled
 * 
 * Requirements: 4.3, 4.4
 */
function getStudentClass(int $studentId, ?int $schoolYearId = null): ?array
{
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return null;
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT c.id, c.grade_level, c.section, c.teacher_id, c.school_year_id,
                   u.full_name AS teacher_name, u.email AS teacher_email,
                   sy.name AS school_year_name,
                   sc.enrolled_at, sc.is_active AS enrollment_active
            FROM classes c
            JOIN student_classes sc ON c.id = sc.class_id
            JOIN users u ON c.teacher_id = u.id
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE sc.student_id = ? AND c.school_year_id = ? AND sc.is_active = 1
            LIMIT 1";
    
    return dbFetchOne($sql, [$studentId, $schoolYearId]);
}

/**
 * Get student's enrollment history across all school years
 * 
 * @param int $studentId Student ID
 * @return array List of enrollments with class and teacher info
 * 
 * Requirements: 4.4
 */
function getStudentEnrollmentHistory(int $studentId): array
{
    $sql = "SELECT c.id AS class_id, c.grade_level, c.section, c.teacher_id, c.school_year_id,
                   u.full_name AS teacher_name, u.email AS teacher_email,
                   sy.name AS school_year_name, sy.is_active AS is_current_year,
                   sc.id AS enrollment_id, sc.enrolled_at, sc.is_active AS enrollment_active,
                   eu.full_name AS enrolled_by_name
            FROM student_classes sc
            JOIN classes c ON sc.class_id = c.id
            JOIN users u ON c.teacher_id = u.id
            JOIN school_years sy ON c.school_year_id = sy.id
            LEFT JOIN users eu ON sc.enrolled_by = eu.id
            WHERE sc.student_id = ?
            ORDER BY sy.name DESC, sc.enrolled_at DESC";
    
    return dbFetchAll($sql, [$studentId]);
}


/**
 * Get all students for a teacher (across all their classes)
 * 
 * @param int $teacherId Teacher user ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array List of students with class info
 * 
 * Requirements: 6.1
 */
function getTeacherStudents(int $teacherId, ?int $schoolYearId = null): array
{
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT DISTINCT s.id, s.student_id AS student_code, s.lrn, s.first_name, s.last_name, 
                   s.date_of_birth, s.parent_name, s.parent_phone, s.parent_email, s.is_active,
                   c.id AS class_id, c.grade_level, c.section,
                   sy.name AS school_year_name,
                   sc.enrolled_at
            FROM students s
            JOIN student_classes sc ON s.id = sc.student_id
            JOIN classes c ON sc.class_id = c.id
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE c.teacher_id = ? AND c.school_year_id = ? 
                  AND sc.is_active = 1 AND s.is_active = 1 AND c.is_active = 1
            ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
    
    return dbFetchAll($sql, [$teacherId, $schoolYearId]);
}

/**
 * Check if a teacher can access a specific student
 * Teacher can only access students enrolled in their classes
 * 
 * @param int $teacherId Teacher user ID
 * @param int $studentId Student ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return bool True if student is in teacher's class
 * 
 * Requirements: 6.4
 */
function canTeacherAccessStudent(int $teacherId, int $studentId, ?int $schoolYearId = null): bool
{
    // If no school year specified, use active school year
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return false;
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT 1
            FROM student_classes sc
            JOIN classes c ON sc.class_id = c.id
            WHERE sc.student_id = ? AND c.teacher_id = ? AND c.school_year_id = ?
                  AND sc.is_active = 1 AND c.is_active = 1
            LIMIT 1";
    
    $result = dbFetchOne($sql, [$studentId, $teacherId, $schoolYearId]);
    
    return $result !== null;
}


/**
 * Move students to new classes for a new school year
 * Creates new enrollment records without modifying previous enrollments
 * 
 * @param array $assignments Array of [student_id => class_id]
 * @param int|null $enrolledBy User ID who performed the movement (optional)
 * @return array Results with success/failure per student
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4
 */
function moveStudentsToClasses(array $assignments, ?int $enrolledBy = null): array
{
    $results = [
        'success' => [],
        'failed' => [],
        'total' => count($assignments),
        'success_count' => 0,
        'failed_count' => 0
    ];
    
    if (empty($assignments)) {
        return $results;
    }
    
    // Use transaction for data integrity
    dbBeginTransaction();
    
    try {
        foreach ($assignments as $studentId => $classId) {
            $studentId = (int) $studentId;
            $classId = (int) $classId;
            
            // Validate student exists
            $studentSql = "SELECT id, first_name, last_name FROM students WHERE id = ? AND is_active = 1";
            $student = dbFetchOne($studentSql, [$studentId]);
            
            if (!$student) {
                $results['failed'][] = [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'reason' => 'Student not found or inactive'
                ];
                $results['failed_count']++;
                continue;
            }
            
            // Get class info
            $class = getClassById($classId);
            if (!$class || !$class['is_active']) {
                $results['failed'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'class_id' => $classId,
                    'reason' => 'Class not found or inactive'
                ];
                $results['failed_count']++;
                continue;
            }
            
            // Check if student is already enrolled in a class for this school year
            $existingEnrollmentSql = "SELECT sc.id, c.grade_level, c.section 
                                      FROM student_classes sc
                                      JOIN classes c ON sc.class_id = c.id
                                      WHERE sc.student_id = ? AND c.school_year_id = ? AND sc.is_active = 1";
            $existingEnrollment = dbFetchOne($existingEnrollmentSql, [$studentId, $class['school_year_id']]);
            
            if ($existingEnrollment) {
                $results['failed'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'class_id' => $classId,
                    'reason' => 'Student already enrolled in ' . $existingEnrollment['grade_level'] . ' - ' . $existingEnrollment['section'] . ' for this school year'
                ];
                $results['failed_count']++;
                continue;
            }
            
            // Create new enrollment (historical records are preserved - we never modify old enrollments)
            $insertSql = "INSERT INTO student_classes (student_id, class_id, enrolled_by, is_active) 
                          VALUES (?, ?, ?, 1)";
            $enrollmentId = dbInsert($insertSql, [$studentId, $classId, $enrolledBy]);
            
            if ($enrollmentId) {
                $results['success'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'class_id' => $classId,
                    'class_name' => $class['grade_level'] . ' - ' . $class['section'],
                    'enrollment_id' => $enrollmentId
                ];
                $results['success_count']++;
            } else {
                $results['failed'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'class_id' => $classId,
                    'reason' => 'Failed to create enrollment record'
                ];
                $results['failed_count']++;
            }
        }
        
        dbCommit();
    } catch (PDOException $e) {
        dbRollback();
        
        if (function_exists('logError')) {
            logError('Bulk student movement failed: ' . $e->getMessage());
        }
        
        // Mark all remaining as failed
        $results['failed'][] = [
            'reason' => 'Database error: ' . $e->getMessage()
        ];
        $results['failed_count'] = $results['total'];
        $results['success'] = [];
        $results['success_count'] = 0;
    }
    
    return $results;
}

/**
 * Remove a student from a class (soft delete - sets is_active to 0)
 * 
 * @param int $studentId Student ID
 * @param int $classId Class ID
 * @return bool True on success
 */
function removeStudentFromClass(int $studentId, int $classId): bool
{
    try {
        $sql = "UPDATE student_classes SET is_active = 0 WHERE student_id = ? AND class_id = ?";
        $affected = dbExecute($sql, [$studentId, $classId]);
        return $affected > 0;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to remove student from class: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Update a class
 * 
 * @param int $classId Class ID
 * @param string $gradeLevel Grade level
 * @param string $section Section name
 * @param int $teacherId Teacher user ID
 * @return bool True on success
 */
function updateClass(int $classId, string $gradeLevel, string $section, int $teacherId): bool
{
    // Validate required fields
    if (empty(trim($gradeLevel)) || empty(trim($section)) || $teacherId <= 0) {
        return false;
    }
    
    // Verify teacher exists and has teacher role
    $teacherSql = "SELECT id, role FROM users WHERE id = ? AND is_active = 1";
    $teacher = dbFetchOne($teacherSql, [$teacherId]);
    
    if (!$teacher || $teacher['role'] !== 'teacher') {
        return false;
    }
    
    // Get current class to check school year
    $currentClass = getClassById($classId);
    if (!$currentClass) {
        return false;
    }
    
    // Check for duplicate (different class with same grade, section, school year)
    $duplicateSql = "SELECT id FROM classes WHERE grade_level = ? AND section = ? AND school_year_id = ? AND id != ?";
    $existing = dbFetchOne($duplicateSql, [trim($gradeLevel), trim($section), $currentClass['school_year_id'], $classId]);
    
    if ($existing) {
        return false;
    }
    
    try {
        $sql = "UPDATE classes SET grade_level = ?, section = ?, teacher_id = ? WHERE id = ?";
        $affected = dbExecute($sql, [trim($gradeLevel), trim($section), $teacherId, $classId]);
        return $affected >= 0; // 0 is valid if no changes were made
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to update class: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Deactivate a class (soft delete)
 * 
 * @param int $classId Class ID
 * @return bool True on success
 */
function deactivateClass(int $classId): bool
{
    try {
        $sql = "UPDATE classes SET is_active = 0 WHERE id = ?";
        $affected = dbExecute($sql, [$classId]);
        return $affected > 0;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to deactivate class: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Get all teachers (users with teacher role)
 * 
 * @return array List of teachers
 */
function getAllTeachers(): array
{
    $sql = "SELECT id, username, full_name, email 
            FROM users 
            WHERE role = 'teacher' AND is_active = 1 
            ORDER BY full_name";
    
    return dbFetchAll($sql);
}

/**
 * Get all teachers with their advisory class info for the active school year
 * 
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array List of teachers with advisory info
 */
function getTeachersWithAdvisoryInfo(?int $schoolYearId = null): array
{
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.last_login, u.created_at,
                   c.id AS class_id, c.grade_level, c.section,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS student_count
            FROM users u
            LEFT JOIN classes c ON u.id = c.teacher_id AND c.school_year_id = ? AND c.is_active = 1
            WHERE u.role = 'teacher' AND u.is_active = 1
            ORDER BY u.full_name";
    
    return dbFetchAll($sql, [$schoolYearId]);
}

/**
 * Get teachers without advisory class for the active school year
 * 
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array List of teachers without advisory
 */
function getTeachersWithoutAdvisory(?int $schoolYearId = null): array
{
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return [];
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT u.id, u.username, u.full_name, u.email
            FROM users u
            WHERE u.role = 'teacher' AND u.is_active = 1
            AND NOT EXISTS (
                SELECT 1 FROM classes c 
                WHERE c.teacher_id = u.id AND c.school_year_id = ? AND c.is_active = 1
            )
            ORDER BY u.full_name";
    
    return dbFetchAll($sql, [$schoolYearId]);
}

/**
 * Get teacher's advisory class for a school year
 * 
 * @param int $teacherId Teacher user ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array|null Class info or null if no advisory
 */
function getTeacherAdvisoryClass(int $teacherId, ?int $schoolYearId = null): ?array
{
    if ($schoolYearId === null) {
        $activeSchoolYear = getActiveSchoolYear();
        if (!$activeSchoolYear) {
            return null;
        }
        $schoolYearId = $activeSchoolYear['id'];
    }
    
    $sql = "SELECT c.id, c.grade_level, c.section, c.school_year_id,
                   sy.name AS school_year_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS student_count
            FROM classes c
            JOIN school_years sy ON c.school_year_id = sy.id
            WHERE c.teacher_id = ? AND c.school_year_id = ? AND c.is_active = 1
            LIMIT 1";
    
    return dbFetchOne($sql, [$teacherId, $schoolYearId]);
}


/**
 * Get student's current class info for display purposes
 * Returns grade_level, section, and display string from enrollment
 * 
 * @param int $studentId Student ID
 * @param int|null $schoolYearId School year ID (null = active school year)
 * @return array|null Class info array or null if not enrolled
 */
function getStudentCurrentClassInfo(int $studentId, ?int $schoolYearId = null): ?array
{
    $class = getStudentClass($studentId, $schoolYearId);
    if ($class) {
        return [
            'grade_level' => $class['grade_level'],
            'section' => $class['section'],
            'display' => $class['grade_level'] . ' - ' . $class['section'],
            'teacher_name' => $class['teacher_name'] ?? null,
            'school_year_name' => $class['school_year_name'] ?? null,
            'class_id' => $class['id'] ?? null
        ];
    }
    return null;
}
