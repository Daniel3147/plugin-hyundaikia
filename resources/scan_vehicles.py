#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Scan ponctuel des véhicules — appelé depuis PHP via shell_exec.
Retourne un tableau JSON sur stdout.

Flux obligatoire : login() en premier, PUIS update_all_vehicles_with_cached_state()
"""

import argparse
import json
import sys
import logging

logging.basicConfig(level=logging.WARNING)

def main():
    parser = argparse.ArgumentParser()
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

        # login() DOIT être appelé avant toute autre méthode
        vm.login()
        vm.update_all_vehicles_with_cached_state()

        vehicles = [
            {
                'vin':   vin,
                'name':  getattr(v, 'name',  vin),
                'model': getattr(v, 'model', ''),
            }
            for vin, v in vm.vehicles.items()
        ]

        print(json.dumps(vehicles), flush=True)

    except Exception as e:
        print(f'Erreur: {e}', file=sys.stderr, flush=True)
        print('[]', flush=True)
        sys.exit(1)

if __name__ == '__main__':
    main()
