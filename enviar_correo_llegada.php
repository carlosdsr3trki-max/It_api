<?php

require __DIR__ . '/../vendor/autoload.php';

use Resend;

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$correo  = $_POST["correo"] ?? "";
$cliente = $_POST["cliente"] ?? "Cliente";
$pedido  = $_POST["pedido"] ?? "";

if (!$correo) {
    echo json_encode([
        "status" => "error",
        "msg" => "Falta correo"
    ]);
    exit;
}

try {
    $resend = Resend::client($_ENV['RESEND_API_KEY']);

    $result = $resend->emails->send([
        'from' => 'TRKI <onboarding@resend.dev>', // 🔥 temporal
        'to' => [$correo],
        'subject' => 'Pedido próximo a llegar',
        'html' => "
            <p>Hola {$cliente},</p>
            <p>Tu pedido <strong>{$pedido}</strong> está próximo a llegar.</p>
            <p>Por favor, prepárate para recibirlo.</p>
            <br>
            <p>Gracias.</p>
        ",
    ]);

    echo json_encode([
        "status" => "ok",
        "msg" => "Correo enviado"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}