<?php
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $area = $_POST['area'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $emergency_contact = $_POST['emergency_contact'];
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['profile_pic']['size'] < 5 * 1024 * 1024) {
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = 'media/profile/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if ($user['Profile_Pic'] && file_exists('media/profile/' . $user['Profile_Pic'])) {
                    unlink('media/profile/' . $user['Profile_Pic']);
                }
                
                // Update database with new profile picture
                $sql = "UPDATE user SET name = ?, Description = ?, Area = ?, Contact = ?, Email = ?, Emergency_Contact = ?, Profile_Pic = ? WHERE ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssi", $name, $description, $area, $contact, $email, $emergency_contact, $new_filename, $user_id);
            } else {
                $error = "Error uploading file. Please try again.";
            }
        } else {
            $error = "Invalid file type or size. Please upload a JPG, JPEG, PNG, or GIF file under 5MB.";
        }
    } else {
        // Update without changing profile picture
        $sql = "UPDATE user SET name = ?, Description = ?, Area = ?, Contact = ?, Email = ?, Emergency_Contact = ? WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $name, $description, $area, $contact, $email, $emergency_contact, $user_id);
    }
    
    if (!isset($error) && $stmt->execute()) {
        $success = "Profile updated successfully!";
        // Refresh user data
        $sql = "SELECT * FROM user WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else if (!isset($error)) {
        $error = "Error updating profile. Please try again.";
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Edit Profile</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-4 text-center mb-4">
                                <?php if ($user['Profile_Pic']): ?>
                                    <img src="media/profile/<?php echo htmlspecialchars($user['Profile_Pic']); ?>" 
                                         class="img-fluid rounded-circle mb-3" 
                                         style="width: 150px; height: 150px; object-fit: cover;" 
                                         alt="Profile Picture">
                                <?php else: ?>
                                    <img src="media/profile/default.jpg" 
                                         class="img-fluid rounded-circle mb-3" 
                                         style="width: 150px; height: 150px; object-fit: cover;" 
                                         alt="Default Profile Picture">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="profile_pic" class="form-label">Change Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                                    <small class="text-muted">Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($user['Description']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="area" class="form-label">Area</label>
                                    <input type="text" class="form-control" id="area" name="area" value="<?php echo htmlspecialchars($user['Area']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($user['Contact']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="emergency_contact" class="form-label">Emergency Contact Number</label>
                                    <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($user['Emergency_Contact']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 