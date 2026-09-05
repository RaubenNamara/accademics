-- Add LIN column to students table
ALTER TABLE students ADD COLUMN lin VARCHAR(255) DEFAULT '' AFTER enrollment_date;

-- Add NIN column to parents table
ALTER TABLE parents ADD COLUMN nin VARCHAR(255) DEFAULT '' AFTER email;
