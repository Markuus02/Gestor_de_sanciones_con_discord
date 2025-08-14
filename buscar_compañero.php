<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bot_token = $_ENV['DISCORD_TOKEN'];
$guild_id = $_ENV['DISCORD_GUILD_ID'];
$rol_permitido_id = '1129159480068804668'; // Si quieres, también puedes ponerlo en .env

header('Content-Type: application/json');

// Validación de búsqueda
$query = $_GET['q'] ?? '';
if (!$query) {
    echo json_encode([]);
    exit;
}

// Buscar miembros
$ch = curl_init("https://discord.com/api/guilds/$guild_id/members/search?query=" . urlencode($query) . "&limit=10");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$res = curl_exec($ch);
curl_close($ch);
$members = json_decode($res, true);

// Validar respuesta
if (!is_array($members)) {
    echo json_encode([]);
    exit;
}

// Filtrar solo los que tengan el rol permitido
$filtrados = [];

foreach ($members as $m) {
    if (in_array($rol_permitido_id, $m['roles'] ?? [])) {
        $filtrados[] = [
            'id' => $m['user']['id'],
            'username' => $m['user']['username'],
            'avatar' => $m['user']['avatar'] ?? null
        ];
    }
}

// Al final, antes de devolver los resultados:
error_log("Búsqueda de staff: " . $_GET['q']);
error_log("Resultados encontrados: " . json_encode($filtrados));
echo json_encode($filtrados);
