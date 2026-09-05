-- Update class_teacher_performance table for combined weekly assessment and academic performance system
-- Add new columns for weekly assessment system while keeping existing academic columns

ALTER TABLE class_teacher_performance 
ADD COLUMN week INT NOT NULL DEFAULT 1 COMMENT 'Week number (1-13)',
ADD COLUMN roll_call_score INT NOT NULL DEFAULT 12 COMMENT 'Roll call score (0,12,15,20)',
ADD COLUMN mentorship_score INT NOT NULL DEFAULT 12 COMMENT 'Mentorship score (0,12,15,20)',
ADD COLUMN devotion_score INT NOT NULL DEFAULT 12 COMMENT 'Devotion score (0,12,15,20)',
ADD COLUMN cleanliness_score INT NOT NULL DEFAULT 12 COMMENT 'Cleanliness score (0,12,15,20)',
ADD COLUMN parent_contacted BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether parent was contacted',
ADD COLUMN weekly_score INT NOT NULL DEFAULT 0 COMMENT 'Total weekly score (max 80)';

-- Add unique constraint for teacher, class, stream, year, term, week
ALTER TABLE class_teacher_performance 
ADD UNIQUE KEY unique_teacher_week (teacher_id, class, stream, year, term, week);
