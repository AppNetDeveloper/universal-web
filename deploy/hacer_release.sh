#!/usr/bin/env bash
# Construye y firma el paquete oficial (desde la 0.4.0 lo hace bin/release; este script se mantiene por compatibilidad).
# Deja en dist/: universal-web-<versión>.zip, universal-web-<versión>.update.json (firmado), universal-web.zip y sus .sha256.
# Uso:  deploy/hacer_release.sh [--key=ruta-de-la-clave]   y sube los 5 ficheros de dist/ más deploy/instalar-vps.sh.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
exec php bin/release "$@"
