<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// 1. KONFIGURASI KONEKSI DATABASE (Disesuaikan dengan database.sql)
$host = "localhost";
$user = "root";              // Sesuaikan dengan username MySQL Anda
$pass = "";                  // Sesuaikan dengan password MySQL Anda
$db   = "absensi_sekolah";   // Nama database dari file database.sql

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Koneksi database gagal: " . $conn->connect_error
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/* =========================================================================
 * 1. METHOD GET: Ambil Data Kehadiran (Untuk admin.html)
 * ========================================================================= */
if ($method === 'GET') {
    // Join dengan tabel siswa untuk mendapatkan informasi lengkap
    $sql = "SELECT k.id, k.siswa_id, k.nomor_absen, k.tanggal, k.waktu, k.status, s.nama, s.kelas, s.nis 
            FROM kehadiran k
            JOIN siswa s ON k.siswa_id = s.id
            ORDER BY k.tanggal DESC, k.waktu DESC";
            
    $result = $conn->query($sql);

    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                "id"          => $row['id'],
                "siswa_id"    => $row['siswa_id'],
                "absen"       => (int)$row['nomor_absen'],
                "tanggal"     => $row['tanggal'],
                "jam"         => $row['waktu'], // Alias 'waktu' ke 'jam'
                "status"      => $row['status'],
                "nama"        => $row['nama'],
                "kelas"       => $row['kelas'],
                "nis"         => $row['nis']
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "data"    => $data
    ]);
    exit;
}

/* =========================================================================
 * 2. METHOD POST: Simpan/Update Kehadiran (Untuk index.html / tap siswa)
 * ========================================================================= */
if ($method === 'POST') {
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);

    // Validasi data input dari frontend
    if ((!isset($input['siswa_id']) && !isset($input['absen'])) || !isset($input['tanggal']) || !isset($input['jam']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Data input tidak lengkap."
        ]);
        exit;
    }

    $tanggal = $conn->real_escape_string($input['tanggal']);
    $waktu   = $conn->real_escape_string($input['jam']); // Dikirim sebagai 'jam' oleh JS
    $status  = $conn->real_escape_string($input['status']);

    // Cari data siswa berdasarkan ID siswa atau nomor_absen
    if (isset($input['siswa_id']) && !empty($input['siswa_id'])) {
        $siswa_id = $conn->real_escape_string($input['siswa_id']);
        $getSiswa = $conn->query("SELECT id, nomor_absen FROM siswa WHERE id = '$siswa_id'");
    } else {
        $absen = (int)$input['absen'];
        $getSiswa = $conn->query("SELECT id, nomor_absen FROM siswa WHERE nomor_absen = $absen");
    }

    if (!$getSiswa || $getSiswa->num_num_rows === 0 && $getSiswa->num_rows === 0) {
        http_response_code(444);
        echo json_encode([
            "success" => false,
            "message" => "Data siswa tidak ditemukan di database."
        ]);
        exit;
    }

    $dataSiswa   = $getSiswa->fetch_assoc();
    $siswa_id    = $dataSiswa['id'];
    $nomor_absen = (int)$dataSiswa['nomor_absen'];

    // Query INSERT ... ON DUPLICATE KEY UPDATE sesuai dengan UNIQUE KEY (siswa_id, tanggal) di database.sql
    $sql = "INSERT INTO kehadiran (siswa_id, nomor_absen, tanggal, waktu, status) 
            VALUES ('$siswa_id', $nomor_absen, '$tanggal', '$waktu', '$status')
            ON DUPLICATE KEY UPDATE waktu = VALUES(waktu), status = VALUES(status)";

    if ($conn->query($sql)) {
        echo json_encode([
            "success" => true,
            "message" => "Presensi berhasil dicatat."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menyimpan ke database: " . $conn->error
        ]);
    }
    exit;
}

$conn->close();
?>