<?php
header('Content-Type: application/json');
session_start();

// Connect to database
$host = 'localhost';
$dbname = 'novatech_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['matric_number'])) {
    echo json_encode(['success' => false, 'message' => 'No matric number provided']);
    exit;
}

$matricNumber = trim($data['matric_number']);

// Validate format: 13 digits only
if (!preg_match('/^\d{13}$/', $matricNumber)) {
    echo json_encode([
        'success' => false,
        'exists' => false,
        'message' => 'Invalid matric exam number format. Must be exactly 13 digits with no letters or special characters.'
    ]);
    exit;
}

// Check if this is an upgrade scenario
$isUpgrade = isset($_SESSION['user_id']);

if ($isUpgrade) {
    // For upgrades, check if the matric number belongs to the current user
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT id, matric_exam_number FROM students WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // If the matric number matches the current user's matric number, it's valid (same user upgrading)
        if ($user['matric_exam_number'] === $matricNumber) {
            echo json_encode([
                'success' => true,
                'exists' => false,
                'message' => 'Matric number is valid'
            ]);
            exit;
        }
    }
}

// For new enrollments OR if the matric number doesn't match the current user during upgrade
// Check if matric number already exists for another user
$stmt = $pdo->prepare("SELECT id FROM students WHERE matric_exam_number = :matric_number");
$stmt->execute([':matric_number' => $matricNumber]);
$exists = $stmt->fetch() ? true : false;

if ($exists) {
    echo json_encode([
        'success' => false,
        'exists' => true,
        'message' => 'This matric exam number is already registered. Please use a different number.'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'Matric number is valid and available'
    ]);
}
?>