<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Handle delete menu
if (isset($_GET['delete_menu'])) {
    $menu_id = $_GET['delete_menu'];
    $query = "UPDATE menu SET status = 'nonaktif' WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $menu_id);
    if ($stmt->execute()) {
        $success = "Menu berhasil dihapus!";
    }
}

// Handle delete kategori
if (isset($_GET['delete_kategori'])) {
    $kategori_id = $_GET['delete_kategori'];
    $query = "UPDATE kategori_menu SET status = 'nonaktif' WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $kategori_id);
    if ($stmt->execute()) {
        $success = "Kategori berhasil dihapus!";
    }
}

// Ambil data menu dengan kategori
$query = "SELECT m.*, k.nama_kategori 
          FROM menu m 
          LEFT JOIN kategori_menu k ON m.kategori_id = k.id 
          WHERE m.status != 'nonaktif'
          ORDER BY k.nama_kategori, m.nama_menu";
$stmt = $db->prepare($query);
$stmt->execute();
$menu_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data kategori
$query = "SELECT * FROM kategori_menu WHERE status = 'aktif' ORDER BY nama_kategori";
$stmt = $db->prepare($query);
$stmt->execute();
$kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user = getUserInfo();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Ramen Gen Kiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                    <div class="mb-2">
                            <img src="assets/logo.jpg" alt="Ramen Gen Kiro" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                        </div>    
                    <h4>Ramen Gen Kiro</h4>
                        <small><?php echo ucfirst($user['role']); ?>: <?php echo $user['nama']; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-utensils me-2"></i> Kelola Menu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/financial.php">
                                <i class="fas fa-chart-line me-2"></i> Laporan Keuangan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/sales.php">
                                <i class="fas fa-chart-bar me-2"></i> Laporan Penjualan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pos/index.php">
                                <i class="fas fa-cash-register me-2"></i> POS Digital
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../orders/index.php">
                                <i class="fas fa-clipboard-list me-2"></i> Menu Orderan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../customers/index.php">
                                <i class="fas fa-user-friends me-2"></i> Data Pelanggan
                            </a>
                        </li>
                        
                        <hr class="text-white">
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-utensils text-primary me-2"></i>Kelola Menu
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                                <i class="fas fa-plus"></i> Tambah Menu
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addKategoriModal">
                                <i class="fas fa-tags"></i> Tambah Kategori
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="menuTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="menu-tab" data-bs-toggle="tab" data-bs-target="#menu" type="button" role="tab">
                            <i class="fas fa-utensils me-2"></i>Daftar Menu
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="kategori-tab" data-bs-toggle="tab" data-bs-target="#kategori" type="button" role="tab">
                            <i class="fas fa-tags me-2"></i>Kategori Menu
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="menuTabsContent">
                    <!-- Tab Menu -->
                    <div class="tab-pane fade show active" id="menu" role="tabpanel">
                        <div class="row mt-4">
                            <?php if (empty($menu_list)): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Belum ada menu</h5>
                                        <p class="text-muted">Klik tombol "Tambah Menu" untuk menambah menu baru</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($menu_list as $menu): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card menu-card h-100 position-relative">
                                        <div class="status-badge">
                                            <?php
                                            $status_class = [
                                                'tersedia' => 'success',
                                                'habis' => 'warning',
                                                'nonaktif' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$menu['status']]; ?>">
                                                <?php echo ucfirst($menu['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="card-body">
                                            <?php if ($menu['gambar'] && file_exists('../uploads/menu/' . $menu['gambar'])): ?>
                                                <img src="../uploads/menu/<?php echo $menu['gambar']; ?>" class="menu-image mb-3" alt="<?php echo $menu['nama_menu']; ?>">
                                            <?php else: ?>
                                                <div class="menu-placeholder mb-3">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <h5 class="card-title"><?php echo $menu['nama_menu']; ?></h5>
                                            <p class="card-text text-muted small"><?php echo $menu['deskripsi']; ?></p>
                                            <p class="card-text">
                                                <small class="text-muted">Kategori: <?php echo $menu['nama_kategori']; ?></small>
                                            </p>
                                            <h6 class="text-primary">Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?></h6>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-outline-primary btn-sm edit-menu" 
                                                        data-id="<?php echo $menu['id']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editMenuModal">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete_menu=<?php echo $menu['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Yakin ingin menghapus menu ini?')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab Kategori -->
                    <div class="tab-pane fade" id="kategori" role="tabpanel">
                        <div class="row mt-4">
                            <?php foreach ($kategori_list as $kategori): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-tag text-primary me-2"></i>
                                            <?php echo $kategori['nama_kategori']; ?>
                                        </h5>
                                        <p class="card-text text-muted"><?php echo $kategori['deskripsi']; ?></p>
                                        <small class="text-muted">
                                            Dibuat: <?php echo date('d/m/Y', strtotime($kategori['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100">
                                            <button class="btn btn-outline-primary btn-sm edit-kategori" 
                                                    data-id="<?php echo $kategori['id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editKategoriModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete_kategori=<?php echo $kategori['id']; ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Tambah Menu -->
    <div class="modal fade" id="addMenuModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus text-success me-2"></i>Tambah Menu Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_menu.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Menu *</label>
                                    <input type="text" class="form-control" name="nama_menu" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kategori *</label>
                                    <select class="form-select" name="kategori_id" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php foreach ($kategori_list as $kategori): ?>
                                        <option value="<?php echo $kategori['id']; ?>">
                                            <?php echo $kategori['nama_kategori']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Harga *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" name="harga" required min="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="tersedia">Tersedia</option>
                                        <option value="habis">Habis</option>
                                        <option value="nonaktif">Non Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="deskripsi" rows="4"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gambar Menu</label>
                                    <input type="file" class="form-control" name="gambar" accept="image/*">
                                    <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Menu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Kategori -->
    <div class="modal fade" id="addKategoriModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags text-info me-2"></i>Tambah Kategori Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_kategori.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori *</label>
                            <input type="text" class="form-control" name="nama_kategori" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i> Simpan Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Menu -->
    <div class="modal fade" id="editMenuModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-primary me-2"></i>Edit Menu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div id="editMenuContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Kategori -->
    <div class="modal fade" id="editKategoriModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-primary me-2"></i>Edit Kategori
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div id="editKategoriContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load edit menu form
        document.querySelectorAll('.edit-menu').forEach(button => {
            button.addEventListener('click', function() {
                const menuId = this.dataset.id;
                fetch(`edit_menu_form.php?id=${menuId}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('editMenuContent').innerHTML = html;
                    });
            });
        });

        // Load edit kategori form
        document.querySelectorAll('.edit-kategori').forEach(button => {
            button.addEventListener('click', function() {
                const kategoriId = this.dataset.id;
                fetch(`edit_kategori_form.php?id=${kategoriId}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('editKategoriContent').innerHTML = html;
                    });
            });
        });
    </script>
</body>
</html>
