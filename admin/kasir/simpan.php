<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$aksi = $_REQUEST['aksi'] ?? '';

switch ($aksi) {

    case 'tambah':
        $nama       = bersihkan($_POST['nama'] ?? '');
        $email      = bersihkan($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $no_telepon = bersihkan($_POST['no_telepon'] ?? '');

        if (empty($nama) || empty($email) || empty($password)) {
            redirect('index.php', 'Nama, email, dan password wajib diisi', 'error');
        }
        if (strlen($password) < 6) {
            redirect('index.php', 'Password minimal 6 karakter', 'error');
        }

        // Cek email duplikat
        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            redirect('index.php', 'Email sudah terdaftar', 'error');
        }

        // Upload foto
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $upload = uploadFoto($_FILES['foto']);
            if (!$upload['status']) {
                redirect('index.php', $upload['pesan'], 'error');
            }
            $foto = $upload['nama_file'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (nama, email, password, role, no_telepon, foto)
             VALUES (?, ?, ?, 'kasir', ?, ?)"
        );
        $stmt->execute([$nama, $email, $hash, $no_telepon, $foto]);
        redirect('index.php', "Kasir $nama berhasil ditambahkan!");
        break;

    case 'edit':
        $id         = (int)($_POST['id'] ?? 0);
        $nama       = bersihkan($_POST['nama'] ?? '');
        $email      = bersihkan($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $no_telepon = bersihkan($_POST['no_telepon'] ?? '');

        if (!$id || empty($nama) || empty($email)) {
            redirect('index.php', 'Data tidak valid', 'error');
        }

        // Cek email duplikat (kecuali milik sendiri)
        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $cek->execute([$email, $id]);
        if ($cek->fetch()) {
            redirect('index.php', 'Email sudah digunakan', 'error');
        }

        // Upload foto baru (kalau ada)
        $foto_update = '';
        if (!empty($_FILES['foto']['name'])) {
            $upload = uploadFoto($_FILES['foto']);
            if (!$upload['status']) {
                redirect('index.php', $upload['pesan'], 'error');
            }
            $foto_update = ", foto = '{$upload['nama_file']}'";
        }

        // Update password (kalau diisi)
        $pass_update = '';
        if (!empty($password)) {
            if (strlen($password) < 6) {
                redirect('index.php', 'Password minimal 6 karakter', 'error');
            }
            $hash        = password_hash($password, PASSWORD_BCRYPT);
            $pass_update = ", password = '$hash'";
        }

        $pdo->prepare(
            "UPDATE users SET
             nama = ?, email = ?, no_telepon = ?
             $foto_update $pass_update
             WHERE id = ? AND role = 'kasir'"
        )->execute([$nama, $email, $no_telepon, $id]);

        redirect('index.php', "Data kasir $nama berhasil diupdate!");
        break;

    case 'toggle':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('index.php', 'Data tidak valid', 'error');

        $stmt = $pdo->prepare(
            "SELECT nama, is_aktif FROM users WHERE id = ? AND role = 'kasir'"
        );
        $stmt->execute([$id]);
        $kasir = $stmt->fetch();
        if (!$kasir) redirect('index.php', 'Kasir tidak ditemukan', 'error');

        $new_status = $kasir['is_aktif'] ? 0 : 1;
        $pdo->prepare(
            "UPDATE users SET is_aktif = ? WHERE id = ?"
        )->execute([$new_status, $id]);

        $pesan = $new_status
            ? "Kasir {$kasir['nama']} berhasil diaktifkan"
            : "Kasir {$kasir['nama']} berhasil dinonaktifkan";
        redirect('index.php', $pesan);
        break;

    default:
        redirect('index.php');
}