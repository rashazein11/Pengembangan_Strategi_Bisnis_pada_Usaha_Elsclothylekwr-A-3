<?php
session_start();
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$variasiId = (int) ($_POST["variasi_id"] ?? 0);
$jumlah = (int) ($_POST["jumlah"] ?? 1);

if ($variasiId <= 0 || $jumlah <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT v.id, v.stok, p.id AS produk_id
     FROM variasi_produk v
     JOIN produk p ON p.id = v.produk_id
     WHERE v.id = ? AND p.aktif = 1
     LIMIT 1"
);

$stmt->bind_param("i", $variasiId);
$stmt->execute();
$variasi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$variasi) {
    die("Variasi produk tidak ditemukan.");
}

if ($variasi["stok"] <= 0) {
    die("Maaf, stok produk sedang habis.");
}

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

$jumlahLama = $_SESSION["keranjang"][$variasiId] ?? 0;
$totalJumlah = $jumlahLama + $jumlah;

if ($totalJumlah > $variasi["stok"]) {
    die("Jumlah melebihi stok yang tersedia. Stok saat ini: " . $variasi["stok"]);
}

$_SESSION["keranjang"][$variasiId] = $totalJumlah;

header("Location: keranjang.php");
exit;
?>
