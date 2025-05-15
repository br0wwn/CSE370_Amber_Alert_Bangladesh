<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please log in to leave a group.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $group_id = (int)$_POST['group_id'];
    $user_id = $_SESSION['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get user's NID
        $sql = "SELECT NID FROM user WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("User not found.");
        }
        
        // Remove user from group members
        $sql = "DELETE FROM volunteer_name_group_members WHERE group_id = ? AND NID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $group_id, $user['NID']);
        $stmt->execute();
        
        // Update user's group_id to NULL
        $sql = "UPDATE user SET Group_ID = NULL WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['message' => 'Successfully left the group!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Error leaving group: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?> 