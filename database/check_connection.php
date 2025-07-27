<?php
// File untuk test koneksi database
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Koneksi Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>🔍 Test Koneksi Database</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            require_once '../config/database.php';
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            if ($db) {
                                echo '<div class="alert alert-success">';
                                echo '<h6>✅ Koneksi Database Berhasil!</h6>';
                                
                                // Test query untuk cek tabel
                                $tables = ['users', 'kategori_menu', 'menu', 'meja', 'pelanggan', 'pesanan', 'detail_pesanan', 'transaksi'];
                                echo '<h6>📋 Status Tabel:</h6><ul>';
                                
                                foreach ($tables as $table) {
                                    try {
                                        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo "<li><strong>$table:</strong> {$result['count']} records ✅</li>";
                                    } catch (Exception $e) {
                                        echo "<li><strong>$table:</strong> Error - {$e->getMessage()} ❌</li>";
                                    }
                                }
                                echo '</ul>';
                                
                                // Test login credentials
                                echo '<h6>👤 Test Login Credentials:</h6>';
                                $stmt = $db->query("SELECT username, role FROM users WHERE status = 'aktif'");
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                echo '<ul>';
                                foreach ($users as $user) {
                                    echo "<li><strong>{$user['username']}</strong> ({$user['role']})</li>";
                                }
                                echo '</ul>';
                                
                                echo '</div>';
                                
                                echo '<div class="text-center">';
                                echo '<a href="../login.php" class="btn btn-primary me-2">🚀 Login ke Aplikasi</a>';
                                echo '<a href="../customer/order.php" class="btn btn-success">🛒 Order Sebagai Customer</a>';
                                echo '</div>';
                                
                            } else {
                                throw new Exception('Koneksi database gagal');
                            }
                            
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">';
                            echo '<h6>❌ Koneksi Database Gagal!</h6>';
                            echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
                            echo '<p>Silakan jalankan installer terlebih dahulu.</p>';
                            echo '</div>';
                            
                            echo '<div class="text-center">';
                            echo '<a href="install.php" class="btn btn-warning">🔧 Install Database</a>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
