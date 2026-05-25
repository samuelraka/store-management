<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

$suppliers = $pdo->query(
    "SELECT * FROM supplier WHERE is_aktif = 1 ORDER BY nama_supplier"
)->fetchAll();

$barang_list = $pdo->query(
    "SELECT b.*, k.nama as kategori
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     ORDER BY b.nama_barang ASC"
)->fetchAll();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id   = (int)($_POST['supplier_id'] ?? 0);
    $tanggal_masuk = $_POST['tanggal_masuk'] ?? date('Y-m-d');
    $keterangan    = bersihkan($_POST['keterangan'] ?? '');
    $barang_ids    = $_POST['barang_id'] ?? [];
    $jumlahs       = $_POST['jumlah'] ?? [];
    $harga_belis   = $_POST['harga_beli'] ?? [];

    if (!$supplier_id) {
        $error = 'Pilih supplier terlebih dahulu';
    } elseif (empty($barang_ids)) {
        $error = 'Tambahkan minimal 1 barang';
    } else {
        // Validasi ada item qty > 0
        $ada_item = false;
        foreach ($barang_ids as $i => $bid) {
            if ((int)($jumlahs[$i] ?? 0) > 0) {
                $ada_item = true;
                break;
            }
        }

        if (!$ada_item) {
            $error = 'Jumlah barang harus lebih dari 0';
        } else {
            try {
                $pdo->beginTransaction();

                $no_pembelian = generateNoPembelian($pdo);
                $total_harga  = 0;

                foreach ($barang_ids as $i => $bid) {
                    $qty   = (int)($jumlahs[$i] ?? 0);
                    $harga = (float)($harga_belis[$i] ?? 0);
                    if ($qty > 0) {
                        $total_harga += $qty * $harga;
                    }
                }

                // Insert header pembelian
                $stmt = $pdo->prepare(
                    "INSERT INTO pembelian
                     (supplier_id, user_id, no_pembelian,
                      tanggal_masuk, total_harga, keterangan)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $supplier_id, $user['id'], $no_pembelian,
                    $tanggal_masuk, $total_harga, $keterangan
                ]);
                $pembelian_id = $pdo->lastInsertId();

                // Insert detail + update stok + catat mutasi
                foreach ($barang_ids as $i => $bid) {
                    $bid   = (int)$bid;
                    $qty   = (int)($jumlahs[$i] ?? 0);
                    $harga = (float)($harga_belis[$i] ?? 0);

                    if ($qty <= 0 || !$bid) continue;

                    $subtotal = $qty * $harga;

                    // Insert detail
                    $pdo->prepare(
                        "INSERT INTO pembelian_detail
                         (pembelian_id, barang_id, jumlah, harga_beli, subtotal)
                         VALUES (?, ?, ?, ?, ?)"
                    )->execute([$pembelian_id, $bid, $qty, $harga, $subtotal]);

                    // Ambil stok sebelum
                    $st = $pdo->prepare("SELECT stok FROM barang WHERE id = ?");
                    $st->execute([$bid]);
                    $stok_sebelum = (int)$st->fetchColumn();
                    $stok_sesudah = $stok_sebelum + $qty;

                    // Update stok + harga beli
                    $pdo->prepare(
                        "UPDATE barang SET stok = stok + ?,
                         harga_beli = ?, tanggal_masuk = ?
                         WHERE id = ?"
                    )->execute([$qty, $harga, $tanggal_masuk, $bid]);

                    // Catat mutasi
                    $pdo->prepare(
                        "INSERT INTO stok_mutasi
                         (barang_id, user_id, tipe, jumlah,
                          stok_sebelum, stok_sesudah, keterangan)
                         VALUES (?, ?, 'masuk', ?, ?, ?, ?)"
                    )->execute([
                        $bid, $user['id'], $qty,
                        $stok_sebelum, $stok_sesudah,
                        'Pembelian dari supplier: ' . $no_pembelian
                    ]);
                }

                $pdo->commit();
                redirect('index.php', 'Barang masuk berhasil! No: ' . $no_pembelian);

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

$kode_otomatis = generateNoPembelian($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Barang Masuk</title>
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
        .item-row {
            background:#f9fafb;border:1px solid #e5e7eb;
            border-radius:8px;padding:14px;margin-bottom:10px;
            position:relative;
        }
        .btn-hapus-row {
            position:absolute;top:10px;right:10px;
            background:none;border:none;color:#ef4444;
            cursor:pointer;font-size:1rem;padding:4px;
        }
        .total-box {
            background:#f0fdf4;border:1px solid #bbf7d0;
            border-radius:8px;padding:16px 20px;
        }
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
        <a href="index.php"><i class="bi bi-box-arrow-in-down"></i> Barang Masuk</a>
        <a href="tambah.php" class="active"><i class="bi bi-plus-circle"></i> Input Barang Masuk</a>
        <a href="../stok/histori.php"><i class="bi bi-clock-history"></i> Histori Stok</a>
        <div class="menu-label">Akun</div>
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
                <li class="breadcrumb-item active">Input Baru</li>
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

        <form method="POST" id="formPembelian">
            <div class="row g-3">

                <!-- Kiri -->
                <div class="col-md-8">
                    <div class="form-card">
                        <div class="section-title">Informasi Pembelian</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    Supplier <span class="text-danger">*</span>
                                </label>
                                <select name="supplier_id" class="form-select" required>
                                    <option value="">-- Pilih Supplier --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['nama_supplier']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($suppliers)): ?>
                                    <div class="form-text text-danger">
                                        Belum ada supplier.
                                        <a href="../../admin/supplier/index.php">Tambah supplier</a>
                                        terlebih dahulu.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Masuk</label>
                                <input type="date" name="tanggal_masuk"
                                       class="form-control"
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Keterangan</label>
                                <input type="text" name="keterangan"
                                       class="form-control"
                                       placeholder="Opsional...">
                            </div>
                        </div>

                        <div class="section-title">Daftar Barang Masuk</div>

                        <!-- Pilih barang -->
                        <div class="d-flex gap-2 mb-3">
                            <select id="pilihBarang" class="form-select form-select-sm">
                                <option value="">-- Pilih Barang --</option>
                                <?php foreach ($barang_list as $b): ?>
                                <option value="<?= $b['id'] ?>"
                                        data-nama="<?= htmlspecialchars($b['nama_barang']) ?>"
                                        data-satuan="<?= htmlspecialchars($b['satuan']) ?>"
                                        data-harga="<?= $b['harga_beli'] ?>"
                                        data-stok="<?= $b['stok'] ?>">
                                    <?= htmlspecialchars($b['nama_barang']) ?>
                                    (Stok: <?= $b['stok'] ?> <?= $b['satuan'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="tambahBarang()"
                                    class="btn btn-outline-primary btn-sm"
                                    style="white-space:nowrap">
                                <i class="bi bi-plus-lg me-1"></i>Tambah
                            </button>
                        </div>

                        <!-- Item container -->
                        <div id="itemContainer">
                            <div class="text-center py-4 text-muted small"
                                 id="emptyMsg">
                                <i class="bi bi-inbox d-block mb-1"
                                   style="font-size:1.5rem"></i>
                                Belum ada barang dipilih
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kanan -->
                <div class="col-md-4">
                    <div class="form-card">
                        <div class="section-title">Ringkasan</div>
                        <div class="total-box mb-3">
                            <div style="font-size:.8rem;color:#6b7280">
                                Total Nilai Pembelian
                            </div>
                            <div style="font-size:1.8rem;font-weight:700;color:#166534"
                                 id="totalHarga">Rp 0</div>
                            <div style="font-size:.78rem;color:#6b7280"
                                 id="totalItem">0 jenis barang</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2"
                                id="btnSimpan">
                            <i class="bi bi-check-lg me-1"></i>Simpan Barang Masuk
                        </button>
                        <a href="index.php"
                           class="btn btn-outline-secondary w-100">Batal</a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let itemCount = 0;

function tambahBarang() {
    const sel  = document.getElementById('pilihBarang');
    const opt  = sel.options[sel.selectedIndex];
    const bid  = sel.value;

    if (!bid) {
        alert('Pilih barang terlebih dahulu');
        return;
    }

    // Cek duplikat
    if (document.querySelector(`[data-barang-id="${bid}"]`)) {
        alert('Barang sudah ditambahkan');
        return;
    }

    const nama   = opt.dataset.nama;
    const satuan = opt.dataset.satuan;
    const harga  = parseFloat(opt.dataset.harga) || 0;
    const stok   = opt.dataset.stok;

    document.getElementById('emptyMsg').style.display = 'none';
    itemCount++;

    const div       = document.createElement('div');
    div.className   = 'item-row';
    div.setAttribute('data-barang-id', bid);
    div.innerHTML   = `
        <button type="button" class="btn-hapus-row" onclick="hapusItem(this)">
            <i class="bi bi-x-circle"></i>
        </button>
        <input type="hidden" name="barang_id[]" value="${bid}">
        <div class="row g-2 align-items-center">
            <div class="col-12">
                <div style="font-weight:600;font-size:.9rem">${nama}</div>
                <div style="font-size:.75rem;color:#6b7280">
                    Stok saat ini: ${stok} ${satuan}
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Jumlah (${satuan})</label>
                <input type="number" name="jumlah[]"
                       class="form-control form-control-sm qty-input"
                       min="1" value="1" oninput="hitungTotal()">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Harga Beli (Rp)</label>
                <input type="number" name="harga_beli[]"
                       class="form-control form-control-sm harga-input"
                       min="0" value="${harga}" oninput="hitungTotal()">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Subtotal</label>
                <div class="subtotal-display"
                     style="font-weight:700;color:#16a34a;
                            font-size:.9rem;padding-top:4px">
                    ${formatRp(harga)}
                </div>
            </div>
        </div>
    `;

    document.getElementById('itemContainer').appendChild(div);
    hitungTotal();
    sel.value = '';
}

function hapusItem(btn) {
    btn.closest('.item-row').remove();
    itemCount--;
    if (document.querySelectorAll('.item-row').length === 0) {
        document.getElementById('emptyMsg').style.display = 'block';
    }
    hitungTotal();
}

function hitungTotal() {
    let total = 0;
    let count = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
        const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
        const sub   = qty * harga;
        row.querySelector('.subtotal-display').textContent = formatRp(sub);
        total += sub;
        count++;
    });
    document.getElementById('totalHarga').textContent = formatRp(total);
    document.getElementById('totalItem').textContent  = count + ' jenis barang';
}

function formatRp(val) {
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

// Konfirmasi sebelum submit
document.getElementById('formPembelian').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('.item-row');
    if (items.length === 0) {
        e.preventDefault();
        alert('Tambahkan minimal 1 barang!');
        return;
    }
    document.getElementById('btnSimpan').disabled = true;
    document.getElementById('btnSimpan').innerHTML =
        '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
});
</script>
</body>
</html>