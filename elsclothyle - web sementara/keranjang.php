<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

$pesan = "";

// Menghapus barang
if (isset($_POST["hapus"])) {
    $variasiId = (int) $_POST["hapus"];
    unset($_SESSION["keranjang"][$variasiId]);
    header("Location: keranjang.php");
    exit;
}

// Memperbarui jumlah barang
if (isset($_POST["update"])) {
    foreach ($_POST["jumlah"] as $id => $jumlah) {
        $id = (int) $id;
        $jumlah = (int) $jumlah;

        if (!isset($_SESSION["keranjang"][$id])) {
            continue;
        }

        if ($jumlah <= 0) {
            unset($_SESSION["keranjang"][$id]);
            continue;
        }

        $stmt = $conn->prepare(
            "SELECT stok FROM variasi_produk WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dataStok = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dataStok) {
            unset($_SESSION["keranjang"][$id]);
        } elseif ($jumlah > $dataStok["stok"]) {
            $_SESSION["keranjang"][$id] = (int) $dataStok["stok"];
            $pesan = "Jumlah disesuaikan dengan stok yang tersedia.";
        } else {
            $_SESSION["keranjang"][$id] = $jumlah;
        }
    }

    if ($pesan !== "") {
        $_SESSION["pesan_keranjang"] = $pesan;
    }

    header("Location: keranjang.php");
    exit;
}

if (isset($_SESSION["pesan_keranjang"])) {
    $pesan = $_SESSION["pesan_keranjang"];
    unset($_SESSION["pesan_keranjang"]);
}

$items = [];
$total = 0;

// Mengambil data produk di keranjang
foreach ($_SESSION["keranjang"] as $variasiId => $jumlah) {
    $stmt = $conn->prepare(
        "SELECT
            v.id AS variasi_id,
            v.ukuran,
            v.warna,
            v.stok,
            p.id AS produk_id,
            p.nama_produk,
            p.harga,
            p.foto
         FROM variasi_produk v
         JOIN produk p ON p.id = v.produk_id
         WHERE v.id = ? AND p.aktif = 1"
    );

    $stmt->bind_param("i", $variasiId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item || $item["stok"] <= 0) {
        unset($_SESSION["keranjang"][$variasiId]);
        continue;
    }

    if ($jumlah > $item["stok"]) {
        $jumlah = (int) $item["stok"];
        $_SESSION["keranjang"][$variasiId] = $jumlah;
    }

    $item["jumlah"] = $jumlah;
    $item["subtotal"] = $item["harga"] * $jumlah;

    $total += $item["subtotal"];
    $items[] = $item;
}

$totalBarang = array_sum($_SESSION["keranjang"]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f4f1;
            color: #292524;
        }

        .container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            margin-bottom: 25px;
        }

        .item {
            display: flex;
            gap: 20px;
            align-items: center;
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 9px;
        }

        .item img {
            width: 110px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }

        .item-info {
            flex: 1;
        }

        .item-info p {
            margin: 7px 0;
            color: #777;
        }

        input[type="number"] {
            width: 75px;
            padding: 9px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        button, .btn {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #292524;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .hapus {
            background: #963e3e;
        }

        .total {
            background: white;
            padding: 25px;
            border-radius: 9px;
            text-align: right;
        }

        .total h2 {
            margin-bottom: 20px;
        }

        .kosong, .pesan {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .pesan {
            color: #8a5b22;
        }

        .kembali {
            display: inline-block;
            margin-top: 20px;
            color: #a47768;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .item {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .item img {
                width: 90px;
                height: 100px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Keranjang Belanja</h1>

    <?php if ($pesan !== ""): ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if (count($items) > 0): ?>

        <form method="POST">

            <?php foreach ($items as $item): ?>
                <div class="item">

                    <?php if (!empty($item["foto"])): ?>
                        <img
                            src="uploads/<?= htmlspecialchars($item["foto"]) ?>"
                            alt="<?= htmlspecialchars($item["nama_produk"]) ?>"
                        >
                    <?php else: ?>
                        <div style="width:110px;height:120px;background:#eee;display:flex;align-items:center;justify-content:center;">
                            No Image
                        </div>
                    <?php endif; ?>

                    <div class="item-info">
                        <h3><?= htmlspecialchars($item["nama_produk"]) ?></h3>

                        <p>
                            Ukuran: <?= htmlspecialchars($item["ukuran"]) ?>
                            | Warna: <?= htmlspecialchars($item["warna"]) ?>
                        </p>

                        <p>
                            Harga: Rp <?= number_format($item["harga"], 0, ",", ".") ?>
                        </p>

                        <p>
                            Stok tersedia: <?= $item["stok"] ?>
                        </p>

                        <label>
                            Jumlah:
                            <input
                                type="number"
                                name="jumlah[<?= $item["variasi_id"] ?>]"
                                value="<?= $item["jumlah"] ?>"
                                min="1"
                                max="<?= $item["stok"] ?>"
                            >
                        </label>

                        <p>
                            Subtotal:
                            <strong>
                                Rp <?= number_format($item["subtotal"], 0, ",", ".") ?>
                            </strong>
                        </p>

                        <button
                            type="submit"
                            name="hapus"
                            value="<?= $item["variasi_id"] ?>"
                            class="hapus"
                            formnovalidate
                        >
                            Hapus
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>

            <button type="submit" name="update">
                Perbarui Keranjang
            </button>

        </form>

        <div class="total">
            <p>Total Barang: <?= $totalBarang ?></p>

            <h2>
                Total: Rp <?= number_format($total, 0, ",", ".") ?>
            </h2>

            <a href="checkout.php" class="btn">
                Lanjut Checkout
            </a>
        </div>

    <?php else: ?>

        <div class="kosong">
            <h3>Keranjang masih kosong</h3>
            <p>Yuk, temukan produk favoritmu di Elsclothyle.</p>
            <br>
            <a href="index.php" class="btn">Mulai Belanja</a>
        </div>

    <?php endif; ?>

    <a href="index.php" class="kembali">← Kembali Belanja</a>

</div>

</body>
</html>
