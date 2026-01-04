<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect sesuai role
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: pemilik/dashboard.php");
    }
    exit();
}

require_once 'config/koneksi.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Cari user berdasarkan username
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verifikasi password
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Redirect sesuai role
        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: pemilik/dashboard.php");
        }
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Nayla Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bs-body-bg: #f0f2f5; /* Warna latar belakang utama */
            --card-bg: white; /* Warna latar belakang kartu */
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Shadow untuk kartu */
            --primary-gradient: linear-gradient(135deg, #91a6faff 0%, #086e8dff 100%); /* Gradient utama */
        }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif; /* Font modern */
            background: var(--primary-gradient); /* Gunakan gradient sebagai background */
            background-attachment: fixed; /* Background tetap saat di-scroll */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px; /* Tambahkan padding untuk layar kecil */
        }
        .login-container {
            width: 100%;
            max-width: 450px; /* Batasi lebar maksimum */
        }
        .card {
            border: none;
            border-radius: 16px; /* Sudut lebih lembut */
            box-shadow: var(--card-shadow);
            overflow: hidden; /* Pastikan elemen dalam tidak keluar */
            background: var(--card-bg);
            transition: transform 0.3s ease, box-shadow 0.3s ease; /* Efek hover */
        }
        .card:hover {
            transform: translateY(-5px); /* Efek angkat sedikit saat hover */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: var(--primary-gradient); /* Header kartu juga pakai gradient */
            color: white;
            border: none;
            padding: 25px 20px;
            text-align: center;
            font-weight: 600;
            font-size: 1.4rem;
        }
        .card-body {
            padding: 30px 25px;
        }
        .form-control {
            border-radius: 8px; /* Sudut input lebih lembut */
            padding: 10px 15px;
            border: 1px solid #e1e5e9;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control:focus {
            border-color: #667eea; /* Warna border saat focus */
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25); /* Shadow focus */
            outline: 0;
        }
        .btn-primary {
            background: var(--primary-gradient); /* Tombol pakai gradient */
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            transition: opacity 0.3s;
        }
        .btn-primary:hover {
            opacity: 0.9; /* Efek hover untuk tombol */
        }
        .alert {
            border-radius: 8px; /* Sudut alert lebih lembut */
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 100%; 
            max-height: 120px; 
            width: 120px;  
            height: 120px; 
            object-fit: cover; 
            border-radius: 50%; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); 
            margin-bottom: 10px; 
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h4>Nayla Laundry</h4>
            </div>
            <div class="card-body">
                <div class="logo">
                    <img src="img/nayla_laundry.png" alt="logo">
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label"><i class="bi bi-person me-1"></i>Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username Anda" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password Anda" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Masuk</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>