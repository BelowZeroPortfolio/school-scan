-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 30, 2025 at 08:34 AM
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
(19, 15, 2, '2025-12-23', '2025-12-23 04:03:36', '2025-12-23 04:03:43', 'present', 4, NULL),
(30, 9, 2, '2025-12-30', '2025-12-30 07:23:08', NULL, 'late', 5, NULL);

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

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `student_id`, `notification_type`, `recipient`, `message`, `status`, `sent_at`, `error_message`, `retry_count`, `created_at`) VALUES
(3, 9, 'sms', '+639945880632', 'Hello Juanito, your child Juans Dela Cruz arrived at school on Dec 30, 2025 03:23 PM. - SAGAY NATIONAL HIGH SCHOOL', 'sent', '2025-12-30 07:23:10', NULL, 0, '2025-12-30 07:23:10');

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
('2d5eqpsnp2jfahv37bp1ira48s', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:23:41', '2025-12-30 07:23:41'),
('84tr4mdd0amds2bjrsh2a31cjg', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 07:31:33', '2025-12-19 07:31:33'),
('dnqpfrck34kb9aa15l32ks4ka2', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 09:58:20', '2025-12-21 09:58:20'),
('dqb5k4hc408qev0v99s2v91mib', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 04:03:11', '2025-12-23 04:03:11'),
('duppurm3ogv076rh82is9q3doh', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:05', '2025-12-23 05:08:05'),
('ed1j9i2j85p2qo703dp3e5o634', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 13:26:13', '2025-12-08 13:26:13'),
('mdf618aj1ikfgda3gqkk5tivb1', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:00:35', '2025-12-19 06:00:35'),
('q6k9d8sm8093et1tdujvq6an06', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 11:25:58', '2025-12-29 11:25:58');

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
(9, '117327080031', '117327080031', 'Juans', 'Dela Cruz', 'storage/barcodes/student_117327080031.svg', 'storage/photos/student_9_1766686047.jpg', 'Juanito', '+639945880632', 'jazz@gmail.com', 'purok mangingisda, barangay zone 2, pulupandan', '2003-03-05', '2025-12-19 07:12:42', '2025-12-25 18:07:50', 1, 1, NULL),
(10, 'STU-2025-0002', '123123123123', 'Sofia', 'First', 'storage/barcodes/student_123123123123.svg', NULL, 'Jullie', '+639192381920', 'pearly@gmail.com', 'purok magsasaka, barangay zone 2, pulupandan', '2002-09-02', '2025-12-19 07:15:40', '2025-12-22 11:46:15', 1, 1, NULL),
(11, 'STU-2025-0003', '117244007448', 'Kenny', 'Jayona', 'storage/barcodes/student_117244007448.svg', NULL, 'sample', '+639876432657', 'sample@gmail.com', 'SAMPLE ADDRESS', '2025-12-22', '2025-12-22 09:42:00', '2025-12-22 11:46:15', 1, 1, NULL),
(12, 'STU-2025-0004', '123456789123', 'sample', 'sample', 'storage/barcodes/student_123456789123.svg', 'storage/photos/student_12_1766404143.png', 'sample parent', '+639876543219', 'sample@gmail.com', 'sample CITY', '2025-12-22', '2025-12-22 11:49:03', '2025-12-22 11:49:03', 1, 0, NULL),
(13, 'STU-2025-0005', '123456789098', 'FIRST NAME', 'LAST NAME', 'storage/barcodes/student_123456789098.svg', 'storage/photos/student_13_1766404730.png', 'PARENT', '+639837474747', 'parent@gmail.com', 'sagay city', NULL, '2025-12-22 11:58:50', '2025-12-22 11:58:50', 1, 0, NULL),
(14, '123456765432', '123456765432', 'Palaboy', 'boy', 'storage/barcodes/student_123456765432.svg', 'storage/photos/student_14_1766686104.jpg', 'sample1', '+639876463228', 'pal@gmail.com', 'cadiz city', '2025-12-22', '2025-12-22 12:04:27', '2025-12-25 18:08:24', 1, 0, NULL),
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
(32, 'error', 'Failed to get attendance by date: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'c.grade_level\' in \'field list\'', '{\"date\":\"2025-12-23\",\"school_year_id\":2}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 05:08:52'),
(33, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-25\",\"end_date\":\"2025-12-25\",\"school_year_id\":2},\"record_count\":3}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-25 18:11:38'),
(34, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-25\",\"end_date\":\"2025-12-25\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":3}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-25 18:11:38'),
(35, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:13:00'),
(36, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:13:00'),
(37, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:13:39'),
(38, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:13:39'),
(39, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:14:24'),
(40, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":3}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:14:24'),
(41, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:50:10'),
(42, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:50:10'),
(43, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:53:52'),
(44, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:53:52'),
(45, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:54:02'),
(46, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:54:02'),
(47, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:54:10'),
(48, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:54:10'),
(49, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:56:43'),
(50, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 06:56:43'),
(51, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:00:12'),
(52, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:00:12'),
(53, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:00:33'),
(54, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:00:33'),
(55, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:01:05'),
(56, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:01:05'),
(57, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:01:55'),
(58, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:01:55'),
(59, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:08'),
(60, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:08'),
(61, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:14'),
(62, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:14'),
(63, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:22'),
(64, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:02:22'),
(65, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:06:04'),
(66, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":4},\"record_count\":4}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:06:04'),
(67, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2},\"record_count\":5}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:29:31'),
(68, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":5}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:29:31'),
(69, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2},\"record_count\":5}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:33:02'),
(70, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-30\",\"end_date\":\"2025-12-30\",\"school_year_id\":2,\"class_id\":null,\"teacher_id\":null},\"record_count\":5}', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 07:33:02');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL COMMENT 'Teacher login time',
  `time_out` datetime DEFAULT NULL COMMENT 'Teacher logout time',
  `first_student_scan` datetime DEFAULT NULL COMMENT 'First student scan time in teacher class',
  `attendance_status` enum('pending','confirmed','late','no_scan','absent') DEFAULT 'pending' COMMENT 'Overall attendance state',
  `late_status` enum('on_time','late') DEFAULT NULL COMMENT 'Lateness determination',
  `time_rule_id` int(11) DEFAULT NULL COMMENT 'Locked time rule used for evaluation',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_attendance`
--

INSERT INTO `teacher_attendance` (`id`, `teacher_id`, `school_year_id`, `attendance_date`, `time_in`, `time_out`, `first_student_scan`, `attendance_status`, `late_status`, `time_rule_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, 2, '2025-12-30', '2025-12-30 15:22:50', '2025-12-30 15:23:38', '2025-12-30 15:23:08', 'late', 'late', 3, NULL, '2025-12-30 07:22:50', '2025-12-30 07:23:38');

-- --------------------------------------------------------

--
-- Table structure for table `time_schedules`
--

CREATE TABLE `time_schedules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Schedule name (e.g., Regular Schedule, Summer Schedule)',
  `time_in` time NOT NULL COMMENT 'Official time in',
  `time_out` time NOT NULL COMMENT 'Official time out',
  `late_threshold_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Grace period in minutes before marked late',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Only one schedule can be active at a time',
  `effective_date` date DEFAULT NULL COMMENT 'When this schedule takes effect (NULL = immediate)',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `time_schedules`
--

INSERT INTO `time_schedules` (`id`, `name`, `time_in`, `time_out`, `late_threshold_minutes`, `is_active`, `effective_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Regular Schedule', '07:30:00', '17:00:00', 15, 0, NULL, 1, '2025-12-30 06:13:21', '2025-12-30 06:19:02'),
(2, 'late', '08:00:00', '17:00:00', 15, 0, NULL, 6, '2025-12-30 06:19:02', '2025-12-30 06:41:09'),
(3, '2', '14:00:00', '17:40:00', 0, 1, NULL, 1, '2025-12-30 06:41:03', '2025-12-30 06:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `time_schedule_logs`
--

CREATE TABLE `time_schedule_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL COMMENT 'NULL if schedule was deleted',
  `action` enum('create','update','delete','activate','deactivate') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `change_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `time_schedule_logs`
--

INSERT INTO `time_schedule_logs` (`id`, `schedule_id`, `action`, `changed_by`, `old_values`, `new_values`, `change_reason`, `created_at`) VALUES
(1, 2, 'create', 6, NULL, '{\"name\":\"late\",\"time_in\":\"08:00\",\"time_out\":\"17:00\",\"late_threshold_minutes\":15,\"is_active\":1,\"effective_date\":null}', NULL, '2025-12-30 06:19:02'),
(2, 3, 'create', 1, NULL, '{\"name\":\"2\",\"time_in\":\"14:30\",\"time_out\":\"17:40\",\"late_threshold_minutes\":15,\"is_active\":0,\"effective_date\":null}', NULL, '2025-12-30 06:41:03'),
(3, 3, 'activate', 1, '{\"is_active\":0}', '{\"is_active\":1}', NULL, '2025-12-30 06:41:09'),
(4, 3, 'update', 1, '{\"id\":3,\"name\":\"2\",\"time_in\":\"14:30:00\",\"time_out\":\"17:40:00\",\"late_threshold_minutes\":15,\"is_active\":1,\"effective_date\":null,\"created_by\":1,\"created_at\":\"2025-12-30 14:41:03\",\"updated_at\":\"2025-12-30 14:41:09\"}', '{\"name\":\"2\",\"time_in\":\"14:00\",\"time_out\":\"17:40:00\",\"late_threshold_minutes\":15,\"is_active\":1,\"effective_date\":null}', '', '2025-12-30 06:42:04'),
(5, 3, 'update', 1, '{\"id\":3,\"name\":\"2\",\"time_in\":\"14:00:00\",\"time_out\":\"17:40:00\",\"late_threshold_minutes\":15,\"is_active\":1,\"effective_date\":null,\"created_by\":1,\"created_at\":\"2025-12-30 14:41:03\",\"updated_at\":\"2025-12-30 14:42:04\"}', '{\"name\":\"2\",\"time_in\":\"14:00:00\",\"time_out\":\"17:40:00\",\"late_threshold_minutes\":0,\"is_active\":1,\"effective_date\":null}', '', '2025-12-30 06:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','principal','teacher') NOT NULL DEFAULT 'teacher',
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
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@attendance.local', '2025-12-06 00:04:25', '2025-12-30 06:24:00', '2025-12-30 06:24:00', 1, 1, NULL),
(4, 'teacher', '$2y$10$sD03JRxYLjcpdG.s7zC/cO/Bcmr.8BaJ8WsjigjDg38JDcITLuZq6', 'teacher', 'Jona Mondia', 'teacher@gmail.com', '2025-12-16 05:36:21', '2025-12-30 06:48:52', '2025-12-30 06:48:52', 1, 1, NULL),
(5, 'teacher1', '$2y$10$mWDAZgRK3LhTzr8kXf0OJOQFlhIve4gWxg/ryZLEnoMMxrgXb8002', 'teacher', 'Maloi Cruz', 'teacher1@gmail.com', '2025-12-18 21:59:35', '2025-12-30 07:22:50', '2025-12-30 07:22:50', 1, 1, NULL),
(6, 'principal', '$2y$10$H1Kuu8qomBTzYEu9LxH4P.3IytD1GPLY5SOFG2buV6NinGbnzdDnO', 'principal', 'Mr. Principal', 'principal@gmail.com', '2025-12-30 05:44:01', '2025-12-30 07:23:41', '2025-12-30 07:23:41', 1, 0, NULL);

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
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_date` (`teacher_id`,`attendance_date`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_date` (`attendance_date`),
  ADD KEY `idx_school_year` (`school_year_id`),
  ADD KEY `idx_attendance_status` (`attendance_status`),
  ADD KEY `idx_late_status` (`late_status`);

--
-- Indexes for table `time_schedules`
--
ALTER TABLE `time_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `fk_time_schedules_created_by` (`created_by`);

--
-- Indexes for table `time_schedule_logs`
--
ALTER TABLE `time_schedule_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule` (`schedule_id`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `retry_queue`
--
ALTER TABLE `retry_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `time_schedules`
--
ALTER TABLE `time_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `time_schedule_logs`
--
ALTER TABLE `time_schedule_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD CONSTRAINT `fk_teacher_attendance_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_attendance_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_schedules`
--
ALTER TABLE `time_schedules`
  ADD CONSTRAINT `fk_time_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `time_schedule_logs`
--
ALTER TABLE `time_schedule_logs`
  ADD CONSTRAINT `fk_schedule_logs_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
