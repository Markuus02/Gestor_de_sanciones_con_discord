<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (!isset($_SESSION['user']) && isset($_COOKIE['discord_user'])) {
    $_SESSION['user'] = json_decode($_COOKIE['discord_user'], true);
}

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Tu usuario Discord
$user = $_SESSION['user'];

// --- Datos para Discord API ---
$bot_token = $_ENV['DISCORD_TOKEN'];
$guild_id = [$_ENV['DISCORD_GUILD_ID']];
$allowed_roles = [$_ENV['ROL_PERMITIDO_ID']]; // Ahora el rol permitido también desde .env

// Paso: Obtener roles del usuario en el primer servidor (guild_id[0])
$user_id = $user['id'];
$ch = curl_init("https://discord.com/api/guilds/{$guild_id[0]}/members/$user_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$guild_member = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($guild_member['roles'])) {
    exit('Acceso denegado: no se pudo obtener información de tus roles.');
}

// Paso: Obtener lista de roles del servidor para mapear id -> nombre
$ch = curl_init("https://discord.com/api/guilds/{$guild_id[0]}/roles");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token"
]);
$roles_response = json_decode(curl_exec($ch), true);
curl_close($ch);

$role_names = [];
if (is_array($roles_response)) {
    foreach ($roles_response as $role) {
        $role_names[$role['id']] = $role['name']; // Guardamos id => nombre
    }
}

// Buscar si el usuario tiene el rol permitido y obtener su nombre
$user_role_name = '';
$has_access = false;
foreach ($guild_member['roles'] as $role_id) {
    if (in_array($role_id, $allowed_roles)) {
        $user_role_name = $role_names[$role_id] ?? 'Rol desconocido';
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    exit('Acceso denegado: no tienes el rol REI en el servidor.');
}

// --- Conexión a la base de datos ---
$host = 'localhost';
$db = 'discord_db';
$user_db = 'root';
$pass_db = '';
$conn = new mysqli($host, $user_db, $pass_db, $db);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

if (isset($_GET['discord_usuario_id']) || isset($_GET['nombre_usuario'])) {
    if (isset($_GET['discord_usuario_id'])) {
        $discord_id = $conn->real_escape_string($_GET['discord_usuario_id']);
        $sql = "SELECT nombre_usuario, hexa_usuario, discord_usuario_id, nombre_staff, discord_id_staff, staff_rango FROM sanciones WHERE discord_usuario_id = '$discord_id' ORDER BY id DESC LIMIT 1";
    } elseif (isset($_GET['nombre_usuario'])) {
        $nombre = $conn->real_escape_string($_GET['nombre_usuario']);
        $sql = "SELECT nombre_usuario, hexa_usuario, discord_usuario_id, nombre_staff, discord_id_staff, staff_rango FROM sanciones WHERE nombre_usuario = '$nombre' ORDER BY id DESC LIMIT 1";
    }

    $result = $conn->query($sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => $conn->error, 'sql' => $sql]);
        exit;
    }

    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode([]); // No encontrado
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Usuario</title>
    <link rel="stylesheet" href="styles_dashboard12.css"> <!-- Vinculamos CSS externo -->
</head>
<body>

<div class="header layout-inicio" id="mainHeader">
  <!-- user-card -->
  <div class="user-card">
    <button class="user-toggle" onclick="toggleUserDetails()"><img src="https://cdn.discordapp.com/avatars/<?= $user['id'] ?>/<?= $user['avatar'] ?>.png" alt="Avatar" width="32" height="32" style="border-radius: 50%; vertical-align: middle;">
 <?= htmlspecialchars($user['username']) ?></button>
    <div class="user-info" id="userInfo">
      <p>ID: <?= $user['id'] ?></p>
      <a href="logout.php" style="color: #7289DA; text-decoration: none;">Cerrar sesión</a>
    </div>
  </div>

  <!-- centro -->
  <div class="header-center">
    <img src="meme_norte.png" alt="Logo de GTA Zone" class="logo">
    <h3>Sanciones Sancionitas</h3>
    <div class="buttons">
      <button class="btn" onclick="toggleSection('formSection')">Registro de Sanciones</button>
      <button class="btn" onclick="toggleSection('searchSection')">Buscador Sanciones</button>
    </div>
  </div>
</div>
<!-- Sección Formulario sanciones -->
<div id="formSection" class="section">
    <form class="sancion-form" id="formSancion" method="POST" action="guardar_sancion.php" enctype="multipart/form-data">

  <fieldset>
    <legend>Datos del Usuario</legend>
<div class="grid-2">
  <div class="form-group">
    <label for="nombre_usuario">🧍 Nombre</label>
    <input type="text" name="nombre_usuario" id="nombre_usuario" />
  </div>

  <!-- Discord -->
  <div class="form-group">
    <label for="discord_usuario_id">🆔 Discord</label>
    <input type="text" name="discord_usuario_id" id="discord_usuario_id" />
  </div>

  <!-- Hexa Steam con botón -->
  <div class="form-group">
    <label for="hexa_usuario">🎮 Hexa Steam</label>
    <div class="input-with-button">
      <input type="text" name="hexa_usuario" id="hexa_usuario" />
      <button type="button" id="buscarUsuarioBtn"style="  width: 55px; height: 45px;">🔍</button>
    </div>
  </div>
