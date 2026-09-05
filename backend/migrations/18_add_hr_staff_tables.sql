-- Migration 18: Add core HR staff tables (employees, departments, roles, timetable, class_teacher_assignments,
--              leave_requests, payroll, employee_documents) and link teachers -> employees
--
-- This migration is written to be IDEMPOTENT where possible, so it can be run safely multiple times.

SET @db := DATABASE();

-- 1) DEPARTMENTS TABLE ------------------------------------------------------

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2) ROLES TABLE ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    department_id INT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_roles_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE SET NULL,
    UNIQUE KEY uq_role_name_dept (name, department_id)
);

-- 3) EMPLOYEES TABLE --------------------------------------------------------

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Staff type
    staff_type ENUM('teaching', 'non_teaching') NOT NULL DEFAULT 'teaching',

    -- HR code (TS-xxx for teaching, NTS-xxx for non-teaching)
    hr_code VARCHAR(20) NOT NULL UNIQUE,

    -- Personal information
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    nationality VARCHAR(100) DEFAULT NULL,
    national_id VARCHAR(100) DEFAULT NULL,
    passport_photo VARCHAR(255) DEFAULT NULL,
    phone_number VARCHAR(50) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    emergency_contact VARCHAR(255) DEFAULT NULL,
    marital_status VARCHAR(50) DEFAULT NULL,

    -- Employment information
    department_id INT NULL,
    role_id INT NULL,
    employment_type ENUM('permanent','contract','part_time','intern','volunteer') DEFAULT 'permanent',
    date_joined DATE DEFAULT NULL,
    salary DECIMAL(12,2) DEFAULT 0,
    status ENUM('active','inactive','on_leave','terminated') DEFAULT 'active',
    supervisor_id INT NULL,

    -- Financial information
    bank_name VARCHAR(150) DEFAULT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
    mobile_money VARCHAR(100) DEFAULT NULL,
    tin_number VARCHAR(100) DEFAULT NULL,
    nssf_number VARCHAR(100) DEFAULT NULL,

    -- Meta
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_employees_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_role FOREIGN KEY (role_id)
        REFERENCES roles(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_supervisor FOREIGN KEY (supervisor_id)
        REFERENCES employees(id) ON DELETE SET NULL
);

-- Helpful index for quick lookup by staff_type
CREATE INDEX IF NOT EXISTS idx_employees_staff_type ON employees (staff_type);

-- 4) LINK TEACHERS -> EMPLOYEES --------------------------------------------

-- Add employee_id column to teachers table if it does not exist
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'employee_id'
);

SET @stmt := IF(@col_exists = 0,
  'ALTER TABLE teachers ADD COLUMN employee_id INT NULL AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure teacher_code column exists (used for TS-XXX codes)
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'teacher_code'
);

SET @stmt := IF(@col_exists = 0,
  'ALTER TABLE teachers ADD COLUMN teacher_code VARCHAR(20) UNIQUE AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add foreign key from teachers.employee_id to employees.id (if not already present)
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'teachers' AND CONSTRAINT_NAME = 'fk_teachers_employee'
);

SET @stmt := IF(@fk_exists = 0,
  'ALTER TABLE teachers
     ADD CONSTRAINT fk_teachers_employee
     FOREIGN KEY (employee_id) REFERENCES employees(id)
     ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) NON-TEACHING STAFF DETAIL TABLE ---------------------------------------

CREATE TABLE IF NOT EXISTS non_teaching_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    duty_assignment VARCHAR(150) DEFAULT NULL,
    shift_schedule VARCHAR(150) DEFAULT NULL,
    specialization VARCHAR(150) DEFAULT NULL,
    staff_status ENUM('active','inactive','on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_non_teaching_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE CASCADE
);

-- 6) TIMETABLE TABLE --------------------------------------------------------

CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year INT NOT NULL,
    term INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    period_number INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    room VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_timetable_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetable_subject FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetable_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT uq_timetable_slot UNIQUE (academic_year, term, day_of_week, period_number, class_id, stream)
);

-- Optional: add stream column if not present
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'timetable' AND COLUMN_NAME = 'stream'
);

SET @stmt := IF(@col_exists = 0,
  'ALTER TABLE timetable ADD COLUMN stream VARCHAR(50) DEFAULT NULL AFTER class_id',
  'SELECT 1'
);
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) CLASS TEACHER ASSIGNMENTS ---------------------------------------------

CREATE TABLE IF NOT EXISTS class_teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    stream VARCHAR(50) DEFAULT NULL,
    academic_year INT NOT NULL,
    term INT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_teacher_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teacher_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_class_teacher_active (teacher_id, class_id, IFNULL(stream, ''), academic_year, IFNULL(term, 0))
);

-- 8) LEAVE REQUESTS ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual','sick','maternity','paternity','compassionate','unpaid','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,2) DEFAULT 0,
    reason TEXT,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_leave_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_leave_employee_status ON leave_requests (employee_id, status);

-- 9) PAYROLL ----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period_year INT NOT NULL,
    period_month INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
    details JSON NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT NULL,
    CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payroll_employee_period (employee_id, period_year, period_month)
);

-- 10) EMPLOYEE DOCUMENTS ----------------------------------------------------

CREATE TABLE IF NOT EXISTS employee_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    doc_type ENUM('cv','certificate','contract','appointment_letter','other') NOT NULL DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_employee_documents_employee (employee_id)
);

-- 11) BACKFILL EMPLOYEES FROM EXISTING TEACHERS ----------------------------

-- For existing systems, create employees for teachers that do not yet have one
INSERT INTO employees (
    staff_type,
    hr_code,
    first_name,
    last_name,
    email,
    phone_number,
    status,
    created_at,
    updated_at
)
SELECT
    'teaching' AS staff_type,
    COALESCE(t.teacher_code, CONCAT('TS-', LPAD(t.id, 3, '0'))) AS hr_code,
    SUBSTRING_INDEX(t.full_name, ' ', 1) AS first_name,
    TRIM(SUBSTRING(t.full_name FROM LENGTH(SUBSTRING_INDEX(t.full_name, ' ', 1)) + 1)) AS last_name,
    t.email,
    t.contact,
    CASE WHEN t.is_active = 1 THEN 'active' ELSE 'inactive' END AS status,
    t.created_at,
    t.updated_at
FROM teachers t
LEFT JOIN employees e
  ON e.hr_code = COALESCE(t.teacher_code, CONCAT('TS-', LPAD(t.id, 3, '0')))
WHERE e.id IS NULL;

-- Link teachers to their corresponding employees
UPDATE teachers t
JOIN employees e
  ON e.hr_code = COALESCE(t.teacher_code, CONCAT('TS-', LPAD(t.id, 3, '0')))
SET t.employee_id = e.id
WHERE t.employee_id IS NULL;
