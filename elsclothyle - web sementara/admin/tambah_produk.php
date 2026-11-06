<?php
require_once "auth.php";
?>
<?php
require_once "../config.php";

$pesan = "";
$ukuranList = ["S", "M", "L", "XL"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kode = trim($_POST["kode_produk"] ?? "");
    $nama = trim($_POST["nama_produk"] ?? "");
    $kategori = trim($_POST["kategori"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $harga = $_POST["harga"] ?? "";

    $warnaData = $_POST["warna"] ?? [];
    $stokData = $_POST["stok"] ?? [];

    $foto = "";
    $fotoBaru = "";

    try {
        // Validasi data produk
        if (
            $kode === "" ||
            $nama === "" ||
            $kategori === "" ||
            $harga === "" ||
            !is_numeric($harga) ||
            (float)$harga < 0
        ) {
            throw new Exception("Lengkapi data produk dengan benar.");
        }

        if (!is_array($warnaData) || count($warnaData) === 0) {
            throw new Exception("Tambahkan minimal satu warna.");
        }

        // Validasi nama warna dan stok
        $warnaValid = [];
        $warnaUnik = [];

        foreach ($warnaData as $index => $warna) {
            $warna = trim($warna);

            if ($warna === "") {
                throw new Exception("Nama warna tidak boleh kosong.");
            }

            $warnaKey = mb_strtolower($warna);

            if (in_array($warnaKey, $warnaUnik, true)) {
                throw new Exception("Nama warna tidak boleh sama.");
            }

            $warnaUnik[] = $warnaKey;
            $warnaValid[$index] = $warna;

            foreach ($ukuranList as $ukuran) {
                $stok = $stokData[$index][$ukuran] ?? null;

                if (
                    $stok === null ||
                    filter_var($stok, FILTER_VALIDATE_INT) === false ||
                    (int)$stok < 0
                ) {
                    throw new Exception(
                        "Stok warna " . $warna . " ukuran " . $ukuran .
                        " harus berupa angka 0 atau lebih."
                    );
                }
            }
        }

        // Upload foto jika dipilih
        if (
            isset($_FILES["foto"]) &&
            $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
                throw new Exception("Foto gagal diunggah.");
            }

            if ($_FILES["foto"]["size"] > 2 * 1024 * 1024) {
                throw new Exception("Ukuran foto maksimal 2 MB.");
            }

            $ekstensi = strtolower(
                pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION)
            );

            $ekstensiDiizinkan = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($ekstensi, $ekstensiDiizinkan, true)) {
                throw new Exception("Format foto harus JPG, JPEG, PNG, atau WEBP.");
            }

            $folderUpload = "../uploads/";

            if (!is_dir($folderUpload)) {
                mkdir($folderUpload, 0777, true);
            }

            $namaFoto = uniqid("produk_", true) . "." . $ekstensi;
            $tujuanFoto = $folderUpload . $namaFoto;

            if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $tujuanFoto)) {
                throw new Exception("Foto tidak berhasil disimpan.");
            }

            $foto = $namaFoto;
            $fotoBaru = $tujuanFoto;
        }

        // Simpan produk dan variasinya sekaligus
        $conn->begin_transaction();

        $stmtProduk = $conn->prepare(
            "INSERT INTO produk
            (kode_produk, nama_produk, kategori, deskripsi, harga, foto, aktif)
            VALUES (?, ?, ?, ?, ?, ?, 1)"
        );

        $harga = (float)$harga;

        $stmtProduk->bind_param(
            "ssssds",
            $kode,
            $nama,
            $kategori,
            $deskripsi,
            $harga,
            $foto
        );

        if (!$stmtProduk->execute()) {
            throw new Exception("Produk gagal disimpan. Periksa kode produk.");
        }

        $produkId = $conn->insert_id;

        $stmtVariasi = $conn->prepare(
            "INSERT INTO variasi_produk
            (produk_id, ukuran, warna, stok)
            VALUES (?, ?, ?, ?)"
        );

        foreach ($warnaValid as $index => $warna) {
            foreach ($ukuranList as $ukuran) {
                $stok = (int)$stokData[$index][$ukuran];

                $stmtVariasi->bind_param(
                    "issi",
                    $produkId,
                    $ukuran,
                    $warna,
                    $stok
                );

                if (!$stmtVariasi->execute()) {
                    throw new Exception("Variasi produk gagal disimpan.");
                }
            }
        }

        $conn->commit();

        header("Location: tambah_produk.php?status=berhasil");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();

        if ($fotoBaru !== "" && file_exists($fotoBaru)) {
            unlink($fotoBaru);
        }

        $pesan = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk - Elsclothyle</title>

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
            max-width: 850px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        h1 {
            margin-top: 0;
            color: #493b32;
        }

        .deskripsi {
            color: #81746a;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd2c7;
            border-radius: 8px;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .warna-card {
            padding: 18px;
            margin-top: 18px;
            border: 1px solid #e5dcd2;
            border-radius: 12px;
            background: #fdfbf8;
        }

        .warna-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .warna-header h3 {
            margin: 0;
        }

        .stok-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .stok-grid label {
            font-size: 13px;
        }

        .tombol {
            display: inline-block;
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            background: #725847;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .tombol:hover {
            background: #594434;
        }

        .hapus {
            background: #a94c43;
        }

        .hapus:hover {
            background: #873a33;
        }

        .tambah-warna {
            margin-top: 18px;
            background: #eee5dc;
            color: #493b32;
        }

        .simpan {
            margin-top: 25px;
            width: 100%;
            padding: 14px;
            font-size: 16px;
        }

        .pesan {
            padding: 12px;
            margin-bottom: 15px;
            background: #f9e8e5;
            color: #963d34;
            border-radius: 8px;
        }

        .berhasil {
            padding: 12px;
            margin-bottom: 15px;
            background: #e6f3e7;
            color: #326b3b;
            border-radius: 8px;
        }

        .keterangan {
            font-size: 13px;
            color: #81746a;
            margin-top: 7px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            .stok-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Tambah Produk</h1>
    <p class="deskripsi">
        Masukkan informasi produk dan atur warna serta stok setiap ukuran.
    </p>

    <?php if ($pesan !== ""): ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["status"]) && $_GET["status"] === "berhasil"): ?>
        <div class="berhasil">
            Produk berhasil ditambahkan!
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Kode Produk</label>
        <input type="text" name="kode_produk" placeholder="Contoh: ELS003" required>

        <label>Nama Produk</label>
        <input type="text" name="nama_produk" placeholder="Nama produk" required>

        <label>Kategori</label>
        <input type="text" name="kategori" placeholder="Contoh: Cardigan" required>

        <label>Deskripsi Produk</label>
        <textarea name="deskripsi" placeholder="Deskripsi produk..."></textarea>

        <label>Harga Produk</label>
        <input type="number" name="harga" min="0" step="1" placeholder="Contoh: 150000" required>

        <label>Foto Produk</label>
        <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
        <p class="keterangan">Format JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB.</p>

        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

        <h2>Warna dan Stok Produk</h2>
        <p class="keterangan">
            Isi sendiri nama warnanya. Stok setiap ukuran bisa berbeda.
        </p>

        <div id="daftar-warna">

            <div class="warna-card">
                <div class="warna-header">
                    <h3>Warna 1</h3>
                </div>

                <label>Nama Warna</label>
                <input
                    type="text"
                    name="warna[0]"
                    placeholder="Ketik nama warna sendiri"
                    required
                >

                <div class="stok-grid">
                    <?php foreach ($ukuranList as $ukuran): ?>
                        <div>
                            <label>Stok <?= $ukuran ?></label>
                            <input
                                type="number"
                                name="stok[0][<?= $ukuran ?>]"
                                min="0"
                                value="0"
                                required
                            >
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <button type="button" class="tombol tambah-warna" id="tambahWarna">
            + Tambah Warna
        </button>

        <button type="submit" class="tombol simpan">
            Simpan Produk
        </button>

    </form>

    <p style="margin-top: 20px;">
        <a href="index.php" style="color: #725847;">← Kembali ke Dashboard</a>
    </p>

