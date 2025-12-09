<?php
session_start(); // Wajib ada untuk mengakses session

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
session_destroy();

// Redirect kembali ke halaman utama
header("Location: index.php");
exit;
?>