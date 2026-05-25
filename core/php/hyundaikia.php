<?php
/**
 * Callback HTTP appelé par le démon Python pour remonter
 * les données des véhicules et les événements vers Jeedom.
 */
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');
} catch (Exception $e) {
    die('Core load error: ' . $e->getMessage());
}

// Vérification de la clé API
$apiKey = '';
foreach (['HTTP_AUTHORIZATION', 'HTTP_X_API_KEY'] as $h) {
    if (!empty($_SERVER[$h])) { $apiKey = $_SERVER[$h]; break; }
}
if (empty($apiKey) && !empty($_GET['apikey'])) {
    $apiKey = $_GET['apikey'];
}

if ($apiKey !== jeedom::getApiKey('hyundaikia')) {
    log::add('hyundaikia', 'warning', 'Clé API invalide – accès refusé');
    http_response_code(403);
    die(json_encode(['state' => 'error', 'result' => 'Forbidden']));
}

// Lecture du body JSON
$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(400);
    die(json_encode(['state' => 'error', 'result' => 'Empty body']));
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    log::add('hyundaikia', 'error', 'JSON invalide : ' . $raw);
    http_response_code(400);
    die(json_encode(['state' => 'error', 'result' => 'Invalid JSON']));
}

log::add('hyundaikia', 'debug', 'Callback reçu: action=' . ($data['action'] ?? ''));

header('Content-Type: application/json');

switch ($data['action'] ?? '') {

    case 'vehicle_update':
        if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
            foreach ($data['vehicles'] as $vehicleData) {
                hyundaikia::updateVehicle($vehicleData);
            }
        }
        echo json_encode(['state' => 'ok']);
        break;

    case 'log':
        $level   = $data['level']   ?? 'debug';
        $message = $data['message'] ?? '';
        log::add('hyundaikia', $level, '[daemon] ' . $message);
        echo json_encode(['state' => 'ok']);
        break;

    default:
        log::add('hyundaikia', 'warning', 'Action inconnue: ' . ($data['action'] ?? ''));
        echo json_encode(['state' => 'error', 'result' => 'Unknown action']);
        break;
}
