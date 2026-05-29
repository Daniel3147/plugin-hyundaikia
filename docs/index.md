# Plugin Jeedom - Hyundai/Kia Connect

Plugin permettant de contrôler et surveiller vos véhicules **Hyundai**, **Kia** et **Genesis** directement depuis Jeedom via l'API BlueLink / UVO Connect.

## Prérequis

- Jeedom v4.5+
- Debian 12 (Bookworm)
- Python 3.9 ou supérieur
- Un compte Bluelink (Hyundai) ou UVO Connect (Kia) actif
- L'application Bluelink/UVO connectée au véhicule

## Installation

### 1. Installer le plugin

Via le Market Jeedom ou en copiant le dossier dans `/var/www/html/plugins/hyundaikia/`.

### 2. Installer les dépendances

Depuis **Plugins > Gestion des plugins > hyundaikia**, cliquer sur **Installer les dépendances**.

> **Note Debian 12 / Python 3.11+** : Sur Debian 12, Python 3.11 est la version par défaut et utilise PEP 668 qui protège les packages système. Le script d'installation gère automatiquement cette situation en utilisant `--break-system-packages` ou un environnement virtuel Python selon la configuration.

### 3. Configuration

Aller dans **Plugins > Automatisation > Hyundai/Kia Connect** ou depuis la page de configuration du plugin :

| Paramètre | Description |
|-----------|-------------|
| **Marque** | Hyundai, Kia ou Genesis |
| **Région** | Europe, USA, Canada, Chine, Australie, Inde |
| **Identifiant** | Email de votre compte Bluelink/UVO |
| **Mot de passe** | Mot de passe du compte |
| **Code PIN** | PIN de l'application (4-6 chiffres) |
| **Fréquence** | Intervalle de rafraîchissement en minutes (défaut: 30) |

### 4. Découverte des véhicules

1. Sauvegarder la configuration
2. Cliquer sur **Tester la connexion** pour vérifier
3. Cliquer sur **Rechercher mes véhicules**
4. Cliquer sur **Importer** pour chaque véhicule souhaité

## Commandes disponibles

### Actions
| Commande | Description |
|----------|-------------|
| Rafraîchir (cache) | Met à jour depuis le serveur Hyundai/Kia |
| Rafraîchir depuis véhicule | Force le réveil du véhicule (plus lent) |
| Verrouiller | Verrouille les portes |
| Déverrouiller | Déverrouille les portes |
| Démarrer moteur | Démarrage à distance |
| Arrêter moteur | Arrêt à distance |
| Démarrer climatisation | Active la clim/chauffage |
| Arrêter climatisation | Désactive la clim |
| Régler température | Température cible (14-30°C) |
| Démarrer charge *(EV/PHEV)* | Lance la recharge |
| Arrêter charge *(EV/PHEV)* | Stoppe la recharge |
| Limite charge AC *(EV/PHEV)* | Définit la limite de charge AC (50-100%) |
| Limite charge DC *(EV/PHEV)* | Définit la limite de charge DC (50-100%) |

### Informations
| Commande | Description |
|----------|-------------|
| Batterie EV (%) *(EV/PHEV)* | Niveau de charge batterie haute tension |
| En charge *(EV/PHEV)* | Indique si le véhicule est en charge |
| Branché *(EV/PHEV)* | Indique si le câble est branché |
| Autonomie EV (km) *(EV/PHEV)* | Distance restante sur batterie |
| Autonomie totale (km) *(EV/PHEV)* | Distance totale (EV + carburant) |
| Durée charge restante (min) *(EV/PHEV)* | Temps avant fin de charge |
| Durée charge rapide (min) *(EV/PHEV)* | Temps avec charge rapide |
| Niveau carburant (%) | Jauge carburant |
| Autonomie carburant (km) | Distance restante en carburant |
| Latitude/Longitude | Position GPS |
| Adresse | Adresse géocodée |
| Verrouillé | État du verrouillage |
| Porte avant/arrière G/D | État ouvert/fermé des portes |
| Coffre / Capot | État ouvert/fermé |
| Vitre avant/arrière G/D | État ouvert/fermé des vitres |
| Pression pneus | Pression de chaque pneu (PSI) |
| Kilométrage | Compteur total (km) |
| Batterie 12V (%) | État de la batterie auxiliaire |
| Température cible | Température de confort réglée |
| Moteur | Moteur en marche ou non |
| Climatisation | État de la climatisation |
| Dernière mise à jour | Horodatage de la dernière mise à jour |

## Widget Dashboard

Le plugin inclut un widget de synthèse affichant :
- Niveau de batterie ou carburant avec barre de progression
- État de verrouillage, moteur et climatisation
- Boutons d'actions rapides (verrouiller, démarrer, climatisation, charge)
- Localisation (adresse)
- Horodatage de la dernière mise à jour

## Bonnes pratiques

- **Éviter les rafraîchissements trop fréquents** : des appels fréquents peuvent vider la batterie 12V du véhicule ou déclencher des limitations de l'API.
- **Utiliser le mode cache** : en général, les données du serveur Hyundai/Kia sont mises à jour toutes les 30 minutes. Le mode "Rafraîchir depuis véhicule" doit être utilisé ponctuellement.
- **Régions** : assurez-vous de sélectionner la bonne région correspondant à votre contrat Bluelink/UVO.

## Dépannage

### Erreur d'authentification
Vérifier les identifiants email/mot de passe et le code PIN dans la configuration.

### Aucun véhicule trouvé
- Vérifier que l'application Bluelink/UVO est correctement configurée sur votre téléphone
- S'assurer que le véhicule est lié à votre compte

### Erreur Python / module manquant
Ré-installer les dépendances depuis la page de gestion des plugins.

### Timeout / pas de réponse
- L'API peut être temporairement indisponible
- Le véhicule peut être hors couverture réseau (garage souterrain...)

## Changelog

### v1.0.0
- Version initiale
- Support Hyundai, Kia, Genesis
- Toutes régions (EU, US, CA, CN, AU, IN)
- Support complet EV/PHEV (charge, limites, autonomie)
- Widget dashboard avec actions rapides
- Compatibilité Debian 12 / Python 3.11+

## Licence

Ce plugin est distribué sous licence AGPL v3.
