#!/bin/bash
# Installation de hyundai_kia_connect_api >= 4.14.1
# --break-system-packages est requis sur Debian 12+ / Ubuntu 23.04+
# (Python 3.12+ refuse d'installer sans ce flag en dehors d'un venv)

set -e

echo "[hyundaikia] Installation de hyundai_kia_connect_api >= 4.14.1 ..."

pip3 install \
    --upgrade \
    --break-system-packages \
    "hyundai_kia_connect_api>=4.14.1"

echo "[hyundaikia] Version installée :"
pip3 show hyundai_kia_connect_api | grep -E "^(Name|Version)"

echo "[hyundaikia] Installation jeedomdaemon ..."
pip3 install \
    --upgrade \
    --break-system-packages \
    jeedomdaemon

echo "[hyundaikia] Dépendances installées avec succès."
