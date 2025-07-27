<?php
// File installer untuk database
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Database - Ramen App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .install-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .install-header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .step {
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .step.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .step.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="install-card">
                    <div class="install-header">
                        <h2>🍜 Ramen App Installer</h2>
                        <p class="mb-0">Setup Database Aplikasi</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!isset($_POST['install'])): ?>
                        <!-- Form Konfigurasi Database -->
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Host Database</label>
                                <input type="text" class="form-control" name="db_host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username Database</label>
                                <input type="text" class="form-control" name="db_user" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password Database</label>
                                <input type="password" class="form-control" name="db_pass" placeholder="Kosongkan jika tidak ada password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Database</label>
                                <input type="text" class="form-control" name="db_name" value="ramen_app" required>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6>⚠️ Perhatian:</h6>
                                <ul class="mb-0">
                                    <li>Pastikan MySQL/MariaDB sudah berjalan</li>
                                    <li>User database memiliki privilege CREATE DATABASE</li>
                                    <li>Proses ini akan membuat database baru</li>
                                    <li>Jika database sudah ada, data akan di-reset</li>
                                </ul>
                            </div>
                            
                            <button type="submit" name="install" class="btn btn-primary w-100">
                                🚀 Install Database
                            </button>
                        </form>
                        
                        <?php else: ?>
                        <!-- Proses Instalasi -->
                        <?php
                        $db_host = $_POST['db_host'];
                        $db_user = $_POST['db_user'];
                        $db_pass = $_POST['db_pass'];
                        $db_name = $_POST['db_name'];
                        
                        $steps = [];
                        $success = true;
                        
                        try {
                            // Step 1: Koneksi ke MySQL
                            $steps[] = ['title' => 'Menghubungkan ke MySQL...', 'status' => 'process'];
                            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $steps[count($steps)-1]['status'] = 'success';
                            $steps[count($steps)-1]['message'] = 'Koneksi berhasil';
                            
                            // Step 2: Buat Database
                            $steps[] = ['title' => 'Membuat database...', 'status' => 'process'];
                            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $pdo->exec("USE `$db_name`");
                            $steps[count($steps)-1]['status'] = 'success';
                            $steps[count($steps)-1]['message'] = "Database '$db_name' berhasil dibuat";
                            
                            // Step 3: Buat Tabel Users
                            $steps[] = ['title' => 'Membuat tabel users...', 'status' => 'process'];
                            $pdo->exec("DROP TABLE IF EXISTS transaksi, detail_pesanan, pesanan, pelanggan, meja, menu, kategori_menu, users");
                            $pdo->exec("
                                CREATE TABLE users (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    username VARCHAR(50) NOT NULL UNIQUE,
                                    password VARCHAR(255) NOT NULL,
                                    nama_lengkap VARCHAR(100) NOT NULL,
                                    email VARCHAR(100) NOT NULL UNIQUE,
                                    role ENUM('owner', 'admin', 'kasir') NOT NULL,
                                    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 4: Buat Tabel Kategori Menu
                            $steps[] = ['title' => 'Membuat tabel kategori_menu...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE kategori_menu (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    nama_kategori VARCHAR(50) NOT NULL,
                                    deskripsi TEXT,
                                    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 5: Buat Tabel Menu
                            $steps[] = ['title' => 'Membuat tabel menu...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE menu (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    kategori_id INT,
                                    nama_menu VARCHAR(100) NOT NULL,
                                    deskripsi TEXT,
                                    harga DECIMAL(10,2) NOT NULL,
                                    gambar VARCHAR(255),
                                    status ENUM('tersedia', 'habis', 'nonaktif') DEFAULT 'tersedia',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    INDEX idx_kategori (kategori_id),
                                    INDEX idx_status (status),
                                    FOREIGN KEY (kategori_id) REFERENCES kategori_menu(id) ON DELETE SET NULL
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 6: Buat Tabel Meja
                            $steps[] = ['title' => 'Membuat tabel meja...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE meja (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    nomor_meja VARCHAR(10) NOT NULL UNIQUE,
                                    kapasitas INT NOT NULL,
                                    status ENUM('kosong', 'terisi', 'reserved') DEFAULT 'kosong',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 7: Buat Tabel Pelanggan
                            $steps[] = ['title' => 'Membuat tabel pelanggan...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE pelanggan (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    nama VARCHAR(100) NOT NULL,
                                    email VARCHAR(100),
                                    no_handphone VARCHAR(20),
                                    alamat TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    INDEX idx_nama (nama),
                                    INDEX idx_email (email)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 8: Buat Tabel Pesanan
                            $steps[] = ['title' => 'Membuat tabel pesanan...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE pesanan (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    kode_pesanan VARCHAR(20) NOT NULL UNIQUE,
                                    pelanggan_id INT,
                                    meja_id INT,
                                    kasir_id INT,
                                    total_harga DECIMAL(10,2) NOT NULL,
                                    status ENUM('pending', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'pending',
                                    metode_pembayaran ENUM('tunai', 'kartu', 'digital') DEFAULT 'tunai',
                                    tanggal_pesanan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    catatan TEXT,
                                    INDEX idx_kode (kode_pesanan),
                                    INDEX idx_tanggal (tanggal_pesanan),
                                    INDEX idx_status (status),
                                    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE SET NULL,
                                    FOREIGN KEY (meja_id) REFERENCES meja(id) ON DELETE SET NULL,
                                    FOREIGN KEY (kasir_id) REFERENCES users(id) ON DELETE SET NULL
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 9: Buat Tabel Detail Pesanan
                            $steps[] = ['title' => 'Membuat tabel detail_pesanan...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE detail_pesanan (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    pesanan_id INT NOT NULL,
                                    menu_id INT,
                                    jumlah INT NOT NULL,
                                    harga_satuan DECIMAL(10,2) NOT NULL,
                                    subtotal DECIMAL(10,2) NOT NULL,
                                    catatan TEXT,
                                    INDEX idx_pesanan (pesanan_id),
                                    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
                                    FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE SET NULL
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 10: Buat Tabel Transaksi
                            $steps[] = ['title' => 'Membuat tabel transaksi...', 'status' => 'process'];
                            $pdo->exec("
                                CREATE TABLE transaksi (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    pesanan_id INT,
                                    jenis ENUM('pemasukan', 'pengeluaran') NOT NULL,
                                    jumlah DECIMAL(10,2) NOT NULL,
                                    keterangan TEXT,
                                    tanggal_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    user_id INT,
                                    INDEX idx_tanggal (tanggal_transaksi),
                                    INDEX idx_jenis (jenis),
                                    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE SET NULL,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 11: Insert Data Users
                            $steps[] = ['title' => 'Menambahkan data users...', 'status' => 'process'];
                            $pdo->exec("
                                INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
                                ('owner', MD5('owner123'), 'Pemilik Ramen House', 'owner@ramenhouse.com', 'owner'),
                                ('admin', MD5('admin123'), 'Administrator Sistem', 'admin@ramenhouse.com', 'admin'),
                                ('kasir1', MD5('kasir123'), 'Kasir Pertama', 'kasir1@ramenhouse.com', 'kasir'),
                                ('kasir2', MD5('kasir123'), 'Kasir Kedua', 'kasir2@ramenhouse.com', 'kasir')
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 12: Insert Data Kategori
                            $steps[] = ['title' => 'Menambahkan data kategori menu...', 'status' => 'process'];
                            $pdo->exec("
                                INSERT INTO kategori_menu (nama_kategori, deskripsi) VALUES
                                ('Ramen', 'Berbagai jenis ramen tradisional dan modern'),
                                ('Minuman', 'Minuman segar dan hangat'),
                                ('Appetizer', 'Makanan pembuka'),
                                ('Dessert', 'Makanan penutup'),
                                ('Rice Bowl', 'Nasi dengan berbagai topping'),
                                ('Side Dish', 'Lauk pendamping')
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 13: Insert Data Menu
                            $steps[] = ['title' => 'Menambahkan data menu...', 'status' => 'process'];
                            $pdo->exec("
                                INSERT INTO menu (kategori_id, nama_menu, deskripsi, harga, status) VALUES
                                (1, 'Ramen Shoyu', 'Ramen dengan kuah shoyu yang gurih', 35000, 'tersedia'),
                                (1, 'Ramen Miso', 'Ramen dengan kuah miso yang kaya rasa', 38000, 'tersedia'),
                                (1, 'Ramen Tonkotsu', 'Ramen dengan kuah tulang babi yang creamy', 42000, 'tersedia'),
                                (1, 'Ramen Shio', 'Ramen dengan kuah garam yang clear', 36000, 'tersedia'),
                                (2, 'Teh Hijau', 'Teh hijau hangat tradisional', 8000, 'tersedia'),
                                (2, 'Ramune', 'Minuman soda Jepang', 12000, 'tersedia'),
                                (3, 'Gyoza', 'Pangsit goreng isi daging (5 pcs)', 18000, 'tersedia'),
                                (3, 'Edamame', 'Kacang edamame rebus', 15000, 'tersedia'),
                                (4, 'Mochi Ice Cream', 'Es krim mochi berbagai rasa', 20000, 'tersedia'),
                                (5, 'Chicken Teriyaki Bowl', 'Nasi dengan ayam teriyaki', 28000, 'tersedia')
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 14: Insert Data Meja
                            $steps[] = ['title' => 'Menambahkan data meja...', 'status' => 'process'];
                            $pdo->exec("
                                INSERT INTO meja (nomor_meja, kapasitas) VALUES
                                ('01', 2), ('02', 2), ('03', 4), ('04', 4), 
                                ('05', 6), ('06', 6), ('07', 8), ('08', 2)
                            ");
                            $steps[count($steps)-1]['status'] = 'success';
                            
                            // Step 15: Update Config Database
                            $steps[] = ['title' => 'Membuat file konfigurasi...', 'status' => 'process'];
                            $config_content = "<?php
class Database {
    private \$host = \"$db_host\";
    private \$db_name = \"$db_name\";
    private \$username = \"$db_user\";
    private \$password = \"$db_pass\";
    public \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO(\"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name, \$this->username, \$this->password);
            \$this->conn->exec(\"set names utf8mb4\");
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }
        return \$this->conn;
    }
}
?>";
                            file_put_contents('../config/database.php', $config_content);
                            $steps[count($steps)-1]['status'] = 'success';
                            
                        } catch (Exception $e) {
                            $success = false;
                            $steps[count($steps)-1]['status'] = 'error';
                            $steps[count($steps)-1]['message'] = $e->getMessage();
                        }
                        ?>
                        
                        <h5>📋 Proses Instalasi</h5>
                        <?php foreach ($steps as $step): ?>
                        <div class="step <?php echo $step['status']; ?>">
                            <strong><?php echo $step['title']; ?></strong>
                            <?php if (isset($step['message'])): ?>
                            <br><small><?php echo $step['message']; ?></small>
                            <?php endif; ?>
                            <?php if ($step['status'] == 'success'): ?>
                            <i class="fas fa-check-circle text-success float-end"></i>
                            <?php elseif ($step['status'] == 'error'): ?>
                            <i class="fas fa-times-circle text-danger float-end"></i>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success mt-4">
                            <h5>✅ Instalasi Berhasil!</h5>
                            <p>Database telah berhasil dibuat dan diisi dengan data sample.</p>
                            <hr>
                            <h6>Login Credentials:</h6>
                            <ul>
                                <li><strong>Owner:</strong> username: <code>owner</code>, password: <code>owner123</code></li>
                                <li><strong>Admin:</strong> username: <code>admin</code>, password: <code>admin123</code></li>
                                <li><strong>Kasir:</strong> username: <code>kasir1</code>, password: <code>kasir123</code></li>
                            </ul>
                        </div>
                        
                        <div class="text-center">
                            <a href="../login.php" class="btn btn-success btn-lg">
                                🚀 Mulai Menggunakan Aplikasi
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger mt-4">
                            <h5>❌ Instalasi Gagal!</h5>
                            <p>Terjadi kesalahan saat instalasi. Silakan periksa konfigurasi database Anda.</p>
                        </div>
                        
                        <div class="text-center">
                            <a href="install.php" class="btn btn-primary">
                                🔄 Coba Lagi
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
