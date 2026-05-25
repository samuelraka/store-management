<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

// Ambil semua barang yang stoknya ada
$barang_list = $pdo->query(
    "SELECT b.*, k.nama as kategori
     FROM barang b
     LEFT JOIN kategori_barang k ON b.kategori_id = k.id
     WHERE b.stok > 0
     ORDER BY b.nama_barang ASC"
)->fetchAll();

// Daftar kategori untuk filter
$kategoris = $pdo->query(
    "SELECT * FROM kategori_barang ORDER BY nama"
)->fetchAll();

$profil = $pdo->query("SELECT * FROM profil_usaha LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS — Kasir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/kasir/pos/style.css">
</head>
<body>

<div class="pos-wrap">

    <!-- ===== KIRI: Daftar Produk ===== -->
    <div class="pos-left">

        <!-- Topbar -->
        <div class="pos-topbar">
            <div class="d-flex align-items-center gap-3">
                <a href="../dashboard.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h6>POS — <?= htmlspecialchars($profil['nama_toko'] ?? 'Kasir') ?></h6>
            </div>
            <div style="font-size:.8rem;color:#9ca3af">
                <?= htmlspecialchars($user['nama']) ?> &middot;
                <?= date('H:i') ?>
            </div>
        </div>

        <!-- Filter -->
        <div class="pos-filter">
            <input type="text" id="searchProduk"
                   class="form-control form-control-sm"
                   placeholder="Cari nama atau kode barang..."
                   oninput="filterProduk()">
            <div class="kat-tabs">
                <div class="kat-tab active" onclick="filterKat('', this)">
                    Semua
                </div>
                <?php foreach ($kategoris as $k): ?>
                <div class="kat-tab" onclick="filterKat('<?= $k['id'] ?>', this)">
                    <?= htmlspecialchars($k['nama']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid Produk -->
        <div class="produk-scroll">
            <div class="produk-grid" id="produkGrid">
                <?php foreach ($barang_list as $b): ?>
                <div class="produk-card <?= $b['stok'] == 0 ? 'habis' : '' ?>"
                     data-id="<?= $b['id'] ?>"
                     data-nama="<?= htmlspecialchars($b['nama_barang']) ?>"
                     data-harga="<?= $b['harga_jual'] ?>"
                     data-harga-beli="<?= $b['harga_beli'] ?>"
                     data-stok="<?= $b['stok'] ?>"
                     data-satuan="<?= htmlspecialchars($b['satuan']) ?>"
                     data-kat="<?= $b['kategori_id'] ?>"
                     onclick="tambahKeKeranjang(this)">
                    <?php if ($b['kategori']): ?>
                    <span class="produk-kat"><?= htmlspecialchars($b['kategori']) ?></span>
                    <?php endif; ?>
                    <div class="produk-nama"><?= htmlspecialchars($b['nama_barang']) ?></div>
                    <div class="produk-harga"><?= formatRupiah($b['harga_jual']) ?></div>
                    <div class="produk-stok">
                        Stok: <?= number_format($b['stok']) ?> <?= htmlspecialchars($b['satuan']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== KANAN: Keranjang ===== -->
    <div class="pos-right">

        <!-- Header keranjang -->
        <div class="keranjang-head">
            <h6><i class="bi bi-cart3 me-2"></i>Keranjang</h6>
            <button onclick="kosongkanKeranjang()"
                    class="btn btn-sm btn-outline-danger"
                    style="font-size:.75rem;padding:3px 10px">
                <i class="bi bi-trash me-1"></i>Kosongkan
            </button>
        </div>

        <!-- Isi keranjang -->
        <div class="keranjang-scroll" id="keranjangList">
            <div class="keranjang-empty" id="keranjangEmpty">
                <i class="bi bi-cart3" style="font-size:2.5rem"></i>
                <span>Pilih produk untuk ditambahkan</span>
            </div>
        </div>

        <!-- Footer: total & bayar -->
        <div class="keranjang-footer">
            <div class="total-row">
                <span style="color:#6b7280">Subtotal</span>
                <span id="subtotalDisplay">Rp 0</span>
            </div>
            <div class="total-row grand">
                <span>Total</span>
                <span id="totalDisplay">Rp 0</span>
            </div>

            <div class="bayar-input">
                <label>Uang Bayar (Rp)</label>
                <input type="number" id="inputBayar"
                       class="form-control form-control-sm"
                       min="0" placeholder="0"
                       oninput="hitungKembalian()">
            </div>

            <div class="kembalian-box">
                <span class="label">Kembalian</span>
                <span class="val" id="kembalianDisplay">Rp 0</span>
            </div>

            <button class="btn-bayar" id="btnBayar"
                    onclick="prosesTransaksi()" disabled>
                <i class="bi bi-check-circle me-2"></i>
                Proses Transaksi
            </button>
        </div>
    </div>
</div>

<!-- Modal Struk -->
<div class="modal fade" id="modalStruk" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Transaksi Berhasil</h6>
            </div>
            <div class="modal-body p-3" id="strukcontent">
                <div class="struk">
                    <div class="text-center fw-bold">
                        <?= htmlspecialchars($profil['nama_toko'] ?? 'TOKO') ?>
                    </div>
                    <div class="text-center" style="font-size:.75rem;color:#6b7280">
                        <?= htmlspecialchars($profil['kota'] ?? '') ?>
                    </div>
                    <hr class="struk-divider">
                    <div id="struk-no"></div>
                    <div id="struk-tgl"></div>
                    <div id="struk-kasir">Kasir: <?= htmlspecialchars($user['nama']) ?></div>
                    <hr class="struk-divider">
                    <div id="struk-items"></div>
                    <hr class="struk-divider">
                    <div id="struk-total"></div>
                    <div id="struk-bayar"></div>
                    <div id="struk-kembalian" style="font-weight:bold"></div>
                    <hr class="struk-divider">
                    <div class="text-center" style="font-size:.75rem">
                        Terima kasih sudah berbelanja!
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 gap-2">
                <button onclick="cetakStruk()"
                        class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>Cetak
                </button>
                <button onclick="transaksiSelesai()"
                        class="btn btn-sm btn-primary">
                    <i class="bi bi-plus me-1"></i>Transaksi Baru
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================
// STATE
// ============================================
let keranjang = [];
let activeKat = '';

// ============================================
// FILTER PRODUK
// ============================================
function filterProduk() {
    const q = document.getElementById('searchProduk').value.toLowerCase();
    document.querySelectorAll('.produk-card').forEach(card => {
        const nama     = card.dataset.nama.toLowerCase();
        const kat      = card.dataset.kat;
        const matchQ   = nama.includes(q);
        const matchKat = !activeKat || kat == activeKat;
        card.style.display = matchQ && matchKat ? '' : 'none';
    });
}

function filterKat(katId, el) {
    activeKat = katId;
    document.querySelectorAll('.kat-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    filterProduk();
}

// ============================================
// KERANJANG
// ============================================
function tambahKeKeranjang(card) {
    if (card.classList.contains('habis')) return;

    const id       = card.dataset.id;
    const stokMax  = parseInt(card.dataset.stok);
    const harga    = parseFloat(card.dataset.harga);
    const hargaBeli= parseFloat(card.dataset.hargaBeli) || 0;
    const nama     = card.dataset.nama;
    const satuan   = card.dataset.satuan;

    const existing = keranjang.find(i => i.id == id);

    if (existing) {
        if (existing.qty >= stokMax) {
            alert('Stok tidak mencukupi! Sisa: ' + stokMax);
            return;
        }
        existing.qty++;
    } else {
        keranjang.push({
            id       : id,
            nama     : nama,
            harga    : harga,
            hargaBeli: hargaBeli,
            satuan   : satuan,
            stokMax  : stokMax,
            qty      : 1
        });
    }

    renderKeranjang();
}

function ubahQty(id, delta) {
    const idx = keranjang.findIndex(i => i.id == id);
    if (idx === -1) return;

    keranjang[idx].qty += delta;

    if (keranjang[idx].qty <= 0) {
        keranjang.splice(idx, 1);
    } else if (keranjang[idx].qty > keranjang[idx].stokMax) {
        keranjang[idx].qty = keranjang[idx].stokMax;
        alert('Stok tidak mencukupi!');
    }

    renderKeranjang();
}

function kosongkanKeranjang() {
    if (keranjang.length === 0) return;
    if (!confirm('Kosongkan keranjang?')) return;
    keranjang = [];
    renderKeranjang();
}

// ============================================
// RENDER KERANJANG
// ============================================
function renderKeranjang() {
    const container = document.getElementById('keranjangList');

    // Hitung total dulu
    const total   = keranjang.reduce((s, i) => s + i.harga * i.qty, 0);
    const bayar   = parseFloat(document.getElementById('inputBayar').value) || 0;
    const kembali = bayar - total;

    if (keranjang.length === 0) {
        // Buat ulang empty state, jangan pakai getElementById
        // karena bisa sudah terhapus dari DOM
        container.innerHTML = `
            <div class="keranjang-empty" id="keranjangEmpty"
                 style="display:flex">
                <i class="bi bi-cart3" style="font-size:2.5rem"></i>
                <span>Pilih produk untuk ditambahkan</span>
            </div>`;
        document.getElementById('subtotalDisplay').textContent  = formatRp(0);
        document.getElementById('totalDisplay').textContent     = formatRp(0);
        document.getElementById('kembalianDisplay').textContent = 'Rp 0';
        document.getElementById('btnBayar').disabled = true;
        return;
    }

    // Render item langsung ke innerHTML
    // Tidak pakai empty element sama sekali
    let html = '';
    keranjang.forEach(item => {
        const subtotal = item.harga * item.qty;
        html += `
        <div class="keranjang-item">
            <div class="item-info">
                <div class="item-nama">${escHtml(item.nama)}</div>
                <div class="item-harga">
                    ${formatRp(item.harga)} / ${escHtml(item.satuan)}
                </div>
            </div>
            <div class="item-qty">
                <div class="qty-btn minus" onclick="ubahQty('${item.id}', -1)">
                    <i class="bi bi-dash"></i>
                </div>
                <span class="qty-val">${item.qty}</span>
                <div class="qty-btn" onclick="ubahQty('${item.id}', 1)">
                    <i class="bi bi-plus"></i>
                </div>
            </div>
            <div class="item-subtotal">${formatRp(subtotal)}</div>
        </div>`;
    });

    container.innerHTML = html;

    // Update total display
    document.getElementById('subtotalDisplay').textContent = formatRp(total);
    document.getElementById('totalDisplay').textContent    = formatRp(total);

    // Update kembalian
    document.getElementById('kembalianDisplay').textContent =
        bayar > 0
            ? (kembali >= 0 ? formatRp(kembali) : '— Kurang ' + formatRp(Math.abs(kembali)))
            : 'Rp 0';

    // Update tombol bayar
    document.getElementById('btnBayar').disabled =
        keranjang.length === 0 || bayar <= 0 || bayar < total;
}

// ============================================
// HITUNG KEMBALIAN
// ============================================
function hitungKembalian() {
    const total   = keranjang.reduce((s, i) => s + i.harga * i.qty, 0);
    const bayar   = parseFloat(document.getElementById('inputBayar').value) || 0;
    const kembali = bayar - total;

    document.getElementById('kembalianDisplay').textContent =
        bayar > 0 ? (kembali >= 0 ? formatRp(kembali) : '— Kurang ' + formatRp(Math.abs(kembali))) : 'Rp 0';

    document.getElementById('btnBayar').disabled =
        keranjang.length === 0 || bayar < total || bayar === 0;
}

// ============================================
// PROSES TRANSAKSI
// ============================================
async function prosesTransaksi() {
    const total = keranjang.reduce((s, i) => s + i.harga * i.qty, 0);
    const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;

    if (keranjang.length === 0) {
        alert('Keranjang masih kosong!');
        return;
    }
    if (bayar <= 0) {
        alert('Masukkan uang bayar terlebih dahulu!');
        return;
    }
    if (bayar < total) {
        alert('Uang bayar kurang! Total: ' + formatRp(total));
        return;
    }

    const btnBayar = document.getElementById('btnBayar');
    btnBayar.disabled   = true;
    btnBayar.innerHTML  = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';

    try {
        const resp = await fetch('proses.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                keranjang : keranjang,
                bayar     : bayar,
                total     : total
            })
        });

        // Cek apakah response valid JSON
        const text = await resp.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(e) {
            console.error('Response bukan JSON:', text);
            alert('Error: Response tidak valid. Cek console untuk detail.');
            btnBayar.disabled  = false;
            btnBayar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Proses Transaksi';
            return;
        }

        if (result.status === 'ok') {
            tampilStruk(result, bayar);
        } else {
            alert('Gagal: ' + (result.message || 'Terjadi kesalahan'));
            btnBayar.disabled  = false;
            btnBayar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Proses Transaksi';
        }

    } catch (e) {
        console.error('Fetch error:', e);
        alert('Terjadi kesalahan koneksi: ' + e.message);
        btnBayar.disabled  = false;
        btnBayar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Proses Transaksi';
    }
}

// ============================================
// TAMPIL STRUK
// ============================================
// ============================================
// TAMPIL STRUK
// ============================================
function tampilStruk(data, bayar) {
    const total   = keranjang.reduce((s, i) => s + i.harga * i.qty, 0);
    const kembali = bayar - total;
    const tgl     = new Date().toLocaleString('id-ID');

    // Isi modal struk untuk tampilan layar
    document.getElementById('struk-no').textContent      = 'No: ' + data.no_transaksi;
    document.getElementById('struk-tgl').textContent     = tgl;
    document.getElementById('struk-total').textContent   = 'Total   : ' + formatRp(total);
    document.getElementById('struk-bayar').textContent   = 'Bayar   : ' + formatRp(bayar);
    document.getElementById('struk-kembalian').textContent = 'Kembali : ' + formatRp(kembali);

    let itemsHtml = '';
    keranjang.forEach(item => {
        itemsHtml += item.nama + '\n';
        itemsHtml += '  ' + item.qty + ' x ' + formatRp(item.harga) +
                     ' = ' + formatRp(item.harga * item.qty) + '\n';
    });
    document.getElementById('struk-items').textContent = itemsHtml;

    // Simpan data untuk cetak
    window._strukData = { data, bayar, total, kembali, tgl };

    // Tampilkan modal
    new bootstrap.Modal(document.getElementById('modalStruk')).show();
}

// ============================================
// CETAK STRUK — buka tab baru
// ============================================
function cetakStruk() {
    const { data, bayar, total, kembali, tgl } = window._strukData || {};

    if (!data) {
        alert('Data struk tidak ditemukan');
        return;
    }

    const namaToko  = <?= json_encode($profil['nama_toko'] ?? 'TOKO') ?>;
    const kotaToko  = <?= json_encode($profil['kota'] ?? '') ?>;
    const namaKasir = <?= json_encode($user['nama']) ?>;

    // Buat baris produk
    let barisProduk = '';
    keranjang.forEach(item => {
        const nama = item.nama.length > 22
            ? item.nama.substring(0, 22) + '...'
            : item.nama;
        barisProduk += `
            <div style="margin-bottom:4px">
                <div>${nama}</div>
                <div style="display:flex;justify-content:space-between">
                    <span>${item.qty} x ${formatRp(item.harga)}</span>
                    <span>${formatRp(item.harga * item.qty)}</span>
                </div>
            </div>`;
    });

    const htmlStruk = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Struk ${data.no_transaksi}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            width: 280px;
            padding: 8px;
            color: #000;
        }
        .center { text-align:center; }
        .bold   { font-weight:bold; }
        .divider { border-top:1px dashed #000; margin:6px 0; }
        .row {
            display:flex;
            justify-content:space-between;
            font-weight:600;
            margin-bottom:2px;
        }
        .small { font-size:10px; }
        @media print {
            body { width:100%; }
        }
    </style>
</head>
<body>
    <div class="center bold" style="font-size:14px">${namaToko}</div>
    <div class="center small">${kotaToko}</div>
    <div class="divider"></div>
    <div class="small">
        <div>No    : ${data.no_transaksi}</div>
        <div>Tgl   : ${tgl}</div>
        <div>Kasir : ${namaKasir}</div>
    </div>
    <div class="divider"></div>
    <div class="small">${barisProduk}</div>
    <div class="divider"></div>
    <div style="margin-bottom:4px">
        <div class="row"><span>TOTAL</span><span>${formatRp(total)}</span></div>
        <div class="row"><span>BAYAR</span><span>${formatRp(bayar)}</span></div>
        <div class="row"><span>KEMBALI</span><span>${formatRp(kembali)}</span></div>
    </div>
    <div class="divider"></div>
    <div class="center small" style="margin-top:6px">
        Terima kasih sudah berbelanja!
    </div>
    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() { window.close(); }, 1000);
        };
    <\/script>
</body>
</html>`;

    // Buka window baru kecil
    const w = window.open('', '_blank', 'width=320,height=500,toolbar=0,menubar=0,scrollbars=0');
    if (!w) {
        alert('Popup diblokir browser! Izinkan popup untuk halaman ini.');
        return;
    }
    w.document.write(htmlStruk);
    w.document.close();
}

function transaksiSelesai() {
    const modal = bootstrap.Modal.getInstance(
        document.getElementById('modalStruk')
    );
    if (modal) modal.hide();

    keranjang = [];
    document.getElementById('inputBayar').value            = '';
    document.getElementById('kembalianDisplay').textContent = 'Rp 0';
    document.getElementById('btnBayar').innerHTML =
        '<i class="bi bi-check-circle me-2"></i>Proses Transaksi';
    renderKeranjang();
    location.reload();
}

// ============================================
// HELPER
// ============================================
function formatRp(val) {
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
</body>
</html>