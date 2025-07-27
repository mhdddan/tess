<?php
// Helper functions untuk cetak transaksi

class PrintHelper {
    
    public static function generatePrintButtons($pesanan_id, $type = 'all') {
        $buttons = '';
        
        if ($type == 'all' || $type == 'receipt') {
            $buttons .= '<a href="../print/transaction.php?id=' . $pesanan_id . '" target="_blank" class="btn btn-primary btn-sm me-1">
                            <i class="fas fa-print"></i> Cetak Struk
                        </a>';
        }
        
        if ($type == 'all' || $type == 'thermal') {
            $buttons .= '<a href="../print/receipt_thermal.php?id=' . $pesanan_id . '" target="_blank" class="btn btn-info btn-sm me-1">
                            <i class="fas fa-receipt"></i> Struk Thermal
                        </a>';
        }
        
        if ($type == 'all' || $type == 'kitchen') {
            $buttons .= '<a href="../print/kitchen_order.php?id=' . $pesanan_id . '" target="_blank" class="btn btn-warning btn-sm me-1">
                            <i class="fas fa-utensils"></i> Order Dapur
                        </a>';
        }
        
        return $buttons;
    }
    
    public static function autoPrint($pesanan_id, $type = 'receipt') {
        $url = '';
        switch ($type) {
            case 'thermal':
                $url = '../print/receipt_thermal.php?id=' . $pesanan_id . '&print=1';
                break;
            case 'kitchen':
                $url = '../print/kitchen_order.php?id=' . $pesanan_id . '&print=1';
                break;
            default:
                $url = '../print/transaction.php?id=' . $pesanan_id . '&print=1';
                break;
        }
        
        return "<script>window.open('$url', '_blank');</script>";
    }
    
    public static function formatRupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    
    public static function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        return date($format, strtotime($datetime));
    }
    
    public static function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'diproses' => '<span class="badge bg-info">Diproses</span>',
            'selesai' => '<span class="badge bg-success">Selesai</span>',
            'dibatalkan' => '<span class="badge bg-danger">Dibatalkan</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Function untuk menambahkan tombol print ke halaman lain
function addPrintButtons($pesanan_id, $type = 'all') {
    return PrintHelper::generatePrintButtons($pesanan_id, $type);
}

// Function untuk auto print setelah transaksi
function autoPrintReceipt($pesanan_id, $type = 'receipt') {
    return PrintHelper::autoPrint($pesanan_id, $type);
}
?>
