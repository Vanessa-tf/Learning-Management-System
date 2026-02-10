<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include(__DIR__ . "/includes/db.php");

// Get student info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, surname, email FROM students WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'Other';
    $priority = $_POST['priority'] ?? 'Medium';
    
    if (!empty($subject) && !empty($description)) {
        // Generate case number
        $case_number = 'CASE-' . date('Ymd') . '-' . rand(1000, 9999);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO support_cases (student_id, case_number, subject, description, category, priority, status, logged_by_role) 
                VALUES (:student_id, :case_number, :subject, :description, :category, :priority, 'Open', 'student')
            ");
            
            $stmt->execute([
                ':student_id' => $user_id,
                ':case_number' => $case_number,
                ':subject' => $subject,
                ':description' => $description,
                ':category' => $category,
                ':priority' => $priority
            ]);
            
            $success = "Case logged successfully! Case Number: " . $case_number;
        } catch (Exception $e) {
            $error = "Error logging case: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get student's cases
$stmt = $pdo->prepare("
    SELECT * FROM support_cases 
    WHERE student_id = :student_id 
    AND logged_by_role = 'student'
    ORDER BY created_at DESC
");
$stmt->execute([':student_id' => $user_id]);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Support Case - NovaTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --primary-yellow: #facc15;
            --light-beige: #f5f1e3;
            --white: #ffffff;
            --text-dark: #333333;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-beige);
        }
        
        .case-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin: 20px 0;
            padding: 30px;
        }
        
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .priority-urgent { border-left: 4px solid #6f42c1; }
        
        .status-open { color: #dc3545; font-weight: bold; }
        .status-in-progress { color: #ffc107; font-weight: bold; }
        .status-resolved { color: #28a745; font-weight: bold; }
        .status-closed { color: #6c757d; font-weight: bold; }
        
        .btn-back {
            background-color: var(--primary-blue);
            color: white;
            border: none;
        }
        .btn-back:hover {
            background-color: #152a6b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Back to Dashboard Button -->
        <div class="mb-4">
            <a href="student-dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="text-center mb-4">
                    <h1 style="color: var(--primary-blue);">📋 Log Support Case</h1>
                    <p>Need help? Log a case and our support team will assist you.</p>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="case-container">
                    <h4>New Support Case</h4>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category" required>
                                    <option value="Technical">Technical Support</option>
                                    <option value="Academic">Academic Assistance</option>
                                    <option value="Billing">Billing Issue</option>
                                    <option value="Account">Account Problem</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority *</label>
                                <select class="form-control" name="priority" required>
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" placeholder="Brief description of your issue" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="6" 
                                      placeholder="Please provide detailed information about your issue..." required></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" style="background-color: var(--primary-yellow); color: var(--primary-blue); border: none;">
                                Submit Case
                            </button>
                            <a href="student-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <!-- Existing Cases -->
                <div class="case-container mt-4">
                    <h4>Your Support Cases</h4>
                    <?php if (empty($cases)): ?>
                        <p class="text-muted">No cases logged yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Case #</th>
                                        <th>Subject</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr class="priority-<?= strtolower($case['priority']) ?>">
                                            <td><strong><?= $case['case_number'] ?></strong></td>
                                            <td><?= htmlspecialchars($case['subject']) ?></td>
                                            <td><?= $case['category'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $case['priority'] === 'Urgent' ? 'danger' : 
                                                    ($case['priority'] === 'High' ? 'warning' : 
                                                    ($case['priority'] === 'Medium' ? 'info' : 'success')) 
                                                ?>">
                                                    <?= $case['priority'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-<?= strtolower(str_replace(' ', '-', $case['status'])) ?>">
                                                    <?= $case['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($case['created_at'])) ?></td>
                                            <td>
                                                <a href="view_case.php?id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>