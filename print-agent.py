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

CONFIG_FILE = Path(sys.executable).parent / "agent-config.json"

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

    local_url = input("URL del servidor local para webhooks (default: http://localhost:8000): ").strip().rstrip("/")
    if not local_url:
        local_url = "http://localhost:8000"
    elif not local_url.startswith("http://") and not local_url.startswith("https://"):
        local_url = "http://" + local_url

    return {"vps_url": url, "api_key": api_key, "poll_interval": 1, "local_server_url": local_url}


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
    ack_url = config["vps_url"] + "/pos/print-jobs/{}/ack"
    webhook_poll_url = "{}/pos/webhooks-jobs/pending".format(config["vps_url"])
    webhook_ack_url = config["vps_url"] + "/pos/webhooks-jobs/{}/ack"
    interval = 1

    # Configuracion local del servidor para forwardear webhooks
    local_server_url = config.get("local_server_url", "http://localhost:8000")

    print("[*] Iniciando ciclo de polling cada {} segundos...".format(interval))
    print("[*] Presiona Ctrl+C para detener.")
    print()

    while True:
        try:
            # === Print Jobs ===
            r = requests.get(poll_url, headers=headers, timeout=15)

            if r.status_code != 200:
                print("[!] Error del servidor ({}), reintentando...".format(r.status_code))
                time.sleep(interval)
                continue

            jobs = r.json()

            if jobs:
                print("[{}] {} trabajo(s) de impresion pendiente(s)".format(
                    time.strftime("%H:%M:%S"), len(jobs)
                ))

            results = []

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
                        print("IMPRESO")
                    else:
                        print("ERROR: {}".format(error))

                    results.append((job, success, error))

                except Exception as e:
                    print("    -> Error procesando trabajo #{}: {}".format(
                        job.get("id", "?"), e
                    ))
                    results.append((job, False, str(e)))

            for job, success, error in results:
                try:
                    status = "completed" if success else "failed"
                    requests.post(
                        ack_url.format(job["id"]),
                        json={"status": status, "error_message": error},
                        headers=headers,
                        timeout=10,
                    )
                except Exception as e:
                    print("    -> Error al confirmar trabajo #{}: {}".format(
                        job.get("id", "?"), e
                    ))

            # === Webhook Jobs ===
            try:
                wh_r = requests.get(webhook_poll_url, headers=headers, timeout=15)
                if wh_r.status_code == 200:
                    webhooks = wh_r.json()
                    if webhooks:
                        print("[{}] {} webhook(s) pendiente(s)".format(
                            time.strftime("%H:%M:%S"), len(webhooks)
                        ))

                    for wh_job in webhooks:
                        try:
                            print("    -> Webhook #{} forwardeando a localhost... ".format(
                                wh_job["id"]
                            ), end="", flush=True)

                            # Parse the raw payload and forward to local server
                            try:
                                payload_data = json.loads(wh_job["raw_payload"])
                            except (json.JSONDecodeError, TypeError):
                                payload_data = wh_job["raw_payload"]

                            # Forward to local server (same endpoint path)
                            forward_headers = {"Content-Type": "application/json"}
                            forward_url = "{}/mp/webhook".format(local_server_url)

                            r_forward = requests.post(
                                forward_url,
                                json=payload_data,
                                headers=forward_headers,
                                timeout=10,
                            )

                            if r_forward.status_code == 200:
                                print("OK")
                            else:
                                print("ERROR: Servidor respondio {}".format(r_forward.status_code))

                            # Always ACK the job to avoid reprocessing
                            requests.post(
                                webhook_ack_url.format(wh_job["id"]),
                                headers=headers,
                                timeout=10,
                            )

                        except Exception as e:
                            print("ERROR: {}".format(e))
                            # Try to ACK anyway
                            try:
                                requests.post(
                                    webhook_ack_url.format(wh_job["id"]),
                                    headers=headers,
                                    timeout=10,
                                )
                            except Exception:
                                pass
            except Exception as e:
                # Webhook polling errors are not critical
                pass

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
    try:
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
                return

        # Iniciar polling
        try:
            poll_loop(config)
        except KeyboardInterrupt:
            pass

        print()
        print("[*] Agente detenido.")
    except Exception as e:
        print()
        print("[!] Error inesperado: {}".format(e))
        import traceback
        traceback.print_exc()
    finally:
        input("Presiona Enter para salir...")


if __name__ == "__main__":
    main()
