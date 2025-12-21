<?php
/**
 * Student Placement Functions
 * Handle student placement operations for moving students between school years
 * 
 * Requirements: 1.2, 2.2, 2.3, 5.1
 */

// Ensure required files are loaded
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schoolyear.php';

/**
 * Get eligible students from source school year
 * Returns students enrolled in source year but not yet placed in target year
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return array List of eligible students with their current class info
 * 
 * Requirements: 1.2
 */
function getEligibleStudents(int $sourceSchoolYearId, int $targetSchoolYearId): array
{
    if ($sourceSchoolYearId <= 0 || $targetSchoolYearId <= 0) {
        return [];
    }
    
    // Get students enrolled in source school year who are:
    // - Active students
    // - Have active enrollment in source school year
    // - NOT already enrolled in any class for target school year
    $sql = "SELECT DISTINCT 
                s.id,
                s.student_id AS student_code,
                s.lrn,
                s.first_name,
                s.last_name,
                s.date_of_birth,
                s.is_active,
                c.id AS source_class_id,
                c.grade_level AS source_grade_level,
                c.section AS source_section,
                sc.enrolled_at AS source_enrolled_at
            FROM students s
            INNER JOIN student_classes sc ON s.id = sc.student_id
            INNER JOIN classes c ON sc.class_id = c.id
            WHERE c.school_year_id = ?
              AND sc.is_active = 1
              AND s.is_active = 1
              AND c.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 
                  FROM student_classes sc2
                  INNER JOIN classes c2 ON sc2.class_id = c2.id
                  WHERE sc2.student_id = s.id
                    AND c2.school_year_id = ?
                    AND sc2.is_active = 1
              )
            ORDER BY c.grade_level, c.section, s.last_name, s.first_name";
    
    return dbFetchAll($sql, [$sourceSchoolYearId, $targetSchoolYearId]);
}

/**
 * Get suggested grade promotion for a student
 * Returns next grade level based on current enrollment
 * 
 * @param string $currentGrade Current grade level (e.g., "Grade 6")
 * @return string Suggested next grade level (e.g., "Grade 7")
 * 
 * Requirements: 5.1
 */
function getSuggestedGrade(string $currentGrade): string
{
    // Extract grade number from string like "Grade 6", "Grade 7", etc.
    if (preg_match('/Grade\s*(\d+)/i', $currentGrade, $matches)) {
        $currentGradeNum = (int) $matches[1];
        $nextGradeNum = $currentGradeNum + 1;
        return "Grade " . $nextGradeNum;
    }
    
    // Handle other formats like "Kindergarten", "K", etc.
    $lowerGrade = strtolower(trim($currentGrade));
    
    if ($lowerGrade === 'kindergarten' || $lowerGrade === 'k') {
        return "Grade 1";
    }
    
    // If format is not recognized, return the same grade (repeater scenario)
    return $currentGrade;
}

/**
 * Get suggested grade display string
 * Returns formatted string showing current → suggested grade
 * 
 * @param string $currentGrade Current grade level
 * @return string Display string (e.g., "Grade 6 → Grade 7")
 * 
 * Requirements: 5.2
 */
function getSuggestedGradeDisplay(string $currentGrade): string
{
    $suggestedGrade = getSuggestedGrade($currentGrade);
    return $currentGrade . " → " . $suggestedGrade;
}

/**
 * Filter students by grade level and/or section
 * 
 * @param array $students Array of student records
 * @param string|null $gradeLevel Grade level to filter by (null = no filter)
 * @param string|null $section Section to filter by (null = no filter)
 * @return array Filtered array of students
 * 
 * Requirements: 2.2, 2.3
 */
function filterStudents(array $students, ?string $gradeLevel, ?string $section): array
{
    if (empty($students)) {
        return [];
    }
    
    // If no filters, return all students
    if ($gradeLevel === null && $section === null) {
        return $students;
    }
    
    return array_filter($students, function ($student) use ($gradeLevel, $section) {
        // Check grade level filter
        if ($gradeLevel !== null && $gradeLevel !== '') {
            $studentGrade = $student['source_grade_level'] ?? '';
            if ($studentGrade !== $gradeLevel) {
                return false;
            }
        }
        
        // Check section filter
        if ($section !== null && $section !== '') {
            $studentSection = $student['source_section'] ?? '';
            if ($studentSection !== $section) {
                return false;
            }
        }
        
        return true;
    });
}

/**
 * Get available filter options from student list
 * Returns unique grade levels and sections for filter dropdowns
 * 
 * @param array $students Array of student records
 * @return array Associative array with 'grade_levels' and 'sections' arrays
 * 
 * Requirements: 2.1
 */
function getFilterOptions(array $students): array
{
    $gradeLevels = [];
    $sections = [];
    
    foreach ($students as $student) {
        $gradeLevel = $student['source_grade_level'] ?? null;
        $section = $student['source_section'] ?? null;
        
        if ($gradeLevel !== null && !in_array($gradeLevel, $gradeLevels, true)) {
            $gradeLevels[] = $gradeLevel;
        }
        
        if ($section !== null && !in_array($section, $sections, true)) {
            $sections[] = $section;
        }
    }
    
    // Sort for consistent display
    sort($gradeLevels);
    sort($sections);
    
    return [
        'grade_levels' => $gradeLevels,
        'sections' => $sections
    ];
}

/**
 * Get eligible students with suggested grades
 * Combines getEligibleStudents with grade suggestions for each student
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return array List of eligible students with suggested grade info
 * 
 * Requirements: 1.2, 5.1, 5.2
 */
function getEligibleStudentsWithSuggestions(int $sourceSchoolYearId, int $targetSchoolYearId): array
{
    $students = getEligibleStudents($sourceSchoolYearId, $targetSchoolYearId);
    
    foreach ($students as &$student) {
        $currentGrade = $student['source_grade_level'] ?? '';
        $student['suggested_grade'] = getSuggestedGrade($currentGrade);
        $student['suggested_grade_display'] = getSuggestedGradeDisplay($currentGrade);
    }
    
    return $students;
}

/**
 * Get count of eligible students
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return int Count of eligible students
 */
function getEligibleStudentCount(int $sourceSchoolYearId, int $targetSchoolYearId): int
{
    if ($sourceSchoolYearId <= 0 || $targetSchoolYearId <= 0) {
        return 0;
    }
    
    $sql = "SELECT COUNT(DISTINCT s.id) AS count
            FROM students s
            INNER JOIN student_classes sc ON s.id = sc.student_id
            INNER JOIN classes c ON sc.class_id = c.id
            WHERE c.school_year_id = ?
              AND sc.is_active = 1
              AND s.is_active = 1
              AND c.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 
                  FROM student_classes sc2
                  INNER JOIN classes c2 ON sc2.class_id = c2.id
                  WHERE sc2.student_id = s.id
                    AND c2.school_year_id = ?
                    AND sc2.is_active = 1
              )";
    
    $result = dbFetchOne($sql, [$sourceSchoolYearId, $targetSchoolYearId]);
    return $result ? (int) $result['count'] : 0;
}

/**
 * Get available target classes for a school year
 * 
 * @param int $schoolYearId Target school year ID
 * @param string|null $gradeLevel Optional grade level filter
 * @return array List of available classes
 */
function getAvailableTargetClasses(int $schoolYearId, ?string $gradeLevel = null): array
{
    if ($schoolYearId <= 0) {
        return [];
    }
    
    $sql = "SELECT c.id, c.grade_level, c.section, c.max_capacity,
                   u.full_name AS teacher_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS current_enrollment
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.school_year_id = ? AND c.is_active = 1";
    
    $params = [$schoolYearId];
    
    if ($gradeLevel !== null && $gradeLevel !== '') {
        $sql .= " AND c.grade_level = ?";
        $params[] = $gradeLevel;
    }
    
    $sql .= " ORDER BY c.grade_level, c.section";
    
    return dbFetchAll($sql, $params);
}

/**
 * Check if a student is already enrolled in a class for a specific school year
 * 
 * @param int $studentId Student ID
 * @param int $schoolYearId School year ID
 * @return bool True if already enrolled
 */
function isStudentEnrolledInSchoolYear(int $studentId, int $schoolYearId): bool
{
    $sql = "SELECT 1 
            FROM student_classes sc
            INNER JOIN classes c ON sc.class_id = c.id
            WHERE sc.student_id = ?
              AND c.school_year_id = ?
              AND sc.is_active = 1
            LIMIT 1";
    
    $result = dbFetchOne($sql, [$studentId, $schoolYearId]);
    return $result !== null;
}

/**
 * Get the school year ID for a class
 * 
 * @param int $classId Class ID
 * @return int|null School year ID or null if class not found
 */
function getClassSchoolYearId(int $classId): ?int
{
    $sql = "SELECT school_year_id FROM classes WHERE id = ? AND is_active = 1";
    $result = dbFetchOne($sql, [$classId]);
    return $result ? (int) $result['school_year_id'] : null;
}

