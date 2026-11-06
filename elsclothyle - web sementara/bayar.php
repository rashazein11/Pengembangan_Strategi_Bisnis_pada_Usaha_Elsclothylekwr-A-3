<?php
session_start();
require_once "config.php";

// Pastikan pelanggan sudah login
if (!isset($_SESSION["pelanggan_id"])) {
    $_SESSION["redirect_after_login"] = "index.php";
    header("Location: login_pelanggan.php");
    exit;
}

$pelangganId = (int) $_SESSION["pelanggan_id"];

// Ambil ID pesanan
$pesananId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($pesananId <= 0) {
    header("Location: index.php");
    exit;
}

// Proses simulasi pembayaran
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $conn->prepare("
        UPDATE pesanan
        SET status = 'Sudah Dibayar'
        WHERE id = ?
        AND pelanggan_id = ?
        AND status = 'Menunggu Pembayaran'
    ");

    $stmt->bind_param("ii", $pesananId, $pelangganId);
    $stmt->execute();
    $stmt->close();

    header("Location: bayar.php?id=" . $pesananId . "&sukses=1");
    exit;
}

// Ambil data pesanan milik pelanggan yang sedang login
$stmt = $conn->prepare("
    SELECT id, total_harga, metode_pembayaran, status, dibuat_pada
    FROM pesanan
    WHERE id = ? AND pelanggan_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $pesananId, $pelangganId);
$stmt->execute();
$result = $stmt->get_result();
$pesanan = $result->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    die("Pesanan tidak ditemukan.");
}

$berhasil = isset($_GET["sukses"]) && $pesanan["status"] === "Sudah Dibayar";

$metode = $pesanan["metode_pembayaran"];

$namaMetode = [
    "QRIS" => "QRIS",
    "GoPay" => "GoPay",
    "Transfer Bank" => "Transfer Bank"
];

if (!isset($namaMetode[$metode])) {
    $metode = "QRIS";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f5f2;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.07);
        }

        h1 {
            text-align: center;
            margin-top: 0;
        }

        .info {
            background: #f7f5f2;
            padding: 18px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info p {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .total {
            font-size: 20px;
            font-weight: bold;
            color: #9b705c;
        }

        .payment-box {
            text-align: center;
            padding: 25px 10px;
            border: 1px solid #eee;
            border-radius: 10px;
            margin: 20px 0;
        }

        .payment-box h2 {
            color: #9b705c;
        }

        .simulation {
            color: #777;
            font-size: 13px;
            line-height: 1.6;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #9b705c;
            color: white;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            text-align: center;
            font-size: 15px;
            cursor: pointer;
            margin-top: 15px;
        }

        .btn:hover {
            background: #805944;
        }

        .success {
            background: #e8f5e9;
            color: #26743b;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        .warning {
            background: #fff4df;
            color: #8a6425;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>

<body>

<div class="container">
    <div class="card">

        <?php if ($berhasil): ?>

            <h1>Pembayaran Berhasil!</h1>

            <div class="success">
                Pesanan kamu sudah berhasil dibayar melalui simulasi.
            </div>

            <div class="info">
                <p>
                    <span>Nomor Pesanan</span>
                    <strong>#<?= $pesanan["id"]; ?></strong>
                </p>

                <p>
                    <span>Metode Pembayaran</span>
                    <strong><?= htmlspecialchars($metode); ?></strong>
                </p>

                <p>
                    <span>Total Pembayaran</span>
                    <strong>
                        Rp<?= number_format($pesanan["total_harga"], 0, ",", "."); ?>
                    </strong>
                </p>

                <p>
                    <span>Status</span>
                    <strong>Sudah Dibayar</strong>
                </p>
            </div>

            <a href="index.php" class="btn">Kembali ke Beranda</a>

        <?php elseif ($pesanan["status"] === "Menunggu Pembayaran"): ?>

            <h1>Pembayaran Pesanan</h1>

            <p style="text-align:center;color:#777;">
                Selesaikan pembayaran pesanan Elsclothyle kamu.
            </p>

            <div class="info">
                <p>
                    <span>Nomor Pesanan</span>
                    <strong>#<?= $pesanan["id"]; ?></strong>
                </p>

                <p>
                    <span>Metode Pembayaran</span>
                    <strong><?= htmlspecialchars($metode); ?></strong>
                </p>

                <p class="total">
                    <span>Total</span>
                    <strong>
                        Rp<?= number_format($pesanan["total_harga"], 0, ",", "."); ?>
                    </strong>
                </p>
            </div>

            <div class="payment-box">
                <h2><?= htmlspecialchars($metode); ?></h2>

                <?php if ($metode === "QRIS"): ?>
                    <p>Silakan lakukan simulasi pembayaran menggunakan QRIS.</p>
                    <div style="font-size:65px;">▦</div>

                <?php elseif ($metode === "GoPay"): ?>
                    <p>Simulasi pembayaran menggunakan GoPay.</p>
                    <div style="font-size:45px;font-weight:bold;">GoPay</div>

                <?php else: ?>
                    <p>Simulasi pembayaran melalui Transfer Bank.</p>
                    <div style="font-size:22px;font-weight:bold;">
                        1234567890
                    </div>
                    <p>Nomor rekening simulasi, bukan rekening asli.</p>
                <?php endif; ?>

                <p class="simulation">
                    Informasi pembayaran di atas hanya untuk keperluan demonstrasi proyek.
                    Tidak ada transaksi uang sungguhan.
                </p>
            </div>

            <div class="warning">
                Ini adalah halaman simulasi. Tekan tombol di bawah untuk
                mengubah status pesanan menjadi Sudah Dibayar.
            </div>

            <form method="POST">
                <button type="submit" class="btn"
                    onclick="return confirm('Konfirmasi simulasi pembayaran berhasil?')">
                    Simulasi Pembayaran Berhasil
                </button>
            </form>

        <?php else: ?>

            <h1>Status Pesanan</h1>

            <div class="info">
                <p>
                    <span>Nomor Pesanan</span>
                    <strong>#<?= $pesanan["id"]; ?></strong>
                </p>

                <p>
                    <span>Status</span>
                    <strong><?= htmlspecialchars($pesanan["status"]); ?></strong>
                </p>
            </div>

            <p class="simulation">
                Pesanan ini tidak sedang menunggu pembayaran.
            </p>

            <a href="index.php" class="btn">Kembali ke Beranda</a>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
