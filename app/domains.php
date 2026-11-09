<?php
/**
 * Conectar dominios: lo que la IA necesita para explicárselo a la persona.
 * IP del servidor, si el HTTPS es automático (Caddy con TLS bajo demanda, install.sh) o hay que dar de alta el dominio
 * en el panel del hosting (Plesk/cPanel), y el estado real de cada dominio: DNS y certificado.
 */

declare(strict_types=1);

/** IP pública del servidor: la fijada por el superadmin, la del dominio del panel o la de la propia conexión. */
function server_public_ip(): string
{
    $ip = trim((string) (platform()['public_ip'] ?? ''));
    if ($ip !== '') return $ip;
    $host = (string) (platform()['admin_host'] ?: request_host());
    $resolved = $host !== '' && !filter_var($host, FILTER_VALIDATE_IP) ? gethostbyname($host) : $host;
    if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $resolved;
    $addr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    return filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $addr : '';
}

/** IPv6 pública del servidor (si tiene): la fijada por el superadmin o la del dominio del panel. */
function server_public_ipv6(): string
{
    $ip = trim((string) (platform()['public_ipv6'] ?? ''));
    if ($ip !== '') return $ip;
    $host = (string) (platform()['admin_host'] ?: request_host());
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) return '';
    foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) if (!empty($r['ipv6'])) return $r['ipv6'];
    return '';
}

/** Dominio raíz que se compra en el registrador: mitienda.com, mitienda.com.es (sin el subdominio). */
function domain_zone(string $d): string
{
    $parts = explode('.', $d);
    $n = count($parts);
    $second = ['com', 'org', 'net', 'gob', 'edu', 'nom', 'co', 'ac'];
    $take = $n >= 3 && in_array($parts[$n - 2], $second, true) && strlen($parts[$n - 1]) === 2 ? 3 : 2;
    return implode('.', array_slice($parts, -$take));
}

/** ¿Es la raíz del dominio (mitienda.com, mitienda.com.es) o un subdominio (tienda.mitienda.com)? */
function domain_is_apex(string $d): bool
{
    return domain_zone($d) === $d;
}

/** Registros DNS exactos que hay que crear para que $d apunte a este servidor. */
function domain_dns_records(string $d, string $ip, string $ipv6): array
{
    if (domain_is_magic($d)) return [];
    $apex = domain_is_apex($d);
    $name = $apex ? '@' : substr($d, 0, strlen($d) - strlen(domain_zone($d)) - 1);
    $out = [['type' => 'A', 'name' => $name, 'value' => $ip ?: 'IP DEL SERVIDOR', 'ttl' => '3600 (o «automático»)']];
    if ($ipv6 !== '') $out[] = ['type' => 'AAAA', 'name' => $name, 'value' => $ipv6, 'ttl' => '3600'];
    if ($apex) $out[] = ['type' => 'CNAME', 'name' => 'www', 'value' => $d, 'ttl' => '3600'];
    return $out;
}

/** Guía completa para apuntar un dominio, válida para cualquier registrador. */
function domain_dns_howto(): array
{
    return [
        '1. Entra en la web donde compraste el dominio (el «registrador»: IONOS, GoDaddy, DonDominio, Arsys, Hostinger, Namecheap, OVH, Cloudflare…) y busca «DNS», «Zona DNS» o «Gestionar DNS» del dominio.',
        '2. Borra los registros A, AAAA y CNAME que ya existan para «@» y «www» (suelen apuntar a una página de «dominio aparcado» o a la web antigua). No toques los registros MX ni TXT si usas el correo de ese dominio: el correo seguiría funcionando igual.',
        '3. Crea los registros de arriba exactamente como aparecen (tipo, nombre y valor). En algunos paneles «@» se escribe vacío o con el dominio completo.',
        '4. Guarda. El cambio tarda normalmente de 5 minutos a 2 horas (como mucho 48 horas).',
        '5. Si el dominio usa Cloudflare: deja los registros en «Solo DNS» (nube gris) al menos hasta que el candado HTTPS funcione.',
        '6. Si el registrador solo te deja cambiar los «servidores de nombres» (nameservers) y no los registros, cambia los registros en el servicio donde apunten esos nameservers (por ejemplo, Cloudflare o tu hosting anterior).',
        '7. Avísame cuando esté hecho: lo compruebo' . (server_hosting_mode() === 'auto_tls' ? ' y en ese momento se genera el certificado HTTPS (el candado).' : '; después hay que dar de alta el dominio y su certificado en el panel del hosting.'),
    ];
}

