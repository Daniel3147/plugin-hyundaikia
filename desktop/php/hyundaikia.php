<?php
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}
/* @var $eqLogic hyundaikia */
?>

<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend>
            <i class="fas fa-car-side"></i> <?php echo __('Mes véhicules', __FILE__); ?>
            <a class="btn btn-default btn-xs pull-right" id="bt_addHyundaiKia">
                <i class="fas fa-plus-circle"></i> <?php echo __('Rechercher des véhicules', __FILE__); ?>
            </a>
        </legend>
        <div class="input-group" style="margin:5px 0 10px 0;">
            <input class="form-control roundedLeft" placeholder="<?php echo __('Recherche...', __FILE__); ?>"
                   id="in_searchEqLogic" />
            <div class="input-group-btn roundedRight">
                <a class="btn btn-default" id="bt_resetSearch">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
        <?php
        foreach (eqLogic::byType('hyundaikia') as $eqLogic) {
            $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
            echo '<div class="col-md-4 col-sm-6 thumbnailEqLogic ' . $opacity . '" data-eqlogic_id="' . $eqLogic->getId() . '">';
            echo '<div class="thumbnail cursor">';
            echo '<img src="plugins/hyundaikia/plugin_info/hyundaikia_icon.png" height="75" />';
            echo '<div class="caption">';
            echo '<strong>' . $eqLogic->getHumanName(true) . '</strong>';
            echo '<br/>';
            $model = $eqLogic->getConfiguration('vehicle_model', '');
            $year = $eqLogic->getConfiguration('vehicle_year', '');
            echo '<small>' . $model . ' ' . $year . '</small>';
            $isEv = $eqLogic->getConfiguration('is_ev', 0);
            $isPhev = $eqLogic->getConfiguration('is_phev', 0);
            if ($isEv) echo '<br/><span class="label label-success">EV</span>';
            elseif ($isPhev) echo '<br/><span class="label label-info">PHEV</span>';
            else echo '<br/><span class="label label-default">Thermique</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>

    <div class="col-xs-12 eqLogic" style="display:none;">
        <div class="input-group pull-right" style="display:inline-flex;">
            <a class="btn btn-default btn-sm eqLogicAction" data-action="configure">
                <i class="fa fa-cogs"></i>
            </a>
            <a class="btn btn-default btn-sm eqLogicAction" data-action="copy">
                <i class="fa fa-copy"></i>
            </a>
            <a class="btn btn-sm btn-danger eqLogicAction" data-action="remove">
                <i class="fas fa-minus-circle"></i> <?php echo __('Supprimer', __FILE__); ?>
            </a>
            <a class="btn btn-sm btn-success eqLogicAction pull-right" data-action="save">
                <i class="fas fa-check-circle"></i> <?php echo __('Sauvegarder', __FILE__); ?>
            </a>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab">
                    <i class="fas fa-car"></i> <?php echo __('Équipement', __FILE__); ?>
                </a>
            </li>
            <li role="presentation">
                <a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab">
                    <i class="fas fa-list-alt"></i> <?php echo __('Commandes', __FILE__); ?>
                </a>
            </li>
        </ul>
        <div class="tab-content">

            <!-- ONGLET ÉQUIPEMENT -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <div class="col-lg-6">
                    <form class="form-horizontal">
                        <fieldset>
                            <div class="form-group">
                                <legend><i class="fas fa-info-circle"></i> <?php echo __('Informations générales', __FILE__); ?></legend>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Nom de l\'équipement', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name"
                                           placeholder="<?php echo __('Nom du véhicule', __FILE__); ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Objet parent', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <?php
                                    $options = '<option value="">Aucun</option>';
                                    foreach (jeeObject::all() as $object) {
                                        $options .= '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                                    }
                                    echo '<select class="eqLogicAttr form-control" data-l1key="object_id">' . $options . '</select>';
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Activé', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Visible', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>

                <!-- Infos véhicule (lecture seule) -->
                <div class="col-lg-6">
                    <form class="form-horizontal">
                        <fieldset>
                            <div class="form-group">
                                <legend><i class="fas fa-car"></i> <?php echo __('Informations véhicule', __FILE__); ?></legend>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('ID Véhicule', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                                           data-l2key="vehicle_id" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Modèle', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                                           data-l2key="vehicle_model" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Année', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                                           data-l2key="vehicle_year" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('VIN', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                                           data-l2key="vehicle_vin" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-4 control-label"><?php echo __('Type', __FILE__); ?></label>
                                <div class="col-lg-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                                           data-l2key="is_ev" readonly
                                           placeholder="<?php echo __('0=Thermique, 1=EV/PHEV', __FILE__); ?>"/>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>

            <!-- ONGLET COMMANDES -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <br/>
                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th><?php echo __('ID', __FILE__); ?></th>
                            <th><?php echo __('Nom', __FILE__); ?></th>
                            <th><?php echo __('Type', __FILE__); ?></th>
                            <th><?php echo __('Sous-type', __FILE__); ?></th>
                            <th><?php echo __('Unité', __FILE__); ?></th>
                            <th><?php echo __('Valeur', __FILE__); ?></th>
                            <th><?php echo __('Visible', __FILE__); ?></th>
                            <th><?php echo __('Historique', __FILE__); ?></th>
                            <th><?php echo __('Actions', __FILE__); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tbody_cmd">
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include_file('desktop', 'hyundaikia', 'js', 'hyundaikia'); ?>
