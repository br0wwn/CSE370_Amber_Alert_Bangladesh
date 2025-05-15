<?php
require_once 'config.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Check if alert ID is provided
if (!isset($_GET['id'])) {
    header('Location: alert_feed.php');
    exit();
}

$alert_id = (int)$_GET['id'];

// Get alert details
$sql = "SELECT a.*, u.name as creator_name, u.Contact as creator_contact 
        FROM Alert a 
        JOIN user u ON a.user_id = u.ID 
        WHERE a.Alert_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alert_id);
$stmt->execute();
$alert = $stmt->get_result()->fetch_assoc();

if (!$alert) {
    header('Location: alert_feed.php');
    exit();
}

// Set Open Graph meta tag variables for header.php
$og_title = $alert['title'];
$og_description = $alert['Details'];
$og_url = "http://" . $_SERVER['HTTP_HOST'] . "/Amber Alert/view_alert.php?id=" . $alert_id;
$og_image = '';
if (!empty($alert['Media'])) {
    $og_image = "http://" . $_SERVER['HTTP_HOST'] . "/Amber Alert/media/alerts/" . rawurlencode($alert['Media']);
}

require_once 'includes/header.php';

 // Handle status update
 if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
     // Check if user owns this alert
     if ($alert['user_id'] == $user_id) {
         // Debug: fetch share links server-side to display on page
         $share_links = null;
         $share_sql = "SELECT facebook, twitter FROM share_on_social WHERE alert_id = ?";
         $share_stmt = $conn->prepare($share_sql);
         $share_stmt->bind_param("i", $alert_id);
         $share_stmt->execute();
         $share_result = $share_stmt->get_result();
         if ($share_result->num_rows > 0) {
             $share_links = $share_result->fetch_assoc();
         }
 
         if ($share_links) {
             ?>
             <div class="container my-4">
                 <h5>Debug: Share Links</h5>
                 <p><a href="<?= htmlspecialchars($share_links['facebook']) ?>" target="_blank">Facebook Share Link</a></p>
                 <p><a href="<?= htmlspecialchars($share_links['twitter']) ?>" target="_blank">Twitter Share Link</a></p>
             </div>
             <?php
         }
         $new_status = $_POST['status'];
         $sql = "UPDATE Alert SET Status = ? WHERE Alert_ID = ? AND user_id = ?";
         $stmt = $conn->prepare($sql);
         $stmt->bind_param("sii", $new_status, $alert_id, $user_id);
         
         if ($stmt->execute()) {
             $success = "Alert status updated successfully!";
             $alert['Status'] = $new_status; // Update local alert data
         } else {
             $error = "Error updating alert status. Please try again.";
         }
     } else {
         $error = "You don't have permission to edit this alert.";
     }
 }

// Handle new log entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_log'])) {
    // Check if user is authorized (alert creator or police)
    $is_authorized = ($alert['user_id'] == $user_id || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police'));
    
    if ($is_authorized) {
        $log_title = trim($_POST['log_title']);
        $log_details = trim($_POST['log_details']);
        $log_datetime = date('Y-m-d H:i:s');
        
        // Handle file upload
        $media = '';
        if (isset($_FILES['log_media']) && $_FILES['log_media']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['log_media']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed) && $_FILES['log_media']['size'] < 10 * 1024 * 1024) {
                $new_filename = 'log_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_dir = 'media/logs/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['log_media']['tmp_name'], $upload_path)) {
                    $media = $new_filename;
                } else {
                    $error = "Error uploading file. Please try again.";
                }
            } else {
                $error = "Invalid file type or size. Please upload a JPG, JPEG, PNG, or GIF file under 10MB.";
            }
        }
        
        if (!isset($error)) {
            $sql = "INSERT INTO log (alert_id, log_datetime, area, title, details, media) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $alert_id, $log_datetime, $alert['Area'], $log_title, $log_details, $media);
            
            if ($stmt->execute()) {
                $success = "Log entry added successfully!";
            } else {
                $error = "Error adding log entry. Please try again.";
            }
        }
    } else {
        $error = "You don't have permission to add log entries.";
    }
}

