-- Fix unique constraint for subject_teacher_performance to allow multiple records per teacher for different subject/class/stream combinations

-- Step 1: Drop any foreign key that might be using the index
ALTER TABLE subject_teacher_performance DROP FOREIGN KEY IF EXISTS subject_teacher_performance_ibfk_1;

-- Step 2: Drop the old unique constraint (if it exists)
ALTER TABLE subject_teacher_performance DROP INDEX IF EXISTS unique_teacher_term;

-- Step 3: Add the new unique constraint that includes subject, class, and stream
ALTER TABLE subject_teacher_performance
ADD UNIQUE KEY unique_teacher_term_subject_class_stream (teacher_id, year, term, subject, class, stream);

-- Step 4: Recreate the foreign key constraint
ALTER TABLE subject_teacher_performance
ADD CONSTRAINT subject_teacher_performance_ibfk_1
FOREIGN KEY (teacher_id) REFERENCES teachers(id)
ON DELETE CASCADE ON UPDATE CASCADE;
