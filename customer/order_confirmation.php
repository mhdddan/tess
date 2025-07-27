<?php
session_start();
require_once '../config/database.php';

// Check if order confirmation data exists
if (!isset($_SESSION['order_confirmation'])) {
    header("Location: order.php");
    exit();
}

$orderData = $_SESSION['order_confirmation'];
$database = new Database();
$db = $database->getConnection();

// Get meja info
$mejaInfo = null;
if ($orderData['meja_id']) {
    $query = "SELECT * FROM meja WHERE id = :meja_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':meja_id', $orderData['meja_id']);
    $stmt->execute();
    $mejaInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Clear session data
unset($_SESSION['order_confirmation']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Ramen House</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            animation: bounce 1s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            80% {
                transform: translateY(-5px);
            }
        }
        
        .order-details {
            padding: 2rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .order-items {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .total-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
            color: white;
        }
        
        .btn-new-order {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-new-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .estimated-time {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }
        
        @media (max-width: 576px) {
            .success-container {
                padding: 1rem 0.5rem;
            }
            
            .success-header {
                padding: 1.5rem;
            }
            
            .order-details {
                padding: 1.5rem;
            }
            
            .success-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="mb-2">Pesanan Berhasil!</h2>
                <p class="mb-0">Terima kasih atas pesanan Anda</p>
            </div>
            
            <!-- Order Details -->
            <div class="order-details">
                <div class="text-center mb-4">
                    <h4 class="text-primary"><?php echo $orderData['kode_pesanan']; ?></h4>
                    <small class="text-muted">Kode Pesanan</small>
                </div>
                
                <!-- Customer Info -->
                <div class="detail-item">
                    <div>
                        <i class="fas fa-user text-primary me-2"></i>
                        <strong>Pelanggan</strong>
                    </div>
                    <div><?php echo $orderData['nama_pelanggan']; ?></div>
                </div>
                
                <?php if ($mejaInfo): ?>
                <div class="detail-item">
                    <div>
                        <i class="fas fa-chair text-primary me-2"></i>
                        <strong>Meja</strong>
                    </div>
                    <div>Meja <?php echo $mejaInfo['nomor_meja']; ?> (<?php echo $mejaInfo['kapasitas']; ?> orang)</div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <div>
                        <i class="fas fa-clock text-primary me-2"></i>
                        <strong>Waktu Pesan</strong>
                    </div>
                    <div><?php echo date('d/m/Y H:i'); ?></div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items">
                    <h6 class="mb-3">
                        <i class="fas fa-list me-2"></i>Detail Pesanan
                    </h6>
                    <?php 
                    $subtotal = 0;
                    foreach ($orderData['cart'] as $item): 
                        $itemTotal = $item['harga'] * $item['jumlah'];
                        $subtotal += $itemTotal;
                    ?>
                    <div class="item-row">
                        <div>
                            <strong><?php echo $item['nama']; ?></strong>
                            <br>
                            <small class="text-muted"><?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></small>
                        </div>
                        <div class="fw-bold">
                            Rp <?php echo number_format($itemTotal, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Total Section -->
                <div class="total-section">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Pajak (10%):</span>
                        <span>Rp <?php echo number_format($subtotal * 0.1, 0, ',', '.'); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total Pembayaran:</strong>
                        <strong class="text-success fs-5">Rp <?php echo number_format($orderData['total_harga'] * 1.1, 0, ',', '.'); ?></strong>
                    </div>
                </div>
                
                <!-- Estimated Time -->
                <div class="estimated-time">
                    <h6 class="text-warning mb-2">
                        <i class="fas fa-clock me-2"></i>Estimasi Waktu
                    </h6>
                    <p class="mb-0">Pesanan Anda akan siap dalam <strong>15-20 menit</strong></p>
                    <small class="text-muted">Kami akan memberitahu Anda ketika pesanan sudah siap</small>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-grid gap-2 mt-4">
                    <a href="order.php" class="btn btn-new-order">
                        <i class="fas fa-plus me-2"></i>Pesan Lagi
                    </a>
                    <a href="../" class="btn btn-home">
                        <i class="fas fa-home me-2"></i>Kembali ke Beranda
                    </a>
                </div>
                
                <!-- Additional Info -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Simpan kode pesanan untuk referensi Anda
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto redirect after 30 seconds
        setTimeout(function() {
            if (confirm('Apakah Anda ingin kembali ke halaman utama?')) {
                window.location.href = 'order.php';
            }
        }, 30000);
        
        // Clear localStorage cart
        localStorage.removeItem('customer_cart');
    </script>
</body>
</html>
