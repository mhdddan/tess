<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Build query with filters
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.nama_lengkap LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "u.role = :role_filter";
    $params[':role_filter'] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "u.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT 
    u.nama_lengkap,
    u.username,
    u.email,
    u.role,
    u.status,
    u.created_at
    FROM users u
    WHERE $where_clause
    ORDER BY u.nama_lengkap ASC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="data_pengguna_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

echo '<table border="1">';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Nama Lengkap</th>';
echo '<th>Username</th>';
echo '<th>Email</th>';
echo '<th>Role</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Bergabung</th>';
echo '</tr>';

$no = 1;
foreach ($users as $user) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . $user['nama_lengkap'] . '</td>';
    echo '<td>' . $user['username'] . '</td>';
    echo '<td>' . $user['email'] . '</td>';
    echo '<td>' . ucfirst($user['role']) . '</td>';
    echo '<td>' . ($user['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif') . '</td>';
    echo '<td>' . date('d/m/Y', strtotime($user['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</table>';
?>
