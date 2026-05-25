-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2026 at 05:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_toko`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'superadmin yang input',
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `kode_barang` varchar(50) NOT NULL,
  `nama_barang` varchar(200) NOT NULL,
  `satuan` varchar(30) NOT NULL DEFAULT 'pcs' COMMENT 'pcs, kg, liter, dll',
  `harga_beli` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stok` int(11) NOT NULL DEFAULT 0 COMMENT 'sisa stok realtime',
  `stok_minimal` int(11) NOT NULL DEFAULT 5 COMMENT 'batas alert stok menipis',
  `tanggal_masuk` date DEFAULT NULL COMMENT 'tanggal pertama kali produk masuk',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barang`
--

INSERT INTO `barang` (`id`, `user_id`, `kategori_id`, `supplier_id`, `kode_barang`, `nama_barang`, `satuan`, `harga_beli`, `harga_jual`, `stok`, `stok_minimal`, `tanggal_masuk`, `created_at`) VALUES
(27, 2, 12, 5, 'BRG-00001', 'Aqua 600ml', 'pcs', 2500.00, 4000.00, 98, 10, '2026-05-06', '2026-05-06 10:32:45'),
(28, 2, 12, 5, 'BRG-00002', 'Teh Botol 350ml', 'pcs', 3000.00, 5000.00, 78, 10, '2026-05-06', '2026-05-06 10:33:19'),
(29, 2, 13, 5, 'BRG-00003', 'Indomie Goreng', 'pcs', 3200.00, 5000.00, 48, 5, '2026-05-06', '2026-05-06 10:33:58'),
(30, 2, 13, 5, 'BRG-00004', 'Roti Tawar', 'pcs', 8000.00, 12000.00, 30, 5, '2026-05-06', '2026-05-06 10:35:13'),
(31, 2, 14, 5, 'BRG-00005', 'Chitato 68gr', 'pcs', 7000.00, 10000.00, 39, 5, '2026-05-06', '2026-05-06 10:35:49'),
(32, 2, 14, 5, 'BRG-00006', 'Oreo Vanilla', 'pcs', 5000.00, 8000.00, 58, 5, '2026-05-06', '2026-05-06 10:36:18');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_barang`
--

CREATE TABLE `kategori_barang` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'superadmin yang buat',
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_barang`
--

INSERT INTO `kategori_barang` (`id`, `user_id`, `nama`, `created_at`) VALUES
(12, 2, 'Minuman', '2026-05-06 10:29:08'),
(13, 2, 'Makanan', '2026-05-06 10:29:08'),
(14, 2, 'Snack', '2026-05-06 10:29:08');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian`
--

