<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Barang tidak ditemukan', 'error');

// Ambil data barang
$stmt = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
$stmt->execute([$id]);
$barang = $stmt->fetch();
if (!$barang) redirect('index.php', 'Barang tidak ditemukan', 'error');

$kategoris = $pdo->query("SELECT * FROM kategori_barang ORDER BY nama")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM supplier WHERE is_aktif = 1 ORDER BY nama_supplier")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang   = bersihkan($_POST['nama_barang'] ?? '');
    $kode_barang   = bersihkan($_POST['kode_barang'] ?? '');
    $kategori_id   = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $supplier_id   = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $satuan        = bersihkan($_POST['satuan'] ?? 'pcs');
    $harga_beli    = (float)($_POST['harga_beli'] ?? 0);
    $harga_jual    = (float)($_POST['harga_jual'] ?? 0);
    $stok_minimal  = (int)($_POST['stok_minimal'] ?? 5);
    $tanggal_masuk = $_POST['tanggal_masuk'] ?? date('Y-m-d');

    if (empty($nama_barang)) {
        $error = 'Nama barang wajib diisi';
    } elseif ($harga_jual < $harga_beli) {
        $error = 'Harga jual tidak boleh lebih kecil dari harga beli';
    } else {
        // Cek kode duplikat (kecuali milik sendiri)
        $cek = $pdo->prepare("SELECT id FROM barang WHERE kode_barang = ? AND id != ?");
        $cek->execute([$kode_barang, $id]);
        if ($cek->fetch()) {
            $error = 'Kode barang sudah digunakan barang lain';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE barang SET
                    kategori_id = ?, supplier_id = ?, kode_barang = ?,
                    nama_barang = ?, satuan = ?, harga_beli = ?,
                    harga_jual = ?, stok_minimal = ?, tanggal_masuk = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $kategori_id, $supplier_id, $kode_barang,
                $nama_barang, $satuan, $harga_beli,
                $harga_jual, $stok_minimal, $tanggal_masuk, $id
            ]);
            redirect('index.php', 'Barang berhasil diupdate!');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Barang</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w:250px; --primary:#2563eb; }
        body { background:#f3f4f6; font-family:'Segoe UI',sans-serif; }
        .sidebar {
            position:fixed;top:0;left:0;width:var(--sidebar-w);
            height:100vh;background:#1e2a3b;overflow-y:auto;z-index:1000;
        }
        .sidebar-brand { padding:20px 20px 16px; border-bottom:1px solid rgba(255,255,255,.1); }
        .sidebar-brand h6 { color:#fff;font-weight:700;margin:0; }
        .sidebar-brand small { color:#94a3b8;font-size:.75rem; }
        .sidebar-menu { padding:12px 0; }
        .menu-label {
            padding:8px 20px 4px;font-size:.7rem;font-weight:600;
            color:#64748b;text-transform:uppercase;letter-spacing:.8px;
        }
        .sidebar a {
            display:flex;align-items:center;gap:10px;padding:10px 20px;
            color:#94a3b8;text-decoration:none;font-size:.9rem;
            border-left:3px solid transparent;transition:all .2s;
        }
        .sidebar a:hover,.sidebar a.active {
            color:#fff;background:rgba(255,255,255,.06);border-left-color:var(--primary);
        }
        .sidebar a i { font-size:1rem;width:20px; }
        .main { margin-left:var(--sidebar-w);min-height:100vh; }
        .topbar {
            background:#fff;border-bottom:1px solid #e5e7eb;
            padding:14px 24px;display:flex;align-items:center;
            justify-content:space-between;position:sticky;top:0;z-index:100;
        }
        .content { padding:24px; }
        .form-card {
            background:#fff;border:1px solid #e5e7eb;
            border-radius:12px;padding:24px;
        }
        .section-title {
            font-size:.8rem;font-weight:700;color:#6b7280;
            text-transform:uppercase;letter-spacing:.8px;
            margin-bottom:16px;padding-bottom:8px;
            border-bottom:1px solid #f3f4f6;
        }
        .preview-margin {
            background:#f0fdf4;border:1px solid #bbf7d0;
            border-radius:8px;padding:12px 16px;
        }
        .stok-info {
            background:#eff6ff;border:1px solid #bfdbfe;
            border-radius:8px;padding:12px 16px;
        }
    </style>
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

<div class="main">
    <div class="topbar">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="index.php" style="color:var(--primary)">Data Barang</a>
                </li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="content">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-card">
                        <div class="section-title">Informasi Barang</div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                                <input type="text" name="nama_barang" class="form-control"
                                       value="<?= htmlspecialchars($_POST['nama_barang'] ?? $barang['nama_barang']) ?>"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Satuan</label>
                                <select name="satuan" class="form-select">
                                    <?php
                                    $satuans = ['pcs','kg','gram','liter','ml','dus','lusin','meter','cm'];
                                    foreach ($satuans as $s):
                                        $cur = $_POST['satuan'] ?? $barang['satuan'];
                                        $sel = $cur === $s ? 'selected' : '';
                                    ?>
                                    <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kode Barang</label>
                                <input type="text" name="kode_barang" class="form-control"
                                       value="<?= htmlspecialchars($_POST['kode_barang'] ?? $barang['kode_barang']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Masuk</label>
                                <input type="date" name="tanggal_masuk" class="form-control"
                                       value="<?= $_POST['tanggal_masuk'] ?? $barang['tanggal_masuk'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kategori</label>
                                <select name="kategori_id" class="form-select">
                                    <option value="">-- Tanpa Kategori --</option>
                                    <?php foreach ($kategoris as $k):
                                        $cur = $_POST['kategori_id'] ?? $barang['kategori_id'];
                                        $sel = $cur == $k['id'] ? 'selected' : '';
                                    ?>
                                    <option value="<?= $k['id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($k['nama']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select">
                                    <option value="">-- Tanpa Supplier --</option>
                                    <?php foreach ($suppliers as $s):
                                        $cur = $_POST['supplier_id'] ?? $barang['supplier_id'];
                                        $sel = $cur == $s['id'] ? 'selected' : '';
                                    ?>
                                    <option value="<?= $s['id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($s['nama_supplier']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-card mt-3">
                        <div class="section-title">Harga & Margin</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Harga Beli <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="harga_beli" id="hargaBeli"
                                           class="form-control" min="0" step="100"
                                           value="<?= $_POST['harga_beli'] ?? $barang['harga_beli'] ?>"
                                           oninput="hitungMargin()" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Harga Jual <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="harga_jual" id="hargaJual"
                                           class="form-control" min="0" step="100"
                                           value="<?= $_POST['harga_jual'] ?? $barang['harga_jual'] ?>"
                                           oninput="hitungMargin()" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="preview-margin">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div style="font-size:.75rem;color:#6b7280">Margin / item</div>
                                            <div style="font-weight:700;color:#16a34a" id="previewMargin">Rp 0</div>
                                        </div>
                                        <div class="col-4">
                                            <div style="font-size:.75rem;color:#6b7280">Persentase</div>
                                            <div style="font-weight:700;color:#16a34a" id="previewPersen">0%</div>
                                        </div>
                                        <div class="col-4">
                                            <div style="font-size:.75rem;color:#6b7280">Status</div>
                                            <div style="font-weight:700" id="previewStatus">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Info stok saat ini -->
                    <div class="form-card">
                        <div class="section-title">Stok Saat Ini</div>
                        <div class="stok-info mb-3">
                            <div style="font-size:.8rem;color:#1e40af">Jumlah Stok</div>
                            <div style="font-size:2rem;font-weight:700;color:#1d4ed8;line-height:1.2">
                                <?= number_format($barang['stok']) ?>
                            </div>
                            <div style="font-size:.78rem;color:#3b82f6">
                                <?= htmlspecialchars($barang['satuan']) ?>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Untuk ubah stok, gunakan menu
                            <a href="../pembelian/tambah.php">Barang Masuk</a>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Stok Minimal (Alert)</label>
                            <input type="number" name="stok_minimal" class="form-control"
                                   min="0" value="<?= $_POST['stok_minimal'] ?? $barang['stok_minimal'] ?>">
                            <div class="form-text">Alert jika stok ≤ nilai ini</div>
                        </div>
                    </div>

                    <div class="form-card mt-3">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100">Batal</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function hitungMargin() {
    const beli  = parseFloat(document.getElementById('hargaBeli').value) || 0;
    const jual  = parseFloat(document.getElementById('hargaJual').value) || 0;
    const margin = jual - beli;
    const persen = beli > 0 ? ((margin / beli) * 100).toFixed(1) : 0;
    document.getElementById('previewMargin').textContent =
        'Rp ' + margin.toLocaleString('id-ID');
    document.getElementById('previewPersen').textContent = persen + '%';
    const statusEl = document.getElementById('previewStatus');
    if (margin > 0) { statusEl.textContent='Untung'; statusEl.style.color='#16a34a'; }
    else if (margin < 0) { statusEl.textContent='Rugi'; statusEl.style.color='#dc2626'; }
    else { statusEl.textContent='BEP'; statusEl.style.color='#6b7280'; }
}
hitungMargin();
</script>
</body>
</html>