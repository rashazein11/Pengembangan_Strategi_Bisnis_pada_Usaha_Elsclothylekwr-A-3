<?php
require_once "auth.php";
require_once "../config.php";

$query = "
    SELECT
        p.id,
        p.total_harga,
        p.metode_pembayaran,
        p.status,
        p.dibuat_pada,
        pl.nama,
        pl.email,
        COUNT(dp.id) AS jumlah_item
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id
    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    GROUP BY
        p.id,
        p.total_harga,
        p.metode_pembayaran,
        p.status,
        p.dibuat_pada,
        pl.nama,
        pl.email
    ORDER BY p.dibuat_pada DESC
";

$result = $conn->query($query);

function rupiah($angka) {
    return "Rp" . number_format((float)$angka, 0, ",", ".");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - Elsclothyle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f5f2;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 35px auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            font-size: 27px;
        }

        .back {
            color: #9b705c;
            text-decoration: none;
            font-weight: bold;
        }

        .table-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #f7f5f2;
            color: #755541;
        }

        .status {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .menunggu {
            background: #fff0cf;
            color: #946600;
        }

        .dibayar {
            background: #dff4e3;
            color: #26743b;
        }

        .lainnya {
            background: #eee;
            color: #555;
        }

        .kosong {
            text-align: center;
            padding: 35px;
            color: #888;
        }

        @media (max-width: 600px) {
            .container {
                padding: 12px;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Daftar Pesanan</h1>
        <a href="index.php" class="back">← Kembali ke Dashboard</a>
    </div>

    <div class="table-box">

        <table>
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Jumlah Item</th>
                    <th>Total Harga</th>
                    <th>Metode Pembayaran</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($pesanan = $result->fetch_assoc()): ?>

                    <?php
                    $statusClass = "lainnya";

                    if ($pesanan["status"] === "Menunggu Pembayaran") {
                        $statusClass = "menunggu";
                    } elseif ($pesanan["status"] === "Sudah Dibayar") {
                        $statusClass = "dibayar";
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>#<?= (int)$pesanan["id"]; ?></strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($pesanan["nama"] ?? "Pelanggan"); ?>
                            <br>
                            <small>
                                <?= htmlspecialchars($pesanan["email"] ?? "-"); ?>
                            </small>
                        </td>

                        <td>
                            <?= date("d-m-Y H:i", strtotime($pesanan["dibuat_pada"])); ?>
                        </td>

                        <td>
                            <?= (int)$pesanan["jumlah_item"]; ?> item
                        </td>

                        <td>
                            <?= rupiah($pesanan["total_harga"]); ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($pesanan["metode_pembayaran"] ?? "-"); ?>
                        </td>

                        <td>
                            <span class="status <?= $statusClass; ?>">
                                <?= htmlspecialchars($pesanan["status"]); ?>
                            </span>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="kosong">
                        Belum ada pesanan.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>