</div>

<script>
    let nomorWarna = 1;

    const daftarWarna = document.getElementById("daftar-warna");
    const tombolTambah = document.getElementById("tambahWarna");

    tombolTambah.addEventListener("click", function () {
        const index = nomorWarna++;

        const kartu = document.createElement("div");
        kartu.className = "warna-card";

        kartu.innerHTML = `
            <div class="warna-header">
                <h3>Warna ${index + 1}</h3>
                <button type="button" class="tombol hapus">Hapus</button>
            </div>

            <label>Nama Warna</label>
            <input
                type="text"
                name="warna[${index}]"
                placeholder="Ketik nama warna sendiri"
                required
            >

            <div class="stok-grid">
                <div>
                    <label>Stok S</label>
                    <input type="number" name="stok[${index}][S]" min="0" value="0" required>
                </div>

                <div>
                    <label>Stok M</label>
                    <input type="number" name="stok[${index}][M]" min="0" value="0" required>
                </div>

                <div>
                    <label>Stok L</label>
                    <input type="number" name="stok[${index}][L]" min="0" value="0" required>
                </div>

                <div>
                    <label>Stok XL</label>
                    <input type="number" name="stok[${index}][XL]" min="0" value="0" required>
                </div>
            </div>
        `;

        daftarWarna.appendChild(kartu);
    });

    daftarWarna.addEventListener("click", function (event) {
        if (event.target.classList.contains("hapus")) {
            event.target.closest(".warna-card").remove();
        }
    });
</script>

</body>
</html>
