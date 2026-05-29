<?php
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}

// Lecture des valeurs sauvegardées (Jeedom v4 : config::byKey)
$brand             = config::byKey('brand',             'hyundaikia', 'HY');
$region            = config::byKey('region',            'hyundaikia', 'EU');
$username          = config::byKey('username',          'hyundaikia', '');
$password          = config::byKey('password',          'hyundaikia', '');
$pin               = config::byKey('pin',               'hyundaikia', '');
$refresh_frequency = config::byKey('refresh_frequency', 'hyundaikia', 30);
$use_cache         = config::byKey('use_cache',         'hyundaikia', 1);

// Helper : sélectionne l'option courante
function opt($val, $current) {
    return $val === $current ? ' selected="selected"' : '';
}
?>

<form class="form-horizontal" id="hyundaikia-config-form">
    <fieldset>

        <!-- ── Compte ──────────────────────────────────────────────────── -->
        <div class="form-group">
            <legend><i class="fas fa-car"></i>&nbsp;<?php echo __('Configuration du compte', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"><?php echo __('Marque', __FILE__); ?></label>
            <div class="col-sm-4 col-lg-3">
                <select id="hk_brand" class="form-control">
                    <option value="HY"<?php echo opt('HY',$brand); ?>><?php echo __('Hyundai', __FILE__); ?></option>
                    <option value="KI"<?php echo opt('KI',$brand); ?>><?php echo __('Kia', __FILE__); ?></option>
                    <option value="GE"<?php echo opt('GE',$brand); ?>><?php echo __('Genesis', __FILE__); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"><?php echo __('Région', __FILE__); ?></label>
            <div class="col-sm-4 col-lg-3">
                <select id="hk_region" class="form-control">
                    <option value="EU"<?php echo opt('EU',$region); ?>><?php echo __('Europe', __FILE__); ?></option>
                    <option value="US"<?php echo opt('US',$region); ?>><?php echo __('États-Unis', __FILE__); ?></option>
                    <option value="CA"<?php echo opt('CA',$region); ?>><?php echo __('Canada', __FILE__); ?></option>
                    <option value="CN"<?php echo opt('CN',$region); ?>><?php echo __('Chine', __FILE__); ?></option>
                    <option value="AU"<?php echo opt('AU',$region); ?>><?php echo __('Australie', __FILE__); ?></option>
                    <option value="IN"<?php echo opt('IN',$region); ?>><?php echo __('Inde', __FILE__); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"><?php echo __('Identifiant (email)', __FILE__); ?></label>
            <div class="col-sm-5 col-lg-4">
                <input type="email" id="hk_username" class="form-control"
                       value="<?php echo htmlspecialchars($username); ?>"
                       placeholder="votre@email.com" autocomplete="off"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"><?php echo __('Mot de passe', __FILE__); ?></label>
            <div class="col-sm-5 col-lg-4">
                <input type="password" id="hk_password" class="form-control"
                       value="<?php echo htmlspecialchars($password); ?>"
                       placeholder="••••••••" autocomplete="new-password"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label">
                <?php echo __('Code PIN', __FILE__); ?>
                <sup><i class="fas fa-info-circle"
                    title="<?php echo __('Code PIN de l\'application Bluelink / UVO Connect (4 à 6 chiffres)', __FILE__); ?>">
                </i></sup>
            </label>
            <div class="col-sm-2 col-lg-2">
                <input type="password" id="hk_pin" class="form-control"
                       value="<?php echo htmlspecialchars($pin); ?>"
                       placeholder="1234" maxlength="6" autocomplete="new-password"/>
            </div>
        </div>

        <!-- ── Boutons Sauvegarder / Tester ───────────────────────────── -->
        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"></label>
            <div class="col-sm-9 col-lg-8">
                <button type="button" class="btn btn-success" id="bt_saveConfig">
                    <i class="fas fa-save"></i>&nbsp;<?php echo __('Sauvegarder', __FILE__); ?>
                </button>
                &nbsp;
                <button type="button" class="btn btn-info" id="bt_testConnection">
                    <i class="fas fa-plug"></i>&nbsp;<?php echo __('Tester la connexion', __FILE__); ?>
                </button>
                &nbsp;
                <span id="testConnectionResult"></span>
            </div>
        </div>

        <!-- ── Rafraîchissement ────────────────────────────────────────── -->
        <div class="form-group">
            <legend><i class="fas fa-sync-alt"></i>&nbsp;<?php echo __('Actualisation automatique', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label">
                <?php echo __('Fréquence (minutes)', __FILE__); ?>
            </label>
            <div class="col-sm-2 col-lg-1">
                <input type="number" id="hk_refresh_frequency" class="form-control"
                       value="<?php echo intval($refresh_frequency); ?>"
                       min="5" max="1440"/>
            </div>
            <div class="col-sm-7 col-lg-6">
                <p class="help-block">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <?php echo __('Des rafraîchissements trop fréquents peuvent déclencher des limitations API ou décharger la batterie 12V.', __FILE__); ?>
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label">
                <?php echo __('Utiliser le cache (recommandé)', __FILE__); ?>
            </label>
            <div class="col-sm-1">
                <input type="checkbox" id="hk_use_cache"<?php echo $use_cache ? ' checked' : ''; ?>/>
            </div>
            <div class="col-sm-7 col-lg-6">
                <p class="help-block">
                    <?php echo __('Utilise les données en cache Hyundai/Kia. Désactiver uniquement pour forcer le réveil du véhicule (plus lent, plus consommateur).', __FILE__); ?>
                </p>
            </div>
        </div>

        <!-- ── Découverte véhicules ────────────────────────────────────── -->
        <div class="form-group">
            <legend><i class="fas fa-search"></i>&nbsp;<?php echo __('Découverte des véhicules', __FILE__); ?></legend>
        </div>

        <div class="form-group">
            <label class="col-sm-3 col-lg-2 control-label"></label>
            <div class="col-sm-9">
                <button type="button" class="btn btn-primary" id="bt_searchVehicles">
                    <i class="fas fa-search"></i>&nbsp;<?php echo __('Rechercher mes véhicules', __FILE__); ?>
                </button>
                <p class="help-block">
                    <?php echo __('Sauvegardez d\'abord vos identifiants, puis lancez la recherche.', __FILE__); ?>
                </p>
            </div>
        </div>

        <!-- Résultats véhicules -->
        <div id="div_vehiclesList" style="display:none;">
            <div class="form-group">
                <legend><?php echo __('Véhicules trouvés', __FILE__); ?></legend>
            </div>
            <div class="form-group">
                <div class="col-sm-12">
                    <div id="div_vehiclesTable"></div>
                </div>
            </div>
        </div>

    </fieldset>
</form>

<script>
/* ── Sauvegarde config (Jeedom v4 : jeedom.config.save) ──────────────────── */
$('#bt_saveConfig').on('click', function () {
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    jeedom.config.save({
        plugin: 'hyundaikia',
        configuration: {
            brand:             $('#hk_brand').val(),
            region:            $('#hk_region').val(),
            username:          $('#hk_username').val(),
            password:          $('#hk_password').val(),
            pin:               $('#hk_pin').val(),
            refresh_frequency: $('#hk_refresh_frequency').val(),
            use_cache:         $('#hk_use_cache').is(':checked') ? 1 : 0
        },
        error: function (err) {
            $.fn.showAlert({ message: err.message, level: 'danger' });
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i>&nbsp;<?php echo __('Sauvegarder', __FILE__); ?>');
        },
        success: function () {
            $.fn.showAlert({ message: '<?php echo __('Configuration sauvegardée.', __FILE__); ?>', level: 'success' });
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i>&nbsp;<?php echo __('Sauvegarder', __FILE__); ?>');
        }
    });
});

/* ── Test connexion ──────────────────────────────────────────────────────── */
$('#bt_testConnection').on('click', function () {
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    var $res = $('#testConnectionResult').html('');

    $.ajax({
        type: 'POST',
        url:  'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
        data: { action: 'testConnection' },
        dataType: 'json',
        error: function (xhr, status, error) {
            $res.html('<span class="text-danger"><i class="fas fa-times-circle"></i> ' + error + '</span>');
            $btn.prop('disabled', false).html('<i class="fas fa-plug"></i>&nbsp;<?php echo __('Tester la connexion', __FILE__); ?>');
        },
        success: function (data) {
            if (data.state !== 'ok') {
                $res.html('<span class="text-danger"><i class="fas fa-times-circle"></i> ' + data.result + '</span>');
            } else {
                $res.html('<span class="text-success"><i class="fas fa-check-circle"></i> ' + data.result.message + '</span>');
            }
            $btn.prop('disabled', false).html('<i class="fas fa-plug"></i>&nbsp;<?php echo __('Tester la connexion', __FILE__); ?>');
        }
    });
});

/* ── Recherche véhicules ─────────────────────────────────────────────────── */
$('#bt_searchVehicles').on('click', function () {
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>&nbsp;<?php echo __('Recherche...', __FILE__); ?>');
    $('#div_vehiclesList').hide();

    $.ajax({
        type: 'POST',
        url:  'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
        data: { action: 'getVehicles' },
        dataType: 'json',
        error: function (xhr, status, error) {
            $('#div_vehiclesTable').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + error + '</div>');
            $('#div_vehiclesList').show();
            $btn.prop('disabled', false).html('<i class="fas fa-search"></i>&nbsp;<?php echo __('Rechercher mes véhicules', __FILE__); ?>');
        },
        success: function (data) {
            if (data.state !== 'ok') {
                $('#div_vehiclesTable').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + data.result + '</div>');
            } else {
                var vehicles = data.result;
                if (!vehicles || vehicles.length === 0) {
                    $('#div_vehiclesTable').html('<div class="alert alert-warning"><?php echo __('Aucun véhicule trouvé.', __FILE__); ?></div>');
                } else {
                    var html = '<table class="table table-bordered table-hover"><thead><tr>'
                        + '<th><?php echo __('Nom', __FILE__); ?></th>'
                        + '<th><?php echo __('Modèle', __FILE__); ?></th>'
                        + '<th><?php echo __('Année', __FILE__); ?></th>'
                        + '<th><?php echo __('VIN', __FILE__); ?></th>'
                        + '<th><?php echo __('Type', __FILE__); ?></th>'
                        + '<th><?php echo __('Action', __FILE__); ?></th>'
                        + '</tr></thead><tbody>';

                    $.each(vehicles, function (i, v) {
                        var badge = v.is_ev   ? '<span class="label label-success">EV</span>'
                                  : v.is_phev ? '<span class="label label-info">PHEV</span>'
                                  :             '<span class="label label-default">Thermique</span>';
                        html += '<tr>'
                            + '<td><i class="fas fa-car"></i> ' + (v.name  || '-') + '</td>'
                            + '<td>' + (v.model || '-') + '</td>'
                            + '<td>' + (v.year  || '-') + '</td>'
                            + '<td><small>' + (v.vin || '-') + '</small></td>'
                            + '<td>' + badge + '</td>'
                            + '<td><button class="btn btn-primary btn-sm bt_importVehicle" data-vehicle-id="' + v.id + '">'
                            + '<i class="fas fa-download"></i>&nbsp;<?php echo __('Importer', __FILE__); ?></button></td>'
                            + '</tr>';
                    });
                    html += '</tbody></table>';
                    $('#div_vehiclesTable').html(html);
                }
            }
            $('#div_vehiclesList').show();
            $btn.prop('disabled', false).html('<i class="fas fa-search"></i>&nbsp;<?php echo __('Rechercher mes véhicules', __FILE__); ?>');
        }
    });
});

/* ── Import véhicule ─────────────────────────────────────────────────────── */
$(document).on('click', '.bt_importVehicle', function () {
    var $btn      = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    var vehicleId = $btn.data('vehicle-id');

    $.ajax({
        type: 'POST',
        url:  'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
        data: { action: 'importVehicle', vehicle_id: vehicleId },
        dataType: 'json',
        error: function (xhr, status, error) {
            $.fn.showAlert({ message: error, level: 'danger' });
            $btn.prop('disabled', false).html('<i class="fas fa-download"></i>&nbsp;<?php echo __('Importer', __FILE__); ?>');
        },
        success: function (data) {
            if (data.state !== 'ok') {
                $.fn.showAlert({ message: data.result, level: 'danger' });
                $btn.prop('disabled', false).html('<i class="fas fa-download"></i>&nbsp;<?php echo __('Importer', __FILE__); ?>');
            } else {
                $btn.html('<i class="fas fa-check text-success"></i>&nbsp;<?php echo __('Importé', __FILE__); ?>');
                $.fn.showAlert({ message: '<?php echo __('Véhicule importé avec succès !', __FILE__); ?>', level: 'success' });
            }
        }
    });
});
</script>
