<?php
// includes/config.php

// Database configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'novatech_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_URL', 'http://localhost/novatech/');
define('SITE_NAME', 'NovaTech FET College');

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Upload directory paths
define('UPLOADS_BASE_DIR', __DIR__ . '/../uploads/');
define('STUDY_MATERIALS_DIR', UPLOADS_BASE_DIR . 'study_materials/');
define('MOCK_EXAMS_DIR', UPLOADS_BASE_DIR . 'mock_exams/');
define('EXAM_PAPERS_DIR', UPLOADS_BASE_DIR . 'exam_papers/');
define('PROOF_DOCUMENTS_DIR', UPLOADS_BASE_DIR . 'proof_documents/');
define('SPONSOR_LETTERS_DIR', UPLOADS_BASE_DIR . 'sponsor_letters/');
define('CASE_ATTACHMENTS_DIR', UPLOADS_BASE_DIR . 'case_attachments/');
define('PROFILE_PICTURES_DIR', UPLOADS_BASE_DIR . 'profile_pictures/');

// Allowed file types
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv']);

// Package pricing (in ZAR)
define('PACKAGE_PRICES', [
    'Basic' => 0,      // Free package - 1 subject
    'Standard' => 699, // 2 subjects
    'Premium' => 1199  // 4 subjects
]);

// Package subject limits
define('PACKAGE_SUBJECT_LIMITS', [
    'Basic' => 1,
    'Standard' => 2,
    'Premium' => 4
]);

// User roles
define('ROLE_STUDENT', 'student');
define('ROLE_PARENT', 'parent');
define('ROLE_TEACHER', 'teacher');
define('ROLE_CONTENT_DEVELOPER', 'content');
define('ROLE_ADMIN', 'admin');

// Payment statuses
define('PAYMENT_PENDING', 'Pending');
define('PAYMENT_COMPLETED', 'Completed');
define('PAYMENT_FAILED', 'Failed');

// Subscription statuses
define('SUBSCRIPTION_ACTIVE', 'active');
define('SUBSCRIPTION_CANCELLED', 'cancelled');
define('SUBSCRIPTION_EXPIRED', 'expired');
define('SUBSCRIPTION_PENDING', 'pending');

// Support case priorities and statuses
define('PRIORITY_LOW', 'Low');
define('PRIORITY_MEDIUM', 'Medium');
define('PRIORITY_HIGH', 'High');
define('PRIORITY_URGENT', 'Urgent');

define('CASE_OPEN', 'Open');
define('CASE_IN_PROGRESS', 'In Progress');
define('CASE_RESOLVED', 'Resolved');
define('CASE_CLOSED', 'Closed');

// Mock exam marking types
define('MARKING_AUTO', 'auto');
define('MARKING_TEACHER', 'teacher');

// Create upload directories if they don't exist
$uploadDirs = [
    UPLOADS_BASE_DIR,
    STUDY_MATERIALS_DIR,
    MOCK_EXAMS_DIR,
    EXAM_PAPERS_DIR,
    PROOF_DOCUMENTS_DIR,
    SPONSOR_LETTERS_DIR,
    CASE_ATTACHMENTS_DIR,
    PROFILE_PICTURES_DIR
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Database connection using Database class
try {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
        $database = new Database();
        $conn = $database->getConnection();
    } else {
        // Fallback: Create direct PDO connection
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please contact the administrator.");
}

// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Utility Functions

/**
 * Generate unique filename for uploads
 */
function generateUniqueFileName($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = $allowedTypes ?? ALLOWED_FILE_TYPES;
    
    if (!in_array($extension, $allowed)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    return ['success' => true, 'extension' => $extension];
}

/**
 * Send notification to user
 */
function sendNotification($userId, $type, $message) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, is_read, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$userId, $type, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database connection
 */
function getDatabaseConnection() {
    global $conn;
    if (!$conn) {
        throw new Exception("Database connection not available");
    }
    return $conn;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . 'login.php');
        exit();
    }
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . SITE_URL . 'unauthorized.php');
        exit();
    }
}

/**
 * Get user's package
 */
function getUserPackage($userId) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT package_selected FROM students WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['package_selected'] : 'Basic';
    } catch (PDOException $e) {
        error_log("Error getting user package: " . $e->getMessage());
        return 'Basic';
    }
}

/**
 * Format currency (ZAR)
 */
function formatCurrency($amount) {
    return 'R ' . number_format($amount, 2);
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate transaction ID
 */
function generateTransactionId($prefix = 'TXN') {
    return $prefix . '-' . time() . '-' . rand(1000, 9999);
}

/**
 * Calculate subscription end date
 */
function calculateSubscriptionEndDate($startDate, $months = 1) {
    $date = new DateTime($startDate);
    $date->modify("+{$months} months");
    return $date->format('Y-m-d');
}

/**
 * Check if subscription is active
 */
function isSubscriptionActive($userId) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT subscription_status, subscription_end_date 
            FROM students 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result) return false;
        
        if ($result['subscription_status'] !== 'active') return false;
        
        if ($result['subscription_end_date']) {
            $endDate = new DateTime($result['subscription_end_date']);
            $now = new DateTime();
            return $endDate >= $now;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error checking subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Get enrolled courses for student
 */
function getStudentCourses($userId) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT e.*, c.course_name 
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.user_id = ?
            ORDER BY c.course_name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting student courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Log activity
 */
function logActivity($userId, $action, $details = '') {
    error_log("User {$userId}: {$action} - {$details}");
}
/**
 * Notify all content developers
 */
function notifyAllContentDevelopers($conn, $type, $message, $content_type = 'general', $content_id = null) {
    try {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_type = 'content_developer'");
        $stmt->execute();
        $developers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($developers as $dev) {
            sendNotification($dev['user_id'], $type, $message);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins
 */
function notifyAllAdmins($conn, $type, $message, $content_type = 'general', $content_id = null) {
    try {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_type = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            sendNotification($admin['user_id'], $type, $message);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 */
function getNotifications($conn, $user_id, $limit = 50, $filter = 'all') {
    try {
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        
        if ($filter === 'unread') {
            $query .= " AND is_read = 0";
        } elseif ($filter === 'read') {
            $query .= " AND is_read = 1";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($conn, $user_id) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user details by ID
 */
function getUserDetails($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name, email, user_type FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get User Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Notify content developer when admin edits their material
 */
function notifyContentDeveloperEdit($conn, $uploader_id, $admin_id, $content_type, $content_title, $content_id = null) {
    $admin = getUserDetails($conn, $admin_id);
    $admin_name = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Admin';
    
    $message = "Admin {$admin_name} edited your {$content_type}: '{$content_title}'";
    return sendNotification($uploader_id, 'content_edit', $message);
}

/**
 * Notify content developer when admin deletes their material
 */
function notifyContentDeveloperDelete($conn, $uploader_id, $admin_id, $content_type, $content_title) {
    $admin = getUserDetails($conn, $admin_id);
    $admin_name = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Admin';
    
    $message = "Admin {$admin_name} deleted your {$content_type}: '{$content_title}'";
    return sendNotification($uploader_id, 'content_delete', $message);
}

/**
 * Notify admins when content developer uploads material
 */
function notifyAdminsUpload($conn, $uploader_id, $content_type, $content_title, $content_id = null) {
    $uploader = getUserDetails($conn, $uploader_id);
    $uploader_name = $uploader ? $uploader['first_name'] . ' ' . $uploader['last_name'] : 'Content Developer';
    
    $message = "{$uploader_name} uploaded new {$content_type}: '{$content_title}'";
    return notifyAllAdmins($conn, 'content_upload', $message);
}