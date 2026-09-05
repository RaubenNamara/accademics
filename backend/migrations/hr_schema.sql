-- School HR Management System Core Schema
-- This script defines the recommended relational structure for:
-- employees, departments, roles, teaching_staff, non_teaching_staff,
-- timetable, class_teacher_assignments, leave_requests, payroll,
-- employee_documents
--
-- NOTE:
-- - Uses InnoDB + utf8mb4
-- - Designed to align with existing `employees` and `non_teaching_staff`
--   usage in the current codebase.
-- - Uses CREATE TABLE IF NOT EXISTS so it is safe to run on a database
--   that already contains some of these tables (no destructive changes).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------
-- Departments
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Roles (Designations / Positions)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  department_id INT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  -- Whether this role generally belongs to teaching staff
  is_teaching TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_roles_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  UNIQUE KEY uniq_roles_name_dept (name, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Employees (Master table for ALL staff)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Staff type: teaching vs non-teaching
  staff_type ENUM('teaching','non_teaching') NOT NULL,

  -- HR code e.g. TS-001, NTS-001 (unique across all staff)
  hr_code VARCHAR(20) NOT NULL UNIQUE,

  -- Personal Information
  first_name VARCHAR(80) NOT NULL,
  last_name  VARCHAR(80) NOT NULL,
  gender ENUM('male','female','other') NULL,
  date_of_birth DATE NULL,
  nationality VARCHAR(80) NULL,
  national_id VARCHAR(80) NULL,
  passport_photo VARCHAR(255) NULL,
  phone_number VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  emergency_contact VARCHAR(255) NULL,
  marital_status VARCHAR(40) NULL,

  -- Employment Information
  department_id INT UNSIGNED NULL,
  role_id       INT UNSIGNED NULL,
  employment_type ENUM('permanent','contract','part_time','intern','volunteer')
    NOT NULL DEFAULT 'permanent',
  date_joined DATE NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('active','on_leave','suspended','terminated')
    NOT NULL DEFAULT 'active',
  supervisor_id INT UNSIGNED NULL,

  -- Financial Information
  bank_name      VARCHAR(120) NULL,
  account_number VARCHAR(80)  NULL,
  mobile_money   VARCHAR(80)  NULL,
  tin_number     VARCHAR(80)  NULL,
  nssf_number    VARCHAR(80)  NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_employees_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_employees_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_employees_supervisor
    FOREIGN KEY (supervisor_id) REFERENCES employees(id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX idx_employees_staff_type (staff_type),
  INDEX idx_employees_department (department_id),
  INDEX idx_employees_role (role_id),
  INDEX idx_employees_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Teaching Staff (HR view, complements academic `teachers` table)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS teaching_staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,

  -- Optional mirror of the academic teacher code if you want to store it
  ts_code VARCHAR(20) NULL,

  -- Teaching load & allocation
  main_subject    VARCHAR(120) NULL,
  other_subjects  VARCHAR(255) NULL,
  classes_assigned TEXT NULL,
  streams_assigned TEXT NULL,
  weekly_teaching_load INT UNSIGNED NOT NULL DEFAULT 0,
  max_weekly_load   INT UNSIGNED NOT NULL DEFAULT 0,
  lesson_attendance_target DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_teaching_staff_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uniq_teaching_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Non-Teaching Staff (HR details)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS non_teaching_staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,

  duty_assignment VARCHAR(255) NULL,
  shift_schedule  VARCHAR(255) NULL,
  specialization  VARCHAR(255) NULL,
  staff_status ENUM('active','relieved','transferred','terminated')
    NOT NULL DEFAULT 'active',

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_non_teaching_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uniq_non_teaching_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Timetable (Teacher & Class Scheduling)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS timetable (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  academic_year VARCHAR(9) NOT NULL, -- e.g. "2025" or "2025/2026"
  term TINYINT UNSIGNED NOT NULL,    -- 1, 2, 3

  day_of_week TINYINT UNSIGNED NOT NULL, -- 1=Mon ... 7=Sun
  period_index TINYINT UNSIGNED NOT NULL, -- Period number in the day
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,

  class_name VARCHAR(80) NOT NULL,
  stream     VARCHAR(40) NULL,
  subject    VARCHAR(120) NOT NULL,
  room       VARCHAR(80) NULL,

  employee_id INT UNSIGNED NOT NULL, -- Teacher (from employees)

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_timetable_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  INDEX idx_timetable_teacher (employee_id, academic_year, term),
  INDEX idx_timetable_class (class_name, stream, academic_year, term),
  INDEX idx_timetable_day (academic_year, term, day_of_week, period_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Class Teacher Assignments (Class / Stream Owners)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS class_teacher_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  academic_year VARCHAR(9) NOT NULL,
  class_name    VARCHAR(80) NOT NULL,
  stream        VARCHAR(40) NULL,

  employee_id INT UNSIGNED NOT NULL,  -- Class teacher (from employees)
  is_primary  TINYINT(1) NOT NULL DEFAULT 1,

  start_date DATE NOT NULL,
  end_date   DATE NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_class_teacher_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  INDEX idx_class_teacher_current (academic_year, class_name, stream, is_primary, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Leave Management
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS leave_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  employee_id INT UNSIGNED NOT NULL,
  leave_type  VARCHAR(50) NOT NULL,  -- e.g. Annual, Sick, Maternity
  start_date  DATE NOT NULL,
  end_date    DATE NOT NULL,
  total_days  DECIMAL(5,2) NOT NULL,
  reason      TEXT NULL,

  status ENUM('pending','approved','rejected','cancelled')
    NOT NULL DEFAULT 'pending',
  approver_id INT UNSIGNED NULL,

  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at   TIMESTAMP NULL,

  balance_before DECIMAL(5,2) NULL,
  balance_after  DECIMAL(5,2) NULL,

  CONSTRAINT fk_leave_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_leave_approver
    FOREIGN KEY (approver_id) REFERENCES employees(id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX idx_leave_employee (employee_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Payroll
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS payroll (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  employee_id INT UNSIGNED NOT NULL,

  -- Pay period in YYYY-MM format (e.g. 2025-01)
  pay_period VARCHAR(7) NOT NULL,

  basic_salary DECIMAL(12,2) NOT NULL,
  allowances   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deductions   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_pay      DECIMAL(12,2) NOT NULL,

  payment_date DATE NULL,
  status ENUM('draft','processed','paid','void')
    NOT NULL DEFAULT 'draft',

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_payroll_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  UNIQUE KEY uniq_payroll_employee_period (employee_id, pay_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Employee Documents
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS employee_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  employee_id INT UNSIGNED NOT NULL,
  doc_type ENUM('cv','certificate','contract','appointment_letter','other')
    NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,

  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT UNSIGNED NULL,

  CONSTRAINT fk_employee_documents_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_employee_documents_uploader
    FOREIGN KEY (uploaded_by) REFERENCES employees(id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX idx_employee_documents_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- End of HR core schema
