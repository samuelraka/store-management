<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

sudahLogin(); // Kalau sudah login, redirect sesuai role

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = bersihkan($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi';
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Akun tidak ditemukan';
        } elseif (!$user['is_aktif']) {
            $error = 'Akun Anda dinonaktifkan, hubungi admin';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Password salah';
        } else {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['foto']    = $user['foto'];

            // Redirect sesuai role
            switch ($user['role']) {
                case 'superadmin':
                    redirect('../admin/dashboard.php', 'Selamat datang, ' . $user['nama']);
                    break;
                case 'kasir':
                    redirect('../kasir/dashboard.php', 'Selamat datang, ' . $user['nama']);
                    break;
                case 'pembeli':
                    redirect('../toko/index.php', 'Selamat datang, ' . $user['nama']);
                    break;
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
    <title>Login — Toko</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="mb-4 text-center">Masuk</h4>
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Masuk</button>
                        </form>
                        <hr>
                        <div class="text-center small">
                            Belum punya akun?
                            <a href="register_owner.php">Daftar Toko</a> |
                            <a href="register_pembeli.php">Daftar Pembeli</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>