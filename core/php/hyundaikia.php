<?php
/**
 * Callback HTTP appelé par le démon Python (jeedomdaemon).
 *
 * jeedomdaemon envoie :
 *   POST <callback_url>?apikey=<apikey>
 *   Content-Type: application/json
 *   Body: { ... }
 *
 * L'apikey est UNIQUEMENT dans $_GET['apikey'].
 * jeedomdaemon la construit lui-même : url + '?apikey=' + apikey
 * → ne jamais mettre ?apikey= dans l'URL callback côté PHP/PHP.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

// Lecture de la clé reçue — uniquement depuis $_GET (protocole jeedomdaemon)
$receivedKey = isset($_GET['apikey']) ? trim($_GET['apikey']) : '';

// Clé attendue
$expectedKey = trim(jeedom::getApiKey('hyundaikia'));

if ($receivedKey === '' || $receivedKey !== $expectedKey) {
    log::add('hyundaikia', 'warning',
        'Callback : clé API invalide'
        . ' – reçue=[' . substr($receivedKey, 0, 8) . '...]'
        . ' – attendue=[' . substr($expectedKey, 0, 8) . '...]'
    );
    http_response_code(403);
    die(json_encode(['state' => 'error', 'result' => 'Forbidden']));
}

header('Content-Type: application/json');

// Corps vide = heartbeat de jeedomdaemon → on répond OK
$raw = file_get_contents('php://input');
if (empty(trim($raw))) {
    echo json_encode(['state' => 'ok']);
    die();
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    log::add('hyundaikia', 'error', 'Callback : JSON invalide : ' . substr($raw, 0, 200));
    http_response_code(400);
    die(json_encode(['state' => 'error', 'result' => 'Invalid JSON']));
}

$action = isset($data['action']) ? $data['action'] : '';
log::add('hyundaikia', 'debug', 'Callback reçu : action=' . $action);

switch ($action) {

    case 'vehicle_update':
        if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
            foreach ($data['vehicles'] as $vehicleData) {
                hyundaikia::updateVehicle($vehicleData);
            }
        }
        echo json_encode(['state' => 'ok']);
        break;

    case 'log':
        $level   = isset($data['level'])   ? $data['level']   : 'debug';
        $message = isset($data['message']) ? $data['message'] : '';
        log::add('hyundaikia', $level, '[daemon] ' . $message);
        echo json_encode(['state' => 'ok']);
        break;

    default:
        log::add('hyundaikia', 'debug', 'Callback : action non gérée : ' . $action);
        echo json_encode(['state' => 'ok']);
        break;
}
