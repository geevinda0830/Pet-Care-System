<?php
// Include header
include_once 'includes/header.php';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Here you would typically save to database or send email
        // For now, we'll just show a success message
        $success_message = "Thank you for your message! We'll get back to you within 24 hours.";
        
        // Clear form data after successful submission
        $name = $email = $phone = $subject = $message = '';
    }
}
?>

<!-- Page Header -->
<section class="page-header-modern">
    <div class="page-header-content">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="page-badge">
                        <i class="fas fa-phone-alt me-2"></i>
                        Get In Touch
                    </div>
                    <h1 class="page-title">Contact Us</h1>
                    <p class="page-subtitle">
                        Have questions about our services? Need help with your pet care needs? 
                        We're here to help you 24/7. Reach out to us anytime!
                    </p>
                </div>
                <div class="col-lg-4 text-end">
                    <div class="page-stats">
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support Available</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Information Section -->
<section class="contact-info-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>Visit Our Office</h4>
                    <p>
                        123 Pet Care Avenue<br>
                        Colombo 03, Sri Lanka<br>
                        Open: Mon-Sat 9AM-6PM
                    </p>
                    <a href="#" class="contact-link">Get Directions</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4>Call Us</h4>
                    <p>
                        <strong>Customer Service:</strong><br>
                        +94 11 234 5678<br>
                        <strong>Emergency Line:</strong><br>
                        +94 77 234 5678
                    </p>
                    <a href="tel:+94112345678" class="contact-link">Call Now</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4>Email Us</h4>
                    <p>
                        <strong>General Inquiries:</strong><br>
                        info@petcaresystem.lk<br>
                        <strong>Support:</strong><br>
                        support@petcaresystem.lk
                    </p>
                    <a href="mailto:info@petcaresystem.lk" class="contact-link">Send Email</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="contact-form-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="form-header text-center mb-5">
                    <h2 class="section-title">Send Us a Message</h2>
                    <p class="section-subtitle">
                        Fill out the form below and we'll get back to you as soon as possible
                    </p>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-modern">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-modern">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="contact-form-card">
                    <form method="POST" action="" class="contact-form">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control-modern" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                               placeholder="Enter your full name" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" 
                                               class="form-control-modern" 
                                               id="email" 
                                               name="email" 
                                               value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                               placeholder="Enter your email address" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" 
                                               class="form-control-modern" 
                                               id="phone" 
                                               name="phone" 
                                               value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                                               placeholder="Enter your phone number">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-tag"></i>
                                        </span>
                                        <select class="form-control-modern" id="subject" name="subject" required>
                                            <option value="">Select a subject</option>
                                            <option value="General Inquiry" <?php echo (($subject ?? '') == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                            <option value="Pet Sitting Services" <?php echo (($subject ?? '') == 'Pet Sitting Services') ? 'selected' : ''; ?>>Pet Sitting Services</option>
                                            <option value="Product Questions" <?php echo (($subject ?? '') == 'Product Questions') ? 'selected' : ''; ?>>Product Questions</option>
                                            <option value="Technical Support" <?php echo (($subject ?? '') == 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                            <option value="Feedback" <?php echo (($subject ?? '') == 'Feedback') ? 'selected' : ''; ?>>Feedback</option>
                                            <option value="Partnership" <?php echo (($subject ?? '') == 'Partnership') ? 'selected' : ''; ?>>Partnership Opportunities</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="message" class="form-label">Message *</label>
                                    <div class="input-group">
                                        <span class="input-icon textarea-icon">
                                            <i class="fas fa-comment"></i>
                                        </span>
                                        <textarea class="form-control-modern textarea-modern" 
                                                  id="message" 
                                                  name="message" 
                                                  rows="6" 
                                                  placeholder="Please provide details about your inquiry..."
                                                  required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group text-center">
                                    <button type="submit" class="btn btn-primary btn-lg btn-submit">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Send Message
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">Quick answers to common questions</p>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I book a pet sitter?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Simply browse our verified pet sitters, check their profiles and availability, 
                                then send a booking request. Once confirmed, you're all set!
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What areas do you deliver to?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We deliver island-wide across Sri Lanka. Free delivery is available for orders 
                                above Rs. 2,500 within Colombo and suburbs.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Are all pet sitters background checked?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! All our pet sitters undergo thorough background checks, reference verification, 
                                and training before joining our platform.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                What if I'm not satisfied with a product?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We offer a 30-day return policy for most products. If you're not completely satisfied, 
                                contact our support team for a hassle-free return or exchange.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="map-section">
    <div class="container-fluid px-0">
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.798467128636!2d79.84115831477063!3d6.921830095010785!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a7003%3A0x320b2e4d32d3838d!2sColombo%2C%20Sri%20Lanka!5e0!3m2!1sen!2s!4v1635000000000!5m2!1sen!2s" 
                    width="100%" 
                    height="400" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
            </iframe>
            <div class="map-overlay">
                <div class="map-info">
                    <h4>Visit Our Office</h4>
                    <p>123 Pet Care Avenue, Colombo 03</p>
                    <a href="#" class="btn btn-primary btn-sm">Get Directions</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Page Header */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 100px 0;
    position: relative;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.page-header-content {
    position: relative;
    z-index: 2;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    display: inline-block;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
    line-height: 1.6;
}

.page-stats {
    text-align: center;
}

.page-stats .stat-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 16px;
    backdrop-filter: blur(10px);
}

.page-stats .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.page-stats .stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Contact Info Section */
.contact-info-section {
    padding: 80px 0;
    background: white;
    margin-top: -50px;
    position: relative;
    z-index: 2;
}

.contact-card {
    background: white;
    padding: 40px 32px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.contact-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.contact-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    color: white;
    font-size: 2rem;
}

.contact-card h4 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 16px;
}

