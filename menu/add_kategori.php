<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $nama_kategori = $_POST['nama_kategori'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    
    if (empty($nama_kategori)) {
        header("Location: index.php?error=" . urlencode("Nama kategori harus diisi!"));
        exit();
    }
    
    try {
        // Cek apakah kategori sudah ada
        $query = "SELECT COUNT(*) FROM kategori_menu WHERE nama_kategori = :nama_kategori AND status = 'aktif'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            header("Location: index.php?error=" . urlencode("Kategori dengan nama tersebut sudah ada!"));
            exit();
        }
        
        $query = "INSERT INTO kategori_menu (nama_kategori, deskripsi) VALUES (:nama_kategori, :deskripsi)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->bindParam(':deskripsi', $deskripsi);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=" . urlencode("Kategori berhasil ditambahkan!"));
        } else {
            header("Location: index.php?error=" . urlencode("Gagal menambahkan kategori!"));
        }
    } catch (Exception $e) {
        header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
    header("Location: index.php");
}
exit();
?>
