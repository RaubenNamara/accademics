-- Add optional fields to duty_performance table
ALTER TABLE duty_performance
ADD COLUMN areas_of_improvement TEXT DEFAULT NULL AFTER comment,
ADD COLUMN general_remarks TEXT DEFAULT NULL AFTER areas_of_improvement,
ADD COLUMN supervisor VARCHAR(255) DEFAULT NULL AFTER general_remarks;
