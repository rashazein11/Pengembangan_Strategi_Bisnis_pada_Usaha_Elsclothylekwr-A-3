<?php
require_once "auth.php";
?>
<?php
require_once "../config.php";

$ukuranList = ["S", "M", "L", "XL"];
$pesan = "";

// Ambil ID produk dari URL
$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$produk = $stmt->get_result()->fetch_assoc();

if (!$produk) {
    die("Produk tidak ditemukan.");
}

// Ambil variasi produk
function ambilVariasi($conn, $id) {
    $stmt = $conn->prepare(
        "SELECT warna, ukuran, stok
         FROM variasi_produk
         WHERE produk_id = ?
         ORDER BY warna, ukuran"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $hasil = $stmt->get_result();
    $warnaList = [];

    while ($row = $hasil->fetch_assoc()) {
        $warna = $row["warna"];
        $ukuran = $row["ukuran"];

        if (!isset($warnaList[$warna])) {
            $warnaList[$warna] = [
                "S" => 0,
                "M" => 0,
                "L" => 0,
                "XL" => 0
            ];
        }

        if (in_array($ukuran, ["S", "M", "L", "XL"], true)) {
            $warnaList[$warna][$ukuran] = (int)$row["stok"];
        }
    }

    return $warnaList;
}

$variasiAwal = ambilVariasi($conn, $id);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kode = trim($_POST["kode_produk"] ?? "");
    $nama = trim($_POST["nama_produk"] ?? "");
    $kategori = trim($_POST["kategori"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $harga = $_POST["harga"] ?? "";

    $warnaData = $_POST["warna"] ?? [];
    $stokData = $_POST["stok"] ?? [];

    $foto = $produk["foto"];
    $fotoBaru = "";

    try {
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
                        "Stok warna $warna ukuran $ukuran tidak valid."
                    );
                }
            }
        }

        // Upload foto baru jika dipilih
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

            if (!in_array($ekstensi, ["jpg", "jpeg", "png", "webp"], true)) {
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

        $conn->begin_transaction();

        // Update informasi produk
        $stmtUpdate = $conn->prepare(
            "UPDATE produk
             SET kode_produk = ?, nama_produk = ?, kategori = ?,
                 deskripsi = ?, harga = ?, foto = ?
             WHERE id = ?"
        );

        $harga = (float)$harga;

        $stmtUpdate->bind_param(
            "ssssdsi",
            $kode,
            $nama,
            $kategori,
            $deskripsi,
            $harga,
            $foto,
            $id
        );

        if (!$stmtUpdate->execute()) {
            throw new Exception("Data produk gagal diperbarui.");
        }

        // Hapus variasi lama
        $stmtHapus = $conn->prepare(
            "DELETE FROM variasi_produk WHERE produk_id = ?"
        );

        $stmtHapus->bind_param("i", $id);
        $stmtHapus->execute();

        // Masukkan variasi terbaru
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
                    $id,
                    $ukuran,
                    $warna,
                    $stok
                );

                if (!$stmtVariasi->execute()) {
                    throw new Exception("Variasi produk gagal diperbarui.");
                }
            }
        }

        $conn->commit();

        // Hapus foto lama setelah perubahan berhasil
        if ($fotoBaru !== "" && !empty($produk["foto"])) {
            $fotoLama = "../uploads/" . $produk["foto"];

            if (is_file($fotoLama)) {
                unlink($fotoLama);
            }
        }

        header("Location: daftar_produk.php?status=diubah");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();

        if ($fotoBaru !== "" && is_file($fotoBaru)) {
            unlink($fotoBaru);
        }

        $pesan = $e->getMessage();
    }

    // Tampilkan kembali data yang sudah dikirim jika gagal
    $produk["kode_produk"] = $kode;
    $produk["nama_produk"] = $nama;
    $produk["kategori"] = $kategori;
    $produk["deskripsi"] = $deskripsi;
    $produk["harga"] = $harga;
    $produk["foto"] = $foto;

    $variasiAwal = [];

    foreach ($warnaData as $index => $warna) {
        $warna = trim($warna);

        if ($warna !== "") {
            $variasiAwal[$warna] = [];

            foreach ($ukuranList as $ukuran) {
                $variasiAwal[$warna][$ukuran] =
                    (int)($stokData[$index][$ukuran] ?? 0);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Produk - Elsclothyle</title>

<style>
* { box-sizing: border-box; }

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
}

label {
    display: block;
    margin: 15px 0 7px;
    font-weight: bold;
}

input, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ddd2c7;
    border-radius: 8px;
}

textarea {
    min-height: 100px;
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
}

.stok-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
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
}

