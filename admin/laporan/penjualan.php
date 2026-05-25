<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$tab    = $_GET['tab']    ?? 'harian';
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$tahun  = (int)($_GET['tahun'] ?? date('Y'));

// ============================================
// DATA HARIAN
// ============================================
$rekap_harian = [];
if ($tab === 'harian') {
    $stmt = $pdo->prepare(
        "SELECT tanggal_terjual,
                COUNT(*) as jumlah_transaksi,
                SUM(total_harga) as total_pendapatan,
                SUM(total_margin) as total_margin,
                SUM(total_harga - total_margin) as total_modal
         FROM transaksi
         WHERE tanggal_terjual BETWEEN ? AND ?
         AND status = 'selesai'
         GROUP BY tanggal_terjual
         ORDER BY tanggal_terjual ASC"
    );
    $stmt->execute([$dari, $sampai]);
    $rekap_harian = $stmt->fetchAll();
}

// ============================================
// DATA BULANAN
// ============================================
$rekap_bulanan = [];
if ($tab === 'bulanan') {
    $stmt = $pdo->prepare(
        "SELECT
                MONTH(tanggal_terjual) as bulan,
                MONTHNAME(tanggal_terjual) as nama_bulan,
                COUNT(*) as jumlah_transaksi,
                SUM(total_harga) as total_pendapatan,
                SUM(total_margin) as total_margin,
                SUM(total_harga - total_margin) as total_modal
         FROM transaksi
         WHERE YEAR(tanggal_terjual) = ?
         AND status = 'selesai'
         GROUP BY MONTH(tanggal_terjual)
         ORDER BY MONTH(tanggal_terjual) ASC"
    );
    $stmt->execute([$tahun]);
    $rekap_bulanan = $stmt->fetchAll();

    // Lengkapi 12 bulan (isi 0 kalau tidak ada data)
    $bulan_lengkap = [];
    $nama_bulan_id = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
        5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
        9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    for ($b = 1; $b <= 12; $b++) {
        $found = false;
        foreach ($rekap_bulanan as $r) {
            if ((int)$r['bulan'] === $b) {
                $bulan_lengkap[] = $r;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $bulan_lengkap[] = [
                'bulan'             => $b,
                'nama_bulan'        => $nama_bulan_id[$b],
                'jumlah_transaksi'  => 0,
                'total_pendapatan'  => 0,
                'total_margin'      => 0,
                'total_modal'       => 0,
            ];
        }
    }
    $rekap_bulanan = $bulan_lengkap;
}

// ============================================
// DATA PER TRANSAKSI
// ============================================
$rekap_transaksi = [];
if ($tab === 'transaksi') {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.nama as nama_kasir,
                COUNT(td.id) as total_item,
                SUM(td.jumlah) as total_qty
         FROM transaksi t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
         WHERE t.tanggal_terjual BETWEEN ? AND ?
         AND t.status = 'selesai'
         GROUP BY t.id
         ORDER BY t.created_at DESC"
    );
    $stmt->execute([$dari, $sampai]);
    $rekap_transaksi = $stmt->fetchAll();
}

// ============================================
// DATA PESANAN ONLINE
// ============================================
$rekap_pesanan_harian  = [];
$rekap_pesanan_bulanan = [];
$rekap_pesanan_list    = [];

