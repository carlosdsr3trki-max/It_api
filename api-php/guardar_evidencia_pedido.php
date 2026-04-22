<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . "/db.php";

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

$id_ruta      = $_POST["id_ruta"]      ?? null;
$cve_pedido   = $_POST["cve_pedido"]   ?? null;
$comentario   = $_POST["comentario"]   ?? "";
$quien_recibe = $_POST["quien_recibe"] ?? "";

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

// ─── Función reutilizable para subir a Cloudinary ──────────
function subirCloudinary($tmpName, $mime, $fileName, $publicId, $cloudName, $apiKey, $apiSecret) {
    $timestamp = time();
    $signature = sha1("public_id=" . $publicId . "&timestamp=" . $timestamp . $apiSecret);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.cloudinary.com/v1_1/$cloudName/image/upload",
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            "file"      => new CURLFile($tmpName, $mime, $fileName),
            "api_key"   => $apiKey,
            "timestamp" => $timestamp,
            "public_id" => $publicId,
            "signature" => $signature,
        ]
    ]);

    $response   = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) return ["error" => $curl_error];

    $result = json_decode($response, true);
    if (!isset($result["secure_url"])) return ["error" => "Sin URL", "response" => $result];

    return ["url" => $result["secure_url"]];
}

$allowed = ["image/jpeg", "image/png", "image/webp"];

// ─── Subir foto pedido ─────────────────────────────────────
$mime = mime_content_type($_FILES["foto"]["tmp_name"]);
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Formato no permitido en foto"]);
    exit;
}

$publicIdFoto = "pedidos/ruta_" . intval($id_ruta) . "_pedido_" . intval($cve_pedido) . "_" . date("Ymd_His");
$resultFoto   = subirCloudinary(
    $_FILES["foto"]["tmp_name"],
    $mime,
    $_FILES["foto"]["name"],
    $publicIdFoto,
    $CLOUD_NAME, $API_KEY, $API_SECRET
);

if (isset($resultFoto["error"])) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Error subiendo foto", "error" => $resultFoto["error"]]);
    exit;
}

$foto_url = $resultFoto["url"];

// ─── Subir firma (opcional) ────────────────────────────────
$firma_url = null;

if (isset($_FILES["firma"]) && $_FILES["firma"]["error"] === UPLOAD_ERR_OK) {
    $mimeFirma = mime_content_type($_FILES["firma"]["tmp_name"]);

    if (in_array($mimeFirma, $allowed)) {
        $publicIdFirma = "firmas/ruta_" . intval($id_ruta) . "_pedido_" . intval($cve_pedido) . "_" . date("Ymd_His");
        $resultFirma   = subirCloudinary(
            $_FILES["firma"]["tmp_name"],
            $mimeFirma,
            $_FILES["firma"]["name"],
            $publicIdFirma,
            $CLOUD_NAME, $API_KEY, $API_SECRET
        );

        if (!isset($resultFirma["error"])) {
            $firma_url = $resultFirma["url"];
        }
    }
}

// ─── Guardar en BD ─────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO evidencia_pedido (id_ruta, cve_pedido, foto_url, comentario, quien_recibe, firma_url, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
        foto_url      = VALUES(foto_url),
        comentario    = VALUES(comentario),
        quien_recibe  = VALUES(quien_recibe),
        firma_url     = VALUES(firma_url)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("isssss", $id_ruta, $cve_pedido, $foto_url, $comentario, $quien_recibe, $firma_url);

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
    "foto_url"   => $foto_url,
    "firma_url"  => $firma_url
]);

$stmt->close();
$conn->close();
?>