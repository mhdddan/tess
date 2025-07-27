-- Gunakan database ramen_app
USE ramen_app;

-- Insert data users
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('owner', MD5('owner123'), 'Pemilik Ramen House', 'owner@ramenhouse.com', 'owner'),
('admin', MD5('admin123'), 'Administrator Sistem', 'admin@ramenhouse.com', 'admin'),
('kasir1', MD5('kasir123'), 'Kasir Pertama', 'kasir1@ramenhouse.com', 'kasir'),
('kasir2', MD5('kasir123'), 'Kasir Kedua', 'kasir2@ramenhouse.com', 'kasir');

-- Insert data kategori menu
INSERT INTO kategori_menu (nama_kategori, deskripsi) VALUES
('Ramen', 'Berbagai jenis ramen tradisional dan modern dengan kuah yang kaya rasa'),
('Minuman', 'Minuman segar dan hangat untuk menemani hidangan Anda'),
('Appetizer', 'Makanan pembuka yang menggugah selera'),
('Dessert', 'Makanan penutup manis untuk mengakhiri santapan'),
('Rice Bowl', 'Nasi dengan berbagai topping lezat'),
('Side Dish', 'Lauk pendamping yang sempurna');

-- Insert data menu
INSERT INTO menu (kategori_id, nama_menu, deskripsi, harga, status) VALUES
-- Ramen
(1, 'Ramen Shoyu', 'Ramen dengan kuah shoyu yang gurih dan mie yang kenyal', 35000, 'tersedia'),
(1, 'Ramen Miso', 'Ramen dengan kuah miso yang kaya rasa dan creamy', 38000, 'tersedia'),
(1, 'Ramen Tonkotsu', 'Ramen dengan kuah tulang babi yang creamy dan rich', 42000, 'tersedia'),
(1, 'Ramen Shio', 'Ramen dengan kuah garam yang clear dan light', 36000, 'tersedia'),
(1, 'Ramen Spicy Miso', 'Ramen miso dengan level kepedasan yang menantang', 40000, 'tersedia'),
(1, 'Ramen Vegetarian', 'Ramen dengan kuah sayuran dan topping vegetarian', 33000, 'tersedia'),

-- Minuman
(2, 'Teh Hijau Panas', 'Teh hijau hangat tradisional Jepang', 8000, 'tersedia'),
(2, 'Teh Hijau Dingin', 'Teh hijau dingin yang menyegarkan', 10000, 'tersedia'),
(2, 'Ramune Original', 'Minuman soda Jepang dengan rasa original', 12000, 'tersedia'),
(2, 'Ramune Melon', 'Minuman soda Jepang dengan rasa melon', 12000, 'tersedia'),
(2, 'Sake Panas', 'Sake tradisional disajikan hangat', 25000, 'tersedia'),
(2, 'Sake Dingin', 'Sake premium disajikan dingin', 28000, 'tersedia'),
(2, 'Jus Jeruk', 'Jus jeruk segar tanpa pengawet', 15000, 'tersedia'),
(2, 'Air Mineral', 'Air mineral dalam kemasan', 5000, 'tersedia'),

-- Appetizer
(3, 'Gyoza Ayam', 'Pangsit goreng isi daging ayam cincang (5 pcs)', 18000, 'tersedia'),
(3, 'Gyoza Babi', 'Pangsit goreng isi daging babi cincang (5 pcs)', 20000, 'tersedia'),
(3, 'Edamame', 'Kacang edamame rebus dengan garam laut', 15000, 'tersedia'),
(3, 'Chicken Karaage', 'Ayam goreng tepung ala Jepang yang crispy', 22000, 'tersedia'),
(3, 'Takoyaki', 'Bola-bola gurita dengan saus takoyaki (6 pcs)', 25000, 'tersedia'),
(3, 'Agedashi Tofu', 'Tahu goreng dengan kuah dashi', 16000, 'tersedia'),

-- Dessert
(4, 'Mochi Ice Cream Vanilla', 'Es krim mochi dengan rasa vanilla', 20000, 'tersedia'),
(4, 'Mochi Ice Cream Strawberry', 'Es krim mochi dengan rasa strawberry', 20000, 'tersedia'),
(4, 'Mochi Ice Cream Green Tea', 'Es krim mochi dengan rasa green tea', 22000, 'tersedia'),
(4, 'Dorayaki', 'Pancake Jepang dengan isi kacang merah', 18000, 'tersedia'),
(4, 'Taiyaki', 'Kue ikan dengan isi kacang merah', 15000, 'tersedia'),

