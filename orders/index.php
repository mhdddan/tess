<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Handle status update
if ($_POST && isset($_POST['update_status'])) {
    $pesanan_id = $_POST['pesanan_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $query = "UPDATE pesanan SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $pesanan_id);
        
        if ($stmt->execute()) {
            // Update meja status jika pesanan selesai atau dibatalkan
            if ($new_status == 'selesai' || $new_status == 'dibatalkan') {
                $query = "UPDATE meja SET status = 'kosong' WHERE id = (SELECT meja_id FROM pesanan WHERE id = :id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $pesanan_id);
                $stmt->execute();
            }
            
            $success = "Status pesanan berhasil diupdate!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Query untuk mengambil data pesanan
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(p.tanggal_pesanan) = :date";
    $params[':date'] = $date_filter;
}

if ($search) {
    $where_conditions[] = "(p.kode_pesanan LIKE :search OR pl.nama LIKE :search OR m.nomor_meja LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT 
    p.*,
    pl.nama as nama_pelanggan,
    pl.no_handphone,
    m.nomor_meja,
    m.kapasitas,
    u.nama_lengkap as nama_kasir,
    COUNT(dp.id) as jumlah_item
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id
    LEFT JOIN meja m ON p.meja_id = m.id
    LEFT JOIN users u ON p.kasir_id = u.id
    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.tanggal_pesanan DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics
$query = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'diproses' THEN 1 END) as processing_orders,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN status = 'dibatalkan' THEN 1 END) as cancelled_orders
    FROM pesanan 
    WHERE DATE(tanggal_pesanan) = :date";

$stmt = $db->prepare($query);
$stmt->bindParam(':date', $date_filter);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Orderan - Ramen App</title>
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
        .order-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-diproses { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
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
        .order-timeline {
            position: relative;
            padding-left: 30px;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.active::before {
            background: #28a745;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: none;
        }
        @media (max-width: 768px) {
            .order-card {
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
                        
                       
                        <li class="nav-item">
                            <a class="nav-link" href="../menu/index.php">
                                <i class="fas fa-utensils me-2"></i> Kelola Menu
                            </a>
                        </li>
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
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../pos/index.php">
                                <i class="fas fa-cash-register me-2"></i> POS Digital
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
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
                        <i class="fas fa-clipboard-list text-primary me-2"></i>Menu Orderan
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="../pos/index.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> Pesanan Baru
                            </a>
                            <button type="button" class="btn btn-info" onclick="refreshOrders()">
                                <i class="fas fa-sync-alt"></i> Refresh
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
                                            Total Pesanan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_orders']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                                            <i class="fas fa-clipboard-list"></i>
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
                                            Pending
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_orders']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-clock"></i>
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
                                            Diproses
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['processing_orders']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #36b9cc, #258391);">
                                            <i class="fas fa-cogs"></i>
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
                                            Selesai
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['completed_orders']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                                            <i class="fas fa-check-circle"></i>
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
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="diproses" <?php echo ($status_filter == 'diproses') ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo ($status_filter == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="dibatalkan" <?php echo ($status_filter == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cari</label>
                                <input type="text" class="form-control" name="search" value="<?php echo $search; ?>" placeholder="Kode pesanan, nama pelanggan, atau meja...">
                            </div>
                            <div class="col-md-2">
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

                <!-- Orders List -->
                <div class="row">
                    <?php if (empty($orders)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada pesanan</h5>
                                <p class="text-muted">Belum ada pesanan untuk filter yang dipilih</p>
                                <a href="../pos/index.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Buat Pesanan Baru
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card order-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo $order['kode_pesanan']; ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($order['tanggal_pesanan'])); ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Pelanggan:</small>
                                            <div class="fw-bold"><?php echo $order['nama_pelanggan'] ?: 'Guest'; ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Meja:</small>
                                            <div class="fw-bold">
                                                <?php if ($order['nomor_meja']): ?>
                                                    Meja <?php echo $order['nomor_meja']; ?>
                                                <?php else: ?>
                                                    Take Away
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Total:</small>
                                            <div class="fw-bold text-success">
                                                Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Item:</small>
                                            <div class="fw-bold"><?php echo $order['jumlah_item']; ?> item</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Pembayaran:</small>
                                            <div class="fw-bold"><?php echo ucfirst($order['metode_pembayaran']); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Kasir:</small>
                                            <div class="fw-bold"><?php echo $order['nama_kasir'] ?: 'Online'; ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($order['catatan']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">Catatan:</small>
                                        <div class="fst-italic"><?php echo $order['catatan']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['no_handphone']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">Kontak:</small>
                                        <div><?php echo $order['no_handphone']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="row g-2">
                                        <div class="col-12 mb-2">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="pesanan_id" value="<?php echo $order['id']; ?>">
                                                <select class="form-select form-select-sm" name="new_status" onchange="this.form.submit()">
                                                    <option value="">Update Status</option>
                                                    <?php if ($order['status'] == 'pending'): ?>
                                                        <option value="diproses">Proses Pesanan</option>
                                                        <option value="dibatalkan">Batalkan</option>
                                                    <?php elseif ($order['status'] == 'diproses'): ?>
                                                        <option value="selesai">Selesaikan</option>
                                                        <option value="dibatalkan">Batalkan</option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </div>
                                        <div class="col-4">
                                            <a href="detail.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </div>
                                        <div class="col-4">
                                            <a href="../print/transaction.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline-info btn-sm w-100">
                                                <i class="fas fa-print"></i> Struk
                                            </a>
                                        </div>
                                        <div class="col-4">
                                            <a href="../print/kitchen_order.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline-warning btn-sm w-100">
                                                <i class="fas fa-utensils"></i> Dapur
                                            </a>
                                        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshOrders() {
            location.reload();
        }
        
        // Auto refresh every 30 seconds
        setInterval(refreshOrders, 30000);
        
        // Sound notification for new orders (if supported)
        function playNotificationSound() {
            if ('Audio' in window) {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
                audio.play().catch(() => {});
            }
        }
        
        // Check for new orders periodically
        let lastOrderCount = <?php echo count($orders); ?>;
        
        function checkNewOrders() {
            fetch('check_new_orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > lastOrderCount) {
                        playNotificationSound();
                        showNotification('Pesanan baru masuk!', 'Ada ' + (data.count - lastOrderCount) + ' pesanan baru');
                        lastOrderCount = data.count;
                    }
                })
                .catch(error => console.log('Error checking new orders:', error));
        }
        
        function showNotification(title, message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        }
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Check for new orders every 15 seconds
        setInterval(checkNewOrders, 15000);
    </script>
</body>
</html>
