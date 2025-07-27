<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Ambil pesanan berdasarkan filter
$where_conditions = ["p.status != 'dibatalkan'"];
$params = [];

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where_conditions[] = "DATE(p.tanggal_pesanan) >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where_conditions[] = "DATE(p.tanggal_pesanan) <= :date_to";
    $params[':date_to'] = $_GET['date_to'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $_GET['status'];
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT p.*, pl.nama as nama_pelanggan, m.nomor_meja 
          FROM pesanan p 
          LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id 
          LEFT JOIN meja m ON p.meja_id = m.id 
          WHERE $where_clause
          ORDER BY p.tanggal_pesanan DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$pesanan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Massal - Ramen App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-print me-2"></i>Cetak Massal Transaksi
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Filter Form -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Dari</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Sampai</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo (($_GET['status'] ?? '') == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="diproses" <?php echo (($_GET['status'] ?? '') == 'diproses') ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo (($_GET['status'] ?? '') == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="bulk_print.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>

                        <!-- Bulk Actions -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-success me-2" onclick="printSelected('receipt')">
                                    <i class="fas fa-print"></i> Cetak Struk Terpilih
                                </button>
                                <button type="button" class="btn btn-info me-2" onclick="printSelected('thermal')">
                                    <i class="fas fa-receipt"></i> Cetak Thermal Terpilih
                                </button>
                                <button type="button" class="btn btn-warning me-2" onclick="printSelected('kitchen')">
                                    <i class="fas fa-utensils"></i> Cetak Order Dapur Terpilih
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="selectAll()">
                                    <i class="fas fa-check-square"></i> Pilih Semua
                                </button>
                            </div>
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="select-all" onchange="toggleAll()">
                                        </th>
                                        <th>Kode Pesanan</th>
                                        <th>Tanggal</th>
                                        <th>Pelanggan</th>
                                        <th>Meja</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pesanan_list)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            Tidak ada data transaksi
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($pesanan_list as $pesanan): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="transaction-checkbox" value="<?php echo $pesanan['id']; ?>">
                                            </td>
                                            <td><strong><?php echo $pesanan['kode_pesanan']; ?></strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan'])); ?></td>
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
                                            <td>
                                                <div class="btn-group">
                                                    <a href="transaction.php?id=<?php echo $pesanan['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    <a href="receipt_thermal.php?id=<?php echo $pesanan['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <a href="kitchen_order.php?id=<?php echo $pesanan['id']; ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-utensils"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const selectAllCheckbox = document.getElementById('select-all');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            selectAllCheckbox.checked = true;
        }
        
        function printSelected(type) {
            const selected = [];
            document.querySelectorAll('.transaction-checkbox:checked').forEach(checkbox => {
                selected.push(checkbox.value);
            });
            
            if (selected.length === 0) {
                alert('Pilih minimal satu transaksi untuk dicetak!');
                return;
            }
            
            const baseUrl = {
                'receipt': 'transaction.php',
                'thermal': 'receipt_thermal.php',
                'kitchen': 'kitchen_order.php'
            };
            
            selected.forEach(id => {
                window.open(`${baseUrl[type]}?id=${id}&print=1`, '_blank');
            });
        }
    </script>
</body>
</html>
