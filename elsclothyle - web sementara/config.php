<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "elsclothyle";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>