# Requirements Document

## Introduction

The Student Placement feature enables school administrators to efficiently manage the annual process of promoting and assigning students to new classes for a new school year. This feature handles bulk student placement from one school year to another, supporting grade promotion, section assignments, and exception handling for repeaters or transfers. The system preserves historical enrollment data while creating new enrollment records for the target school year.

## Glossary

- **Student Placement**: The process of assigning students from a previous school year to classes in a new school year
- **Source School Year**: The school year from which students are being promoted/moved
- **Target School Year**: The school year to which students are being assigned
- **Grade Promotion**: Moving a student from one grade level to the next (e.g., Grade 6 → Grade 7)
- **Repeater**: A student who remains in the same grade level for another school year
- **Enrollment Record**: A record in `student_classes` linking a student to a specific class
- **Bulk Assignment**: Assigning multiple students to a class in a single operation
- **Placement Lock**: A mechanism to prevent changes to enrollment after finalization
- **Eligible Students**: Active students enrolled in the source school year who have not yet been placed in the target school year

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to select source and target school years for student placement, so that I can move students from one academic year to the next.

#### Acceptance Criteria

1. WHEN the administrator opens the Student Placement page THEN the System SHALL display dropdown selectors for source and target school years
2. WHEN the administrator selects a source school year THEN the System SHALL load all eligible students enrolled in that school year
3. WHEN the administrator selects a target school year THEN the System SHALL load all available classes for that school year
4. IF the target school year has no classes defined THEN the System SHALL display a warning message and disable placement actions
5. IF the source and target school years are the same THEN the System SHALL display an error and prevent placement

### Requirement 2

**User Story:** As an administrator, I want to filter students by their previous grade and section, so that I can work with manageable groups during placement.

#### Acceptance Criteria

1. WHEN students are loaded from the source school year THEN the System SHALL display filter options for grade level and section
2. WHEN the administrator selects a grade level filter THEN the System SHALL show only students from that grade level
3. WHEN the administrator selects a section filter THEN the System SHALL show only students from that section
4. WHEN filters are applied THEN the System SHALL display the count of filtered students
5. WHEN the administrator clears filters THEN the System SHALL show all eligible students from the source school year

### Requirement 3

**User Story:** As an administrator, I want to bulk assign multiple students to a target class, so that I can efficiently place large groups of students.

#### Acceptance Criteria

1. WHEN the administrator selects multiple students using checkboxes THEN the System SHALL enable the bulk assignment action
2. WHEN the administrator chooses a target class and clicks Apply THEN the System SHALL assign all selected students to that class
3. WHEN bulk assignment completes THEN the System SHALL display a success message with the count of students assigned
4. IF a student is already enrolled in a class for the target school year THEN the System SHALL skip that student and report the conflict
5. WHEN bulk assignment is performed THEN the System SHALL create new enrollment records without modifying source school year data

### Requirement 4

**User Story:** As an administrator, I want to individually adjust student placements, so that I can handle exceptions like repeaters or section changes.

#### Acceptance Criteria

1. WHEN viewing the student list THEN the System SHALL display an edit option for each student row
2. WHEN the administrator edits a student's placement THEN the System SHALL allow selection of a different target class
3. WHEN the administrator marks a student as a repeater THEN the System SHALL allow assignment to the same grade level in the target school year
4. WHEN individual changes are made THEN the System SHALL update the placement immediately
5. WHEN a student's placement is changed THEN the System SHALL log the modification with timestamp and user

### Requirement 5

**User Story:** As an administrator, I want to see auto-suggested grade promotions, so that I can quickly verify and apply standard progressions.

#### Acceptance Criteria

1. WHEN students are loaded THEN the System SHALL calculate and display suggested target grade levels based on current enrollment
2. WHEN displaying suggestions THEN the System SHALL show "Grade 6 → Grade 7" format for each student
3. WHEN the administrator applies suggestions THEN the System SHALL use the suggested grade level for class selection
4. IF no classes exist for the suggested grade level THEN the System SHALL indicate this with a warning icon
5. WHEN suggestions are displayed THEN the System SHALL allow the administrator to override any suggestion

### Requirement 6

