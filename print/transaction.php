<?php
require_once '../config/database.php';
require_once '../config/session.php';

if (!isset($_GET['id'])) {
    die('ID transaksi tidak ditemukan');
}

$database = new Database();
$db = $database->getConnection();

// Ambil data pesanan dengan detail
$query = "SELECT p.*, pl.nama as nama_pelanggan, pl.email, pl.no_handphone, 
          m.nomor_meja, u.nama_lengkap as nama_kasir
          FROM pesanan p 
          LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id 
          LEFT JOIN meja m ON p.meja_id = m.id 
          LEFT JOIN users u ON p.kasir_id = u.id
          WHERE p.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$pesanan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pesanan) {
    die('Transaksi tidak ditemukan');
}

// Ambil detail pesanan
$query = "SELECT dp.*, mn.nama_menu 
          FROM detail_pesanan dp 
          JOIN menu mn ON dp.menu_id = mn.id 
          WHERE dp.pesanan_id = :pesanan_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':pesanan_id', $_GET['id']);
$stmt->execute();
$detail_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung pajak dan total
$subtotal = $pesanan['total_harga'];
$pajak_persen = 10; // 10% pajak
$pajak = $subtotal * ($pajak_persen / 100);
$total_dengan_pajak = $subtotal + $pajak;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi - <?php echo $pesanan['kode_pesanan']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: white;
        }
        
        .receipt {
            width: 80mm;
            max-width: 300px;
            margin: 0 auto;
            padding: 10px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .restaurant-info {
            font-size: 10px;
            line-height: 1.3;
        }
        
        .transaction-info {
            margin-bottom: 15px;
            font-size: 11px;
        }
        
        .transaction-info div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        
        .items-header {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .item {
            margin-bottom: 8px;
            font-size: 11px;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }
        
        .totals {
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .total-line.grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #000;
            font-size: 10px;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .qr-code {
            margin: 10px 0;
        }
        
        .print-buttons {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        @media print {
            .print-buttons {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .receipt {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 5px;
            }
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-diproses { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="print-buttons">
        <button class="btn" onclick="window.print()">
            🖨️ Cetak Struk
        </button>
        <button class="btn btn-success" onclick="printAndClose()">
            ✅ Cetak & Tutup
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            ❌ Tutup
        </button>
    </div>

    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="logo">🍜 Ramen Gen Kiro</div>
            <div class="restaurant-info">
                Jl. Ramen Sedap No. 123<br>
                Jakarta Selatan 12345<br>
                Telp: (021) 1234-5678<br>
                Email: info@ramenhouse.com
            </div>
        </div>

        <!-- Transaction Info -->
        <div class="transaction-info">
            <div>
                <span>No. Transaksi:</span>
                <span><strong><?php echo $pesanan['kode_pesanan']; ?></strong></span>
            </div>
            <div>
                <span>Tanggal:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan'])); ?></span>
            </div>
            <div>
                <span>Kasir:</span>
                <span><?php echo $pesanan['nama_kasir'] ?: 'Online Order'; ?></span>
            </div>
            <div>
                <span>Meja:</span>
                <span>No. <?php echo $pesanan['nomor_meja']; ?></span>
            </div>
            <div>
                <span>Status:</span>
                <span class="status-badge status-<?php echo $pesanan['status']; ?>">
                    <?php echo ucfirst($pesanan['status']); ?>
                </span>
            </div>
        </div>

        <!-- Customer Info -->
        <?php if ($pesanan['nama_pelanggan']): ?>
        <div class="transaction-info">
            <div style="font-weight: bold; margin-bottom: 5px;">PELANGGAN:</div>
            <div>
                <span>Nama:</span>
                <span><?php echo $pesanan['nama_pelanggan']; ?></span>
            </div>
            <?php if ($pesanan['no_handphone']): ?>
            <div>
                <span>HP:</span>
                <span><?php echo $pesanan['no_handphone']; ?></span>
            </div>
            <?php endif; ?>
            <?php if ($pesanan['email']): ?>
            <div>
                <span>Email:</span>
                <span><?php echo $pesanan['email']; ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Items Header -->
        <div class="items-header">
            DETAIL PESANAN
        </div>

        <!-- Items -->
        <?php foreach ($detail_pesanan as $item): ?>
        <div class="item">
            <div class="item-name"><?php echo $item['nama_menu']; ?></div>
            <div class="item-details">
                <span><?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></span>
                <span>Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
            </div>
            <?php if ($item['catatan']): ?>
            <div style="font-size: 9px; color: #666; margin-top: 2px;">
                Catatan: <?php echo $item['catatan']; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Totals -->
        <div class="totals">
            <div class="total-line">
                <span>Subtotal:</span>
                <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
            </div>
            <div class="total-line">
                <span>Pajak (<?php echo $pajak_persen; ?>%):</span>
                <span>Rp <?php echo number_format($pajak, 0, ',', '.'); ?></span>
            </div>
            <div class="total-line grand-total">
                <span>TOTAL:</span>
                <span>Rp <?php echo number_format($total_dengan_pajak, 0, ',', '.'); ?></span>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="transaction-info" style="margin-top: 15px;">
            <div>
                <span>Metode Bayar:</span>
                <span><?php echo ucfirst($pesanan['metode_pembayaran']); ?></span>
            </div>
        </div>

        <?php if ($pesanan['catatan']): ?>
        <div class="transaction-info">
            <div style="font-weight: bold;">Catatan Pesanan:</div>
            <div style="font-style: italic; margin-top: 3px;">
                <?php echo $pesanan['catatan']; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">TERIMA KASIH!</div>
            <div>Selamat menikmati hidangan Anda</div>
            <div style="margin-top: 10px;">
                Follow us: @ramenhouse<br>
                www.ramenhouse.com
            </div>
            
            <!-- QR Code Placeholder -->
            <div class="qr-code">
                <div style="border: 1px solid #000; width: 60px; height: 60px; margin: 10px auto; display: flex; align-items: center; justify-content: center; font-size: 8px;">
                    QR CODE<br>FEEDBACK
                </div>
            </div>
            
            <div style="font-size: 8px; margin-top: 10px;">
                Struk ini adalah bukti pembayaran yang sah<br>
                Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>

    <script>
        function printAndClose() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        }
        
        // Auto print jika parameter print=1
        <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
    </script>
</body>
</html>
