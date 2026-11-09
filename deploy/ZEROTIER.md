# Universal Web + ZeroTier

ZeroTier crea una red privada entre tus equipos. Crea una red en https://my.zerotier.com y anota su ID (16 caracteres) y su rango (por ejemplo `10.147.17.0/24`).

## Escenario 1: panel y MCP solo por ZeroTier (la web sigue pública)

1. En el servidor: `sudo ./install.sh --domain=... --email=... --password=... --zerotier=<ID_RED>`
2. Autoriza el nodo del servidor (te muestra su Node ID) en my.zerotier.com, y une también tu portátil (`zerotier-cli join <ID_RED>`).
3. Restringe el acceso: o bien añade `--admin-only-zerotier` al instalador, o en Panel -> Seguridad y red pon el rango ZeroTier en «IPs o redes permitidas» (la web comprueba que no te dejes fuera).
4. Entra a `https://tudominio/admin` desde un equipo con ZeroTier activo. Desde Internet, `/admin` y `/mcp` devuelven 403; la web pública sigue igual.

## Escenario 2: servidor sin IP pública detrás de un VPS (`--edge`)

```
Visitante --HTTPS--> VPS (IP pública, Caddy) --ZeroTier--> Servidor real (Universal Web)
```

1. Instala ZeroTier en ambos y autoriza los dos nodos. Anota sus IP ZeroTier (VPS `10.147.17.1`, servidor `10.147.17.5` en el ejemplo).
2. Servidor real: instala Universal Web (`install.sh --zerotier=<ID>`) y haz que Caddy escuche también en HTTP plano solo en su IP ZeroTier, añadiendo a `/etc/caddy/Caddyfile`:
   ```
   http://10.147.17.5:8080 {
   	bind 10.147.17.5
   	root * /var/www/universal-web/public
   	php_fastcgi unix//run/php/php8.2-fpm.sock
   	file_server
   }
   ```
   (ajusta la versión de PHP-FPM). Esa IP solo es alcanzable por la red ZeroTier.
3. En Panel -> Seguridad y red del servidor real: añade la IP ZeroTier del VPS (`10.147.17.1`) a «Proxies de confianza». Así se usa la IP real del visitante (`X-Forwarded-For`) y se detecta HTTPS.
4. VPS: copia `deploy/Caddyfile.edge.example` como Caddyfile, cambia la IP del backend y recarga Caddy. El DNS de tus dominios apunta a la IP pública del VPS.
5. Para el panel solo por ZeroTier, pon el rango ZeroTier en «IPs permitidas». Como todo el tráfico público llega a través del VPS, el panel dejará de verse desde Internet (la IP real del visitante no está en la lista), pero tú entrarás desde tu equipo ZeroTier directamente a `http://10.147.17.5:8080/admin` (o con el hosts apuntando a esa IP).

## Notas de seguridad

- Solo pon en «Proxies de confianza» servidores que controlas; si no, cualquiera podría falsificar `X-Forwarded-For`.
- No abras el puerto 8080 del backend a Internet (escucha solo en la IP ZeroTier) y limita en my.zerotier.com quién puede unirse (red privada, nodos autorizados a mano).
- El panel solo acepta navegación normal (Fetch Metadata, COOP, CSP), de modo que el JavaScript personalizado de una web no pueda leerlo ni manejarlo desde el navegador de un administrador.
- Cambia la sesión de administrador a una caducidad corta en Seguridad y red si trabajas desde equipos compartidos.

## Recuperación si te quedas fuera

En el servidor (SSH o consola del proveedor):

```
sudo uw security:reset        # o: sudo -u www-data php bin/uw security:reset
```

Esto vacía la lista de IPs permitidas y los proxies. Después vuelve a configurarlo en el panel.

## Escenario 3: panel privado + web estática pública (lo más seguro)

1. El servidor con Universal Web (panel, MCP y base de datos) solo está en tu red ZeroTier.
2. En **Panel → Publicación** pon la web en modo **Estático en otro servidor** y elige **SSH/rsync** hacia la IP ZeroTier
   del servidor web público (usuario `deploy` con permiso solo en la carpeta de la web).
3. El servidor público solo sirve archivos (`deploy/static-server.nginx.example`): no hay PHP ni base de datos que atacar.
4. Para el formulario de contacto, el servidor público reenvía únicamente `/api/contact` y `/api/form-token` al panel por
   ZeroTier, y ese dominio va en «URL pública de este servidor para el formulario». Ejemplo con Caddy en el servidor público:

```
api.miweb.com {
    @form path /api/contact /api/form-token
    reverse_proxy @form 10.147.17.10:80
    respond 404
}
```
