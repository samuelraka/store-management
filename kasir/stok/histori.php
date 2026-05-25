<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

$search = bersihkan($_GET['search'] ?? '');
$tipe   = $_GET['tipe'] ?? '';
$dari   = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

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

// Summary hari ini
$stmt = $pdo->prepare(
    "SELECT
        SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) as masuk,
        SUM(CASE WHEN tipe IN ('keluar','penjualan','pesanan')
            THEN jumlah ELSE 0 END) as keluar,
        COUNT(*) as total
     FROM stok_mutasi
     WHERE DATE(created_at) = CURDATE()"
);
$stmt->execute();
$summary = $stmt->fetch();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Stok</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w:220px; --primary:#2563eb; }
        body { background:#f3f4f6; font-family:'Segoe UI',sans-serif; }
        .sidebar {
            position:fixed;top:0;left:0;width:var(--sidebar-w);
            height:100vh;background:#1e2a3b;overflow-y:auto;z-index:1000;
        }
        .sidebar-brand { padding:20px 16px 16px; border-bottom:1px solid rgba(255,255,255,.1); }
        .sidebar-brand h6 { color:#fff;font-weight:700;margin:0;font-size:.95rem; }
        .sidebar-brand small { color:#94a3b8;font-size:.72rem; }
        .sidebar-menu { padding:12px 0; }
        .menu-label {
            padding:8px 16px 4px;font-size:.68rem;font-weight:600;
            color:#64748b;text-transform:uppercase;letter-spacing:.8px;
        }
        .sidebar a {
            display:flex;align-items:center;gap:10px;padding:10px 16px;
            color:#94a3b8;text-decoration:none;font-size:.875rem;
            border-left:3px solid transparent;transition:all .2s;
        }
        .sidebar a:hover,.sidebar a.active {
            color:#fff;background:rgba(255,255,255,.06);border-left-color:var(--primary);
        }
        .sidebar a i { font-size:1rem;width:18px; }
        .main { margin-left:var(--sidebar-w);min-height:100vh; }
        .topbar {
            background:#fff;border-bottom:1px solid #e5e7eb;padding:14px 24px;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;
        }
        .topbar h5 { margin:0;font-weight:600;color:#1f2937; }
        .content { padding:24px; }
        .stat-card {
            background:#fff;border:1px solid #e5e7eb;
            border-radius:12px;padding:16px 20px;
        }
        .filter-bar {
            background:#fff;border:1px solid #e5e7eb;
            border-radius:10px;padding:16px 20px;margin-bottom:20px;
        }
        .card-box {
            background:#fff;border:1px solid #e5e7eb;
            border-radius:12px;overflow:hidden;
        }
        .tbl { width:100%;font-size:.85rem; }
        .tbl th {
            background:#f9fafb;color:#6b7280;font-weight:600;
            padding:12px 16px;border-bottom:1px solid #e5e7eb;white-space:nowrap;
        }
        .tbl td {
            padding:12px 16px;border-bottom:1px solid #f3f4f6;
            color:#374151;vertical-align:middle;
        }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:#fafafa; }
        .badge-tipe {
            padding:3px 10px;border-radius:20px;
            font-size:.75rem;font-weight:600;
        }
        .tipe-masuk     { background:#dcfce7;color:#166534; }
        .tipe-keluar    { background:#fee2e2;color:#991b1b; }
        .tipe-penjualan { background:#dbeafe;color:#1e40af; }
        .tipe-pesanan   { background:#f3e8ff;color:#6b21a8; }
        .stok-plus  { color:#16a34a;font-weight:700; }
        .stok-minus { color:#dc2626;font-weight:700; }
        .legenda {
            display:flex;flex-wrap:wrap;gap:8px;
            margin-bottom:16px;
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-1"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?> &middot; Kasir</small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Menu</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="../pos/index.php"><i class="bi bi-calculator"></i> POS / Transaksi</a>
        <div class="menu-label">Inventori</div>
        <a href="../pembelian/index.php">
            <i class="bi bi-box-arrow-in-down"></i> Barang Masuk
        </a>
        <a href="../pembelian/tambah.php">
            <i class="bi bi-plus-circle"></i> Input Barang Masuk
        </a>
        <a href="histori.php" class="active">
            <i class="bi bi-clock-history"></i> Histori Stok
        </a>
        <div class="menu-label">Akun</div>
        <a href="../../auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Keluar
        </a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <h5>Histori Pergerakan Stok</h5>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- Stat Hari Ini -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">
                        Total Mutasi Hari Ini
                    </div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= number_format($summary['total'] ?? 0) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">pergerakan stok</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">
                        Stok Masuk Hari Ini
                    </div>
                    <div style="font-size:1.6rem;font-weight:700;color:#16a34a">
                        +<?= number_format($summary['masuk'] ?? 0) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">item masuk</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.78rem;color:#6b7280">
                        Stok Keluar Hari Ini
                    </div>
                    <div style="font-size:1.6rem;font-weight:700;color:#dc2626">
                        -<?= number_format($summary['keluar'] ?? 0) ?>
                    </div>
                    <div style="font-size:.72rem;color:#9ca3af">item keluar</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Cari Barang</label>
                    <input type="text" name="search"
                           class="form-control form-control-sm"
                           placeholder="Nama barang..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Tipe</label>
                    <select name="tipe" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="masuk"
                            <?= $tipe==='masuk'?'selected':'' ?>>Masuk</option>
                        <option value="keluar"
                            <?= $tipe==='keluar'?'selected':'' ?>>Keluar</option>
                        <option value="penjualan"
                            <?= $tipe==='penjualan'?'selected':'' ?>>Penjualan</option>
                        <option value="pesanan"
                            <?= $tipe==='pesanan'?'selected':'' ?>>Pesanan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Dari</label>
                    <input type="date" name="dari"
                           class="form-control form-control-sm"
                           value="<?= $dari ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Sampai</label>
                    <input type="date" name="sampai"
                           class="form-control form-control-sm"
                           value="<?= $sampai ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="histori.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Legenda -->
        <div class="legenda">
            <span class="badge-tipe tipe-masuk">
                <i class="bi bi-arrow-up me-1"></i>Masuk — dari supplier
            </span>
            <span class="badge-tipe tipe-penjualan">
                <i class="bi bi-arrow-down me-1"></i>Penjualan — via POS
            </span>
            <span class="badge-tipe tipe-pesanan">
                <i class="bi bi-arrow-down me-1"></i>Pesanan — order online
            </span>
            <span class="badge-tipe tipe-keluar">
                <i class="bi bi-arrow-down me-1"></i>Keluar — manual
            </span>
        </div>

        <!-- Tabel -->
        <div class="card-box">
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;
                        font-size:.85rem;color:#6b7280">
                Menampilkan
                <strong><?= count($mutasi_list) ?></strong> mutasi terakhir
            </div>

            <?php if (empty($mutasi_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clock-history d-block mb-2"
                       style="font-size:2.5rem"></i>
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
                                $selisih = $m['stok_sesudah'] - $m['stok_sebelum'];
                            ?>
                            <tr>
                                <td style="font-size:.78rem;color:#6b7280;
                                           white-space:nowrap">
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
                                    <span style="color:#9ca3af;font-size:.78rem;
                                                 font-weight:400">
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
                                    <span class="<?= $selisih >= 0
                                        ? 'stok-plus' : 'stok-minus' ?>">
                                        <?= $selisih >= 0 ? '+' : '' ?>
                                        <?= number_format($selisih) ?>
                                    </span>
                                </td>
                                <td style="font-size:.8rem;color:#6b7280;
                                           max-width:160px">
                                    <div style="white-space:nowrap;overflow:hidden;
                                                text-overflow:ellipsis;max-width:150px"
                                         title="<?= htmlspecialchars($m['keterangan'] ?? '') ?>">
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