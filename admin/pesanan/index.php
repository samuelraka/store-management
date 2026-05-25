<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$status = $_GET['status'] ?? '';
$search = bersihkan($_GET['search'] ?? '');
$dari   = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if ($status) {
    $where   .= " AND p.status = ?";
    $params[] = $status;
}
if ($search) {
    $where   .= " AND (p.no_pesanan LIKE ? OR u.nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dari) {
    $where   .= " AND DATE(p.created_at) >= ?";
    $params[] = $dari;
}
if ($sampai) {
    $where   .= " AND DATE(p.created_at) <= ?";
    $params[] = $sampai;
}

$stmt = $pdo->prepare(
    "SELECT p.*, u.nama as nama_pembeli, u.no_telepon,
            COUNT(pd.id) as total_item,
            SUM(pd.jumlah) as total_qty,
            pg.status as status_kirim,
            pg.kurir, pg.no_resi
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     LEFT JOIN pesanan_detail pd ON p.id = pd.pesanan_id
     LEFT JOIN pengiriman pg ON p.id = pg.pesanan_id
     $where
     GROUP BY p.id
     ORDER BY p.created_at DESC"
);
$stmt->execute($params);
$pesanan_list = $stmt->fetchAll();

// Hitung per status untuk badge
$stmt_count = $pdo->query(
    "SELECT status, COUNT(*) as total FROM pesanan GROUP BY status"
);
$count_status = array_column($stmt_count->fetchAll(), 'total', 'status');

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Online</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/pesanan/index.css">
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
        <a href="../pembelian/index.php"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="../stok/histori.php"><i class="bi bi-clock-history"></i> Histori Stok</a>
        <div class="menu-label">Penjualan</div>
        <a href="index.php" class="active">
            <i class="bi bi-bag-check"></i> Pesanan Online
            <?php if (!empty($count_status['menunggu'])): ?>
                <span class="badge-notif"><?= $count_status['menunggu'] ?></span>
            <?php endif; ?>
        </a>
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
        <h5>Pesanan Online</h5>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- Status Tabs -->
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
                $cnt = $val ? ($count_status[$val] ?? 0) : array_sum($count_status);
            ?>
            <a href="index.php<?= $val ? '?status='.$val : '' ?>"
               class="status-tab <?= $status === $val ? 'active' : '' ?>">
                <?= $label ?>
                <?php if ($cnt > 0): ?>
                    <span style="margin-left:4px;background:rgba(0,0,0,.1);
                          padding:1px 7px;border-radius:10px;font-size:.7rem">
                        <?= $cnt ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <?php if ($status): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="No. pesanan / nama pembeli..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="dari" class="form-control form-control-sm"
                           value="<?= $dari ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="sampai" class="form-control form-control-sm"
                           value="<?= $sampai ?>">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
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
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;
                        font-size:.85rem;color:#6b7280">
                <strong><?= count($pesanan_list) ?></strong> pesanan ditemukan
            </div>
            <?php if (empty($pesanan_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-x d-block mb-2" style="font-size:2.5rem"></i>
                    Belum ada pesanan
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Pembeli</th>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Status Pesanan</th>
                                <th>Status Kirim</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesanan_list as $p): ?>
                            <tr>
                                <td>
                                    <code style="background:#f3f4f6;padding:2px 8px;
                                          border-radius:4px;font-size:.78rem">
                                        <?= htmlspecialchars($p['no_pesanan']) ?>
                                    </code>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:.85rem">
                                        <?= htmlspecialchars($p['nama_pembeli']) ?>
                                    </div>
                                    <div style="font-size:.75rem;color:#9ca3af">
                                        <?= htmlspecialchars($p['no_telepon'] ?? '') ?>
                                    </div>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= $p['total_item'] ?> jenis
                                    <span style="color:#9ca3af">
                                        (<?= number_format($p['total_qty']) ?> pcs)
                                    </span>
                                </td>
                                <td style="font-weight:700">
                                    <?= formatRupiah($p['total_harga']) ?>
                                </td>
                                <td>
                                    <span class="badge-status badge-<?= $p['status'] ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status_kirim']): ?>
                                    <span class="badge-status badge-<?= $p['status_kirim'] ?>">
                                        <?= ucfirst($p['status_kirim']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span style="color:#9ca3af;font-size:.78rem">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.78rem;color:#6b7280;white-space:nowrap">
                                    <?= formatTanggalJam($p['created_at']) ?>
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