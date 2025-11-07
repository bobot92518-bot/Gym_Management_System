<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FITNESS HUB - Your Fitness Journey Starts Here</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--dark-color) 0%, #34495e 100%);
            color: white;
            padding: 100px 0;
            min-height: 80vh;
            display: flex;
            align-items: center;
        }

        .hero-section .container {
            position: relative;
            z-index: 2;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-hero {
            background: var(--primary-color);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-hero:hover {
            background: #e55a5a;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
            color: white;
        }

        .features-section {
            padding: 80px 0;
            background: var(--light-color);
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .about-section {
            padding: 80px 0;
            background: white;
        }

        .about-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #666;
        }

        .stats-section {
            padding: 60px 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .contact-section {
            padding: 80px 0;
            background: var(--light-color);
        }

        .contact-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .footer {
            background: var(--dark-color);
            color: white;
            padding: 40px 0 20px;
        }

        .footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }

        .footer a:hover {
            color: white;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dumbbell me-2"></i>FITNESS HUB
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="member/member_landing.php">Member Portal</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Transform Your Body, Transform Your Life</h1>
                    <p class="hero-subtitle">Join FITNESS HUB and embark on your fitness journey with state-of-the-art equipment, expert trainers, and a supportive community.</p>
                    <a href="member/member_landing.php" class="btn btn-hero me-3">
                        <i class="fas fa-play me-2"></i>Get Started
                    </a>
                    <a href="#features" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-info-circle me-2"></i>Learn More
                    </a>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-dumbbell display-1 text-primary"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-4 fw-bold text-dark">Why Choose FITNESS HUB?</h2>
                    <p class="lead text-muted">Experience the difference with our comprehensive fitness solutions</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-dumbbell feature-icon"></i>
                        <h3 class="feature-title">Modern Equipment</h3>
                        <p class="text-muted">State-of-the-art fitness equipment from leading brands to help you achieve your goals.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-user-friends feature-icon"></i>
                        <h3 class="feature-title">Expert Trainers</h3>
                        <p class="text-muted">Certified personal trainers ready to guide you through personalized workout programs.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-clock feature-icon"></i>
                        <h3 class="feature-title">24/7 Access</h3>
                        <p class="text-muted">Round-the-clock access to our facilities with secure entry systems and monitoring.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-heartbeat feature-icon"></i>
                        <h3 class="feature-title">Health Monitoring</h3>
                        <p class="text-muted">Track your progress with our integrated health and fitness monitoring systems.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-users feature-icon"></i>
                        <h3 class="feature-title">Community</h3>
                        <p class="text-muted">Join a supportive community of fitness enthusiasts and make lasting connections.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-shower feature-icon"></i>
                        <h3 class="feature-title">Premium Amenities</h3>
                        <p class="text-muted">Enjoy locker rooms, showers, sauna, and other premium facilities for your comfort.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Active Members</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Equipment Types</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Expert Trainers</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Access Hours</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="display-4 fw-bold text-dark mb-4">About FITNESS HUB</h2>
                    <div class="about-content">
                        <p>FITNESS HUB has been serving the community for over a decade, providing top-quality fitness facilities and programs designed to help individuals of all fitness levels achieve their health and wellness goals.</p>
                        <p>Our mission is to create a welcoming environment where everyone feels empowered to take control of their fitness journey. Whether you're a beginner or an experienced athlete, our team is here to support you every step of the way.</p>
                        <p>We believe that fitness is not just about physical strength, but also about building mental resilience, fostering community connections, and creating sustainable healthy habits that last a lifetime.</p>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-users display-1 text-primary"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-4 fw-bold text-dark">Get In Touch</h2>
                    <p class="lead text-muted">Ready to start your fitness journey? Contact us today!</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-card">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="mb-4">Visit Us</h4>
                                <p><i class="fas fa-map-marker-alt me-3 text-primary"></i>123 Fitness Street<br>Health City, HC 12345</p>
                                <p><i class="fas fa-phone me-3 text-primary"></i>(555) 123-4567</p>
                                <p><i class="fas fa-envelope me-3 text-primary"></i>info@fitzonegym.com</p>
                                <p><i class="fas fa-clock me-3 text-primary"></i>Mon-Sun: 24/7</p>
                            </div>
                            <div class="col-md-6">
                                <h4 class="mb-4">Quick Actions</h4>
                                <a href="member/member_landing.php" class="btn btn-primary btn-lg w-100 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Join Now
                                </a>
                                <a href="login.php" class="btn btn-outline-primary btn-lg w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Staff Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>FITNESS HUB</h5>
                    <p>Your trusted partner in fitness and wellness. Join our community and transform your life today.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Follow Us</h5>
                    <div class="social-links">
                        <a href="#" class="me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2024 FITNESS HUB. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('bg-dark');
                navbar.classList.remove('bg-transparent');
            } else {
                navbar.classList.add('bg-transparent');
                navbar.classList.remove('bg-dark');
            }
        });
    </script>
</body>
</html>
