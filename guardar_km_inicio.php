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
$km_fin    = $_POST["km_fin"]    ?? null;
$auto_id   = $_POST["auto_id"]   ?? null;

if (!$id_ruta) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta id_ruta"]);
    exit;
}

try {

    // ── Flujo km_fin ─────────────────────────────────────────
    if ($km_fin !== null && $km_fin !== "") {

        $km_fin_val = floatval($km_fin); // ← convierte string a número correctamente

        $stmt = $conn->prepare("UPDATE kilometraje SET km_fin = ? WHERE id_ruta = ?");

        if (!$stmt) {
            echo json_encode(["status" => "error", "msg" => "Prepare failed: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("di", $km_fin_val, $id_ruta);

        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "msg" => "Execute failed: " . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }

        // ← CRÍTICO: verifica que realmente actualizó una fila
        if ($stmt->affected_rows === 0) {
            echo json_encode(["status" => "error", "msg" => "No se encontró id_ruta=$id_ruta en kilometraje"]);
            $stmt->close();
            $conn->close();
            exit;
        }

        $stmt->close();
        $conn->close();
        echo json_encode(["status" => "ok", "msg" => "km_fin guardado"]);
        exit;
    }

    // ── Flujo km_inicio ──────────────────────────────────────
    if ($km_inicio === null || $km_inicio === "") {
        http_response_code(400);
        echo json_encode(["status" => "error", "msg" => "Faltan datos"]);
        exit;
    }

    $km_inicio_val = floatval($km_inicio); // ← igual aquí

    $check = $conn->prepare("SELECT id FROM kilometraje WHERE id_ruta = ? LIMIT 1");
    $check->bind_param("i", $id_ruta);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if ($row) {
        $stmt = $conn->prepare("UPDATE kilometraje SET km_inicio = ?, auto_id = ? WHERE id_ruta = ?");
        $stmt->bind_param("dsi", $km_inicio_val, $auto_id, $id_ruta);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["status" => "ok", "msg" => "Kilometraje actualizado"]);
    } else {
        $stmt = $conn->prepare("INSERT INTO kilometraje (id_ruta, km_inicio, auto_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ids", $id_ruta, $km_inicio_val, $auto_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["status" => "ok", "msg" => "Kilometraje guardado"]);
    }

    $conn->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}