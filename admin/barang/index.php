<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

// Filter
$filter  = $_GET['filter'] ?? '';
$search  = bersihkan($_GET['search'] ?? '');
$kat_id  = (int)($_GET['kategori'] ?? 0);

// Query barang
$where  = "WHERE 1=1";
$params = [];

if ($search) {
    $where   .= " AND (b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($kat_id) {
    $where   .= " AND b.kategori_id = ?";
    $params[] = $kat_id;
}
if ($filter === 'menipis') {
    $where .= " AND b.stok <= b.stok_minimal";
}
if ($filter === 'habis') {
    $where .= " AND b.stok = 0";
}

$stmt = $pdo->prepare(
    "SELECT b.*, k.nama as kategori, s.nama_supplier
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     LEFT JOIN supplier s ON b.supplier_id = s.id
     $where
     ORDER BY b.created_at DESC"
);
$stmt->execute($params);
$barang_list = $stmt->fetchAll();

// Daftar kategori untuk filter
$kategoris = $pdo->query(
    "SELECT * FROM kategori_barang ORDER BY nama"
)->fetchAll();

// Profil toko
$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang — <?= htmlspecialchars($profil['nama_toko'] ?? '') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/barang/style.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h6><i class="bi bi-shop me-2"></i><?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?></h6>
        <small><?= htmlspecialchars($user['nama']) ?> &middot; Super Admin</small>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Utama</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>

        <div class="menu-label">Katalog</div>
        <a href="index.php" class="active"><i class="bi bi-box-seam"></i> Data Barang</a>
        <a href="../kategori/index.php"><i class="bi bi-tags"></i> Kategori</a>
        <a href="../supplier/index.php"><i class="bi bi-truck"></i> Supplier</a>

        <div class="menu-label">Inventori</div>
        <a href="../pembelian/index.php"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
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

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none"
                    onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list"></i>
            </button>
            <h5>Data Barang</h5>
        </div>
        <a href="tambah.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Barang
        </a>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-600 mb-1">Cari Barang</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Nama atau kode barang..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Kategori</label>
                    <select name="kategori" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoris as $k): ?>
                        <option value="<?= $k['id'] ?>"
                            <?= $kat_id == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Filter Stok</label>
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">Semua Stok</option>
                        <option value="menipis" <?= $filter==='menipis'?'selected':'' ?>>Stok Menipis</option>
                        <option value="habis"   <?= $filter==='habis'  ?'selected':'' ?>>Stok Habis</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- TABEL BARANG -->
        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;
                        display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:.85rem;color:#6b7280">
                    Menampilkan <strong><?= count($barang_list) ?></strong> barang
                    <?php if ($filter === 'menipis'): ?>
                        <span class="badge bg-warning text-dark ms-1">Stok Menipis</span>
                    <?php elseif ($filter === 'habis'): ?>
                        <span class="badge bg-danger ms-1">Stok Habis</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($barang_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-seam d-block mb-2" style="font-size:2.5rem"></i>
                    <p class="mb-2">Belum ada barang</p>
                    <a href="tambah.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Barang Pertama
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Supplier</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Margin</th>
                                <th>Stok</th>
                                <th>Tgl Masuk</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($barang_list as $b):
                                $margin_persen = persentaseMargin($b['harga_beli'], $b['harga_jual']);
                                if ($b['stok'] == 0) {
                                    $stok_class = 'stok-habis';
                                    $stok_label = 'Habis';
                                } elseif ($b['stok'] <= $b['stok_minimal']) {
                                    $stok_class = 'stok-menipis';
                                    $stok_label = 'Menipis';
                                } else {
                                    $stok_class = 'stok-aman';
                                    $stok_label = 'Aman';
                                }
                            ?>
                            <tr>
                                <td>
                                    <code style="font-size:.78rem;background:#f3f4f6;
                                          padding:2px 6px;border-radius:4px">
                                        <?= htmlspecialchars($b['kode_barang']) ?>
                                    </code>
                                </td>
                                <td>
                                    <div style="font-weight:600">
                                        <?= htmlspecialchars($b['nama_barang']) ?>
                                    </div>
                                    <div style="font-size:.75rem;color:#9ca3af">
                                        <?= htmlspecialchars($b['satuan']) ?>
                                    </div>
                                </td>
                                <td style="font-size:.82rem;color:#6b7280">
                                    <?= htmlspecialchars($b['kategori'] ?? '-') ?>
                                </td>
                                <td style="font-size:.82rem;color:#6b7280">
                                    <?= htmlspecialchars($b['nama_supplier'] ?? '-') ?>
                                </td>
                                <td style="font-weight:600">
                                    <?= formatRupiah($b['harga_beli']) ?>
                                </td>
                                <td style="font-weight:600">
                                    <?= formatRupiah($b['harga_jual']) ?>
                                </td>
                                <td>
                                    <span style="color:#16a34a;font-weight:600">
                                        <?= $margin_persen ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="font-weight:700">
                                            <?= number_format($b['stok']) ?>
                                        </span>
                                        <span class="badge-stok <?= $stok_class ?>">
                                            <?= $stok_label ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="font-size:.82rem;color:#6b7280">
                                    <?= $b['tanggal_masuk']
                                        ? formatTanggal($b['tanggal_masuk'])
                                        : '-' ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="edit.php?id=<?= $b['id'] ?>"
                                           class="btn-aksi btn-edit">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button onclick="konfirmasiHapus(<?= $b['id'] ?>,
                                                    '<?= htmlspecialchars(addslashes($b['nama_barang'])) ?>')"
                                                class="btn-aksi btn-hapus">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </div>
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

<!-- Modal Hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">Hapus Barang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-0">
                    Yakin hapus barang <strong id="namaBarangHapus"></strong>?
                    Data tidak bisa dikembalikan.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Batal</button>
                <a href="#" id="linkHapus" class="btn btn-sm btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function konfirmasiHapus(id, nama) {
    document.getElementById('namaBarangHapus').textContent = nama;
    document.getElementById('linkHapus').href = 'hapus.php?id=' + id;
    new bootstrap.Modal(document.getElementById('modalHapus')).show();
}
</script>
</body>
</html>