/** «auto_tls» (Caddy de install.sh: el certificado sale solo) o «panel» (Plesk, cPanel, Apache/Nginx a mano). */
function server_hosting_mode(): string
{
    $set = (string) (platform()['hosting'] ?? '');
    if (in_array($set, ['auto_tls', 'panel'], true)) return $set;
    if (stripos((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'caddy') !== false) return 'auto_tls';
    // Caddyfile de install.sh: pregunta a /_uw/domain-check antes de emitir cada certificado
    $caddy = @file_get_contents('/etc/caddy/Caddyfile');
    return is_string($caddy) && str_contains($caddy, '/_uw/domain-check') ? 'auto_tls' : 'panel';
}

/** Dominios que resuelven solos a una IP escrita en el nombre (sslip.io, nip.io): no necesitan DNS. */
function domain_is_magic(string $d): bool
{
    return (bool) preg_match('/\.(sslip|nip)\.io$/', $d);
}

/**
 * Estado de un dominio: a qué IPs apunta y si responde por HTTPS con un certificado válido.
 * Con $issue (servidor con Caddy, auto_tls) la conexión HTTPS a este mismo servidor ES la que hace que Caddy pida el
 * certificado a Let's Encrypt al momento (TLS bajo demanda espera a tenerlo): se le da tiempo y vuelve a mirar.
 */
function domain_probe(string $domain, string $serverIp, bool $issue = false): array
{
    $ips = [];
    foreach (@dns_get_record($domain, DNS_A | DNS_AAAA) ?: [] as $r) $ips[] = $r['ip'] ?? $r['ipv6'] ?? '';
    if (!$ips) $ips = gethostbynamel($domain) ?: []; // algunos hostings no permiten dns_get_record
    $ips = array_values(array_unique(array_filter($ips)));
    $dns = !$ips ? 'missing' : ($serverIp !== '' && in_array($serverIp, $ips, true) ? 'ok' : ($serverIp === '' ? 'unknown' : 'other'));
    $https = 'unknown';
    // Solo se conecta si el dominio apunta a ESTE servidor: nunca a una IP ajena (ni de red privada).
    if ($dns === 'ok') {
        $try = function (int $timeout) use ($domain, $serverIp): bool {
            $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $domain, 'SNI_enabled' => true]]);
            $sock = @stream_socket_client('ssl://' . $serverIp . ':443', $errno, $err, $timeout, STREAM_CLIENT_CONNECT, $ctx);
            if ($sock) fclose($sock);
            return (bool) $sock;
        };
        $ok = $try(5);
        if (!$ok && $issue) {
            @set_time_limit(90);
            $ok = $try(30) || $try(10); // el primer intento lanza la emisión; el segundo ya encuentra el certificado
            if ($ok) audit(null, 'domain.certificate', $domain);
        }
        $https = $ok ? 'ok' : 'no_cert';
    }
    return ['domain' => $domain, 'dns' => $dns, 'points_to' => $ips, 'https' => $https];
}

/**
 * Guía completa para conectar los dominios de una web: estado de cada uno, pasos que faltan y un mensaje listo para
 * que la IA se lo pase a la persona (en su idioma, sin tecnicismos de más).
 */