</div>
  </fieldset>

  <fieldset>
    <legend>Datos del Staff</legend>
    <div class="grid-4">
      <div class="form-group">
        <label>🧑‍💼 Nombre</label>
        <input type="text" name="nombre_staff" value="<?= htmlspecialchars($user['username']) ?>" readonly />
      </div>
      <div class="form-group">
        <label>💼 Rango</label>
      <input type="text" name="staff_rango" value="<?php echo htmlspecialchars($user_role_name); ?>" readonly />
      </div>
      <div class="form-group">
        <label>🆔 Discord ID</label>
        <input type="text" name="discord_id_staff" value="<?= htmlspecialchars($user['id']) ?>" readonly />
      </div>
    <div class="form-group" style="position: relative;">
  <label for="staffSearch">🤝 Compañeros Staff</label>
  <input type="text" id="staffSearch" placeholder="Buscar compañero..." autocomplete="off">
  <div id="resultado" style="position: absolute; top: 100%; left: 0; right: 0; background: black; border: 1px solid #ccc; display: none; max-height: 200px; overflow-y: auto; z-index: 999;"></div>
  <div id="staffList" style="margin-top: 10px;"></div>
  <input type="hidden" id="discord_staff_compañero" name="companero_staff">
</div>
  </fieldset>

  <fieldset>
    <legend>¡ Datos de la Sanción</legend>
    <div class="grid-3">
      <div class="form-group">
        <label>⚠️ Tipo</label>
<select name="tipo_sancion" id="tipo_sancion">
  <option value="none" selected disabled>Selecciona una opción</option>
  <option style="background-color: green; color: white;" value="WARN">WARN</option> <!-- COLOR VERDE -->
  <option style="background-color: orange; color: white;" value="STRIKE">STRIKE</option><!-- COLOR NARANJA-->
  <option style="background-color: red; color: white;" value="BAN">BAN</option> <!-- COLOR ROJO-->
  <option style="background-color: black; color: white;" value="PERMABAN">PERMABAN</option> <!-- COLOR NEGRO -->
</select>
      </div>
<div class="container">
  <div class="input-group">
    <label for="datetimeInput">📅 Inicio</label>
    <input type="datetime-local" id="datetimeInput" name="inicio_ban">
  </div>

  <button id="dia_sanciones" type="button">➕</button>

  <div class="input-group">
    <label for="fechaFin">📅 Fin</label>
    <input type="datetime-local" id="fechaFin" name="fin_ban">
  </div>
  </fieldset>

<fieldset>
  <legend>📝 Motivo de Sanción</legend>

  <!-- Botones para añadir texto predefinido -->
<div style="margin-bottom: 8px;">
  <button type="button" data-text="PG: comportamiento inapropiado en el juego." class="boton_sanciones_prehechas">PG</button>
  <button type="button" data-text="MG: mala conducta o lenguaje inapropiado." class="boton_sanciones_prehechas">MG</button>
  <button type="button" data-text="Toxicidad: actitudes negativas y disruptivas." class="boton_sanciones_prehechas">Toxicidad</button>
</div>
<textarea placeholder="Describe el motivo..." name="motivo_ban" id="motivo_ban" rows="5" style="width: 100%;"></textarea>

</fieldset>

<fieldset>
    <legend>📷 Pruebas / Notas 👀</legend>
    <textarea placeholder="Notas internas o comentarios..." name="pruebas_ban"></textarea>
    <label style="margin-top: 10px;">📎 Subir archivos adjuntos (imágenes, vídeos ...)</label>
    <input type="file" name="archivos_prueba[]" multiple accept="image/*,video/*" />
  </fieldset>
  <div class="form-buttons">
    <button class="cancelar" type="button">CANCELAR ❌</button>
    <button class="enviar" type="submit">ENVIAR ✅</button>
  </div>
</form>
</div>


<!-- Sección Buscador sanciones -->
<div id="searchSection" class="section">
  <form id="formBuscarUsuario">
    <fieldset>
      <legend>🔍 Buscar Usuario</legend>
      <div class="grid-3">
        <div class="form-group">
          <label>🧍 Nombre</label>
          <input type="text" name="nombre_usuario" id="buscador_nombre_usuario">
        </div>
        <div class="form-group">
          <label>🆔 Discord</label>
          <input type="text" name="discord_usuario_id" id="buscador_discord_usuario_id"/>
        </div>
        <div class="form-group">
          <label>🎮 Hexa Steam</label>
          <input type="text" name="hexa_usuario" id="buscador_hexa_usuario"/>
        </div>
      </div>
      <div class="form-group">
        <button type="submit" id="buscarUsuarioBtn" style="  width: 55px; height: 45px;">🔍</button>
      </div>
    </fieldset>
  </form>

<div id="resultadoSanciones" class="resultados-sanciones"></div>
  <div id="detalleSancion" class="detalle-sancion"></div>
  <div id="detalleSancionModal" class="modal" style="display: none;">
  <div class="modal-content">
    <span class="cerrar-modal">&times;</span>
    <div id="detalleContenido"></div>
  </div>
</div>
</div>

<!-- Toast -->
<div id="toast" style="
    visibility: hidden;
    min-width: 250px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 8px;
    padding: 12px;
    position: fixed;
    z-index: 9999;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 16px;
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
    transition: visibility 0s, opacity 0.3s ease;
    opacity: 0;
"></div>

<script src="script_dashbord22.js"></script>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>
<?php
