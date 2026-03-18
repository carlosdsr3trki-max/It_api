<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db.php";

$traker_id = $_GET["traker_id"] ?? null;

if (!$traker_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta traker_id"]);
    exit;
}

try {
$conn = db_conn(); // mysqli

$stmt = $conn->prepare("
    SELECT a.imagen_cloud
    FROM rutas r
    JOIN autos a ON a.id_unidad = r.auto_id
    WHERE r.traker_id = ?
    AND r.auto_id IS NOT NULL
    LIMIT 1
");
$stmt->bind_param("s", $traker_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

    if (!$row || empty($row["imagen_cloud"])) {
        echo json_encode(["status" => "error", "msg" => "Sin imagen"]);
        exit;
    }

    echo json_encode([
        "status"   => "success",
        "foto_url" => $row["imagen_cloud"]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}