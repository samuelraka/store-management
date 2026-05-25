<?php
function generateNoTransaksi($pdo) {
    $tgl = date('Ymd');
    $prefix = 'TRX-' . $tgl . '-';
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM transaksi 
         WHERE no_transaksi LIKE '$prefix%'"
    );
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

function generateNoPembelian($pdo) {
    $tgl = date('Ymd');
    $prefix = 'PBL-' . $tgl . '-';
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM pembelian 
         WHERE no_pembelian LIKE '$prefix%'"
    );
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

function generateNoPesanan($pdo) {
    $tgl = date('Ymd');
    $prefix = 'ORD-' . $tgl . '-';
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM pesanan 
         WHERE no_pesanan LIKE '$prefix%'"
    );
    $urut = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
}
?>