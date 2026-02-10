<?php
// includes/functions.php

// Function to check if user is logged in
function check_session() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Function to safely escape output to prevent XSS
function safe_output($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to check if user has an active subscription (placeholder)
function check_subscription($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND expiry_date > NOW()");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

/**
 * Send notification to parent when case status changes
 */
function notify_parent_case_status_change($pdo, $case_id, $new_status, $parent_id) {
    try {
        // Get case details
        $stmt = $pdo->prepare("
            SELECT sc.case_number, sc.subject, s.first_name, s.surname 
            FROM support_cases sc 
            JOIN students s ON sc.student_id = s.id 
            JOIN financiers f ON s.id = f.student_id 
            WHERE sc.id = ? AND f.id = ?
        ");
        $stmt->execute([$case_id, $parent_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($case) {
            $student_name = $case['first_name'] . ' ' . $case['surname'];
            $message = "Case #{$case['case_number']} for {$student_name} has been updated to: {$new_status}. Subject: {$case['subject']}";
            
            // Insert notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, created_at) 
                VALUES (?, 'case_update', ?, NOW())
            ");
            $stmt->execute([$parent_id, $message]);
            
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error sending case status notification: " . $e->getMessage());
    }
    return false;
}

/**
 * Send notification to parent when new response is added to their case
 */
function notify_parent_case_response($pdo, $case_id, $parent_id, $responder_role) {
    try {
        // Only notify if the response is not from the parent themselves
        if ($responder_role === 'parent') {
            return false;
        }
        
        // Get case details
        $stmt = $pdo->prepare("
            SELECT sc.case_number, sc.subject, s.first_name, s.surname 
            FROM support_cases sc 
            JOIN students s ON sc.student_id = s.id 
            JOIN financiers f ON s.id = f.student_id 
            WHERE sc.id = ? AND f.id = ?
        ");
        $stmt->execute([$case_id, $parent_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($case) {
            $student_name = $case['first_name'] . ' ' . $case['surname'];
            $message = "New response received for Case #{$case['case_number']} for {$student_name}. Subject: {$case['subject']}";
            
            // Insert notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, created_at) 
                VALUES (?, 'case_update', ?, NOW())
            ");
            $stmt->execute([$parent_id, $message]);
            
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error sending case response notification: " . $e->getMessage());
    }
    return false;
}

/**
 * Get parent ID from student ID
 */
function get_parent_id_from_student($pdo, $student_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.id 
            FROM financiers f 
            WHERE f.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting parent ID from student: " . $e->getMessage());
        return null;
    }
}

/**
 * Send notification when new case is created by parent
 */
function notify_admin_new_parent_case($pdo, $case_id, $parent_name) {
    try {
        // Get case details
        $stmt = $pdo->prepare("
            SELECT sc.case_number, sc.subject, sc.description, sc.priority, sc.category,
                   s.first_name, s.surname
            FROM support_cases sc 
            JOIN students s ON sc.student_id = s.id 
            WHERE sc.id = ?
        ");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($case) {
            $student_name = $case['first_name'] . ' ' . $case['surname'];
            $message = "New support case #{$case['case_number']} from parent {$parent_name} for student {$student_name}. Priority: {$case['priority']}, Category: {$case['category']}";
            
            // Get all admin users
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send notification to each admin
            foreach ($admins as $admin) {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, created_at) 
                    VALUES (?, 'case_update', ?, NOW())
                ");
                $stmt->execute([$admin['id'], $message]);
            }
            
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error sending new case notification to admin: " . $e->getMessage());
    }
    return false;
}
?>