<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

$stmt = $pdo->prepare(
    "SELECT p.*, u.nama as nama_pembeli,
            u.email as email_pembeli,
            u.no_telepon as telp_pembeli
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     WHERE p.id = ?"
);
$stmt->execute([$id]);
$pesanan = $stmt->fetch();
if (!$pesanan) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

// Detail barang
$stmt = $pdo->prepare(
    "SELECT pd.*, b.nama_barang, b.kode_barang, b.satuan
     FROM pesanan_detail pd
     JOIN barang b ON pd.barang_id = b.id
     WHERE pd.pesanan_id = ?"
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

// Info pengiriman
$stmt = $pdo->prepare("SELECT * FROM pengiriman WHERE pesanan_id = ?");
$stmt->execute([$id]);
$pengiriman = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();

// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi       = $_POST['aksi'] ?? '';
    $new_status = bersihkan($_POST['status'] ?? '');

    if ($aksi === 'update_status') {
        $allowed = ['menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];
        if (in_array($new_status, $allowed)) {
            $pdo->prepare(
                "UPDATE pesanan SET status = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$new_status, $id]);

            // Kalau dibatalkan, kembalikan stok
            if ($new_status === 'dibatalkan' && $pesanan['status'] !== 'dibatalkan') {
                foreach ($details as $d) {
                    $stok_stmt = $pdo->prepare("SELECT stok FROM barang WHERE id = ?");
                    $stok_stmt->execute([$d['barang_id']]);
                    $stok_before = (int)$stok_stmt->fetchColumn();
                    $stok_after  = $stok_before + $d['jumlah'];

                    $pdo->prepare(
                        "UPDATE barang SET stok = stok + ? WHERE id = ?"
                    )->execute([$d['jumlah'], $d['barang_id']]);

                    $pdo->prepare(
                        "INSERT INTO stok_mutasi
                         (barang_id, user_id, tipe, jumlah, stok_sebelum,
                          stok_sesudah, keterangan)
                         VALUES (?, ?, 'masuk', ?, ?, ?, ?)"
                    )->execute([
                        $d['barang_id'], $user['id'],
                        $d['jumlah'], $stok_before, $stok_after,
                        'Pembatalan pesanan: ' . $pesanan['no_pesanan']
                    ]);
                }
            }

            redirect(
                'detail.php?id=' . $id,
                'Status pesanan diupdate ke: ' . ucfirst($new_status)
            );
        }
    }
}

// Status options berdasarkan status saat ini
$next_statuses = match($pesanan['status']) {
    'menunggu'   => ['diproses', 'dibatalkan'],
    'diproses'   => ['dikirim', 'dibatalkan'],
    'dikirim'    => ['selesai'],
    default      => []
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/pesanan/detail.css">
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
        <a href="index.php" class="active"><i class="bi bi-bag-check"></i> Pesanan Online</a>
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
                    <a href="index.php" style="color:var(--primary)">Pesanan Online</a>
                </li>
                <li class="breadcrumb-item active">
                    <?= htmlspecialchars($pesanan['no_pesanan']) ?>
                </li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <span class="badge-status badge-<?= $pesanan['status'] ?>">
                <?= ucfirst($pesanan['status']) ?>
            </span>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <div class="row g-3">
            <!-- Kiri -->
            <div class="col-md-8">

                <!-- Action Box -->
                <?php if (!empty($next_statuses)): ?>
                <div class="action-box mb-3">
                    <div style="font-size:.875rem;font-weight:600;color:#92400e;margin-bottom:10px">
                        <i class="bi bi-lightning-charge me-1"></i>
                        Update Status Pesanan
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($next_statuses as $ns): ?>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="aksi" value="update_status">
                            <input type="hidden" name="status" value="<?= $ns ?>">
                            <?php
                            $btn_class = match($ns) {
                                'diproses'   => 'btn-proses',
                                'dikirim'    => 'btn-kirim',
                                'selesai'    => 'btn-selesai',
                                'dibatalkan' => 'btn-batal',
                                default      => 'btn-proses'
                            };
                            $btn_icon = match($ns) {
                                'diproses'   => 'bi-gear',
                                'dikirim'    => 'bi-truck',
                                'selesai'    => 'bi-check-circle',
                                'dibatalkan' => 'bi-x-circle',
                                default      => 'bi-arrow-right'
                            };
                            $confirm = $ns === 'dibatalkan'
                                ? "onclick=\"return confirm('Batalkan pesanan ini? Stok akan dikembalikan.')\""
                                : '';
                            ?>
                            <button type="submit"
                                    class="btn-status <?= $btn_class ?>"
                                    <?= $confirm ?>>
                                <i class="bi <?= $btn_icon ?> me-1"></i>
                                <?= ucfirst($ns) ?>
                            </button>
                        </form>
                        <?php endforeach; ?>

                        <?php if ($pesanan['status'] === 'diproses'): ?>
                        <a href="pengiriman.php?id=<?= $id ?>"
                           style="padding:8px 20px;border-radius:8px;font-weight:600;
                                  font-size:.85rem;background:#0891b2;color:#fff;
                                  text-decoration:none">
                            <i class="bi bi-truck me-1"></i>Input Data Kirim
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Detail Barang -->
                <div class="info-card" style="padding:0;overflow:hidden">
                    <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                        <h6 style="margin:0;font-weight:700;color:#111827">
                            <i class="bi bi-list-ul me-2"></i>Detail Barang
                        </h6>
                    </div>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Barang</th>
                                <th>Jumlah</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $i => $d): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <div style="font-weight:600">
                                        <?= htmlspecialchars($d['nama_barang']) ?>
                                    </div>
                                    <div style="font-size:.75rem;color:#9ca3af">
                                        <?= htmlspecialchars($d['kode_barang']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= number_format($d['jumlah']) ?>
                                    <?= htmlspecialchars($d['satuan']) ?>
                                </td>
                                <td><?= formatRupiah($d['harga_jual']) ?></td>
                                <td style="font-weight:700">
                                    <?= formatRupiah($d['subtotal']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="4"
                                    style="text-align:right;font-weight:700;
                                           background:#f9fafb">Total</td>
                                <td style="font-weight:700;color:#16a34a;
                                           font-size:1rem;background:#f9fafb">
                                    <?= formatRupiah($pesanan['total_harga']) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kanan -->
            <div class="col-md-4">

                <!-- Info Pesanan -->
                <div class="info-card">
                    <div class="card-title">Info Pesanan</div>
                    <div class="info-row">
                        <span style="color:#6b7280">No. Pesanan</span>
                        <code style="font-size:.78rem">
                            <?= htmlspecialchars($pesanan['no_pesanan']) ?>
                        </code>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Tanggal</span>
                        <span style="font-weight:600;font-size:.82rem">
                            <?= formatTanggalJam($pesanan['created_at']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Status</span>
                        <span class="badge-status badge-<?= $pesanan['status'] ?>">
                            <?= ucfirst($pesanan['status']) ?>
                        </span>
                    </div>
                    <?php if ($pesanan['catatan']): ?>
                    <div class="info-row">
                        <span style="color:#6b7280">Catatan</span>
                        <span style="font-size:.82rem;text-align:right;max-width:160px">
                            <?= htmlspecialchars($pesanan['catatan']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Info Pembeli -->
                <div class="info-card">
                    <div class="card-title">Info Pembeli</div>
                    <div class="info-row">
                        <span style="color:#6b7280">Nama</span>
                        <span style="font-weight:600">
                            <?= htmlspecialchars($pesanan['nama_pembeli']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Email</span>
                        <span style="font-size:.82rem">
                            <?= htmlspecialchars($pesanan['email_pembeli']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Telepon</span>
                        <span><?= htmlspecialchars($pesanan['telp_pembeli'] ?? '-') ?></span>
                    </div>
                </div>

                <!-- Info Pengiriman -->
                <?php if ($pengiriman): ?>
                <div class="info-card">
                    <div class="card-title">Info Pengiriman</div>
                    <div class="info-row">
                        <span style="color:#6b7280">Penerima</span>
                        <span style="font-weight:600">
                            <?= htmlspecialchars($pengiriman['nama_penerima']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Telepon</span>
                        <span><?= htmlspecialchars($pengiriman['no_telepon']) ?></span>
                    </div>
                    <div class="info-row">
                        <span style="color:#6b7280">Alamat</span>
                        <span style="font-size:.82rem;text-align:right;max-width:160px">
                            <?= htmlspecialchars($pengiriman['alamat_lengkap']) ?>
                        </span>
                    </div>
                    <?php if ($pengiriman['kota_tujuan']): ?>
                    <div class="info-row">
                        <span style="color:#6b7280">Kota</span>
                        <span><?= htmlspecialchars($pengiriman['kota_tujuan']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pengiriman['kurir']): ?>
                    <div class="info-row">
                        <span style="color:#6b7280">Kurir</span>
                        <span style="font-weight:600">
                            <?= htmlspecialchars($pengiriman['kurir']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pengiriman['no_resi']): ?>
                    <div class="info-row">
                        <span style="color:#6b7280">No. Resi</span>
                        <code style="font-size:.78rem">
                            <?= htmlspecialchars($pengiriman['no_resi']) ?>
                        </code>
                    </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <a href="pengiriman.php?id=<?= $id ?>"
                           class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-pencil me-1"></i>Edit Data Kirim
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>