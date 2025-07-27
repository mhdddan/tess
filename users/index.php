<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = getUserInfo();

// Filter parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'nama_lengkap';
$order = $_GET['order'] ?? 'ASC';
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Handle user actions
if ($_POST) {
    if (isset($_POST['add_user'])) {
        $nama_lengkap = $_POST['nama_lengkap'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status'] ?? 'aktif';
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            $query = "INSERT INTO users (nama_lengkap, username, email, password, role, status) VALUES (:nama_lengkap, :username, :email, :password, :role, :status)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama_lengkap', $nama_lengkap);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $success = "Pengguna berhasil ditambahkan!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_user'])) {
        $id = $_POST['user_id'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        try {
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET nama_lengkap = :nama_lengkap, username = :username, email = :email, password = :password, role = :role, status = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $password);
            } else {
                $query = "UPDATE users SET nama_lengkap = :nama_lengkap, username = :username, email = :email, role = :role, status = :status WHERE id = :id";
                $stmt = $db->prepare($query);
            }
            
            $stmt->bindParam(':nama_lengkap', $nama_lengkap);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Data pengguna berhasil diupdate!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        
        try {
            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Pengguna berhasil dihapus!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

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

$valid_sorts = ['nama_lengkap', 'username', 'email', 'role', 'status', 'created_at'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'nama_lengkap';
$order = ($order === 'DESC') ? 'DESC' : 'ASC';

// Get users with statistics
$query = "SELECT 
    u.*,
    CASE 
        WHEN u.role = 'owner' THEN 'Owner'
        WHEN u.role = 'admin' THEN 'Admin'
        WHEN u.role = 'kasir' THEN 'Kasir'
        ELSE 'Unknown'
    END as role_display
    FROM users u
    WHERE $where_clause
    ORDER BY $sort $order";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN status = 'aktif' THEN 1 END) as active_users,
    COUNT(CASE WHEN role = 'owner' THEN 1 END) as owners,
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
    COUNT(CASE WHEN role = 'kasir' THEN 1 END) as kasirs,
    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
    FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna - Ramen Gen Kiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .user-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin-right: 1rem;
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
        }
        .role-owner { background: linear-gradient(45deg, #ffd700, #ffed4e); color: #8b7355; }
        .role-admin { background: linear-gradient(45deg, #4e73df, #224abe); color: white; }
        .role-kasir { background: linear-gradient(45deg, #1cc88a, #13855c); color: white; }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
        }
        .status-aktif { background: #d4edda; color: #155724; }
        .status-nonaktif { background: #f8d7da; color: #721c24; }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: none;
        }
        @media (max-width: 768px) {
            .user-card {
                margin-bottom: 15px;
            }
            .stat-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <div class="mb-2">
                            <img src="../assets/logo.jpg" alt="Ramen Gen Kiro" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                        </div>
                        <h4>Ramen Gen Kiro</h4>
                        <small><?php echo ucfirst($user['role']); ?>: <?php echo $user['nama']; ?></small>
                    </div>
                    
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../menu/index.php">
                                <i class="fas fa-utensils me-2"></i> Kelola Menu
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('owner') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/financial.php">
                                <i class="fas fa-chart-line me-2"></i> Laporan Keuangan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/sales.php">
                                <i class="fas fa-chart-bar me-2"></i> Laporan Penjualan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('owner')): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-users me-2"></i> Data Pengguna
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('kasir') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../pos/index.php">
                                <i class="fas fa-cash-register me-2"></i> POS Digital
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../orders/index.php">
                                <i class="fas fa-clipboard-list me-2"></i> Menu Orderan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../customers/index.php">
                                <i class="fas fa-user-friends me-2"></i> Data Pelanggan
                            </a>
                        </li>
                        
                        <hr class="text-white">
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users text-primary me-2"></i>Data Pengguna
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if (hasRole('owner')): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus"></i> Tambah Pengguna
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="exportUsers()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Pengguna
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_users']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Pengguna Aktif
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['active_users']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Baru Minggu Ini
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['new_this_week']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #36b9cc, #258391);">
                                            <i class="fas fa-calendar-week"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Admin & Owner
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo ($stats['admins'] + $stats['owners']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Cari Pengguna</label>
                                <input type="text" class="form-control" name="search" value="<?php echo $search; ?>" placeholder="Nama, username, atau email...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role_filter">
                                    <option value="">Semua Role</option>
                                    <option value="owner" <?php echo ($role_filter == 'owner') ? 'selected' : ''; ?>>Owner</option>
                                    <option value="admin" <?php echo ($role_filter == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    <option value="kasir" <?php echo ($role_filter == 'kasir') ? 'selected' : ''; ?>>Kasir</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status_filter">
                                    <option value="">Semua Status</option>
                                    <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="nonaktif" <?php echo ($status_filter == 'nonaktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Urutkan</label>
                                <select class="form-select" name="sort">
                                    <option value="nama_lengkap" <?php echo ($sort == 'nama_lengkap') ? 'selected' : ''; ?>>Nama</option>
                                    <option value="username" <?php echo ($sort == 'username') ? 'selected' : ''; ?>>Username</option>
                                    <option value="role" <?php echo ($sort == 'role') ? 'selected' : ''; ?>>Role</option>
                                    <option value="created_at" <?php echo ($sort == 'created_at') ? 'selected' : ''; ?>>Tanggal Dibuat</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Urutan</label>
                                <select class="form-select" name="order">
                                    <option value="ASC" <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>A-Z / Lama-Baru</option>
                                    <option value="DESC" <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>Z-A / Baru-Lama</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users List -->
                <div class="row">
                    <?php if (empty($users)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada pengguna</h5>
                                <p class="text-muted">Tambahkan pengguna pertama Anda</p>
                                <?php if (hasRole('owner')): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-plus"></i> Tambah Pengguna
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($users as $user_data): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card user-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="user-avatar" style="background: linear-gradient(45deg, <?php echo '#' . substr(md5($user_data['nama_lengkap']), 0, 6); ?>, <?php echo '#' . substr(md5($user_data['nama_lengkap']), 6, 6); ?>);">
                                            <?php echo strtoupper(substr($user_data['nama_lengkap'], 0, 2)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo $user_data['nama_lengkap']; ?></h6>
                                            <small class="text-muted">@<?php echo $user_data['username']; ?></small>
                                        </div>
                                        <?php if (hasRole('owner')): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="editUser(<?php echo htmlspecialchars(json_encode($user_data)); ?>)">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user_data['id']; ?>, '<?php echo $user_data['nama_lengkap']; ?>')">
                                                        <i class="fas fa-trash me-2"></i>Hapus
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="role-badge role-<?php echo $user_data['role']; ?>">
                                            <?php echo ucfirst($user_data['role']); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo $user_data['status']; ?>">
                                            <?php echo $user_data['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <small><?php echo $user_data['email']; ?></small>
                                    </div>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="fw-bold text-primary"><?php echo ucfirst($user_data['role']); ?></div>
                                            <small class="text-muted">Role</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold text-success">
                                                <?php echo $user_data['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif'; ?>
                                            </div>
                                            <small class="text-muted">Status</small>
                                        </div>
                                    </div>
                                    
                                    <div class="text-muted">
                                        <i class="fas fa-calendar text-muted me-2"></i>
                                        <small>Bergabung: <?php echo date('d/m/Y', strtotime($user_data['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <?php if (hasRole('owner')): ?>
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="kasir">Kasir</option>
                                <option value="admin">Admin</option>
                                <option value="owner">Owner</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Tidak Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_user" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Pengguna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama_lengkap" id="edit_nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" class="form-control" name="password" id="edit_password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="kasir">Kasir</option>
                                <option value="admin">Admin</option>
                                <option value="owner">Owner</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Tidak Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Apakah Anda yakin ingin menghapus pengguna <strong id="delete_user_name"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_nama').value = user.nama_lengkap;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('edit_password').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
        
        function deleteUser(id, nama) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_user_name').textContent = nama;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            modal.show();
        }
        
        function exportUsers() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('export_users.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