.contact-card p {
    color: #718096;
    line-height: 1.6;
    margin-bottom: 20px;
}

.contact-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.contact-link:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Contact Form Section */
.contact-form-section {
    padding: 100px 0;
    background: #f8f9ff;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 16px;
}

.section-subtitle {
    font-size: 1.2rem;
    color: #718096;
    margin-bottom: 0;
}

.contact-form-card {
    background: white;
    padding: 48px;
    border-radius: 20px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    display: block;
}

.input-group {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    z-index: 2;
    font-size: 1.1rem;
}

.textarea-icon {
    top: 20px;
    transform: none;
}

.form-control-modern {
    width: 100%;
    padding: 16px 16px 16px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    font-family: inherit;
    background: rgba(255, 255, 255, 0.8);
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
    outline: none;
}

.textarea-modern {
    padding-left: 48px;
    padding-top: 16px;
    min-height: 150px;
    resize: vertical;
}

select.form-control-modern {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 16px;
    padding-right: 40px;
}

.btn-submit {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 16px 40px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.1rem;
    color: white;
    transition: all 0.3s ease;
    min-width: 200px;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

/* Alert Styles */
.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    font-weight: 500;
    margin-bottom: 24px;
}

.alert-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

/* FAQ Section */
.faq-section {
    padding: 100px 0;
    background: white;
}

.accordion-item {
    border: none;
    margin-bottom: 16px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.accordion-button {
    background: white;
    border: none;
    padding: 20px 24px;
    font-weight: 600;
    color: #2d3748;
    font-size: 1.1rem;
}

.accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
    border: none;
}

.accordion-body {
    padding: 0 24px 24px;
    color: #718096;
    line-height: 1.6;
}

/* Map Section */
.map-section {
    position: relative;
}

.map-container {
    position: relative;
}

.map-overlay {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    max-width: 300px;
}

.map-info h4 {
    color: #2d3748;
    margin-bottom: 8px;
    font-weight: 600;
}

.map-info p {
    color: #718096;
    margin-bottom: 16px;
}

/* Animations */
@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-20px) rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .contact-form-card {
        padding: 32px 24px;
    }
    
    .map-overlay {
        position: static;
        margin: 20px;
        max-width: none;
    }
    
    .contact-card {
        margin-bottom: 20px;
    }
}

@media (max-width: 576px) {
    .page-title {
        font-size: 2rem;
    }
    
    .contact-form-card {
        padding: 24px 16px;
    }
    
    .btn-submit {
        min-width: auto;
        width: 100%;
    }
}
</style>

<?php
// Include footer
include_once 'includes/footer.php';
?>