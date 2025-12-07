<?php
include 'config.php';

$is_age_verified = (isset($_SESSION['is_age_verified']) && $_SESSION['is_age_verified'] == 1);
$mal_id = $_GET['mal_id'] ?? 0;
if ($mal_id <= 0) { die("Error: Invalid or no anime ID provided."); }

$url = "https://api.jikan.moe/v4/anime/{$mal_id}/full";
$anime_data_full = get_jikan_data($url, false);

if (empty($anime_data_full) || empty($anime_data_full['data'])) { die("Error: Could not retrieve anime data for ID {$mal_id}."); }
$anime = $anime_data_full['data'];

// --- AGE GATE CHECK ---
$is_anime_adult = false;
if (isset($anime['rating']) && strpos($anime['rating'], 'Rx') !== false) { $is_anime_adult = true; }
else if (isset($anime['genres'])) {
    foreach($anime['genres'] as $genre) { if (in_array($genre['mal_id'], ADULT_GENRE_IDS)) { $is_anime_adult = true; break; } }
}
if ($is_anime_adult && !$is_age_verified) {
     include 'partials/header.php';
     echo '<div class="container mt-5"><div class="alert alert-danger text-center">';
     echo '<h4><i class="bi bi-exclamation-triangle-fill"></i> Restricted Content</h4>';
     echo '<p>You must be 18 or older and verified to view this anime. Please update your birthdate in your <a href="profile.php" class="alert-link">profile</a>.</p>';
     echo '<a href="index.php" class="btn btn-secondary mt-2">Back to Home</a>';
     echo '</div></div>';
     include 'partials/footer.php';
     exit;
}

// --- Cek watchlist ---
$is_in_watchlist = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql_check = "SELECT 1 FROM watchlist w JOIN animes a ON w.anime_id = a.id WHERE w.user_id = ? AND a.mal_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $user_id, $mal_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) { $is_in_watchlist = true; }
    $stmt_check->close();
}

// --- Data untuk tombol Add ---
$anime_genres = isset($anime['genres']) ? array_map(function($g) { return $g['name']; }, $anime['genres']) : [];
$url_data = http_build_query(array( 'mal_id' => $anime['mal_id'], 'title' => $anime['title'], 'image' => $anime['images']['jpg']['image_url'], 'rating' => $anime['score'] ?? 0.0, 'genre' => implode(', ', $anime_genres), 'description' => $anime['synopsis'] ?? 'No synopsis.' ));

