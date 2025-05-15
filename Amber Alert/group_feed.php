<?php
require_once 'includes/header.php';

// Fetch distinct areas for filter dropdown
$area_sql = "SELECT DISTINCT area FROM volunteer_name_group ORDER BY area ASC";
$area_result = $conn->query($area_sql);
$areas = [];
if ($area_result) {
    while ($row = $area_result->fetch_assoc()) {
        $areas[] = $row['area'];
    }
}

// Get selected area from GET parameter, default to empty (all areas)
$selected_area = isset($_GET['area']) ? $_GET['area'] : '';

$show_only_user_group = false;
if (isset($user['Group_ID']) && $user['Group_ID']) {
    $show_only_user_group = true;
    // Fetch only the user's group with member count
    $stmt = $conn->prepare("SELECT vng.group_id, vng.group_name, vng.area, COUNT(vngm.NID) AS member_count
                            FROM volunteer_name_group vng
                            LEFT JOIN volunteer_name_group_members vngm ON vng.group_id = vngm.group_id
                            WHERE vng.group_id = ?
                            GROUP BY vng.group_id");
    $stmt->bind_param("i", $user['Group_ID']);
} else {
    // Prepare SQL to fetch groups with member count, filtered by area if selected
    if ($selected_area && in_array($selected_area, $areas)) {
        $stmt = $conn->prepare("SELECT vng.group_id, vng.group_name, vng.area, COUNT(vngm.NID) AS member_count
                                FROM volunteer_name_group vng
                                LEFT JOIN volunteer_name_group_members vngm ON vng.group_id = vngm.group_id
                                WHERE vng.area = ?
                                GROUP BY vng.group_id
                                ORDER BY vng.group_name ASC");
        $stmt->bind_param("s", $selected_area);
    } else {
        $stmt = $conn->prepare("SELECT vng.group_id, vng.group_name, vng.area, COUNT(vngm.NID) AS member_count
                                FROM volunteer_name_group vng
                                LEFT JOIN volunteer_name_group_members vngm ON vng.group_id = vngm.group_id
                                GROUP BY vng.group_id
                                ORDER BY vng.group_name ASC");
    }
}
$stmt->execute();
$groups_result = $stmt->get_result();
?>

<div class="container mt-4">
    <h3>Volunteer Groups</h3>

    <?php if ($user_type !== 'police' && (!isset($user['Group_ID']) || !$user['Group_ID'])): ?>
        <div class="mb-3">
            <a href="create_group.php" class="btn btn-success">Create Group</a>
        </div>
    <?php endif; ?>

    <form method="GET" class="mb-3">
        <label for="areaFilter" class="form-label">Filter by Area:</label>
        <select id="areaFilter" name="area" class="form-select" onchange="this.form.submit()">
            <option value="">All Areas</option>
            <?php foreach ($areas as $area): ?>
                <option value="<?php echo htmlspecialchars($area); ?>" <?php if ($area === $selected_area) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($area); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($groups_result->num_rows > 0): ?>
        <div class="list-group">
            <?php while ($group = $groups_result->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h5>
                        <p class="mb-1">
                            <strong>Area:</strong> <?php echo htmlspecialchars($group['area']); ?><br>
                            <strong>Members:</strong> <?php echo $group['member_count']; ?>
                        </p>
                    </div>
                <div>
<?php if ($user_type === 'police'): ?>
    <a href="alert_assignment.php?group_id=<?php echo $group['group_id']; ?>" class="btn btn-warning btn-sm">Assign</a>
    <button class="btn btn-danger btn-sm" onclick="deleteGroup(<?php echo $group['group_id']; ?>)">Delete</button>
<?php else: ?>
    <?php if (isset($user['Group_ID']) && $user['Group_ID'] == $group['group_id']): ?>
        <button class="btn btn-danger btn-sm" onclick="leaveGroup(<?php echo $group['group_id']; ?>)">Leave Group</button>
    <?php else: ?>
        <button class="btn btn-primary btn-sm" onclick="joinGroup(<?php echo $group['group_id']; ?>)">Join Group</button>
    <?php endif; ?>
<?php endif; ?>
                </div>
                </div>
                <?php if (isset($user['Group_ID']) && $user['Group_ID'] == $group['group_id']): ?>
                    <?php
                    // Fetch detailed group info
                    $detail_stmt = $conn->prepare("SELECT * FROM volunteer_name_group WHERE group_id = ?");
                    $detail_stmt->bind_param("i", $group['group_id']);
                    $detail_stmt->execute();
                    $group_info = $detail_stmt->get_result()->fetch_assoc();
                    $detail_stmt->close();

                    // Fetch group members
                    $members_stmt = $conn->prepare("SELECT u.name, u.Contact FROM user u JOIN volunteer_name_group_members vngm ON u.NID = vngm.NID WHERE vngm.group_id = ?");
                    $members_stmt->bind_param("i", $group['group_id']);
                    $members_stmt->execute();
                    $group_members = $members_stmt->get_result();
                    $members_stmt->close();

                    // Fetch assigned alerts
                    $alerts_stmt = $conn->prepare("SELECT a.* FROM Alert a JOIN quick_respond_to q ON a.Alert_ID = q.alert_id WHERE q.group_id = ? ORDER BY a.Alert_datetime DESC");
                    $alerts_stmt->bind_param("i", $group['group_id']);
                    $alerts_stmt->execute();
                    $group_alerts = $alerts_stmt->get_result();
                    $alerts_stmt->close();
                    ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6>Group Details: <?php echo htmlspecialchars($group_info['group_name']); ?></h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($group_info['area']); ?></p>
                            <h6>Members (<?php echo $group['member_count']; ?>):</h6>
                            <?php if ($group_members->num_rows > 0): ?>
                                <ul>
                                    <?php while ($member = $group_members->fetch_assoc()): ?>
                                        <li><?php echo htmlspecialchars($member['name']) . ' - ' . htmlspecialchars($member['Contact']); ?></li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p>No members in this group.</p>
                            <?php endif; ?>
                            <h6>Assigned Alerts:</h6>
                            <?php if ($group_alerts->num_rows > 0): ?>
                                <ul>
                                    <?php while ($alert = $group_alerts->fetch_assoc()): ?>
                                        <li>
                                            <a href="view_alert.php?id=<?php echo $alert['Alert_ID']; ?>">
                                                <?php echo htmlspecialchars($alert['title']); ?> (<?php echo date('F j, Y g:i A', strtotime($alert['Alert_datetime'])); ?>)
                                            </a>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p>No alerts assigned to this group.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No groups found<?php echo $selected_area ? ' in the selected area.' : '.'; ?></p>
    <?php endif; ?>
</div>

<script>
function joinGroup(groupId) {
    // Check if user is over 18 before joining group
    <?php
    $dob = isset($user['DOB']) ? new DateTime($user['DOB']) : null;
    $age = null;
    if ($dob) {
        $today = new DateTime();
        $age = $dob->diff($today)->y;
    }
    ?>
    const userAge = <?php echo $age !== null ? $age : 'null'; ?>;
    if (userAge === null) {
        alert('Unable to determine your age. Please update your profile.');
        return;
    }
    if (userAge < 18) {
        alert('You must be over 18 years old to join any volunteer group.');
        return;
    }

    if (confirm('Are you sure you want to join this group?')) {
        fetch('join_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'group_id=' + groupId
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    }
}

function leaveGroup(groupId) {
    if (confirm('Are you sure you want to leave this group?')) {
        fetch('leave_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'group_id=' + groupId
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    }
}
function leaveGroup(groupId) {
    if (confirm('Are you sure you want to leave this group?')) {
        fetch('leave_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'group_id=' + groupId
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    }
}

function deleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        fetch('delete_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'group_id=' + groupId
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
