<?php

class hyundaikia extends eqLogic {

    /*     * *************************Attributs****************************** */

    // Constantes de régions
    const REGION_EUROPE = 'EU';
    const REGION_CANADA = 'CA';
    const REGION_USA = 'US';
    const REGION_CHINA = 'CN';
    const REGION_AUSTRALIA = 'AU';
    const REGION_INDIA = 'IN';

    // Constantes de marques
    const BRAND_HYUNDAI = 'HY';
    const BRAND_KIA = 'KI';
    const BRAND_GENESIS = 'GE';

    /*     * ***********************Methodes statiques*************************** */

    public static function dependancy_info() {
        $return = array();
        $return['log'] = log::getPathToLog('hyundaikia_update');
        $return['progress_file'] = jeedom::getTmpFolder('hyundaikia') . '/dependance';
        if (file_exists(jeedom::getTmpFolder('hyundaikia') . '/dependance')) {
            $return['state'] = 'in_progress';
        } else {
            if (exec(system::getCmdSudo() . system::get('cmd_check') . '-Ec "hyundai.?kia.?connect.?api" 2>/dev/null') < 1) {
                $return['state'] = 'nok';
            } else {
                $return['state'] = 'ok';
            }
        }
        return $return;
    }

    public static function dependancy_install() {
        log::remove('hyundaikia_update');
        return array(
            'script' => dirname(__FILE__) . '/../../resources/install_dep.sh ' . jeedom::getTmpFolder('hyundaikia') . '/dependance',
            'log' => log::getPathToLog('hyundaikia_update')
        );
    }

    public static function getConfig($_key, $_default = '') {
        $value = config::byKey($_key, 'hyundaikia');
        if (is_array($_default) && !is_array($value)) {
            return $_default;
        }
        if ($value == '') {
            return $_default;
        }
        return $value;
    }

    /**
     * Récupère la liste des véhicules depuis l'API
     */
    public static function getVehiclesFromAPI() {
        $script = dirname(__FILE__) . '/../../resources/hyundaikia.py';
        $brand = self::getConfig('brand', self::BRAND_HYUNDAI);
        $region = self::getConfig('region', self::REGION_EUROPE);
        $username = self::getConfig('username', '');
        $password = self::getConfig('password', '');
        $pin = self::getConfig('pin', '');

        if (empty($username) || empty($password)) {
            throw new Exception(__('Veuillez configurer vos identifiants dans la configuration du plugin', __FILE__));
        }

        $cmd = 'python3 ' . $script . ' --action list_vehicles'
            . ' --brand ' . escapeshellarg($brand)
            . ' --region ' . escapeshellarg($region)
            . ' --username ' . escapeshellarg($username)
            . ' --password ' . escapeshellarg($password)
            . ' --pin ' . escapeshellarg($pin)
            . ' 2>&1';

        log::add('hyundaikia', 'debug', 'Exécution: ' . preg_replace('/--password[^-]+/', '--password *** ', $cmd));
        $result = shell_exec($cmd);
        log::add('hyundaikia', 'debug', 'Résultat API: ' . $result);

        $json = json_decode($result, true);
        if (!is_array($json)) {
            throw new Exception(__('Erreur de communication avec l\'API: ', __FILE__) . $result);
        }
        if (isset($json['error'])) {
            throw new Exception(__('Erreur API: ', __FILE__) . $json['error']);
        }
        return $json;
    }

