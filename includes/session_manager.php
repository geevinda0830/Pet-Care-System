<?php
// Create includes/session_manager.php - fixes "session already active" warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>