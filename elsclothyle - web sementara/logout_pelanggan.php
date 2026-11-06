<?php
session_start();

// Hapus session khusus pelanggan
unset($_SESSION["pelanggan_id"]);
unset($_SESSION["pelanggan_nama"]);
unset($_SESSION["pelanggan_email"]);

// Kembali ke halaman utama
header("Location: index.php");
exit;
?>
