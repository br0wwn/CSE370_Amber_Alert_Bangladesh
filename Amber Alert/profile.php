<?php
require_once 'includes/header.php';

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['profile_pic'])) {
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
            
            // Update database
            $sql = "UPDATE user SET Profile_Pic = ? WHERE ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_filename, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile picture updated successfully!";
                // Refresh user data
                $user['Profile_Pic'] = $new_filename;
            } else {
                $error = "Error updating profile picture. Please try again.";
            }
        } else {
            $error = "Error uploading file. Please try again.";
        }
    } else {
        $error = "Invalid file type or size. Please upload a JPG, JPEG, PNG, or GIF file under 5MB.";
    }
}

// Get user type from session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;

// Get user data
$sql = "SELECT * FROM user WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's alerts
$sql = "SELECT * FROM Alert WHERE user_id = ? ORDER BY Alert_datetime DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get linked children if user is a guardian
$linked_children = [];
if (!$is_child) {
    $sql = "SELECT c.*, u.name, u.DOB FROM child c 
            JOIN user u ON c.id = u.ID 
            JOIN relation r ON c.id = r.child_id 
            WHERE r.guardian_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $linked_children = $stmt->get_result();
}

// Get relationship information
$relation_info = null;
if (isset($_SESSION['user_id'])) {
    // Check if user is a guardian
    $sql = "SELECT r.*, u.name as child_name 
            FROM relation r 
            JOIN user u ON r.child_id = u.ID 
            WHERE r.guardian_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $guardian_relations = $stmt->get_result();

    // Check if user is a child
    $sql = "SELECT r.*, u.name as guardian_name 
            FROM relation r 
            JOIN user u ON r.guardian_id = u.ID 
            WHERE r.child_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $child_relations = $stmt->get_result();
}

// Handle relation update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_relation'])) {
    $relation = $_POST['relation'];
    $child_id = $_POST['child_id'];
    $guardian_id = $_POST['guardian_id'];
    
    $sql = "UPDATE relation SET relation = ? WHERE child_id = ? AND guardian_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $relation, $child_id, $guardian_id);
    
    if ($stmt->execute()) {
        $success = "Relationship updated successfully!";
    } else {
        $error = "Error updating relationship.";
    }
}
?>

<div class="container mt-4">
    <div class="row">
<div class="col-md-4">
    <div class="card">
        <div class="card-body text-center">
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
            
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($user['Area']); ?></p>
            
            <a href="edit_profile.php" class="btn btn-primary mb-3">Edit Profile</a>
            
            <div class="mt-3 text-start">
                <h5>Details</h5>
                <p><strong>Age:</strong> 
                    <?php 
                        $dob = new DateTime($user['DOB']);
                        $now = new DateTime();
                        $age = $now->diff($dob)->y;
                        echo $age;
                    ?>
                </p>
                <p><strong>Area:</strong> <?php echo htmlspecialchars($user['Area']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($user['Contact']); ?></p>
                <?php if ($user['Email']): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['Email']); ?></p>
                <?php endif; ?>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($user['Emergency_Contact']); ?></p>
                <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($user['Description'])); ?></p>
            </div>

            <?php if ($guardian_relations && $guardian_relations->num_rows > 0): ?>
                <div class="mt-4">
                    <h5>Children Under Care</h5>
                    <div class="list-group text-start">
                        <?php while ($relation = $guardian_relations->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($relation['child_name']); ?></h6>
                                        <p class="mb-1">Relation: <?php echo htmlspecialchars($relation['relation']); ?></p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editRelationModal<?php echo $relation['child_id']; ?>">
                                        Edit Relation
                                    </button>
                                </div>
                            </div>

                            <!-- Edit Relation Modal -->
                            <div class="modal fade" id="editRelationModal<?php echo $relation['child_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Relationship</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="child_id" value="<?php echo $relation['child_id']; ?>">
                                                <input type="hidden" name="guardian_id" value="<?php echo $_SESSION['user_id']; ?>">
                                                <div class="mb-3">
                                                    <label for="relation" class="form-label">Relation</label>
                                                    <select class="form-select" name="relation" required>
                                                        <option value="child" <?php echo $relation['relation'] == 'child' ? 'selected' : ''; ?>>Child</option>
                                                        <option value="adopted" <?php echo $relation['relation'] == 'adopted' ? 'selected' : ''; ?>>Adopted Child</option>
                                                        <option value="foster" <?php echo $relation['relation'] == 'foster' ? 'selected' : ''; ?>>Foster Child</option>
                                                        <option value="step" <?php echo $relation['relation'] == 'step' ? 'selected' : ''; ?>>Step Child</option>
                                                        <option value="grandchild" <?php echo $relation['relation'] == 'grandchild' ? 'selected' : ''; ?>>Grandchild</option>
                                                        <option value="niece" <?php echo $relation['relation'] == 'niece' ? 'selected' : ''; ?>>Niece</option>
                                                        <option value="nephew" <?php echo $relation['relation'] == 'nephew' ? 'selected' : ''; ?>>Nephew</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_relation" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($child_relations && $child_relations->num_rows > 0): ?>
                <div class="mt-4">
                    <h5>Guardians</h5>
                    <div class="list-group text-start">
                        <?php while ($relation = $child_relations->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($relation['guardian_name']); ?></h6>
                                        <p class="mb-1">Relation: <?php echo htmlspecialchars($relation['relation']); ?></p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editRelationModal<?php echo $relation['guardian_id']; ?>">
                                        Edit Relation
                                    </button>
                                </div>
                            </div>

                            <!-- Edit Relation Modal -->
                            <div class="modal fade" id="editRelationModal<?php echo $relation['guardian_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Relationship</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="child_id" value="<?php echo $_SESSION['user_id']; ?>">
                                                <input type="hidden" name="guardian_id" value="<?php echo $relation['guardian_id']; ?>">
                                                <div class="mb-3">
                                                    <label for="relation" class="form-label">Relation</label>
                                                    <select class="form-select" name="relation" required>
                                                        <option value="child" <?php echo $relation['relation'] == 'child' ? 'selected' : ''; ?>>Child</option>
                                                        <option value="adopted" <?php echo $relation['relation'] == 'adopted' ? 'selected' : ''; ?>>Adopted Child</option>
                                                        <option value="foster" <?php echo $relation['relation'] == 'foster' ? 'selected' : ''; ?>>Foster Child</option>
                                                        <option value="step" <?php echo $relation['relation'] == 'step' ? 'selected' : ''; ?>>Step Child</option>
                                                        <option value="grandchild" <?php echo $relation['relation'] == 'grandchild' ? 'selected' : ''; ?>>Grandchild</option>
                                                        <option value="niece" <?php echo $relation['relation'] == 'niece' ? 'selected' : ''; ?>>Niece</option>
                                                        <option value="nephew" <?php echo $relation['relation'] == 'nephew' ? 'selected' : ''; ?>>Nephew</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_relation" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My Alerts</h4>
                    <a href="create_alert.php" class="btn btn-primary">Create New Alert</a>
                </div>
                <div class="card-body">
                    <?php if (empty($alerts)): ?>
                        <p class="text-center">No alerts posted yet.</p>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($alert['title']); ?></h5>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($alert['Details'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-<?php echo $alert['Status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($alert['Status']); ?>
                                            </span>
                                            <small class="text-muted ms-2">
                                                Posted on: <?php echo date('F j, Y g:i A', strtotime($alert['Alert_datetime'])); ?>
                                            </small>
                                        </div>
                                        <a href="view_alert.php?id=<?php echo $alert['Alert_ID']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?> 