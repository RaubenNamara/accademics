<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'check') {
            checkConflicts($db);
        } else {
            getConflicts($db);
        }
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getConflicts(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $year = (int)($_GET['academic_year'] ?? date('Y'));
    $term = (int)($_GET['term'] ?? 1);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $conflicts = [];
    
    // Check teacher conflicts (double booking)
    $teacherConflicts = $db->prepare("
        SELECT 
            tt.id,
            'Teacher Double Booking' as conflict_type,
            t.full_name as teacher_name,
            t.teacher_code,
            tt.day_of_week,
            tt.period_number,
            c.class_name,
            s.subject_name
        FROM timetable tt
        JOIN teachers t ON tt.teacher_id = t.id
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        WHERE tt.academic_session_id = :session_id
        AND tt.entry_type = 'lesson'
        GROUP BY tt.day_of_week, tt.period_number, tt.teacher_id
        HAVING COUNT(*) > 1
    ");
    $teacherConflicts->execute([':session_id' => $session_id]);
    $conflicts['teacher_conflicts'] = $teacherConflicts->fetchAll(PDO::FETCH_ASSOC);
    
    // Check class conflicts
    $classConflicts = $db->prepare("
        SELECT 
            tt.id,
            'Class Double Booking' as conflict_type,
            c.class_name,
            tt.day_of_week,
            tt.period_number,
            t.full_name as teacher_name,
            s.subject_name
        FROM timetable tt
        JOIN classes c ON tt.class_id = c.id
        LEFT JOIN teachers t ON tt.teacher_id = t.id
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        WHERE tt.academic_session_id = :session_id
        AND tt.entry_type = 'lesson'
        GROUP BY tt.day_of_week, tt.period_number, tt.class_id
        HAVING COUNT(*) > 1
    ");
    $classConflicts->execute([':session_id' => $session_id]);
    $conflicts['class_conflicts'] = $classConflicts->fetchAll(PDO::FETCH_ASSOC);
    
    // Check room conflicts
    $roomConflicts = $db->prepare("
        SELECT 
            tt.id,
            'Room Double Booking' as conflict_type,
            r.room_code,
            r.room_name,
            tt.day_of_week,
            tt.period_number,
            c.class_name,
            s.subject_name
        FROM timetable tt
        JOIN rooms r ON tt.room_id = r.id
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        WHERE tt.academic_session_id = :session_id
        AND tt.entry_type = 'lesson'
        AND tt.room_id IS NOT NULL
        GROUP BY tt.day_of_week, tt.period_number, tt.room_id
        HAVING COUNT(*) > 1
    ");
    $roomConflicts->execute([':session_id' => $session_id]);
    $conflicts['room_conflicts'] = $roomConflicts->fetchAll(PDO::FETCH_ASSOC);
    
    // Check missing lesson requirements
    $missingRequirements = $db->prepare("
        SELECT 
            lr.id,
            'Missing Lesson' as conflict_type,
            c.class_name,
            s.subject_name,
            t.full_name as teacher_name,
            lr.periods_per_week as required_periods,
            COALESCE(COUNT(tt.id), 0) as scheduled_periods
        FROM lesson_requirements lr
        JOIN classes c ON lr.class_id = c.id
        JOIN subjects_new s ON lr.subject_id = s.id
        JOIN teachers t ON lr.teacher_id = t.id
        LEFT JOIN timetable tt ON 
            lr.academic_session_id = tt.academic_session_id AND
            lr.class_id = tt.class_id AND
            lr.subject_id = tt.subject_id AND
            lr.teacher_id = tt.teacher_id AND
            tt.entry_type = 'lesson'
        WHERE lr.academic_session_id = :session_id
        GROUP BY lr.id
        HAVING scheduled_periods < required_periods
    ");
    $missingRequirements->execute([':session_id' => $session_id]);
    $conflicts['missing_lessons'] = $missingRequirements->fetchAll(PDO::FETCH_ASSOC);
    
    // Check teacher overload (max lessons per day)
    $teacherOverload = $db->prepare("
        SELECT 
            tt.id,
            'Teacher Overload' as conflict_type,
            t.full_name as teacher_name,
            t.teacher_code,
            tt.day_of_week,
            COUNT(*) as lessons_count
        FROM timetable tt
        JOIN teachers t ON tt.teacher_id = t.id
        WHERE tt.academic_session_id = :session_id
        AND tt.entry_type = 'lesson'
        GROUP BY tt.day_of_week, tt.teacher_id
        HAVING lessons_count > 6
    ");
    $teacherOverload->execute([':session_id' => $session_id]);
    $conflicts['teacher_overload'] = $teacherOverload->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Conflicts checked', $conflicts);
}

function checkConflicts(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $teacher_id = (int)($_GET['teacher_id'] ?? 0);
    $class_id = (int)($_GET['class_id'] ?? 0);
    $room_id = (int)($_GET['room_id'] ?? 0);
    $day = $_GET['day'] ?? '';
    $period = (int)($_GET['period'] ?? 0);
    
    $conflicts = [];
    
    // Check teacher conflict
    if ($teacher_id > 0 && $day && $period > 0) {
        $teacherCheck = $db->prepare("
            SELECT COUNT(*) as count
            FROM timetable
            WHERE academic_session_id = :session_id
            AND teacher_id = :teacher_id
            AND day_of_week = :day
            AND period_number = :period
            AND entry_type = 'lesson'
        ");
        $teacherCheck->execute([
            ':session_id' => $session_id,
            ':teacher_id' => $teacher_id,
            ':day' => $day,
            ':period' => $period
        ]);
        
        if ($teacherCheck->fetchColumn() > 0) {
            $conflicts[] = 'Teacher is already scheduled at this time';
        }
    }
    
    // Check class conflict
    if ($class_id > 0 && $day && $period > 0) {
        $classCheck = $db->prepare("
            SELECT COUNT(*) as count
            FROM timetable
            WHERE academic_session_id = :session_id
            AND class_id = :class_id
            AND day_of_week = :day
            AND period_number = :period
            AND entry_type = 'lesson'
        ");
        $classCheck->execute([
            ':session_id' => $session_id,
            ':class_id' => $class_id,
            ':day' => $day,
            ':period' => $period
        ]);
        
        if ($classCheck->fetchColumn() > 0) {
            $conflicts[] = 'Class already has a lesson at this time';
        }
    }
    
    // Check room conflict
    if ($room_id > 0 && $day && $period > 0) {
        $roomCheck = $db->prepare("
            SELECT COUNT(*) as count
            FROM timetable
            WHERE academic_session_id = :session_id
            AND room_id = :room_id
            AND day_of_week = :day
            AND period_number = :period
            AND entry_type = 'lesson'
        ");
        $roomCheck->execute([
            ':session_id' => $session_id,
            ':room_id' => $room_id,
            ':day' => $day,
            ':period' => $period
        ]);
        
        if ($roomCheck->fetchColumn() > 0) {
            $conflicts[] = 'Room is already booked at this time';
        }
    }
    
    hr_respond(true, 'Conflict check complete', ['conflicts' => $conflicts]);
}
