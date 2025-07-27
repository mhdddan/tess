<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

if (!isset($_GET['id'])) {
    exit('ID menu tidak ditemukan');
}

$database = new Database();
$db = $database->getConnection();

// Ambil data menu
$query = "SELECT * FROM menu WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$menu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    exit('Menu tidak ditemukan');
}

// Ambil data kategori
$query = "SELECT * FROM kategori_menu WHERE status = 'aktif' ORDER BY nama_kategori";
$stmt = $db->prepare($query);
$stmt->execute();
$kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<form action="update_menu.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
    <input type="hidden" name="gambar_lama" value="<?php echo $menu['gambar']; ?>">
    
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Nama Menu *</label>
                    <input type="text" class="form-control" name="nama_menu" value="<?php echo htmlspecialchars($menu['nama_menu']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori *</label>
                    <select class="form-select" name="kategori_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($kategori_list as $kategori): ?>
                        <option value="<?php echo $kategori['id']; ?>" <?php echo ($kategori['id'] == $menu['kategori_id']) ? 'selected' : ''; ?>>
                            <?php echo $kategori['nama_kategori']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Harga *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" name="harga" value="<?php echo $menu['harga']; ?>" required min="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="tersedia" <?php echo ($menu['status'] == 'tersedia') ? 'selected' : ''; ?>>Tersedia</option>
                        <option value="habis" <?php echo ($menu['status'] == 'habis') ? 'selected' : ''; ?>>Habis</option>
                        <option value="nonaktif" <?php echo ($menu['status'] == 'nonaktif') ? 'selected' : ''; ?>>Non Aktif</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" rows="4"><?php echo htmlspecialchars($menu['deskripsi']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gambar Menu</label>
                    <?php if ($menu['gambar'] && file_exists('../uploads/menu/' . $menu['gambar'])): ?>
                        <div class="mb-2">
                            <img src="../uploads/menu/<?php echo $menu['gambar']; ?>" class="img-thumbnail" style="max-width: 200px;">
                            <br><small class="text-muted">Gambar saat ini</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="gambar" accept="image/*">
                    <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 2MB. Kosongkan jika tidak ingin mengubah gambar.</small>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Menu
        </button>
    </div>
</form>
