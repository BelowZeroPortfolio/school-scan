-- Migration: Add 'principal' role to users table
-- Run this SQL to add the principal role to your database
-- Date: 2025-12-23

-- Modify the role ENUM to include 'principal'
ALTER TABLE users MODIFY COLUMN role ENUM('admin','principal','operator','viewer','teacher') NOT NULL DEFAULT 'viewer';

-- Optional: Create a sample principal user (password: password)
-- Uncomment the following line if you want to create a test principal user
-- INSERT INTO users (username, password_hash, role, full_name, email, is_active, is_premium) 
-- VALUES ('principal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'principal', 'School Principal', 'principal@school.local', 1, 1);
