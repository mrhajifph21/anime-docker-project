<?php
include 'config.php'; // Hubungkan DB & mulai Session

// 1. CEK APAKAH USER SUDAH LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. AMBIL DATA DARI URL
// Data dikirim pakai base64_encode, jadi harus di-decode dulu
if (!isset($_GET['data'])) {
    echo "Error: Tidak ada data.";
    echo "<br><a href='index.php'>Kembali</a>";
    exit;
}

// Decode data dari URL
$url_data = base64_decode($_GET['data']);
// parse_str mengubah string "a=1&b=2" menjadi variabel $a dan $b
parse_str($url_data, $anime_data);

// 3. CEK APAKAH DATA ANIME LENGKAP
if (!isset($anime_data['mal_id']) || !isset($anime_data['title'])) {
    echo "Error: Data anime tidak lengkap.";
    echo "<br><a href='index.php'>Kembali</a>";
    exit;
}

// 4. SIAPKAN SEMUA DATA
$user_id = $_SESSION['user_id'];
$mal_id = $anime_data['mal_id'];

$title = $anime_data['title'];
$genre = $anime_data['genre'] ?? 'Unknown';
$rating = $anime_data['rating'] ?? 0.0;
// Bersihkan deskripsi dari karakter aneh
$description = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $anime_data['description'] ?? 'No description.'); 
// Ambil HANYA nama file gambar dari URL
$image_url = $anime_data['image'] ?? '';
// parse_url ambil path-nya (cth: /images/anime/12/345.jpg)
// basename ambil nama filenya aja (cth: 345.jpg)

$local_anime_id = null; // Variabel untuk menyimpan ID anime di tabel 'animes' kita

// --- INI LOGIKA UTAMANYA ---

// 5. CEK APAKAH ANIME INI SUDAH ADA DI DATABASE LOKAL (berdasarkan mal_id)
$sql_check = "SELECT id FROM animes WHERE mal_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $mal_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // 6A. JIKA SUDAH ADA: Ambil ID lokalnya
    $row = $result_check->fetch_assoc();
    $local_anime_id = $row['id'];
    $stmt_check->close();

} else {
    // 6B. JIKA BELUM ADA: Masukkan anime baru ini ke tabel 'animes' kita
    $stmt_check->close(); // Tutup statement cek dulu
    
    $sql_insert_anime = "INSERT INTO animes (mal_id, title, genre, rating, description, image) 
                         VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert_anime);
    // 'i' = integer, 's' = string, 'd' = double
    $stmt_insert->bind_param("issdss", $mal_id, $title, $genre, $rating, $description, $image_url);
    
    if ($stmt_insert->execute()) {
        // Jika sukses insert, ambil ID dari data yang baru saja dimasukkan
        $local_anime_id = $conn->insert_id;
    } else {
        echo "Error: Gagal menyimpan anime baru ke database lokal. " . $stmt_insert->error;
        $stmt_insert->close();
        $conn->close();
        exit;
    }
    $stmt_insert->close();
}


// 7. TAMBAHKAN KE WATCHLIST (JIKA KITA DAPAT ID LOKALNYA)
if ($local_anime_id) {
    // Query untuk memasukkan ke watchlist (pakai IGNORE agar tidak error jika sudah ada)
    $sql_watchlist = "INSERT IGNORE INTO watchlist (user_id, anime_id) VALUES (?, ?)";
    $stmt_watchlist = $conn->prepare($sql_watchlist);
    $stmt_watchlist->bind_param("ii", $user_id, $local_anime_id);

    if ($stmt_watchlist->execute()) {
        // Berhasil! Redirect kembali ke halaman asal (index.php atau hasil search)
        // HTTP_REFERER adalah URL halaman sebelumnya
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } else {
        echo "Error: Gagal menambahkan ke watchlist. " . $stmt_watchlist->error;
    }
    $stmt_watchlist->close();

} else {
    echo "Error: Gagal mendapatkan ID anime lokal.";
}

$conn->close();
?>