/**
 * Check if a student is active
 * 
 * @param int $studentId Student ID
 * @return bool True if student is active
 */
function isStudentActive(int $studentId): bool
{
    $sql = "SELECT is_active FROM students WHERE id = ?";
    $result = dbFetchOne($sql, [$studentId]);
    return $result && (int) $result['is_active'] === 1;
}

/**
 * Bulk assign students to a target class
 * Creates pending placements in session (not saved to DB until savePlacements is called)
 * 
 * @param array $studentIds Array of student IDs to assign
 * @param int $targetClassId Target class ID
 * @param int $enrolledBy User ID performing the assignment
 * @return array Result with success/skipped counts and details
 * 
 * Requirements: 3.2, 3.3, 3.4
 */
function bulkAssignStudents(array $studentIds, int $targetClassId, int $enrolledBy): array
{
    $result = [
        'success' => true,
        'assigned_count' => 0,
        'skipped_count' => 0,
        'skipped' => [],
        'assignments' => []
    ];
    
    if (empty($studentIds) || $targetClassId <= 0) {
        $result['success'] = false;
        return $result;
    }
    
    // Get target class school year
    $targetSchoolYearId = getClassSchoolYearId($targetClassId);
    if ($targetSchoolYearId === null) {
        $result['success'] = false;
        $result['error'] = 'Target class not found';
        return $result;
    }
    
    // Check if target school year is locked
    if (isEnrollmentLocked($targetSchoolYearId)) {
        $result['success'] = false;
        $result['error'] = 'Target school year enrollment is locked';
        return $result;
    }
    
    // Process each student
    foreach ($studentIds as $studentId) {
        $studentId = (int) $studentId;
        
        // Check if student is active
        if (!isStudentActive($studentId)) {
            $result['skipped_count']++;
            $result['skipped'][] = [
                'student_id' => $studentId,
                'reason' => 'Student inactive'
            ];
            continue;
        }
        
        // Check if student is already enrolled in target school year (Requirement 3.4)
        if (isStudentEnrolledInSchoolYear($studentId, $targetSchoolYearId)) {
            $result['skipped_count']++;
            $result['skipped'][] = [
                'student_id' => $studentId,
                'reason' => 'Already enrolled in target year'
            ];
            continue;
        }
        
        // Check if student already has a pending assignment for this school year
        if (hasPendingPlacement($studentId, $targetSchoolYearId)) {
            $result['skipped_count']++;
            $result['skipped'][] = [
                'student_id' => $studentId,
                'reason' => 'Already has pending placement'
            ];
            continue;
        }
        
        // Add to pending assignments in session
        addPendingPlacement($studentId, $targetClassId);
        
        $result['assigned_count']++;
        $result['assignments'][] = [
            'student_id' => $studentId,
            'target_class_id' => $targetClassId
        ];
    }
    
    // Store the bulk action in undo stack for potential reversal
    if ($result['assigned_count'] > 0) {
        $assignedStudentIds = array_column($result['assignments'], 'student_id');
        pushUndoAction([
            'type' => 'bulk_assign',
            'student_ids' => $assignedStudentIds,
            'target_class_id' => $targetClassId,
            'enrolled_by' => $enrolledBy,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    return $result;
}

/**
 * Check if enrollment is locked for a school year
 * 
 * @param int $schoolYearId School year ID
 * @return bool True if locked
 * 
 * Requirements: 9.3, 9.4
 */
function isEnrollmentLocked(int $schoolYearId): bool
{
    if ($schoolYearId <= 0) {
        return false;
    }
    
    $sql = "SELECT is_locked FROM school_years WHERE id = ?";
    $result = dbFetchOne($sql, [$schoolYearId]);
    return $result && (int) $result['is_locked'] === 1;
}

/**
 * Lock enrollment for a school year
 * Prevents any new student placements or removal of students from classes
 * 
 * @param int $schoolYearId School year ID to lock
 * @param int $lockedBy User ID performing the lock action
 * @return array Result with success status and message
 * 
 * Requirements: 9.1, 9.2
 */
function lockEnrollment(int $schoolYearId, int $lockedBy = 0): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'message' => '',
        'school_year_id' => $schoolYearId
    ];
    
    if ($schoolYearId <= 0) {
        $result['message'] = 'Invalid school year ID';
        return $result;
    }
    
    // Check if school year exists
    $sql = "SELECT id, name, is_locked FROM school_years WHERE id = ?";
    $schoolYear = dbFetchOne($sql, [$schoolYearId]);
    
    if ($schoolYear === null) {
        $result['message'] = 'School year not found';
        return $result;
    }
    
    // Check if already locked
    if ((int) $schoolYear['is_locked'] === 1) {
        $result['success'] = true;
        $result['message'] = 'School year enrollment is already locked';
        $result['already_locked'] = true;
        return $result;
    }
    
    // Lock the school year
    $updateSql = "UPDATE school_years SET is_locked = 1 WHERE id = ?";
    $affected = dbExecute($updateSql, [$schoolYearId]);
    
    if ($affected > 0) {
        $result['success'] = true;
        $result['message'] = 'School year enrollment locked successfully';
        
        // Log the lock action
        logInfo('School year enrollment locked', [
            'school_year_id' => $schoolYearId,
            'school_year_name' => $schoolYear['name'],
            'locked_by' => $lockedBy
        ]);
    } else {
        $result['message'] = 'Failed to lock school year enrollment';
        
        logError('Failed to lock school year enrollment', [
            'school_year_id' => $schoolYearId,
            'locked_by' => $lockedBy
        ]);
    }
    
    return $result;
}

/**
 * Unlock enrollment for a school year (admin only)
 * Allows student placements and modifications to resume
 * 
 * @param int $schoolYearId School year ID to unlock
 * @param int $unlockedBy User ID performing the unlock action (must be admin)
 * @return array Result with success status and message
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4
 */
function unlockEnrollment(int $schoolYearId, int $unlockedBy = 0): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'message' => '',
        'school_year_id' => $schoolYearId
    ];
    
    if ($schoolYearId <= 0) {
        $result['message'] = 'Invalid school year ID';
        return $result;
    }
    
    // Check if school year exists
    $sql = "SELECT id, name, is_locked FROM school_years WHERE id = ?";
    $schoolYear = dbFetchOne($sql, [$schoolYearId]);
    
    if ($schoolYear === null) {
        $result['message'] = 'School year not found';
        return $result;
    }
    
    // Check if already unlocked
    if ((int) $schoolYear['is_locked'] === 0) {
        $result['success'] = true;
        $result['message'] = 'School year enrollment is already unlocked';
        $result['already_unlocked'] = true;
        return $result;
    }
    
    // Unlock the school year
    $updateSql = "UPDATE school_years SET is_locked = 0 WHERE id = ?";
    $affected = dbExecute($updateSql, [$schoolYearId]);
    
    if ($affected > 0) {
        $result['success'] = true;
        $result['message'] = 'School year enrollment unlocked successfully';
        
        // Log the unlock action
        logInfo('School year enrollment unlocked', [
            'school_year_id' => $schoolYearId,
            'school_year_name' => $schoolYear['name'],
            'unlocked_by' => $unlockedBy
        ]);
    } else {
        $result['message'] = 'Failed to unlock school year enrollment';
        
        logError('Failed to unlock school year enrollment', [
            'school_year_id' => $schoolYearId,
            'unlocked_by' => $unlockedBy
        ]);
    }
    
    return $result;
}

/**
 * Initialize placement session if not already initialized
 * 
 * @param int|null $sourceSchoolYearId Optional source school year ID to set
 * @param int|null $targetSchoolYearId Optional target school year ID to set
 * 
 * Requirements: 3.5, 8.4
 */
function initPlacementSession(?int $sourceSchoolYearId = null, ?int $targetSchoolYearId = null): void
{
    if (!isset($_SESSION['placement'])) {
        $_SESSION['placement'] = [
            'source_school_year_id' => $sourceSchoolYearId,
            'target_school_year_id' => $targetSchoolYearId,
            'assignments' => [],
            'undo_stack' => []
        ];
    } else {
        // Update school year IDs if provided
        if ($sourceSchoolYearId !== null) {
            $_SESSION['placement']['source_school_year_id'] = $sourceSchoolYearId;
        }
        if ($targetSchoolYearId !== null) {
            $_SESSION['placement']['target_school_year_id'] = $targetSchoolYearId;
        }
    }
}

/**
 * Get the current placement session data
 * Returns the entire session state including school years, assignments, and undo stack
 * 
 * @return array Placement session data
 * 
 * Requirements: 3.5, 8.4
 */
function getPlacementSession(): array
{
    initPlacementSession();
    return $_SESSION['placement'];
}

/**
 * Update placement session with new data
 * Allows partial updates to session state
 * 
 * @param array $data Associative array of session data to update
 *                    Supported keys: source_school_year_id, target_school_year_id, assignments
 * @return bool True if update was successful
 * 
 * Requirements: 3.5, 8.4
 */
