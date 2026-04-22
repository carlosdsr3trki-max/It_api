<?php
// file_put_contents("debug.txt", print_r($_POST, true)); // Debug de POST
// file_put_contents("debug_raw.txt", file_get_contents("php://input")); // Debug de JSON

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$servername = getenv("MYSQLHOST");
$username   = getenv("MYSQLUSER");
$password   = getenv("MYSQLPASSWORD");
$dbname     = getenv("MYSQLDATABASE");
$port       = getenv("MYSQLPORT");

$conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
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
// $servername = "localhost";
// $username = "root";
// $password = "root";
// $dbname = "rastreo";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "msg" => "DB Error"]));
}

// 1️⃣ Intentar leer por POST
$id = $_POST["traker_id"] ?? null;
$lat = $_POST["lat"] ?? null;
$lon = $_POST["lon"] ?? null;

// 2️⃣ Si POST viene vacío, intentamos JSON
if (!$id || !$lat || !$lon) {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if ($data) {
$id  = $data["traker_id"] ?? $id;
        $lat = $data["lat"] ?? $lat;
        $lon = $data["lon"] ?? $lon;
    }
}

if (!$id || !$lat || !$lon) {
    echo json_encode([
        "status" => "error",
        "msg" => "Datos faltantes",
        "post" => $_POST,
        "json" => $data ?? null
    ]);
    exit;
}

// UPSERT (insert or update)
$sql = "INSERT INTO ubicaciones (nombre, lat, lon)
        VALUES ('$id', '$lat', '$lon')
        ON DUPLICATE KEY UPDATE 
        lat = VALUES(lat),
        lon = VALUES(lon)";

if ($conn->query($sql) === TRUE) {
    echo json_encode([
        "status" => "success",
        "msg" => "Registro guardado o actualizado",
        "data" => ["id_usuario" => $id, "lat" => $lat, "lon" => $lon]
    ]);
} else {
    echo json_encode(["status" => "error", "msg" => $conn->error]);
}

$conn->close();
?>
