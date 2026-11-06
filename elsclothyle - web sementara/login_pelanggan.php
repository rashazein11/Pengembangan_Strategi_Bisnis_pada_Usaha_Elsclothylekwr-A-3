<?php
session_start();
require_once "config.php";

$error = "";

// Jika sudah login, cek tujuan sebelumnya
if (isset($_SESSION["pelanggan_id"])) {
    $tujuan = $_SESSION["redirect_after_login"] ?? "index.php";
    unset($_SESSION["redirect_after_login"]);

    header("Location: " . $tujuan);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, nama, email, password
         FROM pelanggan
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $pelanggan = $result->fetch_assoc();

    if ($pelanggan && password_verify($password, $pelanggan["password"])) {
        session_regenerate_id(true);

        $_SESSION["pelanggan_id"] = $pelanggan["id"];
        $_SESSION["pelanggan_nama"] = $pelanggan["nama"];
        $_SESSION["pelanggan_email"] = $pelanggan["email"];

        // Kembali ke halaman checkout jika login dari checkout
        $tujuan = $_SESSION["redirect_after_login"] ?? "index.php";
        unset($_SESSION["redirect_after_login"]);

        // Batasi tujuan redirect ke halaman lokal
        if (!in_array($tujuan, ["checkout.php", "index.php"], true)) {
            $tujuan = "index.php";
        }

        header("Location: " . $tujuan);
        exit;
    } else {
        $error = "Email atau password salah!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Elsclothyle</title>

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
            max-width: 400px;
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
            margin-bottom: 18px;
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

        .error {
            background: #ffe5e5;
            color: #b42318;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
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
    <p class="subtitle">Login akun pelanggan</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Email</label>
        <input
            type="email"
            name="email"
            placeholder="Masukkan email"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <div class="link">
        Belum punya akun?
        <a href="register.php">Daftar sekarang</a>

        <br><br>

        <a href="index.php">← Kembali ke Beranda</a>
    </div>

</div>

</body>
</html>
