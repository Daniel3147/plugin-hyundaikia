<?php
/* Configuration globale du plugin - Jeedom 4.x
 *
 * Cette page est chargée par le core dans :
 *   Plugins > Gestion des plugins > [plugin] > Configuration
 *
 * Règles Jeedom 4 :
 *  - Les champs portent la classe "configKey" + data-l1key="maClé"
 *  - Le core se charge de lire/écrire via config::byKey('maClé', 'hyundaikia')
 *  - On DOIT inclure 'plugin.config.template' en JS à la fin
 *  - On DOIT avoir un bouton class="savePluginConfig" pour déclencher la sauvegarde
 */
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<div class="row">
    <div class="col-xs-12">
        <form class="form-horizontal" id="formPluginConfig">

            <!-- ============================================================
                 Bloc 1 – Identifiants
                 ============================================================ -->
            <fieldset>
                <legend>
                    <i class="fas fa-user-lock"></i>
                    {{Identifiants Hyundai / Kia Connect}}
                </legend>

                <!-- Marque -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Marque}}
                    </label>
                    <div class="col-sm-4 col-md-3">
                        <select class="configKey form-control" data-l1key="brand">
                            <option value="2">Hyundai (Bluelink)</option>
                            <option value="1">Kia (UVO / Connect)</option>
                            <option value="3">Genesis</option>
                        </select>
                    </div>
                </div>

                <!-- Région -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Région}}
                    </label>
                    <div class="col-sm-4 col-md-3">
                        <select class="configKey form-control" data-l1key="region">
                            <option value="1">Europe</option>
                            <option value="2">Canada</option>
                            <option value="3">USA</option>
                            <option value="4">Chine</option>
                            <option value="5">Australie</option>
                            <option value="6">Nouvelle-Zélande</option>
                        </select>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Identifiant (e-mail)}}
                        <sup>
                            <i class="fas fa-question-circle tooltips"
                               title="{{Adresse e-mail de votre compte Bluelink ou Kia Connect}}">
                            </i>
                        </sup>
                    </label>
                    <div class="col-sm-5 col-md-4">
                        <input type="text"
                               class="configKey form-control"
                               data-l1key="username"
                               placeholder="votre@email.com"
                               autocomplete="off" />
                    </div>
                </div>

                <!-- Mot de passe -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Mot de passe}}
                    </label>
                    <div class="col-sm-5 col-md-4">
                        <div class="input-group">
                            <input type="password"
                                   class="configKey form-control"
                                   data-l1key="password"
                                   id="input_password"
                                   autocomplete="new-password" />
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" id="bt_togglePassword"
                                        title="{{Afficher / masquer}}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- PIN -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{PIN}}
                        <sup>
                            <i class="fas fa-question-circle tooltips"
                               title="{{Requis uniquement pour Canada / USA. Laisser vide pour l'Europe.}}">
                            </i>
                        </sup>
                    </label>
                    <div class="col-sm-2 col-md-2">
                        <input type="text"
                               class="configKey form-control"
                               data-l1key="pin"
                               placeholder="{{Vide si Europe}}"
                               maxlength="6"
                               pattern="[0-9]*" />
                    </div>
                </div>

            </fieldset>

            <!-- ============================================================
                 Bloc 2 – Paramètres démon
                 ============================================================ -->
            <fieldset>
                <legend>
                    <i class="fas fa-cog"></i>
                    {{Paramètres du démon}}
                </legend>

                <!-- Cycle -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Cycle de rafraîchissement (min)}}
                        <sup>
                            <i class="fas fa-question-circle tooltips"
                               title="{{Minimum conseillé : 15 min. En dessous, risque de blocage du compte par l'API.}}">
                            </i>
                        </sup>
                    </label>
                    <div class="col-sm-2 col-md-2">
                        <input type="number"
                               class="configKey form-control"
                               data-l1key="cycle"
                               min="5" max="120"
                               placeholder="30" />
                    </div>
                </div>

                <!-- Port socket -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Port socket interne}}
                        <sup>
                            <i class="fas fa-question-circle tooltips"
                               title="{{Port TCP local PHP↔Python. Changer uniquement en cas de conflit.}}">
                            </i>
                        </sup>
                    </label>
                    <div class="col-sm-2 col-md-2">
                        <input type="number"
                               class="configKey form-control"
                               data-l1key="socketport"
                               min="1024" max="65535"
                               placeholder="55987" />
                    </div>
                </div>

                <!-- Température clim -->
                <div class="form-group">
                    <label class="col-sm-4 col-md-3 control-label">
                        {{Température clim. par défaut (°C)}}
                    </label>
                    <div class="col-sm-2 col-md-2">
                        <input type="number"
                               class="configKey form-control"
                               data-l1key="default_climate_temp"
                               min="16" max="30" step="0.5"
                               placeholder="22" />
                    </div>
                </div>

            </fieldset>

            <!-- ============================================================
                 Bouton Sauvegarder  ← OBLIGATOIRE en Jeedom 4
                 Le core détecte ce bouton via la classe "savePluginConfig"
                 ============================================================ -->
            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8 col-md-offset-3 col-md-9">
                    <a class="btn btn-success savePluginConfig">
                        <i class="fas fa-check-circle"></i>
                        {{Sauvegarder}}
                    </a>
                    <span class="btn btn-default" id="bt_testConnection" style="margin-left:10px;">
                        <i class="fas fa-plug"></i>
                        {{Tester la connexion}}
                    </span>
                </div>
            </div>

            <!-- Lien vers la page équipements -->
            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8 col-md-offset-3 col-md-9">
                    <a class="btn btn-default"
                       href="index.php?v=d&m=hyundaikia&p=hyundaikia">
                        <i class="fas fa-car"></i>
                        {{Gérer mes véhicules}}
                    </a>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
