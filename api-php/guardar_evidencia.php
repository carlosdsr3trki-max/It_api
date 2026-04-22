<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// ─── Credenciales Cloudinary ───────────────────────────────────────────────
$CLOUD_NAME = getenv("CLOUD_NAME");           // ← sin prefijo
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

$id_ruta    = $_POST["id_ruta"] ?? null;
$comentario = $_POST["comentario"] ?? "";

if (!$id_ruta) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta id_ruta"]);
    exit;
}

if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Falta foto o error al subir"]);
    exit;
}

// ─── Validación de tipo MIME ───────────────────────────────────────────────
$allowed = ["image/jpeg", "image/png", "image/webp"];
$mime    = mime_content_type($_FILES["foto"]["tmp_name"]);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Formato no permitido"]);
    exit;
}

// ─── Subir a Cloudinary ────────────────────────────────────────────────────
$timestamp  = time();
$public_id  = "evidencias/ruta_" . intval($id_ruta) . "_" . date("Ymd_His");
$signature  = sha1("public_id=" . $public_id . "&timestamp=" . $timestamp . $API_SECRET);

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
    echo json_encode(["status" => "error", "msg" => "Error al conectar con Cloudinary", "error" => $curl_error]);
    exit;
}

$cloudinary = json_decode($response, true);

if (!isset($cloudinary["secure_url"])) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Cloudinary no devolvió URL", "response" => $cloudinary]);
    exit;
}

$foto_url = $cloudinary["secure_url"]; // ✅ URL pública permanente

// ─── Guardar en BD ─────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO evidencia_unidad (id_ruta, comentario, foto_url)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE
        comentario = VALUES(comentario),
        foto_url   = VALUES(foto_url)"
);

$stmt->bind_param("iss", $id_ruta, $comentario, $foto_url);

if ($stmt->execute()) {
    echo json_encode([
        "status"   => "success",
        "msg"      => "Evidencia guardada",
        "foto_url" => $foto_url
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>