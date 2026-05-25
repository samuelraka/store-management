<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

$search  = bersihkan($_GET['search'] ?? '');
$dari    = $_GET['dari'] ?? '';
$sampai  = $_GET['sampai'] ?? '';

$where  = "WHERE p.user_id = ?";
$params = [$user['id']];

// Kalau superadmin lihat semua, kasir lihat miliknya saja
if ($_SESSION['role'] === 'superadmin') {
    $where  = "WHERE 1=1";
    $params = [];
}

if ($search) {
    $where   .= " AND p.no_pembelian LIKE ?";
    $params[] = "%$search%";
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

$total_nilai = array_sum(array_column($pembelian_list, 'total_harga'));

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Masuk</title>
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
        .btn-aksi {
            padding:4px 10px;border-radius:6px;font-size:.78rem;
            border:1px solid;text-decoration:none;
            display:inline-flex;align-items:center;gap:4px;
        }
        .btn-detail { border-color:#bfdbfe;color:#1d4ed8;background:#fff; }
        .btn-detail:hover { background:#eff6ff; }
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
        <a href="index.php" class="active"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="tambah.php"><i class="bi bi-plus-circle"></i> Input Barang Masuk</a>
        <a href="../stok/histori.php"><i class="bi bi-clock-history"></i> Histori Stok</a>
        <div class="menu-label">Akun</div>
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
                    <div style="font-size:.8rem;color:#6b7280">Total Pembelian</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= number_format(count($pembelian_list)) ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">transaksi ditemukan</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.8rem;color:#6b7280">Total Nilai</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#111827">
                        <?= formatRupiah($total_nilai) ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">modal dikeluarkan</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="font-size:.8rem;color:#6b7280">Hari Ini</div>
                    <?php
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM pembelian
                         WHERE DATE(created_at) = CURDATE()
                         AND user_id = ?"
                    );
                    $stmt->execute([$user['id']]);
                    $hari_ini = $stmt->fetchColumn();
                    ?>
                    <div style="font-size:1.6rem;font-weight:700;color:#111827">
                        <?= $hari_ini ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af">input hari ini</div>
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
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" class="form-control form-control-sm"
                           value="<?= $dari ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="form-control form-control-sm"
                           value="<?= $sampai ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
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
                    <i class="bi bi-box-arrow-in-down d-block mb-2"
                       style="font-size:2.5rem"></i>
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
                                    <a href="../../admin/pembelian/detail.php?id=<?= $p['id'] ?>"
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