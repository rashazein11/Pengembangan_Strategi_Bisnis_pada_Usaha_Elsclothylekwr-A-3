<?php
session_start();
require_once "config.php";

$error = "";
$berhasil = "";

if (isset($_SESSION["pelanggan_id"])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi"] ?? "";

    if ($nama === "" || $email === "" || $password === "") {
        $error = "Nama, email, dan password wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        $cek = $conn->prepare(
            "SELECT id FROM pelanggan WHERE email = ? LIMIT 1"
        );
        $cek->bind_param("s", $email);
        $cek->execute();

        $sudahAda = $cek->get_result()->num_rows > 0;
        $cek->close();

        if ($sudahAda) {
            $error = "Email sudah terdaftar. Silakan login.";
        } else {
            $hashPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO pelanggan (nama, email, password, no_hp)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $nama,
                $email,
                $hashPassword,
                $no_hp
            );

            if ($stmt->execute()) {
                $berhasil = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Registrasi gagal. Silakan coba lagi.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f4f1;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 430px;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.07);
        }

        h2 {
            text-align: center;
            letter-spacing: 2px;
            color: #292524;
        }

        .subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 17px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            background: #292524;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #a47768;
        }

        .error, .success {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
        }

        .error {
            background: #ffe5e5;
            color: #b42318;
        }

        .success {
            background: #e5f5e9;
            color: #18733a;
        }

        .link {
            text-align: center;
            margin-top: 20px;
            color: #777;
            font-size: 14px;
        }

        .link a {
            color: #a47768;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>ELSCLOTHYLE</h2>
    <p class="subtitle">Buat akun pelanggan baru</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($berhasil !== ""): ?>
        <div class="success">
            <?= htmlspecialchars($berhasil) ?>
            <br><br>
            <a href="login_pelanggan.php">Login sekarang</a>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Nama Lengkap</label>
        <input type="text" name="nama"
               placeholder="Masukkan nama lengkap"
               value="<?= htmlspecialchars($_POST["nama"] ?? "") ?>"
               required>

        <label>Email</label>
        <input type="email" name="email"
               placeholder="Masukkan email"
               value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
               required>

        <label>Nomor HP</label>
        <input type="text" name="no_hp"
               placeholder="Masukkan nomor HP"
               value="<?= htmlspecialchars($_POST["no_hp"] ?? "") ?>">

        <label>Password</label>
        <input type="password" name="password"
               placeholder="Minimal 8 karakter"
               minlength="8" required>

        <label>Konfirmasi Password</label>
        <input type="password" name="konfirmasi"
               placeholder="Ulangi password"
               required>

        <button type="submit">Daftar Sekarang</button>

    </form>

    <div class="link">
        Sudah punya akun?
        <a href="login_pelanggan.php">Login</a>
        <br><br>
        <a href="index.php">← Kembali ke Beranda</a>
    </div>

</div>

</body>
</html>
