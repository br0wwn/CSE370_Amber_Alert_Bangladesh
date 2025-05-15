<?php
    session_start();
    require_once 'config.php';

    $is_logged_in = isset($_SESSION['user_id']);
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;

    echo $user_type;
?>