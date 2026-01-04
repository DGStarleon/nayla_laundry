<?php
session_start();
require_once '../../config/koneksi.php';

// Cek role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Validasi apakah ID adalah angka positif
if (!is_numeric($id) || $id <= 0) {
    $_SESSION['error'] = "ID transaksi tidak valid.";
    header("Location: data_transaksi.php");
    exit();
}

if ($id > 0) {
    try {
        // Siapkan statement untuk menghapus transaksi
        $stmt = $pdo->prepare("DELETE FROM transaksi WHERE id = ?");
        if ($stmt->execute([$id])) {
            // Cek apakah ada baris yang terpengaruh
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Transaksi berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Transaksi tidak ditemukan.";
            }
        } else {
            $_SESSION['error'] = "Gagal menghapus transaksi.";
        }
    } catch (PDOException $e) {
        // Log error untuk debugging, jangan tampilkan ke user
        error_log("Error menghapus transaksi ID $id: " . $e->getMessage());
        $_SESSION['error'] = "Terjadi kesalahan saat menghapus transaksi.";
    }
} else {
    $_SESSION['error'] = "ID transaksi tidak valid.";
}

// Kembali ke halaman daftar transaksi
header("Location: data_transaksi.php");
exit();
?>