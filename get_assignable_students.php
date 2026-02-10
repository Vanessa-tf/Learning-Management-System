<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "novatech_db";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$teacher_id = $_GET['teacher_id'] ?? 0;
$course_id = $_GET['course_id'] ?? 0;

// If course_id is provided, get teachers for that course
if ($course_id > 0) {
    // Get the course name first
    $course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_row = $course_result->fetch_assoc()) {
        $course_name = $course_row['course_name'];
        
        // Get teachers who teach this course
        $teacher_stmt = $conn->prepare("
            SELECT u.id, u.first_name, u.last_name 
            FROM users u 
            WHERE u.role = 'teacher' 
            AND u.status = 'active'
            AND JSON_CONTAINS(u.tutor_subjects, ?)
        ");
        $json_course_name = json_encode($course_name);
        $teacher_stmt->bind_param("s", $json_course_name);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        
        $teachers = [];
        while ($row = $teacher_result->fetch_assoc()) {
            $teachers[] = $row;
        }
        echo json_encode($teachers);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Original logic for getting assignable students
if (!is_numeric($teacher_id) || $teacher_id == 0) {
    echo json_encode([]);
    exit;
}

// Get courses taught by teacher from tutor_subjects JSON field
$taught_courses = [];
$stmt = $conn->prepare("SELECT tutor_subjects FROM users WHERE id = ? AND role = 'teacher'");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $tutor_subjects = json_decode($row['tutor_subjects'], true) ?: [];
    foreach ($tutor_subjects as $subject) {
        $stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
        $stmt->bind_param("s", $subject);
        $stmt->execute();
        $course_res = $stmt->get_result();
        if ($course_row = $course_res->fetch_assoc()) {
            $taught_courses[] = $course_row['id'];
        }
    }
}

if (empty($taught_courses)) {
    echo json_encode([]);
    exit;
}

$placeholders = str_repeat('?,', count($taught_courses) - 1) . '?';
$stmt = $conn->prepare("
    SELECT s.id, s.first_name, s.surname as last_name, c.course_name
    FROM students s
    JOIN enrollments e ON s.id = e.user_id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN student_teacher_assignments sta ON s.id = sta.student_id AND e.course_id = sta.course_id AND sta.teacher_id = ?
    WHERE s.enrollment_confirmed = 1
    AND e.course_id IN ($placeholders)
    AND sta.student_id IS NULL
    ORDER BY s.first_name, s.surname
");

$params = array_merge([$teacher_id], $taught_courses);
$types = str_repeat('i', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode($students);
$conn->close();
?>