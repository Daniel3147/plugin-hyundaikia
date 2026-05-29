#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
hyundaikia.py - Backend Python pour le plugin Jeedom hyundaikia
Interface avec la librairie hyundai_kia_connect_api
"""

import sys
import json
import argparse
import logging
import traceback

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
    print(json.dumps({"error": f"Librairie hyundai_kia_connect_api non installée: {str(e)}"}))
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
    region = REGION_MAP.get(args.region, Region.Europe)
    brand = BRAND_MAP.get(args.brand, Brand.Hyundai)

    manager = VehicleManager(
        region=region.value,
        brand=brand.value,
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
        v = {
            "id": vehicle.id,
            "name": vehicle.name or "",
            "model": vehicle.model or "",
            "year": vehicle.year or "",
            "vin": vehicle.vin or "",
            "reg_no": vehicle.registration_date or "",
            "is_ev": bool(getattr(vehicle, "ev_battery_is_charging", None) is not None),
            "is_phev": bool(getattr(vehicle, "fuel_level", None) is not None and getattr(vehicle, "ev_battery_level", None) is not None),
            "is_hev": False,
        }
        # Détection EV/PHEV plus robuste
        try:
            v["is_ev"] = vehicle.ev_battery_is_charging is not None
        except Exception:
            pass
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

    def safe_get(obj, attr, default=None):
        try:
            val = getattr(obj, attr, default)
            return val
        except Exception:
            return default

    def bool_to_int(val):
        if val is None:
            return None
        return 1 if val else 0

    def safe_float(val):
        try:
            return float(val) if val is not None else None
        except (TypeError, ValueError):
            return None

    # Localisation
    lat = safe_get(vehicle, "location_latitude")
    lng = safe_get(vehicle, "location_longitude")
    geo_address = safe_get(vehicle, "location_name", "")

    # Limites de charge
    ac_limit = None
    dc_limit = None
    try:
        charge_limits = safe_get(vehicle, "ev_charge_limits")
        if charge_limits:
            if isinstance(charge_limits, dict):
                ac_limit = charge_limits.get("ac")
                dc_limit = charge_limits.get("dc")
            else:
                ac_limit = safe_get(charge_limits, "ac")
                dc_limit = safe_get(charge_limits, "dc")
    except Exception:
        pass

    # Température cible
    target_temp = None
    try:
        target_temp = safe_get(vehicle, "air_temperature")
        if target_temp and hasattr(target_temp, "value"):
            target_temp = target_temp.value
    except Exception:
        pass

    data = {
        # Général
        "last_updated_at": str(safe_get(vehicle, "last_updated_at", "")),
        "engine": bool_to_int(safe_get(vehicle, "engine_is_running")),
        "air_conditioning": bool_to_int(safe_get(vehicle, "air_control_is_on")),
        "smart_key_battery": safe_get(vehicle, "smart_key_battery_warning_is_on"),

        # Localisation
        "latitude": safe_float(lat),
        "longitude": safe_float(lng),
        "geocode_address": str(geo_address) if geo_address else "",
        "geocode_name": str(safe_get(vehicle, "location_name", "")),

        # Carburant
        "fuel_level": safe_get(vehicle, "fuel_level"),
        "fuel_driving_range": safe_get(vehicle, "fuel_driving_range"),

        # Électrique
        "ev_battery_level": safe_get(vehicle, "ev_battery_level"),
        "ev_battery_is_charging": bool_to_int(safe_get(vehicle, "ev_battery_is_charging")),
        "ev_battery_is_plugged_in": bool_to_int(safe_get(vehicle, "ev_battery_is_plugged_in")),
        "ev_driving_range": safe_get(vehicle, "ev_driving_range"),
        "ev_estimated_current_charge_duration": safe_get(vehicle, "ev_estimated_current_charge_duration"),
        "ev_estimated_fast_charge_duration": safe_get(vehicle, "ev_estimated_fast_charge_duration"),
        "ev_estimated_portable_charge_duration": safe_get(vehicle, "ev_estimated_portable_charge_duration"),
        "ev_estimated_station_charge_duration": safe_get(vehicle, "ev_estimated_station_charge_duration"),
        "total_driving_range": safe_get(vehicle, "total_driving_range"),
        "ev_charge_limits_ac": ac_limit,
        "ev_charge_limits_dc": dc_limit,

        # Verrouillage
        "is_locked": bool_to_int(safe_get(vehicle, "is_locked")),

        # Portes
        "front_left_door": bool_to_int(safe_get(vehicle, "front_left_door_is_open")),
        "front_right_door": bool_to_int(safe_get(vehicle, "front_right_door_is_open")),
        "back_left_door": bool_to_int(safe_get(vehicle, "back_left_door_is_open")),
        "back_right_door": bool_to_int(safe_get(vehicle, "back_right_door_is_open")),
        "trunk": bool_to_int(safe_get(vehicle, "trunk_is_open")),
        "hood": bool_to_int(safe_get(vehicle, "hood_is_open")),

        # Fenêtres
        "front_left_window": bool_to_int(safe_get(vehicle, "front_left_window_is_open")),
        "front_right_window": bool_to_int(safe_get(vehicle, "front_right_window_is_open")),
        "back_left_window": bool_to_int(safe_get(vehicle, "back_left_window_is_open")),
        "back_right_window": bool_to_int(safe_get(vehicle, "back_right_window_is_open")),

        # Pneus
        "tire_front_left": safe_get(vehicle, "tire_pressure_front_left_bar"),
        "tire_front_right": safe_get(vehicle, "tire_pressure_front_right_bar"),
        "tire_back_left": safe_get(vehicle, "tire_pressure_back_left_bar"),
        "tire_back_right": safe_get(vehicle, "tire_pressure_back_right_bar"),

        # Autres
        "target_temperature": safe_float(target_temp),
        "odometer": safe_get(vehicle, "odometer"),
        "battery_12v": safe_get(vehicle, "battery_12v"),
    }

    # Nettoyer les None
    data = {k: v for k, v in data.items() if v is not None}
    return data


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
            options = ClimateRequestOptions(set_temp=float(temp), duration=10, heating=False)
            manager.start_climate(args.vehicle_id, options)
        except ImportError:
            manager.start_climate(args.vehicle_id)
    elif action == "stop_climate":
        manager.stop_climate(args.vehicle_id)
    elif action == "set_charge_limits":
        ac = getattr(args, "ac_limit", 90)
        dc = getattr(args, "dc_limit", 80)
        manager.set_charge_limits(args.vehicle_id, int(ac), int(dc))
    elif action == "set_target_temperature":
        temp = getattr(args, "temperature", 22)
        # set_target_temperature n'est pas directement disponible, on passe par start_climate
        try:
            from hyundai_kia_connect_api import ClimateRequestOptions
            options = ClimateRequestOptions(set_temp=float(temp), duration=10, heating=False)
            manager.start_climate(args.vehicle_id, options)
        except ImportError:
            pass
    else:
        return {"error": f"Action inconnue: {action}"}

    return {"success": True, "action": action}


def main():
    parser = argparse.ArgumentParser(description="Hyundai/Kia Connect API bridge for Jeedom")
    parser.add_argument("--action", required=True, help="Action à effectuer")
    parser.add_argument("--brand", default="HY", help="Marque (HY/KI/GE)")
    parser.add_argument("--region", default="EU", help="Région (EU/CA/US/CN/AU/IN)")
    parser.add_argument("--username", required=True, help="Identifiant")
    parser.add_argument("--password", required=True, help="Mot de passe")
    parser.add_argument("--pin", default="", help="Code PIN")
    parser.add_argument("--vehicle_id", default="", help="ID du véhicule")
    parser.add_argument("--temperature", type=float, default=22.0, help="Température cible")
    parser.add_argument("--ac_limit", type=int, default=90, help="Limite charge AC (%)")
    parser.add_argument("--dc_limit", type=int, default=80, help="Limite charge DC (%)")

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
        print(json.dumps({"error": f"Erreur d'authentification: {str(e)}"}))
        sys.exit(1)
    except RateLimitingError as e:
        print(json.dumps({"error": f"Limite de requêtes atteinte: {str(e)}"}))
        sys.exit(1)
    except NoDataFound as e:
        print(json.dumps({"error": f"Aucune donnée trouvée: {str(e)}"}))
        sys.exit(1)
    except Exception as e:
        logger.error(traceback.format_exc())
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
