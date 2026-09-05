# Live Server Deployment Guide

This guide lists all changes made to the local environment that need to be applied to the live server.

## Database Changes

Run the following SQL migrations on your live database:

### 1. Add day_of_week column to teacher_lesson_attendance table
```sql
ALTER TABLE teacher_lesson_attendance ADD COLUMN day_of_week VARCHAR(3) DEFAULT NULL AFTER attendance_date;

-- Update existing records with day_of_week
UPDATE teacher_lesson_attendance SET day_of_week = CASE DAYOFWEEK(attendance_date) 
    WHEN 1 THEN 'Sun' 
    WHEN 2 THEN 'Mon' 
    WHEN 3 THEN 'Tue' 
    WHEN 4 THEN 'Wed' 
    WHEN 5 THEN 'Thu' 
    WHEN 6 THEN 'Fri' 
    WHEN 7 THEN 'Sat' 
END WHERE day_of_week IS NULL;
```

### 2. Update lesson_compensations table schema
```sql
-- Add minutes_compensated column
ALTER TABLE lesson_compensations ADD COLUMN minutes_compensated INT DEFAULT 0 AFTER compensation_day;

-- Update status enum to include new values
ALTER TABLE lesson_compensations MODIFY COLUMN status ENUM('Pending', 'Completed', 'Partially Compensated', 'Fully Compensated') DEFAULT 'Partially Compensated';

-- Remove old columns (if they exist)
ALTER TABLE lesson_compensations DROP COLUMN IF EXISTS compensation_time;
ALTER TABLE lesson_compensations DROP COLUMN IF EXISTS periods_regained;
```

### 3. Apply existing compensations to original lessons
```sql
-- This will subtract compensated minutes from the original lesson's minutes_lost
UPDATE teacher_lesson_attendance a
SET a.minutes_lost = GREATEST(0, a.minutes_lost - (
    SELECT COALESCE(SUM(lc.minutes_compensated), 0)
    FROM lesson_compensations lc
    WHERE lc.lesson_monitoring_id = a.id
))
WHERE EXISTS (
    SELECT 1 FROM lesson_compensations lc WHERE lc.lesson_monitoring_id = a.id AND lc.minutes_compensated > 0
);
```

## Files to Upload

### Backend API Files
1. `backend/api/lesson-monitoring.php` - Updated to:
   - Read ID from URL query parameter for PUT requests
   - Add `compensation_status` field to GET query
   - Added logging for debugging

2. `backend/api/lesson-compensations.php` - Updated to:
   - Accept `minutes_compensated` instead of `compensation_time` and `periods_regained`
   - Remove duplicate compensation check
   - Add logic to subtract compensated minutes from original lesson
   - Added logging for debugging

3. `backend/api/auth.php` - Updated to:
   - Allow all origins for CORS
   - Added logging for debugging

### Frontend Files
Since your live server uses pre-built Vue files (assets/ and index.html), you need to build the Vue app locally and upload the compiled files:

1. Build the Vue app locally:
   ```bash
   npm run build
   ```

2. Upload the contents of the `dist/` folder to your live server:
   - `dist/index.html`
   - `dist/assets/` (all files in this folder)

The following source files were modified locally and will be included in the build:
1. `src/views/LessonMonitoring.vue` - Updated to:
   - Move `getDayOfWeek` function definition before usage
   - Change default term from 1 to 2
   - Update compensation form to use `minutes_compensated`
   - Add `remainingMinutesLost` computed property
   - Change status options to "Partially Compensated" and "Fully Compensated"
   - Add logging to `saveRecord` and `saveCompensation`
   - Update status display with color coding
   - Show "Complete" for records with 0 minutes lost

2. `src/views/Login.vue` - Updated to:
   - Add logging for debugging

## Deployment Steps

1. **Backup your live database** before making any changes

2. **Run the database migrations** in order:
   - Execute SQL from section 1 (day_of_week column)
   - Execute SQL from section 2 (lesson_compensations schema)
   - Execute SQL from section 3 (apply existing compensations)

3. **Upload the backend files**:
   - `backend/api/lesson-monitoring.php`
   - `backend/api/lesson-compensations.php`
   - `backend/api/auth.php`

4. **Build the frontend**:
   ```bash
   npm run build
   ```

5. **Upload the built files** to your live server:
   - Upload `dist/index.html` to replace the live `index.html`
   - Upload all files from `dist/assets/` to replace the live `assets/` folder

7. **Test the application**:
   - Test login functionality
   - Test lesson monitoring data display
   - Test editing/updating records
   - Test recording compensations with minutes input
   - Verify status colors display correctly
   - Verify "Complete" status shows for 0 minutes lost

## Verification Checklist

- [ ] Login works correctly
- [ ] Lesson monitoring data displays with year=2026, term=2
- [ ] Edit/update functionality works
- [ ] Compensation modal accepts minutes input
- [ ] Minutes compensated subtracts from original lesson
- [ ] Status shows "Partially Compensated" (amber), "Fully Compensated" (green), or "Not Compensated" (red)
- [ ] Records with 0 minutes lost show "Complete" status
- [ ] Day of week is auto-calculated and saved

## Rollback Plan

If anything goes wrong, restore the database from backup and revert the uploaded files to their previous versions.
