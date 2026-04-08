<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . "/db.php";

// ─── Credenciales Cloudinary ───────────────────────────────
$CLOUD_NAME = getenv("CLOUD_NAME");
$API_KEY    = getenv("CLOUDINARY_API_KEY");
$API_SECRET = getenv("CLOUDINARY_API_SECRET");

try {
    $conn = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "DB fail", "error" => $e->getMessage()]);
    exit;
}

// ─── Parámetros ────────────────────────────────────────────
$id_ruta    = $_POST["id_ruta"]    ?? null;
$cve_pedido = $_POST["cve_pedido"] ?? null;
$comentario = $_POST["comentario"] ?? "";
$quien_recibe = $_POST["quien_recibe"]  ?? "";  

if (!$id_ruta || !$cve_pedido) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan parámetros: id_ruta, cve_pedido"]);
    exit;
}

if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta foto o error al subir"]);
    exit;
}

// ─── Validación MIME ───────────────────────────────────────
$allowed = ["image/jpeg", "image/png", "image/webp"];
$mime    = mime_content_type($_FILES["foto"]["tmp_name"]);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Formato no permitido"]);
    exit;
}

// ─── Subir a Cloudinary ────────────────────────────────────
$timestamp = time();
$public_id = "pedidos/ruta_" . intval($id_ruta) . "_pedido_" . intval($cve_pedido) . "_" . date("Ymd_His");
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

// ─── Guardar en BD ─────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO evidencia_pedido (id_ruta, cve_pedido, foto_url, comentario, quien_recibe, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
        foto_url      = VALUES(foto_url),
        comentario    = VALUES(comentario),
        quien_recibe  = VALUES(quien_recibe)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("issss", $id_ruta, $cve_pedido, $foto_url, $comentario, $quien_recibe);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Execute failed: " . $stmt->error]);
    exit;
}

echo json_encode([
    "status"     => "success",
    "msg"        => "Evidencia del pedido guardada",
    "id_ruta"    => $id_ruta,
    "cve_pedido" => $cve_pedido,
    "foto_url"   => $foto_url
]);

$stmt->close();
$conn->close();
?>