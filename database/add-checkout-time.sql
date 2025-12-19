-- Migration: Add check_out_time column to attendance table
-- Run this SQL to add dismissal/time-out support

ALTER TABLE `attendance` 
ADD COLUMN `check_out_time` timestamp NULL DEFAULT NULL AFTER `check_in_time`;

-- Add index for check_out_time queries
ALTER TABLE `attendance` 
ADD INDEX `idx_checkout` (`check_out_time`);
