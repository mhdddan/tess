<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

requireLogin();
if (!hasRole('owner') && !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

switch ($action) {
    case 'daily_revenue':
        // Data pendapatan harian untuk grafik real-time
        $query = "SELECT 
            DATE(tanggal_pesanan) as tanggal,
            COUNT(*) as jumlah_transaksi,
            SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as pendapatan
            FROM pesanan 
            WHERE DATE(tanggal_pesanan) BETWEEN :date_from AND :date_to
            GROUP BY DATE(tanggal_pesanan)
            ORDER BY tanggal";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'hourly_revenue':
        // Data pendapatan per jam untuk hari ini
        $today = date('Y-m-d');
        $query = "SELECT 
            HOUR(tanggal_pesanan) as jam,
            COUNT(*) as jumlah_transaksi,
            SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as pendapatan
            FROM pesanan 
            WHERE DATE(tanggal_pesanan) = :today
            GROUP BY HOUR(tanggal_pesanan)
            ORDER BY jam";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'summary':
        // Summary data untuk dashboard
        $query = "SELECT 
            COUNT(*) as total_transaksi,
            SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_pendapatan,
            AVG(CASE WHEN status = 'selesai' THEN total_harga ELSE NULL END) as rata_rata,
            COUNT(CASE WHEN status = 'selesai' THEN 1 END) as transaksi_sukses,
            COUNT(CASE WHEN status = 'dibatalkan' THEN 1 END) as transaksi_batal
            FROM pesanan 
            WHERE DATE(tanggal_pesanan) BETWEEN :date_from AND :date_to";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;
        
    case 'top_menu':
        // Top menu berdasarkan pendapatan
        $query = "SELECT 
            m.nama_menu,
            k.nama_kategori,
            SUM(dp.jumlah) as total_terjual,
            SUM(dp.subtotal) as total_pendapatan
            FROM detail_pesanan dp
            JOIN menu m ON dp.menu_id = m.id
            JOIN kategori_menu k ON m.kategori_id = k.id
            JOIN pesanan p ON dp.pesanan_id = p.id
            WHERE DATE(p.tanggal_pesanan) BETWEEN :date_from AND :date_to
            AND p.status = 'selesai'
            GROUP BY dp.menu_id
            ORDER BY total_pendapatan DESC
            LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
