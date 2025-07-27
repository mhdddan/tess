-- Gunakan database ramen_app
USE ramen_app;

-- Hapus tabel jika sudah ada (untuk reset)
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS detail_pesanan;
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS pelanggan;
DROP TABLE IF EXISTS meja;
DROP TABLE IF EXISTS menu;
DROP TABLE IF EXISTS kategori_menu;
DROP TABLE IF EXISTS users;

-- Tabel Users (Owner, Admin, Kasir)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Kategori Menu
CREATE TABLE kategori_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Menu
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Meja
CREATE TABLE meja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_meja VARCHAR(10) NOT NULL UNIQUE,
    kapasitas INT NOT NULL,
    status ENUM('kosong', 'terisi', 'reserved') DEFAULT 'kosong',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Pelanggan
CREATE TABLE pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    no_handphone VARCHAR(20),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nama (nama),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Pesanan
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Detail Pesanan
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Transaksi Keuangan
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
