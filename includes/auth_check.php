<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek sudah login atau belum
function cekLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('/index.php', 'Silakan login terlebih dahulu', 'warning');
    }
}

// Cek role superadmin
function cekAdmin() {
    cekLogin();
    if ($_SESSION['role'] !== 'superadmin') {
        redirect('/index.php', 'Akses ditolak', 'error');
    }
}

// Cek role kasir
function cekKasir() {
    cekLogin();
    if (!in_array($_SESSION['role'], ['superadmin', 'kasir'])) {
        redirect('/index.php', 'Akses ditolak', 'error');
    }
}

// Cek role pembeli
function cekPembeli() {
    cekLogin();
    if ($_SESSION['role'] !== 'pembeli') {
        redirect('/index.php', 'Akses ditolak', 'error');
    }
}

// Ambil data user yang sedang login
function userLogin() {
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'nama'  => $_SESSION['nama']      ?? '',
        'email' => $_SESSION['email']     ?? '',
        'role'  => $_SESSION['role']      ?? '',
        'foto'  => $_SESSION['foto']      ?? null,
    ];
}

// Redirect jika sudah login (untuk halaman login/register)
function sudahLogin() {
    if (isset($_SESSION['user_id'])) {
        switch ($_SESSION['role']) {
            case 'superadmin':
                redirect('../admin/dashboard.php');
                break;
            case 'kasir':
                redirect('../kasir/dashboard.php');
                break;
            case 'pembeli':
                redirect('../toko/index.php');
                break;
        }
    }
}
?>