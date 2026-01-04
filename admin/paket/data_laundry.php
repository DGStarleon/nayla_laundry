<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$stmt = $pdo->query("SELECT * FROM paket ORDER BY nama_paket ASC");
$pakets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Paket Laundry - Admin</title>
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
        /* Gaya untuk kolom aksi */
        .aksi-col {
            width: 120px; /* Sesuaikan lebar kolom aksi jika perlu */
        }
        /* Gaya untuk pratinjau cetak */
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            .sidebar, .topbar, .mobile-toggle, .offcanvas, .main-content .d-flex, .btn {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                padding: 10px; /* Tambahkan sedikit padding untuk cetak */
            }
            .card {
                border: none;
                box-shadow: none;
            }
            .table th, .table td {
                page-break-inside: avoid; /* Cegah baris dipotong di halaman berbeda */
            }
            /* Sembunyikan kolom aksi saat cetak */
            .aksi-col {
                display: none !important;
            }
            /* Opsional: Sembunyikan header kolom aksi juga */
            .table th.aksi-col {
                display: none !important;
            }
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
    <h4><i class="bi bi-tag-fill"></i> Daftar Paket Laundry</h4>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="col-md-4">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama paket...">
        </div>
        <div class="d-flex gap-2"> <!-- Container untuk tombol -->
            <a href="tambah.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Tambah Paket Baru
            </a>
            <button id="printBtn" class="btn btn-info">
                <i class="bi bi-printer"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nama Paket</th>
                            <th>Harga Kg/Satuan</th>
                            <th>Jenis</th>
                            <th class="aksi-col">Aksi</th> <!-- Tambahkan kelas ke header -->
                        </tr>
                    </thead>
                    <tbody id="paketTableBody"> <!-- Tambahkan ID ke tbody -->
                        <?php if (empty($pakets)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Tidak ada paket.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pakets as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nama_paket']) ?></td>
                                <td>Rp <?= number_format($p['harga_per_kg'], 0, ',', '.') ?></td>
                                <td>
                                    <?php if ($p['jenis'] === 'kg'): ?>
                                        <span class="badge bg-primary">Per Kg</span>
                                    <?php elseif ($p['jenis'] === 'satuan'): ?>
                                        <span class="badge bg-success">Per Satuan</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tidak Diketahui</span>
                                    <?php endif; ?>
                                </td>
                                <td class="aksi-col"> <!-- Tambahkan kelas ke sel data -->
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="hapus.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus paket <?= htmlspecialchars($p['nama_paket']) ?>?')">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js  "></script>
<script>
    // Fungsi pencarian
    function searchTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const tableBody = document.getElementById('paketTableBody');
        const rows = tableBody.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;

            // Cek kolom nama paket (indeks 0)
            if (cells.length > 0) {
                const cellText = cells[0].textContent || cells[0].innerText;
                if (cellText.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                }
            }

            // Tampilkan atau sembunyikan baris
            if (found) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }

    // Tambahkan event listener ke input pencarian
    document.getElementById('searchInput').addEventListener('keyup', searchTable);

    // Fungsi cetak
    document.getElementById('printBtn').addEventListener('click', function() {
        window.print();
    });

    // Panggil fungsi saat halaman dimuat untuk mereset filter jika ada
    window.onload = function() {
        document.getElementById('searchInput').value = ''; // Kosongkan input saat load
        searchTable(); // Jalankan pencarian (akan menampilkan semua karena input kosong)
    };
</script>
</body>
</html>