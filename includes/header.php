<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Base URL - update with your project path
$base_url = "http://localhost/pet_care_system";

// Get cart count for pet owners
$cart_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'pet_owner') {
    require_once (strpos(__FILE__, 'user/') !== false ? '../' : '') . 'config/db_connect.php';
    
    $cart_count_sql = "SELECT COALESCE(SUM(ci.quantity), 0) as total_items 
                       FROM cart c 
                       LEFT JOIN cart_items ci ON c.cartID = ci.cartID 
                       WHERE c.userID = ? AND c.orderID IS NULL";
    $cart_count_stmt = $conn->prepare($cart_count_sql);
    if ($cart_count_stmt) {
        $cart_count_stmt->bind_param("i", $_SESSION['user_id']);
        $cart_count_stmt->execute();
        $cart_result = $cart_count_stmt->get_result();
        $cart_count = $cart_result->fetch_assoc()['total_items'] ?? 0;
        $cart_count_stmt->close();
    }
}
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
    <!-- Custom CSS - FIXED: Uncommented -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    
    <style>
        /* Modern Navigation Styles */
        .navbar-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            position: sticky;
            top: 0;
            z-index: 1050;
        }
        
        .navbar-brand-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.5rem;
            color: white !important;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .navbar-brand-modern:hover {
            transform: scale(1.02);
            color: white !important;
        }
        
       .brand-icon {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center; /* FIXED: Added missing value 'center' */
    font-size: 1.3rem;
    transition: all 0.3s ease;
}
        
        .navbar-brand-modern:hover .brand-icon {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(10deg);
        }
        
        .nav-link-modern {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 10px 18px !important;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin: 0 4px;
            position: relative;
            text-decoration: none;
        }
        
        .nav-link-modern::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link-modern:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .nav-link-modern:hover::before {
            width: 80%;
        }
        
        .nav-link-modern.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }
        
        .nav-link-modern.active::before {
            width: 80%;
        }

        /* Cart Badge Styles */
        .cart-link {
            position: relative;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            animation: cartPulse 0.5s ease-in-out;
        }

        @keyframes cartPulse {
            0% { transform: scale(0.8); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .nav-link-modern:hover .cart-badge {
            background: #dc2626;
        }
        
        /* FIXED: Dropdown Menu Styles */
        .dropdown-menu-modern {
            background: white !important;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
            padding: 8px !important;
            z-index: 9999 !important;
            min-width: 200px;
            margin-top: 8px !important;
        }
        
        .navbar-nav .dropdown {
            position: relative;
        }
        
        .dropdown-item-modern {
            padding: 12px 20px !important;
            border-radius: 8px !important;
            color: #333 !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
        }
        
        .dropdown-item-modern:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
        }
        
        .dropdown-item-modern:focus,
        .dropdown-item-modern:active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        
        .navbar-toggler {
            border: none;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 8px 12px;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        /* Alert Styles */
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
        }
        
        /* Main Content Styling */
        .main-content {
            flex: 1;
        }
        
        /* Mobile Responsive */
        @media (max-width: 991.98px) {
            .navbar-modern {
                padding: 15px 0;
            }
            
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                border-radius: 12px;
                padding: 20px;
                margin-top: 20px;
            }
            
            .nav-link-modern {
                margin: 4px 0;
                text-align: center;
            }
            
            .dropdown-menu-modern {
                background: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
            }
            
            .dropdown-item-modern {
                color: #333 !important;
            }
            
            .dropdown-item-modern:hover {
                background: rgba(102, 126, 234, 0.2) !important;
                color: #333 !important;
            }

            /* Mobile responsive cart badge */
            .cart-badge {
                top: -5px;
                right: -5px;
                width: 18px;
                height: 18px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container">
            <a class="navbar-brand-modern" href="<?php echo $base_url; ?>/index.php">
                <div class="brand-icon">
                    <i class="fas fa-paw"></i>
                </div>
                PetCare System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <?php if(isset($_SESSION['user_id']) && isset($_SESSION['user_type'])): ?>
                        <?php if($_SESSION['user_type'] === 'pet_owner'): ?>
                            <!-- Cart for Pet Owners -->
                            <li class="nav-item position-relative">
                                <a class="nav-link-modern cart-link" href="<?php echo $base_url; ?>/cart.php">
                                    <i class="fas fa-shopping-cart me-1"></i> Cart
                                    <?php if ($cart_count > 0): ?>
                                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link-modern dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-modern dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/pets.php">
                                        <i class="fas fa-paw"></i> My Pets
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/bookings.php">
                                        <i class="fas fa-calendar-alt"></i> My Bookings
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/user/orders.php">
                                        <i class="fas fa-shopping-bag"></i> My Orders
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a></li>
                                </ul>
                            </li>
                        <?php elseif($_SESSION['user_type'] === 'pet_sitter'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link-modern dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Sitter'; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-modern dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/bookings.php">
                                        <i class="fas fa-calendar-alt"></i> My Bookings
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/pet_sitter/profile.php">
                                        <i class="fas fa-user-edit"></i> Profile
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
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
                                <ul class="dropdown-menu dropdown-menu-modern dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/admin/users.php">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/admin/manage_products.php">
                                        <i class="fas fa-box"></i> Manage Products
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item dropdown-item-modern" href="<?php echo $base_url; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
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
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>