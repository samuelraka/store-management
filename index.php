<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

sudahLogin(); // Kalau sudah login redirect sesuai role

// Ambil info toko untuk ditampilkan di landing page
$stmt = $pdo->query(
    "SELECT u.nama, p.nama_toko, p.bidang_usaha
     FROM users u
     JOIN profil_usaha p ON u.id = p.user_id
     WHERE u.role = 'superadmin'
     LIMIT 1"
);
$toko = $stmt->fetch();

// Ambil total produk tersedia untuk ditampilkan
$stmt        = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok > 0");
$total_produk = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Kami') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel ="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ============ NAVBAR ============ -->
<nav class="navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="bi bi-shop me-1"></i>
            <?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Kami') ?>
        </a>
        <div class="d-flex gap-2">
            <a href="/toko/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-grid me-1"></i>Katalog
            </a>
            <a href="auth/login.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
            </a>
            <a href="auth/register_pembeli.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus me-1"></i>Daftar
            </a>
        </div>
    </div>
</nav>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1>
                    Belanja Mudah,<br>
                    <span style="color: var(--primary)">Langsung dari Toko</span>
                </h1>
                <p class="mt-3 mb-4">
                    <?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Kami') ?> menyediakan berbagai produk
                    <?= htmlspecialchars($toko['bidang_usaha'] ?? '') ?> berkualitas.
                    Pesan online, kami antar ke depan pintu Anda.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="toko/index.php" class="btn-primary-custom">
                        <i class="bi bi-grid me-2"></i>Lihat Produk
                    </a>
                    <a href="auth/register_pembeli.php" class="btn-outline-custom">
                        <i class="bi bi-person-plus me-2"></i>Daftar Gratis
                    </a>
                </div>
            </div>
            <div class="col-md-5 text-center mt-4 mt-md-0">
                <div class="hero-img">🛒</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ STATISTIK ============ -->
<section class="section-stat">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3 stat-item">
                <h2><?= number_format($total_produk) ?>+</h2>
                <p>Produk Tersedia</p>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pembeli'");
                ?>
                <h2><?= number_format($stmt->fetchColumn()) ?>+</h2>
                <p>Pembeli Terdaftar</p>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status = 'selesai'");
                ?>
                <h2><?= number_format($stmt->fetchColumn()) ?>+</h2>
                <p>Transaksi Selesai</p>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'selesai'");
                ?>
                <h2><?= number_format($stmt->fetchColumn()) ?>+</h2>
                <p>Pesanan Dikirim</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ FITUR ============ -->
<section class="section-fitur">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-700" style="font-weight:700;color:#1f2937">Kenapa Belanja di Sini?</h2>
            <p class="text-muted">Kami hadir untuk memudahkan pengalaman belanja Anda</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-box-seam"></i></div>
                    <h5>Stok Selalu Update</h5>
                    <p>Ketersediaan produk ditampilkan secara realtime, tidak perlu khawatir kehabisan.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-truck"></i></div>
                    <h5>Pengiriman ke Rumah</h5>
                    <p>Pesan online dan barang langsung dikirim ke alamat Anda dengan kurir terpercaya.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-shield-check"></i></div>
                    <h5>Transaksi Aman</h5>
                    <p>Setiap pesanan tercatat rapi dan bisa dipantau statusnya kapan saja.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-search"></i></div>
                    <h5>Mudah Dicari</h5>
                    <p>Cari produk berdasarkan nama atau kategori dengan cepat dan mudah.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-clock-history"></i></div>
                    <h5>Histori Pesanan</h5>
                    <p>Semua pesanan Anda tersimpan dan bisa dilihat kapan saja di akun Anda.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fitur-card">
                    <div class="fitur-icon"><i class="bi bi-headset"></i></div>
                    <h5>Mudah Dihubungi</h5>
                    <p>Ada pertanyaan? Hubungi kami langsung melalui nomor yang tersedia.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ CTA DAFTAR ============ -->
<section class="section-cta">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="font-weight:700;color:#1f2937">Bergabung Sekarang</h2>
            <p class="text-muted">Pilih sesuai kebutuhan Anda</p>
        </div>
        <div class="row g-4 justify-content-center">

            <!-- Card Pembeli -->
            <div class="col-md-4">
                <div class="cta-card">
                    <div class="icon-big">🛍️</div>
                    <h4>Saya Pembeli</h4>
                    <p>Daftar sebagai pembeli untuk mulai berbelanja, melacak pesanan, dan menikmati kemudahan berbelanja online.</p>
                    <a href="auth/register_pembeli.php" class="btn-pembeli">
                        <i class="bi bi-person-plus me-2"></i>Daftar sebagai Pembeli
                    </a>
                    <div class="mt-3 small text-muted">
                        Sudah punya akun? <a href="auth/login.php">Masuk</a>
                    </div>
                </div>
            </div>

            <!-- Card Owner -->
            <div class="col-md-4">
                <div class="cta-card">
                    <div class="icon-big">🏪</div>
                    <h4>Saya Pemilik Toko</h4>
                    <p>Daftarkan toko Anda untuk mengelola produk, stok, transaksi, dan laporan penjualan dalam satu sistem.</p>
                    <a href="auth/register_owner.php" class="btn-owner">
                        <i class="bi bi-shop me-2"></i>Daftar Toko
                    </a>
                    <div class="mt-3 small text-muted">
                        Sudah punya akun? <a href="auth/login.php">Masuk</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="footer">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <strong style="color:#fff">
                    <i class="bi bi-shop me-1"></i>
                    <?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Kami') ?>
                </strong>
                <p class="mb-0 mt-1">
                    <?= htmlspecialchars($toko['bidang_usaha'] ?? '') ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <a href="/toko/index.php" class="me-3">Katalog</a>
                <a href="/auth/login.php" class="me-3">Masuk</a>
                <a href="/auth/register_pembeli.php">Daftar</a>
                <p class="mb-0 mt-2">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Kami') ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>