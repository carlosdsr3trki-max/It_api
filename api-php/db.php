<?php
// db.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// mysqli (para rutas.php, finalizar_ruta.php, etc.)
function db_conn(): mysqli {
    $host = getenv("MYSQLHOST");
    $user = getenv("MYSQLUSER");
    $pass = getenv("MYSQLPASSWORD");
    $name = getenv("MYSQLDATABASE");
    $port = (int)(getenv("MYSQLPORT") ?: 19027);

    if (!$host || !$user || !$name) {
        http_response_code(500);
echo json_encode([
    "host" => getenv("MYSQLHOST"),
    "user" => getenv("MYSQLUSER"),
    "db"   => getenv("MYSQLDATABASE")
]);
exit;
    }

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->real_connect($host, $user, $pass, $name, $port);
    $conn->set_charset("utf8mb4");
    return $conn;
}