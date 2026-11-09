#!/usr/bin/env bash
# Universal Web en un VPS (Ubuntu 22.04+ / Debian 12+) con UNA línea:
#
#   curl -fsSL https://github.com/AppNetDeveloper/universal-web/releases/latest/download/instalar-vps.sh | sudo bash
#
# Te pregunta el dominio y tu email, genera una contraseña fuerte (la verás UNA vez al final), descarga el paquete
# oficial comprobando su huella SHA-256 y ejecuta install.sh (PHP-FPM + Caddy con HTTPS automático).
# La contraseña nunca viaja por la línea de comandos: no queda en el historial ni en `ps`.
#
# Variables opcionales:  UW_PACKAGE_URL (paquete; la huella se lee de <url>.sha256) · UW_DOMAIN · UW_EMAIL · UW_NAME
#   Pruebas: UW_PACKAGE_FILE=/ruta/zip + UW_PACKAGE_SHA256=<huella> (zip local), UW_VPS_DRYRUN=1 (se detiene antes de instalar nada)
set -euo pipefail

PACKAGE_URL="${UW_PACKAGE_URL:-https://github.com/AppNetDeveloper/universal-web/releases/latest/download/universal-web.zip}"
say()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mError:\033[0m %s\n' "$*" >&2; exit 1; }

[ "${UW_VPS_DRYRUN:-0}" = 1 ] || [ "$(id -u)" -eq 0 ] || fail "Ejecútalo con sudo:  curl -fsSL <url> | sudo bash"
[ "${UW_VPS_DRYRUN:-0}" = 1 ] || command -v apt-get >/dev/null || fail "Esto es para Ubuntu/Debian. En otros sistemas usa el instalador de un fichero (instalar.php)."
command -v curl >/dev/null || fail "Falta curl (apt-get install -y curl)."
command -v sha256sum >/dev/null || fail "Falta sha256sum."

# Con `curl | bash` la entrada estándar es el propio script: las respuestas se leen del terminal.
ask() { # ask VAR "pregunta"
  local var="$1" q="$2" v="${!1:-}"
  [ -n "$v" ] && return 0
  { : </dev/tty; } 2>/dev/null || fail "No hay terminal para preguntar. Define UW_DOMAIN y UW_EMAIL en el entorno."
  read -rp "$q " v < /dev/tty
  printf -v "$var" '%s' "$v"
}
DOMAIN="${UW_DOMAIN:-}"; EMAIL="${UW_EMAIL:-}"
ask DOMAIN "Dominio de tu web (ej. miweb.com):"
ask EMAIL  "Tu email (será el usuario administrador):"
DOMAIN="$(printf '%s' "$DOMAIN" | tr 'A-Z' 'a-z' | sed -E 's#^https?://##; s#/.*$##; s/^[[:space:]]+|[[:space:]]+$//g')"
[[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ && "$DOMAIN" == *.* || "$DOMAIN" == localhost ]] || fail "El dominio «$DOMAIN» no parece válido."
[[ "$EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || fail "El email «$EMAIL» no parece válido."

# Contraseña fuerte: 24 caracteres sin símbolos ambiguos.
PASSWORD="$(LC_ALL=C tr -dc 'A-HJ-NP-Za-km-z2-9' < /dev/urandom | head -c 24 || true)"
[ ${#PASSWORD} -eq 24 ] || fail "No pude generar la contraseña."

WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
say "Obteniendo el paquete de Universal Web…"
if [ -n "${UW_PACKAGE_FILE:-}" ]; then
  cp "$UW_PACKAGE_FILE" "$WORK/paquete.zip"; EXPECTED="${UW_PACKAGE_SHA256:-}"
else
  case "$PACKAGE_URL" in https://*) ;; *) fail "UW_PACKAGE_URL debe empezar por https://" ;; esac
  curl -fsSL --proto '=https' --max-time 300 -o "$WORK/paquete.zip" "$PACKAGE_URL" || fail "No se pudo descargar $PACKAGE_URL"
  EXPECTED="$(curl -fsSL --proto '=https' --max-time 60 "$PACKAGE_URL.sha256" | grep -oE '[0-9a-fA-F]{64}' | head -n1 || true)"
fi
EXPECTED="$(printf '%s' "$EXPECTED" | tr 'A-F' 'a-f')"
[[ "$EXPECTED" =~ ^[0-9a-f]{64}$ ]] || fail "No pude obtener la huella SHA-256 del paquete: no instalo nada."
ACTUAL="$(sha256sum "$WORK/paquete.zip" | cut -d' ' -f1)"
[ "$ACTUAL" = "$EXPECTED" ] || fail "La huella SHA-256 NO coincide (esperada $EXPECTED, obtenida $ACTUAL). Paquete alterado o corrupto: no instalo nada."
say "Huella SHA-256 correcta."

if [ "${UW_VPS_DRYRUN:-0}" = 1 ]; then
  say "SIMULACIÓN: se instalarían paquetes y se ejecutaría install.sh para $DOMAIN / $EMAIL (contraseña de ${#PASSWORD} caracteres generada, no mostrada)."
  exit 0
fi

command -v unzip >/dev/null || { say "Instalando unzip…"; DEBIAN_FRONTEND=noninteractive apt-get update -q && DEBIAN_FRONTEND=noninteractive apt-get install -y -q unzip; }
mkdir "$WORK/src" && unzip -q "$WORK/paquete.zip" -d "$WORK/src"
[ -f "$WORK/src/install.sh" ] || fail "El paquete no trae install.sh."

# La contraseña entra por la entrada estándar de install.sh (--password-stdin), nunca por argumentos.
printf '%s' "$PASSWORD" | bash "$WORK/src/install.sh" --domain="$DOMAIN" --email="$EMAIL" --name="${UW_NAME:-Universal Web}" --password-stdin

echo
say "Guarda estos datos AHORA (la contraseña no se vuelve a mostrar):"
echo "   Panel:       https://$DOMAIN/admin"
echo "   Usuario:     $EMAIL"
echo "   Contraseña:  $PASSWORD"
echo "   Cámbiala desde el panel cuando quieras. Apunta el DNS (registro A) de $DOMAIN a la IP de este servidor."
