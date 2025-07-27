<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

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

// Ambil data meja
$query = "SELECT * FROM meja ORDER BY nomor_meja";
$stmt = $db->prepare($query);
$stmt->execute();
$meja_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses pemesanan
if ($_POST && isset($_POST['submit_order'])) {
    $nama_pelanggan = $_POST['nama_pelanggan'] ?? '';
    $email_pelanggan = $_POST['email_pelanggan'] ?? '';
    $no_handphone = $_POST['no_handphone'] ?? '';
    $meja_id = $_POST['meja_id'] ?? null;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'tunai';
    $catatan = $_POST['catatan'] ?? '';
    $cart = json_decode($_POST['cart_data'], true);
    
    if (empty($cart)) {
        $error = 'Keranjang kosong! Silakan pilih menu terlebih dahulu.';
    } else {
        try {
            $db->beginTransaction();
            
            // Insert atau update pelanggan
            $pelanggan_id = null;
            if (!empty($nama_pelanggan)) {
                // Cek apakah pelanggan sudah ada berdasarkan email
                if (!empty($email_pelanggan)) {
                    $query = "SELECT id FROM pelanggan WHERE email = :email";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $email_pelanggan);
                    $stmt->execute();
                    $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_customer) {
                        $pelanggan_id = $existing_customer['id'];
                        // Update data pelanggan
                        $query = "UPDATE pelanggan SET nama = :nama, no_handphone = :no_handphone WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':nama', $nama_pelanggan);
                        $stmt->bindParam(':no_handphone', $no_handphone);
                        $stmt->bindParam(':id', $pelanggan_id);
                        $stmt->execute();
                    }
                }
                
                // Jika belum ada, insert pelanggan baru
                if (!$pelanggan_id) {
                    $query = "INSERT INTO pelanggan (nama, email, no_handphone) VALUES (:nama, :email, :no_handphone)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':nama', $nama_pelanggan);
                    $stmt->bindParam(':email', $email_pelanggan);
                    $stmt->bindParam(':no_handphone', $no_handphone);
                    $stmt->execute();
                    $pelanggan_id = $db->lastInsertId();
                }
            }
            
            // Generate kode pesanan
            $kode_pesanan = 'POS' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            
            // Hitung total
            $total_harga = 0;
            foreach ($cart as $item) {
                $total_harga += $item['harga'] * $item['jumlah'];
            }
            
            // Insert pesanan
            $query = "INSERT INTO pesanan (kode_pesanan, pelanggan_id, meja_id, kasir_id, total_harga, metode_pembayaran, catatan, status)
                      VALUES (:kode_pesanan, :pelanggan_id, :meja_id, :kasir_id, :total_harga, :metode_pembayaran, :catatan, 'selesai')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':kode_pesanan', $kode_pesanan);
            $stmt->bindParam(':pelanggan_id', $pelanggan_id);
            $stmt->bindParam(':meja_id', $meja_id);
            $stmt->bindParam(':kasir_id', $user['id']);
            $stmt->bindParam(':total_harga', $total_harga);
            $stmt->bindParam(':metode_pembayaran', $metode_pembayaran);
            $stmt->bindParam(':catatan', $catatan);
            $stmt->execute();
            $pesanan_id = $db->lastInsertId();
            
            // Insert detail pesanan
            foreach ($cart as $item) {
                $subtotal = $item['harga'] * $item['jumlah'];
                $query = "INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, harga_satuan, subtotal, catatan)
                          VALUES (:pesanan_id, :menu_id, :jumlah, :harga_satuan, :subtotal, :catatan)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':pesanan_id', $pesanan_id);
                $stmt->bindParam(':menu_id', $item['id']);
                $stmt->bindParam(':jumlah', $item['jumlah']);
                $stmt->bindParam(':harga_satuan', $item['harga']);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':catatan', $item['catatan'] ?? '');
                $stmt->execute();
            }
            
            // Update status meja jika dipilih
            if ($meja_id) {
                $query = "UPDATE meja SET status = 'terisi' WHERE id = :meja_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':meja_id', $meja_id);
                $stmt->execute();
            }
            
            // Insert transaksi keuangan
            $query = "INSERT INTO transaksi (pesanan_id, jenis, jumlah, keterangan, user_id)
                      VALUES (:pesanan_id, 'pemasukan', :jumlah, :keterangan, :user_id)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':pesanan_id', $pesanan_id);
            $stmt->bindParam(':jumlah', $total_harga);
            $keterangan = "Pembayaran pesanan $kode_pesanan via $metode_pembayaran";
            $stmt->bindParam(':keterangan', $keterangan);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->execute();
            
            $db->commit();
            
            // Redirect ke menu orderan setelah berhasil
            header("Location: ../orders/index.php?success=Pesanan berhasil dibuat dengan kode: $kode_pesanan&new_order=$pesanan_id");
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
    <title>POS Digital - Ramen App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .pos-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .menu-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .menu-card.selected {
            border: 3px solid #007bff;
            background: #e3f2fd;
        }
        .cart-sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.3s;
        }
        .cart-sidebar.show {
            transform: translateX(0);
        }
        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        .cart-overlay.show {
            display: block;
        }
        .floating-cart {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 998;
        }
        .category-tabs {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        .category-tabs .nav-link {
            border: none;
            border-radius: 10px;
            margin: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .category-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .menu-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
        }
        .menu-placeholder {
            width: 100%;
            height: 120px;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 15px;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        @media (max-width: 768px) {
            .cart-sidebar {
                width: 100%;
            }
            .floating-cart {
                bottom: 10px;
                right: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="pos-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>🍜 POS Digital - Ramen Gen Kiro</h2>
                    <small><?php echo ucfirst($user['role']); ?>: <?php echo $user['nama']; ?></small>
                    </div> 
                <div class="col-md-6 text-end">
                    <a href="../dashboard.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="../orders/index.php" class="btn btn-outline-light">
                        <i class="fas fa-clipboard-list"></i> Orderan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Category Tabs -->
        <div class="category-tabs">
            <ul class="nav nav-pills justify-content-center" id="categoryTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-category="all">
                        <i class="fas fa-th-large me-2"></i>Semua Menu
                    </button>
                </li>
                <?php foreach ($menu_by_category as $kategori => $menu_items): ?>
                <li class="nav-item">
                    <button class="nav-link" data-category="<?php echo strtolower(str_replace(' ', '-', $kategori)); ?>">
                        <i class="fas fa-utensils me-2"></i><?php echo $kategori; ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
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
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fas fa-shopping-cart me-2"></i>Keranjang</h4>
                <button class="btn btn-outline-secondary btn-sm" id="close-cart">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="cart-items">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                    <p>Keranjang masih kosong</p>
                </div>
            </div>
            
            <div id="cart-total" class="total-section" style="display: none;">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span id="subtotal-amount">Rp 0</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Pajak (10%):</span>
                    <span id="tax-amount">Rp 0</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total:</strong>
                    <strong id="total-amount">Rp 0</strong>
                </div>
            </div>
            
            <div id="checkout-section" style="display: none;">
                <form id="order-form" method="POST">
                    <h6 class="mb-3">Informasi Pesanan</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Pelanggan</label>
                        <input type="text" class="form-control" name="nama_pelanggan" placeholder="Opsional">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email_pelanggan" placeholder="Opsional">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">No. Handphone</label>
                        <input type="text" class="form-control" name="no_handphone" placeholder="Opsional">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meja</label>
                        <select class="form-select" name="meja_id">
                            <option value="">Pilih Meja (Opsional)</option>
                            <?php foreach ($meja_list as $meja): ?>
                            <option value="<?php echo $meja['id']; ?>" <?php echo ($meja['status'] != 'kosong') ? 'disabled' : ''; ?>>
                                Meja <?php echo $meja['nomor_meja']; ?> 
                                (<?php echo $meja['kapasitas']; ?> orang)
                                <?php echo ($meja['status'] != 'kosong') ? ' - ' . ucfirst($meja['status']) : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Metode Pembayaran</label>
                        <select class="form-select" name="metode_pembayaran" required>
                            <option value="tunai">Tunai</option>
                            <option value="kartu">Kartu Debit/Kredit</option>
                            <option value="digital">Digital (QRIS/E-Wallet)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="2" placeholder="Catatan khusus..."></textarea>
                    </div>
                    
                    <input type="hidden" name="cart_data" id="cart_data">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_order" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Proses Pesanan
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Kosongkan Keranjang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let cart = [];

        // Category filter functionality
        document.querySelectorAll('[data-category]').forEach(tab => {
            tab.addEventListener('click', function() {
                const category = this.dataset.category;
                
                // Update active tab
                document.querySelectorAll('[data-category]').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Filter menu items
                document.querySelectorAll('.menu-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Toggle cart item functionality (add or reduce)
        function toggleCartItem(menu) {
            const existingItem = cart.find(item => item.id === menu.menu_id);
            if (existingItem) {
                // If item exists, reduce quantity or remove
                if (existingItem.jumlah > 1) {
                    existingItem.jumlah--;
                } else {
                    const index = cart.findIndex(item => item.id === menu.menu_id);
                    cart.splice(index, 1);
                }
            } else {
                // If item doesn't exist, add to cart
                cart.push({
                    id: menu.menu_id,
                    nama: menu.nama_menu,
                    harga: parseInt(menu.harga),
                    jumlah: 1,
                    catatan: ''
                });
            }
            
            updateCart();
            updateMenuDisplay();
        }

        // Add to cart functionality (for + button)
        function addToCart(menu) {
            const existingItem = cart.find(item => item.id === menu.menu_id);
            if (existingItem) {
                existingItem.jumlah++;
            } else {
                cart.push({
                    id: menu.menu_id,
                    nama: menu.nama_menu,
                    harga: parseInt(menu.harga),
                    jumlah: 1,
                    catatan: ''
                });
            }
            
            updateCart();
            updateMenuDisplay();
        }

        // Increase quantity from menu card
        function increaseQuantity(menuId) {
            const existingItem = cart.find(item => item.id === menuId);
            if (existingItem) {
                existingItem.jumlah++;
            }
            updateCart();
            updateMenuDisplay();
        }

        // Decrease quantity from menu card
        function decreaseQuantity(menuId) {
            const existingItem = cart.find(item => item.id === menuId);
            if (existingItem) {
                if (existingItem.jumlah > 1) {
                    existingItem.jumlah--;
                } else {
                    const index = cart.findIndex(item => item.id === menuId);
                    cart.splice(index, 1);
                }
            }
            updateCart();
            updateMenuDisplay();
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
            const cartData = document.getElementById('cart_data');
            
            const totalItems = cart.reduce((sum, item) => sum + item.jumlah, 0);
            const subtotal = cart.reduce((sum, item) => sum + (item.harga * item.jumlah), 0);
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            cartCount.textContent = totalItems;
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Keranjang masih kosong</p>
                <small>Klik menu untuk menambah ke keranjang</small>
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
                            <small class="text-muted">Rp ${item.harga.toLocaleString('id-ID')}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline-secondary quantity-btn" onclick="decreaseCartQuantity(${index})">-</button>
                            <span class="mx-3 fw-bold">${item.jumlah}</span>
                            <button class="btn btn-sm btn-outline-primary quantity-btn" onclick="increaseCartQuantity(${index})">+</button>
                        </div>
                        <div class="fw-bold">
                            Rp ${(item.harga * item.jumlah).toLocaleString('id-ID')}
                        </div>
                    </div>
                    <div class="mt-2">
                        <input type="text" class="form-control form-control-sm"
                                placeholder="Catatan khusus..."
                                value="${item.catatan}"
                               onchange="updateItemNote(${index}, this.value)">
                    </div>
                </div>
            `;
                });
                
                cartItems.innerHTML = itemsHtml;
                subtotalAmount.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
                taxAmount.textContent = 'Rp ' + tax.toLocaleString('id-ID');
                totalAmount.textContent = 'Rp ' + total.toLocaleString('id-ID');
                cartTotal.style.display = 'block';
                checkoutSection.style.display = 'block';
                cartData.value = JSON.stringify(cart);
            }
            
            saveCart();
        }

        // Update menu display with quantities
        function updateMenuDisplay() {
            document.querySelectorAll('.menu-card').forEach(card => {
                const menuId = card.onclick.toString().match(/menu_id":"(\d+)"/);
                if (menuId) {
                    const id = menuId[1];
                    const cartItem = cart.find(item => item.id === id);
                    const quantityControls = card.querySelector('.quantity-controls');
                    const quantityDisplay = card.querySelector('.quantity-display');
                    
                    if (cartItem && cartItem.jumlah > 0) {
                        card.classList.add('selected');
                        quantityControls.style.display = 'flex';
                        quantityDisplay.textContent = cartItem.jumlah;
                        
                        // Add click instruction
                        const cardBody = card.querySelector('.card-body');
                        let instruction = cardBody.querySelector('.click-instruction');
                        if (!instruction) {
                            instruction = document.createElement('small');
                            instruction.className = 'click-instruction text-muted d-block mt-1';
                            instruction.innerHTML = '<i class="fas fa-info-circle"></i> Klik untuk kurangi';
                            cardBody.appendChild(instruction);
                        }
                    } else {
                        card.classList.remove('selected');
                        quantityControls.style.display = 'none';
                        quantityDisplay.textContent = '0';
                        
                        // Remove click instruction
                        const instruction = card.querySelector('.click-instruction');
                        if (instruction) {
                            instruction.remove();
                        }
                    }
                }
            });
        }

        // Quantity control functions
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

        function updateItemNote(index, note) {
            cart[index].catatan = note;
            updateCart();
        }

        function clearCart() {
            if (confirm('Yakin ingin mengosongkan keranjang?')) {
                cart = [];
                updateCart();
                updateMenuDisplay();
            }
        }

        // Cart toggle functionality
        document.getElementById('cart-toggle').addEventListener('click', function() {
            document.getElementById('cart-sidebar').classList.add('show');
            document.getElementById('cart-overlay').classList.add('show');
        });

        document.getElementById('close-cart').addEventListener('click', function() {
            document.getElementById('cart-sidebar').classList.remove('show');
            document.getElementById('cart-overlay').classList.remove('show');
        });

        document.getElementById('cart-overlay').addEventListener('click', function() {
            document.getElementById('cart-sidebar').classList.remove('show');
            document.getElementById('cart-overlay').classList.remove('show');
        });

        // Auto-save cart to localStorage
        function saveCart() {
            localStorage.setItem('pos_cart', JSON.stringify(cart));
        }

        function loadCart() {
            const savedCart = localStorage.getItem('pos_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                updateCart();
                updateMenuDisplay();
            }
        }

        // Load cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('cart-sidebar').classList.remove('show');
                document.getElementById('cart-overlay').classList.remove('show');
            }
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('order-form').submit();
            }
        });

        // Form submission handler
        document.getElementById('order-form').addEventListener('submit', function(e) {
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            // Clear cart from localStorage immediately
            localStorage.removeItem('pos_cart');
            cart = [];
            
            // Don't prevent default - let the form submit normally
            // The PHP redirect will handle taking us to the orders page
        });
    </script>
</body>
</html>
