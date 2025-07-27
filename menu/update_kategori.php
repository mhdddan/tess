<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $kategori_id = $_POST['kategori_id'] ?? '';
    $nama_kategori = $_POST['nama_kategori'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    
    if (empty($nama_kategori)) {
        header("Location: index.php?error=" . urlencode("Nama kategori harus diisi!"));
        exit();
    }
    
    try {
        // Cek apakah kategori dengan nama yang sama sudah ada (kecuali kategori yang sedang diedit)
        $query = "SELECT COUNT(*) FROM kategori_menu WHERE nama_kategori = :nama_kategori AND status = 'aktif' AND id != :kategori_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->bindParam(':kategori_id', $kategori_id);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            header("Location: index.php?error=" . urlencode("Kategori dengan nama tersebut sudah ada!"));
            exit();
        }
        
        $query = "UPDATE kategori_menu SET nama_kategori = :nama_kategori, deskripsi = :deskripsi WHERE id = :kategori_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':kategori_id', $kategori_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=" . urlencode("Kategori berhasil diupdate!"));
        } else {
            header("Location: index.php?error=" . urlencode("Gagal mengupdate kategori!"));
        }
    } catch (Exception $e) {
        header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
    header("Location: index.php");
}
exit();
?>
