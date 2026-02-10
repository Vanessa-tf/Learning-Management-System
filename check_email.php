<?php
header('Content-Type: application/json');

// Connect to database
$host = 'localhost';
$dbname = 'novatech_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    // Check if email exists in database
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode(['success' => true, 'exists' => $exists]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>