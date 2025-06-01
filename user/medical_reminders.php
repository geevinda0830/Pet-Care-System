<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Process reminder actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_completed' && isset($_POST['reminder_id'])) {
            $reminder_id = $_POST['reminder_id'];
            
            // Update reminder status
            $update_sql = "UPDATE pet_medical_reminders SET status = 'completed' WHERE reminderID = ? AND petID IN (SELECT petID FROM pet_profile WHERE userID = ?)";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $reminder_id, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Reminder marked as completed!";
            } else {
                $_SESSION['error_message'] = "Error updating reminder.";
            }
            $update_stmt->close();
        }
        
        if ($_POST['action'] === 'snooze' && isset($_POST['reminder_id']) && isset($_POST['snooze_days'])) {
            $reminder_id = $_POST['reminder_id'];
            $snooze_days = (int)$_POST['snooze_days'];
            
            // Update reminder date
            $snooze_sql = "UPDATE pet_medical_reminders SET reminder_date = DATE_ADD(reminder_date, INTERVAL ? DAY) WHERE reminderID = ? AND petID IN (SELECT petID FROM pet_profile WHERE userID = ?)";
            $snooze_stmt = $conn->prepare($snooze_sql);
            $snooze_stmt->bind_param("iii", $snooze_days, $reminder_id, $user_id);
            
            if ($snooze_stmt->execute()) {
                $_SESSION['success_message'] = "Reminder snoozed for $snooze_days days!";
            } else {
                $_SESSION['error_message'] = "Error snoozing reminder.";
            }
            $snooze_stmt->close();
        }
        
        header("Location: medical_reminders.php");
        exit();
    }
}

// Get all pets for this user
$pets_sql = "SELECT petID, petName, image FROM pet_profile WHERE userID = ? ORDER BY petName ASC";
$pets_stmt = $conn->prepare($pets_sql);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = [];
while ($row = $pets_result->fetch_assoc()) {
    $pets[] = $row;
}
$pets_stmt->close();

// Get medical reminders
$reminders_sql = "SELECT r.*, p.petName, p.image as petImage 
                  FROM pet_medical_reminders r 
                  JOIN pet_profile p ON r.petID = p.petID 
                  WHERE p.userID = ? 
                  ORDER BY 
                    CASE 
                        WHEN r.status = 'overdue' THEN 1
                        WHEN r.status = 'pending' AND r.reminder_date <= CURDATE() THEN 2
                        WHEN r.status = 'pending' THEN 3
                        ELSE 4
                    END,
                    r.due_date ASC";
$reminders_stmt = $conn->prepare($reminders_sql);
$reminders_stmt->bind_param("i", $user_id);
$reminders_stmt->execute();
$reminders_result = $reminders_stmt->get_result();
$reminders = [];

while ($row = $reminders_result->fetch_assoc()) {
    // Update status based on dates
    $today = date('Y-m-d');
    if ($row['status'] === 'pending') {
        if ($row['due_date'] < $today) {
            $row['status'] = 'overdue';
            // Update in database
            $update_status_sql = "UPDATE pet_medical_reminders SET status = 'overdue' WHERE reminderID = ?";
            $update_status_stmt = $conn->prepare($update_status_sql);
            $update_status_stmt->bind_param("i", $row['reminderID']);
            $update_status_stmt->execute();
            $update_status_stmt->close();
        }
    }
    $reminders[] = $row;
}
$reminders_stmt->close();

// Calculate statistics
$total_reminders = count($reminders);
$overdue_reminders = count(array_filter($reminders, function($r) { return $r['status'] === 'overdue'; }));
$due_today = count(array_filter($reminders, function($r) { return $r['reminder_date'] === date('Y-m-d') && $r['status'] === 'pending'; }));
$upcoming_week = count(array_filter($reminders, function($r) { 
    return $r['status'] === 'pending' && 
           $r['reminder_date'] > date('Y-m-d') && 
           $r['reminder_date'] <= date('Y-m-d', strtotime('+7 days')); 
}));

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">⏰ Medical Reminders</span>
                    <h1 class="page-title">Health <span class="text-gradient">Reminders</span></h1>
                    <p class="page-subtitle">Stay on top of your pets' medical care schedule</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="add_reminder.php" class="btn btn-primary-gradient">
                        <i class="fas fa-plus me-2"></i> Add Reminder
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reminder Statistics -->
<section class="stats-section-modern">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $overdue_reminders; ?></h3>
                        <p class="stat-label">Overdue</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern warning">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $due_today; ?></h3>
                        <p class="stat-label">Due Today</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern info">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $upcoming_week; ?></h3>
                        <p class="stat-label">This Week</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern success">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $total_reminders; ?></h3>
                        <p class="stat-label">Total Active</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Actions -->