-- Rice Bowl
(5, 'Chicken Teriyaki Bowl', 'Nasi dengan ayam teriyaki dan sayuran', 28000, 'tersedia'),
(5, 'Beef Teriyaki Bowl', 'Nasi dengan daging sapi teriyaki', 32000, 'tersedia'),
(5, 'Katsu Curry Bowl', 'Nasi dengan chicken katsu dan curry', 30000, 'tersedia'),
(5, 'Salmon Bowl', 'Nasi dengan salmon panggang dan sayuran', 35000, 'tersedia'),

-- Side Dish
(6, 'Nori Seaweed', 'Rumput laut kering untuk tambahan ramen', 5000, 'tersedia'),
(6, 'Extra Chashu', 'Tambahan daging chashu untuk ramen', 12000, 'tersedia'),
(6, 'Extra Egg', 'Tambahan telur ajitsuke untuk ramen', 8000, 'tersedia'),
(6, 'Extra Noodle', 'Tambahan mie untuk ramen', 10000, 'tersedia');

-- Insert data meja
INSERT INTO meja (nomor_meja, kapasitas) VALUES
('01', 2), ('02', 2), ('03', 4), ('04', 4), 
('05', 6), ('06', 6), ('07', 8), ('08', 2),
('09', 4), ('10', 4), ('11', 6), ('12', 8);

-- Insert data pelanggan sample
INSERT INTO pelanggan (nama, email, no_handphone, alamat) VALUES
('John Doe', 'john@email.com', '081234567890', 'Jl. Sudirman No. 123, Jakarta'),
('Jane Smith', 'jane@email.com', '081234567891', 'Jl. Thamrin No. 456, Jakarta'),
('Ahmad Wijaya', 'ahmad@email.com', '081234567892', 'Jl. Gatot Subroto No. 789, Jakarta'),
('Siti Nurhaliza', 'siti@email.com', '081234567893', 'Jl. Kuningan No. 321, Jakarta'),
('Budi Santoso', 'budi@email.com', '081234567894', 'Jl. Senayan No. 654, Jakarta');

-- Insert data pesanan sample
INSERT INTO pesanan (kode_pesanan, pelanggan_id, meja_id, kasir_id, total_harga, status, metode_pembayaran, tanggal_pesanan, catatan) VALUES
('ORD20241201001', 1, 1, 3, 53000, 'selesai', 'tunai', '2024-12-01 12:30:00', 'Tidak pedas'),
('ORD20241201002', 2, 3, 3, 76000, 'selesai', 'kartu', '2024-12-01 13:15:00', 'Extra pedas'),
('ORD20241201003', 3, 5, 3, 45000, 'diproses', 'digital', '2024-12-01 14:00:00', NULL),
('ORD20241201004', 4, 2, 3, 62000, 'pending', 'tunai', '2024-12-01 14:30:00', 'Tanpa bawang'),
('ORD20241201005', 5, 7, 3, 89000, 'selesai', 'kartu', '2024-12-01 15:00:00', NULL);

-- Insert detail pesanan sample
INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, harga_satuan, subtotal, catatan) VALUES
-- Pesanan 1
(1, 1, 1, 35000, 35000, NULL),
(1, 3, 1, 18000, 18000, NULL),

-- Pesanan 2
(2, 3, 1, 42000, 42000, 'Extra pedas'),
(2, 9, 1, 12000, 12000, NULL),
(2, 11, 1, 22000, 22000, NULL),

-- Pesanan 3
(3, 2, 1, 38000, 38000, NULL),
(3, 8, 1, 5000, 5000, NULL),
(3, 17, 1, 2000, 2000, NULL),

-- Pesanan 4
(4, 5, 1, 40000, 40000, 'Level 2'),
(4, 13, 1, 22000, 22000, NULL),

-- Pesanan 5
(5, 4, 2, 36000, 72000, NULL),
(5, 10, 1, 12000, 12000, NULL),
(5, 15, 1, 5000, 5000, NULL);

-- Insert transaksi keuangan sample
INSERT INTO transaksi (pesanan_id, jenis, jumlah, keterangan, tanggal_transaksi, user_id) VALUES
(1, 'pemasukan', 53000, 'Pembayaran pesanan ORD20241201001', '2024-12-01 12:45:00', 3),
(2, 'pemasukan', 76000, 'Pembayaran pesanan ORD20241201002', '2024-12-01 13:30:00', 3),
(5, 'pemasukan', 89000, 'Pembayaran pesanan ORD20241201005', '2024-12-01 15:15:00', 3);
