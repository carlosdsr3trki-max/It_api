<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db.php";

$auto_id = $_GET["auto_id"] ?? null;

if (!$auto_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta auto_id"]);
    exit;
}

try {
    $pdo  = db_conn_pdo();
    $stmt = $pdo->prepare("SELECT imagen_cloud FROM autos WHERE id_unidad = ?");
    $stmt->execute([$auto_id]);
    $row  = $stmt->fetch();

    if (!$row || empty($row["imagen_cloud"])) {
        echo json_encode(["status" => "error", "msg" => "Sin imagen"]);
        exit;
    }

    echo json_encode([
        "status"    => "success",
        "foto_url"  => $row["imagen_cloud"]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}