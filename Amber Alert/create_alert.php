<?php
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle alert submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $details = $_POST['details'];
    $area = $_POST['area'];
    $location_url = isset($_POST['location_url']) ? $_POST['location_url'] : '';
    $status = 'active';
    $alert_datetime = date('Y-m-d H:i:s');
    
    // Handle file upload
    $media = '';
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['media']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['media']['size'] < 10 * 1024 * 1024) {
            $new_filename = 'alert_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = 'media/alerts/';
            
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
    
    if (!isset($error)) {
        $sql = "INSERT INTO Alert (title, details, area, status, alert_datetime, media, user_id, Location_URL) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssis", $title, $details, $area, $status, $alert_datetime, $media, $user_id, $location_url);
        
        if ($stmt->execute()) {
            $success = "Alert posted successfully!";
            // Clear form data after successful submission
            $_POST = array();
        } else {
            $error = "Error posting alert. Please try again.";
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Create New Alert</h5>
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
                            <label for="title" class="form-label">Alert Title</label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="details" class="form-label">Alert Details</label>
                            <textarea class="form-control" id="details" name="details" rows="4" required><?php echo isset($_POST['details']) ? htmlspecialchars($_POST['details']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="area" class="form-label">Area</label>
                            <input type="text" class="form-control" id="area" name="area" required
                                   value="<?php echo isset($_POST['area']) ? htmlspecialchars($_POST['area']) : htmlspecialchars($user['Area']); ?>">
                        </div>
                        
                    <div class="mb-3">
                        <label for="media" class="form-label">Upload Image (Optional)</label>
                        <input type="file" class="form-control" id="media" name="media" accept="image/*">
                        <small class="text-muted">Maximum file size: 10MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>

                    <div class="mb-3">
                        <label for="location_url" class="form-label">Location URL (Optional)</label>
                        <input type="url" class="form-control" id="location_url" name="location_url" placeholder="https://maps.google.com/..." 
                               value="<?php echo isset($_POST['location_url']) ? htmlspecialchars($_POST['location_url']) : ''; ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Post Alert</button>
                        <a href="alert_feed.php" class="btn btn-secondary">Cancel</a>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 