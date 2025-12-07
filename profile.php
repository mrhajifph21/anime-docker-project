<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "info";

// --- Ambil data user saat ini ---
$sql_user = "SELECT username, birthdate, profile_picture FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();
$stmt_user->close();

$current_birthdate = $user_data['birthdate'];
$current_picture = $user_data['profile_picture'];

// --- Proses Update Tanggal Lahir ---
if (isset($_POST['update_birthdate'])) {
    $birthdate_input = $_POST['birthdate'];
    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $birthdate_input)) {
        $birthDate = new DateTime($birthdate_input);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
        $is_verified = ($age >= 18) ? 1 : 0;
        $sql_update_bday = "UPDATE users SET birthdate = ?, is_age_verified = ? WHERE id = ?";
        $stmt_update_bday = $conn->prepare($sql_update_bday);
        $stmt_update_bday->bind_param("sii", $birthdate_input, $is_verified, $user_id);
        if ($stmt_update_bday->execute()) {
            $message = "Birthdate updated successfully.";
            if ($is_verified) {
                $message .= " Your age is verified.";
                $_SESSION['is_age_verified'] = 1;
            } else {
                 $message .= " Age not yet verified (under 18).";
                 $_SESSION['is_age_verified'] = 0;
            }
            $message_type = "success";
            $current_birthdate = $birthdate_input;
        } else {
            $message = "Error updating birthdate: " . $stmt_update_bday->error;
            $message_type = "danger";
        }
        $stmt_update_bday->close();
    } else {
        $message = "Invalid date format. Please use YYYY-MM-DD.";
        $message_type = "danger";
    }
}

// --- Proses Upload Foto Profil ---
if (isset($_POST['upload_picture']) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $target_dir = "assets/avatars/";
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $user_id . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    $check = @getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check === false) { $message = "File is not an image."; $message_type = "danger"; $uploadOk = 0; }
    if ($_FILES["avatar"]["size"] > 1000000) { $message = "Sorry, your file is too large (Max 1MB)."; $message_type = "danger"; $uploadOk = 0; }
    if(!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) { $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed."; $message_type = "danger"; $uploadOk = 0; }

    if ($uploadOk == 1) {
         if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            if ($current_picture && $current_picture != 'default_avatar.png' && file_exists($target_dir . $current_picture)) { @unlink($target_dir . $current_picture); }
            $sql_update_pic = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt_update_pic = $conn->prepare($sql_update_pic);
            $stmt_update_pic->bind_param("si", $new_filename, $user_id);
            if($stmt_update_pic->execute()) {
                 $message = "Profile picture uploaded successfully.";
                 $message_type = "success";
                 $_SESSION['profile_picture'] = $new_filename;
                 $current_picture = $new_filename;
            } else {
                 $message = "Error updating database: " . $stmt_update_pic->error; $message_type = "danger"; @unlink($target_file);
            }
             $stmt_update_pic->close();
        } else { $message = "Sorry, there was an error uploading your file."; $message_type = "danger"; }
    }
} elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] != 0 && $_FILES['avatar']['error'] != 4) {
     $message = "Upload error code: " . $_FILES['avatar']['error']; $message_type = "danger";
}
$conn->close();

