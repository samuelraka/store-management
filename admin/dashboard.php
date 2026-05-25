<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekAdmin();
$user = userLogin();

// ============================================
// DATA STATISTIK UTAMA
// ============================================

// Total pendapatan POS hari ini
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) FROM transaksi
     WHERE tanggal_terjual = CURDATE() AND status = 'selesai'"
);
$stmt->execute();
$pendapatan_pos_hari_ini = $stmt->fetchColumn();

// Pendapatan Online Hari ini (status pesanan 'selesai')
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM pesanan
     WHERE DATE(created_at) = CURDATE() AND status = 'selesai'"
);
$stmt->execute();
$pendapatan_online_hari_ini = $stmt->fetchColumn();

// Total pendapatan POS bulan ini
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) FROM transaksi
     WHERE MONTH(tanggal_terjual) = MONTH(CURDATE())
     AND YEAR(tanggal_terjual) = YEAR(CURDATE())
     AND status = 'selesai'"
);
$stmt->execute();
$pendapatan_pos_bulan = $stmt->fetchColumn();

// Pendapatan pesanan online bulan ini (status selesai)
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) FROM pesanan
     WHERE MONTH(updated_at) = MONTH(CURDATE())
     AND YEAR(updated_at) = YEAR(CURDATE())
     AND status = 'selesai'"
);
$stmt->execute();
$pendapatan_online_bulan = $stmt->fetchColumn();

// Total margin bulan ini
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_margin), 0) FROM transaksi
     WHERE MONTH(tanggal_terjual) = MONTH(CURDATE())
     AND YEAR(tanggal_terjual) = YEAR(CURDATE())
     AND status = 'selesai'"
);
$stmt->execute();
$margin_bulan_ini = $stmt->fetchColumn();

// Total transaksi hari ini
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM transaksi
     WHERE tanggal_terjual = CURDATE() AND status = 'selesai'"
);
$stmt->execute();
$transaksi_hari_ini = $stmt->fetchColumn();

// Total produk
$stmt = $pdo->query("SELECT COUNT(*) FROM barang");
$total_produk = $stmt->fetchColumn();

// Stok menipis
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM barang WHERE stok <= stok_minimal"
);
$stok_menipis = $stmt->fetchColumn();

// Pesanan menunggu konfirmasi
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM pesanan WHERE status = 'menunggu'"
);
$pesanan_menunggu = $stmt->fetchColumn();

// Total pembeli terdaftar
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'pembeli'"
);
$total_pembeli = $stmt->fetchColumn();

// ============================================
// GRAFIK PENJUALAN 7 HARI TERAKHIR
// ============================================
$stmt = $pdo->query(
    "SELECT tanggal_terjual,
            SUM(total_harga) as total,
            SUM(total_margin) as margin,
            COUNT(*) as jumlah_transaksi
     FROM transaksi
     WHERE tanggal_terjual >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     AND status = 'selesai'
     GROUP BY tanggal_terjual
     ORDER BY tanggal_terjual ASC"
);
$grafik_raw = $stmt->fetchAll();

