/* Plugin Hyundai/Kia Connect - JS Desktop */

/* ===================== CHARGEMENT COMMANDES ===================== */
addCmdToTable = function (_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    var tr = '';
    tr += '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs">';
    tr += '<span class="cmdAttr" data-l1key="id">' + init(_cmd.id) + '</span>';
    tr += '</td>';

    // Nom
    tr += '<td>';
    tr += '<div class="input-group">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" value="' + init(_cmd.name) + '"/>';
    tr += '<span class="input-group-btn">';
    tr += '<a class="btn btn-default btn-sm cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a>';
    tr += '</span>';
    tr += '</div>';
    tr += '</td>';

    // Type
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="humanName">' + init(_cmd.humanName) + '</span>';
    tr += '</td>';

    // Sous-type
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="humanSubType">' + init(_cmd.humanSubType) + '</span>';
    tr += '</td>';

    // Unité
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="unite">' + init(_cmd.unite) + '</span>';
    tr += '</td>';

    // Valeur actuelle
    tr += '<td>';
    if (_cmd.type == 'info') {
        tr += '<span class="cmdAttr" data-l1key="currentValue">' + init(_cmd.currentValue) + '</span>';
    } else {
        tr += '-';
    }
    tr += '</td>';

    // Visible
    tr += '<td>';
    tr += '<input type="checkbox" class="cmdAttr" data-l1key="isVisible" ';
    if (init(_cmd.isVisible) == '1') tr += 'checked';
    tr += '/>';
    tr += '</td>';

    // Historique
    tr += '<td>';
    tr += '<input type="checkbox" class="cmdAttr" data-l1key="isHistorized" ';
    if (init(_cmd.isHistorized) == '1') tr += 'checked';
    tr += '/>';
    tr += '</td>';

    // Actions
    tr += '<td>';
    if (_cmd.type == 'info') {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="execCmd" title="Tester">';
        tr += '<i class="fas fa-play"></i>';
        tr += '</a>';
    }
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure" title="Configurer">';
    tr += '<i class="fas fa-cogs"></i>';
    tr += '</a>';
    tr += '</td>';

    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
};

/* ===================== INIT PAGE ===================== */
$(function () {
    // Charger les équipements au clic sur un thumbnail
    $('.eqLogicThumbnailDisplay').on('click', '.thumbnailEqLogic', function () {
        var eqLogic_id = $(this).data('eqlogic_id');
        jeedom.eqLogic.get({
            id: eqLogic_id,
            error: function (error) {
                $.fn.showAlert({ message: error.message, level: 'danger' });
            },
            success: function (data) {
                jeedomUtils.setConfigPanelValues(data);
                $('.eqLogicThumbnailDisplay').hide();
                $('.eqLogic').show();
                // Charger les commandes
                jeedom.cmd.getForEqLogic({
                    id: eqLogic_id,
                    error: function (error) {
                        $.fn.showAlert({ message: error.message, level: 'danger' });
                    },
                    success: function (cmds) {
                        $('#table_cmd tbody').empty();
                        for (var i = 0; i < cmds.length; i++) {
                            addCmdToTable(cmds[i]);
                        }
                    }
                });
            }
        });
    });

    // Bouton recherche véhicules -> redirection vers config plugin
    $('#bt_addHyundaiKia').on('click', function () {
        window.location = 'index.php?v=d&p=plugin&id=hyundaikia';
    });

    // Sauvegarde
    $('.eqLogicAction[data-action=save]').on('click', function () {
        var eqLogic = jeedomUtils.getConfigPanelValues('.eqLogicAttr');
        jeedom.eqLogic.save({
            type: 'hyundaikia',
            eqLogic: eqLogic,
            error: function (error) {
                $.fn.showAlert({ message: error.message, level: 'danger' });
            },
            success: function (data) {
                $.fn.showAlert({ message: '{{Sauvegardé}}', level: 'success' });
                location.reload();
            }
        });
    });

    // Suppression
    $('.eqLogicAction[data-action=remove]').on('click', function () {
        bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer cet équipement ?}}', function (result) {
            if (result) {
                var eqLogic_id = $('.eqLogicAttr[data-l1key=id]').value();
                jeedom.eqLogic.remove({
                    id: eqLogic_id,
                    error: function (error) {
                        $.fn.showAlert({ message: error.message, level: 'danger' });
                    },
                    success: function () {
                        location.reload();
                    }
                });
            }
        });
    });

    // Retour liste
    $('.eqLogicAction[data-action=returnToThumbnailDisplay]').on('click', function () {
        $('.eqLogic').hide();
        $('.eqLogicThumbnailDisplay').show();
    });

    // Recherche dans la liste
    $('#in_searchEqLogic').on('keyup', function () {
        var search = $(this).val().toLowerCase();
        $('.thumbnailEqLogic').each(function () {
            var name = $(this).find('.caption strong').text().toLowerCase();
            $(this).toggle(name.indexOf(search) >= 0);
        });
    });

    $('#bt_resetSearch').on('click', function () {
        $('#in_searchEqLogic').val('').trigger('keyup');
    });
});
