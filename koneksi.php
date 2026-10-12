<?php

$host     = "localhost";
$user     = "root";
$password = "";
$database = "absensi_sekolah";

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Koneksi database gagal: " . mysqli_connect_error()
    ]);
    exit;
}

mysqli_set_charset($koneksi, "utf8mb4");

?>