<?php
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}

function hyundaikia_install() {
    // Installation du plugin
}

function hyundaikia_update() {
    // Mise à jour du plugin
}

function hyundaikia_remove() {
    // Suppression du plugin
}
