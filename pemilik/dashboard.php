<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: login.php");
    exit();
}

// --- Ambil Jumlah Total Transaksi ---
$stmt_total_transaksi = $pdo->query("SELECT COUNT(*) as jumlah FROM transaksi");
$total_transaksi = $stmt_total_transaksi->fetch(PDO::FETCH_ASSOC)['jumlah'];

// --- Ambil Jumlah Transaksi Berdasarkan Status ---
$stmt_status = $pdo->query("
    SELECT status, COUNT(*) as jumlah
    FROM transaksi
    GROUP BY status
");
$transaksi_per_status = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

// --- Ambil Jumlah User ---
$stmt_users = $pdo->query("SELECT COUNT(*) as jumlah FROM users");
$total_users = $stmt_users->fetch(PDO::FETCH_ASSOC)['jumlah'];

// --- Ambil Pendapatan Total (dari detail_transaksi) ---
$stmt_pendapatan = $pdo->query("
    SELECT SUM(dt.total_harga) as total_pendapatan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
");
$total_pendapatan = $stmt_pendapatan->fetch(PDO::FETCH_ASSOC)['total_pendapatan'] ?? 0;

// --- Ambil Data Grafik (Transaksi Harian 7 Hari Terakhir) ---
$stmt_grafik = $pdo->query("
    SELECT DATE(tanggal_masuk) as tanggal, COUNT(*) as jumlah
    FROM transaksi
    WHERE tanggal_masuk >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal_masuk)
    ORDER BY tanggal ASC
");
$grafik_data = $stmt_grafik->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil Transaksi Terbanyak (Peringkat Paket berdasarkan jumlah baris detail) ---
$stmt_peringkat = $pdo->query("
    SELECT p.nama_paket, COUNT(dt.id) as jumlah_transaksi -- COUNT(dt.id) untuk menghitung jumlah baris detail
    FROM detail_transaksi dt
    JOIN paket p ON dt.paket_id = p.id
    GROUP BY dt.paket_id
    ORDER BY jumlah_transaksi DESC
    LIMIT 3
");
$peringkat_paket = $stmt_peringkat->fetchAll(PDO::FETCH_ASSOC);

// --- Siapkan Data untuk Chart.js ---
$labels = [];
$datasets = [];
foreach ($grafik_data as $row) {
    $labels[] = $row['tanggal'];
    $datasets[] = $row['jumlah'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Pemilik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css    " rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css    ">
    <script src="https://cdn.jsdelivr.net/npm/chart.js    "></script>
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
            .mobile-toggle {
                display: none !important;
            }
        }
        /* Desktop: sidebar tetap */
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
        .summary-card {
            height: 100%;
            border: none;
            border-radius: 12px; /* Sudut lebih lembut */
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* Shadow halus */
            transition: all 0.3s ease; /* Transisi untuk efek hover */
            overflow: hidden; /* Pastikan elemen dalam tidak keluar */
            background: white; /* Latar belakang putih */
            position: relative; /* Untuk positioning elemen dalam */
        }
        .summary-card::before {
            /* Garis dekoratif atas */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0d6efd, #0dcaf0); /* Gradient biru */
        }
        .summary-card:hover {
            transform: translateY(-5px); /* Efek angkat saat hover */
            box-shadow: 0 8px 20px rgba(0,0,0,0.12); /* Shadow lebih dalam saat hover */
        }
        .summary-card .card-body {
            padding: 20px;
        }
        .summary-card h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 0;
            color: #212529;
        }
        .summary-card .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #6c757d;
            display: flex;
            align-items: center;
        }
        .summary-card .card-title i {
            margin-right: 8px;
            font-size: 1.2rem;
        }
        .summary-card .card-text {
            font-size: 0.8rem;
            color: #adb5bd;
            margin: 0;
        }
        /* Warna ikon judul sesuai dengan konteks */
        .summary-card.total-transaksi .card-title i { color: #0d6efd; }
        .summary-card.masuk .card-title i { color: #0dcaf0; }
        .summary-card.proses .card-title i { color: #ffc107; }
        .summary-card.selesai .card-title i { color: #198754; }
        .summary-card.diambil .card-title i { color: #6c757d; }
        .summary-card.users .card-title i { color: #6f42c1; }
        .summary-card.pendapatan .card-title i { color: #20c997; } /* Warna untuk pendapatan */


        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            background: white;
        }
        .card-header {
            background: white; /* Latar header tetap putih */
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #212529;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .peringkat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .peringkat-item:last-child {
            border-bottom: none;
        }
        .peringkat-rank {
            font-weight: bold;
            color: #6c757d;
            min-width: 30px;
        }
        .peringkat-nama {
            flex: 1;
            font-weight: 500;
        }
        .peringkat-jumlah {
            background-color: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
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
        <img src="../img/nayla_laundry.png" alt="Nayla Laundry Logo">
        <span>Nayla Laundry</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a></li>
        <hr style="border-color: #495057; margin: 15px 10px;">
        <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    </ul>
</div>

<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <a class="nav-link" href="laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <h4><i class="bi bi-house-door"></i> Dashboard Pemilik</h4>

    <!-- Summary Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card total-transaksi">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-receipt"></i> Total Transaksi</h5>
                    <h2 class="display-6"><?= $total_transaksi ?></h2>
                    <p class="card-text">Jumlah keseluruhan</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card masuk">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-arrow-down-circle"></i> Masuk</h5>
                    <h2 class="display-6"><?= $transaksi_per_status['masuk'] ?? 0 ?></h2>
                    <p class="card-text">Baru</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card proses">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-gear"></i> Proses</h5>
                    <h2 class="display-6"><?= $transaksi_per_status['proses'] ?? 0 ?></h2>
                    <p class="card-text">Dikerjakan</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card selesai">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-check-circle"></i> Selesai</h5>
                    <h2 class="display-6"><?= $transaksi_per_status['selesai'] ?? 0 ?></h2>
                    <p class="card-text">Siap diambil</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card diambil">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-box-arrow-right"></i> Diambil</h5>
                    <h2 class="display-6"><?= $transaksi_per_status['diambil'] ?? 0 ?></h2>
                    <p class="card-text">Sudah diambil</p>
                </div>
            </div>
        </div>

        <!-- Kartu Pendapatan -->
        <div class="col-md-6 col-xl-2 mb-3">
            <div class="card summary-card pendapatan">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-currency-dollar"></i> Total Pendapatan</h5>
                    <h2 class="display-6">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h2>
                    <p class="card-text">Pendapatan keseluruhan</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik & Peringkat -->
    <div class="row">
        <!-- Grafik -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Grafik Transaksi Harian (7 Hari Terakhir)</h5>
                </div>
                <div class="card-body">
                    <canvas id="myChart" height="70"></canvas>
                </div>
            </div>
        </div>
        <!-- Peringkat -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Peringkat Paket Terlaris</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($peringkat_paket)): ?>
                        <p class="text-muted">Belum ada data transaksi.</p>
                    <?php else: ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($peringkat_paket as $p): ?>
                            <div class="peringkat-item">
                                <span class="peringkat-rank">#<?= $rank ?></span>
                                <span class="peringkat-nama"><?= htmlspecialchars($p['nama_paket']) ?></span>
                                <span class="peringkat-jumlah"><?= $p['jumlah_transaksi'] ?></span>
                            </div>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js    "></script>
<script>
    // Ambil data dari PHP dan konversi ke JavaScript
    const labels = <?= json_encode($labels) ?>;
    const data = <?= json_encode($datasets) ?>;

    // Konfigurasi Chart.js
    const config = {
        type: 'line', // atau 'bar'
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Transaksi',
                 data,
                borderColor: 'rgb(13, 110, 253)', // Warna garis biru
                backgroundColor: 'rgba(13, 110, 253, 0.1)', // Area di bawah garis
                tension: 0.3, // Garis sedikit melengkung
                fill: true, // Isi area
                pointRadius: 4,
                pointBackgroundColor: 'rgb(13, 110, 253)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Biarkan tinggi ditentukan oleh container
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 10,
                    displayColors: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { size: 11 } },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            }
        }
    };

    // Render Chart
    const ctx = document.getElementById('myChart').getContext('2d');
    const myChart = new Chart(ctx, config);
</script>
</body>
</html>