<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

// Statistik untuk dashboard
$stats = [];

// Total pesanan hari ini
$query = "SELECT COUNT(*) as total FROM pesanan WHERE DATE(tanggal_pesanan) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pesanan_hari_ini'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total pendapatan hari ini
$query = "SELECT COALESCE(SUM(total_harga), 0) as total FROM pesanan WHERE DATE(tanggal_pesanan) = CURDATE() AND status = 'selesai'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pendapatan_hari_ini'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total menu
$query = "SELECT COUNT(*) as total FROM menu WHERE status != 'nonaktif'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_menu'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total meja
$query = "SELECT COUNT(*) as total FROM meja";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_meja'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pesanan terbaru
$query = "SELECT p.*, pl.nama as nama_pelanggan, m.nomor_meja 
          FROM pesanan p 
          LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id 
          LEFT JOIN meja m ON p.meja_id = m.id 
          ORDER BY p.tanggal_pesanan DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$pesanan_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ramen Gen Kiro</title>
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="menu/index.php">
                                <i class="fas fa-utensils me-2"></i> Kelola Menu
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (hasRole('owner') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reports/financial.php">
                                <i class="fas fa-chart-line me-2"></i> Laporan Keuangan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports/sales.php">
                                <i class="fas fa-chart-bar me-2"></i> Laporan Penjualan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('owner')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users/index.php">
                                <i class="fas fa-users me-2"></i> Data Pengguna
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>

                        <li class="nav-item">
                            <a class="nav-link" href="pos/index.php">
                                <i class="fas fa-cash-register me-2"></i> POS Digital
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders/index.php">
                                <i class="fas fa-clipboard-list me-2"></i> Menu Orderan
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="customers/index.php">
                                <i class="fas fa-user-friends me-2"></i> Data Pelanggan
                            </a>
                        </li>
                        
                        <hr class="text-white">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistik Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Pesanan Hari Ini
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pesanan_hari_ini']; ?>
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

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Pendapatan Hari Ini
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            Rp <?php echo number_format($stats['pendapatan_hari_ini'], 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Menu
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_menu']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #36b9cc, #258391);">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Meja
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_meja']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-chair"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pesanan Terbaru -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Pesanan Terbaru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pesanan_terbaru)): ?>
                            <p class="text-muted text-center">Belum ada pesanan hari ini</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Pesanan</th>
                                            <th>Pelanggan</th>
                                            <th>Meja</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pesanan_terbaru as $pesanan): ?>
                                        <tr>
                                            <td><strong><?php echo $pesanan['kode_pesanan']; ?></strong></td>
                                            <td><?php echo $pesanan['nama_pelanggan'] ?: 'Guest'; ?></td>
                                            <td>Meja <?php echo $pesanan['nomor_meja']; ?></td>
                                            <td>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'diproses' => 'info',
                                                    'selesai' => 'success',
                                                    'dibatalkan' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_class[$pesanan['status']]; ?>">
                                                    <?php echo ucfirst($pesanan['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('H:i', strtotime($pesanan['tanggal_pesanan'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
