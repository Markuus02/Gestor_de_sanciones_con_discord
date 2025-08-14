<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Conectar a la base de datos usando variables de entorno
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db = $_ENV['DB_NAME'] ?? 'discord_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

// Validar que los campos requeridos estén presentes
$required_fields = ['nombre_usuario', 'discord_usuario_id', 'hexa_usuario', 'nombre_staff', 'staff_rango', 'discord_id_staff', 'tipo_sancion', 'inicio_ban', 'fin_ban', 'motivo_ban'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Falta el campo requerido: $field"]);
        exit;
    }
}

// Escapar y limpiar los valores
$nombre_usuario = $conn->real_escape_string($_POST['nombre_usuario']);
$discord_usuario_id = $conn->real_escape_string($_POST['discord_usuario_id']);
$hexa_usuario = $conn->real_escape_string($_POST['hexa_usuario']);
$nombre_staff = $conn->real_escape_string($_POST['nombre_staff']);
$staff_rango = $conn->real_escape_string($_POST['staff_rango']);
$discord_id_staff = $conn->real_escape_string($_POST['discord_id_staff']);
error_log("Valor de companero_staff recibido: " . print_r($_POST['companero_staff'], true));
$companero_staff = isset($_POST['companero_staff']) ? $conn->real_escape_string($_POST['companero_staff']) : '';
error_log("Valor de companero_staff después de escape: " . $companero_staff);
$tipo_sancion = $conn->real_escape_string($_POST['tipo_sancion']);
$inicio_ban = $conn->real_escape_string($_POST['inicio_ban']);
$fin_ban = $conn->real_escape_string($_POST['fin_ban']);
$motivo_ban_original = $_POST['motivo_ban'];
$motivo_ban_limpio = preg_replace("/\r|\n/", ' ', $motivo_ban_original); // elimina saltos de línea
$motivo_ban = $conn->real_escape_string($motivo_ban_limpio);
$pruebas_ban = isset($_POST['pruebas_ban']) ? $conn->real_escape_string($_POST['pruebas_ban']) : '';

// Procesar archivos subidos
$archivos = [];
if (!empty($_FILES['archivos_prueba']['name'][0])) {
    $upload_dir = __DIR__ . '/uploads/imagenes/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    foreach ($_FILES['archivos_prueba']['tmp_name'] as $key => $tmp_name) {
        $file_name = basename($_FILES['archivos_prueba']['name'][$key]);
        $web_path = 'uploads/imagenes/' . time() . '_' . $file_name;
        $target_path = $upload_dir . time() . '_' . $file_name;
        if (move_uploaded_file($tmp_name, $target_path)) {
            $archivos[] = $web_path;
        }
    }
}
$archivos_json = json_encode($archivos);

// Insertar en la base de datos
$stmt = $conn->prepare("INSERT INTO sanciones (
    nombre_usuario, discord_usuario_id, hexa_usuario, nombre_staff,
    staff_rango, discord_id_staff, companero_staff, tipo_sancion,
    inicio_ban, fin_ban, motivo_ban, pruebas_ban, archivos_adjuntos
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "sssssssssssss",
    $nombre_usuario, $discord_usuario_id, $hexa_usuario, $nombre_staff,
    $staff_rango, $discord_id_staff, $companero_staff, $tipo_sancion,
    $inicio_ban, $fin_ban, $motivo_ban, $pruebas_ban, $archivos_json
);

$guardoCorrectamente = $stmt->execute();

if ($guardoCorrectamente) {
    echo json_encode(['success' => true, 'message' => 'Sanción guardada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar la sanción: ' . $stmt->error]);
}

exit;
?>