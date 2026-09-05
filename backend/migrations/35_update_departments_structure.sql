-- Migration 35: Update departments table structure
-- This migration removes the position column and adds the description column

SET @db := DATABASE();

-- Check if departments table exists
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'departments'
);

SET @sql = IF(@table_exists = 0,
    'SELECT "Table departments does not exist" AS message',
    'SELECT "Table departments exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove position column if it exists
SET @position_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'departments'
    AND COLUMN_NAME = 'position'
);

SET @sql = IF(@position_exists > 0,
    'ALTER TABLE departments DROP COLUMN position',
    'SELECT "Column position does not exist" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add description column if it doesn't exist
SET @desc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'departments'
    AND COLUMN_NAME = 'description'
);

SET @sql = IF(@desc_exists = 0,
    'ALTER TABLE departments ADD COLUMN description TEXT AFTER name',
    'SELECT "Column description already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the migration
SELECT 'Migration 35 completed successfully' AS status;
