<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$usuario = $_ENV['DB_USER'] ?? 'root';
$contrasena = $_ENV['DB_PASS'] ?? '';
$base_de_datos = $_ENV['DB_NAME'] ?? 'discord_db';

$conn = new mysqli($host, $usuario, $contrasena, $base_de_datos);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
