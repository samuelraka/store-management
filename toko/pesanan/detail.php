<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$no = bersihkan($_GET['no'] ?? '');
if (!$no) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

$stmt = $pdo->prepare(
    "SELECT p.*, pg.nama_penerima, pg.no_telepon as telp_penerima,
            pg.alamat_lengkap, pg.kota_tujuan,
            pg.status as status_kirim, pg.kurir, pg.no_resi,
            pg.updated_at as update_kirim
     FROM pesanan p
     LEFT JOIN pengiriman pg ON p.id = pg.pesanan_id
     WHERE p.no_pesanan = ? AND p.user_id = ?"
);
$stmt->execute([$no, $user['id']]);
$pesanan = $stmt->fetch();
if (!$pesanan) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

$stmt = $pdo->prepare(
    "SELECT pd.*, b.nama_barang, b.kode_barang, b.satuan
     FROM pesanan_detail pd
     JOIN barang b ON pd.barang_id = b.id
     WHERE pd.pesanan_id = ?"
);
$stmt->execute([$pesanan['id']]);
$details = $stmt->fetchAll();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();

// Status timeline
$timeline = [
    'menunggu'   => ['label' => 'Pesanan Masuk',       'icon' => 'bi-hourglass'],
    'diproses'   => ['label' => 'Sedang Diproses',     'icon' => 'bi-gear'],
    'dikirim'    => ['label' => 'Dalam Pengiriman',    'icon' => 'bi-truck'],
    'selesai'    => ['label' => 'Pesanan Selesai',     'icon' => 'bi-check-circle'],
];
$status_order = array_keys($timeline);
$current_idx  = array_search($pesanan['status'], $status_order);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan <?= htmlspecialchars($no) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/toko/pesanan/detail.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="nav-icon">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <a class="navbar-brand mb-0" href="../index.php">
                    <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?>
                </a>
            </div>
            <span class="badge-status badge-<?= $pesanan['status'] ?>">
                <?= ucfirst($pesanan['status']) ?>
            </span>
        </div>
    </div>
</nav>

<div class="container py-4" style="max-width:680px">
    <?php flashPesan(); ?>

    <!-- Info Pesanan -->
    <div class="info-card">
        <div class="card-title">Info Pesanan</div>
        <div class="info-row">
            <span class="info-label">No. Pesanan</span>
            <span class="info-value">
                <code><?= htmlspecialchars($pesanan['no_pesanan']) ?></code>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Tanggal Pesan</span>
            <span class="info-value">
                <?= formatTanggalJam($pesanan['created_at']) ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Belanja</span>
            <span class="info-value" style="color:#16a34a;font-size:1rem">
                <?= formatRupiah($pesanan['total_harga']) ?>
            </span>
        </div>
        <?php if ($pesanan['catatan']): ?>
        <div class="info-row">
            <span class="info-label">Catatan</span>
            <span class="info-value"><?= htmlspecialchars($pesanan['catatan']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Timeline Status -->
    <?php if ($pesanan['status'] !== 'dibatalkan'): ?>
    <div class="info-card">
        <div class="card-title">Status Pesanan</div>
        <div class="timeline">
            <?php foreach ($timeline as $key => $tl):
                if ($current_idx === false) {
                    $state = 'pending';
                } elseif (array_search($key, $status_order) < $current_idx) {
                    $state = 'done';
                } elseif ($key === $pesanan['status']) {
                    $state = 'current';
                } else {
                    $state = 'pending';
                }
            ?>
            <div class="tl-item">
                <div class="tl-icon <?= $state ?>">
                    <i class="bi <?= $tl['icon'] ?>"></i>
                </div>
                <div class="tl-label <?= $state ?>">
                    <?= $tl['label'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info Pengiriman -->
    <?php if ($pesanan['nama_penerima']): ?>
    <div class="info-card">
        <div class="card-title">Info Pengiriman</div>
        <div class="info-row">
            <span class="info-label">Nama Penerima</span>
            <span class="info-value"><?= htmlspecialchars($pesanan['nama_penerima']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">No. Telepon</span>
            <span class="info-value"><?= htmlspecialchars($pesanan['telp_penerima']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Alamat</span>
            <span class="info-value" style="max-width:240px">
                <?= htmlspecialchars($pesanan['alamat_lengkap']) ?>
            </span>
        </div>
        <?php if ($pesanan['kota_tujuan']): ?>
        <div class="info-row">
            <span class="info-label">Kota Tujuan</span>
            <span class="info-value"><?= htmlspecialchars($pesanan['kota_tujuan']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($pesanan['status_kirim'] === 'dikirim' && $pesanan['no_resi']): ?>
        <div class="resi-box mt-3">
            <div style="font-size:.78rem;color:#1e40af;font-weight:600;margin-bottom:4px">
                <i class="bi bi-truck me-1"></i>Info Pengiriman
            </div>
            <div style="font-size:.875rem">
                Kurir: <strong><?= htmlspecialchars($pesanan['kurir']) ?></strong>
            </div>
            <div style="font-size:.875rem">
                No. Resi: <strong><?= htmlspecialchars($pesanan['no_resi']) ?></strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Detail Barang -->
    <div class="info-card">
        <div class="card-title">Detail Barang</div>
        <?php foreach ($details as $d): ?>
        <div class="item-row">
            <div class="item-img">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="flex-grow-1">
                <div style="font-weight:600;font-size:.875rem;color:#111827">
                    <?= htmlspecialchars($d['nama_barang']) ?>
                </div>
                <div style="font-size:.78rem;color:#6b7280">
                    <?= number_format($d['jumlah']) ?> <?= $d['satuan'] ?>
                    × <?= formatRupiah($d['harga_jual']) ?>
                </div>
            </div>
            <div style="font-weight:700;color:var(--primary);font-size:.875rem">
                <?= formatRupiah($d['subtotal']) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:space-between;
                    padding:12px 0 0;font-weight:700;font-size:1rem;
                    border-top:2px solid #f3f4f6;margin-top:4px">
            <span>Total</span>
            <span style="color:#16a34a"><?= formatRupiah($pesanan['total_harga']) ?></span>
        </div>
    </div>

    <div class="text-center">
        <a href="index.php"
           style="color:#6b7280;font-size:.875rem;text-decoration:none">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Pesanan
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>