<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/db.php";

$conn = db_conn();

$nombre    = trim($_POST["nombre"]    ?? "");
$apellido  = trim($_POST["apellido"]  ?? "");
$telefono  = trim($_POST["telefono"]  ?? "");
$correo    = trim($_POST["correo"]    ?? "");
$clave     = $_POST["password"]       ?? "";
$traker_id = trim($_POST["traker_id"] ?? "");

if ($nombre === "" || $apellido === "" || $telefono === "" || $correo === "" || $clave === "" || $traker_id === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan datos"]);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Correo inválido"]);
    exit;
}

$chk = $conn->prepare("SELECT id FROM repartidores_registro WHERE correo = ? LIMIT 1");
$chk->bind_param("s", $correo);
$chk->execute();
$chkRes = $chk->get_result();
if ($chkRes && $chkRes->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "msg" => "Ese correo ya está registrado"]);
    exit;
}
$chk->close();

$hash = password_hash($clave, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO repartidores_registro (nombre, apellido, telefono, correo, password, traker_id)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssss", $nombre, $apellido, $telefono, $correo, $hash, $traker_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "msg" => "Usuario registrado"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $stmt->error]);
}

$stmt->close();
$conn->close();