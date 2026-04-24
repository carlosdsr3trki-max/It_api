<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$servername = getenv("MYSQLHOST");
$username   = getenv("MYSQLUSER");
$password   = getenv("MYSQLPASSWORD");
$dbname     = getenv("MYSQLDATABASE");
$port       = (int)(getenv("MYSQLPORT") ?: 3306);

if (!$servername || !$username || !$dbname) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "DB env missing"
    ]);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname, $port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "DB fail",
        "error" => $conn->connect_error
    ]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$ubicaciones = $data["ubicaciones"] ?? [];

if (!is_array($ubicaciones) || count($ubicaciones) === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "msg" => "No llegaron ubicaciones"
    ]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO ubicaciones (nombre, lat, lon)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        lat = VALUES(lat),
        lon = VALUES(lon)
");

$guardadas = 0;

foreach ($ubicaciones as $u) {
    $traker_id = $u["traker_id"] ?? null;
    $lat       = $u["lat"] ?? null;
    $lon       = $u["lon"] ?? null;

    if (!$traker_id || !$lat || !$lon) {
        continue;
    }

    $stmt->bind_param("sss", $traker_id, $lat, $lon);

    if ($stmt->execute()) {
        $guardadas++;
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "msg" => "Batch procesado",
    "recibidas" => count($ubicaciones),
    "guardadas" => $guardadas
], JSON_UNESCAPED_UNICODE);