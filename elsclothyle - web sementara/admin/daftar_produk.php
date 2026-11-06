<?php
require_once "auth.php";
?>
<?php
require_once "../config.php";

$pesan = "";

// Proses hapus produk
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hapus_id"])) {
    $id = (int) $_POST["hapus_id"];

    // Ambil foto produk
    $stmtFoto = $conn->prepare("SELECT foto FROM produk WHERE id = ?");
    $stmtFoto->bind_param("i", $id);
    $stmtFoto->execute();

    $hasilFoto = $stmtFoto->get_result();
    $dataFoto = $hasilFoto->fetch_assoc();

    if ($dataFoto) {
        try {
            $conn->begin_transaction();

            // Hapus variasi produk
            $stmtVariasi = $conn->prepare(
                "DELETE FROM variasi_produk WHERE produk_id = ?"
            );
            $stmtVariasi->bind_param("i", $id);
            $stmtVariasi->execute();

            // Hapus produk
            $stmtProduk = $conn->prepare(
                "DELETE FROM produk WHERE id = ?"
            );
            $stmtProduk->bind_param("i", $id);
            $stmtProduk->execute();

            $conn->commit();

            // Hapus foto produk
            if (!empty($dataFoto["foto"])) {
                $lokasiFoto = "../uploads/" . $dataFoto["foto"];

                if (is_file($lokasiFoto)) {
                    unlink($lokasiFoto);
                }
            }

            header("Location: daftar_produk.php?status=terhapus");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $pesan = "Produk gagal dihapus: " . $e->getMessage();
        }
    } else {
        $pesan = "Produk tidak ditemukan.";
    }
}

// Ambil seluruh produk
$query = $conn->query(
    "SELECT * FROM produk ORDER BY dibuat_pada DESC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Produk - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: #f7f3ed;
            font-family: Arial, sans-serif;
            color: #493b32;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 15px;
        }

        h1 {
            margin-top: 0;
        }

        .tombol {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 7px;
            background: #725847;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .tombol:hover {
            opacity: 0.85;
        }

        .hapus {
            background: #a94442;
        }

        .pesan {
            padding: 12px;
            margin: 15px 0;
            border-radius: 7px;
            background: #f9e8e5;
            color: #963d34;
        }

        .berhasil {
            padding: 12px;
            margin: 15px 0;
            border-radius: 7px;
            background: #e6f3e7;
            color: #326b3b;
        }

        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #eee5dc;
        }

        th {
            background: #f5eee7;
        }

        .foto {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 7px;
        }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #81746a;
        }

        .aksi {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .aksi form {
            margin: 0;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Daftar Produk</h1>
    <p>Kelola, edit, dan hapus produk Elsclothyle.</p>

    <a href="index.php" class="tombol">← Dashboard</a>
    <a href="tambah_produk.php" class="tombol">+ Tambah Produk</a>

    <?php if (isset($_GET["status"]) && $_GET["status"] === "terhapus"): ?>
        <div class="berhasil">
            Produk berhasil dihapus.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["status"]) && $_GET["status"] === "diubah"): ?>
        <div class="berhasil">
            Produk berhasil diperbarui!
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ""): ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Kode</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($query && $query->num_rows > 0): ?>

                <?php while ($produk = $query->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <?php if (!empty($produk["foto"])): ?>
                                <img
                                    class="foto"
                                    src="../uploads/<?= htmlspecialchars($produk["foto"]) ?>"
                                    alt="Foto produk"
                                >
                            <?php else: ?>
                                Tidak ada foto
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($produk["kode_produk"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($produk["nama_produk"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($produk["kategori"]) ?>
                        </td>

                        <td>
                            Rp <?= number_format((float)$produk["harga"], 0, ",", ".") ?>
                        </td>

                        <td>
                            <div class="aksi">

                                <a
                                    href="edit_produk.php?id=<?= (int)$produk['id'] ?>"
                                    class="tombol"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Yakin ingin menghapus produk ini beserta semua variasi dan stoknya?');"
                                >

                                    <input
                                        type="hidden"
                                        name="hapus_id"
                                        value="<?= (int)$produk["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="tombol hapus"
                                    >
                                        Hapus
                                    </button>

                                </form>

                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="kosong">
                        Belum ada produk.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

</body>
</html>
