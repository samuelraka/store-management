<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Data tidak ditemukan', 'error');

// Header pembelian
$stmt = $pdo->prepare(
    "SELECT p.*, s.nama_supplier, s.no_telepon as telp_supplier,
            s.alamat as alamat_supplier, u.nama as nama_user
     FROM pembelian p
     LEFT JOIN supplier s ON p.supplier_id = s.id
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.id = ?"
);
$stmt->execute([$id]);
$pembelian = $stmt->fetch();
if (!$pembelian) redirect('index.php', 'Data tidak ditemukan', 'error');

// Detail barang
$stmt = $pdo->prepare(
    "SELECT pd.*, b.nama_barang, b.kode_barang, b.satuan
     FROM pembelian_detail pd
     JOIN barang b ON pd.barang_id = b.id
     WHERE pd.pembelian_id = ?"
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembelian</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/pembelian/detail.css">
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="index.php" style="color:var(--primary)">Barang Masuk</a>
                </li>
                <li class="breadcrumb-item active">
                    <?= htmlspecialchars($pembelian['no_pembelian']) ?>
                </li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <button onclick="window.print()"
                    class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    <div class="content">
        <div class="row g-3">

            <!-- Info Pembelian -->
            <div class="col-md-6">
                <div class="info-card">
                    <h6 style="font-weight:700;color:#111827;margin-bottom:16px">
                        <i class="bi bi-receipt me-2 text-primary"></i>
                        Info Pembelian
                    </h6>
                    <div class="info-row">
                        <span class="info-label">No. Pembelian</span>
                        <span class="info-value">
                            <code><?= htmlspecialchars($pembelian['no_pembelian']) ?></code>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Masuk</span>
                        <span class="info-value">
                            <?= formatTanggal($pembelian['tanggal_masuk']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Diinput Oleh</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pembelian['nama_user']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Keterangan</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pembelian['keterangan'] ?? '-') ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Nilai</span>
                        <span class="info-value" style="color:#16a34a;font-size:1.1rem">
                            <?= formatRupiah($pembelian['total_harga']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Info Supplier -->
            <div class="col-md-6">
                <div class="info-card">
                    <h6 style="font-weight:700;color:#111827;margin-bottom:16px">
                        <i class="bi bi-truck me-2 text-primary"></i>
                        Info Supplier
                    </h6>
                    <div class="info-row">
                        <span class="info-label">Nama Supplier</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pembelian['nama_supplier']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">No. Telepon</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pembelian['telp_supplier'] ?? '-') ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Alamat</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pembelian['alamat_supplier'] ?? '-') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabel Detail Barang -->
            <div class="col-12">
                <div class="info-card" style="padding:0;overflow:hidden">
                    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
                        <h6 style="margin:0;font-weight:700;color:#111827">
                            <i class="bi bi-list-ul me-2 text-primary"></i>
                            Detail Barang
                        </h6>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Jumlah</th>
                                    <th>Satuan</th>
                                    <th>Harga Beli</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $i => $d): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <code style="background:#f3f4f6;padding:2px 6px;
                                              border-radius:4px;font-size:.78rem">
                                            <?= htmlspecialchars($d['kode_barang']) ?>
                                        </code>
                                    </td>
                                    <td style="font-weight:600">
                                        <?= htmlspecialchars($d['nama_barang']) ?>
                                    </td>
                                    <td><?= number_format($d['jumlah']) ?></td>
                                    <td style="color:#6b7280"><?= htmlspecialchars($d['satuan']) ?></td>
                                    <td><?= formatRupiah($d['harga_beli']) ?></td>
                                    <td style="font-weight:600">
                                        <?= formatRupiah($d['subtotal']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="6" style="text-align:right">Total</td>
                                    <td style="color:#16a34a;font-size:1rem">
                                        <?= formatRupiah($pembelian['total_harga']) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>