function updatePlacementSession(array $data): bool
{
    initPlacementSession();
    
    // Update source school year if provided
    if (isset($data['source_school_year_id'])) {
        $_SESSION['placement']['source_school_year_id'] = $data['source_school_year_id'];
    }
    
    // Update target school year if provided
    if (isset($data['target_school_year_id'])) {
        $_SESSION['placement']['target_school_year_id'] = $data['target_school_year_id'];
    }
    
    // Update assignments if provided (merge or replace based on context)
    if (isset($data['assignments']) && is_array($data['assignments'])) {
        foreach ($data['assignments'] as $studentId => $classId) {
            $_SESSION['placement']['assignments'][$studentId] = $classId;
        }
    }
    
    return true;
}

/**
 * Clear the entire placement session
 * Removes all pending placements, undo stack, and resets school year selections
 * 
 * Requirements: 3.5, 8.4
 */
function clearPlacementSession(): void
{
    $_SESSION['placement'] = [
        'source_school_year_id' => null,
        'target_school_year_id' => null,
        'assignments' => [],
        'undo_stack' => []
    ];
}

/**
 * Add a pending placement to session
 * 
 * @param int $studentId Student ID
 * @param int $targetClassId Target class ID
 */
function addPendingPlacement(int $studentId, int $targetClassId): void
{
    initPlacementSession();
    $_SESSION['placement']['assignments'][$studentId] = $targetClassId;
}

/**
 * Check if student has a pending placement for a school year
 * 
 * @param int $studentId Student ID
 * @param int $schoolYearId School year ID
 * @return bool True if has pending placement
 */
function hasPendingPlacement(int $studentId, int $schoolYearId): bool
{
    initPlacementSession();
    
    if (!isset($_SESSION['placement']['assignments'][$studentId])) {
        return false;
    }
    
    $pendingClassId = $_SESSION['placement']['assignments'][$studentId];
    $pendingSchoolYearId = getClassSchoolYearId($pendingClassId);
    
    return $pendingSchoolYearId === $schoolYearId;
}

/**
 * Push action to undo stack
 * Stores the action in a session-based undo stack for potential reversal
 * 
 * @param array $action Action details including type, affected student IDs, and timestamp
 * 
 * Requirements: 10.1
 */
function pushUndoAction(array $action): void
{
    initPlacementSession();
    $_SESSION['placement']['undo_stack'][] = $action;
}

/**
 * Pop and return last action from undo stack
 * Removes and returns the most recent action (LIFO order)
 * 
 * @return array|null Last action or null if stack is empty
 * 
 * Requirements: 10.2, 10.4
 */
function popUndoAction(): ?array
{
    initPlacementSession();
    
    if (empty($_SESSION['placement']['undo_stack'])) {
        return null;
    }
    
    return array_pop($_SESSION['placement']['undo_stack']);
}

/**
 * Get undo stack size
 * Returns the number of actions available for undo
 * 
 * @return int Number of actions in undo stack
 * 
 * Requirements: 10.4
 */
function getUndoStackSize(): int
{
    initPlacementSession();
    return count($_SESSION['placement']['undo_stack']);
}

/**
 * Clear undo stack
 * Removes all actions from the undo stack (typically called after save)
 * 
 * Requirements: 10.4
 */
function clearUndoStack(): void
{
    initPlacementSession();
    $_SESSION['placement']['undo_stack'] = [];
}

/**
 * Get the entire undo stack without modifying it
 * Useful for debugging or displaying undo history
 * 
 * @return array Array of undo actions
 * 
 * Requirements: 10.4
 */
function getUndoStack(): array
{
    initPlacementSession();
    return $_SESSION['placement']['undo_stack'] ?? [];
}

/**
 * Assign a single student to a class (for individual adjustments)
 * Updates pending placement in session
 * 
 * @param int $studentId Student ID
 * @param int $targetClassId Target class ID
 * @param int $enrolledBy User ID performing the assignment
 * @return array Result with success status and details
 * 
 * Requirements: 4.2, 4.4
 */