// --- Path gambar profil ---
$display_pic_path = 'assets/avatars/default_avatar.png';
if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture']) && file_exists('assets/avatars/' . $_SESSION['profile_picture'])) { $display_pic_path = 'assets/avatars/' . $_SESSION['profile_picture']; }
if (!file_exists($display_pic_path) && file_exists('assets/avatars/default_avatar.png')) { $display_pic_path = 'assets/avatars/default_avatar.png'; }
elseif (!file_exists($display_pic_path) && !file_exists('assets/avatars/default_avatar.png')) { $display_pic_path = '#'; }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anime['title']); ?> - IAmWeeb</title>
    <link rel="icon" type="image/png" href="/anime-recommendation/assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style> /* ... CSS styles tidak berubah ... */
        body { background-color: #101010; } .navbar-brand, .btn-danger { color: #ff4757 !important; } .btn-danger { background-color: #ff4757; color: white; border-color: #ff4757;} .dropdown-menu { background-color: #202020; } .dropdown-item { color: white; } .dropdown-item:hover { background-color: #333; } .navbar-nav .nav-link { font-size: 1.1rem; padding: 0.5rem 0.75rem; } .navbar-brand { font-size: 1.5rem; } .anime-poster { max-width: 300px; height: auto; border-radius: 10px; } .genre-badge { background-color: #333; color: #eee; margin-right: 5px; margin-bottom: 5px; display: inline-block; text-decoration: none;} .trailer-responsive { overflow: hidden; padding-bottom: 56.25%; position: relative; height: 0; } .trailer-responsive iframe { left: 0; top: 0; height: 100%; width: 100%; position: absolute; } .navbar-brand:hover, .navbar-brand:focus { color: #ff4757 !important; } @keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="text-white">

    <nav class="navbar navbar-expand-lg" style="background-color: #202020;"> <div class="container-fluid"> <a class="navbar-brand fw-bold" href="index.php">IAmWeeb</a> <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"> <span class="navbar-toggler-icon"></span> </button> <div class="collapse navbar-collapse" id="navbarNav"> <ul class="navbar-nav me-auto align-items-center"> <li class="nav-item"><a class="nav-link" href="index.php" title="Home"><i class="bi bi-house-fill fs-5"></i></a></li> <li class="nav-item"><a class="nav-link" href="#" id="search-toggle" title="Search"><i class="bi bi-search fs-5"></i></a></li> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-tags-fill"></i> Genres</a> <ul class="dropdown-menu"><li><a class="dropdown-item" href="index.php">Go to Home for Genres</a></li></ul> </li> <li class="nav-item"> <a class="nav-link" href="#" id="surprise-me-button" title="Surprise Me!"><i class="bi bi-shuffle fs-5"></i></a> </li> </ul> <ul class="navbar-nav ms-auto"> <?php if (isset($_SESSION['user_id'])): ?> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <img src="<?php echo $display_pic_path; ?>" alt="Profile" width="32" height="32" class="rounded-circle me-1 border border-secondary"> </a> <ul class="dropdown-menu dropdown-menu-end"> <li><h6 class="dropdown-header">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h6></li> <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-badge-fill"></i> My Profile</a></li> <li><a class="dropdown-item" href="watchlist.php"><i class="bi bi-list-task"></i> My Watchlist</a></li> <li><hr class="dropdown-divider"></li> <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li> </ul> </li> <?php else: ?> <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li> <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li> <?php endif; ?> </ul> </div> </div> </nav>
    <div id="search-container" style="display: none;"> <div class="container"><form action="index.php" method="GET"></form></div> </div>

    <div class="container mt-4"> <div class="row g-4"> <div class="col-lg-4 col-md-5"> <img src="<?php echo htmlspecialchars($anime['images']['jpg']['large_image_url']); ?>" class="anime-poster w-100 mb-3" alt="<?php echo htmlspecialchars($anime['title']); ?>"> <div class="d-grid gap-2"> <?php if (isset($_SESSION['user_id'])): ?> <?php if ($is_in_watchlist): ?> <button class="btn btn-success" disabled><i class="bi bi-check-circle-fill"></i> Added to Watchlist</button> <?php else: ?> <a href="add_to_watchlist.php?data=<?php echo base64_encode($url_data); ?>" class="btn btn-light"><i class="bi bi-bookmark-plus"></i> Add to Watchlist</a> <?php endif; ?> <?php else: ?> <a href="login.php" class="btn btn-outline-light">Login to Add</a> <?php endif; ?> </div> <a href="index.php" class="btn btn-sm btn-outline-secondary mt-3 w-100"><i class="bi arrow-left-circle"></i> Back to List</a> </div> <div class="col-lg-8 col-md-7"> <h1 style="color: #ff4757;"><?php echo htmlspecialchars($anime['title']); ?></h1> <?php if (!empty($anime['title_japanese'])): ?> <h5 class="text-muted mb-3"><?php echo htmlspecialchars($anime['title_japanese']); ?></h5> <?php endif; ?> <p class="mb-1"> <span class="badge bg-warning text-dark fs-6 me-2"><i class="bi bi-star-fill"></i> <?php echo htmlspecialchars($anime['score'] ?? 'N/A'); ?></span> <?php if(isset($anime['rank'])): ?><span class="me-2"><i class="bi bi-trophy-fill"></i> Rank #<?php echo htmlspecialchars($anime['rank']); ?></span><?php endif; ?> <?php if(isset($anime['popularity'])): ?><span class="me-2"><i class="bi bi-heart-fill"></i> Popularity #<?php echo htmlspecialchars($anime['popularity']); ?></span><?php endif; ?> </p> <p class="text-muted small"> <span class="me-2"><strong>Type:</strong> <?php echo htmlspecialchars($anime['type'] ?? '?'); ?></span> | <span class="mx-2"><strong>Episodes:</strong> <?php echo htmlspecialchars($anime['episodes'] ?? '?'); ?></span> | <span class="mx-2"><strong>Status:</strong> <?php echo htmlspecialchars($anime['status'] ?? '?'); ?></span> | <span class="ms-2"><strong>Season:</strong> <?php echo htmlspecialchars(ucfirst($anime['season'] ?? '?') . ' ' . ($anime['year'] ?? '')); ?></span> </p> <div class="my-3"> <strong>Genres:</strong> <?php if(!empty($anime_genres)): ?> <?php foreach($anime['genres'] as $genre): ?> <a href="index.php?genre=<?php echo $genre['mal_id']; ?>&name=<?php echo urlencode($genre['name']); ?>" class="badge genre-badge text-decoration-none <?php echo (in_array($genre['mal_id'], ADULT_GENRE_IDS)) ? 'bg-danger' : 'bg-secondary'; ?>"> <?php echo htmlspecialchars($genre['name']); ?> </a> <?php endforeach; ?> <?php else: ?> <span class="text-muted">N/A</span> <?php endif; ?> </div> <h4 class="mt-4">Synopsis</h4> <p><?php echo nl2br(htmlspecialchars($anime['synopsis'] ?? 'No synopsis available.')); ?></p> <?php if (!empty($anime['trailer']['youtube_id'])): ?> <h4 class="mt-4">Trailer</h4> <div class="trailer-responsive"> <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($anime['trailer']['youtube_id']); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> </div> <?php endif; ?> </div> </div> </div>

    <footer class="text-center text-muted p-4 mt-5" style="background-color: #0c0c0c;"> &copy; <?php echo date("Y"); ?> IAmWeeb Project - Powered by Jikan API </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Script untuk Search (redirect)
            const searchToggle = document.getElementById('search-toggle');
            if(searchToggle) { searchToggle.addEventListener('click', function(e) { e.preventDefault(); window.location.href = 'index.php'; }); } // Redirect ke index

            // Script untuk tombol Surprise Me (AKTIF)
            const surpriseBtn = document.getElementById('surprise-me-button');
            if(surpriseBtn) {
                surpriseBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // Mencegah link default
                    this.innerHTML = '<i class="bi bi-arrow-clockwise fs-5 anim-spin"></i>'; // Ganti ikon jadi loading
                    window.location.href = 'surprise.php'; // Redirect ke surprise.php
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