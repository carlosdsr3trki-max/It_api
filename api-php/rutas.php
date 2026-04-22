<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

try {
    $conexion = db_conn();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "normales" => [],
        "cajas" => new stdClass(),
        "msg" => "DB fail",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$traker_id = $_POST['traker_id'] ?? $_GET['traker_id'] ?? '';

if ($traker_id === '') {
    echo json_encode([
        "ok" => false,
        "normales" => [],
        "cajas" => new stdClass(),
        "msg" => "Falta traker_id"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$sql = "
    SELECT
        id,
        id_ruta,
        pedido_id,
        repartidor_id,
        auto_id,
        lat,
        lon,
        direccion,
        orden,
        fecha,
        STAT_PED,
        STAT_REP,
        lon_alm,
        lat_alm,
        STAT_AUTO,
        traker_id,
        unidad,
        almacen, 
        direccion_alm,
        encargado_alm,
        telefono_alm,
        nombre_clie,
        telefono_clie,
        correo_clie,
        caja
    FROM rutas
    WHERE traker_id = ? AND STAT_RUT = 'N'
    ORDER BY id_ruta ASC, orden ASC
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $traker_id);
$stmt->execute();
$result = $stmt->get_result();

$normales = [];
$cajas = [];

while ($row = $result->fetch_assoc()) {
    $caja = trim((string)($row['caja'] ?? ''));

    if ($caja === '' || strtoupper($caja) === 'SN') {
        $normales[] = $row;
    } else {
        if (!isset($cajas[$caja])) {
            $cajas[$caja] = [];
        }
        $cajas[$caja][] = $row;
    }
}

echo json_encode([
    "ok" => true,
    "normales" => $normales,
    "cajas" => empty($cajas) ? new stdClass() : $cajas
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();