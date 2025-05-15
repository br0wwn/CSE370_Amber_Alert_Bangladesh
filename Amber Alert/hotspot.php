<?php
require_once 'includes/header.php';

// Get selected area (default to user's area)
$selected_area = isset($_GET['area']) ? $_GET['area'] : (isset($user['Area']) ? $user['Area'] : '');

// Get alert count for selected area
$sql = "SELECT COUNT(*) as count FROM Alert WHERE Area = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $selected_area);
$stmt->execute();
$area_count = $stmt->get_result()->fetch_assoc()['count'];

// Get top 5 areas with most alerts in the last month
$sql = "SELECT Area, COUNT(*) as count 
        FROM Alert 
        WHERE Alert_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY Area 
        ORDER BY count DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute();
$top_areas = $stmt->get_result();

// Get all areas for dropdown
$sql = "SELECT DISTINCT Area FROM Alert ORDER BY Area";
$stmt = $conn->prepare($sql);
$stmt->execute();
$all_areas = $stmt->get_result();
?>

<div class="row">
    <!-- Area Alert Count -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Alert Frequency by Area</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="mb-3">
                        <label for="area" class="form-label">Select Area</label>
                        <select class="form-select" id="area" name="area">
                            <?php while ($area = $all_areas->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($area['Area']); ?>" 
                                        <?php echo $area['Area'] === $selected_area ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['Area']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Show Statistics</button>
                </form>

                <div class="text-center">
                    <h3><?php echo $area_count; ?></h3>
                    <p class="text-muted">Total alerts in <?php echo htmlspecialchars($selected_area); ?></p>
                </div>

                <!-- Monthly Alert Chart -->
                <canvas id="monthlyChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Top 5 Areas -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Top 5 Areas with Most Alerts (Last Month)</h5>
            </div>
            <div class="card-body">
                <?php if ($top_areas->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($area = $top_areas->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($area['Area']); ?></h6>
                                    <span class="badge bg-primary rounded-pill"><?php echo $area['count']; ?> alerts</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No alert data available for the last month.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Get monthly alert data for the selected area
fetch('get_monthly_stats.php?area=<?php echo urlencode($selected_area); ?>')
    .then(response => response.json())
    .then(data => {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Alerts',
                    data: data.values,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?> 