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

// Check if pet_id is provided
if (!isset($_GET['pet_id']) || empty($_GET['pet_id'])) {
    $_SESSION['error_message'] = "Invalid pet ID.";
    header("Location: pets.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$pet_id = $_GET['pet_id'];

// Verify pet belongs to user
$pet_sql = "SELECT petName, image FROM pet_profile WHERE petID = ? AND userID = ?";
$pet_stmt = $conn->prepare($pet_sql);
$pet_stmt->bind_param("ii", $pet_id, $user_id);
$pet_stmt->execute();
$pet_result = $pet_stmt->get_result();

if ($pet_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pet not found or does not belong to you.";
    header("Location: pets.php");
    exit();
}

$pet = $pet_result->fetch_assoc();
$pet_stmt->close();

// Get filter parameters
$record_type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Build SQL query
$sql = "SELECT * FROM pet_medical_records WHERE petID = ?";
$params = [$pet_id];
$param_types = "i";

if (!empty($record_type_filter)) {
    $sql .= " AND record_type = ?";
    $params[] = $record_type_filter;
    $param_types .= "s";
}

if (!empty($year_filter)) {
    $sql .= " AND YEAR(record_date) = ?";
    $params[] = $year_filter;
    $param_types .= "i";
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY record_date ASC, created_at ASC";
        break;
    case 'type':
        $sql .= " ORDER BY record_type ASC, record_date DESC";
        break;
    case 'cost_high':
        $sql .= " ORDER BY cost DESC";
        break;
    case 'cost_low':
        $sql .= " ORDER BY cost ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY record_date DESC, created_at DESC";
        break;
}

// Execute query
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$medical_records = [];

while ($row = $result->fetch_assoc()) {
    $medical_records[] = $row;
}
$stmt->close();

// Get available years for filter
$years_sql = "SELECT DISTINCT YEAR(record_date) as year FROM pet_medical_records WHERE petID = ? ORDER BY year DESC";
$years_stmt = $conn->prepare($years_sql);
$years_stmt->bind_param("i", $pet_id);
$years_stmt->execute();
$years_result = $years_stmt->get_result();
$available_years = [];
while ($row = $years_result->fetch_assoc()) {
    $available_years[] = $row['year'];
}
$years_stmt->close();

// Calculate statistics
$total_records = count($medical_records);
$total_cost = array_sum(array_column($medical_records, 'cost'));
$recent_records = array_filter($medical_records, function($record) {
    return strtotime($record['record_date']) > strtotime('-3 months');
});
$recent_count = count($recent_records);

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">🏥 Medical Records</span>
                    <h1 class="page-title"><?php echo htmlspecialchars($pet['petName']); ?>'s <span class="text-gradient">Health History</span></h1>
                    <p class="page-subtitle">Complete medical records and health tracking</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary-gradient">
                        <i class="fas fa-plus me-2"></i> Add Record
                    </a>
                    <a href="pet_details.php?id=<?php echo $pet_id; ?>" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Health Statistics -->
<section class="stats-section-modern">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern primary">
                    <div class="stat-icon">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $total_records; ?></h3>
                        <p class="stat-label">Total Records</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern success">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $recent_count; ?></h3>
                        <p class="stat-label">Recent (3 months)</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern warning">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">$<?php echo number_format($total_cost, 0); ?></h3>
                        <p class="stat-label">Total Cost</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern info">
                    <div class="stat-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">
                            <?php 
                            $last_checkup = null;
                            foreach ($medical_records as $record) {
                                if ($record['record_type'] === 'Health Checkup') {
                                    $last_checkup = $record['record_date'];
                                    break;
                                }
                            }
                            echo $last_checkup ? date('M Y', strtotime($last_checkup)) : 'None';
                            ?>
                        </h3>
                        <p class="stat-label">Last Checkup</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Section -->
<section class="filters-section-modern">
    <div class="container">
        <div class="filter-card-modern">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form-modern">
                <input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="filter-label-modern">Record Type</label>
                        <select class="form-select modern-select" name="type">
                            <option value="">All Types</option>
                            <option value="Vaccination" <?php echo ($record_type_filter === 'Vaccination') ? 'selected' : ''; ?>>💉 Vaccination</option>
                            <option value="Health Checkup" <?php echo ($record_type_filter === 'Health Checkup') ? 'selected' : ''; ?>>🔍 Health Checkup</option>
                            <option value="Emergency Visit" <?php echo ($record_type_filter === 'Emergency Visit') ? 'selected' : ''; ?>>🚨 Emergency Visit</option>
                            <option value="Surgery" <?php echo ($record_type_filter === 'Surgery') ? 'selected' : ''; ?>>🏥 Surgery</option>
                            <option value="Dental Care" <?php echo ($record_type_filter === 'Dental Care') ? 'selected' : ''; ?>>🦷 Dental Care</option>
                            <option value="Lab Results" <?php echo ($record_type_filter === 'Lab Results') ? 'selected' : ''; ?>>🧪 Lab Results</option>
                            <option value="Medication" <?php echo ($record_type_filter === 'Medication') ? 'selected' : ''; ?>>💊 Medication</option>
                            <option value="Other" <?php echo ($record_type_filter === 'Other') ? 'selected' : ''; ?>>📋 Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="filter-label-modern">Year</label>
                        <select class="form-select modern-select" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year_filter == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="filter-label-modern">Sort By</label>
                        <select class="form-select modern-select" name="sort_by">
                            <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="type" <?php echo ($sort_by === 'type') ? 'selected' : ''; ?>>By Type</option>
                            <option value="cost_high" <?php echo ($sort_by === 'cost_high') ? 'selected' : ''; ?>>Cost: High to Low</option>
                            <option value="cost_low" <?php echo ($sort_by === 'cost_low') ? 'selected' : ''; ?>>Cost: Low to High</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-gradient w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="medical_records.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-refresh me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Medical Records Content -->
<section class="content-section-modern">
    <div class="container">
        <?php if (empty($medical_records)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-notes-medical"></i>
                </div>
                <h4>No Medical Records Found</h4>
                <p>No medical records match your current filters<?php echo !empty($record_type_filter) || !empty($year_filter) ? "." : " for " . htmlspecialchars($pet['petName']) . "."; ?></p>
                <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary-gradient">
                    <i class="fas fa-plus me-2"></i> Add First Record
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Medical Records <span class="count-badge"><?php echo count($medical_records); ?> found</span></h3>
                <div class="header-actions">
                    <button class="btn btn-outline-primary" onclick="exportRecords()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
            
            <div class="records-timeline">
                <?php foreach ($medical_records as $record): ?>
                    <div class="record-card-modern">
                        <div class="record-date-indicator">
                            <div class="date-circle">
                                <span class="day"><?php echo date('d', strtotime($record['record_date'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($record['record_date'])); ?></span>
                                <span class="year"><?php echo date('Y', strtotime($record['record_date'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="record-content">
                            <div class="record-header">
                                <div class="record-type-badge type-<?php echo strtolower(str_replace(' ', '-', $record['record_type'])); ?>">
                                    <?php
                                    $type_icons = [
                                        'Vaccination' => '💉',
                                        'Health Checkup' => '🔍',
                                        'Emergency Visit' => '🚨',
                                        'Surgery' => '🏥',
                                        'Dental Care' => '🦷',
                                        'Lab Results' => '🧪',
                                        'Medication' => '💊',
                                        'Grooming' => '✂️',
                                        'Injury' => '🩹',
                                        'Other' => '📋'
                                    ];
                                    echo isset($type_icons[$record['record_type']]) ? $type_icons[$record['record_type']] : '📋';
                                    ?>
                                    <?php echo htmlspecialchars($record['record_type']); ?>
                                </div>
                                
                                <?php if (!empty($record['cost'])): ?>
                                    <div class="record-cost">
                                        $<?php echo number_format($record['cost'], 2); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="record-description">
                                <p><?php echo nl2br(htmlspecialchars($record['description'])); ?></p>
                            </div>
                            
                            <div class="record-details">
                                <?php if (!empty($record['veterinarian'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-user-md"></i>
                                        <span>Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['clinic_name'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-hospital"></i>
                                        <span><?php echo htmlspecialchars($record['clinic_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['medication'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-pills"></i>
                                        <span><?php echo htmlspecialchars($record['medication']); ?>
                                        <?php if (!empty($record['dosage'])): ?>
                                            - <?php echo htmlspecialchars($record['dosage']); ?>
                                        <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['follow_up_date'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span>Follow-up: <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($record['notes'])): ?>
                                <div class="record-notes">
                                    <h6>Additional Notes:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['attachment'])): ?>
                                <div class="record-attachment">
                                    <a href="../assets/uploads/medical_records/<?php echo htmlspecialchars($record['attachment']); ?>" target="_blank" class="attachment-link">
                                        <i class="fas fa-paperclip"></i>
                                        <span>View Attachment</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="record-actions">
                                <button class="btn btn-outline-primary btn-sm" onclick="editRecord(<?php echo $record['recordID']; ?>)">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteRecord(<?php echo $record['recordID']; ?>)">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
    background: #f8fdf4;
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

.stat-card-modern.primary .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card-modern.success .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-card-modern.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card-modern.info .stat-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }

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

.filters-section-modern {
    padding: 40px 0;
    background: #f8fdf4;
}

.filter-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label-modern {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: white;
}

.modern-select:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.content-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-state-modern {
    text-align: center;
    padding: 80px 40px;
    background: #f8fdf4;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.records-timeline {
    position: relative;
    padding-left: 60px;
}

.records-timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #10b981, #e2e8f0);
}

.record-card-modern {
    position: relative;
    margin-bottom: 32px;
    display: flex;
    gap: 24px;
}

.record-date-indicator {
    position: absolute;
    left: -45px;
    top: 0;
}

.date-circle {
    width: 60px;
    height: 60px;
    background: white;
    border: 3px solid #10b981;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.day {
    font-size: 1.1rem;
    color: #10b981;
    line-height: 1;
}

.month {
    font-size: 0.7rem;
    color: #64748b;
    line-height: 1;
}

.year {
    font-size: 0.6rem;
    color: #9ca3af;
    line-height: 1;
}

.record-content {
    background: white;
    border-radius: 16px;
    padding: 24px;
    flex: 1;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.record-content:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.record-type-badge {
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.type-vaccination { background: #fef3c7; color: #92400e; }
.type-health-checkup { background: #dbeafe; color: #1e40af; }
.type-emergency-visit { background: #fee2e2; color: #991b1b; }
.type-surgery { background: #f3e8ff; color: #7c3aed; }
.type-dental-care { background: #ecfdf5; color: #065f46; }
.type-lab-results { background: #f0f9ff; color: #0c4a6e; }
.type-medication { background: #fdf2f8; color: #be185d; }
.type-grooming { background: #fffbeb; color: #92400e; }
.type-injury { background: #fef2f2; color: #dc2626; }
.type-other { background: #f1f5f9; color: #475569; }

.record-cost {
    background: #10b981;
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
}

.record-description {
    margin-bottom: 16px;
}

.record-description p {
    color: #374151;
    line-height: 1.6;
    margin: 0;
}

.record-details {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.9rem;
}

.detail-item i {
    color: #10b981;
    width: 16px;
}

.record-notes {
    background: #f8fafc;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.record-notes h6 {
    color: #374151;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.record-notes p {
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.record-attachment {
    margin-bottom: 16px;
}

.attachment-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #10b981;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.attachment-link:hover {
    color: #059669;
}

.record-actions {
    display: flex;
    gap: 8px;
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
    
    .records-timeline {
        padding-left: 0;
    }
    
    .records-timeline::before {
        display: none;
    }
    
    .record-date-indicator {
        position: static;
        margin-bottom: 12px;
    }
    
    .record-card-modern {
        flex-direction: column;
    }
    
    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}
</style>

<script>
function editRecord(recordId) {
    // Redirect to edit page (to be implemented)
    window.location.href = 'edit_medical_record.php?id=' + recordId;
}

function deleteRecord(recordId) {
    if (confirm('Are you sure you want to delete this medical record? This action cannot be undone.')) {
        // Send delete request
        fetch('delete_medical_record.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'record_id=' + recordId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting record: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the record.');
        });
    }
}

function exportRecords() {
    // Export records to PDF or CSV (to be implemented)
    window.open('export_medical_records.php?pet_id=<?php echo $pet_id; ?>&format=pdf', '_blank');
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>