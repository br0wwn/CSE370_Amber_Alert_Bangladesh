<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['area'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Area parameter is required']);
    exit();
}

$area = $_GET['area'];

// Get alert counts for the last 6 months
$sql = "SELECT 
            DATE_FORMAT(Alert_datetime, '%Y-%m') as month,
            COUNT(*) as count
        FROM Alert 
        WHERE Area = ? 
        AND Alert_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(Alert_datetime, '%Y-%m')
        ORDER BY month";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $area);
$stmt->execute();
$result = $stmt->get_result();

$data = [
    'labels' => [],
    'values' => []
];

// Initialize last 6 months with zero counts
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[$month] = 0;
}

// Fill in actual counts
while ($row = $result->fetch_assoc()) {
    $months[$row['month']] = (int)$row['count'];
}

// Format data for chart
foreach ($months as $month => $count) {
    $data['labels'][] = date('M Y', strtotime($month));
    $data['values'][] = $count;
}

echo json_encode($data);
?> 