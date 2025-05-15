<?php
require_once 'includes/header.php';

$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
$user_area = isset($user['Area']) ? $user['Area'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $details = $_POST['details'];
    $area = $_POST['area'];
    $anon_rep = isset($_POST['anon_rep']) ? 1 : 0;
    $report_datetime = date('Y-m-d H:i:s');
    
    // Handle file upload
    $media = '';
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['media']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['media']['size'] < 10 * 1024 * 1024) {
            $new_filename = 'citirep_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = 'media/citirep/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['media']['tmp_name'], $upload_path)) {
                $media = $new_filename;
            } else {
                $error = "Error uploading file. Please try again.";
                error_log("Failed to move uploaded file to: " . $upload_path);
            }
        } else {
            $error = "Invalid file type or size. Please upload a JPG, JPEG, PNG, or GIF file under 10MB.";
        }
    }
    
    $sql = "INSERT INTO citizen_report (ID, anon_rep, report_datetime, title, details, area, media) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssss", $user_id, $anon_rep, $report_datetime, $title, $details, $area, $media);
    
    if ($stmt->execute()) {
        $success = "Report submitted successfully!";
    } else {
        $error = "Error submitting report. Please try again.";
    }
}

$filter_area = isset($_GET['filter_area']) ? $_GET['filter_area'] : '';
$filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
$filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';

if ($user_type === 'police') {
    // Build query with filters for police
    $query = "SELECT * FROM citizen_report WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($filter_area !== '') {
        $query .= " AND area = ?";
        $params[] = $filter_area;
        $types .= 's';
    }
    if ($filter_start_date !== '') {
        $query .= " AND report_datetime >= ?";
        $params[] = $filter_start_date . ' 00:00:00';
        $types .= 's';
    }
    if ($filter_end_date !== '') {
        $query .= " AND report_datetime <= ?";
        $params[] = $filter_end_date . ' 23:59:59';
        $types .= 's';
    }
    $query .= " ORDER BY report_datetime DESC";
    
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $reports = $stmt->get_result();
} else {
    // Normal user: reports for user's area
    $sql = "SELECT * FROM citizen_report WHERE area = ? ORDER BY report_datetime DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_area);
    $stmt->execute();
    $reports = $stmt->get_result();
}
?>

<div class="row">
    <?php if ($user_type !== 'police'): ?>
    <!-- SAR Form -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Submit Suspicious Activity Report</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="details" class="form-label">Details</label>
                        <textarea class="form-control" id="details" name="details" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="area" class="form-label">Area</label>
                        <input type="text" class="form-control" id="area" name="area" value="<?php echo htmlspecialchars($user['Area']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="media" class="form-label">Upload Image (Optional)</label>
                        <input type="file" class="form-control" id="media" name="media" accept="image/*">
                        <small class="text-muted">Maximum file size: 10MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="anon_rep" name="anon_rep">
                        <label class="form-check-label" for="anon_rep">Submit anonymously</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Area Reports -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Reports in Your Area</h5>
            </div>
            <div class="card-body">
                <?php if ($reports->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($report = $reports->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['title']); ?></h6>
                                    <small class="text-muted"><?php echo date('F j, Y g:i A', strtotime($report['report_datetime'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($report['details']); ?></p>
                                <?php if ($report['media']): ?>
                                    <img src="media/citirep/<?php echo htmlspecialchars($report['media']); ?>" class="img-fluid mt-2" style="max-height: 200px;" alt="Report Image">
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?php if ($report['anon_rep']): ?>
                                        Anonymous Report
                                    <?php else: ?>
                                        Reported by: <?php 
                                            $sql = "SELECT name FROM user WHERE ID = ?";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bind_param("i", $report['ID']);
                                            $stmt->execute();
                                            echo htmlspecialchars($stmt->get_result()->fetch_assoc()['name']);
                                        ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No reports found in your area.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Police view: filtering form and all reports -->
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter Suspicious Activity Reports</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filter_area" class="form-label">Area</label>
                            <input type="text" class="form-control" id="filter_area" name="filter_area" value="<?php echo htmlspecialchars($filter_area); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Filter</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">All Suspicious Activity Reports</h5>
            </div>
            <div class="card-body">
                <?php if ($reports->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($report = $reports->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['title']); ?></h6>
                                    <small class="text-muted"><?php echo date('F j, Y g:i A', strtotime($report['report_datetime'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($report['details']); ?></p>
                                <?php if ($report['media']): ?>
                                    <img src="media/citirep/<?php echo htmlspecialchars($report['media']); ?>" class="img-fluid mt-2" style="max-height: 200px;" alt="Report Image">
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?php if ($report['anon_rep']): ?>
                                        Anonymous Report
                                    <?php else: ?>
                                        Reported by: <?php 
                                            $sql = "SELECT name FROM user WHERE ID = ?";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bind_param("i", $report['ID']);
                                            $stmt->execute();
                                            echo htmlspecialchars($stmt->get_result()->fetch_assoc()['name']);
                                        ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No suspicious activity reports found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?> 