<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;

// Redirect based on login status
if ($is_logged_in) {
    header('Location: alert_feed.php');
    exit();
} else {
    header('Location: login.php');
    exit();
}
?>