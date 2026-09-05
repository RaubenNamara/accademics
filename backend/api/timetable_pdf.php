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
        
        if ($action === 'class') {
            generateClassPDF($db);
        } elseif ($action === 'teacher') {
            generateTeacherPDF($db);
        } elseif ($action === 'room') {
            generateRoomPDF($db);
        } elseif ($action === 'master') {
            generateMasterPDF($db);
        } else {
            hr_respond(false, 'Invalid action', null, 400);
        }
    } else {
        hr_respond(false, 'Method not allowed', null, 405);
    }
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function generateClassPDF(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $class_id = (int)($_GET['class_id'] ?? 0);
    
    if ($session_id <= 0 || $class_id <= 0) {
        hr_respond(false, 'Session ID and Class ID are required', null, 400);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get class info
    $classStmt = $db->prepare("SELECT * FROM classes WHERE id = :class_id");
    $classStmt->execute([':class_id' => $class_id]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get timetable data
    $sql = "
        SELECT 
            tt.day_of_week,
            tt.period_number,
            s.subject_code,
            s.subject_name,
            t.teacher_code,
            t.full_name AS teacher_name,
            r.room_code,
            tt.entry_type,
            tt.event_name,
            tt.event_color
        FROM timetable tt
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        LEFT JOIN teachers t ON tt.teacher_id = t.id
        LEFT JOIN rooms r ON tt.room_id = r.id
        WHERE tt.class_id = :class_id
            AND tt.academic_year = :year
            AND tt.term = :term
        ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), tt.period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':class_id' => $class_id,
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML for PDF
    $html = generateTimetableHTML($class, $session, $timetable, 'class');
    
    hr_respond(true, 'Class timetable PDF data generated', [
        'html' => $html,
        'filename' => "timetable_{$class['class_name']}_{$session['session_name']}.pdf"
    ]);
}

function generateTeacherPDF(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $teacher_id = (int)($_GET['teacher_id'] ?? 0);
    
    if ($session_id <= 0 || $teacher_id <= 0) {
        hr_respond(false, 'Session ID and Teacher ID are required', null, 400);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get teacher info
    $teacherStmt = $db->prepare("SELECT * FROM teachers WHERE id = :teacher_id");
    $teacherStmt->execute([':teacher_id' => $teacher_id]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get timetable data
    $sql = "
        SELECT 
            tt.day_of_week,
            tt.period_number,
            s.subject_code,
            s.subject_name,
            c.class_name,
            c.stream_name,
            r.room_code,
            tt.entry_type,
            tt.event_name,
            tt.event_color
        FROM timetable tt
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN rooms r ON tt.room_id = r.id
        WHERE tt.teacher_id = :teacher_id
            AND tt.academic_year = :year
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), tt.period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML for PDF
    $html = generateTimetableHTML($teacher, $session, $timetable, 'teacher');
    
    hr_respond(true, 'Teacher timetable PDF data generated', [
        'html' => $html,
        'filename' => "timetable_{$teacher['teacher_code']}_{$session['session_name']}.pdf"
    ]);
}

function generateRoomPDF(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $room_id = (int)($_GET['room_id'] ?? 0);
    
    if ($session_id <= 0 || $room_id <= 0) {
        hr_respond(false, 'Session ID and Room ID are required', null, 400);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get room info
    $roomStmt = $db->prepare("SELECT * FROM rooms WHERE id = :room_id");
    $roomStmt->execute([':room_id' => $room_id]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get timetable data
    $sql = "
        SELECT 
            tt.day_of_week,
            tt.period_number,
            s.subject_code,
            s.subject_name,
            t.teacher_code,
            t.full_name AS teacher_name,
            c.class_name,
            c.stream_name,
            tt.entry_type,
            tt.event_name,
            tt.event_color
        FROM timetable tt
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        LEFT JOIN teachers t ON tt.teacher_id = t.id
        LEFT JOIN classes c ON tt.class_id = c.id
        WHERE tt.room_id = :room_id
            AND tt.academic_year = :year
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), tt.period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':room_id' => $room_id,
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML for PDF
    $html = generateTimetableHTML($room, $session, $timetable, 'room');
    
    hr_respond(true, 'Room timetable PDF data generated', [
        'html' => $html,
        'filename' => "timetable_{$room['room_code']}_{$session['session_name']}.pdf"
    ]);
}

function generateMasterPDF(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Session ID is required', null, 400);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $session_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all classes
    $classesStmt = $db->prepare("SELECT * FROM classes ORDER BY class_name");
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get timetable data for all classes
    $sql = "
        SELECT 
            tt.class_id,
            tt.day_of_week,
            tt.period_number,
            s.subject_code,
            s.subject_name,
            t.teacher_code,
            t.full_name AS teacher_name,
            tt.entry_type,
            tt.event_name,
            tt.event_color
        FROM timetable tt
        LEFT JOIN subjects_new s ON tt.subject_id = s.id
        LEFT JOIN teachers t ON tt.teacher_id = t.id
        WHERE tt.academic_year = :year
            AND tt.term = :term
            AND tt.entry_type = 'lesson'
        ORDER BY tt.class_id, FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), tt.period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $session['academic_year'],
        ':term' => $session['term']
    ]);
    $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML for PDF
    $html = generateMasterTimetableHTML($classes, $session, $timetable);
    
    hr_respond(true, 'Master timetable PDF data generated', [
        'html' => $html,
        'filename' => "master_timetable_{$session['session_name']}.pdf"
    ]);
}

