<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

// Filter parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'nama';
$order = $_GET['order'] ?? 'ASC';

// Handle customer actions
if ($_POST) {
    if (isset($_POST['add_customer'])) {
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $no_handphone = $_POST['no_handphone'];
        $alamat = $_POST['alamat'] ?? '';
        
        try {
            $query = "INSERT INTO pelanggan (nama, email, no_handphone, alamat) VALUES (:nama, :email, :no_handphone, :alamat)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $nama);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':no_handphone', $no_handphone);
            $stmt->bindParam(':alamat', $alamat);
            
            if ($stmt->execute()) {
                $success = "Pelanggan berhasil ditambahkan!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_customer'])) {
        $id = $_POST['customer_id'];
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $no_handphone = $_POST['no_handphone'];
        $alamat = $_POST['alamat'] ?? '';
        
        try {
            $query = "UPDATE pelanggan SET nama = :nama, email = :email, no_handphone = :no_handphone, alamat = :alamat WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $nama);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':no_handphone', $no_handphone);
            $stmt->bindParam(':alamat', $alamat);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Data pelanggan berhasil diupdate!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_customer'])) {
        $id = $_POST['customer_id'];
        
        try {
            $query = "DELETE FROM pelanggan WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Pelanggan berhasil dihapus!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Build query with filters
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(p.nama LIKE :search OR p.email LIKE :search OR p.no_handphone LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);
$valid_sorts = ['nama', 'email', 'total_pesanan', 'total_belanja', 'created_at'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'nama';
$order = ($order === 'DESC') ? 'DESC' : 'ASC';

// Get customers with statistics
$query = "SELECT 
    p.*,
    COUNT(ps.id) as total_pesanan,
    COALESCE(SUM(ps.total_harga), 0) as total_belanja,
    MAX(ps.tanggal_pesanan) as last_order,
    CASE 
        WHEN COALESCE(SUM(ps.total_harga), 0) >= 1000000 THEN 'VIP'
        WHEN COALESCE(SUM(ps.total_harga), 0) >= 500000 THEN 'Gold'
        WHEN COALESCE(SUM(ps.total_harga), 0) >= 100000 THEN 'Silver'
        ELSE 'Regular'
    END as customer_level
    FROM pelanggan p
    LEFT JOIN pesanan ps ON p.id = ps.pelanggan_id AND ps.status = 'selesai'
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY $sort $order";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
    COUNT(*) as total_customers,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
    COUNT(CASE WHEN EXISTS(SELECT 1 FROM pesanan WHERE pelanggan_id = pelanggan.id AND status = 'selesai') THEN 1 END) as active_customers
    FROM pelanggan";

$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - Ramen Gen Kiro</title>
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
        .customer-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin-right: 1rem;
        }
        .level-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
        }
        .level-vip { background: linear-gradient(45deg, #ffd700, #ffed4e); color: #8b7355; }
        .level-gold { background: linear-gradient(45deg, #f39c12, #f1c40f); color: white; }
        .level-silver { background: linear-gradient(45deg, #95a5a6, #bdc3c7); color: white; }
        .level-regular { background: linear-gradient(45deg, #6c757d, #adb5bd); color: white; }
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: none;
        }
        @media (max-width: 768px) {
            .customer-card {
                margin-bottom: 15px;
            }
            .stat-card {
                margin-bottom: 15px;
            }
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
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../menu/index.php">
                                <i class="fas fa-utensils me-2"></i> Kelola Menu
                            </a>
                        </li>
                        <?php endif; ?>


                        <?php if (hasRole('owner') || hasRole('admin')): ?>
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
                        <?php endif; ?>
                        
                        <?php if (hasRole('owner')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../users/index.php">
                                <i class="fas fa-users me-2"></i> Data Pengguna
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>
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
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
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
                        <i class="fas fa-user-friends text-primary me-2"></i>Data Pelanggan
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                <i class="fas fa-plus"></i> Tambah Pelanggan
                            </button>
                            <button type="button" class="btn btn-info" onclick="exportCustomers()">
                                <i class="fas fa-download"></i> Export
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Pelanggan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_customers']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Baru Hari Ini
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['new_today']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Minggu Ini
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['new_this_week']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #36b9cc, #258391);">
                                            <i class="fas fa-calendar-week"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Aktif
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['active_customers']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Cari Pelanggan</label>
                                <input type="text" class="form-control" name="search" value="<?php echo $search; ?>" placeholder="Nama, email, atau nomor HP...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Urutkan</label>
                                <select class="form-select" name="sort">
                                    <option value="nama" <?php echo ($sort == 'nama') ? 'selected' : ''; ?>>Nama</option>
                                    <option value="total_pesanan" <?php echo ($sort == 'total_pesanan') ? 'selected' : ''; ?>>Total Pesanan</option>
                                    <option value="total_belanja" <?php echo ($sort == 'total_belanja') ? 'selected' : ''; ?>>Total Belanja</option>
                                    <option value="created_at" <?php echo ($sort == 'created_at') ? 'selected' : ''; ?>>Tanggal Daftar</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Urutan</label>
                                <select class="form-select" name="order">
                                    <option value="ASC" <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>A-Z / Kecil-Besar</option>
                                    <option value="DESC" <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>Z-A / Besar-Kecil</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Customers List -->
                <div class="row">
                    <?php if (empty($customers)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada pelanggan</h5>
                                <p class="text-muted">Tambahkan pelanggan pertama Anda</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                    <i class="fas fa-plus"></i> Tambah Pelanggan
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card customer-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="customer-avatar" style="background: linear-gradient(45deg, <?php echo '#' . substr(md5($customer['nama']), 0, 6); ?>, <?php echo '#' . substr(md5($customer['nama']), 6, 6); ?>);">
                                            <?php echo strtoupper(substr($customer['nama'], 0, 2)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo $customer['nama']; ?></h6>
                                            <span class="level-badge level-<?php echo strtolower($customer['customer_level']); ?>">
                                                <?php echo $customer['customer_level']; ?>
                                            </span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo $customer['nama']; ?>')">
                                                        <i class="fas fa-trash me-2"></i>Hapus
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="fw-bold text-primary"><?php echo $customer['total_pesanan']; ?></div>
                                            <small class="text-muted">Pesanan</small>
                                        </div>
                                        <div class="col-8">
                                            <div class="fw-bold text-success">Rp <?php echo number_format($customer['total_belanja'], 0, ',', '.'); ?></div>
                                            <small class="text-muted">Total Belanja</small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($customer['email']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <small><?php echo $customer['email']; ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($customer['no_handphone']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <small><?php echo $customer['no_handphone']; ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($customer['last_order']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-clock text-muted me-2"></i>
                                        <small>Terakhir: <?php echo date('d/m/Y', strtotime($customer['last_order'])); ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-muted">
                                        <i class="fas fa-calendar text-muted me-2"></i>
                                        <small>Bergabung: <?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Tambah Pelanggan Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Handphone</label>
                            <input type="text" class="form-control" name="no_handphone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_customer" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Pelanggan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCustomerForm">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="edit_customer_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Handphone</label>
                            <input type="text" class="form-control" name="no_handphone" id="edit_no_handphone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" id="edit_alamat" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_customer" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div class="modal fade" id="deleteCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteCustomerForm">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="delete_customer_id">
                        <p>Apakah Anda yakin ingin menghapus pelanggan <strong id="delete_customer_name"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_customer" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCustomer(customer) {
            document.getElementById('edit_customer_id').value = customer.id;
            document.getElementById('edit_nama').value = customer.nama;
            document.getElementById('edit_email').value = customer.email || '';
            document.getElementById('edit_no_handphone').value = customer.no_handphone || '';
            document.getElementById('edit_alamat').value = customer.alamat || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
            modal.show();
        }
        
        function deleteCustomer(id, nama) {
            document.getElementById('delete_customer_id').value = id;
            document.getElementById('delete_customer_name').textContent = nama;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCustomerModal'));
            modal.show();
        }
        
        function exportCustomers() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('export_customers.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
