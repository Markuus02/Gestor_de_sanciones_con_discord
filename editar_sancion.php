<?php
session_start();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Error PHP: $errstr en $errfile línea $errline"]);
    exit;
});
set_exception_handler(function($ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Excepción: ' . $ex->getMessage()]);
    exit;
});

require_once 'permisos.php';

if (!isset($_SESSION['discord_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '⛔ No has iniciado sesión con Discord.']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

require_once 'conexion.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

$companero = $_POST['companero_staff'] ?? '';
$fin = $_POST['fecha_fin'] ?? '';
$motivo = $_POST['motivo'] ?? '';
$pruebasTexto = $_POST['pruebas'] ?? '';

$actuales = json_decode($_POST['pruebas_actuales'] ?? '[]', true);
$eliminadas = json_decode($_POST['pruebas_eliminadas'] ?? '[]', true);
$nuevas = $_FILES['pruebas_nuevas'] ?? null;

if (!is_array($actuales)) $actuales = [];
if (!is_array($eliminadas)) $eliminadas = [];

$directorio = 'uploads/imagenes/';
if (!is_dir($directorio)) mkdir($directorio, 0777, true);

$nuevas_urls = [];

// Eliminar archivos eliminados
foreach ($eliminadas as $archivo) {
    if (file_exists($archivo)) {
        unlink($archivo);
    }
    $actuales = array_filter($actuales, fn($f) => $f !== $archivo);
}

// Subir nuevos archivos
if ($nuevas && isset($nuevas['name']) && is_array($nuevas['name']) && count($nuevas['name']) > 0) {
    for ($i = 0; $i < count($nuevas['name']); $i++) {
        $nombreTmp = $nuevas['tmp_name'][$i];
        $nombreOriginal = basename($nuevas['name'][$i]);
        $rutaDestino = $directorio . uniqid() . '_' . $nombreOriginal;

        if (move_uploaded_file($nombreTmp, $rutaDestino)) {
            $nuevas_urls[] = $rutaDestino;
        }
    }
}

$final_urls = array_merge($actuales, $nuevas_urls);
$archivosJson = json_encode($final_urls, JSON_UNESCAPED_SLASHES);

$sql = "UPDATE sanciones SET 
            fin_ban = ?, 
            motivo_ban = ?, 
            pruebas_ban = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Error en prepare: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssi", $fin, $motivo, $pruebasTexto, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Sanción actualizada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al ejecutar: ' . $stmt->error]);
}
?>