function generateTimetableHTML($entity, $session, $timetable, $type): string
{
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = range(1, 8);
    
    // Create timetable grid
    $grid = [];
    foreach ($days as $day) {
        $grid[$day] = array_fill(1, 8, null);
    }
    
    // Fill grid with data
    foreach ($timetable as $entry) {
        $day = $entry['day_of_week'];
        $period = (int)$entry['period_number'];
        
        if (isset($grid[$day][$period])) {
            if ($entry['entry_type'] === 'event') {
                $grid[$day][$period] = [
                    'type' => 'event',
                    'name' => $entry['event_name'],
                    'color' => $entry['event_color']
                ];
            } else {
                $grid[$day][$period] = [
                    'type' => 'lesson',
                    'subject_code' => $entry['subject_code'],
                    'subject_name' => $entry['subject_name'],
                    'teacher_code' => $entry['teacher_code'],
                    'room_code' => $entry['room_code']
                ];
            }
        }
    }
    
    // Generate entity name
    $entityName = '';
    if ($type === 'class') {
        $entityName = $entity['class_name'] . ($entity['stream_name'] ? " - {$entity['stream_name']}" : '');
    } elseif ($type === 'teacher') {
        $entityName = $entity['full_name'] . " ({$entity['teacher_code']})";
    } elseif ($type === 'room') {
        $entityName = $entity['room_name'] . " ({$entity['room_code']})";
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Timetable</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .header p { margin: 5px 0; color: #666; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #333; padding: 8px; text-align: center; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .lesson { background-color: #e3f2fd; }
            .event { background-color: #fff3cd; }
            .empty { background-color: #f9f9f9; }
            .legend { margin-top: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($entityName); ?></h1>
            <p><?php echo htmlspecialchars($session['session_name']); ?></p>
            <p><?php echo date('F j, Y', strtotime($session['start_date'])); ?> - <?php echo date('F j, Y', strtotime($session['end_date'])); ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <?php foreach ($days as $day): ?>
                        <th><?php echo $day; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $period): ?>
                    <tr>
                        <td>P<?php echo $period; ?></td>
                        <?php foreach ($days as $day): ?>
                            <td class="<?php echo getCellClass($grid[$day][$period]); ?>">
                                <?php echo getCellContent($grid[$day][$period]); ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="legend">
            <strong>Legend:</strong><br>
            <span style="background-color: #e3f2fd; padding: 2px 5px;">Lesson: SUBJECT_CODE (TEACHER_INITIALS)</span><br>
            <span style="background-color: #fff3cd; padding: 2px 5px;">Event: EVENT_NAME</span>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function generateMasterTimetableHTML($classes, $session, $timetable): string
{
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = range(1, 8);
    
    // Organize timetable by class
    $classTimetables = [];
    foreach ($classes as $class) {
        $classTimetables[$class['id']] = [
            'class' => $class,
            'grid' => []
        ];
        
        foreach ($days as $day) {
            $classTimetables[$class['id']]['grid'][$day] = array_fill(1, 8, null);
        }
    }
    
    // Fill grids
    foreach ($timetable as $entry) {
        $class_id = $entry['class_id'];
        $day = $entry['day_of_week'];
        $period = (int)$entry['period_number'];
        
        if (isset($classTimetables[$class_id]['grid'][$day][$period])) {
            $classTimetables[$class_id]['grid'][$day][$period] = [
                'subject_code' => $entry['subject_code'],
                'teacher_code' => $entry['teacher_code']
            ];
        }
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Master Timetable</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .header p { margin: 5px 0; color: #666; }
            .class-section { margin-bottom: 30px; page-break-after: always; }
            .class-section:last-child { page-break-after: auto; }
            .class-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #333; padding: 6px; text-align: center; font-size: 11px; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .lesson { background-color: #e3f2fd; }
            .empty { background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Master Timetable</h1>
            <p><?php echo htmlspecialchars($session['session_name']); ?></p>
            <p><?php echo date('F j, Y', strtotime($session['start_date'])); ?> - <?php echo date('F j, Y', strtotime($session['end_date'])); ?></p>
        </div>
        
        <?php foreach ($classTimetables as $classData): ?>
            <div class="class-section">
                <div class="class-title">
                    <?php echo htmlspecialchars($classData['class']['class_name']); ?>
                    <?php if ($classData['class']['stream_name']): ?>
                        (<?php echo htmlspecialchars($classData['class']['stream_name']); ?>)
                    <?php endif; ?>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <?php foreach ($days as $day): ?>
                                <th><?php echo substr($day, 0, 3); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                            <tr>
                                <td>P<?php echo $period; ?></td>
                                <?php foreach ($days as $day): ?>
                                    <td class="<?php echo getCellClass($classData['grid'][$day][$period]); ?>">
                                        <?php echo getCellContent($classData['grid'][$day][$period]); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function getCellClass($cell): string
{
    if ($cell === null) return 'empty';
    if ($cell['type'] === 'event') return 'event';
    return 'lesson';
}

function getCellContent($cell): string
{
    if ($cell === null) return '';
    
    if ($cell['type'] === 'event') {
        return htmlspecialchars($cell['name']);
    }
    
    if (isset($cell['subject_code']) && isset($cell['teacher_code'])) {
        return htmlspecialchars($cell['subject_code']) . ' (' . htmlspecialchars($cell['teacher_code']) . ')';
    }
    
    return '';
}
