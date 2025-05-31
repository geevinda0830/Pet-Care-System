</main>

    <!-- Modern Footer -->
    <footer class="footer-modern">
        <div class="footer-background">
            <div class="footer-particles"></div>
        </div>
        
        <div class="container">
            <!-- Main Footer Content -->
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <div class="brand-logo-footer">
                            <div class="brand-icon-footer">
                                <i class="fas fa-paw"></i>
                            </div>
                            <h4>Pet Care & Sitting</h4>
                        </div>
                        <p class="brand-description">
                            Your trusted partner for all pet care needs. We provide reliable pet sitting services and quality pet products to ensure your pets stay happy and healthy.
                        </p>
                        
                        <div class="social-links">
                            <a href="#" class="social-link facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link linkedin">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Quick Links</h5>
                        <ul class="footer-links">
                            <li><a href="<?php echo $base_url; ?>/index.php">
                                <i class="fas fa-home me-2"></i> Home
                            </a></li>
                            <li><a href="<?php echo $base_url; ?>/pet_sitters.php">
                                <i class="fas fa-user-friends me-2"></i> Pet Sitters
                            </a></li>
                            <li><a href="<?php echo $base_url; ?>/shop.php">
                                <i class="fas fa-store me-2"></i> Pet Shop
                            </a></li>
                            <li><a href="<?php echo $base_url; ?>/about.php">
                                <i class="fas fa-info-circle me-2"></i> About Us
                            </a></li>
                            <li><a href="<?php echo $base_url; ?>/contact.php">
                                <i class="fas fa-envelope me-2"></i> Contact
                            </a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Services</h5>
                        <ul class="footer-links">
                            <li><a href="#">
                                <i class="fas fa-home me-2"></i> Pet Sitting
                            </a></li>
                            <li><a href="#">
                                <i class="fas fa-walking me-2"></i> Dog Walking
                            </a></li>
                            <li><a href="#">
                                <i class="fas fa-cut me-2"></i> Pet Grooming
                            </a></li>
                            <li><a href="#">
                                <i class="fas fa-graduation-cap me-2"></i> Pet Training
                            </a></li>
                            <li><a href="#">
                                <i class="fas fa-pills me-2"></i> Pet Healthcare
                            </a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Contact Info</h5>
                        
                        <div class="contact-info">
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-details">
                                    <p>123 Pet Street, Colombo 07, Sri Lanka</p>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="contact-details">
                                    <p>+94 123 456 789</p>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-details">
                                    <p>info@petcare.lk</p>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="contact-details">
                                    <p>Mon - Sun: 24/7 Service</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Newsletter Subscription -->
            <div class="newsletter-section">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="newsletter-content">
                            <h5>🐾 Stay Updated!</h5>
                            <p>Get the latest pet care tips, special offers, and updates delivered to your inbox.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <form class="newsletter-form">
                            <div class="input-group">
                                <input type="email" class="form-control newsletter-input" placeholder="Enter your email address" required>
                                <button class="btn newsletter-btn" type="submit">
                                    <i class="fas fa-paper-plane me-1"></i> Subscribe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="copyright">
                            &copy; <?php echo date('Y'); ?> Pet Care & Sitting System. All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="footer-bottom-links">
                            <a href="#">Privacy Policy</a>
                            <a href="#">Terms of Service</a>
                            <a href="#">Cookie Policy</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="<?php echo $base_url; ?>/assets/js/main.js"></script>
    
    <style>
        .footer-modern {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            position: relative;
            overflow: hidden;
            padding: 80px 0 0;
            margin-top: 100px;
        }
        
        .footer-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }
        
        .footer-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="30" r="0.8" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: floatParticles 30s infinite linear;
        }
        
        @keyframes floatParticles {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }
        
        .footer-brand {
            position: relative;
            z-index: 2;
        }
        
        .brand-logo-footer {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .brand-icon-footer {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        
        .brand-logo-footer h4 {
            color: white;
            margin: 0;
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .brand-description {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            margin-bottom: 32px;
            font-size: 1rem;
        }
        
        .social-links {
            display: flex;
            gap: 16px;
        }
        
        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .social-link.facebook { background: linear-gradient(135deg, #4267B2, #3b5998); }
        .social-link.twitter { background: linear-gradient(135deg, #1DA1F2, #0d8bd9); }
        .social-link.instagram { background: linear-gradient(135deg, #E4405F, #833AB4); }
        .social-link.linkedin { background: linear-gradient(135deg, #0077B5, #0e6b96); }
        
        .social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }
        
        .footer-section {
            position: relative;
            z-index: 2;
        }
        
        .footer-title {
            color: white;
            font-weight: 700;
            margin-bottom: 24px;
            font-size: 1.2rem;
            position: relative;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 30px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-links a i {
            color: #667eea;
            width: 20px;
        }
        
        .contact-info {
            margin-top: 24px;
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            flex-shrink: 0;
        }
        
        .contact-details p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            line-height: 1.5;
        }
        
        .newsletter-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            margin: 60px 0 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 2;
        }
        
        .newsletter-content h5 {
            color: white;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.3rem;
        }
        
        .newsletter-content p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 1rem;
        }
        
        .newsletter-form .input-group {
            max-width: 400px;
            margin-left: auto;
        }
        
        .newsletter-input {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid transparent;
            border-radius: 12px 0 0 12px;
            padding: 12px 16px;
            font-size: 1rem;
        }
        
        .newsletter-input:focus {
            background: white;
            border-color: #667eea;
            box-shadow: none;
        }
        
        .newsletter-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 0 12px 12px 0;
            transition: all 0.3s ease;
        }
        
        .newsletter-btn:hover {
            background: linear-gradient(135deg, #5a6fd8, #6b4190);
            transform: translateY(-1px);
            color: white;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 0;
            margin-top: 20px;
            position: relative;
            z-index: 2;
        }
        
        .copyright {
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            font-size: 0.9rem;
        }
        
        .footer-bottom-links {
            display: flex;
            gap: 24px;
            justify-content: flex-end;
        }
        
        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.9rem;
        }
        
        .footer-bottom-links a:hover {
            color: white;
        }
        
        @media (max-width: 991px) {
            .footer-modern {
                padding: 60px 0 0;
                margin-top: 60px;
            }
            
            .newsletter-section {
                padding: 30px 20px;
                margin: 40px 0 30px;
                text-align: center;
            }
            
            .newsletter-form .input-group {
                max-width: 100%;
                margin: 20px 0 0;
            }
            
            .footer-bottom-links {
                justify-content: center;
                margin-top: 16px;
            }
            
            .social-links {
                justify-content: center;
                margin-top: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .newsletter-form .input-group {
                flex-direction: column;
            }
            
            .newsletter-input {
                border-radius: 12px;
                margin-bottom: 12px;
            }
            
            .newsletter-btn {
                border-radius: 12px;
                width: 100%;
            }
            
            .footer-bottom-links {
                flex-direction: column;
                gap: 12px;
                align-items: center;
            }
        }
    </style>
</body>
</html>