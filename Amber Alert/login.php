<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login_type'])) {
        if ($_POST['login_type'] == 'user') {
            // User login
            $email_phone = $_POST['email_phone'];
            $password = $_POST['password'];
            
            $sql = "SELECT * FROM user WHERE (Email = ? OR Contact = ?) AND password = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $email_phone, $email_phone, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['user_type'] = $user['user_type'];
                header("Location: /Amber Alert/alert_feed.php");
                exit();
            } else {
                $error = "Invalid credentials";
            }
        } else {
            // Police login
            $station_id = $_POST['station_id'];
            $password = $_POST['password'];
            
            $sql = "SELECT * FROM thana WHERE station_id = ? AND password = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $station_id, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            
if ($result->num_rows == 1) {
    $thana = $result->fetch_assoc();
    $_SESSION['station_id'] = $thana['station_id'];
    $_SESSION['user_type'] = 'police';
    header("Location: /Amber Alert/alert_feed.php");
    exit();
} else {
    $error = "Invalid credentials";
}
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Amber Alert Bangladesh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/Amber Alert/styles/auth.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-type-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="auth-theme">
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h2>Amber Alert Bangladesh</h2>
                <h4>Login</h4>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="login-type-btn">
                <button class="btn btn-primary w-100" id="userLoginBtn">User Login</button>
                <button class="btn btn-secondary w-100 mt-2" id="policeLoginBtn">Police Login</button>
            </div>

            <form id="userLoginForm" method="POST" action="">
                <input type="hidden" name="login_type" value="user">
                <div class="mb-3">
                    <label for="email_phone" class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-control" id="email_phone" name="email_phone" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <form id="policeLoginForm" method="POST" action="" style="display: none;">
                <input type="hidden" name="login_type" value="police">
                <div class="mb-3">
                    <label for="station_id" class="form-label">Station ID</label>
                    <input type="number" class="form-control" id="station_id" name="station_id" required>
                </div>
                <div class="mb-3">
                    <label for="police_password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="police_password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="text-center mt-3">
                <p>Don't have an account? <a href="/Amber Alert/signup.php">Sign up</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('userLoginBtn').addEventListener('click', function() {
            document.getElementById('userLoginForm').style.display = 'block';
            document.getElementById('policeLoginForm').style.display = 'none';
            this.classList.add('btn-primary');
            this.classList.remove('btn-secondary');
            document.getElementById('policeLoginBtn').classList.remove('btn-primary');
            document.getElementById('policeLoginBtn').classList.add('btn-secondary');
        });

        document.getElementById('policeLoginBtn').addEventListener('click', function() {
            document.getElementById('policeLoginForm').style.display = 'block';
            document.getElementById('userLoginForm').style.display = 'none';
            this.classList.add('btn-primary');
            this.classList.remove('btn-secondary');
            document.getElementById('userLoginBtn').classList.remove('btn-primary');
            document.getElementById('userLoginBtn').classList.add('btn-secondary');
        });
    </script>
</body>
</html> 