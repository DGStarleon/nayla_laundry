<?php
session_start();
require_once '../../config/koneksi.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Query diubah untuk menyesuaikan struktur tabel transaksi dan detail_transaksi
// Sekarang menggabungkan informasi detail untuk paket, kuantitas, jenis, dan harga
$stmt = $pdo->query("
    SELECT t.*, u.username, pl.nama AS nama_pelanggan, pl.telepon, pl.alamat,
           dt.nama_paket_gabungan,
           dt.kuantitas_gabungan,
           dt.jenis_item_gabungan,
           dt.harga_per_kg_gabungan, -- Kolom baru untuk harga
           dt.total_berat_dari_detail,
           dt.total_satuan_dari_detail,
           dt.total_pendapatan_dari_detail
    FROM transaksi t
    JOIN users u ON t.user_id = u.id
    JOIN pelanggan pl ON t.pelanggan_id = pl.id
    LEFT JOIN (
        -- Subquery untuk mengambil dan menggabungkan informasi dari detail_transaksi
        SELECT
            dt.transaksi_id,
            GROUP_CONCAT(pk.nama_paket SEPARATOR ', ') AS nama_paket_gabungan,
            GROUP_CONCAT(CONCAT(dt.kuantitas, ' ', dt.jenis_item) SEPARATOR ', ') AS kuantitas_gabungan, -- Gabungkan kuantitas dan jenis
            GROUP_CONCAT(dt.jenis_item SEPARATOR ', ') AS jenis_item_gabungan,
            GROUP_CONCAT(CONCAT('Rp ', FORMAT(dt.harga_per_kg, 0, 'de_DE')) SEPARATOR ', ') AS harga_per_kg_gabungan, -- Gabungkan harga
            SUM(CASE WHEN dt.jenis_item = 'kg' THEN dt.kuantitas ELSE 0 END) AS total_berat_dari_detail,
            SUM(CASE WHEN dt.jenis_item = 'satuan' THEN dt.kuantitas ELSE 0 END) AS total_satuan_dari_detail,
            SUM(dt.total_harga) AS total_pendapatan_dari_detail
        FROM detail_transaksi dt
        JOIN paket pk ON dt.paket_id = pk.id
        GROUP BY dt.transaksi_id
    ) dt ON t.id = dt.transaksi_id
    ORDER BY t.tanggal_masuk DESC
");
$transaksis = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Transaksi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css    " rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css    ">
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
        <li class="nav-item"><a class="nav-link" href="../paket/data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a></li>
        <li class="nav-item"><a class="nav-link" href="data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
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
        <a class="nav-link" href="../paket/data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a>
        <a class="nav-link" href="data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a>
        <a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <h4><i class="bi bi-receipt-fill"></i> Daftar Transaksi</h4>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="pos.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Buat Transaksi Baru
        </a>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th>Item</th> 
                            <th>Kuantitas</th> 
                            <th>Harga Kg/Satuan</th> 
                            <th>Diskon</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Kasir</th>
                            <th>Masuk</th>
                            <th>Selesai</th>
                            <th>Diambil</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksis)): ?>
                            <tr>
                                <td colspan="15" class="text-center">Tidak ada transaksi.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transaksis as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($t['telepon'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['alamat'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['nama_paket_gabungan'] ?? 'Tidak ada detail') ?></td>
                                <td><?= htmlspecialchars($t['kuantitas_gabungan'] ?? '0') ?></td> <!-- Tampilkan kuantitas dan jenis -->
                                <td><?= htmlspecialchars($t['harga_per_kg_gabungan'] ?? 'Rp 0') ?></td> <!-- Tampilkan harga gabungan -->
                                <td>Rp <?= number_format($t['diskon'], 0, ',', '.') ?></td>
                                <td><strong>Rp <?= number_format($t['total_pendapatan_dari_detail'] ?? $t['total'], 0, ',', '.') ?></strong></td>
                                <td>
                                    <?php
                                    $status = $t['status'];
                                    $badge_class = match($status) {
                                        'masuk' => 'bg-primary',
                                        'proses' => 'bg-warning text-dark',
                                        'selesai' => 'bg-success',
                                        'diambil' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= ucfirst($status) ?></span>
                                </td>
                                <td><?= htmlspecialchars($t['username']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($t['tanggal_masuk'])) ?></td>
                                <td>
                                    <?php if ($t['tanggal_selesai']): ?>
                                        <?= date('d/m/Y H:i', strtotime($t['tanggal_selesai'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['tanggal_diambil']): ?>
                                        <?= date('d/m/Y H:i', strtotime($t['tanggal_diambil'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="struk.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-info" title="Cetak Struk">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="update_status.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning" title="Update Status">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="hapus.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" title="Hapus Transaksi" onclick="return confirm('Anda yakin ingin menghapus transaksi ini? Data yang dihapus tidak dapat dikembalikan.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js    "></script>
</body>
</html>