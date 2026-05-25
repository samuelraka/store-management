<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

// Filter
$search  = bersihkan($_GET['search'] ?? '');
$tipe    = $_GET['tipe'] ?? '';
$dari    = $_GET['dari'] ?? '';
$sampai  = $_GET['sampai'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if ($search) {
    $where   .= " AND b.nama_barang LIKE ?";
    $params[] = "%$search%";
}
if ($tipe) {
    $where   .= " AND sm.tipe = ?";
    $params[] = $tipe;
}
if ($dari) {
    $where   .= " AND DATE(sm.created_at) >= ?";
    $params[] = $dari;
}
if ($sampai) {
    $where   .= " AND DATE(sm.created_at) <= ?";
    $params[] = $sampai;
}

$stmt = $pdo->prepare(
    "SELECT sm.*, b.nama_barang, b.kode_barang, b.satuan,
            u.nama as nama_user
     FROM stok_mutasi sm
     JOIN barang b ON sm.barang_id = b.id
     JOIN users u ON sm.user_id = u.id
     $where
     ORDER BY sm.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$mutasi_list = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([
    $_SESSION['role'] === 'superadmin'
        ? $user['id']
        : $pdo->query("SELECT id FROM users WHERE role='superadmin' LIMIT 1")->fetchColumn()
]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Stok</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/stok/style.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-2"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?></small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Utama</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="menu-label">Katalog</div>
        <a href="../barang/index.php"><i class="bi bi-box-seam"></i> Data Barang</a>
        <a href="../kategori/index.php"><i class="bi bi-tags"></i> Kategori</a>
        <a href="../supplier/index.php"><i class="bi bi-truck"></i> Supplier</a>
        <div class="menu-label">Inventori</div>
        <a href="../pembelian/index.php"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="histori.php" class="active"><i class="bi bi-clock-history"></i> Histori Stok</a>
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
        <h5>Histori Pergerakan Stok</h5>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Cari Barang</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Nama barang..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Tipe</label>
                    <select name="tipe" class="form-select form-select-sm">
                        <option value="">Semua Tipe</option>
                        <option value="masuk"     <?= $tipe==='masuk'    ?'selected':'' ?>>Masuk</option>
                        <option value="keluar"    <?= $tipe==='keluar'   ?'selected':'' ?>>Keluar</option>
                        <option value="penjualan" <?= $tipe==='penjualan'?'selected':'' ?>>Penjualan</option>
                        <option value="pesanan"   <?= $tipe==='pesanan'  ?'selected':'' ?>>Pesanan</option>
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
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="histori.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Legenda -->
        <div class="d-flex gap-2 flex-wrap mb-3">
            <span class="badge-tipe tipe-masuk">Masuk — stok bertambah dari supplier</span>
            <span class="badge-tipe tipe-penjualan">Penjualan — stok berkurang via POS</span>
            <span class="badge-tipe tipe-pesanan">Pesanan — stok berkurang via order online</span>
            <span class="badge-tipe tipe-keluar">Keluar — stok berkurang manual</span>
        </div>

        <!-- Tabel -->
        <div class="card-box">
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;
                        font-size:.85rem;color:#6b7280">
                Menampilkan <strong><?= count($mutasi_list) ?></strong> mutasi terakhir
            </div>
            <?php if (empty($mutasi_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clock-history d-block mb-2" style="font-size:2.5rem"></i>
                    Belum ada histori pergerakan stok
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Stok Sebelum</th>
                                <th>Stok Sesudah</th>
                                <th>Perubahan</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mutasi_list as $m):
                                $masuk = in_array($m['tipe'], ['masuk']);
                                $selisih = $m['stok_sesudah'] - $m['stok_sebelum'];
                            ?>
                            <tr>
                                <td style="font-size:.78rem;color:#6b7280;white-space:nowrap">
                                    <?= formatTanggalJam($m['created_at']) ?>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:.85rem">
                                        <?= htmlspecialchars($m['nama_barang']) ?>
                                    </div>
                                    <div style="font-size:.72rem;color:#9ca3af">
                                        <?= htmlspecialchars($m['kode_barang']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-tipe tipe-<?= $m['tipe'] ?>">
                                        <?= ucfirst($m['tipe']) ?>
                                    </span>
                                </td>
                                <td style="font-weight:600">
                                    <?= number_format($m['jumlah']) ?>
                                    <span style="color:#9ca3af;font-size:.78rem">
                                        <?= htmlspecialchars($m['satuan']) ?>
                                    </span>
                                </td>
                                <td style="color:#6b7280">
                                    <?= number_format($m['stok_sebelum']) ?>
                                </td>
                                <td style="font-weight:600">
                                    <?= number_format($m['stok_sesudah']) ?>
                                </td>
                                <td>
                                    <span class="stok-change <?= $selisih >= 0 ? 'stok-plus' : 'stok-minus' ?>">
                                        <?= $selisih >= 0 ? '+' : '' ?><?= number_format($selisih) ?>
                                    </span>
                                </td>
                                <td style="font-size:.8rem;color:#6b7280;max-width:160px">
                                    <div style="white-space:nowrap;overflow:hidden;
                                                text-overflow:ellipsis;max-width:150px">
                                        <?= htmlspecialchars($m['keterangan'] ?? '-') ?>
                                    </div>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= htmlspecialchars($m['nama_user']) ?>
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