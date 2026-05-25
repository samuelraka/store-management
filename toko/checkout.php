<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$keranjang = $_SESSION['keranjang'] ?? [];
if (empty($keranjang)) redirect('keranjang.php', 'Keranjang kosong', 'error');

$total = array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $keranjang));

// Ambil profil pembeli untuk pre-fill alamat
$stmt = $pdo->prepare("SELECT * FROM profil_pembeli WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil_pembeli = $stmt->fetch();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_penerima  = bersihkan($_POST['nama_penerima'] ?? '');
    $no_telepon     = bersihkan($_POST['no_telepon'] ?? '');
    $alamat_lengkap = bersihkan($_POST['alamat_lengkap'] ?? '');
    $kota_tujuan    = bersihkan($_POST['kota_tujuan'] ?? '');
    $catatan        = bersihkan($_POST['catatan'] ?? '');

    if (empty($nama_penerima) || empty($no_telepon) || empty($alamat_lengkap)) {
        $error = 'Nama, no. telepon, dan alamat wajib diisi';
    } else {
        try {
            $pdo->beginTransaction();

            $no_pesanan = generateNoPesanan($pdo);

            // Insert pesanan
            $stmt = $pdo->prepare(
                "INSERT INTO pesanan (user_id, no_pesanan, total_harga, status, catatan)
                 VALUES (?, ?, ?, 'menunggu', ?)"
            );
            $stmt->execute([$user['id'], $no_pesanan, $total, $catatan]);
            $pesanan_id = $pdo->lastInsertId();

            // Insert detail pesanan
            foreach ($keranjang as $item) {
                $pdo->prepare(
                    "INSERT INTO pesanan_detail
                     (pesanan_id, barang_id, jumlah, harga_jual, subtotal)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $pesanan_id,
                    $item['barang_id'],
                    $item['qty'],
                    $item['harga'],
                    $item['harga'] * $item['qty']
                ]);

                // Catat mutasi stok (stok dikurangi saat pesanan dibuat)
                $stok_stmt = $pdo->prepare("SELECT stok FROM barang WHERE id = ?");
                $stok_stmt->execute([$item['barang_id']]);
                $stok_sebelum = (int)$stok_stmt->fetchColumn();
                $stok_sesudah = $stok_sebelum - $item['qty'];

                $pdo->prepare(
                    "UPDATE barang SET stok = stok - ? WHERE id = ?"
                )->execute([$item['qty'], $item['barang_id']]);

                $pdo->prepare(
                    "INSERT INTO stok_mutasi
                     (barang_id, user_id, tipe, jumlah, stok_sebelum,
                      stok_sesudah, keterangan)
                     VALUES (?, ?, 'pesanan', ?, ?, ?, ?)"
                )->execute([
                    $item['barang_id'], $user['id'],
                    $item['qty'], $stok_sebelum, $stok_sesudah,
                    'Pesanan online: ' . $no_pesanan
                ]);
            }

            // Insert pengiriman
            $pdo->prepare(
                "INSERT INTO pengiriman
                 (pesanan_id, nama_penerima, no_telepon,
                  alamat_lengkap, kota_tujuan, status)
                 VALUES (?, ?, ?, ?, ?, 'menunggu')"
            )->execute([
                $pesanan_id, $nama_penerima, $no_telepon,
                $alamat_lengkap, $kota_tujuan
            ]);

            $pdo->commit();

            // Kosongkan keranjang
            $_SESSION['keranjang'] = [];

            redirect(
                'pesanan/detail.php?no=' . $no_pesanan,
                'Pesanan berhasil dibuat! No: ' . $no_pesanan
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan, coba lagi';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css//toko/checkout.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-3">
                <a href="keranjang.php" class="nav-icon">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <a class="navbar-brand mb-0" href="index.php">
                    <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?>
                </a>
            </div>
            <span style="font-size:.875rem;color:#6b7280;font-weight:600">
                Checkout
            </span>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            <!-- Form Pengiriman -->
            <div class="col-md-7">
                <div class="form-card">
                    <div class="section-title">
                        <i class="bi bi-geo-alt me-1"></i>Alamat Pengiriman
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                            <input type="text" name="nama_penerima"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['nama_penerima'] ?? $user['nama']) ?>"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                            <input type="text" name="no_telepon"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['no_telepon'] ?? $user['no_telepon'] ?? '') ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea name="alamat_lengkap" class="form-control"
                                      rows="3" required
                                      placeholder="Nama jalan, nomor rumah, RT/RW..."><?= htmlspecialchars($_POST['alamat_lengkap'] ?? $profil_pembeli['alamat'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Kota Tujuan</label>
                            <input type="text" name="kota_tujuan"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['kota_tujuan'] ?? '') ?>"
                                   placeholder="Nama kota/kabupaten">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan untuk Toko</label>
                            <textarea name="catatan" class="form-control"
                                      rows="2"
                                      placeholder="Opsional — misal: warna, ukuran, preferensi lain..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ringkasan Order -->
            <div class="col-md-5">
                <div class="form-card">
                    <div class="section-title">
                        <i class="bi bi-bag me-1"></i>Ringkasan Pesanan
                    </div>

                    <?php foreach ($keranjang as $item): ?>
                    <div class="order-item">
                        <div class="order-img">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div style="font-weight:600;font-size:.875rem">
                                <?= htmlspecialchars($item['nama']) ?>
                            </div>
                            <div style="font-size:.78rem;color:#6b7280">
                                <?= $item['qty'] ?> x <?= formatRupiah($item['harga']) ?>
                            </div>
                        </div>
                        <div style="font-weight:700;font-size:.875rem">
                            <?= formatRupiah($item['harga'] * $item['qty']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="summary-box mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:.875rem;color:#6b7280">
                                Total Belanja
                            </span>
                            <span style="font-weight:700;color:#16a34a;font-size:1rem">
                                <?= formatRupiah($total) ?>
                            </span>
                        </div>
                        <div style="font-size:.75rem;color:#6b7280">
                            <i class="bi bi-info-circle me-1"></i>
                            Pembayaran dilakukan saat barang diterima (COD)
                        </div>
                    </div>

                    <button type="submit" class="btn-pesan mt-3">
                        <i class="bi bi-bag-check me-2"></i>
                        Buat Pesanan
                    </button>
                    <a href="keranjang.php"
                       style="display:block;text-align:center;margin-top:10px;
                              font-size:.85rem;color:#6b7280;text-decoration:none">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Keranjang
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>