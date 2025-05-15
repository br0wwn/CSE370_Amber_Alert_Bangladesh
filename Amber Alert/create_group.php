<?php
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $group_name = trim($_POST['group_name']);
    $area = trim($_POST['area']);
    $description = trim($_POST['description']);

    // Check user age before creating group
    $dob = isset($user['DOB']) ? new DateTime($user['DOB']) : null;
    $age = null;
    if ($dob) {
        $today = new DateTime();
        $age = $dob->diff($today)->y;
    }
    if ($age === null) {
        $error = "Unable to determine your age. Please update your profile.";
    } elseif ($age < 18) {
        $error = "You must be over 18 years old to create a volunteer group.";
    } else {
        // Validate input
        if (empty($group_name) || empty($area)) {
            $error = "Group name and area are required.";
        } else {
            // Check if group name already exists in the area
            $sql = "SELECT * FROM volunteer_name_group WHERE group_name = ? AND area = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $group_name, $area);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = "A group with this name already exists in this area.";
            } else {
                // Create the group
                $sql = "INSERT INTO volunteer_name_group (group_name, area) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $group_name, $area);

                if ($stmt->execute()) {
                    $group_id = $conn->insert_id;

                    // Update user's group_id
                    $sql = "UPDATE user SET Group_ID = ? WHERE ID = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $group_id, $user_id);

                    if ($stmt->execute()) {
                        // Add creator to volunteer_name_group_members
                        $sql = "SELECT NID FROM user WHERE ID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user_data = $result->fetch_assoc();
                        $nid = $user_data['NID'];

                        $sql = "INSERT INTO volunteer_name_group_members (group_id, NID) VALUES (?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("is", $group_id, $nid);
                        $stmt->execute();

                        $success = "Group created successfully!";
                        // Refresh user data
                        $user['Group_ID'] = $group_id;
                    } else {
                        $error = "Error updating user group. Please try again.";
                    }
                } else {
                    $error = "Error creating group. Please try again.";
                }
            }
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Create New Volunteer Group</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" required
                                   value="<?php echo isset($_POST['group_name']) ? htmlspecialchars($_POST['group_name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="area" class="form-label">Area</label>
                            <input type="text" class="form-control" id="area" name="area" required
                                   value="<?php echo isset($_POST['area']) ? htmlspecialchars($_POST['area']) : htmlspecialchars($user['Area']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Create Group</button>
                            <a href="group_feed.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 