<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class hyundaikia extends eqLogic {

    // ============================
    // Méthodes statiques du plugin
    // ============================

    public static function getConfigurationOptions() {
        return array();
    }

    /**
     * Vérifie l'état du démon
     */
    public static function deamon_info() {
        $return = array();
        $return['log']     = 'hyundaikia';
        $return['state']   = 'nok';
        $return['launchable'] = 'ok';

        $pid_file = jeedom::getTmpFolder('hyundaikia') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            if (!empty($pid) && posix_getsid(intval($pid)) !== false) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . ' rm -f ' . $pid_file . ' 2>&1 &');
            }
        }

        // Vérifie que la configuration est complète
        if (config::byKey('username', 'hyundaikia') == ''
            || config::byKey('password', 'hyundaikia') == '') {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez configurer vos identifiants Hyundai/Kia Connect dans la configuration du plugin.', __FILE__);
        }
        return $return;
    }

    /**
     * Démarre le démon Python
     */
    public static function deamon_start() {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration du plugin', __FILE__) . ' : ' . $deamon_info['launchable_message']);
        }

        $plugin       = plugin::byId('hyundaikia');
        $path_plugin  = dirname(__FILE__) . '/../../';
        $path_log     = log::getPathToLog('hyundaikia');
        $path_pid     = jeedom::getTmpFolder('hyundaikia') . '/daemon.pid';

        $cmd  = JEEDOM_ROOT . '/core/php/jeeHelper.php daemon start ';
        $cmd .= __CLASS__;
        $cmd .= ' socketPort ' . config::byKey('socketport', 'hyundaikia', '55987');
        $cmd .= ' socketHost 127.0.0.1';
        $cmd .= ' apiKey '    . jeedom::getApiKey('hyundaikia');
        $cmd .= ' callbackUrl ' . network::getNetworkAccess('internal') . '/plugins/hyundaikia/core/php/hyundaikia.php';
        $cmd .= ' cycle '     . config::byKey('cycle', 'hyundaikia', '30');
        $cmd .= ' loglevel '  . log::convertLogLevel(log::getLogLevel('hyundaikia'));
        $cmd .= ' pid '       . $path_pid;
        $cmd .= ' region '    . config::byKey('region', 'hyundaikia', '1');
        $cmd .= ' brand '     . config::byKey('brand', 'hyundaikia', '1');
        $cmd .= ' username "' . config::byKey('username', 'hyundaikia') . '"';
        $cmd .= ' password "' . config::byKey('password', 'hyundaikia') . '"';
        $cmd .= ' pin "'      . config::byKey('pin', 'hyundaikia', '') . '"';

        log::add('hyundaikia', 'info', 'Lancement du démon');
        $result = exec($cmd . ' >> ' . $path_log . ' 2>&1 &');

        $i = 0;
        while ($i < 20) {
            sleep(1);
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            $i++;
        }
        if ($i >= 20) {
            log::add('hyundaikia', 'error', 'Impossible de lancer le démon');
            return false;
        }
        return true;
    }

    /**
     * Arrête le démon Python
     */
    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder('hyundaikia') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            if ($pid > 0) {
                shell_exec(system::getCmdSudo() . ' kill -15 ' . $pid . ' 2>&1 &');
            }
            sleep(1);
            shell_exec(system::getCmdSudo() . ' rm -f ' . $pid_file . ' 2>&1 &');
        }
    }

    /**
     * Gestion des dépendances
     */
    public static function dependancy_info() {
        $return = array();
        $return['log']     = 'hyundaikia_dep';
        $return['progress_file'] = jeedom::getTmpFolder('hyundaikia') . '/dep_progress';
        $return['state']   = 'ok';

        // Vérifie si les packages pip sont installés
        $check = shell_exec('pip3 show hyundai_kia_connect_api 2>&1');
        if (strpos($check, 'Name: hyundai_kia_connect_api') === false) {
            $return['state'] = 'nok';
        }
        $check2 = shell_exec('pip3 show jeedomdaemon 2>&1');
        if (strpos($check2, 'Name: jeedomdaemon') === false) {
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function dependancy_install() {
        log::remove('hyundaikia_dep');
        return array(
            'script' => dirname(__FILE__) . '/../../plugin_info/install.php dep',
            'log'    => log::getPathToLog('hyundaikia_dep')
        );
    }

    /**
     * Crée ou met à jour les équipements lors d'un message du démon
     */
    public static function updateVehicle($data) {
        if (!isset($data['vin'])) {
            return;
        }
        $vin = $data['vin'];

        // Cherche ou crée l'équipement
        $eqLogic = eqLogic::byLogicalId($vin, 'hyundaikia');
        if (!is_object($eqLogic)) {
            $eqLogic = new hyundaikia();
            $eqLogic->setLogicalId($vin);
            $eqLogic->setEqType_name('hyundaikia');
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
            $name = isset($data['name']) ? $data['name'] : $vin;
            $eqLogic->setName($name);
            $eqLogic->setCategory('automatisation', 1);
            $eqLogic->save();
            log::add('hyundaikia', 'info', 'Nouvel équipement créé : ' . $name . ' (' . $vin . ')');
        }

        // Définit et met à jour toutes les commandes info
        $commands = self::getVehicleCommandsList();
        foreach ($commands as $key => $def) {
            if (!isset($data[$key])) continue;
            $cmd = $eqLogic->getCmd(null, $key);
            if (!is_object($cmd)) {
                $cmd = new hyundaikiaCmd();
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setLogicalId($key);
                $cmd->setName($def['name']);
                $cmd->setType('info');
                $cmd->setSubType($def['subtype']);
                if (isset($def['unit']))   $cmd->setUnite($def['unit']);
                if (isset($def['icon']))   $cmd->setDisplay('icon', $def['icon']);
                $cmd->save();
            }
            $cmd->event($data[$key]);
        }

        log::add('hyundaikia', 'debug', 'Véhicule mis à jour : ' . $vin);
    }

    /**
     * Définition de toutes les commandes info disponibles
     */
    public static function getVehicleCommandsList() {
        return array(
            // Identité
            'name'                      => array('name' => 'Nom',                     'subtype' => 'string'),
            'model'                     => array('name' => 'Modèle',                  'subtype' => 'string'),
            'registration_date'         => array('name' => 'Date immatriculation',    'subtype' => 'string'),
            // Batterie / autonomie
            'ev_battery_percentage'     => array('name' => 'Batterie (%)',             'subtype' => 'numeric', 'unit' => '%'),
            'ev_battery_is_charging'    => array('name' => 'En charge',               'subtype' => 'binary'),
            'ev_battery_is_plugged_in'  => array('name' => 'Branché',                 'subtype' => 'binary'),
            'ev_driving_range'          => array('name' => 'Autonomie électrique (km)','subtype' => 'numeric', 'unit' => 'km'),
            'fuel_driving_range'        => array('name' => 'Autonomie carburant (km)', 'subtype' => 'numeric', 'unit' => 'km'),
            'fuel_level'                => array('name' => 'Niveau carburant (%)',     'subtype' => 'numeric', 'unit' => '%'),
            // Portes / verrouillage
            'is_locked'                 => array('name' => 'Verrouillé',              'subtype' => 'binary'),
            'front_left_door_open'      => array('name' => 'Porte AV gauche',         'subtype' => 'binary'),
            'front_right_door_open'     => array('name' => 'Porte AV droite',         'subtype' => 'binary'),
            'back_left_door_open'       => array('name' => 'Porte AR gauche',         'subtype' => 'binary'),
            'back_right_door_open'      => array('name' => 'Porte AR droite',         'subtype' => 'binary'),
            'trunk_open'                => array('name' => 'Coffre ouvert',           'subtype' => 'binary'),
            'hood_open'                 => array('name' => 'Capot ouvert',            'subtype' => 'binary'),
            // Fenêtres
            'front_left_window_open'    => array('name' => 'Fenêtre AV gauche',       'subtype' => 'binary'),
            'front_right_window_open'   => array('name' => 'Fenêtre AV droite',       'subtype' => 'binary'),
            'back_left_window_open'     => array('name' => 'Fenêtre AR gauche',       'subtype' => 'binary'),
            'back_right_window_open'    => array('name' => 'Fenêtre AR droite',       'subtype' => 'binary'),
            // Localisation
            'latitude'                  => array('name' => 'Latitude',                'subtype' => 'numeric'),
            'longitude'                 => array('name' => 'Longitude',               'subtype' => 'numeric'),
            'location_name'             => array('name' => 'Lieu',                    'subtype' => 'string'),
            // Climatisation
            'air_temperature'           => array('name' => 'Température clim (°C)',   'subtype' => 'numeric', 'unit' => '°C'),
            'air_control_is_on'         => array('name' => 'Climatisation active',    'subtype' => 'binary'),
            // Odométrie
            'odometer'                  => array('name' => 'Kilométrage',             'subtype' => 'numeric', 'unit' => 'km'),
            // Pression des pneus
            'tire_front_left_pressure'  => array('name' => 'Pression AV gauche',      'subtype' => 'numeric', 'unit' => 'bar'),
            'tire_front_right_pressure' => array('name' => 'Pression AV droite',      'subtype' => 'numeric', 'unit' => 'bar'),
            'tire_back_left_pressure'   => array('name' => 'Pression AR gauche',      'subtype' => 'numeric', 'unit' => 'bar'),
            'tire_back_right_pressure'  => array('name' => 'Pression AR droite',      'subtype' => 'numeric', 'unit' => 'bar'),
            // Divers
            'last_updated_at'           => array('name' => 'Dernière mise à jour',    'subtype' => 'string'),
        );
    }

    // ============================
    // Méthodes d'instance
    // ============================

    /**
     * Initialisation des commandes par défaut à la création d'un équipement
     */
    public function postSave() {
        // Ajoute les commandes actions si elles n'existent pas
        $actions = array(
            'refresh'        => array('name' => 'Rafraîchir',            'icon' => '<i class="fas fa-sync"></i>'),
            'lock'           => array('name' => 'Verrouiller',           'icon' => '<i class="fas fa-lock"></i>'),
            'unlock'         => array('name' => 'Déverrouiller',         'icon' => '<i class="fas fa-lock-open"></i>'),
            'start_climate'  => array('name' => 'Démarrer climatisation','icon' => '<i class="fas fa-snowflake"></i>'),
            'stop_climate'   => array('name' => 'Arrêter climatisation', 'icon' => '<i class="fas fa-stop"></i>'),
            'start_charge'   => array('name' => 'Démarrer charge',       'icon' => '<i class="fas fa-charging-station"></i>'),
            'stop_charge'    => array('name' => 'Arrêter charge',        'icon' => '<i class="fas fa-stop-circle"></i>'),
        );

        foreach ($actions as $logicalId => $def) {
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new hyundaikiaCmd();
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $cmd->setName($def['name']);
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setDisplay('icon', $def['icon']);
                $cmd->save();
            }
        }
    }

    public function preRemove() {
        // Rien de spécial
    }
}


