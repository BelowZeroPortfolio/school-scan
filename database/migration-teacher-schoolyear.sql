-- Migration: Teacher Role and School Year Management
-- Version: 1.0
-- Date: 2025-12-16
-- Description: Adds school_years, classes, student_classes tables and modifies users/attendance tables
-- Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 3.4, 4.1, 4.2, 7.4, 9.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- 1.1 Create school_years table
-- Requirements: 1.1, 1.2, 1.3
-- ============================================================================

CREATE TABLE IF NOT EXISTS `school_years` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(9) NOT NULL COMMENT 'Format: YYYY-YYYY',
    `is_active` TINYINT(1) DEFAULT 0,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 1.4 Modify users table to add teacher role
-- Requirements: 2.1, 2.2
-- Note: Must be done before creating classes table due to foreign key
-- ============================================================================

ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin', 'operator', 'viewer', 'teacher') NOT NULL DEFAULT 'viewer';

-- ============================================================================
-- 1.2 Create classes table
-- Requirements: 3.1, 3.2, 3.4
-- ============================================================================

CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `grade_level` VARCHAR(20) NOT NULL COMMENT 'e.g., Grade 7, Grade 8',
    `section` VARCHAR(50) NOT NULL COMMENT 'e.g., Section A, Einstein',
    `teacher_id` INT(11) NOT NULL,
    `school_year_id` INT(11) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_class` (`grade_level`, `section`, `school_year_id`),
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_school_year` (`school_year_id`),
    INDEX `idx_grade_section` (`grade_level`, `section`),
    CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_classes_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 1.3 Create student_classes table
-- Requirements: 4.1, 4.2
-- ============================================================================

CREATE TABLE IF NOT EXISTS `student_classes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `class_id` INT(11) NOT NULL,
    `enrolled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `enrolled_by` INT(11) DEFAULT NULL COMMENT 'Admin/Teacher who enrolled the student',
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_student_class` (`student_id`, `class_id`),
    INDEX `idx_student` (`student_id`),
    INDEX `idx_class` (`class_id`),
    INDEX `idx_enrolled_by` (`enrolled_by`),
    CONSTRAINT `fk_student_classes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_student_classes_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_student_classes_enrolled_by` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 1.5 Modify attendance table to add school_year_id
-- Requirements: 7.4, 9.1
-- ============================================================================

ALTER TABLE `attendance` 
ADD COLUMN `school_year_id` INT(11) DEFAULT NULL AFTER `student_id`,
ADD INDEX `idx_school_year` (`school_year_id`),
ADD CONSTRAINT `fk_attendance_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE SET NULL;

-- ============================================================================
-- Insert default school year (optional - can be removed if not needed)
-- ============================================================================

-- INSERT INTO `school_years` (`name`, `is_active`, `start_date`, `end_date`) 
-- VALUES ('2024-2025', 1, '2024-06-01', '2025-03-31');

COMMIT;
