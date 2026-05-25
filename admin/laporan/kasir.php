<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

// Rekap per kasir
$stmt = $pdo->prepare(
    "SELECT u.id, u.nama, u.email, u.foto, u.is_aktif,
            COUNT(t.id) as total_transaksi,
            COALESCE(SUM(t.total_harga), 0) as total_pendapatan,
            COALESCE(SUM(t.total_margin), 0) as total_margin,
            COALESCE(SUM(t.bayar - t.kembalian), 0) as total_diterima,
            MIN(t.created_at) as pertama_transaksi,
            MAX(t.created_at) as terakhir_transaksi
     FROM users u
     LEFT JOIN transaksi t ON u.id = t.user_id
         AND t.tanggal_terjual BETWEEN ? AND ?
         AND t.status = 'selesai'
     WHERE u.role = 'kasir'
     GROUP BY u.id
     ORDER BY total_pendapatan DESC"
);
$stmt->execute([$dari, $sampai]);
$kasir_list = $stmt->fetchAll();

// Detail transaksi per kasir (kalau ada filter kasir)
$kasir_id    = (int)($_GET['kasir_id'] ?? 0);
$detail_list = [];

if ($kasir_id) {
    $stmt = $pdo->prepare(
        "SELECT t.*,
                COUNT(td.id) as total_item,
                SUM(td.jumlah) as total_qty
         FROM transaksi t
         LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
         WHERE t.user_id = ?
         AND t.tanggal_terjual BETWEEN ? AND ?
         AND t.status = 'selesai'
         GROUP BY t.id
         ORDER BY t.created_at DESC"
    );
    $stmt->execute([$kasir_id, $dari, $sampai]);
    $detail_list = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
    $stmt->execute([$kasir_id]);
    $nama_kasir_filter = $stmt->fetchColumn();
}

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();

