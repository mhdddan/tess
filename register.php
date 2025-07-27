<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'kasir';
    
    // Validasi input
    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($email)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Cek username sudah ada
        $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Username atau email sudah terdaftar!';
        } else {
            // Insert user baru
            $insert_query = "INSERT INTO users (username, password, nama_lengkap, email, role, status) 
                           VALUES (:username, MD5(:password), :nama_lengkap, :email, :role, 'aktif')";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':username', $username);
            $insert_stmt->bindParam(':password', $password);
            $insert_stmt->bindParam(':nama_lengkap', $nama_lengkap);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':role', $role);
            
            if ($insert_stmt->execute()) {
                $success = 'Akun berhasil dibuat! Silakan login dengan akun baru Anda.';
            } else {
                $error = 'Gagal membuat akun. Silakan coba lagi.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun - Ramen Gen Kiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem 0;
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .brand-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-outline-primary {
            border-radius: 10px;
            border: 2px solid #667eea;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .login-link {
            text-align: center;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card">
                    <div class="register-header">
                        <img src="assets/logo.jpg" alt="Ramen Gen Kiro" class="brand-logo">
                        <h2>Ramen Gen Kiro</h2>
                        <p class="mb-0">Buat Akun Staff Baru</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-sign-in-alt me-1"></i>Login Sekarang
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user me-2"></i>Username *
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                        <div class="form-text">Minimal 3 karakter, hanya huruf dan angka</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email *
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nama_lengkap" class="form-label">
                                    <i class="fas fa-id-card me-2"></i>Nama Lengkap *
                                </label>
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                                       value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div id="passwordStrength" class="password-strength"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Konfirmasi Password *
                                        </label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div id="passwordMatch" class="form-text"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">
                                    <i class="fas fa-user-tag me-2"></i>Role/Jabatan
                                </label>
                                <select class="form-select" id="role" name="role">
                                    <option value="kasir" <?php echo ($_POST['role'] ?? '') === 'kasir' ? 'selected' : ''; ?>>
                                        Kasir
                                    </option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                        Admin
                                    </option>
                                </select>
                                <div class="form-text">Role Owner hanya bisa dibuat oleh Owner yang sudah ada</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agreement" required>
                                <label class="form-check-label" for="agreement">
                                    Saya setuju dengan syarat dan ketentuan yang berlaku di Ramen Gen Kiro
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>
                                <i class="fas fa-user-plus me-2"></i>Buat Akun
                            </button>
                        </form>
                        
                        <?php endif; ?>
                        
                        
                    </div>
                    
                    <div class="login-link">
                        <p class="mb-0">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sudah punya akun? 
                            <a href="login.php">Login Disini</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            const submitBtn = document.getElementById('submitBtn');
            const agreement = document.getElementById('agreement');
            
            let strength = 0;
            let message = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = '<span class="strength-weak">Lemah - Minimal 6 karakter</span>';
                    break;
                case 2:
                case 3:
                    message = '<span class="strength-medium">Sedang - Tambahkan huruf besar/angka</span>';
                    break;
                case 4:
                case 5:
                    message = '<span class="strength-strong">Kuat - Password aman</span>';
                    break;
            }
            
            strengthDiv.innerHTML = message;
            updateSubmitButton();
        });
        
        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
            } else if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="text-success">✓ Password cocok</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-danger">✗ Password tidak cocok</span>';
            }
            
            updateSubmitButton();
        });
        
        // Agreement checkbox
        document.getElementById('agreement').addEventListener('change', updateSubmitButton);
        
        function updateSubmitButton() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const agreement = document.getElementById('agreement').checked;
            const submitBtn = document.getElementById('submitBtn');
            
            const isValid = password.length >= 6 && 
                           password === confirmPassword && 
                           agreement;
            
            submitBtn.disabled = !isValid;
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const namaLengkap = document.getElementById('nama_lengkap').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (username.length < 3) {
                alert('Username minimal 3 karakter!');
                e.preventDefault();
                return;
            }
            
            if (password.length < 6) {
                alert('Password minimal 6 karakter!');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Konfirmasi password tidak cocok!');
                e.preventDefault();
                return;
            }
            
            if (!email.includes('@')) {
                alert('Format email tidak valid!');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
