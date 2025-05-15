<?php
require_once 'includes/header.php';

// Check if user is police
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'police') {
    header("Location: /Amber Alert/login.php");
    exit();
}

// Get group_id from GET parameter
if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    echo "Invalid group ID.";
    exit();
}
$group_id = (int)$_GET['group_id'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$area = isset($_GET['area']) ? $_GET['area'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query for alerts
$sql = "SELECT * FROM Alert WHERE 1=1";
$filter_params = [];
$filter_types = "";

if ($status) {
    $sql .= " AND Status = ?";
    $filter_params[] = $status;
    $filter_types .= "s";
}

if ($area) {
    $sql .= " AND Area = ?";
    $filter_params[] = $area;
    $filter_types .= "s";
}

if ($start_date) {
    $sql .= " AND Alert_datetime >= ?";
    $filter_params[] = $start_date;
    $filter_types .= "s";
}

if ($end_date) {
    $sql .= " AND Alert_datetime <= ?";
    $filter_params[] = $end_date;
    $filter_types .= "s";
}

// Get total count for pagination
$count_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($count_sql);
if (!empty($filter_params)) {
    $stmt->bind_param($filter_types, ...$filter_params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Add pagination to main query
$sql .= " ORDER BY Alert_datetime DESC LIMIT ? OFFSET ?";
$main_params = array_merge($filter_params, [$per_page, $offset]);
$main_types = $filter_types . "ii";

$stmt = $conn->prepare($sql);
if (!empty($main_params)) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$alerts = $stmt->get_result();

// Fetch assigned alerts for this group
$assigned_alerts = [];
$assign_stmt = $conn->prepare("SELECT alert_id FROM quick_respond_to WHERE group_id = ?");
$assign_stmt->bind_param("i", $group_id);
$assign_stmt->execute();
$assign_result = $assign_stmt->get_result();
while ($row = $assign_result->fetch_assoc()) {
    $assigned_alerts[] = $row['alert_id'];
}
$assign_stmt->close();

// Handle assignment/unassignment POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id'], $_POST['action'])) {
    $alert_id = (int)$_POST['alert_id'];
    $action = $_POST['action'];

    if ($action === 'assign') {
        // Insert assignment if not exists
        $check_stmt = $conn->prepare("SELECT * FROM quick_respond_to WHERE group_id = ? AND alert_id = ?");
        $check_stmt->bind_param("ii", $group_id, $alert_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows === 0) {
            $insert_stmt = $conn->prepare("INSERT INTO quick_respond_to (group_id, alert_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $group_id, $alert_id);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $check_stmt->close();
    } elseif ($action === 'unassign') {
        // Delete assignment
        $delete_stmt = $conn->prepare("DELETE FROM quick_respond_to WHERE group_id = ? AND alert_id = ?");
        $delete_stmt->bind_param("ii", $group_id, $alert_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    // Refresh page to reflect changes
    header("Location: alert_assignment.php?group_id=$group_id&page=$page&status=$status&area=$area&start_date=$start_date&end_date=$end_date");
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Assigning a case to: 
                    <?php
                    $group_name_stmt = $conn->prepare("SELECT group_name FROM volunteer_name_group WHERE group_id = ?");
                    $group_name_stmt->bind_param("i", $group_id);
                    $group_name_stmt->execute();
                    $group_name_result = $group_name_stmt->get_result();
                    $group_name = $group_name_result->fetch_assoc()['group_name'] ?? 'Unknown Group';
                    $group_name_stmt->close();
                    echo htmlspecialchars($group_name);
                    ?>
                </h5>
                <a href="group_feed.php" class="btn btn-secondary">Back to Groups</a>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="mb-4">
                    <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group_id); ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="area" class="form-label">Area</label>
                            <input type="text" class="form-control" id="area" name="area" value="<?php echo htmlspecialchars($area); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="alert_assignment.php?group_id=<?php echo htmlspecialchars($group_id); ?>" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>

                <!-- Alerts List -->
                <?php if ($alerts->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($alert = $alerts->fetch_assoc()): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($alert['title']); ?></h5>
                                    <small class="text-muted"><?php echo date('F j, Y g:i A', strtotime($alert['Alert_datetime'])); ?></small>
                                    <p class="mb-1"><?php echo htmlspecialchars(substr($alert['Details'], 0, 200)) . '...'; ?></p>
                                    <small class="text-muted">
                                        Area: <?php echo htmlspecialchars($alert['Area']); ?> | 
                                        Status: <span class="badge bg-<?php echo $alert['Status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($alert['Status']); ?>
                                        </span>
                                    </small>
                                </div>
                                <div>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="alert_id" value="<?php echo $alert['Alert_ID']; ?>">
                                        <?php if (in_array($alert['Alert_ID'], $assigned_alerts)): ?>
                                            <button type="submit" name="action" value="unassign" class="btn btn-danger btn-sm">Unassign</button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="assign" class="btn btn-success btn-sm">Assign</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="alert_assignment.php?group_id=<?php echo $group_id; ?>&page=<?php echo $page-1; ?>&status=<?php echo $status; ?>&area=<?php echo urlencode($area); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="alert_assignment.php?group_id=<?php echo $group_id; ?>&page=<?php echo $i; ?>&status=<?php echo $status; ?>&area=<?php echo urlencode($area); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="alert_assignment.php?group_id=<?php echo $group_id; ?>&page=<?php echo $page+1; ?>&status=<?php echo $status; ?>&area=<?php echo urlencode($area); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-center">No alerts found matching your criteria.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