// Get alert logs
$sql = "SELECT * FROM log WHERE alert_id = ? ORDER BY log_datetime DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alert_id);
$stmt->execute();
$logs = $stmt->get_result();

// Get suspicious activity reports in the same area
$sql = "SELECT cr.*, u.name as reporter_name 
        FROM citizen_report cr 
        JOIN user u ON cr.ID = u.ID 
        WHERE cr.area = ? 
        AND cr.report_datetime >= DATE_SUB(?, INTERVAL 7 DAY)
        ORDER BY cr.report_datetime DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $alert['Area'], $alert['Alert_datetime']);
$stmt->execute();
$suspicious_reports = $stmt->get_result();

// Handle adding suspicious report to log
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_report_to_log'])) {
    // Check if user is authorized (alert creator or police)
    $is_authorized = ($alert['user_id'] == $user_id || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police'));
    
    if ($is_authorized) {
        $report_id = (int)$_POST['report_id'];
        $report_datetime = $_POST['report_datetime'];
        $report_area = $_POST['report_area'];
        
        // Get report details
        $sql = "SELECT cr.*, u.name as reporter_name 
                FROM citizen_report cr 
                JOIN user u ON cr.ID = u.ID 
                WHERE cr.ID = ? AND cr.report_datetime = ? AND cr.area = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $report_id, $report_datetime, $report_area);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        
        if ($report) {
            $log_datetime = $report['report_datetime'];
            $log_media = '';
            // Copy the report's image if it exists
            if (!empty($report['media'])) {
                $src_path = 'media/citirep/' . $report['media'];
                if (file_exists($src_path)) {
                    $ext = pathinfo($report['media'], PATHINFO_EXTENSION);
                    $new_filename = 'log_' . time() . '_' . uniqid() . '.' . $ext;
                    $dest_path = 'media/logs/' . $new_filename;
                    if (copy($src_path, $dest_path)) {
                        $log_media = $new_filename;
                    }
                }
            }
            $sql = "INSERT INTO log (alert_id, log_datetime, area, title, details, media) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $alert_id, $log_datetime, $report['area'], $report['title'], $report['details'], $log_media);
            
            if ($stmt->execute()) {
                $success = "Report added to alert log successfully!";
            } else {
                $error = "Error adding report to log. Please try again.";
            }
        } else {
            $error = "Report not found.";
        }
    } else {
        $error = "You don't have permission to add reports to the log.";
    }
}
?>



