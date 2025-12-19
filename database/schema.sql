-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 19, 2025 at 07:06 AM
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
(3, 8, NULL, '2025-12-08', '2025-12-08 13:24:58', NULL, 'present', NULL, NULL),
(16, 8, NULL, '2025-12-11', '2025-12-11 04:10:36', NULL, 'present', NULL, NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `grade_level`, `section`, `teacher_id`, `school_year_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Grade 7', 'Example', 4, 1, 1, '2025-12-16 13:38:43', '2025-12-16 13:38:43');

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
(1, 8, 'sms', '+639319120634', 'Hello Carly Cata-an, your child Carl Navid Cata-an arrived at school on Dec 11, 2025 05:01 AM. - Your School Name', 'sent', '2025-12-10 21:01:10', NULL, 0, '2025-12-11 04:01:10'),
(2, 8, 'sms', '+639319120634', 'Hello Carly Cata-an, your child Carl Navid Cata-an arrived at school on Dec 11, 2025 12:10 PM. - Your School Name', 'sent', '2025-12-10 21:10:40', NULL, 0, '2025-12-11 04:10:40');

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
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `name` varchar(9) NOT NULL COMMENT 'Format: YYYY-YYYY',
  `is_active` tinyint(1) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `name`, `is_active`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, '2024-2025', 1, '2025-01-01', '2025-12-31', '2025-12-16 12:56:40', '2025-12-16 12:56:43'),
(2, '2025-2026', 0, '2025-12-02', '2025-12-25', '2025-12-16 12:57:08', '2025-12-16 12:57:08'),
(3, '2026-2027', 0, '2025-12-08', '2025-12-18', '2025-12-16 14:19:43', '2025-12-16 14:19:43');

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
('ed1j9i2j85p2qo703dp3e5o634', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 13:26:13', '2025-12-08 13:26:13'),
('mdf618aj1ikfgda3gqkk5tivb1', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:00:35', '2025-12-19 06:00:35'),
('u07erlfggvikmdrmk4ta1v7bq0', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:00:58', '2025-12-19 06:00:58');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

-- Note: class and section columns removed - now managed via student_classes -> classes relationship
CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `barcode_path` varchar(255) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `lrn`, `first_name`, `last_name`, `barcode_path`, `parent_name`, `parent_phone`, `parent_email`, `address`, `date_of_birth`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'STU001', NULL, 'John', 'Doe', NULL, 'Jane Doe', '+1234567890', 'jane.doe@example.com', NULL, NULL, '2025-12-06 08:04:25', '2025-12-06 08:04:25', 1),
(2, 'STU002', NULL, 'Alice', 'Smith', NULL, 'Bob Smith', '+1234567891', 'bob.smith@example.com', NULL, NULL, '2025-12-06 08:04:25', '2025-12-06 08:04:25', 1),
(3, 'STU003', NULL, 'Michael', 'Johnson', NULL, 'Sarah Johnson', '+1234567892', 'sarah.johnson@example.com', NULL, NULL, '2025-12-06 08:04:25', '2025-12-06 08:04:25', 1),
(4, 'STU004', NULL, 'Emily', 'Brown', NULL, 'David Brown', '+1234567893', 'david.brown@example.com', NULL, NULL, '2025-12-06 08:04:25', '2025-12-06 08:04:25', 1),
(5, 'STU005', NULL, 'Daniel', 'Wilson', NULL, 'Lisa Wilson', '+1234567894', 'lisa.wilson@example.com', NULL, NULL, '2025-12-06 08:04:25', '2025-12-06 08:04:25', 1),
(6, '123123123123', '123123123123', 'adsda', 'asdasd', 'storage/barcodes/student_123123123123.svg', 'asdasd', '+639876543234', 'asdas@gmail.com', 'hgfagshdjkasjdh', '2025-12-17', '2025-12-08 11:20:09', '2025-12-08 11:20:09', 1),
(7, '987481903249', '987481903249', 'u1623781na', '7189304oj', 'storage/barcodes/student_987481903249.svg', 'asdasd', '+639871627431', 'jkas@gmail.com', 'alksjdkaosd', '1222-12-12', '2025-12-08 11:20:47', '2025-12-08 11:20:47', 1),
(8, '117324080029', '117324080029', 'Carl Navid', 'Cata-an', 'storage/barcodes/student_117324080029.svg', 'Carly Cata-an', '+639319120634', 'jkas@gmail.com', 'Purok Mangingisda, Pulupandan, Negros Occidental', '2003-03-05', '2025-12-08 11:23:27', '2025-12-11 03:36:15', 1);

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
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_classes`
--

INSERT INTO `student_classes` (`id`, `student_id`, `class_id`, `enrolled_at`, `enrolled_by`, `is_active`) VALUES
(1, 8, 1, '2025-12-16 14:05:06', 4, 1),
(2, 7, 1, '2025-12-16 13:55:44', 4, 0),
(3, 6, 1, '2025-12-16 13:55:44', 4, 1),
(4, 4, 1, '2025-12-16 14:04:51', 4, 1);

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
(1, 'info', 'Report generated', '{\"filters\":{\"start_date\":\"2025-11-16\",\"end_date\":\"2025-12-16\",\"school_year_id\":1,\"teacher_id\":4},\"record_count\":0}', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:05:37');

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
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `email`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@attendance.local', '2025-12-06 08:04:25', '2025-12-19 06:00:58', '2025-12-19 06:00:58', 1),
(2, 'operator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operator', 'Attendance Operator', 'operator@attendance.local', '2025-12-06 08:04:25', '2025-12-06 08:04:25', NULL, 1),
(3, 'viewer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer', 'Report Viewer', 'viewer@attendance.local', '2025-12-06 08:04:25', '2025-12-06 08:04:25', NULL, 1),
(4, 'teacher', '$2y$10$sD03JRxYLjcpdG.s7zC/cO/Bcmr.8BaJ8WsjigjDg38JDcITLuZq6', 'teacher', 'teacher', 'teacher@gmail.com', '2025-12-16 13:36:21', '2025-12-16 14:12:17', '2025-12-16 14:12:17', 1),
(5, 'teacher1', '$2y$10$mWDAZgRK3LhTzr8kXf0OJOQFlhIve4gWxg/ryZLEnoMMxrgXb8002', 'teacher', 'teacher1', 'teacher1@gmail.com', '2025-12-19 05:59:35', '2025-12-19 05:59:47', '2025-12-19 05:59:47', 1);

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
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`),
  ADD KEY `idx_active` (`is_active`);

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
  ADD KEY `idx_lrn` (`lrn`);

--
-- Indexes for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_class` (`student_id`,`class_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_enrolled_by` (`enrolled_by`);

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
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_classes`
--
ALTER TABLE `student_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
