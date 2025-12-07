<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

// --- Siapkan Path Gambar Profil ---
$display_pic_path = 'assets/avatars/default_avatar.png'; /* ... kode path gambar profil ... */
if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture']) && file_exists('assets/avatars/' . $_SESSION['profile_picture'])) { $display_pic_path = 'assets/avatars/' . $_SESSION['profile_picture']; } if (!file_exists($display_pic_path) && file_exists('assets/avatars/default_avatar.png')) { $display_pic_path = 'assets/avatars/default_avatar.png'; } elseif (!file_exists($display_pic_path) && !file_exists('assets/avatars/default_avatar.png')) { $display_pic_path = '#'; }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Watchlist - IAmWeeb</title>
    <link rel="icon" type="image/png" href="/anime-recommendation/assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* --- HAPUS Style untuk Overlay Sinopsis --- */
        /* .card.position-relative { ... } */
        /* .synopsis-overlay { ... } */
        /* .synopsis-text { ... } */
        /* .card:hover .synopsis-overlay { ... } */
        /* -------------------------------------- */
        body { background-color: #101010; } .card-img-top { height: 350px; object-fit: cover; } .card { background-color: #202020; border: 1px solid #333; } .navbar-brand, .btn-danger { color: #ff4757 !important; } .btn-danger { background-color: #ff4757; color: white; border-color: #ff4757;} .btn-outline-danger { color: #ff4757; border-color: #ff4757; } .btn-outline-danger:hover { background-color: #ff4757; color: white; } .card-title { color: #ff4757; height: 48px; overflow: hidden;} .dropdown-menu { background-color: #202020; } .dropdown-item { color: white; } .dropdown-item:hover { background-color: #333; } .dropdown-item.active { background-color: #ff4757; } .navbar-nav .nav-link { font-size: 1.1rem; padding: 0.5rem 0.75rem; } .navbar-brand { font-size: 1.5rem; } .navbar-brand:hover, .navbar-brand:focus { color: #ff4757 !important; } @keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="text-white">

    <nav class="navbar navbar-expand-lg" style="background-color: #202020;"> <div class="container-fluid"> <a class="navbar-brand fw-bold" href="index.php">IAmWeeb</a> <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"> <span class="navbar-toggler-icon"></span> </button> <div class="collapse navbar-collapse" id="navbarNav"> <ul class="navbar-nav me-auto align-items-center"> <li class="nav-item"><a class="nav-link" href="index.php" title="Home"><i class="bi bi-house-fill fs-5"></i></a></li> <li class="nav-item"><a class="nav-link" href="index.php" title="Search"><i class="bi bi-search fs-5"></i></a></li> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-tags-fill"></i> Genres</a> <ul class="dropdown-menu"><li><a class="dropdown-item" href="index.php">Go to Home for Genres</a></li></ul> </li> <li class="nav-item"> <a class="nav-link" href="#" id="surprise-me-button" title="Surprise Me!"><i class="bi bi-shuffle fs-5"></i></a> </li> </ul> <ul class="navbar-nav ms-auto"> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <img src="<?php echo $display_pic_path; ?>" alt="Profile" width="32" height="32" class="rounded-circle me-1 border border-secondary"> </a> <ul class="dropdown-menu dropdown-menu-end"> <li><h6 class="dropdown-header">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h6></li> <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-badge-fill"></i> My Profile</a></li> <li><a class="dropdown-item active" href="watchlist.php"><i class="bi bi-list-task"></i> My Watchlist</a></li> <li><hr class="dropdown-divider"></li> <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li> </ul> </li> </ul> </div> </div> </nav>

    <div class="container mt-4"> <h1 class="mb-4" style="color: #ff4757;">My Watchlist</h1> <a href="index.php" class="btn btn-outline-danger mb-4"><i class="bi bi-arrow-left-circle"></i> Back to Home</a> <div class="row g-4"> <?php $sql = "SELECT a.id, a.title, a.genre, a.rating, a.image, a.mal_id, a.description FROM animes a JOIN watchlist w ON a.id = w.anime_id WHERE w.user_id = ? ORDER BY a.title"; $stmt = $conn->prepare($sql); $stmt->bind_param("i", $user_id); $stmt->execute(); $result = $stmt->get_result(); if ($result->num_rows > 0): while($row = $result->fetch_assoc()): $image_path = htmlspecialchars($row['image']); if (empty($image_path) || !filter_var($image_path, FILTER_VALIDATE_URL)) { $image_path = 'assets/img/default.jpg'; if (!file_exists($image_path)) $image_path = '#'; } $title = htmlspecialchars($row['title']); $genres = htmlspecialchars($row['genre']); $rating = htmlspecialchars($row['rating']); $local_anime_id = $row['id']; $mal_id = $row['mal_id'];
            // --- Siapkan teks sinopsis untuk ditampilkan (WATCHLIST) ---
            $synopsis_watchlist = $row['description'] ?? 'No synopsis saved.';
            $display_synopsis_watchlist = htmlspecialchars(substr($synopsis_watchlist, 0, 80) . (strlen($synopsis_watchlist) > 80 ? '...' : ''));
            ?> <div class="col-md-6 col-lg-4 col-xl-3">
             <div class="card h-100 shadow">
            <a href="detail.php?mal_id=<?php echo $mal_id; ?>" class="text-decoration-none">
                <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo $title; ?>">
            </a>
            <div class="card-body d-flex flex-column"> <a href="detail.php?mal_id=<?php echo $mal_id; ?>" class="text-decoration-none"> <h5 class="card-title"><?php echo $title; ?></h5> </a> <p class="card-text mb-1"><small>Genre: <?php echo $genres; ?></small></p> <p class="card-text mb-2"><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> <?php echo $rating; ?></span></p>
             <p class="card-text small text-muted flex-grow-1"><?php echo $display_synopsis_watchlist; ?></p>
              <div class="mt-auto pt-2"> <a href="remove_from_watchlist.php?anime_id=<?php echo $local_anime_id; ?>" class="btn btn-danger w-100"><i class="bi bi-trash-fill"></i> Remove from Watchlist</a> </div> </div> </div> </div> <?php endwhile; else: echo "<div class='col-12'><p class='alert alert-info'>Your watchlist is empty. Add anime from the home page!</p></div>"; endif; $stmt->close(); $conn->close(); ?> </div> </div>

    <footer class="text-center text-muted p-4 mt-5" style="background-color: #0c0c0c;"> &copy; <?php echo date("Y"); ?> IAmWeeb Project </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- HAPUS Inisialisasi Tooltip ---

            // Script untuk tombol Surprise Me (AKTIF)
            const surpriseBtn = document.getElementById('surprise-me-button'); /* ... JS Surprise Me ... */
            if(surpriseBtn) { surpriseBtn.addEventListener('click', function(e) { e.preventDefault(); this.innerHTML = '<i class="bi bi-arrow-clockwise fs-5 anim-spin"></i>'; window.location.href = 'surprise.php'; }); }
            const style = document.createElement('style'); style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }`; document.head.appendChild(style);
        });
    </script>
</body>
</html>