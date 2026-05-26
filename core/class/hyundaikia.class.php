<?php
/* This file is part of Jeedom.
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class hyundaikia extends eqLogic {

    // =========================================================
    //  Démon
    // =========================================================

    public static function deamon_info() {
        $return = [
            'log'        => 'hyundaikia',
            'state'      => 'nok',
            'launchable' => 'ok',
        ];

        $pid_file = jeedom::getTmpFolder('hyundaikia') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            if ($pid > 0 && posix_getsid($pid) !== false) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . ' rm -f ' . $pid_file . ' 2>&1 &');
            }
        }

        if (config::byKey('username', 'hyundaikia', '') === ''
            || config::byKey('password', 'hyundaikia', '') === '') {
            $return['launchable']         = 'nok';
            $return['launchable_message'] = __('Identifiants Hyundai/Kia manquants dans la configuration du plugin.', __FILE__);
        }

        return $return;
    }

    public static function deamon_start() {
        self::deamon_stop();

        $info = self::deamon_info();
        if ($info['launchable'] !== 'ok') {
            throw new Exception(__('Vérifiez la configuration : ', __FILE__) . $info['launchable_message']);
        }

        $tmpDir   = jeedom::getTmpFolder('hyundaikia');
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $pidFile  = $tmpDir . '/daemon.pid';
        $logFile  = log::getPathToLog('hyundaikia');
        $daemonPy = dirname(__FILE__) . '/../../resources/daemon.py';

        // Tous les arguments sont échappés avec escapeshellarg()
        // pour éviter les problèmes avec les mots de passe contenant des caractères spéciaux.
        // Les arguments personnalisés (region, brand, username, password, pin)
        // sont déclarés via BaseConfig dans daemon.py — ils doivent être listés
        // APRÈS les arguments standards de jeedomdaemon.
        $params  = ' --socketport '  . escapeshellarg(config::byKey('socketport', 'hyundaikia', '55987'));
        $params .= ' --sockethost '  . escapeshellarg('127.0.0.1');
        $params .= ' --apikey '      . escapeshellarg(jeedom::getApiKey('hyundaikia'));
        // NE PAS mettre ?apikey= ici : jeedomdaemon l'ajoute lui-même automatiquement.
        // Si on l'ajoute en double, l'URL devient ?apikey=xxx?apikey=xxx → 403.
        $callbackUrl = network::getNetworkAccess('internal')
            . '/plugins/hyundaikia/core/php/hyundaikia.php';
        $params .= ' --callback ' . escapeshellarg($callbackUrl);
        $params .= ' --cycle '       . escapeshellarg(config::byKey('cycle', 'hyundaikia', '30'));
        $params .= ' --loglevel '    . escapeshellarg(log::convertLogLevel(log::getLogLevel('hyundaikia')));
        $params .= ' --pid '         . escapeshellarg($pidFile);
        // Arguments personnalisés déclarés dans HyundaiKiaConfig (BaseConfig)
        $params .= ' --region '      . escapeshellarg(config::byKey('region',   'hyundaikia', '1'));
        $params .= ' --brand '       . escapeshellarg(config::byKey('brand',    'hyundaikia', '2'));
        $params .= ' --username '    . escapeshellarg(config::byKey('username', 'hyundaikia', ''));
        $params .= ' --password '    . escapeshellarg(config::byKey('password', 'hyundaikia', ''));
        $params .= ' --pin '         . escapeshellarg(config::byKey('pin',      'hyundaikia', ''));

        $cmd = 'python3 ' . escapeshellarg($daemonPy) . $params . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

        log::add('hyundaikia', 'info', 'Lancement du démon : ' . $cmd);
        exec($cmd);

        // Attente démarrage (max 20s)
        for ($i = 0; $i < 20; $i++) {
            sleep(1);
            if (self::deamon_info()['state'] === 'ok') {
                return true;
            }
        }
        log::add('hyundaikia', 'error', 'Impossible de lancer le démon (timeout)');
        return false;
    }

    public static function deamon_stop() {
        $pidFile = jeedom::getTmpFolder('hyundaikia') . '/daemon.pid';
        if (file_exists($pidFile)) {
            $pid = intval(trim(file_get_contents($pidFile)));
            if ($pid > 0) {
                shell_exec(system::getCmdSudo() . ' kill -15 ' . $pid . ' 2>&1');
                sleep(1);
            }
            shell_exec(system::getCmdSudo() . ' rm -f ' . $pidFile . ' 2>&1 &');
        }
    }

    // =========================================================
    //  Dépendances
    // =========================================================

    // Version minimale requise de hyundai_kia_connect_api
    const HKCA_MIN_VERSION = '4.0.0';

    public static function dependancy_info() {
        $return = [
            'log'           => 'hyundaikia_dep',
            'progress_file' => jeedom::getTmpFolder('hyundaikia') . '/dep_progress',
            'state'         => 'ok',
        ];

        // pip3 show fonctionne sans --break-system-packages (lecture seule)
        $checkDaemon = shell_exec('pip3 show jeedomdaemon 2>&1');
        if (strpos($checkDaemon, 'Name:') === false) {
            $return['state'] = 'nok';
            return $return;
        }

        $checkApi = shell_exec('pip3 show hyundai_kia_connect_api 2>&1');
        if (strpos($checkApi, 'Name:') === false) {
            $return['state'] = 'nok';
            return $return;
        }

        // Vérifie la version minimale
        if (preg_match('/^Version:\s*(\S+)/m', $checkApi, $m)) {
            if (version_compare($m[1], self::HKCA_MIN_VERSION, '<')) {
                log::add('hyundaikia', 'warning',
                    'hyundai_kia_connect_api v' . $m[1] .
                    ' installée, minimum requis : v' . self::HKCA_MIN_VERSION
                );
                $return['state'] = 'nok';
            }
        } else {
            $return['state'] = 'nok';
        }

        return $return;
    }

    public static function dependancy_install() {
        log::remove('hyundaikia_dep');
        $tmpDir = jeedom::getTmpFolder('hyundaikia');
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $progressFile = $tmpDir . '/dep_progress';
        $logFile      = log::getPathToLog('hyundaikia_dep');

        // --break-system-packages est obligatoire sur Debian 12+ / Python 3.12+
        // sans ce flag pip3 refuse d'installer hors d'un venv (PEP 668)
        $pip = 'pip3 install --upgrade --break-system-packages'
             . ' \"hyundai_kia_connect_api>=4.14.1\" jeedomdaemon';

        // nohup + bash -c : le process survit après la requête HTTP Jeedom
        $inner = $pip . ' >> ' . $logFile . ' 2>&1 ; echo \"[OK] Installation terminee\" >> ' . $logFile;
        $cmd   = 'nohup bash -c ' . escapeshellarg($inner)
               . ' > /dev/null 2>&1 & echo $! > ' . escapeshellarg($progressFile);

        log::add('hyundaikia', 'info', 'Installation dependances : ' . $pip);
        exec($cmd);

        return array('script' => '', 'log' => $logFile);
    }

    // =========================================================
    //  Import / découverte des véhicules
    // =========================================================

    /**
     * Appelé par l'AJAX "scan" : interroge le démon (ou directement l'API)
     * et retourne la liste des véhicules détectés sans encore les créer.
     */
    public static function scanVehicles() {
        // On demande au démon de renvoyer la liste via le callback PHP
        // En pratique on appelle directement l'API python en CLI pour
        // un scan ponctuel sans démon.
        $region   = config::byKey('region',   'hyundaikia', '1');
        $brand    = config::byKey('brand',    'hyundaikia', '1');
        $username = config::byKey('username', 'hyundaikia', '');
        $password = config::byKey('password', 'hyundaikia', '');
        $pin      = config::byKey('pin',      'hyundaikia', '');

        if (empty($username) || empty($password)) {
            throw new Exception(__('Identifiants non configurés', __FILE__));
        }

        $scanScript = dirname(__FILE__) . '/../../resources/scan_vehicles.py';
        $args  = ' --region '   . intval($region);
        $args .= ' --brand '    . intval($brand);
        $args .= ' --username ' . escapeshellarg($username);
        $args .= ' --password ' . escapeshellarg($password);
        $args .= ' --pin '      . escapeshellarg($pin);

        $raw = shell_exec('python3 ' . $scanScript . $args . ' 2>&1');
        log::add('hyundaikia', 'debug', 'scan_vehicles output: ' . $raw);

        // Le script retourne du JSON sur stdout
        $lines = explode("\n", trim($raw));
        $json  = '';
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] === '[') {
                $json = $line;
                break;
            }
        }

        if (empty($json)) {
            throw new Exception(__('Aucune donnée retournée par le script de scan. Vérifiez les identifiants et les logs.', __FILE__));
        }

        $vehicles = json_decode($json, true);
        if (!is_array($vehicles)) {
            throw new Exception(__('Réponse invalide du script de scan : ', __FILE__) . $json);
        }

        return $vehicles;
    }

    /**
     * Crée ou met à jour un équipement à partir d'un VIN + nom.
     */
    public static function importVehicle($vin, $name, $model, $objectId = null) {
        $eqLogic = eqLogic::byLogicalId($vin, 'hyundaikia');
        if (!is_object($eqLogic)) {
            $eqLogic = new hyundaikia();
            $eqLogic->setLogicalId($vin);
            $eqLogic->setEqType_name('hyundaikia');
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
        }

        $eqLogic->setName($name ?: $vin);
        $eqLogic->setConfiguration('model', $model);
        if ($objectId !== null && $objectId !== '') {
            $eqLogic->setObject_id($objectId);
        }
        $eqLogic->save();

        log::add('hyundaikia', 'info', 'Véhicule importé : ' . $name . ' (' . $vin . ')');
        return $eqLogic->getId();
    }

    // =========================================================
    //  Mise à jour des données (appelée par le callback PHP)
    // =========================================================

    public static function updateVehicle($data) {
        if (empty($data['vin'])) return;

        $vin     = $data['vin'];
        $eqLogic = eqLogic::byLogicalId($vin, 'hyundaikia');
        if (!is_object($eqLogic)) {
            // Création automatique si inconnu
            $eqLogic = new hyundaikia();
            $eqLogic->setLogicalId($vin);
            $eqLogic->setEqType_name('hyundaikia');
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
            $eqLogic->setName(isset($data['name']) ? $data['name'] : $vin);
            $eqLogic->save();
        }

        foreach (self::getVehicleCommandsList() as $key => $def) {
            if (!array_key_exists($key, $data)) continue;
            $cmd = $eqLogic->getCmd(null, $key);
            if (!is_object($cmd)) {
                $cmd = new hyundaikiaCmd();
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setLogicalId($key);
                $cmd->setName($def['name']);
                $cmd->setType('info');
                $cmd->setSubType($def['subtype']);
                if (!empty($def['unit']))        $cmd->setUnite($def['unit']);
                if (!empty($def['genericType'])) $cmd->setGeneric_type($def['genericType']);
                $cmd->save();
            }
            $cmd->event($data[$key]);
        }

        log::add('hyundaikia', 'debug', 'Données mises à jour : ' . $vin);
    }

    // =========================================================
    //  Liste des commandes info disponibles
    // =========================================================

    public static function getVehicleCommandsList() {
        return [
            'ev_battery_percentage'     => ['name' => __('Batterie (%)',              __FILE__), 'subtype' => 'numeric', 'unit' => '%',  'genericType' => 'BATTERY'],
            'ev_battery_is_charging'    => ['name' => __('En charge',                 __FILE__), 'subtype' => 'binary',                  'genericType' => ''],
            'ev_battery_is_plugged_in'  => ['name' => __('Branché',                   __FILE__), 'subtype' => 'binary'],
            'ev_driving_range'          => ['name' => __('Autonomie électrique (km)', __FILE__), 'subtype' => 'numeric', 'unit' => 'km'],
            'fuel_level'                => ['name' => __('Niveau carburant (%)',       __FILE__), 'subtype' => 'numeric', 'unit' => '%'],
            'fuel_driving_range'        => ['name' => __('Autonomie carburant (km)',   __FILE__), 'subtype' => 'numeric', 'unit' => 'km'],
            'is_locked'                 => ['name' => __('Verrouillé',                __FILE__), 'subtype' => 'binary',                  'genericType' => 'LOCK_STATE'],
            'front_left_door_open'      => ['name' => __('Porte AV gauche',           __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'front_right_door_open'     => ['name' => __('Porte AV droite',           __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'back_left_door_open'       => ['name' => __('Porte AR gauche',           __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'back_right_door_open'      => ['name' => __('Porte AR droite',           __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'trunk_open'                => ['name' => __('Coffre',                    __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'hood_open'                 => ['name' => __('Capot',                     __FILE__), 'subtype' => 'binary',                  'genericType' => 'OPENING'],
            'front_left_window_open'    => ['name' => __('Fenêtre AV gauche',         __FILE__), 'subtype' => 'binary'],
            'front_right_window_open'   => ['name' => __('Fenêtre AV droite',         __FILE__), 'subtype' => 'binary'],
            'back_left_window_open'     => ['name' => __('Fenêtre AR gauche',         __FILE__), 'subtype' => 'binary'],
            'back_right_window_open'    => ['name' => __('Fenêtre AR droite',         __FILE__), 'subtype' => 'binary'],
            'latitude'                  => ['name' => __('Latitude',                  __FILE__), 'subtype' => 'numeric'],
            'longitude'                 => ['name' => __('Longitude',                 __FILE__), 'subtype' => 'numeric'],
            'location_name'             => ['name' => __('Lieu',                      __FILE__), 'subtype' => 'string'],
            'air_temperature'           => ['name' => __('Temp. climatisation (°C)',  __FILE__), 'subtype' => 'numeric', 'unit' => '°C', 'genericType' => 'THERMOSTAT_TEMPERATURE'],
            'air_control_is_on'         => ['name' => __('Climatisation active',      __FILE__), 'subtype' => 'binary'],
            'odometer'                  => ['name' => __('Kilométrage',               __FILE__), 'subtype' => 'numeric', 'unit' => 'km'],
            'tire_front_left_pressure'  => ['name' => __('Pression AV gauche',        __FILE__), 'subtype' => 'numeric', 'unit' => 'bar'],
            'tire_front_right_pressure' => ['name' => __('Pression AV droite',        __FILE__), 'subtype' => 'numeric', 'unit' => 'bar'],
            'tire_back_left_pressure'   => ['name' => __('Pression AR gauche',        __FILE__), 'subtype' => 'numeric', 'unit' => 'bar'],
            'tire_back_right_pressure'  => ['name' => __('Pression AR droite',        __FILE__), 'subtype' => 'numeric', 'unit' => 'bar'],
            'last_updated_at'           => ['name' => __('Dernière mise à jour',      __FILE__), 'subtype' => 'string'],
        ];
    }

    // =========================================================
    //  Cycle de vie de l'équipement
    // =========================================================

    public function postSave() {
        $actions = [
            'refresh'       => ['name' => __('Rafraîchir',             __FILE__), 'icon' => 'fas fa-sync'],
            'lock'          => ['name' => __('Verrouiller',            __FILE__), 'icon' => 'fas fa-lock'],
            'unlock'        => ['name' => __('Déverrouiller',          __FILE__), 'icon' => 'fas fa-lock-open'],
            'start_climate' => ['name' => __('Démarrer climatisation', __FILE__), 'icon' => 'fas fa-snowflake'],
            'stop_climate'  => ['name' => __('Arrêter climatisation',  __FILE__), 'icon' => 'fas fa-stop'],
            'start_charge'  => ['name' => __('Démarrer charge',        __FILE__), 'icon' => 'fas fa-charging-station'],
            'stop_charge'   => ['name' => __('Arrêter charge',         __FILE__), 'icon' => 'fas fa-stop-circle'],
        ];

        foreach ($actions as $logicalId => $def) {
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new hyundaikiaCmd();
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $cmd->setName($def['name']);
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setDisplay('icon', '<i class="' . $def['icon'] . '"></i>');
                $cmd->save();
            }
        }
    }
}


// =========================================================
//  Classe commande
// =========================================================

class hyundaikiaCmd extends cmd {

    public function execute($_options = []) {
        if ($this->getType() !== 'action') return;

        $logicalId = $this->getLogicalId();
        $eqLogic   = $this->getEqLogic();
        $vin       = $eqLogic->getLogicalId();

        $payload = ['action' => $logicalId, 'vin' => $vin];

        if ($logicalId === 'start_climate') {
            $payload['temperature'] = config::byKey('default_climate_temp', 'hyundaikia', '22');
        }

        $port   = config::byKey('socketport', 'hyundaikia', '55987');
        $apiKey = jeedom::getApiKey('hyundaikia');

        try {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAuthorization: " . $apiKey . "\r\n",
                    'content' => json_encode($payload),
                    'timeout' => 5,
                ],
            ];
            $result = file_get_contents(
                'http://127.0.0.1:' . $port,
                false,
                stream_context_create($opts)
            );
            log::add('hyundaikia', 'debug', 'Cmd ' . $logicalId . ' → ' . $result);
        } catch (Exception $e) {
            log::add('hyundaikia', 'error', 'Envoi commande : ' . $e->getMessage());
        }
    }
}
