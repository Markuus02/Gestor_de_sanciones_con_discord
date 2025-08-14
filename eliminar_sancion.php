<?php
session_start(); // Asegúrate de iniciar sesión
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'permisos.php';

// Verifica que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificación de permisos usando la función de permisos
if (!tienePermiso('eliminar_sanciones')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '⛔ No tienes permiso para eliminar sanciones']);
    exit;
}

// Validar ID
if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Falta el ID de la sanción']);
    exit;
}

$id = intval($_POST['id']);

// Conexión DB usando variables de entorno
$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'discord_db';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la conexión a la base de datos']);
    exit;
}

// Eliminar sanción
$stmt = $conn->prepare("DELETE FROM sanciones WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta']);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Sanción eliminada correctamente']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontró la sanción']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la sanción']);
}

$stmt->close();
$conn->close();
?>
