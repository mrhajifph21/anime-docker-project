<?php
// =========================================================================
// IAmWeeb - Anime Recommendation System
// File: index.php (Main Entry Point)
// =========================================================================

// Include the configuration file which contains database connection,
// session start, constants, and the API fetching function.
include 'config.php';

// --- SESSION & USER DATA ---

// Check if the user is logged in and their age verification status.
// Default to not verified if not logged in or status not set.
$is_user_logged_in = isset($_SESSION['user_id']);
$is_age_verified = ($is_user_logged_in && isset($_SESSION['is_age_verified']) && $_SESSION['is_age_verified'] == 1);

// --- 1. FETCH USER'S WATCHLIST MAL_IDs (If Logged In) ---
$user_watchlist_ids = array(); // Initialize as an empty array (for older PHP versions)
if ($is_user_logged_in) {
    // Prepare SQL query to get mal_ids from the user's watchlist
    // We join users -> watchlist -> animes to get the mal_id stored in the animes table
    $current_user_id = $_SESSION['user_id']; // Get user ID from session
    $sql_watchlist_query = "SELECT a.mal_id
                            FROM animes a
                            JOIN watchlist w ON a.id = w.anime_id
                            WHERE w.user_id = ? AND a.mal_id IS NOT NULL";

    // Prepare and execute the statement
    $stmt_watchlist = $conn->prepare($sql_watchlist_query);
    if ($stmt_watchlist) {
        $stmt_watchlist->bind_param("i", $current_user_id);
        $stmt_watchlist->execute();
        $result_watchlist = $stmt_watchlist->get_result();

        // Fetch all mal_ids into the array
        while ($watchlist_row = $result_watchlist->fetch_assoc()) {
            if (isset($watchlist_row['mal_id']) && $watchlist_row['mal_id'] !== null) {
                 $user_watchlist_ids[] = $watchlist_row['mal_id'];
            }
        }
        $stmt_watchlist->close();
    } else {
        // Handle potential error in preparing the statement
        // For simplicity in this example, we might just log this error or ignore it.
        error_log("Failed to prepare watchlist query: " . $conn->error);
    }
} // End if user is logged in

// --- Prepare Profile Picture Path for Navbar ---
$navbar_profile_pic = 'assets/avatars/default_avatar.png'; // Default image path
if ($is_user_logged_in && isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $user_specific_avatar_path = 'assets/avatars/' . $_SESSION['profile_picture'];
    // Check if the user's specific avatar file actually exists
    if (file_exists($user_specific_avatar_path)) {
        $navbar_profile_pic = $user_specific_avatar_path;
    }
}
// Final check: if the determined path doesn't exist, ensure the default exists, otherwise use a placeholder
if (!file_exists($navbar_profile_pic)) {
    if (file_exists('assets/avatars/default_avatar.png')) {
        $navbar_profile_pic = 'assets/avatars/default_avatar.png';
    } else {
         $navbar_profile_pic = '#'; // Placeholder if default is also missing
    }
}


// --- 2. FETCH ALL ANIME GENRES FROM API (Using Cache) ---
$all_available_genres = array(); // Initialize genre list
$genres_api_url = "https://api.jikan.moe/v4/genres/anime";
// Call the API function (always try to get NSFW data for the master list, filtering happens during display)
$genres_api_response = get_jikan_data($genres_api_url, false); // false = don't force SFW fetch
if (isset($genres_api_response['data']) && is_array($genres_api_response['data'])) {
    $all_available_genres = $genres_api_response['data'];
}


// --- 3. HELPER FUNCTION TO RENDER ANIME CARD (for Homepage Rows) ---
/**
 * Renders a single anime card HTML for the horizontal scrolling rows.
 *
 * @param array $anime_data Anime data array from Jikan API.
 * @param array $watchlist_mal_ids Array of MAL IDs currently in the user's watchlist.
 * @return void Outputs HTML directly.
 */
