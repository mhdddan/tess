<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

if (!isset($_GET['id'])) {
    exit('ID kategori tidak ditemukan');
}

$database = new Database();
$db = $database->getConnection();

// Ambil data kategori
$query = "SELECT * FROM kategori_menu WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$kategori = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    exit('Kategori tidak ditemukan');
}
?>

<form action="update_kategori.php" method="POST">
    <input type="hidden" name="kategori_id" value="<?php echo $kategori['id']; ?>">
    
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nama Kategori *</label>
            <input type="text" class="form-control" name="nama_kategori" value="<?php echo htmlspecialchars($kategori['nama_kategori']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea class="form-control" name="deskripsi" rows="3"><?php echo htmlspecialchars($kategori['deskripsi']); ?></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Kategori
        </button>
    </div>
</form>
