<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaTech FET College - Matric Rewrite LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a8a;
            --gold: #fbbf24;
            --beige: #f5f1e3;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        .bg-navy {
            background-color: var(--navy);
        }
        
        .bg-gold {
            background-color: var(--gold);
        }
        
        .bg-light-beige {
            background-color: var(--beige);
        }
        
        .text-navy {
            color: var(--navy);
        }
        
        .text-gold {
            color: var(--gold);
        }
        
        .logo-text {
            font-weight: 700;
        }
        
        .logo-college {
            color: var(--gold);
        }
        
        .subject-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .hidden-content {
            display: none;
        }
        
        .hidden-content.visible {
            display: block;
        }
        
        .testimonial-card {
            background-color: #f8fafc;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-card {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--navy);
            margin-bottom: 1rem;
        }
        
        .step {
            text-align: center;
            padding: 1.5rem;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background-color: var(--gold);
            color: var(--navy);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        
        .package-card {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .package-card:hover {
            transform: translateY(-5px);
        }
        
        .price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--navy);
            margin: 1rem 0;
        }
        
        .countdown-container {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(30, 58, 138, 0.1) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 2px solid rgba(251, 191, 36, 0.3);
        }
        
        .countdown-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .countdown-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--navy);
        }
        
        .countdown-label {
            font-size: 0.75rem;
            color: var(--navy);
            text-transform: uppercase;
            margin-top: 0.25rem;
        }
        
        .exam-period-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .badge-june {
            background-color: #3b82f6;
            color: white;
        }
        
        .badge-november {
            background-color: #8b5cf6;
            color: white;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-icon {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--navy);
            margin-bottom: 1rem;
        }
        
        .modal-message {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .modal-button {
            background-color: var(--gold);
            color: var(--navy);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .modal-button:hover {
            background-color: #f59e0b;
        }
    </style>
</head>
<body class="bg-light-beige">
    <?php
    // Database connection to get intake schedules
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "novatech_db";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        // If database connection fails, use default values
        $intake_schedules = [
            'May/June' => [
                'intake_year' => 2025,
                'exam_start_date' => '2025-05-08',
                'exam_end_date' => '2025-06-06',
                'registration_deadline' => '2025-02-21',
                'results_date' => '2025-08-05',
                'status' => 'current'
            ],
            'October/November' => [
                'intake_year' => 2025,
                'exam_start_date' => '2025-10-08',
                'exam_end_date' => '2025-11-07',
                'registration_deadline' => '2025-08-15',
                'results_date' => '2025-12-05',
                'status' => 'next'
            ]
        ];
        $registration_closed = false;
    } else {
        // Get intake schedules from database
        $intake_schedules = [];
        $result = $conn->query("SELECT * FROM intake_schedules ORDER BY exam_start_date");
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $intake_schedules[$row['intake_name']] = $row;
            }
        } else {
            // Use default values if no data in database
            $intake_schedules = [
                'May/June' => [
                    'intake_year' => 2025,
                    'exam_start_date' => '2025-05-08',
                    'exam_end_date' => '2025-06-06',
                    'registration_deadline' => '2025-02-21',
                    'results_date' => '2025-08-05',
                    'status' => 'current'
                ],
                'October/November' => [
                    'intake_year' => 2025,
                    'exam_start_date' => '2025-10-08',
                    'exam_end_date' => '2025-11-07',
                    'registration_deadline' => '2025-08-15',
                    'results_date' => '2025-12-05',
                    'status' => 'next'
                ]
            ];
        }
        
        // Check if registration deadline has passed
        $registration_closed = false;
        $today = date('Y-m-d');
        
        // Find the next upcoming registration deadline
        $upcoming_deadline = null;
        foreach ($intake_schedules as $schedule) {
            if ($schedule['registration_deadline'] >= $today) {
                if (!$upcoming_deadline || $schedule['registration_deadline'] < $upcoming_deadline) {
                    $upcoming_deadline = $schedule['registration_deadline'];
                }
            }
        }
        
        // If no upcoming deadlines found, registration is closed
        if (!$upcoming_deadline) {
            $registration_closed = true;
        }
        
        $conn->close();
    }
    ?>

    <!-- Registration Closed Modal -->
    <div id="registrationModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h2 class="modal-title">Registration Closed</h2>
            <p class="modal-message">
                The registration period for the current intake has ended. 
                Please check back for the next enrollment period.
            </p>
            <button class="modal-button" onclick="closeRegistrationModal()">OK</button>
        </div>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-20 w-auto"/>
                    <span class="ml-4 text-2xl font-bold text-navy">
                        <span class="logo-text">NovaTech FET</span>
                        <span class="logo-college"> College</span>
                    </span>
                </div>
                <nav class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-navy hover:text-gold font-medium">Home</a>
                    <a href="subjects.php" class="text-navy hover:text-gold font-medium">Subjects</a>
                    <a href="packages.php" class="text-navy hover:text-gold font-medium">Packages</a>
                    <a href="about.php" class="text-navy hover:text-gold font-medium">About Us</a>
                    <a href="contact.php" class="text-navy hover:text-gold font-medium">Contact Us</a>
                </nav>
                <div class="flex items-center space-x-4">
				<a href="login.php" class="px-6 py-3 bg-navy text-white font-bold rounded-lg hover:bg-opacity-90 transition">Login</a>
                    <?php if ($registration_closed): ?>
                        <button onclick="showRegistrationClosed()" class="px-6 py-3 bg-gray-400 text-white font-bold rounded-lg cursor-not-allowed" disabled>
                            Enrollment Closed
                        </button>
                    <?php else: ?>
                        <a href="enroll.php" class="px-6 py-3 bg-gold text-navy font-bold rounded-lg hover:bg-yellow-500 transition">Enroll Now</a>
                    <?php endif; ?>
                    <button class="md:hidden focus:outline-none">
                        <i data-feather="menu" class="text-navy"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-navy text-white">
        <div class="absolute inset-0 overflow-hidden">
            <img src="images/hero.jpg" alt="Background" class="w-full h-full object-cover filter blur-sm opacity-20">
        </div>
        <div class="container mx-auto px-6 py-24 md:py-32 relative z-10">
            <div class="max-w-3xl mx-auto text-center" data-aos="fade-up">
                <h1 class="text-5xl md:text-7xl font-bold mb-6">Rewrite Your Matric, Rewrite Your Future</h1>
                <p class="text-xl mb-12">Access quality learning resources, expert tutors, and a supportive community to improve your matric results and unlock new opportunities.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <?php if ($registration_closed): ?>
                        <button onclick="showRegistrationClosed()" class="bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105 cursor-not-allowed" disabled>
                            Enrollment Closed
                        </button>
                    <?php else: ?>
                        <a href="enroll.php" class="bg-gold hover:bg-yellow-600 text-navy font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105">
                            Get Started Today
                        </a>
                    <?php endif; ?>
                    <a href="subjects.php" class="bg-transparent hover:bg-white hover:text-navy border-2 border-white text-white font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105">
                        View Subjects
                    </a>
                </div>
                
                <!-- Countdown Timer Section -->
                <div class="countdown-container mt-8" data-aos="fade-up" data-aos-delay="200">
                    <h3 class="text-xl font-bold text-center mb-4">Next Registration Deadline</h3>
                    <div class="flex justify-center mb-2">
                        <span class="exam-period-badge" id="exam-period-badge">Loading...</span>
                    </div>
                    <p class="text-center mb-4" id="countdown-description">Register before the deadline to secure your spot!</p>
                    
                    <div class="grid grid-cols-4 gap-4 max-w-md mx-auto">
                        <div class="countdown-item">
                            <div class="countdown-number" id="countdown-days">00</div>
                            <div class="countdown-label">Days</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="countdown-hours">00</div>
                            <div class="countdown-label">Hours</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="countdown-minutes">00</div>
                            <div class="countdown-label">Minutes</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="countdown-seconds">00</div>
                            <div class="countdown-label">Seconds</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <p class="text-sm opacity-80" id="deadline-text">Registration deadline: <span id="deadline-date">Loading...</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute inset-0 opacity-20">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-navy"></div>
        </div>
    </div>

    <!-- About Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-navy mb-4">About NovaTech FET College</h2>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">Empowering South African students with flexible, stigma-free online learning.</p>
            </div>
            <div class="max-w-3xl mx-auto text-center" data-aos="fade-up" data-aos-delay="100">
                <p class="text-gray-600 text-lg">NovaTech FET College is dedicated to providing an innovative Learning Management System (LMS) that allows students to rewrite their matric comfortably from home.</p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-beige">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-navy mb-4">Why Choose NovaTech LMS?</h2>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">Our platform is designed specifically for matric rewrite students, offering comprehensive tools for success.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon"><i class="fas fa-book-open"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Past Exam Papers</h3>
                    <p class="text-gray-600">Access a comprehensive database of NSC exam papers with detailed solutions.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon"><i class="fas fa-video"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Live & Recorded Lessons</h3>
                    <p class="text-gray-600">Attend live classes or watch recordings at your convenience.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Progress Tracking</h3>
                    <p class="text-gray-600">Monitor your improvement with personalized analytics.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon"><i class="fas fa-users"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Peer Community</h3>
                    <p class="text-gray-600">Collaborate and motivate each other in our forums.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Mobile Access</h3>
                    <p class="text-gray-600">Study anytime, anywhere on any device.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <h3 class="text-xl font-bold text-navy mb-2">Affordable Packages</h3>
                    <p class="text-gray-600">Flexible plans to fit your budget and needs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-navy mb-4">How It Works</h2>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">Simple steps to start your matric rewrite journey.</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="step" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-number">1</div>
                    <h3 class="text-xl font-bold text-navy mb-2">Register</h3>
                    <p class="text-gray-600">Create an account and select subjects.</p>
                </div>
                
                <div class="step" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-number">2</div>
                    <h3 class="text-xl font-bold text-navy mb-2">Choose Package</h3>
                    <p class="text-gray-600">Select a plan that suits you.</p>
                </div>
                
                <div class="step" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-number">3</div>
                    <h3 class="text-xl font-bold text-navy mb-2">Start Learning</h3>
                    <p class="text-gray-600">Access resources and track progress.</p>
                </div>
                
                <div class="step" data-aos="fade-up" data-aos-delay="400">
                    <div class="step-number">4</div>
                    <h3 class="text-xl font-bold text-navy mb-2">Excel</h3>
                    <p class="text-gray-600">Achieve your academic goals.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="py-20 bg-beige">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-navy mb-4">Our Packages</h2>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">Choose a plan that fits your learning needs and budget.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="package-card" data-aos="fade-up" data-aos-delay="100">
                    <h3 class="text-2xl font-bold text-navy mb-4">Basic Package</h3>
                    <p class="price">Free</p>
                    <a href="packages.php" class="mt-4 bg-navy text-white py-2 px-6 rounded-lg hover:bg-opacity-90 transition inline-block">
                        Learn More
                    </a>
                </div>
                
                <div class="package-card" data-aos="fade-up" data-aos-delay="200">
                    <h3 class="text-2xl font-bold text-navy mb-4">Standard Plan</h3>
                    <p class="price">R699/month</p>
                    <a href="packages.php" class="mt-4 bg-navy text-white py-2 px-6 rounded-lg hover:bg-opacity-90 transition inline-block">
                        Learn More
                    </a>
                </div>
                
                <div class="package-card" data-aos="fade-up" data-aos-delay="300">
                    <h3 class="text-2xl font-bold text-navy mb-4">Premium Package</h3>
                    <p class="price">R1 199/month</p>
                    <a href="packages.php" class="mt-4 bg-navy text-white py-2 px-6 rounded-lg hover:bg-opacity-90 transition inline-block">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="apply" class="py-20 bg-navy text-white">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Start Your Journey?</h2>
                <p class="text-xl mb-8">Join NovaTech FET College and unlock your potential with our exceptional education programs.</p>

                <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                    <?php if ($registration_closed): ?>
                        <button onclick="showRegistrationClosed()" class="bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105 cursor-not-allowed" disabled>
                            Enrollment Closed
                        </button>
                    <?php else: ?>
                        <a href="enroll.php" class="bg-gold hover:bg-yellow-600 text-navy font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105">
                            Apply Now
                        </a>
                    <?php endif; ?>
                    <a href="contact.php" class="bg-transparent hover:bg-white hover:text-navy border-2 border-white text-white font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105">
                        Contact Us
                    </a>
                </div>
                
                <div class="mt-12 flex justify-center">
                    <div class="relative">
                        <div class="absolute -inset-1 bg-gold rounded-lg blur opacity-75 animate-pulse"></div>
                        <div class="relative bg-white text-navy px-6 py-3 rounded-lg font-bold">
                            <?php if ($registration_closed): ?>
                                Next Enrollment Period Opening Soon!
                            <?php else: ?>
                                Limited Spaces Available - Apply Today!
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
	

  <!-- Stats Section -->
  <section class="py-16 bg-gold text-navy">
    <div class="container mx-auto px-6">
      <div class="grid md:grid-cols-4 gap-8 text-center">
        <div data-aos="fade-up" data-aos-delay="100">
          <div class="text-4xl font-bold mb-2"></div>
          <div class="text-lg font-medium">Core Subjects</div>
        </div>
        <div data-aos="fade-up" data-aos-delay="200">
          <div class="text-4xl font-bold mb-2"></div>
          <div class="text-lg font-medium">Students Enrolled</div>
        </div>
        <div data-aos="fade-up" data-aos-delay="300">
          <div class="text-4xl font-bold mb-2"></div>
          <div class="text-lg font-medium">Qualified Educators</div>
        </div>
        <div data-aos="fade-up" data-aos-delay="400">
          <div class="text-4xl font-bold mb-2"></div>
          <div class="text-lg font-medium">Years Experience</div>
        </div>
      </div>
    </div>
  </section>

    <!-- Footer -->
    <footer class="bg-navy text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="space-y-4">
                    <div class="flex items-center">
                        <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-16 w-auto"/>
                        <span class="ml-4 text-2xl font-bold">
                            <span>NovaTech FET</span>
                            <span class="text-gold"> College</span>
                        </span>
                    </div>
                    <p>Empowering matric rewrite students with quality education.</p>
                    <p>NovaTech - Rewriting Futures, Transforming Lives</p>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gold">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="hover:text-gold transition">Home</a></li>
                        <li><a href="subjects.php" class="hover:text-gold transition">Subjects</a></li>
                        <li><a href="packages.php" class="hover:text-gold transition">Packages</a></li>
                        <li><a href="about.php" class="hover:text-gold transition">About Us</a></li>
                        <li><a href="contact.php" class="hover:text-gold transition">Contact Us</a></li>
						 <li><a href="login.php" class="hover:text-gold transition">Login</a></li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gold">Subjects</h3>
                    <ul class="space-y-2">
                        <li><a href="subjects.php" class="hover:text-gold transition">Mathematics</a></li>
                        <li><a href="subjects.php" class="hover:text-gold transition">Physical Science</a></li>
                        <li><a href="subjects.php" class="hover:text-gold transition">English</a></li>
                        <li><a href="subjects.php" class="hover:text-gold transition">CAT</a></li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gold">Contact Us</h3>
                    <div class="flex items-start space-x-3">
                        <i data-feather="map-pin" class="mt-1"></i>
                        <p>123 Education Street, Midrand, 1685</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <i data-feather="phone" class="mt-1"></i>
                        <p>+27 66 193 1982</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <i data-feather="mail" class="mt-1"></i>
                        <a href="mailto:info@novatechfet.co.za" class="hover:text-gold transition">info@novatechfet.co.za</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-12 pt-8 text-center">
                <p>&copy; 2025 NovaTech FET College. All Rights Reserved. | Designed by STEMinists |</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        document.querySelector('header button').addEventListener('click', function() {
            const nav = document.querySelector('header nav');
            nav.classList.toggle('hidden');
            nav.classList.toggle('flex');
            nav.classList.toggle('flex-col');
            nav.classList.toggle('absolute');
            nav.classList.toggle('top-16');
            nav.classList.toggle('left-0');
            nav.classList.toggle('right-0');
            nav.classList.toggle('bg-white');
            nav.classList.toggle('shadow-lg');
            nav.classList.toggle('p-4');
            nav.classList.toggle('space-y-4');
            nav.classList.toggle('space-x-8');
        });

        // Replace icons
        feather.replace();

        // Registration modal functions
        function showRegistrationClosed() {
            document.getElementById('registrationModal').classList.add('active');
        }

        function closeRegistrationModal() {
            document.getElementById('registrationModal').classList.remove('active');
        }

        // Countdown Timer Functionality with Database Data
        function updateCountdown() {
            // Exam schedule data from PHP
            const examSchedule = <?php echo json_encode($intake_schedules); ?>;

            const now = new Date();
            let targetDeadline = null;
            let examPeriod = "";

            // Find the next upcoming registration deadline
            for (const [intakeName, schedule] of Object.entries(examSchedule)) {
                const deadline = new Date(schedule.registration_deadline);
                if (deadline > now) {
                    if (!targetDeadline || deadline < targetDeadline) {
                        targetDeadline = deadline;
                        examPeriod = `${intakeName} ${schedule.intake_year}`;
                    }
                }
            }

            // If no upcoming deadlines found, show the next year's first intake
            if (!targetDeadline) {
                const currentYear = now.getFullYear();
                const nextYear = currentYear + 1;
                targetDeadline = new Date(`${nextYear}-02-21`);
                examPeriod = `May/June ${nextYear}`;
            }

            // Calculate time difference
            const timeDifference = targetDeadline - now;

            if (timeDifference > 0) {
                // Calculate days, hours, minutes, seconds
                const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDifference % (1000 * 60)) / 1000);

                // Update countdown display
                document.getElementById('countdown-days').textContent = days.toString().padStart(2, '0');
                document.getElementById('countdown-hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('countdown-minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('countdown-seconds').textContent = seconds.toString().padStart(2, '0');

                // Update exam period and deadline text
                document.getElementById('exam-period-badge').textContent = examPeriod;
                document.getElementById('exam-period-badge').className = examPeriod.includes('May/June') ? 
                    'exam-period-badge badge-june' : 'exam-period-badge badge-november';
                
                document.getElementById('deadline-date').textContent = formatDateDisplay(targetDeadline.toISOString().split('T')[0]);

                // Update description based on how close the deadline is
                const countdownDescription = document.getElementById('countdown-description');
                if (days === 0) {
                    countdownDescription.textContent = "Last day to register! Don't miss this opportunity!";
                    countdownDescription.className = "text-center mb-4 text-red-600 font-bold";
                } else if (days <= 7) {
                    countdownDescription.textContent = "Only a few days left to register! Secure your spot now!";
                    countdownDescription.className = "text-center mb-4 text-yellow-600 font-bold";
                } else {
                    countdownDescription.textContent = "Register before the deadline to secure your spot!";
                    countdownDescription.className = "text-center mb-4";
                }
            } else {
                // Deadline has passed
                document.getElementById('countdown-days').textContent = '00';
                document.getElementById('countdown-hours').textContent = '00';
                document.getElementById('countdown-minutes').textContent = '00';
                document.getElementById('countdown-seconds').textContent = '00';
                
                document.getElementById('exam-period-badge').textContent = 'Registration Closed';
                document.getElementById('exam-period-badge').className = 'exam-period-badge bg-gray-500 text-white';
                
                document.getElementById('countdown-description').textContent = 'Registration for this period has ended. Check back for the next enrollment period.';
                document.getElementById('countdown-description').className = "text-center mb-4 text-gray-600";
                
                document.getElementById('deadline-date').textContent = 'Registration Closed';
            }
        }

        // Helper function to format date display
        function formatDateDisplay(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        }

        // Update countdown every second
        setInterval(updateCountdown, 1000);

        // Initial call to set up the countdown immediately
        updateCountdown();

        // Prevent default behavior for disabled buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[disabled]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showRegistrationClosed();
                });
            });
        });
    </script>
</body>
</html>