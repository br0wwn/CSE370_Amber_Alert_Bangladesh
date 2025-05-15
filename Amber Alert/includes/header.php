<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Dhaka');
require_once __DIR__ . '/../config.php';




// Check if user is logged in
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police' && isset($_SESSION['station_id']))) {
    header("Location: /Amber Alert/login.php");
    exit();
}

// Get user type from session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;

if ($user_type === 'police') {
    // Get police user info from thana table
    $station_id = $_SESSION['station_id'];
    $sql = "SELECT * FROM thana WHERE station_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $station_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Police users do not have child or group info
    $is_child = false;
    $child_info = null;
    $group_info = null;
} else {
    // Get user information
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM user WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Check if user is a child
    $is_child = false;
    $child_info = null;
    $sql = "SELECT * FROM child WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $is_child = true;
        $child_info = $result->fetch_assoc();
    }

    // Get user's group information
    $group_info = null;
    if ($user['Group_ID']) {
        $sql = "SELECT * FROM volunteer_name_group WHERE group_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user['Group_ID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $group_info = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amber Alert Bangladesh</title>
    <?php
    // Output Open Graph meta tags if variables are set
    if (isset($og_title)) {
        echo '<meta property="og:title" content="' . htmlspecialchars($og_title, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }
    if (isset($og_description)) {
        echo '<meta property="og:description" content="' . htmlspecialchars($og_description, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }
    if (isset($og_url)) {
        echo '<meta property="og:url" content="' . htmlspecialchars($og_url, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }
    if (isset($og_image)) {
        echo '<meta property="og:image" content="' . htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }
    ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Amber Alert/styles/main.css">
</head>
<body class="<?php echo $user_type === 'police' ? 'police-theme' : 'user-theme'; ?>">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/Amber%20Alert/alert_feed.php">Amber Alert Bangladesh</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/Amber%20Alert/alert_feed.php"><i class="bi bi-bell"></i> Alert Feed</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Amber%20Alert/group_feed.php"><i class="bi bi-people"></i> Group Feed</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Amber%20Alert/sar.php"><i class="bi bi-exclamation-triangle"></i> SAR</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Amber%20Alert/hotspot.php"><i class="bi bi-map"></i> Hotspot</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (isset($user['Profile_Pic']) && $user['Profile_Pic']): ?>
                                <img src="/Amber%20Alert/media/profile/<?php echo htmlspecialchars($user['Profile_Pic']); ?>" class="profile-pic me-1" alt="Profile">
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <?php 
                                if ($user_type === 'police') {
                                    echo htmlspecialchars($user['station_name'] ?? 'Police User');
                                } else {
                                    echo htmlspecialchars($user['name'] ?? 'User');
                                }
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo ($user_type === 'police') ? '/Amber%20Alert/police_profile.php' : '/Amber%20Alert/profile.php'; ?>"><i class="bi bi-person"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/Amber%20Alert/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4"> 