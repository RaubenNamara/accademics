-- Migration: Simplify Lesson Observation Module
-- This migration adds new fields to support the simplified intelligent evaluation system
-- while maintaining compatibility with existing records

-- Add new columns to lesson_observations table
ALTER TABLE lesson_observations 
ADD COLUMN IF NOT EXISTS round INT DEFAULT 1 COMMENT 'Observation round (1-4)',
ADD COLUMN IF NOT EXISTS total_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Total score out of 100',
ADD COLUMN IF NOT EXISTS calculated_rating DECIMAL(3,2) DEFAULT NULL COMMENT 'Calculated rating (score/25)',
ADD COLUMN IF NOT EXISTS performance_category VARCHAR(50) DEFAULT NULL COMMENT 'Performance category (Outstanding, Very Good, Good, Fair, Below Expectation)',
ADD COLUMN IF NOT EXISTS strengths_observed TEXT DEFAULT NULL COMMENT 'Auto-generated strengths based on performance',
ADD COLUMN IF NOT EXISTS general_comment TEXT DEFAULT NULL COMMENT 'Auto-generated general comment',
ADD COLUMN IF NOT EXISTS areas_for_improvement TEXT DEFAULT NULL COMMENT 'Manual areas for improvement';

-- Update existing records to set default round to 1
UPDATE lesson_observations SET round = 1 WHERE round IS NULL;

-- Create index on round for faster filtering
CREATE INDEX IF NOT EXISTS idx_lesson_observations_round ON lesson_observations(round);
