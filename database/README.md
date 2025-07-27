# Database Setup - Ramen App

## Cara Install Database

### Opsi 1: Menggunakan Web Installer (Recommended)
1. Buka browser dan akses: `http://localhost/ramen-app/database/install.php`
2. Isi konfigurasi database:
   - Host: localhost
   - Username: root (atau username MySQL Anda)
   - Password: (kosongkan jika tidak ada password)
   - Database Name: ramen_app
3. Klik "Install Database"
4. Tunggu proses selesai
5. Login dengan credentials yang disediakan

### Opsi 2: Manual Import via phpMyAdmin
1. Buka phpMyAdmin
2. Buat database baru dengan nama `ramen_app`
3. Import file SQL secara berurutan:
   - create_database.sql
   - create_tables.sql
   - insert_data.sql
4. Sesuaikan konfigurasi di `config/database.php`

### Opsi 3: Manual via Command Line
\`\`\`bash
# Login ke MySQL
mysql -u root -p

# Jalankan script SQL
source /path/to/create_database.sql
source /path/to/create_tables.sql
source /path/to/insert_data.sql
\`\`\`

## Test Koneksi
Setelah install, test koneksi dengan mengakses:
`http://localhost/ramen-app/database/check_connection.php`

## Login Credentials
- **Owner:** username: `owner`, password: `owner123`
- **Admin:** username: `admin`, password: `admin123`
- **Kasir:** username: `kasir1`, password: `kasir123`

## Struktur Database
- **users** - Data pengguna sistem
- **kategori_menu** - Kategori menu makanan
- **menu** - Data menu dan harga
- **meja** - Data meja restoran
- **pelanggan** - Data pelanggan
- **pesanan** - Data pesanan
- **detail_pesanan** - Detail item pesanan
- **transaksi** - Data transaksi keuangan

## Troubleshooting
1. **Error "Access denied"**: Periksa username/password MySQL
2. **Error "Database not found"**: Pastikan database sudah dibuat
3. **Error "Table doesn't exist"**: Jalankan ulang create_tables.sql
4. **Error "Foreign key constraint"**: Import tabel sesuai urutan

## Requirements
- PHP 7.4+
- MySQL 5.7+ atau MariaDB 10.2+
- PDO MySQL extension
- Web server (Apache/Nginx)