    /**
     * Importe un véhicule dans Jeedom
     */
    public static function importVehicle($_vehicleId) {
        $vehicles = self::getVehiclesFromAPI();
        $vehicleData = null;
        foreach ($vehicles as $v) {
            if ($v['id'] == $_vehicleId) {
                $vehicleData = $v;
                break;
            }
        }
        if ($vehicleData === null) {
            throw new Exception(__('Véhicule introuvable: ', __FILE__) . $_vehicleId);
        }

        // Vérifier si l'équipement existe déjà
        $eqLogic = eqLogic::byLogicalId($_vehicleId, 'hyundaikia');
        if (!is_object($eqLogic)) {
            $eqLogic = new hyundaikia();
            $eqLogic->setLogicalId($_vehicleId);
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
            $eqLogic->setName($vehicleData['name'] . ' ' . $vehicleData['model']);
            $eqLogic->setEqType_name('hyundaikia');
            $eqLogic->save();
        }

        $eqLogic->setConfiguration('vehicle_id', $vehicleData['id']);
        $eqLogic->setConfiguration('vehicle_name', $vehicleData['name']);
        $eqLogic->setConfiguration('vehicle_model', $vehicleData['model']);
        $eqLogic->setConfiguration('vehicle_year', $vehicleData['year'] ?? '');
        $eqLogic->setConfiguration('vehicle_vin', $vehicleData['vin'] ?? '');
        $eqLogic->setConfiguration('vehicle_reg_no', $vehicleData['reg_no'] ?? '');
        $eqLogic->setConfiguration('is_ev', $vehicleData['is_ev'] ?? false ? 1 : 0);
        $eqLogic->setConfiguration('is_phev', $vehicleData['is_phev'] ?? false ? 1 : 0);
        $eqLogic->setConfiguration('is_hev', $vehicleData['is_hev'] ?? false ? 1 : 0);
        $eqLogic->save();

        // Créer les commandes
        $eqLogic->createCommands($vehicleData);

        return $eqLogic;
    }

    /**
     * Création de toutes les commandes du véhicule
     */
    public function createCommands($_vehicleData = null) {
        $isEv = $this->getConfiguration('is_ev', 0);
        $isPhev = $this->getConfiguration('is_phev', 0);

        $commands = $this->getCommandsDefinition($isEv || $isPhev);

        foreach ($commands as $cmdDef) {
            $cmd = $this->getCmd(null, $cmdDef['logicalId']);
            if (!is_object($cmd)) {
                $cmd = new hyundaikiaCmd();
                $cmd->setLogicalId($cmdDef['logicalId']);
                $cmd->setEqLogic_id($this->getId());
            }
            $cmd->setName($cmdDef['name']);
            $cmd->setType($cmdDef['type']);
            $cmd->setSubType($cmdDef['subType']);
            if (isset($cmdDef['unite'])) $cmd->setUnite($cmdDef['unite']);
            if (isset($cmdDef['isVisible'])) $cmd->setIsVisible($cmdDef['isVisible']);
            if (isset($cmdDef['isHistorized'])) $cmd->setIsHistorized($cmdDef['isHistorized']);
            if (isset($cmdDef['order'])) $cmd->setOrder($cmdDef['order']);
            if (isset($cmdDef['generic_type'])) $cmd->setGeneric_type($cmdDef['generic_type']);
            if (isset($cmdDef['configuration'])) {
                foreach ($cmdDef['configuration'] as $k => $v) {
                    $cmd->setConfiguration($k, $v);
                }
            }
            $cmd->save();
        }
    }

