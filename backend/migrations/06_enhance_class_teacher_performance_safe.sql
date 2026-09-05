-- Add missing columns to class_teacher_performance table individually
-- This will skip columns that already exist

ALTER TABLE class_teacher_performance ADD COLUMN roll_call INT DEFAULT 0 AFTER stream;
ALTER TABLE class_teacher_performance ADD COLUMN mentorship INT DEFAULT 0 AFTER roll_call;
ALTER TABLE class_teacher_performance ADD COLUMN devotion INT DEFAULT 0 AFTER mentorship;
ALTER TABLE class_teacher_performance ADD COLUMN cleanliness INT DEFAULT 0 AFTER devotion;
ALTER TABLE class_teacher_performance ADD COLUMN bt1 DECIMAL(10,2) DEFAULT 0 AFTER cleanliness;
ALTER TABLE class_teacher_performance ADD COLUMN t1 DECIMAL(10,2) DEFAULT 0 AFTER bt1;
ALTER TABLE class_teacher_performance ADD COLUMN t2 DECIMAL(10,2) DEFAULT 0 AFTER t1;
ALTER TABLE class_teacher_performance ADD COLUMN t3 DECIMAL(10,2) DEFAULT 0 AFTER t2;
ALTER TABLE class_teacher_performance ADD COLUMN c1 DECIMAL(10,2) DEFAULT 0 AFTER t3;
ALTER TABLE class_teacher_performance ADD COLUMN c2 DECIMAL(10,2) DEFAULT 0 AFTER c1;
ALTER TABLE class_teacher_performance ADD COLUMN c3 DECIMAL(10,2) DEFAULT 0 AFTER c2;
ALTER TABLE class_teacher_performance ADD COLUMN average_score DECIMAL(10,2) DEFAULT 0 AFTER c3;
ALTER TABLE class_teacher_performance ADD COLUMN average_comment VARCHAR(255) DEFAULT '' AFTER average_score;
