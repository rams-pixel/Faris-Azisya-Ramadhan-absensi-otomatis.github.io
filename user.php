<?php

require_once "koneksi.php";

session_start();

/*
|--------------------------------------------------------------------------
| CEK LOGIN / ID SISWA
|--------------------------------------------------------------------------
| Contoh akses: user.php?id=R8N42C
|--------------------------------------------------------------------------
*/

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID siswa tidak ditemukan.");
}

$id = $_GET['id'];

/*
|--------------------------------------------------------------------------
| AMBIL DATA SISWA
|--------------------------------------------------------------------------
*/

$stmtSiswa = mysqli_prepare(
    $koneksi,
    "SELECT id, nomor_absen, nama, nis, kelas
     FROM siswa
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmtSiswa, "s", $id);
mysqli_stmt_execute($stmtSiswa);

$resultSiswa = mysqli_stmt_get_result($stmtSiswa);

if (mysqli_num_rows($resultSiswa) === 0) {
    mysqli_stmt_close($stmtSiswa);
    die("Data siswa tidak ditemukan.");
}

$siswa = mysqli_fetch_assoc($resultSiswa);
mysqli_stmt_close($stmtSiswa);

/*
|--------------------------------------------------------------------------
| DATA KEHADIRAN SISWA
|--------------------------------------------------------------------------
*/

$stmtKehadiran = mysqli_prepare(
    $koneksi,
    "SELECT id, nomor_absen, tanggal, waktu, status
     FROM kehadiran
     WHERE siswa_id = ?
     ORDER BY tanggal DESC, waktu DESC"
);

mysqli_stmt_bind_param($stmtKehadiran, "s", $id);
mysqli_stmt_execute($stmtKehadiran);

$kehadiran = mysqli_stmt_get_result($stmtKehadiran);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User - <?= htmlspecialchars($siswa['nama']); ?></title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 40px auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-bottom: 20px;
        }

        .data-siswa {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .data-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .data-item strong {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .status {
            font-weight: bold;
        }

        .hadir { color: green; }
        .izin { color: #d89b00; }
        .sakit { color: #0077cc; }
        .terlambat { color: #e67e22; }
        .tidak-hadir { color: red; }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #777;
        }

        @media (max-width: 700px) {
            .data-siswa {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- DATA SISWA -->
    <div class="card">
        <h1>Data Siswa</h1>
        <div class="data-siswa">
            <div class="data-item">
                <strong>Nomor Absen</strong>
                <?= htmlspecialchars($siswa['nomor_absen']); ?>
            </div>
            <div class="data-item">
                <strong>ID</strong>
                <?= htmlspecialchars($siswa['id']); ?>
            </div>
            <div class="data-item">
                <strong>Nama</strong>
                <?= htmlspecialchars($siswa['nama']); ?>
            </div>
            <div class="data-item">
                <strong>NIS</strong>
                <?= $siswa['nis'] ? htmlspecialchars($siswa['nis']) : '-'; ?>
            </div>
            <div class="data-item">
                <strong>Kelas</strong>
                <?= htmlspecialchars($siswa['kelas']); ?>
            </div>
        </div>
    </div>

    <!-- RIWAYAT KEHADIRAN -->
    <div class="card">
        <h1>Riwayat Kehadiran</h1>

        <?php if (mysqli_num_rows($kehadiran) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                while ($row = mysqli_fetch_assoc($kehadiran)):
                    $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['tanggal']); ?></td>
                        <td><?= htmlspecialchars($row['waktu']); ?></td>
                        <td class="status <?= htmlspecialchars($statusClass); ?>">
                            <?= htmlspecialchars($row['status']); ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="kosong">
                Belum ada data kehadiran.
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>

<?php

mysqli_stmt_close($stmtKehadiran);
mysqli_close($koneksi);

?>