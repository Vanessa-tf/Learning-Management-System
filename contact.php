<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - NovaTech FET College</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Important: same stylesheet as Subjects page -->
  <link rel="stylesheet" href="Css/style_subject.css">
  <link rel="stylesheet" href="css/contact.css">
  <style>
    /* Root variables from subjects.php CSS */
    :root {
        --navy: #1e3a8a;
        --gold: #facc15;
        --beige: #f5f5dc;
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

    /* Hero Section with Gradient */
    .hero {
        background-color: var(--navy);
        color: white;
        text-align: center;
        padding: 100px 0;
        position: relative;
        overflow: hidden;
    }

    .hero:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, var(--navy), var(--gold));
        opacity: 0.7;
    }

    .hero .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-content h1 {
        font-size: 3em;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        font-weight: 700;
    }

    .hero-content p {
        font-size: 1.5em;
        margin-bottom: 30px;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
        font-weight: 400;
        line-height: 1.6;
    }

    /* Active navigation link */
    nav ul li a.active {
        color: var(--gold);
        font-weight: 600;
    }

    /* Flexibility section */
    .flexibility-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }

    .flexibility-card {
        text-align: center;
        padding: 30px;
        background-color: var(--beige);
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }

    .flexibility-card:hover {
        transform: translateY(-10px);
    }

    .flexibility-icon {
        font-size: 2.5em;
        color: var(--navy);
        margin-bottom: 15px;
    }

    /* Package badge */
    .package-badge {
        position: absolute;
        top: -10px;
        right: 20px;
        background-color: var(--gold);
        color: var(--navy);
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8em;
    }

    .package-card {
        position: relative;
        padding-top: 40px;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .package-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .package-card.popular {
        transform: scale(1.05);
        border: 2px solid var(--gold);
    }

    .package-card.popular:hover {
        transform: scale(1.05) translateY(-10px);
    }

    .package-card ul li {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .package-card ul li i.fa-check-circle {
        color: #22c55e;
    }

    .package-card ul li i.fa-times-circle {
        color: #ef4444;
    }

    /* FAQ Section */
    .faq-section {
        padding: 80px 0;
        background-color: var(--beige);
    }

    .faq-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .faq-item {
        background-color: white;
        margin-bottom: 15px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .faq-item h3 {
        padding: 20px;
        margin: 0;
        color: var(--navy);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.1em;
    }

    .faq-item h3 i {
        transition: transform 0.3s;
    }

    .faq-answer {
        padding: 0 20px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, padding 0.3s ease;
    }

    .faq-answer.active {
        padding: 0 20px 20px;
        max-height: 200px;
    }

    .faq-answer p {
        margin: 0;
        color: #333;
    }

    /* Button Styles to match subjects.php */
    .package-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 25px;
    }

    .package-buttons .btn {
        padding: 12px 25px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        text-align: center;
        display: block;
        width: 100%;
        box-sizing: border-box;
    }

    .package-buttons .btn-primary {
        background-color: var(--gold);
        color: var(--navy);
        border: 2px solid var(--gold);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .package-buttons .btn-primary:hover {
        background-color: #eab308;
        border-color: #eab308;
        transform: scale(1.05);
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        text-decoration: none;
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

    /* Contact Section Specific Styles */
    .contact-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }

    .contact-card {
        text-align: center;
        padding: 30px;
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s;
    }

    .contact-card:hover {
        transform: translateY(-5px);
    }

    .contact-icon {
        font-size: 2.5em;
        color: var(--navy);
        margin-bottom: 15px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .hero {
            padding: 60px 0;
        }

        .hero-content h1 {
            font-size: 2.5em;
        }

        .hero-content p {
            font-size: 1.2em;
        }

        .package-card.popular {
            transform: scale(1);
        }

        .package-card.popular:hover {
            transform: translateY(-10px);
        }

        .flexibility-info {
            grid-template-columns: 1fr;
        }

        .package-buttons {
            flex-direction: column;
        }

        .package-buttons .btn {
            width: 100%;
        }

        .faq-item h3 {
            font-size: 1em;
            padding: 15px;
        }

        .faq-answer {
            padding: 0 15px;
        }

        .faq-answer.active {
            padding: 0 15px 15px;
        }

        .contact-info-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Additional spacing for sections */
    .section-spacing {
        padding: 80px 0;
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

    <!-- Header (identical to Subjects page) -->
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

    <!-- Contact Hero Section -->
    <section class="relative text-center text-white py-24" style="background-image: url('images/heroo.jpg'); background-size: cover; background-position: center;">
        <div class="absolute inset-0 bg-gradient-to-r from-navy to-gold opacity-70"></div>
        <div class="relative z-10 container mx-auto px-6">
            <h1 class="text-5xl font-bold mb-4">Get in Touch With Us</h1>
            <p class="text-lg">Have questions about our matric rewrite programs? We're here to help you succeed.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-navy">Contact NovaTech FET College</h2>
                <p class="text-gray-600">Reach out to us through any of these channels</p>
            </div>
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div class="grid sm:grid-cols-2 gap-6">
                    <div class="bg-beige p-6 rounded-xl shadow-lg text-center">
                        <div class="text-3xl text-navy mb-4"><i class="fas fa-map-marker-alt"></i></div>
                        <h3 class="text-xl font-semibold text-navy mb-2">Visit Us</h3>
                        <p>123 Education Street<br>Midrand, 1685<br>South Africa</p>
                    </div>
                    <div class="bg-beige p-6 rounded-xl shadow-lg text-center">
                        <div class="text-3xl text-navy mb-4"><i class="fas fa-phone"></i></div>
                        <h3 class="text-xl font-semibold text-navy mb-2">Call Us</h3>
                        <p>+27 66 193 1982<br>Mon-Fri: 8am - 5pm<br>Sat: 9am - 1pm</p>
                    </div>
                    <div class="bg-beige p-6 rounded-xl shadow-lg text-center">
                        <div class="text-3xl text-navy mb-4"><i class="fas fa-envelope"></i></div>
                        <h3 class="text-xl font-semibold text-navy mb-2">Email Us</h3>
                        <p>info@novatechfet.co.za<br>support@novatechfet.co.za<br>admissions@novatechfet.co.za</p>
                    </div>
                    <div class="bg-beige p-6 rounded-xl shadow-lg text-center">
                        <div class="text-3xl text-navy mb-4"><i class="fas fa-graduation-cap"></i></div>
                        <h3 class="text-xl font-semibold text-navy mb-2">Student Support</h3>
<p>For academic queries, technical issues, or general assistance, our support team is available during business hours.</p>        
                </div>
				</div>
                <!-- Contact Form -->
                <div class="bg-beige p-8 rounded-xl shadow-lg">
                    <h3 class="text-xl font-bold text-navy mb-6 text-center">Send us a Message</h3>
                    <form class="space-y-4" action="send_contact.php" method="POST">
                        <div>
                            <label for="name" class="block text-navy font-medium mb-2">Full Name *</label>
                            <input type="text" id="name" name="name" required class="w-full border rounded-md p-3">
                        </div>
                        <div>
                            <label for="email" class="block text-navy font-medium mb-2">Email Address *</label>
                            <input type="email" id="email" name="email" required class="w-full border rounded-md p-3">
                        </div>
                        <div>
                            <label for="phone" class="block text-navy font-medium mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="w-full border rounded-md p-3">
                        </div>
                        <div>
                            <label for="subject" class="block text-navy font-medium mb-2">Subject *</label>
                            <select id="subject" name="subject" required class="w-full border rounded-md p-3">
                                <option value="">Select a subject</option>
                                <option value="admissions">Admissions Inquiry</option>
                                <option value="academic">Academic Questions</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing Inquiry</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="message" class="block text-navy font-medium mb-2">Message *</label>
                            <textarea id="message" name="message" rows="5" required class="w-full border rounded-md p-3"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-gold text-navy font-bold py-3 rounded-lg hover:bg-yellow-500 transition">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 bg-beige">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-navy">Frequently Asked Questions</h2>
                <p class="text-gray-600">Quick answers to common questions</p>
            </div>
           
            <div class="faq-container">
                <div class="faq-item">
                    <h3>How do I enroll in a matric rewrite program?<i class="fas fa-chevron-down"></i></h3>
                    <div class="faq-answer">
                        <p>You can enroll by registering on our website, selecting your subjects, and choosing a subscription package.</p>
                    </div>
                </div>
               
                <div class="faq-item">
                    <h3>What subjects do you offer? <i class="fas fa-chevron-down"></i></h3>
                    <div class="faq-answer">
                        <p>We offer Mathematics, Physical Science, English, and CAT. Visit our Subjects page for more.</p>
                    </div>
                </div>
               
                <div class="faq-item">
                    <h3>Can I change my subscription plan later? <i class="fas fa-chevron-down"></i></h3>
                    <div class="faq-answer">
                        <p>Yes, you can upgrade or downgrade anytime through your student dashboard.</p>
                    </div>
                </div>
               
                <div class="faq-item">
                    <h3>Do you provide physical study materials? <i class="fas fa-chevron-down"></i></h3>
                    <div class="faq-answer">
                        <p>All our study materials are digital and accessible through our online platform.</p>
                    </div>
                </div>
               
                <div class="faq-item">
                    <h3>Will I get a refund if I cancel? <i class="fas fa-chevron-down"></i></h3>
                    <div class="faq-answer">
                        <p>We do not offer any refunds for cancellations.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-navy mb-4">Our Location</h2>
            <p class="text-gray-600 mb-8">Visit our campus in Midrand</p>
            <div class="rounded-xl shadow-lg overflow-hidden">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d57494.55030087582!2d28.102988!3d-25.996583!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1e95714a4a299339%3A0xeb082cd16e63d961!2sMidrand%2C%20South%20Africa!5e0!3m2!1sen!2sus!4v1642000000000!5m2!1sen!2sus" width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
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
                    <div class="text-lg font-medium">Training</div>
                </div>
                <div data-aos="fade-up" data-aos-delay="200">
                    <div class="text-lg font-medium">Students Enrolled</div>
                </div>
                <div data-aos="fade-up" data-aos-delay="300">
                    <div class="text-lg font-medium">Qualified Educators</div>
                </div>
                <div data-aos="fade-up" data-aos-delay="400">
                    <div class="text-lg font-medium">Years Experience</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer - Exact copy from subjects.php -->
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
                <p>&copy; 2025 NovaTech FET College. All Rights Reserved.</p>
                <p>|Designed by STEMinists|</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        AOS.init();
        feather.replace();
        
        // FAQ toggle
        document.querySelectorAll('.faq-item h3').forEach(q => {
            q.addEventListener('click', () => {
                const answer = q.nextElementSibling;
                const icon = q.querySelector('i');
                answer.classList.toggle('active');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });
        });

        // Registration modal functions
        function showRegistrationClosed() {
            document.getElementById('registrationModal').classList.add('active');
        }

        function closeRegistrationModal() {
            document.getElementById('registrationModal').classList.remove('active');
        }

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