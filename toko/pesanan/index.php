<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$status = $_GET['status'] ?? '';

$where  = "WHERE p.user_id = ?";
$params = [$user['id']];

if ($status) {
    $where   .= " AND p.status = ?";
    $params[] = $status;
}

$stmt = $pdo->prepare(
    "SELECT p.*,
            COUNT(pd.id) as total_item,
            SUM(pd.jumlah) as total_qty
     FROM pesanan p
     LEFT JOIN pesanan_detail pd ON p.id = pd.pesanan_id
     $where
     GROUP BY p.id
     ORDER BY p.created_at DESC"
);
$stmt->execute($params);
$pesanan_list = $stmt->fetchAll();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
$keranjang_count = array_sum(array_column($_SESSION['keranjang'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/toko/pesanan/index.css">
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
                <a href="../keranjang.php" class="nav-icon">
                    <i class="bi bi-cart3"></i>
                    <?php if ($keranjang_count > 0): ?>
                    <span class="nav-badge"><?= $keranjang_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="../profil/index.php" class="nav-icon">
                    <i class="bi bi-person"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <h5 style="font-weight:700;color:#111827;margin-bottom:4px">Pesanan Saya</h5>
    <p style="color:#6b7280;font-size:.875rem;margin-bottom:0">
        Riwayat semua pesanan online kamu
    </p>

    <!-- Status tabs -->
    <div class="status-tabs">
        <?php
        $statuses = [
            ''           => 'Semua',
            'menunggu'   => 'Menunggu',
            'diproses'   => 'Diproses',
            'dikirim'    => 'Dikirim',
            'selesai'    => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
        foreach ($statuses as $val => $label):
        ?>
        <a href="index.php<?= $val ? '?status='.$val : '' ?>"
           class="status-tab <?= $status === $val ? 'active' : '' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php flashPesan(); ?>

    <?php if (empty($pesanan_list)): ?>
        <div class="pesanan-card">
            <div class="empty-state">
                <i class="bi bi-bag-x"></i>
                <p style="font-weight:600;color:#374151">Belum ada pesanan</p>
                <a href="../index.php" class="btn btn-primary btn-sm">
                    Mulai Belanja
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pesanan_list as $p): ?>
        <div class="pesanan-card">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <div style="font-weight:700;color:#111827;font-size:.9rem">
                        <?= htmlspecialchars($p['no_pesanan']) ?>
                    </div>
                    <div style="font-size:.78rem;color:#9ca3af;margin-top:2px">
                        <?= formatTanggalJam($p['created_at']) ?>
                    </div>
                </div>
                <span class="badge-status badge-<?= $p['status'] ?>">
                    <?= ucfirst($p['status']) ?>
                </span>
            </div>

            <div class="d-flex gap-4 mt-3 flex-wrap"
                 style="font-size:.85rem;color:#6b7280">
                <div>
                    <i class="bi bi-box-seam me-1"></i>
                    <?= $p['total_item'] ?> jenis,
                    <?= number_format($p['total_qty']) ?> item
                </div>
                <div>
                    <i class="bi bi-cash me-1"></i>
                    <strong style="color:#111827">
                        <?= formatRupiah($p['total_harga']) ?>
                    </strong>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <a href="detail.php?no=<?= htmlspecialchars($p['no_pesanan']) ?>"
                   class="btn-detail">
                    <i class="bi bi-eye me-1"></i>Lihat Detail
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>