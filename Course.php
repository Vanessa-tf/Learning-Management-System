<?php
require_once 'includes/db.php';
require_once 'includes/config.php';

class Course {
    private $conn;
    private $table = 'courses';
    private $content_table = 'course_content';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Get all courses from courses table
    public function getAll($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND c.course_name LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $query = "SELECT c.id, c.course_name,
                         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
                         (SELECT COUNT(*) FROM course_content WHERE course_id = c.id) as content_count
                  FROM " . $this->table . " c 
                  " . $whereClause . " 
                  ORDER BY c.course_name ASC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get course by ID
    public function getById($id) {
        $query = "SELECT c.*,
                         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
                         (SELECT COUNT(*) FROM course_content WHERE course_id = c.id) as content_count
                  FROM " . $this->table . " c 
                  WHERE c.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get course content (from course_content table)
    public function getCourseContent($courseId) {
        $query = "SELECT * FROM " . $this->content_table . " 
                  WHERE course_id = :course_id 
                  ORDER BY order_index ASC, id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get single content item by ID with course information
    public function getContentById($contentId) {
        $query = "SELECT cc.*, c.course_name, c.id as course_id
                  FROM " . $this->content_table . " cc 
                  JOIN " . $this->table . " c ON cc.course_id = c.id
                  WHERE cc.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $contentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get content by ID and type (with validation)
    public function getContentByIdAndType($contentId, $contentType) {
        $query = "SELECT cc.*, c.course_name, c.id as course_id
                  FROM " . $this->content_table . " cc 
                  JOIN " . $this->table . " c ON cc.course_id = c.id
                  WHERE cc.id = :id AND cc.content_type = :type";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':id' => $contentId,
            ':type' => $contentType
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Create new course content item - FIXED VERSION WITH AUTO-INCREMENT CHECK
    public function createContent($data) {
        try {
            // Validate required fields
            if (empty($data['course_id'])) {
                return ['success' => false, 'error' => 'Course ID is required'];
            }
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Title is required'];
            }
            if (empty($data['content_type'])) {
                return ['success' => false, 'error' => 'Content type is required'];
            }
            
            // Verify course exists
            $checkStmt = $this->conn->prepare("SELECT id FROM courses WHERE id = :id");
            $checkStmt->execute([':id' => $data['course_id']]);
            if (!$checkStmt->fetch()) {
                return ['success' => false, 'error' => 'Invalid course ID'];
            }
            
            // Set defaults for optional fields
            $description = $data['description'] ?? '';
            $url = $data['url'] ?? '';
            $order_index = $data['order_index'] ?? 1;
            $quiz_content = $data['quiz_content'] ?? '';
            $quiz_settings = $data['quiz_settings'] ?? null;
            $open_date = $data['open_date'] ?? null;
            $close_date = $data['close_date'] ?? null;
            $passing_percentage = $data['passing_percentage'] ?? 50.00;
            
            // IMPORTANT: Do NOT include 'id' in the INSERT query - let AUTO_INCREMENT handle it
            $query = "INSERT INTO " . $this->content_table . " 
                      (course_id, content_type, title, description, url, order_index, 
                       quiz_content, quiz_settings, passing_percentage, open_date, close_date) 
                      VALUES 
                      (:course_id, :content_type, :title, :description, :url, :order_index, 
                       :quiz_content, :quiz_settings, :passing_percentage, :open_date, :close_date)";
            
            $stmt = $this->conn->prepare($query);
            
            $params = [
                ':course_id' => (int)$data['course_id'],
                ':content_type' => $data['content_type'],
                ':title' => $data['title'],
                ':description' => $description,
                ':url' => $url,
                ':order_index' => (int)$order_index,
                ':quiz_content' => $quiz_content,
                ':quiz_settings' => $quiz_settings,
                ':passing_percentage' => (float)$passing_percentage,
                ':open_date' => $open_date,
                ':close_date' => $close_date
            ];
            
            // Debug log
            error_log("Executing insert with params: " . json_encode($params));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $contentId = $this->conn->lastInsertId();
                
                // Check if we got a valid ID
                if ($contentId == 0 || empty($contentId)) {
                    error_log("WARNING: lastInsertId returned 0 or empty. Checking database...");
                    
                    // Try to get the last inserted record by matching the data
                    $fallbackQuery = "SELECT id FROM course_content 
                                     WHERE course_id = :course_id 
                                     AND title = :title 
                                     AND content_type = :content_type 
                                     ORDER BY id DESC LIMIT 1";
                    $fallbackStmt = $this->conn->prepare($fallbackQuery);
                    $fallbackStmt->execute([
                        ':course_id' => $data['course_id'],
                        ':title' => $data['title'],
                        ':content_type' => $data['content_type']
                    ]);
                    $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fallbackResult && $fallbackResult['id'] > 0) {
                        $contentId = $fallbackResult['id'];
                        error_log("Found content ID via fallback method: " . $contentId);
                    } else {
                        error_log("CRITICAL: Cannot determine inserted content ID");
                        return ['success' => false, 'error' => 'Content may have been inserted but ID cannot be determined'];
                    }
                }
                
                error_log("Successfully inserted content with ID: " . $contentId);
                
                // Verify insertion
                $verifyStmt = $this->conn->prepare("SELECT * FROM course_content WHERE id = :id");
                $verifyStmt->execute([':id' => $contentId]);
                $inserted = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($inserted) {
                    error_log("Verified insertion: " . json_encode($inserted));
                    return ['success' => true, 'content_id' => $contentId];
                } else {
                    error_log("Insert succeeded but verification failed for ID: " . $contentId);
                    return ['success' => false, 'error' => 'Content inserted but verification failed'];
                }
            }
            
            $errorInfo = $stmt->errorInfo();
            error_log("Insert failed: " . implode(", ", $errorInfo));
            return ['success' => false, 'error' => 'Failed to create content: ' . $errorInfo[2]];
            
        } catch (PDOException $e) {
            error_log("PDO Exception in createContent: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Exception in createContent: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Update course content - FIXED VERSION
    public function updateContent($id, $data) {
        try {
            // Validate content exists
            $checkStmt = $this->conn->prepare("SELECT id FROM course_content WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            if (!$checkStmt->fetch()) {
                return ['success' => false, 'error' => 'Content not found'];
            }
            
            // Build update query dynamically based on provided data
            $updates = [];
            $params = [':id' => $id];
            
            if (isset($data['title'])) {
                $updates[] = "title = :title";
                $params[':title'] = $data['title'];
            }
            if (isset($data['description'])) {
                $updates[] = "description = :description";
                $params[':description'] = $data['description'];
            }
            if (isset($data['url'])) {
                $updates[] = "url = :url";
                $params[':url'] = $data['url'];
            }
            if (isset($data['order_index'])) {
                $updates[] = "order_index = :order_index";
                $params[':order_index'] = (int)$data['order_index'];
            }
            if (isset($data['quiz_content'])) {
                $updates[] = "quiz_content = :quiz_content";
                $params[':quiz_content'] = $data['quiz_content'];
            }
            if (isset($data['quiz_settings'])) {
                $updates[] = "quiz_settings = :quiz_settings";
                $params[':quiz_settings'] = $data['quiz_settings'];
            }
            if (isset($data['open_date'])) {
                $updates[] = "open_date = :open_date";
                $params[':open_date'] = $data['open_date'];
            }
            if (isset($data['close_date'])) {
                $updates[] = "close_date = :close_date";
                $params[':close_date'] = $data['close_date'];
            }
            if (isset($data['passing_percentage'])) {
                $updates[] = "passing_percentage = :passing_percentage";
                $params[':passing_percentage'] = (float)$data['passing_percentage'];
            }
            
            if (empty($updates)) {
                return ['success' => false, 'error' => 'No data to update'];
            }
            
            $query = "UPDATE " . $this->content_table . " 
                      SET " . implode(', ', $updates) . "
                      WHERE id = :id";
            
            error_log("Executing update with query: " . $query);
            error_log("Update params: " . json_encode($params));
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                error_log("Successfully updated content ID: " . $id);
                return ['success' => true];
            }
            
            error_log("Update failed: " . implode(", ", $stmt->errorInfo()));
            return ['success' => false, 'error' => 'Failed to update content: ' . implode(", ", $stmt->errorInfo())];
            
        } catch (PDOException $e) {
            error_log("PDO Exception in updateContent: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Exception in updateContent: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Delete course content - FIXED VERSION
    public function deleteContent($id) {
        try {
            // Verify content exists
            $checkStmt = $this->conn->prepare("SELECT id, title FROM course_content WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            $content = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$content) {
                return ['success' => false, 'error' => 'Content not found'];
            }
            
            error_log("Attempting to delete content ID: " . $id . " (" . $content['title'] . ")");
            
            $query = "DELETE FROM " . $this->content_table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([':id' => $id]);
            
            if ($result) {
                error_log("Successfully deleted content ID: " . $id);
                return ['success' => true];
            }
            
            error_log("Delete failed: " . implode(", ", $stmt->errorInfo()));
            return ['success' => false, 'error' => 'Failed to delete content: ' . implode(", ", $stmt->errorInfo())];
            
        } catch (PDOException $e) {
            error_log("PDO Exception in deleteContent: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Exception in deleteContent: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Create new course (in courses table)
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " (course_name) VALUES (:course_name)";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([':course_name' => $data['course_name']]);
            
            if ($result) {
                $courseId = $this->conn->lastInsertId();
                return ['success' => true, 'course_id' => $courseId];
            }
            
            return ['success' => false, 'error' => 'Failed to create course'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Update course
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET course_name = :course_name
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([
                ':course_name' => $data['course_name'],
                ':id' => $id
            ]);
            
            if ($result) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to update course'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Delete course
    public function delete($id) {
        try {
            // Check for dependencies
            $checkQuery = "SELECT 
                            (SELECT COUNT(*) FROM enrollments WHERE course_id = :id) as enrollment_count,
                            (SELECT COUNT(*) FROM study_materials WHERE course_id = :id) as material_count,
                            (SELECT COUNT(*) FROM mock_exams WHERE course_id = :id) as exam_count,
                            (SELECT COUNT(*) FROM live_lessons WHERE course_id = :id) as lesson_count,
                            (SELECT COUNT(*) FROM course_content WHERE course_id = :id) as content_count";
            
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([':id' => $id]);
            $counts = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            $totalContent = ($counts['enrollment_count'] ?? 0) + 
                           ($counts['material_count'] ?? 0) + 
                           ($counts['exam_count'] ?? 0) + 
                           ($counts['lesson_count'] ?? 0) +
                           ($counts['content_count'] ?? 0);
            
            if ($totalContent > 0) {
                return [
                    'success' => false, 
                    'error' => "Cannot delete course: {$totalContent} associated items found."
                ];
            }
            
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([':id' => $id]);
            
            if ($result) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to delete course'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Get courses with statistics
    public function getCoursesWithStats() {
        $query = "SELECT c.id, c.course_name,
                         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
                         (SELECT COUNT(*) FROM study_materials WHERE course_id = c.id) as material_count,
                         (SELECT COUNT(*) FROM mock_exams WHERE course_id = c.id) as exam_count,
                         (SELECT COUNT(*) FROM live_lessons WHERE course_id = c.id) as lesson_count,
                         (SELECT COUNT(*) FROM course_content WHERE course_id = c.id) as content_count
                  FROM " . $this->table . " c 
                  ORDER BY c.course_name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get popular courses
    public function getPopularCourses($limit = 5) {
        $query = "SELECT c.id, c.course_name,
                         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
                  FROM " . $this->table . " c 
                  ORDER BY enrollment_count DESC, c.course_name ASC
                  LIMIT " . (int)$limit;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ===== QUIZ-SPECIFIC METHODS (UPDATED FOR LOCKDOWN SYSTEM) =====
    
    // Get quiz by ID with full details
    public function getQuizById($quiz_id) {
        try {
            $query = "SELECT cc.*, c.course_name 
                      FROM " . $this->content_table . " cc
                      LEFT JOIN " . $this->table . " c ON cc.course_id = c.id
                      WHERE cc.id = :id AND cc.content_type = 'quiz'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':id' => $quiz_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching quiz: " . $e->getMessage());
            return null;
        }
    }
    
    // Get all quizzes with attempt statistics (UPDATED FOR LOCKDOWN SYSTEM)
    public function getAllQuizzes($filters = []) {
        try {
            $whereClause = "WHERE cc.content_type = 'quiz'";
            $params = [];
            
            if (!empty($filters['course_id'])) {
                $whereClause .= " AND cc.course_id = :course_id";
                $params[':course_id'] = $filters['course_id'];
            }
            
            $query = "SELECT cc.*, c.course_name,
                             COUNT(DISTINCT lqa.id) as attempt_count,
                             COUNT(DISTINCT lqa.user_id) as unique_students,
                             AVG(lqa.percentage) as avg_score,
                             SUM(CASE WHEN lqa.violations >= 3 THEN 1 ELSE 0 END) as high_violation_count,
                             MAX(lqa.submitted_at) as last_attempt_date
                      FROM " . $this->content_table . " cc
                      LEFT JOIN " . $this->table . " c ON cc.course_id = c.id
                      LEFT JOIN lockdown_quiz_attempts lqa ON cc.id = lqa.content_id
                      " . $whereClause . "
                      GROUP BY cc.id, cc.course_id, cc.title, cc.description, c.course_name
                      ORDER BY cc.title ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching quizzes: " . $e->getMessage());
            return [];
        }
    }
    
    // Get quiz attempts for a specific quiz (UPDATED FOR LOCKDOWN SYSTEM)
    public function getQuizAttempts($quiz_id, $filters = []) {
        try {
            $whereClause = "WHERE lqa.content_id = :quiz_id";
            $params = [':quiz_id' => $quiz_id];
            
            if (!empty($filters['student_id'])) {
                $whereClause .= " AND lqa.user_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }
            
            $query = "SELECT lqa.*, 
                             s.first_name, s.surname, s.email,
                             cc.title as quiz_title,
                             c.course_name
                      FROM lockdown_quiz_attempts lqa
                      JOIN students s ON lqa.user_id = s.id
                      JOIN course_content cc ON lqa.content_id = cc.id
                      JOIN courses c ON lqa.course_id = c.id
                      " . $whereClause . "
                      ORDER BY lqa.submitted_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching quiz attempts: " . $e->getMessage());
            return [];
        }
    }
    
    // Get violations for a specific attempt
    public function getQuizViolations($attempt_id) {
        try {
            $query = "SELECT * FROM quiz_violations 
                      WHERE attempt_id = :attempt_id 
                      ORDER BY logged_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':attempt_id' => $attempt_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching violations: " . $e->getMessage());
            return [];
        }
    }
    
    // Calculate total marks from quiz content
    public function calculateQuizMarks($quiz_content_json) {
        try {
            $questions = json_decode($quiz_content_json, true);
            if (!is_array($questions)) return 0;
            
            $total_marks = 0;
            foreach ($questions as $question) {
                $total_marks += ($question['marks'] ?? 1);
            }
            return $total_marks;
            
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>