<?php
session_start();
require_once '../../config/koneksi.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'pemilik')) {
    header("Location: ../login.php");
    exit();
}

// --- Ambil Filter dari Form ---
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$status_filter = $_GET['status'] ?? '';

// --- Bangun Kondisi WHERE ---
$where_parts = [];
$params = [];

// Filter Rentang Tanggal
if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
    $where_parts[] = "DATE(t.tanggal_masuk) BETWEEN ? AND ?";
    $params[] = $tanggal_awal;
    $params[] = $tanggal_akhir;
} elseif (!empty($tanggal_awal)) {
    $where_parts[] = "DATE(t.tanggal_masuk) >= ?";
    $params[] = $tanggal_awal;
} elseif (!empty($tanggal_akhir)) {
    $where_parts[] = "DATE(t.tanggal_masuk) <= ?";
    $params[] = $tanggal_akhir;
}

// Filter Status
if (!empty($status_filter) && $status_filter !== 'all') {
    $where_parts[] = "t.status = ?";
    $params[] = $status_filter;
}

// Gabungkan kondisi WHERE
$where_clause = '';
if (!empty($where_parts)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
}

// --- Query untuk Data Tabel (Gabungan dari detail_transaksi) ---
$query_data = "
    SELECT t.id, t.status, t.diskon, t.tanggal_masuk, t.tanggal_selesai, t.tanggal_diambil, t.total AS total_transaksi_header,
           u.username, pl.nama AS nama_pelanggan, pl.telepon, pl.alamat,
           dt.nama_paket_gabungan,
           dt.kuantitas_gabungan,
           dt.jenis_item_gabungan,
           dt.harga_per_kg_gabungan,
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
            GROUP_CONCAT(CONCAT(dt.kuantitas, ' ', dt.jenis_item) SEPARATOR ', ') AS kuantitas_gabungan,
            GROUP_CONCAT(dt.jenis_item SEPARATOR ', ') AS jenis_item_gabungan,
            GROUP_CONCAT(CONCAT('Rp ', FORMAT(dt.harga_per_kg, 0, 'de_DE')) SEPARATOR ', ') AS harga_per_kg_gabungan,
            SUM(CASE WHEN dt.jenis_item = 'kg' THEN dt.kuantitas ELSE 0 END) AS total_berat_dari_detail,
            SUM(CASE WHEN dt.jenis_item = 'satuan' THEN dt.kuantitas ELSE 0 END) AS total_satuan_dari_detail,
            SUM(dt.total_harga) AS total_pendapatan_dari_detail
        FROM detail_transaksi dt
        JOIN paket pk ON dt.paket_id = pk.id
        GROUP BY dt.transaksi_id
    ) dt ON t.id = dt.transaksi_id
    $where_clause
    ORDER BY t.tanggal_masuk DESC
";

$stmt_data = $pdo->prepare($query_data);
$stmt_data->execute($params);
$transaksis = $stmt_data->fetchAll();

// --- Query untuk Total Pendapatan (dari detail_transaksi) ---
$query_total = "
    SELECT SUM(dt.total_harga) as total_pendapatan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    $where_clause
";

$stmt_total = $pdo->prepare($query_total);
$stmt_total->execute($params);
$total_pendapatan = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_pendapatan'] ?? 0;

// --- Query untuk Jumlah Transaksi (hanya hitung transaksi unik yang sesuai filter) ---
$query_jumlah = "
    SELECT COUNT(DISTINCT t.id) as jumlah_transaksi
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    $where_clause
";

$stmt_jumlah = $pdo->prepare($query_jumlah);
$stmt_jumlah->execute($params);
$jumlah_transaksi = $stmt_jumlah->fetch(PDO::FETCH_ASSOC)['jumlah_transaksi'] ?? 0;

// --- Daftar Status untuk Dropdown ---
$daftar_status = ['all' => '--Pilih Status--'] + ['masuk', 'proses', 'selesai', 'diambil'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css  " rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css  ">
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
        <li class="nav-item"><a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
        <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a></li>
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
        <a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a>
        <a class="nav-link" href="laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <h4><i class="bi bi-bar-chart-line"></i> Laporan Transaksi</h4>

    <!-- Form Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                        <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= htmlspecialchars($tanggal_awal) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($daftar_status as $status_opt => $label): ?>
                                <option value="<?= $status_opt ?>" <?= $status_filter == $status_opt ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                            <a href="laporan.php" class="btn btn-secondary"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                            <a href="pdf.php?tanggal_awal=<?= urlencode($tanggal_awal) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>&status=<?= urlencode($status_filter) ?>" target="_blank" class="btn btn-success"><i class="bi bi-printer"></i> Cetak Laporan</a>
                            <a href="excel.php?tanggal_awal=<?= urlencode($tanggal_awal) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Ekspor Excel</a>                        
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="summary-box">
                <div class="summary-item">
                    <div class="summary-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                    <div class="summary-label">Total Pendapatan</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="summary-box">
                <div class="summary-item">
                    <div class="summary-value"><?= $jumlah_transaksi ?></div>
                    <div class="summary-label">Jumlah Transaksi</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Hasil -->
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
                            <th>Diskon </th>
                            <th>Total </th> 
                            <th>Status</th>
                            <th>Kasir</th>
                            <th>Masuk</th>
                            <th>Selesai</th>
                            <th>Diambil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksis)): ?>
                            <tr>
                                <td colspan="14" class="text-center">Tidak ada transaksi yang ditemukan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transaksis as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($t['telepon'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['alamat'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['nama_paket_gabungan'] ?? 'Tidak ada detail') ?></td>
                                <td><?= htmlspecialchars($t['kuantitas_gabungan'] ?? '0') ?></td>
                                <td><?= htmlspecialchars($t['harga_per_kg_gabungan'] ?? 'Rp 0') ?></td>
                                <td>Rp <?= number_format($t['diskon'], 0, ',', '.') ?></td>
                                <td><strong>Rp <?= number_format($t['total_pendapatan_dari_detail'] ?? $t['total_transaksi_header'], 0, ',', '.') ?></strong></td>
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
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js  "></script>
</body>
</html>