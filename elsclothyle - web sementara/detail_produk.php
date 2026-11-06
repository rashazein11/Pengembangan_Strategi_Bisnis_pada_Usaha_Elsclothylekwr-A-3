<?php
session_start();
require_once "config.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT * FROM produk WHERE id = ? AND aktif = 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();

$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    die("Produk tidak ditemukan.");
}

$stmtVariasi = $conn->prepare(
    "SELECT * FROM variasi_produk
     WHERE produk_id = ?
     ORDER BY ukuran, warna"
);
$stmtVariasi->bind_param("i", $id);
$stmtVariasi->execute();

$variasi = $stmtVariasi->get_result();

$totalKeranjang = 0;
if (isset($_SESSION["keranjang"])) {
    foreach ($_SESSION["keranjang"] as $jumlah) {
        $totalKeranjang += $jumlah;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produk['nama_produk']) ?> - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            color: #292524;
            background: #fff;
        }

        .navbar {
            padding: 25px 8%;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar a {
            color: #292524;
            text-decoration: none;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .container {
            max-width: 1100px;
            margin: 60px auto;
            padding: 0 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 55px;
        }

        .product-image img {
            width: 100%;
            height: 500px;
            object-fit: cover;
        }

        .no-image {
            height: 500px;
            background: #f2efec;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #888;
        }

        .category {
            color: #a47768;
            margin-bottom: 15px;
        }

        h1 {
            font-size: 34px;
            margin-bottom: 20px;
        }

        .price {
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        .description {
            color: #777;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .variant {
            margin-bottom: 20px;
        }

        .variant h3 {
            font-size: 15px;
            margin-bottom: 12px;
        }

        .variant select,
        .variant input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            background: white;
            margin-bottom: 15px;
        }

        .stock {
            color: #777;
            margin-bottom: 20px;
        }

        .add-cart {
            width: 100%;
            padding: 15px;
            border: none;
            background: #292524;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        .add-cart:hover {
            background: #a47768;
        }

        .add-cart:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .back {
            display: inline-block;
            margin-top: 25px;
            color: #a47768;
            text-decoration: none;
        }

        @media (max-width: 700px) {
            .container {
                grid-template-columns: 1fr;
                margin-top: 30px;
            }

            .product-image img,
            .no-image {
                height: 350px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="index.php">ELS CLOTHYLE</a>
    <a href="keranjang.php">Keranjang (<?= $totalKeranjang ?>)</a>
</header>

<main class="container">

    <div class="product-image">
        <?php if (!empty($produk['foto'])): ?>
            <img
                src="uploads/<?= htmlspecialchars($produk['foto']) ?>"
                alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
            >
        <?php else: ?>
            <div class="no-image">No Image</div>
        <?php endif; ?>
    </div>

    <div class="product-info">

        <p class="category">
            <?= htmlspecialchars($produk['kategori']) ?>
        </p>

        <h1><?= htmlspecialchars($produk['nama_produk']) ?></h1>

        <p class="price">
            Rp <?= number_format($produk['harga'], 0, ',', '.') ?>
        </p>

        <p class="description">
            <?= nl2br(htmlspecialchars($produk['deskripsi'] ?? 'Belum ada deskripsi produk.')) ?>
        </p>

        <form action="tambah_keranjang.php" method="POST">

            <div class="variant">
                <h3>Pilih Ukuran dan Warna</h3>

                <select name="variasi_id" id="pilihanVariasi" required>
                    <option value="">-- Pilih ukuran dan warna --</option>

                    <?php while ($item = $variasi->fetch_assoc()): ?>
                        <option
                            value="<?= $item['id'] ?>"
                            data-stok="<?= $item['stok'] ?>"
                            <?= $item['stok'] <= 0 ? 'disabled' : '' ?>
                        >
                            <?= htmlspecialchars($item['ukuran']) ?>
                            - <?= htmlspecialchars($item['warna']) ?>
                            (Stok: <?= $item['stok'] ?>)
                        </option>
                    <?php endwhile; ?>

                </select>

                <p class="stock" id="infoStok">
                    Silakan pilih variasi produk.
                </p>

                <h3>Jumlah</h3>

                <input
                    type="number"
                    name="jumlah"
                    id="jumlah"
                    value="1"
                    min="1"
                    required
                >
            </div>

            <button
                type="submit"
                class="add-cart"
                id="tombolTambah"
                disabled
            >
                Tambah ke Keranjang
            </button>

        </form>

        <a href="index.php" class="back">← Kembali ke Produk</a>

    </div>

</main>

<script>
    const pilihan = document.getElementById("pilihanVariasi");
    const infoStok = document.getElementById("infoStok");
    const jumlah = document.getElementById("jumlah");
    const tombol = document.getElementById("tombolTambah");

    pilihan.addEventListener("change", function () {
        const opsi = this.options[this.selectedIndex];

        if (!this.value) {
            infoStok.textContent = "Silakan pilih variasi produk.";
            jumlah.max = "";
            tombol.disabled = true;
            return;
        }

        const stok = parseInt(opsi.dataset.stok);

        infoStok.textContent = "Stok tersedia: " + stok;
        jumlah.max = stok;
        jumlah.value = 1;
        tombol.disabled = stok <= 0;
    });

    jumlah.addEventListener("input", function () {
        const stok = parseInt(pilihan.options[pilihan.selectedIndex]?.dataset.stok || 0);

        if (parseInt(this.value) > stok) {
            this.value = stok;
        }

        if (parseInt(this.value) < 1) {
            this.value = 1;
        }
    });
</script>

</body>
</html>
