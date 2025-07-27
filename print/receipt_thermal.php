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
$pajak_persen = 10;
$pajak = $subtotal * ($pajak_persen / 100);
$total_dengan_pajak = $subtotal + $pajak;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Thermal - <?php echo $pesanan['kode_pesanan']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.2;
            color: #000;
            background: white;
        }
        
        .thermal-receipt {
            width: 58mm;
            max-width: 220px;
            margin: 0 auto;
            padding: 5px;
            background: white;
        }
        
        .center { text-align: center; }
        .left { text-align: left; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .logo {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .restaurant-info {
            font-size: 9px;
            line-height: 1.2;
        }
        
        .separator {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        
        .double-separator {
            border-top: 2px solid #000;
            margin: 8px 0;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1px;
            font-size: 10px;
        }
        
        .item {
            margin-bottom: 5px;
            font-size: 10px;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .item-qty-price {
            display: flex;
            justify-content: space-between;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 10px;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 3px;
            margin-top: 3px;
        }
        
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 8px;
        }
        
        .print-buttons {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            margin: 0 3px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        @media print {
            .print-buttons { display: none !important; }
            body { margin: 0; padding: 0; }
            .thermal-receipt { width: 100%; max-width: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-buttons">
        <button class="btn" onclick="window.print()">🖨️ Cetak</button>
        <button class="btn" onclick="printAndClose()">✅ Cetak & Tutup</button>
        <button class="btn" onclick="window.close()">❌ Tutup</button>
    </div>

    <div class="thermal-receipt">
        <!-- Header -->
        <div class="header">
            <div class="logo">🍜 RAMEN HOUSE</div>
            <div class="restaurant-info">
                Jl. Ramen Sedap No. 123<br>
                Jakarta Selatan 12345<br>
                Telp: (021) 1234-5678
            </div>
        </div>

        <div class="separator"></div>

        <!-- Transaction Info -->
        <div class="info-line">
            <span>No:</span>
            <span class="bold"><?php echo $pesanan['kode_pesanan']; ?></span>
        </div>
        <div class="info-line">
            <span>Tgl:</span>
            <span><?php echo date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan'])); ?></span>
        </div>
        <div class="info-line">
            <span>Kasir:</span>
            <span><?php echo $pesanan['nama_kasir'] ?: 'Online'; ?></span>
        </div>
        <div class="info-line">
            <span>Meja:</span>
            <span>No. <?php echo $pesanan['nomor_meja']; ?></span>
        </div>

        <?php if ($pesanan['nama_pelanggan']): ?>
        <div class="separator"></div>
        <div class="info-line">
            <span>Pelanggan:</span>
            <span><?php echo $pesanan['nama_pelanggan']; ?></span>
        </div>
        <?php if ($pesanan['no_handphone']): ?>
        <div class="info-line">
            <span>HP:</span>
            <span><?php echo $pesanan['no_handphone']; ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="separator"></div>

        <!-- Items -->
        <?php foreach ($detail_pesanan as $item): ?>
        <div class="item">
            <div class="item-name"><?php echo $item['nama_menu']; ?></div>
            <div class="item-qty-price">
                <span><?php echo $item['jumlah']; ?> x <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></span>
                <span><?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="separator"></div>

        <!-- Totals -->
        <div class="total-line">
            <span>Subtotal:</span>
            <span><?php echo number_format($subtotal, 0, ',', '.'); ?></span>
        </div>
        <div class="total-line">
            <span>Pajak (<?php echo $pajak_persen; ?>%):</span>
            <span><?php echo number_format($pajak, 0, ',', '.'); ?></span>
        </div>
        <div class="total-line grand-total">
            <span>TOTAL:</span>
            <span>Rp <?php echo number_format($total_dengan_pajak, 0, ',', '.'); ?></span>
        </div>

        <div class="separator"></div>

        <div class="info-line">
            <span>Bayar:</span>
            <span><?php echo ucfirst($pesanan['metode_pembayaran']); ?></span>
        </div>

        <?php if ($pesanan['catatan']): ?>
        <div class="separator"></div>
        <div class="center bold">CATATAN:</div>
        <div class="center" style="font-style: italic; font-size: 9px;">
            <?php echo $pesanan['catatan']; ?>
        </div>
        <?php endif; ?>

        <div class="double-separator"></div>

        <!-- Footer -->
        <div class="footer">
            <div class="bold" style="font-size: 10px;">TERIMA KASIH!</div>
            <div>Selamat menikmati</div>
            <div style="margin-top: 5px;">
                Follow: @ramenhouse<br>
                www.ramenhouse.com
            </div>
            <div style="margin-top: 8px; font-size: 7px;">
                <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>

    <script>
        function printAndClose() {
            window.print();
            setTimeout(() => window.close(), 1000);
        }
        
        <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        window.onload = () => window.print();
        <?php endif; ?>
    </script>
</body>
</html>
