<?php
require_once "../config.php";

$username = "admin";
$password = password_hash("kelompok3", PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO admin_users (username, password) VALUES (?, ?)"
);

$stmt->bind_param("ss", $username, $password);

if ($stmt->execute()) {
    echo "Akun admin berhasil dibuat!";
} else {
    echo "Gagal membuat akun: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
