<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Barang tidak ditemukan', 'error');

// Cek barang ada
$stmt = $pdo->prepare("SELECT nama_barang FROM barang WHERE id = ?");
$stmt->execute([$id]);
$barang = $stmt->fetch();
if (!$barang) redirect('index.php', 'Barang tidak ditemukan', 'error');

// Cek apakah sudah pernah ada di transaksi
$cek = $pdo->prepare(
    "SELECT COUNT(*) FROM transaksi_detail WHERE barang_id = ?"
);
$cek->execute([$id]);
if ($cek->fetchColumn() > 0) {
    redirect('index.php',
        'Barang tidak bisa dihapus karena sudah ada di riwayat transaksi',
        'error'
    );
}

// Cek di pesanan
$cek2 = $pdo->prepare("SELECT COUNT(*) FROM pesanan_detail WHERE barang_id = ?");
$cek2->execute([$id]);
if ($cek2->fetchColumn() > 0) {
    redirect('index.php',
        'Barang tidak bisa dihapus karena sudah ada di riwayat pesanan',
        'error'
    );
}

// Hapus stok mutasi dulu, lalu barang
try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM stok_mutasi WHERE barang_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM barang WHERE id = ?")->execute([$id]);
    $pdo->commit();
    redirect('index.php', 'Barang ' . $barang['nama_barang'] . ' berhasil dihapus');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect('index.php', 'Gagal menghapus barang', 'error');
}