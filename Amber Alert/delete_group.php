<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is police
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'police') {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $group_id = (int)$_POST['group_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete group members
        $stmt = $conn->prepare("DELETE FROM volunteer_name_group_members WHERE group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        // Set Group_ID to NULL for users in this group
        $stmt = $conn->prepare("UPDATE user SET Group_ID = NULL WHERE Group_ID = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        // Delete quick_respond_to entries
        $stmt = $conn->prepare("DELETE FROM quick_respond_to WHERE group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        // Delete the group
        $stmt = $conn->prepare("DELETE FROM volunteer_name_group WHERE group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['message' => 'Group deleted successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Error deleting group: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?>
