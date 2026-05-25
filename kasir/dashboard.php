<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekKasir();
$user = userLogin();

// Stat hari ini khusus kasir ini
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM transaksi
     WHERE user_id = ? AND tanggal_terjual = CURDATE() AND status = 'selesai'"
);
$stmt->execute([$user['id']]);
$transaksi_hari_ini = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) FROM transaksi
     WHERE user_id = ? AND tanggal_terjual = CURDATE() AND status = 'selesai'"
);
$stmt->execute([$user['id']]);
$pendapatan_hari_ini = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_margin), 0) FROM transaksi
     WHERE user_id = ? AND tanggal_terjual = CURDATE() AND status = 'selesai'"
);
$stmt->execute([$user['id']]);
$margin_hari_ini = $stmt->fetchColumn();

// Total item terjual hari ini
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(td.jumlah), 0)
     FROM transaksi_detail td
     JOIN transaksi t ON td.transaksi_id = t.id
     WHERE t.user_id = ? AND t.tanggal_terjual = CURDATE()
     AND t.status = 'selesai'"
);
$stmt->execute([$user['id']]);
$item_terjual = $stmt->fetchColumn();

// Stok menipis
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM barang WHERE stok <= stok_minimal"
);
$stok_menipis = $stmt->fetchColumn();

// Transaksi terakhir hari ini
$stmt = $pdo->prepare(
    "SELECT t.*, COUNT(td.id) as total_item
     FROM transaksi t
     LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
     WHERE t.user_id = ? AND t.tanggal_terjual = CURDATE()
     AND t.status = 'selesai'
     GROUP BY t.id
     ORDER BY t.created_at DESC
     LIMIT 8"
);
$stmt->execute([$user['id']]);
$transaksi_list = $stmt->fetchAll();

// Grafik transaksi 7 hari
$stmt = $pdo->prepare(
    "SELECT tanggal_terjual,
            COUNT(*) as jumlah,
            SUM(total_harga) as total
     FROM transaksi
     WHERE user_id = ?
     AND tanggal_terjual >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     AND status = 'selesai'
     GROUP BY tanggal_terjual
     ORDER BY tanggal_terjual ASC"
);
$stmt->execute([$user['id']]);
$grafik_raw = $stmt->fetchAll();

$grafik_labels = [];
$grafik_total  = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl             = date('Y-m-d', strtotime("-$i days"));
    $grafik_labels[] = date('d/m', strtotime($tgl));
    $found           = false;
    foreach ($grafik_raw as $row) {
        if ($row['tanggal_terjual'] === $tgl) {
            $grafik_total[] = (float)$row['total'];
            $found          = true;
            break;
        }
    }
    if (!$found) $grafik_total[] = 0;
}

// Profil toko
$stmt = $pdo->query("SELECT * FROM profil_usaha LIMIT 2");
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/kasir/style.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-1"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?> &middot; Kasir</small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Menu</div>
        <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="pos/index.php"><i class="bi bi-calculator"></i> POS / Transaksi</a>
        <div class="menu-label">Inventori</div>
        <a href="pembelian/tambah.php"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="stok/histori.php"><i class="bi bi-clock-history"></i> Histori Stok</a>
        <div class="menu-label">Akun</div>
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <h5>Dashboard Kasir</h5>
            <div style="font-size:.78rem;color:#9ca3af">
                <?= formatTanggal(date('Y-m-d')) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($stok_menipis > 0): ?>
            <span style="font-size:.8rem;color:#f59e0b">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= $stok_menipis ?> stok menipis
            </span>
            <?php endif; ?>
            <div style="font-size:.85rem;font-weight:600;color:#374151">
                <?= htmlspecialchars($user['nama']) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- Tombol POS besar -->
        <div class="mb-4">
            <a href="pos/index.php" class="pos-btn">
                <i class="bi bi-calculator"></i>
                Buka Kasir / Input Transaksi POS
            </a>
        </div>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#eff6ff">
                        <i class="bi bi-receipt" style="color:#2563eb"></i>
                    </div>
                    <div>
                        <div style="font-size:.78rem;color:#6b7280">Transaksi Hari Ini</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#111827">
                            <?= number_format($transaksi_hari_ini) ?>
                        </div>
                        <div style="font-size:.72rem;color:#9ca3af">transaksi</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#f0fdf4">
                        <i class="bi bi-cash-coin" style="color:#16a34a"></i>
                    </div>
                    <div>
                        <div style="font-size:.78rem;color:#6b7280">Pendapatan Hari Ini</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#111827;line-height:1.3">
                            <?= formatRupiah($pendapatan_hari_ini) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#fdf4ff">
                        <i class="bi bi-graph-up" style="color:#9333ea"></i>
                    </div>
                    <div>
                        <div style="font-size:.78rem;color:#6b7280">Margin Hari Ini</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#111827;line-height:1.3">
                            <?= formatRupiah($margin_hari_ini) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#fff7ed">
                        <i class="bi bi-box-seam" style="color:#ea580c"></i>
                    </div>
                    <div>
                        <div style="font-size:.78rem;color:#6b7280">Item Terjual</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#111827">
                            <?= number_format($item_terjual) ?>
                        </div>
                        <div style="font-size:.72rem;color:#9ca3af">hari ini</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Grafik -->
            <div class="col-md-7">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-bar-chart me-2"></i>Penjualan Saya 7 Hari</h6>
                    </div>
                    <div style="padding:20px">
                        <canvas id="grafikKasir" height="120"></canvas>
                    </div>
                </div>
            </div>

            <!-- Transaksi terakhir -->
            <div class="col-md-5">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-clock-history me-2"></i>Transaksi Hari Ini</h6>
                    </div>
                    <?php if (empty($transaksi_list)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-receipt d-block mb-1" style="font-size:1.8rem"></i>
                            Belum ada transaksi hari ini
                        </div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Item</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transaksi_list as $t): ?>
                                <tr>
                                    <td>
                                        <div style="font-size:.8rem;font-weight:600">
                                            <?= htmlspecialchars($t['no_transaksi']) ?>
                                        </div>
                                        <div style="font-size:.72rem;color:#9ca3af">
                                            <?= date('H:i', strtotime($t['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td style="font-size:.82rem">
                                        <?= $t['total_item'] ?> item
                                    </td>
                                    <td style="font-weight:600;font-size:.82rem">
                                        <?= formatRupiah($t['total_harga']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ctx = document.getElementById('grafikKasir').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($grafik_labels) ?>,
        datasets: [{
            label: 'Pendapatan',
            data: <?= json_encode($grafik_total) ?>,
            backgroundColor: 'rgba(37,99,235,0.15)',
            borderColor: '#2563eb',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: val => {
                        if (val >= 1000000) return 'Rp ' + (val/1000000).toFixed(1) + 'jt';
                        if (val >= 1000) return 'Rp ' + (val/1000).toFixed(0) + 'rb';
                        return 'Rp ' + val;
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>