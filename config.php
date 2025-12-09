<?php
// TAMPILKAN SEMUA ERROR
error_reporting(E_ALL);
ini_set('display_errors', 1);

// MULAI SESSION UNTUK LOGIN
session_start();

// --- PENGATURAN UNTUK CACHING & GENRE DEWASA ---
define('CACHE_DURATION', 3600); // Durasi cache = 3600 detik (1 jam)
define('CACHE_DIR', __DIR__ . '/cache/'); // Path ke folder cache
define('ADULT_GENRE_IDS', [12, 49]); // 12 = Hentai, 49 = Erotica
// --- BARU: URL Random ---
define('JIKAN_RANDOM_ANIME_URL', 'https://api.jikan.moe/v4/random/anime');
// -----------------------------

// KONEKSI DATABASE
$servername = "db";
$username = "root";
$password = "1234";
$dbname = "anime_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// --- FUNGSI UNTUK MENGAMBIL DATA API DENGAN CACHE (VERSI Age Gate Aware + Random Exception) ---
/**
 * Mengambil data dari Jikan API, menggunakan cache jika ada, kecuali untuk endpoint random.
 * @param string $url URL API yang akan dipanggil
 * @param bool $force_sfw Jika true, paksa panggil API SFW. Default false.
 * @return array|null Data JSON yang sudah di-decode, or null on failure
 */
function get_jikan_data($url, $force_sfw = false) {

    $is_random_request = ($url == JIKAN_RANDOM_ANIME_URL);
    
    $final_url = $url;
    $cache_suffix = '';

    if (!$force_sfw) {
        // Jika tidak dipaksa SFW, coba tambahkan sfw=false
        if (strpos($url, '?') !== false) {
            $final_url = $url . '&sfw=false';
        } else {
            $final_url = $url . '?sfw=false';
        }
        $cache_suffix = '_nsfw';
    }

    // Buat nama file cache (hanya jika bukan request random)
    $cache_file = CACHE_DIR . md5($url) . $cache_suffix . '.json';

    // 1. CEK CACHE (HANYA JIKA BUKAN REQUEST RANDOM)
    if (!$is_random_request && file_exists($cache_file) && (time() - filemtime($cache_file) < CACHE_DURATION)) {
        $response_json = file_get_contents($cache_file);
        return json_decode($response_json, true);
    }

    // 2. JIKA CACHE TIDAK ADA/BASI ATAU INI REQUEST RANDOM
    // Panggil API Jikan (Gunakan $final_url)
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: IAmWeeb-Project/1.0'
        ]
    ];
    $context = stream_context_create($options);
    $response_json = @file_get_contents($final_url, false, $context);

    // Handle Fallback jika sfw=false gagal (dan bukan request random)
    if ($response_json === FALSE && !$force_sfw && !$is_random_request) {
        $response_json_fallback = @file_get_contents($url, false, $context); // Panggil URL asli
        if ($response_json_fallback === FALSE) return null;
        $response_json = $response_json_fallback;
        // Gunakan nama cache asli untuk fallback
        $cache_file = CACHE_DIR . md5($url) . '.json';
    } elseif ($response_json === FALSE) {
         // Gagal saat memang minta SFW atau saat request random
         return null;
    }

    // 3. SIMPAN KE CACHE (HANYA JIKA BUKAN REQUEST RANDOM)
    if (!$is_random_request) {
        $data = json_decode($response_json, true);
        if ($data && ( (isset($data['data']) && !empty($data['data'])) || (!isset($data['data']) && !empty($data)) ) ) {
            if (!is_dir(CACHE_DIR)) { mkdir(CACHE_DIR, 0755, true); }
            file_put_contents($cache_file, $response_json);
        }
    }

    // 4. KEMBALIKAN DATA (Baik dari API langsung atau fallback)
    return json_decode($response_json, true);
}
?>