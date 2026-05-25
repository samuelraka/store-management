<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

// Filter
$search    = bersihkan($_GET['search'] ?? '');
$supplier_filter = (int)($_GET['supplier'] ?? 0);
$dari      = $_GET['dari'] ?? '';
$sampai    = $_GET['sampai'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if ($search) {
    $where   .= " AND p.no_pembelian LIKE ?";
    $params[] = "%$search%";
}
if ($supplier_filter) {
    $where   .= " AND p.supplier_id = ?";
    $params[] = $supplier_filter;
}
if ($dari) {
    $where   .= " AND p.tanggal_masuk >= ?";
    $params[] = $dari;
}
if ($sampai) {
    $where   .= " AND p.tanggal_masuk <= ?";
    $params[] = $sampai;
}

$stmt = $pdo->prepare(
    "SELECT p.*, s.nama_supplier, u.nama as nama_user,
            COUNT(pd.id) as total_item,
            SUM(pd.jumlah) as total_qty
     FROM pembelian p
     LEFT JOIN supplier s ON p.supplier_id = s.id
     LEFT JOIN users u ON p.user_id = u.id
     LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
     $where
     GROUP BY p.id
     ORDER BY p.created_at DESC"
);
$stmt->execute($params);
$pembelian_list = $stmt->fetchAll();

// Total nilai pembelian
$stmt2 = $pdo->prepare(
    "SELECT COALESCE(SUM(p.total_harga), 0)
     FROM pembelian p $where"
);
$stmt2->execute($params);
$total_nilai = $stmt2->fetchColumn();

$suppliers = $pdo->query(
    "SELECT * FROM supplier WHERE is_aktif = 1 ORDER BY nama_supplier"
)->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Masuk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/pembelian/index.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-2"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?> &middot; Super Admin</small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Utama</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="menu-label">Katalog</div>
        <a href="../barang/index.php"><i class="bi bi-box-seam"></i> Data Barang</a>
        <a href="../kategori/index.php"><i class="bi bi-tags"></i> Kategori</a>
        <a href="../supplier/index.php"><i class="bi bi-truck"></i> Supplier</a>
        <div class="menu-label">Inventori</div>
        <a href="index.php" class="active"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="../stok/histori.php"><i class="bi bi-clock-history"></i> Histori Stok</a>
        <div class="menu-label">Penjualan</div>
        <a href="../pesanan/index.php"><i class="bi bi-bag-check"></i> Pesanan Online</a>
        <div class="menu-label">Laporan</div>
        <a href="../laporan/penjualan.php"><i class="bi bi-bar-chart-line"></i> Lap. Penjualan</a>
        <a href="../laporan/produk.php"><i class="bi bi-pie-chart"></i> Lap. Produk</a>
        <a href="../laporan/kasir.php"><i class="bi bi-person-badge"></i> Lap. Kasir</a>
        <div class="menu-label">Pengaturan</div>
        <a href="../kasir/index.php"><i class="bi bi-people"></i> Kelola Kasir</a>
        <a href="../profil/index.php"><i class="bi bi-gear"></i> Profil Usaha</a>
        <a href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <h5>Barang Masuk</h5>
        <a href="tambah.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Input Barang Masuk
        </a>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- Stat -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.8rem;color:#6b7280">Total Transaksi</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= number_format(count($pembelian_list)) ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">pembelian ditemukan</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.8rem;color:#6b7280">Total Nilai Pembelian</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= formatRupiah($total_nilai) ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">modal yang dikeluarkan</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.8rem;color:#6b7280">Supplier Aktif</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= count($suppliers) ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">supplier tersedia</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">No. Pembelian</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Cari no. pembelian..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Supplier</label>
                    <select name="supplier" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"
                            <?= $supplier_filter == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nama_supplier']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" class="form-control form-control-sm"
                           value="<?= $dari ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="form-control form-control-sm"
                           value="<?= $sampai ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabel -->
        <div class="card-box">
            <?php if (empty($pembelian_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-arrow-in-down d-block mb-2" style="font-size:2.5rem"></i>
                    <p class="mb-2">Belum ada data barang masuk</p>
                    <a href="tambah.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Input Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>No. Pembelian</th>
                                <th>Tanggal Masuk</th>
                                <th>Supplier</th>
                                <th>Total Item</th>
                                <th>Total Qty</th>
                                <th>Total Harga</th>
                                <th>Diinput Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pembelian_list as $p): ?>
                            <tr>
                                <td>
                                    <code style="background:#f3f4f6;padding:2px 8px;
                                          border-radius:4px;font-size:.8rem">
                                        <?= htmlspecialchars($p['no_pembelian']) ?>
                                    </code>
                                </td>
                                <td><?= formatTanggal($p['tanggal_masuk']) ?></td>
                                <td style="font-weight:600">
                                    <?= htmlspecialchars($p['nama_supplier'] ?? '-') ?>
                                </td>
                                <td><?= number_format($p['total_item']) ?> jenis</td>
                                <td><?= number_format($p['total_qty']) ?> pcs</td>
                                <td style="font-weight:700">
                                    <?= formatRupiah($p['total_harga']) ?>
                                </td>
                                <td style="font-size:.82rem;color:#6b7280">
                                    <?= htmlspecialchars($p['nama_user']) ?>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= $p['id'] ?>"
                                       class="btn-aksi btn-detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>