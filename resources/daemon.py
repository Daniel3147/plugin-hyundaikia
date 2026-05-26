#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Démon Jeedom pour le plugin Hyundai/Kia Connect.

API correcte de VehicleManager :
  - check_and_refresh_token()              → authentification + refresh token (à appeler en premier)
  - update_all_vehicles_with_cached_state() → récupère l'état mis en cache côté serveur
  - force_refresh_vehicle_state(id)         → force le véhicule à remonter son état

Il n'existe PAS de méthode login() publique sur VehicleManager.
check_and_refresh_token() appelle initialize() → api.login() en interne.
"""

import asyncio
import logging
import sys

from jeedomdaemon import BaseDaemon
from jeedomdaemon import BaseConfig

try:
    from hyundai_kia_connect_api import VehicleManager, ClimateRequestOptions
    from hyundai_kia_connect_api.const import BRANDS, REGIONS
except ImportError:
    logging.critical("hyundai_kia_connect_api non installé. Lancez : pip3 install hyundai_kia_connect_api")
    sys.exit(1)

logger = logging.getLogger(__name__)


# ──────────────────────────────────────────────────────────────────────────────
# Configuration personnalisée
# ──────────────────────────────────────────────────────────────────────────────

class HyundaiKiaConfig(BaseConfig):
    def __init__(self):
        super().__init__()
        self.add_argument('--region',   type=int, default=1)
        self.add_argument('--brand',    type=int, default=2)
        self.add_argument('--username', type=str, default='')
        self.add_argument('--password', type=str, default='')
        self.add_argument('--pin',      type=str, default='')


# ──────────────────────────────────────────────────────────────────────────────
# Démon principal
# ──────────────────────────────────────────────────────────────────────────────

class HyundaiKiaDaemon(BaseDaemon):

    def __init__(self) -> None:
        super().__init__(
            config=HyundaiKiaConfig(),
            on_start_cb=self.on_start,
            on_message_cb=self.on_message,
            on_stop_cb=self.on_stop,
        )
        self._vm: VehicleManager | None = None
        self._refresh_task: asyncio.Task | None = None

    # ── Démarrage ─────────────────────────────────────────────────────────────

    async def on_start(self):
        region   = self._config.region
        brand    = self._config.brand
        username = self._config.username
        password = self._config.password
        pin      = self._config.pin
        cycle    = float(self._config.cycle)  # en minutes

        logger.info(
            f"Démarrage – région={region} ({REGIONS.get(region, '?')}), "
            f"marque={brand} ({BRANDS.get(brand, '?')}), "
            f"utilisateur={username}, cycle={cycle} min"
        )

        if not username or not password:
            logger.error("Identifiants manquants – démon arrêté")
            return

        self._vm = VehicleManager(
            region=region,
            brand=brand,
            username=username,
            password=password,
            pin=pin,
        )

        # check_and_refresh_token() est la SEULE méthode publique d'auth.
        # Elle appelle initialize() → api.login() en interne si aucun token n'existe.
        try:
            logger.info("Authentification en cours…")
            await asyncio.get_event_loop().run_in_executor(
                None, self._vm.check_and_refresh_token
            )
            logger.info("Authentification réussie")
        except Exception as e:
            logger.error(f"Échec de l'authentification : {e}")
            logger.error(
                "Vérifiez : identifiants dans l'app mobile, "
                "marque (1=Kia 2=Hyundai 3=Genesis), région (1=Europe). "
                "Si l'erreur persiste, mettez à jour la librairie : "
                "pip3 install --upgrade hyundai_kia_connect_api"
            )
            return

        # Premier rafraîchissement immédiat
        try:
            await self._do_refresh()
        except Exception as e:
            logger.warning(f"Rafraîchissement initial non bloquant : {e}")

        # Boucle périodique
        interval_seconds = max(cycle, 5.0) * 60
        self._refresh_task = asyncio.create_task(
            self._refresh_loop(interval_seconds)
        )

    async def on_stop(self):
        logger.info("Arrêt du démon")
        if self._refresh_task and not self._refresh_task.done():
            self._refresh_task.cancel()
            try:
                await self._refresh_task
            except asyncio.CancelledError:
                pass

    # ── Messages entrants (commandes PHP → démon) ─────────────────────────────

    async def on_message(self, message: list):
        if not isinstance(message, list):
            message = [message]

        for msg in message:
            action = msg.get('action', '')
            vin    = msg.get('vin', '')
            logger.info(f"Commande reçue : action={action} vin={vin}")

            if not self._vm:
                logger.error("VehicleManager non initialisé")
                continue

            try:
                await asyncio.get_event_loop().run_in_executor(
                    None, self._vm.check_and_refresh_token
                )
                vehicle = self._vm.vehicles.get(vin)
                if not vehicle:
                    logger.error(f"Véhicule introuvable : {vin}")
                    continue
                await self._execute_action(vehicle, action, msg)
            except Exception as e:
                logger.error(f"Erreur action {action} : {e}")

    # ── Boucle de rafraîchissement ────────────────────────────────────────────

    async def _refresh_loop(self, interval_seconds: float):
        while True:
            await asyncio.sleep(interval_seconds)
            try:
                await self._do_refresh()
            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Erreur rafraîchissement : {e}")

    async def _do_refresh(self):
        if not self._vm:
            return

        logger.debug("Rafraîchissement des données véhicules…")
        loop = asyncio.get_event_loop()

        await loop.run_in_executor(None, self._vm.check_and_refresh_token)
        await loop.run_in_executor(None, self._vm.update_all_vehicles_with_cached_state)

        vehicles_data = [self._vehicle_to_dict(v) for v in self._vm.vehicles.values()]
        logger.info(f"{len(vehicles_data)} véhicule(s) récupéré(s)")

        await self.send_to_jeedom({
            'action':   'vehicle_update',
            'vehicles': vehicles_data,
        })

    # ── Actions véhicule ──────────────────────────────────────────────────────

    async def _execute_action(self, vehicle, action: str, options: dict):
        loop = asyncio.get_event_loop()

        if action == 'refresh':
            await loop.run_in_executor(
                None, lambda: self._vm.force_refresh_vehicle_state(vehicle.id)
            )
            await self._do_refresh()
            return

        simple_actions = {
            'lock':         lambda: self._vm.lock(vehicle.id),
            'unlock':       lambda: self._vm.unlock(vehicle.id),
            'stop_climate': lambda: self._vm.stop_climate(vehicle.id),
            'start_charge': lambda: self._vm.start_charge(vehicle.id),
            'stop_charge':  lambda: self._vm.stop_charge(vehicle.id),
        }

        if action == 'start_climate':
            temp = float(options.get('temperature', 22))
            opts = ClimateRequestOptions(set_temp=temp)
            await loop.run_in_executor(None, lambda: self._vm.start_climate(vehicle.id, opts))
        elif action in simple_actions:
            await loop.run_in_executor(None, simple_actions[action])
        else:
            logger.warning(f"Action inconnue : {action}")
            return

        logger.info(f"Action '{action}' exécutée sur {vehicle.id}")
        await asyncio.sleep(5)
        await self._do_refresh()

    # ── Sérialisation véhicule ────────────────────────────────────────────────

    def _vehicle_to_dict(self, vehicle) -> dict:
        def safe(attr, default=None):
            try:
                val = getattr(vehicle, attr, default)
                return default if val is None else val
            except Exception:
                return default

        def safe_dt(attr):
            val = safe(attr)
            if val is None:
                return None
            return val.isoformat() if hasattr(val, 'isoformat') else str(val)

        data = {
            'vin':   safe('id', ''),
            'name':  safe('name', ''),
            'model': safe('model', ''),

            'ev_battery_percentage':     safe('ev_battery_percentage'),
            'ev_battery_is_charging':    int(bool(safe('ev_battery_is_charging',   False))),
            'ev_battery_is_plugged_in':  int(bool(safe('ev_battery_is_plugged_in', False))),
            'ev_driving_range':          safe('ev_driving_range'),

            'fuel_level':                safe('fuel_level'),
            'fuel_driving_range':        safe('fuel_driving_range'),

            'is_locked':                 int(bool(safe('is_locked', False))),

            'front_left_door_open':      int(bool(safe('front_left_door_open',  False))),
            'front_right_door_open':     int(bool(safe('front_right_door_open', False))),
            'back_left_door_open':       int(bool(safe('back_left_door_open',   False))),
            'back_right_door_open':      int(bool(safe('back_right_door_open',  False))),
            'trunk_open':                int(bool(safe('trunk_open',            False))),
            'hood_open':                 int(bool(safe('hood_open',             False))),

            'front_left_window_open':    int(bool(safe('front_left_window_open',  False))),
            'front_right_window_open':   int(bool(safe('front_right_window_open', False))),
            'back_left_window_open':     int(bool(safe('back_left_window_open',   False))),
            'back_right_window_open':    int(bool(safe('back_right_window_open',  False))),

            'latitude':  None,
            'longitude': None,
            'location_name': safe('location_name', ''),

            'air_temperature':  safe('air_temperature'),
            'air_control_is_on': int(bool(safe('air_control_is_on', False))),

            'odometer': safe('odometer'),

            'tire_front_left_pressure':  safe('tire_front_left_pressure'),
            'tire_front_right_pressure': safe('tire_front_right_pressure'),
            'tire_back_left_pressure':   safe('tire_back_left_pressure'),
            'tire_back_right_pressure':  safe('tire_back_right_pressure'),

            'last_updated_at': safe_dt('last_updated_at'),
        }

        loc = safe('location')
        if loc is not None:
            try:
                data['latitude']  = loc.latitude
                data['longitude'] = loc.longitude
            except Exception:
                pass

        return data


if __name__ == '__main__':
    HyundaiKiaDaemon().run()
