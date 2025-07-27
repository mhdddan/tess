<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (!hasRole('owner') && !hasRole('admin')) {
    header("Location: ../dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

// Default filter
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Awal bulan
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hari ini
$period = $_GET['period'] ?? 'daily';

// Validasi tanggal
if (strtotime($date_from) > strtotime($date_to)) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Query untuk ringkasan keuangan
$query = "SELECT 
    COUNT(DISTINCT p.id) as total_transaksi,
    COALESCE(SUM(CASE WHEN p.status = 'selesai' THEN p.total_harga ELSE 0 END), 0) as total_pendapatan,
    COALESCE(AVG(CASE WHEN p.status = 'selesai' THEN p.total_harga ELSE NULL END), 0) as rata_rata_transaksi,
    COUNT(DISTINCT CASE WHEN p.status = 'selesai' THEN p.id END) as transaksi_selesai,
    COUNT(DISTINCT CASE WHEN p.status = 'dibatalkan' THEN p.id END) as transaksi_batal
    FROM pesanan p 
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Query untuk data grafik berdasarkan periode
$group_by = '';
$date_format = '';
switch ($period) {
    case 'hourly':
        $group_by = 'DATE(p.tanggal_pesanan), HOUR(p.tanggal_pesanan)';
        $date_format = "CONCAT(DATE(p.tanggal_pesanan), ' ', LPAD(HOUR(p.tanggal_pesanan), 2, '0'), ':00')";
        break;
    case 'daily':
        $group_by = 'DATE(p.tanggal_pesanan)';
        $date_format = 'DATE(p.tanggal_pesanan)';
        break;
    case 'weekly':
        $group_by = 'YEARWEEK(p.tanggal_pesanan)';
        $date_format = "CONCAT('Week ', WEEK(p.tanggal_pesanan), ' - ', YEAR(p.tanggal_pesanan))";
        break;
    case 'monthly':
        $group_by = 'YEAR(p.tanggal_pesanan), MONTH(p.tanggal_pesanan)';
        $date_format = "DATE_FORMAT(p.tanggal_pesanan, '%Y-%m')";
        break;
}

$query = "SELECT 
    $date_format as periode,
    COUNT(DISTINCT p.id) as jumlah_transaksi,
    COALESCE(SUM(CASE WHEN p.status = 'selesai' THEN p.total_harga ELSE 0 END), 0) as pendapatan,
    COUNT(DISTINCT CASE WHEN p.status = 'selesai' THEN p.id END) as transaksi_sukses
    FROM pesanan p 
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    GROUP BY $group_by
    ORDER BY periode";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk top menu
$query = "SELECT 
    m.nama_menu,
    k.nama_kategori,
    SUM(dp.jumlah) as total_terjual,
    SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    JOIN kategori_menu k ON m.kategori_id = k.id
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'
    GROUP BY dp.menu_id
    ORDER BY total_pendapatan DESC
    LIMIT 10";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$top_menu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk metode pembayaran
$query = "SELECT 
    p.metode_pembayaran,
    COUNT(*) as jumlah_transaksi,
    SUM(p.total_harga) as total_nilai
    FROM pesanan p 
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'
    GROUP BY p.metode_pembayaran
    ORDER BY total_nilai DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk pendapatan per kategori
$query = "SELECT 
    k.nama_kategori,
    COUNT(DISTINCT dp.pesanan_id) as jumlah_pesanan,
    SUM(dp.jumlah) as total_item,
    SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    JOIN kategori_menu k ON m.kategori_id = k.id
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'
    GROUP BY k.id
    ORDER BY total_pendapatan DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$category_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung pajak (10%)
$pajak_persen = 10;
$pendapatan_kotor = $summary['total_pendapatan'];
$pajak = $pendapatan_kotor * ($pajak_persen / 100);
$pendapatan_bersih = $pendapatan_kotor - $pajak;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Ramen Gen Kiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
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
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: none;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        @keyframes progress-bar-stripes {
            0% { background-position: 1rem 0; }
            100% { background-position: 0 0; }
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
                            <a class="nav-link active" href="financial.php">
                                <i class="fas fa-chart-line me-2"></i> Laporan Keuangan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="sales.php">
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
                        <i class="fas fa-chart-line text-success me-2"></i>Laporan Keuangan
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Dari</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Sampai</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Periode Grafik</label>
                                <select class="form-select" name="period">
                                    <option value="daily" <?php echo ($period == 'daily') ? 'selected' : ''; ?>>Harian</option>
                                    <option value="weekly" <?php echo ($period == 'weekly') ? 'selected' : ''; ?>>Mingguan</option>
                                    <option value="monthly" <?php echo ($period == 'monthly') ? 'selected' : ''; ?>>Bulanan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="financial.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Pendapatan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            Rp <?php echo number_format($pendapatan_kotor, 0, ',', '.'); ?>
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
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Transaksi
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($summary['total_transaksi'], 0, ',', '.'); ?>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Rata-rata Transaksi
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            Rp <?php echo number_format($summary['rata_rata_transaksi'], 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #36b9cc, #258391);">
                                            <i class="fas fa-calculator"></i>
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
                                            Tingkat Keberhasilan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $success_rate = $summary['total_transaksi'] > 0 ? 
                                                ($summary['transaksi_selesai'] / $summary['total_transaksi']) * 100 : 0;
                                            echo number_format($success_rate, 1); 
                                            ?>%
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafik Pendapatan -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-area text-primary me-2"></i>
                                    Grafik Pendapatan (<?php echo ucfirst($period); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Keuangan -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-calculator text-success me-2"></i>
                                    Rincian Keuangan
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Pendapatan Kotor:</strong></td>
                                        <td class="text-end">Rp <?php echo number_format($pendapatan_kotor, 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Pajak (<?php echo $pajak_persen; ?>%):</strong></td>
                                        <td class="text-end text-danger">- Rp <?php echo number_format($pajak, 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Pendapatan Bersih:</strong></td>
                                        <td class="text-end text-success"><strong>Rp <?php echo number_format($pendapatan_bersih, 0, ',', '.'); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card text-info me-2"></i>
                                    Metode Pembayaran
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($payment_methods as $method): ?>
                                <?php 
                                $percentage = $pendapatan_kotor > 0 ? ($method['total_nilai'] / $pendapatan_kotor) * 100 : 0;
                                $method_colors = [
                                    'tunai' => 'success',
                                    'kartu' => 'primary',
                                    'digital' => 'info'
                                ];
                                $color = $method_colors[$method['metode_pembayaran']] ?? 'secondary';
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><?php echo ucfirst($method['metode_pembayaran']); ?></span>
                                        <span>Rp <?php echo number_format($method['total_nilai'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" 
                                             style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $method['jumlah_transaksi']; ?> transaksi (<?php echo number_format($percentage, 1); ?>%)</small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Menu dan Kategori -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star text-warning me-2"></i>
                                    Top 10 Menu Terlaris
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Menu</th>
                                                <th>Kategori</th>
                                                <th>Terjual</th>
                                                <th>Pendapatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_menu as $index => $menu): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                                    <?php echo $menu['nama_menu']; ?>
                                                </td>
                                                <td><small class="text-muted"><?php echo $menu['nama_kategori']; ?></small></td>
                                                <td><?php echo $menu['total_terjual']; ?>x</td>
                                                <td>Rp <?php echo number_format($menu['total_pendapatan'], 0, ',', '.'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-tags text-primary me-2"></i>
                                    Pendapatan per Kategori
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Detail Kategori -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-table text-info me-2"></i>
                                    Detail Pendapatan per Kategori
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Jumlah Pesanan</th>
                                                <th>Total Item</th>
                                                <th>Total Pendapatan</th>
                                                <th>Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($category_revenue as $category): ?>
                                            <?php $percentage = $pendapatan_kotor > 0 ? ($category['total_pendapatan'] / $pendapatan_kotor) * 100 : 0; ?>
                                            <tr>
                                                <td><strong><?php echo $category['nama_kategori']; ?></strong></td>
                                                <td><?php echo number_format($category['jumlah_pesanan']); ?></td>
                                                <td><?php echo number_format($category['total_item']); ?></td>
                                                <td>Rp <?php echo number_format($category['total_pendapatan'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" 
                                                             style="width: <?php echo $percentage; ?>%">
                                                            <?php echo number_format($percentage, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data untuk grafik pendapatan
        const revenueData = {
            labels: [<?php echo "'" . implode("','", array_column($chart_data, 'periode')) . "'"; ?>],
            datasets: [{
                label: 'Pendapatan',
                data: [<?php echo implode(',', array_column($chart_data, 'pendapatan')); ?>],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }, {
                label: 'Jumlah Transaksi',
                data: [<?php echo implode(',', array_column($chart_data, 'jumlah_transaksi')); ?>],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        };

        // Konfigurasi grafik pendapatan
        const revenueConfig = {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Periode'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Transaksi'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                } else {
                                    return 'Transaksi: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            }
        };

        // Data untuk grafik kategori
        const categoryData = {
            labels: [<?php echo "'" . implode("','", array_column($category_revenue, 'nama_kategori')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($category_revenue, 'total_pendapatan')); ?>],
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        // Konfigurasi grafik kategori
        const categoryConfig = {
            type: 'doughnut',
            data: categoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': Rp ' + context.parsed.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        };

        // Render grafik
        const revenueChart = new Chart(document.getElementById('revenueChart'), revenueConfig);
        const categoryChart = new Chart(document.getElementById('categoryChart'), categoryConfig);

        // Export functions
        function exportToExcel() {
            window.open('export_financial.php?format=excel&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>', '_blank');
        }

        function exportToPDF() {
            window.open('export_financial.php?format=pdf&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>', '_blank');
        }

        // Auto refresh setiap 5 menit
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
