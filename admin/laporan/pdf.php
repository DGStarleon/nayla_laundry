<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'pemilik')) {
    header("Location: ../login.php");
    exit();
}

// Ambil filter dari query string (GET) dan sanitasi dasar
$tanggal_awal = filter_input(INPUT_GET, 'tanggal_awal', FILTER_SANITIZE_STRING) ?? '';
$tanggal_akhir = filter_input(INPUT_GET, 'tanggal_akhir', FILTER_SANITIZE_STRING) ?? '';
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? '';

// Bangun kondisi WHERE
$where_parts = [];
$params = [];

if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
    $date1 = DateTime::createFromFormat('Y-m-d', $tanggal_awal);
    $date2 = DateTime::createFromFormat('Y-m-d', $tanggal_akhir);
    if ($date1 && $date2) {
        $where_parts[] = "DATE(t.tanggal_masuk) BETWEEN ? AND ?";
        $params[] = $tanggal_awal;
        $params[] = $tanggal_akhir;
    } else {
        die("Format tanggal tidak valid.");
    }
} elseif (!empty($tanggal_awal)) {
    $date1 = DateTime::createFromFormat('Y-m-d', $tanggal_awal);
    if ($date1) {
        $where_parts[] = "DATE(t.tanggal_masuk) >= ?";
        $params[] = $tanggal_awal;
    } else {
        die("Format tanggal awal tidak valid.");
    }
} elseif (!empty($tanggal_akhir)) {
    $date2 = DateTime::createFromFormat('Y-m-d', $tanggal_akhir);
    if ($date2) {
        $where_parts[] = "DATE(t.tanggal_masuk) <= ?";
        $params[] = $tanggal_akhir;
    } else {
        die("Format tanggal akhir tidak valid.");
    }
}

$valid_status = ['masuk', 'proses', 'selesai', 'diambil', 'all'];
if (!empty($status_filter) && $status_filter !== 'all' && in_array($status_filter, $valid_status)) {
    $where_parts[] = "t.status = ?";
    $params[] = $status_filter;
} elseif (!empty($status_filter) && !in_array($status_filter, $valid_status)) {
    die("Status filter tidak valid.");
}

$where_clause = '';
if (!empty($where_parts)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
}

// Ambil data transaksi (Gabungan dari detail_transaksi)
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
            -- Perbaikan: Hapus locale 'de_DE' dari FORMAT
            GROUP_CONCAT(CONCAT('Rp ', FORMAT(dt.harga_per_kg, 0)) SEPARATOR ', ') AS harga_per_kg_gabungan,
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

try {
    $stmt_data = $pdo->prepare($query_data);
    $stmt_data->execute($params);
    $transaksis = $stmt_data->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil data transaksi: " . $e->getMessage());
}

// Ambil total pendapatan (dari detail_transaksi)
$query_total = "
    SELECT SUM(dt.total_harga) as total_pendapatan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    $where_clause
";

try {
    $stmt_total = $pdo->prepare($query_total);
    $stmt_total->execute($params);
    $total_pendapatan = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_pendapatan'] ?? 0;
} catch (PDOException $e) {
    die("Error menghitung total pendapatan: " . $e->getMessage());
}

// Ambil jumlah transaksi (hanya hitung transaksi unik yang sesuai filter)
$query_jumlah = "
    SELECT COUNT(DISTINCT t.id) as jumlah_transaksi
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    $where_clause
";

try {
    $stmt_jumlah = $pdo->prepare($query_jumlah);
    $stmt_jumlah->execute($params);
    $jumlah_transaksi = $stmt_jumlah->fetch(PDO::FETCH_ASSOC)['jumlah_transaksi'] ?? 0;
} catch (PDOException $e) {
    die("Error menghitung jumlah transaksi: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            padding: 0;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .filter-info {
            text-align: center;
            margin-bottom: 15px;
        }
        .summary {
            margin-bottom: 20px;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        .data-table th {
            background-color: #e0e0e0;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
        }
        .status-primary { background-color: #0d6efd; }
        .status-warning { background-color: #ffc107; color: #000; }
        .status-success { background-color: #198754; }
        .status-secondary { background-color: #6c757d; }
        /* Gaya cetak */
        @media print {
            body {
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
        .action-buttons {
            text-align: center;
            margin-top: 20px;
        }
        .action-buttons button {
            margin: 0 10px;
            padding: 8px 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Transaksi Nayla Laundry</h1>
        <p>Tanggal Cetak: <?= date('d/m/Y H:i') ?></p>
    </div>

    <div class="filter-info">
        <p><strong>Periode:</strong>
            <?php if ($tanggal_awal || $tanggal_akhir): ?>
                <?php if ($tanggal_awal): ?><?= date('d/m/Y', strtotime($tanggal_awal)); endif; ?>
                <?php if ($tanggal_awal && $tanggal_akhir): ?> - <?php endif; ?>
                <?php if ($tanggal_akhir): ?><?= date('d/m/Y', strtotime($tanggal_akhir)); endif; ?>
            <?php else: ?>
                Semua Tanggal
            <?php endif; ?>
        </p>
        <p><strong>Status:</strong>
            <?php if ($status_filter && $status_filter !== 'all'): ?>
                <?= htmlspecialchars(ucfirst($status_filter)) ?>
            <?php else: ?>
                Semua Status
            <?php endif; ?>
        </p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td><strong>Total Pendapatan:</strong><br>Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                <td><strong>Jumlah Transaksi:</strong><br><?= $jumlah_transaksi ?></td>
            </tr>
        </table>
    </div>

    <table class="data-table">
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
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transaksis)): ?>
                <tr>
                    <td colspan="14" style="text-align: center;">Tidak ada transaksi yang ditemukan.</td>
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
                        <span class="status-badge status-<?= $t['status'] === 'proses' ? 'warning' : ($t['status'] === 'masuk' ? 'primary' : ($t['status'] === 'selesai' ? 'success' : 'secondary')) ?>">
                            <?= htmlspecialchars(ucfirst($t['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($t['username']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($t['tanggal_masuk'])) ?></td>
                    <td><?= $t['tanggal_selesai'] ? date('d/m/Y H:i', strtotime($t['tanggal_selesai'])) : '-' ?></td>
                    <td><?= $t['tanggal_diambil'] ? date('d/m/Y H:i', strtotime($t['tanggal_diambil'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Tombol Aksi untuk Cetak dan Tutup -->
    <div class="action-buttons no-print">
        <button onclick="window.print();">Cetak Laporan</button>
        <button onclick="window.close();">Tutup</button>
    </div>


</body>
</html>