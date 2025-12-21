# Implementation Plan

- [x] 1. Database schema updates





  - [x] 1.1 Create migration file for school_years.is_locked column


    - Add `is_locked TINYINT(1) DEFAULT 0` to school_years table
    - _Requirements: 9.1, 9.3, 9.4_
  - [x] 1.2 Create migration file for classes.max_capacity column


    - Add `max_capacity INT DEFAULT 50` to classes table
    - _Requirements: 12.1, 12.5_

- [-] 2. Core placement service




  - [x] 2.1 Create `includes/placement.php` with base functions


    - Implement `getEligibleStudents()` function
    - Implement `getSuggestedGrade()` function
    - Implement `filterStudents()` and `getFilterOptions()` functions
    - _Requirements: 1.2, 2.2, 2.3, 5.1_
  - [ ]* 2.2 Write property test for eligible students retrieval
    - **Property 1: Eligible Students Retrieval**
    - **Validates: Requirements 1.2**
  - [ ]* 2.3 Write property test for filter correctness
    - **Property 2: Filter Correctness**
    - **Validates: Requirements 2.2, 2.3, 2.4**
  - [ ]* 2.4 Write property test for grade suggestion
    - **Property 5: Grade Suggestion Calculation**
    - **Validates: Requirements 5.1, 5.2**

- [x] 3. Placement assignment functions





  - [x] 3.1 Implement bulk assignment function


    - Create `bulkAssignStudents()` function
    - Handle conflict detection and reporting
    - Return success/skipped counts
    - _Requirements: 3.2, 3.3, 3.4_

  - [x] 3.2 Implement individual assignment function

    - Create `assignStudentPlacement()` function
    - Create `removePendingPlacement()` function
    - _Requirements: 4.2, 4.4_
  - [ ]* 3.3 Write property test for bulk assignment integrity
    - **Property 3: Bulk Assignment Integrity**
    - **Validates: Requirements 3.2, 3.3, 3.4**

- [x] 4. Session-based state management





  - [x] 4.1 Implement placement session functions


    - Create `initPlacementSession()` function
    - Create `getPlacementSession()` function
    - Create `updatePlacementSession()` function
    - Create `clearPlacementSession()` function
    - _Requirements: 3.5, 8.4_
  - [x] 4.2 Implement undo stack functions


    - Create `pushUndoAction()` function
    - Create `popUndoAction()` function
    - Create `clearUndoStack()` function
    - Create `getUndoStackSize()` function
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  - [ ]* 4.3 Write property test for undo stack LIFO behavior
    - **Property 10: Undo Stack LIFO Behavior**
    - **Validates: Requirements 10.2, 10.3, 10.4**

- [x] 5. Save and validation functions





  - [x] 5.1 Implement save placements function


    - Create `savePlacements()` with transaction support
    - Implement rollback on failure
    - Return detailed results
    - _Requirements: 8.1, 8.2, 8.3, 8.5_

  - [x] 5.2 Implement validation functions

    - Create `validatePlacement()` function
    - Create `checkClassCapacity()` function
    - _Requirements: 1.5, 12.2_
  - [ ]* 5.3 Write property test for transaction atomicity
    - **Property 8: Transaction Atomicity**
    - **Validates: Requirements 8.1, 8.2, 8.3**
  - [ ]* 5.4 Write property test for source data preservation
    - **Property 4: Source Data Preservation**
    - **Validates: Requirements 3.5, 8.4**

- [x] 6. Lock enrollment feature





  - [x] 6.1 Implement lock functions


    - Create `isEnrollmentLocked()` function
    - Create `lockEnrollment()` function
    - Create `unlockEnrollment()` function (admin only)
    - _Requirements: 9.1, 9.2, 9.3, 9.4_
  - [ ]* 6.2 Write property test for lock enforcement
    - **Property 9: Lock Enforcement**
    - **Validates: Requirements 9.3, 9.4**
-

- [x] 7. Statistics and progress tracking



  - [x] 7.1 Implement statistics functions


    - Create `getPlacementStats()` function
    - Create `getClassDistribution()` function
    - _Requirements: 7.1, 7.2, 7.4_
  - [ ]* 7.2 Write property test for progress statistics consistency
    - **Property 7: Progress Statistics Consistency**
    - **Validates: Requirements 7.1, 7.2, 7.4**


- [x] 8. Export functionality




  - [x] 8.1 Implement export function


    - Create `exportPlacementPreview()` function
    - Generate CSV with required columns
    - Include timestamp in filename
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_
  - [ ]* 8.2 Write property test for export content completeness
    - **Property 11: Export Content Completeness**
    - **Validates: Requirements 11.2, 11.3, 11.5**

- [x] 9. Checkpoint - Ensure all backend tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Student Placement Page UI


  - [x] 10.1 Create `pages/student-placement.php` base structure


    - Add page header and navigation
    - Add school year selectors (source/target)
    - Add filter controls (grade, section)
    - _Requirements: 1.1, 2.1_
  - [x] 10.2 Implement student list with checkboxes


    - Display eligible students in table format
    - Show current class, suggested grade, status
    - Add select all/none functionality
    - _Requirements: 3.1, 5.2, 5.3_
  - [x] 10.3 Implement bulk assignment controls


    - Add target class dropdown
    - Add Apply button with confirmation
    - Display success/error messages
    - _Requirements: 3.2, 3.3_

  - [x] 10.4 Implement individual edit functionality

    - Add edit button per row
    - Create inline edit or modal for class selection
    - Add repeater checkbox option
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [x] 11. Progress and review UI





  - [x] 11.1 Implement progress indicator


    - Show "X of Y students placed" counter
    - Show status breakdown (placed, pending, conflicts)
    - Add completion notification
    - _Requirements: 7.1, 7.2, 7.3, 7.5_
  - [x] 11.2 Implement Review by Class view


    - Add tab or toggle for class-based view
    - Show students per class with source info
    - Add remove/move actions
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_
  - [ ]* 11.3 Write property test for class review data completeness
    - **Property 6: Class Review Data Completeness**
    - **Validates: Requirements 6.2, 6.3**
-

- [x] 12. Capacity warnings UI




  - [x] 12.1 Implement capacity display and warnings

    - Show current/max capacity per class
    - Add warning indicators at threshold
    - Add confirmation dialog for over-capacity
    - _Requirements: 12.1, 12.2, 12.3, 12.4_
  - [ ]* 12.2 Write property test for capacity warning accuracy
    - **Property 12: Capacity Warning Accuracy**
    - **Validates: Requirements 12.1, 12.2, 12.3**

- [x] 13. Action buttons and finalization





  - [x] 13.1 Implement undo button


    - Add Undo button with stack count indicator
    - Handle undo action and UI refresh
    - _Requirements: 10.1, 10.2, 10.5_
  - [x] 13.2 Implement save and export buttons

    - Add Save Placement button with confirmation
    - Add Export Preview button
    - Show save results summary
    - _Requirements: 8.1, 8.5, 11.1, 11.4_
  - [x] 13.3 Implement lock enrollment UI

    - Add Lock Enrollment button (when placements saved)
    - Add confirmation dialog
    - Show locked status indicator
    - _Requirements: 9.1, 9.2, 9.5_

- [x] 14. Navigation and access control




  - [x] 14.1 Add Student Placement to sidebar navigation


    - Add menu item under appropriate section
    - Restrict to admin role only
    - _Requirements: 1.1_
  - [x] 14.2 Update school year management page


    - Show lock status in school year list
    - Add lock/unlock actions
    - _Requirements: 9.1, 9.5_

- [x] 15. Final Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