function domain_guide(SiteService $svc, bool $live = true): array
{
    $ip = server_public_ip();
    $mode = server_hosting_mode();
    // Los nombres locales (localhost, IPs, *.test) no se conectan a nada: no se mencionan
    $domains = array_values(array_filter(site_domains($svc->id()), fn($d) => !filter_var($d, FILTER_VALIDATE_IP) && str_contains($d, '.') && !preg_match('/\.(localhost|local|test|lan|internal)$/', $d)));
    // En un VPS con Caddy se genera el certificado de lo que ya apunta aquí (como mucho 3 dominios por llamada)
    $issued = 0;
    $status = $live ? array_map(function ($d) use ($ip, $mode, &$issued) {
        $r = domain_probe($d, $ip, $mode === 'auto_tls' && $issued < 3);
        if ($r['dns'] === 'ok' && $mode === 'auto_tls') $issued++;
        return $r;
    }, $domains) : [];
    $pending = $live ? array_values(array_filter($status, fn($s) => $s['dns'] !== 'ok' || $s['https'] !== 'ok')) : array_map(fn($d) => ['domain' => $d], $domains);
    $preview = site_preview_link($svc->site);

    $ipv6 = server_public_ipv6();
    $steps = [];
    $records = [];
    foreach ($pending as $p) {
        $d = $p['domain'];
        if (!domain_is_magic($d) && ($p['dns'] ?? 'missing') !== 'ok') {
            $rec = domain_dns_records($d, $ip, $ipv6);
            $records[$d] = $rec;
            $now = ($p['points_to'] ?? []) ? ' (ahora apunta a ' . implode(', ', $p['points_to']) . ')' : '';
            $steps[] = "Apuntar $d$now en el DNS del sitio donde se compró: " . implode('; ', array_map(fn($r) => "{$r['type']} «{$r['name']}» → {$r['value']}", $rec)) . '.';
        }
        if ($mode === 'auto_tls' && ($p['dns'] ?? '') === 'ok' && ($p['https'] ?? '') === 'no_cert') {
            $steps[] = "$d ya apunta a este servidor, pero el certificado HTTPS no se ha podido generar todavía: suele ser cuestión de minutos (o los puertos 80 y 443 del servidor están cerrados). Vuelve a comprobarlo en unos minutos.";
        }
        if ($mode === 'panel' && ($p['https'] ?? 'unknown') !== 'ok') {
            $steps[] = "En el panel del hosting (Plesk/cPanel), el administrador del servidor da de alta $d como alias (o subdominio) apuntando a la carpeta de Universal Web (en Plesk, httpdocs/public) y emite el certificado gratuito de Let's Encrypt.";
        }
    }
    if ($mode === 'auto_tls' && array_filter($pending, fn($p) => ($p['dns'] ?? 'missing') !== 'ok')) $steps[] = 'El certificado HTTPS (el candado) se genera solo en cuanto el DNS apunte al servidor: no hay que hacer nada más.';

    $ready = $live && !$pending;
    $main = $domains[0] ?? '';
    $table = '';
    foreach ($records as $d => $rec) {
        $table .= "\n\nRegistros DNS para $d:\n" . implode("\n", array_map(fn($r) => "  · Tipo {$r['type']} · Nombre {$r['name']} · Valor {$r['value']}", $rec));
    }
    $msg = $ready
        ? "Tu web ya está publicada en https://$main"
        : "Tu web ya está creada y puedes verla aquí: $preview (enlace de vista previa, válido 7 días).\n\nPara que se vea en tu dominio "
          . ($main !== '' ? "($main) " : '') . "falta:\n- " . implode("\n- ", $steps ?: ['añadir tu dominio a la web (dímelo y lo añado)'])
          . $table . ($records ? "\n\nCómo hacerlo:\n" . implode("\n", domain_dns_howto()) : '');
    return [
        'server_ip' => $ip, 'server_ipv6' => $ipv6, 'dns_records' => $records, 'dns_howto' => $records ? domain_dns_howto() : [], 'hosting' => $mode === 'auto_tls' ? 'auto_tls (HTTPS automático)' : 'panel (Plesk/cPanel: alta manual del dominio y del certificado)',
        'preview_url' => $preview, 'domains' => $live ? $status : $domains, 'ready' => $ready, 'steps' => $steps,
        'message_for_user' => $msg,
        'how' => 'Pasa message_for_user a la persona (adáptalo a su idioma). Cuando diga que ha cambiado el DNS, vuelve a llamar a domain_status: lo comprueba' . ($mode === 'auto_tls' ? ' y genera el certificado HTTPS en ese momento (puede tardar hasta un minuto)' : '') . '. Para añadir su dominio usa domain_add.',
    ];
}
