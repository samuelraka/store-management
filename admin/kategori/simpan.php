<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$aksi = $_REQUEST['aksi'] ?? '';

switch ($aksi) {

    case 'tambah':
        $nama = bersihkan($_POST['nama'] ?? '');
        if (empty($nama)) {
            redirect('index.php', 'Nama kategori wajib diisi', 'error');
        }

        // Cek duplikat
        $cek = $pdo->prepare(
            "SELECT id FROM kategori_barang WHERE nama = ? AND user_id = ?"
        );
        $cek->execute([$nama, $user['id']]);
        if ($cek->fetch()) {
            redirect('index.php', 'Kategori sudah ada', 'error');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO kategori_barang (user_id, nama) VALUES (?, ?)"
        );
        $stmt->execute([$user['id'], $nama]);
        redirect('index.php', 'Kategori berhasil ditambahkan!');
        break;

    case 'edit':
        $id   = (int)($_POST['id'] ?? 0);
        $nama = bersihkan($_POST['nama'] ?? '');

        if (!$id || empty($nama)) {
            redirect('index.php', 'Data tidak valid', 'error');
        }

        // Cek duplikat (kecuali milik sendiri)
        $cek = $pdo->prepare(
            "SELECT id FROM kategori_barang
             WHERE nama = ? AND user_id = ? AND id != ?"
        );
        $cek->execute([$nama, $user['id'], $id]);
        if ($cek->fetch()) {
            redirect('index.php', 'Nama kategori sudah digunakan', 'error');
        }

        $stmt = $pdo->prepare(
            "UPDATE kategori_barang SET nama = ? WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$nama, $id, $user['id']]);
        redirect('index.php', 'Kategori berhasil diupdate!');
        break;

    case 'hapus':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('index.php', 'Data tidak valid', 'error');

        // Set barang yang pakai kategori ini jadi null
        $pdo->prepare(
            "UPDATE barang SET kategori_id = NULL WHERE kategori_id = ?"
        )->execute([$id]);

        $pdo->prepare(
            "DELETE FROM kategori_barang WHERE id = ? AND user_id = ?"
        )->execute([$id, $user['id']]);

        redirect('index.php', 'Kategori berhasil dihapus');
        break;

    default:
        redirect('index.php');
}