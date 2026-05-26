/* Plugin Hyundai/Kia Connect – JS desktop (Jeedom 4.x) */
"use strict";

/* Construction dynamique d'une ligne de commande dans l'onglet Commandes */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) _cmd = {};
    if (!isset(_cmd.configuration)) _cmd.configuration = {};

    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td><span class="cmdAttr" data-l1key="id" style="display:none;"></span></td>';

    tr += '<td><div class="input-group">'
        + '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom}}" value="' + init(_cmd.name) + '"/>'
        + '</div></td>';

    tr += '<td><span class="cmdAttr" data-l1key="type">' + init(_cmd.type) + '</span>'
        + ' / <span class="cmdAttr" data-l1key="subType">' + init(_cmd.subType) + '</span></td>';

    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';

    tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="unite" style="width:55px;" value="' + init(_cmd.unite) + '"/></td>';

    tr += '<td><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" '
        + (init(_cmd.isHistorized) == 1 ? 'checked' : '') + '/></td>';

    tr += '<td><input type="checkbox" class="cmdAttr" data-l1key="isVisible" '
        + (init(_cmd.isVisible) == 1 ? 'checked' : '') + '/></td>';

    tr += '<td>'
        + '<a class="btn btn-default btn-sm cmdAction tooltips" data-action="configure" title="{{Configuration avancée}}"><i class="fas fa-cog"></i></a> '
        + '<a class="btn btn-sm btn-danger cmdAction tooltips" data-action="remove" title="{{Supprimer}}"><i class="fas fa-minus-circle"></i></a>'
        + '</td>';

    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
}

/* ============================================================
   Bouton "Rechercher les véhicules"
   ============================================================ */
$(document).on('click', '#bt_scanVehicles', function () {
    $('#div_scan_loading').show();
    $('#div_scan_result').hide();
    $('#div_scan_error').hide().text('');
    $('#bt_importVehicles').hide();
    $('#tbody_scan_result').empty();

    $('#modal_hyundaikia_scan').modal('show');

    $.ajax({
        type    : 'POST',
        url     : 'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
        data    : { action: 'scanVehicles' },
        dataType: 'json',
        global  : false,
        success : function (data) {
            $('#div_scan_loading').hide();
            if (data.state !== 'ok') {
                $('#div_scan_error').text(data.result).show();
                return;
            }
            var vehicles = data.result;
            if (!vehicles || vehicles.length === 0) {
                $('#div_scan_error').text('{{Aucun véhicule trouvé pour ce compte.}}').show();
                return;
            }

            /* Options objets Jeedom injectées depuis PHP via variable JS globale */
            var objectOptions = '<option value="">{{Aucun}}</option>';
            if (typeof hyundaikiaConfig !== 'undefined' && hyundaikiaConfig.jeedomObjects) {
                $.each(hyundaikiaConfig.jeedomObjects, function (i, o) {
                    objectOptions += '<option value="' + o.id + '">' + o.name + '</option>';
                });
            }

            $.each(vehicles, function (i, v) {
                var isNew = (typeof hyundaikiaConfig !== 'undefined'
                             && hyundaikiaConfig.existingVins.indexOf(v.vin) === -1);
                var badge = isNew
                    ? '<span class="label label-success">{{Nouveau}}</span>'
                    : '<span class="label label-default">{{Déjà importé}}</span>';

                var row = '<tr data-vin="' + v.vin + '">';
                row += '<td><input type="checkbox" class="scan_select" ' + (isNew ? 'checked' : '') + '></td>';
                row += '<td>' + escapeHtml(v.name) + ' ' + badge + '</td>';
                row += '<td>' + escapeHtml(v.model || '—') + '</td>';
                row += '<td><code>' + escapeHtml(v.vin) + '</code></td>';
                row += '<td><select class="scan_object form-control input-sm">' + objectOptions + '</select></td>';
                row += '</tr>';
                $('#tbody_scan_result').append(row);
            });

            $('#bt_importVehicles').data('vehicles', vehicles).show();
            $('#div_scan_result').show();
        },
        error: function (xhr) {
            $('#div_scan_loading').hide();
            $('#div_scan_error').text('{{Erreur AJAX : }}' + xhr.responseText).show();
        }
    });
});

/* ============================================================
   Bouton "Importer la sélection"
   ============================================================ */
$(document).on('click', '#bt_importVehicles', function () {
    var vehicles = $(this).data('vehicles');
    var toImport = [];

    $('#tbody_scan_result tr').each(function () {
        if ($(this).find('.scan_select').is(':checked')) {
            var vin     = $(this).data('vin');
            var objId   = $(this).find('.scan_object').val();
            var vehicle = $.grep(vehicles, function (v) { return v.vin === vin; })[0];
            if (vehicle) {
                toImport.push({ vin: vin, name: vehicle.name, model: vehicle.model || '', object_id: objId });
            }
        }
    });

    if (toImport.length === 0) {
        $.fn.showAlert({ message: '{{Aucun véhicule sélectionné.}}', level: 'warning' });
        return;
    }

    var done = 0, errors = [];

    function importNext() {
        if (done >= toImport.length) {
            $('#modal_hyundaikia_scan').modal('hide');
            if (errors.length > 0) {
                $.fn.showAlert({ message: '{{Erreur import : }}' + errors.join(', '), level: 'danger' });
            } else {
                $.fn.showAlert({ message: toImport.length + ' {{véhicule(s) importé(s) avec succès.}}', level: 'success' });
            }
            setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }
        var v = toImport[done];
        $.ajax({
            type    : 'POST',
            url     : 'plugins/hyundaikia/core/ajax/hyundaikia.ajax.php',
            data    : { action: 'importVehicle', vin: v.vin, name: v.name, model: v.model, object_id: v.object_id },
            dataType: 'json',
            global  : false,
            success : function (data) {
                if (data.state !== 'ok') errors.push(v.name + ': ' + data.result);
                done++; importNext();
            },
            error: function () { errors.push(v.name); done++; importNext(); }
        });
    }
    importNext();
});

/* Utilitaire XSS */
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
