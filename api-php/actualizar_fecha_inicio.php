<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

date_default_timezone_set('America/Mexico_City');

$servername = getenv("MYSQLHOST");
$username   = getenv("MYSQLUSER");
$password   = getenv("MYSQLPASSWORD");
$dbname     = getenv("MYSQLDATABASE");
$port       = getenv("MYSQLPORT");

$conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "DB fail", "error" => $conn->connect_error]);
    exit;
}

$id_ruta = $_POST["id_ruta"] ?? null;

if (!$id_ruta) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta id_ruta"]);
    exit;
}

$fecha_hora = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO fechas_inicio (id_ruta, fecha_hora)
    VALUES (?, ?)
");

$stmt->bind_param("ss", $id_ruta, $fecha_hora);

if ($stmt->execute()) {
    echo json_encode([
        "status"     => "success",
        "msg"        => "Fecha de inicio registrada",
        "id_ruta"    => $id_ruta,
        "fecha_hora" => $fecha_hora,
        "insert_id"  => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>