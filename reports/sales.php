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
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$category_filter = $_GET['category'] ?? '';
$menu_filter = $_GET['menu'] ?? '';

// Query untuk summary penjualan
$query = "SELECT 
    COUNT(DISTINCT p.id) as total_pesanan,
    SUM(dp.jumlah) as total_item_terjual,
    COUNT(DISTINCT dp.menu_id) as menu_terjual,
    SUM(dp.subtotal) as total_penjualan
    FROM detail_pesanan dp
    JOIN pesanan p ON dp.pesanan_id = p.id
    JOIN menu m ON dp.menu_id = m.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'";

$params = [':date_from' => $date_from, ':date_to' => $date_to];

if ($category_filter) {
    $query .= " AND m.kategori_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if ($menu_filter) {
    $query .= " AND m.id = :menu_id";
    $params[':menu_id'] = $menu_filter;
}

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Query untuk top menu
$query = "SELECT 
    m.nama_menu,
    k.nama_kategori,
    m.harga,
    SUM(dp.jumlah) as total_terjual,
    SUM(dp.subtotal) as total_pendapatan,
    AVG(dp.harga_satuan) as harga_rata
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    JOIN kategori_menu k ON m.kategori_id = k.id
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'";

if ($category_filter) {
    $query .= " AND m.kategori_id = :category_id";
}

if ($menu_filter) {
    $query .= " AND m.id = :menu_id";
}

$query .= " GROUP BY dp.menu_id ORDER BY total_terjual DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$menu_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk penjualan per kategori
$query = "SELECT 
    k.nama_kategori,
    COUNT(DISTINCT dp.pesanan_id) as jumlah_pesanan,
    SUM(dp.jumlah) as total_item,
    SUM(dp.subtotal) as total_penjualan,
    AVG(dp.subtotal) as rata_rata_penjualan
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    JOIN kategori_menu k ON m.kategori_id = k.id
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'
    GROUP BY k.id
    ORDER BY total_penjualan DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk trend penjualan harian
$query = "SELECT 
    DATE(p.tanggal_pesanan) as tanggal,
    COUNT(DISTINCT p.id) as jumlah_pesanan,
    SUM(dp.jumlah) as total_item,
    SUM(dp.subtotal) as total_penjualan
    FROM detail_pesanan dp
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    AND p.status = 'selesai'
    GROUP BY DATE(p.tanggal_pesanan)
    ORDER BY tanggal";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk kategori dropdown
$query = "SELECT * FROM kategori_menu WHERE status = 'aktif' ORDER BY nama_kategori";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk menu dropdown
$menu_options = [];
if ($category_filter) {
    $query = "SELECT * FROM menu WHERE kategori_id = :category_id AND status != 'nonaktif' ORDER BY nama_menu";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $category_filter);
    $stmt->execute();
    $menu_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Ramen Gen Kiro</title>
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
        .product-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .rank-badge {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .rank-1 { background: #FFD700; }
        .rank-2 { background: #C0C0C0; }
        .rank-3 { background: #CD7F32; }
        .rank-other { background: #6c757d; }
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
                            <a class="nav-link" href="financial.php">
                                <i class="fas fa-chart-line me-2"></i> Laporan Keuangan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="sales.php">
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
                        <i class="fas fa-chart-bar text-primary me-2"></i>Laporan Penjualan
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" onclick="exportSales('excel')">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="exportSales('pdf')">
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
                            <div class="col-md-2">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category" onchange="loadMenuOptions(this.value)">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo $category['nama_kategori']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Menu</label>
                                <select class="form-select" name="menu" id="menuSelect">
                                    <option value="">Semua Menu</option>
                                    <?php foreach ($menu_options as $menu): ?>
                                    <option value="<?php echo $menu['id']; ?>" <?php echo ($menu_filter == $menu['id']) ? 'selected' : ''; ?>>
                                        <?php echo $menu['nama_menu']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="sales.php" class="btn btn-secondary">Reset</a>
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
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Pesanan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($summary['total_pesanan'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                                            <i class="fas fa-shopping-cart"></i>
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
                                            Total Item Terjual
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($summary['total_item_terjual'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                                            <i class="fas fa-boxes"></i>
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
                                            Menu Terjual
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($summary['menu_terjual'] ?? 0); ?>
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
                                            Total Penjualan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            Rp <?php echo number_format($summary['total_penjualan'] ?? 0, 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line text-primary me-2"></i>
                                    Trend Penjualan Harian
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="salesTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie text-success me-2"></i>
                                    Penjualan per Kategori
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy text-warning me-2"></i>
                                    Produk Terlaris
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach (array_slice($menu_sales, 0, 12) as $index => $menu): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                        <div class="card product-card h-100 position-relative">
                                            <div class="rank-badge rank-<?php echo $index < 3 ? $index + 1 : 'other'; ?>">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div class="card-body text-center">
                                                <h6 class="card-title"><?php echo $menu['nama_menu']; ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted"><?php echo $menu['nama_kategori']; ?></small>
                                                </p>
                                                <div class="mb-2">
                                                    <span class="badge bg-primary"><?php echo $menu['total_terjual']; ?>x Terjual</span>
                                                </div>
                                                <h6 class="text-success">
                                                    Rp <?php echo number_format($menu['total_pendapatan'], 0, ',', '.'); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    @ Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Performance Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-table text-info me-2"></i>
                                    Performa Kategori
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
                                                <th>Total Penjualan</th>
                                                <th>Rata-rata</th>
                                                <th>Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_all_sales = array_sum(array_column($category_sales, 'total_penjualan'));
                                            foreach ($category_sales as $category): 
                                            $percentage = $total_all_sales > 0 ? ($category['total_penjualan'] / $total_all_sales) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $category['nama_kategori']; ?></strong></td>
                                                <td><?php echo number_format($category['jumlah_pesanan']); ?></td>
                                                <td><?php echo number_format($category['total_item']); ?></td>
                                                <td>Rp <?php echo number_format($category['total_penjualan'], 0, ',', '.'); ?></td>
                                                <td>Rp <?php echo number_format($category['rata_rata_penjualan'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-primary" 
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
        // Data untuk grafik trend penjualan
        const salesTrendData = {
            labels: [<?php echo "'" . implode("','", array_map(function($item) { return date('d/m', strtotime($item['tanggal'])); }, $daily_sales)) . "'"; ?>],
            datasets: [{
                label: 'Total Penjualan',
                data: [<?php echo implode(',', array_column($daily_sales, 'total_penjualan')); ?>],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }, {
                label: 'Jumlah Item',
                data: [<?php echo implode(',', array_column($daily_sales, 'total_item')); ?>],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        };

        // Data untuk grafik kategori
        const categoryData = {
            labels: [<?php echo "'" . implode("','", array_column($category_sales, 'nama_kategori')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($category_sales, 'total_penjualan')); ?>],
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        // Render charts
        const salesTrendChart = new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: salesTrendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
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
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        const categoryChart = new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: categoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Load menu options based on category
        function loadMenuOptions(categoryId) {
            const menuSelect = document.getElementById('menuSelect');
            menuSelect.innerHTML = '<option value="">Semua Menu</option>';
            
            if (categoryId) {
                fetch(`../api/get_menu.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(menu => {
                            const option = document.createElement('option');
                            option.value = menu.id;
                            option.textContent = menu.nama_menu;
                            menuSelect.appendChild(option);
                        });
                    });
            }
        }

        // Export functions
        function exportSales(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('format', format);
            window.open('export_sales.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
