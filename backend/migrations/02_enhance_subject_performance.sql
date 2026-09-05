-- Add TC fields and comments to subject_teacher_performance table
ALTER TABLE subject_teacher_performance 
ADD COLUMN tc1 DECIMAL(10,2) DEFAULT 0 AFTER eot1,
ADD COLUMN tc1_comment VARCHAR(255) DEFAULT '' AFTER tc1,
ADD COLUMN tc2 DECIMAL(10,2) DEFAULT 0 AFTER eot2,
ADD COLUMN tc2_comment VARCHAR(255) DEFAULT '' AFTER tc2,
ADD COLUMN tc3 DECIMAL(10,2) DEFAULT 0 AFTER eot3,
ADD COLUMN tc3_comment VARCHAR(255) DEFAULT '' AFTER tc3,
ADD COLUMN agp_comment VARCHAR(255) DEFAULT '' AFTER agp;