<section class="quick-actions-section">
    <div class="container">
        <div class="quick-actions-card">
            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            <div class="actions-grid">
                <a href="add_reminder.php" class="action-item">
                    <div class="action-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <span>Add Reminder</span>
                </a>
                <a href="#" class="action-item" onclick="scheduleVaccination()">
                    <div class="action-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <span>Schedule Vaccination</span>
                </a>
                <a href="#" class="action-item" onclick="scheduleCheckup()">
                    <div class="action-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <span>Schedule Checkup</span>
                </a>
                <a href="#" class="action-item" onclick="viewCalendar()">
                    <div class="action-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <span>View Calendar</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Reminders Content -->
<section class="reminders-section-modern">
    <div class="container">
        <?php if (empty($reminders)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h4>No Medical Reminders</h4>
                <p>Set up reminders to keep track of your pets' medical care schedule.</p>
                <a href="add_reminder.php" class="btn btn-primary-gradient">
                    <i class="fas fa-plus me-2"></i> Add First Reminder
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Medical Reminders <span class="count-badge"><?php echo count($reminders); ?> active</span></h3>
            </div>
            
            <!-- Overdue Reminders -->
            <?php
            $overdue_items = array_filter($reminders, function($r) { return $r['status'] === 'overdue'; });
            if (!empty($overdue_items)):
            ?>
                <div class="reminder-section">
                    <div class="section-header urgent">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Overdue Reminders</h5>
                        <span class="urgent-badge"><?php echo count($overdue_items); ?> overdue</span>
                    </div>
                    
                    <div class="reminders-grid">
                        <?php foreach ($overdue_items as $reminder): ?>
                            <div class="reminder-card overdue">
                                <div class="reminder-header">
                                    <div class="pet-info">
                                        <div class="pet-avatar">
                                            <?php if (!empty($reminder['petImage'])): ?>
                                                <img src="../assets/images/pets/<?php echo htmlspecialchars($reminder['petImage']); ?>" alt="<?php echo htmlspecialchars($reminder['petName']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <i class="fas fa-paw"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-details">
                                            <h6><?php echo htmlspecialchars($reminder['petName']); ?></h6>
                                            <span class="reminder-type"><?php echo htmlspecialchars($reminder['reminder_type']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="status-badge overdue">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Overdue
                                    </div>
                                </div>
                                
                                <div class="reminder-content">
                                    <h6><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                    <?php if (!empty($reminder['description'])): ?>
                                        <p><?php echo htmlspecialchars($reminder['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="reminder-dates">
                                    <div class="date-item">
                                        <span class="label">Due Date:</span>
                                        <span class="value overdue"><?php echo date('M d, Y', strtotime($reminder['due_date'])); ?></span>
                                    </div>
                                    <div class="days-overdue">
                                        <?php 
                                        $days_overdue = floor((strtotime(date('Y-m-d')) - strtotime($reminder['due_date'])) / (60 * 60 * 24));
                                        echo $days_overdue . ' day' . ($days_overdue != 1 ? 's' : '') . ' overdue';
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="reminder-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_completed">
                                        <input type="hidden" name="reminder_id" value="<?php echo $reminder['reminderID']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i> Mark Complete
                                        </button>
                                    </form>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-outline-warning btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-clock me-1"></i> Snooze
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php foreach ([1, 3, 7, 14] as $days): ?>
                                                <li>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="snooze">
                                                        <input type="hidden" name="reminder_id" value="<?php echo $reminder['reminderID']; ?>">
                                                        <input type="hidden" name="snooze_days" value="<?php echo $days; ?>">
                                                        <button type="submit" class="dropdown-item"><?php echo $days; ?> day<?php echo $days != 1 ? 's' : ''; ?></button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Due Today -->
            <?php
            $due_today_items = array_filter($reminders, function($r) { 
                return $r['status'] === 'pending' && $r['reminder_date'] === date('Y-m-d'); 
            });
            if (!empty($due_today_items)):
            ?>
                <div class="reminder-section">
                    <div class="section-header warning">
                        <h5><i class="fas fa-bell me-2"></i>Due Today</h5>
                        <span class="warning-badge"><?php echo count($due_today_items); ?> due</span>
                    </div>
                    
                    <div class="reminders-grid">
                        <?php foreach ($due_today_items as $reminder): ?>
                            <?php include 'reminder_card_template.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Upcoming This Week -->
            <?php
            $upcoming_items = array_filter($reminders, function($r) { 
                return $r['status'] === 'pending' && 
                       $r['reminder_date'] > date('Y-m-d') && 
                       $r['reminder_date'] <= date('Y-m-d', strtotime('+7 days')); 
            });
            if (!empty($upcoming_items)):
            ?>
                <div class="reminder-section">
                    <div class="section-header info">
                        <h5><i class="fas fa-calendar-week me-2"></i>Upcoming This Week</h5>
                        <span class="info-badge"><?php echo count($upcoming_items); ?> upcoming</span>
                    </div>
                    
                    <div class="reminders-grid">
                        <?php foreach ($upcoming_items as $reminder): ?>
                            <div class="reminder-card upcoming">
                                <div class="reminder-header">
                                    <div class="pet-info">
                                        <div class="pet-avatar">
                                            <?php if (!empty($reminder['petImage'])): ?>
                                                <img src="../assets/images/pets/<?php echo htmlspecialchars($reminder['petImage']); ?>" alt="<?php echo htmlspecialchars($reminder['petName']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <i class="fas fa-paw"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-details">
                                            <h6><?php echo htmlspecialchars($reminder['petName']); ?></h6>
                                            <span class="reminder-type"><?php echo htmlspecialchars($reminder['reminder_type']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="status-badge upcoming">
                                        <i class="fas fa-calendar"></i>
                                        Upcoming
                                    </div>
                                </div>
                                
                                <div class="reminder-content">
                                    <h6><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                    <?php if (!empty($reminder['description'])): ?>
                                        <p><?php echo htmlspecialchars($reminder['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="reminder-dates">
                                    <div class="date-item">
                                        <span class="label">Reminder:</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($reminder['reminder_date'])); ?></span>
                                    </div>
                                    <div class="date-item">
                                        <span class="label">Due:</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($reminder['due_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="reminder-actions">
                                    <button class="btn btn-outline-primary btn-sm" onclick="editReminder(<?php echo $reminder['reminderID']; ?>)">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Future Reminders -->
            <?php
            $future_items = array_filter($reminders, function($r) { 
                return $r['status'] === 'pending' && $r['reminder_date'] > date('Y-m-d', strtotime('+7 days')); 
            });
            if (!empty($future_items)):
            ?>
                <div class="reminder-section">
                    <div class="section-header success">
                        <h5><i class="fas fa-calendar-plus me-2"></i>Future Reminders</h5>
                        <span class="success-badge"><?php echo count($future_items); ?> scheduled</span>
                    </div>
                    
                    <div class="reminders-grid">
                        <?php foreach (array_slice($future_items, 0, 6) as $reminder): ?>
                            <div class="reminder-card future">
                                <div class="reminder-header">
                                    <div class="pet-info">
                                        <div class="pet-avatar">
                                            <?php if (!empty($reminder['petImage'])): ?>
                                                <img src="../assets/images/pets/<?php echo htmlspecialchars($reminder['petImage']); ?>" alt="<?php echo htmlspecialchars($reminder['petName']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <i class="fas fa-paw"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-details">
                                            <h6><?php echo htmlspecialchars($reminder['petName']); ?></h6>
                                            <span class="reminder-type"><?php echo htmlspecialchars($reminder['reminder_type']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="status-badge future">
                                        <i class="fas fa-clock"></i>
                                        Scheduled
                                    </div>
                                </div>
                                
                                <div class="reminder-content">
                                    <h6><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                </div>
                                
                                <div class="reminder-dates">
                                    <div class="date-item">
                                        <span class="label">Reminder:</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($reminder['reminder_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($future_items) > 6): ?>
                        <div class="text-center mt-4">
                            <button class="btn btn-outline-primary" onclick="showAllFuture()">
                                View All Future Reminders (<?php echo count($future_items); ?>)
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 80px 0;
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
}

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.page-actions .btn {
    margin-left: 12px;
}

.stats-section-modern {
    padding: 40px 0;
    background: #fffbeb;
    margin-top: -30px;
    position: relative;
    z-index: 2;
}

.stat-card-modern {
    background: white;
    border-radius: 20px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    height: 100%;
}

.stat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-card-modern.danger .stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
.stat-card-modern.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card-modern.info .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-card-modern.success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-label {
    color: #64748b;
    font-weight: 500;
    margin: 0;
}

.quick-actions-section {
    padding: 40px 0;
    background: #fffbeb;
}

.quick-actions-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.quick-actions-card h5 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 24px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.action-item:hover {
    background: #f59e0b;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

.action-icon {
    width: 40px;
    height: 40px;
    background: #f59e0b;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.action-item:hover .action-icon {
    background: white;
    color: #f59e0b;
}

.reminders-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-state-modern {
    text-align: center;
    padding: 80px 40px;
    background: #fffbeb;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
    max-width: 500px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.empty-state-modern h4 {
    color: #374151;
    margin-bottom: 16px;
    font-weight: 600;
}

.empty-state-modern p {
    color: #6b7280;
    margin-bottom: 32px;
}

.content-header {
    margin-bottom: 40px;
}

.content-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.count-badge {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 12px;
}

.reminder-section {
    margin-bottom: 50px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px 24px;
    border-radius: 12px;
}

.section-header.urgent { background: #fef2f2; border-left: 4px solid #ef4444; }
.section-header.warning { background: #fffbeb; border-left: 4px solid #f59e0b; }
.section-header.info { background: #eff6ff; border-left: 4px solid #3b82f6; }
.section-header.success { background: #f0fdf4; border-left: 4px solid #10b981; }

.section-header h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.urgent-badge { background: #ef4444; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
.warning-badge { background: #f59e0b; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
.info-badge { background: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
.success-badge { background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }

.reminders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.reminder-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
}

.reminder-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.reminder-card.overdue { border-left: 4px solid #ef4444; }
.reminder-card.upcoming { border-left: 4px solid #3b82f6; }
.reminder-card.future { border-left: 4px solid #10b981; }

.reminder-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.pet-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pet-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.pet-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 1.2rem;
}

.pet-details h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 2px;
}

.reminder-type {
    color: #64748b;
    font-size: 0.85rem;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.status-badge.overdue { background: #fee2e2; color: #991b1b; }
.status-badge.upcoming { background: #dbeafe; color: #1e40af; }
.status-badge.future { background: #dcfce7; color: #166534; }

.reminder-content {
    margin-bottom: 16px;
}

.reminder-content h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 8px;
}

.reminder-content p {
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.reminder-dates {
    margin-bottom: 16px;
}

.date-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.date-item .label {
    color: #9ca3af;
    font-size: 0.85rem;
}

.date-item .value {
    color: #374151;
    font-weight: 500;
    font-size: 0.85rem;
}

.date-item .value.overdue {
    color: #ef4444;
    font-weight: 600;
}

.days-overdue {
    color: #ef4444;
    font-weight: 600;
    font-size: 0.8rem;
    text-align: center;
    background: #fee2e2;
    padding: 4px 8px;
    border-radius: 6px;
    margin-top: 8px;
}

.reminder-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.reminder-actions .btn {
    flex: 1;
    min-width: 120px;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .page-actions .btn {
        margin-left: 0;
        margin-right: 12px;
        margin-bottom: 8px;
    }
    
    .reminders-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .reminder-actions {
        flex-direction: column;
    }
    
    .reminder-actions .btn {
        min-width: auto;
    }
}
</style>

<script>
function scheduleVaccination() {
    window.location.href = 'add_reminder.php?type=vaccination';
}

function scheduleCheckup() {
    window.location.href = 'add_reminder.php?type=checkup';
}

function viewCalendar() {
    window.location.href = 'medical_calendar.php';
}

function editReminder(reminderId) {
    window.location.href = 'edit_reminder.php?id=' + reminderId;
}

function showAllFuture() {
    // Filter to show all future reminders
    document.querySelectorAll('.reminder-card.future').forEach(function(card) {
        card.style.display = 'block';
    });
    event.target.style.display = 'none';
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>