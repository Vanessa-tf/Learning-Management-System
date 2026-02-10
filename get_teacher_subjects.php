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
if (!is_numeric($teacher_id)) {
    echo json_encode([]);
    exit;
}

// Get teacher's subjects from JSON field
$stmt = $conn->prepare("SELECT tutor_subjects FROM users WHERE id = ? AND role = 'teacher'");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([]);
    exit;
}

$tutor_subjects = json_decode($row['tutor_subjects'], true) ?: [];

// Fetch all courses to map names to IDs
$subjects = [];
$subj_result = $conn->query("SELECT id, course_name FROM courses");
if ($subj_result) {
    while ($row = $subj_result->fetch_assoc()) {
        $subjects[$row['course_name']] = $row['id'];
    }
}

// Filter subjects that the teacher teaches
$teacher_subjects = [];
foreach ($tutor_subjects as $subj_name) {
    if (isset($subjects[$subj_name])) {
        $teacher_subjects[] = [
            'id' => $subjects[$subj_name],
            'name' => $subj_name
        ];
    }
}

echo json_encode($teacher_subjects);
$conn->close();
?>