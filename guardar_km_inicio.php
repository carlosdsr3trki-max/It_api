<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "DB fail", "error" => $e->getMessage()]);
    exit;
}

$id_ruta   = $_POST["id_ruta"]   ?? null;
$km_inicio = $_POST["km_inicio"] ?? null;
$auto_id   = $_POST["auto_id"]   ?? null; // ✅ nuevo

if (!$id_ruta || $km_inicio === null || $km_inicio === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan datos"]);
    exit;
}

try {
    $check = $conn->prepare("SELECT id FROM kilometros WHERE id_ruta = ? LIMIT 1");
    $check->bind_param("i", $id_ruta);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if ($row) {
        $stmt = $conn->prepare("
            UPDATE kilometraje
            SET km_inicio = ?, auto_id = ?
            WHERE id_ruta = ?
        ");
        $stmt->bind_param("dsi", $km_inicio, $auto_id, $id_ruta); // ✅
        $stmt->execute();
        $stmt->close();

        echo json_encode(["status" => "ok", "msg" => "Kilometraje actualizado"]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO kilometraje (id_ruta, km_inicio, auto_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("ids", $id_ruta, $km_inicio, $auto_id); // ✅
        $stmt->execute();
        $stmt->close();

        echo json_encode(["status" => "ok", "msg" => "Kilometraje guardado"]);
    }

    $conn->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}