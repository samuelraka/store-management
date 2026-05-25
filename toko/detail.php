<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php', 'Produk tidak ditemukan', 'error');

$stmt = $pdo->prepare(
    "SELECT b.*, k.nama as kategori, s.nama_supplier
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     LEFT JOIN supplier s ON b.supplier_id = s.id
     WHERE b.id = ?"
);
$stmt->execute([$id]);
$barang = $stmt->fetch();
if (!$barang) redirect('index.php', 'Produk tidak ditemukan', 'error');

// Total terjual
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) FROM transaksi_detail WHERE barang_id = ?"
);
$stmt->execute([$id]);
$total_terjual = $stmt->fetchColumn();

$keranjang_count = array_sum(array_column($_SESSION['keranjang'] ?? [], 'qty'));
$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (int)($_POST['qty'] ?? 1);
    if ($qty < 1) $qty = 1;
    if ($qty > $barang['stok']) {
        $error = 'Jumlah melebihi stok tersedia';
    } else {
        if (!isset($_SESSION['keranjang'])) $_SESSION['keranjang'] = [];
        $found = false;
        foreach ($_SESSION['keranjang'] as &$item) {
            if ($item['barang_id'] == $id) {
                $item['qty'] = min($item['qty'] + $qty, $barang['stok']);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['keranjang'][] = [
                'barang_id'  => $id,
                'nama'       => $barang['nama_barang'],
                'harga'      => $barang['harga_jual'],
                'satuan'     => $barang['satuan'],
                'stok_max'   => $barang['stok'],
                'qty'        => $qty
            ];
        }
        redirect('keranjang.php', 'Produk ditambahkan ke keranjang!');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($barang['nama_barang']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/toko/detail.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="nav-icon">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <a class="navbar-brand mb-0" href="index.php">
                    <?= htmlspecialchars($profil['nama_toko'] ?? 'Toko') ?>
                </a>
            </div>
            <div class="d-flex gap-2">
                <a href="keranjang.php" class="nav-icon">
                    <i class="bi bi-cart3"></i>
                    <?php if ($keranjang_count > 0): ?>
                    <span class="nav-badge"><?= $keranjang_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="detail-card">
                <div class="produk-img-big">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="detail-body" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px">
                <?php if ($barang['kategori']): ?>
                <span class="badge-kat"><?= htmlspecialchars($barang['kategori']) ?></span>
                <?php endif; ?>

                <h2 style="font-weight:700;color:#111827;margin:10px 0 4px;font-size:1.4rem">
                    <?= htmlspecialchars($barang['nama_barang']) ?>
                </h2>

                <div style="font-size:.8rem;color:#9ca3af">
                    Kode: <?= htmlspecialchars($barang['kode_barang']) ?>
                </div>

                <div class="harga-besar">
                    <?= formatRupiah($barang['harga_jual']) ?>
                    <span style="font-size:.9rem;color:#9ca3af;font-weight:400">
                        / <?= htmlspecialchars($barang['satuan']) ?>
                    </span>
                </div>

                <div class="stok-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            <span style="font-size:.875rem;font-weight:600;color:#166534">
                                Stok Tersedia
                            </span>
                        </div>
                        <div style="font-weight:700;color:#16a34a;font-size:1rem">
                            <?= number_format($barang['stok']) ?>
                            <?= htmlspecialchars($barang['satuan']) ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="qty-control">
                        <span style="font-size:.875rem;color:#6b7280;font-weight:600">
                            Jumlah:
                        </span>
                        <div class="qty-btn" onclick="ubahQty(-1)">
                            <i class="bi bi-dash"></i>
                        </div>
                        <input type="number" name="qty" id="qtyInput"
                               class="qty-input" value="1"
                               min="1" max="<?= $barang['stok'] ?>">
                        <div class="qty-btn" onclick="ubahQty(1)">
                            <i class="bi bi-plus"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-beli">
                        <i class="bi bi-cart-plus me-2"></i>
                        Tambah ke Keranjang
                    </button>
                </form>

                <hr style="border-color:#f3f4f6;margin:20px 0">

                <div class="info-row">
                    <span style="color:#6b7280">Supplier</span>
                    <span style="font-weight:600">
                        <?= htmlspecialchars($barang['nama_supplier'] ?? '-') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span style="color:#6b7280">Total Terjual</span>
                    <span style="font-weight:600">
                        <?= number_format($total_terjual) ?> <?= htmlspecialchars($barang['satuan']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span style="color:#6b7280">Tanggal Masuk</span>
                    <span style="font-weight:600">
                        <?= $barang['tanggal_masuk'] ? formatTanggal($barang['tanggal_masuk']) : '-' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function ubahQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val     = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>
</body>
</html>