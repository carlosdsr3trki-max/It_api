<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

try {
    $conexion = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "DB fail", "error" => $e->getMessage()]);
    exit();
}

$id_ruta   = $_POST['id_ruta']   ?? '';
$traker_id = $_POST['traker_id'] ?? '';

if ($id_ruta === '' || $traker_id === '') {
    echo json_encode(["ok" => false, "msg" => "Faltan parámetros"]);
    exit();
}

$sql = "UPDATE rutas SET STAT_RUT = 'A' WHERE id_ruta = ? AND traker_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("is", $id_ruta, $traker_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["ok" => true, "msg" => "Ruta actualizada"]);
} else {
    echo json_encode(["ok" => false, "msg" => "Sin cambios, verifica id_ruta y traker_id"]);
}

$stmt->close();
$conexion->close();