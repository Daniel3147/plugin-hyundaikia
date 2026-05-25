<?php
/* Point d'entrée HTTP appelé par le démon Python
 * pour remonter les données des véhicules vers Jeedom
 */

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');
} catch (Exception $e) {
    die('Erreur chargement core: ' . $e->getMessage());
}

if (!isConnect('admin')) {
    // Vérifie l'API key dans le header Authorization
    $apiKey = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $apiKey = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_GET['apikey'])) {
        $apiKey = $_GET['apikey'];
    }
    if ($apiKey !== jeedom::getApiKey('hyundaikia')) {
        log::add('hyundaikia', 'error', 'Clé API invalide - accès refusé');
        die('Access denied');
    }
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    die('No data');
}

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    log::add('hyundaikia', 'error', 'Données invalides reçues du démon: ' . $rawInput);
    die('Invalid JSON');
}

log::add('hyundaikia', 'debug', 'Données reçues du démon: ' . json_encode($data));

$action = isset($data['action']) ? $data['action'] : '';

switch ($action) {
    case 'vehicle_update':
        // Mise à jour des données d'un véhicule
        if (isset($data['vehicles']) && is_array($data['vehicles'])) {
            foreach ($data['vehicles'] as $vehicleData) {
                hyundaikia::updateVehicle($vehicleData);
            }
        }
        echo json_encode(array('status' => 'ok'));
        break;

    case 'log':
        // Log envoyé par le démon
        $level   = isset($data['level']) ? $data['level'] : 'debug';
        $message = isset($data['message']) ? $data['message'] : '';
        log::add('hyundaikia', $level, '[daemon] ' . $message);
        echo json_encode(array('status' => 'ok'));
        break;

    default:
        log::add('hyundaikia', 'warning', 'Action inconnue reçue du démon: ' . $action);
        echo json_encode(array('status' => 'unknown_action'));
        break;
}
