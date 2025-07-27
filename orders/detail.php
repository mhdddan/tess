<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

$pesanan_id = $_GET['id'];

// Query untuk detail pesanan
$query = "SELECT 
    p.*,
    pl.nama as nama_pelanggan,
    pl.email,
    pl.no_handphone,
    pl.alamat,
    m.nomor_meja,
    m.kapasitas,
    u.nama_lengkap as nama_kasir
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id
    LEFT JOIN meja m ON p.meja_id = m.id
    LEFT JOIN users u ON p.kasir_id = u.id
    WHERE p.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $pesanan_id);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Query untuk detail item pesanan
$query = "SELECT 
    dp.*,
    m.nama_menu,
    m.harga as harga_menu,
    k.nama_kategori
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    JOIN kategori_menu k ON m.kategori_id = k.id
    WHERE dp.pesanan_id = :pesanan_id
    ORDER BY k.nama_kategori, m.nama_menu";

$stmt = $db->prepare($query);
$stmt->bindParam(':pesanan_id', $pesanan_id);
$stmt->execute();
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung pajak dan total
$subtotal = $order['total_harga'];
$pajak_persen = 10;
$pajak = $subtotal * ($pajak_persen / 100);
$total_dengan_pajak = $subtotal + $pajak;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan <?php echo $order['kode_pesanan']; ?> - Ramen App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .detail-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-diproses { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
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
        .timeline-item.completed::before {
            background: #28a745;
        }
        .timeline-item.current::before {
            background: #007bff;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }
        .item-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .item-card:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>
                            <i class="fas fa-receipt text-primary me-2"></i>
                            Detail Pesanan
                        </h2>
                        <p class="text-muted mb-0">Kode: <?php echo $order['kode_pesanan']; ?></p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <div class="btn-group">
                            <a href="../print/transaction.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-print"></i> Cetak Struk
                            </a>
                            <a href="../print/kitchen_order.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-warning">
                                <i class="fas fa-utensils"></i> Order Dapur
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Info -->
            <div class="col-lg-8">
                <div class="card detail-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Informasi Pesanan
                        </h5>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Kode Pesanan:</strong></td>
                                        <td><?php echo $order['kode_pesanan']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tanggal:</strong></td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($order['tanggal_pesanan'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kasir:</strong></td>
                                        <td><?php echo $order['nama_kasir'] ?: 'Online Order'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Metode Pembayaran:</strong></td>
                                        <td><?php echo ucfirst($order['metode_pembayaran']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Pelanggan:</strong></td>
                                        <td><?php echo $order['nama_pelanggan'] ?: 'Guest'; ?></td>
                                    </tr>
                                    <?php if ($order['email']): ?>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo $order['email']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($order['no_handphone']): ?>
                                    <tr>
                                        <td><strong>No. HP:</strong></td>
                                        <td><?php echo $order['no_handphone']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Meja:</strong></td>
                                        <td>
                                            <?php if ($order['nomor_meja']): ?>
                                                Meja <?php echo $order['nomor_meja']; ?> (<?php echo $order['kapasitas']; ?> orang)
                                            <?php else: ?>
                                                Take Away
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($order['catatan']): ?>
                        <div class="alert alert-info">
                            <strong><i class="fas fa-sticky-note me-2"></i>Catatan:</strong>
                            <?php echo $order['catatan']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-success me-2"></i>
                            Detail Item Pesanan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $current_category = '';
                        foreach ($order_items as $item): 
                            if ($current_category != $item['nama_kategori']):
                                if ($current_category != '') echo '</div>';
                                $current_category = $item['nama_kategori'];
                        ?>
                        <h6 class="text-primary mt-3 mb-3">
                            <i class="fas fa-tag me-2"></i><?php echo $current_category; ?>
                        </h6>
                        <div class="category-items">
                        <?php endif; ?>
                        
                        <div class="item-card p-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-1"><?php echo $item['nama_menu']; ?></h6>
                                    <small class="text-muted">
                                        Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?> per item
                                    </small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <span class="badge bg-primary fs-6"><?php echo $item['jumlah']; ?>x</span>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h6 class="text-success mb-0">
                                        Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                    </h6>
                                </div>
                            </div>
                            <?php if ($item['catatan']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-comment me-1"></i>
                                    Catatan: <?php echo $item['catatan']; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Order Timeline -->
                <div class="card detail-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock text-warning me-2"></i>
                            Status Timeline
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item completed">
                                <h6>Pesanan Dibuat</h6>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($order['tanggal_pesanan'])); ?>
                                </small>
                            </div>
                            
                            <div class="timeline-item <?php echo ($order['status'] == 'diproses' || $order['status'] == 'selesai') ? 'completed' : (($order['status'] == 'pending') ? 'current' : ''); ?>">
                                <h6>Menunggu Diproses</h6>
                                <small class="text-muted">
                                    <?php if ($order['status'] == 'pending'): ?>
                                        Status saat ini
                                    <?php elseif ($order['status'] == 'diproses' || $order['status'] == 'selesai'): ?>
                                        Selesai
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="timeline-item <?php echo ($order['status'] == 'selesai') ? 'completed' : (($order['status'] == 'diproses') ? 'current' : ''); ?>">
                                <h6>Sedang Diproses</h6>
                                <small class="text-muted">
                                    <?php if ($order['status'] == 'diproses'): ?>
                                        Status saat ini
                                    <?php elseif ($order['status'] == 'selesai'): ?>
                                        Selesai
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="timeline-item <?php echo ($order['status'] == 'selesai') ? 'completed current' : ''; ?>">
                                <h6>Pesanan Selesai</h6>
                                <small class="text-muted">
                                    <?php if ($order['status'] == 'selesai'): ?>
                                        Status saat ini
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <?php if ($order['status'] == 'dibatalkan'): ?>
                            <div class="timeline-item" style="color: #dc3545;">
                                <h6>Pesanan Dibatalkan</h6>
                                <small class="text-muted">Status saat ini</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calculator text-info me-2"></i>
                            Ringkasan Pembayaran
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td>Pajak (<?php echo $pajak_persen; ?>%):</td>
                                <td class="text-end">Rp <?php echo number_format($pajak, 0, ',', '.'); ?></td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Total:</strong></td>
                                <td class="text-end"><strong class="text-success">Rp <?php echo number_format($total_dengan_pajak, 0, ',', '.'); ?></strong></td>
                            </tr>
                        </table>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Metode Pembayaran:</small>
                                <small><strong><?php echo ucfirst($order['metode_pembayaran']); ?></strong></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Status Pembayaran:</small>
                                <small>
                                    <span class="badge bg-<?php echo ($order['status'] == 'selesai') ? 'success' : 'warning'; ?>">
                                        <?php echo ($order['status'] == 'selesai') ? 'Lunas' : 'Pending'; ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
