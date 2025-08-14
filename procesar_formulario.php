<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['discord_staff_compañero'])) {
        $nombres_string = $_POST['discord_staff_compañero'];
        $nombres = explode(',', $nombres_string);
        $nombres = array_map('trim', $nombres);

        foreach ($nombres as &$nombre) {
            $nombre = preg_replace('/(?<!\d)0$/', '', $nombre);
        }
        unset($nombre);

        // Ejemplo de conexión a la base de datos usando variables de entorno
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db = $_ENV['DB_NAME'] ?? 'discord_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $conn = new mysqli($host, $user, $pass, $db);

        if ($conn->connect_error) {
            echo "Error de conexión: " . $conn->connect_error;
            exit;
        }

        foreach ($nombres as $nombre) {
            echo "Nombre procesado: " . htmlspecialchars($nombre) . "<br>";
            // Ejemplo: guardar en la base de datos
            // $stmt = $conn->prepare("INSERT INTO tabla (campo) VALUES (?)");
            // $stmt->bind_param("s", $nombre);
            // $stmt->execute();
        }
        $conn->close();
    } else {
        echo "No se recibió el campo discord_staff_compañero";
    }
} else {
    echo "Acceso no permitido";
}
?>
