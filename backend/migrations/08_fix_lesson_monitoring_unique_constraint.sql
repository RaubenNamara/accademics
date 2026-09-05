-- Check if there's a foreign key using the unique constraint
-- If so, we need to drop the foreign key first, then the index, then recreate both

-- Step 1: Drop any foreign key that might be using the index
ALTER TABLE lesson_monitoring DROP FOREIGN KEY IF EXISTS lesson_monitoring_ibfk_1;

-- Step 2: Drop the old unique constraint
ALTER TABLE lesson_monitoring DROP INDEX unique_teacher_term;

-- Step 3: Add the new unique constraint that includes class and stream
ALTER TABLE lesson_monitoring
ADD UNIQUE KEY unique_teacher_term_class_stream (teacher_id, year, term, class, stream);

-- Step 4: Recreate the foreign key constraint if it existed
ALTER TABLE lesson_monitoring
ADD CONSTRAINT lesson_monitoring_ibfk_1
FOREIGN KEY (teacher_id) REFERENCES teachers(id)
ON DELETE CASCADE ON UPDATE CASCADE;
