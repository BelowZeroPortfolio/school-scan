-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 23, 2025 at 06:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `school_year_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_out_time` timestamp NULL DEFAULT NULL,
  `status` enum('present','late','absent') DEFAULT 'present',
  `recorded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `school_year_id`, `attendance_date`, `check_in_time`, `check_out_time`, `status`, `recorded_by`, `notes`) VALUES
(17, 14, 2, '2025-12-22', '2025-12-22 12:06:44', '2025-12-22 12:07:23', 'present', 4, NULL),
(18, 14, 2, '2025-12-23', '2025-12-23 04:03:30', '2025-12-23 04:03:59', 'present', 4, NULL),
(19, 15, 2, '2025-12-23', '2025-12-23 04:03:36', '2025-12-23 04:03:43', 'present', 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `grade_level` varchar(20) NOT NULL COMMENT 'e.g., Grade 7, Grade 8',
  `section` varchar(50) NOT NULL COMMENT 'e.g., Section A, Einstein',
  `teacher_id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `max_capacity` int(11) DEFAULT 50 COMMENT 'Maximum students allowed in class',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `grade_level`, `section`, `teacher_id`, `school_year_id`, `is_active`, `max_capacity`, `created_at`, `updated_at`) VALUES
(3, 'Grade 7', 'A', 4, 1, 1, 50, '2025-12-19 07:11:39', '2025-12-19 07:11:39'),
(4, 'Grade 8', 'A', 5, 2, 1, 50, '2025-12-19 07:13:15', '2025-12-19 07:13:15'),
(5, 'Grade 11', 'ESL', 4, 2, 1, 50, '2025-12-22 11:59:28', '2025-12-22 11:59:28');

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `notification_type` enum('sms','email') NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `retry_queue`
--

