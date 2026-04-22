<?php
require_once __DIR__ . "/db.php";

try {
    $conn = db_conn();
    echo "Conexión correcta";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}