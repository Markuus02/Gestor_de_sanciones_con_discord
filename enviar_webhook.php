<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Recibe datos JSON desde el JS
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['modo']) || !isset($input['data'])) {
  http_response_code(400);
  echo "Faltan datos obligatorios.";
  exit;
}

// Webhooks desde .env
$webhookPublico = $_ENV['WEBHOOK_PUBLICO'];
$webhookInterno = $_ENV['WEBHOOK_INTERNO'];

// Colores
$colores = [
  "WARN" => hexdec("3cb371"),
  "STRIKE" => hexdec("ffa500"),
  "BAN" => hexdec("ff0000"),
  "PERMABAN" => hexdec("000000"),
];

// Armar payload
$data = $input['data'];
$modo = $input['modo'];
$url = $modo === "manual" ? $webhookPublico : $webhookInterno;

$payload = [
  "username" => $modo === "manual" ? "📛 Sistema de Sanciones" : "🛠️ Auto-Sanción Interna",
  "avatar_url" => "https://r2.fivemanage.com/rfCrVWANawnX1lHiZq5sc/log_gtazone.png",
  "embeds" => [[
    "title" => $modo === "manual" ? "🚨 Sanción aplicada" : "🚨 Registro de sanción automática",
    "color" => $colores[$data["tipo"]] ?? hexdec("7289da"),
    "fields" => $modo === "manual"
      ? [
          ["name" => "⚠️ Tipo", "value" => $data["tipo"] ?? "N/A", "inline" => true],
          ["name" => "📝 Motivo", "value" => $data["motivo"] ?? "*No especificado*", "inline" => false],
        ]
      : [
          ["name" => "📅 Fecha de Registro", "value" => date("Y-m-d H:i:s"), "inline" => true],
          ["name" => "👤 Usuario involucrado", "value" => "" . ($data["usuario"] ?? "Desconocido") . "\n `" . ($data["hexa_usuario"] ?? "") . "`\n" . ($data["discord_usuario_id"] ?? "N/A"), "inline" => true],
          ["name" => "🛡️ Staff aplicador", "value" => ($data["staff"] ?? "Sistema") . " (" . ($data["staff_rango"] ?? "N/A") . ")\n" . ($data["staff_id"] ?? "N/A"), "inline" => true],
          ["name" => "⚠️ Tipo de sanción", "value" => $data["tipo"] ?? "N/A", "inline" => true],
          ["name" => "✏️ Motivo de la sanción", "value" => $data["motivo"] ?? "*No especificado*", "inline" => false],
          ["name" => "📄 Notas y Adicionales", "value" => $data["notas"] ?? "*Sin notas*", "inline" => false],
          ["name" => "📆 Inicio", "value" => $data["inicio"] ?? "No indicado", "inline" => true],
          ["name" => "📆 Fin", "value" => $data["fin"] ?? "Indefinido", "inline" => true],
        ],

    "footer" => [
      "text" => "📋 GTAZONE - Sistema de Sanciones",
      "icon_url" => "https://r2.fivemanage.com/rfCrVWANawnX1lHiZq5sc/log_gtazone.png",
    ],
    "timestamp" => date("Y-m-d H:i")
  ]]
];

// Enviar a Discord
$options = [
  "http" => [
    "method" => "POST",
    "header" => "Content-type: application/json",
    "content" => json_encode($payload)
  ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    $error = error_get_last();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Error al enviar a Discord",
        "detalle" => $error['message']
    ]);
    exit;
} else {
    header('Content-Type: application/json');
    echo json_encode(["success" => true, "message" => "Webhook enviado correctamente"]);
}

