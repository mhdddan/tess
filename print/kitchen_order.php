<?php
require_once '../config/database.php';
require_once '../config/session.php';

if (!isset($_GET['id'])) {
    die('ID pesanan tidak ditemukan');
}

$database = new Database();
$db = $database->getConnection();

// Ambil data pesanan dengan detail
$query = "SELECT p.*, pl.nama as nama_pelanggan, m.nomor_meja, u.nama_lengkap as nama_kasir
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
    die('Pesanan tidak ditemukan');
}

// Ambil detail pesanan
$query = "SELECT dp.*, mn.nama_menu, k.nama_kategori
          FROM detail_pesanan dp 
          JOIN menu mn ON dp.menu_id = mn.id 
          JOIN kategori_menu k ON mn.kategori_id = k.id
          WHERE dp.pesanan_id = :pesanan_id
          ORDER BY k.nama_kategori, mn.nama_menu";
$stmt = $db->prepare($query);
$stmt->bindParam(':pesanan_id', $_GET['id']);
$stmt->execute();
$detail_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Dapur - <?php echo $pesanan['kode_pesanan']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background: white;
        }
        
        .kitchen-order {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 15px;
            background: white;
            border: 2px solid #000;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .order-type {
            font-size: 24px;
            font-weight: bold;
            background: #000;
            color: white;
            padding: 8px;
            margin-bottom: 10px;
        }
        
        .order-info {
            font-size: 18px;
            font-weight: bold;
        }
        
        .info-section {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .items-section {
            margin-bottom: 20px;
        }
        
        .category-header {
            background: #333;
            color: white;
            padding: 8px;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .item {
            border: 2px solid #333;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: bold;
        }
        
        .item-qty {
            font-size: 24px;
            font-weight: bold;
            background: #000;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
        }
        
        .item-notes {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 8px;
            margin-top: 8px;
            font-style: italic;
            border-radius: 3px;
        }
        
        .special-notes {
            background: #ffebee;
            border: 2px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .special-notes-header {
            font-weight: bold;
            font-size: 16px;
            color: #d32f2f;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .footer {
            text-align: center;
            border-top: 3px solid #000;
            padding-top: 10px;
            font-size: 12px;
        }
        
        .print-buttons {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            margin: 0 5px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        @media print {
            .print-buttons { display: none !important; }
            body { margin: 0; padding: 0; }
            .kitchen-order { max-width: none; margin: 0; border: none; }
        }
        
        .priority-high {
            background: #ffcdd2 !important;
            border-color: #f44336 !important;
        }
        
        .priority-urgent {
            background: #ff5722 !important;
            color: white !important;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <div class="print-buttons">
        <button class="btn" onclick="window.print()">
            🖨️ Cetak Order
        </button>
        <button class="btn btn-success" onclick="printAndClose()">
            ✅ Cetak & Tutup
        </button>
        <button class="btn btn-danger" onclick="window.close()">
            ❌ Tutup
        </button>
    </div>

    <div class="kitchen-order">
        <!-- Header -->
        <div class="header">
            <div class="order-type">🍜 ORDER DAPUR</div>
            <div class="order-info">
                <?php echo $pesanan['kode_pesanan']; ?>
            </div>
        </div>

        <!-- Order Info -->
        <div class="info-section">
            <div class="info-row">
                <span>MEJA:</span>
                <span>NO. <?php echo $pesanan['nomor_meja']; ?></span>
            </div>
            <div class="info-row">
                <span>WAKTU:</span>
                <span><?php echo date('H:i', strtotime($pesanan['tanggal_pesanan'])); ?></span>
            </div>
            <div class="info-row">
                <span>TANGGAL:</span>
                <span><?php echo date('d/m/Y', strtotime($pesanan['tanggal_pesanan'])); ?></span>
            </div>
            <?php if ($pesanan['nama_pelanggan']): ?>
            <div class="info-row">
                <span>PELANGGAN:</span>
                <span><?php echo strtoupper($pesanan['nama_pelanggan']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span>KASIR:</span>
                <span><?php echo strtoupper($pesanan['nama_kasir'] ?: 'ONLINE ORDER'); ?></span>
            </div>
        </div>

        <!-- Special Notes -->
        <?php if ($pesanan['catatan']): ?>
        <div class="special-notes">
            <div class="special-notes-header">⚠️ CATATAN KHUSUS:</div>
            <div style="font-size: 16px; font-weight: bold;">
                <?php echo strtoupper($pesanan['catatan']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Items by Category -->
        <div class="items-section">
            <?php 
            $current_category = '';
            foreach ($detail_pesanan as $item): 
                if ($current_category != $item['nama_kategori']):
                    $current_category = $item['nama_kategori'];
            ?>
            <div class="category-header">
                📋 <?php echo strtoupper($current_category); ?>
            </div>
            <?php endif; ?>
            
            <div class="item">
                <div class="item-header">
                    <div class="item-name"><?php echo strtoupper($item['nama_menu']); ?></div>
                    <div class="item-qty"><?php echo $item['jumlah']; ?>x</div>
                </div>
                
                <?php if ($item['catatan']): ?>
                <div class="item-notes">
                    <strong>CATATAN:</strong> <?php echo strtoupper($item['catatan']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div style="font-weight: bold; font-size: 14px; margin-bottom: 5px;">
                TOTAL ITEM: <?php echo array_sum(array_column($detail_pesanan, 'jumlah')); ?>
            </div>
            <div>
                Dicetak: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
            <div style="margin-top: 10px; font-weight: bold;">
                STATUS: <?php echo strtoupper($pesanan['status']); ?>
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
        
        // Refresh halaman setiap 30 detik untuk update status
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
