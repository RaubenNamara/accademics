-- Add obligation column to teachers table
ALTER TABLE teachers ADD COLUMN obligation VARCHAR(100) DEFAULT 'Subject Teacher' AFTER stream;

-- Update existing records
UPDATE teachers SET obligation = 'Subject Teacher' WHERE obligation IS NULL OR obligation = '';
