<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$CLOUD_NAME = getenv("CLOUD_NAME");
$API_KEY    = getenv("CLOUDINARY_API_KEY");
$API_SECRET = getenv("CLOUDINARY_API_SECRET");

require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "DB fail", "error" => $e->getMessage()]);
    exit;
}

$id_ruta       = $_POST["id_ruta"]       ?? null;
$id_unidad     = $_POST["id_unidad"]     ?? null;
$tipo          = $_POST["tipo"]          ?? null;
$estado_tanque = $_POST["estado_tanque"] ?? null;
$gas_rut       = $_POST["gas_rut"]       ?? "";

if (!$id_ruta || !$id_unidad || !$tipo) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan parámetros: id_ruta, id_unidad, tipo"]);
    exit;
}

if (!in_array($tipo, ["inicio", "fin"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "tipo debe ser 'inicio' o 'fin'"]);
    exit;
}

if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta foto o error al subir"]);
    exit;
}

$allowed = ["image/jpeg", "image/png", "image/webp"];
$mime    = mime_content_type($_FILES["foto"]["tmp_name"]);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Formato no permitido"]);
    exit;
}

// ─── Subir a Cloudinary ────────────────────────────────────────────────────
$timestamp = time();
$public_id = "gasolina/ruta_" . intval($id_ruta) . "_unidad_" . intval($id_unidad) . "_" . $tipo . "_" . date("Ymd_His");
$signature = sha1("public_id=" . $public_id . "&timestamp=" . $timestamp . $API_SECRET);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://api.cloudinary.com/v1_1/$CLOUD_NAME/image/upload",
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => [
        "file"      => new CURLFile($_FILES["foto"]["tmp_name"], $mime, $_FILES["foto"]["name"]),
        "api_key"   => $API_KEY,
        "timestamp" => $timestamp,
        "public_id" => $public_id,
        "signature" => $signature,
    ]
]);

$response   = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Error Cloudinary", "error" => $curl_error]);
    exit;
}

$cloudinary = json_decode($response, true);

if (!isset($cloudinary["secure_url"])) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Cloudinary no devolvió URL", "response" => $cloudinary]);
    exit;
}

$foto_url = $cloudinary["secure_url"];

// ─── Subir ticket (opcional) ───────────────────────────────────────────────
$tiket_url = null;

if (isset($_FILES["tiket"]) && $_FILES["tiket"]["error"] === UPLOAD_ERR_OK) {

    $mime_tiket = mime_content_type($_FILES["tiket"]["tmp_name"]);

    if (!in_array($mime_tiket, $allowed)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "msg" => "Formato de ticket no permitido"]);
        exit;
    }

    $public_id_tiket = "gasolina/tiket_ruta_" . intval($id_ruta) . "_" . date("Ymd_His");
    $timestamp_tiket = time();
    $signature_tiket = sha1("public_id=" . $public_id_tiket . "&timestamp=" . $timestamp_tiket . $API_SECRET);

    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => "https://api.cloudinary.com/v1_1/$CLOUD_NAME/image/upload",
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            "file"      => new CURLFile($_FILES["tiket"]["tmp_name"], $mime_tiket, $_FILES["tiket"]["name"]),
            "api_key"   => $API_KEY,
            "timestamp" => $timestamp_tiket,
            "public_id" => $public_id_tiket,
            "signature" => $signature_tiket,
        ]
    ]);

    $response_tiket   = curl_exec($ch2);
    $curl_error_tiket = curl_error($ch2);
    curl_close($ch2);

    if (!$curl_error_tiket) {
        $cloudinary_tiket = json_decode($response_tiket, true);
        $tiket_url = $cloudinary_tiket["secure_url"] ?? null;
    }
}
// ─── Guardar en BD ─────────────────────────────────────────────────────────
if ($tipo === "inicio") {

    $stmt = $conn->prepare(
        "INSERT INTO gasolina_evidencias
            (id_ruta, id_unidad, foto_inicio_url, formInicio)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            foto_inicio_url = VALUES(foto_inicio_url),
            formInicio      = VALUES(formInicio)"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => "error", "msg" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("iiss", $id_ruta, $id_unidad, $foto_url, $estado_tanque);

} else {

    $stmt = $conn->prepare(
        "INSERT INTO gasolina_evidencias
            (id_ruta, id_unidad, foto_fin_url, formFinal, gas_rut, tiket_combus)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            foto_fin_url   = VALUES(foto_fin_url),
            formFinal      = VALUES(formFinal),
            gas_rut        = VALUES(gas_rut),
            tiket_combus   = VALUES(tiket_combus)"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => "error", "msg" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("iissss", $id_ruta, $id_unidad, $foto_url, $estado_tanque, $gas_rut, $tiket_url);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg"    => "Execute failed: " . $stmt->error,
        "errno"  => $stmt->errno
    ]);
    exit;
}

echo json_encode([
    "status"        => "success",
    "msg"           => "Foto de gasolina ($tipo) guardada",
    "foto_url"      => $foto_url,
    "tiket_url"     => $tiket_url,  // ← nuevo
    "tipo"          => $tipo,
    "estado_tanque" => $estado_tanque,
    "gas_rut"       => $gas_rut
]);

$stmt->close();
$conn->close();
?>