// Grand total
$grand_transaksi  = array_sum(array_column($kasir_list, 'total_transaksi'));
$grand_pendapatan = array_sum(array_column($kasir_list, 'total_pendapatan'));
$grand_margin     = array_sum(array_column($kasir_list, 'total_margin'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kasir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/laporan/kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<aside class="sidebar no-print">
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
        <a href="../pesanan/index.php"><i class="bi bi-bag-check"></i> Pesanan Online</a>
        <div class="menu-label">Laporan</div>
        <a href="penjualan.php"><i class="bi bi-bar-chart-line"></i> Lap. Penjualan</a>
        <a href="produk.php"><i class="bi bi-pie-chart"></i> Lap. Produk</a>
        <a href="kasir.php" class="active"><i class="bi bi-person-badge"></i> Lap. Kasir</a>
        <div class="menu-label">Pengaturan</div>
        <a href="../kasir/index.php"><i class="bi bi-people"></i> Kelola Kasir</a>
        <a href="../profil/index.php"><i class="bi bi-gear"></i> Profil Usaha</a>
        <a href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar no-print">
        <h5>Laporan Kasir</h5>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
    </div>

    <div class="content">

        <!-- Filter -->
        <div class="filter-bar no-print">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" class="form-control form-control-sm"
                           value="<?= $dari ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="form-control form-control-sm"
                           value="<?= $sampai ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Filter Kasir</label>
                    <select name="kasir_id" class="form-select form-select-sm">
                        <option value="">Semua Kasir</option>
                        <?php foreach ($kasir_list as $k): ?>
                        <option value="<?= $k['id'] ?>"
                            <?= $kasir_id == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                    <a href="?dari=<?= date('Y-m-01') ?>&sampai=<?= date('Y-m-d') ?>"
                       class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <!-- Info Periode -->
        <div style="font-size:.85rem;color:#6b7280;margin-bottom:16px">
            Periode: <strong><?= formatTanggal($dari) ?></strong>
            — <strong><?= formatTanggal($sampai) ?></strong>
            <?php if ($kasir_id && isset($nama_kasir_filter)): ?>
                · Kasir: <strong><?= htmlspecialchars($nama_kasir_filter) ?></strong>
            <?php endif; ?>
        </div>

        <!-- Kartu per Kasir -->
        <div class="row g-3 mb-4">
            <?php foreach ($kasir_list as $k): ?>
            <div class="col-md-6">
                <div class="kasir-card">
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <div class="kasir-avatar">
                            <?php if ($k['foto']): ?>
                                <img src="../../assets/img/uploads/<?= htmlspecialchars($k['foto']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($k['nama'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div style="font-weight:700;color:#111827">
                                <?= htmlspecialchars($k['nama']) ?>
                            </div>
                            <div style="font-size:.78rem;color:#6b7280">
                                <?= htmlspecialchars($k['email']) ?>
                            </div>
                            <div style="margin-top:4px">
                                <?php if ($k['is_aktif']): ?>
                                    <span style="background:#dcfce7;color:#166534;padding:2px 8px;
                                          border-radius:10px;font-size:.7rem;font-weight:600">Aktif</span>
                                <?php else: ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;
                                          border-radius:10px;font-size:.7rem;font-weight:600">Nonaktif</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="?dari=<?= $dari ?>&sampai=<?= $sampai ?>&kasir_id=<?= $k['id'] ?>"
                           class="btn btn-sm btn-outline-primary no-print">
                            <i class="bi bi-eye me-1"></i>Detail
                        </a>
                    </div>

                    <div class="row g-2">
                        <div class="col-4">
                            <div class="stat-mini">
                                <div style="font-size:1.1rem;font-weight:700;color:#111827">
                                    <?= number_format($k['total_transaksi']) ?>
                                </div>
                                <div style="font-size:.7rem;color:#9ca3af">Transaksi</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-mini">
                                <div style="font-size:.85rem;font-weight:700;color:#2563eb">
                                    <?= formatRupiah($k['total_pendapatan']) ?>
                                </div>
                                <div style="font-size:.7rem;color:#9ca3af">Pendapatan</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-mini">
                                <div style="font-size:.85rem;font-weight:700;color:#16a34a">
                                    <?= formatRupiah($k['total_margin']) ?>
                                </div>
                                <div style="font-size:.7rem;color:#9ca3af">Margin</div>
                            </div>
                        </div>
                    </div>

                    <?php if ($k['total_transaksi'] > 0): ?>
                    <div style="font-size:.75rem;color:#9ca3af;margin-top:10px">
                        <i class="bi bi-clock me-1"></i>
                        Terakhir transaksi:
                        <?= formatTanggalJam($k['terakhir_transaksi']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Ringkasan Total -->
        <div class="card-box mb-4">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700">Ringkasan Semua Kasir</h6>
            </div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Kasir</th>
                        <th>Total Transaksi</th>
                        <th>Total Pendapatan</th>
                        <th>Total Margin</th>
                        <th>Rata-rata/Transaksi</th>
                        <th>Kontribusi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kasir_list as $k):
                        $avg = $k['total_transaksi'] > 0
                            ? $k['total_pendapatan'] / $k['total_transaksi']
                            : 0;
                        $kontribusi = $grand_pendapatan > 0
                            ? round(($k['total_pendapatan'] / $grand_pendapatan) * 100, 1)
                            : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600">
                                <?= htmlspecialchars($k['nama']) ?>
                            </div>
                        </td>
                        <td style="font-weight:700">
                            <?= number_format($k['total_transaksi']) ?>x
                        </td>
                        <td style="font-weight:600">
                            <?= formatRupiah($k['total_pendapatan']) ?>
                        </td>
                        <td style="color:#16a34a;font-weight:600">
                            <?= formatRupiah($k['total_margin']) ?>
                        </td>
                        <td style="color:#6b7280">
                            <?= formatRupiah($avg) ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="flex:1;background:#e5e7eb;border-radius:4px;height:6px">
                                    <div style="width:<?= $kontribusi ?>%;background:var(--primary);
                                                height:6px;border-radius:4px"></div>
                                </div>
                                <span style="font-size:.78rem;font-weight:600;min-width:36px">
                                    <?= $kontribusi ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td><?= number_format($grand_transaksi) ?>x</td>
                        <td><?= formatRupiah($grand_pendapatan) ?></td>
                        <td style="color:#16a34a"><?= formatRupiah($grand_margin) ?></td>
                        <td>—</td>
                        <td>100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Detail Transaksi Kasir Tertentu -->
        <?php if ($kasir_id && !empty($detail_list)): ?>
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700">
                    Detail Transaksi — <?= htmlspecialchars($nama_kasir_filter ?? '') ?>
                </h6>
            </div>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Waktu</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Bayar</th>
                            <th>Kembalian</th>
                            <th>Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_list as $t): ?>
                        <tr>
                            <td>
                                <code style="background:#f3f4f6;padding:2px 6px;
                                      border-radius:4px;font-size:.78rem">
                                    <?= htmlspecialchars($t['no_transaksi']) ?>
                                </code>
                            </td>
                            <td style="font-size:.78rem;color:#6b7280;white-space:nowrap">
                                <?= formatTanggalJam($t['created_at']) ?>
                            </td>
                            <td style="font-size:.82rem">
                                <?= $t['total_item'] ?> jenis
                                (<?= number_format($t['total_qty']) ?> pcs)
                            </td>
                            <td style="font-weight:600">
                                <?= formatRupiah($t['total_harga']) ?>
                            </td>
                            <td><?= formatRupiah($t['bayar']) ?></td>
                            <td style="color:#6b7280">
                                <?= formatRupiah($t['kembalian']) ?>
                            </td>
                            <td style="color:#16a34a;font-weight:600">
                                <?= formatRupiah($t['total_margin']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>