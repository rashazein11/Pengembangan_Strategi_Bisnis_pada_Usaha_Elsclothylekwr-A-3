<?php
session_start();
require_once "config.php";

// Jika belum login
if (!isset($_SESSION["pelanggan_id"])) {
    $_SESSION["redirect_after_login"] = "checkout.php";
    header("Location: login_pelanggan.php");
    exit;
}

// Jika keranjang kosong
if (empty($_SESSION["keranjang"])) {
    header("Location: keranjang.php");
    exit;
}

$pelangganId = (int) $_SESSION["pelanggan_id"];
$pesanError = "";

// Ambil data pelanggan
$stmt = $conn->prepare(
    "SELECT nama, email, no_hp, alamat FROM pelanggan WHERE id = ?"
);
$stmt->bind_param("i", $pelangganId);
$stmt->execute();
$pelanggan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pelanggan) {
    die("Data pelanggan tidak ditemukan.");
}

// Ambil isi keranjang
function ambilKeranjang($conn, $keranjang) {
    $items = [];
    $total = 0;

    $stmt = $conn->prepare("
        SELECT
            v.id AS variasi_id,
            v.produk_id,
            v.ukuran,
            v.warna,
            v.stok,
            p.nama_produk,
            p.harga,
            p.foto
        FROM variasi_produk v
        JOIN produk p ON p.id = v.produk_id
        WHERE v.id = ? AND p.aktif = 1
    ");

    foreach ($keranjang as $variasiId => $jumlah) {
        $variasiId = (int) $variasiId;
        $jumlah = (int) $jumlah;

        if ($jumlah < 1) {
            continue;
        }

        $stmt->bind_param("i", $variasiId);
        $stmt->execute();

        $produk = $stmt->get_result()->fetch_assoc();

        if (!$produk) {
            continue;
        }

        $produk["jumlah"] = $jumlah;
        $produk["subtotal"] = $produk["harga"] * $jumlah;

        $items[] = $produk;
        $total += $produk["subtotal"];
    }

    $stmt->close();

    return [
        "items" => $items,
        "total" => $total
    ];
}

// Proses checkout
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $alamat = trim($_POST["alamat"] ?? "");
    $noHp = trim($_POST["no_hp"] ?? "");
    $catatan = trim($_POST["catatan"] ?? "");
    $metodePembayaran = $_POST["metode_pembayaran"] ?? "";

    $metodeTersedia = ["QRIS", "GoPay", "Transfer Bank"];

    if ($alamat === "" || $noHp === "") {
        $pesanError = "Alamat dan nomor HP wajib diisi.";

    } elseif (!in_array($metodePembayaran, $metodeTersedia, true)) {
        $pesanError = "Silakan pilih metode pembayaran.";

    } else {

        $conn->begin_transaction();

        try {
            $items = [];
            $total = 0;

            // Periksa ulang harga dan stok dari database
            $stmt = $conn->prepare("
                SELECT
                    v.id AS variasi_id,
                    v.produk_id,
                    v.ukuran,
                    v.warna,
                    v.stok,
                    p.nama_produk,
                    p.harga
                FROM variasi_produk v
                JOIN produk p ON p.id = v.produk_id
                WHERE v.id = ? AND p.aktif = 1
                FOR UPDATE
            ");

            foreach ($_SESSION["keranjang"] as $variasiId => $jumlah) {

                $variasiId = (int) $variasiId;
                $jumlah = (int) $jumlah;

                if ($jumlah < 1) {
                    throw new Exception("Jumlah produk tidak valid.");
                }

                $stmt->bind_param("i", $variasiId);
                $stmt->execute();

                $produk = $stmt->get_result()->fetch_assoc();

                if (!$produk) {
                    throw new Exception(
                        "Ada produk yang sudah tidak tersedia."
                    );
                }

                if ($jumlah > (int) $produk["stok"]) {
                    throw new Exception(
                        "Stok " . $produk["nama_produk"] .
                        " tidak mencukupi. Silakan periksa keranjang."
                    );
                }

                $produk["jumlah"] = $jumlah;
                $produk["subtotal"] = $produk["harga"] * $jumlah;

                $items[] = $produk;
                $total += $produk["subtotal"];
            }

            $stmt->close();

            if (empty($items)) {
                throw new Exception(
                    "Keranjang kosong atau produk tidak tersedia."
                );
            }

            // Simpan pesanan
            $status = "Menunggu Pembayaran";

            $stmtPesanan = $conn->prepare("
                INSERT INTO pesanan
                (
                    pelanggan_id,
                    total_harga,
                    metode_pembayaran,
                    alamat_pengiriman,
                    no_hp,
                    catatan,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtPesanan->bind_param(
                "idsssss",
                $pelangganId,
                $total,
                $metodePembayaran,
                $alamat,
                $noHp,
                $catatan,
                $status
            );

            $stmtPesanan->execute();

            $pesananId = $conn->insert_id;

            $stmtPesanan->close();

            // Simpan detail pesanan
            $stmtDetail = $conn->prepare("
                INSERT INTO detail_pesanan
                (
                    pesanan_id,
                    produk_id,
                    variasi_id,
                    nama_produk,
                    ukuran,
                    warna,
                    harga,
                    jumlah,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Kurangi stok
            $stmtStok = $conn->prepare("
                UPDATE variasi_produk
                SET stok = stok - ?
                WHERE id = ? AND stok >= ?
            ");

            foreach ($items as $item) {

                $produkId = (int) $item["produk_id"];
                $variasiId = (int) $item["variasi_id"];
                $namaProduk = $item["nama_produk"];
                $ukuran = $item["ukuran"];
                $warna = $item["warna"];
                $harga = (float) $item["harga"];
                $jumlah = (int) $item["jumlah"];
                $subtotal = (float) $item["subtotal"];

                $stmtDetail->bind_param(
                    "iiisssdid",
                    $pesananId,
                    $produkId,
                    $variasiId,
                    $namaProduk,
                    $ukuran,
                    $warna,
                    $harga,
                    $jumlah,
                    $subtotal
                );

                $stmtDetail->execute();

                $stmtStok->bind_param(
                    "iii",
                    $jumlah,
                    $variasiId,
                    $jumlah
                );

                $stmtStok->execute();

                if ($stmtStok->affected_rows !== 1) {
                    throw new Exception(
                        "Gagal memperbarui stok produk."
                    );
                }
            }

            $stmtDetail->close();
            $stmtStok->close();

            // Simpan transaksi
            $conn->commit();

            // Kosongkan keranjang
            unset($_SESSION["keranjang"]);

            // Arahkan ke halaman pembayaran
            header("Location: bayar.php?id=" . $pesananId);
            exit;

        } catch (Exception $e) {

            $conn->rollback();
            $pesanError = $e->getMessage();
        }
    }
}

// Ambil data keranjang untuk ditampilkan
$dataKeranjang = ambilKeranjang($conn, $_SESSION["keranjang"]);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f7f5;
            color: #333;
        }

        header {
            background: white;
            padding: 20px 7%;
            border-bottom: 1px solid #eee;
        }

        header a {
            color: #333;
            text-decoration: none;
            font-size: 23px;
            font-weight: bold;
        }

        .container {
            max-width: 850px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .panel {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px #00000008;
        }

        h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            background: white;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .item {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 13px 0;
            border-bottom: 1px solid #eee;
        }

        .total {
            display: flex;
            justify-content: space-between;
            font-size: 19px;
            font-weight: bold;
            margin-top: 20px;
        }

        .tombol {
            display: block;
            width: 100%;
            padding: 14px;
            background: #292522;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
            text-align: center;
        }

        .tombol:hover {
            background: #a47768;
        }

        .error {
            padding: 13px;
            background: #ffe5e5;
            color: #b00020;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .kembali {
            color: #333;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }

        .info-pembayaran {
            color: #777;
            font-size: 13px;
            margin-top: 8px;
        }

        @media (max-width: 600px) {
            .panel {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <a href="index.php">ELSclothyle</a>
</header>

<div class="container">

    <h2>Checkout</h2>

    <?php if ($pesanError !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($pesanError) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($dataKeranjang["items"])): ?>

        <div class="panel">
            <h3>Keranjang belanja kosong</h3>
            <p>Silakan pilih produk terlebih dahulu.</p>
            <a href="index.php" class="tombol">Mulai Belanja</a>
        </div>

    <?php else: ?>

        <form method="POST">

            <div class="panel">

                <h2>Informasi Pengiriman</h2>

                <label>Nama Pelanggan</label>
                <input
                    type="text"
                    value="<?= htmlspecialchars($pelanggan["nama"]) ?>"
                    disabled
                >

                <label>Email</label>
                <input
                    type="email"
                    value="<?= htmlspecialchars($pelanggan["email"]) ?>"
                    disabled
                >

                <label>Nomor HP</label>
                <input
                    type="text"
                    name="no_hp"
                    value="<?= htmlspecialchars($_POST["no_hp"] ?? $pelanggan["no_hp"] ?? "") ?>"
                    required
                >

                <label>Alamat Pengiriman</label>
                <textarea name="alamat" required><?= htmlspecialchars($_POST["alamat"] ?? $pelanggan["alamat"] ?? "") ?></textarea>

                <label>Catatan (Opsional)</label>
                <textarea name="catatan"><?= htmlspecialchars($_POST["catatan"] ?? "") ?></textarea>

            </div>

            <div class="panel">

                <h2>Metode Pembayaran</h2>

                <label>Pilih metode pembayaran</label>

                <select name="metode_pembayaran" required>
                    <option value="">-- Pilih Pembayaran --</option>

                    <option value="QRIS"
                        <?= (($_POST["metode_pembayaran"] ?? "") === "QRIS") ? "selected" : "" ?>>
                        QRIS
                    </option>

                    <option value="GoPay"
                        <?= (($_POST["metode_pembayaran"] ?? "") === "GoPay") ? "selected" : "" ?>>
                        GoPay
                    </option>

                    <option value="Transfer Bank"
                        <?= (($_POST["metode_pembayaran"] ?? "") === "Transfer Bank") ? "selected" : "" ?>>
                        Transfer Bank
                    </option>
                </select>

                <p class="info-pembayaran">
                    Pembayaran dilakukan melalui halaman simulasi setelah pesanan dibuat.
                </p>

            </div>

            <div class="panel">

                <h2>Ringkasan Pesanan</h2>

                <?php foreach ($dataKeranjang["items"] as $item): ?>

                    <div class="item">

                        <div>
                            <strong>
                                <?= htmlspecialchars($item["nama_produk"]) ?>
                            </strong>

                            <br>

                            Ukuran: <?= htmlspecialchars($item["ukuran"]) ?>

                            <br>

                            Warna: <?= htmlspecialchars($item["warna"]) ?>

                            <br>

                            Jumlah: <?= $item["jumlah"] ?>
                        </div>

                        <div>
                            Rp<?= number_format($item["subtotal"], 0, ",", ".") ?>
                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="total">
                    <span>Total</span>

                    <span>
                        Rp<?= number_format($dataKeranjang["total"], 0, ",", ".") ?>
                    </span>
                </div>

                <button type="submit" class="tombol">
                    Buat Pesanan
                </button>

                <a href="keranjang.php" class="kembali">
                    ← Kembali ke Keranjang
                </a>

            </div>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
