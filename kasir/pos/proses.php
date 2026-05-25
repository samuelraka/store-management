<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';

cekKasir();
$user = userLogin();

header('Content-Type: application/json');

// Ambil JSON body
$body = json_decode(file_get_contents('php://input'), true);

$keranjang = $body['keranjang'] ?? [];
$bayar     = (float)($body['bayar'] ?? 0);
$total     = (float)($body['total'] ?? 0);

if (empty($keranjang)) {
    echo json_encode(['status' => 'error', 'message' => 'Keranjang kosong']);
    exit;
}

if ($bayar < $total) {
    echo json_encode(['status' => 'error', 'message' => 'Uang bayar kurang']);
    exit;
}

try {
    $pdo->beginTransaction();

    $no_transaksi = generateNoTransaksi($pdo);
    $kembalian    = $bayar - $total;
    $total_margin = 0;

    // Validasi stok semua item dulu
    foreach ($keranjang as $item) {
        $bid = (int)$item['id'];
        $qty = (int)$item['qty'];

        $stmt = $pdo->prepare("SELECT stok FROM barang WHERE id = ?");
        $stmt->execute([$bid]);
        $stok = (int)$stmt->fetchColumn();

        if ($stok < $qty) {
            $pdo->rollBack();
            echo json_encode([
                'status'  => 'error',
                'message' => "Stok {$item['nama']} tidak mencukupi (sisa: $stok)"
            ]);
            exit;
        }
    }

    // Hitung total margin
    foreach ($keranjang as $item) {
        $margin       = ($item['harga'] - $item['hargaBeli']) * $item['qty'];
        $total_margin += $margin;
    }

    // Insert header transaksi
    $stmt = $pdo->prepare(
        "INSERT INTO transaksi
         (user_id, no_transaksi, total_harga, bayar, kembalian,
          total_margin, tanggal_terjual, status)
         VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'selesai')"
    );
    $stmt->execute([
        $user['id'], $no_transaksi, $total,
        $bayar, $kembalian, $total_margin
    ]);
    $transaksi_id = $pdo->lastInsertId();

    // Insert detail + update stok + catat mutasi
    foreach ($keranjang as $item) {
        $bid       = (int)$item['id'];
        $qty       = (int)$item['qty'];
        $harga     = (float)$item['harga'];
        $hargaBeli = (float)$item['hargaBeli'];
        $subtotal  = $harga * $qty;
        $margin    = ($harga - $hargaBeli) * $qty;

        // Insert detail
        $pdo->prepare(
            "INSERT INTO transaksi_detail
             (transaksi_id, barang_id, jumlah, harga_beli,
              harga_jual, margin, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $transaksi_id, $bid, $qty,
            $hargaBeli, $harga, $margin, $subtotal
        ]);

        // Ambil stok sebelum
        $stok_stmt = $pdo->prepare("SELECT stok FROM barang WHERE id = ?");
        $stok_stmt->execute([$bid]);
        $stok_sebelum = (int)$stok_stmt->fetchColumn();
        $stok_sesudah = $stok_sebelum - $qty;

        // Update stok barang
        $pdo->prepare(
            "UPDATE barang SET stok = stok - ? WHERE id = ?"
        )->execute([$qty, $bid]);

        // Catat mutasi stok
        $pdo->prepare(
            "INSERT INTO stok_mutasi
             (barang_id, user_id, tipe, jumlah, stok_sebelum,
              stok_sesudah, keterangan)
             VALUES (?, ?, 'penjualan', ?, ?, ?, ?)"
        )->execute([
            $bid, $user['id'], $qty, $stok_sebelum, $stok_sesudah,
            'Penjualan POS: ' . $no_transaksi
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status'       => 'ok',
        'no_transaksi' => $no_transaksi,
        'total'        => $total,
        'bayar'        => $bayar,
        'kembalian'    => $kembalian,
        'total_margin' => $total_margin
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}