function assignStudentPlacement(int $studentId, int $targetClassId, int $enrolledBy): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'message' => '',
        'previous_class_id' => null
    ];
    
    if ($studentId <= 0 || $targetClassId <= 0) {
        $result['message'] = 'Invalid student or class ID';
        return $result;
    }
    
    // Check if student is active
    if (!isStudentActive($studentId)) {
        $result['message'] = 'Student is inactive';
        return $result;
    }
    
    // Get target class school year
    $targetSchoolYearId = getClassSchoolYearId($targetClassId);
    if ($targetSchoolYearId === null) {
        $result['message'] = 'Target class not found';
        return $result;
    }
    
    // Check if target school year is locked
    if (isEnrollmentLocked($targetSchoolYearId)) {
        $result['message'] = 'Target school year enrollment is locked';
        return $result;
    }
    
    // Check if student is already enrolled in target school year (in DB)
    if (isStudentEnrolledInSchoolYear($studentId, $targetSchoolYearId)) {
        $result['message'] = 'Student already enrolled in target school year';
        return $result;
    }
    
    initPlacementSession();
    
    // Store previous assignment if exists (for undo and logging)
    $previousClassId = $_SESSION['placement']['assignments'][$studentId] ?? null;
    $result['previous_class_id'] = $previousClassId;
    
    // Update the pending placement
    $_SESSION['placement']['assignments'][$studentId] = $targetClassId;
    
    // Log the modification (Requirement 4.5)
    logInfo('Student placement modified', [
        'student_id' => $studentId,
        'previous_class_id' => $previousClassId,
        'new_class_id' => $targetClassId,
        'enrolled_by' => $enrolledBy,
        'action' => $previousClassId === null ? 'assigned' : 'changed'
    ]);
    
    // Add to undo stack
    pushUndoAction([
        'type' => 'individual_assign',
        'student_id' => $studentId,
        'previous_class_id' => $previousClassId,
        'new_class_id' => $targetClassId,
        'enrolled_by' => $enrolledBy,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $result['success'] = true;
    $result['message'] = $previousClassId === null 
        ? 'Student assigned to class' 
        : 'Student placement updated';
    
    return $result;
}

/**
 * Remove a student's pending placement (before save)
 * 
 * @param int $studentId Student ID
 * @param int $targetClassId Target class ID (for verification)
 * @return bool True if removed successfully
 * 
 * Requirements: 4.2, 4.4
 */
function removePendingPlacement(int $studentId, int $targetClassId): bool
{
    require_once __DIR__ . '/logger.php';
    
    initPlacementSession();
    
    // Verify the student has this pending placement
    if (!isset($_SESSION['placement']['assignments'][$studentId])) {
        return false;
    }
    
    $currentClassId = $_SESSION['placement']['assignments'][$studentId];
    
    // If targetClassId is provided, verify it matches
    if ($targetClassId > 0 && $currentClassId !== $targetClassId) {
        return false;
    }
    
    // Remove the pending placement
    unset($_SESSION['placement']['assignments'][$studentId]);
    
    // Log the removal
    logInfo('Pending placement removed', [
        'student_id' => $studentId,
        'removed_class_id' => $currentClassId
    ]);
    
    // Add to undo stack
    pushUndoAction([
        'type' => 'remove_placement',
        'student_id' => $studentId,
        'removed_class_id' => $currentClassId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    return true;
}

/**
 * Get all pending placements from session
 * 
 * @return array Associative array of student_id => class_id
 */
function getPendingPlacements(): array
{
    initPlacementSession();
    return $_SESSION['placement']['assignments'] ?? [];
}

/**
 * Get pending placement for a specific student
 * 
 * @param int $studentId Student ID
 * @return int|null Target class ID or null if no pending placement
 */
function getStudentPendingPlacement(int $studentId): ?int
{
    initPlacementSession();
    return $_SESSION['placement']['assignments'][$studentId] ?? null;
}

/**
 * Clear all pending placements
 */
function clearPendingPlacements(): void
{
    initPlacementSession();
    $_SESSION['placement']['assignments'] = [];
}

/**
 * Get count of pending placements
 * 
 * @return int Number of pending placements
 */
function getPendingPlacementCount(): int
{
    initPlacementSession();
    return count($_SESSION['placement']['assignments'] ?? []);
}

/**
 * Undo the last action from the undo stack
 * Reverses the action and restores previous state
 * 
 * @return array Result with success status and details
 * 
 * Requirements: 10.2, 10.3
 */
function undoLastAction(): array
{
    $result = [
        'success' => false,
        'message' => '',
        'action' => null
    ];
    
    $action = popUndoAction();
    
    if ($action === null) {
        $result['message'] = 'No actions to undo';
        return $result;
    }
    
    $result['action'] = $action;
    
    switch ($action['type']) {
        case 'bulk_assign':
            // Remove all students from this bulk assignment
            foreach ($action['student_ids'] as $studentId) {
                if (isset($_SESSION['placement']['assignments'][$studentId]) &&
                    $_SESSION['placement']['assignments'][$studentId] === $action['target_class_id']) {
                    unset($_SESSION['placement']['assignments'][$studentId]);
                }
            }
            $result['success'] = true;
            $result['message'] = 'Bulk assignment undone: ' . count($action['student_ids']) . ' students removed';
            break;
            
        case 'individual_assign':
            // Restore previous state
            if ($action['previous_class_id'] === null) {
                // Was a new assignment, remove it
                unset($_SESSION['placement']['assignments'][$action['student_id']]);
            } else {
                // Was a change, restore previous class
                $_SESSION['placement']['assignments'][$action['student_id']] = $action['previous_class_id'];
            }
            $result['success'] = true;
            $result['message'] = 'Individual assignment undone';
            break;
            
        case 'remove_placement':
            // Restore the removed placement
            $_SESSION['placement']['assignments'][$action['student_id']] = $action['removed_class_id'];
            $result['success'] = true;
            $result['message'] = 'Placement removal undone';
            break;
            
        default:
            $result['message'] = 'Unknown action type';
    }
    
    return $result;
}

/**
 * Save all pending placements to database
 * Uses transaction for atomicity - either all succeed or all fail
 * 
 * @param array $placements Associative array of student_id => class_id (optional, uses session if empty)
 * @param int $enrolledBy User ID performing the save
 * @return array Result with success status, counts, and details
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.5
 */
function savePlacements(array $placements, int $enrolledBy): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'created_count' => 0,
        'skipped_count' => 0,
        'skipped' => [],
        'enrollments' => [],
        'error' => null
    ];
    
    // Use session placements if none provided
    if (empty($placements)) {
        $placements = getPendingPlacements();
    }
    
    if (empty($placements)) {
        $result['error'] = 'No placements to save';
        return $result;
    }
    
    // Validate all placements before starting transaction
    $validPlacements = [];
    foreach ($placements as $studentId => $classId) {
        $studentId = (int) $studentId;
        $classId = (int) $classId;
        
        $validation = validatePlacement($studentId, $classId);
        
        if (!$validation['valid']) {
            $result['skipped_count']++;
            $result['skipped'][] = [
                'student_id' => $studentId,
                'class_id' => $classId,
                'reason' => $validation['error']
            ];
            continue;
        }
        
        $validPlacements[$studentId] = $classId;
    }
    
    if (empty($validPlacements)) {
        $result['error'] = 'No valid placements to save';
        return $result;
    }
    
    // Begin transaction for atomicity (Requirement 8.2)
    try {
        dbBeginTransaction();
        
        foreach ($validPlacements as $studentId => $classId) {
            // Double-check enrollment doesn't exist (race condition protection)
            $targetSchoolYearId = getClassSchoolYearId($classId);
            if (isStudentEnrolledInSchoolYear($studentId, $targetSchoolYearId)) {
                $result['skipped_count']++;
                $result['skipped'][] = [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'reason' => 'Already enrolled (concurrent modification)'
                ];
                continue;
            }
            
            // Check if inactive record exists (reactivate instead of insert)
            $existingSql = "SELECT id FROM student_classes WHERE student_id = ? AND class_id = ?";
            $existing = dbFetchOne($existingSql, [$studentId, $classId]);
            
            if ($existing) {
                // Reactivate existing record
                $updateSql = "UPDATE student_classes SET is_active = 1, enrolled_by = ?, enrolled_at = NOW() WHERE id = ?";
                dbExecute($updateSql, [$enrolledBy, $existing['id']]);
                $enrollmentId = $existing['id'];
            } else {
                // Insert new enrollment record
                $insertSql = "INSERT INTO student_classes (student_id, class_id, enrolled_by, enrolled_at, is_active) 
                              VALUES (?, ?, ?, NOW(), 1)";
                $enrollmentId = dbInsert($insertSql, [$studentId, $classId, $enrolledBy]);
            }
            
            $result['created_count']++;
            $result['enrollments'][] = [
                'student_id' => $studentId,
                'class_id' => $classId,
                'enrollment_id' => $enrollmentId
            ];
        }
        
        // Commit transaction
        dbCommit();
        
        $result['success'] = true;
        
        // Clear session data after successful save (Requirement 10.5)
        clearPendingPlacements();
        clearUndoStack();
        
        // Log successful save
        logInfo('Placements saved successfully', [
            'created_count' => $result['created_count'],
            'skipped_count' => $result['skipped_count'],
            'enrolled_by' => $enrolledBy
        ]);
        
    } catch (\PDOException $e) {
        // Rollback on failure (Requirement 8.3)
        dbRollback();
        
        $result['success'] = false;
        $result['error'] = 'Database error: ' . $e->getMessage();
        $result['created_count'] = 0;
        $result['enrollments'] = [];
        
        // Log error
        logError('Placement save failed', [
            'error' => $e->getMessage(),
            'enrolled_by' => $enrolledBy
        ]);
    } catch (\Exception $e) {
        // Rollback on any other failure
        dbRollback();
        
        $result['success'] = false;
        $result['error'] = 'Error: ' . $e->getMessage();
        $result['created_count'] = 0;
        $result['enrollments'] = [];
        
        logError('Placement save failed', [
            'error' => $e->getMessage(),
            'enrolled_by' => $enrolledBy
        ]);
    }
    
    return $result;
}

/**
 * Validate a single placement operation
 * Checks: same year, locked status, class exists, student active, capacity
 * 
 * @param int $studentId Student ID
 * @param int $targetClassId Target class ID
 * @return array Validation result with 'valid' boolean and 'error' message if invalid
 * 
 * Requirements: 1.5, 12.2
 */
function validatePlacement(int $studentId, int $targetClassId): array
{
    $result = [
        'valid' => true,
        'error' => null,
        'warnings' => []
    ];
    
    // Check if student ID is valid
    if ($studentId <= 0) {
        $result['valid'] = false;
        $result['error'] = 'Invalid student ID';
        return $result;
    }
    
    // Check if class ID is valid
    if ($targetClassId <= 0) {
        $result['valid'] = false;
        $result['error'] = 'Invalid class ID';
        return $result;
    }
    
    // Check if student exists and is active
    if (!isStudentActive($studentId)) {
        $result['valid'] = false;
        $result['error'] = 'Student is inactive or does not exist';
        return $result;
    }
    
    // Check if target class exists
    $classInfo = getClassInfo($targetClassId);
    if ($classInfo === null) {
        $result['valid'] = false;
        $result['error'] = 'Target class does not exist';
        return $result;
    }
    
    // Check if class is active
    if (!$classInfo['is_active']) {
        $result['valid'] = false;
        $result['error'] = 'Target class is not active';
        return $result;
    }
    
    // Get target school year ID
    $targetSchoolYearId = (int) $classInfo['school_year_id'];
    
    // Check if target school year is locked (Requirement 9.3, 9.4)
    if (isEnrollmentLocked($targetSchoolYearId)) {
        $result['valid'] = false;
        $result['error'] = 'Target school year enrollment is locked';
        return $result;
    }
    
    // Check if student is already enrolled in target school year (Requirement 1.5)
    if (isStudentEnrolledInSchoolYear($studentId, $targetSchoolYearId)) {
        $result['valid'] = false;
        $result['error'] = 'Student already enrolled in target school year';
        return $result;
    }
    
    // Check class capacity (Requirement 12.2)
    $capacityCheck = checkClassCapacity($targetClassId, 1);
    if ($capacityCheck['exceeds_capacity']) {
        // Add warning but don't invalidate - admin can proceed with acknowledgment
        $result['warnings'][] = $capacityCheck['message'];
    }
    
    return $result;
}

/**
 * Get class information by ID
 * 
 * @param int $classId Class ID
 * @return array|null Class info or null if not found
 */
function getClassInfo(int $classId): ?array
{
    $sql = "SELECT c.*, 
                   sy.name AS school_year_name,
                   sy.is_locked AS school_year_locked,
                   u.full_name AS teacher_name,
                   (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id AND sc.is_active = 1) AS current_enrollment
            FROM classes c
            LEFT JOIN school_years sy ON c.school_year_id = sy.id
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?";
    
    return dbFetchOne($sql, [$classId]);
}

/**
 * Check class capacity and return status
 * 
 * @param int $classId Class ID
 * @param int $additionalStudents Number of students to add (default 1)
 * @return array Capacity check result with current/max counts and warnings
 * 
 * Requirements: 12.1, 12.2, 12.3
 */
function checkClassCapacity(int $classId, int $additionalStudents = 1): array
{
    $result = [
        'class_id' => $classId,
        'current_enrollment' => 0,
        'max_capacity' => 50, // Default capacity
        'additional_students' => $additionalStudents,
        'projected_enrollment' => 0,
        'available_slots' => 0,
        'at_threshold' => false,
        'exceeds_capacity' => false,
        'message' => ''
    ];
    
    // Get class info with current enrollment
    $classInfo = getClassInfo($classId);
    
    if ($classInfo === null) {
        $result['message'] = 'Class not found';
        return $result;
    }
    
    $result['current_enrollment'] = (int) $classInfo['current_enrollment'];
    $result['max_capacity'] = (int) ($classInfo['max_capacity'] ?? 50);
    $result['projected_enrollment'] = $result['current_enrollment'] + $additionalStudents;
    $result['available_slots'] = max(0, $result['max_capacity'] - $result['current_enrollment']);
    
    // Calculate threshold (90% of capacity) for warning (Requirement 12.1)
    $threshold = (int) ($result['max_capacity'] * 0.9);
    
    // Check if at or above threshold
    if ($result['current_enrollment'] >= $threshold) {
        $result['at_threshold'] = true;
    }
    
    // Check if would exceed capacity (Requirement 12.2)
    if ($result['projected_enrollment'] > $result['max_capacity']) {
        $result['exceeds_capacity'] = true;
        $result['message'] = sprintf(
            'Adding %d student(s) would exceed class capacity (%d/%d)',
            $additionalStudents,
            $result['projected_enrollment'],
            $result['max_capacity']
        );
    } elseif ($result['at_threshold']) {
        $result['message'] = sprintf(
            'Class is at or above 90%% capacity (%d/%d)',
            $result['current_enrollment'],
            $result['max_capacity']
        );
    } else {
        $result['message'] = sprintf(
            'Class has %d available slots (%d/%d)',
            $result['available_slots'],
            $result['current_enrollment'],
            $result['max_capacity']
        );
    }
    
    return $result;
}

/**
 * Validate bulk placement for multiple students
 * 
 * @param array $studentIds Array of student IDs
 * @param int $targetClassId Target class ID
 * @return array Validation result with valid students and errors
 */
function validateBulkPlacement(array $studentIds, int $targetClassId): array
{
    $result = [
        'valid' => true,
        'valid_students' => [],
        'invalid_students' => [],
        'capacity_warning' => null,
        'error' => null
    ];
    
    if (empty($studentIds)) {
        $result['valid'] = false;
        $result['error'] = 'No students selected';
        return $result;
    }
    
    if ($targetClassId <= 0) {
        $result['valid'] = false;
        $result['error'] = 'Invalid target class';
        return $result;
    }
    
    // Check class exists and is valid
    $classInfo = getClassInfo($targetClassId);
    if ($classInfo === null) {
        $result['valid'] = false;
        $result['error'] = 'Target class does not exist';
        return $result;
    }
    
    // Check if school year is locked
    $targetSchoolYearId = (int) $classInfo['school_year_id'];
    if (isEnrollmentLocked($targetSchoolYearId)) {
        $result['valid'] = false;
        $result['error'] = 'Target school year enrollment is locked';
        return $result;
    }
    
    // Validate each student
    foreach ($studentIds as $studentId) {
        $studentId = (int) $studentId;
        $validation = validatePlacement($studentId, $targetClassId);
        
        if ($validation['valid']) {
            $result['valid_students'][] = $studentId;
        } else {
            $result['invalid_students'][] = [
                'student_id' => $studentId,
                'error' => $validation['error']
            ];
        }
    }
    
    // Check capacity for all valid students
    if (!empty($result['valid_students'])) {
        $capacityCheck = checkClassCapacity($targetClassId, count($result['valid_students']));
        if ($capacityCheck['exceeds_capacity']) {
            $result['capacity_warning'] = $capacityCheck['message'];
        }
    }
    
    // Set overall validity
    $result['valid'] = !empty($result['valid_students']);
    
    return $result;
}


/**
 * Get placement statistics for progress tracking
 * Returns counts of placed, pending, and conflict students
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return array Statistics including total, placed, pending, and conflict counts
 * 
 * Requirements: 7.1, 7.2, 7.4
 */
function getPlacementStats(int $sourceSchoolYearId, int $targetSchoolYearId): array
{
    $stats = [
        'total_eligible' => 0,
        'placed' => 0,
        'pending' => 0,
        'conflicts' => 0,
        'unassigned' => 0,
        'progress_percentage' => 0,
        'is_complete' => false
    ];
    
    if ($sourceSchoolYearId <= 0 || $targetSchoolYearId <= 0) {
        return $stats;
    }
    
    // Get total eligible students from source school year
    // These are students enrolled in source year who are active
    $totalSql = "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_classes sc ON s.id = sc.student_id
                 INNER JOIN classes c ON sc.class_id = c.id
                 WHERE c.school_year_id = ?
                   AND sc.is_active = 1
                   AND s.is_active = 1
                   AND c.is_active = 1";
    
    $totalResult = dbFetchOne($totalSql, [$sourceSchoolYearId]);
    $stats['total_eligible'] = $totalResult ? (int) $totalResult['count'] : 0;
    
    if ($stats['total_eligible'] === 0) {
        return $stats;
    }
    
    // Get students already placed (enrolled in target school year in DB)
    $placedSql = "SELECT COUNT(DISTINCT s.id) AS count
                  FROM students s
                  INNER JOIN student_classes sc_source ON s.id = sc_source.student_id
                  INNER JOIN classes c_source ON sc_source.class_id = c_source.id
                  INNER JOIN student_classes sc_target ON s.id = sc_target.student_id
                  INNER JOIN classes c_target ON sc_target.class_id = c_target.id
                  WHERE c_source.school_year_id = ?
                    AND sc_source.is_active = 1
                    AND s.is_active = 1
                    AND c_source.is_active = 1
                    AND c_target.school_year_id = ?
                    AND sc_target.is_active = 1
                    AND c_target.is_active = 1";
    
    $placedResult = dbFetchOne($placedSql, [$sourceSchoolYearId, $targetSchoolYearId]);
    $stats['placed'] = $placedResult ? (int) $placedResult['count'] : 0;
    
    // Get pending placements from session
    initPlacementSession();
    $pendingPlacements = $_SESSION['placement']['assignments'] ?? [];
    
    // Count pending placements that are for the target school year
    $pendingCount = 0;
    foreach ($pendingPlacements as $studentId => $classId) {
        $classSchoolYearId = getClassSchoolYearId((int) $classId);
        if ($classSchoolYearId === $targetSchoolYearId) {
            $pendingCount++;
        }
    }
    $stats['pending'] = $pendingCount;
    
    // Conflicts are students who have issues (already enrolled, inactive, etc.)
    // For now, we count students who are in pending but also already placed as conflicts
    $conflictCount = 0;
    foreach ($pendingPlacements as $studentId => $classId) {
        $classSchoolYearId = getClassSchoolYearId((int) $classId);
        if ($classSchoolYearId === $targetSchoolYearId) {
            // Check if student is already enrolled in target year
            if (isStudentEnrolledInSchoolYear((int) $studentId, $targetSchoolYearId)) {
                $conflictCount++;
            }
        }
    }
    $stats['conflicts'] = $conflictCount;
    
    // Adjust pending count to exclude conflicts
    $stats['pending'] = max(0, $stats['pending'] - $stats['conflicts']);
    
    // Calculate unassigned (not placed and not pending)
    $stats['unassigned'] = max(0, $stats['total_eligible'] - $stats['placed'] - $stats['pending']);
    
    // Calculate progress percentage (placed + pending without conflicts)
    $assignedCount = $stats['placed'] + $stats['pending'];
    if ($stats['total_eligible'] > 0) {
        $stats['progress_percentage'] = round(($assignedCount / $stats['total_eligible']) * 100, 1);
    }
    
    // Check if placement is complete (all students placed or pending)
    $stats['is_complete'] = ($stats['unassigned'] === 0 && $stats['conflicts'] === 0);
    
    return $stats;
}

/**
 * Get student distribution across target classes
 * Shows how many students are assigned to each class in the target school year
 * 
 * @param int $targetSchoolYearId Target school year ID
 * @param bool $includePending Whether to include pending placements from session
 * @return array Array of classes with their enrollment counts
 * 
 * Requirements: 7.4
 */
function getClassDistribution(int $targetSchoolYearId, bool $includePending = true): array
{
    if ($targetSchoolYearId <= 0) {
        return [];
    }
    
    // Get all classes for the target school year with their current enrollment
    $sql = "SELECT c.id,
                   c.grade_level,
                   c.section,
                   c.max_capacity,
                   u.full_name AS teacher_name,
                   COUNT(sc.id) AS enrolled_count
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            LEFT JOIN student_classes sc ON c.id = sc.class_id AND sc.is_active = 1
            WHERE c.school_year_id = ?
              AND c.is_active = 1
            GROUP BY c.id, c.grade_level, c.section, c.max_capacity, u.full_name
            ORDER BY c.grade_level, c.section";
    
    $classes = dbFetchAll($sql, [$targetSchoolYearId]);
    
    if (empty($classes)) {
        return [];
    }
    
    // Add pending placements from session if requested
    $pendingByClass = [];
    if ($includePending) {
        initPlacementSession();
        $pendingPlacements = $_SESSION['placement']['assignments'] ?? [];
        
        foreach ($pendingPlacements as $studentId => $classId) {
            $classId = (int) $classId;
            // Verify this class belongs to target school year
            $classSchoolYearId = getClassSchoolYearId($classId);
            if ($classSchoolYearId === $targetSchoolYearId) {
                if (!isset($pendingByClass[$classId])) {
                    $pendingByClass[$classId] = 0;
                }
                $pendingByClass[$classId]++;
            }
        }
    }
    
    // Enhance class data with additional statistics
    $distribution = [];
    foreach ($classes as $class) {
        $classId = (int) $class['id'];
        $enrolledCount = (int) $class['enrolled_count'];
        $pendingCount = $pendingByClass[$classId] ?? 0;
        $maxCapacity = (int) ($class['max_capacity'] ?? 50);
        
        $totalCount = $enrolledCount + $pendingCount;
        $availableSlots = max(0, $maxCapacity - $totalCount);
        $capacityPercentage = $maxCapacity > 0 ? round(($totalCount / $maxCapacity) * 100, 1) : 0;
        
        // Determine capacity status
        $capacityStatus = 'normal';
        if ($totalCount >= $maxCapacity) {
            $capacityStatus = 'full';
        } elseif ($capacityPercentage >= 90) {
            $capacityStatus = 'warning';
        }
        
        $distribution[] = [
            'class_id' => $classId,
            'grade_level' => $class['grade_level'],
            'section' => $class['section'],
            'teacher_name' => $class['teacher_name'],
            'max_capacity' => $maxCapacity,
            'enrolled_count' => $enrolledCount,
            'pending_count' => $pendingCount,
            'total_count' => $totalCount,
            'available_slots' => $availableSlots,
            'capacity_percentage' => $capacityPercentage,
            'capacity_status' => $capacityStatus,
            'display_name' => $class['grade_level'] . ' - ' . $class['section']
        ];
    }
    
    return $distribution;
}

/**
 * Get summary statistics for class distribution
 * Provides aggregate data across all classes
 * 
 * @param int $targetSchoolYearId Target school year ID
 * @return array Summary statistics
 * 
 * Requirements: 7.4
 */
function getClassDistributionSummary(int $targetSchoolYearId): array
{
    $distribution = getClassDistribution($targetSchoolYearId, true);
    
    $summary = [
        'total_classes' => count($distribution),
        'total_capacity' => 0,
        'total_enrolled' => 0,
        'total_pending' => 0,
        'total_students' => 0,
        'total_available' => 0,
        'classes_at_capacity' => 0,
        'classes_near_capacity' => 0,
        'average_class_size' => 0,
        'overall_capacity_percentage' => 0
    ];
    
    if (empty($distribution)) {
        return $summary;
    }
    
    foreach ($distribution as $class) {
        $summary['total_capacity'] += $class['max_capacity'];
        $summary['total_enrolled'] += $class['enrolled_count'];
        $summary['total_pending'] += $class['pending_count'];
        $summary['total_students'] += $class['total_count'];
        $summary['total_available'] += $class['available_slots'];
        
        if ($class['capacity_status'] === 'full') {
            $summary['classes_at_capacity']++;
        } elseif ($class['capacity_status'] === 'warning') {
            $summary['classes_near_capacity']++;
        }
    }
    
    // Calculate averages
    if ($summary['total_classes'] > 0) {
        $summary['average_class_size'] = round($summary['total_students'] / $summary['total_classes'], 1);
    }
    
    if ($summary['total_capacity'] > 0) {
        $summary['overall_capacity_percentage'] = round(
            ($summary['total_students'] / $summary['total_capacity']) * 100, 
            1
        );
    }
    
    return $summary;
}


/**
 * Export placement preview to CSV
 * Generates a CSV file with all students and their placement status
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return array Result with success status, filepath, and download info
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 */
function exportPlacementPreview(int $sourceSchoolYearId, int $targetSchoolYearId): array
{
    require_once __DIR__ . '/logger.php';
    require_once __DIR__ . '/schoolyear.php';
    
    $result = [
        'success' => false,
        'filepath' => null,
        'filename' => null,
        'error' => null,
        'record_count' => 0
    ];
    
    if ($sourceSchoolYearId <= 0 || $targetSchoolYearId <= 0) {
        $result['error'] = 'Invalid school year IDs';
        return $result;
    }
    
    // Get school year names for filename and header (Requirement 11.5)
    $sourceSchoolYear = getSchoolYearById($sourceSchoolYearId);
    $targetSchoolYear = getSchoolYearById($targetSchoolYearId);
    
    if ($sourceSchoolYear === null || $targetSchoolYear === null) {
        $result['error'] = 'School year not found';
        return $result;
    }
    
    $sourceSchoolYearName = $sourceSchoolYear['name'];
    $targetSchoolYearName = $targetSchoolYear['name'];
    
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Build filename with timestamp and school year info (Requirement 11.5)
        $timestamp = date('Y-m-d_His');
        $sourceYearForFilename = str_replace('-', '_', $sourceSchoolYearName);
        $targetYearForFilename = str_replace('-', '_', $targetSchoolYearName);
        $filename = "placement_preview_SY{$sourceYearForFilename}_to_SY{$targetYearForFilename}_{$timestamp}.csv";
        $filepath = $exportDir . '/' . $filename;
        
        // Open file for writing
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception('Failed to create CSV file');
        }
        
        // Write UTF-8 BOM for Excel compatibility
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header information (Requirement 11.5)
        fputcsv($file, ['Student Placement Preview']);
        fputcsv($file, ['Source School Year: ' . $sourceSchoolYearName]);
        fputcsv($file, ['Target School Year: ' . $targetSchoolYearName]);
        fputcsv($file, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($file, []); // Empty row for spacing
        
        // Write column headers (Requirement 11.2, 11.3)
        $headers = [
            'Student Name',
            'LRN',
            'Source Class',
            'Target Class',
            'Status'
        ];
        fputcsv($file, $headers);
        
        // Get all eligible students from source school year
        $eligibleStudents = getEligibleStudents($sourceSchoolYearId, $targetSchoolYearId);
        
        // Get pending placements from session
        initPlacementSession();
        $pendingPlacements = $_SESSION['placement']['assignments'] ?? [];
        
        // Get target classes info for lookup
        $targetClasses = getAvailableTargetClasses($targetSchoolYearId);
        $classLookup = [];
        foreach ($targetClasses as $class) {
            $classLookup[$class['id']] = $class['grade_level'] . ' - ' . $class['section'];
        }
        
        // Get students already placed in target school year (from DB)
        $placedStudents = getStudentsPlacedInTargetYear($sourceSchoolYearId, $targetSchoolYearId);
        $placedStudentIds = array_column($placedStudents, 'id');
        
        $recordCount = 0;
        
        // Process eligible students (not yet placed in DB)
        foreach ($eligibleStudents as $student) {
            $studentId = (int) $student['id'];
            $studentName = trim($student['last_name'] . ', ' . $student['first_name']);
            $lrn = $student['lrn'] ?? '';
            $sourceClass = $student['source_grade_level'] . ' - ' . $student['source_section'];
            
            // Determine target class and status (Requirement 11.3)
            $targetClass = '';
            $status = 'Pending';
            
            if (isset($pendingPlacements[$studentId])) {
                $targetClassId = (int) $pendingPlacements[$studentId];
                $targetClass = $classLookup[$targetClassId] ?? 'Unknown Class';
                $status = 'Assigned';
            }
            
            // Write row
            fputcsv($file, [
                $studentName,
                $lrn,
                $sourceClass,
                $targetClass,
                $status
            ]);
            $recordCount++;
        }
        
        // Process students already placed in target year (from DB)
        foreach ($placedStudents as $student) {
            $studentName = trim($student['last_name'] . ', ' . $student['first_name']);
            $lrn = $student['lrn'] ?? '';
            $sourceClass = $student['source_grade_level'] . ' - ' . $student['source_section'];
            $targetClass = $student['target_grade_level'] . ' - ' . $student['target_section'];
            $status = 'Placed';
            
            // Check for conflict (has pending placement but already placed)
            $studentId = (int) $student['id'];
            if (isset($pendingPlacements[$studentId])) {
                $status = 'Conflict';
            }
            
            // Write row
            fputcsv($file, [
                $studentName,
                $lrn,
                $sourceClass,
                $targetClass,
                $status
            ]);
            $recordCount++;
        }
        
        fclose($file);
        
        $result['success'] = true;
        $result['filepath'] = $filepath;
        $result['filename'] = $filename;
        $result['record_count'] = $recordCount;
        
        // Log successful export
        logInfo('Placement preview exported', [
            'filename' => $filename,
            'record_count' => $recordCount,
            'source_school_year' => $sourceSchoolYearName,
            'target_school_year' => $targetSchoolYearName
        ]);
        
    } catch (Exception $e) {
        $result['error'] = 'Export failed: ' . $e->getMessage();
        
        logError('Placement preview export failed', [
            'error' => $e->getMessage(),
            'source_school_year_id' => $sourceSchoolYearId,
            'target_school_year_id' => $targetSchoolYearId
        ]);
    }
    
    return $result;
}

