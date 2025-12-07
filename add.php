<?php
include 'config.php'; 
$message = ""; 
$message_type = "danger";

if(isset($_POST['submit'])) {
    $title = $_POST['title'];
    $genre = $_POST['genre'];
    $rating = $_POST['rating'];
    $description = $_POST['description'];
    $image = $_POST['image'];

    if(!empty($title) && !empty($genre) && !empty($rating) && !empty($description)) {
        
        $sql = "INSERT INTO animes (title, genre, rating, description, image)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdss", $title, $genre, $rating, $description, $image);

        if ($stmt->execute()) {
            $message = "Anime berhasil ditambahkan!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Semua field (kecuali gambar) wajib diisi!";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Anime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css"> <style>
        body { background-color: #101010; }
        .card { background-color: #202020; }
        .btn-danger { background-color: #ff4757; border-color: #ff4757; }
        a { color: #ff4757; text-decoration: none; }
    </style>
</head>
<body class="text-white">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6" style="margin-top: 10vh;">
                <div class="card shadow p-4">
                    <div class="card-body">
                        <h1 class="text-center mb-4" style="color: #ff4757;">Tambah Anime Baru</h1>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="add.php">
                            <div class="mb-3">
                                <label for="title" class="form-label">Judul Anime</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <input type="text" class="form-control" id="genre" name="genre" required>
                            </div>
                            <div class="mb-3">
                                <label for="rating" class="form-label">Rating (1.0 - 10.0)</label>
                                <input type="number" step="0.1" min="1" max="10" class="form-control" id="rating" name="rating" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Nama File Gambar (contoh: aot.jpg)</label>
                                <input type="text" class="form-control" id="image" name="image" placeholder="Kosongkan jika tidak ada">
                            </div>
                            <button type="submit" name="submit" class="btn btn-danger w-100"><i class="bi bi-plus-circle-fill"></i> Tambah</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="index.php"><i class="bi bi-arrow-left-circle"></i> Kembali ke List</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>