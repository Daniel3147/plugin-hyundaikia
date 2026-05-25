#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Démon Python pour le plugin Jeedom Hyundai/Kia Connect
Utilise la librairie hyundai_kia_connect_api pour récupérer
l'état des véhicules et exécuter des commandes.
"""

import asyncio
import logging
import sys
import json
from datetime import datetime, timezone

from jeedomdaemon import BaseDaemon

try:
    from hyundai_kia_connect_api import VehicleManager, ClimateRequestOptions
    from hyundai_kia_connect_api.const import (
        REGIONS, BRANDS,
        REGION_EUROPE, REGION_CANADA, REGION_USA,
        BRAND_KIA, BRAND_HYUNDAI, BRAND_GENESIS,
    )
except ImportError:
    logging.critical("Librairie hyundai_kia_connect_api non installée. "
                     "Exécutez: pip3 install hyundai_kia_connect_api")
    sys.exit(1)

logger = logging.getLogger(__name__)


class HyundaiKiaDaemon(BaseDaemon):

    def __init__(self) -> None:
        super().__init__(
            on_start_cb=self.on_start,
            on_message_cb=self.on_message,
            on_stop_cb=self.on_stop,
        )
        self._vm: VehicleManager | None = None
        self._refresh_task: asyncio.Task | None = None

    # ------------------------------------------------------------------
    # Cycle de vie du démon
    # ------------------------------------------------------------------

    async def on_start(self):
        logger.info("Démarrage du démon Hyundai/Kia Connect")

        region   = int(self._config.get('region',   1))
        brand    = int(self._config.get('brand',    1))
        username = self._config.get('username', '')
        password = self._config.get('password', '')
        pin      = self._config.get('pin',      '')
        cycle    = int(self._config.get('cycle', 30))

        if not username or not password:
            logger.error("Identifiants manquants dans la configuration")
            return

        logger.info(f"Connexion – région: {region}, marque: {brand}, utilisateur: {username}")

        try:
            self._vm = VehicleManager(
                region=region,
                brand=brand,
                username=username,
                password=password,
                pin=pin,
            )
            await asyncio.get_event_loop().run_in_executor(
                None, self._vm.check_and_refresh_token
            )
            logger.info("Authentification réussie")
        except Exception as e:
            logger.error(f"Erreur d'authentification: {e}")
            return

        # Lance la boucle de rafraîchissement
        self._refresh_task = asyncio.create_task(
            self._refresh_loop(cycle)
        )

    async def on_stop(self):
        logger.info("Arrêt du démon Hyundai/Kia Connect")
        if self._refresh_task and not self._refresh_task.done():
            self._refresh_task.cancel()
            try:
                await self._refresh_task
            except asyncio.CancelledError:
                pass

    # ------------------------------------------------------------------
    # Gestion des messages entrants (commandes depuis PHP)
    # ------------------------------------------------------------------

    async def on_message(self, message: dict):
        """Reçoit une commande depuis le PHP (verrouillage, clim, etc.)"""
        action = message.get('action', '')
        vin    = message.get('vin', '')

        logger.info(f"Commande reçue: action={action}, vin={vin}")

        if not self._vm:
            logger.error("VehicleManager non initialisé")
            return

        try:
            await asyncio.get_event_loop().run_in_executor(
                None, self._vm.check_and_refresh_token
            )
            vehicle = self._get_vehicle_by_vin(vin)
            if not vehicle:
                logger.error(f"Véhicule introuvable: {vin}")
                return

            await self._execute_action(vehicle, action, message)

        except Exception as e:
            logger.error(f"Erreur lors de l'exécution de {action}: {e}")

    # ------------------------------------------------------------------
    # Boucle de rafraîchissement périodique
    # ------------------------------------------------------------------

    async def _refresh_loop(self, cycle: int):
        while True:
            try:
                await self._do_refresh()
            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Erreur dans la boucle de rafraîchissement: {e}")

            await asyncio.sleep(cycle * 60)  # cycle est en minutes

    async def _do_refresh(self):
        """Rafraîchit les données de tous les véhicules et les envoie à Jeedom."""
        if not self._vm:
            return

        logger.debug("Rafraîchissement des données véhicules...")

        await asyncio.get_event_loop().run_in_executor(
            None, self._vm.check_and_refresh_token
        )
        await asyncio.get_event_loop().run_in_executor(
            None, self._vm.update_all_vehicles_with_cached_state
        )

        vehicles_data = []
        for vin, vehicle in self._vm.vehicles.items():
            data = self._vehicle_to_dict(vehicle)
            vehicles_data.append(data)
            logger.debug(f"Véhicule: {data.get('name', vin)}")

        # Envoie les données à Jeedom via le callback HTTP
        await self.send_to_jeedom({
            'action':   'vehicle_update',
            'vehicles': vehicles_data,
        })

    # ------------------------------------------------------------------
    # Exécution des commandes sur le véhicule
    # ------------------------------------------------------------------

    async def _execute_action(self, vehicle, action: str, options: dict):
        loop = asyncio.get_event_loop()

        if action == 'refresh':
            await loop.run_in_executor(
                None,
                lambda: self._vm.force_refresh_vehicle_state(vehicle.id)
            )
            await self._do_refresh()

        elif action == 'lock':
            await loop.run_in_executor(
                None, lambda: self._vm.lock(vehicle.id)
            )
            logger.info(f"Verrouillage envoyé pour {vehicle.id}")

        elif action == 'unlock':
            await loop.run_in_executor(
                None, lambda: self._vm.unlock(vehicle.id)
            )
            logger.info(f"Déverrouillage envoyé pour {vehicle.id}")

        elif action == 'start_climate':
            temperature = float(options.get('temperature', 22))
            climate_options = ClimateRequestOptions(set_temp=temperature)
            await loop.run_in_executor(
                None,
                lambda: self._vm.start_climate(vehicle.id, climate_options)
            )
            logger.info(f"Climatisation démarrée à {temperature}°C pour {vehicle.id}")

        elif action == 'stop_climate':
            await loop.run_in_executor(
                None, lambda: self._vm.stop_climate(vehicle.id)
            )
            logger.info(f"Climatisation arrêtée pour {vehicle.id}")

        elif action == 'start_charge':
            await loop.run_in_executor(
                None, lambda: self._vm.start_charge(vehicle.id)
            )
            logger.info(f"Charge démarrée pour {vehicle.id}")

        elif action == 'stop_charge':
            await loop.run_in_executor(
                None, lambda: self._vm.stop_charge(vehicle.id)
            )
            logger.info(f"Charge arrêtée pour {vehicle.id}")

        else:
            logger.warning(f"Action inconnue: {action}")

        # Après une action, on rafraîchit après un délai
        await asyncio.sleep(5)
        await self._do_refresh()

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    def _get_vehicle_by_vin(self, vin: str):
        if not self._vm or not vin:
            return None
        return self._vm.vehicles.get(vin)

    def _vehicle_to_dict(self, vehicle) -> dict:
        """Convertit un objet Vehicle en dict sérialisable pour Jeedom."""

        def safe(attr, default=None):
            try:
                val = getattr(vehicle, attr, default)
                if val is None:
                    return default
                return val
            except Exception:
                return default

        def safe_dt(attr):
            val = safe(attr)
            if val is None:
                return None
            if hasattr(val, 'isoformat'):
                return val.isoformat()
            return str(val)

        data = {
            'vin':                       safe('id', ''),
            'name':                      safe('name', ''),
            'model':                     safe('model', ''),
            'registration_date':         safe('registration_date', ''),

            # Batterie électrique
            'ev_battery_percentage':     safe('ev_battery_percentage'),
            'ev_battery_is_charging':    int(bool(safe('ev_battery_is_charging', False))),
            'ev_battery_is_plugged_in':  int(bool(safe('ev_battery_is_plugged_in', False))),
            'ev_driving_range':          safe('ev_driving_range'),

            # Carburant
            'fuel_level':                safe('fuel_level'),
            'fuel_driving_range':        safe('fuel_driving_range'),

            # Verrouillage
            'is_locked':                 int(bool(safe('is_locked', False))),

            # Portes
            'front_left_door_open':      int(bool(safe('front_left_door_open', False))),
            'front_right_door_open':     int(bool(safe('front_right_door_open', False))),
            'back_left_door_open':       int(bool(safe('back_left_door_open', False))),
            'back_right_door_open':      int(bool(safe('back_right_door_open', False))),
            'trunk_open':                int(bool(safe('trunk_open', False))),
            'hood_open':                 int(bool(safe('hood_open', False))),

            # Fenêtres
            'front_left_window_open':    int(bool(safe('front_left_window_open', False))),
            'front_right_window_open':   int(bool(safe('front_right_window_open', False))),
            'back_left_window_open':     int(bool(safe('back_left_window_open', False))),
            'back_right_window_open':    int(bool(safe('back_right_window_open', False))),

            # Localisation
            'latitude':                  safe('location', {}).latitude if safe('location') else None,
            'longitude':                 safe('location', {}).longitude if safe('location') else None,
            'location_name':             safe('location_name', ''),

            # Climatisation
            'air_temperature':           safe('air_temperature'),
            'air_control_is_on':         int(bool(safe('air_control_is_on', False))),

            # Odométrie
            'odometer':                  safe('odometer'),

            # Pression des pneus
            'tire_front_left_pressure':  safe('tire_front_left_pressure'),
            'tire_front_right_pressure': safe('tire_front_right_pressure'),
            'tire_back_left_pressure':   safe('tire_back_left_pressure'),
            'tire_back_right_pressure':  safe('tire_back_right_pressure'),

            # Horodatage
            'last_updated_at':           safe_dt('last_updated_at'),
        }

        # Localisation : accès à l'objet Location si disponible
        loc = safe('location')
        if loc is not None:
            try:
                data['latitude']  = loc.latitude
                data['longitude'] = loc.longitude
            except Exception:
                pass

        return data


# ------------------------------------------------------------------
# Point d'entrée
# ------------------------------------------------------------------
if __name__ == '__main__':
    HyundaiKiaDaemon().run()