/**
 * Get students from source school year who are already placed in target school year
 * Used for export to show complete picture of all students
 * 
 * @param int $sourceSchoolYearId Source school year ID
 * @param int $targetSchoolYearId Target school year ID
 * @return array List of students with source and target class info
 */
function getStudentsPlacedInTargetYear(int $sourceSchoolYearId, int $targetSchoolYearId): array
{
    if ($sourceSchoolYearId <= 0 || $targetSchoolYearId <= 0) {
        return [];
    }
    
    $sql = "SELECT DISTINCT 
                s.id,
                s.student_id AS student_code,
                s.lrn,
                s.first_name,
                s.last_name,
                c_source.grade_level AS source_grade_level,
                c_source.section AS source_section,
                c_target.grade_level AS target_grade_level,
                c_target.section AS target_section,
                c_target.id AS target_class_id
            FROM students s
            INNER JOIN student_classes sc_source ON s.id = sc_source.student_id
            INNER JOIN classes c_source ON sc_source.class_id = c_source.id
            INNER JOIN student_classes sc_target ON s.id = sc_target.student_id
            INNER JOIN classes c_target ON sc_target.class_id = c_target.id
            WHERE c_source.school_year_id = ?
              AND sc_source.is_active = 1
              AND s.is_active = 1
              AND c_source.is_active = 1
              AND c_target.school_year_id = ?
              AND sc_target.is_active = 1
              AND c_target.is_active = 1
            ORDER BY c_source.grade_level, c_source.section, s.last_name, s.first_name";
    
    return dbFetchAll($sql, [$sourceSchoolYearId, $targetSchoolYearId]);
}

