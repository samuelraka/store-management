<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$aksi = $_REQUEST['aksi'] ?? '';

switch ($aksi) {

    case 'tambah':
        $nama      = bersihkan($_POST['nama_supplier'] ?? '');
        $telepon   = bersihkan($_POST['no_telepon'] ?? '');
        $kota      = bersihkan($_POST['kota'] ?? '');
        $alamat    = bersihkan($_POST['alamat'] ?? '');

        if (empty($nama)) {
            redirect('index.php', 'Nama supplier wajib diisi', 'error');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO supplier (user_id, nama_supplier, no_telepon, kota, alamat)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user['id'], $nama, $telepon, $kota, $alamat]);
        redirect('index.php', 'Supplier berhasil ditambahkan!');
        break;

    case 'edit':
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = bersihkan($_POST['nama_supplier'] ?? '');
        $telepon  = bersihkan($_POST['no_telepon'] ?? '');
        $kota     = bersihkan($_POST['kota'] ?? '');
        $alamat   = bersihkan($_POST['alamat'] ?? '');
        $is_aktif = (int)($_POST['is_aktif'] ?? 1);

        if (!$id || empty($nama)) {
            redirect('index.php', 'Data tidak valid', 'error');
        }

        $stmt = $pdo->prepare(
            "UPDATE supplier SET
                nama_supplier = ?, no_telepon = ?,
                kota = ?, alamat = ?, is_aktif = ?
             WHERE id = ?"
        );
        $stmt->execute([$nama, $telepon, $kota, $alamat, $is_aktif, $id]);
        redirect('index.php', 'Supplier berhasil diupdate!');
        break;

    case 'hapus':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('index.php', 'Data tidak valid', 'error');

        // Cek ada barang yang pakai supplier ini
        $cek = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE supplier_id = ?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            redirect('index.php',
                'Supplier tidak bisa dihapus karena masih dipakai oleh barang',
                'error'
            );
        }

        $pdo->prepare("DELETE FROM supplier WHERE id = ?")->execute([$id]);
        redirect('index.php', 'Supplier berhasil dihapus');
        break;

    default:
        redirect('index.php');
}