class hyundaikiaCmd extends cmd {

    public function execute($options = array()) {
        if ($this->getType() != 'action') return;

        $logicalId = $this->getLogicalId();
        $eqLogic   = $this->getEqLogic();
        $vin       = $eqLogic->getLogicalId();

        // Envoie la commande au démon
        $payload = array(
            'action' => $logicalId,
            'vin'    => $vin,
        );

        // Options supplémentaires pour la climatisation
        if ($logicalId === 'start_climate') {
            $payload['temperature'] = config::byKey('default_climate_temp', 'hyundaikia', '22');
        }

        $port    = config::byKey('socketport', 'hyundaikia', '55987');
        $apiKey  = jeedom::getApiKey('hyundaikia');
        $url     = 'http://127.0.0.1:' . $port;

        try {
            $http = new com_http($url);
            $http->setPost(json_encode($payload));
            $http->setHeader(array('Content-Type: application/json', 'Authorization: ' . $apiKey));
            $result = $http->exec(5, 1);
            log::add('hyundaikia', 'debug', 'Commande envoyée: ' . $logicalId . ' pour ' . $vin . ' → ' . $result);
        } catch (Exception $e) {
            log::add('hyundaikia', 'error', 'Impossible d\'envoyer la commande: ' . $e->getMessage());
        }
    }
}
