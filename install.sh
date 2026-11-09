#!/usr/bin/env bash
# Instalador de Universal Web para VPS (Ubuntu 22.04+ / Debian 12+).
# Instala PHP-FPM + Caddy (SSL automático), copia la app y la configura.
#
#   sudo ./install.sh --domain=miweb.com --email=yo@miweb.com --password='secreto123' [--name="Mi web"] [--demo]
#   La contraseña también puede llegar sin pasar por la línea de comandos (no queda en `ps` ni en el historial):
#     printf '%s' "$PW" | sudo ./install.sh --domain=… --email=… --password-stdin     (o variable UW_PASSWORD)
#
#   Opcional: --zerotier=<ID_RED_16_HEX>   instala ZeroTier y se une a esa red
#             --admin-only-zerotier         tras instalar, /admin y /mcp solo accesibles desde la red ZeroTier
#   Servidor sin IP pública detrás de un VPS (--edge): ver deploy/ZEROTIER.md y deploy/Caddyfile.edge.example
#
# Volver a ejecutarlo sobre una instalación existente ACTUALIZA el código sin tocar los datos.
# Los dominios nuevos que añadas en el panel reciben certificado SSL automáticamente.
set -euo pipefail

APP_DIR=/var/www/universal-web
NAME="Universal Web"; DOMAIN=""; EMAIL=""; PASSWORD="${UW_PASSWORD:-}"; DEMO=""; ZT_NET=""; ZT_ONLY=0; TIMEZONE="Europe/Madrid"
for arg in "$@"; do
  case "$arg" in
    --domain=*)   DOMAIN="${arg#*=}" ;;
    --email=*)    EMAIL="${arg#*=}" ;;
    --password=*) PASSWORD="${arg#*=}" ;;
    --password-stdin) IFS= read -r PASSWORD || true ;;
    --name=*)     NAME="${arg#*=}" ;;
    --timezone=*) TIMEZONE="${arg#*=}" ;;
    --dir=*)      APP_DIR="${arg#*=}" ;;
    --demo)       DEMO="--demo" ;;
    --zerotier=*) ZT_NET="${arg#*=}" ;;
    --admin-only-zerotier) ZT_ONLY=1 ;;
    -h|--help)    sed -n '2,17p' "$0"; exit 0 ;;
    *) echo "Opción desconocida: $arg" >&2; exit 1 ;;
  esac
done

say()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mError:\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "Ejecuta con sudo."
command -v apt-get >/dev/null || fail "Este instalador es para Ubuntu/Debian. En otros sistemas usa el instalador web (/install)."
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -f "$SRC_DIR/app/bootstrap.php" ] || fail "Ejecuta el script desde la carpeta de Universal Web."

[ -z "$ZT_NET" ] || [[ "$ZT_NET" =~ ^[0-9a-fA-F]{16}$ ]] || fail "--zerotier debe ser un ID de red de 16 caracteres hexadecimales."
[ $ZT_ONLY -eq 0 ] || [ -n "$ZT_NET" ] || fail "--admin-only-zerotier requiere --zerotier=<ID>."

