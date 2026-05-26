<?php
/**
 * Callback HTTP appelé par le démon Python (jeedomdaemon).
 *
 * Protocole jeedomdaemon :
 *   POST  <callback_url>?apikey=<apikey>
 *   Body  : JSON  { "action": "...", ... }
 *
 * L'apikey est donc dans $_GET['apikey'], PAS dans un header.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

// ── Vérification de la clé API ────────────────────────────────────────────────
// jeedomdaemon ajoute ?apikey=xxx à l'URL callback
$receivedKey = isset($_GET['apikey']) ? $_GET['apikey'] : '';

// Fallback sur les headers au cas où
if ($receivedKey === '') {
    foreach (['HTTP_AUTHORIZATION', 'HTTP_X_API_KEY'] as $h) {
        if (!empty($_SERVER[$h])) {
            $receivedKey = $_SERVER[$h];
            break;
        }
    }
}

if ($receivedKey !== jeedom::getApiKey('hyundaikia')) {
    log::add('hyundaikia', 'warning', 'Callback : clé API invalide');
    http_response_code(403);
    die(json_encode(['state' => 'error', 'result' => 'Forbidden']));
}

// ── Lecture du body JSON ──────────────────────────────────────────────────────
$raw = file_get_contents('php://input');

if (empty($raw)) {
    // jeedomdaemon peut aussi faire un GET simple pour vérifier que le
    // callback répond (heartbeat). On répond OK dans ce cas.
    header('Content-Type: application/json');
    echo json_encode(['state' => 'ok']);
    die();
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    log::add('hyundaikia', 'error', 'Callback : JSON invalide reçu : ' . substr($raw, 0, 200));
    http_response_code(400);
    die(json_encode(['state' => 'error', 'result' => 'Invalid JSON']));
}

header('Content-Type: application/json');

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
        // Action inconnue mais on répond toujours 200 pour ne pas bloquer le démon
        log::add('hyundaikia', 'debug', 'Callback : action non gérée : ' . $action . ' – ' . substr($raw, 0, 200));
        echo json_encode(['state' => 'ok']);
        break;
}
