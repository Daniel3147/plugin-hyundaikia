<?php
/* Configuration globale du plugin - Compatible Jeedom 4.x
 * Accessible via : Plugins > Gestion des plugins > Hyundai/Kia > Configuration
 */
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-user-lock"></i> {{Identifiants Hyundai / Kia Connect}}</legend>

        <div class="form-group">
            <label class="col-sm-4 control-label">{{Marque}}</label>
            <div class="col-sm-4">
                <select class="configKey form-control" data-l1key="brand">
                    <option value="2">Hyundai (Bluelink)</option>
                    <option value="1">Kia (UVO / Connect)</option>
                    <option value="3">Genesis</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-4 control-label">{{Région}}</label>
            <div class="col-sm-4">
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

        <div class="form-group">
            <label class="col-sm-4 control-label">
                {{Identifiant (adresse e-mail)}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Adresse e-mail utilisée pour vous connecter à l'application Bluelink ou Kia Connect}}"></i></sup>
            </label>
            <div class="col-sm-4">
                <input type="email" class="configKey form-control"
                       data-l1key="username"
                       placeholder="votre@email.com"
                       autocomplete="off"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-4 control-label">{{Mot de passe}}</label>
            <div class="col-sm-4">
                <input type="password" class="configKey form-control inputPassword"
                       data-l1key="password"
                       autocomplete="new-password"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-4 control-label">
                {{PIN}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Requis uniquement pour le Canada et certains comptes USA. Laisser vide pour l'Europe.}}"></i></sup>
            </label>
            <div class="col-sm-2">
                <input type="text" class="configKey form-control"
                       data-l1key="pin"
                       placeholder="{{Laisser vide si non requis}}"
                       maxlength="6"
                       pattern="[0-9]*"/>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-cog"></i> {{Paramètres du démon}}</legend>

        <div class="form-group">
            <label class="col-sm-4 control-label">
                {{Cycle de rafraîchissement (minutes)}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Fréquence d'interrogation de l'API. Minimum recommandé : 15 min pour ne pas bloquer votre compte.}}"></i></sup>
            </label>
            <div class="col-sm-2">
                <input type="number" class="configKey form-control"
                       data-l1key="cycle"
                       min="5" max="120" value="30"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-4 control-label">
                {{Port socket interne}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Port TCP local utilisé pour la communication PHP↔Python. Modifier uniquement en cas de conflit.}}"></i></sup>
            </label>
            <div class="col-sm-2">
                <input type="number" class="configKey form-control"
                       data-l1key="socketport"
                       min="1024" max="65535" value="55987"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-4 control-label">{{Température clim. par défaut (°C)}}</label>
            <div class="col-sm-2">
                <input type="number" class="configKey form-control"
                       data-l1key="default_climate_temp"
                       min="16" max="30" step="0.5" value="22"/>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-car"></i> {{Gestion des véhicules}}</legend>
        <div class="form-group">
            <div class="col-sm-offset-4 col-sm-8">
                <a class="btn btn-primary" href="/index.php?v=d&m=hyundaikia&p=hyundaikia">
                    <i class="fas fa-car"></i> {{Aller à la gestion des véhicules}}
                </a>
            </div>
        </div>
    </fieldset>
</form>
