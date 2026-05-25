<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$search = bersihkan($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where   .= " AND (nama_supplier LIKE ? OR kota LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare(
    "SELECT s.*,
            COUNT(DISTINCT b.id) as total_produk
     FROM supplier s
     LEFT JOIN barang b ON s.id = b.supplier_id
     $where
     GROUP BY s.id
     ORDER BY s.created_at DESC"
);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/supplier/index.css">
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
        <a href="index.php" class="active"><i class="bi bi-truck"></i> Supplier</a>
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

<div class="main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <h5>Supplier</h5>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-1"></i>Tambah Supplier
        </button>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Cari nama supplier atau kota..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="card-box">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <span style="font-size:.85rem;color:#6b7280">
                    Menampilkan <strong><?= count($suppliers) ?></strong> supplier
                </span>
            </div>
            <?php if (empty($suppliers)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-truck d-block mb-2" style="font-size:2.5rem"></i>
                    <p class="mb-2">Belum ada supplier</p>
                    <button class="btn btn-primary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Supplier
                    </button>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Nama Supplier</th>
                                <th>No. Telepon</th>
                                <th>Kota</th>
                                <th>Alamat</th>
                                <th>Total Produk</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600">
                                        <?= htmlspecialchars($s['nama_supplier']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($s['no_telepon'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['kota'] ?? '-') ?></td>
                                <td style="max-width:200px">
                                    <div style="white-space:nowrap;overflow:hidden;
                                                text-overflow:ellipsis;max-width:180px">
                                        <?= htmlspecialchars($s['alamat'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600">
                                        <?= number_format($s['total_produk']) ?>
                                    </span>
                                    <span style="color:#9ca3af;font-size:.78rem"> produk</span>
                                </td>
                                <td>
                                    <?php if ($s['is_aktif']): ?>
                                        <span class="badge-aktif">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge-nonaktif">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button onclick="editSupplier(<?= htmlspecialchars(json_encode($s)) ?>)"
                                                class="btn-aksi btn-edit">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button onclick="konfirmasiHapus(<?= $s['id'] ?>, '<?= addslashes($s['nama_supplier']) ?>')"
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="simpan.php">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header">
                    <h6 class="modal-title">Tambah Supplier</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="nama_supplier" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telepon" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="simpan.php">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h6 class="modal-title">Edit Supplier</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="nama_supplier" id="editNama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telepon" id="editTelepon" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" id="editKota" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" id="editAlamat" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select name="is_aktif" id="editAktif" class="form-select">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">Hapus Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-0">
                    Yakin hapus supplier <strong id="namaHapus"></strong>?
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
function editSupplier(data) {
    document.getElementById('editId').value       = data.id;
    document.getElementById('editNama').value     = data.nama_supplier;
    document.getElementById('editTelepon').value  = data.no_telepon ?? '';
    document.getElementById('editKota').value     = data.kota ?? '';
    document.getElementById('editAlamat').value   = data.alamat ?? '';
    document.getElementById('editAktif').value    = data.is_aktif;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
function konfirmasiHapus(id, nama) {
    document.getElementById('namaHapus').textContent = nama;
    document.getElementById('linkHapus').href = 'simpan.php?aksi=hapus&id=' + id;
    new bootstrap.Modal(document.getElementById('modalHapus')).show();
}
</script>
</body>
</html>