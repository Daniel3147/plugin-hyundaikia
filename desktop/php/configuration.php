<?php
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}
?>

<form class="form-horizontal">
    <fieldset>

        <!-- Section Marque et Région -->
        <div class="form-group">
            <legend><i class="fas fa-car"></i> <?php echo __('Configuration du compte', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Marque', __FILE__); ?></label>
            <div class="col-lg-4">
                <select id="brand" class="configKey form-control" data-l1key="brand">
                    <option value="HY"><?php echo __('Hyundai', __FILE__); ?></option>
                    <option value="KI"><?php echo __('Kia', __FILE__); ?></option>
                    <option value="GE"><?php echo __('Genesis', __FILE__); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Région', __FILE__); ?></label>
            <div class="col-lg-4">
                <select id="region" class="configKey form-control" data-l1key="region">
                    <option value="EU"><?php echo __('Europe', __FILE__); ?></option>
                    <option value="US"><?php echo __('États-Unis', __FILE__); ?></option>
                    <option value="CA"><?php echo __('Canada', __FILE__); ?></option>
                    <option value="CN"><?php echo __('Chine', __FILE__); ?></option>
                    <option value="AU"><?php echo __('Australie', __FILE__); ?></option>
                    <option value="IN"><?php echo __('Inde', __FILE__); ?></option>
                </select>
            </div>
        </div>

        <!-- Identifiants -->
        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Identifiant (email)', __FILE__); ?></label>
            <div class="col-lg-4">
                <input type="email" class="configKey form-control" data-l1key="username"
                    placeholder="votre@email.com" autocomplete="off"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Mot de passe', __FILE__); ?></label>
            <div class="col-lg-4">
                <input type="password" class="configKey form-control" data-l1key="password"
                    placeholder="••••••••" autocomplete="new-password"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Code PIN', __FILE__); ?>
                <sup><i class="fas fa-info-circle" title="<?php echo __('Code PIN de l\'application Bluelink/UVO', __FILE__); ?>"></i></sup>
            </label>
            <div class="col-lg-2">
                <input type="password" class="configKey form-control" data-l1key="pin"
                    placeholder="1234" maxlength="6" autocomplete="new-password"/>
            </div>
        </div>

        <!-- Test connexion -->
        <div class="form-group">
            <label class="col-lg-4 control-label"></label>
            <div class="col-lg-8">
                <button type="button" class="btn btn-info" id="bt_testConnection">
                    <i class="fas fa-plug"></i> <?php echo __('Tester la connexion', __FILE__); ?>
                </button>
                <span id="testConnectionResult" style="margin-left:10px;"></span>
            </div>
        </div>

        <!-- Séparateur -->
        <div class="form-group">
            <legend><i class="fas fa-sync-alt"></i> <?php echo __('Actualisation automatique', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Fréquence de rafraîchissement (minutes)', __FILE__); ?></label>
            <div class="col-lg-2">
                <input type="number" class="configKey form-control" data-l1key="refresh_frequency"
                    value="30" min="5" max="1440"/>
            </div>
            <div class="col-lg-5">
                <span class="help-block">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <?php echo __('Attention: des rafraîchissements trop fréquents peuvent entraîner des limitations de l\'API ou décharger la batterie 12V du véhicule.', __FILE__); ?>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"><?php echo __('Utiliser le cache (recommandé)', __FILE__); ?></label>
            <div class="col-lg-2">
                <input type="checkbox" class="configKey" data-l1key="use_cache" checked/>
            </div>
            <div class="col-lg-5">
                <span class="help-block">
                    <?php echo __('Si activé, utilise les données en cache du serveur Hyundai/Kia. Désactiver pour forcer la lecture depuis le véhicule (plus lent, consomme plus d\'énergie).', __FILE__); ?>
                </span>
            </div>
        </div>

        <!-- Recherche véhicules -->
        <div class="form-group">
            <legend><i class="fas fa-search"></i> <?php echo __('Découverte des véhicules', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-lg-4 control-label"></label>
            <div class="col-lg-8">
                <button type="button" class="btn btn-success" id="bt_searchVehicles">
                    <i class="fas fa-search"></i> <?php echo __('Rechercher mes véhicules', __FILE__); ?>
                </button>
                <span class="help-block">
                    <?php echo __('Sauvegardez d\'abord vos identifiants, puis cliquez pour découvrir vos véhicules.', __FILE__); ?>
                </span>
            </div>
        </div>

        <!-- Liste des véhicules trouvés -->
        <div id="vehiclesList" style="display:none;">
            <div class="form-group">
                <legend><?php echo __('Véhicules trouvés', __FILE__); ?></legend>
            </div>
            <div class="form-group">
                <div class="col-lg-12">
                    <div id="vehiclesTable"></div>
                </div>
            </div>
        </div>

    </fieldset>
</form>

<script>
    /* ========= TEST CONNEXION ========= */
    $('#bt_testConnection').on('click', function () {
        var $btn = $(this);
        var $result = $('#testConnectionResult');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $result.html('');

        $.ajax({
            type: 'POST',
            url: 'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
            data: {
                action: 'testConnection'
            },
            dataType: 'json',
            error: function (request, status, error) {
                $result.html('<span class="text-danger"><i class="fas fa-times-circle"></i> ' + error + '</span>');
                $btn.prop('disabled', false).html('<i class="fas fa-plug"></i> <?php echo __('Tester la connexion', __FILE__); ?>');
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $result.html('<span class="text-danger"><i class="fas fa-times-circle"></i> ' + data.result + '</span>');
                } else {
                    $result.html('<span class="text-success"><i class="fas fa-check-circle"></i> ' + data.result.message + '</span>');
                }
                $btn.prop('disabled', false).html('<i class="fas fa-plug"></i> <?php echo __('Tester la connexion', __FILE__); ?>');
            }
        });
    });

    /* ========= RECHERCHE VEHICULES ========= */
    $('#bt_searchVehicles').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo __('Recherche en cours...', __FILE__); ?>');
        $('#vehiclesList').hide();

        $.ajax({
            type: 'POST',
            url: 'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
            data: {
                action: 'getVehicles'
            },
            dataType: 'json',
            error: function (request, status, error) {
                $('#vehiclesTable').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + error + '</div>');
                $('#vehiclesList').show();
                $btn.prop('disabled', false).html('<i class="fas fa-search"></i> <?php echo __('Rechercher mes véhicules', __FILE__); ?>');
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#vehiclesTable').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + data.result + '</div>');
                    $('#vehiclesList').show();
                } else {
                    var vehicles = data.result;
                    if (!vehicles || vehicles.length === 0) {
                        $('#vehiclesTable').html('<div class="alert alert-warning"><?php echo __('Aucun véhicule trouvé.', __FILE__); ?></div>');
                    } else {
                        var html = '<table class="table table-bordered table-hover">';
                        html += '<thead><tr>';
                        html += '<th><?php echo __('Nom', __FILE__); ?></th>';
                        html += '<th><?php echo __('Modèle', __FILE__); ?></th>';
                        html += '<th><?php echo __('Année', __FILE__); ?></th>';
                        html += '<th><?php echo __('VIN', __FILE__); ?></th>';
                        html += '<th><?php echo __('Type', __FILE__); ?></th>';
                        html += '<th><?php echo __('Action', __FILE__); ?></th>';
                        html += '</tr></thead><tbody>';

                        $.each(vehicles, function (i, v) {
                            var type = v.is_ev ? '<span class="label label-success">EV</span>' :
                                       (v.is_phev ? '<span class="label label-info">PHEV</span>' :
                                       '<span class="label label-default">Thermique</span>');
                            html += '<tr>';
                            html += '<td><i class="fas fa-car"></i> ' + (v.name || '-') + '</td>';
                            html += '<td>' + (v.model || '-') + '</td>';
                            html += '<td>' + (v.year || '-') + '</td>';
                            html += '<td><small>' + (v.vin || '-') + '</small></td>';
                            html += '<td>' + type + '</td>';
                            html += '<td><button class="btn btn-primary btn-sm bt_importVehicle" data-vehicle-id="' + v.id + '">';
                            html += '<i class="fas fa-download"></i> <?php echo __('Importer', __FILE__); ?></button></td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        $('#vehiclesTable').html(html);
                    }
                    $('#vehiclesList').show();
                }
                $btn.prop('disabled', false).html('<i class="fas fa-search"></i> <?php echo __('Rechercher mes véhicules', __FILE__); ?>');
            }
        });
    });

    /* ========= IMPORT VEHICULE ========= */
    $(document).on('click', '.bt_importVehicle', function () {
        var $btn = $(this);
        var vehicleId = $btn.data('vehicle-id');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            type: 'POST',
            url: 'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
            data: {
                action: 'importVehicle',
                vehicle_id: vehicleId
            },
            dataType: 'json',
            error: function (request, status, error) {
                $btn.prop('disabled', false).html('<i class="fas fa-download"></i> <?php echo __('Importer', __FILE__); ?>');
                $.fn.showAlert({
                    message: error,
                    level: 'danger'
                });
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $.fn.showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                    $btn.prop('disabled', false).html('<i class="fas fa-download"></i> <?php echo __('Importer', __FILE__); ?>');
                } else {
                    $btn.html('<i class="fas fa-check text-success"></i> <?php echo __('Importé', __FILE__); ?>');
                    $.fn.showAlert({
                        message: '<?php echo __('Véhicule importé avec succès !', __FILE__); ?>',
                        level: 'success'
                    });
                }
            }
        });
    });
</script>
