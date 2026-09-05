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
        
        if ($action === 'teacher-workload') {
            getTeacherWorkload($db);
        } elseif ($action === 'subject-coverage') {
            getSubjectCoverage($db);
        } elseif ($action === 'class-coverage') {
            getClassCoverage($db);
        } elseif ($action === 'room-utilization') {
            getRoomUtilization($db);
        } elseif ($action === 'conflict-trends') {
            getConflictTrends($db);
        } elseif ($action === 'dashboard') {
            getDashboard($db);
        } else {
            hr_respond(false, 'Invalid action', null, 400);
        }
    } else {
        hr_respond(false, 'Method not allowed', null, 405);
    }
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getTeacherWorkload(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $sql = "
        SELECT 
            t.id,
            t.full_name,
            t.teacher_code,
            COUNT(DISTINCT tt.id) as total_lessons,
            COUNT(DISTINCT tt.class_id) as classes_taught,
            COUNT(DISTINCT tt.subject_id) as subjects_taught,
            SUM(CASE WHEN tt.day_of_week = 'Monday' THEN 1 ELSE 0 END) as monday_lessons,
            SUM(CASE WHEN tt.day_of_week = 'Tuesday' THEN 1 ELSE 0 END) as tuesday_lessons,
            SUM(CASE WHEN tt.day_of_week = 'Wednesday' THEN 1 ELSE 0 END) as wednesday_lessons,
            SUM(CASE WHEN tt.day_of_week = 'Thursday' THEN 1 ELSE 0 END) as thursday_lessons,
            SUM(CASE WHEN tt.day_of_week = 'Friday' THEN 1 ELSE 0 END) as friday_lessons
        FROM teachers t
        LEFT JOIN timetable tt ON t.id = tt.teacher_id 
            AND tt.academic_year = :year 
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        WHERE t.is_active = 1
        GROUP BY t.id, t.full_name, t.teacher_code
        ORDER BY total_lessons DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $workload = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Teacher workload loaded', $workload);
}

function getSubjectCoverage(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $sql = "
        SELECT 
            s.id,
            s.subject_name,
            s.subject_code,
            COUNT(DISTINCT tt.class_id) as classes_covered,
            COUNT(DISTINCT tt.teacher_id) as teachers_assigned,
            COUNT(DISTINCT tt.id) as total_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Monday' THEN tt.id END) as monday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Tuesday' THEN tt.id END) as tuesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Wednesday' THEN tt.id END) as wednesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Thursday' THEN tt.id END) as thursday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Friday' THEN tt.id END) as friday_lessons
        FROM subjects_new s
        LEFT JOIN timetable tt ON s.id = tt.subject_id 
            AND tt.academic_year = :year 
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        GROUP BY s.id, s.subject_name, s.subject_code
        ORDER BY total_lessons DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $coverage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Subject coverage loaded', $coverage);
}

function getClassCoverage(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $sql = "
        SELECT 
            c.id,
            c.class_name,
            c.stream_name,
            COUNT(DISTINCT tt.subject_id) as subjects_covered,
            COUNT(DISTINCT tt.teacher_id) as teachers_assigned,
            COUNT(DISTINCT tt.id) as total_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Monday' THEN tt.id END) as monday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Tuesday' THEN tt.id END) as tuesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Wednesday' THEN tt.id END) as wednesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Thursday' THEN tt.id END) as thursday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Friday' THEN tt.id END) as friday_lessons
        FROM classes c
        LEFT JOIN timetable tt ON c.id = tt.class_id 
            AND tt.academic_year = :year 
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        GROUP BY c.id, c.class_name, c.stream_name
        ORDER BY c.class_name
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $coverage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Class coverage loaded', $coverage);
}

function getRoomUtilization(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $sql = "
        SELECT 
            r.id,
            r.room_code,
            r.room_name,
            r.room_type,
            COUNT(DISTINCT tt.class_id) as classes_using,
            COUNT(DISTINCT tt.subject_id) as subjects_taught,
            COUNT(DISTINCT tt.id) as total_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Monday' THEN tt.id END) as monday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Tuesday' THEN tt.id END) as tuesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Wednesday' THEN tt.id END) as wednesday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Thursday' THEN tt.id END) as thursday_lessons,
            COUNT(DISTINCT CASE WHEN tt.day_of_week = 'Friday' THEN tt.id END) as friday_lessons
        FROM rooms r
        LEFT JOIN timetable tt ON r.id = tt.room_id 
            AND tt.academic_year = :year 
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        GROUP BY r.id, r.room_code, r.room_name, r.room_type
        ORDER BY total_lessons DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $utilization = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Room utilization loaded', $utilization);
}

function getConflictTrends(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    // Get conflict counts by type
    $sql = "
        SELECT 
            'teacher_conflicts' as conflict_type,
            COUNT(*) as conflict_count
        FROM timetable_conflicts tc
        JOIN academic_sessions as ON as.id = :session_id
        WHERE tc.academic_year = as.academic_year AND tc.term = as.term
        
        UNION ALL
        
        SELECT 
            'class_conflicts' as conflict_type,
            COUNT(*) as conflict_count
        FROM timetable_conflicts tc
        JOIN academic_sessions as ON as.id = :session_id
        WHERE tc.academic_year = as.academic_year AND tc.term = as.term
        
        UNION ALL
        
        SELECT 
            'room_conflicts' as conflict_type,
            COUNT(*) as conflict_count
        FROM timetable_conflicts tc
        JOIN academic_sessions as ON as.id = :session_id
        WHERE tc.academic_year = as.academic_year AND tc.term = as.term
        
        UNION ALL
        
        SELECT 
            'missing_lessons' as conflict_type,
            COUNT(*) as conflict_count
        FROM timetable_conflicts tc
        JOIN academic_sessions as ON as.id = :session_id
        WHERE tc.academic_year = as.academic_year AND tc.term = as.term
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Conflict trends loaded', $trends);
}

function getDashboard(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $year = $session['academic_year'];
    $term = $session['term'];
    
    // Get summary stats
    $stats = [
        'total_teachers' => 0,
        'total_classes' => 0,
        'total_subjects' => 0,
        'total_rooms' => 0,
        'total_lessons' => 0,
        'total_events' => 0,
        'teacher_conflicts' => 0,
        'class_conflicts' => 0,
        'room_conflicts' => 0,
        'missing_lessons' => 0
    ];
    
    // Count active entities
    $stats['total_teachers'] = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1")->fetchColumn();
    $stats['total_classes'] = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $stats['total_subjects'] = $db->query("SELECT COUNT(*) FROM subjects_new")->fetchColumn();
    $stats['total_rooms'] = $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    
    // Count timetable entries
    $lessonStmt = $db->prepare("SELECT COUNT(*) FROM timetable WHERE academic_year = :year AND term = :term AND entry_type = 'lesson'");
    $lessonStmt->execute([':year' => $year, ':term' => $term]);
    $stats['total_lessons'] = $lessonStmt->fetchColumn();
    
    $eventStmt = $db->prepare("SELECT COUNT(*) FROM timetable WHERE academic_year = :year AND term = :term AND entry_type = 'event'");
    $eventStmt->execute([':year' => $year, ':term' => $term]);
    $stats['total_events'] = $eventStmt->fetchColumn();
    
    // Get conflict counts (simplified - would normally query conflict table)
    $stats['teacher_conflicts'] = 0;
    $stats['class_conflicts'] = 0;
    $stats['room_conflicts'] = 0;
    $stats['missing_lessons'] = 0;
    
    hr_respond(true, 'Dashboard data loaded', $stats);
}
