-- Migration: Add LRN (Learner Reference Number) column to students table
-- LRN will be used as the barcode identifier
-- Run this script to update existing database

-- Add LRN column to students table
ALTER TABLE students
ADD COLUMN lrn VARCHAR(12) UNIQUE NULL AFTER student_id,
ADD INDEX idx_lrn (lrn);

-- Copy existing student_id values to lrn for existing records (optional)
-- Uncomment the line below if you want to migrate existing student_id to lrn
-- UPDATE students SET lrn = student_id WHERE lrn IS NULL;

-- Note: After running this migration, update the following files:
-- 1. pages/student-add.php - Add LRN field and use it for barcode generation
-- 2. pages/student-edit.php - Add LRN field and use it for barcode regeneration
-- 3. pages/student-view.php - Display LRN field
-- 4. includes/barcode.php - Update to use LRN instead of student_id
