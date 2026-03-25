<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/auth_lib.php";
require_once __DIR__ . "/db.php";

$conn = db_conn();

$correo = trim($_POST["correo"]  ?? "");
$pass   = $_POST["password"]     ?? "";

if ($correo === "" || $pass === "") {
    http_response_code(400);
    echo json_encode(["status" => "fail", "msg" => "Ingresa correo y contraseña"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, traker_id, password, nombre, telefono, apellido 
    FROM repartidores_registro
    WHERE correo = ? LIMIT 1
");
$stmt->bind_param("s", $correo);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();

    if (password_verify($pass, $row["password"])) {
        $token        = make_token();
        $tokenHash    = hash_token($token);
        $repartidorId = (int)$row["id"];
        $expiresAt    = (new DateTime("+30 days"))->format("Y-m-d H:i:s");
        $deviceId     = substr(trim($_POST["device_id"] ?? ""), 0, 100);

        $ins = $conn->prepare("INSERT INTO sesiones (repartidor_id, token_hash, expires_at, device_id) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $repartidorId, $tokenHash, $expiresAt, $deviceId);
        $ins->execute();
        $ins->close();

        echo json_encode([
            "status"     => "success",
            "id"         => $row["id"],
            "traker_id"  => $row["traker_id"],
            "token"      => $token,
            "expires_at" => $expiresAt,
            "nombre"     => $row["nombre"],
            "telefono"   => $row["telefono"],
            "apellido"   => $row["apellido"]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "fail", "msg" => "Credenciales incorrectas"]);
    }
} else {
    http_response_code(401);
    echo json_encode(["status" => "fail", "msg" => "Credenciales incorrectas"]);
}

$stmt->close();
$conn->close();