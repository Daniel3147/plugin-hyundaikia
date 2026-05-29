<?php
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'getVehicles') {
        ajax::success(hyundaikia::getVehiclesFromAPI());
    }

    if (init('action') == 'importVehicle') {
        $vehicleId = init('vehicle_id');
        if (empty($vehicleId)) {
            throw new Exception(__('ID du véhicule manquant', __FILE__));
        }
        $eqLogic = hyundaikia::importVehicle($vehicleId);
        ajax::success(utils::o2a($eqLogic));
    }

    if (init('action') == 'testConnection') {
        $vehicles = hyundaikia::getVehiclesFromAPI();
        ajax::success(array('count' => count($vehicles), 'message' => count($vehicles) . ' véhicule(s) trouvé(s)'));
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));

} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
