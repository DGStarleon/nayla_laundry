<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM paket WHERE id = ?");
$stmt->execute([$id]);
$paket = $stmt->fetch();

if (!$paket) {
    $_SESSION['error'] = "Paket tidak ditemukan.";
    header("Location: data_laundry.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_paket = trim($_POST['nama_paket']);
    $harga_per_kg = (float)$_POST['harga_per_kg'];
    $jenis = $_POST['jenis']; // Ambil jenis dari form

    if (empty($nama_paket)) {
        $error = "Nama paket wajib diisi!";
    } elseif ($harga_per_kg <= 0) {
        $error = "Harga per kg harus lebih besar dari 0.";
    } elseif (empty($jenis) || !in_array($jenis, ['kg', 'satuan'])) {
        $error = "Jenis paket harus dipilih (kg atau satuan).";
    } else {
        $stmt = $pdo->prepare("UPDATE paket SET nama_paket = ?, harga_per_kg = ?, jenis = ? WHERE id = ?");
        if ($stmt->execute([$nama_paket, $harga_per_kg, $jenis, $id])) {
            $_SESSION['success'] = "Paket berhasil diperbarui!";
            header("Location: data_laundry.php");
            exit();
        } else {
            $error = "Gagal memperbarui paket.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Paket Laundry - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 56px;
        }
@media (min-width: 768px) {
            body {
                padding-top: 0;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 220px;
                background-color: #343a40;
                padding-top: 20px;
                z-index: 1000;
            }
            .main-content {
                margin-left: 220px;
                padding: 20px;
            }
            /* Sembunyikan ☰ di desktop */
            .mobile-toggle {
                display: none !important;
            }
        }
        /* Mobile: sidebar disembunyikan */
        @media (max-width: 767.98px) {
            .sidebar {
                display: none;
            }
            .main-content {
                padding: 20px;
            }
        }
        .sidebar .logo {
            text-align: center;
            color: white;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 1.2rem;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 15px;
            margin: 4px 10px;
            border-radius: 0 15px 15px 0;
            font-size: 0.95rem;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #596d8bff;
        }
        .topbar {
            background-color: #3a6099ff;
        }
        .topbar .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .offcanvas .nav-link {
            padding: 12px 20px;
            display: block;
            color: #343a40;
            text-decoration: none;
            border-radius: 4px;
        }
        .offcanvas .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        .offcanvas .nav-link:hover {
            background-color: #e9ecef;
        }
        .summary-box {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-item {
            text-align: center;
        }
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .summary-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
                        .sidebar .logo {
            text-align: center;
            color: white;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 1.3rem; /* Diperbesar */
            padding: 15px 0; /* Tambahkan padding atas-bawah */
            display: flex; /* Jadikan flex container */
            flex-direction: column; /* Arah item: kolom (logo di atas, teks di bawah) */
            align-items: center; /* Rata tengah secara horizontal */
            justify-content: center; /* Rata tengah secara vertikal (jika tinggi tetap) */
        }
        .sidebar .logo img {
            max-height: 80px; /* Diperbesar */
            width: auto;
            object-fit: contain;
            margin-bottom: 8px; /* Jarak antara logo dan teks */
        }
    </style>
</head>
<body>

<nav class="navbar topbar d-md-none">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <span class="navbar-brand">Nayla Laundry</span>
        <button class="btn btn-light mobile-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
            ☰
        </button>
    </div>
</nav>

<div class="sidebar d-none d-md-block">
    <div class="logo">
        <img src="../../img/nayla_laundry.png" alt="Nayla Laundry Logo">
        <span>Nayla Laundry</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="../user/data.php"><i class="bi bi-people"></i> Kelola User</a></li>
        <li class="nav-item"><a class="nav-link" href="data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a></li>
        <li class="nav-item"><a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
        <li class="nav-item"><a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a></li>
        <hr style="border-color: #495057; margin: 15px 10px;">
        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    </ul>
</div>

<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <a class="nav-link" href="../user/data.php"><i class="bi bi-people"></i> Kelola User</a>
        <a class="nav-link" href="data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a>
        <a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a>
        <a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <h4><i class="bi bi-pencil-square"></i> Edit Paket Laundry: <?= htmlspecialchars($paket['nama_paket']) ?></h4>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="nama_paket" class="form-label">Nama Paket</label>
                    <input type="text" class="form-control" id="nama_paket" name="nama_paket" value="<?= htmlspecialchars($paket['nama_paket']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="harga_per_kg" class="form-label">Harga Per Kg/Satuan (Rp)</label>
                    <input type="number" class="form-control" id="harga_per_kg" name="harga_per_kg" value="<?= htmlspecialchars($paket['harga_per_kg']) ?>" min="0" step="100" required>
                </div>
                <div class="mb-3">
                    <label for="jenis" class="form-label">Jenis Paket</label>
                    <select class="form-select" id="jenis" name="jenis" required>
                        <option value="">-- Pilih Jenis --</option>
                        <option value="kg" <?= $paket['jenis'] === 'kg' ? 'selected' : '' ?>>Per Kg</option>
                        <option value="satuan" <?= $paket['jenis'] === 'satuan' ? 'selected' : '' ?>>Per Satuan</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Perubahan
                </button>
                <a href="data_laundry.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Batal
                </a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>