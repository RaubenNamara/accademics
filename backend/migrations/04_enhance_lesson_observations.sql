-- Add round fields to lesson_observations table
ALTER TABLE lesson_observations
ADD COLUMN score1 DECIMAL(3,1) DEFAULT 0 AFTER term,
ADD COLUMN aoi1 VARCHAR(255) DEFAULT '' AFTER score1,
ADD COLUMN action_points1 TEXT DEFAULT '' AFTER aoi1,
ADD COLUMN score2 DECIMAL(3,1) DEFAULT 0 AFTER action_points1,
ADD COLUMN aoi2 VARCHAR(255) DEFAULT '' AFTER score2,
ADD COLUMN action_points2 TEXT DEFAULT '' AFTER aoi2,
ADD COLUMN average_score DECIMAL(3,1) DEFAULT 0 AFTER action_points2,
ADD COLUMN overall_comment TEXT DEFAULT '' AFTER average_score;
