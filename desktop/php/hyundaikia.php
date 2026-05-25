<?php
/* Page principale de gestion des équipements
 * Affichée dans Plugins > Domotique > Hyundai/Kia Connect
 */

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$pluginVersion = plugin::byId('hyundaikia')->getVersion();
?>

<div class="row row-overflow">
    <!-- Panneau gauche : liste des équipements -->
    <div class="col-xs-12 col-sm-12 col-md-2 col-lg-2 eqLogicThumbnailDisplay">
        <legend>
            <i class="fas fa-car"></i> {{Mes véhicules}}
        </legend>
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach (eqLogic::byType('hyundaikia') as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'opacity:0.4;';
                echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color:#ffffff;' . $opacity . '">';
                echo '<img src="plugins/hyundaikia/desktop/img/hyundaikia.png" style="height:80px;"/>';
                echo '<br><span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
        <a class="btn btn-default btn-sm eqLogicAction" data-action="add" style="margin-top:5px;">
            <i class="fas fa-plus-circle"></i> {{Ajouter un véhicule manuellement}}
        </a>
    </div>

    <!-- Panneau droit : configuration d'un équipement -->
    <div class="col-xs-12 col-sm-12 col-md-10 col-lg-10 eqLogic" style="display:none;">
        <div class="input-group pull-right" style="display:inline-flex">
            <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure">
                <i class="fas fa-cogs"></i> {{Configuration avancée}}
            </a>
            <a class="btn btn-sm btn-success eqLogicAction" data-action="save">
                <i class="fas fa-check-circle"></i> {{Sauvegarder}}
            </a>
            <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove">
                <i class="fas fa-minus-circle"></i> {{Supprimer}}
            </a>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab">
                    <i class="fas fa-car"></i> {{Équipement}}
                </a>
            </li>
            <li role="presentation">
                <a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab">
                    <i class="fas fa-list"></i> {{Commandes}}
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Onglet équipement -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <div class="col-xs-12 col-sm-7">
                    <form class="form-horizontal">
                        <fieldset>
                            <legend>{{Informations générales}}</legend>
                            <?php
                            echo eqLogic::getHTMLInput(array('eqLogic_id' => init('id')));
                            ?>
                        </fieldset>
                    </form>
                </div>

                <div class="col-xs-12 col-sm-5">
                    <!-- Widget de statut rapide -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <i class="fas fa-tachometer-alt"></i> {{Statut rapide}}
                        </div>
                        <div class="panel-body" id="hyundaikia_status">
                            <em>{{Sélectionnez un équipement pour voir son statut}}</em>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet commandes -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;">
                    <i class="fas fa-plus-circle"></i> {{Ajouter une commande}}
                </a>
                <br/>
                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>{{#}}</th>
                            <th>{{Nom}}</th>
                            <th>{{Type}}</th>
                            <th>{{Valeur}}</th>
                            <th>{{Unité}}</th>
                            <th>{{Historiser}}</th>
                            <th>{{Afficher}}</th>
                            <th>{{Actions}}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
