<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$search  = bersihkan($_GET['search'] ?? '');
$kat_id  = (int)($_GET['kategori'] ?? 0);
$sort    = $_GET['sort'] ?? 'terbaru';

$where  = "WHERE b.stok > 0";
$params = [];

if ($search) {
    $where   .= " AND b.nama_barang LIKE ?";
    $params[] = "%$search%";
}
if ($kat_id) {
    $where   .= " AND b.kategori_id = ?";
    $params[] = $kat_id;
}

$order = match($sort) {
    'termurah' => "ORDER BY b.harga_jual ASC",
    'termahal' => "ORDER BY b.harga_jual DESC",
    'terlaris' => "ORDER BY total_terjual DESC",
    default    => "ORDER BY b.created_at DESC"
};

$stmt = $pdo->prepare(
    "SELECT b.*, k.nama as kategori,
            COALESCE(SUM(td.jumlah), 0) as total_terjual
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     LEFT JOIN transaksi_detail td ON b.id = td.barang_id
     $where
     GROUP BY b.id
     $order"
);
$stmt->execute($params);
$barang_list = $stmt->fetchAll();

$kategoris = $pdo->query(
    "SELECT k.*, COUNT(b.id) as total
     FROM kategori_barang k
     LEFT JOIN barang b ON k.id = b.kategori_id AND b.stok > 0
     GROUP BY k.id
     ORDER BY k.nama"
)->fetchAll();

// Jumlah keranjang session
$keranjang_count = array_sum(array_column($_SESSION['keranjang'] ?? [], 'qty'));

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog — <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/toko/index.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100 gap-3">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop me-1"></i>
                <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?>
            </a>

            <!-- Search -->
            <form method="GET" class="flex-grow-1" style="max-width:400px">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control"
                           placeholder="Cari produk..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <!-- Nav icons -->
            <div class="d-flex gap-2 align-items-center">
                <a href="keranjang.php" class="nav-icon">
                    <i class="bi bi-cart3" style="font-size:1.1rem"></i>
                    <?php if ($keranjang_count > 0): ?>
                    <span class="nav-badge"><?= $keranjang_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="pesanan/index.php" class="nav-icon">
                    <i class="bi bi-bag-check" style="font-size:1.1rem"></i>
                </a>
                <a href="profil/index.php" class="nav-icon">
                    <i class="bi bi-person" style="font-size:1.1rem"></i>
                </a>
                <a href="../auth/logout.php" class="nav-icon">
                    <i class="bi bi-box-arrow-right" style="font-size:1.1rem"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="row g-4">

        <!-- Sidebar Filter -->
        <div class="col-md-3">
            <div class="sidebar-filter">
                <div class="filter-title">Kategori</div>
                <a href="index.php<?= $search ? '?search='.$search : '' ?>"
                   class="kat-item <?= !$kat_id ? 'active' : '' ?>">
                    <span>Semua Produk</span>
                    <span class="kat-count"><?= count($barang_list) ?></span>
                </a>
                <?php foreach ($kategoris as $k): ?>
                <a href="index.php?kategori=<?= $k['id'] ?><?= $search ? '&search='.$search : '' ?>"
                   class="kat-item <?= $kat_id == $k['id'] ? 'active' : '' ?>">
                    <span><?= htmlspecialchars($k['nama']) ?></span>
                    <span class="kat-count"><?= $k['total'] ?></span>
                </a>
                <?php endforeach; ?>

                <hr style="border-color:#f3f4f6;margin:16px 0">
                <div class="filter-title">Urutkan</div>
                <?php
                $sorts = [
                    'terbaru'  => 'Terbaru',
                    'terlaris' => 'Terlaris',
                    'termurah' => 'Harga Terendah',
                    'termahal' => 'Harga Tertinggi',
                ];
                foreach ($sorts as $val => $label):
                    $url = 'index.php?sort=' . $val;
                    if ($kat_id) $url .= '&kategori=' . $kat_id;
                    if ($search) $url .= '&search=' . $search;
                ?>
                <a href="<?= $url ?>"
                   class="kat-item <?= $sort === $val ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Produk Grid -->
        <div class="col-md-9">
            <?php flashPesan(); ?>

            <div class="sort-bar">
                <span style="font-size:.85rem;color:#6b7280">
                    <strong><?= count($barang_list) ?></strong> produk ditemukan
                    <?php if ($search): ?>
                        untuk "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($barang_list)): ?>
                <div class="empty-state">
                    <i class="bi bi-box-seam"></i>
                    <p>Produk tidak ditemukan</p>
                    <a href="index.php" class="btn btn-primary btn-sm">
                        Lihat Semua Produk
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($barang_list as $b): ?>
                    <div class="col-6 col-md-4">
                        <div class="produk-card">
                            <a href="detail.php?id=<?= $b['id'] ?>"
                               style="text-decoration:none">
                                <div class="produk-img">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </a>
                            <div class="produk-body">
                                <?php if ($b['kategori']): ?>
                                <span class="produk-kat">
                                    <?= htmlspecialchars($b['kategori']) ?>
                                </span>
                                <?php endif; ?>
                                <a href="detail.php?id=<?= $b['id'] ?>"
                                   style="text-decoration:none">
                                    <div class="produk-nama">
                                        <?= htmlspecialchars($b['nama_barang']) ?>
                                    </div>
                                </a>
                                <div class="produk-harga">
                                    <?= formatRupiah($b['harga_jual']) ?>
                                    <span style="font-size:.75rem;color:#9ca3af;
                                          font-weight:400">/ <?= $b['satuan'] ?></span>
                                </div>
                                <div class="produk-stok">
                                    <?php if ($b['stok'] <= $b['stok_minimal']): ?>
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span class="stok-warn">
                                            Sisa <?= $b['stok'] ?> <?= $b['satuan'] ?>
                                        </span>
                                    <?php else: ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span class="stok-ok">Tersedia</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" action="keranjang.php">
                                    <input type="hidden" name="aksi" value="tambah">
                                    <input type="hidden" name="barang_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn-beli">
                                        <i class="bi bi-cart-plus me-1"></i>
                                        Tambah ke Keranjang
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>