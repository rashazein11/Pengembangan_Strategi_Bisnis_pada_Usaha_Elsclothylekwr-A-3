<?php
session_start();
require_once "../config.php";

$error = "";

if (isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, username, password FROM admin_users WHERE username = ? LIMIT 1"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin["password"])) {
        session_regenerate_id(true);

        $_SESSION["admin_id"] = $admin["id"];
        $_SESSION["admin_username"] = $admin["username"];

        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f1ed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            width: 90%;
            max-width: 400px;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 8px;
            color: #292321;
        }

        .subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 13px;
            background: #292321;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #51443d;
        }

        .error {
            background: #ffe5e5;
            color: #b42318;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #777;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>

<body>

<div class="login-container">

    <h2>ELSCLOTHYLE</h2>
    <p class="subtitle">Login Administrator</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label>
        <input
            type="text"
            name="username"
            placeholder="Masukkan username"
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

    <a href="../index.php" class="back">← Kembali ke Beranda</a>

</div>

</body>
</html>
