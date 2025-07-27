-- Database untuk Aplikasi Ramen
CREATE DATABASE ramen_app;
USE ramen_app;

-- Tabel Users (Owner, Admin, Kasir)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('owner', 'admin', 'kasir') NOT NULL,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Kategori Menu
CREATE TABLE kategori_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Menu
CREATE TABLE menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kategori_id INT,
    nama_menu VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(10,2) NOT NULL,
    gambar VARCHAR(255),
    status ENUM('tersedia', 'habis', 'nonaktif') DEFAULT 'tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori_menu(id)
);

-- Tabel Meja
CREATE TABLE meja (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nomor_meja VARCHAR(10) UNIQUE NOT NULL,
    kapasitas INT NOT NULL,
    status ENUM('kosong', 'terisi', 'reserved') DEFAULT 'kosong',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Pelanggan
CREATE TABLE pelanggan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    no_handphone VARCHAR(20),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Pesanan
CREATE TABLE pesanan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_pesanan VARCHAR(20) UNIQUE NOT NULL,
    pelanggan_id INT,
    meja_id INT,
    kasir_id INT,
    total_harga DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'pending',
    metode_pembayaran ENUM('tunai', 'kartu', 'digital') DEFAULT 'tunai',
    tanggal_pesanan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id),
    FOREIGN KEY (meja_id) REFERENCES meja(id),
    FOREIGN KEY (kasir_id) REFERENCES users(id)
);

-- Tabel Detail Pesanan
CREATE TABLE detail_pesanan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pesanan_id INT,
    menu_id INT,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    catatan TEXT,
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menu(id)
);

-- Tabel Transaksi Keuangan
CREATE TABLE transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pesanan_id INT,
    jenis ENUM('pemasukan', 'pengeluaran') NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    keterangan TEXT,
    tanggal_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert data awal
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('owner', MD5('owner123'), 'Pemilik Ramen', 'owner@ramen.com', 'owner'),
('admin', MD5('admin123'), 'Administrator', 'admin@ramen.com', 'admin'),
('kasir1', MD5('kasir123'), 'Kasir Satu', 'kasir1@ramen.com', 'kasir');

INSERT INTO kategori_menu (nama_kategori, deskripsi) VALUES
('Ramen', 'Berbagai jenis ramen tradisional dan modern'),
('Minuman', 'Minuman segar dan hangat'),
('Appetizer', 'Makanan pembuka'),
('Dessert', 'Makanan penutup');

INSERT INTO menu (kategori_id, nama_menu, deskripsi, harga, status) VALUES
(1, 'Ramen Shoyu', 'Ramen dengan kuah shoyu yang gurih', 35000, 'tersedia'),
(1, 'Ramen Miso', 'Ramen dengan kuah miso yang kaya rasa', 38000, 'tersedia'),
(1, 'Ramen Tonkotsu', 'Ramen dengan kuah tulang babi yang creamy', 42000, 'tersedia'),
(2, 'Teh Hijau', 'Teh hijau hangat tradisional', 8000, 'tersedia'),
(2, 'Ramune', 'Minuman soda Jepang', 12000, 'tersedia'),
(3, 'Gyoza', 'Pangsit goreng isi daging', 18000, 'tersedia'),
(3, 'Edamame', 'Kacang edamame rebus', 15000, 'tersedia'),
(4, 'Mochi Ice Cream', 'Es krim mochi berbagai rasa', 20000, 'tersedia');

INSERT INTO meja (nomor_meja, kapasitas) VALUES
('01', 2), ('02', 2), ('03', 4), ('04', 4), ('05', 6), ('06', 6), ('07', 8), ('08', 2);
