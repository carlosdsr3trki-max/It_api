<?php
$host = 'centerbeam.proxy.rlwy.net';
$port = '33598';
$db   = 'railway';
$user = 'root';
$pass = 'ewucRbYlBixqDqmJWEHeSVyNYtWFZQiO'; // usa variable de entorno, no hardcodeada

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo json_encode([
        "status" => "ok",
        "message" => "Conexión exitosa a la BD"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}