<?php
session_start();
require_once '../../config/koneksi.php';

// Cek role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Jangan izinkan admin menghapus akunnya sendiri
if ($id == $_SESSION['user_id']) {
    $_SESSION['error'] = "Tidak bisa menghapus akun sendiri!";
    header("Location: data.php");
    exit();
}

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = "User berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus user.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ID user tidak valid.";
}

// Kembali ke halaman daftar user
header("Location: data.php");
exit();