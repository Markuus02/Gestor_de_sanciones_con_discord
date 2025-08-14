<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// CONFIGURACIÓN desde .env
$client_id = $_ENV['DISCORD_CLIENT_ID'];
$client_secret = $_ENV['DISCORD_CLIENT_SECRET'];
$redirect_uri = 'http://localhost/Pagina_web_gtazone/callback.php'; // Puedes ponerlo en .env si lo necesitas

$bot_token = $_ENV['DISCORD_TOKEN'];
$guild_id = [$_ENV['DISCORD_GUILD_ID']];
$allowed_roles = [$_ENV['ROL_PERMITIDO_ID']];

if (!isset($_GET['code'])) {
    exit('Error: No se recibió el código de autorización (code).');
}

$code = $_GET['code'];

// Paso 1: Obtener access token
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'scope' => 'identify guilds'
];

$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($response['access_token'])) {
    exit('Error al obtener el token de acceso');
}

$access_token = $response['access_token'];

// Paso 2: Obtener datos del usuario
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
$user = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($user['id'])) {
    exit('Error al obtener información del usuario');
}

// Paso 3: Verificar roles del usuario en el servidor con token del bot
$user_id = $user['id'];
$has_access = false;

foreach ($guild_id as $guild) {
    $ch = curl_init("https://discord.com/api/guilds/$guild/members/$user_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $bot_token",
        "Content-Type: application/json"
    ]);
    $guild_member = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($guild_member['roles'])) {
        continue;
    }

    foreach ($guild_member['roles'] as $role_id) {
        if (in_array($role_id, $allowed_roles)) {
            $has_access = true;
            break 2;
        }
    }
}

if (!$has_access) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Acceso Denegado</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            body {
                margin: 0;
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background-color: rgba(0, 0, 0, 0.7);
                font-family: Arial, sans-serif;
            }
        </style>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Acceso denegado',
            text: 'No tienes el rol REI en el servidor.',
            timer: 5000,
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading()
            },
            willClose: () => {
                window.location.href = 'index.php';
            }
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

// Paso 4: Guardar login en base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . ";dbname=" . ($_ENV['DB_NAME'] ?? 'discord_db'),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS discord_logins (
            discord_id BIGINT PRIMARY KEY,
            username VARCHAR(100),
            discriminator VARCHAR(10),
            avatar VARCHAR(255),
            last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO discord_logins (discord_id, username, discriminator, avatar)
        VALUES (:id, :username, :discriminator, :avatar)
        ON DUPLICATE KEY UPDATE 
            username = VALUES(username),
            discriminator = VALUES(discriminator),
            avatar = VALUES(avatar),
            last_login = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':id' => $user['id'],
        ':username' => $user['username'],
        ':discriminator' => $user['discriminator'],
        ':avatar' => $user['avatar'] ?? null,
    ]);
} catch (PDOException $e) {
    error_log('Error en la base de datos: ' . $e->getMessage());
}

// Paso 5: Guardar sesión y cookie
$_SESSION['user'] = $user;
$_SESSION['discord_id'] = $user['id'];
setcookie('discord_user', json_encode($user), time() + (86400 * 7), "/");

// Redirigir al dashboard
header('Location: dashboard.php');
exit;
