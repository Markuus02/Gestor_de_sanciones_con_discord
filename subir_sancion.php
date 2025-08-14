<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? 'discord_db';

// Crear conexión
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// Variables del formulario
$pruebasBan = $_POST['pruebas_ban'] ?? '';
echo "📋 Notas recibidas: " . htmlspecialchars($pruebasBan) . "<br>";

$archivosGuardados = [];

if (isset($_FILES['archivos_prueba']) && $_FILES['archivos_prueba']['error'][0] !== UPLOAD_ERR_NO_FILE) {
    $archivos = $_FILES['archivos_prueba'];
    $rutaDestino = __DIR__ . "/uploads/imagenes/";

    if (!is_dir($rutaDestino)) {
        mkdir($rutaDestino, 0755, true);
    }

    for ($i = 0; $i < count($archivos['name']); $i++) {
        $tmpName = $archivos['tmp_name'][$i];
        $nombreOriginal = basename($archivos['name'][$i]);

        $nombreSeguro = time() . "_$i_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
        $rutaFinal = $rutaDestino . $nombreSeguro;
        $webPath = "uploads/imagenes/" . $nombreSeguro;

        $extension = strtolower(pathinfo($rutaFinal, PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];

        if (in_array($extension, $permitidos)) {
            if (move_uploaded_file($tmpName, $rutaFinal)) {
                echo "✅ Archivo subido: $webPath<br>";
                $archivosGuardados[] = $webPath;
            } else {
                echo "⚠️ Error al mover archivo: $nombreOriginal<br>";
            }
        } else {
            echo "⚠️ Archivo no permitido: $nombreOriginal<br>";
        }
    }
} else {
    echo "ℹ️ No se subieron archivos<br>";
}

$archivosJson = json_encode($archivosGuardados, JSON_UNESCAPED_SLASHES);
echo "🧾 JSON de archivos: $archivosJson<br>";

// Insertar en la base de datos
$stmt = $conn->prepare("INSERT INTO sanciones (pruebas, archivos_prueba) VALUES (?, ?)");
if (!$stmt) {
    die("❌ Error preparando statement: " . $conn->error);
}
$stmt->bind_param("ss", $pruebasBan, $archivosJson);

if ($stmt->execute()) {
    echo "✅ Sanción guardada correctamente.";
} else {
    echo "❌ Error al guardar en la base de datos: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