CREATE TABLE `pembelian` (
  `id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'siapa yang input',
  `no_pembelian` varchar(50) NOT NULL COMMENT 'format: PBL-YYYYMMDD-001',
  `tanggal_masuk` date NOT NULL,
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_detail`
--

CREATE TABLE `pembelian_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `pembelian_id` int(10) UNSIGNED NOT NULL,
  `barang_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengiriman`
--

CREATE TABLE `pengiriman` (
  `id` int(10) UNSIGNED NOT NULL,
  `pesanan_id` int(10) UNSIGNED NOT NULL,
  `nama_penerima` varchar(150) NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `alamat_lengkap` text NOT NULL,
  `kota_tujuan` varchar(100) NOT NULL,
  `status` enum('menunggu','diproses','dikirim','selesai') NOT NULL DEFAULT 'menunggu',
  `kurir` varchar(100) DEFAULT NULL COMMENT 'JNE, J&T, SiCepat, dll',
  `no_resi` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengiriman`
--

INSERT INTO `pengiriman` (`id`, `pesanan_id`, `nama_penerima`, `no_telepon`, `alamat_lengkap`, `kota_tujuan`, `status`, `kurir`, `no_resi`, `created_at`, `updated_at`) VALUES
(1, 1, 'Azza', '085651173362', 'Jl.PDKT Doang', 'Kota Palangka Raya', 'dikirim', 'J&amp;T Express', 'SDSD1232134', '2026-05-06 12:10:06', '2026-05-07 00:34:59'),
(2, 2, 'Azza', '085651173362', 'Jl.PDKT Doang', 'Kota Palangka Raya', 'dikirim', 'JNE', '', '2026-05-07 07:15:23', '2026-05-07 07:16:00');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'pembeli yang pesan',
  `no_pesanan` varchar(50) NOT NULL COMMENT 'format: ORD-YYYYMMDD-001',
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('menunggu','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `user_id`, `no_pesanan`, `total_harga`, `status`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 4, 'ORD-20260506-001', 26000.00, 'selesai', '', '2026-05-06 12:10:06', '2026-05-07 00:36:36'),
(2, 4, 'ORD-20260507-001', 18000.00, 'selesai', '', '2026-05-07 07:15:23', '2026-05-07 07:16:06');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan_detail`
--

CREATE TABLE `pesanan_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `pesanan_id` int(10) UNSIGNED NOT NULL,
  `barang_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL COMMENT 'snapshot harga saat pesan',
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pesanan_detail`
--

INSERT INTO `pesanan_detail` (`id`, `pesanan_id`, `barang_id`, `jumlah`, `harga_jual`, `subtotal`) VALUES
(1, 1, 32, 2, 8000.00, 16000.00),
(2, 1, 31, 1, 10000.00, 10000.00),
(3, 2, 29, 2, 5000.00, 10000.00),
(4, 2, 27, 2, 4000.00, 8000.00);

-- --------------------------------------------------------

--
-- Table structure for table `profil_pembeli`
--

CREATE TABLE `profil_pembeli` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profil_pembeli`
--

INSERT INTO `profil_pembeli` (`id`, `user_id`, `alamat`, `created_at`) VALUES
(1, 4, 'Jl.PDKT Doang', '2026-05-06 10:50:19');

-- --------------------------------------------------------

--
-- Table structure for table `profil_usaha`
--

CREATE TABLE `profil_usaha` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nama_toko` varchar(150) NOT NULL,
  `bidang_usaha` varchar(100) NOT NULL,
  `lama_beroperasi` int(11) NOT NULL DEFAULT 0 COMMENT 'dalam tahun',
  `provinsi` varchar(100) NOT NULL,
  `kota` varchar(100) NOT NULL,
  `kecamatan` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profil_usaha`
--

INSERT INTO `profil_usaha` (`id`, `user_id`, `nama_toko`, `bidang_usaha`, `lama_beroperasi`, `provinsi`, `kota`, `kecamatan`, `created_at`) VALUES
(1, 1, 'Toko Saya', 'Umum', 1, 'Kalimantan Tengah', 'Palangka Raya', 'Jekan Raya', '2026-05-05 17:15:56'),
(2, 2, 'Jellybeam', 'Kuliner', 4, 'Kalimantan Tengah', 'Kodya Palangka Raya', 'JEKAN RAYA', '2026-05-05 17:25:40');

-- --------------------------------------------------------

--
-- Table structure for table `stok_mutasi`
--

CREATE TABLE `stok_mutasi` (
  `id` int(10) UNSIGNED NOT NULL,
  `barang_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'siapa yang melakukan',
  `tipe` enum('masuk','keluar','penjualan','pesanan') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `stok_sebelum` int(11) NOT NULL,
  `stok_sesudah` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stok_mutasi`
--

INSERT INTO `stok_mutasi` (`id`, `barang_id`, `user_id`, `tipe`, `jumlah`, `stok_sebelum`, `stok_sesudah`, `keterangan`, `created_at`) VALUES
(1, 27, 2, 'masuk', 100, 0, 100, 'Stok awal saat input barang', '2026-05-06 10:32:45'),
(2, 28, 2, 'masuk', 80, 0, 80, 'Stok awal saat input barang', '2026-05-06 10:33:19'),
(3, 29, 2, 'masuk', 50, 0, 50, 'Stok awal saat input barang', '2026-05-06 10:33:58'),
(4, 30, 2, 'masuk', 30, 0, 30, 'Stok awal saat input barang', '2026-05-06 10:35:13'),
(5, 31, 2, 'masuk', 40, 0, 40, 'Stok awal saat input barang', '2026-05-06 10:35:49'),
(6, 32, 2, 'masuk', 60, 0, 60, 'Stok awal saat input barang', '2026-05-06 10:36:18'),
(7, 32, 4, 'pesanan', 2, 60, 58, 'Pesanan online: ORD-20260506-001', '2026-05-06 12:10:06'),
(8, 31, 4, 'pesanan', 1, 40, 39, 'Pesanan online: ORD-20260506-001', '2026-05-06 12:10:06'),
(9, 29, 4, 'pesanan', 2, 50, 48, 'Pesanan online: ORD-20260507-001', '2026-05-07 07:15:23'),
(10, 27, 4, 'pesanan', 2, 100, 98, 'Pesanan online: ORD-20260507-001', '2026-05-07 07:15:23'),
(11, 28, 3, 'penjualan', 2, 80, 78, 'Penjualan POS: TRX-20260508-001', '2026-05-08 18:33:50');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'superadmin yang input',
  `nama_supplier` varchar(150) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`id`, `user_id`, `nama_supplier`, `no_telepon`, `alamat`, `kota`, `is_aktif`, `created_at`) VALUES
(5, 2, 'Supplier Test', '081234567890', NULL, 'Palangka Raya', 1, '2026-05-06 10:28:43');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'kasir yang proses',
  `no_transaksi` varchar(50) NOT NULL COMMENT 'format: TRX-YYYYMMDD-001',
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `bayar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_margin` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tanggal_terjual` date NOT NULL,
  `status` enum('selesai','batal') NOT NULL DEFAULT 'selesai',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `user_id`, `no_transaksi`, `total_harga`, `bayar`, `kembalian`, `total_margin`, `tanggal_terjual`, `status`, `created_at`) VALUES
(1, 3, 'TRX-20260508-001', 10000.00, 20000.00, 10000.00, 4000.00, '2026-05-09', 'selesai', '2026-05-08 18:33:50');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaksi_id` int(10) UNSIGNED NOT NULL,
  `barang_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL COMMENT 'snapshot harga beli saat transaksi',
  `harga_jual` decimal(15,2) NOT NULL COMMENT 'snapshot harga jual saat transaksi',
  `margin` decimal(15,2) NOT NULL COMMENT '(harga_jual - harga_beli) x jumlah',
  `subtotal` decimal(15,2) NOT NULL COMMENT 'harga_jual x jumlah'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi_detail`
--

INSERT INTO `transaksi_detail` (`id`, `transaksi_id`, `barang_id`, `jumlah`, `harga_beli`, `harga_jual`, `margin`, `subtotal`) VALUES
(1, 1, 28, 2, 3000.00, 5000.00, 4000.00, 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','kasir','pembeli') NOT NULL DEFAULT 'pembeli',
  `foto` varchar(255) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `foto`, `no_telepon`, `is_aktif`, `created_at`) VALUES
(1, 'Admin Toko', 'admin@toko.com', '$2y$10$contohHashBcryptDisini', 'superadmin', NULL, NULL, 1, '2026-05-05 17:15:56'),
(2, 'Samuel Raka Yustianto', 'samuelrakayustianto@gmail.com', '$2y$10$ziyW5DPtXrhN.kmuh4I9e.I3Bd2xLchywL1FmdPIbr.xIh9RIKX9.', 'superadmin', NULL, '089516836510', 1, '2026-05-05 17:25:40'),
(3, 'Keysya Agni Yustiaman', 'keysya@gmail.com', '$2y$10$7ieZkNOcwK.Ez3woPJ71hO.JBJpfIPj/MlE0jPy16IZ16taSx7x6e', 'kasir', NULL, '089516836511', 1, '2026-05-06 04:54:40'),
(4, 'Azza', 'azza@gmail.com', '$2y$10$1a1ELo/Y73zhG3iG5Go/N.z13kV0dJcldnz7OOZSDYNibfRXVLRsy', 'pembeli', NULL, '089516836510', 1, '2026-05-06 10:50:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD KEY `fk_barang_user` (`user_id`),
  ADD KEY `fk_barang_kategori` (`kategori_id`),
  ADD KEY `fk_barang_supplier` (`supplier_id`),
  ADD KEY `idx_kode_barang` (`kode_barang`),
  ADD KEY `idx_nama_barang` (`nama_barang`);

--
-- Indexes for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kategori_user` (`user_id`);

--
-- Indexes for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_pembelian` (`no_pembelian`),
  ADD KEY `fk_pembelian_supplier` (`supplier_id`),
  ADD KEY `fk_pembelian_user` (`user_id`),
  ADD KEY `idx_tanggal_masuk` (`tanggal_masuk`);

--
-- Indexes for table `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pembelian_detail_pembelian` (`pembelian_id`),
  ADD KEY `fk_pembelian_detail_barang` (`barang_id`);

--
-- Indexes for table `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pesanan_id` (`pesanan_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_pesanan` (`no_pesanan`),
  ADD KEY `fk_pesanan_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `pesanan_detail`
--
ALTER TABLE `pesanan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pesanan_detail_pesanan` (`pesanan_id`),
  ADD KEY `fk_pesanan_detail_barang` (`barang_id`);

--
-- Indexes for table `profil_pembeli`
--
ALTER TABLE `profil_pembeli`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `profil_usaha`
--
ALTER TABLE `profil_usaha`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mutasi_barang` (`barang_id`),
  ADD KEY `fk_mutasi_user` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_user` (`user_id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_transaksi` (`no_transaksi`),
  ADD KEY `fk_transaksi_user` (`user_id`),
  ADD KEY `idx_tanggal_terjual` (`tanggal_terjual`),
  ADD KEY `idx_no_transaksi` (`no_transaksi`);

--
-- Indexes for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transaksi_detail_transaksi` (`transaksi_id`),
  ADD KEY `fk_transaksi_detail_barang` (`barang_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `pembelian`
--
ALTER TABLE `pembelian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengiriman`
--
ALTER TABLE `pengiriman`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pesanan_detail`
--
ALTER TABLE `pesanan_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `profil_pembeli`
--
ALTER TABLE `profil_pembeli`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `profil_usaha`
--
ALTER TABLE `profil_usaha`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `fk_barang_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_barang` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_barang_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_barang_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  ADD CONSTRAINT `fk_kategori_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD CONSTRAINT `fk_pembelian_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembelian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD CONSTRAINT `fk_pembelian_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembelian_detail_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD CONSTRAINT `fk_pengiriman_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pesanan_detail`
--
ALTER TABLE `pesanan_detail`
  ADD CONSTRAINT `fk_pesanan_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pesanan_detail_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `profil_pembeli`
--
ALTER TABLE `profil_pembeli`
  ADD CONSTRAINT `fk_profil_pembeli_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `profil_usaha`
--
ALTER TABLE `profil_usaha`
  ADD CONSTRAINT `fk_profil_usaha_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  ADD CONSTRAINT `fk_mutasi_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mutasi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `supplier`
--
ALTER TABLE `supplier`
  ADD CONSTRAINT `fk_supplier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `fk_transaksi_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
