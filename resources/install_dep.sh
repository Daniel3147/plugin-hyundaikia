#!/bin/bash
# install_dep.sh - Installation des dépendances pour le plugin hyundaikia

PROGRESS_FILE=$1
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Créer le dossier tmp si nécessaire
mkdir -p "$(dirname "$PROGRESS_FILE")"
touch "$PROGRESS_FILE"

echo "=== Installation des dépendances hyundaikia ===" >&2

# Détecter la version de Python
PYTHON_CMD=""
for cmd in python3.11 python3.10 python3.9 python3.8 python3; do
    if command -v "$cmd" &>/dev/null; then
        PYTHON_CMD="$cmd"
        echo "Python trouvé: $PYTHON_CMD ($(${PYTHON_CMD} --version 2>&1))" >&2
        break
    fi
done

if [ -z "$PYTHON_CMD" ]; then
    echo "ERREUR: Python3 non trouvé!" >&2
    rm -f "$PROGRESS_FILE"
    exit 1
fi

# Détecter pip
PIP_CMD=""
for cmd in pip3 pip; do
    if command -v "$cmd" &>/dev/null; then
        PIP_CMD="$cmd"
        echo "pip trouvé: $PIP_CMD" >&2
        break
    fi
done

if [ -z "$PIP_CMD" ]; then
    echo "pip non trouvé, installation..." >&2
    apt-get install -y python3-pip 2>&1
    PIP_CMD="pip3"
fi

# Vérifier la version Python pour choisir la méthode d'installation
PYTHON_VERSION=$($PYTHON_CMD -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')" 2>/dev/null)
echo "Version Python: $PYTHON_VERSION" >&2

# Fonction d'installation avec gestion des différences Python 3.11+ (PEP 668)
install_package() {
    local package="$1"
    echo "Installation de $package..." >&2
    
    # Python 3.11+ sur Debian 12 nécessite --break-system-packages ou venv
    if $PYTHON_CMD -c "import sys; exit(0 if sys.version_info >= (3,11) else 1)" 2>/dev/null; then
        # Essayer d'abord sans flag (peut fonctionner si pas de protection)
        if $PIP_CMD install "$package" --quiet 2>/dev/null; then
            echo "$package installé (pip standard)" >&2
            return 0
        fi
        # Sinon utiliser --break-system-packages (Debian 12)
        if $PIP_CMD install "$package" --break-system-packages --quiet 2>&1; then
            echo "$package installé (--break-system-packages)" >&2
            return 0
        fi
        # En dernier recours, utiliser un venv
        echo "Utilisation d'un environnement virtuel..." >&2
        VENV_DIR="$PLUGIN_DIR/venv"
        if [ ! -d "$VENV_DIR" ]; then
            $PYTHON_CMD -m venv "$VENV_DIR" 2>&1
        fi
        "$VENV_DIR/bin/pip" install "$package" --quiet 2>&1
        # Créer un wrapper pour utiliser le venv
        cat > "$PLUGIN_DIR/hyundaikia_wrapper.sh" << EOF
#!/bin/bash
"$VENV_DIR/bin/python3" "$PLUGIN_DIR/hyundaikia.py" "\$@"
EOF
        chmod +x "$PLUGIN_DIR/hyundaikia_wrapper.sh"
        echo "$package installé dans venv" >&2
        return 0
    else
        # Python < 3.11, installation standard
        $PIP_CMD install "$package" --quiet 2>&1
        echo "$package installé" >&2
        return 0
    fi
}

# Installer les dépendances
echo "--- Installation de hyundai_kia_connect_api ---" >&2
install_package "hyundai_kia_connect_api"

# Vérifier l'installation
echo "--- Vérification ---" >&2
if $PYTHON_CMD -c "from hyundai_kia_connect_api import VehicleManager; print('OK')" 2>/dev/null; then
    echo "hyundai_kia_connect_api installé avec succès!" >&2
elif [ -d "$PLUGIN_DIR/venv" ] && "$PLUGIN_DIR/venv/bin/python3" -c "from hyundai_kia_connect_api import VehicleManager; print('OK')" 2>/dev/null; then
    echo "hyundai_kia_connect_api installé dans venv avec succès!" >&2
else
    echo "ERREUR: Impossible de vérifier l'installation!" >&2
    rm -f "$PROGRESS_FILE"
    exit 1
fi

echo "=== Installation terminée ===" >&2
rm -f "$PROGRESS_FILE"
exit 0
