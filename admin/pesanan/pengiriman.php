<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekAdmin();
$user = userLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

$stmt = $pdo->prepare(
    "SELECT p.*, u.nama as nama_pembeli
     FROM pesanan p JOIN users u ON p.user_id = u.id
     WHERE p.id = ?"
);
$stmt->execute([$id]);
$pesanan = $stmt->fetch();
if (!$pesanan) redirect('index.php', 'Pesanan tidak ditemukan', 'error');

$stmt = $pdo->prepare("SELECT * FROM pengiriman WHERE pesanan_id = ?");
$stmt->execute([$id]);
$pengiriman = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM profil_usaha WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_penerima  = bersihkan($_POST['nama_penerima'] ?? '');
    $no_telepon     = bersihkan($_POST['no_telepon'] ?? '');
    $alamat_lengkap = bersihkan($_POST['alamat_lengkap'] ?? '');
    $kota_tujuan    = bersihkan($_POST['kota_tujuan'] ?? '');
    $kurir          = bersihkan($_POST['kurir'] ?? '');
    $no_resi        = bersihkan($_POST['no_resi'] ?? '');
    $status_kirim   = bersihkan($_POST['status_kirim'] ?? 'menunggu');

    if (empty($nama_penerima) || empty($no_telepon) || empty($alamat_lengkap)) {
        $error = 'Nama, telepon, dan alamat wajib diisi';
    } else {
        if ($pengiriman) {
            // Update
            $pdo->prepare(
                "UPDATE pengiriman SET
                 nama_penerima = ?, no_telepon = ?,
                 alamat_lengkap = ?, kota_tujuan = ?,
                 kurir = ?, no_resi = ?, status = ?,
                 updated_at = NOW()
                 WHERE pesanan_id = ?"
            )->execute([
                $nama_penerima, $no_telepon, $alamat_lengkap,
                $kota_tujuan, $kurir, $no_resi, $status_kirim, $id
            ]);
        } else {
            // Insert baru
            $pdo->prepare(
                "INSERT INTO pengiriman
                 (pesanan_id, nama_penerima, no_telepon,
                  alamat_lengkap, kota_tujuan, kurir, no_resi, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $id, $nama_penerima, $no_telepon,
                $alamat_lengkap, $kota_tujuan, $kurir, $no_resi, $status_kirim
            ]);
        }

        // Update status pesanan ke dikirim kalau ada resi
        if ($no_resi && $pesanan['status'] === 'diproses') {
            $pdo->prepare(
                "UPDATE pesanan SET status = 'dikirim', updated_at = NOW()
                 WHERE id = ?"
            )->execute([$id]);
        }

        redirect('detail.php?id=' . $id, 'Data pengiriman berhasil disimpan!');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengiriman</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin/pesanan/pengiriman.css">
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
                    <a href="index.php" style="color:var(--primary)">Pesanan</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="detail.php?id=<?= $id ?>" style="color:var(--primary)">
                        <?= htmlspecialchars($pesanan['no_pesanan']) ?>
                    </a>
                </li>
                <li class="breadcrumb-item active">Data Pengiriman</li>
            </ol>
        </nav>
        <a href="detail.php?id=<?= $id ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="content">
        <div class="row justify-content-center">
            <div class="col-md-7">

                <!-- Info Pesanan -->
                <div class="pesanan-info">
                    <div style="font-size:.8rem;color:#166534;font-weight:600">
                        <i class="bi bi-bag-check me-1"></i>Pesanan
                    </div>
                    <div style="font-size:.875rem;color:#166534">
                        <strong><?= htmlspecialchars($pesanan['no_pesanan']) ?></strong>
                        · <?= htmlspecialchars($pesanan['nama_pembeli']) ?>
                        · <?= formatRupiah($pesanan['total_harga']) ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST">
                        <div class="section-title">
                            <i class="bi bi-geo-alt me-1"></i>Alamat Penerima
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                                <input type="text" name="nama_penerima" class="form-control"
                                       value="<?= htmlspecialchars($_POST['nama_penerima'] ?? $pengiriman['nama_penerima'] ?? '') ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                                <input type="text" name="no_telepon" class="form-control"
                                       value="<?= htmlspecialchars($_POST['no_telepon'] ?? $pengiriman['no_telepon'] ?? '') ?>"
                                       required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="alamat_lengkap" class="form-control"
                                          rows="3" required><?= htmlspecialchars($_POST['alamat_lengkap'] ?? $pengiriman['alamat_lengkap'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Kota Tujuan</label>
                                <input type="text" name="kota_tujuan" class="form-control"
                                       value="<?= htmlspecialchars($_POST['kota_tujuan'] ?? $pengiriman['kota_tujuan'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-truck me-1"></i>Info Kurir
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Kurir</label>
                                <select name="kurir" class="form-select">
                                    <option value="">-- Pilih Kurir --</option>
                                    <?php
                                    $kurir_list = ['JNE','J&T Express','SiCepat','Anteraja',
                                                   'Pos Indonesia','GoSend','GrabExpress','Ninja Xpress'];
                                    $cur_kurir  = $_POST['kurir'] ?? $pengiriman['kurir'] ?? '';
                                    foreach ($kurir_list as $k):
                                        $sel = $cur_kurir === $k ? 'selected' : '';
                                    ?>
                                    <option value="<?= $k ?>" <?= $sel ?>><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. Resi</label>
                                <input type="text" name="no_resi" class="form-control"
                                       placeholder="Isi jika sudah dikirim"
                                       value="<?= htmlspecialchars($_POST['no_resi'] ?? $pengiriman['no_resi'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Status Pengiriman</label>
                                <select name="status_kirim" class="form-select">
                                    <?php
                                    $kirim_statuses = [
                                        'menunggu' => 'Menunggu Pickup',
                                        'diproses' => 'Sedang Diproses',
                                        'dikirim'  => 'Dalam Pengiriman',
                                        'selesai'  => 'Terkirim'
                                    ];
                                    $cur_status = $_POST['status_kirim'] ?? $pengiriman['status'] ?? 'menunggu';
                                    foreach ($kirim_statuses as $val => $lbl):
                                        $sel = $cur_status === $val ? 'selected' : '';
                                    ?>
                                    <option value="<?= $val ?>" <?= $sel ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Simpan Data Pengiriman
                            </button>
                            <a href="detail.php?id=<?= $id ?>"
                               class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>