CREATE TABLE `retry_queue` (
  `id` int(11) NOT NULL,
  `operation_type` varchar(50) NOT NULL,
  `operation_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`operation_data`)),
  `retry_count` int(11) DEFAULT 0,
  `max_retries` int(11) DEFAULT 3,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_settings`
--

INSERT INTO `school_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'school_name', 'SAGAY NATIONAL HIGH SCHOOL', '2025-12-22 10:05:12'),
(2, 'school_logo', 'storage/uploads/school_logo_1766397912.webp', '2025-12-22 10:05:12');

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `name` varchar(9) NOT NULL COMMENT 'Format: YYYY-YYYY',
  `is_active` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'Prevents enrollment changes when locked',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `name`, `is_active`, `is_locked`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, '2024-2025', 0, 0, '2025-01-01', '2025-12-31', '2025-12-16 12:56:40', '2025-12-21 08:35:10'),
(2, '2025-2026', 1, 0, '2025-12-02', '2025-12-25', '2025-12-16 12:57:08', '2025-12-21 09:13:59'),
(3, '2026-2027', 0, 0, '2025-12-08', '2025-12-18', '2025-12-16 14:19:43', '2025-12-16 14:19:43'),
(4, '2027-2028', 0, 0, '2027-01-01', '2027-12-31', '2025-12-21 09:10:45', '2025-12-21 09:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
('107ughcd5t4oebf5vhl22mf36f', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:45:10', '2025-12-16 14:45:10'),
('84tr4mdd0amds2bjrsh2a31cjg', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 07:31:33', '2025-12-19 07:31:33'),
('dnqpfrck34kb9aa15l32ks4ka2', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 09:58:20', '2025-12-21 09:58:20'),
('dqb5k4hc408qev0v99s2v91mib', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:03:11', '2025-12-23 04:03:11'),
('duppurm3ogv076rh82is9q3doh', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:05', '2025-12-23 05:08:05'),
('ed1j9i2j85p2qo703dp3e5o634', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 13:26:13', '2025-12-08 13:26:13'),
('mdf618aj1ikfgda3gqkk5tivb1', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:00:35', '2025-12-19 06:00:35');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `barcode_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL COMMENT 'Path to student photo',
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `sms_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether SMS notifications are enabled for this student (paid subscription)',
  `previous_school` varchar(255) DEFAULT NULL COMMENT 'Previous school name for transferees'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `lrn`, `first_name`, `last_name`, `barcode_path`, `photo_path`, `parent_name`, `parent_phone`, `parent_email`, `address`, `date_of_birth`, `created_at`, `updated_at`, `is_active`, `sms_enabled`, `previous_school`) VALUES
(9, '117327080031', '117327080031', 'Juans', 'Dela Cruz', 'storage/barcodes/student_117327080031.svg', 'storage/photos/student_9_1766397969.png', 'Juanito', '+639871627431', 'carly@gmail.com', 'purok mangingisda, barangay zone 1-a, pulupandan', '2003-03-05', '2025-12-19 07:12:42', '2025-12-22 11:46:15', 1, 1, NULL),
(10, 'STU-2025-0002', '123123123123', 'Sofia', 'First', 'storage/barcodes/student_123123123123.svg', NULL, 'Jullie', '+639192381920', 'pearly@gmail.com', 'purok magsasaka, barangay zone 2, pulupandan', '2002-09-02', '2025-12-19 07:15:40', '2025-12-22 11:46:15', 1, 1, NULL),
(11, 'STU-2025-0003', '117244007448', 'Kenny', 'Jayona', 'storage/barcodes/student_117244007448.svg', NULL, 'sample', '+639876432657', 'sample@gmail.com', 'SAMPLE ADDRESS', '2025-12-22', '2025-12-22 09:42:00', '2025-12-22 11:46:15', 1, 1, NULL),
(12, 'STU-2025-0004', '123456789123', 'sample', 'sample', 'storage/barcodes/student_123456789123.svg', 'storage/photos/student_12_1766404143.png', 'sample parent', '+639876543219', 'sample@gmail.com', 'sample CITY', '2025-12-22', '2025-12-22 11:49:03', '2025-12-22 11:49:03', 1, 0, NULL),
(13, 'STU-2025-0005', '123456789098', 'FIRST NAME', 'LAST NAME', 'storage/barcodes/student_123456789098.svg', 'storage/photos/student_13_1766404730.png', 'PARENT', '+639837474747', 'parent@gmail.com', 'sagay city', NULL, '2025-12-22 11:58:50', '2025-12-22 11:58:50', 1, 0, NULL),
(14, 'STU-2025-0006', '123456765432', 'Palaboy', 'boy', 'storage/barcodes/student_123456765432.svg', 'storage/photos/student_14_1766405067.png', 'sample1', '+639876463228', 'pal@gmail.com', 'cadiz city', '2025-12-22', '2025-12-22 12:04:27', '2025-12-22 12:04:27', 1, 0, NULL),
(15, 'STU-2025-0007', '117244007441', 'kenny', 'jayona', 'storage/barcodes/student_117244007441.svg', 'storage/photos/student_15_1766462512.png', 'NSOY', '+639876543234', 'parent1@gmail.com', 'MANAPLA CITY', '2025-12-23', '2025-12-23 04:01:52', '2025-12-23 04:01:52', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_classes`
--

CREATE TABLE `student_classes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `enrolled_by` int(11) DEFAULT NULL COMMENT 'Admin/Teacher who enrolled the student',
  `is_active` tinyint(1) DEFAULT 1,
  `enrollment_type` enum('regular','transferee','returnee','repeater') DEFAULT 'regular' COMMENT 'Type of enrollment: regular promotion, transferee from another school, returnee, or repeater',
  `enrollment_status` enum('active','withdrawn','dropped','transferred_out','completed') DEFAULT 'active' COMMENT 'Current status of this enrollment',
  `status_changed_at` datetime DEFAULT NULL COMMENT 'When the enrollment status was last changed',
  `status_changed_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User who changed the status',
  `status_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for status change (withdrawal reason, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_classes`
--

INSERT INTO `student_classes` (`id`, `student_id`, `class_id`, `enrolled_at`, `enrolled_by`, `is_active`, `enrollment_type`, `enrollment_status`, `status_changed_at`, `status_changed_by`, `status_reason`) VALUES
(8, 9, 3, '2025-12-19 07:12:42', 1, 1, 'regular', 'active', NULL, NULL, NULL),
(9, 9, 4, '2025-12-21 08:47:16', 1, 1, 'regular', 'active', NULL, NULL, NULL),
(10, 10, 4, '2025-12-19 07:15:40', 1, 0, 'regular', 'active', NULL, NULL, NULL),
(11, 10, 3, '2025-12-19 07:32:17', 1, 1, 'regular', 'active', NULL, NULL, NULL),
(14, 14, 5, '2025-12-22 12:04:27', 4, 1, 'regular', 'active', NULL, NULL, NULL),
(15, 15, 5, '2025-12-23 04:01:52', 4, 1, 'repeater', 'active', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_level` enum('info','warning','error','critical') NOT NULL,
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `log_level`, `message`, `context`, `user_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-16\",\"end_date\":\"2025-12-16\",\"school_year_id\":1,\"teacher_id\":4},\"record_count\":0}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:05:37'),
(2, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-19\",\"end_date\":\"2025-12-19\",\"school_year_id\":1},\"record_count\":0}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 07:01:50'),
(4, 'error', 'Placement save failed', '{\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'9-4\' for key \'unique_student_class\'\",\"enrolled_by\":1}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 08:45:40'),
(6, 'error', 'Placement save failed', '{\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'9-4\' for key \'unique_student_class\'\",\"enrolled_by\":1}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 08:45:50'),
(7, 'info', 'Placements saved successfully', '{\"created_count\":1,\"skipped_count\":0,\"enrolled_by\":1}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 08:47:16'),
(8, 'info', 'Placement preview exported', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_094732.csv\",\"record_count\":2,\"source_school_year\":\"2024-2025\",\"target_school_year\":\"2025-2026\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:47:32'),
(9, 'info', 'Placement preview downloaded', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_094732.csv\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:47:32'),
(10, 'info', 'Placement preview exported', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_095103.csv\",\"record_count\":2,\"source_school_year\":\"2024-2025\",\"target_school_year\":\"2025-2026\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:51:03'),
(11, 'info', 'Placement preview downloaded', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_095103.csv\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:51:03'),
(12, 'info', 'Placement preview exported', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_095441.csv\",\"record_count\":2,\"source_school_year\":\"2024-2025\",\"target_school_year\":\"2025-2026\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:54:41'),
(13, 'info', 'Placement preview downloaded', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_095441.csv\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 08:54:41'),
(14, 'info', 'Placement preview exported', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_100042.csv\",\"record_count\":2,\"source_school_year\":\"2024-2025\",\"target_school_year\":\"2025-2026\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 09:00:42'),
(15, 'info', 'Placement preview downloaded', '{\"filename\":\"placement_preview_SY2024_2025_to_SY2025_2026_2025-12-21_100042.csv\"}', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2025-12-21 09:00:42'),
(16, 'info', 'School year enrollment locked', '{\"school_year_id\":2,\"school_year_name\":\"2025-2026\",\"locked_by\":1}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 09:01:20'),
(17, 'info', 'School year enrollment unlocked', '{\"school_year_id\":2,\"school_year_name\":\"2025-2026\",\"unlocked_by\":1}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 09:13:59'),
(18, 'info', 'CSV export created', '{\"filename\":\"attendance_history_2025_2026_SY2025_2026_2025-12-22_140421.csv\",\"record_count\":1,\"school_year\":null}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-22 13:04:21'),
(19, 'info', 'CSV file downloaded', '{\"filename\":\"attendance_history_2025_2026_2025-12-22.csv\"}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-22 13:04:21'),
(20, 'error', 'Database query failed: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"sql\":\"SELECT DISTINCT a.*, \\r\\n                       s.student_id AS student_code, s.first_name, s.last_name,\\r\\n                       COALESCE(c.grade_level, \'\') AS class, COALESCE(c.section, \'\') AS section,\\r\\n                       u.full_name as recorded_by_name,\\r\\n                       sy.name AS school_year_name\\r\\n                FROM attendance a\\r\\n                INNER JOIN students s ON a.student_id = s.id\\r\\n                       LEFT JOIN users u ON a.recorded_by = u.id\\r\\n                LEFT JOIN school_years sy ON a.school_year_id = sy.id\\r\\n                WHERE a.attendance_date = ? AND a.school_year_id = ?\\r\\n                ORDER BY a.check_in_time DESC\",\"params\":[\"2025-12-23\",2]}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 03:54:18'),
(21, 'error', 'Failed to get attendance by date: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"date\":\"2025-12-23\",\"school_year_id\":2}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 03:54:18'),
(22, 'info', 'CSV export created', '{\"filename\":\"attendance_history_2025_2026_SY2025_2026_2025-12-23_050446.csv\",\"record_count\":2,\"school_year\":null}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:04:46'),
(23, 'info', 'CSV file downloaded', '{\"filename\":\"attendance_history_2025_2026_2025-12-23.csv\"}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:04:46'),
(24, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-23\",\"end_date\":\"2025-12-23\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":3}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:08:08'),
(25, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-23\",\"end_date\":\"2025-12-23\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":3}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:08:18'),
(26, 'info', 'CSV export created', '{\"filename\":\"attendance_report_2025_2026_SY2025_2026_2025-12-23_050818.csv\",\"record_count\":3,\"school_year\":null}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:08:18'),
(27, 'info', 'CSV file downloaded', '{\"filename\":\"attendance_report_2025_2026_2025-12-23.csv\"}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:08:18'),
(28, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-23\",\"end_date\":\"2025-12-23\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":3}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:32:40'),
(29, 'error', 'Database query failed: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"sql\":\"SELECT DISTINCT a.*, \\r\\n                       s.student_id AS student_code, s.first_name, s.last_name,\\r\\n                       COALESCE(c.grade_level, \'\') AS class, COALESCE(c.section, \'\') AS section,\\r\\n                       u.full_name as recorded_by_name,\\r\\n                       sy.name AS school_year_name\\r\\n                FROM attendance a\\r\\n                INNER JOIN students s ON a.student_id = s.id\\r\\n                       LEFT JOIN users u ON a.recorded_by = u.id\\r\\n                LEFT JOIN school_years sy ON a.school_year_id = sy.id\\r\\n                WHERE a.attendance_date = ? AND a.school_year_id = ?\\r\\n                ORDER BY a.check_in_time DESC\",\"params\":[\"2025-12-23\",2]}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:42'),
(30, 'error', 'Failed to get attendance by date: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"date\":\"2025-12-23\",\"school_year_id\":2}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:42'),
(31, 'error', 'Database query failed: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"sql\":\"SELECT DISTINCT a.*, \\r\\n                       s.student_id AS student_code, s.first_name, s.last_name,\\r\\n                       COALESCE(c.grade_level, \'\') AS class, COALESCE(c.section, \'\') AS section,\\r\\n                       u.full_name as recorded_by_name,\\r\\n                       sy.name AS school_year_name\\r\\n                FROM attendance a\\r\\n                INNER JOIN students s ON a.student_id = s.id\\r\\n                       LEFT JOIN users u ON a.recorded_by = u.id\\r\\n                LEFT JOIN school_years sy ON a.school_year_id = sy.id\\r\\n                WHERE a.attendance_date = ? AND a.school_year_id = ?\\r\\n                ORDER BY a.check_in_time DESC\",\"params\":[\"2025-12-23\",2]}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:52'),
(32, 'error', 'Failed to get attendance by date: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"date\":\"2025-12-23\",\"school_year_id\":2}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','operator','viewer','teacher') NOT NULL DEFAULT 'viewer',
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_premium` tinyint(1) DEFAULT 0 COMMENT 'Whether user has premium access (paid subscription for reports/exports)',
  `premium_expires_at` date DEFAULT NULL COMMENT 'Premium subscription expiration date (NULL = no expiration)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `email`, `created_at`, `updated_at`, `last_login`, `is_active`, `is_premium`, `premium_expires_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@attendance.local', '2025-12-06 08:04:25', '2025-12-23 05:08:05', '2025-12-23 05:08:05', 1, 1, NULL),
(4, 'teacher', '$2y$10$sD03JRxYLjcpdG.s7zC/cO/Bcmr.8BaJ8WsjigjDg38JDcITLuZq6', 'teacher', 'Jona Mondia', 'teacher@gmail.com', '2025-12-16 13:36:21', '2025-12-23 04:03:11', '2025-12-23 04:03:11', 1, 1, NULL),
(5, 'teacher1', '$2y$10$mWDAZgRK3LhTzr8kXf0OJOQFlhIve4gWxg/ryZLEnoMMxrgXb8002', 'teacher', 'Maloi Cruz', 'teacher1@gmail.com', '2025-12-19 05:59:35', '2025-12-22 12:26:04', '2025-12-22 12:26:04', 1, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`attendance_date`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_date` (`attendance_date`),
  ADD KEY `idx_student_date` (`student_id`,`attendance_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_school_year` (`school_year_id`),
  ADD KEY `idx_checkout` (`check_out_time`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class` (`grade_level`,`section`,`school_year_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_school_year` (`school_year_id`),
  ADD KEY `idx_grade_section` (`grade_level`,`section`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `retry_queue`
--
ALTER TABLE `retry_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_retry` (`status`,`next_retry_at`),
  ADD KEY `idx_operation` (`operation_type`);

--
-- Indexes for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_key` (`setting_key`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_locked` (`is_locked`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_activity` (`last_activity`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_name` (`last_name`,`first_name`),
  ADD KEY `idx_lrn` (`lrn`),
  ADD KEY `idx_sms_enabled` (`sms_enabled`),
  ADD KEY `idx_photo_path` (`photo_path`);

--
-- Indexes for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_class` (`student_id`,`class_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_enrolled_by` (`enrolled_by`),
  ADD KEY `idx_enrollment_type` (`enrollment_type`),
  ADD KEY `idx_enrollment_status` (`enrollment_status`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_level_date` (`log_level`,`created_at`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_premium` (`is_premium`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `retry_queue`
--
ALTER TABLE `retry_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `student_classes`
--
ALTER TABLE `student_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD CONSTRAINT `fk_student_classes_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_classes_enrolled_by` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_student_classes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
