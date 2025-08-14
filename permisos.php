<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar variables de entorno solo si no están ya cargadas
if (!isset($_ENV['DISCORD_TOKEN'])) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configuración: roles y permisos desde .env
$rolesPermisos = [
    'buscar_sanciones'   => [$_ENV['ROL_PERMITIDO_BUSCAR']],
    'editar_sanciones'   => [$_ENV['ROL_PERMITIDO_EDITAR']],
    'eliminar_sanciones' => [$_ENV['ROL_PERMITIDO_ELIMINAR']],
];

function obtenerBotToken() {
    return $_ENV['DISCORD_TOKEN'];
}

function obtenerGuildId() {
    return $_ENV['DISCORD_GUILD_ID'];
}

/**
 * Verifica si el usuario tiene el permiso solicitado
 * @param string $permiso Nombre del permiso a verificar
 * @return bool true si tiene permiso, false si no
 */
function tienePermiso(string $permiso): bool {
    global $rolesPermisos;

    if (!isset($_SESSION['discord_id'])) {
        return false; // No logueado
    }

    if (!isset($rolesPermisos[$permiso])) {
        return false; // Permiso no definido
    }

    $guildId = obtenerGuildId();
    $botToken = obtenerBotToken();
    $requiredRoles = $rolesPermisos[$permiso];
    $discordId = $_SESSION['discord_id'];

    $url = "https://discord.com/api/guilds/$guildId/members/$discordId";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bot $botToken"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['roles']) || !is_array($data['roles'])) {
        return false;
    }

    // Comprobar intersección roles del usuario con roles permitidos para el permiso
    foreach ($data['roles'] as $userRole) {
        if (in_array($userRole, $requiredRoles, true)) {
            return true;
        }
    }
    return false;
}
