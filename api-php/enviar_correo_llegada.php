<?php

require __DIR__ . '/vendor/autoload.php';

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

$apiKey = getenv('RESEND_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => "No se encontró RESEND_API_KEY en Railway"
    ]);
    exit;
}

try {
    $resend = Resend::client($apiKey);

    $result = $resend->emails->send([
        'from' => 'TRKI <onboarding@resend.dev>',
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
        "msg" => "Correo enviado",
        "data" => $result
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}