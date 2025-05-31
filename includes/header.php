<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Base URL - update with your project path
$base_url = "http://localhost/pet_care_system";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care & Sitting System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base_url; ?>/assets/images/favicon.ico" type="image/x-icon">
    
    <style>
        /* Modern Navigation Styles */
        .navbar-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 16px 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
        }
        
        .navbar-brand-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.4rem;
            color: white !important;
            text-decoration: none;
        }
        
        .brand-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .nav-link-modern {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 8px 16px !important;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0 4px;
        }
        
        .nav-link-modern:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .nav-link-modern.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }
        
        .dropdown-menu-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 8px;
        }
        
        .dropdown-item-modern {
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.3s ease;
            color: #374151;
            font-weight: 500;
        }
        
        .dropdown-item-modern:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .cart-badge {
            position: relative;
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 1.2rem;
            padding: 8px 12px !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .cart-badge:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .cart-count {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
            min-width: 20px;
            height: 20px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        
        .alert-modern {
            border: none;
            border-radius: 12px;
            margin-bottom: 24px;
            backdrop-filter: blur(20px);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .main-content {
            min-height: calc(100vh - 200px);
        }
        
        @media (max-width: 991px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                border-radius: 12px;
                margin-top: 16px;
                padding: 16px;
            }
            
            .nav-link-modern {
                margin: 4px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container">
            <a class="navbar-brand-modern" href="<?php echo $base_url; ?>/index.php">
                <div class="brand-icon">
                    <i class="fas fa-paw"></i>
                </div>
                Pet Care & Sitting
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link-modern" href="<?php echo $base_url; ?>/index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-modern" href="<?php echo $base_url; ?>/pet_sitters.php">
                            <i class="fas fa-user-friends me-1"></i> Pet Sitters
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-modern" href="<?php echo $base_url; ?>/shop.php">
                            <i class="fas fa-store me-1"></i> Pet Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-modern" href="<?php echo $base_url; ?>/about.php">
                            <i class="fas fa-info-circle me-1"></i> About
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-modern" href="<?php echo $base_url; ?>/contact.php">
                            <i class="fas fa-envelope me-1"></i> Contact
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['user_type'] === 'pet_owner'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link-modern dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <?php echo $_SESSION['username']; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/pets.php">
                                        <i class="fas fa-paw me-2"></i> My Pets
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/bookings.php">
                                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/orders.php">
                                        <i class="fas fa-shopping-bag me-2"></i> My Orders
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item">
                                <a class="cart-badge" href="<?php echo $base_url; ?>/cart.php">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span class="cart-count">0</span>
                                </a>
                            </li>
                            
                        <?php elseif($_SESSION['user_type'] === 'pet_sitter'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link-modern dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar">
                                        <i class="fas fa-hands-helping"></i>
                                    </div>
                                    <?php echo $_SESSION['username']; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/bookings.php">
                                        <i class="fas fa-calendar-alt me-2"></i> My Bookings
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/services.php">
                                        <i class="fas fa-clipboard-list me-2"></i> My Services
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a></li>
                                </ul>
                            </li>
                            
                        <?php elseif($_SESSION['user_type'] === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link-modern dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    Admin
                                </a>
                                <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/admin/users.php">
                                        <i class="fas fa-users me-2"></i> Users
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link-modern" href="<?php echo $base_url; ?>/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link-modern" href="<?php echo $base_url; ?>/register.php">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="main-content">
        <div class="container-fluid px-0">
            <?php
            // Display flash messages if any
            if(isset($_SESSION['success_message'])): ?>
                <div class="container mt-4">
                    <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="container mt-4">
                    <div class="alert alert-danger alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['info_message'])): ?>
                <div class="container mt-4">
                    <div class="alert alert-info alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo $_SESSION['info_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['info_message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['warning_message'])): ?>
                <div class="container mt-4">
                    <div class="alert alert-warning alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['warning_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>