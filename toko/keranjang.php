<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

cekLogin();
cekPembeli();
$user = userLogin();

// Proses aksi keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if (!isset($_SESSION['keranjang'])) $_SESSION['keranjang'] = [];

    switch ($aksi) {
        case 'tambah':
            $bid = (int)($_POST['barang_id'] ?? 0);
            if ($bid) {
                $stmt = $pdo->prepare(
                    "SELECT id, nama_barang, harga_jual, satuan, stok
                     FROM barang WHERE id = ? AND stok > 0"
                );
                $stmt->execute([$bid]);
                $b = $stmt->fetch();
                if ($b) {
                    $found = false;
                    foreach ($_SESSION['keranjang'] as &$item) {
                        if ($item['barang_id'] == $bid) {
                            $item['qty'] = min($item['qty'] + 1, $b['stok']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $_SESSION['keranjang'][] = [
                            'barang_id' => $bid,
                            'nama'      => $b['nama_barang'],
                            'harga'     => $b['harga_jual'],
                            'satuan'    => $b['satuan'],
                            'stok_max'  => $b['stok'],
                            'qty'       => 1
                        ];
                    }
                    redirect('keranjang.php', 'Produk ditambahkan!');
                }
            }
            break;

        case 'update':
            $bid = (int)($_POST['barang_id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 1);
            foreach ($_SESSION['keranjang'] as &$item) {
                if ($item['barang_id'] == $bid) {
                    $item['qty'] = max(1, min($qty, $item['stok_max']));
                    break;
                }
            }
            redirect('keranjang.php');
            break;

        case 'hapus':
            $bid = (int)($_POST['barang_id'] ?? 0);
            $_SESSION['keranjang'] = array_values(
                array_filter($_SESSION['keranjang'],
                    fn($i) => $i['barang_id'] != $bid
                )
            );
            redirect('keranjang.php', 'Produk dihapus dari keranjang');
            break;

        case 'kosongkan':
            $_SESSION['keranjang'] = [];
            redirect('keranjang.php');
            break;
    }
}

$keranjang     = $_SESSION['keranjang'] ?? [];
$total         = array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $keranjang));
$keranjang_count = array_sum(array_column($keranjang, 'qty'));
$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/toko/keranjang.css">
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
            <span style="font-size:.875rem;color:#6b7280">
                <?= $keranjang_count ?> item di keranjang
            </span>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php flashPesan(); ?>

    <?php if (empty($keranjang)): ?>
        <div class="card-box">
            <div class="empty-state">
                <i class="bi bi-cart3"></i>
                <p style="font-size:1rem;font-weight:600;color:#374151">
                    Keranjang masih kosong
                </p>
                <p style="font-size:.875rem">
                    Yuk pilih produk yang kamu butuhkan!
                </p>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-grid me-1"></i>Lihat Produk
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Daftar Item -->
            <div class="col-md-8">
                <div class="card-box">
                    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;
                                display:flex;align-items:center;justify-content:space-between">
                        <h6 style="margin:0;font-weight:700;color:#111827">
                            <i class="bi bi-cart3 me-2"></i>
                            Keranjang Belanja
                        </h6>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="aksi" value="kosongkan">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Kosongkan semua keranjang?')">
                                <i class="bi bi-trash me-1"></i>Kosongkan
                            </button>
                        </form>
                    </div>

                    <?php foreach ($keranjang as $item): ?>
                    <div class="item-row">
                        <div class="item-img">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="item-info">
                            <div class="item-nama">
                                <?= htmlspecialchars($item['nama']) ?>
                            </div>
                            <div class="item-harga">
                                <?= formatRupiah($item['harga']) ?>
                                / <?= htmlspecialchars($item['satuan']) ?>
                            </div>
                        </div>
                        <div class="qty-control">
                            <form method="POST" style="margin:0;display:contents">
                                <input type="hidden" name="aksi" value="update">
                                <input type="hidden" name="barang_id"
                                       value="<?= $item['barang_id'] ?>">
                                <div class="qty-btn"
                                     onclick="this.closest('form').querySelector('input[name=qty]').value=Math.max(1,parseInt(this.closest('form').querySelector('input[name=qty]').value)-1);this.closest('form').submit()">
                                    <i class="bi bi-dash"></i>
                                </div>
                                <span class="qty-val"><?= $item['qty'] ?></span>
                                <div class="qty-btn"
                                     onclick="this.closest('form').querySelector('input[name=qty]').value=Math.min(<?= $item['stok_max'] ?>,parseInt(this.closest('form').querySelector('input[name=qty]').value)+1);this.closest('form').submit()">
                                    <i class="bi bi-plus"></i>
                                </div>
                                <input type="hidden" name="qty" value="<?= $item['qty'] ?>">
                            </form>
                        </div>
                        <div class="item-subtotal">
                            <?= formatRupiah($item['harga'] * $item['qty']) ?>
                        </div>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="barang_id"
                                   value="<?= $item['barang_id'] ?>">
                            <button type="submit" class="btn-hapus-item">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Summary -->
            <div class="col-md-4">
                <div class="summary-card">
                    <h6 style="font-weight:700;color:#111827;margin-bottom:16px">
                        Ringkasan Pesanan
                    </h6>
                    <?php foreach ($keranjang as $item): ?>
                    <div class="summary-row">
                        <span style="color:#6b7280">
                            <?= htmlspecialchars($item['nama']) ?>
                            <span style="color:#9ca3af">(<?= $item['qty'] ?>x)</span>
                        </span>
                        <span><?= formatRupiah($item['harga'] * $item['qty']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total</span>
                        <span style="color:var(--primary)"><?= formatRupiah($total) ?></span>
                    </div>
                    <a href="checkout.php" class="btn-checkout">
                        <i class="bi bi-bag-check me-2"></i>
                        Lanjut ke Checkout
                    </a>
                    <a href="index.php"
                       style="display:block;text-align:center;margin-top:10px;
                              font-size:.85rem;color:#6b7280;text-decoration:none">
                        <i class="bi bi-arrow-left me-1"></i>Lanjut Belanja
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>