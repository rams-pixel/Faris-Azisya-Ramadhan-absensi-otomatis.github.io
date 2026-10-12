<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once "koneksi.php";

/*
|--------------------------------------------------------------------------
| BUAT TABEL OTOMATIS JIKA BELUM ADA
|--------------------------------------------------------------------------
*/

$sqlTable = "
CREATE TABLE IF NOT EXISTS kehadiran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id VARCHAR(50) NOT NULL,
    nomor_absen INT NOT NULL,
    tanggal DATE NOT NULL,
    waktu TIME NOT NULL,
    status ENUM('Hadir', 'Tidak Hadir', 'Izin', 'Sakit', 'Terlambat') NOT NULL,
    CONSTRAINT fk_kehadiran_siswa
        FOREIGN KEY (siswa_id)
        REFERENCES siswa(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    UNIQUE KEY satu_absen_per_hari (siswa_id, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!mysqli_query($koneksi, $sqlTable)) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengeksekusi struktur tabel: " . mysqli_error($koneksi)
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLER PREFLIGHT (OPTIONS)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD POST (Dipakai index.html untuk mencatat presensi)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $input = file_get_contents("php://input");
    $data  = json_decode($input, true);

    if (!$data) {
        echo json_encode([
            "success" => false,
            "message" => "Data JSON tidak valid"
        ]);
        exit;
    }

    $absen   = trim((string) ($data["absen"] ?? ""));
    $tanggal = trim($data["tanggal"] ?? "");
    $jam     = trim($data["jam"] ?? "");
    $status  = trim($data["status"] ?? "");

    // Validasi Format Input
    if (
        !ctype_digit($absen) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ||
        !preg_match('/^\d{2}:\d{2}:\d{2}$/', $jam) ||
        !in_array($status, ["Hadir", "Terlambat", "Izin", "Sakit", "Tidak Hadir"], true)
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Format data presensi tidak valid / belum lengkap"
        ]);
        exit;
    }

    // Ambil siswa_id berdasarkan nomor_absen
    $stmtSiswa = mysqli_prepare($koneksi, "SELECT id FROM siswa WHERE nomor_absen = ? LIMIT 1");
    $nomorAbsen = (int) $absen;
    mysqli_stmt_bind_param($stmtSiswa, "i", $nomorAbsen);
    mysqli_stmt_execute($stmtSiswa);
    $hasilSiswa = mysqli_stmt_get_result($stmtSiswa);

    if (mysqli_num_rows($hasilSiswa) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Nomor absen tidak ditemukan di data siswa"
        ]);
        mysqli_stmt_close($stmtSiswa);
        exit;
    }

    $rowSiswa = mysqli_fetch_assoc($hasilSiswa);
    $siswaId  = $rowSiswa["id"];
    mysqli_stmt_close($stmtSiswa);

    // Cek apakah sudah presensi pada hari yang sama
    $stmtCek = mysqli_prepare($koneksi, "SELECT id FROM kehadiran WHERE siswa_id = ? AND tanggal = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtCek, "ss", $siswaId, $tanggal);
    mysqli_stmt_execute($stmtCek);
    $hasilCek = mysqli_stmt_get_result($stmtCek);

    if (mysqli_num_rows($hasilCek) > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Siswa dengan nomor absen " . $absen . " sudah melakukan presensi hari ini"
        ]);
        mysqli_stmt_close($stmtCek);
        exit;
    }
    mysqli_stmt_close($stmtCek);

    // Insert Data Presensi
    $stmtInsert = mysqli_prepare($koneksi, "INSERT INTO kehadiran (siswa_id, nomor_absen, tanggal, waktu, status) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmtInsert, "sisss", $siswaId, $nomorAbsen, $tanggal, $jam, $status);

    if (mysqli_stmt_execute($stmtInsert)) {
        echo json_encode([
            "success" => true,
            "message" => "Presensi berhasil disimpan",
            "id"      => mysqli_stmt_insert_id($stmtInsert)
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Gagal menyimpan presensi: " . mysqli_stmt_error($stmtInsert)
        ]);
    }

    mysqli_stmt_close($stmtInsert);
    mysqli_close($koneksi);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD GET (Dipakai admin.html untuk membaca seluruh data)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $sql = "
        SELECT
            kehadiran.id,
            kehadiran.siswa_id,
            kehadiran.nomor_absen AS absen,
            siswa.nama,
            siswa.nis,
            siswa.kelas,
            kehadiran.tanggal,
            kehadiran.waktu AS jam,
            kehadiran.status
        FROM kehadiran
        INNER JOIN siswa ON siswa.id = kehadiran.siswa_id
        ORDER BY kehadiran.tanggal DESC, kehadiran.waktu DESC
    ";

    $result = mysqli_query($koneksi, $sql);

    if (!$result) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal mengambil data: " . mysqli_error($koneksi)
        ]);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data"    => $data
    ], JSON_UNESCAPED_UNICODE);

    mysqli_close($koneksi);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD UNKNOWN
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => false,
    "message" => "Method HTTP tidak didukung"
]);

mysqli_close($koneksi);
exit;

?>