<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil daftar paket
$pakets = $pdo->query("SELECT * FROM paket")->fetchAll();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $telepon_pelanggan = trim($_POST['telepon_pelanggan'] ?? '');
    $alamat_pelanggan = trim($_POST['alamat_pelanggan'] ?? '');
    $paket_ids = $_POST['paket_id'] ?? [];
    $kuantitas = $_POST['kuantitas'] ?? [];
    $jenis_items = $_POST['jenis_item'] ?? []; // Ambil jenis dari form
    $diskon = (float)($_POST['diskon'] ?? 0);
    $tanggal_masuk_tanggal = $_POST['tanggal_masuk_tanggal'] ?? '';
    $tanggal_masuk_waktu = $_POST['tanggal_masuk_waktu'] ?? '';

    if (empty($nama_pelanggan)) {
        $error = "Nama pelanggan wajib diisi!";
    } elseif (empty($paket_ids) || empty($kuantitas) || empty($jenis_items) || count($paket_ids) !== count($kuantitas) || count($paket_ids) !== count($jenis_items)) {
        $error = "Paket, kuantitas, dan jenis harus diisi!";
    } elseif (empty($tanggal_masuk_tanggal) || empty($tanggal_masuk_waktu)) {
        $error = "Tanggal dan waktu masuk wajib diisi!";
    } else {
        $tanggal_masuk = $tanggal_masuk_tanggal . ' ' . $tanggal_masuk_waktu . ':00';

        $pdo->beginTransaction();

        try {
            // Simpan pelanggan baru
            $stmt_pelanggan = $pdo->prepare("INSERT INTO pelanggan (nama, telepon, alamat) VALUES (?, ?, ?)");
            $stmt_pelanggan->execute([$nama_pelanggan, $telepon_pelanggan, $alamat_pelanggan]);
            $pelanggan_id = $pdo->lastInsertId();

            // --- Hitung total dan simpan ke transaksi utama ---
            $total = 0;
            $total_berat = 0; // Untuk barang jenis kg
            $total_satuan = 0; // Untuk barang jenis satuan
            $harga_per_kg_referensi = 0;

            $detail_data = [];

            for ($i = 0; $i < count($paket_ids); $i++) {
                $paket_id = (int)$paket_ids[$i];
                $qty = (float)$kuantitas[$i];
                $jenis_item = $jenis_items[$i]; // 'kg' atau 'satuan'

                if ($qty <= 0) {
                     $error = "Kuantitas untuk semua item harus lebih besar dari 0.";
                     break;
                }

                // Ambil harga dan jenis dari database untuk paket ini
                $paket_stmt = $pdo->prepare("SELECT harga_per_kg, jenis FROM paket WHERE id = ? LIMIT 1");
                $paket_stmt->execute([$paket_id]);
                $paket_data = $paket_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$paket_data) {
                    $error = "Data paket tidak ditemukan untuk ID: $paket_id";
                    break;
                }

                $harga_per_kg = (float)$paket_data['harga_per_kg'];
                $jenis_paket_db = $paket_data['jenis'];

                // Validasi: jenis yang dipilih di form harus sesuai dengan jenis paket di database
                if ($jenis_item !== $jenis_paket_db) {
                    $error = "Jenis item untuk paket ID $paket_id tidak sesuai dengan jenis paket di database.";
                    break;
                }

                $total_harga_item = 0;
                if ($jenis_item === 'kg') {
                    $total_harga_item = $qty * $harga_per_kg;
                    $total_berat += $qty;
                } elseif ($jenis_item === 'satuan') {
                    $total_harga_item = $qty * $harga_per_kg; // Asumsi harga adalah per satuan
                    $total_satuan += $qty;
                }

                $total += $total_harga_item;

                // Jika ini adalah paket pertama, simpan sebagai referensi
                if ($i === 0) {
                    $harga_per_kg_referensi = $harga_per_kg;
                }

                $detail_data[] = [
                    'paket_id' => $paket_id,
                    'kuantitas' => $qty,
                    'jenis_item' => $jenis_item,
                    'harga_per_kg' => $harga_per_kg,
                    'total_harga' => $total_harga_item
                ];
            }

            if (!empty($error)) {
                throw new Exception($error);
            }

            // Terapkan diskon ke total
            $total_setelah_diskon = max(0, $total - $diskon);

            // Simpan ke tabel transaksi utama
            // Kita bisa menyimpan total_berat dan total_satuan jika perlu
            $primary_paket_id = $paket_ids[0] ?? null;
            $stmt_transaksi = $pdo->prepare("
                INSERT INTO transaksi (
                    user_id, pelanggan_id, paket_id, berat_kg, harga_per_kg, diskon, tanggal_masuk, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt_transaksi->execute([
                $_SESSION['user_id'],
                $pelanggan_id,
                $primary_paket_id,
                $total_berat, // Simpan total berat dari item jenis kg
                $harga_per_kg_referensi,
                $diskon,
                $tanggal_masuk,
                $total_setelah_diskon
            ])) {
                throw new Exception("Gagal menyimpan header transaksi.");
            }

            $transaksi_id = $pdo->lastInsertId();

            // --- Simpan detail ke detail_transaksi ---
            $stmt_detail = $pdo->prepare("
                INSERT INTO detail_transaksi (transaksi_id, paket_id, kuantitas, jenis_item, harga_per_kg, total_harga)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($detail_data as $detail) {
                if (!$stmt_detail->execute([
                    $transaksi_id,
                    $detail['paket_id'],
                    $detail['kuantitas'],
                    $detail['jenis_item'],
                    $detail['harga_per_kg'],
                    $detail['total_harga']
                ])) {
                    throw new Exception("Gagal menyimpan detail transaksi.");
                }
            }

            $pdo->commit();
            $_SESSION['success'] = "Transaksi berhasil dibuat!";
            header("Location: data_transaksi.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Transaksi - Admin</title>
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
        <li class="nav-item"><a class="nav-link" href="../paket/data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a></li>
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
        <a class="nav-link" href="../paket/data_laundry.php"><i class="bi bi-tag"></i> Paket Laundry</a>
        <a class="nav-link" href="../transaksi/data_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a>
        <a class="nav-link" href="../laporan/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
        <hr>
        <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <h4><i class="bi bi-plus-circle"></i> Buat Transaksi Baru</h4>

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
                    <label for="nama_pelanggan" class="form-label">Nama Pelanggan</label>
                    <input type="text" class="form-control" id="nama_pelanggan" name="nama_pelanggan" value="<?= htmlspecialchars($_POST['nama_pelanggan'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telepon_pelanggan" class="form-label">Telepon</label>
                    <input type="text" class="form-control" id="telepon_pelanggan" name="telepon_pelanggan" value="<?= htmlspecialchars($_POST['telepon_pelanggan'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="alamat_pelanggan" class="form-label">Alamat</label>
                    <textarea class="form-control" id="alamat_pelanggan" name="alamat_pelanggan"><?= htmlspecialchars($_POST['alamat_pelanggan'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Item & Kuantitas</label>
                    <div id="item-list">
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <select class="form-select item-select" name="paket_id[]" required>
                                    <option value="">-- Pilih Paket --</option>
                                    <?php foreach ($pakets as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-harga="<?= $p['harga_per_kg'] ?>" data-jenis="<?= $p['jenis'] ?>">
                                            <?= htmlspecialchars($p['nama_paket']) ?> (Rp <?= number_format($p['harga_per_kg'], 0, ',', '.') ?>/<?= $p['jenis'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" class="form-control kuantitas-input" name="kuantitas[]" placeholder="Kuantitas" min="0.01" required>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select jenis-input" name="jenis_item[]" required disabled>
                                    <option value="">-- Jenis --</option>
                                    <option value="kg">Kg</option>
                                    <option value="satuan">Satuan</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger btn-sm remove-item">X</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="tambah-item">+ Tambah Item</button>
                    <div class="mt-2">
                        <strong>Total Berat: <span id="total-berat">0.0</span> kg | Total Satuan: <span id="total-satuan">0</span></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="diskon" class="form-label">Diskon (Rp)</label>
                    <input type="number" class="form-control" id="diskon" name="diskon" value="<?= htmlspecialchars($_POST['diskon'] ?? '0') ?>" min="0">
                </div>
                <div class="mb-3">
                    <label for="tanggal_masuk" class="form-label">Tanggal Masuk</label>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk_tanggal" value="<?= htmlspecialchars($_POST['tanggal_masuk_tanggal'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <input type="time" class="form-control" id="waktu_masuk" name="tanggal_masuk_waktu" value="<?= htmlspecialchars($_POST['tanggal_masuk_waktu'] ?? date('H:i')) ?>" required>
                        </div>
                    </div>
                    <div class="form-text">Contoh: 21/12/2025 jam 10:30</div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Transaksi
                </button>
                <a href="data_transaksi.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Batal
                </a>
            </form>
        </div>
    </div>
</div>

<script>
let totalBerat = 0;
let totalSatuan = 0;

function updateTotal() {
    totalBerat = 0;
    totalSatuan = 0;

    // Ambil semua baris item
    const allRows = [...document.querySelectorAll('#item-list > .row')];

    allRows.forEach(row => {
        const jenis = row.querySelector('.jenis-input').value;
        const qty = parseFloat(row.querySelector('.kuantitas-input').value) || 0;

        if(jenis === 'kg' && qty > 0) {
            totalBerat += qty;
        } else if(jenis === 'satuan' && qty > 0) {
            totalSatuan += qty;
        }
    });

    document.getElementById('total-berat').textContent = totalBerat.toFixed(2);
    document.getElementById('total-satuan').textContent = totalSatuan;
}

// Fungsi untuk menambahkan event listener ke satu baris
function attachListenersToRow(rowElement) {
    // Listener untuk perubahan select paket
    rowElement.querySelector('.item-select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const jenisPaket = selectedOption.getAttribute('data-jenis');
        const jenisSelect = rowElement.querySelector('.jenis-input');
        jenisSelect.value = jenisPaket;
        jenisSelect.disabled = false;
        updateTotal(); // Panggil updateTotal setelah mengubah jenis
    });

    // Listener untuk perubahan input kuantitas
    rowElement.querySelector('.kuantitas-input').addEventListener('input', updateTotal);

    // Listener untuk perubahan select jenis (jika pengguna mengubahnya secara manual setelah diaktifkan)
    rowElement.querySelector('.jenis-input').addEventListener('change', updateTotal);

    // Listener untuk tombol hapus (hanya untuk baris tambahan, baris pertama tidak punya tombol ini di listener ini)
    const removeButton = rowElement.querySelector('.remove-item');
    if (removeButton) {
        removeButton.addEventListener('click', function() {
            const itemList = document.getElementById('item-list');
            if (itemList.children.length > 1) {
                this.closest('.row').remove();
                updateTotal();
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const itemList = document.getElementById('item-list');
    const tambahBtn = document.getElementById('tambah-item');

    // Tambahkan listener ke baris pertama saat halaman dimuat
    const firstRow = itemList.querySelector('.row'); // Ambil baris pertama
    if (firstRow) {
        attachListenersToRow(firstRow);
    }

    tambahBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2';
        newRow.innerHTML = `
            <div class="col-md-4">
                <select class="form-select item-select" name="paket_id[]" required>
                    <option value="">-- Pilih Paket --</option>
                    <?php foreach ($pakets as $p): ?>
                        <option value="<?= $p['id'] ?>" data-harga="<?= $p['harga_per_kg'] ?>" data-jenis="<?= $p['jenis'] ?>">
                            <?= htmlspecialchars($p['nama_paket']) ?> (Rp <?= number_format($p['harga_per_kg'], 0, ',', '.') ?>/<?= $p['jenis'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" step="0.01" class="form-control kuantitas-input" name="kuantitas[]" placeholder="Kuantitas" min="0.01" required>
            </div>
            <div class="col-md-3">
                <select class="form-select jenis-input" name="jenis_item[]" required disabled>
                    <option value="">-- Jenis --</option>
                    <option value="kg">Kg</option>
                    <option value="satuan">Satuan</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-item">X</button>
            </div>
        `;
        itemList.appendChild(newRow);

        // Tambahkan listener ke baris baru
        attachListenersToRow(newRow);

        // Panggil updateTotal setelah menambah baris
        updateTotal();
    });

    // Panggil updateTotal saat halaman pertama kali dimuat
    updateTotal();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>