**User Story:** As an administrator, I want to review placements by target class before finalizing, so that I can verify correct student distribution.

#### Acceptance Criteria

1. WHEN placements are in progress THEN the System SHALL provide a "Review by Class" view
2. WHEN viewing a target class THEN the System SHALL display all students assigned to that class with their source class information
3. WHEN reviewing a class THEN the System SHALL show the total student count and class capacity status
4. WHEN reviewing THEN the System SHALL allow the administrator to remove students from the class
5. WHEN reviewing THEN the System SHALL allow the administrator to move students between classes

### Requirement 7

**User Story:** As an administrator, I want to see placement progress and statistics, so that I can track completion status.

#### Acceptance Criteria

1. WHILE placement is in progress THEN the System SHALL display a progress indicator showing "X of Y students placed"
2. WHEN viewing progress THEN the System SHALL show counts by status: placed, pending, conflicts
3. WHEN all students are placed THEN the System SHALL display a completion notification
4. WHEN viewing statistics THEN the System SHALL show student distribution across target classes
5. WHEN conflicts exist THEN the System SHALL highlight unresolved items requiring attention

### Requirement 8

**User Story:** As an administrator, I want to save and confirm placements, so that enrollment records are created in the database.

#### Acceptance Criteria

1. WHEN the administrator clicks Save Placement THEN the System SHALL create enrollment records in the student_classes table
2. WHEN saving placements THEN the System SHALL use a database transaction to ensure data integrity
3. IF any enrollment fails THEN the System SHALL rollback the transaction and report the error
4. WHEN placements are saved THEN the System SHALL preserve all existing enrollment records from previous school years
5. WHEN save completes successfully THEN the System SHALL display a summary of created enrollments

### Requirement 9

**User Story:** As an administrator, I want to lock a school year's enrollment, so that accidental changes are prevented after finalization.

#### Acceptance Criteria

1. WHEN viewing a school year with completed placements THEN the System SHALL display a Lock Enrollment option
2. WHEN the administrator clicks Lock Enrollment THEN the System SHALL prompt for confirmation
3. WHEN enrollment is locked THEN the System SHALL prevent any new student placements for that school year
4. WHEN enrollment is locked THEN the System SHALL prevent removal of students from classes in that school year
5. WHEN a locked school year is selected as target THEN the System SHALL display "Enrollment Locked" status and disable placement actions

### Requirement 10

**User Story:** As an administrator, I want to undo bulk actions before final save, so that I can correct mistakes during the placement process.

#### Acceptance Criteria

1. WHEN a bulk assignment is performed THEN the System SHALL store the action in a session-based undo stack
2. WHEN the administrator clicks Undo THEN the System SHALL reverse the last bulk action
3. WHEN undo is performed THEN the System SHALL restore students to their previous unassigned state
4. WHEN multiple bulk actions are performed THEN the System SHALL allow undoing each action in reverse order
5. WHEN placements are saved to the database THEN the System SHALL clear the undo stack

### Requirement 11

**User Story:** As an administrator, I want to export the placement preview before saving, so that I can review and share the proposed assignments.

#### Acceptance Criteria

1. WHEN placements are pending THEN the System SHALL provide an Export Preview option
2. WHEN exporting THEN the System SHALL generate a CSV file with student name, LRN, source class, and target class
3. WHEN exporting THEN the System SHALL include placement status (assigned, pending, conflict) for each student
4. WHEN the export completes THEN the System SHALL trigger a file download
5. WHEN exporting THEN the System SHALL include a timestamp and school year information in the filename

### Requirement 12

**User Story:** As an administrator, I want class capacity warnings, so that I can avoid over-enrolling classes.

#### Acceptance Criteria

1. WHEN a class reaches a configurable capacity threshold THEN the System SHALL display a warning indicator
2. WHEN bulk assigning students THEN the System SHALL warn if the assignment would exceed class capacity
3. WHEN viewing class statistics THEN the System SHALL show current enrollment versus capacity
4. WHEN capacity is exceeded THEN the System SHALL allow the administrator to proceed with acknowledgment
5. WHEN configuring the system THEN the System SHALL allow setting default class capacity limits
