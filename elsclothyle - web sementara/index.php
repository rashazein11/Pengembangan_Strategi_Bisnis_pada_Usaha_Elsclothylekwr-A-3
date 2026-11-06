<?php
session_start();
require_once "config.php";

$query = "SELECT * FROM produk WHERE aktif = 1 ORDER BY dibuat_pada DESC";
$result = $conn->query($query);

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
    <title>Elsclothyle | Fashion Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="navbar">
        <a href="index.php" class="logo">ELS<span>CLOTHYLE</span></a>

        <nav>
            <a href="index.php">Beranda</a>
            <a href="#produk">Produk</a>
            <a href="#tentang">Tentang Kami</a>
        </nav>

        <a href="keranjang.php" class="cart">
            Keranjang (<?= $totalKeranjang ?>)
        </a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <p class="subtitle">NEW COLLECTION</p>
            <h1>Discover Your<br>Own Style.</h1>
            <p>
                Temukan pilihan fashion terbaik untuk
                melengkapi gaya sehari-harimu.
            </p>
            <a href="#produk" class="btn">Belanja Sekarang</a>
        </div>
    </section>

    <section class="products" id="produk">
        <div class="section-heading">
            <p class="subtitle">OUR COLLECTION</p>
            <h2>Produk Terbaru</h2>
            <p>Pilihan fashion terbaik dari Elsclothyle.</p>
        </div>

        <div class="product-list">
            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($produk = $result->fetch_assoc()): ?>
                    <div class="product-card">

                        <?php if (!empty($produk['foto'])): ?>
                            <img
                                src="uploads/<?= htmlspecialchars($produk['foto']) ?>"
                                alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                            >
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>

                        <div class="product-info">
                            <p class="category">
                                <?= htmlspecialchars($produk['kategori']) ?>
                            </p>

                            <h3>
                                <?= htmlspecialchars($produk['nama_produk']) ?>
                            </h3>

                            <p class="price">
                                Rp <?= number_format($produk['harga'], 0, ',', '.') ?>
                            </p>

                            <a
                                href="detail_produk.php?id=<?= $produk['id'] ?>"
                                class="detail-btn"
                            >
                                Lihat Detail
                            </a>
                        </div>

                    </div>
                <?php endwhile; ?>

            <?php else: ?>
                <p class="empty-product">
                    Belum ada produk. Produk akan segera hadir!
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="about" id="tentang">
        <h2>About Elsclothyle</h2>
        <p>
            Elsclothyle hadir untuk memberikan pilihan fashion
            yang stylish dan nyaman untuk setiap kesempatan.
        </p>
    </section>

    <footer>
        <p>&copy; <?= date('Y') ?> Elsclothyle. All Rights Reserved.</p>
    </footer>

</body>
</html>
