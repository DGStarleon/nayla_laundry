<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT t.*, u.username, pl.nama AS nama_pelanggan, pl.telepon, pl.alamat, pk.nama_paket AS nama_paket_header, pk.harga_per_kg AS harga_per_kg_header
    FROM transaksi t
    JOIN users u ON t.user_id = u.id
    JOIN pelanggan pl ON t.pelanggan_id = pl.id
    LEFT JOIN paket pk ON t.paket_id = pk.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    die("Transaksi tidak ditemukan!");
}

// Ambil detail transaksi dari tabel detail_transaksi
$stmt_detail = $pdo->prepare("
    SELECT dt.*, p.nama_paket, p.harga_per_kg
    FROM detail_transaksi dt
    JOIN paket p ON dt.paket_id = p.id
    WHERE dt.transaksi_id = ?
    ORDER BY dt.created_at ASC
");
$stmt_detail->execute([$id]);
$transaksi_details = $stmt_detail->fetchAll();

if (!$transaksi_details) {
    die("Detail transaksi tidak ditemukan!");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Transaksi #<?= $transaksi['id'] ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 0;
            background: white;
        }
        .struk {
            width: 100%;
            max-width: 58mm;
            margin: 0 auto;
            padding: 3px 0;
            box-sizing: border-box;
            background: white;
        }
        @media print {
            .struk {
                width: 58mm;
                margin: 0;
                padding: 0;
            }
        }
        .header {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 0;
            padding: 0;
        }
        .alamat, .kontak {
            text-align: center;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .detail {
            margin: 5px 0;
            padding: 0;
        }
        .detail p {
            margin: 2px 0;
            line-height: 1.2;
        }
        .item {
            display: flex;
            justify-content: space-between;
            margin: 0;
            padding: 0;
        }
        .item .name {
            flex: 1;
            margin: 0;
            padding: 0;
        }
        .item .value {
            text-align: right;
            margin: 0;
            padding: 0;
        }
        .total {
            font-weight: bold;
            font-size: 14px;
            text-align: right;
            margin: 8px 0 0 0;
            padding: 0;
            border-top: 1px solid #000;
        }
        .footer {
            text-align: center;
            margin: 8px 0 0 0;
            padding: 0;
            font-size: 10px;
            color: #555;
        }
        .divider {
            text-align: center;
            margin: 5px 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="struk">
        <!-- Header -->
        <div class="header">
            <p>NAYLA LAUNDRY</p>
        </div>
        <div class="alamat">
            <p>Kp Leuwikopo RT 003/RW 002 Gg Artayasa</p>
            <p>Babakan - Dramaga</p>
        </div>
        <div class="kontak">
            <p>+62 85714824170</p>
        </div>

        <!-- Informasi Transaksi -->
        <div class="divider">
            <p>================================</p>
        </div>
        <div class="detail">
            <p>ID Transaksi: <?= $transaksi['id'] ?></p>
            <p>Pelanggan: <?= htmlspecialchars($transaksi['nama_pelanggan']) ?></p>
            <p>Telepon: <?= htmlspecialchars($transaksi['telepon'] ?? '-') ?></p>
            <p>Alamat: <?= htmlspecialchars($transaksi['alamat'] ?? '-') ?></p>
            <p>--------------------------------</p>
            <?php foreach ($transaksi_details as $detail): ?>
            <p class="item">
                <span class="name"><?= htmlspecialchars($detail['nama_paket']) ?></span>
                <span class="value"><?= $detail['kuantitas'] ?> <?= $detail['jenis_item'] ?> x Rp <?= number_format($detail['harga_per_kg'], 0, ',', '.') ?></span>
            </p>
            <p class="item">
                <span class="name"></span>
                <span class="value">Subtotal: Rp <?= number_format($detail['total_harga'], 0, ',', '.') ?></span>
            </p>
            <p>--------------------------------</p>
            <?php endforeach; ?>
            <p class="item">
                <span class="name">Diskon:</span>
                <span class="value">-Rp <?= number_format($transaksi['diskon'] ?? 0, 0, ',', '.') ?></span>
            </p>
            <p>Tanggal Masuk: <?= date('d/m/Y H:i', strtotime($transaksi['tanggal_masuk'])) ?></p>
            <p>Kasir: <?= htmlspecialchars($transaksi['username']) ?></p>
        </div>

        <!-- Total -->
        <div class="total">
            <p>Total: Rp <?= number_format($transaksi['total'] ?? 0, 0, ',', '.') ?></p>
        </div>

        <!-- Footer -->
        <div class="divider">
            <p>================================</p>
        </div>
        <div class="footer">
            <p>Terima kasih!</p>
            <p>Semoga berlangganan kembali.</p>
            <p>Harap simpan struk ini.</p>
            <p>Komplain maks. 24 jam setelah pengambilan.</p>
        </div>
    </div>

    <script>
        // Tunggu halaman selesai dimuat, lalu cetak
        window.addEventListener('load', () => {
            window.print();
            setTimeout(() => {
                window.close();
            }, 1500); // Tunggu 1.5 detik sebelum menutup
        });
    </script>
</body>
</html>