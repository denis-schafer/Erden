#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Erden Print Agent
=================
Agente de impresion local para Erden POS.
Consulta el servidor VPS por trabajos de impresion pendientes
y los envía a la impresora termica configurada.

Uso:
    python print-agent.py

La primera vez pedira la URL del servidor y la clave API.
Los datos se guardan en agent-config.json para proximas ejecuciones.
"""

import requests
import socket
import base64
import time
import json
import sys
import os
from pathlib import Path

CONFIG_FILE = Path(__file__).parent / "agent-config.json"

VERSION = "1.0.0"


def print_banner():
    print("=" * 55)
    print("   Erden Print Agent v{}".format(VERSION))
    print("   Agente de Impresion Local para POS")
    print("=" * 55)
    print()


def load_config():
    if CONFIG_FILE.exists():
        try:
            with open(CONFIG_FILE, "r") as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            print("[!] Error leyendo agent-config.json. Se pediran los datos nuevamente.")
    return None


def save_config(config):
    try:
        with open(CONFIG_FILE, "w") as f:
            json.dump(config, f, indent=4)
        print("[+] Configuracion guardada en agent-config.json")
    except IOError as e:
        print("[!] Error guardando configuracion: {}".format(e))
        sys.exit(1)


def request_config():
    print("--- Configuracion Inicial ---")
    print()

    url = input("URL del servidor VPS (ej: http://149.50.133.48): ").strip().rstrip("/")
    if not url:
        print("[!] La URL no puede estar vacia.")
        sys.exit(1)

    if not url.startswith("http://") and not url.startswith("https://"):
        url = "http://" + url
        print("[i] Se agrego http:// automaticamente: {}".format(url))

    api_key = input("Clave API (desde Configuracion > POS > Agente de Impresion): ").strip()
    if not api_key:
        print("[!] La clave API no puede estar vacia.")
        sys.exit(1)

    return {"vps_url": url, "api_key": api_key, "poll_interval": 3}


def test_connection(config):
    print("[*] Probando conexion al servidor...")
    try:
        headers = {"X-Print-Agent-Key": config["api_key"]}
        r = requests.get(
            "{}/pos/print-jobs/pending".format(config["vps_url"]),
            headers=headers,
            timeout=10,
        )
        if r.status_code == 200:
            print("[+] Conexion exitosa!")
            return True
        else:
            print("[!] Error: Servidor respondio con codigo {}".format(r.status_code))
            print("    Respuesta: {}".format(r.text))
            return False
    except requests.exceptions.ConnectionError:
        print("[!] No se pudo conectar a {}".format(config["vps_url"]))
        return False
    except requests.exceptions.Timeout:
        print("[!] Tiempo de espera agotado al conectar a {}".format(config["vps_url"]))
        return False
    except Exception as e:
        print("[!] Error de conexion: {}".format(e))
        return False


def send_to_printer(ip, port, data):
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(10)
        s.connect((ip, int(port)))
        s.sendall(data)
        s.close()
        return True, None
    except socket.timeout:
        return False, "Tiempo de espera agotado al conectar a {}:{}".format(ip, port)
    except ConnectionRefusedError:
        return False, "Conexion rechazada por {}:{}".format(ip, port)
    except socket.gaierror:
        return False, "Direccion IP invalida: {}".format(ip)
    except Exception as e:
        return False, str(e)


def poll_loop(config):
    headers = {"X-Print-Agent-Key": config["api_key"]}
    poll_url = "{}/pos/print-jobs/pending".format(config["vps_url"])
    ack_url = "{}/pos/print-jobs/{}/ack".format(config["vps_url"])
    interval = config.get("poll_interval", 3)

    print("[*] Iniciando ciclo de polling cada {} segundos...".format(interval))
    print("[*] Presiona Ctrl+C para detener.")
    print()

    while True:
        try:
            r = requests.get(poll_url, headers=headers, timeout=15)

            if r.status_code != 200:
                print("[!] Error del servidor ({}), reintentando...".format(r.status_code))
                time.sleep(interval)
                continue

            jobs = r.json()

            if jobs:
                print("[{}] {} trabajo(s) pendiente(s)".format(
                    time.strftime("%H:%M:%S"), len(jobs)
                ))

            for job in jobs:
                try:
                    print("    -> Pedido #{} a {}:{}... ".format(
                        job["order_id"], job["printer_ip"], job["printer_port"]
                    ), end="", flush=True)

                    data = base64.b64decode(job["ticket_data"])
                    success, error = send_to_printer(
                        job["printer_ip"], job["printer_port"], data
                    )

                    if success:
                        status = "completed"
                        print("IMPRESO")
                    else:
                        status = "failed"
                        print("ERROR: {}".format(error))

                    requests.post(
                        ack_url.format(job["id"]),
                        json={"status": status, "error_message": error},
                        headers=headers,
                        timeout=10,
                    )

                except Exception as e:
                    print("    -> Error procesando trabajo #{}: {}".format(
                        job.get("id", "?"), e
                    ))

        except KeyboardInterrupt:
            print()
            print("[*] Detenido por el usuario.")
            break
        except requests.exceptions.ConnectionError:
            print("[!] ({}) Sin conexion al servidor. Reintentando en {}s...".format(
                time.strftime("%H:%M:%S"), interval
            ))
            time.sleep(interval)
            continue
        except requests.exceptions.Timeout:
            print("[!] ({}) Tiempo de espera agotado. Reintentando...".format(
                time.strftime("%H:%M:%S")
            ))
        except Exception as e:
            print("[!] Error inesperado: {}".format(e))

        time.sleep(interval)


def prompt_new_url():
    resp = input("[?] La URL actual no responde. Ingresar nueva URL? (s/n): ").strip().lower()
    if resp == "s" or resp == "si":
        new_url = input("    Nueva URL: ").strip().rstrip("/")
        if new_url:
            if not new_url.startswith("http://") and not new_url.startswith("https://"):
                new_url = "http://" + new_url
            return new_url
    return None


def main():
    print_banner()

    config = load_config()

    if config is None:
        # Primera ejecucion - pedir datos
        config = request_config()
        save_config(config)

    # Verificar conexion
    connected = test_connection(config)

    if not connected:
        new_url = prompt_new_url()
        if new_url:
            config["vps_url"] = new_url
            save_config(config)
            connected = test_connection(config)

        if not connected:
            print()
            print("[!] No se pudo establecer conexion. Verifica:")
            print("    1. La URL del servidor")
            print("    2. La clave API")
            print("    3. Que el servidor VPS este accesible")
            print()
            input("Presiona Enter para salir...")
            sys.exit(1)

    # Iniciar polling
    try:
        poll_loop(config)
    except KeyboardInterrupt:
        pass

    print()
    print("[*] Agente detenido.")
    input("Presiona Enter para salir...")


if __name__ == "__main__":
    main()
