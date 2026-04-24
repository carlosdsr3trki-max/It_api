<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();

    echo json_encode([
        "status" => "ok",
        "msg" => "Conexión correcta",
        "host" => getenv("MYSQLHOST"),
        "db" => getenv("MYSQLDATABASE")
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "DB fail",
        "error" => $e->getMessage(),
        "host" => getenv("MYSQLHOST"),
        "db" => getenv("MYSQLDATABASE")
    ], JSON_UNESCAPED_UNICODE);
}