<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Alert Details</h5>
                    <div>
                        <a href="alert_feed.php" class="btn btn-secondary me-2">Back to Alerts</a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Share
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" id="shareDropdownMenu">
                                <!-- Facebook share URL only supports URL parameter, no custom text -->
                                <li><a class="dropdown-item" href="<?php 
                                    $fb_url = "http://" . $_SERVER['HTTP_HOST'] . "/Amber Alert/view_alert.php?id=" . $alert_id;
                                    echo "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($fb_url);
                                ?>" target="_blank" id="shareFacebook">Share on Facebook</a></li>
                                <li><a class="dropdown-item" href="<?php 
                                    $twitter_text = "!!!!!!!! AMBER ALERT !!!!!!!\n\n" . $alert['title'] . "\n" . $fb_url;
                                    echo "https://twitter.com/intent/tweet?text=" . urlencode($twitter_text);
                                ?>" target="_blank" id="shareTwitter">Share on Twitter (X)</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-<?php echo $alert['Status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($alert['Status']); ?>
                            </span>
                            <small class="text-muted">
                                Posted on: <?php echo date('F j, Y g:i A', strtotime($alert['Alert_datetime'])); ?>
                            </small>
                        </div>
                        
                        <?php if ($alert['Media']): ?>
                            <div class="mb-3">
                                <img src="media/alerts/<?php echo htmlspecialchars($alert['Media']); ?>" 
                                     class="img-fluid rounded" 
                                     alt="Alert Media">
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <h6>Details:</h6>
                            <p><?php echo nl2br(htmlspecialchars($alert['Details'])); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Location:</h6>
                            <p><?php echo htmlspecialchars($alert['Area']); ?></p>
                            <?php if (!empty($alert['Location_URL'])): ?>
                                <a href="<?php echo htmlspecialchars($alert['Location_URL']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    View on Map
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Posted by:</h6>
                            <p>
                                <?php echo htmlspecialchars($alert['creator_name']); ?>
                                <br>
                                Contact: <?php echo htmlspecialchars($alert['creator_contact']); ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($alert['user_id'] == $user_id): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Update Alert Status</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $alert['Status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $alert['Status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alert Log Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Alert Log</h5>
                </div>
                <div class="card-body">
                    <?php if ($alert['user_id'] == $user_id || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police')): ?>
                        <form method="POST" action="" class="mb-4" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="log_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="log_title" name="log_title" required>
                            </div>
                            <div class="mb-3">
                                <label for="log_details" class="form-label">Details</label>
                                <textarea class="form-control" id="log_details" name="log_details" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="log_media" class="form-label">Upload Image (Optional)</label>
                                <input type="file" class="form-control" id="log_media" name="log_media" accept="image/*">
                                <small class="text-muted">Maximum file size: 10MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                            </div>
                            <button type="submit" name="add_log" class="btn btn-primary">Add Log Entry</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($logs->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($log['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y g:i A', strtotime($log['log_datetime'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($log['details'])); ?></p>
                                    <?php if ($log['media']): ?>
                                        <?php
                                        $log_media_path = 'media/logs/' . htmlspecialchars($log['media']);
                                        $report_media_path = 'media/reports/' . htmlspecialchars($log['media']);
                                        if (file_exists($log_media_path)) {
                                            $img_src = $log_media_path;
                                        } elseif (file_exists($report_media_path)) {
                                            $img_src = $report_media_path;
                                        } else {
                                            $img_src = '';
                                        }
                                        ?>
                                        <?php if ($img_src): ?>
                                            <img src="<?php echo $img_src; ?>" class="img-fluid mt-2" style="max-height: 200px;" alt="Log Media">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <small class="text-muted">Area: <?php echo htmlspecialchars($log['area']); ?></small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No log entries yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Suspicious Activity Reports Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Related Suspicious Activity Reports</h5>
                </div>
                <div class="card-body">
                    <?php if ($suspicious_reports->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($report = $suspicious_reports->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($report['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y g:i A', strtotime($report['report_datetime'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($report['details'])); ?></p>
                                    <?php if ($report['media']): ?>
                                        <img src="media/citirep/<?php echo htmlspecialchars($report['media']); ?>" 
                                             class="img-fluid mt-2" 
                                             style="max-height: 200px;" 
                                             alt="Report Media">
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Reported by: <?php echo $report['anon_rep'] ? 'Anonymous' : htmlspecialchars($report['reporter_name']); ?>
                                            <br>
                                            Area: <?php echo htmlspecialchars($report['area']); ?>
                                        </small>
                                        <?php if ($alert['user_id'] == $user_id || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police')): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['ID']; ?>">
                                                <input type="hidden" name="report_datetime" value="<?php echo $report['report_datetime']; ?>">
                                                <input type="hidden" name="report_area" value="<?php echo $report['area']; ?>">
                                                <button type="submit" name="add_report_to_log" class="btn btn-sm btn-outline-primary">
                                                    Add to Log
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No suspicious activity reports in this area for the past week.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php
// Debug: fetch share links server-side to display on page
$share_links = null;
$share_sql = "SELECT facebook, twitter FROM share_on_social WHERE alert_id = ?";
$share_stmt = $conn->prepare($share_sql);
$share_stmt->bind_param("i", $alert_id);
$share_stmt->execute();
$share_result = $share_stmt->get_result();
if ($share_result->num_rows > 0) {
    $share_links = $share_result->fetch_assoc();
}
?>

<?php if ($share_links): ?>
<div class="container my-4">
    <h5>Debug: Share Links</h5>
    <p><a href="<?php echo htmlspecialchars($share_links['facebook']); ?>" target="_blank">Facebook Share Link</a></p>
    <p><a href="<?php echo htmlspecialchars($share_links['twitter']); ?>" target="_blank">Twitter Share Link</a></p>
</div>
<?php endif; ?>

<script>
// Removed JavaScript fetch and event listeners for share buttons to test direct links
</script>