if ($tab === 'pesanan') {
    // Rekap harian pesanan online
    $stmt = $pdo->prepare(
        "SELECT DATE(p.updated_at) as tanggal,
                COUNT(p.id) as jumlah_pesanan,
                SUM(p.total_harga) as total_pendapatan
         FROM pesanan p
         WHERE DATE(p.updated_at) BETWEEN ? AND ?
         AND p.status = 'selesai'
         GROUP BY DATE(p.updated_at)
         ORDER BY tanggal ASC"
    );
    $stmt->execute([$dari, $sampai]);
    $rekap_pesanan_harian = $stmt->fetchAll();

    // List detail pesanan
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nama as nama_pembeli,
                u.no_telepon,
                pg.kurir, pg.no_resi,
                pg.kota_tujuan,
                COUNT(pd.id) as total_item,
                SUM(pd.jumlah) as total_qty
         FROM pesanan p
         JOIN users u ON p.user_id = u.id
         LEFT JOIN pengiriman pg ON p.id = pg.pesanan_id
         LEFT JOIN pesanan_detail pd ON p.id = pd.pesanan_id
         WHERE DATE(p.updated_at) BETWEEN ? AND ?
         AND p.status = 'selesai'
         GROUP BY p.id
         ORDER BY p.updated_at DESC"
    );
    $stmt->execute([$dari, $sampai]);
    $rekap_pesanan_list = $stmt->fetchAll();

    // Summary
    $total_transaksi  = count($rekap_pesanan_list);
    $total_pendapatan = array_sum(array_column($rekap_pesanan_list, 'total_harga'));
    $total_margin     = 0; // pesanan online tidak punya margin
    $total_modal      = 0;

    // Grafik
    $grafik_labels     = array_map(
        fn($r) => date('d/m', strtotime($r['tanggal'])),
        $rekap_pesanan_harian
    );
    $grafik_pendapatan = array_map(
        fn($r) => (float)$r['total_pendapatan'],
        $rekap_pesanan_harian
    );
    $grafik_margin     = [];
}

// ============================================
// SUMMARY TOTALS
// ============================================
if ($tab === 'harian') {
    $total_pendapatan = array_sum(array_column($rekap_harian, 'total_pendapatan'));
    $total_margin     = array_sum(array_column($rekap_harian, 'total_margin'));
    $total_modal      = array_sum(array_column($rekap_harian, 'total_modal'));
    $total_transaksi  = array_sum(array_column($rekap_harian, 'jumlah_transaksi'));

} elseif ($tab === 'bulanan') {
    $total_pendapatan = array_sum(array_column($rekap_bulanan, 'total_pendapatan'));
    $total_margin     = array_sum(array_column($rekap_bulanan, 'total_margin'));
    $total_modal      = array_sum(array_column($rekap_bulanan, 'total_modal'));
    $total_transaksi  = array_sum(array_column($rekap_bulanan, 'jumlah_transaksi'));

} elseif ($tab === 'transaksi') {
    $total_transaksi  = count($rekap_transaksi);
    $total_pendapatan = array_sum(array_column($rekap_transaksi, 'total_harga'));
    $total_margin     = array_sum(array_column($rekap_transaksi, 'total_margin'));
    $total_modal      = 0;

} elseif ($tab === 'pesanan') {
    $total_modal = 0;

} else {
    $total_pendapatan = 0;
    $total_margin     = 0;
    $total_modal      = 0;
    $total_transaksi  = 0;
}

// ============================================
// GRAFIK DATA
// ============================================
// SESUDAH (benar) — setiap tab punya blok sendiri
$grafik_labels     = [];
$grafik_pendapatan = [];
$grafik_margin     = [];

if ($tab === 'harian') {
    $grafik_labels     = array_map(
        fn($r) => date('d/m', strtotime($r['tanggal_terjual'])),
        $rekap_harian
    );
    $grafik_pendapatan = array_map(fn($r) => (float)$r['total_pendapatan'], $rekap_harian);
    $grafik_margin     = array_map(fn($r) => (float)$r['total_margin'], $rekap_harian);

} elseif ($tab === 'bulanan') {
    $nama_bulan_id = [
        1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
        7=>'Jul',8=>'Ags',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'
    ];
    $grafik_labels     = array_map(
        fn($r) => $nama_bulan_id[(int)$r['bulan']] ?? '',
        $rekap_bulanan
    );
    $grafik_pendapatan = array_map(fn($r) => (float)$r['total_pendapatan'], $rekap_bulanan);
    $grafik_margin     = array_map(fn($r) => (float)$r['total_margin'], $rekap_bulanan);

}
elseif ($tab === 'pesanan') {
    // Grafik untuk pesanan online (harian)
    $grafik_labels     = array_map(
        fn($r) => date('d/m', strtotime($r['tanggal'])),
        $rekap_pesanan_harian
    );
    $grafik_pendapatan = array_map(fn($r) => (float)$r['total_pendapatan'], $rekap_pesanan_harian);
    $grafik_margin     = [];

}