.hapus {
    background: #a94442;
}

.tambah {
    margin-top: 18px;
    background: #eee5dc;
    color: #493b32;
}

.simpan {
    width: 100%;
    margin-top: 25px;
    font-size: 16px;
}

.pesan {
    padding: 12px;
    background: #f9e8e5;
    color: #963d34;
    border-radius: 8px;
}

.keterangan {
    color: #81746a;
    font-size: 13px;
}

.foto-lama {
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 8px;
}

@media (max-width: 600px) {
    .stok-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
</head>

<body>

<div class="container">

<h1>Edit Produk</h1>
<p class="keterangan">Ubah informasi produk, warna, dan stok.</p>

<?php if ($pesan !== ""): ?>
    <div class="pesan"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="id" value="<?= $id ?>">

<label>Kode Produk</label>
<input type="text" name="kode_produk"
value="<?= htmlspecialchars($produk["kode_produk"]) ?>" required>

<label>Nama Produk</label>
<input type="text" name="nama_produk"
value="<?= htmlspecialchars($produk["nama_produk"]) ?>" required>

<label>Kategori</label>
<input type="text" name="kategori"
value="<?= htmlspecialchars($produk["kategori"]) ?>" required>

<label>Deskripsi</label>
<textarea name="deskripsi"><?= htmlspecialchars($produk["deskripsi"] ?? "") ?></textarea>

<label>Harga</label>
<input type="number" name="harga" min="0"
value="<?= htmlspecialchars((string)$produk["harga"]) ?>" required>

<label>Foto Produk</label>

<?php if (!empty($produk["foto"])): ?>
    <img class="foto-lama"
         src="../uploads/<?= htmlspecialchars($produk["foto"]) ?>"
         alt="Foto produk">
<?php endif; ?>

<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
<p class="keterangan">Kosongkan jika tidak ingin mengganti foto.</p>

<hr style="margin:25px 0; border:0; border-top:1px solid #eee;">

<h2>Warna dan Stok</h2>
<p class="keterangan">Atur stok untuk setiap ukuran. Kamu juga bisa menambah warna baru.</p>

<div id="daftar-warna">

<?php
$nomor = 0;

foreach ($variasiAwal as $warna => $stokList):
?>

<div class="warna-card">

    <div class="warna-header">
        <h3>Warna <?= $nomor + 1 ?></h3>

        <button type="button" class="tombol hapus">
            Hapus
        </button>
    </div>

    <label>Nama Warna</label>
    <input type="text"
           name="warna[<?= $nomor ?>]"
           value="<?= htmlspecialchars($warna) ?>"
           required>

    <div class="stok-grid">
        <?php foreach ($ukuranList as $ukuran): ?>
            <div>
                <label>Stok <?= $ukuran ?></label>
                <input type="number"
                       name="stok[<?= $nomor ?>][<?= $ukuran ?>]"
                       min="0"
                       value="<?= (int)($stokList[$ukuran] ?? 0) ?>"
                       required>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php
$nomor++;
endforeach;
?>

</div>

<button type="button" class="tombol tambah" id="tambahWarna">
    + Tambah Warna
</button>

<button type="submit" class="tombol simpan">
    Simpan Perubahan
</button>

</form>

<p style="margin-top:20px;">
    <a href="daftar_produk.php" style="color:#725847;">
        ← Kembali ke Daftar Produk
    </a>
</p>

</div>

<script>
let nomorWarna = <?= $nomor ?>;

const daftarWarna = document.getElementById("daftar-warna");

document.getElementById("tambahWarna").addEventListener("click", function () {
    const index = nomorWarna++;

    const kartu = document.createElement("div");
    kartu.className = "warna-card";

    kartu.innerHTML = `
        <div class="warna-header">
            <h3>Warna ${index + 1}</h3>
            <button type="button" class="tombol hapus">Hapus</button>
        </div>

        <label>Nama Warna</label>
        <input type="text" name="warna[${index}]" placeholder="Nama warna" required>

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

daftarWarna.addEventListener("click", function(event) {
    if (event.target.classList.contains("hapus")) {
        event.target.closest(".warna-card").remove();
    }
});
</script>

</body>
</html>
