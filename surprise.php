<?php
include 'config.php'; // Hubungkan DB, mulai session, panggil get_jikan_data()

// Cek status verifikasi umur user
$is_age_verified = (isset($_SESSION['is_age_verified']) && $_SESSION['is_age_verified'] == 1);

$found_suitable_anime = false;
$attempts = 0; // Batasi percobaan agar tidak infinite loop
$redirect_url = 'index.php'; // Default redirect jika gagal

while (!$found_suitable_anime && $attempts < 10) { // Coba maksimal 10 kali
    $attempts++;

    // Panggil API untuk anime random
    // Kita selalu minta data NSFW dulu, nanti kita filter manual
    $random_data = get_jikan_data("https://api.jikan.moe/v4/random/anime", false); // false = jangan paksa SFW

    if (!empty($random_data) && !empty($random_data['data'])) {
        $anime = $random_data['data'];
        $mal_id = $anime['mal_id'];

        // Cek apakah anime ini dewasa
        $is_anime_adult = false;
        if (isset($anime['rating']) && strpos($anime['rating'], 'Rx') !== false) {
            $is_anime_adult = true;
        } else if (isset($anime['genres'])) {
            foreach($anime['genres'] as $genre) {
                if (in_array($genre['mal_id'], ADULT_GENRE_IDS)) {
                    $is_anime_adult = true;
                    break;
                }
            }
        }

        // Jika anime TIDAK dewasa, ATAU jika anime dewasa TAPI user SUDAH verified
        if (!$is_anime_adult || ($is_anime_adult && $is_age_verified)) {
            // Anime cocok! Siapkan URL redirect ke detail
            $redirect_url = "detail.php?mal_id=" . $mal_id;
            $found_suitable_anime = true; // Hentikan loop
        }
        // Jika anime dewasa dan user BELUM verified, loop akan lanjut mencari lagi
    } else {
        // Jika API gagal atau tidak ada data, tunggu sebentar lalu coba lagi
        sleep(1); // Tunggu 1 detik sebelum retry
    }
}

// Jika setelah 10x mencoba masih gagal, redirect ke index saja
// Jika berhasil, redirect ke halaman detail anime random
header("Location: " . $redirect_url);
exit; // Pastikan script berhenti setelah redirect

?>