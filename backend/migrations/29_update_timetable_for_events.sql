-- Update timetable table to support both subjects and events
ALTER TABLE timetable ADD COLUMN entry_type ENUM('subject', 'event') NOT NULL DEFAULT 'subject';
ALTER TABLE timetable ADD COLUMN event_id INT NULL;
ALTER TABLE timetable ADD COLUMN event_name VARCHAR(100) NULL;
ALTER TABLE timetable ADD COLUMN event_color VARCHAR(20) NULL;
ALTER TABLE timetable ADD COLUMN event_description TEXT NULL;
ALTER TABLE timetable ADD COLUMN spans_periods TINYINT UNSIGNED NULL DEFAULT 1;

-- Add foreign key for event_id
ALTER TABLE timetable ADD CONSTRAINT fk_timetable_event FOREIGN KEY (event_id) REFERENCES school_events(id) ON DELETE SET NULL;

-- Add index for event_id
ALTER TABLE timetable ADD INDEX idx_timetable_event (event_id);
