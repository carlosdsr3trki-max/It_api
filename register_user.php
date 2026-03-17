<?php
header("Content-Type: application/json; charset=utf-8");

$servername = getenv("MYSQLHOST");
$username   = getenv("MYSQLUSER");
$password   = getenv("MYSQLPASSWORD");
$dbname     = getenv("MYSQLDATABASE");
$port       = (int)(getenv("MYSQLPORT") ?: 33598);

$conn = new mysqli($servername, $username, $password, $dbname, $port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "DB fail"]);
    exit;
}

$nombre    = trim($_POST["nombre"] ?? "");
$apellido  = trim($_POST["apellido"] ?? "");
$telefono  = trim($_POST["telefono"] ?? "");
$correo    = trim($_POST["correo"] ?? "");
$clave     = $_POST["password"] ?? "";
$traker_id = trim($_POST["traker_id"] ?? "");

if ($nombre === "" || $apellido === "" || $telefono === "" || $correo === "" || $clave === "" || $traker_id === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Faltan datos"]);
    exit;
}

// (Opcional) valida formato correo rápido
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "msg" => "Correo inválido"]);
    exit;
}

// 1) Verificar si el correo ya existe (tu campo correo es UNIQUE, pero esto da mensaje claro)
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

// 2) Guardar password como HASH (esto es clave)
$hash = password_hash($clave, PASSWORD_DEFAULT);

// 3) Insert con prepared statement
$stmt = $conn->prepare("
    INSERT INTO repartidores_registro (nombre, apellido, telefono, correo, password, traker_id)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssss", $nombre, $apellido, $telefono, $correo, $hash, $traker_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "msg" => "Usuario registrado"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Error al registrar"]);
}

$stmt->close();
$conn->close();