/* Afficher / masquer le mot de passe */
$('#bt_togglePassword').on('click', function () {
    var inp = $('#input_password');
    if (inp.attr('type') === 'password') {
        inp.attr('type', 'text');
        $(this).find('i').removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        inp.attr('type', 'password');
        $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
    }
});

/* Bouton "Tester la connexion" – lance un scan rapide et affiche le résultat */
$('#bt_testConnection').on('click', function () {
    var btn = $(this);
    btn.prop('disabled', true)
       .html('<i class="fas fa-spinner fa-spin"></i> {{Test en cours…}}');

    $.ajax({
        type    : 'POST',
        url     : 'core/ajax/hyundaikia.ajax.php',
        data    : { action: 'scanVehicles' },
        dataType: 'json',
        global  : false,
        success : function (data) {
            btn.prop('disabled', false)
               .html('<i class="fas fa-plug"></i> {{Tester la connexion}}');
            if (data.state === 'ok' && data.result && data.result.length > 0) {
                var names = data.result.map(function(v){ return v.name || v.vin; }).join(', ');
                $.fn.showAlert({
                    message : '{{Connexion réussie – véhicule(s) : }}' + names,
                    level   : 'success'
                });
            } else if (data.state === 'ok') {
                $.fn.showAlert({
                    message : '{{Connexion réussie mais aucun véhicule trouvé.}}',
                    level   : 'warning'
                });
            } else {
                $.fn.showAlert({ message : data.result, level : 'danger' });
            }
        },
        error: function (xhr) {
            btn.prop('disabled', false)
               .html('<i class="fas fa-plug"></i> {{Tester la connexion}}');
            $.fn.showAlert({
                message : '{{Erreur : }}' + xhr.responseText,
                level   : 'danger'
            });
        }
    });
});
</script>

<?php
/* OBLIGATOIRE – charge le JS du core qui lit/écrit les configKey */
include_file('core', 'plugin.config.template', 'js');
?>
