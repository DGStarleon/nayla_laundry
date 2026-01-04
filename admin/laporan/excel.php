<?php
session_start();
require_once '../../config/koneksi.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'pemilik')) {
    header("Location: ../login.php");
    exit();
}

// Ambil Filter dari URL (GET)
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Bangun Kondisi WHERE (sama seperti di laporan.php)
$where_parts = [];
$params = [];

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

if (!empty($status_filter) && $status_filter !== 'all') {
    $where_parts[] = "t.status = ?";
    $params[] = $status_filter;
}

$where_clause = '';
if (!empty($where_parts)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
}

// Query untuk Data Tabel (sama seperti di laporan.php)
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
$transaksis = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

// Atur header untuk download file CSV/Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="laporan_transaksi_'.date('Y-m-d_His').'.xls"');
header('Cache-Control: max-age=0');

// Buka output buffer
ob_start();

$output = fopen("php://output", "w");

// Header kolom Excel
$headers = [
    "Pelanggan",
    "Telepon",
    "Alamat",
    "Item",
    "Kuantitas",
    "Harga Kg/Satuan",
    "Diskon",
    "Total",
    "Status",
    "Kasir",
    "Masuk",
    "Selesai",
    "Diambil"
];
// Gunakan tab sebagai delimiter, bukan koma
fputcsv($output, $headers, "\t");

// Isi data
foreach ($transaksis as $t) {
    $row = [
        $t['nama_pelanggan'],
        $t['telepon'] ?? '-',
        $t['alamat'] ?? '-',
        $t['nama_paket_gabungan'] ?? 'Tidak ada detail',
        $t['kuantitas_gabungan'] ?? '0',
        $t['harga_per_kg_gabungan'] ?? 'Rp 0',
        "Rp " . number_format($t['diskon'], 0, ',', '.'),
        number_format($t['total_pendapatan_dari_detail'] ?? $t['total_transaksi_header'], 0, ',', '.'),
        ucfirst($t['status']),
        $t['username'],
        date('d/m/Y H:i', strtotime($t['tanggal_masuk'])),
        $t['tanggal_selesai'] ? date('d/m/Y H:i', strtotime($t['tanggal_selesai'])) : '-',
        $t['tanggal_diambil'] ? date('d/m/Y H:i', strtotime($t['tanggal_diambil'])) : '-'
    ];
    fputcsv($output, $row, "\t");
}

fclose($output);
ob_end_flush(); // Kirim output ke browser
exit();
?>