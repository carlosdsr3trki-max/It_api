<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "DB fail",
        "error" => $e->getMessage()
    ]);
    exit;
}

$id_ruta   = $_POST["id_ruta"] ?? null;
$km_inicio = $_POST["km_inicio"] ?? null;

if (!$id_ruta || $km_inicio === null || $km_inicio === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "msg" => "Faltan datos"
    ]);
    exit;
}

try {
    // revisa si ya existe
    $check = $conn->prepare("SELECT id FROM kilometraje WHERE id_ruta = ? LIMIT 1");
    $check->bind_param("i", $id_ruta);
    $check->execute();
    $res = $check->get_result();
    $row = $res->fetch_assoc();
    $check->close();

    if ($row) {
        $stmt = $conn->prepare("
            UPDATE kilometraje
            SET km_inicio = ?
            WHERE id_ruta = ?
        ");
        $stmt->bind_param("di", $km_inicio, $id_ruta);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            "status" => "ok",
            "msg" => "Kilometraje actualizado"
        ]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO kilometraje (id_ruta, km_inicio, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("id", $id_ruta, $km_inicio);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            "status" => "ok",
            "msg" => "Kilometraje guardado"
        ]);
    }

    $conn->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}