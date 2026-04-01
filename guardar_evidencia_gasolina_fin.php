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
$estado_tanque = $_POST["estado_tanque"] ?? null;
$gas_rut       = $_POST["gas_rut"]       ?? null;

if (!$id_ruta || !$id_unidad) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan parámetros: id_ruta, id_unidad"]);
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
$public_id = "gasolina/ruta_" . intval($id_ruta) . "_unidad_" . intval($id_unidad) . "_fin_" . date("Ymd_His");
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
// 1. ¿Ya existe una fila para este id_ruta?
$check = $conn->prepare("SELECT id FROM gasolina_evidencias WHERE id_ruta = ? LIMIT 1");
$check->bind_param("i", $id_ruta);
$check->execute();
$check->store_result();
$existe = $check->num_rows > 0;
$check->close();

if ($existe) {
    // UPDATE
    $stmt = $conn->prepare(
        "UPDATE gasolina_evidencias
         SET foto_fin_url = ?, formFinal = ?, gas_rut = ?
         WHERE id_ruta = ?"
    );
    $stmt->bind_param("sssi", $foto_url, $estado_tanque, $gas_rut, $id_ruta);
} else {
    // INSERT (primera vez)
    $stmt = $conn->prepare(
        "INSERT INTO gasolina_evidencias (id_ruta, id_unidad, foto_fin_url, formFinal)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $id_ruta, $id_unidad, $foto_url, $estado_tanque);
}

if ($stmt->execute()) {
    echo json_encode([
        "status"        => "success",
        "msg"           => $existe ? "Registro actualizado" : "Registro creado",
        "foto_url"      => $foto_url,
        "estado_tanque" => $estado_tanque,
        "gas_rut"       => $gas_rut
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>