// --- Siapkan path gambar profil ---
$display_pic_path = 'assets/avatars/default_avatar.png';
if (!empty($current_picture) && file_exists('assets/avatars/' . $current_picture)) {
    $display_pic_path = 'assets/avatars/' . $current_picture;
} elseif (!empty($_SESSION['profile_picture']) && file_exists('assets/avatars/' . $_SESSION['profile_picture'])) {
    $display_pic_path = 'assets/avatars/' . $_SESSION['profile_picture'];
}
if (!file_exists($display_pic_path) && file_exists('assets/avatars/default_avatar.png')) {
    $display_pic_path = 'assets/avatars/default_avatar.png';
} elseif (!file_exists($display_pic_path) && !file_exists('assets/avatars/default_avatar.png')) {
    $display_pic_path = '#';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - IAmWeeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background-color: #101010; }
        .card { background-color: #202020; }
        .btn-danger { background-color: #ff4757; border-color: #ff4757; }
        .profile-pic { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #333; }
        a { color: #ff4757; text-decoration: none;}
        .navbar-brand { font-size: 1.5rem; color: #ff4757 !important;}
        .navbar-nav .nav-link { font-size: 1.1rem; padding: 0.5rem 0.75rem; }
        .dropdown-menu { background-color: #202020; }
        .dropdown-item { color: white; }
        .dropdown-item:hover { background-color: #333; }
        .dropdown-item.active { background-color: #ff4757; }
        .navbar-brand:hover, .navbar-brand:focus { color: #ff4757 !important; }
         /* Animasi loading untuk Surprise Me */
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .anim-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="text-white">

    <nav class="navbar navbar-expand-lg" style="background-color: #202020;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">IAmWeeb</a>
             <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"> <span class="navbar-toggler-icon"></span> </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                 <ul class="navbar-nav me-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php" title="Home"><i class="bi bi-house-fill fs-5"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php" title="Search"><i class="bi bi-search fs-5"></i></a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-tags-fill"></i> Genres</a>
                        <ul class="dropdown-menu"><li><a class="dropdown-item" href="index.php">Go to Home for Genres</a></li></ul>
                    </li>
                    <li class="nav-item"> <a class="nav-link" href="#" id="surprise-me-button" title="Surprise Me!"><i class="bi bi-shuffle fs-5"></i></a> </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                             <img src="<?php echo $display_pic_path; ?>" alt="Profile" width="32" height="32" class="rounded-circle me-1 border border-secondary">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h6></li>
                            <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person-badge-fill"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="watchlist.php"><i class="bi bi-list-task"></i> My Watchlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow p-4">
                    <div class="card-body text-center">
                        <h1 class="mb-4" style="color: #ff4757;">My Profile</h1>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <img src="<?php echo $display_pic_path; ?>" alt="Profile Picture" class="profile-pic mb-3">
                        <h4 class="mb-4"><?php echo htmlspecialchars($user_data['username']); ?></h4>

                        <form action="profile.php" method="post" enctype="multipart/form-data" class="mb-4">
                             <div class="mb-3">
                                <label for="avatar" class="form-label">Change Profile Picture (Max 1MB: JPG, PNG, GIF)</label>
                                <input class="form-control form-control-sm" type="file" id="avatar" name="avatar" accept="image/png, image/jpeg, image/gif">
                             </div>
                             <button type="submit" name="upload_picture" class="btn btn-secondary btn-sm"><i class="bi bi-upload"></i> Upload Picture</button>
                        </form>
                        <hr>
                        <form action="profile.php" method="post" class="mt-4">
                             <div class="mb-3 text-start">
                                 <label for="birthdate" class="form-label">Birthdate (YYYY-MM-DD) - For Age Verification (18+)</label>
                                 <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($current_birthdate); ?>" required max="<?php echo date('Y-m-d'); ?>">
                             </div>
                             <button type="submit" name="update_birthdate" class="btn btn-danger"><i class="bi bi-calendar-check"></i> Update Birthdate</button>
                             <?php if (isset($_SESSION['is_age_verified']) && $_SESSION['is_age_verified'] == 1): ?>
                                <p class="text-success mt-2 mb-0"><i class="bi bi-patch-check-fill"></i> Age Verified (18+)</p>
                             <?php elseif ($current_birthdate): ?>
                                 <p class="text-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Age Not Verified (Under 18)</p>
                             <?php else: ?>
                                <p class="text-muted mt-2 mb-0">Please set your birthdate for age verification.</p>
                             <?php endif; ?>
                        </form>
                        <div class="text-center mt-5"> <a href="index.php"><i class="bi bi-arrow-left-circle"></i> Back to Home</a> </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-muted p-4 mt-5" style="background-color: #0c0c0c;"> &copy; <?php echo date("Y"); ?> IAmWeeb Project </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Script untuk tombol Surprise Me (AKTIF)
            const surpriseBtn = document.getElementById('surprise-me-button');
            if(surpriseBtn) {
                surpriseBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // Mencegah link default
                    // Tampilkan loading sederhana (opsional)
                    this.innerHTML = '<i class="bi bi-arrow-clockwise fs-5 anim-spin"></i>'; // Ganti ikon jadi loading
                    // Redirect ke surprise.php
                    window.location.href = 'surprise.php';
                });
            }
            // Tambahkan sedikit CSS untuk animasi loading (opsional)
            const style = document.createElement('style');
            style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }`;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>