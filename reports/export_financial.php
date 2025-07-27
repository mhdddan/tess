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

$format = $_GET['format'] ?? 'excel';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Query data untuk export
$query = "SELECT 
    p.kode_pesanan,
    p.tanggal_pesanan,
    pl.nama as nama_pelanggan,
    m.nomor_meja,
    p.total_harga,
    p.status,
    p.metode_pembayaran,
    u.nama_lengkap as kasir
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id
    LEFT JOIN meja m ON p.meja_id = m.id
    LEFT JOIN users u ON p.kasir_id = u.id
    WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
    ORDER BY p.tanggal_pesanan DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary data
$query = "SELECT 
    COUNT(*) as total_transaksi,
    SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_pendapatan,
    AVG(CASE WHEN status = 'selesai' THEN total_harga ELSE NULL END) as rata_rata
    FROM pesanan 
    WHERE DATE(tanggal_pesanan) BETWEEN :date_from AND :date_to";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

if ($format == 'excel') {
    // Export ke Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_keuangan_' . $date_from . '_' . $date_to . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, ['LAPORAN KEUANGAN RAMEN HOUSE']);
    fputcsv($output, ['Periode: ' . date('d/m/Y', strtotime($date_from)) . ' - ' . date('d/m/Y', strtotime($date_to))]);
    fputcsv($output, ['Dicetak: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, []);
    
    // Summary
    fputcsv($output, ['RINGKASAN']);
    fputcsv($output, ['Total Transaksi', $summary['total_transaksi']]);
    fputcsv($output, ['Total Pendapatan', 'Rp ' . number_format($summary['total_pendapatan'], 0, ',', '.')]);
    fputcsv($output, ['Rata-rata Transaksi', 'Rp ' . number_format($summary['rata_rata'], 0, ',', '.')]);
    fputcsv($output, []);
    
    // Header tabel
    fputcsv($output, [
        'Kode Pesanan',
        'Tanggal',
        'Pelanggan',
        'Meja',
        'Total',
        'Status',
        'Pembayaran',
        'Kasir'
    ]);
    
    // Data transaksi
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction['kode_pesanan'],
            date('d/m/Y H:i', strtotime($transaction['tanggal_pesanan'])),
            $transaction['nama_pelanggan'] ?: 'Guest',
            'Meja ' . $transaction['nomor_meja'],
            'Rp ' . number_format($transaction['total_harga'], 0, ',', '.'),
            ucfirst($transaction['status']),
            ucfirst($transaction['metode_pembayaran']),
            $transaction['kasir'] ?: 'Online'
        ]);
    }
    
    fclose($output);
    
} elseif ($format == 'pdf') {
    // Export ke PDF (HTML to PDF)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Keuangan</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; }
            .summary { margin-bottom: 30px; }
            .summary table { width: 100%; border-collapse: collapse; }
            .summary td { padding: 5px; border: 1px solid #ddd; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 10px; }
            .data-table th, .data-table td { padding: 4px; border: 1px solid #ddd; text-align: left; }
            .data-table th { background-color: #f5f5f5; font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>🍜 RAMEN HOUSE</h2>
            <h3>LAPORAN KEUANGAN</h3>
            <p>Periode: <?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?></p>
            <p>Dicetak: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
        
        <div class="summary">
            <h4>RINGKASAN KEUANGAN</h4>
            <table>
                <tr>
                    <td><strong>Total Transaksi</strong></td>
                    <td class="text-right"><?php echo number_format($summary['total_transaksi']); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Pendapatan</strong></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_pendapatan'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td><strong>Rata-rata Transaksi</strong></td>
                    <td class="text-right">Rp <?php echo number_format($summary['rata_rata'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td><strong>Pajak (10%)</strong></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_pendapatan'] * 0.1, 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td><strong>Pendapatan Bersih</strong></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_pendapatan'] * 0.9, 0, ',', '.'); ?></td>
                </tr>
            </table>
        </div>
        
        <h4>DETAIL TRANSAKSI</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode Pesanan</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Meja</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $index => $transaction): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo $transaction['kode_pesanan']; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($transaction['tanggal_pesanan'])); ?></td>
                    <td><?php echo $transaction['nama_pelanggan'] ?: 'Guest'; ?></td>
                    <td class="text-center">Meja <?php echo $transaction['nomor_meja']; ?></td>
                    <td class="text-right">Rp <?php echo number_format($transaction['total_harga'], 0, ',', '.'); ?></td>
                    <td class="text-center"><?php echo ucfirst($transaction['status']); ?></td>
                    <td class="text-center"><?php echo ucfirst($transaction['metode_pembayaran']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center; font-size: 10px;">
            <p>Laporan ini digenerate otomatis oleh sistem Ramen House</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 1000);
            }
        </script>
    </body>
    </html>
    <?php
}
?>
