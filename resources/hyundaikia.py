#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
hyundaikia.py - Backend Python pour le plugin Jeedom hyundaikia
Interface avec la librairie hyundai_kia_connect_api
"""

import sys
import os
import json
import argparse
import logging
import traceback

# ── Résolution automatique du virtualenv ──────────────────────────────────────
# Si le script est lancé avec python3 système mais que le venv existe,
# on se relance avec le python du venv (contient hyundai_kia_connect_api)
_script_dir = os.path.dirname(os.path.abspath(__file__))
_venv_python = os.path.join(_script_dir, "venv", "bin", "python3")

if os.path.isfile(_venv_python) and os.path.realpath(sys.executable) != os.path.realpath(_venv_python):
    # Relancer le même script avec le python du venv
    os.execv(_venv_python, [_venv_python] + sys.argv)
    # os.execv remplace le processus, on n'atteint jamais la ligne suivante

# ── Imports librairie ─────────────────────────────────────────────────────────
try:
    from hyundai_kia_connect_api import VehicleManager, Brand, Region
    from hyundai_kia_connect_api.exceptions import (
        AuthenticationError,
        NoDataFound,
        RateLimitingError,
        DeviceIDError,
        InvalidAPIResponseError,
    )
except ImportError as e:
    print(json.dumps({"error": f"Librairie hyundai_kia_connect_api non installée: {str(e)}. Installez les dépendances depuis Jeedom."}))
    sys.exit(1)

# Mapping région
REGION_MAP = {
    "EU": Region.Europe,
    "CA": Region.Canada,
    "US": Region.USA,
    "CN": Region.China,
    "AU": Region.Australia,
    "IN": Region.India,
}

# Mapping marque
BRAND_MAP = {
    "HY": Brand.Hyundai,
    "KI": Brand.Kia,
    "GE": Brand.Genesis,
}

logging.basicConfig(
    level=logging.WARNING,
    format="%(asctime)s %(levelname)s %(message)s",
    stream=sys.stderr,
)
logger = logging.getLogger("hyundaikia")


def get_manager(args):
    """Crée et retourne un VehicleManager connecté"""
    region_obj = REGION_MAP.get(args.region, Region.Europe)
    brand_obj  = BRAND_MAP.get(args.brand, Brand.Hyundai)

    manager = VehicleManager(
        region=region_obj.value,
        brand=brand_obj.value,
        username=args.username,
        password=args.password,
        pin=args.pin,
    )
    manager.check_and_refresh_token()
    return manager


def list_vehicles(args):
    """Liste les véhicules disponibles"""
    manager = get_manager(args)
    manager.update_all_vehicles_with_cached_state()

    vehicles = []
    for v_id, vehicle in manager.vehicles.items():
        # Détection EV/PHEV
        has_ev  = getattr(vehicle, "ev_battery_level", None) is not None
        has_fuel = getattr(vehicle, "fuel_level", None) is not None
        is_phev = has_ev and has_fuel
        is_ev   = has_ev and not has_fuel

        v = {
            "id":      vehicle.id,
            "name":    vehicle.name or "",
            "model":   vehicle.model or "",
            "year":    str(vehicle.year) if vehicle.year else "",
            "vin":     vehicle.vin or "",
            "reg_no":  getattr(vehicle, "car_control_status", "") or "",
            "is_ev":   is_ev,
            "is_phev": is_phev,
            "is_hev":  False,
        }
        vehicles.append(v)

    return vehicles


def get_vehicle_status(args, force=False):
    """Récupère le statut d'un véhicule"""
    manager = get_manager(args)

    if force:
        manager.force_refresh_vehicle_state(args.vehicle_id)
    else:
        manager.update_vehicle_with_cached_state(args.vehicle_id)

    vehicle = manager.vehicles.get(args.vehicle_id)
    if vehicle is None:
        return {"error": f"Véhicule {args.vehicle_id} introuvable"}

    def safe(attr, default=None):
        try:
            val = getattr(vehicle, attr, default)
            return val
        except Exception:
            return default

    def b(val):
        if val is None:
            return None
        return 1 if val else 0

    def f(val):
        try:
            return float(val) if val is not None else None
        except (TypeError, ValueError):
            return None

    # Limites de charge
    ac_limit = dc_limit = None
    try:
        cl = safe("ev_charge_limits")
        if cl:
            if isinstance(cl, dict):
                ac_limit, dc_limit = cl.get("ac"), cl.get("dc")
            else:
                ac_limit = safe("ev_charge_limits_ac") or getattr(cl, "ac", None)
                dc_limit = safe("ev_charge_limits_dc") or getattr(cl, "dc", None)
    except Exception:
        pass

    # Température cible
    target_temp = None
    try:
        t = safe("air_temperature")
        if t is not None:
            target_temp = t.value if hasattr(t, "value") else f(t)
    except Exception:
        pass

    data = {
        "last_updated_at":    str(safe("last_updated_at", "")),
        "engine":             b(safe("engine_is_running")),
        "air_conditioning":   b(safe("air_control_is_on")),
        "smart_key_battery":  b(safe("smart_key_battery_warning_is_on")),
        # Localisation
        "latitude":           f(safe("location_latitude")),
        "longitude":          f(safe("location_longitude")),
        "geocode_address":    str(safe("location_name", "") or ""),
        "geocode_name":       str(safe("location_name", "") or ""),
        # Carburant
        "fuel_level":         safe("fuel_level"),
        "fuel_driving_range": safe("fuel_driving_range"),
        # EV
        "ev_battery_level":                    safe("ev_battery_level"),
        "ev_battery_is_charging":              b(safe("ev_battery_is_charging")),
        "ev_battery_is_plugged_in":            b(safe("ev_battery_is_plugged_in")),
        "ev_driving_range":                    safe("ev_driving_range"),
        "ev_estimated_current_charge_duration":safe("ev_estimated_current_charge_duration"),
        "ev_estimated_fast_charge_duration":   safe("ev_estimated_fast_charge_duration"),
        "ev_estimated_portable_charge_duration":safe("ev_estimated_portable_charge_duration"),
        "ev_estimated_station_charge_duration":safe("ev_estimated_station_charge_duration"),
        "total_driving_range":                 safe("total_driving_range"),
        "ev_charge_limits_ac":                 ac_limit,
        "ev_charge_limits_dc":                 dc_limit,
        # Verrouillage
        "is_locked":         b(safe("is_locked")),
        # Portes
        "front_left_door":   b(safe("front_left_door_is_open")),
        "front_right_door":  b(safe("front_right_door_is_open")),
        "back_left_door":    b(safe("back_left_door_is_open")),
        "back_right_door":   b(safe("back_right_door_is_open")),
        "trunk":             b(safe("trunk_is_open")),
        "hood":              b(safe("hood_is_open")),
        # Fenêtres
        "front_left_window":  b(safe("front_left_window_is_open")),
        "front_right_window": b(safe("front_right_window_is_open")),
        "back_left_window":   b(safe("back_left_window_is_open")),
        "back_right_window":  b(safe("back_right_window_is_open")),
        # Pneus
        "tire_front_left":  safe("tire_pressure_front_left_bar"),
        "tire_front_right": safe("tire_pressure_front_right_bar"),
        "tire_back_left":   safe("tire_pressure_back_left_bar"),
        "tire_back_right":  safe("tire_pressure_back_right_bar"),
        # Divers
        "target_temperature": f(target_temp),
        "odometer":           safe("odometer"),
        "battery_12v":        safe("battery_12v"),
    }

    return {k: v for k, v in data.items() if v is not None}


