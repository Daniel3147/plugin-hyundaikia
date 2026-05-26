<?php
/* This file is part of Jeedom.
 *
 * Configuration globale du plugin Hyundai/Kia Connect
 * DOIT être dans plugin_info/ — c'est là que Jeedom 4 le cherche.
 * Le core charge automatiquement le JS qui lit/écrit les configKey.
 * Ne pas ajouter de require, de bouton save, ni d'include JS ici.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>

<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-user-lock"></i> {{Identifiants Hyundai / Kia Connect}}</legend>

        <!-- Marque -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Marque}}
            </label>
            <div class="col-md-4">
                <select class="configKey form-control" data-l1key="brand">
                    <option value="2">Hyundai (Bluelink)</option>
                    <option value="1">Kia (UVO / Connect)</option>
                    <option value="3">Genesis</option>
                </select>
            </div>
        </div>

        <!-- Région -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Région}}
            </label>
            <div class="col-md-4">
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
            <label class="col-md-4 control-label">
                {{Identifiant (e-mail)}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Adresse e-mail de votre compte Bluelink ou Kia Connect}}"></i></sup>
            </label>
            <div class="col-md-4">
                <input type="text"
                       class="configKey form-control"
                       data-l1key="username"
                       placeholder="votre@email.com"
                       autocomplete="off" />
            </div>
        </div>

        <!-- Mot de passe -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Mot de passe}}
            </label>
            <div class="col-md-4">
                <input type="password"
                       class="configKey form-control"
                       data-l1key="password"
                       autocomplete="new-password" />
            </div>
        </div>

        <!-- PIN -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{PIN}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Requis uniquement pour Canada / USA. Laisser vide pour l'Europe.}}"></i></sup>
            </label>
            <div class="col-md-2">
                <input type="text"
                       class="configKey form-control"
                       data-l1key="pin"
                       placeholder="{{Vide si Europe}}"
                       maxlength="6" />
            </div>
        </div>

    </fieldset>

    <fieldset>
        <legend><i class="fas fa-cog"></i> {{Paramètres du démon}}</legend>

        <!-- Cycle -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Cycle de rafraîchissement (minutes)}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Minimum conseillé : 15 min. En dessous, risque de blocage du compte par l'API.}}"></i></sup>
            </label>
            <div class="col-md-2">
                <input type="number"
                       class="configKey form-control"
                       data-l1key="cycle"
                       min="5" max="120"
                       placeholder="30" />
            </div>
        </div>

        <!-- Port socket -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Port socket interne}}
                <sup><i class="fas fa-question-circle tooltips"
                        title="{{Port TCP local PHP↔Python. Changer uniquement en cas de conflit de port.}}"></i></sup>
            </label>
            <div class="col-md-2">
                <input type="number"
                       class="configKey form-control"
                       data-l1key="socketport"
                       min="1024" max="65535"
                       placeholder="55987" />
            </div>
        </div>

        <!-- Température clim -->
        <div class="form-group">
            <label class="col-md-4 control-label">
                {{Température climatisation par défaut (°C)}}
            </label>
            <div class="col-md-2">
                <input type="number"
                       class="configKey form-control"
                       data-l1key="default_climate_temp"
                       min="16" max="30" step="0.5"
                       placeholder="22" />
            </div>
        </div>

    </fieldset>
</form>
