-- Migration 34: Update departments table structure
-- This migration updates the departments table to use position as VARCHAR and removes description

SET @db := DATABASE();

-- Check if position column exists and add it if it doesn't
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db 
    AND TABLE_NAME = 'departments' 
    AND COLUMN_NAME = 'position'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE departments ADD COLUMN position VARCHAR(100) NOT NULL DEFAULT "" AFTER name',
    'SELECT "Column position already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove description column if it exists
SET @desc_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db 
    AND TABLE_NAME = 'departments' 
    AND COLUMN_NAME = 'description'
);

SET @sql = IF(@desc_exists > 0, 
    'ALTER TABLE departments DROP COLUMN description',
    'SELECT "Column description does not exist" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the migration
SELECT 'Migration 34 completed successfully' AS status;
