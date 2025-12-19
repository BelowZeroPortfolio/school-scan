-- Migration: Remove class and section columns from students table
-- Date: 2025-12-19
-- Description: These columns are now redundant as class/section data comes from student_classes -> classes relationship
-- 
-- IMPORTANT: Run this AFTER all code changes have been deployed and tested
-- 

-- Step 1: Verify data migration (run this SELECT first to confirm data is in student_classes)
-- SELECT s.id, s.first_name, s.last_name, s.class, s.section, 
--        c.grade_level, c.section as class_section
-- FROM students s
-- LEFT JOIN student_classes sc ON s.id = sc.student_id AND sc.is_active = 1
-- LEFT JOIN classes c ON sc.class_id = c.id
-- WHERE s.is_active = 1;

-- Step 2: Drop the index first
ALTER TABLE `students` DROP INDEX IF EXISTS `idx_class_section`;

-- Step 3: Drop the columns
ALTER TABLE `students` DROP COLUMN IF EXISTS `class`;
ALTER TABLE `students` DROP COLUMN IF EXISTS `section`;

-- Note: If your MySQL version doesn't support "IF EXISTS" for columns, use:
-- ALTER TABLE `students` DROP INDEX `idx_class_section`;
-- ALTER TABLE `students` DROP COLUMN `class`;
-- ALTER TABLE `students` DROP COLUMN `section`;
