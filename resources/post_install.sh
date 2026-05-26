#!/bin/bash
# Force l'installation de la version >= 4.14.1 de hyundai_kia_connect_api
# Ce script est nécessaire car Jeedom ne supporte pas la syntaxe >= dans packages.json

echo "Installation de hyundai_kia_connect_api >= 4.14.1 ..."
pip3 install "hyundai_kia_connect_api>=4.14.1" --upgrade 2>&1
if [ $? -eq 0 ]; then
    echo "hyundai_kia_connect_api installé avec succès"
    pip3 show hyundai_kia_connect_api | grep Version
else
    echo "ERREUR lors de l'installation de hyundai_kia_connect_api"
    exit 1
fi
