<?php
include 'config.php'; // config.php sudah ada session_start()

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek apakah anime_id ada
if (!isset($_GET['anime_id'])) {
    header("Location: watchlist.php"); // Kembali ke watchlist jika ID tidak ada
    exit;
}

$user_id = $_SESSION['user_id'];
$anime_id = $_GET['anime_id'];

// Query untuk menghapus dari watchlist
$sql = "DELETE FROM watchlist WHERE user_id = ? AND anime_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $anime_id);

if ($stmt->execute()) {
    // Jika berhasil, redirect kembali ke halaman watchlist
    header("Location: watchlist.php");
    exit;
} else {
    echo "Gagal menghapus dari watchlist: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>