// Jika tab harian tapi data kosong, buat label tanggal dari $dari sampai $sampai
if ($tab === 'harian' && empty($grafik_labels)) {
    $start = new DateTime($dari);
    $end   = new DateTime($sampai);
    if ($end < $start) {
        // swap jika input terbalik
        [$start, $end] = [$end, $start];
    }
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    // buat peta tanggal => nilai dari $rekap_harian jika ada
    $mapPendapatan = [];
    $mapMargin = [];
    foreach ($rekap_harian as $r) {
        $mapPendapatan[$r['tanggal_terjual']] = (float)$r['total_pendapatan'];
        $mapMargin[$r['tanggal_terjual']] = (float)$r['total_margin'];
    }

    $grafik_labels = [];
    $grafik_pendapatan = [];
    $grafik_margin = [];
    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        $grafik_labels[] = $dt->format('d/m');
        $grafik_pendapatan[] = $mapPendapatan[$d] ?? 0;
        $grafik_margin[] = $mapMargin[$d] ?? 0;
    }
}

// Daftar tahun tersedia
$stmt = $pdo->query(
    "SELECT DISTINCT YEAR(tanggal_terjual) as tahun
     FROM transaksi ORDER BY tahun DESC"
);
$tahun_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($tahun_list)) $tahun_list = [date('Y')];

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/laporan/penjualan.css">
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
        <a href="penjualan.php" class="active"><i class="bi bi-bar-chart-line"></i> Lap. Penjualan</a>
        <a href="produk.php"><i class="bi bi-pie-chart"></i> Lap. Produk</a>
        <a href="kasir.php"><i class="bi bi-person-badge"></i> Lap. Kasir</a>
        <div class="menu-label">Pengaturan</div>
        <a href="../kasir/index.php"><i class="bi bi-people"></i> Kelola Kasir</a>
        <a href="../profil/index.php"><i class="bi bi-gear"></i> Profil Usaha</a>
        <a href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar no-print">
        <h5>Laporan Penjualan</h5>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
    </div>

    <div class="content">

        <!-- Tab Navigasi -->
        <div class="tab-nav no-print">
            <a href="?tab=harian&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="tab-btn <?= $tab === 'harian' ? 'active' : '' ?>">
                <i class="bi bi-calendar-day me-1"></i>Harian
            </a>
            <a href="?tab=bulanan&tahun=<?= $tahun ?>"
               class="tab-btn <?= $tab === 'bulanan' ? 'active' : '' ?>">
                <i class="bi bi-calendar-month me-1"></i>Bulanan
            </a>
            <a href="?tab=transaksi&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="tab-btn <?= $tab === 'transaksi' ? 'active' : '' ?>">
                <i class="bi bi-receipt me-1"></i>Per Transaksi
            </a>
            <a href="?tab=pesanan&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="tab-btn <?= $tab === 'pesanan' ? 'active' : '' ?>">
                <i class="bi bi-bag-check me-1"></i>Pesanan Onlinex
            </a>
        </div>

        <!-- Filter -->
        <div class="filter-bar no-print">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?= $tab ?>">

                <?php if ($tab === 'bulanan'): ?>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                        <?php foreach ($tahun_list as $t): ?>
                        <option value="<?= $t ?>" <?= $tahun == $t ? 'selected' : '' ?>>
                            <?= $t ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
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
                <?php endif; ?>

                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                    <?php if ($tab === 'harian' || $tab === 'transaksi'): ?>
                    <a href="?tab=<?= $tab ?>&dari=<?= date('Y-m-d') ?>&sampai=<?= date('Y-m-d') ?>"
                       class="btn btn-outline-secondary btn-sm">Hari Ini</a>
                    <a href="?tab=<?= $tab ?>&dari=<?= date('Y-m-01') ?>&sampai=<?= date('Y-m-d') ?>"
                       class="btn btn-outline-secondary btn-sm">Bulan Ini</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Periode Info -->
        <div style="font-size:.85rem;color:#6b7280;margin-bottom:16px">
            <?php if ($tab === 'bulanan'): ?>
                Laporan tahun: <strong><?= $tahun ?></strong>
            <?php else: ?>
                Periode: <strong><?= formatTanggal($dari) ?></strong>
                — <strong><?= formatTanggal($sampai) ?></strong>
            <?php endif; ?>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Transaksi</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= number_format($total_transaksi) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">transaksi POS</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Pendapatan</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#111827">
                        <?= formatRupiah($total_pendapatan) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">omzet kotor</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Modal</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#ef4444">
                        <?= formatRupiah($total_modal) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">harga beli barang</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">Total Margin</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#16a34a">
                        <?= formatRupiah($total_margin) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">
                        <?= $total_pendapatan > 0
                            ? round(($total_margin / $total_pendapatan) * 100, 1)
                            : 0 ?>% margin
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik (harian & bulanan) -->
        <?php if ($tab !== 'transaksi'): ?>
        <div class="card-box mb-4 no-print">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;
                        display:flex;align-items:center;justify-content:space-between">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    <?= $tab === 'bulanan'
                        ? 'Grafik Penjualan Bulanan ' . $tahun
                        : ($tab === 'harian' ? 'Grafik Penjualan Harian' : 'Grafik Pesanan Online Harian') ?>
                </h6>
            </div>
            <div style="padding:20px">
                <canvas id="grafikPenjualan" height="<?= $tab === 'bulanan' ? '70' : '80' ?>"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== TAB HARIAN ===== -->
        <?php if ($tab === 'harian'): ?>
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    Rekapitulasi Penjualan Harian
                </h6>
            </div>
            <?php if (empty($rekap_harian)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bar-chart d-block mb-2" style="font-size:2.5rem"></i>
                    Tidak ada data penjualan pada periode ini
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Hari</th>
                                <th>Jml Transaksi</th>
                                <th>Pendapatan</th>
                                <th>Modal</th>
                                <th>Margin</th>
                                <th>% Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_harian as $r):
                                $persen = $r['total_pendapatan'] > 0
                                    ? round(($r['total_margin'] / $r['total_pendapatan']) * 100, 1)
                                    : 0;
                                $is_today = $r['tanggal_terjual'] === date('Y-m-d');
                            ?>
                            <tr <?= $is_today ? 'class="bulan-highlight"' : '' ?>>
                                <td style="font-weight:600">
                                    <?= formatTanggal($r['tanggal_terjual']) ?>
                                    <?php if ($is_today): ?>
                                        <span style="background:#2563eb;color:#fff;
                                              font-size:.65rem;padding:1px 6px;
                                              border-radius:6px;margin-left:4px">
                                            Hari ini
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#6b7280">
                                    <?php
                                    $hari_id = [
                                        'Sunday'    => 'Minggu',
                                        'Monday'    => 'Senin',
                                        'Tuesday'   => 'Selasa',
                                        'Wednesday' => 'Rabu',
                                        'Thursday'  => 'Kamis',
                                        'Friday'    => 'Jumat',
                                        'Saturday'  => 'Sabtu',
                                    ];
                                    echo $hari_id[date('l', strtotime($r['tanggal_terjual']))] ?? '';
                                    ?>
                                </td>
                                <td><?= number_format($r['jumlah_transaksi']) ?>x</td>
                                <td style="font-weight:600">
                                    <?= formatRupiah($r['total_pendapatan']) ?>
                                </td>
                                <td style="color:#ef4444">
                                    <?= formatRupiah($r['total_modal']) ?>
                                </td>
                                <td style="font-weight:700;color:#16a34a">
                                    <?= formatRupiah($r['total_margin']) ?>
                                </td>
                                <td>
                                    <span style="background:#dcfce7;color:#166534;
                                          padding:2px 8px;border-radius:10px;
                                          font-size:.75rem;font-weight:600">
                                        <?= $persen ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">TOTAL</td>
                                <td><?= number_format($total_transaksi) ?>x</td>
                                <td><?= formatRupiah($total_pendapatan) ?></td>
                                <td style="color:#ef4444">
                                    <?= formatRupiah($total_modal) ?>
                                </td>
                                <td style="color:#16a34a">
                                    <?= formatRupiah($total_margin) ?>
                                </td>
                                <td>
                                    <?= $total_pendapatan > 0
                                        ? round(($total_margin / $total_pendapatan) * 100, 1)
                                        : 0 ?>%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== TAB BULANAN ===== -->
        <?php elseif ($tab === 'bulanan'): ?>
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    Rekapitulasi Penjualan Bulanan <?= $tahun ?>
                </h6>
            </div>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Jml Transaksi</th>
                            <th>Pendapatan</th>
                            <th>Modal</th>
                            <th>Margin</th>
                            <th>% Margin</th>
                            <th>Tren</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $prev_pendapatan = 0;
                        foreach ($rekap_bulanan as $i => $r):
                            $persen = $r['total_pendapatan'] > 0
                                ? round(($r['total_margin'] / $r['total_pendapatan']) * 100, 1)
                                : 0;
                            $is_current = (int)$r['bulan'] === (int)date('n')
                                          && $tahun == date('Y');
                            $tren = '';
                            if ($i > 0 && $prev_pendapatan > 0) {
                                $diff = $r['total_pendapatan'] - $prev_pendapatan;
                                $pct  = round(($diff / $prev_pendapatan) * 100, 1);
                                $tren = $diff >= 0
                                    ? "<span style='color:#16a34a'>▲ {$pct}%</span>"
                                    : "<span style='color:#dc2626'>▼ " . abs($pct) . "%</span>";
                            }
                            $prev_pendapatan = $r['total_pendapatan'];
                        ?>
                        <tr <?= $is_current ? 'class="bulan-highlight"' : '' ?>>
                            <td style="font-weight:600">
                                <?= htmlspecialchars($r['nama_bulan']) ?>
                                <?php if ($is_current): ?>
                                    <span style="background:#2563eb;color:#fff;
                                          font-size:.65rem;padding:1px 6px;
                                          border-radius:6px;margin-left:4px">
                                        Berjalan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['jumlah_transaksi'] > 0): ?>
                                    <span style="font-weight:600">
                                        <?= number_format($r['jumlah_transaksi']) ?>x
                                    </span>
                                <?php else: ?>
                                    <span style="color:#d1d5db">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600">
                                <?php if ($r['total_pendapatan'] > 0): ?>
                                    <?= formatRupiah($r['total_pendapatan']) ?>
                                <?php else: ?>
                                    <span style="color:#d1d5db">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#ef4444">
                                <?php if ($r['total_modal'] > 0): ?>
                                    <?= formatRupiah($r['total_modal']) ?>
                                <?php else: ?>
                                    <span style="color:#d1d5db">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:700;color:#16a34a">
                                <?php if ($r['total_margin'] > 0): ?>
                                    <?= formatRupiah($r['total_margin']) ?>
                                <?php else: ?>
                                    <span style="color:#d1d5db">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['total_pendapatan'] > 0): ?>
                                <span style="background:#dcfce7;color:#166534;
                                      padding:2px 8px;border-radius:10px;
                                      font-size:.75rem;font-weight:600">
                                    <?= $persen ?>%
                                </span>
                                <?php else: ?>
                                    <span style="color:#d1d5db">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.82rem">
                                <?= $tren ?: '<span style="color:#d1d5db">—</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL <?= $tahun ?></td>
                            <td><?= number_format($total_transaksi) ?>x</td>
                            <td><?= formatRupiah($total_pendapatan) ?></td>
                            <td style="color:#ef4444">
                                <?= formatRupiah($total_modal) ?>
                            </td>
                            <td style="color:#16a34a">
                                <?= formatRupiah($total_margin) ?>
                            </td>
                            <td>
                                <?= $total_pendapatan > 0
                                    ? round(($total_margin / $total_pendapatan) * 100, 1)
                                    : 0 ?>%
                            </td>
                            <td>—</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ===== TAB PER TRANSAKSI ===== -->
        <?php elseif ($tab === 'transaksi'): ?>
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;
                        display:flex;align-items:center;justify-content:space-between">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    Detail Per Transaksi
                </h6>
                <span style="font-size:.82rem;color:#6b7280">
                    <?= count($rekap_transaksi) ?> transaksi
                </span>
            </div>
            <?php if (empty($rekap_transaksi)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-receipt d-block mb-2" style="font-size:2.5rem"></i>
                    Tidak ada transaksi pada periode ini
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>No. Transaksi</th>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Bayar</th>
                                <th>Kembalian</th>
                                <th>Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_transaksi as $t): ?>
                            <tr>
                                <td>
                                    <code style="background:#f3f4f6;padding:2px 8px;
                                          border-radius:4px;font-size:.78rem">
                                        <?= htmlspecialchars($t['no_transaksi']) ?>
                                    </code>
                                </td>
                                <td style="font-size:.78rem;color:#6b7280;white-space:nowrap">
                                    <?= formatTanggalJam($t['created_at']) ?>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= htmlspecialchars($t['nama_kasir']) ?>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= $t['total_item'] ?> jenis
                                    <span style="color:#9ca3af">
                                        (<?= number_format($t['total_qty']) ?> pcs)
                                    </span>
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
                        <tfoot>
                            <tr>
                                <td colspan="4">TOTAL</td>
                                <td><?= formatRupiah($total_pendapatan) ?></td>
                                <td>—</td>
                                <td>—</td>
                                <td style="color:#16a34a">
                                    <?= formatRupiah($total_margin) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== TAB PESANAN ONLINE ===== -->
        <?php elseif ($tab === 'pesanan'): ?>

        <!-- Rekap Harian -->
        <?php if (!empty($rekap_pesanan_harian)): ?>
        <div class="card-box mb-4">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    Rekap Harian Pesanan Online
                </h6>
            </div>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Hari</th>
                            <th>Jumlah Pesanan</th>
                            <th>Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hari_id = [
                            'Sunday'    => 'Minggu',
                            'Monday'    => 'Senin',
                            'Tuesday'   => 'Selasa',
                            'Wednesday' => 'Rabu',
                            'Thursday'  => 'Kamis',
                            'Friday'    => 'Jumat',
                            'Saturday'  => 'Sabtu',
                        ];
                        foreach ($rekap_pesanan_harian as $r):
                            $is_today = $r['tanggal'] === date('Y-m-d');
                        ?>
                        <tr <?= $is_today ? 'class="bulan-highlight"' : '' ?>>
                            <td style="font-weight:600">
                                <?= formatTanggal($r['tanggal']) ?>
                                <?php if ($is_today): ?>
                                    <span style="background:#2563eb;color:#fff;font-size:.65rem;
                                        padding:1px 6px;border-radius:6px;margin-left:4px">
                                        Hari ini
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#6b7280">
                                <?= $hari_id[date('l', strtotime($r['tanggal']))] ?? '' ?>
                            </td>
                            <td style="font-weight:600">
                                <?= number_format($r['jumlah_pesanan']) ?> pesanan
                            </td>
                            <td style="font-weight:700;color:#16a34a">
                                <?= formatRupiah($r['total_pendapatan']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">TOTAL</td>
                            <td><?= count($rekap_pesanan_list) ?> pesanan</td>
                            <td style="color:#16a34a">
                                <?= formatRupiah($total_pendapatan) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detail Pesanan -->
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;
                        display:flex;align-items:center;justify-content:space-between">
                <h6 style="margin:0;font-weight:700;color:#111827">
                    Detail Pesanan Online Selesai
                </h6>
                <span style="font-size:.82rem;color:#6b7280">
                    <?= count($rekap_pesanan_list) ?> pesanan
                </span>
            </div>

            <?php if (empty($rekap_pesanan_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-x d-block mb-2" style="font-size:2.5rem"></i>
                    Tidak ada pesanan selesai pada periode ini
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Tanggal Selesai</th>
                                <th>Pembeli</th>
                                <th>Kota Tujuan</th>
                                <th>Kurir</th>
                                <th>Item</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_pesanan_list as $p): ?>
                            <tr>
                                <td>
                                    <a href="../pesanan/detail.php?id=<?= $p['id'] ?>"
                                    style="text-decoration:none">
                                        <code style="background:#f3f4f6;padding:2px 8px;
                                            border-radius:4px;font-size:.78rem;
                                            color:#2563eb">
                                            <?= htmlspecialchars($p['no_pesanan']) ?>
                                        </code>
                                    </a>
                                </td>
                                <td style="font-size:.78rem;color:#6b7280;white-space:nowrap">
                                    <?= formatTanggalJam($p['updated_at']) ?>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:.85rem">
                                        <?= htmlspecialchars($p['nama_pembeli']) ?>
                                    </div>
                                    <div style="font-size:.72rem;color:#9ca3af">
                                        <?= htmlspecialchars($p['no_telepon'] ?? '') ?>
                                    </div>
                                </td>
                                <td style="font-size:.82rem;color:#6b7280">
                                    <?= htmlspecialchars($p['kota_tujuan'] ?? '-') ?>
                                </td>
                                <td style="font-size:.82rem">
                                    <?php if ($p['kurir']): ?>
                                        <span style="background:#f3f4f6;padding:2px 8px;
                                            border-radius:6px;font-size:.75rem;font-weight:600">
                                            <?= htmlspecialchars($p['kurir']) ?>
                                        </span>
                                        <?php if ($p['no_resi']): ?>
                                        <div style="font-size:.72rem;color:#9ca3af;margin-top:2px">
                                            <?= htmlspecialchars($p['no_resi']) ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#d1d5db">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem">
                                    <?= $p['total_item'] ?> jenis
                                    <span style="color:#9ca3af">
                                        (<?= number_format($p['total_qty']) ?> pcs)
                                    </span>
                                </td>
                                <td style="font-weight:700;color:#16a34a">
                                    <?= formatRupiah($p['total_harga']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">TOTAL</td>
                                <td style="color:#16a34a">
                                    <?= formatRupiah($total_pendapatan) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const canvas = document.getElementById('grafikPenjualan');
    if (!canvas) return; // kalau canvas tidak ada, stop

    const ctx = canvas.getContext('2d');

    <?php if ($tab === 'harian' && !empty($grafik_labels)): ?>

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grafik_labels) ?>,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: <?= json_encode($grafik_pendapatan) ?>,
                    backgroundColor: 'rgba(37,99,235,0.15)',
                    borderColor: '#2563eb',
                    borderWidth: 2,
                    borderRadius: 6,
                    order: 2
                },
                {
                    label: 'Margin',
                    data: <?= json_encode($grafik_margin) ?>,
                    type: 'line',
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#16a34a',
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode:'index', intersect:false },
            plugins: {
                legend: { position:'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': Rp ' +
                            ctx.raw.toLocaleString('id-ID')
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

    <?php elseif ($tab === 'bulanan' && !empty($grafik_labels)): ?>

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grafik_labels) ?>,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: <?= json_encode($grafik_pendapatan) ?>,
                    backgroundColor: 'rgba(37,99,235,0.15)',
                    borderColor: '#2563eb',
                    borderWidth: 2,
                    borderRadius: 6,
                    order: 2
                },
                {
                    label: 'Margin',
                    data: <?= json_encode($grafik_margin) ?>,
                    type: 'line',
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#16a34a',
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode:'index', intersect:false },
            plugins: {
                legend: { position:'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': Rp ' +
                            ctx.raw.toLocaleString('id-ID')
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

    <?php elseif ($tab === 'pesanan' && !empty($grafik_labels)): ?>

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grafik_labels) ?>,
            datasets: [{
                label: 'Pendapatan Online',
                data: <?= json_encode($grafik_pendapatan) ?>,
                backgroundColor: 'rgba(22,163,74,0.15)',
                borderColor: '#16a34a',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position:'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => 'Pendapatan: Rp ' +
                            ctx.raw.toLocaleString('id-ID')
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

    <?php endif; ?>

}); // end DOMContentLoaded
</script>
</body>
</html>