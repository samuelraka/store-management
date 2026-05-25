<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

sudahLogin();

$error = '';
$step  = isset($_POST['step']) ? (int)$_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar'])) {
    $nama           = bersihkan($_POST['nama'] ?? '');
    $email          = bersihkan($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $konfirmasi     = $_POST['konfirmasi'] ?? '';
    $no_telepon     = bersihkan($_POST['no_telepon'] ?? '');
    $nama_toko      = bersihkan($_POST['nama_toko'] ?? '');
    $bidang_usaha   = bersihkan($_POST['bidang_usaha'] ?? '');
    $lama_beroperasi = (int)($_POST['lama_beroperasi'] ?? 0);
    $provinsi       = bersihkan($_POST['provinsi'] ?? '');
    $kota           = bersihkan($_POST['kota'] ?? '');
    $kecamatan      = bersihkan($_POST['kecamatan'] ?? '');

    // Validasi
    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Semua field wajib diisi';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        // Cek email sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert users
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (nama, email, password, role, no_telepon)
                     VALUES (?, ?, ?, 'superadmin', ?)"
                );
                $stmt->execute([$nama, $email, $hash, $no_telepon]);
                $user_id = $pdo->lastInsertId();

                // Insert profil_usaha
                $stmt = $pdo->prepare(
                    "INSERT INTO profil_usaha 
                     (user_id, nama_toko, bidang_usaha, lama_beroperasi, provinsi, kota, kecamatan)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $user_id, $nama_toko, $bidang_usaha,
                    $lama_beroperasi, $provinsi, $kota, $kecamatan
                ]);

                $pdo->commit();

                // Auto login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['nama']    = $nama;
                $_SESSION['email']   = $email;
                $_SESSION['role']    = 'superadmin';

                redirect('../admin/dashboard.php', 'Selamat datang! Toko Anda berhasil didaftarkan.');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan, coba lagi';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Toko</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-1 text-center">Daftar Toko</h4>
                    <p class="text-muted text-center small mb-4">Isi informasi di bawah untuk mendaftarkan toko Anda</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="daftar" value="1">

                        <h6 class="text-muted mb-3">— Informasi Akun —</h6>
                        <div class="mb-3">
                            <label class="form-label">Nama Pemilik</label>
                            <input type="text" name="nama" class="form-control"
                                   value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Konfirmasi Password</label>
                                <input type="password" name="konfirmasi" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="no_telepon" class="form-control"
                                   value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                        </div>

                        <h6 class="text-muted mb-3">— Informasi Usaha —</h6>
                        <div class="mb-3">
                            <label class="form-label">Nama Toko</label>
                            <input type="text" name="nama_toko" class="form-control"
                                   value="<?= htmlspecialchars($_POST['nama_toko'] ?? '') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Bidang Usaha</label>
                                <select name="bidang_usaha" class="form-select" required>
                                    <option value="">-- Pilih --</option>
                                    <?php
                                    $bidang = ['Kuliner','Fashion','Elektronik','Kesehatan',
                                               'Kecantikan','Otomotif','Pertanian','Umum','Lainnya'];
                                    foreach ($bidang as $b):
                                        $sel = ($_POST['bidang_usaha'] ?? '') === $b ? 'selected' : '';
                                    ?>
                                    <option value="<?= $b ?>" <?= $sel ?>><?= $b ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Lama Beroperasi (tahun)</label>
                                <input type="number" name="lama_beroperasi" class="form-control"
                                       min="0" value="<?= htmlspecialchars($_POST['lama_beroperasi'] ?? '0') ?>">
                            </div>
                        </div>

                        <h6 class="text-muted mb-3">— Lokasi Toko —</h6>
                        <div class="mb-3">
                            <label class="form-label">Provinsi</label>
                            <input type="text" name="provinsi" class="form-control"
                                   value="<?= htmlspecialchars($_POST['provinsi'] ?? '') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kota / Kabupaten</label>
                                <input type="text" name="kota" class="form-control"
                                       value="<?= htmlspecialchars($_POST['kota'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kecamatan</label>
                                <input type="text" name="kecamatan" class="form-control"
                                       value="<?= htmlspecialchars($_POST['kecamatan'] ?? '') ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-2">Daftar Sekarang</button>
                    </form>
                    <hr>
                    <div class="text-center small">
                        Sudah punya akun? <a href="login.php">Masuk</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>