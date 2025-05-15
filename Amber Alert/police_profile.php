<?php
// Removed session_start() to avoid "session already active" notice because header.php already starts session
require_once 'config.php';

// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if police user is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'police' || !isset($_SESSION['station_id'])) {
    header("Location: login.php");
    exit();
}

$station_id = $_SESSION['station_id'];

// Fetch thana info
$sql = "SELECT station_name, OC_name, OC_contact, station_id FROM thana WHERE station_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $station_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Police station information not found.";
    exit();
}

$thana = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Police Profile - Amber Alert Bangladesh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container mt-4">
        <h2>Police Station Profile</h2>
        <div class="card">
            <div class="card-body text-center">
                <img src="media/policeLogo.png" alt="Police Logo" style="width: 200px; height: 200px; margin-bottom: 20px;">
                <div>
                    <p><strong>Station Name:</strong> <?php echo htmlspecialchars($thana['station_name']); ?></p>
                    <p><strong>Officer in Charge (OC):</strong> <?php echo htmlspecialchars($thana['OC_name']); ?></p>
                    <p><strong>OC Contact:</strong> <?php echo htmlspecialchars($thana['OC_contact']); ?></p>
                    <p><strong>Station ID:</strong> <?php echo htmlspecialchars($thana['station_id']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
