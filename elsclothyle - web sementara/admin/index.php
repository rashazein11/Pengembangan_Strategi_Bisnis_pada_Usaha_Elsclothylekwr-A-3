<?php
require_once "auth.php";
require_once "../config.php";

// Menghitung jumlah produk
$queryProduk = $conn->query("SELECT COUNT(*) AS total FROM produk");
$totalProduk = $queryProduk->fetch_assoc()['total'];

// Menghitung total stok
$queryStok = $conn->query("SELECT SUM(stok) AS total FROM variasi_produk");
$totalStok = $queryStok->fetch_assoc()['total'] ?? 0;

// Menghitung jumlah kategori
$queryKategori = $conn->query("SELECT COUNT(DISTINCT kategori) AS total FROM produk");
$totalKategori = $queryKategori->fetch_assoc()['total'];

// Menghitung jumlah pesanan
$queryPesanan = $conn->query("SELECT COUNT(*) AS total FROM pesanan");
$totalPesanan = $queryPesanan->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Elsclothyle</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f7f4f1;
            color: #292524;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: #292524;
            color: white;
            position: fixed;
            padding: 30px 20px;
        }

        .sidebar h2 {
            font-size: 20px;
            margin-bottom: 45px;
            letter-spacing: 2px;
        }

        .sidebar h2 span {
            color: #c49a87;
        }

        .sidebar a {
            display: block;
            padding: 14px;
            margin-bottom: 10px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #a47768;
        }

        .sidebar .logout {
            background: #8b3939;
            margin-top: 30px;
        }

        .sidebar .logout:hover {
            background: #a94444;
        }

        .main {
            margin-left: 240px;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        .topbar p {
            color: #777;
            margin-top: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px #00000008;
        }

        .card p {
            color: #777;
            margin-bottom: 15px;
        }

        .card h2 {
            font-size: 32px;
            color: #a47768;
        }

        .welcome {
            background: #eee5df;
            padding: 30px;
            margin-top: 30px;
            border-radius: 8px;
        }

        .welcome h2 {
            margin-bottom: 12px;
        }

        .welcome p {
            color: #6b625d;
            line-height: 1.7;
        }

        @media (max-width: 1000px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <h2>ELS<span>CLOTHYLE</span></h2>

        <a href="index.php" class="active">Dashboard</a>
        <a href="tambah_produk.php">Tambah Produk</a>
        <a href="daftar_produk.php">Kelola Produk</a>
        <a href="daftar_pesanan.php">Daftar Pesanan</a>
        <a href="../index.php">Lihat Website</a>

        <a href="logout.php" class="logout"
           onclick="return confirm('Yakin ingin logout?')">
            Logout
        </a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Dashboard Admin</h1>
                <p>Selamat datang di halaman pengelolaan Elsclothyle.</p>
            </div>
        </div>

        <div class="cards">

            <div class="card">
                <p>Total Produk</p>
                <h2><?= $totalProduk ?></h2>
            </div>

            <div class="card">
                <p>Total Stok</p>
                <h2><?= $totalStok ?></h2>
            </div>

            <div class="card">
                <p>Total Kategori</p>
                <h2><?= $totalKategori ?></h2>
            </div>

            <div class="card">
                <p>Total Pesanan</p>
                <h2><?= $totalPesanan ?></h2>
            </div>

        </div>

        <div class="welcome">
            <h2>Selamat Datang, Admin!</h2>
            <p>
                Kelola produk, harga, kategori, ukuran, warna,
                stok, dan pesanan fashion Elsclothyle melalui dashboard ini.
            </p>
        </div>

    </main>

</body>
</html>
