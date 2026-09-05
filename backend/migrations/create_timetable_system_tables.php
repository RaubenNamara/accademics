<?php
/**
 * Timetable Management System Database Migration
 * Creates all necessary tables for the comprehensive timetable system
 */

require_once __DIR__ . '/../config/Database.php';

$db = (new Database())->getConnection();

try {
    echo "Starting timetable system migration...\n";

    // 1. School Events Table
    echo "Creating school_events table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS school_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(100) NOT NULL,
            event_type ENUM('devotion', 'assembly', 'breakfast', 'break', 'lunch', 'mentorship', 'games', 'clubs', 'prep', 'supper', 'other') NOT NULL,
            event_color VARCHAR(7) DEFAULT '#FF6B6B',
            event_description TEXT,
            is_mandatory BOOLEAN DEFAULT FALSE,
            applies_to_all_classes BOOLEAN DEFAULT TRUE,
            duration_minutes INT DEFAULT 40,
            spans_periods INT DEFAULT 1,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Teacher Availability Table
    echo "Creating teacher_availability table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS teacher_availability (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
            period_number INT NOT NULL,
            is_available BOOLEAN DEFAULT TRUE,
            reason VARCHAR(255),
            academic_session_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL,
            UNIQUE KEY unique_teacher_period (teacher_id, day_of_week, period_number, academic_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Enhanced Bell Schedule Table (if not exists)
    echo "Checking/creating timetable_bell_schedules table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_bell_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            schedule_name VARCHAR(100) NOT NULL,
            schedule_type ENUM('weekly', 'fortnightly', 'custom_cycle', 'day_rotation') DEFAULT 'weekly',
            cycle_days INT DEFAULT 5,
            is_uniform_schedule BOOLEAN DEFAULT TRUE,
            is_active BOOLEAN DEFAULT TRUE,
            academic_session_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Bell Period Details Table
    echo "Creating timetable_bell_periods table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_bell_periods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bell_schedule_id INT NOT NULL,
            period_number INT NOT NULL,
            period_name VARCHAR(50) DEFAULT 'Period',
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') DEFAULT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            period_type ENUM('lesson', 'devotion', 'breakfast', 'break', 'lunch', 'mentorship', 'games', 'prep', 'supper', 'other') DEFAULT 'lesson',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (bell_schedule_id) REFERENCES timetable_bell_schedules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 5. Enhanced Lesson Requirements Table
    echo "Creating timetable_lesson_requirements table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_lesson_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_session_id INT NOT NULL,
            class_id INT NOT NULL,
            stream VARCHAR(50),
            subject_id INT NOT NULL,
            teacher_id INT,
            room_id INT,
            periods_per_week INT NOT NULL DEFAULT 1,
            double_lesson_allowed BOOLEAN DEFAULT FALSE,
            double_lesson_required BOOLEAN DEFAULT FALSE,
            preferred_days JSON,
            preferred_periods JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
            UNIQUE KEY unique_class_subject_session (academic_session_id, class_id, stream, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 6. Constraints Table
    echo "Creating timetable_constraints table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_constraints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            constraint_type ENUM('teacher', 'subject', 'class', 'room') NOT NULL,
            constraint_name VARCHAR(100) NOT NULL,
            constraint_value JSON NOT NULL,
            constraint_rule ENUM('no_double_booking', 'max_lessons_per_day', 'min_free_periods', 'max_consecutive_lessons', 'preferred_periods', 'room_restrictions', 'no_back_to_back', 'same_room_for_subject', 'other') NOT NULL,
            severity ENUM('critical', 'warning', 'info') DEFAULT 'warning',
            is_active BOOLEAN DEFAULT TRUE,
            academic_session_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 7. Timetable Versions Table
    echo "Creating timetable_versions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version_name VARCHAR(100) NOT NULL,
            version_number INT NOT NULL,
            academic_session_id INT NOT NULL,
            generation_mode ENUM('automatic', 'semi_automatic', 'manual') DEFAULT 'automatic',
            optimization_mode ENUM('balanced', 'fast', 'maximum_accuracy') DEFAULT 'balanced',
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            total_conflicts INT DEFAULT 0,
            total_lessons INT DEFAULT 0,
            generated_by INT,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            archived_at TIMESTAMP NULL,
            notes TEXT,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES teachers(id) ON DELETE SET NULL,
            UNIQUE KEY unique_version_session (academic_session_id, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 8. Timetable Locks Table (for drag-and-drop editing)
    echo "Creating timetable_locks table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timetable_entry_id INT NOT NULL,
            is_locked BOOLEAN DEFAULT FALSE,
            locked_by INT,
            locked_at TIMESTAMP NULL,
            lock_reason VARCHAR(255),
            FOREIGN KEY (timetable_entry_id) REFERENCES timetable(id) ON DELETE CASCADE,
            FOREIGN KEY (locked_by) REFERENCES teachers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 9. Timetable Settings Table
    echo "Creating timetable_settings table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
            description VARCHAR(255),
            category VARCHAR(50) DEFAULT 'general',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Insert default settings
    echo "Inserting default timetable settings...\n";
    $defaultSettings = [
        ['max_lessons_per_day', '8', 'integer', 'Maximum lessons a teacher can have per day', 'constraints'],
        ['min_free_periods', '1', 'integer', 'Minimum free periods per day for teachers', 'constraints'],
        ['max_consecutive_lessons', '3', 'integer', 'Maximum consecutive lessons without a break', 'constraints'],
        ['default_generation_mode', 'automatic', 'string', 'Default timetable generation mode', 'generation'],
        ['default_optimization_mode', 'balanced', 'string', 'Default optimization mode', 'generation'],
        ['auto_save_versions', 'true', 'boolean', 'Automatically save timetable versions', 'versioning'],
        ['conflict_check_on_save', 'true', 'boolean', 'Run conflict checker on save', 'validation'],
        ['pdf_include_teacher_initials', 'true', 'boolean', 'Include teacher initials in PDF exports', 'export'],
        ['pdf_include_subject_codes', 'true', 'boolean', 'Include subject codes in PDF exports', 'export'],
    ];

    foreach ($defaultSettings as $setting) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO timetable_settings (setting_key, setting_value, setting_type, description, category)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute($setting);
    }

    // 10. Analytics Data Table (for caching analytics)
    echo "Creating timetable_analytics table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS timetable_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_session_id INT NOT NULL,
            timetable_version_id INT,
            metric_type ENUM('teacher_workload', 'subject_coverage', 'class_coverage', 'room_utilization', 'conflict_trends') NOT NULL,
            metric_data JSON NOT NULL,
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (timetable_version_id) REFERENCES timetable_versions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "\n✅ Timetable system migration completed successfully!\n";
    echo "Created/verified tables:\n";
    echo "  - school_events\n";
    echo "  - teacher_availability\n";
    echo "  - timetable_bell_schedules\n";
    echo "  - timetable_bell_periods\n";
    echo "  - timetable_lesson_requirements\n";
    echo "  - timetable_constraints\n";
    echo "  - timetable_versions\n";
    echo "  - timetable_locks\n";
    echo "  - timetable_settings\n";
    echo "  - timetable_analytics\n";

} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
