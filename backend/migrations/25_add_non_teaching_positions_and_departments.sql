 -- Migration 25: Add Non-Teaching Staff Positions and Departments
-- This migration adds new departments and positions for non-teaching staff
-- Each position is linked to its respective department via foreign key

SET @db := DATABASE();

-- Insert new departments (if they don't exist)
INSERT IGNORE INTO departments (name, description) VALUES
('ICT Department', 'Information and Communication Technology'),
('Science Department', 'Science Laboratory and Research'),
('Administration Department', 'School Administration and Management'),
('Finance Department', 'Financial Management and Accounting'),
('Chaplaincy Department', 'Spiritual Guidance and Chaplaincy'),
('Guidance & Counselling Department', 'Student Guidance and Counselling Services'),
('Procurement Department', 'Procurement and Supply Chain Management'),
('Transport Department', 'Transport and Logistics'),
('Catering Department', 'Food Services and Catering'),
('Academics Department', 'Academic Affairs and Examinations'),
('Public Relations Department', 'Public Relations and Communications'),
('Human Resource Department', 'Human Resource Management'),
('Maintenance Department', 'Facilities Maintenance and Repairs'),
('Media & Communications Department', 'Media Production and Communications'),
('Co-Curricular Activities Department', 'Co-Curricular and Extra-Curricular Activities'),
('Sports Department', 'Sports and Physical Education');

-- Get department IDs for inserting positions
SET @ict_dept = (SELECT id FROM departments WHERE name = 'ICT Department' LIMIT 1);
SET @science_dept = (SELECT id FROM departments WHERE name = 'Science Department' LIMIT 1);
SET @admin_dept = (SELECT id FROM departments WHERE name = 'Administration Department' LIMIT 1);
SET @finance_dept = (SELECT id FROM departments WHERE name = 'Finance Department' LIMIT 1);
SET @chaplaincy_dept = (SELECT id FROM departments WHERE name = 'Chaplaincy Department' LIMIT 1);
SET @guidance_dept = (SELECT id FROM departments WHERE name = 'Guidance & Counselling Department' LIMIT 1);
SET @procurement_dept = (SELECT id FROM departments WHERE name = 'Procurement Department' LIMIT 1);
SET @transport_dept = (SELECT id FROM departments WHERE name = 'Transport Department' LIMIT 1);
SET @catering_dept = (SELECT id FROM departments WHERE name = 'Catering Department' LIMIT 1);
SET @academics_dept = (SELECT id FROM departments WHERE name = 'Academics Department' LIMIT 1);
SET @pr_dept = (SELECT id FROM departments WHERE name = 'Public Relations Department' LIMIT 1);
SET @hr_dept = (SELECT id FROM departments WHERE name = 'Human Resource Department' LIMIT 1);
SET @maintenance_dept = (SELECT id FROM departments WHERE name = 'Maintenance Department' LIMIT 1);
SET @media_dept = (SELECT id FROM departments WHERE name = 'Media & Communications Department' LIMIT 1);
SET @cocurricular_dept = (SELECT id FROM departments WHERE name = 'Co-Curricular Activities Department' LIMIT 1);
SET @sports_dept = (SELECT id FROM departments WHERE name = 'Sports Department' LIMIT 1);

-- Insert new positions (roles) linked to departments
-- Using INSERT IGNORE to prevent duplicates
INSERT IGNORE INTO roles (name, department_id, description) VALUES
('ICT Lab Technician', @ict_dept, 'Manages and maintains ICT laboratory equipment and systems'),
('Science Lab Technician', @science_dept, 'Manages and maintains science laboratory equipment and materials'),
('Data Entrant', @admin_dept, 'Responsible for data entry and record management'),
('Systems Developer', @ict_dept, 'Develops and maintains software systems'),
('Assistant Finance Officer', @finance_dept, 'Assists in financial management and accounting'),
('Finance Officer', @finance_dept, 'Manages financial operations and accounting'),
('School Chaplain', @chaplaincy_dept, 'Provides spiritual guidance and chaplaincy services'),
('Guidance and Counselling Officer', @guidance_dept, 'Provides guidance and counselling services to students'),
('Procurement Officer', @procurement_dept, 'Manages procurement and supply chain operations'),
('School Driver', @transport_dept, 'Operates school vehicles for transport services'),
('Cook', @catering_dept, 'Prepares food for school catering services'),
('Exam Master', @academics_dept, 'Oversees examination administration and management'),
('Exam Secretary', @academics_dept, 'Assists in examination administration and record keeping'),
('Administrative Secretary', @admin_dept, 'Provides administrative secretarial support'),
('Public Relations Officer (P.R.O)', @pr_dept, 'Manages public relations and communications'),
('HR Secretary', @hr_dept, 'Provides human resource secretarial support'),
('Electrician', @maintenance_dept, 'Maintains and repairs electrical systems'),
('Content Manager', @media_dept, 'Manages content creation and media production'),
('Music Director', @cocurricular_dept, 'Directs music and co-curricular activities'),
('Coach', @sports_dept, 'Coaches sports and physical education activities');

-- Verify the migration
SELECT 'Migration 25 completed successfully' AS status;
SELECT COUNT(*) AS departments_added FROM departments WHERE name IN (
    'ICT Department', 'Science Department', 'Administration Department', 'Finance Department',
    'Chaplaincy Department', 'Guidance & Counselling Department', 'Procurement Department',
    'Transport Department', 'Catering Department', 'Academics Department',
    'Public Relations Department', 'Human Resource Department', 'Maintenance Department',
    'Media & Communications Department', 'Co-Curricular Activities Department', 'Sports Department'
);
SELECT COUNT(*) AS positions_added FROM roles WHERE name IN (
    'ICT Lab Technician', 'Science Lab Technician', 'Data Entrant', 'Systems Developer',
    'Assistant Finance Officer', 'Finance Officer', 'School Chaplain',
    'Guidance and Counselling Officer', 'Procurement Officer', 'School Driver',
    'Cook', 'Exam Master', 'Exam Secretary', 'Administrative Secretary',
    'Public Relations Officer (P.R.O)', 'HR Secretary', 'Electrician',
    'Content Manager', 'Music Director', 'Coach'
);
