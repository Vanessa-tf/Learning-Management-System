<?php
// includes/ContentHelper.php

class ContentHelper {
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Get content statistics for dashboard
     */
    public function getContentStatistics() {
        $stats = [];
        
        // Total materials
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM study_materials");
        $stats['total_materials'] = $stmt->fetch()['total'];
        
        // Materials by status
        $stmt = $this->conn->query("
            SELECT status, COUNT(*) as count 
            FROM study_materials 
            GROUP BY status
        ");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Materials by category
        $stmt = $this->conn->query("
            SELECT category, COUNT(*) as count 
            FROM study_materials 
            WHERE status = 'published'
            GROUP BY category
        ");
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Materials by course
        $stmt = $this->conn->query("
            SELECT c.course_name, COUNT(sm.id) as count
            FROM courses c
            LEFT JOIN study_materials sm ON c.id = sm.course_id AND sm.status = 'published'
            GROUP BY c.id, c.course_name
            ORDER BY c.course_name
        ");
        $stats['by_course'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pending approvals
        $stmt = $this->conn->query("
            SELECT COUNT(*) as count 
            FROM study_materials 
            WHERE status = 'draft'
        ");
        $stats['pending_approvals'] = $stmt->fetch()['count'];
        
        // Total downloads
        $stmt = $this->conn->query("
            SELECT SUM(download_count) as total 
            FROM study_materials
        ");
        $stats['total_downloads'] = $stmt->fetch()['total'] ?? 0;
        
        // Recent uploads (last 7 days)
        $stmt = $this->conn->query("
            SELECT COUNT(*) as count 
            FROM study_materials 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['recent_uploads'] = $stmt->fetch()['count'];
        
        // Most downloaded materials
        $stmt = $this->conn->query("
            SELECT sm.id, sm.title, sm.download_count, c.course_name
            FROM study_materials sm
            JOIN courses c ON sm.course_id = c.id
            WHERE sm.status = 'published'
            ORDER BY sm.download_count DESC
            LIMIT 5
        ");
        $stats['top_downloads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Get content by filters
     */
    public function getFilteredContent($filters = []) {
        $sql = "SELECT sm.*, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as uploader_name,
                u.role as uploader_role
                FROM study_materials sm
                LEFT JOIN courses c ON sm.course_id = c.id
                LEFT JOIN users u ON sm.uploaded_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND sm.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND sm.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['course_id'])) {
            $sql .= " AND sm.course_id = ?";
            $params[] = $filters['course_id'];
        }
        
        if (!empty($filters['target_audience'])) {
            $sql .= " AND sm.target_audience = ?";
            $params[] = $filters['target_audience'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (sm.title LIKE ? OR sm.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['uploaded_by'])) {
            $sql .= " AND sm.uploaded_by = ?";
            $params[] = $filters['uploaded_by'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND sm.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND sm.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Sorting
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $sql .= " ORDER BY sm.$orderBy $orderDir";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = $filters['offset'];
            }
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get content by ID
     */
    public function getContentById($id) {
        $stmt = $this->conn->prepare("
            SELECT sm.*, c.course_name, 
            CONCAT(u.first_name, ' ', u.last_name) as uploader_name
            FROM study_materials sm
            LEFT JOIN courses c ON sm.course_id = c.id
            LEFT JOIN users u ON sm.uploaded_by = u.id
            WHERE sm.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update content status
     */
    public function updateStatus($materialId, $status, $adminId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE study_materials 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $materialId]);
            
            // Log the action
            $this->logAction($adminId, 'status_update', $materialId, "Status changed to: $status");
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete content
     */
    public function deleteContent($materialId, $adminId) {
        try {
            // Get file path
            $stmt = $this->conn->prepare("SELECT file_path FROM study_materials WHERE id = ?");
            $stmt->execute([$materialId]);
            $material = $stmt->fetch();
            
            if ($material) {
                // Delete file
                $filePath = __DIR__ . '/../' . $material['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                $stmt = $this->conn->prepare("DELETE FROM study_materials WHERE id = ?");
                $stmt->execute([$materialId]);
                
                // Log the action
                $this->logAction($adminId, 'delete', $materialId, "Content deleted");
                
                return ['success' => true, 'message' => 'Content deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Content not found'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting content: ' . $e->getMessage()];
        }
    }
    
    /**
     * Bulk update status
     */
    public function bulkUpdateStatus($materialIds, $status, $adminId) {
        try {
            $placeholders = str_repeat('?,', count($materialIds) - 1) . '?';
            $stmt = $this->conn->prepare("
                UPDATE study_materials 
                SET status = ?, updated_at = NOW() 
                WHERE id IN ($placeholders)
            ");
            
            $params = array_merge([$status], $materialIds);
            $stmt->execute($params);
            
            // Log the action
            $this->logAction($adminId, 'bulk_status_update', implode(',', $materialIds), 
                            "Bulk status update to: $status for " . count($materialIds) . " items");
            
            return ['success' => true, 'message' => count($materialIds) . ' items updated successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error in bulk update: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get content upload activity
     */
    public function getUploadActivity($days = 30) {
        $stmt = $this->conn->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM study_materials
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get uploader statistics
     */
    public function getUploaderStats() {
        $stmt = $this->conn->query("
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as uploader_name,
                u.role,
                COUNT(sm.id) as upload_count,
                SUM(CASE WHEN sm.status = 'published' THEN 1 ELSE 0 END) as published_count,
                SUM(CASE WHEN sm.status = 'draft' THEN 1 ELSE 0 END) as pending_count,
                SUM(sm.download_count) as total_downloads
            FROM users u
            LEFT JOIN study_materials sm ON u.id = sm.uploaded_by
            WHERE u.role IN ('admin', 'teacher', 'content')
            GROUP BY u.id, u.first_name, u.last_name, u.role
            HAVING upload_count > 0
            ORDER BY upload_count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log admin action
     */
    private function logAction($adminId, $action, $materialId, $details = '') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, details, created_at)
                VALUES (?, ?, 'content', ?, ?, NOW())
            ");
            $stmt->execute([$adminId, $action, $materialId, $details]);
        } catch (PDOException $e) {
            error_log("Error logging action: " . $e->getMessage());
        }
    }
    
    /**
     * Get file type statistics
     */
    public function getFileTypeStats() {
        $stmt = $this->conn->query("
            SELECT file_type, COUNT(*) as count, SUM(file_size) as total_size
            FROM study_materials
            WHERE status = 'published'
            GROUP BY file_type
            ORDER BY count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search content with advanced options
     */
    public function advancedSearch($searchTerm, $options = []) {
        $sql = "SELECT sm.*, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as uploader_name
                FROM study_materials sm
                LEFT JOIN courses c ON sm.course_id = c.id
                LEFT JOIN users u ON sm.uploaded_by = u.id
                WHERE (sm.title LIKE ? OR sm.description LIKE ?)";
        
        $params = ["%$searchTerm%", "%$searchTerm%"];
        
        if (!empty($options['status'])) {
            $sql .= " AND sm.status = ?";
            $params[] = $options['status'];
        }
        
        if (!empty($options['min_downloads'])) {
            $sql .= " AND sm.download_count >= ?";
            $params[] = $options['min_downloads'];
        }
        
        $sql .= " ORDER BY sm.created_at DESC LIMIT 50";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Export content list to CSV
     */
    public function exportToCSV($filters = []) {
        $content = $this->getFilteredContent($filters);
        
        $output = fopen('php://temp', 'w');
        
        // Headers
        fputcsv($output, [
            'ID', 'Title', 'Course', 'Category', 'Target Audience', 
            'File Type', 'File Size (KB)', 'Downloads', 'Status', 
            'Uploaded By', 'Created At'
        ]);
        
        // Data
        foreach ($content as $item) {
            fputcsv($output, [
                $item['id'],
                $item['title'],
                $item['course_name'],
                ucwords(str_replace('_', ' ', $item['category'])),
                ucfirst($item['target_audience']),
                strtoupper($item['file_type']),
                number_format($item['file_size'] / 1024, 2),
                $item['download_count'],
                ucfirst($item['status']),
                $item['uploader_name'],
                date('Y-m-d H:i', strtotime($item['created_at']))
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
?>