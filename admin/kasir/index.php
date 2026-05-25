<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$stmt = $pdo->query(
    "SELECT u.*,
            COUNT(DISTINCT t.id) as total_transaksi,
            COALESCE(SUM(t.total_harga), 0) as total_penjualan
     FROM users u
     LEFT JOIN transaksi t ON u.id = t.user_id AND t.status = 'selesai'
     WHERE u.role = 'kasir'
     GROUP BY u.id
     ORDER BY u.created_at DESC"
);
$kasir_list = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kasir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/kasir/style.css">
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
        <a href="../pesanan/index.php"><i class="bi bi-bag-check"></i> Pesanan Online</a>
        <div class="menu-label">Laporan</div>
        <a href="../laporan/penjualan.php"><i class="bi bi-bar-chart-line"></i> Lap. Penjualan</a>
        <a href="../laporan/produk.php"><i class="bi bi-pie-chart"></i> Lap. Produk</a>
        <a href="../laporan/kasir.php"><i class="bi bi-person-badge"></i> Lap. Kasir</a>
        <div class="menu-label">Pengaturan</div>
        <a href="index.php" class="active"><i class="bi bi-people"></i> Kelola Kasir</a>
        <a href="../profil/index.php"><i class="bi bi-gear"></i> Profil Usaha</a>
        <a href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <h5>Kelola Kasir</h5>
        <button class="btn btn-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kasir
        </button>
    </div>

    <div class="content">
        <?php flashPesan(); ?>

        <div class="kasir-grid">

            <!-- Card Tambah -->
            <div class="tambah-card"
                 data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-person-plus"></i>
                <span>Tambah Kasir Baru</span>
            </div>

            <?php foreach ($kasir_list as $k): ?>
            <div class="kasir-card">
                <!-- Header -->
                <div class="d-flex gap-3 align-items-center mb-3">
                    <div class="kasir-avatar">
                        <?php if ($k['foto']): ?>
                            <img src="../../assets/img/uploads/<?= htmlspecialchars($k['foto']) ?>"
                                 alt="foto">
                        <?php else: ?>
                            <?= strtoupper(substr($k['nama'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <div style="font-weight:700;color:#111827;font-size:.95rem">
                            <?= htmlspecialchars($k['nama']) ?>
                        </div>
                        <div style="font-size:.78rem;color:#6b7280">
                            <?= htmlspecialchars($k['email']) ?>
                        </div>
                        <div class="mt-1">
                            <?php if ($k['is_aktif']): ?>
                                <span class="badge-aktif">Aktif</span>
                            <?php else: ?>
                                <span class="badge-nonaktif">Nonaktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="stat-mini mb-2">
                    <span style="font-size:.78rem;color:#6b7280">Total Transaksi</span>
                    <span style="font-weight:700;color:#111827">
                        <?= number_format($k['total_transaksi']) ?>x
                    </span>
                </div>
                <div class="stat-mini mb-3">
                    <span style="font-size:.78rem;color:#6b7280">Total Penjualan</span>
                    <span style="font-weight:700;color:#16a34a">
                        <?= formatRupiah($k['total_penjualan']) ?>
                    </span>
                </div>

                <!-- Aksi -->
                <div class="d-flex gap-2">
                    <button onclick="editKasir(<?= htmlspecialchars(json_encode($k)) ?>)"
                            class="btn-aksi btn-edit flex-grow-1 justify-content-center">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button onclick="toggleAktif(<?= $k['id'] ?>, <?= $k['is_aktif'] ?>, '<?= addslashes($k['nama']) ?>')"
                            class="btn-aksi <?= $k['is_aktif'] ? 'btn-hapus' : 'btn-edit' ?> flex-grow-1 justify-content-center">
                        <i class="bi bi-<?= $k['is_aktif'] ? 'x-circle' : 'check-circle' ?>"></i>
                        <?= $k['is_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- Modal Tambah Kasir -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="simpan.php" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header">
                    <h6 class="modal-title">Tambah Kasir</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <div id="previewFoto" style="width:80px;height:80px;border-radius:50%;
                             background:#eff6ff;display:flex;align-items:center;
                             justify-content:center;font-size:2rem;color:var(--primary);
                             margin:0 auto 8px;overflow:hidden">
                            <i class="bi bi-person"></i>
                        </div>
                        <label class="btn btn-outline-secondary btn-sm" style="cursor:pointer">
                            <i class="bi bi-camera me-1"></i>Upload Foto
                            <input type="file" name="foto" accept="image/*"
                                   style="display:none" onchange="previewImg(this,'previewFoto')">
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telepon" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Minimal 6 karakter" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Kasir -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="simpan.php" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h6 class="modal-title">Edit Kasir</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <div id="previewFotoEdit" style="width:80px;height:80px;border-radius:50%;
                             background:#eff6ff;display:flex;align-items:center;
                             justify-content:center;font-size:2rem;color:var(--primary);
                             margin:0 auto 8px;overflow:hidden">
                            <i class="bi bi-person"></i>
                        </div>
                        <label class="btn btn-outline-secondary btn-sm" style="cursor:pointer">
                            <i class="bi bi-camera me-1"></i>Ganti Foto
                            <input type="file" name="foto" accept="image/*"
                                   style="display:none"
                                   onchange="previewImg(this,'previewFotoEdit')">
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="editNama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telepon" id="editTelepon" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Kosongkan jika tidak diubah">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Toggle Aktif -->
<div class="modal fade" id="modalToggle" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title" id="toggleTitle">Nonaktifkan Kasir</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-0" id="togglePesan"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Batal</button>
                <a href="#" id="linkToggle" class="btn btn-sm btn-warning">Ya, Lanjutkan</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editKasir(data) {
    document.getElementById('editId').value      = data.id;
    document.getElementById('editNama').value    = data.nama;
    document.getElementById('editEmail').value   = data.email;
    document.getElementById('editTelepon').value = data.no_telepon ?? '';
    const prev = document.getElementById('previewFotoEdit');
    if (data.foto) {
        prev.innerHTML = `<img src="../../assets/img/uploads/${data.foto}"
                          style="width:100%;height:100%;object-fit:cover">`;
    } else {
        prev.innerHTML = `<i class="bi bi-person"></i>`;
    }
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

function toggleAktif(id, aktif, nama) {
    const nonaktif = aktif == 1;
    document.getElementById('toggleTitle').textContent =
        nonaktif ? 'Nonaktifkan Kasir' : 'Aktifkan Kasir';
    document.getElementById('togglePesan').textContent =
        nonaktif
            ? `Kasir ${nama} tidak bisa login setelah dinonaktifkan.`
            : `Kasir ${nama} akan bisa login kembali.`;
    document.getElementById('linkToggle').href =
        `simpan.php?aksi=toggle&id=${id}`;
    new bootstrap.Modal(document.getElementById('modalToggle')).show();
}

function previewImg(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById(targetId).innerHTML =
                `<img src="${e.target.result}"
                  style="width:100%;height:100%;object-fit:cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>