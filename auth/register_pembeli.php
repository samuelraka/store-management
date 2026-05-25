<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

sudahLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = bersihkan($_POST['nama'] ?? '');
    $email      = bersihkan($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    $no_telepon = bersihkan($_POST['no_telepon'] ?? '');
    $alamat     = bersihkan($_POST['alamat'] ?? '');

    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Nama, email, dan password wajib diisi';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar';
        } else {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (nama, email, password, role, no_telepon)
                     VALUES (?, ?, ?, 'pembeli', ?)"
                );
                $stmt->execute([$nama, $email, $hash, $no_telepon]);
                $user_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    "INSERT INTO profil_pembeli (user_id, alamat) VALUES (?, ?)"
                );
                $stmt->execute([$user_id, $alamat]);

                $pdo->commit();

                $_SESSION['user_id'] = $user_id;
                $_SESSION['nama']    = $nama;
                $_SESSION['email']   = $email;
                $_SESSION['role']    = 'pembeli';

                redirect('../toko/index.php', 'Akun berhasil dibuat, selamat berbelanja!');

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
    <title>Daftar Pembeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-1 text-center">Daftar Akun Pembeli</h4>
                    <p class="text-muted text-center small mb-4">Daftar untuk mulai berbelanja</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control"
                                   value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="no_telepon" class="form-control"
                                   value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Konfirmasi</label>
                                <input type="password" name="konfirmasi" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-muted">(opsional)</span></label>
                            <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Daftar Sekarang</button>
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