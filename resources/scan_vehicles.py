#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de scan ponctuel des véhicules.
Appelé depuis PHP (shell_exec) pour lister les véhicules d'un compte
sans avoir besoin que le démon soit démarré.
Retourne un tableau JSON sur stdout.
"""

import argparse
import json
import sys
import logging

logging.basicConfig(level=logging.WARNING)

def main():
    parser = argparse.ArgumentParser(description='Scan véhicules Hyundai/Kia')
    parser.add_argument('--region',   type=int, required=True)
    parser.add_argument('--brand',    type=int, required=True)
    parser.add_argument('--username', type=str, required=True)
    parser.add_argument('--password', type=str, required=True)
    parser.add_argument('--pin',      type=str, default='')
    args = parser.parse_args()

    try:
        from hyundai_kia_connect_api import VehicleManager
    except ImportError:
        print('[]', flush=True)
        sys.exit(1)

    try:
        vm = VehicleManager(
            region=args.region,
            brand=args.brand,
            username=args.username,
            password=args.password,
            pin=args.pin,
        )
        vm.check_and_refresh_token()
        vm.update_all_vehicles_with_cached_state()

        vehicles = []
        for vin, vehicle in vm.vehicles.items():
            vehicles.append({
                'vin'   : vin,
                'name'  : getattr(vehicle, 'name', vin),
                'model' : getattr(vehicle, 'model', ''),
            })

        print(json.dumps(vehicles), flush=True)

    except Exception as e:
        # Écrit l'erreur sur stderr pour les logs, tableau vide sur stdout
        print(f'Erreur scan: {e}', file=sys.stderr, flush=True)
        print('[]', flush=True)
        sys.exit(1)


if __name__ == '__main__':
    main()
