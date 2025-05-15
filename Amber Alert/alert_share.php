<?php
require_once 'includes/header.php';

header('Content-Type: application/json');

if (!isset($_GET['alert_id'])) {
    echo json_encode(['error' => 'Missing alert_id']);
    exit();
}

$alert_id = (int)$_GET['alert_id'];

// Check if share links already exist
$sql = "SELECT facebook, twitter FROM share_on_social WHERE alert_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alert_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $links = $result->fetch_assoc();
    echo json_encode(['success' => true, 'links' => $links]);
    exit();
}

// Get alert details for generating share URLs
$sql = "SELECT title, Details FROM Alert WHERE Alert_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alert_id);
$stmt->execute();
$alert = $stmt->get_result()->fetch_assoc();

if (!$alert) {
    echo json_encode(['error' => 'Alert not found']);
    exit();
}

require_once 'config.php';

// Use dummy localhost URL and fixed template text for testing
$dummy_url = "http://localhost/view_alert.php?id=" . $alert_id;
$encoded_dummy_url = urlencode($dummy_url);

// Facebook share URL with dummy URL
$facebook_url = "https://www.facebook.com/sharer/sharer.php?u=".$encoded_dummy_url;

// Twitter (X) share URL with fixed template text and dummy URL
$template_text = "AMBER ALERT!!!%0AThis is a test alert.%0A%0Afor details: {$dummy_url}";
$twitter_url = "https://twitter.com/intent/tweet?text={$template_text}";

// Store in database
$sql = "INSERT INTO share_on_social (alert_id, facebook, twitter) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $alert_id, $facebook_url, $twitter_url);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'links' => ['facebook' => $facebook_url, 'twitter' => $twitter_url]]);
} else {
?>
