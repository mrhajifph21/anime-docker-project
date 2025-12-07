<?php
include 'config.php'; // Langsung include di paling atas

// Cek apakah 'id' ada di URL (query string)
if(isset($_GET['id'])) {
    $id = $_GET['id'];

    // Gunakan prepared statement untuk menghapus
    $sql = "DELETE FROM animes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id); // 'i' = integer

    if ($stmt->execute()) {
        // Jika berhasil, redirect (arahkan) kembali ke halaman utama
        header("Location: index.php");
        exit; // Penting untuk menghentikan eksekusi script setelah redirect
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    // Jika tidak ada ID di URL
    echo "ID tidak ditemukan.";
    echo "<br><a href='index.php'>Kembali ke List</a>";
}
$conn->close();
?>