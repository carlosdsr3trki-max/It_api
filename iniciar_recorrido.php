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
$fecha_hora = date('Y-m-d H:i:s');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg"   => "DB fail",
        "error" => $conn->connect_error
    ]);
    exit;
}

$id_ruta = $_POST["id_ruta"] ?? null;

if (!$id_ruta) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "msg"   => "Falta id_ruta"
    ]);
    exit;
}

// UPDATE ruta
$stmt = $conn->prepare("UPDATE rutas SET STAT_PED = 'C' WHERE id_ruta = ?");
$stmt->bind_param("i", $id_ruta);

if ($stmt->execute()) {

    // INSERT fecha de inicio
$stmt2 = $conn->prepare("INSERT INTO fechas_inicio (id_ruta, fecha_hora) VALUES (?, ?)");
$stmt2->bind_param("is", $id_ruta, $fecha_hora);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode([
        "status"          => "success",
        "msg"             => "Recorrido iniciado correctamente",
        "id_ruta"         => (int)$id_ruta,
        "filas_afectadas" => $stmt->affected_rows
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg"   => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>