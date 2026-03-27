<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();

    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes->fetch_assoc();

    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM rutas");
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row["Field"];
    }

    echo json_encode([
        "status" => "ok",
        "db_actual" => $dbRow["db"] ?? null,
        "columnas_rutas" => $cols
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
    exit;
}