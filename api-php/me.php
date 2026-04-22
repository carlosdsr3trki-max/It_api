<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/auth_lib.php";

$servername = getenv("MYSQLHOST");
$username   = getenv("MYSQLUSER");
$password   = getenv("MYSQLPASSWORD");
$dbname     = getenv("MYSQLDATABASE");
$port       = getenv("MYSQLPORT");

$conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "DB fail",
        "error" => $conn->connect_error
    ]);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["status"=>"error","msg"=>"DB fail"]);
  exit;
}

// token puede venir por POST o por header Authorization
$token = trim($_POST["token"] ?? "");
if ($token === "" && isset($_SERVER["HTTP_AUTHORIZATION"])) {
  if (preg_match('/Bearer\s+(.*)$/i', $_SERVER["HTTP_AUTHORIZATION"], $m)) {
    $token = trim($m[1]);
  }
}

if ($token === "") {
  http_response_code(401);
  echo json_encode(["status"=>"fail","msg"=>"Token requerido"]);
  exit;
}

$tokenHash = hash_token($token);

$sql = "
SELECT rr.id, rr.traker_id, s.expires_at
FROM sesiones s
JOIN repartidores_registro rr ON rr.id = s.repartidor_id
WHERE s.token_hash = ?
  AND s.expires_at > NOW()
LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
  $row = $res->fetch_assoc();

  // opcional: last_seen
  $upd = $conn->prepare("UPDATE sesiones SET last_seen_at = NOW() WHERE token_hash = ?");
  $upd->bind_param("s", $tokenHash);
  $upd->execute();
  $upd->close();

  echo json_encode([
    "status"=>"success",
    "id"=>$row["id"],
    "traker_id"=>$row["traker_id"],
    "expires_at"=>$row["expires_at"]
  ]);
} else {
  http_response_code(401);
  echo json_encode(["status"=>"fail","msg"=>"Token inválido o expirado"]);
}

$stmt->close();
$conn->close();