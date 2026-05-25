<?php
// ============================================
// FORMAT UANG
// ============================================
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ============================================
// FORMAT TANGGAL
// ============================================
function formatTanggal($tanggal) {
    $bulan = [
        '01' => 'Januari',  '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',    '05' => 'Mei',       '06' => 'Juni',
        '07' => 'Juli',     '08' => 'Agustus',   '09' => 'September',
        '10' => 'Oktober',  '11' => 'November',  '12' => 'Desember'
    ];
    $d = date('d', strtotime($tanggal));
    $m = date('m', strtotime($tanggal));
    $y = date('Y', strtotime($tanggal));
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

// ============================================
// FORMAT TANGGAL + JAM
// ============================================
function formatTanggalJam($datetime) {
    return formatTanggal($datetime) . ' ' . date('H:i', strtotime($datetime));
}

// ============================================
// HITUNG MARGIN
// ============================================
function hitungMargin($harga_beli, $harga_jual, $jumlah = 1) {
    return ($harga_jual - $harga_beli) * $jumlah;
}

// ============================================
// HITUNG PERSENTASE MARGIN
// ============================================
function persentaseMargin($harga_beli, $harga_jual) {
    if ($harga_beli == 0) return 0;
    return round((($harga_jual - $harga_beli) / $harga_beli) * 100, 2);
}

// ============================================
// GENERATE KODE BARANG OTOMATIS
// ============================================
function generateKodeBarang($pdo) {
    $prefix = 'BRG-';
    $stmt = $pdo->query("SELECT COUNT(*) FROM barang");
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 5, '0', STR_PAD_LEFT);
}

// ============================================
// GENERATE NOMOR TRANSAKSI
// ============================================
function generateNoTransaksi($pdo) {
    $tgl    = date('Ymd');
    $prefix = 'TRX-' . $tgl . '-';
    $stmt   = $pdo->prepare(
        "SELECT COUNT(*) FROM transaksi WHERE no_transaksi LIKE ?"
    );
    $stmt->execute([$prefix . '%']);
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

// ============================================
// GENERATE NOMOR PEMBELIAN
// ============================================
function generateNoPembelian($pdo) {
    $tgl    = date('Ymd');
    $prefix = 'PBL-' . $tgl . '-';
    $stmt   = $pdo->prepare(
        "SELECT COUNT(*) FROM pembelian WHERE no_pembelian LIKE ?"
    );
    $stmt->execute([$prefix . '%']);
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

// ============================================
// GENERATE NOMOR PESANAN
// ============================================
function generateNoPesanan($pdo) {
    $tgl    = date('Ymd');
    $prefix = 'ORD-' . $tgl . '-';
    $stmt   = $pdo->prepare(
        "SELECT COUNT(*) FROM pesanan WHERE no_pesanan LIKE ?"
    );
    $stmt->execute([$prefix . '%']);
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

// ============================================
// CEK STOK MENIPIS
// ============================================
function cekStokMenipis($pdo) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM barang WHERE stok <= stok_minimal"
    );
    return $stmt->fetchColumn();
}

// ============================================
// UPLOAD FOTO
// ============================================
function uploadFoto($file, $folder = 'assets/img/uploads/') {
    $ekstensi_ok = ['jpg', 'jpeg', 'png', 'webp'];
    $ekstensi    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maks_size   = 2 * 1024 * 1024; // 2MB

    if (!in_array($ekstensi, $ekstensi_ok)) {
        return ['status' => false, 'pesan' => 'Format foto tidak didukung (jpg, jpeg, png, webp)'];
    }
    if ($file['size'] > $maks_size) {
        return ['status' => false, 'pesan' => 'Ukuran foto maksimal 2MB'];
    }

    $nama_file = uniqid('img_') . '.' . $ekstensi;
    $tujuan    = $folder . $nama_file;

    if (move_uploaded_file($file['tmp_name'], $tujuan)) {
        return ['status' => true, 'nama_file' => $nama_file];
    }

    return ['status' => false, 'pesan' => 'Gagal upload foto'];
}

// ============================================
// REDIRECT DENGAN PESAN
// ============================================
function redirect($url, $pesan = '', $tipe = 'success') {
    if ($pesan) {
        $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe];
    }
    header('Location: ' . $url);
    exit;
}

// ============================================
// TAMPILKAN PESAN FLASH
// ============================================
function flashPesan() {
    if (isset($_SESSION['flash'])) {
        $f    = $_SESSION['flash'];
        $tipe = $f['tipe'] === 'success' ? 'success' : 
               ($f['tipe'] === 'error'   ? 'danger'  : $f['tipe']);
        echo '<div class="alert alert-' . $tipe . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($f['pesan']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['flash']);
    }
}

// ============================================
// SANITASI INPUT
// ============================================
function bersihkan($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>