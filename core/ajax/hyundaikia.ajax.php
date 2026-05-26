<?php
/* Points d'entrée AJAX pour les appels JS (desktop) */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

ajax::init();

$action = init('action');

// ------- Scan des véhicules -------
if ($action === 'scanVehicles') {
    try {
        $vehicles = hyundaikia::scanVehicles();
        ajax::success($vehicles);
    } catch (Exception $e) {
        ajax::error($e->getMessage());
    }
}

// ------- Import d'un véhicule -------
if ($action === 'importVehicle') {
    $vin      = init('vin', '');
    $name     = init('name', '');
    $model    = init('model', '');
    $objectId = init('object_id', null);

    if (empty($vin)) ajax::error('VIN manquant');

    try {
        $id = hyundaikia::importVehicle($vin, $name, $model, $objectId);
        ajax::success(['id' => $id]);
    } catch (Exception $e) {
        ajax::error($e->getMessage());
    }
}

throw new Exception('Action inconnue : ' . $action);
