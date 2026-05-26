<?php
/* Page principale de gestion des équipements - Compatible Jeedom 4.x */
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

// Données injectées en JS pour éviter le mélange PHP/JS dans le fichier .js
$jeedomObjects = [];
foreach (jeeObject::buildTree(null, false) as $object) {
    $jeedomObjects[] = [
        'id'   => $object->getId(),
        'name' => str_repeat('  ', $object->getConfiguration('parentNumber')) . $object->getName(),
    ];
}

$existingVins = [];
foreach (eqLogic::byType('hyundaikia') as $eq) {
    $existingVins[] = $eq->getLogicalId();
}
?>

<!-- Config PHP → JS -->
<script>
var hyundaikiaConfig = {
    jeedomObjects: <?php echo json_encode($jeedomObjects); ?>,
    existingVins : <?php echo json_encode($existingVins); ?>
};
</script>

<div class="row row-overflow">

    <!-- ===== Colonne gauche : liste des véhicules ===== -->
    <div class="col-xs-12 col-sm-12 col-md-2 col-lg-2 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-car"></i> {{Mes véhicules}}</legend>

        <div style="margin-bottom:8px;">
            <a class="btn btn-sm btn-success btn-block" id="bt_scanVehicles">
                <i class="fas fa-search"></i> {{Rechercher les véhicules}}
            </a>
        </div>

        <div class="eqLogicThumbnailContainer">
            <?php foreach (eqLogic::byType('hyundaikia') as $eqLogic) :
                $opacity = $eqLogic->getIsEnable() ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
            ?>
            <div class="eqLogicDisplayCard cursor"
                 data-eqLogic_id="<?php echo $eqLogic->getId(); ?>"
                 style="text-align:center;background:#fff;padding:10px;<?php echo $opacity; ?>">
                <i class="fas fa-car" style="font-size:3em;margin:10px 0;display:block;color:#3c8dbc;"></i>
                <span class="name"><?php echo $eqLogic->getHumanName(true, true); ?></span><br>
                <small class="label label-default" style="margin-top:5px;display:inline-block;">
                    <?php echo htmlspecialchars($eqLogic->getConfiguration('model', '')); ?>
                </small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== Colonne droite : configuration d'un équipement ===== -->
    <div class="col-xs-12 col-sm-12 col-md-10 col-lg-10 eqLogic" style="display:none;">
        <div class="input-group pull-right" style="display:inline-flex;">
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
                <a href="#eqlogictab" role="tab" data-toggle="tab">
                    <i class="fas fa-car"></i> {{Équipement}}
                </a>
            </li>
            <li role="presentation">
                <a href="#commandtab" role="tab" data-toggle="tab">
                    <i class="fas fa-list"></i> {{Commandes}}
                </a>
            </li>
        </ul>

        <div class="tab-content" style="padding-top:15px;">

            <!-- ---- Onglet Équipement ---- -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <legend>{{Informations générales}}</legend>

                        <input type="text" class="eqLogicAttr" data-l1key="id" style="display:none;">

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Nom}}</label>
                            <div class="col-sm-5">
                                <input type="text" class="eqLogicAttr form-control"
                                       data-l1key="name"
                                       placeholder="{{Nom de l'équipement}}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Objet parent}}</label>
                            <div class="col-sm-5">
                                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value="">{{Aucun}}</option>
                                    <?php
                                    foreach ($jeedomObjects as $o) {
                                        echo '<option value="' . $o['id'] . '">' . htmlspecialchars($o['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Catégorie}}</label>
                            <div class="col-sm-9">
                                <?php foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) : ?>
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="eqLogicAttr"
                                           data-l1key="category"
                                           data-l2key="<?php echo $key; ?>">
                                    <?php echo $value['name']; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Options}}</label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>
                                    {{Activer}}
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>
                                    {{Visible}}
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>{{Informations véhicule}}</legend>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{VIN}}</label>
                            <div class="col-sm-5">
                                <input type="text" class="eqLogicAttr form-control"
                                       data-l1key="logicalId"
                                       placeholder="{{VIN (rempli automatiquement)}}"
                                       readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Modèle}}</label>
                            <div class="col-sm-5">
                                <input type="text" class="eqLogicAttr form-control"
                                       data-l1key="configuration"
                                       data-l2key="model"
                                       placeholder="—"
                                       readonly>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>

            <!-- ---- Onglet Commandes ---- -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;">
                    <i class="fas fa-plus-circle"></i> {{Ajouter une commande}}
                </a>
                <br><br>
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

        </div><!-- .tab-content -->
    </div><!-- .eqLogic -->
</div><!-- .row -->

<!-- ===== Modal : Scan des véhicules ===== -->
<div class="modal fade" id="modal_hyundaikia_scan" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fas fa-search"></i> {{Rechercher les véhicules}}</h4>
            </div>
            <div class="modal-body">

                <div id="div_scan_loading" class="text-center" style="padding:40px;">
                    <i class="fas fa-spinner fa-spin fa-3x"></i><br><br>
                    <span>{{Connexion à l'API Hyundai/Kia en cours…}}</span>
                </div>

                <div id="div_scan_result" style="display:none;">
                    <div class="alert alert-info">
                        {{Véhicules trouvés. Sélectionnez l'objet Jeedom pour chaque véhicule à importer.}}
                    </div>
                    <table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th style="width:40px;">{{Import}}</th>
                                <th>{{Nom}}</th>
                                <th>{{Modèle}}</th>
                                <th>{{VIN}}</th>
                                <th>{{Objet Jeedom}}</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_scan_result"></tbody>
                    </table>
                </div>

                <div id="div_scan_error" class="alert alert-danger" style="display:none;"></div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fas fa-times"></i> {{Fermer}}
                </button>
                <button type="button" class="btn btn-success" id="bt_importVehicles" style="display:none;">
                    <i class="fas fa-download"></i> {{Importer la sélection}}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var eqType='hyundaikia';
</script>
<?php include_file('core', 'plugin.template', 'js'); ?>
<?php include_file('desktop', 'hyundaikia', 'js', 'hyundaikia'); ?>
