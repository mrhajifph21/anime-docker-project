<?php
include 'config.php'; 
$message = "";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // --- UBAH QUERY: Ambil juga is_age_verified dan profile_picture ---
    $sql = "SELECT id, password, is_age_verified, profile_picture FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Sukses! Simpan data user ke session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            // --- TAMBAHAN: Simpan status umur & gambar ke session ---
            $_SESSION['is_age_verified'] = $user['is_age_verified'];
            $_SESSION['profile_picture'] = $user['profile_picture']; // Bisa NULL atau nama file
            // ----------------------------------------------------
            
            header("Location: index.php"); 
            exit;
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "Username not found.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IAmWeeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background-color: #101010; }
        .card { background-color: #202020; }
        .btn-danger { background-color: #ff4757; border-color: #ff4757; }
        a { color: #ff4757; text-decoration: none;}
    </style>
</head>
<body class="text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4" style="margin-top: 10vh;">
                <div class="card shadow p-4">
                    <div class="card-body">
                        <h1 class="text-center mb-4" style="color: #ff4757;">Login</h1>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-danger"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="username" class="form-label"><i class="bi bi-person-fill"></i> Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><i class="bi bi-key-fill"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="submit" class="btn btn-danger w-100"><i class="bi bi-box-arrow-in-right"></i> Login</button>
                        </form>
                        <div class="text-center mt-3">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
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