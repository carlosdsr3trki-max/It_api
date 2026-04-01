<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// ─── Credenciales Cloudinary ───────────────────────────────────────────────
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

// ─── Parámetros requeridos ─────────────────────────────────────────────────
$id_ruta       = $_POST["id_ruta"]       ?? null;
$id_unidad     = $_POST["id_unidad"]     ?? null;
$tipo          = $_POST["tipo"]          ?? null; // "inicio" o "fin"
$estado_tanque = $_POST["estado_tanque"] ?? null; // "Lleno", "3/4", "Medio", "1/4", "Reserva"

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

// ─── Validación MIME ───────────────────────────────────────────────────────
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

// ─── Guardar en BD ─────────────────────────────────────────────────────────
$col_foto = $tipo === "inicio" ? "foto_inicio_url" : "foto_fin_url";
$col_form = $tipo === "inicio" ? "formInicio"      : "formFinal";

$stmt = $conn->prepare(
    "INSERT INTO gasolina_evidencias (id_ruta, id_unidad, $col_foto, $col_form)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        $col_foto = VALUES($col_foto),
        $col_form = VALUES($col_form)"
);

$stmt->bind_param("iiss", $id_ruta, $id_unidad, $foto_url, $estado_tanque);  // ← solo este

if ($stmt->execute()) {
    echo json_encode([
        "status"        => "success",
        "msg"           => "Foto de gasolina ($tipo) guardada",
        "foto_url"      => $foto_url,
        "tipo"          => $tipo,
        "estado_tanque" => $estado_tanque
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>