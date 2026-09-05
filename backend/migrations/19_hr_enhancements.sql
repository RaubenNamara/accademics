-- Migration 19: HR enhancements (leave balances, periods, seeds, teaching_staff detail)

SET @db := DATABASE();

-- Leave balances
CREATE TABLE IF NOT EXISTS leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual','sick','maternity','paternity','compassionate','unpaid','other') NOT NULL DEFAULT 'annual',
    year INT NOT NULL,
    entitled_days DECIMAL(5,2) NOT NULL DEFAULT 21,
    used_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    remaining_days DECIMAL(5,2) NOT NULL DEFAULT 21,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leave_balance (employee_id, leave_type, year),
    CONSTRAINT fk_leave_balance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Timetable periods (optional period templates)
CREATE TABLE IF NOT EXISTS timetable_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_number INT NOT NULL,
    label VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE KEY uq_period_number (period_number)
);

INSERT IGNORE INTO timetable_periods (period_number, label, start_time, end_time) VALUES
(1, 'Period 1', '08:00:00', '08:40:00'),
(2, 'Period 2', '08:40:00', '09:20:00'),
(3, 'Period 3', '09:20:00', '10:00:00'),
(4, 'Break', '10:00:00', '10:20:00'),
(5, 'Period 4', '10:20:00', '11:00:00'),
(6, 'Period 5', '11:00:00', '11:40:00'),
(7, 'Period 6', '11:40:00', '12:20:00'),
(8, 'Period 7', '13:30:00', '14:10:00'),
(9, 'Period 8', '14:10:00', '14:50:00');

-- Teaching staff extended detail (linked to employee)
CREATE TABLE IF NOT EXISTS teaching_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    teacher_id INT NULL,
    subjects_taught TEXT NULL,
    classes_assigned TEXT NULL,
    streams_assigned TEXT NULL,
    weekly_load INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teaching_staff_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_teaching_staff_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Lesson attendance table if missing
CREATE TABLE IF NOT EXISTS teacher_lesson_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    year INT NOT NULL,
    term INT NOT NULL,
    week_number INT NOT NULL,
    lesson_date DATE NOT NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    subject VARCHAR(150) NULL,
    class_name VARCHAR(100) NULL,
    stream VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tla_teacher (teacher_id, year, term),
    CONSTRAINT fk_tla_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Seed departments
INSERT IGNORE INTO departments (name, description) VALUES
('Administration', 'School administration'),
('Accounts', 'Finance and accounts'),
('Academics', 'Teaching and academics'),
('Security', 'Security services'),
('Health', 'School clinic / nursing'),
('Transport', 'Drivers and transport'),
('IT', 'Information technology'),
('Maintenance', 'Cleaning and maintenance'),
('Reception', 'Front desk and reception'),
('Stores', 'Inventory and stores');

-- Seed roles for non-teaching
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Accountant', id, 'Finance officer' FROM departments WHERE name = 'Accounts' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Secretary', id, 'Administrative secretary' FROM departments WHERE name = 'Administration' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Security Guard', id, 'Security personnel' FROM departments WHERE name = 'Security' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Nurse', id, 'School nurse' FROM departments WHERE name = 'Health' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Driver', id, 'School driver' FROM departments WHERE name = 'Transport' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Receptionist', id, 'Front desk' FROM departments WHERE name = 'Reception' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Cleaner', id, 'Cleaning staff' FROM departments WHERE name = 'Maintenance' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'IT Officer', id, 'IT support' FROM departments WHERE name = 'IT' LIMIT 1;
INSERT IGNORE INTO roles (name, department_id, description)
SELECT 'Store Keeper', id, 'Stores management' FROM departments WHERE name = 'Stores' LIMIT 1;
