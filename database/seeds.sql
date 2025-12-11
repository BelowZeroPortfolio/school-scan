-- Seed Data for Barcode Attendance System
-- Initial admin user and sample data

-- Insert default admin user
-- Username: admin
-- Password: password
INSERT INTO users (username, password_hash, role, full_name, email, is_active) 
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password"
    'admin',
    'System Administrator',
    'admin@attendance.local',
    TRUE
) ON DUPLICATE KEY UPDATE username = username;

-- Insert sample operator user (optional)
-- Username: operator
-- Password: password
INSERT INTO users (username, password_hash, role, full_name, email, is_active) 
VALUES (
    'operator',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password"
    'operator',
    'Attendance Operator',
    'operator@attendance.local',
    TRUE
) ON DUPLICATE KEY UPDATE username = username;

-- Insert sample viewer user (optional)
-- Username: viewer
-- Password: password
INSERT INTO users (username, password_hash, role, full_name, email, is_active) 
VALUES (
    'viewer',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password"
    'viewer',
    'Report Viewer',
    'viewer@attendance.local',
    TRUE
) ON DUPLICATE KEY UPDATE username = username;

-- Sample students (optional - for testing)
INSERT INTO students (student_id, first_name, last_name, class, section, parent_name, parent_phone, parent_email, is_active)
VALUES 
    ('STU001', 'John', 'Doe', 'Grade 10', 'A', 'Jane Doe', '+1234567890', 'jane.doe@example.com', TRUE),
    ('STU002', 'Alice', 'Smith', 'Grade 10', 'A', 'Bob Smith', '+1234567891', 'bob.smith@example.com', TRUE),
    ('STU003', 'Michael', 'Johnson', 'Grade 10', 'B', 'Sarah Johnson', '+1234567892', 'sarah.johnson@example.com', TRUE),
    ('STU004', 'Emily', 'Brown', 'Grade 11', 'A', 'David Brown', '+1234567893', 'david.brown@example.com', TRUE),
    ('STU005', 'Daniel', 'Wilson', 'Grade 11', 'B', 'Lisa Wilson', '+1234567894', 'lisa.wilson@example.com', TRUE)
ON DUPLICATE KEY UPDATE student_id = student_id;

