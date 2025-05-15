<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please log in to join a group.']);
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
        
        // Add user to group members
        $sql = "INSERT INTO volunteer_name_group_members (group_id, NID) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $group_id, $user['NID']);
        $stmt->execute();
        
        // Update user's group_id
        $sql = "UPDATE user SET Group_ID = ? WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['message' => 'Successfully joined the group!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Error joining group: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?> 