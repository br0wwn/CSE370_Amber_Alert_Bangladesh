<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $nid = $_POST['nid'];
    $dob = $_POST['dob'];
    $description = $_POST['description'];
    $area = $_POST['area'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $emergency_contact = $_POST['emergency_contact'];
    $password = $_POST['password'];
    
    // Calculate age
    $today = new DateTime();
    $birthdate = new DateTime($dob);
    $age = $birthdate->diff($today)->y;
    
    // Check if user is a child (under 18)
    $is_child = ($age < 18);

    // If child, set nid to null or empty string
    if ($is_child) {
        $nid = null;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into user table
        $sql = "INSERT INTO user (name, NID, DOB, Description, Area, Contact, Email, Emergency_Contact, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $name, $nid, $dob, $description, $area, $contact, $email, $emergency_contact, $password);
        $stmt->execute();
        
        $user_id = $conn->insert_id;
        
        if ($is_child) {
            // Insert into child table
            $father_name = $_POST['father_name'];
            $mother_name = $_POST['mother_name'];
            $guardian_name = $_POST['guardian_name'];
            
            // Check if guardian exists in user table
            $sql = "SELECT ID FROM user WHERE name = ? AND Contact = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $guardian_name, $emergency_contact);
            $stmt->execute();
            $guardian_result = $stmt->get_result();
            
            if ($guardian_result->num_rows > 0) {
                $guardian_id = $guardian_result->fetch_assoc()['ID'];
                
                // Insert into child table
                $sql = "INSERT INTO child (id, father_name, mother_name, guardian_name) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isss", $user_id, $father_name, $mother_name, $guardian_name);
                $stmt->execute();
                
                // Check if guardian exists in guardian table, if not insert
                $sql = "SELECT id FROM guardian WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $guardian_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows == 0) {
                    $sql = "INSERT INTO guardian (id) VALUES (?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $guardian_id);
                    $stmt->execute();
                }
                
                // Insert into relation table with typed relation
                $relation = $_POST['relation'];
                $sql = "INSERT INTO relation (child_id, guardian_id, relation) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $user_id, $guardian_id, $relation);
                $stmt->execute();
            } else {
                throw new Exception("Guardian not found. Please ensure the guardian is registered and the contact number matches their emergency contact.");
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: login.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Amber Alert Bangladesh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/Amber Alert/styles/auth.css">
</head>
<body class="auth-theme">
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="signup-container auth-container">
            <div class="signup-header">
                <h2>Amber Alert Bangladesh</h2>
                <h4>Sign Up</h4>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                
                <div class="mb-3">
                    <label for="nid" class="form-label">NID Number</label>
                    <input type="text" class="form-control" id="nid" name="nid" required>
                </div>
                
                <div class="mb-3">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="area" class="form-label">Area</label>
                    <input type="text" class="form-control" id="area" name="area" required>
                </div>
                
                <div class="mb-3">
                    <label for="contact" class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" id="contact" name="contact" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                
                <div class="mb-3">
                    <label for="emergency_contact" class="form-label">Emergency Contact Number</label>
                    <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <!-- Child-specific fields (hidden by default) -->
                <div id="childFields" style="display: none;">
                    <h5 class="mt-4">Guardian Information (Required for minors)</h5>
                    <div class="mb-3">
                        <label for="father_name" class="form-label">Father's Name</label>
                        <input type="text" class="form-control" id="father_name" name="father_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="mother_name" class="form-label">Mother's Name</label>
                        <input type="text" class="form-control" id="mother_name" name="mother_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="guardian_name" class="form-label">Guardian's Name</label>
                        <input type="text" class="form-control" id="guardian_name" name="guardian_name">
                        <small class="text-muted">Must match the name of a registered guardian</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="relation" class="form-label">Relation with Guardian</label>
                        <input type="text" class="form-control" id="relation" name="relation">
                        <small class="text-muted">e.g., son, daughter, nephew, niece, etc.</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            </form>

            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('dob').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            
            const childFields = document.getElementById('childFields');
            const childInputs = childFields.querySelectorAll('input');
            const nidInput = document.getElementById('nid');
            
            if (age < 18) {
                childFields.style.display = 'block';
                childInputs.forEach(input => input.required = true);
                nidInput.required = false;
            } else {
                childFields.style.display = 'none';
                childInputs.forEach(input => input.required = false);
                nidInput.required = true;
            }
        });
    </script>
</body>
</html> 