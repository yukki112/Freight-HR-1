<?php
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $_SESSION['username'] ?? 'Guest';
$current_role = $_SESSION['role'] ?? 'Guest';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';

// Database connection
$host = 'localhost:3307';
$dbname = 'freight_management';
$username = 'root'; // Update with your DB username
$password = ''; // Update with your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch published job postings
    $stmt = $pdo->prepare("SELECT * FROM job_postings WHERE status = 'published' ORDER BY published_date DESC");
    $stmt->execute();
    $job_postings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $job_postings = [];
    error_log("Database connection error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLATE - Freight Management System</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #f8fafc;
            background: linear-gradient(135deg, #0a1929 0%, #1a2942 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        i[data-lucide] {
            vertical-align: middle;
        }

        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0a1929 0%, #1a2942 50%, #0f3a4a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-logo {
            width: 120px;
            height: auto;
            margin-bottom: 2rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(14, 165, 233, 0.2);
            border-top-color: #0ea5e9;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: #cbd5e1;
            font-size: 1.1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-weight: 500;
            letter-spacing: 0.5px;
            animation: fadeInOut 2s ease-in-out infinite;
        }

        @keyframes fadeInOut {
            0%, 100% {
                opacity: 0.6;
            }
            50% {
                opacity: 1;
            }
        }

        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% {
                content: '';
            }
            40% {
                content: '.';
            }
            60% {
                content: '..';
            }
            80%, 100% {
                content: '...';
            }
        }
        
        /* Navigation */
        .main-nav {
            background: rgba(30, 41, 54, 0.8);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo img {
            width: 50px;
            height: auto;
        }
        
        .logo h1 {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 0.5rem;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #cbd5e1;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links a:hover {
            color: #ffffff;
            background: rgba(14, 165, 233, 0.15);
            transform: translateY(-1px);
        }

        .nav-links a.btn-primary {
            background: #0ea5e9;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .nav-links a.btn-primary:hover {
            background: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.4);
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            min-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            overflow: hidden;
            padding: 4rem 2rem;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .hero-content {
            max-width: 900px;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(14, 165, 233, 0.2);
            border: 1px solid rgba(14, 165, 233, 0.3);
            color: #0ea5e9;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        
        .hero-content h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-content p {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            color: #cbd5e1;
            line-height: 1.6;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-button {
            background: #0ea5e9;
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
        }
        
        .cta-button:hover {
            background: #0284c7;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.4);
        }

        .cta-button.secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: none;
        }

        .cta-button.secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 4rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: rgba(30, 41, 54, 0.4);
            border-radius: 12px;
            border: 1px solid rgba(58, 69, 84, 0.5);
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0ea5e9;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        /* Job Listings Section */
        .jobs-section {
            padding: 6rem 0;
            background: rgba(30, 41, 54, 0.3);
            position: relative;
        }

        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .job-card {
            background: rgba(30, 41, 54, 0.8);
            border: 1px solid rgba(58, 69, 84, 0.5);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .job-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .job-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(14, 165, 233, 0.3);
        }

        .job-card:hover::before {
            opacity: 1;
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .job-code {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .job-slots {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .job-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .job-department {
            display: inline-block;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 0.4rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .job-details {
            margin: 1.5rem 0;
            padding: 1rem 0;
            border-top: 1px solid rgba(58, 69, 84, 0.5);
            border-bottom: 1px solid rgba(58, 69, 84, 0.5);
        }

        .job-detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .job-detail-item i {
            width: 1.2rem;
            height: 1.2rem;
            color: #0ea5e9;
        }

        .job-description {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        .job-salary {
            color: #0ea5e9;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .apply-btn {
            background: #0ea5e9;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .apply-btn:hover {
            background: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(14, 165, 233, 0.3);
        }

        .no-jobs {
            text-align: center;
            padding: 4rem;
            background: rgba(30, 41, 54, 0.6);
            border-radius: 16px;
            border: 1px solid rgba(58, 69, 84, 0.5);
            grid-column: 1 / -1;
        }

        .no-jobs i {
            width: 4rem;
            height: 4rem;
            color: #475569;
            margin-bottom: 1.5rem;
        }

        .no-jobs h3 {
            color: #ffffff;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-jobs p {
            color: #94a3b8;
            font-size: 1rem;
        }

        .job-type-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(14, 165, 233, 0.2);
            color: #0ea5e9;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(14, 165, 233, 0.3);
        }
        
        /* Features Section */
        .features-section {
            padding: 6rem 0;
            background: transparent;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-badge {
            display: inline-block;
            background: rgba(14, 165, 233, 0.1);
            border: 1px solid rgba(14, 165, 233, 0.2);
            color: #0ea5e9;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            padding: 2.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(30, 41, 54, 0.6);
            border: 1px solid rgba(58, 69, 84, 0.5);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(14, 165, 233, 0.5);
            background: rgba(30, 41, 54, 0.8);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.5rem;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: rgba(14, 165, 233, 0.2);
            transform: scale(1.1);
        }

        .feature-icon i {
            width: 2.5rem;
            height: 2.5rem;
            color: #0ea5e9;
        }
        
        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #ffffff;
        }
        
        .feature-card p {
            color: #94a3b8;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .about-section {
            padding: 6rem 0;
            background: rgba(30, 41, 54, 0.3);
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .about-text h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .about-text p {
            color: #cbd5e1;
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 2rem;
        }

        .about-features {
            display: grid;
            gap: 1rem;
        }

        .about-feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(14, 165, 233, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(14, 165, 233, 0.1);
        }

        .about-feature-item i {
            color: #0ea5e9;
            width: 1.5rem;
            height: 1.5rem;
        }

        .about-feature-item span {
            color: #cbd5e1;
            font-size: 0.95rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .top-header {
                display: none;
            }
            
            .nav-container {
                flex-direction: column;
                height: auto;
                padding: 20px;
            }
            
            .nav-links {
                margin-top: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .jobs-grid {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .about-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }
        
        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .feature-card {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <img src="assets/images/logo1.png" alt="SLATE Logo" class="loading-logo">
        <div class="loading-spinner"></div>
        <div class="loading-text">
            Loading SLATE Freight System<span class="loading-dots"></span>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="main-nav">
        <div class="nav-container">
            <div class="logo">
                <img src="assets/images/logo1.png" alt="SLATE Logo">
                <h1>SLATE</h1>
            </div>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#jobs">Jobs</a></li>
                <li><a href="#about">About</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="login-redirect.php" class="btn-primary">
                        <i data-lucide="layout-dashboard"></i>
                        Dashboard
                    </a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="#jobs" class="btn-primary">
                        <i data-lucide="briefcase"></i>
                        Apply Now
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge"> SLATE HR Management System</div>
            <h1>Recruitment to Regularization — All in One Platform</h1>
            <p>Manage the full employee lifecycle: job requisitions, applicant screening, interviews, road tests, onboarding, performance reviews, and social recognition — built for freight &amp; logistics teams.</p>
            <div class="cta-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="login-redirect.php" class="cta-button">
                        <i data-lucide="layout-dashboard"></i>
                        Access Dashboard
                    </a>
                    <a href="#jobs" class="cta-button secondary">
                        <i data-lucide="briefcase"></i>
                        View Openings
                    </a>
                <?php else: ?>
                    <a href="#jobs" class="cta-button">
                        <i data-lucide="briefcase"></i>
                        Apply Now
                    </a>
                    <a href="login.php" class="cta-button secondary">
                        <i data-lucide="log-in"></i>
                        Login
                    </a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($job_postings); ?></div>
                    <div class="stat-label">Open Positions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">5</div>
                    <div class="stat-label">User Portals</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">E2E</div>
                    <div class="stat-label">HR Workflow</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Job Listings Section -->
    <section class="jobs-section" id="jobs">
        <div class="container">
            <div class="section-header">
                <div class="section-badge"> Join Our Team</div>
                <h2 class="section-title">Current Open Positions</h2>
                <p class="section-subtitle">Browse through our available positions and start your journey with SLATE Freight Management</p>
            </div>

            <div class="jobs-grid">
                <?php if (empty($job_postings)): ?>
                    <div class="no-jobs">
                        <i data-lucide="briefcase"></i>
                        <h3>No Open Positions</h3>
                        <p>There are no job openings at the moment. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($job_postings as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <span class="job-code"><?php echo htmlspecialchars($job['job_code']); ?></span>
                                <span class="job-slots"><?php echo $job['slots_available'] - $job['slots_filled']; ?> slots left</span>
                            </div>
                            <div class="job-type-badge">
                                <?php echo str_replace('_', ' ', ucfirst($job['employment_type'] ?? 'Full Time')); ?>
                            </div>
                            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <span class="job-department">
                                <?php echo ucfirst(htmlspecialchars($job['department'])); ?>
                            </span>
                            
                            <div class="job-details">
                                <?php if (!empty($job['experience_required'])): ?>
                                <div class="job-detail-item">
                                    <i data-lucide="clock"></i>
                                    <span>Experience: <?php echo htmlspecialchars($job['experience_required']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['education_required'])): ?>
                                <div class="job-detail-item">
                                    <i data-lucide="graduation-cap"></i>
                                    <span>Education: <?php echo htmlspecialchars($job['education_required']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['location'])): ?>
                                <div class="job-detail-item">
                                    <i data-lucide="map-pin"></i>
                                    <span>Location: <?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['salary_min']) && !empty($job['salary_max'])): ?>
                                <div class="job-detail-item">
                                    <i data-lucide="wallet"></i>
                                    <span>₱<?php echo number_format($job['salary_min']); ?> - ₱<?php echo number_format($job['salary_max']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($job['description'])): ?>
                            <p class="job-description"><?php echo htmlspecialchars($job['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="job-footer">
                                <span class="job-salary">
                                    <?php if (!empty($job['salary_min']) && !empty($job['salary_max'])): ?>
                                        ₱<?php echo number_format($job['salary_min']); ?> - ₱<?php echo number_format($job['salary_max']); ?>
                                    <?php else: ?>
                                        Salary Negotiable
                                    <?php endif; ?>
                                </span>
                                <a href="apply.php?code=<?php echo htmlspecialchars($job['link_code']); ?>" class="apply-btn">
                                    <i data-lucide="send"></i>
                                    Apply Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-badge"> System Modules</div>
                <h2 class="section-title">5 Integrated Modules for Complete HR Management</h2>
                <p class="section-subtitle">From job requisitions to social recognition — every stage of the employee lifecycle in one platform</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="file-plus"></i>
                    </div>
                    <h3>Recruitment Management</h3>
                    <p>Managers submit job requisitions with budget approval. HR reviews, approves, and creates job postings from approved requests.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="filter"></i>
                    </div>
                    <h3>Applicant Management</h3>
                    <p>Screen applicants, schedule interviews, manage road tests, and track candidates through a Kanban-style recruitment pipeline.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="clipboard-check"></i>
                    </div>
                    <h3>New Hire Onboarding</h3>
                    <p>Automated account creation with company email, onboarding checklists, document submission, and requirement verification.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="target"></i>
                    </div>
                    <h3>Performance Management</h3>
                    <p>Set probationary goals, conduct 3rd and 5th month reviews, and track employee performance toward regularization.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="trophy"></i>
                    </div>
                    <h3>Social Recognition</h3>
                    <p>Auto-generated welcome posts for new hires, peer-to-peer kudos, and a public recognition wall to celebrate achievements.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="shield"></i>
                    </div>
                    <h3>Role-Based Access</h3>
                    <p>Dedicated portals for Admin, HR Staff, Managers, Employees, and Applicants with granular permission controls.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>Built for SLATE Freight Management</h2>
                    <p>A complete HR management system designed specifically for freight and logistics operations. From hiring truck drivers to tracking probationary performance, every module is tailored to the unique needs of the transportation industry.</p>
                    <div class="about-features">
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Job Requisition &rarr; Posting &rarr; Hiring Pipeline</span>
                        </div>
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Automated Company Email &amp; Employee ID Generation</span>
                        </div>
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Onboarding Checklists with Document Verification</span>
                        </div>
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Probationary Goal Setting &amp; Performance Reviews</span>
                        </div>
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Road Test Scheduling &amp; Driver License Tracking</span>
                        </div>
                        <div class="about-feature-item">
                            <i data-lucide="check-circle"></i>
                            <span>Social Recognition Wall &amp; Welcome Posts</span>
                        </div>
                    </div>
                </div>
                <div class="about-stats">
                    <div style="background: rgba(30, 41, 54, 0.6); padding: 3rem; border-radius: 16px; text-align: center; border: 1px solid rgba(58, 69, 84, 0.5); backdrop-filter: blur(10px);">
                        <h3 style="font-size: 2.5rem; color: #0ea5e9; margin-bottom: 1rem; font-weight: 800;">5 Modules</h3>
                        <p style="color: #cbd5e1; font-size: 1.1rem; margin-bottom: 2rem;">End-to-End HR Workflow</p>
                        <div style="display: grid; gap: 0.75rem; margin-top: 1.5rem; text-align: left;">
                            <div style="background: rgba(14, 165, 233, 0.1); padding: 0.85rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.25rem;">1</span>
                                <span style="color: #cbd5e1; font-size: 0.9rem;">Recruitment Management</span>
                            </div>
                            <div style="background: rgba(139, 92, 246, 0.1); padding: 0.85rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.25rem;">2</span>
                                <span style="color: #cbd5e1; font-size: 0.9rem;">Applicant Management</span>
                            </div>
                            <div style="background: rgba(16, 185, 129, 0.1); padding: 0.85rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.25rem;">3</span>
                                <span style="color: #cbd5e1; font-size: 0.9rem;">New Hire Onboarding</span>
                            </div>
                            <div style="background: rgba(245, 158, 11, 0.1); padding: 0.85rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.25rem;">4</span>
                                <span style="color: #cbd5e1; font-size: 0.9rem;">Performance Management</span>
                            </div>
                            <div style="background: rgba(236, 72, 153, 0.1); padding: 0.85rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.25rem;">5</span>
                                <span style="color: #cbd5e1; font-size: 0.9rem;">Social Recognition</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access Section -->
    <section style="padding: 6rem 0; background: transparent;">
        <div class="container">
            <div class="section-header">
                <div class="section-badge"> Quick Access</div>
                <?php if ($is_logged_in): ?>
                    <h2 class="section-title">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>!</h2>
                    <p class="section-subtitle">You are logged in as <strong style="color: #0ea5e9;"><?php echo htmlspecialchars(str_replace('_', ' ', $_SESSION['role_type'] ?? 'User')); ?></strong> — access your portal below</p>
                <?php else: ?>
                    <h2 class="section-title">Get Started Today</h2>
                    <p class="section-subtitle">Login to access your HR portal or browse our job openings</p>
                <?php endif; ?>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-top: 3rem;">
                <?php if ($is_logged_in): ?>
                    <?php
                        $role = $_SESSION['role_type'] ?? '';
                        $portal_cards = [];
                        if ($role === 'Admin') {
                            $portal_cards = [
                                ['icon' => 'shield', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)', 'title' => 'Admin Portal', 'desc' => 'System settings, user management, and audit logs', 'link' => 'views/admin/index.php'],
                                ['icon' => 'users', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'HR Staff Portal', 'desc' => 'Recruitment pipeline, screening, and onboarding', 'link' => 'views/hr_staff/index.php'],
                                ['icon' => 'briefcase', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'title' => 'Manager Portal', 'desc' => 'Job requisitions, interviews, and performance', 'link' => 'views/manager/index.php'],
                            ];
                        } elseif ($role === 'HR_Staff') {
                            $portal_cards = [
                                ['icon' => 'kanban', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'HR Dashboard', 'desc' => 'Recruitment pipeline, screening, and onboarding tracker', 'link' => 'views/hr_staff/index.php'],
                                ['icon' => 'file-plus', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'title' => 'Job Requisitions', 'desc' => 'Review and approve manager requests', 'link' => 'views/hr_staff/index.php?page=job-requisitions'],
                                ['icon' => 'briefcase', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'title' => 'Careers Page', 'desc' => 'View public job postings', 'link' => 'careers.php'],
                            ];
                        } elseif ($role === 'Manager') {
                            $portal_cards = [
                                ['icon' => 'layout-dashboard', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'title' => 'Manager Dashboard', 'desc' => 'Team overview, requisitions, and performance reviews', 'link' => 'views/manager/index.php'],
                                ['icon' => 'file-plus', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'Job Requisitions', 'desc' => 'Request new staff for your department', 'link' => 'views/manager/index.php?page=job-requisitions'],
                                ['icon' => 'target', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'title' => 'Goal Setting', 'desc' => 'Set and track probationary goals', 'link' => 'views/manager/index.php?page=goal-setting'],
                            ];
                        } elseif ($role === 'Employee') {
                            $portal_cards = [
                                ['icon' => 'layout-dashboard', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'Employee Dashboard', 'desc' => 'Onboarding progress, profile, and kudos', 'link' => 'views/employee/index.php'],
                                ['icon' => 'list-checks', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'title' => 'Onboarding', 'desc' => 'Complete your onboarding checklist', 'link' => 'views/employee/index.php?page=onboarding'],
                                ['icon' => 'trophy', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'title' => 'Recognition Wall', 'desc' => 'Give kudos and celebrate achievements', 'link' => 'views/employee/index.php?page=recognition-wall'],
                            ];
                        } elseif ($role === 'Applicant') {
                            $portal_cards = [
                                ['icon' => 'layout-dashboard', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'title' => 'Applicant Dashboard', 'desc' => 'Track your applications and interview schedule', 'link' => 'views/applicant/index.php'],
                                ['icon' => 'file-text', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'My Applications', 'desc' => 'View status of all your applications', 'link' => 'views/applicant/index.php?page=applications'],
                                ['icon' => 'briefcase', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'title' => 'Browse Jobs', 'desc' => 'Find and apply for open positions', 'link' => '#jobs'],
                            ];
                        } else {
                            $portal_cards = [
                                ['icon' => 'layout-dashboard', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'title' => 'Dashboard', 'desc' => 'Access your portal', 'link' => 'login-redirect.php'],
                            ];
                        }
                        foreach ($portal_cards as $card):
                    ?>
                    <div style="background: rgba(30, 41, 54, 0.6); padding: 2.5rem; border-radius: 16px; text-align: center; border: 1px solid rgba(58, 69, 84, 0.5); backdrop-filter: blur(10px); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.borderColor='<?php echo $card['color']; ?>'" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(58, 69, 84, 0.5)'">
                        <div style="width: 70px; height: 70px; margin: 0 auto 1.5rem; background: <?php echo $card['bg']; ?>; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="<?php echo $card['icon']; ?>" style="width: 2.5rem; height: 2.5rem; color: <?php echo $card['color']; ?>;"></i>
                        </div>
                        <h3 style="margin-bottom: 1rem; color: #ffffff; font-size: 1.3rem;"><?php echo htmlspecialchars($card['title']); ?></h3>
                        <p style="color: #94a3b8; margin-bottom: 1.5rem; line-height: 1.6;"><?php echo htmlspecialchars($card['desc']); ?></p>
                        <a href="<?php echo $card['link']; ?>" style="background: <?php echo $card['color']; ?>; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.opacity='0.85'; this.style.transform='translateY(-2px)'" onmouseout="this.style.opacity='1'; this.style.transform='translateY(0)'">Open</a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background: rgba(30, 41, 54, 0.6); padding: 2.5rem; border-radius: 16px; text-align: center; border: 1px solid rgba(58, 69, 84, 0.5); backdrop-filter: blur(10px); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.borderColor='rgba(14, 165, 233, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(58, 69, 84, 0.5)'">
                        <div style="width: 70px; height: 70px; margin: 0 auto 1.5rem; background: rgba(14, 165, 233, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="log-in" style="width: 2.5rem; height: 2.5rem; color: #0ea5e9;"></i>
                        </div>
                        <h3 style="margin-bottom: 1rem; color: #ffffff; font-size: 1.3rem;">Login</h3>
                        <p style="color: #94a3b8; margin-bottom: 1.5rem; line-height: 1.6;">Access your HR management system</p>
                        <a href="login.php" style="background: #0ea5e9; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.background='#0284c7'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#0ea5e9'; this.style.transform='translateY(0)'">Login</a>
                    </div>
                    
                    <div style="background: rgba(30, 41, 54, 0.6); padding: 2.5rem; border-radius: 16px; text-align: center; border: 1px solid rgba(58, 69, 84, 0.5); backdrop-filter: blur(10px); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.borderColor='rgba(14, 165, 233, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(58, 69, 84, 0.5)'">
                        <div style="width: 70px; height: 70px; margin: 0 auto 1.5rem; background: rgba(14, 165, 233, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="briefcase" style="width: 2.5rem; height: 2.5rem; color: #0ea5e9;"></i>
                        </div>
                        <h3 style="margin-bottom: 1rem; color: #ffffff; font-size: 1.3rem;">Apply for Jobs</h3>
                        <p style="color: #94a3b8; margin-bottom: 1.5rem; line-height: 1.6;">Browse and apply for open positions</p>
                        <a href="#jobs" style="background: #0ea5e9; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.background='#0284c7'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#0ea5e9'; this.style.transform='translateY(0)'">View Jobs</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); color: white; padding: 3rem 0; text-align: center; border-top: 1px solid rgba(58, 69, 84, 0.5);">
        <div class="container">
            <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 1rem;">&copy; 2025 SLATE Freight Management System. All rights reserved.</p>
           
        </div>
    </footer>
    
    <script>
        // Hide loading screen when page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loadingScreen = document.getElementById('loadingScreen');
                loadingScreen.classList.add('hidden');
                
                // Remove from DOM after transition
                setTimeout(function() {
                    loadingScreen.remove();
                }, 500);
            }, 800); // Show loading screen for at least 800ms
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to navigation
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('.main-nav');
            if (window.scrollY > 100) {
                nav.style.background = 'rgba(30, 41, 54, 0.95)';
                nav.style.boxShadow = '0 8px 32px rgba(0, 0, 0, 0.4)';
            } else {
                nav.style.background = 'rgba(30, 41, 54, 0.8)';
                nav.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.3)';
            }
        });
        
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>