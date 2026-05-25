<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$stmt = $pdo->prepare("SELECT * FROM profil_pembeli WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil_pembeli = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$data_user = $stmt->fetch();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();

// Stat pesanan
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) as total
     FROM pesanan WHERE user_id = ?
     GROUP BY status"
);
$stmt->execute([$user['id']]);
$stat_raw = $stmt->fetchAll();
$stats = array_column($stat_raw, 'total', 'status');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'profil') {
        $nama       = bersihkan($_POST['nama'] ?? '');
        $no_telepon = bersihkan($_POST['no_telepon'] ?? '');
        $alamat     = bersihkan($_POST['alamat'] ?? '');

        if (empty($nama)) {
            $error = 'Nama tidak boleh kosong';
        } else {
            $pdo->prepare(
                "UPDATE users SET nama = ?, no_telepon = ? WHERE id = ?"
            )->execute([$nama, $no_telepon, $user['id']]);

            if ($profil_pembeli) {
                $pdo->prepare(
                    "UPDATE profil_pembeli SET alamat = ? WHERE user_id = ?"
                )->execute([$alamat, $user['id']]);
            } else {
                $pdo->prepare(
                    "INSERT INTO profil_pembeli (user_id, alamat) VALUES (?, ?)"
                )->execute([$user['id'], $alamat]);
            }

            $_SESSION['nama'] = $nama;
            $success = 'Profil berhasil diupdate!';

            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $data_user = $stmt->fetch();
        }
    }

    if ($aksi === 'password') {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $konfirmasi    = $_POST['konfirmasi'] ?? '';

        if (!password_verify($password_lama, $data_user['password'])) {
            $error = 'Password lama salah';
        } elseif (strlen($password_baru) < 6) {
            $error = 'Password baru minimal 6 karakter';
        } elseif ($password_baru !== $konfirmasi) {
            $error = 'Konfirmasi password tidak cocok';
        } else {
            $hash = password_hash($password_baru, PASSWORD_BCRYPT);
            $pdo->prepare(
                "UPDATE users SET password = ? WHERE id = ?"
            )->execute([$hash, $user['id']]);
            $success = 'Password berhasil diubah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/toko/profil/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-shop me-1"></i>
                <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?>
            </a>
            <div class="d-flex gap-2">
                <a href="../index.php" class="nav-icon">
                    <i class="bi bi-grid"></i>
                </a>
                <a href="../pesanan/index.php" class="nav-icon">
                    <i class="bi bi-bag-check"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4" style="max-width:680px">

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Profil -->
    <div class="profile-header">
        <div class="avatar">
            <?= strtoupper(substr($data_user['nama'], 0, 1)) ?>
        </div>
        <div>
            <div style="font-weight:700;font-size:1.1rem;color:#111827">
                <?= htmlspecialchars($data_user['nama']) ?>
            </div>
            <div style="font-size:.875rem;color:#6b7280;margin-top:2px">
                <?= htmlspecialchars($data_user['email']) ?>
            </div>
            <div style="font-size:.78rem;color:#9ca3af;margin-top:4px">
                Bergabung <?= formatTanggal($data_user['created_at']) ?>
            </div>
        </div>
    </div>

    <!-- Statistik Pesanan -->
    <div class="stat-grid">
        <div class="stat-item">
            <div class="num"><?= $stats['menunggu'] ?? 0 ?></div>
            <div class="lbl">Menunggu</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= $stats['dikirim'] ?? 0 ?></div>
            <div class="lbl">Dikirim</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= $stats['selesai'] ?? 0 ?></div>
            <div class="lbl">Selesai</div>
        </div>
        <div class="stat-item">
            <div class="num">
                <?= array_sum($stats) ?>
            </div>
            <div class="lbl">Total</div>
        </div>
    </div>

    <!-- Tab -->
    <div class="nav-tab-custom">
        <a href="#" class="active" onclick="showTab('profil', this)">
            <i class="bi bi-person me-1"></i>Edit Profil
        </a>
        <a href="#" onclick="showTab('password', this)">
            <i class="bi bi-lock me-1"></i>Ubah Password
        </a>
    </div>

    <!-- Form Edit Profil -->
    <div id="tab-profil">
        <div class="form-card">
            <div class="section-title">Informasi Pribadi</div>
            <form method="POST">
                <input type="hidden" name="aksi" value="profil">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control"
                           value="<?= htmlspecialchars($data_user['nama']) ?>"
                           required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control"
                           value="<?= htmlspecialchars($data_user['email']) ?>"
                           disabled>
                    <div class="form-text">Email tidak bisa diubah</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telepon" class="form-control"
                           value="<?= htmlspecialchars($data_user['no_telepon'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat Default</label>
                    <textarea name="alamat" class="form-control" rows="3"
                              placeholder="Alamat pengiriman default..."><?= htmlspecialchars($profil_pembeli['alamat'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- Form Ubah Password -->
    <div id="tab-password" style="display:none">
        <div class="form-card">
            <div class="section-title">Ubah Password</div>
            <form method="POST">
                <input type="hidden" name="aksi" value="password">
                <div class="mb-3">
                    <label class="form-label">Password Lama</label>
                    <input type="password" name="password_lama"
                           class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password_baru"
                           class="form-control"
                           placeholder="Minimal 6 karakter" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="konfirmasi"
                           class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-lock me-1"></i>Ubah Password
                </button>
            </form>
        </div>
    </div>

    <div class="text-center mt-3">
        <a href="../../auth/logout.php"
           style="color:#ef4444;font-size:.875rem;text-decoration:none"
           onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right me-1"></i>Keluar dari Akun
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTab(tab, el) {
    document.getElementById('tab-profil').style.display   = 'none';
    document.getElementById('tab-password').style.display = 'none';
    document.getElementById('tab-' + tab).style.display   = 'block';
    document.querySelectorAll('.nav-tab-custom a').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    return false;
}
</script>
</body>
</html>