function render_anime_card($anime_data, $watchlist_mal_ids) {
    // Extract necessary data, providing defaults if missing
    $mal_id = $anime_data['mal_id'] ?? null;
    $title = $anime_data['title'] ?? 'Unknown Title';
    $image_url = $anime_data['images']['jpg']['image_url'] ?? 'assets/img/default.jpg'; // Fallback image
    $rating = $anime_data['score'] ?? 'N/A';
    $synopsis = $anime_data['synopsis'] ?? 'No synopsis available.';

    // Prepare synopsis for display (limit length)
    $display_synopsis = htmlspecialchars(substr($synopsis, 0, 80) . (strlen($synopsis) > 80 ? '...' : ''));

    // Prepare data needed for the 'Add to Watchlist' button/link
    $genres_list_for_data = array();
    if(isset($anime_data['genres']) && is_array($anime_data['genres'])) {
        foreach($anime_data['genres'] as $genre_item) { $genres_list_for_data[] = $genre_item['name'] ?? ''; }
    }
    $genres_string_for_data = implode(', ', array_filter($genres_list_for_data));
    if (empty($genres_string_for_data)) $genres_string_for_data = "Unknown";

    $watchlist_button_data_array = array(
        'mal_id' => $mal_id,
        'title' => $title,
        'image' => $image_url, // Pass the full URL
        'rating' => $rating,
        'genre' => $genres_string_for_data,
        'description' => $synopsis // Pass full synopsis
    );
    $encoded_watchlist_data = base64_encode(http_build_query($watchlist_button_data_array));

    // Check if this anime is already in the user's watchlist
    $is_currently_in_watchlist = ($mal_id !== null) && in_array($mal_id, $watchlist_mal_ids);

    // Start rendering the card wrapper
    echo '<div class="anime-card-wrapper">';
    // Card element (Note: position-relative removed as overlay is gone)
    echo '  <div class="card h-100 shadow">';

    // Link wrapping the image, leading to the detail page
    $detail_page_url = "detail.php?mal_id=" . ($mal_id ?? 0);
    echo '      <a href="' . $detail_page_url . '" class="text-decoration-none">';
    echo '          <img src="' . htmlspecialchars($image_url) . '" class="card-img-top" alt="' . htmlspecialchars($title) . '">';
    echo '      </a>';

    // Card Body Content
    echo '      <div class="card-body d-flex flex-column">';
    // Title linked to detail page
    echo '          <a href="' . $detail_page_url . '" class="text-decoration-none"><h5 class="card-title">' . htmlspecialchars($title) . '</h5></a>';

    // Rating Badge
    echo '          <p class="card-text mb-2"><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> ' . htmlspecialchars($rating) . '</span></p>';

    // --- BARU: Sinopsis Singkat Ditampilkan Langsung ---
    echo '          <p class="card-text small text-muted flex-grow-1">' . $display_synopsis . '</p>';
    // ---------------------------------------------

    // Action Button Area (Add/Added/Login)
    echo '          <div class="mt-auto pt-2">';
    if (isset($_SESSION['user_id'])) { // Check if user is logged in
        if ($is_currently_in_watchlist) {
            // Display 'Added' button if in watchlist
            echo '      <button class="btn btn-sm btn-success w-100" disabled><i class="bi bi-check-circle-fill"></i> Added</button>';
        } else {
            // Display 'Add to Watchlist' button if not in watchlist
            $add_watchlist_url = 'add_to_watchlist.php?data=' . $encoded_watchlist_data;
            echo '      <a href="' . $add_watchlist_url . '" class="btn btn-sm btn-light w-100"><i class="bi bi-bookmark-plus"></i> Add to Watchlist</a>';
        }
    } else {
        // Display 'Login to Add' button if user is not logged in
        echo '      <a href="login.php" class="btn btn-sm btn-outline-light w-100">Login to Add</a>';
    }
    echo '          </div>'; // Close mt-auto
    echo '      </div>'; // Close card-body
    echo '  </div>'; // Close card
    echo '</div>'; // Close anime-card-wrapper
} // End render_anime_card function


