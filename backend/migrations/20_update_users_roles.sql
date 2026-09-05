-- Migration 20: Extend users.role to match application roles (super_admin, teacher, staff)

ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'super_admin',
    'admin',
    'teacher',
    'staff',
    'academic_office',
    'hr_manager'
  ) NOT NULL DEFAULT 'admin';
