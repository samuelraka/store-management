<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

// Laporan produk terlaris
$stmt = $pdo->prepare(
    "SELECT b.kode_barang, b.nama_barang, b.satuan,
            b.harga_beli, b.harga_jual, b.stok,
            k.nama as kategori,
            COALESCE(SUM(td.jumlah), 0) as total_terjual,
            COALESCE(SUM(td.subtotal), 0) as total_pendapatan,
            COALESCE(SUM(td.margin), 0) as total_margin
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     LEFT JOIN transaksi_detail td ON b.id = td.barang_id
     LEFT JOIN transaksi t ON td.transaksi_id = t.id
         AND t.tanggal_terjual BETWEEN ? AND ?
         AND t.status = 'selesai'
     GROUP BY b.id
     ORDER BY total_terjual DESC"
);
$stmt->execute([$dari, $sampai]);
$produk_list = $stmt->fetchAll();

// Ringkasan
$total_produk    = count($produk_list);
$total_terjual   = array_sum(array_column($produk_list, 'total_terjual'));
$total_pendapatan= array_sum(array_column($produk_list, 'total_pendapatan'));
$total_margin    = array_sum(array_column($produk_list, 'total_margin'));

// Top 5 untuk chart
$top5       = array_slice($produk_list, 0, 5);
$top5_label = array_map(fn($p) => substr($p['nama_barang'], 0, 15), $top5);
$top5_data  = array_map(fn($p) => (int)$p['total_terjual'], $top5);

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/laporan/produk.css">
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
        <a href="produk.php" class="active"><i class="bi bi-pie-chart"></i> Lap. Produk</a>
        <a href="kasir.php"><i class="bi bi-person-badge"></i> Lap. Kasir</a>
        <div class="menu-label">Pengaturan</div>
        <a href="../kasir/index.php"><i class="bi bi-people"></i> Kelola Kasir</a>
        <a href="../profil/index.php"><i class="bi bi-gear"></i> Profil Usaha</a>
        <a href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar no-print">
        <h5>Laporan Produk</h5>
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
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                    <a href="?dari=<?= date('Y-m-01') ?>&sampai=<?= date('Y-m-d') ?>"
                       class="btn btn-outline-secondary btn-sm">Bulan Ini</a>
                </div>
            </form>
        </div>

        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Produk</div>
                    <div style="font-size:1.6rem;font-weight:700"><?= $total_produk ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Item Terjual</div>
                    <div style="font-size:1.6rem;font-weight:700">
                        <?= number_format($total_terjual) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Pendapatan</div>
                    <div style="font-size:1.2rem;font-weight:700">
                        <?= formatRupiah($total_pendapatan) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Margin</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#16a34a">
                        <?= formatRupiah($total_margin) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Chart Top 5 -->
            <div class="col-md-5 no-print">
                <div class="card-box">
                    <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                        <h6 style="margin:0;font-weight:700">Top 5 Produk Terlaris</h6>
                    </div>
                    <div style="padding:20px">
                        <canvas id="chartTop5" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stok Menipis -->
            <div class="col-md-7">
                <div class="card-box">
                    <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                        <h6 style="margin:0;font-weight:700">
                            <i class="bi bi-exclamation-triangle text-danger me-1"></i>
                            Stok Perlu Perhatian
                        </h6>
                    </div>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Stok</th>
                                <th>Minimal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stok_perlu = array_filter(
                                $produk_list,
                                fn($p) => $p['stok'] <= 10
                            );
                            if (empty($stok_perlu)):
                            ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    Semua stok dalam kondisi aman
                                </td>
                            </tr>
                            <?php else:
                                foreach (array_slice($stok_perlu, 0, 6) as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:.85rem">
                                        <?= htmlspecialchars($p['nama_barang']) ?>
                                    </div>
                                    <div style="font-size:.72rem;color:#9ca3af">
                                        <?= htmlspecialchars($p['kode_barang']) ?>
                                    </div>
                                </td>
                                <td style="font-weight:700;color:<?= $p['stok'] == 0 ? '#dc2626' : '#f59e0b' ?>">
                                    <?= $p['stok'] ?>
                                </td>
                                <td style="color:#9ca3af">—</td>
                                <td>
                                    <?php if ($p['stok'] == 0): ?>
                                        <span style="background:#fee2e2;color:#991b1b;
                                              padding:2px 8px;border-radius:10px;
                                              font-size:.72rem;font-weight:600">Habis</span>
                                    <?php else: ?>
                                        <span style="background:#fef3c7;color:#92400e;
                                              padding:2px 8px;border-radius:10px;
                                              font-size:.72rem;font-weight:600">Menipis</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabel Semua Produk -->
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700">Performa Semua Produk</h6>
            </div>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga Jual</th>
                            <th>Terjual</th>
                            <th>Pendapatan</th>
                            <th>Margin</th>
                            <th>Sisa Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produk_list as $i => $p):
                            $rank_colors = ['#f59e0b','#9ca3af','#cd7f32'];
                            $rank_color  = $rank_colors[$i] ?? '#e5e7eb';
                        ?>
                        <tr>
                            <td>
                                <?php if ($i < 3): ?>
                                <span class="rank-badge"
                                      style="background:<?= $rank_color ?>;color:#fff">
                                    <?= $i + 1 ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#9ca3af;font-size:.82rem">
                                    <?= $i + 1 ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600">
                                    <?= htmlspecialchars($p['nama_barang']) ?>
                                </div>
                                <div style="font-size:.72rem;color:#9ca3af">
                                    <?= htmlspecialchars($p['kode_barang']) ?>
                                    · <?= htmlspecialchars($p['satuan']) ?>
                                </div>
                            </td>
                            <td style="font-size:.82rem;color:#6b7280">
                                <?= htmlspecialchars($p['kategori'] ?? '-') ?>
                            </td>
                            <td style="font-weight:600">
                                <?= formatRupiah($p['harga_jual']) ?>
                            </td>
                            <td style="font-weight:700">
                                <?= number_format($p['total_terjual']) ?>
                                <span style="color:#9ca3af;font-weight:400">
                                    <?= htmlspecialchars($p['satuan']) ?>
                                </span>
                            </td>
                            <td style="font-weight:600">
                                <?= formatRupiah($p['total_pendapatan']) ?>
                            </td>
                            <td style="color:#16a34a;font-weight:600">
                                <?= formatRupiah($p['total_margin']) ?>
                            </td>
                            <td>
                                <?php
                                $stok = $p['stok'];
                                $stok_color = $stok == 0
                                    ? '#dc2626'
                                    : ($stok <= 10 ? '#f59e0b' : '#16a34a');
                                ?>
                                <span style="font-weight:700;color:<?= $stok_color ?>">
                                    <?= number_format($stok) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ctx5 = document.getElementById('chartTop5').getContext('2d');
new Chart(ctx5, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($top5_label) ?>,
        datasets: [{
            data: <?= json_encode($top5_data) ?>,
            backgroundColor: [
                '#2563eb','#16a34a','#9333ea','#ea580c','#0891b2'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position:'bottom', labels:{ font:{ size:11 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.label + ': ' +
                        ctx.raw.toLocaleString('id-ID') + ' terjual'
                }
            }
        }
    }
});
</script>
</body>
</html>