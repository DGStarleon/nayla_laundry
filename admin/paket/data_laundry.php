<?php
session_start();
require_once '../../config/koneksi.php';

// Proteksi halaman: Hanya admin yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data paket diurutkan berdasarkan nama
$stmt = $pdo->query("SELECT * FROM paket ORDER BY nama_paket ASC");
$pakets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Paket Laundry - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* =========================================
           1. GLOBAL & LAYOUT STYLES
           ========================================= */
        body {
            background-color: #f8f9fa;
            padding-top: 56px;
        }
        @media (min-width: 768px) {
            body { padding-top: 0; }
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
            .mobile-toggle { display: none !important; }
        }
        @media (max-width: 767.98px) {
            .sidebar { display: none; }
            .main-content { padding: 20px; }
        }

        /* =========================================
           2. SIDEBAR & NAVIGATION STYLES
           ========================================= */
        .sidebar .logo {
            text-align: center;
            color: white;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 1.3rem;
            padding: 15px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .sidebar .logo img {
            max-height: 80px;
            width: auto;
            object-fit: contain;
            margin-bottom: 8px;
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
        .topbar { background-color: #3a6099ff; }
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
        .offcanvas .nav-link:hover { background-color: #e9ecef; }

        /* =========================================
           3. TABLE & COMPONENT STYLES
           ========================================= */
        .aksi-col { width: 120px; }
        
        /* Gaya untuk pratinjau cetak */
        @media print {
            body { background-color: white; padding: 0; }
            .sidebar, .topbar, .mobile-toggle, .offcanvas, .main-content .d-flex, .btn {
                display: none !important;
            }
            .main-content { margin-left: 0; padding: 10px; }
            .card { border: none; box-shadow: none; }
            .table th, .table td { page-break-inside: avoid; }
            .aksi-col { display: none !important; }
            .table th.aksi-col { display: none !important; }
            
            /* PERBAIKAN BUG: Sembunyikan kolom pencarian saat dicetak */
            #searchInput { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Mobile Topbar -->
<nav class="navbar topbar d-md-none">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <span class="navbar-brand">Nayla Laundry</span>
        <button class="btn btn-light mobile-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
            ☰
        </button>
    </div>
</nav>

<!-- Desktop Sidebar -->
<div class="sidebar d-none d-md-block">
    <div class="logo">
        <img src="../../img/nayla_laundry.png" alt="Nayla Laundry Logo">
        <span>Nayla Laundry</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="../user/data.php"><i class="bi bi-people"></i> Kelola User</a></li>
        <li class="nav-item"><a class="nav-link active" href="data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a></li>
        <li class="nav-item"><a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
        <li class="nav-item"><a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a></li>
        <hr style="border-color: #495057; margin: 15px 10px;">
        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    </ul>
</div>

<!-- Mobile Sidebar (Offcanvas) -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <a class="nav-link" href="../user/data.php"><i class="bi bi-people"></i> Kelola User</a>
        <a class="nav-link active" href="data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a>
        <a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a>
        <a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <h4><i class="bi bi-tag-fill"></i> Daftar Paket Laundry</h4>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="col-md-4">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama, harga, atau jenis paket...">
        </div>
        <div class="d-flex gap-2">
            <a href="tambah.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Tambah Paket Baru
            </a>
            <button id="printBtn" class="btn btn-info text-white">
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
                            <th class="aksi-col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="paketTableBody">
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
                                <td class="aksi-col">
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="hapus.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus paket <?= htmlspecialchars($p['nama_paket']) ?>?')" title="Hapus">
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ==========================================
    // FUNGSI PENCARIAN (SUDAH DIPERBAIKI)
    // Mencari kata kunci di SEMUA kolom, bukan hanya kolom pertama
    // ==========================================
    function searchTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const tableBody = document.getElementById('paketTableBody');
        const rows = tableBody.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;

            // PERBAIKAN: Loop melalui SEMUA kolom (cells) dalam baris ini
            for (let j = 0; j < cells.length; j++) {
                const cellText = cells[j].textContent || cells[j].innerText;
                
                // Jika teks di kolom manapun cocok dengan kata kunci pencarian
                if (cellText.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break; // Hentikan pencarian di baris ini jika sudah ketemu
                }
            }

            // Tampilkan atau sembunyikan baris berdasarkan hasil pencarian
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
        document.getElementById('searchInput').value = ''; 
        searchTable(); 
    };
</script>
</body>
</html>