// --- 4. MAIN PAGE LOGIC (Determine Mode: Homepage vs Grid) ---
$is_homepage_mode = false; // Default to grid mode
$current_page_title = "";
$api_call_error = false;

// Get parameters from URL, providing defaults
$search_query_term = $_GET['search'] ?? '';
$single_genre_id = $_GET['genre'] ?? '';
$multiple_genre_ids = $_GET['genres'] ?? array(); // Comes from checkbox form
$genre_display_name = $_GET['name'] ?? 'Selected';
$current_page_number = $_GET['page'] ?? 1;
$view_all_category = $_GET['view_all'] ?? '';

// Ensure multiple_genre_ids is always an array for safe processing
$multiple_genre_ids_safe = is_array($multiple_genre_ids) ? $multiple_genre_ids : array();

// Determine if the current view should be the grid view (Search, Genre, View All)
$is_grid_view_mode = !empty($search_query_term) || !empty($single_genre_id) || !empty($multiple_genre_ids_safe) || !empty($view_all_category);

// Determine if the API call should enforce SFW content
$force_api_call_sfw = !$is_age_verified; // Force SFW if user is NOT age verified

if ($is_grid_view_mode) {
    // --- GRID VIEW MODE LOGIC (Search / Genre / View All) ---
    $is_homepage_mode = false;
    $api_url_to_call = ""; // Initialize API URL variable
    $api_base_url = "https://api.jikan.moe/v4/anime?limit=16&order_by=score&sort=desc&page=" . $current_page_number;
    $api_url_to_call = $api_base_url; // Start with base URL

    // Add search term if provided
    if (!empty($search_query_term)) {
        $current_page_title = "Search Results: " . htmlspecialchars($search_query_term);
        $api_url_to_call .= "&q=" . urlencode($search_query_term);
    }

    // Add genre filters (prioritize multi-genre checkbox selection)
    if (!empty($multiple_genre_ids_safe)) {
        // Check age verification for selected adult genres
        foreach ($multiple_genre_ids_safe as $grid_genre_id) {
            if (in_array($grid_genre_id, ADULT_GENRE_IDS) && !$is_age_verified) {
                // If trying to access adult genre without verification, block immediately
                die("Access Denied: You must be 18 or older and verified to view this content. Please update your birthdate in your profile.");
            }
        }
        $genre_ids_string = implode(',', $multiple_genre_ids_safe);
        $api_url_to_call .= "&genres=" . $genre_ids_string; // No urlencode needed for comma-separated IDs
        if (empty($search_query_term)) $current_page_title = "Genre Search Results";

    } elseif (!empty($single_genre_id) && empty($view_all_category)) {
        // Handle single genre selection from navbar (if not overridden by view_all)
        if (in_array($single_genre_id, ADULT_GENRE_IDS) && !$is_age_verified) {
             die("Access Denied: You must be 18 or older and verified to view this content. Please update your birthdate in your profile.");
        }
        $current_page_title = "Genre: " . htmlspecialchars($genre_display_name);
        $api_url_to_call .= "&genres=" . $single_genre_id; // No urlencode needed
    }

    // Override API URL if this is a "View All" request
    if (!empty($view_all_category)) {
        if ($view_all_category == 'airing') { $current_page_title = "All Top Airing"; $api_url_to_call = "https://api.jikan.moe/v4/top/anime?filter=airing&limit=16&page=" . $current_page_number; }
        elseif ($view_all_category == 'popular') { $current_page_title = "All Most Popular"; $api_url_to_call = "https://api.jikan.moe/v4/top/anime?filter=bypopularity&limit=16&page=" . $current_page_number; }
        elseif ($view_all_category == 'season') { $current_page_title = "All Latest Releases"; $api_url_to_call = "https://api.jikan.moe/v4/seasons/now?limit=16&page=" . $current_page_number; }
        elseif ($view_all_category == 'recommend' && isset($_GET['genre_id'])) {
             $recommend_genre_id = $_GET['genre_id'];
             if (in_array($recommend_genre_id, ADULT_GENRE_IDS) && !$is_age_verified) { die("Access Denied..."); }
             $current_page_title = "Recommended: " . htmlspecialchars($genre_display_name);
             $api_url_to_call = "https://api.jikan.moe/v4/anime?genres=" . $recommend_genre_id . "&order_by=score&sort=desc&limit=16&page=" . $current_page_number; // No urlencode
        }
    }

    // Call the API using the constructed URL and SFW flag
    $api_data_main_grid = get_jikan_data($api_url_to_call, $force_api_call_sfw);
    // Check for errors or empty results
    if (empty($api_data_main_grid) || (isset($api_data_main_grid['data']) && empty($api_data_main_grid['data']))) {
        // Provide a more specific message if filtering likely caused empty results
        $genre_filter_active = !empty($multiple_genre_ids_safe) || !empty($single_genre_id) || ($view_all_category == 'recommend' && isset($_GET['genre_id']));
        if ($force_api_call_sfw && $genre_filter_active) {
             $api_call_error = "No suitable anime found for this query (age restrictions may apply).";
        } else {
             $api_call_error = "No anime found for this query.";
        }
    }

} else {
    // --- HOMEPAGE MODE LOGIC ---
    $is_homepage_mode = true;
    $current_page_title = "Home";

    // Fetch data for homepage rows using the API function (respects SFW flag)
    $homepage_data_airing = get_jikan_data("https://api.jikan.moe/v4/top/anime?filter=airing&limit=10", $force_api_call_sfw);
    $homepage_data_popular = get_jikan_data("https://api.jikan.moe/v4/top/anime?filter=bypopularity&limit=10", $force_api_call_sfw);
    $homepage_data_season = get_jikan_data("https://api.jikan.moe/v4/seasons/now?limit=10", $force_api_call_sfw);

    // Fetch recommendation data (only if logged in)
    $homepage_data_recommend = null;
    $recommendation_top_genre_name = "";
    $recommendation_top_genre_id = null;
    if ($is_user_logged_in && !empty($all_available_genres)) {
        // Query database for genres in user's watchlist
        $sql_user_genres = "SELECT a.genre FROM animes a JOIN watchlist w ON a.id = w.anime_id WHERE w.user_id = ?";
        $stmt_user_genres = $conn->prepare($sql_user_genres);
        if ($stmt_user_genres) {
            $stmt_user_genres->bind_param("i", $_SESSION['user_id']);
            $stmt_user_genres->execute();
            $result_user_genres = $stmt_user_genres->get_result();
            $all_user_watchlist_genres = array();
            while ($genre_row = $result_user_genres->fetch_assoc()) {
                // Explode comma-separated genres and add to the list
                if (!empty($genre_row['genre'])) {
                    $all_user_watchlist_genres = array_merge($all_user_watchlist_genres, explode(', ', $genre_row['genre']));
                }
            }
            $stmt_user_genres->close();

            // Find the most frequent genre
            if (!empty($all_user_watchlist_genres)) {
                $genre_frequency = array_count_values(array_filter($all_user_watchlist_genres)); // Count non-empty genres
                 if (!empty($genre_frequency)) {
                    arsort($genre_frequency); // Sort by frequency DESC
                    $recommendation_top_genre_name = key($genre_frequency); // Get the name

                    // Find the ID for this genre name
                    foreach ($all_available_genres as $master_genre) {
                        if (isset($master_genre['name']) && isset($master_genre['mal_id']) && strtolower($master_genre['name']) == strtolower($recommendation_top_genre_name)) {
                            $recommendation_top_genre_id = $master_genre['mal_id'];
                            break;
                        }
                    }

                    // Fetch recommendations if genre ID found and is appropriate for user's age
                    if ($recommendation_top_genre_id !== null) {
                        if (in_array($recommendation_top_genre_id, ADULT_GENRE_IDS) && !$is_age_verified) {
                            // Don't show adult recommendations to unverified users
                            $homepage_data_recommend = null;
                            $recommendation_top_genre_name = "[Restricted Content]";
                        } else {
                            // Fetch recommendations for the top genre
                            $recommendation_api_url = "https://api.jikan.moe/v4/anime?genres=" . $recommendation_top_genre_id . "&order_by=score&sort=desc&limit=10";
                            $homepage_data_recommend = get_jikan_data($recommendation_api_url, $force_api_call_sfw);
                        }
                    }
                 }
            }
        } else {
             error_log("Failed to prepare user genre query: " . $conn->error);
        }
    } // End if user logged in for recommendations
} // End if/else for page mode
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_page_title); ?> - IAmWeeb</title>
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
        body { background-color: #101010; } .navbar-brand, .btn-danger { color: #ff4757 !important; } .btn-danger { background-color: #ff4757; color: white; border-color: #ff4757;} .btn-outline-danger { color: #ff4757; border-color: #ff4757; } .btn-outline-danger:hover { background-color: #ff4757; color: white; } .card-title { color: #ff4757; height: 48px; overflow: hidden; } .card-text small { color: #ccc; } .dropdown-menu { background-color: #202020; max-height: 400px; overflow-y: auto; } .dropdown-item { color: white; } .dropdown-item:hover { background-color: #333; } #search-container { display: none; background-color: #1a1a1a; padding: 20px 0; border-bottom: 1px solid #333; } .navbar-nav .nav-link { font-size: 1.1rem; padding: 0.5rem 0.75rem; } .navbar-brand { font-size: 1.5rem; } .anime-row-container { margin-bottom: 30px; } .scrolling-wrapper { display: flex; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; padding-bottom: 20px; } .scrolling-wrapper::-webkit-scrollbar { height: 8px; } .scrolling-wrapper::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; } .scrolling-wrapper::-webkit-scrollbar-track { background: #1a1a1a; } .anime-card-wrapper { flex: 0 0 240px; margin-right: 15px; } .anime-card-wrapper .card { background-color: #202020; border: 1px solid #333; } .anime-card-wrapper .card-img-top { height: 300px; object-fit: cover; } .genre-checkbox-list { list-style-type: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto; border: 1px solid #333; border-radius: 5px; background-color: #101010; } .genre-checkbox-list li { padding: 5px 10px; } .genre-checkbox-list li:hover { background-color: #2a2a2a; } .form-check-input { border-color: #555; } .navbar-brand:hover, .navbar-brand:focus { color: #ff4757 !important; } @keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="text-white">

    <nav class="navbar navbar-expand-lg" style="background-color: #202020;"> <div class="container-fluid"> <a class="navbar-brand fw-bold" href="index.php">IAmWeeb</a> <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"> <span class="navbar-toggler-icon"></span> </button> <div class="collapse navbar-collapse" id="navbarNav"> <ul class="navbar-nav me-auto align-items-center"> <li class="nav-item"><a class="nav-link" href="index.php" title="Home"><i class="bi bi-house-fill fs-5"></i></a></li> <li class="nav-item"><a class="nav-link" href="#" id="search-toggle" title="Search"><i class="bi bi-search fs-5"></i></a></li> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-tags-fill"></i> Genres</a> <ul class="dropdown-menu"> <?php if (!empty($all_available_genres)): ?> <?php foreach ($all_available_genres as $genre): ?> <?php if (in_array($genre['mal_id'], ADULT_GENRE_IDS) && !$is_age_verified) { continue; } ?> <li><a class="dropdown-item" href="index.php?genre=<?php echo $genre['mal_id']; ?>&name=<?php echo urlencode($genre['name']); ?>"> <?php echo htmlspecialchars($genre['name']); ?> </a></li> <?php endforeach; ?> <?php else: ?> <li><span class="dropdown-item">Could not load genres.</span></li> <?php endif; ?> </ul> </li> <li class="nav-item"> <a class="nav-link" href="#" id="surprise-me-button" title="Surprise Me!"><i class="bi bi-shuffle fs-5"></i></a> </li> </ul> <ul class="navbar-nav ms-auto"> <?php if ($is_user_logged_in): ?> <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <img src="<?php echo $navbar_profile_pic; ?>" alt="Profile" width="32" height="32" class="rounded-circle me-1 border border-secondary"> </a> <ul class="dropdown-menu dropdown-menu-end"> <li><h6 class="dropdown-header">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h6></li> <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-badge-fill"></i> My Profile</a></li> <li><a class="dropdown-item" href="watchlist.php"><i class="bi bi-list-task"></i> My Watchlist</a></li> <li><hr class="dropdown-divider"></li> <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li> </ul> </li> <?php else: ?> <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li> <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li> <?php endif; ?> </ul> </div> </div> </nav>

    <div id="search-container"> <div class="container"> <form action="index.php" method="GET"> <div class="row g-3"> <div class="col-md-7"> <label for="search-text" class="form-label">Search by Name</label> <input type="text" name="search" id="search-text" class="form-control" placeholder="e.g. Solo Leveling" value="<?php echo htmlspecialchars($search_query_term); ?>" style="background-color: #333; color: white; border-color: #444;"> </div> <div class="col-md-5"> <label class="form-label">Filter by Genre (can select multiple)</label> <ul class="genre-checkbox-list"> <?php if (!empty($all_available_genres)): ?> <?php foreach ($all_available_genres as $genre): ?> <?php if (in_array($genre['mal_id'], ADULT_GENRE_IDS) && !$is_age_verified) { continue; } $checked = in_array($genre['mal_id'], $multiple_genre_ids_safe) ? 'checked' : ''; ?> <li> <div class="form-check"> <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo $genre['mal_id']; ?>" id="genre-<?php echo $genre['mal_id']; ?>" <?php echo $checked; ?>> <label class="form-check-label" for="genre-<?php echo $genre['mal_id']; ?>"> <?php echo htmlspecialchars($genre['name']); ?> </label> </div> </li> <?php endforeach; ?> <?php else: ?> <li>Could not load genres.</li> <?php endif; ?> </ul> </div> </div> <div class="text-center mt-3"> <button type="submit" class="btn btn-danger"><i class="bi bi-search"></i> Search</button> </div> </form> </div> </div>

    <div class="container mt-4">
        <?php if ($is_homepage_mode): ?>
            <?php if (isset($homepage_data_recommend) && !empty($homepage_data_recommend['data'])): ?> <div class="anime-row-container"> <div class="d-flex justify-content-between align-items-center mb-3"> <h2 style="color: #ff4757; margin-bottom: 0;">Recommended (<?php echo htmlspecialchars($recommendation_top_genre_name); ?>)</h2> <a href="index.php?view_all=recommend&genre_id=<?php echo $recommendation_top_genre_id; ?>&name=<?php echo urlencode($recommendation_top_genre_name); ?>" class="btn btn-sm btn-outline-danger">See All</a> </div> <div class="scrolling-wrapper"> <?php foreach ($homepage_data_recommend['data'] as $anime_item) { render_anime_card($anime_item, $user_watchlist_ids); } ?> </div> </div> <?php endif; ?>
            <?php if (isset($homepage_data_airing) && !empty($homepage_data_airing['data'])): ?> <div class="anime-row-container"> <div class="d-flex justify-content-between align-items-center mb-3"> <h2 style="color: #ff4757; margin-bottom: 0;">Top Airing</h2> <a href="index.php?view_all=airing" class="btn btn-sm btn-outline-danger">See All</a> </div> <div class="scrolling-wrapper"> <?php foreach ($homepage_data_airing['data'] as $anime_item) { render_anime_card($anime_item, $user_watchlist_ids); } ?> </div> </div> <?php endif; ?>
            <?php if (isset($homepage_data_popular) && !empty($homepage_data_popular['data'])): ?> <div class="anime-row-container"> <div class="d-flex justify-content-between align-items-center mb-3"> <h2 style="color: #ff4757; margin-bottom: 0;">Most Popular</h2> <a href="index.php?view_all=popular" class="btn btn-sm btn-outline-danger">See All</a> </div> <div class="scrolling-wrapper"> <?php foreach ($homepage_data_popular['data'] as $anime_item) { render_anime_card($anime_item, $user_watchlist_ids); } ?> </div> </div> <?php endif; ?>
            <?php if (isset($homepage_data_season) && !empty($homepage_data_season['data'])): ?> <div class="anime-row-container"> <div class="d-flex justify-content-between align-items-center mb-3"> <h2 style="color: #ff4757; margin-bottom: 0;">Latest Releases</h2> <a href="index.php?view_all=season" class="btn btn-sm btn-outline-danger">See All</a> </div> <div class="scrolling-wrapper"> <?php foreach ($homepage_data_season['data'] as $anime_item) { render_anime_card($anime_item, $user_watchlist_ids); } ?> </div> </div> <?php endif; ?>
        <?php else: ?>
            <h1 class="mb-4" style="color: #ff4757;"><?php echo htmlspecialchars($current_page_title); ?></h1> <div class="row g-4"> <?php if ($api_call_error): echo "<div class='col-12'><p class='alert alert-warning'>".$api_call_error."</p></div>"; elseif (isset($api_data_main_grid) && !empty($api_data_main_grid['data'])): foreach($api_data_main_grid['data'] as $anime_item): $mal_id = $anime_item['mal_id']; $title = $anime_item['title']; $image_url = $anime_item['images']['jpg']['image_url']; $rating = $anime_item['score'] ?? 'N/A'; $genres = implode(', ', array_map(function($g) { return $g['name']; }, $anime_item['genres'])); if (empty($genres)) $genres = "Unknown"; $synopsis = $anime_item['synopsis'] ?? 'No synopsis available.'; $url_data = http_build_query(array('mal_id' => $mal_id, 'title' => $title, 'image' => $image_url, 'rating' => $rating, 'genre' => $genres, 'description' => $synopsis)); $is_in_watchlist = in_array($mal_id, $user_watchlist_ids); $display_synopsis_grid = htmlspecialchars(substr($synopsis, 0, 80) . (strlen($synopsis) > 80 ? '...' : '')); ?> <div class="col-md-6 col-lg-4 col-xl-3"> <div class="card h-100 shadow"> <a href="detail.php?mal_id=<?php echo $mal_id; ?>" class="text-decoration-none"> <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($title); ?>" style="height: 350px; object-fit: cover;"> </a> <div class="card-body d-flex flex-column"> <a href="detail.php?mal_id=<?php echo $mal_id; ?>" class="text-decoration-none"> <h5 class="card-title"><?php echo htmlspecialchars($title); ?></h5> </a> <p class="card-text mb-1"><small>Genre: <?php echo htmlspecialchars(substr($genres, 0, 40)); ?>...</small></p> <p class="card-text mb-2"><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> <?php echo htmlspecialchars($rating); ?></span></p> <p class="card-text small text-muted flex-grow-1"><?php echo $display_synopsis_grid; ?></p> <div class="mt-auto pt-2"> <?php if (isset($_SESSION['user_id'])): ?> <?php if ($is_in_watchlist): ?> <button class="btn btn-sm btn-success w-100" disabled><i class="bi bi-check-circle-fill"></i> Added</button> <?php else: ?> <a href="add_to_watchlist.php?data=<?php echo base64_encode($url_data); ?>" class="btn btn-sm btn-light mt-2 w-100"><i class="bi bi-bookmark-plus"></i> Add to Watchlist</a> <?php endif; ?> <?php else: ?> <a href="login.php" class="btn btn-sm btn-outline-light mt-2 w-100">Login to Add</a> <?php endif; ?> </div> </div> </div> </div> <?php endforeach; else: echo "<div class='col-12'><p class='alert alert-info'>No anime data to display.</p></div>"; endif; ?> </div>
            <?php $pagination_data = $api_data_main_grid['pagination'] ?? null; if ($pagination_data && $pagination_data['last_visible_page'] > 1): /* ... kode pagination ... */ $current_page_num = $pagination_data['current_page']; $last_page_num = $pagination_data['last_visible_page']; $query_params_base = $_GET; $pagination_range = 2; $start_page = max(1, $current_page_num - $pagination_range); $end_page = min($last_page_num, $current_page_num + $pagination_range); ?> <nav aria-label="Page navigation" class="mt-5 d-flex justify-content-center"> <ul class="pagination"> <?php if ($current_page_num > 1): $query_params_base['page'] = 1; $first_link_url = 'index.php?' . http_build_query($query_params_base); ?> <li class="page-item"><a class="page-link" href="<?php echo $first_link_url; ?>" title="First Page">&laquo;</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">&laquo;</span></li> <?php endif; ?> <?php if ($current_page_num > 1): $query_params_base['page'] = $current_page_num - 1; $prev_link_url = 'index.php?' . http_build_query($query_params_base); ?> <li class="page-item"><a class="page-link" href="<?php echo $prev_link_url; ?>">Previous</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">Previous</span></li> <?php endif; ?> <?php if ($start_page > 1): ?> <li class="page-item disabled"><span class="page-link">...</span></li> <?php endif; ?> <?php for ($i = $start_page; $i <= $end_page; $i++): $query_params_base['page'] = $i; $page_link_url = 'index.php?' . http_build_query($query_params_base); ?> <li class="page-item <?php if ($i == $current_page_num) echo 'active'; ?>"> <a class="page-link" href="<?php echo $page_link_url; ?>"><?php echo $i; ?></a> </li> <?php endfor; ?> <?php if ($end_page < $last_page_num): ?> <li class="page-item disabled"><span class="page-link">...</span></li> <?php endif; ?> <?php if ($pagination_data['has_next_page']): $query_params_base['page'] = $current_page_num + 1; $next_link_url = 'index.php?' . http_build_query($query_params_base); ?> <li class="page-item"><a class="page-link" href="<?php echo $next_link_url; ?>">Next</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">Next</span></li> <?php endif; ?> <?php if ($current_page_num < $last_page_num): $query_params_base['page'] = $last_page_num; $last_link_url = 'index.php?' . http_build_query($query_params_base); ?> <li class="page-item"><a class="page-link" href="<?php echo $last_link_url; ?>" title="Last Page">&raquo;</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">&raquo;</span></li> <?php endif; ?> </ul> </nav> <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="text-center text-muted p-4 mt-5" style="background-color: #0c0c0c;"> &copy; <?php echo date("Y"); ?> IAmWeeb Project - Powered by Jikan API </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- HAPUS Inisialisasi Tooltip ---

            var searchToggle = document.getElementById('search-toggle'); /* ... JS Search ... */
            var searchContainer = document.getElementById('search-container'); if (searchToggle && searchContainer) { searchToggle.addEventListener('click', function(e) { e.preventDefault(); if (searchContainer.style.display === 'none' || searchContainer.style.display === '') { searchContainer.style.display = 'block'; } else { searchContainer.style.display = 'none'; } }); }
            const surpriseBtn = document.getElementById('surprise-me-button'); /* ... JS Surprise Me ... */
            if(surpriseBtn) { surpriseBtn.addEventListener('click', function(e) { e.preventDefault(); this.innerHTML = '<i class="bi bi-arrow-clockwise fs-5 anim-spin"></i>'; window.location.href = 'surprise.php'; }); }
            const style = document.createElement('style'); style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } } .anim-spin { animation: spin 1s linear infinite; }`; document.head.appendChild(style);
        });
    </script>
</body>
</html>