    /**
     * Définition de toutes les commandes disponibles
     */
    public function getCommandsDefinition($_withElectric = false) {
        $order = 0;
        $cmds = [];

        // ===== INFORMATIONS GÉNÉRALES =====
        $cmds[] = ['logicalId' => 'refresh', 'name' => __('Rafraîchir (cache)', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'refresh_from_vehicle', 'name' => __('Rafraîchir depuis véhicule', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];

        // État général
        $cmds[] = ['logicalId' => 'last_updated_at', 'name' => __('Dernière mise à jour', __FILE__), 'type' => 'info', 'subType' => 'string', 'order' => $order++, 'isHistorized' => 0, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'engine', 'name' => __('Moteur', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1, 'generic_type' => 'GENERIC'];
        $cmds[] = ['logicalId' => 'air_conditioning', 'name' => __('Climatisation', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'smart_key_battery', 'name' => __('Batterie télécommande (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isVisible' => 1];

        // ===== LOCALISATION =====
        $cmds[] = ['logicalId' => 'latitude', 'name' => __('Latitude', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1, 'generic_type' => 'GENERIC'];
        $cmds[] = ['logicalId' => 'longitude', 'name' => __('Longitude', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1, 'generic_type' => 'GENERIC'];
        $cmds[] = ['logicalId' => 'geocode_address', 'name' => __('Adresse', __FILE__), 'type' => 'info', 'subType' => 'string', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'geocode_name', 'name' => __('Lieu', __FILE__), 'type' => 'info', 'subType' => 'string', 'order' => $order++, 'isVisible' => 1];

        // ===== CARBURANT =====
        $cmds[] = ['logicalId' => 'fuel_level', 'name' => __('Niveau carburant (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1, 'generic_type' => 'GENERIC'];
        $cmds[] = ['logicalId' => 'fuel_driving_range', 'name' => __('Autonomie carburant (km)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'km', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1];

        // ===== ÉLECTRIQUE =====
        if ($_withElectric) {
            $cmds[] = ['logicalId' => 'ev_battery_level', 'name' => __('Batterie EV (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1, 'generic_type' => 'BATTERY'];
            $cmds[] = ['logicalId' => 'ev_battery_is_charging', 'name' => __('En charge', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1, 'generic_type' => 'CHARGING'];
            $cmds[] = ['logicalId' => 'ev_battery_is_plugged_in', 'name' => __('Branché', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'ev_driving_range', 'name' => __('Autonomie EV (km)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'km', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'ev_estimated_current_charge_duration', 'name' => __('Durée charge restante (min)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'min', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'ev_estimated_fast_charge_duration', 'name' => __('Durée charge rapide (min)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'min', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'ev_estimated_portable_charge_duration', 'name' => __('Durée charge portable (min)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'min', 'order' => $order++, 'isVisible' => 0];
            $cmds[] = ['logicalId' => 'ev_estimated_station_charge_duration', 'name' => __('Durée charge station (min)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'min', 'order' => $order++, 'isVisible' => 0];
            $cmds[] = ['logicalId' => 'total_driving_range', 'name' => __('Autonomie totale (km)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'km', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1];

            // Limites de charge
            $cmds[] = ['logicalId' => 'ev_charge_limits_ac', 'name' => __('Limite charge AC (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'ev_charge_limits_dc', 'name' => __('Limite charge DC (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isVisible' => 1];

            // Actions EV
            $cmds[] = ['logicalId' => 'start_charge', 'name' => __('Démarrer charge', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'stop_charge', 'name' => __('Arrêter charge', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
            $cmds[] = ['logicalId' => 'set_charge_limits', 'name' => __('Définir limites charge', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1, 'configuration' => ['ac_limit' => 90, 'dc_limit' => 80]];
            $cmds[] = ['logicalId' => 'set_charge_limits_ac', 'name' => __('Limite charge AC', __FILE__), 'type' => 'action', 'subType' => 'slider', 'order' => $order++, 'isVisible' => 1, 'configuration' => ['minValue' => 50, 'maxValue' => 100, 'step' => 10]];
            $cmds[] = ['logicalId' => 'set_charge_limits_dc', 'name' => __('Limite charge DC', __FILE__), 'type' => 'action', 'subType' => 'slider', 'order' => $order++, 'isVisible' => 1, 'configuration' => ['minValue' => 50, 'maxValue' => 100, 'step' => 10]];
        }

        // ===== VERROUILLAGE =====
        $cmds[] = ['logicalId' => 'is_locked', 'name' => __('Verrouillé', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1, 'generic_type' => 'LOCK_STATE'];
        $cmds[] = ['logicalId' => 'lock', 'name' => __('Verrouiller', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1, 'generic_type' => 'LOCK_LOCK'];
        $cmds[] = ['logicalId' => 'unlock', 'name' => __('Déverrouiller', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1, 'generic_type' => 'LOCK_UNLOCK'];

        // ===== PORTES ET FENÊTRES =====
        $cmds[] = ['logicalId' => 'front_left_door', 'name' => __('Porte avant gauche', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'front_right_door', 'name' => __('Porte avant droite', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'back_left_door', 'name' => __('Porte arrière gauche', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'back_right_door', 'name' => __('Porte arrière droite', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'trunk', 'name' => __('Coffre', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'hood', 'name' => __('Capot', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];

        // Fenêtres
        $cmds[] = ['logicalId' => 'front_left_window', 'name' => __('Vitre avant gauche', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];
        $cmds[] = ['logicalId' => 'front_right_window', 'name' => __('Vitre avant droite', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];
        $cmds[] = ['logicalId' => 'back_left_window', 'name' => __('Vitre arrière gauche', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];
        $cmds[] = ['logicalId' => 'back_right_window', 'name' => __('Vitre arrière droite', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];

        // ===== PNEUMATIQUES =====
        $cmds[] = ['logicalId' => 'tire_front_left', 'name' => __('Pression pneu avant gauche', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'PSI', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'tire_front_right', 'name' => __('Pression pneu avant droit', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'PSI', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'tire_back_left', 'name' => __('Pression pneu arrière gauche', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'PSI', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'tire_back_right', 'name' => __('Pression pneu arrière droit', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'PSI', 'order' => $order++, 'isVisible' => 1];

        // ===== MOTEUR / DÉMARRAGE =====
        $cmds[] = ['logicalId' => 'start_engine', 'name' => __('Démarrer moteur', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'stop_engine', 'name' => __('Arrêter moteur', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];

        // ===== CLIMATISATION =====
        $cmds[] = ['logicalId' => 'start_climate', 'name' => __('Démarrer climatisation', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'stop_climate', 'name' => __('Arrêter climatisation', __FILE__), 'type' => 'action', 'subType' => 'other', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'target_temperature', 'name' => __('Température cible', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '°C', 'order' => $order++, 'isVisible' => 1];
        $cmds[] = ['logicalId' => 'set_target_temperature', 'name' => __('Régler température', __FILE__), 'type' => 'action', 'subType' => 'slider', 'order' => $order++, 'isVisible' => 1, 'configuration' => ['minValue' => 14, 'maxValue' => 30, 'step' => 0.5]];

        // ===== SÉCURITÉ =====
        $cmds[] = ['logicalId' => 'set_charge_limits_warning', 'name' => __('Alarme anti-vol', __FILE__), 'type' => 'info', 'subType' => 'binary', 'order' => $order++, 'isVisible' => 0];

        // ===== ODOMÈTRE =====
        $cmds[] = ['logicalId' => 'odometer', 'name' => __('Kilométrage', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => 'km', 'order' => $order++, 'isHistorized' => 1, 'isVisible' => 1];

        // ===== 12V BATTERIE =====
        $cmds[] = ['logicalId' => 'battery_12v', 'name' => __('Batterie 12V (%)', __FILE__), 'type' => 'info', 'subType' => 'numeric', 'unite' => '%', 'order' => $order++, 'isVisible' => 1, 'isHistorized' => 1];

        return $cmds;
    }

    /**
     * Rafraîchit les données du véhicule (depuis le cache)
     */
    public function refresh() {
        $this->updateVehicleData(false);
    }

    /**
     * Rafraîchit les données depuis le véhicule (wake-up)
     */
    public function refreshFromVehicle() {
        $this->updateVehicleData(true);
    }

    /**
     * Met à jour les données du véhicule
     */
    public function updateVehicleData($_forceRefresh = false) {
        $script = dirname(__FILE__) . '/../../resources/hyundaikia.py';
        $vehicleId = $this->getConfiguration('vehicle_id');

        $brand = config::byKey('brand', 'hyundaikia');
        $region = config::byKey('region', 'hyundaikia');
        $username = config::byKey('username', 'hyundaikia');
        $password = config::byKey('password', 'hyundaikia');
        $pin = config::byKey('pin', 'hyundaikia');

        $action = $_forceRefresh ? 'get_vehicle_status_force' : 'get_vehicle_status';

        $cmd = 'python3 ' . $script
            . ' --action ' . $action
            . ' --brand ' . escapeshellarg($brand)
            . ' --region ' . escapeshellarg($region)
            . ' --username ' . escapeshellarg($username)
            . ' --password ' . escapeshellarg($password)
            . ' --pin ' . escapeshellarg($pin)
            . ' --vehicle_id ' . escapeshellarg($vehicleId)
            . ' 2>&1';

        log::add('hyundaikia', 'debug', 'Mise à jour véhicule ' . $vehicleId);
        $result = shell_exec($cmd);
        $json = json_decode($result, true);

        if (!is_array($json)) {
            log::add('hyundaikia', 'error', 'Erreur API: ' . $result);
            throw new Exception(__('Erreur API: ', __FILE__) . $result);
        }
        if (isset($json['error'])) {
            log::add('hyundaikia', 'error', 'Erreur: ' . $json['error']);
            throw new Exception($json['error']);
        }

        $this->updateCommandValues($json);
    }

    /**
     * Met à jour les valeurs des commandes depuis les données JSON
     */
    private function updateCommandValues($_data) {
        $mapping = [
            // Général
            'last_updated_at'        => 'last_updated_at',
            'engine'                 => 'engine',
            'air_conditioning'       => 'air_conditioning',
            'smart_key_battery'      => 'smart_key_battery',
            // Localisation
            'latitude'               => 'latitude',
            'longitude'              => 'longitude',
            'geocode_address'        => 'geocode_address',
            'geocode_name'           => 'geocode_name',
            // Carburant
            'fuel_level'             => 'fuel_level',
            'fuel_driving_range'     => 'fuel_driving_range',
            // EV
            'ev_battery_level'              => 'ev_battery_level',
            'ev_battery_is_charging'        => 'ev_battery_is_charging',
            'ev_battery_is_plugged_in'      => 'ev_battery_is_plugged_in',
            'ev_driving_range'              => 'ev_driving_range',
            'ev_estimated_current_charge_duration' => 'ev_estimated_current_charge_duration',
            'ev_estimated_fast_charge_duration'    => 'ev_estimated_fast_charge_duration',
            'ev_estimated_portable_charge_duration'=> 'ev_estimated_portable_charge_duration',
            'ev_estimated_station_charge_duration' => 'ev_estimated_station_charge_duration',
            'total_driving_range'            => 'total_driving_range',
            'ev_charge_limits_ac'            => 'ev_charge_limits_ac',
            'ev_charge_limits_dc'            => 'ev_charge_limits_dc',
            // Verrouillage
            'is_locked'              => 'is_locked',
            // Portes
            'front_left_door'        => 'front_left_door',
            'front_right_door'       => 'front_right_door',
            'back_left_door'         => 'back_left_door',
            'back_right_door'        => 'back_right_door',
            'trunk'                  => 'trunk',
            'hood'                   => 'hood',
            // Fenêtres
            'front_left_window'      => 'front_left_window',
            'front_right_window'     => 'front_right_window',
            'back_left_window'       => 'back_left_window',
            'back_right_window'      => 'back_right_window',
            // Pneus
            'tire_front_left'        => 'tire_front_left',
            'tire_front_right'       => 'tire_front_right',
            'tire_back_left'         => 'tire_back_left',
            'tire_back_right'        => 'tire_back_right',
            // Autres
            'target_temperature'     => 'target_temperature',
            'odometer'               => 'odometer',
            'battery_12v'            => 'battery_12v',
        ];

        foreach ($mapping as $dataKey => $logicalId) {
            if (!isset($_data[$dataKey])) continue;
            $cmd = $this->getCmd('info', $logicalId);
            if (is_object($cmd)) {
                $value = $_data[$dataKey];
                if (is_bool($value)) $value = $value ? 1 : 0;
                $cmd->event($value);
            }
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {}
    public function postInsert() {}
    public function preSave() {}

    public function postSave() {
        // S'assurer que les commandes existent
        $isEv = $this->getConfiguration('is_ev', 0);
        $isPhev = $this->getConfiguration('is_phev', 0);
        if ($this->getConfiguration('vehicle_id') != '') {
            $this->createCommands();
        }
    }

    public function preUpdate() {}
    public function postUpdate() {}
    public function preRemove() {}
    public function postRemove() {}

    public function toHtml($_version = 'dashboard') {
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);

        // Données du véhicule
        $replace['#vehicle_name#'] = $this->getName();
        $replace['#vehicle_model#'] = $this->getConfiguration('vehicle_model', '');
        $replace['#vehicle_year#'] = $this->getConfiguration('vehicle_year', '');
        $replace['#vehicle_vin#'] = $this->getConfiguration('vehicle_vin', '');
        $replace['#is_ev#'] = $this->getConfiguration('is_ev', 0);

        // Commandes info
        $infoKeys = ['ev_battery_level', 'fuel_level', 'is_locked', 'engine', 'ev_battery_is_charging', 'ev_battery_is_plugged_in', 'ev_driving_range', 'fuel_driving_range', 'odometer', 'geocode_address', 'last_updated_at'];
        foreach ($infoKeys as $key) {
            $cmd = $this->getCmd('info', $key);
            $replace['#' . $key . '#'] = is_object($cmd) ? $cmd->execCmd() : '';
            $replace['#' . $key . '_id#'] = is_object($cmd) ? $cmd->getId() : '';
        }

        // Commandes action
        $actionKeys = ['lock', 'unlock', 'refresh', 'refresh_from_vehicle', 'start_engine', 'stop_engine', 'start_climate', 'stop_climate', 'start_charge', 'stop_charge'];
        foreach ($actionKeys as $key) {
            $cmd = $this->getCmd('action', $key);
            $replace['#' . $key . '_id#'] = is_object($cmd) ? $cmd->getId() : '';
        }

        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'hyundaikia', 'hyundaikia')));
    }
}

class hyundaikiaCmd extends cmd {

    /*     * *************************Attributs****************************** */

    /*     * ***********************Méthodes statiques*************************** */

    /*     * *********************Méthodes d'instance************************* */

    public function execute($_options = null) {
        $eqLogic = $this->getEqLogic();
        $logicalId = $this->getLogicalId();

        $script = dirname(__FILE__) . '/../../resources/hyundaikia.py';
        $brand  = config::byKey('brand', 'hyundaikia');
        $region = config::byKey('region', 'hyundaikia');
        $username = config::byKey('username', 'hyundaikia');
        $password = config::byKey('password', 'hyundaikia');
        $pin    = config::byKey('pin', 'hyundaikia');
        $vehicleId = $eqLogic->getConfiguration('vehicle_id');

        $baseCmd = 'python3 ' . $script
            . ' --brand ' . escapeshellarg($brand)
            . ' --region ' . escapeshellarg($region)
            . ' --username ' . escapeshellarg($username)
            . ' --password ' . escapeshellarg($password)
            . ' --pin ' . escapeshellarg($pin)
            . ' --vehicle_id ' . escapeshellarg($vehicleId);

        switch ($logicalId) {
            case 'refresh':
                $eqLogic->refresh();
                break;

            case 'refresh_from_vehicle':
                $eqLogic->refreshFromVehicle();
                break;

            case 'lock':
                $result = shell_exec($baseCmd . ' --action lock 2>&1');
                self::handleActionResult($result, 'Verrouillage');
                $eqLogic->refresh();
                break;

            case 'unlock':
                $result = shell_exec($baseCmd . ' --action unlock 2>&1');
                self::handleActionResult($result, 'Déverrouillage');
                $eqLogic->refresh();
                break;

            case 'start_engine':
                $result = shell_exec($baseCmd . ' --action start_engine 2>&1');
                self::handleActionResult($result, 'Démarrage moteur');
                break;

            case 'stop_engine':
                $result = shell_exec($baseCmd . ' --action stop_engine 2>&1');
                self::handleActionResult($result, 'Arrêt moteur');
                break;

            case 'start_climate':
                $temp = $this->getConfiguration('temperature', 22);
                $result = shell_exec($baseCmd . ' --action start_climate --temperature ' . escapeshellarg($temp) . ' 2>&1');
                self::handleActionResult($result, 'Climatisation');
                break;

            case 'stop_climate':
                $result = shell_exec($baseCmd . ' --action stop_climate 2>&1');
                self::handleActionResult($result, 'Arrêt climatisation');
                break;

            case 'start_charge':
                $result = shell_exec($baseCmd . ' --action start_charge 2>&1');
                self::handleActionResult($result, 'Démarrage charge');
                $eqLogic->refresh();
                break;

            case 'stop_charge':
                $result = shell_exec($baseCmd . ' --action stop_charge 2>&1');
                self::handleActionResult($result, 'Arrêt charge');
                $eqLogic->refresh();
                break;

            case 'set_charge_limits_ac':
                $acLimit = isset($_options['slider']) ? intval($_options['slider']) : 90;
                $dcLimit = intval($eqLogic->getCmd('info', 'ev_charge_limits_dc')->execCmd() ?? 80);
                $result = shell_exec($baseCmd . ' --action set_charge_limits --ac_limit ' . escapeshellarg($acLimit) . ' --dc_limit ' . escapeshellarg($dcLimit) . ' 2>&1');
                self::handleActionResult($result, 'Limite charge AC');
                $eqLogic->refresh();
                break;

            case 'set_charge_limits_dc':
                $dcLimit = isset($_options['slider']) ? intval($_options['slider']) : 80;
                $acLimit = intval($eqLogic->getCmd('info', 'ev_charge_limits_ac')->execCmd() ?? 90);
                $result = shell_exec($baseCmd . ' --action set_charge_limits --ac_limit ' . escapeshellarg($acLimit) . ' --dc_limit ' . escapeshellarg($dcLimit) . ' 2>&1');
                self::handleActionResult($result, 'Limite charge DC');
                $eqLogic->refresh();
                break;

            case 'set_charge_limits':
                $acLimit = $this->getConfiguration('ac_limit', 90);
                $dcLimit = $this->getConfiguration('dc_limit', 80);
                $result = shell_exec($baseCmd . ' --action set_charge_limits --ac_limit ' . escapeshellarg($acLimit) . ' --dc_limit ' . escapeshellarg($dcLimit) . ' 2>&1');
                self::handleActionResult($result, 'Limites charge');
                $eqLogic->refresh();
                break;

            case 'set_target_temperature':
                $temp = isset($_options['slider']) ? floatval($_options['slider']) : 22;
                $result = shell_exec($baseCmd . ' --action set_target_temperature --temperature ' . escapeshellarg($temp) . ' 2>&1');
                self::handleActionResult($result, 'Température cible');
                break;

            default:
                throw new Exception(__('Commande inconnue: ', __FILE__) . $logicalId);
        }
    }

    private static function handleActionResult($_result, $_action) {
        log::add('hyundaikia', 'debug', $_action . ' résultat: ' . $_result);
        $json = json_decode($_result, true);
        if (is_array($json) && isset($json['error'])) {
            throw new Exception($_action . ': ' . $json['error']);
        }
    }
}