// Siapkan data 7 hari (isi 0 kalau tidak ada transaksi)
$grafik_labels   = [];
$grafik_total    = [];
$grafik_margin   = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl   = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime($tgl));
    $grafik_labels[] = $label;

    $found = false;
    foreach ($grafik_raw as $row) {
        if ($row['tanggal_terjual'] === $tgl) {
            $grafik_total[]  = (float)$row['total'];
            $grafik_margin[] = (float)$row['margin'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $grafik_total[]  = 0;
        $grafik_margin[] = 0;
    }
}

// ============================================
// PRODUK TERLARIS (TOP 5)
// ============================================
$stmt = $pdo->query(
    "SELECT b.nama_barang, b.satuan,
            SUM(td.jumlah) as total_terjual,
            SUM(td.subtotal) as total_pendapatan,
            SUM(td.margin) as total_margin
     FROM transaksi_detail td
     JOIN barang b ON td.barang_id = b.id
     JOIN transaksi t ON td.transaksi_id = t.id
     WHERE t.status = 'selesai'
     GROUP BY td.barang_id
     ORDER BY total_terjual DESC
     LIMIT 5"
);
$produk_terlaris = $stmt->fetchAll();

// ============================================
// STOK MENIPIS (LIST)
// ============================================
$stmt = $pdo->query(
    "SELECT b.nama_barang, b.kode_barang, b.stok,
            b.stok_minimal, k.nama as kategori
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     WHERE b.stok <= b.stok_minimal
     ORDER BY b.stok ASC
     LIMIT 5"
);
$list_stok_menipis = $stmt->fetchAll();

// ============================================
// PESANAN ONLINE TERBARU
// ============================================
$stmt = $pdo->query(
    "SELECT p.no_pesanan, p.total_harga, p.status,
            p.created_at, u.nama as nama_pembeli
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     ORDER BY p.created_at DESC
     LIMIT 5"
);
$pesanan_terbaru = $stmt->fetchAll();

// ============================================
// TRANSAKSI POS TERBARU
// ============================================
$stmt = $pdo->query(
    "SELECT t.no_transaksi, t.total_harga,
            t.total_margin, t.created_at,
            u.nama as nama_kasir
     FROM transaksi t
     JOIN users u ON t.user_id = u.id
     WHERE t.status = 'selesai'
     ORDER BY t.created_at DESC
     LIMIT 5"
);
$transaksi_terbaru = $stmt->fetchAll();

// Profil usaha
$stmt = $pdo->prepare(
    "SELECT * FROM profil_usaha WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — <?= htmlspecialchars($profil['nama_toko'] ?? '') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-w: 250px;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
        }
        body { background: #f3f4f6; font-family: 'Segoe UI', sans-serif; }

        /* ---- SIDEBAR ---- */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: #1e2a3b; overflow-y: auto;
            z-index: 1000; transition: transform .3s;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-brand h6 {
            color: #fff; font-weight: 700;
            font-size: 1rem; margin: 0;
        }
        .sidebar-brand small { color: #94a3b8; font-size: .75rem; }
        .sidebar-menu { padding: 12px 0; }
        .menu-label {
            padding: 8px 20px 4px;
            font-size: .7rem; font-weight: 600;
            color: #64748b; text-transform: uppercase;
            letter-spacing: .8px;
        }
        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px; color: #94a3b8;
            text-decoration: none; font-size: .9rem;
            transition: all .2s; border-left: 3px solid transparent;
        }
        .sidebar a:hover, .sidebar a.active {
            color: #fff; background: rgba(255,255,255,.06);
            border-left-color: var(--primary);
        }
        .sidebar a i { font-size: 1rem; width: 20px; }
        .badge-menu {
            margin-left: auto;
            background: #ef4444;
            color: #fff; font-size: .7rem;
            padding: 2px 7px; border-radius: 10px;
        }

        /* ---- MAIN ---- */
        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 24px;
            display: flex; align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .topbar h5 { margin: 0; font-weight: 600; color: #1f2937; }
        .content { padding: 24px; }

        /* ---- STAT CARDS ---- */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            height: 100%;
        }
        .stat-card .label {
            font-size: .8rem; color: #6b7280;
            font-weight: 500; margin-bottom: 6px;
        }
        .stat-card .value {
            font-size: 1.5rem; font-weight: 700;
            color: #111827; line-height: 1.2;
        }
        .stat-card .sub {
            font-size: .78rem; color: #9ca3af; margin-top: 4px;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* ---- CARD ---- */
        .card-box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .card-box .card-head {
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center;
            justify-content: space-between;
        }
        .card-box .card-head h6 {
            margin: 0; font-weight: 600;
            color: #1f2937; font-size: .95rem;
        }

        /* ---- TABLE ---- */
        .tbl { width: 100%; font-size: .85rem; }
        .tbl th {
            background: #f9fafb;
            color: #6b7280; font-weight: 600;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .tbl td {
            padding: 10px 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151; vertical-align: middle;
        }
        .tbl tr:last-child td { border-bottom: none; }

        /* ---- BADGE STATUS ---- */
        .badge-status {
            padding: 3px 10px; border-radius: 20px;
            font-size: .75rem; font-weight: 600;
        }
        .badge-menunggu  { background:#fef3c7; color:#92400e; }
        .badge-diproses  { background:#dbeafe; color:#1e40af; }
        .badge-dikirim   { background:#e0e7ff; color:#3730a3; }
        .badge-selesai   { background:#dcfce7; color:#166534; }
        .badge-dibatalkan{ background:#fee2e2; color:#991b1b; }
        .badge-batal     { background:#fee2e2; color:#991b1b; }

        /* ---- STOK MENIPIS ---- */
        .stok-bar-wrap { background: #f3f4f6; border-radius: 4px; height: 6px; }
        .stok-bar {
            height: 6px; border-radius: 4px;
            background: #ef4444; transition: width .3s;
        }
        .stok-bar.ok { background: #22c55e; }
        .stok-bar.warn { background: #f59e0b; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-2"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?> &middot; Super Admin</small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Utama</div>
        <a href="dashboard.php" class="active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="menu-label">Katalog</div>
        <a href="barang/index.php">
            <i class="bi bi-box-seam"></i> Data Barang
        </a>
        <a href="kategori/index.php">
            <i class="bi bi-tags"></i> Kategori
        </a>
        <a href="supplier/index.php">
            <i class="bi bi-truck"></i> Supplier
        </a>

        <div class="menu-label">Inventori</div>
        <a href="pembelian/index.php">
            <i class="bi bi-box-arrow-in-down"></i> Barang Masuk
        </a>
        <a href="stok/histori.php">
            <i class="bi bi-clock-history"></i> Histori Stok
        </a>

        <div class="menu-label">Penjualan</div>
        <a href="pesanan/index.php">
            <i class="bi bi-bag-check"></i> Pesanan Online
            <?php if ($pesanan_menunggu > 0): ?>
                <span class="badge-menu"><?= $pesanan_menunggu ?></span>
            <?php endif; ?>
        </a>

        <div class="menu-label">Laporan</div>
        <a href="laporan/penjualan.php">
            <i class="bi bi-bar-chart-line"></i> Lap. Penjualan
        </a>
        <a href="laporan/produk.php">
            <i class="bi bi-pie-chart"></i> Lap. Produk
        </a>
        <a href="laporan/kasir.php">
            <i class="bi bi-person-badge"></i> Lap. Kasir
        </a>

        <div class="menu-label">Pengaturan</div>
        <a href="kasir/index.php">
            <i class="bi bi-people"></i> Kelola Kasir
        </a>
        <a href="profil/index.php">
            <i class="bi bi-gear"></i> Profil Usaha
        </a>
        <a href="../auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Keluar
        </a>
    </div>
</aside>

<!-- ============ MAIN ============ -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none"
                    onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list"></i>
            </button>
            <h5>Dashboard</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($stok_menipis > 0): ?>
            <a href="barang/index.php?filter=menipis"
               class="btn btn-sm btn-outline-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= $stok_menipis ?> Stok Menipis
            </a>
            <?php endif; ?>
            <?php if ($pesanan_menunggu > 0): ?>
            <a href="pesanan/index.php"
               class="btn btn-sm btn-outline-warning">
                <i class="bi bi-bell me-1"></i>
                <?= $pesanan_menunggu ?> Pesanan Baru
            </a>
            <?php endif; ?>
            <span class="text-muted small">
                <?= formatTanggal(date('Y-m-d')) ?>
            </span>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <?php flashPesan(); ?>

        <!-- ---- STAT CARDS ---- -->

        <!-- Baris 1: POS & Online hari ini -->
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div style="font-size:.78rem;font-weight:700;color:#6b7280;
                            text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    <i class="bi bi-calendar-day me-1"></i>Hari Ini —
                    <?= formatTanggal(date('Y-m-d')) ?>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#eff6ff">
                        <i class="bi bi-calculator" style="color:#2563eb"></i>
                    </div>
                    <div>
                        <div class="label">Pendapatan POS</div>
                        <div class="value"><?= formatRupiah($pendapatan_pos_hari_ini) ?></div>
                        <div class="sub"><?= $transaksi_hari_ini ?> transaksi kasir</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#f0fdf4">
                        <i class="bi bi-bag-check" style="color:#16a34a"></i>
                    </div>
                    <div>
                        <div class="label">Pendapatan Online</div>
                        <div class="value"><?= formatRupiah($pendapatan_online_hari_ini) ?></div>
                        <div class="sub"><?= $pendapatan_online_hari_ini ?> pesanan masuk</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#fdf4ff">
                        <i class="bi bi-box-seam" style="color:#9333ea"></i>
                    </div>
                    <div>
                        <div class="label">Total Produk</div>
                        <div class="value"><?= number_format($total_produk) ?></div>
                        <div class="sub">
                            <?php if ($stok_menipis > 0): ?>
                                <span style="color:#ef4444">
                                    <?= $stok_menipis ?> stok menipis
                                </span>
                            <?php else: ?>
                                Semua stok aman
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#fff7ed">
                        <i class="bi bi-clock-history" style="color:#ea580c"></i>
                    </div>
                    <div>
                        <div class="label">Pesanan Menunggu</div>
                        <div class="value"><?= number_format($pesanan_menunggu) ?></div>
                        <div class="sub">perlu dikonfirmasi</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baris 2: Rekap bulan ini -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div style="font-size:.78rem;font-weight:700;color:#6b7280;
                            text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    <i class="bi bi-calendar-month me-1"></i>Bulan Ini —
                    <?= date('F Y') ?>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#eff6ff">
                        <i class="bi bi-cash-coin" style="color:#2563eb"></i>
                    </div>
                    <div>
                        <div class="label">Omzet POS</div>
                        <div class="value" style="font-size:1.1rem">
                            <?= formatRupiah($pendapatan_pos_bulan) ?>
                        </div>
                        <div class="sub">dari kasir</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#f0fdf4">
                        <i class="bi bi-globe" style="color:#16a34a"></i>
                    </div>
                    <div>
                        <div class="label">Omzet Online</div>
                        <div class="value" style="font-size:1.1rem">
                            <?= formatRupiah($pendapatan_online_bulan) ?>
                        </div>
                        <div class="sub">pesanan selesai</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#fefce8">
                        <i class="bi bi-graph-up-arrow" style="color:#ca8a04"></i>
                    </div>
                    <div>
                        <div class="label">Total Omzet</div>
                        <div class="value" style="font-size:1.1rem">
                            <?= formatRupiah($pendapatan_pos_bulan + $pendapatan_online_bulan) ?>
                        </div>
                        <div class="sub">POS + Online</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex gap-3 align-items-start">
                    <div class="stat-icon" style="background:#f0fdf4">
                        <i class="bi bi-percent" style="color:#16a34a"></i>
                    </div>
                    <div>
                        <div class="label">Margin POS</div>
                        <div class="value" style="font-size:1.1rem;color:#16a34a">
                            <?= formatRupiah($margin_bulan_ini) ?>
                        </div>
                        <div class="sub">
                            <?php
                            $persen_margin = $pendapatan_pos_bulan > 0
                                ? round(($margin_bulan_ini / $pendapatan_pos_bulan) * 100, 1)
                                : 0;
                            ?>
                            <?= $persen_margin ?>% dari omzet POS
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ---- GRAFIK PENJUALAN ---- -->
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-bar-chart me-2"></i>Penjualan 7 Hari Terakhir</h6>
                        <a href="laporan/penjualan.php" class="btn btn-sm btn-outline-primary">
                            Lihat Laporan
                        </a>
                    </div>
                    <div style="padding:20px">
                        <canvas id="grafikPenjualan" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-box h-100">
                    <div class="card-head">
                        <h6><i class="bi bi-trophy me-2"></i>Produk Terlaris</h6>
                    </div>
                    <?php if (empty($produk_terlaris)): ?>
                        <div class="p-4 text-center text-muted small">
                            Belum ada data penjualan
                        </div>
                    <?php else: ?>
                        <div style="padding:16px">
                            <?php foreach ($produk_terlaris as $i => $p): ?>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div style="width:24px;height:24px;border-radius:6px;
                                    background:<?= ['#2563eb','#16a34a','#9333ea','#ea580c','#0891b2'][$i] ?>;
                                    color:#fff;font-size:.75rem;font-weight:700;
                                    display:flex;align-items:center;justify-content:center;
                                    flex-shrink:0">
                                    <?= $i + 1 ?>
                                </div>
                                <div class="flex-grow-1" style="min-width:0">
                                    <div style="font-size:.85rem;font-weight:600;color:#111827;
                                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        <?= htmlspecialchars($p['nama_barang']) ?>
                                    </div>
                                    <div style="font-size:.75rem;color:#6b7280">
                                        <?= number_format($p['total_terjual']) ?>
                                        <?= htmlspecialchars($p['satuan']) ?> terjual
                                    </div>
                                </div>
                                <div style="font-size:.8rem;font-weight:600;color:#16a34a;
                                    flex-shrink:0">
                                    <?= formatRupiah($p['total_pendapatan']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ---- TABEL BAWAH ---- -->
        <div class="row g-3">

            <!-- Stok Menipis -->
            <div class="col-md-6">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Stok Menipis
                        </h6>
                        <a href="barang/index.php" class="btn btn-sm btn-outline-secondary">
                            Lihat Semua
                        </a>
                    </div>
                    <?php if (empty($list_stok_menipis)): ?>
                        <div class="p-4 text-center text-muted small">
                            <i class="bi bi-check-circle text-success d-block mb-1"
                               style="font-size:1.5rem"></i>
                            Semua stok dalam kondisi aman
                        </div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Stok</th>
                                    <th>Minimal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_stok_menipis as $s): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;font-size:.85rem">
                                            <?= htmlspecialchars($s['nama_barang']) ?>
                                        </div>
                                        <div style="font-size:.75rem;color:#9ca3af">
                                            <?= htmlspecialchars($s['kode_barang']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:#ef4444">
                                            <?= number_format($s['stok']) ?>
                                        </span>
                                    </td>
                                    <td style="color:#6b7280">
                                        <?= number_format($s['stok_minimal']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pesanan Online Terbaru -->
            <div class="col-md-6">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-bag me-2"></i>Pesanan Online Terbaru</h6>
                        <a href="pesanan/index.php" class="btn btn-sm btn-outline-secondary">
                            Lihat Semua
                        </a>
                    </div>
                    <?php if (empty($pesanan_terbaru)): ?>
                        <div class="p-4 text-center text-muted small">
                            Belum ada pesanan online
                        </div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Pembeli</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pesanan_terbaru as $p): ?>
                                <tr>
                                    <td>
                                        <a href="pesanan/detail.php?id=<?= $p['no_pesanan'] ?>"
                                           style="font-weight:600;font-size:.82rem;color:#2563eb;
                                                  text-decoration:none">
                                            <?= htmlspecialchars($p['no_pesanan']) ?>
                                        </a>
                                        <div style="font-size:.72rem;color:#9ca3af">
                                            <?= formatTanggalJam($p['created_at']) ?>
                                        </div>
                                    </td>
                                    <td style="font-size:.85rem">
                                        <?= htmlspecialchars($p['nama_pembeli']) ?>
                                    </td>
                                    <td style="font-size:.85rem;font-weight:600">
                                        <?= formatRupiah($p['total_harga']) ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?= $p['status'] ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transaksi POS Terbaru -->
            <div class="col-12">
                <div class="card-box">
                    <div class="card-head">
                        <h6><i class="bi bi-receipt me-2"></i>Transaksi POS Terbaru</h6>
                        <a href="laporan/penjualan.php" class="btn btn-sm btn-outline-secondary">
                            Lihat Laporan
                        </a>
                    </div>
                    <?php if (empty($transaksi_terbaru)): ?>
                        <div class="p-4 text-center text-muted small">
                            Belum ada transaksi POS
                        </div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Kasir</th>
                                    <th>Total</th>
                                    <th>Margin</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transaksi_terbaru as $t): ?>
                                <tr>
                                    <td>
                                        <span style="font-weight:600;font-size:.85rem">
                                            <?= htmlspecialchars($t['no_transaksi']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:.85rem">
                                        <?= htmlspecialchars($t['nama_kasir']) ?>
                                    </td>
                                    <td style="font-weight:600;font-size:.85rem">
                                        <?= formatRupiah($t['total_harga']) ?>
                                    </td>
                                    <td style="font-size:.85rem;color:#16a34a;font-weight:600">
                                        <?= formatRupiah($t['total_margin']) ?>
                                    </td>
                                    <td style="font-size:.8rem;color:#6b7280">
                                        <?= formatTanggalJam($t['created_at']) ?>
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
// ---- GRAFIK PENJUALAN ----
const ctx = document.getElementById('grafikPenjualan').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($grafik_labels) ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?= json_encode($grafik_total) ?>,
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
                backgroundColor: 'rgba(22,163,74,0.1)',
                borderWidth: 2,
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
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': Rp ' +
                            ctx.raw.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(val) {
                        if (val >= 1000000)
                            return 'Rp ' + (val/1000000).toFixed(1) + 'jt';
                        if (val >= 1000)
                            return 'Rp ' + (val/1000).toFixed(0) + 'rb';
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