def do_action(args):
    """Exécute une action sur le véhicule"""
    manager = get_manager(args)
    action = args.action

    if action == "lock":
        manager.lock(args.vehicle_id)
    elif action == "unlock":
        manager.unlock(args.vehicle_id)
    elif action == "start_engine":
        manager.start_engine(args.vehicle_id)
    elif action == "stop_engine":
        manager.stop_engine(args.vehicle_id)
    elif action == "start_charge":
        manager.start_charge(args.vehicle_id)
    elif action == "stop_charge":
        manager.stop_charge(args.vehicle_id)
    elif action == "start_climate":
        temp = getattr(args, "temperature", 22)
        try:
            from hyundai_kia_connect_api import ClimateRequestOptions
            opts = ClimateRequestOptions(set_temp=float(temp), duration=10, heating=False)
            manager.start_climate(args.vehicle_id, opts)
        except (ImportError, TypeError):
            manager.start_climate(args.vehicle_id)
    elif action == "stop_climate":
        manager.stop_climate(args.vehicle_id)
    elif action == "set_charge_limits":
        manager.set_charge_limits(args.vehicle_id, int(args.ac_limit), int(args.dc_limit))
    elif action == "set_target_temperature":
        temp = getattr(args, "temperature", 22)
        try:
            from hyundai_kia_connect_api import ClimateRequestOptions
            opts = ClimateRequestOptions(set_temp=float(temp), duration=10, heating=False)
            manager.start_climate(args.vehicle_id, opts)
        except (ImportError, TypeError):
            pass
    else:
        return {"error": f"Action inconnue: {action}"}

    return {"success": True, "action": action}


def main():
    parser = argparse.ArgumentParser(description="Hyundai/Kia Connect API bridge for Jeedom")
    parser.add_argument("--action",     required=True)
    parser.add_argument("--brand",      default="HY")
    parser.add_argument("--region",     default="EU")
    parser.add_argument("--username",   required=True)
    parser.add_argument("--password",   required=True)
    parser.add_argument("--pin",        default="")
    parser.add_argument("--vehicle_id", default="")
    parser.add_argument("--temperature",type=float, default=22.0)
    parser.add_argument("--ac_limit",   type=int,   default=90)
    parser.add_argument("--dc_limit",   type=int,   default=80)
    args = parser.parse_args()

    try:
        if args.action == "list_vehicles":
            result = list_vehicles(args)
        elif args.action == "get_vehicle_status":
            result = get_vehicle_status(args, force=False)
        elif args.action == "get_vehicle_status_force":
            result = get_vehicle_status(args, force=True)
        else:
            result = do_action(args)

        print(json.dumps(result, default=str, ensure_ascii=False))

    except AuthenticationError as e:
        print(json.dumps({"error": f"Erreur d'authentification: {e}"}))
        sys.exit(1)
    except RateLimitingError as e:
        print(json.dumps({"error": f"Limite de requêtes atteinte: {e}"}))
        sys.exit(1)
    except NoDataFound as e:
        print(json.dumps({"error": f"Aucune donnée: {e}"}))
        sys.exit(1)
    except Exception as e:
        logger.error(traceback.format_exc())
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
