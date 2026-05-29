<?php
// Tâche cron pour le rafraîchissement automatique des véhicules Hyundai/Kia
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}

log::add('hyundaikia', 'debug', 'Démarrage cron de rafraîchissement');

$frequency = config::byKey('refresh_frequency', 'hyundaikia', 30);
$useCache = config::byKey('use_cache', 'hyundaikia', 1);

foreach (eqLogic::byType('hyundaikia') as $eqLogic) {
    if (!$eqLogic->getIsEnable()) {
        continue;
    }
    try {
        log::add('hyundaikia', 'debug', 'Rafraîchissement: ' . $eqLogic->getName());
        if ($useCache) {
            $eqLogic->refresh();
        } else {
            $eqLogic->refreshFromVehicle();
        }
    } catch (Exception $e) {
        log::add('hyundaikia', 'error', 'Erreur rafraîchissement ' . $eqLogic->getName() . ': ' . $e->getMessage());
    }
}

log::add('hyundaikia', 'debug', 'Cron terminé');
