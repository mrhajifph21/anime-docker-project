<?php
include 'config.php';
$message = ""; 
$message_type = "danger"; 

if (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "Username and password cannot be empty.";
    } else {
        $sql_check = "SELECT id FROM users WHERE username = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result(); 

        if ($stmt_check->num_rows > 0) {
            $message = "Username already taken. Please try another.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_insert = "INSERT INTO users (username, password) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ss", $username, $hashed_password);

            if ($stmt_insert->execute()) {
                $message = "Registration successful! Please <a href='login.php' class='alert-link'>Login</a>.";
                $message_type = "success"; 
            } else {
                $message = "Registration failed: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - IAmWeeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background-color: #101010; }
        .card { background-color: #202020; }
        .btn-danger { background-color: #ff4757; border-color: #ff4757; }
        a { color: #ff4757; }
    </style>
</head>
<body class="text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4" style="margin-top: 10vh;">
                <div class="card shadow p-4">
                    <div class="card-body">
                        <h1 class="text-center mb-4" style="color: #ff4757;">Register</h1>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="register.php">
                            <div class="mb-3">
                                <label for="username" class="form-label"><i class="bi bi-person-plus-fill"></i> Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><i class="bi bi-key-fill"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="submit" class="btn btn-danger w-100"><i class="bi bi-check-circle-fill"></i> Register</button>
                        </form>
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <a href="index.php">← Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>