/**
 * Download placement preview CSV file to browser
 * Triggers file download (Requirement 11.4)
 * 
 * @param string $filepath Full path to CSV file
 * @param string|null $downloadName Optional filename for download
 * @return void
 * 
 * Requirements: 11.4
 */
function downloadPlacementPreview(string $filepath, ?string $downloadName = null): void
{
    require_once __DIR__ . '/logger.php';
    
    if (!file_exists($filepath)) {
        logError('Placement preview file not found for download', ['filepath' => $filepath]);
        die('File not found');
    }
    
    if ($downloadName === null) {
        $downloadName = basename($filepath);
    }
    
    // Set headers for download (Requirement 11.4)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    
    logInfo('Placement preview downloaded', ['filename' => $downloadName]);
    
    exit;
}


/**
 * Get students assigned to a specific class (both pending and saved)
 * Used for the Review by Class view
 * 
 * @param int $classId Target class ID
 * @param int $sourceSchoolYearId Source school year ID (for source class info)
 * @return array Array of students with their source and assignment info
 * 
 * Requirements: 6.2, 6.3
 */
function getStudentsByTargetClass(int $classId, int $sourceSchoolYearId): array
{
    if ($classId <= 0) {
        return [];
    }
    
    $students = [];
    
    // Get students already enrolled in this class (saved to DB)
    $savedSql = "SELECT 
                    s.id,
                    s.student_id AS student_code,
                    s.lrn,
                    s.first_name,
                    s.last_name,
                    sc_target.enrolled_at,
                    'saved' AS assignment_status,
                    c_source.grade_level AS source_grade_level,
                    c_source.section AS source_section,
                    c_source.id AS source_class_id
                 FROM students s
                 INNER JOIN student_classes sc_target ON s.id = sc_target.student_id
                 LEFT JOIN student_classes sc_source ON s.id = sc_source.student_id AND sc_source.is_active = 1
                 LEFT JOIN classes c_source ON sc_source.class_id = c_source.id AND c_source.school_year_id = ?
                 WHERE sc_target.class_id = ?
                   AND sc_target.is_active = 1
                   AND s.is_active = 1
                 ORDER BY s.last_name, s.first_name";
    
    $savedStudents = dbFetchAll($savedSql, [$sourceSchoolYearId, $classId]);
    
    foreach ($savedStudents as $student) {
        $students[$student['id']] = [
            'id' => $student['id'],
            'student_code' => $student['student_code'],
            'lrn' => $student['lrn'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'full_name' => $student['last_name'] . ', ' . $student['first_name'],
            'source_grade_level' => $student['source_grade_level'] ?? 'N/A',
            'source_section' => $student['source_section'] ?? 'N/A',
            'source_class_id' => $student['source_class_id'],
            'source_class_display' => ($student['source_grade_level'] ?? 'N/A') . ' - ' . ($student['source_section'] ?? 'N/A'),
            'assignment_status' => 'saved',
            'enrolled_at' => $student['enrolled_at']
        ];
    }
    
    // Get students with pending placements for this class (from session)
    initPlacementSession();
    $pendingPlacements = $_SESSION['placement']['assignments'] ?? [];
    
    $pendingStudentIds = [];
    foreach ($pendingPlacements as $studentId => $pendingClassId) {
        if ((int) $pendingClassId === $classId && !isset($students[$studentId])) {
            $pendingStudentIds[] = (int) $studentId;
        }
    }
    
    if (!empty($pendingStudentIds)) {
        // Get student details for pending placements
        $placeholders = implode(',', array_fill(0, count($pendingStudentIds), '?'));
        $pendingSql = "SELECT 
                          s.id,
                          s.student_id AS student_code,
                          s.lrn,
                          s.first_name,
                          s.last_name,
                          c_source.grade_level AS source_grade_level,
                          c_source.section AS source_section,
                          c_source.id AS source_class_id
                       FROM students s
                       LEFT JOIN student_classes sc_source ON s.id = sc_source.student_id AND sc_source.is_active = 1
                       LEFT JOIN classes c_source ON sc_source.class_id = c_source.id AND c_source.school_year_id = ?
                       WHERE s.id IN ($placeholders)
                         AND s.is_active = 1
                       ORDER BY s.last_name, s.first_name";
        
        $params = array_merge([$sourceSchoolYearId], $pendingStudentIds);
        $pendingStudents = dbFetchAll($pendingSql, $params);
        
        foreach ($pendingStudents as $student) {
            $students[$student['id']] = [
                'id' => $student['id'],
                'student_code' => $student['student_code'],
                'lrn' => $student['lrn'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'full_name' => $student['last_name'] . ', ' . $student['first_name'],
                'source_grade_level' => $student['source_grade_level'] ?? 'N/A',
                'source_section' => $student['source_section'] ?? 'N/A',
                'source_class_id' => $student['source_class_id'],
                'source_class_display' => ($student['source_grade_level'] ?? 'N/A') . ' - ' . ($student['source_section'] ?? 'N/A'),
                'assignment_status' => 'pending',
                'enrolled_at' => null
            ];
        }
    }
    
    // Sort by last name, first name
    usort($students, function($a, $b) {
        $lastNameCmp = strcmp($a['last_name'], $b['last_name']);
        if ($lastNameCmp !== 0) return $lastNameCmp;
        return strcmp($a['first_name'], $b['first_name']);
    });
    
    return array_values($students);
}

/**
 * Get all classes with their assigned students for Review by Class view
 * 
 * @param int $targetSchoolYearId Target school year ID
 * @param int $sourceSchoolYearId Source school year ID
 * @return array Array of classes with their students
 * 
 * Requirements: 6.1, 6.2, 6.3
 */
function getClassesWithStudents(int $targetSchoolYearId, int $sourceSchoolYearId): array
{
    if ($targetSchoolYearId <= 0) {
        return [];
    }
    
    // Get class distribution first
    $distribution = getClassDistribution($targetSchoolYearId, true);
    
    // Enhance each class with its students
    foreach ($distribution as &$class) {
        $class['students'] = getStudentsByTargetClass($class['class_id'], $sourceSchoolYearId);
        $class['student_count'] = count($class['students']);
        $class['saved_count'] = count(array_filter($class['students'], fn($s) => $s['assignment_status'] === 'saved'));
        $class['pending_count'] = count(array_filter($class['students'], fn($s) => $s['assignment_status'] === 'pending'));
    }
    
    return $distribution;
}


/**
 * Update enrollment status for a student in a class
 * Handles withdrawal, dropout, and transfer out scenarios
 * 
 * @param int $studentId Student ID
 * @param int $classId Current class ID
 * @param string $newStatus New status (withdrawn, dropped, transferred_out)
 * @param int $changedBy User ID making the change
 * @param string $reason Reason for the status change
 * @return array Result with success status and message
 */
function updateEnrollmentStatus(int $studentId, int $classId, string $newStatus, int $changedBy, string $reason = ''): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'message' => ''
    ];
    
    // Validate status
    $validStatuses = ['active', 'withdrawn', 'dropped', 'transferred_out', 'completed'];
    if (!in_array($newStatus, $validStatuses)) {
        $result['message'] = 'Invalid enrollment status';
        return $result;
    }
    
    // Get current enrollment
    $sql = "SELECT sc.*, c.grade_level, c.section, c.school_year_id,
                   s.first_name, s.last_name
            FROM student_classes sc
            JOIN classes c ON sc.class_id = c.id
            JOIN students s ON sc.student_id = s.id
            WHERE sc.student_id = ? AND sc.class_id = ? AND sc.is_active = 1";
    $enrollment = dbFetchOne($sql, [$studentId, $classId]);
    
    if (!$enrollment) {
        $result['message'] = 'Student is not enrolled in this class';
        return $result;
    }
    
    // Check if school year is locked
    if (isEnrollmentLocked($enrollment['school_year_id'])) {
        $result['message'] = 'Cannot modify enrollment - school year is locked';
        return $result;
    }
    
    try {
        // Update the enrollment status and deactivate
        $updateSql = "UPDATE student_classes 
                      SET enrollment_status = ?, 
                          is_active = 0,
                          status_changed_at = NOW(),
                          status_changed_by = ?,
                          status_reason = ?
                      WHERE student_id = ? AND class_id = ? AND is_active = 1";
        
        $affected = dbExecute($updateSql, [$newStatus, $changedBy, $reason, $studentId, $classId]);
        
        if ($affected > 0) {
            $result['success'] = true;
            
            $statusLabels = [
                'withdrawn' => 'withdrawn',
                'dropped' => 'dropped out',
                'transferred_out' => 'transferred to another school'
            ];
            $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
            
            $result['message'] = sprintf(
                '%s %s has been marked as %s from %s - %s',
                $enrollment['first_name'],
                $enrollment['last_name'],
                $statusLabel,
                $enrollment['grade_level'],
                $enrollment['section']
            );
            
            // Log the status change
            logInfo('Student enrollment status changed', [
                'student_id' => $studentId,
                'student_name' => $enrollment['first_name'] . ' ' . $enrollment['last_name'],
                'class_id' => $classId,
                'class_name' => $enrollment['grade_level'] . ' - ' . $enrollment['section'],
                'new_status' => $newStatus,
                'reason' => $reason,
                'changed_by' => $changedBy
            ]);
        } else {
            $result['message'] = 'Failed to update enrollment status';
        }
    } catch (Exception $e) {
        $result['message'] = 'Database error: ' . $e->getMessage();
        logError('Failed to update enrollment status', [
            'student_id' => $studentId,
            'class_id' => $classId,
            'new_status' => $newStatus,
            'error' => $e->getMessage()
        ]);
    }
    
    return $result;
}

