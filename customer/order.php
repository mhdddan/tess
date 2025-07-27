<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil data menu berdasarkan kategori
$query = "SELECT k.*, m.id as menu_id, m.nama_menu, m.deskripsi, m.harga, m.gambar, m.status
          FROM kategori_menu k 
          LEFT JOIN menu m ON k.id = m.kategori_id 
          WHERE k.status = 'aktif' AND (m.status = 'tersedia' OR m.status IS NULL)
          ORDER BY k.id, m.nama_menu";
$stmt = $db->prepare($query);
$stmt->execute();
$menu_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan menu berdasarkan kategori
$menu_by_category = [];
foreach ($menu_data as $item) {
    if (!isset($menu_by_category[$item['nama_kategori']])) {
        $menu_by_category[$item['nama_kategori']] = [];
    }
    if ($item['menu_id']) {
        $menu_by_category[$item['nama_kategori']][] = $item;
    }
}

// Ambil data meja yang kosong
$query = "SELECT * FROM meja WHERE status = 'kosong' ORDER BY nomor_meja";
$stmt = $db->prepare($query);
$stmt->execute();
$meja_tersedia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses pemesanan
if ($_POST && isset($_POST['submit_order'])) {
    $nama_pelanggan = $_POST['nama_pelanggan'] ?? '';
    $email_pelanggan = $_POST['email_pelanggan'] ?? '';
    $no_handphone = $_POST['no_handphone'] ?? '';
    $meja_id = $_POST['meja_id'] ?? '';
    $catatan = $_POST['catatan'] ?? '';
    $cart = json_decode($_POST['cart_data'], true);
    
    if (empty($nama_pelanggan) || empty($meja_id) || empty($cart)) {
        $error = 'Data pelanggan, meja, dan pesanan harus diisi!';
    } else {
        try {
            $db->beginTransaction();
            
            // Insert pelanggan
            $query = "INSERT INTO pelanggan (nama, email, no_handphone) VALUES (:nama, :email, :no_handphone)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $nama_pelanggan);
            $stmt->bindParam(':email', $email_pelanggan);
            $stmt->bindParam(':no_handphone', $no_handphone);
            $stmt->execute();
            $pelanggan_id = $db->lastInsertId();
            
            // Generate kode pesanan
            $kode_pesanan = 'ORD' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            
            // Hitung total
            $total_harga = 0;
            foreach ($cart as $item) {
                $total_harga += $item['harga'] * $item['jumlah'];
            }
            
            // Insert pesanan
            $query = "INSERT INTO pesanan (kode_pesanan, pelanggan_id, meja_id, total_harga, catatan) 
                      VALUES (:kode_pesanan, :pelanggan_id, :meja_id, :total_harga, :catatan)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':kode_pesanan', $kode_pesanan);
            $stmt->bindParam(':pelanggan_id', $pelanggan_id);
            $stmt->bindParam(':meja_id', $meja_id);
            $stmt->bindParam(':total_harga', $total_harga);
            $stmt->bindParam(':catatan', $catatan);
            $stmt->execute();
            $pesanan_id = $db->lastInsertId();
            
            // Insert detail pesanan
            foreach ($cart as $item) {
                $subtotal = $item['harga'] * $item['jumlah'];
                $query = "INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, harga_satuan, subtotal) 
                          VALUES (:pesanan_id, :menu_id, :jumlah, :harga_satuan, :subtotal)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':pesanan_id', $pesanan_id);
                $stmt->bindParam(':menu_id', $item['id']);
                $stmt->bindParam(':jumlah', $item['jumlah']);
                $stmt->bindParam(':harga_satuan', $item['harga']);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->execute();
            }
            
            // Update status meja
            $query = "UPDATE meja SET status = 'terisi' WHERE id = :meja_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':meja_id', $meja_id);
            $stmt->execute();
            
            $db->commit();
            
            // Set session untuk konfirmasi pesanan
            session_start();
            $_SESSION['order_confirmation'] = [
                'kode_pesanan' => $kode_pesanan,
                'total_harga' => $total_harga,
                'cart' => $cart,
                'nama_pelanggan' => $nama_pelanggan,
                'meja_id' => $meja_id
            ];
            
            // Redirect ke halaman konfirmasi
            header("Location: order_confirmation.php");
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Menu - Ramen Gen Kiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Mobile-first responsive design */
        .mobile-header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .category-nav {
            background: white;
            padding: 1rem 0;
            position: sticky;
            top: 80px;
            z-index: 999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .category-nav::-webkit-scrollbar {
            height: 4px;
        }
        
        .category-nav::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .category-nav::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }
        
        .category-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 25px;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .category-btn.active,
        .category-btn:hover {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border-color: #ff6b6b;
            text-decoration: none;
        }
        
        .menu-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .menu-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .menu-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        
        .quantity-controls {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .floating-cart {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .cart-sidebar {
            position: fixed;
            right: -100%;
            top: 0;
            width: 100%;
            max-width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 15px rgba(0,0,0,0.2);
            transition: right 0.3s;
            z-index: 1001;
            overflow-y: auto;
        }
        
        .cart-sidebar.show {
            right: 0;
        }
        
        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }
        
        .cart-overlay.show {
            display: block;
        }
        
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 1rem;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .total-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem;
        }
        
        .checkout-form {
            padding: 1rem;
        }
        
        .btn-add-to-cart {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add-to-cart:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .menu-card.selected {
            border: 3px solid #28a745;
            background: #f8fff9;
        }
        
        /* Payment Confirmation Modal */
        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .payment-modal.show {
            display: flex;
        }
        
        .payment-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .additional-menu {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .mobile-header h1 {
                font-size: 1.5rem;
            }
            
            .category-nav {
                top: 70px;
                padding: 0.5rem 0;
            }
            
            .menu-card {
                margin-bottom: 0.75rem;
            }
            
            .floating-cart {
                bottom: 15px;
                right: 15px;
            }
            
            .cart-sidebar {
                width: 100%;
                max-width: none;
            }
            
            .total-section {
                margin: 0.5rem;
                padding: 1rem;
            }
            
            .checkout-form {
                padding: 0.5rem;
            }
            
            .payment-content {
                padding: 1.5rem;
                width: 95%;
            }
        }
        
        @media (max-width: 576px) {
            .mobile-header {
                padding: 0.75rem;
            }
            
            .mobile-header h1 {
                font-size: 1.25rem;
            }
            
            .category-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .menu-image,
            .menu-placeholder {
                height: 120px;
            }
            
            .floating-cart {
                bottom: 10px;
                right: 10px;
            }
            
            .payment-content {
                padding: 1rem;
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Success animation */
        .success-animation {
            animation: bounce 0.6s ease-in-out;
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
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0">🍜 Ramen Gen Kiro</h1>
                <small>Pesan menu favorit Anda</small>
            </div>
            <a href="../login.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-user"></i> Staff
            </a>
        </div>
    </div>

    <!-- Category Navigation -->
    <div class="category-nav">
        <div class="container-fluid px-3">
            <a href="#" class="category-btn active" data-category="all">
                <i class="fas fa-th-large me-1"></i>Semua
            </a>
            <?php foreach ($menu_by_category as $kategori => $menu_items): ?>
            <a href="#" class="category-btn" data-category="<?php echo strtolower(str_replace(' ', '-', $kategori)); ?>">
                <i class="fas fa-utensils me-1"></i><?php echo $kategori; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Alert Messages -->
    <div class="container-fluid px-3 mt-3">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Menu Grid -->
    <div class="container-fluid px-3 pb-5" style="margin-top: 1rem;">
        <div class="row g-3" id="menuGrid">
            <?php foreach ($menu_by_category as $kategori => $menu_items): ?>
                <?php foreach ($menu_items as $menu): ?>
                <div class="col-6 col-md-4 col-lg-3 menu-item" data-category="<?php echo strtolower(str_replace(' ', '-', $kategori)); ?>">
                    <div class="card menu-card h-100" onclick="addToCart(<?php echo htmlspecialchars(json_encode($menu)); ?>)">
                        <?php if ($menu['gambar'] && file_exists('../uploads/menu/' . $menu['gambar'])): ?>
                            <img src="../uploads/menu/<?php echo $menu['gambar']; ?>" class="menu-image" alt="<?php echo $menu['nama_menu']; ?>">
                        <?php else: ?>
                            <div class="menu-placeholder">
                                <i class="fas fa-utensils"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2"><?php echo $menu['nama_menu']; ?></h6>
                            <p class="card-text text-muted small mb-2" style="font-size: 0.8rem; line-height: 1.3;">
                                <?php echo substr($menu['deskripsi'], 0, 50) . (strlen($menu['deskripsi']) > 50 ? '...' : ''); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="text-primary mb-0">Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?></h6>
                                <button class="btn btn-add-to-cart btn-sm" onclick="event.stopPropagation(); addToCart(<?php echo htmlspecialchars(json_encode($menu)); ?>)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            
                            <div class="quantity-controls" data-menu-id="<?php echo $menu['menu_id']; ?>">
                                <button class="btn btn-outline-danger quantity-btn" onclick="event.stopPropagation(); decreaseQuantity('<?php echo $menu['menu_id']; ?>')">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-display fw-bold fs-5">0</span>
                                <button class="btn btn-outline-success quantity-btn" onclick="event.stopPropagation(); increaseQuantity('<?php echo $menu['menu_id']; ?>')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Floating Cart Button -->
    <div class="floating-cart">
        <button class="btn btn-success btn-lg rounded-circle position-relative" id="cart-toggle">
            <i class="fas fa-shopping-cart"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                0
            </span>
        </button>
    </div>

    <!-- Cart Overlay -->
    <div class="cart-overlay" id="cart-overlay"></div>

    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cart-sidebar">
        <!-- Cart Header -->
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h5 class="mb-0">
                <i class="fas fa-shopping-cart me-2 text-success"></i>Keranjang Belanja
            </h5>
            <button class="btn btn-outline-secondary btn-sm" id="close-cart">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Cart Items -->
        <div id="cart-items">
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <p class="text-muted">Keranjang masih kosong</p>
                <small class="text-muted">Pilih menu untuk mulai berbelanja</small>
            </div>
        </div>
        
        <!-- Cart Total -->
        <div id="cart-total" class="total-section" style="display: none;">
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal:</span>
                <span class="fw-bold" id="subtotal-amount">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Pajak (10%):</span>
                <span id="tax-amount">Rp 0</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
                <strong>Total:</strong>
                <strong class="text-success fs-5" id="total-amount">Rp 0</strong>
            </div>
        </div>
        
        <!-- Checkout Form -->
        <div id="checkout-section" class="checkout-form" style="display: none;">
            <h6 class="mb-3 text-primary">
                <i class="fas fa-user me-2"></i>Informasi Pelanggan
            </h6>
            
            <div class="mb-3">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" class="form-control" id="nama_pelanggan" required placeholder="Masukkan nama Anda">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" id="email_pelanggan" placeholder="email@example.com">
            </div>
            
            <div class="mb-3">
                <label class="form-label">No. Handphone</label>
                <input type="text" class="form-control" id="no_handphone" placeholder="08xxxxxxxxxx">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Pilih Meja *</label>
                <select class="form-select" id="meja_id" required>
                    <option value="">-- Pilih Meja --</option>
                    <?php foreach ($meja_tersedia as $meja): ?>
                    <option value="<?php echo $meja['id']; ?>">
                        Meja <?php echo $meja['nomor_meja']; ?> (<?php echo $meja['kapasitas']; ?> orang)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                        <label class="form-label">Metode Pembayaran</label>
                        <select class="form-select" name="metode_pembayaran" required>
                            <option value="tunai">Tunai</option>
                            <option value="digital">Digital (QRIS/E-Wallet)</option>
                        </select>
                    </div>
            
            <div class="mb-3">
                <label class="form-label">Catatan Khusus</label>
                <textarea class="form-control" id="catatan" rows="2" placeholder="Catatan untuk pesanan Anda..."></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success btn-lg" onclick="showPaymentConfirmation()">
                    <i class="fas fa-check me-2"></i>Proses Pesanan
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                    <i class="fas fa-trash me-2"></i>Kosongkan Keranjang
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="payment-modal" id="payment-modal">
        <div class="payment-content">
            <div class="text-center mb-4">
                <h4 class="text-primary">
                    <i class="fas fa-receipt me-2"></i>Konfirmasi Pesanan
                </h4>
                <p class="text-muted">Periksa kembali pesanan Anda sebelum melanjutkan</p>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <h6 class="mb-3">
                    <i class="fas fa-list me-2"></i>Ringkasan Pesanan
                </h6>
                <div id="order-summary-items"></div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total Pembayaran:</strong>
                    <strong class="text-success fs-5" id="final-total">Rp 0</strong>
                </div>
            </div>
            
            <!-- Additional Menu Section -->
            <div class="additional-menu">
                <h6 class="mb-3">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Menu Lainnya?
                </h6>
                <p class="small text-muted mb-3">Pilih menu tambahan untuk melengkapi pesanan Anda</p>
                <div class="row g-2" id="additional-menu-items">
                    <!-- Menu tambahan akan dimuat di sini -->
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="mb-3">
                <h6><i class="fas fa-user me-2"></i>Informasi Pelanggan</h6>
                <div id="customer-info"></div>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success btn-lg" onclick="submitOrder()">
                    <i class="fas fa-check me-2"></i>Konfirmasi & Pesan
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="hidePaymentConfirmation()">
                    <i class="fas fa-arrow-left me-2"></i>Kembali Edit
                </button>
            </div>
            
            <!-- Hidden Form -->
            <form id="order-form" method="POST" style="display: none;">
                <input type="hidden" name="nama_pelanggan" id="form_nama_pelanggan">
                <input type="hidden" name="email_pelanggan" id="form_email_pelanggan">
                <input type="hidden" name="no_handphone" id="form_no_handphone">
                <input type="hidden" name="meja_id" id="form_meja_id">
                <input type="hidden" name="catatan" id="form_catatan">
                <input type="hidden" name="cart_data" id="form_cart_data">
                <input type="hidden" name="submit_order" value="1">
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let additionalMenus = <?php echo json_encode(array_slice($menu_data, 0, 6)); ?>; // 6 menu untuk rekomendasi
        
        // Category filter functionality
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const category = this.dataset.category;
                
                // Update active category
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Filter menu items
                document.querySelectorAll('.menu-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Smooth scroll to top of menu grid
                document.getElementById('menuGrid').scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // Add to cart functionality
        function addToCart(menu) {
            const existingItem = cart.find(item => item.id === menu.menu_id);
            if (existingItem) {
                existingItem.jumlah++;
            } else {
                cart.push({
                    id: menu.menu_id,
                    nama: menu.nama_menu,
                    harga: parseInt(menu.harga),
                    jumlah: 1
                });
            }
            
            updateCart();
            updateMenuDisplay();
            
            // Show success feedback
            showAddToCartFeedback();
        }
        
        function showAddToCartFeedback() {
            const cartBtn = document.getElementById('cart-toggle');
            cartBtn.classList.add('success-animation');
            setTimeout(() => {
                cartBtn.classList.remove('success-animation');
            }, 600);
        }
        
        // Update cart display
        function updateCart() {
            const cartCount = document.getElementById('cart-count');
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');
            const checkoutSection = document.getElementById('checkout-section');
            const subtotalAmount = document.getElementById('subtotal-amount');
            const taxAmount = document.getElementById('tax-amount');
            const totalAmount = document.getElementById('total-amount');
            
            const totalItems = cart.reduce((sum, item) => sum + item.jumlah, 0);
            const subtotal = cart.reduce((sum, item) => sum + (item.harga * item.jumlah), 0);
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            cartCount.textContent = totalItems;
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Keranjang masih kosong</p>
                        <small class="text-muted">Pilih menu untuk mulai berbelanja</small>
                    </div>
                `;
                cartTotal.style.display = 'none';
                checkoutSection.style.display = 'none';
            } else {
                let itemsHtml = '';
                cart.forEach((item, index) => {
                    itemsHtml += `
                        <div class="cart-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${item.nama}</h6>
                                    <small class="text-muted">Rp ${item.harga.toLocaleString('id-ID')} per item</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <button class="btn btn-sm btn-outline-secondary me-2" onclick="decreaseCartQuantity(${index})">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="fw-bold mx-2">${item.jumlah}</span>
                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="increaseCartQuantity(${index})">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="fw-bold text-success">
                                    Rp ${(item.harga * item.jumlah).toLocaleString('id-ID')}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                cartItems.innerHTML = itemsHtml;
                subtotalAmount.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
                taxAmount.textContent = 'Rp ' + Math.round(tax).toLocaleString('id-ID');
                totalAmount.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
                cartTotal.style.display = 'block';
                checkoutSection.style.display = 'block';
            }
            
            // Save to localStorage
            localStorage.setItem('customer_cart', JSON.stringify(cart));
        }
        
        // Update menu display with quantities
        function updateMenuDisplay() {
            document.querySelectorAll('.menu-card').forEach(card => {
                const quantityControls = card.querySelector('.quantity-controls');
                const quantityDisplay = card.querySelector('.quantity-display');
                const menuId = quantityControls?.dataset.menuId;
                
                if (menuId) {
                    const cartItem = cart.find(item => item.id === menuId);
                    
                    if (cartItem && cartItem.jumlah > 0) {
                        card.classList.add('selected');
                        quantityControls.style.display = 'flex';
                        quantityDisplay.textContent = cartItem.jumlah;
                    } else {
                        card.classList.remove('selected');
                        quantityControls.style.display = 'none';
                        quantityDisplay.textContent = '0';
                    }
                }
            });
        }
        
        // Quantity control functions
        function increaseQuantity(menuId) {
            const item = cart.find(item => item.id === menuId);
            if (item) {
                item.jumlah++;
                updateCart();
                updateMenuDisplay();
            }
        }
        
        function decreaseQuantity(menuId) {
            const itemIndex = cart.findIndex(item => item.id === menuId);
            if (itemIndex !== -1) {
                if (cart[itemIndex].jumlah > 1) {
                    cart[itemIndex].jumlah--;
                } else {
                    cart.splice(itemIndex, 1);
                }
                updateCart();
                updateMenuDisplay();
            }
        }
        
        function increaseCartQuantity(index) {
            cart[index].jumlah++;
            updateCart();
            updateMenuDisplay();
        }
        
        function decreaseCartQuantity(index) {
            if (cart[index].jumlah > 1) {
                cart[index].jumlah--;
            } else {
                cart.splice(index, 1);
            }
            updateCart();
            updateMenuDisplay();
        }
        
        function removeItem(index) {
            cart.splice(index, 1);
            updateCart();
            updateMenuDisplay();
        }
        
        function clearCart() {
            if (confirm('Yakin ingin mengosongkan keranjang?')) {
                cart = [];
                updateCart();
                updateMenuDisplay();
                localStorage.removeItem('customer_cart');
            }
        }
        
        // Payment confirmation functions
        function showPaymentConfirmation() {
            // Validate form
            const nama = document.getElementById('nama_pelanggan').value;
            const meja = document.getElementById('meja_id').value;
            
            if (!nama || !meja || cart.length === 0) {
                alert('Mohon lengkapi data pelanggan dan pilih meja!');
                return;
            }
            
            // Update order summary
            updateOrderSummary();
            
            // Update customer info
            updateCustomerInfo();
            
            // Load additional menu recommendations
            loadAdditionalMenus();
            
            // Show modal
            document.getElementById('payment-modal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function hidePaymentConfirmation() {
            document.getElementById('payment-modal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        function updateOrderSummary() {
            const orderSummaryItems = document.getElementById('order-summary-items');
            const finalTotal = document.getElementById('final-total');
            
            let itemsHtml = '';
            let subtotal = 0;
            
            cart.forEach(item => {
                const itemTotal = item.harga * item.jumlah;
                subtotal += itemTotal;
                
                itemsHtml += `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>${item.nama}</strong>
                            <br>
                            <small class="text-muted">${item.jumlah} x Rp ${item.harga.toLocaleString('id-ID')}</small>
                        </div>
                        <div class="fw-bold">
                            Rp ${itemTotal.toLocaleString('id-ID')}
                        </div>
                    </div>
                `;
            });
            
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            itemsHtml += `
                <div class="d-flex justify-content-between text-muted mb-1">
                    <span>Subtotal:</span>
                    <span>Rp ${subtotal.toLocaleString('id-ID')}</span>
                </div>
                <div class="d-flex justify-content-between text-muted mb-2">
                    <span>Pajak (10%):</span>
                    <span>Rp ${Math.round(tax).toLocaleString('id-ID')}</span>
                </div>
            `;
            
            orderSummaryItems.innerHTML = itemsHtml;
            finalTotal.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
        }
        
        function updateCustomerInfo() {
            const customerInfo = document.getElementById('customer-info');
            const nama = document.getElementById('nama_pelanggan').value;
            const email = document.getElementById('email_pelanggan').value;
            const phone = document.getElementById('no_handphone').value;
            const mejaSelect = document.getElementById('meja_id');
            const mejaNama = mejaSelect.options[mejaSelect.selectedIndex].text;
            const catatan = document.getElementById('catatan').value;
            
            let infoHtml = `
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Nama:</small>
                        <div class="fw-bold">${nama}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Meja:</small>
                        <div class="fw-bold">${mejaNama}</div>
                    </div>
                </div>
            `;
            
            if (email) {
                infoHtml += `
                    <div class="mt-2">
                        <small class="text-muted">Email:</small>
                        <div>${email}</div>
                    </div>
                `;
            }
            
            if (phone) {
                infoHtml += `
                    <div class="mt-2">
                        <small class="text-muted">No. HP:</small>
                        <div>${phone}</div>
                    </div>
                `;
            }
            
            if (catatan) {
                infoHtml += `
                    <div class="mt-2">
                        <small class="text-muted">Catatan:</small>
                        <div class="fst-italic">${catatan}</div>
                    </div>
                `;
            }
            
            customerInfo.innerHTML = infoHtml;
        }
        
        function loadAdditionalMenus() {
            const additionalMenuItems = document.getElementById('additional-menu-items');
            let menuHtml = '';
            
            // Filter menu yang belum ada di cart
            const availableMenus = additionalMenus.filter(menu => 
                !cart.some(cartItem => cartItem.id === menu.menu_id)
            ).slice(0, 4); // Maksimal 4 rekomendasi
            
            availableMenus.forEach(menu => {
                menuHtml += `
                    <div class="col-6">
                        <div class="card border-0 shadow-sm" style="cursor: pointer;" onclick="addAdditionalMenu(${JSON.stringify(menu).replace(/"/g, '&quot;')})">
                            <div class="card-body p-2 text-center">
                                <h6 class="card-title small mb-1">${menu.nama_menu}</h6>
                                <small class="text-primary fw-bold">Rp ${parseInt(menu.harga).toLocaleString('id-ID')}</small>
                                <div class="mt-1">
                                    <button class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plus"></i> Tambah
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (menuHtml === '') {
                menuHtml = '<div class="col-12 text-center text-muted"><small>Tidak ada menu tambahan yang tersedia</small></div>';
            }
            
            additionalMenuItems.innerHTML = menuHtml;
        }
        
        function addAdditionalMenu(menu) {
            const existingItem = cart.find(item => item.id === menu.menu_id);
            if (existingItem) {
                existingItem.jumlah++;
            } else {
                cart.push({
                    id: menu.menu_id,
                    nama: menu.nama_menu,
                    harga: parseInt(menu.harga),
                    jumlah: 1
                });
            }
            
            // Update displays
            updateCart();
            updateMenuDisplay();
            updateOrderSummary();
            loadAdditionalMenus(); // Refresh additional menu
            
            // Show feedback
            showAddToCartFeedback();
        }
        
        function submitOrder() {
            // Populate hidden form
            document.getElementById('form_nama_pelanggan').value = document.getElementById('nama_pelanggan').value;
            document.getElementById('form_email_pelanggan').value = document.getElementById('email_pelanggan').value;
            document.getElementById('form_no_handphone').value = document.getElementById('no_handphone').value;
            document.getElementById('form_meja_id').value = document.getElementById('meja_id').value;
            document.getElementById('form_catatan').value = document.getElementById('catatan').value;
            document.getElementById('form_cart_data').value = JSON.stringify(cart);
            
            // Show loading state
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span> Memproses Pesanan...';
            submitBtn.disabled = true;
            
            // Submit form
            document.getElementById('order-form').submit();
        }
        
        // Cart toggle functionality
        document.getElementById('cart-toggle').addEventListener('click', function() {
            document.getElementById('cart-sidebar').classList.add('show');
            document.getElementById('cart-overlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        });
        
        document.getElementById('close-cart').addEventListener('click', closeCart);
        document.getElementById('cart-overlay').addEventListener('click', closeCart);
        
        function closeCart() {
            document.getElementById('cart-sidebar').classList.remove('show');
            document.getElementById('cart-overlay').classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        // Load cart from localStorage on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedCart = localStorage.getItem('customer_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                updateCart();
                updateMenuDisplay();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCart();
                hidePaymentConfirmation();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
