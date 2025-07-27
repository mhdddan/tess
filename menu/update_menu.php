<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $menu_id = $_POST['menu_id'] ?? '';
    $nama_menu = $_POST['nama_menu'] ?? '';
    $kategori_id = $_POST['kategori_id'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $harga = $_POST['harga'] ?? '';
    $status = $_POST['status'] ?? 'tersedia';
    $gambar_lama = $_POST['gambar_lama'] ?? '';
    
    // Handle file upload
    $gambar = $gambar_lama;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $upload_dir = '../uploads/menu/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions) && $_FILES['gambar']['size'] <= 2097152) { // 2MB
            // Hapus gambar lama jika ada
            if ($gambar_lama && file_exists($upload_dir . $gambar_lama)) {
                unlink($upload_dir . $gambar_lama);
            }
            
            $gambar = time() . '_' . uniqid() . '.' . $file_extension;
            move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_dir . $gambar);
        }
    }
    
    if (empty($nama_menu) || empty($kategori_id) || empty($harga)) {
        header("Location: index.php?error=" . urlencode("Nama menu, kategori, dan harga harus diisi!"));
        exit();
    }
    
    try {
        $query = "UPDATE menu SET kategori_id = :kategori_id, nama_menu = :nama_menu, deskripsi = :deskripsi, 
                  harga = :harga, gambar = :gambar, status = :status, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :menu_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':kategori_id', $kategori_id);
        $stmt->bindParam(':nama_menu', $nama_menu);
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':harga', $harga);
        $stmt->bindParam(':gambar', $gambar);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':menu_id', $menu_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=" . urlencode("Menu berhasil diupdate!"));
        } else {
            header("Location: index.php?error=" . urlencode("Gagal mengupdate menu!"));
        }
    } catch (Exception $e) {
        header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
    header("Location: index.php");
}
exit();
?>
