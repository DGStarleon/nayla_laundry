<?php
session_start();
require_once '../../config/koneksi.php';

// Cek role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        // Cek apakah paket digunakan di transaksi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE paket_id = ?");
        $stmt->execute([$id]);
        $jumlah_transaksi = $stmt->fetchColumn();

        if ($jumlah_transaksi > 0) {
            $_SESSION['error'] = "Paket tidak bisa dihapus karena sedang digunakan dalam transaksi.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM paket WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['success'] = "Paket berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus paket.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ID paket tidak valid.";
}

// Kembali ke halaman daftar paket
header("Location: data_laundry.php");
exit();