/**
 * Transfer a student from one class to another within the same school year
 * 
 * @param int $studentId Student ID
 * @param int $fromClassId Current class ID
 * @param int $toClassId Target class ID
 * @param int $transferredBy User ID making the transfer
 * @param string $reason Reason for the transfer
 * @return array Result with success status and message
 */
function transferStudentToClass(int $studentId, int $fromClassId, int $toClassId, int $transferredBy, string $reason = ''): array
{
    require_once __DIR__ . '/logger.php';
    
    $result = [
        'success' => false,
        'message' => ''
    ];
    
    if ($fromClassId === $toClassId) {
        $result['message'] = 'Cannot transfer to the same class';
        return $result;
    }
    
    // Get current enrollment
    $sql = "SELECT sc.*, c.grade_level, c.section, c.school_year_id,
                   s.first_name, s.last_name
            FROM student_classes sc
            JOIN classes c ON sc.class_id = c.id
            JOIN students s ON sc.student_id = s.id
            WHERE sc.student_id = ? AND sc.class_id = ? AND sc.is_active = 1";
    $currentEnrollment = dbFetchOne($sql, [$studentId, $fromClassId]);
    
    if (!$currentEnrollment) {
        $result['message'] = 'Student is not enrolled in the source class';
        return $result;
    }
    
    // Get target class info
    $targetSql = "SELECT c.*, u.full_name AS teacher_name 
                  FROM classes c 
                  LEFT JOIN users u ON c.teacher_id = u.id
                  WHERE c.id = ? AND c.is_active = 1";
    $targetClass = dbFetchOne($targetSql, [$toClassId]);
    
    if (!$targetClass) {
        $result['message'] = 'Target class not found';
        return $result;
    }
    
    // Verify same school year
    if ($currentEnrollment['school_year_id'] != $targetClass['school_year_id']) {
        $result['message'] = 'Cannot transfer between different school years. Use student placement instead.';
        return $result;
    }
    
    // Check if school year is locked
    if (isEnrollmentLocked($currentEnrollment['school_year_id'])) {
        $result['message'] = 'Cannot transfer - school year enrollment is locked';
        return $result;
    }
    
    // Check target class capacity
    $capacitySql = "SELECT COUNT(*) AS count FROM student_classes WHERE class_id = ? AND is_active = 1";
    $currentCount = dbFetchOne($capacitySql, [$toClassId]);
    $enrolledCount = $currentCount ? (int)$currentCount['count'] : 0;
    
    if ($targetClass['max_capacity'] && $enrolledCount >= $targetClass['max_capacity']) {
        $result['message'] = sprintf(
            'Target class %s - %s is at full capacity (%d/%d)',
            $targetClass['grade_level'],
            $targetClass['section'],
            $enrolledCount,
            $targetClass['max_capacity']
        );
        return $result;
    }
    
    // Check if student is already enrolled in target class
    $existingSql = "SELECT id FROM student_classes WHERE student_id = ? AND class_id = ? AND is_active = 1";
    $existing = dbFetchOne($existingSql, [$studentId, $toClassId]);
    
    if ($existing) {
        $result['message'] = 'Student is already enrolled in the target class';
        return $result;
    }
    
    try {
        // Start transaction
        dbBeginTransaction();
        
        // Deactivate current enrollment with transferred status
        $deactivateSql = "UPDATE student_classes 
                          SET enrollment_status = 'transferred_out',
                              is_active = 0,
                              status_changed_at = NOW(),
                              status_changed_by = ?,
                              status_reason = ?
                          WHERE student_id = ? AND class_id = ? AND is_active = 1";
        dbExecute($deactivateSql, [$transferredBy, $reason ?: 'Transferred to ' . $targetClass['grade_level'] . ' - ' . $targetClass['section'], $studentId, $fromClassId]);
        
        // Create new enrollment in target class
        $insertSql = "INSERT INTO student_classes (student_id, class_id, enrolled_at, enrolled_by, is_active, enrollment_status, enrollment_type, status_reason)
                      VALUES (?, ?, NOW(), ?, 1, 'active', 'transferee', ?)";
        dbExecute($insertSql, [$studentId, $toClassId, $transferredBy, 'Transferred from ' . $currentEnrollment['grade_level'] . ' - ' . $currentEnrollment['section']]);
        
        dbCommit();
        
        $result['success'] = true;
        $result['message'] = sprintf(
            '%s %s has been transferred from %s - %s to %s - %s',
            $currentEnrollment['first_name'],
            $currentEnrollment['last_name'],
            $currentEnrollment['grade_level'],
            $currentEnrollment['section'],
            $targetClass['grade_level'],
            $targetClass['section']
        );
        
        // Log the transfer
        logInfo('Student transferred between classes', [
            'student_id' => $studentId,
            'student_name' => $currentEnrollment['first_name'] . ' ' . $currentEnrollment['last_name'],
            'from_class_id' => $fromClassId,
            'from_class' => $currentEnrollment['grade_level'] . ' - ' . $currentEnrollment['section'],
            'to_class_id' => $toClassId,
            'to_class' => $targetClass['grade_level'] . ' - ' . $targetClass['section'],
            'reason' => $reason,
            'transferred_by' => $transferredBy
        ]);
        
    } catch (Exception $e) {
        dbRollback();
        $result['message'] = 'Transfer failed: ' . $e->getMessage();
        logError('Failed to transfer student', [
            'student_id' => $studentId,
            'from_class_id' => $fromClassId,
            'to_class_id' => $toClassId,
            'error' => $e->getMessage()
        ]);
    }
    
    return $result;
}

/**
 * Get enrollment status history for a student
 * 
 * @param int $studentId Student ID
 * @return array Array of enrollment status changes
 */
function getEnrollmentStatusHistory(int $studentId): array
{
    $sql = "SELECT sc.*, 
                   c.grade_level, c.section,
                   sy.name AS school_year_name,
                   u.full_name AS changed_by_name
            FROM student_classes sc
            JOIN classes c ON sc.class_id = c.id
            JOIN school_years sy ON c.school_year_id = sy.id
            LEFT JOIN users u ON sc.status_changed_by = u.id
            WHERE sc.student_id = ?
            ORDER BY sc.enrolled_at DESC, sc.status_changed_at DESC";
    
    return dbFetchAll($sql, [$studentId]);
}
