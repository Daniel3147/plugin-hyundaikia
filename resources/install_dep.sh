#!/bin/bash
# install_dep.sh - Installation des dépendances pour le plugin hyundaikia
# Compatible Debian 10/11/12, Python 3.8 à 3.12+

PROGRESS_FILE="$1"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG() { echo "[hyundaikia] $*"; }

# Créer le fichier de progression (Jeedom le surveille)
[ -n "$PROGRESS_FILE" ] && mkdir -p "$(dirname "$PROGRESS_FILE")" && touch "$PROGRESS_FILE"

LOG "=== Début installation dépendances ==="
LOG "Plugin dir : $PLUGIN_DIR"

# ── 1. Trouver Python 3 ────────────────────────────────────────────────────────
PYTHON_CMD=""
for cmd in python3 python3.12 python3.11 python3.10 python3.9 python3.8; do
    if command -v "$cmd" &>/dev/null; then
        VER=$("$cmd" -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')" 2>/dev/null)
        MAJ=$(echo "$VER" | cut -d. -f1)
        MIN=$(echo "$VER" | cut -d. -f2)
        if [ "$MAJ" -eq 3 ] && [ "$MIN" -ge 8 ]; then
            PYTHON_CMD="$cmd"
            LOG "Python trouvé : $PYTHON_CMD ($VER)"
            break
        fi
    fi
done

if [ -z "$PYTHON_CMD" ]; then
    LOG "ERREUR : Python 3.8+ introuvable. Installation..."
    apt-get install -y python3 python3-pip python3-venv 2>&1 | grep -E "(Inst|Err|erreur)"
    PYTHON_CMD="python3"
fi

# ── 2. S'assurer que pip et venv sont disponibles ─────────────────────────────
LOG "Vérification pip et venv..."
if ! "$PYTHON_CMD" -m pip --version &>/dev/null; then
    LOG "pip absent, installation via apt..."
    apt-get install -y python3-pip 2>&1 | grep -E "(Inst|Err|erreur)"
fi

if ! "$PYTHON_CMD" -m venv --help &>/dev/null 2>&1; then
    LOG "venv absent, installation via apt..."
    apt-get install -y python3-venv 2>&1 | grep -E "(Inst|Err|erreur)"
    # Debian 12 : paquet versionné
    PYVER=$("$PYTHON_CMD" -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')")
    apt-get install -y "python${PYVER}-venv" 2>&1 | grep -E "(Inst|Err|erreur)" || true
fi

# ── 3. Créer/réutiliser le virtualenv dans resources/venv ────────────────────
VENV_DIR="$PLUGIN_DIR/venv"
LOG "Création virtualenv : $VENV_DIR"

if [ -d "$VENV_DIR" ]; then
    LOG "Virtualenv existant, suppression pour réinstallation propre..."
    rm -rf "$VENV_DIR"
fi

"$PYTHON_CMD" -m venv "$VENV_DIR"
if [ ! -f "$VENV_DIR/bin/python3" ]; then
    LOG "ERREUR : impossible de créer le virtualenv !"
    [ -n "$PROGRESS_FILE" ] && rm -f "$PROGRESS_FILE"
    exit 1
fi
LOG "Virtualenv créé : OK"

# ── 4. Mettre à jour pip dans le venv ─────────────────────────────────────────
LOG "Mise à jour pip dans le venv..."
"$VENV_DIR/bin/pip" install --upgrade pip --quiet 2>&1 | tail -1

# ── 5. Installer hyundai_kia_connect_api ──────────────────────────────────────
LOG "Installation de hyundai_kia_connect_api..."
"$VENV_DIR/bin/pip" install hyundai_kia_connect_api 2>&1
RC=$?
if [ $RC -ne 0 ]; then
    LOG "ERREUR : échec installation hyundai_kia_connect_api (code $RC)"
    [ -n "$PROGRESS_FILE" ] && rm -f "$PROGRESS_FILE"
    exit 1
fi

# ── 6. Vérification finale ─────────────────────────────────────────────────────
LOG "Vérification de l'import..."
"$VENV_DIR/bin/python3" -c "
from hyundai_kia_connect_api import VehicleManager
print('hyundai_kia_connect_api : OK')
import hyundai_kia_connect_api
print('Version :', getattr(hyundai_kia_connect_api, '__version__', 'inconnue'))
"
RC=$?
if [ $RC -ne 0 ]; then
    LOG "ERREUR : vérification import échouée !"
    [ -n "$PROGRESS_FILE" ] && rm -f "$PROGRESS_FILE"
    exit 1
fi

# ── 7. Permissions sur le script Python ───────────────────────────────────────
chmod +x "$PLUGIN_DIR/hyundaikia.py"

LOG "=== Installation terminée avec succès ==="
[ -n "$PROGRESS_FILE" ] && rm -f "$PROGRESS_FILE"
exit 0