UPDATE=0
[ -f "$APP_DIR/data/config.php" ] && UPDATE=1
if [ $UPDATE -eq 0 ]; then
  [ -n "$DOMAIN" ] || read -rp "Dominio principal (ej. miweb.com): " DOMAIN
  [ -n "$EMAIL" ] || read -rp "Email del administrador: " EMAIL
  if [ -z "$PASSWORD" ]; then read -rsp "Contraseña (mín. 8 caracteres): " PASSWORD; echo; fi
  [ ${#PASSWORD} -ge 8 ] || fail "La contraseña debe tener al menos 8 caracteres."
fi

say "Instalando paquetes (PHP-FPM, SQLite, Caddy)…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q php-fpm php-cli php-sqlite3 php-mbstring php-gd php-curl php-xml php-zip rsync curl
if ! command -v caddy >/dev/null; then
  if ! apt-get install -y -q caddy; then
    say "Añadiendo el repositorio oficial de Caddy…"
    apt-get install -y -q debian-keyring debian-archive-keyring apt-transport-https gnupg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -q && apt-get install -y -q caddy
  fi
fi

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
FPM_SOCK="/run/php/php${PHPV}-fpm.sock"
# Límites de subida acordes con la app (vídeo y audio hasta 100 MB)
cat > "/etc/php/${PHPV}/fpm/conf.d/90-universal-web.ini" <<INI
upload_max_filesize = 100M
post_max_size = 110M
memory_limit = 256M
session.use_strict_mode = 1
expose_php = Off
INI
systemctl enable --now "php${PHPV}-fpm"
systemctl restart "php${PHPV}-fpm"

say "Copiando la aplicación a $APP_DIR…"
mkdir -p "$APP_DIR"
rsync -a --delete \
  --exclude data/ --exclude 'public/media/*/' --exclude .tools/ --exclude .git/ \
  "$SRC_DIR/" "$APP_DIR/"
mkdir -p "$APP_DIR/data" "$APP_DIR/public/media"
chown -R www-data:www-data "$APP_DIR/data" "$APP_DIR/public/media"
chmod 750 "$APP_DIR/data"
chmod +x "$APP_DIR/bin/uw"

if [ $UPDATE -eq 0 ]; then
  say "Creando la base de datos y el administrador…"
  # La contraseña viaja por entorno (UW_PASSWORD), no por argumentos: así no aparece en `ps`.
  export UW_PASSWORD="$PASSWORD"
  sudo --preserve-env=UW_PASSWORD -u www-data php "$APP_DIR/bin/uw" install --name="$NAME" --domain="$DOMAIN" \
    --email="$EMAIL" --timezone="$TIMEZONE" $DEMO
  unset UW_PASSWORD
else
  say "Instalación existente detectada: código actualizado, datos intactos."
fi

say "Configurando Caddy (HTTPS automático)…"
cat > /etc/caddy/Caddyfile <<CADDY
# Generado por Universal Web. Cualquier dominio dado de alta en el panel
# obtiene su certificado SSL automáticamente (on-demand TLS).
{
	on_demand_tls {
		ask http://127.0.0.1:9123/_uw/domain-check
	}
}

# Solo local: Caddy pregunta aquí si un dominio pertenece a alguna web.
http://127.0.0.1:9123 {
	bind 127.0.0.1
	root * $APP_DIR/public
	php_fastcgi unix/$FPM_SOCK
}

https:// {
	tls {
		on_demand
	}
	root * $APP_DIR/public
	encode zstd gzip
	@blocked path /media/*.php /media/*.phtml /media/*.phar
	respond @blocked 403
	header /media/* X-Content-Type-Options "nosniff"
	request_body {
		max_size 110MB
	}
	header -Server
	php_fastcgi unix/$FPM_SOCK
	file_server
}

# HTTP -> HTTPS
http:// {
	redir https://{host}{uri} permanent
}
CADDY
caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null
systemctl enable caddy >/dev/null 2>&1 || true
systemctl reload caddy 2>/dev/null || systemctl restart caddy
# Este servidor da el HTTPS solo a cada dominio nuevo: el MCP se lo dice así a la IA (domain_add / domain_status)
sudo -u www-data php "$APP_DIR/bin/uw" hosting:set --mode=auto_tls >/dev/null || true

# ---------------------------------------------------------------- ZeroTier (opcional)
ZT_CIDR=""
if [ -n "$ZT_NET" ]; then
  say "Configurando ZeroTier…"
  if ! command -v zerotier-cli >/dev/null; then
    curl -s https://install.zerotier.com | bash || fail "No se pudo instalar ZeroTier."
  fi
  systemctl enable --now zerotier-one >/dev/null 2>&1 || true
  zerotier-cli join "$ZT_NET" >/dev/null || fail "No se pudo unir a la red $ZT_NET."
  echo "   Node ID: $(zerotier-cli info | awk '{print $3}')"
  echo "   >> Autoriza este nodo en https://my.zerotier.com/network/$ZT_NET (casilla «Auth») <<"
  say "Esperando IP de ZeroTier (hasta 60 s)…"
  ZT_IF=""; ZT_IP=""
  for _ in $(seq 1 30); do
    ZT_LINE="$(ip -o -f inet addr show 2>/dev/null | awk '$2 ~ /^zt/ {print $2, $4; exit}')"
    if [ -n "$ZT_LINE" ]; then ZT_IF="${ZT_LINE%% *}"; ZT_CIDR_HOST="${ZT_LINE##* }"; ZT_IP="${ZT_CIDR_HOST%/*}"; break; fi
    sleep 2
  done
  if [ -n "$ZT_IP" ]; then
    ZT_CIDR="$(php -r '[$ip, $b] = explode("/", $argv[1]); $m = $b ? (-1 << (32 - (int) $b)) & 0xFFFFFFFF : 0; echo long2ip(ip2long($ip) & $m) . "/" . $b;' "$ZT_CIDR_HOST" 2>/dev/null || true)"
    echo "   IP ZeroTier de este servidor: $ZT_IP (interfaz $ZT_IF, red ${ZT_CIDR:-?})"
  else
    echo "   Aún sin IP: autoriza el nodo en my.zerotier.com y consulta luego con: ip -4 addr show | grep zt"
  fi
  if [ $ZT_ONLY -eq 1 ]; then
    if [ -n "$ZT_CIDR" ]; then
      say "Restringiendo /admin y /mcp a la red ZeroTier ($ZT_CIDR)…"
      sudo -u www-data env UW_ROOT_DIR="$APP_DIR" ZT_CIDR="$ZT_CIDR" php -r 'require getenv("UW_ROOT_DIR")."/app/bootstrap.php"; platform_save(["admin_allowlist" => [getenv("ZT_CIDR"), "127.0.0.1"]]);'
      echo "   Si te quedas fuera:  sudo uw security:reset"
    else
      echo "   No se restringió el panel: no hay IP de ZeroTier todavía. Autoriza el nodo y vuelve a ejecutar con las mismas opciones." >&2
    fi
  fi
fi

# Atajo de CLI: sudo uw <comando>  (backup, token:create, site:list…)
cat > /usr/local/bin/uw <<WRAP
#!/bin/sh
exec sudo -u www-data php "$APP_DIR/bin/uw" "\$@"
WRAP
chmod +x /usr/local/bin/uw

echo
say "¡Listo!"
if [ $UPDATE -eq 0 ]; then
  echo "   Web:    https://$DOMAIN"
  echo "   Panel:  https://$DOMAIN/admin  ($EMAIL)"
  echo "   MCP:    https://$DOMAIN/mcp    (crea un token en Panel → Tokens MCP)"
  echo
  echo "   Apunta el DNS (registro A) de $DOMAIN a la IP de este servidor."
  echo "   Para más webs: añade el dominio en el panel y apunta su DNS aquí. Nada más."
fi
echo "   Copia de seguridad: sudo uw backup --out=/root/uw-backup.tar.gz"
