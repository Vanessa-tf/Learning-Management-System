<?php
require_once(__DIR__ . "/includes/libs/fpdf/fpdf.php");
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

class AnalyticsPDF extends FPDF
{
    private $stats;
    private $chartsData;
    private $system_metrics;
    private $support_stats;

    function __construct($stats, $chartsData, $system_metrics, $support_stats)
    {
        parent::__construct();
        $this->stats = $stats;
        $this->chartsData = $chartsData;
        $this->system_metrics = $system_metrics;
        $this->support_stats = $support_stats;
    }

    // Page header
    function Header()
    {
        // Logo
        if (file_exists('Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png')) {
            $this->Image('Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png', 10, 6, 30);
        }
        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);
        // Move to the right
        $this->Cell(80);
        // Title
        $this->Cell(30, 10, 'NovaTech FET College - Analytics Report', 0, 0, 'C');
        // Line break
        $this->Ln(20);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        // Date generated
        $this->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 0, 'R');
    }

    // Chapter title
    function ChapterTitle($label)
    {
        // Arial 12
        $this->SetFont('Arial', 'B', 12);
        // Background color
        $this->SetFillColor(200, 220, 255);
        // Title
        $this->Cell(0, 6, $label, 0, 1, 'L', true);
        // Line break
        $this->Ln(4);
    }

    // Statistics table
    function StatisticsTable()
    {
        $this->ChapterTitle('Key Statistics');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(80, 8, 'Metric', 1, 0, 'L', true);
        $this->Cell(40, 8, 'Count', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Value', 1, 1, 'C', true);
        
        // Table data
        $this->SetFillColor(255, 255, 255);
        
        $metrics = [
            'Total Students' => $this->stats['total_students'],
            'Total Teachers' => $this->stats['total_teachers'],
            'Total Parents' => $this->stats['total_parents'],
            'Total Courses' => $this->stats['total_courses'],
            'Active Subscriptions' => $this->stats['active_subscriptions'],
            'Expired Subscriptions' => $this->stats['expired_subscriptions'],
            'Total Revenue' => 'R ' . number_format($this->stats['total_revenue'], 2),
            'Total Announcements' => $this->stats['total_announcements'],
            'Total Study Groups' => $this->stats['total_study_groups'],
            'Total Live Lessons' => $this->stats['total_live_lessons'],
            'Total Quiz Attempts' => $this->stats['total_quiz_attempts'],
            'Premium Users' => $this->stats['premium_users'],
            'Standard Users' => $this->stats['standard_users'],
            'Basic Users' => $this->stats['basic_users']
        ];

        foreach ($metrics as $metric => $value) {
            $this->Cell(80, 8, $metric, 1);
            $this->Cell(40, 8, is_numeric($value) ? number_format($value) : '', 1, 0, 'C');
            $this->Cell(40, 8, $value, 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // System Performance Metrics
    function SystemMetrics()
    {
        $this->ChapterTitle('System Performance Metrics');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(100, 8, 'Metric', 1, 0, 'L', true);
        $this->Cell(60, 8, 'Value', 1, 1, 'C', true);
        
        $metrics = [
            'Average Quiz Score' => $this->system_metrics['avg_quiz_score'] . '%',
            'Active Study Groups' => $this->system_metrics['active_study_groups'],
            'Recent Messages (7 days)' => $this->system_metrics['recent_messages']
        ];

        foreach ($metrics as $metric => $value) {
            $this->Cell(100, 8, $metric, 1);
            $this->Cell(60, 8, $value, 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // Support Cases Summary
    function SupportCases()
    {
        $this->ChapterTitle('Support Cases Summary');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(80, 8, 'Status', 1, 0, 'L', true);
        $this->Cell(60, 8, 'Count', 1, 1, 'C', true);
        
        $statuses = [
            'Open' => $this->support_stats['Open'] ?? 0,
            'In Progress' => $this->support_stats['In Progress'] ?? 0,
            'Resolved' => $this->support_stats['Resolved'] ?? 0,
            'Closed' => $this->support_stats['Closed'] ?? 0
        ];

        foreach ($statuses as $status => $count) {
            $this->Cell(80, 8, $status, 1);
            $this->Cell(60, 8, number_format($count), 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // Package distribution
    function PackageDistribution()
    {
        $this->ChapterTitle('Package Distribution');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(80, 8, 'Package', 1, 0, 'L', true);
        $this->Cell(40, 8, 'Users', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Percentage', 1, 1, 'C', true);
        
        $totalUsers = $this->stats['premium_users'] + $this->stats['standard_users'] + $this->stats['basic_users'];
        
        $packages = [
            'Premium' => $this->stats['premium_users'],
            'Standard' => $this->stats['standard_users'],
            'Basic' => $this->stats['basic_users']
        ];

        foreach ($packages as $package => $count) {
            $percentage = $totalUsers > 0 ? round(($count / $totalUsers) * 100, 1) : 0;
            
            $this->Cell(80, 8, $package, 1);
            $this->Cell(40, 8, number_format($count), 1, 0, 'C');
            $this->Cell(40, 8, $percentage . '%', 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // Course popularity
    function CoursePopularity()
    {
        $this->ChapterTitle('Course Popularity');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(100, 8, 'Course Name', 1, 0, 'L', true);
        $this->Cell(60, 8, 'Enrollments', 1, 1, 'C', true);
        
        if (!empty($this->chartsData['course_popularity'])) {
            foreach ($this->chartsData['course_popularity'] as $course) {
                $this->Cell(100, 8, $course['course_name'], 1);
                $this->Cell(60, 8, number_format($course['enrollments']), 1, 1, 'C');
            }
        } else {
            $this->Cell(160, 8, 'No course data available', 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // Student Engagement
    function StudentEngagement()
    {
        $this->ChapterTitle('Student Engagement (Quiz Attempts)');
        
        $this->SetFont('Arial', '', 10);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(100, 8, 'Course Name', 1, 0, 'L', true);
        $this->Cell(60, 8, 'Quiz Attempts', 1, 1, 'C', true);
        
        if (!empty($this->chartsData['student_engagement'])) {
            foreach ($this->chartsData['student_engagement'] as $course) {
                $this->Cell(100, 8, $course['course_name'], 1);
                $this->Cell(60, 8, number_format($course['quiz_attempts']), 1, 1, 'C');
            }
        } else {
            $this->Cell(160, 8, 'No engagement data available', 1, 1, 'C');
        }
        
        $this->Ln(10);
    }

    // Recent activities
    function RecentActivities()
    {
        $this->ChapterTitle('Recent System Activities');
        
        $this->SetFont('Arial', '', 8);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(35, 8, 'User', 1, 0, 'L', true);
        $this->Cell(25, 8, 'Type', 1, 0, 'C', true);
        $this->Cell(80, 8, 'Activity', 1, 0, 'L', true);
        $this->Cell(30, 8, 'Date', 1, 1, 'C', true);
        
        if (!empty($this->chartsData['recent_activities'])) {
            $activities = array_slice($this->chartsData['recent_activities'], 0, 15);
            
            foreach ($activities as $activity) {
                // Wrap text for activity description
                $activityText = substr($activity['message'], 0, 60) . (strlen($activity['message']) > 60 ? '...' : '');
                $userName = substr($activity['user_name'] ?? 'System', 0, 20);
                
                $this->Cell(35, 8, $userName, 1);
                $this->Cell(25, 8, $activity['type'], 1, 0, 'C');
                $this->Cell(80, 8, $activityText, 1);
                $this->Cell(30, 8, date('M d, Y', strtotime($activity['created_at'])), 1, 1, 'C');
            }
        } else {
            $this->Cell(170, 8, 'No recent activities', 1, 1, 'C');
        }
    }

    // Growth Trends
    function GrowthTrends()
    {
        $this->ChapterTitle('User Growth Trends (Last 30 Days)');
        
        $this->SetFont('Arial', '', 9);
        
        // Table header
        $this->SetFillColor(230, 230, 230);
        $this->Cell(60, 8, 'Date', 1, 0, 'L', true);
        $this->Cell(50, 8, 'New Students', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Daily Revenue', 1, 1, 'C', true);
        
        if (!empty($this->chartsData['user_growth']) && !empty($this->chartsData['revenue_trends'])) {
            // Combine data for last 7 days for readability
            $recentUserGrowth = array_slice($this->chartsData['user_growth'], -7);
            $recentRevenue = array_slice($this->chartsData['revenue_trends'], -7);
            
            foreach ($recentUserGrowth as $index => $growth) {
                $date = date('M d', strtotime($growth['date']));
                $students = $growth['count'];
                $revenue = isset($recentRevenue[$index]) ? 'R ' . number_format($recentRevenue[$index]['revenue'], 2) : 'R 0.00';
                
                $this->Cell(60, 8, $date, 1);
                $this->Cell(50, 8, $students, 1, 0, 'C');
                $this->Cell(50, 8, $revenue, 1, 1, 'C');
            }
        } else {
            $this->Cell(160, 8, 'No growth data available', 1, 1, 'C');
        }
        
        $this->Ln(10);
    }
}

// Fetch data (same as in your admin_analytics.php)
try {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students");
    $stats['total_students'] = $stmt->fetchColumn();

    // Total teachers
    $stmt = $pdo->query("SELECT COUNT(*) as total_teachers FROM users WHERE role = 'teacher'");
    $stats['total_teachers'] = $stmt->fetchColumn();

    // Total parents/financiers
    $stmt = $pdo->query("SELECT COUNT(*) as total_parents FROM financiers WHERE role = 'Parent'");
    $stats['total_parents'] = $stmt->fetchColumn();

    // Total revenue (lifetime)
    $stmt = $pdo->query("SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'Completed'");
    $stats['total_revenue'] = $stmt->fetchColumn() ?? 0;

    // Active subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as active_subscriptions FROM students WHERE subscription_status = 'active'");
    $stats['active_subscriptions'] = $stmt->fetchColumn();

    // Expired subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as expired_subscriptions FROM students WHERE subscription_status = 'expired'");
    $stats['expired_subscriptions'] = $stmt->fetchColumn();

    // Total courses
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses");
    $stats['total_courses'] = $stmt->fetchColumn();

    // Total announcements
    $stmt = $pdo->query("SELECT COUNT(*) as total_announcements FROM announcements");
    $stats['total_announcements'] = $stmt->fetchColumn();

    // Total study groups
    $stmt = $pdo->query("SELECT COUNT(*) as total_study_groups FROM study_groups");
    $stats['total_study_groups'] = $stmt->fetchColumn();

    // Total live lessons
    $stmt = $pdo->query("SELECT COUNT(*) as total_live_lessons FROM live_lessons");
    $stats['total_live_lessons'] = $stmt->fetchColumn();

    // Total quiz attempts
    $stmt = $pdo->query("SELECT COUNT(*) as total_quiz_attempts FROM lockdown_quiz_attempts");
    $stats['total_quiz_attempts'] = $stmt->fetchColumn();

    // Package distribution
    $stmt = $pdo->query("SELECT package_selected, COUNT(*) as count FROM students WHERE package_selected IS NOT NULL GROUP BY package_selected");
    $package_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['premium_users'] = $package_stats['Premium'] ?? 0;
    $stats['standard_users'] = $package_stats['Standard'] ?? 0;
    $stats['basic_users'] = $package_stats['Basic'] ?? 0;

    // User growth (students over time - last 30 days)
    $stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM students 
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                         GROUP BY DATE(created_at) 
                         ORDER BY date");
    $chartsData['user_growth'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue trends (last 30 days)
    $stmt = $pdo->query("SELECT DATE(created_at) as date, SUM(amount) as revenue 
                         FROM payments 
                         WHERE status = 'Completed' 
                         AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         GROUP BY DATE(created_at) 
                         ORDER BY date");
    $chartsData['revenue_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Course popularity (enrollments)
    $stmt = $pdo->query("SELECT c.course_name, COUNT(e.id) as enrollments 
                         FROM courses c 
                         LEFT JOIN enrollments e ON c.id = e.course_id 
                         GROUP BY c.id 
                         ORDER BY enrollments DESC 
                         LIMIT 10");
    $chartsData['course_popularity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student engagement (quiz attempts per course)
    $stmt = $pdo->query("SELECT c.course_name, COUNT(lqa.id) as quiz_attempts 
                         FROM courses c 
                         LEFT JOIN lockdown_quiz_attempts lqa ON c.id = lqa.course_id 
                         GROUP BY c.id 
                         ORDER BY quiz_attempts DESC 
                         LIMIT 8");
    $chartsData['student_engagement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent activities
    $stmt = $pdo->query("SELECT 'Payment' as type, CONCAT('Payment received - R', amount) as message, created_at, 
                         (SELECT CONCAT(first_name, ' ', surname) FROM students WHERE id = p.student_id) as user_name
                         FROM payments p 
                         WHERE status = 'Completed' 
                         UNION ALL
                         SELECT 'New Student' as type, CONCAT('New student registered') as message, created_at,
                         CONCAT(first_name, ' ', surname) as user_name
                         FROM students 
                         UNION ALL
                         SELECT 'Quiz' as type, CONCAT('Quiz completed - Score: ', COALESCE(score, 0)) as message, submitted_at as created_at,
                         (SELECT CONCAT(first_name, ' ', surname) FROM students WHERE id = lqa.user_id) as user_name
                         FROM lockdown_quiz_attempts lqa
                         ORDER BY created_at DESC 
                         LIMIT 15");
    $chartsData['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Support cases summary
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM support_cases GROUP BY status");
    $support_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // System performance metrics
    $system_metrics = [];
    $stmt = $pdo->query("SELECT ROUND(AVG(percentage), 2) as avg_quiz_score FROM lockdown_quiz_attempts WHERE percentage > 0");
    $system_metrics['avg_quiz_score'] = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as active_study_groups FROM study_groups WHERE last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $system_metrics['active_study_groups'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as recent_messages FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $system_metrics['recent_messages'] = $stmt->fetchColumn();

} catch (Exception $e) {
    die("Error fetching data for PDF: " . $e->getMessage());
}

// Generate PDF
$pdf = new AnalyticsPDF($stats, $chartsData, $system_metrics, $support_stats);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Add report title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'System Analytics Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Comprehensive overview of platform performance and user engagement', 0, 1, 'C');
$pdf->Ln(10);

// Add content sections
$pdf->StatisticsTable();
$pdf->SystemMetrics();
$pdf->SupportCases();

// Check if we need a new page
if ($pdf->GetY() > 180) {
    $pdf->AddPage();
}

$pdf->PackageDistribution();
$pdf->CoursePopularity();

// Check if we need a new page
if ($pdf->GetY() > 180) {
    $pdf->AddPage();
}

$pdf->StudentEngagement();
$pdf->GrowthTrends();

// Check if we need a new page
if ($pdf->GetY() > 150) {
    $pdf->AddPage();
}

$pdf->RecentActivities();

// Output PDF - CHANGED TO FORCE DOWNLOAD LIKE PACKAGE MANAGEMENT
$pdf->Output('D', 'NovaTech_Analytics_Report_' . date('Y-m-d') . '.pdf');
?>